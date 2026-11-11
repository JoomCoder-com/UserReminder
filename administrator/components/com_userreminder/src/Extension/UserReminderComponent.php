<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_userreminder
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomCoder\Component\UserReminder\Administrator\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\Router\RouterServiceInterface;
use Joomla\CMS\Component\Router\RouterServiceTrait;
use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\Factory;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\Database\DatabaseInterface;
use JoomCoder\Component\UserReminder\Administrator\Service\SendService;
use Psr\Container\ContainerInterface;

/**
 * Component class for com_userreminder (shared by the administrator and site
 * halves of the monolithic package — never a separate site-copy class).
 *
 * Loads component languages on boot, exposes the site router service and
 * hands out the send engine through the component itself, which keeps one
 * construction site per request instead of a leaked singleton.
 *
 * @since  4.0.0
 */
class UserReminderComponent extends MVCComponent implements
    ComponentInterface,
    BootableExtensionInterface,
    RouterServiceInterface
{
    use RouterServiceTrait;

    /**
     * Lazily created send engine for this request.
     *
     * @var  SendService|null
     *
     * @since  6.1.0
     */
    private ?SendService $sendService = null;

    /**
     * Loads the admin + site language files right after the component boots so
     * menus, toolbars and views don't have to remember to do it themselves.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public function boot(ContainerInterface $container): void
    {
        $app = Factory::getApplication();

        $lang = $app->getLanguage();
        $lang->load('com_userreminder', JPATH_ADMINISTRATOR . '/components/com_userreminder');
        $lang->load('com_userreminder', JPATH_SITE . '/components/com_userreminder');
    }

    /**
     * The send engine, wired to the running application's database and mailer
     * factory once per request.
     *
     * @return  SendService
     *
     * @since   6.1.0
     */
    public function getSendService(): SendService
    {
        if ($this->sendService === null) {
            $container = Factory::getContainer();

            $this->sendService = new SendService(
                $container->get(DatabaseInterface::class),
                $container->get(MailerFactoryInterface::class)
            );
        }

        return $this->sendService;
    }

    /**
     * Resolve the send engine from any execution context (admin, site,
     * console/task scheduler). Falls back to direct construction when the
     * application cannot boot the component (defensive, unit-test style).
     *
     * The old SendService::instance() global-singleton lookup is gone: it
     * registered in a child container the global lookup could never see and
     * silently "worked" via its fallback instead.
     *
     * @return  SendService
     *
     * @since   6.1.0
     */
    public static function service(): SendService
    {
        try {
            $component = Factory::getApplication()->bootComponent('com_userreminder');

            if ($component instanceof self) {
                return $component->getSendService();
            }
        } catch (\Throwable) {
        }

        $container = Factory::getContainer();

        return new SendService(
            $container->get(DatabaseInterface::class),
            $container->get(MailerFactoryInterface::class)
        );
    }
}
