<?php
require_once __DIR__ . '/../core/medacap_config.php';
/**
 * cronjobs/generate_subsidiary_reports.php
 *
 * Génère le cache quotidien des résultats par filiale.
 * - Compatible PHP 7.3 + MongoDB 4.2
 * - Historique conservé (inter-jours)
 * - 1 document par filiale et par jour (upsert {date, subsidiary})
 */

ini_set('memory_limit', '1024M');
set_time_limit(1800); // 30 minutes
date_default_timezone_set('Africa/Douala');

// -------- Autoload Composer (chemins relatifs usuels) --------
$autoloadFound = false;
$autoloadCandidates = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
    __DIR__ . '/../../../vendor/autoload.php',
];

foreach ($autoloadCandidates as $candidate) {
    if (file_exists($candidate)) {
        require_once $candidate;
        $autoloadFound = true;
        break;
    }
}

if (!$autoloadFound) {
    die("Could not find vendor/autoload.php\n");
}

use MongoDB\Client;
use MongoDB\BSON\UTCDateTime;
use MongoDB\BSON\ObjectId;

// -------- Helpers --------
function oid_str($maybeOid) {
    if (is_array($maybeOid) && isset($maybeOid['$oid'])) {
        return (string)$maybeOid['$oid'];
    }
    if ($maybeOid instanceof ObjectId) {
        return (string)$maybeOid;
    }
    return (string)$maybeOid;
}


/**
 * Convertit proprement BSONDocument/BSONArray -> array PHP
 * (compatible PHP 7.3)
 */
function bson_to_array($value) {
    if ($value instanceof MongoDB\Model\BSONDocument || $value instanceof MongoDB\Model\BSONArray) {
        return json_decode(json_encode($value), true);
    }
    if (is_array($value)) return $value;
    if ($value instanceof Traversable) return iterator_to_array($value);
    return [];
}

// en haut
const APP_WEB_BASE = '/MEDACAP';

function normalize_flag_web_path($raw) {
    if (!$raw) return null;
    $raw = str_replace('\\', '/', (string)$raw);

    // Absolu web ?
    if (preg_match('#^https?://#i', $raw)) return $raw;
    if ($raw[0] === '/') return $raw;

    // Enlever ./ et ../
    $raw = preg_replace('#^(\./|(\.\./)+)#', '', $raw);

    // Si le chemin commence par "define/", on le mappe sous /MEDACAP/views/define/...
    if (stripos($raw, 'views/') === 0) {
        return rtrim(APP_WEB_BASE, '/') . '/' . $raw;
    }
    if (stripos($raw, 'define/') === 0) {
        return rtrim(APP_WEB_BASE, '/') . '/views/' . $raw;
    }

    // fallback
    return rtrim(APP_WEB_BASE, '/') . '/' . ltrim($raw, '/');
}

function safe_flag_path($sub_info) {
    $raw = null;
    if (!empty($sub_info['flagPathComplete'])) {
        $raw = (string)$sub_info['flagPathComplete'];
    } elseif (!empty($sub_info['flagPath'])) {
        $raw = (string)$sub_info['flagPath'] . '.svg';
    }
    return normalize_flag_web_path($raw);
}



function getAllocation($allocations_collection, $userIdStr, $level, $type, $activeManager = false) {
    $query = [
        'user'   => new ObjectId($userIdStr),
        'level'  => $level,
        'type'   => $type,
        'active' => true,
    ];
    if ($activeManager) {
        $query['activeManager'] = true;
    }
    return $allocations_collection->findOne($query);
}

function getResults($results_collection, $userIdStr, $level, $typeR, $type) {
    return $results_collection->findOne([
        "user"  => new ObjectId($userIdStr),
        "level" => $level,
        'typeR' => $typeR,
        "type"  => $type,
    ]);
}

/** Renvoie une moyenne finale (0..100) entre factuel et déclaratif si présents */
function calculatePercentage($factuel, $declaratif) {
    if ($factuel > 0 && $declaratif > 0) {
        return round(($factuel + $declaratif) / 2, 2);
    }
    if ($factuel > 0) return round($factuel, 2);
    if ($declaratif > 0) return round($declaratif, 2);
    return 0.0;
}

// -------- Connexion Mongo --------
echo "Génération des rapports de filiales...\n";

$conn    = new Client(medacapMongoUri());
$academy = $conn->academy;

// Collections
$users_collection        = $academy->users;
$results_collection      = $academy->results;
$allocations_collection  = $academy->allocations;
$subsidiaries_collection = $academy->cfao_subsidiaries;
$reports_collection      = $academy->reports_subsidiary_results;

