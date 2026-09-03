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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\UserHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;
use JoomCoder\Component\UserReminder\Administrator\Helper\ActivationUrlHelper;

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

    public function __construct(private DatabaseInterface $db)
    {
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
        $params = ComponentHelper::getParams('com_userreminder');
        $limit  = $limit > 0 ? $limit : (int) $params->get('number_email', 50);

        // Clean up stale rows first (user activated in the meantime, etc).
        $this->cleanupStaleRows();

        $candidates = $this->buildRegistrationCandidateQuery($params);

        $this->db->setQuery($candidates, $offset, $limit);
        $rows = $this->db->loadObjectList() ?: [];

        $stats = ['processed' => 0, 'sent' => 0, 'deleted' => 0, 'errors' => 0];

        foreach ($rows as $row) {
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
            ->where('(TO_DAYS(NOW()) - TO_DAYS(a.lastvisitDate)) > ' . $days)
            ->order('a.lastvisitDate ASC');

        $this->db->setQuery($query, $offset, $limit);
        $rows = $this->db->loadObjectList() ?: [];

        $stats = ['processed' => 0, 'sent' => 0, 'deleted' => 0, 'removedGroups' => 0, 'errors' => 0];

        foreach ($rows as $row) {
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

        return $stats;
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

        $notActivated = $db->getQuery(true)
            ->select('a.id, a.email, a.activation, a.block, a.registerDate, a.lastvisitDate')
            ->select('b.datesent, b.remindernumber, b.optoutcode')
            ->select((string) self::TYPE_NOT_ACTIVATED . ' AS type')
            ->from($db->quoteName('#__users', 'a'))
            ->leftJoin($db->quoteName('#__userreminder', 'b') . ' ON b.userid = a.id')
            ->leftJoin($db->quoteName('#__userreminder_optout', 'o') . ' ON o.user_id = a.id')
            ->where('o.user_id IS NULL')
            ->where('a.activation <> ' . $db->quote(''))
            ->where('a.block >= 1')
            ->where('a.lastvisitDate IS NULL')
            ->where('DATE_ADD(a.registerDate, INTERVAL ' . (int) $params->get('numberOfDays', 1) . ' DAY) < NOW()');

        $neverLogged = $db->getQuery(true)
            ->select('a.id, a.email, a.activation, a.block, a.registerDate, a.lastvisitDate')
            ->select('b.datesent, b.remindernumber, b.optoutcode')
            ->select((string) self::TYPE_NEVER_LOGGED . ' AS type')
            ->from($db->quoteName('#__users', 'a'))
            ->leftJoin($db->quoteName('#__userreminder', 'b') . ' ON b.userid = a.id')
            ->leftJoin($db->quoteName('#__userreminder_optout', 'o') . ' ON o.user_id = a.id')
            ->where('o.user_id IS NULL')
            ->where('a.block = 0')
            ->where('a.lastvisitDate IS NULL')
            ->where('a.activation = ' . $db->quote(''));

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
     * Send one reminder email.
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
        // Debug mode never actually sends.
        if ((int) $params->get('debugUserReminder', 0) === 1) {
            return true;
        }

        $app    = Factory::getApplication();
        $sitename = $app->get('sitename');
        $mailfrom = $app->get('mailfrom');
        $fromname = $app->get('fromname');

        $subjectKey = match ($type) {
            self::TYPE_NOT_ACTIVATED => 'regactivationEmailSubject',
            self::TYPE_NEVER_LOGGED  => 'regLoginEmailSubject',
            default                  => 'regExistingUserEmailSubject',
        };

        $bodyKey = match ($type) {
            self::TYPE_NOT_ACTIVATED => 'regActivationEmailBodyHTML',
            self::TYPE_NEVER_LOGGED  => 'regLoginEmailBodyHTML',
            default                  => 'regExistingUserEmailBodyHTML',
        };

        $defaultSubjectKey = match ($type) {
            self::TYPE_NOT_ACTIVATED => 'USERREMINDER_REMINDER_DETAILS_FOR',
            self::TYPE_NEVER_LOGGED  => 'USERREMINDER_LOGINREMINDER_DETAILS_FOR',
            default                  => 'USERREMINDER_EXISTINGUSERREMINDER_DETAILS_FOR',
        };

        $defaultBodyKey = match ($type) {
            self::TYPE_NOT_ACTIVATED => 'USERREMINDER_SEND_MSG_REMINDER',
            self::TYPE_NEVER_LOGGED  => 'USERREMINDER_SEND_MSG_LOGINREMINDER',
            default                  => 'USERREMINDER_SEND_MSG_EXISTINGUSERREMINDER',
        };

        $subject = $params->get($subjectKey, '') ?: Text::_($defaultSubjectKey);
        $body    = $params->get($bodyKey, '') ?: Text::_($defaultBodyKey);

        // Build tokens.
        $user        = Factory::getUser($row->id);
        $name        = $user->get('name', $row->name ?? '');
        $username    = $user->get('username', $row->username ?? '');
        $email       = $user->get('email', $row->email);
        $siteUrl     = Uri::root();

        $optOutCode  = $row->optoutcode ?: ApplicationHelper::getHash(UserHelper::genRandomPassword());
        $optOutUrl   = $siteUrl . 'index.php?option=com_userreminder&task=optoutnow&uid=' . $optOutCode;
        $passwordReset = $siteUrl . ($params->get('passwordReset', 'index.php?option=com_users&view=reset'));

        $tokens = [
            '[NAME]'           => $name,
            '[SITE_NAME]'      => $sitename,
            '[SITE_URL]'       => $siteUrl,
            '[USERNAME]'       => $username,
            '[PASSWORD_RESET]' => $passwordReset,
            '[OPTOUT]'         => $optOutUrl,
            '[ACTIVATE_URL]'   => ActivationUrlHelper::get($row, $params),
        ];

        $subject = strtr($subject, $tokens);
        $body    = strtr($body, $tokens);

        // BCC for admins (optional).
        $bcc  = null;
        if ((int) $params->get('enabledBccToAdmin', 1) === 0) {
            $bcc = $params->get('bccEmailAddress', '');
        }

        try {
            /** @var MailerFactoryInterface $mailerFactory */
            $mailerFactory = Factory::getContainer()->get(MailerFactoryInterface::class);
            $mailer        = $mailerFactory->createMailer();

            $mailer->setSender($mailfrom, $fromname);
            $mailer->setSubject($subject);
            $mailer->setBody($body);
            $mailer->addRecipient($email);

            if (!empty($bcc)) {
                $mailer->addBcc($bcc);
            }

            $mailer->send();

            return true;
        } catch (\Throwable) {
            return false;
        }
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

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__userreminder'))
            ->set($this->db->quoteName('datesent') . ' = NOW()')
            ->set($this->db->quoteName('remindernumber') . ' = remindernumber + 1')
            ->set($this->db->quoteName('type') . ' = ' . $type)
            ->set($this->db->quoteName('optoutcode') . ' = ' . $this->db->quote($optOutCode))
            ->where($this->db->quoteName('userid') . ' = ' . $userId);

        $this->db->setQuery($query);
        if (!$this->db->execute() || $this->db->getAffectedRows() === 0) {
            $insert = $this->db->getQuery(true)
                ->insert($this->db->quoteName('#__userreminder'))
                ->columns([
                    $this->db->quoteName('userid'),
                    $this->db->quoteName('datesent'),
                    $this->db->quoteName('remindernumber'),
                    $this->db->quoteName('type'),
                    $this->db->quoteName('optoutcode'),
                ])
                ->values(implode(',', [$userId, 'NOW()', 1, (int) $type, $this->db->quote($optOutCode)]));

            $this->db->setQuery($insert);
            $this->db->execute();
        }
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
        $description = match ($type) {
            self::TYPE_NOT_ACTIVATED => Text::_('USERREMINDER_ACTIVATE_REMINDER_SENT'),
            self::TYPE_NEVER_LOGGED  => Text::_('USERREMINDER_LOGIN_REMINDER_SENT'),
            default                  => Text::_('USERREMINDER_USER_REMINDER_SENT'),
        };

        $query = $this->db->getQuery(true)
            ->insert($this->db->quoteName('#__userreminder_log'))
            ->columns([
                $this->db->quoteName('userId'),
                $this->db->quoteName('username'),
                $this->db->quoteName('description'),
                $this->db->quoteName('date'),
            ])
            ->values(implode(',', [
                (int) $row->id,
                $this->db->quote($row->username ?? ''),
                $this->db->quote('Reminders Run: ' . $description),
                $this->db->quote(Factory::getDate()->toSql()),
            ]));

        $this->db->setQuery($query);
        $this->db->execute();
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
        $delete = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__userreminder'))
            ->where('userid IN (SELECT id FROM ' . $this->db->quoteName('#__users')
                . ' WHERE lastvisitDate IS NOT NULL AND block = 0)');

        $this->db->setQuery($delete);
        $this->db->execute();
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