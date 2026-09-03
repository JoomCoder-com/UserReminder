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
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use JoomCoder\Component\UserReminder\Administrator\Helper\UserReminderHelper;

/**
 * Reminders view — shows the user table for the "Incomplete Registrations"
 * tab plus a toolbar for sending reminders.
 *
 * @since  4.0.0
 */
class HtmlView extends HtmlView
{
    /**
     * @var  \Joomla\CMS\Pagination\Pagination
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
     * @var  \Joomla\Database\DatabaseDriver
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

        $this->items      = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->db         = Factory::getDbo();

        $this->addToolbar();

        UserReminderHelper::addSubmenu('reminders');

        parent::display($tpl);
    }

    protected function addToolbar(): void
    {
        $toolbar = ToolbarHelper::getInstance('toolbar');

        ToolbarHelper::title(Text::_('COM_USERREMINDER_TOOLBAR_REMINDERS'), 'userreminder');

        $toolbar->standardButton('sendTestMail', Text::_('COM_USERREMINDER_TOOLBAR_TEST'), 'reminders.sendTestMail')
            ->icon('icon-envelope');

        $toolbar->standardButton('sendReminders', Text::_('COM_USERREMINDER_TOOLBAR_SEND'), 'reminders.sendReminders')
            ->icon('icon-paper-plane');

        $toolbar->standardButton('cpanel', Text::_('COM_USERREMINDER_TOOLBAR_HOME'), 'display.cpanel')
            ->icon('icon-home');
    }
}