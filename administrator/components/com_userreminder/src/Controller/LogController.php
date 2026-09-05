<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_userreminder
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomCoder\Component\UserReminder\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

/**
 * Log controller — only one custom task: clear the log.
 *
 * @since  4.0.0
 */
class LogController extends BaseController
{
    use AclTrait;

    public function clear(): void
    {
        $this->checkToken();
        $this->requireAuthorised('core.delete');

        $app = Factory::getApplication();

        // With rows checked, only the selected entries are removed; otherwise the whole log.
        $cid = (array) $app->getInput()->get('cid', [], 'array');

        /** @var \JoomCoder\Component\UserReminder\Administrator\Model\LogModel $model */
        $model = $this->getModel('Log');

        if ($cid !== []) {
            $deleted = $model->remove($cid);
            $app->enqueueMessage(Text::sprintf('COM_USERREMINDER_LOG_REMOVED_N', $deleted), 'success');
        } else {
            $model->clear();
            $app->enqueueMessage(Text::_('COM_USERREMINDER_LOG_CLEARED'), 'success');
        }

        $this->setRedirect(Route::_('index.php?option=com_userreminder&view=log', false));
    }

    /**
     * Prune log rows older than 365 days (12-month retention).
     * Called from dashboard toolbar and log view.
     *
     * @return  void
     *
     * @since   4.1.0
     */
    public function pruneOld(): void
    {
        $this->checkToken();
        $this->requireAuthorised('core.delete');

        /** @var \JoomCoder\Component\UserReminder\Administrator\Model\LogModel $model */
        $model   = $this->getModel('Log');
        $deleted = $model->pruneOld(365);

        if ($deleted > 0) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('COM_USERREMINDER_LOG_PRUNED', $deleted),
                'success'
            );
        } else {
            Factory::getApplication()->enqueueMessage(Text::_('COM_USERREMINDER_LOG_PRUNED_NONE'), 'info');
        }

        // Return to where the user came from — prefer cpanel if that was the view.
        // The return URL is attacker-influenced input: only accept relative
        // index.php URLs of this component, never absolute or protocol URLs.
        $return = $this->input->get('return', '', 'base64');

        if ($return !== '' && preg_match('#^index\.php\?option=com_userreminder&view=(log|cpanel)$#i', base64_decode($return) ?: '')) {
            $url = base64_decode($return);
        } else {
            $referer = $this->input->get('view', 'cpanel', 'cmd');
            $url     = $referer === 'log'
                ? 'index.php?option=com_userreminder&view=log'
                : 'index.php?option=com_userreminder&view=cpanel';
        }

        $this->setRedirect(Route::_($url, false));
    }
}
