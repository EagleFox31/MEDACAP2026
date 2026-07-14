<?php
/**
 * Crée une épreuve figée « Mécanicien Poids Lourds » — recrutement.
 *
 * Usage:
 *   php scripts/create_mecanicien_pl_paper.php
 *   php scripts/create_mecanicien_pl_paper.php --dry-run
 */
require __DIR__ . '/../vendor/autoload.php';

use MongoDB\BSON\ObjectId;
use MongoDB\Client;
use MongoDB\Model\BSONArray;

$dryRun = in_array('--dry-run', $argv, true);
$uri = getenv('MEDACAP_MONGO_URI') ?: 'mongodb://10.68.0.7:27017';
$db = (new Client($uri))->academy;

const LABEL = 'Recrutement Mécanicien Poids Lourds 2026';
const BRANDS = [
    'RENAULT TRUCK',
    'MERCEDES TRUCK',
    'SINOTRUK',
    'HINO',
    'FUSO',
];
const QCM_TOTAL = 200;
const DURATION_HOUR = 2;
const DURATION_MINUTE = 0;

/** Pack orientation recommandé recrutement PL (7 questions). */
const ORIENTATION_IDS = [
    '6944559e72d68a2edb25d835', // Ville de résidence
    '694455bf72d68a2edb25d836', // Mobilité Cameroun
    '694455ca72d68a2edb25d837', // Mobilité étranger
    '694455e172d68a2edb25d838', // Situation familiale
    '694455f972d68a2edb25d839', // Enfants à charge
    '6944558e72d68a2edb25d834', // Conduite poids lourds
    '6944563d72d68a2edb25d83a', // Prétention salariale
];

/** Thèmes demandés (maintenance dominante, un peu pneumatique, électricité). */
const THEMES = [
    'maintenance' => [
        'weight' => 0.55,
        'specialities' => [
            'Transversale',
            'Moteur Thermique',
            'Moteur Diesel',
            'Freinage',
            'Direction',
            'Suspension',
            'Suspension à Lame',
            'Pont',
            'Hydraulique',
            'Arbre de Transmission',
            'Demi Arbre de Roue',
            'Boite de Vitesse',
            'Boite de Vitesse Mécanique',
            'Boite de Transfert',
            'Reducteur',
            'Climatisation',
            'Assistance à la Conduite',
        ],
    ],
    'electricite' => [
        'weight' => 0.30,
        'specialities' => [
            'Electricité et Electronique',
            'Réseaux de Communication',
        ],
    ],
    'pneumatique' => [
        'weight' => 0.15,
        'specialities' => [
            'Pneumatique',
            'Freinage Pneumatique',
            'Suspension Pneumatique',
        ],
    ],
];

function toOid($v): ?ObjectId
{
    if ($v instanceof ObjectId) {
        return $v;
    }
    if (is_string($v) && preg_match('/^[0-9a-f]{24}$/i', $v)) {
        return new ObjectId($v);
    }
    return null;
}

function pickRandom(array $arr, int $n): array
{
    if ($n <= 0 || empty($arr)) {
        return [];
    }
    shuffle($arr);
    return array_slice($arr, 0, min($n, count($arr)));
}

function poolBrandQuizzes(MongoDB\Database $db, array $brands, ?string $level = null): array
{
    $quizIdMap = [];
    $vehicles = $db->vehicles->find(
        ['brand' => ['$in' => $brands], 'type' => 'Factuel', 'active' => true],
        ['projection' => ['quizzes' => 1]]
    );
    foreach ($vehicles as $veh) {
        $raw = $veh['quizzes'] ?? [];
        if ($raw instanceof BSONArray) {
            $raw = $raw->getArrayCopy();
        }
        if (!is_array($raw)) {
            continue;
        }
        foreach ($raw as $id) {
            $oid = toOid($id instanceof ObjectId ? (string) $id : (string) $id);
            if ($oid) {
                $quizIdMap[(string) $oid] = $oid;
            }
        }
    }
    if (empty($quizIdMap)) {
        return [];
    }
    $filter = [
        '_id' => ['$in' => array_values($quizIdMap)],
        'active' => true,
        'type' => ['$in' => ['Factuel', 'Factuelle']],
    ];
    if ($level !== null) {
        $filter['level'] = $level;
    }
    $out = [];
    foreach ($db->quizzes->find($filter) as $q) {
        $out[] = $q;
    }
    return $out;
}

