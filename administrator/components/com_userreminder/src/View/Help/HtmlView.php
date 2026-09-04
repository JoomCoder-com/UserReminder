<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_userreminder
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomCoder\Component\UserReminder\Administrator\View\Help;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use JoomCoder\Component\UserReminder\Administrator\Helper\UserReminderHelper;

/**
 * Static help / documentation view.
 *
 * @since  4.0.0
 */
class HtmlView extends BaseHtmlView
{
    public function display($tpl = null): void
    {
        if ($this->getLayout() === 'modal') {
            parent::display($tpl);
            return;
        }

        UserReminderHelper::addSubmenu('help');

        $this->addToolbar();

        parent::display($tpl);
    }

    protected function addToolbar(): void
    {
        ToolbarHelper::title(Text::_('COM_USERREMINDER_TOOLBAR_HELP'), 'userreminder');

        if (Factory::getUser()->authorise('core.admin', 'com_userreminder')) {
            ToolbarHelper::preferences('com_userreminder');
        }
    }
}