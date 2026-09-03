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
    public function sendReminders(): void
    {
        $this->checkToken();

        /** @var SendService $send */
        $send = Factory::getContainer()->get(SendService::class);
        $stats = $send->processInactiveUserReminders(true, 0, 0);

        Factory::getApplication()->enqueueMessage(
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

        /** @var \JoomCoder\Component\UserReminder\Administrator\Model\ActiveUsersModel $model */
        $model = $this->getModel('ActiveUsers');
        $model->sendTestMail();

        Factory::getApplication()->enqueueMessage(Text::_('COM_USERREMINDER_TEST_SENT'), 'info');

        $this->setRedirect(Route::_('index.php?option=com_userreminder&view=activeusers', false));
    }
}