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

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

/**
 * Resolves the site base URL safely in every execution context.
 *
 * Uri::root() needs $_SERVER['HTTP_HOST'], which the Joomla 4 CLI does not
 * populate (only the --live-site option does). A cron-run scheduler task that
 * calls Uri::root() without it used to fatal every send. This helper falls
 * back to the live_site configuration (or http://localhost) when the request
 * context is unavailable.
 *
 * @since  6.0.0
 */
final class SiteUrl
{
    /**
     * Absolute site root URL, always ending with a slash.
     *
     * @return  string
     *
     * @since   6.0.0
     */
    public static function root(): string
    {
        try {
            return Uri::root();
        } catch (\Throwable) {
            $live = trim((string) Factory::getApplication()->get('live_site', ''), " \t/");

            return ($live !== '' ? $live : 'http://localhost') . '/';
        }
    }
}
