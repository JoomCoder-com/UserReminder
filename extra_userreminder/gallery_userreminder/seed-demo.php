<?php
/**
 * User Reminder - showcase demo data for the gallery and docs screenshots.
 *
 * Wipes every site user except the admin, plus User Reminder's reminder, log and opt-out rows, and
 * writes a realistic community: pending activations, people who never logged in, inactive members,
 * active members, two staff accounts in an opted-out group, a few users who unsubscribed, and 30 days
 * of reminder history. Dates are relative to "now", so a re-run always gives a fresh dashboard.
 *
 * DEV SITE ONLY (http://usereminder.test, DB usereminder_j4). Never run against a live site.
 *
 * Run: php seed-demo.php
 */

declare(strict_types=1);

const DB     = 'usereminder_j4';
const PREFIX = 'm1pdq_';
const ADMIN  = 343;

$db = new mysqli('127.0.0.1', 'root', '', DB);
$db->set_charset('utf8mb4');
mt_srand(20261121);

function q(string $sql): void
{
    global $db;
    $db->query(str_replace('#__', PREFIX, $sql));
}

function ins(string $table, array $row): int
{
    global $db;
    $cols = implode(',', array_map(fn ($c) => "`$c`", array_keys($row)));
    $vals = implode(',', array_map(fn ($v) => $v === null ? 'NULL' : "'" . $db->real_escape_string((string) $v) . "'", $row));
    $db->query('INSERT INTO `' . PREFIX . $table . "` ($cols) VALUES ($vals)");

    return (int) $db->insert_id;
}

/** UTC SQL date, $days ago (fractions allowed), at a plausible hour. */
function ago(float $days, ?int $hour = null): string
{
    $t = time() - (int) round($days * 86400);
    if ($hour !== null) {
        $t = strtotime(gmdate('Y-m-d', $t) . sprintf(' %02d:%02d:%02d', $hour, mt_rand(0, 59), mt_rand(0, 59)) . ' UTC');
    }

    if ($t > time()) {
        $t -= 86400;
    }

    return gmdate('Y-m-d H:i:s', $t);
}

// ---------------------------------------------------------------- wipe
$ids = [];
$r = $db->query('SELECT id FROM ' . PREFIX . 'users WHERE id <> ' . ADMIN);
while ($row = $r->fetch_row()) {
    $ids[] = (int) $row[0];
}
if ($ids) {
    $in = implode(',', $ids);
    q("DELETE FROM #__user_usergroup_map WHERE user_id IN ($in)");
    q("DELETE FROM #__user_profiles WHERE user_id IN ($in)");
    q("DELETE FROM #__users WHERE id IN ($in)");
}
foreach (['userreminder', 'userreminder_log', 'userreminder_optout'] as $t) {
    q("DELETE FROM #__$t");
}
q('ALTER TABLE #__userreminder_log AUTO_INCREMENT = 1');
q('ALTER TABLE #__users AUTO_INCREMENT = ' . (ADMIN + 1));
// Staff (Manager) never get reminders.
q('DELETE FROM #__userreminder_optout_usergroups');
q('INSERT INTO #__userreminder_optout_usergroups (group_id) VALUES (6)');

$hash = password_hash('demo-' . bin2hex(random_bytes(6)), PASSWORD_BCRYPT);
$params = '{"admin_style":"","admin_language":"","language":"","editor":"","timezone":""}';

/**
 * [name, state, registered days ago, last visit days ago|null, reminders [type, number, sent days ago]|null, group]
 * state: pending = not activated, never = activated but never logged in, member = has logged in.
 */
