<?php
/**
 * E2E seeder.
 *
 * Usage: php seed.php pre|post
 *
 * pre  (upgrade scenario only, BEFORE installing 6.0.0):
 *        seed users + old-schema #__userreminder rows + optout + legacy log
 *        row + customized legacy email params (for the mail-template migration).
 * post (both scenarios, AFTER install/update):
 *        ensure users exist, insert the completed-cycle reminder row (fresh
 *        installs only), force the scheduled task due, wipe Mailpit.
 *
 * Seed users (tag = db prefix, e.g. j4 / j5 / j6):
 *   u-act    not activated (activation token, blocked, never visited, 10d old)  -> reminder_activation
 *   u-never  activated, never logged in (10d old)                               -> reminder_login
 *   u-inact  logged in 400 days ago (inactive window default 180d)              -> reminder_inactive
 *   u-optout never-logged profile, opted out                                    -> nothing
 *   u-young  never-logged profile, registered just now                          -> nothing
 *   u-done   not-activated profile with remindernumber=1 (cycle complete)       -> nothing
 */

require __DIR__ . '/_boot.php';

use Joomla\CMS\User\UserHelper;

$mode = $argv[1] ?? 'post';

if (!in_array($mode, ['pre', 'post'], true)) {
    fwrite(STDERR, "usage: seed.php pre|post\n");
    exit(2);
}

$DOM = 'example.test';

