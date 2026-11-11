<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_userreminder
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomCoder\Component\UserReminder\Site\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use JoomCoder\Component\UserReminder\Administrator\Table\LogTable;
use JoomCoder\Component\UserReminder\Administrator\Table\OptOutTable;

/**
 * Site opt-out model.
 *
 * Read methods (getStatus) never write. Write methods (optOut / optIn) are
 * only called from POST tasks in the OptoutController, never from the view.
 *
 * @since  4.0.0
 */
class OptoutModel extends BaseDatabaseModel
{
    /**
     * Code is unknown — no reminder row carries it.
     *
     * @since  4.0.0
     */
    public const STATUS_INVALID = 'invalid';

    /**
     * Code is valid and the user still receives reminders.
     *
     * @since  4.0.0
     */
    public const STATUS_ACTIVE = 'active';

    /**
     * Code is valid but the user is already opted out.
     *
     * @since  4.0.0
     */
    public const STATUS_OPTED_OUT = 'optedout';

    /**
     * Resolve the subscription status for an opt-out code without writing.
     *
     * @param   string  $code  Opt-out code (hash from #__userreminder.optoutcode).
     *
     * @return  string  One of the STATUS_* constants.
     *
     * @since   4.0.0
     */
    public function getStatus(string $code): string
    {
        $userId = $this->getUserIdByCode($code);

        if ($userId <= 0) {
            return self::STATUS_INVALID;
        }

        return $this->isOptedOut($userId) ? self::STATUS_OPTED_OUT : self::STATUS_ACTIVE;
    }

    /**
     * Mark the user identified by $code as opted out (idempotent) and record
     * the event in the audit log.
     *
     * @param   string  $code  Opt-out code (hash from #__userreminder.optoutcode).
     *
     * @return  string  done|already|invalid
     *
     * @since   4.0.0
     */
    public function optOut(string $code): string
    {
        $userId = $this->getUserIdByCode($code);

        if ($userId <= 0) {
            return self::STATUS_INVALID;
        }

        if ($this->isOptedOut($userId)) {
            return 'already';
        }

        try {
            $table = new OptOutTable($this->getDbo());
            $table->bind(['user_id' => $userId]);

            if (!$table->store()) {
                return self::STATUS_INVALID;
            }
        } catch (\UnexpectedValueException) {
            return self::STATUS_INVALID;
        }

        $this->logEvent($userId, 'COM_USERREMINDER_OPTOUT_LOG');

        return 'done';
    }

    /**
     * Remove the user identified by $code from the opt-out list (resubscribe)
     * and record the event in the audit log.
     *
     * @param   string  $code  Opt-out code (hash from #__userreminder.optoutcode).
     *
     * @return  string  resubscribed|notoptedout|invalid
     *
     * @since   4.0.0
     */
    public function optIn(string $code): string
    {
        $userId = $this->getUserIdByCode($code);

        if ($userId <= 0) {
            return self::STATUS_INVALID;
        }

        if (!$this->isOptedOut($userId)) {
            return 'notoptedout';
        }

        try {
            $table = new OptOutTable($this->getDbo());

            if (!$table->delete($userId)) {
                return self::STATUS_INVALID;
            }
        } catch (\Throwable) {
            return self::STATUS_INVALID;
        }

        $this->logEvent($userId, 'COM_USERREMINDER_OPTIN_LOG');

        return 'resubscribed';
    }

    /**
     * Find the reminder user id for an opt-out code.
     *
     * @param   string  $code  Raw code from the request.
     *
     * @return  int  User id, or 0 when the code is empty/unknown.
     *
     * @since   4.0.0
     */
    private function getUserIdByCode(string $code): int
    {
        $code = trim($code);

        if ($code === '') {
            return 0;
        }

        $db = $this->getDbo();

        $query = $db->getQuery(true)
            ->select($db->quoteName('userid'))
            ->from($db->quoteName('#__userreminder'))
            ->where($db->quoteName('optoutcode') . ' = ' . $db->quote($code));

        $db->setQuery($query);

        return (int) $db->loadResult();
    }

    /**
     * Append an audit row to #__userreminder_log for a subscription change.
     *
     * Never fails the visitor's flow: a logging problem must not turn a
     * successful unsubscribe into an error page.
     *
     * @param   int     $userId  Joomla user id.
     * @param   string  $key     Language key for the event (stored as message_key).
     *
     * @return  void
     *
     * @since  6.1.0
     */
    private function logEvent(int $userId, string $key): void
    {
        try {
            // The log keys live in the admin language file — the scheduler
            // writes the same way from outside the admin UI.
            Factory::getApplication()->getLanguage()->load('com_userreminder', JPATH_ADMINISTRATOR);

            $db = $this->getDbo();

            $username = (string) $db->setQuery(
                'SELECT ' . $db->quoteName('username')
                . ' FROM ' . $db->quoteName('#__users')
                . ' WHERE ' . $db->quoteName('id') . ' = ' . (int) $userId
            )->loadResult();

            $table = new LogTable($db);
            $table->bind([
                'userId'      => $userId,
                'username'    => $username,
                'description' => Text::_($key),
                'message_key' => $key,
            ]);

            if (!$table->store()) {
                Factory::getApplication()->getLogger()->error(
                    'User Reminder: failed to write opt-out audit log row: ' . $table->getError()
                );
            }
        } catch (\Throwable $e) {
            Factory::getApplication()->getLogger()->error(
                'User Reminder: opt-out audit log write failed: ' . $e->getMessage()
            );
        }
    }

    /**
     * Check whether a user id is on the opt-out list.
     *
     * @param   int  $userId  Joomla user id.
     *
     * @return  bool
     *
     * @since   4.0.0
     */
    private function isOptedOut(int $userId): bool
    {
        $db = $this->getDbo();

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__userreminder_optout'))
            ->where($db->quoteName('user_id') . ' = ' . (int) $userId);

        $db->setQuery($query);

        return (int) $db->loadResult() > 0;
    }
}
