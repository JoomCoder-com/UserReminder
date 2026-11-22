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

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\Mail\Mail;
use Joomla\CMS\Mail\MailTemplate;
use Joomla\CMS\Event\GenericEvent;
use Joomla\CMS\User\UserHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\CMS\Plugin\PluginHelper;
use JoomCoder\Component\UserReminder\Administrator\Helper\ActivationUrlHelper;
use JoomCoder\Component\UserReminder\Administrator\Helper\SiteUrl;

/**
 * SendService — the reminder send engine. No rendering, no MVC: pure business
 * logic shared by the admin UI, the task plugin and the CLI.
 *
 * Two pipelines:
 *   - processRegistrationReminders(): type 1 (not activated) + type 2 (never logged in)
 *   - processInactiveUserReminders(): type 3 (registered users inactive for X days)
 *
 * Every send is announced through events so other extensions can veto or
 * enrich it (no mailer/recipient/skip magic happens outside the event loop):
 *   - onUserReminderBeforeSend — with the full tag map; listeners may set the
 *     `abort` argument (or return false) to veto this recipient.
 *   - onUserReminderAfterSend — with sent=true/false and the type/all tags.
 *
 * When ReminderOptions::debug() is on the service runs as a dry run: nothing
 * is sent and no reminder state/log row is written; the experiment is only
 * recorded in the debug log (see MailDebug).
 *
 * @since  4.0.0
 */
final class SendService
{
    public const TYPE_NOT_ACTIVATED = 1;
    public const TYPE_NEVER_LOGGED  = 2;
    public const TYPE_INACTIVE_USER = 3;

    /** Event name dispatched before a single reminder is sent (veto possible). */
    public const EVENT_BEFORE_SEND = 'onUserReminderBeforeSend';

    /** Event name dispatched after a single reminder attempt. */
    public const EVENT_AFTER_SEND = 'onUserReminderAfterSend';

    /**
     * Pending log rows, flushed to #__userreminder_log in one multi-row
     * INSERT per batch instead of one query per sent email.
     *
     * @var  string[]
     *
     * @since  6.0.0
     */
    private array $logBuffer = [];

    /**
     * How many log rows to buffer before an intermediate flush.
     *
     * @var  int
     *
     * @since  6.0.0
     */
    private const LOG_BATCH = 25;

    /**
     * Lazily loaded ids of the opt-out user groups
     * (#__userreminder_optout_usergroups).
     *
     * @var  int[]|null
     *
     * @since  4.2.1
     */
    private ?array $excludedGroups = null;

    /**
     * Whether the "cannot resolve a site URL" failure was already logged for
     * this run (once per run instead of once per recipient).
     *
     * @var  bool
     *
     * @since  6.1.0
     */
    private bool $siteUrlFailureLogged = false;

    /**
     * Whether the loopback-link warning was already logged for this run.
     *
     * @var  bool
     *
     * @since  6.1.0
     */
    private bool $loopbackWarned = false;

