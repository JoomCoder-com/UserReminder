<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_userreminder
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomCoder\Component\UserReminder\Administrator\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Categories\CategoryServiceInterface;
use Joomla\CMS\Categories\CategoryServiceTrait;
use Joomla\CMS\Component\Router\RouterServiceInterface;
use Joomla\CMS\Component\Router\RouterServiceTrait;
use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\Factory;
use Psr\Container\ContainerInterface;

/**
 * Component class for com_userreminder (site half).
 *
 * Loads component languages on boot and exposes category/router service traits
 * so feature subclasses can opt in later without re-declaring boilerplate.
 *
 * @since  4.0.0
 */
class UserReminderComponent extends MVCComponent implements
    ComponentInterface,
    BootableExtensionInterface,
    CategoryServiceInterface,
    RouterServiceInterface
{
    use CategoryServiceTrait;
    use RouterServiceTrait;

    /**
     * Loads the admin + site language files right after the component boots so
     * menus, toolbars and views don't have to remember to do it themselves.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public function boot(ContainerInterface $container): void
    {
        $app = Factory::getApplication();

        $lang = $app->getLanguage();
        $lang->load('com_userreminder', JPATH_ADMINISTRATOR . '/components/com_userreminder');
        $lang->load('com_userreminder', JPATH_SITE . '/components/com_userreminder');
    }
}