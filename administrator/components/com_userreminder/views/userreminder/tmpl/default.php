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
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

defined('_JEXEC') or die('Restricted access');

HTMLHelper::_('bootstrap.tooltip', '.hasTooltip');
JHtml::_('formbehavior.chosen', 'select');

  $itemlist = $this->itemlistexistinglogin;
?>

<div>

    <?php echo HTMLHelper::_('uitab.startTabSet', 'mytab', ['active' => 'enonlogin', 'recall' => true, 'breakpoint' => 768]); ?>
    <?php echo HTMLHelper::_('uitab.addTab', 'mytab', 'enonlogin', Text::_('USERREMINDER_ENONLOGIN_DET')); ?>
                    <form method="post" name="adminForm" id="adminForm" action="index.php?option=com_userreminder">
        <table class="table table-striped" id="articleList">
            <thead>
            <tr>
                <th><?php print JText::_('USERREMINDER_NAME'); ?></th>
                <th><?php print JText::_('USERREMINDER_EMAIL'); ?></th>
                <th><?php print JText::_('USERREMINDER_LASTLOGINDATE'); ?></th>
                <th><?php print JText::_('USERREMINDER_TIMESINCEREG'); ?></th>
                <th><?php print JText::_('USERREMINDER_NUMBER'); ?></th>
                <th><?php print JText::_('USERREMINDER_REGREMACTION'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php
            foreach ($itemlist as $item){
                print "<tr><td>\n";
                print $item["name"];
                print "</td>\n";
                print "<td>\n";
                print $item["email"];
                print "</td>\n";
                print "<td>\n";
                print $item["lastvisitDate"]."(".$item["reminderdays"]." days)";
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
                    <br />
                    <?php echo $this->paginationExistingUser->getListFooter(); ?>
                    <input type="hidden" name="task" value="displayUserReminders" />
                </td>
            </tr>
            </tfoot>
        </table>
    </form>
    <?php echo HTMLHelper::_('uitab.endTab'); ?>
    <?php echo HTMLHelper::_('uitab.endTabSet'); ?>

</div>
