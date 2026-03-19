<?php
session_start();
include_once "../language.php";
include_once "../partials/background-manager.php";

if (!isset($_SESSION["id"])) {
  header("Location: ../../");
  exit();
}

require_once "../../vendor/autoload.php";
use MongoDB\Client;
use MongoDB\BSON\ObjectId;

/* ---------- Mongo ---------- */
$client     = new Client("mongodb://localhost:27017");
$academy    = $client->academy;
$usersCol   = $academy->users;
$resultsCol = $academy->results;
$allocCol   = $academy->allocations;
$subsCol    = $academy->cfao_subsidiaries;

setPageBackground("bg-dashboard");

$AVATAR_FEMME  = '../../public/images/femme.png';
$AVATAR_ENFANT = '../../public/images/enfant.png';

/* ============================================================
|  NOUVEAU — Récupération des campagnes disponibles
|  (valeurs distinctes du champ `role` pour profile=Candidat)
|============================================================ */
$availableCampaigns = [];
try {
  $campPipe = [
    ['$match' => ['profile' => 'Candidat', 'active' => true]],
    ['$project' => [
      'allRoles' => [
        '$setUnion' => [
          ['$cond' => [
            ['$and' => [['$ne' => ['$role', null]], ['$ne' => ['$role', '']]]],
            [['$ifNull' => ['$role', '']]],
            []
          ]],
          ['$ifNull' => ['$roles', []]],
        ]
      ]
    ]],
    ['$unwind' => '$allRoles'],
    ['$match'  => ['allRoles' => ['$ne' => '']]],
    ['$group'  => ['_id' => '$allRoles']],
    ['$sort'   => ['_id' => 1]],
  ];
  foreach ($usersCol->aggregate($campPipe)->toArray() as $r) {
    $v = trim((string)($r['_id'] ?? ''));
    if ($v !== '') $availableCampaigns[] = $v;
  }
} catch (Throwable $e) { /* tableau vide */ }

/* ---------- Campagne active ---------- */
// Si ?campaign= est fourni ET qu'il existe, on l'utilise ; sinon première campagne dispo
$selectedCampaign = isset($_GET['campaign']) ? trim($_GET['campaign']) : null;
if ($selectedCampaign && !in_array($selectedCampaign, $availableCampaigns, true)) {
  $selectedCampaign = null; // valeur invalide -> ignore
}
if (!$selectedCampaign && !empty($availableCampaigns)) {
  $selectedCampaign = $availableCampaigns[0]; // défaut = première
}
$CAMPAIGN_ROLE = $selectedCampaign ?? '';

/* ---------- Filtres ---------- */
$selectedCountry   = isset($_GET['country'])   ? trim($_GET['country'])   : null;
$selectedAgency    = isset($_GET['agency'])    ? trim($_GET['agency'])    : null;
$selectedSpecialty = isset($_GET['specialty']) ? trim($_GET['specialty']) : null;

$page    = isset($_GET['page'])      ? max(1, (int)$_GET['page'])      : 1;
$perPage = 5;
$offset  = ($page - 1) * $perPage;

$specPerPage = 5;
$specPage    = isset($_GET['spec_page']) ? max(1, (int)$_GET['spec_page']) : 1;
$specOffset  = ($specPage - 1) * $specPerPage;

$paperId  = isset($_GET['paper']) ? trim($_GET['paper']) : null;
$paperOid = ($paperId && preg_match('/^[0-9a-f]{24}$/i', $paperId)) ? new ObjectId($paperId) : null;

/* ---------- Base match candidats ---------- */
$baseMatch = [
  'active'  => true,
  'profile' => 'Candidat',
];
if ($CAMPAIGN_ROLE !== '') {
  $baseMatch['$or'] = [
    ['role'  => $CAMPAIGN_ROLE],
    ['roles' => ['$in' => [$CAMPAIGN_ROLE]]],
  ];
}
if ($selectedCountry)   $baseMatch['country']   = $selectedCountry;
if ($selectedAgency)    $baseMatch['agency']     = $selectedAgency;
if ($selectedSpecialty) $baseMatch['specialty']  = $selectedSpecialty;

/* ---------- Normalisation des noms ---------- */
$NAME_NORMALIZATION = [
  ['$addFields' => [
    'fname'        => ['$ifNull' => ['$firstName', '']],
    'lname'        => ['$ifNull' => ['$lastName',  '']],
    'nameFallback' => ['$ifNull' => ['$username', '—']],
  ]],
  ['$addFields' => [
    'fullnameRaw' => [
      '$trim' => ['input' => ['$concat' => [['$ifNull' => ['$fname','']], ' ', ['$ifNull' => ['$lname','']]]]]
    ]
  ]],
  ['$addFields' => [
    'fullname' => [
      '$cond' => [
        ['$ne' => ['$fullnameRaw', '']],
        ['$trim' => ['input' => ['$concat' => [
          ['$ifNull' => ['$fname','']], ' ',
          ['$toUpper' => ['$ifNull' => ['$lname','']]]
        ]]]],
        '$nameFallback'
      ]
    ]
  ]]
];

function scoreClass($pct, $attempted = true) {
  if (!$attempted) return 'score-neutral';
  if ($pct >= 80) return 'score-green';
  if ($pct >= 51) return 'score-yellow';
  return 'score-orange';
}

