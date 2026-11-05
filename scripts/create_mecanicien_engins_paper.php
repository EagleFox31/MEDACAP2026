<?php
require_once __DIR__ . '/../core/medacap_config.php';
/**
 * Crée l'épreuve figée « Recrutement Mécanicien Engins Senior - Novembre 2026 ».
 *
 * Demande RH (05/11/2026) : 100 à 150 questions Senior.
 * Répartition : Électricité machine 10 %, Électronique 10 %, Moteur 30 %, Hydraulique 20 %, Transmission 25 %, Pneumatique 5 %.
 * Tirage par domaine : banque Engins (refs « FacEng-<code>-NN »), puis quiz des marques Engins (JCB, LOVOL),
 * puis quiz Poids Lourds (questions propres au camion écartées en électricité).
 * Les domaines sont enregistrés sur l'épreuve (papers.domains) pour l'export recrutement.
 *
 * Usage:
 *   php scripts/create_mecanicien_engins_paper.php [--db=academy] [--total=150] --dry-run
 *   php scripts/create_mecanicien_engins_paper.php [--db=academy] [--total=150]
 */
require __DIR__ . '/../vendor/autoload.php';

use MongoDB\BSON\ObjectId;
use MongoDB\Client;

$opts = getopt('', ['dry-run', 'db:', 'total:']);
$dryRun = isset($opts['dry-run']);
$dbName = isset($opts['db']) ? (string) $opts['db'] : 'academy';
$qcmTotal = isset($opts['total']) ? (int) $opts['total'] : 150;
if ($qcmTotal < 100 || $qcmTotal > 150) {
    fwrite(STDERR, "[ERREUR] --total doit être entre 100 et 150.\n");
    exit(1);
}
$uri = medacapMongoUri('mongodb://10.68.0.7:27017');
$db = (new Client($uri))->selectDatabase($dbName);

const LEVEL = 'Senior';
const DURATION_HOUR = 2;
const DURATION_MINUTE = 0;
const LABEL = 'Recrutement Mécanicien Engins Senior - Novembre 2026';

const BRANDS_ENGINS = ['JCB', 'LOVOL'];
const BRANDS_PL = ['FUSO', 'HINO', 'MERCEDES TRUCK', 'RENAULT TRUCK', 'SINOTRUK'];

const BANK_REF_PREFIX = 'FacEng-';
const BANK_DOMAINS = ['ELT' => 'electricite', 'ELN' => 'electronique', 'MOT' => 'moteur', 'HYD' => 'hydraulique', 'TRA' => 'transmission', 'PNE' => 'pneumatique'];

const ELECTRONIC = '/calculateur|[ée]lectroni|diode|transistor|capteur|multiplex|\bCAN\b|common-rail|protocol|\bbus\b/iu';
const TRUCK_ONLY = '/remorque|ISO 7638|ISO 3731|24S|tachygraphe|15 broches|attelage|ensemble routier|projecteur|H7|radar|cam[ée]ra|pare-brise|ABS|\bfeux?\b|[ée]clairage|r[ée]servoir d.air/iu';
const TRUCK_ONLY_SPECS = ['Electricité Poids Lourds'];

// Ordre = priorité de tirage : l'électronique passe avant l'électricité (même pool de spécialités)
const DOMAINS = [
    'electronique' => ['label' => 'Électronique', 'pct' => 10, 'specs' => ['Electricité et Electronique', 'Réseaux de Communication', 'Electricité Poids Lourds'], 'match' => ELECTRONIC, 'always' => ['Réseaux de Communication']],
    'electricite' => ['label' => 'Électricité (machine)', 'pct' => 10, 'specs' => ['Electricité et Electronique', 'Electricité Poids Lourds'], 'exclude' => ELECTRONIC],
    'moteur' => ['label' => 'Moteur', 'pct' => 30, 'specs' => ['Moteur Diesel', 'Moteur Thermique']],
    'hydraulique' => ['label' => 'Hydraulique', 'pct' => 20, 'specs' => ['Hydraulique', 'Freinage Hydraulique']],
    'transmission' => ['label' => 'Transmission', 'pct' => 25, 'specs' => ['Boite de Vitesse', 'Boite de Vitesse Mécanique', 'Boite de Transfert', 'Arbre de Transmission', 'Demi Arbre de Roue', 'Pont', 'Reducteur']],
    'pneumatique' => ['label' => 'Pneumatique', 'pct' => 5, 'specs' => ['Pneumatique', 'Freinage Pneumatique', 'Suspension Pneumatique']],
];
/** Ordre d'affichage demandé par le client (papers.domains). */
const CLIENT_ORDER = ['electricite', 'electronique', 'moteur', 'hydraulique', 'transmission', 'pneumatique'];

