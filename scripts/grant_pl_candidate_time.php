<?php
/**
 * Accorde du temps supplémentaire aux candidats PL (prorata / question QCM restante, plafonné).
 *
 * Usage:
 *   php scripts/grant_pl_candidate_time.php --dry-run
 *   php scripts/grant_pl_candidate_time.php --apply
 *
 * Options:
 *   --seconds-per-question=60     (défaut: 1 min / question)
 *   --max-bonus-minutes=105       (défaut: plafond 1 h 45)
 */
require __DIR__ . '/../vendor/autoload.php';

use MongoDB\Client;
use MongoDB\BSON\ObjectId;
use MongoDB\Model\BSONArray;

function argValue(array $argv, string $name, ?string $default = null): ?string
{
    $prefix = "--{$name}=";
    foreach ($argv as $arg) {
        if (strpos($arg, $prefix) === 0) {
            return substr($arg, strlen($prefix));
        }
        if ($arg === "--{$name}" && $name !== 'dry-run' && $name !== 'apply') {
            return '1';
        }
    }
    return $default;
}

function hasFlag(array $argv, string $name): bool
{
    return in_array("--{$name}", $argv, true);
}

function toArray($value): array
{
    if ($value instanceof BSONArray) {
        return $value->getArrayCopy();
    }
    return is_array($value) ? $value : [];
}

function count_qcm_answered(array $answers, int $qTotal): int
{
    $n = 0;
    for ($i = 0; $i < $qTotal; $i++) {
        if ((string)($answers[$i] ?? 'null') !== 'null') {
            $n++;
        }
    }
    return $n;
}

function timer_to_seconds(int $hr, int $mn, int $sc): int
{
    return max(0, ($hr * 3600) + ($mn * 60) + $sc);
}

function seconds_to_timer(int $seconds): array
{
    $seconds = max(0, $seconds);
    $hr = intdiv($seconds, 3600);
    $seconds %= 3600;
    $mn = intdiv($seconds, 60);
    $sc = $seconds % 60;
    return ['hour' => $hr, 'minute' => $mn, 'second' => $sc];
}

function fmt_timer(int $seconds): string
{
    $t = seconds_to_timer($seconds);
    return sprintf('%02d:%02d:%02d', $t['hour'], $t['minute'], $t['second']);
}

function resolve_answers($db, $userOid, $paperOid, $exam, int $qTotal): array
{
    $fromExam = [];
    if ($exam && isset($exam['answers'])) {
        $fromExam = array_values(toArray($exam['answers']));
    }

    $fromResults = [];
    $r = $db->results->findOne(
        [
            'user' => $userOid,
            'paper' => $paperOid,
            'type' => 'Factuel',
            'level' => 'Mixed',
            'userAnswers' => ['$exists' => true],
        ],
        ['sort' => ['created' => -1]]
    );
    if ($r && isset($r['userAnswers'])) {
        $fromResults = array_values(toArray($r['userAnswers']));
    }

    $answers = count_qcm_answered($fromExam, $qTotal) >= count_qcm_answered($fromResults, $qTotal)
        ? $fromExam
        : $fromResults;

    while (count($answers) < $qTotal) {
        $answers[] = 'null';
    }
    return array_slice($answers, 0, $qTotal);
}

function resolve_q_total($paper, $questions): int
{
    if (isset($paper['counts']['QCM_total'])) {
        return (int)$paper['counts']['QCM_total'];
    }
    $ids = [];
    foreach (toArray($paper['questions'] ?? []) as $x) {
        if ($x instanceof ObjectId) {
            $ids[] = $x;
        } elseif (is_array($x) && isset($x['$oid'])) {
            $ids[] = new ObjectId((string)$x['$oid']);
        } elseif (is_string($x) && preg_match('/^[0-9a-f]{24}$/i', $x)) {
            $ids[] = new ObjectId($x);
        }
    }
    if (empty($ids)) {
        return 0;
    }
    return (int)$questions->countDocuments(['_id' => ['$in' => $ids], 'active' => true]);
}

function pick_exam($db, $userOid, $paperOid)
{
    $active = $db->exams->findOne(['user' => $userOid, 'paper' => $paperOid, 'active' => true]);
    if ($active) {
        return $active;
    }
    return $db->exams->findOne(
        ['user' => $userOid, 'paper' => $paperOid],
        ['sort' => ['updated' => -1, 'created' => -1]]
    );
}

$dryRun = hasFlag($argv, 'dry-run') || !hasFlag($argv, 'apply');
$mongoUri = (string)(argValue($argv, 'mongo-uri', 'mongodb://10.68.0.7:27017') ?? 'mongodb://10.68.0.7:27017');
$paperId = (string)(argValue($argv, 'paper-id', '6a452db87ae27d2cc30d8592') ?? '6a452db87ae27d2cc30d8592');
$secondsPerQuestion = max(1, (int)(argValue($argv, 'seconds-per-question', '60') ?? 60));
$maxBonusMinutes = max(1, (int)(argValue($argv, 'max-bonus-minutes', '105') ?? 105));
$maxBonusSeconds = $maxBonusMinutes * 60;
$userFilter = trim((string)(argValue($argv, 'user', '') ?? ''));
$reopen = (argValue($argv, 'reopen', '1') ?? '1') !== '0';
$includeDemo = (argValue($argv, 'include-demo', '0') ?? '0') === '1';

if (!preg_match('/^[0-9a-f]{24}$/i', $paperId)) {
    fwrite(STDERR, "ERROR: invalid --paper-id\n");
    exit(1);
}

