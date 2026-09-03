<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_userreminder
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomCoder\Component\UserReminder\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\Database\DatabaseInterface;

/**
 * Admin helper for com_userreminder.
 *
 * Owns the admin sidebar (in-place replacement of Joomla's <ul.main-nav>)
 * and the common JS/CSS asset load. Every list view calls
 * `UserReminderHelper::addSubmenu($vName)` before parent::display().
 *
 * @since  4.0.0
 */
final class UserReminderHelper
{
    /**
     * Load assets shared across admin views. Called by addSubmenu().
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public static function loadCommonAssets(): void
    {
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->useScript('jquery');
    }

    /**
     * Whether the component's system plugin is currently enabled.
     *
     * Only relevant if the component uses a system plugin to inject
     * the sidebar on "vertical" admin pages (com_fields, com_config,
     * com_categories) — when the plugin is disabled, those pages
     * silently fall back to Joomla's default sidebar. The dashboard
     * should surface this as a warning.
     *
     * @return  bool
     *
     * @since   4.0.0
     */
    public static function isSystemPluginEnabled(): bool
    {
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
        } catch (\Throwable) {
            $db = Factory::getDbo();
        }
        $query = $db->getQuery(true)
            ->select($db->quoteName('enabled'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('userreminder'));

        $db->setQuery($query);

        return (bool) $db->loadResult();
    }

    /**
     * Configure the left sidebar menu.
     * Outputs com_userreminder menu items as JSON config for the JS sidebar script.
     *
     * @param   string  $vName  The name of the active view.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public static function addSubmenu(string $vName): void
    {
        self::loadCommonAssets();

        $document = Factory::getApplication()->getDocument();

        // Build menu items array.
        // Each item: ['label' => ..., 'url' => ..., 'icon' => ..., 'active' => ...]
        $items = [];

        $items[] = [
            'label'  => Text::_('COM_USERREMINDER_SUBMENU_CPANEL'),
            'url'    => 'index.php?option=com_userreminder&view=cpanel',
            'icon'   => 'fas fa-tachometer-alt',
            'active' => ($vName == 'cpanel'),
        ];

        $items[] = [
            'label'  => Text::_('COM_USERREMINDER_SUBMENU_REMINDERS'),
            'url'    => 'index.php?option=com_userreminder&view=reminders',
            'icon'   => 'fas fa-user-clock',
            'active' => ($vName == 'reminders'),
        ];

        $items[] = [
            'label'  => Text::_('COM_USERREMINDER_SUBMENU_ACTIVE_USERS'),
            'url'    => 'index.php?option=com_userreminder&view=activeusers',
            'icon'   => 'fas fa-user-check',
            'active' => ($vName == 'activeusers'),
        ];

        $items[] = [
            'label'  => Text::_('COM_USERREMINDER_SUBMENU_OPTOUT'),
            'url'    => 'index.php?option=com_userreminder&view=optoutusers',
            'icon'   => 'fas fa-user-slash',
            'active' => in_array($vName, ['optoutusers', 'optoutusers.userlist', 'optoutusers.usergroup'], true),
        ];

        $items[] = [
            'label'  => Text::_('COM_USERREMINDER_SUBMENU_LOG'),
            'url'    => 'index.php?option=com_userreminder&view=log',
            'icon'   => 'fas fa-clipboard-list',
            'active' => ($vName == 'log'),
        ];

        $items[] = [
            'label'  => Text::_('COM_USERREMINDER_SUBMENU_HELP'),
            'url'    => 'index.php?option=com_userreminder&view=help',
            'icon'   => 'fas fa-info-circle',
            'active' => ($vName == 'help'),
        ];

        // Pass menu config to JavaScript.
        $document->addScriptOptions('userreminder.sidebar', [
            'items'       => $items,
            'activeView'  => $vName,
            'backLabel'   => Text::_('COM_USERREMINDER_SIDEBAR_BACK_TO_MAIN_MENU'),
            'headerLabel' => Text::_('COM_USERREMINDER'),
        ]);

        // Anti-flicker + header/back-item/submenu styling.
        $document->addStyleDeclaration('
            ul.main-nav { visibility: hidden; }
            .main-nav .userreminder-header a {
                font-weight: 600;
                font-size: 1rem;
                letter-spacing: 0.03em;
                background-color: rgba(255,255,255,0.04);
                opacity: 1;
            }
            .main-nav .userreminder-header a:hover {
                background-color: rgba(255,255,255,0.08);
            }
            .main-nav .userreminder-header-divider {
                display: none;
            }
            .main-nav .userreminder-header + .item {
                margin-top: 0.5rem;
            }
            .main-nav .collapse-level-1 .item a,
            .main-nav ul.userreminder-submenu .item a {
                padding-left: 1.25rem;
            }
            .main-nav .userreminder-back-item {
                margin-top: 0.75rem;
            }
            .main-nav .userreminder-back-item a {
                opacity: 0.65;
                font-size: 0.85rem;
            }
            .main-nav .userreminder-back-item a:hover {
                opacity: 1;
            }
        ');

        // Load the sidebar JS.
        HTMLHelper::script('media/com_userreminder/js/userreminder-sidebar.js', ['version' => 'auto']);

        // Toolbar preferences (Parameters) link for users with admin rights.
        if (Factory::getUser()->authorise('core.admin', 'com_userreminder')) {
            Toolbar::getInstance()->preferences('com_userreminder');
        }
    }
}
