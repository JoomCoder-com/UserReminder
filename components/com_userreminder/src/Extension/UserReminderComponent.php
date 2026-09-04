<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_userreminder
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomCoder\Component\UserReminder\Site\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\Router\RouterServiceInterface;
use Joomla\CMS\Component\Router\RouterServiceTrait;
use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\Factory;
use Psr\Container\ContainerInterface;

/**
 * Site component for com_userreminder.
 *
 * Routing is provided through the standard router factory (see
 * Site\Service\Router), wired in the services provider.
 *
 * @since  4.0.0
 */
class UserReminderComponent extends MVCComponent implements ComponentInterface, BootableExtensionInterface, RouterServiceInterface
{
    use RouterServiceTrait;

    public function boot(ContainerInterface $container): void
    {
        // Load component language on the site too (admin language is loaded by the
        // admin component's boot()).
        $lang = Factory::getApplication()->getLanguage();
        $lang->load('com_userreminder', JPATH_SITE . '/components/com_userreminder');
    }
}
