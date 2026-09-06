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
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Mail\Mail;
use Joomla\CMS\Mail\MailTemplate;
use Joomla\CMS\User\UserHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Filesystem\Path;
use Joomla\Registry\Registry;
use JoomCoder\Component\UserReminder\Administrator\Helper\ActivationUrlHelper;
use JoomCoder\Component\UserReminder\Administrator\Helper\SiteUrl;

/**
 * SendService — pure email-sending pipeline shared by the admin UI and the
 * system plugin. No rendering, no MVC, just business logic.
 *
 * Two methods:
 *   - processRegistrationReminders(): type 1 (not activated) + type 2 (never logged)
 *   - processInactiveUserReminders(): type 3 (existing users inactive for X days)
 *
 * @since  4.0.0
 */
final class SendService
{
    public const TYPE_NOT_ACTIVATED = 1;
    public const TYPE_NEVER_LOGGED  = 2;
    public const TYPE_INACTIVE_USER = 3;

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

    public function __construct(private DatabaseInterface $db)
    {
    }

    /**
     * Populate the request context when it is missing.
     *
     * Joomla 4's CLI does not populate $_SERVER['HTTP_HOST'] (only the
     * --live-site option does), and core's MailTemplate::send() needs it to
     * absolutize relative URLs (MailHelper::convertRelativeToAbsoluteUrls) —
     * without it every cron run fatalled. Fall back to the live_site config,
     * then to http://localhost.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    private function ensureRequestContext(): void
    {
        if (isset($_SERVER['HTTP_HOST'])) {
            return;
        }

        $live = trim((string) Factory::getApplication()->get('live_site', ''), " \t/");

        if ($live !== '' && ($parts = parse_url('//' . str_replace('//', '/', $live))) !== false) {
            $_SERVER['HTTP_HOST']   = ($parts['host'] ?? 'localhost') . (isset($parts['port']) ? ':' . $parts['port'] : '');
            $_SERVER['REQUEST_URI'] = ($parts['path'] ?? '') . '/';
        } else {
            $_SERVER['HTTP_HOST']   = 'localhost';
            $_SERVER['REQUEST_URI'] = '/';
        }

        $_SERVER['SCRIPT_NAME'] = $_SERVER['REQUEST_URI'];
    }

    /**
     * Resolve the service. The component provider registers SendService in the
     * component's DI container; fall back to direct construction when the
     * global container is asked (controllers, plugins, CLI).
     *
     * @return  self
     *
     * @since   4.2.0
     */
    public static function instance(): self
    {
        try {
            return Factory::getContainer()->get(self::class);
        } catch (\Throwable) {
            return new self(Factory::getContainer()->get(DatabaseInterface::class));
        }
    }

