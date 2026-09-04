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

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use JoomCoder\Component\UserReminder\Administrator\Helper\UserReminderHelper;

/**
 * Cpanel / dashboard view — now exposes the full KPI/analytics payload.
 *
 * @since  4.1.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * @var  bool
     *
     * @since  4.0.0
     */
    protected $taskReady = false;

    /**
     * @var  array  Dashboard payload from CpanelModel::getDashboard().
     *
     * @since  4.1.0
     */
    protected $dashboard = [];

    /**
     * @var  \Joomla\Registry\Registry
     *
     * @since  4.1.0
     */
    protected $params;

    public function display($tpl = null): void
    {
        if ($this->getLayout() === 'modal') {
            parent::display($tpl);
            return;
        }

        $app   = Factory::getApplication();
        $input = $app->input;

        /** @var \JoomCoder\Component\UserReminder\Administrator\Model\CpanelModel $model */
        $model = $this->getModel();

        // Refresh cache when ?refresh=1 is present (toolbar Refresh button).
        $forceRefresh = $input->getInt('refresh', 0) === 1;
        if ($forceRefresh) {
            $model->clearDashboardCache();
        }

        $this->dashboard  = $model->getDashboard($forceRefresh);
        $this->taskReady  = UserReminderHelper::isTaskPluginEnabled() && UserReminderHelper::isScheduledTaskEnabled();
        $this->params     = ComponentHelper::getParams('com_userreminder');

        // Pass chart data to JS via script options — dashboard.js reads it.
        $trend  = $this->dashboard['analytics']['trend'] ?? [];
        $byType = $this->dashboard['analytics']['byType'] ?? [1 => 0, 2 => 0, 3 => 0];
        $aging  = $this->dashboard['analytics']['aging'] ?? [];

        $app->getDocument()->addScriptOptions('com_userreminder.dashboard', [
            'trend'  => $trend,
            'byType' => $byType,
            'aging'  => $aging,
            'labels' => [
                'sent'     => Text::_('COM_USERREMINDER_DASH_TREND_30D'),
                'type1'    => Text::_('COM_USERREMINDER_DASH_TYPE_1'),
                'type2'    => Text::_('COM_USERREMINDER_DASH_TYPE_2'),
                'type3'    => Text::_('COM_USERREMINDER_DASH_TYPE_3'),
            ],
        ]);

        UserReminderHelper::addSubmenu('cpanel');

        $this->addToolbar();
        $this->loadDashboardAssets();

        parent::display($tpl);
    }

    protected function addToolbar(): void
    {
        ToolbarHelper::title(Text::_('COM_USERREMINDER_TOOLBAR'), 'userreminder');

        $bar = Toolbar::getInstance();

        // Send registration reminders (types 1 + 2) now.
        $bar->standardButton('sendReminders', Text::_('COM_USERREMINDER_DASH_ACTION_SEND_REMINDERS'), 'display.sendReminders')
            ->icon('icon-mail')
            ->buttonClass('btn btn-success');

        // Send active user (inactivity) reminders now.
        $bar->standardButton('sendInactiveReminders', Text::_('COM_USERREMINDER_DASH_ACTION_SEND_ACTIVE'), 'display.sendInactiveReminders')
            ->icon('icon-mail');

        // Refresh — clears 10-min cache and reloads.
        $bar->standardButton('refresh', Text::_('COM_USERREMINDER_DASH_REFRESH'), 'display.refresh')
            ->icon('icon-refresh');

        // Quick prune (12-mo retention) — runs LogModel::pruneOld().
        $bar->standardButton('prune', Text::_('COM_USERREMINDER_DASH_PRUNE'), 'log.pruneOld')
            ->icon('icon-trash');

        if (Factory::getUser()->authorise('core.admin', 'com_userreminder')) {
            ToolbarHelper::preferences('com_userreminder');
        }
    }

    private function loadDashboardAssets(): void
    {
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->getRegistry()->addRegistryFile('media/com_userreminder/joomla.asset.json');

        // Chart.js — self-hosted, optional. dashboard.js degrades to CSS bars if missing.
        $wa->useScript('com_userreminder.dashboard');

        $wa->useStyle('com_userreminder.style');
    }
}