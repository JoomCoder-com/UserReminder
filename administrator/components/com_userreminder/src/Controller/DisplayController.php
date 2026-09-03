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

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;

/**
 * Base controller for com_userreminder. Default task = display.
 *
 * @since  4.0.0
 */
class DisplayController extends BaseController
{
    /**
     * @param   array                     $config
     * @param   MVCFactoryInterface|null  $factory
     * @param   AdministratorApplication|null $app
     * @param   \Joomla\CMS\Input\Input|null $input
     */
    public function __construct(
        $config = [],
        ?MVCFactoryInterface $factory = null,
        ?AdministratorApplication $app = null,
        ?\Joomla\CMS\Input\Input $input = null
    ) {
        parent::__construct($config, $factory, $app, $input);

        // Default landing page.
        $this->default_view = 'cpanel';
    }

    /**
     * Show the cpanel/dashboard view.
     *
     * @param   bool  $cachable
     * @param   array $urlparams
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public function cpanel($cachable = false, $urlparams = []): void
    {
        $this->input->set('view', 'cpanel');
        $this->display($cachable, $urlparams);
    }
}