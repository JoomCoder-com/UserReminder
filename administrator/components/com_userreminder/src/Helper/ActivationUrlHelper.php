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
use Joomla\CMS\Uri\Uri;

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
     * @param   object  $row     User record (must expose ->id and ->activation).
     * @param   mixed   $params  Optional params registry (ignored; kept for BC).
     *
     * @return  string
     *
     * @since   4.0.0
     */
    public static function get(object $row, $params = null): string
    {
        if ($params === null) {
            $params = ComponentHelper::getParams('com_userreminder');
        } elseif (\is_array($params)) {
            $params = new \Joomla\Registry\Registry($params);
        } elseif (!$params instanceof \Joomla\Registry\Registry) {
            $params = ComponentHelper::getParams('com_userreminder');
        }
        $app     = Factory::getApplication();
        $db      = Factory::getDbo();
        $tlsMode = (int) $app->get('force_ssl', 0) === 2 ? Route::TLS_FORCE : Route::TLS_IGNORE;

        if ((int) $params->get('useCBActivation', 0) === 1) {
            $cbUrl = $params->get('activateURL', '');

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

            return Uri::root() . $cbUrl . $code;
        }

        $base = $params->get('activateURL', 'index.php?option=com_users&task=registration.activate&token=');

        return Route::link('site', $base . $row->activation, false, $tlsMode, true);
    }
}