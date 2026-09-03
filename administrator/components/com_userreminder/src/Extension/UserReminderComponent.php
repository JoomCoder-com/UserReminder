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
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use JoomCoder\Component\UserReminder\Administrator\Helper\UserReminderHelper;

/**
 * Component class for com_userreminder.
 *
 * Loads the helper on boot and exposes category/router service traits so feature
 * subclasses can opt in later without re-declaring boilerplate.
 *
 * @since  4.0.0
 */
class UserReminderComponent extends MVCComponent implements
    ComponentInterface,
    CategoryServiceInterface,
    RouterServiceInterface
{
    use CategoryServiceTrait;
    use RouterServiceTrait;

    /**
     * Registers the helper-side hooks (sidebar, common assets) right after the
     * component boots so toolbar code can rely on them.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public function boot(): void
    {
        $app = $this->getApplication();

        // Always load the admin + site language files for the component so
        // menus, toolbars and views don't have to remember to do it themselves.
        $lang = $app->getLanguage();
        $lang->load('com_userreminder', JPATH_ADMINISTRATOR . '/components/com_userreminder');
        $lang->load('com_userreminder', JPATH_SITE . '/components/com_userreminder');

        UserReminderHelper::loadCommonAssets();
    }
}