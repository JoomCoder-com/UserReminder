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

use JoomCoder\Component\UserReminder\Administrator\Service\Audience;
use JoomCoder\Component\UserReminder\Administrator\Service\ReminderOptions;
use JoomCoder\Component\UserReminder\Administrator\Service\SendService;
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
        $db = $this->getDbo();

        // Exactly the users the inactive-user run targets.
        $query = Audience::query($db, ReminderOptions::fromComponent(), SendService::TYPE_INACTIVE_USER)
            ->select('a.id, a.name, a.username, a.email, a.lastvisitDate')
            ->select('DATEDIFF(UTC_TIMESTAMP(), a.lastvisitDate) AS nodays')
            ->select('b.datesent, b.remindernumber');

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
