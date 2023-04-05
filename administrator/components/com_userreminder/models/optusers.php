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
jimport('joomla.html.pagination');

class userreminderModelOptUsers extends JModelList {
	
    var $_pagination = null;
    var $_pagination2 = null;
    
    var $_total = null;
    var $_total2 = null;
    
    var $_data = null;
    var $_data2 = null;
    
    var $_list = null;

    function __construct($config = array()) {
    	parent::__construct($config);

        global $option;
		    //$mainframe = & JFactory::getApplication();
        $mainframe = JFactory::getApplication();
        // Get pagination request variables
        $limit = $mainframe->getUserStateFromRequest('global.list.limit', 'limit', $mainframe->getCfg('list_limit'), 'int');
        $limitstart = JFactory::getApplication()->input->get('limitstart', 0, '', 'int');

        // In case limit has been changed, adjust it
        $limitstart = ($limit != 0 ? (floor($limitstart / $limit) * $limit) : 0);

        $this->setState('limit', $limit);
        $this->setState('limitstart', $limitstart);
        
        // Get pagination request variables2
        $limit2 = $mainframe->getUserStateFromRequest('global.list.limit', 'limit', $mainframe->getCfg('list_limit'), 'int');
        $limitstart2 = JFactory::getApplication()->input->get('user_limitstart', 0, '', 'int');
        
        // In case limit has been changed, adjust it
        $limitstart2 = ($limit2 != 0 ? (floor($limitstart2 / $limit2) * $limit2) : 0);
        
        $this->setState('limit2', $limit2);
        $this->setState('limitstart2', $limitstart2);   
    }
    
    function getPagination() {
    	// Load the content if it doesn't already exist
    	if (empty($this->_pagination)) {
    		$this->_pagination = new JPagination($this->getTotal(), $this->getState('limitstart'), $this->getState('limit'));
    	}
    	return $this->_pagination;
    }
    
    function getTotal() {
    	// Load the content if it doesn't already exist
    	if (empty($this->_total)) {
    		$this->_data = $this->getOptUsers();
    	}
    	return $this->_total;
    }
    
    function getPaginationForUser() {
    	// Load the content if it doesn't already exist
    	if (empty($this->_pagination2)) {
    		$this->_pagination2 = new JPagination($this->getTotalForUser(), $this->getState('limitstart2'), $this->getState('limit2'), 'user_');
    	}
    	return $this->_pagination2;
    }
    
    function getTotalForUser() {
    	// Load the content if it doesn't already exist
    	if (empty($this->_total2)) {
    		$this->_data2 = $this->getUsersList();
    	}
    	return $this->_total2;
    }

/** 
    // get's the users that are optout
    function getOptUsers($ordering = '', $direction = ''){
    	if(empty($this->_data)){
	    	$sql = $this->getListQuery($ordering, $direction);
	     	$this->_data = $this->_getList($sql, $this->getState('limitstart'), $this->getState('limit'));
	
	    	$db = $this->getDbo();
	    	$db->setQuery($sql);
	    	$db->loadObjectList();
	    	$db->setQuery( "SELECT FOUND_ROWS();" );
	    	$this->_total = $db->loadResult();
    	}
    	return $this->_data;
    }
**/    
    /**
     * get the selected user group
     */
    function getOptGroups(){
    	$sql = "SELECT * FROM `#__userreminder_optout_usergroups`";
    	$db = $this->getDbo();
    	$db->setQuery($sql);
    	return $db->loadObjectList();
    }
    
    function removeOptUsers($list){
    	// get the ids to be remove from the opt list
    	$ids = implode(",", $list);
    	
    	// execute the delete query
    	$db	= JFactory::getDBO();
    	$sql = "DELETE FROM `#__userreminder_optout` WHERE user_id IN ({$ids})";
    	$db->setQuery($sql);
    	return $db->execute();
    }
    
    /**
     * Function that will include user in the optout table
     */
    function addOptUsers($list){
    	// get the ids to be remove from the opt list
    	$ids = implode("),(", $list);
    	// execute the delete query
    	$db	= JFactory::getDBO();
    	$sql = "INSERT INTO `#__userreminder_optout` VALUES ({$ids})";
    	$db->setQuery($sql);
    	return $db->execute();
    }
    
