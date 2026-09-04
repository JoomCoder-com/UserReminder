<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_userreminder
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomCoder\Component\UserReminder\Site\View\Optout;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

/**
 * Site opt-out view. Read-only: it never writes, it only asks the model for
 * the current status and renders the matching layout.
 *
 * Layouts:
 * - default: confirmation card (or already/invalid state) with POST forms.
 * - result: outcome of a POST task, driven by the status query parameter
 *   after the controller's POST-redirect-GET.
 *
 * @since  4.0.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * Raw opt-out code from the request.
     *
     * @var  string
     *
     * @since  4.0.0
     */
    public $code = '';

    /**
     * Subscription status. One of: active, optedout, invalid (default layout)
     * or done, already, resubscribed, notoptedout, invalid (result layout).
     *
     * @var  string
     *
     * @since  4.0.0
     */
    public $status = 'invalid';

    public function display($tpl = null): void
    {
        $app   = Factory::getApplication();
        $input = $app->getInput();

        $this->code = trim((string) $input->get('uid', '', 'string'));

        /** @var \JoomCoder\Component\UserReminder\Site\Model\OptoutModel $model */
        $model = $this->getModel();

        if ($this->getLayout() === 'result') {
            $status = (string) $input->get('status', '', 'cmd');

            $allowed = ['done', 'already', 'resubscribed', 'notoptedout', 'invalid'];

            $this->status = \in_array($status, $allowed, true) ? $status : 'invalid';
        } else {
            $this->status = $this->code === '' ? 'invalid' : $model->getStatus($this->code);
        }

        $app->getDocument()->setTitle($app->getLanguage()->_('COM_USERREMINDER_OPTOUT_TITLE'));

        parent::display($tpl);
    }
}
