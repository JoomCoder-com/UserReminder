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
use Joomla\CMS\Factory;

defined('JPATH_BASE') or die;

jimport('joomla.plugin.plugin');

class plgSystemUserreminder extends JPlugin
{

	public function onAfterRender()
	{

		// some inits
		$params = ComponentHelper::getParams('com_userreminder');


		$db = JFactory::getDBO();

		// get the current time parameters
		$day       = intval(date("d", time()));
		$month     = intval(date("m", time()));
		$year      = intval(date("Y", time()));
		$dayOfWeek = intval(date("N", time()));
		$hour      = intval(date("h", time())); // Bug Fix 4-9-2013

		// get other parameters
		$number_email           = $params->get('number_email', 1);
		$runUserReminders       = $params->get('enabledScheduledUserReminders', 1);
		$runActivationReminders = $params->get('enabledScheduledActivationReminders', 1);
		$schedulerEnabled = $params->get('enabledScheduledUserReminders', 1);
		$activationEnabled = $params->get('enabledScheduledActivationReminders', 1);

		// check to see id any of the reminders are enabled
		if (!$schedulerEnabled || !$activationEnabled)
		{
			// check to see if the scheduler has been enabled
			if (!$params->get('enabledScheduledExecution', 1))
			{

				// get the parameters
				$scheduleExecutionType = $params->get('scheduledExecutionType', 1);
				$scheduleExecutionTime = $params->get('scheduledExecutionTime', 1);

				// setup variables
				$date = date('Y-m-d H:i:s');

				// ---- Check to see if Daily , Weekly or Monthly should be run -------------------------------------------------
				if ($scheduleExecutionType)
				{
					//send daily
					if ($scheduleExecutionTime <= $hour)
					{  // Bug Fix 4-9-2013
						$db->setQuery("Select count(id) from #__userreminder_sch where daysent = '$day' and monthsent = '$month' and yearsent = '$year'");
						$count = $db->loadResult();
						if ($count == 0)
						{
							// run the scheduled reminders
							$this->runReminders("Daily Scheduled Run", $day, $month, $year, $number_email, $runUserReminders, $runActivationReminders);
						}
					}
				}
				elseif ($scheduleExecutionType == 2)
				{
					//send weekly - Check to see if today is the correct day to run it
					if ($scheduleExecutionTime == $dayOfWeek)
					{
						$db->setQuery("Select count(id) from #__userreminder_sch where daysent = '$day' and monthsent = '$month' and yearsent = '$year'");
						$count = $db->loadResult();
						if ($count == 0)
						{
							// run the scheduled reminders
							$this->runReminders("Weekly Scheduled Run", $day, $month, $year, $number_email, $runUserReminders, $runActivationReminders);
						}
					}
				}
				elseif ($scheduleExecutionType == 3)
				{
					//send monthly - Check to see if today is the correct day to run it
					if ($day == $scheduleExecutionTime)
					{
						$db->setQuery("Select count(id) from #__userreminder_sch where daysent = '$day' and monthsent = '$month' and yearsent = '$year'");
						$count = $db->loadResult();
						if ($count == 0)
						{
							// run the scheduled reminders
							$this->runReminders("Monthly Scheduled Run", $day, $month, $year, $number_email, $runUserReminders, $runActivationReminders);
						}
					}
				}
			}
		}
	}


	public function runReminders($type, $day, $month, $year, $number_email, $runUserReminders, $runActivationReminders)
	{

		require_once(JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_userreminder' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'sendreminder.php');
		require_once(JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_userreminder' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'senduserreminder.php');

		$db = JFactory::getDBO();

		//insert into the schedule table
		$db->setQuery("INSERT into #__userreminder_sch (id,daysent,monthsent,yearsent,timesent) values (NULL,'$day','$month','$year','" . time() . "')");
		$db->execute();

		// get number email send this time
		$email_number_old = Factory::getApplication()->input->get('email_number_old', 0, 'int');
		$email_number_new = Factory::getApplication()->input->get('email_number_new', 0, 'int');

		//check to see what to run
		$enabledScheduledUserReminders       = ComponentHelper::getParams('com_userreminder')->get('enabledScheduledUserReminders', 1);
		$enabledScheduledActivationReminders = ComponentHelper::getParams('com_userreminder')->get('enabledScheduledActivationReminders', 1);

		// log the event into the log table
		$description = 'STARTED ' . $type;
		$date        = date('Y-m-d H:i:s');
		$q2          = "INSERT INTO #__userreminder_log (userId, username, description, date) VALUES ('0', 'System', '" . $description . "', '" . $date . "');";
		$db->setQuery($q2);
		$db->execute();

		if (!$runActivationReminders)
		{
			// log the event into the log table
			//$description = 'User Activation and Login DEBUG: Reminders sendReminder(false,'.$email_number_old.','.$email_number_new.','.$number_email.')' ;
			$description = 'START - Scheduled Registration Reminders now running....';
			$date        = date('Y-m-d H:i:s');
			$q2          = "INSERT INTO #__userreminder_log (userId, username, description, date) VALUES ('0', 'System', '" . $description . "', '" . $date . "');";
			$db->setQuery($q2);
			$db->execute();

			// activation reminders
			$reminders = new userreminderModelSendReminder();
			//$reminders->sendReminder(false);
			$reminders->sendReminder(false, $email_number_old, $email_number_new, $number_email);

			$description = 'END - Scheduled Registration Reminders';
			$date        = date('Y-m-d H:i:s');
			$q2          = "INSERT INTO #__userreminder_log (userId, username, description, date) VALUES ('0', 'System', '" . $description . "', '" . $date . "');";
			$db->setQuery($q2);
			$db->execute();

		}

		if (!$runUserReminders)
		{
			// log the event into the log table
			$description = 'START - Scheduled User Reminders now running....';
			$date        = date('Y-m-d H:i:s');
			$q2          = "INSERT INTO #__userreminder_log (userId, username, description, date) VALUES ('0', 'System', '" . $description . "', '" . $date . "');";
			$db->setQuery($q2);
			$db->execute();

			// user reminders
			$reminders = new userreminderModelSendUserReminder();
			$reminders->sendUserReminder(false, $email_number_old, $email_number_new, $number_email);

			$description = 'END - Scheduled User Reminders';
			$date        = date('Y-m-d H:i:s');
			$q2          = "INSERT INTO #__userreminder_log (userId, username, description, date) VALUES ('0', 'System', '" . $description . "', '" . $date . "');";
			$db->setQuery($q2);
			$db->execute();

		}

		// log the event into the log table
		$description = 'COMPLETED ' . $type;
		$date        = date('Y-m-d H:i:s');
		$q2          = "INSERT INTO #__userreminder_log (userId, username, description, date) VALUES ('0', 'System', '" . $description . "', '" . $date . "');";
		$db->setQuery($q2);
		$db->execute();

	}



}