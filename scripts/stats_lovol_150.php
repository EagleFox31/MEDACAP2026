<?php
require __DIR__ . '/../vendor/autoload.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use MongoDB\Client;

$uri = getenv('MEDACAP_MONGO_URI') ?: 'mongodb://localhost:27017';
$db = (new Client($uri))->academy;

// Dernier paper LOVOL avec ~200 QCM
$paper = $db->papers->findOne(
    [
        'active' => true,
        'label' => new Regex('LOVOL', 'i'),
        'counts.QCM_total' => ['$gte' => 190],
    ],
    ['sort' => ['created' => -1]]
);

if (!$paper) {
    // fallback: any recent LOVOL recruitment
    $paper = $db->papers->findOne(
        ['active' => true, 'label' => new Regex('Recrutement LOVOL', 'i')],
        ['sort' => ['created' => -1]]
    );
}

if (!$paper) {
    echo "Aucun paper LOVOL trouvé.\n";
    exit(1);
}

$paperId = $paper['_id'];
$paperOid = $paperId instanceof ObjectId ? $paperId : new ObjectId((string)$paperId);
$qTotal = (int)($paper['counts']['QCM_total'] ?? 0);
if ($qTotal <= 0) {
    $raw = $paper['questions'] ?? [];
    if ($raw instanceof MongoDB\Model\BSONArray) $raw = $raw->getArrayCopy();
    $qTotal = is_array($raw) ? count($raw) : 0;
}

echo "Paper : " . ($paper['label'] ?? '?') . "\n";
echo "ID    : $paperOid\n";
echo "QCM total paper : $qTotal\n\n";

// Candidats via allocations + exams
$exams = $db->exams->find(
    ['paper' => $paperOid],
    ['projection' => ['user' => 1, 'total' => 1, 'active' => 1, 'created' => 1, 'updated' => 1]]
)->toArray();

$byUser = [];
foreach ($exams as $ex) {
    $uid = (string)$ex['user'];
    $answered = (int)($ex['total'] ?? 0);
    if (!isset($byUser[$uid]) || $answered > $byUser[$uid]) {
        $byUser[$uid] = $answered;
    }
}

// Allocations done / in progress sans exam ou total=0 — compter depuis results Factuel
$allocations = $db->allocations->find(
    ['paper' => $paperOid],
    ['projection' => ['user' => 1, 'status' => 1]]
)->toArray();

$allocUsers = [];
foreach ($allocations as $a) {
    $allocUsers[(string)$a['user']] = (string)($a['status'] ?? '');
}

// Compléter depuis results si exam manquant
foreach ($allocUsers as $uid => $status) {
    if (isset($byUser[$uid]) && $byUser[$uid] > 0) {
        continue;
    }
    $userOid = new ObjectId($uid);
    $r = $db->results->findOne(
        ['user' => $userOid, 'paper' => $paperOid, 'type' => 'Factuel'],
        ['sort' => ['created' => -1], 'projection' => ['answers' => 1]]
    );
    if ($r && is_array($r['answers'] ?? null)) {
        $byUser[$uid] = count($r['answers']);
    } elseif (!isset($byUser[$uid])) {
        $byUser[$uid] = 0;
    }
}

$totalCandidates = count($allocUsers);
if ($totalCandidates === 0) {
    $totalCandidates = count($byUser);
}

$reached150 = 0;
$reached200 = 0;
$dist = ['0' => 0, '1-49' => 0, '50-99' => 0, '100-149' => 0, '150-199' => 0, '200+' => 0];

foreach ($byUser as $uid => $n) {
    if ($n >= 150) $reached150++;
    if ($n >= 200) $reached200++;
    if ($n === 0) $dist['0']++;
    elseif ($n < 50) $dist['1-49']++;
    elseif ($n < 100) $dist['50-99']++;
    elseif ($n < 150) $dist['100-149']++;
    elseif ($n < 200) $dist['150-199']++;
    else $dist['200+']++;
}

// Users with allocation but no exam entry
$noExam = 0;
foreach ($allocUsers as $uid => $_) {
    if (!isset($byUser[$uid])) {
        $noExam++;
        $byUser[$uid] = 0;
        $dist['0']++;
    }
}
$totalCandidates = max($totalCandidates, count($byUser));

echo "Candidats avec allocation sur ce paper : $totalCandidates\n";
echo "Candidats avec données exam/results     : " . count($byUser) . "\n";
echo "Sans réponse enregistrée (0)            : " . ($dist['0']) . "\n\n";

echo "=== Seuils ===\n";
echo "≥ 150 questions : $reached150 / $totalCandidates";
if ($totalCandidates > 0) {
    echo " (" . round(100 * $reached150 / $totalCandidates, 1) . "%)";
}
echo "\n";
echo "≥ 200 questions : $reached200 / $totalCandidates";
if ($totalCandidates > 0) {
    echo " (" . round(100 * $reached200 / $totalCandidates, 1) . "%)";
}
echo "\n\n";

echo "=== Répartition réponses QCM ===\n";
foreach ($dist as $bucket => $cnt) {
    echo "  $bucket : $cnt\n";
}

// Top / bottom sample
arsort($byUser);
echo "\n=== Top 5 ===\n";
$i = 0;
foreach ($byUser as $uid => $n) {
    if ($i++ >= 5) break;
    $u = $db->users->findOne(['_id' => new ObjectId($uid)], ['projection' => ['username' => 1, 'firstName' => 1, 'lastName' => 1]]);
    $name = $u ? (($u['firstName'] ?? '') . ' ' . ($u['lastName'] ?? '') . ' (' . ($u['username'] ?? '') . ')') : $uid;
    echo "  $n rép. — $name\n";
}
