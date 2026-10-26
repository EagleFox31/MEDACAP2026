<?php
require_once __DIR__ . '/../core/medacap_config.php';
require __DIR__ . '/../vendor/autoload.php';

function mongoCount($field): int {
    if ($field === null) return 0;
    if (is_array($field)) return count($field);
    if ($field instanceof Traversable || $field instanceof MongoDB\Model\BSONArray) {
        return iterator_count($field);
    }
    return 0;
}

$client = new MongoDB\Client(medacapMongoUri('mongodb://10.68.0.7:27017'));
$vehicles = $client->academy->vehicles;
$tests = $client->academy->tests;
$users = $client->academy->users;
$quizzes = $client->academy->quizzes;

echo "=== Vehicles PL/Engins — quizzes réels ===\n";
foreach (['HINO', 'SINOTRUK', 'KING LONG', 'RENAULT TRUCK', 'JCB'] as $brand) {
    foreach (['Junior', 'Senior', 'Expert'] as $level) {
        foreach (['Factuel', 'Declaratif'] as $type) {
            $v = $vehicles->findOne(['brand' => $brand, 'level' => $level, 'type' => $type, 'active' => true]);
            if (!$v) continue;
            $cnt = mongoCount($v['quizzes'] ?? null);
            echo "$brand | $level | $type => $cnt quiz refs\n";
        }
    }
}

echo "\n=== Managers — quizzes réels dans tests ===\n";
foreach (['Clovis FORBANG', 'Sonkouat KUSSO', 'Gildas PASSO', 'Wonepo DZOGNOU'] as $uname) {
    $u = $users->findOne(['username' => $uname, 'active' => true]);
    if (!$u) continue;
    echo "\n$uname:\n";
    foreach ($tests->find(['user' => $u['_id']]) as $t) {
        $cnt = mongoCount($t['quizzes'] ?? null);
        echo "  {$t['level']} {$t['type']}: total={$t['total']} quiz_refs=$cnt\n";
    }
}

echo "\n=== Technicien import récent (Michel BAYEMI) ===\n";
$tech = $users->findOne(['username' => 'Michel BAYEMI', 'active' => true]);
if ($tech) {
    foreach ($tests->find(['user' => $tech['_id']]) as $t) {
        $cnt = mongoCount($t['quizzes'] ?? null);
        if ($cnt > 0 || ($t['total'] ?? 0) > 0) {
            echo "  {$t['level']} {$t['type']}: total={$t['total']} quiz_refs=$cnt\n";
        }
    }
}

echo "\n=== Collection quizzes (actifs, sample HINO) ===\n";
$qSample = $quizzes->findOne(['brand' => 'HINO', 'active' => true]);
if ($qSample) {
    echo "  trouvé quiz HINO: level={$qSample['level']} type={$qSample['type']} total={$qSample['total']}\n";
} else {
    echo "  aucun quiz actif brand=HINO\n";
}
$qCount = $quizzes->count(['brand' => 'HINO', 'active' => true]);
echo "  quizzes HINO actifs: $qCount\n";
$qCountKl = $quizzes->count(['brand' => 'KING LONG', 'active' => true]);
echo "  quizzes KING LONG actifs: $qCountKl\n";
