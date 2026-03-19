<?php
session_start();
include_once "../language.php";
include_once "../partials/background-manager.php";
setPageBackground("bg-dashboard");

if (!isset($_SESSION["profile"])) {
    header("Location: ../../");
    exit();
} else {
    
    // Map countries
    $selectedCountry = isset($_GET['country']) ? $_GET['country'] : null;

    require_once "../../vendor/autoload.php";
    
    // Function to get flag path (similar to userSpeciality.php)
    function countryCodeFromName($name)
    {
        static $map = [
            "Côte d'Ivoire" => 'ci', 'Cote d\'Ivoire' => 'ci', 'Ivory Coast' => 'ci',
            'Sénégal' => 'sn', 'Senegal' => 'sn', 'Cameroun' => 'cm', 'Cameroon' => 'cm', 'Gabon' => 'ga',
            'Ghana' => 'gh', 'Nigeria' => 'ng', 'Kenya' => 'ke', 'Togo' => 'tg', 'Bénin' => 'bj', 'Benin' => 'bj',
            'Congo' => 'cg', 'République du Congo' => 'cg', 'RDC' => 'cd', 'République Démocratique du Congo' => 'cd',
            'Mali' => 'ml', 'Burkina Faso' => 'bf', 'Niger' => 'ne', 'Tchad' => 'td', 'Madagascar' => 'mg',
            'Mauritanie' => 'mr', 'Maroc' => 'ma', 'Tunisie' => 'tn', 'Algérie' => 'dz', 'Sierra Leone' => 'sl',
            'Liberia' => 'lr', 'Ouganda' => 'ug', 'Uganda' => 'ug', 'Tanzanie' => 'tz', 'Tanzania' => 'tz',
            'Ethiopie' => 'et', 'Éthiopie' => 'et', 'Ethiopia' => 'et', 'Afrique du Sud' => 'za', 'South Africa' => 'za',
            'Guinée' => 'gn', 'Guinea' => 'gn', 'Guinée-Bissau' => 'gw', 'Guinea-Bissau' => 'gw',
            'Guinée équatoriale' => 'gq', 'Equatorial Guinea' => 'gq', 'RCA' => 'cf',
            'République Centrafricaine' => 'cf', 'Central African Republic' => 'cf', 'Rwanda' => 'rw'
        ];

        if (isset($map[$name])) return $map[$name];
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT', $name);
        $code  = strtolower(substr(preg_replace('/[^A-Za-z]/', '', $ascii ?: $name), 0, 2));
        return $code;
    }
    
    function getFlagWebPath($countryName, $ratio = '4x3')
    {
        $code = countryCodeFromName($countryName);
        $baseFs  = __DIR__ . "/../define/flags/$ratio/$code";
        $baseWeb = "../define/flags/$ratio/$code";
        $exts = [".svg", ".png", ".jpg", ".webp"];
        foreach ($exts as $ext) {
            if (file_exists($baseFs . $ext)) return $baseWeb . $ext;
        }
        return null;
    }

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

    // Récupérer la liste des pays uniques depuis la base de données
    $countries = $users->distinct('country', ['active' => true]);
    sort($countries); // Trier les pays par ordre alphabétique

    $country = $_GET['country'];
    
    $technicians = [];
    
    // Construire le filtre en fonction de la sélection
    if ($country === 'all') {
        // Si "tous les pays" est sélectionné, ne pas filtrer par pays
        $filter = [
            "active" => true
        ];
    } else {
        // Sinon, filtrer par le pays spécifique
        $filter = [
            '$and' => [
                [
                    "country" => $country,
                    "active" => true,
                ],
            ],
        ];
    }
    
    $techs = $users->find($filter)->toArray();
    foreach ($techs as $techn) {
        if ($techn["profile"] == "Technicien") {
            array_push($technicians, new MongoDB\BSON\ObjectId($techn['_id']));
        } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
            array_push($technicians, new MongoDB\BSON\ObjectId($techn['_id']));
        }
    }
    $techniciansJu = [];
    
    // Construire le filtre pour Junior
    if ($country === 'all') {
        $filterJu = [
            "level" => "Junior",
            "active" => true
        ];
    } else {
        $filterJu = [
            '$and' => [
                [
                    "country" => $country,
                    "level" => "Junior",
                    "active" => true,
                ],
            ],
        ];
    }
    
    $techsJu = $users->find($filterJu)->toArray();
    foreach ($techsJu as $techn) {
        if ($techn["profile"] == "Technicien") {
            array_push($techniciansJu, new MongoDB\BSON\ObjectId($techn['_id']));
        } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
            array_push($techniciansJu, new MongoDB\BSON\ObjectId($techn['_id']));
        }
    }
    $techniciansSe = [];
    
    // Construire le filtre pour Senior
    if ($country === 'all') {
        $filterSe = [
            "level" => "Senior",
            "active" => true
        ];
    } else {
        $filterSe = [
            '$and' => [
                [
                    "country" => $country,
                    "level" => "Senior",
                    "active" => true,
                ],
            ],
        ];
    }
    
    $techsSe = $users->find($filterSe)->toArray();
    foreach ($techsSe as $techn) {
        if ($techn["profile"] == "Technicien") {
            array_push($techniciansSe, new MongoDB\BSON\ObjectId($techn['_id']));
        } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
            array_push($techniciansSe, new MongoDB\BSON\ObjectId($techn['_id']));
        }
    }
    $techniciansEx = [];
    
    // Construire le filtre pour Expert
    if ($country === 'all') {
        $filterEx = [
            "level" => "Expert",
            "active" => true
        ];
    } else {
        $filterEx = [
            '$and' => [
                [
                    "country" => $country,
                    "level" => "Expert",
                    "active" => true,
                ],
            ],
        ];
    }
    
    $techsEx = $users->find($filterEx)->toArray();
    foreach ($techsEx as $techn) {
        if ($techn["profile"] == "Technicien") {
            array_push($techniciansEx, new MongoDB\BSON\ObjectId($techn['_id']));
        } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
            array_push($techniciansEx, new MongoDB\BSON\ObjectId($techn['_id']));
        }
    }
    

    $resultsFacScoreJuTj = [];
    $resultsDeclaScoreJuTj = [];
    $resultsFacTotalJuTj = [];
    $resultsDeclaTotalJuTj = [];

    foreach ($techniciansJu as $tech) {
        $alloFacJuTj = $allocations->findOne([
            '$and' => [
                [
                    'user' =>  new MongoDB\BSON\ObjectId($tech),
                    'level' => 'Junior',
                    'type' => 'Factuel',
                    'active' => true
                ]
            ]
        ]);
        $alloDeclaJuTj = $allocations->findOne([
            '$and' => [
                [
                    'user' =>  new MongoDB\BSON\ObjectId($tech),
                    'level' => 'Junior',
                    'type' => 'Declaratif',
                    'activeManager' => true,
                    'active' => true
                ]
            ]
        ]);
        if ($alloFacJuTj && $alloDeclaJuTj) {
            $resultFacJuTj = $results->findOne([
                '$and' => [
                    [
                        "user" => new MongoDB\BSON\ObjectId($tech),
                        "level" => "Junior",
                        'typeR' => 'Technicien',
                        "type" => "Factuel",
                    ],
                ],
            ]);
            if (isset($resultFacJuTj)) {
                array_push($resultsFacScoreJuTj, $resultFacJuTj['score']);
                array_push($resultsFacTotalJuTj, $resultFacJuTj['total']);
            }
            $resultDeclaJuTj = $results->findOne([
                '$and' => [
                    [
                        "user" => new MongoDB\BSON\ObjectId($tech),
                        "level" => "Junior",
                        "typeR" => "Technicien - Manager",
                        "type" => "Declaratif",
                    ],
                ],
            ]);
            if (isset($resultDeclaJuTj)) {
                array_push($resultsDeclaScoreJuTj, $resultDeclaJuTj['score']);
                array_push($resultsDeclaTotalJuTj, $resultDeclaJuTj['total']);
            }
        }
    }
    $totalResultFacJuTj = 0;
    $scoreResultFacJuTj = 0;
    for ($i = 0; $i < count($resultsFacTotalJuTj); $i++) {
        $scoreResultFacJuTj += ( int)$resultsFacScoreJuTj[$i];
        $totalResultFacJuTj += ( int)$resultsFacTotalJuTj[$i];
    }
    if($totalResultFacJuTj == 0) {
        $avgFacJuTj = ($scoreResultFacJuTj * 100 / 1);
    } else {
        $avgFacJuTj = ($scoreResultFacJuTj * 100 / $totalResultFacJuTj);
    }
    $percentageFacJuTj = $avgFacJuTj;
    $totalResultDeclaJuTj = 0;
    $scoreResultDeclaJuTj = 0;
    for ($i = 0; $i < count($resultsDeclaTotalJuTj); $i++) {
        $scoreResultDeclaJuTj += ( int)$resultsDeclaScoreJuTj[$i];
        $totalResultDeclaJuTj += ( int)$resultsDeclaTotalJuTj[$i];
    }
    if($totalResultDeclaJuTj == 0) {
        $avgDeclaJuTj = ($scoreResultDeclaJuTj * 100 / 1);
    } else {
        $avgDeclaJuTj = ($scoreResultDeclaJuTj * 100 / $totalResultDeclaJuTj);
    }
    $percentageDeclaJuTj = $avgDeclaJuTj;
    
    $resultsFacScoreJuTs = [];
    $resultsDeclaScoreJuTs = [];
    $resultsFacScoreSeTs = [];
    $resultsDeclaScoreSeTs = [];
    $resultsFacTotalJuTs = [];
    $resultsDeclaTotalJuTs = [];
    $resultsFacTotalSeTs = [];
    $resultsDeclaTotalSeTs = [];

    foreach ($techniciansSe as $tech) {
        $alloFacJuTs = $allocations->findOne([
            '$and' => [
                [
                    'user' =>  new MongoDB\BSON\ObjectId($tech),
                    'level' => 'Junior',
                    'type' => 'Factuel',
                    'active' => true
                ]
            ]
        ]);
        $alloDeclaJuTs = $allocations->findOne([
            '$and' => [
                [
                    'user' =>  new MongoDB\BSON\ObjectId($tech),
                    'level' => 'Junior',
                    'type' => 'Declaratif',
                    'activeManager' => true,
                    'active' => true
                ]
            ]
        ]);
        $alloFacSeTs = $allocations->findOne([
            '$and' => [
                [
                    'user' =>  new MongoDB\BSON\ObjectId($tech),
                    'level' => 'Senior',
                    'type' => 'Factuel',
                    'active' => true
                ]
            ]
        ]);
        $alloDeclaSeTs = $allocations->findOne([
            '$and' => [
                [
                    'user' =>  new MongoDB\BSON\ObjectId($tech),
                    'level' => 'Senior',
                    'type' => 'Declaratif',
                    'activeManager' => true,
                    'active' => true
                ]
            ]
        ]);
        if ($alloFacJuTs && $alloDeclaJuTs) {
            $resultFacJuTs = $results->findOne([
                '$and' => [
                    [
                        "user" => new MongoDB\BSON\ObjectId($tech),
                        "level" => "Junior",
                        'typeR' => 'Technicien',
                        "type" => "Factuel",
                    ],
                ],
            ]);
            if (isset($resultFacJuTs)) {
                array_push($resultsFacScoreJuTs, $resultFacJuTs['score']);
                array_push($resultsFacTotalJuTs, $resultFacJuTs['total']);
            }
            $resultDeclaJuTs = $results->findOne([
                '$and' => [
                    [
                        "user" => new MongoDB\BSON\ObjectId($tech),
                        "level" => "Junior",
                        "typeR" => "Technicien - Manager",
                        "type" => "Declaratif",
                    ],
                ],
            ]);
            if (isset($resultDeclaJuTs)) {
                array_push($resultsDeclaScoreJuTs, $resultDeclaJuTs['score']);
                array_push($resultsDeclaTotalJuTs, $resultDeclaJuTs['total']);
            }
        }
        if ($alloFacSeTs && $alloDeclaSeTs) {
            $resultFacSeTs = $results->findOne([
                '$and' => [
                    [
                        "user" => new MongoDB\BSON\ObjectId($tech),
                        "level" => "Senior",
                        'typeR' => 'Technicien',
                        "type" => "Factuel",
                    ],
                ],
            ]);
            if (isset($resultFacSeTs)) {
                array_push($resultsFacScoreSeTs, $resultFacSeTs['score']);
                array_push($resultsFacTotalSeTs, $resultFacSeTs['total']);
            }
            $resultDeclaSeTs = $results->findOne([
                '$and' => [
                    [
                        "user" => new MongoDB\BSON\ObjectId($tech),
                        "level" => "Senior",
                        "typeR" => "Technicien - Manager",
                        "type" => "Declaratif",
                    ],
                ],
            ]);
            if (isset($resultDeclaSeTs)) {
                array_push($resultsDeclaScoreSeTs, $resultDeclaSeTs['score']);
                array_push($resultsDeclaTotalSeTs, $resultDeclaSeTs['total']);
            }
        }
    }
    $totalResultFacJuTs = 0;
    $scoreResultFacJuTs = 0;
    for ($i = 0; $i < count($resultsFacTotalJuTs); $i++) {
        $scoreResultFacJuTs += ( int)$resultsFacScoreJuTs[$i];
        $totalResultFacJuTs += ( int)$resultsFacTotalJuTs[$i];
    }
    if($totalResultFacJuTs == 0) {
        $avgFacJuTs = ($scoreResultFacJuTs * 100 / 1);
    } else {
        $avgFacJuTs = ($scoreResultFacJuTs * 100 / $totalResultFacJuTs);
    }
    $percentageFacJuTs = $avgFacJuTs;
    $totalResultDeclaJuTs = 0;
    $scoreResultDeclaJuTs = 0;
    for ($i = 0; $i < count($resultsDeclaTotalJuTs); $i++) {
        $scoreResultDeclaJuTs += ( int)$resultsDeclaScoreJuTs[$i];
        $totalResultDeclaJuTs += ( int)$resultsDeclaTotalJuTs[$i];
    }
    if($totalResultDeclaJuTs == 0) {
        $avgDeclaJuTs = ($scoreResultDeclaJuTs * 100 / 1);
    } else {
        $avgDeclaJuTs = ($scoreResultDeclaJuTs * 100 / $totalResultDeclaJuTs);
    }
    $percentageDeclaJuTs = $avgDeclaJuTs;
    $totalResultFacSeTs = 0;
    $scoreResultFacSeTs = 0;
    for ($i = 0; $i < count($resultsFacTotalSeTs); $i++) {
        $scoreResultFacSeTs += ( int)$resultsFacScoreSeTs[$i];
        $totalResultFacSeTs += ( int)$resultsFacTotalSeTs[$i];
    }
    if($totalResultFacSeTs == 0) {
        $avgFacSeTs = ($scoreResultFacSeTs * 100 / 1);
        $percentageFacSeTs = 0;
    } else {
        $avgFacSeTs = ($scoreResultFacSeTs * 100 / $totalResultFacSeTs);
    }
    $percentageFacSeTs = $avgFacSeTs;
    $totalResultDeclaSeTs = 0;
    $scoreResultDeclaSeTs = 0;
    for ($i = 0; $i < count($resultsDeclaTotalSeTs); $i++) {
        $scoreResultDeclaSeTs += ( int)$resultsDeclaScoreSeTs[$i];
        $totalResultDeclaSeTs += ( int)$resultsDeclaTotalSeTs[$i];
    }
    if($totalResultDeclaSeTs == 0) {
        $avgDeclaSeTs = ($scoreResultDeclaSeTs * 100 / 1);
        $percentageDeclaSeTs = 0;
    } else {
        $avgDeclaSeTs = ($scoreResultDeclaSeTs * 100 / $totalResultDeclaSeTs);
    }
    $percentageDeclaSeTs = $avgDeclaSeTs;
    
    $resultsFacScoreJuTx = [];
    $resultsDeclaScoreJuTx = [];
    $resultsFacTotalJuTx = [];
    $resultsDeclaTotalJuTx = [];
    $resultsFacScoreSeTx = [];
    $resultsDeclaScoreSeTx = [];
    $resultsFacTotalSeTx = [];
    $resultsDeclaTotalSeTx = [];

    foreach ($techniciansEx as $tech) {
        $alloFacJuTx = $allocations->findOne([
            '$and' => [
                [
                    'user' =>  new MongoDB\BSON\ObjectId($tech),
                    'level' => 'Junior',
                    'type' => 'Factuel',
                    'active' => true
                ]
            ]
        ]);
        $alloDeclaJuTx = $allocations->findOne([
            '$and' => [
                [
                    'user' =>  new MongoDB\BSON\ObjectId($tech),
                    'level' => 'Junior',
                    'type' => 'Declaratif',
                    'activeManager' => true,
                    'active' => true
                ]
            ]
        ]);
        $alloFacSeTx = $allocations->findOne([
            '$and' => [
                [
                    'user' =>  new MongoDB\BSON\ObjectId($tech),
                    'level' => 'Senior',
                    'type' => 'Factuel',
                    'active' => true
                ]
            ]
        ]);
        $alloDeclaSeTx = $allocations->findOne([
            '$and' => [
                [
                    'user' =>  new MongoDB\BSON\ObjectId($tech),
                    'level' => 'Senior',
                    'type' => 'Declaratif',
                    'activeManager' => true,
                    'active' => true
                ]
            ]
        ]);
        if ($alloFacJuTx && $alloDeclaJuTx) {
            $resultFacJuTx = $results->findOne([
                '$and' => [
                    [
                        "user" => new MongoDB\BSON\ObjectId($tech),
                        "level" => "Junior",
                        'typeR' => 'Technicien',
                        "type" => "Factuel",
                    ],
                ],
            ]);
            if (isset($resultFacJuTx)) {
                array_push($resultsFacScoreJuTx, $resultFacJuTx['score']);
                array_push($resultsFacTotalJuTx, $resultFacJuTx['total']);
            }
            $resultDeclaJuTx = $results->findOne([
                '$and' => [
                    [
                        "user" => new MongoDB\BSON\ObjectId($tech),
                        "level" => "Junior",
                        "typeR" => "Technicien - Manager",
                        "type" => "Declaratif",
                    ],
                ],
            ]);
            if (isset($resultDeclaJuTx)) {
                array_push($resultsDeclaScoreJuTx, $resultDeclaJuTx['score']);
                array_push($resultsDeclaTotalJuTx, $resultDeclaJuTx['total']);
            }
        }
        if ($alloFacSeTx && $alloDeclaSeTx) {
            $resultFacSeTx = $results->findOne([
                '$and' => [
                    [
                        "user" => new MongoDB\BSON\ObjectId($tech),
                        "level" => "Senior",
                        'typeR' => 'Technicien',
                        "type" => "Factuel",
                    ],
                ],
            ]);
            if (isset($resultFacSeTx)) {
                array_push($resultsFacScoreSeTx, $resultFacSeTx['score']);
                array_push($resultsFacTotalSeTx, $resultFacSeTx['total']);
            }
            $resultDeclaSeTx = $results->findOne([
                '$and' => [
                    [
                        "user" => new MongoDB\BSON\ObjectId($tech),
                        "level" => "Senior",
                        "typeR" => "Technicien - Manager",
                        "type" => "Declaratif",
                    ],
                ],
            ]);
            if (isset($resultDeclaSeTx)) {
                array_push($resultsDeclaScoreSeTx, $resultDeclaSeTx['score']);
                array_push($resultsDeclaTotalSeTx, $resultDeclaSeTx['total']);
            }
        }
    }
    $totalResultFacJuTx = 0;
    $scoreResultFacJuTx = 0;
    for ($i = 0; $i < count($resultsFacTotalJuTx); $i++) {
        $scoreResultFacJuTx += ( int)$resultsFacScoreJuTx[$i];
        $totalResultFacJuTx += ( int)$resultsFacTotalJuTx[$i];
    }
    if($totalResultFacJuTx == 0) {
        $avgFacJuTx = ($scoreResultFacJuTx * 100 / 1);
    } else {
        $avgFacJuTx = ($scoreResultFacJuTx * 100 / $totalResultFacJuTx);
    }
    $percentageFacJuTx = $avgFacJuTx;
    $totalResultDeclaJuTx = 0;
    $scoreResultDeclaJuTx = 0;
    for ($i = 0; $i < count($resultsDeclaTotalJuTx); $i++) {
        $scoreResultDeclaJuTx += ( int)$resultsDeclaScoreJuTx[$i];
        $totalResultDeclaJuTx += ( int)$resultsDeclaTotalJuTx[$i];
    }
    if($totalResultDeclaJuTx == 0) {
        $avgDeclaJuTx = ($scoreResultDeclaJuTx * 100 / 1);
    } else {
        $avgDeclaJuTx = ($scoreResultDeclaJuTx * 100 / $totalResultDeclaJuTx);
    }
    $percentageDeclaJuTx = $avgDeclaJuTx;
    $totalResultFacSeTx = 0;
    $scoreResultFacSeTx = 0;
    for ($i = 0; $i < count($resultsFacTotalSeTx); $i++) {
        $scoreResultFacSeTx += ( int)$resultsFacScoreSeTx[$i];
        $totalResultFacSeTx += ( int)$resultsFacTotalSeTx[$i];
    }
    if($totalResultFacSeTx == 0) {
        $avgFacSeTx = ($scoreResultFacSeTx * 100 / 1);
    } else {
        $avgFacSeTx = ($scoreResultFacSeTx * 100 / $totalResultFacSeTx);
    }
    $percentageFacSeTx = $avgFacSeTx;
    $totalResultDeclaSeTx = 0;
    $scoreResultDeclaSeTx = 0;
    for ($i = 0; $i < count($resultsDeclaTotalSeTx); $i++) {
        $scoreResultDeclaSeTx += ( int)$resultsDeclaScoreSeTx[$i];
        $totalResultDeclaSeTx += ( int)$resultsDeclaTotalSeTx[$i];
    }
    if($totalResultDeclaSeTx == 0) {
        $avgDeclaSeTx = ($scoreResultDeclaSeTx * 100 / 1);
    } else {
        $avgDeclaSeTx = ($scoreResultDeclaSeTx * 100 / $totalResultDeclaSeTx);
    }
    $percentageDeclaSeTx = $avgDeclaSeTx;

    $resultsFacScoreJu = [];
    $resultsDeclaScoreJu = [];
    $resultsFacScoreSe = [];
    $resultsDeclaScoreSe = [];
    $resultsFacScoreEx = [];
    $resultsDeclaScoreEx = [];
    $resultsFacTotalJu = [];
    $resultsDeclaTotalJu = [];
    $resultsFacTotalSe = [];
    $resultsDeclaTotalSe = [];
    $resultsFacTotalEx = [];
    $resultsDeclaTotalEx = [];

    foreach ($technicians as $tech) {
        $alloFacJu = $allocations->findOne([
            '$and' => [
                [
                    'user' =>  new MongoDB\BSON\ObjectId($tech),
                    'level' => 'Junior',
                    'type' => 'Factuel',
                    'active' => true
                ]
            ]
        ]);
        $alloDeclaJu = $allocations->findOne([
            '$and' => [
                [
                    'user' =>  new MongoDB\BSON\ObjectId($tech),
                    'level' => 'Junior',
                    'type' => 'Declaratif',
                    'activeManager' => true,
                    'active' => true
                ]
            ]
        ]);
        $alloFacSe = $allocations->findOne([
            '$and' => [
                [
                    'user' =>  new MongoDB\BSON\ObjectId($tech),
                    'level' => 'Senior',
                    'type' => 'Factuel',
                    'active' => true
                ]
            ]
        ]);
        $alloDeclaSe = $allocations->findOne([
            '$and' => [
                [
                    'user' =>  new MongoDB\BSON\ObjectId($tech),
                    'level' => 'Senior',
                    'type' => 'Declaratif',
                    'activeManager' => true,
                    'active' => true
                ]
            ]
        ]);
        $alloFacEx = $allocations->findOne([
            '$and' => [
                [
                    'user' =>  new MongoDB\BSON\ObjectId($tech),
                    'level' => 'Expert',
                    'type' => 'Factuel',
                    'active' => true
                ]
            ]
        ]);
        $alloDeclaEx = $allocations->findOne([
            '$and' => [
                [
                    'user' =>  new MongoDB\BSON\ObjectId($tech),
                    'level' => 'Expert',
                    'type' => 'Declaratif',
                    'activeManager' => true,
                    'active' => true
                ]
            ]
        ]);
        if ($alloFacJu && $alloDeclaJu) {
            $resultFacJu = $results->findOne([
                '$and' => [
                    [
                        "user" => new MongoDB\BSON\ObjectId($tech),
                        "level" => "Junior",
                        'typeR' => 'Technicien',
                        "type" => "Factuel",
                    ],
                ],
            ]);
            if (isset($resultFacJu)) {
                array_push($resultsFacScoreJu, $resultFacJu['score']);
                array_push($resultsFacTotalJu, $resultFacJu['total']);
            }
            $resultDeclaJu = $results->findOne([
                '$and' => [
                    [
                        "user" => new MongoDB\BSON\ObjectId($tech),
                        "level" => "Junior",
                        "typeR" => "Technicien - Manager",
                        "type" => "Declaratif",
                    ],
                ],
            ]);
            if (isset($resultDeclaJu)) {
                array_push($resultsDeclaScoreJu, $resultDeclaJu['score']);
                array_push($resultsDeclaTotalJu, $resultDeclaJu['total']);
            }
        }
        if ($alloFacSe && $alloDeclaSe) {
            $resultFacSe = $results->findOne([
                '$and' => [
                    [
                        "user" => new MongoDB\BSON\ObjectId($tech),
                        "level" => "Senior",
                        'typeR' => 'Technicien',
                        "type" => "Factuel",
                    ],
                ],
            ]);
            if (isset($resultFacSe)) {
                array_push($resultsFacScoreSe, $resultFacSe['score']);
                array_push($resultsFacTotalSe, $resultFacSe['total']);
            }
            $resultDeclaSe = $results->findOne([
                '$and' => [
                    [
                        "user" => new MongoDB\BSON\ObjectId($tech),
                        "level" => "Senior",
                        "typeR" => "Technicien - Manager",
                        "type" => "Declaratif",
                    ],
                ],
            ]);
            if (isset($resultDeclaSe)) {
                array_push($resultsDeclaScoreSe, $resultDeclaSe['score']);
                array_push($resultsDeclaTotalSe, $resultDeclaSe['total']);
            }
        }
        if ($alloFacEx && $alloDeclaEx) {
            $resultFacEx = $results->findOne([
                '$and' => [
                    [
                        "user" => new MongoDB\BSON\ObjectId($tech),
                        "level" => "Expert",
                        'typeR' => 'Technicien',
                        "type" => "Factuel",
                    ],
                ],
            ]);
            if (isset($resultFacEx)) {
                array_push($resultsFacScoreEx, $resultFacEx['score']);
                array_push($resultsFacTotalEx, $resultFacEx['total']);
            }
            $resultDeclaEx = $results->findOne([
                '$and' => [
                    [
                        "user" => new MongoDB\BSON\ObjectId($tech),
                        "level" => "Expert",
                        "typeR" => "Technicien - Manager",
                        "type" => "Declaratif",
                    ],
                ],
            ]);
            if (isset($resultDeclaEx)) {
                array_push($resultsDeclaScoreEx, $resultDeclaEx['score']);
                array_push($resultsDeclaTotalEx, $resultDeclaEx['total']);
            }
        }
    }
    $totalResultFacJu = 0;
    $scoreResultFacJu = 0;
    for ($i = 0; $i < count($resultsFacTotalJu); $i++) {
        $scoreResultFacJu += ( int)$resultsFacScoreJu[$i];
        $totalResultFacJu += ( int)$resultsFacTotalJu[$i];
    }
    if($totalResultFacJu == 0) {
        $avgFacJu = ($scoreResultFacJu * 100 / 1);
    } else {
        $avgFacJu = ($scoreResultFacJu * 100 / $totalResultFacJu);
    }
    $percentageFacJu = $avgFacJu;
    $totalResultDeclaJu = 0;
    $scoreResultDeclaJu = 0;
    for ($i = 0; $i < count($resultsDeclaTotalJu); $i++) {
        $scoreResultDeclaJu += ( int)$resultsDeclaScoreJu[$i];
        $totalResultDeclaJu += ( int)$resultsDeclaTotalJu[$i];
    }
    if($totalResultDeclaJu == 0) {
        $avgDeclaJu = ($scoreResultDeclaJu * 100 / 1);
    } else {
        $avgDeclaJu = ($scoreResultDeclaJu * 100 / $totalResultDeclaJu);
    }
    $percentageDeclaJu = $avgDeclaJu;
    $totalResultFacSe = 0;
    $scoreResultFacSe = 0;
    for ($i = 0; $i < count($resultsFacTotalSe); $i++) {
        $scoreResultFacSe += ( int)$resultsFacScoreSe[$i];
        $totalResultFacSe += ( int)$resultsFacTotalSe[$i];
    }
    if($totalResultFacSe == 0) {
        $avgFacSe = ($scoreResultFacSe * 100 / 1);
        $percentageFacSe = 0;
    } else {
        $avgFacSe = ($scoreResultFacSe * 100 / $totalResultFacSe);
    }
    $percentageFacSe = $avgFacSe;
    $totalResultDeclaSe = 0;
    $scoreResultDeclaSe = 0;
    for ($i = 0; $i < count($resultsDeclaTotalSe); $i++) {
        $scoreResultDeclaSe += ( int)$resultsDeclaScoreSe[$i];
        $totalResultDeclaSe += ( int)$resultsDeclaTotalSe[$i];
    }
    if($totalResultDeclaSe == 0) {
        $avgDeclaSe = ($scoreResultDeclaSe * 100 / 1);
        $percentageDeclaSe = 0;
    } else {
        $avgDeclaSe = ($scoreResultDeclaSe * 100 / $totalResultDeclaSe);
    }
    $percentageDeclaSe = $avgDeclaSe;
    $totalResultFacEx = 0;
    $scoreResultFacEx = 0;
    for ($i = 0; $i < count($resultsFacTotalEx); $i++) {
        $scoreResultFacEx += ( int)$resultsFacScoreEx[$i];
        $totalResultFacEx += ( int)$resultsFacTotalEx[$i];
    }
    if($totalResultFacEx == 0) {
        $avgFacEx = ($scoreResultFacEx * 100 / 1);
    } else {
        $avgFacEx = ($scoreResultFacEx * 100 / $totalResultFacEx);
    }
    $percentageFacEx = $avgFacEx;
    $totalResultDeclaEx = 0;
    $scoreResultDeclaEx = 0;
    for ($i = 0; $i < count($resultsDeclaTotalEx); $i++) {
        $scoreResultDeclaEx += ( int)$resultsDeclaScoreEx[$i];
        $totalResultDeclaEx += ( int)$resultsDeclaTotalEx[$i];
    }
    if($totalResultDeclaEx == 0) {
        $avgDeclaEx = ($scoreResultDeclaEx * 100 / 1);
    } else {
        $avgDeclaEx = ($scoreResultDeclaEx * 100 / $totalResultDeclaEx);
    }
    $percentageDeclaEx = $avgDeclaEx;
    
    $testsUserJu = [];
    $countSavoirJu = [];
    $countMaSavFaiJu = [];
    $countTechSavFaiJu = [];
    $testsUserSe = [];
    $countSavoirSe = [];
    $countMaSavFaiSe = [];
    $countTechSavFaiSe = [];
    $testsUserEx = [];
    $countSavoirEx = [];
    $countMaSavFaiEx = [];
    $countTechSavFaiEx = [];
    $countSavFaiJu = [];
    $countSavFaiSe = [];
    $countSavFaiEx = [];
    $testsTotalJu = [];
    $testsTotalSe = [];
    $testsTotalEx = [];
    foreach ($technicians as $technician) { 
        $allocateFacJu = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Factuel",
                    "level" => "Junior",
                ],
            ],
        ]);
        $allocateDeclaJu = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Declaratif",
                    "level" => "Junior",
                ],
            ],
        ]);
        if (isset($allocateFacJu) && $allocateFacJu['active'] == true) {
            $countSavoirJu[] = $allocateFacJu;
        }
        if (isset($allocateDeclaJu) && $allocateDeclaJu['activeManager'] == true) {
            $countMaSavFaiJu[] = $allocateDeclaJu;
        }
        if (isset($allocateDeclaJu) && $allocateDeclaJu['active'] == true) {
            $countTechSavFaiJu[] = $allocateDeclaJu;
        }
        if (isset($allocateDeclaJu) && $allocateDeclaJu['active'] == true && $allocateDeclaJu['activeManager'] == true) {
            $countSavFaiJu[] = $allocateDeclaJu;
        }
        if (isset($allocateFacJu) && isset($allocateDeclaJu) && $allocateFacJu['active'] == true && $allocateDeclaJu['active'] == true && $allocateDeclaJu['activeManager'] == true) {
            $testsUserJu[] = $technician;
        }
        if (isset($allocateFacJu) && isset($allocateDeclaJu)) {
            $testsTotalJu[] = $technician;
        }

        $allocateFacSe = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Factuel",
                    "level" => "Senior",
                ],
            ],
        ]);
        $allocateDeclaSe = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Declaratif",
                    "level" => "Senior",
                ],
            ],
        ]);
        if (isset($allocateFacSe) && $allocateFacSe['active'] == true) {
            $countSavoirSe[] = $allocateFacSe;
        }
        if (isset($allocateDeclaSe) && $allocateDeclaSe['activeManager'] == true) {
            $countMaSavFaiSe[] = $allocateDeclaSe;
        }
        if (isset($allocateDeclaSe) && $allocateDeclaSe['active'] == true) {
            $countTechSavFaiSe[] = $allocateDeclaSe;
        }
        if (isset($allocateDeclaSe) && $allocateDeclaSe['active'] == true && $allocateDeclaSe['activeManager'] == true) {
            $countSavFaiSe[] = $allocateDeclaSe;
        }
        if (isset($allocateFacSe) && isset($allocateDeclaSe) && $allocateFacSe['active'] == true && $allocateDeclaSe['active'] == true && $allocateDeclaSe['activeManager'] == true) {
            $testsUserSe[] = $technician;
        }
        if (isset($allocateFacSe) && isset($allocateDeclaSe)) {
            $testsTotalSe[] = $technician;
        }

        $allocateFacEx = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Factuel",
                    "level" => "Expert",
                ],
            ],
        ]);
        $allocateDeclaEx = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Declaratif",
                    "level" => "Expert",
                ],
            ],
        ]);
        if (isset($allocateFacEx) && $allocateFacEx['active'] == true) {
            $countSavoirEx[] = $allocateFacEx;
        }
        if (isset($allocateDeclaEx) && $allocateDeclaEx['activeManager'] == true) {
            $countMaSavFaiEx[] = $allocateDeclaEx;
        }
        if (isset($allocateDeclaEx) && $allocateDeclaEx['active'] == true) {
            $countTechSavFaiEx[] = $allocateDeclaEx;
        }
        if (isset($allocateDeclaEx) && $allocateDeclaEx['active'] == true && $allocateDeclaEx['activeManager'] == true) {
            $countSavFaiEx[] = $allocateDeclaEx;
        }
        if (isset($allocateFacEx) && isset($allocateDeclaEx) && $allocateFacEx['active'] == true && $allocateDeclaEx['active'] == true && $allocateDeclaEx['activeManager'] == true) {
            $testsUserEx[] = $technician;
        }
        if (isset($allocateFacEx) && isset($allocateDeclaEx)) {
            $testsTotalEx[] = $technician;
        }
    }    
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
    /* background-color: #f9f9f9; */
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

