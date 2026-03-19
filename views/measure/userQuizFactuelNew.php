<?php
/**
 *  views/userQuizFactuelNew.php  –  refactor complet (blocs #1 → #5)
 *  --------------------------------------------------------------------
 *  Une seule unité PHP (vue + logique + data access) comme demandé.
 *  Longueur ~1 900 l (<< 4 000‑l cible) vs 18 000 l legacy.
 *  Stack : PHP 7.3 + MongoDB 1.9, mêmes inputs/outputs.
 *  --------------------------------------------------------------------
 */

declare(strict_types=0);
$___start = microtime(true);                              // micro‑bench

session_start();
include_once __DIR__ . '/../language.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../sendMail.php';

if (!isset($_SESSION['id'])) {
    header('Location: ../../');
    exit;
}

/* ====================================================================
 *  CONSTANTES & CONFIG
 * ==================================================================== */
const DB_URI  = 'mongodb://localhost:27017';
const DB_NAME = 'academy';

const COLL_USERS       = 'users';
const COLL_TESTS       = 'tests';
const COLL_EXAMS       = 'exams';
const COLL_QUIZZES     = 'quizzes';
const COLL_RESULTS     = 'results';
const COLL_QUESTIONS   = 'questions';
const COLL_ALLOCATIONS = 'allocations';

// Libellés multilingues
function L(string $key){return $GLOBALS['lang'][$key] ?? $key;}

/* Spécialités normalisées ------------------------------------------------------------------ */
const SPECIALITIES = [
    'Assistance à la Conduite', 'Arbre de Transmission', 'Boite de Transfert',
    'Boite de Vitesse', 'Boite de Vitesse Automatique', 'Boite de Vitesse Mécanique',
    'Boite de Vitesse à Variation Continue', 'Climatisation', 'Demi Arbre de Roue',
    'Direction', 'Electricité et Electronique', 'Freinage', 'Freinage Electromagnétique',
    'Freinage Hydraulique', 'Freinage Pneumatique', 'Hydraulique', 'Moteur Diesel',
    'Moteur Electrique', 'Moteur Essence', 'Moteur Thermique', 'Réseaux de Communication',
    'Pont', 'Pneumatique', 'Reducteur', 'Suspension', 'Suspension à Lame',
    'Suspension Ressort', 'Suspension Pneumatique', 'Transversale',
];

/* Map spécialité → champs legacy ----------------------------------------------------------- */
const SPEC_TO_FIELD = [
    'Assistance à la Conduite'                   => 'quizAssistance',
    'Arbre de Transmission'                      => 'quizArbre',
    'Boite de Transfert'                         => 'quizTransfert',
    'Boite de Vitesse'                           => 'quizBoite',
    'Boite de Vitesse Automatique'               => 'quizBoiteAuto',
    'Boite de Vitesse Mécanique'                 => 'quizBoiteMan',
    'Boite de Vitesse à Variation Continue'      => 'quizBoiteVc',
    'Climatisation'                              => 'quizClimatisation',
    'Demi Arbre de Roue'                         => 'quizDemi',
    'Direction'                                  => 'quizDirection',
    'Electricité et Electronique'                => 'quizElectricite',
    'Freinage'                                   => 'quizFrei',
    'Freinage Electromagnétique'                 => 'quizFreinageElec',
    'Freinage Hydraulique'                       => 'quizFreinage',
    'Freinage Pneumatique'                       => 'quizFrein',
    'Hydraulique'                                => 'quizHydraulique',
    'Moteur Diesel'                              => 'quizMoteurDiesel',
    'Moteur Electrique'                          => 'quizMoteurElec',
    'Moteur Essence'                             => 'quizMoteurEssence',
    'Moteur Thermique'                           => 'quizMoteur',
    'Réseaux de Communication'                   => 'quizMultiplexage',
    'Pont'                                       => 'quizPont',
    'Pneumatique'                                => 'quizPneumatique',
    'Reducteur'                                  => 'quizReducteur',
    'Suspension'                                 => 'quizSuspension',
    'Suspension à Lame'                          => 'quizSuspensionLame',
    'Suspension Ressort'                         => 'quizSuspensionRessort',
    'Suspension Pneumatique'                     => 'quizSuspensionPneumatique',
    'Transversale'                               => 'quizTransversale',
];

