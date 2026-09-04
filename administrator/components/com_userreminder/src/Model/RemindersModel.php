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
 * Reminders list model — paginated list of users who need a registration
 * reminder (not activated OR activated but never logged in).
 *
 * @since  4.0.0
 */
class RemindersModel extends ListModel
{
    /**
     * The prefix to use with controller messages.
     *
     * @var  string
     */
    protected $filterFormName = 'filter_reminders';

    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @since   4.0.0
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'a.id',
                'name', 'a.name',
                'username', 'a.username',
                'email', 'a.email',
                'registerDate', 'a.registerDate',
                'lastvisitDate', 'a.lastvisitDate',
                'datesent', 'b.datesent',
                'remindernumber', 'b.remindernumber',
                'search',
            ];
        }

        parent::__construct($config);
    }

    /**
     * Build the SQL that powers the "Incomplete Registrations" tab.
     *
     * @return  QueryInterface
     *
     * @since   4.0.0
     */
    protected function getListQuery(): QueryInterface
    {
        $db     = $this->getDbo();
        $query  = $db->getQuery(true);
        $params = ComponentHelper::getParams('com_userreminder');
        $days   = max(1, (int) $params->get('numberOfDays', 1));

        // Sargable: column < DATE_SUB(NOW(), INTERVAL) — never wrap the column in DATE_ADD/TO_DAYS.
        $query->select('a.id, a.name, a.username, a.email, a.registerDate, a.lastvisitDate, a.block, a.activation')
            ->select('b.datesent, b.remindernumber')
            ->from($db->quoteName('#__users', 'a'))
            ->leftJoin($db->quoteName('#__userreminder', 'b') . ' ON b.userid = a.id')
            ->leftJoin($db->quoteName('#__userreminder_optout', 'o') . ' ON o.user_id = a.id')
            ->where('o.user_id IS NULL')
            ->where('a.block >= 1')
            ->where('a.activation <> ' . $db->quote(''))
            ->where('a.lastvisitDate IS NULL')
            ->where($db->quoteName('a.registerDate') . ' < DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY)');

        // Optional search — mirrors OptOutUsersModel pattern, only when filter is set.
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
        $orderCol  = $this->state->get('list.ordering', 'a.registerDate');
        $orderDirn = $this->state->get('list.direction', 'asc');
        $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDirn));

        return $query;
    }
}
