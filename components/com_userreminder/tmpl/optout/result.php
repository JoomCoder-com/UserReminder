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
                    <?php if ($this->status === 'done') : ?>
                        <div class="alert alert-success" role="alert">
                            <h1 class="alert-heading h4"><?php echo Text::_('COM_USERREMINDER_OPTOUT_SUCCESS_HEADING'); ?></h1>
                            <p class="mb-0"><?php echo Text::_('COM_USERREMINDER_OPTOUT_SUCCESS'); ?></p>
                        </div>
                        <p class="text-muted"><?php echo Text::_('COM_USERREMINDER_OPTOUT_CHANGED_MIND'); ?></p>
                        <form action="<?php echo Route::_('index.php?option=com_userreminder&task=optout.resubscribe'); ?>"
                            method="post" class="d-inline">
                            <input type="hidden" name="uid" value="<?php echo htmlspecialchars($this->code, ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" class="btn btn-outline-primary">
                                <?php echo Text::_('COM_USERREMINDER_OPTOUT_RESUBSCRIBE'); ?>
                            </button>
                            <?php echo HTMLHelper::_('form.token'); ?>
                        </form>
                    <?php elseif ($this->status === 'already') : ?>
                        <div class="alert alert-info" role="alert">
                            <h1 class="alert-heading h4"><?php echo Text::_('COM_USERREMINDER_OPTOUT_ALREADY_HEADING'); ?></h1>
                            <p class="mb-0"><?php echo Text::_('COM_USERREMINDER_OPTOUT_ALREADY'); ?></p>
                        </div>
                        <form action="<?php echo Route::_('index.php?option=com_userreminder&task=optout.resubscribe'); ?>"
                            method="post" class="d-inline">
                            <input type="hidden" name="uid" value="<?php echo htmlspecialchars($this->code, ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" class="btn btn-primary">
                                <?php echo Text::_('COM_USERREMINDER_OPTOUT_RESUBSCRIBE'); ?>
                            </button>
                            <?php echo HTMLHelper::_('form.token'); ?>
                        </form>
                    <?php elseif ($this->status === 'resubscribed') : ?>
                        <div class="alert alert-success" role="alert">
                            <h1 class="alert-heading h4"><?php echo Text::_('COM_USERREMINDER_OPTOUT_RESUBSCRIBED_HEADING'); ?></h1>
                            <p class="mb-0"><?php echo Text::_('COM_USERREMINDER_OPTOUT_RESUBSCRIBED'); ?></p>
                        </div>
                    <?php elseif ($this->status === 'notoptedout') : ?>
                        <div class="alert alert-info" role="alert">
                            <h1 class="alert-heading h4"><?php echo Text::_('COM_USERREMINDER_OPTOUT_NOTOPTEDOUT_HEADING'); ?></h1>
                            <p class="mb-0"><?php echo Text::_('COM_USERREMINDER_OPTOUT_NOTOPTEDOUT'); ?></p>
                        </div>
                    <?php else : ?>
                        <div class="alert alert-danger" role="alert">
                            <h1 class="alert-heading h4"><?php echo Text::_('COM_USERREMINDER_OPTOUT_FAILED_HEADING'); ?></h1>
                            <p class="mb-0"><?php echo Text::_('COM_USERREMINDER_OPTOUT_FAILED'); ?></p>
                        </div>
                    <?php endif; ?>

                    <a class="btn btn-outline-secondary mt-3" href="<?php echo Uri::root(); ?>">
                        <?php echo Text::_('COM_USERREMINDER_OPTOUT_BACK_HOME'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
