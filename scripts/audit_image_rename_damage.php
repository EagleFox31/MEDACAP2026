<?php
require __DIR__ . '/../vendor/autoload.php';
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use MongoDB\Client;

$db = (new Client('mongodb://127.0.0.1:27017'))->academy;
$filesDir = realpath(__DIR__ . '/../public/files');

$keep9 = [
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

$refs = ['Fac103','Fac240','Fac107','Fac210','Fac109','Fac127','Fac302','Fac2217'];

echo "=== Refs demandées (état BD + fichier) ===\n";
foreach ($refs as $ref) {
    $q = $db->questions->findOne(['ref' => new Regex('^' . preg_quote($ref, '/') . '$', 'i')]);
    if (!$q) { echo "$ref: absent\n"; continue; }
    $id = (string)$q['_id'];
    $img = (string)($q['image'] ?? '');
    $exists = ($img && is_file($filesDir . '/' . $img)) ? 'OK' : 'MANQUANT';
    echo "$ref | $id | image=$img | fichier=$exists\n";
    echo "  " . ($q['label'] ?? '') . "\n\n";
}

// Pattern PL26-JR-{id24}.png (bulk rename suspect)
$bulk = [];
foreach ($db->questions->find(['image' => new Regex('^PL26-JR-[a-f0-9]{24}\\.png$', 'i'), 'active' => true]) as $q) {
    $id = (string)$q['_id'];
    if (isset($keep9[$id])) continue;
    $img = (string)$q['image'];
    $bulk[] = [
        'ref' => (string)($q['ref'] ?? ''),
        'id' => $id,
        'image' => $img,
        'file_exists' => is_file($filesDir . '/' . $img),
    ];
}

echo "=== Questions avec PL26-JR-{id}.png HORS les 9: " . count($bulk) . " ===\n";
foreach (array_slice($bulk, 0, 30) as $b) {
    echo "{$b['ref']} | {$b['id']} | {$b['image']} | " . ($b['file_exists'] ? 'OK' : 'MANQUANT') . "\n";
}
if (count($bulk) > 30) echo "... +" . (count($bulk) - 30) . " autres\n";

// Junior PL paper: images avec fichier OK vs cassées
$paper = $db->papers->findOne([
    'active' => true,
    '$and' => [
        ['label' => new Regex('Poids Lourds 2026', 'i')],
        ['label' => ['$not' => new Regex('Renault Truck', 'i')]],
    ],
], ['sort' => ['created' => -1]]);

$juniorIds = [];
foreach ($db->quizzes->find(['level' => 'Junior'], ['projection' => ['questions' => 1]]) as $qz) {
    foreach ((array)($qz['questions'] ?? []) as $qid) {
        $juniorIds[(string)($qid instanceof ObjectId ? $qid : $qid)] = true;
    }
}

$broken = [];
$ok = [];
foreach ((array)($paper['questions'] ?? []) as $x) {
    $k = (string)($x instanceof ObjectId ? $x : $x);
    if (!isset($juniorIds[$k])) continue;
    $q = $db->questions->findOne(['_id' => new ObjectId($k)]);
    $img = trim((string)($q['image'] ?? ''));
    if ($img === '') continue;
    $exists = is_file($filesDir . '/' . $img);
    $row = ['ref' => $q['ref'] ?? '', 'id' => $k, 'image' => $img];
    if ($exists) $ok[] = $row; else $broken[] = $row;
}

echo "\n=== Junior PL: image en BD avec fichier OK: " . count($ok) . " ===\n";
echo "=== Junior PL: image en BD mais fichier MANQUANT: " . count($broken) . " ===\n";
foreach (array_slice($broken, 0, 15) as $b) {
    echo "  {$b['ref']} | {$b['image']}\n";
}
