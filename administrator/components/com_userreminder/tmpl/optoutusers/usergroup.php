<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_userreminder
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \JoomCoder\Component\UserReminder\Administrator\View\Optoutusers\HtmlView $this */

HTMLHelper::_('behavior.multiselect');
?>
<form action="<?php echo Route::_('index.php?option=com_userreminder&view=optoutusers&layout=usergroup'); ?>" method="post" name="adminForm" id="adminForm">
    <div id="j-main-container" class="j-main-container">
        <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
                <a class="nav-link" href="<?php echo Route::_('index.php?option=com_userreminder&view=optoutusers'); ?>">
                    <?php echo Text::_('COM_USERREMINDER_OPTOUT_USERS2'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="<?php echo Route::_('index.php?option=com_userreminder&view=optoutusers&layout=usergroup'); ?>">
                    <?php echo Text::_('COM_USERREMINDER_OPTUSER_GROUP'); ?>
                </a>
            </li>
        </ul>

        <?php if (empty($this->groupList)) : ?>
            <div class="alert alert-info">
                <span class="icon-info-circle" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
                <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
            </div>
        <?php else : ?>
            <table class="table table-striped" id="optoutgroupsList">
                <caption class="visually-hidden"><?php echo Text::_('COM_USERREMINDER_OPTUSER_GROUP'); ?></caption>
                <thead>
                    <tr>
                        <th class="w-1 text-center">
                            <?php echo HTMLHelper::_('grid.checkall'); ?>
                        </th>
                        <th scope="col"><?php echo Text::_('COM_USERREMINDER_GROUP_TITLE'); ?></th>
                        <th scope="col" class="w-1 text-center"><?php echo Text::_('JGRID_HEADING_ID'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->groupList as $i => $row) : ?>
                        <tr class="row<?php echo $i % 2; ?>">
                            <td class="text-center">
                                <input type="checkbox" id="cb<?php echo $i; ?>" name="cid[]"
                                    value="<?php echo (int) $row->id; ?>"
                                    <?php echo \in_array((int) $row->id, $this->optgroups, true) ? 'checked' : ''; ?>
                                    onclick="Joomla.isChecked(this.checked);">
                            </td>
                            <th scope="row">
                                <?php echo str_repeat('&mdash; ', (int) $row->level); ?>
                                <a href="<?php echo Route::_('index.php?option=com_users&task=group.edit&id=' . (int) $row->id); ?>">
                                    <?php echo htmlspecialchars((string) $row->title, ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </th>
                            <td class="text-center"><?php echo (int) $row->id; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <input type="hidden" name="task" value="">
        <input type="hidden" name="boxchecked" value="0">
        <?php echo HTMLHelper::_('form.token'); ?>
    </div>
</form>