/* ------------ KPIs / Best / Worst / TopN ------------ */
try {
  $pipeline = array_merge(
    [['$match' => $baseMatch]],
    $NAME_NORMALIZATION,
    [
      ['$lookup' => [
        'from' => 'results',
        'let'  => ['uid' => '$_id'],
        'pipeline' => array_values(array_filter([
          ['$match' => ['$expr' => ['$and' => [
            ['$eq' => ['$user', '$$uid']],
            ['$eq' => ['$type', 'Factuel']],
            ['$eq' => ['$level','Mixed']],
          ]]]],
          $paperOid ? ['$match' => ['paper'=>$paperOid]] : null,
          ['$project' => ['score'=>1,'total'=>1]],
        ])),
        'as' => 'res'
      ]],
      ['$addFields' => [
        'attempted' => ['$gt' => [['$size' => '$res'], 0]],
        'sumScore'  => ['$reduce' => ['input'=>'$res','initialValue'=>0,'in'=>['$add'=>['$$value',['$ifNull'=>['$$this.score',0]]]]]],
        'sumTotal'  => ['$reduce' => ['input'=>'$res','initialValue'=>0,'in'=>['$add'=>['$$value',['$ifNull'=>['$$this.total',0]]]]]],
      ]],
      ['$addFields' => [
        'pct' => ['$cond' => [['$gt' => ['$sumTotal', 0]],['$multiply' => [['$divide' => ['$sumScore', '$sumTotal']], 100]],0]]
      ]],
      ['$facet' => [
        'kpis' => [
          ['$group' => [
            '_id'    => null,
            'total'  => ['$sum' => 1],
            'tested' => ['$sum' => ['$cond' => ['$attempted', 1, 0]]],
            'avgPctTested' => ['$avg' => ['$cond' => ['$attempted', '$pct', null]]],
          ]],
          ['$project' => [
            '_id'=>0,'total'=>1,'tested'=>1,
            'avgPct' => ['$round' => [['$ifNull' => ['$avgPctTested', 0]], 1]],
            'completion' => ['$cond' => [
              ['$gt' => ['$total', 0]],
              ['$round' => [['$multiply' => [['$divide' => ['$tested','$total']], 100]], 1]],
              0
            ]]
          ]]
        ],
        'best'  => [['$match'=>['attempted'=>true]],['$sort'=>['pct'=>-1,'_id'=>1]],['$limit'=>1],['$project'=>['_id'=>0,'fullname'=>['$ifNull'=>['$fullname','—']],'pct'=>['$round'=>['$pct',1]]]]],
        'worst' => [['$match'=>['attempted'=>true]],['$sort'=>['pct'=>1,'_id'=>1]], ['$limit'=>1],['$project'=>['_id'=>0,'fullname'=>['$ifNull'=>['$fullname','—']],'pct'=>['$round'=>['$pct',1]]]]],
        'topN'  => [['$match'=>['attempted'=>true]],['$sort'=>['pct'=>-1,'_id'=>1]],['$limit'=>5],['$project'=>['_id'=>0,'fullname'=>['$ifNull'=>['$fullname','—']],'pct'=>['$round'=>['$pct',1]]]]],
      ]],
    ]
  );

  $agg   = $usersCol->aggregate($pipeline)->toArray();
  $facet = $agg[0] ?? ['kpis'=>[],'best'=>[],'worst'=>[],'topN'=>[]];

  $kpis  = $facet['kpis'][0]  ?? ['total'=>0,'tested'=>0,'completion'=>0,'avgPct'=>0];
  $best  = $facet['best'][0]  ?? ['fullname'=>'—','pct'=>0];
  $worst = $facet['worst'][0] ?? ['fullname'=>'—','pct'=>0];
  $topN  = $facet['topN']     ?? [];

  $notTested = max(0, (int)($kpis['total'] ?? 0) - (int)($kpis['tested'] ?? 0));

  $topNames=[]; $topPct=[];
  foreach ($topN as $r){ $topNames[]=(string)($r['fullname']??'—'); $topPct[]=(float)($r['pct']??0); }

} catch (Throwable $e) {
  http_response_code(500);
  echo "<pre>Erreur: ".htmlspecialchars($e->getMessage())."</pre>";
  exit();
}

/* ------------ Pire spécialité ------------ */
try {
  $wsMatch = array_filter([
    'type'=>'Factuel','level'=>'Mixed','total'=>['$gt'=>0],
    $paperOid ? 'paper' : '' => $paperOid
  ], function($v){ return $v !== '' && $v !== null; });

  $worstSpecDoc = $resultsCol->aggregate([
    ['$match'=>$wsMatch],
    ['$group'=>['_id'=>'$speciality','avg'=>['$avg'=>['$multiply'=>[['$divide'=>['$score','$total']],100]]],'n'=>['$sum'=>1]]],
    ['$match'=>['_id'=>['$ne'=>null]]],
    ['$sort'=>['avg'=>1]],['$limit'=>1],
    ['$project'=>['_id'=>0,'spec'=>'$_id','avg'=>['$round'=>['$avg',1]],'n'=>1]]
  ])->toArray();
  $worstSpec = $worstSpecDoc[0] ?? ['spec'=>'—','avg'=>0,'n'=>0];
} catch (Throwable $e) { $worstSpec = ['spec'=>'—','avg'=>0,'n'=>0]; }

/* ------------ Tableau Spécialités ------------ */
$currentParams = $_GET;
unset($currentParams['spec_page'], $currentParams['partial']);
$specBaseHref = $_SERVER['PHP_SELF'].'?'.http_build_query($currentParams);

