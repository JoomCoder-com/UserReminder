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
use Joomla\Filesystem\File;
use Joomla\Registry\Registry;

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
                File::delete(self::MAIL_LAYOUT_TARGET);
            }
        } catch (\Throwable) {
            // Best effort.
        }

        $this->updateParams('com_mails', static function (Registry $params): bool {
            if ($params->get('mail_htmllayout') !== 'userreminder') {
                return false;
            }

            $params->remove('mail_htmllayout');

            return true;
        });

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

            File::copy(self::MAIL_LAYOUT_SOURCE, self::MAIL_LAYOUT_TARGET);
        } catch (\Throwable) {
            // Non-fatal — the stock container is used when the layout is missing.
        }
    }

    /**
     * Point com_mails at our container layout, unless the admin already chose
     * one. The layout falls back to the stock joomla.mail.mailtemplate
     * container for other emails, so this is safe to set globally.
     *
     * @return  void
     *
     * @since  4.2.1
     */
    private function selectMailLayout(): void
    {
        $this->setDefaultParam('com_mails', 'mail_htmllayout', 'userreminder');
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
        $this->setDefaultParam('com_userreminder', 'enableInactiveReminder', 1);
    }

    /**
     * Make com_mails send the HTML part of mail templates ("both" style),
     * unless the admin already chose a mail style.
     *
     * @return  void
     *
     * @since   4.2.0
     */
    private function enableHtmlMailStyle(): void
    {
        $this->setDefaultParam('com_mails', 'mail_style', 'both');
    }

    /**
     * Save a component option only when the site has no value for it yet.
     *
     * @param   string  $element  Component element (com_mails, com_userreminder).
     * @param   string  $key      Option name.
     * @param   mixed   $value    Value to save.
     *
     * @return  void
     *
     * @since   6.1.0
     */
    private function setDefaultParam(string $element, string $key, $value): void
    {
        $this->updateParams($element, static function (Registry $params) use ($key, $value): bool {
            if ($params->exists($key)) {
                return false;
            }

            $params->set($key, $value);

            return true;
        });
    }

    /**
     * Load a component's options, let $change edit them, and save them when it
     * returns true. Never blocks the install.
     *
     * @param   string    $element  Component element.
     * @param   callable  $change   fn(Registry $params): bool.
     *
     * @return  void
     *
     * @since   6.1.0
     */
    private function updateParams(string $element, callable $change): void
    {
        try {
            $db  = Factory::getDbo();
            $row = $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName(['extension_id', 'params']))
                    ->from($db->quoteName('#__extensions'))
                    ->where($db->quoteName('element') . ' = ' . $db->quote($element))
                    ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
            )->loadObject();

            if (!$row) {
                return;
            }

            $params = new Registry((string) $row->params);

            if (!$change($params)) {
                return;
            }

            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__extensions'))
                    ->set($db->quoteName('params') . ' = ' . $db->quote((string) $params))
                    ->where($db->quoteName('extension_id') . ' = ' . (int) $row->extension_id)
            )->execute();
        } catch (\Throwable) {
            // Non-fatal: the admin can set it under the component's Options.
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
     * Whether an index (or the PRIMARY key) exists on a table.
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
        return $this->inSchema('STATISTICS', $table, ['INDEX_NAME' => $index]);
    }

    /**
     * Whether a column exists on a table.
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
        return $this->inSchema('COLUMNS', $table, ['COLUMN_NAME' => $column]);
    }

    /**
     * Whether a table exists.
     *
     * @param   string  $table  Table name with the #__ placeholder.
     *
     * @return  bool
     *
     * @since  6.1.0
     */
    private function tableExists(string $table): bool
    {
        return $this->inSchema('TABLES', $table);
    }

    /**
     * Look a table (and optional column/index) up in information_schema for
     * the current database.
     *
     * @param   string  $view   information_schema view: TABLES, COLUMNS or STATISTICS.
     * @param   string  $table  Table name with the #__ placeholder.
     * @param   array   $match  Extra column => value conditions.
     *
     * @return  bool
     *
     * @since  6.1.0
     */
    private function inSchema(string $view, string $table, array $match = []): bool
    {
        $db = Factory::getDbo();

        try {
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('information_schema.' . $view))
                ->where($db->quoteName('TABLE_SCHEMA') . ' = DATABASE()')
                ->where($db->quoteName('TABLE_NAME') . ' = ' . $db->quote($db->replacePrefix($table)));

            foreach ($match as $column => $value) {
                $query->where($db->quoteName($column) . ' = ' . $db->quote($value));
            }

            return (int) $db->setQuery($query)->loadResult() > 0;
        } catch (\Throwable) {
            return false;
        }
    }
}
