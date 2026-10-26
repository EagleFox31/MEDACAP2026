<?php
require_once __DIR__ . '/../core/medacap_config.php';
require __DIR__ . '/../vendor/autoload.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use MongoDB\Client;

$uri = medacapMongoUri();
$db = (new Client($uri))->academy;

$refs = ['Fac103', 'Fac240', 'Fac107', 'Fac2217', 'Fac210', 'Fac109', 'Fac127', 'Fac302', 'Fac2o217', 'fac103', 'fac240'];

function hasImage($q): bool {
    $img = $q['image'] ?? null;
    return !($img === null || $img === '' || (is_string($img) && trim($img) === ''));
}

function needsVisual(string $label): bool {
    return (bool)preg_match('/ci-dessous|ci-joint|schéma|figure|image|représente le|marquage|illustr|diagramme|repère\s*\d|élément\s*\d|numéro\s*\d/i', $label);
}

echo "=== Questions par ref demandée ===\n\n";
foreach ($refs as $ref) {
    $q = $db->questions->findOne(['ref' => new Regex('^' . preg_quote($ref, '/') . '$', 'i'), 'active' => true]);
    if (!$q) {
        if (in_array($ref, ['Fac2o217'], true)) continue;
        echo "[$ref] INTROUVABLE\n\n";
        continue;
    }
    $id = (string)$q['_id'];
    $label = (string)($q['label'] ?? '');
    $img = $q['image'] ?? null;
    echo "[$ref] id=$id level=" . ($q['level'] ?? '') . " spec=" . ($q['speciality'] ?? '') . "\n";
    echo "  image: " . ($img ?: '(vide)') . " | fichier: " . (hasImage($q) && is_file(__DIR__ . '/../public/files/' . $img) ? 'OK' : 'MANQUANT') . "\n";
    echo "  label: $label\n";
    echo "  needs_visual: " . (needsVisual($label) ? 'OUI' : 'NON') . "\n\n";
}

// PL paper junior without image stats
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

$inPaper = [];
foreach ((array)($paper['questions'] ?? []) as $x) {
    $k = (string)($x instanceof ObjectId ? $x : $x);
    if (isset($juniorIds[$k])) $inPaper[] = new ObjectId($k);
}

$noImg = 0;
$noImgNeedsVisual = 0;
$noImgTextOnly = 0;
$examples = [];

foreach ($db->questions->find(['_id' => ['$in' => $inPaper], 'active' => true]) as $q) {
    if (hasImage($q)) continue;
    $noImg++;
    $label = (string)($q['label'] ?? '');
    if (needsVisual($label)) {
        $noImgNeedsVisual++;
        if (count($examples) < 15) {
            $examples[] = ['ref' => $q['ref'] ?? '', 'id' => (string)$q['_id'], 'label' => $label];
        }
    } else {
        $noImgTextOnly++;
    }
}

echo "=== Paper PL Junior : pourquoi tant sans image ? ===\n";
echo "Total Junior dans paper: " . count($inPaper) . "\n";
echo "Sans image: $noImg\n";
echo "  → texte seul (pas besoin visuel): $noImgTextOnly\n";
echo "  → mentionnent schéma/image/ci-dessous: $noImgNeedsVisual\n";
echo "  → déjà traités (9 HAUTE): 9 avec image maintenant\n\n";

echo "Exemples Junior sans image qui DEVRAIENT avoir un visuel (hors les 9):\n";
foreach ($examples as $e) {
    if (in_array($e['ref'], ['Fac103','Fac240','Fac107','Fac2217','Fac210','Fac109','Fac127','Fac302'], true)) continue;
    echo "  {$e['ref']} | {$e['id']} | " . mb_substr($e['label'], 0, 80) . "\n";
}
