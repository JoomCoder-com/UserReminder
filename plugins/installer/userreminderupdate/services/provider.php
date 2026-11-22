<?php
/**
 * @package         Joomla.Plugin
 * @subpackage      Installer.userreminderupdate
 *
 * @copyright       (C) 2026 JoomCoder <https://www.joomcoder.com/>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use JoomCoder\Plugin\Installer\UserReminderUpdate\Extension\UserReminderUpdate;

return new class () implements ServiceProviderInterface {
	public function register(Container $container): void
	{
		$container->set(
			PluginInterface::class,
			function (Container $container) {
				$config = (array) PluginHelper::getPlugin('installer', 'userreminderupdate');

				// Joomla 6 takes the config alone; 4 and 5 take the dispatcher first, by reference.
				if (version_compare(JVERSION, '6.0', '>=')) {
					$plugin = new UserReminderUpdate($config);
				} else {
					$dispatcher = $container->get(DispatcherInterface::class);
					$plugin     = new UserReminderUpdate($dispatcher, $config);
				}

				$plugin->setApplication(Factory::getApplication());

				return $plugin;
			}
		);
	}
};
