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

\Joomla\CMS\HTML\HTMLHelper::addIncludePath(JPATH_COMPONENT.'/helpers/html');
\Joomla\CMS\HTML\HTMLHelper::_('bootstrap.tooltip','.hasTooltip');
\Joomla\CMS\HTML\HTMLHelper::_('behavior.multiselect');
\Joomla\CMS\HTML\HTMLHelper::_('dropdown.init');
\Joomla\CMS\HTML\HTMLHelper::_('formbehavior.chosen', 'select');

  $itemlist = $this->itemlistactionlog;
?>

<div>

    <?php echo HTMLHelper::_('uitab.startTabSet', 'mytab', ['active' => 'log', 'recall' => true, 'breakpoint' => 768]); ?>
    <?php echo HTMLHelper::_('uitab.addTab', 'mytab', 'log', Text::_('USERREMINDER_ACTION_LOG')); ?>
    <form method="post" name="adminForm" id="adminForm" action="index.php?option=com_userreminder">
        <table class="table table-striped" id="articleList">
            <thead>
            <tr>
                <th><?php print \Joomla\CMS\Language\Text::_('USERREMINDER_USER_ID'); ?></th>
                <th><?php print \Joomla\CMS\Language\Text::_('USERREMINDER_USER_NAME'); ?></th>
                <th><?php print \Joomla\CMS\Language\Text::_('USERREMINDER_ACTION_DESCRIPTION'); ?></th>
                <th><?php print \Joomla\CMS\Language\Text::_('USERREMINDER_ACTION_DATE'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php
            foreach ($itemlist as $item){
                print "<tr><td>\n";
                print $item["userId"];
                print "</td>\n";
                print "<td>\n";
                print $item["username"];
                print "</td>\n";
                print "<td>\n";
                print \Joomla\CMS\Language\Text::_($item["description"]);
                print "</td>\n";
                print "<td>\n";
                print $item["date"];
                print "</td></tr>";
            }
            ?>
            </tbody>
            <tfoot>
            <tr>
                <td colspan="9">
                    <br />

                    <?php echo $this->paginationActionLog->getListFooter(); ?>
                    <input type="hidden" name="task" value="displayActionLog" />
                    <input type="hidden" name="boxchecked" value="1" />

                </td>
            </tr>
            </tfoot>
        </table>
    </form>
    <?php echo HTMLHelper::_('uitab.endTab'); ?>
    <?php echo HTMLHelper::_('uitab.endTabSet'); ?>

</div>
