<?php
/**
 * @package         Joomla.Plugin
 * @subpackage      Installer.userreminderupdate
 *
 * @copyright       (C) 2026 JoomCoder <https://www.joomcoder.com/>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomCoder\Plugin\Installer\UserReminderUpdate\Extension;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\EventInterface;
use Joomla\Event\SubscriberInterface;

defined('_JEXEC') or die;

/**
 * Identifies this site to the JoomCoder update server when Joomla downloads a User Reminder update.
 *
 * User Reminder is a paid product: joomcoder.com checks the download key AND the requesting domain
 * before it serves a package. Joomla's own updater sends no site identity, so without this
 * plugin an update is listed as available and then fails to download.
 *
 * It lives in the `installer` group because InstallerHelper::downloadPackage() imports exactly
 * that group right before it dispatches onInstallerBeforePackageDownload, so it loads only while
 * an update is being downloaded, never on an ordinary page request.
 */
final class UserReminderUpdate extends CMSPlugin implements SubscriberInterface
{
	/** The hosts the JoomCoder update server answers on. */
	private const UPDATE_HOSTS = ['www.joomcoder.com', 'joomcoder.com'];

	/**
	 * @return  array<string, string>
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onInstallerBeforePackageDownload' => 'onInstallerBeforePackageDownload',
		];
	}

	/**
	 * Append the site host to package download URLs on our update server.
	 *
	 * Only the host is sent, never a full URL: most web application firewalls block a request
	 * variable that contains a URL scheme.
	 *
	 * Typed as EventInterface, not BeforePackageDownloadEvent: that class does not exist before
	 * Joomla 5, and Joomla 4 dispatches a plain Event with positional [&$url, &$headers]. A
	 * concrete type here would break every extension's download on Joomla 4.
	 *
	 * @param   EventInterface  $event  BeforePackageDownloadEvent on Joomla 5+, a legacy Event on 4.
	 *
	 * @return  void
	 */
	public function onInstallerBeforePackageDownload(EventInterface $event): void
	{
		$url = self::readUrl($event);

		if ($url === '' || strpos($url, 'task=download.download') === false) {
			return;
		}

		// Our update server only: no other extension's download learns which site asks.
		if (!\in_array(strtolower((string) parse_url($url, PHP_URL_HOST)), self::UPDATE_HOSTS, true)) {
			return;
		}

		// Several JoomCoder products may be installed; the first plugin to run decorates the URL.
		if (strpos($url, 'site_url=') !== false) {
			return;
		}

		$host = self::siteHost();

		if ($host === '') {
			return;
		}

		$separator = (strpos($url, '?') !== false) ? '&' : '?';
		self::writeUrl($event, $url . $separator . 'site_url=' . urlencode($host));
	}

	/**
	 * Host of this site. Uri::root() needs a web request; an update run from the Joomla CLI has
	 * none, so fall back to the live_site setting, and send nothing if that is empty too.
	 */
	private static function siteHost(): string
	{
		try {
			$host = (string) Uri::getInstance(Uri::root())->getHost();
		} catch (\Throwable $e) {
			$host = '';
		}

		if ($host === '') {
			try {
				$live = (string) \Joomla\CMS\Factory::getApplication()->get('live_site', '');
				$host = $live !== '' ? (string) parse_url($live, PHP_URL_HOST) : '';
			} catch (\Throwable $e) {
				$host = '';
			}
		}

		return $host;
	}

	private static function readUrl(EventInterface $event): string
	{
		if (method_exists($event, 'getUrl')) {
			return (string) $event->getUrl();
		}

		// Joomla 4: positional arguments, url first.
		return (string) ($event->getArgument('url') ?? $event->getArgument(0) ?? '');
	}

	private static function writeUrl(EventInterface $event, string $url): void
	{
		if (method_exists($event, 'updateUrl')) {
			$event->updateUrl($url);

			return;
		}

		// Joomla 4: element 0 is a reference to the caller's own $url.
		$event->setArgument($event->getArgument('url') !== null ? 'url' : 0, $url);
	}
}
