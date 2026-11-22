<?php
/**
 * @package     JoomCoder.UserReminder
 * @copyright   Copyright (c) JoomCoder (https://www.joomcoder.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/**
 * "Action to Perform" badge: what the next run does for this user
 * (SendService::plannedAction()).
 *
 * @var  array  $displayData  ['action' => send|wait|done|delete|removegroups|off]
 */
$badges = [
    'send'         => ['bg-info', 'COM_USERREMINDER_ACTIONSEND'],
    'wait'         => ['bg-secondary', 'COM_USERREMINDER_ACTION_WAIT'],
    'done'         => ['bg-success', 'COM_USERREMINDER_ACTION_DONE'],
    'delete'       => ['bg-danger', 'COM_USERREMINDER_ACTION_DELETE'],
    'removegroups' => ['bg-warning text-dark', 'COM_USERREMINDER_ACTION_REMOVEGROUPS'],
    'off'          => ['bg-light text-dark border', 'COM_USERREMINDER_ACTION_OFF'],
];

[$class, $key] = $badges[$displayData['action'] ?? ''] ?? $badges['send'];
?>
<span class="badge <?php echo $class; ?>" title="<?php echo Text::_($key . '_DESC'); ?>"><?php echo Text::_($key); ?></span>
