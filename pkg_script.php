<?php
/**
 * Package installer script.
 *
 * Enforces the Joomla floor (no J3), and since 4.2.0 uninstalls the legacy
 * page-hit system plugin (scheduling moved to plg_task_userreminder).
 *
 * @copyright  Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Installer\InstallerScript;

/**
 * pkg_userreminderInstallerScript
 *
 * @since  4.0.0
 */
class pkg_userreminderInstallerScript extends InstallerScript
{
    /**
     * Joomla floor check.
     *
     * @param   string                $type     install / update / discover_install
     * @param   \Joomla\CMS\Installer\Installer  $parent
     *
     * @return  bool
     *
     * @since   4.0.0
     */
    public function preflight($type, $parent): bool
    {
        if ($type === 'uninstall') {
            return true;
        }

        if (version_compare(JVERSION, '4.0.0', 'lt')) {
            Factory::getApplication()->enqueueMessage(
                'UserReminder 4 requires Joomla 4.0 or later. Please upgrade Joomla first.',
                'error'
            );
            return false;
        }

        return true;
    }

    /**
     * Uninstall the legacy system plugin after install or update.
     *
     * Since 4.2.0 scheduling lives in plg_task_userreminder (Scheduled Tasks).
     * The system plugin is a no-op stub since 4.2.0, so a failed uninstall here
     * can never cause double-sending.
     *
     * @param   string                $type
     * @param   \Joomla\CMS\Installer\Installer  $parent
     *
     * @return  void
     *
     * @since   4.2.0
     */
    public function postflight($type, $parent): void
    {
        if ($type === 'uninstall') {
            return;
        }

        $db   = Factory::getDbo();
        $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName('extension_id'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('userreminder'))
        );

        try {
            $extensionId = (int) $db->loadResult();
        } catch (\Throwable) {
            return;
        }

        if ($extensionId > 0) {
            try {
                Installer::getInstance()->uninstall('plugin', $extensionId, 0);
            } catch (\Throwable) {
                // The stub is inert — safe to leave behind.
            }
        }
    }
}
