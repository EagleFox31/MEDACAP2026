<?php
/**
 * Attache les 3 images Senior PL manquantes.
 * Usage: php scripts/attach_pl_senior_images.php
 */
require __DIR__ . '/../vendor/autoload.php';

use MongoDB\BSON\ObjectId;
use MongoDB\Client;

$uri = getenv('MEDACAP_MONGO_URI') ?: 'mongodb://127.0.0.1:27017';
$db = (new Client($uri))->academy;
$filesDir = realpath(__DIR__ . '/../public/files');

$map = [
    '663b4920fa7700005400293f' => 'PL26-SR-663b4920fa7700005400293f-contacteur-cle.png',
    '663b4922fa770000540029bf' => 'PL26-SR-663b4922fa770000540029bf-train-planetaire.png',
    '663b4920fa770000540028f5' => 'PL26-SR-663b4920fa770000540028f5-capteur-embrayage.png',
];

foreach ($map as $id => $filename) {
    $oid = new ObjectId($id);
    $q = $db->questions->findOne(['_id' => $oid], ['projection' => ['ref' => 1, 'image' => 1, 'label' => 1]]);
    if (!$q) {
        echo "[ERR] Question introuvable: $id\n";
        continue;
    }

    $path = $filesDir . DIRECTORY_SEPARATOR . $filename;
    if (!is_file($path)) {
        echo "[ERR] Fichier manquant: $filename\n";
        continue;
    }

    $db->questions->updateOne(
        ['_id' => $oid],
        ['$set' => ['image' => $filename, 'updated' => date('d-m-Y H:i:s')]]
    );

    $ref = (string)($q['ref'] ?? '');
    $label = mb_substr((string)($q['label'] ?? ''), 0, 70);
    echo "[OK] $ref | $id\n     image = $filename\n     $label\n\n";
}

echo '=== ' . count($map) . ' image(s) attachée(s) ===' . "\n";
