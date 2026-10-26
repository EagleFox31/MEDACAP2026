<?php
require_once __DIR__ . '/../../core/medacap_config.php';
// declare(strict_types=1);

/* Debug (désactive en prod) */
// ini_set('display_errors','1'); ini_set('display_startup_errors','1'); error_reporting(E_ALL);

session_start();
require_once __DIR__.'/../../vendor/autoload.php';
include_once __DIR__.'/../language.php';
include_once __DIR__.'/../partials/background-manager.php';
setPageBackground('bg-dashboard');

if (empty($_SESSION['id'])) { header('Location: ../../'); exit(); }

use MongoDB\Client;
use MongoDB\BSON\ObjectId;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;

/* ===================== CONFIG & HELPERS ===================== */
if (!defined('MONGO_DSN')) {
    define('MONGO_DSN', medacapMongoUri());
}
const DB_NAME   = 'academy';

function now_str(): string { return date('d-m-y H:i:s'); }
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function u1(?string $s): ?string {
  if ($s===null) return null; $s=trim($s); if ($s==='') return null;
  return mb_strtoupper(mb_substr($s,0,1)).mb_substr($s,1);
}
function normalizeQuestionType(?string $raw): ?string {
  if ($raw===null) return null; $l=mb_strtolower(trim($raw));
  return $l==='declarative' ? 'Declarative' : ($l==='factuelle'?'Factuelle':u1($raw));
}
function answerFromLetter(?string $letter, array $props): ?string {
  switch (strtoupper(trim((string)$letter))) {
    case 'A': return $props[0]??null; case 'B': return $props[1]??null;
    case 'C': return $props[2]??null; case 'D': return $props[3]??null;
    default: return null;
  }
}
function bytes_human(int $b): string {
  if ($b>=1048576) return number_format($b/1048576,2).' Mo';
  if ($b>=1024) return number_format($b/1024,0).' Ko'; return $b.' o';
}

/* ====== Téléchargement du template (Robuste) ====== */
if (isset($_GET['download']) && $_GET['download']==='template_bodypaint') {
  $spreadsheet = new Spreadsheet();
  $sheet = $spreadsheet->getActiveSheet();
  $sheet->fromArray([
    ['Niveau (Junior|Senior|Expert)','Type (Factuelle|Declarative)','Référence (unique)','Libellé (Question)','Proposition A','Proposition B','Proposition C','Proposition D','Image (URL)','Réponse (A|B|C|D)'],
    ['Junior','Factuelle','CP-001','Quelle est la fonction du mastic polyester ?','Combler les défauts','Accroître la brillance','Décaper','Dégraisser','','A'],
    ['Expert','Declarative','CP-900','Préparation avant mise en peinture : ordre correct ?','1-...-1','2-...-2','3-...-3','','','',''],
  ]);

  $tmp = tempnam(sys_get_temp_dir(),'tpl_'); $file = $tmp.'.xlsx';
  IOFactory::createWriter($spreadsheet,'Xlsx')->save($file);

  while(ob_get_level()) ob_end_clean();
  header_remove('Content-Encoding');
  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment; filename="modele-import-questions-carrosserie-peinture.xlsx"');
  header('Content-Length: '.filesize($file));
  readfile($file); @unlink($file); exit;
}

