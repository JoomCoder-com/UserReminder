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
 * Activeusers controller — runs the existing-user inactivity pipeline.
 *
 * @since  4.0.0
 */
class ActiveusersController extends BaseController
{
    use RunTrait;

    /**
     * Send inactive-user reminders now.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public function sendReminders(): void
    {
        $this->runNow(true, 'activeusers');
    }

    /**
     * Send an inactive-user test email to the configured test user.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public function sendTestMail(): void
    {
        $this->sendTest([SendService::TYPE_INACTIVE_USER], 'activeusers');
    }
}
