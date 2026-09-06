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

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\QueryInterface;

/**
 * Activeusers list model — users who registered and have been inactive for X
 * days.
 *
 * @since  4.0.0
 */
class ActiveusersModel extends ListModel
{
    /**
     * The prefix to use with controller messages.
     *
     * @var  string
     */
    protected $filterFormName = 'filter_activeusers';

    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'a.id',
                'name', 'a.name',
                'username', 'a.username',
                'email', 'a.email',
                'lastvisitDate', 'a.lastvisitDate',
                'nodays', 'nodays',
                'datesent', 'b.datesent',
                'remindernumber', 'b.remindernumber',
                'search',
            ];
        }

        parent::__construct($config);
    }

    protected function getListQuery(): QueryInterface
    {
        $db     = $this->getDbo();
        $query  = $db->getQuery(true);
        $params = ComponentHelper::getParams('com_userreminder');
        $days   = max(1, (int) $params->get('numberOfDaysExistingUser', 180));

        // Keep nodays for display but filter sargably on the indexed column directly.
        $query->select('a.id, a.name, a.username, a.email, a.lastvisitDate')
            ->select('(TO_DAYS(NOW()) - TO_DAYS(a.lastvisitDate)) AS nodays')
            ->select('b.datesent, b.remindernumber')
            ->from($db->quoteName('#__users', 'a'))
            ->leftJoin($db->quoteName('#__userreminder', 'b') . ' ON b.userid = a.id')
            ->leftJoin($db->quoteName('#__userreminder_optout', 'o') . ' ON o.user_id = a.id')
            ->where('o.user_id IS NULL')
            ->where('a.block = 0')
            ->where('a.lastvisitDate IS NOT NULL')
            ->where($db->quoteName('a.lastvisitDate') . ' < DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY)');

        // Members of the opt-out user groups never receive reminders — keep the
        // list in sync with what the send pipeline targets (SendService).
        $groups = \JoomCoder\Component\UserReminder\Administrator\Helper\UserReminderHelper::getOptOutGroups();

        if (!empty($groups)) {
            $sub = $db->getQuery(true)
                ->select($db->quoteName('gm.user_id'))
                ->from($db->quoteName('#__user_usergroup_map', 'gm'))
                ->where($db->quoteName('gm.group_id') . ' IN (' . implode(',', $groups) . ')');

            $query->where($db->quoteName('a.id') . ' NOT IN (' . $sub . ')');
        }

        $search = (string) $this->getState('filter.search', '');
        if ($search !== '') {
            $search = '%' . $db->escape($search, true) . '%';
            $query->where(
                '(a.name LIKE ' . $db->quote($search, false)
                . ' OR a.username LIKE ' . $db->quote($search, false)
                . ' OR a.email LIKE ' . $db->quote($search, false) . ')'
            );
        }

        // Add the list ordering clause (searchtools drives list.ordering/list.direction).
        $orderCol  = $this->state->get('list.ordering', 'a.lastvisitDate');
        $orderDirn = $this->state->get('list.direction', 'asc');
        $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDirn));

        return $query;
    }
}
