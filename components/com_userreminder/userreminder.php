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
use Joomla\CMS\Factory;

defined('_JEXEC') or die('Restricted access');
// get the request details
$task = \Joomla\CMS\Factory::getApplication()->input->get('task', '', 'default', 'cmd');

// load the right language files
//$lang =& \Joomla\CMS\Factory::getLanguage();
$lang         = \Joomla\CMS\Factory::getLanguage();
$extension    = 'com_userreminder';
$base_dir     = JPATH_SITE;
$language_tag = 'en-GB';
$lang->load($extension, $base_dir, $language_tag, true);

$extension    = 'com_users';
$base_dir     = JPATH_SITE;
$language_tag = 'en-GB';
$lang->load($extension, $base_dir, $language_tag, true);

// now check to see what the request was
switch ($task)
{
	case "optout":
		optout();
		break;
	case "optoutnow":
		optoutnow();
		break;
}

// now that the user has been validated process the op-out request
function optoutnow()
{

	$db = \Joomla\CMS\Factory::getDBO();
	//$user = \Joomla\CMS\Factory::getUser();

	// double check that the user logged in is the one being opted out
	$optoutcode = Factory::getApplication()->input->get('uid', '', 'string');

	// find the user
	//$optoutcode = \Joomla\CMS\Factory::getApplication()->input->get('uid',0,'','int');
	$optoutcode2 = $db->quote($db->escape($optoutcode));
	$q           = "Select userid from #__userreminder where optoutcode = $optoutcode2";
	$db->setQuery($q);
	$db->execute();
	$user = $db->loadObject();

	$notexist = 0;
	if (!isset($user))
	{
		$notexist = 1;
	}
	else
	{
		// check to see if the user has already opt out
		$theuid = $db->quote($db->escape($user->userid));
		$db->setQuery("Select count(user_id) from #__userreminder_optout where user_id = $theuid");
		$count = $db->loadResult();

		if ($count == 0)
		{
			$db->setQuery("Insert into #__userreminder_optout (user_id) values ($theuid)");
			$db->execute();
		}
	}

	if ($notexist)
	{ ?>

        <div id="system-message" class="alert alert-danger alert-dismissible fade show" role="alert">
            <h4 class="alert-heading">Message</h4>
            <p>Error encountered - Please contact the website administrator to unsubscribe.</p>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>

	<?php }
	else
	{

		?>

        <div id="system-message" class="alert alert-success alert-dismissible fade show" role="alert">
            <h4 class="alert-heading">Message</h4>
            <p><?php echo \Joomla\CMS\Language\Text::_('USERREMINDER_OPTOUT_SUCCESS') ?></p>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>



		<?php
	}

}


// process the op-out request
function optout()
{ ?>
    <div id="system-message" class="alert alert-warning alert-dismissible fade show" role="alert">
        <h4 class="alert-heading">Message</h4>
        <p><?php echo \Joomla\CMS\Language\Text::_('USERREMINDER_CONFIRMATION') ?></p>
        <hr>
        <a class="btn btn-sm btn-outline-success bg-white text-success" href="<?php echo JURI::base() ?>index.php?option=com_userreminder&task=optoutnow&uid=<?php echo \Joomla\CMS\Factory::getApplication()->input->get('uid') ?>"><?php echo \Joomla\CMS\Language\Text::_('USERREMINDER_YES') ?></a>
        <a class="btn btn-sm btn-outline-warning bg-white text-warning" href="<?php echo JURI::base() ?>index.php"><?php echo \Joomla\CMS\Language\Text::_('USERREMINDER_NO') ?></a>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php } ?>