try {
  $specMatch = array_filter([
    'type'=>'Factuel','level'=>'Mixed','total'=>['$gt'=>0],
    $paperOid ? 'paper' : '' => $paperOid
  ], function($v){ return $v !== '' && $v !== null; });

  $totalSpecs     = ($resultsCol->aggregate([['$match'=>$specMatch],['$group'=>['_id'=>'$speciality']],['$match'=>['_id'=>['$ne'=>null]]],['$count'=>'total']])->toArray()[0]['total'] ?? 0);
  $totalSpecPages = (int)ceil($totalSpecs / $specPerPage);

  $specResults = $resultsCol->aggregate([
    ['$match'=>$specMatch],
    ['$group'=>['_id'=>'$speciality','avg'=>['$avg'=>['$multiply'=>[['$divide'=>['$score','$total']],100]]],'n'=>['$sum'=>1]]],
    ['$match'=>['_id'=>['$ne'=>null]]],
    ['$sort'=>['avg'=>-1]],
    ['$skip'=>$specOffset],['$limit'=>$specPerPage],
    ['$project'=>['_id'=>0,'spec'=>'$_id','avg'=>['$round'=>['$avg',1]],'n'=>1]]
  ])->toArray();

  $rows = [];
  foreach ($specResults as $r) $rows[] = ['spec'=>(string)($r['spec']??''),'avg'=>(float)($r['avg']??0),'n'=>(int)($r['n']??0)];
} catch (Throwable $e) { $rows=[]; $totalSpecs=0; $totalSpecPages=0; }

/* ------------ Fonctions de rendu partiel ------------ */
function renderSpecTablePartial(array $rows, int $specPage, int $totalSpecPages, string $specBaseHref){
  ob_start(); ?>
  <div class="table-responsive">
    <table class="table-top5">
      <thead><tr><th>Spécialité</th><th class="text-end">Score moyen</th><th class="text-end">Répondants</th></tr></thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="3">Aucune donnée disponible</td></tr>
      <?php else: foreach ($rows as $r):
        $avg = (float)($r['avg']??0);
        $cls = $avg>=80?'score-green':($avg>=51?'score-yellow':'score-orange');
      ?>
        <tr>
          <td><?= htmlspecialchars($r['spec']??'—',ENT_QUOTES,'UTF-8') ?></td>
          <td class="text-end"><span class="badge-score <?= $cls ?>"><?= number_format($avg,1) ?>%</span></td>
          <td class="text-end"><?= (int)($r['n']??0) ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if (!empty($totalSpecPages) && $totalSpecPages > 1):
    $qsSep = (strpos($specBaseHref,'?')!==false && substr($specBaseHref,-1)!=='?') ? '&' : ''; ?>
    <div class="pagination-wrapper">
      <div class="pagination-info">Page <?= $specPage ?> sur <?= $totalSpecPages ?></div>
      <div class="pagination-nav">
        <a href="<?= htmlspecialchars($specBaseHref.$qsSep.'spec_page='.max(1,$specPage-1)) ?>" class="pagination-btn <?= $specPage<=1?'disabled':'' ?>"><i class="fa-solid fa-chevron-left"></i> Précédent</a>
        <?php for($i=max(1,$specPage-2);$i<=min($totalSpecPages,$specPage+2);$i++): ?>
          <a class="pagination-btn <?= $i===$specPage?'current':'' ?>" href="<?= htmlspecialchars($specBaseHref.$qsSep.'spec_page='.$i) ?>"><?= $i ?></a>
        <?php endfor; ?>
        <a href="<?= htmlspecialchars($specBaseHref.$qsSep.'spec_page='.min($totalSpecPages,$specPage+1)) ?>" class="pagination-btn <?= $specPage>=$totalSpecPages?'disabled':'' ?>">Suivant <i class="fa-solid fa-chevron-right"></i></a>
      </div>
    </div>
  <?php endif; ?>
  <?php return ob_get_clean();
}

function renderCandidatesPartial(array $candidates, int $page, int $totalPages, int $offset, int $perPage, int $totalCandidates, string $baseUrl, string $AVATAR_FEMME, string $AVATAR_ENFANT){
  ob_start(); ?>
  <?php if (empty($candidates)): ?>
    <div class="text-muted">Aucun candidat trouvé.</div>
  <?php else: foreach ($candidates as $c):
    $name = trim((string)($c['fullname']??'—')); if($name==='') $name='—';
    $ag   = (string)($c['agency']??'');
    $ctry = (string)($c['country']??'');
    $flag = (string)($c['flagPath']??'');
    $att  = !empty($c['attempted']);
    $pct  = (float)($c['pct']??0);
    $cls  = $att?($pct>=80?'score-green':($pct>=51?'score-yellow':'score-orange')):'score-neutral';
    $g    = strtolower((string)($c['gender']??''));
    $avi  = ($g==='f'||$g==='female'||$g==='femme'||$g==='feminin') ? $AVATAR_FEMME : $AVATAR_ENFANT;
  ?>
    <div class="mini-card">
      <div class="mini-left">
        <div class="mini-avatar"><img src="<?= htmlspecialchars($avi,ENT_QUOTES,'UTF-8') ?>" alt="avatar"></div>
        <div class="mini-text">
          <div class="mini-title"><?= htmlspecialchars($name,ENT_QUOTES,'UTF-8') ?></div>
          <div class="mini-sub">
            <?php if($flag):?><img src="<?= htmlspecialchars($flag,ENT_QUOTES,'UTF-8') ?>" class="flag-img" alt="flag"><?php endif;?>
            <span><?= htmlspecialchars($ctry?:'—',ENT_QUOTES,'UTF-8') ?></span>
            <?= $ag ? '• <i class="fa-solid fa-building"></i> '.htmlspecialchars($ag,ENT_QUOTES,'UTF-8') : '' ?>
          </div>
        </div>
      </div>
      <div><span class="badge-score <?= $cls ?>"><?= $att ? number_format($pct,1).'%' : '—' ?></span></div>
    </div>
  <?php endforeach; endif; ?>
  <?php if ($totalPages > 1): ?>
    <div class="pagination-wrapper">
      <div class="pagination-info">Page <?= $page ?> sur <?= $totalPages ?> (<?= ($offset+1) ?>–<?= min($offset+$perPage,$totalCandidates) ?> sur <?= $totalCandidates ?>)</div>
      <div class="pagination-nav">
        <a href="<?= $baseUrl ?>page=<?= max(1,$page-1) ?>" class="pagination-btn <?= $page<=1?'disabled':'' ?>"><i class="fa-solid fa-chevron-left"></i> Précédent</a>
        <?php for($i=max(1,$page-2);$i<=min($totalPages,$page+2);$i++): ?>
          <a href="<?= $baseUrl ?>page=<?= $i ?>" class="pagination-btn <?= $i===$page?'current':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <a href="<?= $baseUrl ?>page=<?= min($totalPages,$page+1) ?>" class="pagination-btn <?= $page>=$totalPages?'disabled':'' ?>">Suivant <i class="fa-solid fa-chevron-right"></i></a>
      </div>
    </div>
  <?php endif; ?>
  <?php return ob_get_clean();
}

