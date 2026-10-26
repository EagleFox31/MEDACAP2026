<?php
require_once __DIR__ . '/../core/medacap_config.php';
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/export_paper_scores.php';

$paperId = new MongoDB\BSON\ObjectId('6a452db87ae27d2cc30d8592');
$db = (new MongoDB\Client(medacapMongoUri('mongodb://10.68.0.7:27017')))->academy;

foreach (['machia', 'imanol', 'dinga'] as $needle) {
    $u = null;
    foreach ($db->users->find(['active' => true, 'country' => 'Cameroun']) as $user) {
        $blob = strtolower(toStr($user['username']) . toStr($user['lastName']));
        if (strpos($blob, $needle) !== false) { $u = $user; break; }
    }
    if (!$u) continue;

    $exam = $db->exams->findOne(['user' => $u['_id'], 'paper' => $paperId], ['sort' => ['updated' => -1]]);
    $res = $db->results->findOne([
        'user' => $u['_id'], 'paper' => $paperId, 'type' => 'Factuel', 'level' => 'Mixed',
        'userAnswers' => ['$exists' => true],
    ], ['sort' => ['created' => -1]]);

    $count = function ($arr) {
        $n = 0;
        foreach (toArr($arr) as $v) {
            if ((string)$v !== '' && (string)$v !== 'null') $n++;
        }
        return $n;
    };

    echo toStr($u['username']) . "\n";
    echo "  exam updated=" . toStr($exam['updated'] ?? '') . " answers=" . $count($exam['answers'] ?? []) . " active=" . var_export($exam['active'] ?? null, true) . "\n";
    echo "  result created=" . toStr($res['created'] ?? '') . " answers=" . $count($res['userAnswers'] ?? []) . " score=" . ($res['score'] ?? '?') . "/" . ($res['total'] ?? '?') . "\n\n";
}
