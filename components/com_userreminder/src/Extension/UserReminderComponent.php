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
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\MVCComponent;
use Psr\Container\ContainerInterface;
use JoomCoder\Component\UserReminder\Site\Service\Router;

/**
 * Site component for com_userreminder.
 *
 * Holds the SEF router service and exposes a small accessor so the
 * services provider can pass it back to Joomla's router manager.
 *
 * @since  4.0.0
 */
class UserReminderComponent extends MVCComponent implements ComponentInterface, RouterServiceInterface
{
    use RouterServiceTrait;

    public function boot(): void
    {
        // Load component language on the site too (admin language is loaded by the
        // admin component's boot()).
        $lang = $this->getApplication()->getLanguage();
        $lang->load('com_userreminder', JPATH_SITE . '/components/com_userreminder');
    }

    public function getRouter(?ContainerInterface $container = null): Router
    {
        return new Router($this->getApplication()->getRouter());
    }
}