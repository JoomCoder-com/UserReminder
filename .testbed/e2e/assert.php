<?php
/**
 * E2E assertions.
 *
 * Usage: UR_VERSION=x.y.z php assert.php fresh|upgrade|upgrade6 [after-install-only]
 *
 * Verifies, for the seeded users of this stack (tag = db prefix):
 *   - the detection/send pipeline: exactly the 3 expected users were reminded,
 *     recorded in #__userreminder and #__userreminder_log;
 *   - opted-out / too-young / cycle-complete users got nothing;
 *   - Mailpit received exactly the 3 expected messages (per-stack domain).
 * upgrade adds:
 *   - schema migrated (#__schemas = UR_VERSION, InnoDB/utf8mb4, PK, indexes, sch dropped);
 *   - old data preserved (u-done row, translated legacy log row);
 *   - legacy email params migrated into the Joomla mail templates;
 *   - legacy system plugin disabled/removed.
 */

require __DIR__ . '/_boot.php';

$mode    = $argv[1] ?? 'fresh';
$version = (string) getenv('UR_VERSION');
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

if ($mode === 'upgrade6') {
    $schemas = val("SELECT version_id FROM {$P}schemas WHERE extension_id IN (
            SELECT extension_id FROM {$P}extensions
            WHERE type='component' AND element='com_userreminder'
        ) ORDER BY version_id DESC LIMIT 1");
    $schemas === $version ? pass("#__schemas = $version after 6.0.0 -> $version") : fail("#__schemas = " . var_export($schemas, true) . ", expected $version");

    $dupes = (int) val("SELECT COUNT(*) FROM {$P}extensions WHERE type='component' AND element='com_userreminder'");
    $dupes === 1 ? pass('exactly one com_userreminder component row') : fail("$dupes com_userreminder component rows (expected 1)");

    val("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$P}userreminder_log' AND COLUMN_NAME = 'message_key'") == 1
        ? pass('log table has message_key')
        : fail('log table is missing message_key');
}

if ($mode === 'upgrade') {
    // Duplicate-proofed: `= (subquery)` aborts with "Subquery returns more
    // than 1 row" when the extension table carries duplicate com_userreminder
    // rows (seen on J6 upgrades). IN + latest row wins keeps the assert alive
    // so the real problem is surfaced by the explicit duplicate check below.
    $schemas = val("SELECT version_id FROM {$P}schemas WHERE extension_id IN (
            SELECT extension_id FROM {$P}extensions
            WHERE type='component' AND element='com_userreminder'
        ) ORDER BY version_id DESC LIMIT 1");
    $schemas === $version ? pass("#__schemas = $version") : fail("#__schemas = " . var_export($schemas, true) . ", expected $version");

    // Inactive-user reminders became opt-in; an upgraded site keeps them on (script.php).
    $cp = json_decode((string) val("SELECT params FROM {$P}extensions WHERE type='component' AND element='com_userreminder' ORDER BY extension_id DESC LIMIT 1"), true) ?: [];
    (int) ($cp['enableInactiveReminder'] ?? 0) === 1
        ? pass('inactive-user reminders kept on after the update')
        : fail('inactive-user reminders switched off by the update: ' . var_export($cp['enableInactiveReminder'] ?? null, true));

    $dupes = (int) val("SELECT COUNT(*) FROM {$P}extensions WHERE type='component' AND element='com_userreminder'");
    $dupes === 1 ? pass('exactly one com_userreminder component row') : fail("$dupes com_userreminder component rows (expected 1)");

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
            $bccs   = array_map(fn($b) => strtolower($b['Address'] ?? ''), (array) ($m['Bcc'] ?? []));
            $mine[] = ['id' => $m['ID'] ?? '', 'to' => $tos, 'subject' => $m['Subject'] ?? '', 'bcc' => $bccs];
        }
    }

    count($mine) === 3 ? pass('Mailpit: exactly 3 reminder emails for this stack') : fail('Mailpit: ' . count($mine) . ' emails for this stack');

    $bccOk = count(array_filter($mine, fn($m) => in_array("bcc@{$expected['tag']}.$DOM", $m['bcc'], true)));
    $bccOk === count($mine) && $bccOk > 0
        ? pass('Mailpit: every reminder has the admin BCC copy')
        : fail("Mailpit: $bccOk of " . count($mine) . ' reminders have the admin BCC copy');

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

    checkMailContent($mine, $mode, $TAG, $DOM, $ids);
}

/**
 * Open every reminder in Mailpit and check what the user actually receives:
 * subject per type, all tags filled in, the user's own name/username, a plain
 * text part, the HTML container (Joomla 5+), and that each link opens on the
 * site. The opt-out link must show a confirmation page, not opt the user out.
 */
