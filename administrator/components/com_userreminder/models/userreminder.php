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

class userreminderModelUserReminder extends JModelLegacy {

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

  function __construct()
  {
        parent::__construct();
 
        global $option;
		//$mainframe = & JFactory::getApplication();
       $mainframe =  JFactory::getApplication();
        // Get pagination request variables
        $limit = $mainframe->getUserStateFromRequest('global.list.limit', 'limit', $mainframe->getCfg('list_limit'), 'int');
        $limitstart = JFactory::getApplication()->input->get('limitstart', 0, '', 'int');
 
        // In case limit has been changed, adjust it
        $limitstart = ($limit != 0 ? (floor($limitstart / $limit) * $limit) : 0);
 
        $this->setState('limit', $limit);
        $this->setState('limitstart', $limitstart);
  }

  function getPaginationExistingUser()
  {
        // Load the content if it doesn't already exist
        if (empty($this->_pagination)) {
            jimport('joomla.html.pagination');
            $this->_pagination = new JPagination($this->_total, $this->getState('limitstart'), $this->getState('limit') );
        }
        return $this->_pagination;
  }

	function showexistinguserReminder() 
	{
		//$db	=& JFactory::getDBO();
		$db	= JFactory::getDBO();

		// get the parameter for this component
    $query = "SELECT params FROM #__extensions WHERE `element`='com_userreminder'";
    $db->setQuery($query);
    $result = $db->loadRow();
  
    $tableParam = str_replace("\r\n", "<br />", $result[0]);
    $usersConfig = json_decode($tableParam, TRUE);

		$list = array();
    
    // Changes 2.5.9.13.
     if ($this->getParamData( $usersConfig,'debugUserReminder',0 )==1) {
        ?>
  			<tr>
  				<td colspan=3><font color="red"><H1><?php print JText::_('USERREMINDER_DEBUG'); ?></H1><?php print JText::_('USERREMINDER_DEBUG_TEXT'); ?></font><br /></td>
  			</tr>
        <?php
      }  
	
		// find users who have not logged in for x number of days
		// added the sql below to exclude useres that are in the user group
		// AND #__users.id IN (SELECT user_id FROM #__user_usergroup_map WHERE group_id NOT IN (SELECT group_id FROM #__userreminder_optout_usergroups))
		$days= $this->getParamData( $usersConfig,'numberOfDaysExistingUser',180 );
    $sql = "SELECT SQL_CALC_FOUND_ROWS id, email, block, registerDate, lastvisitDate, activation, datesent, type, remindernumber, (TO_DAYS(NOW()) - TO_DAYS(lastvisitDate)) as nodays 
				FROM #__users 
				LEFT JOIN #__userreminder on id=userid
				LEFT JOIN #__userreminder_optout on #__users.id=#__userreminder_optout.user_id  
				WHERE
				( 
				(isnull(#__userreminder_optout.user_id) AND lastvisitDate <> '0000-00-00 00:00:00' AND block = 0 AND (TO_DAYS(NOW()) - TO_DAYS(lastvisitDate)) > ".$days.") 
				or 
				(isnull(#__userreminder_optout.user_id) AND id=userid and type=3)
				)
    			AND (#__users.id NOT IN (SELECT user_id FROM #__user_usergroup_map WHERE group_id IN (SELECT group_id FROM #__userreminder_optout_usergroups)))";
    
		$rows = $this->_getList($sql, $this->getState('limitstart'), $this->getState('limit')); 

		  //find total number of rows for pagnation
		  $db->setQuery('SELECT FOUND_ROWS();'); 
		$this->_total = $db->loadResult();

		  // set the max counter , make sure to incldue the records from previous pages
		  $iMaxRecords = $this->getState('limitstart')+ 1;
		
		if (count($rows) > 0)
		  {
				foreach ($rows as $row)
				{
				  $user	= JFactory::getUser($row->id);
				  $name 	= $user->name . " [". $user->username."]"; 
			  // check to see if another reminder needs to be sent
			  if ($row->nodays < $this->getParamData( $usersConfig,'numberOfDays',0 ))
			  {
				$action=JText::_('USERREMINDER_USER_HASLOGGEDIN');
			  }
			  elseif ($this->getParamData( $usersConfig,'numberOfReminders',1 ) > $row->remindernumber)
			  {
				   // check to see if the maximum number of emails is going to be exceeded
				   //if ($iMaxRecords > $usersConfig->get( 'maxemailstosend',20 )){
				   //      $action=JText::_('USERREMINDER_ACTIONMAXEMAILS1');
				   //}
				   //else
				   //{
					  $check_date = strtotime($row->datesent); 
					  $d9 = strtotime('-'.$this->getParamData( $usersConfig,'numberOfDays',1 ).' days',time());  
					  if($check_date < $d9)
					  { 
						  $action=JText::_('USERREMINDER_ACTIONSEND');
						  $iMaxRecords = $iMaxRecords+1;
					  }
					  else
					  { 
						  $action=JText::_('USERREMINDER_ACTION_LOGINNONE'); 
					  }
				   //}   
			   }
			   else
			   { 
				  // delete the user              
				  $check_date = strtotime($row->datesent); 
				  $d9 = strtotime('-'.$this->getParamData( $usersConfig,'numberOfDays',1 ).' days',time());  
				  if($check_date < $d9)
				  { 
					  if ($this->getParamData( $usersConfig,'enableDeleteExistingUsers',1 ) == 0)
					  {
						 $action=JText::_('USERREMINDER_ACTIONDELETE'); 
					  }
					  else
					  {
						 $action=JText::_('USERREMINDER_ACTIONNOTDELETED'); 
					  }
				  }
				  else
				  {
					  $action=JText::_('USERREMINDER_ACTION_LOGINNONE'); 
				  }
				
				  //$action=JText::_('USERREMINDER_ACTION_LOGINNONE'); 
			   }
			   $list[] = array ( "name" => $name, "email"=> $row->email, "lastvisitDate" => $row->lastvisitDate, "remindersent" => $row->datesent, "action" => $action, "remindernumber"=>$row->remindernumber, "reminderdays"=>$row->nodays   ); 
				}
		   }
		return $list;
	}


	function sendUserTestMail()
	{
			//$mainframe = & JFactory::getApplication();
      $mainframe =  JFactory::getApplication();

			//$db		=& JFactory::getDBO();
      $db		= JFactory::getDBO();

			// get current user
			//$user =& JFactory::getUser();
      $user = JFactory::getUser();

			?>
		<table class="adminheading">
			<tr> 
				<td>
				<h1><?php print JText::_( 'USERREMINDER_TEST' ).' '. $user->get('email'); ?></h1>
				</td>
			</tr>
			</table>
		<?php
		
			$name 		= $user->get('name');
			$email 		= $user->get('email');
			$username 	= $user->get('username');

			//$usersConfig 	= &JComponentHelper::getParams( 'com_users' );
			//$usersConfig 	= JComponentHelper::getParams( 'com_users' );
      $query = "SELECT params FROM #__extensions WHERE `element`='com_userreminder'";
      $db->setQuery($query);
      $result = $db->loadRow();
    
      $tableParam = str_replace("\r\n", "<br />", $result[0]);
      $usersConfig = json_decode($tableParam, TRUE);

			$sitename 		= $mainframe->getCfg( 'sitename' );
			$mailfrom 		= $mainframe->getCfg( 'mailfrom' );
			$fromname 		= $mainframe->getCfg( 'fromname' );
			$siteURL		= JURI::root();

		// get the parameter for this component
		//$regConfig = &JComponentHelper::getParams( 'com_userreminder' );
		$query = "SELECT params FROM #__extensions WHERE `element`='com_userreminder'";
        $db->setQuery($query);
        $result = $db->loadRow();

        $tableParam = str_replace("\r\n", "<br />", $result[0]);
        $regConfig = json_decode($tableParam, TRUE);

		// Check and Set sender details
			if ( ! $mailfrom  || ! $fromname ) {
				$fromname = $rows[0]->name;
				$mailfrom = $rows[0]->email;
			}

		// ********************************************************************************
		// Create and send TEST email for users who have not logged in for x number of days
		// ********************************************************************************
		if ($this->getParamData( $regConfig,'regExistingUserEmailSubject',"" )==""){ 
		  $subject = JText::_('USERREMINDER_EXISTINGUSERREMINDER_DETAILS_FOR');
		}
		else{
		  $subject = $this->getParamData( $regConfig,'regExistingUserEmailSubject',"" );
		}
		
		$subject 	= userreminderModelUserReminder::replaceParams($subject,"[NAME]",$name);
		$subject 	= userreminderModelUserReminder::replaceParams($subject,"[SITE_NAME]",$sitename);
		$subject 	= html_entity_decode($subject, ENT_QUOTES);

		// Get email body
		// check send body html or plain text
		if($this->getParamData( $regConfig,'chkEmailHTML_Remider',0 )==0){			$mode = 0;
			// send body email plain text
			if ($this->getParamData( $regConfig,'regExistingUserEmailBody',"" )==""){
			  $message = JText::_('USERREMINDER_SEND_MSG_EXISTINGUSERREMINDER');
			}
			else{
			  $message = $this->getParamData( $regConfig,'regExistingUserEmailBody',"" );
			}
		}
		else {			$mode = 1;
			// send mail with html body
			if ($this->getParamData( $regConfig,'regExistingUserEmailBodyHTML',"" )==""){
				$message = JText::_('USERREMINDER_SEND_MSG_EXISTINGUSERREMINDER');
				$message	= preg_replace("(\n)", "<br />", $message); // if content is plain text -> carriage returns it if have \n
			}
			else{
				$message = $this->getParamData( $regConfig,'regExistingUserEmailBodyHTML',"" );
			}
		}
		
		$message =  JText::_( 'USERREMINDER_REMINDER_DETAILS_FOR_TEST' ).chr(10).chr(10).$message;
		$message 	= userreminderModelUserReminder::replaceParams($message,"[NAME]",$name);
		$message 	= userreminderModelUserReminder::replaceParams($message,"[SITE_NAME]",$sitename);
		$message 	= userreminderModelUserReminder::replaceParams($message,"[SITE_URL]",$siteURL);
		$message 	= userreminderModelUserReminder::replaceParams($message,"[USERNAME]",$username);
		//$message 	= userreminderModelUserReminder::replaceParams($message,"[PASSWORD_RESET]",$siteURL.'index.php?option=com_user&view=reset');
		//if ($this->getParamData( $regConfig,'useCBActivation',0 )==0) {
		//  $message 	= userreminderModelUserReminder::replaceParams($message,"[PASSWORD_RESET]",$siteURL.'index.php?option=com_user&view=reset');
		//}
		//else
		//{
		//  $message 	= userreminderModelUserReminder::replaceParams($message,"[PASSWORD_RESET]",$siteURL.'index.php?option=com_comprofiler&task=lostpassword');
		//}
		// Reset Passowrd link
    $message 	= userreminderModelUserReminder::replaceParams($message,"[PASSWORD_RESET]",$siteURL.$this->getParamData( $regConfig,'passwordReset',"" ));
        
    $message 	= userreminderModelUserReminder::replaceParams($message,"[OPTOUT]",$siteURL.'index.php?option=com_userreminder&task=optout&uid='.$user->id);
		$message = html_entity_decode($message, ENT_QUOTES);
		// if($chkEmailHTML_Remider == 0)			// $message	= preg_replace("(\n)", "<br />", $message); // if content is plain text -> carriage returns it if have \n			
		// send email
		JFactory::getMailer()->sendMail($mailfrom, $fromname, $email, $subject, $message,$mode);
	}

	function replaceParams($message,$parameter,$replacementString)
	{
		$newString = "";
		$newString = str_replace($parameter, $replacementString, $message); 
		return $newString;
	}
	
	//Masum: newly added
    function getParamData($dataInputArray, $arrayIndex, $defaultValue) {

        $returnValue = "";

        if (isset($dataInputArray['' . $arrayIndex])) {
            if ($dataInputArray[$arrayIndex] == '' || $dataInputArray[$arrayIndex] == NULL) {
                $returnValue = $defaultValue;
            } else {
                if (is_numeric($dataInputArray[$arrayIndex])) {
                    $returnValue =  intval($dataInputArray[$arrayIndex]);
                } else {
                    $returnValue = $dataInputArray[$arrayIndex];
                }
            }
        } else {
            $returnValue = $defaultValue;
        }

        //$returnValue = str_replace( "<br />","\r\n", $returnValue);
        return $returnValue;

    }
}
?>