$client = new Client($mongoUri);
$db = $client->academy;
$paperOid = new ObjectId($paperId);
$paper = $db->papers->findOne(['_id' => $paperOid]);
if (!$paper) {
    fwrite(STDERR, "ERROR: paper not found\n");
    exit(1);
}

$qTotal = resolve_q_total($paper, $db->questions);
echo ($dryRun ? "[DRY-RUN] " : "[APPLY] ") . 'Paper: ' . ($paper['label'] ?? $paperId)
    . " | QCM total={$qTotal} | +{$secondsPerQuestion}s/question | max "
    . fmt_timer($maxBonusSeconds) . "\n\n";

$allocations = iterator_to_array($db->allocations->find(['paper' => $paperOid]));
if (empty($allocations)) {
    echo "No allocations on this paper.\n";
    exit(0);
}

$processed = 0;
foreach ($allocations as $alloc) {
    $user = $db->users->findOne(['_id' => $alloc['user']]);
    if (!$user) {
        continue;
    }

    $username = (string)($user['username'] ?? '');
    $fullName = trim((string)($user['firstName'] ?? '') . ' ' . (string)($user['lastName'] ?? ''));
    $blob = strtolower($username . ' ' . $fullName);

    if (!$includeDemo && strpos($blob, 'demo') !== false) {
        continue;
    }
    if ($userFilter !== '' && stripos($blob, strtolower($userFilter)) === false) {
        continue;
    }

    $exam = pick_exam($db, $alloc['user'], $paperOid);
    if (!$exam) {
        echo "- SKIP {$username} | {$fullName} : aucun examen trouvé\n";
        continue;
    }

    $answers = resolve_answers($db, $alloc['user'], $paperOid, $exam, $qTotal);
    $answered = count_qcm_answered($answers, $qTotal);
    $remaining = max(0, $qTotal - $answered);
    if ($remaining <= 0) {
        echo "- SKIP {$username} | {$fullName} : 0 question restante\n";
        continue;
    }

    $rawBonusSeconds = $remaining * $secondsPerQuestion;
    $bonusSeconds = min($rawBonusSeconds, $maxBonusSeconds);
    $currentSeconds = timer_to_seconds(
        (int)($exam['hour'] ?? 0),
        (int)($exam['minute'] ?? 0),
        (int)($exam['second'] ?? 0)
    );
    $allocStatus = (string)($alloc['status'] ?? '');
    // Le temps RH remplace le chrono (pas de cumul avec un ancien bonus).
    $newSeconds = $bonusSeconds;

    $newTimer = seconds_to_timer($newSeconds);
    $newStatus = $allocStatus;
    if ($reopen && in_array($allocStatus, ['done', 'awaiting_orientation', 'awaiting_catchup_choice'], true)) {
        $newStatus = 'in_progress';
    } elseif ($allocStatus === 'catchup_qcm') {
        $newStatus = 'catchup_qcm';
    } elseif ($allocStatus === '' || $allocStatus === 'not_started') {
        $newStatus = 'in_progress';
    }

    echo "- {$username} | {$fullName}\n";
    echo "    allocation: {$allocStatus}" . ($newStatus !== $allocStatus ? " -> {$newStatus}" : '') . "\n";
    echo "    QCM: {$answered}/{$qTotal} répondues, {$remaining} restantes\n";
    echo "    timer: " . fmt_timer($currentSeconds) . ' -> ' . fmt_timer($newSeconds)
        . ' (' . round($bonusSeconds / 60) . ' min';
    if ($rawBonusSeconds > $maxBonusSeconds) {
        echo ', plafonné à ' . $maxBonusMinutes . ' min';
    }
    echo ")\n";

    if (!$dryRun) {
        $now = date('Y-m-d H:i:s');
        $examSet = [
            'active' => true,
            'hour' => $newTimer['hour'],
            'minute' => $newTimer['minute'],
            'second' => $newTimer['second'],
            'answers' => $answers,
            'updated' => $now,
            'lockQcm' => false,
            'pendingCatchupChoice' => false,
            'catchupMode' => false,
            'catchupIndices' => [],
            'rhTimeGrant' => [
                'at' => $now,
                'secondsPerQuestion' => $secondsPerQuestion,
                'maxBonusMinutes' => $maxBonusMinutes,
                'remainingQuestions' => $remaining,
                'rawBonusSeconds' => $rawBonusSeconds,
                'bonusSeconds' => $bonusSeconds,
                'previousAllocationStatus' => $allocStatus,
                'previousTimerSeconds' => $currentSeconds,
            ],
        ];
        if (!isset($exam['originalOrder']) || empty(toArray($exam['originalOrder'] ?? []))) {
            $examSet['originalOrder'] = toArray($paper['questions'] ?? []);
        }

        $db->exams->updateOne(['_id' => $exam['_id']], ['$set' => $examSet]);

        $allocSet = [
            'active' => true,
            'status' => $newStatus,
            'updated' => $now,
            'rhTimeGrantAt' => $now,
        ];
        if ($newStatus === 'in_progress') {
            $allocSet['finishedAt'] = null;
        }
        $db->allocations->updateOne(['_id' => $alloc['_id']], ['$set' => $allocSet]);

        // Désactiver les autres exams actifs du même paper (sécurité)
        $db->exams->updateMany(
            ['user' => $alloc['user'], 'paper' => $paperOid, '_id' => ['$ne' => $exam['_id']], 'active' => true],
            ['$set' => ['active' => false, 'updated' => $now]]
        );

        echo "    => APPLIQUÉ\n";
    }

    echo "\n";
    $processed++;
}

echo $dryRun
    ? "Dry-run terminé ({$processed} candidat(s)). Relancer avec --apply pour exécuter.\n"
    : "Terminé ({$processed} candidat(s) mis à jour).\n";
