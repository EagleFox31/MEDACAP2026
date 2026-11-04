<?php
require_once __DIR__ . '/../../core/medacap_config.php';
// ============================================================================
// PAGE CANDIDAT (Cameroun) — Épreuve Figée (Paper) Factuel Mixed
// QCM 1/1 + ORIENTATION comme pages #N+1, N+2 (ex: 61 & 62)
// - Timer 1h, SAVE/VALID, reprise
// - Résultats QCM + Orientation
// - Si QCM validé mais orientation incomplète => 'awaiting_orientation'
// - MODE REVUE: ?review=1 OU allocation.status=done
//   -> recharge la DERNIÈRE version (exam récent ou results.userAnswers)
//   -> lecture seule (lockQcm + lockOrientation)
// - TIMEUP: à 0, auto-validate + redirection vers congrat
// ============================================================================

session_start();
if (isset($_GET['lang']) && ($_GET['lang'] === 'EN' || $_GET['lang'] === 'FR')) {
  $_SESSION['lang'] = $_GET['lang'];
}
$lang = $_SESSION['lang'] ?? 'FR';
if ($lang === 'EN') {
  include_once "../language_EN.php";
} else {
  include_once "../language.php";
}
include_once "../partials/background-manager.php";
setPageBackground("bg-dashboard");

if (!isset($_SESSION["id"])) { header("Location: ../../"); exit(); }

require_once "../../vendor/autoload.php";
require_once "../sendMail.php";

use MongoDB\Client;
use MongoDB\BSON\ObjectId;
use MongoDB\Model\BSONArray;

/* ---------------- Helpers ---------------- */
function qs_get(string $k, ?string $d=null): ?string {
  if (!isset($_GET[$k])) return $d;
  $v = is_string($_GET[$k]) ? $_GET[$k] : null;
  if ($v===null) return $d;
  $v = trim($v);
  return $v==='' ? $d : $v;
}
function post_str(string $k, ?string $d=null): ?string {
  if (!isset($_POST[$k])) return $d;
  $v = is_string($_POST[$k]) ? $_POST[$k] : null;
  if ($v===null) return $d;
  $v = trim($v);
  return $v==='' ? $d : $v;
}
function post_array(string $k): array {
  $v = $_POST[$k] ?? [];
  if ($v instanceof Traversable) return iterator_to_array($v);
  return is_array($v) ? $v : [];
}
function to_oid(?string $id): ?ObjectId {
  try { return ($id && preg_match('/^[0-9a-f]{24}$/i', $id)) ? new ObjectId($id) : null; }
  catch (\Throwable $e) { return null; }
}
function collect_aligned_answers(array $post, int $n, array $baseAnswers = []): array {
  $out = !empty($baseAnswers) ? array_values($baseAnswers) : array_fill(0, $n, 'null');
  if (count($out) < $n) {
    $out = array_pad($out, $n, 'null');
  }
  $tmp = [];
  foreach ($post as $k => $v) {
    if (!is_string($k)) continue;
    if (preg_match('/^answer(\d+)$/', $k, $m)) {
      $idx = (int)$m[1];
      if ($idx < $n) {
        $out[$idx] = is_string($v) ? $v : 'null';
      }
    }
  }
  return $out;
}
function col_count(MongoDB\Collection $col, array $filter): int {
  if (method_exists($col,'countDocuments')) return $col->countDocuments($filter);
  if (method_exists($col,'count')) return (int)$col->count($filter);
  return iterator_count($col->find($filter,['projection'=>['_id'=>1]]));
}
function to_php_array($maybeBsonArray): array {
  if ($maybeBsonArray instanceof BSONArray) return $maybeBsonArray->getArrayCopy();
  return is_array($maybeBsonArray) ? $maybeBsonArray : [];
}
function orientation_complete(array $orientationDocs, array $answersMap): bool {
  foreach ($orientationDocs as $od) {
    $oid = (string)$od['_id'];
    $min = (int)($od['minSelect'] ?? 0);
    $max = (int)($od['maxSelect'] ?? 0);
    $opts = to_php_array($od['options'] ?? []);
    $sel  = isset($answersMap[$oid]) ? array_values(array_intersect((array)$answersMap[$oid], $opts)) : [];
    if ($min>0 && count($sel) < $min) return false;
    if ($max>0 && count($sel) > $max) return false;
  }
  return true;
}

/** Normalise une réponse orientation (radio = string, checkbox = array) */
function parse_orientation_chosen($raw, array $opts): array {
  if (is_array($raw)) {
    return array_values(array_intersect(
      array_values(array_filter($raw, 'is_string')),
      $opts
    ));
  }
  if (is_string($raw) && $raw !== '' && in_array($raw, $opts, true)) {
    return [$raw];
  }
  return [];
}
function quiz_image_src(string $filename): string {
  $filename = trim($filename);
  if ($filename === '') return '';
  $src = '../../public/files/' . $filename;
  $path = __DIR__ . '/../../public/files/' . $filename;
  if (is_file($path)) $src .= '?v=' . filemtime($path);
  return $src;
}

/** Secondes restantes sur le chrono candidat */
function timer_to_seconds(int $hr, int $mn, int $sc): int {
  return max(0, $hr * 3600 + $mn * 60 + $sc);
}

/** Nb de QCM sans réponse (valeur "null") */
function count_unanswered_qcm_answers(array $answers, int $total): int {
  $n = 0;
  for ($i = 0; $i < $total; $i++) {
    if ((string)($answers[$i] ?? 'null') === 'null') {
      $n++;
    }
  }
  return $n;
}

/** Indices des N premières questions QCM non répondues */
function pick_catchup_indices(array $answers, int $limit): array {
  $out = [];
  $limit = max(0, $limit);
  for ($i = 0; $i < count($answers) && count($out) < $limit; $i++) {
    if ((string)($answers[$i] ?? 'null') === 'null') {
      $out[] = $i;
    }
  }
  return $out;
}

/** Réponses QCM courantes (exam actif, sinon dernier résultat) */
function resolve_qcm_answers_snapshot(
  ?array $examArray,
  MongoDB\Collection $results,
  $userOid,
  $paperOid,
  array $paperQOids
): array {
  $total = count($paperQOids);
  $out = array_fill(0, $total, 'null');
  if ($total === 0) {
    return $out;
  }

  $fromExam = is_array($examArray['answers'] ?? null) ? array_values($examArray['answers']) : [];
  foreach ($fromExam as $i => $v) {
    if ($i >= $total) {
      break;
    }
    $out[$i] = (string)$v;
  }

  if (count($fromExam) >= $total) {
    return $out;
  }

  $res = $results->findOne(
    [
      'user' => $userOid,
      'paper' => $paperOid,
      'type' => 'Factuel',
      'level' => 'Mixed',
      'userAnswers' => ['$exists' => true],
    ],
    ['sort' => ['created' => -1]]
  );
  if ($res && isset($res['userAnswers'])) {
    $fromRes = array_values((array)$res['userAnswers']);
    foreach ($fromRes as $i => $v) {
      if ($i >= $total) {
        break;
      }
      if ((string)$out[$i] === 'null') {
        $out[$i] = (string)$v;
      }
    }
  }

  return $out;
}

/**
 * Propose le rattrapage QCM si du temps et des questions non répondues restent.
 * @return int Nombre de questions proposées, 0 si pas d'offre.
 */
function try_offer_catchup_choice(
  MongoDB\Collection $exams,
  MongoDB\Collection $allocations,
  $exam,
  $allocation,
  array $examArray,
  array $orientationAnswers,
  int $hr,
  int $mn,
  int $sc,
  MongoDB\Collection $results,
  $userOid,
  $paperOid,
  array $paperQOids
): int {
  if (!$exam) {
    return 0;
  }

  $savedAnswers = resolve_qcm_answers_snapshot($examArray, $results, $userOid, $paperOid, $paperQOids);
  $remainingSec = timer_to_seconds($hr, $mn, $sc);
  $total = count($paperQOids);
  $unanswered = count_unanswered_qcm_answers($savedAnswers, $total);

  if ($remainingSec < CATCHUP_SECONDS_PER_QUESTION || $unanswered <= 0) {
    return 0;
  }

  $catchupOffer = min($unanswered, (int)floor($remainingSec / CATCHUP_SECONDS_PER_QUESTION));
  if ($catchupOffer <= 0) {
    return 0;
  }

  $exams->updateOne(['_id' => $exam['_id']], ['$set' => [
    'orientationAnswers' => $orientationAnswers,
    'pendingCatchupChoice' => true,
    'catchupMaxOffered' => $catchupOffer,
    'answers' => $savedAnswers,
    'hour' => $hr,
    'minute' => $mn,
    'second' => $sc,
    'updated' => date('Y-m-d H:i:s'),
  ]]);
  if ($allocation) {
    $allocations->updateOne(
      ['_id' => $allocation['_id']],
      ['$set' => ['status' => 'awaiting_catchup_choice', 'active' => true]]
    );
  }

  return $catchupOffer;
}

function persist_orientation_results(
  MongoDB\Collection $results,
  $userOid,
  $paperOid,
  array $orientationDocs,
  array $orientationAnswers
): void {
  $payload = [];
  foreach ($orientationDocs as $od) {
    $oid = (string)$od['_id'];
    $payload[] = [
      'id' => $oid,
      'title' => (string)$od['title'],
      'minSelect' => (int)($od['minSelect'] ?? 0),
      'maxSelect' => (int)($od['maxSelect'] ?? 0),
      'selected' => $orientationAnswers[$oid] ?? [],
    ];
  }
  $exists = $results->findOne([
    'user' => $userOid,
    'paper' => $paperOid,
    'type' => 'Orientation',
    'level' => 'Mixed',
  ]);
  if ($exists) {
    $results->updateOne(
      ['_id' => $exists['_id']],
      ['$set' => ['answers' => $payload, 'updated' => date('Y-m-d H:i:s')]]
    );
  } else {
    $results->insertOne([
      'user' => $userOid,
      'paper' => $paperOid,
      'type' => 'Orientation',
      'level' => 'Mixed',
      'answers' => $payload,
      'active' => true,
      'created' => date('Y-m-d H:i:s'),
    ]);
  }
}

function finalize_exam_close(
  MongoDB\Collection $allocations,
  MongoDB\Collection $exams,
  $allocation,
  $exam,
  $db = null,
  $paperOid = null
): void {
  if ($allocation) {
    $allocations->updateOne(
      ['_id' => $allocation['_id']],
      ['$set' => ['status' => 'done', 'active' => false, 'finishedAt' => date('Y-m-d H:i:s')]]
    );
  }
  if ($exam) {
    $exams->updateOne(
      ['_id' => $exam['_id']],
      ['$set' => [
        'active' => false,
        'pendingCatchupChoice' => false,
        'catchupMode' => false,
        'updated' => date('Y-m-d H:i:s'),
      ]]
    );
  }
  if ($db !== null && $paperOid !== null) {
    require_once __DIR__ . '/includes/paper_candidate_report_lib.php';
    paperReportMaybeSendThresholdAlert($db, $paperOid);
  }
}

function apply_catchup_answers_to_results(
  MongoDB\Collection $results,
  MongoDB\Collection $questions,
  $userOid,
  $paperOid,
  array $qids,
  array $allAnswers,
  array $catchupIndices
): void {
  $res = $results->findOne([
    'user' => $userOid,
    'paper' => $paperOid,
    'type' => 'Factuel',
    'level' => 'Mixed',
    'userAnswers' => ['$exists' => true],
  ]);
  if (!$res) {
    return;
  }

  $qmap = [];
  $qc = $questions->find(['_id' => ['$in' => $qids], 'active' => true]);
  foreach ($qc as $qd) {
    $qmap[(string)$qd['_id']] = $qd;
  }

  $userAnswers = (array)($res['userAnswers'] ?? []);
  $gradedAnswers = (array)($res['answers'] ?? []);

  foreach ($catchupIndices as $i) {
    $ua = (string)($allAnswers[$i] ?? 'null');
    $userAnswers[$i] = $ua;
    $k = (string)($qids[$i] ?? '');
    if ($k !== '' && isset($qmap[$k])) {
      $ok = ($ua === (string)($qmap[$k]['answer'] ?? ''));
      $gradedAnswers[$i] = $ok ? 'Maitrisé' : 'Non maitrisé';
    }
  }

  $score = 0;
  foreach ($qids as $i => $oid) {
    $k = (string)$oid;
    if (!isset($qmap[$k])) {
      continue;
    }
    if (((string)($userAnswers[$i] ?? 'null')) === (string)($qmap[$k]['answer'] ?? '')) {
      $score++;
    }
  }

  $results->updateOne(
    ['_id' => $res['_id']],
    ['$set' => [
      'userAnswers' => $userAnswers,
      'answers' => $gradedAnswers,
      'score' => $score,
      'updated' => date('Y-m-d H:i:s'),
    ]]
  );
}

/** 1 minute par question de rattrapage QCM */
const CATCHUP_SECONDS_PER_QUESTION = 60;

/* ---------------- Mongo ---------------- */
$client   = new Client(medacapMongoUri());
$db       = $client->academy;

$users         = $db->users;
$papers        = $db->papers;
$questions     = $db->questions;
$quizzesCol    = $db->quizzes;
$results       = $db->results;
$exams         = $db->exams;
$allocations   = $db->allocations;
$orientCol     = $db->orientation_questions;
$quizQuestionReports = $db->quiz_question_reports;

/* ---------------- Inputs ---------------- */
$userId   = (string)($_SESSION["id"] ?? '');
$paperArg = qs_get('paper');
if (($paperArg === null || $paperArg === '') && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $paperArg = post_str('paper');
}
$userArg  = qs_get('id'); // compat

$userOid  = to_oid($userId ?: $userArg);
$paperOid = to_oid($paperArg);

if (!$userOid || !$paperOid) { http_response_code(400); echo "Paramètres invalides."; exit(); }

/* ---------------- User & Access Control (Cameroun + Candidat) ---------------- */
$technician = $users->findOne(['_id'=>$userOid, 'active'=>true]);
if (!$technician) { http_response_code(403); echo "Utilisateur introuvable."; exit(); }
$country = strtoupper(trim((string)($technician['country'] ?? '')));
$profile = strtoupper(trim((string)($technician['profile'] ?? ($technician['typeR'] ?? ''))));
$isCameroon  = in_array($country, ['CAMEROUN','CAMEROON'], true);
$isCandidate = in_array($profile, ['CANDIDAT','CANDIDATE'], true);
if (!$isCameroon || !$isCandidate) { http_response_code(403); echo "Cette page est réservée aux candidats du Cameroun."; exit(); }

$candidateFullName = trim(
  (string)($technician['firstName'] ?? '') . ' ' . (string)($technician['lastName'] ?? '')
);
if ($candidateFullName === '') {
  $candidateFullName = (string)($technician['username'] ?? 'Candidat');
}

/* ---------------- Paper ---------------- */
$paper = $papers->findOne(['_id'=>$paperOid, 'active'=>true, 'type'=>['$in'=>['Factuel','Factuelle']], 'mode'=>'mixed']);
if (!$paper) { http_response_code(404); echo "Épreuve figée introuvable."; exit(); }

