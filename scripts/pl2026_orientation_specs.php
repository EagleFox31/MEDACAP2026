<?php
require_once __DIR__ . '/../core/medacap_config.php';
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/export_paper_scores.php';

$paperId = new MongoDB\BSON\ObjectId('6a452db87ae27d2cc30d8592');
$db = (new MongoDB\Client(medacapMongoUri('mongodb://10.68.0.7:27017')))->academy;

echo "=== Questions orientation contenant spécialité / maintenance / pneu / élec ===\n";
foreach ($db->orientation_questions->find(['active' => true]) as $o) {
    $title = toStr($o['title'] ?? '');
    $opts = toArr($o['options'] ?? []);
    $blob = $title . ' ' . implode(' ', array_map('strval', $opts));
    if (preg_match('/maintenance|pneu|élec|electri|spécial|compétence|domaine/i', $blob)) {
        echo "\n" . $title . "\n";
        foreach ($opts as $opt) echo "  - " . toStr($opt) . "\n";
    }
}

echo "\n=== Réponse orientation Machia (exemple) ===\n";
$u = $db->users->findOne(['username' => new MongoDB\BSON\Regex('Machia', 'i'), 'active' => true]);
if ($u) {
    $ori = $db->results->findOne(['user' => $u['_id'], 'paper' => $paperId, 'type' => 'Orientation']);
    if ($ori) {
        foreach (toArr($ori['answers'] ?? []) as $row) {
            $row = toArr($row);
            echo "\n" . toStr($row['title'] ?? '') . "\n";
            foreach (toArr($row['selected'] ?? []) as $s) echo "  * " . toStr($s) . "\n";
        }
    }
}
