<?php
require_once __DIR__ . '/../core/medacap_config.php';
/**
 * Resynchronise results PL depuis le dernier exam (quand exam > result).
 * Usage: php scripts/resync_pl2026_from_exam.php [--dry-run] [--user=machia]
 */
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/export_paper_scores.php';

use MongoDB\BSON\ObjectId;

$dryRun = in_array('--dry-run', $argv ?? [], true);
$onlyNeedle = null;
foreach ($argv ?? [] as $a) {
    if (strpos($a, '--user=') === 0) {
        $onlyNeedle = strtolower(substr($a, 7));
    }
}

$paperId = new ObjectId('6a452db87ae27d2cc30d8592');
$client = new MongoDB\Client(medacapMongoUri('mongodb://10.68.0.7:27017'));
$db = $client->academy;

function countAnsweredArr($arr): int {
    $n = 0;
    foreach (toArr($arr) as $v) {
        if ((string)$v !== '' && (string)$v !== 'null') {
            $n++;
        }
    }
    return $n;
}

function resyncFromExam($db, ObjectId $paperId, $user, bool $dryRun): array {
    $userOid = $user['_id'];
    $username = toStr($user['username'] ?? '');

    $exam = $db->exams->findOne(
        ['user' => $userOid, 'paper' => $paperId],
        ['sort' => ['updated' => -1, 'created' => -1]]
    );
    if (!$exam) {
        return ['username' => $username, 'action' => 'skip', 'reason' => 'pas d\'exam'];
    }

    $examAnswers = array_values(toArr($exam['answers'] ?? []));
    $examAnswered = countAnsweredArr($examAnswers);
    if ($examAnswered === 0) {
        return ['username' => $username, 'action' => 'skip', 'reason' => 'exam vide'];
    }

    $global = $db->results->findOne([
        'user' => $userOid,
        'paper' => $paperId,
        'type' => 'Factuel',
        'level' => 'Mixed',
        'userAnswers' => ['$exists' => true],
    ], ['sort' => ['created' => -1]]);

    $resultAnswered = $global ? countAnsweredArr($global['userAnswers'] ?? []) : 0;

    if ($examAnswered <= $resultAnswered) {
        return [
            'username' => $username,
            'action' => 'skip',
            'reason' => "exam=$examAnswered <= result=$resultAnswered",
        ];
    }

    $paper = $db->papers->findOne(['_id' => $paperId]);
    $paperQOids = [];
    if (!empty($exam['originalOrder'])) {
        foreach (toArr($exam['originalOrder']) as $x) {
            $paperQOids[] = $x instanceof ObjectId ? $x : new ObjectId((string)$x);
        }
    } else {
        foreach (toArr($paper['questions'] ?? []) as $x) {
            $paperQOids[] = $x instanceof ObjectId ? $x : new ObjectId((string)$x);
        }
    }
    if (!$paperQOids) {
        return ['username' => $username, 'action' => 'error', 'reason' => 'pas de questions'];
    }

    $qids = $paperQOids;
    $qmap = [];
    foreach ($db->questions->find(['_id' => ['$in' => $qids], 'active' => true]) as $qd) {
        $qmap[(string)$qd['_id']] = $qd;
    }

    $specQuizMap = [];
    if (!empty($paper['specQuizMap'])) {
        foreach (toArr($paper['specQuizMap']) as $spec => $qid) {
            $oid = $qid instanceof ObjectId ? $qid : new ObjectId((string)$qid);
            $specQuizMap[(string)$spec] = $oid;
        }
    }

    $timeStr = sprintf(
        '%02d:%02d:%02d',
        (int)($exam['hour'] ?? 0),
        (int)($exam['minute'] ?? 0),
        (int)($exam['second'] ?? 0)
    );
    $syncedAt = toStr($exam['updated'] ?? $exam['created'] ?? date('Y-m-d H:i:s'));

    $agg = [];
    foreach ($qids as $i => $oid) {
        $k = (string)$oid;
        if (!isset($qmap[$k])) {
            continue;
        }
        $qd = $qmap[$k];
        $spec = toStr($qd['speciality'] ?? '') ?: '(Sans spécialité)';
        if (!isset($agg[$spec])) {
            $agg[$spec] = ['quizId' => $specQuizMap[$spec] ?? null, 'score' => 0, 'questions' => [], 'answers' => []];
        }
        $ua = (string)($examAnswers[$i] ?? 'null');
        $ok = ($ua !== 'null' && $ua !== '' && $ua === toStr($qd['answer'] ?? ''));
        if ($ok) {
            $agg[$spec]['score']++;
        }
        $agg[$spec]['questions'][] = $qd['_id'];
        $agg[$spec]['answers'][] = $ok ? 'Maitrisé' : 'Non maitrisé';
    }

    $overallScore = 0;
    $overallAnswers = [];
    foreach ($qids as $i => $oid) {
        $k = (string)$oid;
        if (!isset($qmap[$k])) {
            $overallAnswers[] = 'Non maitrisé';
            continue;
        }
        $ua = (string)($examAnswers[$i] ?? 'null');
        $ok = ($ua !== 'null' && $ua !== '' && $ua === toStr($qmap[$k]['answer'] ?? ''));
        if ($ok) {
            $overallScore++;
        }
        $overallAnswers[] = $ok ? 'Maitrisé' : 'Non maitrisé';
    }

    if ($dryRun) {
        return [
            'username' => $username,
            'action' => 'would_sync',
            'exam_answered' => $examAnswered,
            'result_answered' => $resultAnswered,
            'score' => $overallScore,
            'total' => count($qids),
            'exam_updated' => $syncedAt,
        ];
    }

    // Supprimer anciens résultats Factuel (garder Orientation)
    $db->results->deleteMany([
        'user' => $userOid,
        'paper' => $paperId,
        'type' => 'Factuel',
    ]);

    foreach ($agg as $spec => $data) {
        $db->results->insertOne([
            'questions' => $data['questions'],
            'answers' => $data['answers'],
            'quiz' => $data['quizId'] ?? null,
            'user' => $userOid,
            'score' => $data['score'],
            'speciality' => $spec,
            'level' => 'Mixed',
            'type' => 'Factuel',
            'total' => count($data['questions']),
            'numberTest' => 1,
            'time' => $timeStr,
            'paper' => $paperId,
            'active' => true,
            'created' => $syncedAt,
        ]);
    }

    $db->results->insertOne([
        'questions' => $qids,
        'answers' => $overallAnswers,
        'userAnswers' => $examAnswers,
        'originalOrder' => $paperQOids,
        'user' => $userOid,
        'score' => $overallScore,
        'level' => 'Mixed',
        'type' => 'Factuel',
        'subsidiary' => $user['subsidiary'] ?? null,
        'typeR' => 'Candidat',
        'total' => count($qids),
        'numberTest' => 1,
        'time' => $timeStr,
        'paper' => $paperId,
        'active' => true,
        'created' => $syncedAt,
    ]);

    $alloc = $db->allocations->findOne(['user' => $userOid, 'paper' => $paperId]);
    if ($alloc) {
        $db->allocations->updateOne(['_id' => $alloc['_id']], ['$set' => [
            'status' => 'done',
            'active' => false,
            'finishedAt' => $syncedAt,
            'updated' => date('Y-m-d H:i:s'),
        ]]);
    }

    if (!empty($exam['active'])) {
        $db->exams->updateOne(['_id' => $exam['_id']], ['$set' => [
            'active' => false,
            'updated' => date('Y-m-d H:i:s'),
        ]]);
    }

    return [
        'username' => $username,
        'action' => 'synced',
        'exam_answered' => $examAnswered,
        'result_answered' => $resultAnswered,
        'score' => $overallScore,
        'total' => count($qids),
        'exam_updated' => $syncedAt,
    ];
}

echo ($dryRun ? "[DRY-RUN] " : '') . "Resync PL 2026 depuis exam\n\n";

$needles = $onlyNeedle ? [$onlyNeedle] : ['machia', 'imanol'];
$results = [];

foreach ($db->users->find(['active' => true, 'country' => 'Cameroun']) as $user) {
    $blob = strtolower(toStr($user['username']) . toStr($user['firstName']) . toStr($user['lastName']));
    $hit = false;
    foreach ($needles as $n) {
        if (strpos($blob, $n) !== false) {
            $hit = true;
            break;
        }
    }
    if (!$hit) {
        continue;
    }
    if (stripos(toStr($user['username']), 'demo') !== false) {
        continue;
    }

    $r = resyncFromExam($db, $paperId, $user, $dryRun);
    $results[] = $r;
    echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\nDone.\n";
