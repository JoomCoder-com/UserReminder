<?php
/**
 * @version		UserReminder v1.0
 * @package		userreminder
 * @copyright	Copyright � 2021 - JoomCoder - All rights reserved.
 * @license - http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 * @author		JoomCoder
 * @author mail	support@joomcoder.com
 * @website		www.joomcoder.com
 */
 
// no direct access
defined('_JEXEC') or die('Restricted access');

// load user reminder libraries
require_once JPATH_ADMINISTRATOR.'/components/com_userreminder/libraries/vendor/autoload.php';

// Require the base controller
require_once (JPATH_COMPONENT.'/controller.php');

// Create the controller
$controller	= \Joomla\CMS\MVC\Controller\BaseController::getInstance('userreminder');
// Perform the Request task
$controller->execute(\Joomla\CMS\Factory::getApplication()->input->get('task', 'cpanel', 'default', 'cmd'));
$controller->redirect();
