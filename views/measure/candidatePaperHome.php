<?php
// ============================================================================
// Accueil Candidat (Cameroun) — Épreuve Figée (Paper) Factuel Mixed
// Version améliorée avec design moderne cohérent
// ============================================================================

session_start();
include_once "../language.php";
include_once "../partials/background-manager.php";
setPageBackground("bg-dashboard");

if (!isset($_SESSION["id"])) { header("Location: ../../"); exit(); }

require_once "../../vendor/autoload.php";

use MongoDB\Client;
use MongoDB\BSON\ObjectId;
use MongoDB\Model\BSONArray;

/* ---------------- Helpers ---------------- */
function to_oid($v){ try{ return ($v && preg_match('/^[0-9a-f]{24}$/i',$v)) ? new ObjectId($v) : null; }catch(\Throwable $e){ return null; } }

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

/* ---------------- Sélection du paper (Épreuve) ---------------- */
$userRole = (string)($user['role'] ?? '');

$labelPriority = [
  'Recrutement Atelier VL Multibrands',
  'Recrutement Junior VL',
  'Recrutement LOVOL 2025 Expert',
  'Campagne de rectrutement stagiaire technicien CFAO 2025',
  'Recrutement Technicien CFAO 2025',
  'Recrutement Technicien Campagne FNE-CFAO 2025'
];

$paper = null;

// 1. Priorité ABSOLUE : Le rôle spécifique assigné au candidat (ex: "Candidat Cameroun VL")
if (!empty($userRole)) {
    $paper = $papers->findOne(
        ['active'=>true, 'label'=>$userRole],
        ['sort'=>['created'=>-1]]
    );
}

// 2. Si non trouvé par rôle, on teste la liste de priorité standard
if (!$paper) {
    foreach ($labelPriority as $lb) {
        $paper = $papers->findOne(
            ['active'=>true, 'label'=>$lb],
            ['sort'=>['created'=>-1]]
        );
        if ($paper) break;
    }
}

// 3. Fallback ultime : Le dernier paper actif de type Factuel Mixed
if (!$paper) {
    $paper = $papers->findOne(
        ['active'=>true, 'type'=>['$in'=>['Factuel','Factuelle']], 'mode'=>'mixed'],
        ['sort'=>['created'=>-1]]
    );
}

if (!$paper) { http_response_code(404); echo "Aucune épreuve figée active n'a été trouvée."; exit(); }

$paperId = (string)$paper['_id'];
$paperLabel = (string)($paper['label'] ?? 'Épreuve figée');
$orientation = $paper['orientation'] ?? [];
if ($orientation instanceof BSONArray) $orientation = $orientation->getArrayCopy();

/* Nombre de questions */
$qTotal = null;
if (isset($paper['counts']['QCM_total'])) $qTotal = (int)$paper['counts']['QCM_total'];
if ($qTotal === null) {
  $raw = $paper['questions'] ?? [];
  if ($raw instanceof BSONArray) $raw = $raw->getArrayCopy();
  $ids=[]; foreach ((array)$raw as $x){
    $oid = ($x instanceof ObjectId)? $x : (is_array($x)&&isset($x['$oid'])? to_oid((string)$x['$oid']) : (is_string($x)? to_oid($x):null));
    if ($oid) $ids[]=$oid;
  }
  $qTotal = $ids ? $questions->countDocuments(['_id'=>['$in'=>$ids],'active'=>true]) : 0;
}

/* ---------------- Statut (exams + allocations) ---------------- */
$userOid = to_oid($userId);
$paperOid= to_oid($paperId);

$examActive = $exams->findOne(['user'=>$userOid,'paper'=>$paperOid,'active'=>true]);
$alloc      = $allocations->findOne(['user'=>$userOid,'paper'=>$paperOid]);

$allocStatus = (string)($alloc['status'] ?? '');
$status   = 'not_started';
$ctaLabel = 'Commencer';
$badge    = ['text'=>'Non démarré','class'=>'badge-light-danger'];
$ctaHref  = "./userPaperQuiz.php?paper=".htmlspecialchars($paperId, ENT_QUOTES, 'UTF-8');

