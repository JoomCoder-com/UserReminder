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

class userreminderViewHelp extends \Joomla\CMS\MVC\View\HtmlView
{
	function _display($tpl = null){
		\Joomla\CMS\Toolbar\ToolbarHelper::title( \Joomla\CMS\Language\Text::_( 'USERREMINDER_TOOLBAR' ), 'cpanel' );

		$bar = JToolBar::getInstance('toolbar');
		\Joomla\CMS\Toolbar\ToolbarHelper::custom( 'displayReminders', 'users', '', \Joomla\CMS\Language\Text::_( 'USERREMINDER_INCOMPLETE_REGS' ), false, false );

		\Joomla\CMS\Toolbar\ToolbarHelper::custom( 'displayUserReminders', 'user', '', \Joomla\CMS\Language\Text::_( 'USERREMINDER_USER_REM' ), false, false );

		\Joomla\CMS\Toolbar\ToolbarHelper::custom( 'displayActionLog', 'clipboard', '', \Joomla\CMS\Language\Text::_( 'USERREMINDER_ACTION_LOG' ), false, false );


		$canDo   = ContentHelper::getActions('com_userreminder');
		$toolbar = Toolbar::getInstance();


		if ($canDo->get('core.admin')) {
			$toolbar->preferences('com_userreminder');
		}

		parent::display($tpl);



	}

}
?>
