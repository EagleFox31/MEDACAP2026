<?php
/**
 * Attache le pack orientation recommandé au paper PL multi-marques.
 *
 * Usage: php scripts/attach_pl_orientation_pack.php
 */
require __DIR__ . '/../vendor/autoload.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use MongoDB\Client;

const PAPER_LABEL = 'Recrutement Mécanicien Poids Lourds 2026';

/** Pack recommandé PL (7 questions) */
const ORIENTATION_IDS = [
    '6944559e72d68a2edb25d835', // Ville de résidence
    '694455bf72d68a2edb25d836', // Mobilité Cameroun
    '694455ca72d68a2edb25d837', // Mobilité étranger
    '694455e172d68a2edb25d838', // Situation familiale
    '694455f972d68a2edb25d839', // Enfants à charge
    '6944558e72d68a2edb25d834', // Conduite poids lourds
    '6944563d72d68a2edb25d83a', // Prétention salariale
];

$uri = getenv('MEDACAP_MONGO_URI') ?: 'mongodb://localhost:27017';
$db = (new Client($uri))->academy;

$paper = $db->papers->findOne(
    ['active' => true, 'label' => new Regex('^' . preg_quote(PAPER_LABEL, '/') . '$', 'i')],
    ['sort' => ['created' => -1]]
);

if (!$paper) {
    fwrite(STDERR, "Paper introuvable : " . PAPER_LABEL . "\n");
    exit(1);
}

$orientationOids = [];
foreach (ORIENTATION_IDS as $id) {
    $oid = new ObjectId($id);
    $q = $db->orientation_questions->findOne(['_id' => $oid, 'active' => true]);
    if (!$q) {
        fwrite(STDERR, "Question d'orientation introuvable ou inactive : $id\n");
        exit(1);
    }
    $orientationOids[] = $oid;
}

$db->papers->updateOne(
    ['_id' => $paper['_id']],
    ['$set' => ['orientation' => $orientationOids]]
);

echo "Paper : " . ($paper['label'] ?? PAPER_LABEL) . "\n";
echo "ID    : " . $paper['_id'] . "\n";
echo "Orientation attachée : " . count($orientationOids) . " question(s)\n\n";

foreach ($orientationOids as $oid) {
    $q = $db->orientation_questions->findOne(['_id' => $oid], ['projection' => ['title' => 1]]);
    echo "  - " . ($q['title'] ?? $oid) . "\n";
}
