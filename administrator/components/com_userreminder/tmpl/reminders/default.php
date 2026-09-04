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

/** @var \JoomCoder\Component\UserReminder\Administrator\View\Reminders\HtmlView $this */

$listOrder = $this->escape($this->state->get('list.ordering', 'a.registerDate'));
$listDirn  = $this->escape($this->state->get('list.direction', 'ASC'));
?>
<form action="<?php echo Route::_('index.php?option=com_userreminder&view=reminders'); ?>" method="post" name="adminForm" id="adminForm">
    <div id="j-main-container" class="j-main-container">
        <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

        <?php if (empty($this->items)) : ?>
            <div class="alert alert-info">
                <span class="icon-info-circle" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
                <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
            </div>
        <?php else : ?>
            <table class="table table-striped" id="remindersList">
                <caption class="visually-hidden">
                    <?php echo Text::_('COM_USERREMINDER_TOOLBAR_REMINDERS'); ?>,
                    <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?> </span>,
                    <span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
                </caption>
                <thead>
                    <tr>
                        <th scope="col">
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_USERREMINDER_NAME', 'a.name', $listDirn, $listOrder); ?>
                        </th>
                        <th scope="col">
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_USERREMINDER_EMAIL', 'a.email', $listDirn, $listOrder); ?>
                        </th>
                        <th scope="col">
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_USERREMINDER_REGISTRATIONDATE', 'a.registerDate', $listDirn, $listOrder); ?>
                        </th>
                        <th scope="col">
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_USERREMINDER_DATESENT', 'b.datesent', $listDirn, $listOrder); ?>
                        </th>
                        <th scope="col" class="w-10 text-center">
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_USERREMINDER_NUMBER', 'b.remindernumber', $listDirn, $listOrder); ?>
                        </th>
                        <th scope="col" class="w-10 text-center">
                            <?php echo Text::_('COM_USERREMINDER_REGREMACTION'); ?>
                        </th>
                        <th scope="col" class="w-1 text-center">
                            <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->items as $row) : ?>
                        <tr>
                            <th scope="row">
                                <a href="<?php echo Route::_('index.php?option=com_users&task=user.edit&id=' . (int) $row->id); ?>">
                                    <?php echo htmlspecialchars((string) ($row->name ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </th>
                            <td><?php echo htmlspecialchars((string) ($row->email ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?php echo !empty($row->registerDate)
                                    ? HTMLHelper::_('date', $row->registerDate, Text::_('DATE_FORMAT_LC4'))
                                    : '—'; ?>
                            </td>
                            <td>
                                <?php echo !empty($row->datesent)
                                    ? HTMLHelper::_('date', $row->datesent, Text::_('DATE_FORMAT_LC4'))
                                    : '—'; ?>
                            </td>
                            <td class="text-center"><?php echo (int) ($row->remindernumber ?? 0); ?></td>
                            <td class="text-center">
                                <span class="badge bg-info text-dark"><?php echo Text::_('COM_USERREMINDER_ACTIONSEND'); ?></span>
                            </td>
                            <td class="text-center"><?php echo (int) $row->id; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php echo $this->pagination->getListFooter(); ?>
        <?php endif; ?>

        <input type="hidden" name="task" value="">
        <?php echo HTMLHelper::_('form.token'); ?>
    </div>
</form>
