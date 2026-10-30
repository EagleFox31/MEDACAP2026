<?php
require_once __DIR__ . '/../core/medacap_config.php';
/**
 * Ajoute les tests Expert manquants pour Clovis FORBANG (ex-cl4fo).
 * Usage: php scripts/fix_clovis_expert_tests_cm.php [--dry-run]
 */
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/includes/mongo_quiz_helpers.php';

use MongoDB\BSON\ObjectId;

$dryRun = in_array('--dry-run', $argv ?? [], true);
$mongoUri = medacapMongoUri('mongodb://10.68.0.7:27017');

$client = new MongoDB\Client($mongoUri);
$users = $client->academy->users;
$tests = $client->academy->tests;
$vehicles = $client->academy->vehicles;
$allocations = $client->academy->allocations;

$user = $users->findOne(['username' => 'Clovis FORBANG', 'active' => true, 'profile' => 'Manager']);
if (!$user) {
    echo "ERREUR: Clovis FORBANG introuvable\n";
    exit(1);
}

$userId = $user['_id'];
$brandExpert = array_values((array)($user['brandExpert'] ?? []));
if (empty($brandExpert)) {
    echo "ERREUR: brandExpert vide sur le compte\n";
    exit(1);
}

$existingExpert = $tests->findOne([
    'user' => $userId,
    'level' => 'Expert',
    'type' => 'Factuel',
]);

if ($existingExpert) {
    echo "Expert Factuel existe déjà — rien à faire.\n";
    exit(0);
}

echo ($dryRun ? "[DRY-RUN] " : '') . "Clovis FORBANG — ajout tests Expert\n";
echo "brandExpert: " . implode(', ', $brandExpert) . "\n\n";

function createTest($tests, ObjectId $userId, array $brands, string $type, string $level): ObjectId {
    return $tests->insertOne([
        'quizzes' => [],
        'user' => $userId,
        'brand' => $brands,
        'type' => $type,
        'level' => $level,
        'total' => 0,
        'active' => true,
        'created' => date('d-m-Y H:i:s'),
    ])->getInsertedId();
}

function addFromVehicles($tests, $vehicles, ObjectId $testId, string $brand, string $type, string $level): void {
    $vehicle = $vehicles->findOne(['brand' => $brand, 'type' => $type, 'level' => $level, 'active' => true]);
    $refs = mongoQuizRefs($vehicle);
    if ($refs) {
        $tests->updateOne(['_id' => $testId], ['$addToSet' => ['quizzes' => ['$each' => $refs]]]);
    }
}

function finalize($tests, ObjectId $testId): int {
    $t = $tests->findOne(['_id' => $testId]);
    $total = mongoQuizCount($t);
    $tests->updateOne(['_id' => $testId], ['$set' => ['total' => $total]]);
    return $total;
}

function allocate($allocations, ObjectId $userId, ObjectId $testId, string $type, string $level): void {
    $allocations->insertOne([
        'user' => $userId,
        'test' => $testId,
        'type' => $type,
        'level' => $level,
        'activeTest' => false,
        'activeManager' => false,
        'active' => false,
        'created' => date('d-m-Y H:i:s'),
    ]);
}

if ($dryRun) {
    echo "Créerait Expert Factuel + Expert Declaratif + 2 allocations\n";
    exit(0);
}

$tExF = createTest($tests, $userId, $brandExpert, 'Factuel', 'Expert');
$tExD = createTest($tests, $userId, $brandExpert, 'Declaratif', 'Expert');

foreach ($brandExpert as $b) {
    addFromVehicles($tests, $vehicles, $tExF, $b, 'Factuel', 'Expert');
    addFromVehicles($tests, $vehicles, $tExD, $b, 'Declaratif', 'Expert');
}

$totalF = finalize($tests, $tExF);
$totalD = finalize($tests, $tExD);

allocate($allocations, $userId, $tExF, 'Factuel', 'Expert');
allocate($allocations, $userId, $tExD, 'Declaratif', 'Expert');

$testCount = $tests->count(['user' => $userId]);
$allocCount = $allocations->count(['user' => $userId]);

echo "OK — Expert Factuel (quizzes=$totalF), Expert Declaratif (quizzes=$totalD)\n";
echo "Total tests: $testCount | Total allocations: $allocCount\n";
