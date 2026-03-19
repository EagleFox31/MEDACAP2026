<?php
/* -----------------------------------------------------------------
 *  INITIALISATION (session, Mongo, helpers)
 * -----------------------------------------------------------------*/
session_start();
include_once "../language.php";
include_once "getValidatedResults.php";
include_once "score_decla.php"; // Inclusion du fichier pour les scores déclaratifs
include_once "score_fact.php";   // <- renvoie [techId][questionId] => 'Maîtrisé' / 'Non maîtrisé'
include_once "../userFilters.php";        // <- contient filterUsersByProfile()
include_once "../partials/background-manager.php"; // Système de gestion de fond d'écran

if (!isset($_SESSION['profile'])) {
    header('Location: ../../');
    exit();
}
require_once "../../vendor/autoload.php";
$mongo = new MongoDB\Client("mongodb://localhost:27017");
$db    = $mongo->academy;

/* -----------------------------------------------------------------
 *  CONSTANTES & PETITES FONCTIONS
 * -----------------------------------------------------------------*/
const TECH_PROFILES = ['Technicien', 'Manager'];   // + Manager testé
const LEVELS        = ['Junior', 'Senior', 'Expert'];

function pct(array $scores, array $totals): int
{
    $tot = array_sum($totals) ?: 1;
    return (int) round(array_sum($scores) * 100 / $tot);
}

/** Retourne true si l’utilisateur possède une allocation Factuel + Déclaratif actives */
function hasFullAllocation($allocCol, $userId, string $level): bool
{
    $uid = new MongoDB\BSON\ObjectId($userId);
    $fac = $allocCol->count([
        'user' => $uid,
        'level' => $level,
        'type' => 'Factuel',
        'active' => true
    ]);
    $dec = $allocCol->count([
        'user' => $uid,
        'level' => $level,
        'type' => 'Declaratif',
        'active' => true,
        'activeManager' => true
    ]);
    return $fac && $dec;
}

/**
 * Vrai si les deux allocations (Factuel et Déclaratif) existent
 * quelle que soit leur activation.
 */
function hasAllocationPair($allocCol, $userId, string $level): bool
{
    $uid = new MongoDB\BSON\ObjectId($userId);
    $fac = $allocCol->count([
        'user'  => $uid,
        'level' => $level,
        'type'  => 'Factuel'
    ]);
    $dec = $allocCol->count([
        'user'  => $uid,
        'level' => $level,
        'type'  => 'Declaratif'
    ]);
    return $fac && $dec;
}





/* ---------- fonctions manquantes restaurées --------------------- */

/**
 * Calcule, pour chaque question déclarative d’un niveau,
 * combien de techniciens la maîtrisent et agrège les stats.
 */
function calculateQuestionMasteryStats(
    MongoDB\Database $db,
    string           $level,
    ?string          $country,
    array            $validatedResults,
    ?string          $agency = null
): array
{
    /* toutes les questions déclaratives actives du niveau */
    $questions = $db->questions->find([
        'type'   => 'Declarative',
        'level'  => $level,
        'active' => true
    ])->toArray();

    /* techniciens concernés selon le profil courant + filtres pays/agence */
    $techs = filterUsersByProfile($db, $_SESSION['profile'], $country, $level, $agency);

    $non = $one = $two = 0;
    foreach ($questions as $q) {
        $qid  = (string) $q['_id'];
        $hits = 0;
        foreach ($techs as $t) {
            $tid = (string) $t['_id'];
            if (($validatedResults[$tid][$qid] ?? 'Non maîtrisé') === 'Maîtrisé') {
                if (++$hits === 2) {           // on s’arrête de compter à 2
                    break;
                }
            }
        }
        if     ($hits === 0) $non++;
        elseif ($hits === 1) $one++;
        else                 $two++;
    }

    $total = count($questions);
    return [
        'totalQuestions' => $total,
        'nonMaitrise'    => $non,
        'singleMaitrise' => $one,
        'doubleMaitrise' => $two,
        'othersCount'    => $total - ($non + $one + $two)
    ];
}

/** Transforme les counts précédents en pourcentages arrondis */
function calculatePercentages(array $stats): array
{
    $t = $stats['totalQuestions'] ?: 1;
    $p1 = round($stats['nonMaitrise']    * 100 / $t);
    $p2 = round($stats['singleMaitrise'] * 100 / $t);
    $p3 = round($stats['doubleMaitrise'] * 100 / $t);
    return [
        'nonMaitrise'    => $p1,
        'singleMaitrise' => $p2,
        'doubleMaitrise' => $p3,
        'others'         => 100 - ($p1 + $p2 + $p3)
    ];
}

/* -----------------------------------------------------------------
 *  DÉTERMINATION DE LA PORTÉE (groupe / filiale)
 * -----------------------------------------------------------------*/
$scope      = in_array($_SESSION['profile'], ['Directeur Groupe', 'Super Admin']) ? 'groupe' : 'filiale';
$subsidiary = $_SESSION['subsidiary'] ?? null;   // utilisé si portée = filiale

/* -----------------------------------------------------------------
 *  REQUÊTES COMMUNES
 * -----------------------------------------------------------------*/
$users       = $db->users;
$allocations = $db->allocations;
$results     = $db->results;
$connections = $db->connections;

$baseTechMgr = [
    'profile' => ['$in' => TECH_PROFILES],
    'active'  => true,
    '$or'     => [
        ['profile' => 'Technicien'],
        ['profile' => 'Manager', 'test' => true]
    ]
];
if ($scope === 'filiale') {
    $baseTechMgr['subsidiary'] = $subsidiary;
}

/* -----------------------------------------------------------------
 *  EFFECTIFS (techniciens+managers) par niveau + total
 * -----------------------------------------------------------------*/
foreach (LEVELS as $lvl) {
    $filter               = $baseTechMgr + ['level' => $lvl];
    ${"countUsers$lvl"}    = $users->count($filter);     // ex: $countUsersJunior
}
$countUsersTotal = $users->count($baseTechMgr);          // total tous niveaux

/* -----------------------------------------------------------------
 *  SCORES (Factuel et Déclaratif) par niveau
 * -----------------------------------------------------------------*/
foreach (LEVELS as $lvl) {
    $matchFac = ['level' => $lvl, 'typeR' => 'Technicien', 'type' => 'Factuel'];
    $matchDec = ['level' => $lvl, 'typeR' => 'Technicien - Manager', 'type' => 'Declaratif'];
    if ($scope === 'filiale') {
        $matchFac['subsidiary'] = $matchDec['subsidiary'] = $subsidiary;
    }

    $curFac = $results->aggregate([
        ['$match' => $matchFac],
        ['$group' => ['_id' => null, 's' => ['$sum' => '$score'], 't' => ['$sum' => '$total']]]
    ])->toArray();
    $curDec = $results->aggregate([
        ['$match' => $matchDec],
        ['$group' => ['_id' => null, 's' => ['$sum' => '$score'], 't' => ['$sum' => '$total']]]
    ])->toArray();

    ${"pctFac$lvl"}   = empty($curFac) ? 0 : pct([$curFac[0]['s']], [$curFac[0]['t']]);
    ${"pctDecla$lvl"} = empty($curDec) ? 0 : pct([$curDec[0]['s']], [$curDec[0]['t']]);
}

/* -----------------------------------------------------------------
 *  TESTS RÉALISÉS / ALLOCATIONS COMPLÈTES
 * -----------------------------------------------------------------*/
$completedTestsByLevel = [];
$idsAllTechMgr         = $users->distinct('_id', $baseTechMgr);

foreach (LEVELS as $lvl) {
    $done = 0;
    foreach ($idsAllTechMgr as $id) {
        if (hasFullAllocation($allocations, $id, $lvl)) {
            $done++;
        }
    }
    $completedTestsByLevel[$lvl] = $done;
}

/* -----------------------------------------------------------------
 *  ===  NOUVEAU : INDICATEURS SAVOIR / SAVOIR-FAIRE  ===============
 *  (ex-$percentageSavoir, $percentageMaSavoirFaire, $percentageTech…)
 * -----------------------------------------------------------------*/
$countSavoir            = 0;   // Factuel active
$countMaSavoirFaire     = 0;   // Declaratif activeManager == true
$countTechSavoirFaire   = 0;   // Declaratif active (technicien)
foreach ($idsAllTechMgr as $id) {
    foreach (LEVELS as $lvl) {
        /* allocation Factuel */
        $fac = $allocations->findOne([
            'user'   => $id,
            'level'  => $lvl,
            'type'   => 'Factuel',
            'active' => true
        ]);
        if ($fac) {
            $countSavoir++;
        }

        /* allocation Déclaratif */
        $dec = $allocations->findOne([
            'user'   => $id,
            'level'  => $lvl,
            'type'   => 'Declaratif'
        ]);
        if ($dec && !empty($dec['active'])) {
            $countTechSavoirFaire++;
            if (!empty($dec['activeManager'])) {
                $countMaSavoirFaire++;
            }
        }
    }
}
$denominatorAlloc = $countUsersJunior + $countUsersSenior + $countUsersExpert * 2; // même règle que l’ancien code
$percentageSavoir          = (int) ceil($countSavoir          * 100 / max(1, $denominatorAlloc));
$percentageMaSavoirFaire   = (int) ceil($countMaSavoirFaire   * 100 / max(1, $denominatorAlloc));
$percentageTechSavoirFaire = (int) ceil($countTechSavoirFaire * 100 / max(1, $denominatorAlloc));

/* -----------------------------------------------------------------
 *  ADMIN / MANAGER / DIRECTEURS – comptages simples
 * -----------------------------------------------------------------*/
$countManagers          = $users->count(['profile' => 'Manager', 'active' => true]);
$countAdmins            = $users->count(['profile' => 'Admin', 'active' => true]);
$countDPS               = $users->count(['profile' => 'Directeur Pièce et Service', 'active' => true]);
$countDOP               = $users->count(['profile' => 'Directeur des Opérations', 'active' => true]);
$countDirecteurFiliales = $countDPS + $countDOP;
$countDirecteurGroupes  = $users->count(['profile' => 'Directeur Groupe', 'active' => true]);

/* -----------------------------------------------------------------
 *  UTILISATEURS EN LIGNE
 * -----------------------------------------------------------------*/
$countOnlineUsers = $connections->count(['status' => 'Online', 'active' => true]);

/* -----------------------------------------------------------------
 *  STATISTIQUES MAÎTRISE DES QUESTIONS (déclaratif)
 * -----------------------------------------------------------------*/
$country   = $_GET['country'] ?? $_SESSION['country'] ?? null;
$agencySel = $_GET['agency']  ?? null;
$selectedCountry = $country;     // compatibilité ancien JS
$selectedAgency  = $agencySel;


$allStats = [];
foreach (LEVELS as $lvl) {
    $validated      = getValidatedResults($lvl);
    $statsLvl       = calculateQuestionMasteryStats($db, $lvl, $country, $validated, $agencySel);
    $allStats[$lvl] = $statsLvl;
    ${"pct$lvl"}    = calculatePercentages($statsLvl);   // $pctJunior, $pctSenior, $pctExpert
}



/* ---- total global ------------------------------------------------*/
$statsTot = [
    'totalQuestions' => array_sum(array_column($allStats, 'totalQuestions')),
    'nonMaitrise'    => array_sum(array_column($allStats, 'nonMaitrise')),
    'singleMaitrise' => array_sum(array_column($allStats, 'singleMaitrise')),
    'doubleMaitrise' => array_sum(array_column($allStats, 'doubleMaitrise')),
];
$statsTot['othersCount'] = $statsTot['totalQuestions']
    - ($statsTot['nonMaitrise'] + $statsTot['singleMaitrise'] + $statsTot['doubleMaitrise']);
$pctTotal = calculatePercentages($statsTot);
$percentagesJunior  = $pctJunior;
$percentagesSenior  = $pctSenior;
$percentagesExpert  = $pctExpert;
$percentagesTotal   = $pctTotal;
$statsJunior = $allStats['Junior'];
$statsSenior = $allStats['Senior'];
$statsExpert = $allStats['Expert'];
$statsTotal  = $statsTot;
/* -----------------------------------------------------------------
 *  TESTS (allocations) : listes compatibles avec l’ancien JS
 * -----------------------------------------------------------------*/

 $testsUserJu = $testsUserSe = $testsUserEx =
 $testsTotalJu = $testsTotalSe = $testsTotalEx = [];
 
 foreach (LEVELS as $lvl) {
     // filtre commun (groupe ou filiale selon $scope)
     $filter = $baseTechMgr + ['level' => $lvl];
     $ids    = $users->distinct('_id', $filter);
 
     foreach ($ids as $id) {
         // Allocations actives -> $testsUserXX
         if (hasFullAllocation($allocations, $id, $lvl)) {
             ${'testsUser'.substr($lvl,0,2)}[] = $id;   // Ju / Se / Ex
         }
         // Allocations existantes -> $testsTotalXX
         if (hasAllocationPair($allocations, $id, $lvl)) {
             ${'testsTotal'.substr($lvl,0,2)}[] = $id;
         }
     }
 }
/* -----------------------------------------------------------------
 *  EMBARQUER LES DONNÉES POUR JS (une seule variable globale)
 * -----------------------------------------------------------------*/
$jsData = [
    'scope'       => $scope,
    'subsidiary'  => $subsidiary,
    'online'      => $countOnlineUsers,

    /* effectifs */
    'counts'      => [
        'Junior' => $countUsersJunior,
        'Senior' => $countUsersSenior,
        'Expert' => $countUsersExpert,
        'Total'  => $countUsersTotal
    ],

    /* scores */
    'scoresFac'   => [
        'Junior' => $pctFacJunior,
        'Senior' => $pctFacSenior,
        'Expert' => $pctFacExpert
    ],
    'scoresDecla' => [
        'Junior' => $pctDeclaJunior,
        'Senior' => $pctDeclaSenior,
        'Expert' => $pctDeclaExpert
    ],

    /* tests */
    'testsDone'   => $completedTestsByLevel,          // ['Junior'=>…, …]

    /* couverture Savoir / Savoir-faire */
    'coverage'    => [
        'savoir'          => $percentageSavoir,
        'maSavoirFaire'   => $percentageMaSavoirFaire,
        'techSavoirFaire' => $percentageTechSavoirFaire
    ],

    /* maîtrise déclaratif */
    'mastery'     => [
        'Junior' => $pctJunior,
        'Senior' => $pctSenior,
        'Expert' => $pctExpert,
        'Total'  => $pctTotal
    ],
];
/* -----------------------------------------------------------------
 *  1) LISTES PAR NIVEAU
 * -----------------------------------------------------------------*/
$techIdsByLevel = [];
foreach (LEVELS as $lvl) {
    $techIdsByLevel[$lvl] = $users->distinct(
        '_id',
        $baseTechMgr + ['level' => $lvl]    // même filtre que plus haut
    );
}

$countUsersJunior = count($techIdsByLevel['Junior']);
$countUsersSenior = count($techIdsByLevel['Senior']);
$countUsersExpert = count($techIdsByLevel['Expert']);

/* -----------------------------------------------------------------
 *  2) FONCTION utilitaire (allocations actives ET validées manager)
 * -----------------------------------------------------------------*/
function hasFullAllocationActive($allocCol, $uid, string $level): bool
{
    $id = new MongoDB\BSON\ObjectId($uid);
    $fac = $allocCol->count([
        'user'   => $id,
        'level'  => $level,
        'type'   => 'Factuel',
        'active' => true
    ]);
    $dec = $allocCol->count([
        'user'          => $id,
        'level'         => $level,
        'type'          => 'Declaratif',
        'active'        => true,
        'activeManager' => true
    ]);
    return $fac && $dec;
}

/* -----------------------------------------------------------------
 *  3) TABLEAUX    $doneTestJuTj / SeTs / ExTx
 * -----------------------------------------------------------------*/
$doneTestJuTj = $doneTestSeTs = $doneTestExTx = [];

foreach ($techIdsByLevel['Junior'] as $uid) {
    if (hasFullAllocationActive($allocations, $uid, 'Junior')) $doneTestJuTj[] = $uid;
}
foreach ($techIdsByLevel['Senior'] as $uid) {
    if (hasFullAllocationActive($allocations, $uid, 'Senior')) $doneTestSeTs[] = $uid;
}
foreach ($techIdsByLevel['Expert'] as $uid) {
    if (hasFullAllocationActive($allocations, $uid, 'Expert')) $doneTestExTx[] = $uid;
}

/* -----------------------------------------------------------------
 *  4) POURCENTAGES DE COMPÉTENCE
 *      (Factuel + Déclaratif) pour chaque sous-population
 * -----------------------------------------------------------------*/
function averageSkillPct(
    MongoDB\Collection $resCol,
    array              $userIds,
    string             $level,
    string             $typeR,
    string             $type
): array {
    $scores = $totals = [];
    foreach ($userIds as $uid) {
        $r = $resCol->findOne([
            'user'  => new MongoDB\BSON\ObjectId($uid),
            'level' => $level,
            'typeR' => $typeR,
            'type'  => $type
        ]);
        if ($r) {
            $scores[] = $r['score'];
            $totals[] = $r['total'];
        }
    }
    $pct = empty($totals) ? 0 : (int) round(array_sum($scores) * 100 / max(1, array_sum($totals)));
    return [$pct, $scores, $totals];    // on renvoie aussi les tableaux si besoin
}

/* Junior ----------------------------------------------------------*/
[$percentageFacJuTj]   = averageSkillPct($results, $techIdsByLevel['Junior'],
                                        'Junior', 'Technicien', 'Factuel');
