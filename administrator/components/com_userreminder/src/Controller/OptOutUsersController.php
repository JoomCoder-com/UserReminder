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
    use AclTrait;

    /**
     * Add the staged users (from the "Select Users" toolbar modal) to the opt-out list.
     *
     * Staged picks arrive as userids[] so they never collide with the list
     * cid[] checkboxes used by the remove task.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public function save(): void
    {
        $this->checkToken();
        $this->requireAuthorised('core.manage');

        $app     = Factory::getApplication();
        $userIds = (array) $app->getInput()->get('userids', [], 'array');

        if (empty($userIds)) {
            // Fallback for direct posts using the list checkbox name.
            $userIds = (array) $app->getInput()->get('cid', [], 'array');
        }

        /** @var \JoomCoder\Component\UserReminder\Administrator\Model\OptOutUsersModel $model */
        $model = $this->getModel('OptOutUsers');

        $added = $model->addOptUsers($userIds);

        if ($added > 0) {
            $app->enqueueMessage(Text::sprintf('COM_USERREMINDER_OPTUSER_ADDED_N', $added), 'success');
        } else {
            $app->enqueueMessage(Text::_('COM_USERREMINDER_OPTUSER_FAILED'), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_userreminder&view=optoutusers', false));
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
        $this->requireAuthorised('core.delete');

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
        $this->requireAuthorised('core.manage');

        $app  = Factory::getApplication();
        $cid  = (array) $app->getInput()->get('cid', [], 'array');

        /** @var \JoomCoder\Component\UserReminder\Administrator\Model\OptOutUsersModel $model */
        $model = $this->getModel('OptOutUsers');

        if ($model->saveOptGroups($cid)) {
            $app->enqueueMessage(Text::_('COM_USERREMINDER_OPTGROUP_ADDED'), 'success');
        } else {
            $app->enqueueMessage(Text::_('COM_USERREMINDER_OPTUSER_FAILED'), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_userreminder&view=optoutusers&layout=usergroup', false));
    }
}
