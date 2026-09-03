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

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Categories\CategoryFactoryInterface;
use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Component\Router\Rules\MenuRules;
use Joomla\CMS\Component\Router\Rules\NomenuRules;
use Joomla\CMS\Component\Router\Rules\StandardRules;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\Database\DatabaseInterface;

/**
 * SEF router for com_userreminder on the site.
 *
 * Generates URLs of the form:
 *   /component/userreminder/optout/<code>/confirm
 *
 * The raw URLs the reminder emails generate are kept as a fallback when SEF
 * is disabled:
 *   index.php?option=com_userreminder&task=optout&uid=<code>
 *
 * @since  4.0.0
 */
class Router extends RouterView
{
    public function __construct(
        SiteApplication $app,
        AbstractMenu $menu,
        ?CategoryFactoryInterface $categoryFactory = null,
        ?DatabaseInterface $db = null
    ) {
        $optout = new RouterViewConfiguration('optout');
        $optout->setKey('uid');

        $this->registerView($optout);

        parent::__construct($app, $menu);

        $this->attachRule(new MenuRules($this));
        $this->attachRule(new StandardRules($this));
        $this->attachRule(new NomenuRules($this));
    }

    /**
     * Build a SEF URL from a query array.
     *
     * @param   array  &$query
     *
     * @return  array
     *
     * @since   4.0.0
     */
    public function build(&$query): array
    {
        if (!isset($query['view']) || $query['view'] !== 'optout') {
            return parent::build($query);
        }

        $segments = ['optout'];

        if (!empty($query['uid'])) {
            $segments[] = $query['uid'];
            unset($query['uid']);
        }

        if (!empty($query['layout']) && $query['layout'] === 'confirm') {
            $segments[] = 'confirm';
            unset($query['layout']);
        }

        unset($query['view']);

        return $segments;
    }

    /**
     * Parse a SEF URL back into a query array.
     *
     * @param   array  &$segments
     *
     * @return  array
     *
     * @since   4.0.0
     */
    public function parse(&$segments): array
    {
        if (empty($segments) || $segments[0] !== 'optout') {
            return [];
        }

        $vars = ['view' => 'optout'];

        if (isset($segments[1]) && $segments[1] !== 'confirm') {
            $vars['uid'] = $segments[1];
        }

        if (isset($segments[2]) && $segments[2] === 'confirm') {
            $vars['layout'] = 'confirm';
        } elseif (isset($segments[1]) && $segments[1] === 'confirm') {
            $vars['layout'] = 'confirm';
        }

        return $vars;
    }
}