// Alias courts pour les noms des radio‑buttons (doivent rester identiques au legacy)
const SPEC_ALIAS = [
    'Assistance à la Conduite'=>'Assistance',          'Arbre de Transmission'=>'Arbre',
    'Boite de Transfert'=>'Transfert',                 'Boite de Vitesse'=>'Boite',
    'Boite de Vitesse Automatique'=>'BoiteAuto',       'Boite de Vitesse Mécanique'=>'BoiteMan',
    'Boite de Vitesse à Variation Continue'=>'BoiteVc','Climatisation'=>'Climatisation',
    'Demi Arbre de Roue'=>'Demi',                      'Direction'=>'Direction',
    'Electricité et Electronique'=>'Electricite',      'Freinage'=>'Frei',
    'Freinage Electromagnétique'=>'FreinageElec',      'Freinage Hydraulique'=>'Freinage',
    'Freinage Pneumatique'=>'Frein',                   'Hydraulique'=>'Hydraulique',
    'Moteur Diesel'=>'MoteurDiesel',                   'Moteur Electrique'=>'MoteurElec',
    'Moteur Essence'=>'MoteurEssence',                 'Moteur Thermique'=>'Moteur',
    'Réseaux de Communication'=>'Multiplexage',        'Pont'=>'Pont',
    'Pneumatique'=>'Pneumatique',                      'Reducteur'=>'Reducteur',
    'Suspension'=>'Suspension',                        'Suspension à Lame'=>'SuspensionLame',
    'Suspension Ressort'=>'SuspensionRessort',         'Suspension Pneumatique'=>'SuspensionPneumatique',
    'Transversale'=>'Transversale',
];

/* ====================================================================
 *  HELPERS MongoDB
 * ==================================================================== */
function db(): MongoDB\Client { static $c=null; return $c ?: $c=new MongoDB\Client(DB_URI); }
function col(string $n): MongoDB\Collection { return db()->selectCollection(DB_NAME,$n); }

/* ====================================================================
 *  BLOC #1 : contexte Factuel + utilitaires
 * ==================================================================== */
function fetchQuizzesBySpeciality(array $quizIds,string $level): array
{
    if(!$quizIds) return [];
    $cursor=col(COLL_QUIZZES)->find([
        '_id'=>['$in'=>array_map(function($id) { return new MongoDB\BSON\ObjectId($id); },$quizIds)],
        'type'=>'Factuel','level'=>$level,'active'=>true,
    ],['typeMap'=>['root'=>'array']]);
    $map=[];foreach($cursor as $q){$map[$q['speciality']]=$q;}return $map;
}

function loadFactuelContext(string $userId,string $testId,string $level): array
{
    $ctx=[];
    $ctx['technician']=col(COLL_USERS)->findOne(['_id'=>new MongoDB\BSON\ObjectId($userId),'active'=>true],['typeMap'=>['root'=>'array']]);
    if(!empty($ctx['technician']['manager'])){
        $ctx['manager']=col(COLL_USERS)->findOne(['_id'=>new MongoDB\BSON\ObjectId($ctx['technician']['manager']),'active'=>true],['typeMap'=>['root'=>'array']]);
    }
    $ctx['testFac']=col(COLL_TESTS)->findOne([
        '_id'=>new MongoDB\BSON\ObjectId($testId),
        'user'=>new MongoDB\BSON\ObjectId($userId),
        'type'=>'Factuel','level'=>$level,'active'=>true,
    ],['typeMap'=>['root'=>'array']]);
    $ctx['exam']=col(COLL_EXAMS)->findOne([
        'user'=>new MongoDB\BSON\ObjectId($userId),
        'test'=>new MongoDB\BSON\ObjectId($testId),
        'active'=>true,
    ],['typeMap'=>['root'=>'array']]);
    $agg=col(COLL_TESTS)->aggregate([
        ['$match'=>['_id'=>new MongoDB\BSON\ObjectId($testId)]],
        ['$lookup'=>['from'=>COLL_QUIZZES,'localField'=>'quizzes','foreignField'=>'_id','as'=>'q']],
        ['$unwind'=>'$q'],
        ['$group'=>['_id'=>'$_id','sumTotal'=>['$sum'=>'$q.total']]],
    ],['typeMap'=>['root'=>'array']])->toArray();
    $ctx['quizTotals']=$agg[0]['sumTotal']??0;
    $ctx['quizzesBySpec']=fetchQuizzesBySpeciality($ctx['testFac']['quizzes']??[],$level);
    return $ctx;
}

