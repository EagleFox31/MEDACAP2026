<?php
    session_start();
    include_once "../language.php";
    include_once "../userFilters.php"; // Inclusion du fichier contenant les fonctions de filtrage
    include_once "../partials/background-manager.php"; // Include background manager

    setPageBackground("bg-dashboard"); // Set the dashboard background

    if (!isset($_SESSION["profile"])) {
        header("Location: ../../");
        exit();
    } else {
        require_once "../../vendor/autoload.php";

        // Create connection
        $conn = new MongoDB\Client("mongodb://localhost:27017");

        // Connecting in database
        $academy = $conn->academy;

        // Connecting in collections
        $users = $academy->users;
        $quizzes = $academy->quizzes;
        $vehicles = $academy->vehicles;
        $tests = $academy->tests;
        $exams = $academy->exams;
        $results = $academy->results;
        $allocations = $academy->allocations;
        $connections = $academy->connections;
        $questionsCollection = $academy->questions;
        $userCountry   = $_SESSION['country'] ?? '';
                      // on pourra en rajouter plus tard
        $selectedBrand = isset($_GET['brand']) ? $_GET['brand'] : '';   // '' => toutes
             // 2) Prépare la pipeline au format PHP
$pipeline = [

    /* 1️⃣  Filtre commun */
    [
        '$match' => [
            'active'  => true,
            'country' => 'Madagascar',
            '$or'     => [
                [ 'profile' => 'Technicien' ],
                [ 'profile' => 'Manager', 'test' => true ],
            ],
        ],
    ],

    /* 2️⃣  Comptages */
    [
        '$group' => [
            '_id' => null,

            // totaux par niveau
            'JuniorTotal' => [
                '$sum' => [
                    '$cond' => [
                        [ '$eq' => [ '$level', 'Junior' ] ],
                        1,
                        0,
                    ],
                ],
            ],
            'SeniorTotal' => [
                '$sum' => [
                    '$cond' => [
                        [ '$eq' => [ '$level', 'Senior' ] ],
                        1,
                        0,
                    ],
                ],
            ],
            'ExpertTotal' => [
                '$sum' => [
                    '$cond' => [
                        [ '$eq' => [ '$level', 'Expert' ] ],
                        1,
                        0,
                    ],
                ],
            ],

            /* ——— Junior + TOYOTA ——— */
            'Junior_Toyota_brandJunior' => [
                '$sum' => [
                    '$cond' => [
                        [
                            '$and' => [
                                [ '$eq' => [ '$level', 'Junior' ] ],
                                [ '$in' => [ 'TOYOTA', '$brandJunior' ] ],
                            ],
                        ],
                        1,
                        0,
                    ],
                ],
            ],

            /* ——— Senior ——— */
            'Senior_Toyota_brandSenior' => [
                '$sum' => [
                    '$cond' => [
                        [
                            '$and' => [
                                [ '$eq' => [ '$level', 'Senior' ] ],
                                [ '$in' => [ 'TOYOTA', '$brandSenior' ] ],
                            ],
                        ],
                        1,
                        0,
                    ],
                ],
            ],
            'Senior_Toyota_brandJunior' => [
                '$sum' => [
                    '$cond' => [
                        [
                            '$and' => [
                                [ '$eq' => [ '$level', 'Senior' ] ],
                                [ '$in' => [ 'TOYOTA', '$brandJunior' ] ],
                            ],
                        ],
                        1,
                        0,
                    ],
                ],
            ],

            /* ——— Expert ——— */
            'Expert_Toyota_brandExpert' => [
                '$sum' => [
                    '$cond' => [
                        [
                            '$and' => [
                                [ '$eq' => [ '$level', 'Expert' ] ],
                                [ '$in' => [ 'TOYOTA', '$brandExpert' ] ],
                            ],
                        ],
                        1,
                        0,
                    ],
                ],
            ],
            'Expert_Toyota_brandSenior' => [
                '$sum' => [
                    '$cond' => [
                        [
                            '$and' => [
                                [ '$eq' => [ '$level', 'Expert' ] ],
                                [ '$in' => [ 'TOYOTA', '$brandSenior' ] ],
                            ],
                        ],
                        1,
                        0,
                    ],
                ],
            ],
            'Expert_Toyota_brandJunior' => [
                '$sum' => [
                    '$cond' => [
                        [
                            '$and' => [
                                [ '$eq' => [ '$level', 'Expert' ] ],
                                [ '$in' => [ 'TOYOTA', '$brandJunior' ] ],
                            ],
                        ],
                        1,
                        0,
                    ],
                ],
            ],
        ],
    ],

    /* 3️⃣  Supprime _id pour ne garder que les compteurs */
    [
        '$project' => [
            '_id' => 0,
        ],
    ],
];
/* 3) Lance l’agrégation et récupère le premier (et unique) document */
$result = $users->aggregate($pipeline)->toArray();
$stats  = $result[0] ?? [];   // tableau associatif avec tous les compteurs

/* 4) Exemple d’utilisation */
$juniorTotal  = $stats['JuniorTotal']  ?? 0;
$seniorTotal  = $stats['SeniorTotal']  ?? 0;
$expertTotal  = $stats['ExpertTotal']  ?? 0;


        if ($_SESSION['profile'] == 'Directeur Général' || $_SESSION['profile'] == 'Directeur Pièce et Service' || $_SESSION['profile'] == 'Directeur des Opérations' || $_SESSION['profile'] == 'Admin' || $_SESSION['profile'] == 'Ressource Humaine') {
            // Requête pour determiner les utilisateurs filiale
             // 1) Redéfinition de la fonction pour intégrer la marque
        function getTechnicians($users, $subsidiary, $level = null, $brand = '') {
            $query = [
                'profile'    => ['$in'=> ['Technicien','Manager']],
                'subsidiary' => $subsidiary,
                'active'     => true,
            ];

            #Filtrer par departement pour que l'utilisateur connecté voi uniquement les éléments de son département
            $user = $users->findOne(["username" => $_SESSION['username']]);

            $departements = explode(' & ', $user['department']);

            if (!empty($departements)) {
                $query['$or'] = [];
                foreach ($departements as $departement) {
                    $query['$or'][] = ['department' => new MongoDB\BSON\Regex($departement, 'i')];
                }
            }


            if ($level) {
                $query['level'] = $level;
            }
            if ($brand !== '') {
                switch ($level) {
                    case 'Junior':
                        $query['brandJunior'] = ['$in'=> [$brand]];
                        break;
                    case 'Senior':
                        $query['brandSenior'] = ['$in'=> [$brand]];
                        break;
                    case 'Expert':
                        $query['brandExpert'] = ['$in'=> [$brand]];
                        break;
                    default:
                        $query['$or'] = [
                            ['brandJunior'=> ['$in'=>[$brand]]],
                            ['brandSenior'=> ['$in'=>[$brand]]],
                            ['brandExpert'=> ['$in'=>[$brand]]],
                        ];
                }
            }
            // On ne sélectionne que l’_id pour plus de performance
            $cursor = $users->find($query, ['projection'=>['_id'=>1]]);
            $ids = [];
            foreach ($cursor as $doc) {
                $ids[] = $doc->_id;
            }
            return $ids;
        }
    // 1) Définition du filtre commun
$baseFilter = [
    'subsidiary' => $_SESSION['subsidiary'],
    'active'     => true,
    '$or'        => [
        ['profile' => 'Technicien'],
        ['profile' => 'Manager', 'test' => true],
    ],
];

// 2) Pipeline d'agrégation pour extraire la liste unique des marques non vides
$pipeline = [
    ['$match'   => $baseFilter],
    ['$project' => [
        // union des trois tableaux (ou champs) de marques
        'brands' => [
            '$setUnion' => [
                ['$ifNull' => ['$brandJunior', []]],
                ['$ifNull' => ['$brandSenior', []]],
                ['$ifNull' => ['$brandExpert', []]],
            ]
        ]
    ]],
    ['$unwind'  => '$brands'],                      // un doc par marque
    ['$match'   => ['brands' => ['$ne' => '']]],    // exclure les chaînes vides
    ['$group'   => ['_id' => '$brands']],           // regrouper pour l’unicité
    ['$sort'    => ['_id' => 1]],                   // tri alphabétique
];

// 3) Exécution et collecte
$cursor = $users->aggregate($pipeline);
$uniqueBrands = [];
foreach ($cursor as $doc) {
    $uniqueBrands[] = (string)$doc->_id;
}



    $countBrandsFili = count($uniqueBrands);

            

            // Get all technicians
            
            // Get Junior technicians
            $techniciansJu = getTechnicians($users, $_SESSION['subsidiary'], 'Junior',  $selectedBrand);
            $techniciansSe = getTechnicians($users, $_SESSION['subsidiary'], 'Senior',  $selectedBrand);
            $techniciansEx = getTechnicians($users, $_SESSION['subsidiary'], 'Expert',  $selectedBrand);
            $techniciansFi = array_merge(
                $techniciansJu,
                $techniciansSe,
                $techniciansEx
            );
            function getAllocation($allocations, $user, $level, $type, $activeManager = false) {
                $query = [
                    'user' => new MongoDB\BSON\ObjectId($user),
                    'level' => $level,
                    'type' => $type,
                    'active' => true
                ];
                if ($activeManager) {
                    $query['activeManager'] = true;
                }
                return $allocations->findOne(['$and' => [$query]]);
            }

            function getResults($results, $user, $level, $typeR, $type) {
                return $results->findOne([
                    '$and' => [
                        [
                            "user" => new MongoDB\BSON\ObjectId($user),
                            "level" => $level,
                            'typeR' => $typeR,
                            "type" => $type,
                        ],
                    ],
                ]);
            }

            function calculateAverage($scores, $totals) {
                $scoreSum = array_sum($scores);
                $totalSum = array_sum($totals);
                return $totalSum === 0 ? ($scoreSum * 100 / 1) : ($scoreSum * 100 / $totalSum);
            }
            
            // Requête pour recuperer les differents resultats filiale
            $resultsFacScoreJuF = [];
            $resultsDeclaScoreJuF = [];
            $resultsFacTotalJuF = [];
            $resultsDeclaTotalJuF = [];
            $resultsFacScoreSeF = [];
            $resultsDeclaScoreSeF = [];
            $resultsFacTotalSeF = [];
            $resultsDeclaTotalSeF = [];
            $resultsFacScoreExF = [];
            $resultsDeclaScoreExF = [];
            $resultsFacTotalExF = [];
            $resultsDeclaTotalExF = [];
            $doneTestExTxF = [];

            foreach ($techniciansFi as $tech) {
                // Junior
                $alloFacJu = getAllocation($allocations, $tech, 'Junior', 'Factuel');
                $alloDeclaJu = getAllocation($allocations, $tech, 'Junior', 'Declaratif', true);
                
                if ($alloFacJu && $alloDeclaJu) {
                    $resultFacJu = getResults($results, $tech, "Junior", 'Technicien', "Factuel");
                    if ($resultFacJu) {
                        $resultsFacScoreJuF[] = $resultFacJu['score'];
                        $resultsFacTotalJuF[] = $resultFacJu['total'];
                    }
                    
                    $resultDeclaJu = getResults($results, $tech, "Junior", "Technicien - Manager", "Declaratif");
                    if ($resultDeclaJu) {
                        $resultsDeclaScoreJuF[] = $resultDeclaJu['score'];
                        $resultsDeclaTotalJuF[] = $resultDeclaJu['total'];
                    }
                }

                // Senior
                $alloFacSe = getAllocation($allocations, $tech, 'Senior', 'Factuel');
                $alloDeclaSe = getAllocation($allocations, $tech, 'Senior', 'Declaratif', true);
                
                if ($alloFacSe && $alloDeclaSe) {
                    $resultFacSe = getResults($results, $tech, "Senior", 'Technicien', "Factuel");
                    if ($resultFacSe) {
                        $resultsFacScoreSeF[] = $resultFacSe['score'];
                        $resultsFacTotalSeF[] = $resultFacSe['total'];
                    }
                    
                    $resultDeclaSe = getResults($results, $tech, "Senior", "Technicien - Manager", "Declaratif");
                    if ($resultDeclaSe) {
                        $resultsDeclaScoreSeF[] = $resultDeclaSe['score'];
                        $resultsDeclaTotalSeF[] = $resultDeclaSe['total'];
                    }
                }

                // Expert
                $alloFacEx = getAllocation($allocations, $tech, 'Expert', 'Factuel');
                $alloDeclaEx = getAllocation($allocations, $tech, 'Expert', 'Declaratif', true);
                
                if ($alloFacEx && $alloDeclaEx) {
                    $resultFacEx = getResults($results, $tech, "Expert", 'Technicien', "Factuel");
                    if ($resultFacEx) {
                        $resultsFacScoreExF[] = $resultFacEx['score'];
                        $resultsFacTotalExF[] = $resultFacEx['total'];
                    }
                    
                    $resultDeclaEx = getResults($results, $tech, "Expert", "Technicien - Manager", "Declaratif");
                    if ($resultDeclaEx) {
                        $resultsDeclaScoreExF[] = $resultDeclaEx['score'];
                        $resultsDeclaTotalExF[] = $resultDeclaEx['total'];
                    }
                }

                if ($alloFacEx && $alloDeclaEx) {
                    $doneTestExTxF[] = $tech;
                }
            }

            $avgFacJu = calculateAverage($resultsFacScoreJuF, $resultsFacTotalJuF);
            $avgDeclaJu = calculateAverage($resultsDeclaScoreJuF, $resultsDeclaTotalJuF);
            $avgFacSe = calculateAverage($resultsFacScoreSeF, $resultsFacTotalSeF);
            $avgDeclaSe = calculateAverage($resultsDeclaScoreSeF, $resultsDeclaTotalSeF);
            $avgFacEx = calculateAverage($resultsFacScoreExF, $resultsFacTotalExF);
            $avgDeclaEx = calculateAverage($resultsDeclaScoreExF, $resultsDeclaTotalExF);

            $percentageFacJuF = $avgFacJu;
            $percentageDeclaJuF = $avgDeclaJu;
            $percentageFacSeF = $avgFacSe;
            $percentageDeclaSeF = $avgDeclaSe;
            $percentageFacExF = $avgFacEx;
            $percentageDeclaExF = $avgDeclaEx;
        
            $resultsFacScoreJuTjF = [];
            $resultsDeclaScoreJuTjF = [];
            $resultsFacTotalJuTjF = [];
            $resultsDeclaTotalJuTjF = [];
            $doneTestJuTjF = [];

            foreach ($techniciansJu as $tech) {
                $alloFacJuTj = getAllocation($allocations, $tech, 'Junior', 'Factuel');
                $alloDeclaJuTj = getAllocation($allocations, $tech, 'Junior', 'Declaratif', true);
                
                if ($alloFacJuTj && $alloDeclaJuTj && $alloFacJuTj['active'] && $alloDeclaJuTj['active'] && $alloDeclaJuTj['activeManager']) {
                    $doneTestJuTjF[] = $tech;
                }
                
                if ($alloFacJuTj && $alloDeclaJuTj) {
                    $resultFacJuTj = getResults($results, $tech, "Junior", 'Technicien', "Factuel");
                    if ($resultFacJuTj) {
                        $resultsFacScoreJuTjF[] = $resultFacJuTj['score'];
                        $resultsFacTotalJuTjF[] = $resultFacJuTj['total'];
                    }
                    
                    $resultDeclaJuTj = getResults($results, $tech, "Junior", "Technicien - Manager", "Declaratif");
                    if ($resultDeclaJuTj) {
                        $resultsDeclaScoreJuTjF[] = $resultDeclaJuTj['score'];
                        $resultsDeclaTotalJuTjF[] = $resultDeclaJuTj['total'];
                    }
                }
            }

            $percentageFacJuTjF = calculateAverage($resultsFacScoreJuTjF, $resultsFacTotalJuTjF);
            $percentageDeclaJuTjF = calculateAverage($resultsDeclaScoreJuTjF, $resultsDeclaTotalJuTjF);
            
            $resultsFacScoreJuTsF = [];
            $resultsDeclaScoreJuTsF = [];
            $resultsFacScoreSeTsF = [];
            $resultsDeclaScoreSeTsF = [];
            $resultsFacTotalJuTsF = [];
            $resultsDeclaTotalJuTsF = [];
            $resultsFacTotalSeTsF = [];
            $resultsDeclaTotalSeTsF = [];
            $doneTestSeTsF = [];

            foreach ($techniciansSe as $tech) {
                // Junior Allocations
                $alloFacJuTs = getAllocation($allocations, $tech, 'Junior', 'Factuel');
                $alloDeclaJuTs = getAllocation($allocations, $tech, 'Junior', 'Declaratif', true);
                
                // Senior Allocations
                $alloFacSeTs = getAllocation($allocations, $tech, 'Senior', 'Factuel');
                $alloDeclaSeTs = getAllocation($allocations, $tech, 'Senior', 'Declaratif', true);
                
                if ($alloFacSeTs && $alloDeclaSeTs && $alloFacSeTs['active'] && $alloDeclaSeTs['active'] && $alloDeclaSeTs['activeManager']) {
                    $doneTestSeTsF[] = $tech;
                }
                
                // Process Junior results
                if ($alloFacJuTs && $alloDeclaJuTs) {
                    $resultFacJuTs = getResults($results, $tech, "Junior", 'Technicien', "Factuel");
                    if ($resultFacJuTs) {
                        $resultsFacScoreJuTsF[] = $resultFacJuTs['score'];
                        $resultsFacTotalJuTsF[] = $resultFacJuTs['total'];
                    }
                    
                    $resultDeclaJuTs = getResults($results, $tech, "Junior", "Technicien - Manager", "Declaratif");
                    if ($resultDeclaJuTs) {
                        $resultsDeclaScoreJuTsF[] = $resultDeclaJuTs['score'];
                        $resultsDeclaTotalJuTsF[] = $resultDeclaJuTs['total'];
                    }
                }

                // Process Senior results
                if ($alloFacSeTs && $alloDeclaSeTs) {
                    $resultFacSeTs = getResults($results, $tech, "Senior", 'Technicien', "Factuel");
                    if ($resultFacSeTs) {
                        $resultsFacScoreSeTsF[] = $resultFacSeTs['score'];
                        $resultsFacTotalSeTsF[] = $resultFacSeTs['total'];
                    }
                    
                    $resultDeclaSeTs = getResults($results, $tech, "Senior", "Technicien - Manager", "Declaratif");
                    if ($resultDeclaSeTs) {
                        $resultsDeclaScoreSeTsF[] = $resultDeclaSeTs['score'];
                        $resultsDeclaTotalSeTsF[] = $resultDeclaSeTs['total'];
                    }
                }
            }

            // Calculate averages
            $percentageFacJuTsF = calculateAverage($resultsFacScoreJuTsF, $resultsFacTotalJuTsF);
            $percentageDeclaJuTsF = calculateAverage($resultsDeclaScoreJuTsF, $resultsDeclaTotalJuTsF);
            $percentageFacSeTsF = calculateAverage($resultsFacScoreSeTsF, $resultsFacTotalSeTsF);
            $percentageDeclaSeTsF = calculateAverage($resultsDeclaScoreSeTsF, $resultsDeclaTotalSeTsF);
            
            $resultsFacScoreJuTxF = [];
            $resultsDeclaScoreJuTxF = [];
            $resultsFacScoreSeTxF = [];
            $resultsDeclaScoreSeTxF = [];
            $resultsFacTotalJuTxF = [];
            $resultsDeclaTotalJuTxF = [];
            $resultsFacTotalSeTxF = [];
            $resultsDeclaTotalSeTxF = [];

            foreach ($techniciansEx as $tech) {
                // Junior Allocations
                $alloFacJuTx = getAllocation($allocations, $tech, 'Junior', 'Factuel');
                $alloDeclaJuTx = getAllocation($allocations, $tech, 'Junior', 'Declaratif', true);
                
                // Senior Allocations
                $alloFacSeTx = getAllocation($allocations, $tech, 'Senior', 'Factuel');
                $alloDeclaSeTx = getAllocation($allocations, $tech, 'Senior', 'Declaratif', true);
                
                // Process Junior results
                if ($alloFacJuTx && $alloDeclaJuTx) {
                    $resultFacJuTx = getResults($results, $tech, "Junior", 'Technicien', "Factuel");
                    if ($resultFacJuTx) {
                        $resultsFacScoreJuTxF[] = $resultFacJuTx['score'];
                        $resultsFacTotalJuTxF[] = $resultFacJuTx['total'];
                    }
                    
                    $resultDeclaJuTx = getResults($results, $tech, "Junior", "Technicien - Manager", "Declaratif");
                    if ($resultDeclaJuTx) {
                        $resultsDeclaScoreJuTxF[] = $resultDeclaJuTx['score'];
                        $resultsDeclaTotalJuTxF[] = $resultDeclaJuTx['total'];
                    }
                }

                // Process Senior results
                if ($alloFacSeTx && $alloDeclaSeTx) {
                    $resultFacSeTx = getResults($results, $tech, "Senior", 'Technicien', "Factuel");
                    if ($resultFacSeTx) {
                        $resultsFacScoreSeTxF[] = $resultFacSeTx['score'];
                        $resultsFacTotalSeTxF[] = $resultFacSeTx['total'];
                    }
                    
                    $resultDeclaSeTx = getResults($results, $tech, "Senior", "Technicien - Manager", "Declaratif");
                    if ($resultDeclaSeTx) {
                        $resultsDeclaScoreSeTxF[] = $resultDeclaSeTx['score'];
                        $resultsDeclaTotalSeTxF[] = $resultDeclaSeTx['total'];
                    }
                }
            }

            // Calculate averages
            $percentageFacJuTxF = calculateAverage($resultsFacScoreJuTxF, $resultsFacTotalJuTxF);
            $percentageDeclaJuTxF = calculateAverage($resultsDeclaScoreJuTxF, $resultsDeclaTotalJuTxF);
            $percentageFacSeTxF = calculateAverage($resultsFacScoreSeTxF, $resultsFacTotalSeTxF);
            $percentageDeclaSeTxF = calculateAverage($resultsDeclaScoreSeTxF, $resultsDeclaTotalSeTxF);
            
            // Réquete pour compter le nombre de QCM Connaissances Tâches Pro Tech et Managers réaliser du groupe
            function checkAllocations($allocations, $user, $level, $type) {
                return $allocations->findOne([
                    "user" => new MongoDB\BSON\ObjectId($user),
                    "level" => $level,
                    "type" => $type,
                ]);
            }

            $testsJu = [];
            $testTotalJu = [];
            $countSavoirsJu = [];
            $countMaSavFaisJu = [];
            $countTechSavFaisJu = [];
            $testsSe = [];
            $testTotalSe = [];
            $countSavoirsSe = [];
            $countMaSavFaisSe = [];
            $countTechSavFaisSe = [];
            $countSavFaisJu = [];
            $countSavFaisSe = [];
            $countSavFaisEx = [];
            $testsEx = [];
            $testTotalEx = [];
            $countSavoirsEx = [];
            $countMaSavFaisEx = [];
            $countTechSavFaisEx = [];
            
            foreach ($techniciansFi as $user) {
                foreach (['Junior' => 'Ju', 'Senior' => 'Se', 'Expert' => 'Ex'] as $level => $levelCode) {
                    $factuel = checkAllocations($allocations, $user, $level, "Factuel");
                    $declaratif = checkAllocations($allocations, $user, $level, "Declaratif");
                
                    // Accumuler les résultats par niveau
                    if (isset($factuel) && $factuel['active'] == true) {
                        ${'countSavoirs'.$levelCode}[] = $factuel;
                    }
                    if (isset($declaratif) && $declaratif['activeManager'] == true) {
                        ${'countMaSavFais'.$levelCode}[] = $declaratif;
                    }
                    if (isset($declaratif) && $declaratif['active'] == true) {
                        ${'countTechSavFais'.$levelCode}[] = $declaratif;
                    }
                    if (isset($declaratif) && $declaratif['active'] == true && $declaratif['activeManager'] == true) {
                        ${'countSavFais'.$levelCode}[] = $declaratif;
                    }
                    if (isset($factuel) && isset($declaratif) && $factuel['active'] == true && $declaratif['active'] == true && $declaratif['activeManager'] == true) {
                        ${'tests'.$levelCode}[] = $user;
                    }
                    if (isset($factuel) && isset($declaratif)) {
                        ${'testTotal'.$levelCode}[] = $user;
                    }
                }
            
            }

            /* ----------  init ---------- */
            $todoCon  = $doneCon  = [];
            $todoTpT  = $doneTpT  = [];
            $todoTpM  = $doneTpM  = [];

            foreach ($techniciansFi as $u) {
                foreach (['Junior'=>'Ju','Senior'=>'Se','Expert'=>'Ex'] as $lvl=>$c) {

                    /*   Connaissances (Factuel / Technicien) */
                    if ($a = checkAllocations($allocations,$u,$lvl,'Factuel')) {
                        $todoCon[$c]  [] = 1;
                        if ($a['active'])                     $doneCon[$c]  [] = 1;
                    }

                    /*   Tâches Pro  – Techniciens  (Déclaratif / active) */
                    if ($a = checkAllocations($allocations,$u,$lvl,'Declaratif')) {
                        $todoTpT[$c]  [] = 1;
                        if ($a['active'])                     $doneTpT[$c]  [] = 1;

                        /*   … Managers  (Déclaratif / activeManager) */
                        if ($a['activeManager']) {
                            $todoTpM[$c] [] = 1;              // même total
                            $doneTpM[$c] [] = 1;
                        } else {
                            $todoTpM[$c] [] = 1;              // pas fait, mais planifié
                        }
                    }
                }
            }

            /* ----------  helper ---------- */
            function pct($done,$todo){ return $todo?round(count($done)*100/count($todo)):0; }

            /* ----------  % par niveau ---------- */
            function buildPct($code){
                global $doneCon,$todoCon,$doneTpT,$todoTpT,$doneTpM,$todoTpM;
                return [
                    pct($doneCon[$code]??[], $todoCon[$code]??[]),
                    pct($doneTpT[$code]??[], $todoTpT[$code]??[]),
                    pct($doneTpM[$code]??[], $todoTpM[$code]??[]),
                ];
            }

            $QCM = [
              'Ju' => buildPct('Ju'),
              'Se' => buildPct('Se'),
              'Ex' => buildPct('Ex'),
              // total = moyenne pondérée sur les techniciens planifiés :
              'To' => [
                  pct(array_merge($doneCon['Ju'] ?? [], $doneCon['Se'] ?? [], $doneCon['Ex'] ?? []),
                      array_merge($todoCon['Ju'] ?? [], $todoCon['Se'] ?? [], $todoCon['Ex'] ?? [])),
                  pct(array_merge($doneTpT['Ju'] ?? [], $doneTpT['Se'] ?? [], $doneTpT['Ex'] ?? []),
                      array_merge($todoTpT['Ju'] ?? [], $todoTpT['Se'] ?? [], $todoTpT['Ex'] ?? [])),
                  pct(array_merge($doneTpM['Ju'] ?? [], $doneTpM['Se'] ?? [], $doneTpM['Ex'] ?? []),
                      array_merge($todoTpM['Ju'] ?? [], $todoTpM['Se'] ?? [], $todoTpM['Ex'] ?? [])),
              ],
            ];
        
            $man = $users->find([
                '$and' => [
                    [
                        "profile" => "Manager",
                        "subsidiary" => $_SESSION["subsidiary"],
                        "active" => true,
                    ],
                ],
            ])->toArray();
            $mgers = count($man);
        }
        
        // Assuming you have the country stored in a session variable
        $country = $_SESSION["country"]; // Set this session variable based on your logic
        // Map countries to their respective agencies
        $agencies = [
            "Burkina Faso" => ["Ouaga"],
            "Cameroun" => ["Bafoussam","Bertoua", "Douala", "Garoua", "Ngaoundere", "Yaoundé"],
            "Cote d'Ivoire" => ["Vridi - Equip"],
            "Gabon" => ["Libreville"],
            "Mali" => ["Bamako"],
            "RCA" => ["Bangui"],
            "RDC" => ["Kinshasa", "Kolwezi", "Lubumbashi"],
            "Senegal" => ["Dakar"],
            // Add more countries and their agencies here
        ];
        
        // Retrieve the selected subsidiary from session
        $selectedSubsidiary = isset($_SESSION['country']) ? $_SESSION['country'] : '';
        
        // Get the agencies for the selected subsidiary
        $agencyList = isset($agencies[$selectedSubsidiary]) ? $agencies[$selectedSubsidiary] : [];
        
        // Convert the agency list to JSON for JavaScript
        $agencyListJson = json_encode($agencyList);
        
        // Récupérer les paramètres depuis l'URL
        $selectedCountry = isset($_GET['country']) ? $_GET['country'] : null;
        $selectedAgency = isset($_GET['agency']) ? $_GET['agency'] : null;


/* ------------------------------------------------------------------ */

          
        // Récupérer les agences du pays sélectionné si le profil est Directeur de Filiale ou Super Admin
        if ($_SESSION['profile'] == 'Directeur des Opérations' || $_SESSION['profile'] == 'Directeur Pièce et Service' || $_SESSION['profile'] == 'Super Admin') {
            // Si aucun pays n'est sélectionné, utiliser le pays de l'utilisateur
            if (!$selectedCountry) {
                $selectedCountry = $_SESSION['country'];
            }

            // Récupérer les agences du pays sélectionné
            $agencies = $academy->agencies->find([
                'country' => $selectedCountry
            ])->toArray();

            
        }
        // Récupérer les résultats validés pour chaque niveau
        $junior = 'Junior';
        $senior = 'Senior';
        $expert = 'Expert';
        $total = 'Total';

 
      

        // Récupérer les techniciens pour chaque niveau
        $techniciansJunior = filterUsersByProfile($academy, $_SESSION['profile'], $selectedCountry, 'Junior', $selectedAgency);
        $numberOfTechniciansJunior = count($techniciansJunior);

        $techniciansSenior = filterUsersByProfile($academy, $_SESSION['profile'], $selectedCountry, 'Senior', $selectedAgency);
        $numberOfTechniciansSenior = count($techniciansSenior);

        $techniciansExpert = filterUsersByProfile($academy, $_SESSION['profile'], $selectedCountry, 'Expert', $selectedAgency);
        $numberOfTechniciansExpert = count($techniciansExpert);

        // Calculer le nombre total de techniciens uniques
        $allTechnicians = array_merge($techniciansJunior, $techniciansSenior, $techniciansExpert);

        // Extraire les IDs des techniciens
        $technicianIds = array();
        foreach ($allTechnicians as $technician) {
            $technicianIds[] = (string)$technician['_id'];
        }

        // Obtenir les IDs uniques
        $uniqueTechnicianIds = array_unique($technicianIds);
        $numberOfTechniciansTotal = count($uniqueTechnicianIds);


        // Récupérer les paramètres depuis l'URL
        $selectedLevel = isset($_GET['level']) ? $_GET['level'] : 'Junior';
        $selectedCountry = isset($_GET['country']) ? $_GET['country'] : null;
        $selectedAgency = isset($_GET['agency']) ? $_GET['agency'] : null;
        $selectedUser = isset($_GET['user']) ? $_GET['user'] : null;
    
    
        // Récupérer le profil utilisateur de la session
        $profile = $_SESSION['profile'];
        $userCountry = isset($_SESSION['country']) ? $_SESSION['country'] : null;
    
        // Filtrer pour obtenir uniquement les techniciens selon le profil de l'utilisateur
        $technicians = filterUsersByProfile($academy, $profile, $selectedCountry, $selectedLevel, $selectedAgency);
        $techniciansF = filterUsersByProfile($academy, $profile, $selectedCountry, $selectedLevel, $selectedAgency);
    
    
        $agencies = [
            "Burkina Faso" => ["Ouaga"],
            "Cameroun" => ["Bafoussam","Bertoua", "Douala", "Garoua", "Ngaoundere", "Yaoundé"],
            "Cote d'Ivoire" => ["Vridi - Equip"],
            "Gabon" => ["Libreville"],
            "Mali" => ["Bamako"],
            "RCA" => ["Bangui"],
            "RDC" => ["Kinshasa", "Kolwezi", "Lubumbashi"],
            "Senegal" => ["Dakar"],
            // Ajoutez d'autres pays et agences ici
        ];
        $countries = array_keys($agencies);  // Extraction des pays
    
        $niveaus = ['Junior', 'Senior', 'Expert'];
    
        // Initialiser les tableaux pour stocker les informations
        $technicianPercentagesByLevel = [];
        $technicianCountsByLevel = [];
        $totalTechniciansByLevel = [];
    
        $technicianPercentagesByLevelF = [];
        $technicianCountsByLevelF = [];
        $totalTechniciansByLevelF = [];

 

        // Passer les données au JavaScript
        
        ?>
    <?php include "./partials/header.php"; ?>
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
            text-align: center;
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
            width: 100%;
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
    <?php openBackgroundContainer(); ?>
        <?php if ($_SESSION["profile"] == "Admin" || $_SESSION["profile"] == "Ressource Humaine" || $_SESSION["profile"] == "Directeur Pièce et Service" || $_SESSION["profile"] == "Directeur des Opérations" || $_SESSION["profile"] == "Directeur Général" || $_SESSION["profile"] == "Directeur Groupe") { ?>
            <!--begin::Title-->
            <div class="card mb-5">
                <div class="card-body py-3">
                    <h1 class="text-dark fw-bolder my-1 fs-1">
                        <?php echo $tableau ?>
                    </h1>
                </div>
            </div>
            <!--end::Title-->
            <!--begin::Post-->
            <div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
                <!--begin::Container-->
                <div class=" container-xxl ">
                    <!--end::Layout Builder Notice-->
                    <!--begin::Row-->
                    <div class="row g-6 g-xl-9 mb-6 mb-xl-9">

                        <?php if ( $_SESSION["profile"] == "Admin" || $_SESSION["profile"] == "Ressource Humaine" || $_SESSION["profile"] == "Directeur Pièce et Service" || $_SESSION["profile"] == "Directeur des Opérations" || $_SESSION["profile"] == "Directeur Général") { ?>
                           <!--begin::Col-->


                            <!-- Ligne des 4 cards -->
                            <div class="row row-cols-xxl-4 row-cols-lg-4 row-cols-md-2 g-6 mb-0">
                            <!-- 1) JUNIOR -->
                            <div class="col">
                                <div class="card h-auto">
                                <div class="card-body text-center py-3 px-3">
                                    <div class="fs-2x fw-bold text-gray-800"
                                        data-kt-countup="true"
                                        data-kt-countup-value="<?= count($techniciansJu) ?>">
                                    </div>
                                    <div class="fw-semibold mt-2">
                                    <?= $technicienss.' '.$Level.' '.$junior ?>
                                    </div>
                                </div>
                                </div>
                            </div>
                            <!-- 2) SENIOR -->
                            <div class="col">
                                <div class="card h-auto">
                                <div class="card-body text-center py-3 px-3">
                                    <div class="fs-2x fw-bold text-gray-800"
                                        data-kt-countup="true"
                                        data-kt-countup-value="<?= count($techniciansSe) ?>">
                                    </div>
                                    <div class="fw-semibold mt-2">
                                    <?= $technicienss.' '.$Level.' '.$senior ?>
                                    </div>
                                </div>
                                </div>
                            </div>
                            <!-- 3) EXPERT -->
                            <div class="col">
                                <div class="card h-auto">
                                <div class="card-body text-center py-3 px-3">
                                    <div class="fs-2x fw-bold text-gray-800"
                                        data-kt-countup="true"
                                        data-kt-countup-value="<?= count($techniciansEx) ?>">
                                    </div>
                                    <div class="fw-semibold mt-2">
                                    <?= $technicienss.' '.$Level.' '.$expert ?>
                                    </div>
                                </div>
                                </div>
                            </div>
                            <!-- 4) FILIALES -->
                            <div class="col">
                                <div class="card h-auto">
                                <div class="card-body text-center py-3 px-3">
                                    <div class="fs-2x fw-bold text-gray-800"
                                        data-kt-countup="true"
                                        data-kt-countup-value="<?= count($techniciansFi) ?>">
                                    </div>
                                    <div class="fw-semibold mt-2">
                                    <?= $technicienss.' '.$subsidiary ?>
                                    </div>
                                </div>
                                </div>
                            </div>
                            </div>







                            <!--end::Col-->


                            <?php if ($_SESSION['profile'] == 'Directeur des Opérations' || $_SESSION['profile'] == 'Directeur Pièce et Service' || $_SESSION['profile'] == 'Admin' || $_SESSION['profile'] == 'Ressource Humaine') { ?>
                                <!--begin::Title-->
                                <div class="card mb-5">
                                    <div class="card-body py-3">
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
                                
                                <!--begin::Title-->
                                <div class="card mb-5">
                                    <div class="card-body py-3">
                                        <h6 class="text-dark fw-bold my-1 fs-2">
                                            <?php echo $plus_details_tests ?>
                                        </h6>
                                    </div>
                                </div>
                                <!--end::Title-->
                                
                                <!-- Begin::Row -->
                                <div class="row">
                                    <!-- Card 1 -->
                                    <div class="col-xl-3">
                                        <div class="card" style="height: 430px; margin-bottom: 25px; display: flex; flex-direction: column;">
                                            <div class="text-center">
                                                <h5 class="mt-2" style="margin-top: 18px !important;">
                                                    <?= $test_niveau_junior ?></h5>
                                            </div>
                                            <div class="d-flex align-items-center justify-content-center flex-grow-1">
                                                <div id="qcm_junior_filiale" style="height: 90%; width: 90%;"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Card 2 -->
                                    <div class="col-xl-3">
                                        <div class="card" style="height: 430px; margin-bottom: 25px; display: flex; flex-direction: column;">
                                            <div class="text-center">
                                                <h5 class="mt-2" style="margin-top: 18px !important;">
                                                    <?= $test_niveau_senior ?></h5>
                                            </div>
                                            <div class="d-flex align-items-center justify-content-center flex-grow-1">
                                                <div id="qcm_senior_filiale" style="height: 90%; width: 90%;"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Card 3 -->
                                    <div class="col-xl-3">
                                        <div class="card" style="height: 430px; margin-bottom: 25px; display: flex; flex-direction: column;">
                                            <div class="text-center">
                                                <h5 class="mt-2" style="margin-top: 18px !important;">
                                                    <?= $test_niveau_expert ?></h5>
                                            </div>
                                            <div class="d-flex align-items-center justify-content-center flex-grow-1">
                                                <div id="qcm_expert_filiale" style="height: 90%; width: 90%;"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Card 4 -->
                                    <div class="col-xl-3">
                                        <div class="card" style="height: 430px; margin-bottom: 25px; display: flex; flex-direction: column;">
                                            <div class="text-center">
                                                <h5 class="mt-2" style="margin-top: 18px !important;">
                                                    <?= $total_03_niveaux ?></h5>
                                            </div>
                                            <div class="d-flex align-items-center justify-content-center flex-grow-1">
                                                <div id="qcm_total_filiale" style="height: 90%; width: 90%;"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- End::Row -->
                                
                                <!-- Legend card spanning full width -->
                                <div class="row">
                                    <div class="col-12">
                                        <div class="card p-3 text-center" style="background-color: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2); margin-bottom: 25px;">
                                            <div style="color: black; font-weight: 500;"><?php echo $qcm_legend ?></div>
                                        </div>
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
          <?php closeBackgroundContainer(); ?>
    <!--end::Content-->
        <?php
    }
    ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/apexcharts/3.50.0/apexcharts.min.js"
    integrity="sha512-h3DSSmgtvmOo5gm3pA/YcDNxtlAZORKVNAcMQhFi3JJgY41j9G06WsepipL7+l38tn9Awc5wgMzJGrUWaeUEGA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
      const qcmPct = <?=json_encode($QCM)?>;   // {Ju:[…],Se:[…],Ex:[…],To:[…]}
      
      // Transform QCM data for charts
      const dataQcm = {
          Junior: qcmPct.Ju || [0, 0, 0],
          Senior: qcmPct.Se || [0, 0, 0],
          Expert: qcmPct.Ex || [0, 0, 0],
          Total: qcmPct.To || [0, 0, 0]
      };
        function applyBrandFilter() {
    const brand = document.getElementById('brand-select').value;
    const url   = new URL(window.location);
    brand ? url.searchParams.set('brand', brand)
          : url.searchParams.delete('brand');
    window.location = url.toString();
}


        $(document).ready(function() {
            $('.dropdown-toggle1').click(function() {
                var $dropdownContent = $('.dropdown-content1');
                var isVisible = $dropdownContent.is(':visible');

                $dropdownContent.slideToggle();
                $(this).toggleClass('open', !isVisible);
            });
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
                        data: [statJu.nonMaitrise, statJu.singleMaitrise, statJu.doubleMaitrise, statJu.othersCount], // Test réalisés vs. Test to be completed
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
                        data: [statSe.nonMaitrise, statSe.singleMaitrise, statSe.doubleMaitrise, statSe.othersCount], // Test réalisés vs. Test to be completed
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
                        data: [statEx.nonMaitrise, statEx.singleMaitrise, statEx.doubleMaitrise, statEx.othersCount], // Test réalisés vs. Test to be completed
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
                        data: [statTo.nonMaitrise, statTo.singleMaitrise, statTo.doubleMaitrise, statTo.othersCount], // Test réalisés vs. Test to be completed
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
                                    <h5 class="mb-4">${data.title}</h5>
                                    <div style="position: relative; display: flex; justify-content: center; align-items: center;">
                                        <canvas id="doughnutChart${index}" width="200" height="200"></canvas>
                                        <div style="position: absolute; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                            <span class="fs-2hx fw-bold text-dark">${data.percentage[3]}%</span>
                                        </div>
                                    </div>
                                    <h6 class="mt-4 text-gray-600"><?php echo $nombres_taches_professionnelles ?>: ${data.total}</h6>
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
                                                $('#questionsModalLabel').text('<?php echo $listes_taches_professionnelles ?>');
                                                
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
                                    display: false
                                },
                                datalabels: {
                                    display: false
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
                        title: '<?php echo $test_niveau_junior ?>',
                        total: <?php echo count($testsTotalJu) ?>,
                        completed: <?php echo count($testsUserJu) ?>, // Test réalisés
                        data: [<?php echo count($testsUserJu) ?>,
                            <?php echo (count($testsTotalJu) - count($testsUserJu)) ?>
                        ], // Test réalisés vs. Test to be completed
                        labels: ['<?php echo $tests_completes ?>', '<?php echo $tests_restants_completer ?>'],
                        backgroundColor: ['#4303ec', '#D3D3D3'] // Blue and Lightgrey
                    },
                    {
                        title: '<?php echo $test_niveau_senior ?>',
                        total: <?php echo count($testsTotalSe) ?>,
                        completed: <?php echo count($testsUserSe) ?>, // Test réalisés
                        data: [<?php echo count($testsUserSe) ?>,
                            <?php echo (count($testsTotalSe) - count($testsUserSe)) ?>
                        ], // Test réalisés vs. Test to be completed
                        labels: ['<?php echo $tests_completes ?>', '<?php echo $tests_restants_completer ?>'],
                        backgroundColor: ['#4303ec', '#D3D3D3'] // Blue and Lightgrey
                    },
                    {
                        title: '<?php echo $test_niveau_expert ?>',
                        total: <?php echo count($testsTotalEx) ?>,
                        completed: <?php echo count($testsUserEx) ?>, // Test réalisés
                        data: [<?php echo count($testsUserEx) ?>,
                            <?php echo (count($testsTotalEx) - count($testsUserEx)) ?>
                        ], // Test réalisés vs. Test to be completed
                        labels: ['<?php echo $tests_completes ?>', '<?php echo $tests_restants_completer ?>'],
                        backgroundColor: ['#4303ec', '#D3D3D3'] // Blue and Lightgrey
                    },
                    {
                        title: '<?php echo $total_03_niveaux ?>',
                        total: <?php echo count($testsTotalJu) + count($testsTotalSe) + count($testsTotalEx) ?>,
                        completed: <?php echo count($testsUserJu) + count($testsUserSe) + count($testsUserEx) ?>, // Test réalisés
                        data: [<?php echo count($testsUserJu) + count($testsUserSe) + count($testsUserEx) ?>,
                            <?php echo (count($testsTotalJu) + count($testsTotalSe) + count($testsTotalEx)) - (count($testsUserJu) + count($testsUserSe) + count($testsUserEx)) ?>
                        ], // Test réalisés vs. Test to be completed
                        labels: ['<?php echo $tests_completes ?>', '<?php echo $tests_restants_completer ?>'],
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
                                    <h5 class="mb-4">${data.title}</h5>
                                    <div style="position: relative; display: flex; justify-content: center; align-items: center;">
                                        <canvas id="doughnutChart${index}" width="200" height="200"></canvas>
                                        <div style="position: absolute; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                            <span class="fs-2hx fw-bold text-dark">${completedPercentage}%</span>
                                        </div>
                                    </div>
                                    <h6 class="mt-4 text-gray-600"><?php echo $total_tests_realiser ?>: ${data.total}</h6>
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
                                    display: false
                                },
                                datalabels: {
                                    display: false
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
                    if (percentage === 0) return ['#d3d3d3']; // Gray circle when 0% instead of white
                    return [
                        getColorForCompletion(percentage), // Color for the completed part
                        '#DCDCDC' // Grey color for the remaining part
                    ];
                };

                // Data for each chart
                const chartDataM = [{
                        title: '<?php echo $resultat_techniciens_junior ?> <?php echo count($doneTestJuTj) ?> / <?php echo count($countUsersJu) ?>',
                        total: 100,
                        completed: <?php echo round(($percentageFacJuTj + $percentageDeclaJuTj) / 2)?>, // Moyenne acquired skills
                        data: [<?php echo round(($percentageFacJuTj + $percentageDeclaJuTj) / 2)?>,
                            100 -
                            <?php echo round(($percentageFacJuTj + $percentageDeclaJuTj) / 2)?>
                        ], // Moyenne acquired skills vs. Moyenne skills to be acquired
                        labels: [
                            '<?php echo round(($percentageFacJuTj + $percentageDeclaJuTj) / 2)?>%  <?php echo$competences_acquises ?>',
                            '<?php echo 100 - round(($percentageFacJuTj + $percentageDeclaJuTj) / 2)?><?php echo $competences_acquerir ?>'
                        ],
                        backgroundColor: getBackgroundColor(<?php echo round(($percentageFacJuTj + $percentageDeclaJuTj) / 2) ?>)
                    },
                    {
                        title: '<?php echo $resultat_techniciens_senior ?> <?php echo count($doneTestSeTs) ?> / <?php echo count($countUsersSe) ?>',
                        total: 100,
                        completed: <?php echo round(($percentageFacSeTs + $percentageDeclaSeTs) / 2)?>, // Moyenne des Acquired skills
                        data: [<?php echo round(($percentageFacSeTs + $percentageDeclaSeTs) / 2)?>,
                            100 -
                            <?php echo round(($percentageFacSeTs + $percentageDeclaSeTs) / 2)?>
                        ], // Moyenne acquired skills vs. Moyenne skills to be acquired
                        labels: [
                            '<?php echo round(($percentageFacSeTs + $percentageDeclaSeTs) / 2)?>% acquired skills',
                            '<?php echo 100 - round(($percentageFacSeTs + $percentageDeclaSeTs) / 2)?><?php echo $competences_acquerir ?>'
                        ],
                        backgroundColor: getBackgroundColor(<?php echo round(($percentageFacSeTs + $percentageDeclaSeTs) / 2) ?>)
                    },
                    {
                        title: '<?php echo $resultat_techniciens_expert ?> <?php echo count($doneTestExTx) ?> / <?php echo count($countUsersEx) ?>',
                        total: 100,
                        completed: <?php echo round(($percentageFacEx + $percentageDeclaEx) / 2)?>, // Moyenne acquired skills
                        data: [<?php echo round(($percentageFacEx + $percentageDeclaEx) / 2)?>,
                            100 -
                            <?php echo round(($percentageFacEx + $percentageDeclaEx) / 2)?>
                        ], // Moyenne acquired skills vs. Moyenne skills to be acquired
                        labels: [
                            '<?php echo round(($percentageFacEx + $percentageDeclaEx) / 2)?>% <?php echo$competences_acquises ?>',
                            '<?php echo 100 - round(($percentageFacEx + $percentageDeclaEx) / 2)?><?php echo $competences_acquerir ?>'
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
                    title: '<?php echo $resultat_techniciens_total ?> <?php echo count($doneTestJuTj) + count($doneTestSeTs) + count($doneTestExTx) ?> / <?php echo count($countUsers) ?>',
                    total: 100,
                    completed: averageCompleted,
                    data: averageData,
                    labels: [
                        `${averageCompleted}% <?php echo$competences_acquises ?>`,
                        `${100 - averageCompleted}<?php echo $competences_acquerir ?>`
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
                                    <h5 class="mb-4">${data.title}</h5>
                                    <div style="position: relative; display: flex; justify-content: center; align-items: center;">
                                        <canvas id="doughnutChart${index}" width="200" height="200"></canvas>
                                        <div style="position: absolute; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                            <span class="fs-2hx fw-bold text-dark">${completedPercentage}%</span>
                                        </div>
                                    </div>
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
                                    display: false
                                },
                                datalabels: {
                                    display: false
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(tooltipItem) {
                                            let percentage = Math.round((tooltipItem.raw / 100) * 100);
                                            return `<?php echo$competences_acquises ?>: ${percentage}%`;
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
            var label = ['<?php echo $connaissances ?>', '<?php echo $taches_professionnelles ?>', '<?php echo $competence ?>'];

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
                        name: "<?php echo $taux_realisation_tests ?>",
                        data: data
                    }],
                    xaxis: {
                        categories: labels, // Use city names for x-axis labels
                        labels: {
                            rotate: -90,
                            style: {
                                cssClass: 'apexcharts-xaxis-label'
                            }
                        }
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
            var labelQ = ['<?php echo $qcm_co ?>', '<?php echo $qcm_tp_tech ?>', '<?php echo $qcm_tp_man ?>'];

            function completedPercentage (completed, total) {
                let moyen = Math.round((completed / total) * 100);
        
                return moyen;
            }
            
            // Sample test data for Junior, Senior, and Expert levels
            var juniorScoreQ = [completedPercentage(<?php echo count($countSavoirJu) ?>, <?php echo count($countUsers) ?>), completedPercentage(<?php echo count($countSavFaiJu) ?>, <?php echo count($countUsers) ?>), completedPercentage(<?php echo count($testsUserJu) ?>, <?php echo count($testsTotalJu) ?>)]; // Replace with actual junior data
            var seniorScoreQ = [completedPercentage(<?php echo count($countSavoirSe) ?>, <?php echo count($countUsersSe) + count($countUsersEx) ?>), completedPercentage(<?php echo count($countSavFaiSe) ?>, <?php echo count($countUsersSe) + count($countUsersEx) ?>), completedPercentage(<?php echo count($testsUserSe) ?>, <?php echo count($testsTotalSe) ?>)];  // Replace with actual senior data
            var expertScoreQ = [completedPercentage(<?php echo count($countSavoirEx) ?>, <?php echo count($countUsersEx) ?>), completedPercentage(<?php echo count($countSavFaiEx) ?>, <?php echo count($countUsersEx) ?>), completedPercentage(<?php echo count($testsUserEx) ?>, <?php echo count($testsTotalEx) ?>)]; // Replace with actual expert data
            var averageScoreQ = [completedPercentage(<?php echo count($countSavoirJu) + count($countSavoirSe) + count($countSavoirEx) ?>, <?php echo count($countUsers) + count($countUsersSe)  + (count($countUsersEx) * 2) ?>), completedPercentage(<?php echo count($countSavFaiJu) + count($countSavFaiSe) + count($countSavFaiEx) ?>, <?php echo count($countUsers) + count($countUsersSe)  + (count($countUsersEx) * 2) ?>) ,completedPercentage(<?php echo count($testsUserJu) + count($testsUserSe) + count($testsUserEx) ?>, <?php echo count($testsTotalJu) + count($testsTotalSe) + count($testsTotalEx) ?>)]; // Replace with actual expert data

            // Function to create the chart for a specific data set and container
            function renderChartQ(chartId, data, label) {
                try {
                    var chartContainerQ = document.querySelector("#" + chartId);
                    if (!chartContainerQ) {
                        console.error("Chart container with ID " + chartId + " not found.");
                        return;
                    }
                    
                    // Ensure data is valid
                    if (!Array.isArray(data)) {
                        console.error("Invalid data for chart " + chartId);
                        data = [0, 0, 0]; // Default data
                    }

                    var chartOptionQ = {
                    chart: {
                        type: 'bar',
                        height: 350,
                        width: 300
                    },
                    series: [{
                        name: "<?php echo $taux_realisation_tests ?>",
                        data: data
                    }],
                    xaxis: {
                        categories: label, // Use city names for x-axis labels
                        labels: {
                            rotate: -45,
                            rotateAlways: true,
                            hideOverlappingLabels: false,
                            trim: false,
                            style: {
                                fontSize: '12px',
                                fontWeight: 500,
                                cssClass: 'apexcharts-xaxis-label'
                            }
                        }
                    },
                    plotOptions: {
                        bar: {
                            distributed: true, // Ensure each bar gets a different color
                            horizontal: false,
                            borderRadius: 4 // Rounded bar edges
                        }
                    },
                    colors: ['#82CDFF', '#039FFE', '#4303EC'],
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
                } catch (error) {
                    console.error(`Error in renderChartQ for ${chartId}:`, error);
                }
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
            
            // Graphiques pour les resultats des techniciens par niveau

            // Fixed list of cities for the x-axis labels
            var cityLabelJu = ['<?php echo count($countUsersJu) ?> <?php echo $techniciens_junior ?>', '<?php echo count($countUsersSe) ?> <?php echo $techniciens_senior ?>', '<?php echo count($countUsersEx) ?> <?php echo $techniciens_expert ?>'];
            var cityLabelSe = ['<?php echo count($countUsersSe) ?> <?php echo $techniciens_senior ?>', '<?php echo count($countUsersEx) ?> <?php echo $techniciens_expert ?>', ''];
            var cityLabelEx = ['<?php echo count($countUsersEx) ?> <?php echo $techniciens_expert ?>', '', ''];
            var cityLabels = ['<?php echo $total_niveau_junior ?>', '<?php echo $total_niveau_senior ?>', '<?php echo $total_niveau_expert ?>'];

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
                try {
                    var chartContainers = document.querySelector("#" + chartId);
                    if (!chartContainers) {
                        console.error("Chart container with ID " + chartId + " not found.");
                        return;
                    }

                    // Ensure data is valid
                    if (!Array.isArray(data)) {
                        console.error("Invalid data for chart " + chartId);
                        data = [0, 0, 0]; // Default data
                    }

                    var colors = data.map(value => determineColor(value)); // Apply dynamic colors based on score

                    var chartOptions = {
                    chart: {
                        type: 'bar',
                        height: 350,
                        width: 300
                    },
                    series: [{
                        name: "<?php echo $taux_realisation_tests ?>",
                        data: data
                    }],
                    xaxis: {
                        categories: labels, // Use city names for x-axis labels
                        labels: {
                            rotate: -45,
                            style: {
                                cssClass: 'apexcharts-xaxis-label'
                            }
                        }
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
                } catch (error) {
                    console.error(`Error in renderCharts for ${chartId}:`, error);
                }
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
                        title: '<?php echo $test_niveau_junior ?>',
                        total: <?php echo count($testTotalJu) ?>,
                        completed: <?php echo count($testsJu) ?>, // Test réalisés
                        data: [<?php echo count($testsJu) ?>,
                            <?php echo (count($testTotalJu) - count($testsJu)) ?>
                        ], // Test réalisés vs. Test to be completed
                        labels: ['<?php echo count($testsJu) ?> <?php echo $tests_completes ?>',
                            '<?php echo (count($testTotalJu)) - (count($testsJu)) ?> <?php echo $tests_restants_completer ?>'
                        ],
                        backgroundColor: ['#4303ec', '#D3D3D3'] // Blue and Lightgrey
                    },
                    {
                        title: '<?php echo $test_niveau_senior ?>',
                        total: <?php echo count($testTotalSe) ?>,
                        completed: <?php echo count($testsSe) ?>, // Test réalisés
                        data: [<?php echo count($testsSe) ?>,
                            <?php echo (count($testTotalSe) - count($testsSe)) ?>
                        ], // Test réalisés vs. Test to be completed
                        labels: ['<?php echo count($testsSe) ?> <?php echo $tests_completes ?>',
                            '<?php echo (count($testTotalSe)) - (count($testsSe)) ?> <?php echo $tests_restants_effectuer ?>'
                        ],
                        backgroundColor: ['#4303ec', '#D3D3D3'] // Blue and Lightgrey
                    },
                    {
                        title: '<?php echo $test_niveau_expert ?>',
                        total: <?php echo count($testTotalEx) ?>,
                        completed: <?php echo count($testsEx) ?>, // Test réalisés
                        data: [<?php echo count($testsEx) ?>,
                            <?php echo (count($testTotalEx) - count($testsEx)) ?>
                        ], // Test réalisés vs. Test to be completed
                        labels: ['<?php echo count($testsEx) ?> <?php echo $tests_completes ?>',
                            '<?php echo (count($testTotalEx)) - (count($testsEx)) ?> <?php echo $tests_restants_completer ?>'
                        ],
                        backgroundColor: [
                            <?php echo (count($testsEx) === 0 && count($testTotalEx) === 0) ? "'#D3D3D3'" : "'#4303ec'"; ?>,
                            '#D3D3D3'
                        ] // Ensure gray for 0 tests, blue for completed tests
                    },
                    {
                        title: '<?php echo $total_03_niveaux ?>',
                        total: <?php echo count($testTotalJu) + count($testTotalSe) + count($testTotalEx) ?>,
                        completed: <?php echo count($testsJu) + count($testsSe) + count($testsEx) ?>, // Test réalisés
                        data: [<?php echo count($testsJu) + count($testsSe) + count($testsEx) ?>,
                            <?php echo (count($testTotalJu) + count($testTotalSe) + count($testTotalEx)) - (count($testsJu) + count($testsSe) + count($testsEx)) ?>
                        ], // Test réalisés vs. Test to be completed
                        labels: ['<?php echo count($testsJu) + count($testsSe) + count($testsEx) ?> <?php echo $tests_completes ?>',
                            '<?php echo (count($testTotalJu) + count($testTotalSe) + count($testTotalEx)) - (count($testsJu) + count($testsSe) + count($testsEx)) ?> <?php echo $tests_restants_completer ?>'
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
                                    <h5 class="mb-4">${data.title}</h5>
                                    <div style="position: relative; display: flex; justify-content: center; align-items: center;">
                                        <canvas id="doughnutChart${index}" width="200" height="200"></canvas>
                                        <div style="position: absolute; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                            <span class="fs-2hx fw-bold text-dark">${completedPercentage}%</span>
                                        </div>
                                    </div>
                                    <h6 class="mt-4 text-gray-600"><?php echo $total_tests_realiser ?>: ${data.total}</h6>
                                </div>
                            </div>
                        </div>
                    `;

                    // Append the card to the container
                    container.insertAdjacentHTML('beforeend', cardHtml);

                    // Initialize the Chart.js doughnut chart
                    const ctx = document.getElementById(`doughnutChart${index}`).getContext('2d');
                    
                    // Prépare les valeurs
                    const completed = data.data[0];
                    const total = data.data[0] + data.data[1];
                    const remaining = total - completed;
                    
                    // Définir les données du graphique
                    let chartData;
                    
                    if (total === 0) {
                        // Si pas de données, afficher un cercle gris uniforme
                        chartData = {
                            labels: ['<?php echo $aucun_test_prevu; ?>'],
                            datasets: [{
                                data: [1],
                                backgroundColor: ['#D3D3D3'],
                                borderWidth: 0
                            }]
                        };
                    } else {
                        // Sinon, afficher les données normales
                        chartData = {
                            labels: data.labels,
                            datasets: [{
                                data: [completed, remaining],
                                backgroundColor: ['#4303EC', '#DCDCDC'],
                                borderWidth: 1
                            }]
                        };
                    }

                    // Calculate total outside the chart options to avoid reference error
                    const totalValue = data.data[0] + data.data[1];
                    
                    new Chart(ctx, {
                        type: 'doughnut',
                        data: chartData,
                        options: {
                            cutout: '60%',            // épaisseur du donut
                            responsive: true,
                            plugins: {
                                legend: {
                                    display: false
                                },
                                datalabels: {
                                    display: false
                                },
                                tooltip: {
                                    enabled: totalValue > 0,
                                    callbacks: {
                                        label: function(tooltipItem) {
                                            if (totalValue === 0) {
                                                return '<?php echo $aucun_test_prevu; ?>';
                                            }
                                            
                                            const value = tooltipItem.raw;
                                            const total = tooltipItem.chart.data.datasets[0].data
                                                .reduce((a, b) => a + b, 0);
                                            const percentage = Math.round((value / total) * 100);
                                            return `Nombre: ${value}, Pourcentage: ${percentage}%`;
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
                        title: '<?php echo $resultat_techniciens_junior ?> <?php echo count($doneTestJuTjF) ?> / <?php echo count($techniciansJu) ?>',
                        total: 100,
                        completed: <?php echo round(($percentageFacJuTjF + $percentageDeclaJuTjF) / 2)?>, // Moyenne acquired skills
                        data: [<?php echo round(($percentageFacJuTjF + $percentageDeclaJuTjF) / 2)?>,
                            100 -
                            <?php echo round(($percentageFacJuTjF + $percentageDeclaJuTjF) / 2)?>
                        ], // Moyenne acquired skills vs. Moyenne skills to be acquired
                        labels: [
                            '<?php echo round(($percentageFacJuTjF + $percentageDeclaJuTjF) / 2)?>% <?php echo$competences_acquises ?>',
                            '<?php echo 100 - round(($percentageFacJuTjF + $percentageDeclaJuTjF) / 2)?><?php echo $competences_acquerir ?>'
                        ],
                        backgroundColor: getBackgroundColor(<?php echo round(($percentageFacJuTjF + $percentageDeclaJuTjF) / 2) ?>)
                    },
                    {
                        title: '<?php echo $resultat_techniciens_senior ?> <?php echo count($doneTestSeTsF) ?> / <?php echo count($techniciansSe) ?>',
                        total: 100,
                        completed: <?php echo round(($percentageFacSeTsF + $percentageDeclaSeTsF) / 2)?>, // Moyenne acquired skills
                        data: [<?php echo round(($percentageFacSeTsF + $percentageDeclaSeTsF) / 2)?>,
                            100 -
                            <?php echo round(($percentageFacSeTsF + $percentageDeclaSeTsF) / 2)?>
                        ], // Moyenne acquired skills vs. Moyenne skills to be acquired
                        labels: [
                            '<?php echo round(($percentageFacSeTsF + $percentageDeclaSeTsF) / 2)?>% <?php echo$competences_acquises ?>',
                            '<?php echo 100 - round(($percentageFacSeTsF + $percentageDeclaSeTsF) / 2)?><?php echo $competences_acquerir ?>'
                        ],
                        backgroundColor: getBackgroundColor(<?php echo round(($percentageFacSeTsF + $percentageDeclaSeTsF) / 2) ?>)
                    },
                    {
                        title: '<?php echo $resultat_techniciens_expert ?> <?php echo count($doneTestExTxF) ?> / <?php echo count($techniciansEx) ?>',
                        total: 100,
                        completed: <?php echo round(($percentageFacExF + $percentageDeclaExF) / 2)?>, // Moyenne acquired skills
                        data: [<?php echo round(($percentageFacExF + $percentageDeclaExF) / 2)?>,
                            100 -
                            <?php echo round(($percentageFacExF + $percentageDeclaExF) / 2)?>
                        ], // Moyenne acquired skills vs. Moyenne skills to be acquired
                        labels: [
                            '<?php echo round(($percentageFacExF + $percentageDeclaExF) / 2)?>% <?php echo$competences_acquises ?>',
                            '<?php echo 100 - round(($percentageFacExF + $percentageDeclaExF) / 2)?><?php echo $competences_acquerir ?>'
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
                    title: '<?php echo $resultat_techniciens_total ?> <?php echo count($doneTestJuTjF) + count($doneTestSeTsF) + count($doneTestExTxF) ?> / <?php echo count($techniciansFi) ?>',
                    total: 100,
                    completed: averageCompleted,
                    data: averageData,
                    labels: [
                        `${averageCompleted}% <?php echo$competences_acquises ?>`,
                        `${100 - averageCompleted}<?php echo $competences_acquerir ?>`
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
                                    <h5 class="mb-4">${data.title}</h5>
                                    <div style="position: relative; display: flex; justify-content: center; align-items: center;">
                                        <canvas id="doughnutChart${index}" width="200" height="200"></canvas>
                                        <div style="position: absolute; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                            <span class="fs-2hx fw-bold text-dark">${data.completed}%</span>
                                        </div>
                                    </div>
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
                            cutout: data.completed === 0 ? '0%' : '50%', // Solid circle when 0%, donut when > 0%
                            responsive: true,
                            plugins: {
                                legend: {
                                    display: false
                                },
                                datalabels: {
                                    display: false
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(tooltipItem) {
                                            let percentage = Math.round((tooltipItem.raw / 100) * 100);
                                            return `<?php echo$competences_acquises ?>: ${percentage}%`;
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
                if (percentage === 0) return ['#d3d3d3']; // Gray circle when 0% instead of white
                return [
                    getColorForCompletion(percentage), // Color for the completed part
                    '#DCDCDC' // Grey color for the remaining part
                ];
            };
            // Graphiques pour les resultats des connaissances et tâches professionnelles

            // Fixed list of cities for the x-axis labels
            var cityLabelJu = ['<?php echo count($techniciansJu) ?> <?php echo $techniciens_junior ?>', '<?php echo count($techniciansSe) ?> <?php echo $techniciens_senior ?>', '<?php echo count($techniciansEx) ?> <?php echo $techniciens_expert ?>'];
            var cityLabelSe = ['<?php echo count($techniciansSe) ?> <?php echo $techniciens_senior ?>', '<?php echo count($techniciansEx) ?> <?php echo $techniciens_expert ?>', ''];
            var cityLabelEx = ['<?php echo count($techniciansEx) ?> <?php echo $techniciens_expert ?>', '', ''];
            var cityLabels = ['<?php echo $total_niveau_junior ?>', '<?php echo $total_niveau_senior ?>', '<?php echo $total_niveau_expert ?>'];

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
                try {
                    var chartContainer = document.querySelector("#" + chartId);
                    if (!chartContainer) {
                        console.error("Chart container with ID " + chartId + " not found.");
                        return;
                    }

                    // Ensure data is valid
                    if (!Array.isArray(data)) {
                        console.error("Invalid data for chart " + chartId);
                        data = [0, 0, 0]; // Default data
                    }

                    var colors = data.map(value => determineColor(value)); // Apply dynamic colors based on score

                    var chartOptions = {
                    chart: {
                        type: 'bar',
                        height: 350,
                        width: 300
                    },
                    series: [{
                        name: "<?php echo $taux_realisation_tests ?>",
                        data: data
                    }],
                    xaxis: {
                        categories: labels, // Use city names for x-axis labels
                        labels: {
                            rotate: -45,
                            style: {
                                cssClass: 'apexcharts-xaxis-label'
                            }
                        }
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
                        console.error(`Error rendering chart with ID '${chartId}':`, error);
                    });
                } catch (error) {
                    console.error(`Error in renderChart for ${chartId}:`, error);
                }
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
            var labelQ = ['<?php echo $qcm_co ?>', '<?php echo $qcm_tp_tech ?>', '<?php echo $qcm_tp_man ?>'];
            
            function completedPercentage (completed, total) {
                let moyen = Math.round((completed / total) * 100);
        
                return moyen;
            }
            
            // Sample test data for Junior, Senior, and Expert levels
            var juniorScoreQ = [completedPercentage(<?php echo count($countSavoirsJu) ?>, <?php echo count($techniciansFi) ?>), completedPercentage(<?php echo count($countSavFaisJu) ?>, <?php echo count($techniciansFi) ?>), completedPercentage(<?php echo count($testsJu) ?>, <?php echo count($testTotalJu) ?>)]; // Replace with actual junior data
            var seniorScoreQ = [completedPercentage(<?php echo count($countSavoirsSe) ?>, <?php echo count($techniciansSe) + count($techniciansEx) ?>), completedPercentage(<?php echo count($countSavFaisSe) ?>, <?php echo count($techniciansSe) + count($techniciansEx) ?>), completedPercentage(<?php echo count($testsSe) ?>, <?php echo count($testTotalSe) ?>)];  // Replace with actual senior data
            var expertScoreQ = [completedPercentage(<?php echo count($countSavoirsEx) ?>, <?php echo count($techniciansEx) ?>), completedPercentage(<?php echo count($countSavFaisEx) ?>, <?php echo count($techniciansEx) ?>), completedPercentage(<?php echo count($testsEx) ?>, <?php echo count($testTotalEx) ?>)]; // Replace with actual expert data
            var averageScoreQ = [completedPercentage(<?php echo count($countSavoirsJu) + count($countSavoirsSe) + count($countSavoirsEx) ?>, <?php echo count($techniciansFi) + count($techniciansSe)  + (count($techniciansEx) * 2) ?>), completedPercentage(<?php echo count($countSavFaisJu) + count($countSavFaisSe) + count($countSavFaisEx) ?>, <?php echo count($techniciansFi) + count($techniciansSe)  + (count($techniciansEx) * 2) ?>) ,completedPercentage(<?php echo count($testsJu) + count($testsSe) + count($testsEx) ?>, <?php echo count($testTotalJu) + count($testTotalSe) + count($testTotalEx) ?>)]; // Replace with actual expert data
                    
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
                        name: "<?php echo $taux_realisation_tests ?>",
                        data: data
                    }],
                    xaxis: {
                        categories: label, // Use city names for x-axis labels
                        labels: {
                            rotate: -45,
                            rotateAlways: true,
                            hideOverlappingLabels: false,
                            trim: false,
                            style: {
                                fontSize: '12px',
                                fontWeight: 500,
                                cssClass: 'apexcharts-xaxis-label'
                            }
                        }
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
                try {
                    renderChartQ('qcm_junior_filiale', dataQcm.Junior, labelQ);
                    renderChartQ('qcm_senior_filiale', dataQcm.Senior, labelQ);
                    renderChartQ('qcm_expert_filiale', dataQcm.Expert, labelQ);
                    renderChartQ('qcm_total_filiale', dataQcm.Total, labelQ);
                } catch (error) {
                    console.error("Error initializing charts:", error);
                }
            }
        
            // Ensure the charts are initialized after the DOM fully loads
            document.addEventListener('DOMContentLoaded', function() {
                initialiseChart();
            });
            
            // Graphiques pour les resultats des connaissances et tâches professionnelles

            // Fixed list of cities for the x-axis labels
            var label = ['<?php echo $connaissances ?>', '<?php echo $taches_professionnelles ?>', '<?php echo $competence ?>'];

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
                        name: "<?php echo $taux_realisation_tests ?>",
                        data: data
                    }],
                    xaxis: {
                        categories: labels, // Use city names for x-axis labels
                        labels: {
                            rotate: -45,
                            rotateAlways: true,
                            hideOverlappingLabels: false,
                            trim: false,
                            style: {
                                fontSize: '12px',
                                fontWeight: 500,
                                cssClass: 'apexcharts-xaxis-label'
                            }
                        }
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
                try {
                    // Check if elements exist before rendering
                    if (document.getElementById('result_junior_filiale')) {
                        renderChart('result_junior_filiale', juniorScore, label);
                    }
                    if (document.getElementById('result_senior_filiale')) {
                        renderChart('result_senior_filiale', seniorScore, label);
                    }
                    if (document.getElementById('result_expert_filiale')) {
                        renderChart('result_expert_filiale', expertScore, label);
                    }
                    if (document.getElementById('result_total_filiale')) {
                        renderChart('result_total_filiale', averageScore, label);
                    }
                } catch (error) {
                    console.error("Error initializing charts:", error);
                }
            }

            // Ensure the charts are initialized after the DOM fully loads
            document.addEventListener('DOMContentLoaded', function() {
                initializeChart();
            });
        </script>
    <?php } ?>

<?php include "./partials/footer.php"; ?>