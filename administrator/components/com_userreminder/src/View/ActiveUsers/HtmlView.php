<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_userreminder
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomCoder\Component\UserReminder\Administrator\View\ActiveUsers;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Registry\Registry;
use JoomCoder\Component\UserReminder\Administrator\Helper\UserReminderHelper;

/**
 * Active users view — users who registered but haven't visited in X days.
 *
 * @since  4.0.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * The search tools form
     *
     * @var  Form|null
     *
     * @since  4.2.0
     */
    public $filterForm;

    /**
     * The active search filters
     *
     * @var  array
     *
     * @since  4.2.0
     */
    public $activeFilters = [];

    /**
     * @var  Pagination
     *
     * @since  4.0.0
     */
    protected $pagination;

    /**
     * @var  array
     *
     * @since  4.0.0
     */
    protected $items;

    /**
     * The model state
     *
     * @var  Registry
     *
     * @since  4.2.0
     */
    protected $state;

    public function display($tpl = null): void
    {
        if ($this->getLayout() === 'modal') {
            parent::display($tpl);
            return;
        }

        $this->items      = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->state      = $this->get('State');
        $this->filterForm    = $this->get('FilterForm');
        $this->activeFilters = $this->get('ActiveFilters');

        UserReminderHelper::addSubmenu('activeusers');

        $this->addToolbar();

        parent::display($tpl);
    }

    protected function addToolbar(): void
    {
        ToolbarHelper::title(Text::_('COM_USERREMINDER_TOOLBAR_ACTIVE_USERS'), 'userreminder');

        $toolbar = Toolbar::getInstance();

        $toolbar->standardButton('sendTestMail', Text::_('COM_USERREMINDER_TOOLBAR_TEST'), 'activeusers.sendTestMail')
            ->icon('icon-envelope');

        $toolbar->standardButton('sendReminders', Text::_('COM_USERREMINDER_TOOLBAR_SEND'), 'activeusers.sendReminders')
            ->icon('icon-mail');

        if (Factory::getUser()->authorise('core.admin', 'com_userreminder')) {
            ToolbarHelper::preferences('com_userreminder');
        }
    }
}