<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_userreminder
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;

HTMLHelper::_('bootstrap.tooltip');
HTMLHelper::_('formbehavior.chosen', 'select');
?>
<form action="<?php echo Route::_('index.php?option=com_userreminder&view=optoutusers&layout=usergroup'); ?>" method="post" name="adminForm" id="adminForm">
    <div id="j-main-container" class="j-main-container">
        <ul class="nav nav-tabs" id="submenu">
            <li class="nav-item">
                <a class="nav-link" href="<?php echo Route::_('index.php?option=com_userreminder&view=optoutusers'); ?>">
                    <?php echo Text::_('COM_USERREMINDER_OPTOUT_USERS2'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo Route::_('index.php?option=com_userreminder&view=optoutusers&layout=userlist'); ?>">
                    <?php echo Text::_('COM_USERREMINDER_USER_LIST'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="<?php echo Route::_('index.php?option=com_userreminder&view=optoutusers&layout=usergroup'); ?>">
                    <?php echo Text::_('COM_USERREMINDER_OPTUSER_GROUP'); ?>
                </a>
            </li>
        </ul>

        <table class="table table-striped">
            <thead>
                <tr>
                    <th width="1%">
                        <input type="checkbox" name="checkall-toggle" value="" title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)">
                    </th>
                    <th><?php echo Text::_('COM_USERREMINDER_GROUP_TITLE'); ?></th>
                    <th width="20%"><?php echo Text::_('COM_USERREMINDER_USER_IN_GROUP'); ?></th>
                    <th width="5%"><?php echo Text::_('JGRID_HEADING_ID'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($this->groupList)): ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted">
                            <?php echo Text::_('COM_USERREMINDER_NO_ITEMS'); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($this->groupList as $i => $row): ?>
                        <tr>
                            <td class="center">
                                <input type="checkbox" name="cid[]"
                                       value="<?php echo (int) $row->id; ?>"
                                       <?php echo in_array((int) $row->id, $this->optgroups, true) ? 'checked="checked"' : ''; ?>
                                       onclick="Joomla.isChecked(this.checked);">
                            </td>
                            <td>
                                <?php echo str_repeat('<span class="gi">|&mdash;</span>', (int) $row->level); ?>
                                <a href="<?php echo Route::_('index.php?option=com_users&task=group.edit&id=' . (int) $row->id); ?>" target="_blank">
                                    <?php echo htmlspecialchars($row->title, ENT_QUOTES); ?>
                                </a>
                            </td>
                            <td class="center">&nbsp;</td>
                            <td class="center"><?php echo (int) $row->id; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <input type="hidden" name="task" value="">
        <?php echo HTMLHelper::_('form.token'); ?>
    </div>
</form>