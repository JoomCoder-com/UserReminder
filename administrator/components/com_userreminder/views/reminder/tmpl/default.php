<?php
/**
 * @version        UserReminder v1.0
 * @package        userreminder
 * @copyright    Copyright � 2021 - JoomCoder - All rights reserved.
 * @license - http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 * @author        JoomCoder
 * @author mail    support@joomcoder.com
 * @website        www.joomcoder.com
 */

// no direct access
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

defined('_JEXEC') or die('Restricted access');
// Include the component HTML helpers.
HTMLHelper::_('bootstrap.tooltip', '.hasTooltip');
JHtml::_('behavior.multiselect');
//JHtml::_('behavior.modal');
JHtml::_('formbehavior.chosen', 'select');

?>

<form method="post" name="adminForm" id="adminForm" action="index.php?option=com_userreminder&view=displayReminders">
    <div>

        <?php echo HTMLHelper::_('uitab.startTabSet', 'mytab', ['active' => 'nonreg', 'recall' => true, 'breakpoint' => 768]); ?>
        <?php echo HTMLHelper::_('uitab.addTab', 'mytab', 'nonreg', Text::_('USERREMINDER_NONREG')); ?>
        <?php $itemlist = $this->itemlist; ?>
        <fieldset>
            <table class="table table-striped" id="articleList">
                <thead>
                <tr>
                    <th><?php print JText::_('USERREMINDER_NAME'); ?></th>
                    <th><?php print JText::_('USERREMINDER_EMAIL'); ?></th>
                    <th><?php print JText::_('USERREMINDER_REGISTRATIONDATE'); ?></th>
                    <th><?php print JText::_('USERREMINDER_TIMESINCEREG'); ?></th>
                    <th><?php print JText::_('USERREMINDER_NUMBER'); ?></th>
                    <th><?php print JText::_('USERREMINDER_REGREMACTION'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php
                foreach ($itemlist as $item) {
                    print "<tr><td>\n";
                    print $item["name"];
                    print "</td>\n";
                    print "<td>\n";
                    print $item["email"];
                    print "</td>\n";
                    print "<td>\n";
                    print $item["regDate"];
                    print "</td>\n";
                    print "<td>\n";
                    print $item["remindersent"];
                    print "</td>";
                    print "<td>\n";
                    print $item["remindernumber"];
                    print "</td>";
                    print "<td>\n";
                    print $item["action"];
                    print "</td></tr>";
                }
                ?>
                </tbody>
                <tfoot>
                <tr>
                    <td colspan="9">
                        <?php echo $this->pagination->getListFooter(); ?>

                    </td>
                </tr>
                </tfoot>
            </table>
        </fieldset>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'mytab', 'nonlogin', Text::_('USERREMINDER_NONLOGIN_DET')); ?>
        <?php $itemlist = $this->itemlistlogin; ?>
        <fieldset>
            <table class="table table-striped" id="articleList">
                <thead>
                <tr>
                    <th><?php print JText::_('USERREMINDER_NAME'); ?></th>
                    <th><?php print JText::_('USERREMINDER_EMAIL'); ?></th>
                    <th><?php print JText::_('USERREMINDER_REGISTRATIONDATE'); ?></th>
                    <th><?php print JText::_('USERREMINDER_TIMESINCEREG'); ?></th>
                    <th><?php print JText::_('USERREMINDER_NUMBER'); ?></th>
                    <th><?php print JText::_('USERREMINDER_REGREMACTION'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($itemlist as $item) {
                    print "<tr><td>\n";
                    print $item["name"];
                    print "</td>\n";
                    print "<td>\n";
                    print $item["email"];
                    print "</td>\n";
                    print "<td>\n";
                    print $item["regDate"];
                    print "</td>\n";
                    print "<td>\n";
                    print $item["remindersent"];
                    print "</td>";
                    print "<td>\n";
                    print $item["remindernumber"];
                    print "</td>";
                    print "<td>\n";
                    print $item["action"];
                    print "</td></tr>";
                } ?>
                </tbody>
                <tfoot>
                <tr>
                    <td colspan="9">
                        <del class="container">
                            <div class="pagination">
                                <?php echo $this->pagination2->getListFooter(); ?>
                            </div>
                        </del>
                    </td>
                </tr>
                </tfoot>
            </table>
        </fieldset>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>
        <?php echo HTMLHelper::_('uitab.endTabSet'); ?>


        <input type="hidden" name="task" value="displayReminders"/>
        <input type="hidden" name="view" value="userreminder"/>

        <?php echo JHtml::_('form.token'); ?>
</form> 


