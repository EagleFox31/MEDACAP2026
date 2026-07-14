<?php
/**
 * ---------------------------------------------------------------------------
 *  stats_tests.php  –  Avancement des QCM (Factuel + Déclaratif validé manager)
 * ---------------------------------------------------------------------------
 *  Retourne un tableau :
 *
 *      [
 *          'Junior' => ['done' => 18, 'total' => 27],
 *          'Senior' => ['done' => 11, 'total' => 23],
 *          'Expert' => ['done' =>  4, 'total' => 12],
 *          'Total'  => ['done' => 33, 'total' => 62], // ⇠ personnes UNIQUES
 *      ]
 * ---------------------------------------------------------------------------
 */

// Error debugging
error_log("Starting stats_tests.php execution");

// Make sure autoloader is included
$autoloadPath = __DIR__ . '/../../../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
    error_log("Loaded autoloader from: " . $autoloadPath);
} else {
    error_log("ERROR: Autoloader not found at: " . $autoloadPath);
    // Return empty data to prevent dashboard crashing
    return [
        'Junior' => ['done' => 0, 'total' => 0],
        'Senior' => ['done' => 0, 'total' => 0],
        'Expert' => ['done' => 0, 'total' => 0],
        'Total'  => ['done' => 0, 'total' => 0],
    ];
}

// Import MongoDB classes
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\BSON\ObjectId;

// Verify MongoDB classes are available
if (!class_exists('MongoDB\BSON\ObjectId')) {
    error_log("ERROR: MongoDB\BSON\ObjectId class not found!");
    return [
        'Junior' => ['done' => 0, 'total' => 0],
        'Senior' => ['done' => 0, 'total' => 0],
        'Expert' => ['done' => 0, 'total' => 0],
        'Total'  => ['done' => 0, 'total' => 0],
    ];
}

$emptyStats = [
    'Junior' => ['done' => 0, 'total' => 0],
    'Senior' => ['done' => 0, 'total' => 0],
    'Expert' => ['done' => 0, 'total' => 0],
    'Total'  => ['done' => 0, 'total' => 0],
];

/* ════════════════════ 1. Connexion DB ═════════════════════════════════════ */
try {
    require_once __DIR__ . '/../core/portal-mongo.php';
    $academy  = definePortalMongoDatabase();
    $usersCol = $academy->users;
    $allocCol = $academy->allocations;

    error_log("MongoDB connection successful");
} catch (\Throwable $e) {
    error_log("MongoDB connection error: " . $e->getMessage());
    return $emptyStats;
}

/* ════════════════════ 2. Filtres venant du contrôleur ═════════════════════ */
$filters     = $GLOBALS['filtersForStats'] ?? [];
$wantedLevel = $filters['level'] ?? 'all';   // 'Junior' | 'Senior' | 'Expert' | 'all'

/* ════════════════════ 3. Outils ═══════════════════════════════════════════ */
if (!function_exists('getTechnicians')) {
    function getTechnicians(Collection $users, array $f): array
    {
        $q = [
            'active' => true,
            '$or'    => [
                ['profile' => 'Technicien'],
                ['profile' => 'Manager', 'test' => true],
            ],
        ];
    
        foreach (['subsidiary', 'agency'] as $k) {
            if (($f[$k] ?? 'all') !== 'all') $q[$k] = $f[$k];
        }
        if (($f['managerId'] ?? 'all')    !== 'all') $q['manager'] = $f['managerId'];
        if (($f['technicianId'] ?? 'all') !== 'all') {
            try {
                // Use string ID if ObjectId creation fails
                try {
                    $q['_id'] = new MongoDB\BSON\ObjectId($f['technicianId']);
                } catch (\Exception $e) {
                    // Fall back to string comparison if ObjectId fails
                    $q['_id'] = $f['technicianId'];
                    error_log("Using string ID instead of ObjectId: " . $e->getMessage());
                }
            } catch (\Exception $e) {
                error_log("Error setting technicianId filter: " . $e->getMessage());
            }
        }
    
        /* Niveau hiérarchique */
        if (($f['level'] ?? 'all') !== 'all') {
            switch ($f['level']) {
                case 'Junior':
                    $q['level'] = ['$in' => ['Junior', 'Senior', 'Expert']];
                    break;
                case 'Senior':
                    $q['level'] = ['$in' => ['Senior', 'Expert']];
                    break;
                case 'Expert':
                    $q['level'] = 'Expert';
                    break;
            }
        }
    
        /* Marque — $and pour conserver la clause profil $or */
        if (($f['brand'] ?? 'all') !== 'all') {
            $brand = $f['brand'];
            $brandClause = [
                '$or' => [
                    ['brandJunior' => $brand],
                    ['brandSenior' => $brand],
                    ['brandExpert' => $brand],
                ],
            ];
            if (isset($q['$and'])) {
                $q['$and'][] = $brandClause;
            } else {
                $q['$and'] = [$brandClause];
            }
        }
    
        /* On récupère aussi le niveau pour éviter une requête plus loin */
        $ids = [];
        foreach ($users->find($q, ['projection' => ['_id' => 1, 'level' => 1]]) as $doc) {
            $ids[(string) $doc->_id] = $doc['level'] ?? 'Junior';
        }
        return $ids; // [ '656a…' => 'Senior', … ]
    }
}

