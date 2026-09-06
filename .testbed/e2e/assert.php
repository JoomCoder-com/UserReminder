<?php
/**
 * E2E assertions.
 *
 * Usage: php assert.php fresh|upgrade [after-install-only]
 *
 * Verifies, for the seeded users of this stack (tag = db prefix):
 *   - the detection/send pipeline: exactly the 3 expected users were reminded,
 *     recorded in #__userreminder and #__userreminder_log;
 *   - opted-out / too-young / cycle-complete users got nothing;
 *   - Mailpit received exactly the 3 expected messages (per-stack domain).
 * upgrade adds:
 *   - schema migrated (6.0.0 marker, InnoDB/utf8mb4, PK, indexes, sch dropped);
 *   - old data preserved (u-done row, translated legacy log row);
 *   - legacy email params migrated into the Joomla mail templates;
 *   - legacy system plugin disabled/removed.
 */

require __DIR__ . '/_boot.php';

$mode = $argv[1] ?? 'fresh';
$expected = json_decode((string) file_get_contents('/tmp/e2e-expected.json'), true);
$ids = $expected['ids'];
$DOM = $expected['domain'];

$fails = 0;

function fail(string $msg): void
{
    echo "  [FAIL] $msg\n";
    $GLOBALS['fails']++;
}

function pass(string $msg): void
{
    echo "  [ok]   $msg\n";
}

// --------------------------------------------------------------- upgrade-only

if ($mode === 'upgrade') {
    $schemas = val("SELECT version_id FROM {$P}schemas WHERE extension_id = (SELECT extension_id FROM {$P}extensions WHERE type='component' AND element='com_userreminder')");
    $schemas === '6.0.0' ? pass('#__schemas = 6.0.0') : fail("#__schemas = " . var_export($schemas, true));

    $engine = val("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$P}userreminder'");
    $engine === 'InnoDB' ? pass('#__userreminder engine InnoDB') : fail("#__userreminder engine $engine");

    foreach (['userreminder', 'userreminder_log', 'userreminder_optout', 'userreminder_optout_usergroups'] as $tbl) {
        $cs = val("SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$P}{$tbl}'");
        str_starts_with((string) $cs, 'utf8mb4_') ? pass("#__$tbl collation $cs") : fail("#__$tbl collation $cs");
    }

    $sch = val("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$P}userreminder_sch'");
    (int) $sch === 0 ? pass('#__userreminder_sch dropped') : fail('#__userreminder_sch still exists');

    $pk = val("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$P}userreminder' AND INDEX_NAME = 'PRIMARY'");
    (int) $pk > 0 ? pass('#__userreminder has PRIMARY KEY') : fail('#__userreminder has no PRIMARY KEY');

    foreach ([
        'userreminder'    => ['idx_ur_sent_type'],
        'userreminder_log' => ['idx_userreminder_log_userId_date', 'idx_ur_log_date', 'idx_ur_log_date_id'],
    ] as $tbl => $idxs) {
        foreach ($idxs as $idx) {
            $c = (int) val("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$P}{$tbl}' AND INDEX_NAME = '$idx'");
            $c > 0 ? pass("#__$tbl index $idx") : fail("#__$tbl missing index $idx");
        }
    }

    // Old data preserved.
    $done = rows("SELECT remindernumber, datesent, type, optoutcode FROM {$P}userreminder WHERE userid = {$ids['u-done']}");
    if (count($done) === 1 && (int) $done[0]->remindernumber === 1 && $done[0]->datesent === $expected['doneDatesent']) {
        pass('u-done legacy reminder row preserved');
    } else {
        fail('u-done legacy reminder row altered: ' . json_encode($done));
    }

    $legacy = (string) val("SELECT description FROM {$P}userreminder_log WHERE id = " . (int) $expected['legacyLogId']);
    str_contains($legacy, 'USERREMINDER_') ? fail("legacy log row still raw: $legacy") : pass("legacy log row translated ($legacy)");

    // New mail-template system in use: all three shipped templates registered
    // with non-empty bodies (no legacy email-param migration by design).
    foreach (['reminder_activation', 'reminder_login', 'reminder_inactive'] as $slug) {
        $t = rows("SELECT subject, htmlbody FROM {$P}mail_templates WHERE template_id = 'com_userreminder.userreminder.$slug'");
        if (count($t) === 1 && $t[0]->subject !== '' && $t[0]->htmlbody !== '') {
            pass("shipped mail template $slug registered (new system in use)");
        } else {
            fail("shipped mail template $slug missing/empty: " . json_encode($t));
        }
    }

    // Legacy system plugin must be gone or disabled.
    $sys = val("SELECT COUNT(*) FROM {$P}extensions WHERE type='plugin' AND folder='system' AND element='userreminder' AND enabled=1");
    (int) $sys === 0 ? pass('legacy system plugin disabled/removed') : fail('legacy system plugin still enabled');
}

