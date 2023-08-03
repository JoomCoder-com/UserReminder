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
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;

defined('_JEXEC') or die('Restricted access');

jimport( 'joomla.application.component.view' );

class userreminderViewHelp extends JViewLegacy
{
	function _display($tpl = null){
		JToolBarHelper::title( JText::_( 'USERREMINDER_TOOLBAR' ), 'cpanel' );

		$bar = JToolBar::getInstance('toolbar');
		JToolBarHelper::custom( 'displayReminders', 'users', '', JText::_( 'USERREMINDER_INCOMPLETE_REGS' ), false, false );

		JToolBarHelper::custom( 'displayUserReminders', 'user', '', JText::_( 'USERREMINDER_USER_REM' ), false, false );

		JToolBarHelper::custom( 'displayActionLog', 'clipboard', '', JText::_( 'USERREMINDER_ACTION_LOG' ), false, false );


		$canDo   = ContentHelper::getActions('com_userreminder');
		$toolbar = Toolbar::getInstance();


		if ($canDo->get('core.admin')) {
			$toolbar->preferences('com_userreminder');
		}

		parent::display($tpl);



	}

}
?>
