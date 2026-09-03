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
 * Site controller for the opt-out flow.
 *
 * URL pattern in reminder emails: index.php?option=com_userreminder&task=optoutnow&uid=CODE
 * and a SEF variant: /component/userreminder/optout/CODE/confirm
 *
 * @since  4.0.0
 */
class OptoutController extends BaseController
{
    /**
     * Display the confirmation prompt.
     *
     * @param   bool  $cachable  ignored (POST-only behaviour)
     * @param   array $urlparams ignored
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public function display($cachable = false, $urlparams = []): void
    {
        $app   = Factory::getApplication();
        $input = $app->getInput();
        $uid   = (string) $input->get('uid', '', 'string');

        // Hand off to the view which renders the alert with confirm / cancel buttons.
        $app->getDocument()->setTitle($app->getLanguage()->_('COM_USERREMINDER_OPTOUT_TITLE'));

        $this->input->set('view', 'optout');
        $this->input->set('uid', $uid);
        $this->input->set('hidemainmenu', 0);

        parent::display($cachable, $urlparams);
    }

    /**
     * Commit the opt-out: insert into #__userreminder_optout, show success/failure.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public function optoutnow(): void
    {
        $this->input->set('view', 'optout');
        $this->input->set('layout', 'result');
        $this->input->set('hidemainmenu', 0);

        parent::display();
    }
}