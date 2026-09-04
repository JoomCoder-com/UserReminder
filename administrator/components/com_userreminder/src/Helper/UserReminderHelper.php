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
use Joomla\CMS\Language\Text;

/**
 * Admin helper for com_userreminder.
 *
 * Owns the admin sidebar (in-place replacement of Joomla's <ul.main-nav>)
 * and the scheduled-task status checks. Every list view calls
 * `UserReminderHelper::addSubmenu($vName)` before parent::display().
 *
 * @since  4.0.0
 */
final class UserReminderHelper
{
    /**
     * Task type used by plg_task_userreminder.
     *
     * @var  string
     *
     * @since  4.2.0
     */
    public const SCHEDULER_TASK_TYPE = 'userreminder.run';

    /**
     * Whether the Task - UserReminder plugin is installed and enabled.
     *
     * @return  bool
     *
     * @since   4.2.0
     */
    public static function isTaskPluginEnabled(): bool
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('enabled'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('task'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('userreminder'));

        try {
            $db->setQuery($query);

            return (bool) $db->loadResult();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Whether an enabled scheduled task exists for User Reminder.
     *
     * @return  bool  true when at least one enabled userreminder.run task exists.
     *
     * @since   4.2.0
     */
    public static function isScheduledTaskEnabled(): bool
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__scheduler_tasks'))
            ->where($db->quoteName('type') . ' = ' . $db->quote(self::SCHEDULER_TASK_TYPE))
            ->where($db->quoteName('state') . ' = 1');

        try {
            $db->setQuery($query);

            return (int) $db->loadResult() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Fetch the first configured User Reminder scheduled task.
     *
     * @return  object|null  Row with title/state/next_execution/params or null.
     *
     * @since   4.2.0
     */
    public static function getSchedulerTask(): ?object
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['title', 'state', 'next_execution', 'params']))
            ->from($db->quoteName('#__scheduler_tasks'))
            ->where($db->quoteName('type') . ' = ' . $db->quote(self::SCHEDULER_TASK_TYPE))
            ->order($db->quoteName('id') . ' ASC');

        try {
            $db->setQuery($query, 0, 1);

            $row = $db->loadObject();

            return $row ?: null;
        } catch (\Throwable) {
            return null;
        }
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
            'active' => in_array($vName, ['optoutusers', 'optoutusers.default', 'optoutusers.usergroup'], true),
        ];

        $items[] = [
            'label'  => Text::_('COM_USERREMINDER_SUBMENU_LOG'),
            'url'    => 'index.php?option=com_userreminder&view=log',
            'icon'   => 'fas fa-clipboard-list',
            'active' => ($vName == 'log'),
        ];

        $items[] = [
            'label'  => Text::_('COM_USERREMINDER_SUBMENU_MAILTEMPLATES'),
            'url'    => 'index.php?option=com_mails&view=templates&filter[extension]=com_userreminder',
            'icon'   => 'fas fa-envelope-open-text',
            'active' => false,
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

        // Load the sidebar JS through the asset registry.
        $wa = $document->getWebAssetManager();
        $wa->getRegistry()->addRegistryFile('media/com_userreminder/joomla.asset.json');
        $wa->useScript('com_userreminder.sidebar');
    }
}
