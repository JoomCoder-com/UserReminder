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
?>
<div class="row">
    <div class="col-md-12">
        <h1><?php echo Text::_('COM_USERREMINDER_HELP_HEADING'); ?></h1>
        <p><?php echo Text::_('COM_USERREMINDER_HELP_INTRO'); ?></p>

        <div class="card mt-3">
            <div class="card-header">
                <h3><?php echo Text::_('COM_USERREMINDER_HELP_PARAMS_TITLE'); ?></h3>
            </div>
            <div class="card-body">
                <p><?php echo Text::_('COM_USERREMINDER_HELP_PARAMS_DESC'); ?></p>
                <p><?php echo Text::_('COM_USERREMINDER_HELP_PLACEHOLDERS'); ?></p>
                <ul>
                    <li><code>{NAME}</code> &mdash; <?php echo Text::_('COM_USERREMINDER_PLACEHOLDER_NAME'); ?></li>
                    <li><code>{SITENAME}</code> &mdash; <?php echo Text::_('COM_USERREMINDER_PLACEHOLDER_SITENAME'); ?></li>
                    <li><code>{SITELINK}</code> &mdash; <?php echo Text::_('COM_USERREMINDER_PLACEHOLDER_SITEURL'); ?></li>
                    <li><code>{USERNAME}</code> &mdash; <?php echo Text::_('COM_USERREMINDER_PLACEHOLDER_USERNAME'); ?></li>
                    <li><code>{PASSWORD_RESET_URL}</code> &mdash; <?php echo Text::_('COM_USERREMINDER_PLACEHOLDER_RESET'); ?></li>
                    <li><code>{OPTOUT_URL}</code> &mdash; <?php echo Text::_('COM_USERREMINDER_PLACEHOLDER_OPTOUT'); ?></li>
                    <li><code>{ACTIVATE_URL}</code> &mdash; <?php echo Text::_('COM_USERREMINDER_PLACEHOLDER_ACTIVATE'); ?></li>
                </ul>
                <p class="alert alert-info"><?php echo Text::_('COM_USERREMINDER_HELP_MAILTEMPLATES_DESC'); ?></p>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">
                <h3><?php echo Text::_('COM_USERREMINDER_HELP_SCHEDULER_TITLE'); ?></h3>
            </div>
            <div class="card-body">
                <p><?php echo Text::_('COM_USERREMINDER_HELP_SCHEDULER_DESC'); ?></p>
            </div>
        </div>
    </div>
</div>