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

class userreminderViewActionlog extends JViewLegacy
{

	public $itemlistactionlog;
	public $paginationActionLog;

	function _display($tpl = null){
		
		JToolBarHelper::title( JText::_( 'USERREMINDER_TOOLBAR' ), 'cpanel' );
		//JHTML::stylesheet(JURI::root().'administrator/components/com_userreminder/assets/css/userreminder.css', false, true, false);
        JToolBarHelper::deleteList(JText::_('USERREMINDER_DELETE_CONFIRM'), 'clearActionLog',  JText::_( 'USERREMINDER_CLEAR_LOG' ) );
		JToolBarHelper::custom( 'cpanel', 'home.png', 'home.png', JText::_( 'USERREMINDER_BACK' ), false, false );
		
		parent::display($tpl);
	}

}
?>
