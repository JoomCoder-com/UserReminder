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


class userreminderViewoptoutusers extends \Joomla\CMS\MVC\View\HtmlView
{
	public $items;
	
	public $pagination;
	
	public $state;

	public $optusers, $userlist, $optgroups, $groupList, $userPagination, $message;
	public $sortColumn = '';
	public $sortDirection = '';

		function _display($tpl = null){
		
		if($this->getLayout() == 'userlist'){
			// toolbar button for user list
			\Joomla\CMS\Toolbar\ToolbarHelper::title( \Joomla\CMS\Language\Text::_( 'USERREMINDER_TOOLBAR_USER_LIST' ), 'cpanel' );
			\Joomla\CMS\Toolbar\ToolbarHelper::publishList('optoutusers.apply',  \Joomla\CMS\Language\Text::_('USERREMINDER_OPTUSER_ADD'));
			\Joomla\CMS\Toolbar\ToolbarHelper::publishList('optoutusers.save', \Joomla\CMS\Language\Text::_('USERREMINDER_OPTUSER_ADD_CLOSE'));
		} else if($this->getLayout() == 'usergroup') {
			\Joomla\CMS\Toolbar\ToolbarHelper::title( \Joomla\CMS\Language\Text::_( 'USERREMINDER_TOOLBAR_USER_GROUPS' ), 'cpanel' );
			\Joomla\CMS\Toolbar\ToolbarHelper::apply('optoutusers.applyGroup',  \Joomla\CMS\Language\Text::_('USERREMINDER_SAVE'));
			\Joomla\CMS\Toolbar\ToolbarHelper::save('optoutusers.saveGroup', \Joomla\CMS\Language\Text::_('USERREMINDER_SAVE_CLOSE'));
		} else {
			\Joomla\CMS\Toolbar\ToolbarHelper::title( \Joomla\CMS\Language\Text::_( 'USERREMINDER_TOOLBAR' ), 'cpanel' );
			\Joomla\CMS\Toolbar\ToolbarHelper::deleteList('', 'removeoptuser',  \Joomla\CMS\Language\Text::_('USERREMINDER_OPTUSER_REMOVE_BUTTON'));
		}
		
		\Joomla\CMS\Toolbar\ToolbarHelper::custom( 'cpanel', 'home.png', 'home.png', \Joomla\CMS\Language\Text::_( 'USERREMINDER_BACK' ), false, false );
		\Joomla\CMS\Toolbar\ToolbarHelper::custom( 'configuration.help', 'help.png', 'help.png', 'Help', false, false );
		
		parent::display($tpl);
	}
	
}
?>
