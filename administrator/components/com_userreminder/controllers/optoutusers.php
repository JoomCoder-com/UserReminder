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
use Joomla\CMS\Factory;

defined('_JEXEC') or die;

/**
 * Note: this view is intended only to be opened in a popup
 *
 * @package     Joomla.Administrator
 * @subpackage  com_config
 * @since       1.5
 */
class UserreminderControllerOptoutUsers extends JControllerLegacy
{
	/**
	 * Class Constructor
	 *
	 * @param   array  $config		An optional associative array of configuration settings.
	 * @return  void
	 * @since   1.5
	 */
	public function __construct($config = array())
	{
		parent::__construct($config);

		// Map the apply task to the save method.
		 $this->registerTask('apply', 'save');
		 $this->registerTask('applyGroup', 'saveGroup');
	}
	
	function userlist($message = null){
		$sortColumn = \Joomla\CMS\Factory::getApplication()->input->get('filter_order','');
		$sortDirection =  \Joomla\CMS\Factory::getApplication()->input->get('filter_order_Dir','');
		
		// get the data
		$model = $this->getModel('optusers');
		$userList = $model->getUsersList($sortColumn, $sortDirection);
		$userPagination = $model->getPaginationForUser();
		
		// get the view and set the layout
		$view = $this->getView ('optoutusers','html');
		$view->setLayout('userlist');
		
		//assign variables to be used for the layout
		$view->sortColumn = $sortColumn;
		$view->sortDirection = $sortDirection;
		$view->message = $message;
		$view->userlist = $userList;
		$view->userPagination = $userPagination;
		
		$view->_display();
		
	}
	
	function usergroup($message = null){

        $input = Factory::getApplication()->input;

        $input->set('filter_search', '');
		\Joomla\CMS\MVC\Model\BaseDatabaseModel::addIncludePath (JPATH_ADMINISTRATOR . '/components/com_users/models');
		$groupsModel = \Joomla\CMS\MVC\Model\BaseDatabaseModel::getInstance('groups', 'usersModel');
		$groupList = $groupsModel->getItems();
		
		// get the data
		$model = $this->getModel('optusers');
		$optGroups = $model->getOptGroups();
		
		// set the view and layout
		$view = $this->getView ('optoutusers','html');
		$view->setLayout('usergroup');
		$view->message = $message;
		$view->optgroups = $optGroups;
		$view->groupList = $groupList;
		$view->_display();
	}

	/**
	 * Save the configuration
	 */
	public function save()
	{


        $cid = Factory::getApplication()->input->get('cid','','string');

		$model = $this->getModel('optusers');
		$message = null;
		
		if($model->addOptUsers($cid)){
			$message = \Joomla\CMS\Language\Text::_('USERREMINDER_OPTUSER_ADDED');
		} else {
			$message = \Joomla\CMS\Language\Text::_('USERREMINDER_OPTUSER_FAILED');
		}
		
        switch ($this->getTask())
        {
        	case 'apply':
        		$this->userlist($message);
        		break;
        	case 'save':
        	default:
        		 $redirect = 'index.php?option=com_userreminder';
        		 $this->setRedirect($redirect);
        		break;
        }
	}
	
	/**
	 * Save the configuration
	 */
	public function saveGroup(){

        $cid = Factory::getApplication()->input->get('cid',[],'array');
	
		$model = $this->getModel('optusers');
		$message = null;

		 
		if($model->saveUserGroup($cid)){
			$message = \Joomla\CMS\Language\Text::_('USERREMINDER_OPTGROUP_ADDED');
		} else {
			$message = \Joomla\CMS\Language\Text::_('USERREMINDER_OPTUSER_FAILED');
		}
	
		switch ($this->getTask())
		{
			case 'applyGroup':
				$this->usergroup($message);
				break;
			case 'saveGroup':
			default:
				$redirect = 'index.php?option=com_userreminder';
				$this->setRedirect($redirect);
				break;
		}
	}
	
	function help() {
		$view = $this->getView ( 'help','html');
		$view->_display();
	}
}