if (!function_exists('getAllocation')) {
    function getAllocation(
        Collection $c,
        $uId,
        string $level,
        string $type,
        bool $needMgr = false
    ): ?array {
        try {
            $q = ['user' => $uId, 'level' => $level, 'type' => $type];
            if ($needMgr) $q['activeManager'] = true;
        
            $doc = $c->findOne($q);
            return $doc ? $doc->getArrayCopy() : null;
        } catch (\Exception $e) {
            error_log("Error in getAllocation(): " . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('hasBothAlloc')) {
    function hasBothAlloc(Collection $c, $uId, string $lvl): bool
    {
        return getAllocation($c, $uId, $lvl, 'Factuel')    !== null
            && getAllocation($c, $uId, $lvl, 'Declaratif') !== null;
    }
}

if (!function_exists('hasBothActive')) {
    function hasBothActive(Collection $c, $uId, string $lvl): bool
    {
        $fact = getAllocation($c, $uId, $lvl, 'Factuel');
        $decl = getAllocation($c, $uId, $lvl, 'Declaratif', true);
    
        return $fact && $decl &&
               ($fact['active']        ?? false) &&
               ($decl['active']        ?? false) &&
               ($decl['activeManager'] ?? false);
    }
}

try {
/* ════════════════════ 4. Population concernée ═════════════════════════════ */
$techLevels = getTechnicians($usersCol, $filters); // id => level

/* ════════════════════ 5. Calcul par niveau ════════════════════════════════ */
$levels = ['Junior', 'Senior', 'Expert'];

/* --- Pré-initialisation pour éviter les “Undefined index” ----------------- */
$statsTests = [
    'Junior' => ['done' => 0, 'total' => 0],
    'Senior' => ['done' => 0, 'total' => 0],
    'Expert' => ['done' => 0, 'total' => 0],
];
/* ------------------------------------------------------------------------- */

$doneSet  = []; // personnes uniques ayant terminé
$totalSet = []; // personnes uniques devant passer

foreach ($levels as $lvl) {
    /* Si un filtre de niveau est appliqué, on peut ignorer les autres niveaux.
       Les valeurs pré-initialisées resteront donc à 0. */
    if ($wantedLevel !== 'all' && $lvl !== $wantedLevel) {
        continue;
    }

    $done = $total = 0;

    foreach ($techLevels as $id => $userLevel) {
        if ($userLevel !== $lvl) continue; // ne compte que son vrai niveau

        try {
            $oid = new MongoDB\BSON\ObjectId($id);
        } catch (\Exception $e) {
            error_log("Error creating ObjectId for id $id: " . $e->getMessage());
            continue;
        }

        if (hasBothAlloc($allocCol, $oid, $lvl)) {
            $total++;
            $totalSet[$id] = true;

            if (hasBothActive($allocCol, $oid, $lvl)) {
                $done++;
                $doneSet[$id] = true;
            }
        }
    }

    /* Mise à jour du tableau pré-initialisé */
    $statsTests[$lvl]['done']  = $done;
    $statsTests[$lvl]['total'] = $total;
}

/* -------------------- COLONNE TOTAL (personnes uniques) ------------------ */
$statsTests['Total'] = [
    'done'  => count($doneSet),
    'total' => count($totalSet),
];

} catch (\Throwable $e) {
    error_log("stats_tests query error: " . $e->getMessage());
    return $emptyStats;
}

/* ════════════════════ 6. Retour ═══════════════════════════════════════════ */
error_log("Stats calculation complete. Returning data for all levels.");

// Make sure we have valid data for each level
foreach (['Junior', 'Senior', 'Expert', 'Total'] as $level) {
    if (!isset($statsTests[$level])) {
        $statsTests[$level] = ['done' => 0, 'total' => 0];
    }
    
    // Ensure numbers are integers
    $statsTests[$level]['done'] = (int)($statsTests[$level]['done'] ?? 0);
    $statsTests[$level]['total'] = (int)($statsTests[$level]['total'] ?? 0);
}

return $statsTests;

/* ════════════════════ 7. Fin du script ═══════════════════════════════════ */
?>
