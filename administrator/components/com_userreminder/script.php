<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_userreminder
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerScript;
use Joomla\CMS\Mail\MailTemplate;

/**
 * Installer script for com_userreminder (admin).
 *
 * On update: migrates customized legacy email subject/body params into
 * Joomla mail templates (System → Mail Templates), once, non-destructively.
 *
 * Class name must match Joomla's expected `com_userreminderInstallerScript`.
 *
 * @since  4.2.0
 */
class com_userreminderInstallerScript extends InstallerScript
{
    /**
     * Legacy param defaults (pre-4.2 config.xml). A stored value equal to one
     * of these is considered "not customized" and is not migrated.
     *
     * @var  array
     *
     * @since  4.2.0
     */
    private const LEGACY_DEFAULTS = [
        'regactivationEmailSubject' => 'Please complete registration for [NAME] at [SITE_NAME]',
        'regLoginEmailSubject'      => 'You have successfully activated your account at [SITE_NAME]',
        'regLoginEmailSubjectVisited' => 'We have missed you at [SITE_NAME]',
        'regActivationEmailBodyHTML' => 'Hello [NAME],<br /><br />We have noticed that you have not completed registering at [SITE_NAME].<br /><br />Your account is created and must be activated before you can use it.<br /><br />To activate the account click on the following link or copy-paste it in your browser:<br />[ACTIVATE_URL]<br /><br />After activation you may login to [SITE_URL] using the following username and password:<br /><br />Username: [USERNAME]<br />Forgot your Password? : [PASSWORD_RESET]<br /><br />If you no longer wish to receive these reminders then unsubscribe here [OPTOUT]',
        'regLoginEmailBodyHTML'     => 'Hello [NAME],<br /><br />We have noticed that you have successfully registered you account at [SITE_NAME].<br /><br />However you have not logged in since activating your account.<br /><br />To access the website click on the following link or copy-paste it in your browser:<br />[SITE_URL]<br /><br />You may login using the following username and password:<br /><br />Username: [USERNAME]<br />Forgot your Password? : [PASSWORD_RESET]<br /><br />If you no longer wish to receive these reminders then unsubscribe here [OPTOUT]',
        'regExistingUserEmailBodyHTML' => 'Hello [NAME],<br /><br />We have noticed that you have not visited [SITE_NAME] for a while.<br /><br />If you no longer wish to receive these reminders then unsubscribe here [OPTOUT]',
    ];

    /**
     * Legacy param name → mail template id + tag list.
     *
     * @var  array
     *
     * @since  4.2.0
     */
    private const TEMPLATE_MAP = [
        'reminder_activation' => [
            'subjectParam' => 'regactivationEmailSubject',
            'bodyParam'    => 'regActivationEmailBodyHTML',
            'tags'         => ['NAME', 'SITENAME', 'SITELINK', 'USERNAME', 'PASSWORD_RESET_URL', 'OPTOUT_URL', 'ACTIVATE_URL'],
        ],
        'reminder_login' => [
            'subjectParam' => 'regloginemailsubject',
            'bodyParam'    => 'regLoginEmailBodyHTML',
            'tags'         => ['NAME', 'SITENAME', 'SITELINK', 'USERNAME', 'PASSWORD_RESET_URL', 'OPTOUT_URL'],
        ],
        'reminder_inactive' => [
            'subjectParam' => 'regLoginEmailSubjectVisited',
            'bodyParam'    => 'regExistingUserEmailBodyHTML',
            'tags'         => ['NAME', 'SITENAME', 'SITELINK', 'USERNAME', 'PASSWORD_RESET_URL', 'OPTOUT_URL'],
        ],
    ];

    /**
     * Source of the custom HTML mail container layout (shipped with the
     * component) and its target in the site-wide layout override folder.
     *
     * @var  string
     *
     * @since  4.2.1
     */
    private const MAIL_LAYOUT_SOURCE = JPATH_ADMINISTRATOR . '/components/com_userreminder/layouts/joomla/mail/userreminder.php';
    private const MAIL_LAYOUT_TARGET = JPATH_ROOT . '/layouts/joomla/mail/userreminder.php';

    /**
     * Run after install / update.
     *
     * @param   string  $type    Install type.
     * @param   object  $parent  Installer adapter.
     *
     * @return  bool
     *
     * @since   4.2.0
     */
    public function postflight($type, $parent): bool
    {
        if ($type === 'uninstall') {
            return true;
        }

        // MailTemplate::send() reads #__mail_templates — register our default
        // templates from the shipped mails/*.xml (idempotent).
        $this->registerMailTemplates();

        // Our templates ship rich HTML bodies; com_mails defaults to plain-text
        // mail style, which would never send them. Switch to "both" (plain +
        // HTML) unless the admin already picked a style.
        $this->enableHtmlMailStyle();

        // Install the custom mail container layout and point com_mails at it.
        $this->installMailLayout();
        $this->selectMailLayout();

        if ($type === 'update') {
            // Copy customized legacy email params into the mail templates, once.
            $this->migrateLegacyParams();

            // Index/PK surgery for the 6.0.0 rebuild. Done here (not in the
            // SQL file) because MySQL aborts on duplicate index names and the
            // 5.2.x schema carries a UNIQUE KEY that must become the PK.
            $this->migrateSchema6();
        }

        return true;
    }

