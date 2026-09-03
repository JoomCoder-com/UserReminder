<?php
/**
 * @version		UserReminder v1.0
 * @package		userreminder
 * @copyright	Copyright � 2021 - JoomCoder - All rights reserved.
 * @license - http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 * @author		JoomCoder
 * @author mail	support@joomcoder.com
 * @website		www.joomcoder.com
 */
// no direct access
defined('_JEXEC') or die('Restricted access');
function userreminderBuildRoute(&$query){
       $segments = array();
       if(isset($query['task']))
       {
                $segments[] = $query['task'];
                unset( $query['task'] );
       }
       if(isset($query['uid']))
       {
                $segments[] = $query['uid'];
                unset( $query['uid'] );
       };
       return $segments;
}
function userreminderParseRoute($segments){
      $vars = array();
       switch($segments[0])
       {
               case 'optout':
                	$vars['task']	= 'optout';
                	$vars['uid'] = $segments[1];
                  break;
       }
       return $vars;
}