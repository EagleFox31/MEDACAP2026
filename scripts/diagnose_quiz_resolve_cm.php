<?php
require __DIR__ . '/../vendor/autoload.php';

function mongoToArray($field): array {
    if ($field === null) return [];
    if (is_array($field)) return $field;
    if ($field instanceof Traversable) return iterator_to_array($field);
    return [];
}

$client = new MongoDB\Client('mongodb://10.68.0.7:27017');
$vehicles = $client->academy->vehicles;
$quizzes = $client->academy->quizzes;

$v = $vehicles->findOne(['brand' => 'KING LONG', 'level' => 'Expert', 'type' => 'Factuel', 'active' => true]);
$refs = mongoToArray($v['quizzes'] ?? null);
echo "KING LONG Expert Factuel vehicle: " . count($refs) . " quiz refs\n";
if ($refs) {
    $q = $quizzes->findOne(['_id' => $refs[0]]);
    echo "Premier quiz doc: " . ($q ? "exists, total={$q['total']}, active=" . var_export($q['active'] ?? null, true) : "NOT FOUND") . "\n";
}
