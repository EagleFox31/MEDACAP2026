<?php
require_once __DIR__ . '/../core/medacap_config.php';
require __DIR__ . '/../vendor/autoload.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use MongoDB\Client;
use MongoDB\Model\BSONDocument;

$uri = medacapMongoUri();
$db = (new Client($uri))->academy;

function docToArray($doc): array
{
    if ($doc instanceof BSONDocument) {
        return $doc->getArrayCopy();
    }
    return is_array($doc) ? $doc : [];
}

function qTextField(array $q): string
{
    return 'label';
}

function qGetText(array $q): string
{
    return trim((string)($q['label'] ?? $q['question'] ?? $q['text'] ?? ''));
}

function qHasImage(array $q): bool
{
    $img = $q['image'] ?? null;
    if ($img === null || $img === '') {
        return false;
    }
    if (is_string($img) && trim($img) !== '') {
        return true;
    }
    if ($img instanceof ObjectId) {
        return true;
    }
    return is_array($img) && !empty($img);
}

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
if ($qIds instanceof MongoDB\Model\BSONArray) {
    $qIds = $qIds->getArrayCopy();
}
$oids = [];
foreach ((array)$qIds as $x) {
    if ($x instanceof ObjectId) {
        $oids[] = $x;
    } elseif (is_string($x) && preg_match('/^[a-f0-9]{24}$/i', $x)) {
        $oids[] = new ObjectId($x);
    }
}

$juniorIds = [];
foreach ($db->quizzes->find(['level' => 'Junior', 'active' => true], ['projection' => ['questions' => 1]]) as $qz) {
    $raw = $qz['questions'] ?? [];
    if ($raw instanceof MongoDB\Model\BSONArray) {
        $raw = $raw->getArrayCopy();
    }
    foreach ((array)$raw as $qid) {
        $juniorIds[(string)($qid instanceof ObjectId ? $qid : $qid)] = true;
    }
}

$fixes = [
    "/\bI['']image\b/ui" => "l'image",
    "/\bI ['']image\b/ui" => "l'image",
    "/\bdonne t-on\b/ui" => "donne-t-on",
    "/\bQuel est la valeur\b/ui" => "Quelle est la valeur",
    "/\bLequel des schémas suivants représentent\b/ui" => "Lequel des schémas suivants représente",
    "/\bcâbles électrique\b/ui" => "câbles électriques",
    "/\blorsque on\b/ui" => "lorsqu'on",
    "/\s{2,}/u" => " ",
    "/\?\s*$/u" => " ?",
];

$updated = 0;
$noImage = [];

foreach ($oids as $oid) {
    $k = (string)$oid;
    if (!isset($juniorIds[$k])) {
        continue;
    }

    $q = docToArray($db->questions->findOne(['_id' => $oid, 'active' => true]));
    if (!$q) {
        continue;
    }

    $field = qTextField($q);
    $orig = qGetText($q);
    $new = $orig;
    foreach ($fixes as $pat => $rep) {
        $new = preg_replace($pat, $rep, $new);
    }
    $new = trim($new);

    if ($new !== $orig) {
        $db->questions->updateOne(['_id' => $oid], ['$set' => [$field => $new]]);
        echo "FIX {$oid}\n  AV: {$orig}\n  AP: {$new}\n\n";
        $updated++;
    }

    $text = $new ?: $orig;
    if (!qHasImage($q)) {
        $noImage[] = [
            'id' => $k,
            'text' => $text,
            'speciality' => (string)($q['speciality'] ?? ''),
            'proposals' => array_values(array_filter([
                (string)($q['proposal1'] ?? ''),
                (string)($q['proposal2'] ?? ''),
                (string)($q['proposal3'] ?? ''),
                (string)($q['proposal4'] ?? ''),
            ])),
        ];
    }
}

echo "=== {$updated} libellé(s) corrigé(s) ===\n\n";
echo "=== Questions Junior SANS image (" . count($noImage) . ") ===\n";
file_put_contents(
    __DIR__ . '/out_junior_no_image.json',
    json_encode($noImage, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

foreach ($noImage as $row) {
    echo $row['id'] . " | " . $row['text'] . "\n";
}
