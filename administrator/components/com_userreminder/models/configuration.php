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

jimport( 'joomla.application.component.model' );

class userreminderModelConfiguration extends JModelLegacy {
	function getParamData($userDataArray, $defaultDataArray) {
		$outputArray = array();
		foreach (array_keys($defaultDataArray) as $key) {
			if (isset($userDataArray[$key])) {
				if ($userDataArray[$key] == '' || $userDataArray[$key] == NULL) {
					$outputArray[$key] = $defaultDataArray[$key];
				} else {
					$outputArray[$key] = $userDataArray[$key];
				}
			} else {
				$outputArray[$key] = $defaultDataArray[$key];
			}
			$outputArray[$key] = str_replace("<br />", "
	", $outputArray[$key]);
		}
		return $outputArray;
	}

	function getP(){
		$finalData = $this->getParamData($usersConfig, $defualValues);
		echo '<pre>'; print_r($finalData);
	}
	function getParams()
	{
		$table =& JTable::getInstance('extensions');
		$table->loadByOption( 'com_userreminder' );

		$params = array();

		// *************************************************************************************
		// General parameters
			// *************************************************************************************
			$path	= JPATH_ADMINISTRATOR.DS.'components'.DS.'com_userreminder'.DS.'config.xml';
			$params['general'] = new JParameter( $table->params, $path );
			
			// *************************************************************************************
		// Activation reminders parameters
			// *************************************************************************************
		$path	= JPATH_ADMINISTRATOR.DS.'components'.DS.'com_userreminder'.DS.'configactivate.xml';
			$params['activate'] = new JParameter( $table->params, $path );
		// check to see if a custsom value has been set. If not use the value from the language file
		if ($params['activate']->get('regActivationEmailBody')=="")
		{
		  $params['activate']->set('regActivationEmailBody',JText::_('USERREMINDER_SEND_MSG_REMINDER'));
		}
		if ($params['activate']->get('regActivationEmailSubject')=="")
		{
		  $params['activate']->set('regActivationEmailSubject',JText::_('USERREMINDER_REMINDER_DETAILS_FOR'));
		}

			// *************************************************************************************
		// Login reminders parameters
			// *************************************************************************************
		$path	= JPATH_ADMINISTRATOR.DS.'components'.DS.'com_userreminder'.DS.'configlogin.xml';
			$params['login'] = new JParameter( $table->params, $path );
		
		// check to see if a custsom value has been set. If not use the value from the language file
		if ($params['login']->get('regLoginEmailBody')=="")
		{
		  $params['login']->set('regLoginEmailBody',JText::_('USERREMINDER_SEND_MSG_LOGINREMINDER'));
		}
		if ($params['login']->get('regLoginEmailSubject')=="")
		{
		  $params['login']->set('regLoginEmailSubject',JText::_('USERREMINDER_LOGINREMINDER_DETAILS_FOR'));
		}

		// *************************************************************************************
		// Existing User reminders parameters
		// *************************************************************************************
		$path	= JPATH_ADMINISTRATOR.DS.'components'.DS.'com_userreminder'.DS.'configexistinguser.xml';
		$params['existinguser'] = new JParameter( $table->params, $path );
		
		// check to see if a custsom value has been set. If not use the value from the language file
		if ($params['existinguser']->get('regExistingUserEmailBody')=="")
		{
		  $params['existinguser']->set('regExistingUserEmailBody',JText::_('USERREMINDER_SEND_MSG_EXISTINGUSERREMINDER'));
		}
		if ($params['existinguser']->get('regExistingUserEmailSubject')=="")
		{
		  $params['existinguser']->set('regExistingUserEmailSubject',JText::_('USERREMINDER_EXISTINGUSERREMINDER_DETAILS_FOR'));
		}

		return $params;
	}	
	
	
	function getAllParams(){
		$table =& JTable::getInstance('component');
		$table->loadByOption( 'com_userreminder' );
		$params = array();
		
		// *************************************************************************************
		// General parameters
		// *************************************************************************************
		$path	= JPATH_ADMINISTRATOR.DS.'components'.DS.'com_userreminder'.DS.'config.xml';
		$params['general'] = new JParameter( $table->params, $path );
			
		// *************************************************************************************
		// Activation reminders parameters
		// *************************************************************************************
		$params['activate'] = new JParameter( $table->params, $path );
		// check to see if a custsom value has been set. If not use the value from the language file
		if ($params['activate']->get('regActivationEmailBody')=="")
		{
		  $params['activate']->set('regActivationEmailBody',JText::_('USERREMINDER_SEND_MSG_REMINDER'));
		}
		if ($params['activate']->get('regActivationEmailSubject')=="")
		{
		  $params['activate']->set('regActivationEmailSubject',JText::_('USERREMINDER_REMINDER_DETAILS_FOR'));
		}
		$params['activate'] = $params['activate']->_registry['_default']['data'];
		
		// *************************************************************************************
		// Login reminders parameters
		// *************************************************************************************		
		$params['login'] = new JParameter( $table->params, $path );
		
		// check to see if a custsom value has been set. If not use the value from the language file
		if ($params['login']->get('regLoginEmailBody')=="")
		{
		  $params['login']->set('regLoginEmailBody',JText::_('USERREMINDER_SEND_MSG_LOGINREMINDER'));
		}
		if ($params['login']->get('regLoginEmailSubject')=="")
		{
		  $params['login']->set('regLoginEmailSubject',JText::_('USERREMINDER_LOGINREMINDER_DETAILS_FOR'));
		}
		$params['login'] = $params['login']->_registry['_default']['data'];
		
		// *************************************************************************************
		// Existing User reminders parameters
		// *************************************************************************************
		$params['existinguser'] = new JParameter( $table->params, $path );		
		// check to see if a custsom value has been set. If not use the value from the language file
		if ($params['existinguser']->get('regExistingUserEmailBody')=="")
		{
		  $params['existinguser']->set('regExistingUserEmailBody',JText::_('USERREMINDER_SEND_MSG_EXISTINGUSERREMINDER'));
		}
		if ($params['existinguser']->get('regExistingUserEmailSubject')=="")
		{
		  $params['existinguser']->set('regExistingUserEmailSubject',JText::_('USERREMINDER_EXISTINGUSERREMINDER_DETAILS_FOR'));
		}
		
		$params['existinguser'] = $params['existinguser']->_registry['_default']['data'];
		
		return $params;
	}
	
	public function save()
	{
		// Check for request forgeries
		JRequest::checkToken() or die('Invalid Token');
		$post = JRequest::get('post');
		$postRaw = $post['params'];
		$count = sizeof($postRaw);
		$counter = 0;
		$jsondata = "{";
	
		foreach (array_keys($postRaw) as $key) {
			$counter++;
			if ($counter == $count) {
				$jsondata .= '"' . $key . '":"' . htmlentities($postRaw[$key]) . '"';
			} else {
				$jsondata .= '"' . $key . '":"' . htmlentities($postRaw[$key]) . '",';
			}
		}
	
		$jsondata .= "}";

		//$jsondata = str_replace("\r\n", "<br />", $jsondata);
		$db = JFactory::getDbo();
		$jsondata = $db->escape($jsondata);
		$query = "update #__extensions set params='" . $jsondata . "' WHERE element='com_userreminder'";
		$db->setQuery($query);
		if (!$db->query()) {
			//throw new Exception($db->getErrorMsg());
			JError::raiseWarning(500, $db->getErrorMsg());
			return false;
		}
		// now return to the main page
		//$view = $this->getView ( 'cpanel','html');
		//$view->_display();
		$redirect = 'index.php?option=com_userreminder';
		$this->setRedirect($redirect);
	
	}
}
?>
