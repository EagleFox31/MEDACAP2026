<?php
require_once __DIR__ . '/../core/medacap_config.php';
/**
 * Crée les épreuves figées « Recrutement Mécanicien PL — Octobre 2026 » (une par niveau).
 *
 * Demande RH : 100 questions, 1h30, un seul niveau par épreuve.
 * Thèmes : mécanique de base (freinage, suspension, pneumatique, électricité) + entretien / vidange (Junior).
 * Seules les questions de type Factuelle sont tirées (les quiz Factuels contiennent aussi des items Déclaratifs).
 * Si un domaine manque de stock, le déficit est complété dans les autres domaines du même niveau.
 *
 * Usage:
 *   php scripts/create_pl_recrutement_oct2026_papers.php --level=Senior --dry-run
 *   php scripts/create_pl_recrutement_oct2026_papers.php --level=Junior
 *   php scripts/create_pl_recrutement_oct2026_papers.php --segment=VL --level=Junior
 */
require __DIR__ . '/../vendor/autoload.php';

use MongoDB\BSON\ObjectId;
use MongoDB\Client;

$dryRun = in_array('--dry-run', $argv, true);
$level = null;
$segment = 'PL';
foreach ($argv as $a) {
    if (strpos($a, '--level=') === 0) {
        $level = substr($a, 8);
    }
    if (strpos($a, '--segment=') === 0) {
        $segment = strtoupper(substr($a, 10));
    }
}
if (!in_array($segment, ['PL', 'VL'], true) || !in_array($level, ['Junior', 'Senior'], true)
    || ($segment === 'VL' && $level !== 'Junior')) {
    fwrite(STDERR, "Usage: [--segment=PL|VL] --level=Junior|Senior [--dry-run]  (VL : Junior uniquement)\n");
    exit(1);
}

$uri = medacapMongoUri('mongodb://10.68.0.7:27017');
$db = (new Client($uri))->academy;

const QCM_TOTAL = 100;
const DURATION_HOUR = 1;
const DURATION_MINUTE = 30;

const BRANDS = [
    'PL' => ['FUSO', 'HINO', 'MERCEDES TRUCK', 'RENAULT TRUCK', 'SINOTRUK'],
    'VL' => ['BYD', 'CITROEN', 'MERCEDES', 'MITSUBISHI', 'PEUGEOT', 'SUZUKI', 'TOYOTA', 'VOLKSWAGEN'],
];

const DOMAINS_VL = [
    'Junior' => [
        'entretien' => ['label' => 'Entretien & Vidange', 'target' => 30, 'specs' => ['Transversale', 'Moteur Thermique', 'Moteur Essence', 'Moteur Diesel'], 'priority' => '/vidange|entretien|filtre|huile|lubrifi|graiss|maintenance/iu'],
        'freinage' => ['label' => 'Freinage', 'target' => 20, 'specs' => ['Freinage', 'Freinage Hydraulique']],
        'suspension' => ['label' => 'Suspension', 'target' => 15, 'specs' => ['Suspension', 'Suspension Ressort', 'Suspension à Lame']],
        'pneumatique' => ['label' => 'Pneumatiques', 'target' => 10, 'specs' => ['Pneumatique']],
        'electricite' => ['label' => 'Électricité', 'target' => 25, 'specs' => ['Electricité et Electronique']],
    ],
];

