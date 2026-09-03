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

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\Toolbar;

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
     * Build the sidebar items and ship them to JS as script options.
     *
     * @param   string  $vName  Active view name, e.g. 'reminders'.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public static function addSubmenu(string $vName): void
    {
        $items = [
            [
                'id'       => 'cpanel',
                'label'    => Text::_('COM_USERREMINDER_SUBMENU_CPANEL'),
                'icon'     => 'fas fa-tachometer-alt',
                'url'      => 'index.php?option=com_userreminder&view=cpanel',
                'active'   => $vName === 'cpanel',
            ],
            [
                'id'       => 'reminders',
                'label'    => Text::_('COM_USERREMINDER_SUBMENU_REMINDERS'),
                'icon'     => 'fas fa-user-clock',
                'url'      => 'index.php?option=com_userreminder&view=reminders',
                'active'   => $vName === 'reminders' || $vName === 'sendreminders',
            ],
            [
                'id'       => 'activeusers',
                'label'    => Text::_('COM_USERREMINDER_SUBMENU_ACTIVE_USERS'),
                'icon'     => 'fas fa-user-check',
                'url'      => 'index.php?option=com_userreminder&view=activeusers',
                'active'   => $vName === 'activeusers' || $vName === 'sendactiveusers',
            ],
            [
                'id'       => 'optout',
                'label'    => Text::_('COM_USERREMINDER_SUBMENU_OPTOUT'),
                'icon'     => 'fas fa-user-slash',
                'url'      => 'index.php?option=com_userreminder&view=optoutusers',
                'active'   => in_array($vName, ['optoutusers', 'optoutusers.userlist', 'optoutusers.usergroup'], true),
            ],
            [
                'id'       => 'log',
                'label'    => Text::_('COM_USERREMINDER_SUBMENU_LOG'),
                'icon'     => 'fas fa-clipboard-list',
                'url'      => 'index.php?option=com_userreminder&view=log',
                'active'   => $vName === 'log',
            ],
            [
                'id'       => 'help',
                'label'    => Text::_('COM_USERREMINDER_SUBMENU_HELP'),
                'icon'     => 'fas fa-info-circle',
                'url'      => 'index.php?option=com_userreminder&view=help',
                'active'   => $vName === 'help',
            ],
        ];

        $app = Factory::getApplication();
        if ($app instanceof AdministratorApplication) {
            $app->getDocument()->addScriptOptions('com_userreminder.sidebar.items', $items);
        }

        // Toolbar preferences (Parameters) link for users with admin rights.
        if (Factory::getUser()->authorise('core.admin', 'com_userreminder')) {
            Toolbar::getInstance()->preferences('com_userreminder');
        }
    }

    /**
     * Load the common JS/CSS assets used by every list/utility view.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public static function loadCommonAssets(): void
    {
        HTMLHelper::_('script', 'com_userreminder/userreminder-sidebar.js', [
            'relative' => true,
            'version'  => 'auto',
        ]);

        HTMLHelper::_('stylesheet', 'com_userreminder/userreminder.css', [
            'relative' => true,
            'version'  => 'auto',
        ]);
    }

    /**
     * Is the system-userreminder plugin enabled? Used for the dashboard warning
     * so admins notice when the scheduled-runner is disabled.
     *
     * @return  bool
     *
     * @since   4.0.0
     */
    public static function isSystemPluginEnabled(): bool
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('enabled'))
            ->from($db->quoteName('#__extensions'))
            ->where(
                [
                    $db->quoteName('type') . ' = ' . $db->quote('plugin'),
                    $db->quoteName('folder') . ' = ' . $db->quote('system'),
                    $db->quoteName('element') . ' = ' . $db->quote('userreminder'),
                ]
            );
        $db->setQuery($query);

        return (int) $db->loadResult() === 1;
    }
}