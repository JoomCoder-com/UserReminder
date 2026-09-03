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
<div class="userreminder optout-confirm">
    <div class="alert alert-warning" role="alert">
        <h4 class="alert-heading"><?php echo Text::_('COM_USERREMINDER_OPTOUT_TITLE'); ?></h4>
        <p class="mb-3"><?php echo Text::_('COM_USERREMINDER_OPTOUT_CONFIRMATION'); ?></p>
        <hr>
        <a class="btn btn-sm btn-outline-success"
           href="<?php echo htmlspecialchars($this->confirmUrl, ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo Text::_('COM_USERREMINDER_OPTOUT_YES'); ?>
        </a>
        <a class="btn btn-sm btn-outline-secondary" href="<?php echo \Joomla\CMS\Uri\Uri::base(); ?>">
            <?php echo Text::_('COM_USERREMINDER_OPTOUT_NO'); ?>
        </a>
    </div>
</div>