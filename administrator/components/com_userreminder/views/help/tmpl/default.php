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

$logo = \Joomla\CMS\Uri\Uri::base(true)."/components/com_userreminder/assets/images/";

?>
<script type="text/javascript">
Joomla.submitbutton = function(task)
{
	Joomla.submitform(task, document.getElementById('userreminder-form'));
}
</script>

<h1>Help</h1>

<div class="my-3">
    Below you will find details on how to use UserReminder.<br />
    For further help and updates please visit <a href="http://www.joomcoder.com/" target="_blank">UserReminder Website (www.joomcoder.com)</a>
</div>

<div class="card">
    <div class="card-header">
        <h3>Configuration - Parameters Tab</h3>
    </div>
    <div class="card-body">
        <b>Days before next reminder is sent or user deleted</b> - The number of days before a user can be deleted<br />
        <b>Send 1st reminder immediately</b> - This only applies to the first reminder. If you set this to yes the first reminder will be sent the first time userreminder is run (ie it will not wait until the number of days in the above field have passed)<br />
        <b>Number of Reminders</b> -The number of reminders sent to a user before they are deleted<br />
        <b>BCC Reminder Emails</b> - Blind cc the an email address on all reminders (the email address is stored in the next field)<br />
        <b>BCC email address</b> - The email address to BCC emails to<br />
        <b>Maximum Emails</b> - This sets the maximum number of emails that can be sent in a single instance. This is important as many hosting companies have a limit on the number of emails that can be sent per hour (or day). By default this value is set to the low number of 20.<br />
        <b>Number of Emails in RecordSet</b> - Limit the number of rows returned in each query<br />
        <b>URL to send in Reminder Emails for Password Resets</b> - The url (not including the site) for Password Resets. The may look different depending if you are using Joomla default ot other extensions such as Community Builder <br />
        <b>Scheduled Execution</b> - Enable scheduled execution of this component. NOTE This will only work once the UserReminder Scheduler PLUGIN has been installed and is enabled.<br />
        <b>Execution Type and Execution On</b> - these parameters set up how regular the scheduler will run automatically.<br />

        <b>Notes on the Scheduler</b> - For the automated scheduler to work the userReminder "System - UserReminder Scheduler" plugin must be installed and enabled.<br />

        Automatic notifications are only sent for users who have not competed the registration process (eg "Incomplete Registrations" and "Users who have never login").

        <b>Technical Details on the Scheduler</b>
        UserReminder uses the onAfterRoute event supplied by the Joomla framework.
        The onAfterRoute event is executed every time a request is made of the Joomla website.
        When the onAfterRoute event is executed in the UserReminder plugin it checks to see if the notifications have been sent, if not then they are sent.
        For example if you have the userreminder scheduler set to Weekly on Tuesdays then when a request is made of the website the plugin checks to see if today is Tuesday and if a reminder has already been sent. If no reminder has been sent then it will be executed.
        <br />
        Because the onAfterRoute event replies on traffic to your site, in the event that you do get traffic to your site then the automated event will not occur for that day
        <br />
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">
        <h3>Configuration - In-Active Registrations Tab</h3>
    </div>
    <div class="card-body">
        <b>Delete Users</b> - Set this to true to enable users to be deleted. Note that users will only be deleted if the number of days defined in the parameter 'Days before next reminder is sent or users deleted' has passed since the maximum number of notification have been sent.<br />
        <b>Use Community Builder Activation</b> - If you are using the Community Builder component on your website to manage users then set this option to 'yes' to create a valid link in the reminder email. By Default the Joomla registration/activation link is used<br />
        <b>Email Subject</b> - Subject line in email. Available merge fields are  <br />
        [NAME] = Users Full Name  <br />
        [SITE_NAME] = Webiste Name<br />
        <b>Email Message</b> = Email Body. Available merge fields are  <br />
        [NAME] = Users Full Name <br />
        [SITE_NAME] = Webiste Name <br />
        [SITE_URL] = Website url <br />
        [USERNAME] = Username<br />
        [ACTIVATE_URL] = Activation url <br />
        [PASSWORD_RESET] = Password reset url<br />
        [OPTOUT] = optout link to be CAN-SPAM compliant<br />
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">
        <h3>Configuration - Users who have never logged in Tab</h3>
    </div>
    <div class="card-body">
        <b>Enabled</b> - Enable reminders for users who have activated their accounts be never logged in
        <b>Delete Users</b> - Set this to true to enable users to be deleted. Note that users will only be deleted if the number of days defined in the parameter 'Days before next reminder is sent or users deleted' has passed since the maximum number of notification have been sent.<br />
        <b>Email Subject</b> - Subject line in email. Available merge fields are  <br />
        [NAME] = Users Full Name  <br />
        [SITE_NAME] = Webiste Name<br />
        <b>Email Message</b> = Email Body. Available merge fields are  <br />
        [NAME] = Users Full Name <br />
        [SITE_NAME] = Webiste Name <br />
        [SITE_URL] = Website url <br />
        [USERNAME] = Username <br />
        [PASSWORD_RESET] = Password reset url<br />
        [OPTOUT] = optout link to be CAN-SPAM compliant<br />
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">
        <h3>Configuration - User Reminders Tab</h3>
    </div>
    <div class="card-body">
        <b>Days</b> - Number of days that a user has not logged in for. Once this number of days has been reached a reminder email can be sent.<br />
        <b>Email Subject</b> - Subject line in email. Available merge fields are <br />
        [NAME] = Users Full Name <br />
        [SITE_NAME] = Webiste Name<br />
        <b>Email Message</b> = Email Body. Available merge fields are<br />
        [NAME] = Users Full Name <br />
        [SITE_NAME] = Webiste Name <br />
        [SITE_URL] = Website url <br />
        [USERNAME] = Username <br />
        [PASSWORD_RESET] = Password reset url<br />
        [OPTOUT] = optout link to be CAN-SPAM compliant<br />
        <br />
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">
        <h3>Instructions</h3>
    </div>
    <div class="card-body">
        1 – Setup your parameters<br />
        2 – Click on the 'Registration Reminders' or 'Active User Reminder' toolbar button. This will display a list of users who will recieve reminders. In the far right hand column (Action to Perform) it will display the action the component will take. The three possible actions available are No Action, Send a Reminder or Delete User. Note that none of these actions will be performed until the ‘Send Reminders’ toolbar button is pressed.<br />
        3 – Click the ‘Send Reminders’ toolbar button. Once this button is clicked Reminder emails will be sent and users deleted (if you have enable this in the Parameters screen. A list of the actions will be displayed when the button has been pressed.<br />
        <br />
        <h1>Test Email</h1>
        The Test Email toolbar button will send a test email to the logged in user.<br />
        <br />
    </div>
</div>

<form method="post" name="adminForm" id="userreminder-form" action="index.php?option=com_userreminder">
<input type="hidden" name="task" value="" />
</form> 