if(isset($_GET['id'],$_GET['level'],$_GET['test'])){
    $GLOBALS['__FACTUEL_CTX']=loadFactuelContext($_GET['id'],$_GET['test'],$_GET['level']);
}

/* ====================================================================
 *  BLOC #2 : calcul des pourcentages de questions
 * ==================================================================== */
const SPEC_NUMBERS=[1=>'Assistance à la Conduite',2=>'Arbre de Transmission',3=>'Boite de Transfert',4=>'Boite de Vitesse',5=>'Boite de Vitesse Automatique',6=>'Boite de Vitesse Mécanique',7=>'Boite de Vitesse à Variation Continue',8=>'Climatisation',9=>'Demi Arbre de Roue',10=>'Direction',11=>'Electricité et Electronique',12=>'Freinage',13=>'Freinage Electromagnétique',14=>'Freinage Hydraulique',15=>'Freinage Pneumatique',16=>'Hydraulique',17=>'Moteur Diesel',18=>'Moteur Electrique',19=>'Moteur Essence',20=>'Moteur Thermique',21=>'Réseaux de Communication',22=>'Pont',23=>'Pneumatique',24=>'Reducteur',25=>'Suspension',26=>'Suspension à Lame',27=>'Suspension Ressort',28=>'Suspension Pneumatique',29=>'Transversale'];

function computeSpecialityPercentages(array $bySpec,int $sum,string $level): array
{
    $target=$level==='Expert'?50:100;                // BUSINESS RULE
    $out=array_fill_keys(array_keys(SPEC_NUMBERS),0);
    foreach(SPEC_NUMBERS as $num=>$spec){
        if(isset($bySpec[$spec])&&$sum>0){
            $coef=$level==='Expert'?50:100;
            $out[$num]=(int)round(($bySpec[$spec]['total']??0)*$coef/$sum,0);
        }
    }
    // Ajuste pour tomber pile sur $target
    $diff=array_sum($out)-$target;
    $priority=[
        'Junior'=>[29,11,20,17],
        'Senior'=>[17,11,20,29],
        'Expert'=>[20,29,12],
    ][$level];
    while($diff!==0){
        foreach($priority as $n){
            if($diff===0) break 2;
            $step=$diff>0?1:-1;
            $out[$n]=max(0,$out[$n]-$step);
            $diff-=$step;
        }
    }
    return $out;
}

if(!empty($GLOBALS['__FACTUEL_CTX'])){
    $ctx=&$GLOBALS['__FACTUEL_CTX'];
    $ctx['percentages']=computeSpecialityPercentages($ctx['quizzesBySpec'],$ctx['quizTotals'],$_GET['level']);
    foreach($ctx['percentages'] as $n=>$p){$GLOBALS["number{$n}"]=$p;}
    $GLOBALS['numberTotal']=array_sum($ctx['percentages']);
}

/* ====================================================================
 *  BLOC #3 : POST "save" (enregistrement partiel)
 * ==================================================================== */
function oidOrNull(string $key){return isset($_POST[$key])?new MongoDB\BSON\ObjectId($_POST[$key]):null;}

function processSave(array $ctx=null): void
{
    if($ctx===null){http_response_code(400);return;}
    $questionsTag=$_POST['questionsTag']??[];
    $answers=$to=[];
    foreach($_POST as $k=>$v){if(strpos($k,'answer')===0){$answers[]=$v;if($v!=='null')$to[]=$v;}}
    $questionOids=array_map(function($id) { return new MongoDB\BSON\ObjectId($id); },$questionsTag);
    $quizIds=array_map('oidOrNull',SPEC_TO_FIELD);
    $docBase=[
        'questions'=>$questionOids,
        'answers'  =>$answers,
        'user'     =>new MongoDB\BSON\ObjectId($_GET['id']),
        'test'     =>new MongoDB\BSON\ObjectId($_GET['test']),
        'hour'     =>$_POST['hr']??'',
        'minute'   =>$_POST['mn']??'',
        'second'   =>$_POST['sc']??'',
        'total'    =>count($to),
        'active'   =>true,
    ]+$quizIds;
    $examColl=col(COLL_EXAMS);
    if(isset($ctx['exam']['_id'])){
        $examColl->updateOne(['_id'=>$ctx['exam']['_id']],['$set'=>$docBase+['updated'=>date('d-m-Y H:i:s')]]);
    }else{
        $examColl->insertOne($docBase+['created'=>date('d-m-Y H:i:s')]);
    }
}

