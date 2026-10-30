<?php
require_once __DIR__ . '/../core/medacap_config.php';
require __DIR__ . '/../vendor/autoload.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use MongoDB\Client;

$uri = medacapMongoUri();
$db = (new Client($uri))->academy;

$paper = $db->papers->findOne(
    [
        'active' => true,
        '$and' => [
            ['label' => new Regex('Poids Lourds 2026', 'i')],
            ['label' => ['$not' => new Regex('Renault Truck', 'i')]],
        ],
    ],
    ['sort' => ['created' => -1]]
);

$qIds = $paper['questions'] ?? [];
if ($qIds instanceof MongoDB\Model\BSONArray) $qIds = $qIds->getArrayCopy();
$oids = [];
foreach ((array)$qIds as $x) {
    if ($x instanceof ObjectId) $oids[] = $x;
    elseif (is_string($x) && preg_match('/^[a-f0-9]{24}$/i', $x)) $oids[] = new ObjectId($x);
}

$quizLevel = [];
foreach ($db->quizzes->find(['level' => 'Junior', 'active' => true], ['projection' => ['questions' => 1]]) as $qz) {
    $raw = $qz['questions'] ?? [];
    if ($raw instanceof MongoDB\Model\BSONArray) $raw = $raw->getArrayCopy();
    foreach ((array)$raw as $qid) {
        $quizLevel[(string)($qid instanceof ObjectId ? $qid : $qid)] = true;
    }
}

$imageKw = ['image', 'figure', 'schéma', 'schema', 'photo', 'ci-dessous', 'ci dessous', 'représent', 'diagramme'];
$grammarFlags = [];
$imageDep = 0;
$noImageField = 0;
$withImageField = 0;
$all = [];

foreach ($oids as $oid) {
    if (!isset($quizLevel[(string)$oid])) continue;
    $q = $db->questions->findOne(['_id' => $oid, 'active' => true]);
    if (!$q) continue;
    $text = trim(strip_tags((string)($q['question'] ?? $q['label'] ?? $q['text'] ?? '')));
    $hasImgKw = false;
    foreach ($imageKw as $kw) {
        if (stripos($text, $kw) !== false) { $hasImgKw = true; break; }
    }
    $img = $q['image'] ?? $q['img'] ?? $q['picture'] ?? null;
    $hasImgDoc = !empty($img);
    if ($hasImgKw) {
        $imageDep++;
        if (!$hasImgDoc) $noImageField++;
        else $withImageField++;
    }
    if (preg_match("/I['']image|donne t-on|Quel est la valeur|Quel est le nom de l'élément correspondant au numéro/i", $text)) {
        $grammarFlags[] = mb_substr($text, 0, 120);
    }
    $all[] = $text;
}

echo "Junior total: " . count($all) . "\n";
echo "Questions mentionnant une image/schéma: $imageDep\n";
echo "  avec champ image en base: $withImageField\n";
echo "  SANS image attachée: $noImageField\n\n";

echo "=== Exemples dépendants image (sans image en base) ===\n";
$i = 0;
foreach ($oids as $oid) {
    if (!isset($quizLevel[(string)$oid])) continue;
    $q = $db->questions->findOne(['_id' => $oid, 'active' => true]);
    if (!$q) continue;
    $text = trim(strip_tags((string)($q['question'] ?? $q['label'] ?? $q['text'] ?? '')));
    $hasImgKw = (bool)preg_match('/image|figure|schéma|ci-dessous|représent/i', $text);
    $img = $q['image'] ?? $q['img'] ?? $q['picture'] ?? null;
    if ($hasImgKw && empty($img) && $i < 8) {
        echo "- $text\n";
        $i++;
    }
}

echo "\n=== Questions texte seul (les plus longues) ===\n";
usort($all, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
foreach (array_slice($all, 0, 8) as $t) {
    echo "- [$t]\n";
}
