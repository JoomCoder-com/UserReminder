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
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
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
    protected $systemPluginEnabled = false;

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

        $this->dashboard           = $model->getDashboard($forceRefresh);
        $this->systemPluginEnabled = UserReminderHelper::isSystemPluginEnabled();
        $this->params              = ComponentHelper::getParams('com_userreminder');

        // Pass chart data to JS via script options — dashboard.js reads it.
        $trend  = $this->dashboard['analytics']['trend'] ?? [];
        $byType = $this->dashboard['analytics']['byType'] ?? [1 => 0, 2 => 0, 3 => 0];
        $aging  = $this->dashboard['analytics']['aging'] ?? [];

        $app->getDocument()->addScriptOptions('com_userreminder.dashboard', [
            'trend'  => $trend,
            'byType' => $byType,
            'aging'  => $aging,
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

        // Refresh — clears 10-min cache and reloads.
        $bar->standardButton('refresh', Text::_('COM_USERREMINDER_DASH_REFRESH'), 'display.refresh')
            ->icon('icon-refresh')
            ->buttonClass('btn btn-primary');

        // Quick prune (12-mo retention) — runs LogModel::pruneOld().
        $bar->standardButton('prune', Text::_('COM_USERREMINDER_DASH_PRUNE'), 'log.pruneOld')
            ->icon('icon-trash')
            ->buttonClass('btn btn-outline-danger');

        $bar->standardButton('cpanel', Text::_('JTOOLBAR_HELP'), 'display.cpanel')
            ->icon('icon-help');

        if (Factory::getUser()->authorise('core.admin', 'com_userreminder')) {
            ToolbarHelper::preferences('com_userreminder');
        }
    }

    private function loadDashboardAssets(): void
    {
        // Common sidebar + base CSS.
        UserReminderHelper::loadCommonAssets();

        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();

        // Chart.js — self-hosted, optional. dashboard.js degrades to CSS bars if missing.
        try {
            $wa->registerAndUseScript(
                'com_userreminder.chart',
                'com_userreminder/chart.umd.min.js',
                [],
                ['defer' => true]
            );
        } catch (\Throwable) {
            // Joomla 4 fallback via HTMLHelper.
            try {
                HTMLHelper::_('script', 'com_userreminder/chart.umd.min.js', ['relative' => true, 'version' => 'auto']);
            } catch (\Throwable) {
            }
        }

        try {
            $wa->registerAndUseScript(
                'com_userreminder.dashboard',
                'com_userreminder/dashboard.js',
                ['com_userreminder.chart'],
                ['defer' => true]
            );
        } catch (\Throwable) {
            HTMLHelper::_('script', 'com_userreminder/dashboard.js', ['relative' => true, 'version' => 'auto']);
        }

        HTMLHelper::_('stylesheet', 'com_userreminder/userreminder.css', ['relative' => true, 'version' => 'auto']);
    }
}