/* ====== Traitements AJAX (validate / import) ====== */
if (($_POST['ajax'] ?? null) === '1') {
  $phase = $_POST['phase'] ?? 'validate';
  $level = u1($_POST['level'] ?? 'Junior'); // sélection utilisateur

  try {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
      throw new RuntimeException('CSRF token invalide.');
    }
    if (!isset($_FILES['excel']) || $_FILES['excel']['error'] !== UPLOAD_ERR_OK) {
      throw new RuntimeException('Fichier manquant ou invalide.');
    }
    $ext = strtolower(pathinfo($_FILES['excel']['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($ext,['xls','xlsx'],true)) throw new RuntimeException('Extension refusée (.xls, .xlsx).');

    $spreadsheet = IOFactory::load($_FILES['excel']['tmp_name']);
    $rows = $spreadsheet->getActiveSheet()->toArray(null,true,true,true);

    if ($phase === 'validate') {
      // Prévisualisation (5 premières lignes utiles)
      $preview = [];
      $i=0;
      foreach ($rows as $r) {
        if ($i++===0) continue; // skip header
        $empty=true; foreach (range('A','J') as $c) { if (trim((string)($r[$c]??''))!==''){ $empty=false; break; } }
        if ($empty) continue;
        $preview[] = [
          'level'=>$r['A']??'','type'=>$r['B']??'','ref'=>$r['C']??'','label'=>$r['D']??'',
          'A'=>$r['E']??'','B'=>$r['F']??'','C'=>$r['G']??'','D'=>$r['H']??'',
          'image'=>$r['I']??'','ans'=>$r['J']??'',
        ];
        if (count($preview)>=5) break;
      }
      while(ob_get_level()) ob_end_clean();
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(['ok'=>true,'preview'=>$preview,'rows'=>max(0,count($rows)-1)], JSON_UNESCAPED_UNICODE);
      exit;
    }

    // === phase IMPORT ===
    $mongo    = new Client(MONGO_DSN);
    $db       = $mongo->{DB_NAME};
    // ⬇️ Collections génériques
    $questions= $db->questions;     // ex. référentiel de questions
    $quizzes  = $db->quizzes;       // ex. QCM (groupe de questions)

    $cntInserted=0; $cntUpdated=0; $cntSkipped=0;
    $byLevel=['Junior'=>0,'Senior'=>0,'Expert'=>0,'Autre'=>0];
    $byType=['Factuelle'=>0,'Declarative'=>0,'Autre'=>0];

    $i=0;
    foreach ($rows as $line) {
      if ($i++===0) continue;
      $empty=true; foreach(range('A','J') as $c){ if (trim((string)($line[$c]??''))!==''){ $empty=false; break; } }
      if ($empty) continue;

      // Colonnes du template
      $rowLevel = u1($line['A'] ?? '') ?: $level;   // priorise colonne A, sinon sélection UI
      $type     = normalizeQuestionType($line['B'] ?? '') ?: 'Factuelle';
      $ref      = trim((string)($line['C'] ?? ''));
      $label    = trim((string)($line['D'] ?? ''));
      $p1       = trim((string)($line['E'] ?? ''));
      $p2       = trim((string)($line['F'] ?? ''));
      $p3       = trim((string)($line['G'] ?? ''));
      $p4       = trim((string)($line['H'] ?? ''));
      $image    = trim((string)($line['I'] ?? ''));
      $ansL     = trim((string)($line['J'] ?? ''));

      if ($ref==='' || $label==='' || $rowLevel==='') { $cntSkipped++; continue; }

      if (isset($byLevel[$rowLevel])) $byLevel[$rowLevel]++; else $byLevel['Autre']++;
      if (isset($byType[$type])) $byType[$type]++; else $byType['Autre']++;

      $props = [$p1,$p2,$p3,$p4];
      $answer = ($type==='Factuelle') ? u1(answerFromLetter($ansL,$props) ?? '') : null;

      // Upsert idempotent sur la collection "questions"
      // Clé logique: ref + level + type + speciality
      $filterQ = [
        'ref'        => $ref,
        'level'      => $rowLevel,
        'type'       => $type,
        'speciality' => 'Carrosserie & Peinture'
      ];
      $setQ = [
        'label'      => u1($label),
        'image'      => $image,
        'proposal1'  => u1($p1),
        'proposal2'  => u1($p2),
        'proposal3'  => u1($p3),
        'proposal4'  => u1($p4),
        'brandScope' => 'ALL',
        'active'     => true,
        'updated'    => now_str()
      ];
      if ($type==='Factuelle') { $setQ['answer'] = $answer; }

      $resQ = $questions->updateOne(
        $filterQ,
        ['$set'=>$setQ, '$setOnInsert'=>['created'=>now_str()]],
        ['upsert'=>true]
      );

      // _id de la question
      if ($resQ->getUpsertedCount() > 0) {
        $cntInserted++;
        $questionId = $resQ->getUpsertedId();
      } else {
        $docQ = $questions->findOne($filterQ, ['projection'=>['_id'=>1]]);
        $questionId = $docQ ? $docQ->_id : null;
        $cntUpdated += ($resQ->getModifiedCount() > 0) ? 1 : 0;
        if ($resQ->getModifiedCount()===0 && !$docQ) $cntSkipped++;
      }

      // Rattacher la question à un quiz "quizzes" (speciality CP + level + type)
      if ($questionId instanceof ObjectId) {
        $quizType = ($type==='Declarative') ? 'Declaratif' : 'Factuel';

        // Trouver ou créer le quiz CP correspondant au niveau+type
        $quiz = $quizzes->findOne(
          ['speciality'=>'Carrosserie & Peinture','level'=>$rowLevel,'type'=>$quizType,'active'=>true],
          ['projection'=>['_id'=>1]]
        );

        if (!$quiz) {
          $ins = $quizzes->insertOne([
            'label'      => "QCM Carrosserie & Peinture - {$rowLevel}",
            'speciality' => 'Carrosserie & Peinture',
            'brandScope' => 'ALL',
            'level'      => $rowLevel,
            'type'       => $quizType,
            'questions'  => [],
            'total'      => 0,
            'active'     => true,
            'created'    => now_str(),
            'updated'    => now_str()
          ]);
          $quizId = $ins->getInsertedId();
        } else {
          $quizId = $quiz->_id;
        }

        // addToSet + inc total si ajout effectif
        $r = $quizzes->updateOne(['_id'=>$quizId], ['$addToSet'=>['questions'=>$questionId]]);
        if ($r->getModifiedCount() > 0) {
          $quizzes->updateOne(['_id'=>$quizId], [
            '$inc'=>['total'=>1],
            '$set'=>['updated'=>now_str()]
          ]);
        }
      }
    }

    $payload = [
      'ok'=>true,
      'success'=>sprintf('Import terminé : %d insérées, %d mises à jour, %d ignorées.', $cntInserted,$cntUpdated,$cntSkipped),
      'kpi'=>[
        'file'=>$_FILES['excel']['name'] ?? '',
        'size'=>bytes_human((int)($_FILES['excel']['size'] ?? 0)),
        'total_rows'=>max(0,count($rows)-1),
        'processed'=>$cntInserted+$cntUpdated,
        'inserted'=>$cntInserted, 'updated'=>$cntUpdated, 'skipped'=>$cntSkipped,
        'by_level'=>$byLevel, 'by_type'=>$byType
      ]
    ];
    while(ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;

  } catch (\Throwable $e) {
    while(ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'error'=>'Échec : '.$e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
  }
}

/* ===================== VUE (Stepper + Progress) ===================== */
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
include_once __DIR__.'/partials/header.php';
?>
<title>Import QCM Carrosserie & Peinture | CFAO Mobility Academy</title>
<style>
html,body{max-width:100%;overflow-x:hidden;}
.import-wrap{min-height:calc(90vh - 80px);display:flex;align-items:center;justify-content:center;padding:32px 16px;}
.glass-card{width:100%;max-width:980px;background:rgba(255,255,255,.78);backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px);border:1px solid rgba(255,255,255,.25);border-radius:16px;box-shadow:0 10px 30px rgba(31,38,135,.18);overflow:hidden;}
.card-header-glass{padding:28px 32px 10px 32px;background:transparent;}
.center-header{display:flex;flex-direction:column;align-items:center;gap:12px;text-align:center;}
.brand-logo{height:60px}
.import-title{margin:0;letter-spacing:.5px;font-weight:800;font-size:26px;color:#1b1b1b;text-transform:uppercase;}
.toolbar-links{margin-top:10px}

.stepper{display:flex;gap:8px;align-items:center;justify-content:center;padding:8px 16px 0 16px}
.step{flex:1;display:flex;align-items:center;gap:8px}
.step .dot{width:22px;height:22px;border-radius:50%;background:#e2e8f0;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;color:#334155}
.step .label{font-size:12px;color:#334155}
.step.active .dot{background:#3b82f6;color:#fff}
.step.done .dot{background:#10b981;color:#fff}
.stepper .bar{height:4px;background:#e2e8f0;border-radius:999px;flex:1}
.stepper .bar.fill{background:#3b82f6}

.section{padding:16px 32px 28px 32px}
.dropzone{position:relative;border:2px dashed #3b82f6;border-radius:12px;background:rgba(59,130,246,.06);min-height:180px;display:flex;align-items:center;justify-content:center;text-align:center;padding:24px;transition:.2s}
.dropzone.dragover{background:rgba(59,130,246,.12);box-shadow:0 0 0 4px rgba(59,130,246,.12) inset;}
.dropzone input[type="file"]{position:absolute;inset:0;opacity:0;cursor:pointer;}
.filename{margin-top:8px;font-weight:700;color:#0f172a}
.form-actions{display:flex;gap:12px;justify-content:flex-end;margin-top:18px;}
.kpi-grid{margin-top:18px;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.kpi-card{background:rgba(255,255,255,.9);border:1px solid rgba(0,0,0,.05);border-radius:12px;padding:14px}
.kpi-title{font-size:12px;color:#334155;text-transform:uppercase}
.kpi-value{font-weight:800;font-size:22px;color:#0f172a}
.badge-soft{background:#f1f5f9;color:#0f172a;border-radius:10px;padding:2px 8px;font-size:12px}

.progress-container{background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.15);border-radius:12px;padding:16px;margin:16px 0;display:none}
.progress-container.active{display:block}
.progress-bar-wrapper{background:rgba(255,255,255,.6);border-radius:8px;height:12px;overflow:hidden}
.progress-bar{height:100%;background:linear-gradient(90deg,#3b82f6 0%,#1d4ed8 100%);border-radius:8px;width:0%;transition:width .3s}
.progress-text{text-align:center;font-weight:600;color:#1e40af;margin-top:6px;font-size:14px}
.preview{background:rgba(255,255,255,.6);border:1px solid rgba(0,0,0,.06);border-radius:10px;padding:10px;margin-top:12px;max-height:180px;overflow:auto}
.preview table{width:100%;font-size:13px}
.preview th, .preview td{padding:6px;border-bottom:1px solid rgba(0,0,0,.06)}
</style>

<?php openBackgroundContainer(); ?>
<div class="import-wrap">
  <div class="glass-card">
    <div class="card-header-glass">
      <div class="center-header">
        <img src="../../public/images/logo.png" class="brand-logo" alt="CFAO Mobility" />
        <h1 class="import-title">Import QCM Carrosserie &amp; Peinture</h1>
        <div class="toolbar-links">
          <a href="?download=template_bodypaint" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-cloud-download me-1"></i>Modèle (.xlsx)
          </a>
        </div>
      </div>

      <!-- Stepper -->
      <div class="stepper" id="stepper">
        <div class="step active" data-step="1"><div class="dot">1</div><div class="label">Fichier</div></div>
        <div class="bar" id="bar12"></div>
        <div class="step" data-step="2"><div class="dot">2</div><div class="label">Paramètres</div></div>
        <div class="bar" id="bar23"></div>
        <div class="step" data-step="3"><div class="dot">3</div><div class="label">Import</div></div>
        <div class="bar" id="bar34"></div>
        <div class="step" data-step="4"><div class="dot">4</div><div class="label">Résultats</div></div>
      </div>
    </div>

    <div class="section">
      <form id="wizardForm" enctype="multipart/form-data" method="POST" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
        <input type="hidden" name="ajax" value="1">

        <!-- Étape 1 : Fichier -->
        <div id="panel1">
          <div id="dz" class="dropzone mb-2">
            <input type="file" name="excel" id="excel" accept=".xlsx,.xls" required />
            <div>
              <h5>Sélectionnez le fichier Excel</h5>
              <div class="text-muted">Glissez-déposez ou cliquez pour parcourir</div>
              <div class="filename text-muted" id="fileName">Formats acceptés : .xls, .xlsx</div>
            </div>
          </div>

          <div id="previewBox" class="preview" style="display:none"></div>

          <div class="form-actions">
            <button type="button" id="next1" class="btn btn-primary" disabled>Continuer</button>
          </div>
        </div>

        <!-- Étape 2 : Paramètres -->
        <div id="panel2" style="display:none">
          <div class="mb-3">
            <label class="form-label"><strong>Spécialité</strong></label>
            <input type="text" class="form-control" value="Carrosserie & Peinture" disabled>
            <div class="form-text">Cette spécialité est dédiée et sera importée en <em>toutes marques</em>.</div>
          </div>
          <div class="mb-3">
            <label for="level" class="form-label"><strong>Niveau</strong></label>
            <select id="level" name="level" class="form-select" required>
              <option value="Junior">Junior</option>
              <option value="Senior">Senior</option>
              <option value="Expert">Expert</option>
            </select>
            <div class="form-text">Le niveau peut aussi être précisé ligne par ligne dans la colonne A du fichier.</div>
          </div>

          <div class="form-actions">
            <button type="button" id="back2" class="btn btn-light">Retour</button>
            <button type="button" id="next2" class="btn btn-primary">Importer</button>
          </div>
        </div>

        <!-- Étape 3 : Import -->
        <div id="panel3" style="display:none">
          <div id="progressBox" class="progress-container">
            <div class="progress-bar-wrapper"><div id="progressBar" class="progress-bar"></div></div>
            <div id="progressText" class="progress-text">Préparation…</div>
          </div>
        </div>

        <!-- Étape 4 : Résultats -->
        <div id="panel4" style="display:none">
          <div id="finalMsg" class="mb-3"></div>
          <div id="kpiSlot" class="mb-3"></div>
          <div class="form-actions">
            <button type="button" id="downloadReport" class="btn btn-outline-secondary">
              <i class="bi bi-download me-1"></i>Télécharger le rapport (.json)
            </button>
            <button type="button" id="restart" class="btn btn-soft">Nouvel import</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>
<?php closeBackgroundContainer(); ?>

<script>
(function(){
  'use strict';

  /* ===== Stepper helpers ===== */
  function stepActive(n){
    document.querySelectorAll('.step').forEach(s=>s.classList.remove('active','done'));
    const steps=[...document.querySelectorAll('.step')];
    steps.forEach((s,i)=>{ if(i+1<n) s.classList.add('done'); else if(i+1===n) s.classList.add('active'); });
    document.getElementById('bar12').classList.toggle('fill', n>1);
    document.getElementById('bar23').classList.toggle('fill', n>2);
    document.getElementById('bar34').classList.toggle('fill', n>3);
  }
  function showPanel(id){
    ['panel1','panel2','panel3','panel4'].forEach(p=>document.getElementById(p).style.display='none');
    document.getElementById(id).style.display='block';
  }

  let lastReport = null;

  function renderFinal(state, text, kpi) {
    const finalMsg = document.getElementById('finalMsg');
    const cls = state === 'success' ? 'success' : 'danger';
    finalMsg.innerHTML = `
      <div class="alert alert-${cls} alert-dismissible fade show" role="alert">
        <strong>${text || (state==='success' ? 'Import terminé.' : 'Échec de l’import')}</strong>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
      </div>
    `;
    progressBox.classList.remove('active');
    if (kpi) {
      document.getElementById('kpiSlot').innerHTML = buildKpiHtml(kpi);
    } else {
      document.getElementById('kpiSlot').innerHTML = '';
    }
  }

  /* ===== Étape 1 : fichier + preview ===== */
  const dz = document.getElementById('dz');
  const excelInput = document.getElementById('excel');
  const fileName = document.getElementById('fileName');
  const previewBox = document.getElementById('previewBox');
  const next1 = document.getElementById('next1');
  const postUrl = window.location.pathname;

  ['dragenter','dragover'].forEach(evt => dz.addEventListener(evt,e=>{e.preventDefault();e.stopPropagation();dz.classList.add('dragover');}));
  ['dragleave','drop'].forEach(evt => dz.addEventListener(evt,e=>{e.preventDefault();e.stopPropagation();dz.classList.remove('dragover');}));
  dz.addEventListener('drop', e => { if (e.dataTransfer.files?.length){ excelInput.files=e.dataTransfer.files; onFileChange(); }});
  excelInput.addEventListener('change', onFileChange);

  function onFileChange(){
    const f = excelInput.files?.[0];
    fileName.textContent = f ? f.name : 'Formats acceptés : .xls, .xlsx';
    next1.disabled = !f;
    if (!f) return;

    const fd = new FormData();
    fd.append('ajax','1'); fd.append('phase','validate');
    fd.append('csrf_token','<?php echo e($_SESSION['csrf_token']); ?>');
    fd.append('excel', f);

    const xhr = new XMLHttpRequest();
    xhr.open('POST', postUrl, true);
    xhr.responseType='json';
    xhr.onload = ()=>{
      const res = xhr.response;
      if (!res?.ok){ previewBox.style.display='none'; alert(res?.error||'Erreur de prévisualisation'); return; }
      previewBox.style.display='block';
      previewBox.innerHTML = buildPreviewHtml(res.preview, res.rows);
    };
    xhr.send(fd);
  }

  function buildPreviewHtml(rows,total){
    let h = `<div class="mb-2"><strong>Prévisualisation</strong> — ${total||0} ligne(s) détectées</div>`;
    h += `<table><thead><tr>
      <th>Niveau</th><th>Type</th><th>Réf</th><th>Libellé</th><th>A</th><th>B</th><th>C</th><th>D</th><th>Image</th><th>Réponse</th>
    </tr></thead><tbody>`;
    (rows||[]).forEach(r=>{
      h += `<tr><td>${r.level||''}</td><td>${r.type||''}</td><td>${r.ref||''}</td><td>${r.label||''}</td>
        <td>${r.A||''}</td><td>${r.B||''}</td><td>${r.C||''}</td><td>${r.D||''}</td><td>${r.image||''}</td><td>${r.ans||''}</td></tr>`;
    });
    h += `</tbody></table>`;
    return h;
  }

  next1.addEventListener('click', ()=>{ stepActive(2); showPanel('panel2'); });

  /* ===== Étape 2 : paramètres ===== */
  const back2 = document.getElementById('back2');
  const next2 = document.getElementById('next2');
  back2.addEventListener('click', ()=>{ stepActive(1); showPanel('panel1'); });

  next2.addEventListener('click', ()=>{
    const level = document.getElementById('level').value;
    if (!excelInput.files?.length){ alert('Veuillez sélectionner un fichier.'); return; }
    stepActive(3); showPanel('panel3');
    runImport(level);
  });

  /* ===== Étape 3 : import + progress ===== */
  const progressBox = document.getElementById('progressBox');
  const progressBar = document.getElementById('progressBar');
  const progressText = document.getElementById('progressText');
  function setBar(p){ progressBar.style.width = Math.max(0,Math.min(100,p))+'%'; }

  function runImport(level){
    progressBox.classList.add('active');
    setBar(0);
    progressText.textContent = 'Préparation…';

    const fd = new FormData();
    fd.append('ajax','1'); fd.append('phase','import');
    fd.append('csrf_token','<?php echo e($_SESSION['csrf_token']); ?>');
    fd.append('level', level);
    fd.append('excel', excelInput.files[0]);

    const xhr = new XMLHttpRequest();
    xhr.open('POST', postUrl, true);
    xhr.responseType='json';

    xhr.upload.onprogress = (ev)=>{ if(ev.lengthComputable){ const p=(ev.loaded/ev.total)*40; setBar(p); progressText.textContent='Téléversement… '+Math.round(p)+'%'; } };
    let sim=null;
    xhr.onloadstart = ()=>{ let p=40; sim=setInterval(()=>{ p=Math.min(90,p+2+Math.random()*3); setBar(p); progressText.textContent='Traitement… '+Math.round(p)+'%'; },180); };
    xhr.onreadystatechange = ()=>{
      if (xhr.readyState===4){
        clearInterval(sim);
        setBar(100);
        progressText.textContent='Terminé.';

        const res = xhr.response;
        if (!res || res.ok !== true) {
          lastReport = { ok:false, error: res?.error || 'Erreur pendant l’import', at: new Date().toISOString() };
          renderFinal('error', lastReport.error, null);
          stepActive(4); showPanel('panel4');
          return;
        }

        lastReport = {
          ok: true,
          message: res.success || 'Import terminé.',
          kpi: res.kpi || null,
          at: new Date().toISOString()
        };
        renderFinal('success', lastReport.message, lastReport.kpi);
        stepActive(4); showPanel('panel4');
      }
    };

    xhr.send(fd);
  }

  function buildKpiHtml(k){
    return `
    <div class="kpi-grid">
      <div class="kpi-card"><div class="kpi-title">Fichier</div><div class="kpi-value">${k.file||''}</div><div><span class="badge-soft">${k.size||''}</span></div></div>
      <div class="kpi-card"><div class="kpi-title">Lignes lues</div><div class="kpi-value">${k.total_rows||0}</div><div><span class="badge-soft">traitées : ${k.processed||0}</span></div></div>
      <div class="kpi-card"><div class="kpi-title">Insérées</div><div class="kpi-value text-success">${k.inserted||0}</div></div>
      <div class="kpi-card"><div class="kpi-title">Mises à jour</div><div class="kpi-value text-primary">${k.updated||0}</div></div>
      <div class="kpi-card"><div class="kpi-title">Ignorées</div><div class="kpi-value text-warning">${k.skipped||0}</div></div>
      <div class="kpi-card"><div class="kpi-title">Répartition Type</div>
        <div><span class="badge-soft me-1">Factuelle: ${(k.by_type?.Factuelle)||0}</span>
             <span class="badge-soft me-1">Déclarative: ${(k.by_type?.Declarative)||0}</span></div>
      </div>
      <div class="kpi-card"><div class="kpi-title">Par niveau</div>
        <div><span class="badge-soft me-1">Junior: ${(k.by_level?.Junior)||0}</span>
             <span class="badge-soft me-1">Senior: ${(k.by_level?.Senior)||0}</span>
             <span class="badge-soft me-1">Expert: ${(k.by_level?.Expert)||0}</span></div>
      </div>
    </div>`;
  }

  /* ===== Étape 4 : Reset ===== */
  document.getElementById('restart').addEventListener('click', ()=>{
    document.getElementById('wizardForm').reset();
    fileName.textContent='Formats acceptés : .xls, .xlsx';
    previewBox.style.display='none'; previewBox.innerHTML='';
    progressBox.classList.remove('active');
    setBar(0); progressText.textContent='Préparation…';
    stepActive(1); showPanel('panel1');
  });

  window.addEventListener('load', function () {
    document.body.removeAttribute('data-kt-app-page-loading');
    document.documentElement.classList.remove('page-loading');
    var el = document.querySelector('#kt_app_page_loader, .page-loader');
    if (el) el.style.display = 'none';
  });

  document.getElementById('downloadReport').addEventListener('click', ()=>{
    if (!lastReport) { alert('Aucun rapport à télécharger.'); return; }
    const blob = new Blob([JSON.stringify(lastReport, null, 2)], {type: 'application/json'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `rapport-import-${new Date().toISOString().replace(/[:.]/g,'-')}.json`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  });

})();
</script>

<?php include_once __DIR__.'/partials/footer.php'; ?>
