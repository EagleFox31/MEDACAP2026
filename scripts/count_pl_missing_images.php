<?php
require __DIR__ . '/../vendor/autoload.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use MongoDB\Client;

$db = (new Client(getenv('MEDACAP_MONGO_URI') ?: 'mongodb://127.0.0.1:27017'))->academy;

function hasImage($q): bool
{
    if ($q instanceof MongoDB\Model\BSONDocument) {
        $q = $q->getArrayCopy();
    }
    $img = $q['image'] ?? null;
    return !($img === null || $img === '' || (is_string($img) && trim($img) === ''));
}

function needsImage(string $text): bool
{
    return (bool)preg_match(
        '/ci-dessous|ci-joint|schéma|schema|figure|l\'image|marquage\s+185|1200\s*A|micromètre|train planétaire|embrayage|suspension à lame|freinage pneumatique|valve illustrée|boitier ci|numéro\s*\d|repère\s*\d|élément\s*\d|représente le/i',
        $text
    );
}

$paper = $db->papers->findOne([
    'active' => true,
    '$and' => [
        ['label' => new Regex('Poids Lourds 2026', 'i')],
        ['label' => ['$not' => new Regex('Renault Truck', 'i')]],
    ],
], ['sort' => ['created' => -1]]);

$juniorIds = [];
$seniorIds = [];
foreach ($db->quizzes->find(['level' => 'Junior'], ['projection' => ['questions' => 1]]) as $qz) {
    foreach ((array)($qz['questions'] ?? []) as $qid) {
        $juniorIds[(string)($qid instanceof ObjectId ? $qid : $qid)] = true;
    }
}
foreach ($db->quizzes->find(['level' => 'Senior'], ['projection' => ['questions' => 1]]) as $qz) {
    foreach ((array)($qz['questions'] ?? []) as $qid) {
        $seniorIds[(string)($qid instanceof ObjectId ? $qid : $qid)] = true;
    }
}

$stats = [
    'Junior' => ['need' => 0, 'missing' => 0, 'ok' => 0],
    'Senior' => ['need' => 0, 'missing' => 0, 'ok' => 0],
];
$missing = [];

foreach ((array)($paper['questions'] ?? []) as $x) {
    $id = (string)($x instanceof ObjectId ? $x : $x);
    $level = isset($juniorIds[$id]) ? 'Junior' : (isset($seniorIds[$id]) ? 'Senior' : null);
    if ($level === null) {
        continue;
    }

    $q = $db->questions->findOne(['_id' => new ObjectId($id)]);
    if (!$q) {
        continue;
    }

    $text = trim((string)($q['label'] ?? $q['question'] ?? ''));
    if (!needsImage($text)) {
        continue;
    }

    $stats[$level]['need']++;
    if (hasImage($q)) {
        $stats[$level]['ok']++;
    } else {
        $stats[$level]['missing']++;
        $missing[] = [
            'level' => $level,
            'ref' => (string)($q['ref'] ?? ''),
            'id' => $id,
            'label' => $text,
        ];
    }
}

$totalNeed = $stats['Junior']['need'] + $stats['Senior']['need'];
$totalMissing = $stats['Junior']['missing'] + $stats['Senior']['missing'];

echo 'Paper: ' . ($paper['label'] ?? '') . "\n";
echo 'Questions total: ' . count(iterator_to_array($paper['questions'] ?? [])) . "\n\n";
echo "=== Énoncé qui suppose une image ===\n";
echo "Junior: besoin={$stats['Junior']['need']} | avec image={$stats['Junior']['ok']} | MANQUANT={$stats['Junior']['missing']}\n";
echo "Senior: besoin={$stats['Senior']['need']} | avec image={$stats['Senior']['ok']} | MANQUANT={$stats['Senior']['missing']}\n";
echo "TOTAL: besoin=$totalNeed | MANQUANT=$totalMissing\n\n";

if ($missing) {
    echo "=== Détail MANQUANT ($totalMissing) ===\n";
    foreach ($missing as $m) {
        echo "{$m['level']} | {$m['ref']} | {$m['id']}\n";
        echo '  ' . mb_substr($m['label'], 0, 120) . "\n\n";
    }
}