// -------- Constantes métier --------
$BRAND_GROUPS = [
    'VL'       => ['TOYOTA', 'BYD', 'MITSUBISHI', 'MERCEDES', 'PEUGEOT', 'CITROEN', 'SUZUKI'],
    'TRUCKS'   => ['RENAULT TRUCK', 'MERCEDES TRUCK', 'SINOTRUK', 'HINO', 'FUSO'],
    'BUS'      => ['KING LONG'],
    'CHARIOTS' => ['TOYOTA FORKLIFT', 'TOYOTA BT'],
    'ENGINS'   => ['JCB', 'LOVOL'],
];
$LEVELS = ['Junior', 'Senior', 'Expert'];

// -------- Date du jour (minuit) en ms (UTCDateTime attend des ms !) --------
$today_midnight_ts = strtotime('today midnight'); // secondes
$today_start       = new UTCDateTime($today_midnight_ts * 1000); // ms

echo "Date de travail: " . date('Y-m-d', $today_midnight_ts) . "\n";

// -------- Index unique {date, subsidiary} (idempotence intrajournalière) --------
try {
    $reports_collection->createIndex(
        ['date' => 1, 'subsidiary' => 1],
        ['unique' => true, 'name' => 'uniq_date_subsidiary']
    );
    echo "Index unique {date, subsidiary} OK.\n";
} catch (Throwable $e) {
    // si déjà créé, Mongo renverra rien de bloquant
    echo "Info index: " . $e->getMessage() . "\n";
}

// -------- Récupérer info filiales --------
$subsidiaries_info = [];
$subs_cursor = $subsidiaries_collection->find([]);
foreach ($subs_cursor as $sub_data) {
    $arr = bson_to_array($sub_data);
    $subsidiaryName = isset($arr['subsidiary']) ? (string)$arr['subsidiary'] : '';
    if ($subsidiaryName === '') continue;

    $subsidiaries_info[$subsidiaryName] = [
        'country'  => isset($arr['country']) ? (string)$arr['country'] : '',
        'agencies' => isset($arr['agencies']) ? bson_to_array($arr['agencies']) : [],
        'flag'     => safe_flag_path($arr),
    ];
}
echo "Filiales chargées: " . count($subsidiaries_info) . "\n";

// -------- Récupérer techniciens actifs (et quelques managers test) --------
$all_technicians = $users_collection->find(
    [
        'subsidiary' => ['$in' => array_keys($subsidiaries_info)],
        'active'     => true,
        '$or'        => [
            ['profile' => 'Technicien'],
            ['profile' => 'Manager', 'test' => true]
        ]
    ],
    [
        'projection' => [
            'subsidiary'   => 1,
            'brandJunior'  => 1,
            'brandSenior'  => 1,
            'brandExpert'  => 1,
        ]
    ]
);

$technicians_by_subsidiary = [];
$techCount = 0;
foreach ($all_technicians as $tech) {
    $techCount++;
    $arr = bson_to_array($tech);
    $subsName = isset($arr['subsidiary']) ? (string)$arr['subsidiary'] : '';
    if ($subsName) {
        $technicians_by_subsidiary[$subsName][] = $arr;
    }
}
echo "Techniciens actifs chargés: $techCount\n";

// -------- Boucle de génération par filiale (UPsert) --------
$total_upserts = 0;
$total_updates = 0;
$total_skipped = 0;

