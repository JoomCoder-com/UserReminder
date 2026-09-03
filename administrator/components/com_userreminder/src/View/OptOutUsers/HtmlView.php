<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_userreminder
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomCoder\Component\UserReminder\Administrator\View\OptOutUsers;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use JoomCoder\Component\UserReminder\Administrator\Helper\UserReminderHelper;

/**
 * OptOutUsers view. Three layouts: optout (default), userlist (picker for
 * new opt-outs), usergroup (pick groups to exclude from reminders).
 *
 * @since  4.0.0
 */
class HtmlView extends BaseHtmlView
{
    public $items;
    public $pagination;
    public $optgroups = [];
    public $groupList = [];
    public $filterSearch;

    public function display($tpl = null): void
    {
        if ($this->getLayout() === 'modal') {
            parent::display($tpl);
            return;
        }

        $this->items      = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->optgroups  = $this->get('OptGroups');

        $this->filterSearch = trim((string) Factory::getApplication()->input->get('filter_search', '', 'string'));

        if ($this->getLayout() === 'usergroup') {
            $this->groupList = $this->loadUserGroups();
        }

        $this->addToolbar();
        UserReminderHelper::addSubmenu('optoutusers.' . ($this->getLayout() ?: 'optout'));

        parent::display($tpl);
    }

    protected function addToolbar(): void
    {
        $layout = $this->getLayout();

        switch ($layout) {
            case 'userlist':
                ToolbarHelper::title(Text::_('COM_USERREMINDER_TOOLBAR_USER_LIST'), 'userreminder');
                Toolbar::getInstance()->standardButton('save', Text::_('COM_USERREMINDER_OPTUSER_ADD_CLOSE'), 'optoutusers.save')
                    ->icon('icon-save');
                break;

            case 'usergroup':
                ToolbarHelper::title(Text::_('COM_USERREMINDER_TOOLBAR_USER_GROUPS'), 'userreminder');
                Toolbar::getInstance()->standardButton('saveGroup', Text::_('COM_USERREMINDER_SAVE_CLOSE'), 'optoutusers.saveGroup')
                    ->icon('icon-save');
                break;

            default:
                ToolbarHelper::title(Text::_('COM_USERREMINDER_TOOLBAR_OPTOUT'), 'userreminder');
                $bar = Toolbar::getInstance();
                $bar->standardButton('remove', Text::_('COM_USERREMINDER_OPTUSER_REMOVE_BUTTON'), 'optoutusers.remove')
                    ->icon('icon-trash')
                    ->listCheck(true);
                break;
        }

        Toolbar::getInstance()->standardButton('cpanel', Text::_('COM_USERREMINDER_TOOLBAR_HOME'), 'display.cpanel')
            ->icon('icon-home');
    }

    private function loadUserGroups(): array
    {
        $db    = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'parent_id', 'title', 'lft', 'rgt']))
            ->from($db->quoteName('#__usergroups'))
            ->order('lft ASC');

        $db->setQuery($query);

        $groups = $db->loadObjectList() ?: [];

        // Compute level via parent hierarchy to keep template indentation working
        // without relying on a DB column that Joomla 4+ no longer provides.
        $byId = [];
        foreach ($groups as $g) {
            $byId[(int) $g->id] = $g;
            $g->level = 0;
        }

        foreach ($groups as $g) {
            $level   = 0;
            $pid     = (int) $g->parent_id;
            $visited = [];

            while ($pid !== 0 && isset($byId[$pid]) && !isset($visited[$pid])) {
                $visited[$pid] = true;
                $level++;
                $pid = (int) $byId[$pid]->parent_id;

                // Safety cap for malformed trees.
                if ($level > 20) {
                    break;
                }
            }

            $g->level = $level;
        }

        return $groups;
    }
}