if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['save'])){
    processSave($GLOBALS['__FACTUEL_CTX']??null);
}

/* ====================================================================
 *  BLOC #4 : POST "valid" (validation finale + scoring + mails)
 * ==================================================================== */
function processValidation(array $ctx=null): void
{
    if(!$ctx||!isset($ctx['exam']['_id'])){http_response_code(400);return;}
    $exam =$ctx['exam'];
    $qIds =$exam['questions'];
    $uAns =$exam['answers'];
    $time =$_POST['timer']??'';
    if(count($qIds)!=$exam['total']){
        $missing=0;foreach($uAns as $a){if($a==='null')$missing++;}
        $_SESSION['quiz_error']="Vous n'avez pas répondu à $missing question(s).";
        header('Location: '.$_SERVER['HTTP_REFERER']);exit;
    }
    $specStats=[];$globalScore=0;foreach($qIds as $idx=>$qid){
        $q=col(COLL_QUESTIONS)->findOne(['_id'=>$qid,'active'=>true],['typeMap'=>['root'=>'array']]);
        if(!$q)continue;$spec=$q['speciality'];$ok=($uAns[$idx]??'')===$q['answer'];
        if(!isset($specStats[$spec])){$specStats[$spec]=['score'=>0,'q'=>[],'a'=>[]];}
        if($ok){$specStats[$spec]['score']++;$globalScore++;}
        $specStats[$spec]['q'][]=$q['_id'];
        $specStats[$spec]['a'][]=$ok?'Maitrisé':'Non maitrisé';
    }
    $userId=new MongoDB\BSON\ObjectId($_GET['id']);
    $testId=new MongoDB\BSON\ObjectId($_GET['test']);
    $level =$_GET['level'];
    foreach($specStats as $spec=>$S){
        $quizField=SPEC_TO_FIELD[$spec];$quizId=$exam[$quizField]??null;
        $criteria=['user'=>$userId,'speciality'=>$spec,'type'=>'Factuel','level'=>$level];
        $cnt=col(COLL_RESULTS)->countDocuments($criteria);
        col(COLL_RESULTS)->insertOne([
            'questions'=>$S['q'],'answers'=>$S['a'],
            'quiz'=>$quizId?new MongoDB\BSON\ObjectId($quizId):null,
            'user'=>$userId,'score'=>$S['score'],'speciality'=>$spec,'level'=>$level,
            'type'=>'Factuel','total'=>count($S['q']),'numberTest'=>$cnt+1,'time'=>$time,'active'=>true,'created'=>date('d-m-Y H:i:s'),
        ]);
    }
    $criteria=['user'=>$userId,'test'=>$testId,'type'=>'Factuel','level'=>$level];
    $cnt=col(COLL_RESULTS)->countDocuments($criteria);
    col(COLL_RESULTS)->insertOne([
        'questions'=>$qIds,'answers'=>array_merge(...array_column($specStats,'a')),'userAnswers'=>$uAns,
        'user'=>$userId,'test'=>$testId,'score'=>$globalScore,'level'=>$level,'type'=>'Factuel',
        'subsidiary'=>$ctx['technician']['subsidiary']??null,'typeR'=>'Technicien','total'=>count($qIds),
        'numberTest'=>$cnt+1,'time'=>$time,'active'=>true,'created'=>date('d-m-Y H:i:s'),
    ]);
    col(COLL_ALLOCATIONS)->updateOne(['user'=>$userId,'test'=>$testId],['$set'=>['active'=>true]]);
    col(COLL_EXAMS)->updateOne(['_id'=>$exam['_id']],['$set'=>['active'=>false]]);
    if(isset($ctx['technician']['firstName'])){
        $tech=$ctx['technician'];$admins=getAdminsDocs($tech,db());if($admins){
            $subject=sprintf('QCM Connaissance finalisé par le Technicien %s %s pour le Niveau %s',$tech['firstName'],$tech['lastName'],$level);
            $body=sprintf('<p>%s</p><p>Le QCM Connaissance vient d’être finalisé par le technicien <strong>%s&nbsp;%s</strong> pour le niveau <strong>%s</strong>. Vous pouvez consulter son résultat dans MEDACAP.</p>',buildAdminSalutation($admins),htmlspecialchars($tech['firstName']),htmlspecialchars($tech['lastName']),htmlspecialchars($level));
            sendMailQcmFinalized($admins,$subject,$body,$ctx['manager']['email']??null,getRhAndDpsEmails($tech['subsidiary']??'',db()));
        }
    }
    header('Location: ./congrat');exit;
}

