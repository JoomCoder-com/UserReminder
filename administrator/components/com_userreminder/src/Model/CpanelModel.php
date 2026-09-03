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

use Joomla\CMS\MVC\Model\BaseModel;

/**
 * Cpanel / dashboard model — no DB access, just exposes whether the system
 * plugin is enabled so the view can render the warning alert.
 *
 * @since  4.0.0
 */
class CpanelModel extends BaseModel
{
    public function getForm($data = [], $loadData = true, $formName = null): ?\Joomla\CMS\Form\Form
    {
        return null;
    }
}