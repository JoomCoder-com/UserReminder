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
defined('JPATH_BASE') or die;

jimport( 'joomla.plugin.plugin' );

class plgSystemUserreminder extends JPlugin
{


  public function onAfterRender()
  {

    $db = JFactory::getDBO();        	

    // get the parameter for this component
    $query = "SELECT params FROM #__extensions WHERE `element`='com_userreminder'";
    $db->setQuery($query);
    $result = $db->loadRow();
    $tableParam = str_replace("\r\n", "<br />", $result[0]);
    $usersConfig = json_decode($tableParam, TRUE);
	
		// get the current time parameters
		$day = intval(date("d",time()));
		$month = intval(date("m",time()));
		$year = intval(date("Y",time()));
		$dayOfWeek = intval(date("N",time()));
    $hour = intval(date("h",time())); // Bug Fix 4-9-2013

    // get other parameters
		$number_email 		=   $this->getParamData($usersConfig, "number_email", 1);
    $runUserReminders = $this->getParamData($usersConfig, "enabledScheduledUserReminders", 1);
    $runActivationReminders = $this->getParamData($usersConfig, "enabledScheduledActivationReminders", 1);
		
      // check to see id any of the reminders are enabled
      if ($this->getParamData($usersConfig, "enabledScheduledUserReminders", 1) ==0  || $this->getParamData($usersConfig, "enabledScheduledActivationReminders", 1) ==0 ) 
      {
          // check to see if the scheduler has been enabled
          if ($this->getParamData($usersConfig, "enabledScheduledExecution", 1) ==0 ) {
          
              // get the parameters
              $scheduleExecutionType = $this->getParamData($usersConfig, "scheduledExecutionType", 1);
              $scheduleExecutionTime = $this->getParamData($usersConfig, "scheduledExecutionTime", 1);

              // setup variables
              $date = date('Y-m-d H:i:s');
          
             // ---- Check to see if Daily , Weekly or Monthly should be run -------------------------------------------------
            	if($scheduleExecutionType == 1){
            		  //send daily
            		  if($scheduleExecutionTime <= $hour){  // Bug Fix 4-9-2013
              		  $db->setQuery("Select count(id) from #__userreminder_sch where daysent = '$day' and monthsent = '$month' and yearsent = '$year'");
              		  $count = $db->loadResult();
              		  if($count == 0)
                    {
              			   // run the scheduled reminders
              			   $this->runReminders("Daily Scheduled Run",$day,$month,$year,$number_email,$runUserReminders,$runActivationReminders,$usersConfig);
                    }
                  }
            	}elseif($scheduleExecutionType == 2){
            	   	//send weekly - Check to see if today is the correct day to run it
            		  if($scheduleExecutionTime == $dayOfWeek){
    	        		   $db->setQuery("Select count(id) from #__userreminder_sch where daysent = '$day' and monthsent = '$month' and yearsent = '$year'");
    	        		   $count = $db->loadResult();
    	        		   if($count == 0)
                     {
    	        			    // run the scheduled reminders
            			      $this->runReminders("Weekly Scheduled Run",$day,$month,$year,$number_email,$runUserReminders,$runActivationReminders,$usersConfig);
    	        		   }
            		  }
            	}elseif($scheduleExecutionType == 3){
            		  //send monthly - Check to see if today is the correct day to run it
            		  if($day == $scheduleExecutionTime){
            			   $db->setQuery("Select count(id) from #__userreminder_sch where daysent = '$day' and monthsent = '$month' and yearsent = '$year'");
    	        		   $count = $db->loadResult();
    	        		   if($count == 0)
                     {
    	        			    // run the scheduled reminders
            			      $this->runReminders("Monthly Scheduled Run",$day,$month,$year,$number_email,$runUserReminders,$runActivationReminders,$usersConfig);
    	        		   }
            		  }
            	}
          }
        }
      }
    

