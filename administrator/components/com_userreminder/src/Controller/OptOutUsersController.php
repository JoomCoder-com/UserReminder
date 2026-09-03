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
 * OptOutUsers controller — handles opt-out user pickers and group pickers.
 *
 * @since  4.0.0
 */
class OptOutUsersController extends BaseController
{
    /**
     * Save the picked users to the opt-out list.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public function save(): void
    {
        $this->checkToken();

        $app  = Factory::getApplication();
        $cid  = (array) $app->getInput()->get('cid', [], 'array');

        /** @var \JoomCoder\Component\UserReminder\Administrator\Model\OptOutUsersModel $model */
        $model = $this->getModel('OptOutUsers');

        if ($model->addOptUsers($cid)) {
            $app->enqueueMessage(Text::_('COM_USERREMINDER_OPTUSER_ADDED'), 'success');
        } else {
            $app->enqueueMessage(Text::_('COM_USERREMINDER_OPTUSER_FAILED'), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_userreminder&view=optoutusers&layout=userlist', false));
    }

    /**
     * Remove the picked users from the opt-out list.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public function remove(): void
    {
        $this->checkToken();

        $app  = Factory::getApplication();
        $cid  = (array) $app->getInput()->get('cid', [], 'array');

        /** @var \JoomCoder\Component\UserReminder\Administrator\Model\OptOutUsersModel $model */
        $model = $this->getModel('OptOutUsers');

        if ($model->removeOptUsers($cid)) {
            $app->enqueueMessage(Text::_('COM_USERREMINDER_OPTUSER_REMOVE'), 'success');
        } else {
            $app->enqueueMessage(Text::_('COM_USERREMINDER_OPTUSER_FAILED'), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_userreminder&view=optoutusers', false));
    }

    /**
     * Save the picked user-groups.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public function saveGroup(): void
    {
        $this->checkToken();

        $app  = Factory::getApplication();
        $cid  = (array) $app->getInput()->get('cid', [], 'array');

        /** @var \JoomCoder\Component\UserReminder\Administrator\Model\OptOutUsersModel $model */
        $model = $this->getModel('OptOutUsers');

        if ($model->saveOptGroups($cid)) {
            $app->enqueueMessage(Text::_('COM_USERREMINDER_OPTGROUP_ADDED'), 'success');
        } else {
            $app->enqueueMessage(Text::_('COM_USERREMINDER_OPTUSER_FAILED'), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_userreminder&view=optoutusers', false));
    }
}