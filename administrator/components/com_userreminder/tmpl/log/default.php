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

/** @var \Joomla\CMS\Pagination\Pagination $pagination */
$pagination = $this->pagination;
?>
<form action="<?php echo Route::_('index.php?option=com_userreminder&view=log'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th><?php echo Text::_('COM_USERREMINDER_USER_ID'); ?></th>
                            <th><?php echo Text::_('COM_USERREMINDER_USER_NAME'); ?></th>
                            <th><?php echo Text::_('COM_USERREMINDER_ACTION_DESCRIPTION'); ?></th>
                            <th><?php echo Text::_('COM_USERREMINDER_ACTION_DATE'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($this->items)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted">
                                    <?php echo Text::_('COM_USERREMINDER_LOG_EMPTY'); ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($this->items as $row): ?>
                                <tr>
                                    <td><?php echo (int) ($row->userId ?? 0); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($row->username ?? ''), ENT_QUOTES); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($row->description ?? ''), ENT_QUOTES); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($row->date ?? ''), ENT_QUOTES); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4">
                                <?php echo $pagination ? $pagination->getListFooter() : ''; ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>

                <input type="hidden" name="task" value="">
                <?php echo \Joomla\CMS\HTML\HTMLHelper::_('form.token'); ?>
            </div>
        </div>
    </div>
</form>