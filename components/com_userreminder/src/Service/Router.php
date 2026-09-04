<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_userreminder
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomCoder\Component\UserReminder\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\Router\RouterBase;

/**
 * SEF router for com_userreminder on the site.
 *
 * Canonical URLs (also used in reminder emails):
 *   index.php?option=com_userreminder&view=optout&uid=<code>
 *   index.php?option=com_userreminder&view=optout&layout=result&status=<status>&uid=<code>
 *
 * With SEF enabled these become:
 *   /component/userreminder/<code>
 *   /component/userreminder/<code>/result
 *
 * Task URLs (task=optout.unsubscribe / task=optout.resubscribe) are left
 * untouched so form POSTs keep working.
 *
 * @since  4.0.0
 */
class Router extends RouterBase
{
    /**
     * Build a SEF URL from a query array.
     *
     * @param   array  &$query  The query array (view/uid/layout consumed, status kept).
     *
     * @return  array  URL segments.
     *
     * @since   4.0.0
     */
    public function build(&$query): array
    {
        if (!isset($query['view']) || $query['view'] !== 'optout' || isset($query['task'])) {
            return [];
        }

        $segments = [];

        unset($query['view']);

        if (!empty($query['uid'])) {
            $segments[] = $query['uid'];
            unset($query['uid']);
        }

        if (!empty($query['layout']) && $query['layout'] === 'result') {
            $segments[] = 'result';
            unset($query['layout']);
        } elseif (isset($query['layout'])) {
            unset($query['layout']);
        }

        return $segments;
    }

    /**
     * Parse SEF URL segments back into a query array.
     *
     * @param   array  &$segments  URL segments.
     *
     * @return  array  Query variables (view, uid, layout).
     *
     * @since   4.0.0
     */
    public function parse(&$segments): array
    {
        $vars  = [];
        $total = \count($segments);

        if ($total < 1 || $total > 2) {
            return [];
        }

        if ($total === 2 && $segments[1] !== 'result') {
            return [];
        }

        $vars['view'] = 'optout';
        $vars['uid']  = $segments[0];

        if ($total === 2) {
            $vars['layout'] = 'result';
        }

        // Consume the segments so the app router sees a fully parsed path.
        $segments = [];

        return $vars;
    }
}
