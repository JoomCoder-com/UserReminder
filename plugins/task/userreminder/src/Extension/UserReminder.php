<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Task.userreminder
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomCoder\Plugin\Task\UserReminder\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Event\SubscriberInterface;
use JoomCoder\Component\UserReminder\Administrator\Service\SendService;

/**
 * Scheduled-tasks plugin for UserReminder. Replaces the legacy
 * onAfterRoute page-hit scheduler (plg_system_userreminder) — runs are
 * managed, logged and retried by com_scheduler instead.
 *
 * @since  4.2.0
 */
final class UserReminder extends CMSPlugin implements SubscriberInterface
{
    use TaskPluginTrait;

    /**
     * @var  string[]
     *
     * @since  4.2.0
     */
    private const TASKS_MAP = [
        'userreminder.run' => [
            'langConstPrefix' => 'PLG_TASK_USERREMINDER',
            'method'          => 'runReminders',
            'form'            => 'userreminderForm',
        ],
    ];

    /**
     * @var  boolean
     *
     * @since  4.2.0
     */
    protected $autoloadLanguage = true;

    /**
     * @return  string[]
     *
     * @since   4.2.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList'    => 'advertiseRoutines',
            'onExecuteTask'        => 'standardRoutineHandler',
            'onContentPrepareForm' => 'enhanceTaskItemForm',
        ];
    }

    /**
     * Run the configured reminder pipelines.
     *
     * @param   ExecuteTaskEvent  $event  The onExecuteTask event.
     *
     * @return  int  Routine exit code.
     *
     * @since   4.2.0
     */
    private function runReminders(ExecuteTaskEvent $event): int
    {
        if (!class_exists(SendService::class)) {
            $this->logTask('com_userreminder is not installed — aborting', 'error');

            return Status::NO_RUN;
        }

        try {
            /** @var SendService $send */
            $send   = SendService::instance();
            $params = $event->getArgument('params');

            $regOk = $inactiveOk = true;
            $regStats = ['processed' => 0, 'sent' => 0, 'deleted' => 0, 'errors' => 0];
            $inactiveStats = ['processed' => 0, 'sent' => 0, 'deleted' => 0, 'errors' => 0];

            if ((int) ($params->runRegistrationReminders ?? 1) === 1) {
                $regStats = $send->processRegistrationReminders(true, 0, 0);

                if ($regStats['errors'] > 0) {
                    $regOk = false;
                }
            }

            if ((int) ($params->runInactiveReminders ?? 1) === 1) {
                $inactiveStats = $send->processInactiveUserReminders(true, 0, 0);

                if ($inactiveStats['errors'] > 0) {
                    $inactiveOk = false;
                }
            }
        } catch (\Throwable $e) {
            $this->logTask('Exception: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine(), 'error');

            return Status::KNOCKOUT;
        }

        $this->logTask(
            sprintf(
                'Processed %d, sent %d, deleted %d, mail errors %d',
                $regStats['processed'] + $inactiveStats['processed'],
                $regStats['sent'] + $inactiveStats['sent'],
                $regStats['deleted'] + $inactiveStats['deleted'],
                $regStats['errors'] + $inactiveStats['errors']
            ),
            $regOk && $inactiveOk ? 'info' : 'warning'
        );

        // The routine itself ran fine; per-mail failures are already logged.
        return Status::OK;
    }
}
