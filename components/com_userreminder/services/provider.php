<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_userreminder
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Component\Router\RouterFactoryInterface;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\Extension\Service\Provider\RouterFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use JoomCoder\Component\UserReminder\Administrator\Extension\UserReminderComponent;

/**
 * The component service provider (site half).
 *
 * The package ships com_userreminder as one monolithic component, so the site
 * half shares the administrator component class. It wires the MVC factory,
 * the dispatcher factory and the site router (SEF opt-out links).
 *
 * NOTE: Joomla boots components from the ADMINISTRATOR half's provider for
 * both applications (ExtensionManagerTrait::bootComponent always resolves
 * under JPATH_ADMINISTRATOR). This file is kept complete and consistent with
 * admin/services/provider.php so nothing regresses if that ever changes.
 *
 * @since  6.1.0
 */
return new class implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->registerServiceProvider(new MVCFactory('JoomCoder\\Component\\UserReminder'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('JoomCoder\\Component\\UserReminder'));
        $container->registerServiceProvider(new RouterFactory('JoomCoder\\Component\\UserReminder'));

        $container->set(
            ComponentInterface::class,
            static function (Container $container) {
                $component = new UserReminderComponent(
                    $container->get(ComponentDispatcherFactoryInterface::class)
                );
                $component->setMVCFactory($container->get(MVCFactoryInterface::class));
                $component->setRouterFactory($container->get(RouterFactoryInterface::class));

                return $component;
            }
        );
    }
};
