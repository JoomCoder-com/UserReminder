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
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \JoomCoder\Component\UserReminder\Administrator\View\Log\HtmlView $this */

HTMLHelper::_('behavior.multiselect');

$listOrder = $this->escape($this->state->get('list.ordering', 'id'));
$listDirn  = $this->escape($this->state->get('list.direction', 'DESC'));
?>
<form action="<?php echo Route::_('index.php?option=com_userreminder&view=log'); ?>" method="post" name="adminForm" id="adminForm">
    <div id="j-main-container" class="j-main-container">
        <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

        <?php if (empty($this->items)) : ?>
            <div class="alert alert-info">
                <span class="icon-info-circle" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
                <?php echo Text::_('COM_USERREMINDER_LOG_EMPTY'); ?>
            </div>
        <?php else : ?>
            <table class="table table-striped" id="logList">
                <caption class="visually-hidden">
                    <?php echo Text::_('COM_USERREMINDER_TOOLBAR_LOG'); ?>,
                    <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?> </span>,
                    <span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
                </caption>
                <thead>
                    <tr>
                        <th class="w-1 text-center">
                            <?php echo HTMLHelper::_('grid.checkall'); ?>
                        </th>
                        <th scope="col">
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_USERREMINDER_USER_NAME', 'username', $listDirn, $listOrder); ?>
                        </th>
                        <th scope="col">
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_USERREMINDER_ACTION_DESCRIPTION', 'description', $listDirn, $listOrder); ?>
                        </th>
                        <th scope="col">
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_USERREMINDER_ACTION_DATE', 'date', $listDirn, $listOrder); ?>
                        </th>
                        <th scope="col" class="w-1 text-center">
                            <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'id', $listDirn, $listOrder); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->items as $i => $row) : ?>
                        <tr>
                            <td class="text-center">
                                <?php echo HTMLHelper::_('grid.id', $i, (int) $row->id); ?>
                            </td>
                            <th scope="row"><?php echo htmlspecialchars((string) ($row->username ?? ''), ENT_QUOTES, 'UTF-8'); ?></th>
                            <td><?php echo htmlspecialchars((string) ($row->description ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?php echo !empty($row->date)
                                    ? HTMLHelper::_('date', $row->date, Text::_('DATE_FORMAT_LC4'))
                                    : '—'; ?>
                            </td>
                            <td class="text-center"><?php echo (int) $row->id; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php echo $this->pagination->getListFooter(); ?>
        <?php endif; ?>

        <input type="hidden" name="task" value="">
        <input type="hidden" name="boxchecked" value="0">
        <?php echo HTMLHelper::_('form.token'); ?>
    </div>
</form>
