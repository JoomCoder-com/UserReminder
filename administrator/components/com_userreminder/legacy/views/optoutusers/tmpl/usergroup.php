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
use Joomla\CMS\HTML\HTMLHelper;

defined('_JEXEC') or die('Restricted access');

HTMLHelper::_('bootstrap.tooltip', '.hasTooltip');
\Joomla\CMS\HTML\HTMLHelper::_('behavior.multiselect');
//\Joomla\CMS\HTML\HTMLHelper::_('behavior.modal');
\Joomla\CMS\HTML\HTMLHelper::_('formbehavior.chosen', 'select');


$groupList = $this->groupList;
?>
<div>
	<?php
	if(isset($this->message)):	 
		if($this->message):?>
		<div id="system-message-container">
			<div class="alert alert-success">
				<h4 class="alert-heading">Message</h4>
				<p><?php echo $this->message?></p>
			</div>
		</div>
	<?php endif;
	endif; ?>
    <ul class="nav nav-tabs" id="submenu">
        <li class="nav-item">
            <a class="nav-link " href="index.php?option=com_userreminder&task=optuserPanel"><?php echo \Joomla\CMS\Language\Text::_('USERREMINDER_OPTOUT_USERS2')?></a>
        </li>
        <li class="nav-item">
            <a class="nav-link " href="index.php?option=com_userreminder&task=optoutusers.userlist"><?php echo \Joomla\CMS\Language\Text::_('USERREMINDER_USER_LIST')?></a>
        </li>
        <li class="nav-item">
            <a class="nav-link active" href="index.php?option=com_userreminder&task=optoutusers.usergroup"><?php echo \Joomla\CMS\Language\Text::_('USERREMINDER_OPTUSER_GROUP')?></a>
        </li>
    </ul>
    <div class="tab-content">
        <form method="post" name="adminForm" id="adminForm" action="index.php?option=com_userreminder">
            <table class="table table-striped" id="articleList">
                <thead>
                <tr>
                    <th width="1%">
                        <input type="checkbox" name="checkall-toggle" value="" title="<?php echo \Joomla\CMS\Language\Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" />
                    </th>
                    <th class="left">
                        <?php echo \Joomla\CMS\Language\Text::_('USERREMINDER_GROUP_TITLE'); ?>
                    </th>
                    <th width="20%">
                        <?php echo \Joomla\CMS\Language\Text::_('USERREMINDER_USER_IN_GROUP'); ?>
                    </th>
                    <th width="5%">
                        <?php echo \Joomla\CMS\Language\Text::_('JGRID_HEADING_ID'); ?>
                    </th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($this->groupList as $i => $item) :
                    ?>
                    <tr class="row<?php echo $i % 2; ?>">
                        <td class="center">
                            <input type="checkbox" <?php echo checkedBox($this->optgroups, $item)?> title="Checkbox for row <?php echo $i + 1?>" onclick="Joomla.isChecked(this.checked);" value="<?php echo $item->id?>" name="cid[]" id="cb<?php echo $i?>">
                        </td>
                        <td>
                            <?php echo str_repeat('<span class="gi">|&mdash;</span>', $item->level) ?>
                            <a target="_blank" href="<?php echo  \Joomla\CMS\Router\Route::_('index.php?option=com_users&task=group.edit&id='.$item->id);?>">
                                <?php echo $this->escape($item->title); ?>
                            </a>
                        </td>
                        <td class="center">
                            <?php echo $item->user_count ? $item->user_count : ''; ?>
                        </td>
                        <td class="center">
                            <?php echo (int) $item->id; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <input type="hidden" name="task" id="task" value="optoutusers.usergroup" />
            <input type="hidden" value="0" name="boxchecked" />
        </form>
    </div>
</div>
<script type="text/javascript">
	function tableOrdering( order, dir, task ){
	        var form = document.adminForm;
	 
	        form.filter_order.value = order;
	        form.filter_order_Dir.value = dir;
	        document.adminForm.submit( task );
	}
</script>
<?php 
function checkedBox($userGroups, $item){
	
	foreach ($userGroups as $group) :
		if($group->group_id == $item->id):
			return 'checked="checked"';
		endif;
	endforeach;
	return "";
}
?>