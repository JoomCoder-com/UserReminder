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

jimport( 'joomla.application.component.view' );

class userreminderViewReminder extends JViewLegacy
{
	public $pagination, $itemlist, $itemlistlogin, $pagination2;
	
	function _display($tpl = null){
		
		JToolBarHelper::title( JText::_( 'USERREMINDER_TOOLBAR' ), 'cpanel' );
		//JHTML::stylesheet(JURI::root().'administrator/components/com_userreminder/assets/css/userreminder.css', false, true, false);
		JToolbarHelper::custom('sendTestMail', 'envelope', 'send_f2.png', JText::_( 'USERREMINDER_SEND_TEST' ), false);
		JToolBarHelper::custom( 'sendReminder', 'envelope', 'go.png', JText::_( 'USERREMINDER_USER_SEND' ), false, false );
		JToolBarHelper::custom( 'cpanel', 'home.png', 'home.png', JText::_( 'USERREMINDER_BACK' ), false, false );
		parent::display($tpl);

	}

}
?>
