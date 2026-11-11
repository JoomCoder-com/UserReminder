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

use Joomla\CMS\Factory;

/**
 * MailDebug — dry-run/session debugging support for the send engine.
 *
 * When the component's "Developer debug mode" option is on, ReminderOptions
 * reports debug() = true and the send engine switches to a simulation: no
 * email leaves the server (MailTemplate::send() is never called) and no
 * reminder state is written. Everything the run WOULD have done is appended
 * to <log_path>/userreminder-mail.log instead — recipients, template and all
 * merge-tag values (which contain tokenised links), which is why this log is
 * strictly opt-in.
 *
 * The mailer itself is created by MailerFactoryInterface (System → Mail
 * settings, including any third-party mailer plugins), not hand-built.
 *
 * @since  6.1.0
 */
final class MailDebug
{
    /**
     * Is dry-run/debug mode enabled?
     *
     * @return  bool
     *
     * @since  6.1.0
     */
    public static function enabled(): bool
    {
        return ReminderOptions::fromComponent()->debug();
    }

    /**
     * Append a dry-run record to <log_path>/userreminder-mail.log.
     *
     * No-op when debug mode is off — never let logging break a send.
     *
     * @param   string  $message  One human-readable line (should not end in \n).
     *
     * @return  void
     *
     * @since  6.1.0
     */
    public static function log(string $message): void
    {
        if (!self::enabled()) {
            return;
        }

        try {
            $file = self::file();

            @file_put_contents($file, '[' . ReminderOptions::utcNow() . '] ' . $message . "\n", FILE_APPEND | LOCK_EX);
        } catch (\Throwable) {
            // Never let logging break a send.
        }
    }

    /**
     * Absolute path of the debug log file.
     *
     * @return  string
     *
     * @since  6.1.0
     */
    public static function file(): string
    {
        try {
            $logPath = Factory::getApplication()->get('log_path', JPATH_ADMINISTRATOR . '/logs');
        } catch (\Throwable) {
            $logPath = JPATH_ADMINISTRATOR . '/logs';
        }

        return rtrim((string) $logPath, '/\\') . '/userreminder-mail.log';
    }
}