/* Signalements image déjà enregistrés pour cette épreuve (tous candidats) */
$paperReportedQuestionIds = [];
try {
  $reportCursor = $quizQuestionReports->find(
    ['paperId' => (string)$paperOid],
    ['projection' => ['questionId' => 1]]
  );
  foreach ($reportCursor as $rdoc) {
    $rqid = (string)($rdoc['questionId'] ?? '');
    if ($rqid !== '') {
      $paperReportedQuestionIds[$rqid] = true;
    }
  }
} catch (Throwable $e) {
  // collection absente ou indisponible — on continue sans bloquer le quiz
}

/* QCM IDs avec ordre randomisé par utilisateur */
$paperQ = to_php_array($paper['questions'] ?? []);
$paperQOids = [];
foreach ($paperQ as $x) {
  $oid = ($x instanceof ObjectId)? $x : (is_array($x) && isset($x['$oid']) ? to_oid((string)$x['$oid']) : (is_string($x)? to_oid($x) : null));
  if ($oid) $paperQOids[] = $oid;
}
if (empty($paperQOids)) { http_response_code(400); echo "Paper sans questions."; exit(); }

// Randomisation basée sur l'ID utilisateur + ID paper pour consistance
$seed = crc32($userId . '_' . $paperArg);
mt_srand($seed);
shuffle($paperQOids);
mt_srand(); // Reset du générateur aléatoire

/* Orientation IDs -> docs (ordre conservé) */
$orientationIdsRaw = to_php_array($paper['orientation'] ?? []);
$orientationIds = [];
foreach ($orientationIdsRaw as $x) {
  $oid = ($x instanceof ObjectId)? $x : (is_array($x)&&isset($x['$oid'])? to_oid((string)$x['$oid']) : (is_string($x)? to_oid($x):null));
  if ($oid) $orientationIds[] = $oid;
}
$orientationDocs = [];
if (!empty($orientationIds)) {
  $byId=[]; $oc=$orientCol->find(['_id'=>['$in'=>$orientationIds],'active'=>true]);
  foreach ($oc as $d) $byId[(string)$d['_id']]=$d;
  foreach ($orientationIds as $oid){ $k=(string)$oid; if(isset($byId[$k])) $orientationDocs[]=$byId[$k]; }
}

/* Map spécialité → quiz (si présent dans paper) */
$specQuizMap = [];
if (!empty($paper['specQuizMap']) && is_array($paper['specQuizMap'])) {
  foreach ($paper['specQuizMap'] as $spec=>$qid) {
    $oid = ($qid instanceof ObjectId)? $qid : (is_array($qid) && isset($qid['$oid']) ? to_oid((string)$qid['$oid']) : (is_string($qid)? to_oid($qid) : null));
    if ($oid) $specQuizMap[(string)$spec] = $oid;
  }
}

/* ---------------- Allocation & Modes ---------------- */
$allocation = $allocations->findOne(['user'=>$userOid,'paper'=>$paperOid]);
$modeOrientationOnly = ($allocation && (($allocation['status'] ?? '') === 'awaiting_orientation'));
$reviewModeParam = (qs_get('review','') === '1');
$reviewMode = $reviewModeParam || (($allocation['status'] ?? '') === 'done');

/* ---------------- EXAM reprise / revue ---------------- */
$exam = $exams->findOne(['user'=>$userOid,'paper'=>$paperOid,'active'=>true]);
$examArray = $exam ? json_decode(json_encode($exam), true) : null;

/* Si pas d'exam actif et en mode revue -> charger le DERNIER EXAM (même inactif) */
if (!$examArray && $reviewMode) {
  $lastExam = $exams->findOne(['user'=>$userOid,'paper'=>$paperOid], ['sort'=>['updated'=>-1,'created'=>-1]]);
  if ($lastExam) $examArray = json_decode(json_encode($lastExam), true);
}

/* Si toujours rien et en mode revue -> charger le DERNIER RESULTAT VALIDÉ (userAnswers) */
if ((!$examArray || empty($examArray['answers'])) && $reviewMode) {
  $res = $results->findOne(
    ['user'=>$userOid,'paper'=>$paperOid,'type'=>'Factuel','level'=>'Mixed','userAnswers'=>['$exists'=>true]],
    ['sort'=>['created'=>-1]]
  );
  if ($res && isset($res['userAnswers'])) {
    $examArray = is_array($examArray) ? $examArray : [];
    $examArray['answers'] = (array)$res['userAnswers'];
    $examArray['hour']=0; $examArray['minute']=0; $examArray['second']=0;
  }
}
/* Si on reprend un examen, utiliser l'ordre sauvegardé plutôt que de re-randomiser */
if ($examArray && !empty($examArray['originalOrder'])) {
    $savedOrder = [];
    foreach ($examArray['originalOrder'] as $x) {
        $oid = ($x instanceof ObjectId)? $x : (is_array($x) && isset($x['$oid']) ? to_oid((string)$x['$oid']) : (is_string($x)? to_oid($x) : null));
        if ($oid) $savedOrder[] = $oid;
    }
    if (!empty($savedOrder)) {
        $paperQOids = $savedOrder;
    }
}

/* État rattrapage QCM (après orientation anticipée) */
$catchupMode = $examArray ? (bool)($examArray['catchupMode'] ?? false) : false;
$pendingCatchupChoice = $examArray ? (bool)($examArray['pendingCatchupChoice'] ?? false) : false;
$catchupIndices = [];
if ($examArray && !empty($examArray['catchupIndices']) && is_array($examArray['catchupIndices'])) {
  $catchupIndices = array_values(array_map('intval', $examArray['catchupIndices']));
}
$catchupMaxOffered = $examArray ? (int)($examArray['catchupMaxOffered'] ?? 0) : 0;
if ($catchupMode || $pendingCatchupChoice) {
  $modeOrientationOnly = false;
}

/* ---------------- Pondération affichage (stat sidebar) ---------------- */
$specWeights = [];
try {
  $qcur = $questions->find(['_id'=>['$in'=>$paperQOids], 'active'=>true], ['projection'=>['_id'=>1,'speciality'=>1]]);
  $countBySpec=[];
  foreach ($qcur as $qd) {
    $sp=(string)($qd['speciality']??''); if ($sp==='') continue;
    $countBySpec[$sp]=($countBySpec[$sp]??0)+1;
  }
  $tot=array_sum($countBySpec);
  if ($tot>0) {
    foreach ($countBySpec as $sp=>$c) $specWeights[$sp]=(int)round(($c*100)/$tot);
    $delta=100-array_sum($specWeights);
    if ($delta!==0) { $keys=array_keys($specWeights); $i=0; $d=abs($delta);
      while($d>0 && !empty($keys)){ $k=$keys[$i%count($keys)]; $specWeights[$k]+= ($delta>0?1:-1); $i++; $d--; } }
  }
} catch (\Throwable $e) { /* no-op */ }

/* ---------------- SAVE / VALID / TIMEUP ---------------- */
$error_msg = null;
$first_missing_idx = null;
$first_unanswered_idx = null;
$orientationStartIndex = count($paperQOids);
if (qs_get('err', '') === 'orientation_missing') {
  $error_msg = "Veuillez répondre aux questions de préférences pour finaliser votre test.";
}
/* En mode revue: on ignore toute soumission (lecture seule) */
if ($reviewMode && $_SERVER['REQUEST_METHOD']==='POST') {
  header("Location: ".$_SERVER['REQUEST_URI']); exit;
}

if (!$reviewMode && $_SERVER['REQUEST_METHOD']==='POST') {

  // === SIGNALEMENT image manquante (AJAX) ===
  if (isset($_POST['report_question'])) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/json; charset=UTF-8');
    try {
      $reportQid = post_str('question_id', '');
      $reportOid = to_oid($reportQid);
      $paperQidSet = [];
      foreach ($paperQOids as $poid) {
        $paperQidSet[(string)$poid] = true;
      }

      if (!$reportOid || !isset($paperQidSet[(string)$reportOid])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Question invalide.']);
        exit;
      }

      $qdoc = $questions->findOne(['_id' => $reportOid], ['projection' => ['label' => 1, 'ref' => 1]]);
      if (!$qdoc) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Question introuvable.']);
        exit;
      }

      $reportKey = (string)$paperOid . ':' . (string)$reportOid;
      $reportQidStr = (string)$reportOid;

      if (!empty($_SESSION['quiz_question_reports'][$reportKey])) {
        echo json_encode(['ok' => true, 'already' => true, 'message' => 'Vous avez déjà signalé cette question.']);
        exit;
      }

      if (!empty($paperReportedQuestionIds[$reportQidStr])) {
        $_SESSION['quiz_question_reports'][$reportKey] = 'shared';
        echo json_encode(['ok' => true, 'already' => true, 'message' => 'Cette question a déjà été signalée par un autre candidat.']);
        exit;
      }

      $tz = new DateTimeZone('Africa/Douala');
      $reportedAt = (new DateTime('now', $tz))->format('d/m/Y H:i:s');

      $qRef = is_array($qdoc) || $qdoc instanceof MongoDB\Model\BSONDocument
        ? (string)($qdoc['ref'] ?? post_str('question_ref', ''))
        : post_str('question_ref', '');
      $qLabel = is_array($qdoc) || $qdoc instanceof MongoDB\Model\BSONDocument
        ? (string)($qdoc['label'] ?? post_str('question_label', ''))
        : post_str('question_label', '');

      $reportDoc = [
        'paperId' => (string)$paperOid,
        'questionId' => $reportQidStr,
        'reportedAt' => $reportedAt,
        'reportedBy' => $userId,
        'candidateName' => $candidateFullName,
        'questionRef' => $qRef,
        'paperLabel' => (string)($paper['label'] ?? ''),
      ];

      $claimResult = $quizQuestionReports->updateOne(
        ['paperId' => (string)$paperOid, 'questionId' => $reportQidStr],
        ['$setOnInsert' => $reportDoc],
        ['upsert' => true]
      );

      if ($claimResult->getUpsertedCount() === 0) {
        $_SESSION['quiz_question_reports'][$reportKey] = 'shared';
        $paperReportedQuestionIds[$reportQidStr] = true;
        echo json_encode(['ok' => true, 'already' => true, 'message' => 'Cette question a déjà été signalée par un autre candidat.']);
        exit;
      }

      $sent = sendMailQuizQuestionReport(
        $candidateFullName,
        (string)($paper['label'] ?? 'Épreuve'),
        $qRef,
        $qLabel,
        $reportedAt,
        (string)$reportOid
      );

      if ($sent) {
        $_SESSION['quiz_question_reports'][$reportKey] = $reportedAt;
        $paperReportedQuestionIds[$reportQidStr] = true;
        echo json_encode(['ok' => true, 'message' => 'Merci, votre signalement a été envoyé.']);
      } else {
        $quizQuestionReports->deleteOne(['paperId' => (string)$paperOid, 'questionId' => $reportQidStr]);
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Échec d\'envoi du mail. Réessayez ou contactez le support.']);
      }
    } catch (Throwable $e) {
      http_response_code(500);
      echo json_encode(['ok' => false, 'message' => 'Erreur serveur lors du signalement.']);
    }
    exit;
  }

  // === AUTOSAVE (AJAX silencieux, déclenché à chaque changement de question) ===
  if (isset($_POST['autosave'])) {
    header('Content-Type: application/json');
    $as_hr = (int)(post_str('hr','0')); $as_mn = (int)(post_str('mn','0')); $as_sc = (int)(post_str('sc','0'));
    $as_orientPosts = $_POST['orient'] ?? [];
    $as_oriAnswers = [];
    foreach ($orientationDocs as $od) {
      $as_oidStr = (string)$od['_id'];
      $as_opts = to_php_array($od['options'] ?? []);
      $as_chosen = isset($as_orientPosts[$as_oidStr])
        ? parse_orientation_chosen($as_orientPosts[$as_oidStr], $as_opts)
        : [];
      $as_oriAnswers[$as_oidStr] = array_values(array_intersect($as_chosen, $as_opts));
    }
    $as_set = ['orientationAnswers'=>$as_oriAnswers,'hour'=>$as_hr,'minute'=>$as_mn,'second'=>$as_sc,'updated'=>date('Y-m-d H:i:s')];
    if (!$modeOrientationOnly || $catchupMode) {
      $as_qidsRaw = post_array('questionsTag');
      $as_qids = [];
      foreach ($as_qidsRaw as $id) { $o = to_oid(is_string($id)?$id:null); if ($o) $as_qids[]=$o; }
      if (empty($as_qids)) $as_qids = $paperQOids;
      $currentBase = resolve_qcm_answers_snapshot($examArray, $results, $userOid, $paperOid, $paperQOids);
      $as_answers = collect_aligned_answers($_POST, count($as_qids), $currentBase);
      $as_tot = 0; foreach ($as_answers as $a) if ($a !== 'null' && $a !== '' && $a !== null) $as_tot++;
      $as_set['questions']=$as_qids; $as_set['answers']=$as_answers; $as_set['total']=$as_tot; $as_set['originalOrder']=$paperQOids;
    }
    // Temps supplémentaire accordé depuis connectedUsers.php : proposé au candidat à chaque autosave tant qu'il n'a pas
    // cliqué sur OK ; le chrono envoyé avec bonus_ack inclut déjà le bonus.
    $as_reply = ['ok'=>true];
    $as_update = [];
    $as_bonus = ($exam && isset($exam['bonus']['status']) && $exam['bonus']['status'] === 'pending') ? $exam['bonus'] : null;
    if ($as_bonus && post_str('bonus_ack','') === (string)$as_bonus['id']) {
      $as_set['bonus.status'] = 'accepted';
      $as_set['bonus.acceptedAt'] = date('Y-m-d H:i:s');
      $as_update['$inc'] = ['bonusMinutes'=>(int)$as_bonus['minutes']];
    } elseif ($as_bonus) {
      $as_reply['bonus'] = ['id'=>(string)$as_bonus['id'], 'minutes'=>(int)$as_bonus['minutes']];
    }
    if ($exam) {
      $exams->updateOne(['_id'=>$exam['_id']], array_merge(['$set'=>$as_set], $as_update));
    } else {
      $exams->insertOne(array_merge(['user'=>$userOid,'paper'=>$paperOid,'active'=>true,'created'=>date('Y-m-d H:i:s')], $as_set));
    }
    echo json_encode($as_reply);
    exit;
  }

  $hr = (int)(post_str('hr','0')); $mn = (int)(post_str('mn','0')); $sc = (int)(post_str('sc','0'));
  $timeStr = post_str('timer','');
  $isTimeUp = (post_str('timeup','0') === '1');

  // -------- Orientation (toujours lue pour pouvoir sauver indépendamment)
  $orientPosts = $_POST['orient'] ?? [];
  $orientationAnswers = [];
  foreach ($orientationDocs as $od) {
    $oidStr  = (string)$od['_id'];
    $opts    = to_php_array($od['options'] ?? []);
    $chosen  = isset($orientPosts[$oidStr])
      ? parse_orientation_chosen($orientPosts[$oidStr], $opts)
      : [];
    $orientationAnswers[$oidStr] = array_values(array_intersect($chosen, $opts));
  }

  // -------- QCM (ignoré si modeOrientationOnly, sauf rattrapage)
  $qids = $paperQOids; $answers = []; $totalAnswered = 0;
  if (!$modeOrientationOnly || $catchupMode) {
    $qidsRaw = post_array('questionsTag');
    $qids = [];
    foreach ($qidsRaw as $id) { $oid = to_oid(is_string($id)?$id:null); if ($oid) $qids[]=$oid; }
    if (empty($qids)) $qids = $paperQOids;

    $currentBase = resolve_qcm_answers_snapshot($examArray, $results, $userOid, $paperOid, $paperQOids);
    $answers = collect_aligned_answers($_POST, count($qids), $currentBase);
    foreach ($answers as $a) if ($a !== 'null' && $a !== '' && $a !== null) $totalAnswered++;
  }

  // === TIMEUP (auto-validate & redirect congrat) ===
