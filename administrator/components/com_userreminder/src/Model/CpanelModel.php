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

use JoomCoder\Component\UserReminder\Administrator\Helper\UserReminderHelper;
use JoomCoder\Component\UserReminder\Administrator\Service\Audience;
use JoomCoder\Component\UserReminder\Administrator\Service\ReminderOptions;
use JoomCoder\Component\UserReminder\Administrator\Service\SendService;
use Joomla\CMS\Cache\Controller\OutputController;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\QueryInterface;

/**
 * Cpanel / dashboard model: KPIs, analytics and health checks.
 *
 * User counts start from Audience::query(), the same query the send run uses,
 * so the dashboard counts the users the run would email. The payload is cached
 * for 10 minutes and every query is wrapped in try/catch so the dashboard never
 * white-screens on large sites.
 *
 * @since  4.1.0
 */
class CpanelModel extends BaseDatabaseModel
{
    public const CACHE_TTL = 600;

    public const CACHE_GROUP = 'com_userreminder.dashboard';

    /**
     * Installed manifest version, resolved once per request (static cache).
     *
     * @var    string|null
     * @since  6.1.0
     */
    private static ?string $manifestVersion = null;

    public function getForm($data = [], $loadData = true, $formName = null): ?Form
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
        $options  = ReminderOptions::fromComponent();
        $cacheKey = $this->getCacheKey($options);

        if (!$forceRefresh) {
            $cached = $this->getCached($cacheKey);

            if ($cached !== null) {
                return $cached;
            }
        }

