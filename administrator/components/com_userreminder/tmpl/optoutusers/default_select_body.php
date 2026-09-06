<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_userreminder
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Batch-style body for the "Select Users" toolbar modal (see com_content
 * default_batch_body.php). Loaded into a joomla-dialog template from
 * tmpl/optoutusers/default.php. Picks from the com_users iframe are staged
 * below as userids[] inputs and added with task=optoutusers.save.
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \JoomCoder\Component\UserReminder\Administrator\View\Optoutusers\HtmlView $this */

$modalUrl = 'index.php?option=com_users&view=users&layout=modal&tmpl=component&field=userreminder_optout_pick';

// Hide already opted-out users from the picker (core excluded param, same as the user field layout).
if (!empty($this->excludedIds)) {
    $modalUrl .= '&excluded=' . base64_encode(json_encode(array_values(array_map('intval', (array) $this->excludedIds))));
}

$modalUrl = Route::_($modalUrl);
?>
<div class="p-3">
    <p><?php echo Text::_('COM_USERREMINDER_OPTOUT_ADD_USERS_DESC'); ?></p>

    <iframe src="<?php echo $modalUrl; ?>" width="100%" height="420" loading="lazy"
        title="<?php echo $this->escape(Text::_('COM_USERREMINDER_OPTOUT_SELECT_USERS')); ?>"></iframe>

    <h3 class="h6 mt-3"><?php echo Text::_('COM_USERREMINDER_OPTOUT_SELECTED_USERS'); ?></h3>

    <table class="table table-sm">
        <caption class="visually-hidden"><?php echo Text::_('COM_USERREMINDER_OPTOUT_SELECTED_USERS'); ?></caption>
        <thead>
            <tr>
                <th scope="col"><?php echo Text::_('COM_USERREMINDER_NAME'); ?></th>
                <th scope="col" class="text-end"><?php echo Text::_('JGRID_HEADING_ID'); ?></th>
                <th scope="col" class="text-end w-1"><?php echo Text::_('JTOOLBAR_DELETE'); ?></th>
            </tr>
        </thead>
        <tbody id="userreminder-picked-users">
            <tr id="userreminder-picked-empty">
                <td colspan="3" class="text-center text-muted">
                    <?php echo Text::_('COM_USERREMINDER_OPTOUT_NO_USERS_SELECTED'); ?>
                </td>
            </tr>
        </tbody>
    </table>

    <joomla-toolbar-button task="optoutusers.save" class="ms-auto">
        <button type="button" class="btn btn-success"><?php echo Text::_('COM_USERREMINDER_OPTOUT_ADD_SELECTED'); ?></button>
    </joomla-toolbar-button>
</div>
