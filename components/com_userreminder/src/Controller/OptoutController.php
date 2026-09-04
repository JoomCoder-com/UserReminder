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
use Joomla\CMS\Router\Route;

/**
 * Site controller for the opt-out flow.
 *
 * Canonical URL (as generated in reminder emails):
 *   index.php?option=com_userreminder&view=optout&uid=CODE
 *
 * Writes only happen here, on POST with a valid session token, followed by a
 * redirect (POST-redirect-GET) so bots, prefetchers and refreshes can never
 * trigger a state change:
 *   task=optout.unsubscribe  -> optOut()  -> layout=result&status=done|already|invalid
 *   task=optout.resubscribe  -> optIn()   -> layout=result&status=resubscribed|notoptedout|invalid
 *
 * @since  4.0.0
 */
class OptoutController extends BaseController
{
    /**
     * Display the confirmation prompt.
     *
     * @param   bool   $cachable   Ignored.
     * @param   array  $urlparams  Ignored.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public function display($cachable = false, $urlparams = []): void
    {
        $this->input->set('view', 'optout');

        Factory::getApplication()->getDocument()
            ->setTitle(Factory::getApplication()->getLanguage()->_('COM_USERREMINDER_OPTOUT_TITLE'));

        parent::display($cachable, $urlparams);
    }

    /**
     * Commit the opt-out on POST, then redirect to the result layout.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public function unsubscribe(): void
    {
        $this->checkToken('post');

        $code = trim((string) $this->input->get('uid', '', 'string'));

        /** @var \JoomCoder\Component\UserReminder\Site\Model\OptoutModel $model */
        $model = $this->getModel('Optout');

        $status = $model->optOut($code);

        $this->setRedirect(
            Route::_(
                'index.php?option=com_userreminder&view=optout&layout=result&status=' . $status . '&uid=' . urlencode($code),
                false
            )
        );
    }

    /**
     * Remove the opt-out on POST (resubscribe), then redirect to the result layout.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public function resubscribe(): void
    {
        $this->checkToken('post');

        $code = trim((string) $this->input->get('uid', '', 'string'));

        /** @var \JoomCoder\Component\UserReminder\Site\Model\OptoutModel $model */
        $model = $this->getModel('Optout');

        $status = $model->optIn($code);

        $this->setRedirect(
            Route::_(
                'index.php?option=com_userreminder&view=optout&layout=result&status=' . $status . '&uid=' . urlencode($code),
                false
            )
        );
    }
}
