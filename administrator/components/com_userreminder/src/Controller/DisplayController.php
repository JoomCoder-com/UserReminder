<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_userreminder
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomCoder\Component\UserReminder\Administrator\Controller;

\defined('_JEXEC') or die;

use JoomCoder\Component\UserReminder\Administrator\Helper\SiteUrl;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

/**
 * Base controller for com_userreminder. Default task = display.
 *
 * @since  4.0.0
 */
class DisplayController extends BaseController
{
    use RunTrait;

    /**
     * Default landing page.
     *
     * @var  string
     */
    protected $default_view = 'cpanel';

    /**
     * Show the cpanel/dashboard view.
     *
     * Every view in this component exposes personal data (names, usernames,
     * email addresses), so display is gated on core.manage — the same action
     * the access.xml declares for component administration.
     *
     * @param   bool  $cachable
     * @param   array $urlparams
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public function display($cachable = false, $urlparams = []): void
    {
        $this->requireAuthorised('core.manage');

        // Command-line runs (server cron) have no site address of their own.
        SiteUrl::remember();

        parent::display($cachable, $urlparams);
    }

    /**
     * Show the cpanel/dashboard view.
     *
     * @param   bool  $cachable
     * @param   array $urlparams
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public function cpanel($cachable = false, $urlparams = []): void
    {
        $this->input->set('view', 'cpanel');
        $this->display($cachable, $urlparams);
    }

    /**
     * Refresh dashboard — clears the 10-min cache and redirects back to cpanel.
     *
     * @return  void
     *
     * @since   4.1.0
     */
    public function refresh(): void
    {
        $this->checkToken();
        $this->requireAuthorised('core.manage');

        /** @var \JoomCoder\Component\UserReminder\Administrator\Model\CpanelModel $model */
        $model = $this->getModel('Cpanel', 'Administrator');
        $model->clearDashboardCache();

        $this->setRedirect(Route::_('index.php?option=com_userreminder&view=cpanel', false));
    }

    /**
     * Send registration reminders (types 1 + 2) from the dashboard toolbar.
     *
     * @return  void
     *
     * @since   4.1.0
     */
    public function sendReminders(): void
    {
        $this->runNow(false, 'cpanel');
    }

    /**
     * Send inactive-user reminders from the dashboard toolbar.
     *
     * @return  void
     *
     * @since   4.1.0
     */
    public function sendInactiveReminders(): void
    {
        $this->runNow(true, 'cpanel');
    }
}
