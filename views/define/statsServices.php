<?php
/**
 * StatsService.php
 * =====================
 * Récupère via $_GET : subsidiary, agency, managerId, brand, level,
 * thresholdLocal, thresholdGroup.
 * Construit un pipeline d'agrégation pour :
 *   - Flatten => (manager, code, brand, level, occurrences)
 *   - Group par manager => codesDetail (+ distinct/weighted par all, junior, senior, expert)
 *   - Group par agency => idem
 *   - Group par subsidiary => idem
 *   - (Optionnel) Group final => "CFAO GROUP" => apply thresholdGroup = 12
 */

use MongoDB\Client as MongoClient;
use MongoDB\BSON\ObjectId;

require_once __DIR__ . '/vendor/autoload.php'; // Ajustez selon votre autoloader / emplacement

// --------------------------------------------------
// 1) Lecture des paramètres GET ou valeurs par défaut
// --------------------------------------------------
$subsidiary     = isset($_GET['subsidiary'])     ? trim($_GET['subsidiary'])      : 'all';
$agency         = isset($_GET['agency'])         ? trim($_GET['agency'])          : 'all';
$managerId      = isset($_GET['managerId'])      ? trim($_GET['managerId'])       : 'all';
$brandFilter    = isset($_GET['brand'])          ? trim($_GET['brand'])           : 'all';
$levelFilter    = isset($_GET['level'])          ? trim($_GET['level'])           : 'all';

// Seuil local = 6 par défaut, global = 12
$thresholdLocal = isset($_GET['thresholdLocal']) ? (int)$_GET['thresholdLocal']   : 6;
$thresholdGroup = isset($_GET['thresholdGroup']) ? (int)$_GET['thresholdGroup']   : 12;

// --------------------------------------------------
// 2) Connexion MongoDB
// --------------------------------------------------
$mongoUri = "mongodb://localhost:27017";
$dbName   = "academy"; // Ajustez selon votre BD

try {
    $client = new MongoClient($mongoUri);
    $db     = $client->selectDatabase($dbName);
} catch (\Exception $e) {
    header("HTTP/1.1 500 Internal Server Error");
    echo "Erreur connexion Mongo: " . $e->getMessage();
    exit();
}

// On va travailler sur la collection "managersTechniciens"
$coll = $db->selectCollection("managersTechniciens");
$trainingsCollName = "trainings";

// --------------------------------------------------
// 3) Construire le pipeline d'agrégation
// --------------------------------------------------

// 3.1) Définir les conditions de filtrage dynamiques
$matchConditions = [];

// Filtrer par managerId si spécifié
if ($managerId !== 'all') {
    // Validation de l'ObjectId
    if (preg_match('/^[a-f\d]{24}$/i', $managerId)) {
        $matchConditions[] = ['$_id' => new ObjectId($managerId)];
    } else {
        header("HTTP/1.1 400 Bad Request");
        echo "managerId invalide.";
        exit();
    }
}

// Filtrer par brand si spécifié
if ($brandFilter !== 'all') {
    $matchConditions[] = ['trainDoc.brand' => $brandFilter];
}

// Filtrer par level si spécifié
if ($levelFilter !== 'all') {
    $matchConditions[] = ['trainDoc.level' => $levelFilter];
}

// --------------------------------------------------
// 3.2) Construire le pipeline
// --------------------------------------------------

$pipeline = [
    // ───────────────── [A] Flatten & Lookup trainings ─────────────────
    [ '$unwind' => '$trainingsBySubordinate' ],
    [ '$unwind' => '$trainingsBySubordinate.trainingsByBrand' ],
    [ '$unwind' => '$trainingsBySubordinate.trainingsByBrand.trainings' ],

    [
        '$lookup' => [
            'from'         => $trainingsCollName,
            'localField'   => 'trainingsBySubordinate.trainingsByBrand.trainings', // code
            'foreignField' => 'code',
            'as'           => 'trainDoc'
        ]
    ],
    [
        '$unwind' => [
            'path' => '$trainDoc',
            'preserveNullAndEmptyArrays' => false
        ]
    ],
    // Appliquer les conditions de filtrage dynamiques pour brand et level
];

