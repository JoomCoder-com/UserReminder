<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Task.userreminder
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use JoomCoder\Plugin\Task\UserReminder\Extension\UserReminder;

return new class () implements ServiceProviderInterface {
    /**
     * Registers the service provider with a DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     *
     * @since   4.2.0
     */
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $config = (array) PluginHelper::getPlugin('task', 'userreminder');

                // Joomla 4: CMSPlugin::__construct(&$subject, $config) wants the
                // dispatcher as the subject. Joomla 5+ takes only $config and the
                // dispatcher is attached through setDispatcher().
                if (version_compare(JVERSION, '5', '<')) {
                    $plugin = new UserReminder($container->get(DispatcherInterface::class), $config);
                } else {
                    $plugin = new UserReminder($config);
                    $plugin->setDispatcher($container->get(DispatcherInterface::class));
                }

                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};
