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

use Joomla\CMS\MVC\Controller\BaseController;

/**
 * Default site controller for com_userreminder.
 *
 * Only defaults an empty request to the optout view. All writes live in
 * OptoutController::unsubscribe() / ::resubscribe() on POST tasks
 * (task=optout.unsubscribe, task=optout.resubscribe).
 *
 * @since  4.0.0
 */
class DisplayController extends BaseController
{
    public function display($cachable = false, $urlparams = []): void
    {
        if ((string) $this->input->get('view', '', 'cmd') === '') {
            $this->input->set('view', 'optout');
        }

        parent::display($cachable, $urlparams);
    }
}
