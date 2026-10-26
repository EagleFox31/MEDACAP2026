<?php
require_once __DIR__ . '/../../core/medacap_config.php';
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
      ? 'The clock is set for each test. MCQs first, then preference questions at the end.'
      : 'Le chrono est indiqué sur chaque épreuve. QCM théoriques, puis questions de préférences en fin de parcours.';
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
      $desc = '<strong>' . $dur . '</strong> allotted for <strong>' . $q . ' MCQs</strong>';
      if ($ori > 0) {
        $desc .= ' and <strong>' . $ori . ' preference questions</strong> (not scored)';
      }
      return $desc . '.';
    }
    $desc = '<strong>' . $dur . '</strong> allouées pour <strong>' . $q . ' QCM</strong>';
    if ($ori > 0) {
      $desc .= ' et <strong>' . $ori . ' questions de préférences</strong> (non notées)';
    }
    return $desc . '.';
  }

  if (count($durations) === 1) {
    $dur = htmlspecialchars(format_duration_label($durations[0], $lang), ENT_QUOTES, 'UTF-8');
    if ($lang === 'EN') {
      return '<strong>' . $dur . '</strong> per test: MCQs on the menu, preference questions at the end.';
    }
    return '<strong>' . $dur . '</strong> par épreuve : QCM théoriques, puis questions de préférences à la fin.';
  }

  $durMin = htmlspecialchars(format_duration_label($durations[0], $lang), ENT_QUOTES, 'UTF-8');
  $durMax = htmlspecialchars(format_duration_label($durations[count($durations) - 1], $lang), ENT_QUOTES, 'UTF-8');
  if ($lang === 'EN') {
    return 'Between <strong>' . $durMin . '</strong> and <strong>' . $durMax . '</strong> depending on the test. MCQs and preference questions.';
  }
  return 'Entre <strong>' . $durMin . '</strong> et <strong>' . $durMax . '</strong> selon l\'épreuve. QCM et questions de préférences.';
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
$client   = new Client(medacapMongoUri());
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
  // Compte démo : priorité au paper explicite s'il existe dans user.papers, sinon dernier role assigné
  $userPaperIds = $user['papers'] ?? [];
  if ($userPaperIds instanceof BSONArray) $userPaperIds = $userPaperIds->getArrayCopy();
  if (!empty($userPaperIds)) {
    $pId = end($userPaperIds);
    $oid = ($pId instanceof ObjectId) ? $pId : to_oid((string)$pId);
    if ($oid) {
      $p = $papers->findOne(['_id' => $oid, 'active' => true]);
      if ($p) {
        $paperList[(string)$p['_id']] = $p;
      }
    }
  }
  if (empty($paperList)) {
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

  $paperDuration = ($paper['duration'] instanceof BSONDocument || $paper['duration'] instanceof BSONArray) ? $paper['duration']->getArrayCopy() : (is_array($paper['duration'] ?? null) ? $paper['duration'] : []);
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
  padding: 0 16px;
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
  gap: 16px;
  padding-top: 26px;
  padding-bottom: 36px;
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
  border-radius: 20px;
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border: 1px solid rgba(255, 255, 255, 0.85);
  box-shadow: 0 10px 30px -4px rgba(15, 23, 42, 0.08), 0 2px 6px -1px rgba(15, 23, 42, 0.04);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  overflow: hidden;
}
.eval-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 16px 36px -6px rgba(15, 23, 42, 0.12), 0 4px 10px -2px rgba(15, 23, 42, 0.06);
}

