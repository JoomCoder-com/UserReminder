<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_userreminder
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomCoder\Component\UserReminder\Administrator\View\Optoutusers;

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
 * Optoutusers view. Two layouts: default (opted-out users list with a
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
    public $inlineDialog = false;

    public function display($tpl = null): void
    {
        $model = $this->getModel();

        // Joomla 5+ PopupButton supports inline dialogs; Joomla 4 falls back
        // to a classic Bootstrap modal (see tmpl/optoutusers/default.php).
        $this->inlineDialog = method_exists(
            \Joomla\CMS\Toolbar\Button\PopupButton::class,
            'popupType'
        );

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
            $this->groupList = $this->get('UserGroups');
        } else {
            // Used by the staging JS inside the "Select Users" dialog.
            Text::script('COM_USERREMINDER_OPTOUT_REMOVE_PICKED');
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

            if ($this->inlineDialog) {
                // Batch-style picker: opens the com_users modal in a Joomla dialog,
                // staged picks are added with task=optoutusers.save (see select_body.php).
                $bar->popupButton('selectUsers', Text::_('COM_USERREMINDER_OPTOUT_SELECT_USERS'))
                    ->popupType('inline')
                    ->textHeader(Text::_('COM_USERREMINDER_OPTOUT_ADD_USERS'))
                    ->url('#userreminder-select-users-dialog')
                    ->modalWidth('800px')
                    ->modalHeight('fit-content')
                    ->icon('icon-plus');
            } else {
                // Joomla 4: PopupButton has no inline popup type, use a classic
                // Bootstrap modal rendered in tmpl/optoutusers/default.php.
                $bar->standardButton('selectUsers', Text::_('COM_USERREMINDER_OPTOUT_SELECT_USERS'))
                    ->icon('icon-plus')
                    ->attributes([
                        'data-bs-toggle' => 'modal',
                        'data-bs-target' => '#userreminder-select-users-modal',
                    ]);
            }

            $bar->standardButton('remove', Text::_('COM_USERREMINDER_OPTUSER_REMOVE_BUTTON'), 'optoutusers.remove')
                ->icon('icon-trash')
                ->listCheck(true);
        }

        if (Factory::getUser()->authorise('core.admin', 'com_userreminder')) {
            ToolbarHelper::preferences('com_userreminder');
        }
    }
}
