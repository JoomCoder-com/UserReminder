<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_userreminder
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/** @var \JoomCoder\Component\UserReminder\Site\View\Optout\HtmlView $this */
?>
<div class="com-userreminder-optout container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card">
                <div class="card-body">
                    <h1 class="card-title h4"><?php echo Text::_('COM_USERREMINDER_OPTOUT_TITLE'); ?></h1>

                    <?php if ($this->status === 'active') : ?>
                        <div class="alert alert-warning" role="alert">
                            <?php echo Text::_('COM_USERREMINDER_OPTOUT_CONFIRMATION'); ?>
                        </div>
                        <form action="<?php echo Route::_('index.php?option=com_userreminder&task=optout.unsubscribe'); ?>"
                            method="post" class="d-inline">
                            <input type="hidden" name="uid" value="<?php echo htmlspecialchars($this->code, ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" class="btn btn-primary">
                                <?php echo Text::_('COM_USERREMINDER_OPTOUT_YES'); ?>
                            </button>
                            <?php echo HTMLHelper::_('form.token'); ?>
                        </form>
                        <a class="btn btn-outline-secondary" href="<?php echo Uri::root(); ?>">
                            <?php echo Text::_('COM_USERREMINDER_OPTOUT_NO'); ?>
                        </a>
                    <?php elseif ($this->status === 'optedout') : ?>
                        <div class="alert alert-info" role="alert">
                            <?php echo Text::_('COM_USERREMINDER_OPTOUT_ALREADY'); ?>
                        </div>
                        <form action="<?php echo Route::_('index.php?option=com_userreminder&task=optout.resubscribe'); ?>"
                            method="post" class="d-inline">
                            <input type="hidden" name="uid" value="<?php echo htmlspecialchars($this->code, ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" class="btn btn-primary">
                                <?php echo Text::_('COM_USERREMINDER_OPTOUT_RESUBSCRIBE'); ?>
                            </button>
                            <?php echo HTMLHelper::_('form.token'); ?>
                        </form>
                        <a class="btn btn-outline-secondary" href="<?php echo Uri::root(); ?>">
                            <?php echo Text::_('COM_USERREMINDER_OPTOUT_BACK_HOME'); ?>
                        </a>
                    <?php else : ?>
                        <div class="alert alert-danger" role="alert">
                            <h2 class="alert-heading h5"><?php echo Text::_('COM_USERREMINDER_OPTOUT_FAILED_HEADING'); ?></h2>
                            <p class="mb-0"><?php echo Text::_('COM_USERREMINDER_OPTOUT_FAILED'); ?></p>
                        </div>
                        <a class="btn btn-outline-secondary" href="<?php echo Uri::root(); ?>">
                            <?php echo Text::_('COM_USERREMINDER_OPTOUT_BACK_HOME'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