// Ajouter dynamiquement le $match si des conditions existent
if (!empty($matchConditions)) {
    $pipeline[] = ['$match' => ['$and' => $matchConditions]];
}

// Projet (managerId, managerName, code, brand, level)
$pipeline[] = [
    '$project' => [
        '_id'          => 0,
        'managerId'    => '$_id',
        'managerName'  => [ '$concat' => ['$firstName',' ','$lastName'] ],
        'code'         => '$trainDoc.code',
        'brand'        => '$trainDoc.brand',
        'level'        => [ '$ifNull' => ['$trainDoc.level','UNKNOWN'] ]
    ]
];

// ───────────────── [B] Group (managerId, code, brand, level) => occurrences ─────────────────
$pipeline[] = [
    '$group' => [
        '_id' => [
            'managerId'   => '$managerId',
            'managerName' => '$managerName',
            'code'        => '$code',
            'brand'       => '$brand',
            'level'       => '$level'
        ],
        'occurrences' => [ '$sum' => 1 ]
    ]
];

// ───────────────── [C] Re-group par manager => codesDetail ─────────────────
$pipeline[] = [
    '$group' => [
        '_id' => [
            'managerId'   => '$_id.managerId',
            'managerName' => '$_id.managerName'
        ],
        'codesDetail' => [
            '$push' => [
                'code'       => '$_id.code',
                'brand'      => '$_id.brand',
                'level'      => '$_id.level',
                'occurrences'=> '$occurrences'
            ]
        ]
    ]
];

// Maintenant, calculer distinct/weighted par ALL, Junior, Senior, Expert => thresholdLocal
$pipeline[] = [
    '$project' => [
        '_id'         => 0,
        'managerId'   => '$_id.managerId',
        'managerName' => '$_id.managerName',
        'codesDetail' => 1,

        // DistinctCodes ALL
        'distinctCodesAll' => [
            '$setUnion' => [
                [
                    '$map' => [
                        'input' => '$codesDetail',
                        'as'    => 'cd',
                        'in'    => '$$cd.code'
                    ]
                ],
                []
            ]
        ],
        // DistinctCodes Junior
        'distinctCodesJunior' => [
            '$setUnion' => [
                [
                    '$map' => [
                        'input' => [
                            '$filter' => [
                                'input' => '$codesDetail',
                                'as'    => 'f',
                                'cond'  => [ '$eq' => ['$$f.level', 'Junior'] ]
                            ]
                        ],
                        'as' => 'ff',
                        'in' => '$$ff.code'
                    ]
                ],
                []
            ]
        ],
        // DistinctCodes Senior
        'distinctCodesSenior' => [
            '$setUnion' => [
                [
                    '$map' => [
                        'input' => [
                            '$filter' => [
                                'input' => '$codesDetail',
                                'as'    => 'f',
                                'cond'  => [ '$eq' => ['$$f.level', 'Senior'] ]
                            ]
                        ],
                        'as' => 'ff',
                        'in' => '$$ff.code'
                    ]
                ],
                []
            ]
        ],
        // DistinctCodes Expert
        'distinctCodesExpert' => [
            '$setUnion' => [
                [
                    '$map' => [
                        'input' => [
                            '$filter' => [
                                'input' => '$codesDetail',
                                'as'    => 'f',
                                'cond'  => [ '$eq' => ['$$f.level', 'Expert'] ]
                            ]
                        ],
                        'as' => 'ff',
                        'in' => '$$ff.code'
                    ]
                ],
                []
            ]
        ],

        // Weighted => ALL  => \ceil(occurrences / thresholdLocal)
        'weightedCodesAll' => [
            '$reduce' => [
                'input'       => '$codesDetail',
                'initialValue'=> [],
                'in' => [
                    '$concatArrays' => [
                        '$$value',
                        [
                            '$map' => [
                                'input' => [
                                    '$range' => [
                                        0,
                                        [ '$ceil' => [ '$divide' => [ '$$this.occurrences', $thresholdLocal ] ] ]
                                    ]
                                ],
                                'as' => 'dummy',
                                'in' => '$$this.code'
                            ]
                        ]
                    ]
                ]
            ]
        ],

        // Weighted => Junior
        'weightedCodesJunior' => [
            '$reduce' => [
                'input' => [
                    '$filter' => [
                        'input' => '$codesDetail',
                        'as'    => 'f',
                        'cond'  => [ '$eq' => ['$$f.level','Junior'] ]
                    ]
                ],
                'initialValue' => [],
                'in' => [
                    '$concatArrays' => [
                        '$$value',
                        [
                            '$map' => [
                                'input' => [
                                    '$range' => [
                                        0,
                                        [ '$ceil' => [ '$divide' => [ '$$this.occurrences', $thresholdLocal ] ] ]
                                    ]
                                ],
                                'as' => 'dummy',
                                'in' => '$$this.code'
                            ]
                        ]
                    ]
                ]
            ]
        ],
        // Weighted => Senior
        'weightedCodesSenior' => [
            '$reduce' => [
                'input' => [
                    '$filter' => [
                        'input' => '$codesDetail',
                        'as'    => 'f',
                        'cond'  => [ '$eq' => ['$$f.level','Senior'] ]
                    ]
                ],
                'initialValue' => [],
                'in' => [
                    '$concatArrays' => [
                        '$$value',
                        [
                            '$map' => [
                                'input' => [
                                    '$range' => [
                                        0,
                                        [ '$ceil' => [ '$divide' => [ '$$this.occurrences', $thresholdLocal ] ] ]
                                    ]
                                ],
                                'as' => 'dummy',
                                'in' => '$$this.code'
                            ]
                        ]
                    ]
                ]
            ]
        ],
        // Weighted => Expert
        'weightedCodesExpert' => [
            '$reduce' => [
                'input' => [
                    '$filter' => [
                        'input' => '$codesDetail',
                        'as'    => 'f',
                        'cond'  => [ '$eq' => ['$$f.level','Expert'] ]
                    ]
                ],
                'initialValue' => [],
                'in' => [
                    '$concatArrays' => [
                        '$$value',
                        [
                            '$map' => [
                                'input' => [
                                    '$range' => [
                                        0,
                                        [ '$ceil' => [ '$divide' => [ '$$this.occurrences', $thresholdLocal ] ] ]
                                    ]
                                ],
                                'as' => 'dummy',
                                'in' => '$$this.code'
                            ]
                        ]
                    ]
                ]
            ]
        ],
    ]
];

