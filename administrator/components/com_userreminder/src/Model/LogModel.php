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
    /**
     * The prefix to use with controller messages.
     *
     * @var  string
     */
    protected $filterFormName = 'filter_log';

    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'id',
                'userId', 'userId',
                'username', 'username',
                'description', 'description',
                'date', 'date',
                'search',
            ];
        }

        parent::__construct($config);
    }

    protected function getListQuery(): QueryInterface
    {
        $db = $this->getDbo();

        // Lean: only columns the template actually uses; keeps temp tables small on large logs.
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'userId', 'username', 'description', 'date']))
            ->from($db->quoteName('#__userreminder_log'));

        // Search by username or description.
        $search = (string) $this->getState('filter.search', '');
        if ($search !== '') {
            $search = '%' . $db->escape($search, true) . '%';
            $query->where(
                '(' . $db->quoteName('username') . ' LIKE ' . $db->quote($search, false)
                . ' OR ' . $db->quoteName('description') . ' LIKE ' . $db->quote($search, false) . ')'
            );
        }

        // Add the list ordering clause (searchtools drives list.ordering/list.direction).
        $orderCol  = $this->state->get('list.ordering', 'id');
        $orderDirn = $this->state->get('list.direction', 'desc');
        $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDirn));

        return $query;
    }

    /**
     * Delete selected log rows.
     *
     * @param   int[]  $ids  Log row IDs.
     *
     * @return  int  Rows deleted.
     *
     * @since   4.2.0
     */
    public function remove(array $ids): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));

        if (empty($ids)) {
            return 0;
        }

        $db = $this->getDbo();
        $db->setQuery(
            $db->getQuery(true)
                ->delete($db->quoteName('#__userreminder_log'))
                ->where($db->quoteName('id') . ' IN (' . implode(',', $ids) . ')')
        );
        $db->execute();

        return $db->getAffectedRows();
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
     * Delete log rows older than $days days, in batches of $batchSize to avoid
     * long locks on large tables. Used by the 12-month retention task.
     *
     * @param   int  $days       Age threshold (e.g. 365).
     * @param   int  $batchSize  Rows per DELETE.
     *
     * @return  int  Total rows deleted.
     *
     * @since   4.1.0
     */
    public function pruneOld(int $days = 365, int $batchSize = 1000): int
    {
        $days      = max(1, (int) $days);
        $batchSize = max(100, min(5000, (int) $batchSize));
        $cutoff    = Factory::getDate()->modify('-' . $days . ' days')->toSql();
        $db        = $this->getDbo();
        $total     = 0;

        do {
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__userreminder_log'))
                ->where($db->quoteName('date') . ' < ' . $db->quote($cutoff))
                ->order($db->quoteName('id') . ' ASC');
            // MySQL supports LIMIT on DELETE; fall back to subselect if needed.
            try {
                $db->setQuery($query . ' LIMIT ' . $batchSize);
                $db->execute();
                $affected = $db->getAffectedRows();
            } catch (\Throwable) {
                // Fallback for stricter sql modes — delete by id subselect.
                $sub = $db->getQuery(true)
                    ->select($db->quoteName('id'))
                    ->from($db->quoteName('#__userreminder_log'))
                    ->where($db->quoteName('date') . ' < ' . $db->quote($cutoff))
                    ->order($db->quoteName('id') . ' ASC');
                $db->setQuery($sub, 0, $batchSize);
                $ids = $db->loadColumn() ?: [];
                if (empty($ids)) {
                    break;
                }
                $del = $db->getQuery(true)
                    ->delete($db->quoteName('#__userreminder_log'))
                    ->where($db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $ids)) . ')');
                $db->setQuery($del);
                $db->execute();
                $affected = $db->getAffectedRows();
            }

            $total += $affected;

            // Avoid infinite loop on 0 affected but still rows present (should not happen).
            if ($affected === 0) {
                break;
            }
        } while ($affected === $batchSize);

        return $total;
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