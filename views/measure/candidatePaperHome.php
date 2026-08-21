<?php
// ============================================================================
// Accueil Candidat (Cameroun) — Épreuve Figée (Paper) Factuel Mixed
// Version multi-tests : affiche tous les papers assignés via user.papers[]
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
require_once __DIR__ . '/../../core/mongo_compat.php';

use MongoDB\Client;
use MongoDB\BSON\ObjectId;
use MongoDB\Model\BSONArray;

/* ---------------- Helpers ---------------- */
function to_oid($v){ try{ return ($v && preg_match('/^[0-9a-f]{24}$/i',$v)) ? new ObjectId($v) : null; }catch(\Throwable $e){ return null; } }
function parse_dt_mixed($value){
  if (!is_string($value) || trim($value) === '') return null;
  $value = trim($value);
  $formats = ['Y-m-d H:i:s', 'd-m-Y H:i:s', DATE_ATOM];
  foreach ($formats as $fmt) {
    $dt = \DateTime::createFromFormat($fmt, $value);
    if ($dt instanceof \DateTime) return $dt;
  }
  try { return new \DateTime($value); } catch (\Throwable $e) { return null; }
}
function ago_label(?\DateTime $dt, string $lang = 'FR'): ?string {
  if (!$dt) return null;
  $now = new \DateTime();
  $sec = max(0, $now->getTimestamp() - $dt->getTimestamp());
  if ($lang === 'EN') {
    if ($sec < 60) return 'just now';
    if ($sec < 3600) return floor($sec / 60) . ' min ago';
    if ($sec < 86400) return floor($sec / 3600) . ' h ago';
    return floor($sec / 86400) . ' d ago';
  }
  if ($sec < 60) return "à l'instant";
  if ($sec < 3600) return "il y a ".floor($sec/60)." min";
  if ($sec < 86400) return "il y a ".floor($sec/3600)." h";
  return "il y a ".floor($sec/86400)." j";
}

function format_duration_label(int $totalMin, string $lang = 'FR'): string {
  $totalMin = max(1, $totalMin);
  $h = intdiv($totalMin, 60);
  $m = $totalMin % 60;
  if ($lang === 'EN') {
    if ($h > 0 && $m > 0) return "{$h} h {$m} min";
    if ($h > 0) return "{$h} h";
    return "{$m} min";
  }
  if ($h > 0 && $m > 0) return "{$h} h {$m} min";
  if ($h > 0) return "{$h} h";
  return "{$m} min";
}

function build_guide_duration_desc(array $paperData, string $lang = 'FR'): string {
  if (empty($paperData)) {
    return $lang === 'EN'
      ? 'The clock is set for each test. MCQs first, then a few preference questions at the end — <strong>no impact on your score</strong>.'
      : 'Le chrono est indiqué sur chaque épreuve. D\'abord les QCM, puis quelques questions de préférences en fin de parcours — <strong>sans impact sur votre note</strong>.';
  }

  $durations = array_values(array_unique(array_map(function ($p) {
    return (int)($p['durationMin'] ?? 60);
  }, $paperData)));
  sort($durations);

  if (count($paperData) === 1) {
    $p = $paperData[0];
    $dur = htmlspecialchars(format_duration_label($durations[0], $lang), ENT_QUOTES, 'UTF-8');
    $q = (int)($p['qTotal'] ?? 0);
    $ori = (int)($p['orientationTotal'] ?? 0);
    if ($lang === 'EN') {
      $desc = 'You have <strong>' . $dur . '</strong> ahead of you — take your time. <strong>' . $q . ' MCQs</strong> to tackle';
      if ($ori > 0) {
        $desc .= ', then <strong>' . $ori . ' quick preference questions</strong> (not scored)';
      }
      return $desc . '. You\'ve got this!';
    }
    $desc = 'Vous avez <strong>' . $dur . '</strong> devant vous — prenez votre temps. <strong>' . $q . ' QCM</strong> à relever';
    if ($ori > 0) {
      $desc .= ', puis <strong>' . $ori . ' petites questions</strong> de préférences (sans impact sur votre note)';
    }
    return $desc . '. Vous êtes prêt(e) !';
  }

  if (count($durations) === 1) {
    $dur = htmlspecialchars(format_duration_label($durations[0], $lang), ENT_QUOTES, 'UTF-8');
    if ($lang === 'EN') {
      return 'Plan for <strong>' . $dur . '</strong> per test: MCQs on the menu, with a few preference questions at the end. <strong>Go at your own pace!</strong>';
    }
    return 'Comptez <strong>' . $dur . '</strong> par épreuve : des QCM au programme, et quelques questions de préférences à la fin. <strong>À votre rythme !</strong>';
  }

  $durMin = htmlspecialchars(format_duration_label($durations[0], $lang), ENT_QUOTES, 'UTF-8');
  $durMax = htmlspecialchars(format_duration_label($durations[count($durations) - 1], $lang), ENT_QUOTES, 'UTF-8');
  if ($lang === 'EN') {
    return 'Depending on the test, allow between <strong>' . $durMin . '</strong> and <strong>' . $durMax . '</strong>. MCQs plus preference questions — <strong>you set the pace</strong>.';
  }
  return 'Selon l\'épreuve, prévoyez entre <strong>' . $durMin . '</strong> et <strong>' . $durMax . '</strong>. QCM et questions de préférences — <strong>c\'est vous qui menez</strong>.';
}

