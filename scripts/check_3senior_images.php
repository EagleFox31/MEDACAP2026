<?php
require __DIR__ . '/../vendor/autoload.php';
use MongoDB\BSON\ObjectId;
use MongoDB\Client;

$db = (new Client('mongodb://127.0.0.1:27017'))->academy;
$filesDir = realpath(__DIR__ . '/../public/files');

$map = [
    '663b4920fa7700005400293f' => 'PL26-SR-663b4920fa7700005400293f-contacteur-cle.png',
    '663b4922fa770000540029bf' => 'PL26-SR-663b4922fa770000540029bf-train-planetaire.png',
    '663b4920fa770000540028f5' => 'PL26-SR-663b4920fa770000540028f5-capteur-embrayage.png',
];

echo "=== Fichiers disque ===\n";
foreach ($map as $id => $file) {
    $ok = is_file($filesDir . '/' . $file) ? 'OK' : 'MANQUANT';
    echo "$file => $ok\n";
}

echo "\n=== MongoDB ===\n";
foreach ($map as $id => $file) {
    $q = $db->questions->findOne(['_id' => new ObjectId($id)], ['projection' => ['ref' => 1, 'image' => 1, 'label' => 1]]);
    if (!$q) { echo "$id: question absente\n"; continue; }
    $img = (string)($q['image'] ?? '');
    $disk = is_file($filesDir . '/' . $file);
    echo ($q['ref'] ?? '?') . " | image en base: " . ($img ?: '(vide)') . " | fichier: " . ($disk ? 'OK' : 'NON') . "\n";
}