        $data = $this->buildDashboard($options);
        $data['generatedAt'] = Factory::getDate()->toSql();

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
        try {
            $factory = Factory::getContainer()->get(CacheControllerFactoryInterface::class);

            foreach (['callback', 'output'] as $type) {
                $factory->createCacheController($type, ['defaultgroup' => self::CACHE_GROUP])->clean();
            }
        } catch (\Throwable $e) {
            Log::add('User Reminder: could not clean dashboard cache: ' . $e->getMessage(), Log::WARNING, 'com_userreminder');
        }
    }

    /**
     * Build the dashboard payload without touching cache.
     *
     * @param   ReminderOptions  $options
     *
     * @return  array
     *
     * @since   4.1.0
     */
    private function buildDashboard(ReminderOptions $options): array
    {
        $db = $this->getDatabase();

        $pendingActivation = $this->countAudience($db, $options, SendService::TYPE_NOT_ACTIVATED);
        $neverLoggedIn     = $this->countAudience($db, $options, SendService::TYPE_NEVER_LOGGED);
        $inactiveUsers     = $this->countAudience($db, $options, SendService::TYPE_INACTIVE_USER);
        $dueNow            = $this->countDueNow($db, $options);
        $sent              = $this->countSent($db);

        $thresholds = [
            'days'         => $options->cooldownDays(),
            'existingDays' => $options->inactivityDays(),
            'maxReminders' => $options->maxReminders(),
            'batchSize'    => $options->batchSize(),
        ];

        return [
            'kpi' => [
                'pendingActivation' => $pendingActivation,
                'neverLoggedIn'     => $neverLoggedIn,
                'inactiveUsers'     => $inactiveUsers,
                'dueNow'            => $dueNow,
                'sentTotal'         => $sent['total'],
                'sent7d'            => $sent['d7'],
                'sent30d'           => $sent['d30'],
                'optedOut'          => $this->countTable($db, '#__userreminder_optout'),
                'totalUsers'        => $this->countTable($db, '#__users'),
            ],
            'analytics' => [
                'trend'  => $this->getTrend30($db),
                'byType' => $this->getByType($db),
                'aging'  => $this->getAging($db, $options),
            ],
            'recent' => [
                'logs'            => $this->getRecentLogs($db, 5),
                'oldestPending'   => $this->getOldest($db, $options, SendService::TYPE_NOT_ACTIVATED, 'a.registerDate', 5),
                'longestInactive' => $this->getOldest($db, $options, SendService::TYPE_INACTIVE_USER, 'a.lastvisitDate', 5),
            ],
            'health'         => $this->buildHealth($options, $pendingActivation + $neverLoggedIn + $inactiveUsers, $dueNow),
            'configSnapshot' => $this->buildConfigSnapshot($options, $thresholds),
            'thresholds'     => $thresholds,
        ];
    }

    /**
     * Run a single-value query; 0 on any database error.
     *
     * @param   DatabaseInterface      $db
     * @param   QueryInterface|string  $query
     *
     * @return  int
     *
     * @since  6.1.0
     */
    private function loadInt(DatabaseInterface $db, QueryInterface|string $query): int
    {
        try {
            return (int) $db->setQuery($query)->loadResult();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function countAudience(DatabaseInterface $db, ReminderOptions $options, int $type): int
    {
        return $this->loadInt($db, Audience::query($db, $options, $type)->select('COUNT(a.id)'));
    }

    private function countTable(DatabaseInterface $db, string $table): int
    {
        return $this->loadInt($db, 'SELECT COUNT(*) FROM ' . $db->quoteName($table));
    }

    /**
     * Users whose cool-down has elapsed and who still have reminders left
     * (the three types never overlap, so one OR-ed query counts them all).
     *
     * @param   DatabaseInterface  $db
     * @param   ReminderOptions    $options
     *
     * @return  int
     *
     * @since   4.1.0
     */
    private function countDueNow(DatabaseInterface $db, ReminderOptions $options): int
    {
        $query = Audience::query($db, $options, SendService::TYPE_NOT_ACTIVATED, SendService::TYPE_NEVER_LOGGED, SendService::TYPE_INACTIVE_USER)
            ->select('COUNT(a.id)')
            ->where('(b.userid IS NULL OR b.remindernumber < ' . $options->maxReminders() . ')')
            ->where('(b.datesent IS NULL OR b.datesent < ' . $db->quote(ReminderOptions::utcNow('-' . $options->cooldownDays() . ' days')) . ')');

        return $this->loadInt($db, $query);
    }

    /**
     * Sent reminders: all time, last 7 and last 30 days (one query).
     *
     * @param   DatabaseInterface  $db
     *
     * @return  array{total:int, d7:int, d30:int}
     *
     * @since   4.1.0
     */
    private function countSent(DatabaseInterface $db): array
    {
        $date = $db->quoteName('date');

        try {
            $row = $db->setQuery(
                $db->getQuery(true)
                    ->select('COUNT(*) AS total')
                    ->select('SUM(CASE WHEN ' . $date . ' >= ' . $db->quote(ReminderOptions::utcNow('-7 days')) . ' THEN 1 ELSE 0 END) AS d7')
                    ->select('SUM(CASE WHEN ' . $date . ' >= ' . $db->quote(ReminderOptions::utcNow('-30 days')) . ' THEN 1 ELSE 0 END) AS d30')
                    ->from($db->quoteName('#__userreminder_log'))
            )->loadAssoc() ?: [];
        } catch (\Throwable) {
            $row = [];
        }

        return [
            'total' => (int) ($row['total'] ?? 0),
            'd7'    => (int) ($row['d7'] ?? 0),
            'd30'   => (int) ($row['d30'] ?? 0),
        ];
    }

    /**
     * Last 30 days trend — at most 31 rows, uses idx_ur_log_date.
     *
     * @return  array<int, array{date:string,count:int}>
     */
    private function getTrend30(DatabaseInterface $db): array
    {
        try {
            $day = 'DATE(' . $db->quoteName('date') . ')';
            $map = $db->setQuery(
                $db->getQuery(true)
                    ->select($day . ' AS d, COUNT(*) AS c')
                    ->from($db->quoteName('#__userreminder_log'))
                    ->where($db->quoteName('date') . ' >= ' . $db->quote(ReminderOptions::utcNow('-30 days')))
                    ->group($day)
            )->loadAssocList('d', 'c') ?: [];
        } catch (\Throwable) {
            return [];
        }

        // Fill missing days with 0 so the chart is continuous.
        $out   = [];
        $today = new \DateTimeImmutable('today');

        for ($i = 29; $i >= 0; $i--) {
            $d     = $today->modify('-' . $i . ' days')->format('Y-m-d');
            $out[] = ['date' => $d, 'count' => (int) ($map[$d] ?? 0)];
        }

        return $out;
    }

    /**
     * Reminder state rows per type.
     *
     * @return  array<int,int>  type => count
     */
    private function getByType(DatabaseInterface $db): array
    {
        $out = [1 => 0, 2 => 0, 3 => 0];

        try {
            $rows = $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName('type') . ', COUNT(*) AS c')
                    ->from($db->quoteName('#__userreminder'))
                    ->group($db->quoteName('type'))
            )->loadAssocList('type', 'c') ?: [];
        } catch (\Throwable) {
            return $out;
        }

        foreach ($rows as $type => $count) {
            $out[(int) $type] = (int) $count;
        }

        return $out;
    }

    /**
     * Aging buckets: pending activations by account age, inactive users by
     * how far past the inactivity threshold they are (one query each).
     *
     * @param   DatabaseInterface  $db
     * @param   ReminderOptions    $options
     *
     * @return  array{pending: array<string,int>, inactive: array<string,int>}
     *
     * @since   4.1.0
     */
    private function getAging(DatabaseInterface $db, ReminderOptions $options): array
    {
        $existingDays = $options->inactivityDays();

        $pending = $this->buckets($db, Audience::query($db, $options, SendService::TYPE_NOT_ACTIVATED), 'a.registerDate', [
            '1-7'  => [null, '-7 days'],
            '8-30' => ['-7 days', '-30 days'],
            '30+'  => ['-30 days', null],
        ]);

        $inactive = $this->buckets($db, Audience::query($db, $options, SendService::TYPE_INACTIVE_USER), 'a.lastvisitDate', [
            'just' => [null, '-' . ($existingDays * 2) . ' days'],
            '2x'   => ['-' . ($existingDays * 2) . ' days', '-' . ($existingDays * 4) . ' days'],
            '4x'   => ['-' . ($existingDays * 4) . ' days', null],
        ]);

        return ['pending' => $pending, 'inactive' => $inactive];
    }

    /**
     * Count rows of a query per date bucket in one SELECT.
     *
     * @param   DatabaseInterface  $db
     * @param   QueryInterface     $query    Audience query (no SELECT yet).
     * @param   string             $column   Date column.
     * @param   array              $buckets  name => [before, since]: date modifiers such as '-7 days'
     *                                       for "column < now+before" and "column >= now+since" (null = open).
     *
     * @return  array<string,int>
     *
     * @since  6.1.0
     */
    private function buckets(DatabaseInterface $db, QueryInterface $query, string $column, array $buckets): array
    {
        foreach ($buckets as $name => [$before, $since]) {
            $conditions = [];

            if ($before !== null) {
                $conditions[] = $column . ' < ' . $db->quote(ReminderOptions::utcNow($before));
            }

            if ($since !== null) {
                $conditions[] = $column . ' >= ' . $db->quote(ReminderOptions::utcNow($since));
            }

            $query->select('SUM(CASE WHEN ' . implode(' AND ', $conditions) . ' THEN 1 ELSE 0 END) AS ' . $db->quoteName('b_' . $name));
        }

        try {
            $row = $db->setQuery($query)->loadAssoc() ?: [];
        } catch (\Throwable) {
            $row = [];
        }

        $out = [];

        foreach (array_keys($buckets) as $name) {
            $out[$name] = (int) ($row['b_' . $name] ?? 0);
        }

        return $out;
    }

    private function getRecentLogs(DatabaseInterface $db, int $limit): array
    {
        try {
            $q = $db->getQuery(true)
                ->select($db->quoteName(['id', 'userId', 'username', 'description', 'message_key', 'date']))
                ->from($db->quoteName('#__userreminder_log'))
                ->order($db->quoteName('id') . ' DESC');

            return $db->setQuery($q, 0, $limit)->loadAssocList() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * The users of one reminder type who have waited longest.
     *
     * @param   DatabaseInterface  $db
     * @param   ReminderOptions    $options
     * @param   int                $type
     * @param   string             $dateColumn  a.registerDate or a.lastvisitDate.
     * @param   int                $limit
     *
     * @return  array
     *
     * @since  6.1.0
     */
    private function getOldest(DatabaseInterface $db, ReminderOptions $options, int $type, string $dateColumn, int $limit): array
    {
        try {
            $q = Audience::query($db, $options, $type)
                ->select('a.id, a.name, a.username, a.email, ' . $dateColumn)
                ->select('b.datesent, b.remindernumber')
                ->order($dateColumn . ' ASC');

            return $db->setQuery($q, 0, $limit)->loadAssocList() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function buildHealth(ReminderOptions $options, int $queueTotal, int $dueNow): array
    {
        $alerts    = [];
        $batchSize = $options->batchSize();

        // Ready = Task - UserReminder plugin enabled AND an enabled scheduled task exists.
        $taskReady = UserReminderHelper::isTaskPluginEnabled() && UserReminderHelper::isScheduledTaskEnabled();

        if (!$taskReady) {
            $alerts[] = ['level' => 'danger', 'icon' => 'fa-plug', 'key' => 'plugin_disabled'];

            if ($queueTotal > 0) {
                $alerts[] = ['level' => 'warning', 'icon' => 'fa-clock', 'key' => 'scheduler_off'];
            }
        }

        if ($options->debug()) {
            $alerts[] = ['level' => 'info', 'icon' => 'fa-bug', 'key' => 'debug_on'];
        }

        $deleteEnabled = $options->deleteAtEndOfCycle(SendService::TYPE_NOT_ACTIVATED)
            || $options->deleteAtEndOfCycle(SendService::TYPE_NEVER_LOGGED)
            || $options->deleteAtEndOfCycle(SendService::TYPE_INACTIVE_USER);

        if ($deleteEnabled) {
            $alerts[] = ['level' => 'danger', 'icon' => 'fa-trash', 'key' => 'delete_enabled'];
        }

        $mailFrom = trim((string) Factory::getApplication()->get('mailfrom', ''));
        $mailOk   = (bool) filter_var($mailFrom, FILTER_VALIDATE_EMAIL);

        if (!$mailOk) {
            $alerts[] = ['level' => 'danger', 'icon' => 'fa-envelope', 'key' => 'mail_invalid'];
        }

        // BCC switched on without an address.
        $params = $options->raw();

        if ((int) $params->get('enabledBccToAdmin', 0) === 1 && trim((string) $params->get('bccEmailAddress', '')) === '') {
            $alerts[] = ['level' => 'warning', 'icon' => 'fa-copy', 'key' => 'bcc_missing'];
        }

        if ($dueNow > $batchSize) {
            $alerts[] = ['level' => 'warning', 'icon' => 'fa-layer-group', 'key' => 'backlog_pressure', 'due' => $dueNow, 'batch' => $batchSize];
        }

        return [
            'alerts'  => $alerts,
            'summary' => [
                'schedulerEnabled' => $taskReady,
                'debugOn'          => $options->debug(),
                'deleteEnabled'    => $deleteEnabled,
                'mailOk'           => $mailOk,
                'queueTotal'       => $queueTotal,
                'dueNow'           => $dueNow,
                'batchSize'        => $batchSize,
            ],
        ];
    }

    private function buildConfigSnapshot(ReminderOptions $options, array $thresholds): array
    {
        $task = UserReminderHelper::getSchedulerTask();
        $on   = $task !== null && (int) $task->state === 1;

        return $thresholds + [
            'maxPerRun'    => $options->maxPerRun(),
            'scheduleText' => $on ? Text::_('COM_USERREMINDER_DASH_ON') : '—',
            'nextRun'      => $on ? (string) $task->next_execution : '',
            'activationOn' => $options->typeEnabled(SendService::TYPE_NOT_ACTIVATED),
            'loginOn'      => $options->typeEnabled(SendService::TYPE_NEVER_LOGGED),
        ];
    }

    /**
     * Cache key that changes whenever a setting the dashboard depends on, the
     * scheduled task or the installed version changes.
     *
     * @param   ReminderOptions  $options
     *
     * @return  string
     *
     * @since   4.1.0
     */
    private function getCacheKey(ReminderOptions $options): string
    {
        return md5(json_encode([
            $options->raw()->toArray(),
            UserReminderHelper::getSchedulerTask()?->next_execution ?? 'off',
            $this->manifestVersion(),
        ]));
    }

    /**
     * Version string of the installed component manifest, cached per request.
     *
     * @return  string
     *
     * @since  6.1.0
     */
    private function manifestVersion(): string
    {
        if (self::$manifestVersion === null) {
            // Joomla keeps the admin manifest under its package name, userreminder.xml.
            $xml = @simplexml_load_file(JPATH_ADMINISTRATOR . '/components/com_userreminder/userreminder.xml');

            self::$manifestVersion = $xml instanceof \SimpleXMLElement && (string) $xml->version !== ''
                ? (string) $xml->version
                : 'unknown';
        }

        return self::$manifestVersion;
    }

    private function cache(): OutputController
    {
        $cache = Factory::getContainer()->get(CacheControllerFactoryInterface::class)
            ->createCacheController('output', ['defaultgroup' => self::CACHE_GROUP]);
        $cache->setLifeTime(self::CACHE_TTL);

        return $cache;
    }

    private function getCached(string $key): ?array
    {
        try {
            $data = $this->cache()->get(['userreminder.dashboard', $key]);

            return is_array($data) && isset($data['generatedAt']) ? $data : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function storeCached(string $key, array $data): void
    {
        try {
            $this->cache()->store($data, ['userreminder.dashboard', $key]);
        } catch (\Throwable) {
            // Cache is best-effort — a failed store only costs one uncached dashboard render.
        }
    }
}
