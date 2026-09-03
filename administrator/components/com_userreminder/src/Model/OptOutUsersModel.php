<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_userreminder
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomCoder\Component\UserReminder\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\QueryInterface;

/**
 * Opt-out users list model.
 *
 * Single view, two layouts: default (current opt-outs) and userlist (picker
 * for users not yet opted-out). Search by name/username/email is supported
 * via the standard `filter.search` request variable.
 *
 * @since  4.0.0
 */
class OptOutUsersModel extends ListModel
{
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = ['id', 'name', 'username', 'email', 'lastvisitDate', 'registerDate'];
        }

        parent::__construct($config);
    }

    protected function populateState($ordering = 'a.name', $direction = 'asc'): void
    {
        parent::populateState($ordering, $direction);

        $search = trim((string) Factory::getApplication()->input->get('filter_search', '', 'string'));
        $this->setState('filter.search', $search);
    }

    /**
     * The list query depends on the active layout: 'optout' returns users that
     * are opted-out, 'userlist' returns users that are NOT opted-out.
     *
     * @return  QueryInterface
     *
     * @since   4.0.0
     */
    protected function getListQuery(): QueryInterface
    {
        $db    = $this->getDbo();
        $query = $db->getQuery(true);

        // Lean SELECT — never SELECT a.* on #__users (wide table, kills covering + temp tables).
        $query->select('a.id, a.name, a.username, a.email, a.registerDate, a.lastvisitDate, a.block, a.activation')
            ->from($db->quoteName('#__users', 'a'));

        $layout = Factory::getApplication()->input->get('layout', 'optout', 'string');

        if ($layout === 'userlist') {
            // Sargable / anti-join: LEFT JOIN .. IS NULL beats NOT IN (SELECT) on large tables.
            $query->leftJoin($db->quoteName('#__userreminder_optout', 'o') . ' ON o.user_id = a.id')
                ->where('o.user_id IS NULL');
        } else {
            $query->innerJoin($db->quoteName('#__userreminder_optout', 'o') . ' ON o.user_id = a.id');
        }

        $search = $this->getState('filter.search');
        if ($search !== '') {
            $search = '%' . $db->escape($search, true) . '%';
            $query->where(
                '(a.name LIKE ' . $db->quote($search, false) .
                ' OR a.username LIKE ' . $db->quote($search, false) .
                ' OR a.email LIKE ' . $db->quote($search, false) . ')'
            );
        }

        $ordering  = $this->getState('list.ordering', 'a.name');
        $direction = $this->getState('list.direction', 'ASC');

        // Whitelist ordering to avoid injection via state.
        $allowedOrder = ['a.name', 'a.username', 'a.email', 'a.registerDate', 'a.lastvisitDate', 'a.id'];
        if (!in_array($ordering, $allowedOrder, true)) {
            $ordering = 'a.name';
        }
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

        $query->order($db->quoteName($ordering) . ' ' . $direction);

        return $query;
    }

    /**
     * Return the list of opt-out user groups (configured in the component params).
     *
     * @return  array
     *
     * @since   4.0.0
     */
    public function getOptGroups(): array
    {
        $db    = $this->getDbo();
        $query = $db->getQuery(true)
            ->select('group_id')
            ->from($db->quoteName('#__userreminder_optout_usergroups'));

        $db->setQuery($query);

        return array_map(static fn($r) => (int) $r, (array) $db->loadColumn());
    }

    /**
     * Mark a batch of users as opted out.
     *
     * @param   int[]  $userIds
     *
     * @return  bool
     *
     * @since   4.0.0
     */
    public function addOptUsers(array $userIds): bool
    {
        $userIds = array_filter(array_map('intval', $userIds));

        if (empty($userIds)) {
            return false;
        }

        $db    = $this->getDbo();
        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__userreminder_optout'))
            ->columns($db->quoteName('user_id'));

        foreach ($userIds as $id) {
            $query->values((int) $id);
        }

        $db->setQuery($query);

        try {
            return (bool) $db->execute();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Remove a batch of users from the opt-out list.
     *
     * @param   int[]  $userIds
     *
     * @return  bool
     *
     * @since   4.0.0
     */
    public function removeOptUsers(array $userIds): bool
    {
        $userIds = array_filter(array_map('intval', $userIds));

        if (empty($userIds)) {
            return false;
        }

        $db    = $this->getDbo();
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__userreminder_optout'))
            ->where($db->quoteName('user_id') . ' IN (' . implode(',', $userIds) . ')');

        $db->setQuery($query);

        try {
            return (bool) $db->execute();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Replace the entire opt-out groups list with the provided IDs.
     *
     * @param   int[]  $groupIds
     *
     * @return  bool
     *
     * @since   4.0.0
     */
    public function saveOptGroups(array $groupIds): bool
    {
        $groupIds = array_filter(array_map('intval', $groupIds));

        $db = $this->getDbo();

        // TRUNCATE first so deletes go through (faster than DELETE on PK).
        try {
            $db->setQuery('TRUNCATE TABLE ' . $db->quoteName('#__userreminder_optout_usergroups'));
            $db->execute();
        } catch (\Throwable) {
            $db->setQuery('DELETE FROM ' . $db->quoteName('#__userreminder_optout_usergroups'));
            $db->execute();
        }

        if (empty($groupIds)) {
            return true;
        }

        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__userreminder_optout_usergroups'))
            ->columns($db->quoteName('group_id'));

        foreach ($groupIds as $id) {
            $query->values((int) $id);
        }

        $db->setQuery($query);

        try {
            return (bool) $db->execute();
        } catch (\Throwable) {
            return false;
        }
    }
}