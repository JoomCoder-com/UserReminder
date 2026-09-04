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
use Joomla\CMS\Mail\Mail;
use Joomla\CMS\Mail\MailHelper;
use Joomla\Registry\Registry;

/**
 * MailCapture — a Mail that logs the fully rendered email to the site log
 * folder (userreminder-mail.log) before actually sending it.
 *
 * Lets you verify the exact subject/body/recipients of every reminder email
 * without relying on an SMTP catcher such as Papercut.
 *
 * @since  4.2.0
 */
final class MailCapture extends Mail
{
    /**
     * Build a mailer preconfigured from the site configuration
     * (same defaults as MailerFactory::createMailer()).
     *
     * @param   ?Registry  $settings  Optional settings override.
     *
     * @return  self
     *
     * @since   4.2.0
     */
    public static function createMailer(?Registry $settings = null): self
    {
        $config = new Registry(Factory::getContainer()->get('config'));

        if ($settings) {
            $config->merge($settings);
        }

        $mailer = new self((bool) $config->get('throw_exceptions', true));

        $smtpauth   = $config->get('smtpauth') == 0 ? null : 1;
        $smtpuser   = $config->get('smtpuser');
        $smtppass   = $config->get('smtppass');
        $smtphost   = $config->get('smtphost');
        $smtpsecure = $config->get('smtpsecure');
        $smtpport   = $config->get('smtpport');
        $mailfrom   = $config->get('mailfrom');
        $fromname   = $config->get('fromname');
        $mailType   = $config->get('mailer');

        $mailfrom = MailHelper::cleanLine($mailfrom);

        if (MailHelper::isEmailAddress($mailfrom)) {
            try {
                $mailer->setFrom($mailfrom, MailHelper::cleanLine($fromname), false);
            } catch (\Exception) {
                // Sender stays unset; Mail::send() will fail loudly like usual.
            }
        }

        switch ($mailType) {
            case 'smtp':
                $mailer->useSmtp($smtpauth, $smtphost, $smtpuser, $smtppass, $smtpsecure, $smtpport);
                break;

            case 'sendmail':
                $mailer->isSendmail();
                break;

            default:
                $mailer->isMail();
        }

        return $mailer;
    }

    /**
     * Dump the rendered message to the log, then send for real.
     *
     * @return  bool
     *
     * @since   4.2.0
     */
    public function send()
    {
        $this->captureToLog();

        return parent::send();
    }

    /**
     * Append a human readable dump of the rendered email to
     * <log_path>/userreminder-mail.log.
     *
     * @return  void
     *
     * @since   4.2.0
     */
    private function captureToLog(): void
    {
        try {
            $app = Factory::getApplication();
            $log = rtrim($app->get('log_path', JPATH_ADMINISTRATOR . '/logs'), '/\\')
                . '/userreminder-mail.log';

            $addr = static fn(array $list) => $list === []
                ? '-'
                : implode('; ', array_map(
                    static fn($a) => $a[1] !== '' ? $a[1] . ' <' . $a[0] . '>' : $a[0],
                    $list
                ));

            $lines = [
                '================ ' . Factory::getDate()->toSql() . ' ================',
                'TO:     ' . $addr($this->getToAddresses()),
                'CC:     ' . $addr($this->getCcAddresses()),
                'BCC:    ' . $addr($this->getBccAddresses()),
                'FROM:   ' . $this->FromName . ' <' . $this->From . '>',
                'REPLY:  ' . ($this->ReplyTo ? $addr(array_map(
                    static fn($r) => [$r[0], $r[1] ?? ''],
                    array_values($this->ReplyTo)
                )) : '-'),
                'SUBJECT: ' . $this->Subject,
                'TYPE:    ' . $this->ContentType . ' / charset ' . $this->CharSet,
                'ATTACH:  ' . count($this->getAttachments() ?? []),
            ];

            $lines[] = '--- BODY (primary) ---';
            $lines[] = $this->Body;
            $lines[] = '--- ALT BODY (plain) ---';
            $lines[] = $this->AltBody ?: '-';
            $lines[] = '';

            @file_put_contents($log, implode("\n", $lines) . "\n", FILE_APPEND | LOCK_EX);
        } catch (\Throwable) {
            // Never let logging break an actual send.
        }
    }
}
