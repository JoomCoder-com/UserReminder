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
use JoomCoder\Component\UserReminder\Administrator\Extension\UserReminderComponent;

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
        if (!class_exists(UserReminderComponent::class)) {
            $this->logTask('com_userreminder is not installed — aborting', 'error');

            return Status::NO_RUN;
        }

        $params = $event->getArgument('params');
        $runs   = [];

        try {
            $send = UserReminderComponent::service();

            if ((int) ($params->runRegistrationReminders ?? 1) === 1) {
                $runs[] = $send->processRegistrationReminders();
            }

            if ((int) ($params->runInactiveReminders ?? 1) === 1) {
                $runs[] = $send->processInactiveUserReminders();
            }
        } catch (\Throwable $e) {
            $this->logTask('Exception: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine(), 'error');

            return Status::KNOCKOUT;
        }

        $total  = static fn(string $key): int => (int) array_sum(array_column($runs, $key));
        $dryRun = \in_array(true, array_column($runs, 'dryRun'), true);

        $this->logTask(
            sprintf(
                $dryRun
                    ? 'Dry run (debug mode): processed %d candidate(s), would have sent %d, deleted %d, skipped %d, mail errors %d'
                    : 'Processed %d, sent %d, deleted %d, skipped %d, mail errors %d',
                $total('processed'),
                $total($dryRun ? 'simulated' : 'sent'),
                $total('deleted'),
                $total('skipped'),
                $total('errors')
            ),
            $total('errors') > 0 && !$dryRun ? 'warning' : 'info'
        );

        // The routine itself ran fine; per-mail failures are already logged.
        return Status::OK;
    }
}
