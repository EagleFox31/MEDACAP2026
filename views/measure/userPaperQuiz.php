<?php
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
include_once "../language.php";
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
function collect_aligned_answers(array $post, int $n): array {
  $tmp=[]; foreach ($post as $k=>$v){
    if (!is_string($k)) continue;
    if (preg_match('/^answer(\d+)$/',$k,$m)) {
      $idx=(int)$m[1]; $tmp[$idx]= is_string($v)?$v:'null';
    }
  }
  $out=array_fill(0,$n,'null');
  if ($tmp){ ksort($tmp); foreach($tmp as $i=>$val){ if ($i<$n) $out[$i]=$val; } }
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

/* ---------------- Mongo ---------------- */
$client   = new Client("mongodb://localhost:27017");
$db       = $client->academy;

$users         = $db->users;
$papers        = $db->papers;
$questions     = $db->questions;
$quizzesCol    = $db->quizzes;
$results       = $db->results;
$exams         = $db->exams;
$allocations   = $db->allocations;
$orientCol     = $db->orientation_questions;

/* ---------------- Inputs ---------------- */
$userId   = (string)($_SESSION["id"] ?? '');
$paperArg = qs_get('paper');
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

/* ---------------- Paper ---------------- */
$paper = $papers->findOne(['_id'=>$paperOid, 'active'=>true, 'type'=>['$in'=>['Factuel','Factuelle']], 'mode'=>'mixed']);
if (!$paper) { http_response_code(404); echo "Épreuve figée introuvable."; exit(); }

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
/* En mode revue: on ignore toute soumission (lecture seule) */
if ($reviewMode && $_SERVER['REQUEST_METHOD']==='POST') {
  header("Location: ".$_SERVER['REQUEST_URI']); exit;
}

if (!$reviewMode && $_SERVER['REQUEST_METHOD']==='POST') {
  $hr = (int)(post_str('hr','0')); $mn = (int)(post_str('mn','0')); $sc = (int)(post_str('sc','0'));
  $timeStr = post_str('timer','');
  $isTimeUp = (post_str('timeup','0') === '1');

  // -------- Orientation (toujours lue pour pouvoir sauver indépendamment)
  $orientPosts = $_POST['orient'] ?? [];
  $orientationAnswers = [];
  foreach ($orientationDocs as $od) {
    $oidStr  = (string)$od['_id'];
    $opts    = to_php_array($od['options'] ?? []);
    $chosen  = isset($orientPosts[$oidStr]) && is_array($orientPosts[$oidStr]) ? array_values(array_filter($orientPosts[$oidStr], 'is_string')) : [];
    $orientationAnswers[$oidStr] = array_values(array_intersect($chosen, $opts));
  }

  // -------- QCM (ignoré si modeOrientationOnly)
  $qids = $paperQOids; $answers = []; $totalAnswered = 0;
  if (!$modeOrientationOnly) {
    $qidsRaw = post_array('questionsTag');
    $qids = [];
    foreach ($qidsRaw as $id) { $oid = to_oid(is_string($id)?$id:null); if ($oid) $qids[]=$oid; }
    if (empty($qids)) $qids = $paperQOids;

    $answers = collect_aligned_answers($_POST, count($qids));
    foreach ($answers as $a) if ($a!=='null') $totalAnswered++;
  }

  // === TIMEUP (auto-validate & redirect congrat) ===
 // === TIMEUP (auto-validate & redirect congrat) ===
if ($isTimeUp) {
    // Upsert exam snapshot
    if ($exam) {
        $set = [
            'orientationAnswers'=>$orientationAnswers,
            'hour'=>$hr,'minute'=>$mn,'second'=>$sc,
            'updated'=>date('Y-m-d H:i:s')
        ];
        if (!$modeOrientationOnly) {
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
        if (!$modeOrientationOnly) {
            $doc['questions']=$qids; 
            $doc['answers']=$answers; 
            $doc['total']=$totalAnswered;
            $doc['originalOrder'] = $paperQOids;
        }
        $exams->insertOne($doc);
        $exam = $exams->findOne(['user'=>$userOid,'paper'=>$paperOid,'active'=>true]);
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
        header("Location: ./userPaperQuiz.php?paper=".$paperId."&focus=orientation&bonus=1"); 
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
      if (!$modeOrientationOnly) {
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
      if (!$modeOrientationOnly) {
        $doc['questions']=$qids; $doc['answers']=$answers; $doc['total']=$totalAnswered;
      }
      $exams->insertOne($doc);
      $exam = $exams->findOne(['user'=>$userOid,'paper'=>$paperOid,'active'=>true]);
    }
    header("Location: ".$_SERVER['REQUEST_URI']); exit;
  }

  // === VALID ORIENTATION SEULE ===
  if (isset($_POST['valid_orientation'])) {
    if (!orientation_complete($orientationDocs, $orientationAnswers)) {
        //Message dynamique basé sur les vraies règles
        $error_details = [];
        
        foreach ($orientationDocs as $od) {
            $oid = (string)$od['_id'];
            $title = (string)($od['title'] ?? 'Question d\'orientation');
            $min = (int)($od['minSelect'] ?? 0);
            $max = (int)($od['maxSelect'] ?? 0);
            $sel = isset($orientationAnswers[$oid]) ? $orientationAnswers[$oid] : [];
            $count = count($sel);
            
            // Vérifier si cette question est incorrecte
            if ($min > 0 && $count < $min) {
                $error_details[] = "• <strong>" . htmlspecialchars($title) . "</strong> : vous avez sélectionné {$count} option(s), mais le minimum requis est {$min}";
            }
            if ($max > 0 && $count > $max) {
                $error_details[] = "• <strong>" . htmlspecialchars($title) . "</strong> : vous avez sélectionné {$count} option(s), mais le maximum autorisé est {$max}";
            }
        }
        
        if (!empty($error_details)) {
            $error_msg = "<strong>Veuillez corriger les questions d'orientation suivantes :</strong><br><br>" . implode("<br>", $error_details);
        } else {
            $error_msg = "Merci de compléter correctement toutes les questions d'orientation.";
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

      // Résultat Orientation (upsert)
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

      // Close
      $allocations->updateOne(['_id'=>$allocation['_id']], ['$set'=>['status'=>'done','active'=>false,'finishedAt'=>date('Y-m-d H:i:s')]]);
      if ($exam) { $exams->updateOne(['_id'=>$exam['_id']], ['$set'=>['active'=>false]]); }
      header("Location: ./congrat"); exit;
    }
  }

  // === VALID (QCM + éventuellement orientation) ===
  if (isset($_POST['valid']) && !$modeOrientationOnly) {
    if (count($qids)===0) {
      $error_msg = "Aucune question trouvée.";
    } elseif ($totalAnswered !== count($qids)) {
      $missing = count($qids)-$totalAnswered;
      $error_msg = "Vous n'avez pas répondu à {$missing} question(s).";
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
$initHr = (int)($examArray['hour']   ?? 1);
$initMn = (int)($examArray['minute'] ?? 0);
$initSc = (int)($examArray['second'] ?? 0);

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
$lockQcm = $reviewMode || (bool)($examArray['lockQcm'] ?? false) || $modeOrientationOnly;
$lockOrientation = $reviewMode;

$orientationStartIndex = count($orderedQ);
$shouldFocusOrientation = $modeOrientationOnly || (qs_get('focus','') === 'orientation');
?>
<?php include_once "partials/header.php"; ?>
<?php openBackgroundContainer(); ?>

<style>
.glass{background:rgba(255,255,255,.78);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);
border:1px solid rgba(255,255,255,.18);border-radius:16px;box-shadow:0 10px 30px rgba(31,38,135,.12)}
.container-xxl{padding-top:16px;padding-bottom:24px}
.q-card{padding:24px}
.badge-time,.badge-info-ghost{background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:10px;padding:.5rem .8rem;font-weight:600}
.ref-badge{background:#e9f3ff;color:#1273ea;border:1px solid #cfe6ff;font-weight:600;font-size:.85rem;margin-left:.5rem}
#qHost .q-item{display:none}
#qHost .q-item.is-visible{display:block}
.q-label{font-size:1.1rem;margin:8px 0 12px 0}
.quiz-form__ans{display:block;margin:12px 0;border:1px solid rgba(0,0,0,.08);border-radius:12px;background:#fff;padding:.9rem 1rem}
.quiz-form__ans input{margin-right:.6rem;transform:translateY(2px)}
.nav-footer .btn{min-width:160px;font-weight:600;border-radius:12px}
.nav-footer .btn-outline-secondary{background:#fff}
.sidebar{position:sticky;top:20px}
.q-grid{display:grid;grid-template-columns:repeat(10,1fr);gap:8px}
.q-dot{display:flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:50%;
border:2px solid #e33;color:#e33;font-weight:700;cursor:pointer;background:#fff;transition:transform .12s,background .12s,color .12s,border .12s}
.q-dot:hover{transform:translateY(-2px)}
.q-dot.done{background:#2ecc71;border-color:#2ecc71;color:#fff}
.q-dot.current{background:#1273ea;border-color:#1273ea;color:#fff}
.contImage{margin-top:12px;display:flex;justify-content:center;align-items:center;width:100%;overflow:hidden;border-radius:12px}
.contImage img{width:100%;height:auto;max-height:70vh;object-fit:contain;transition:transform .08s ease}
@media (min-width: 992px){.q-card .contImage{max-width:900px;margin:auto}}
.orient-hint{color:#6b7280;font-size:.9rem}
</style>

<div class="container-xxl">
  <form method="POST" action="" id="quizForm">
    <!-- Header -->
    <div class="glass p-3 mb-3 d-flex align-items-center justify-content-between">
      <div class="text-center flex-grow-1">
        <div class="h3 mb-1 fw-bolder">Évaluation Connaissance — Épreuve Figée</div>
        <div class="text-muted"><?php echo htmlspecialchars($titleUser); ?> — Level Mixed<?php echo $reviewMode?' — <span style="color:#374151">Lecture seule</span>':''; ?></div>
      </div>
      <div class="d-flex align-items-center gap-3">
        <div class="badge-time"><span>⏱️</span> <span id="timer_sec"><?php printf('%02d:%02d:%02d',$initHr,$initMn,$initSc); ?></span></div>
        <div class="badge-info-ghost">Questions restantes : <strong><span id="remainingCount">0</span></strong></div>
        <?php if(!$reviewMode): ?>
          <?php if($modeOrientationOnly): ?>
            <button type="submit" name="valid_orientation" class="btn btn-primary fw-bold">Valider mes préférences</button>
          <?php else: ?>
            <button type="submit" name="valid" class="btn btn-primary fw-bold">Valider</button>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($shouldFocusOrientation): ?>
      <div class="glass p-3 mb-3" style="border-left:4px solid #1273ea">
        <div class="fw-bold mb-1">QCM validé ✅ — complétez vos préférences</div>
        <div class="text-muted">Merci de répondre aux <strong>questions d’orientation</strong> (pages <?php echo $orientationStartIndex+1; ?> à <?php echo $orientationStartIndex+count($orientationDocs); ?>).</div>
        <button type="button" class="btn btn-sm btn-primary mt-2" id="goOrientation">Aller aux questions d’orientation</button>
      </div>
    <?php endif; ?>

    <div class="row g-4">
      <!-- Zone principale -->
      <div class="col-lg-8">

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
            ?>
            <div class="q-item" data-type="qcm">
              <input type="hidden" name="questionsTag[]" value="<?php echo htmlspecialchars($qid); ?>">
              <p class="quiz-form__question fw-bold q-label">
                <?php echo ($ansIndex+1).' - '.htmlspecialchars($label); ?>
                <?php if ($ref!==''): ?><span class="badge ref-badge"><?php echo htmlspecialchars($ref); ?></span><?php endif; ?>
              </p>
              <?php if ($img!==''): ?>
              <div class="contImage"><img alt="" src="../../public/files/<?php echo htmlspecialchars($img); ?>"></div>
              <?php endif; ?>

              <?php
                $name='answer'.$ansIndex;
              $opts = array_values(array_filter([$p1, $p2, $p3, $p4], function($x) { return $x !== ''; }));
                foreach ($opts as $opt):
                  $checked = ($prev === $opt) ? ' checked' : '';
                  $disabled = $lockQcm ? ' disabled' : '';
              ?>
                <label class="quiz-form__ans">
                  <input type="radio" onclick="checkedRadio()" name="<?php echo $name; ?>" value="<?php echo htmlspecialchars($opt); ?>"<?php echo $checked.$disabled; ?>>
                  <span class="text"><?php echo htmlspecialchars($opt); ?></span>
                </label>
              <?php endforeach; ?>
              <label class="quiz-form__ans" hidden>
                <input type="radio" onclick="checkedRadio()" name="<?php echo $name; ?>" value="null"<?php echo (!in_array($prev,$opts,true)?' checked':''); ?> <?php echo $lockQcm?'disabled':''; ?>>
                <span class="text"></span>
              </label>
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
              $num = $startIndex + $i + 1;
              $dis = $lockOrientation ? ' disabled' : '';
            ?>
            <div class="q-item" data-type="orient" data-min="<?php echo $minSel; ?>" data-max="<?php echo $maxSel; ?>">
              <p class="quiz-form__question fw-bold q-label">
                <?php echo $num.' - '.htmlspecialchars((string)$od['title']); ?>
              </p>
              <div class="orient-hint mb-2">Ces réponses ne sont pas notées. Elles orientent votre affectation.</div>
              <div class="orient-hint mb-3">
                <?php if ($minSel>0 && $maxSel>0): ?>
                  Choisir entre <strong><?php echo $minSel; ?></strong> et <strong><?php echo $maxSel; ?></strong> options.
                <?php elseif ($minSel>0 && $maxSel===0): ?>
                  Choisir au moins <strong><?php echo $minSel; ?></strong> option(s).
                <?php elseif ($maxSel>0): ?>
                  Choisir au plus <strong><?php echo $maxSel; ?></strong> option(s).
                <?php endif; ?>
              </div>
              <input type="hidden" name="orientationTag[]" value="<?php echo htmlspecialchars($oidStr); ?>">

              <?php foreach ($opts as $op): if(!is_string($op) || $op==='') continue;
                $checked = in_array($op, $prevCh, true) ? ' checked' : ''; ?>
                <label class="quiz-form__ans" style="margin:8px 0;">
                  <input type="checkbox" class="orient-opt" name="orient[<?php echo htmlspecialchars($oidStr); ?>][]" value="<?php echo htmlspecialchars($op); ?>"<?php echo $checked.$dis; ?>>
                  <span class="text"><?php echo htmlspecialchars($op); ?></span>
                </label>
              <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
          </div>

          <div class="d-flex justify-content-between align-items-center mt-3 nav-footer">
            <button type="button" class="btn btn-outline-secondary" id="btnPrev">‹ Précédent</button>
            <div class="d-flex gap-2">
              <?php if(!$reviewMode): ?>
                <button type="submit" class="btn btn-secondary" name="save">Sauvegarder</button>
                <?php if($modeOrientationOnly): ?>
                  <button type="submit" class="btn btn-primary" name="valid_orientation">Valider mes préférences ›</button>
                <?php else: ?>
                  <button type="button" class="btn btn-primary" id="btnNext">Suivant ›</button>
                <?php endif; ?>
              <?php else: ?>
                <a class="btn btn-secondary" href="./candidatAccueil.php">Retour</a>
              <?php endif; ?>
            </div>
          </div>

          <?php if (!empty($error_msg)): ?>
            <div class="alert alert-warning mt-3 mb-0" role="alert"><?php echo htmlspecialchars($error_msg); ?></div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Sidebar -->
      <div class="col-lg-4">
        <div class="glass p-3 sidebar">
          <div class="d-flex align-items-center justify-content-between">
            <div class="fw-bold">QUESTIONS & STATISTIQUES</div>
            <div class="text-muted small">Total : <span id="totalCount">0</span></div>
          </div>
          <hr class="my-2">
          <div id="qGrid" class="q-grid"></div>
          <hr class="my-3">
          <!-- Répartition par spécialité masquée ici -->
        </div>
      </div>
    </div>
  </form>
</div>

<script>
// Zoom image (idem)
document.querySelectorAll('.contImage').forEach((c)=>{
  const img=c.querySelector('img'); if(!img) return;
  c.addEventListener('mousemove',(e)=>{
    const r=c.getBoundingClientRect();
    const x=((e.clientX-r.left)/c.clientWidth)*100, y=((e.clientY-r.top)/c.clientHeight)*100;
    img.style.transformOrigin=`${x}% ${y}%`; img.style.transform='scale(1.2)';
  });
  c.addEventListener('mouseleave',()=>{ img.style.transformOrigin='center center'; img.style.transform='scale(1)'; });
});

// Timer (si revue: on fige). À 0 => timeup=1 + submit auto
const isReview = <?php echo $reviewMode ? 'true' : 'false'; ?>;
let hr = <?php echo (int)$initHr; ?>, mn = <?php echo (int)$initMn; ?>, sc = <?php echo (int)$initSc; ?>;
let time = (Number(hr)*3600)+(Number(mn)*60)+Number(sc);
const countDown=document.getElementById('timer_sec');
const formEl=document.getElementById('quizForm');
const timeupEl=document.getElementById('timeup');
let submittedOnTimeout=false;

function tick(){
  const H=Math.max(0,Math.floor(time/3600));
  const M=Math.max(0,Math.floor((time/60)-(H*60)));
  const S=Math.max(0,time-((H*3600)+(M*60)));
  const HH=(H<10?'0':'')+H, MM=(M<10?'0':'')+M, SS=(S<10?'0':'')+S;
  countDown.textContent=`${HH}:${MM}:${SS}`;
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

// Navigation 1/1 + grille (QCM + Orientation)
const items=Array.from(document.querySelectorAll('#qHost .q-item'));
const btnPrev=document.getElementById('btnPrev'), btnNext=document.getElementById('btnNext');
const totalEl=document.getElementById('totalCount'), remainEl=document.getElementById('remainingCount');
const gridEl=document.getElementById('qGrid');
let idx=0;

function show(i){
  if(!items.length) return;
  idx=Math.max(0,Math.min(i,items.length-1));
  items.forEach(el=>el.classList.remove('is-visible'));
  items[idx].classList.add('is-visible');
  btnPrev.disabled=(idx===0);
  if (btnNext) btnNext.textContent=(idx===items.length-1)?'Dernière ›':'Suivant ›';
  updateTotals(); updateGridStates(); markCurrentDot();
}
function answeredCount(){
  let c=0;
  for(let i=0;i<items.length;i++){
    const it=items[i];
    const kind=it.dataset.type||'qcm';
    if (kind==='qcm'){
      const checked=formEl.querySelector(`input[name="answer${i}"]:checked`);
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
  totalEl.textContent=items.length;
  remainEl.textContent=Math.max(0,items.length-answeredCount());
}
function buildGrid(){
  gridEl.innerHTML='';
  for(let i=0;i<items.length;i++){
    const b=document.createElement('button');
    b.type='button'; b.className='q-dot'; b.textContent=(i+1);
    b.addEventListener('click',()=>show(i));
    gridEl.appendChild(b);
  }
}
function updateGridStates(){
  const dots=Array.from(gridEl.querySelectorAll('.q-dot'));
  dots.forEach((d,i)=>{
    const it=items[i];
    const kind=it.dataset.type||'qcm';
    let done=false;
    if (kind==='qcm'){
      const checked=formEl.querySelector(`input[name="answer${i}"]:checked`);
      done = !!checked && checked.value!=='null';
    } else {
      const min=Number(it.dataset.min||'0');
      const chk=it.querySelectorAll('input.orient-opt:checked').length;
      done = (chk>0 && (min===0 || chk>=min));
    }
    d.classList.toggle('done', done);
  });
}
function markCurrentDot(){ Array.from(gridEl.querySelectorAll('.q-dot')).forEach((d,i)=>d.classList.toggle('current', i===idx)); }
btnPrev.addEventListener('click',()=>show(idx-1));
if (btnNext) btnNext.addEventListener('click',()=>show(idx+1));
function checkedRadio(){ updateTotals(); updateGridStates(); } window.checkedRadio=checkedRadio;

// Démarrage : aller directement sur l'orientation si demandé
const orientationStartIndex = <?php echo (int)$orientationStartIndex; ?>;
const startIndex = <?php echo (int)($shouldFocusOrientation ? $orientationStartIndex : 0); ?>;
buildGrid(); show(startIndex);
// Bouton de raccourci vers orientation
const goBtn = document.getElementById('goOrientation');
if (goBtn) goBtn.addEventListener('click', ()=> show(orientationStartIndex));
</script>

<?php closeBackgroundContainer(); ?>
<?php include_once "partials/footer.php"; ?>
