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
 * ActiveUsers controller — runs the existing-user inactivity pipeline.
 *
 * @since  4.0.0
 */
class ActiveUsersController extends BaseController
{
    use AclTrait;

    public function sendReminders(): void
    {
        $this->checkToken();
        $this->requireAuthorised('core.manage');

        $app   = Factory::getApplication();
        $stats = SendService::instance()->processInactiveUserReminders(true, 0, 0);

        $app->enqueueMessage(
            Text::sprintf(
                'COM_USERREMINDER_RUN_COMPLETE',
                $stats['processed'],
                $stats['sent'],
                $stats['deleted']
            ),
            'info'
        );

        $this->setRedirect(Route::_('index.php?option=com_userreminder&view=activeusers', false));
    }

    public function sendTestMail(): void
    {
        $this->checkToken();
        $this->requireAuthorised('core.manage');

        $app = Factory::getApplication();

        $sent = SendService::instance()
            ->sendTestMail([SendService::TYPE_INACTIVE_USER]);

        $app->enqueueMessage(
            $sent ? Text::_('COM_USERREMINDER_TEST_SENT') : Text::_('COM_USERREMINDER_TEST_SEND_ERROR'),
            $sent ? 'info' : 'error'
        );

        $this->setRedirect(Route::_('index.php?option=com_userreminder&view=activeusers', false));
    }
}
