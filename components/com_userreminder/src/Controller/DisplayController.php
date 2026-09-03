<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_userreminder
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomCoder\Component\UserReminder\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;

/**
 * Default site controller for com_userreminder.
 *
 * Handles both the opt-out confirmation prompt (display) and the commit
 * action (optoutnow) so URLs of the form
 *   index.php?option=com_userreminder&task=optoutnow&uid=CODE
 * work without a controller prefix (Joomla maps a bare task to the Display
 * controller).
 *
 * The dedicated OptoutController is kept for BC where callers use
 * task=optout.optoutnow.
 *
 * @since  4.0.0
 */
class DisplayController extends BaseController
{
    public function display($cachable = false, $urlparams = []): void
    {
        $app   = Factory::getApplication();
        $input = $app->getInput();
        $uid   = (string) $input->get('uid', '', 'string');

        // If a view is already set (e.g. view=optout), respect it; otherwise
        // default to optout when a uid is present, otherwise let the component
        // show its default (which is optout per our routing).
        $view = (string) $input->get('view', '', 'string');
        if ($view === '' && $uid !== '') {
            $view = 'optout';
        }
        if ($view !== '') {
            $this->input->set('view', $view);
        } elseif ($uid !== '') {
            $this->input->set('view', 'optout');
        }

        if ($uid !== '') {
            $this->input->set('uid', $uid);
        }

        $this->input->set('hidemainmenu', 0);

        if ($view === 'optout' || $uid !== '') {
            $app->getDocument()->setTitle($app->getLanguage()->_('COM_USERREMINDER_OPTOUT_TITLE'));
        }

        parent::display($cachable, $urlparams);
    }

    public function optoutnow(): void
    {
        $this->input->set('view', 'optout');
        $this->input->set('layout', 'result');
        $this->input->set('hidemainmenu', 0);

        parent::display();
    }
}