function count_qcm_answered(array $answers, int $qTotal): int {
  if ($qTotal <= 0) {
    return 0;
  }
  $n = 0;
  for ($i = 0; $i < $qTotal; $i++) {
    if ((string)($answers[$i] ?? 'null') !== 'null') {
      $n++;
    }
  }
  return $n;
}

function normalize_bson_array($value): array {
  if ($value instanceof BSONArray) {
    return $value->getArrayCopy();
  }
  return is_array($value) ? $value : [];
}

function count_orientation_answered_map($oa): int {
  $oa = normalize_bson_array($oa);
  $n = 0;
  foreach ($oa as $selected) {
    $vals = normalize_bson_array($selected);
    if (count(array_filter($vals, function ($v) {
      return is_string($v) && trim($v) !== '';
    })) > 0) {
      $n++;
    }
  }
  return $n;
}

function count_orientation_answered_results($rows): int {
  $rows = normalize_bson_array($rows);
  $n = 0;
  foreach ($rows as $row) {
    if (!is_array($row)) {
      continue;
    }
    $selected = isset($row['selected']) && is_array($row['selected']) ? $row['selected'] : [];
    if (count(array_filter($selected, function ($v) {
      return is_string($v) && trim($v) !== '';
    })) > 0) {
      $n++;
    }
  }
  return $n;
}

/* ---------------- Mongo ---------------- */
$client   = new Client("mongodb://localhost:27017");
$db       = $client->academy;

$users       = $db->users;
$papers      = $db->papers;
$exams       = $db->exams;
$results     = $db->results;
$allocations = $db->allocations;
$questions   = $db->questions;

/* ---------------- User & Access Control ---------------- */
$userId = (string)$_SESSION["id"];
$user   = $users->findOne(['_id'=>to_oid($userId), 'active'=>true]);
if (!$user) { http_response_code(403); echo "Utilisateur introuvable."; exit(); }

$country = strtoupper(trim((string)($user['country'] ?? '')));
$profile = strtoupper(trim((string)($user['profile'] ?? ($user['typeR'] ?? ''))));
$isCameroon  = in_array($country, ['CAMEROUN','CAMEROON'], true);
$isCandidate = in_array($profile, ['CANDIDAT','CANDIDATE'], true);

if (!$isCameroon || !$isCandidate) {
  http_response_code(403);
  echo "Cette page est réservée aux candidats de CFAO.";
  exit();
}

$userOid = to_oid($userId);

/* ---------------- POST: Démarrer un test ---------------- */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['start']) && $_POST['start']==='1') {
  $startPaperId = $_POST['paper_id'] ?? '';
  $startPaperOid = to_oid($startPaperId);
  if ($startPaperOid) {
    $existing = $allocations->findOne(['user'=>$userOid, 'paper'=>$startPaperOid]);
    if (!$existing) {
      $allocations->insertOne([
        'user'=>$userOid,
        'paper'=>$startPaperOid,
        'type'=>'Factuel',
        'level'=>'Mixed',
        'status'=>'in_progress',
        'active'=>true,
        'created'=>date('Y-m-d H:i:s'),
      ]);
    } elseif (!in_array((string)($existing['status'] ?? ''), ['done'], true)) {
      $allocations->updateOne(['_id'=>$existing['_id']], ['$set'=>['status'=>'in_progress','active'=>true]]);
    }
    header("Location: ./userPaperQuiz.php?paper=".$startPaperId);
    exit();
  }
}

/* ---------------- Sélection des papers ---------------- */
$username = (string)($user['username'] ?? '');
$isDemoAccount = (bool)preg_match('/demo/i', $username);

$userRole = $user['role'] ?? '';
$userRoles = [];
if (is_string($userRole) && $userRole !== '') {
  $userRoles = [$userRole];
} elseif ($userRole instanceof BSONArray) {
  $userRoles = $userRole->getArrayCopy();
} elseif (is_array($userRole)) {
  $userRoles = $userRole;
}

$paperList = [];

if ($isDemoAccount) {
  // Compte démo : n'afficher que le dernier test assigné (role courant), pas tout l'historique papers/roles
  $lastRole = trim((string)($user['role'] ?? ''));
  if ($lastRole === '') {
    $rolesHistory = $user['roles'] ?? [];
    if ($rolesHistory instanceof BSONArray) $rolesHistory = $rolesHistory->getArrayCopy();
    if (is_array($rolesHistory) && !empty($rolesHistory)) {
      $lastRole = trim((string)end($rolesHistory));
    }
  }
  if ($lastRole !== '') {
    $p = $papers->findOne(['active' => true, 'label' => $lastRole], ['sort' => ['created' => -1]]);
    if ($p) {
      $paperList[(string)$p['_id']] = $p;
    }
  }
} else {
  $userPaperIds = $user['papers'] ?? [];
  if ($userPaperIds instanceof BSONArray) $userPaperIds = $userPaperIds->getArrayCopy();
  $paperOids = [];
  foreach ((array)$userPaperIds as $p) {
    $oid = ($p instanceof ObjectId) ? $p : to_oid((string)$p);
    if ($oid) $paperOids[] = $oid;
  }
  if (!empty($paperOids)) {
    $cursor = $papers->find(['_id'=>['$in'=>$paperOids], 'active'=>true]);
    foreach ($cursor as $p) { $paperList[(string)$p['_id']] = $p; }
  }

  if (!empty($userRoles)) {
    foreach ($userRoles as $role) {
      $role = (string)$role;
      if ($role === '') continue;
      $p = $papers->findOne(['active'=>true, 'label'=>$role], ['sort'=>['created'=>-1]]);
      if ($p && !isset($paperList[(string)$p['_id']])) {
        $paperList[(string)$p['_id']] = $p;
      }
    }
  }
}

