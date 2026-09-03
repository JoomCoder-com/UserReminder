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
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Joomla\CMS\Pagination\Pagination $pagination */
$pagination = $this->pagination;
?>
<form action="<?php echo Route::_('index.php?option=com_userreminder&view=reminders'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th><?php echo Text::_('COM_USERREMINDER_NAME'); ?></th>
                            <th><?php echo Text::_('COM_USERREMINDER_EMAIL'); ?></th>
                            <th><?php echo Text::_('COM_USERREMINDER_REGISTRATIONDATE'); ?></th>
                            <th><?php echo Text::_('COM_USERREMINDER_DATESENT'); ?></th>
                            <th><?php echo Text::_('COM_USERREMINDER_NUMBER'); ?></th>
                            <th><?php echo Text::_('COM_USERREMINDER_REGREMACTION'); ?></th>
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
                            <?php foreach ($this->items as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row->name, ENT_QUOTES); ?></td>
                                    <td><?php echo htmlspecialchars($row->email, ENT_QUOTES); ?></td>
                                    <td><?php echo htmlspecialchars($row->registerDate, ENT_QUOTES); ?></td>
                                    <td><?php echo htmlspecialchars($row->datesent ?? '', ENT_QUOTES); ?></td>
                                    <td><?php echo (int) ($row->remindernumber ?? 0); ?></td>
                                    <td><?php echo Text::_('COM_USERREMINDER_ACTIONSEND'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="6">
                                <?php echo $pagination ? $pagination->getListFooter() : ''; ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>

                <input type="hidden" name="task" value="">
                <input type="hidden" name="boxchecked" value="0">
                <?php echo \Joomla\CMS\HTML\HTMLHelper::_('form.token'); ?>
            </div>
    </div>
</form>