/* ------------ Liste Candidats ------------ */
try {
  $countRes        = $usersCol->aggregate(array_merge([['$match'=>$baseMatch]],$NAME_NORMALIZATION,[['$count'=>'total']]))->toArray();
  $totalCandidates = (int)($countRes[0]['total'] ?? 0);
  $totalPages      = (int)ceil($totalCandidates / $perPage);

  $candPipe = array_merge(
    [['$match'=>$baseMatch]],
    $NAME_NORMALIZATION,
    [
      ['$lookup'=>['from'=>'cfao_subsidiaries','localField'=>'subsidiary','foreignField'=>'subsidiary','as'=>'subinfo']],
      ['$addFields'=>[
        'sub_country'=>['$ifNull'=>[['$arrayElemAt'=>['$subinfo.country',0]],'$country']],
        'flagFromComplete'=>['$arrayElemAt'=>['$subinfo.flagPathComplete',0]],
        'flagFromShort'=>['$arrayElemAt'=>['$subinfo.flagPath',0]],
      ]],
      ['$addFields'=>['flagPath'=>['$cond'=>[
        ['$and'=>[['$ne'=>['$flagFromComplete',null]],['$ne'=>['$flagFromComplete','']]]],
        '$flagFromComplete',
        ['$cond'=>[
          ['$and'=>[['$ne'=>['$flagFromShort',null]],['$ne'=>['$flagFromShort','']]]],
          ['$concat'=>['$flagFromShort','.svg']],
          null
        ]]
      ]]]],
      ['$lookup'=>['from'=>'results','let'=>['uid'=>'$_id'],'pipeline'=>array_values(array_filter([
        ['$match'=>['$expr'=>['$and'=>[['$eq'=>['$user','$$uid']],['$eq'=>['$type','Factuel']],['$eq'=>['$level','Mixed']]]],'userAnswers'=>['$exists'=>true]]],
        $paperOid ? ['$match'=>['paper'=>$paperOid]] : null,
      ])),'as'=>'rf']],
      ['$addFields'=>[
        'sumScore'=>['$reduce'=>['input'=>'$rf','initialValue'=>0,'in'=>['$add'=>['$$value',['$ifNull'=>['$$this.score',0]]]]]],
        'sumTotal'=>['$reduce'=>['input'=>'$rf','initialValue'=>0,'in'=>['$add'=>['$$value',['$ifNull'=>['$$this.total',0]]]]]],
        'attempted'=>['$gt'=>[['$size'=>'$rf'],0]],
      ]],
      ['$addFields'=>['pct'=>['$cond'=>[['$gt'=>['$sumTotal',0]],['$multiply'=>[['$divide'=>['$sumScore','$sumTotal']],100]],0]]]],
      ['$project'=>['_id'=>0,'fullname'=>1,'gender'=>['$ifNull'=>['$gender','']],'agency'=>['$ifNull'=>['$agency','']],'country'=>['$ifNull'=>['$sub_country',['$ifNull'=>['$country','']]]],'flagPath'=>1,'attempted'=>1,'pct'=>['$round'=>['$pct',1]]]],
      ['$sort'=>['attempted'=>-1,'fullname'=>1]],
      ['$skip'=>$offset],['$limit'=>$perPage],
    ]
  );
  $candidates = $usersCol->aggregate($candPipe)->toArray();
} catch(Throwable $e){ $candidates=[]; $totalCandidates=0; $totalPages=0; }

/* ---------- Partials ---------- */
if (isset($_GET['partial']) && $_GET['partial'] === 'specs') {
  echo renderSpecTablePartial($rows,(int)$specPage,(int)$totalSpecPages,$specBaseHref);
  exit;
}
if (isset($_GET['partial']) && $_GET['partial'] === 'candidates') {
  $p = $_GET; unset($p['page'],$p['partial']);
  $baseUrl = $_SERVER['PHP_SELF'].'?'.(http_build_query($p)?http_build_query($p).'&':'');
  echo renderCandidatesPartial($candidates,(int)$page,(int)$totalPages,(int)$offset,(int)$perPage,(int)$totalCandidates,$baseUrl,$AVATAR_FEMME,$AVATAR_ENFANT);
  exit;
}

/* ---------- Helper URL ---------- */
function buildUrl(array $base, array $overrides = []) {
  $p = array_merge($base, $overrides);
  foreach ($p as $k=>$v) if ($v===null||$v==='') unset($p[$k]);
  $qs = http_build_query($p);
  return $_SERVER['PHP_SELF'].($qs ? '?'.$qs : '');
}