    /**
     * Whether the third-party "userreminder" plugin group was imported during
     * this request (once per process, never per pipeline run).
     *
     * @var  bool
     *
     * @since  6.1.0
     */
    private static bool $reminderPluginsLoaded = false;

    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly ?MailerFactoryInterface $mailerFactory = null
    ) {
    }

    /**
     * Import the "userreminder" plugin group so plg_userreminder_* event
     * listeners are constructed and attached before onUserReminderBefore/After
     * dispatches. No-op (and never repeated) when nothing is registered.
     *
     * @return  void
     *
     * @since  6.1.0
     */
    private function loadThirdPartyPlugins(): void
    {
        if (self::$reminderPluginsLoaded) {
            return;
        }

        self::$reminderPluginsLoaded = true;

        try {
            PluginHelper::importPlugin('userreminder');
        } catch (\Throwable) {
        }
    }

    /**
     * Populate the request context when it is missing (CLI cron).
     *
     * Joomla core's MailTemplate::send() absolutises relative URLs found in
     * the mail template HTML (MailHelper::convertRelativeToAbsoluteUrls),
     * which needs $_SERVER['HTTP_HOST']. The Joomla CLI does not populate it
     * (only the --live-site option does), so a cron run without it used to
     * fatal. The reminder links themselves are always built from SiteUrl —
     * which refuses "localhost" — this shim only serves core's URL
     * absolutisation and is applied once and only when truly missing.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    private function ensureRequestContext(): void
    {
        if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== '') {
            return;
        }

        $live = trim((string) Factory::getApplication()->get('live_site', ''), " \t/");

        if ($live !== '' && ($parts = parse_url('//' . str_replace('//', '/', $live))) !== false) {
            $_SERVER['HTTP_HOST']   = ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');
            $_SERVER['REQUEST_URI'] = ($parts['path'] ?? '') . '/';
        } else {
            $_SERVER['HTTP_HOST']   = 'localhost';
            $_SERVER['REQUEST_URI'] = '/';
        }

        $_SERVER['SCRIPT_NAME'] = $_SERVER['REQUEST_URI'];
    }

    /**
     * Send reminders to users who registered but never activated or never logged in.
     *
     * @param   bool  $logToTable  Write a row to #__userreminder_log for every real send.
     * @param   int   $offset      SQL offset (0-based).
     * @param   int   $limit       Batch size (0 = ReminderOptions::batchSize()).
     *
     * @return  array{processed:int, sent:int, deleted:int, skipped:int, errors:int, dryRun:bool}
     *
     * @since   4.0.0
     */
    public function processRegistrationReminders(bool $logToTable = true, int $offset = 0, int $limit = 0): array
    {
        $this->ensureRequestContext();
        $this->loadThirdPartyPlugins();
        $options = ReminderOptions::fromComponent();
        $limit   = $limit > 0 ? $limit : $options->batchSize();

        // Clean up rows whose users are healthy again (batched).
        $this->cleanupStaleRows();

        $candidates = $this->buildRegistrationCandidateQuery($options);

        $this->db->setQuery($candidates, $offset, $limit);
        $rows = $this->db->loadObjectList() ?: [];

        $stats = ['processed' => 0, 'sent' => 0, 'deleted' => 0, 'skipped' => 0, 'simulated' => 0, 'errors' => 0, 'dryRun' => false];
        $maxSends = $options->maxPerRun();
        $dryRun   = $options->debug();

        foreach ($rows as $row) {
            if (!$dryRun && $maxSends > 0 && $stats['sent'] >= $maxSends) {
                break;
            }

            $stats['processed']++;

            $type = (int) ($row->type ?? 0);
            $type = in_array($type, [self::TYPE_NOT_ACTIVATED, self::TYPE_NEVER_LOGGED], true)
                ? $type
                : self::TYPE_NEVER_LOGGED;

            $action = $this->decideAction($row, $type, $options);

            if ($action === 'send') {
                if ($dryRun) {
                    $this->simulateSend($row, $type, $options);
                    $stats['dryRun'] = true;
                    $stats['simulated']++;
                    continue;
                }

                if ($this->sendOne($row, $type, $options)) {
                    $this->upsertReminderRow($row->id, $type, $row->optoutcode ?? null);
                    $stats['sent']++;

                    if ($logToTable) {
                        $this->writeLog($row, $type);
                    }
                } else {
                    $stats['errors']++;
                }
            } elseif ($action === 'delete') {
                if ($dryRun) {
                    $stats['dryRun'] = true;

                    MailDebug::log(sprintf('DRY-RUN would delete user #%d (cycle complete, type %d)', $row->id, $type));
                    continue;
                }

                $this->maybeDelete($row->id, $type, $options);
                $stats['deleted']++;
            } else {
                $stats['skipped']++;
            }
        }

        $this->flushLog();

        return $stats;
    }

    /**
     * Send reminders to existing registered users who haven't visited in X days.
     *
     * @param   bool  $logToTable
     * @param   int   $offset
     * @param   int   $limit
     *
     * @return  array{processed:int, sent:int, deleted:int, skipped:int, removedGroups:int, errors:int, dryRun:bool}
     *
     * @since   4.0.0
     */
    public function processInactiveUserReminders(bool $logToTable = true, int $offset = 0, int $limit = 0): array
    {
        $this->ensureRequestContext();
        $this->loadThirdPartyPlugins();
        $options = ReminderOptions::fromComponent();
        $limit   = $limit > 0 ? $limit : $options->batchSize();

        $days   = $options->inactivityDays();
        $cutoff = ReminderOptions::utcNow('-' . $days . ' days');

        $query = $this->db->getQuery(true)
            ->select('a.id, a.email, a.block, a.registerDate, a.lastvisitDate, a.activation, a.username, a.name')
            ->select('b.datesent, b.remindernumber, b.type as savedtype, b.optoutcode')
            ->select('DATEDIFF(UTC_TIMESTAMP(), a.lastvisitDate) AS nodays')
            ->from($this->db->quoteName('#__users', 'a'))
            ->leftJoin($this->db->quoteName('#__userreminder', 'b') . ' ON ' . $this->db->quoteName('b.userid') . ' = ' . $this->db->quoteName('a.id'))
            ->leftJoin($this->db->quoteName('#__userreminder_optout', 'o') . ' ON ' . $this->db->quoteName('o.user_id') . ' = ' . $this->db->quoteName('a.id'))
            ->where($this->db->quoteName('o.user_id') . ' IS NULL')
            ->where($this->db->quoteName('a.lastvisitDate') . ' IS NOT NULL')
            ->where($this->db->quoteName('a.block') . ' = 0')
            ->where($this->db->quoteName('a.lastvisitDate') . ' < :cutoff')
            ->bind(':cutoff', $cutoff);

        $this->applyGroupExclusion($query);

        $query->order($this->db->quoteName('a.lastvisitDate') . ' ASC');

        $this->db->setQuery($query, $offset, $limit);
        $rows = $this->db->loadObjectList() ?: [];

        $stats = ['processed' => 0, 'sent' => 0, 'deleted' => 0, 'skipped' => 0, 'simulated' => 0, 'removedGroups' => 0, 'errors' => 0, 'dryRun' => false];
        $maxSends = $options->maxPerRun();
        $dryRun   = $options->debug();
        $maxReminders = $options->maxReminders();

        foreach ($rows as $row) {
            if (!$dryRun && $maxSends > 0 && $stats['sent'] >= $maxSends) {
                break;
            }

            $stats['processed']++;
            $row->type = self::TYPE_INACTIVE_USER;

            $reminderNumber = (int) ($row->remindernumber ?? 0);

            if ($reminderNumber >= $maxReminders) {
                // Cycle is over, optionally delete / remove from groups.
                if (!$dryRun && $options->deleteAtEndOfCycle(self::TYPE_INACTIVE_USER)) {
                    $this->maybeDelete($row->id, self::TYPE_INACTIVE_USER, $options);
                    $stats['deleted']++;
                } elseif (!$dryRun) {
                    $removedGroups = $this->maybeRemoveFromGroups($row->id, $options);

                    if ($removedGroups > 0) {
                        $stats['removedGroups']++;
                    } else {
                        $stats['skipped']++;
                    }
                } else {
                    MailDebug::log(sprintf('DRY-RUN cycle ended for user #%d (type 3): would delete/remove-groups', $row->id));
                    $stats['dryRun'] = true;
                }

                continue;
            }

            // Eligible to send? (Cool-down in UTC, matching what we store.)
            if ($this->isWithinCoolDown($row->datesent, $options->cooldownDays())) {
                $stats['skipped']++;
                continue;
            }

            if ($dryRun) {
                $this->simulateSend($row, self::TYPE_INACTIVE_USER, $options);
                $stats['dryRun'] = true;
                $stats['simulated']++;
                continue;
            }

            if ($this->sendOne($row, self::TYPE_INACTIVE_USER, $options)) {
                $this->upsertReminderRow($row->id, self::TYPE_INACTIVE_USER, $row->optoutcode ?? null);
                $stats['sent']++;

                if ($logToTable) {
                    $this->writeLog($row, self::TYPE_INACTIVE_USER);
                }
            } else {
                $stats['errors']++;
            }
        }

        $this->flushLog();

        return $stats;
    }

    /**
     * Simulate sending one reminder in dry-run mode: record the exact
     * template/recipient/tags to the debug log, send nothing, write nothing.
     *
     * @param   object           $row
     * @param   int              $type
     * @param   ReminderOptions  $options
     *
     * @return  void
     *
     * @since  6.1.0
     */
    private function simulateSend(object $row, int $type, ReminderOptions $options): void
    {
        // The real path generates the stable opt-out code inside sendOne();
        // mirror that here so the recorded dry-run tags show a usable link.
        if (empty($row->optoutcode)) {
            $row->optoutcode = ApplicationHelper::getHash(UserHelper::genRandomPassword());
        }

        $siteUrl = SiteUrl::root();

        if ($siteUrl === null) {
            return;
        }

        MailDebug::log(sprintf(
            'DRY-RUN send: template=%s user=#%d "%s" <%s> tags=%s',
            match ($type) {
                self::TYPE_NOT_ACTIVATED => 'reminder_activation',
                self::TYPE_NEVER_LOGGED  => 'reminder_login',
                default                  => 'reminder_inactive',
            },
            (int) $row->id,
            (string) ($row->name ?? $row->username ?? ''),
            (string) $row->email,
            json_encode($this->buildTags($row, $type, $options, $siteUrl), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        ));
    }

    /**
     * Load the ids of the opt-out user groups once per run.
     *
     * @return  int[]
     *
     * @since   4.2.1
     */
    private function getExcludedGroups(): array
    {
        if ($this->excludedGroups === null) {
            $query = $this->db->getQuery(true)
                ->select($this->db->quoteName('group_id'))
                ->from($this->db->quoteName('#__userreminder_optout_usergroups'));

            $this->db->setQuery($query);

            $this->excludedGroups = array_values(
                array_filter(array_map('intval', (array) $this->db->loadColumn()))
            );
        }

        return $this->excludedGroups;
    }

    /**
     * Add the opt-out group exclusion to a candidate query.
     *
     * Members of the configured groups (#__userreminder_optout_usergroups,
     * managed under Opt-out → User Groups) never receive reminders.
     *
     * @param   \Joomla\Database\QueryInterface  $query  Query on #__users aliased as "a".
     *
     * @return  void
     *
     * @since   4.2.1
     */
    private function applyGroupExclusion(\Joomla\Database\QueryInterface $query): void
    {
        $groups = $this->getExcludedGroups();

        if (empty($groups)) {
            return;
        }

        $sub = $this->db->getQuery(true)
            ->select($this->db->quoteName('gm.user_id'))
            ->from($this->db->quoteName('#__user_usergroup_map', 'gm'))
            ->where($this->db->quoteName('gm.group_id') . ' IN (' . implode(',', $groups) . ')');

        $query->where($this->db->quoteName('a.id') . ' NOT IN (' . $sub . ')');
    }

    /**
     * Build the SQL that selects users needing activation or "never logged in" reminders.
     *
     * All date comparisons are computed in PHP/UTC — the fifteen岁月 of SQL
     * NOW()/DATE_SUB() mixing server and PHP time zones is over: the same
     * cutoff value is used by every consumer of the options object.
     *
     * @param   ReminderOptions  $options
     *
     * @return  \Joomla\Database\QueryInterface
     *
     * @since   4.0.0
     */
    private function buildRegistrationCandidateQuery(ReminderOptions $options): \Joomla\Database\QueryInterface
    {
        $db = $this->db;

        $days   = $options->cooldownDays();
        $cutoff = ReminderOptions::utcNow('-' . $days . ' days');

        // Without "send first reminder immediately", a user's first reminder
        // waits until the account is at least `$days` old.
        $firstSendAgeFilter = $options->firstImmediate()
            ? null
            : fn() => $db->quoteName('a.registerDate') . ' < :cutoff';

        $addAgeFilter = static function (\Joomla\Database\QueryInterface $query) use ($firstSendAgeFilter, $cutoff) {
            if ($firstSendAgeFilter === null) {
                return $query;
            }

            return $query->where($firstSendAgeFilter())->bind(':cutoff', $cutoff);
        };

        $notActivated = $addAgeFilter(
            $db->getQuery(true)
                ->select('a.id, a.email, a.activation, a.block, a.registerDate, a.lastvisitDate, a.username, a.name')
                ->select('b.datesent, b.remindernumber, b.optoutcode')
                ->select((string) self::TYPE_NOT_ACTIVATED . ' AS type')
                ->from($db->quoteName('#__users', 'a'))
                ->leftJoin($db->quoteName('#__userreminder', 'b') . ' ON ' . $db->quoteName('b.userid') . ' = ' . $db->quoteName('a.id'))
                ->leftJoin($db->quoteName('#__userreminder_optout', 'o') . ' ON ' . $db->quoteName('o.user_id') . ' = ' . $db->quoteName('a.id'))
                ->where($db->quoteName('o.user_id') . ' IS NULL')
                ->where($db->quoteName('a.activation') . ' <> ' . $db->quote(''))
                ->where($db->quoteName('a.block') . ' >= 1')
                ->where($db->quoteName('a.lastvisitDate') . ' IS NULL')
        );

        $this->applyGroupExclusion($notActivated);

        $neverLogged = $addAgeFilter(
            $db->getQuery(true)
                ->select('a.id, a.email, a.activation, a.block, a.registerDate, a.lastvisitDate, a.username, a.name')
                ->select('b.datesent, b.remindernumber, b.optoutcode')
                ->select((string) self::TYPE_NEVER_LOGGED . ' AS type')
                ->from($db->quoteName('#__users', 'a'))
                ->leftJoin($db->quoteName('#__userreminder', 'b') . ' ON ' . $db->quoteName('b.userid') . ' = ' . $db->quoteName('a.id'))
                ->leftJoin($db->quoteName('#__userreminder_optout', 'o') . ' ON ' . $db->quoteName('o.user_id') . ' = ' . $db->quoteName('a.id'))
                ->where($db->quoteName('o.user_id') . ' IS NULL')
                ->where($db->quoteName('a.block') . ' = 0')
                ->where($db->quoteName('a.lastvisitDate') . ' IS NULL')
                ->where($db->quoteName('a.activation') . ' = ' . $db->quote(''))
        );

        $this->applyGroupExclusion($neverLogged);

        return $notActivated->union($neverLogged);
    }

    /**
     * Decide whether to send / skip / delete the user.
     *
     * Rules, per reminder type (types 1 and 2):
     *   1. Type disabled → skip.
     *   2. No reminder sent yet → send (after the account-age gate unless
     *      "send first reminder immediately").
     *   3. Cycle done (maxReminders reached) → delete if the type has "auto
     *      delete" on, otherwise skip.
     *   4. Still inside the cool-down window → skip.
     *   5. Otherwise → send.
     *
     * @param   object           $row
     * @param   int              $type
     * @param   ReminderOptions  $options
     *
     * @return  string  'send'|'skip'|'delete'
     *
     * @since   4.0.0
     */
    private function decideAction(object $row, int $type, ReminderOptions $options): string
    {
        if (!$options->typeEnabled($type)) {
            return 'skip';
        }

        $reminderNumber = (int) ($row->remindernumber ?? 0);

        if ($reminderNumber === 0) {
            // Defensive: mirrors the SQL age filter in the candidate query in
            // case the row reaches this method through another path.
            if (!$options->firstImmediate()
                && $this->isWithinCoolDown($row->registerDate, $options->cooldownDays())) {
                return 'skip';
            }

            return 'send';
        }

        if ($reminderNumber >= $options->maxReminders()) {
            return $options->deleteAtEndOfCycle($type) ? 'delete' : 'skip';
        }

        if ($this->isWithinCoolDown($row->datesent, $options->cooldownDays())) {
            return 'skip';
        }

        return 'send';
    }

    /**
     * Are we still inside the cool-down window? If the user received a reminder
     * less than `$days` ago, skip.
     *
     * datesent is a datetime stored in DB-local server time by previous
     * versions; since 6.1 all timestamps are written in UTC. The comparison
     * therefore treats the stored value as UTC — values written by 6.0.0 were
     * UTC whenever the server clock was UTC (com_scheduler runs in PHP TZ);
     * the residual skew for legacy rows is at most the server UTC offset and
     * always errs on the *safe* side (delays the next reminder, never sends one
     * early). New rows are exact.
     *
     * @param   string|null  $datesent
     * @param   int          $days
     *
     * @return  bool  true = still in cool-down (skip)
     *
     * @since   4.0.0
     */
    /**
     * What the next run will do for a user, for the admin lists' "Action to Perform"
     * column. Same rules as the run (decideAction() for types 1/2, the inactive-user
     * branch of sendInactiveReminders() for type 3).
     *
     * @param   object  $row   User row with remindernumber, datesent, registerDate.
     * @param   int     $type  One of the TYPE_* constants.
     *
     * @return  string  send|wait|done|delete|removegroups|off
     *
     * @since   6.0.1
     */
    public function plannedAction(object $row, int $type): string
    {
        static $options = null;
        $options ??= ReminderOptions::fromComponent();

        if (!$options->typeEnabled($type)) {
            return 'off';
        }

        $reminderNumber = (int) ($row->remindernumber ?? 0);

        if ($reminderNumber > 0 && $reminderNumber >= $options->maxReminders()) {
            if ($options->deleteAtEndOfCycle($type)) {
                return 'delete';
            }

            return $type === self::TYPE_INACTIVE_USER && $options->removeFromGroups() ? 'removegroups' : 'done';
        }

        if ($reminderNumber === 0) {
            // Types 1/2 wait for the account age unless the first reminder goes out at once
            $waiting = $type !== self::TYPE_INACTIVE_USER && !$options->firstImmediate()
                && $this->isWithinCoolDown($row->registerDate ?? null, $options->cooldownDays());

            return $waiting ? 'wait' : 'send';
        }

        return $this->isWithinCoolDown($row->datesent ?? null, $options->cooldownDays()) ? 'wait' : 'send';
    }

    private function isWithinCoolDown(?string $datesent, int $days): bool
    {
        if (empty($datesent)) {
            return false;
        }

        return ReminderOptions::utcNow('-' . $days . ' days') < $datesent;
    }

    /**
     * Send one reminder email through the Joomla mail template
     * (com_userreminder.userreminder.reminder_* — editable in System → Mail Templates).
     *
     * The mailer comes from MailerFactoryInterface (System → Mail settings,
     * including any mail-transport plugin), no longer a hand-built copy.
     * onUserReminderBeforeSend listeners may veto; onUserReminderAfterSend
     * listeners get the result with the full tag map.
     *
     * @param   object             $row      User row (id, email, username, name, ...).
     * @param   int                $type     One of the TYPE_* constants.
     * @param   ReminderOptions|null $options When null, built from the component config.
     *
     * @return  bool  true on success (in debug mode the simulation always "succeeds").
     *
     * @since   4.0.0
     */
    public function sendOne(object $row, int $type, ?ReminderOptions $options = null): bool
    {
        $options ??= ReminderOptions::fromComponent();
        $app     = Factory::getApplication();
        $mailfrom = $app->get('mailfrom');
        $fromname = $app->get('fromname');

        $templateId = match ($type) {
            self::TYPE_NOT_ACTIVATED => 'com_userreminder.userreminder.reminder_activation',
            self::TYPE_NEVER_LOGGED  => 'com_userreminder.userreminder.reminder_login',
            default                  => 'com_userreminder.userreminder.reminder_inactive',
        };

        // The site root is mandatory: every link in the email derives from it.
        // Refusing to send beats inventing http://localhost links.
        $siteUrl = SiteUrl::root();

        if ($siteUrl === null) {
            if (!$this->siteUrlFailureLogged) {
                $this->siteUrlFailureLogged = true;

                Log::add(
                    'User Reminder: no usable site URL could be resolved (request context missing and live_site empty). '
                    . 'Reminders are skipped this run — set live_site in Global Configuration or run the task on the site.',
                    Log::ERROR,
                    'com_userreminder'
                );
            }

            $this->dispatchAfter($type, $row, (string) ($row->email ?? ''), $templateId, [], false);

            return false;
        }

        // A loopback base URL is allowed but flagged once per run: opt-out or
        // activation links pointing at localhost only work for visitors on
        // the very machine that runs the site.
        if (!$this->loopbackWarned && SiteUrl::isLoopback($siteUrl)) {
            $this->loopbackWarned = true;

            Log::add(
                Text::sprintf('COM_USERREMINDER_LOOPBACK_URL', $siteUrl),
                Log::WARNING,
                'com_userreminder'
            );
        }

        // The opt-out code is generated once and then stays stable per user so
        // links in previously delivered emails keep working.
        if (empty($row->optoutcode)) {
            $row->optoutcode = ApplicationHelper::getHash(UserHelper::genRandomPassword());
        }

        $tags = $this->buildTags($row, $type, $options, $siteUrl);

        // Event: any listener may veto an individual send.
        if (!$this->dispatchBeforeSend($type, $row, $templateId, $tags)) {
            $this->dispatchAfter($type, $row, (string) ($row->email ?? ''), $templateId, $tags, false);

            return false;
        }

        // Dry run: the exact values are only recorded, never sent or stored.
        if ($options->debug()) {
            MailDebug::log(sprintf('DRY-RUN send (sendOne): template=%s to=%s tags=%s',
                $templateId, (string) ($row->email ?? ''), json_encode($tags, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)));

            return true;
        }

        try {
            $mailer = $this->resolveMailer();
            $mailTemplate = new MailTemplate($templateId, $app->getLanguage()->getTag(), $mailer);
            $mailTemplate->addTemplateData($tags);

            // Custom HTML container (header / footer / logo), configured under
            // User Reminder → Options → Email Container. The layout only
            // applies to our emails — everything else keeps Joomla's stock
            // container (see layouts/joomla/mail/userreminder.php).
            // MailTemplate::addLayoutTemplateData() is Joomla 5+; on Joomla 4
            // the template's HTML body is sent as-is.
            if (method_exists($mailTemplate, 'addLayoutTemplateData')) {
                $mailTemplate->addLayoutTemplateData($this->containerLayoutData($options, $mailer, $app->get('sitename'), $siteUrl));
            }

            $mailTemplate->addRecipient((string) ($row->email ?? ''));

            $sent = $mailTemplate->send() === true;
        } catch (\Throwable $e) {
            $sent = false;

            Log::add(
                Text::sprintf('COM_USERREMINDER_SEND_ERROR', (string) ($row->email ?? ''), $e->getMessage()),
                Log::ERROR,
                'com_userreminder'
            );
        }

        $this->dispatchAfter($type, $row, (string) ($row->email ?? ''), $templateId, $tags, $sent);

        return $sent;
    }

    /**
     * Build the merge-tag map for one reminder template.
     *
     * @param   object           $row
     * @param   int              $type
     * @param   ReminderOptions  $options
     * @param   string           $siteUrl
     *
     * @return  array<string,string>
     *
     * @since  6.1.0
     */
    private function buildTags(object $row, int $type, ReminderOptions $options, string $siteUrl): array
    {
        $app       = Factory::getApplication();
        $name      = (string) ($row->name ?? Factory::getUser($row->id)->get('name', ''));
        $username  = (string) ($row->username ?? '');
        $email     = (string) ($row->email ?? '');

        $optOutCode   = (string) ($row->optoutcode ?? '');
        $optOutUrl    = $this->optOutUrl($siteUrl, $optOutCode);
        $activateUrl  = ActivationUrlHelper::get($row, $siteUrl, $options->raw());

        return [
            'NAME'               => $name ?: $username,
            'SITENAME'           => (string) $app->get('sitename'),
            'SITELINK'           => $siteUrl,
            'USERNAME'           => $username,
            'EMAIL'              => $email,
            'PASSWORD_RESET_URL' => $siteUrl . $options->passwordResetPath(),
            'OPTOUT_URL'         => $optOutUrl,
            'ACTIVATE_URL'       => $activateUrl,
        ];
    }

    /**
     * Absolute opt-out URL for a code. SEF when the site router can build one,
     * plain non-SEF URL otherwise (both are functionally equivalent).
     *
     * @param   string  $siteUrl
     * @param   string  $code
     *
     * @return  string
     *
     * @since  6.1.0
     */
    private function optOutUrl(string $siteUrl, string $code): string
    {
        $path = 'index.php?option=com_userreminder&view=optout&uid=' . $code;

        try {
            // Route::link('site') is safe from admin/CLI: it only needs the
            // site router, not the current request.
            $sef = \Joomla\CMS\Router\Route::link('site', $path, false, \Joomla\CMS\Router\Route::TLS_IGNORE, true);

            // $absolute = true: the router already returns the full URL (scheme, host and
            // any subfolder). Prefixing $siteUrl again doubled it ("http://x/http://x/...").
            if (preg_match('#^https?://#i', $sef) && !str_contains($sef, '/index.php?')) {
                return $sef;
            }

            return $siteUrl . $path;
        } catch (\Throwable) {
            return $siteUrl . $path;
        }
    }

    /**
     * Send one test email per requested reminder type to the configured test user.
     *
     * In debug mode the dry-run is "successful" (recorded to the debug log)
     * without sending anything — the caller message must make that clear (see
     * TEST_SIMULATED language keys).
     *
     * @param   int[]  $types  One or more TYPE_* constants.
     *
     * @return  bool  true when every email was sent (or simulated in debug).
     *
     * @since   4.2.0
     */
    public function sendTestMail(array $types): bool
    {
        $options = ReminderOptions::fromComponent();
        $app     = Factory::getApplication();
        $userId  = $options->testUserId((int) $app->getIdentity()->id);

        if ($userId <= 0) {
            return false;
        }

        $user = Factory::getUser($userId);

        $row = (object) [
            'id'             => $user->id,
            'name'           => $user->name,
            'username'       => $user->username,
            'email'          => $user->email,
            'activation'     => $user->activation ?? '',
            'registerDate'   => $user->registerDate,
            'lastvisitDate'  => $user->lastvisitDate,
            'remindernumber' => 0,
            'datesent'       => null,
            // A generated code so the test email's opt-out link is usable too.
            'optoutcode'     => ApplicationHelper::getHash(UserHelper::genRandomPassword()),
        ];

        $ok = true;

        foreach ($types as $type) {
            if (!$this->sendOne($row, (int) $type, $options)) {
                $ok = false;
            }
        }

        return $ok;
    }

    /**
     * Sanitize a hex color (falls back to the given default when invalid).
     *
     * @param   mixed   $value    Raw value from the params.
     * @param   string  $default  Fallback color.
     *
     * @return  string
     *
     * @since   4.2.1
     */
    private function normalizeColor($value, string $default): string
    {
        $color = trim((string) $value);

        return preg_match('/^#[0-9a-fA-F]{3,8}$/', $color) ? $color : $default;
    }

    /**
     * Build the extra layout data for the custom HTML mail container.
     *
     * @param   ReminderOptions  $options  Component params.
     * @param   Mail      $mailer   The mailer — the logo is attached inline.
     * @param   string    $sitename Site name.
     * @param   string    $siteUrl  Site root URL.
     *
     * @return  array
     *
     * @since   4.2.1
     */
    private function containerLayoutData(ReminderOptions $options, Mail $mailer, string $sitename, string $siteUrl): array
    {
        if ((int) $options->raw()->get('mail_container', 1) !== 1) {
            return [];
        }

        $header = trim((string) $options->raw()->get('mail_container_header', ''));

        $footer = trim((string) $options->raw()->get('mail_container_footer', ''));

        if ($footer === '') {
            $footer = '&copy; {SITENAME} {YEAR}<br /><a href="{SITELINK}">{SITELINK}</a>';
        }

        $footer = strtr($footer, [
            '{SITENAME}' => htmlspecialchars($sitename, ENT_QUOTES),
            '{SITELINK}' => $siteUrl,
            '{YEAR}'     => (string) date('Y'),
        ]);

        $data = [
            'ur_container'     => 1,
            'ur_header'        => $header !== '' ? $header : $sitename,
            'ur_footer'        => $footer,
            'ur_footer_bg'     => $this->normalizeColor($options->raw()->get('mail_container_footer_bg', '#112855'), '#112855'),
            'ur_footer_color'  => $this->normalizeColor($options->raw()->get('mail_container_footer_color', '#cccccc'), '#cccccc'),
        ];

        // Attach the configured logo inline (embedded image, no external URL).
        if ((int) $options->raw()->get('mail_container_logo', 0) === 1) {
            $file = (string) $options->raw()->get('mail_container_logofile', '');

            if ($file !== '') {
                try {
                    $path = \Joomla\Filesystem\Path::check(JPATH_ROOT . '/' . \Joomla\CMS\HTML\HTMLHelper::_('cleanImageURL', $file)->url);

                    if (is_file(urldecode($path))) {
                        $mailer->addAttachment($path, 'ur-logo', 'base64', mime_content_type($path), 'inline');
                        $data['ur_logo'] = 'ur-logo';
                    } else {
                        MailDebug::log("mail logo file not found: {$path}");
                    }
                } catch (\Throwable $e) {
                    MailDebug::log('mail logo could not be attached: ' . get_class($e) . ' - ' . $e->getMessage());
                }
            }
        }

        return $data;
    }

    /**
     * Insert or update the #__userreminder row tracking sends for this user.
     *
     * The opt-out code is written once (on INSERT) and intentionally NOT
     * overwritten on updates: regenerating it per send used to invalidate the
     * links in every previously delivered email.
     *
     * @param   int      $userId
     * @param   int      $type
     * @param   string|null  $optOutCode  The stable code used in the URL of the
     *                                    email just sent (generated when empty).
     *
     * @return  void
     *
     * @since   4.0.0
     */
    private function upsertReminderRow(int $userId, int $type, ?string $optOutCode = null): void
    {
        $now = ReminderOptions::utcNow();
        $code = $optOutCode ?: ApplicationHelper::getHash(UserHelper::genRandomPassword());

        // Single-statement upsert — one round trip instead of
        // UPDATE-then-INSERT per user. Values are escaped inline: the raw SQL
        // string cannot carry the Query object's ->bind() parameters, and
        // DatabaseDriver has no bind() of its own (that mistake 500s on the
        // first real send).
        $this->db->setQuery(
            'INSERT INTO ' . $this->db->quoteName('#__userreminder')
            . ' (' . $this->db->quoteName('userid') . ', ' . $this->db->quoteName('datesent')
            . ', ' . $this->db->quoteName('remindernumber') . ', ' . $this->db->quoteName('type')
            . ', ' . $this->db->quoteName('optoutcode') . ')'
            . ' VALUES (' . (int) $userId . ', ' . $this->db->quote($now) . ', 1, ' . (int) $type . ', ' . $this->db->quote($code) . ')'
            . ' ON DUPLICATE KEY UPDATE '
            . $this->db->quoteName('datesent') . ' = ' . $this->db->quote($now)
            . ', ' . $this->db->quoteName('remindernumber') . ' = ' . $this->db->quoteName('remindernumber') . ' + 1'
            . ', ' . $this->db->quoteName('type') . ' = ' . (int) $type
        )->execute();
    }

    /**
     * Append a row to #__userreminder_log.
     *
     * Stores both the human-readable (translated) description — handy for
     * export/search — and the raw language message key, which the log view
     * prefers so stored text can never go stale when the interface language
     * changes.
     *
     * @param   object  $row
     * @param   int     $type
     *
     * @return  void
     *
     * @since   4.0.0
     */
    private function writeLog(object $row, int $type): void
    {
        // The scheduler task runs outside the admin UI — make sure the
        // component language is loaded or Text::_() returns the raw key,
        // which would then be stored verbatim in the log table.
        Factory::getApplication()->getLanguage()->load('com_userreminder', JPATH_ADMINISTRATOR);

        $key = match ($type) {
            self::TYPE_NOT_ACTIVATED => 'COM_USERREMINDER_ACTIVATE_REMINDER_SENT',
            self::TYPE_NEVER_LOGGED  => 'COM_USERREMINDER_LOGIN_REMINDER_SENT',
            default                  => 'COM_USERREMINDER_USER_REMINDER_SENT',
        };

        $this->logBuffer[] = implode(',', [
            (int) $row->id,
            $this->db->quote($row->username ?? ''),
            $this->db->quote(\Joomla\CMS\Language\Text::_($key)),
            $this->db->quote($key),
            $this->db->quote(ReminderOptions::utcNow()),
        ]);

        if (count($this->logBuffer) >= self::LOG_BATCH) {
            $this->flushLog();
        }
    }

    /**
     * Flush the buffered log rows to #__userreminder_log in a single
     * multi-row INSERT (no-op when nothing was buffered).
     *
     * @return  void
     *
     * @since  6.0.0
     */
    private function flushLog(): void
    {
        if (!$this->logBuffer) {
            return;
        }

        $query = $this->db->getQuery(true)
            ->insert($this->db->quoteName('#__userreminder_log'))
            ->columns([
                $this->db->quoteName('userId'),
                $this->db->quoteName('username'),
                $this->db->quoteName('description'),
                $this->db->quoteName('message_key'),
                $this->db->quoteName('date'),
            ]);

        foreach ($this->logBuffer as $values) {
            $query->values($values);
        }

        $this->db->setQuery($query);
        $this->db->execute();

        $this->logBuffer = [];
    }

    /**
     * Remove reminder rows that no longer reflect reality.
     *
     * Only type 1/2 rows are maintained: a row is stale once its user is fully
     * healthy again — activated, unblocked AND logged in (activation is what
     * makes type 1 disappear from the pipeline; lastvisitDate is what makes
     * 'never logged in' disappear). Inactive-user (type 3) cycle state is
     * never touched from here: wiping it used to reset the cool-down and
     * reminder count, causing the same user to be emailed on every run.
     *
     * Orphan rows (user deleted, no matter the type) are removed as well.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    private function cleanupStaleRows(): void
    {
        $batch = 5000;

        // Part 1: healthy-again users (types 1 and 2 only).
        do {
            $ids = $this->db->setQuery(
                $this->db->getQuery(true)
                    ->select($this->db->quoteName('r.userid'))
                    ->from($this->db->quoteName('#__userreminder', 'r'))
                    ->innerJoin($this->db->quoteName('#__users', 'u') . ' ON ' . $this->db->quoteName('u.id') . ' = ' . $this->db->quoteName('r.userid'))
                    ->where($this->db->quoteName('r.type') . ' IN (1, 2)')
                    ->where($this->db->quoteName('u.activation') . ' = ' . $this->db->quote(''))
                    ->where($this->db->quoteName('u.block') . ' = 0')
                    ->where($this->db->quoteName('u.lastvisitDate') . ' IS NOT NULL'),
                0,
                $batch
            )->loadColumn() ?: [];

            if (!$ids) {
                break;
            }

            $this->deleteReminderIds($ids);
        } while (count($ids) === $batch);

        // Part 2: rows for users that no longer exist (any type).
        do {
            $orphanIds = $this->db->setQuery(
                $this->db->getQuery(true)
                    ->select($this->db->quoteName('r.userid'))
                    ->from($this->db->quoteName('#__userreminder', 'r'))
                    ->leftJoin($this->db->quoteName('#__users', 'u') . ' ON ' . $this->db->quoteName('u.id') . ' = ' . $this->db->quoteName('r.userid'))
                    ->where($this->db->quoteName('u.id') . ' IS NULL'),
                0,
                $batch
            )->loadColumn() ?: [];

            if (!$orphanIds) {
                break;
            }

            $this->deleteReminderIds($orphanIds);
        } while (count($orphanIds) === $batch);
    }

    /**
     * Delete reminder rows by user id (helper for the batched cleanup).
     *
     * @param   int[]  $ids
     *
     * @return  void
     *
     * @since  6.1.0
     */
    private function deleteReminderIds(array $ids): void
    {
        $this->db->setQuery(
            $this->db->getQuery(true)
                ->delete($this->db->quoteName('#__userreminder'))
                ->where($this->db->quoteName('userid') . ' IN (' . implode(',', array_map('intval', $ids)) . ')')
        )->execute();
    }

    /**
     * Maybe delete the user (depends on the type's "auto delete" option).
     *
     * @param   int              $userId
     * @param   int              $type
     * @param   ReminderOptions  $options
     *
     * @return  void
     *
     * @since   4.0.0
     */
    private function maybeDelete(int $userId, int $type, ReminderOptions $options): void
    {
        if (!$options->deleteAtEndOfCycle($type)) {
            return;
        }

        try {
            $user = Factory::getUser($userId);

            if ($user->id) {
                $user->delete();
            }
        } catch (\Throwable) {
            // Swallow — the cycle continues for other recipients either way.
        }
    }

    /**
     * Remove the user from configured opt-out-from-reminder groups.
     *
     * @param   int              $userId
     * @param   ReminderOptions  $options
     *
     * @return  int  Number of groups the user was removed from.
     *
     * @since   4.0.0
     */
    private function maybeRemoveFromGroups(int $userId, ReminderOptions $options): int
    {
        $groups = $options->removeFromGroups();

        if (empty($groups)) {
            return 0;
        }

        try {
            $user = Factory::getUser($userId);

            if (!$user->id) {
                return 0;
            }

            $removed = 0;

            foreach ($user->groups as $gid => $_) {
                if (in_array((int) $gid, $groups, true)) {
                    unset($user->groups[$gid]);
                    $removed++;
                }
            }

            if ($removed > 0) {
                $user->save();
            }

            return $removed;
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Resolve the configured mailer through the container's mailer factory.
     *
     * @return  \Joomla\CMS\Mail\Mail
     *
     * @since  6.1.0
     */
    private function resolveMailer(): \Joomla\CMS\Mail\Mail
    {
        $mailerFactory = $this->mailerFactory
            ?? Factory::getContainer()->get(MailerFactoryInterface::class);

        $mailer = $mailerFactory->createMailer();

        if (!$mailer instanceof \Joomla\CMS\Mail\Mail) {
            throw new \RuntimeException('The configured mailer does not extend Joomla\CMS\Mail\Mail.');
        }

        return $mailer;
    }

    /**
     * Dispatch onUserReminderBeforeSend.
     *
     * @param   int     $type
     * @param   object  $row
     * @param   string  $templateId
     * @param   array   $tags
     *
     * @return  bool  false when a listener vetoed the send.
     *
     * @since  6.1.0
     */
    private function dispatchBeforeSend(int $type, object $row, string $templateId, array $tags): bool
    {
        try {
            $event = new GenericEvent(self::EVENT_BEFORE_SEND, [
                'type'       => $type,
                'user'       => $row,
                'userId'     => (int) ($row->id ?? 0),
                'email'      => (string) ($row->email ?? ''),
                'templateId' => $templateId,
                'tags'       => $tags,
            ]);

            Factory::getApplication()->getDispatcher()->dispatch($event->getName(), $event);

            if ((bool) $event->getArgument('abort', false)) {
                MailDebug::log(sprintf(
                    'Send vetoed by onUserReminderBeforeSend for user #%d (type %d).',
                    (int) ($row->id ?? 0),
                    $type
                ));

                return false;
            }
        } catch (\Throwable) {
            // No dispatcher (e.g. some CLI contexts) or a faulty listener must
            // never block the pipeline.
        }

        return true;
    }

    /**
     * Dispatch onUserReminderAfterSend.
     *
     * @param   int     $type
     * @param   object  $row
     * @param   string  $email
     * @param   string  $templateId
     * @param   array   $tags
     * @param   bool    $sent
     *
     * @return  void
     *
     * @since  6.1.0
     */
    private function dispatchAfter(int $type, object $row, string $email, string $templateId, array $tags, bool $sent): void
    {
        try {
            $event = new GenericEvent(self::EVENT_AFTER_SEND, [
                'type'       => $type,
                'user'       => $row,
                'userId'     => (int) ($row->id ?? 0),
                'email'      => $email,
                'templateId' => $templateId,
                'tags'       => $tags,
                'sent'       => $sent,
            ]);

            Factory::getApplication()->getDispatcher()->dispatch($event->getName(), $event);
        } catch (\Throwable) {
            // Same as before: never let listeners break the pipeline.
        }
    }
}
