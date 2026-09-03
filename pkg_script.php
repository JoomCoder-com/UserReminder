<?php
/**
 * Package installer script.
 *
 * Enforces the Joomla floor (no J3), enables the system plugin on install,
 * and runs schema migrations cleanly.
 *
 * @copyright  Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
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
     * Enable the system plugin after a fresh install.
     *
     * @param   string                $type
     * @param   \Joomla\CMS\Installer\Installer  $parent
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public function postflight($type, $parent): void
    {
        if ($type === 'update' || $type === 'uninstall') {
            return;
        }

        $db = Factory::getDbo();
        $db->setQuery(
            $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('enabled') . ' = 1')
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('userreminder'))
        );
        $db->execute();
    }
}