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
 * Log list model — paginated audit log of every reminder that has been sent.
 *
 * @since  4.0.0
 */
class LogModel extends ListModel
{
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = ['id', 'userId', 'username', 'description', 'date'];
        }

        parent::__construct($config);
    }

    protected function getListQuery(): QueryInterface
    {
        $query = $this->getDbo()->getQuery(true)
            ->select('*')
            ->from($this->getDbo()->quoteName('#__userreminder_log'))
            ->order($this->getDbo()->quoteName('id') . ' DESC');

        return $query;
    }

    /**
     * Truncate the log table.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public function clear(): void
    {
        $db = $this->getDbo();
        $db->setQuery('TRUNCATE TABLE ' . $db->quoteName('#__userreminder_log'));
        $db->execute();
    }

    /**
     * Get a list of distinct users referenced by the log, used by the toolbar
     * to render the empty-state info message.
     *
     * @return  int
     *
     * @since   4.0.0
     */
    public function countEntries(): int
    {
        return (int) $this->getDbo()->setQuery('SELECT COUNT(*) FROM #__userreminder_log')->loadResult();
    }
}