<?php
require_once __DIR__ . '/../core/medacap_config.php';
require __DIR__ . '/../vendor/autoload.php';
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use MongoDB\Client;

$db = (new Client(medacapMongoUri()))->academy;
$filesDir = realpath(__DIR__ . '/../public/files');

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

$oids = [];
foreach ((array)($paper['questions'] ?? []) as $x) {
    $k = (string)($x instanceof ObjectId ? $x : $x);
    if (isset($juniorIds[$k])) $oids[] = new ObjectId($k);
}

$withField = 0;
$fileOk = 0;
$fileMissing = 0;
$fieldEmpty = 0;
$missingList = [];

foreach ($db->questions->find(['_id' => ['$in' => $oids]]) as $q) {
    $img = trim((string)($q['image'] ?? ''));
    if ($img === '') { $fieldEmpty++; continue; }
    $withField++;
    $path = $filesDir . DIRECTORY_SEPARATOR . $img;
    if (is_file($path)) $fileOk++;
    else {
        $fileMissing++;
        $missingList[] = [
            'ref' => (string)($q['ref'] ?? ''),
            'id' => (string)$q['_id'],
            'image' => $img,
            'label' => mb_substr((string)($q['label'] ?? ''), 0, 90),
        ];
    }
}

echo "Junior PL paper: " . count($oids) . " questions\n";
echo "Champ image vide: $fieldEmpty\n";
echo "Champ image renseigné: $withField\n";
echo "  Fichier présent: $fileOk\n";
echo "  Fichier MANQUANT: $fileMissing\n\n";

$requested = ['Fac103','Fac240','Fac107','Fac210','Fac109','Fac127','Fac302'];
echo "=== Refs demandées ===\n";
foreach ($missingList as $m) {
    if (in_array($m['ref'], $requested, true) || $m['ref'] === 'Fac2217') {
        echo "{$m['ref']} | {$m['id']} | {$m['image']}\n  {$m['label']}\n\n";
    }
}

echo "Total fichiers manquants (échantillon 20):\n";
foreach (array_slice($missingList, 0, 20) as $m) {
    echo "  {$m['ref']} | {$m['image']}\n";
}
