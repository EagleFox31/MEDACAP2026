<?php
require __DIR__ . '/../vendor/autoload.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use MongoDB\Client;

$uri = getenv('MEDACAP_MONGO_URI') ?: 'mongodb://localhost:27017';
$db = (new Client($uri))->academy;

echo "=== Questions d'orientation globales (actives) ===\n\n";
foreach ($db->orientation_questions->find(
    ['active' => true, 'scope' => 'global'],
    ['sort' => ['title' => 1]]
) as $q) {
    $opts = $q['options'] ?? $q['choices'] ?? $q['values'] ?? [];
    if ($opts instanceof MongoDB\Model\BSONArray) $opts = $opts->getArrayCopy();
    $optCount = is_array($opts) ? count($opts) : 0;
    echo (string)$q['_id'] . "\n";
    echo "  Titre: " . ($q['title'] ?? $q['label'] ?? '?') . "\n";
    if (!empty($q['description'])) echo "  Desc: " . $q['description'] . "\n";
    echo "  Options: $optCount\n";
    if (is_array($opts) && $optCount > 0 && $optCount <= 12) {
        foreach ($opts as $o) {
            if (is_string($o)) echo "    - $o\n";
            elseif (is_array($o)) echo "    - " . ($o['label'] ?? $o['value'] ?? json_encode($o)) . "\n";
        }
    }
    echo "\n";
}

echo "=== Papers recrutement avec orientation ===\n\n";
foreach ($db->papers->find(
    ['active' => true, 'label' => new Regex('Recrutement', 'i')],
    ['sort' => ['created' => -1], 'projection' => ['label' => 1, 'orientation' => 1]]
) as $p) {
    $ori = $p['orientation'] ?? [];
    if ($ori instanceof MongoDB\Model\BSONArray) $ori = $ori->getArrayCopy();
    if (empty($ori)) continue;
    echo ($p['label'] ?? '?') . " (" . count($ori) . " ori)\n";
    foreach ($ori as $oid) {
        $id = $oid instanceof ObjectId ? (string)$oid : (string)$oid;
        $oq = $db->orientation_questions->findOne(['_id' => new ObjectId($id)]);
        echo "  - $id : " . ($oq['title'] ?? $oq['label'] ?? '?') . "\n";
    }
    echo "\n";
}
