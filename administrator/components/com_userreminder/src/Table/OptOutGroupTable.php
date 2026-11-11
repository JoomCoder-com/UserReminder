<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_userreminder
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomCoder\Component\UserReminder\Administrator\Table;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

/**
 * Table for #__userreminder_optout_usergroups — user groups whose members are
 * automatically excluded from reminders.
 *
 * Like OptOutTable the primary key is a fixed group id, so store() always
 * INSERTs (idempotent after an existence check).
 *
 * @since  4.0.0
 */
class OptOutGroupTable extends Table
{
    /**
     * Constructor.
     *
     * @param   DatabaseDriver  $db  Database driver.
     *
     * @since  6.1.0
     */
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__userreminder_optout_usergroups', 'group_id', $db);
    }

    /**
     * Validate the row before it is written.
     *
     * @return  boolean  Always true; throws on invalid data.
     *
     * @throws  \UnexpectedValueException  When no group id is set.
     *
     * @since  6.1.0
     */
    public function check(): bool
    {
        if ((int) $this->group_id <= 0) {
            throw new \UnexpectedValueException(
                \Joomla\CMS\Language\Text::_('COM_USERREMINDER_ERROR_OPTOUT_GROUP_REQUIRED')
            );
        }

        $this->group_id = (int) $this->group_id;

        return true;
    }

    /**
     * Insert the group row, idempotent (see OptOutTable::store for the fixed
     * primary key rationale).
     *
     * @param   boolean  $updateNulls  True to update fields even if they are null.
     *
     * @return  boolean  True on success (including the already-present case).
     *
     * @since  6.1.0
     */
    public function store($updateNulls = false)
    {
        $this->check();

        if ($this->exists()) {
            return true;
        }

        $data            = new \stdClass();
        $data->group_id = $this->group_id;

        try {
            $this->getDbo()->insertObject($this->_tbl, $data);
        } catch (\Exception $e) {
            if (!$this->exists()) {
                $this->setError($e->getMessage());

                return false;
            }
        }

        return true;
    }

    /**
     * Whether the bound group is already on the opt-out groups list.
     *
     * @return  boolean
     *
     * @since  6.1.0
     */
    public function exists(): bool
    {
        if ((int) $this->group_id <= 0) {
            return false;
        }

        $db     = $this->getDbo();
        $exists = $db->setQuery(
            'SELECT 1 FROM ' . $db->quoteName($this->_tbl)
            . ' WHERE ' . $db->quoteName($this->_tbl_key) . ' = ' . (int) $this->group_id
        )->loadResult();

        return $exists !== null;
    }
}
