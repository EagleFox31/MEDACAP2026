<?php
require __DIR__ . '/../vendor/autoload.php';

$client = new MongoDB\Client('mongodb://10.68.0.7:27017');
$vehicles = $client->academy->vehicles;
$tests = $client->academy->tests;
$users = $client->academy->users;

echo "=== Véhicules actifs par marque/niveau (PL) ===\n";
$brands = ['HINO', 'SINOTRUK', 'KING LONG', 'RENAULT TRUCK', 'JCB', 'LOVOL', 'TOYOTA BT', 'TOYOTA FORKLIFT'];
$levels = ['Junior', 'Senior', 'Expert'];
$types = ['Factuel', 'Declaratif'];

foreach ($brands as $brand) {
    foreach ($levels as $level) {
        foreach ($types as $type) {
            $v = $vehicles->findOne(['brand' => $brand, 'level' => $level, 'type' => $type, 'active' => true]);
            if (!$v) continue;
            $q = is_array($v['quizzes'] ?? null) ? count($v['quizzes']) : 0;
            if ($q === 0) {
                echo "$brand | $level | $type => véhicule existe, quizzes=0\n";
            } else {
                echo "$brand | $level | $type => quizzes=$q\n";
            }
        }
    }
}

echo "\n=== Comptage global vehicles ===\n";
$total = $vehicles->count(['active' => true]);
$withQuizzes = 0;
$emptyQuizzes = 0;
$noQuizzesField = 0;
foreach ($vehicles->find(['active' => true]) as $v) {
    if (!isset($v['quizzes'])) { $noQuizzesField++; continue; }
    if (!is_array($v['quizzes']) || count($v['quizzes']) === 0) $emptyQuizzes++;
    else $withQuizzes++;
}
echo "Total actifs: $total\n";
echo "Avec quizzes non vides: $withQuizzes\n";
echo "Quizzes vides: $emptyQuizzes\n";
echo "Sans champ quizzes: $noQuizzesField\n";

echo "\n=== Exemple vehicle avec quizzes (1er trouvé) ===\n";
$sample = $vehicles->findOne(['active' => true, 'quizzes.0' => ['$exists' => true]]);
if ($sample) {
    echo "brand={$sample['brand']} level={$sample['level']} type={$sample['type']} quizzes=" . count($sample['quizzes']) . "\n";
} else {
    echo "AUCUN vehicle avec quizzes en base\n";
}

echo "\n=== Tests managers CM (quiz totals) ===\n";
foreach (['Clovis FORBANG', 'Sonkouat KUSSO', 'Gildas PASSO', 'Wonepo DZOGNOU'] as $uname) {
    $u = $users->findOne(['username' => $uname, 'active' => true]);
    if (!$u) continue;
    echo "\n$uname:\n";
    foreach ($tests->find(['user' => $u['_id']]) as $t) {
        $q = is_array($t['quizzes'] ?? null) ? count($t['quizzes']) : 0;
        echo "  {$t['level']} {$t['type']}: total={$t['total']} quizzes_array=$q brands=" . implode(',', (array)($t['brand'] ?? [])) . "\n";
    }
}