    /**
     * Send reminders to users who registered but never activated or never logged in.
     *
     * @param   bool   $logToTable  Write a row to #__userreminder_log.
     * @param   int    $offset      SQL offset (0-based).
     * @param   int    $limit       Batch size.
     *
     * @return  array{processed:int, sent:int, deleted:int, errors:int}
     *
     * @since   4.0.0
     */
    public function processRegistrationReminders(bool $logToTable, int $offset = 0, int $limit = 0): array
    {
        $this->ensureRequestContext();
        $params = ComponentHelper::getParams('com_userreminder');
        $limit  = $limit > 0 ? $limit : (int) $params->get('number_email', 50);

        // Clean up stale rows first (user activated in the meantime, etc).
        $this->cleanupStaleRows();

        $candidates = $this->buildRegistrationCandidateQuery($params);

        $this->db->setQuery($candidates, $offset, $limit);
        $rows = $this->db->loadObjectList() ?: [];

        $stats = ['processed' => 0, 'sent' => 0, 'deleted' => 0, 'errors' => 0];
        $maxSends = (int) $params->get('maxemailstosend', 0);

        foreach ($rows as $row) {
            if ($maxSends > 0 && $stats['sent'] >= $maxSends) {
                break;
            }

            $stats['processed']++;
            $type = (int) $row->type === self::TYPE_NOT_ACTIVATED
                ? self::TYPE_NOT_ACTIVATED
                : self::TYPE_NEVER_LOGGED;

            $action = $this->decideAction($row, $type, $params);

            if ($action === 'send') {
                $sent = $this->sendOne($row, $type, $params);
                if ($sent) {
                    $this->upsertReminderRow($row->id, $type);
                    $stats['sent']++;
                    if ($logToTable) {
                        $this->writeLog($row, $type);
                    }
                } else {
                    $stats['errors']++;
                }
            } elseif ($action === 'delete') {
                $this->maybeDelete($row->id, $type, $params);
                $stats['deleted']++;
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
     * @return  array{processed:int, sent:int, deleted:int, removedGroups:int, errors:int}
     *
     * @since   4.0.0
     */
    public function processInactiveUserReminders(bool $logToTable, int $offset = 0, int $limit = 0): array
    {
        $this->ensureRequestContext();
        $params = ComponentHelper::getParams('com_userreminder');
        $limit  = $limit > 0 ? $limit : (int) $params->get('number_email', 50);

        $days = (int) $params->get('numberOfDaysExistingUser', 180);

        $query = $this->db->getQuery(true)
            ->select('a.id, a.email, a.block, a.registerDate, a.lastvisitDate, a.activation, a.username, a.name')
            ->select('b.datesent, b.remindernumber, b.type as savedtype, b.optoutcode')
            ->select('(TO_DAYS(NOW()) - TO_DAYS(a.lastvisitDate)) AS nodays')
            ->from($this->db->quoteName('#__users', 'a'))
            ->leftJoin($this->db->quoteName('#__userreminder', 'b') . ' ON b.userid = a.id')
            ->leftJoin($this->db->quoteName('#__userreminder_optout', 'o') . ' ON o.user_id = a.id')
            ->where('o.user_id IS NULL')
            ->where('a.lastvisitDate IS NOT NULL')
            ->where('a.block = 0')
            ->where($this->db->quoteName('a.lastvisitDate') . ' < DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY)');

        $this->applyGroupExclusion($query);

        $query->order('a.lastvisitDate ASC');

        $this->db->setQuery($query, $offset, $limit);
        $rows = $this->db->loadObjectList() ?: [];

        $stats = ['processed' => 0, 'sent' => 0, 'deleted' => 0, 'removedGroups' => 0, 'errors' => 0];
        $maxSends = (int) $params->get('maxemailstosend', 0);

        foreach ($rows as $row) {
            if ($maxSends > 0 && $stats['sent'] >= $maxSends) {
                break;
            }

            $stats['processed']++;
            $row->type = self::TYPE_INACTIVE_USER;

            // Existing reminder row already exists (b.savedtype = 3)? Then we're in the cycle.
            $reminderNumber = (int) ($row->remindernumber ?? 0);
            $maxReminders   = (int) $params->get('numberOfReminders', 1);

            if ($reminderNumber >= $maxReminders) {
                // Cycle is over, optionally delete / remove from groups.
                if ((int) $params->get('enableDeleteExistingUsers', 0)) {
                    $this->maybeDelete($row->id, self::TYPE_INACTIVE_USER, $params);
                    $stats['deleted']++;
                } else {
                    $removedGroups = $this->maybeRemoveFromGroups($row->id, $params);
                    if ($removedGroups > 0) {
                        $stats['removedGroups']++;
                    }
                }
                continue;
            }

            // Eligible to send?
            if ($this->isWithinCoolDown($row->datesent, (int) $params->get('numberOfDays', 1))) {
                continue;
            }

            $sent = $this->sendOne($row, self::TYPE_INACTIVE_USER, $params);
            if ($sent) {
                $this->upsertReminderRow($row->id, self::TYPE_INACTIVE_USER);
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
     * @param   Registry  $params
     *
     * @return  \Query
     *
     * @since   4.0.0
     */
    private function buildRegistrationCandidateQuery(Registry $params): \Joomla\Database\QueryInterface
    {
        $db = $this->db;

        $days = max(1, (int) $params->get('numberOfDays', 1));

        // Without "send first reminder immediately", a user's first reminder
        // waits until the account is at least `$days` old.
        $firstSendAgeFilter = (int) $params->get('enabledSendImed', 0) === 1
            ? null
            : $db->quoteName('a.registerDate') . ' < DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY)';

        $notActivated = $db->getQuery(true)
            ->select('a.id, a.email, a.activation, a.block, a.registerDate, a.lastvisitDate, a.username')
            ->select('b.datesent, b.remindernumber, b.optoutcode')
            ->select((string) self::TYPE_NOT_ACTIVATED . ' AS type')
            ->from($db->quoteName('#__users', 'a'))
            ->leftJoin($db->quoteName('#__userreminder', 'b') . ' ON b.userid = a.id')
            ->leftJoin($db->quoteName('#__userreminder_optout', 'o') . ' ON o.user_id = a.id')
            ->where('o.user_id IS NULL')
            ->where('a.activation <> ' . $db->quote(''))
            ->where('a.block >= 1')
            ->where('a.lastvisitDate IS NULL');

        if ($firstSendAgeFilter !== null) {
            $notActivated->where($firstSendAgeFilter);
        }

        $this->applyGroupExclusion($notActivated);

        $neverLogged = $db->getQuery(true)
            ->select('a.id, a.email, a.activation, a.block, a.registerDate, a.lastvisitDate, a.username')
            ->select('b.datesent, b.remindernumber, b.optoutcode')
            ->select((string) self::TYPE_NEVER_LOGGED . ' AS type')
            ->from($db->quoteName('#__users', 'a'))
            ->leftJoin($db->quoteName('#__userreminder', 'b') . ' ON b.userid = a.id')
            ->leftJoin($db->quoteName('#__userreminder_optout', 'o') . ' ON o.user_id = a.id')
            ->where('o.user_id IS NULL')
            ->where('a.block = 0')
            ->where('a.lastvisitDate IS NULL')
            ->where('a.activation = ' . $db->quote(''));

        if ($firstSendAgeFilter !== null) {
            $neverLogged->where($firstSendAgeFilter);
        }

        $this->applyGroupExclusion($neverLogged);

        return $notActivated->union($neverLogged);
    }

    /**
     * Decide whether to send / skip / delete the user.
     *
     * @param   object    $row
     * @param   int       $type
     * @param   Registry  $params
     *
     * @return  string  'send'|'skip'|'delete'
     *
     * @since   4.0.0
     */
    private function decideAction(object $row, int $type, Registry $params): string
    {
        $enabled = (int) $params->get(
            $type === self::TYPE_NOT_ACTIVATED ? 'enableActivateReminder' : 'enableLoginReminder',
            1
        );

        if (!$enabled) {
            return 'skip';
        }

        $reminderNumber = (int) ($row->remindernumber ?? 0);
        $maxReminders   = (int) $params->get('numberOfReminders', 1);

        if ($reminderNumber === 0) {
            // Defensive: mirrors the SQL age filter in the candidate query in
            // case the row reaches this method through another path.
            if ((int) $params->get('enabledSendImed', 0) !== 1
                && $this->isWithinCoolDown($row->registerDate, max(1, (int) $params->get('numberOfDays', 1)))) {
                return 'skip';
            }

            return 'send';
        }

        if ($reminderNumber >= $maxReminders) {
            return $type === self::TYPE_NOT_ACTIVATED
                ? ((int) $params->get('enableDeleteUsers', 0) ? 'delete' : 'skip')
                : ((int) $params->get('enableDeleteUsersLogin', 0) ? 'delete' : 'skip');
        }

        if ($this->isWithinCoolDown($row->datesent, (int) $params->get('numberOfDays', 1))) {
            return 'skip';
        }

        return 'send';
    }

    /**
     * Are we still inside the cool-down window? If the user received a reminder
     * less than `$days` ago, skip.
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

        $sentTs    = strtotime($datesent);
        $coolDown  = strtotime('-' . $days . ' days', time());

        return $sentTs > $coolDown;
    }

    /**
     * Send one reminder email through the Joomla mail template
     * (com_userreminder.userreminder.reminder_* — editable in System → Mail Templates).
     *
     * @param   object    $row
     * @param   int       $type
     * @param   Registry  $params
     *
     * @return  bool
     *
     * @since   4.0.0
     */
    public function sendOne(object $row, int $type, Registry $params): bool
    {
        $app    = Factory::getApplication();
        $sitename = $app->get('sitename');
        $mailfrom = $app->get('mailfrom');
        $fromname = $app->get('fromname');

        $templateId = match ($type) {
            self::TYPE_NOT_ACTIVATED => 'com_userreminder.userreminder.reminder_activation',
            self::TYPE_NEVER_LOGGED  => 'com_userreminder.userreminder.reminder_login',
            default                  => 'com_userreminder.userreminder.reminder_inactive',
        };

        // Build tags.
        $user        = Factory::getUser($row->id);
        $name        = $user->get('name', $row->name ?? '');
        $username    = $user->get('username', $row->username ?? '');
        $email       = $user->get('email', $row->email);
        $siteUrl     = SiteUrl::root();

        $optOutCode  = $row->optoutcode ?: ApplicationHelper::getHash(UserHelper::genRandomPassword());
        $optOutUrl   = $siteUrl . 'index.php?option=com_userreminder&view=optout&uid=' . $optOutCode;
        $passwordReset = $siteUrl . ($params->get('passwordReset', 'index.php?option=com_users&view=reset'));

        // BCC for admins (optional).
        $bcc = null;
        if ((int) $params->get('enabledBccToAdmin', 1) === 1) {
            $bcc = $params->get('bccEmailAddress', '');
        }

        try {
            $mailer = MailCapture::createMailer();

            $mailer->setSender($mailfrom, $fromname);

            if (!empty($bcc)) {
                $mailer->addBcc($bcc);
            }

            $mailTemplate = new MailTemplate($templateId, $app->getLanguage()->getTag(), $mailer);
            $mailTemplate->addTemplateData([
                'NAME'             => $name,
                'SITENAME'         => $sitename,
                'SITELINK'         => $siteUrl,
                'USERNAME'         => $username,
                'PASSWORD_RESET_URL' => $passwordReset,
                'OPTOUT_URL'       => $optOutUrl,
                'ACTIVATE_URL'     => ActivationUrlHelper::get($row, $params),
            ]);

            // Custom HTML container (header / footer / logo), configured under
            // User Reminder → Options → Email Container. The layout only
            // applies to our emails — everything else keeps Joomla's stock
            // container (see layouts/joomla/mail/userreminder.php).
            // MailTemplate::addLayoutTemplateData() is Joomla 5+; on Joomla 4
            // the template's HTML body is sent as-is.
            if (method_exists($mailTemplate, 'addLayoutTemplateData')) {
                $mailTemplate->addLayoutTemplateData($this->containerLayoutData($params, $mailer, $sitename, $siteUrl));
            }

            $mailTemplate->addRecipient($email);

            $sent = $mailTemplate->send();

            return $sent === true;
        } catch (\Throwable $e) {
            Log::add(Text::sprintf('COM_USERREMINDER_SEND_ERROR', $email, $e->getMessage()), Log::ERROR, 'com_userreminder');

            return false;
        }
    }

    /**
     * Send one test email per requested reminder type to the configured test user.
     *
     * @param   int[]  $types  One or more TYPE_* constants.
     *
     * @return  bool  true when every email was sent (debug mode always succeeds).
     *
     * @since   4.2.0
     */
    public function sendTestMail(array $types): bool
    {
        $params = ComponentHelper::getParams('com_userreminder');
        $app    = Factory::getApplication();
        $userId = (int) $params->get('test_user', $app->getIdentity()->id);

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
            'optoutcode'     => null,
            'remindernumber' => 0,
            'datesent'       => null,
        ];

        $ok = true;

        foreach ($types as $type) {
            if (!$this->sendOne($row, (int) $type, $params)) {
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
     * Append a line to the mail capture log (same file MailCapture uses).
     *
     * Only writes when the component's debug mode is on — the log can contain
     * tokenised links and personal data, so it must stay opt-in.
     *
     * @param   string  $message  Message to append.
     *
     * @return  void
     *
     * @since   4.2.1
     */
    public static function debugLog(string $message): void
    {
        if (!MailCapture::debugEnabled()) {
            return;
        }

        try {
            $app = Factory::getApplication();
            $log = rtrim($app->get('log_path', JPATH_ADMINISTRATOR . '/logs'), '/\\')
                . '/userreminder-mail.log';

            @file_put_contents($log, '[' . Factory::getDate()->toSql() . '] ' . $message . "\n", FILE_APPEND | LOCK_EX);
        } catch (\Throwable) {
            // Never let logging break a send.
        }
    }

    /**
     * Build the extra layout data for the custom HTML mail container.
     *
     * @param   Registry  $params   Component params.
     * @param   Mail      $mailer   The mailer — the logo is attached inline.
     * @param   string    $sitename Site name.
     * @param   string    $siteUrl  Site root URL.
     *
     * @return  array
     *
     * @since   4.2.1
     */
    private function containerLayoutData(Registry $params, Mail $mailer, string $sitename, string $siteUrl): array
    {
        if ((int) $params->get('mail_container', 1) !== 1) {
            return [];
        }

        $header = trim((string) $params->get('mail_container_header', ''));

        $footer = trim((string) $params->get('mail_container_footer', ''));

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
            'ur_footer_bg'     => $this->normalizeColor($params->get('mail_container_footer_bg', '#112855'), '#112855'),
            'ur_footer_color'  => $this->normalizeColor($params->get('mail_container_footer_color', '#cccccc'), '#cccccc'),
        ];

        // Attach the configured logo inline (embedded image, no external URL).
        if ((int) $params->get('mail_container_logo', 0) === 1) {
            $file = (string) $params->get('mail_container_logofile', '');

            if ($file !== '') {
                try {
                    $path = Path::check(JPATH_ROOT . '/' . HTMLHelper::_('cleanImageURL', $file)->url);

                    if (is_file(urldecode($path))) {
                        $mailer->addAttachment($path, 'ur-logo', 'base64', mime_content_type($path), 'inline');
                        $data['ur_logo'] = 'ur-logo';
                    } else {
                        self::debugLog("mail logo file not found: {$path}");
                    }
                } catch (\Throwable $e) {
                    self::debugLog('mail logo could not be attached: ' . get_class($e) . ' - ' . $e->getMessage());
                }
            }
        }

        return $data;
    }

    /**
     * Insert or update the row in #__userreminder that tracks sends for this user.
     *
     * @param   int  $userId
     * @param   int  $type
     *
     * @return  void
     *
     * @since   4.0.0
     */
    private function upsertReminderRow(int $userId, int $type): void
    {
        $optOutCode = ApplicationHelper::getHash(UserHelper::genRandomPassword());

        // Single-statement upsert — one round trip instead of
        // UPDATE-then-INSERT per user.
        $this->db->setQuery(
            'INSERT INTO ' . $this->db->quoteName('#__userreminder')
            . ' (' . $this->db->quoteName('userid') . ', ' . $this->db->quoteName('datesent')
            . ', ' . $this->db->quoteName('remindernumber') . ', ' . $this->db->quoteName('type')
            . ', ' . $this->db->quoteName('optoutcode') . ')'
            . ' VALUES (' . (int) $userId . ', NOW(), 1, ' . (int) $type . ', '
            . $this->db->quote($optOutCode) . ')'
            . ' ON DUPLICATE KEY UPDATE '
            . $this->db->quoteName('datesent') . ' = NOW()'
            . ', ' . $this->db->quoteName('remindernumber') . ' = ' . $this->db->quoteName('remindernumber') . ' + 1'
            . ', ' . $this->db->quoteName('type') . ' = ' . (int) $type
            . ', ' . $this->db->quoteName('optoutcode') . ' = ' . $this->db->quote($optOutCode)
        )->execute();
    }

    /**
     * Append a row to #__userreminder_log.
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

        $description = match ($type) {
            self::TYPE_NOT_ACTIVATED => Text::_('COM_USERREMINDER_ACTIVATE_REMINDER_SENT'),
            self::TYPE_NEVER_LOGGED  => Text::_('COM_USERREMINDER_LOGIN_REMINDER_SENT'),
            default                  => Text::_('COM_USERREMINDER_USER_REMINDER_SENT'),
        };

        $this->logBuffer[] = implode(',', [
            (int) $row->id,
            $this->db->quote($row->username ?? ''),
            $this->db->quote(Text::_('COM_USERREMINDER_LOG_PREFIX') . ' ' . $description),
            $this->db->quote(Factory::getDate()->toSql()),
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
     * Remove users who have completed their journey — no need to keep their
     * reminder record around.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    private function cleanupStaleRows(): void
    {
        // Delete reminder rows where the user has now activated AND logged in
        // (type 1 = not activated, type 2 = never logged) — no need to keep
        // the reminder record around once the user is healthy.
        // Batched so row locks stay short on sites with many stale rows.
        $batch = 5000;

        do {
            $ids = $this->db->setQuery(
                $this->db->getQuery(true)
                    ->select($this->db->quoteName('r.userid'))
                    ->from($this->db->quoteName('#__userreminder', 'r'))
                    ->innerJoin($this->db->quoteName('#__users', 'u') . ' ON u.id = r.userid')
                    ->where('u.lastvisitDate IS NOT NULL')
                    ->where('u.block = 0'),
                0,
                $batch
            )->loadColumn() ?: [];

            if (!$ids) {
                break;
            }

            $this->db->setQuery(
                $this->db->getQuery(true)
                    ->delete($this->db->quoteName('#__userreminder'))
                    ->where('userid IN (' . implode(',', array_map('intval', $ids)) . ')')
            )->execute();
        } while (count($ids) === $batch);
    }

    /**
     * Maybe delete the user (depends on the `enableDelete*` params).
     *
     * @param   int       $userId
     * @param   int       $type
     * @param   Registry  $params
     *
     * @return  void
     *
     * @since   4.0.0
     */
    private function maybeDelete(int $userId, int $type, Registry $params): void
    {
        $enableKey = match ($type) {
            self::TYPE_NOT_ACTIVATED => 'enableDeleteUsers',
            self::TYPE_NEVER_LOGGED  => 'enableDeleteUsersLogin',
            default                  => 'enableDeleteExistingUsers',
        };

        if ((int) $params->get($enableKey, 0) !== 1) {
            return;
        }

        try {
            $user = Factory::getUser($userId);
            if ($user->id) {
                $user->delete();
            }
        } catch (\Throwable) {
            // Swallow — caller already counts deletes via $row->id presence.
        }
    }

    /**
     * Remove the user from configured opt-out-from-reminder groups.
     *
     * @param   int       $userId
     * @param   Registry  $params
     *
     * @return  int  Number of groups the user was removed from.
     *
     * @since   4.0.0
     */
    private function maybeRemoveFromGroups(int $userId, Registry $params): int
    {
        $groups = (array) $params->get('removefromusergroups', []);

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
                if (in_array((int) $gid, array_map('intval', $groups), true)) {
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
}