if (empty($paperList)) {
  // Important: ne plus afficher de paper générique non assigné au candidat.
  // La page doit refléter uniquement les données réelles de l'utilisateur connecté.
  $paperList = [];
}

/* ---------------- Statut par paper ---------------- */
$paperData = [];
foreach ($paperList as $paper) {
  $pid  = (string)$paper['_id'];
  $pOid = to_oid($pid);

  $qTotal = null;
  if (isset($paper['counts']['QCM_total'])) $qTotal = (int)$paper['counts']['QCM_total'];
  if ($qTotal === null) {
    $raw = $paper['questions'] ?? [];
    if ($raw instanceof BSONArray) $raw = $raw->getArrayCopy();
    $ids = [];
    foreach ((array)$raw as $x) {
      $oid = ($x instanceof ObjectId) ? $x : (is_array($x) && isset($x['$oid']) ? to_oid((string)$x['$oid']) : (is_string($x) ? to_oid($x) : null));
      if ($oid) $ids[] = $oid;
    }
    $qTotal = $ids ? medacapCountDocuments($questions, ['_id'=>['$in'=>$ids],'active'=>true]) : 0;
  }

  $examActive  = $exams->findOne(['user'=>$userOid,'paper'=>$pOid,'active'=>true]);
  $alloc       = $allocations->findOne(['user'=>$userOid,'paper'=>$pOid]);
  $allocStatus = (string)($alloc['status'] ?? '');

  $status   = 'not_started';
  $ctaLabel = $cand_cta_start;
  $badge    = ['text'=>$cand_badge_not_started,'class'=>'badge-neutral'];
  $ctaHref  = "./userPaperQuiz.php?paper=".htmlspecialchars($pid, ENT_QUOTES, 'UTF-8');

  if ($examActive) {
    $status   = 'in_progress';
    $ctaLabel = $cand_cta_continue;
    $badge    = ['text'=>$cand_badge_in_progress,'class'=>'badge-warning'];
  } elseif ($alloc && $allocStatus === 'done') {
    $status   = 'done';
    $ctaLabel = $cand_cta_review;
    $badge    = ['text'=>$cand_badge_done,'class'=>'badge-success'];
    $ctaHref  = "./userPaperQuiz.php?paper=".htmlspecialchars($pid, ENT_QUOTES, 'UTF-8')."&review=1";
  } elseif ($alloc && in_array($allocStatus, ['in_progress','awaiting_orientation','awaiting_catchup_choice','catchup_qcm'], true)) {
    $status   = 'in_progress';
    $ctaLabel = $cand_cta_continue;
    $badge    = ['text'=>$cand_badge_in_progress,'class'=>'badge-warning'];
    $focus    = ($allocStatus === 'awaiting_orientation') ? "&focus=orientation" : "";
    $ctaHref  = "./userPaperQuiz.php?paper=".htmlspecialchars($pid, ENT_QUOTES, 'UTF-8').$focus;
  }

  $orientation = $paper['orientation'] ?? [];
  if ($orientation instanceof BSONArray) $orientation = $orientation->getArrayCopy();
  $orientationTotal = count((array)$orientation);
  $orientationAnswered = 0;

  $lastExam = $exams->findOne(
    ['user'=>$userOid,'paper'=>$pOid],
    ['sort'=>['updated'=>-1,'created'=>-1]]
  );

  if ($examActive) {
    $orientationAnswered = count_orientation_answered_map($examActive['orientationAnswers'] ?? []);
    if ($orientationTotal > 0) {
      $orientationAnswered = min($orientationAnswered, $orientationTotal);
    }
  } elseif ($allocStatus === 'done' && $orientationTotal > 0) {
    $rOri = $results->findOne(
      ['user'=>$userOid,'paper'=>$pOid,'type'=>'Orientation','level'=>'Mixed'],
      ['sort'=>['created'=>-1]]
    );
    if ($rOri && is_array($rOri['answers'] ?? null)) {
      $orientationAnswered = count_orientation_answered_results($rOri['answers']);
      $orientationAnswered = min($orientationAnswered, $orientationTotal);
    } elseif ($lastExam) {
      $orientationAnswered = count_orientation_answered_map($lastExam['orientationAnswers'] ?? []);
      $orientationAnswered = min($orientationAnswered, $orientationTotal);
    }
  } elseif ($lastExam && $orientationTotal > 0) {
    $orientationAnswered = count_orientation_answered_map($lastExam['orientationAnswers'] ?? []);
    $orientationAnswered = min($orientationAnswered, $orientationTotal);
  }

  $paperDuration = is_array($paper['duration'] ?? null) ? $paper['duration'] : [];
  $paperHour = isset($paperDuration['hour']) ? (int)$paperDuration['hour'] : (int)($paper['hour'] ?? 1);
  $paperMinute = isset($paperDuration['minute']) ? (int)$paperDuration['minute'] : (int)($paper['minute'] ?? 0);
  $paperSecond = isset($paperDuration['second']) ? (int)$paperDuration['second'] : (int)($paper['second'] ?? 0);
  $paperHour = max(0, min(8, $paperHour));
  $paperMinute = max(0, min(59, $paperMinute));
  $paperSecond = max(0, min(59, $paperSecond));
  $durationMin = ($paperHour * 60) + $paperMinute + (($paperSecond > 0) ? 1 : 0);
  if ($durationMin <= 0) $durationMin = 60;

  $savedAnswered = 0;
  $answersSource = null;
  if ($examActive && isset($examActive['answers'])) {
    $answersSource = normalize_bson_array($examActive['answers']);
  } elseif ($lastExam && isset($lastExam['answers'])) {
    $answersSource = normalize_bson_array($lastExam['answers']);
  }
  if (is_array($answersSource) && !empty($answersSource)) {
    $savedAnswered = count_qcm_answered($answersSource, (int)$qTotal);
  } elseif ($allocStatus === 'done') {
    $rFact = $results->findOne(
      ['user'=>$userOid,'paper'=>$pOid,'type'=>'Factuel','level'=>'Mixed','userAnswers'=>['$exists'=>true]],
      ['sort'=>['created'=>-1]]
    );
    if ($rFact && isset($rFact['userAnswers'])) {
      $savedAnswered = count_qcm_answered(normalize_bson_array($rFact['userAnswers']), (int)$qTotal);
    }
  }
  if ($savedAnswered === 0 && $examActive) {
    $savedAnswered = (int)($examActive['total'] ?? 0);
  }

  $lastActivityDt = null;
  if ($examActive) {
    $lastActivityDt = parse_dt_mixed((string)($examActive['updated'] ?? $examActive['created'] ?? ''));
  } elseif ($lastExam) {
    $lastActivityDt = parse_dt_mixed((string)($lastExam['updated'] ?? $lastExam['created'] ?? ''));
  }
  if (!$lastActivityDt && $alloc) {
    $lastActivityDt = parse_dt_mixed((string)($alloc['finishedAt'] ?? $alloc['updated'] ?? $alloc['created'] ?? ''));
  }
  if (!$lastActivityDt && $status === 'done') {
    $rFact = $results->findOne(
      ['user'=>$userOid,'paper'=>$pOid,'type'=>'Factuel','level'=>'Mixed'],
      ['sort'=>['created'=>-1], 'projection'=>['created'=>1]]
    );
    if ($rFact) $lastActivityDt = parse_dt_mixed((string)($rFact['created'] ?? ''));
  }
  $lastActivity = ago_label($lastActivityDt, $lang);

  $paperData[] = [
    'id'            => $pid,
    'label'         => (string)($paper['label'] ?? $cand_paper_default),
    'domain'        => (string)($paper['domain'] ?? ''),
    'qTotal'        => $qTotal,
    'orientation'   => $orientation,
    'status'        => $status,
    'ctaLabel'      => $ctaLabel,
    'badge'         => $badge,
    'ctaHref'       => $ctaHref,
    'savedAnswered' => $savedAnswered,
    'orientationAnswered' => $orientationAnswered,
    'orientationTotal' => $orientationTotal,
    'durationMin'   => $durationMin,
    'lastActivity'  => $lastActivity,
  ];
}

