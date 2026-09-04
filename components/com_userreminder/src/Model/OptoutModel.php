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

use Joomla\CMS\MVC\Model\BaseDatabaseModel;

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
     * Mark the user identified by $code as opted out (idempotent).
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

        $db     = $this->getDbo();
        $insert = $db->getQuery(true)
            ->insert($db->quoteName('#__userreminder_optout'))
            ->columns($db->quoteName('user_id'))
            ->values((int) $userId);

        $db->setQuery($insert);

        try {
            $db->execute();

            return 'done';
        } catch (\Throwable) {
            // Race condition: another request inserted in the meantime — treat as success.
            return $this->isOptedOut($userId) ? 'already' : self::STATUS_INVALID;
        }
    }

    /**
     * Remove the user identified by $code from the opt-out list (resubscribe).
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

        $db    = $this->getDbo();
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__userreminder_optout'))
            ->where($db->quoteName('user_id') . ' = ' . (int) $userId);

        $db->setQuery($query);

        try {
            $db->execute();

            return 'resubscribed';
        } catch (\Throwable) {
            return self::STATUS_INVALID;
        }
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