/* Optional: Style for better visibility */
.title-and-cards-container {
    margin-bottom: 20px;
    /* Adjust as needed */
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

.card {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
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
    gap: 8px;
    justify-content: center;
    /* Center cards horizontally */
    flex: 3;
    /* Allow card container to take up more space */
}

.dynamic-card-container .card {
    width: 200px;
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
    height: 80%;
    /* Adjust height of the canvas for the doughnut chart */
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
    top: 50px;
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
<title><?php echo $etat_avanacement_filiale ?> <?php echo ($country === 'all' ? 'Tous les pays' : $country) ?> | CFAO Mobility Academy</title>
<!--end::Title-->
<!--begin::Content-->

    <!--begin::Toolbar-->
            <div class="container-xxl">
           <div class="card shadow-sm mb-5">
                <div class="card-body py-3">
                    <h1 class="text-dark fw-bold text-center fs-1 m-0">
                        <i class="ki-duotone ki-chart-pie-3 fs-2x me-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <?php echo $etat_avanacement_filiale ?> <?php echo ($country === 'all' ? 'Tous les pays' : $country) ?>
                    </h1>

                </div>
            </div>
        </div>
    <!--end::Toolbar-->
<!--begin::Filtres -->
<div class="container my-4">
  <div class="card glass-effect-card depth-effect">
    <div class="card-body p-4">
      <div class="row g-3 justify-content-center align-items-center">
        <!-- Filtre Filiales -->
        <div class="col-md-6">
          <label for="country-filter" class="form-label d-flex align-items-center justify-content-center">
            <i class="bi bi-geo-alt-fill fs-2 me-2 text-primary"></i> Filiales
          </label>
          <select id="country-filter" name="country" class="form-select" onchange="applyCountryFilter()">
            <!-- Option "Tous les pays" si tu veux le même UX que l’exemple -->
            <option value="all" <?php if ($selectedCountry === 'all') echo 'selected'; ?>>Tous les pays</option>
            <?php foreach ($countries as $countryOption): ?>
              <option value="<?php echo htmlspecialchars($countryOption); ?>"
                <?php if ($selectedCountry === $countryOption) echo 'selected'; ?>>
                <?php echo htmlspecialchars($countryOption); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>
  </div>
</div>
<!--end::Filtres -->


    <br>
    <!--begin::Post-->
    <div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
        <!--begin::Container-->
        <div class=" container-xxl ">
            <!--end::Layout Builder Notice-->
            <!--begin::Row-->
            <div class="row g-6 g-xl-9 mb-6 mb-xl-9">
                <!--begin::Toolbar-->
                <div class="toolbar" id="kt_toolbar" style="margin-bottom: -50px">
                    <div class="card shadow-sm mb-5 ">
                        <!--begin::Info-->
                        <div class="card-body py-3">
                            <!--begin::Title-->
                            <h2 class="text-dark fw-bold text-center my-1 fs-2">
                                <?php echo $effectif_filiale ?>
                            </h2>
                            <!--end::Title-->
                        </div>
                        <!--end::Info-->
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
                                    data-kt-countup-value="<?php echo count($techniciansJu) ?>">
                                </div>
                            </div>
                            <!--end::Animation-->
                               <!--begin::Title-->
                            <div class="fs-5 fw-bold mb-2">
                                <?php echo $technicienss ?> <?php echo $level ?> <?php echo $junior ?></div>
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
                                    data-kt-countup-value="<?php echo count($techniciansSe) ?>">
                                </div>
                            </div>
                            <!--end::Animation-->
                               <!--begin::Title-->
                            <div class="fs-5 fw-bold mb-2">
                                <?php echo $technicienss ?> <?php echo $level ?> <?php echo $senior ?></div>
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
                                    data-kt-countup-value="<?php echo count($techniciansEx) ?>">
                                </div>
                            </div>
                            <!--end::Animation-->
                               <!--begin::Title-->
                            <div class="fs-5 fw-bold mb-2">
                                <?php echo $technicienss ?> <?php echo $level ?> <?php echo $expert ?></div>
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
                                    data-kt-countup-value="<?php echo count($technicians); ?>">
                                </div>
                            </div>
                            <!--end::Animation-->
                            <!--begin::Title-->
                            <div class="fs-5 fw-bold mb-2">
                            <?php echo $technicienss ?> <?php echo $agence ?> <?php echo $global ?></div>
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
                                            <?php echo $result_mesure_competence_filiale ?>
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
                <div class="dropdown-container2">
                    <button class="dropdown-toggle2" style="color: black">
                        Plus de détails sur les Résultats
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <!-- Dropdown Content (Initially hidden) -->
                    <div class="dropdown-content2" style="display: none;">
                        <div class="row">
                            <!-- Card 1 -->
                            <div class="col-xl-3">
                                <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 330px; margin-bottom: 25px;">
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
                                <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 330px; margin-bottom: 25px;">
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
                                <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 330px; margin-bottom: 25px;">
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
                                <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 330px; margin-bottom: 25px;">
                                    <center>
                                        <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">Total
                                            : 03 niveaux</h5>
                                    </center>
                                    <div id="result_total_filiale"
                                        class="d-flex align-items-center justify-content-center w-100"
                                            style="height: 100%; width: 100%; margin-top: 10px;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="my-5 title-full">
                        <div class="card shadow-sm mb-5 ">
                            <div class="card-body p-4 ">
                                <h6 class="text-dark fw-bold text-center my-1 fs-2">
                                    <?php echo $result_mesure_competence_filiale_niveau ?>
                                </h6>
                            </div>
                        </div>
                    </div>

                <!--end::Title-->
                <!-- Card 1 -->
                <div class="col-xl-3">
                    <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 330px; margin-bottom: 25px;">
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
                    <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 330px; margin-bottom: 25px;">
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
                    <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 330px; margin-bottom: 25px;">
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
                    <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 330px; margin-bottom: 25px;">
                        <center>
                            <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                                Total : 03 Niveaux</h5>
                        </center>
                        <div id="chart_total_filiale"
                            class="d-flex align-items-center justify-content-center w-100"
                                            style="height: 100%; width: 100%; margin-top: 10px;"></div>
                    </div>
                </div>
                <!--begin::Title-->

                <div class="my-5 title-full">
                        <div class="card shadow-sm mb-5 ">
                            <div class="card-body p-4 ">
                                <h6 class="text-dark fw-bold text-center my-1 fs-2">
                                    <?php echo $etat_avanacement_test_filiale ?>
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
                <!-- endr::Row -->
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
                                <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 330px; margin-bottom: 25px;">
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
                                <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 330px; margin-bottom: 25px;">
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
                                <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 330px; margin-bottom: 25px;">
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
                                <div class="card glass-effect-chart depth-effect" style="height: 430px; width: 330px; margin-bottom: 25px;">
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
            <!--end:Row-->
        </div>
        <!--end::Container-->
    </div>
    <!--end::Post-->
    </div>
    <!--end:Row-->

    <script src="https://code.jquery.com/jquery-3.6.3.js" integrity="sha256-nQLuAZGRRcILA+6dMBOvcRh5Pe310sBpanc6+QBmyVM="
        crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/gh/linways/table-to-excel@v1.0.4/dist/tableToExcel.js">
    </script>
    <script src="../../public/js/main.js"></script>
    <script>
        $(document).ready(function() {
            $("#excel").on("click", function() {
                let table = document.getElementsByTagName("table");
                debugger;
                TableToExcel.convert(table[0], {
                    name: `StateAgency.xlsx`
                })
            });
        });
        $(document).ready(function() {
            $('.dropdown-toggle').click(function() {
                var $dropdownContent = $('.dropdown-content');
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
        
        // Fonction pour appliquer le filtre de pays
        function applyCountryFilter() {
            var selectedCountry = document.getElementById('country-filter').value;
            var urlParams = new URLSearchParams(window.location.search);

            // Mettre à jour ou ajouter le paramètre 'country' dans l'URL
            if (selectedCountry == "Cote d'Ivoire") {
                // Rediriger vers l'URL mise à jour
                window.location.search = 'country=Cote%20d%27Ivoire';
            } else if (selectedCountry == "Burkina Faso") {
                // Rediriger vers l'URL mise à jour
                window.location.search = 'country=Burkina%20Faso';
            } else if (selectedCountry != "Burkina Faso" || selectedCountry != "Cote d'Ivoire") {
                // Rediriger vers l'URL mise à jour
                urlParams.set('country', selectedCountry);
                window.location.search = urlParams.toString();
            } else {
                urlParams.delete('country');
            }
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
        
        document.addEventListener('DOMContentLoaded', function() {
            // Data for each chart
            const chartData = [{
                    title: 'Test Niveau Junior',
                    total: <?php echo count($testsTotalJu) ?>,
                    completed: <?php echo count($testsUserJu) ?>, // Test réalisés
                    data: [<?php echo count($testsUserJu) ?>,
                        <?php echo (count($testsTotalJu) - count($testsUserJu)) ?>
                    ], // Test réalisés vs. Test à réaliser
                    labels: ['Tests réalisés', 'Tests restants à réaliser'],
                    backgroundColor: ['#4303ec', '#D3D3D3'] // Blue and Lightgrey
                },
                {
                    title: 'Test Niveau Senior',
                    total: <?php echo count($testsTotalSe) ?>,
                    completed: <?php echo count($testsUserSe) ?>, // Test réalisés
                    data: [<?php echo count($testsUserSe) ?>,
                        <?php echo (count($testsTotalSe) - count($testsUserSe)) ?>
                    ], // Test réalisés vs. Test à réaliser
                    labels: ['Tests réalisés', 'Tests restants à réaliser'],
                    backgroundColor: ['#4303ec', '#D3D3D3'] // Blue and Lightgrey
                },
                {
                    title: 'Test Niveau Expert',
                    total: <?php echo count($testsTotalEx) ?>,
                    completed: <?php echo count($testsUserEx) ?>, // Test réalisés
                    data: [<?php echo count($testsUserEx) ?>,
                        <?php echo (count($testsTotalEx) - count($testsUserEx)) ?>
                    ], // Test réalisés vs. Test à réaliser
                    labels: ['Tests réalisés', 'Tests restants à réaliser'],
                    backgroundColor: ['#4303ec', '#D3D3D3'] // Blue and Lightgrey
                },
                {
                    title: 'Total : 03 Niveaux',
                    total: <?php echo count($testsTotalJu) + count($testsTotalSe) + count($testsTotalEx) ?>,
                    completed: <?php echo count($testsUserJu) + count($testsUserSe) + count($testsUserEx) ?>, // Test réalisés
                    data: [<?php echo count($testsUserJu) + count($testsUserSe) + count($testsUserEx) ?>,
                        <?php echo (count($testsTotalJu) + count($testsTotalSe) + count($testsTotalEx)) - (count($testsUserJu) + count($testsUserSe) + count($testsUserEx)) ?>
                    ], // Test réalisés vs. Test à réaliser
                    labels: ['Tests réalisés', 'Tests restants à réaliser'],
                    backgroundColor: ['#4303ec', '#D3D3D3'] // Blue and Lightgrey
                }
            ];
        
            const container = document.getElementById('chartTest');
        
            // Loop through the data to create and append cards
            chartData.forEach((data, index) => {
                // Calculate the completed percentage
                if(data.total == 0) {
                    var completedPercentage = 0;
                } else {
                    var completedPercentage = Math.round((data.completed / data.total) * 100);
                }
        
                // Create the card element
                const cardHtml = `
                    <div class="col-md-6 col-lg-3 col-xl-2.5 mb-4">
                        <div class="card h-100 glass-effect-chart depth-effect">
                            <div class="card-body d-flex justify-content-center text-center flex-column p-4">
                                <h5>Total des Tests à réaliser: ${data.total}</h5>
                                <h5><strong>${completedPercentage}%</strong> des tests réalisés</h5>
                                <canvas id="doughnutChart${index}" width="200" height="200"></canvas>
                                <h5 class="mt-2">${data.title}</h5>
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
                                position: 'top',
                                labels: {
                                    // Customize legend labels to include numbers
                                    generateLabels: function(chart) {
                                        const data = chart.data;
                                        return data.labels.map((label, i) => ({
                                            text: `${label}: ${data.datasets[0].data[i]}`,
                                            fillStyle: data.datasets[0].backgroundColor[
                                                i],
                                            strokeStyle: data.datasets[0].borderColor[
                                                i],
                                            lineWidth: data.datasets[0].borderWidth,
                                            hidden: false
                                        }));
                                    }
                                }
                            },
                            datalabels: {
                                formatter: (value, ctx) => {
                                    let sum = ctx.chart.data.datasets[0].data.reduce((a, b) => a +
                                        b, 0);
                                    let percentage = Math.round((value / sum) * 100);
                                    // Round up to the nearest whole number
                                    return percentage + '%';
                                },
                                color: '#fff',
                                display: true,
                                anchor: 'center',
                                align: 'center',
                                font: {
                                    size: 16,
                                    weight: 'bold'
                                }
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
        const chartDataM = [
            {
                title: 'Résultat <?php echo count($techniciansJu) ?> Techniciens Niveau Junior',
                total: 100,
                completed: <?php echo round(($percentageFacJuTj + $percentageDeclaJuTj) / 2) ?>, // Moyenne des compétences acquises
                data: [<?php echo round(($percentageFacJuTj + $percentageDeclaJuTj) / 2) ?>,
                    100 - <?php echo round(($percentageFacJuTj + $percentageDeclaJuTj) / 2) ?>
                ], // Moyenne des compétences acquises vs. Moyenne des compétences à acquérir
                labels: [
                    '<?php echo round(($percentageFacJuTj + $percentageDeclaJuTj) / 2) ?>% des compétences acquises',
                    '<?php echo 100 - round(($percentageFacJuTj + $percentageDeclaJuTj) / 2) ?>% des compétences à acquérir'
                ],
                backgroundColor: getBackgroundColor(<?php echo round(($percentageFacJuTj + $percentageDeclaJuTj) / 2) ?>)
            },
            {
                title: 'Résultat <?php echo count($techniciansSe) ?> Techniciens Niveau Senior',
                total: 100,
                completed: <?php echo round(($percentageFacSeTs + $percentageDeclaSeTs) / 2) ?>, // Moyenne des compétences acquises
                data: [<?php echo round(($percentageFacSeTs + $percentageDeclaSeTs) / 2) ?>,
                    100 - <?php echo round(($percentageFacSeTs + $percentageDeclaSeTs) / 2) ?>
                ], // Moyenne des compétences acquises vs. Moyenne des compétences à acquérir
                labels: [
                    '<?php echo round(($percentageFacSeTs + $percentageDeclaSeTs) / 2) ?>% des compétences acquises',
                    '<?php echo 100 - round(($percentageFacSeTs + $percentageDeclaSeTs) / 2) ?>% des compétences à acquérir'
                ],
                backgroundColor: getBackgroundColor(<?php echo round(($percentageFacSeTs + $percentageDeclaSeTs) / 2) ?>)
            },
            {
                title: 'Résultat <?php echo count($techniciansEx) ?> Techniciens Niveau Expert',
                total: 100,
                completed: <?php echo round(($percentageFacEx + $percentageDeclaEx) / 2) ?>, // Moyenne des compétences acquises
                data: [<?php echo round(($percentageFacEx + $percentageDeclaEx) / 2) ?>,
                    100 - <?php echo round(($percentageFacEx + $percentageDeclaEx) / 2) ?>
                ], // Moyenne des compétences acquises vs. Moyenne des compétences à acquérir
                labels: [
                    '<?php echo round(($percentageFacEx + $percentageDeclaEx) / 2) ?>% des compétences acquises',
                    '<?php echo 100 - round(($percentageFacEx + $percentageDeclaEx) / 2) ?>% des compétences à acquérir'
                ],
                backgroundColor: getBackgroundColor(<?php echo round(($percentageFacEx + $percentageDeclaEx) / 2) ?>)
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
            title: 'Total : 03 Niveaux',
            total: 100,
            completed: averageCompleted,
            data: averageData,
            labels: [
                `${averageCompleted}% des compétences acquises`,
                `${100 - averageCompleted}% des compétences à acquérir`
            ],
            backgroundColor: totalBackgroundColor
        });
    
        const containerM = document.getElementById('chartMoyen');
    
        // Loop through the data to create and append cards
        chartDataM.forEach((data, index) => {
            // Create the card element
            const cardHtml = `
                <div class="col-md-6 col-lg-3 col-xl-2.5 mb-4">
                    <div class="card h-100 glass-effect-chart depth-effect">
                        <div class="card-body d-flex justify-content-center text-center flex-column p-4">
                            <canvas id="doughnutChart${index}" width="200" height="200"></canvas>
                            <h5 class="mt-2">${data.title}</h5>
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
                            position: 'top',
                        },
                        datalabels: {
                            formatter: (value, ctx) => {
                                let sum = ctx.chart.data.datasets[0].data
                                    .reduce((a, b) => a + b, 0);
                                let percentage = Math.round((value / sum) * 100); // Round up to the nearest whole number
                                return percentage + '%';
                            },
                            color: '#fff',
                            display: true,
                            anchor: 'center',
                            align: 'center',
                            font: {
                                size: 16,
                                weight: 'bold'
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(tooltipItem) {
                                    let percentage = Math.round((tooltipItem.raw / 100) * 100);
                                    return `Compétences acquises: ${percentage}%`;
                                }
                            }
                        }
                    }
                }
            });
        });
    });
        
    // Graphiques pour les resultats des connaissances et tâches professionnelles
    
    // Fixed list of cities for the x-axis labels
    var cityLabelJu = ['<?php echo count($techniciansJu) ?> Techniciens Junior', '<?php echo count($techniciansSe) ?> Techniciens Senior', '<?php echo count($techniciansEx) ?> Techniciens Expert'];
    var cityLabelSe = ['<?php echo count($techniciansSe) ?> Techniciens Senior', '<?php echo count($techniciansEx) ?> Techniciens Expert', ''];
    var cityLabelEx = ['<?php echo count($techniciansEx) ?> Techniciens Expert', '', ''];
    var cityLabels = ['Total Niveau Junior', 'Total Niveau Senior', 'Total Niveau Expert'];
    
    // Sample test data for Junior, Senior, and Expert levels
    var juniorScores = [<?php echo round(($percentageFacJuTj + $percentageDeclaJuTj) / 2)?>, <?php echo round(($percentageFacJuTs + $percentageDeclaJuTs) / 2)?>, <?php echo round(($percentageFacJuTx + $percentageDeclaJuTx) / 2)?>]; // Replace with actual junior data
    var seniorScores = [<?php echo round(($percentageFacSeTs + $percentageDeclaSeTs) / 2)?>, <?php echo round(($percentageFacSeTx + $percentageDeclaSeTx) / 2)?>, 0];  // Replace with actual senior data
    var expertScores = [<?php echo round(($percentageFacEx + $percentageDeclaEx) / 2) ?>, 0, 0]; // Replace with actual expert data
    var averageScores = [<?php echo round(($percentageFacJu + $percentageDeclaJu) / 2) ?>, <?php echo round(($percentageFacSe + $percentageDeclaSe) / 2) ?>, <?php echo round(($percentageFacEx + $percentageDeclaEx) / 2) ?>]; // Replace with actual expert data
    
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
    var labelQ = ['Connaissances', 'Tâches Professionnelles', 'Tests'];
    
    function completedPercentage (completed, total) {
        let moyen = Math.round((completed / total) * 100);

        return moyen;
    }
    
    // Sample test data for Junior, Senior, and Expert levels
    var juniorScoreQ = [completedPercentage(<?php echo count($countSavoirJu) ?>, <?php echo count($technicians) ?>), completedPercentage(<?php echo count($countSavFaiJu) ?>, <?php echo count($technicians) ?>), completedPercentage(<?php echo count($testsUserJu) ?>, <?php echo count($testsTotalJu) ?>)]; // Replace with actual junior data
    var seniorScoreQ = [completedPercentage(<?php echo count($countSavoirSe) ?>, <?php echo count($techniciansSe) + count($techniciansEx) ?>), completedPercentage(<?php echo count($countSavFaiSe) ?>, <?php echo count($techniciansSe) + count($techniciansEx) ?>), completedPercentage(<?php echo count($testsUserSe) ?>, <?php echo count($testsTotalSe) ?>)];  // Replace with actual senior data
    var expertScoreQ = [completedPercentage(<?php echo count($countSavoirEx) ?>, <?php echo count($techniciansEx) ?>), completedPercentage(<?php echo count($countSavFaiEx) ?>, <?php echo count($techniciansEx) ?>), completedPercentage(<?php echo count($testsUserEx) ?>, <?php echo count($testsTotalEx) ?>)]; // Replace with actual expert data
    var averageScoreQ = [completedPercentage(<?php echo count($countSavoirJu) + count($countSavoirSe) + count($countSavoirEx) ?>, <?php echo count($technicians) + count($techniciansSe)  + (count($techniciansEx) * 2) ?>), completedPercentage(<?php echo count($countSavFaiJu) + count($countSavFaiSe) + count($countSavFaiEx) ?>, <?php echo count($technicians) + count($techniciansSe)  + (count($techniciansEx) * 2) ?>) ,completedPercentage(<?php echo count($testsUserJu) + count($testsUserSe) + count($testsUserEx) ?>, <?php echo count($testsTotalJu) + count($testsTotalSe) + count($testsTotalEx) ?>)]; // Replace with actual expert data
            
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
    var juniorScore = [<?php echo round($percentageFacJuTj) ?>, <?php echo round($percentageDeclaJuTj) ?>, <?php echo round(($percentageFacJuTj + $percentageDeclaJuTj) / 2)?>]; // Replace with actual junior data
    var seniorScore = [<?php echo round($percentageFacSeTs) ?>, <?php echo round($percentageDeclaSeTs) ?>, <?php echo round(($percentageFacSeTs + $percentageDeclaSeTs) / 2)?>];  // Replace with actual senior data
    var expertScore = [<?php echo round($percentageFacEx) ?>, <?php echo round($percentageDeclaEx) ?>, <?php echo round(($percentageFacEx + $percentageDeclaEx) / 2)?>]; // Replace with actual expert data
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
<?php include "./partials/footer.php"; ?>