$totalTests   = count($paperData);
$doneCount    = count(array_filter($paperData, function($p){ return $p['status'] === 'done'; }));
$startedCount = count(array_filter($paperData, function($p){ return $p['status'] === 'in_progress'; }));
$notStarted   = $totalTests - $doneCount - $startedCount;
$guideTimeDesc = build_guide_duration_desc($paperData, $lang);

/* ---------------- Gradients par index ---------------- */
$cardGradients = [
  ['banner'=>'linear-gradient(135deg,#1e40af 0%,#3b82f6 100%)', 'accent'=>'#3b82f6', 'light'=>'#eff6ff', 'text'=>'#1d4ed8', 'prog'=>'#3b82f6'],
  ['banner'=>'linear-gradient(135deg,#5b21b6 0%,#8b5cf6 100%)', 'accent'=>'#8b5cf6', 'light'=>'#f5f3ff', 'text'=>'#6d28d9', 'prog'=>'#8b5cf6'],
  ['banner'=>'linear-gradient(135deg,#0e7490 0%,#06b6d4 100%)', 'accent'=>'#06b6d4', 'light'=>'#ecfeff', 'text'=>'#0e7490', 'prog'=>'#06b6d4'],
  ['banner'=>'linear-gradient(135deg,#92400e 0%,#d97706 100%)', 'accent'=>'#d97706', 'light'=>'#fffbeb', 'text'=>'#92400e', 'prog'=>'#d97706'],
  ['banner'=>'linear-gradient(135deg,#065f46 0%,#16a34a 100%)', 'accent'=>'#16a34a', 'light'=>'#f0fdf4', 'text'=>'#065f46', 'prog'=>'#16a34a'],
];

/* ---------------- Vue ---------------- */
$candidate_nav_title = $cand_nav_my_tests;
$candidate_nav_subtitle = $cand_nav_subtitle;
include_once "partials/header.php";
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=Bricolage+Grotesque:wght@500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer"/>
<title>Évaluations Connaissances — Candidat | CFAO Mobility Academy</title>

<style>
/* ── Base (fond géré par .cand-viewport-bg dans candidateHeader) ── */
.eval-candidate-home.page-background-container {
  background: transparent !important;
  overflow: visible;
  border-radius: 0;
}
.eval-candidate-home .page-content-container {
  overflow: visible;
  padding: 0;
  box-sizing: border-box;
}

body, .eval-page {
  font-family: 'DM Sans', sans-serif !important;
  font-size: 16px;
}

