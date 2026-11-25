<?php
/**
 * UserReminder E2E orchestrator (host side).
 *
 * Usage:  php run.php <scenario> <stack>
 *   scenario: fresh | upgrade | upgrade6 | all
 *   stack:    j4 | j5 | j6 | j4m | j5m | j6y | all
 *
 * The package under test is releases/pkg_userreminder_v<package.json version>.zip.
 *
 * fresh    — clean Joomla, install the package, seed, run the scheduler task,
 *            assert emails + detection.
 * upgrade  — clean Joomla, install com_userreminder_v5.2.3_j4.zip, seed
 *            old-schema data, update to the package, run, assert migration +
 *            emails + detection.
 * upgrade6 — clean Joomla, install the released pkg 6.0.0, seed, update to the
 *            package, run, assert the same as fresh plus the schema version.
 */

define('COMPOSE', 'E:/laragon/www/usereminder/.testbed/docker-compose.yml');
define('VERSION', json_decode((string) file_get_contents(__DIR__ . '/../../package.json'), true)['version']);
define('PKG', '/pkg/pkg_userreminder_v' . VERSION . '.zip');

if (!is_file(__DIR__ . '/../../releases/pkg_userreminder_v' . VERSION . '.zip')) {
    fwrite(STDERR, 'releases/pkg_userreminder_v' . VERSION . ".zip is missing: run npm run build first\n");
    exit(2);
}

$stacks = [
    'j4' => ['service' => 'joomla-j4', 'port' => 8811],
    'j5' => ['service' => 'joomla-j5', 'port' => 8812],
    'j6' => ['service' => 'joomla-j6', 'port' => 8813],
    'j4m' => ['service' => 'joomla-j4m', 'port' => 8814], // Joomla 4.4 + MariaDB
    'j5m' => ['service' => 'joomla-j5m', 'port' => 8815], // Joomla 5 + MariaDB
    'j6y' => ['service' => 'joomla-j6y', 'port' => 8816], // Joomla 6.2 + MySQL 8.4
];

$scenario = $argv[1] ?? 'all';
$stackArg = $argv[2] ?? 'all';

if (!in_array($scenario, ['fresh', 'upgrade', 'upgrade6', 'all'], true) || ($stackArg !== 'all' && !in_array($stackArg, array_keys($stacks), true))) {
    fwrite(STDERR, "usage: php run.php <fresh|upgrade|upgrade6|all> <j4|j5|j6|j4m|j5m|j6y|all>\n");
    exit(2);
}

$runStacks = $stackArg === 'all' ? array_keys($stacks) : [$stackArg];
$runScenarios = $scenario === 'all' ? ['fresh', 'upgrade', 'upgrade6'] : [$scenario];

echo 'Testing ' . PKG . "\n";

// Ensure the SMTP catcher is running. Scenario steps recreate only the
// joomla/db services, so a stack brought down with `docker compose down`
// would otherwise run the whole matrix with no Mailpit to receive mail.
echo "Ensuring Mailpit catcher is up\n";
shell('docker compose -p urtest -f ' . COMPOSE . ' up -d mailpit');

$results = [];

foreach ($runScenarios as $sc) {
    foreach ($runStacks as $st) {
        $results[] = runScenario($sc, $st);
    }
}

echo "\n", str_repeat('=', 78), "\nSUMMARY\n", str_repeat('=', 78), "\n";

$allOk = true;

foreach ($results as $r) {
    echo sprintf("%-10s %-4s %s\n", $r['scenario'], $r['stack'], $r['ok'] ? 'PASS' : 'FAIL');
    $allOk = $allOk && $r['ok'];
}

exit($allOk ? 0 : 1);

// ---------------------------------------------------------------------------

