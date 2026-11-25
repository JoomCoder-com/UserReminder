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

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\Registry\Registry;

/**
 * ReminderOptions — typed accessor for the component's parameters.
 *
 * Single source of truth for option defaults and clamping. The send engine,
 * the list models and the dashboard all read options through this class, so a
 * change to a field in config.xml only has to be reflected here.
 *
 * @since  6.1.0
 */
final class ReminderOptions
{
    /**
     * The raw component parameters.
     *
     * @var  Registry
     *
     * @since  6.1.0
     */
    private readonly Registry $params;

    /**
     * @param   Registry  $params  Component parameters.
     *
     * @since  6.1.0
     */
    public function __construct(Registry $params)
    {
        $this->params = $params;
    }

    /**
     * Options for the current com_userreminder configuration.
     *
     * @return  self
     *
     * @since  6.1.0
     */
    public static function fromComponent(): self
    {
        return new self(ComponentHelper::getParams('com_userreminder'));
    }

    /**
     * Unfiltered access to the underlying parameters (escape hatch; prefer void).
     *
     * @return  Registry
     *
     * @since  6.1.0
     */
    public function raw(): Registry
    {
        return $this->params;
    }

    /**
     * Days between reminders (and the minimum account age for the first
     * reminder when "send first reminder immediately" is off).
     *
     * @return  int  >= 1
     *
     * @since  6.1.0
     */
    public function cooldownDays(): int
    {
        return max(1, (int) $this->params->get('numberOfDays', 1));
    }

    /**
     * Reminders a user receives before end-of-cycle actions apply.
     *
     * @return  int  >= 1
     *
     * @since  6.1.0
     */
    public function maxReminders(): int
    {
        return max(1, (int) $this->params->get('numberOfReminders', 1));
    }

    /**
     * Send the very first reminder immediately (bypasses the account-age gate)?
     *
     * @return  bool
     *
     * @since  6.1.0
     */
    public function firstImmediate(): bool
    {
        return (int) $this->params->get('enabledSendImed', 0) === 1;
    }

    /**
     * How many candidates to load from the DB per query/pipeline pass.
     *
     * @return  int  >= 1
     *
     * @since  6.1.0
     */
    public function batchSize(): int
    {
        return max(1, (int) $this->params->get('number_email', 50));
    }

    /**
     * Hard cap on real emails per run (0 = unlimited).
     *
     * @return  int  >= 0
     *
     * @since  6.1.0
     */
    public function maxPerRun(): int
    {
        return max(0, (int) $this->params->get('maxemailstosend', 20));
    }

    /**
     * Days of inactivity before a registered-and-logged-in user qualifies for
     * an inactive-user reminder.
     *
     * @return  int  >= 1
     *
     * @since  6.1.0
     */
    public function inactivityDays(): int
    {
        return max(1, (int) $this->params->get('numberOfDaysExistingUser', 180));
    }

    /**
     * Is this reminder type enabled?
     *
     * Each type has its own switch in Options (all off on new installs).
     *
     * @param   int  $type  One of the SendService::TYPE_* constants.
     *
     * @return  bool
     *
     * @since  6.1.0
     */
    public function typeEnabled(int $type): bool
    {
        // Inactive-user reminders are opt-in like the other two (6.0.0 rebuild, user decision
        // 2026-11-23): before, they had no switch and started right after install. Sites updated
        // from a version without the switch get it saved as on (script.php), so they keep sending.
        $key = match ($type) {
            SendService::TYPE_NOT_ACTIVATED => 'enableActivateReminder',
            SendService::TYPE_INACTIVE_USER => 'enableInactiveReminder',
            default                         => 'enableLoginReminder',
        };

        return (int) $this->params->get($key, 0) === 1;
    }

    /**
     * End-of-cycle action for a reminder type: delete the account?
     *
     * @param   int  $type  One of the SendService::TYPE_* constants.
     *
     * @return  bool
     *
     * @since  6.1.0
     */
    public function deleteAtEndOfCycle(int $type): bool
    {
        $key = match ($type) {
            SendService::TYPE_NOT_ACTIVATED => 'enableDeleteUsers',
            SendService::TYPE_NEVER_LOGGED  => 'enableDeleteUsersLogin',
            default                          => 'enableDeleteExistingUsers',
        };

        return (int) $this->params->get($key, 0) === 1;
    }

    /**
     * Groups the user should be removed from when the inactive cycle ends
     * without auto-delete.
     *
     * @return  int[]
     *
     * @since  6.1.0
     */
    public function removeFromGroups(): array
    {
        $groups = (array) $this->params->get('removefromusergroups', []);

        return array_values(array_filter(array_map('intval', $groups)));
    }

    /**
     * BCC every reminder to this address (when enabled and valid)?
     *
     * @return  string|null  Address or null when disabled/empty/invalid.
     *
     * @since  6.1.0
     */
    public function bccAddress(): ?string
    {
        if ((int) $this->params->get('enabledBccToAdmin', 0) !== 1) {
            return null;
        }

        $email = trim((string) $this->params->get('bccEmailAddress', ''));

        return \Joomla\CMS\Mail\MailHelper::isEmailAddress($email) ? $email : null;
    }

    /**
     * Absolute path segment for the password-reset URL tag
     * (falls back to the com_users reset view path when saved empty).
     *
     * @return  string  A relative path starting with "index.php" or "/".
     *
     * @since  6.1.0
     */
    public function passwordResetPath(): string
    {
        $path = trim(str_replace('\\', '/', (string) $this->params->get('passwordReset', '')));

        if ($path === '') {
            $path = 'index.php?option=com_users&view=reset';
        }

        return $path;
    }

    /**
     * Activation URL base override. Empty string = the com_users default.
     *
     * @return  string
     *
     * @since  6.1.0
     */
    public function activationBase(): string
    {
        return trim((string) $this->params->get('activateURL', ''));
    }

    /**
     * Use Community Builder's activation flow?
     *
     * @return  bool
     *
     * @since  6.1.0
     */
    public function cbActivation(): bool
    {
        return (int) $this->params->get('useCBActivation', 0) === 1;
    }

    /**
     * Developer debug mode (dry run: nothing is sent, nothing is stored).
     *
     * @return  bool
     *
     * @since  6.1.0
     */
    public function debug(): bool
    {
        return (int) $this->params->get('debugUserReminder', 0) === 1;
    }

    /**
     * The configured "send test email to" user id.
     *
     * @param   int  $fallback  Id used when no test user is configured
     *                          (typically the currently logged-in super user).
     *
     * @return  int  0 when neither config nor fallback is usable.
     *
     * @since  6.1.0
     */
    public function testUserId(int $fallback = 0): int
    {
        return max(0, (int) $this->params->get('test_user', $fallback));
    }

    /**
     * Timestamp "now" as a UTC datetime string matching what we store.
     *
     * Used everywhere the engine previously used MySQL NOW() — the two had
     * to agree on a time zone (see isWithinCoolDown). Joomla stores user
     * timestamps (registerDate/lastvisitDate) in UTC, so all comparisons
     * happen in UTC.
     *
     * @param   string|null  $modify  Optional Date::modify() expression.
     *
     * @return  string  e.g. "2026-11-10 05:20:00"
     *
     * @since  6.1.0
     */
    public static function utcNow(?string $modify = null): string
    {
        $date = Factory::getDate();

        if ($modify !== null) {
            $date->modify($modify);
        }

        return $date->toSql(true);
    }
}
