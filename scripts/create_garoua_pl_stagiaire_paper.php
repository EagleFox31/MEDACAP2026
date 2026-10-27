<?php
require_once __DIR__ . '/../core/medacap_config.php';
/**
 * Crée l'épreuve figée pour le recrutement Mécanicien Poids Lourds Stagiaire — Garoua 2026.
 *
 * Répartition validée :
 *   - Total : 100 questions QCM
 *   - Durée : 1h30 (90 min)
 *   - Niveaux : 60% Junior (60 Q) / 40% Senior (40 Q)
 *   - Compétences :
 *       * Mécanique & Organes : 55 questions (33 Junior / 22 Senior)
 *       * Électricité Poids Lourds : 25 questions (15 Junior / 10 Senior)
 *       * Pneumatique & Freinage : 20 questions (12 Junior / 8 Senior)
 *   - Pack orientation RH : 7 questions
 *
 * Usage:
 *   php scripts/create_garoua_pl_stagiaire_paper.php --dry-run
 *   php scripts/create_garoua_pl_stagiaire_paper.php
 */
require __DIR__ . '/../vendor/autoload.php';

use MongoDB\Client;
use MongoDB\BSON\ObjectId;

$dryRun = in_array('--dry-run', $argv, true);
$uri = medacapMongoUri('mongodb://10.68.0.7:27017');
$mongo = new Client($uri);
$db = $mongo->academy;

const LABEL = 'Recrutement Mécanicien PL Stagiaire - Garoua 2026';
const DURATION_HOUR = 1;
const DURATION_MINUTE = 30;

const BRANDS = [
    'FUSO',
    'HINO',
    'MERCEDES TRUCK',
    'RENAULT TRUCK',
    'SINOTRUK',
];

const DOMAINS = [
    'Mecanique' => [
        'label' => 'Mécanique & Chaîne cinématique',
        'target_junior' => 33,
        'target_senior' => 22,
        'specs' => [
            'Transversale',
            'Moteur Thermique',
            'Moteur Diesel',
            'Boite de Vitesse Mécanique',
            'Boite de Vitesse',
            'Boite de Transfert',
            'Pont',
            'Arbre de Transmission',
            'Demi Arbre de Roue',
            'Reducteur',
            'Direction',
            'Suspension',
            'Suspension à Lame',
            'Suspension Pneumatique',
            'Hydraulique',
        ]
    ],
    'Electricite' => [
        'label' => 'Électricité Poids Lourds',
        'target_junior' => 15,
        'target_senior' => 10,
        'specs' => [
            'Electricité et Electronique',
            'Electricité Poids Lourds',
        ]
    ],
    'Pneumatique' => [
        'label' => 'Pneumatique & Freinage',
        'target_junior' => 12,
        'target_senior' => 8,
        'specs' => [
            'Freinage Pneumatique',
            'Pneumatique',
            'Freinage',
        ]
    ]
];

const ORIENTATION_IDS = [
    '6944558e72d68a2edb25d834', // Conduite poids lourd
    '6944559e72d68a2edb25d835', // Ville préférée
    '694455bf72d68a2edb25d836', // Mobilité au Cameroun
    '694455ca72d68a2edb25d837', // Mobilité internationale
    '694455e172d68a2edb25d838', // Situation matrimoniale
    '694455f972d68a2edb25d839', // Nombre d'enfants
    '6944563d72d68a2edb25d83a', // Salaire attendu
];

echo "===============================================================\n";
echo "  Génération Épreuve : " . LABEL . "\n";
echo "  MongoDB: $uri\n";
echo "  Mode: " . ($dryRun ? "DRY-RUN (simulation sans écriture)" : "INSERTION DIRECTE") . "\n";
echo "===============================================================\n\n";

// 1. Récupération des quizzes associés aux marques PL
$vehFilter = ['brand' => ['$in' => BRANDS], 'type' => 'Factuel', 'active' => true];
$quizIds = [];
foreach ($db->vehicles->find($vehFilter, ['projection' => ['quizzes' => 1]]) as $veh) {
    if (!empty($veh['quizzes'])) {
        foreach ($veh['quizzes'] as $qid) {
            $quizIds[(string)$qid] = ($qid instanceof ObjectId) ? $qid : new ObjectId((string)$qid);
        }
    }
}
echo "1. Quizzes PL référencés : " . count($quizIds) . "\n";

// 2. Indexation des quizzes par niveau et par spécialité
$quizzesCursor = $db->quizzes->find([
    '_id' => ['$in' => array_values($quizIds)],
    'active' => true
]);

$quizzesByLevelSpec = [];
$sourceQuizMap = [];
$specQuizMap = [];
foreach ($quizzesCursor as $q) {
    $qid = (string)$q['_id'];
    $sourceQuizMap[$qid] = $q['_id'];
    $lvl = (string)($q['level'] ?? '');
    $sp  = (string)($q['speciality'] ?? '');
    if (!isset($specQuizMap[$sp])) {
        $specQuizMap[$sp] = $q['_id'];
    }
    if (!isset($quizzesByLevelSpec[$lvl][$sp])) {
        $quizzesByLevelSpec[$lvl][$sp] = [];
    }
    $quizzesByLevelSpec[$lvl][$sp][] = $q;
}

// 3. Échantillonnage équilibré par domaine et niveau
$selectedQuestionIds = [];
$selectedByLevel = ['Junior' => 0, 'Senior' => 0];
$selectedByDomain = [];

