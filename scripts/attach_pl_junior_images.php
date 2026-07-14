<?php
/**
 * Pré-attache les noms d'images (champ image) aux 9 questions Junior PL priorité HAUTE.
 * Les fichiers doivent être déposés dans public/files/
 *
 * Usage: php scripts/attach_pl_junior_images.php
 */
require __DIR__ . '/../vendor/autoload.php';

use MongoDB\BSON\ObjectId;
use MongoDB\Client;

$uri = getenv('MEDACAP_MONGO_URI') ?: 'mongodb://localhost:27017';
$db = (new Client($uri))->academy;

$map = [
    '66351f73b87b0000790039c1' => 'PL26-JR-66351f73b87b0000790039c1-pneu-88.png',
    '66351f73b87b0000790039c2' => 'PL26-JR-66351f73b87b0000790039c2-pneu-14.png',
    '66351f71b87b000079003907' => 'PL26-JR-66351f71b87b000079003907-batterie-1200A.png',
    '66351f71b87b00007900393a' => 'PL26-JR-66351f71b87b00007900393a-frein-accumulateurs.png',
    '66351f71b87b00007900393c' => 'PL26-JR-66351f71b87b00007900393c-frein-valve-4voies.png',
    '66351f71b87b00007900393b' => 'PL26-JR-66351f71b87b00007900393b-frein-compresseur.png',
    '66351f70b87b0000790038c3' => 'PL26-JR-66351f70b87b0000790038c3-embrayage-commande.png',
    '66351f70b87b0000790038c4' => 'PL26-JR-66351f70b87b0000790038c4-embrayage-amiante.png',
    '66351f70b87b0000790038d9' => 'PL26-JR-66351f70b87b0000790038d9-demi-arbre-joint.png',
];

$filesDir = realpath(__DIR__ . '/../public/files');
$ok = 0;

foreach ($map as $id => $filename) {
    $oid = new ObjectId($id);
    $q = $db->questions->findOne(['_id' => $oid], ['projection' => ['label' => 1, 'image' => 1]]);
    if (!$q) {
        echo "[ERR] Question introuvable: $id\n";
        continue;
    }

    $db->questions->updateOne(
        ['_id' => $oid],
        ['$set' => ['image' => $filename, 'updated' => date('d-m-Y H:i:s')]]
    );

    $path = $filesDir . DIRECTORY_SEPARATOR . $filename;
    $exists = is_file($path) ? 'fichier OK' : 'fichier manquant (à ajouter)';
    $label = mb_substr((string)($q['label'] ?? ''), 0, 70);
    echo "[OK] $id\n     image = $filename ($exists)\n     $label\n\n";
    $ok++;
}

echo "=== $ok / " . count($map) . " question(s) mises à jour ===\n";
