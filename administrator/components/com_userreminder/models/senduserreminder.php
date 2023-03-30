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

class userreminderModelSendUserReminder extends JModelLegacy {

//function showuserReminder( $option )
	function count_all(){
		$db = JFactory::getDBO();
		
		// find all records to be sent
		// added the sql below to exclude useres that are in the user group
		// AND #__users.id IN (SELECT user_id FROM #__user_usergroup_map WHERE group_id NOT IN (SELECT group_id FROM #__userreminder_optout_usergroups))
    $sql = "SELECT COUNT(*) AS numrecord
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
		
		$db->setQuery($sql);	
		$a = $db->loadObjectList(); 
		echo $a[0]->numrecord;
		exit;	
		
		if( $db->loadResult() ) return $db->loadResult() ;
		return 0;
	}
  
  function sendUserReminder($displayHTML=true, $email_number_old,$email_number_new,$email_number) {
	$db = JFactory::getDBO();
    jimport('joomla.user.helper');
    jimport('joomla.language.helper');

    //$lang = & JFactory::getLanguage();
   $lang = JFactory::getLanguage();
   $lang->load( 'com_users', JPATH_SITE);

    //require_once(JPATH_ROOT.DS.'components'.DS.'com_user'.DS.'controller.php');
	require_once(JPATH_ROOT .'/components/com_users/controller.php');

    // check to see if the current user is in the administrator group. if not then exit
    //$cuser =& JFactory::getUser();
	// echo '<pre>'; print_r($cuser); exit;
    // // check to see if the user is a super administrator
    // if($cuser->gid != 25) {
        // echo JText::_('USERREMINDER_SEC');
        // return;
    // }

    //get the list of administrators to notify - we will bcc them
    //$query = 'SELECT email ' .
    //        ' FROM #__users' .
    //        ' WHERE LOWER( usertype ) = "super administrator" and sendEmail=1';
    //$db->setQuery( $query );
    //$adminemails = $db->loadResultArray();

    $adminemails = \Joomla\CMS\Component\ComponentHelper::getParams('com_userreminder')->get('bccEmailAddress','');
    
    // find the days parameter
    $days= \Joomla\CMS\Component\ComponentHelper::getParams('com_userreminder')->get('numberOfDaysExistingUser',180);

    // Clean up any records in the userreminder table where the user has now loged back in
    $q = "DELETE #__userreminder FROM #__userreminder INNER JOIN #__users ON  #__userreminder.userid=#__users.id WHERE lastvisitDate <> '0000-00-00 00:00:00' and type=3 and (TO_DAYS(NOW()) - TO_DAYS(lastvisitDate)) <= ".$days."";
    $db->setQuery($q);
    $db->execute();


    // Clean up any records in the userreminder table where the user no longer exists in the user table
    $q = "DELETE FROM #__userreminder where  #__userreminder.userid not in (Select #__users.id from #__users)";
    $db->setQuery($q);
    $db->execute();


    // find users who have not logged in for x number of days
    // added the sql below to exclude useres that are in the user group
    // AND #__users.id IN (SELECT user_id FROM #__user_usergroup_map WHERE group_id NOT IN (SELECT group_id FROM #__userreminder_optout_usergroups))
		$sql = "SELECT id, email, block, registerDate, lastvisitDate, activation, datesent, type, remindernumber, (TO_DAYS(NOW()) - TO_DAYS(lastvisitDate)) as nodays, optoutcode, username, name  
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
		
		$sql_count = "SELECT COUNT(*) 
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
	
		// if being run from the user interface then display data/messages
    if ($displayHTML==true){
      ?>
      		<script type="text/javascript">
				Joomla.submitbutton = function(task){
					Joomla.submitform(task, document.getElementById('userreminder-form'));
				}
			</script>
    		<table class="table table-striped" id="articleList">
    		<thead>
    		<tr>
            <th width="30%"><?php print JText::_('USERREMINDER_NAME'); ?></th>
            <th width="30%"><?php print JText::_('USERREMINDER_EMAIL'); ?></th>
            <th align="left"><?php print JText::_('USERREMINDER_DATESENT'); ?></th>
    		</tr>
    		</thead>
    		<tbody>
      <?php
    }
    
    // ensure counter is at 1
    $iMaxRecords = 1;
    $email_number_new = 0;

		// count all record
		$db->setQuery($sql_count);
		$countAll = $db->loadResult(); 


		
    // set the flag for total number of records
		if($countAll){
			$recordsetcount=1;
			$recordcount = $countAll;
		}else{
			$recordsetcount=-1;
			$recordcount = 0;
		}
		
		// if being run from the user interface then display data/messages
		if ($displayHTML==true){ 
      //$progressmessage = 'Total number of Records to Process = '.$recordcount.'<br />';
      //$progressmessage = $progressmessage.'Records in each batch = '.$email_number.'<br />';
      $progressmessage = JText::_('USERREMINDER_PROCESS_MESSAGE1').' = '.$recordcount.'<br />';
      $progressmessage = $progressmessage.JText::_('USERREMINDER_PROCESS_MESSAGE2').' = '.$email_number.'<br />';
      ?>
			<tr>
				<td colspan=3><?php print JText::_($progressmessage); ?></td>
			</tr>
      <?php
      
   	  // Changes 2.5.9.13. debug for memory usage
      if (\Joomla\CMS\Component\ComponentHelper::getParams('com_userreminder')->get('debugUserReminder',0)==1) {
        ?>
  			<tr>
  				<td colspan=3><font color="red"><H1><?php print JText::_('USERREMINDER_DEBUG'); ?></H1><?php print JText::_('USERREMINDER_DEBUG_TEXT'); ?></font><br /></td>
  			</tr>
        <?php
      }
    }
		
		$i=0;
		while($recordsetcount>0){

      // increment the batch number
      $i = $i+1;

      // query the Db for the next batch of records
      $sqlwithlimit = $sql." LIMIT $email_number_new,$email_number ";
			$db->setQuery( $sqlwithlimit );
			$rows = $db->loadObjectList();	
			$recordsetcount = count($rows);
			
  		// if being run from the user interface then display data/messages
      if ($displayHTML==true){
            $maxrecordnumber = $email_number + $email_number_new;
       			$display_email_number_new = $email_number_new +1;
            //$progressmessage = 'Batch Number '.$i.'. Processing Records '.$display_email_number_new.' to '.$maxrecordnumber.'. ';
            $progressmessage = JText::_('USERREMINDER_PROCESS_MESSAGE3').' '.$i.'. '.JText::_('USERREMINDER_PROCESS_MESSAGE4').' '.$display_email_number_new.' to '.$maxrecordnumber.'. ';
       			// Changes 2.5.9.13. debug for memory usage
            $progressmessage = $progressmessage.' '.JText::_('USERREMINDER_PROCESS_MESSAGE5');
            if (\Joomla\CMS\Component\ComponentHelper::getParams('com_userreminder')->get('debugUserReminder',0)==1) {
              $progressmessage = $progressmessage.' ('.memory_get_usage(true).')';
            }
       			//$progressmessage = 'Batch Number '.$i.'. Processing Records '.$email_number_new.' to '.$maxrecordnumber.'. ';
            //$progressmessage = $progressmessage.'Records in this batch = '.count($rows);
      			?>
            <tr>
      				<td colspan=3><?php print JText::_($progressmessage); ?></td>
      			</tr>
            <?php
      }

      // increment the LIMITE starting position by the number required
			$email_number_new= $email_number_new+$email_number;

			if (count($rows) > 0) {
				foreach($rows as $row){
    				//$user	= JFactory::getUser($row->id);

            // Changes 2.5.9.13.
            //$user	= JFactory::getUser($row->id);
    				unset($user);
            $user	= $row;
            //$user	= JFactory::getUser($row->id);
				    //$name 	= $user->name . " [". $user->username."]"; 
                				
    				if( !empty($user) ){
    						$name 	= $user->name . " [". $user->username."]";
    						// check to see if another reminder needs to be sent
    						if (\Joomla\CMS\Component\ComponentHelper::getParams('com_userreminder')->get('numberOfReminders',1) > $row->remindernumber) {
    							// check to see if the maximum number of emails is going to be exceeded
    							if ($iMaxRecords > \Joomla\CMS\Component\ComponentHelper::getParams('com_userreminder')->get('maxemailstosend',20)) {
                 			if ($displayHTML==true){
                      ?>
                      <tr>
                 				<td colspan=3><?php print JText::_('MAXIMUM_EMAILS_REACHED'); ?></td>
                 			</tr>
                      <?php
                      }
                      //set recordcount to 0 so that we exit the while loop
                      $recordsetcount = 0;
                      break;
    								  //$action=JText::_('USERREMINDER_ACTIONMAXEMAILS1');
    							}
    							else {
    								$check_date = strtotime($row->datesent);
    								$d9 = strtotime('-'.\Joomla\CMS\Component\ComponentHelper::getParams('com_userreminder')->get('numberOfDays',1).' days',time());
    								if($check_date < $d9) {
    									$action=JText::_('USERREMINDER_ACTIONSEND');
    									$iMaxRecords = $iMaxRecords+1;
    								}
    								else {
    									$action=JText::_('USERREMINDER_ACTION_LOGINNONE');
    								}
    							}
    						}
    						else {
    							// delete the user
    							$check_date = strtotime($row->datesent);
    							$d9 = strtotime('-'.\Joomla\CMS\Component\ComponentHelper::getParams('com_userreminder')->get('numberOfDays',1).' days',time());
    							if($check_date < $d9) {
    								if (\Joomla\CMS\Component\ComponentHelper::getParams('com_userreminder')->get('enableDeleteExistingUsers',1) == 0) {
    									$action=JText::_('USERREMINDER_ACTIONDELETE');
    								}
    								else {
    									$action=JText::_('USERREMINDER_ACTIONNOTDELETED');
    								}
    							}
    							else {
    								$action=JText::_('USERREMINDER_ACTION_LOGINNONE');
    							}
    
    							//$action=JText::_('USERREMINDER_ACTION_LOGINNONE');
    						}
    
    						$reminderNumber= $row->remindernumber;
    						$userOptOutCode = $row->optoutcode;
                $type= $row->type;
    						//$user = JFactory::getUser($row->id);
    

    						// now do the work and send the reminder

                // Changes 2.5.9.13. skip displaying this is no action is to be performed
                if ($action!=JText::_('USERREMINDER_ACTION_LOGINNONE')){
      						if ($displayHTML==true){
                    ?>
        						<tr class="row0">
        							<td width="350"><?php echo $user->name; ?></td>
        							<td width="150"><?php echo $user->email; ?></td>
        
        						<?php
                  }
                  
      						$sourceimg = "images/publish_x.png";
      
      						// delete the user if required
      						if (\Joomla\CMS\Component\ComponentHelper::getParams('com_userreminder')->get('enableDeleteExistingUsers',1) == 0 & $action==JText::_('USERREMINDER_ACTIONDELETE')) {
      							// Changes 2.5.9.13.
                    $user2 = JFactory::getUser($row->id);
                  $user2->delete(false);
                    unset($user2);
                    //$user->delete(false);
      						}
      
      						// send a reminder
      						if ($action==JText::_('USERREMINDER_ACTIONSEND')) {
        						// check to see if the user has an optout code
                      if ($userOptOutCode==""){
                          $userOptOutCode =  JApplication::getHash(JUserHelper::genRandomPassword());
                      }
                  // send email reminder
      						if(userreminderModelSendUserReminder::_sendMail($user,$adminemails,$type,$userOptOutCode)) {
      							// check to see if we need to insert or update a record
      							// Record that we are sending a reminder
      									if ( $reminderNumber > 0) {
      										$reminderNumber=$reminderNumber+1;
      										$q = "UPDATE #__userreminder set datesent=NOW(), remindernumber=".$reminderNumber.", type=3, optoutcode='".$userOptOutCode."' where userid=".$user->id."";
                        }
      									else {
      										$q = "INSERT INTO #__userreminder(userid, datesent, remindernumber, type,optoutcode) values (".$user->id." , NOW(), 1, 3,'".$userOptOutCode."')";
                        }
      									$db->setQuery($q);
      									$db->execute();

      								}
      							}
      							if ($action==JText::_('USERREMINDER_ACTIONSEND')) { 
                      if ($displayHTML==true){
                        ?>
        								<td  align="left"><?php echo JText::_('USERREMINDER_USER_REMINDER_SENT') ?></td>
        								<?php
      								}
                      //insert log
      								$description = "Reminders Run: ".JText::_('USERREMINDER_USER_REMINDER_SENT');
      								$date = date('Y-m-d H:i:s');
      								$q2 = "INSERT INTO #__userreminder_log (`userId`, `username`, `description`, `date`) VALUES ('".$user->id."', '".$user->username."', '".$description."', '".$date."');";
      								$db->setQuery($q2);
      								$db->execute();
      
      							} else { 
                      if ($displayHTML==true){
                        ?>
        								<td  align="left"><?php echo $action ?></td>
        								<?php
      							 }
                    } 
                    if ($displayHTML==true){
                      ?>
        							</tr>
        				      <?php
        				    }  
    				    } // Skip if no action
           } 
			  }
			}
		}
  if ($displayHTML==true){
  	?>
    </tbody>
  	</table>
  	<form method="post" name="adminForm" action="index.php?option=com_userreminder" id="userreminder-form">			
  				<input type="hidden" name="task" value="" />
  	</form>			
  	<?php
	}	
}

function _sendMail(&$user,&$adminemails,&$type,&$userOptOutCode) {
        //$mainframe = & JFactory::getApplication();
        $mainframe = JFactory::getApplication();

        //$db		=& JFactory::getDBO();
        $db		= JFactory::getDBO();

        //$name 		= $user->get('name');
        //$email 		= $user->get('email');
        //$username 	= $user->get('username');
        $name 		= $user->name;
        $email 		= $user->email;
        $username 	= $user->username;

        //$usersConfig 	= &JComponentHelper::getParams( 'com_users' );
        $usersConfig 	= JComponentHelper::getParams( 'com_users' );
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

        // create the message to be sent to users who are active but never logged in

        if (\Joomla\CMS\Component\ComponentHelper::getParams('com_userreminder')->get('regExistingUserEmailSubject','') == "") {
            $subject = JText::_('USERREMINDER_EXISTINGUSERREMINDER_DETAILS_FOR');
        }
        else {

            $subject = \Joomla\CMS\Component\ComponentHelper::getParams('com_userreminder')->get('regExistingUserEmailSubject','');
        }

        $subject 	= userreminderModelSendUserReminder::replaceParams($subject,"[NAME]",$name);
        $subject 	= userreminderModelSendUserReminder::replaceParams($subject,"[SITE_NAME]",$sitename);
        $subject 	= html_entity_decode($subject, ENT_QUOTES);

        // Get email body
		// check email html

		if(\Joomla\CMS\Component\ComponentHelper::getParams('com_userreminder')->get('chkEmailHTML_Remider',0) == 0){
			$mode = 0;
			// send mail plain text (default of component)

			if (\Joomla\CMS\Component\ComponentHelper::getParams('com_userreminder')->get('regExistingUserEmailBody','') == "") {
				$message = JText::_('USERREMINDER_SEND_MSG_EXISTINGUSERREMINDER');
			}
			else {

				$message = \Joomla\CMS\Component\ComponentHelper::getParams('com_userreminder')->get('regExistingUserEmailBody','');
			}
		}
		else
		{
			$mode = 1;
			//send email html of user custom

			if(\Joomla\CMS\Component\ComponentHelper::getParams('com_userreminder')->get('regExistingUserEmailBodyHTML','') == ""){
				$message = JText::_('USERREMINDER_SEND_MSG_EXISTINGUSERREMINDER');
				$message	= preg_replace("(\n)", "<br />", $message); // if content is plain text -> carriage returns it if have \n
			}
			else{

				$message = \Joomla\CMS\Component\ComponentHelper::getParams('com_userreminder')->get('regExistingUserEmailBodyHTML','');
			}
		}

        $message 	= userreminderModelSendUserReminder::replaceParams($message,"[NAME]",$name);
        $message 	= userreminderModelSendUserReminder::replaceParams($message,"[SITE_NAME]",$sitename);
        $message 	= userreminderModelSendUserReminder::replaceParams($message,"[SITE_URL]",$siteURL);
        $message 	= userreminderModelSendUserReminder::replaceParams($message,"[USERNAME]",$username);
        
        // Reset Passowrd link

        $message 	= userreminderModelSendUserReminder::replaceParams($message,"[PASSWORD_RESET]",$siteURL.\Joomla\CMS\Component\ComponentHelper::getParams('com_userreminder')->get('passwordReset',''));
                
        $message 	= userreminderModelSendUserReminder::replaceParams($message,"[OPTOUT]",$siteURL.'index.php?option=com_userreminder&task=optout&uid='.$userOptOutCode);
        $message = html_entity_decode($message, ENT_QUOTES);
		
		// if($chkEmailHTML_Remider == 0)
			// $message	= preg_replace("(\n)", "<br />", $message); // if content is plain text -> carriage returns it if have \n
        
		// Send email to user
        if ( ! $mailfrom  || ! $fromname ) {
            $fromname = $rows[0]->name;
            $mailfrom = $rows[0]->email;
        }

        //  COMMENT OUT while IN TESTING MODE
        //$successmail = true;
        $successmail = false;
      // if debug is set to on then do not send emails
      // Changes 2.5.9.13

      if (\Joomla\CMS\Component\ComponentHelper::getParams('com_userreminder')->get('debugUserReminder',0) == 1) {
         return true;
      }
      else{

        if (\Joomla\CMS\Component\ComponentHelper::getParams('com_userreminder')->get('enabledBccToAdmin',1) == 0) {
            $successmail = JFactory::getMailer()->sendMail($mailfrom, $fromname, $email, $subject, $message, $mode, null, $adminemails);
        }
        else {
            $successmail = JFactory::getMailer()->sendMail($mailfrom, $fromname, $email, $subject, $message, $mode, null);
        }

        return $successmail;
      }
    }

    function replaceParams($message,$parameter,$replacementString) {
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