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
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use JoomCoder\Component\UserReminder\Administrator\Helper\UserReminderHelper;

/**
 * OptOutUsers view. Two layouts: default (opted-out users list with a
 * com_users modal picker to add more) and usergroup (pick groups to exclude
 * from reminders).
 *
 * @since  4.0.0
 */
class HtmlView extends BaseHtmlView
{
    public $items;
    public ?Pagination $pagination = null;
    public $state;
    public ?Form $filterForm = null;
    public $activeFilters = [];
    public $optgroups = [];
    public $groupList = [];
    public $excludedIds = [];

    public function display($tpl = null): void
    {
        $model = $this->getModel();

        $this->items         = $this->get('Items');
        $this->pagination    = $this->get('Pagination');
        $this->state         = $this->get('State');
        $this->filterForm    = $this->get('FilterForm');
        $this->activeFilters = $this->get('ActiveFilters');
        $this->optgroups     = $this->get('OptGroups');
        $this->excludedIds   = $this->get('OptedOutIds');

        if ($error = $model->getError()) {
            Factory::getApplication()->enqueueMessage($error, 'error');
        }

        if ($this->getLayout() === 'usergroup') {
            $this->groupList = $this->loadUserGroups();
        }

        UserReminderHelper::addSubmenu('optoutusers.' . ($this->getLayout() ?: 'default'));

        $this->addToolbar();

        parent::display($tpl);
    }

    protected function addToolbar(): void
    {
        $layout = $this->getLayout();

        if ($layout === 'usergroup') {
            ToolbarHelper::title(Text::_('COM_USERREMINDER_TOOLBAR_USER_GROUPS'), 'userreminder');
            Toolbar::getInstance()->standardButton('saveGroup', Text::_('COM_USERREMINDER_SAVE_CLOSE'), 'optoutusers.saveGroup')
                ->icon('icon-save');
        } else {
            ToolbarHelper::title(Text::_('COM_USERREMINDER_TOOLBAR_OPTOUT'), 'userreminder');
            $bar = Toolbar::getInstance();
            // Batch-style picker: opens the com_users modal in a Joomla dialog,
            // staged picks are added with task=optoutusers.save (see select_body.php).
            $bar->popupButton('selectUsers', Text::_('COM_USERREMINDER_OPTOUT_SELECT_USERS'))
                ->popupType('inline')
                ->textHeader(Text::_('COM_USERREMINDER_OPTOUT_ADD_USERS'))
                ->url('#userreminder-select-users-dialog')
                ->modalWidth('800px')
                ->modalHeight('fit-content')
                ->icon('icon-plus');
            $bar->standardButton('remove', Text::_('COM_USERREMINDER_OPTUSER_REMOVE_BUTTON'), 'optoutusers.remove')
                ->icon('icon-trash')
                ->listCheck(true);
        }

        if (Factory::getUser()->authorise('core.admin', 'com_userreminder')) {
            ToolbarHelper::preferences('com_userreminder');
        }
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
