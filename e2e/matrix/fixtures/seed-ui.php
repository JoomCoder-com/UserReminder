<?php
/**
 * Users for the browser suite (e2e/matrix), one set per run.
 *
 * Usage (inside a .testbed container): php seed-ui.php <run>
 * Needs /e2e/_boot.php from .testbed/e2e. Prints JSON: {"domain": ..., "ids": {...}}.
 *
 * Every e-mail is <key>@<run>-<tag>.example.test, so a shared Mailpit can be searched for exactly
 * this run's mails. The users match the reminder rules with the default options:
 *   act    not activated, registered 10 days ago        -> activation reminder
 *   never  activated, never logged in, 10 days old      -> login reminder
 *   inact  last visit 400 days ago                      -> inactive reminder
 *   member a normal active user (visited today)         -> nothing; used for the opt-out list
 */

require '/e2e/_boot.php';

$run = preg_replace('/[^a-z0-9]/', '', strtolower($argv[1] ?? ''));

if ($run === '') {
    fwrite(STDERR, "usage: seed-ui.php <run>\n");
    exit(2);
}

$domain = "$run-$TAG.example.test";
$now    = time();
$users  = [
    'act'    => ['block' => 1, 'activation' => md5("act$run"), 'registerDate' => $now - 10 * 86400, 'lastvisitDate' => null],
    'never'  => ['block' => 0, 'activation' => '', 'registerDate' => $now - 10 * 86400, 'lastvisitDate' => null],
    'inact'  => ['block' => 0, 'activation' => '', 'registerDate' => $now - 500 * 86400, 'lastvisitDate' => $now - 400 * 86400],
    'member' => ['block' => 0, 'activation' => '', 'registerDate' => $now - 30 * 86400, 'lastvisitDate' => $now],
];

$registered = (int) (\Joomla\CMS\Component\ComponentHelper::getParams('com_users')->get('new_usertype', 2) ?: 2);
$ids        = [];

foreach ($users as $key => $u) {
    $username = "ui-$run-$key";
    $id       = (int) val("SELECT id FROM {$P}users WHERE username = " . $db->quote($username));

    if (!$id) {
        $row = (object) [
            'name'          => 'UI ' . ucfirst($key) . " $run",
            'username'      => $username,
            'email'         => "$key@$domain",
            'password'      => password_hash('e2epassword', PASSWORD_BCRYPT),
            'block'         => $u['block'],
            'sendEmail'     => 0,
            'registerDate'  => gmdate('Y-m-d H:i:s', $u['registerDate']),
            'lastvisitDate' => $u['lastvisitDate'] === null ? null : gmdate('Y-m-d H:i:s', $u['lastvisitDate']),
            'activation'    => $u['activation'],
            'params'        => '{}',
            'lastResetTime' => gmdate('Y-m-d H:i:s', $u['registerDate']),
            'requireReset'  => 0,
            'otpKey'        => '',
            'otep'          => '',
        ];
        $db->insertObject("{$P}users", $row, 'id');
        $id = (int) $row->id;
        q("INSERT INTO {$P}user_usergroup_map (user_id, group_id) VALUES ($id, $registered)");
    }

    $ids[$key] = $id;
}

echo json_encode(['domain' => $domain, 'ids' => $ids]), "\n";
