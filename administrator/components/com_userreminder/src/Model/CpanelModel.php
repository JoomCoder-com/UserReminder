<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_userreminder
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomCoder\Component\UserReminder\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\DatabaseInterface;
use JoomCoder\Component\UserReminder\Administrator\Helper\UserReminderHelper;

/**
 * Cpanel / dashboard model — aggregates all KPIs, analytics and health checks
 * for the rebuilt dashboard.
 *
 * All heavy counts are COUNT(a.id) or small GROUP BYs, sargable, and cached
 * for 10 minutes. Every query is wrapped in try/catch so the dashboard never
 * white-screens on large sites.
 *
 * @since  4.1.0
 */
class CpanelModel extends BaseDatabaseModel
{
    public const CACHE_TTL = 600;

    public const CACHE_GROUP = 'com_userreminder.dashboard';

    public function getForm($data = [], $loadData = true, $formName = null): ?\Joomla\CMS\Form\Form
    {
        return null;
    }

    /**
     * Return the full dashboard payload (cached).
     *
     * @param   bool  $forceRefresh  Bypass cache when the user hits Refresh.
     *
     * @return  array
     *
     * @since   4.1.0
     */
    public function getDashboard(bool $forceRefresh = false): array
    {
        $params = ComponentHelper::getParams('com_userreminder');

        // Build a cache key that busts when thresholds change.
        $cacheKey = $this->getCacheKey($params);

        if (!$forceRefresh) {
            $cached = $this->getCached($cacheKey);
            if (is_array($cached) && isset($cached['generatedAt'])) {
                return $cached;
            }
        }

        $data = $this->buildDashboard($params);
        $data['generatedAt'] = Factory::getDate()->toSql();
        $data['cacheKey']    = $cacheKey;

        $this->storeCached($cacheKey, $data);

        return $data;
    }

    /**
     * Clear the dashboard cache group.
     *
     * @return  void
     *
     * @since   4.1.0
     */
    public function clearDashboardCache(): void
    {
        foreach (['callback', 'output'] as $type) {
            try {
                $factory = Factory::getContainer()->get(CacheControllerFactoryInterface::class);
                $ctrl    = $factory->createCacheController($type, ['defaultgroup' => self::CACHE_GROUP]);
                $ctrl->clean();
            } catch (\Throwable $e) {
                Log::add('User Reminder: could not clean dashboard cache: ' . $e->getMessage(), Log::WARNING, 'com_userreminder');
            }
        }
    }