if ($isTimeUp) {
    $wasPendingChoice = (bool)($examArray['pendingCatchupChoice'] ?? false);
    $wasCatchupMode = (bool)($examArray['catchupMode'] ?? false);
    $wasCatchupIndices = [];
    if (!empty($examArray['catchupIndices']) && is_array($examArray['catchupIndices'])) {
      $wasCatchupIndices = array_values(array_map('intval', $examArray['catchupIndices']));
    }

    // Upsert exam snapshot
    if ($exam) {
        $set = [
            'orientationAnswers'=>$orientationAnswers,
            'hour'=>$hr,'minute'=>$mn,'second'=>$sc,
            'updated'=>date('Y-m-d H:i:s')
        ];
        if (!$modeOrientationOnly || $catchupMode) {
            $set['questions'] = $qids; 
            $set['answers'] = $answers; 
            $set['total'] = $totalAnswered;
            $set['originalOrder'] = $paperQOids;
        }
        $exams->updateOne(['_id'=>$exam['_id']], ['$set'=>$set]);
        $exam = $exams->findOne(['_id'=>$exam['_id']]);
    } else {
        $doc = [
            'user'=>$userOid,'paper'=>$paperOid,'active'=>true,
            'orientationAnswers'=>$orientationAnswers,
            'hour'=>$hr,'minute'=>$mn,'second'=>$sc,
            'created'=>date('Y-m-d H:i:s')
        ];
        if (!$modeOrientationOnly || $catchupMode) {
            $doc['questions']=$qids; 
            $doc['answers']=$answers; 
            $doc['total']=$totalAnswered;
            $doc['originalOrder'] = $paperQOids;
        }
        $exams->insertOne($doc);
        $exam = $exams->findOne(['user'=>$userOid,'paper'=>$paperOid,'active'=>true]);
    }

    if ($wasPendingChoice) {
        finalize_exam_close($allocations, $exams, $allocation, $exam, $db, $paperOid);
        header("Location: ./congrat");
        exit;
    }

    if ($wasCatchupMode && !empty($wasCatchupIndices)) {
        $baseAnswers = resolve_qcm_answers_snapshot($examArray, $results, $userOid, $paperOid, $paperQOids);
        $mergedAnswers = $baseAnswers;
        foreach ($wasCatchupIndices as $ci) {
            if (isset($answers[$ci])) {
                $mergedAnswers[$ci] = (string)$answers[$ci];
            }
        }
        apply_catchup_answers_to_results(
            $results,
            $questions,
            $userOid,
            $paperOid,
            $paperQOids,
            $mergedAnswers,
            $wasCatchupIndices
        );
        $totalMerged = 0;
        foreach ($mergedAnswers as $ma) {
            if ($ma !== 'null' && $ma !== '' && $ma !== null) $totalMerged++;
        }
        if ($exam) {
            $exams->updateOne(['_id' => $exam['_id']], ['$set' => [
                'answers' => $mergedAnswers,
                'total' => $totalMerged,
                'hour' => $hr,
                'minute' => $mn,
                'second' => $sc,
                'updated' => date('Y-m-d H:i:s'),
            ]]);
        }
        finalize_exam_close($allocations, $exams, $allocation, $exam, $db, $paperOid);
        header("Location: ./congrat");
        exit;
    }

    // Si on n'est PAS en orientation-only, on génère/insère les résultats QCM
    if (!$modeOrientationOnly) {
        $alreadyGlobal = $results->findOne([
            'user'=>$userOid,
            'paper'=>$paperOid,
            'type'=>'Factuel',
            'level'=>'Mixed',
            'userAnswers'=>['$exists'=>true]
        ]);
        
        if (!$alreadyGlobal) {
            $qmap=[]; 
            if (!empty($qids)) {
                $qc=$questions->find(['_id'=>['$in'=>$qids], 'active'=>true]);
                foreach ($qc as $qd) $qmap[(string)$qd['_id']]=$qd;
            }
            
            // par spécialité
            $agg=[];
            foreach ($qids as $i=>$oid) {
                $k=(string)$oid; 
                if (!isset($qmap[$k])) continue;
                $qd=$qmap[$k];
                $spec=(string)($qd['speciality']??''); 
                if ($spec==='') $spec='(Sans spécialité)';
                if (!isset($agg[$spec])) {
                    $agg[$spec]=[
                        'quizId'=>($specQuizMap[$spec]??null),
                        'score'=>0,
                        'questions'=>[],
                        'answers'=>[]
                    ];
                }
                $ua= (string)($answers[$i] ?? 'null');
                $ok= ($ua === (string)($qd['answer']??''));        
                $agg[$spec]['score'] += $ok?1:0;
                $agg[$spec]['questions'][] = $qd['_id'];
                $agg[$spec]['answers'][]   = $ok ? 'Maitrisé' : 'Non maitrisé';
            }
            
            foreach ($agg as $spec=>$data) {
                $existFilter = [
                    'user'=>$userOid,
                    'paper'=>$paperOid,
                    'speciality'=>$spec,
                    'type'=>'Factuel',
                    'level'=>'Mixed'
                ];
                if (! $results->findOne($existFilter)) {
                    $results->insertOne([
                        'questions'=>$data['questions'],
                        'answers'=>$data['answers'],
                        'quiz'=>$data['quizId'] ?? null,
                        'user'=>$userOid,
                        'score'=>$data['score'],
                        'speciality'=>$spec,
                        'level'=>'Mixed',
                        'type'=>'Factuel',
                        'total'=>count($data['questions']),
                        'numberTest'=>1,
                        'time'=>$timeStr,
                        'paper'=>$paperOid,
                        'active'=>true,
                        'created'=>date('Y-m-d H:i:s'),
                    ]);
                }
            }
            
            // global
            $overallScore=0; 
            $overallAnswers=[];
            foreach ($qids as $i=>$oid) {
                $k=(string)$oid;
                if (!isset($qmap[$k])) { 
                    $overallAnswers[]='Non maitrisé'; 
                    continue; 
                }
                $ok = ((string)($answers[$i]??'null')) === (string)($qmap[$k]['answer']??'');
                if ($ok) $overallScore++;
                $overallAnswers[] = $ok ? 'Maitrisé' : 'Non maitrisé';
            }
            
            $results->insertOne([
                'questions'=>$qids,
                'answers'=>$overallAnswers,
                'userAnswers'=>$answers,
                'originalOrder'=>$paperQOids,
                'user'=>$userOid,
                'score'=>$overallScore,
                'level'=>'Mixed',
                'type'=>'Factuel',
                'subsidiary'=>$technician['subsidiary'] ?? null,
                'typeR'=>'Candidat',
                'total'=>count($qids),
                'numberTest'=>1,
                'time'=>$timeStr,
                'paper'=>$paperOid,
                'active'=>true,
                'created'=>date('Y-m-d H:i:s'),
            ]);
        }
    }

    // ============================================================
    // CORRECTION ICI : Vérifier l'orientation AVANT de rediriger
    // ============================================================
    
    $oriCompleteNow = orientation_complete($orientationDocs, $orientationAnswers);
    
    if ($oriCompleteNow && !empty($orientationDocs)) {
        // CAS 1 : Orientation complète → Sauvegarder + Terminer + Rediriger
        $payload=[];
        foreach ($orientationDocs as $od) {
            $oid=(string)$od['_id'];
            $payload[] = [
                'id'=>$oid,
                'title'=>(string)$od['title'],
                'minSelect'=>(int)($od['minSelect']??0),
                'maxSelect'=>(int)($od['maxSelect']??0),
                'selected'=>$orientationAnswers[$oid] ?? []
            ];
        }
        
        $exists = $results->findOne([
            'user'=>$userOid,
            'paper'=>$paperOid,
            'type'=>'Orientation',
            'level'=>'Mixed'
        ]);
        
        if ($exists) {
            $results->updateOne(
                ['_id'=>$exists['_id']], 
                ['$set'=>['answers'=>$payload,'updated'=>date('Y-m-d H:i:s')]]
            );
        } else {
            $results->insertOne([
                'user'=>$userOid,
                'paper'=>$paperOid,
                'type'=>'Orientation',
                'level'=>'Mixed',
                'answers'=>$payload,
                'active'=>true,
                'created'=>date('Y-m-d H:i:s')
            ]);
        }

        $examSnap = $exam ? json_decode(json_encode($exam), true) : [];
        if (try_offer_catchup_choice(
            $exams,
            $allocations,
            $exam,
            $allocation,
            is_array($examSnap) ? $examSnap : [],
            $orientationAnswers,
            $hr,
            $mn,
            $sc,
            $results,
            $userOid,
            $paperOid,
            $paperQOids
        ) > 0) {
            header("Location: ./userPaperQuiz.php?paper=".$paperArg); exit;
        }

        // Clôture complète
        $allocations->updateOne(
            ['_id'=>$allocation['_id']], 
            ['$set'=>[
                'status'=>'done',
                'active'=>false,
                'finishedAt'=>date('Y-m-d H:i:s')
            ]]
        );
        
        if ($exam) { 
            $exams->updateOne(
                ['_id'=>$exam['_id']], 
                ['$set'=>['active'=>false]]
            ); 
        }

        require_once __DIR__ . '/includes/paper_candidate_report_lib.php';
        paperReportMaybeSendThresholdAlert($db, $paperOid);
        
        header("Location: ./congrat"); 
        exit;
        
    } elseif (!empty($orientationDocs)) {
        // CAS 2 : Orientation incomplète → Donner du temps supplémentaire
        
        // AJOUTER 30 MINUTES SUPPLÉMENTAIRES (modifiable)
        $bonusMinutes = 30;
        
        // Mettre le statut en attente d'orientation
        $allocations->updateOne(
            ['_id'=>$allocation['_id']], 
            ['$set'=>[
                'status'=>'awaiting_orientation',
                'active'=>true
            ]]
        );
        
        // Verrouiller le QCM (ne peut plus être modifié)
        // MAIS donner du temps bonus pour l'orientation
        if ($exam) { 
            $exams->updateOne(
                ['_id'=>$exam['_id']], 
                ['$set'=>[
                    'lockQcm'=>true,
                    'hour'=>0,
                    'minute'=>$bonusMinutes,
                    'second'=>0,
                    'bonusTimeGiven'=>true  // Flag pour tracer qu'on a donné du temps
                ]]
            ); 
        }
        
        // IMPORTANT : Rediriger vers le quiz avec focus sur orientation
        // ET avec le nouveau temps
        header("Location: ./userPaperQuiz.php?paper=".$paperArg."&focus=orientation&bonus=1"); 
        exit;
        
    } else {
        // CAS 3 : Pas de questions d'orientation → Terminer directement
        $allocations->updateOne(
            ['_id'=>$allocation['_id']], 
            ['$set'=>[
                'status'=>'done',
                'active'=>false,
                'finishedAt'=>date('Y-m-d H:i:s')
            ]]
        );
        
        if ($exam) { 
            $exams->updateOne(
                ['_id'=>$exam['_id']], 
                ['$set'=>['active'=>false]]
            ); 
        }

        require_once __DIR__ . '/includes/paper_candidate_report_lib.php';
        paperReportMaybeSendThresholdAlert($db, $paperOid);
        
        header("Location: ./congrat"); 
        exit;
    }
} // -- FIN TIMEUP
 // -- FIN TIMEUP

  // === SAVE (brouillon) ===
  if (isset($_POST['save'])) {
    if ($exam) {
      $set = [
        'orientationAnswers'=>$orientationAnswers,
        'hour'=>$hr,'minute'=>$mn,'second'=>$sc,
        'updated'=>date('Y-m-d H:i:s')
      ];
      if (!$modeOrientationOnly || $catchupMode) {
        $set['questions'] = $qids; $set['answers'] = $answers; $set['total'] = $totalAnswered;$set['originalOrder'] = $paperQOids;
      }
      $exams->updateOne(['_id'=>$exam['_id']], ['$set'=>$set]);
      $exam = $exams->findOne(['_id'=>$exam['_id']]);
    } else {
      $doc = [
        'user'=>$userOid,'paper'=>$paperOid,'active'=>true,
        'orientationAnswers'=>$orientationAnswers,
        'hour'=>$hr,'minute'=>$mn,'second'=>$sc,
        'created'=>date('Y-m-d H:i:s')
      ];
      if (!$modeOrientationOnly || $catchupMode) {
        $doc['questions']=$qids; $doc['answers']=$answers; $doc['total']=$totalAnswered;
      }
      $exams->insertOne($doc);
      $exam = $exams->findOne(['user'=>$userOid,'paper'=>$paperOid,'active'=>true]);
    }
    header("Location: ".$_SERVER['REQUEST_URI']); exit;
  }

  // === CLÔTURE après orientation (choix candidat ou fin de temps) ===
  if (isset($_POST['close_exam_after_orientation'])) {
    if ($exam && ($examArray['pendingCatchupChoice'] ?? false)) {
      finalize_exam_close($allocations, $exams, $allocation, $exam, $db, $paperOid);
      header("Location: ./congrat"); exit;
    }
  }

  // === DÉMARRER rattrapage QCM ===
  if (isset($_POST['start_catchup'])) {
    if ($exam && ($examArray['pendingCatchupChoice'] ?? false)) {
      $savedAnswers = resolve_qcm_answers_snapshot($examArray, $results, $userOid, $paperOid, $paperQOids);
      $offer = max(0, (int)($examArray['catchupMaxOffered'] ?? 0));
      $indices = pick_catchup_indices($savedAnswers, $offer);
      if (!empty($indices)) {
        $exams->updateOne(['_id' => $exam['_id']], ['$set' => [
          'pendingCatchupChoice' => false,
          'catchupMode' => true,
          'catchupIndices' => $indices,
          'lockQcm' => true,
          'hour' => $hr,
          'minute' => $mn,
          'second' => $sc,
          'updated' => date('Y-m-d H:i:s'),
        ]]);
        if ($allocation) {
          $allocations->updateOne(
            ['_id' => $allocation['_id']],
            ['$set' => ['status' => 'catchup_qcm', 'active' => true]]
          );
        }
        header("Location: ".$_SERVER['REQUEST_URI']); exit;
      }
    }
    finalize_exam_close($allocations, $exams, $allocation, $exam, $db, $paperOid);
    header("Location: ./congrat"); exit;
  }

  // === VALIDER rattrapage QCM ===
  if (isset($_POST['valid_catchup'])) {
    $catchupIdx = is_array($examArray['catchupIndices'] ?? null)
      ? array_values(array_map('intval', $examArray['catchupIndices']))
      : [];
    if ($exam && $catchupMode && !empty($catchupIdx)) {
      $baseAnswers = resolve_qcm_answers_snapshot($examArray, $results, $userOid, $paperOid, $paperQOids);
      $mergedAnswers = $baseAnswers;
      foreach ($catchupIdx as $ci) {
        if (isset($answers[$ci])) {
          $mergedAnswers[$ci] = (string)$answers[$ci];
        }
      }
      $missing = 0;
      foreach ($catchupIdx as $ci) {
        if ((string)($mergedAnswers[$ci] ?? 'null') === 'null') {
          $missing++;
          if ($first_unanswered_idx === null) {
            $first_unanswered_idx = $ci;
          }
        }
      }
      if ($missing > 0) {
        $error_msg = "Il reste {$missing} question(s) de rattrapage sans réponse.";
      } else {
        apply_catchup_answers_to_results(
          $results,
          $questions,
          $userOid,
          $paperOid,
          $paperQOids,
          $mergedAnswers,
          $catchupIdx
        );
        $totalNonEmpty = 0;
        foreach ($mergedAnswers as $ma) {
          if ($ma !== 'null' && $ma !== '' && $ma !== null) $totalNonEmpty++;
        }
        $exams->updateOne(['_id' => $exam['_id']], ['$set' => [
          'answers' => $mergedAnswers,
          'total' => $totalNonEmpty,
          'hour' => $hr,
          'minute' => $mn,
          'second' => $sc,
          'updated' => date('Y-m-d H:i:s'),
        ]]);
        finalize_exam_close($allocations, $exams, $allocation, $exam, $db, $paperOid);
        header("Location: ./congrat"); exit;
      }
    }
  }

  // === VALID ORIENTATION SEULE ===
  if (isset($_POST['valid_orientation'])) {
    if (!orientation_complete($orientationDocs, $orientationAnswers)) {
        $error_details = [];
        $first_missing_idx = null;
        
        foreach ($orientationDocs as $i => $od) {
            $oid = (string)$od['_id'];
            $title = (string)($od['title'] ?? 'Question de préférences');
            $min = (int)($od['minSelect'] ?? 0);
            $max = (int)($od['maxSelect'] ?? 0);
            $sel = isset($orientationAnswers[$oid]) ? $orientationAnswers[$oid] : [];
            $count = count($sel);
            
            // Vérifier si cette question est incorrecte
            if ($min > 0 && $count < $min) {
                $error_details[] = $title;
                if ($first_missing_idx === null) {
                    $first_missing_idx = $orientationStartIndex + $i;
                }
            } elseif ($max > 0 && $count > $max) {
                $error_details[] = $title;
                if ($first_missing_idx === null) {
                    $first_missing_idx = $orientationStartIndex + $i;
                }
            }
        }
        
        $missingCount = count($error_details);
        if ($missingCount === 1) {
            $error_msg = "Veuillez répondre à la question de préférences : « " . $error_details[0] . " ».";
        } elseif ($missingCount > 1) {
            $error_msg = "Veuillez compléter les {$missingCount} questions de préférences restantes pour valider votre parcours.";
        } else {
            $error_msg = "Merci de compléter correctement toutes les questions de préférences.";
        }
    } else {
      if ($exam) {
        $exams->updateOne(['_id'=>$exam['_id']], ['$set'=>[
          'orientationAnswers'=>$orientationAnswers,
          'updated'=>date('Y-m-d H:i:s')
        ]]);
      } else {
        $exams->insertOne([
          'user'=>$userOid,'paper'=>$paperOid,'active'=>true,
          'orientationAnswers'=>$orientationAnswers,
          'created'=>date('Y-m-d H:i:s')
        ]);
        $exam = $exams->findOne(['user'=>$userOid,'paper'=>$paperOid,'active'=>true]);
      }

      persist_orientation_results($results, $userOid, $paperOid, $orientationDocs, $orientationAnswers);

      if (try_offer_catchup_choice(
        $exams,
        $allocations,
        $exam,
        $allocation,
        is_array($examArray) ? $examArray : [],
        $orientationAnswers,
        $hr,
        $mn,
        $sc,
        $results,
        $userOid,
        $paperOid,
        $paperQOids
      ) > 0) {
        header("Location: ".$_SERVER['REQUEST_URI']); exit;
      }

      finalize_exam_close($allocations, $exams, $allocation, $exam, $db, $paperOid);
      header("Location: ./congrat"); exit;
    }
  }

  // === VALID (QCM + éventuellement orientation) ===
  if (isset($_POST['valid']) && !$modeOrientationOnly) {
    if (count($qids)===0) {
      $error_msg = "Aucune question trouvée.";
    } elseif ($totalAnswered !== count($qids)) {
      $missing = count($qids)-$totalAnswered;
      foreach ($answers as $ai => $av) {
        if ((string)$av === 'null' || $av === null || $av === '') {
          $first_unanswered_idx = $ai;
          break;
        }
      }
      $error_msg = "Vous n'avez pas répondu à {$missing} question(s) du test théorique.";
    } else {
      // Upsert exam
      if ($exam) {
        $exams->updateOne(['_id'=>$exam['_id']], ['$set'=>[
          'questions'=>$qids,'answers'=>$answers,'total'=>$totalAnswered, 'originalOrder'=>$paperQOids,
          'orientationAnswers'=>$orientationAnswers,
          'hour'=>$hr,'minute'=>$mn,'second'=>$sc,
          'updated'=>date('Y-m-d H:i:s')
        ]]);
        $exam = $exams->findOne(['_id'=>$exam['_id']]);
      } else {
        $exams->insertOne([
          'user'=>$userOid,'paper'=>$paperOid,'active'=>true,
          'questions'=>$qids,'answers'=>$answers,'total'=>$totalAnswered, 'originalOrder'=>$paperQOids,
          'orientationAnswers'=>$orientationAnswers,
          'hour'=>$hr,'minute'=>$mn,'second'=>$sc,
          'created'=>date('Y-m-d H:i:s')
        ]);
        $exam = $exams->findOne(['user'=>$userOid,'paper'=>$paperOid,'active'=>true]);
      }

      // Résultats QCM (si pas déjà insérés)
      $alreadyGlobal = $results->findOne(['user'=>$userOid,'paper'=>$paperOid,'type'=>'Factuel','level'=>'Mixed','userAnswers'=>['$exists'=>true]]);
      if (!$alreadyGlobal) {
        $qmap=[]; if (!empty($qids)) {
          $qc=$questions->find(['_id'=>['$in'=>$qids], 'active'=>true]);
          foreach ($qc as $qd) $qmap[(string)$qd['_id']]=$qd;
        }
        // par spécialité
        $agg=[];
        foreach ($qids as $i=>$oid) {
          $k=(string)$oid; if (!isset($qmap[$k])) continue;
          $qd=$qmap[$k];
          $spec=(string)($qd['speciality']??''); if ($spec==='') $spec='(Sans spécialité)';
          if (!isset($agg[$spec])) $agg[$spec]=['quizId'=>($specQuizMap[$spec]??null),'score'=>0,'questions'=>[],'answers'=>[]];
          $ua= (string)($answers[$i] ?? 'null');
          $ok= ($ua === (string)($qd['answer']??''));        
          $agg[$spec]['score'] += $ok?1:0;
          $agg[$spec]['questions'][] = $qd['_id'];
          $agg[$spec]['answers'][]   = $ok ? 'Maitrisé' : 'Non maitrisé';
        }
        foreach ($agg as $spec=>$data) {
          $existFilter = ['user'=>$userOid,'paper'=>$paperOid,'speciality'=>$spec,'type'=>'Factuel','level'=>'Mixed'];
          if (! $results->findOne($existFilter)) {
            $results->insertOne([
              'questions'=>$data['questions'],
              'answers'=>$data['answers'],
              'quiz'=>$data['quizId'] ?? null,
              'user'=>$userOid,
              'score'=>$data['score'],
              'speciality'=>$spec,
              'level'=>'Mixed',
              'type'=>'Factuel',
              'total'=>count($data['questions']),
              'numberTest'=>1,
              'time'=>$timeStr,
              'paper'=>$paperOid,
              'active'=>true,
              'created'=>date('Y-m-d H:i:s'),
            ]);
          }
        }
        // global
        $overallScore=0; $overallAnswers=[];
        foreach ($qids as $i=>$oid) {
          $k=(string)$oid;
          if (!isset($qmap[$k])) { $overallAnswers[]='Non maitrisé'; continue; }
          $ok = ((string)($answers[$i]??'null')) === (string)($qmap[$k]['answer']??'');
          if ($ok) $overallScore++;
          $overallAnswers[] = $ok ? 'Maitrisé' : 'Non maitrisé';
        }
        $results->insertOne([
          'questions'=>$qids,
          'answers'=>$overallAnswers,
          'originalOrder'=>$paperQOids,
          'userAnswers'=>$answers,
          'user'=>$userOid,
          'score'=>$overallScore,
          'level'=>'Mixed',
          'type'=>'Factuel',
          'subsidiary'=>$technician['subsidiary'] ?? null,
          'typeR'=>'Candidat',
          'total'=>count($qids),
          'numberTest'=>1,
          'time'=>$timeStr,
          'paper'=>$paperOid,
          'active'=>true,
          'created'=>date('Y-m-d H:i:s'),
        ]);
      }

      // Orientation maintenant ?
      $oriCompleteNow = orientation_complete($orientationDocs, $orientationAnswers);
      if ($oriCompleteNow && !empty($orientationDocs)) {
        $payload=[];
        foreach ($orientationDocs as $od) {
          $oid=(string)$od['_id'];
          $payload[] = [
            'id'=>$oid,
            'title'=>(string)$od['title'],
            'minSelect'=>(int)($od['minSelect']??0),
            'maxSelect'=>(int)($od['maxSelect']??0),
            'selected'=>$orientationAnswers[$oid] ?? []
          ];
        }
        $exists = $results->findOne(['user'=>$userOid,'paper'=>$paperOid,'type'=>'Orientation','level'=>'Mixed']);
        if ($exists) $results->updateOne(['_id'=>$exists['_id']], ['$set'=>['answers'=>$payload,'updated'=>date('Y-m-d H:i:s')]]);
        else $results->insertOne(['user'=>$userOid,'paper'=>$paperOid,'type'=>'Orientation','level'=>'Mixed','answers'=>$payload,'active'=>true,'created'=>date('Y-m-d H:i:s')]);
      }

      if ($oriCompleteNow || empty($orientationDocs)) {
        $allocations->updateOne(['_id'=>$allocation['_id']], ['$set'=>['status'=>'done','active'=>false,'finishedAt'=>date('Y-m-d H:i:s')]]);
        if ($exam) { $exams->updateOne(['_id'=>$exam['_id']], ['$set'=>['active'=>false]]); }
        require_once __DIR__ . '/includes/paper_candidate_report_lib.php';
        paperReportMaybeSendThresholdAlert($db, $paperOid);
        header("Location: ./congrat"); exit;
      } else {
        $allocations->updateOne(['_id'=>$allocation['_id']], ['$set'=>['status'=>'awaiting_orientation','active'=>true]]);
        if ($exam) { $exams->updateOne(['_id'=>$exam['_id']], ['$set'=>['lockQcm'=>true]]); }
        $base = strtok($_SERVER['REQUEST_URI'], '?');
        $qs   = $_GET; 
        $qs['focus'] = 'orientation';
        $qs['err']   = 'orientation_missing';
        header("Location: ".$base.'?'.http_build_query($qs)); exit;
      }
    }
  }
}