if ($examActive) {
  $status   = 'in_progress';
  $ctaLabel = 'Continuer';
  $badge    = ['text'=>'En cours','class'=>'badge-light-warning'];
  $ctaHref  = "./userPaperQuiz.php?paper=".htmlspecialchars($paperId, ENT_QUOTES, 'UTF-8');
} elseif ($alloc && $allocStatus === 'done') {
  $status   = 'done';
  $ctaLabel = 'Revoir les questions';
  $badge    = ['text'=>'Terminé','class'=>'badge-light-success'];
  $ctaHref  = "./userPaperQuiz.php?paper=".htmlspecialchars($paperId, ENT_QUOTES, 'UTF-8')."&review=1";
} elseif ($alloc && in_array($allocStatus, ['in_progress','awaiting_orientation'], true)) {
  $status   = 'in_progress';
  $ctaLabel = 'Continuer';
  $badge    = ['text'=>'En cours','class'=>'badge-light-warning'];
  $focus = ($allocStatus === 'awaiting_orientation') ? "&focus=orientation" : "";
  $ctaHref  = "./userPaperQuiz.php?paper=".htmlspecialchars($paperId, ENT_QUOTES, 'UTF-8').$focus;
}

/* ---------------- Création allocation au clic "Commencer" ---------------- */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['start']) && $_POST['start']==='1') {
  $existing = $allocations->findOne(['user'=>$userOid,'paper'=>$paperOid]);
  if (!$existing) {
    $allocations->insertOne([
      'user'=>$userOid,
      'paper'=>$paperOid,
      'type'=>'Factuel',
      'level'=>'Mixed',
      'status'=>'in_progress',
      'active'=>true,
      'created'=>date('Y-m-d H:i:s'),
    ]);
  } elseif (!in_array((string)($existing['status'] ?? ''), ['done'], true)) {
    $allocations->updateOne(['_id'=>$existing['_id']], ['$set'=>['status'=>'in_progress','active'=>true]]);
  }
  header("Location: ./userPaperQuiz.php?paper=".$paperId);
  exit();
}

/* ---------------- Vue ---------------- */
include_once "partials/header.php";
?>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer"/>

<title>Évaluations Connaissances — Candidat Cameroun | CFAO Mobility Academy</title>

<style>
:root {
  --brand: #0ea5e9;
  --text-dark: #0f172a;
  --muted: #64748b;
  --surface: #ffffffcc;
  --glass-bg: rgba(255,255,255,.80);
  --glass-brd: rgba(15,23,42,.08);
  --radius: 16px;
  --shadow: 0 8px 24px rgba(15,23,42,.10);
  
  --score-green: #6CF95D;
  --score-yellow: #FAF75A;
  --score-orange: #FB9258;
}

/* Global type */
body, .card, .table, .kpi-title, .kpi-sub, .hero-title, .paper-title { 
  font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, Noto Sans, sans-serif; 
}

/* Glass effects */
.glass-effect-card {
  background: var(--glass-bg) !important;
  backdrop-filter: blur(14px) !important;
  -webkit-backdrop-filter: blur(14px) !important;
  border: 1px solid var(--glass-brd) !important;
  border-radius: var(--radius) !important;
  box-shadow: var(--shadow) !important;
  overflow: hidden;
}

.depth-effect:hover {
  transform: translateY(-2px);
  transition: transform .25s ease;
  box-shadow: 0 12px 32px rgba(15,23,42,.15) !important;
}

.container-xxl {
  padding-top: 2rem;
  padding-bottom: 2rem;
}

/* Header Hero */
.hero-header {
  background: linear-gradient(135deg, rgba(255,255,255,.95), rgba(255,255,255,.85));
  backdrop-filter: blur(20px);
  border: 1px solid rgba(15,23,42,.06);
  border-radius: 20px;
  padding: 2rem;
  margin-bottom: 2rem;
  box-shadow: 0 20px 40px rgba(15,23,42,.08);
}

