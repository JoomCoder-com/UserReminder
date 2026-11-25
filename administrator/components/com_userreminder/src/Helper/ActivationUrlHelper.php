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

use JoomCoder\Component\UserReminder\Administrator\Service\ReminderOptions;
use Joomla\CMS\Factory;

/**
 * Builds the per-user activation URL used in reminder emails.
 *
 * Supports Community Builder as well as native Joomla activation.
 *
 * @since  4.0.0
 */
final class ActivationUrlHelper
{
    private const DEFAULT_BASE = 'index.php?option=com_users&task=registration.activate&token=';

    /**
     * Return the URL the user must visit to (re)activate their account.
     *
     * @param   object           $row      User record (must expose ->id and ->activation).
     * @param   string|null      $siteUrl  The resolved site root from SiteUrl::root()
     *                                     (null triggers the same resolution logic).
     * @param   ReminderOptions  $options  Component options.
     *
     * @return  string
     *
     * @since  4.0.0
     */
    public static function get(object $row, ?string $siteUrl, ReminderOptions $options): string
    {
        $siteUrl ??= SiteUrl::root() ?? '';
        $base      = $options->activationBase();

        if ($options->cbActivation()) {
            $db = Factory::getDbo();

            try {
                $query = $db->getQuery(true)
                    ->select($db->quoteName('cbactivation'))
                    ->from($db->quoteName('#__comprofiler'))
                    ->where($db->quoteName('user_id') . ' = ' . (int) $row->id);

                $code = (string) $db->setQuery($query)->loadResult();
            } catch (\Throwable) {
                $code = '';
            }

            return $siteUrl . $base . $code;
        }

        $base = ltrim($base !== '' ? $base : self::DEFAULT_BASE, '/');

        return str_starts_with($base, 'index.php?')
            ? SiteUrl::route($siteUrl, $base . $row->activation)
            : $siteUrl . $base . $row->activation;
    }
}
