<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/export_paper_scores.php';

$paperId = new MongoDB\BSON\ObjectId('6a452db87ae27d2cc30d8592');
$client = new MongoDB\Client('mongodb://10.68.0.7:27017');
$db = $client->academy;

$targets = ['imanol', 'machia', 'dinga', 'mengolo'];

function countAnswered($ua): int {
    $n = 0;
    foreach (toArr($ua) as $v) {
        if ((string)$v !== '' && (string)$v !== 'null') $n++;
    }
    return $n;
}

echo "Paper: Recrutement Mécanicien Poids Lourds 2026\n\n";

foreach ($db->users->find(['active' => true, 'country' => 'Cameroun']) as $u) {
    $blob = strtolower(implode(' ', [toStr($u['username'] ?? ''), toStr($u['firstName'] ?? ''), toStr($u['lastName'] ?? '')]));
    $match = false;
    foreach ($targets as $t) {
        if (strpos($blob, $t) !== false) { $match = true; break; }
    }
    if (!$match) continue;

    $uid = $u['_id'];
    $name = trim(toStr($u['firstName']) . ' ' . toStr($u['lastName']));
    echo str_repeat('=', 70) . "\n";
    echo "$name | " . toStr($u['username']) . "\n\n";

    $alloc = $db->allocations->findOne(['user' => $uid, 'paper' => $paperId]);
    if ($alloc) {
        echo "ALLOCATION\n";
        echo "  status=" . toStr($alloc['status'] ?? '') . "\n";
        echo "  created=" . toStr($alloc['created'] ?? '') . "\n";
        echo "  updated=" . toStr($alloc['updated'] ?? '') . "\n";
        echo "  finishedAt=" . toStr($alloc['finishedAt'] ?? '') . "\n";
    }

    echo "\nRESULTS (Factuel Mixed):\n";
    foreach ($db->results->find(['user' => $uid, 'paper' => $paperId, 'type' => 'Factuel'], ['sort' => ['created' => 1]]) as $r) {
        $ans = countAnswered($r['userAnswers'] ?? []);
        echo "  created=" . toStr($r['created'] ?? '') . " | answered=$ans/200 | score=" . ($r['score'] ?? '?') . "/" . ($r['total'] ?? '?') . "\n";
    }

    echo "\nEXAMS:\n";
    foreach ($db->exams->find(['user' => $uid, 'paper' => $paperId], ['sort' => ['created' => 1]]) as $e) {
        $ans = countAnswered($e['answers'] ?? []);
        echo "  created=" . toStr($e['created'] ?? '') . " updated=" . toStr($e['updated'] ?? '') . " active=" . var_export($e['active'] ?? null, true) . " | answered=$ans\n";
    }

    echo "\nORIENTATION:\n";
    $ori = $db->results->findOne(['user' => $uid, 'paper' => $paperId, 'type' => 'Orientation'], ['sort' => ['created' => -1]]);
    if ($ori) {
        echo "  created=" . toStr($ori['created'] ?? '') . "\n";
    } else {
        echo "  (aucune)\n";
    }
    echo "\n";
}
