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
 * Resolves the canonical site base URL — or null when none can be trusted.
 *
 * Uri::root() needs $_SERVER['HTTP_HOST'], which the Joomla CLI does not
 * populate (only the --live-site option does). Where the request context is
 * missing, the live_site configuration is used instead.
 *
 * No URL is ever *invented*: 6.0 silently fabricated "http://localhost"
 * links, which were worse than no link. When even live_site is unset the
 * helper returns null and the send engine refuses to send (and logs one
 * error per run); the dashboard flags the misconfiguration.
 *
 * A resolvable *loopback* URL (localhost / 127.0.0.1) is accepted — dev,
 * test and staging sites legitimately run on localhost — but it is flagged:
 * see isLoopback(), used by the send engine (one warning per run in the log)
 * and the dashboard health checks.
 *
 * @since  6.0.0
 */
final class SiteUrl
{
    /**
     * Absolute site root URL ending with a slash, or null when no trusted
     * base URL can be determined (request context missing and live_site unset).
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

        // No usable request context: fall back to live_site explicitly.
        try {
            $live = trim((string) Factory::getApplication()->get('live_site', ''), " \t/");
        } catch (\Throwable) {
            $live = '';
        }

        return $live !== '' ? self::normalize($live . '/') : null;
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
     * Normalise/validate a candidate base URL.
     *
     * A valid candidate has a scheme and a host and is absolute. Rejects
     * scheme-relative garbage ("//") and host-less fabrications that a
     * request-less Uri::root() can build.
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

        return rtrim($url, '/') . '/';
    }
}
