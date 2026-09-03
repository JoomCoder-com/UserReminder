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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Joomla\CMS\Application\AdministratorApplication $app */
$app = Factory::getApplication();
?>
<div class="row">
    <div class="col-md-12">
        <div id="j-main-container" class="j-main-container">
            <?php if (!$this->systemPluginEnabled): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <h4 class="alert-heading"><?php echo Text::_('COM_USERREMINDER_PLUGIN_DISABLED_HEADING'); ?></h4>
                    <p class="mb-2"><?php echo Text::_('COM_USERREMINDER_PLUGIN_DISABLED_TEXT'); ?></p>
                    <a class="btn btn-sm btn-primary"
                       href="<?php echo Route::_('index.php?option=com_plugins&filter_folder=system&filter_search=userreminder'); ?>">
                        <?php echo Text::_('COM_USERREMINDER_PLUGIN_DISABLED_CTA'); ?>
                    </a>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3><?php echo Text::_('COM_USERREMINDER_CPANEL_MANAGE_TITLE'); ?></h3>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-3">
                        <a class="btn btn-success btn-lg flex-fill" href="<?php echo Route::_('index.php?option=com_userreminder&view=reminders'); ?>">
                            <i class="fas fa-user-clock fa-3x d-block mb-2"></i>
                            <?php echo Text::_('COM_USERREMINDER_CPANEL_REMINDERS'); ?>
                        </a>
                        <a class="btn btn-warning btn-lg flex-fill" href="<?php echo Route::_('index.php?option=com_userreminder&view=activeusers'); ?>">
                            <i class="fas fa-user-check fa-3x d-block mb-2"></i>
                            <?php echo Text::_('COM_USERREMINDER_CPANEL_ACTIVE_USERS'); ?>
                        </a>
                        <a class="btn btn-primary btn-lg flex-fill" href="<?php echo Route::_('index.php?option=com_userreminder&view=optoutusers'); ?>">
                            <i class="fas fa-user-slash fa-3x d-block mb-2"></i>
                            <?php echo Text::_('COM_USERREMINDER_CPANEL_OPTOUT'); ?>
                        </a>
                        <a class="btn btn-dark btn-lg flex-fill" href="<?php echo Route::_('index.php?option=com_userreminder&view=log'); ?>">
                            <i class="fas fa-clipboard-list fa-3x d-block mb-2"></i>
                            <?php echo Text::_('COM_USERREMINDER_CPANEL_LOG'); ?>
                        </a>
                        <a class="btn btn-light btn-lg flex-fill" href="<?php echo Route::_('index.php?option=com_config&view=component&component=com_userreminder'); ?>">
                            <i class="fas fa-cog fa-3x d-block mb-2"></i>
                            <?php echo Text::_('COM_USERREMINDER_CPANEL_PARAMS'); ?>
                        </a>
                        <a class="btn btn-info btn-lg flex-fill" href="<?php echo Route::_('index.php?option=com_userreminder&view=help'); ?>">
                            <i class="fas fa-info-circle fa-3x d-block mb-2"></i>
                            <?php echo Text::_('COM_USERREMINDER_CPANEL_HELP'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>