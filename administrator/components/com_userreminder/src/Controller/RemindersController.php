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

use JoomCoder\Component\UserReminder\Administrator\Service\SendService;
use Joomla\CMS\MVC\Controller\BaseController;

/**
 * Reminders controller — runs the registration-reminder pipeline and reports
 * back to the user.
 *
 * @since  4.0.0
 */
class RemindersController extends BaseController
{
    use RunTrait;

    /**
     * Send registration reminders (types 1 + 2).
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public function sendReminders(): void
    {
        $this->runNow(false, 'reminders');
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
        $this->sendTest([SendService::TYPE_NOT_ACTIVATED, SendService::TYPE_NEVER_LOGGED], 'reminders');
    }
}