/* ---------------- Vue (build DOM) ---------------- */
$paperDuration = is_array($paper['duration'] ?? null) ? $paper['duration'] : [];
$defaultHr = isset($paperDuration['hour']) ? (int)$paperDuration['hour'] : (int)($paper['hour'] ?? 1);
$defaultMn = isset($paperDuration['minute']) ? (int)$paperDuration['minute'] : (int)($paper['minute'] ?? 0);
$defaultSc = isset($paperDuration['second']) ? (int)$paperDuration['second'] : (int)($paper['second'] ?? 0);
$defaultHr = max(0, min(8, $defaultHr));
$defaultMn = max(0, min(59, $defaultMn));
$defaultSc = max(0, min(59, $defaultSc));
if ($defaultHr === 0 && $defaultMn === 0 && $defaultSc === 0) {
  $defaultHr = 1;
}
$initHr = (int)($examArray['hour']   ?? $defaultHr);
$initMn = (int)($examArray['minute'] ?? $defaultMn);
$initSc = (int)($examArray['second'] ?? $defaultSc);

/* Charger QCM docs actifs (ordre respecté) */
$qmap=[]; if (!empty($paperQOids)) {
  $qc = $questions->find(['_id'=>['$in'=>$paperQOids], 'active'=>true]);
  foreach ($qc as $qd) $qmap[(string)$qd['_id']]=$qd;
}
$orderedQ = []; foreach ($paperQOids as $oid) { $k=(string)$oid; if (isset($qmap[$k])) $orderedQ[]=$qmap[$k]; }

/* User/Exam info */
$fullName = trim(($technician['firstName'] ?? '').' '.($technician['lastName'] ?? ''));
$titleUser = $fullName !== '' ? $fullName : '—';
$prevOrientation = is_array($examArray['orientationAnswers'] ?? null) ? $examArray['orientationAnswers'] : [];

/* Si pas d'orientation en examArray et en revue, tenter depuis results Orientation */
if ($reviewMode && empty($prevOrientation) && !empty($orientationDocs)) {
  $rOri = $results->findOne(['user'=>$userOid,'paper'=>$paperOid,'type'=>'Orientation','level'=>'Mixed'], ['sort'=>['created'=>-1]]);
  if ($rOri && is_array($rOri['answers'] ?? null)) {
    $map=[];
    foreach ($rOri['answers'] as $row) {
      if (!empty($row['id']) && is_array($row['selected'] ?? null)) $map[(string)$row['id']] = (array)$row['selected'];
    }
    $prevOrientation = $map;
  }
}

/* Verrous */
$lockQcmBase = $reviewMode || (bool)($examArray['lockQcm'] ?? false) || ($modeOrientationOnly && !$catchupMode);
$lockOrientation = $reviewMode || $pendingCatchupChoice || $catchupMode;

$orientationStartIndex = count($orderedQ);
$shouldFocusOrientation = ($modeOrientationOnly || qs_get('focus','') === 'orientation') && !$catchupMode && !$pendingCatchupChoice;
$catchupNavTotal = $catchupMode ? count($catchupIndices) : count($orderedQ);
$candidate_nav_title = $cand_nav_quiz;
$candidate_nav_subtitle = $cand_nav_quiz_subtitle;
?>
<?php include_once "partials/header.php"; ?>
<?php openBackgroundContainer('candidate-fullscreen-bg'); ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

