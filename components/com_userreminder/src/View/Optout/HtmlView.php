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
use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Uri\Uri;

/**
 * Site opt-out view. Two layouts: default (confirm prompt) and result (success/failure).
 *
 * @since  4.0.0
 */
class HtmlView extends HtmlView
{
    /**
     * @var  string
     *
     * @since  4.0.0
     */
    public $code = '';

    /**
     * @var  bool
     *
     * @since  4.0.0
     */
    public $success = false;

    /**
     * @var  string
     *
     * @since  4.0.0
     */
    public $confirmUrl = '';

    public function display($tpl = null): void
    {
        $app   = Factory::getApplication();
        $input = $app->getInput();

        $this->code = (string) $input->get('uid', '', 'string');

        if ($this->getLayout() === 'result') {
            /** @var \JoomCoder\Component\UserReminder\Site\Model\OptoutModel $model */
            $model = $this->getModel();
            $this->success = $model->optOut($this->code);
        } else {
            $this->confirmUrl = Uri::current() . '?' . http_build_query([
                'option' => 'com_userreminder',
                'task'   => 'optoutnow',
                'uid'    => $this->code,
            ]);
        }

        parent::display($tpl);
    }
}