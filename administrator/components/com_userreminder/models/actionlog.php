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

jimport( 'joomla.application.component.model' );
jimport( 'joomla.application.component.helper' );

class userreminderModelActionLog extends JModelLegacy {

    /**
     * Items total
     * @var integer
     */
    var $_total = null;

    /**
     * Pagination object
     * @var object
     */
    var $_pagination = null;

    function __construct() {
        parent::__construct();

        global $option;
		    //$mainframe = & JFactory::getApplication();
        $mainframe = JFactory::getApplication();
        // Get pagination request variables
        $limit = $mainframe->getUserStateFromRequest('global.list.limit', 'log_limit', $mainframe->getCfg('list_limit'), 'int');
        $limitstart = JFactory::getApplication()->input->get('log_limitstart', 0, '', 'int');

        // In case limit has been changed, adjust it
        $limitstart = ($limit != 0 ? (floor($limitstart / $limit) * $limit) : 0);

        $this->setState('log_limit', $limit);
        $this->setState('log_limitstart', $limitstart);
    }

    function getPaginationActionLog() {
        // Load the content if it doesn't already exist
        if (empty($this->_pagination)) {
            jimport('joomla.html.pagination');
            $this->_pagination = new JPagination($this->_total, $this->getState('log_limitstart'), $this->getState('log_limit'), 'log_');
        }
        return $this->_pagination;
    }

    function showActionLog() {
        // get the parameter for this component
        //$usersConfig = &JComponentHelper::getParams( 'com_userreminder' );
        $usersConfig = JComponentHelper::getParams( 'com_userreminder' );

        $list = array();

        //$db	=& JFactory::getDBO();
        $db	= JFactory::getDBO();

        // find users who have not logged in for x number of days
        $days= $usersConfig->get( 'numberOfDaysExistingUser',180 );
        $sql = "SELECT SQL_CALC_FOUND_ROWS `id`, `userId`, `username`, `description`, `date` FROM #__userreminder_log order by `id` desc";

        $rows = $this->_getList($sql, $this->getState('log_limitstart'), $this->getState('log_limit'));

        //find total number of rows for pagnation
        $db->setQuery('SELECT FOUND_ROWS();');
        $this->_total = $db->loadResult();

        // set the max counter , make sure to incldue the records from previous pages
        $iMaxRecords = $this->getState('log_limitstart')+ 1;

        if (count($rows) > 0) {
            foreach ($rows as $row) {
                
                $list[] = array ( "id" => $row->id, "userId"=> $row->userId, "username" => $row->username, "description" => $row->description, "date" => $row->date  );
            }
        }
        return $list;
    }

    function clearActionLog() {

        //$db	=& JFactory::getDBO();
        $db	= JFactory::getDBO();

        $sql = "TRUNCATE TABLE `#__userreminder_log`";
        $db->setQuery($sql);
        $db->query();
    }



    function replaceParams($message,$parameter,$replacementString) {
        $newString = "";
        $newString = str_replace($parameter, $replacementString, $message);
        return $newString;
    }

}
?>