<style>
:root {
  --border: #e2e8f0;
  --quiz-blue: #1273ea;
  --quiz-blue-dark: #0b3c6e;
  --quiz-green: #16a34a;
  --quiz-radius: 18px;
}
body.candidate-portal .container-xxl{
  padding-top:1.25rem;padding-bottom:.75rem;max-width:1280px;margin:0 auto;
}
.glass{
  background:rgba(255,255,255,.92);
  backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px);
  border:1px solid rgba(255,255,255,.85);
  border-radius:var(--quiz-radius);
  box-shadow:0 8px 32px rgba(15,23,42,.08), 0 1px 0 rgba(255,255,255,.6) inset;
}
.q-card{
  padding:24px 28px;display:flex;flex-direction:column;
  height:auto;max-height:none;min-height:460px;overflow:visible;
}
#qHost{flex:1 1 auto;min-height:0;overflow:visible;padding-right:0}
.glass .text-muted{color:#64748b !important}

/* ── Top bar ── */
.quiz-topbar{
  display:grid;
  grid-template-columns:minmax(110px,auto) 1fr minmax(280px,auto);
  align-items:center;
  gap:16px;
  min-height:76px;
  padding:14px 18px !important;
}
.quiz-topbar .topbar-exit{justify-self:start}
.quiz-topbar .topbar-left{justify-self:center;text-align:center;min-width:0;padding:0 12px}
.quiz-topbar .topbar-right{justify-self:end}
.quiz-topbar--review{grid-template-columns:1fr minmax(280px,auto)}
.quiz-topbar--review .topbar-left{justify-self:center}
.quiz-topbar .topbar-left .h3{
  font-size:1.28rem;line-height:1.2;margin:0 0 4px;color:var(--quiz-blue-dark);
  font-weight:800;letter-spacing:-.02em;
}
.quiz-topbar .topbar-left .text-muted{font-size:.88rem;font-weight:500}
.btn-exit{
  display:inline-flex;align-items:center;gap:7px;
  border:1px solid #e2e8f0;background:#fff;color:#475569;
  border-radius:999px;padding:.5rem .95rem;font-weight:600;font-size:.84rem;
  transition:background .15s,border-color .15s,transform .12s;
}
.btn-exit:hover{background:#f8fafc;color:#0f172a;border-color:#cbd5e1;transform:translateY(-1px)}
.topbar-stats{
  display:flex;align-items:stretch;gap:10px;flex-wrap:wrap;justify-content:flex-end;
}
.badge-time{
  display:inline-flex;align-items:center;gap:.55rem;
  background:linear-gradient(135deg,#fff 0%,#f8fafc 100%);
  border:1px solid #dbeafe;border-radius:14px;
  padding:.55rem 1rem .55rem .85rem;white-space:nowrap;
  box-shadow:0 4px 14px rgba(18,115,234,.08);
}
.badge-time .timer-icon{
  width:36px;height:36px;border-radius:10px;
  background:linear-gradient(135deg,#1273ea 0%,#0b3c6e 100%);
  color:#fff;display:inline-flex;align-items:center;justify-content:center;
  font-size:.95rem;flex-shrink:0;
}
.badge-time #timer_sec{
  font-size:1.5rem;font-weight:800;letter-spacing:.05em;
  font-variant-numeric:tabular-nums;color:var(--quiz-blue-dark);line-height:1;
}
.badge-progress{
  display:inline-flex;flex-direction:column;justify-content:center;gap:4px;
  background:#fff;border:1px solid #e2e8f0;border-radius:14px;
  padding:.5rem .9rem;min-width:168px;
}
.badge-progress-label{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
.badge-progress-value{font-size:1rem;font-weight:800;color:#0f172a;line-height:1}
.badge-progress-value span{color:var(--quiz-green)}
.badge-progress-track{height:5px;background:#e2e8f0;border-radius:99px;overflow:hidden}
.badge-progress-fill{height:100%;background:linear-gradient(90deg,#22c55e,#16a34a);border-radius:99px;width:0%;transition:width .35s ease}

/* ── Questions navigator ── */
.questions-panel{position:sticky;top:8px;z-index:20}
.questions-panel{padding:14px 16px 12px !important;margin-bottom:14px !important}
.questions-head{
  display:flex;justify-content:space-between;align-items:flex-end;
  gap:12px;margin-bottom:10px;flex-wrap:wrap;
}
.questions-title-block{min-width:0}
.questions-total{
  font-size:1.05rem;color:#0f172a;font-weight:700;line-height:1.2;
}
.questions-total #questionsTotalLabel{color:var(--quiz-blue);font-size:1.2rem}
.questions-meta{
  text-align:right;font-size:.8rem;font-weight:600;color:#64748b;
  background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;
  padding:.45rem .75rem;white-space:nowrap;
}
.questions-meta .range-total{color:#0f172a;font-weight:800}
.questions-meta #currentQuestionLabel{color:var(--quiz-blue)}
.q-nav-wrap{
  display:grid;grid-template-columns:44px minmax(0,1fr) 44px;
  align-items:center;gap:8px;
  background:linear-gradient(180deg,#f8fafc 0%,#f1f5f9 100%);
  border:1px solid #e2e8f0;border-radius:14px;padding:10px 8px;
}
.q-grid-viewport{
  min-width:0;overflow:hidden;position:relative;border-radius:10px;
}
.q-grid-viewport::after{
  content:'';position:absolute;top:0;right:0;bottom:0;width:17px;
  background:linear-gradient(to right,rgba(241,245,249,0),#f1f5f9 75%);
  pointer-events:none;z-index:1;transition:opacity .15s;
}
.q-grid-viewport.can-scroll-left::before{
  content:'';position:absolute;top:0;left:0;bottom:0;width:17px;
  background:linear-gradient(to left,rgba(241,245,249,0),#f1f5f9 75%);
  pointer-events:none;z-index:1;
}
.q-grid-viewport.at-end::after{opacity:0}
.q-grid{
  display:flex;justify-content:flex-start;gap:6px;
  overflow-x:auto;overflow-y:hidden;padding:2px 2px 2px 4px;
  scroll-behavior:smooth;scrollbar-width:none;-ms-overflow-style:none;
}
.q-grid::-webkit-scrollbar{display:none}
.q-dot{
  flex:0 0 auto;width:34px;height:34px;border-radius:10px;
  border:1px solid #dbeafe;color:#64748b;font-weight:700;font-size:.82rem;
  cursor:pointer;background:#fff;
  transition:transform .12s,background .12s,color .12s,border-color .12s,box-shadow .12s;
}
.q-dot:hover{transform:translateY(-1px);box-shadow:0 4px 10px rgba(15,23,42,.08)}
.q-dot.done{background:var(--quiz-green);border-color:var(--quiz-green);color:#fff}
.q-dot.current{
  background:var(--quiz-blue);border-color:var(--quiz-blue);color:#fff;
  box-shadow:0 0 0 3px rgba(18,115,234,.22);transform:scale(1.05);
}
.q-nav-btn{
  width:44px;height:44px;border-radius:12px;border:1px solid #e2e8f0;
  background:#fff;color:#475569;font-weight:700;font-size:1.15rem;line-height:1;
  transition:background .15s,border-color .15s,color .15s,box-shadow .15s;
}
.q-nav-btn:not(:disabled){
  border-color:#bfdbfe;color:var(--quiz-blue);background:#eff6ff;
  box-shadow:0 2px 8px rgba(18,115,234,.12);
}
.q-nav-btn:not(:disabled):hover{background:#dbeafe}
.q-nav-btn:disabled{opacity:.35;cursor:not-allowed}
.questions-nav-hint{
  text-align:center;font-size:.76rem;color:#64748b;font-weight:600;
  margin-top:8px;padding:0;
}
.questions-nav-hint i{color:var(--quiz-blue);margin-right:4px}

/* ── Question card ── */
.q-label{font-size:1.5rem;font-weight:700;line-height:1.45;margin:4px 0 14px;color:#0f172a;flex-shrink:0}
.q-layout{
  display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:20px;
  align-items:start;flex:0 1 auto;min-height:0;width:100%;
}
.q-left{display:flex;flex-direction:column;gap:0;min-width:0}
.quiz-form__ans{
  display:flex;align-items:center;gap:.65rem;
  margin:8px 0;border:1.5px solid #e2e8f0;border-radius:14px;
  background:#fff;padding:.85rem 1rem;cursor:pointer;
  transition:border-color .15s,background .15s,box-shadow .15s;
}
.quiz-form__ans:hover{border-color:#93c5fd;background:#f8fbff}
.quiz-form__ans:has(input:checked){
  border-color:var(--quiz-blue);background:linear-gradient(135deg,#eff6ff 0%,#f0f9ff 100%);
  box-shadow:0 0 0 1px rgba(18,115,234,.12);
}
.quiz-form__ans input{margin-top:0;flex-shrink:0;accent-color:var(--quiz-blue)}
.quiz-form__ans .text{line-height:1.4;color:#1e293b;font-weight:500;flex:1;min-width:0}

/* ── Footer nav ── */
.nav-footer{
  flex:0 0 auto;margin-top:1rem;padding-top:1rem;
  border-top:1px solid #e8edf3;
}
.nav-footer .btn{
  min-width:150px;font-weight:700;border-radius:999px;
  padding:.62rem 1.15rem;font-size:.9rem;line-height:1.2;
  transition:transform .12s,box-shadow .12s,background .15s;
}
.nav-footer .btn:hover{transform:translateY(-1px)}
.nav-footer .btn-outline-secondary{background:#fff;border-color:#cbd5e1;color:#475569;box-shadow:0 2px 6px rgba(15,23,42,.04)}
.nav-footer .btn-secondary{background:#f1f5f9;border-color:#e2e8f0;color:#334155}
.nav-footer .btn-primary{
  background:linear-gradient(135deg,#1273ea 0%,#0b3c6e 100%);
  border:none;color:#fff;box-shadow:0 6px 18px rgba(18,115,234,.28);
}
.nav-footer .btn-primary:hover{box-shadow:0 8px 22px rgba(18,115,234,.35)}

.ref-badge{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;font-weight:600;font-size:.8rem;margin-left:.4rem;border-radius:8px;padding:.2rem .5rem}
#qHost .q-item{display:none}
#qHost .q-item.is-visible{display:flex;flex-direction:column;height:auto;min-height:0;overflow:visible}
.q-right{display:flex;flex-direction:column;min-width:0;gap:8px;align-self:start;width:100%}
.q-right .contImage{flex:0 0 auto;width:100%}
.exit-modal{position:fixed;inset:0;background:rgba(15,23,42,.45);display:none;align-items:center;justify-content:center;z-index:9999}
.exit-modal.show{display:flex}
.exit-modal-card{width:min(460px,92vw);background:#fff;border-radius:16px;border:1px solid #e2e8f0;box-shadow:0 18px 40px rgba(2,6,23,.25);padding:18px}
.exit-modal-title{font-weight:800;font-size:1rem;color:#0f172a;margin:0 0 8px}
.exit-modal-text{font-size:.92rem;color:#334155;margin:0 0 14px}
.exit-modal-actions{display:flex;justify-content:flex-end;gap:10px}
.btn-modal-cancel,.btn-modal-confirm{border-radius:999px;padding:.52rem 1rem;font-weight:700;font-size:.86rem;border:1px solid transparent}
.btn-modal-cancel{background:#fff;border-color:#cbd5e1;color:#334155}
.btn-modal-confirm{background:var(--quiz-blue);color:#fff}
.contImage{display:flex;justify-content:center;align-items:center;width:100%;height:auto;min-height:160px;max-height:min(42vh,360px);overflow:hidden;border-radius:14px;background:#f8fafc;border:1px solid #e2e8f0;cursor:zoom-in;position:relative}
.contImage.is-fallback{cursor:default;min-height:140px;max-height:min(30vh,240px)}
.contImage.is-fallback::after{display:none}
.img-magnifier-lens{position:fixed;z-index:10001;display:none;pointer-events:none;width:180px;height:180px;border-radius:50%;border:3px solid #fff;box-shadow:0 0 0 1px rgba(15,23,42,.15),0 10px 28px rgba(15,23,42,.28);background-color:#fff;background-repeat:no-repeat}
.contImage::after{content:"\f00e";font-family:"Font Awesome 6 Free";font-weight:900;position:absolute;top:10px;right:10px;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;background:rgba(15,23,42,.7);color:#fff;opacity:0;transition:opacity .15s ease}
.contImage:hover::after{opacity:1}
.contImage img{width:100%;height:auto;max-height:min(42vh,360px);object-fit:contain}
.img-fallback{display:flex;align-items:center;justify-content:center;width:100%;min-height:140px;padding:12px}
.img-fallback img{width:min(240px,70%);max-height:180px;height:auto;object-fit:contain;opacity:.95}
.btn-report-question{display:inline-flex;align-items:center;justify-content:center;gap:.45rem;margin-top:8px;width:100%;border:1px solid #fecaca;border-radius:12px;background:#fff5f5;color:#b91c1c;font-size:.8rem;font-weight:700;padding:.55rem .75rem;cursor:pointer}
.btn-report-question:hover{color:#991b1b;border-color:#f87171;background:#fee2e2}
.btn-report-question.is-loading{opacity:.85;cursor:wait;pointer-events:none}
.btn-report-question.is-reported{border-color:#bbf7d0;background:#f0fdf4;color:#15803d;cursor:default}
.btn-report-question.is-reported:hover{color:#15803d;border-color:#bbf7d0;background:#f0fdf4}
.report-toast{position:fixed;left:50%;bottom:24px;transform:translateX(-50%) translateY(16px);background:#0f172a;color:#fff;padding:.55rem .9rem;border-radius:10px;font-size:.85rem;opacity:0;pointer-events:none;transition:opacity .2s ease,transform .2s ease;z-index:10000}
.report-toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
.orient-hint{color:#64748b;font-size:.9rem}
/* Orientation à plus de 4 options : 2 colonnes pour limiter le défilement, 1 colonne sur mobile */
.orient-opts-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));column-gap:16px}
@media (max-width:768px){.orient-opts-grid{grid-template-columns:1fr}}
.q-catchup-hidden{display:none!important}
.catchup-banner{border-left:4px solid #f59e0b}
/* Rattrapage : colonne unique + image réduite pour libérer de la place aux réponses */
.quiz-catchup-mode .q-layout{grid-template-columns:1fr;gap:14px}
.quiz-catchup-mode .q-right{order:-1}
.quiz-catchup-mode .contImage{max-height:min(24vh,200px);min-height:110px}
.quiz-catchup-mode .contImage.is-fallback{max-height:min(20vh,160px);min-height:100px}
.quiz-catchup-mode .contImage img{max-height:min(24vh,200px)}
.quiz-catchup-mode .img-fallback img{max-height:130px}

/* 15" / viewport bas (ex. 1366×768, 1920×1080 + barre navigateur) */
@media (max-height: 900px) and (min-width: 993px) {
  body.candidate-portal .container-xxl{padding-top:.65rem;padding-bottom:.75rem}
  .quiz-topbar{min-height:58px;padding:10px 14px !important;margin-bottom:.65rem !important}
  .quiz-topbar .topbar-left .h3{font-size:1.05rem;margin-bottom:2px}
  .quiz-topbar .topbar-left .text-muted{font-size:.78rem}
  .badge-time{padding:.4rem .75rem .4rem .65rem}
  .badge-time .timer-icon{width:30px;height:30px;font-size:.82rem}
  .badge-time #timer_sec{font-size:1.2rem}
  .badge-progress{min-width:140px;padding:.4rem .7rem}
  .questions-panel{padding:10px 12px 8px !important;margin-bottom:.65rem !important;top:4px}
  .questions-head{margin-bottom:6px}
  .questions-total{font-size:.92rem}
  .questions-total #questionsTotalLabel{font-size:1rem}
  .questions-meta{font-size:.72rem;padding:.35rem .6rem}
  .q-nav-wrap{padding:6px;gap:6px}
  .q-nav-btn{width:36px;height:36px;font-size:1rem;border-radius:10px}
  .q-dot{width:28px;height:28px;font-size:.74rem;border-radius:8px}
  .questions-nav-hint{margin-top:4px;font-size:.7rem}
  .q-card{padding:18px 22px;min-height:400px}
  .q-label{font-size:1.25rem;line-height:1.38;margin:0 0 8px}
  .ref-badge{font-size:.72rem;padding:.15rem .4rem}
  .q-layout{gap:12px;grid-template-columns:minmax(0,1fr) minmax(0,1fr)}
  .quiz-form__ans{margin:5px 0;padding:.6rem .75rem;border-radius:12px}
  .quiz-form__ans .text{font-size:.92rem;line-height:1.32}
  .contImage{max-height:min(34vh,280px);min-height:130px}
  .contImage.is-fallback{max-height:min(26vh,210px);min-height:120px}
  .img-fallback{min-height:120px;padding:8px}
  .img-fallback img{max-height:150px}
  .btn-report-question{margin-top:6px;padding:.45rem .6rem;font-size:.74rem}
  .nav-footer{padding-top:.65rem}
  .nav-footer .btn{min-width:120px;padding:.5rem .9rem;font-size:.84rem}
}

.quiz-validation-alert {
  display: flex;
  align-items: center;
  gap: 12px;
  background: #fffbeb !important;
  border: 1px solid #fcd34d !important;
  color: #92400e !important;
  border-radius: 14px;
  padding: 10px 16px;
  font-size: 0.88rem;
  font-weight: 500;
  box-shadow: 0 4px 14px rgba(245, 158, 11, 0.08);
  animation: quizAlertSlide 0.2s ease-out;
}
.quiz-validation-alert .alert-icon {
  font-size: 1.15rem;
  color: #d97706;
  display: inline-flex;
  align-items: center;
  flex-shrink: 0;
}
.quiz-validation-alert .alert-body {
  flex: 1;
  line-height: 1.45;
}
@keyframes quizAlertSlide {
  from { opacity: 0; transform: translateY(-4px); }
  to { opacity: 1; transform: translateY(0); }
}

@media (max-width:992px){
  .quiz-topbar{grid-template-columns:1fr;gap:12px}
  .quiz-topbar .topbar-exit,.quiz-topbar .topbar-left,.quiz-topbar .topbar-right{justify-self:stretch}
  .quiz-topbar .topbar-left{text-align:left;padding:0}
  .topbar-stats{justify-content:flex-start}
  .q-layout{grid-template-columns:1fr}
  .q-right{order:-1}
  .contImage{height:auto;min-height:220px;max-height:52vh}
  .questions-head{flex-direction:column;align-items:stretch}
  .questions-meta{text-align:center;white-space:normal}
  .q-card{height:auto;max-height:none;overflow:visible}
  #qHost{overflow:visible}
  .nav-footer{flex-direction:column;align-items:stretch !important;gap:10px}
  .nav-footer .btn{width:100%;min-width:0}
  .nav-footer > .d-flex{width:100%;flex-direction:column}
}
</style>

<div class="container-xxl<?php echo $catchupMode ? ' quiz-catchup-mode' : ''; ?>">
  <form method="POST" action="" id="quizForm">
    <!-- Header -->
    <div class="glass p-3 mb-3 quiz-topbar<?php echo $reviewMode ? ' quiz-topbar--review' : ''; ?>">
      <?php if(!$reviewMode): ?>
      <div class="topbar-exit">
        <button type="button" class="btn-exit" onclick="exitQuiz(event)">
          <i class="fa-solid fa-arrow-left"></i> Sortir
        </button>
      </div>
      <?php endif; ?>
      <div class="topbar-left">
        <div class="h3 mb-1 fw-bolder">Tests Théoriques</div>
        <div class="text-muted"><?php echo htmlspecialchars((string)($paper['label'] ?? 'Épreuve')); ?><?php echo $reviewMode?' — <span style="color:#111827">Lecture seule</span>':''; ?></div>
      </div>
      <div class="topbar-right">
        <div class="topbar-stats">
          <div class="badge-time">
            <span class="timer-icon" aria-hidden="true"><i class="fa-regular fa-clock"></i></span>
            <span id="timer_sec"><?php printf('%02d:%02d:%02d',$initHr,$initMn,$initSc); ?></span>
          </div>
          <div class="badge-progress">
            <div class="badge-progress-label">Réponses</div>
            <div class="badge-progress-value"><span id="answeredCount">0</span> / <span id="answeredTotalTop">0</span></div>
            <div class="badge-progress-track"><div class="badge-progress-fill" id="answeredProgressFill"></div></div>
          </div>
        </div>
      </div>
    </div>

    <?php if ($shouldFocusOrientation): ?>
      <div class="glass p-3 mb-3" style="border-left:4px solid #1273ea">
        <div class="fw-bold mb-1">QCM validé ✅ — complétez vos préférences</div>
        <div class="text-muted">Merci de répondre aux <strong>questions d’orientation</strong> (pages <?php echo $orientationStartIndex+1; ?> à <?php echo $orientationStartIndex+count($orientationDocs); ?>).</div>
        <button type="button" class="btn btn-sm btn-primary mt-2" id="goOrientation">Aller aux questions d’orientation</button>
      </div>
    <?php endif; ?>

    <?php if ($catchupMode && !$reviewMode): ?>
      <div class="glass p-3 mb-3 catchup-banner">
        <div class="fw-bold mb-1">Mode rattrapage QCM</div>
        <div class="text-muted">
          Vous pouvez répondre à <strong><?php echo count($catchupIndices); ?></strong> question(s) non répondues
          (environ <?php echo (int)(CATCHUP_SECONDS_PER_QUESTION / 60); ?> min par question).
          Temps restant : <strong><?php printf('%02d:%02d:%02d', $initHr, $initMn, $initSc); ?></strong>.
        </div>
      </div>
    <?php endif; ?>

    <div class="row g-4">
      <!-- Zone principale -->
      <div class="col-12">

        <div class="glass p-3 questions-panel">
          <div class="questions-head">
            <div class="questions-title-block">
              <div class="questions-total"><span id="questionsTotalLabel">—</span> questions au total</div>
            </div>
            <div class="questions-meta">
              Question <strong id="currentQuestionLabel">1</strong> sur <span class="range-total" id="rangeTotal">—</span>
            </div>
          </div>
          <div class="q-nav-wrap">
            <button type="button" id="navPrevChunk" class="q-nav-btn" title="Défiler vers la gauche">‹</button>
            <div class="q-grid-viewport" id="qGridViewport">
              <div id="qGrid" class="q-grid"></div>
            </div>
            <button type="button" id="navNextChunk" class="q-nav-btn" title="Défiler vers la droite">›</button>
          </div>
          <div class="questions-nav-hint" id="navHint" hidden>
            <i class="fa-solid fa-arrows-left-right" aria-hidden="true"></i>
            Utilisez les flèches pour parcourir toutes les questions
          </div>
        </div>

        <div class="glass q-card">
          <!-- Timer + timeup hidden -->
          <input type="hidden" id="clock" name="timer" value="<?php printf('%02d:%02d:%02d',$initHr,$initMn,$initSc); ?>">
          <input type="hidden" id="hr" name="hr" value="<?php echo $initHr; ?>">
          <input type="hidden" id="mn" name="mn" value="<?php echo $initMn; ?>">
          <input type="hidden" id="sc" name="sc" value="<?php echo $initSc; ?>">
          <input type="hidden" id="timeup" name="timeup" value="0">

          <div id="qHost">
            <?php
            $ansIndex=0;
            $given = is_array($examArray['answers'] ?? null) ? $examArray['answers'] : [];

            // ----- QCM -----
            foreach ($orderedQ as $qdoc):
              $qid   = (string)$qdoc['_id'];
              $label = (string)($qdoc['label'] ?? '');
              $ref   = (string)($qdoc['ref'] ?? '');
              $p1=(string)($qdoc['proposal1']??''); $p2=(string)($qdoc['proposal2']??'');
              $p3=(string)($qdoc['proposal3']??''); $p4=(string)($qdoc['proposal4']??'');
              $img=(string)($qdoc['image'] ?? '');
              $prev = (string)($given[$ansIndex] ?? 'null');
              $qcmIdx = $ansIndex;
              $isCatchupTarget = $catchupMode && in_array($qcmIdx, $catchupIndices, true);
              $qcmHidden = $catchupMode && !$isCatchupTarget;
              $qcmLocked = $lockQcmBase && !$isCatchupTarget;
            ?>
            <div class="q-item<?php echo $qcmHidden ? ' q-catchup-hidden' : ''; ?>" data-type="qcm" data-qcm-index="<?php echo $qcmIdx; ?>">
              <input type="hidden" name="questionsTag[]" value="<?php echo htmlspecialchars($qid); ?>">
              <p class="quiz-form__question fw-bold q-label">
                <?php echo ($ansIndex+1).' - '.htmlspecialchars($label); ?>
                <?php if ($ref!==''): ?><span class="badge ref-badge"><?php echo htmlspecialchars($ref); ?></span><?php endif; ?>
              </p>
              <div class="q-layout">
                <div class="q-left">
                  <?php
                    $name='answer'.$ansIndex;
                    $opts = array_values(array_filter([$p1, $p2, $p3, $p4], function($x) { return $x !== ''; }));
                    foreach ($opts as $opt):
                      $checked = ($prev === $opt) ? ' checked' : '';
                      $disabled = $qcmLocked ? ' disabled' : '';
                  ?>
                    <label class="quiz-form__ans">
                      <input type="radio" onclick="checkedRadio()" name="<?php echo $name; ?>" value="<?php echo htmlspecialchars($opt); ?>"<?php echo $checked.$disabled; ?>>
                      <span class="text"><?php echo htmlspecialchars($opt); ?></span>
                    </label>
                  <?php endforeach; ?>
                  <?php if ($qcmLocked && $prev !== 'null' && $prev !== ''): ?>
                    <input type="hidden" name="<?php echo $name; ?>" value="<?php echo htmlspecialchars($prev); ?>">
                  <?php endif; ?>
                  <label class="quiz-form__ans" hidden>
                    <input type="radio" onclick="checkedRadio()" name="<?php echo $name; ?>" value="null"<?php echo (!in_array($prev,$opts,true)?' checked':''); ?> <?php echo $qcmLocked?'disabled':''; ?>>
                    <span class="text"></span>
                  </label>
                </div>

                <div class="q-right">
                  <?php if ($img!==''): ?>
                    <div class="contImage"><img alt="Illustration de la question" src="<?php echo htmlspecialchars(quiz_image_src($img)); ?>" onerror="window.quizImageBroken && window.quizImageBroken(this)"></div>
                  <?php else: ?>
                    <div class="contImage is-fallback">
                      <div class="img-fallback">
                        <img alt="CFAO Mobility" src="../../public/images/logo-cfao.png">
                      </div>
                    </div>
                  <?php endif; ?>
                  <?php if (!$reviewMode):
                    $reportKey = (string)$paperOid . ':' . $qid;
                    $qAlreadyReported = !empty($paperReportedQuestionIds[$qid])
                      || !empty($_SESSION['quiz_question_reports'][$reportKey]);
                  ?>
                    <button type="button"
                      class="btn-report-question<?php echo $qAlreadyReported ? ' is-reported' : ''; ?>"
                      data-qid="<?php echo htmlspecialchars($qid); ?>"
                      data-qref="<?php echo htmlspecialchars($ref); ?>"
                      data-qlabel="<?php echo htmlspecialchars($label); ?>"
                      <?php echo $qAlreadyReported ? ' disabled' : ''; ?>>
                      <?php if ($qAlreadyReported): ?>
                        <i class="fa-solid fa-check" aria-hidden="true"></i>
                        Déjà signalé
                      <?php else: ?>
                        <i class="fa-solid fa-flag" aria-hidden="true"></i>
                        Signaler — problème d'image
                      <?php endif; ?>
                    </button>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <?php $ansIndex++; endforeach; ?>

            <?php
            // ----- ORIENTATION en pages #N+1, N+2, ...
            $startIndex = $ansIndex;
            foreach ($orientationDocs as $i => $od):
              $oidStr = (string)$od['_id'];
              $minSel = (int)($od['minSelect'] ?? 0);
              $maxSel = (int)($od['maxSelect'] ?? 0);
              $opts   = to_php_array($od['options'] ?? []);
              $prevCh = isset($prevOrientation[$oidStr]) && is_array($prevOrientation[$oidStr]) ? $prevOrientation[$oidStr] : [];
              $singleSelect = ($maxSel === 1);
              $num = $startIndex + $i + 1;
              $dis = $lockOrientation ? ' disabled' : '';
            ?>
            <div class="q-item" data-type="orient" data-min="<?php echo $minSel; ?>" data-max="<?php echo $maxSel; ?>">
              <p class="quiz-form__question fw-bold q-label">
                <?php echo $num.' - '.htmlspecialchars((string)$od['title']); ?>
              </p>
              <div class="orient-hint mb-2">Ces réponses ne sont pas notées. Elles orientent votre affectation.</div>
              <div class="orient-hint mb-3">
                <?php if ($singleSelect): ?>
                  Choisir <strong>une seule</strong> option.
                <?php elseif ($minSel>0 && $maxSel>0): ?>
                  Choisir entre <strong><?php echo $minSel; ?></strong> et <strong><?php echo $maxSel; ?></strong> options.
                <?php elseif ($minSel>0 && $maxSel===0): ?>
                  Choisir au moins <strong><?php echo $minSel; ?></strong> option(s).
                <?php elseif ($maxSel>0): ?>
                  Choisir au plus <strong><?php echo $maxSel; ?></strong> option(s).
                <?php endif; ?>
              </div>
              <input type="hidden" name="orientationTag[]" value="<?php echo htmlspecialchars($oidStr); ?>">

              <div class="<?php echo count($opts) > 4 ? 'orient-opts-grid' : ''; ?>">
              <?php foreach ($opts as $op): if(!is_string($op) || $op==='') continue;
                $checked = in_array($op, $prevCh, true) ? ' checked' : ''; ?>
                <label class="quiz-form__ans" style="margin:8px 0;">
                  <?php if ($singleSelect): ?>
                    <input type="radio" class="orient-opt" name="orient[<?php echo htmlspecialchars($oidStr); ?>]" value="<?php echo htmlspecialchars($op); ?>"<?php echo $checked.$dis; ?>>
                  <?php else: ?>
                    <input type="checkbox" class="orient-opt" name="orient[<?php echo htmlspecialchars($oidStr); ?>][]" value="<?php echo htmlspecialchars($op); ?>"<?php echo $checked.$dis; ?>>
                  <?php endif; ?>
                  <span class="text"><?php echo htmlspecialchars($op); ?></span>
                </label>
              <?php endforeach; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>

          <div class="d-flex justify-content-between align-items-center mt-3 nav-footer">
            <button type="button" class="btn btn-outline-secondary" id="btnPrev">‹ Précédent</button>
            <div class="d-flex gap-2">
              <?php if(!$reviewMode): ?>
                <button type="submit" class="btn btn-secondary" name="save">Sauvegarder</button>
                <?php /* #btnNext : navigation Précédent/Suivant ; sur la dernière étape, finishSubmitName()
                         soumet valid, valid_orientation (temps supplémentaire orientation) ou valid_catchup. */ ?>
                <button type="button" class="btn btn-primary" id="btnNext">Suivant ›</button>
              <?php else: ?>
                <a class="btn btn-secondary" href="./candidatePaperHome.php">Retour</a>
              <?php endif; ?>
            </div>
          </div>

          <?php if (!empty($error_msg)): ?>
            <div class="alert alert-warning quiz-validation-alert mt-3 mb-0" role="alert">
              <span class="alert-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
              <div class="alert-body"><?php echo htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </form>

  <?php if ($pendingCatchupChoice && !$reviewMode): ?>
  <div id="catchupChoiceModal" class="exit-modal show" aria-hidden="false">
    <div class="exit-modal-card" role="dialog" aria-modal="true" aria-labelledby="catchupChoiceTitle">
      <h4 id="catchupChoiceTitle" class="exit-modal-title">Orientation terminée</h4>
      <p class="exit-modal-text">
        Vous avez encore du temps sur le chrono
        (<strong id="catchupModalTimer"><?php printf('%02d:%02d:%02d', $initHr, $initMn, $initSc); ?></strong>).
        <br><br>
        Souhaitez-vous <strong>clôturer votre examen</strong> maintenant, ou
        <strong>rattraper <?php echo (int)$catchupMaxOffered; ?> question(s) QCM</strong>
        non répondues (environ <?php echo (int)(CATCHUP_SECONDS_PER_QUESTION / 60); ?> min par question) ?
      </p>
      <div class="exit-modal-actions">
        <button type="submit" form="quizForm" name="close_exam_after_orientation" value="1" class="btn-modal-cancel">
          Clôturer l'examen
        </button>
        <button type="submit" form="quizForm" name="start_catchup" value="1" class="btn-modal-confirm">
          Rattraper <?php echo (int)$catchupMaxOffered; ?> question(s)
        </button>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div id="exitModal" class="exit-modal" aria-hidden="true">
    <div class="exit-modal-card" role="dialog" aria-modal="true" aria-labelledby="exitModalTitle">
      <h4 id="exitModalTitle" class="exit-modal-title">Quitter le test ?</h4>
      <p class="exit-modal-text">Vos réponses sont sauvegardées automatiquement. Vous pourrez reprendre plus tard.</p>
      <div class="exit-modal-actions">
        <button type="button" class="btn-modal-cancel" onclick="closeExitModal()">Rester</button>
        <button type="button" class="btn-modal-confirm" onclick="confirmExitQuiz()">Quitter</button>
      </div>
    </div>
  </div>

  <div id="bonusModal" class="exit-modal" aria-hidden="true">
    <div class="exit-modal-card" role="dialog" aria-modal="true" aria-labelledby="bonusModalTitle">
      <h4 id="bonusModalTitle" class="exit-modal-title">Temps supplémentaire</h4>
      <p id="bonusModalText" class="exit-modal-text"></p>
      <div class="exit-modal-actions">
        <button type="button" class="btn-modal-confirm" onclick="acceptBonus()">OK</button>
      </div>
    </div>
  </div>
</div>
<div id="reportToast" class="report-toast" role="status" aria-live="polite"></div>

<script>
window.quizImageBroken = function(imgEl) {
  const right = imgEl && imgEl.closest ? imgEl.closest('.q-right') : null;
  if (!right) return;
  const btn = right.querySelector('.btn-report-question');
  if (btn) btn.removeAttribute('hidden');
  const wrap = imgEl.closest('.contImage');
  if (wrap) {
    wrap.classList.add('is-fallback');
    wrap.innerHTML = '<div class="img-fallback"><img alt="CFAO Mobility" src="../../public/images/logo-cfao.png"></div>';
  }
};

function showReportToast(message) {
  const el = document.getElementById('reportToast');
  if (!el) return;
  el.textContent = message;
  el.classList.add('show');
  clearTimeout(showReportToast._t);
  showReportToast._t = setTimeout(() => el.classList.remove('show'), 3200);
}

function setReportButtonReported(btn, sent) {
  btn.disabled = true;
  btn.classList.remove('is-loading');
  btn.classList.add('is-reported');
  btn.innerHTML = sent
    ? '<i class="fa-solid fa-check" aria-hidden="true"></i> Signalement envoyé'
    : '<i class="fa-solid fa-check" aria-hidden="true"></i> Déjà signalé';
}

document.querySelectorAll('.btn-report-question').forEach((btn) => {
  btn.addEventListener('click', async () => {
    if (btn.disabled || btn.classList.contains('is-reported') || btn.classList.contains('is-loading')) return;
    const prevHtml = btn.innerHTML;
    btn.disabled = true;
    btn.classList.add('is-loading');
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Envoi en cours…';
    const fd = new FormData();
    fd.set('report_question', '1');
    fd.set('question_id', btn.dataset.qid || '');
    fd.set('question_ref', btn.dataset.qref || '');
    fd.set('question_label', btn.dataset.qlabel || '');
    const paperId = new URLSearchParams(window.location.search).get('paper');
    if (paperId) fd.set('paper', paperId);
    try {
      const res = await fetch(window.location.pathname + window.location.search, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
      });
      const raw = await res.text();
      let data = null;
      try { data = JSON.parse(raw); } catch (parseErr) { data = null; }
      if (!data) {
        showReportToast('Réponse serveur invalide. Réessayez.');
        btn.disabled = false;
        btn.classList.remove('is-loading');
        btn.innerHTML = prevHtml;
        return;
      }
      showReportToast(data.message || (data.ok ? 'Signalement envoyé.' : 'Erreur.'));
      if (data.ok) {
        setReportButtonReported(btn, !data.already);
      } else {
        btn.disabled = false;
        btn.classList.remove('is-loading');
        btn.innerHTML = prevHtml;
      }
    } catch (e) {
      showReportToast('Erreur réseau. Réessayez.');
      btn.disabled = false;
      btn.classList.remove('is-loading');
      btn.innerHTML = prevHtml;
    }
  });
});

// Loupe : zoom réel au survol (lentille qui suit la souris)
(function initQuizImageMagnifier() {
  const ZOOM = 3;
  const LENS = 180;
  const lens = document.createElement('div');
  lens.className = 'img-magnifier-lens';
  document.body.appendChild(lens);

  function hideLens() {
    lens.style.display = 'none';
  }

  function displayedImageRect(img, box) {
    const nw = img.naturalWidth;
    const nh = img.naturalHeight;
    if (!nw || !nh) return null;
    const cw = box.clientWidth;
    const ch = box.clientHeight;
    const scale = Math.min(cw / nw, ch / nh);
    const dw = nw * scale;
    const dh = nh * scale;
    return {
      ox: (cw - dw) / 2,
      oy: (ch - dh) / 2,
      dw,
      dh,
    };
  }

  document.querySelectorAll('.contImage').forEach((box) => {
    if (box.classList.contains('is-fallback')) return;
    const img = box.querySelector('img');
    if (!img) return;

    const move = (e) => {
      if (!img.complete || !img.naturalWidth) return;
      const rect = box.getBoundingClientRect();
      const mx = e.clientX - rect.left;
      const my = e.clientY - rect.top;
      const disp = displayedImageRect(img, box);
      if (!disp) return hideLens();

      if (mx < disp.ox || mx > disp.ox + disp.dw || my < disp.oy || my > disp.oy + disp.dh) {
        return hideLens();
      }

      const relX = (mx - disp.ox) / disp.dw;
      const relY = (my - disp.oy) / disp.dh;
      const bgW = disp.dw * ZOOM;
      const bgH = disp.dh * ZOOM;

      lens.style.display = 'block';
      lens.style.width = LENS + 'px';
      lens.style.height = LENS + 'px';
      lens.style.left = (e.clientX - LENS / 2) + 'px';
      lens.style.top = (e.clientY - LENS / 2) + 'px';
      lens.style.backgroundImage = 'url("' + img.currentSrc + '")';
      lens.style.backgroundSize = bgW + 'px ' + bgH + 'px';
      lens.style.backgroundPosition = (-(relX * bgW - LENS / 2)) + 'px ' + (-(relY * bgH - LENS / 2)) + 'px';
    };

    box.addEventListener('mouseenter', move);
    box.addEventListener('mousemove', move);
    box.addEventListener('mouseleave', hideLens);
    img.addEventListener('load', hideLens);
  });

  window.addEventListener('scroll', hideLens, true);
})();

// Timer (si revue: on fige). À 0 => timeup=1 + submit auto
const isReview = <?php echo $reviewMode ? 'true' : 'false'; ?>;
const catchupMode = <?php echo $catchupMode ? 'true' : 'false'; ?>;
const catchupIndices = <?php echo json_encode($catchupIndices); ?>;
const pendingCatchupChoice = <?php echo $pendingCatchupChoice ? 'true' : 'false'; ?>;
const catchupNavTotal = <?php echo (int)$catchupNavTotal; ?>;
const hasOrientation = <?php echo !empty($orientationDocs) ? 'true' : 'false'; ?>;
const lockQcmActive = <?php echo ($lockQcmBase && !$catchupMode) ? 'true' : 'false'; ?>;
let hr = <?php echo (int)$initHr; ?>, mn = <?php echo (int)$initMn; ?>, sc = <?php echo (int)$initSc; ?>;
let time = (Number(hr)*3600)+(Number(mn)*60)+Number(sc);
const countDown=document.getElementById('timer_sec');
const formEl=document.getElementById('quizForm');
const timeupEl=document.getElementById('timeup');
const exitModalEl=document.getElementById('exitModal');
let submittedOnTimeout=false;
function exitQuiz(e){
  if (e) e.preventDefault();
  if (exitModalEl) {
    exitModalEl.classList.add('show');
    exitModalEl.setAttribute('aria-hidden','false');
  }
}
function closeExitModal(){
  if (!exitModalEl) return;
  exitModalEl.classList.remove('show');
  exitModalEl.setAttribute('aria-hidden','true');
}
function confirmExitQuiz(){
  closeExitModal();
  try { if (typeof autoSave === 'function') autoSave(); } catch(err) {}
  setTimeout(function(){ window.location.href = "./candidatePaperHome.php"; }, 180);
}
if (exitModalEl) {
  exitModalEl.addEventListener('click', function(ev){
    if (ev.target === exitModalEl) closeExitModal();
  });
}
document.addEventListener('keydown', function(ev){
  if (ev.key === 'Escape') closeExitModal();
});

function tick(){
  const H=Math.max(0,Math.floor(time/3600));
  const M=Math.max(0,Math.floor((time/60)-(H*60)));
  const S=Math.max(0,time-((H*3600)+(M*60)));
  const HH=(H<10?'0':'')+H, MM=(M<10?'0':'')+M, SS=(S<10?'0':'')+S;
  countDown.textContent=`${HH}:${MM}:${SS}`;
  const catchupModalTimer = document.getElementById('catchupModalTimer');
  if (catchupModalTimer) catchupModalTimer.textContent = `${HH}:${MM}:${SS}`;
  document.getElementById('clock').value=`${HH}:${MM}:${SS}`;
  document.getElementById('hr').value=HH; document.getElementById('mn').value=MM; document.getElementById('sc').value=SS;
  time--;
  if(time < 0 && !submittedOnTimeout && !isReview){
    submittedOnTimeout=true;
    timeupEl.value='1';
    try{ formEl.submit(); }catch(e){}
  }
}
tick();
let tId=null;
if(!isReview){ tId=setInterval(()=>{ if(time>=-1){ tick(); } else { clearInterval(tId); } },1000); }

// Réserve en bas pour le footer candidat.
function getViewportBottomReserve() {
  const pageFooter = document.querySelector('.cand-footer');
  return pageFooter ? Math.max(32, pageFooter.offsetHeight + 16) : 32;
}

// Ajuste la hauteur de la card de manière spacieuse et sans écrasement forcé.
function fitQuizCardToViewport() {
  const card = document.querySelector('.q-card');
  const host = document.getElementById('qHost');
  if (!card || !host) return;

  if (window.matchMedia('(max-width: 992px)').matches) {
    card.style.height = '';
    card.style.maxHeight = '';
    card.style.minHeight = '';
    host.style.maxHeight = '';
    host.style.overflowY = '';
    return;
  }

  const topbar = document.querySelector('.quiz-topbar');
  const panel = document.querySelector('.questions-panel');
  const topbarH = topbar ? topbar.offsetHeight + 16 : 80;
  const panelH = panel ? panel.offsetHeight + 16 : 120;
  const headerTotal = topbarH + panelH + 20;
  const bottomReserve = getViewportBottomReserve();
  const available = Math.floor(window.innerHeight - headerTotal - bottomReserve);
  const minH = 460;
  const maxH = Math.max(minH, available);

  // Remettre à auto pour laisser la question respirer naturellement
  card.style.height = 'auto';
  card.style.maxHeight = '';
  card.style.minHeight = minH + 'px';
  host.style.maxHeight = '';
  host.style.overflowY = 'visible';

  const visibleItem = host.querySelector('.q-item.is-visible');
  const layout = visibleItem ? visibleItem.querySelector('.q-layout') : null;
  const qLeft = visibleItem ? visibleItem.querySelector('.q-left') : null;
  if (layout) { layout.style.height = 'auto'; layout.style.maxHeight = ''; layout.style.overflowY = ''; }
  if (qLeft) { qLeft.style.maxHeight = ''; qLeft.style.overflowY = ''; }

  // Ne restreindre que si le contenu est réellement plus grand que tout l'écran disponible
  if (card.scrollHeight > maxH && maxH > minH + 60) {
    card.style.height = maxH + 'px';
    card.style.maxHeight = maxH + 'px';
    const footer = card.querySelector('.nav-footer');
    const footerH = footer ? footer.offsetHeight + 16 : 60;
    const cardStyle = window.getComputedStyle(card);
    const cardPad = parseFloat(cardStyle.paddingTop) + parseFloat(cardStyle.paddingBottom);
    const hostMax = Math.max(280, maxH - footerH - cardPad);
    host.style.maxHeight = hostMax + 'px';
    host.style.overflowY = 'auto';

    if (layout && qLeft && !catchupMode) {
      const qRight = layout.querySelector('.q-right');
      const rightH = qRight ? qRight.offsetHeight : 0;
      const leftMax = Math.max(180, hostMax - 20);
      if (qLeft.scrollHeight > leftMax) {
        qLeft.style.maxHeight = leftMax + 'px';
        qLeft.style.overflowY = 'auto';
      }
    }
  }
}
window.addEventListener('resize', fitQuizCardToViewport);
window.addEventListener('load', fitQuizCardToViewport);

function resizeActiveQuestionLayout() {
  fitQuizCardToViewport();
}

// Navigation 1/1 + grille (QCM + Orientation)
const items=Array.from(document.querySelectorAll('#qHost .q-item'));
const btnPrev=document.getElementById('btnPrev'), btnNext=document.getElementById('btnNext');
const answeredCountEl=document.getElementById('answeredCount');
const answeredTotalTopEl=document.getElementById('answeredTotalTop');
const answeredProgressFillEl=document.getElementById('answeredProgressFill');
const gridEl=document.getElementById('qGrid');
const gridViewportEl=document.getElementById('qGridViewport');
const rangeTotalEl=document.getElementById('rangeTotal');
const currentQuestionLabelEl=document.getElementById('currentQuestionLabel');
const questionsTotalLabelEl=document.getElementById('questionsTotalLabel');
const navHintEl=document.getElementById('navHint');
const navPrevChunk=document.getElementById('navPrevChunk');
const navNextChunk=document.getElementById('navNextChunk');
let idx=0;

function isNavigableItem(i) {
  const it = items[i];
  if (!it) return false;
  if (catchupMode) {
    if ((it.dataset.type || '') !== 'qcm') return false;
    const qIdx = parseInt(it.dataset.qcmIndex || '-1', 10);
    return catchupIndices.includes(qIdx);
  }
  return true;
}
function getNavigableIndices() {
  const out = [];
  for (let i = 0; i < items.length; i++) {
    if (isNavigableItem(i)) out.push(i);
  }
  return out;
}
function nearestNavigableIndex(target) {
  const nav = getNavigableIndices();
  if (!nav.length) return 0;
  if (nav.includes(target)) return target;
  for (let i = 0; i < nav.length; i++) {
    if (nav[i] >= target) return nav[i];
  }
  return nav[nav.length - 1];
}

function isOnOrientationItem(itemIdx) {
  const it = items[itemIdx];
  return !!(it && (it.dataset.type || '') === 'orient');
}

function finishSubmitName() {
  if (catchupMode) return 'valid_catchup';
  if (hasOrientation && (lockQcmActive || isOnOrientationItem(idx))) {
    return 'valid_orientation';
  }
  return 'valid';
}

function updateNextButton() {
  if (!btnNext) return;
  const nav = getNavigableIndices();
  const pos = nav.indexOf(idx);
  const isLast = pos >= 0 && pos === nav.length - 1;
  if (catchupMode) {
    btnNext.textContent = isLast ? 'Terminer le rattrapage ›' : 'Suivant ›';
    return;
  }
  if (!isLast) {
    btnNext.textContent = 'Suivant ›';
    return;
  }
  if (lockQcmActive || isOnOrientationItem(idx)) {
    btnNext.textContent = 'Valider mes préférences ›';
    return;
  }
  btnNext.textContent = 'Terminer ›';
}

function submitFinishExam() {
  autoSave();
  const name = finishSubmitName();
  let btn = formEl.querySelector(`button[name="${name}"]`);
  if (!btn) {
    btn = document.createElement('button');
    btn.type = 'submit';
    btn.name = name;
    btn.value = '1';
    btn.hidden = true;
    formEl.appendChild(btn);
  }
  btn.click();
}

function scrollGridBy(delta) {
  if (!gridEl) return;
  gridEl.scrollBy({ left: delta, behavior: 'smooth' });
}
function updateScrollChrome() {
  if (!gridEl) return;
  const canScroll = gridEl.scrollWidth > gridEl.clientWidth + 2;
  if (navHintEl) navHintEl.hidden = !canScroll;
  if (navPrevChunk) navPrevChunk.disabled = gridEl.scrollLeft <= 2;
  if (navNextChunk) navNextChunk.disabled = gridEl.scrollLeft + gridEl.clientWidth >= gridEl.scrollWidth - 2;
  if (gridViewportEl) {
    gridViewportEl.classList.toggle('can-scroll-left', gridEl.scrollLeft > 2);
    gridViewportEl.classList.toggle('at-end', gridEl.scrollLeft + gridEl.clientWidth >= gridEl.scrollWidth - 2);
  }
}
function scrollCurrentDotIntoView() {
  if (!gridEl) return;
  const dot = gridEl.querySelector('.q-dot.current');
  if (!dot) return;
  const pad = 8;
  const viewLeft = gridEl.scrollLeft;
  const viewWidth = gridEl.clientWidth;
  const dotLeft = dot.offsetLeft;
  const dotWidth = dot.offsetWidth;
  if (dotLeft < viewLeft + pad) {
    gridEl.scrollLeft = Math.max(0, dotLeft - pad);
  } else if (dotLeft + dotWidth > viewLeft + viewWidth - pad) {
    gridEl.scrollLeft = dotLeft + dotWidth - viewWidth + pad;
  }
  updateScrollChrome();
}
function updateCurrentQuestionMeta() {
  if (currentQuestionLabelEl) {
    currentQuestionLabelEl.textContent = String(displayQuestionNumber(idx));
  }
}
function show(i){
  if(!items.length) return;
  idx = nearestNavigableIndex(i);
  items.forEach(el=>el.classList.remove('is-visible'));
  items[idx].classList.add('is-visible');
  const nav = getNavigableIndices();
  const navPos = nav.indexOf(idx);
  btnPrev.disabled = (navPos <= 0);
  updateNextButton();
  updateTotals(); updateGridStates(); markCurrentDot();
  updateCurrentQuestionMeta();
  fitQuizCardToViewport();
}
function answeredCount(){
  let c=0;
  if (catchupMode) {
    catchupIndices.forEach((qIdx) => {
      const checked = formEl.querySelector(`input[name="answer${qIdx}"]:checked`);
      if (checked && checked.value !== 'null') c++;
    });
    return c;
  }
  for(let i=0;i<items.length;i++){
    const it=items[i];
    const kind=it.dataset.type||'qcm';
    if (kind==='qcm'){
      const qIdx = parseInt(it.dataset.qcmIndex || String(i), 10);
      const checked=formEl.querySelector(`input[name="answer${qIdx}"]:checked`);
      if(checked && checked.value!=='null') c++;
    } else {
      const min=Number(it.dataset.min||'0');
      const checked=it.querySelectorAll('input.orient-opt:checked').length;
      if (checked>0 && (min===0 || checked>=min)) c++;
    }
  }
  return c;
}
function updateTotals(){
  const answered = answeredCount();
  const total = catchupMode ? catchupNavTotal : items.length;
  if (answeredCountEl) answeredCountEl.textContent = answered;
  if (answeredTotalTopEl) answeredTotalTopEl.textContent = total;
  if (answeredProgressFillEl && total > 0) {
    answeredProgressFillEl.style.width = Math.min(100, Math.round((answered / total) * 100)) + '%';
  }
}
// Une sauvegarde à la fois : deux requêtes rapprochées peuvent être traitées dans le désordre et l'ancien état
// écraserait le nouveau. Un clic pendant l'envoi relance une sauvegarde (état à jour) à la fin de celui-ci.
var autoSaveBusy = false, autoSaveAgain = false;
function autoSave(){
  if(isReview) return;
  if (autoSaveBusy) { autoSaveAgain = true; return; }
  autoSaveBusy = true;
  var fd=new FormData(formEl);
  fd.set('autosave','1');
  const paperId = new URLSearchParams(window.location.search).get('paper');
  if (paperId) fd.set('paper', paperId);
  if (bonusAck) fd.set('bonus_ack', bonusAck);
  // Session fermée (déconnexion depuis connectedUsers.php ou expirée) : la page renvoie vers la connexion
  fetch(window.location.pathname + window.location.search,{method:'POST',body:fd,credentials:'same-origin'})
    .then(function(r){
      if (r.redirected) { window.location.href = r.url; return; }
      // La réponse peut être précédée d'une balise <link> émise par un include : on lit le JSON à partir de {"ok"
      return r.text().then(function(t){
        var i = t.indexOf('{"ok"');
        var j = i >= 0 ? JSON.parse(t.slice(i)) : null;
        if (j && j.bonus) offerBonus(j.bonus); else if (j) bonusAck = '';
      });
    })
    .catch(function(){})
    .then(function(){
      autoSaveBusy = false;
      if (autoSaveAgain) { autoSaveAgain = false; autoSave(); }
    });
}
// Sauvegarde aussi sans action du candidat : le temps supplémentaire lui parvient en moins d'une minute.
if(!isReview){ setInterval(autoSave, 60000); }

// Temps supplémentaire : ajouté au chrono au clic sur OK, confirmé au serveur par les autosaves suivants (bonus_ack).
var bonusAck = '', bonusOffer = null;
const bonusModalEl = document.getElementById('bonusModal');
function offerBonus(b){
  if (b.id === bonusAck || bonusOffer || !bonusModalEl) return;
  bonusOffer = b;
  document.getElementById('bonusModalText').textContent = b.minutes + ' minutes supplémentaires vous sont accordées pour cette épreuve.';
  bonusModalEl.classList.add('show');
  bonusModalEl.setAttribute('aria-hidden','false');
}
function acceptBonus(){
  if (!bonusOffer) return;
  time += bonusOffer.minutes * 60;
  tick();
  bonusAck = bonusOffer.id;
  bonusOffer = null;
  bonusModalEl.classList.remove('show');
  bonusModalEl.setAttribute('aria-hidden','true');
  autoSave();
}
function displayQuestionNumber(itemIndex) {
  const it = items[itemIndex];
  if (catchupMode && it && it.dataset.qcmIndex !== undefined) {
    return parseInt(it.dataset.qcmIndex, 10) + 1;
  }
  return itemIndex + 1;
}
function grandQuestionTotal() {
  return catchupMode ? catchupNavTotal : items.length;
}
function buildGrid(){
  gridEl.innerHTML='';
  const nav = getNavigableIndices();
  const navList = catchupMode ? nav : items.map((_, i) => i);
  for (let n = 0; n < navList.length; n++) {
    const i = navList[n];
    const b=document.createElement('button');
    b.type='button'; b.className='q-dot';
    b.textContent = String(displayQuestionNumber(i));
    b.dataset.idx=String(i);
    b.addEventListener('click',(function(qi){ return function(){ autoSave(); show(qi); }; })(i));
    gridEl.appendChild(b);
  }
  const grandTotal = grandQuestionTotal();
  if (questionsTotalLabelEl) questionsTotalLabelEl.textContent = String(grandTotal);
  if (rangeTotalEl) rangeTotalEl.textContent = String(grandTotal);
  requestAnimationFrame(function(){
    updateScrollChrome();
    scrollCurrentDotIntoView();
  });
}
function updateGridStates(){
  const dots=Array.from(gridEl.querySelectorAll('.q-dot'));
  dots.forEach((d)=>{
    const i=Number(d.dataset.idx||0);
    const it=items[i];
    const kind=it.dataset.type||'qcm';
    let done=false;
    if (kind==='qcm'){
      const qIdx = parseInt(it.dataset.qcmIndex || String(i), 10);
      const checked=formEl.querySelector(`input[name="answer${qIdx}"]:checked`);
      done = !!checked && checked.value!=='null';
    } else {
      const min=Number(it.dataset.min||'0');
      const chk=it.querySelectorAll('input.orient-opt:checked').length;
      done = (chk>0 && (min===0 || chk>=min));
    }
    d.classList.toggle('done', done);
  });
}
function markCurrentDot(){
  Array.from(gridEl.querySelectorAll('.q-dot')).forEach((d)=>d.classList.toggle('current', Number(d.dataset.idx||0)===idx));
  scrollCurrentDotIntoView();
}
btnPrev.addEventListener('click',function(){
  autoSave();
  const nav = getNavigableIndices();
  const pos = nav.indexOf(idx);
  if (pos > 0) show(nav[pos - 1]);
});
if (btnNext) btnNext.addEventListener('click',function(){
  const nav = getNavigableIndices();
  const pos = nav.indexOf(idx);
  if (pos >= 0 && pos < nav.length - 1) {
    autoSave();
    show(nav[pos + 1]);
  } else if (pos === nav.length - 1) {
    submitFinishExam();
  }
});
if (navPrevChunk) navPrevChunk.addEventListener('click', function(){
  scrollGridBy(-(gridEl.clientWidth * 0.8));
});
if (navNextChunk) navNextChunk.addEventListener('click', function(){
  scrollGridBy(gridEl.clientWidth * 0.8);
});
if (gridEl) gridEl.addEventListener('scroll', updateScrollChrome, { passive: true });
window.addEventListener('resize', updateScrollChrome);
function checkedRadio(){ updateTotals(); updateGridStates(); autoSave(); } window.checkedRadio=checkedRadio;

document.querySelectorAll('.q-item[data-type="orient"]').forEach((item) => {
  const max = Number(item.dataset.max || '0');
  item.querySelectorAll('input.orient-opt').forEach((el) => {
    el.addEventListener('change', () => {
      if (max > 1 && el.type === 'checkbox') {
        const checked = item.querySelectorAll('input.orient-opt:checked');
        if (checked.length > max) el.checked = false;
      }
      checkedRadio();
    });
  });
});

// Démarrage : aller directement sur la question manquante/incomplète si erreur, ou orientation si demandé, ou rattrapage
const orientationStartIndex = <?php echo (int)$orientationStartIndex; ?>;
let startIndex = <?php
  if (isset($first_missing_idx) && $first_missing_idx !== null) {
    echo (int)$first_missing_idx;
  } elseif (isset($first_unanswered_idx) && $first_unanswered_idx !== null) {
    echo (int)$first_unanswered_idx;
  } elseif ($shouldFocusOrientation) {
    echo (int)$orientationStartIndex;
  } else {
    echo 0;
  }
?>;
if (catchupMode && catchupIndices.length && startIndex === 0) {
  startIndex = catchupIndices[0];
}
buildGrid(); show(startIndex);
// Bouton de raccourci vers orientation
const goBtn = document.getElementById('goOrientation');
if (goBtn) goBtn.addEventListener('click', ()=> show(orientationStartIndex));
</script>

<?php closeBackgroundContainer(); ?>
<?php include_once "partials/footer.php"; ?>
