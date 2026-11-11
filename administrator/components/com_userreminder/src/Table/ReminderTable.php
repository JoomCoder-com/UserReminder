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

use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

/**
 * Table for #__userreminder — one row per user/reminder state.
 *
 * The primary key is a fixed user id, never auto-incremented. Table::store's
 * UPDATE branch would silently affect 0 rows for a user without a reminder
 * row yet, so store() decides INSERT vs UPDATE on row existence.
 *
 * The send engine deliberately keeps its single-statement
 * ON DUPLICATE KEY UPDATE upsert (SendService::upsertReminderRow) on the hot
 * path — one round trip per user instead of a SELECT + INSERT/UPDATE pair.
 *
 * @since  4.0.0
 */
class ReminderTable extends Table
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
        parent::__construct('#__userreminder', 'userid', $db);
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
        if ((int) $this->userid <= 0) {
            throw new \UnexpectedValueException(Text::_('COM_USERREMINDER_ERROR_REMINDER_USER_REQUIRED'));
        }

        $this->userid         = (int) $this->userid;
        $this->remindernumber = max(0, (int) $this->remindernumber);
        $this->type           = max(0, (int) $this->type);
        $this->optoutcode     = (string) $this->optoutcode;

        return true;
    }

    /**
     * Insert or update the reminder row for the bound user.
     *
     * @param   boolean  $updateNulls  True to update fields even if they are null.
     *
     * @return  boolean  True on success.
     *
     * @since  6.1.0
     */
    public function store($updateNulls = false)
    {
        $this->check();

        if (!$this->exists()) {
            $data                 = new \stdClass();
            $data->userid         = $this->userid;
            $data->datesent       = $this->datesent;
            $data->remindernumber = $this->remindernumber;
            $data->type           = $this->type;
            $data->optoutcode     = $this->optoutcode;

            try {
                return (bool) $this->getDbo()->insertObject($this->_tbl, $data);
            } catch (\Exception $e) {
                // Raced into existence by another writer — fall through to the
                // UPDATE branch below.
                if (!$this->exists()) {
                    $this->setError($e->getMessage());

                    return false;
                }
            }
        }

        return parent::store($updateNulls);
    }

    /**
     * Whether the bound user already has a reminder row.
     *
     * @return  boolean
     *
     * @since  6.1.0
     */
    public function exists(): bool
    {
        if ((int) $this->userid <= 0) {
            return false;
        }

        $db     = $this->getDbo();
        $exists = $db->setQuery(
            'SELECT 1 FROM ' . $db->quoteName($this->_tbl)
            . ' WHERE ' . $db->quoteName($this->_tbl_key) . ' = ' . (int) $this->userid
        )->loadResult();

        return $exists !== null;
    }
}