foreach ($subsidiaries_info as $subsName => $subInfo) {
    $subsidiary_technicians = isset($technicians_by_subsidiary[$subsName]) ? $technicians_by_subsidiary[$subsName] : [];

    if (empty($subsidiary_technicians)) {
        $total_skipped++;
        echo "Skip (0 techniciens): {$subsName}\n";
        continue;
    }

    echo "Calcul: {$subsName} (" . count($subsidiary_technicians) . " techs)\n";

    $report = [
        'date'             => $today_start,
        'subsidiary'       => $subsName,
        'country'          => $subInfo['country'],
        'flag'             => $subInfo['flag'],
        'totalTechnicians' => count($subsidiary_technicians),
        'results'          => [],
        // métadonnées
        'generatedAt'      => new UTCDateTime((int)(microtime(true) * 1000)),
        'dataVersion'      => 1,
        'rebuildReason'    => 'daily',
    ];

    // ---- Calculs par niveau + all_levels ----
    $all_levels_and_brands = array_merge($LEVELS, ['all_levels']);

    foreach ($all_levels_and_brands as $level) {
        $level_results = [
            'average'        => 0,
            'by_brand_group' => [],
            'by_brand'       => [],
        ];

        $tech_scores_by_brand = [];
        $tech_scores_by_group = [];
        $overall_level_scores = [];

        foreach ($subsidiary_technicians as $tech) {
            // ID utilisateur
            $techIdStr = isset($tech['_id']) ? oid_str($tech['_id']) : null;
            if (!$techIdStr) continue;

            // Niveaux à considérer
            $levels_to_check = ($level === 'all_levels') ? $LEVELS : [$level];

            // Marques de l'utilisateur sur les niveaux considérés
            $user_brands_for_levels = [];
            foreach ($levels_to_check as $l) {
                $field = 'brand' . $l;
                $user_brands_for_levels = array_merge(
                    $user_brands_for_levels,
                    isset($tech[$field]) ? (array)bson_to_array($tech[$field]) : []
                );
            }
            $user_brands_for_levels = array_values(array_unique(array_filter(array_map(function($b){
                $b = strtoupper(trim((string)$b));
                return $b === '' ? null : $b; // ← on jette les vides
            }, $user_brands_for_levels))));

            // Scores par niveau (moyenne Factuel / Déclaratif)
            $user_level_scores = [];
            foreach ($levels_to_check as $current_level) {
                $alloFac   = getAllocation($allocations_collection, $techIdStr, $current_level, 'Factuel');
                $alloDecla = getAllocation($allocations_collection, $techIdStr, $current_level, 'Declaratif', true);

                if ($alloFac || $alloDecla) {
                    $scoreFac = 0; $totalFac = 0; $scoreDecla = 0; $totalDecla = 0;

                    $resFac = getResults($results_collection, $techIdStr, $current_level, 'Technicien', 'Factuel');
                    if ($resFac) {
                        $a = bson_to_array($resFac);
                        $scoreFac = isset($a['score']) ? (float)$a['score'] : 0;
                        $totalFac = isset($a['total']) ? (float)$a['total'] : 0;
                    }

                    $resDecla = getResults($results_collection, $techIdStr, $current_level, 'Technicien - Manager', 'Declaratif');
                    if ($resDecla) {
                        $a = bson_to_array($resDecla);
                        $scoreDecla = isset($a['score']) ? (float)$a['score'] : 0;
                        $totalDecla = isset($a['total']) ? (float)$a['total'] : 0;
                    }

                    $avgFac   = ($totalFac   > 0) ? ($scoreFac   / $totalFac)   * 100 : 0;
                    $avgDecla = ($totalDecla > 0) ? ($scoreDecla / $totalDecla) * 100 : 0;

                    $final_score = calculatePercentage($avgFac, $avgDecla);
                    if ($final_score > 0) {
                        $user_level_scores[] = $final_score;
                    }
                }
            }

            // Agrégation des scores de l'utilisateur
            if (!empty($user_level_scores)) {
                $tech_avg_score = array_sum($user_level_scores) / count($user_level_scores);
                $overall_level_scores[] = $tech_avg_score;

                // Par marque
                foreach ($user_brands_for_levels as $brand) {
                    if ($brand === '') continue;
                    $tech_scores_by_brand[$brand][] = $tech_avg_score;

                    // Par groupe de marques
                    foreach ($BRAND_GROUPS as $groupName => $groupBrands) {
                        if (in_array($brand, $groupBrands, true)) {
                            $tech_scores_by_group[$groupName][] = $tech_avg_score;
                        }
                    }
                }
            }
        }

        // Moyenne du niveau (ou all_levels)
        if (!empty($overall_level_scores)) {
            $level_results['average'] = round(array_sum($overall_level_scores) / count($overall_level_scores), 2);
        }

        // Averages par marque
        foreach ($tech_scores_by_brand as $brand => $scores) {
            $level_results['by_brand'][$brand] = [
                'average'          => round(array_sum($scores) / count($scores), 2),
                'technician_count' => count($scores),
            ];
        }

        // Averages par groupe
        foreach ($tech_scores_by_group as $group => $scores) {
            $level_results['by_brand_group'][$group] = [
                'average'          => round(array_sum($scores) / count($scores), 2),
                'technician_count' => count($scores),
            ];
        }

        $report['results'][$level] = $level_results;
    }

    // ---- Upsert {date, subsidiary} ----
    $filter = ['date' => $today_start, 'subsidiary' => $subsName];
    $update = [
        '$set'        => $report,
        '$setOnInsert'=> ['createdAt' => $report['generatedAt']],
    ];

    $res = $reports_collection->updateOne($filter, $update, ['upsert' => true]);

    if ($res->getUpsertedCount() > 0) {
        $total_upserts++;
    } elseif ($res->getModifiedCount() > 0) {
        $total_updates++;
    }
}

// -------- Résumé --------
echo "-----------------------------\n";
echo "Upserts (créations jour): $total_upserts\n";
echo "Updates (remplacements):  $total_updates\n";
echo "Filiales sans tech:       $total_skipped\n";
echo "Génération terminée.\n";