$people = [
    // Pending activation
    ['Lena Hoffmann', 'pending', 1.2, null, null],
    ['Marco Bianchi', 'pending', 2.6, null, [1, 1, 1.5]],
    ['Aisha Rahman', 'pending', 4.1, null, [1, 1, 3.0]],
    ['Tomás Ortega', 'pending', 6.3, null, [1, 2, 1.1]],
    ['Chloé Martin', 'pending', 9.0, null, [1, 2, 4.2]],
    ['Daniel Novak', 'pending', 12.4, null, [1, 2, 7.3]],
    ['Priya Sharma', 'pending', 15.7, null, [1, 2, 10.6]],
    ['Owen Gallagher', 'pending', 3.4, null, null],
    // Activated, never logged in
    ['Sofia Lindqvist', 'never', 5.2, null, [2, 1, 2.1]],
    ['Kwame Mensah', 'never', 8.8, null, [2, 1, 5.4]],
    ['Hannah Weber', 'never', 13.5, null, [2, 2, 3.2]],
    ['Ravi Patel', 'never', 21.0, null, [2, 2, 9.9]],
    ['Emily Clarke', 'never', 2.3, null, null],
    // Inactive members (threshold: 30 days)
    ['James Whitfield', 'member', 610, 34, null],
    ['Yuki Tanaka', 'member', 540, 41, [3, 1, 6.2]],
    ['Isabel Fernández', 'member', 702, 58, [3, 1, 12.8]],
    ['Lukas Becker', 'member', 455, 77, [3, 2, 2.4]],
    ['Grace Okafor', 'member', 880, 96, [3, 2, 8.1]],
    ['Matteo Rossi', 'member', 390, 133, [3, 2, 15.5]],
    ['Nora Jansen', 'member', 1010, 187, [3, 2, 21.7]],
    ['Ahmed Karimi', 'member', 760, 244, null],
    ['Olivia Brooks', 'member', 1200, 321, [3, 1, 26.0]],
    ['Pierre Dubois', 'member', 940, 412, [3, 2, 18.3]],
    // Active members
    ['Sarah Mitchell', 'member', 820, 0.1, null],
    ['David Kim', 'member', 310, 0.4, null],
    ['Elena Popescu', 'member', 150, 1.3, null],
    ['Liam O\'Connor', 'member', 95, 2.2, null],
    ['Mia Schneider', 'member', 44, 3.5, null],
    ['Noah Williams', 'member', 33, 5.0, null],
    ['Zara Ali', 'member', 28, 6.1, null],
    ['Carlos Mendes', 'member', 19, 8.7, null],
    ['Freya Nilsson', 'member', 17, 11.0, null],
    ['Ben Turner', 'member', 26, 14.6, null],
    ['Amara Diallo', 'member', 520, 19.2, null],
    ['Jonas Vogel', 'member', 11, 23.4, null],
    // Staff in the opted-out Manager group (inactive, but never reminded)
    ['Rachel Green', 'member', 1400, 65, null, 6],
    ['Tom Hughes', 'member', 1350, 120, null, 6],
    // Unsubscribed
    ['Viktor Petrov', 'member', 980, 150, null],
    ['Laura Silva', 'never', 40, null, null],
    ['Ines Moreau', 'member', 670, 205, null],
];
$optedOut = ['Viktor Petrov', 'Laura Silva', 'Ines Moreau'];

$labels = [1 => ['Activation reminder sent', 'COM_USERREMINDER_ACTIVATE_REMINDER_SENT'],
           2 => ['Login reminder sent', 'COM_USERREMINDER_LOGIN_REMINDER_SENT'],
           3 => ['User reminder sent', 'COM_USERREMINDER_USER_REMINDER_SENT']];
$log = [];
$users = [];
$domains = ['gmail.com', 'outlook.com', 'proton.me', 'yahoo.com', 'icloud.com', 'gmx.de', 'example.org'];