/** Pack orientation recrutement (sans « Conduite poids lourds »). */
const ORIENTATION_IDS = [
    '6944559e72d68a2edb25d835', // Ville de résidence
    '694455bf72d68a2edb25d836', // Mobilité Cameroun
    '694455ca72d68a2edb25d837', // Mobilité étranger
    '694455e172d68a2edb25d838', // Situation familiale
    '694455f972d68a2edb25d839', // Enfants à charge
    '6944563d72d68a2edb25d83a', // Prétention salariale
];

// Cibles : arrondi des pourcentages, l'écart d'arrondi va au moteur
$targets = [];
foreach (DOMAINS as $key => $cfg) {
    $targets[$key] = (int) round($qcmTotal * $cfg['pct'] / 100);
}
$targets['moteur'] += $qcmTotal - array_sum($targets);

echo "Mongo: $uri / $dbName\nLabel: " . LABEL . "\nTotal visé: $qcmTotal\nMode: " . ($dryRun ? 'DRY-RUN' : 'INSERTION') . "\n\n";

if ($db->papers->findOne(['label' => LABEL])) {
    fwrite(STDERR, "[ERREUR] Une épreuve « " . LABEL . " » existe déjà.\n");
    exit(1);
}

// 1. Quiz Factuels Senior des marques Engins et PL (un quiz partagé avec une marque Engins reste Engins)
$quizIds = [];
foreach ($db->vehicles->find(['brand' => ['$in' => array_merge(BRANDS_ENGINS, BRANDS_PL)], 'type' => 'Factuel', 'active' => true], ['projection' => ['quizzes' => 1, 'brand' => 1]]) as $veh) {
    $isPl = in_array((string) $veh['brand'], BRANDS_PL, true);
    foreach ($veh['quizzes'] ?? [] as $qid) {
        $plOnly = $isPl && (!isset($quizIds[(string) $qid]) || $quizIds[(string) $qid]['pl']);
        $quizIds[(string) $qid] = ['id' => new ObjectId((string) $qid), 'pl' => $plOnly];
    }
}
$sourceQuizMap = [];
$specQuizMap = [];
$questionIdsBySpec = [];
$plQuestionIds = [];
$allQuizIds = array_map(function ($x) { return $x['id']; }, $quizIds);
foreach ($db->quizzes->find(['_id' => ['$in' => array_values($allQuizIds)], 'active' => true, 'level' => LEVEL]) as $q) {
    $sp = (string) ($q['speciality'] ?? '');
    $isPl = $quizIds[(string) $q['_id']]['pl'];
    $sourceQuizMap[(string) $q['_id']] = $q['_id'];
    if ($sp !== '' && !isset($specQuizMap[$sp])) {
        $specQuizMap[$sp] = $q['_id'];
    }
    foreach ($q['questions'] ?? [] as $x) {
        $questionIdsBySpec[$sp][(string) $x] = new ObjectId((string) $x);
        if ($isPl) {
            $plQuestionIds[(string) $x] = true;
        }
    }
}

// 2. Stock actif Factuelle Senior par domaine
$stockByDomain = [];
$excluded = [];
foreach (DOMAINS as $key => $cfg) {
    $ids = [];
    foreach ($cfg['specs'] as $sp) {
        $ids += $questionIdsBySpec[$sp] ?? [];
    }
    $active = [];
    if (!empty($ids)) {
        $cur = $db->questions->find([
            '_id' => ['$in' => array_values($ids)],
            'active' => true,
            'level' => LEVEL,
            'type' => ['$in' => ['Factuelle', 'Factuel']],
            'answer' => ['$nin' => [null, '']],
        ], ['projection' => ['_id' => 1, 'label' => 1, 'speciality' => 1]]);
        foreach ($cur as $qd) {
            $label = (string) ($qd['label'] ?? '');
            $sp = (string) ($qd['speciality'] ?? '');
            if (in_array($sp, TRUCK_ONLY_SPECS, true) && preg_match(TRUCK_ONLY, $label)) {
                $excluded[(string) $qd['_id']] = true;
                continue;
            }
            if (!in_array($sp, $cfg['specs'], true)) {
                continue;
            }
            $always = isset($cfg['always']) && in_array($sp, $cfg['always'], true);
            if (!$always && isset($cfg['match']) && !preg_match($cfg['match'], $label)) {
                continue;
            }
            if (isset($cfg['exclude']) && preg_match($cfg['exclude'], $label)) {
                continue;
            }
            $active[(string) $qd['_id']] = $qd['_id'];
        }
    }
    $stockByDomain[$key] = $active;
}
echo "  Questions camion écartées (Electricité PL) : " . count($excluded) . "\n";