.hero-title {
  font-size: 2.2rem;
  font-weight: 900;
  color: var(--text-dark);
  margin-bottom: 0.5rem;
  background: linear-gradient(135deg, #0ea5e9, #3b82f6);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.hero-subtitle {
  font-size: 1.1rem;
  color: var(--muted);
  margin-bottom: 0;
}

.hero-badge {
  padding: 0.75rem 1.5rem;
  border-radius: 12px;
  font-weight: 700;
  font-size: 0.9rem;
  border: 2px solid;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.badge-light-success {
  background: #ecfdf5;
  color: #065f46;
  border-color: #22c55e;
}

.badge-light-warning {
  background: #fff7ed;
  color: #9a3412;
  border-color: #f59e0b;
}

.badge-light-danger {
  background: #fef2f2;
  color: #991b1b;
  border-color: #ef4444;
}

/* Main Card */
.paper-card {
  background: linear-gradient(135deg, rgba(255,255,255,.95), rgba(255,255,255,.88));
  backdrop-filter: blur(20px);
  border: 1px solid rgba(15,23,42,.06);
  border-radius: 24px;
  padding: 2.5rem;
  box-shadow: 0 25px 50px rgba(15,23,42,.10);
  margin-bottom: 2rem;
  position: relative;
  overflow: hidden;
}

.paper-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, #0ea5e9, #3b82f6, #8b5cf6);
  border-radius: 24px 24px 0 0;
}

.paper-title {
  font-size: 1.8rem;
  font-weight: 900;
  color: var(--text-dark);
  margin-bottom: 0.5rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.paper-subtitle {
  color: var(--muted);
  font-size: 1rem;
  margin-bottom: 1.5rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

/* Stats Chips */
.stats-container {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  margin: 1.5rem 0;
}

.stat-chip {
  background: rgba(255,255,255,.9);
  border: 1px solid rgba(15,23,42,.08);
  border-radius: 14px;
  padding: 0.75rem 1.25rem;
  font-weight: 600;
  color: var(--text-dark);
  display: flex;
  align-items: center;
  gap: 0.5rem;
  transition: all 0.2s ease;
  box-shadow: 0 4px 12px rgba(15,23,42,.06);
}

.stat-chip:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(15,23,42,.12);
}

.stat-chip i {
  color: var(--brand);
  font-size: 1.1rem;
}

/* Action Buttons */
.actions-container {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
  margin-top: 2rem;
  justify-content: flex-end;
}

.btn-action {
  padding: 1rem 2rem;
  border-radius: 16px;
  font-weight: 800;
  font-size: 1rem;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  text-decoration: none;
  border: none;
  cursor: pointer;
  position: relative;
  overflow: hidden;
}

.btn-primary-action {
  background: linear-gradient(135deg, #0ea5e9, #3b82f6);
  color: white;
  box-shadow: 0 8px 20px rgba(14, 165, 233, .3);
}

.btn-primary-action:hover {
  transform: translateY(-3px);
  box-shadow: 0 12px 30px rgba(14, 165, 233, .4);
  color: white;
}

.btn-secondary-action {
  background: rgba(255,255,255,.9);
  color: var(--text-dark);
  border: 2px solid rgba(15,23,42,.12);
}

.btn-secondary-action:hover {
  background: rgba(255,255,255,1);
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(15,23,42,.12);
  color: var(--text-dark);
}

/* Tips Card */
.tips-card {
  background: linear-gradient(135deg, rgba(236, 253, 245, .95), rgba(220, 252, 231, .88));
  backdrop-filter: blur(20px);
  border: 1px solid rgba(34, 197, 94, .2);
  border-radius: 20px;
  padding: 2rem;
  box-shadow: 0 15px 35px rgba(34, 197, 94, .1);
}

.tips-title {
  font-size: 1.3rem;
  font-weight: 800;
  color: #065f46;
  margin-bottom: 1rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.tips-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.tips-list li {
  padding: 0.75rem 0;
  color: #047857;
  font-weight: 500;
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
}

.tips-list li::before {
  content: '✓';
  color: #22c55e;
  font-weight: bold;
  font-size: 1.1rem;
  margin-top: 0.1rem;
  flex-shrink: 0;
}

.tips-list li strong {
  color: #064e3b;
}

/* Responsive */
@media (max-width: 768px) {
  .container-xxl {
    padding: 1rem;
  }
  
  .hero-header {
    padding: 1.5rem;
    text-align: center;
  }
  
  .hero-title {
    font-size: 1.8rem;
    flex-direction: column;
    gap: 0.5rem;
  }
  
  .paper-card {
    padding: 1.5rem;
  }
  
  .paper-title {
    font-size: 1.4rem;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
  }
  
  .stats-container {
    justify-content: center;
  }
  
  .actions-container {
    justify-content: center;
    flex-direction: column;
  }
  
  .btn-action {
    justify-content: center;
    width: 100%;
  }

  .hero-header .d-flex {
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 1rem;
  }
}

/* Animation d'entrée */
.fade-in {
  animation: fadeInUp 0.6s ease-out;
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>

<?php openBackgroundContainer(); ?>
<div class="container-xxl">
  
  <!-- Header Hero -->
  <div class="hero-header fade-in depth-effect">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
      <div>
        <h1 class="hero-title">
          <i class="fa-solid fa-graduation-cap"></i>
          Évaluations Connaissances
        </h1>
        <p class="hero-subtitle">
          <?= htmlspecialchars($paperLabel, ENT_QUOTES, 'UTF-8') ?> • Répondez au QCM de connaissance technique
          <br><strong>Durée indicative : 1 heure</strong>
        </p>
      </div>
      <div>
        <span class="hero-badge <?= htmlspecialchars($badge['class'], ENT_QUOTES, 'UTF-8') ?>">
          <?php if ($badge['class'] === 'badge-light-success'): ?>
            <i class="fa-solid fa-check-circle"></i>
          <?php elseif ($badge['class'] === 'badge-light-warning'): ?>
            <i class="fa-solid fa-clock"></i>
          <?php else: ?>
            <i class="fa-solid fa-play"></i>
          <?php endif; ?>
          <?= htmlspecialchars($badge['text'], ENT_QUOTES, 'UTF-8') ?>
        </span>
      </div>
    </div>
  </div>

  <!-- Main Paper Card -->
  <div class="paper-card fade-in depth-effect">
    <h2 class="paper-title">
      <i class="fa-solid fa-file-lines"></i>
      QCM Connaissances — Épreuve figée
    </h2>
    <p class="paper-subtitle">
      <i class="fa-solid fa-bullhorn"></i>
      Campagne : <strong><?= htmlspecialchars($paperLabel, ENT_QUOTES, 'UTF-8') ?></strong>
    </p>

    <!-- Stats -->
    <div class="stats-container">
      <div class="stat-chip">
        <i class="fa-solid fa-layer-group"></i>
        <span>Niveau : <strong>___</strong></span>
      </div>
      <div class="stat-chip">
        <i class="fa-solid fa-question-circle"></i>
        <span>Questions : <strong><?= (int)$qTotal ?></strong></span>
      </div>
      <div class="stat-chip">
        <i class="fa-solid fa-list-check"></i>
        <span>Format : <strong>QCM</strong></span>
      </div>
      <?php if (!empty($orientation)): ?>
      <div class="stat-chip">
        <i class="fa-solid fa-compass"></i>
        <span>Orientation : <strong><?= count($orientation) ?></strong> question(s)</span>
      </div>
      <?php endif; ?>
    </div>

    <!-- Actions -->
    <div class="actions-container">
      <?php if ($status !== 'in_progress'): ?>
        <button type="button" class="btn-action btn-secondary-action" 
                onclick="document.getElementById('tips').scrollIntoView({behavior:'smooth'})">
          <i class="fa-solid fa-info-circle"></i>
          Consignes
        </button>
      <?php endif; ?>
      
      <?php if ($status === 'not_started'): ?>
        <form method="post" action="" style="display: inline">
          <input type="hidden" name="start" value="1">
          <button type="submit" class="btn-action btn-primary-action">
            <i class="fa-solid fa-play"></i>
            <?= htmlspecialchars($ctaLabel, ENT_QUOTES, 'UTF-8') ?>
            <i class="fa-solid fa-arrow-right"></i>
          </button>
        </form>
      <?php else: ?>
        <a href="<?= htmlspecialchars($ctaHref, ENT_QUOTES, 'UTF-8') ?>" class="btn-action btn-primary-action">
          <?php if ($status === 'in_progress'): ?>
            <i class="fa-solid fa-play"></i>
          <?php else: ?>
            <i class="fa-solid fa-eye"></i>
          <?php endif; ?>
          <?= htmlspecialchars($ctaLabel, ENT_QUOTES, 'UTF-8') ?>
          <i class="fa-solid fa-arrow-right"></i>
        </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Tips Card -->
  <div id="tips" class="tips-card fade-in">
    <div class="tips-title">
      <i class="fa-solid fa-lightbulb"></i>
      Avant de commencer
    </div>
    <ul class="tips-list">
      <li>
        Assurez-vous d'avoir une <strong>connexion stable</strong> et de ne pas recharger la page pendant le QCM
      </li>
      <li>
        Vous pouvez <strong>sauvegarder et reprendre</strong> plus tard tant que l'épreuve n'est pas validée
      </li>
      <li>
        Temps indicatif : <strong>1 heure </strong>. L'épreuve se compose de questions à choix multiples
      </li>
      <li>
        Lisez attentivement chaque question avant de répondre
      </li>
      <li>
        En cas de problème technique, <strong>contactez immédiatement</strong> l'assistance
      </li>
    </ul>
  </div>
</div>

<?php
closeBackgroundContainer();
include_once "partials/footer.php";
?>

<script>
// Animation d'entrée progressive
document.addEventListener('DOMContentLoaded', function() {
  const elements = document.querySelectorAll('.fade-in');
  elements.forEach((el, index) => {
    el.style.animationDelay = `${index * 0.1}s`;
  });
});
</script>