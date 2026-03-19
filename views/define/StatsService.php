<?php
/**
*StatsService.php
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
use MongoDB\BSON\Javascript;

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
//    On veut :
//    [A] Flatten -> code, brand, level -> occurrences
//    [B] Group par manager => codesDetail (with brand, level, occurrences)
//    [C] distinct/weighted par ALL, Junior, Senior, Expert => thresholdLocal
//    [D] Jointure managersBySubsidiaryAgency => group par (subsidiary, agency)
//    [E] Au niveau agency => distinct/weighted => thresholdLocal
//    [F] Au niveau filiale => distinct/weighted => thresholdLocal
//    [G] Au final => group "CFAO GROUP" => distinct/weighted => thresholdGroup
// --------------------------------------------------

/**
 * Petite helper: pour injecter un match brand/level si pas "all"
 */
$matchBrandLevel = [
    '$expr' => [
        '$and' => [
            // brand
            ($brandFilter !== 'all')
              ? [ '$eq' => ['$trainDoc.brand', $brandFilter] ]
              : [ '$ne' => [1,2] ], // True => no effect
            // level
            ($levelFilter !== 'all')
              ? [ '$eq' => ['$trainDoc.level', $levelFilter] ]
              : [ '$ne' => [3,4] ]  // True => no effect
        ]
    ]
];

// On peut ajouter un match managerId, agency, subsidiary dans la partie "Jointure" => plus tard.