// Banque Engins : le domaine est porté par la ref
$bankIds = [];
$cur = $db->questions->find([
    'ref' => ['$regex' => '^' . BANK_REF_PREFIX],
    'active' => true,
    'level' => LEVEL,
    'type' => 'Factuelle',
    'answer' => ['$nin' => [null, '']],
], ['projection' => ['_id' => 1, 'ref' => 1]]);
foreach ($cur as $qd) {
    $code = explode('-', (string) $qd['ref'])[1] ?? '';
    if (!isset(BANK_DOMAINS[$code])) {
        continue;
    }
    foreach ($stockByDomain as $k => $ids) {
        unset($stockByDomain[$k][(string) $qd['_id']]);
    }
    $stockByDomain[BANK_DOMAINS[$code]][(string) $qd['_id']] = $qd['_id'];
    $bankIds[(string) $qd['_id']] = true;
}
echo "  Banque Engins (" . BANK_REF_PREFIX . "…) : " . count($bankIds) . " questions\n\n";

// 3. Tirage par domaine (sans doublon) : banque Engins, puis stock Engins, puis PL
$picked = [];
$byDomain = [];
$missing = [];
foreach (DOMAINS as $key => $cfg) {
    $pool = array_diff_key($stockByDomain[$key], $picked);
    $bank = array_keys(array_intersect_key($pool, $bankIds));
    $engins = array_keys(array_diff_key($pool, $plQuestionIds, $bankIds));
    $pl = array_keys(array_diff_key(array_intersect_key($pool, $plQuestionIds), $bankIds));
    shuffle($bank);
    shuffle($engins);
    shuffle($pl);
    $take = array_slice(array_merge($bank, $engins, $pl), 0, $targets[$key]);
    foreach ($take as $k) {
        $picked[$k] = $pool[$k];
    }
    $byDomain[$key] = array_map(function ($k) use ($pool) { return $pool[$k]; }, $take);
    if (count($take) < $targets[$key]) {
        $missing[] = $cfg['label'] . ' ' . count($take) . '/' . $targets[$key];
    }
    printf("  %-22s cible %3d | banque %3d | Engins %3d | PL %3d | tiré %3d (dont PL %3d)\n", $cfg['label'], $targets[$key], count($bank), count($engins), count($pl), count($take), count(array_intersect($take, $pl)));
}

$total = count($picked);
echo "\nTotal : $total / $qcmTotal\n";
if (!empty($missing)) {
    fwrite(STDERR, "[ERREUR] Stock insuffisant : " . implode(', ', $missing) . "\n");
    exit(1);
}
if ($dryRun) {
    echo "\n[DRY-RUN] Aucune insertion.\n";
    exit(0);
}

$questions = array_values($picked);
shuffle($questions);
$specialities = [];
foreach (DOMAINS as $cfg) {
    foreach ($cfg['specs'] as $sp) {
        $specialities[$sp] = $sp;
    }
}
$domains = [];
foreach (CLIENT_ORDER as $key) {
    $domains[] = ['key' => $key, 'label' => DOMAINS[$key]['label'], 'questions' => $byDomain[$key]];
}

$doc = [
    'label' => LABEL,
    'type' => 'Factuel',
    'mode' => 'mixed',
    'level' => 'Mixed',
    'brand' => BRANDS_ENGINS,
    'specialities' => array_values($specialities),
    'ratios' => ['Junior' => 0, 'Senior' => 100, 'Expert' => 0],
    'counts' => [
        'QCM_total' => $total,
        'QCM_brand' => $total,
        'QCM_cp' => 0,
        'by_level' => ['Junior' => 0, 'Senior' => $total, 'Expert' => 0],
        'by_domain' => array_map('count', $byDomain),
    ],
    'domains' => $domains,
    'source_quizzes' => array_values($sourceQuizMap),
    'specQuizMap' => $specQuizMap,
    'questions' => $questions,
    'orientation' => array_map(function ($id) { return new ObjectId($id); }, ORIENTATION_IDS),
    'duration' => ['hour' => DURATION_HOUR, 'minute' => DURATION_MINUTE, 'second' => 0],
    'hour' => DURATION_HOUR,
    'minute' => DURATION_MINUTE,
    'second' => 0,
    'active' => true,
    'created' => date('Y-m-d H:i:s'),
    'purpose' => 'Recrutement Mécanicien Engins',
];

$paperId = (string) $db->papers->insertOne($doc)->getInsertedId();
echo "\n=== Épreuve créée ===\nID: $paperId\nLabel: " . LABEL . "\nDurée: " . DURATION_HOUR . "h" . DURATION_MINUTE . "\n";
