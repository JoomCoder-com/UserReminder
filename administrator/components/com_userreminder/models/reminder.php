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
use Joomla\CMS\Language\Text;

defined('_JEXEC') or die('Restricted access');

jimport('joomla.application.component.model');
jimport('joomla.application.component.helper');
jimport('joomla.html.pagination');


class userreminderModelReminder extends \Joomla\CMS\MVC\Model\BaseDatabaseModel
{

	// PAGINATION ADDED

	/**
	 * data array
	 *
	 * @var array
	 */
	var $_data = null;
	var $_data2 = null;

	/**
	 * Items total
	 * @var integer
	 */
	var $_total = null;
	var $_total2 = null;

	/**
	 * Pagination object
	 * @var object
	 */
	var $_pagination = null;
	var $_pagination2 = null;

	function __construct()
	{
		parent::__construct();

		global $option;
		//global $mainframe;
		//$mainframe = & \Joomla\CMS\Factory::getApplication();
		$mainframe = \Joomla\CMS\Factory::getApplication();

		// Get pagination request variables
		$limit      = $mainframe->getUserStateFromRequest('global.list.limit', 'limit', $mainframe->getCfg('list_limit'), 'int');
		$limitstart = \Joomla\CMS\Factory::getApplication()->input->get('regrim_limitstart', 0, '', 'int');

		// In case limit has been changed, adjust it
		$limitstart = ($limit != 0 ? (floor($limitstart / $limit) * $limit) : 0);

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);

		// Get pagination request variables2
		$limit2      = $mainframe->getUserStateFromRequest('global.list.limit', 'limit', $mainframe->getCfg('list_limit'), 'int');
		$limitstart2 = \Joomla\CMS\Factory::getApplication()->input->get('reglog_limitstart', 0, '', 'int');

		// In case limit has been changed, adjust it
		$limitstart2 = ($limit2 != 0 ? (floor($limitstart2 / $limit2) * $limit2) : 0);

