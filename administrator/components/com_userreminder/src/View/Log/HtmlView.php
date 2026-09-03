<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_userreminder
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomCoder\Component\UserReminder\Administrator\View\Log;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use JoomCoder\Component\UserReminder\Administrator\Helper\UserReminderHelper;

/**
 * Log view — paginated audit log of every reminder sent.
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

        UserReminderHelper::addSubmenu('log');

        $this->addToolbar();

        parent::display($tpl);
    }

    protected function addToolbar(): void
    {
        ToolbarHelper::title(Text::_('COM_USERREMINDER_TOOLBAR_LOG'), 'userreminder');

        $bar = Toolbar::getInstance();
        $bar->standardButton('prune', Text::_('COM_USERREMINDER_DASH_PRUNE'), 'log.pruneOld')
            ->icon('icon-clock')
            ->buttonClass('btn btn-outline-secondary');

        $bar->standardButton('clear', Text::_('COM_USERREMINDER_LOG_CLEAR'), 'log.clear')
            ->icon('icon-trash')
            ->listCheck(true);

        $bar->standardButton('cpanel', Text::_('COM_USERREMINDER_TOOLBAR_HOME'), 'display.cpanel')
            ->icon('icon-home');
    }
}