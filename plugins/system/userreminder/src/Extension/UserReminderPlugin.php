<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  system.userreminder
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomCoder\Plugin\System\UserReminder\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\Event;
use JoomCoder\Component\UserReminder\Administrator\Service\SendService;

/**
 * System plugin for the UserReminder scheduler.
 *
 * Runs on `onAfterRoute` (NOT `onAfterRender` like the legacy code did —
 * `onAfterRender` fires for every response, including the admin and JSON
 * APIs, and would slow down the site for no reason). On every request we
 * compare "today" against the scheduled run settings, and if the conditions
 * match we kick off the SendService and record the run in
 * `#__userreminder_sch` so we don't double-fire when the plugin fires
 * multiple times per day across requests.
 *
 * @since  4.0.0
 */
final class UserReminderPlugin extends CMSPlugin
{
    /**
     * @var  bool
     *
     * @since  4.0.0
     */
    private $executedToday = false;

    /**
     * Hook for Joomla's plugin dispatcher.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public function onAfterRoute(Event $event): void
    {
        $params = ComponentHelper::getParams('com_userreminder');

        if ((int) $params->get('enabledScheduledExecution', 0) !== 1) {
            return;
        }

        $app = Factory::getApplication();
        if ($app->isClient('cli') || $app->isClient('api')) {
            // Skip CLI / API — let dedicated scheduled-task plugins drive the run.
            return;
        }

        // Run once per request only.
        if ($this->executedToday) {
            return;
        }
        $this->executedToday = true;

        $type = (int) $params->get('scheduledExecutionType', 1);
        $time = (int) $params->get('scheduledExecutionTime', 0);

        $now = Factory::getDate();
        $day = (int) $now->format('d');
        $month = (int) $now->format('m');
        $year = (int) $now->format('Y');
        $dow = (int) $now->format('N');
        $hour = (int) $now->format('G');

        $shouldRun = match ($type) {
            1 => $hour >= $time,
            2 => $dow === $time,
            3 => $day === $time,
            default => false,
        };

        if (!$shouldRun) {
            return;
        }

        // Have we already recorded a run for today?
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__userreminder_sch'))
            ->where($db->quoteName('daysent') . ' = ' . $day)
            ->where($db->quoteName('monthsent') . ' = ' . $month)
            ->where($db->quoteName('yearsent') . ' = ' . $year);

        $db->setQuery($query);

        if ((int) $db->loadResult() > 0) {
            return;
        }

        // Record this run.
        $insert = $db->getQuery(true)
            ->insert($db->quoteName('#__userreminder_sch'))
            ->columns([
                $db->quoteName('daysent'),
                $db->quoteName('monthsent'),
                $db->quoteName('yearsent'),
                $db->quoteName('timesent'),
            ])
            ->values(implode(',', [$day, $month, $year, time()]));

        $db->setQuery($insert);
        $db->execute();

        try {
            /** @var SendService $send */
            $send = Factory::getContainer()->get(SendService::class);
        } catch (\Throwable) {
            $send = new SendService(Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class));
        }
        $numberEmail = (int) $params->get('number_email', 50);

        if ((int) $params->get('enabledScheduledActivationReminders', 1) === 1) {
            $send->processRegistrationReminders(true, 0, $numberEmail);
        }

        if ((int) $params->get('enabledScheduledUserReminders', 1) === 1) {
            $send->processInactiveUserReminders(true, 0, $numberEmail);
        }
    }
}