foreach (DOMAINS as $domKey => $domConfig) {
    $domLabel = $domConfig['label'];
    $selectedByDomain[$domKey] = ['Junior' => 0, 'Senior' => 0, 'total' => 0];
    echo "---------------------------------------------------------------\n";
    echo "Domaine : $domLabel\n";
    
    foreach (['Junior' => $domConfig['target_junior'], 'Senior' => $domConfig['target_senior']] as $lvl => $target) {
        if ($target <= 0) continue;
        
        // Collecte de toutes les questions actives pour les spécialités du domaine à ce niveau
        $candidateQids = [];
        $specStock = [];
        foreach ($domConfig['specs'] as $sp) {
            $qList = $quizzesByLevelSpec[$lvl][$sp] ?? [];
            foreach ($qList as $qDoc) {
                $rawQ = $qDoc['questions'] ?? [];
                $qArr = is_array($rawQ) ? $rawQ : iterator_to_array($rawQ);
                foreach ($qArr as $qItem) {
                    $oid = ($qItem instanceof ObjectId) ? $qItem : new ObjectId((string)$qItem);
                    $candidateQids[(string)$oid] = $oid;
                    $specStock[$sp] = ($specStock[$sp] ?? 0) + 1;
                }
            }
        }
        
        // Vérifier dans la collection questions si elles sont bien actives
        $activeOids = [];
        if (!empty($candidateQids)) {
            $qCur = $db->questions->find([
                '_id' => ['$in' => array_values($candidateQids)],
                'active' => true,
                'level' => $lvl
            ], ['projection' => ['_id' => 1]]);
            foreach ($qCur as $qd) {
                $activeOids[] = $qd['_id'];
            }
        }
        
        $stockCount = count($activeOids);
        echo "  - $lvl : Cible = $target | Stock actif = $stockCount\n";
        
        if ($stockCount < $target) {
            echo "    [ALERTE] Stock insuffisant pour $domLabel ($lvl) : $stockCount < $target\n";
            // On prend tout ce qui est disponible
            $picked = $activeOids;
        } else {
            shuffle($activeOids);
            $picked = array_slice($activeOids, 0, $target);
        }
        
        foreach ($picked as $pId) {
            $selectedQuestionIds[(string)$pId] = $pId;
            $selectedByLevel[$lvl]++;
            $selectedByDomain[$domKey][$lvl]++;
            $selectedByDomain[$domKey]['total']++;
        }
    }
}

// 4. Bilan de l'échantillonnage
$totalSampled = count($selectedQuestionIds);
echo "\n===============================================================\n";
echo "  RÉSUMÉ ÉCHANTILLONNAGE\n";
echo "===============================================================\n";
echo "Total questions uniques sélectionnées : $totalSampled / 100\n";
echo "  - Junior : {$selectedByLevel['Junior']} (cible: 60)\n";
echo "  - Senior : {$selectedByLevel['Senior']} (cible: 40)\n";
foreach (DOMAINS as $domKey => $domConfig) {
    $info = $selectedByDomain[$domKey];
    echo "  - {$domConfig['label']} : {$info['total']} questions (Junior: {$info['Junior']}, Senior: {$info['Senior']})\n";
}

// 5. Questions d'orientation
$orientationOids = [];
foreach (ORIENTATION_IDS as $id) {
    $orientationOids[] = new ObjectId($id);
}
echo "\nQuestions d'orientation rattachées : " . count($orientationOids) . "\n";

if ($totalSampled !== 100) {
    echo "\n[ERREUR] Le nombre total de questions n'est pas exactement de 100 ($totalSampled).\n";
    if (!$dryRun) {
        exit(1);
    }
}

if ($dryRun) {
    echo "\n[SIMULATION TERMINÉE] Aucune insertion effectuée dans MongoDB.\n";
    exit(0);
}

// 6. Insertion dans la collection `papers`
$paperQuestions = array_values($selectedQuestionIds);
shuffle($paperQuestions); // Mélange aléatoire pour le candidat

$allSpecialities = [];
foreach (DOMAINS as $d) {
    foreach ($d['specs'] as $sp) {
        $allSpecialities[$sp] = true;
    }
}

$paperDoc = [
    'label'        => LABEL,
    'type'         => 'Factuel',
    'mode'         => 'mixed',
    'level'        => 'Mixed',
    'brand'        => BRANDS,
    'specialities' => array_keys($allSpecialities),
    'ratios'       => [
        'Junior' => 60,
        'Senior' => 40,
        'Expert' => 0,
    ],
    'counts'       => [
        'QCM_total' => count($paperQuestions),
        'by_level'  => $selectedByLevel,
        'by_domain' => $selectedByDomain,
    ],
    'source_quizzes' => array_values($sourceQuizMap),
    'specQuizMap'    => $specQuizMap,
    'questions'      => $paperQuestions,
    'orientation'    => $orientationOids,
    'duration'       => [
        'hour'   => DURATION_HOUR,
        'minute' => DURATION_MINUTE,
        'second' => 0
    ],
    'hour'           => DURATION_HOUR,
    'minute'         => DURATION_MINUTE,
    'second'         => 0,
    'active'         => true,
    'created'        => date('Y-m-d H:i:s'),
];

$insertResult = $db->papers->insertOne($paperDoc);
$newPaperId = (string)$insertResult->getInsertedId();

echo "\n===============================================================\n";
echo "  [SUCCÈS] Épreuve figée créée avec succès !\n";
echo "  Paper ID : $newPaperId\n";
echo "  Libellé  : " . LABEL . "\n";
echo "  Durée    : " . DURATION_HOUR . "h" . str_pad(DURATION_MINUTE, 2, '0', STR_PAD_LEFT) . "\n";
echo "===============================================================\n";
