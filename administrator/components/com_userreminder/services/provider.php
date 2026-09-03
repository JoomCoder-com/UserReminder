<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_userreminder
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Categories\CategoryFactoryInterface;
use Joomla\CMS\Component\Router\RouterFactoryInterface;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\CategoryFactory;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\Extension\Service\Provider\RouterFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use JoomCoder\Component\UserReminder\Administrator\Extension\UserReminderComponent;
use JoomCoder\Component\UserReminder\Administrator\Service\SendService;

/**
 * The component service provider.
 *
 * Wires up the MVC factory, the dispatcher factory, the SendService business
 * object and the component class itself.
 *
 * @since  4.0.0
 */
return new class implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->registerServiceProvider(new CategoryFactory('JoomCoder\\Component\\UserReminder'));
        $container->registerServiceProvider(new MVCFactory('JoomCoder\\Component\\UserReminder'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('JoomCoder\\Component\\UserReminder'));
        $container->registerServiceProvider(new RouterFactory('JoomCoder\\Component\\UserReminder'));

        $container->set(
            SendService::class,
            static function (Container $container) {
                return new SendService($container->get(\Joomla\Database\DatabaseInterface::class));
            }
        );

        $container->set(
            ComponentInterface::class,
            static function (Container $container) {
                $component = new UserReminderComponent(
                    $container->get(ComponentDispatcherFactoryInterface::class)
                );
                $component->setMVCFactory($container->get(MVCFactoryInterface::class));
                $component->setCategoryFactory($container->get(CategoryFactoryInterface::class));
                $component->setRouterFactory($container->get(RouterFactoryInterface::class));

                return $component;
            }
        );
    }
};