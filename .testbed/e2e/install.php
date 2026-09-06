<?php
/**
 * E2E instrumented installer — same as the portability skill's script but
 * parameterized: php install.php /path/to/package.zip
 */

require __DIR__ . '/_boot.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Installer\InstallerHelper;
use Joomla\CMS\Log\Log;

$app = Factory::getApplication();
$pkg = $argv[1] ?? '';

if ($pkg === '' || !is_file($pkg)) {
    fwrite(STDERR, "usage: install.php /path/to/package.zip\n");
    exit(2);
}

$logged = [];
Log::addLogger(
    [
        'logger'   => 'callback',
        'callback' => static function ($entry) use (&$logged) {
            $logged[] = trim(($entry->category ?? '') . ': ' . strip_tags((string) $entry->message));
        },
    ],
    Log::ALL
);

echo str_repeat('=', 76), "\n";
echo 'INSTALL  ', basename($pkg), "\n";
echo str_repeat('=', 76), "\n";

@mkdir('/tmp/pkgwork', 0777, true);
$work = '/tmp/pkgwork/' . basename($pkg);

if (!copy($pkg, $work)) {
    echo "RESULT: FAIL - could not copy package to a writable location\n";
    exit(1);
}

$tmp = InstallerHelper::unpack($work, true);

if (!is_array($tmp) || empty($tmp['dir'])) {
    echo "RESULT: FAIL - could not unpack the archive\n";
    exit(1);
}

echo 'unpacked to : ', $tmp['dir'], "\n";
echo 'detected as : ', ($tmp['type'] ?: '(undetected)'), "\n\n";

$installer = new Installer();
$installer->setDatabase(Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class));

$ok     = false;
$thrown = null;

try {
    $ok = $installer->install($tmp['dir']);
} catch (\Throwable $e) {
    $thrown = $e;
}

$printed = 0;

foreach ($app->getMessageQueue() as $m) {
    printf("[%s] %s\n", strtoupper($m['type']), strip_tags($m['message']));
    $printed++;
}

try {
    $rp = new ReflectionProperty($app, 'messages');
    $rp->setAccessible(true);

    foreach ((array) $rp->getValue($app) as $type => $msgs) {
        foreach ((array) $msgs as $msg) {
            printf("[%s] %s\n", strtoupper((string) $type), strip_tags($msg));
            $printed++;
        }
    }
} catch (\Throwable $e) {
    echo '(could not read console messages: ', $e->getMessage(), ")\n";
}

foreach ($logged as $line) {
    echo '[LOG] ', $line, "\n";
    $printed++;
}

if ($printed === 0) {
    echo "(no messages recorded)\n";
}

if ($thrown !== null) {
    echo "\nEXCEPTION ", get_class($thrown), "\n";
    echo '  ', $thrown->getMessage(), "\n";

    for ($p = $thrown->getPrevious(); $p !== null; $p = $p->getPrevious()) {
        echo '  previous: ', get_class($p), ': ', $p->getMessage(), "\n";
    }

    echo '  at ', $thrown->getFile(), ':', $thrown->getLine(), "\n";
}

InstallerHelper::cleanupInstall($work, $tmp['dir']);

echo "\nRESULT: ", ($ok ? 'PASS' : 'FAIL'), "\n";
exit($ok ? 0 : 1);
