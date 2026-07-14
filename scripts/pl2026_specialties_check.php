<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/export_paper_scores.php';

$paperId = new MongoDB\BSON\ObjectId('6a452db87ae27d2cc30d8592');
$db = (new MongoDB\Client('mongodb://10.68.0.7:27017'))->academy;
$paper = $db->papers->findOne(['_id' => $paperId]);

$qOids = [];
foreach (toArr($paper['questions'] ?? []) as $x) {
    $qOids[] = $x instanceof MongoDB\BSON\ObjectId ? $x : new MongoDB\BSON\ObjectId((string)$x);
}

$bySpec = [];
$byLevelSpec = [];
foreach ($db->questions->find(['_id' => ['$in' => $qOids], 'active' => true]) as $q) {
    $spec = toStr($q['speciality'] ?? '') ?: '(Sans spécialité)';
    $level = toStr($q['level'] ?? 'Autre');
    $bySpec[$spec] = ($bySpec[$spec] ?? 0) + 1;
    $byLevelSpec[$level][$spec] = ($byLevelSpec[$level][$spec] ?? 0) + 1;
}

echo "Paper: " . toStr($paper['label']) . "\n\n";
echo "=== Spécialités dans la banque (200 questions) ===\n";
arsort($bySpec);
foreach ($bySpec as $spec => $n) {
    echo "  $spec : $n questions\n";
}

echo "\n=== Par niveau × spécialité ===\n";
foreach (['Junior', 'Senior', 'Expert'] as $lv) {
    if (empty($byLevelSpec[$lv])) continue;
    echo "$lv:\n";
    foreach ($byLevelSpec[$lv] as $spec => $n) {
        echo "  - $spec : $n\n";
    }
}

echo "\n=== Groupes fonctionnels actifs en base ===\n";
foreach ($db->groupes_fonctionnels->find(['isActive' => true]) as $gf) {
    echo "  " . toStr($gf['name'] ?? '') . "\n";
}

echo "\n=== Orientation du paper ===\n";
foreach (toArr($paper['orientation'] ?? []) as $oid) {
    $o = $db->orientation_questions->findOne(['_id' => $oid instanceof MongoDB\BSON\ObjectId ? $oid : new MongoDB\BSON\ObjectId((string)$oid)]);
    if ($o) echo "  " . toStr($o['title'] ?? '') . "\n";
}
