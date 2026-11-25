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
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/**
 * Resolves the site's public base URL for links in reminder emails, or null
 * when none can be trusted.
 *
 * Reminders are often sent from the command line (a server cron running
 * `cli/joomla.php scheduler:run`). There Joomla has no request, and Uri::root()
 * returns a placeholder such as "https://joomla.invalid/set/by/console/application/"
 * (Joomla 5/6) or "https://joomla.invalidcli/" (Joomla 4). Links built from it
 * are dead. So the base URL comes from, in this order:
 *
 *   1. the current web request (administrator page, lazy scheduler),
 *   2. the live_site setting of Global Configuration,
 *   3. the address remembered from the last administrator page view
 *      (remember(), stored in #__extensions.custom_data of com_userreminder).
 *
 * No URL is ever invented: with none of them the send engine refuses to send
 * and logs one error per run. A loopback address (localhost) is accepted but
 * flagged: see isLoopback().
 *
 * @since  6.0.0
 */
final class SiteUrl
{
    /**
     * Remembered root, read once per request.
     *
     * @var  string|null|false  false = not read yet.
     *
     * @since  6.1.0
     */
    private static string|null|false $remembered = false;

    /**
     * Absolute site root URL ending with a slash, or null when no trusted
     * base URL can be determined.
     *
     * @return  string|null
     *
     * @since   6.0.0
     */
    public static function root(): ?string
    {
        try {
            $root = self::normalize(Uri::root());
        } catch (\Throwable) {
            $root = null;
        }

        if ($root !== null) {
            return $root;
        }

        try {
            $live = trim((string) Factory::getApplication()->get('live_site', ''), " \t/");
        } catch (\Throwable) {
            $live = '';
        }

        if ($live !== '' && ($root = self::normalize($live . '/')) !== null) {
            return $root;
        }

        return self::remembered();
    }

    /**
     * Absolute URL of a site page: the SEF route when the site router can
     * build one, the plain non-SEF URL otherwise. Always based on root(), never
     * on the host the router thinks it runs on (a placeholder on the CLI).
     *
     * @param   string  $root  Site root from root().
     * @param   string  $path  Non-SEF path, e.g. "index.php?option=com_users&view=reset".
     *
     * @return  string
     *
     * @since  6.1.0
     */
    public static function route(string $root, string $path): string
    {
        try {
            $sef  = Route::link('site', $path, false, Route::TLS_IGNORE, false);
            $base = rtrim(Uri::root(true), '/');

            // Route::link() prefixes the router's own base path (a subfolder, or the
            // console placeholder path on the CLI): strip it, root() carries the real one.
            if ($base !== '' && str_starts_with($sef, $base . '/')) {
                $sef = substr($sef, \strlen($base));
            }

            $sef = ltrim($sef, '/');

            if ($sef !== '' && !preg_match('#^[a-z][a-z0-9+.-]*://#i', $sef)) {
                return $root . $sef;
            }
        } catch (\Throwable) {
            // No site router available: use the plain URL.
        }

        return $root . ltrim($path, '/');
    }

    /**
     * Remember the site root of the current web request, so command-line runs
     * without live_site can still build links. Called on administrator page
     * views; writes only when the address changed.
     *
     * @return  void
     *
     * @since  6.1.0
     */
    public static function remember(): void
    {
        try {
            $app = Factory::getApplication();

            if (!$app->isClient('administrator') && !$app->isClient('site')) {
                return;
            }

            $root = self::normalize(Uri::root());

            if ($root === null || $root === self::remembered()) {
                return;
            }

            $db = Factory::getDbo();
            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__extensions'))
                    ->set($db->quoteName('custom_data') . ' = ' . $db->quote(json_encode(['site_root' => $root])))
                    ->where($db->quoteName('element') . ' = ' . $db->quote('com_userreminder'))
                    ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
            )->execute();

            self::$remembered = $root;
        } catch (\Throwable) {
            // Best effort: root() still has live_site.
        }
    }

    /**
     * Does a resolved URL point at a loopback host?
     *
     * @param   string|null  $url
     *
     * @return  bool
     *
     * @since   6.1.0
     */
    public static function isLoopback(?string $url): bool
    {
        if ($url === null || $url === '') {
            return false;
        }

        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));

        return $host !== ''
            && ($host === 'localhost'
                || $host === '127.0.0.1'
                || str_ends_with($host, '.localhost'));
    }

    /**
     * The address saved by remember(), or null.
     *
     * @return  string|null
     *
     * @since  6.1.0
     */
    private static function remembered(): ?string
    {
        if (self::$remembered !== false) {
            return self::$remembered;
        }

        self::$remembered = null;

        try {
            $db   = Factory::getDbo();
            $data = (string) $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName('custom_data'))
                    ->from($db->quoteName('#__extensions'))
                    ->where($db->quoteName('element') . ' = ' . $db->quote('com_userreminder'))
                    ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
            )->loadResult();

            $root = json_decode($data, true)['site_root'] ?? '';

            self::$remembered = \is_string($root) && $root !== '' ? self::normalize($root) : null;
        } catch (\Throwable) {
        }

        return self::$remembered;
    }

    /**
     * Normalise/validate a candidate base URL.
     *
     * A valid candidate is absolute with a scheme and a real host. Rejects
     * scheme-relative garbage and the ".invalid" placeholder hosts Joomla's
     * console application uses.
     *
     * @param   string  $url
     *
     * @return  string|null  Normalised URL ending with a slash, or null.
     *
     * @since   6.1.0
     */
    private static function normalize(string $url): ?string
    {
        $url = trim($url);

        if ($url === '' || str_starts_with($url, '/')) {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        // RFC 2606 reserves ".invalid"; Joomla's CLI uses joomla.invalid(cli).
        $host = strtolower($parts['host']);

        if (str_ends_with($host, '.invalid') || str_starts_with($host, 'joomla.invalid') || str_contains($url, '/set/by/console/')) {
            return null;
        }

        return rtrim($url, '/') . '/';
    }
}