function sampleQuestionsOfQuiz(MongoDB\Database $db, $quizDoc, int $n): array
{
    if ($n <= 0) {
        return [];
    }
    $raw = $quizDoc['questions'] ?? [];
    if ($raw instanceof BSONArray) {
        $raw = $raw->getArrayCopy();
    }
    if (!is_array($raw) || empty($raw)) {
        return [];
    }
    $ids = [];
    foreach ($raw as $x) {
        $oid = toOid(is_string($x) ? $x : ($x instanceof ObjectId ? (string) $x : null));
        if ($oid) {
            $ids[(string) $oid] = $oid;
        }
    }
    if (empty($ids)) {
        return [];
    }
    $active = [];
    foreach ($db->questions->find(['_id' => ['$in' => array_values($ids)], 'active' => true], ['projection' => ['_id' => 1]]) as $qd) {
        $active[] = $qd['_id'];
    }
    return pickRandom($active, $n);
}

function filterQuizzesBySpecialities(array $quizzes, array $specialities): array
{
    $set = array_flip($specialities);
    return array_values(array_filter($quizzes, function ($q) use ($set) {
        return isset($set[(string) ($q['speciality'] ?? '')]);
    }));
}

function sampleFromQuizzes(MongoDB\Database $db, array $quizzes, int $target, array &$usedQuestionIds): array
{
    if ($target <= 0 || empty($quizzes)) {
        return [];
    }
    $picked = [];
    $stock = [];
    foreach ($quizzes as $q) {
        $stock[(string) $q['_id']] = $q;
    }
    $attempts = 0;
    while (count($picked) < $target && $attempts < $target * 20) {
        $attempts++;
        if (empty($stock)) {
            break;
        }
        $quiz = $stock[array_rand($stock)];
        $batch = sampleQuestionsOfQuiz($db, $quiz, 1);
        foreach ($batch as $qid) {
            $key = (string) $qid;
            if (isset($usedQuestionIds[$key])) {
                continue;
            }
            $usedQuestionIds[$key] = true;
            $picked[] = $qid;
            if (count($picked) >= $target) {
                break 2;
            }
        }
    }
    return $picked;
}

function countByLevelAndTheme(MongoDB\Database $db, array $brands): void
{
    foreach (['Junior', 'Senior'] as $level) {
        $pool = poolBrandQuizzes($db, $brands, $level);
        echo "\n--- $level ---\n";
        foreach (THEMES as $theme => $cfg) {
            $sub = filterQuizzesBySpecialities($pool, $cfg['specialities']);
            $stock = 0;
            foreach ($sub as $q) {
                $raw = $q['questions'] ?? [];
                if ($raw instanceof BSONArray) {
                    $raw = $raw->getArrayCopy();
                }
                $stock += is_array($raw) ? count($raw) : (int) ($q['total'] ?? 0);
            }
            echo "  $theme: " . count($sub) . " quiz, ~$stock questions\n";
        }
    }
}

echo "Mongo: $uri\n";
echo "Label: " . LABEL . "\n";
echo "Cible: " . QCM_TOTAL . " QCM (100 Junior + 100 Senior)\n";
echo "Marques: " . implode(', ', BRANDS) . "\n";

if ($dryRun) {
    countByLevelAndTheme($db, BRANDS);
    echo "\n[DRY-RUN] Aucune insertion.\n";
    exit(0);
}

$perLevel = (int) (QCM_TOTAL / 2);
$perBrand = (int) round($perLevel / count(BRANDS));
$used = [];
$picked = [];
$sourceQuizIds = [];
$specQuizMap = [];
$countsByTheme = ['maintenance' => 0, 'electricite' => 0, 'pneumatique' => 0];
$countsByLevel = ['Junior' => 0, 'Senior' => 0];
$countsByBrand = array_fill_keys(BRANDS, 0);

