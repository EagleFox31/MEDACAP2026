<?php
require_once __DIR__ . '/../core/medacap_config.php';
require __DIR__ . '/../vendor/autoload.php';

$client = new MongoDB\Client(medacapMongoUri('mongodb://10.68.0.7:27017'));
$users = $client->academy->users;
$tests = $client->academy->tests;
$alloc = $client->academy->allocations;

$names = ['Clovis FORBANG', 'Sonkouat KUSSO', 'Gildas PASSO', 'Wonepo DZOGNOU'];
foreach ($names as $username) {
    $u = $users->findOne(['username' => $username, 'active' => true]);
    if (!$u) {
        echo "$username: NOT FOUND\n";
        continue;
    }
    $tc = $tests->count(['user' => $u['_id']]);
    $ac = $alloc->count(['user' => $u['_id']]);
    echo "$username: profile={$u['profile']} test=" . (!empty($u['test']) ? 'yes' : 'no') . " level={$u['level']} tests=$tc alloc=$ac\n";
}
