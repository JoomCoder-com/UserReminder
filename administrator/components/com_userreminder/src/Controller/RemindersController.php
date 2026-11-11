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
use JoomCoder\Component\UserReminder\Administrator\Extension\UserReminderComponent;
use JoomCoder\Component\UserReminder\Administrator\Service\MailDebug;
use JoomCoder\Component\UserReminder\Administrator\Service\SendService;

/**
 * Reminders controller — runs the registration-reminder pipeline and reports
 * back to the user.
 *
 * @since  4.0.0
 */
class RemindersController extends BaseController
{
    use AclTrait;

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
        $this->requireAuthorised('core.manage');

        $app    = Factory::getApplication();
        $input  = $app->getInput();
        $offset = (int) $input->get('email_number_new', 0);

        $stats = UserReminderComponent::service()->processRegistrationReminders(true, $offset, 0);

        $app->enqueueMessage(
            $this->runMessage($stats),
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
        $this->requireAuthorised('core.manage');

        $app = Factory::getApplication();

        $sent = UserReminderComponent::service()
            ->sendTestMail([SendService::TYPE_NOT_ACTIVATED, SendService::TYPE_NEVER_LOGGED]);

        $app->enqueueMessage(
            $this->testMessage($sent),
            $sent ? 'info' : 'error'
        );

        $this->setRedirect(Route::_('index.php?option=com_userreminder&view=reminders', false));
    }

    /**
     * Human message for a pipeline run (real or simulated).
     *
     * @param   array  $stats
     *
     * @return  string
     *
     * @since  6.1.0
     */
    private function runMessage(array $stats): string
    {
        if (!empty($stats['dryRun'])) {
            return Text::sprintf(
                'COM_USERREMINDER_RUN_SIMULATED',
                $stats['processed'],
                $stats['simulated'] ?? 0
            );
        }

        return Text::sprintf(
            'COM_USERREMINDER_RUN_COMPLETE',
            $stats['processed'],
            $stats['sent'],
            $stats['deleted']
        ) . (!empty($stats['errors'])
            ? ' ' . Text::sprintf('COM_USERREMINDER_RUN_ERRORS', (int) $stats['errors'])
            : '');
    }

    /**
     * Human message for a test-email attempt (real or simulated).
     *
     * @param   bool  $sent
     *
     * @return  string
     *
     * @since  6.1.0
     */
    private function testMessage(bool $sent): string
    {
        if ($sent && MailDebug::enabled()) {
            return Text::_('COM_USERREMINDER_TEST_SIMULATED');
        }

        return $sent ? Text::_('COM_USERREMINDER_TEST_SENT') : Text::_('COM_USERREMINDER_TEST_SEND_ERROR');
    }
}