foreach ($people as $i => $p) {
    [$name, $state, $reg, $last, $rem] = $p;
    $group = $p[5] ?? 2;
    $slug = strtolower(preg_replace('/[^a-z]+/i', '.', preg_replace('/[^a-z ]/i', '', iconv('UTF-8', 'ASCII//TRANSLIT', $name))));
    $id = ins('users', [
        'name' => $name,
        'username' => str_replace('.', '', $slug) . ($i % 3 === 0 ? mt_rand(7, 99) : ''),
        'email' => $slug . '@' . $domains[$i % count($domains)],
        'password' => $hash,
        'block' => $state === 'pending' ? 1 : 0,
        'sendEmail' => 0,
        'registerDate' => ago($reg, mt_rand(7, 22)),
        'lastvisitDate' => $last === null ? null : ago($last, $last < 1 ? null : mt_rand(7, 22)),
        'activation' => $state === 'pending' ? md5($name . 'act') : '',
        'params' => $params,
        'otpKey' => '', 'otep' => '', 'requireReset' => 0, 'resetCount' => 0, 'authProvider' => '',
    ]);
    ins('user_usergroup_map', ['user_id' => $id, 'group_id' => $group]);
    $users[$name] = $id;
    $username = $db->query('SELECT username FROM ' . PREFIX . "users WHERE id = $id")->fetch_row()[0];
    $unames[$name] = $username;

    if ($rem) {
        [$type, $num, $sent] = $rem;
        ins('userreminder', ['userid' => $id, 'datesent' => ago($sent, 5), 'remindernumber' => $num, 'type' => $type, 'optoutcode' => md5($name . 'opt')]);
        // One log row per reminder; the earlier one a week before the last.
        for ($n = 1; $n <= $num; $n++) {
            $log[] = [$id, $username, $labels[$type], ago($sent + ($num - $n) * 7, 5)];
        }
    }
    if (in_array($name, $optedOut, true)) {
        ins('userreminder_optout', ['user_id' => $id]);
        $log[] = [$id, $username, $type = ['User opted out of reminders (via unsubscribe link)', 'COM_USERREMINDER_OPTOUT_LOG'], ago(mt_rand(3, 25) + 0.4)];
    }
    // Members who came back after a reminder: history only (their reminder row was cleared on login).
    if ($state === 'member' && $last !== null && $last < 25 && $reg > 30 && $i % 2 === 0) {
        $log[] = [$id, $username, $labels[3], ago($last + 2, 5)];
    }
    if ($state === 'member' && $reg < 30 && $last !== null) {
        $log[] = [$id, $username, $labels[1], ago($reg - 1, 5)];
    }
}

// A resubscribe: opted out, then changed their mind.
$amara = $users['Amara Diallo'];
$log[] = [$amara, $unames['Amara Diallo'], ['User opted out of reminders (via unsubscribe link)', 'COM_USERREMINDER_OPTOUT_LOG'], ago(18.3)];
$log[] = [$amara, $unames['Amara Diallo'], ['User resubscribed to reminders', 'COM_USERREMINDER_OPTIN_LOG'], ago(16.9)];

usort($log, fn ($a, $b) => strcmp($a[3], $b[3]));
foreach ($log as [$uid, $uname, [$desc, $key], $date]) {
    ins('userreminder_log', ['userId' => $uid, 'username' => $uname, 'description' => $desc, 'message_key' => $key, 'date' => $date]);
}

// Email container: the site's own name in the header, no sample banner image.
$row = $db->query("SELECT params FROM " . PREFIX . "extensions WHERE element = 'com_userreminder' AND type = 'component'")->fetch_row();
$cfg = json_decode($row[0] ?? '{}', true) ?: [];
$cfg = array_merge($cfg, ['mail_container' => '1', 'mail_container_logo' => '0', 'mail_container_header' => 'Northwind Makers Community',
    'mail_container_footer' => 'Sent by {SITENAME} - {YEAR}', 'numberOfReminders' => '2', 'numberOfDaysExistingUser' => '30']);
$db->query("UPDATE " . PREFIX . "extensions SET params = '" . $db->real_escape_string(json_encode($cfg)) . "' WHERE element = 'com_userreminder' AND type = 'component'");

// The scheduled task ran this morning, as on a live site.
q("UPDATE #__scheduler_tasks SET last_execution = '" . ago(0, 5) . "', next_execution = DATE_ADD('" . ago(0, 5) . "', INTERVAL 1 DAY), times_executed = 214, times_failed = 0, last_exit_code = 0 WHERE title = 'User Reminder emails'");

// Dashboard is cached: drop it so the next visit shows the new numbers.
foreach (glob('E:/laragon/www/usereminder/administrator/cache/com_userreminder*', GLOB_ONLYDIR) ?: [] as $dir) {
    array_map('unlink', glob("$dir/*") ?: []);
}

printf("Seeded %d users, %d reminder rows, %d log rows, %d opt-outs.\n", count($people), $db->query('SELECT COUNT(*) FROM ' . PREFIX . 'userreminder')->fetch_row()[0], count($log), count($optedOut));
