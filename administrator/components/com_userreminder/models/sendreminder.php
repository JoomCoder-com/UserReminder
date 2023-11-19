<?php
/**
 * @version        UserReminder v1.0
 * @package        userreminder
 * @copyright      Copyright � 2021 - JoomCoder - All rights reserved.
 * @license        - http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 * @author         JoomCoder
 * @author         mail    support@joomcoder.com
 * @website        www.joomcoder.com
 */

// no direct access
use Joomla\CMS\Component\ComponentHelper;

defined('_JEXEC') or die('Restricted access');

jimport('joomla.application.component.model');
jimport('joomla.application.component.helper');

class userreminderModelSendReminder extends \Joomla\CMS\MVC\Model\BaseDatabaseModel
{

//function showuserReminder( $option )
	function sendReminder($displayHTML = true, $email_number_old = 0, $email_number_new = 0, $email_number = 0)
	{

        // load some libs
        jimport('joomla.user.helper');
		jimport('joomla.language.helper');

		// init some vars
		$db = \Joomla\CMS\Factory::getDBO();
        $params = ComponentHelper::getParams('com_usereminder');
		$adminemails = $params->get('bccEmailAddress', '');


        // force language reload again
		$lang = \Joomla\CMS\Factory::getLanguage();
		$lang->load('com_userreminder', JPATH_ROOT . "/administrator");


		// Clean up any records in the userreminder table where the user is now registered
        $this->cleanUpRegisteredUsers();

		// Clean up any records in the userreminder table where the user no longer exists in the user table
		$this->cleanUpNonExistingUsers();

		// find out who needs to be reminded about registration
		$nullDate = $db->getNullDate();
		$sql      = "";


		$selectfields1 = "SELECT #__users.id, datesent, 1 as type, remindernumber, type as savedtype, optoutcode, #__users.username as username, #__users.name as name, #__users.email as email , #__users.activation as activation ";
		$selectfields2 = "SELECT #__users.id, datesent, 2 as type, remindernumber, type as savedtype, optoutcode, #__users.username as username, #__users.name as name, #__users.email as email , #__users.activation as activation ";




		if ($params->get('enableActivateReminder', 1))
		{


			// check to see if we should check CB table or Joomla users table
			// added the sql below to exclude useres that are in the user group
			// AND #__users.id IN (SELECT user_id FROM #__user_usergroup_map WHERE group_id NOT IN (SELECT group_id FROM #__userreminder_optout_usergroups))
			if ($params->get('useCBActivation', 0))
			{
				if (!$params->get('enabledSendImed', 0))
				{
					$sql = "{SELECT} 
                          FROM #__users 
                          INNER JOIN #__comprofiler on #__comprofiler.user_id=#__users.id 
                          LEFT JOIN #__userreminder on #__users.id=userid 
                          LEFT JOIN #__userreminder_optout on #__users.id=#__userreminder_optout.user_id 
                          WHERE
                          ( 
                          (isnull(#__userreminder_optout.user_id) AND confirmed = 0) 
                          or 
                          (isnull(#__userreminder_optout.user_id) AND #__users.id=userid and type=1) 
                          )
                  		  AND (#__users.id NOT IN (SELECT user_id FROM #__user_usergroup_map WHERE group_id IN (SELECT group_id FROM #__userreminder_optout_usergroups)))";
				}
				else
				{
					$sql = "{SELECT} 
                          FROM #__users 
                          INNER JOIN #__comprofiler on #__comprofiler.user_id=#__users.id 
                          LEFT JOIN #__userreminder on #__users.id=userid 
                          LEFT JOIN #__userreminder_optout on #__users.id=#__userreminder_optout.user_id  
                          WHERE
                          ( 
                          (isnull(#__userreminder_optout.user_id) AND confirmed = 0 AND  date_add(registerDate, INTERVAL " . $params->get('numberOfDays', 1) . " DAY) < now()) 
                          or 
                          (isnull(#__userreminder_optout.user_id) AND #__users.id=userid and type=1) 
                          )
                  		  AND (#__users.id NOT IN (SELECT user_id FROM #__user_usergroup_map WHERE group_id IN (SELECT group_id FROM #__userreminder_optout_usergroups)))";
				}
			}
			else
			{
				if (!$params->get('enabledSendImed', 0))
				{
					$sql = "{SELECT} 
                          FROM #__users 
                          LEFT JOIN #__userreminder on id=userid 
                          LEFT JOIN #__userreminder_optout on #__users.id=#__userreminder_optout.user_id  
                          WHERE 
                          (isnull(#__userreminder_optout.user_id) AND activation != '' AND block >= 1 AND (lastvisitDate = '0000-00-00 00:00:00' || ISNULL(lastvisitDate)))
                          AND (#__users.id NOT IN (SELECT user_id FROM #__user_usergroup_map WHERE group_id IN (SELECT group_id FROM #__userreminder_optout_usergroups)))";
					//isnull(#__userreminder_optout.user_id) AND activation != ''  AND block >= 1 AND (lastvisitDate = '0000-00-00 00:00:00' || ISNULL(lastvisitDate)) ";
					//isnull(#__userreminder_optout.user_id) AND block >= 1 AND (lastvisitDate = '0000-00-00 00:00:00' || ISNULL(lastvisitDate)) ";

				}
				else
				{
					$sql = "{SELECT} 
                          FROM #__users 
                          LEFT JOIN #__userreminder on id=userid 
                          LEFT JOIN #__userreminder_optout on #__users.id=#__userreminder_optout.user_id 
                          WHERE 
                          (isnull(#__userreminder_optout.user_id) AND activation != '' AND  date_add(registerDate, INTERVAL " . $params->get('numberOfDays', 1) . " DAY) < now() AND block >= 1 AND (lastvisitDate = '0000-00-00 00:00:00' || ISNULL(lastvisitDate)))
                  		  AND (#__users.id NOT IN (SELECT user_id FROM #__user_usergroup_map WHERE group_id IN (SELECT group_id FROM #__userreminder_optout_usergroups)))";
					//isnull(#__userreminder_optout.user_id) AND activation != '' AND  date_add(registerDate, INTERVAL ".\Joomla\CMS\Component\$params->get('numberOfDays',1 )." DAY) < now() ";
					//isnull(#__userreminder_optout.user_id) AND block >= 1 AND (lastvisitDate = '0000-00-00 00:00:00' || ISNULL(lastvisitDate)) AND  date_add(registerDate, INTERVAL ".\Joomla\CMS\Component\$params->get('numberOfDays',1 )." DAY) < now() ";
				}
			}
			// in order to use the limit statment on a union need to add brackets
			$sql = "(" . $sql . ")";
		}

		// now add in the reminders for users who have activicated their accounts but never logged in
		if ($params->get('enableLoginReminder', 1))
		{
			if ($sql <> "")
			{
				$sql = $sql . " UNION ";
			}

			if (!$params->get('enabledSendImed', 0))
			{
				$sql = $sql . " ({SELECT2} 
                              FROM #__users 
                              LEFT JOIN #__userreminder on id=userid 
                              LEFT JOIN #__userreminder_optout on #__users.id=#__userreminder_optout.user_id  
                              WHERE 
                              (
                              (isnull(#__userreminder_optout.user_id) AND block = 0 AND (lastvisitDate = '0000-00-00 00:00:00' || ISNULL(lastvisitDate))) 
                              or 
                              (isnull(#__userreminder_optout.user_id) AND id=userid and type=2)
                              )
              				 AND (#__users.id NOT IN (SELECT user_id FROM #__user_usergroup_map WHERE group_id IN (SELECT group_id FROM #__userreminder_optout_usergroups))))";
			}
			else
			{
				$sql = $sql . " ({SELECT2} 
                            FROM #__users LEFT JOIN #__userreminder on id=userid 
                            LEFT JOIN #__userreminder_optout on #__users.id=#__userreminder_optout.user_id  
                            WHERE 
                            (
                            (isnull(#__userreminder_optout.user_id) AND block = 0 AND (lastvisitDate = '0000-00-00 00:00:00' || ISNULL(lastvisitDate)) AND  date_add(registerDate, INTERVAL " . $params->get('numberOfDays', 1) . " DAY) < now()) 
                            or 
                            (isnull(#__userreminder_optout.user_id) AND id=userid and type=2)
                            )
              				AND (#__users.id NOT IN (SELECT user_id FROM #__user_usergroup_map WHERE group_id IN (SELECT group_id FROM #__userreminder_optout_usergroups))))";
			}

		}

		// if being run from the user interface then display data/messages
	if ($displayHTML == true)
	{
		?>
        <script type="text/javascript">
            Joomla.submitbutton = function (task) {
                Joomla.submitform(task, document.getElementById('userreminder-form'));
            }
        </script>
        <form method="post" name="adminForm" id="userreminder-form" action="index.php?option=com_userreminder">
            <input type="hidden" name="task" value=""/>
        </form>

        <table class="table table-striped" id="articleList">
            <thead>
            <tr>
                <th width="30%"><?php print \Joomla\CMS\Language\Text::_('USERREMINDER_NAME'); ?></th>
                <th width="30%"><?php print \Joomla\CMS\Language\Text::_('USERREMINDER_EMAIL'); ?></th>
                <th align="left"><?php print \Joomla\CMS\Language\Text::_('USERREMINDER_DATESENT'); ?></th>
            </tr>
            </thead>
            <tbody>
			<?php
			}

			// ensure counter is at 1
			$iMaxRecords      = 1;
			$email_number_new = 0;

			// Check to see if there is anything to run
			if ($sql == '')
			{
				return;
			}

			// count total number of records that need to be processed
			$sqlcount = str_replace("{SELECT}", " Select count(*) as count ", $sql);
			$sqlcount = str_replace("{SELECT2}", " Select count(*) as count ", $sqlcount);
			$sqlcount = "Select sum(count) as count from (" . $sqlcount . ") as dummy";
			$db->setQuery($sqlcount);
			$users       = $db->loadObjectList();
			$recordcount = $users[0]->count;


			// if being run from the user interface then display data/messages
			if ($displayHTML == true)
			{
				//$progressmessage = 'Total number of Records to Process = '.$recordcount.'<br />';
				//$progressmessage = $progressmessage.'Records in each batch = '.$email_number.'<br />';
				$progressmessage = \Joomla\CMS\Language\Text::_('USERREMINDER_PROCESS_MESSAGE1') . ' = ' . $recordcount . '<br />';
				$progressmessage = $progressmessage . \Joomla\CMS\Language\Text::_('USERREMINDER_PROCESS_MESSAGE2') . ' = ' . $email_number . '<br />';
				?>
                <tr>
                    <td colspan=3><?php print \Joomla\CMS\Language\Text::_($progressmessage); ?></td>
                </tr>
				<?php


				if ($params->get('debugUserReminder', 0))
				{
					?>
                    <tr>
                        <td colspan=3><font color="red">
                                <h1><?php print \Joomla\CMS\Language\Text::_('USERREMINDER_DEBUG'); ?></h1>
								<?php print \Joomla\CMS\Language\Text::_('USERREMINDER_DEBUG_TEXT'); ?></font><br/>
                        </td>
                    </tr>
					<?php
				}

			}

			// create the sql statments
			$sql = str_replace("{SELECT}", $selectfields1, $sql);
			$sql = str_replace("{SELECT2}", $selectfields2, $sql);

			$recordsetcount = 1;
			$i              = 0;
			while ($recordsetcount > 0)
			{
			// increment the batch number
			$i = $i + 1;

			// query the Db for the next batch of records
			$sqlwithlimit = $sql . " LIMIT $email_number_new,$email_number ";
			$db->setQuery($sqlwithlimit);
			$users          = $db->loadObjectList();
			$recordsetcount = count($users);

			// if being run from the user interface then display data/messages
			if ($displayHTML == true)
			{
				$maxrecordnumber          = $email_number + $email_number_new;
				$display_email_number_new = $email_number_new + 1;

				$progressmessage = \Joomla\CMS\Language\Text::_('USERREMINDER_PROCESS_MESSAGE3') . ' ' . $i . '. ' . \Joomla\CMS\Language\Text::_('USERREMINDER_PROCESS_MESSAGE4') . ' ' . $display_email_number_new . ' to ' . $maxrecordnumber . '. ';

				$progressmessage = $progressmessage . ' ' . \Joomla\CMS\Language\Text::_('USERREMINDER_PROCESS_MESSAGE5');
				if ($params->get('debugUserReminder', 0))
				{
					$progressmessage = $progressmessage . ' (' . memory_get_usage(true) . ')';
				}

				?>
                <tr>
                    <td colspan=3><?php print \Joomla\CMS\Language\Text::_($progressmessage); ?></td>
                </tr>
				<?php
			}

			// increment the LIMITE starting position by the number required
			$email_number_new = $email_number_new + $email_number;

			// now loop through all records in the record set
			if (count($users) > 0)
			{
			foreach ($users

			as $user)
			{
			if (!empty($user))
			{
			// check to see what action we should be doing
			if ($user->datesent == "" or ($user->savedtype != "2" and $user->type == "2"))
			{
				// check to see if the maximum number of emails is going to be exceeded
				if ($iMaxRecords > $params->get('maxemailstosend', 20))
				{
					// maximum number of emails reached. Exit the routine od sending emails
					if ($displayHTML == true)
					{
						?>
                        <tr>
                            <td colspan=3><?php print \Joomla\CMS\Language\Text::_('MAXIMUM_EMAILS_REACHED'); ?></td>
                        </tr>
						<?php
					}
					//set recordcount to 0 so that we exit the while loop
					$recordsetcount = 0;
					break;
					//$action=\Joomla\CMS\Language\Text::_('USERREMINDER_ACTIONMAXEMAILS2');
				}
				else
				{
					$action      = \Joomla\CMS\Language\Text::_('USERREMINDER_ACTIONSEND');
					$iMaxRecords = $iMaxRecords + 1;
				}
			}
			else
			{
				$check_date = strtotime($user->datesent);
				$d9         = strtotime('-' . $params->get('numberOfDays', 1) . ' days', time());
				if ($check_date < $d9)
				{
					// check to see if another reminder needs to be sent
					if ($params->get('numberOfReminders', 1) > $user->remindernumber)
					{
						// check to see if the maximum number of emails is going to be exceeded
						if ($iMaxRecords > $params->get('maxemailstosend', 20))
						{
							if ($displayHTML == true)
							{
								?>
                                <tr>
                                    <td colspan=3><?php print \Joomla\CMS\Language\Text::_('MAXIMUM_EMAILS_REACHED'); ?></td>
                                </tr>
								<?php
							}
							//set recordcount to 0 so that we exit the while loop
							$recordsetcount = 0;
							break;
						}
						else
						{
							$action      = \Joomla\CMS\Language\Text::_('USERREMINDER_ACTIONSEND');
							$iMaxRecords = $iMaxRecords + 1;
						}
					}
					else
					{
						$action = \Joomla\CMS\Language\Text::_('USERREMINDER_ACTIONDELETED');
					}
				}
				else
				{
					if ($user->type == 2)
					{
						$action = \Joomla\CMS\Language\Text::_('USERREMINDER_ACTION_LOGINNONE');
					}
					else
					{
						$action = \Joomla\CMS\Language\Text::_('USERREMINDER_ACTION_NONE');
					}
				}
			}

			$reminderNumber = $user->remindernumber;
			$userOptOutCode = $user->optoutcode;
			$type           = $user->type;
			//$user = \Joomla\CMS\Factory::getUser($user->id);

			// if requested delete users who have been notifed and have not completed registration
			if ($type == 2)
			{
				if ($params->get('enableDeleteUsersLogin', 0) && $action == \Joomla\CMS\Language\Text::_('USERREMINDER_ACTIONDELETE'))
				{
					$user2 = \Joomla\CMS\Factory::getUser($user->id);
					$user2->delete(false);
					unset($user2);
				}
                elseif ($action == \Joomla\CMS\Language\Text::_('USERREMINDER_ACTIONDELETED'))
				{
					$action = \Joomla\CMS\Language\Text::_('USERREMINDER_ACTIONNOTDELETED');
				}
			}
			else
			{
				if ($params->get('enableDeleteUsers', 0) && $action == \Joomla\CMS\Language\Text::_('USERREMINDER_ACTIONDELETE'))
				{
					$user2 = \Joomla\CMS\Factory::getUser($user->id);
					$user2->delete(false);
					unset($user2);
				}
                elseif ($action == \Joomla\CMS\Language\Text::_('USERREMINDER_ACTIONDELETED'))
				{
					$action = \Joomla\CMS\Language\Text::_('USERREMINDER_ACTIONNOTDELETED');
				}
			}

			// Changes 2.5.9.13 skip displaying this is no action is to be performed
			if ($action != \Joomla\CMS\Language\Text::_('USERREMINDER_ACTION_NONE') && $action != \Joomla\CMS\Language\Text::_('USERREMINDER_ACTION_LOGINNONE'))
			{

			if ($displayHTML == true)
			{
			?>
            <tr class="row0">
                <td width="350"><?php echo $user->name; ?></td>
                <td width="150"><?php echo $user->email; ?></td>
		<?php
	}

		$sourceimg = "images/publish_x.png";

		if ($action == \Joomla\CMS\Language\Text::_('USERREMINDER_ACTIONSEND'))
		{
			// check to see if the user has an optout code
			if ($userOptOutCode == "")
			{
				$userOptOutCode = \Joomla\CMS\Application\ApplicationHelper::getHash(\Joomla\CMS\User\UserHelper::genRandomPassword());
			}
			// send email reminder
			if (userreminderModelSendReminder::_sendMail($user, $adminemails, $type, $userOptOutCode))
			{
				// check to see if we need to insert or update a record
				// Record that we are sending a reminder
				if ($reminderNumber > 0)
				{
					$reminderNumber = $reminderNumber + 1;
					$q              = "UPDATE #__userreminder set datesent=NOW(), remindernumber=" . $reminderNumber . ", type=" . $type . ", optoutcode='" . $userOptOutCode . "' where userid=" . $user->id . "";
				}
				else
				{
					$q = "INSERT INTO #__userreminder (`userid`, `datesent`, `remindernumber`, `type`, `optoutcode`) values (" . $user->id . " , NOW(), 1, " . $type . ",'" . $userOptOutCode . "')";
				}
				$db->setQuery($q);
				$db->execute();

				$sourceimg = "images/tick.png";
			}
		}

		if ($action == \Joomla\CMS\Language\Text::_('USERREMINDER_ACTIONSEND'))
		{
			if ($type == 2)
			{
				if ($displayHTML == true)
				{
					echo '<td  align="left">' . \Joomla\CMS\Language\Text::_('USERREMINDER_LOGIN_REMINDER_SENT') . '</td>';
				}
				//insert log
				$description = "Reminders Run: " . \Joomla\CMS\Language\Text::_('USERREMINDER_LOGIN_REMINDER_SENT');
				$date        = date('Y-m-d H:i:s');
				$q2          = "INSERT INTO #__userreminder_log (`userId`, `username`, `description`, `date`) VALUES ('" . $user->id . "', '" . $user->username . "', '" . $description . "', '" . $date . "');";
				$db->setQuery($q2);
				$db->execute();

			}
			else
			{
				if ($displayHTML == true)
				{
					echo '<td  align="left">' . \Joomla\CMS\Language\Text::_('USERREMINDER_ACTIVATE_REMINDER_SENT') . '</td>';
				}
				//insert log
				$description = "Reminders Run: " . \Joomla\CMS\Language\Text::_('USERREMINDER_ACTIVATE_REMINDER_SENT');
				$date        = date('Y-m-d H:i:s');
				$q2          = "INSERT INTO #__userreminder_log (`userId`, `username`, `description`, `date`) VALUES ('" . $user->id . "', '" . $user->username . "', '" . $description . "', '" . $date . "');";
				$db->setQuery($q2);
				$db->execute();
			}
		}
		else
		{
			if ($displayHTML == true)
			{
				echo '<td  align="left">' . $action . '</td>';
			}
			//insert log // BETAFIXES
			if ($action == \Joomla\CMS\Language\Text::_('USERREMINDER_ACTIONDELETED'))
			{
				$description = "Reminders Run: " . $action;
				$date        = date('Y-m-d H:i:s');
				$q2          = "INSERT INTO #__userreminder_log (`userId`, `username`, `description`, `date`) VALUES ('" . $user->id . "', '" . $user->username . "', '" . $description . "', '" . $date . "');";
				$db->setQuery($q2);
				$db->execute();
			}
		}
		if ($displayHTML == true)
		{
			echo '</tr>';
		}
	}
	} // skip no action	
	} // for loop

	}  // count test
	}

		if ($displayHTML == true)
		{
			echo '</tbody></table>';
		}
	}


	function _sendMail(&$user, &$adminemails, &$type, &$optoutcode)
	{

        // init some vars
		$mainframe = \Joomla\CMS\Factory::getApplication();
		$params = ComponentHelper::getParams('com_usereminder');
		$db = \Joomla\CMS\Factory::getDBO();
		$name     = $user->name;
		$email    = $user->email;
		$username = $user->username;
		$usersConfig = \Joomla\CMS\Component\ComponentHelper::getParams('com_users');
		$sitename    = $mainframe->getCfg('sitename');
		$mailfrom    = $mainframe->getCfg('mailfrom');
		$fromname    = $mainframe->getCfg('fromname');
		$siteURL     = \Joomla\CMS\Uri\Uri::root();

		// get the parameter for this component
		//$regConfig = &\Joomla\CMS\Component\ComponentHelper::getParams( 'com_userreminder' );
		$query = "SELECT params FROM #__extensions WHERE `element`='com_userreminder'";
		$db->setQuery($query);
		$result = $db->loadRow();

		$tableParam  = str_replace("\r\n", "<br />", $result[0]);
		$usersConfig = json_decode($tableParam, true);

		// check to see if Joomla or CB activation link should be used
		if ($params->get('useCBActivation', 0))
		{
			// Find the activation code for community builder
			$q = "Select cbactivation FROM #__comprofiler where  #__comprofiler.user_id =" . $user->id;
			$db->setQuery($q);
			$db->execute();


			$cbuser = $db->loadObject();

			// set the activation url
			//$activationURL = $siteURL."index.php?option=com_comprofiler&task=confirm&confirmcode=".$cbuser->cbactivation;

			$activationURL = $siteURL . $params->get('activateURL', '') . $cbuser->cbactivation;
		}
		else
		{
			//$activationURL = $siteURL."index.php?option=com_users&task=activate&activation=".$user->get('activation');
			//$activationURL = $siteURL.$this->getParamData( $usersConfig,'activateURL',"" ).$user->get('activation');

			$activationURL = $siteURL . $params->get('activateURL', '') . $user->activation;
		}

		if ($type == 2)
		{
			// create the message to be sent to users who are active but never logged in

			// get email subject

			if ($params->get('regLoginEmailSubject', '') == "")
			{
				$subject = \Joomla\CMS\Language\Text::_('USERREMINDER_LOGINREMINDER_DETAILS_FOR');
			}
			else
			{

				$subject = $params->get('regLoginEmailSubject', '');
			}
			//$subject 	= sprintf ( $subject, $sitename);
			$subject = userreminderModelSendReminder::replaceParams($subject, "[NAME]", $name);
			$subject = userreminderModelSendReminder::replaceParams($subject, "[SITE_NAME]", $sitename);
			$subject = html_entity_decode($subject, ENT_QUOTES);

			// get email html if $mail_html = 1
			$mode = 1;

			if ($params->get('regLoginEmailBodyHTML', '') == "")
			{
				$message = \Joomla\CMS\Language\Text::_('USERREMINDER_SEND_MSG_LOGINREMINDER');
				$message = preg_replace("(\n)", "<br />", $message); // if content is plain text -> carriage returns it if have \n
			}
			else
			{

				$message = $params->get('regLoginEmailBodyHTML', '');
			}

			$passwordReset = $siteURL . $params->get('passwordReset', 'index.php?option=com_users&view=reset');
			$passwordReset = "<a href='$passwordReset'>$passwordReset</a>";
            $optOutUrl =  $siteURL . 'index.php?option=com_userreminder&task=optout&uid=' . $optoutcode;
			$optOutUrl = "<a href='$optOutUrl'>$optOutUrl</a>";
			$websiteURL = "<a href='$siteURL'>$siteURL</a>";

			$message = userreminderModelSendReminder::replaceParams($message, "[NAME]", $name);
			$message = userreminderModelSendReminder::replaceParams($message, "[SITE_NAME]", $sitename);
			$message = userreminderModelSendReminder::replaceParams($message, "[SITE_URL]", $websiteURL);
			$message = userreminderModelSendReminder::replaceParams($message, "[USERNAME]", $username);
			$message = userreminderModelSendReminder::replaceParams($message, "[PASSWORD_RESET]", $passwordReset);
			$message = userreminderModelSendReminder::replaceParams($message, "[OPTOUT]", $optOutUrl);
			$message = html_entity_decode($message, ENT_QUOTES);

			// if($chkEmailHTML_Login == 0)
			// $message	= preg_replace("(\n)", "<br />", $message); // if content is plain text -> carriage returns it if have \n
		}
		else
		{
			// create the message to be sent to users to activate
			// get email subject

			if ($params->get('regActivationEmailSubject', '') == "")
			{
				$subject = \Joomla\CMS\Language\Text::_('USERREMINDER_REMINDER_DETAILS_FOR');
			}
			else
			{

				$subject = $params->get('regActivationEmailSubject', '');
			}
			//$subject 	= sprintf ( $subject, $name, $sitename);
			$subject = userreminderModelSendReminder::replaceParams($subject, "[NAME]", $name);
			$subject = userreminderModelSendReminder::replaceParams($subject, "[SITE_NAME]", $sitename);
			$subject = html_entity_decode($subject, ENT_QUOTES);



			$mode = 1;

			// send email html (custom)
			if ($params->get('regActivationEmailBodyHTML', '') == "")
				$message = \Joomla\CMS\Language\Text::_('USERREMINDER_SEND_MSG_REMINDER');
			else
				$message = $params->get('regActivationEmailBodyHTML', '');


			$passwordReset = $siteURL . $params->get('passwordReset', 'index.php?option=com_users&view=reset');
			$passwordReset = "<a href='$passwordReset'>$passwordReset</a>";
			$optOutUrl =  $siteURL . 'index.php?option=com_userreminder&task=optout&uid=' . $optoutcode;
			$optOutUrl = "<a href='$optOutUrl'>$optOutUrl</a>";
			$websiteURL = "<a href='$siteURL'>$siteURL</a>";
			$activationURL = "<a href='$activationURL'>$activationURL</a>";


			$message = userreminderModelSendReminder::replaceParams($message, "[NAME]", $name);
			$message = userreminderModelSendReminder::replaceParams($message, "[SITE_NAME]", $sitename);
			$message = userreminderModelSendReminder::replaceParams($message, "[ACTIVATE_URL]", $activationURL);
			$message = userreminderModelSendReminder::replaceParams($message, "[SITE_URL]", $websiteURL);
			$message = userreminderModelSendReminder::replaceParams($message, "[USERNAME]", $username);
			$message = userreminderModelSendReminder::replaceParams($message, "[PASSWORD_RESET]", $passwordReset);
			$message = userreminderModelSendReminder::replaceParams($message, "[OPTOUT]", $optOutUrl);
			$message = html_entity_decode($message, ENT_QUOTES);

		}

		// Send email to user
		if (!$mailfrom || !$fromname)
		{
			$fromname = $rows[0]->name;
			$mailfrom = $rows[0]->email;
		}

		//  COMMENT OUT while IN TESTING MODE
		//$successmail = true;
		$successmail = false;
		// if debug is set to on then do not send emails
		// Changes 2.5.9.13
		if ($params->get('debugUserReminder', 0))
		{
			return true;
		}
		else
		{
			if (!$params->get('enabledBccToAdmin', 1))
			{
				$successmail = \Joomla\CMS\Factory::getMailer()->sendMail($mailfrom, $fromname, $email, $subject, $message, true, null, $adminemails);
			}
			else
			{
				$successmail = \Joomla\CMS\Factory::getMailer()->sendMail($mailfrom, $fromname, $email, $subject, $message, true, null);
			}

			return $successmail;
		}
	}


    public function cleanUpRegisteredUsers(){
        $db = \Joomla\CMS\Factory::getDbo();
	    $q = "DELETE #__userreminder FROM #__userreminder INNER JOIN #__users ON  #__userreminder.userid=#__users.id WHERE (lastvisitDate <> '0000-00-00 00:00:00' AND !ISNULL(lastvisitDate) AND type=2) or (block=0 and type=1)";
	    $db->setQuery($q);
	    $result = $db->execute();

    }

    public function cleanUpNonExistingUsers(){
	    $db = \Joomla\CMS\Factory::getDbo();
	    $q = "DELETE FROM #__userreminder where  #__userreminder.userid not in (Select #__users.id from #__users)";
	    $db->setQuery($q);
	    $result = $db->execute();
    }

	public function replaceParams($message,$parameter,$replacementString) {
		$newString = "";
		$newString = str_replace($parameter, $replacementString, $message);
		return $newString;
	}


}