include "./partials/header.php";
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer"/>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
<title>Tableau de bord RH – Campagne Technicien 2025 | CFAO Mobility Academy</title>

<style>
:root{
  --brand:#0ea5e9; --text-dark:#0f172a; --muted:#64748b;
  --surface:#ffffffcc; --glass-bg:rgba(255,255,255,.80);
  --glass-brd:rgba(15,23,42,.08); --radius:16px;
  --shadow:0 8px 24px rgba(15,23,42,.10);
  --score-green:#6CF95D; --score-yellow:#FAF75A; --score-orange:#FB9258;
}

/* ── Campaign selector dans le titre ── */
.campaign-select-wrap {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  background: rgba(99,102,241,.08);
  border: 1.5px solid rgba(99,102,241,.22);
  border-radius: 999px;
  padding: 6px 10px 6px 14px;
  margin-top: 14px;
}
.campaign-select-wrap .csl-icon {
  color: #6366f1;
  font-size: 15px;
  flex-shrink: 0;
}
.campaign-select-wrap select {
  border: none;
  background: transparent;
  font-weight: 700;
  font-size: 15px;
  color: #3730a3;
  cursor: pointer;
  outline: none;
  appearance: none;
  -webkit-appearance: none;
  padding-right: 22px;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236366f1' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 0 center;
  max-width: 420px;
}
.campaign-select-wrap select:focus { box-shadow: none; }
.campaign-select-wrap .csl-count {
  font-size: 12px;
  background: #6366f1;
  color: #fff;
  border-radius: 999px;
  padding: 2px 9px;
  font-weight: 800;
  white-space: nowrap;
  flex-shrink: 0;
}