[$percentageDeclaJuTj] = averageSkillPct($results, $techIdsByLevel['Junior'],
                                        'Junior', 'Technicien - Manager', 'Declaratif');

/* Senior ----------------------------------------------------------*/
[$percentageFacSeTs]   = averageSkillPct($results, $techIdsByLevel['Senior'],
                                        'Senior', 'Technicien', 'Factuel');
[$percentageDeclaSeTs] = averageSkillPct($results, $techIdsByLevel['Senior'],
                                        'Senior', 'Technicien - Manager', 'Declaratif');

/* Expert ----------------------------------------------------------*/
[$percentageFacEx]     = averageSkillPct($results, $techIdsByLevel['Expert'],
                                        'Expert', 'Technicien', 'Factuel');
[$percentageDeclaEx]   = averageSkillPct($results, $techIdsByLevel['Expert'],
                                        'Expert', 'Technicien - Manager', 'Declaratif');
/* ——— Techniciens Senior – compétences JUNIOR ——— */
[$percentageFacJuTs]   = averageSkillPct($results, $techIdsByLevel['Senior'],
                                        'Junior', 'Technicien', 'Factuel');
[$percentageDeclaJuTs] = averageSkillPct($results, $techIdsByLevel['Senior'],
                                        'Junior', 'Technicien - Manager', 'Declaratif');

/* ——— Techniciens Expert – compétences JUNIOR ——— */
[$percentageFacJuTx]   = averageSkillPct($results, $techIdsByLevel['Expert'],
                                        'Junior', 'Technicien', 'Factuel');
[$percentageDeclaJuTx] = averageSkillPct($results, $techIdsByLevel['Expert'],
                                        'Junior', 'Technicien - Manager', 'Declaratif');

/* ——— Techniciens Expert – compétences SENIOR ——— */
[$percentageFacSeTx]   = averageSkillPct($results, $techIdsByLevel['Expert'],
                                        'Senior', 'Technicien', 'Factuel');
[$percentageDeclaSeTx] = averageSkillPct($results, $techIdsByLevel['Expert'],
                                        'Senior', 'Technicien - Manager', 'Declaratif');

/* -------------------------------------------------------------
 *  Cross-level FILIALE uniquement  (variables suffixées …F)
 * ------------------------------------------------------------*/
if ($scope === 'filiale') {

    [$percentageFacJuTsF]   = averageSkillPct($results, $techniciansSe,
                                              'Junior','Technicien','Factuel');
    [$percentageDeclaJuTsF] = averageSkillPct($results, $techniciansSe,
                                              'Junior','Technicien - Manager','Declaratif');

    [$percentageFacJuTxF]   = averageSkillPct($results, $techniciansEx,
                                              'Junior','Technicien','Factuel');
    [$percentageDeclaJuTxF] = averageSkillPct($results, $techniciansEx,
                                              'Junior','Technicien - Manager','Declaratif');

    [$percentageFacSeTxF]   = averageSkillPct($results, $techniciansEx,
                                              'Senior','Technicien','Factuel');
    [$percentageDeclaSeTxF] = averageSkillPct($results, $techniciansEx,
                                              'Senior','Technicien - Manager','Declaratif');
}

/* -----------------------------------------------------------------
 *  5) EXPORT FACULTATIF DANS window.APP_DATA
 * -----------------------------------------------------------------*/
$jsData['skillAverages'] = [
    'Junior' => [$percentageFacJuTj, $percentageDeclaJuTj],
    'Senior' => [$percentageFacSeTs, $percentageDeclaSeTs],
    'Expert' => [$percentageFacEx,   $percentageDeclaEx]
];

/* -----------------------------------------------------------------
 *  FONCTIONS D’AIDE : % de maîtrise d’un technicien pour un niveau
 * -----------------------------------------------------------------*/
/**
 * Retourne, pour chaque technicien, son pourcentage de questions
 * validées (Maîtrisé) sur l’ensemble des questions du niveau.
 * Renvoie  [techId] => pct (0-100)
 */
function getTechnicianResultsPct(MongoDB\Database $db, string $level): array
{
    // questions déclaratives actives du niveau
    $qIds = $db->questions->distinct(
        '_id',
        ['type' => 'Declarative', 'level' => $level, 'active' => true]
    );
    $totalQ = count($qIds) ?: 1;

    // résultats validés stockés par votre helper
    $validated = getValidatedResults($level);   // [techId][questionId] => 'Maîtrisé'

    $out = [];
    foreach ($validated as $techId => $answers) {
        $ok = 0;
        foreach ($qIds as $qid) {
            if (($answers[(string)$qid] ?? 'Non maîtrisé') === 'Maîtrisé') $ok++;
        }
        $out[$techId] = (int) round($ok * 100 / $totalQ);
    }
    return $out;  // tableau % par technicien
}

/* -----------------------------------------------------------------
 *  TABLEAUX  déclaration / factuel
 * -----------------------------------------------------------------*/
$averageMasteryByLevel     = [];  // déclaratif
$totalTechniciansByLevel   = [];
$technicianCountsByLevel   = [];

$averageMasteryByLevelF    = [];  // factuel
$totalTechniciansByLevelF  = [];
$technicianCountsByLevelF  = [];

foreach (LEVELS as $lvl) {

    /* ------------ Déclaratif ------------------------------------*/
    $techDecl = filterUsersByProfile($db, $_SESSION['profile'],
                                     $country, $lvl, $agencySel);
    $totalTechniciansByLevel[$lvl] = count($techDecl);

    $pctByTech = getTechnicianResultsPct($db, $lvl);   // helper ci-dessus
    $sum = $cnt = 0;
    foreach ($techDecl as $t) {
        $tid = (string) $t['_id'];
        if (isset($pctByTech[$tid])) {
            $sum += $pctByTech[$tid];
            $cnt++;
        }
    }
    $technicianCountsByLevel[$lvl] = $cnt;
    $averageMasteryByLevel[$lvl]   = $cnt ? (int) round($sum / $cnt) : 0;

    /* ------------ Factuel ---------------------------------------*/
    // mêmes techniciens mais fonction helper différente (factuelle)
    $techFact = $techDecl;                    // même sélection d’utilisateurs
    $totalTechniciansByLevelF[$lvl] = count($techFact);

    $pctByTechF = getTechnicianResults3($lvl); // <- votre helper factuel existant
    $sumF = $cntF = 0;
    foreach ($techFact as $t) {
        $tid = (string) $t['_id'];
        if (isset($pctByTechF[$tid])) {
            $sumF += $pctByTechF[$tid];
            $cntF++;
        }
    }
    $technicianCountsByLevelF[$lvl] = $cntF;
    $averageMasteryByLevelF[$lvl]   = $cntF ? (int) round($sumF / $cntF) : 0;
}

/* -------------------- totaux « Total » ---------------------------*/
$totalTechniciansByLevel['Total']      = array_sum($totalTechniciansByLevel);
$technicianCountsByLevel['Total']      = array_sum($technicianCountsByLevel);
$averageMasteryByLevel['Total']        = array_sum($averageMasteryByLevel) / (count(LEVELS) ?: 1);

$totalTechniciansByLevelF['Total']     = array_sum($totalTechniciansByLevelF);
$technicianCountsByLevelF['Total']     = array_sum($technicianCountsByLevelF);
$averageMasteryByLevelF['Total']       = array_sum($averageMasteryByLevelF) / (count(LEVELS) ?: 1);

/* -----------------------------------------------------------------
 *  (Optionnel) : embarquer aussi dans window.APP_DATA
 * -----------------------------------------------------------------*/
$jsData['tpMastery']       = $averageMasteryByLevel;
$jsData['tpTechTotals']    = $totalTechniciansByLevel;
$jsData['tpTechCounts']    = $technicianCountsByLevel;

$jsData['factMastery']     = $averageMasteryByLevelF;
$jsData['factTechTotals']  = $totalTechniciansByLevelF;
$jsData['factTechCounts']  = $technicianCountsByLevelF;

/* -----------------------------------------------------------------
 *  SYNTHÈSE POUR LES TABLEAUX JS  (variables “count…” et “tests…”)
 * -----------------------------------------------------------------*/

/* -- Connaissances (alloc Factuel actives) -----------------------*/
$countSavoirJu = isset($countSavoirJu) ? count($countSavoirJu) : 0;
$countSavoirSe = isset($countSavoirSe) ? count($countSavoirSe) : 0;
$countSavoirEx = isset($countSavoirEx) ? count($countSavoirEx) : 0;

/* -- Savoir-faire validé manager (Déclaratif actif + manager) ----*/
$countSavFaiJu = isset($countSavFaiJu) ? count($countSavFaiJu) : 0;
$countSavFaiSe = isset($countSavFaiSe) ? count($countSavFaiSe) : 0;
$countSavFaiEx = isset($countSavFaiEx) ? count($countSavFaiEx) : 0;

/* -- Progression tests  -----------------------------------------*/
$testsUserJu   = isset($testsUserJu)   ? count($testsUserJu)   : 0;
$testsUserSe   = isset($testsUserSe)   ? count($testsUserSe)   : 0;
$testsUserEx   = isset($testsUserEx)   ? count($testsUserEx)   : 0;

$testsTotalJu  = isset($testsTotalJu)  ? count($testsTotalJu)  : 0;
$testsTotalSe  = isset($testsTotalSe)  ? count($testsTotalSe)  : 0;
$testsTotalEx  = isset($testsTotalEx)  ? count($testsTotalEx)  : 0;

/* -- Agrégats pour la ligne « averageScoreQ » -------------------*/
$countUsersTotal = $countUsersJunior + $countUsersSenior + $countUsersExpert;

/* -----------------------------------------------------------------
 *  FIN DES AJOUTS – les variables sont prêtes pour le script JS
 * -----------------------------------------------------------------*/


echo '<script>window.APP_DATA = ' . json_encode($jsData) . ';</script>';



/* -----------------------------------------------------------------
 *  RENDU HTML
 * -----------------------------------------------------------------*/
include "./partials/header.php";

// Définir le fond d'écran pour cette page
setPageBackground('bg-dashboard', true);
?>