    /**
     * Run on uninstall — remove the mail container layout and restore the
     * stock com_mails layout when we were the ones who changed it.
     *
     * @param   object  $parent  Installer adapter.
     *
     * @return  bool
     *
     * @since  4.2.1
     */
    public function uninstall($parent): bool
    {
        try {
            if (is_file(self::MAIL_LAYOUT_TARGET)) {
                \Joomla\Filesystem\File::delete(self::MAIL_LAYOUT_TARGET);
            }

            $db     = Factory::getDbo();
            $query  = $db->getQuery(true)
                ->select($db->quoteName(['extension_id', 'params']))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('com_mails'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'));
            $db->setQuery($query);

            if ($row = $db->loadObject()) {
                $params = new \Joomla\Registry\Registry((string) $row->params);

                if ($params->get('mail_htmllayout') === 'userreminder') {
                    $params->remove('mail_htmllayout');

                    $db->setQuery(
                        $db->getQuery(true)
                            ->update($db->quoteName('#__extensions'))
                            ->set($db->quoteName('params') . ' = ' . $db->quote((string) $params))
                            ->where($db->quoteName('extension_id') . ' = ' . (int) $row->extension_id)
                    );
                    $db->execute();
                }
            }
        } catch (\Throwable) {
            // Best effort.
        }

        return true;
    }

    /**
     * Copy the custom mail container layout into the site layout override
     * folder (JPATH_ROOT/layouts/joomla/mail/userreminder.php).
     *
     * @return  void
     *
     * @since  4.2.1
     */
    private function installMailLayout(): void
    {
        try {
            if (!is_file(self::MAIL_LAYOUT_SOURCE)) {
                return;
            }

            $dir = \dirname(self::MAIL_LAYOUT_TARGET);

            if (!is_dir($dir)) {
                \mkdir($dir, 0755, true);
            }

            \Joomla\Filesystem\File::copy(self::MAIL_LAYOUT_SOURCE, self::MAIL_LAYOUT_TARGET);
        } catch (\Throwable) {
            // Non-fatal — the stock container is used when the layout is missing.
        }
    }

    /**
     * Point com_mails at our container layout. The layout itself falls back to
     * the stock joomla.mail.mailtemplate container for non-User-Reminder
     * emails, so this is safe to set globally.
     *
     * Only applies when the admin has not already configured a layout.
     *
     * @return  void
     *
     * @since  4.2.1
     */
    private function selectMailLayout(): void
    {
        $db = Factory::getDbo();

        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['extension_id', 'params']))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('com_mails'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'));
            $db->setQuery($query);

            $row = $db->loadObject();

            if (!$row) {
                return;
            }

            $params = new \Joomla\Registry\Registry((string) $row->params);

            if ($params->exists('mail_htmllayout')) {
                return;
            }

            $params->set('mail_htmllayout', 'userreminder');

            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__extensions'))
                    ->set($db->quoteName('params') . ' = ' . $db->quote((string) $params))
                    ->where($db->quoteName('extension_id') . ' = ' . (int) $row->extension_id)
            );
            $db->execute();
        } catch (\Throwable) {
            // Non-fatal — System → Mail Templates → Options can set it manually.
        }
    }

    /**
     * Make com_mails send the HTML part of mail templates ("both" style).
     *
     * Only applies when the admin has not explicitly configured mail_style.
     *
     * @return  void
     *
     * @since   4.2.0
     */
    private function enableHtmlMailStyle(): void
    {
        $db = Factory::getDbo();

        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['extension_id', 'params']))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('com_mails'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'));
            $db->setQuery($query);

            $row = $db->loadObject();

            if (!$row) {
                return;
            }

            $params = new \Joomla\Registry\Registry((string) $row->params);

            if ($params->exists('mail_style')) {
                return;
            }

            $params->set('mail_style', 'both');

            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__extensions'))
                    ->set($db->quoteName('params') . ' = ' . $db->quote((string) $params))
                    ->where($db->quoteName('extension_id') . ' = ' . (int) $row->extension_id)
            );
            $db->execute();
        } catch (\Throwable) {
            // Non-fatal — admins can switch the style under System → Mail Templates → Options.
        }
    }

    /**
     * Register default mail template rows from the shipped mails/*.xml files.
     *
     * @return  void
     *
     * @since   4.2.0
     */
    private function registerMailTemplates(): void
    {
        $path = JPATH_ADMINISTRATOR . '/components/com_userreminder/mails';

        if (!is_dir($path)) {
            return;
        }

        foreach ((array) glob($path . '/*.xml') as $file) {
            try {
                $xml = simplexml_load_file($file);

                if ($xml === false) {
                    continue;
                }

                $mail = $xml->mail;

                if (!$mail) {
                    continue;
                }

                $templateId = (string) $mail->name;
                $subject    = (string) $mail->template->subject;
                $body       = (string) $mail->template->body;
                $htmlbody   = (string) $mail->template->htmlbody;
                $tags       = [];
                foreach ($mail->template->tags->tag as $tag) {
                    $tags[] = (string) $tag;
                }

                if ($templateId === '' || MailTemplate::getTemplate($templateId, '') !== null) {
                    continue;
                }

                MailTemplate::createTemplate($templateId, $subject, $body, $tags, $htmlbody);
            } catch (\Throwable) {
                // Never block the install on a registration failure.
            }
        }
    }

    /**
     * Bring the 5.2.x table indexes up to the 6.0.0 schema.
     *
     * Every statement is existence-checked against information_schema and
     * wrapped, so a site that cannot be migrated keeps working exactly as
     * before. Idempotent — no-ops on already-rebuilt schemas.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    private function migrateSchema6(): void
    {
        $db = Factory::getDbo();

        // Legacy 5.2.x UNIQUE KEY `userid` → PRIMARY KEY (`userid`).
        if ($this->indexExists('#__userreminder', 'userid')) {
            try {
                $db->setQuery('ALTER TABLE ' . $db->quoteName('#__userreminder') . ' DROP INDEX ' . $db->quoteName('userid'))->execute();
            } catch (\Throwable) {
            }
        }

        if (!$this->indexExists('#__userreminder', 'PRIMARY')) {
            try {
                $db->setQuery('ALTER TABLE ' . $db->quoteName('#__userreminder') . ' ADD PRIMARY KEY (' . $db->quoteName('userid') . ')')->execute();
            } catch (\Throwable) {
            }
        }

        $indexes = [
            '#__userreminder'    => [
                'idx_ur_sent_type' => '(`datesent`, `remindernumber`, `type`)',
            ],
            '#__userreminder_log' => [
                'idx_userreminder_log_userId_date' => '(`userId`, `date`)',
                'idx_ur_log_date'                  => '(`date`)',
                'idx_ur_log_date_id'               => '(`date`, `id`)',
            ],
        ];

        foreach ($indexes as $table => $defs) {
            foreach ($defs as $name => $columns) {
                if ($this->indexExists($table, $name)) {
                    continue;
                }

                try {
                    $db->setQuery(
                        'ALTER TABLE ' . $db->quoteName($table)
                        . ' ADD INDEX ' . $db->quoteName($name) . ' ' . $columns
                    )->execute();
                } catch (\Throwable) {
                    // Leave the site on the previous index state.
                }
            }
        }
    }

    /**
     * Whether an index (or the PRIMARY key) exists on a table in the current
     * database, # substituted.
     *
     * @param   string  $table  Table name with the #__ placeholder.
     * @param   string  $index  Index name to look for.
     *
     * @return  bool
     *
     * @since   6.0.0
     */
    private function indexExists(string $table, string $index): bool
    {
        $db = Factory::getDbo();

        try {
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('information_schema.STATISTICS'))
                ->where($db->quoteName('TABLE_SCHEMA') . ' = DATABASE()')
                ->where($db->quoteName('TABLE_NAME') . ' = ' . $db->quote($db->replacePrefix($table)))
                ->where($db->quoteName('INDEX_NAME') . ' = ' . $db->quote($index));

            $db->setQuery($query);

            return (int) $db->loadResult() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Copy customized legacy email params into the mail templates.
     *
     * @return  void
     *
     * @since   4.2.0
     */
    private function migrateLegacyParams(): void
    {
        $params = ComponentHelper::getParams('com_userreminder');

        foreach (self::TEMPLATE_MAP as $slug => $map) {
            $subject = (string) $params->get($map['subjectParam'], '');
            $body    = (string) $params->get($map['bodyParam'], '');

            if ($subject === '' && $body === '') {
                continue;
            }

            // Skip stock defaults — the mail templates already ship equivalent copy.
            $defaultSubject = self::LEGACY_DEFAULTS[$map['subjectParam']] ?? '';
            $defaultBody    = self::LEGACY_DEFAULTS[$map['bodyParam']] ?? '';

            if ($subject === $defaultSubject && ($body === $defaultBody || $body === '')) {
                continue;
            }

            $templateId = 'com_userreminder.userreminder.' . $slug;

            try {
                // Only migrate when the admin has not already edited the template.
                $existing = MailTemplate::getTemplate($templateId, '');

                if ($existing !== null && !empty($existing->htmlbody)) {
                    continue;
                }

                $htmlBody = str_replace(
                    ['[NAME]', '[SITE_NAME]', '[SITE_URL]', '[USERNAME]', '[PASSWORD_RESET]', '[OPTOUT]', '[ACTIVATE_URL]'],
                    ['{NAME}', '{SITENAME}', '{SITELINK}', '{USERNAME}', '{PASSWORD_RESET_URL}', '{OPTOUT_URL}', '{ACTIVATE_URL}'],
                    $body
                );

                if ($existing !== null) {
                    MailTemplate::updateTemplate($templateId, $subject, '', $map['tags'], $htmlBody);
                } else {
                    MailTemplate::createTemplate($templateId, $subject, '', $map['tags'], $htmlBody);
                }
            } catch (\Throwable) {
                // Never block the update on a migration failure.
            }
        }
    }
}
