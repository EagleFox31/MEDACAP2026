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
        echo "$username: NOT FOUND\n\n";
        continue;
    }

    echo "=== $username ===\n";
    echo "level={$u['level']} test=" . (!empty($u['test']) ? 'yes' : 'no') . "\n";
    echo "J: " . implode(', ', (array)($u['brandJunior'] ?? [])) . "\n";
    echo "S: " . implode(', ', (array)($u['brandSenior'] ?? [])) . "\n";
    echo "E: " . implode(', ', (array)($u['brandExpert'] ?? [])) . "\n";
    echo "created={$u['created']}" . (isset($u['updated']) ? " updated={$u['updated']}" : '') . "\n\n";

    $cursor = $tests->find(['user' => $u['_id']], ['sort' => ['level' => 1, 'type' => 1]]);
    $i = 0;
    foreach ($cursor as $t) {
        $i++;
        $quizCount = is_array($t['quizzes'] ?? null) ? count($t['quizzes']) : 0;
        echo "  Test #$i: {$t['level']} {$t['type']} | quizzes=$quizCount | brands=" . implode(',', (array)($t['brand'] ?? [])) . "\n";
    }
    echo "  Total tests: $i\n";

    $ac = $alloc->count(['user' => $u['_id']]);
    echo "  Total allocations: $ac\n\n";
}