  public function runReminders($type,$day,$month,$year,$number_email,$runUserReminders,$runActivationReminders,$usersConfig){
			      
			      require_once(JPATH_ADMINISTRATOR.DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'com_userreminder'.DIRECTORY_SEPARATOR.'models'.DIRECTORY_SEPARATOR.'sendreminder.php');
			      require_once(JPATH_ADMINISTRATOR.DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'com_userreminder'.DIRECTORY_SEPARATOR.'models'.DIRECTORY_SEPARATOR.'senduserreminder.php');
            
            $db = JFactory::getDBO(); 

        		//insert into the schedule schedule table
      			$db->setQuery("Insert into #__userreminder_sch (id,daysent,monthsent,yearsent,timesent) values (NULL,'$day','$month','$year','".time()."')");
      			$db->query();
            
       		  // get number email send this time
            $email_number_old 	= JRequest::getVar('email_number_old',0,'','int');
        		$email_number_new 	= JRequest::getVar('email_number_new',0,'','int');
            
            //check to see what to run
            $enabledScheduledUserReminders = $this->getParamData($usersConfig, "enabledScheduledUserReminders", 1);
            $enabledScheduledActivationReminders = $this->getParamData($usersConfig, "enabledScheduledActivationReminders", 1);
              
            // log the event into the log table
            $description = 'STARTED '.$type;
            $date = date('Y-m-d H:i:s');
            $q2 = "INSERT INTO #__userreminder_log (userId, username, description, date) VALUES ('0', 'System', '".$description."', '".$date."');";
            $db->setQuery($q2);
            $db->query();

            if ($runActivationReminders==0){
      			    // log the event into the log table
                //$description = 'User Activation and Login DEBUG: Reminders sendReminder(false,'.$email_number_old.','.$email_number_new.','.$number_email.')' ;
                $description = 'START - Scheduled Registration Reminders now running....' ;
                $date = date('Y-m-d H:i:s');
                $q2 = "INSERT INTO #__userreminder_log (userId, username, description, date) VALUES ('0', 'System', '".$description."', '".$date."');";
                $db->setQuery($q2);
                $db->query();
                
                // activation reminders
                $reminders = new userreminderModelSendReminder();
                //$reminders->sendReminder(false);
                $reminders->sendReminder(false,$email_number_old,$email_number_new,$number_email);

                $description = 'END - Scheduled Registration Reminders' ;
                $date = date('Y-m-d H:i:s');
                $q2 = "INSERT INTO #__userreminder_log (userId, username, description, date) VALUES ('0', 'System', '".$description."', '".$date."');";
                $db->setQuery($q2);
                $db->query();

            }
            
            if ($runUserReminders==0){
      			    // log the event into the log table
                $description = 'START - Scheduled User Reminders now running....' ;
                $date = date('Y-m-d H:i:s');
                $q2 = "INSERT INTO #__userreminder_log (userId, username, description, date) VALUES ('0', 'System', '".$description."', '".$date."');";
                $db->setQuery($q2);
                $db->query();
                
      			    // user reminders
                $reminders = new userreminderModelSendUserReminder();
                $reminders->sendUserReminder(false,$email_number_old,$email_number_new,$number_email);
                
                $description = 'END - Scheduled User Reminders' ;
                $date = date('Y-m-d H:i:s');
                $q2 = "INSERT INTO #__userreminder_log (userId, username, description, date) VALUES ('0', 'System', '".$description."', '".$date."');";
                $db->setQuery($q2);
                $db->query();

            }
				
            // log the event into the log table
            $description = 'COMPLETED '.$type;
            $date = date('Y-m-d H:i:s');
            $q2 = "INSERT INTO #__userreminder_log (userId, username, description, date) VALUES ('0', 'System', '".$description."', '".$date."');";
            $db->setQuery($q2);
            $db->query();
        
    }

  public function getParamData($dataInputArray, $arrayIndex, $defaultValue) {

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