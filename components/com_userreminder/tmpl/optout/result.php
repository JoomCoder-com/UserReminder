<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_userreminder
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
?>
<div class="userreminder optout-result">
    <?php if ($this->success): ?>
        <div class="alert alert-success" role="alert">
            <h4 class="alert-heading"><?php echo Text::_('COM_USERREMINDER_OPTOUT_SUCCESS_HEADING'); ?></h4>
            <p class="mb-0"><?php echo Text::_('COM_USERREMINDER_OPTOUT_SUCCESS'); ?></p>
        </div>
    <?php else: ?>
        <div class="alert alert-danger" role="alert">
            <h4 class="alert-heading"><?php echo Text::_('COM_USERREMINDER_OPTOUT_FAILED_HEADING'); ?></h4>
            <p class="mb-0"><?php echo Text::_('COM_USERREMINDER_OPTOUT_FAILED'); ?></p>
        </div>
    <?php endif; ?>
</div>