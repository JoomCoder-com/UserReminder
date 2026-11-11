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
 * Table for #__userreminder_log — append-only audit log.
 *
 * Bulk writes stay on the batched multi-row INSERT in SendService (hot path);
 * this table is the single-row writer with validation for everything else.
 *
 * @since  4.0.0
 */
class LogTable extends Table
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
        parent::__construct('#__userreminder_log', 'id', $db);
    }

    /**
     * Validate and normalise a log row before it is written.
     *
     * @return  boolean  Always true; throws on invalid data.
     *
     * @throws  \UnexpectedValueException  When no user id is set, or neither a
     *                                     description nor a message_key is present.
     *
     * @since  6.1.0
     */
    public function check(): bool
    {
        if ((int) $this->userId <= 0) {
            throw new \UnexpectedValueException(Text::_('COM_USERREMINDER_ERROR_LOG_USER_REQUIRED'));
        }

        $this->userId      = (int) $this->userId;
        $this->username    = trim((string) $this->username);
        $this->description = trim((string) $this->description);
        $this->message_key = trim((string) ($this->message_key ?? ''));

        if ($this->description === '' && $this->message_key === '') {
            throw new \UnexpectedValueException(Text::_('COM_USERREMINDER_ERROR_LOG_EMPTY'));
        }

        // Log dates are UTC, same as every other timestamp this component
        // writes (ReminderOptions::utcNow is the single source of truth).
        if (empty($this->date)) {
            $this->date = \JoomCoder\Component\UserReminder\Administrator\Service\ReminderOptions::utcNow();
        }

        return true;
    }
}
