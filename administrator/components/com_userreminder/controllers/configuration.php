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
defined('_JEXEC') or die;

/**
 * Note: this view is intended only to be opened in a popup
 *
 * @package     Joomla.Administrator
 * @subpackage  com_config
 * @since       1.5
 */
class UserreminderControllerConfiguration extends JControllerLegacy
{
	/**
	 * Class Constructor
	 *
	 * @param   array  $config		An optional associative array of configuration settings.
	 * @return  void
	 * @since   1.5
	 */
	public function __construct($config = array())
	{
		parent::__construct($config);

		// Map the apply task to the save method.
		$this->registerTask('apply', 'save');
	}

	/**
	 * Cancel operation
	 *
	 * @return  void
	 *
	 * @since   3.0
	 */
	function cancel()
	{
		// Clean the session data.
		$app = JFactory::getApplication();
		$app->setUserState('com_config.config.global.data', null);

		$return = $this->input->post->get('return', null, 'base64');
		$redirect = 'index.php?option=com_userreminder';
		if (!empty($return))
		{
			$redirect = base64_decode($return);
		}

		$this->setRedirect($redirect);
	}

	/**
	 * Save the configuration
	 */
	public function save()
	{
        // Check for request forgeries
        JRequest::checkToken() or die('Invalid Token');
        $post = JRequest::get('post');

        $registry = new JRegistry();
        $registry->loadArray($post['params']);
        $jsondata = $registry->toString();

        //$postRaw = $post['params'];
        //$count = sizeof($postRaw);
        //$counter = 0;
        //$jsondata = "{";
		
        //foreach (array_keys($postRaw) as $key) {
        //    $counter++;
        //    if ($counter == $count) {
        //        $jsondata .= '"' . $key . '":"' . htmlentities($postRaw[$key]) . '"';
        //    } else {
        //        $jsondata .= '"' . $key . '":"' . htmlentities($postRaw[$key]) . '",';
        //    }
        //}
        //$jsondata .= "}";
        
        //$jsondata = str_replace("\r\n", "<br />", $jsondata);
        $db = JFactory::getDbo();
        $jsondata = $db->escape($jsondata);
        $query = "update #__extensions set params='" . $jsondata . "' WHERE element='com_userreminder'";
        
        $db->setQuery($query);
        if (!$db->query()) {
            //throw new Exception($db->getErrorMsg());
            JError::raiseWarning(500, $db->getErrorMsg());
            echo "<br/> Error...";
            return false;
        }
        
        switch ($this->getTask())
        {
        	case 'apply':
        		$message = 'Configuration successfully saved.';
        		
        		$view = $this->getView ( 'configuration','html');
        		$view->assignRef('message', $message);
        		$view->display();

        		break;
        
        	case 'save':
        	default:
        		 $redirect = 'index.php?option=com_userreminder';
        		if (!empty($returnUri))
        		{
        			$redirect = base64_decode($returnUri);
        		}
        
        		$this->setRedirect($redirect);
        		break;
        }
        
		    // now return to the main page
		//$view = $this->getView ( 'cpanel','html');
        //$view->_display();
//         $redirect = 'index.php?option=com_userreminder';
//         $this->setRedirect($redirect);
    
	}
	
	function help() {
		$view = $this->getView ( 'help','html');
		$view->_display();
	}
}