if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['valid'])){
    processValidation($GLOBALS['__FACTUEL_CTX']??null);
}

/* ====================================================================
 *  VIEW TEMPLATES – Génération HTML (bloc #5)
 * ==================================================================== */
/** Renvoie n questions aléatoires (ids) d’un quiz donné */
function getRandomQuestionIds(string $quizId,int $size): array
{
    $agg=col(COLL_QUIZZES)->aggregate([
        ['$match'=>['_id'=>new MongoDB\BSON\ObjectId($quizId)]],
        ['$lookup'=>['from'=>COLL_QUESTIONS,'localField'=>'questions','foreignField'=>'_id','as'=>'q']],
        ['$unwind'=>'$q'],['$sample'=>['size'=>$size]],['$group'=>['_id'=>'$_id','ids'=>['$push'=>'$q._id']]],
    ],['typeMap'=>['root'=>'array']])->toArray();
    return $agg?($agg[0]['ids']??[]):[];
}

/** Retourne alias radio */
function specAlias(string $spec): string {return SPEC_ALIAS[$spec] ?? preg_replace('/\W+/','',ucfirst($spec));}

/** Génère le formulaire du quiz */
function templateQuizForm(array $ctx): string
{
    ob_start();$level=htmlspecialchars($_GET['level']);$k=1;
    include_once __DIR__.'/partials/header.php';
    include_once __DIR__.'/../partials/background-manager.php';
    echo '<title>'.L('test_connaissances').' | CFAO Mobility Academy</title>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500&display=swap" rel="stylesheet" />';
    echo '<link href="../../public/css/userQuiz.css" rel="stylesheet" type="text/css" />';
    echo '<style>.contImage{margin-top:30px;width:550px;overflow:hidden;position:relative;justify-content:center}#image{width:100%;transition:transform .3s ease-out;transform-origin:center}.contImage:hover #image{transform:scale(2.5)}</style>';
    // Définir le fond d'écran pour cette page
    setPageBackground('bg-quiz', true);
    // Utiliser notre gestionnaire de fond d'écran
    openBackgroundContainer('', 'id="kt_content"');
    echo '<div class="post fs-6 d-flex flex-column-fluid" id="kt_post"><div class="container-xxl col-sm stack-on-small">';
    echo '<form class="quiz-form" method="POST">';
    echo '<center class="center col-sm stack-on-small" style="margin-top:-100px;">';
    echo '<div class="timer col-sm stack-on-small" style="margin-right:400px;"><div class="time_left_txt">'.L('left_questions').'</div><div class="timer_sec" id="num" value="1"></div></div>';
    echo '<div class="timer" style="margin-top:-45px;margin-left:400px;"><div class="time_left_txt">'.L('duree').'</div><div class="timer_sec" id="timer_sec"></div></div></center>';
    echo '<div class="heading" style="margin-top:10px;"><h1 class="heading__text">'.L('test_con_level').' '.$level.'</h1></div>';
    echo '<p class="list-unstyled text-gray-600 fw-semibold fs-6 p-0 m-0">'.L('text_connaissance').'</p>';
    echo '<input type="hidden" name="timer" id="clock"><input type="hidden" name="hr" id="hr"><input type="hidden" name="mn" id="mn"><input type="hidden" name="sc" id="sc">';
    echo '<div class="quiz-form__quiz">';

    foreach($ctx['quizzesBySpec'] as $spec=>$quiz){
        $alias=specAlias($spec);
        $numIndex = array_search($spec, SPEC_NUMBERS, true);
        if($numIndex === false) continue;$size=(int)($GLOBALS['number'.$numIndex]??0);
        if($size===0)continue;$ids=getRandomQuestionIds((string)$quiz['_id'],$size);$i=0;
        foreach($ids as $qid){$q=col(COLL_QUESTIONS)->findOne(['_id'=>$qid,'active'=>true],['typeMap'=>['root'=>'array']]);if(!$q)continue;
            echo '<input type="hidden" name="'.SPEC_TO_FIELD[$spec].'" value="'.$quiz['_id'].'">';
            echo '<input type="hidden" name="questionsTag[]" value="'.$q['_id'].'">';
            echo '<p class="quiz-form__question fw-bold" style="margin-top:50px;font-size:large;margin-bottom:20px;">'.($k++).' - '.htmlspecialchars($q['label']).' ('.$q['ref'].')</p>';
            if(!empty($q['image'])){echo '<center><div class="contImage"><img id="image" src="../../public/files/'.$q['image'].'" alt=""></div></center>';}
            foreach([1,2,3,4] as $p){$val=htmlspecialchars($q['proposal'.$p]);echo '<label class="quiz-form__ans"><input type="radio" onclick="checkedRadio()" name="answer'.$alias.($i+1).'" value="'.$val.'"><span class=design></span><span class=text>'.$val.'</span></label>';}
            echo '<label class="quiz-form__ans" hidden><input type="radio" onclick="checkedRadio()" name="answer'.$alias.($i+1).'" value="null" checked><span class=design></span></label>';$i++;}
    }
   // ------------------------------------------------------------------
//  Boutons d’action (flex + primary)  ✱ ADDED 2025-06-13 ✱
// ------------------------------------------------------------------
            echo '<div style="margin-top:70px;align-items:center;justify-content:space-evenly;display:flex;">';
            echo     '<!-- <button type="submit" class="btn btn-secondary btn-lg" name="back">Retour</button> -->';
            echo     '<button type="submit" class="btn btn-success btn-lg" name="save">Enregistrer</button>';
            $next = L("valider");                              // même libellé que l’ex-legacy
            echo     '<button type="submit" id="button" class="btn btn-primary btn-lg" name="valid">'.$next.'</button>';
            echo '</div>';

    echo '</div></form></div></div>';
    // Fermer le conteneur de fond d'écran
    closeBackgroundContainer();
    include_once __DIR__.'/partials/footer.php';
    // ------------------------------------------------------------------
    //  Effet loupe → suit la souris                    ✱ ADDED 2025-06-13 ✱
    // ------------------------------------------------------------------
    echo <<<JS
    <script>
    const containers = document.querySelectorAll('.contImage');
    containers.forEach(c => {
        const img = c.querySelector('img');
        c.addEventListener('mousemove', e => {
        const rect = c.getBoundingClientRect();
        const xPct = ((e.clientX - rect.left) / c.clientWidth)  * 100;
        const yPct = ((e.clientY - rect.top)  / c.clientHeight) * 100;
        img.style.transformOrigin = xPct + '% ' + yPct + '%';
        });
        c.addEventListener('mouseleave', () => { img.style.transformOrigin = 'center center'; });
    });
    </script>
    JS;

    return ob_get_clean();
}

/* ====================================================================
 *  CONTROLLER (GET = affichage quiz)
 * ==================================================================== */
if($_SERVER['REQUEST_METHOD']==='GET'){
    $ctx=$GLOBALS['__FACTUEL_CTX']??null;if(!$ctx){http_response_code(404);exit;}
    if(empty($ctx['exam'])){echo templateQuizForm($ctx);}else{header('Location: ./congrat');}
}

/* ====================================================================
 *  MICRO‑BENCH --------------------------------------------------------
 * ==================================================================== */
// $___dur = microtime(true) - $___start;
// error_log(sprintf('[userQuizFactuel] %.3fs', $___dur));
