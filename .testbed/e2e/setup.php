<?php
/**
 * E2E setup — point Joomla's mailer at Mailpit so every sent reminder is caught.
 */

require __DIR__ . '/_boot.php';

$file = '/var/www/html/configuration.php';
$src  = file_get_contents($file);

$src = preg_replace("/public \\\$mailer\s*=\s*'[^']*';/", "public \$mailer = 'smtp';", $src);
$src = preg_replace("/public \\\$mailfrom\s*=\s*'[^']*';/", "public \$mailfrom = 'noreply@joomcoder.test';", $src);
$src = preg_replace("/public \\\$fromname\s*=\s*'[^']*';/", "public \$fromname = 'UserReminder E2E';", $src);
$src = preg_replace("/public \\\$smtphost\s*=\s*'[^']*';/", "public \$smtphost = 'mailpit';", $src);
$src = preg_replace("/public \\\$smtpport\s*=\s*[^;]+;/", "public \$smtpport = 1025;", $src);
$src = preg_replace("/public \\\$smtpsecure\s*=\s*'[^']*';/", "public \$smtpsecure = 'none';", $src);
$src = preg_replace("/public \\\$smtpauth\s*=\s*[^;]+;/", "public \$smtpauth = false;", $src);
$src = preg_replace("/public \\\$debug\s*=\s*[^;]+;/", "public \$debug = false;", $src);

// The site address for links built on the command line: live_site when given,
// otherwise empty (the run then uses the address remembered from the administrator).
$live = $argv[1] ?? '';
$src  = preg_replace("/public \\\$live_site\s*=\s*'[^']*';/", "public \$live_site = '" . addslashes($live) . "';", $src);

file_put_contents($file, $src);

foreach (['mailer', 'smtphost', 'smtpport', 'smtpsecure', 'smtpauth', 'mailfrom', 'live_site'] as $k) {
    preg_match("/public \\\$$k\s*=\s*([^;]+);/", $src, $m);
    echo "  $k = " . trim($m[1] ?? '???') . "\n";
}

// Browser suite: Joomla's guided tour starts by itself on the first admin login and takes over
// the page; the statistics prompt adds a table above every list. Neither belongs to the product.
q("UPDATE {$P}extensions SET enabled = 0 WHERE type = 'plugin' AND folder = 'system' AND element IN ('guidedtours', 'stats')");

echo "SETUP OK (tag=$TAG)\n";
