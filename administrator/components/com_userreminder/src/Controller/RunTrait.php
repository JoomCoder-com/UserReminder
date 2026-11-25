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

use JoomCoder\Component\UserReminder\Administrator\Extension\UserReminderComponent;
use JoomCoder\Component\UserReminder\Administrator\Service\MailDebug;
use JoomCoder\Component\UserReminder\Administrator\Service\ReminderOptions;
use JoomCoder\Component\UserReminder\Administrator\Service\SendService;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/**
 * "Send now" and "Send test email" toolbar tasks, shared by the dashboard and
 * the two reminder lists. Each task checks the token and core.manage, runs,
 * reports the result as a message and returns to $view.
 *
 * @since  6.1.0
 */
trait RunTrait
{
    use AclTrait;

    /**
     * Run the registration (types 1 + 2) or inactive-user pipeline now.
     *
     * @param   bool    $inactive  true = inactive-user reminders.
     * @param   string  $view      View to return to.
     *
     * @return  void
     *
     * @since  6.1.0
     */
    private function runNow(bool $inactive, string $view): void
    {
        $this->checkToken();
        $this->requireAuthorised('core.manage');
        $this->setRedirect(Route::_('index.php?option=com_userreminder&view=' . $view, false));

        $app     = Factory::getApplication();
        $service = UserReminderComponent::service();

        if (!$inactive) {
            $app->enqueueMessage($this->runMessage($service->processRegistrationReminders()), 'info');

            return;
        }

        // Switched off in Options: say so instead of "Processed 0".
        if (!ReminderOptions::fromComponent()->typeEnabled(SendService::TYPE_INACTIVE_USER)) {
            $app->enqueueMessage(Text::_('COM_USERREMINDER_INACTIVE_OFF'), 'warning');

            return;
        }

        $app->enqueueMessage($this->runMessage($service->processInactiveUserReminders()), 'info');
    }

    /**
     * Send one test email per type to the configured test user.
     *
     * @param   int[]   $types  SendService::TYPE_* constants.
     * @param   string  $view   View to return to.
     *
     * @return  void
     *
     * @since  6.1.0
     */
    private function sendTest(array $types, string $view): void
    {
        $this->checkToken();
        $this->requireAuthorised('core.manage');
        $this->setRedirect(Route::_('index.php?option=com_userreminder&view=' . $view, false));

        $sent = UserReminderComponent::service()->sendTestMail($types);

        if (!$sent) {
            $message = Text::_('COM_USERREMINDER_TEST_SEND_ERROR');
        } elseif (MailDebug::enabled()) {
            $message = Text::_('COM_USERREMINDER_TEST_SIMULATED');
        } else {
            $message = Text::_('COM_USERREMINDER_TEST_SENT');
        }

        Factory::getApplication()->enqueueMessage($message, $sent ? 'info' : 'error');
    }

    /**
     * Human message for a pipeline run (real or simulated).
     *
     * @param   array  $stats  SendService run counters.
     *
     * @return  string
     *
     * @since  6.1.0
     */
    private function runMessage(array $stats): string
    {
        if (!empty($stats['dryRun'])) {
            return Text::sprintf('COM_USERREMINDER_RUN_SIMULATED', $stats['processed'], $stats['simulated']);
        }

        $message = Text::sprintf('COM_USERREMINDER_RUN_COMPLETE', $stats['processed'], $stats['sent'], $stats['deleted']);

        if (!empty($stats['errors'])) {
            $message .= ' ' . Text::sprintf('COM_USERREMINDER_RUN_ERRORS', (int) $stats['errors']);
        }

        return $message;
    }
}
