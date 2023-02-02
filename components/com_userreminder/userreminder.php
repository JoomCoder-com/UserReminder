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
  // get the request details
  $task = JFactory::getApplication()->input->get('task','','default','cmd');

  // load the right language files
  //$lang =& JFactory::getLanguage();
  $lang = JFactory::getLanguage();
  $extension = 'com_userreminder';
  $base_dir = JPATH_SITE;
  $language_tag = 'en-GB';
  $lang->load($extension, $base_dir, $language_tag, true);

  $extension = 'com_users';
  $base_dir = JPATH_SITE;
  $language_tag = 'en-GB';
  $lang->load($extension, $base_dir, $language_tag, true);

  // now check to see what the request was
  switch ($task){
  	case "optout":
  		optout();
  	break;
  	case "optoutnow":
  		optoutnow();
  	break;
  }

  // now that the user has been validated process the op-out request 
  function optoutnow(){
  	global $mainframe;
  	$db = JFactory::getDBO();
  	//$user = JFactory::getUser()	;
 	
    // double check that the user logged in is the one being opted out
    $optoutcode = JRequest::getString('uid');

//    if($uid != $user->id){
//    		$mainframe->redirect(JURI::base()."index.php",JText::_('USERREMINDER_PERMISSION'));
//    }
  
    // find the user
    //$optoutcode = JFactory::getApplication()->input->get('uid',0,'','int');
    $optoutcode2 = $db->quote( $db->escape( $optoutcode ) );
    $q="Select userid from #__userreminder where optoutcode = $optoutcode2";
    $db->setQuery($q);
  	$db->query();
    $user = $db->loadObject();
    
    $notexist = 0 ;
    if(!isset( $user )){
  	    $notexist = 1;
  	}
  	else {
      // check to see if the user has already opt out
      $theuid = $db->quote( $db->escape( $user->userid ));
      $db->setQuery("Select count(user_id) from #__userreminder_optout where user_id = $theuid");
    	$count = $db->loadResult();
  
    	if($count == 0){
    		$db->setQuery("Insert into #__userreminder_optout (user_id) values ($theuid)");
    		$db->query();
    	}
    }
    
    if ($db->getErrorNum() || $notexist == 1) { ?>
    	<div id="system-message">
			<div class="alert alert-warning" style="padding: 0;">
				<h4 class="alert-heading">Message</h4>
				<div><p>Error encountered - Please contact the website administrator to unsubscribe.</p></div>
			</div>
		</div>	
    <?php } else{
    
    		?>
		<table cellpadding="0" cellspacing="0" width="100%">
			<tr>
				<td width="100%" align="center" style="padding:10px;">
					<div id="system-message" style="padding: 0;">
						<div class="alert alert-warning"><a data-dismiss="alert" class="close" style="right: 8px;top: 2px;">&times;</a>
									<h4 class="alert-heading">Message</h4>
							<div><p><?php echo JText::_('USERREMINDER_OPTOUT_SUCCESS')?></p></div>
						</div>
					</div>
				</td>
			</tr>
		</table>
		<?php
    }

  }



// process the op-out request
function optout(){ ?>
	<table cellpadding="0" cellspacing="0" width="100%">
		<tr>
			<td width="100%" align="center" style="padding:10px;">
			<div id="system-message">
				<div class="alert alert-warning" style="padding: 0;"><a data-dismiss="alert" class="close" style="right: 8px;top: 2px;">&times;</a>
					<h4 class="alert-heading">Message</h4>
					<div><p><?php echo JText::_('USERREMINDER_CONFIRMATION');?></p></div>
				</div>
			</div>
				<a href="<?php echo JURI::base()?>index.php?option=com_userreminder&task=optoutnow&uid=<?php echo JFactory::getApplication()->input->get('uid')?>"><?php echo JText::_('USERREMINDER_YES')?></a>
				 &nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;
				 <a href="<?php echo JURI::base()?>index.php"><?php echo JText::_('USERREMINDER_NO')?></a>
			</td>
		</tr>
	</table>
<?php } ?>
