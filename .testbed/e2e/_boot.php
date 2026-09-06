<?php
/**
 * Shared CLI bootstrap for the UserReminder E2E container scripts.
 * Boots exactly like cli/joomla.php does (root-based JPATH_BASE), so it works
 * on Joomla 4, 5 and 6.
 */

if (!defined('_JEXEC')) {
    define('_JEXEC', 1);
    define('JPATH_BASE', '/var/www/html');
    require_once JPATH_BASE . '/includes/defines.php';
    require_once JPATH_BASE . '/includes/framework.php';

    $container = \Joomla\CMS\Factory::getContainer();
    $container->alias('session', 'session.cli')
        ->alias('JSession', 'session.cli')
        ->alias(\Joomla\CMS\Session\Session::class, 'session.cli')
        ->alias(\Joomla\Session\Session::class, 'session.cli')
        ->alias(\Joomla\Session\SessionInterface::class, 'session.cli');

    \Joomla\CMS\Factory::$application = $container->get(\Joomla\Console\Application::class);

    if (!class_exists('JNamespacePsr4Map')) {
        require_once JPATH_LIBRARIES . '/namespacemap.php';
    }

    $mapFile = JPATH_ADMINISTRATOR . '/cache/autoload_psr4.php';

    if (is_file($mapFile)) {
        foreach ((array) (include $mapFile) as $ns => $paths) {
            \JLoader::registerNamespace(rtrim($ns, '\\'), $paths[0], false, false, 'psr4');
        }
    }
}

$db = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
$P  = $db->getPrefix();            // e.g. j4_
$TAG = rtrim($P, '_');             // e.g. j4 — unique per stack, used in emails

function q(string $sql): bool
{
    global $db;
    $db->setQuery($sql);

    return (bool) $db->execute();
}

function rows(string $sql): array
{
    global $db;
    $db->setQuery($sql);

    return (array) ($db->loadObjectList() ?: []);
}

function val(string $sql)
{
    global $db;
    $db->setQuery($sql);

    return $db->loadResult();
}

function t(string $table): string
{
    global $P;

    return $P . $table;
}