/* Pagination */
.pagination-wrapper{display:flex;justify-content:space-between;align-items:center;padding:1rem 0;margin-top:1rem;border-top:1px solid rgba(15,23,42,.06)}
.pagination-info{font-size:13px;color:#64748b}
.pagination-nav{display:flex;gap:.5rem}
.pagination-btn{padding:.5rem 1rem;border:1px solid rgba(15,23,42,.12);background:white;color:#0f172a;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;transition:all .2s ease;display:flex;align-items:center;gap:.25rem}
.pagination-btn:hover:not(.disabled){background:#f8fafc;border-color:rgba(15,23,42,.18);transform:translateY(-1px);color:#0f172a;text-decoration:none}
.pagination-btn.current{background:#0ea5e9;color:white;border-color:#0ea5e9}
.pagination-btn.disabled{opacity:.4;cursor:not-allowed;pointer-events:none}

/* Layout */
.row-progress{--left:1.35fr;--right:0.65fr}
@media(min-width:992px){
  .row-progress{display:grid;grid-template-columns:var(--left) var(--right);gap:1.5rem}
  .row-progress>[class*="col-"]{width:auto!important;max-width:none!important;flex:0 1 auto!important;padding:0}
  .row-progress .donut-wrap{height:420px}
  .row-progress .donut-wrap canvas{width:100%!important;height:100%!important}
}

/* Cards */
body,.card,.table,.kpi-title,.kpi-sub{font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif}
.glass-effect-card,.glass-effect-chart{background:var(--glass-bg)!important;backdrop-filter:blur(14px)!important;-webkit-backdrop-filter:blur(14px)!important;border:1px solid var(--glass-brd)!important;border-radius:var(--radius)!important;box-shadow:var(--shadow)!important;overflow:hidden}
.has-white-header .card-header{background:#fff!important;border-bottom:1px solid rgba(15,23,42,.06)!important}
.depth-effect:hover{transform:translateY(-2px);transition:transform .25s ease}

/* KPI */
.kpi-card .card-body{padding:22px}
.kpi-title{font-size:13px;letter-spacing:.4px;color:black;font-weight:700;margin-bottom:6px}
.kpi-value{font-size:34px;font-weight:900;color:black;line-height:1.1;font-variant-numeric:tabular-nums}
.kpi-value.kpi-perc{background:transparent!important;box-shadow:none!important;padding:0!important;border-radius:0!important;display:inline-block;position:relative}
.kpi-value.kpi-perc.score-green{color:#16a34a}
.kpi-value.kpi-perc.score-yellow{color:#a16207}
.kpi-value.kpi-perc.score-orange{color:#c2410c}
.kpi-value.kpi-perc::after{content:"";display:block;width:68px;height:4px;border-radius:999px;margin:8px auto 0;background:currentColor;opacity:.22}
.kpi-sub{color:#64748b;font-size:12px;margin-top:6px}
.kpi-card.glass-effect-card{background:linear-gradient(180deg,rgba(255,255,255,.95),rgba(255,255,255,.86))!important;border:1px solid rgba(15,23,42,.07)!important;box-shadow:0 10px 28px rgba(15,23,42,.10)!important;border-radius:16px!important}

/* Tableau */
.table-top5{width:100%;border-collapse:separate;border-spacing:0;border-radius:14px;overflow:hidden;box-shadow:0 4px 18px rgba(0,0,0,.06)}
.table-top5 thead{background:linear-gradient(90deg,#eef2ff,#e0f2fe);color:#0f172a;font-weight:800;text-transform:uppercase;font-size:12px;letter-spacing:.5px}
.table-top5 th,.table-top5 td{padding:12px 16px}
.table-top5 tbody tr{background:rgba(255,255,255,.9);transition:all .2s ease;border-bottom:1px solid rgba(15,23,42,.04)}
.table-top5 tbody tr:hover{background:#f0fdf4}
.table-top5 td:first-child{font-weight:700;color:#0f172a}
.table-top5 td:last-child{text-align:right}

/* Performers */
.kpi-performer{border-radius:16px;padding:20px;text-align:center;color:#0f172a;transition:transform .2s ease,box-shadow .2s ease}
.kpi-performer:hover{transform:translateY(-4px);box-shadow:0 10px 24px rgba(0,0,0,.10)}
.kpi-performer .title{font-size:14px;font-weight:700;letter-spacing:.3px;margin-bottom:8px;display:flex;align-items:center;justify-content:center;gap:6px}
.kpi-performer .name{font-size:20px;font-weight:900;margin-bottom:6px}
.kpi-best{background:linear-gradient(135deg,#ecfdf5,#d1fae5)}.kpi-best .title{color:#065f46}
.kpi-worst{background:linear-gradient(135deg,#fef2f2,#fee2e2)}.kpi-worst .title{color:#991b1b}
.kpi-spec{background:linear-gradient(135deg,#f3f4f6,#e5e7eb)}.kpi-spec .title{color:#4b5563}

/* Badges */
.badge-score{display:inline-block;min-width:74px;text-align:center;font-weight:900;padding:.45rem .7rem;border-radius:12px;letter-spacing:.2px;border:1px solid rgba(15,23,42,.06)}
.score-green{background:var(--score-green);color:#064e3b}
.score-yellow{background:var(--score-yellow);color:#7a6100}
.score-orange{background:var(--score-orange);color:#6b2c0c}
.score-neutral{background:#e5e7eb;color:#374151}

/* Mini-cards */
.mini-card{border-radius:14px;padding:12px 14px;margin-bottom:12px;display:flex;align-items:center;justify-content:space-between;gap:12px;border:1px solid rgba(15,23,42,.06);background:var(--surface);transition:transform .2s ease,box-shadow .2s ease}
.mini-card:hover{transform:translateY(-2px);box-shadow:0 8px 18px rgba(0,0,0,.08)}
.mini-left{display:flex;gap:12px;align-items:center;min-width:0}
.mini-avatar{width:44px;height:44px;border-radius:12px;overflow:hidden;flex:0 0 44px;background:#eef2ff;box-shadow:inset 0 0 0 2px rgba(30,64,175,.12)}
.mini-avatar img{width:100%;height:100%;object-fit:cover;display:block}
.mini-title{font-weight:800;color:var(--text-dark);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.mini-sub{font-size:12px;color:#475569;display:flex;align-items:center;gap:8px}
.flag-img{width:22px;height:16px;object-fit:cover;border-radius:3px;box-shadow:0 0 0 1px rgba(0,0,0,.15)}
</style>

<?php openBackgroundContainer(); ?>
<div class="container-xxl campaign-wrap">

  <!-- ══════════════════════════════════════════════════
       TITRE + SÉLECTEUR DE CAMPAGNE (intégré dans la card)
  ══════════════════════════════════════════════════ -->
  <div class="card shadow-sm mb-5 glass-effect-card">
    <div class="card-body p-4 text-center" style="background-color:white;">
      <h1 class="text-dark fw-bold fs-1 mb-1">
        <i class="fa-solid fa-gauge-high me-2"></i>Tableau de bord RH
      </h1>
      <div class="text-muted mb-2" style="font-size:15px">Résultats QCM factuel — par campagne de recrutement</div>

      <!-- Dropdown campagne -->
      <?php if (!empty($availableCampaigns)): ?>
      <form method="get" id="campaignForm" style="display:inline-block">
        <?php
          // Préserver les autres filtres actifs
          foreach (['country','agency','specialty','paper','page','spec_page'] as $k) {
            if (!empty($_GET[$k])) echo '<input type="hidden" name="'.htmlspecialchars($k,ENT_QUOTES,'UTF-8').'" value="'.htmlspecialchars($_GET[$k],ENT_QUOTES,'UTF-8').'">';
          }
        ?>
        <div class="campaign-select-wrap">
          <i class="fa-solid fa-flag csl-icon"></i>
          <select name="campaign" onchange="document.getElementById('campaignForm').submit()">
            <option value="">— Toutes les campagnes —</option>
            <?php foreach ($availableCampaigns as $camp): ?>
              <option value="<?= htmlspecialchars($camp,ENT_QUOTES,'UTF-8') ?>"
                <?= ($selectedCampaign === $camp) ? 'selected' : '' ?>>
                <?= htmlspecialchars($camp,ENT_QUOTES,'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span class="csl-count"><?= (int)($kpis['total']??0) ?> candidat<?= ((int)($kpis['total']??0))>1?'s':'' ?></span>
        </div>
      </form>
      <?php else: ?>
        <span class="text-muted fst-italic">Aucune campagne disponible</span>
      <?php endif; ?>
    </div>
  </div>
  <!-- ══════════════════════════════════════════════════ -->

  <!-- KPI -->
  <?php
    $completion    = (float)($kpis['completion'] ?? 0);
    $avgPctVal     = (float)($kpis['avgPct']     ?? 0);
    $completionCls = scoreClass($completion, true);
    $avgPctCls     = scoreClass($avgPctVal,  true);
  ?>
  <div class="row g-4 mb-5">
    <div class="col-md-6 col-lg-3">
      <div class="card h-100 glass-effect-card depth-effect kpi-card">
        <div class="card-body text-center">
          <div class="kpi-title"><i class="fa-solid fa-users me-2"></i>Total candidats</div>
          <div class="kpi-value"><?= (int)$kpis['total'] ?></div>
          <div class="kpi-sub"><?= htmlspecialchars($CAMPAIGN_ROLE ?: '—',ENT_QUOTES,'UTF-8') ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-6 col-lg-3">
      <div class="card h-100 glass-effect-card depth-effect kpi-card">
        <div class="card-body text-center">
          <div class="kpi-title"><i class="fa-solid fa-list-check me-2"></i>Candidats testés</div>
          <div class="kpi-value"><?= (int)$kpis['tested'] ?></div>
          <div class="kpi-sub">Au moins un QCM factuel</div>
        </div>
      </div>
    </div>
    <div class="col-md-6 col-lg-3">
      <div class="card h-100 glass-effect-card depth-effect kpi-card">
        <div class="card-body text-center">
          <div class="kpi-title"><i class="fa-solid fa-arrow-trend-up me-2"></i>Avancement des tests</div>
          <div class="kpi-value kpi-perc <?= $completionCls ?>"><?= number_format($completion,1) ?>%</div>
          <div class="kpi-sub"><?= (int)$kpis['tested'] ?> / <?= (int)$kpis['total'] ?> candidats</div>
        </div>
      </div>
    </div>
    <div class="col-md-6 col-lg-3">
      <div class="card h-100 glass-effect-card depth-effect kpi-card">
        <div class="card-body text-center">
          <div class="kpi-title"><i class="fa-solid fa-percent me-2"></i>Moyenne de performance</div>
          <div class="kpi-value kpi-perc <?= $avgPctCls ?>"><?= number_format($avgPctVal,1) ?>%</div>
          <div class="kpi-sub">Score moyen QCM factuel</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Donut + Best/Worst/Spec -->
  <div class="row g-4 mb-5 row-progress">
    <div class="col-lg-7">
      <div class="card glass-effect-chart depth-effect has-white-header">
        <div class="card-header border-0 pb-0 bg-white" style="border-radius:16px 16px 0 0;">
          <div class="p-3 fw-bold"><i class="fa-solid fa-chart-pie me-2"></i>Avancement — Testés vs Restants</div>
        </div>
        <div class="card-body p-4"><div class="donut-wrap"><canvas id="donutProgress"></canvas></div></div>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="row g-4">
        <div class="col-12">
          <div class="kpi-performer kpi-best">
            <div class="title"><i class="fa-solid fa-medal"></i> Meilleur performeur</div>
            <div class="name"><?= htmlspecialchars($best['fullname']??'—',ENT_QUOTES,'UTF-8') ?></div>
            <?php $bpct=(float)($best['pct']??0); ?>
            <div class="badge-score <?= scoreClass($bpct,!empty($bpct)) ?>"><?= number_format($bpct,1) ?>%</div>
          </div>
        </div>
        <div class="col-12">
          <div class="kpi-performer kpi-worst">
            <div class="title"><i class="fa-solid fa-skull-crossbones"></i> Pire performeur</div>
            <div class="name"><?= htmlspecialchars($worst['fullname']??'—',ENT_QUOTES,'UTF-8') ?></div>
            <?php $wpct=(float)($worst['pct']??0); ?>
            <div class="badge-score <?= scoreClass($wpct,!empty($wpct)) ?>"><?= number_format($wpct,1) ?>%</div>
          </div>
        </div>
        <div class="col-12">
          <div class="kpi-performer kpi-spec">
            <div class="title"><i class="fa-solid fa-screwdriver-wrench"></i> Pire spécialité (moy.)</div>
            <div class="name"><?= htmlspecialchars($worstSpec['spec']??'—',ENT_QUOTES,'UTF-8') ?></div>
            <?php $spct=(float)($worstSpec['avg']??0); ?>
            <div class="badge-score <?= scoreClass($spct,!empty($worstSpec['n']??0)) ?>"><?= number_format($spct,1) ?>% • N=<?= (int)($worstSpec['n']??0) ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Top5 + Spécialités -->
  <div class="row g-4 mb-5">
    <div class="col-lg-7">
      <div class="card glass-effect-chart depth-effect has-white-header">
        <div class="card-header border-0 pb-0 bg-white" style="border-radius:16px 16px 0 0;">
          <div class="p-3 fw-bold"><i class="fa-solid fa-trophy me-2"></i>Top 5 performeurs</div>
        </div>
        <div class="card-body p-4"><canvas id="barTopN" height="149"></canvas></div>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="card glass-effect-chart depth-effect has-white-header">
        <div class="card-header border-0 pb-0 bg-white" style="border-radius:16px 16px 0 0;">
          <div class="p-3 fw-bold"><i class="fa-solid fa-screwdriver-wrench me-2"></i>Spécialités — Taux de réussite</div>
        </div>
        <div class="card-body p-4">
          <div id="specCard"><?= renderSpecTablePartial($rows,(int)$specPage,(int)$totalSpecPages,$specBaseHref) ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Détail Top5 + Candidats -->
  <div class="row g-4">
    <div class="col-lg-7">
      <div class="card glass-effect-card depth-effect has-white-header">
        <div class="card-header border-0 pb-0 bg-white" style="border-radius:16px 16px 0 0;">
          <div class="p-3 fw-bold"><i class="fa-solid fa-list-ol me-2"></i>Détail Top 5</div>
        </div>
        <div class="card-body p-4">
          <table class="table-top5">
            <thead><tr><th>Nom</th><th class="text-end">Score</th></tr></thead>
            <tbody>
            <?php if(empty($topN)): ?>
              <tr><td colspan="2">—</td></tr>
            <?php else: foreach($topN as $t):
              $n=trim((string)($t['fullname']??'')); if($n==='') $n='—';
              $p=(float)($t['pct']??0);
              $c=$p>=80?'score-green':($p>=51?'score-yellow':'score-orange');
            ?>
              <tr>
                <td><i class="fa-solid fa-user me-2 text-muted"></i><?= htmlspecialchars($n,ENT_QUOTES,'UTF-8') ?></td>
                <td><span class="badge-score <?= $c ?>"><?= number_format($p,1) ?>%</span></td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="card glass-effect-card depth-effect has-white-header">
        <div class="card-header border-0 pb-0 bg-white" style="border-radius:16px 16px 0 0;">
          <div class="p-3 fw-bold">
            <i class="fa-solid fa-people-group me-2"></i>Candidats (campagne)
            <span class="badge bg-light text-dark ms-2"><?= $totalCandidates ?> total</span>
          </div>
        </div>
        <div class="card-body p-4">
          <?php
            $p2 = $_GET; unset($p2['page'],$p2['partial']);
            $baseUrl = $_SERVER['PHP_SELF'].'?'.(http_build_query($p2)?http_build_query($p2).'&':'');
          ?>
          <div id="candidatesCard">
            <?= renderCandidatesPartial($candidates,(int)$page,(int)$totalPages,(int)$offset,(int)$perPage,(int)$totalCandidates,$baseUrl,$AVATAR_FEMME,$AVATAR_ENFANT) ?>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>
<?php closeBackgroundContainer(); ?>
<?php include "./partials/footer.php"; ?>

<!-- Charts -->
<script>
(function(){
  Chart.defaults.font.family='Inter,system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif';
  Chart.defaults.color='#0f172a';

  const tested=<?= (int)$kpis['tested'] ?>, notTested=<?= (int)max(0,($kpis['total']??0)-($kpis['tested']??0)) ?>;
  const total=tested+notTested, pctTested=total>0?Math.round((tested/total)*100):0;

  new Chart(document.getElementById('donutProgress'),{
    type:'doughnut',
    data:{
      labels:total===0?['Aucun candidat']:['Testés','Restants'],
      datasets:[{
        data:total===0?[1]:[tested,notTested],
        backgroundColor:total===0?['#9ca3af']:['#22c55e','#e5e7eb'],
        borderColor:'#fff',borderWidth:3,hoverOffset:8,cutout:'75%'
      }]
    },
    options:{
      responsive:true,maintainAspectRatio:false,
      plugins:{
        legend:{position:'bottom',labels:{usePointStyle:true,pointStyle:'circle',padding:20,font:{size:13,weight:'600'}}},
        tooltip:{callbacks:{label:(ctx)=>total===0?'—':ctx.label+' : '+ctx.parsed+' • '+Math.round((ctx.parsed/total)*100)+'%'}}
      },
      elements:{arc:{borderRadius:12}}
    },
    plugins:[{id:'donutText',afterDraw:(chart)=>{
      const {ctx}=chart, m=chart.getDatasetMeta(0);
      if(!m||!m.data||!m.data[0])return;
      const {x,y}=m.data[0];
      ctx.save();ctx.font='bold 22px Inter,system-ui,sans-serif';ctx.fillStyle='#0f172a';
      ctx.textAlign='center';ctx.textBaseline='middle';
      ctx.fillText(pctTested+'%',x,y);
    }}]
  });

  const topNames=<?= json_encode($topNames,JSON_UNESCAPED_UNICODE) ?>;
  const topPct=<?= json_encode($topPct) ?>;
  if(topNames.length){
    new Chart(document.getElementById('barTopN'),{
      type:'bar',
      data:{labels:topNames,datasets:[{data:topPct,backgroundColor:topPct.map(v=>v>=80?'#22c55e':(v>=51?'#facc15':'#fb923c')),label:'Score (%)'}]},
      options:{responsive:true,scales:{y:{beginAtZero:true,max:100,ticks:{callback:v=>v+'%'}}},plugins:{legend:{display:false},tooltip:{callbacks:{label:ctx=>(ctx.parsed.y??ctx.parsed)+'%'}}}}
    });
  } else {
    document.getElementById('barTopN').insertAdjacentHTML('afterend','<div class="text-center text-muted py-3">Aucun résultat à afficher</div>');
  }
})();
</script>

<!-- AJAX pagination spécialités -->
<script>
(function(){
  const w=document.getElementById('specCard');
  if(!w)return;
  document.addEventListener('click',function(e){
    const a=e.target.closest('#specCard a.pagination-btn');
    if(!a||a.classList.contains('disabled'))return;
    e.preventDefault();
    const url=new URL(a.getAttribute('href'),window.location.href);
    url.searchParams.set('partial','specs');
    fetch(url,{headers:{'X-Requested-With':'fetch'}}).then(r=>r.text()).then(html=>{
      w.innerHTML=html;
      const np=(new URL(a.href,window.location.href)).searchParams.get('spec_page');
      const fu=new URL(window.location.href);
      np?fu.searchParams.set('spec_page',np):fu.searchParams.delete('spec_page');
      history.replaceState(null,'',fu);
    }).catch(console.error);
  });
})();
</script>

<!-- AJAX pagination candidats -->
<script>
(function(){
  const w=document.getElementById('candidatesCard');
  if(!w)return;
  document.addEventListener('click',function(e){
    const a=e.target.closest('#candidatesCard a.pagination-btn');
    if(!a||a.classList.contains('disabled'))return;
    e.preventDefault();
    const url=new URL(a.getAttribute('href'),window.location.href);
    url.searchParams.set('partial','candidates');
    fetch(url,{headers:{'X-Requested-With':'fetch'}}).then(r=>r.text()).then(html=>{
      w.innerHTML=html;
      const np=(new URL(a.href,window.location.href)).searchParams.get('page');
      const fu=new URL(window.location.href);
      np?fu.searchParams.set('page',np):fu.searchParams.delete('page');
      history.replaceState(null,'',fu);
    }).catch(console.error);
  });
})();
</script>