/* ── Card head ── */
.card-head {
  padding: 20px 24px 18px;
  background: linear-gradient(180deg, rgba(248, 250, 252, 0.65) 0%, rgba(255, 255, 255, 0) 100%);
  border-bottom: 1px solid rgba(226, 232, 240, 0.85);
}
.card-head-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 12px;
  flex-wrap: wrap;
}
.card-head-badges {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
.level-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border-radius: 100px;
  padding: 5px 14px;
  font-size: 13px;
  font-weight: 700;
  letter-spacing: 0.3px;
  text-transform: uppercase;
}
.domain-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 700;
  color: #475569;
  letter-spacing: 0.5px;
  text-transform: uppercase;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  border-radius: 100px;
  padding: 5px 12px;
}
.domain-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  display: inline-block;
}
.card-title {
  font-family: 'Bricolage Grotesque', 'DM Sans', sans-serif;
  font-size: 22px;
  font-weight: 800;
  color: #0f172a;
  line-height: 1.35;
  letter-spacing: -0.015em;
  margin: 0;
}
.status-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border-radius: 100px;
  padding: 6px 14px;
  font-size: 13px;
  font-weight: 700;
  white-space: nowrap;
  flex-shrink: 0;
}
.sp-neutral { background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; }
.sp-warning { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
.sp-success { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }

.card-last-activity {
  margin-top: 8px;
  font-size: 13px;
  color: #64748b;
  font-weight: 500;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

/* ── Card body ── */
.card-body-inner {
  padding: 22px 24px 24px;
}

/* ── Modern Stats Grid ── */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
  margin-bottom: 20px;
}
.stat-card {
  position: relative;
  display: flex;
  align-items: center;
  gap: 14px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 14px 16px;
  transition: all 0.2s ease;
}
.stat-card:hover {
  background: #ffffff;
  border-color: #cbd5e1;
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
}
.sc-icon {
  width: 42px;
  height: 42px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  flex-shrink: 0;
}
.sc-questions .sc-icon { background: #eff6ff; color: #2563eb; }
.sc-duration  .sc-icon { background: #fff7ed; color: #ea580c; }
.sc-answered  .sc-icon { background: #f1f5f9; color: #94a3b8; }
.sc-answered.is-active .sc-icon { background: #ecfdf5; color: #16a34a; }
.sc-orientation .sc-icon { background: #faf5ff; color: #9333ea; }

.sc-content {
  min-width: 0;
  flex: 1;
}
.sc-val {
  font-family: 'Bricolage Grotesque', 'DM Sans', sans-serif;
  font-size: 24px;
  font-weight: 800;
  color: #0f172a;
  line-height: 1.1;
  letter-spacing: -0.02em;
}
.sc-answered:not(.is-active) .sc-val {
  color: #94a3b8;
}
.sc-unit {
  font-size: 14px;
  font-weight: 600;
  color: #64748b;
  margin-left: 2px;
}
.sc-lbl {
  font-size: 11px;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-top: 4px;
}

/* ── Tags Chips ── */
.tags-row {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 20px;
}
.tag-chip {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 100px;
  padding: 6px 14px;
  font-size: 13px;
  font-weight: 600;
  color: #334155;
}
.tag-chip i { font-size: 12px; }
.tag-chip-purple {
  background: #faf5ff;
  border-color: #f3e8ff;
  color: #7e22ce;
}
.tag-chip-purple i { color: #9333ea; }

/* ── Progress Section ── */
.prog-container {
  margin-bottom: 22px;
}
.prog-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 13px;
  font-weight: 700;
  color: #334155;
  margin-bottom: 8px;
}
.prog-title {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  color: #475569;
}
.prog-value {
  color: #0f172a;
  font-family: 'Bricolage Grotesque', sans-serif;
  font-size: 15px;
  font-weight: 800;
}
.prog-track {
  height: 8px;
  background: #e2e8f0;
  border-radius: 100px;
  overflow: hidden;
}
.prog-fill {
  height: 100%;
  border-radius: 100px;
  transition: width 0.5s ease;
}

/* ── Action Buttons ── */
.card-actions {
  display: flex;
  align-items: center;
  gap: 14px;
}
.btn-consignes {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: #ffffff;
  border: 1.5px solid #cbd5e1;
  border-radius: 12px;
  padding: 13px 20px;
  font-size: 14px;
  font-weight: 700;
  color: #334155;
  cursor: pointer;
  font-family: inherit;
  transition: all 0.18s ease;
  text-decoration: none;
  white-space: nowrap;
  box-shadow: 0 1px 2px rgba(0,0,0,0.04);
}
.btn-consignes:hover {
  background: #f8fafc;
  border-color: #94a3b8;
  color: #0f172a;
}
.btn-consignes i { font-size: 14px; color: #64748b; }

.btn-cta {
  flex: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  border: none;
  border-radius: 12px;
  padding: 14px 28px;
  font-size: 15px;
  font-weight: 700;
  font-family: inherit;
  cursor: pointer;
  color: #ffffff !important;
  text-decoration: none;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
  letter-spacing: 0.2px;
}
.btn-cta:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(37, 99, 235, 0.45);
  opacity: 0.95;
}
.btn-cta:active {
  transform: translateY(0);
}
.btn-cta i { font-size: 13px; transition: transform 0.15s ease; }
.btn-cta:hover i { transform: translateX(3px); }

.btn-cta-continue {
  background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%) !important;
  box-shadow: 0 4px 14px rgba(217, 119, 6, 0.35);
}
.btn-cta-continue:hover {
  box-shadow: 0 6px 20px rgba(217, 119, 6, 0.45);
}
.btn-cta-review {
  background: linear-gradient(135deg, #059669 0%, #10b981 100%) !important;
  box-shadow: 0 4px 14px rgba(5, 150, 105, 0.35);
}
.btn-cta-review:hover {
  box-shadow: 0 6px 20px rgba(5, 150, 105, 0.45);
}

@media (max-width: 900px) {
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
@media (max-width: 540px) {
  .card-head, .card-body-inner {
    padding: 16px;
  }
  .stats-grid {
    grid-template-columns: 1fr 1fr;
    gap: 10px;
  }
  .stat-card {
    padding: 10px 12px;
    gap: 10px;
  }
  .sc-icon {
    width: 34px;
    height: 34px;
    font-size: 13px;
  }
  .sc-val {
    font-size: 20px;
  }
  .card-actions {
    flex-direction: column;
  }
  .btn-consignes, .btn-cta, form[method="post"] {
    width: 100%;
  }
}

/* ── Guide card (3 Piliers UX) ── */
.guide-card {
  border-radius: 20px;
  background: rgba(255, 255, 255, 0.96);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border: 1px solid rgba(255, 255, 255, 0.85);
  box-shadow: 0 10px 30px -4px rgba(15, 23, 42, 0.06), 0 2px 6px -1px rgba(15, 23, 42, 0.03);
  margin-bottom: 0 !important;
  overflow: hidden;
}
.guide-card-header {
  background: linear-gradient(180deg, rgba(248, 250, 252, 0.8) 0%, rgba(255, 255, 255, 0.95) 100%);
  border-bottom: 1px solid rgba(226, 232, 240, 0.85);
  padding: 14px 22px;
  display: flex;
  align-items: center;
  gap: 14px;
}
.guide-header-icon {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: #eff6ff;
  border: 1px solid #dbeafe;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 17px;
  color: #2563eb;
  flex-shrink: 0;
}
.guide-htext h3 {
  font-family: 'Bricolage Grotesque', 'DM Sans', sans-serif;
  font-size: 18px;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
  letter-spacing: -0.015em;
}
.guide-htext p {
  font-size: 13.5px;
  color: #64748b;
  margin: 2px 0 0;
  font-weight: 500;
}
.guide-body {
  padding: 18px 22px 20px;
}
.guide-grid-pillars {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}
@media (max-width: 900px) {
  .guide-grid-pillars {
    grid-template-columns: 1fr;
    gap: 12px;
  }
}
.pillar-card {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 16px 18px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  transition: all 0.2s ease;
}
.pillar-card:hover {
  background: #ffffff;
  border-color: #cbd5e1;
  box-shadow: 0 4px 14px rgba(15, 23, 42, 0.05);
}
.pillar-head {
  display: flex;
  align-items: center;
  gap: 10px;
}
.pillar-icon {
  width: 34px;
  height: 34px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 15px;
  flex-shrink: 0;
  background: #eff6ff;
  color: #2563eb;
  border: 1px solid #dbeafe;
}
.pillar-title {
  font-size: 13px;
  font-weight: 700;
  color: #334155;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}
.pillar-body {
  display: flex;
  flex-direction: column;
  gap: 6px;
  flex: 1;
}
.pillar-main {
  font-size: 14px;
  color: #0f172a;
  line-height: 1.45;
  margin: 0;
}
.pillar-main strong {
  font-weight: 700;
  color: #0f172a;
}
.pillar-note {
  font-size: 12.5px;
  color: #64748b;
  line-height: 1.4;
}
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

  <!-- ═══ Guide (3 Piliers UX) ═══ -->
  <div id="guide" class="guide-card">
    <div class="guide-card-header">
      <div class="guide-header-icon">
        <i class="fa-solid fa-compass"></i>
      </div>
      <div class="guide-htext">
        <h3><?= htmlspecialchars($cand_guide_title, ENT_QUOTES, 'UTF-8') ?></h3>
        <p><?= htmlspecialchars($cand_guide_sub, ENT_QUOTES, 'UTF-8') ?></p>
      </div>
    </div>
    <div class="guide-body">
      <div class="guide-grid-pillars">
        <!-- Pilier 1 : Déroulement & Temps -->
        <div class="pillar-card">
          <div class="pillar-head">
            <span class="pillar-icon"><i class="fa-regular fa-clock"></i></span>
            <span class="pillar-title"><?= htmlspecialchars($cand_g3_time_title, ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <div class="pillar-body">
            <p class="pillar-main"><?= $guideTimeDesc ?></p>
            <div class="pillar-note"><?= htmlspecialchars($cand_g3_time_note, ENT_QUOTES, 'UTF-8') ?></div>
          </div>
        </div>

        <!-- Pilier 2 : Sérénité technique -->
        <div class="pillar-card">
          <div class="pillar-head">
            <span class="pillar-icon"><i class="fa-solid fa-cloud-arrow-up"></i></span>
            <span class="pillar-title"><?= htmlspecialchars($cand_g3_tech_title, ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <div class="pillar-body">
            <p class="pillar-main"><?= $cand_g3_tech_main ?></p>
            <div class="pillar-note"><?= htmlspecialchars($cand_g3_tech_note, ENT_QUOTES, 'UTF-8') ?></div>
          </div>
        </div>

        <!-- Pilier 3 : Conseils & Assistance -->
        <div class="pillar-card">
          <div class="pillar-head">
            <span class="pillar-icon"><i class="fa-solid fa-headset"></i></span>
            <span class="pillar-title"><?= htmlspecialchars($cand_g3_help_title, ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <div class="pillar-body">
            <p class="pillar-main"><?= $cand_g3_help_main ?></p>
            <div class="pillar-note"><?= htmlspecialchars($cand_g3_help_note, ENT_QUOTES, 'UTF-8') ?></div>
          </div>
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
        <div class="card-head-badges">
          <span class="level-badge" style="background:<?= $g['light'] ?>;color:<?= $g['text'] ?>">
            <i class="fa-solid <?= $bannerIcon ?>"></i>
            <?= htmlspecialchars($shortLabel, ENT_QUOTES, 'UTF-8') ?>
          </span>
          <span class="domain-pill">
            <span class="domain-dot" style="background:<?= $g['accent'] ?>"></span>
            <?= $pd['domain'] !== '' ? htmlspecialchars($pd['domain'], ENT_QUOTES, 'UTF-8') . ' — ' : '' ?><?= htmlspecialchars($cand_theoretical_test, ENT_QUOTES, 'UTF-8') ?>
          </span>
        </div>
        <span class="status-pill <?= $spClass ?>">
          <i class="fa-solid <?= $spIcon ?>"></i>
          <?= htmlspecialchars($pd['badge']['text'], ENT_QUOTES, 'UTF-8') ?>
        </span>
      </div>
      <h2 class="card-title"><?= htmlspecialchars($pd['label'], ENT_QUOTES, 'UTF-8') ?></h2>
      <?php if (!empty($pd['lastActivity'])): ?>
      <div class="card-last-activity">
        <i class="fa-regular fa-clock"></i> <?= htmlspecialchars($cand_last_activity, ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($pd['lastActivity'], ENT_QUOTES, 'UTF-8') ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Body -->
    <div class="card-body-inner">

      <!-- Modern Stats Grid -->
      <div class="stats-grid">
        <div class="stat-card sc-questions">
          <div class="sc-icon"><i class="fa-solid fa-layer-group"></i></div>
          <div class="sc-content">
            <div class="sc-val"><?= (int)$pd['qTotal'] ?></div>
            <div class="sc-lbl"><?= htmlspecialchars($cand_stat_questions, ENT_QUOTES, 'UTF-8') ?></div>
          </div>
        </div>
        <div class="stat-card sc-duration">
          <div class="sc-icon"><i class="fa-solid fa-stopwatch"></i></div>
          <div class="sc-content">
            <div class="sc-val"><?= (int)$pd['durationMin'] ?><span class="sc-unit">min</span></div>
            <div class="sc-lbl"><?= htmlspecialchars($cand_stat_min, ENT_QUOTES, 'UTF-8') ?></div>
          </div>
        </div>
        <div class="stat-card sc-answered <?= $pd['savedAnswered'] > 0 ? 'is-active' : '' ?>">
          <div class="sc-icon"><i class="fa-solid fa-circle-check"></i></div>
          <div class="sc-content">
            <div class="sc-val"><?= (int)$pd['savedAnswered'] ?></div>
            <div class="sc-lbl"><?= htmlspecialchars($cand_stat_answered, ENT_QUOTES, 'UTF-8') ?></div>
          </div>
        </div>
        <?php if ($oriCount > 0): ?>
        <div class="stat-card sc-orientation">
          <div class="sc-icon"><i class="fa-solid fa-compass"></i></div>
          <div class="sc-content">
            <div class="sc-val"><?= $oriAnswered ?> <span class="sc-unit">/ <?= $oriCount ?></span></div>
            <div class="sc-lbl"><?= htmlspecialchars($cand_orientation, ENT_QUOTES, 'UTF-8') ?></div>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Tags Row (No duplicates!) -->
      <div class="tags-row">
        <span class="tag-chip">
          <i class="fa-solid fa-circle-check" style="color:#2563eb"></i> <?= htmlspecialchars($cand_single_answer, ENT_QUOTES, 'UTF-8') ?>
        </span>
        <?php if ($oriCount > 0): ?>
        <span class="tag-chip tag-chip-purple">
          <i class="fa-solid fa-compass"></i> <?= htmlspecialchars($cand_orientation, ENT_QUOTES, 'UTF-8') ?> : <?= $oriAnswered ?> / <?= $oriCount ?>
        </span>
        <?php endif; ?>
      </div>

      <!-- Progress -->
      <div class="prog-container">
        <div class="prog-header">
          <span class="prog-title"><i class="fa-solid fa-chart-line me-1"></i> <?= htmlspecialchars($cand_progress, ENT_QUOTES, 'UTF-8') ?></span>
          <span class="prog-value"><?= $progPct ?>%</span>
        </div>
        <div class="prog-track">
          <div class="prog-fill" style="width:<?= $progPct ?>%;background:<?= $g['banner'] ?>"></div>
        </div>
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
            <button type="submit" class="btn-cta btn-cta-start" style="background:<?= $g['banner'] ?>">
              <span><?= htmlspecialchars($cand_cta_start, ENT_QUOTES, 'UTF-8') ?></span>
              <i class="fa-solid fa-arrow-right"></i>
            </button>
          </form>
        <?php elseif ($pd['status'] === 'in_progress'): ?>
          <a href="<?= htmlspecialchars($pd['ctaHref'], ENT_QUOTES, 'UTF-8') ?>"
             class="btn-cta btn-cta-continue">
            <span><?= htmlspecialchars($cand_cta_continue, ENT_QUOTES, 'UTF-8') ?></span>
            <i class="fa-solid fa-arrow-right"></i>
          </a>
        <?php else: ?>
          <a href="<?= htmlspecialchars($pd['ctaHref'], ENT_QUOTES, 'UTF-8') ?>"
             class="btn-cta btn-cta-review">
            <span><?= htmlspecialchars($cand_cta_review_results, ENT_QUOTES, 'UTF-8') ?></span>
            <i class="fa-solid fa-eye"></i>
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