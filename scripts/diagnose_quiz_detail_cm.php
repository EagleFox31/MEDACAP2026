<?php
require_once __DIR__ . '/../core/medacap_config.php';
require __DIR__ . '/../vendor/autoload.php';
$client = new MongoDB\Client(medacapMongoUri('mongodb://10.68.0.7:27017'));
$vehicles = $client->academy->vehicles;
$tests = $client->academy->tests;
$users = $client->academy->users;

// Vehicle HINO Junior Factuel detail
$v = $vehicles->findOne(['brand' => 'HINO', 'level' => 'Junior', 'type' => 'Factuel', 'active' => true]);
echo "Vehicle HINO Junior Factuel:\n";
if ($v) {
    echo "  _id=" . (string)$v['_id'] . "\n";
    echo "  quizzes type=" . gettype($v['quizzes'] ?? null) . "\n";
    if (isset($v['quizzes'])) {
        echo "  quizzes count=" . (is_array($v['quizzes']) ? count($v['quizzes']) : 'not array') . "\n";
        if (is_array($v['quizzes']) && count($v['quizzes']) > 0) {
            echo "  first quiz sample: " . json_encode($v['quizzes'][0]) . "\n";
        }
    }
} else echo "  NOT FOUND\n";

// Brands with quizzes in vehicles (any active)
echo "\nMarques avec au moins 1 quiz dans vehicles (actif):\n";
$seen = [];
foreach ($vehicles->find(['active' => true]) as $v) {
    $q = $v['quizzes'] ?? [];
    $cnt = is_array($q) ? count($q) : 0;
    if ($cnt > 0) {
        $key = ($v['brand'] ?? '?') . '|' . ($v['level'] ?? '?') . '|' . ($v['type'] ?? '?');
        if (!isset($seen[$key])) {
            $seen[$key] = $cnt;
            echo "  $key => $cnt quizzes\n";
        }
    }
}
if (!$seen) echo "  (aucune)\n";

// Clovis Junior test detail
$u = $users->findOne(['username' => 'Clovis FORBANG', 'active' => true]);
$t = $tests->findOne(['user' => $u['_id'], 'level' => 'Junior', 'type' => 'Factuel']);
echo "\nClovis Junior Factuel test:\n";
if ($t) {
    echo "  total field={$t['total']}\n";
    echo "  quizzes count=" . (is_array($t['quizzes'] ?? null) ? count($t['quizzes']) : 0) . "\n";
    if (!empty($t['quizzes']) && is_array($t['quizzes'])) {
        echo "  first=" . json_encode($t['quizzes'][0]) . "\n";
    }
}

// Compare with a technicien imported recently who might work
$tech = $users->findOne(['country' => 'Cameroun', 'profile' => 'Technicien', 'active' => true, 'created' => new MongoDB\BSON\Regex('2026')]);
if ($tech) {
    echo "\nTechnicien récent: {$tech['username']} created={$tech['created']}\n";
    $tt = $tests->findOne(['user' => $tech['_id'], 'level' => 'Junior', 'type' => 'Factuel']);
    if ($tt) {
        echo "  Junior Factuel total={$tt['total']} quizzes=" . (is_array($tt['quizzes'] ?? null) ? count($tt['quizzes']) : 0) . "\n";
    }
}
