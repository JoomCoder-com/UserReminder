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
use Joomla\CMS\Router\Route;
use Joomla\Database\QueryInterface;
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

    /** Mail template per reminder type (com_userreminder.userreminder.<name>). */
    private const TEMPLATES = [
        self::TYPE_NOT_ACTIVATED => 'reminder_activation',
        self::TYPE_NEVER_LOGGED  => 'reminder_login',
        self::TYPE_INACTIVE_USER => 'reminder_inactive',
    ];

    /** Counters returned by both pipelines. */
    private const EMPTY_STATS = [
        'processed' => 0, 'sent' => 0, 'deleted' => 0, 'skipped' => 0,
        'simulated' => 0, 'removedGroups' => 0, 'errors' => 0, 'dryRun' => false,
    ];

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
        // Send errors (Log::add(..., 'com_userreminder')) went nowhere: no logger listened to the
        // category, so a run only reported "mail errors N" with no reason. Write them to
        // administrator/logs/com_userreminder.php.
        static $loggerAdded = false;

        if (!$loggerAdded) {
            $loggerAdded = true;
            Log::addLogger(['text_file' => 'com_userreminder.php'], Log::WARNING | Log::ERROR | Log::CRITICAL | Log::ALERT | Log::EMERGENCY, ['com_userreminder']);
        }
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
     * fatal. The reminder links themselves are built from SiteUrl::root();
     * this shim uses the same address and only serves core's URL
     * absolutisation, applied only when the host is truly missing.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    private function ensureRequestContext(): void
    {
        // Resolved before touching $_SERVER, so a made-up host never becomes the site address.
        $parts = parse_url((string) SiteUrl::root()) ?: [];
        $path  = rtrim($parts['path'] ?? '', '/') . '/';

        if (empty($_SERVER['HTTP_HOST'])) {
            $_SERVER['HTTP_HOST'] = ($parts['host'] ?? 'localhost') . (isset($parts['port']) ? ':' . $parts['port'] : '');
        }

        // Joomla's console copies the path of live_site into REQUEST_URI: empty for
        // "https://example.com". Uri::getInstance() then appends the relative script name
        // ("example.comcli/joomla.php") and every mail template send fails with
        // "Could not parse the requested URI" (seen on Joomla 5.4.9).
        if (!str_starts_with((string) ($_SERVER['REQUEST_URI'] ?? ''), '/')) {
            $_SERVER['REQUEST_URI'] = $path;
        }

        if (!str_starts_with((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/')) {
            $_SERVER['SCRIPT_NAME'] = $path;
        }
    }

    /**
     * Send reminders to users who registered but never activated or never logged in.
     *
     * @param   bool  $logToTable  Write a row to #__userreminder_log for every real send.
     * @param   int   $offset      SQL offset (0-based).
     * @param   int   $limit       Batch size (0 = ReminderOptions::batchSize()).
     *
     * @return  array{processed:int, sent:int, deleted:int, skipped:int, simulated:int, removedGroups:int, errors:int, dryRun:bool}
     *
     * @since   4.0.0
     */
    public function processRegistrationReminders(bool $logToTable = true, int $offset = 0, int $limit = 0): array
    {
        $options = $this->startRun();

        // Clean up rows whose users are healthy again (batched).
        $this->cleanupStaleRows();

        $this->db->setQuery($this->buildRegistrationCandidateQuery($options), $offset, $limit > 0 ? $limit : $options->batchSize());

        return $this->processRows($this->db->loadObjectList() ?: [], $options, $logToTable);
    }

    /**
     * Send reminders to existing registered users who haven't visited in X days.
     *
     * @param   bool  $logToTable
     * @param   int   $offset
     * @param   int   $limit
     *
     * @return  array{processed:int, sent:int, deleted:int, skipped:int, simulated:int, removedGroups:int, errors:int, dryRun:bool}
     *
     * @since   4.0.0
     */
    public function processInactiveUserReminders(bool $logToTable = true, int $offset = 0, int $limit = 0): array
    {
        $options = $this->startRun();

        // Switched off in Options: nothing to do.
        if (!$options->typeEnabled(self::TYPE_INACTIVE_USER)) {
            return self::EMPTY_STATS;
        }

        $query = Audience::query($this->db, $options, self::TYPE_INACTIVE_USER)
            ->select('a.id, a.email, a.block, a.registerDate, a.lastvisitDate, a.activation, a.username, a.name')
            ->select('b.datesent, b.remindernumber, b.optoutcode')
            ->select((string) self::TYPE_INACTIVE_USER . ' AS type');

        $query->order($this->db->quoteName('a.lastvisitDate') . ' ASC');

        $this->db->setQuery($query, $offset, $limit > 0 ? $limit : $options->batchSize());

        return $this->processRows($this->db->loadObjectList() ?: [], $options, $logToTable);
    }

    /**
     * Shared set-up of both pipelines.
     *
     * @return  ReminderOptions
     *
     * @since  6.1.0
     */
    private function startRun(): ReminderOptions
    {
        $this->ensureRequestContext();
        $this->loadThirdPartyPlugins();

        return ReminderOptions::fromComponent();
    }

    /**
     * Apply plannedAction() to each candidate row: send, delete, remove from
     * groups or skip. In dry-run mode nothing is sent, stored or deleted.
     *
     * @param   object[]         $rows        Candidate rows, each with a ->type.
     * @param   ReminderOptions  $options
     * @param   bool             $logToTable  Write a log row for every real send.
     *
     * @return  array  See EMPTY_STATS.
     *
     * @since  6.1.0
     */
    private function processRows(array $rows, ReminderOptions $options, bool $logToTable): array
    {
        $stats    = self::EMPTY_STATS;
        $maxSends = $options->maxPerRun();
        $dryRun   = $options->debug();

        foreach ($rows as $row) {
            if (!$dryRun && $maxSends > 0 && $stats['sent'] >= $maxSends) {
                break;
            }

            $stats['processed']++;

            $type = (int) ($row->type ?? 0);
            $type = isset(self::TEMPLATES[$type]) ? $type : self::TYPE_NEVER_LOGGED;

            $action = $this->plannedAction($row, $type, $options);

            if ($action === 'send') {
                if ($dryRun) {
                    $this->simulateSend($row, $type, $options);
                    $stats['dryRun'] = true;
                    $stats['simulated']++;
                } elseif ($this->sendOne($row, $type, $options)) {
                    $this->upsertReminderRow($row->id, $type, $row->optoutcode ?? null);
                    $stats['sent']++;

                    if ($logToTable) {
                        $this->writeLog($row, $type);
                    }
                } else {
                    $stats['errors']++;
                }
            } elseif ($action === 'delete' || $action === 'removegroups') {
                if ($dryRun) {
                    MailDebug::log(sprintf('DRY-RUN would %s user #%d (cycle complete, type %d)', $action === 'delete' ? 'delete' : 'remove from groups', $row->id, $type));
                    $stats['dryRun'] = true;
                } elseif ($action === 'delete') {
                    $this->deleteUser((int) $row->id);
                    $stats['deleted']++;
                } elseif ($this->removeFromGroups((int) $row->id, $options) > 0) {
                    $stats['removedGroups']++;
                } else {
                    $stats['skipped']++;
                }
            } else {
                $stats['skipped']++;
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
            $row->optoutcode = self::newOptOutCode();
        }

        $siteUrl = SiteUrl::root();

        if ($siteUrl === null) {
            return;
        }

        MailDebug::log(sprintf(
            'DRY-RUN send: template=%s user=#%d "%s" <%s> tags=%s',
            self::TEMPLATES[$type] ?? self::TEMPLATES[self::TYPE_INACTIVE_USER],
            (int) $row->id,
            (string) ($row->name ?? $row->username ?? ''),
            (string) $row->email,
            json_encode($this->buildTags($row, $type, $options, $siteUrl), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        ));
    }

    /**
     * A new random opt-out code (stored once per user, then kept stable).
     *
     * @return  string
     *
     * @since  6.1.0
     */
    private static function newOptOutCode(): string
    {
        return ApplicationHelper::getHash(UserHelper::genRandomPassword());
    }

    /**
     * Users needing an activation or "never logged in" reminder: one UNION
     * half per type, so each row carries its type.
     *
     * @param   ReminderOptions  $options
     *
     * @return  QueryInterface
     *
     * @since   4.0.0
     */
    private function buildRegistrationCandidateQuery(ReminderOptions $options): QueryInterface
    {
        $half = fn(int $type) => Audience::query($this->db, $options, $type)
            ->select('a.id, a.email, a.activation, a.block, a.registerDate, a.lastvisitDate, a.username, a.name')
            ->select('b.datesent, b.remindernumber, b.optoutcode')
            ->select($type . ' AS type');

        return $half(self::TYPE_NOT_ACTIVATED)->union($half(self::TYPE_NEVER_LOGGED));
    }

    /**
     * What the next run will do for a user, for the admin lists' "Action to Perform"
     * column, and the decision the run itself acts on (processRows()), so the two can
     * never disagree.
     *
     * @param   object                $row      User row with remindernumber, datesent, registerDate.
     * @param   int                   $type     One of the TYPE_* constants.
     * @param   ReminderOptions|null  $options  The run's options (the admin lists pass none).
     *
     * @return  string  send|wait|done|delete|removegroups|off
     *
     * @since   6.0.1
     */
    public function plannedAction(object $row, int $type, ?ReminderOptions $options = null): string
    {
        static $listOptions = null;
        $options ??= $listOptions ??= ReminderOptions::fromComponent();

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

        $templateId = 'com_userreminder.userreminder.' . (self::TEMPLATES[$type] ?? self::TEMPLATES[self::TYPE_INACTIVE_USER]);

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
            $row->optoutcode = self::newOptOutCode();
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

            // Options → Delivery → BCC: a copy of every reminder to the admin address.
            $bcc = $options->bccAddress();

            if ($bcc !== null) {
                $mailTemplate->addRecipient($bcc, null, 'bcc');
            }

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
        $activateUrl  = ActivationUrlHelper::get($row, $siteUrl, $options);

        return [
            'NAME'               => $name ?: $username,
            'SITENAME'           => (string) $app->get('sitename'),
            'SITELINK'           => $siteUrl,
            'USERNAME'           => $username,
            'EMAIL'              => $email,
            'PASSWORD_RESET_URL' => $this->sitePath($siteUrl, $options->passwordResetPath()),
            'OPTOUT_URL'         => $optOutUrl,
            'ACTIVATE_URL'       => $activateUrl,
        ];
    }

    /**
     * Absolute opt-out URL for a code (SEF when the site router can build one).
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
        return SiteUrl::route($siteUrl, 'index.php?option=com_userreminder&view=optout&uid=' . rawurlencode($code));
    }

    /**
     * Absolute URL for a configured path: Joomla paths (index.php?...) go
     * through the site router, anything else is appended to the site root.
     *
     * @param   string  $siteUrl
     * @param   string  $path
     *
     * @return  string
     *
     * @since  6.1.0
     */
    private function sitePath(string $siteUrl, string $path): string
    {
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return str_starts_with(ltrim($path, '/'), 'index.php?')
            ? SiteUrl::route($siteUrl, ltrim($path, '/'))
            : $siteUrl . ltrim($path, '/');
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
            'optoutcode'     => self::newOptOutCode(),
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
        $code = $optOutCode ?: self::newOptOutCode();

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
            $this->db->quote(Text::_($key)),
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
     * Delete a user whose reminder cycle ended with auto-delete on.
     *
     * @param   int  $userId
     *
     * @return  void
     *
     * @since   4.0.0
     */
    private function deleteUser(int $userId): void
    {
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
     * Remove the user from the groups set in Options (end of the inactive cycle).
     *
     * @param   int              $userId
     * @param   ReminderOptions  $options
     *
     * @return  int  Number of groups the user was removed from.
     *
     * @since   4.0.0
     */
    private function removeFromGroups(int $userId, ReminderOptions $options): int
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
     * @return  Mail
     *
     * @since  6.1.0
     */
    private function resolveMailer(): Mail
    {
        $mailerFactory = $this->mailerFactory
            ?? Factory::getContainer()->get(MailerFactoryInterface::class);

        $mailer = $mailerFactory->createMailer();

        if (!$mailer instanceof Mail) {
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