    /**
     * Saves the user group setting of the opt user screen
     */
    public function saveUserGroup($list){
    	
    	$db	= JFactory::getDBO();
    	// clear the table first
    	$sql = "TRUNCATE TABLE `#__userreminder_optout_usergroups`";
    	$db->setQuery($sql);
    	if(!$db->execute()){
    		return false;
    	}

    	
    	if(empty($list)){
    		return true;
    	}
    	
    	// get the ids to be remove from the opt list

    	$ids = implode("),(", $list);
    	// add all that is checked
    	$sql = "INSERT INTO `#__userreminder_optout_usergroups` VALUES ({$ids})";
    	$db->setQuery($sql);
    	return $db->execute();
    }
/**    
    public function getListQuery($ordering = '', $direction = '') {
    	$db = JFactory::getDbo();
    	$query = $db->getQuery(true);
    
    	$query->select('*');
    	$query->from($db->quoteName('#__users'));
    	
    	
    	$where = 'id IN (SELECT user_id FROM #__userreminder_optout)';
    	// add search filter if any
    	$search = JFactory::getApplication()->input->get('filter_search');
    	if($search != ''){
    		$where .= " AND (name LIKE '%{$search}%' OR username LIKE '%{$search}%' OR email LIKE '%{$search}%')";
    	}
    	 
    	$query->where($where);
    	
    	if($ordering != '' && $direction != ''){
    		$query->order($ordering.' '.$direction);
    	}
    	return $query;
    }
**/    

     /**
     * Returns a list of user that are not opt-out
     */
/** 
    function getUsersList($ordering = '', $direction = ''){
    	$db = JFactory::getDbo();
    	$query = $db->getQuery(true);
    	 
    	$query->select('*');
    	$query->from($db->quoteName('#__users'));
    	
    	$where = 'id NOT IN (SELECT user_id FROM #__userreminder_optout)';
    	// add search filter if any
    	$search = JFactory::getApplication()->input->get('filter_search');
    	if($search != ''){
    		$where .= " AND (name LIKE '%{$search}%' OR username LIKE '%{$search}%' OR email LIKE '%{$search}%')";
    	}
    	
    	$query->where($where);
    	if($ordering != '' && $direction != ''){
    		$query->order($ordering.' '.$direction);
    	}
    	
    	$this->_data2 = $this->_getList($query, $this->getState('limitstart2'), $this->getState('limit2'));
    	
    	$db->setQuery($query);
    	$db->loadObjectList();
    	$db->setQuery( "SELECT FOUND_ROWS();" );
    	$this->_total2 = $db->loadResult();

    	return $this->_data2;
    }
**/    
    // Search query for users that are opt-out
    public function getListQuery($ordering = '', $direction = '') {
    	$db = JFactory::getDbo();
    	$query = $db->getQuery(true);
    
    	$query->select('*');
    	$query->from($db->quoteName('#__users'));
   	
    	$where = 'id IN (SELECT user_id FROM #__userreminder_optout)';
    	// add search filter if any
    	//$search = JRequest::getVar('filter_search');
    	$search = JFactory::getApplication()->input->get('filter_search');
    	if($search != ''){
    		$where .= " AND (name LIKE '%{$search}%' OR username LIKE '%{$search}%' OR email LIKE '%{$search}%')";
    	}
    	 
    	$query->where($where);
    	
    	if($ordering != '' && $direction != ''){
    		$query->order($ordering.' '.$direction);
    	}
    	return $query;
    }

    // Search query for users that are NOT opt-out
    public function getListQuery2($ordering = '', $direction = '') {
    	$db = JFactory::getDbo();
    	$query = $db->getQuery(true);
    
    	$query->select('*');
    	$query->from($db->quoteName('#__users'));
    	
    	$where = 'id NOT IN (SELECT user_id FROM #__userreminder_optout)';
    	// add search filter if any
    	//$search = JRequest::getVar('filter_search');
    	$search = JFactory::getApplication()->input->get('filter_search');
    	if($search != ''){
    		$where .= " AND (name LIKE '%{$search}%' OR username LIKE '%{$search}%' OR email LIKE '%{$search}%')";
    	}
    	
    	$query->where($where);
    	if($ordering != '' && $direction != ''){
    		$query->order($ordering.' '.$direction);
    	}

    	return $query;
    }
    
    /**
     *     get's the users that are optout
    */
    function getOptUsers($ordering = '', $direction = '',$debug = ''){
      if(empty($this->_data)){
            $sql = $this->getListQuery($ordering, $direction);
	    	$db = $this->getDbo();
	    	$this->_data = $this->_getList($sql, $this->getState('limitstart'), $this->getState('limit'));
        $db->setQuery( "SELECT FOUND_ROWS();" );
	    	$this->_total = $db->loadResult();
    	}

    	return $this->_data;
    }
  
    /**
     * Returns a list of user that are not opt-out
     */
    function getUsersList($ordering = '', $direction = ''){
    	if(empty($this->_data2)){
	    	$sql = $this->getListQuery2($ordering, $direction);
	     	$db = $this->getDbo();
        $this->_data2 = $this->_getList($sql, $this->getState('limitstart2'), $this->getState('limit2'));
	    	$db->setQuery( "SELECT FOUND_ROWS();" );
	    	$this->_total2 = $db->loadResult();
      }
    	return $this->_data2;

    }    
    
}
?>


