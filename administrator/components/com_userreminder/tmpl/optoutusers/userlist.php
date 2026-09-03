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
<form action="<?php echo Route::_('index.php?option=com_userreminder&view=optoutusers&layout=userlist'); ?>" method="post" name="adminForm" id="adminForm">
    <div id="j-main-container" class="j-main-container">
        <div class="row mb-3">
            <div class="col-md-4">
                <div class="input-group">
                    <input type="text" name="filter_search" id="filter_search"
                           value="<?php echo htmlspecialchars($this->filterSearch, ENT_QUOTES); ?>"
                           class="form-control"
                           placeholder="<?php echo Text::_('COM_USERREMINDER_USERS_SEARCH_USERS'); ?>">
                    <button type="submit" class="btn btn-outline-secondary"><i class="fas fa-search"></i></button>
                    <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('filter_search').value='';this.form.submit();">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>

        <ul class="nav nav-tabs" id="submenu">
            <li class="nav-item">
                <a class="nav-link" href="<?php echo Route::_('index.php?option=com_userreminder&view=optoutusers'); ?>">
                    <?php echo Text::_('COM_USERREMINDER_OPTOUT_USERS2'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="<?php echo Route::_('index.php?option=com_userreminder&view=optoutusers&layout=userlist'); ?>">
                    <?php echo Text::_('COM_USERREMINDER_USER_LIST'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo Route::_('index.php?option=com_userreminder&view=optoutusers&layout=usergroup'); ?>">
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
                    <th><?php echo Text::_('COM_USERREMINDER_NAME'); ?></th>
                    <th><?php echo Text::_('COM_USERREMINDER_USERNAME'); ?></th>
                    <th><?php echo Text::_('COM_USERREMINDER_EMAIL'); ?></th>
                    <th><?php echo Text::_('COM_USERREMINDER_LASTLOGINDATE'); ?></th>
                    <th><?php echo Text::_('COM_USERREMINDER_REGISTRATIONDATE'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($this->items)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted">
                            <?php echo Text::_('COM_USERREMINDER_NO_ITEMS'); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($this->items as $i => $row): ?>
                        <tr>
                            <td class="center">
                                <?php echo HTMLHelper::_('grid.id', $i, $row->id); ?>
                            </td>
                            <td>
                                <a href="<?php echo Route::_('index.php?option=com_users&task=user.edit&id=' . (int) $row->id); ?>" target="_blank">
                                    <?php echo htmlspecialchars((string) ($row->name ?? ''), ENT_QUOTES); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars((string) ($row->username ?? ''), ENT_QUOTES); ?></td>
                            <td><?php echo htmlspecialchars((string) ($row->email ?? ''), ENT_QUOTES); ?></td>
                            <td><?php echo htmlspecialchars((string) ($row->lastvisitDate ?? ''), ENT_QUOTES); ?></td>
                            <td><?php echo htmlspecialchars((string) ($row->registerDate ?? ''), ENT_QUOTES); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="6">
                        <?php echo $this->pagination ? $this->pagination->getListFooter() : ''; ?>
                    </td>
                </tr>
            </tfoot>
        </table>

        <input type="hidden" name="task" value="">
        <input type="hidden" name="boxchecked" value="0">
        <?php echo HTMLHelper::_('form.token'); ?>
    </div>
</form>