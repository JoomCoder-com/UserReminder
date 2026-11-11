<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_userreminder
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomCoder\Component\UserReminder\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\Registry\Registry;

/**
 * Builds the per-user activation URL used in reminder emails.
 *
 * Supports Community Builder as well as native Joomla activation.
 *
 * @since  4.0.0
 */
final class ActivationUrlHelper
{
    /**
     * Return the URL the user must visit to (re)activate their account.
     *
     * @param   object       $row      User record (must expose ->id and ->activation).
     * @param   string|null  $siteUrl  The resolved site root from SiteUrl::root()
     *                               (null triggers the same resolution logic).
     * @param   mixed        $params   Optional params registry (ignored; kept for BC).
     *
     * @return  string
     *
     * @since  4.0.0
     */
    public static function get(object $row, ?string $siteUrl = null, $params = null): string
    {
        if ($params === null || $params instanceof Registry === false) {
            $params = ComponentHelper::getParams('com_userreminder');
        }

        if ($params instanceof Registry === false) {
            $params = ComponentHelper::getParams('com_userreminder');
        }

        $siteUrl ??= SiteUrl::root() ?? '';

        $app     = Factory::getApplication();
        $db      = Factory::getDbo();
        $tlsMode = (int) $app->get('force_ssl', 0) === 2 ? Route::TLS_FORCE : Route::TLS_IGNORE;

        if ((int) $params->get('useCBActivation', 0) === 1) {
            $cbUrl = trim((string) $params->get('activateURL', ''));

            try {
                $query = $db->getQuery(true)
                    ->select($db->quoteName('cbactivation'))
                    ->from($db->quoteName('#__comprofiler'))
                    ->where($db->quoteName('user_id') . ' = ' . (int) $row->id);
                $db->setQuery($query);

                $code = (string) $db->loadResult();
            } catch (\Throwable) {
                $code = '';
            }

            return $siteUrl . $cbUrl . $code;
        }

        $base = (string) $params->get('activateURL', 'index.php?option=com_users&task=registration.activate&token=');

        if ($base === '') {
            $base = 'index.php?option=com_users&task=registration.activate&token=';
        }

        // Route needs a request context (live_site on the CLI); fall back to a
        // plain SiteUrl-built link when it is unavailable (cron without
        // --live-site).
        try {
            return Route::link('site', $base . $row->activation, false, $tlsMode, true);
        } catch (\Throwable) {
            return $siteUrl . $base . $row->activation;
        }
    }
}
