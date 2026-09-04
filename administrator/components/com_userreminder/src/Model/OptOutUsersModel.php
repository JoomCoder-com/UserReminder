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

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\QueryInterface;

/**
 * Opt-out users list model.
 *
 * Standard SearchTools list of opted-out users. Search and ordering state is
 * handled the Joomla way via the filter form (forms/filter_optoutusers.xml).
 *
 * @since  4.0.0
 */
class OptOutUsersModel extends ListModel
{
    /**
     * The filter form name.
     *
     * @var  string
     *
     * @since  4.0.0
     */
    protected $filterFormName = 'filter_optoutusers';

    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'a.id', 'a.name', 'a.username', 'a.email', 'a.registerDate', 'a.lastvisitDate',
            ];
        }

        parent::__construct($config);
    }

    protected function populateState($ordering = 'a.name', $direction = 'asc'): void
    {
        $search = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string');
        $this->setState('filter.search', $search);

        parent::populateState($ordering, $direction);
    }

    /**
     * Users on the opt-out list, with standard search and ordering.
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
            ->from($db->quoteName('#__users', 'a'))
            ->innerJoin($db->quoteName('#__userreminder_optout', 'o') . ' ON o.user_id = a.id');

        $search = (string) $this->getState('filter.search', '');

        if ($search !== '') {
            $search = '%' . $db->escape($search, true) . '%';
            $query->where(
                '(a.name LIKE ' . $db->quote($search, false) .
                ' OR a.username LIKE ' . $db->quote($search, false) .
                ' OR a.email LIKE ' . $db->quote($search, false) . ')'
            );
        }

        $ordering  = (string) $this->getState('list.ordering', 'a.name');
        $direction = (string) $this->getState('list.direction', 'ASC');

        // Whitelist ordering to avoid injection via state.
        $allowedOrder = ['a.name', 'a.username', 'a.email', 'a.registerDate', 'a.lastvisitDate', 'a.id'];

        if (!\in_array($ordering, $allowedOrder, true)) {
            $ordering = 'a.name';
        }

        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

        $query->order($db->quoteName($ordering) . ' ' . $direction);

        return $query;
    }

    /**
     * Return all opted-out user ids (used to hide them from the picker modal).
     *
     * @return  int[]
     *
     * @since   4.0.0
     */
    public function getOptedOutIds(): array
    {
        $db    = $this->getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('user_id'))
            ->from($db->quoteName('#__userreminder_optout'));

        $db->setQuery($query);

        return array_map('intval', (array) $db->loadColumn());
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
     * All user groups with a computed nesting level, for the group picker layout.
     *
     * Level is computed via the parent hierarchy rather than a DB column.
     *
     * @return  object[]
     *
     * @since   4.2.0
     */
    public function getUserGroups(): array
    {
        $db    = $this->getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'parent_id', 'title', 'lft', 'rgt']))
            ->from($db->quoteName('#__usergroups'))
            ->order('lft ASC');

        $db->setQuery($query);

        $groups = $db->loadObjectList() ?: [];

        $byId = [];
        foreach ($groups as $g) {
            $byId[(int) $g->id] = $g;
            $g->level = 0;
        }

        foreach ($groups as $g) {
            $level   = 0;
            $pid     = (int) $g->parent_id;
            $visited = [];

            while ($pid !== 0 && isset($byId[$pid]) && !isset($visited[$pid])) {
                $visited[$pid] = true;
                $level++;
                $pid = (int) $byId[$pid]->parent_id;

                // Safety cap for malformed trees.
                if ($level > 20) {
                    break;
                }
            }

            $g->level = $level;
        }

        return $groups;
    }

    /**
     * Mark a batch of users as opted out.
     *
     * Already opted-out users are skipped so re-adding a selection is safe.
     *
     * @param   int[]  $userIds
     *
     * @return  int  Number of users added.
     *
     * @since   4.0.0
     */
    public function addOptUsers(array $userIds): int
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));

        if (empty($userIds)) {
            return 0;
        }

        $db = $this->getDbo();

        $query = $db->getQuery(true)
            ->select($db->quoteName('user_id'))
            ->from($db->quoteName('#__userreminder_optout'))
            ->where($db->quoteName('user_id') . ' IN (' . implode(',', $userIds) . ')');

        $db->setQuery($query);

        $existing = array_map('intval', (array) $db->loadColumn());
        $new      = array_values(array_diff($userIds, $existing));

        if (empty($new)) {
            return 0;
        }

        $insert = $db->getQuery(true)
            ->insert($db->quoteName('#__userreminder_optout'))
            ->columns($db->quoteName('user_id'));

        foreach ($new as $id) {
            $insert->values((int) $id);
        }

        $db->setQuery($insert);
        $db->execute();

        return \count($new);
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
