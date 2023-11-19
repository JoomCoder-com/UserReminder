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


// Require the base controller
require_once (JPATH_COMPONENT.'/controller.php');

// Create the controller
$controller	= JControllerLegacy::getInstance('userreminder');
// Perform the Request task
$controller->execute(\Joomla\CMS\Factory::getApplication()->input->get('task', 'cpanel', 'default', 'cmd'));
$controller->redirect();
?>
