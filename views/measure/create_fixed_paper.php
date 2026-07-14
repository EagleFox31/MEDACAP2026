<?php
/**
 * CFAO Mobility Academy — Générateur d'Épreuve Figée
 * (toute la logique PHP est intacte)
 */
session_start();
if (!isset($_SESSION["id"])) { header("Location: ../../"); exit(); }
require_once "../../vendor/autoload.php";
include_once "../partials/background-manager.php";
setPageBackground("bg-dashboard");
use MongoDB\Client; use MongoDB\BSON\ObjectId; use MongoDB\Model\BSONArray;

$mongo=new Client("mongodb://localhost:27017"); $db=$mongo->academy;
$users=$db->users; $vehicles=$db->vehicles; $quizzes=$db->quizzes;
$questions=$db->questions; $papers=$db->papers; $orientCol=$db->orientation_questions;
const CP_SPEC='Carrosserie & Peinture';

function s($v){if(!is_string($v))return $v;return trim(filter_var($v,FILTER_SANITIZE_STRING));}
function arr_str(array $a):array{$o=[];foreach($a as $v){$v=s($v);if($v!=='')$o[]=$v;}return array_values(array_unique($o));}
function toOid($v):?ObjectId{if($v instanceof ObjectId)return $v;if(is_string($v)&&preg_match('/^[0-9a-f]{24}$/i',$v))return new ObjectId($v);return null;}
function dedupOids(array $a):array{$m=[];$o=[];foreach($a as $x){if(!($x instanceof ObjectId))continue;$k=(string)$x;if(isset($m[$k]))continue;$m[$k]=1;$o[]=$x;}return $o;}
function pickRandom(array $arr,int $n):array{if($n<=0||empty($arr))return[];shuffle($arr);return array_slice($arr,0,min($n,count($arr)));}
function poolBrandQuizzes(array $brands):array{global $vehicles,$quizzes;if(empty($brands))return[];$quizIdMap=[];$cur=$vehicles->find(['brand'=>['$in'=>$brands],'type'=>'Factuel','active'=>true],['projection'=>['quizzes'=>1]]);foreach($cur as $veh){$raw=$veh['quizzes']??[];if($raw instanceof BSONArray)$raw=$raw->getArrayCopy();if(!is_array($raw))continue;foreach($raw as $id){$oid=toOid(is_string($id)?$id:($id instanceof ObjectId?(string)$id:null));if($oid)$quizIdMap[(string)$oid]=$oid;}}if(empty($quizIdMap))return[];$docs=[];$qCur=$quizzes->find(['_id'=>['$in'=>array_values($quizIdMap)],'active'=>true,'type'=>['$in'=>['Factuel','Factuelle']]],['projection'=>['_id'=>1,'speciality'=>1,'total'=>1,'questions'=>1,'level'=>1]]);foreach($qCur as $q)$docs[]=$q;return $docs;}
function poolCpQuizzes():array{global $quizzes;$out=[];$cursor=$quizzes->find(['active'=>true,'type'=>['$in'=>['Factuel','Factuelle']],'speciality'=>CP_SPEC],['projection'=>['_id'=>1,'speciality'=>1,'total'=>1,'questions'=>1,'level'=>1]]);foreach($cursor as $q){$out[]=$q;}return $out;}
function quizStockSize($quizDoc):int{$t=(int)($quizDoc['total']??0);if($t>0)return $t;$raw=$quizDoc['questions']??[];if($raw instanceof BSONArray)$raw=$raw->getArrayCopy();if(!is_array($raw))return 0;return count($raw);}
function distributeByStock(array $quizDocs,int $target):array{$stock=0;$ids=[];foreach($quizDocs as $q){$t=quizStockSize($q);$stock+=$t;$ids[(string)$q['_id']]=$t;}if($stock<=0||$target<=0)return[];$alloc=[];$frac=[];foreach($ids as $k=>$t){$r=($t/$stock)*$target;$alloc[$k]=(int)floor($r);$frac[$k]=$r-$alloc[$k];}$given=array_sum($alloc);$rem=$target-$given;if($rem>0){arsort($frac,SORT_NUMERIC);foreach(array_keys($frac)as $k){if($rem<=0)break;$alloc[$k]++;$rem--;}}return $alloc;}
function sampleQuestionsOfQuiz($quizDoc,int $n):array{global $questions;if($n<=0)return[];$raw=$quizDoc['questions']??[];if($raw instanceof BSONArray)$raw=$raw->getArrayCopy();if(!is_array($raw)||empty($raw))return[];$ids=[];foreach($raw as $x){$oid=toOid(is_string($x)?$x:($x instanceof ObjectId?(string)$x:null));if($oid)$ids[(string)$oid]=$oid;}if(empty($ids))return[];$active=[];$cur=$questions->find(['_id'=>['$in'=>array_values($ids)],'active'=>true],['projection'=>['_id'=>1]]);foreach($cur as $qd)$active[]=$qd['_id'];if(empty($active))return[];return pickRandom($active,$n);}
function buildFixedPaper(string $label,array $brands,int $qcmCount,array $ratio,array $orientationOids=[],bool $includeCp=true,int $durationHour=1,int $durationMinute=0):ObjectId{global $papers;if($includeCp){$cpTarget=(int)round($qcmCount*0.10);if($qcmCount>0&&$cpTarget===0)$cpTarget=1;}else{$cpTarget=0;}$brandTarget=max(0,$qcmCount-$cpTarget);$brandPool=poolBrandQuizzes($brands);$cpPool=$includeCp?poolCpQuizzes():[];$brandAlloc=distributeByStock($brandPool,$brandTarget);$cpAlloc=$includeCp?distributeByStock($cpPool,$cpTarget):[];$pickedQ=[];$sourceQuizIds=[];$specQuizMap=[];$useQuiz=function($q)use(&$sourceQuizIds,&$specQuizMap){$qid=(string)$q['_id'];$sourceQuizIds[$qid]=$q['_id'];$spec=(string)($q['speciality']??'');if($spec!==''&&!isset($specQuizMap[$spec]))$specQuizMap[$spec]=$q['_id'];};$byIdDoc=[];foreach($brandPool as $q){$byIdDoc[(string)$q['_id']]=$q;}foreach($cpPool as $q){$byIdDoc[(string)$q['_id']]=$q;}foreach($brandAlloc as $qid=>$take){if($take<=0||!isset($byIdDoc[$qid]))continue;$doc=$byIdDoc[$qid];$useQuiz($doc);$pickedQ=array_merge($pickedQ,sampleQuestionsOfQuiz($doc,$take));}foreach($cpAlloc as $qid=>$take){if($take<=0||!isset($byIdDoc[$qid]))continue;$doc=$byIdDoc[$qid];$useQuiz($doc);$pickedQ=array_merge($pickedQ,sampleQuestionsOfQuiz($doc,$take));}$need=$qcmCount-count($pickedQ);if($need>0){$allActive=[];foreach($byIdDoc as $doc){$allActive=array_merge($allActive,sampleQuestionsOfQuiz($doc,999999));}$seen=[];foreach($pickedQ as $x){$seen[(string)$x]=1;}$rest=[];foreach($allActive as $x){$k=(string)$x;if(!isset($seen[$k]))$rest[]=$x;}shuffle($rest);$pickedQ=array_merge($pickedQ,array_slice($rest,0,$need));}if(count($pickedQ)>$qcmCount)$pickedQ=array_slice($pickedQ,0,$qcmCount);shuffle($pickedQ);$doc=['label'=>$label,'type'=>'Factuel','mode'=>'mixed','level'=>'Mixed','brand'=>array_values(array_unique($brands)),'ratios'=>['Junior'=>(int)($ratio['Junior']??50),'Senior'=>(int)($ratio['Senior']??45),'Expert'=>(int)($ratio['Expert']??5)],'counts'=>['QCM_total'=>$qcmCount,'QCM_brand'=>array_sum($brandAlloc),'QCM_cp'=>array_sum($cpAlloc)],'source_quizzes'=>array_values($sourceQuizIds),'specQuizMap'=>$specQuizMap,'questions'=>$pickedQ,'orientation'=>dedupOids($orientationOids),'duration'=>['hour'=>$durationHour,'minute'=>$durationMinute,'second'=>0],'hour'=>$durationHour,'minute'=>$durationMinute,'second'=>0,'active'=>true,'created'=>date('Y-m-d H:i:s')];$res=$papers->insertOne($doc);return $res->getInsertedId();}