function runScenario(string $scenario, string $stack): array
{
    global $stacks;

    $port = $stacks[$stack]['port'];
    $svc  = $stacks[$stack]['service'];

    echo str_repeat('=', 78), "\n";
    echo "SCENARIO $scenario / $stack (http://localhost:$port)\n";
    echo str_repeat('=', 78), "\n";

    $GLOBALS['steps'] = [];

    // 1. Reset the stack (fresh Joomla + empty DB).
    step('Recreating stack (fresh Joomla + empty DB)', function () use ($svc) {
        out(shell('docker compose -p urtest -f ' . COMPOSE . ' up -d --force-recreate --renew-anon-volumes ' . $svc . ' ' . str_replace('joomla-', 'db-', $svc)));
    });

    // 2. Wait until Joomla is installed.
    step('Waiting for Joomla unattended install', fn() => waitInstalled($port));

    // 3. Mailer -> Mailpit.
    // Links in the emails must point at the real site. Two ways a command-line run learns it:
    // live_site, or the address an admin page view remembers (SiteUrl::remember()). upgrade6 and
    // the Joomla 4 stacks use the remembered address: Joomla 4.4's own scheduler:run fails with
    // JLIB_APPLICATION_ERROR_MODEL_CREATE when live_site is set (a core issue, before any task runs).
    $siteRoot   = "http://localhost:$port";
    $remembered = $scenario === 'upgrade6' || str_starts_with($stack, 'j4');

    step('Configuring Mailpit mailer and site address', function () use ($stack, $remembered, $siteRoot) {
        out(execContainer($stack, 'php /e2e/setup.php' . ($remembered ? '' : ' ' . $siteRoot)));
    });

    if ($scenario === 'fresh') {
        step('Installing ' . PKG, fn() => installPackage($stack, PKG));

        step('Seeding users', function () use ($stack) {
            out(execContainer($stack, 'php /e2e/seed.php post'));
        });
    } elseif ($scenario === 'upgrade6') {
        step('Installing the released 6.0.0', fn() => installPackage($stack, '/pkg/pkg_userreminder_v6.0.0.zip'));

        step('Seeding users on 6.0.0', function () use ($stack) {
            out(execContainer($stack, 'php /e2e/seed.php post'));
        });



        step('Updating to ' . VERSION . ' on top', fn() => installPackage($stack, PKG));
    } else {
        step('Installing 5.2.3 (old release)', function () use ($stack) {
            $out = execContainer($stack, 'php /e2e/install.php /pkg/com_userreminder_v5.2.3_j4.zip');
            out($out);

            if (!str_contains($out, 'RESULT: PASS')) {
                throw new Exception('old install failed');
            }
        });

        step('Seeding old-schema data', function () use ($stack) {
            out(execContainer($stack, 'php /e2e/seed.php pre'));
        });

        step('Updating to ' . VERSION . ' on top', fn() => installPackage($stack, PKG));

        step('Seeding remaining users / forcing task due', function () use ($stack) {
            out(execContainer($stack, 'php /e2e/seed.php post'));
        });
    }

    if ($remembered) {
        step('Remembering the site address (as an admin page view does)', function () use ($stack, $siteRoot) {
            out(execContainer($stack, 'UR_SITE_ROOT=' . $siteRoot . ' php /e2e/seed.php remember'));
        });
    }

    // 4. Run the scheduled task.
    step('Running scheduler:run --all', function () use ($stack) {
        $out = execContainer($stack, 'php cli/joomla.php scheduler:run --all');
        out($out);

        if (stripos($out, 'error') !== false || stripos($out, 'exception') !== false) {
            throw new Exception('scheduler run reported errors');
        }
    });

        step('Assertions', function () use ($stack, $scenario, $siteRoot) {
            $out = execContainer($stack, 'UR_VERSION=' . VERSION . ' UR_SITE_ROOT=' . $siteRoot . ' php /e2e/assert.php ' . $scenario);
            out($out);

            if (!str_contains($out, 'RESULT: PASS')) {
                throw new Exception('assertions failed');
            }
        });

        $ok = runSteps();

        return ['scenario' => $scenario, 'stack' => $stack, 'ok' => $ok];
    }

function installPackage(string $stack, string $zip): void
{
    $out = execContainer($stack, 'php /e2e/install.php ' . $zip);
    out($out);

    if (!str_contains($out, 'RESULT: PASS')) {
        throw new Exception('install of ' . $zip . ' failed');
    }
}

function step(string $label, callable $fn): void
{
    $GLOBALS['steps'][] = ['label' => $label, 'fn' => $fn];
}

function runSteps(): bool
{
    foreach ($GLOBALS['steps'] as $s) {
        echo "\n--- {$s['label']}\n";

        try {
            ($s['fn'])();
        } catch (Throwable $e) {
            echo "  STEP FAILED: {$e->getMessage()}\n";

            return false;
        }
    }

    return true;
}

function out(string $text): void
{
    echo rtrim($text), "\n";
}

function execContainer(string $stack, string $cmd): string
{
    global $stacks;

    $container = 'urtest-' . $stacks[$stack]['service'] . '-1';

    return (string) shell("docker exec $container sh -c " . escapeshellarg('cd /var/www/html && ' . $cmd));
}

function shell(string $cmd): string
{
    $out = shell_exec($cmd . ' 2>&1');

    return (string) $out;
}

function waitInstalled(int $port, int $timeoutSec = 300): void
{
    $start = time();
    $base = "http://localhost:$port";

    while (time() - $start < $timeoutSec) {
        $code = httpCode("$base/installation/index.php");
        $admin = @file_get_contents("$base/administrator/index.php", false, streamCtx());

        if ($code !== 200 && $admin !== false && str_contains($admin, 'Log in')) {
            echo "  Joomla installed and responding.\n";

            return;
        }

        sleep(3);
    }

    throw new Exception("Joomla did not finish installing on port $port within {$timeoutSec}s");
}

function httpCode(string $url): int
{
    $h = @get_headers($url, false, streamCtx());

    if ($h === false) {
        return 0;
    }

    return (int) (explode(' ', $h[0])[1] ?? 0);
}

function streamCtx()
{
    return stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 10]]);
}