function seedUser(array $u): int
{
    global $db, $P;

    $existing = val("SELECT id FROM {$P}users WHERE username = " . $db->quote($u['username']));

    if ($existing) {
        return (int) $existing;
    }

    $db->setQuery(
        "INSERT INTO {$P}users (name, username, email, password, block, sendEmail, registerDate, lastvisitDate, activation, params, lastResetTime, requireReset, otpKey, otep)
         VALUES ("
         . $db->quote($u['name']) . ', '
         . $db->quote($u['username']) . ', '
         . $db->quote($u['email']) . ', '
         . $db->quote(password_hash('e2epassword', PASSWORD_BCRYPT)) . ', '
         . (int) $u['block'] . ', 0, '
         . $db->quote($u['registerDate']) . ', '
         . (isset($u['lastvisitDate']) && $u['lastvisitDate'] !== null ? $db->quote($u['lastvisitDate']) : 'NULL') . ', '
         . $db->quote($u['activation']) . ", '{}', " . $db->quote($u['registerDate']) . ', 0, \'\', \'\')'
    );
    $db->execute();

    $id = (int) $db->insertid();

    $db->setQuery("INSERT INTO {$P}user_usergroup_map (user_id, group_id) VALUES ($id, 2)");
    $db->execute();

    return $id;
}

$users = [
    'u-act' => [
        'name' => "Act User $TAG", 'username' => "u-act-$TAG", 'email' => "u-act@$TAG.$DOM",
        'block' => 1, 'activation' => "activ-token-$TAG-123456", 'registerDate' => gmdate('Y-m-d H:i:s', strtotime('-10 days')),
    ],
    'u-never' => [
        'name' => "Never User $TAG", 'username' => "u-never-$TAG", 'email' => "u-never@$TAG.$DOM",
        'block' => 0, 'activation' => '', 'registerDate' => gmdate('Y-m-d H:i:s', strtotime('-10 days')),
    ],
    'u-inact' => [
        'name' => "Inactive User $TAG", 'username' => "u-inact-$TAG", 'email' => "u-inact@$TAG.$DOM",
        'block' => 0, 'activation' => '', 'registerDate' => gmdate('Y-m-d H:i:s', strtotime('-500 days')),
        'lastvisitDate' => gmdate('Y-m-d H:i:s', strtotime('-400 days')),
    ],
    'u-optout' => [
        'name' => "Optout User $TAG", 'username' => "u-optout-$TAG", 'email' => "u-optout@$TAG.$DOM",
        'block' => 0, 'activation' => '', 'registerDate' => gmdate('Y-m-d H:i:s', strtotime('-10 days')),
    ],
    'u-young' => [
        'name' => "Young User $TAG", 'username' => "u-young-$TAG", 'email' => "u-young@$TAG.$DOM",
        'block' => 0, 'activation' => '', 'registerDate' => gmdate('Y-m-d H:i:s'),
    ],
    'u-done' => [
        'name' => "Done User $TAG", 'username' => "u-done-$TAG", 'email' => "u-done@$TAG.$DOM",
        'block' => 1, 'activation' => "done-token-$TAG-654321", 'registerDate' => gmdate('Y-m-d H:i:s', strtotime('-40 days')),
    ],
];

if ($mode === 'pre') {
    // ---- old-schema reminder rows + optout + legacy artifacts ----
    $ids = [];

    foreach ($users as $k => $u) {
        $ids[$k] = seedUser($u);
    }

    // Completed activation-reminder cycle for u-done, written the 5.2.x way.
    $doneDatesent = gmdate('Y-m-d H:i:s', strtotime('-30 days'));
    q("INSERT INTO {$P}userreminder (userid, datesent, remindernumber, type, optoutcode)
       VALUES ({$ids['u-done']}, '$doneDatesent', 1, 1, 'legacy-code-{$TAG}')
       ON DUPLICATE KEY UPDATE remindernumber = 1");

    // Opt-out for u-optout.
    q("INSERT IGNORE INTO {$P}userreminder_optout (user_id) VALUES ({$ids['u-optout']})");

    // A legacy log row still carrying the untranslated language key.
    q("INSERT INTO {$P}userreminder_log (userId, username, description, date)
       VALUES ({$ids['u-done']}, 'u-done-$TAG', 'COM_USERREMINDER_LOG_PREFIX USERREMINDER_LOGIN_REMINDER_SENT', '$doneDatesent')");
    $legacyLogId = (int) val("SELECT MAX(id) FROM {$P}userreminder_log");

    file_put_contents('/tmp/e2e-expected.json', json_encode([
        'tag'          => $TAG,
        'domain'       => $DOM,
        'ids'          => $ids,
        'legacyLogId'  => $legacyLogId,
        'doneDatesent' => $doneDatesent,
    ]));

    echo "SEED PRE OK (tag=$TAG, users=" . count($ids) . ")\n";
    exit(0);
}

// ------------------------------------------------------------------ post mode

// Merge with any pre-update expectations (upgrade scenario): keep legacyLogId
// and the doneDatesent captured before the update ran.
$expected = [];

if (is_file('/tmp/e2e-expected.json')) {
    $expected = json_decode((string) file_get_contents('/tmp/e2e-expected.json'), true) ?: [];
}

$ids = [];

foreach ($users as $k => $u) {
    $ids[$k] = seedUser($u);
}

// Fresh installs: seed the completed-cycle row (old-schema sites already have it).
$doneDatesent = $expected['doneDatesent'] ?? gmdate('Y-m-d H:i:s', strtotime('-30 days'));
if (!val("SELECT COUNT(*) FROM {$P}userreminder WHERE userid = {$ids['u-done']}")) {
    q("INSERT INTO {$P}userreminder (userid, datesent, remindernumber, type, optoutcode)
       VALUES ({$ids['u-done']}, '$doneDatesent', 1, 1, 'legacy-code-{$TAG}')");
}

// Opt-out for u-optout (idempotent — the upgrade scenario seeded it pre-update).
q("INSERT IGNORE INTO {$P}userreminder_optout (user_id) VALUES ({$ids['u-optout']})");

file_put_contents('/tmp/e2e-expected.json', json_encode(array_merge($expected, [
    'tag'          => $TAG,
    'domain'       => $DOM,
    'ids'          => $ids,
    'doneDatesent' => $doneDatesent,
])));

// Make sure the scheduled task is due and enabled.
q("UPDATE {$P}scheduler_tasks
   SET next_execution = '" . gmdate('Y-m-d H:i:s', strtotime('-1 hour')) . "', state = 1
   WHERE type = 'userreminder.run'");

if (!(int) val("SELECT COUNT(*) FROM {$P}scheduler_tasks WHERE type = 'userreminder.run'")) {
    echo "[FAIL] no userreminder.run scheduled task registered\n";
    exit(1);
}

// Wipe Mailpit so assertions are per-run exact.
$ctx = stream_context_create(['http' => ['method' => 'DELETE', 'header' => "Content-Type: application/json\r\n", 'content' => '{"ids":"all"}', 'ignore_errors' => true]]);
@file_get_contents('http://mailpit:8025/api/v1/messages', false, $ctx);

echo "SEED POST OK (tag=$TAG, users=" . count($ids) . ", task due, mailpit wiped)\n";
