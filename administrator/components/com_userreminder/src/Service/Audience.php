<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_userreminder
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomCoder\Component\UserReminder\Administrator\Service;

\defined('_JEXEC') or die;

use JoomCoder\Component\UserReminder\Administrator\Helper\UserReminderHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\QueryInterface;

/**
 * Who a reminder type targets. The send run, the admin lists and the dashboard
 * all start from this query, so they always agree on the same users.
 *
 * The query reads #__users AS a, LEFT JOINs the reminder state as b, and leaves
 * out opted-out users and members of the opt-out user groups. Callers add their
 * own SELECT, extra filters and ORDER BY.
 *
 * Dates are UTC values built in PHP and quoted inline (not bound): the send run
 * UNIONs two of these queries, and union() drops the second query's bindings.
 *
 * @since  6.1.0
 */
final class Audience
{
    /**
     * Opt-out group ids, read once per request.
     *
     * @var  int[]|null
     *
     * @since  6.1.0
     */
    private static ?array $optOutGroups = null;

    /**
     * Base query for one or more reminder types (OR-ed together).
     *
     * @param   DatabaseInterface  $db
     * @param   ReminderOptions    $options
     * @param   int                ...$types  SendService::TYPE_* constants.
     *
     * @return  QueryInterface
     *
     * @since  6.1.0
     */
    public static function query(DatabaseInterface $db, ReminderOptions $options, int ...$types): QueryInterface
    {
        $query = $db->getQuery(true)
            ->from($db->quoteName('#__users', 'a'))
            ->leftJoin($db->quoteName('#__userreminder', 'b') . ' ON ' . $db->quoteName('b.userid') . ' = ' . $db->quoteName('a.id'))
            ->leftJoin($db->quoteName('#__userreminder_optout', 'o') . ' ON ' . $db->quoteName('o.user_id') . ' = ' . $db->quoteName('a.id'))
            ->where($db->quoteName('o.user_id') . ' IS NULL');

        $query->where('(' . implode(' OR ', array_map(static fn(int $type) => self::condition($db, $options, $type), $types)) . ')');

        $groups = self::optOutGroups();

        if ($groups) {
            $members = $db->getQuery(true)
                ->select($db->quoteName('gm.user_id'))
                ->from($db->quoteName('#__user_usergroup_map', 'gm'))
                ->where($db->quoteName('gm.group_id') . ' IN (' . implode(',', $groups) . ')');

            $query->where($db->quoteName('a.id') . ' NOT IN (' . $members . ')');
        }

        return $query;
    }

    /**
     * Account state (and age window) of one reminder type.
     *
     * @param   DatabaseInterface  $db
     * @param   ReminderOptions    $options
     * @param   int                $type
     *
     * @return  string
     *
     * @since  6.1.0
     */
    private static function condition(DatabaseInterface $db, ReminderOptions $options, int $type): string
    {
        $empty = $db->quote('');

        if ($type === SendService::TYPE_INACTIVE_USER) {
            return '(a.block = 0 AND a.lastvisitDate IS NOT NULL AND a.lastvisitDate < '
                . $db->quote(ReminderOptions::utcNow('-' . $options->inactivityDays() . ' days')) . ')';
        }

        $state = $type === SendService::TYPE_NOT_ACTIVATED
            ? 'a.block >= 1 AND a.activation <> ' . $empty
            : 'a.block = 0 AND a.activation = ' . $empty;

        // Without "send first reminder immediately", the first reminder waits
        // until the account is as old as the days between reminders.
        $age = $options->firstImmediate()
            ? ''
            : ' AND a.registerDate < ' . $db->quote(ReminderOptions::utcNow('-' . $options->cooldownDays() . ' days'));

        return '(' . $state . ' AND a.lastvisitDate IS NULL' . $age . ')';
    }

    /**
     * Ids of the opt-out user groups (cached for the request).
     *
     * @return  int[]
     *
     * @since  6.1.0
     */
    private static function optOutGroups(): array
    {
        return self::$optOutGroups ??= UserReminderHelper::getOptOutGroups();
    }
}
