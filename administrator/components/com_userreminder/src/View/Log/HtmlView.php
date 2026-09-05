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

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Registry\Registry;
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

        /** @var \JoomCoder\Component\UserReminder\Administrator\Model\LogModel $model */
        $model               = $this->getModel();
        $this->items         = $model->getItems();
        $this->pagination    = $model->getPagination();
        $this->state         = $model->getState();
        $this->filterForm    = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();

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

        // Signature is confirmButton($name, $text, $task) — the confirmation
        // message goes through ->message(), otherwise the task is broken and
        // the button silently does nothing.
        $bar->confirmButton('clear', 'COM_USERREMINDER_LOG_CLEAR', 'log.clear')
            ->message('COM_USERREMINDER_LOG_CLEAR_CONFIRM')
            ->icon('icon-trash')
            ->buttonClass('btn btn-outline-danger');

        if (Factory::getUser()->authorise('core.admin', 'com_userreminder')) {
            ToolbarHelper::preferences('com_userreminder');
        }
    }
}
