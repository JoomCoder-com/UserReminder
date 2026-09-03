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

/**
 * Log controller — only one custom task: clear the log.
 *
 * @since  4.0.0
 */
class LogController extends BaseController
{
    public function clear(): void
    {
        $this->checkToken();

        /** @var \JoomCoder\Component\UserReminder\Administrator\Model\LogModel $model */
        $model = $this->getModel('Log');
        $model->clear();

        Factory::getApplication()->enqueueMessage(Text::_('COM_USERREMINDER_LOG_CLEARED'), 'success');

        $this->setRedirect(Route::_('index.php?option=com_userreminder&view=log', false));
    }
}