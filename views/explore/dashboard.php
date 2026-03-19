<?php
session_start();
include_once "../language.php";
include_once "getValidatedResults.php";
include_once "score_decla.php"; // Inclusion du fichier pour les scores déclaratifs
include_once "score_fact.php";  // Inclusion du fichier pour les scores factuels
include_once "../userFilters.php"; // Inclusion du fichier contenant les fonctions de filtrage
include_once "../partials/background-manager.php"; // Include background manager
setPageBackground("bg-dashboard"); // Set the dashboard background - bg-default does not exist

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
                    ['profile' => 'Technicien'],
                    ['profile' => 'Manager', 'test' => true],
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
                            ['$eq' => ['$level', 'Junior']],
                            1,
                            0,
                        ],
                    ],
                ],
                'SeniorTotal' => [
                    '$sum' => [
                        '$cond' => [
                            ['$eq' => ['$level', 'Senior']],
                            1,
                            0,
                        ],
                    ],
                ],
                'ExpertTotal' => [
                    '$sum' => [
                        '$cond' => [
                            ['$eq' => ['$level', 'Expert']],
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
                                    ['$eq' => ['$level', 'Junior']],
                                    ['$in' => ['TOYOTA', '$brandJunior']],
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
                                    ['$eq' => ['$level', 'Senior']],
                                    ['$in' => ['TOYOTA', '$brandSenior']],
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
                                    ['$eq' => ['$level', 'Senior']],
                                    ['$in' => ['TOYOTA', '$brandJunior']],
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
                                    ['$eq' => ['$level', 'Expert']],
                                    ['$in' => ['TOYOTA', '$brandExpert']],
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
                                    ['$eq' => ['$level', 'Expert']],
                                    ['$in' => ['TOYOTA', '$brandSenior']],
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
                                    ['$eq' => ['$level', 'Expert']],
                                    ['$in' => ['TOYOTA', '$brandJunior']],
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

    $juniorToyota = $stats['Junior_Toyota_brandJunior']   ?? 0;
    $seniorToyota = $stats['Senior_Toyota_brandSenior']   ?? 0;
    $expertToyota = $stats['Expert_Toyota_brandExpert']   ?? 0;

    if ($_SESSION['profile'] == 'Directeur Groupe' || $_SESSION['profile'] == 'Super Admin') {
        // Compter les utilisateurs en ligne
        $countOnlineUser  = $connections->find([
            "status" => "Online",
            "active" => true,
        ])->toArray();
        $countOnlineUsers = count($countOnlineUser);

        function getUsersByLevel($users, $level = null, $brand = '')
        {
            $query = [
                'active' => true,
                '$or' => [
                    ['profile' => 'Technicien'],
                    ['profile' => 'Manager', 'test' => true]
                ]
            ];

            if ($level !== null) {
                $query['level'] = $level;
            }
            /* ─── FILTRE MARQUE ───────────────────────────────────────────── */
            if ($brand !== '') {

                // on veut lier chaque « brandXXX » au niveau correspondant
                switch ($level) {
                    case 'Junior':
                        $query['brandJunior'] = ['$in' => [$brand]];
                        break;

                    case 'Senior':
                        $query['brandSenior'] = ['$in' => [$brand]];
                        break;

                    case 'Expert':
                        $query['brandExpert'] = ['$in' => [$brand]];
                        break;

                    default:   // (total ou niveau non précisé) → au moins dans un des trois tableaux
                        $query['$or'][] = ['brandJunior' => ['$in' => [$brand]]];
                        $query['$or'][] = ['brandSenior' => ['$in' => [$brand]]];
                        $query['$or'][] = ['brandExpert' => ['$in' => [$brand]]];
                }
            }
            /* ─────────────────────────────────────────────────────────────── */
            $cursor = $users->find($query, ['projection' => ['_id' => 1]]);
            $countUsers = [];

            foreach ($cursor as $doc) {
                $countUsers[] = $doc->_id;
            }

            return $countUsers;
        }


        // Compter les utilisateurs par niveau
        $countUsersJu = getUsersByLevel($users, 'Junior',  $selectedBrand);
        $countUsersSe = getUsersByLevel($users, 'Senior',  $selectedBrand);
        $countUsersEx = getUsersByLevel($users, 'Expert',  $selectedBrand);
        $countUsers   = getUsersByLevel($users,  null,     $selectedBrand); // total



        $subsidiariesNames = [
            "CAMEROON MOTORS INDUSTRIES" => "Ca",
            "CFAO MOTORS GABON" => "Ga",
            "CFAO MOTORS CONGO" => "Co",
            "CFAO MOTORS BENIN" => "Be",
            "CFAO MOTORS BURKINA" => "Bu",
            "CFAO MOTORS COTE D'IVOIRE" => "Rci",
            "CFAO (GAMBIA) LIMITED" => "Gam",
            "CFAO MOTORS GUINEE BISSAU" => "Bi",
            "CFAO MOTORS GUINEE" => "Gu",
            "CFAO MOTORS GUINEA EQUATORIAL" => "Eq",
            "CFAO MOTORS MALI" => "Mali",
            "CFAO MOTORS MADAGASCAR" => "Mad",
            "CFAO MOTORS NIGER" => "Niger",
            "CFAO MOTORS CENTRAFRIQUE" => "Rca",
            "CFAO MOTORS RDC" => "Rdc",
            "CFAO MOTORS SENEGAL" => "Sen",
            "CFAO MOTORS TCHAD" => "Tc",
            "CFAO MOTORS TOGO" => "To",
            "CFAO MOTORS GHANA" => "Gh",
            "CFAO MOTORS NIGERIA" => "Nigeria",
            "COMPAGNIE MAURITANIENNE DE DISTRIBUTION AUTOMOBILE" => "Mau"
        ];

        // Init all technician arrays dynamically
        foreach ($subsidiariesNames as $label => $suffix) {
            ${"technicians$suffix"} = [];
        }

        // Create reference map
        $subsidiaries = [];
        foreach ($subsidiariesNames as $label => $suffix) {
            $subsidiaries[$label] = &${"technicians$suffix"};
        }


        // Effectuer une seule requête pour récupérer tous les techniciens et managers actifs dans les filiales spécifiées
        $techniciansAndManagers = $users->find([
            '$and' => [
                ['subsidiary' => ['$in' => array_keys($subsidiaries)]],
                ['active' => true],
                ['$or' => [
                    ['profile' => 'Technicien'],
                    ['profile' => 'Manager', 'test' => true]
                ]]
            ]
        ])->toArray();

        // Parcourir les techniciens et managers récupérés et les affecter aux bonnes filiales
        foreach ($techniciansAndManagers as $technician) {
            $subsidiary = $technician['subsidiary'];
            if (isset($subsidiaries[$subsidiary])) {
                array_push($subsidiaries[$subsidiary], new MongoDB\BSON\ObjectId($technician['_id']));
            }
        }

        // Récuperer les tests de chaque niveaux
        function checkAllocation($allocations, $technician, $level)
        {
            $factuel = $allocations->findOne([
                "user" => new MongoDB\BSON\ObjectId($technician),
                "level" => $level,
                "type" => "Factuel",
                "active" => true,
            ]);

            $declaratif = $allocations->findOne([
                "user" => new MongoDB\BSON\ObjectId($technician),
                "level" => $level,
                "type" => "Declaratif",
                "activeManager" => true,
                "active" => true,
            ]);

            return isset($factuel) && isset($declaratif);
        }

        $testJu = [];
        $testSe = [];
        $testEx = [];

        foreach ($countUsers as $technician) {
            if (checkAllocation($allocations, $technician, "Junior")) {
                $testJu[] = $technician;
            }
            if (checkAllocation($allocations, $technician, "Senior")) {
                $testSe[] = $technician;
            }
            if (checkAllocation($allocations, $technician, "Expert")) {
                $testEx[] = $technician;
            }
        }

        // Récuperer les manageurs du groupe
        function countUsersByProfile($users, $profile)
        {
            $result = $users->find([
                "profile" => $profile,
                "active" => true,
            ])->toArray();

            return count($result);
        }

        // Compter les différents profils
        $countManagers = countUsersByProfile($users, "Manager");
        $countAdmins = countUsersByProfile($users, "Admin");
        $countDPS = countUsersByProfile($users, "Directeur Pièce et Service");
        $countDOP = countUsersByProfile($users, "Directeur des Opérations");

        $countDirecteurFiliales = $countDOP + $countDPS;
        $countDirecteurGroupes = countUsersByProfile($users, "Directeur Groupe");

        // Récuperer les vehicules du groupe
        $countVehicle = $vehicles->find()->toArray();
        $countVehicles = count($countVehicle);

        // Réquete pour compter le nombre de QCM Connaissances Tâches Pro Tech et Managers réaliser du groupe
        function checkAllocations($allocations, $user, $level, $type)
        {
            return $allocations->findOne([
                "user" => new MongoDB\BSON\ObjectId($user),
                "level" => $level,
                "type" => $type,
            ]);
        }

        $countSavoirJu = [];
        $countSavoirSe = [];
        $countSavoirEx = [];
        $countTechSavFaiJu = [];
        $countTechSavFaiSe = [];
        $countTechSavFaiEx = [];
        $countMaSavFaiJu = [];
        $countMaSavFaiSe = [];
        $countMaSavFaiEx = [];
        $countSavFaiJu = [];
        $countSavFaiSe = [];
        $countSavFaiEx = [];
        $testsUserJu = [];
        $testsUserSe = [];
        $testsUserEx = [];
        $testsTotalJu = [];
        $testsTotalSe = [];
        $testsTotalEx = [];

        foreach ($countUsers as $user) {
            foreach (['Junior' => 'Ju', 'Senior' => 'Se', 'Expert' => 'Ex'] as $level => $levelCode) {
                $factuel = checkAllocations($allocations, $user, $level, "Factuel");
                $declaratif = checkAllocations($allocations, $user, $level, "Declaratif");

                // Accumuler les résultats par niveau
                if (isset($factuel) && $factuel['active'] == true) {
                    ${'countSavoir' . $levelCode}[] = $factuel;
                }
                if (isset($declaratif) && $declaratif['activeManager'] == true) {
                    ${'countMaSavFai' . $levelCode}[] = $declaratif;
                }
                if (isset($declaratif) && $declaratif['active'] == true) {
                    ${'countTechSavFai' . $levelCode}[] = $declaratif;
                }
                if (isset($declaratif) && $declaratif['active'] == true && $declaratif['activeManager'] == true) {
                    ${'countSavFai' . $levelCode}[] = $declaratif;
                }
                if (isset($factuel) && isset($declaratif) && $factuel['active'] == true && $declaratif['active'] == true && $declaratif['activeManager'] == true) {
                    ${'testsUser' . $levelCode}[] = $user;
                }
                if (isset($factuel) && isset($declaratif)) {
                    ${'testsTotal' . $levelCode}[] = $user;
                }
            }
        }

        function getAllocation($allocations, $user, $level, $type, $activeManager = false)
        {
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

        function getResults($results, $user, $level, $typeR, $type)
        {
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

        function calculateAverage($scores, $totals)
        {
            $scoreSum = array_sum($scores);
            $totalSum = array_sum($totals);
            return $totalSum === 0 ? ($scoreSum * 100 / 1) : ($scoreSum * 100 / $totalSum);
        }

        $resultsFacScoreJu = [];
        $resultsDeclaScoreJu = [];
        $resultsFacTotalJu = [];
        $resultsDeclaTotalJu = [];
        $resultsFacScoreSe = [];
        $resultsDeclaScoreSe = [];
        $resultsFacTotalSe = [];
        $resultsDeclaTotalSe = [];
        $resultsFacScoreEx = [];
        $resultsDeclaScoreEx = [];
        $resultsFacTotalEx = [];
        $resultsDeclaTotalEx = [];
        $doneTestExTx = [];

        foreach ($countUsers as $tech) {
            // Junior
            $alloFacJu = getAllocation($allocations, $tech, 'Junior', 'Factuel');
            $alloDeclaJu = getAllocation($allocations, $tech, 'Junior', 'Declaratif', true);

            if ($alloFacJu && $alloDeclaJu) {
                $resultFacJu = getResults($results, $tech, "Junior", 'Technicien', "Factuel");
                if ($resultFacJu) {
                    $resultsFacScoreJu[] = $resultFacJu['score'];
                    $resultsFacTotalJu[] = $resultFacJu['total'];
                }

                $resultDeclaJu = getResults($results, $tech, "Junior", "Technicien - Manager", "Declaratif");
                if ($resultDeclaJu) {
                    $resultsDeclaScoreJu[] = $resultDeclaJu['score'];
                    $resultsDeclaTotalJu[] = $resultDeclaJu['total'];
                }
            }

            // Senior
            $alloFacSe = getAllocation($allocations, $tech, 'Senior', 'Factuel');
            $alloDeclaSe = getAllocation($allocations, $tech, 'Senior', 'Declaratif', true);

            if ($alloFacSe && $alloDeclaSe) {
                $resultFacSe = getResults($results, $tech, "Senior", 'Technicien', "Factuel");
                if ($resultFacSe) {
                    $resultsFacScoreSe[] = $resultFacSe['score'];
                    $resultsFacTotalSe[] = $resultFacSe['total'];
                }

                $resultDeclaSe = getResults($results, $tech, "Senior", "Technicien - Manager", "Declaratif");
                if ($resultDeclaSe) {
                    $resultsDeclaScoreSe[] = $resultDeclaSe['score'];
                    $resultsDeclaTotalSe[] = $resultDeclaSe['total'];
                }
            }

            // Expert
            $alloFacEx = getAllocation($allocations, $tech, 'Expert', 'Factuel');
            $alloDeclaEx = getAllocation($allocations, $tech, 'Expert', 'Declaratif', true);

            if ($alloFacEx && $alloDeclaEx) {
                $resultFacEx = getResults($results, $tech, "Expert", 'Technicien', "Factuel");
                if ($resultFacEx) {
                    $resultsFacScoreEx[] = $resultFacEx['score'];
                    $resultsFacTotalEx[] = $resultFacEx['total'];
                }

                $resultDeclaEx = getResults($results, $tech, "Expert", "Technicien - Manager", "Declaratif");
                if ($resultDeclaEx) {
                    $resultsDeclaScoreEx[] = $resultDeclaEx['score'];
                    $resultsDeclaTotalEx[] = $resultDeclaEx['total'];
                }
            }

            if ($alloFacEx && $alloDeclaEx) {
                $doneTestExTx[] = $tech;
            }
        }

        $avgFacJu = calculateAverage($resultsFacScoreJu, $resultsFacTotalJu);
        $avgDeclaJu = calculateAverage($resultsDeclaScoreJu, $resultsDeclaTotalJu);
        $avgFacSe = calculateAverage($resultsFacScoreSe, $resultsFacTotalSe);
        $avgDeclaSe = calculateAverage($resultsDeclaScoreSe, $resultsDeclaTotalSe);
        $avgFacEx = calculateAverage($resultsFacScoreEx, $resultsFacTotalEx);
        $avgDeclaEx = calculateAverage($resultsDeclaScoreEx, $resultsDeclaTotalEx);

        $percentageFacJu = $avgFacJu;
        $percentageDeclaJu = $avgDeclaJu;
        $percentageFacSe = $avgFacSe;
        $percentageDeclaSe = $avgDeclaSe;
        $percentageFacEx = $avgFacEx;
        $percentageDeclaEx = $avgDeclaEx;

        $resultsFacScoreJuTj = [];
        $resultsDeclaScoreJuTj = [];
        $resultsFacTotalJuTj = [];
        $resultsDeclaTotalJuTj = [];
        $doneTestJuTj = [];

        foreach ($countUsersJu as $tech) {
            $alloFacJuTj = getAllocation($allocations, $tech, 'Junior', 'Factuel');
            $alloDeclaJuTj = getAllocation($allocations, $tech, 'Junior', 'Declaratif', true);

            if ($alloFacJuTj && $alloDeclaJuTj && $alloFacJuTj['active'] && $alloDeclaJuTj['active'] && $alloDeclaJuTj['activeManager']) {
                $doneTestJuTj[] = $tech;
            }

            if ($alloFacJuTj && $alloDeclaJuTj) {
                $resultFacJuTj = getResults($results, $tech, "Junior", 'Technicien', "Factuel");
                if ($resultFacJuTj) {
                    $resultsFacScoreJuTj[] = $resultFacJuTj['score'];
                    $resultsFacTotalJuTj[] = $resultFacJuTj['total'];
                }

                $resultDeclaJuTj = getResults($results, $tech, "Junior", "Technicien - Manager", "Declaratif");
                if ($resultDeclaJuTj) {
                    $resultsDeclaScoreJuTj[] = $resultDeclaJuTj['score'];
                    $resultsDeclaTotalJuTj[] = $resultDeclaJuTj['total'];
                }
            }
        }

        $percentageFacJuTj = calculateAverage($resultsFacScoreJuTj, $resultsFacTotalJuTj);
        $percentageDeclaJuTj = calculateAverage($resultsDeclaScoreJuTj, $resultsDeclaTotalJuTj);

        $resultsFacScoreJuTs = [];
        $resultsDeclaScoreJuTs = [];
        $resultsFacScoreSeTs = [];
        $resultsDeclaScoreSeTs = [];
        $resultsFacTotalJuTs = [];
        $resultsDeclaTotalJuTs = [];
        $resultsFacTotalSeTs = [];
        $resultsDeclaTotalSeTs = [];
        $doneTestSeTs = [];

        foreach ($countUsersSe as $tech) {
            // Junior Allocations
            $alloFacJuTs = getAllocation($allocations, $tech, 'Junior', 'Factuel');
            $alloDeclaJuTs = getAllocation($allocations, $tech, 'Junior', 'Declaratif', true);

            // Senior Allocations
            $alloFacSeTs = getAllocation($allocations, $tech, 'Senior', 'Factuel');
            $alloDeclaSeTs = getAllocation($allocations, $tech, 'Senior', 'Declaratif', true);

            if ($alloFacSeTs && $alloDeclaSeTs && $alloFacSeTs['active'] && $alloDeclaSeTs['active'] && $alloDeclaSeTs['activeManager']) {
                $doneTestSeTs[] = $tech;
            }

            // Process Junior results
            if ($alloFacJuTs && $alloDeclaJuTs) {
                $resultFacJuTs = getResults($results, $tech, "Junior", 'Technicien', "Factuel");
                if ($resultFacJuTs) {
                    $resultsFacScoreJuTs[] = $resultFacJuTs['score'];
                    $resultsFacTotalJuTs[] = $resultFacJuTs['total'];
                }

                $resultDeclaJuTs = getResults($results, $tech, "Junior", "Technicien - Manager", "Declaratif");
                if ($resultDeclaJuTs) {
                    $resultsDeclaScoreJuTs[] = $resultDeclaJuTs['score'];
                    $resultsDeclaTotalJuTs[] = $resultDeclaJuTs['total'];
                }
            }

            // Process Senior results
            if ($alloFacSeTs && $alloDeclaSeTs) {
                $resultFacSeTs = getResults($results, $tech, "Senior", 'Technicien', "Factuel");
                if ($resultFacSeTs) {
                    $resultsFacScoreSeTs[] = $resultFacSeTs['score'];
                    $resultsFacTotalSeTs[] = $resultFacSeTs['total'];
                }

                $resultDeclaSeTs = getResults($results, $tech, "Senior", "Technicien - Manager", "Declaratif");
                if ($resultDeclaSeTs) {
                    $resultsDeclaScoreSeTs[] = $resultDeclaSeTs['score'];
                    $resultsDeclaTotalSeTs[] = $resultDeclaSeTs['total'];
                }
            }
        }

        // Calculate averages
        $percentageFacJuTs = calculateAverage($resultsFacScoreJuTs, $resultsFacTotalJuTs);
        $percentageDeclaJuTs = calculateAverage($resultsDeclaScoreJuTs, $resultsDeclaTotalJuTs);
        $percentageFacSeTs = calculateAverage($resultsFacScoreSeTs, $resultsFacTotalSeTs);
        $percentageDeclaSeTs = calculateAverage($resultsDeclaScoreSeTs, $resultsDeclaTotalSeTs);

        $resultsFacScoreJuTx = [];
        $resultsDeclaScoreJuTx = [];
        $resultsFacScoreSeTx = [];
        $resultsDeclaScoreSeTx = [];
        $resultsFacTotalJuTx = [];
        $resultsDeclaTotalJuTx = [];
        $resultsFacTotalSeTx = [];
        $resultsDeclaTotalSeTx = [];

        foreach ($countUsersEx as $tech) {
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
                    $resultsFacScoreJuTx[] = $resultFacJuTx['score'];
                    $resultsFacTotalJuTx[] = $resultFacJuTx['total'];
                }

                $resultDeclaJuTx = getResults($results, $tech, "Junior", "Technicien - Manager", "Declaratif");
                if ($resultDeclaJuTx) {
                    $resultsDeclaScoreJuTx[] = $resultDeclaJuTx['score'];
                    $resultsDeclaTotalJuTx[] = $resultDeclaJuTx['total'];
                }
            }

            // Process Senior results
            if ($alloFacSeTx && $alloDeclaSeTx) {
                $resultFacSeTx = getResults($results, $tech, "Senior", 'Technicien', "Factuel");
                if ($resultFacSeTx) {
                    $resultsFacScoreSeTx[] = $resultFacSeTx['score'];
                    $resultsFacTotalSeTx[] = $resultFacSeTx['total'];
                }

                $resultDeclaSeTx = getResults($results, $tech, "Senior", "Technicien - Manager", "Declaratif");
                if ($resultDeclaSeTx) {
                    $resultsDeclaScoreSeTx[] = $resultDeclaSeTx['score'];
                    $resultsDeclaTotalSeTx[] = $resultDeclaSeTx['total'];
                }
            }
        }

        // Calculate averages
        $percentageFacJuTx = calculateAverage($resultsFacScoreJuTx, $resultsFacTotalJuTx);
        $percentageDeclaJuTx = calculateAverage($resultsDeclaScoreJuTx, $resultsDeclaTotalJuTx);
        $percentageFacSeTx = calculateAverage($resultsFacScoreSeTx, $resultsFacTotalSeTx);
        $percentageDeclaSeTx = calculateAverage($resultsDeclaScoreSeTx, $resultsDeclaTotalSeTx);

        $percentageSavoir = ceil(((count($countSavoirJu) + count($countSavoirSe) + count($countSavoirEx)) * 100) / (count($countUsers) + count($countUsersSe) + (count($countUsersEx)) * 2));
        $percentageMaSavoirFaire = ceil(((count($countMaSavFaiJu) + count($countMaSavFaiSe) + count($countMaSavFaiEx)) * 100) / (count($countUsers) + count($countUsersSe) + (count($countUsersEx)) * 2));
        $percentageTechSavoirFaire = ceil(((count($countTechSavFaiJu) + count($countTechSavFaiSe) + count($countTechSavFaiEx)) * 100) / (count($countUsers) + count($countUsersSe) + (count($countUsersEx)) * 2));
    }

    if ($_SESSION['profile'] == 'Directeur Général' || $_SESSION['profile'] == 'Directeur Pièce et Service' || $_SESSION['profile'] == 'Directeur des Opérations' || $_SESSION['profile'] == 'Admin' || $_SESSION['profile'] == 'Ressource Humaine') {
        // Requête pour determiner les utilisateurs filiale
        // 1) Redéfinition de la fonction pour intégrer la marque
        /**
         * Récupère la liste des _id des techniciens (et managers “test=true”)
         * actifs d’une filiale, avec filtres optionnels niveau & marque.
         *
         * @param MongoDB\Collection $users       Collection « users »
         * @param string             $subsidiary  Nom exact de la filiale
         * @param string|null        $level       'Junior' | 'Senior' | 'Expert' | null = tous
         * @param string             $brand       '' = toutes marques, sinon 'TOYOTA', …
         * @return array             Tableau d’ObjectId (ou de chaînes) des utilisateurs trouvés
         */
        function getTechnicians(
            $users,
            string $subsidiary,
            ?string $level = null,
            string $brand = ''
        ): array {

            /* ─── filtre de base ─────────────────────────────────────────────── */
            $query = [
                'active'     => true,
                'subsidiary' => $subsidiary,
                '$or'        => [
                    ['profile' => 'Technicien'],
                    ['profile' => 'Manager', 'test' => true],   // ← managers validés
                ],
            ];

            /* ─── niveau ─────────────────────────────────────────────────────── */
            if ($level !== null) {
                $query['level'] = $level;
            }

            /* ─── filtre marque ──────────────────────────────────────────────── */
            if ($brand !== '') {
                switch ($level) {

                    case 'Junior':
                        $query['brandJunior'] = ['$in' => [$brand]];
                        break;

                    case 'Senior':
                        $query['brandSenior'] = ['$in' => [$brand]];
                        break;

                    case 'Expert':
                        $query['brandExpert'] = ['$in' => [$brand]];
                        break;

                    default:         // total → on regarde dans les trois tableaux
                        $query['$or'][] = ['brandJunior' => ['$in' => [$brand]]];
                        $query['$or'][] = ['brandSenior' => ['$in' => [$brand]]];
                        $query['$or'][] = ['brandExpert' => ['$in' => [$brand]]];
                }
            }

            /* ─── exécution & récupération des _id uniquement ────────────────── */
            $ids = [];
            foreach ($users->find($query, ['projection' => ['_id' => 1]]) as $doc) {
                // $ids[] = (string) $doc->_id;   // ← si tu préfères avoir des chaînes
                $ids[] = $doc->_id;               // ← ou laisse l’ObjectId MongoDB
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
        function getAllocation($allocations, $user, $level, $type, $activeManager = false)
        {
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

        function getResults($results, $user, $level, $typeR, $type)
        {
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

        function calculateAverage($scores, $totals)
        {
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
        function checkAllocations($allocations, $user, $level, $type)
        {
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
                    ${'countSavoirs' . $levelCode}[] = $factuel;
                }
                if (isset($declaratif) && $declaratif['activeManager'] == true) {
                    ${'countMaSavFais' . $levelCode}[] = $declaratif;
                }
                if (isset($declaratif) && $declaratif['active'] == true) {
                    ${'countTechSavFais' . $levelCode}[] = $declaratif;
                }
                if (isset($declaratif) && $declaratif['active'] == true && $declaratif['activeManager'] == true) {
                    ${'countSavFais' . $levelCode}[] = $declaratif;
                }
                if (isset($factuel) && isset($declaratif) && $factuel['active'] == true && $declaratif['active'] == true && $declaratif['activeManager'] == true) {
                    ${'tests' . $levelCode}[] = $user;
                }
                if (isset($factuel) && isset($declaratif)) {
                    ${'testTotal' . $levelCode}[] = $user;
                }
            }
        }

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
        "Cameroun" => ["Bafoussam", "Bertoua", "Douala", "Garoua", "Ngaoundere", "Yaoundé"],
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

    // Fonction pour calculer le pourcentage de non-maîtrise
    function calculateQuestionMasteryStats($academy, $niveau, $country, $tableauResultats, $agency = null)
    {
        // Récupérer les questions "Déclaratives" actives pour le niveau donné
        $questions = $academy->questions->find([
            "type" => "Declarative",
            "level" => $niveau,
            "active" => true
        ])->toArray();

        // Filtrer les techniciens en fonction du profil, du pays, du niveau et de l'agence
        $technicienss = filterUsersByProfile($academy, $_SESSION['profile'], $country, $niveau, $agency);
        $totalQuestions = count($questions);
        $totalNonMaitriseQuestions = 0;
        $totalSingleMaitriseQuestions = 0;
        $totalDoubleMaitriseQuestions = 0; // Nouveau compteur pour totalMaitrise == 2

        foreach ($questions as $question) {
            $totalMaitrise = 0;
            $questionId = (string)$question['_id'];

            foreach ($technicienss as $technician) {
                $techId = (string)$technician['_id'];

                // Vérifier si le résultat est disponible dans le tableau des résultats
                $status = isset($tableauResultats[$techId][$questionId]) ? $tableauResultats[$techId][$questionId] : 'Non maîtrisé';

                // Compter le nombre de "Maîtrisé"
                if ($status == "Maîtrisé") {
                    $totalMaitrise++;
                }
            }

            // Compter les questions en fonction du nombre de techniciens qui les maîtrisent
            if ($totalMaitrise == 0) {
                $totalNonMaitriseQuestions++;
            } elseif ($totalMaitrise == 1) {
                $totalSingleMaitriseQuestions++;
            } elseif ($totalMaitrise == 2) {
                $totalDoubleMaitriseQuestions++; // Incrémenter le compteur pour 2 techniciens
            }
        }

        // Retourner les statistiques
        return [
            'totalQuestions' => $totalQuestions,
            'nonMaitrise' => $totalNonMaitriseQuestions,
            'singleMaitrise' => $totalSingleMaitriseQuestions,
            'doubleMaitrise' => $totalDoubleMaitriseQuestions // Ajouter ce champ
        ];
    }

    // Récupérer les résultats validés pour chaque niveau
    $junior = 'Junior';
    $senior = 'Senior';
    $expert = 'Expert';
    $total = 'Total';

    $tableauResultatsJunior = getValidatedResults($junior);
    $tableauResultatsSenior = getValidatedResults($senior);
    $tableauResultatsExpert = getValidatedResults($expert);

    // Calcul des statistiques pour chaque niveau
    $statsJunior = calculateQuestionMasteryStats($academy, $junior, $selectedCountry, $tableauResultatsJunior, $selectedAgency);
    $statsSenior = calculateQuestionMasteryStats($academy, $senior, $selectedCountry, $tableauResultatsSenior, $selectedAgency);
    $statsExpert = calculateQuestionMasteryStats($academy, $expert, $selectedCountry, $tableauResultatsExpert, $selectedAgency);

    // Calcul des pourcentages pour chaque niveau
    function calculatePercentages($stats)
    {
        $totalQuestions = $stats['totalQuestions'];
        $percentages = [
            'nonMaitrise' => ($totalQuestions > 0) ? round(($stats['nonMaitrise'] / $totalQuestions) * 100) : 0,
            'singleMaitrise' => ($totalQuestions > 0) ? round(($stats['singleMaitrise'] / $totalQuestions) * 100) : 0,
            'doubleMaitrise' => ($totalQuestions > 0) ? round(($stats['doubleMaitrise'] / $totalQuestions) * 100) : 0,
        ];
        // Calculer le pourcentage restant pour les autres tâches
        $percentages['others'] = 100 - ($percentages['nonMaitrise'] + $percentages['singleMaitrise'] + $percentages['doubleMaitrise']);
        return $percentages;
    }

    // Calcul des pourcentages pour chaque niveau
    $percentagesJunior = calculatePercentages($statsJunior);
    $percentagesSenior = calculatePercentages($statsSenior);
    $percentagesExpert = calculatePercentages($statsExpert);

    // Calcul du total pour tous les niveaux
    $totalQuestionsAllLevels = $statsJunior['totalQuestions'] + $statsSenior['totalQuestions'] + $statsExpert['totalQuestions'];
    $totalNonMaitriseAllLevels = $statsJunior['nonMaitrise'] + $statsSenior['nonMaitrise'] + $statsExpert['nonMaitrise'];
    $totalSingleMaitriseAllLevels = $statsJunior['singleMaitrise'] + $statsSenior['singleMaitrise'] + $statsExpert['singleMaitrise'];
    $totalDoubleMaitriseAllLevels = $statsJunior['doubleMaitrise'] + $statsSenior['doubleMaitrise'] + $statsExpert['doubleMaitrise'];

    $statsTotal = [
        'totalQuestions' => $totalQuestionsAllLevels,
        'nonMaitrise' => $totalNonMaitriseAllLevels,
        'singleMaitrise' => $totalSingleMaitriseAllLevels,
        'doubleMaitrise' => $totalDoubleMaitriseAllLevels
    ];

    $statsJunior['othersCount'] = $statsJunior['totalQuestions'] - ($statsJunior['nonMaitrise'] + $statsJunior['singleMaitrise'] + $statsJunior['doubleMaitrise']);
    $statsSenior['othersCount'] = $statsSenior['totalQuestions'] - ($statsSenior['nonMaitrise'] + $statsSenior['singleMaitrise'] + $statsSenior['doubleMaitrise']);
    $statsExpert['othersCount'] = $statsExpert['totalQuestions'] - ($statsExpert['nonMaitrise'] + $statsExpert['singleMaitrise'] + $statsExpert['doubleMaitrise']);
    $statsTotal['othersCount'] = $statsTotal['totalQuestions'] - ($statsTotal['nonMaitrise'] + $statsTotal['singleMaitrise'] + $statsTotal['doubleMaitrise']);

    $percentagesTotal = calculatePercentages($statsTotal);

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

    // Nombre total de tâches pour chaque niveau
    $numberOfTasksJunior = $statsJunior['totalQuestions'];
    $numberOfTasksSenior = $statsSenior['totalQuestions'];
    $numberOfTasksExpert = $statsExpert['totalQuestions'];
    $numberOfTasksTotal = $statsTotal['totalQuestions'];

    // Récupérer les paramètres depuis l'URL
    $selectedLevel = isset($_GET['level']) ? $_GET['level'] : 'Junior';
    $selectedCountry = isset($_GET['country']) ? $_GET['country'] : null;
    $selectedAgency = isset($_GET['agency']) ? $_GET['agency'] : null;
    $selectedUser = isset($_GET['user']) ? $_GET['user'] : null;

    // Récupérer les questions déclaratives actives pour le niveau sélectionné
    $questionDeclaCursor = $questionsCollection->find([
        '$and' => [
            ["type" => "Declarative"],
            ["level" => $selectedLevel],
            ["active" => true]
        ],
    ]);

    $questionDecla = iterator_to_array($questionDeclaCursor);

    $questionDeclaCursorFact = $questionsCollection->find([
        '$and' => [
            ["type" => "Factuelle"],
            ["level" => $selectedLevel],
            ["active" => true]
        ],
    ]);

    $questionDeclaF = iterator_to_array($questionDeclaCursorFact);

    // Récupérer toutes les questions pour créer une liste d'ID de questions
    $allQuestionIds = [];
    foreach ($questionDecla as $question) {
        $allQuestionIds[] = (string)$question['_id'];
    }

    $allQuestionIdsF = [];
    foreach ($questionDeclaF as $questionF) {
        $allQuestionIdsF[] = (string)$questionF['_id'];
    }

    // Récupérer le profil utilisateur de la session
    $profile = $_SESSION['profile'];
    $userCountry = isset($_SESSION['country']) ? $_SESSION['country'] : null;

    // Filtrer pour obtenir uniquement les techniciens selon le profil de l'utilisateur
    $technicians = filterUsersByProfile($academy, $profile, $selectedCountry, $selectedLevel, $selectedAgency);
    $techniciansF = filterUsersByProfile($academy, $profile, $selectedCountry, $selectedLevel, $selectedAgency);

    // Définir le titre en fonction du niveau sélectionné
    $taux_de_couverture = "";
    switch ($selectedLevel) {
        case 'Junior':
            $taux_de_couverture = $taux_de_couverture_ju;
            break;
        case 'Senior':
            $taux_de_couverture = $taux_de_couverture_se;
            break;
        case 'Expert':
            $taux_de_couverture = $taux_de_couverture_ex;
            break;
        default:
            $taux_de_couverture = $taux_de_couverture_ju;
            break;
    }

    // Connexion aux collections nécessaires
    $resultsCollection = $academy->results;

    // Récupérer les résultats validés par technicien et par question
    $tableauResultats = getTechnicianResults($selectedLevel);
    $tableauResultatsF = getTechnicianResults3($selectedLevel);

    $agencies = [
        "Burkina Faso" => ["Ouaga"],
        "Cameroun" => ["Bafoussam", "Bertoua", "Douala", "Garoua", "Ngaoundere", "Yaoundé"],
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

    // Boucles pour chaque niveau (Questions Déclaratives)
    foreach ($niveaus as $niveau) {
        // Récupérer les techniciens par niveau
        $techniciensss = filterUsersByProfile($academy, "Directeur Groupe", null, $niveau, null);
        $totalTechniciansByLevel[$niveau] = count($technicians);
        $tableauResultats = getTechnicianResults($niveau);

        $totalPercentage = 0;
        $count = 0;

        // Calculer les pourcentages de maîtrise
        foreach ($techniciensss as $technician) {
            $techId = (string)$technician['_id'];
            if (isset($tableauResultats[$techId])) {
                $totalPercentage += $tableauResultats[$techId];
                $count++;
            }
        }

        $technicianPercentagesByLevel[$niveau] = $count > 0 ? ($totalPercentage / $count) : 0;
        $technicianCountsByLevel[$niveau] = $count;
    }

    // Calculer les moyennes par niveau (Questions Déclaratives)
    $averageMasteryByLevel = [];
    foreach ($niveaus as $niveau) {
        $averageMasteryByLevel[$niveau] = round($technicianPercentagesByLevel[$niveau]);
    }

    // Calculer les totaux pour 'Total' (Questions Déclaratives)
    $totalTechnicians = array_sum($totalTechniciansByLevel);
    $techniciansWhoTookTest = array_sum($technicianCountsByLevel);
    $totalPercentage = array_sum($averageMasteryByLevel);

    $totalTechniciansByLevel['Total'] = $totalTechnicians;
    $technicianCountsByLevel['Total'] = $techniciansWhoTookTest;
    $averageMasteryByLevel['Total'] = $techniciansWhoTookTest > 0 ? round($totalPercentage / count($niveaus)) : 0;

    // Boucles pour chaque niveau (Questions Factuelles)
    foreach ($niveaus as $niveauF) {
        // Récupérer les techniciens par niveau
        $techniciansF = filterUsersByProfile($academy, $profile, $selectedCountry, $niveauF, $selectedAgency);
        $totalTechniciansByLevelF[$niveauF] = count($techniciansF);
        $tableauResultatsF = getTechnicianResults3($niveauF);

        $totalPercentageF = 0;
        $countF = 0;

        // Calculer les pourcentages de maîtrise
        foreach ($techniciansF as $technicianF) {
            $techIdF = (string)$technicianF['_id'];
            if (isset($tableauResultatsF[$techIdF])) {
                $totalPercentageF += $tableauResultatsF[$techIdF];
                $countF++;
            }
        }

        $technicianPercentagesByLevelF[$niveauF] = $countF > 0 ? ($totalPercentageF / $countF) : 0;
        $technicianCountsByLevelF[$niveauF] = $countF;
    }

    // Calculer les moyennes par niveau (Questions Factuelles)
    $averageMasteryByLevelF = [];
    foreach ($niveaus as $niveauF) {
        $averageMasteryByLevelF[$niveauF] = round($technicianPercentagesByLevelF[$niveauF]);
    }

    // Calculer les totaux pour 'Total' (Questions Factuelles)
    $totalTechniciansF = array_sum($totalTechniciansByLevelF);
    $techniciansWhoTookTestF = array_sum($technicianCountsByLevelF);
    $totalPercentageF = array_sum($averageMasteryByLevelF);

    $totalTechniciansByLevelF['Total'] = $totalTechniciansF;
    $technicianCountsByLevelF['Total'] = $techniciansWhoTookTestF;
    $averageMasteryByLevelF['Total'] = $techniciansWhoTookTestF > 0 ? round($totalPercentageF / count($niveaus)) : 0;

    // Passer les données au JavaScript

?>
    <?php include "./partials/header.php"; ?>

    <?php openBackgroundContainer(); ?>

    <style>
        /* Glassmorphism effects for dashboard elements */
        /* Glass effect with 18px blur for technician statistics cards */
        .glass-effect-card {
            background: rgba(255, 255, 255, 0.7) !important;
            backdrop-filter: blur(18px) !important;
            -webkit-backdrop-filter: blur(18px) !important;
            border: 1px solid rgba(255, 255, 255, 0.18) !important;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15) !important;
            border-radius: 15px !important;
            transition: all 0.3s ease !important;
        }

        /* Glass effect with stronger 25px blur for doughnut charts */
        .glass-effect-chart {
            background: rgba(255, 255, 255, 0.65) !important;
            backdrop-filter: blur(25px) !important;
            -webkit-backdrop-filter: blur(25px) !important;
            border: 1px solid rgba(255, 255, 255, 0.18) !important;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15) !important;
            border-radius: 15px !important;
            transition: all 0.3s ease !important;
        }

        /* Hover effect for glass elements */
        .glass-effect-card:hover,
        .glass-effect-chart:hover {
            background: rgba(255, 255, 255, 0.8) !important;
            box-shadow: 0 10px 40px 0 rgba(31, 38, 135, 0.2) !important;
            transform: translateY(-3px) !important;
        }

        /* Depth effect for enhanced visual appearance */
        .depth-effect {
            transform: translateZ(0);
            box-shadow:
                0 4px 6px -1px rgba(0, 0, 0, 0.1),
                0 2px 4px -1px rgba(0, 0, 0, 0.06),
                0 12px 20px -2px rgba(0, 0, 0, 0.05) !important;
            transition: all 0.3s ease-in-out !important;
        }

        .depth-effect:hover {
            transform: translateY(-5px) translateZ(0);
            box-shadow:
                0 10px 15px -3px rgba(0, 0, 0, 0.1),
                0 4px 6px -2px rgba(0, 0, 0, 0.05),
                0 20px 25px -5px rgba(0, 0, 0, 0.03) !important;
        }

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

        /* Force chaque "card de titre" à prendre 100% de la largeur du container parent */
        .title-full { width: 100%; }
        .title-full > .card { width: 100%; margin-left: 0; margin-right: 0; }

    </style>

    <!--begin::Title-->
    <title><?php echo $tableau ?> | CFAO Mobility Academy</title>
    <!--end::Title-->

    <!--begin::Content-->
    <div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content" style="background-color: transparent;">
        <?php if ($_SESSION["profile"] == "Admin" || $_SESSION["profile"] == "Ressource Humaine" || $_SESSION["profile"] == "Directeur Pièce et Service" || $_SESSION["profile"] == "Directeur des Opérations" || $_SESSION["profile"] == "Directeur Général" || $_SESSION["profile"] == "Directeur Groupe") { ?>
            <!--begin::Toolbar-->
            <div class="toolbar" id="kt_toolbar">
                <div class=" container-fluid  d-flex flex-stack flex-wrap flex-sm-nowrap">
                    <!--begin::Info-->
                    <div class="d-flex flex-column align-items-start justify-content-center flex-wrap me-2 w-100">
                        <!--begin::Title-->
                        <div class="mt-5 title-full">
                            <div class="card shadow-sm mb-5 w-100">
                                <div class="card-body p-4">
                                    <h1 class="text-dark fw-bold text-center fs-1">
                                        <?php echo $tableau ?>
                                    </h1>
                                </div>
                            </div>
                        </div>
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
                        <?php if ($_SESSION["profile"] == "Directeur Groupe") { ?>
                            <!--begin::Toolbar-->
                            <div class="toolbar" id="kt_toolbar" style="margin-bottom: -50px">
                                 <!-- <= ICI -->
                                    <div class="mt-5 title-full">
                                        <div class="card shadow-sm mb-5">
                                            <div class="card-body p-4">
                                                <h1 class="text-dark fw-bold text-center fs-2">
                                                    <?php echo $effectif_total_groupe ?>
                                                </h1>
                                            </div>
                                        </div>
                                    </div>
                                    
                                
                            </div>


                            <!--end::Toolbar-->
                            <!--begin::Col-->
                            <div class="col-md-6 col-lg-4 col-xl-3">
                                <!--begin::Card-->
                                <div class="card h-100 glass-effect-chart depth-effect">
                                    <!--begin::Card body-->
                                    <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                        <!--begin::Name-->
                                        <!--begin::Animation-->
                                        <div
                                            class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                            <div class="min-w-70px" data-kt-countup="true"
                                                data-kt-countup-value="<?php echo count($countUsersJu) ?>">
                                            </div>
                                        </div>
                                        <!--end::Animation-->
                                        <!--begin::Title-->
                                        <div class="fs-5 fw-bold mb-2">
                                            <?php echo $technicienss . ' ' . $Level . ' ' . $junior ?> 
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
                            <div class="col-md-6 col-lg-4 col-xl-3">
                                <!--begin::Card-->
                                <div class="card h-100 glass-effect-chart depth-effect">
                                    <!--begin::Card body-->
                                    <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                        <!--begin::Name-->
                                        <!--begin::Animation-->
                                        <div
                                            class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                            <div class="min-w-70px" data-kt-countup="true"
                                                data-kt-countup-value="<?php echo count($countUsersSe) ?>">
                                            </div>
                                        </div>
                                        <!--end::Animation-->
                                        <!--begin::Title-->
                                        <div class="fs-5 fw-bold mb-2">
                                            <?php echo $technicienss . ' ' . $Level . ' ' . $senior ?> 
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
                            <div class="col-md-6 col-lg-4 col-xl-3">
                                <!--begin::Card-->
                                <div class="card h-100 glass-effect-chart depth-effect">
                                    <!--begin::Card body-->
                                    <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                        <!--begin::Name-->
                                        <!--begin::Animation-->
                                        <div
                                            class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                            <div class="min-w-70px" data-kt-countup="true"
                                                data-kt-countup-value="<?php echo count($countUsersEx) ?>">
                                            </div>
                                        </div>
                                        <!--end::Animation-->
                                        <!--begin::Title-->
                                        <div class="fs-5 fw-bold mb-2">
                                            <?php echo $technicienss . ' ' . $Level . ' ' . $expert ?> 
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
                            <div class="col-md-6 col-lg-4 col-xl-3">
                                <!--begin::Card-->
                                <div class="card h-100 glass-effect-chart depth-effect">
                                    <!--begin::Card body-->
                                    <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                        <!--begin::Name-->
                                        <!--begin::Animation-->
                                        <div
                                            class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                            <div class="min-w-70px" data-kt-countup="true"
                                                data-kt-countup-value="<?php echo count($countUsers) ?>">
                                            </div>
                                        </div>
                                        <!--end::Animation-->
                                        <!--begin::Title-->
                                        <div class="fs-5 fw-bold mb-2">
                                            <?php echo $technicienss . ' ' . $global ?> 
                                        </div>
                                        <!--end::Title-->
                                        <!--end::Name-->
                                    </div>
                                    <!--end::Card body-->
                                </div>
                                <!--end::Card-->
                            </div>
                            <!--end::Col-->
                            <!--begin::Title-->
                            <div class="mt-5 title-full">
                                <div class="card shadow-sm mb-5 w-100">
                                    <div class="card-body p-4">
                                        <h6 class="text-dark fw-bold text-center fs-2">
                                            <?php echo $result_mesure_competence_groupe ?>
                                        </h6>
                                    </div>
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
                                            <h4 style=" margin-top: 30px; color: white"><?php echo $result_entre_0_60 ?></h4>
                                        </div>

                                        <!-- Group 2 -->
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <canvas id='c2' width="75" height="37.5"></canvas>
                                            <h4 style="margin-top: 30px; color: white"><?php echo $result_entre_60_80 ?></h4>
                                        </div>

                                        <!-- Group 3 -->
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <canvas id='c3' width="75" height="37.5"></canvas>
                                            <h4 style="margin-top: 30px; color: white"><?php echo $result_entre_80_100 ?></h4>
                                        </div>
                                    </fieldset>
                                </div>
                            </div>
                            <!-- end::Row -->
                            <!-- Dropdown Container -->
                            <!--begin::Title-->
                            <div class="mt-5 title-full">
                                <div class="card shadow-sm mb-5">
                                    <div class="card-body p-4">
                                        <h6 class="text-dark fw-bold text-center fs-2">
                                            Résultat par Type de Tests
                                        </h6>
                                    </div>
                                </div>
                            </div>
                            <!--end::Title-->
                            <!-- Begin::Row -->
                            <div class="row">
                                <!-- Card 1 -->
                                <div class="col-xl-3">
                                    <div class="card justify-content-center glass-effect-chart depth-effect" style="height: 430px; width: 350px; margin-bottom: 25px;">
                                        <center>
                                            <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                Résultats Niveau Junior
                                            </h5>
                                        </center>
                                        <div id="result_junior"
                                        class="d-flex align-items-center justify-content-center w-100"
                                            style="height: 100%; width: 100%; margin-top: 10px;">
                                        </div>
                                    </div>
                                </div>
                                <!-- Card 2 -->
                                <div class="col-xl-3">
                                    <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 350px; margin-bottom: 25px;">
                                        <center>
                                            <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                Résultats Niveau Senior
                                            </h5>
                                        </center>
                                        <div id="result_senior"
                                            class="d-flex align-items-center justify-content-center w-100"
                                            style="height: 100%; width: 100%; margin-top: 10px;">
                                        </div>
                                    </div>
                                </div>
                                <!-- Card 3 -->
                                <div class="col-xl-3">
                                    <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 350px; margin-bottom: 25px;">
                                        <center>
                                            <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                Résultats Niveau Expert
                                            </h5>
                                        </center>
                                        <div id="result_expert"
                                            class="d-flex align-items-center justify-content-center w-100"
                                            style="height: 100%; width: 100%; margin-top: 10px;">
                                        </div>
                                    </div>
                                </div>
                                <!-- Card 4 -->
                                <div class="col-xl-3">
                                    <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 350px; margin-bottom: 25px;">
                                        <center>
                                            <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                Total : 03 Niveaux
                                            </h5>
                                        </center>
                                        <div id="result_total"
                                            class="d-flex align-items-center justify-content-center w-100"
                                            style="height: 100%; width: 100%; margin-top: 10px;">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- End::Row -->
                    </div>
               
                
                <!--begin::Title-->
                <div class="mt-5 title-full">
                    <div class="card shadow-sm mb-5 w-100">
                        <div class="card-body p-4">
                            <h6 class="text-dark fw-bold text-center fs-2">
                                <?php echo $result_mesure_competence_niveau ?>
                            </h6>
                        </div>
                    </div>
                </div>
                <!--end::Title-->
                <!-- begin::Row -->
                <div class="row">
                    <!-- Card 1 -->
                    <div class="col-xl-3">
                        <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 350px; margin-bottom: 25px;">
                            <center>
                                <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                    Résultats Niveau Junior
                                </h5>
                            </center>
                            <div id="chart_junior"
                                class="d-flex align-items-center justify-content-center w-100"
                                            style="height: 100%; width: 100%; margin-top: 10px;">
                            </div>
                        </div>
                    </div>
                    <!-- Card 2 -->
                    <div class="col-xl-3">
                        <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 350px; margin-bottom: 25px;">
                            <center>
                                <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                    Résultats Niveau Senior</h5>
                            </center>
                            <div id="chart_senior"
                                class="d-flex align-items-center justify-content-center w-100"
                                            style="height: 100%; width: 100%; margin-top: 10px;"></div>
                        </div>
                    </div>
                    <!-- Card 3 -->
                    <div class="col-xl-3">
                        <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 350px; margin-bottom: 25px;">
                            <center>
                                <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                    Résultats Niveau Expert</h5>
                            </center>
                            <div id="chart_expert"
                                class="d-flex align-items-center justify-content-center w-100"
                                            style="height: 100%; width: 100%; margin-top: 10px;"></div>
                        </div>
                    </div>
                    <!-- Card 4 -->
                    <div class="col-xl-3">
                        <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 350px; margin-bottom: 25px;">
                            <center>
                                <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                    Total : 03 Niveaux</h5>
                            </center>
                            <div id="chart_total"
                                class="d-flex align-items-center justify-content-center w-100"
                                            style="height: 100%; width: 100%; margin-top: 10px;"></div>
                        </div>
                    </div>
                </div>
                <!-- end::Row -->
                <!--begin::Title-->
                <div class="mt-5 title-full">
                    <div class="card shadow-sm mb-5 w-100">
                        <div class="card-body p-4">
                            <h6 class="text-dark fw-bold text-center fs-2">
                                <?php echo $etat_avanacement_test_realises_groupe ?>
                            </h6>
                        </div>
                    </div>
                </div>
                <!--end::Title-->
                <!-- begin::Row -->
                <div>
                    <div id="chartTest" class="row">
                        <!-- Dynamic cards will be appended here -->
                    </div>
                </div>
                <!-- end::Row -->
                <!-- Dropdown Toggle Button -->
                <div class="dropdown-container">
                    <button class="dropdown-toggle" style="color: black">Plus de détails sur les tests
                        <i class="fas fa-chevron-down"></i></button>
                    <!-- Hidden Content -->
                    <div class="dropdown-content">
                        <!--begin::Title-->
                        <div class="my-5 title-full">
                              <div class="card shadow-sm mb-5">
                                <div class="card-body p-4">
                            
                                    <h6 class="text-dark fw-bold text-center my-1 fs-2">
                                        <?php echo $etat_avanacement_qcm_realises_groupe ?>
                                    </h6>
                                </div>
                            </div>
                        </div>
                        <!--end::Title-->
                        <!-- Begin::Row -->
                        <div class="row">
                            <!-- Card 1 -->
                            <div class="col-xl-3">
                                <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 350px; margin-bottom: 25px;">
                                    <center>
                                        <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                            QCM Niveau Junior</h5>
                                    </center>
                                    <div id="qcm_junior"
                                        class="d-flex align-items-center justify-content-center w-100"
                                            style="height: 100%; width: 100%; margin-top: 10px;"></div>
                                </div>
                            </div>
                            <!-- Card 2 -->
                            <div class="col-xl-3">
                                <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 350px; margin-bottom: 25px;">
                                    <center>
                                        <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                            QCM Niveau Senior</h5>
                                    </center>
                                    <div id="qcm_senior"
                                        class="d-flex align-items-center justify-content-center w-100"
                                            style="height: 100%; width: 100%; margin-top: 10px;"></div>
                                </div>
                            </div>
                            <!-- Card 3 -->
                            <div class="col-xl-3">
                                <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 350px; margin-bottom: 25px;">
                                    <center>
                                        <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                            QCM Niveau Expert</h5>
                                    </center>
                                    <div id="qcm_expert"
                                        class="d-flex align-items-center justify-content-center w-100"
                                            style="height: 100%; width: 100%; margin-top: 10px;"></div>
                                </div>
                            </div>
                            <!-- Card 4 -->
                            <div class="col-xl-3">
                                <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 350px; margin-bottom: 25px;">
                                    <center>
                                        <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                            Total : 03 Niveaux</h5>
                                    </center>
                                    <div id="qcm_total"
                                        class="d-flex align-items-center justify-content-center w-100"
                                            style="height: 100%; width: 100%; margin-top: 10px;"></div>
                                </div>
                            </div>
                        </div>
                        <!-- End::Row -->
                    </div>
                </div>
            <?php } ?>
            <?php if ($_SESSION["profile"] == "Admin" || $_SESSION["profile"] == "Ressource Humaine" || $_SESSION["profile"] == "Directeur Pièce et Service" || $_SESSION["profile"] == "Directeur des Opérations" || $_SESSION["profile"] == "Directeur Général") { ?>
                <!--begin::Col-->

                <!--begin::Title-->
                <div class="mt-5 title-full" style="margin-bottom: -20px;">
                    <div class="card shadow-sm mb-5 w-100">
                        <div class="card-body p-4">
                            <h6 class="text-dark fw-bold text-center fs-2">
                                Effectif de la Filiale
                            </h6>
                        </div>
                    </div>
                </div>
                <!--end::Title-->
                <!-- Ligne des 4 cards -->
                <div class="row row-cols-xxl-4 row-cols-lg-4 row-cols-md-2 g-6 mb-0">
                    <!-- 1) JUNIOR -->
                    <div class="col">
                        <div class="card h-auto glass-effect-card depth-effect">
                            <div class="card-body text-center py-3 px-3">
                                <div class="fs-2x fw-bold text-gray-800"
                                    data-kt-countup="true"
                                    data-kt-countup-value="<?= count($techniciansJu) ?>">
                                </div>
                                <div class="fw-semibold mt-2">
                                    <?= $technicienss . ' ' . $Level . ' ' . $junior ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- 2) SENIOR -->
                    <div class="col">
                        <div class="card h-auto glass-effect-card depth-effect">
                            <div class="card-body text-center py-3 px-3">
                                <div class="fs-2x fw-bold text-gray-800"
                                    data-kt-countup="true"
                                    data-kt-countup-value="<?= count($techniciansSe) ?>">
                                </div>
                                <div class="fw-semibold mt-2">
                                    <?= $technicienss . ' ' . $Level . ' ' . $senior ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- 3) EXPERT -->
                    <div class="col">
                        <div class="card h-auto glass-effect-card depth-effect">
                            <div class="card-body text-center py-3 px-3">
                                <div class="fs-2x fw-bold text-gray-800"
                                    data-kt-countup="true"
                                    data-kt-countup-value="<?= count($techniciansEx) ?>">
                                </div>
                                <div class="fw-semibold mt-2">
                                    <?= $technicienss . ' ' . $Level . ' ' . $expert ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- 4) FILIALES -->
                    <div class="col">
                        <div class="card h-auto glass-effect-card depth-effect">
                            <div class="card-body text-center py-3 px-3">
                                <div class="fs-2x fw-bold text-gray-800"
                                    data-kt-countup="true"
                                    data-kt-countup-value="<?= count($techniciansFi) ?>">
                                </div>
                                <div class="fw-semibold mt-2">
                                    <?= $technicienss . ' ' . $subsidiary ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php if ($_SESSION['country'] === 'Madagascar'): ?>
                    <?php
                    // Nombre de marques à afficher dans le compteur
                    if ($selectedBrand === '') {
                        // pas de filtre → on reste sur le nombre total
                        $brandCount = count($uniqueBrands);
                        $brandLabel = $brandCount > 1 ? 'Marques Filiales' : 'Marque Filiale';
                    } else {
                        // filtre sur une seule marque
                        $brandCount = 1;
                        $brandLabel = 'Marque Filiale sélectionnée';
                    }
                    ?>
                    <!-- Nouvelle ligne, filtre Madagacar uniquement -->
                    <div class="row mt-3">
                        <div class="
        col-12
        col-md-6 offset-md-6
        col-lg-4 offset-lg-8
        col-xxl-3 offset-xxl-9
      ">
                            <div class="card h-auto">
                                <div class="card-body text-center py-3 px-3">
                                    <div class="fs-2x fw-bold text-gray-800"
                                        data-kt-countup="true"
                                        data-kt-countup-value="<?= $brandCount ?>">
                                    </div>
                                    <div class="fw-semibold mt-2">
                                        <?= $brandLabel ?>
                                    </div>

                                    <select id="brand-select"
                                        class="form-select form-select-solid"
                                        data-control="select2"
                                        data-hide-search="true"
                                        onchange="applyBrandFilter()">

                                        <option value="" <?= $selectedBrand === '' ? 'selected' : '' ?>>
                                            Toutes les marques
                                        </option>
                                        <?php foreach ($uniqueBrands as $brand): ?>
                                            <option value="<?= htmlspecialchars($brand) ?>"
                                                <?= $selectedBrand === $brand ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($brand) ?>
                                            </option>
                                        <?php endforeach; ?>

                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>



                <br><br><br>


                <!--end::Col-->
                <!--begin::Title-->
                <div class="mt-5 title-full" style="margin-top: 20px;">
                    <div class="card shadow-sm mb-5 w-100">
                        <div class="card-body p-4">
                            <h6 class="text-dark fw-bold text-center fs-2">
                                <?php echo $result_mesure_competence_filiale ?>
                            </h6>
                        </div>
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
                                <h4 style=" margin-top: 30px; color: white"><?php echo $result_entre_0_60 ?></h4>
                            </div>

                            <!-- Group 2 -->
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <canvas id='c2' width="75" height="37.5"></canvas>
                                <h4 style=" margin-top: 30px; color: white"><?php echo $result_entre_60_80 ?></h4>
                            </div>

                            <!-- Group 3 -->
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <canvas id='c3' width="75" height="37.5"></canvas>
                                <h4 style=" margin-top: 30px; color: white"><?php echo $result_entre_80_100 ?></h4>
                            </div>
                        </fieldset>
                    </div>
                </div>
                <!-- end::Row -->
                <?php if ($_SESSION['profile'] == 'Directeur des Opérations' || $_SESSION['profile'] == 'Directeur Pièce et Service' || $_SESSION["profile"] == "Directeur Général") { ?>

                    <!--begin::Title-->
                    <div class="mt-5 title-full">
                        <div class="card shadow-sm mb-5 w-100">
                            <div class="card-body p-5">
                                <h6 class="text-dark fw-bold text-center fs-2">
                                    Détails de la maîtrise par Connaissances théoriques et Tâches Professionnelles
                                </h6>
                            </div>
                        </div>
                    </div>

                    <!-- Begin::Row -->
                    <div class="row">
                        <!-- Card 1 -->
                        <div class="col-xl-3">
                            <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 350px; margin-bottom: 25px;">
                                <center>
                                    <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                        Résultats Niveau Junior</h5>
                                </center>
                                <div id="result_junior_filiale"
                                    class="d-flex align-items-center justify-content-center w-100"
                                            style="height: 100%; width: 100%; margin-top: 10px;"></div>
                            </div>
                        </div>
                        <!-- Card 2 -->
                        <div class="col-xl-3">
                            <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 350px; margin-bottom: 25px;">
                                <center>
                                    <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                        Résultats Niveau Senior</h5>
                                </center>
                                <div id="result_senior_filiale"
                                    class="d-flex align-items-center justify-content-center w-100"
                                            style="height: 100%; width: 100%; margin-top: 10px;"></div>
                            </div>
                        </div>
                        <!-- Card 3 -->
                        <div class="col-xl-3">
                            <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 350px; margin-bottom: 25px;">
                                <center>
                                    <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                        Résultats Niveau Expert</h5>
                                </center>
                                <div id="result_expert_filiale"
                                    class="d-flex align-items-center justify-content-center w-100"
                                            style="height: 100%; width: 100%; margin-top: 10px;"></div>
                            </div>
                        </div>
                        <!-- Card 4 -->
                        <div class="col-xl-3">
                            <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 350px; margin-bottom: 25px;">
                                <center>
                                    <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                        Total : 03 Niveaux</h5>
                                </center>
                                <div id="result_total_filiale"
                                    class="d-flex align-items-center justify-content-center w-100"
                                            style="height: 100%; width: 100%; margin-top: 10px;"></div>
                            </div>
                        </div>
                    </div>
                    <!-- End::Row -->
                    <br><br>

                    <!--begin::Title-->
                    <div class="mt-5 title-full">
                        <div class="card shadow-sm mb-5 w-100">
                            <div class="card-body p-4">
                                <h6 class="text-dark fw-bold text-center fs-2">
                                    <?php echo $result_mesure_competence_filiale_niveau ?>
                                </h6>
                            </div>
                        </div>
                    </div>
                    <!--end::Title-->
                    <!-- Card 1 -->
                    <div class="col-xl-3">
                        <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 350px; margin-bottom: 25px;">
                            <center>
                                <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                    Résultats Niveau Junior</h5>
                            </center>
                            <div id="chart_junior_filiale"
                                class="d-flex align-items-center justify-content-center w-100"
                                            style="height: 100%; width: 100%; margin-top: 10px;"></div>
                        </div>
                    </div>

                    <!-- Card 2 -->
                    <div class="col-xl-3">
                        <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 350px; margin-bottom: 25px;">
                            <center>
                                <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                    Résultats Niveau Senior</h5>
                            </center>
                            <div id="chart_senior_filiale"
                                class="d-flex align-items-center justify-content-center w-100"
                                            style="height: 100%; width: 100%; margin-top: 10px;"></div>
                        </div>
                    </div>

                    <!-- Card 3 -->
                    <div class="col-xl-3">
                        <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 350px; margin-bottom: 25px;">
                            <center>
                                <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                    Résultats Niveau Expert</h5>
                            </center>
                            <div id="chart_expert_filiale"
                                class="d-flex align-items-center justify-content-center w-100"
                                            style="height: 100%; width: 100%; margin-top: 10px;"></div>
                        </div>
                    </div>

                    <!-- Card 4 -->
                    <div class="col-xl-3">
                        <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 350px; margin-bottom: 25px;">
                            <center>
                                <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                    Total : 03 Niveaux</h5>
                            </center>
                            <div id="chart_total_filiale"
                                class="d-flex align-items-center justify-content-center w-100"
                                            style="height: 100%; width: 100%; margin-top: 10px;"></div>
                        </div>
                    </div>
                <?php } ?>
                <?php if ($_SESSION['profile'] == 'Directeur des Opérations' || $_SESSION['profile'] == 'Directeur Pièce et Service' || $_SESSION['profile'] == 'Admin' || $_SESSION['profile'] == 'Ressource Humaine') { ?>
                    <!--begin::Title-->
                    <div class="mt-5 title-full">
                        <div class="card shadow-sm mb-5 w-100">
                            <div class="card-body p-4">
                                <h6 class="text-dark fw-bold text-center fs-2">
                                    <?php echo $etat_avanacement_test_filiale ?>
                                </h6>
                            </div>
                        </div>
                    </div>
                    <!--end::Title-->
                    <!-- begin::Row -->
                    <div>
                        <div id="chartTestFiliale" class="row">
                            <!-- Dynamic cards will be appended here -->
                        </div>
                    </div>
                    <!-- end::Row -->
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
                                    <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 350px; margin-bottom: 25px;">
                                        <center>
                                            <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                QCM Niveau Junior</h5>
                                        </center>
                                        <div id="qcm_junior_filiale"
                                            class="d-flex align-items-center justify-content-center w-100"
                                            style="height: 100%; width: 100%; margin-top: 10px;"></div>
                                    </div>
                                </div>
                                <!-- Card 2 -->
                                <div class="col-xl-3">
                                    <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 350px; margin-bottom: 25px;">
                                        <center>
                                            <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                QCM Niveau Senior</h5>
                                        </center>
                                        <div id="qcm_senior_filiale"
                                            class="d-flex align-items-center justify-content-center w-100"
                                            style="height: 100%; width: 100%; margin-top: 10px;"></div>
                                    </div>
                                </div>
                                <!-- Card 3 -->
                                <div class="col-xl-3">
                                    <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 350px; margin-bottom: 25px;">
                                        <center>
                                            <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                QCM Niveau Expert</h5>
                                        </center>
                                        <div id="qcm_expert_filiale"
                                            class="d-flex align-items-center justify-content-center w-100"
                                            style="height: 100%; width: 100%; margin-top: 10px;"></div>
                                    </div>
                                </div>
                                <!-- Card 4 -->
                                <div class="col-xl-3">
                                    <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 350px; margin-bottom: 25px;">
                                        <center>
                                            <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                Total : 03 Niveaux</h5>
                                        </center>
                                        <div id="qcm_total_filiale"
                                            class="d-flex align-items-center justify-content-center w-100"
                                            style="height: 100%; width: 100%; margin-top: 10px;"></div>
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
<?php if ($_SESSION["profile"] == "Super Admin") { ?>

        <div class="container-xxl">
           <div class="card shadow-sm mb-5">
                <div class="card-body py-3">
                    <h1 class="text-dark fw-bold text-center fs-1 m-0">
                       <?php echo $tableau ?>
                    </h1>
                </div>
            </div>
        </div>
    <!--end::Toolbar-->
    <!--begin::Content-->

        <!--begin::Post-->
        <div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
            <!--begin::Container-->
            <div class=" container-xxl ">
                <!--begin::Row-->
                <div class="row g-6 g-xl-9 mb-6 mb-xl-9">
                    <!--begin::Col-->
                    <div class="col-md-6 col-lg-4 col-xl-2.5">
                        <!--begin::Card-->
                        <div class="card h-100 glass-effect-chart depth-effect">
                            <!--begin::Card body-->
                            <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                <!--begin::Name-->
                                <!--begin::Animation-->
                                <div
                                    class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                    <div class="min-w-70px" data-kt-countup="true"
                                        data-kt-countup-value="<?php echo count($countUsers) ?>">
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
                        <div class="card h-100 glass-effect-chart depth-effect">
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
                        <div class="card h-100 glass-effect-chart depth-effect">
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
                        <div class="card h-100 glass-effect-chart depth-effect">
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
                        <div class="card h-100 glass-effect-chart depth-effect">
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
                        <div class="card h-100 glass-effect-chart depth-effect">
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
                    <div class="my-5 title-full">
                        <div class="card shadow-sm mb-5 ">
                            <div class="card-body p-4 ">
                                <h6 class="text-dark fw-bold text-center my-1 fs-2">
                                    <?php echo $result_mesure_competence_groupe ?>
                                </h6>
                            </div>
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
                                    <h4 style=" margin-top: 30px; color: white"><?php echo $result_entre_0_60 ?></h4>
                                </div>

                                <!-- Group 2 -->
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <canvas id='c2' width="75" height="37.5"></canvas>
                                    <h4 style=" margin-top: 30px; color: white"><?php echo $result_entre_60_80 ?></h4>
                                </div>

                                <!-- Group 3 -->
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <canvas id='c3' width="75" height="37.5"></canvas>
                                    <h4 style=" margin-top: 30px; color: white"><?php echo $result_entre_80_100 ?></h4>
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
                                    <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 350px; margin-bottom: 25px;">
                                        <center>
                                            <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                Résultats Niveau Junior</h5>
                                        </center>
                                        <div id="result_junior"
                                            class="d-flex align-items-center justify-content-center w-100"
                                            style="height: 100%; width: 100%; margin-top: 10px;"></div>
                                    </div>
                                </div>
                                <!-- Card 2 -->
                                <div class="col-xl-3">
                                    <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 350px; margin-bottom: 25px;">
                                        <center>
                                            <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                Résultats Niveau Senior</h5>
                                        </center>
                                        <div id="result_senior"
                                            class="d-flex align-items-center justify-content-center w-100"
                                            style="height: 100%; width: 100%; margin-top: 10px;"></div>
                                    </div>
                                </div>
                                <!-- Card 3 -->
                                <div class="col-xl-3">
                                    <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 350px; margin-bottom: 25px;">
                                        <center>
                                            <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                Résultats Niveau Expert</h5>
                                        </center>
                                        <div id="result_expert"
                                            class="d-flex align-items-center justify-content-center w-100"
                                            style="height: 100%; width: 100%; margin-top: 10px;"></div>
                                    </div>
                                </div>
                                <!-- Card 4 -->
                                <div class="col-xl-3">
                                    <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 350px; margin-bottom: 25px;">
                                        <center>
                                            <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                Total : 03 Niveaux</h5>
                                        </center>
                                        <div id="result_total"
                                            class="d-flex align-items-center justify-content-center w-100"
                                            style="height: 100%; width: 100%; margin-top: 10px;"></div>
                                    </div>
                                </div>
                            </div>
                            <!-- End::Row -->
                        </div>
                    </div>
                    <!--begin::Title-->
                    <div class="my-5 title-full">
                        <div class="card shadow-sm mb-5">
                            <div class="card-body p-4">
                                <h6 class="text-dark fw-bold text-center my-1 fs-2">
                                    <?php echo $result_mesure_competence_niveau ?>
                                </h6>
                            </div>
                        </div>
                    </div>
                    <!--end::Title-->
                    <!-- Card 1 -->
                    <div class="col-xl-3">
                        <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 350px; margin-bottom: 25px;">
                            <center>
                                <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                    Résultats Niveau Junior</h5>
                            </center>
                            <div id="chart_junior"
                                class="d-flex align-items-center justify-content-center w-100"
                                            style="height: 100%; width: 100%; margin-top: 10px;"></div>
                        </div>
                    </div>

                    <!-- Card 2 -->
                    <div class="col-xl-3">
                        <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 350px; margin-bottom: 25px;">
                            <center>
                                <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                    Résultats Niveau Senior</h5>
                            </center>
                            <div id="chart_senior"
                                class="d-flex align-items-center justify-content-center w-100"
                                            style="height: 100%; width: 100%; margin-top: 10px;"></div>
                        </div>
                    </div>

                    <!-- Card 3 -->
                    <div class="col-xl-3">
                        <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 350px; margin-bottom: 25px;">
                            <center>
                                <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                    Résultats Niveau Expert</h5>
                            </center>
                            <div id="chart_expert"
                                class="d-flex align-items-center justify-content-center w-100"
                                            style="height: 100%; width: 100%; margin-top: 10px;"></div>
                        </div>
                    </div>

                    <!-- Card 4 -->
                    <div class="col-xl-3">
                        <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 350px; margin-bottom: 25px;">
                            <center>
                                <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                    Total : 03 Niveaux</h5>
                            </center>
                            <div id="chart_total"
                                class="d-flex align-items-center justify-content-center w-100"
                                            style="height: 100%; width: 100%; margin-top: 10px;"></div>
                        </div>
                    </div>
                    <!--begin::Title-->
                    <div class="my-5 title-full">
                        <div class="card shadow-sm mb-5">
                            <div class="card-body p-4">
                                <h6 class="text-dark fw-bold text-center my-1 fs-2">
                                    <?php echo $etat_avanacement_test_realises_groupe ?>
                                </h6>
                            </div>
                        </div>
                    </div>
                    <!--end::Title-->
                    <!-- begin::Row -->
                    <div>
                        <div id="chartTest" class="row">
                            <!-- Dynamic cards will be appended here -->
                        </div>
                    </div>
                    <!-- end::Row -->
                    <!-- Dropdown Toggle Button -->
                    <div class="dropdown-container">
                        <button class="dropdown-toggle" style="color: black">Plus de détails sur les tests
                            <i class="fas fa-chevron-down"></i></button>
                        <!-- Hidden Content -->
                        <div class="dropdown-content">
                            <!--begin::Title-->
                            <div class="my-5 title-full">
                                <div class="card shadow-sm mb-5">
                                    <div class="card-body p-4">
                                        <h6 class="text-dark fw-bold text-center my-1 fs-2">
                                            <?php echo $etat_avanacement_qcm_realises_groupe ?>
                                        </h6>
                                    </div>
                                </div>
                            </div>
                            <!--end::Title-->
                            <!-- Begin::Row -->
                            <div class="row">
                                <!-- Card 1 -->
                                <div class="col-xl-3">
                                    <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 350px; margin-bottom: 25px;">
                                        <center>
                                            <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                QCM Niveau Junior</h5>
                                        </center>
                                        <div id="qcm_junior"
                                            class="d-flex align-items-center justify-content-center w-100"
                                            style="height: 100%; width: 100%; margin-top: 10px;"></div>
                                    </div>
                                </div>
                                <!-- Card 2 -->
                                <div class="col-xl-3">
                                    <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 350px; margin-bottom: 25px;">
                                        <center>
                                            <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                QCM Niveau Senior</h5>
                                        </center>
                                        <div id="qcm_senior"
                                            class="d-flex align-items-center justify-content-center w-100"
                                            style="height: 100%; width: 100%; margin-top: 10px;"></div>
                                    </div>
                                </div>
                                <!-- Card 3 -->
                                <div class="col-xl-3">
                                    <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 350px; margin-bottom: 25px;">
                                        <center>
                                            <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                QCM Niveau Expert</h5>
                                        </center>
                                        <div id="qcm_expert"
                                            class="d-flex align-items-center justify-content-center w-100"
                                            style="height: 100%; width: 100%; margin-top: 10px;"></div>
                                    </div>
                                </div>
                                <!-- Card 4 -->
                                <div class="col-xl-3">
                                    <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 350px; margin-bottom: 25px;">
                                        <center>
                                            <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                                Total : 03 Niveaux</h5>
                                        </center>
                                        <div id="qcm_total"
                                            class="d-flex align-items-center justify-content-center w-100"
                                            style="height: 100%; width: 100%; margin-top: 10px;"></div>
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

</div>
</div>
<!--end::Content-->
<?php include "./partials/footer.php"; ?>
<?php
    closeBackgroundContainer();
}
?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/apexcharts/3.50.0/apexcharts.min.js"
    integrity="sha512-h3DSSmgtvmOo5gm3pA/YcDNxtlAZORKVNAcMQhFi3JJgY41j9G06WsepipL7+l38tn9Awc5wgMzJGrUWaeUEGA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
    function applyBrandFilter() {
        const brand = document.getElementById('brand-select').value;
        const url = new URL(window.location);
        brand ? url.searchParams.set('brand', brand) :
            url.searchParams.delete('brand');
        window.location = url.toString();
    }

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
                    data: [statJu.nonMaitrise, statJu.singleMaitrise, statJu.doubleMaitrise, statJu.othersCount], // Test réalisés vs. Test to be completed
                    labels: ['<?php echo $legend_zero_mastery; ?>', '<?php echo $legend_one_mastery; ?>', '<?php echo $legend_two_mastery; ?>', '<?php echo $legend_more_mastery; ?>'],
                    backgroundColor: [
                        '#d3d3d3', // Gris pour "Aucun technicien maîtrise"
                        '#fddde6', // Variante claire pour "1 seul technicien maîtrise"
                        '#f8d7da', // Couleur pour "Seuls 2 techniciens maîtrisent"
                        '#f5c6cb' // Couleur pour "Plus de 3 techniciens maîtrisent"
                    ]
                },
                {
                    title: '<?php echo $senior_tp; ?>',
                    type: ['nonMaitrise', 'singleMaitrise', 'doubleMaitrise', 'others'],
                    total: statSe.totalQuestions,
                    percentage: [percentageSe.nonMaitrise, percentageSe.singleMaitrise, percentageSe.doubleMaitrise, percentageSe.others], // Test réalisés
                    data: [statSe.nonMaitrise, statSe.singleMaitrise, statSe.doubleMaitrise, statSe.othersCount], // Test réalisés vs. Test to be completed
                    labels: ['<?php echo $legend_zero_mastery; ?>', '<?php echo $legend_one_mastery; ?>', '<?php echo $legend_two_mastery; ?>', '<?php echo $legend_more_mastery; ?>'],
                    backgroundColor: [
                        '#d3d3d3', // Gris pour "Aucun technicien maîtrise"
                        '#fddde6', // Variante claire pour "1 seul technicien maîtrise"
                        '#f8d7da', // Couleur pour "Seuls 2 techniciens maîtrisent"
                        '#f5c6cb' // Couleur pour "Plus de 3 techniciens maîtrisent"
                    ]
                },
                {
                    title: '<?php echo $expert_tp; ?>',
                    type: ['nonMaitrise', 'singleMaitrise', 'doubleMaitrise', 'others'],
                    total: statEx.totalQuestions,
                    percentage: [percentageEx.nonMaitrise, percentageEx.singleMaitrise, percentageEx.doubleMaitrise, percentageEx.others], // Test réalisés
                    data: [statEx.nonMaitrise, statEx.singleMaitrise, statEx.doubleMaitrise, statEx.othersCount], // Test réalisés vs. Test to be completed
                    labels: ['<?php echo $legend_zero_mastery; ?>', '<?php echo $legend_one_mastery; ?>', '<?php echo $legend_two_mastery; ?>', '<?php echo $legend_more_mastery; ?>'],
                    backgroundColor: [
                        '#d3d3d3', // Gris pour "Aucun technicien maîtrise"
                        '#fddde6', // Variante claire pour "1 seul technicien maîtrise"
                        '#f8d7da', // Couleur pour "Seuls 2 techniciens maîtrisent"
                        '#f5c6cb' // Couleur pour "Plus de 3 techniciens maîtrisent"
                    ]
                },
                {
                    title: '<?php echo $total_tp; ?>',
                    type: ['nonMaitrise', 'singleMaitrise', 'doubleMaitrise', 'others'],
                    total: statTo.totalQuestions,
                    percentage: [percentageTo.nonMaitrise, percentageTo.singleMaitrise, percentageTo.doubleMaitrise, percentageTo.others], // Test réalisés
                    data: [statTo.nonMaitrise, statTo.singleMaitrise, statTo.doubleMaitrise, statTo.othersCount], // Test réalisés vs. Test to be completed
                    labels: ['<?php echo $legend_zero_mastery; ?>', '<?php echo $legend_one_mastery; ?>', '<?php echo $legend_two_mastery; ?>', '<?php echo $legend_more_mastery; ?>'],
                    backgroundColor: [
                        '#d3d3d3', // Gris pour "Aucun technicien maîtrise"
                        '#fddde6', // Variante claire pour "1 seul technicien maîtrise"
                        '#f8d7da', // Couleur pour "Seuls 2 techniciens maîtrisent"
                        '#f5c6cb' // Couleur pour "Plus de 3 techniciens maîtrisent"
                    ]
                }
            ];

            const container = document.getElementById('chartGF');

            // Loop through the data to create and append cards
            chartData.forEach((data, index) => {
                // Calculate the completed percentage
                const completedPercentage = Math.round((data.completed / data.total) * 100);
        
                // Create the card element
                const cardHtml = `
                        <div class="col-md-6 col-lg-3 col-xl-2.5 mb-4">
                            <div class="card h-100 glass-effect-chart depth-effect">
                                <div class="card-body d-flex justify-content-center text-center flex-column p-4">
                                    <h5 class="mb-4">${data.title}</h5>
                                    <div style="position: relative; display: flex; justify-content: center; align-items: center;">
                                        <canvas id="doughnutChart${index}" width="200" height="200"></canvas>
                                        <div style="position: absolute; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                            <span class="fs-2hx fw-bold text-dark">${completedPercentage}%</span>
                                        </div>
                                    </div>
                                    <h6 class="mt-4 text-gray-600">Nombres de Tâches Professionnelles: ${data.total}</h6>
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
                                    var agency = '<?php echo urlencode($selectedAgency); ?>'; // L'agence sélectionnée

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
                    title: 'Test Niveau Junior',
                    total: <?php echo count($testsTotalJu) ?>,
                    completed: <?php echo count($testsUserJu) ?>, // Test réalisés
                    data: [<?php echo count($testsUserJu) ?>,
                        <?php echo (count($testsTotalJu) - count($testsUserJu)) ?>
                    ], // Test réalisés vs. Test to be completed
                    labels: ['Tests Complétés', 'Tests à Compléter'],
                    backgroundColor: ['#4303ec', '#D3D3D3'] // Blue and Lightgrey
                },
                {
                    title: 'Test Niveau Senior',
                    total: <?php echo count($testsTotalSe) ?>,
                    completed: <?php echo count($testsUserSe) ?>, // Test réalisés
                    data: [<?php echo count($testsUserSe) ?>,
                        <?php echo (count($testsTotalSe) - count($testsUserSe)) ?>
                    ], // Test réalisés vs. Test to be completed
                    labels: ['Tests Complétés', 'Tests à Compléter'],
                    backgroundColor: ['#4303ec', '#D3D3D3'] // Blue and Lightgrey
                },
                {
                    title: 'Test Niveau Expert',
                    total: <?php echo count($testsTotalEx) ?>,
                    completed: <?php echo count($testsUserEx) ?>, // Test réalisés
                    data: [<?php echo count($testsUserEx) ?>,
                        <?php echo (count($testsTotalEx) - count($testsUserEx)) ?>
                    ], // Test réalisés vs. Test to be completed
                    labels: ['Tests Complétés', 'Tests à Compléter'],
                    backgroundColor: ['#4303ec', '#D3D3D3'] // Blue and Lightgrey
                },
                {
                    title: 'Total : 03 Niveaux',
                    total: <?php echo count($testsTotalJu) + count($testsTotalSe) + count($testsTotalEx) ?>,
                    completed: <?php echo count($testsUserJu) + count($testsUserSe) + count($testsUserEx) ?>, // Test réalisés
                    data: [<?php echo count($testsUserJu) + count($testsUserSe) + count($testsUserEx) ?>,
                        <?php echo (count($testsTotalJu) + count($testsTotalSe) + count($testsTotalEx)) - (count($testsUserJu) + count($testsUserSe) + count($testsUserEx)) ?>
                    ], // Test réalisés vs. Test to be completed
                    labels: ['Tests Complétés', 'Tests à Compléter'],
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
                            <div class="card h-100 glass-effect-chart depth-effect">
                                <div class="card-body d-flex justify-content-center text-center flex-column p-4">
                                    <h5 class="mb-4">${data.title}</h5>
                                    <div style="position: relative; display: flex; justify-content: center; align-items: center;">
                                        <canvas id="doughnutChart${index}" width="200" height="200"></canvas>
                                        <div style="position: absolute; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                            <span class="fs-2hx fw-bold text-dark">${completedPercentage}%</span>
                                        </div>
                                    </div>
                                    <h6 class="mt-4 text-gray-600">Total à réaliser: ${data.total}</h6>
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
                if (percentage === 0) return ['#FFFFFF']; // All white if 0%
                return [
                    getColorForCompletion(percentage), // Color for the completed part
                    '#DCDCDC' // Grey color for the remaining part
                ];
            };

            // Data for each chart
            const chartDataM = [{
                    title: 'Résultat <?php echo count($doneTestJuTj) ?> / <?php echo count($countUsersJu) ?> Techniciens Niveau Junior',
                    total: 100,
                    completed: <?php echo round(($percentageFacJuTj + $percentageDeclaJuTj) / 2) ?>, // Moyenne Compétences acquises
                    data: [<?php echo round(($percentageFacJuTj + $percentageDeclaJuTj) / 2) ?>,
                        100 -
                        <?php echo round(($percentageFacJuTj + $percentageDeclaJuTj) / 2) ?>
                    ], // Moyenne Compétences acquises vs. Moyenne Compétences à acquérir
                    labels: [
                        '<?php echo round(($percentageFacJuTj + $percentageDeclaJuTj) / 2) ?>%  Compétences Acquises',
                        '<?php echo 100 - round(($percentageFacJuTj + $percentageDeclaJuTj) / 2) ?>% Compétences à acquérir'
                    ],
                    backgroundColor: getBackgroundColor(<?php echo round(($percentageFacJuTj + $percentageDeclaJuTj) / 2) ?>)
                },
                {
                    title: 'Résultat <?php echo count($doneTestSeTs) ?> / <?php echo count($countUsersSe) ?> Techniciens Niveau Senior',
                    total: 100,
                    completed: <?php echo round(($percentageFacSeTs + $percentageDeclaSeTs) / 2) ?>, // Moyenne des Compétences Acquises
                    data: [<?php echo round(($percentageFacSeTs + $percentageDeclaSeTs) / 2) ?>,
                        100 -
                        <?php echo round(($percentageFacSeTs + $percentageDeclaSeTs) / 2) ?>
                    ], // Moyenne Compétences acquises vs. Moyenne Compétences à acquérir
                    labels: [
                        '<?php echo round(($percentageFacSeTs + $percentageDeclaSeTs) / 2) ?>% Compétences acquises',
                        '<?php echo 100 - round(($percentageFacSeTs + $percentageDeclaSeTs) / 2) ?>% Compétences à acquérir'
                    ],
                    backgroundColor: getBackgroundColor(<?php echo round(($percentageFacSeTs + $percentageDeclaSeTs) / 2) ?>)
                },
                {
                    title: 'Résultat <?php echo count($doneTestExTx) ?> / <?php echo count($countUsersEx) ?> Techniciens Niveau Expert',
                    total: 100,
                    completed: <?php echo round(($percentageFacEx + $percentageDeclaEx) / 2) ?>, // Moyenne Compétences acquises
                    data: [<?php echo round(($percentageFacEx + $percentageDeclaEx) / 2) ?>,
                        100 -
                        <?php echo round(($percentageFacEx + $percentageDeclaEx) / 2) ?>
                    ], // Moyenne Compétences acquises vs. Moyenne Compétences à acquérir
                    labels: [
                        '<?php echo round(($percentageFacEx + $percentageDeclaEx) / 2) ?>% Compétences acquises',
                        '<?php echo 100 - round(($percentageFacEx + $percentageDeclaEx) / 2) ?>% Compétences à acquérir'
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
                title: 'Résultat <?php echo count($doneTestJuTj) + count($doneTestSeTs) + count($doneTestExTx) ?> / <?php echo count($countUsers) ?> Techniciens Total : 03 Niveaux',
                total: 100,
                completed: averageCompleted,
                data: averageData,
                labels: [
                    `${averageCompleted}% Compétences acquises`,
                    `${100 - averageCompleted}% Compétences à acquérir`
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
                            <div class="card h-100 glass-effect-chart depth-effect">
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
                                        return `Compétences Acquises: ${percentage}%`;
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
        var juniorScore = [factMasteryData.Junior, tpMasteryData.Junior, <?php echo round(($percentageFacJuTj + $percentageDeclaJuTj) / 2) ?>]; // Replace with actual junior data
        var seniorScore = [factMasteryData.Senior, tpMasteryData.Senior, <?php echo round(($percentageFacSeTs + $percentageDeclaSeTs) / 2) ?>]; // Replace with actual senior data
        var expertScore = [factMasteryData.Expert, tpMasteryData.Expert, <?php echo round(($percentageFacEx + $percentageDeclaEx) / 2) ?>]; // Replace with actual expert data
        var averageScore = [averageCompleted(<?php echo round($percentageFacJuTj) ?>, <?php echo round($percentageFacSeTs) ?>, <?php echo round($percentageFacEx) ?>), averageCompleted(<?php echo round($percentageDeclaJuTj) ?>, <?php echo round($percentageDeclaSeTs) ?>, <?php echo round($percentageDeclaEx) ?>), averageCompleted(<?php echo round(($percentageFacJuTj + $percentageDeclaJuTj) / 2) ?>, <?php echo round(($percentageFacSeTs + $percentageDeclaSeTs) / 2) ?>, <?php echo round(($percentageFacEx + $percentageDeclaEx) / 2) ?>)]; // Replace with actual expert data

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
        var labelQ = ['Connaissances', 'Tâches Professionnelles', 'Compétences'];

        function completedPercentage(completed, total) {
            let moyen = Math.round((completed / total) * 100);

            return moyen;
        }

        // Sample test data for Junior, Senior, and Expert levels
        var juniorScoreQ = [completedPercentage(<?php echo count($countSavoirJu) ?>, <?php echo count($countUsers) ?>), completedPercentage(<?php echo count($countSavFaiJu) ?>, <?php echo count($countUsers) ?>), completedPercentage(<?php echo count($testsUserJu) ?>, <?php echo count($testsTotalJu) ?>)]; // Replace with actual junior data
        var seniorScoreQ = [completedPercentage(<?php echo count($countSavoirSe) ?>, <?php echo count($countUsersSe) + count($countUsersEx) ?>), completedPercentage(<?php echo count($countSavFaiSe) ?>, <?php echo count($countUsersSe) + count($countUsersEx) ?>), completedPercentage(<?php echo count($testsUserSe) ?>, <?php echo count($testsTotalSe) ?>)]; // Replace with actual senior data
        var expertScoreQ = [completedPercentage(<?php echo count($countSavoirEx) ?>, <?php echo count($countUsersEx) ?>), completedPercentage(<?php echo count($countSavFaiEx) ?>, <?php echo count($countUsersEx) ?>), completedPercentage(<?php echo count($testsUserEx) ?>, <?php echo count($testsTotalEx) ?>)]; // Replace with actual expert data
        var averageScoreQ = [completedPercentage(<?php echo count($countSavoirJu) + count($countSavoirSe) + count($countSavoirEx) ?>, <?php echo count($countUsers) + count($countUsersSe)  + (count($countUsersEx) * 2) ?>), completedPercentage(<?php echo count($countSavFaiJu) + count($countSavFaiSe) + count($countSavFaiEx) ?>, <?php echo count($countUsers) + count($countUsersSe)  + (count($countUsersEx) * 2) ?>), completedPercentage(<?php echo count($testsUserJu) + count($testsUserSe) + count($testsUserEx) ?>, <?php echo count($testsTotalJu) + count($testsTotalSe) + count($testsTotalEx) ?>)]; // Replace with actual expert data

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
        var cityLabelJu = ['<?php echo count($countUsersJu) ?> Techniciens Junior', '<?php echo count($countUsersSe) ?> Techniciens Senior', '<?php echo count($countUsersEx) ?> Techniciens Expert'];
        var cityLabelSe = ['<?php echo count($countUsersSe) ?> Techniciens Senior', '<?php echo count($countUsersEx) ?> Techniciens Expert', ''];
        var cityLabelEx = ['<?php echo count($countUsersEx) ?> Techniciens Expert', '', ''];
        var cityLabels = ['Total Niveau Junior', 'Total Niveau Senior', 'Total Niveau Expert'];

        // Sample test data for Junior, Senior, and Expert levels
        var juniorScores = [<?php echo round(($percentageFacJuTj + $percentageDeclaJuTj) / 2) ?>, <?php echo round(($percentageFacJuTs + $percentageDeclaJuTs) / 2) ?>, <?php echo round(($percentageFacJuTx + $percentageDeclaJuTx) / 2) ?>]; // Replace with actual junior data
        var seniorScores = [<?php echo round(($percentageFacSeTs + $percentageDeclaSeTs) / 2) ?>, <?php echo round(($percentageFacSeTx + $percentageDeclaSeTx) / 2) ?>, 0]; // Replace with actual senior data
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
                    ], // Test réalisés vs. Test to be completed
                    labels: ['<?php echo count($testsJu) ?> Tests Complétés',
                        '<?php echo (count($testTotalJu)) - (count($testsJu)) ?> Tests à Compléter'
                    ],
                    backgroundColor: ['#4303ec', '#D3D3D3'] // Blue and Lightgrey
                },
                {
                    title: 'Test Niveau Senior',
                    total: <?php echo count($testTotalSe) ?>,
                    completed: <?php echo count($testsSe) ?>, // Test réalisés
                    data: [<?php echo count($testsSe) ?>,
                        <?php echo (count($testTotalSe) - count($testsSe)) ?>
                    ], // Test réalisés vs. Test to be completed
                    labels: ['<?php echo count($testsSe) ?> Tests Complétés',
                        '<?php echo (count($testTotalSe)) - (count($testsSe)) ?> Remaining Tests to Be Performed'
                    ],
                    backgroundColor: ['#4303ec', '#D3D3D3'] // Blue and Lightgrey
                },
                {
                    title: 'Test Niveau Expert',
                    total: <?php echo count($testTotalEx) ?>,
                    completed: <?php echo count($testsEx) ?>, // Test réalisés
                    data: [<?php echo count($testsEx) ?>,
                        <?php echo (count($testTotalEx) - count($testsEx)) ?>
                    ], // Test réalisés vs. Test to be completed
                    labels: ['<?php echo count($testsEx) ?> Tests Complétés',
                        '<?php echo (count($testTotalEx)) - (count($testsEx)) ?> Tests à Compléter'
                    ],
                    backgroundColor: ['#4303ec', '#D3D3D3'] // Blue and Lightgrey
                },
                {
                    title: 'Total : 03 Niveaux',
                    total: <?php echo count($testTotalJu) + count($testTotalSe) + count($testTotalEx) ?>,
                    completed: <?php echo count($testsJu) + count($testsSe) + count($testsEx) ?>, // Test réalisés
                    data: [<?php echo count($testsJu) + count($testsSe) + count($testsEx) ?>,
                        <?php echo (count($testTotalJu) + count($testTotalSe) + count($testTotalEx)) - (count($testsJu) + count($testsSe) + count($testsEx)) ?>
                    ], // Test réalisés vs. Test to be completed
                    labels: ['<?php echo count($testsJu) + count($testsSe) + count($testsEx) ?> Tests Complétés',
                        '<?php echo (count($testTotalJu) + count($testTotalSe) + count($testTotalEx)) - (count($testsJu) + count($testsSe) + count($testsEx)) ?> Tests à Compléter'
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
                            <div class="card h-100 glass-effect-chart depth-effect">
                                <div class="card-body d-flex justify-content-center text-center flex-column p-4">
                                    <h5 class="mb-4">${data.title}</h5>
                                    <div style="position: relative; display: flex; justify-content: center; align-items: center;">
                                        <canvas id="doughnutChart${index}" width="200" height="200"></canvas>
                                        <div style="position: absolute; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                            <span class="fs-2hx fw-bold text-dark">${completedPercentage}%</span>
                                        </div>
                                    </div>
                                    <h6 class="mt-4 text-gray-600">Total à réaliser: ${data.total}</h6>
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
            const chartDataM = [{
                    title: 'Résultat <?php echo count($doneTestJuTjF) ?> / <?php echo count($techniciansJu) ?> Techniciens Niveau Junior',
                    total: 100,
                    completed: <?php echo round(($percentageFacJuTjF + $percentageDeclaJuTjF) / 2) ?>, // Moyenne Compétences acquises
                    data: [<?php echo round(($percentageFacJuTjF + $percentageDeclaJuTjF) / 2) ?>,
                        100 -
                        <?php echo round(($percentageFacJuTjF + $percentageDeclaJuTjF) / 2) ?>
                    ], // Moyenne Compétences acquises vs. Moyenne Compétences à acquérir
                    labels: [
                        '<?php echo round(($percentageFacJuTjF + $percentageDeclaJuTjF) / 2) ?>% Compétences acquises',
                        '<?php echo 100 - round(($percentageFacJuTjF + $percentageDeclaJuTjF) / 2) ?>% Compétences à acquérir'
                    ],
                    backgroundColor: getBackgroundColor(<?php echo round(($percentageFacJuTjF + $percentageDeclaJuTjF) / 2) ?>)
                },
                {
                    title: 'Résultat <?php echo count($doneTestSeTsF) ?> / <?php echo count($techniciansSe) ?> Techniciens Niveau Senior',
                    total: 100,
                    completed: <?php echo round(($percentageFacSeTsF + $percentageDeclaSeTsF) / 2) ?>, // Moyenne Compétences acquises
                    data: [<?php echo round(($percentageFacSeTsF + $percentageDeclaSeTsF) / 2) ?>,
                        100 -
                        <?php echo round(($percentageFacSeTsF + $percentageDeclaSeTsF) / 2) ?>
                    ], // Moyenne Compétences acquises vs. Moyenne Compétences à acquérir
                    labels: [
                        '<?php echo round(($percentageFacSeTsF + $percentageDeclaSeTsF) / 2) ?>% Compétences acquises',
                        '<?php echo 100 - round(($percentageFacSeTsF + $percentageDeclaSeTsF) / 2) ?>% Compétences à acquérir'
                    ],
                    backgroundColor: getBackgroundColor(<?php echo round(($percentageFacSeTsF + $percentageDeclaSeTsF) / 2) ?>)
                },
                {
                    title: 'Résultat <?php echo count($doneTestExTxF) ?> / <?php echo count($techniciansEx) ?> Techniciens Niveau Expert',
                    total: 100,
                    completed: <?php echo round(($percentageFacExF + $percentageDeclaExF) / 2) ?>, // Moyenne Compétences acquises
                    data: [<?php echo round(($percentageFacExF + $percentageDeclaExF) / 2) ?>,
                        100 -
                        <?php echo round(($percentageFacExF + $percentageDeclaExF) / 2) ?>
                    ], // Moyenne Compétences acquises vs. Moyenne Compétences à acquérir
                    labels: [
                        '<?php echo round(($percentageFacExF + $percentageDeclaExF) / 2) ?>% Compétences acquises',
                        '<?php echo 100 - round(($percentageFacExF + $percentageDeclaExF) / 2) ?>% Compétences à acquérir'
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
                title: 'Résultat <?php echo count($doneTestJuTjF) + count($doneTestSeTsF) + count($doneTestExTxF) ?> / <?php echo count($techniciansFi) ?> Techniciens Total : 03 Niveaux',
                total: 100,
                completed: averageCompleted,
                data: averageData,
                labels: [
                    `${averageCompleted}% Compétences acquises`,
                    `${100 - averageCompleted}% Compétences à acquérir`
                ],
                backgroundColor: totalBackgroundColor
            });

            const containerM = document.getElementById('chartMoyenFiliale');

            // Loop through the data to create and append cards
            chartDataM.forEach((data, index) => {
                const completedPercentage = data.total === 0 ? 0 : Math.round((data.completed / data.total) * 100);
                // Create the card element
                const cardHtml = `
                        <div class="col-md-6 col-lg-3 col-xl-2.5 mb-4">
                            <div class="card h-100 glass-effect-chart depth-effect">
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
                            borderWidth: 0 // Remove the border

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
                                        return `Compétences Acquises: ${percentage}%`;
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
        var cityLabelJu = ['<?php echo count($techniciansJu) ?> Techniciens Junior', '<?php echo count($techniciansSe) ?> Techniciens Senior', '<?php echo count($techniciansEx) ?> Techniciens Expert'];
        var cityLabelSe = ['<?php echo count($techniciansSe) ?> Techniciens Senior', '<?php echo count($techniciansEx) ?> Techniciens Expert', ''];
        var cityLabelEx = ['<?php echo count($techniciansEx) ?> Techniciens Expert', '', ''];
        var cityLabels = ['Total Niveau Junior', 'Total Niveau Senior', 'Total Niveau Expert'];

        // Sample test data for Junior, Senior, and Expert levels
        var juniorScores = [<?php echo round(($percentageFacJuTjF + $percentageDeclaJuTjF) / 2) ?>, <?php echo round(($percentageFacJuTsF + $percentageDeclaJuTsF) / 2) ?>, <?php echo round(($percentageFacJuTxF + $percentageDeclaJuTxF) / 2) ?>]; // Replace with actual junior data
        var seniorScores = [<?php echo round(($percentageFacSeTsF + $percentageDeclaSeTsF) / 2) ?>, <?php echo round(($percentageFacSeTxF + $percentageDeclaSeTxF) / 2) ?>, 0]; // Replace with actual senior data
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
        var labelQ = ['Connaissances', 'Tâches Professionnelles', 'Compétences'];

        function completedPercentage(completed, total) {
            let moyen = Math.round((completed / total) * 100);

            return moyen;
        }

        // Sample test data for Junior, Senior, and Expert levels
        var juniorScoreQ = [completedPercentage(<?php echo count($countSavoirsJu) ?>, <?php echo count($techniciansFi) ?>), completedPercentage(<?php echo count($countSavFaisJu) ?>, <?php echo count($techniciansFi) ?>), completedPercentage(<?php echo count($testsJu) ?>, <?php echo count($testTotalJu) ?>)]; // Replace with actual junior data
        var seniorScoreQ = [completedPercentage(<?php echo count($countSavoirsSe) ?>, <?php echo count($techniciansSe) + count($techniciansEx) ?>), completedPercentage(<?php echo count($countSavFaisSe) ?>, <?php echo count($techniciansSe) + count($techniciansEx) ?>), completedPercentage(<?php echo count($testsSe) ?>, <?php echo count($testTotalSe) ?>)]; // Replace with actual senior data
        var expertScoreQ = [completedPercentage(<?php echo count($countSavoirsEx) ?>, <?php echo count($techniciansEx) ?>), completedPercentage(<?php echo count($countSavFaisEx) ?>, <?php echo count($techniciansEx) ?>), completedPercentage(<?php echo count($testsEx) ?>, <?php echo count($testTotalEx) ?>)]; // Replace with actual expert data
        var averageScoreQ = [completedPercentage(<?php echo count($countSavoirsJu) + count($countSavoirsSe) + count($countSavoirsEx) ?>, <?php echo count($techniciansFi) + count($techniciansSe)  + (count($techniciansEx) * 2) ?>), completedPercentage(<?php echo count($countSavFaisJu) + count($countSavFaisSe) + count($countSavFaisEx) ?>, <?php echo count($techniciansFi) + count($techniciansSe)  + (count($techniciansEx) * 2) ?>), completedPercentage(<?php echo count($testsJu) + count($testsSe) + count($testsEx) ?>, <?php echo count($testTotalJu) + count($testTotalSe) + count($testTotalEx) ?>)]; // Replace with actual expert data

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
        var juniorScore = [<?php echo round($percentageFacJuTjF) ?>, <?php echo round($percentageDeclaJuTjF) ?>, <?php echo round(($percentageFacJuTjF + $percentageDeclaJuTjF) / 2) ?>]; // Replace with actual junior data
        var seniorScore = [<?php echo round($percentageFacSeTsF) ?>, <?php echo round($percentageDeclaSeTsF) ?>, <?php echo round(($percentageFacSeTsF + $percentageDeclaSeTsF) / 2) ?>]; // Replace with actual senior data
        var expertScore = [<?php echo round($percentageFacExF) ?>, <?php echo round($percentageDeclaExF) ?>, <?php echo round(($percentageFacExF + $percentageDeclaExF) / 2) ?>]; // Replace with actual expert data
        var averageScore = [averageCompleted(<?php echo round($percentageFacJuTjF) ?>, <?php echo round($percentageFacSeTsF) ?>, <?php echo round($percentageFacExF) ?>), averageCompleted(<?php echo round($percentageDeclaJuTjF) ?>, <?php echo round($percentageDeclaSeTsF) ?>, <?php echo round($percentageDeclaExF) ?>), averageCompleted(<?php echo round(($percentageFacJuTjF + $percentageDeclaJuTjF) / 2) ?>, <?php echo round(($percentageFacSeTsF + $percentageDeclaSeTsF) / 2) ?>, <?php echo round(($percentageFacExF + $percentageDeclaExF) / 2) ?>)]; // Replace with actual expert data

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