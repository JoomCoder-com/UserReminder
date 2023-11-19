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

defined('_JEXEC') or die('Restricted access');
jimport('joomla.application.component.controller');
jimport( 'joomla.application.component.helper' );

class userreminderController extends \Joomla\CMS\MVC\Controller\BaseController {
    function __construct() {
        parent::__construct();
        $this->registerTask('applyList', 'saveList');
        $this->registerTask('applyGroup', 'saveGroup');
    }
    function cpanel() {
        $view = $this->getView ( 'cpanel','html');
        $view->_display();
    }
    function help() {
        $view = $this->getView ( 'help','html');
        $view->_display();
    }
    function displayReminders() {
        //echo 'T='.JRequest::getVar('limitstart', 0, '', 'int');
        //echo 'T='.JRequest::getVar('limitstart2', 0, '', 'int');
        //$model = &$this->getModel('userreminder');
        $model = $this->getModel('reminder');
        $view	= $this->getView ( 'reminder','html');
        // Get the incomplete registrations
        $results = $model->showuserReminder();


        $view->itemlist = $results;
        // Get the registered user who have never logged in, but have activated their registration
        $resultslogin = $model->showloginReminder();

        $view->itemlistlogin = $resultslogin;
        // pagination for first tab
        $pagination = $model->getPagination();

        $view->pagination = $pagination;
        // pagination for second tab
        $pagination2 = $model->getPagination2();

        $view->pagination2 = $pagination2;

        $view->_display();
    }
    function displayUserReminders() {
        //$model = &$this->getModel('userreminder');
        $model = $this->getModel('userreminder');
        $view	= $this->getView ( 'userreminder','html');
        // Get the registered user who have not logged in for x number of days
        $resultsexistinglogin = $model->showexistinguserReminder();

        $view->itemlistexistinglogin = $resultsexistinglogin;
        
		// pagination
        $pagination = $model->getPaginationExistingUser();

        $view->paginationExistingUser = $pagination;
		
        $view->_display();
    }
    function displayActionLog() {
        //$model = &$this->getModel('actionlog');
        $model = $this->getModel('actionlog');
        $view	= $this->getView ( 'actionlog','html');
        $resultsactionlog = $model->showActionLog();
        $view->itemlistactionlog = $resultsactionlog;
        // pagination
        $pagination = $model->getPaginationActionLog();
        $view->paginationActionLog = $pagination;
        $view->_display();
    }
    function clearActionLog() {
        //$model = &$this->getModel('actionlog');
        $model = $this->getModel('actionlog');
        $view	= $this->getView ( 'actionlog','html');
        $removeLog = $model->clearActionLog();
        $resultsactionlog = $model->showActionLog();
        $view->itemlistactionlog = $resultsactionlog;
        // pagination
        $pagination = $model->getPaginationActionLog();
        $view->paginationActionLog = $pagination;
        $view->_display();
    }
    function sendTestMail() {
        //$model = &$this->getModel('userreminder');
        $model = $this->getModel('reminder');
        $view	= $this->getView ( 'reminder','html');
        $model->sendTestMail();
        // Get the incomplete registrations
        $results = $model->showuserReminder();
        $view->itemlist = $results;
        // Get the registered user who have never logged in, but have activated their registration
        $resultslogin = $model->showloginReminder();
        $view->itemlistlogin = $resultslogin;
        // pagination for first tab
        $pagination = $model->getPagination();
        $view->pagination = $pagination;
        // pagination for second tab
        $pagination2 = $model->getPagination2();
        $view->pagination2 = $pagination2;
        $view->_display();
    }
    function sendUserTestMail() {
        //$model = &$this->getModel('userreminder');
        $model = $this->getModel('userreminder');
        $view	= $this->getView ( 'userreminder','html');
        $model->sendUserTestMail();
        // Get the registered user who have not logged in for x number of days
        $resultsexistinglogin = $model->showexistinguserReminder();
        $view->itemlistexistinglogin = $resultsexistinglogin;
        // pagination
        $pagination = $model->getPaginationExistingUser();
        $view->paginationExistingUser = $pagination;
        $view->_display();
    }
    function sendreminder() {


        $input = Factory::getApplication()->input;
		
		// get number email send this time
		$email_number_old = $input->get('email_number_old',0,'int');
		$email_number_new = $input->get('email_number_new',0,'int');
		$email_number = \Joomla\CMS\Component\ComponentHelper::getParams('com_userreminder')->get('number_email',200);
		
		if(!$email_number_new) $email_number_new = $email_number;
		
        $model = $this->getModel('sendreminder');
        //$model = &$this->getModel('sendreminder');
        $view	= $this->getView ( 'sendreminder','html');
        $results = $model->sendReminder(true,$email_number_old,$email_number_new,$email_number);
        $view->_display();
		// end modifiled by Nam Thai on 23may2012
    }
    function sendUserReminder() {

        $input = Factory::getApplication()->input;

		$db = \Joomla\CMS\Factory::getDBO();

		
		// get number email send this time
        $email_number_old = $input->get('email_number_old',0,'int');
        $email_number_new = $input->get('email_number_new',0,'int');
		$number_email 		= \Joomla\CMS\Component\ComponentHelper::getParams('com_userreminder')->get('number_email',200);
				
        //$model = &$this->getModel('senduserreminder');
        $model = $this->getModel('senduserreminder');
        $view	= $this->getView ( 'senduserreminder','html');
        $results = $model->sendUserReminder(true,$email_number_old,$email_number_new,$number_email);
        $view->_display();
		// end modifiled by Nam Thai on 23may2012
    }


    
    /**
     * Function that will load all the information needed for the opt-out panel
     */
    function optuserPanel(){

        $input = Factory::getApplication()->input;


    	$sortColumn = $input->getString('filter_order','');
    	$sortDirection = $input->getString('filter_order_Dir','');
    	 
    	\Joomla\CMS\MVC\Model\BaseDatabaseModel::addIncludePath (JPATH_ADMINISTRATOR . '/components/com_users/models');
    	$groupsModel = \Joomla\CMS\MVC\Model\BaseDatabaseModel::getInstance('groups', 'usersModel');
    	$groupList = $groupsModel->getItems();
    	 
    	// get the list of opt out users and it's pagination
    	$model = $this->getModel('optusers');
    	$optUserList = $model->getOptUsers($sortColumn, $sortDirection);
    	$optGroups = $model->getOptGroups();
    	$pagination = $model->getPagination();
    	$userPagination = $model->getPaginationForUser();
    	$userList = $model->getUsersList($sortColumn, $sortDirection);
    	 
    	$view	= $this->getView ( 'optoutusers','html');
    	$input->getString('task', 'optuserPanel');
    	$view->sortColumn = $sortColumn;
    	$view->sortDirection = $sortDirection;
    	$view->optusers = $optUserList;
    	$view->userlist = $userList;
    	$view->optgroups= $optGroups;
    	$view->groupList = $groupList;
    	$view->pagination = $pagination;
    	$view->userPagination = $userPagination;
    	$view->_display();
    }
    