.eval-page {
  max-width: 1180px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding-top: 4px;
}

/* ── Page hero ── */
.eval-hero {
  background: rgba(255,255,255,.82);
  backdrop-filter: blur(18px);
  -webkit-backdrop-filter: blur(18px);
  border: 1px solid rgba(255,255,255,.7);
  box-shadow: 0 4px 24px rgba(31,38,135,.10);
  border-radius: 18px;
  padding: 12px 18px 10px;
  text-align: center;
  margin-bottom: 0;
  flex-shrink: 0;
}
.eval-hero h1 {
  font-family: 'Bricolage Grotesque', sans-serif;
  font-size: 28px; font-weight: 700; color: #18293a;
  letter-spacing: -.4px; margin-bottom: 8px;
}
.eval-hero .session-tag {
  display: inline-flex; align-items: center; gap: 6px;
  background: rgba(255,255,255,.6); border: 1px solid rgba(255,255,255,.9);
  border-radius: 100px; padding: 5px 16px;
  font-size: 15px; color: #334155;
  backdrop-filter: blur(8px); margin-bottom: 6px;
}
.eval-hero .hero-badges { display: flex; align-items: center; justify-content: center; gap: 10px; flex-wrap: wrap; }
.hero-pill {
  display: inline-flex; align-items: center; gap: 6px;
  border-radius: 100px; padding: 6px 16px;
  font-size: 15px; font-weight: 600;
}
.hero-pill.hp-neutral { background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; }
.hero-pill.hp-warning { background:#fef3c7; color:#92400e; border:1px solid #fde68a; }
.hero-pill.hp-success { background:#d1fae5; color:#065f46; border:1px solid #a7f3d0; }
.hero-pill.hp-blue    { background:#dbeafe; color:#1e40af; border:1px solid #bfdbfe; }

/* ── Grid papers ── */
.papers-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 10px;
  margin-bottom: 12px;
}

/* ── Eval card ── */
.eval-card {
  border-radius: 16px;
  background: #fff;
  border: 1px solid rgba(0,0,0,.07);
  box-shadow: 0 2px 14px rgba(0,0,0,.07);
}

/* ── Card head ── */
.card-head {
  padding: 14px 18px 12px;
  border-bottom: 1px solid #f1f5f9;
}
.card-head-top {
  display: flex; align-items: center; justify-content: space-between; gap: 10px;
  margin-bottom: 8px;
}
.level-badge {
  display: inline-flex; align-items: center;
  border-radius: 100px; padding: 6px 16px;
  font-size: 14px; font-weight: 700; letter-spacing: .4px;
  text-transform: uppercase;
}
.card-domain {
  font-size: 14px; font-weight: 700; letter-spacing: .5px;
  text-transform: uppercase; margin-bottom: 4px;
}
.card-title {
  font-family: 'Bricolage Grotesque', sans-serif;
  font-size: 20px; font-weight: 700; color: #18293a; line-height: 1.3;
}
.status-pill {
  display: inline-flex; align-items: center; gap: 6px;
  border-radius: 100px; padding: 6px 14px;
  font-size: 14px; font-weight: 600; white-space: nowrap; flex-shrink: 0;
}
.sp-neutral { background:#f1f5f9; color:#64748b; border:1px solid #e2e8f0; }
.sp-warning { background:#fef3c7; color:#92400e; border:1px solid #fde68a; }
.sp-success { background:#d1fae5; color:#065f46; border:1px solid #a7f3d0; }

/* ── Card body ── */
.card-body-inner { padding: 14px 18px 18px; }

/* Stats row */
.stats-row {
  display: flex; gap: 0;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px; overflow: hidden;
  margin-bottom: 10px;
}
.stat-col {
  flex: 1; text-align: center; padding: 12px 8px;
  border-right: 1px solid #e2e8f0;
}
.stat-col:last-child { border-right: none; }
.stat-num {
  font-family: 'Bricolage Grotesque', sans-serif;
  font-size: 28px; font-weight: 700; color: #18293a; line-height: 1;
}
.stat-lbl {
  font-size: 13px; font-weight: 700; color: #475569;
  text-transform: uppercase; letter-spacing: .4px; margin-top: 5px;
}

/* Tags */
.tags-row { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 8px; }
.tag-item {
  display: inline-flex; align-items: center; gap: 6px;
  background: #f1f5f9; border: 1px solid #e2e8f0;
  border-radius: 100px; padding: 6px 14px;
  font-size: 14px; font-weight: 600; color: #334155;
}
.tag-item i { font-size: 12px; opacity: .8; }
.tag-orient { border-color: rgba(139,92,246,.3) !important; color: #5b21b6 !important; background: rgba(139,92,246,.08) !important; }

/* Progress */
.prog-label { display: flex; justify-content: space-between; font-size: 15px; color: #334155; font-weight: 700; margin-bottom: 7px; }
.prog-breakdown{
  display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;
  font-size:14px;color:#334155;font-weight:600;margin:0 0 8px;
}
.prog-breakdown .muted{color:#475569;font-weight:600}
.prog-track { height: 8px; background: #e2e8f0; border-radius: 100px; overflow: hidden; margin-bottom: 10px; }
.prog-fill { height: 100%; border-radius: 100px; transition: width .5s ease; }
.card-last-activity{
  margin-top:6px;
  font-size:14px;
  color:#334155;
  font-weight:600;
}

/* Buttons */
.card-actions { display: flex; align-items: center; gap: 12px; }
.btn-consignes {
  display: inline-flex; align-items: center; gap: 6px;
  background: #f1f5f9; border: 1px solid #e2e8f0;
  border-radius: 100px; padding: 10px 16px;
  font-size: 14px; font-weight: 600; color: #334155;
  cursor: pointer; font-family: 'DM Sans', sans-serif;
  transition: background .18s;
  text-decoration: none;
}
.btn-consignes:hover { background: #e2e8f0; }
.btn-consignes i { font-size: 13px; }
.btn-start {
  flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 8px;
  border: none; border-radius: 100px;
  padding: 12px 20px; font-size: 16px; font-weight: 700;
  font-family: 'DM Sans', sans-serif; cursor: pointer; color: #fff;
  text-decoration: none; transition: opacity .18s, transform .15s;
}
.btn-start:hover { opacity: .88; transform: translateY(-1px); }
.btn-start i { font-size: 14px; }

/* ── Guide card ── */
.guide-card {
  border-radius: 16px;
  background: #fff;
  border: 1px solid rgba(0,0,0,.07);
  box-shadow: 0 2px 14px rgba(0,0,0,.07);
  margin-bottom: 0 !important;
}
.guide-card-header {
  background: #fff;
  border-bottom: 1px solid #f1f5f9;
  padding: 10px 16px;
  display: flex; align-items: center; gap: 12px;
}
.guide-header-icon {
  width: 38px; height: 38px; border-radius: 10px;
  background: #fff8e8; display: flex; align-items: center; justify-content: center;
  font-size: 17px; flex-shrink: 0;
}
.guide-htext h3 {
  font-family: 'Bricolage Grotesque', sans-serif;
  font-size: 18px; font-weight: 700; color: #18293a; margin: 0;
}
.guide-htext p { font-size: 14px; color: #64748b; margin: 2px 0 0; }
.guide-body { padding: 8px 10px 10px; }
.guide-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 8px;
}
@media(max-width:900px){ .guide-grid { grid-template-columns: repeat(3,1fr); } }
@media(max-width:500px){ .guide-grid { grid-template-columns: repeat(2,1fr); } }

.guide-item {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px; padding: 10px 10px;
  display: flex; flex-direction: column; align-items: flex-start; gap: 8px;
  position: relative; overflow: hidden;
}
.guide-item::before {
  content: ''; position: absolute; top: 0; left: 0; right: 0;
  height: 4px; border-radius: 16px 16px 0 0;
}
.gi-net::before  { background: linear-gradient(90deg,#3b82f6,#60a5fa); }
.gi-save::before { background: linear-gradient(90deg,#22c55e,#4ade80); }
.gi-time::before { background: linear-gradient(90deg,#f59e0b,#fbbf24); }
.gi-read::before { background: linear-gradient(90deg,#8b5cf6,#a78bfa); }
.gi-help::before { background: linear-gradient(90deg,#ef4444,#f87171); }

.gi-icon {
  width: 32px; height: 32px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center; font-size: 14px;
}
.gi-net  .gi-icon { background:#eff6ff; color:#3b82f6; }
.gi-save .gi-icon { background:#f0fdf4; color:#16a34a; }
.gi-time .gi-icon { background:#fffbeb; color:#d97706; }
.gi-read .gi-icon { background:#f5f3ff; color:#7c3aed; }
.gi-help .gi-icon { background:#fef2f2; color:#dc2626; }

.gi-cat  { font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .5px; }
.gi-desc { font-size: 14px; color: #1e293b; line-height: 1.35; }
.gi-desc strong { font-weight: 700; color: #18293a; }
</style>

<?php openBackgroundContainer('eval-candidate-home'); ?>
<div class="eval-page">

  <!-- ═══ Hero ═══ -->
  <div class="eval-hero">
    <div class="session-tag">
      <i class="fa-solid fa-star" style="color:#3b82f6;font-size:12px"></i>
      <?= htmlspecialchars($cand_hero_session, ENT_QUOTES, 'UTF-8') ?> <?= date('Y') ?>
    </div>
    <h1><i class="fa-solid fa-graduation-cap me-2" style="color:#3b82f6"></i><?= htmlspecialchars($cand_hero_title, ENT_QUOTES, 'UTF-8') ?></h1>
    <div class="hero-badges">
      <span class="hero-pill hp-blue">
        <i class="fa-solid fa-layer-group"></i>
        <?= $totalTests ?> <?= $totalTests > 1 ? htmlspecialchars($cand_papers_many, ENT_QUOTES, 'UTF-8') : htmlspecialchars($cand_papers_one, ENT_QUOTES, 'UTF-8') ?>
      </span>
      <?php if ($doneCount > 0): ?>
        <span class="hero-pill hp-success">
          <i class="fa-solid fa-check-circle"></i>
          <?= $doneCount ?> <?= $doneCount > 1 ? htmlspecialchars($cand_done_many, ENT_QUOTES, 'UTF-8') : htmlspecialchars($cand_done_one, ENT_QUOTES, 'UTF-8') ?>
        </span>
      <?php endif; ?>
      <?php if ($startedCount > 0): ?>
        <span class="hero-pill hp-warning">
          <i class="fa-solid fa-clock"></i>
          <?= $startedCount ?> <?= htmlspecialchars($cand_in_progress, ENT_QUOTES, 'UTF-8') ?>
        </span>
      <?php endif; ?>
      <?php if ($notStarted > 0): ?>
        <span class="hero-pill hp-neutral">
          <i class="fa-regular fa-hourglass"></i>
          <?= $notStarted ?> <?= htmlspecialchars($cand_to_start, ENT_QUOTES, 'UTF-8') ?>
        </span>
      <?php endif; ?>
    </div>
  </div>

  <!-- ═══ Guide ═══ -->
  <div id="guide" class="guide-card">
    <div class="guide-card-header">
      <div class="guide-header-icon">
        <i class="fa-solid fa-star" style="color:#f59e0b"></i>
      </div>
      <div class="guide-htext">
        <h3><?= htmlspecialchars($cand_guide_title, ENT_QUOTES, 'UTF-8') ?></h3>
        <p><?= htmlspecialchars($cand_guide_sub, ENT_QUOTES, 'UTF-8') ?></p>
      </div>
    </div>
    <div class="guide-body">
      <div class="guide-grid">
        <div class="guide-item gi-net">
          <div class="gi-icon"><i class="fa-solid fa-wifi"></i></div>
          <div class="gi-cat"><?= htmlspecialchars($cand_gi_net_cat, ENT_QUOTES, 'UTF-8') ?></div>
          <div class="gi-desc"><?= $cand_gi_net_desc ?></div>
        </div>
        <div class="guide-item gi-save">
          <div class="gi-icon"><i class="fa-solid fa-floppy-disk"></i></div>
          <div class="gi-cat"><?= htmlspecialchars($cand_gi_save_cat, ENT_QUOTES, 'UTF-8') ?></div>
          <div class="gi-desc"><?= $cand_gi_save_desc ?></div>
        </div>
        <div class="guide-item gi-time">
          <div class="gi-icon"><i class="fa-solid fa-clock"></i></div>
          <div class="gi-cat"><?= htmlspecialchars($cand_gi_time_cat, ENT_QUOTES, 'UTF-8') ?></div>
          <div class="gi-desc"><?= $guideTimeDesc ?></div>
        </div>
        <div class="guide-item gi-read">
          <div class="gi-icon"><i class="fa-solid fa-book-open"></i></div>
          <div class="gi-cat"><?= htmlspecialchars($cand_gi_read_cat, ENT_QUOTES, 'UTF-8') ?></div>
          <div class="gi-desc"><?= $cand_gi_read_desc ?></div>
        </div>
        <div class="guide-item gi-help">
          <div class="gi-icon"><i class="fa-solid fa-phone"></i></div>
          <div class="gi-cat"><?= htmlspecialchars($cand_gi_help_cat, ENT_QUOTES, 'UTF-8') ?></div>
          <div class="gi-desc"><?= $cand_gi_help_desc ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══ Papers grid ═══ -->
  <?php if (empty($paperData)): ?>
  <div class="guide-card" style="margin-bottom:24px">
    <div class="guide-card-header">
      <div class="guide-header-icon">
        <i class="fa-solid fa-circle-info" style="color:#3b82f6"></i>
      </div>
      <div class="guide-htext">
        <h3><?= htmlspecialchars($cand_no_papers_title, ENT_QUOTES, 'UTF-8') ?></h3>
        <p><?= htmlspecialchars($cand_no_papers_sub, ENT_QUOTES, 'UTF-8') ?></p>
      </div>
    </div>
    <div class="guide-body">
      <p style="margin:0; color:#475569;"><?= htmlspecialchars($cand_no_papers_body, ENT_QUOTES, 'UTF-8') ?></p>
    </div>
  </div>
  <?php endif; ?>

  <div class="papers-grid">
  <?php foreach ($paperData as $ci => $pd):
    $g = $cardGradients[$ci % count($cardGradients)];

    // Badge pill class
    if ($pd['status'] === 'done')        { $spClass = 'sp-success'; $spIcon = 'fa-check-circle'; }
    elseif ($pd['status'] === 'in_progress') { $spClass = 'sp-warning'; $spIcon = 'fa-clock'; }
    else                                 { $spClass = 'sp-neutral';  $spIcon = 'fa-hourglass'; }
    // Short label for banner
    $lbl = strtolower($pd['label']);
    $shortLabel = $cand_level_test . ' ' . ($ci + 1);
    if      (strpos($lbl, 'junior')    !== false) $shortLabel = $cand_level_junior;
    elseif  (strpos($lbl, 'senior')    !== false) $shortLabel = $cand_level_senior;
    elseif  (strpos($lbl, 'expert')    !== false) $shortLabel = $cand_level_expert;
    elseif  (strpos($lbl, 'stagiaire') !== false) $shortLabel = $cand_level_stagiaire;
    elseif  (strpos($lbl, 'confirmé')  !== false || strpos($lbl, 'confirme') !== false) $shortLabel = $cand_level_confirme;

    // Banner icon
    $bannerIcon = 'fa-file-lines';
    if (strpos($lbl,'junior') !== false)    $bannerIcon = 'fa-seedling';
    elseif (strpos($lbl,'senior') !== false) $bannerIcon = 'fa-user-tie';
    elseif (strpos($lbl,'expert') !== false) $bannerIcon = 'fa-star';

    $oriCount    = (int)($pd['orientationTotal'] ?? count($pd['orientation']));
    $oriAnswered = (int)($pd['orientationAnswered'] ?? 0);
    $progPct     = ($pd['qTotal'] > 0) ? (int)round($pd['savedAnswered'] * 100 / $pd['qTotal']) : 0;
    // CTA button style
    $btnClass = 'btn-start';
    $btnStyle = "background:{$g['banner']};";
  ?>
  <div class="eval-card">

    <!-- Head -->
    <div class="card-head">
      <div class="card-head-top">
        <span class="level-badge" style="background:<?= $g['light'] ?>;color:<?= $g['text'] ?>"><?= htmlspecialchars($shortLabel, ENT_QUOTES, 'UTF-8') ?></span>
        <span class="status-pill <?= $spClass ?>">
          <i class="fa-solid <?= $spIcon ?>" style="font-size:11px"></i>
          <?= htmlspecialchars($pd['badge']['text'], ENT_QUOTES, 'UTF-8') ?>
        </span>
      </div>
      <div class="card-domain" style="color:<?= $g['accent'] ?>"><?= $pd['domain'] !== '' ? htmlspecialchars($pd['domain'], ENT_QUOTES, 'UTF-8') . ' — ' : '' ?><?= htmlspecialchars($cand_theoretical_test, ENT_QUOTES, 'UTF-8') ?></div>
      <div class="card-title"><?= htmlspecialchars($pd['label'], ENT_QUOTES, 'UTF-8') ?></div>
      <?php if (!empty($pd['lastActivity'])): ?>
      <div class="card-last-activity"><i class="fa-regular fa-clock"></i> <?= htmlspecialchars($cand_last_activity, ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($pd['lastActivity'], ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>
    </div>

    <!-- Body -->
    <div class="card-body-inner">

      <!-- Stats -->
      <div class="stats-row">
        <div class="stat-col">
          <div class="stat-num"><?= (int)$pd['qTotal'] ?></div>
          <div class="stat-lbl"><?= htmlspecialchars($cand_stat_questions, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="stat-col">
          <div class="stat-num"><?= (int)$pd['durationMin'] ?></div>
          <div class="stat-lbl"><?= htmlspecialchars($cand_stat_min, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="stat-col">
          <div class="stat-num" style="color:<?= $pd['savedAnswered'] > 0 ? $g['accent'] : '#cbd5e1' ?>"><?= (int)$pd['savedAnswered'] ?></div>
          <div class="stat-lbl"><?= htmlspecialchars($cand_stat_answered, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <?php if ($oriCount > 0): ?>
        <div class="stat-col">
          <div class="stat-num" style="color:#8b5cf6"><?= $oriAnswered ?> / <?= $oriCount ?></div>
          <div class="stat-lbl"><?= htmlspecialchars($cand_orientation, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Tags -->
      <div class="tags-row">
        <span class="tag-item">
          <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($cand_single_answer, ENT_QUOTES, 'UTF-8') ?>
        </span>
        <?php if ($oriCount > 0): ?>
        <span class="tag-item tag-orient">
          <i class="fa-solid fa-compass"></i>
          <?= htmlspecialchars($cand_orientation, ENT_QUOTES, 'UTF-8') ?> : <?= $oriAnswered ?>/<?= $oriCount ?>
        </span>
        <?php endif; ?>
      </div>

      <!-- Progress -->
      <?php if ($oriCount > 0): ?>
      <div class="prog-breakdown">
        <span><?= htmlspecialchars($cand_orientation, ENT_QUOTES, 'UTF-8') ?> : <span class="muted"><?= $oriAnswered ?> / <?= $oriCount ?></span></span>
      </div>
      <?php endif; ?>
      <div class="prog-label">
        <span><?= htmlspecialchars($cand_progress, ENT_QUOTES, 'UTF-8') ?></span>
        <span><?= $progPct ?>%</span>
      </div>
      <div class="prog-track">
        <div class="prog-fill" style="width:<?= $progPct ?>%;background:<?= $g['accent'] ?>"></div>
      </div>

      <!-- Actions -->
      <div class="card-actions">
        <a href="#guide" class="btn-consignes">
          <i class="fa-solid fa-circle-info"></i> <?= htmlspecialchars($cand_consignes, ENT_QUOTES, 'UTF-8') ?>
        </a>

        <?php if ($pd['status'] === 'not_started'): ?>
          <form method="post" action="" style="flex:1;display:flex;margin:0">
            <input type="hidden" name="start" value="1">
            <input type="hidden" name="paper_id" value="<?= htmlspecialchars($pd['id'], ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn-start" style="background:<?= $g['accent'] ?>">
              <i class="fa-solid fa-play"></i> <?= htmlspecialchars($cand_cta_start, ENT_QUOTES, 'UTF-8') ?>
            </button>
          </form>
        <?php elseif ($pd['status'] === 'in_progress'): ?>
          <a href="<?= htmlspecialchars($pd['ctaHref'], ENT_QUOTES, 'UTF-8') ?>"
             class="btn-start" style="background:#d97706">
            <i class="fa-solid fa-play"></i> <?= htmlspecialchars($cand_cta_continue, ENT_QUOTES, 'UTF-8') ?>
          </a>
        <?php else: ?>
          <a href="<?= htmlspecialchars($pd['ctaHref'], ENT_QUOTES, 'UTF-8') ?>"
             class="btn-start" style="background:#16a34a">
            <i class="fa-solid fa-eye"></i> <?= htmlspecialchars($cand_cta_review_results, ENT_QUOTES, 'UTF-8') ?>
          </a>
        <?php endif; ?>
      </div>

    </div><!-- /card-body-inner -->
  </div><!-- /eval-card -->
  <?php endforeach; ?>
  </div><!-- /papers-grid -->


</div><!-- /container -->

<?php
closeBackgroundContainer();
include_once "partials/footer.php";
?>