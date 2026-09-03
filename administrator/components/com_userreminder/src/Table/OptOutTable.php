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
 * Table for #__userreminder_optout — one row per user that has opted out.
 *
 * @since  4.0.0
 */
class OptOutTable extends Table
{
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__userreminder_optout', 'user_id', $db);
    }
}