    function removeOptuser(){

		$cid = Factory::getApplication()->input->get('cid','','string');

    	$model = $this->getModel('optusers');
    	$app = \Joomla\CMS\Factory::getApplication();
    	 
    	if($model->removeOptUsers($cid)){
    		$app->enqueueMessage(\Joomla\CMS\Language\Text::_('USERREMINDER_OPTUSER_REMOVE'));
    	} else {
    		$app->enqueueMessage(\Joomla\CMS\Language\Text::_('USERREMINDER_OPTUSER_FAILED'));
    	}
    	// return back to the same panel
    	$this->optuserPanel();
    }
	
    function userlist(){

    	$sortColumn = Factory::getApplication()->input->get('filter_order','');
    	$sortDirection =  Factory::getApplication()->input->get('filter_order_Dir','');
    
    	// get the data
    	$model = $this->getModel('optusers');
    	$userList = $model->getUsersList($sortColumn, $sortDirection);
    	$userPagination = $model->getPaginationForUser();
    
    	// get the view and set the layout
    	$view = $this->getView ('optoutusers','html');
    	$view->setLayout('userlist');
		Factory::getApplication()->input->set('task','userlist');

    	// assign variables to be used for the layout
    	$view->sortColumn = $sortColumn;
    	$view->sortDirection = $sortDirection;
    	$view->userlist = $userList;
    	$view->userPagination = $userPagination;
    
    	$view->_display();
    }
    
    function usergroup(){

		Factory::getApplication()->input->set('filter_search','');

    	\Joomla\CMS\MVC\Model\BaseDatabaseModel::addIncludePath (JPATH_ADMINISTRATOR . '/components/com_users/models');
    	$groupsModel = \Joomla\CMS\MVC\Model\BaseDatabaseModel::getInstance('groups', 'usersModel');
    	//$groupsModel->setState('filter.search', '');
    	$groupList = $groupsModel->getItems();
    
    	// get the data
    	$model = $this->getModel('optusers');
    	$optGroups = $model->getOptGroups();
    
    	// set the view and layout
    	$view = $this->getView ('optoutusers','html');

	    Factory::getApplication()->input->set('task','usergroup');

    	$view->setLayout('usergroup');
    	$view->optgroups = $optGroups;
    	$view->groupList = $groupList;
    	$view->_display();
    }
    
    public function saveList()
    {

		$cid = Factory::getApplication()->input->get('cid','');
    	$model = $this->getModel('optusers');
    	$app = \Joomla\CMS\Factory::getApplication();
    
    	if($model->addOptUsers($cid)){
    		$app->enqueueMessage(\Joomla\CMS\Language\Text::_('USERREMINDER_OPTUSER_ADDED'));
    	} else {
    		$app->enqueueMessage(\Joomla\CMS\Language\Text::_('USERREMINDER_OPTUSER_FAILED'));
    	}
    
    	switch ($this->getTask())
    	{
    		case 'applyList':
    			$this->userlist();
    			break;
    		case 'saveList':
    		default:
    			$redirect = 'index.php?option=com_userreminder';
    			$this->setRedirect($redirect);
    			break;
    	}
    }
    
    public function saveGroup(){

	    $cid = Factory::getApplication()->input->get('cid',[],'array');
    
    	$model = $this->getModel('optusers');
    	$app = \Joomla\CMS\Factory::getApplication();
    		
    	if($model->saveUserGroup($cid)){
    		$app->enqueueMessage(\Joomla\CMS\Language\Text::_('USERREMINDER_OPTGROUP_ADDED'));
    	} else {
    		$app->enqueueMessage(\Joomla\CMS\Language\Text::_('USERREMINDER_OPTUSER_FAILED'));
    	}
    
    	switch ($this->getTask())
    	{
    		case 'applyGroup':
    			$this->usergroup();
    			break;
    		case 'saveGroup':
    		default:
    			$redirect = 'index.php?option=com_userreminder';
    			$this->setRedirect($redirect);
    			break;
    	}
    }

	
}