foreach (['Junior', 'Senior'] as $level) {
    $levelPicked = [];

    foreach (BRANDS as $brand) {
        $brandPool = poolBrandQuizzes($db, [$brand], $level);
        $brandPicked = [];

        foreach (THEMES as $theme => $cfg) {
            $target = (int) round($perBrand * $cfg['weight']);
            $themeQuizzes = filterQuizzesBySpecialities($brandPool, $cfg['specialities']);
            $batch = sampleFromQuizzes($db, $themeQuizzes, $target, $used);
            foreach ($themeQuizzes as $q) {
                $sourceQuizIds[(string) $q['_id']] = $q['_id'];
                $spec = (string) ($q['speciality'] ?? '');
                if ($spec !== '' && !isset($specQuizMap[$spec])) {
                    $specQuizMap[$spec] = $q['_id'];
                }
            }
            $brandPicked = array_merge($brandPicked, $batch);
            $countsByTheme[$theme] += count($batch);
        }

        $need = $perBrand - count($brandPicked);
        if ($need > 0) {
            $fill = sampleFromQuizzes($db, $brandPool, $need, $used);
            $brandPicked = array_merge($brandPicked, $fill);
        }

        $countsByBrand[$brand] += count($brandPicked);
        echo "$level / $brand: " . count($brandPicked) . " / $perBrand\n";
        $levelPicked = array_merge($levelPicked, $brandPicked);
    }

    $need = $perLevel - count($levelPicked);
    if ($need > 0) {
        $fill = sampleFromQuizzes($db, poolBrandQuizzes($db, BRANDS, $level), $need, $used);
        $levelPicked = array_merge($levelPicked, $fill);
        echo "$level / complément global: " . count($fill) . "\n";
    }

    $countsByLevel[$level] = count($levelPicked);
    $picked = array_merge($picked, $levelPicked);
}

if (count($picked) < QCM_TOTAL) {
    $need = QCM_TOTAL - count($picked);
    $fill = sampleFromQuizzes($db, poolBrandQuizzes($db, BRANDS), $need, $used);
    $picked = array_merge($picked, $fill);
    echo "Complément global: " . count($fill) . "\n";
}

if (count($picked) > QCM_TOTAL) {
    shuffle($picked);
    $picked = array_slice($picked, 0, QCM_TOTAL);
}

shuffle($picked);

$orientationOids = [];
foreach (ORIENTATION_IDS as $id) {
    $oid = toOid($id);
    if ($oid) {
        $orientationOids[] = $oid;
    }
}

$doc = [
    'label' => LABEL,
    'type' => 'Factuel',
    'mode' => 'mixed',
    'level' => 'Mixed',
    'brand' => BRANDS,
    'ratios' => ['Junior' => 50, 'Senior' => 50, 'Expert' => 0],
    'counts' => [
        'QCM_total' => count($picked),
        'by_level' => $countsByLevel,
        'by_theme' => $countsByTheme,
        'by_brand' => $countsByBrand,
    ],
    'themes' => array_map(function ($c) {
        return ['weight' => $c['weight'], 'specialities' => $c['specialities']];
    }, THEMES),
    'source_quizzes' => array_values($sourceQuizIds),
    'specQuizMap' => $specQuizMap,
    'questions' => $picked,
    'orientation' => $orientationOids,
    'duration' => ['hour' => DURATION_HOUR, 'minute' => DURATION_MINUTE, 'second' => 0],
    'hour' => DURATION_HOUR,
    'minute' => DURATION_MINUTE,
    'second' => 0,
    'active' => true,
    'created' => date('Y-m-d H:i:s'),
    'purpose' => 'Recrutement Mécanicien Poids Lourds',
];

$result = $db->papers->insertOne($doc);
$paperId = (string) $result->getInsertedId();

echo "\n=== Épreuve créée ===\n";
echo "ID: $paperId\n";
echo "Questions: " . count($picked) . "\n";
echo "Junior: {$countsByLevel['Junior']} | Senior: {$countsByLevel['Senior']}\n";
echo "Maintenance: {$countsByTheme['maintenance']} | Électricité: {$countsByTheme['electricite']} | Pneumatique: {$countsByTheme['pneumatique']}\n";
foreach ($countsByBrand as $brand => $n) {
    echo "  $brand: $n\n";
}
echo "Lien candidat: compose_fixed.php?paper=$paperId&id=<USER_ID>\n";