<style>
        /* Hide dropdown content by default */
        .dropdown-content2 {
            display: none;
            margin-top: 15px;
            /* Reduced margin for a tighter look */
            padding: 5px;
            /* Add some padding for better spacing */
            background-color: #f9f9f9;
            /* Light background for dropdown content */
            border-radius: 8px;
            /* Rounded corners for dropdown content */
            transition: max-height 0.3s ease, opacity 0.3s ease;
            /* Smooth transitions */
            opacity: 0;
            max-height: 0;
            overflow: hidden;
        }

        /* Style the toggle button */
        .dropdown-toggle2 {
            background-color: #fff;
            /* Button background */
            color: gray;
            /* Button text color */
            padding: 10px 15px !important;
            /* Reduced padding for a more compact button */
            cursor: pointer;
            display: flex;
            align-items: center;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            transition: background-color 0.3s ease, color 0.3s ease, box-shadow 0.3s ease;
            /* Smooth transitions */
            border: none;
            /* No border */
        }

        /* Style for the icon next to the button text */
        .dropdown-toggle2 i {
            margin-left: 10px;
            /* More space between text and icon */
            font-size: 16px;
            /* Proper icon size */
            transition: transform 0.3s ease;
            /* Smooth rotation transition */
        }

        /* Rotate icon when the dropdown is open */
        .dropdown-toggle2.open i {
            transform: rotate(180deg);
        }

        /* Button hover effect */
        .dropdown-toggle2:hover {
            background-color: #f1f1f1;
            /* Slightly darker background on hover */
            color: #333;
            /* Slightly darker text color on hover for better contrast */
        }

        /* Optional: Style for better visibility */
        .title-and-cards-container2 {
            margin-bottom: 25px;
            /* Adjust as needed */
        }

        /* Hide dropdown content by default */
        .dropdown-content {
            display: none;
            margin-top: 25px;
            /* Adjust as needed */
            transition: opacity 0.3s ease, max-height 0.3s ease;
            /* Smooth transition for dropdown visibility */
        }

        /* Style the toggle button */
        .dropdown-toggle {
            background-color: #fff;
            color: white;
            border: none;
            padding: 10px 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            transition: background-color 0.3s ease, color 0.3s ease;
            /* Smooth transition for background and text color */

        }

        .dropdown-toggle i {
            margin-left: 5px;
            font-size: 14px;
            /* Set a proper size for the icon */
            transition: transform 0.3s ease;
            /* Smooth rotation transition */
        }


        /* Ensure no extra content or pseudo-elements */
        .dropdown-toggle::before,
        .dropdown-toggle::after {
            content: none;
            /* Ensure no extra content or pseudo-elements */
        }

        .dropdown-toggle.open i {
            transform: rotate(180deg);
        }

        /* Optional: Style for better visibility */
        .title-and-cards-container {
            margin-bottom: 25px;
            /* Adjust as needed */
        }

        .dropdown-toggle:hover {
            background-color: #f0f0f0;
            /* Slightly darker background on hover */
            color: #333;
            /* Slightly darker text color on hover for better contrast */
        }

        /* Hide dropdown content by default */
        .dropdown-content1 {
            display: none;
            margin-top: 25px;
            /* Adjust as needed */
            transition: opacity 0.3s ease, max-height 0.3s ease;
            /* Smooth transition for dropdown visibility */
        }

        /* Style the toggle button */
        .dropdown-toggle1 {
            background-color: #fff;
            color: white;
            border: none;
            padding: 10px 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            transition: background-color 0.3s ease, color 0.3s ease;
            /* Smooth transition for background and text color */

        }

        .dropdown-toggle1 i {
            margin-left: 5px;
            font-size: 14px;
            /* Set a proper size for the icon */
            transition: transform 0.3s ease;
            /* Smooth rotation transition */
        }


        /* Ensure no extra content or pseudo-elements */
        .dropdown-toggle1::before,
        .dropdown-toggle1::after {
            content: none;
            /* Ensure no extra content or pseudo-elements */
        }

        .dropdown-toggle1.open i {
            transform: rotate(180deg);
        }

        /* Optional: Style for better visibility */
        .title-and-cards-container {
            margin-bottom: 25px;
            /* Adjust as needed */
        }

        .dropdown-toggle1:hover {
            background-color: #f0f0f0;
            /* Slightly darker background on hover */
            color: #333;
            /* Slightly darker text color on hover for better contrast */
        }

        /* Optional: Style for better visibility */
        .title-and-cards-container {
            margin-bottom: 20px;
            /* Adjust as needed */
        }

        .card {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        /* Container for the card */
        .responsive-card {
            max-width: 100%;
            margin: 0 auto;
            padding: 1rem;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            background-color: #fff;
        }

        /* Card body */
        .responsive-card-body {
            display: flex;
            align-items: center;
            padding: 1rem;
        }

        /* Card body inner */
        .responsive-card-body-inner {
            width: 100%;
            padding: 0;
        }

        /* Card header */
        .responsive-card-header {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            padding: 1rem;
        }

        /* Card title */
        .responsive-card-title {
            margin: 0;
            font-size: 1.5rem;
            line-height: 1.2;
        }

        /* Responsive adjustments for card header */
        @media (max-width: 768px) {
            .responsive-card-header {
                padding: 0.5rem;
            }

            .responsive-card-title {
                font-size: 1.25rem;
            }
        }

        /* Chart container */
        .responsive-chart-container {
            width: 100%;
            position: relative;
            /* Make sure canvas is positioned correctly */
        }

        /* Canvas styling */
        .responsive-chart-container canvas {
            width: 100% !important;
            /* Make canvas responsive */
            height: auto !important;
            /* Maintain aspect ratio */
        }

        /* Responsive adjustments for canvas */
        @media (max-width: 768px) {
            .responsive-card-body {
                padding: 0.5rem;
            }

            .responsive-card-title {
                font-size: 1.25rem;
            }
        }

        @media (max-width: 576px) {
            .responsive-card-title {
                font-size: 1rem;
            }
        }

        .title-and-cards-container {
            display: flex;
            align-items: center;
            /* Align items vertically in the center */
            justify-content: space-between;
            /* Space between title, line, and cards */
            padding: 10px;
            /* Optional: adds padding around the container */
        }

        .title-container {
            flex: 1;
            /* Allow title container to take up space */
        }

        .main-title {
            font-size: 18px;
            /* Adjust font size as needed */
            font-weight: 600;
            /* Bold title */
            text-align: left;
            /* Align text to the left */
            margin-left: 25px;
        }

        .dynamic-card-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
            /* Center cards horizontally */
            flex: 3;
            /* Allow card container to take up more space */
        }

        .dynamic-card-container .card {
            width: 250px;
            height: 300px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background-color: #fff;
            border-radius: 8px;
            position: relative;
            /* for potential future use */
            /* Remove any other styles that might conflict with your existing cards */
        }

        .card-title {
            margin-bottom: 10px;
            text-align: center;
            font-size: 15px;
            font-weight: 600;
        }

        .card-canvas {
            width: 100%;
            /* Ensure canvas uses full width */
            height: 100%;
            /* Adjust height of the canvas for the doughnut chart */
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 10px;
            /* Increased margin from the title */
        }

        .card-top-title {
            margin-top: 10px;
            /* Space between the top title and the chart */
            text-align: center;
            font-size: 14px;
            font-weight: bolder;
        }

        .card-secondary-top-title {
            margin-bottom: 5px;
            /* Space between the secondary top title and the chart */
            text-align: center;
            font-size: 12px;
            /* Adjust font size if needed */
            font-weight: bold;
            /* Slightly lighter weight for the Pourcentage complété : */
        }

        .plus-sign {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            /* Large size for visibility */
            color: #000;
            /* Adjust color if needed */
            position: relative;
            /* Allows movement relative to its normal position */
            /* top: 50px; */
            /* Moves the plus sign down by 100px */
            transition: transform 0.3s ease, color 0.3s ease;
            /* Smooth transitions for interactivity */
        }

        /* Optional: Hover effect for a modern touch */
        .plus-sign:hover {
            transform: scale(1.1);
            /* Slightly enlarges on hover */
            color: #007bff;
            /* Change color on hover for better visibility */
        }
    </style>

    <!--begin::Title-->
    <title><?php echo $tableau ?> | CFAO Mobility Academy</title>
    <!--end::Title-->

    <!--begin::Content-->
    <?php openBackgroundContainer('', 'id="kt_content"'); ?>
        <?php if ($_SESSION["profile"] == "Admin" || $_SESSION["profile"] == "Ressource Humaine" || $_SESSION["profile"] == "Directeur Pièce et Service" || $_SESSION["profile"] == "Directeur des Opérations" || $_SESSION["profile"] == "Directeur Général" || $_SESSION["profile"] == "Directeur Groupe") { ?>
            <!--begin::Toolbar-->
            <div class="toolbar" id="kt_toolbar">
                <div class=" container-fluid  d-flex flex-stack flex-wrap flex-sm-nowrap">
                    <!--begin::Info-->
                    <div class="d-flex flex-column align-items-start justify-content-center flex-wrap me-2">
                        <!--begin::Title-->
                        <h1 class="text-dark fw-bolder my-1 fs-1">
                            <?php echo $tableau ?>
                        </h1>
                        <!--end::Title-->
                    </div>
                    <!--end::Info-->
                </div>
            </div>
            <!--end::Toolbar-->
            <!--begin::Post-->
            <div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
                <!--begin::Container-->
                <div class=" container-xxl ">
                    <!--end::Layout Builder Notice-->
                    <!--begin::Row-->
                    <div class="row g-6 g-xl-9 mb-6 mb-xl-9">
                        <?php if ( $_SESSION["profile"] == "Directeur Groupe") { ?>
                            <!--begin::Toolbar-->
                            <div class="toolbar" id="kt_toolbar" style="margin-bottom: -50px">
                                <div class=" container-fluid  d-flex flex-stack flex-wrap flex-sm-nowrap">
                                    <!--begin::Info-->
                                    <div class="d-flex flex-column align-items-start justify-content-center flex-wrap me-2">
                                        <!--begin::Title-->
                                        <h1 class="text-dark fw-bold my-1 fs-2">
                                            <?php echo $effectif_total_groupe ?>
                                        </h1>
                                        <!--end::Title-->
                                    </div>
                                    <!--end::Info-->
                                </div>
                            </div>
                            <!--end::Toolbar-->
                            <!--begin::Col-->
                            <div class="col-md-6 col-lg-4 col-xl-3">
                                <!--begin::Card-->
                                <div class="card h-100 ">
                                    <!--begin::Card body-->
                                    <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                        <!--begin::Name-->
                                        <!--begin::Animation-->
                                        <div
                                            class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                            <div class="min-w-70px" data-kt-countup="true"
                                                data-kt-countup-value="<?php echo $countUsersJunior ?>">
                                            </div>
                                        </div>
                                        <!--end::Animation-->
                                        <!--begin::Title-->
                                        <div class="fs-5 fw-bold mb-2">
                                            <?php echo $technicienss.' '.$Level.' '.$junior ?> </div>
                                        <!--end::Title-->
                                        <!--end::Name-->
                                    </div>
                                    <!--end::Card body-->
                                </div>
                                <!--end::Card-->
                            </div>
                            <!--end::Col-->
                            <!--begin::Col-->
                            <div class="col-md-6 col-lg-4 col-xl-3">
                                <!--begin::Card-->
                                <div class="card h-100 ">
                                    <!--begin::Card body-->
                                    <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                        <!--begin::Name-->
                                        <!--begin::Animation-->
                                        <div
                                            class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                            <div class="min-w-70px" data-kt-countup="true"
                                                data-kt-countup-value="<?php echo ($countUsersSenior ) ?>">
                                            </div>
                                        </div>
                                        <!--end::Animation-->
                                        <!--begin::Title-->
                                        <div class="fs-5 fw-bold mb-2">
                                            <?php echo $technicienss.' '.$Level.' '.$senior ?> </div>
                                        <!--end::Title-->
                                        <!--end::Name-->
                                    </div>
                                    <!--end::Card body-->
                                </div>
                                <!--end::Card-->
                            </div>
                            <!--end::Col-->
                            <!--begin::Col-->
                            <div class="col-md-6 col-lg-4 col-xl-3">
                                <!--begin::Card-->
                                <div class="card h-100 ">
                                    <!--begin::Card body-->
                                    <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                        <!--begin::Name-->
                                        <!--begin::Animation-->
                                        <div
                                            class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                            <div class="min-w-70px" data-kt-countup="true"
                                                data-kt-countup-value="<?php echo ($countUsersExpert) ?>">
                                            </div>
                                        </div>
                                        <!--end::Animation-->
                                        <!--begin::Title-->
                                        <div class="fs-5 fw-bold mb-2">
                                            <?php echo $technicienss.' '.$Level.' '.$expert ?> </div>
                                        <!--end::Title-->
                                        <!--end::Name-->
                                    </div>
                                    <!--end::Card body-->
                                </div>
                                <!--end::Card-->
                            </div>
                            <!--end::Col-->
                            <!--begin::Col-->
                            <div class="col-md-6 col-lg-4 col-xl-3">
                                <!--begin::Card-->
                                <div class="card h-100 ">
                                    <!--begin::Card body-->
                                    <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                        <!--begin::Name-->
                                        <!--begin::Animation-->
                                        <div
                                            class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                            <div class="min-w-70px" data-kt-countup="true"
                                                data-kt-countup-value="<?php echo ($countUsersTotal) ?>">
                                            </div>
                                        </div>
                                        <!--end::Animation-->
                                        <!--begin::Title-->
                                        <div class="fs-5 fw-bold mb-2">
                                            <?php echo $technicienss.' '.$global ?> </div>
                                        <!--end::Title-->
                                        <!--end::Name-->
                                    </div>
                                    <!--end::Card body-->
                                </div>
                                <!--end::Card-->
                            </div>
                            <!--end::Col-->
                            <!--begin::Title-->
                            <div style="margin-top: 55px; margin-bottom : 25px">
                                <div>
                                    <h6 class="text-dark fw-bold my-1 fs-2">
                                        <?php echo $result_mesure_competence_groupe ?>
                                    </h6>
                                </div>
                            </div>
                            <!--end::Title-->
                            <!-- begin::Row -->
                            <div>
                                <div id="chartMoyen" class="row">
                                    <!-- Dynamic cards will be appended here -->
                                </div>
                                <div style="display: flex; justify-content: center; margin-top: -30px; transform: scale(0.75);">
                                    <fieldset style="display: flex; gap: 20px;">
                                        <!-- Group 1 -->
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <canvas id='c1' width="75" height="37.5"></canvas>
                                            <h4 style="opacity: 0.60; margin-top: 30px;"><?php echo $result_entre_0_60 ?></h4>
                                        </div>

                                        <!-- Group 2 -->
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <canvas id='c2' width="75" height="37.5"></canvas>
                                            <h4 style="opacity: 0.60; margin-top: 30px;"><?php echo $result_entre_60_80 ?></h4>
                                        </div>

                                        <!-- Group 3 -->
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <canvas id='c3' width="75" height="37.5"></canvas>
                                            <h4 style="opacity: 0.60; margin-top: 30px;"><?php echo $result_entre_80_100 ?></h4>
                                        </div>
                                    </fieldset>
                                </div>
                            </div>
                            <!-- end::Row -->
                            <!-- Dropdown Container -->
                            <div class="dropdown-container1">
                                <button class="dropdown-toggle1" style="color: black">Plus de détails sur les resultats
                                    <i class="fas fa-chevron-down"></i></button>
                                <!-- Hidden Content -->
                                <div class="dropdown-content1">
                                    <!-- Begin::Row -->
                                    <div class="row">
                                        <!-- Card 1 -->
                                        <div class="col-xl-3">
                                            <div class="card" style="height: 430px; width: 300px; margin-bottom: 25px;">
                                                <center>
                                                    <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                        Résultats Niveau Junior</h5>
                                                </center>
                                                <div id="result_junior"
                                                    style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                                            </div>
                                        </div>
                                        <!-- Card 2 -->
                                        <div class="col-xl-3">
                                            <div class="card" style="height: 430px; width: 300px; margin-bottom: 25px;">
                                                <center>
                                                    <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                        Résultats Niveau Senior</h5>
                                                </center>
                                                <div id="result_senior"
                                                    style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                                            </div>
                                        </div>
                                        <!-- Card 3 -->
                                        <div class="col-xl-3">
                                            <div class="card" style="height: 430px; width: 300px; margin-bottom: 25px;">
                                                <center>
                                                    <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                        Résultats Niveau Expert</h5>
                                                </center>
                                                <div id="result_expert"
                                                    style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                                            </div>
                                        </div>
                                        <!-- Card 4 -->
                                        <div class="col-xl-3">
                                            <div class="card" style="height: 430px; width: 300px; margin-bottom: 25px;">
                                                <center>
                                                    <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                        Total : 03 Niveaux</h5>
                                                </center>
                                                <div id="result_total"
                                                    style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- End::Row -->
                                </div>
                            </div>
                            <!--begin::Title-->
                            <div style="margin-top: 55px; margin-bottom : 25px">
                                <div>
                                    <h6 class="text-dark fw-bold my-1 fs-2">
                                        <?php echo $result_mesure_competence_niveau ?>
                                    </h6>
                                </div>
                            </div>
                            <!--end::Title-->
                            <!-- Card 1 -->
                            <div class="col-xl-3">
                                <div class="card" style="height: 430px; width: 300px; margin-bottom: 25px;">
                                    <center>
                                        <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                            Résultats Niveau Junior</h5>
                                    </center>
                                    <div id="chart_junior"
                                        style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                                </div>
                            </div>
                            <!-- Card 2 -->
                            <div class="col-xl-3">
                                <div class="card" style="height: 430px; width: 300px; margin-bottom: 25px;">
                                    <center>
                                        <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                            Résultats Niveau Senior</h5>
                                    </center>
                                    <div id="chart_senior"
                                        style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                                </div>
                            </div>
                            <!-- Card 3 -->
                            <div class="col-xl-3">
                                <div class="card" style="height: 430px; width: 300px; margin-bottom: 25px;">
                                    <center>
                                        <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                            Résultats Niveau Expert</h5>
                                    </center>
                                    <div id="chart_expert"
                                        style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                                </div>
                            </div>
                            <!-- Card 4 -->
                            <div class="col-xl-3">
                                <div class="card" style="height: 430px; width: 300px; margin-bottom: 25px;">
                                    <center>
                                        <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                            Total : 03 Niveaux</h5>
                                    </center>
                                    <div id="chart_total"
                                        style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                                </div>
                            </div>
                            <!--begin::Title-->
                            <div style="margin-top: 55px; margin-bottom : 25px">
                                <div>
                                    <h6 class="text-dark fw-bold my-1 fs-2">
                                        <?php echo $etat_avanacement_test_realises_groupe ?>
                                    </h6>
                                </div>
                            </div>
                            <!--end::Title-->
                            <!-- begin::Row -->
                            <div>
                                <div id="chartTest" class="row">
                                    <!-- Dynamic cards will be appended here -->
                                </div>
                            </div>
                            <!-- endr::Row -->
                            <!-- Dropdown Toggle Button -->
                            <div class="dropdown-container">
                                <button class="dropdown-toggle" style="color: black">Plus de détails sur les tests
                                    <i class="fas fa-chevron-down"></i></button>
                                <!-- Hidden Content -->
                                <div class="dropdown-content">
                                    <!--begin::Title-->
                                    <div style="margin-top: 55px; margin-bottom : 25px">
                                        <div>
                                            <h6 class="text-dark fw-bold my-1 fs-2">
                                                <?php echo $etat_avanacement_qcm_realises_groupe ?>
                                            </h6>
                                        </div>
                                    </div>
                                    <!--end::Title-->
                                    <!-- Begin::Row -->
                                    <div class="row">
                                        <!-- Card 1 -->
                                        <div class="col-xl-3">
                                            <div class="card" style="height: 430px; width: 300px; margin-bottom: 25px;">
                                                <center>
                                                    <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                        QCM Niveau Junior</h5>
                                                </center>
                                                <div id="qcm_junior"
                                                    style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                                            </div>
                                        </div>
                                        <!-- Card 2 -->
                                        <div class="col-xl-3">
                                            <div class="card" style="height: 430px; width: 300px; margin-bottom: 25px;">
                                                <center>
                                                    <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                        QCM Niveau Senior</h5>
                                                </center>
                                                <div id="qcm_senior"
                                                    style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                                            </div>
                                        </div>
                                        <!-- Card 3 -->
                                        <div class="col-xl-3">
                                            <div class="card" style="height: 430px; width: 300px; margin-bottom: 25px;">
                                                <center>
                                                    <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                        QCM Niveau Expert</h5>
                                                </center>
                                                <div id="qcm_expert"
                                                    style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                                            </div>
                                        </div>
                                        <!-- Card 4 -->
                                        <div class="col-xl-3">
                                            <div class="card" style="height: 430px; width: 300px; margin-bottom: 25px;">
                                                <center>
                                                    <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                        Total : 03 Niveaux</h5>
                                                </center>
                                                <div id="qcm_total"
                                                    style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- End::Row -->
                                </div>
                            </div>
                        <?php } ?>
                        <?php if ( $_SESSION["profile"] == "Admin" || $_SESSION["profile"] == "Ressource Humaine" || $_SESSION["profile"] == "Directeur Pièce et Service" || $_SESSION["profile"] == "Directeur des Opérations" || $_SESSION["profile"] == "Directeur Général") { ?>
                            <!--begin::Toolbar-->
                            <div class="toolbar" id="kt_toolbar" style="margin-bottom: -50px">
                                <div class=" container-fluid  d-flex flex-stack flex-wrap flex-sm-nowrap">
                                    <!--begin::Info-->
                                    <div class="d-flex flex-column align-items-start justify-content-center flex-wrap me-2">
                                        <!--begin::Title-->
                                        <h1 class="text-dark fw-bold my-1 fs-2">
                                            <?php echo $effectif_filiale ?>
                                        </h1>
                                        <!--end::Title-->
                                    </div>
                                    <!--end::Info-->
                                </div>
                            </div>
                            <!--end::Toolbar-->
                            <!--begin::Col-->
                            <div class="col-md-6 col-lg-4 col-xl-3">
                                <!--begin::Card-->
                                <div class="card h-100 ">
                                    <!--begin::Card body-->
                                    <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                        <!--begin::Name-->
                                        <!--begin::Animation-->
                                        <div
                                            class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                            <div class="min-w-70px" data-kt-countup="true"
                                                data-kt-countup-value="<?php echo $techniciansJu ?>">
                                            </div>
                                        </div>
                                        <!--end::Animation-->
                                        <!--begin::Title-->
                                        <div class="fs-5 fw-bold mb-2">
                                            <?php echo $technicienss ?> <?php echo $Level ?> <?php echo $junior ?></div>
                                        <!--end::Title-->
                                        <!--end::Name-->
                                    </div>
                                    <!--end::Card body-->
                                </div>
                                <!--end::Card-->
                            </div>
                            <!--end::Col-->
                            <!--begin::Col-->
                            <div class="col-md-6 col-lg-4 col-xl-3">
                                <!--begin::Card-->
                                <div class="card h-100 ">
                                    <!--begin::Card body-->
                                    <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                        <!--begin::Name-->
                                        <!--begin::Animation-->
                                        <div
                                            class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                            <div class="min-w-70px" data-kt-countup="true"
                                                data-kt-countup-value="<?php echo ($techniciansSe) ?>">
                                            </div>
                                        </div>
                                        <!--end::Animation-->
                                        <!--begin::Title-->
                                        <div class="fs-5 fw-bold mb-2">
                                            <?php echo $technicienss ?> <?php echo $Level ?> <?php echo $senior ?></div>
                                        <!--end::Title-->
                                        <!--end::Name-->
                                    </div>
                                    <!--end::Card body-->
                                </div>
                                <!--end::Card-->
                            </div>
                            <!--end::Col-->
                            <!--begin::Col-->
                            <div class="col-md-6 col-lg-4 col-xl-3">
                                <!--begin::Card-->
                                <div class="card h-100 ">
                                    <!--begin::Card body-->
                                    <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                        <!--begin::Name-->
                                        <!--begin::Animation-->
                                        <div
                                            class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                            <div class="min-w-70px" data-kt-countup="true"
                                                data-kt-countup-value="<?php echo ($techniciansEx) ?>">
                                            </div>
                                        </div>
                                        <!--end::Animation-->
                                        <!--begin::Title-->
                                        <div class="fs-5 fw-bold mb-2">
                                            <?php echo $technicienss ?> <?php echo $Level ?> <?php echo $expert ?></div>
                                        <!--end::Title-->
                                        <!--end::Name-->
                                    </div>
                                    <!--end::Card body-->
                                </div>
                                <!--end::Card-->
                            </div>
                            <!--end::Col-->
                            <!--begin::Col-->
                            <div class="col-md-6 col-lg-4 col-xl-3">
                                <!--begin::Card-->
                                <div class="card h-100 ">
                                    <!--begin::Card body-->
                                    <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                        <!--begin::Name-->
                                        <!--begin::Animation-->
                                        <div
                                            class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                            <div class="min-w-70px" data-kt-countup="true"
                                                data-kt-countup-value="<?php echo ($techniciansFi); ?>">
                                            </div>
                                        </div>
                                        <!--end::Animation-->
                                        <!--begin::Title-->
                                        <div class="fs-5 fw-bold mb-2">
                                            <?php echo $technicienss ?> <?php echo $subsidiary ?> <?php echo $global ?></div>
                                        <!--end::Title-->
                                        <!--end::Name-->
                                    </div>
                                    <!--end::Card body-->
                                </div>
                                <!--end::Card-->
                            </div>
                            <!--end::Col-->
                            <!--begin::Title-->
                            <div style="margin-top: 55px; margin-bottom : 25px">
                                <div>
                                    <h6 class="text-dark fw-bold my-1 fs-2">
                                        <?php echo $result_mesure_competence_filiale ?>
                                    </h6>
                                </div>
                            </div>
                            <!--end::Title-->
                            <!-- begin::Row -->
                            <div>
                                <div id="chartMoyenFiliale" class="row">
                                    <!-- Dynamic cards will be appended here -->
                                </div>
                                <div style="display: flex; justify-content: center; margin-top: -30px; transform: scale(0.75);">
                                    <fieldset style="display: flex; gap: 20px;">
                                        <!-- Group 1 -->
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <canvas id='c1' width="75" height="37.5"></canvas>
                                            <h4 style="opacity: 0.60; margin-top: 30px;"><?php echo $result_entre_0_60 ?></h4>
                                        </div>

                                        <!-- Group 2 -->
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <canvas id='c2' width="75" height="37.5"></canvas>
                                            <h4 style="opacity: 0.60; margin-top: 30px;"><?php echo $result_entre_60_80 ?></h4>
                                        </div>

                                        <!-- Group 3 -->
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <canvas id='c3' width="75" height="37.5"></canvas>
                                            <h4 style="opacity: 0.60; margin-top: 30px;"><?php echo $result_entre_80_100 ?></h4>
                                        </div>
                                    </fieldset>
                                </div>
                            </div>
                            <!-- endr::Row -->
                            <?php if ($_SESSION['profile'] == 'Directeur des Opérations' || $_SESSION['profile'] == 'Directeur Pièce et Service' || $_SESSION["profile"] == "Directeur Général") { ?>
                                <!-- Dropdown Container -->
                                <div class="dropdown-container1">
                                    <button class="dropdown-toggle1" style="color: black">Plus de détails sur les resultats
                                        <i class="fas fa-chevron-down"></i></button>
                                    <!-- Hidden Content -->
                                    <div class="dropdown-content1">
                                        <!-- Begin::Row -->
                                        <div class="row">
                                            <!-- Card 1 -->
                                            <div class="col-xl-3">
                                                <div class="card" style="height: 430px; width: 300px; margin-bottom: 25px;">
                                                    <center>
                                                        <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                            Résultats Niveau Junior</h5>
                                                    </center>
                                                    <div id="result_junior_filiale"
                                                        style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                                                </div>
                                            </div>
                                            <!-- Card 2 -->
                                            <div class="col-xl-3">
                                                <div class="card" style="height: 430px; width: 300px; margin-bottom: 25px;">
                                                    <center>
                                                        <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                            Résultats Niveau Senior</h5>
                                                    </center>
                                                    <div id="result_senior_filiale"
                                                        style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                                                </div>
                                            </div>
                                            <!-- Card 3 -->
                                            <div class="col-xl-3">
                                                <div class="card" style="height: 430px; width: 300px; margin-bottom: 25px;">
                                                    <center>
                                                        <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                            Résultats Niveau Expert</h5>
                                                    </center>
                                                    <div id="result_expert_filiale"
                                                        style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                                                </div>
                                            </div>
                                            <!-- Card 4 -->
                                            <div class="col-xl-3">
                                                <div class="card" style="height: 430px; width: 300px; margin-bottom: 25px;">
                                                    <center>
                                                        <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                            Total : 03 Niveaux</h5>
                                                    </center>
                                                    <div id="result_total_filiale"
                                                        style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- End::Row -->
                                    </div>
                                </div>
                                <!--begin::Title-->
                                <div style="margin-top: 55px; margin-bottom : 25px">
                                    <div>
                                        <h6 class="text-dark fw-bold my-1 fs-2">
                                            <?php echo $result_mesure_competence_filiale_niveau ?>
                                        </h6>
                                    </div>
                                </div>
                                <!--end::Title-->
                                <!-- Card 1 -->
                                <div class="col-xl-3">
                                    <div class="card" style="height: 430px; width: 300px; margin-bottom: 25px;">
                                        <center>
                                            <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                Résultats Niveau Junior</h5>
                                        </center>
                                        <div id="chart_junior_filiale"
                                            style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                                    </div>
                                </div>

                                <!-- Card 2 -->
                                <div class="col-xl-3">
                                    <div class="card" style="height: 430px; width: 300px; margin-bottom: 25px;">
                                        <center>
                                            <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                Résultats Niveau Senior</h5>
                                        </center>
                                        <div id="chart_senior_filiale"
                                            style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                                    </div>
                                </div>

                                <!-- Card 3 -->
                                <div class="col-xl-3">
                                    <div class="card" style="height: 430px; width: 300px; margin-bottom: 25px;">
                                        <center>
                                            <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                Résultats Niveau Expert</h5>
                                        </center>
                                        <div id="chart_expert_filiale"
                                            style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                                    </div>
                                </div>

                                <!-- Card 4 -->
                                <div class="col-xl-3">
                                    <div class="card" style="height: 430px; width: 300px; margin-bottom: 25px;">
                                        <center>
                                            <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                Total : 03 Niveaux</h5>
                                        </center>
                                        <div id="chart_total_filiale"
                                            style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                                    </div>
                                </div>
                            <?php } ?>
                            <?php if ($_SESSION['profile'] == 'Directeur des Opérations' || $_SESSION['profile'] == 'Directeur Pièce et Service' || $_SESSION['profile'] == 'Admin' || $_SESSION['profile'] == 'Ressource Humaine') { ?>
                                <!--begin::Title-->
                                <div style="margin-top: 55px; margin-bottom : 25px">
                                    <div>
                                        <h6 class="text-dark fw-bold my-1 fs-2">
                                            <?php echo $etat_avanacement_test_filiale ?>
                                        </h6>
                                    </div>
                                </div>
                                <!--end::Title-->
                                <!-- begin::Row -->
                                <div>
                                    <div id="chartTestFiliale" class="row">
                                        <!-- Dynamic cards will be appended here -->
                                    </div>
                                </div>
                                <!-- endr::Row -->
                                <!-- Dropdown Toggle Button -->
                                <div class="dropdown-container">
                                    <button class="dropdown-toggle" style="color: black">Plus de détails sur les tests
                                        <i class="fas fa-chevron-down"></i></button>
                                    <!-- Hidden Content -->
                                    <div class="dropdown-content">
                                        <!-- Begin::Row -->
                                        <div class="row">
                                            <!-- Card 1 -->
                                            <div class="col-xl-3">
                                                <div class="card" style="height: 430px; width: 300px; margin-bottom: 25px;">
                                                    <center>
                                                        <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                            QCM Niveau Junior</h5>
                                                    </center>
                                                    <div id="qcm_junior_filiale"
                                                        style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                                                </div>
                                            </div>
                                            <!-- Card 2 -->
                                            <div class="col-xl-3">
                                                <div class="card" style="height: 430px; width: 300px; margin-bottom: 25px;">
                                                    <center>
                                                        <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                            QCM Niveau Senior</h5>
                                                    </center>
                                                    <div id="qcm_senior_filiale"
                                                        style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                                                </div>
                                            </div>
                                            <!-- Card 3 -->
                                            <div class="col-xl-3">
                                                <div class="card" style="height: 430px; width: 300px; margin-bottom: 25px;">
                                                    <center>
                                                        <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                            QCM Niveau Expert</h5>
                                                    </center>
                                                    <div id="qcm_expert_filiale"
                                                        style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                                                </div>
                                            </div>
                                            <!-- Card 4 -->
                                            <div class="col-xl-3">
                                                <div class="card" style="height: 430px; width: 300px; margin-bottom: 25px;">
                                                    <center>
                                                        <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                            Total : 03 Niveaux</h5>
                                                    </center>
                                                    <div id="qcm_total_filiale"
                                                        style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- End::Row -->
                                    </div>
                                </div>
                            <?php } ?>
                        <?php } ?>
                    </div>
                    <!--end:Row-->
                </div>
                <!--end::Container-->
            </div>
            <!--end::Post-->
        <?php } ?>
        <?php if ( $_SESSION["profile"] == "Super Admin") { ?>
            <!--begin::Toolbar-->
            <div class="toolbar" id="kt_toolbar">
                <div class=" container-fluid  d-flex flex-stack flex-wrap flex-sm-nowrap">
                    <!--begin::Info-->
                    <div class="d-flex flex-column align-items-start justify-content-center flex-wrap me-2">
                        <!--begin::Title-->
                        <h1 class="text-dark fw-bold my-1 fs-2">
                            <?php echo $tableau ?>
                        </h1>
                        <!--end::Title-->
                    </div>
                    <!--end::Info-->
                </div>
            </div>
            <!--end::Toolbar-->
            <!--begin::Content-->
            <div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content">
                <!--begin::Post-->
                <div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
                    <!--begin::Container-->
                    <div class=" container-xxl ">
                        <!--begin::Row-->
                        <div class="row g-6 g-xl-9 mb-6 mb-xl-9">
                            <!--begin::Col-->
                            <div class="col-md-6 col-lg-4 col-xl-2.5">
                                <!--begin::Card-->
                                <div class="card h-100 ">
                                    <!--begin::Card body-->
                                    <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                        <!--begin::Name-->
                                        <!--begin::Animation-->
                                        <div
                                            class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                            <div class="min-w-70px" data-kt-countup="true"
                                                data-kt-countup-value="<?php echo ($countUsersTotal) ?>">
                                            </div>
                                        </div>
                                        <!--end::Animation-->
                                        <!--begin::Title-->
                                        <div class="fs-5 fw-bold mb-2">
                                            <?php echo $technicienss ?> </div>
                                        <!--end::Title-->
                                        <!--end::Name-->
                                    </div>
                                    <!--end::Card body-->
                                </div>
                                <!--end::Card-->
                            </div>
                            <!--end::Col-->
                            <!--begin::Col-->
                            <div class="col-md-6 col-lg-4 col-xl-2.5">
                                <!--begin::Card-->
                                <div class="card h-100 ">
                                    <!--begin::Card body-->
                                    <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                        <!--begin::Name-->
                                        <!--begin::Animation-->
                                        <div
                                            class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                            <div class="min-w-70px" data-kt-countup="true"
                                                data-kt-countup-value="<?php echo $countManagers; ?>">
                                            </div>
                                        </div>
                                        <!--end::Animation-->
                                        <!--begin::Title-->
                                        <div class="fs-5 fw-bold mb-2">
                                            <?php echo $manageur ?> </div>
                                        <!--end::Title-->
                                        <!--end::Name-->
                                    </div>
                                    <!--end::Card body-->
                                </div>
                                <!--end::Card-->
                            </div>
                            <!--end::Col-->
                            <!--begin::Col-->
                            <div class="col-md-6 col-lg-4 col-xl-2.5">
                                <!--begin::Card-->
                                <div class="card h-100 ">
                                    <!--begin::Card body-->
                                    <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                        <!--begin::Name-->
                                        <!--begin::Animation-->
                                        <div
                                            class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                            <div class="min-w-70px" data-kt-countup="true"
                                                data-kt-countup-value="<?php echo $countAdmins; ?>">
                                            </div>
                                        </div>
                                        <!--end::Animation-->
                                        <!--begin::Title-->
                                        <div class="fs-5 fw-bold mb-2">
                                            <?php echo $adminss ?>
                                        </div>
                                        <!--end::Title-->
                                        <!--end::Name-->
                                    </div>
                                    <!--end::Card body-->
                                </div>
                                <!--end::Card-->
                            </div>
                            <!--end::Col-->
                            <!--begin::Col-->
                            <div class="col-md-6 col-lg-4 col-xl-2.5">
                                <!--begin::Card-->
                                <div class="card h-100 ">
                                    <!--begin::Card body-->
                                    <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                        <!--begin::Name-->
                                        <!--begin::Animation-->
                                        <div
                                            class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                            <div class="min-w-70px" data-kt-countup="true"
                                                data-kt-countup-value="<?php echo $countDirecteurFiliales ?>">
                                            </div>
                                        </div>
                                        <!--end::Animation-->
                                        <!--begin::Title-->
                                        <div class="fs-5 fw-bold mb-2">
                                            <?php echo $directeurs_filiales ?> </div>
                                        <!--end::Title-->
                                        <!--end::Name-->
                                    </div>
                                    <!--end::Card body-->
                                </div>
                                <!--end::Card-->
                            </div>
                            <!--end::Col-->
                            <!--begin::Col-->
                            <div class="col-md-6 col-lg-4 col-xl-2.5">
                                <!--begin::Card-->
                                <div class="card h-100 ">
                                    <!--begin::Card body-->
                                    <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                        <!--begin::Name-->
                                        <!--begin::Animation-->
                                        <div
                                            class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                            <div class="min-w-70px" data-kt-countup="true"
                                                data-kt-countup-value="<?php echo $countDirecteurGroupes ?>">
                                            </div>
                                        </div>
                                        <!--end::Animation-->
                                        <!--begin::Title-->
                                        <div class="fs-5 fw-bold mb-2">
                                            <?php echo $directeurs_groupe ?> </div>
                                        <!--end::Title-->
                                        <!--end::Name-->
                                    </div>
                                    <!--end::Card body-->
                                </div>
                                <!--end::Card-->
                            </div>
                            <!--end::Col-->
                            <!--begin::Col-->
                            <div class="col-md-6 col-lg-4 col-xl-2.5">
                                <!--begin::Card-->
                                <div class="card h-100 ">
                                    <!--begin::Card body-->
                                    <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                        <!--begin::Name-->
                                        <!--begin::Animation-->
                                        <div
                                            class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                            <div class="min-w-70px" data-kt-countup="true"
                                                data-kt-countup-value="<?php echo $countOnlineUsers ?>">
                                            </div>
                                        </div>
                                        <!--end::Animation-->
                                        <!--begin::Title-->
                                        <div class="fs-5 fw-bold mb-2">
                                            <?php echo $user_online ?> </div>
                                        <!--end::Title-->
                                        <!--end::Name-->
                                    </div>
                                    <!--end::Card body-->
                                </div>
                                <!--end::Card-->
                            </div>
                            <!--end::Col-->

                            <!-- Container for the dynamic cards -->

                            <!--begin::Title-->
                            <div style="margin-top: 55px; margin-bottom : 25px">
                                <div>
                                    <h6 class="text-dark fw-bold my-1 fs-2">
                                        <?php echo $result_mesure_competence_groupe ?>
                                    </h6>
                                </div>
                            </div>
                            <!--end::Title-->
                            <!-- begin::Row -->
                            <div>
                                <div id="chartMoyen" class="row">
                                    <!-- Dynamic cards will be appended here -->
                                </div>
                                <div style="display: flex; justify-content: center; margin-top: -30px; transform: scale(0.75);">
                                    <fieldset style="display: flex; gap: 20px;">
                                        <!-- Group 1 -->
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <canvas id='c1' width="75" height="37.5"></canvas>
                                            <h4 style="opacity: 0.60; margin-top: 30px;"><?php echo $result_entre_0_60 ?></h4>
                                        </div>

                                        <!-- Group 2 -->
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <canvas id='c2' width="75" height="37.5"></canvas>
                                            <h4 style="opacity: 0.60; margin-top: 30px;"><?php echo $result_entre_60_80 ?></h4>
                                        </div>

                                        <!-- Group 3 -->
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <canvas id='c3' width="75" height="37.5"></canvas>
                                            <h4 style="opacity: 0.60; margin-top: 30px;"><?php echo $result_entre_80_100 ?></h4>
                                        </div>
                                    </fieldset>
                                </div>
                            </div>
                            <!-- endr::Row -->
                            <!-- Dropdown Container -->
                            <div class="dropdown-container1">
                                <button class="dropdown-toggle1" style="color: black">Plus de détails sur les resultats
                                    <i class="fas fa-chevron-down"></i></button>
                                <!-- Hidden Content -->
                                <div class="dropdown-content1">
                                    <!-- Begin::Row -->
                                    <div class="row">
                                        <!-- Card 1 -->
                                        <div class="col-xl-3">
                                            <div class="card" style="height: 430px; width: 300px; margin-bottom: 25px;">
                                                <center>
                                                    <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                        Résultats Niveau Junior</h5>
                                                </center>
                                                <div id="result_junior"
                                                    style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                                            </div>
                                        </div>
                                        <!-- Card 2 -->
                                        <div class="col-xl-3">
                                            <div class="card" style="height: 430px; width: 300px; margin-bottom: 25px;">
                                                <center>
                                                    <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                        Résultats Niveau Senior</h5>
                                                </center>
                                                <div id="result_senior"
                                                    style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                                            </div>
                                        </div>
                                        <!-- Card 3 -->
                                        <div class="col-xl-3">
                                            <div class="card" style="height: 430px; width: 300px; margin-bottom: 25px;">
                                                <center>
                                                    <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                        Résultats Niveau Expert</h5>
                                                </center>
                                                <div id="result_expert"
                                                    style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                                            </div>
                                        </div>
                                        <!-- Card 4 -->
                                        <div class="col-xl-3">
                                            <div class="card" style="height: 430px; width: 300px; margin-bottom: 25px;">
                                                <center>
                                                    <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                        Total : 03 Niveaux</h5>
                                                </center>
                                                <div id="result_total"
                                                    style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- End::Row -->
                                </div>
                            </div>
                            <!--begin::Title-->
                            <div style="margin-top: 55px; margin-bottom : 25px">
                                <div>
                                    <h6 class="text-dark fw-bold my-1 fs-2">
                                        <?php echo $result_mesure_competence_niveau ?>
                                    </h6>
                                </div>
                            </div>
                            <!--end::Title-->
                            <!-- Card 1 -->
                            <div class="col-xl-3">
                                <div class="card" style="height: 430px; width: 300px; margin-bottom: 25px;">
                                    <center>
                                        <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                            Résultats Niveau Junior</h5>
                                    </center>
                                    <div id="chart_junior"
                                        style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                                </div>
                            </div>

                            <!-- Card 2 -->
                            <div class="col-xl-3">
                                <div class="card" style="height: 430px; width: 300px; margin-bottom: 25px;">
                                    <center>
                                        <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                            Résultats Niveau Senior</h5>
                                    </center>
                                    <div id="chart_senior"
                                        style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                                </div>
                            </div>

                            <!-- Card 3 -->
                            <div class="col-xl-3">
                                <div class="card" style="height: 430px; width: 300px; margin-bottom: 25px;">
                                    <center>
                                        <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                            Résultats Niveau Expert</h5>
                                    </center>
                                    <div id="chart_expert"
                                        style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                                </div>
                            </div>

                            <!-- Card 4 -->
                            <div class="col-xl-3">
                                <div class="card" style="height: 430px; width: 300px; margin-bottom: 25px;">
                                    <center>
                                        <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                            Total : 03 Niveaux</h5>
                                    </center>
                                    <div id="chart_total"
                                        style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                                </div>
                            </div>
                            <!--begin::Title-->
                            <div style="margin-top: 55px; margin-bottom : 25px">
                                <div>
                                    <h6 class="text-dark fw-bold my-1 fs-2">
                                        <?php echo $etat_avanacement_test_realises_groupe ?>
                                    </h6>
                                </div>
                            </div>
                            <!--end::Title-->
                            <!-- begin::Row -->
                            <div>
                                <div id="chartTest" class="row">
                                    <!-- Dynamic cards will be appended here -->
                                </div>
                            </div>
                            <!-- endr::Row -->
                            <!-- Dropdown Toggle Button -->
                            <div class="dropdown-container">
                                <button class="dropdown-toggle" style="color: black">Plus de détails sur les tests
                                    <i class="fas fa-chevron-down"></i></button>
                                <!-- Hidden Content -->
                                <div class="dropdown-content">
                                    <!--begin::Title-->
                                    <div style="margin-top: 55px; margin-bottom : 25px">
                                        <div>
                                            <h6 class="text-dark fw-bold my-1 fs-2">
                                                <?php echo $etat_avanacement_qcm_realises_groupe ?>
                                            </h6>
                                        </div>
                                    </div>
                                    <!--end::Title-->
                                    <!-- Begin::Row -->
                                    <div class="row">
                                        <!-- Card 1 -->
                                        <div class="col-xl-3">
                                            <div class="card" style="height: 430px; width: 300px; margin-bottom: 25px;">
                                                <center>
                                                    <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                        QCM Niveau Junior</h5>
                                                </center>
                                                <div id="qcm_junior"
                                                    style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                                            </div>
                                        </div>
                                        <!-- Card 2 -->
                                        <div class="col-xl-3">
                                            <div class="card" style="height: 430px; width: 300px; margin-bottom: 25px;">
                                                <center>
                                                    <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                        QCM Niveau Senior</h5>
                                                </center>
                                                <div id="qcm_senior"
                                                    style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                                            </div>
                                        </div>
                                        <!-- Card 3 -->
                                        <div class="col-xl-3">
                                            <div class="card" style="height: 430px; width: 300px; margin-bottom: 25px;">
                                                <center>
                                                    <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                        QCM Niveau Expert</h5>
                                                </center>
                                                <div id="qcm_expert"
                                                    style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                                            </div>
                                        </div>
                                        <!-- Card 4 -->
                                        <div class="col-xl-3">
                                            <div class="card" style="height: 430px; width: 300px; margin-bottom: 25px;">
                                                <center>
                                                    <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                        Total : 03 Niveaux</h5>
                                                </center>
                                                <div id="qcm_total"
                                                    style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- End::Row -->
                                </div>
                            </div>
                            <!-- Dropdown Toggle Button -->
                        </div>
                        <!--end::Container-->
                    </div>
                    <!--end::Container-->
                </div>
                <!--end::Post-->
            </div>
            <!--end::Content-->
        <?php } ?>
    <?php closeBackgroundContainer(); ?>
    <!--end::Content-->
    <?php include "./partials/footer.php"; ?>


    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/apexcharts/3.50.0/apexcharts.min.js"
    integrity="sha512-h3DSSmgtvmOo5gm3pA/YcDNxtlAZORKVNAcMQhFi3JJgY41j9G06WsepipL7+l38tn9Awc5wgMzJGrUWaeUEGA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
        let canvas1 = document.getElementById('c1');
        let ctx1 = canvas1.getContext('2d');
        ctx1.fillStyle = '#f9945e'; //Nuance de bleu
        ctx1.fillRect(50, 25, 200, 100);
        
        let canvas2 = document.getElementById('c2');
        let ctx2 = canvas2.getContext('2d');
        ctx2.fillStyle = '#f9f75e'; //Nuance de bleu
        ctx2.fillRect(50, 25, 200, 100);
        
        let canvas3 = document.getElementById('c3');
        let ctx3 = canvas3.getContext('2d');
        ctx3.fillStyle = '#6cf95e'; //Nuance de bleu
        ctx3.fillRect(50, 25, 200, 100);

        $(document).ready(function() {
            $('.dropdown-toggle').click(function() {
                var $dropdownContent = $('.dropdown-content');
                var isVisible = $dropdownContent.is(':visible');

                $dropdownContent.slideToggle();
                $(this).toggleClass('open', !isVisible);
            });
        });
        $(document).ready(function() {
            $('.dropdown-toggle1').click(function() {
                var $dropdownContent = $('.dropdown-content1');
                var isVisible = $dropdownContent.is(':visible');

                $dropdownContent.slideToggle();
                $(this).toggleClass('open', !isVisible);
            });
        });
        // Script for toggling the dropdown content
        document.querySelector('.dropdown-toggle2').addEventListener('click', function() {
            const dropdownContent = document.querySelector('.dropdown-content2');
            const toggleButton = this;

            if (dropdownContent.style.display === 'none' || dropdownContent.style.display === '') {
                dropdownContent.style.display = 'block';
                dropdownContent.style.opacity = '1';
                dropdownContent.style.maxHeight = dropdownContent.scrollHeight + 'px'; // Smoothly expand
                toggleButton.classList.add('open'); // Add class for rotating icon
            } else {
                dropdownContent.style.opacity = '0';
                dropdownContent.style.maxHeight = '0'; // Smoothly collapse
                setTimeout(() => {
                    dropdownContent.style.display = 'none';
                }, 300); // Delay hiding to allow the transition to complete
                toggleButton.classList.remove('open'); // Remove class for rotating icon
            }
        });
    </script>
    <?php if ($_SESSION['profile'] == 'Super Admin' || $_SESSION['profile'] == 'Directeur Groupe') { ?>
        <script>            
            // Fonction pour appliquer le filtre de pays
            function applyCountryFilter() {
                var selectedCountry = document.getElementById('country-select').value;
                var urlParams = new URLSearchParams(window.location.search);

                // Mettre à jour ou ajouter le paramètre 'country' dans l'URL
                if (selectedCountry) {
                    urlParams.set('country', selectedCountry);
                } else {
                    urlParams.delete('country');
                }

                // Rediriger vers l'URL mise à jour
                window.location.search = urlParams.toString();
            }
            // Fonction pour appliquer le filtre d'agence
            function applyAgencyFilter() {
                var selectedAgency = document.getElementById('agency-select').value;
                var urlParams = new URLSearchParams(window.location.search);

                // Mettre à jour ou ajouter le paramètre 'agency' dans l'URL
                if (selectedAgency) {
                    urlParams.set('agency', selectedAgency);
                } else {
                    urlParams.delete('agency');
                }

                // Rediriger vers l'URL mise à jour
                window.location.search = urlParams.toString();
            }

            // Graphiques pour les niveaux de maitrises des tâches professionnelles
            document.addEventListener('DOMContentLoaded', function() {
                // Données pour les niveaux
                var levels = ['Junior', 'Senior', 'Expert', 'Total'];
                var percentagesData = {
                    'Junior': <?php echo json_encode($percentagesJunior); ?>,
                    'Senior': <?php echo json_encode($percentagesSenior); ?>,
                    'Expert': <?php echo json_encode($percentagesExpert); ?>,
                    'Total': <?php echo json_encode($percentagesTotal); ?>
                };
                var statsData = {
                    'chartJunior': <?php echo json_encode($statsJunior); ?>,
                    'chartSenior': <?php echo json_encode($statsSenior); ?>,
                    'chartExpert': <?php echo json_encode($statsExpert); ?>,
                    'chartTotal': <?php echo json_encode($statsTotal); ?>
                };
                
                var statJu = <?php echo json_encode($statsJunior) ?>;
                var statSe = <?php echo json_encode($statsSenior) ?>;
                var statEx = <?php echo json_encode($statsExpert) ?>;
                var statTo = <?php echo json_encode($statsTotal) ?>;
                
                var percentageJu = <?php echo json_encode($percentagesJunior) ?>;
                var percentageSe = <?php echo json_encode($percentagesSenior) ?>;
                var percentageEx = <?php echo json_encode($percentagesExpert) ?>;
                var percentageTo = <?php echo json_encode($percentagesTotal) ?>;

                // Data for each chart
                const chartData = [{
                        title: '<?php echo $junior_tp; ?>',
                        type: ['nonMaitrise', 'singleMaitrise', 'doubleMaitrise', 'others'],
                        total: statJu.totalQuestions,
                        percentage: [percentageJu.nonMaitrise, percentageJu.singleMaitrise, percentageJu.doubleMaitrise, percentageJu.others], // Test réalisés
                        data: [statJu.nonMaitrise, statJu.singleMaitrise, statJu.doubleMaitrise, statJu.othersCount], // Test réalisés vs. Test à réaliser
                        labels: ['<?php echo $legend_zero_mastery; ?>', '<?php echo $legend_one_mastery; ?>','<?php echo $legend_two_mastery; ?>','<?php echo $legend_more_mastery; ?>'],
                        backgroundColor: [
                            '#d3d3d3',   // Gris pour "Aucun technicien maîtrise"
                            '#fddde6',   // Variante claire pour "1 seul technicien maîtrise"
                            '#f8d7da',   // Couleur pour "Seuls 2 techniciens maîtrisent"
                            '#f5c6cb'    // Couleur pour "Plus de 3 techniciens maîtrisent"
                        ]
                    },
                    {
                        title: '<?php echo $senior_tp; ?>',
                        type: ['nonMaitrise', 'singleMaitrise', 'doubleMaitrise', 'others'],
                        total: statSe.totalQuestions,
                        percentage: [percentageSe.nonMaitrise, percentageSe.singleMaitrise, percentageSe.doubleMaitrise, percentageSe.others], // Test réalisés
                        data: [statSe.nonMaitrise, statSe.singleMaitrise, statSe.doubleMaitrise, statSe.othersCount], // Test réalisés vs. Test à réaliser
                        labels: ['<?php echo $legend_zero_mastery; ?>', '<?php echo $legend_one_mastery; ?>','<?php echo $legend_two_mastery; ?>','<?php echo $legend_more_mastery; ?>'],
                        backgroundColor: [
                            '#d3d3d3',   // Gris pour "Aucun technicien maîtrise"
                            '#fddde6',   // Variante claire pour "1 seul technicien maîtrise"
                            '#f8d7da',   // Couleur pour "Seuls 2 techniciens maîtrisent"
                            '#f5c6cb'    // Couleur pour "Plus de 3 techniciens maîtrisent"
                        ]
                    },
                    {
                        title: '<?php echo $expert_tp; ?>',
                        type: ['nonMaitrise', 'singleMaitrise', 'doubleMaitrise', 'others'],
                        total: statEx.totalQuestions,
                        percentage: [percentageEx.nonMaitrise, percentageEx.singleMaitrise, percentageEx.doubleMaitrise, percentageEx.others], // Test réalisés
                        data: [statEx.nonMaitrise, statEx.singleMaitrise, statEx.doubleMaitrise, statEx.othersCount], // Test réalisés vs. Test à réaliser
                        labels: ['<?php echo $legend_zero_mastery; ?>', '<?php echo $legend_one_mastery; ?>','<?php echo $legend_two_mastery; ?>','<?php echo $legend_more_mastery; ?>'],
                        backgroundColor: [
                            '#d3d3d3',   // Gris pour "Aucun technicien maîtrise"
                            '#fddde6',   // Variante claire pour "1 seul technicien maîtrise"
                            '#f8d7da',   // Couleur pour "Seuls 2 techniciens maîtrisent"
                            '#f5c6cb'    // Couleur pour "Plus de 3 techniciens maîtrisent"
                        ]
                    },
                    {
                        title: '<?php echo $total_tp; ?>',
                        type: ['nonMaitrise', 'singleMaitrise', 'doubleMaitrise', 'others'],
                        total: statTo.totalQuestions,
                        percentage: [percentageTo.nonMaitrise, percentageTo.singleMaitrise, percentageTo.doubleMaitrise, percentageTo.others], // Test réalisés
                        data: [statTo.nonMaitrise, statTo.singleMaitrise, statTo.doubleMaitrise, statTo.othersCount], // Test réalisés vs. Test à réaliser
                        labels: ['<?php echo $legend_zero_mastery; ?>', '<?php echo $legend_one_mastery; ?>','<?php echo $legend_two_mastery; ?>','<?php echo $legend_more_mastery; ?>'],
                        backgroundColor: [
                            '#d3d3d3',   // Gris pour "Aucun technicien maîtrise"
                            '#fddde6',   // Variante claire pour "1 seul technicien maîtrise"
                            '#f8d7da',   // Couleur pour "Seuls 2 techniciens maîtrisent"
                            '#f5c6cb'    // Couleur pour "Plus de 3 techniciens maîtrisent"
                        ]
                    }
                ];

                const container = document.getElementById('chartGF');

                // Loop through the data to create and append cards
                chartData.forEach((data, index) => {
                    // Calculate the completed percentage
                    // const completedPercentage = Math.round((data.completed / data.total) * 100);

                    // Create the card element
                    const cardHtml = `
                        <div class="col-md-6 col-lg-3 col-xl-2.5 mb-4">
                            <div class="card h-100">
                                <div class="card-body d-flex justify-content-center text-center flex-column p-4">
                                    <h5>Nombres de Tâches Professionnelles: ${data.total}</h5>
                                    <canvas id="doughnutChart${index}" width="200" height="200"></canvas>
                                    <h5 class="mt-2">${data.title}</h5>
                                </div>
                            </div>
                        </div>
                    `;

                    // Append the card to the container
                    container.insertAdjacentHTML('beforeend', cardHtml);
                    // Initialize the Chart.js doughnut chart
                    new Chart(document.getElementById(`doughnutChart${index}`).getContext('2d'), {
                        type: 'doughnut',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                label: 'Data',
                                data: data.percentage,
                                backgroundColor: data.backgroundColor,
                                borderColor: 'white',
                                borderWidth: 1
                            }],
                        },
                        options: {
                            onClick: function(e, elements) {
                                if (elements.length > 0) {
                                    var elementIndex = elements[0].index;
                                    var segmentType = data.type[elementIndex];
                                    var chartId = data.title;
                            
                                    // Rediriger vers la page correspondante
                                    if (segmentType !== '') {
                                        // Construire l'URL avec les paramètres nécessaires
                                        var level = '';
                                        if (chartId === '<?php echo $junior_tp; ?>') {
                                            level = 'Junior';
                                        } else if (chartId === '<?php echo $senior_tp; ?>') {
                                            level = 'Senior';
                                        } else if (chartId === '<?php echo $expert_tp; ?>') {
                                            level = 'Expert';
                                        } else {
                                            level = 'Total';
                                        }
                                        
                                        var country = '<?php echo urlencode($selectedCountry); ?>'; // Le pays sélectionné
                                        var agency = '<?php echo urlencode($selectedAgency); ?>';  // L'agence sélectionnée
                                        
                                        // Utilisation de AJAX pour charger le contenu du modal
                                        $.ajax({
                                            url: 'listQuestions.php',
                                            type: 'GET',
                                            data: {
                                                level: level,
                                                type: segmentType,
                                                country: country,
                                                agency: agency
                                            },
                                            success: function(response) {
                                                // Injecter le contenu dans le modal
                                                $('#questionsContent').html(response);
                                                
                                                // Mettre à jour le titre du modal avec la variable PHP $task_list
                                                $('#questionsModalLabel').text('Listes des Tâches Professionnelles');
                                                
                                                // Afficher le modal
                                                $('#questionsModal').modal('show');
                                            }
                                        });
                                    }                   
                                }
                            },
                                
                            responsive: true,
                            plugins: {
                                legend: {
                                    position: 'top',
                                    labels: {
                                        // Customize legend labels to include numbers
                                        generateLabels: function(chart) {
                                            const data = chart.data;
                                            return data.labels.map((label, i) => ({
                                                text: `${label}: ${data.datasets[0].data[i]} % T.P`,
                                                fillStyle: data.datasets[0].backgroundColor[
                                                    i],
                                                strokeStyle: data.datasets[0].borderColor[
                                                    i],
                                                lineWidth: data.datasets[0].borderWidth,
                                                hidden: false
                                            }));
                                        }
                                    }
                                },
                                datalabels: {
                                    formatter: (value, ctx) => {
                                        let sum = ctx.chart.data.datasets[0].data.reduce((a, b) => a +
                                            b, 0);
                                        let percentage = Math.round((value / sum) * 100);
                                        // Round up to the nearest whole number
                                        return percentage + '%';
                                    },
                                    color: '#fff',
                                    display: true,
                                    anchor: 'center',
                                    align: 'center',
                                    font: {
                                        size: 16,
                                        weight: 'bold'
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(tooltipItem) {
                                            const value = tooltipItem.raw || 0;
                                            const dataset = tooltipItem.dataset.data;
                                            let sum = dataset.reduce((a, b) => a + b, 0);
                                            let percentage = Math.round((value / sum) * 100);
                                            // Round up to the nearest whole number
                                            return `Nombre: ${value}, Pourcentage: ${percentage}%`;
                                        }
                                    }
                                }
                            }
                        }
                    });
                });
            });
            
            // Graphiques pour les tests du groupe
            document.addEventListener('DOMContentLoaded', function() {
                // Data for each chart
                const chartData = [{
                        title: 'Test Niveau Junior',
                        total: <?php echo count($testsTotalJu) ?>,
                        completed: <?php echo count($testsUserJu) ?>, // Test réalisés
                        data: [<?php echo count($testsUserJu) ?>,
                            <?php echo (count($testsTotalJu) - count($testsUserJu)) ?>
                        ], // Test réalisés vs. Test à réaliser
                        labels: ['Tests réalisés', 'Tests restants à réaliser'],
                        backgroundColor: ['#4303ec', '#D3D3D3'] // Blue and Lightgrey
                    },
                    {
                        title: 'Test Niveau Senior',
                        total: <?php echo count($testsTotalSe) ?>,
                        completed: <?php echo count($testsUserSe) ?>, // Test réalisés
                        data: [<?php echo count($testsUserSe) ?>,
                            <?php echo (count($testsTotalSe) - count($testsUserSe)) ?>
                        ], // Test réalisés vs. Test à réaliser
                        labels: ['Tests réalisés', 'Tests restants à réaliser'],
                        backgroundColor: ['#4303ec', '#D3D3D3'] // Blue and Lightgrey
                    },
                    {
                        title: 'Test Niveau Expert',
                        total: <?php echo count($testsTotalEx) ?>,
                        completed: <?php echo count($testsUserEx) ?>, // Test réalisés
                        data: [<?php echo count($testsUserEx) ?>,
                            <?php echo (count($testsTotalEx) - count($testsUserEx)) ?>
                        ], // Test réalisés vs. Test à réaliser
                        labels: ['Tests réalisés', 'Tests restants à réaliser'],
                        backgroundColor: ['#4303ec', '#D3D3D3'] // Blue and Lightgrey
                    },
                    {
                        title: 'Total : 03 Niveaux',
                        total: <?php echo count($testsTotalJu) + count($testsTotalSe) + count($testsTotalEx) ?>,
                        completed: <?php echo count($testsUserJu) + count($testsUserSe) + count($testsUserEx) ?>, // Test réalisés
                        data: [<?php echo count($testsUserJu) + count($testsUserSe) + count($testsUserEx) ?>,
                            <?php echo (count($testsTotalJu) + count($testsTotalSe) + count($testsTotalEx)) - (count($testsUserJu) + count($testsUserSe) + count($testsUserEx)) ?>
                        ], // Test réalisés vs. Test à réaliser
                        labels: ['Tests réalisés', 'Tests restants à réaliser'],
                        backgroundColor: ['#4303ec', '#D3D3D3'] // Blue and Lightgrey
                    }
                ];
            
                const container = document.getElementById('chartTest');
            
                // Loop through the data to create and append cards
                chartData.forEach((data, index) => {
                    // Calculate the completed percentage
                    const completedPercentage = Math.round((data.completed / data.total) * 100);
            
                    // Create the card element
                    const cardHtml = `
                        <div class="col-md-6 col-lg-3 col-xl-2.5 mb-4">
                            <div class="card h-100">
                                <div class="card-body d-flex justify-content-center text-center flex-column p-4">
                                    <h5>Total des Tests à réaliser: ${data.total}</h5>
                                    <h5><strong>${completedPercentage}%</strong> des tests réalisés</h5>
                                    <canvas id="doughnutChart${index}" width="200" height="200"></canvas>
                                    <h5 class="mt-2">${data.title}</h5>
                                </div>
                            </div>
                        </div>
                    `;
            
                    // Append the card to the container
                    container.insertAdjacentHTML('beforeend', cardHtml);
                    // Initialize the Chart.js doughnut chart
                    new Chart(document.getElementById(`doughnutChart${index}`).getContext('2d'), {
                        type: 'doughnut',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                label: 'Data',
                                data: data.data,
                                backgroundColor: data.backgroundColor,
                                borderColor: data.backgroundColor,
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    position: 'top',
                                    labels: {
                                        // Customize legend labels to include numbers
                                        generateLabels: function(chart) {
                                            const data = chart.data;
                                            return data.labels.map((label, i) => ({
                                                text: `${label}: ${data.datasets[0].data[i]}`,
                                                fillStyle: data.datasets[0].backgroundColor[
                                                    i],
                                                strokeStyle: data.datasets[0].borderColor[
                                                    i],
                                                lineWidth: data.datasets[0].borderWidth,
                                                hidden: false
                                            }));
                                        }
                                    }
                                },
                                datalabels: {
                                    formatter: (value, ctx) => {
                                        let sum = ctx.chart.data.datasets[0].data.reduce((a, b) => a +
                                            b, 0);
                                        let percentage = Math.round((value / sum) * 100);
                                        // Round up to the nearest whole number
                                        return percentage + '%';
                                    },
                                    color: '#fff',
                                    display: true,
                                    anchor: 'center',
                                    align: 'center',
                                    font: {
                                        size: 16,
                                        weight: 'bold'
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(tooltipItem) {
                                            const value = tooltipItem.raw || 0;
                                            const dataset = tooltipItem.dataset.data;
                                            let sum = dataset.reduce((a, b) => a + b, 0);
                                            let percentage = Math.round((value / sum) * 100);
                                            // Round up to the nearest whole number
                                            return `Nombre: ${value}, Pourcentage: ${percentage}%`;
                                        }
                                    }
                                }
                            }
                        }
                    });
                });
            });

            // Graphiques pour les resultats du groupe
            document.addEventListener('DOMContentLoaded', function() {
                // Define color ranges for percentage completion
                const getColorForCompletion = (percentage) => {
                    if (percentage >= 80) return '#6CF95D'; // Green
                    if (percentage >= 60) return '#FAF75A'; // Yellow
                    return '#FB9258'; // Orange
                };

                // Determine the background color for the donut chart
                const getBackgroundColor = (percentage) => {
                    if (percentage === 0) return ['#FFFFFF']; // All white if 0%
                    return [
                        getColorForCompletion(percentage), // Color for the completed part
                        '#DCDCDC' // Grey color for the remaining part
                    ];
                };

                // Data for each chart
                const chartDataM = [{
                        title: 'Résultat <?php echo count($doneTestJuTj) ?> / <?php echo $countUsersJunior ?> Techniciens Niveau Junior',
                        total: 100,
                        completed: <?php echo round(($percentageFacJuTj + $percentageDeclaJuTj) / 2)?>, // Moyenne des compétences acquises
                        data: [<?php echo round(($percentageFacJuTj + $percentageDeclaJuTj) / 2)?>,
                            100 -
                            <?php echo round(($percentageFacJuTj + $percentageDeclaJuTj) / 2)?>
                        ], // Moyenne des compétences acquises vs. Moyenne des compétences à acquérir
                        labels: [
                            '<?php echo round(($percentageFacJuTj + $percentageDeclaJuTj) / 2)?>% des compétences acquises',
                            '<?php echo 100 - round(($percentageFacJuTj + $percentageDeclaJuTj) / 2)?>% des compétences à acquérir'
                        ],
                        backgroundColor: getBackgroundColor(<?php echo round(($percentageFacJuTj + $percentageDeclaJuTj) / 2) ?>)
                    },
                    {
                        title: 'Résultat <?php echo count($doneTestSeTs) ?> / <?php echo ($countUsersSenior ) ?> Techniciens Niveau Senior',
                        total: 100,
                        completed: <?php echo round(($percentageFacSeTs + $percentageDeclaSeTs) / 2)?>, // Moyenne des compétences acquises
                        data: [<?php echo round(($percentageFacSeTs + $percentageDeclaSeTs) / 2)?>,
                            100 -
                            <?php echo round(($percentageFacSeTs + $percentageDeclaSeTs) / 2)?>
                        ], // Moyenne des compétences acquises vs. Moyenne des compétences à acquérir
                        labels: [
                            '<?php echo round(($percentageFacSeTs + $percentageDeclaSeTs) / 2)?>% des compétences acquises',
                            '<?php echo 100 - round(($percentageFacSeTs + $percentageDeclaSeTs) / 2)?>% des compétences à acquérir'
                        ],
                        backgroundColor: getBackgroundColor(<?php echo round(($percentageFacSeTs + $percentageDeclaSeTs) / 2) ?>)
                    },
                    {
                        title: 'Résultat <?php echo count($doneTestExTx) ?> / <?php echo ($countUsersExpert) ?> Techniciens Niveau Expert',
                        total: 100,
                        completed: <?php echo round(($percentageFacEx + $percentageDeclaEx) / 2)?>, // Moyenne des compétences acquises
                        data: [<?php echo round(($percentageFacEx + $percentageDeclaEx) / 2)?>,
                            100 -
                            <?php echo round(($percentageFacEx + $percentageDeclaEx) / 2)?>
                        ], // Moyenne des compétences acquises vs. Moyenne des compétences à acquérir
                        labels: [
                            '<?php echo round(($percentageFacEx + $percentageDeclaEx) / 2)?>% des compétences acquises',
                            '<?php echo 100 - round(($percentageFacEx + $percentageDeclaEx) / 2)?>% des compétences à acquérir'
                        ],
                        backgroundColor: getBackgroundColor(<?php echo round(($percentageFacEx + $percentageDeclaEx) / 2) ?>)
                    },
                ];
                
                // Calculate the average for "Total : 03 Niveaux" based on non-zero values
                const validData = chartDataM.filter(chart => chart.completed > 0);
                const averageCompleted = validData.length > 0 ?
                    Math.round(validData.reduce((acc, chart) => acc + chart.completed, 0) / validData.length) :
                    0;
                const averageData = [averageCompleted, 100 - averageCompleted];
                
                // Determine color based on average completion percentage
                const totalColor = getColorForCompletion(averageCompleted);
                const totalBackgroundColor = getBackgroundColor(averageCompleted);
                
                chartDataM.push({
                    title: 'Résultat <?php echo count($doneTestJuTj) + count($doneTestSeTs) + count($doneTestExTx) ?> / <?php echo ($countUsersTotal) ?> Techniciens Total : 03 Niveaux',
                    total: 100,
                    completed: averageCompleted,
                    data: averageData,
                    labels: [
                        `${averageCompleted}% des compétences acquises`,
                        `${100 - averageCompleted}% des compétences à acquérir`
                    ],
                    backgroundColor: totalBackgroundColor
                });

                const containerM = document.getElementById('chartMoyen');

                // Loop through the data to create and append cards
                chartDataM.forEach((data, index) => {
                    // Calculate the completed percentage
                    const completedPercentage = Math.round((data.completed / data.total) * 100);

                    // Create the card element
                    const cardHtml = `
                        <div class="col-md-6 col-lg-3 col-xl-2.5 mb-4">
                            <div class="card h-100">
                                <div class="card-body d-flex justify-content-center text-center flex-column p-4">
                                    <canvas id="doughnutChart${index}" width="200" height="200"></canvas>
                                    <h5 class="mt-2">${data.title}</h5>
                                </div>
                            </div>
                        </div>
                    `;

                    // Append the card to the container
                    containerM.insertAdjacentHTML('beforeend', cardHtml);

                    // Initialize the Chart.js doughnut chart
                    new Chart(document.getElementById(`doughnutChart${index}`).getContext('2d'), {
                        type: 'doughnut',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                label: 'Data',
                                data: data.data,
                                backgroundColor: data.backgroundColor,
                                borderColor: data.backgroundColor,
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    position: 'top',
                                },
                                datalabels: {
                                    formatter: (value, ctx) => {
                                        let sum = ctx.chart.data.datasets[0].data
                                            .reduce((a, b) => a + b, 0);
                                        let percentage = Math.round((value / sum) *
                                            100
                                        ); // Round up to the nearest whole number
                                        return percentage + '%';
                                    },
                                    color: '#fff',
                                    display: true,
                                    anchor: 'center',
                                    align: 'center',
                                    font: {
                                        size: 16,
                                        weight: 'bold'
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(tooltipItem) {
                                            let percentage = Math.round((tooltipItem.raw / 100) * 100);
                                            return `Compétences acquises: ${percentage}%`;
                                        }
                                    }
                                }
                            }
                        }
                    });
                });
            });
            
            // Graphiques pour les resultats des connaissances et tâches professionnelles

            // Définir les données passées depuis PHP
            var tpMasteryData = <?php echo json_encode($averageMasteryByLevel); ?>;
            var totalTechniciansByLevel = <?php echo json_encode($totalTechniciansByLevel); ?>;
            var technicianCountsByLevel = <?php echo json_encode($technicianCountsByLevel); ?>;
            
            var factMasteryData = <?php echo json_encode($averageMasteryByLevelF); ?>;
            var totalTechniciansByLevelF = <?php echo json_encode($totalTechniciansByLevelF); ?>;
            var technicianCountsByLevelF = <?php echo json_encode($technicianCountsByLevelF); ?>;

            // Fixed list of cities for the x-axis labels
            var label = ['Connaissances', 'Tâches Professionnelles', 'Compétence'];

            function averageCompleted(dataJunior, dataSenior, dataExpert) {
                // Créer un tableau avec les données
                const data = [dataJunior, dataSenior, dataExpert];
                
                // Filtrer les données pour ne garder que celles qui ne sont pas égales à 0
                const filteredData = data.filter(value => value !== 0);
                
                // Si aucune donnée n'est valide, retourner 0 ou une autre valeur par défaut
                if (filteredData.length === 0) {
                    return 0; // ou NaN, ou null, selon ce que vous préférez
                }
                
                // Calculer la somme des valeurs filtrées
                const sum = filteredData.reduce((acc, value) => acc + value, 0);
                
                // Calculer la moyenne
                const moyen = Math.round(sum / filteredData.length);
                
                return moyen;
            }
            
            // Sample test data for Junior, Senior, and Expert levels
            var juniorScore = [factMasteryData.Junior, tpMasteryData.Junior, <?php echo round(($percentageFacJuTj + $percentageDeclaJuTj) / 2)?>]; // Replace with actual junior data
            var seniorScore = [factMasteryData.Senior, tpMasteryData.Senior, <?php echo round(($percentageFacSeTs + $percentageDeclaSeTs) / 2)?>];  // Replace with actual senior data
            var expertScore = [factMasteryData.Expert, tpMasteryData.Expert, <?php echo round(($percentageFacEx + $percentageDeclaEx) / 2)?>]; // Replace with actual expert data
            var averageScore = [averageCompleted(<?php echo round($percentageFacJuTj)?>, <?php echo round($percentageFacSeTs)?>, <?php echo round($percentageFacEx)?>), averageCompleted(<?php echo round($percentageDeclaJuTj)?>, <?php echo round($percentageDeclaSeTs)?>, <?php echo round($percentageDeclaEx)?>), averageCompleted(<?php echo round(($percentageFacJuTj + $percentageDeclaJuTj) / 2)?>, <?php echo round(($percentageFacSeTs + $percentageDeclaSeTs) / 2)?>, <?php echo round(($percentageFacEx + $percentageDeclaEx) / 2)?>)]; // Replace with actual expert data

            // Function to determine bar color based on score value
            function determineColors(score) {
                if (score < 60) {
                    return '#F9945E'; // Orange for scores <= 60
                } else if (score < 80) {
                    return '#F8F75F'; // Yellow for scores between 61-80 
                } else {
                    return '#63FE5A'; // Green for scores > 80
                }
            }
            
            // Function to create the chart for a specific data set and container
            function renderChart(chartId, data, labels) {
                var chartContainer = document.querySelector("#" + chartId);
                if (!chartContainer) {
                    console.error("Chart container with ID " + chartId + " not found.");
                    return;
                }

                var color = data.map(value => determineColors(value)); // Apply dynamic colors based on score

                var chartOption = {
                    chart: {
                        type: 'bar',
                        height: 350,
                        width: 300
                    },
                    series: [{
                        name: "Taux d'acquisition des compétences",
                        data: data
                    }],
                    xaxis: {
                        categories: labels // Use city names for x-axis labels
                    },
                    plotOptions: {
                        bar: {
                            distributed: true, // Ensure each bar gets a different color
                            horizontal: false,
                            borderRadius: 4 // Rounded bar edges
                        }
                    },
                    colors: color, // Dynamic colors
                    dataLabels: {
                        enabled: true,
                        formatter: function(value) {
                            return value + '%'; // Display percentage with each value
                        },
                        style: {
                            colors: ['#333'] // Set text color to white for percentage labels
                        }
                    },
                    tooltip: {
                        enabled: true // Enable tooltips to show values on hover
                    },
                    yaxis: {
                        max: 100 // Limit y-axis to 100
                    }
                };

                var chartX = new ApexCharts(chartContainer, chartOption);
                chartX.render().catch(function(error) {
                    console.error(`Error rendering chart with ID '${chartId}':`, error);
                });
            }

            // Initialize all charts for the different levels and the total score
            function initializeChart() {
                renderChart('result_junior', juniorScore, label);
                renderChart('result_senior', seniorScore, label);
                renderChart('result_expert', expertScore, label);
                renderChart('result_total', averageScore, label);
            }

            // Ensure the charts are initialized after the DOM fully loads
            document.addEventListener('DOMContentLoaded', function() {
                initializeChart();
            });
            
            // Graphiques pour les QCM des connaissances et tâches professionnelles

            // Fixed list of cities for the x-axis labels
            var labelQ = ['Connaissances', 'Tâches Professionnelles', 'Tests'];

            function completedPercentage (completed, total) {
                let moyen = Math.round((completed / total) * 100);
        
                return moyen;
            }
            
            // Sample test data for Junior, Senior, and Expert levels
            var juniorScoreQ = [completedPercentage(<?php echo ($countSavoirJu) ?>, <?php echo ($countUsersTotal) ?>), completedPercentage(<?php echo ($countSavFaiJu) ?>, <?php echo ($countUsersTotal) ?>), completedPercentage(<?php echo count($testsUserJu) ?>, <?php echo count($testsTotalJu) ?>)]; // Replace with actual junior data
            var seniorScoreQ = [completedPercentage(<?php echo ($countSavoirSe) ?>, <?php echo ($countUsersSenior ) + ($countUsersExpert) ?>), completedPercentage(<?php echo ($countSavFaiSe) ?>, <?php echo ($countUsersSenior ) + ($countUsersExpert) ?>), completedPercentage(<?php echo count($testsUserSe) ?>, <?php echo count($testsTotalSe) ?>)];  // Replace with actual senior data
            var expertScoreQ = [completedPercentage(<?php echo ($countSavoirEx) ?>, <?php echo ($countUsersExpert) ?>), completedPercentage(<?php echo ($countSavFaiEx) ?>, <?php echo ($countUsersExpert) ?>), completedPercentage(<?php echo count($testsUserEx) ?>, <?php echo count($testsTotalEx) ?>)]; // Replace with actual expert data
            var averageScoreQ = [completedPercentage(<?php echo ($countSavoirJu) + ($countSavoirSe) + ($countSavoirEx) ?>, <?php echo ($countUsersTotal) + ($countUsersSenior )  + (($countUsersExpert) * 2) ?>), completedPercentage(<?php echo ($countSavFaiJu) + ($countSavFaiSe) + ($countSavFaiEx) ?>, <?php echo ($countUsersTotal) + ($countUsersSenior )  + (($countUsersExpert) * 2) ?>) ,completedPercentage(<?php echo count($testsUserJu) + count($testsUserSe) + count($testsUserEx) ?>, <?php echo count($testsTotalJu) + count($testsTotalSe) + count($testsTotalEx) ?>)]; // Replace with actual expert data

            // Function to create the chart for a specific data set and container
            function renderChartQ(chartId, data, label) {
                var chartContainerQ = document.querySelector("#" + chartId);
                if (!chartContainerQ) {
                    console.error("Chart container with ID " + chartId + " not found.");
                    return;
                }

                var chartOptionQ = {
                    chart: {
                        type: 'bar',
                        height: 350,
                        width: 300
                    },
                    series: [{
                        name: "Taux d'acquisition des compétences",
                        data: data
                    }],
                    xaxis: {
                        categories: label // Use city names for x-axis labels
                    },
                    plotOptions: {
                        bar: {
                            distributed: true, // Ensure each bar gets a different color
                            horizontal: false,
                            borderRadius: 4 // Rounded bar edges
                        }
                    },
                    colors: ['#82CDFF', '#039FFE', '#4303EC'], // Dynamic colors
                    dataLabels: {
                        enabled: true,
                        formatter: function(value) {
                            return value + '%'; // Display percentage with each value
                        },
                        style: {
                            colors: ['#fff'] // Set text color to white for percentage labels
                        }
                    },
                    tooltip: {
                        enabled: true // Enable tooltips to show values on hover
                    },
                    yaxis: {
                        max: 100 // Limit y-axis to 100
                    }
                };

                var chartQ = new ApexCharts(chartContainerQ, chartOptionQ);
                chartQ.render().catch(function(error) {
                    console.error(`Error rendering chart with ID '${chartId}':`, error);
                });
            }

            // Initialize all charts for the different levels and the total score
            function initialiseChart() {
                renderChartQ('qcm_junior', juniorScoreQ, labelQ);
                renderChartQ('qcm_senior', seniorScoreQ, labelQ);
                renderChartQ('qcm_expert', expertScoreQ, labelQ);
                renderChartQ('qcm_total', averageScoreQ, labelQ);
            }

            // Ensure the charts are initialized after the DOM fully loads
            document.addEventListener('DOMContentLoaded', function() {
                initialiseChart();
            });
            
            // Graphiques pour les resultats des techniciens par niveau

            // Fixed list of cities for the x-axis labels
            var cityLabelJu = ['<?php echo ($countUsersJunior ) ?> Techniciens Junior', '<?php echo ($countUsersSenior ) ?> Techniciens Senior', '<?php echo ($countUsersExpert) ?> Techniciens Expert'];
            var cityLabelSe = ['<?php echo ($countUsersSenior ) ?> Techniciens Senior', '<?php echo ($countUsersExpert) ?> Techniciens Expert', ''];
            var cityLabelEx = ['<?php echo ($countUsersExpert) ?> Techniciens Expert', '', ''];
            var cityLabels = ['Total Niveau Junior', 'Total Niveau Senior', 'Total Niveau Expert'];

            // Sample test data for Junior, Senior, and Expert levels
            var juniorScores = [<?php echo round(($percentageFacJuTj + $percentageDeclaJuTj) / 2)?>, <?php echo round(($percentageFacJuTs + $percentageDeclaJuTs) / 2)?>, <?php echo round(($percentageFacJuTx + $percentageDeclaJuTx) / 2)?>]; // Replace with actual junior data
            var seniorScores = [<?php echo round(($percentageFacSeTs + $percentageDeclaSeTs) / 2)?>, <?php echo round(($percentageFacSeTx + $percentageDeclaSeTx) / 2)?>, 0];  // Replace with actual senior data
            var expertScores = [<?php echo round(($percentageFacEx + $percentageDeclaEx) / 2) ?>, 0, 0]; // Replace with actual expert data
            var averageScores = [<?php echo round(($percentageFacJu + $percentageDeclaJu) / 2) ?>, <?php echo round(($percentageFacSe + $percentageDeclaSe) / 2) ?>, <?php echo round(($percentageFacEx + $percentageDeclaEx) / 2) ?>]; // Replace with actual expert data

            // Function to determine bar color based on score value
            function determineColor(score) {
                if (score < 60) {
                    return '#F9945E'; // Orange for scores <= 60
                } else if (score < 80) {
                    return '#F8F75F'; // Yellow for scores between 61-80 
                } else {
                    return '#63FE5A'; // Green for scores > 80
                }
            }

            // Function to create the chart for a specific data set and container
            function renderCharts(chartId, data, labels) {
                var chartContainers = document.querySelector("#" + chartId);
                if (!chartContainers) {
                    console.error("Chart container with ID " + chartId + " not found.");
                    return;
                }

                var colors = data.map(value => determineColor(value)); // Apply dynamic colors based on score

                var chartOptions = {
                    chart: {
                        type: 'bar',
                        height: 350,
                        width: 300
                    },
                    series: [{
                        name: "Taux d'acquisition des compétences",
                        data: data
                    }],
                    xaxis: {
                        categories: labels // Use city names for x-axis labels
                    },
                    plotOptions: {
                        bar: {
                            distributed: true, // Ensure each bar gets a different color
                            horizontal: false,
                            borderRadius: 4 // Rounded bar edges
                        }
                    },
                    colors: colors, // Dynamic colors
                    dataLabels: {
                        enabled: true,
                        formatter: function(value) {
                            return value + '%'; // Display percentage with each value
                        },
                        style: {
                            colors: ['#333'] // Set text color to white for percentage labels
                        }
                    },
                    tooltip: {
                        enabled: true // Enable tooltips to show values on hover
                    },
                    yaxis: {
                        max: 100 // Limit y-axis to 100
                    }
                };

                var chart = new ApexCharts(chartContainers, chartOptions);
                chart.render().catch(function(error) {
                    console.error(`Error rendering chart with ID '${chartId}':`, error);
                });
            }

            // Initialize all charts for the different levels and the total score
            function initializeCharts() {
                renderCharts('chart_junior', juniorScores, cityLabelJu);
                renderCharts('chart_senior', seniorScores, cityLabelSe);
                renderCharts('chart_expert', expertScores, cityLabelEx);
                renderCharts('chart_total', averageScores, cityLabels);
            }

            // Ensure the charts are initialized after the DOM fully loads
            document.addEventListener('DOMContentLoaded', function() {
                initializeCharts();
            });

        </script>
    <?php } else { ?>
        <script>
            // Graphique pour les tests de la filiale
            document.addEventListener('DOMContentLoaded', function() {
                // Data for each chart
                const chartData = [{
                        title: 'Test Niveau Junior',
                        total: <?php echo count($testTotalJu) ?>,
                        completed: <?php echo count($testsJu) ?>, // Test réalisés
                        data: [<?php echo count($testsJu) ?>,
                            <?php echo (count($testTotalJu) - count($testsJu)) ?>
                        ], // Test réalisés vs. Test à réaliser
                        labels: ['<?php echo count($testsJu) ?> Tests réalisés',
                            '<?php echo (count($testTotalJu)) - (count($testsJu)) ?> Tests restants à réaliser'
                        ],
                        backgroundColor: ['#4303ec', '#D3D3D3'] // Blue and Lightgrey
                    },
                    {
                        title: 'Test Niveau Senior',
                        total: <?php echo count($testTotalSe) ?>,
                        completed: <?php echo count($testsSe) ?>, // Test réalisés
                        data: [<?php echo count($testsSe) ?>,
                            <?php echo (count($testTotalSe) - count($testsSe)) ?>
                        ], // Test réalisés vs. Test à réaliser
                        labels: ['<?php echo count($testsSe) ?> Tests réalisés',
                            '<?php echo (count($testTotalSe)) - (count($testsSe)) ?> Tests restants à réaliser'
                        ],
                        backgroundColor: ['#4303ec', '#D3D3D3'] // Blue and Lightgrey
                    },
                    {
                        title: 'Test Niveau Expert',
                        total: <?php echo count($testTotalEx) ?>,
                        completed: <?php echo count($testsEx) ?>, // Test réalisés
                        data: [<?php echo count($testsEx) ?>,
                            <?php echo (count($testTotalEx) - count($testsEx)) ?>
                        ], // Test réalisés vs. Test à réaliser
                        labels: ['<?php echo count($testsEx) ?> Tests réalisés',
                            '<?php echo (count($testTotalEx)) - (count($testsEx)) ?> Tests restants à réaliser'
                        ],
                        backgroundColor: ['#4303ec', '#D3D3D3'] // Blue and Lightgrey
                    },
                    {
                        title: 'Total : 03 Niveaux',
                        total: <?php echo count($testTotalJu) + count($testTotalSe) + count($testTotalEx) ?>,
                        completed: <?php echo count($testsJu) + count($testsSe) + count($testsEx) ?>, // Test réalisés
                        data: [<?php echo count($testsJu) + count($testsSe) + count($testsEx) ?>,
                            <?php echo (count($testTotalJu) + count($testTotalSe) + count($testTotalEx)) - (count($testsJu) + count($testsSe) + count($testsEx)) ?>
                        ], // Test réalisés vs. Test à réaliser
                        labels: ['<?php echo count($testsJu) + count($testsSe) + count($testsEx) ?> Tests réalisés',
                            '<?php echo (count($testTotalJu) + count($testTotalSe) + count($testTotalEx)) - (count($testsJu) + count($testsSe) + count($testsEx)) ?> Tests restants à réaliser'
                        ],
                        backgroundColor: ['#4303ec', '#D3D3D3'] // Blue and Lightgrey
                    }
                ];

                const container = document.getElementById('chartTestFiliale');

                // Loop through the data to create and append cards
                chartData.forEach((data, index) => {
                    // Calculate the completed percentage
                    if (data.total == 0) {
                        var completedPercentage = 0;
                    } else {
                        var completedPercentage = Math.round((data.completed / data.total) * 100);
                    }

                    // Create the card element
                    const cardHtml = `
                        <div class="col-md-6 col-lg-3 col-xl-2.5 mb-4">
                            <div class="card h-100">
                                <div class="card-body d-flex justify-content-center text-center flex-column p-4">
                                    <h5>Total des Tests à réaliser: ${data.total}</h5>
                                    <h5>Pourcentage complété: ${completedPercentage}%</h5>
                                    <canvas id="doughnutChart${index}" width="200" height="200"></canvas>
                                    <h5 class="mt-2">${data.title}</h5>
                                </div>
                            </div>
                        </div>
                    `;

                    // Append the card to the container
                    container.insertAdjacentHTML('beforeend', cardHtml);

                    // Initialize the Chart.js doughnut chart
                    new Chart(document.getElementById(`doughnutChart${index}`).getContext('2d'), {
                        type: 'doughnut',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                label: 'Data',
                                data: data.data,
                                backgroundColor: data.backgroundColor,
                                borderColor: data.backgroundColor,
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    position: 'top',
                                    labels: {
                                        // Customize legend labels to include numbers
                                        generateLabels: function(chart) {
                                            const data = chart.data;
                                            return data.labels.map((label, i) => ({
                                                text: `${label}`,
                                                fillStyle: data.datasets[0].backgroundColor[
                                                    i],
                                                strokeStyle: data.datasets[0].borderColor[
                                                    i],
                                                lineWidth: data.datasets[0].borderWidth,
                                                hidden: false
                                            }));
                                        }
                                    }
                                },
                                datalabels: {
                                    formatter: (value, ctx) => {
                                        let sum = ctx.chart.data.datasets[0].data.reduce((a, b) => a +
                                            b, 0);
                                        let percentage = Math.round((value / sum) * 100);
                                        // Round up to the nearest whole number
                                        return percentage + '%';
                                    },
                                    color: '#fff',
                                    display: true,
                                    anchor: 'center',
                                    align: 'center',
                                    font: {
                                        size: 16,
                                        weight: 'bold'
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(tooltipItem) {
                                            const value = tooltipItem.raw;
                                            const total = tooltipItem.chart.data.datasets[0].data
                                                .reduce((a, b) => a + b, 0);
                                            const percentage = Math.round((value / total) * 100);
                                            return `Nombre: ${value},\nPourcentage: ${percentage}%`;

                                        }
                                    }
                                }
                            }
                        }
                    });
                });
            });

            // Graphique pour les resultats  de la filiale
            document.addEventListener('DOMContentLoaded', function() {
                // Data for each chart
                const chartDataM = [
                    {
                        title: 'Résultat <?php echo count($doneTestJuTjF) ?> / <?php echo $techniciansJu?> Techniciens Niveau Junior',
                        total: 100,
                        completed: <?php echo round(($percentageFacJuTjF + $percentageDeclaJuTjF) / 2)?>, // Moyenne des compétences acquises
                        data: [<?php echo round(($percentageFacJuTjF + $percentageDeclaJuTjF) / 2)?>,
                            100 -
                            <?php echo round(($percentageFacJuTjF + $percentageDeclaJuTjF) / 2)?>
                        ], // Moyenne des compétences acquises vs. Moyenne des compétences à acquérir
                        labels: [
                            '<?php echo round(($percentageFacJuTjF + $percentageDeclaJuTjF) / 2)?>% des compétences acquises',
                            '<?php echo 100 - round(($percentageFacJuTjF + $percentageDeclaJuTjF) / 2)?>% des compétences à acquérir'
                        ],
                        backgroundColor: getBackgroundColor(<?php echo round(($percentageFacJuTjF + $percentageDeclaJuTjF) / 2) ?>)
                    },
                    {
                        title: 'Résultat <?php echo count($doneTestSeTsF) ?> / <?php echo ($techniciansSe)?> Techniciens Niveau Senior',
                        total: 100,
                        completed: <?php echo round(($percentageFacSeTsF + $percentageDeclaSeTsF) / 2)?>, // Moyenne des compétences acquises
                        data: [<?php echo round(($percentageFacSeTsF + $percentageDeclaSeTsF) / 2)?>,
                            100 -
                            <?php echo round(($percentageFacSeTsF + $percentageDeclaSeTsF) / 2)?>
                        ], // Moyenne des compétences acquises vs. Moyenne des compétences à acquérir
                        labels: [
                            '<?php echo round(($percentageFacSeTsF + $percentageDeclaSeTsF) / 2)?>% des compétences acquises',
                            '<?php echo 100 - round(($percentageFacSeTsF + $percentageDeclaSeTsF) / 2)?>% des compétences à acquérir'
                        ],
                        backgroundColor: getBackgroundColor(<?php echo round(($percentageFacSeTsF + $percentageDeclaSeTsF) / 2) ?>)
                    },
                    {
                        title: 'Résultat <?php echo count($doneTestExTxF) ?> / <?php echo ($techniciansEx)?> Techniciens Niveau Expert',
                        total: 100,
                        completed: <?php echo round(($percentageFacExF + $percentageDeclaExF) / 2)?>, // Moyenne des compétences acquises
                        data: [<?php echo round(($percentageFacExF + $percentageDeclaExF) / 2)?>,
                            100 -
                            <?php echo round(($percentageFacExF + $percentageDeclaExF) / 2)?>
                        ], // Moyenne des compétences acquises vs. Moyenne des compétences à acquérir
                        labels: [
                            '<?php echo round(($percentageFacExF + $percentageDeclaExF) / 2)?>% des compétences acquises',
                            '<?php echo 100 - round(($percentageFacExF + $percentageDeclaExF) / 2)?>% des compétences à acquérir'
                        ],
                        backgroundColor: getBackgroundColor(<?php echo round(($percentageFacExF + $percentageDeclaExF) / 2) ?>)
                    }
                ];
            
                // Calculate the average for "Total : 03 Niveaux" based on non-zero values
                const validData = chartDataM.filter(chart => chart.completed > 0);
                const averageCompleted = validData.length > 0 ?
                    Math.round(validData.reduce((acc, chart) => acc + chart.completed, 0) / validData.length) :
                    0;
                const averageData = [averageCompleted, 100 - averageCompleted];
            
                // Determine color based on average completion percentage
                const totalColor = getColorForCompletion(averageCompleted);
                const totalBackgroundColor = getBackgroundColor(averageCompleted);
            
                chartDataM.push({
                    title: 'Résultat <?php echo count($doneTestJuTjF) + count($doneTestSeTsF) + count($doneTestExTxF) ?> / <?php echo ($techniciansFi)?> Techniciens Total : 03 Niveaux',
                    total: 100,
                    completed: averageCompleted,
                    data: averageData,
                    labels: [
                        `${averageCompleted}% des compétences acquises`,
                        `${100 - averageCompleted}% des compétences à acquérir`
                    ],
                    backgroundColor: totalBackgroundColor
                });
            
                const containerM = document.getElementById('chartMoyenFiliale');
            
                // Loop through the data to create and append cards
                chartDataM.forEach((data, index) => {
                    // Create the card element
                    const cardHtml = `
                        <div class="col-md-6 col-lg-3 col-xl-2.5 mb-4">
                            <div class="card h-100">
                                <div class="card-body d-flex justify-content-center text-center flex-column p-4">
                                    <canvas id="doughnutChart${index}" width="200" height="200"></canvas>
                                    <h5 class="mt-2">${data.title}</h5>
                                </div>
                            </div>
                        </div>
                    `;
            
                    // Append the card to the container
                    containerM.insertAdjacentHTML('beforeend', cardHtml);
            
                    // Initialize the Chart.js doughnut chart
                    new Chart(document.getElementById(`doughnutChart${index}`).getContext('2d'), {
                        type: 'doughnut',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                label: 'Data',
                                data: data.data,
                                backgroundColor: data.backgroundColor,
                                borderWidth: 0 // Remove the border
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    position: 'top',
                                },
                                datalabels: {
                                    formatter: (value, ctx) => {
                                        let sum = ctx.chart.data.datasets[0].data
                                            .reduce((a, b) => a + b, 0);
                                        let percentage = Math.round((value / sum) * 100); // Round up to the nearest whole number
                                        return percentage + '%';
                                    },
                                    color: '#fff',
                                    display: true,
                                    anchor: 'center',
                                    align: 'center',
                                    font: {
                                        size: 16,
                                        weight: 'bold'
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(tooltipItem) {
                                            let percentage = Math.round((tooltipItem.raw / 100) * 100);
                                            return `Compétences acquises: ${percentage}%`;
                                        }
                                    }
                                }
                            }
                        }
                    });
                });
            });
            // Define color ranges for percentage completion
            const getColorForCompletion = (percentage) => {
                if (percentage >= 80) return '#6CF95D'; // Green
                if (percentage >= 60) return '#FAF75A'; // Yellow
                return '#FB9258'; // Orange
            };
        
            // Determine the background color for the donut chart
            const getBackgroundColor = (percentage) => {
                if (percentage === 0) return ['#FFFFFF']; // All white if 0%
                return [
                    getColorForCompletion(percentage), // Color for the completed part
                    '#DCDCDC' // Grey color for the remaining part
                ];
            };
            // Graphiques pour les resultats des connaissances et tâches professionnelles

            // Fixed list of cities for the x-axis labels
            var cityLabelJu = ['<?php echo $techniciansJu ?> Techniciens Junior', '<?php echo ($techniciansSe) ?> Techniciens Senior', '<?php echo ($techniciansEx) ?> Techniciens Expert'];
            var cityLabelSe = ['<?php echo ($techniciansSe) ?> Techniciens Senior', '<?php echo ($techniciansEx) ?> Techniciens Expert', ''];
            var cityLabelEx = ['<?php echo ($techniciansEx) ?> Techniciens Expert', '', ''];
            var cityLabels = ['Total Niveau Junior', 'Total Niveau Senior', 'Total Niveau Expert'];

            // Sample test data for Junior, Senior, and Expert levels
            var juniorScores = [<?php echo round(($percentageFacJuTjF + $percentageDeclaJuTjF) / 2)?>, <?php echo round(($percentageFacJuTsF + $percentageDeclaJuTsF) / 2)?>, <?php echo round(($percentageFacJuTxF + $percentageDeclaJuTxF) / 2)?>]; // Replace with actual junior data
            var seniorScores = [<?php echo round(($percentageFacSeTsF + $percentageDeclaSeTsF) / 2)?>, <?php echo round(($percentageFacSeTxF + $percentageDeclaSeTxF) / 2)?>, 0];  // Replace with actual senior data
            var expertScores = [<?php echo round(($percentageFacExF + $percentageDeclaExF) / 2) ?>, 0, 0]; // Replace with actual expert data
            var averageScores = [<?php echo round(($percentageFacJuF + $percentageDeclaJuF) / 2) ?>, <?php echo round(($percentageFacSeF + $percentageDeclaSeF) / 2) ?>, <?php echo round(($percentageFacExF + $percentageDeclaExF) / 2) ?>]; // Replace with actual expert data

            // Function to determine bar color based on score value
            function determineColor(score) {
                if (score < 60) {
                    return '#F9945E'; // Orange for scores <= 60
                } else if (score <= 80) {
                    return '#F8F75F'; // Yellow for scores between 61-80 
                } else {
                    return '#63FE5A'; // Green for scores > 80
                }
            }

            // Function to create the chart for a specific data set and container
            function renderChart(chartId, data, labels) {
                var chartContainer = document.querySelector("#" + chartId);
                if (!chartContainer) {
                    console.error("Chart container with ID " + chartId + " not found.");
                    return;
                }

                var colors = data.map(value => determineColor(value)); // Apply dynamic colors based on score

                var chartOptions = {
                    chart: {
                        type: 'bar',
                        height: 350,
                        width: 300
                    },
                    series: [{
                        name: "Taux d'acquisition des compétences",
                        data: data
                    }],
                    xaxis: {
                        categories: labels // Use city names for x-axis labels
                    },
                    plotOptions: {
                        bar: {
                            distributed: true, // Ensure each bar gets a different color
                            horizontal: false,
                            borderRadius: 4 // Rounded bar edges
                        }
                    },
                    colors: colors, // Dynamic colors
                    dataLabels: {
                        enabled: true,
                        formatter: function(value) {
                            return value + '%'; // Display percentage with each value
                        },
                        style: {
                            colors: ['#333'] // Set text color to white for percentage labels
                        }
                    },
                    tooltip: {
                        enabled: true // Enable tooltips to show values on hover
                    },
                    yaxis: {
                        max: 100 // Limit y-axis to 100
                    }
                };

                var chart = new ApexCharts(chartContainer, chartOptions);
                chart.render().catch(function(error) {
                    console.error("Error rendering chart:", error);
                });
            }

            // Initialize all charts for the different levels and the total score
            function initializeCharts() {
                renderChart('chart_junior_filiale', juniorScores, cityLabelJu);
                renderChart('chart_senior_filiale', seniorScores, cityLabelSe);
                renderChart('chart_expert_filiale', expertScores, cityLabelEx);
                renderChart('chart_total_filiale', averageScores, cityLabels);
            }

            // Ensure the charts are initialized after the DOM fully loads
            document.addEventListener('DOMContentLoaded', function() {
                initializeCharts();
            });
            
            // Graphiques pour les QCM des connaissances et tâches professionnelles
        
            // Fixed list of cities for the x-axis labels
            var labelQ = ['Connaissances', 'Tâches Professionnelles', 'Tests'];
            
            function completedPercentage (completed, total) {
                let moyen = Math.round((completed / total) * 100);
        
                return moyen;
            }
            
            // Sample test data for Junior, Senior, and Expert levels
            var juniorScoreQ = [completedPercentage(<?php echo ($countSavoirsJu) ?>, <?php echo ($techniciansFi) ?>), completedPercentage(<?php echo ($countSavFaisJu) ?>, <?php echo ($techniciansFi) ?>), completedPercentage(<?php echo count($testsJu) ?>, <?php echo count($testTotalJu) ?>)]; // Replace with actual junior data
            var seniorScoreQ = [completedPercentage(<?php echo ($countSavoirsSe) ?>, <?php echo ($techniciansSe) + ($techniciansEx) ?>), completedPercentage(<?php echo ($countSavFaisSe) ?>, <?php echo ($techniciansSe) + ($techniciansEx) ?>), completedPercentage(<?php echo count($testsSe) ?>, <?php echo count($testTotalSe) ?>)];  // Replace with actual senior data
            var expertScoreQ = [completedPercentage(<?php echo ($countSavoirsEx) ?>, <?php echo ($techniciansEx) ?>), completedPercentage(<?php echo ($countSavFaisEx) ?>, <?php echo ($techniciansEx) ?>), completedPercentage(<?php echo count($testsEx) ?>, <?php echo count($testTotalEx) ?>)]; // Replace with actual expert data
            var averageScoreQ = [completedPercentage(<?php echo ($countSavoirsJu) + ($countSavoirsSe) + ($countSavoirsEx) ?>, <?php echo ($techniciansFi) + ($techniciansSe)  + (($techniciansEx) * 2) ?>), completedPercentage(<?php echo ($countSavFaisJu) + ($countSavFaisSe) + ($countSavFaisEx) ?>, <?php echo ($techniciansFi) + ($techniciansSe)  + (($techniciansEx) * 2) ?>) ,completedPercentage(<?php echo count($testsJu) + count($testsSe) + count($testsEx) ?>, <?php echo count($testTotalJu) + count($testTotalSe) + count($testTotalEx) ?>)]; // Replace with actual expert data
                    
            // Function to create the chart for a specific data set and container
            function renderChartQ(chartId, data, label) {
                var chartContainerQ = document.querySelector("#" + chartId);
                if (!chartContainerQ) {
                    console.error("Chart container with ID " + chartId + " not found.");
                    return;
                }
        
                var chartOptionQ = {
                    chart: {
                        type: 'bar',
                        height: 350,
                        width: 300
                    },
                    series: [{
                        name: "Taux d'acquisition des compétences",
                        data: data
                    }],
                    xaxis: {
                        categories: label // Use city names for x-axis labels
                    },
                    plotOptions: {
                        bar: {
                            distributed: true, // Ensure each bar gets a different color
                            horizontal: false,
                            borderRadius: 4 // Rounded bar edges
                        }
                    },
                    colors: ['#82CDFF', '#039FFE', '#4303EC'], // Dynamic colors
                    dataLabels: {
                        enabled: true,
                        formatter: function(value) {
                            return value + '%'; // Display percentage with each value
                        },
                        style: {
                            colors: ['#fff'] // Set text color to white for percentage labels
                        }
                    },
                    tooltip: {
                        enabled: true // Enable tooltips to show values on hover
                    },
                    yaxis: {
                        max: 100 // Limit y-axis to 100
                    }
                };
        
                var chartQ = new ApexCharts(chartContainerQ, chartOptionQ);
                chartQ.render().catch(function(error) {
                    console.error(`Error rendering chart with ID '${chartId}':`, error);
                });
            }
        
            // Initialize all charts for the different levels and the total score
            function initialiseChart() {
                renderChartQ('qcm_junior_filiale', juniorScoreQ, labelQ);
                renderChartQ('qcm_senior_filiale', seniorScoreQ, labelQ);
                renderChartQ('qcm_expert_filiale', expertScoreQ, labelQ);
                renderChartQ('qcm_total_filiale', averageScoreQ, labelQ);
            }
        
            // Ensure the charts are initialized after the DOM fully loads
            document.addEventListener('DOMContentLoaded', function() {
                initialiseChart();
            });
            
            // Graphiques pour les resultats des connaissances et tâches professionnelles

            // Fixed list of cities for the x-axis labels
            var label = ['Connaissances', 'Tâches Professionnelles', 'Compétence'];

            function averageCompleted(dataJunior, dataSenior, dataExpert) {
                // Créer un tableau avec les données
                const data = [dataJunior, dataSenior, dataExpert];
                
                // Filtrer les données pour ne garder que celles qui ne sont pas égales à 0
                const filteredData = data.filter(value => value !== 0);
                
                // Si aucune donnée n'est valide, retourner 0 ou une autre valeur par défaut
                if (filteredData.length === 0) {
                    return 0; // ou NaN, ou null, selon ce que vous préférez
                }
                
                // Calculer la somme des valeurs filtrées
                const sum = filteredData.reduce((acc, value) => acc + value, 0);
                
                // Calculer la moyenne
                const moyen = Math.round(sum / filteredData.length);
                
                return moyen;
            }
            
            // Sample test data for Junior, Senior, and Expert levels
            var juniorScore = [<?php echo round($percentageFacJuTjF) ?>, <?php echo round($percentageDeclaJuTjF) ?>, <?php echo round(($percentageFacJuTjF + $percentageDeclaJuTjF) / 2)?>]; // Replace with actual junior data
            var seniorScore = [<?php echo round($percentageFacSeTsF) ?>, <?php echo round($percentageDeclaSeTsF) ?>, <?php echo round(($percentageFacSeTsF + $percentageDeclaSeTsF) / 2)?>];  // Replace with actual senior data
            var expertScore = [<?php echo round($percentageFacExF) ?>, <?php echo round($percentageDeclaExF) ?>, <?php echo round(($percentageFacExF + $percentageDeclaExF) / 2)?>]; // Replace with actual expert data
            var averageScore = [averageCompleted(<?php echo round($percentageFacJuTjF)?>, <?php echo round($percentageFacSeTsF)?>, <?php echo round($percentageFacExF)?>), averageCompleted(<?php echo round($percentageDeclaJuTjF)?>, <?php echo round($percentageDeclaSeTsF)?>, <?php echo round($percentageDeclaExF)?>), averageCompleted(<?php echo round(($percentageFacJuTjF + $percentageDeclaJuTjF) / 2)?>, <?php echo round(($percentageFacSeTsF + $percentageDeclaSeTsF) / 2)?>, <?php echo round(($percentageFacExF + $percentageDeclaExF) / 2)?>)]; // Replace with actual expert data

            // Function to determine bar color based on score value
            function determineColors(score) {
                if (score < 60) {
                    return '#F9945E'; // Orange for scores <= 60
                } else if (score < 80) {
                    return '#F8F75F'; // Yellow for scores between 61-80 
                } else {
                    return '#63FE5A'; // Green for scores > 80
                }
            }
            
            // Function to create the chart for a specific data set and container
            function renderChart(chartId, data, labels) {
                var chartContainer = document.querySelector("#" + chartId);
                if (!chartContainer) {
                    console.error("Chart container with ID " + chartId + " not found.");
                    return;
                }

                var color = data.map(value => determineColors(value)); // Apply dynamic colors based on score

                var chartOption = {
                    chart: {
                        type: 'bar',
                        height: 350,
                        width: 300
                    },
                    series: [{
                        name: "Taux d'acquisition des compétences",
                        data: data
                    }],
                    xaxis: {
                        categories: labels // Use city names for x-axis labels
                    },
                    plotOptions: {
                        bar: {
                            distributed: true, // Ensure each bar gets a different color
                            horizontal: false,
                            borderRadius: 4 // Rounded bar edges
                        }
                    },
                    colors: color, // Dynamic colors
                    dataLabels: {
                        enabled: true,
                        formatter: function(value) {
                            return value + '%'; // Display percentage with each value
                        },
                        style: {
                            colors: ['#333'] // Set text color to white for percentage labels
                        }
                    },
                    tooltip: {
                        enabled: true // Enable tooltips to show values on hover
                    },
                    yaxis: {
                        max: 100 // Limit y-axis to 100
                    }
                };

                var chartX = new ApexCharts(chartContainer, chartOption);
                chartX.render().catch(function(error) {
                    console.error(`Error rendering chart with ID '${chartId}':`, error);
                });
            }

            // Initialize all charts for the different levels and the total score
            function initializeChart() {
                renderChart('result_junior_filiale', juniorScore, label);
                renderChart('result_senior_filiale', seniorScore, label);
                renderChart('result_expert_filiale', expertScore, label);
                renderChart('result_total_filiale', averageScore, label);
            }

            // Ensure the charts are initialized after the DOM fully loads
            document.addEventListener('DOMContentLoaded', function() {
                initializeChart();
            });
        </script>
    <?php } ?>