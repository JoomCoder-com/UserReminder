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
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\QueryInterface;
use JoomCoder\Component\UserReminder\Administrator\Service\SendService;

/**
 * ActiveUsers list model — users who registered and have been inactive for X
 * days.
 *
 * @since  4.0.0
 */
class ActiveUsersModel extends ListModel
{
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = ['id', 'name', 'username', 'email', 'lastvisitDate'];
        }

        parent::__construct($config);
    }

    protected function getListQuery(): QueryInterface
    {
        $db     = $this->getDbo();
        $query  = $db->getQuery(true);
        $params = ComponentHelper::getParams('com_userreminder');
        $days   = (int) $params->get('numberOfDaysExistingUser', 180);

        $query->select('a.id, a.name, a.username, a.email, a.lastvisitDate')
            ->select('(TO_DAYS(NOW()) - TO_DAYS(a.lastvisitDate)) AS nodays')
            ->select('b.datesent, b.remindernumber')
            ->from($db->quoteName('#__users', 'a'))
            ->leftJoin($db->quoteName('#__userreminder', 'b') . ' ON b.userid = a.id')
            ->leftJoin($db->quoteName('#__userreminder_optout', 'o') . ' ON o.user_id = a.id')
            ->where('o.user_id IS NULL')
            ->where('a.block = 0')
            ->where('a.lastvisitDate IS NOT NULL')
            ->where('(TO_DAYS(NOW()) - TO_DAYS(a.lastvisitDate)) > ' . $days)
            ->order($db->quoteName('a.lastvisitDate') . ' ASC');

        return $query;
    }

    public function sendTestMail(): void
    {
        $params = ComponentHelper::getParams('com_userreminder');
        $app    = Factory::getApplication();
        $userId = (int) $params->get('test_user', $app->getIdentity()->id);

        if ($userId <= 0) {
            return;
        }

        $user = Factory::getUser($userId);

        try {
            /** @var SendService $send */
            $send = Factory::getContainer()->get(SendService::class);
        } catch (\Throwable) {
            $send = new SendService(Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class));
        }

        $row = (object) [
            'id'             => $user->id,
            'name'           => $user->name,
            'username'       => $user->username,
            'email'          => $user->email,
            'activation'     => '',
            'optoutcode'     => null,
            'remindernumber' => 0,
            'datesent'       => null,
        ];

        $send->sendOne($row, SendService::TYPE_INACTIVE_USER, $params);
    }
}