// On introduit 2 variables en $project => thresholdLocal, thresholdGroup
// On fera un petit $set ou $addFields pour y accéder en pipeline. 
// => Ou on peut injecter direct. Ci-dessous, on l'injecte "en dur" dans $range : [ 0, { $ceil: { $divide: [ "$$this.occurrences", thresholdLocal ] } } ] 

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
    // Filtrer brand/level ?
    [ '$match' => $matchBrandLevel ],

    // Projet (managerId, managerName, code, brand, level)
    [
        '$project' => [
            '_id'          => 0,
            'managerId'    => '$_id',
            'managerName'  => [ '$concat' => ['$firstName',' ','$lastName'] ],
            'code'         => '$trainDoc.code',
            'brand'        => '$trainDoc.brand',
            'level'        => [ '$ifNull' => ['$trainDoc.level','UNKNOWN'] ]
        ]
    ],

    // ───────────────── [B] Group (managerId, code, brand, level) => occurrences ─────────────────
    [
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
    ],

    // ───────────────── [C] Re-group par manager => codesDetail ─────────────────
    [
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
    ],

    // Now on calcule distinct/weighted par ALL, Junior, Senior, Expert => thresholdLocal
    [
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
    ],
    [
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
    ],

    // ───────────────── [D] Lookup => managersBySubsidiaryAgency => subsidiaryName, agencyName ─────────────────
    [
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
    ],
    [
        '$addFields' => [
            'mbaDoc' => [ '$arrayElemAt' => ['$mbaDocs', 0] ]
        ]
    ],
    [
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
    ],

    // ───────────────── [E] Group par (subsidiaryName, agencyName) => managers[] ─────────────────
    [
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
    ],

    // 8) On flatten => "flattenedCodesDetailAgency"
    [
        '$addFields' => [
            'flattenedCodesDetailAgency' => [
                '$reduce' => [
                    'input' => '$allCodesDetailAgency',
                    'initialValue' => [],
                    'in' => [ '$concatArrays' => ['$$value','$$this'] ]
                ]
            ]
        ]
    ],

    // Re-group par code, level => sum occurrences => Weighted Agency (thresholdLocal)
    [
        '$addFields' => [
            'agencyCodesInfo' => [
                '$function' => [
                    'body' => "function(flatArr) {
                        const dict = {};
                        flatArr.forEach(obj => {
                            const key = obj.code + '||' + obj.level;
                            if (!dict[key]) {
                                dict[key] = { code: obj.code, level: obj.level, occurrences: 0 };
                            }
                            dict[key].occurrences += obj.occurrences;
                        });
                        return Object.values(dict);
                    }",
                    'args' => [ '$flattenedCodesDetailAgency' ],
                    'lang' => 'js'
                ]
            ]
        ]
    ],

    // distinctCodesAgencyAll,Junior,Senior,Expert, Weighted => ...
    [
        '$addFields' => [
            // DistinctCodesAgencyAll => map -> setUnion
            'distinctCodesAgencyAll' => [
                '$setUnion' => [
                    [
                        '$map' => [
                            'input' => '$agencyCodesInfo',
                            'as'    => 'ci',
                            'in'    => '$$ci.code'
                        ]
                    ],
                    []
                ]
            ],
            'distinctCodesAgencyJunior' => [
                '$setUnion' => [
                    [
                        '$map' => [
                            'input' => [
                                '$filter' => [
                                    'input' => '$agencyCodesInfo',
                                    'as'    => 'aci',
                                    'cond'  => [ '$eq' => ['$$aci.level','Junior'] ]
                                ]
                            ],
                            'as' => 'fx',
                            'in' => '$$fx.code'
                        ]
                    ],
                    []
                ]
            ],
            'distinctCodesAgencySenior' => [
                '$setUnion' => [
                    [
                        '$map' => [
                            'input' => [
                                '$filter' => [
                                    'input' => '$agencyCodesInfo',
                                    'as'    => 'aci',
                                    'cond'  => [ '$eq' => ['$$aci.level','Senior'] ]
                                ]
                            ],
                            'as' => 'fx',
                            'in' => '$$fx.code'
                        ]
                    ],
                    []
                ]
            ],
            'distinctCodesAgencyExpert' => [
                '$setUnion' => [
                    [
                        '$map' => [
                            'input' => [
                                '$filter' => [
                                    'input' => '$agencyCodesInfo',
                                    'as'    => 'aci',
                                    'cond'  => [ '$eq' => ['$$aci.level','Expert'] ]
                                ]
                            ],
                            'as' => 'fx',
                            'in' => '$$fx.code'
                        ]
                    ],
                    []
                ]
            ],

            // Weighted => \ceil(occurrences / thresholdLocal)
            'weightedCodesAgencyAll' => [
                '$reduce' => [
                    'input' => '$agencyCodesInfo',
                    'initialValue' => [],
                    'in' => [
                        '$concatArrays' => [
                            '$$value',
                            [
                                '$map' => [
                                    'input' => [
                                        '$range' => [
                                            0,
                                            [ '$ceil' => [ '$divide' => ['$$this.occurrences',$thresholdLocal] ] ]
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
            'weightedCodesAgencyJunior' => [
                '$reduce' => [
                    'input' => [
                        '$filter' => [
                            'input' => '$agencyCodesInfo',
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
                                            [ '$ceil' => [ '$divide' => ['$$this.occurrences',$thresholdLocal] ] ]
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
            'weightedCodesAgencySenior' => [
                '$reduce' => [
                    'input' => [
                        '$filter' => [
                            'input' => '$agencyCodesInfo',
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
                                            [ '$ceil' => [ '$divide' => ['$$this.occurrences',$thresholdLocal] ] ]
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
            'weightedCodesAgencyExpert' => [
                '$reduce' => [
                    'input' => [
                        '$filter' => [
                            'input' => '$agencyCodesInfo',
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
                                            [ '$ceil' => [ '$divide' => ['$$this.occurrences',$thresholdLocal] ] ]
                                        ]
                                    ],
                                    'as' => 'dummy',
                                    'in' => '$$this.code'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ],
    [
        '$addFields' => [
            'distinctCountAgencyAll'    => [ '$size' => '$distinctCodesAgencyAll' ],
            'distinctCountAgencyJunior' => [ '$size' => '$distinctCodesAgencyJunior' ],
            'distinctCountAgencySenior' => [ '$size' => '$distinctCodesAgencySenior' ],
            'distinctCountAgencyExpert' => [ '$size' => '$distinctCodesAgencyExpert' ],

            'weightedCountAgencyAll'    => [ '$size' => '$weightedCodesAgencyAll' ],
            'weightedCountAgencyJunior' => [ '$size' => '$weightedCodesAgencyJunior' ],
            'weightedCountAgencySenior' => [ '$size' => '$weightedCodesAgencySenior' ],
            'weightedCountAgencyExpert' => [ '$size' => '$weightedCodesAgencyExpert' ]
        ]
    ],

    // ───────────────── [F] Group par filiale => 1 doc par filiale => agencies[] ─────────────────
    [
        '$group' => [
            '_id' => '$._id.subsidiaryName',
            'agencies' => [
                '$push' => [
                    'agencyName' => '$_id.agencyName',
                    'managers'   => '$managers',

                    'distinctCodesAgencyAll'    => '$distinctCodesAgencyAll',
                    'distinctCodesAgencyJunior' => '$distinctCodesAgencyJunior',
                    'distinctCodesAgencySenior' => '$distinctCodesAgencySenior',
                    'distinctCodesAgencyExpert' => '$distinctCodesAgencyExpert',

                    'weightedCodesAgencyAll'    => '$weightedCodesAgencyAll',
                    'weightedCodesAgencyJunior' => '$weightedCodesAgencyJunior',
                    'weightedCodesAgencySenior' => '$weightedCodesAgencySenior',
                    'weightedCodesAgencyExpert' => '$weightedCodesAgencyExpert',

                    'distinctCountAgencyAll'    => '$distinctCountAgencyAll',
                    'distinctCountAgencyJunior' => '$distinctCountAgencyJunior',
                    'distinctCountAgencySenior' => '$distinctCountAgencySenior',
                    'distinctCountAgencyExpert' => '$distinctCountAgencyExpert',

                    'weightedCountAgencyAll'    => '$weightedCountAgencyAll',
                    'weightedCountAgencyJunior' => '$weightedCountAgencyJunior',
                    'weightedCountAgencySenior' => '$weightedCountAgencySenior',
                    'weightedCountAgencyExpert' => '$weightedCountAgencyExpert',

                    // On stocke flattenedCodesDetailAgency => si on veut regrouper ensuite
                    'flattenedCodesDetailAgency' => '$flattenedCodesDetailAgency'
                ]
            ]
        ]
    ],

    // 9) Flatten => Filiale => "flattenedCodesDetailSubsidiary"
    [
        '$addFields' => [
            'flattenedCodesDetailSubsidiary' => [
                '$reduce' => [
                    'input' => [
                        '$map' => [
                            'input' => '$agencies',
                            'as'    => 'ag',
                            'in'    => [
                                '$ifNull' => [
                                    '$$ag.flattenedCodesDetailAgency',
                                    []
                                ]
                            ]
                        ]
                    ],
                    'initialValue' => [],
                    'in' => [ '$concatArrays' => ['$$value','$$this'] ]
                ]
            ]
        ]
    ],

    // 10) Re-group => sum occurrences => Weighted Filiale (thresholdLocal)
    [
        '$addFields' => [
            'subsidiaryCodesInfo' => [
                '$function' => [
                    'body' => "function(flatArr) {
                        const dict = {};
                        flatArr.forEach(obj => {
                            const key = obj.code + '||' + obj.level;
                            if (!dict[key]) {
                                dict[key] = { code: obj.code, level: obj.level, occurrences: 0 };
                            }
                            dict[key].occurrences += obj.occurrences;
                        });
                        return Object.values(dict);
                    }",
                    'args' => [ '$flattenedCodesDetailSubsidiary' ],
                    'lang' => 'js'
                ]
            ]
        ]
    ],
    [
        '$addFields' => [
            'distinctCodesSubsidiaryAll' => [
                '$setUnion' => [
                    [
                        '$map' => [
                            'input' => '$subsidiaryCodesInfo',
                            'as'    => 'sc',
                            'in'    => '$$sc.code'
                        ]
                    ],
                    []
                ]
            ],
            'distinctCodesSubsidiaryJunior' => [
                '$setUnion' => [
                    [
                        '$map' => [
                            'input' => [
                                '$filter' => [
                                    'input' => '$subsidiaryCodesInfo',
                                    'as'    => 'ssc',
                                    'cond'  => [ '$eq' => ['$$ssc.level','Junior'] ]
                                ]
                            ],
                            'as' => 'fx',
                            'in' => '$$fx.code'
                        ]
                    ],
                    []
                ]
            ],
            'distinctCodesSubsidiarySenior' => [
                '$setUnion' => [
                    [
                        '$map' => [
                            'input' => [
                                '$filter' => [
                                    'input' => '$subsidiaryCodesInfo',
                                    'as'    => 'ssc',
                                    'cond'  => [ '$eq' => ['$$ssc.level','Senior'] ]
                                ]
                            ],
                            'as' => 'fx',
                            'in' => '$$fx.code'
                        ]
                    ],
                    []
                ]
            ],
            'distinctCodesSubsidiaryExpert' => [
                '$setUnion' => [
                    [
                        '$map' => [
                            'input' => [
                                '$filter' => [
                                    'input' => '$subsidiaryCodesInfo',
                                    'as'    => 'ssc',
                                    'cond'  => [ '$eq' => ['$$ssc.level','Expert'] ]
                                ]
                            ],
                            'as' => 'fx',
                            'in' => '$$fx.code'
                        ]
                    ],
                    []
                ]
            ],

            // Weighted => \ceil(occurrences / thresholdLocal)
            'weightedCodesSubsidiaryAll' => [
                '$reduce' => [
                    'input' => '$subsidiaryCodesInfo',
                    'initialValue' => [],
                    'in' => [
                        '$concatArrays' => [
                            '$$value',
                            [
                                '$map' => [
                                    'input' => [
                                        '$range' => [
                                            0,
                                            [ '$ceil' => [ '$divide' => ['$$this.occurrences',$thresholdLocal] ] ]
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
            'weightedCodesSubsidiaryJunior' => [
                '$reduce' => [
                    'input' => [
                        '$filter' => [
                            'input' => '$subsidiaryCodesInfo',
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
                                            [ '$ceil' => [ '$divide' => ['$$this.occurrences',$thresholdLocal] ] ]
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
            'weightedCodesSubsidiarySenior' => [
                '$reduce' => [
                    'input' => [
                        '$filter' => [
                            'input' => '$subsidiaryCodesInfo',
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
                                            [ '$ceil' => [ '$divide' => ['$$this.occurrences',$thresholdLocal] ] ]
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
            'weightedCodesSubsidiaryExpert' => [
                '$reduce' => [
                    'input' => [
                        '$filter' => [
                            'input' => '$subsidiaryCodesInfo',
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
                                            [ '$ceil' => [ '$divide' => ['$$this.occurrences',$thresholdLocal] ] ]
                                        ]
                                    ],
                                    'as' => 'dummy',
                                    'in' => '$$this.code'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ],
    [
        '$addFields' => [
            'distinctCountSubsidiaryAll'    => [ '$size' => '$distinctCodesSubsidiaryAll' ],
            'distinctCountSubsidiaryJunior' => [ '$size' => '$distinctCodesSubsidiaryJunior' ],
            'distinctCountSubsidiarySenior' => [ '$size' => '$distinctCodesSubsidiarySenior' ],
            'distinctCountSubsidiaryExpert' => [ '$size' => '$distinctCodesSubsidiaryExpert' ],

            'weightedCountSubsidiaryAll'    => [ '$size' => '$weightedCodesSubsidiaryAll' ],
            'weightedCountSubsidiaryJunior' => [ '$size' => '$weightedCodesSubsidiaryJunior' ],
            'weightedCountSubsidiarySenior' => [ '$size' => '$weightedCodesSubsidiarySenior' ],
            'weightedCountSubsidiaryExpert' => [ '$size' => '$weightedCodesSubsidiaryExpert' ]
        ]
    ],
    // ───────────────── [G] On veut potentiellement un regroupement "CFAO GROUP" => thresholdGroup = 12 ─────────────────
    //    => On group tout sur un doc unique => groupName = "CFAO GROUP"
    //    => On flatten tout => sum occurrences => on duplique
    [
        '$group' => [
            '_id' => 'CFAO GROUP',
            'subsidiaries' => [
                '$push' => [
                    'subsidiaryName' => '$_id',
                    'agencies'       => '$agencies',

                    'distinctCodesSubsidiaryAll'    => '$distinctCodesSubsidiaryAll',
                    'distinctCodesSubsidiaryJunior' => '$distinctCodesSubsidiaryJunior',
                    'distinctCodesSubsidiarySenior' => '$distinctCodesSubsidiarySenior',
                    'distinctCodesSubsidiaryExpert' => '$distinctCodesSubsidiaryExpert',

                    'weightedCodesSubsidiaryAll'    => '$weightedCodesSubsidiaryAll',
                    'weightedCodesSubsidiaryJunior' => '$weightedCodesSubsidiaryJunior',
                    'weightedCodesSubsidiarySenior' => '$weightedCodesSubsidiarySenior',
                    'weightedCodesSubsidiaryExpert' => '$weightedCodesSubsidiaryExpert',

                    'distinctCountSubsidiaryAll'    => '$distinctCountSubsidiaryAll',
                    'distinctCountSubsidiaryJunior' => '$distinctCountSubsidiaryJunior',
                    'distinctCountSubsidiarySenior' => '$distinctCountSubsidiarySenior',
                    'distinctCountSubsidiaryExpert' => '$distinctCountSubsidiaryExpert',

                    'weightedCountSubsidiaryAll'    => '$weightedCountSubsidiaryAll',
                    'weightedCountSubsidiaryJunior' => '$weightedCountSubsidiaryJunior',
                    'weightedCountSubsidiarySenior' => '$weightedCountSubsidiarySenior',
                    'weightedCountSubsidiaryExpert' => '$weightedCountSubsidiaryExpert',

                    'flattenedCodesDetailSubsidiary' => '$flattenedCodesDetailSubsidiary'
                ]
            ]
        ]
    ],
    // flatten => "flattenedCodesDetailGroup"
    [
        '$addFields' => [
            'flattenedCodesDetailGroup' => [
                '$reduce' => [
                    'input' => [
                        '$map' => [
                            'input' => '$subsidiaries',
                            'as'    => 'subs',
                            'in'    => [ '$ifNull' => ['$$subs.flattenedCodesDetailSubsidiary',[]] ]
                        ]
                    ],
                    'initialValue' => [],
                    'in' => [ '$concatArrays' => ['$$value','$$this'] ]
                ]
            ]
        ]
    ],
    // Re-group => occurrences => WeightedGroup ( thresholdGroup = 12 )
    [
        '$addFields' => [
            'groupCodesInfo' => [
                '$function' => [
                    'body' => "function(flatArr) {
                        const dict = {};
                        flatArr.forEach(obj => {
                            const key = obj.code + '||' + obj.level;
                            if (!dict[key]) {
                                dict[key] = { code: obj.code, level: obj.level, occurrences: 0 };
                            }
                            dict[key].occurrences += obj.occurrences;
                        });
                        return Object.values(dict);
                    }",
                    'args' => [ '$flattenedCodesDetailGroup' ],
                    'lang' => 'js'
                ]
            ]
        ]
    ],
    // distinctCodesGroupAll, Junior, Senior, Expert
    [
        '$addFields' => [
            'distinctCodesGroupAll' => [
                '$setUnion' => [
                    [
                        '$map' => [
                            'input' => '$groupCodesInfo',
                            'as'    => 'gc',
                            'in'    => '$$gc.code'
                        ]
                    ],
                    []
                ]
            ],
            'distinctCodesGroupJunior' => [
                '$setUnion' => [
                    [
                        '$map' => [
                            'input' => [
                                '$filter' => [
                                    'input' => '$groupCodesInfo',
                                    'as'    => 'gcf',
                                    'cond'  => [ '$eq' => ['$$gcf.level','Junior'] ]
                                ]
                            ],
                            'as' => 'fx',
                            'in' => '$$fx.code'
                        ]
                    ],
                    []
                ]
            ],
            'distinctCodesGroupSenior' => [
                '$setUnion' => [
                    [
                        '$map' => [
                            'input' => [
                                '$filter' => [
                                    'input' => '$groupCodesInfo',
                                    'as'    => 'gcf',
                                    'cond'  => [ '$eq' => ['$$gcf.level','Senior'] ]
                                ]
                            ],
                            'as' => 'fx',
                            'in' => '$$fx.code'
                        ]
                    ],
                    []
                ]
            ],
            'distinctCodesGroupExpert' => [
                '$setUnion' => [
                    [
                        '$map' => [
                            'input' => [
                                '$filter' => [
                                    'input' => '$groupCodesInfo',
                                    'as'    => 'gcf',
                                    'cond'  => [ '$eq' => ['$$gcf.level','Expert'] ]
                                ]
                            ],
                            'as' => 'fx',
                            'in' => '$$fx.code'
                        ]
                    ],
                    []
                ]
            ],

            // Weighted => \ceil(occurrences / thresholdGroup)
            'weightedCodesGroupAll' => [
                '$reduce' => [
                    'input' => '$groupCodesInfo',
                    'initialValue' => [],
                    'in' => [
                        '$concatArrays' => [
                            '$$value',
                            [
                                '$map' => [
                                    'input' => [
                                        '$range' => [
                                            0,
                                            [ '$ceil' => [ '$divide' => ['$$this.occurrences',$thresholdGroup] ] ]
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
            'weightedCodesGroupJunior' => [
                '$reduce' => [
                    'input' => [
                        '$filter' => [
                            'input' => '$groupCodesInfo',
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
                                            [ '$ceil' => [ '$divide' => ['$$this.occurrences',$thresholdGroup] ] ]
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
            'weightedCodesGroupSenior' => [
                '$reduce' => [
                    'input' => [
                        '$filter' => [
                            'input' => '$groupCodesInfo',
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
                                            [ '$ceil' => [ '$divide' => ['$$this.occurrences',$thresholdGroup] ] ]
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
            'weightedCodesGroupExpert' => [
                '$reduce' => [
                    'input' => [
                        '$filter' => [
                            'input' => '$groupCodesInfo',
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
                                            [ '$ceil' => [ '$divide' => ['$$this.occurrences',$thresholdGroup] ] ]
                                        ]
                                    ],
                                    'as' => 'dummy',
                                    'in' => '$$this.code'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ],
    [
        '$addFields' => [
            'distinctCountGroupAll' => [ '$size' => '$distinctCodesGroupAll' ],
            'distinctCountGroupJunior' => [ '$size' => '$distinctCodesGroupJunior' ],
            'distinctCountGroupSenior' => [ '$size' => '$distinctCodesGroupSenior' ],
            'distinctCountGroupExpert' => [ '$size' => '$distinctCodesGroupExpert' ],

            'weightedCountGroupAll' => [ '$size' => '$weightedCodesGroupAll' ],
            'weightedCountGroupJunior' => [ '$size' => '$weightedCodesGroupJunior' ],
            'weightedCountGroupSenior' => [ '$size' => '$weightedCodesGroupSenior' ],
            'weightedCountGroupExpert' => [ '$size' => '$weightedCodesGroupExpert' ]
        ]
    ],
    // Final projection
    [
        '$project' => [
            '_id' => 0,
            'groupName' => '$_id',

            'subsidiaries' => 1,

            'distinctCodesGroupAll'    => 1,
            'distinctCodesGroupJunior' => 1,
            'distinctCodesGroupSenior' => 1,
            'distinctCodesGroupExpert' => 1,

            'weightedCodesGroupAll'    => 1,
            'weightedCodesGroupJunior' => 1,
            'weightedCodesGroupSenior' => 1,
            'weightedCodesGroupExpert' => 1,

            'distinctCountGroupAll'    => 1,
            'distinctCountGroupJunior' => 1,
            'distinctCountGroupSenior' => 1,
            'distinctCountGroupExpert' => 1,

            'weightedCountGroupAll'    => 1,
            'weightedCountGroupJunior' => 1,
            'weightedCountGroupSenior' => 1,
            'weightedCountGroupExpert' => 1
        ]
    ]
];

// --------------------------------------------------
// 4) Exécuter le pipeline
// --------------------------------------------------

try {
    $cursor = $coll->aggregate($pipeline);
    $docs   = iterator_to_array($cursor);

    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($docs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (\Exception $e) {
    header("HTTP/1.1 500 Internal Server Error");
    echo "Erreur pipeline: " . $e->getMessage();
    exit();
}
