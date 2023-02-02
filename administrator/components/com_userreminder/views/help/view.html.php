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

class userreminderViewHelp extends JViewLegacy
{
	function _display($tpl = null){
		JToolBarHelper::title( JText::_( 'USERREMINDER_TOOLBAR' ), 'cpanel' );

		$bar = JToolBar::getInstance('toolbar');
		JToolBarHelper::custom( 'displayReminders', 'reguser1.png', 'reguser1.png', JText::_( 'USERREMINDER_INCOMPLETE_REGS' ), false, false );
		JToolBarHelper::custom( 'displayUserReminders', 'reguser2.png', 'reguser2.png', JText::_( 'USERREMINDER_USER_REM' ), false, false );
			//$bar->appendButton( 'Popup', 'regconfig', JText::_( 'USERREMINDER_PARA' ), 'index.php?option=com_userreminder&task=configuration', 600, 500 );
	    JToolBarHelper::custom( 'configuration', 'regconfig.png', 'regconfig.png', JText::_( 'USERREMINDER_PARA' ), false, false );
		JToolBarHelper::custom( 'displayActionLog', 'log.png', 'log.png', JText::_( 'USERREMINDER_ACTION_LOG' ), false, false );
		
		parent::display($tpl);
	}

}
?>
