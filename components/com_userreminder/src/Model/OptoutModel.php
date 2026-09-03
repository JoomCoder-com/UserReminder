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
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Site opt-out model — performs the opt-out write when the user confirms.
 *
 * @since  4.0.0
 */
class OptoutModel extends BaseDatabaseModel
{
    /**
     * Mark the user identified by $code as opted out.
     *
     * @param   string  $code  Opt-out code (hash from #__userreminder.optoutcode).
     *
     * @return  bool  true on success, false if the code is invalid / user missing.
     *
     * @since   4.0.0
     */
    public function optOut(string $code): bool
    {
        $code = trim($code);
        if ($code === '') {
            return false;
        }

        $db  = $this->getDbo();
        $qn  = static fn(string $col) => $db->quoteName($col);

        $query = $db->getQuery(true)
            ->select($qn('userid'))
            ->from($qn('#__userreminder'))
            ->where($qn('optoutcode') . ' = ' . $db->quote($code));

        $db->setQuery($query);
        $userId = (int) $db->loadResult();

        if ($userId <= 0) {
            return false;
        }

        // Idempotent insert — ignore duplicate keys.
        $insert = $db->getQuery(true)
            ->insert($db->quoteName('#__userreminder_optout'))
            ->columns($db->quoteName('user_id'))
            ->values((int) $userId);

        $db->setQuery($insert);

        try {
            $db->execute();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}