    /**
     * Build the dashboard payload without touching cache.
     *
     * @param   \Joomla\Registry\Registry  $params
     *
     * @return  array
     *
     * @since   4.1.0
     */
    private function buildDashboard(\Joomla\Registry\Registry $params): array
    {
        $db = $this->getDatabase();

        // Thresholds from config.
        $days         = max(1, (int) $params->get('numberOfDays', 1));
        $existingDays = max(1, (int) $params->get('numberOfDaysExistingUser', 180));
        $maxReminders = max(1, (int) $params->get('numberOfReminders', 1));
        $batchSize    = max(1, (int) $params->get('number_email', 50));

        // Cutoff datetimes — sargable: column < cutoff, never DATE_ADD(col) < NOW().
        // With "send first reminder immediately", the registration-age cutoff
        // does not apply to first sends — null means "no age filter".
        $imed           = (int) $params->get('enabledSendImed', 0) === 1;
        $cutoffReg      = $imed ? null : Factory::getDate()->modify('-' . $days . ' days')->toSql();
        $cutoffExisting = Factory::getDate()->modify('-' . $existingDays . ' days')->toSql();
        $cutoffCoolDown = Factory::getDate()->modify('-' . $days . ' days')->toSql();

        // Core KPIs — all COUNTs, no ORDER BY, no SELECT *.
        $pendingActivation = $this->countPendingActivation($db, $cutoffReg);
        $neverLoggedIn     = $this->countNeverLoggedIn($db, $cutoffReg);
        $inactiveUsers     = $this->countInactiveUsers($db, $cutoffExisting);
        $dueNow            = $this->countDueNow($db, $cutoffReg, $cutoffExisting, $cutoffCoolDown, $maxReminders, $days, $existingDays);
        $sent              = $this->countSent($db);
        $optedOut          = $this->countOptedOut($db);
        $totalUsers        = $this->countTotalUsers($db);

        // Analytics — tiny aggregates.
        $trend  = $this->getTrend30($db);
        $byType = $this->getByType($db);
        $aging  = $this->getAging($db, $cutoffReg, $cutoffExisting, $days, $existingDays);

        // Preview tables — capped, explicit columns.
        $recentLogs    = $this->getRecentLogs($db, 10);
        $oldestPending = $this->getOldestPending($db, $cutoffReg, 5);
        $longestInactive = $this->getLongestInactive($db, $cutoffExisting, 5);

        // Health + config snapshot.
        $health         = $this->buildHealth($params, $pendingActivation, $neverLoggedIn, $inactiveUsers, $dueNow, $batchSize);
        $configSnapshot = $this->buildConfigSnapshot($params, $days, $existingDays, $maxReminders, $batchSize);

        return [
            'kpi' => [
                'pendingActivation' => $pendingActivation,
                'neverLoggedIn'     => $neverLoggedIn,
                'inactiveUsers'     => $inactiveUsers,
                'pendingTotal'      => $pendingActivation + $neverLoggedIn,
                'dueNow'            => $dueNow,
                'sentTotal'         => $sent['total'],
                'sent7d'            => $sent['d7'],
                'sent30d'           => $sent['d30'],
                'optedOut'          => $optedOut,
                'totalUsers'        => $totalUsers,
            ],
            'analytics' => [
                'trend'  => $trend,
                'byType' => $byType,
                'aging'  => $aging,
            ],
            'recent' => [
                'logs'            => $recentLogs,
                'oldestPending'   => $oldestPending,
                'longestInactive' => $longestInactive,
            ],
            'health'         => $health,
            'configSnapshot' => $configSnapshot,
            'thresholds' => [
                'days'         => $days,
                'existingDays' => $existingDays,
                'maxReminders' => $maxReminders,
                'batchSize'    => $batchSize,
            ],
        ];
    }

