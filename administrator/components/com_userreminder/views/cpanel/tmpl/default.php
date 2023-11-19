<?php
/**
 * @version		UserReminder v1.0
 * @package		userreminder
 * @copyright	Copyright � 2021 - JoomCoder - All rights reserved.
 * @license - http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 * @author		JoomCoder
 * @author mail	support@joomcoder.com
 * @website		www.joomcoder.com
 */
// no direct access
defined('_JEXEC') or die('Restricted access');
$logo = JURI::base(true)."/components/com_userreminder/assets/images/";
$path = JURI::base(true)."/";
?>


    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">


                <div class="row">

                    <div class="col-md-8">

                        <div class="card">
                            <div class="card-header">
                                <h4>Manage</h4>
                            </div>

                            <div class="card-body">

                                <div class="d-flex">

                                    <a class="btn btn-success border me-3" href="index.php?option=com_userreminder&task=displayReminders">
                                        <i class="fas fa-users fa-3x mb-1"></i><br>
                                        <?php echo \Joomla\CMS\Language\Text::_('USERREMINDER_INCOMPLETE_REGS'); ?>
                                    </a>

                                    <a class="btn btn-warning border me-3" href="index.php?option=com_userreminder&task=displayUserReminders">
                                        <i class="fas fa-user-check fa-3x mb-1"></i><br>
                                        <?php echo \Joomla\CMS\Language\Text::_('USERREMINDER_USER_REM'); ?>
                                    </a>

                                    <a class="btn btn-primary border me-3" href="index.php?option=com_userreminder&task=optuserPanel">
                                        <i class="fas fa-user-clock fa-3x mb-1"></i><br>
                                        <?php echo \Joomla\CMS\Language\Text::_('USERREMINDER_OPTOUT_USERS'); ?>
                                    </a>

                                    <a class="btn btn-dark border me-3" href="index.php?option=com_userreminder&task=displayActionLog">
                                        <i class="fas fa-clipboard-list fa-3x mb-1"></i><br>
                                        <?php echo \Joomla\CMS\Language\Text::_('USERREMINDER_ACTION_LOG'); ?>
                                    </a>

                                    <a class="btn btn-light border me-3" href="index.php?option=com_config&view=component&component=com_userreminder">
                                        <i class="fas fa-cog fa-3x mb-1"></i><br>
                                        <?php echo \Joomla\CMS\Language\Text::_('USERREMINDER_PARA'); ?>
                                    </a>

                                    <a class="btn btn-info border me-3" href="index.php?option=com_userreminder&task=help">
                                        <i class="fas fa-info-circle fa-3x mb-1"></i><br>
                                        <?php echo \Joomla\CMS\Language\Text::_('USERREMINDER_HELP'); ?>
                                    </a>

                                </div>
                                
                                

                            </div>

                        </div>

                    </div>

                    <div class="col-md-4">

                    </div>


                </div>

            </div>
        </div>
    </div>

<form method="post" name="adminForm" id="adminForm" action="index.php?option=com_userreminder">
    <input type="hidden" name="task" value="" />
</form> 