		$this->setState('limit2', $limit2);
		$this->setState('limitstart2', $limitstart2);

	}

	function getTotal()
	{
		// Load the content if it doesn't already exist
		if (empty($this->_total))
		{
			$this->_data = $this->showuserReminder();
		}

		return $this->_total;
	}

	function getPagination()
	{
		// Load the content if it doesn't already exist
		if (empty($this->_pagination))
		{
			$this->_pagination = new  \Joomla\CMS\Pagination\Pagination($this->getTotal(), $this->getState('limitstart'), $this->getState('limit'), 'regrim_');
		}

		return $this->_pagination;
	}

	function getTotal2()
	{
		// Load the content if it doesn't already exist
		if (empty($this->_total2))
		{
			$this->_data = $this->getRegisteredUsersNeverLogged();
		}

		return $this->_total2;
	}

	function getPagination2()
	{
		// Load the content if it doesn't already exist
		if (empty($this->_pagination2))
		{
			$this->_pagination2 = new  \Joomla\CMS\Pagination\Pagination($this->getTotal2(), $this->getState('limitstart2'), $this->getState('limit2'), 'reglog_');
		}

		return $this->_pagination2;
	}

	function showuserReminder()
	{
		global $mainframe, $iMaxRecords;

		$iMaxRecords = 1;
		$params      = ComponentHelper::getParams('com_userreminder');

		$db = \Joomla\CMS\Factory::getDBO();


		$list = array();

		// Changes 2.5.9.13
		if ($params->get('debugUserReminder', 0))
		{
			?>
            <tr>
                <td colspan=3><font color="red">
                        <H1><?php print Text::_('USERREMINDER_DEBUG'); ?></H1><?php print Text::_('USERREMINDER_DEBUG_TEXT'); ?>
                    </font><br/></td>
            </tr>
			<?php
		}

		// find out who needs to be reminded about registration
		// check to see if we should check CB table or Joomla users table
		// added the sql below to exclude useres that are in the user group
		// AND #__users.id IN (SELECT user_id FROM #__user_usergroup_map WHERE group_id NOT IN (SELECT group_id FROM #__userreminder_optout_usergroups))
		if ($params->get('useCBActivation', 0))
		{
			if (!$params->get('enabledSendImed', 0))
			{

				// LEFT JOIN #__userreminder_optout on #__users.id=#__userreminder_optout.user_id
				$sql = "SELECT #__users.id, email, activation, block, registerDate, lastvisitDate, activation, datesent, remindernumber 
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

				$sql = "SELECT #__users.id, email, activation, block, registerDate, lastvisitDate, activation, datesent, remindernumber 
                      FROM #__users 
                      INNER JOIN #__comprofiler on #__comprofiler.user_id=#__users.id 
                      LEFT JOIN #__userreminder on #__users.id=userid 
                      LEFT JOIN #__userreminder_optout on #__users.id=#__userreminder_optout.user_id 
                      WHERE
                      ( 
                      (isnull(#__userreminder_optout.user_id) AND confirmed = 0 AND date_add(registerDate, INTERVAL " . $params->get('numberOfDays', 1) . " DAY) < now()) 
                      or 
                      (isnull(#__userreminder_optout.user_id) AND #__users.id=userid and type=1)
                      )
              		  AND (#__users.id NOT IN (SELECT user_id FROM #__user_usergroup_map WHERE group_id IN (SELECT group_id FROM #__userreminder_optout_usergroups)))";
			}
		}
		else
		{

			if ($params->get('enabledSendImed', 0))
			{


				$sql = "SELECT  id, email, activation, block, registerDate, lastvisitDate, activation, datesent, remindernumber 
                      FROM #__users 
                      LEFT JOIN #__userreminder on id = userid 
                      LEFT JOIN #__userreminder_optout on #__users.id = #__userreminder_optout.user_id 
                      WHERE
                      ( 
                            (
                            isnull(#__userreminder_optout.user_id) AND 
                            (activation != ''  OR activation != 0 || !ISNULL(activation)) AND 
                            date_add(registerDate, INTERVAL " . $params->get('numberOfDays', 1) . " DAY) < now() AND
                             block >= 1 AND  ISNULL(lastvisitDate)
                             ) 
                        OR 
                            (isnull(#__userreminder_optout.user_id) AND id = userid AND type = 1)
                      )
              		  AND 
              		   (#__users.id NOT IN (SELECT user_id FROM #__user_usergroup_map WHERE group_id IN (SELECT group_id FROM #__userreminder_optout_usergroups)))
              		  ";


			}
			else
			{

				$sql = "SELECT  id, email, activation, block, registerDate, lastvisitDate, activation, datesent, remindernumber 
                      FROM #__users 
                      LEFT JOIN #__userreminder on id=userid 
                      LEFT JOIN #__userreminder_optout on #__users.id=#__userreminder_optout.user_id 
                      WHERE
                      ( 
                      (isnull(#__userreminder_optout.user_id) AND activation != '' AND block >= 1 AND ISNULL(lastvisitDate)) 
                      or 
                      (isnull(#__userreminder_optout.user_id) AND id=userid and type=1)
                      )
              		  AND (#__users.id NOT IN (SELECT user_id FROM #__user_usergroup_map WHERE group_id IN (SELECT group_id FROM #__userreminder_optout_usergroups)))";
				//(isnull(#__userreminder_optout.user_id) AND activation != '')
				//(isnull(#__userreminder_optout.user_id) AND block >= 1 AND lastvisitDate = '0000-00-00 00:00:00')


			}
			//$sql = "SELECT id, email, block, registerDate, lastvisitDate, activation, datesent, remindernumber FROM #__users LEFT JOIN #__userreminder on id=userid WHERE (block >= 1 AND lastvisitDate = '0000-00-00 00:00:00' AND  date_add(registerDate, INTERVAL ".\Joomla\CMS\Component\$params->get('numberOfDays',1 )." DAY) < now()) or (id=userid and type=1)";
		}

		$db->setQuery($sql, $this->getState('limitstart'), $this->getState('limit'));
		$rows = $db->loadObjectList();

		// PAGINATION ADDED
		$db->setQuery("SELECT FOUND_ROWS();");
		$this->_total = $db->loadResult();

		// ensure counter is at 1
		$iMaxRecords = 1;

		if (count($rows) > 0)
		{
			foreach ($rows as $row)
			{
				//$user	= &\Joomla\CMS\Factory::getUser($row->id);
				$user = \Joomla\CMS\Factory::getUser($row->id);
				$name = $user->name . " [" . $user->username . "]";
				if (!$row->block)
				{
					$action = Text::_('USERREMINDER_USER_REGISTERED');
				}
                elseif (!$params->get('enableActivateReminder', 1))
					// check to see if this function has been enabled
				{
					$action = Text::_('USERREMINDER_ACTIONNOTENABLED');

				}
				// check to see if a reminder needs to be sent
                elseif ($row->datesent == "")
				{
					// check to see if the maximum number of emails is going to be exceeded
					if ($iMaxRecords > $params->get('maxemailstosend', 20))
					{
						//$action=\Joomla\CMS\Language\Text::_('USERREMINDER_ACTIONMAXEMAILS1');
						$action = Text::_('USERREMINDER_ACTIONSEND');
					}
					else
					{
						$action      = Text::_('USERREMINDER_ACTIONSEND');
						$iMaxRecords = $iMaxRecords + 1;
					}
				}
				else
				{
					$check_date = strtotime($row->datesent);
					$d9         = strtotime('-' . $params->get('numberOfDays', 1) . ' days', time());
					if ($check_date < $d9)
					{
						// check to see if another reminder needs to be sent
						if ($params->get('numberOfReminders', 1) > $row->remindernumber)
						{
							// check to see if the maximum number of emails is going to be exceeded
							if ($iMaxRecords > $params->get('maxemailstosend', 20))
							{
								//$action=\Joomla\CMS\Language\Text::_('USERREMINDER_ACTIONMAXEMAILS1');
								$action = Text::_('USERREMINDER_ACTIONSEND');
							}
							else
							{
								$action      = Text::_('USERREMINDER_ACTIONSEND');
								$iMaxRecords = $iMaxRecords + 1;
							}
						}
						else
						{
							// delete the user
							if ($params->get('enableDeleteUsers', 0))
							{
								$action = Text::_('USERREMINDER_ACTIONDELETE');
							}
							else
							{
								$action = Text::_('USERREMINDER_ACTIONNOTDELETED');
							}
						}
					}
					else
					{
						$action = Text::_('USERREMINDER_ACTION_NONE');
					}
				}

				$list[] = array("name" => $name, "email" => $row->email, "regDate" => $row->registerDate, "remindersent" => $row->datesent, "action" => $action, "remindernumber" => $row->remindernumber);
			}
		}

		$this->iMaxRecords = $iMaxRecords;

		return $list;

	}

	/*
	 * Get the registered user who have never logged in, but have activated their registration
	 */
	public function getRegisteredUsersNeverLogged()
	{

		global $iMaxRecords;

		// some inits
		$list                = array();
		$sendFirstImmediatly = ComponentHelper::getParams('com_userreminder')->get('enabledSendImed', 0);
        $numberOfDaysBeforeSending = ComponentHelper::getParams('com_userreminder')->get('numberOfDays', 1);

		$db = \Joomla\CMS\Factory::getDBO();

		// get new query
		$query = $db->getQuery(true);

		// query
		$query->select('id, email, activation, block, registerDate, lastvisitDate, activation, datesent, type, remindernumber')
			->from('#__users')
			->leftJoin('#__userreminder','id = userid')
			->leftJoin('#__userreminder_optout','#__users.id = #__userreminder_optout.user_id');

		// set subqueries
		$subQueryGroupsIds = "SELECT group_id FROM #__userreminder_optout_usergroups"; // get opt-out user groups ids
		$subQueryUsersIds  = "SELECT user_id FROM #__user_usergroup_map WHERE group_id IN ($subQueryGroupsIds)"; // get opt-out users ids

		// set wheres
		$excludeOptOutUsers = "#__users.id NOT IN ($subQueryUsersIds)"; // exclude optOut users ids


		if ($sendFirstImmediatly)
		{ // Send 1st reminder immediately

			$query->where("
			    (
			        (ISNULL(#__userreminder_optout.user_id) AND block = 0 AND ISNULL(lastvisitDate))  OR  
			        (ISNULL(#__userreminder_optout.user_id) AND id=userid and type=2)
			    ) 
			    
			    AND $excludeOptOutUsers	    
			");

		}
		else
		{ //  wait until the number of days

			$query->where("
                   ( 
                   (isnull(#__userreminder_optout.user_id) AND block = 0 AND ISNULL(lastvisitDate) AND date_add(registerDate, INTERVAL $numberOfDaysBeforeSending DAY) < now()) 
                   or 
                   (isnull(#__userreminder_optout.user_id) AND id=userid and type=2)
                   )
           		   AND $excludeOptOutUsers"
			);

		}

		$db->setQuery($query, $this->getState('limitstart2'), $this->getState('limit2'));

		$rows = $db->loadObjectList();

		// PAGINATION ADDED
		$db->setQuery("SELECT FOUND_ROWS();");

		$this->_total2 = $db->loadResult();


		// set the max counter
		$iMaxRecords = $this->iMaxRecords;

		if (count($rows) > 0)
		{
			foreach ($rows as $row)
			{

                // get user info
				$user = \Joomla\CMS\Factory::getUser($row->id);
				$name = $user->name . " [" . $user->username . "]";

				if ($row->block >= 1)
				{
					$action = Text::_('USERREMINDER_USER_NOTREGISTERED');
				}
                elseif (
					!$row->block and
					(
						$row->lastvisitDate != '0000-00-00 00:00:00' &&
						!is_null($row->lastvisitDate) &&
						!empty($row->lastvisitDate)
					)
				)
				{

					$action = Text::_('USERREMINDER_USER_HASLOGGEDIN');
				}
                elseif (!ComponentHelper::getParams('com_userreminder')->get('enableLoginReminder', 1))
					// check to see if this function has been enabled
				{
					$action = Text::_('USERREMINDER_ACTIONNOTENABLED');
				}
				// check to see if a reminder needs to be sent
                elseif ($row->datesent == "" or $row->type != "2")
				{
					// check to see if the maximum number of emails is going to be exceeded
					if ($iMaxRecords > ComponentHelper::getParams('com_userreminder')->get('maxemailstosend', 20))
					{
						$action = Text::_('USERREMINDER_ACTIONSEND');
					}
					else
					{
						$action      = Text::_('USERREMINDER_ACTIONSEND');
						$iMaxRecords = $iMaxRecords + 1;
					}
				}
				else
				{
					$check_date = strtotime($row->datesent);
					$d9         = strtotime('-' . ComponentHelper::getParams('com_userreminder')->get('numberOfDays', 1) . ' days', time());
					if ($check_date < $d9)
					{
						// check to see if another reminder needs to be sent
						if (ComponentHelper::getParams('com_userreminder')->get('numberOfReminders', 1) > $row->remindernumber)
						{
							// check to see if the maximum number of emails is going to be exceeded
							if ($iMaxRecords > ComponentHelper::getParams('com_userreminder')->get('maxemailstosend', 20))
							{
								//$action=\Joomla\CMS\Language\Text::_('USERREMINDER_ACTIONMAXEMAILS1');
								$action = Text::_('USERREMINDER_ACTIONSEND');
							}
							else
							{
								$action      = Text::_('USERREMINDER_ACTIONSEND');
								$iMaxRecords = $iMaxRecords + 1;
							}
						}
						else
						{
							// delete the user
							if (ComponentHelper::getParams('com_userreminder')->get('enableDeleteUsersLogin', 0))
							{
								$action = Text::_('USERREMINDER_ACTIONDELETE');
							}
							else
							{
								$action = Text::_('USERREMINDER_ACTIONNOTDELETED');
							}
						}
					}
					else
					{
						$action = Text::_('USERREMINDER_ACTION_LOGINNONE');
					}
				}

				$list[] = array("name" => $name, "email" => $row->email, "regDate" => $row->registerDate, "remindersent" => $row->datesent, "action" => $action, "remindernumber" => $row->remindernumber);
			}
		}

		return $list;
	}

	function sendTestMail()
	{
		//$mainframe = & \Joomla\CMS\Factory::getApplication();
		$mainframe = \Joomla\CMS\Factory::getApplication();

		//$db		=& \Joomla\CMS\Factory::getDBO();
		$db = \Joomla\CMS\Factory::getDBO();

		// get current user
		//$user =& \Joomla\CMS\Factory::getApplication()->getIdentity();
		$user = \Joomla\CMS\Factory::getApplication()->getIdentity();


		?>
        <table class="adminheading">
            <tr>
                <td>
                    <h1><?php print Text::_('USERREMINDER_TEST') . ' ' . $user->get('email'); ?></h1>
                </td>
            </tr>
        </table>
		<?php

		$name     = $user->get('name');
		$email    = $user->get('email');
		$username = $user->get('username');

		//$usersConfig 	= &\Joomla\CMS\Component\ComponentHelper::getParams( 'com_users' );
		$usersConfig = \Joomla\CMS\Component\ComponentHelper::getParams('com_users');
		$sitename    = $mainframe->getCfg('sitename');
		$mailfrom    = $mainframe->getCfg('mailfrom');
		$fromname    = $mainframe->getCfg('fromname');
		$siteURL     = \Joomla\CMS\Uri\Uri::root();


		// ********************************************************************************
		// check to see if Joomla or CB activation link should be used
		// ********************************************************************************


		if (ComponentHelper::getParams('com_userreminder')->get('useCBActivation', 0))
		{
			// Find the activation code for community builder
			$q = "Select cbactivation FROM #__comprofiler where  #__comprofiler.user_id =" . $user->id;
			$db->setQuery($q);
			$db->execute();

			$cbuser = $db->loadObject();

			// set the activation url
			//$activationURL = $siteURL."index.php?option=com_comprofiler&task=confirm&confirmcode=".$cbuser->cbactivation;

			$activationURL = $siteURL . \Joomla\CMS\Component\ComponentHelper::getParams('com_userreminder')->get('activateURL', '') . $cbuser->cbactivation;
		}
		else
		{
			//$activationURL = $siteURL."index.php?option=com_users&task=activate&activation=".$user->get('activation');
			$activationURL = $siteURL . \Joomla\CMS\Component\ComponentHelper::getParams('com_userreminder')->get('activateURL', '') . $user->get('activation');
		}


		// Check and Set sender details
		if (!$mailfrom || !$fromname)
		{
			$fromname = $rows[0]->name;
			$mailfrom = $rows[0]->email;
		}

		// ********************************************************************************
		// Create and send TEST email for users who have never logged in
		// ********************************************************************************

		if (\Joomla\CMS\Component\ComponentHelper::getParams('com_userreminder')->get('regActivationEmailSubject', '') == "")
		{
			$subject = Text::_('USERREMINDER_REMINDER_DETAILS_FOR');
		}
		else
		{

			$subject = \Joomla\CMS\Component\ComponentHelper::getParams('com_userreminder')->get('regActivationEmailSubject', '');
		}

		//$subject 	= sprintf ( $subject, $name, $sitename);
		$subject = userreminderModelReminder::replaceParams($subject, "[NAME]", $name);
		$subject = userreminderModelReminder::replaceParams($subject, "[SITE_NAME]", $sitename);
		$subject = html_entity_decode($subject, ENT_QUOTES);
		// Get email body// check email html

		$mode = 1;
		// send mail html. If no HTML then use plain text

		if (\Joomla\CMS\Component\ComponentHelper::getParams('com_userreminder')->get('regActivationEmailBodyHTML', "") == "")
		{
			$message = Text::_('USERREMINDER_SEND_MSG_REMINDER');
			$message = preg_replace("(\n)", "<br />", $message); // if content is plain text -> carriage returns it if have \n
		}
		else
		{

			$message = \Joomla\CMS\Component\ComponentHelper::getParams('com_userreminder')->get('regActivationEmailBodyHTML', "");
		}

		$activationURL = "<a href='$activationURL'>$activationURL</a>";
		$passwordReset = $siteURL . ComponentHelper::getParams('com_userreminder')->get('passwordReset', 'index.php?option=com_users&view=reset');
		$passwordReset = "<a href='$passwordReset'>$passwordReset</a>";
		$optOutUrl     = $siteURL . 'index.php?option=com_userreminder&task=optout&uid=' . $user->id;
		$optOutUrl     = "<a href='$optOutUrl'>$optOutUrl</a>";
		$websiteURL    = "<a href='$siteURL'>$siteURL</a>";

		$message = Text::_('USERREMINDER_REMINDER_DETAILS_FOR_TEST') . chr(10) . chr(10) . $message;
		$message = userreminderModelReminder::replaceParams($message, "[NAME]", $name);
		$message = userreminderModelReminder::replaceParams($message, "[SITE_NAME]", $sitename);
		$message = userreminderModelReminder::replaceParams($message, "[ACTIVATE_URL]", $activationURL);
		$message = userreminderModelReminder::replaceParams($message, "[SITE_URL]", $websiteURL);
		$message = userreminderModelReminder::replaceParams($message, "[USERNAME]", $username);

		// Reset Passowrd link

		$message = userreminderModelReminder::replaceParams($message, "[PASSWORD_RESET]", $passwordReset);

		$message = userreminderModelReminder::replaceParams($message, "[OPTOUT]", $optOutUrl);
		$message = html_entity_decode($message, ENT_QUOTES);
		// send email
		\Joomla\CMS\Factory::getMailer()->sendMail($mailfrom, $fromname, $email, $subject, $message, true);
		// ********************************************************************************
		// Create and send TEST email for users who have never logged in
		// ********************************************************************************

		// get email subject

		if (\Joomla\CMS\Component\ComponentHelper::getParams('com_userreminder')->get('regLoginEmailSubject', '') == "")
		{
			$subject = Text::_('USERREMINDER_LOGINREMINDER_DETAILS_FOR');
		}
		else
		{
			$subject = \Joomla\CMS\Component\ComponentHelper::getParams('com_userreminder')->get('regLoginEmailSubject', "");
		}

		//$subject 	= sprintf ( $subject, $sitename);
		$subject = userreminderModelReminder::replaceParams($subject, "[NAME]", $name);
		$subject = userreminderModelReminder::replaceParams($subject, "[SITE_NAME]", $sitename);
		$subject = html_entity_decode($subject, ENT_QUOTES);

		$mode = 1;
		// send mail with message html. if no HTML then use plain text

		if (\Joomla\CMS\Component\ComponentHelper::getParams('com_userreminder')->get('regLoginEmailBodyHTML', '') == "")
		{
			$message = Text::_('USERREMINDER_SEND_MSG_LOGINREMINDER');
			$message = preg_replace("(\n)", "<br />", $message); // if content is plain text -> carriage returns it if have \n
		}
		else
		{

			$message = \Joomla\CMS\Component\ComponentHelper::getParams('com_userreminder')->get('regLoginEmailBodyHTML', '');
		}

		$passwordReset = $siteURL . ComponentHelper::getParams('com_userreminder')->get('passwordReset', 'index.php?option=com_users&view=reset');
		$passwordReset = "<a href='$passwordReset'>$passwordReset</a>";
		$optOutUrl     = $siteURL . 'index.php?option=com_userreminder&task=optout&uid=' . $user->id;
		$optOutUrl     = "<a href='$optOutUrl'>$optOutUrl</a>";
		$websiteUrl    = "<a href='$siteURL'>$siteURL</a>";

		//$message = sprintf ( \Joomla\CMS\Language\Text::_( 'USERREMINDER_REMINDER_DETAILS_FOR_TEST' ).chr(10).chr(10).$message, $name, $sitename, $siteURL, $username, $siteURL.'index.php?option=com_user&view=reset');
		$message = Text::_('USERREMINDER_REMINDER_DETAILS_FOR_TEST') . chr(10) . chr(10) . $message;
		$message = userreminderModelReminder::replaceParams($message, "[NAME]", $name);
		$message = userreminderModelReminder::replaceParams($message, "[SITE_NAME]", $sitename);
		$message = userreminderModelReminder::replaceParams($message, "[SITE_URL]", $websiteUrl);
		$message = userreminderModelReminder::replaceParams($message, "[USERNAME]", $username);
		$message = userreminderModelReminder::replaceParams($message, "[PASSWORD_RESET]", $passwordReset);
		$message = userreminderModelReminder::replaceParams($message, "[OPTOUT]", $optOutUrl);
		$message = html_entity_decode($message, ENT_QUOTES);
		//$message	= preg_replace("(\n)", "<br />", $message); // if content is plain text -> carriage returns it if have \n

		// send email
		\Joomla\CMS\Factory::getMailer()->sendMail($mailfrom, $fromname, $email, $subject, $message, true);

	}

	function replaceParams($message, $parameter, $replacementString)
	{
		$newString = "";
		$newString = str_replace($parameter, $replacementString, $message);

		return $newString;
	}


}