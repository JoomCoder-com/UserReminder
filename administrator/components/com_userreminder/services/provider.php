<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_userreminder
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use JoomCoder\Component\UserReminder\Administrator\Extension\UserReminderComponent;

/**
 * The component service provider (administrator half).
 *
 * Wires the MVC factory and the component class. Business services (the send
 * engine) are created by UserReminderComponent::getSendService() — not
 * registered here: component child containers are unreachable through
 * Factory::getContainer(), so such registrations never resolved anything.
 *
 * @since  4.0.0
 */
return new class implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->registerServiceProvider(new MVCFactory('JoomCoder\\Component\\UserReminder'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('JoomCoder\\Component\\UserReminder'));

        $container->set(
            ComponentInterface::class,
            static function (Container $container) {
                $component = new UserReminderComponent(
                    $container->get(ComponentDispatcherFactoryInterface::class)
                );
                $component->setMVCFactory($container->get(MVCFactoryInterface::class));

                return $component;
            }
        );
    }
};
