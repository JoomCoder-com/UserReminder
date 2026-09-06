<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_userreminder
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \JoomCoder\Component\UserReminder\Administrator\View\Optoutusers\HtmlView $this */

HTMLHelper::_('behavior.multiselect');
Text::script('COM_USERREMINDER_OPTOUT_REMOVE_PICKED');

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->getRegistry()->addRegistryFile('media/com_userreminder/joomla.asset.json');
$wa->useScript('com_userreminder.optoutusers-select');

$listOrder = $this->escape($this->state->get('list.ordering', 'a.name'));
$listDirn  = $this->escape($this->state->get('list.direction', 'ASC'));
?>
<form action="<?php echo Route::_('index.php?option=com_userreminder&view=optoutusers'); ?>" method="post" name="adminForm" id="adminForm">
    <div id="j-main-container" class="j-main-container">
        <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
                <a class="nav-link active" href="<?php echo Route::_('index.php?option=com_userreminder&view=optoutusers'); ?>">
                    <?php echo Text::_('COM_USERREMINDER_OPTOUT_USERS2'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo Route::_('index.php?option=com_userreminder&view=optoutusers&layout=usergroup'); ?>">
                    <?php echo Text::_('COM_USERREMINDER_OPTUSER_GROUP'); ?>
                </a>
            </li>
        </ul>

        <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

        <?php if (empty($this->items)) : ?>
            <div class="alert alert-info">
                <span class="icon-info-circle" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
                <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
            </div>
        <?php else : ?>
            <table class="table table-striped" id="optoutusersList">
                <caption class="visually-hidden">
                    <?php echo Text::_('COM_USERREMINDER_OPTOUT_USERS2'); ?>,
                    <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?> </span>,
                    <span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
                </caption>
                <thead>
                    <tr>
                        <th class="w-1 text-center">
                            <?php echo HTMLHelper::_('grid.checkall'); ?>
                        </th>
                        <th scope="col">
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_USERREMINDER_NAME', 'a.name', $listDirn, $listOrder); ?>
                        </th>
                        <th scope="col">
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_USERREMINDER_USERNAME', 'a.username', $listDirn, $listOrder); ?>
                        </th>
                        <th scope="col">
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_USERREMINDER_EMAIL', 'a.email', $listDirn, $listOrder); ?>
                        </th>
                        <th scope="col">
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_USERREMINDER_LASTLOGINDATE', 'a.lastvisitDate', $listDirn, $listOrder); ?>
                        </th>
                        <th scope="col">
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_USERREMINDER_REGISTRATIONDATE', 'a.registerDate', $listDirn, $listOrder); ?>
                        </th>
                        <th scope="col" class="w-1 text-center">
                            <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->items as $i => $row) : ?>
                        <tr class="row<?php echo $i % 2; ?>">
                            <td class="text-center">
                                <?php echo HTMLHelper::_('grid.id', $i, (int) $row->id); ?>
                            </td>
                            <th scope="row">
                                <a href="<?php echo Route::_('index.php?option=com_users&task=user.edit&id=' . (int) $row->id); ?>">
                                    <?php echo htmlspecialchars((string) ($row->name ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </th>
                            <td><?php echo htmlspecialchars((string) ($row->username ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($row->email ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?php echo !empty($row->lastvisitDate)
                                    ? HTMLHelper::_('date', $row->lastvisitDate, Text::_('DATE_FORMAT_LC4'))
                                    : '—'; ?>
                            </td>
                            <td>
                                <?php echo !empty($row->registerDate)
                                    ? HTMLHelper::_('date', $row->registerDate, Text::_('DATE_FORMAT_LC4'))
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

        <?php // Batch-style "Select Users" dialog (toolbar popup button target). ?>
        <?php if (!empty($this->inlineDialog)) : ?>
        <template id="userreminder-select-users-dialog"><?php echo $this->loadTemplate('select_body'); ?></template>
        <?php else : ?>
        <div class="modal fade" id="userreminder-select-users-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><?php echo Text::_('COM_USERREMINDER_OPTOUT_ADD_USERS'); ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
                    </div>
                    <div class="modal-body"><?php echo $this->loadTemplate('select_body'); ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</form>
