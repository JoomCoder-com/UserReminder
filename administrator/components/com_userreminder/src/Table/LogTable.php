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
 * Table for #__userreminder_log — append-only audit log.
 *
 * @since  4.0.0
 */
class LogTable extends Table
{
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__userreminder_log', 'id', $db);
    }
}