const DOMAINS = [
    'Junior' => [
        'entretien' => ['label' => 'Entretien & Vidange', 'target' => 30, 'specs' => ['Transversale', 'Moteur Thermique', 'Moteur Diesel'], 'priority' => '/vidange|entretien|filtre|huile|lubrifi|graiss|maintenance/iu'],
        'freinage' => ['label' => 'Freinage', 'target' => 20, 'specs' => ['Freinage', 'Freinage Pneumatique']],
        'suspension' => ['label' => 'Suspension', 'target' => 15, 'specs' => ['Suspension', 'Suspension à Lame', 'Suspension Pneumatique']],
        'pneumatique' => ['label' => 'Pneumatiques', 'target' => 10, 'specs' => ['Pneumatique']],
        'electricite' => ['label' => 'Électricité', 'target' => 25, 'specs' => ['Electricité et Electronique', 'Electricité Poids Lourds']],
    ],
    'Senior' => [
        'moteur' => ['label' => 'Moteur & Maintenance', 'target' => 20, 'specs' => ['Moteur Diesel', 'Moteur Thermique']],
        'freinage' => ['label' => 'Freinage', 'target' => 15, 'specs' => ['Freinage']],
        'pneumatique' => ['label' => 'Pneumatique (freinage & suspension)', 'target' => 25, 'specs' => ['Freinage Pneumatique', 'Suspension Pneumatique']],
        'suspension' => ['label' => 'Suspension', 'target' => 15, 'specs' => ['Suspension', 'Suspension à Lame']],
        'electricite' => ['label' => 'Électricité', 'target' => 25, 'specs' => ['Electricité et Electronique', 'Electricité Poids Lourds', 'Réseaux de Communication']],
    ],
];

/** Pack orientation recrutement PL (7 questions). */
const ORIENTATION_IDS = [
    '6944559e72d68a2edb25d835', // Ville de résidence
    '694455bf72d68a2edb25d836', // Mobilité Cameroun
    '694455ca72d68a2edb25d837', // Mobilité étranger
    '694455e172d68a2edb25d838', // Situation familiale
    '694455f972d68a2edb25d839', // Enfants à charge
    '6944558e72d68a2edb25d834', // Conduite poids lourds
    '6944563d72d68a2edb25d83a', // Prétention salariale
];

$label = "Recrutement Mécanicien $segment $level - Octobre 2026";
$domains = $segment === 'VL' ? DOMAINS_VL[$level] : DOMAINS[$level];
$brands = BRANDS[$segment];
// « Conduite poids lourds » ne concerne que le PL
$orientationIds = $segment === 'VL' ? array_values(array_diff(ORIENTATION_IDS, ['6944558e72d68a2edb25d834'])) : ORIENTATION_IDS;

echo "Mongo: $uri\nLabel: $label\nMode: " . ($dryRun ? 'DRY-RUN' : 'INSERTION') . "\n\n";

// 1. Quiz Factuels des marques du segment, au niveau demandé
$quizIds = [];
foreach ($db->vehicles->find(['brand' => ['$in' => $brands], 'type' => 'Factuel', 'active' => true], ['projection' => ['quizzes' => 1]]) as $veh) {
    foreach ($veh['quizzes'] ?? [] as $qid) {
        $quizIds[(string) $qid] = new ObjectId((string) $qid);
    }
}
$sourceQuizMap = [];
$specQuizMap = [];
$questionIdsBySpec = [];
foreach ($db->quizzes->find(['_id' => ['$in' => array_values($quizIds)], 'active' => true, 'level' => $level]) as $q) {
    $sp = (string) ($q['speciality'] ?? '');
    $sourceQuizMap[(string) $q['_id']] = $q['_id'];
    if ($sp !== '' && !isset($specQuizMap[$sp])) {
        $specQuizMap[$sp] = $q['_id'];
    }
    foreach ($q['questions'] ?? [] as $x) {
        $questionIdsBySpec[$sp][(string) $x] = new ObjectId((string) $x);
    }
}

// 2. Stock actif Factuelle par domaine (questions prioritaires repérées par libellé)
$stockByDomain = [];
$priorityIds = [];
foreach ($domains as $key => $cfg) {
    $ids = [];
    foreach ($cfg['specs'] as $sp) {
        $ids += $questionIdsBySpec[$sp] ?? [];
    }
    $active = [];
    if (!empty($ids)) {
        $cur = $db->questions->find([
            '_id' => ['$in' => array_values($ids)],
            'active' => true,
            'level' => $level,
            'type' => ['$in' => ['Factuelle', 'Factuel']],
            'answer' => ['$nin' => [null, '']],
        ], ['projection' => ['_id' => 1, 'label' => 1]]);
        foreach ($cur as $qd) {
            $active[(string) $qd['_id']] = $qd['_id'];
            if (isset($cfg['priority']) && preg_match($cfg['priority'], (string) ($qd['label'] ?? ''))) {
                $priorityIds[(string) $qd['_id']] = true;
            }
        }
    }
    $stockByDomain[$key] = $active;
}

