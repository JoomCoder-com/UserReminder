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

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use JoomCoder\Component\UserReminder\Administrator\Service\SendService;

/**
 * Reminders controller — runs the registration-reminder pipeline and reports
 * back to the user.
 *
 * @since  4.0.0
 */
class RemindersController extends BaseController
{
    /**
     * Send registration reminders (types 1 + 2).
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public function sendReminders(): void
    {
        $this->checkToken();

        try {
            /** @var SendService $send */
            $send = Factory::getContainer()->get(SendService::class);
        } catch (\Throwable) {
            $send = new SendService(Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class));
        }

        $app   = Factory::getApplication();
        $input = $app->getInput();
        $offset = (int) $input->get('email_number_new', 0);
        $params = \Joomla\CMS\Component\ComponentHelper::getParams('com_userreminder');
        $batch  = (int) $params->get('number_email', 50);

        $stats = $send->processRegistrationReminders(true, $offset, $batch);

        $app->enqueueMessage(
            Text::sprintf(
                'COM_USERREMINDER_RUN_COMPLETE',
                $stats['processed'],
                $stats['sent'],
                $stats['deleted']
            ),
            'info'
        );

        $this->setRedirect(Route::_('index.php?option=com_userreminder&view=reminders', false));
    }

    /**
     * Send a test email to the configured test user.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public function sendTestMail(): void
    {
        $this->checkToken();

        /** @var \JoomCoder\Component\UserReminder\Administrator\Model\RemindersModel $model */
        $model = $this->getModel('Reminders');
        $model->sendTestMail();

        Factory::getApplication()->enqueueMessage(Text::_('COM_USERREMINDER_TEST_SENT'), 'info');

        $this->setRedirect(Route::_('index.php?option=com_userreminder&view=reminders', false));
    }
}