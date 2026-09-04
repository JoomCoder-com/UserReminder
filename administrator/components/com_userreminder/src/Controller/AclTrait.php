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

/**
 * Shared ACL gate for state-changing tasks in com_userreminder.
 *
 * @since  4.2.0
 */
trait AclTrait
{
    /**
     * Require the given action, throwing a 403 when not authorised.
     *
     * @param   string  $action  ACL action key, e.g. 'core.delete'.
     *
     * @return  void
     *
     * @throws  \Exception  When the current user is not authorised.
     *
     * @since   4.2.0
     */
    protected function requireAuthorised(string $action): void
    {
        $user = Factory::getApplication()->getIdentity();

        if (!$user || !$user->authorise($action, 'com_userreminder')) {
            throw new \Exception(Text::_('COM_USERREMINDER_ERROR_ACCESS_DENIED'), 403);
        }
    }
}
