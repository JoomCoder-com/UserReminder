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

        $this->joomcoderUpdateSetup($parent, 'pkg_userreminder', 'userreminderupdate', ['com_userreminder']);

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

    /**
     * JoomCoder update setup, run after every install and update of the package.
     *
     * 1. Enables the package's update helper plugin (plg_installer_*update). It is new in this
     *    release, so a site updating from an older version gets it for the first time; without
     *    it joomcoder.com cannot tell which site downloads an update and refuses the download.
     * 2. Moves the old joomcoder.com store update site (option=com_mediastore) to the package.
     *    A site that came from a bare com_x / mod_x has that site on the old extension, with
     *    the customer's download key in it. It becomes the package's update site, on the new
     *    address and with the key kept, so the customer does not have to enter it again, and
     *    the old extension stops being offered the same update a second time. Joomla then
     *    finds the new address already in place (plg_extension_joomla::addUpdateSite()).
     *
     * @param   object    $parent          The installer adapter.
     * @param   string    $packageElement  pkg_x
     * @param   string    $pluginElement   element of the plg_installer_*update plugin
     * @param   string[]  $legacyElements  extensions the old store shipped on their own
     *
     * @return  void
     */
    private function joomcoderUpdateSetup($parent, $packageElement, $pluginElement, array $legacyElements)
    {
        try {
            $db = \Joomla\CMS\Factory::getContainer()->get('DatabaseDriver');
            $q  = static function ($v) use ($db) {
                return $db->quote($v);
            };
            $n  = static function ($v) use ($db) {
                return $db->quoteName($v);
            };

            $db->setQuery(
                'UPDATE ' . $n('#__extensions') . ' SET ' . $n('enabled') . ' = 1'
                . ' WHERE ' . $n('type') . ' = ' . $q('plugin') . ' AND ' . $n('folder') . ' = ' . $q('installer')
                . ' AND ' . $n('element') . ' = ' . $q($pluginElement)
            )->execute();

            $server   = $parent->getManifest()->updateservers->server ?? null;
            $location = $server !== null ? trim((string) $server) : '';
            $name     = $server !== null ? (string) $server['name'] : '';

            $packageId = (int) $db->setQuery(
                'SELECT ' . $n('extension_id') . ' FROM ' . $n('#__extensions')
                . ' WHERE ' . $n('type') . ' = ' . $q('package') . ' AND ' . $n('element') . ' = ' . $q($packageElement)
            )->loadResult();

            if ($packageId <= 0 || $location === '') {
                return;
            }

            $where = [$n('package_id') . ' = ' . $packageId];

            if ($legacyElements) {
                $where[] = $n('element') . ' IN (' . implode(',', array_map($q, $legacyElements)) . ')';
            }

            $childIds = array_map('intval', $db->setQuery(
                'SELECT ' . $n('extension_id') . ' FROM ' . $n('#__extensions')
                . ' WHERE ' . $n('extension_id') . ' <> ' . $packageId . ' AND (' . implode(' OR ', $where) . ')'
            )->loadColumn());

            $packageSite = $db->setQuery(
                'SELECT s.* FROM ' . $n('#__update_sites') . ' AS s INNER JOIN ' . $n('#__update_sites_extensions') . ' AS x'
                . ' ON x.update_site_id = s.update_site_id WHERE x.extension_id = ' . $packageId
            , 0, 1)->loadObject();

            $legacySites = $childIds ? $db->setQuery(
                'SELECT DISTINCT s.* FROM ' . $n('#__update_sites') . ' AS s INNER JOIN ' . $n('#__update_sites_extensions') . ' AS x'
                . ' ON x.update_site_id = s.update_site_id WHERE x.extension_id IN (' . implode(',', $childIds) . ')'
                . ' AND s.location LIKE ' . $q('%option=com_mediastore%')
            )->loadObjectList() : [];

            // The customer's download key, from whichever site has one. The old store used
            // Joomla's default "dlid=" prefix; this package declares "download_id=".
            $key = '';

            foreach (array_merge($packageSite ? [$packageSite] : [], $legacySites) as $site) {
                if (preg_match('/(?:^|&)(?:dlid|download_id)=([^&]+)/', (string) $site->extra_query, $m)) {
                    $key = 'download_id=' . $m[1];
                    break;
                }
            }

            if (!$packageSite && $legacySites) {
                $packageSite = array_shift($legacySites);
                $id          = (int) $packageSite->update_site_id;
                $db->setQuery('DELETE FROM ' . $n('#__update_sites_extensions') . ' WHERE update_site_id = ' . $id)->execute();
                $db->setQuery('INSERT INTO ' . $n('#__update_sites_extensions') . ' (update_site_id, extension_id) VALUES (' . $id . ', ' . $packageId . ')')->execute();
            }

            if ($packageSite) {
                $id = (int) $packageSite->update_site_id;
                $db->setQuery(
                    'UPDATE ' . $n('#__update_sites') . ' SET location = ' . $q($location) . ', enabled = 1'
                    . ($name !== '' ? ', name = ' . $q($name) : '')
                    . ($key !== '' ? ', extra_query = ' . $q($key) : '')
                    . ' WHERE update_site_id = ' . $id
                )->execute();
                $db->setQuery('DELETE FROM ' . $n('#__updates') . ' WHERE update_site_id = ' . $id)->execute();
            }

            foreach ($legacySites as $site) {
                $id = (int) $site->update_site_id;

                if ($packageSite && $id === (int) $packageSite->update_site_id) {
                    continue;
                }

                $db->setQuery('DELETE FROM ' . $n('#__updates') . ' WHERE update_site_id = ' . $id)->execute();
                $db->setQuery('DELETE FROM ' . $n('#__update_sites_extensions') . ' WHERE update_site_id = ' . $id)->execute();
                $db->setQuery('DELETE FROM ' . $n('#__update_sites') . ' WHERE update_site_id = ' . $id)->execute();
            }
        } catch (\Throwable $e) {
            \Joomla\CMS\Factory::getApplication()->enqueueMessage('Update setup: ' . $e->getMessage(), 'warning');
        }
    }
}
