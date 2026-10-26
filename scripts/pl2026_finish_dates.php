<?php
require_once __DIR__ . '/../core/medacap_config.php';
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/export_paper_scores.php';

$paperId = new MongoDB\BSON\ObjectId('6a452db87ae27d2cc30d8592');
$client = new MongoDB\Client(medacapMongoUri('mongodb://10.68.0.7:27017'));
$db = $client->academy;
$paper = $db->papers->findOne(['_id' => $paperId]);
$paperLabel = toStr($paper['label'] ?? '');

$rows = [];
foreach ($db->users->find([
    'active' => true,
    '$or' => [
        ['papers' => $paperId],
        ['role' => $paperLabel],
        ['roles' => $paperLabel],
    ],
]) as $u) {
    $profile = strtoupper(trim(toStr($u['profile'] ?? '')));
    if (!in_array($profile, ['CANDIDAT', 'CANDIDATE'], true)) continue;
    if (stripos(toStr($u['username'] ?? ''), 'demo') !== false) continue;

    $alloc = $db->allocations->findOne(['user' => $u['_id'], 'paper' => $paperId]);
    $result = $db->results->findOne(
        ['user' => $u['_id'], 'paper' => $paperId, 'type' => 'Factuel', 'level' => 'Mixed', 'userAnswers' => ['$exists' => true]],
        ['sort' => ['created' => -1]]
    );

    $answered = 0;
    if ($result) {
        foreach (toArr($result['userAnswers'] ?? []) as $v) {
            if ((string)$v !== '' && (string)$v !== 'null') $answered++;
        }
    }
    if ($answered === 0) continue;

    $name = trim(toStr($u['firstName'] ?? '') . ' ' . toStr($u['lastName'] ?? ''));
    $rows[] = [
        'name' => $name,
        'username' => toStr($u['username'] ?? ''),
        'status' => toStr($alloc['status'] ?? ''),
        'finishedAt_alloc' => toStr($alloc['finishedAt'] ?? ''),
        'updated_alloc' => toStr($alloc['updated'] ?? ''),
        'created_alloc' => toStr($alloc['created'] ?? ''),
        'result_created' => toStr($result['created'] ?? ''),
        'answered' => $answered,
    ];
}

usort($rows, fn($a, $b) => strcmp($a['result_created'] ?: $a['finishedAt_alloc'], $b['result_created'] ?: $b['finishedAt_alloc']));

echo "Épreuve : $paperLabel\n\n";
foreach ($rows as $r) {
    $fin = $r['finishedAt_alloc'] ?: $r['result_created'] ?: $r['updated_alloc'];
    echo $r['name'] . " (" . $r['username'] . ")\n";
    echo "  Statut allocation : " . ($r['status'] ?: '—') . "\n";
    echo "  Fin composition (allocation.finishedAt) : " . ($r['finishedAt_alloc'] ?: '—') . "\n";
    echo "  Enregistrement résultat (results.created) : " . ($r['result_created'] ?: '—') . "\n";
    echo "  Questions répondues : " . $r['answered'] . "/200\n\n";
}
