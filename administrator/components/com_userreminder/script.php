<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_userreminder
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerScript;

/**
 * Installer script for com_userreminder (admin).
 *
 * On install/update: registers the default mail templates and installs the
 * custom HTML mail container layout. Upgrading sites simply switch to the new
 * mail-template system (no legacy email-param migration).
 *
 * Class name must match Joomla's expected `com_userreminderInstallerScript`.
 *
 * @since  4.2.0
 */
class com_userreminderInstallerScript extends InstallerScript
{
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

        if ($type === 'update') {
            // Index/PK surgery for the 6.0.0 rebuild. Done here (not in the
            // SQL file) because MySQL aborts on duplicate index names and the
            // 5.2.x schema carries a UNIQUE KEY that must become the PK.
            $this->migrateSchema6();

            // Inactive-user reminders became opt-in (default off). Sites that had them
            // always on keep sending: save the switch as on when it is not saved yet.
            $this->keepInactiveRemindersOn();
        }

        // 6.1.0 schema deltas on fresh installs and upgrades alike
        // (idempotent, guarded): the log table gains the structured
        // message-key column and the dead legacy scheduler table goes away.
        $this->migrateSchema61();

        // Register the default mail templates (idempotent; existing templates
        // win). Upgrading sites simply switch to the new mail-template system.
        $this->registerMailTemplates();

        // Our templates ship rich HTML bodies; com_mails defaults to plain-text
        // mail style, which would never send them. Switch to "both" (plain +
        // HTML) unless the admin already picked a style.
        $this->enableHtmlMailStyle();

        // Install the custom mail container layout and point com_mails at it.
        $this->installMailLayout();
        $this->selectMailLayout();

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
     * On update: save "enableInactiveReminder" = 1 when the site has no value for it yet.
     *
     * Before the switch existed, inactive-user reminders were always on; without this, an
     * update would silently stop them.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    private function keepInactiveRemindersOn(): void
    {
        try {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select($db->quoteName(['extension_id', 'params']))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('com_userreminder'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'));
            $row = $db->setQuery($query)->loadObject();

            if (!$row) {
                return;
            }

            $params = json_decode((string) $row->params, true) ?: [];

            if (\array_key_exists('enableInactiveReminder', $params)) {
                return;
            }

            $params['enableInactiveReminder'] = 1;

            $update = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('params') . ' = ' . $db->quote(json_encode($params)))
                ->where($db->quoteName('extension_id') . ' = ' . (int) $row->extension_id);
            $db->setQuery($update)->execute();
        } catch (\Throwable) {
            // Never block the update on this.
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

                if ($templateId === '' || $this->mailTemplateExists($templateId)) {
                    continue;
                }

                // Direct insert, as MailTemplate::createTemplate() did up to Joomla 6.1. From 6.2 it
                // saves through com_mails' Template model, which cannot boot in a CLI install
                // ("Could not parse the requested URI"): the templates were silently missing.
                // createTemplate() / getTemplate() are deprecated in 6.2 and go away in 8.0.
                $db  = Factory::getDbo();
                $row = (object) [
                    'template_id' => $templateId,
                    'extension'   => 'com_userreminder',
                    'language'    => '',
                    'subject'     => $subject,
                    'body'        => $body,
                    'htmlbody'    => $htmlbody,
                    'attachments' => '',
                    'params'      => json_encode(['tags' => $tags]),
                ];
                $db->insertObject('#__mail_templates', $row);
            } catch (\Throwable) {
                // Never block the install on a registration failure.
            }
        }
    }

    /**
     * Does the site already have this mail template (the default, language '' row)?
     *
     * @param   string  $templateId  Full template id (com_userreminder.userreminder.xxx).
     *
     * @return  bool
     *
     * @since   6.0.0
     */
    private function mailTemplateExists(string $templateId): bool
    {
        $db    = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__mail_templates'))
            ->where($db->quoteName('template_id') . ' = ' . $db->quote($templateId))
            ->where($db->quoteName('language') . ' = ' . $db->quote(''));

        return (int) $db->setQuery($query)->loadResult() > 0;
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
                'idx_ur_optoutcode' => '(`optoutcode`(32))',
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
     * 6.1.0 schema deltas, applied to fresh installs and upgrades alike.
     *
     * Both statements also exist in the shipped install SQL — the guards only
     * matter for upgrade paths. A plain SQL file is not enough: MariaDB
     * accepts `ADD COLUMN IF NOT EXISTS` but MySQL does not, so the column
     * addition is guarded through information_schema here (project portability
     * rule: schema surgery lives in script.php, update SQL files stay
     * MySQL/MariaDB-portable).
     *
     * @return  void
     *
     * @since  6.1.0
     */
    private function migrateSchema61(): void
    {
        $db = Factory::getDbo();

        // #__userreminder_log.message_key — the send engine stores the raw
        // language key next to the translated description so the log view can
        // re-translate old rows no matter which language was active at send
        // time.
        if (!$this->columnExists('#__userreminder_log', 'message_key')) {
            try {
                $db->setQuery(
                    'ALTER TABLE ' . $db->quoteName('#__userreminder_log')
                    . ' ADD COLUMN ' . $db->quoteName('message_key')
                    . ' VARCHAR(64) NOT NULL DEFAULT \'\' AFTER ' . $db->quoteName('description')
                )->execute();
            } catch (\Throwable) {
                // Keep the previous schema; the log view falls back to the
                // stored description when the column is absent.
            }
        }

        // Legacy 4.x scheduler table — superseded by the com_scheduler task
        // plugin in 4.2.0. The 6.0.0 migration drops it for 5.2.x upgraders;
        // this catches everything else (idempotent).
        if ($this->tableExists('#__userreminder_sch')) {
            try {
                $db->setQuery('DROP TABLE ' . $db->quoteName('#__userreminder_sch'))->execute();
            } catch (\Throwable) {
            }
        }
    }

    /**
     * Whether a column exists on a table in the current database, #_
     * substituted.
     *
     * @param   string  $table   Table name with the #__ placeholder.
     * @param   string  $column  Column name to look for.
     *
     * @return  bool
     *
     * @since  6.1.0
     */
    private function columnExists(string $table, string $column): bool
    {
        $db = Factory::getDbo();

        try {
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('information_schema.COLUMNS'))
                ->where($db->quoteName('TABLE_SCHEMA') . ' = DATABASE()')
                ->where($db->quoteName('TABLE_NAME') . ' = ' . $db->quote($db->replacePrefix($table)))
                ->where($db->quoteName('COLUMN_NAME') . ' = ' . $db->quote($column));

            $db->setQuery($query);

            return (int) $db->loadResult() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Whether a table exists in the current database, #__ substituted.
     *
     * @param   string  $table  Table name with the #__ placeholder.
     *
     * @return  bool
     *
     * @since  6.1.0
     */
    private function tableExists(string $table): bool
    {
        $db = Factory::getDbo();

        try {
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('information_schema.TABLES'))
                ->where($db->quoteName('TABLE_SCHEMA') . ' = DATABASE()')
                ->where($db->quoteName('TABLE_NAME') . ' = ' . $db->quote($db->replacePrefix($table)));

            $db->setQuery($query);

            return (int) $db->loadResult() > 0;
        } catch (\Throwable) {
            return false;
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
}