$pipeline[] = [
    '$addFields' => [
        'distinctCountAll'    => [ '$size' => '$distinctCodesAll' ],
        'distinctCountJunior' => [ '$size' => '$distinctCodesJunior' ],
        'distinctCountSenior' => [ '$size' => '$distinctCodesSenior' ],
        'distinctCountExpert' => [ '$size' => '$distinctCodesExpert' ],

        'weightedCountAll'    => [ '$size' => '$weightedCodesAll' ],
        'weightedCountJunior' => [ '$size' => '$weightedCodesJunior' ],
        'weightedCountSenior' => [ '$size' => '$weightedCodesSenior' ],
        'weightedCountExpert' => [ '$size' => '$weightedCodesExpert' ]
    ]
];

// ───────────────── [D] Lookup => managersBySubsidiaryAgency => subsidiaryName, agencyName ─────────────────
$pipeline[] = [
    '$lookup' => [
        'from' => 'managersBySubsidiaryAgency',
        'let'  => [ 'mgrId' => '$managerId' ],
        'pipeline' => [
            [
                '$match' => [
                    '$expr' => [
                        '$gt' => [
                            [
                                '$size' => [
                                    '$filter' => [
                                        'input' => '$agencies',
                                        'as'    => 'ag',
                                        'cond'  => [
                                            '$gt' => [
                                                [
                                                    '$size' => [
                                                        '$filter' => [
                                                            'input' => '$$ag.managers',
                                                            'as'    => 'm',
                                                            'cond'  => [ '$eq' => ['$$m._id','$$mgrId'] ]
                                                        ]
                                                    ]
                                                ],
                                                0
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                            0
                        ]
                    ]
                ]
            ]
        ],
        'as' => 'mbaDocs'
    ]
];
$pipeline[] = [
    '$addFields' => [
        'mbaDoc' => [ '$arrayElemAt' => ['$mbaDocs', 0] ]
    ]
];
$pipeline[] = [
    '$addFields' => [
        'subsidiaryName' => [
            '$ifNull' => [
                '$mbaDoc.subsidiary',
                'UNKNOWN SUBSIDIARY'
            ]
        ],
        'agencyName' => [
            '$let' => [
                'vars' => [ 'allAgencies' => '$mbaDoc.agencies' ],
                'in' => [
                    '$ifNull' => [
                        [
                            '$arrayElemAt' => [
                                [
                                    '$map' => [
                                        'input' => [
                                            '$filter' => [
                                                'input' => '$$allAgencies',
                                                'as'    => 'ag',
                                                'cond'  => [
                                                    '$gt' => [
                                                        [
                                                            '$size' => [
                                                                '$filter' => [
                                                                    'input' => '$$ag.managers',
                                                                    'as'    => 'mm',
                                                                    'cond'  => [ '$eq' => ['$$mm._id','$managerId'] ]
                                                                ]
                                                            ]
                                                        ],
                                                        0
                                                    ]
                                                ]
                                            ]
                                        ],
                                        'as' => 'ag',
                                        'in' => '$$ag._id'
                                    ]
                                ],
                                0
                            ]
                        ],
                        'UNKNOWN AGENCY'
                    ]
                ]
            ]
        ]
    ]
];

// 3.3) Ajouter les conditions de filtrage dynamiques pour subsidiary et agency
$additionalMatchConditions = [];

if ($subsidiary !== 'all') {
    $additionalMatchConditions[] = ['subsidiaryName' => $subsidiary];
}

if ($agency !== 'all') {
    $additionalMatchConditions[] = ['agencyName' => $agency];
}

if (!empty($additionalMatchConditions)) {
    $pipeline[] = [
        '$match' => [
            '$and' => $additionalMatchConditions
        ]
    ];
}

// ───────────────── [E] Group par (subsidiaryName, agencyName) => managers[] ─────────────────
$pipeline[] = [
    '$group' => [
        '_id' => [
            'subsidiaryName' => '$subsidiaryName',
            'agencyName'     => '$agencyName'
        ],
        'managers' => [
            '$push' => [
                'managerId'           => '$managerId',
                'managerName'         => '$managerName',
                'codesDetail'         => '$codesDetail',

                'distinctCodesAll'    => '$distinctCodesAll',
                'distinctCodesJunior' => '$distinctCodesJunior',
                'distinctCodesSenior' => '$distinctCodesSenior',
                'distinctCodesExpert' => '$distinctCodesExpert',

                'weightedCodesAll'    => '$weightedCodesAll',
                'weightedCodesJunior' => '$weightedCodesJunior',
                'weightedCodesSenior' => '$weightedCodesSenior',
                'weightedCodesExpert' => '$weightedCodesExpert',

                'distinctCountAll'    => '$distinctCountAll',
                'distinctCountJunior' => '$distinctCountJunior',
                'distinctCountSenior' => '$distinctCountSenior',
                'distinctCountExpert' => '$distinctCountExpert',

                'weightedCountAll'    => '$weightedCountAll',
                'weightedCountJunior' => '$weightedCountJunior',
                'weightedCountSenior' => '$weightedCountSenior',
                'weightedCountExpert' => '$weightedCountExpert'
            ]
        ],
        // On stocke un array de codesDetail => on pourra "flatten" ensuite
        'allCodesDetailAgency' => [
            '$push' => '$codesDetail'
        ]
    ]
];

// 3.4) Continuer avec le reste du pipeline (calculs pour agency, subsidiary, group)

// ... (Le reste du pipeline reste inchangé)

// --------------------------------------------------
// 4) Exécuter le pipeline
// --------------------------------------------------

try {
    $cursor = $coll->aggregate($pipeline);
    $docs   = $cursor->toArray();

    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($docs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (\Exception $e) {
    // Log the error
    error_log("Erreur pipeline: " . $e->getMessage());

    header("HTTP/1.1 500 Internal Server Error");
    echo "Une erreur est survenue lors du traitement de votre requête.";
    exit();
}
?>
