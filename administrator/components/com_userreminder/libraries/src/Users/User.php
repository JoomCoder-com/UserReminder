<?php
/**
 * UserReminder by joomcoder
 * a component for Joomla! CMS (http://www.joomla.org)
 * Author Website: https://www.joomcoder.com/
 * @copyright Copyright (C) 2012 joomcoder (https://www.joomcoder.com). All rights reserved.
 * @license   GNU/GPL http://www.gnu.org/copyleft/gpl.html
 */

namespace Userreminder\Users;


use JFactory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

defined('_JEXEC') or die();

Class User{


	public static function getActivationUrl($user){

		$db = Factory::getDBO();
		$app = Factory::getApplication();

		$linkMode = $app->get('force_ssl', 0) == 2 ? Route::TLS_FORCE : Route::TLS_IGNORE;

		// cb activation link
		if (ComponentHelper::getParams('com_userreminder')->get('useCBActivation', 0))
		{
			// Find the activation code for community builder
			$q = "Select cbactivation FROM #__comprofiler where  #__comprofiler.user_id =" . $user->id;
			$db->setQuery($q);
			$db->execute();

			$cbuser = $db->loadObject();

			return \Joomla\CMS\Uri\Uri::root() . ComponentHelper::getParams('com_userreminder')->get('activateURL', '') . $cbuser->cbactivation;
		}

		// joomla activation link

		$activationURL = ComponentHelper::getParams('com_userreminder')->get('activateURL', 'index.php?option=com_users&task=registration.activate&token=');

		return Route::link(
			'site',
			$activationURL . $user->activation,
			false,
			$linkMode,
			true
		);

	}




}