    private function countPendingActivation(DatabaseInterface $db, ?string $cutoff): int
    {
        try {
            $query = $db->getQuery(true)
                ->select('COUNT(a.id)')
                ->from($db->quoteName('#__users', 'a'))
                ->leftJoin($db->quoteName('#__userreminder_optout', 'o') . ' ON o.user_id = a.id')
                ->where('o.user_id IS NULL')
                ->where('a.block >= 1')
                ->where('a.activation <> ' . $db->quote(''))
                ->where('a.lastvisitDate IS NULL');

            if ($cutoff !== null) {
                $query->where($db->quoteName('a.registerDate') . ' < ' . $db->quote($cutoff));
            }

            $db->setQuery($query);

            return (int) $db->loadResult();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function countNeverLoggedIn(DatabaseInterface $db, ?string $cutoff): int
    {
        try {
            $query = $db->getQuery(true)
                ->select('COUNT(a.id)')
                ->from($db->quoteName('#__users', 'a'))
                ->leftJoin($db->quoteName('#__userreminder_optout', 'o') . ' ON o.user_id = a.id')
                ->where('o.user_id IS NULL')
                ->where('a.block = 0')
                ->where('a.activation = ' . $db->quote(''))
                ->where('a.lastvisitDate IS NULL');

            if ($cutoff !== null) {
                $query->where($db->quoteName('a.registerDate') . ' < ' . $db->quote($cutoff));
            }

            $db->setQuery($query);

            return (int) $db->loadResult();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function countInactiveUsers(DatabaseInterface $db, string $cutoffExisting): int
    {
        try {
            $query = $db->getQuery(true)
                ->select('COUNT(a.id)')
                ->from($db->quoteName('#__users', 'a'))
                ->leftJoin($db->quoteName('#__userreminder_optout', 'o') . ' ON o.user_id = a.id')
                ->where('o.user_id IS NULL')
                ->where('a.block = 0')
                ->where('a.lastvisitDate IS NOT NULL')
                ->where($db->quoteName('a.lastvisitDate') . ' < ' . $db->quote($cutoffExisting));
            $db->setQuery($query);

            return (int) $db->loadResult();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Users whose cooldown has elapsed and who still have reminders left.
     * We sum three small COUNTs in PHP to avoid a UNION temp table.
     */
    private function countDueNow(
        DatabaseInterface $db,
        ?string $cutoffReg,
        string $cutoffExisting,
        string $cutoffCoolDown,
        int $maxReminders,
        int $days,
        int $existingDays
    ): int {
        $total = 0;

        // Type 1: not activated, blocked, cooldown elapsed or never sent.
        try {
            $q = $db->getQuery(true)
                ->select('COUNT(a.id)')
                ->from($db->quoteName('#__users', 'a'))
                ->leftJoin($db->quoteName('#__userreminder', 'b') . ' ON b.userid = a.id')
                ->leftJoin($db->quoteName('#__userreminder_optout', 'o') . ' ON o.user_id = a.id')
                ->where('o.user_id IS NULL')
                ->where('a.block >= 1')
                ->where('a.activation <> ' . $db->quote(''))
                ->where('a.lastvisitDate IS NULL');

            if ($cutoffReg !== null) {
                $q->where($db->quoteName('a.registerDate') . ' < ' . $db->quote($cutoffReg));
            }

            $q->where('(b.userid IS NULL OR b.remindernumber < ' . $maxReminders . ')')
                ->where('(b.datesent IS NULL OR b.datesent < ' . $db->quote($cutoffCoolDown) . ')');
            $db->setQuery($q);
            $total += (int) $db->loadResult();
        } catch (\Throwable) {
        }

        // Type 2: never logged in, block=0, respect the registration-age window
        // unless "send first reminder immediately" is on, plus maxReminders.
        try {
            $q = $db->getQuery(true)
                ->select('COUNT(a.id)')
                ->from($db->quoteName('#__users', 'a'))
                ->leftJoin($db->quoteName('#__userreminder', 'b') . ' ON b.userid = a.id')
                ->leftJoin($db->quoteName('#__userreminder_optout', 'o') . ' ON o.user_id = a.id')
                ->where('o.user_id IS NULL')
                ->where('a.block = 0')
                ->where('a.activation = ' . $db->quote(''))
                ->where('a.lastvisitDate IS NULL');

            if ($cutoffReg !== null) {
                $q->where($db->quoteName('a.registerDate') . ' < ' . $db->quote($cutoffReg));
            }

            $q->where('(b.userid IS NULL OR b.remindernumber < ' . $maxReminders . ')')
                ->where('(b.datesent IS NULL OR b.datesent < ' . $db->quote($cutoffCoolDown) . ')');
            $db->setQuery($q);
            $total += (int) $db->loadResult();
        } catch (\Throwable) {
        }

        // Type 3: inactive existing users.
        try {
            $q = $db->getQuery(true)
                ->select('COUNT(a.id)')
                ->from($db->quoteName('#__users', 'a'))
                ->leftJoin($db->quoteName('#__userreminder', 'b') . ' ON b.userid = a.id')
                ->leftJoin($db->quoteName('#__userreminder_optout', 'o') . ' ON o.user_id = a.id')
                ->where('o.user_id IS NULL')
                ->where('a.block = 0')
                ->where('a.lastvisitDate IS NOT NULL')
                ->where($db->quoteName('a.lastvisitDate') . ' < ' . $db->quote($cutoffExisting))
                ->where('(b.userid IS NULL OR b.remindernumber < ' . $maxReminders . ')')
                ->where('(b.datesent IS NULL OR b.datesent < ' . $db->quote($cutoffCoolDown) . ')');
            $db->setQuery($q);
            $total += (int) $db->loadResult();
        } catch (\Throwable) {
        }

        return $total;
    }

    private function countSent(DatabaseInterface $db): array
    {
        $out = ['total' => 0, 'd7' => 0, 'd30' => 0];

        try {
            $db->setQuery('SELECT COUNT(*) FROM ' . $db->quoteName('#__userreminder_log'));
            $out['total'] = (int) $db->loadResult();
        } catch (\Throwable) {
        }

        try {
            $cut7 = Factory::getDate()->modify('-7 days')->toSql();
            $q = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__userreminder_log'))
                ->where($db->quoteName('date') . ' >= ' . $db->quote($cut7));
            $db->setQuery($q);
            $out['d7'] = (int) $db->loadResult();
        } catch (\Throwable) {
        }

        try {
            $cut30 = Factory::getDate()->modify('-30 days')->toSql();
            $q = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__userreminder_log'))
                ->where($db->quoteName('date') . ' >= ' . $db->quote($cut30));
            $db->setQuery($q);
            $out['d30'] = (int) $db->loadResult();
        } catch (\Throwable) {
        }

        return $out;
    }

    private function countOptedOut(DatabaseInterface $db): int
    {
        try {
            $db->setQuery('SELECT COUNT(*) FROM ' . $db->quoteName('#__userreminder_optout'));

            return (int) $db->loadResult();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function countTotalUsers(DatabaseInterface $db): int
    {
        try {
            $db->setQuery('SELECT COUNT(*) FROM ' . $db->quoteName('#__users'));

            return (int) $db->loadResult();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Last 30 days trend — at most 31 rows, uses idx_ur_log_date.
     *
     * @return  array<int, array{date:string,count:int}>
     */
    private function getTrend30(DatabaseInterface $db): array
    {
        try {
            $cut30 = Factory::getDate()->modify('-30 days')->toSql();
            $q = $db->getQuery(true)
                ->select('DATE(' . $db->quoteName('date') . ') AS d, COUNT(*) AS c')
                ->from($db->quoteName('#__userreminder_log'))
                ->where($db->quoteName('date') . ' >= ' . $db->quote($cut30))
                ->group('DATE(' . $db->quoteName('date') . ')')
                ->order('d ASC');
            $db->setQuery($q);
            $rows = $db->loadAssocList() ?: [];

            // Fill missing days with 0 so the chart is continuous.
            $map = [];
            foreach ($rows as $r) {
                $map[$r['d']] = (int) $r['c'];
            }

            $out = [];
            $today = new \DateTimeImmutable('today');
            for ($i = 29; $i >= 0; $i--) {
                $d = $today->modify('-' . $i . ' days')->format('Y-m-d');
                $out[] = ['date' => $d, 'count' => $map[$d] ?? 0];
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Donut by type — 3 rows from #__userreminder.type.
     *
     * @return  array<int,int>  type => count
     */
    private function getByType(DatabaseInterface $db): array
    {
        try {
            $q = $db->getQuery(true)
                ->select($db->quoteName('type') . ', COUNT(*) AS c')
                ->from($db->quoteName('#__userreminder'))
                ->group($db->quoteName('type'));
            $db->setQuery($q);
            $rows = $db->loadAssocList() ?: [];
            $out  = [1 => 0, 2 => 0, 3 => 0];
            foreach ($rows as $r) {
                $t = (int) $r['type'];
                if (isset($out[$t])) {
                    $out[$t] = (int) $r['c'];
                } else {
                    $out[$t] = (int) $r['c'];
                }
            }

            return $out;
        } catch (\Throwable) {
            return [1 => 0, 2 => 0, 3 => 0];
        }
    }

    /**
     * Aging buckets — 3 COUNTs per dimension, no full scan GROUP BY.
     *
     * @return  array
     */
    private function getAging(DatabaseInterface $db, ?string $cutoffReg, string $cutoffExisting, int $days, int $existingDays): array
    {
        $pending = ['1-7' => 0, '8-30' => 0, '30+' => 0];
        $inactive = ['just' => 0, '2x' => 0, '4x' => 0];

        // Pending aging based on registerDate (with the immediate-send option,
        // the youngest bucket covers everything newer than 7 days).
        try {
            $d7  = Factory::getDate()->modify('-7 days')->toSql();
            $d30 = Factory::getDate()->modify('-30 days')->toSql();

            // 1-7 days overdue (registered between cutoff and cutoff+7)
            $q = $db->getQuery(true)
                ->select('COUNT(a.id)')
                ->from($db->quoteName('#__users', 'a'))
                ->leftJoin($db->quoteName('#__userreminder_optout', 'o') . ' ON o.user_id = a.id')
                ->where('o.user_id IS NULL')
                ->where('a.block >= 1')
                ->where('a.activation <> ' . $db->quote(''))
                ->where('a.lastvisitDate IS NULL')
                ->where($db->quoteName('a.registerDate') . ' >= ' . $db->quote($d7));

            if ($cutoffReg !== null) {
                $q->where($db->quoteName('a.registerDate') . ' < ' . $db->quote($cutoffReg));
            }

            $db->setQuery($q);
            $pending['1-7'] = (int) $db->loadResult();

            $q = $db->getQuery(true)
                ->select('COUNT(a.id)')
                ->from($db->quoteName('#__users', 'a'))
                ->leftJoin($db->quoteName('#__userreminder_optout', 'o') . ' ON o.user_id = a.id')
                ->where('o.user_id IS NULL')
                ->where('a.block >= 1')
                ->where('a.activation <> ' . $db->quote(''))
                ->where('a.lastvisitDate IS NULL')
                ->where($db->quoteName('a.registerDate') . ' < ' . $db->quote($d7))
                ->where($db->quoteName('a.registerDate') . ' >= ' . $db->quote($d30));
            $db->setQuery($q);
            $pending['8-30'] = (int) $db->loadResult();

            $q = $db->getQuery(true)
                ->select('COUNT(a.id)')
                ->from($db->quoteName('#__users', 'a'))
                ->leftJoin($db->quoteName('#__userreminder_optout', 'o') . ' ON o.user_id = a.id')
                ->where('o.user_id IS NULL')
                ->where('a.block >= 1')
                ->where('a.activation <> ' . $db->quote(''))
                ->where('a.lastvisitDate IS NULL')
                ->where($db->quoteName('a.registerDate') . ' < ' . $db->quote($d30));
            $db->setQuery($q);
            $pending['30+'] = (int) $db->loadResult();
        } catch (\Throwable) {
        }

        // Inactive aging: just over threshold, 2x, 4x.
        try {
            $d2x = Factory::getDate()->modify('-' . ($existingDays * 2) . ' days')->toSql();
            $d4x = Factory::getDate()->modify('-' . ($existingDays * 4) . ' days')->toSql();

            $q = $db->getQuery(true)
                ->select('COUNT(a.id)')
                ->from($db->quoteName('#__users', 'a'))
                ->leftJoin($db->quoteName('#__userreminder_optout', 'o') . ' ON o.user_id = a.id')
                ->where('o.user_id IS NULL')
                ->where('a.block = 0')
                ->where('a.lastvisitDate IS NOT NULL')
                ->where($db->quoteName('a.lastvisitDate') . ' < ' . $db->quote($cutoffExisting))
                ->where($db->quoteName('a.lastvisitDate') . ' >= ' . $db->quote($d2x));
            $db->setQuery($q);
            $inactive['just'] = (int) $db->loadResult();

            $q = $db->getQuery(true)
                ->select('COUNT(a.id)')
                ->from($db->quoteName('#__users', 'a'))
                ->leftJoin($db->quoteName('#__userreminder_optout', 'o') . ' ON o.user_id = a.id')
                ->where('o.user_id IS NULL')
                ->where('a.block = 0')
                ->where('a.lastvisitDate IS NOT NULL')
                ->where($db->quoteName('a.lastvisitDate') . ' < ' . $db->quote($d2x))
                ->where($db->quoteName('a.lastvisitDate') . ' >= ' . $db->quote($d4x));
            $db->setQuery($q);
            $inactive['2x'] = (int) $db->loadResult();

            $q = $db->getQuery(true)
                ->select('COUNT(a.id)')
                ->from($db->quoteName('#__users', 'a'))
                ->leftJoin($db->quoteName('#__userreminder_optout', 'o') . ' ON o.user_id = a.id')
                ->where('o.user_id IS NULL')
                ->where('a.block = 0')
                ->where('a.lastvisitDate IS NOT NULL')
                ->where($db->quoteName('a.lastvisitDate') . ' < ' . $db->quote($d4x));
            $db->setQuery($q);
            $inactive['4x'] = (int) $db->loadResult();
        } catch (\Throwable) {
        }

        return ['pending' => $pending, 'inactive' => $inactive];
    }

    private function getRecentLogs(DatabaseInterface $db, int $limit): array
    {
        try {
            $q = $db->getQuery(true)
                ->select($db->quoteName(['id', 'userId', 'username', 'description', 'date']))
                ->from($db->quoteName('#__userreminder_log'))
                ->order($db->quoteName('id') . ' DESC');
            $db->setQuery($q, 0, $limit);

            return $db->loadAssocList() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function getOldestPending(DatabaseInterface $db, ?string $cutoffReg, int $limit): array
    {
        try {
            $q = $db->getQuery(true)
                ->select('a.id, a.name, a.username, a.email, a.registerDate')
                ->select('b.datesent, b.remindernumber')
                ->from($db->quoteName('#__users', 'a'))
                ->leftJoin($db->quoteName('#__userreminder', 'b') . ' ON b.userid = a.id')
                ->leftJoin($db->quoteName('#__userreminder_optout', 'o') . ' ON o.user_id = a.id')
                ->where('o.user_id IS NULL')
                ->where('a.block >= 1')
                ->where('a.activation <> ' . $db->quote(''))
                ->where('a.lastvisitDate IS NULL');

            if ($cutoffReg !== null) {
                $q->where($db->quoteName('a.registerDate') . ' < ' . $db->quote($cutoffReg));
            }

            $q->order($db->quoteName('a.registerDate') . ' ASC');
            $db->setQuery($q, 0, $limit);

            return $db->loadAssocList() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function getLongestInactive(DatabaseInterface $db, string $cutoffExisting, int $limit): array
    {
        try {
            $q = $db->getQuery(true)
                ->select('a.id, a.name, a.username, a.email, a.lastvisitDate')
                ->select('b.datesent, b.remindernumber')
                ->from($db->quoteName('#__users', 'a'))
                ->leftJoin($db->quoteName('#__userreminder', 'b') . ' ON b.userid = a.id')
                ->leftJoin($db->quoteName('#__userreminder_optout', 'o') . ' ON o.user_id = a.id')
                ->where('o.user_id IS NULL')
                ->where('a.block = 0')
                ->where('a.lastvisitDate IS NOT NULL')
                ->where($db->quoteName('a.lastvisitDate') . ' < ' . $db->quote($cutoffExisting))
                ->order($db->quoteName('a.lastvisitDate') . ' ASC');
            $db->setQuery($q, 0, $limit);

            return $db->loadAssocList() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function buildHealth(
        \Joomla\Registry\Registry $params,
        int $pendingActivation,
        int $neverLoggedIn,
        int $inactiveUsers,
        int $dueNow,
        int $batchSize
    ): array {
        $alerts = [];
        $app    = Factory::getApplication();

        // Ready = Task - UserReminder plugin enabled AND an enabled scheduled task exists.
        $taskReady = UserReminderHelper::isTaskPluginEnabled() && UserReminderHelper::isScheduledTaskEnabled();
        if (!$taskReady) {
            $alerts[] = [
                'level' => 'danger',
                'icon'  => 'fa-plug',
                'key'   => 'plugin_disabled',
            ];
        }

        $queueTotal = $pendingActivation + $neverLoggedIn + $inactiveUsers;

        if (!$taskReady && $queueTotal > 0) {
            $alerts[] = [
                'level' => 'warning',
                'icon'  => 'fa-clock',
                'key'   => 'scheduler_off',
            ];
        }

        if ((int) $params->get('debugUserReminder', 0) === 1) {
            $alerts[] = [
                'level' => 'info',
                'icon'  => 'fa-bug',
                'key'   => 'debug_on',
            ];
        }

        $deleteFlags = [
            (int) $params->get('enableDeleteUsers', 0),
            (int) $params->get('enableDeleteUsersLogin', 0),
            (int) $params->get('enableDeleteExistingUsers', 0),
        ];
        if (array_sum($deleteFlags) > 0) {
            $alerts[] = [
                'level' => 'danger',
                'icon'  => 'fa-trash',
                'key'   => 'delete_enabled',
            ];
        }

        $mailFrom = trim((string) $app->get('mailfrom', ''));
        if ($mailFrom === '' || !filter_var($mailFrom, FILTER_VALIDATE_EMAIL)) {
            $alerts[] = [
                'level' => 'danger',
                'icon'  => 'fa-envelope',
                'key'   => 'mail_invalid',
            ];
        }

        // BCC check — warn when BCC is on but no address is configured.
        $bccEnabled = (int) $params->get('enabledBccToAdmin', 1);
        $bccAddress = trim((string) $params->get('bccEmailAddress', ''));
        if ($bccAddress === '' && $bccEnabled === 1) {
            $alerts[] = [
                'level' => 'warning',
                'icon'  => 'fa-copy',
                'key'   => 'bcc_missing',
            ];
        }

        if ($dueNow > $batchSize) {
            $alerts[] = [
                'level' => 'warning',
                'icon'  => 'fa-layer-group',
                'key'   => 'backlog_pressure',
                'due'   => $dueNow,
                'batch' => $batchSize,
            ];
        }

        // Health summary for the right-hand health card (compact).
        $health = [
            'pluginEnabled'    => $taskReady,
            'schedulerEnabled' => $taskReady,
            'debugOn'          => (int) $params->get('debugUserReminder', 0) === 1,
            'deleteEnabled'    => array_sum($deleteFlags) > 0,
            'mailOk'           => $mailFrom !== '' && filter_var($mailFrom, FILTER_VALIDATE_EMAIL),
            'queueTotal'       => $queueTotal,
            'dueNow'           => $dueNow,
            'batchSize'        => $batchSize,
        ];

        return ['alerts' => $alerts, 'summary' => $health];
    }

    private function buildConfigSnapshot(
        \Joomla\Registry\Registry $params,
        int $days,
        int $existingDays,
        int $maxReminders,
        int $batchSize
    ): array {
        $task = UserReminderHelper::getSchedulerTask();

        $scheduleText = '—';
        $nextRun      = '';
        if ($task !== null && (int) $task->state === 1) {
            $scheduleText = Text::_('COM_USERREMINDER_DASH_ON');
            $nextRun      = (string) $task->next_execution;
        }

        return [
            'days'             => $days,
            'existingDays'     => $existingDays,
            'maxReminders'     => $maxReminders,
            'batchSize'        => $batchSize,
            'maxPerRun'        => (int) $params->get('maxemailstosend', 20),
            'scheduleText'     => $scheduleText,
            'nextRun'          => $nextRun,
            'activationOn'     => (int) $params->get('enableActivateReminder', 0) === 1,
            'loginOn'          => (int) $params->get('enableLoginReminder', 0) === 1,
            'deleteUsers'      => (int) $params->get('enableDeleteUsers', 0) === 1,
            'deleteLogin'      => (int) $params->get('enableDeleteUsersLogin', 0) === 1,
            'deleteExisting'   => (int) $params->get('enableDeleteExistingUsers', 0) === 1,
        ];
    }

    private function getCacheKey(\Joomla\Registry\Registry $params): string
    {
        $relevant = [
            'numberOfDays'               => (int) $params->get('numberOfDays', 1),
            'enabledSendImed'            => (int) $params->get('enabledSendImed', 0),
            'numberOfDaysExistingUser'   => (int) $params->get('numberOfDaysExistingUser', 180),
            'numberOfReminders'          => (int) $params->get('numberOfReminders', 1),
            'number_email'               => (int) $params->get('number_email', 50),
            'enableDeleteUsers'          => (int) $params->get('enableDeleteUsers', 0),
            'enableDeleteUsersLogin'     => (int) $params->get('enableDeleteUsersLogin', 0),
            'enableDeleteExistingUsers'  => (int) $params->get('enableDeleteExistingUsers', 0),
            'scheduledTask'              => UserReminderHelper::getSchedulerTask()->next_execution ?? 'off',
            'debugUserReminder'          => (int) $params->get('debugUserReminder', 0),
            'ver'                        => '4.2.0',
        ];

        return md5(json_encode($relevant));
    }

    private function getCached(string $key): mixed
    {
        try {
            $factory = Factory::getContainer()->get(CacheControllerFactoryInterface::class);
            $cache   = $factory->createCacheController('output', ['defaultgroup' => self::CACHE_GROUP]);
            $cache->setLifeTime(self::CACHE_TTL);

            $data = $cache->get(['userreminder.dashboard', $key]);

            if (is_array($data) && isset($data['generatedAt'])) {
                return $data;
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function storeCached(string $key, array $data): void
    {
        try {
            $factory = Factory::getContainer()->get(CacheControllerFactoryInterface::class);
            $cache   = $factory->createCacheController('output', ['defaultgroup' => self::CACHE_GROUP]);
            $cache->setLifeTime(self::CACHE_TTL);
            $cache->store($data, ['userreminder.dashboard', $key]);
        } catch (\Throwable) {
            // Cache is best-effort — a failed store only costs one uncached dashboard render.
        }
    }
}