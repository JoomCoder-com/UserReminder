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
 * Table for #__userreminder_optout — one row per user that has opted out.
 *
 * The primary key is a fixed user id, never auto-incremented, so the
 * empty-key-INSERT / non-empty-key-UPDATE pattern of Table::store cannot be
 * used directly: store() here always INSERTs (after an existence check) so a
 * repeated store for the same user stays idempotent.
 *
 * @since  4.0.0
 */
class OptOutTable extends Table
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
        parent::__construct('#__userreminder_optout', 'user_id', $db);
    }

    /**
     * Validate the row before it is written.
     *
     * @return  boolean  Always true; throws on invalid data.
     *
     * @throws  \UnexpectedValueException  When no user id is set.
     *
     * @since  6.1.0
     */
    public function check(): bool
    {
        if ((int) $this->user_id <= 0) {
            throw new \UnexpectedValueException(
                \Joomla\CMS\Language\Text::_('COM_USERREMINDER_ERROR_OPTOUT_USER_REQUIRED')
            );
        }

        $this->user_id = (int) $this->user_id;

        return true;
    }

    /**
     * Insert the opt-out row for the bound user, idempotent: a user already on
     * the list is silently left as-is, so callers get "on the list" semantics
     * regardless of who inserted the row first (model pre-check or a race with
     * another request).
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

        $data           = new \stdClass();
        $data->user_id = $this->user_id;

        try {
            $this->getDbo()->insertObject($this->_tbl, $data);
        } catch (\Exception $e) {
            // The row may have been inserted concurrently between the
            // existence check and the insert. Only a real failure is an error.
            if (!$this->exists()) {
                $this->setError($e->getMessage());

                return false;
            }
        }

        return true;
    }

    /**
     * Whether the bound user is already on the opt-out list.
     *
     * @return  boolean
     *
     * @since  6.1.0
     */
    public function exists(): bool
    {
        if ((int) $this->user_id <= 0) {
            return false;
        }

        $db     = $this->getDbo();
        $exists = $db->setQuery(
            'SELECT 1 FROM ' . $db->quoteName($this->_tbl)
            . ' WHERE ' . $db->quoteName($this->_tbl_key) . ' = ' . (int) $this->user_id
        )->loadResult();

        return $exists !== null;
    }
}
