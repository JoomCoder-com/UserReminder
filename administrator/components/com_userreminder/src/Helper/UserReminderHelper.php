<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_userreminder
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomCoder\Component\UserReminder\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

/**
 * Admin helper for com_userreminder.
 *
 * Owns the scheduled-task status checks and log-text translation. Admin
 * navigation is the core Joomla submenu declared in the component manifest —
 * no custom sidebar code anymore.
 *
 * @since  4.0.0
 */
final class UserReminderHelper
{
    /**
     * Task type used by plg_task_userreminder.
     *
     * @var  string
     *
     * @since  4.2.0
     */
    public const SCHEDULER_TASK_TYPE = 'userreminder.run';

    /**
     * Whether the Task - UserReminder plugin is installed and enabled.
     *
     * @return  bool
     *
     * @since   4.2.0
     */
    public static function isTaskPluginEnabled(): bool
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('enabled'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('task'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('userreminder'));

        try {
            $db->setQuery($query);

            return (bool) $db->loadResult();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Whether an enabled scheduled task exists for User Reminder.
     *
     * @return  bool  true when at least one enabled userreminder.run task exists.
     *
     * @since   4.2.0
     */
    public static function isScheduledTaskEnabled(): bool
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__scheduler_tasks'))
            ->where($db->quoteName('type') . ' = ' . $db->quote(self::SCHEDULER_TASK_TYPE))
            ->where($db->quoteName('state') . ' = 1');

        try {
            $db->setQuery($query);

            return (int) $db->loadResult() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Ids of the opt-out user groups (#__userreminder_optout_usergroups) —
     * members of these groups never receive reminders. Shared by the send
     * pipeline (SendService) and the dashboard counts (CpanelModel).
     *
     * @return  int[]
     *
     * @since   4.2.1
     */
    public static function getOptOutGroups(): array
    {
        $db = Factory::getDbo();

        try {
            $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName('group_id'))
                    ->from($db->quoteName('#__userreminder_optout_usergroups'))
            );

            return array_values(array_filter(array_map('intval', (array) $db->loadColumn())));
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Fetch the first configured User Reminder scheduled task.
     *
     * @return  object|null  Row with title/state/next_execution/params or null.
     *
     * @since   4.2.0
     */
    public static function getSchedulerTask(): ?object
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['title', 'state', 'next_execution', 'params']))
            ->from($db->quoteName('#__scheduler_tasks'))
            ->where($db->quoteName('type') . ' = ' . $db->quote(self::SCHEDULER_TASK_TYPE))
            ->order($db->quoteName('id') . ' ASC');

        try {
            $db->setQuery($query, 0, 1);

            $row = $db->loadObject();

            return $row ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Translate legacy log descriptions that were stored as raw language keys.
     * Older rows were written by the scheduler context before the component
     * language was loaded, so Text::_() returned e.g.
     * "COM_USERREMINDER_LOG_PREFIX COM_USERREMINDER_LOGIN_REMINDER_SENT".
     * Translate every COM_USERREMINDER_* token found in the string.
     *
     * @param   string|null  $text  Raw description as stored in #__userreminder_log.
     *
     * @return  string
     *
     * @since   4.2.2
     */
    public static function translateLogText(?string $text): string
    {
        $text = (string) $text;

        if ($text === '' || !str_contains($text, 'COM_USERREMINDER_')) {
            return $text;
        }

        return (string) preg_replace_callback(
            '/COM_USERREMINDER_[A-Z0-9_]+/',
            static fn(array $m): string => Text::_($m[0]),
            $text
        );
    }

    /**
     * Human text for a log row: prefer the machine-readable message_key stored
     * since 6.1 (always in sync with the schema, translatable even when the
     * description itself was written pre-translation), fall back to the legacy
     * token-translated description for pre-6.1 rows.
     *
     * @param   string|null  $description  Raw description as stored in #__userreminder_log.
     * @param   string|null  $messageKey   Raw message_key column (may be empty for legacy rows).
     *
     * @return  string
     *
     * @since  6.1.0
     */
    public static function logDisplay(?string $description, ?string $messageKey = ''): string
    {
        $messageKey = (string) $messageKey;

        if ($messageKey !== '' && str_starts_with($messageKey, 'COM_USERREMINDER_')) {
            return Text::_($messageKey);
        }

        return self::translateLogText($description);
    }
}
