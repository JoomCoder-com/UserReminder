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

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use JoomCoder\Component\UserReminder\Administrator\Helper\UserReminderHelper;

/**
 * Active users view — users who registered but haven't visited in X days.
 *
 * @since  4.0.0
 */
class HtmlView extends BaseHtmlView
{
    protected $items;
    protected $pagination;

    public function display($tpl = null): void
    {
        if ($this->getLayout() === 'modal') {
            parent::display($tpl);
            return;
        }

        $this->items      = $this->get('Items');
        $this->pagination = $this->get('Pagination');

        UserReminderHelper::addSubmenu('activeusers');

        $this->addToolbar();

        parent::display($tpl);
    }

    protected function addToolbar(): void
    {
        $toolbar = Toolbar::getInstance();

        \Joomla\CMS\Toolbar\ToolbarHelper::title(Text::_('COM_USERREMINDER_TOOLBAR_ACTIVE_USERS'), 'userreminder');

        $toolbar->standardButton('sendTestMail', Text::_('COM_USERREMINDER_TOOLBAR_TEST'), 'activeusers.sendTestMail')
            ->icon('icon-envelope');

        $toolbar->standardButton('sendReminders', Text::_('COM_USERREMINDER_TOOLBAR_SEND'), 'activeusers.sendReminders')
            ->icon('icon-paper-plane');

        $toolbar->standardButton('cpanel', Text::_('COM_USERREMINDER_TOOLBAR_HOME'), 'display.cpanel')
            ->icon('icon-home');
    }
}