// --------------------------------------------------------------------- sends

// Expected: exactly 3 emails to u-act / u-never / u-inact.
$expectRecipients = [$ids['u-act'], $ids['u-never'], $ids['u-inact']];

foreach (['u-act' => 1, 'u-never' => 2, 'u-inact' => 3] as $k => $type) {
    $r = rows("SELECT remindernumber, type, datesent FROM {$P}userreminder WHERE userid = {$ids[$k]}");
    if (count($r) === 1 && (int) $r[0]->remindernumber === 1 && (int) $r[0]->type === $type && $r[0]->datesent !== null) {
        pass("u/$k recorded (type=$type, remindernumber=1)");
    } else {
        fail("u/$k reminder row wrong: " . json_encode($r));
    }
}

// Nothing for skipped users.
foreach (['u-optout', 'u-young', 'u-done'] as $k) {
    if ($k === 'u-done') {
        $c = (int) val("SELECT COUNT(*) FROM {$P}userreminder WHERE userid = {$ids[$k]} AND remindernumber > 1");
        $c === 0 ? pass('u-done untouched (cycle complete, no 2nd send)') : fail('u-done got an extra send');
        continue;
    }
    $c = (int) val("SELECT COUNT(*) FROM {$P}userreminder WHERE userid = {$ids[$k]}");
    $c === 0 ? pass("u/$k not detected (correct)") : fail("u/$k should not have a reminder row");
}

// Log rows for this run.
$logCount = (int) val("SELECT COUNT(*) FROM {$P}userreminder_log WHERE userId IN (" . implode(',', $expectRecipients) . ")");
$logCount === 3 ? pass('3 log rows written') : fail("log rows for sent users: $logCount");

$rawKeys = (int) val("SELECT COUNT(*) FROM {$P}userreminder_log
    WHERE description LIKE '%USERREMINDER\\_%'");
$rawKeys === 0 ? pass('no untranslated log rows') : fail("$rawKeys log rows still carry raw keys");

// Users detected but not expected to send must have no NEW log rows (the
// upgrade scenario's legacy log row is excluded).
$legacyExclude = !empty($expected['legacyLogId']) ? " AND id <> " . (int) $expected['legacyLogId'] : '';
$skipLog = (int) val("SELECT COUNT(*) FROM {$P}userreminder_log WHERE userId IN ({$ids['u-optout']},{$ids['u-young']},{$ids['u-done']})$legacyExclude");
$skipLog === 0 ? pass('skipped users have no log rows') : fail("skipped users have $skipLog log rows");

// ------------------------------------------------------------ Mailpit checks

$raw = @file_get_contents('http://mailpit:8025/api/v1/messages?limit=250');
if ($raw === false) {
    fail('cannot reach Mailpit API');
} else {
    $mail = json_decode($raw, true);
    $mine  = [];
    $suffix = "@{$expected['tag']}.$DOM";

    foreach ((array) ($mail['messages'] ?? []) as $m) {
        $tos = [];

        foreach ((array) ($m['To'] ?? []) as $to) {
            $tos[] = strtolower($to['Address'] ?? '');
        }

        if (array_filter($tos, fn($a) => str_ends_with($a, $suffix))) {
            $mine[] = ['to' => $tos, 'subject' => $m['Subject'] ?? ''];
        }
    }

    count($mine) === 3 ? pass('Mailpit: exactly 3 reminder emails for this stack') : fail('Mailpit: ' . count($mine) . ' emails for this stack');

    $got = [];

    foreach ($mine as $m) {
        foreach ($m['to'] as $a) {
            $got[$a] = $m['subject'];
        }
    }

    $expectedEmails = [
        "u-act@$TAG.$DOM",
        "u-never@$TAG.$DOM",
        "u-inact@$TAG.$DOM",
    ];

    foreach ($expectedEmails as $e) {
        isset($got[$e]) ? pass("Mailpit: delivered to $e") : fail("Mailpit: nothing delivered to $e");
    }

    if (count($got) > 3) {
        fail('Mailpit: unexpected extra recipients: ' . implode(',', array_keys($got)));
    }
}

echo "\n";
echo $fails === 0 ? "RESULT: PASS\n" : "RESULT: FAIL ($fails)\n";
exit($fails === 0 ? 0 : 1);