// 3. Tirage par domaine (sans doublon entre domaines)
$picked = [];
$countsByDomain = [];
$deficit = 0;
foreach ($domains as $key => $cfg) {
    $pool = array_diff_key($stockByDomain[$key], $picked);
    $prio = array_keys(array_intersect_key($pool, $priorityIds));
    $others = array_keys(array_diff_key($pool, $priorityIds));
    shuffle($prio);
    shuffle($others);
    $take = array_slice(array_merge($prio, $others), 0, $cfg['target']);
    foreach ($take as $k) {
        $picked[$k] = $pool[$k];
    }
    $countsByDomain[$key] = count($take);
    $deficit += $cfg['target'] - count($take);
    printf("  %-38s cible %3d | stock %3d | prioritaires %3d | tiré %3d\n", $cfg['label'], $cfg['target'], count($stockByDomain[$key]), count($prio), count($take));
}

// 4. Complément du déficit dans les autres domaines du niveau
if ($deficit > 0) {
    $rest = [];
    foreach ($stockByDomain as $key => $stock) {
        foreach (array_diff_key($stock, $picked) as $k => $oid) {
            $rest[$k] = $key;
        }
    }
    $keys = array_keys($rest);
    shuffle($keys);
    foreach (array_slice($keys, 0, $deficit) as $k) {
        $picked[$k] = $stockByDomain[$rest[$k]][$k];
        $countsByDomain[$rest[$k]]++;
    }
    echo "  Complément déficit : " . min($deficit, count($keys)) . " / $deficit\n";
}

$total = count($picked);
echo "\nTotal : $total / " . QCM_TOTAL . "\n";
foreach ($domains as $key => $cfg) {
    echo "  - {$cfg['label']} : {$countsByDomain[$key]}\n";
}
if ($total !== QCM_TOTAL) {
    fwrite(STDERR, "[ERREUR] Stock insuffisant : $total / " . QCM_TOTAL . "\n");
    exit(1);
}
if ($dryRun) {
    echo "\n[DRY-RUN] Aucune insertion.\n";
    exit(0);
}

$questions = array_values($picked);
shuffle($questions);
$specialities = [];
foreach ($domains as $cfg) {
    foreach ($cfg['specs'] as $sp) {
        $specialities[] = $sp;
    }
}
$ratios = ['Junior' => 0, 'Senior' => 0, 'Expert' => 0];
$ratios[$level] = 100;
$byLevel = ['Junior' => 0, 'Senior' => 0, 'Expert' => 0];
$byLevel[$level] = $total;

$doc = [
    'label' => $label,
    'type' => 'Factuel',
    'mode' => 'mixed',
    'level' => 'Mixed',
    'brand' => $brands,
    'specialities' => $specialities,
    'ratios' => $ratios,
    'counts' => [
        'QCM_total' => $total,
        'QCM_brand' => $total,
        'QCM_cp' => 0,
        'by_level' => $byLevel,
        'by_domain' => $countsByDomain,
    ],
    'source_quizzes' => array_values($sourceQuizMap),
    'specQuizMap' => $specQuizMap,
    'questions' => $questions,
    'orientation' => array_map(function ($id) { return new ObjectId($id); }, $orientationIds),
    'duration' => ['hour' => DURATION_HOUR, 'minute' => DURATION_MINUTE, 'second' => 0],
    'hour' => DURATION_HOUR,
    'minute' => DURATION_MINUTE,
    'second' => 0,
    'active' => true,
    'created' => date('Y-m-d H:i:s'),
    'purpose' => $segment === 'VL' ? 'Recrutement Mécanicien Véhicules Légers' : 'Recrutement Mécanicien Poids Lourds',
];

$paperId = (string) $db->papers->insertOne($doc)->getInsertedId();
echo "\n=== Épreuve créée ===\nID: $paperId\nLabel: $label\nDurée: 1h30\n";
