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

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Router\Route;
use JoomCoder\Component\UserReminder\Administrator\Service\SendService;

/**
 * Base controller for com_userreminder. Default task = display.
 *
 * @since  4.0.0
 */
class DisplayController extends BaseController
{
    use AclTrait;

    /**
     * @param   array                     $config
     * @param   MVCFactoryInterface|null  $factory
     * @param   AdministratorApplication|null $app
     * @param   \Joomla\CMS\Input\Input|null $input
     */
    public function __construct(
        $config = [],
        ?MVCFactoryInterface $factory = null,
        ?AdministratorApplication $app = null,
        ?\Joomla\CMS\Input\Input $input = null
    ) {
        parent::__construct($config, $factory, $app, $input);

        // Default landing page.
        $this->default_view = 'cpanel';
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

        /** @var \JoomCoder\Component\UserReminder\Administrator\Model\CpanelModel $model */
        $model = $this->getModel('Cpanel', 'Administrator');
        $model->clearDashboardCache();

        $this->setRedirect(
            \Joomla\CMS\Router\Route::_('index.php?option=com_userreminder&view=cpanel', false)
        );
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
        $this->checkToken();
        $this->requireAuthorised('core.manage');

        $stats = $this->getSendService()->processRegistrationReminders(true, 0, 0);

        $this->enqueueRunMessage($stats);
        $this->redirectToCpanel();
    }

    /**
     * Send active user (inactivity) reminders from the dashboard toolbar.
     *
     * @return  void
     *
     * @since   4.1.0
     */
    public function sendInactiveReminders(): void
    {
        $this->checkToken();
        $this->requireAuthorised('core.manage');

        $stats = $this->getSendService()->processInactiveUserReminders(true, 0, 0);

        $this->enqueueRunMessage($stats);
        $this->redirectToCpanel();
    }

    /**
     * @return  SendService
     */
    private function getSendService(): SendService
    {
        return SendService::instance();
    }

    /**
     * @param   array  $stats  processed/sent/deleted counts
     *
     * @return  void
     */
    private function enqueueRunMessage(array $stats): void
    {
        Factory::getApplication()->enqueueMessage(
            Text::sprintf(
                'COM_USERREMINDER_RUN_COMPLETE',
                $stats['processed'],
                $stats['sent'],
                $stats['deleted']
            ),
            'info'
        );
    }

    private function redirectToCpanel(): void
    {
        $this->setRedirect(Route::_('index.php?option=com_userreminder&view=cpanel', false));
    }
}
