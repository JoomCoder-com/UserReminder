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


$itemList = $this->optusers;
$search = $this->escape(\Joomla\CMS\Factory::getApplication()->input->get('filter_search', null, 'var'));
?>
<form method="post" name="adminForm" id="adminForm" action="index.php?option=com_userreminder">
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

    <div class="row mb-3">
        <div class="col-md-4">
            <div class="input-group">
                <input value="<?php echo $search?>" name="filter_search" id="filter_search" type="text" class="form-control" placeholder="<?php echo \Joomla\CMS\Language\Text::_('USERREMINDER_USERS_SEARCH_USERS'); ?>">
                <button class="btn btn-outline-secondary hasTooltip" title="JSEARCH_FILTER_SUBMIT" type="button"><i class="fas fa-search"></i></button>
                <button class="btn btn-outline-secondary" title="<?php echo \Joomla\CMS\Language\Text::_('JSEARCH_RESET'); ?>" type="button" onclick="document.querySelector('#filter_search').value='';this.form.submit();">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>


    <ul class="nav nav-tabs" id="submenu">
        <li class="nav-item">
            <a class="nav-link active" href="index.php?option=com_userreminder&task=optuserPanel"><?php echo \Joomla\CMS\Language\Text::_('USERREMINDER_OPTOUT_USERS2')?></a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="index.php?option=com_userreminder&task=optoutusers.userlist"><?php echo \Joomla\CMS\Language\Text::_('USERREMINDER_USER_LIST')?></a>
        </li>
        <li class="nav-item">
            <a class="nav-link " href="index.php?option=com_userreminder&task=optoutusers.usergroup"><?php echo \Joomla\CMS\Language\Text::_('USERREMINDER_OPTUSER_GROUP')?></a>
        </li>
    </ul>

    <div class="tab-content" id="nav-tabContent">
        <table class="table table-striped" id="articleList">
            <thead>
            <tr>
                <th width="1%">
                    <input type="checkbox" name="checkall-toggle" value="" title="<?php echo \Joomla\CMS\Language\Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" />
                </th>
                <th><?php echo \Joomla\CMS\HTML\HTMLHelper::_( 'grid.sort', \Joomla\CMS\Language\Text::_('USERREMINDER_NAME__'), 'name', $this->sortDirection, $this->sortColumn, 'optuserPanel'); ?></th>
                <th><?php echo \Joomla\CMS\HTML\HTMLHelper::_( 'grid.sort', \Joomla\CMS\Language\Text::_('USERREMINDER_NAME'), 'username', $this->sortDirection, $this->sortColumn, 'optuserPanel'); ?></th>
                <th><?php echo \Joomla\CMS\HTML\HTMLHelper::_( 'grid.sort', \Joomla\CMS\Language\Text::_('USERREMINDER_EMAIL'), 'email', $this->sortDirection, $this->sortColumn, 'optuserPanel'); ?></th>
                <th><?php print \Joomla\CMS\Language\Text::_('USERREMINDER_LASTLOGINDATE'); ?></th>
                <th><?php print \Joomla\CMS\Language\Text::_('USERREMINDER_REGISTRATIONDATE'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($itemList as $i => $item) :?>
                <tr>
                    <td class="center">
                        <?php echo \Joomla\CMS\HTML\HTMLHelper::_('grid.id', $i, $item->id); ?>
                    </td>
                    <td>
                        <a target="_blank" href="<?php echo  \Joomla\CMS\Router\Route::_('index.php?option=com_users&task=user.edit&id='.(int) $item->id); ?>" title="<?php echo \Joomla\CMS\Language\Text::sprintf('COM_USERREMINDER_EDIT_USER', $this->escape($item->name)); ?>">
                            <?php echo $this->escape($item->name); ?>
                        </a>
                    </td>
                    <td><?php echo $item->username?></td>
                    <td><?php echo $item->email?></td>
                    <td><?php echo $item->lastvisitDate?></td>
                    <td><?php echo $item->registerDate?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
            <tr>
                <td colspan="6">
                    <br />
                    <?php  echo $this->pagination->getListFooter(); ?>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>



        <input type="hidden" name="filter_order" value="<?php echo $this->sortColumn; ?>" />
        <input type="hidden" name="filter_order_Dir" value="<?php echo $this->sortDirection; ?>" />
        <input type="hidden" name="task" id="task" value="optuserPanel" />
        <input type="hidden" value="0" name="boxchecked" />
        <input type="hidden" id="selectedTab" name="selectedTab" value="<?php echo $selectedTab;?>" />

</div>
</form>
<script type="text/javascript">
	function tableOrdering( order, dir, task ){
	        var form = document.adminForm;
	 
	        form.filter_order.value = order;
	        form.filter_order_Dir.value = dir;
	        document.adminForm.submit( task );
	}
</script>