function checkMailContent(array $mine, string $mode, string $TAG, string $DOM, array $ids): void
{
    // Subjects and the sentence that identifies each type (the default
    // templates; the 5.2.3 upgrade migrates its own customised texts instead).
    $types = [
        "u-act@$TAG.$DOM"   => ['subject' => 'activate your account', 'link' => 'registration.activate', 'name' => "Act User $TAG", 'user' => "u-act-$TAG"],
        "u-never@$TAG.$DOM" => ['subject' => 'your account is waiting', 'link' => 'reset', 'name' => "Never User $TAG", 'user' => "u-never-$TAG"],
        "u-inact@$TAG.$DOM" => ['subject' => 'we miss you', 'link' => null, 'name' => "Inactive User $TAG", 'user' => "u-inact-$TAG"],
    ];

    $optedOutBefore = (int) val('SELECT COUNT(*) FROM ' . $GLOBALS['P'] . 'userreminder_optout');

    foreach ($mine as $summary) {
        $to   = $summary['to'][0] ?? '';
        $want = $types[$to] ?? null;
        $raw  = @file_get_contents('http://mailpit:8025/api/v1/message/' . rawurlencode($summary['id']));
        $msg  = $raw === false ? null : json_decode($raw, true);

        if (!$want || !$msg) {
            fail("Mailpit: cannot open the message to $to");
            continue;
        }

        $html = (string) ($msg['HTML'] ?? '');
        $text = (string) ($msg['Text'] ?? '');
        $subj = (string) ($msg['Subject'] ?? '');

        if ($mode !== 'upgrade') {
            stripos($subj, $want['subject']) !== false
                ? pass("mail to $to: subject \"$subj\"")
                : fail("mail to $to: subject \"$subj\" (expected it to contain \"{$want['subject']}\")");
        }

        preg_match_all('/\{[A-Z_]{3,}\}/', $subj . $html . $text, $left);
        empty($left[0])
            ? pass("mail to $to: every tag filled in")
            : fail("mail to $to: unreplaced tags " . implode(' ', array_unique($left[0])));

        str_contains($html . $text, $want['name']) || str_contains($html . $text, $want['user'])
            ? pass("mail to $to: addressed to the user by name")
            : fail("mail to $to: the user's name/username is missing");

        trim($text) !== '' ? pass("mail to $to: has a plain-text part") : fail("mail to $to: no plain-text part");

        if (version_compare(JVERSION, '5.0', '>=')) {
            str_contains($html, 'ur-mail-container') || str_contains($html, 'ur-footer')
                ? pass("mail to $to: HTML container (header/footer) applied")
                : fail("mail to $to: HTML container missing");
        }

        // Links: every http link must open on the site.
        preg_match_all('/href="([^"]+)"/i', $html, $m);
        $links = array_values(array_unique(array_filter($m[1], fn($u) => str_starts_with($u, 'http'))));

        $optout = array_values(array_filter($links, fn($u) => str_contains($u, 'com_userreminder') || str_contains($u, 'component/userreminder') || str_contains($u, 'optout')));

        $root = rtrim((string) getenv('UR_SITE_ROOT'), '/') . '/';
        $bad  = array_filter($links, fn($u) => !str_starts_with($u, $root));
        empty($bad)
            ? pass("mail to $to: every link starts with the site address $root")
            : fail("mail to $to: links not on $root: " . implode(' ', $bad));
        $optout ? pass("mail to $to: has an opt-out link") : fail("mail to $to: no opt-out link");

        if ($want['link'] !== null) {
            array_filter($links, fn($u) => str_contains(urldecode($u), $want['link']))
                ? pass("mail to $to: has the {$want['link']} link")
                : fail("mail to $to: no {$want['link']} link in " . implode(' ', $links));
        }

        foreach ($links as $url) {
            // Following the activation link would activate the account: check it resolves, without opening it.
            if (str_contains($url, 'registration.activate')) {
                str_contains($url, 'token=') ? pass("mail to $to: activation link carries a token") : fail("mail to $to: activation link has no token: $url");
                continue;
            }

            [$code, $body] = httpGet($url);

            $code >= 200 && $code < 400
                ? pass("mail to $to: link opens ($code) " . shortUrl($url))
                : fail("mail to $to: link returns $code: $url");

            if (in_array($url, $optout, true)) {
                str_contains($body, '<form') && str_contains($body, 'optout')
                    ? pass("mail to $to: opt-out link shows a confirmation form")
                    : fail("mail to $to: opt-out page has no confirmation form");
            }
        }
    }

    $optedOutAfter = (int) val('SELECT COUNT(*) FROM ' . $GLOBALS['P'] . 'userreminder_optout');
    $optedOutAfter === $optedOutBefore
        ? pass('opening the opt-out links did not opt anyone out (confirmation required)')
        : fail("opening the opt-out links opted out " . ($optedOutAfter - $optedOutBefore) . ' user(s)');
}

/**
 * GET a link from inside the container (the site answers on localhost:80).
 *
 * @return  array{0:int, 1:string}  HTTP status and body.
 */
function httpGet(string $url): array
{
    $url  = preg_replace('#^https?://[^/]+#', 'http://localhost', $url);
    $ctx  = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 20, 'follow_location' => 0]]);
    $body = @file_get_contents($url, false, $ctx);
    $code = 0;

    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $mm)) {
            $code = (int) $mm[1];
        }
    }

    return [$code, (string) $body];
}

function shortUrl(string $url): string
{
    return strlen($url) > 70 ? substr($url, 0, 67) . '...' : $url;
}

echo "\n";
echo $fails === 0 ? "RESULT: PASS\n" : "RESULT: FAIL ($fails)\n";
exit($fails === 0 ? 0 : 1);
