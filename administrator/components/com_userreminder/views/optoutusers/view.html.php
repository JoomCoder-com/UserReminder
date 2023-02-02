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


class userreminderViewoptoutusers extends JViewLegacy
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
			JToolBarHelper::title( JText::_( 'USERREMINDER_TOOLBAR_USER_LIST' ), 'cpanel' );
			JToolbarHelper::publishList('optoutusers.apply',  JText::_('USERREMINDER_OPTUSER_ADD'));
			JToolbarHelper::publishList('optoutusers.save', JText::_('USERREMINDER_OPTUSER_ADD_CLOSE'));
		} else if($this->getLayout() == 'usergroup') {
			JToolBarHelper::title( JText::_( 'USERREMINDER_TOOLBAR_USER_GROUPS' ), 'cpanel' );
			JToolbarHelper::apply('optoutusers.applyGroup',  JText::_('USERREMINDER_SAVE'));
			JToolbarHelper::save('optoutusers.saveGroup', JText::_('USERREMINDER_SAVE_CLOSE'));
		} else {
			JToolBarHelper::title( JText::_( 'USERREMINDER_TOOLBAR' ), 'cpanel' );
			JToolbarHelper::deleteList('', 'removeoptuser',  JText::_('USERREMINDER_OPTUSER_REMOVE_BUTTON'));
		}
		
		JToolBarHelper::custom( 'cpanel', 'home.png', 'home.png', JText::_( 'USERREMINDER_BACK' ), false, false );
		JToolBarHelper::custom( 'configuration.help', 'help.png', 'help.png', 'Help', false, false );
		
		parent::display($tpl);
	}
	
}
?>
