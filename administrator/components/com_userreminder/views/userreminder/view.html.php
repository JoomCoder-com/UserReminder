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

class userreminderViewUserreminder extends \Joomla\CMS\MVC\View\HtmlView
{

	public $itemlistexistinglogin, $paginationExistingUser;

	function _display($tpl = null){
		
		\Joomla\CMS\Toolbar\ToolbarHelper::title( \Joomla\CMS\Language\Text::_( 'USERREMINDER_TOOLBAR' ), 'cpanel' );
		//\Joomla\CMS\HTML\HTMLHelper::stylesheet(JURI::root().'administrator/components/com_userreminder/assets/css/userreminder.css', false, true, false);
		\Joomla\CMS\Toolbar\ToolbarHelper::custom('sendUserTestMail', 'envelope', '', \Joomla\CMS\Language\Text::_( 'USERREMINDER_SEND_TEST' ), false);
		\Joomla\CMS\Toolbar\ToolbarHelper::custom( 'sendUserReminder', 'envelope', '', \Joomla\CMS\Language\Text::_( 'USERREMINDER_USER_SEND' ), false, false );

		\Joomla\CMS\Toolbar\ToolbarHelper::custom( 'cpanel', 'home.png', 'home.png', \Joomla\CMS\Language\Text::_( 'USERREMINDER_BACK' ), false, false );
		
		parent::display($tpl);

	}

}
?>
