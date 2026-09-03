<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_userreminder
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomCoder\Component\UserReminder\Administrator\View\Cpanel;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use JoomCoder\Component\UserReminder\Administrator\Helper\UserReminderHelper;

/**
 * Cpanel / dashboard view.
 *
 * @since  4.0.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * @var  bool
     *
     * @since  4.0.0
     */
    protected $systemPluginEnabled = false;

    public function display($tpl = null): void
    {
        if ($this->getLayout() === 'modal') {
            parent::display($tpl);
            return;
        }

        $this->systemPluginEnabled = UserReminderHelper::isSystemPluginEnabled();

        $this->addToolbar();
        UserReminderHelper::addSubmenu('cpanel');

        parent::display($tpl);
    }

    protected function addToolbar(): void
    {
        Toolbar::getInstance()->appendButton('Custom', '<h1 class="page-title">' . Text::_('COM_USERREMINDER_TOOLBAR') . '</h1>');
    }
}