<?php
require __DIR__ . '/../vendor/autoload.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use MongoDB\Client;

$uri = getenv('MEDACAP_MONGO_URI') ?: 'mongodb://localhost:27017';
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
if (!$paper) {
    $paper = $db->papers->findOne(
        ['active' => true, 'label' => new Regex('LOVOL Senior', 'i')],
        ['sort' => ['created' => -1]]
    );
}

$paperLabel = $paper['label'] ?? '?';
$qIds = $paper['questions'] ?? [];
if ($qIds instanceof MongoDB\Model\BSONArray) $qIds = $qIds->getArrayCopy();

$oids = [];
foreach ((array)$qIds as $x) {
    if ($x instanceof ObjectId) $oids[] = $x;
    elseif (is_string($x) && preg_match('/^[a-f0-9]{24}$/i', $x)) $oids[] = new ObjectId($x);
}

// Map question -> quiz level via source_quizzes / quizzes
$quizLevel = [];
foreach ($db->quizzes->find(['level' => 'Junior', 'active' => true], ['projection' => ['questions' => 1, 'level' => 1, 'speciality' => 1]]) as $qz) {
    $raw = $qz['questions'] ?? [];
    if ($raw instanceof MongoDB\Model\BSONArray) $raw = $raw->getArrayCopy();
    foreach ((array)$raw as $qid) {
        $k = (string)($qid instanceof ObjectId ? $qid : $qid);
        $quizLevel[$k] = ['level' => 'Junior', 'speciality' => (string)($qz['speciality'] ?? '')];
    }
}

$junior = [];
foreach ($oids as $oid) {
    $k = (string)$oid;
    if (!isset($quizLevel[$k])) continue;
    $q = $db->questions->findOne(['_id' => $oid, 'active' => true]);
    if (!$q) continue;
    $junior[] = $q;
}

echo "Paper: $paperLabel\n";
echo "Junior questions in paper: " . count($junior) . "\n\n";

$sample = array_slice($junior, 0, 25);
foreach ($sample as $i => $q) {
    $text = trim(strip_tags((string)($q['question'] ?? $q['label'] ?? $q['text'] ?? '')));
    $text = preg_replace('/\s+/', ' ', $text);
    $opts = $q['options'] ?? $q['answers'] ?? [];
    if ($opts instanceof MongoDB\Model\BSONArray) $opts = $opts->getArrayCopy();
    echo "--- Q" . ($i + 1) . " (" . ($quizLevel[(string)$q['_id']]['speciality'] ?? '') . ") ---\n";
    echo mb_substr($text, 0, 500) . (mb_strlen($text) > 500 ? '...' : '') . "\n";
    if (is_array($opts)) {
        foreach (array_slice($opts, 0, 4) as $o) {
            if (is_string($o)) echo "  • $o\n";
            elseif (is_array($o)) echo "  • " . ($o['label'] ?? $o['text'] ?? json_encode($o)) . "\n";
        }
    }
    echo "\n";
}

// Stats longueur
$lens = [];
foreach ($junior as $q) {
    $t = trim(strip_tags((string)($q['question'] ?? $q['label'] ?? $q['text'] ?? '')));
    $lens[] = mb_strlen($t);
}
if ($lens) {
    echo "=== Stats libellés (" . count($lens) . " junior) ===\n";
    echo "Moyenne: " . round(array_sum($lens) / count($lens)) . " car.\n";
    echo "Max: " . max($lens) . " | Min: " . min($lens) . "\n";
    echo ">200 car: " . count(array_filter($lens, fn($l) => $l > 200)) . "\n";
    echo ">350 car: " . count(array_filter($lens, fn($l) => $l > 350)) . "\n";
}
