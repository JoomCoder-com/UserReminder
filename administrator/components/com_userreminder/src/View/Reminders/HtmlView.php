<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_userreminder
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomCoder\Component\UserReminder\Administrator\View\Reminders;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Registry\Registry;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseDriver;
use JoomCoder\Component\UserReminder\Administrator\Helper\UserReminderHelper;

/**
 * Reminders view — shows the user table for the "Incomplete Registrations"
 * tab plus a toolbar for sending reminders.
 *
 * @since  4.0.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * The search tools form
     *
     * @var  Form
     *
     * @since  4.0.0
     */
    public $filterForm;

    /**
     * The active search filters
     *
     * @var  array
     *
     * @since  4.0.0
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
     * @since  4.0.0
     */
    protected $state;

    /**
     * @var  DatabaseDriver
     *
     * @since  4.0.0
     */
    protected $db;

    public function display($tpl = null): void
    {
        if ($this->getLayout() === 'modal') {
            parent::display($tpl);
            return;
        }

        /** @var \JoomCoder\Component\UserReminder\Administrator\Model\RemindersModel $model */
        $model           = $this->getModel();
        $this->items      = $model->getItems();
        $this->pagination = $model->getPagination();
        $this->state      = $model->getState();
        $this->filterForm = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();
        $this->db         = Factory::getDbo();

        UserReminderHelper::addSubmenu('reminders');

        $this->addToolbar();

        parent::display($tpl);
    }

    protected function addToolbar(): void
    {
        $toolbar = Toolbar::getInstance();

        ToolbarHelper::title(Text::_('COM_USERREMINDER_TOOLBAR_REMINDERS'), 'userreminder');

        $toolbar->standardButton('sendTestMail', Text::_('COM_USERREMINDER_TOOLBAR_TEST'), 'reminders.sendTestMail')
            ->icon('icon-envelope');

        $toolbar->standardButton('sendReminders', Text::_('COM_USERREMINDER_TOOLBAR_SEND'), 'reminders.sendReminders')
            ->icon('icon-paper-plane');

        $toolbar->standardButton('cpanel', Text::_('COM_USERREMINDER_TOOLBAR_HOME'), 'display.cpanel')
            ->icon('icon-home');
    }
}