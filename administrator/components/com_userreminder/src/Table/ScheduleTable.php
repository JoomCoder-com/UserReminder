<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_userreminder
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomCoder\Component\UserReminder\Administrator\Table;

\defined('_JEXEC') or die;

use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

/**
 * Table for #__userreminder_sch — daily/weekly/monthly scheduler log so we don't
 * double-fire on busy sites.
 *
 * @since  4.0.0
 */
class ScheduleTable extends Table
{
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__userreminder_sch', 'id', $db);
    }
}