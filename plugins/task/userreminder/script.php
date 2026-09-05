<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Task.userreminder
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerScript;

/**
 * Installer script for plg_task_userreminder.
 *
 * On install/update: enables the plugin, registers a daily scheduled task
 * (once) and disables the legacy page-hit system plugin.
 *
 * @since  4.2.0
 */
class PlgTaskUserreminderInstallerScript extends InstallerScript
{
    /**
     * Runs after install / discover_install / update.
     *
     * @param   string  $type      Install type.
     * @param   object  $parent    Installer adapter.
     *
     * @return  bool
     *
     * @since   4.2.0
     */
    public function postflight($type, $parent): bool
    {
        if ($type === 'uninstall') {
            return true;
        }

        $db = Factory::getDbo();

        // Enable the task plugin (belt & braces with the package manifest enable attr).
        try {
            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__extensions'))
                    ->set($db->quoteName('enabled') . ' = 1')
                    ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                    ->where($db->quoteName('element') . ' = ' . $db->quote('userreminder'))
                    ->where($db->quoteName('folder') . ' = ' . $db->quote('task'))
            );
            $db->execute();
        } catch (\Throwable) {
            // Non-fatal — the package manifest also enables it.
        }

        // Disable the legacy page-hit scheduler so it can never double-send.
        try {
            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__extensions'))
                    ->set($db->quoteName('enabled') . ' = 0')
                    ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                    ->where($db->quoteName('element') . ' = ' . $db->quote('userreminder'))
                    ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
            );
            $db->execute();
        } catch (\Throwable) {
            // Non-fatal — the system plugin is a no-op stub since 4.2.0 anyway.
        }

        // Register a scheduled task once, so the migration "just works".
        try {
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__scheduler_tasks'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('userreminder.run'));
            $db->setQuery($query);

            if ((int) $db->loadResult() === 0) {
                $now = Factory::getDate('now', 'UTC');
                $next = (clone $now)->modify('+1 day');

                $task = $db->getQuery(true)
                    ->insert($db->quoteName('#__scheduler_tasks'))
                    ->columns([
                        $db->quoteName('title'),
                        $db->quoteName('type'),
                        $db->quoteName('execution_rules'),
                        $db->quoteName('cron_rules'),
                        $db->quoteName('state'),
                        $db->quoteName('next_execution'),
                        $db->quoteName('priority'),
                        $db->quoteName('ordering'),
                        $db->quoteName('params'),
                        $db->quoteName('created'),
                        $db->quoteName('created_by'),
                    ])
                    ->values(implode(',', [
                        $db->quote('User Reminder emails'),
                        $db->quote('userreminder.run'),
                        $db->quote('{"rule-type":"interval-hours","interval-hours":24,"exec-time":"03:00","exec-day":"0"}'),
                        $db->quote('{"type":"interval","exp":"PT24H"}'),
                        1,
                        $db->quote($next->toSql(true)),
                        0,
                        0,
                        $db->quote('{"runRegistrationReminders":1,"runInactiveReminders":1}'),
                        $db->quote($now->toSql(true)),
                        (int) (Factory::getApplication()->getIdentity()->id ?? 0),
                    ]));

                $db->setQuery($task);
                $db->execute();
            }
        } catch (\Throwable) {
            // If com_scheduler is not installed (Joomla < 4.1) there is nothing to register.
        }

        return true;
    }
}