$orientationOptions=[];
try{$oc=$orientCol->find(['active'=>true,'scope'=>'global'],['projection'=>['_id'=>1,'title'=>1],'sort'=>['title'=>1]]);foreach($oc as $q)$orientationOptions[]=['id'=>(string)$q->_id,'title'=>(string)$q['title']];}catch(\Throwable $e){}
$success=null;$error=null;$createdPaperId=null;
if($_SERVER['REQUEST_METHOD']==='POST'){try{$label=s($_POST['label']??'');$brands=isset($_POST['brand'])?arr_str((array)$_POST['brand']):[];$qcmCount=max(1,(int)($_POST['qcm_count']??50));$ratio=['Junior'=>(int)($_POST['ratio_junior']??50),'Senior'=>(int)($_POST['ratio_senior']??45),'Expert'=>(int)($_POST['ratio_expert']??5)];$durationHour=max(0,min(8,(int)($_POST['duration_hour']??1)));$durationMinute=max(0,min(59,(int)($_POST['duration_minute']??0)));if($durationHour===0&&$durationMinute===0)throw new \RuntimeException("La durée doit être supérieure à 0 minute.");$orientationIds=[];foreach((array)($_POST['orientation_selected']??[])as $id){$oid=toOid($id);if($oid)$orientationIds[]=$oid;}$includeCp=isset($_POST['include_cp']);if($label===''||empty($brands))throw new \RuntimeException("Le label et au moins une marque sont requis.");$paperId=buildFixedPaper($label,$brands,$qcmCount,$ratio,$orientationIds,$includeCp,$durationHour,$durationMinute);$createdPaperId=(string)$paperId;$success="Épreuve figée créée avec succès";}catch(\Throwable $e){$error="Erreur : ".$e->getMessage();}}
include_once "partials/header.php";
openBackgroundContainer();
?>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
:root{
  --card:#ffffffb3; --card-border:rgba(255,255,255,.85); --hdr:#ffffffeb;
  --shadow:0 2px 16px rgba(0,0,0,.07),0 1px 3px rgba(0,0,0,.04);
  --text:#1a1f36; --sub:#6b7280; --light:#9ca3af; --border:rgba(0,0,0,.07);
  --accent:#5b50e8; --acc-lt:#ede9fd; --acc-md:#8b83f0;
  --jr:#f59e0b; --jr-lt:#fef3c7; --sr:#5b50e8; --sr-lt:#ede9fd;
  --ex:#10b981; --ex-lt:#d1fae5; --ok:#10b981; --ok-lt:#ecfdf5;
  --err:#ef4444; --err-lt:#fef2f2; --r:14px; --rs:9px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
.cfp-page{font-family:'Inter',system-ui,sans-serif;padding:24px 18px 80px;}
.shell{max-width:960px;margin:0 auto;}

.pg-head{display:flex;align-items:center;gap:12px;padding:14px 20px;background:var(--hdr);backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px);border:1px solid var(--card-border);border-radius:var(--r);box-shadow:var(--shadow);margin-bottom:16px;}
.pg-ico{width:40px;height:40px;background:var(--acc-lt);border-radius:9px;display:grid;place-items:center;flex-shrink:0;}
.pg-ico svg{width:18px;height:18px;stroke:var(--accent);fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
.pg-head h1{font-size:.82rem;font-weight:700;color:var(--text);text-transform:uppercase;letter-spacing:.05em;}
.pg-head p{font-size:.72rem;color:var(--sub);margin-top:2px;}

.card{background:var(--card);backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px);border:1px solid var(--card-border);border-radius:var(--r);box-shadow:var(--shadow);margin-bottom:14px;overflow:hidden;}
.ch{display:flex;align-items:center;gap:10px;padding:13px 18px;background:var(--hdr);border-bottom:1px solid var(--border);}
.ci{width:30px;height:30px;background:var(--acc-lt);border-radius:7px;display:grid;place-items:center;flex-shrink:0;}
.ci svg{width:15px;height:15px;stroke:var(--accent);fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
.ch h2{font-size:.82rem;font-weight:700;color:var(--text);}
.ch p{font-size:.71rem;color:var(--sub);margin-top:1px;}
.badge{margin-left:auto;padding:3px 10px;background:var(--acc-lt);color:var(--accent);border-radius:100px;font-size:.7rem;font-weight:700;flex-shrink:0;}
.cb{padding:18px;}
.field{margin-bottom:14px;}.field:last-child{margin-bottom:0;}
.field>label{display:block;font-size:.69rem;font-weight:600;color:var(--sub);text-transform:uppercase;letter-spacing:.07em;margin-bottom:5px;}
.req{color:var(--accent);}
input[type="text"],input[type="number"]{width:100%;padding:9px 12px;background:#fff;border:1px solid var(--border);border-radius:var(--rs);color:var(--text);font-family:'Inter',sans-serif;font-size:.85rem;transition:border-color .15s,box-shadow .15s;outline:none;}
input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(91,80,232,.1);}
input::placeholder{color:var(--light);}
.hint{font-size:.7rem;color:var(--light);margin-top:4px;}
.r2{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.r3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;}
@media(max-width:560px){.r2,.r3{grid-template-columns:1fr;}}

.bctrl{display:flex;align-items:center;gap:7px;margin-bottom:10px;}
.ghost{padding:5px 11px;background:#fff;border:1px solid var(--border);border-radius:6px;color:var(--sub);font-size:.72rem;font-weight:500;cursor:pointer;transition:all .13s;}
.ghost:hover{border-color:var(--accent);color:var(--accent);background:var(--acc-lt);}
.clbl{font-size:.67rem;text-transform:uppercase;letter-spacing:.1em;font-weight:700;color:var(--light);margin-bottom:7px;display:flex;align-items:center;gap:7px;}
.clbl::after{content:'';flex:1;height:1px;background:var(--border);}
.bgrid{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:13px;}
.bgrid:last-child{margin-bottom:0;}
.bc{position:relative;}
.bc input{position:absolute;opacity:0;width:0;height:0;}
.bc label{display:flex;align-items:center;gap:4px;padding:5px 12px;background:#fff;border:1px solid var(--border);border-radius:100px;font-size:.75rem;font-weight:500;color:var(--sub);cursor:pointer;transition:all .13s;margin:0;}
.bc input:checked+label{background:var(--acc-lt);border-color:var(--acc-md);color:var(--accent);}
.bc label:hover{border-color:#c4bff8;color:var(--accent);}
.cd{width:5px;height:5px;border-radius:50%;background:var(--accent);opacity:0;transition:opacity .13s;}
.bc input:checked+label .cd{opacity:1;}

.rg{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;}
@media(max-width:560px){.rg{grid-template-columns:1fr;}}
.rc{background:#fff;border:1px solid var(--border);border-radius:var(--rs);padding:14px;}
.rc.jr{--clr:var(--jr);--lt:var(--jr-lt);}
.rc.sr{--clr:var(--sr);--lt:var(--sr-lt);}
.rc.ex{--clr:var(--ex);--lt:var(--ex-lt);}
.rt{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;}
.rb{font-size:.67rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--clr);background:var(--lt);padding:2px 8px;border-radius:4px;}
.rn{font-size:1.25rem;font-weight:700;color:var(--text);line-height:1;}
.rn span{font-size:.72rem;color:var(--sub);font-weight:500;}
input[type="range"]{-webkit-appearance:none;width:100%;height:4px;background:#e5e7eb;border-radius:2px;outline:none;}
input[type="range"]::-webkit-slider-thumb{-webkit-appearance:none;width:15px;height:15px;border-radius:50%;background:var(--clr);cursor:pointer;box-shadow:0 0 0 3px var(--lt);}
.rw{margin-top:10px;padding:8px 12px;background:#fff7ed;border:1px solid #fed7aa;border-radius:7px;font-size:.74rem;color:#92400e;display:none;}

.trow{display:flex;align-items:center;gap:11px;padding:11px 14px;background:#fff;border:1px solid var(--border);border-radius:var(--rs);}
.tog{position:relative;width:36px;height:20px;flex-shrink:0;}
.tog input{opacity:0;width:0;height:0;}
.ttrack{position:absolute;inset:0;background:#d1d5db;border-radius:10px;cursor:pointer;transition:background .18s;}
.tog input:checked~.ttrack{background:var(--accent);}
.ttrack::after{content:'';position:absolute;left:3px;top:3px;width:14px;height:14px;border-radius:50%;background:#fff;transition:transform .18s;box-shadow:0 1px 3px rgba(0,0,0,.15);}
.tog input:checked~.ttrack::after{transform:translateX(16px);}
.ttxt strong{font-size:.82rem;font-weight:600;color:var(--text);display:block;}
.ttxt small{font-size:.71rem;color:var(--sub);}

.qbox{padding:10px 12px;background:#fff;border:1px solid var(--border);border-radius:var(--rs);}
.qwrap{display:flex;gap:3px;height:7px;border-radius:4px;overflow:hidden;}
.qb{border-radius:2px;transition:flex .22s;}
.qb.brd{background:var(--accent);}
.qb.cp{background:var(--ex);}
.qleg{display:flex;gap:12px;margin-top:7px;}
.ql{display:flex;align-items:center;gap:4px;font-size:.7rem;color:var(--sub);}
.qld{width:7px;height:7px;border-radius:2px;}
.qld.brd{background:var(--accent);}
.qld.cp{background:var(--ex);}

.olist{max-height:190px;overflow-y:auto;background:#fff;border:1px solid var(--border);border-radius:var(--rs);padding:4px;scrollbar-width:thin;scrollbar-color:#e5e7eb transparent;}
.oi{position:relative;}
.oi input{position:absolute;opacity:0;width:0;height:0;}
.oi label{display:flex;align-items:center;gap:9px;padding:7px 9px;border-radius:6px;cursor:pointer;font-size:.79rem;color:var(--sub);transition:background .1s,color .1s;margin:0;}
.oi label::before{content:'';width:14px;height:14px;flex-shrink:0;border:1.5px solid #d1d5db;border-radius:4px;transition:all .12s;}
.oi input:checked+label{color:var(--text);background:var(--acc-lt);}
.oi input:checked+label::before{background:var(--accent);border-color:var(--accent);background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 10 8'%3E%3Cpath d='M1 4l3 3 5-6' stroke='white' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");background-size:9px;background-repeat:no-repeat;background-position:center;}
.oi label:hover{color:var(--text);background:#f9fafb;}

.alert{display:flex;gap:12px;padding:13px 16px;border-radius:var(--rs);margin-bottom:13px;border:1px solid;animation:fi .22s ease;}
@keyframes fi{from{opacity:0;transform:translateY(-5px)}to{opacity:1;transform:translateY(0)}}
.a-ok{background:var(--ok-lt);border-color:#a7f3d0;}
.a-err{background:var(--err-lt);border-color:#fecaca;}
.aico svg{width:17px;height:17px;flex-shrink:0;margin-top:2px;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
.a-ok .aico svg{stroke:var(--ok);}
.a-err .aico svg{stroke:var(--err);}
.abody strong{display:block;font-size:.83rem;font-weight:600;color:var(--text);margin-bottom:3px;}
.abody p{font-size:.76rem;color:var(--sub);}
.cbox{display:flex;align-items:center;gap:7px;margin-top:8px;padding:8px 11px;background:#fff;border:1px solid var(--border);border-radius:7px;}
.cbox code{flex:1;font-family:'SFMono-Regular','Consolas',monospace;font-size:.73rem;color:var(--accent);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.cbtn{padding:4px 10px;background:var(--acc-lt);border:1px solid var(--acc-md);border-radius:5px;color:var(--accent);font-size:.69rem;font-weight:700;cursor:pointer;white-space:nowrap;}
.cbtn:hover{background:#ddd6fe;}

.sbar{display:flex;align-items:center;gap:13px;padding:15px 18px;background:var(--hdr);backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px);border:1px solid var(--card-border);border-radius:var(--r);box-shadow:var(--shadow);margin-top:16px;}
.btn{display:flex;align-items:center;gap:7px;padding:10px 20px;background:var(--accent);color:#fff;font-family:'Inter',sans-serif;font-size:.83rem;font-weight:700;border:none;border-radius:8px;cursor:pointer;transition:background .14s,transform .1s,box-shadow .14s;}
.btn:hover{background:#4a3fd4;transform:translateY(-1px);box-shadow:0 5px 18px rgba(91,80,232,.26);}
.btn svg{width:14px;height:14px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
.shint{font-size:.74rem;color:var(--sub);}
</style>
<div class="cfp-page">
<div class="shell">

<div class="pg-head">
  <div class="pg-ico">
    <svg viewBox="0 0 24 24"><path d="M9 12h6M9 16h6M9 8h6M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/></svg>
  </div>
  <div>
    <h1>Plan Individuel de Formation — Épreuve Figée</h1>
    <p>Factuel · Mode Mixed · Questions identiques pour tous les candidats</p>
  </div>
</div>

<?php if($success): ?>
<div class="alert a-ok">
  <div class="aico"><svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
  <div class="abody">
    <strong><?=htmlspecialchars($success)?></strong>
    <p>L'épreuve est prête. Copiez le lien candidat ci-dessous.</p>
    <div class="cbox"><code id="lc">compose_fixed.php?paper=<?=htmlspecialchars($createdPaperId??'')?>&id=&lt;USER_ID&gt;</code><button class="cbtn" onclick="copyLink()">Copier</button></div>
  </div>
</div>
<?php endif; ?>
<?php if($error): ?>
<div class="alert a-err">
  <div class="aico"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
  <div class="abody"><strong>Échec de la création</strong><p><?=htmlspecialchars($error)?></p></div>
</div>
<?php endif; ?>

<form method="post">

<!-- IDENTITÉ -->
<div class="card">
  <div class="ch">
    <div class="ci"><svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></div>
    <div><h2>Identité de l'épreuve</h2><p>Nommez et configurez le volume de questions</p></div>
  </div>
  <div class="cb">
    <div class="field"><label>Intitulé <span class="req">*</span></label><input type="text" name="label" placeholder="Ex: Factuel Mixed — Recrutement Juin 2026" value="<?=htmlspecialchars($_POST['label']??'')?>"></div>
    <div class="r2">
      <div class="field">
        <label>Nombre de QCM <span class="req">*</span></label>
        <input type="number" min="10" max="300" step="1" name="qcm_count" id="qi" value="<?=htmlspecialchars($_POST['qcm_count']??'50')?>" oninput="uQ()">
        <div class="hint">Entre 10 et 300 questions</div>
      </div>
      <div class="field">
        <label>Répartition</label>
        <div class="qbox">
          <div class="qwrap" id="qv"><div class="qb brd" style="flex:45"></div><div class="qb cp" style="flex:5"></div></div>
          <div class="qleg"><div class="ql"><div class="qld brd"></div><span id="bl">45 marques</span></div><div class="ql"><div class="qld cp"></div><span id="cl">5 CP</span></div></div>
        </div>
      </div>
    </div>
    <div class="r2">
      <div class="field">
        <label>Durée (heures) <span class="req">*</span></label>
        <input type="number" min="0" max="8" step="1" name="duration_hour" value="<?=htmlspecialchars($_POST['duration_hour']??'1')?>">
        <div class="hint">Valeur recommandée : 1 à 3 heures</div>
      </div>
      <div class="field">
        <label>Durée (minutes) <span class="req">*</span></label>
        <input type="number" min="0" max="59" step="1" name="duration_minute" value="<?=htmlspecialchars($_POST['duration_minute']??'0')?>">
        <div class="hint">Entre 0 et 59 minutes</div>
      </div>
    </div>
    <div class="trow">
      <label class="tog"><input type="checkbox" name="include_cp" value="1" id="ct" <?=(isset($_POST['include_cp'])||!isset($_POST['label']))?'checked':''?> onchange="uQ()"><div class="ttrack"></div></label>
      <div class="ttxt"><strong>Inclure Carrosserie &amp; Peinture (10 %)</strong><small>Un échantillon automatique sera prélevé du pool CP</small></div>
    </div>
  </div>
</div>

<!-- MARQUES -->
<div class="card">
  <div class="ch">
    <div class="ci"><svg viewBox="0 0 24 24"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg></div>
    <div><h2>Sélection des marques</h2><p>Choisissez les marques à inclure dans le pool de questions</p></div>
    <div class="badge" id="bb">0 marque(s)</div>
  </div>
  <div class="cb">
    <div class="bctrl"><button type="button" class="ghost" onclick="sa(true)">Tout sélectionner</button><button type="button" class="ghost" onclick="sa(false)">Tout désélectionner</button></div>
    <div class="clbl">Utilitaires &amp; Poids lourds</div>
    <div class="bgrid">
      <?php foreach(['FUSO','HINO','JCB','KING LONG','LOVOL','MERCEDES TRUCK','RENAULT TRUCK','SINOTRUCK','TOYOTA BT','TOYOTA FORKLIFT'] as $b):$sel=isset($_POST['brand'])&&in_array($b,(array)$_POST['brand']);?>
      <div class="bc"><input type="checkbox" name="brand[]" value="<?=htmlspecialchars($b)?>" id="b<?=md5($b)?>" <?=$sel?'checked':''?> onchange="uB()"><label for="b<?=md5($b)?>"><span class="cd"></span><?=htmlspecialchars($b)?></label></div>
      <?php endforeach;?>
    </div>
    <div class="clbl">Véhicules particuliers &amp; Deux-roues</div>
    <div class="bgrid">
      <?php foreach(['BYD','CITROEN','MERCEDES','MITSUBISHI','PEUGEOT','SUZUKI','TOYOTA','VOLKSWAGEN','YAMAHA BATEAU','YAMAHA MOTO'] as $b):$sel=isset($_POST['brand'])&&in_array($b,(array)$_POST['brand']);?>
      <div class="bc"><input type="checkbox" name="brand[]" value="<?=htmlspecialchars($b)?>" id="b<?=md5($b)?>" <?=$sel?'checked':''?> onchange="uB()"><label for="b<?=md5($b)?>"><span class="cd"></span><?=htmlspecialchars($b)?></label></div>
      <?php endforeach;?>
    </div>
  </div>
</div>

<!-- RATIOS -->
<div class="card">
  <div class="ch">
    <div class="ci"><svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></div>
    <div><h2>Ratios de difficulté</h2><p>Répartition Junior / Senior / Expert</p></div>
    <div class="badge" id="rb2">Total : 100%</div>
  </div>
  <div class="cb">
    <div class="rg">
      <div class="rc jr"><div class="rt"><div class="rb">Junior</div><div class="rn" id="vJ"><?=$_POST['ratio_junior']??50?><span>%</span></div></div><input type="range" min="0" max="100" value="<?=$_POST['ratio_junior']??50?>" oninput="uR('J',this.value)"><input type="hidden" name="ratio_junior" id="hJ" value="<?=$_POST['ratio_junior']??50?>"></div>
      <div class="rc sr"><div class="rt"><div class="rb">Senior</div><div class="rn" id="vS"><?=$_POST['ratio_senior']??45?><span>%</span></div></div><input type="range" min="0" max="100" value="<?=$_POST['ratio_senior']??45?>" oninput="uR('S',this.value)"><input type="hidden" name="ratio_senior" id="hS" value="<?=$_POST['ratio_senior']??45?>"></div>
      <div class="rc ex"><div class="rt"><div class="rb">Expert</div><div class="rn" id="vE"><?=$_POST['ratio_expert']??5?><span>%</span></div></div><input type="range" min="0" max="100" value="<?=$_POST['ratio_expert']??5?>" oninput="uR('E',this.value)"><input type="hidden" name="ratio_expert" id="hE" value="<?=$_POST['ratio_expert']??5?>"></div>
    </div>
    <div class="rw" id="rw">Total : <strong id="rt"></strong>% — recommandé : 100%</div>
  </div>
</div>

<!-- ORIENTATION -->
<div class="card">
  <div class="ch">
    <div class="ci"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg></div>
    <div><h2>Questions d'orientation</h2><p>Questions hors-QCM à joindre à l'épreuve</p></div>
    <div class="badge" id="ob">0 sélectionnée(s)</div>
  </div>
  <div class="cb">
    <?php if(empty($orientationOptions)):?>
    <div style="padding:16px;text-align:center;color:var(--sub);font-size:.8rem;background:#fff;border:1px solid var(--border);border-radius:var(--rs);">Aucune question d'orientation disponible.</div>
    <?php else:?>
    <div style="display:flex;justify-content:flex-end;margin-bottom:7px"><button type="button" class="ghost" onclick="cO()">Tout désélectionner</button></div>
    <div class="olist">
      <?php foreach($orientationOptions as $opt):$sel=isset($_POST['orientation_selected'])&&in_array($opt['id'],(array)$_POST['orientation_selected']);?>
      <div class="oi"><input type="checkbox" name="orientation_selected[]" value="<?=htmlspecialchars($opt['id'])?>" id="or<?=htmlspecialchars($opt['id'])?>" <?=$sel?'checked':''?> onchange="uO()"><label for="or<?=htmlspecialchars($opt['id'])?>"><?=htmlspecialchars($opt['title'])?></label></div>
      <?php endforeach;?>
    </div>
    <?php endif;?>
  </div>
</div>

<div class="sbar">
  <button type="submit" class="btn"><svg viewBox="0 0 24 24"><path d="M22 2L11 13"/><path d="M22 2L15 22 11 13 2 9l20-7z"/></svg>Générer l'épreuve figée</button>
  <div class="shint">L'opération peut prendre quelques secondes selon la taille du pool.</div>
</div>

</form>
</div>
</div>
<script>
function uQ(){const n=parseInt(document.getElementById('qi').value)||0,h=document.getElementById('ct').checked,c=h?Math.max(1,Math.round(n*.1)):0,b=n-c;document.getElementById('qv').innerHTML=`<div class="qb brd" style="flex:${b||1}"></div>`+(h?`<div class="qb cp" style="flex:${c}"></div>`:'');document.getElementById('bl').textContent=b+' marques';document.getElementById('cl').textContent=h?c+' CP':'—';}
function uR(k,v){v=parseInt(v);({'J':'vJ','S':'vS','E':'vE'}[k]&&(document.getElementById({'J':'vJ','S':'vS','E':'vE'}[k]).innerHTML=v+'<span>%</span>'));document.getElementById('h'+k).value=v;cR();}
function cR(){const t=(+document.getElementById('hJ').value||0)+(+document.getElementById('hS').value||0)+(+document.getElementById('hE').value||0),w=document.getElementById('rw'),b=document.getElementById('rb2');t!==100?(document.getElementById('rt').textContent=t,w.style.display='block',b.textContent='Total : '+t+'%',b.style.background='#fff7ed',b.style.color='#92400e'):(w.style.display='none',b.textContent='Total : 100%',b.style.background='',b.style.color='');}
function uB(){document.getElementById('bb').textContent=document.querySelectorAll('input[name="brand[]"]:checked').length+' marque(s)';}
function sa(v){document.querySelectorAll('input[name="brand[]"]').forEach(c=>c.checked=v);uB();}
function uO(){const e=document.getElementById('ob');if(e)e.textContent=document.querySelectorAll('input[name="orientation_selected[]"]:checked').length+' sélectionnée(s)';}
function cO(){document.querySelectorAll('input[name="orientation_selected[]"]').forEach(c=>c.checked=false);uO();}
function copyLink(){const t=document.getElementById('lc')?.textContent||'';navigator.clipboard.writeText(t).catch(()=>{});const b=document.querySelector('.cbtn');b.textContent='✓ Copié';setTimeout(()=>b.textContent='Copier',2000);}
uQ();uB();uO();cR();
</script>
<?php closeBackgroundContainer(); include_once "partials/footer.php"; ?>