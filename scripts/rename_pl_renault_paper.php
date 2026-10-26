<?php
require_once __DIR__ . '/../core/medacap_config.php';
require __DIR__ . '/../vendor/autoload.php';

use MongoDB\BSON\ObjectId;
use MongoDB\Client;

$uri = medacapMongoUri('mongodb://10.68.0.7:27017');
$db = (new Client($uri))->academy;

$renaultOnlyId = '6a452b7dc0904052260e9eb2';
$newLabel = 'Recrutement Mécanicien Poids Lourds Renault Truck 2026';

$paper = $db->papers->findOne(['_id' => new ObjectId($renaultOnlyId)]);
if (!$paper) {
    fwrite(STDERR, "Paper Renault introuvable: $renaultOnlyId\n");
    exit(1);
}

$oldLabel = (string)($paper['label'] ?? '');
$result = $db->papers->updateOne(
    ['_id' => new ObjectId($renaultOnlyId)],
    ['$set' => ['label' => $newLabel]]
);

echo "Mongo: $uri\n";
echo "ID: $renaultOnlyId\n";
echo "Ancien libellé: $oldLabel\n";
echo "Nouveau libellé: $newLabel\n";
echo 'Modifié: ' . ($result->getModifiedCount() > 0 ? 'oui' : 'déjà à jour') . "\n";

echo "\n--- Épreuves PL actuelles ---\n";
$cursor = $db->papers->find(
    ['label' => new MongoDB\BSON\Regex('Poids Lourds', 'i')],
    ['projection' => ['label' => 1, 'brand' => 1, 'created' => 1], 'sort' => ['created' => -1]]
);
foreach ($cursor as $p) {
    $brands = $p['brand'] ?? [];
    if ($brands instanceof MongoDB\Model\BSONArray) {
        $brands = $brands->getArrayCopy();
    }
    echo (string) $p['_id'] . ' | ' . ($p['label'] ?? '') . ' | ' . implode(', ', (array) $brands) . "\n";
}
