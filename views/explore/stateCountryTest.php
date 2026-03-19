<?php
session_start();
include_once "../language.php";

if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
} else {
    require_once "../../vendor/autoload.php";

    // Create connection
    $conn = new MongoDB\Client("mongodb://10.68.0.7:27017");

    // Connecting in database
    $academy = $conn->academy;

    // Connecting in collections
    $users = $academy->users;
    $quizzes = $academy->quizzes;
    $vehicles = $academy->vehicles;
    $allocations = $academy->allocations;

    if ($_SESSION['profile'] == 'Super Admin') {
        $i = 4;
    } else {
        $i = 3;
    }
        
    $techniciansBu = [];
    $techsBu = $users->find([
        '$and' => [
            [
                "subsidiary" => "CFAO MOTORS BURKINA",
                "active" => true,
            ],
        ],
    ])->toArray();
    foreach ($techsBu as $techn) {
        if ($techn["profile"] == "Technicien") {
            array_push($techniciansBu, new MongoDB\BSON\ObjectId($techn['_id']));
        } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
            array_push($techniciansBu, new MongoDB\BSON\ObjectId($techn['_id']));
        }
    }
    $techniciansJuBu = [];
    $techsJuBu = $users->find([
        '$and' => [
            [
                "subsidiary" => "CFAO MOTORS BURKINA",
                "level" => "Junior",
                "active" => true,
            ],
        ],
    ])->toArray();
    foreach ($techsJuBu as $techn) {
        if ($techn["profile"] == "Technicien") {
            array_push($techniciansJuBu, new MongoDB\BSON\ObjectId($techn['_id']));
        } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
            array_push($techniciansJuBu, new MongoDB\BSON\ObjectId($techn['_id']));
        }
    }
    $techniciansSeBu = [];
    $techsSeBu = $users->find([
        '$and' => [
            [
                "subsidiary" => "CFAO MOTORS BURKINA",
                "level" => "Senior",
                "active" => true,
            ],
        ],
    ])->toArray();
    foreach ($techsSeBu as $techn) {
        if ($techn["profile"] == "Technicien") {
            array_push($techniciansSeBu, new MongoDB\BSON\ObjectId($techn['_id']));
        } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
            array_push($techniciansSeBu, new MongoDB\BSON\ObjectId($techn['_id']));
        }
    }
    $techniciansExBu = [];
    $techsExBu = $users->find([
        '$and' => [
            [
                "subsidiary" => "CFAO MOTORS BURKINA",
                "level" => "Expert",
                "active" => true,
            ],
        ],
    ])->toArray();
    foreach ($techsExBu as $techn) {
        if ($techn["profile"] == "Technicien") {
            array_push($techniciansExBu, new MongoDB\BSON\ObjectId($techn['_id']));
        } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
            array_push($techniciansExBu, new MongoDB\BSON\ObjectId($techn['_id']));
        }
    }

    
    $testsJuBu = [];
    $countSavoirsJuBu = [];
    $countMaSavFaisJuBu = [];
    $percentageTestsJuBu = [];
    $testsSeBu = [];
    $countSavoirsSeBu = [];
    $countMaSavFaisSeBu = [];
    $percentageTestsSeBu = [];
    $testsExBu = [];
    $countSavoirsExBu = [];
    $countMaSavFaisExBu = [];
    $percentageTestsExBu = [];
    foreach ($techniciansBu as $technician) { 
        $allocateFacJuBu = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Factuel",
                    "level" => "Junior",
                ],
            ],
        ]);
        $allocateDeclaJuBu = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Declaratif",
                    "level" => "Junior",
                ],
            ],
        ]);
        if (isset($allocateFacJuBu) && $allocateFacJuBu['active'] == true) {
            $countSavoirsJuBu[] = $allocateFacJuBu;
        }
        if (isset($allocateDeclaJuBu) && $allocateDeclaJuBu['activeManager'] == true) {
            $countMaSavFaisJuBu[] = $allocateDeclaJuBu;
        }
        if (isset($allocateDeclaJuBu) && $allocateDeclaJuBu['active'] == true) {
            $percentageTestsJuBu[] = $allocateDeclaJuBu;
        }
        if (isset($allocateFacJuBu) && isset($allocateDeclaJuBu) && $allocateFacJuBu['active'] == true && $allocateDeclaJuBu['active'] == true && $allocateDeclaJuBu['activeManager'] == true) {
            $testsJuBu[] = $technician;
        }
        $allocateFacSeBu = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Factuel",
                    "level" => "Senior",
                ],
            ],
        ]);
        $allocateDeclaSeBu = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Declaratif",
                    "level" => "Senior",
                ],
            ],
        ]);
        if (isset($allocateFacSeBu) && $allocateFacSeBu['active'] == true) {
            $countSavoirsSeBu[] = $allocateFacSeBu;
        }
        if (isset($allocateDeclaSeBu) && $allocateDeclaSeBu['activeManager'] == true) {
            $countMaSavFaisSeBu[] = $allocateDeclaSeBu;
        }
        if (isset($allocateDeclaSeBu) && $allocateDeclaSeBu['active'] == true) {
            $percentageTestsSeBu[] = $allocateDeclaSeBu;
        }
        if (isset($allocateFacSeBu) && isset($allocateDeclaSeBu) && $allocateFacSeBu['active'] == true && $allocateDeclaSeBu['active'] == true && $allocateDeclaSeBu['activeManager'] == true) {
            $testsSeBu[] = $technician;
        }
        $allocateFacExBu = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Factuel",
                    "level" => "Expert",
                ],
            ],
        ]);
        $allocateDeclaExBu = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Declaratif",
                    "level" => "Expert",
                ],
            ],
        ]);
        if (isset($allocateFacExBu) && $allocateFacExBu['active'] == true) {
            $countSavoirsExBu[] = $allocateFacExBu;
        }
        if (isset($allocateDeclaExBu) && $allocateDeclaExBu['activeManager'] == true) {
            $countMaSavFaisExBu[] = $allocateDeclaExBu;
        }
        if (isset($allocateDeclaExBu) && $allocateDeclaExBu['active'] == true) {
            $percentageTestsExBu[] = $allocateDeclaExBu;
        }
        if (isset($allocateFacExBu) && isset($allocateDeclaExBu) && $allocateFacExBu['active'] == true && $allocateDeclaExBu['active'] == true && $allocateDeclaExBu['activeManager'] == true) {
            $testsExBu[] = $technician;
        }
    }   
           
    $techniciansCa = [];
    $techsCa = $users->find([
        '$and' => [
            [
                "subsidiary" => "CAMEROON MOTORS INDUSTRIES",
                "active" => true,
            ],
        ],
    ])->toArray();
    foreach ($techsCa as $techn) {
        if ($techn["profile"] == "Technicien") {
            array_push($techniciansCa, new MongoDB\BSON\ObjectId($techn['_id']));
        } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
            array_push($techniciansCa, new MongoDB\BSON\ObjectId($techn['_id']));
        }
    }
    $techniciansJuCa = [];
    $techsJuCa = $users->find([
        '$and' => [
            [
                "subsidiary" => "CAMEROON MOTORS INDUSTRIES",
                "level" => "Junior",
                "active" => true,
            ],
        ],
    ])->toArray();
    foreach ($techsJuCa as $techn) {
        if ($techn["profile"] == "Technicien") {
            array_push($techniciansJuCa, new MongoDB\BSON\ObjectId($techn['_id']));
        } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
            array_push($techniciansJuCa, new MongoDB\BSON\ObjectId($techn['_id']));
        }
    }
    $techniciansSeCa = [];
    $techsSeCa = $users->find([
        '$and' => [
            [
                "subsidiary" => "CAMEROON MOTORS INDUSTRIES",
                "level" => "Senior",
                "active" => true,
            ],
        ],
    ])->toArray();
    foreach ($techsSeCa as $techn) {
        if ($techn["profile"] == "Technicien") {
            array_push($techniciansSeCa, new MongoDB\BSON\ObjectId($techn['_id']));
        } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
            array_push($techniciansSeCa, new MongoDB\BSON\ObjectId($techn['_id']));
        }
    }
    $techniciansExCa = [];
    $techsExCa = $users->find([
        '$and' => [
            [
                "subsidiary" => "CAMEROON MOTORS INDUSTRIES",
                "level" => "Expert",
                "active" => true,
            ],
        ],
    ])->toArray();
    foreach ($techsExCa as $techn) {
        if ($techn["profile"] == "Technicien") {
            array_push($techniciansExCa, new MongoDB\BSON\ObjectId($techn['_id']));
        } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
            array_push($techniciansExCa, new MongoDB\BSON\ObjectId($techn['_id']));
        }
    }

    
    $testsJuCa = [];
    $countSavoirsJuCa = [];
    $countMaSavFaisJuCa = [];
    $percentageTestsJuCa = [];
    $testsSeCa = [];
    $countSavoirsSeCa = [];
    $countMaSavFaisSeCa = [];
    $percentageTestsSeCa = [];
    $testsExCa = [];
    $countSavoirsExCa = [];
    $countMaSavFaisExCa = [];
    $percentageTestsExCa = [];
    foreach ($techniciansCa as $technician) { 
        $allocateFacJuCa = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Factuel",
                    "level" => "Junior",
                ],
            ],
        ]);
        $allocateDeclaJuCa = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Declaratif",
                    "level" => "Junior",
                ],
            ],
        ]);
        if (isset($allocateFacJuCa) && $allocateFacJuCa['active'] == true) {
            $countSavoirsJuCa[] = $allocateFacJuCa;
        }
        if (isset($allocateDeclaJuCa) && $allocateDeclaJuCa['activeManager'] == true) {
            $countMaSavFaisJuCa[] = $allocateDeclaJuCa;
        }
        if (isset($allocateDeclaJuCa) && $allocateDeclaJuCa['active'] == true) {
            $percentageTestsJuCa[] = $allocateDeclaJuCa;
        }
        if (isset($allocateFacJuCa) && isset($allocateDeclaJuCa) && $allocateFacJuCa['active'] == true && $allocateDeclaJuCa['active'] == true && $allocateDeclaJuCa['activeManager'] == true) {
            $testsJuCa[] = $technician;
        }
        $allocateFacSeCa = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Factuel",
                    "level" => "Senior",
                ],
            ],
        ]);
        $allocateDeclaSeCa = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Declaratif",
                    "level" => "Senior",
                ],
            ],
        ]);
        if (isset($allocateFacSeCa) && $allocateFacSeCa['active'] == true) {
            $countSavoirsSeCa[] = $allocateFacSeCa;
        }
        if (isset($allocateDeclaSeCa) && $allocateDeclaSeCa['activeManager'] == true) {
            $countMaSavFaisSeCa[] = $allocateDeclaSeCa;
        }
        if (isset($allocateDeclaSeCa) && $allocateDeclaSeCa['active'] == true) {
            $percentageTestsSeCa[] = $allocateDeclaSeCa;
        }
        if (isset($allocateFacSeCa) && isset($allocateDeclaSeCa) && $allocateFacSeCa['active'] == true && $allocateDeclaSeCa['active'] == true && $allocateDeclaSeCa['activeManager'] == true) {
            $testsSeCa[] = $technician;
        }
        $allocateFacExCa = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Factuel",
                    "level" => "Expert",
                ],
            ],
        ]);
        $allocateDeclaExCa = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Declaratif",
                    "level" => "Expert",
                ],
            ],
        ]);
        if (isset($allocateFacExCa) && $allocateFacExCa['active'] == true) {
            $countSavoirsExCa[] = $allocateFacExCa;
        }
        if (isset($allocateDeclaExCa) && $allocateDeclaExCa['activeManager'] == true) {
            $countMaSavFaisExCa[] = $allocateDeclaExCa;
        }
        if (isset($allocateDeclaExCa) && $allocateDeclaExCa['active'] == true) {
            $percentageTestsExCa[] = $allocateDeclaExCa;
        }
        if (isset($allocateFacExCa) && isset($allocateDeclaExCa) && $allocateFacExCa['active'] == true && $allocateDeclaExCa['active'] == true && $allocateDeclaExCa['activeManager'] == true) {
            $testsExCa[] = $technician;
        }
    }
        
    $techniciansCo = [];
    $techsCo = $users->find([
        '$and' => [
            [
                "subsidiary" => "CFAO MOTORS CONGO",
                "active" => true,
            ],
        ],
    ])->toArray();
    foreach ($techsCo as $techn) {
        if ($techn["profile"] == "Technicien") {
            array_push($techniciansCo, new MongoDB\BSON\ObjectId($techn['_id']));
        } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
            array_push($techniciansCo, new MongoDB\BSON\ObjectId($techn['_id']));
        }
    }
    $techniciansJuCo = [];
    $techsJuCo = $users->find([
        '$and' => [
            [
                "subsidiary" => "CFAO MOTORS CONGO",
                "level" => "Junior",
                "active" => true,
            ],
        ],
    ])->toArray();
    foreach ($techsJuCo as $techn) {
        if ($techn["profile"] == "Technicien") {
            array_push($techniciansJuCo, new MongoDB\BSON\ObjectId($techn['_id']));
        } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
            array_push($techniciansJuCo, new MongoDB\BSON\ObjectId($techn['_id']));
        }
    }
    $techniciansSeCo = [];
    $techsSeCo = $users->find([
        '$and' => [
            [
                "subsidiary" => "CFAO MOTORS CONGO",
                "level" => "Senior",
                "active" => true,
            ],
        ],
    ])->toArray();
    foreach ($techsSeCo as $techn) {
        if ($techn["profile"] == "Technicien") {
            array_push($techniciansSeCo, new MongoDB\BSON\ObjectId($techn['_id']));
        } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
            array_push($techniciansSeCo, new MongoDB\BSON\ObjectId($techn['_id']));
        }
    }
    $techniciansExCo = [];
    $techsExCo = $users->find([
        '$and' => [
            [
                "subsidiary" => "CFAO MOTORS CONGO",
                "level" => "Expert",
                "active" => true,
            ],
        ],
    ])->toArray();
    foreach ($techsExCo as $techn) {
        if ($techn["profile"] == "Technicien") {
            array_push($techniciansExCo, new MongoDB\BSON\ObjectId($techn['_id']));
        } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
            array_push($techniciansExCo, new MongoDB\BSON\ObjectId($techn['_id']));
        }
    }

    
    $testsJuCo = [];
    $countSavoirsJuCo = [];
    $countMaSavFaisJuCo = [];
    $percentageTestsJuCo = [];
    $testsSeCo = [];
    $countSavoirsSeCo = [];
    $countMaSavFaisSeCo = [];
    $percentageTestsSeCo = [];
    $testsExCo = [];
    $countSavoirsExCo = [];
    $countMaSavFaisExCo = [];
    $percentageTestsExCo = [];
    foreach ($techniciansCo as $technician) { 
        $allocateFacJuCo = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Factuel",
                    "level" => "Junior",
                ],
            ],
        ]);
        $allocateDeclaJuCo = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Declaratif",
                    "level" => "Junior",
                ],
            ],
        ]);
        if (isset($allocateFacJuCo) && $allocateFacJuCo['active'] == true) {
            $countSavoirsJuCo[] = $allocateFacJuCo;
        }
        if (isset($allocateDeclaJuCo) && $allocateDeclaJuCo['activeManager'] == true) {
            $countMaSavFaisJuCo[] = $allocateDeclaJuCo;
        }
        if (isset($allocateDeclaJuCo) && $allocateDeclaJuCo['active'] == true) {
            $percentageTestsJuCo[] = $allocateDeclaJuCo;
        }
        if (isset($allocateFacJuCo) && isset($allocateDeclaJuCo) && $allocateFacJuCo['active'] == true && $allocateDeclaJuCo['active'] == true && $allocateDeclaJuCo['activeManager'] == true) {
            $testsJuCo[] = $technician;
        }
        $allocateFacSeCo = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Factuel",
                    "level" => "Senior",
                ],
            ],
        ]);
        $allocateDeclaSeCo = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Declaratif",
                    "level" => "Senior",
                ],
            ],
        ]);
        if (isset($allocateFacSeCo) && $allocateFacSeCo['active'] == true) {
            $countSavoirsSeCo[] = $allocateFacSeCo;
        }
        if (isset($allocateDeclaSeCo) && $allocateDeclaSeCo['activeManager'] == true) {
            $countMaSavFaisSeCo[] = $allocateDeclaSeCo;
        }
        if (isset($allocateDeclaSeCo) && $allocateDeclaSeCo['active'] == true) {
            $percentageTestsSeCo[] = $allocateDeclaSeCo;
        }
        if (isset($allocateFacSeCo) && isset($allocateDeclaSeCo) && $allocateFacSeCo['active'] == true && $allocateDeclaSeCo['active'] == true && $allocateDeclaSeCo['activeManager'] == true) {
            $testsSeCo[] = $technician;
        }
        $allocateFacExCo = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Factuel",
                    "level" => "Expert",
                ],
            ],
        ]);
        $allocateDeclaExCo = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Declaratif",
                    "level" => "Expert",
                ],
            ],
        ]);
        if (isset($allocateFacExCo) && $allocateFacExCo['active'] == true) {
            $countSavoirsExCo[] = $allocateFacExCo;
        }
        if (isset($allocateDeclaExCo) && $allocateDeclaExCo['activeManager'] == true) {
            $countMaSavFaisExCo[] = $allocateDeclaExCo;
        }
        if (isset($allocateDeclaExCo) && $allocateDeclaExCo['active'] == true) {
            $percentageTestsExCo[] = $allocateDeclaExCo;
        }
        if (isset($allocateFacExCo) && isset($allocateDeclaExCo) && $allocateFacExCo['active'] == true && $allocateDeclaExCo['active'] == true && $allocateDeclaExCo['activeManager'] == true) {
            $testsExCo[] = $technician;
        }
    }
        
    $techniciansRci = [];
    $techsRci = $users->find([
        '$and' => [
            [
                "subsidiary" => "CFAO MOTORS COTE D'IVOIRE",
                "active" => true,
            ],
        ],
    ])->toArray();
    foreach ($techsRci as $techn) {
        if ($techn["profile"] == "Technicien") {
            array_push($techniciansRci, new MongoDB\BSON\ObjectId($techn['_id']));
        } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
            array_push($techniciansRci, new MongoDB\BSON\ObjectId($techn['_id']));
        }
    }
    $techniciansJuRci = [];
    $techsJuRci = $users->find([
        '$and' => [
            [
                "subsidiary" => "CFAO MOTORS COTE D'IVOIRE",
                "level" => "Junior",
                "active" => true,
            ],
        ],
    ])->toArray();
    foreach ($techsJuRci as $techn) {
        if ($techn["profile"] == "Technicien") {
            array_push($techniciansJuRci, new MongoDB\BSON\ObjectId($techn['_id']));
        } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
            array_push($techniciansJuRci, new MongoDB\BSON\ObjectId($techn['_id']));
        }
    }
    $techniciansSeRci = [];
    $techsSeRci = $users->find([
        '$and' => [
            [
                "subsidiary" => "CFAO MOTORS COTE D'IVOIRE",
                "level" => "Senior",
                "active" => true,
            ],
        ],
    ])->toArray();
    foreach ($techsSeRci as $techn) {
        if ($techn["profile"] == "Technicien") {
            array_push($techniciansSeRci, new MongoDB\BSON\ObjectId($techn['_id']));
        } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
            array_push($techniciansSeRci, new MongoDB\BSON\ObjectId($techn['_id']));
        }
    }
    $techniciansExRci = [];
    $techsExRci = $users->find([
        '$and' => [
            [
                "subsidiary" => "CFAO MOTORS COTE D'IVOIRE",
                "level" => "Expert",
                "active" => true,
            ],
        ],
    ])->toArray();
    foreach ($techsExRci as $techn) {
        if ($techn["profile"] == "Technicien") {
            array_push($techniciansExRci, new MongoDB\BSON\ObjectId($techn['_id']));
        } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
            array_push($techniciansExRci, new MongoDB\BSON\ObjectId($techn['_id']));
        }
    }

    
    $testsJuRci = [];
    $countSavoirsJuRci = [];
    $countMaSavFaisJuRci = [];
    $percentageTestsJuRci = [];
    $testsSeRci = [];
    $countSavoirsSeRci = [];
    $countMaSavFaisSeRci = [];
    $percentageTestsSeRci = [];
    $testsExRci = [];
    $countSavoirsExRci = [];
    $countMaSavFaisExRci = [];
    $percentageTestsExRci = [];
    foreach ($techniciansRci as $technician) { 
        $allocateFacJuRci = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Factuel",
                    "level" => "Junior",
                ],
            ],
        ]);
        $allocateDeclaJuRci = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Declaratif",
                    "level" => "Junior",
                ],
            ],
        ]);
        if (isset($allocateFacJuRci) && $allocateFacJuRci['active'] == true) {
            $countSavoirsJuRci[] = $allocateFacJuRci;
        }
        if (isset($allocateDeclaJuRci) && $allocateDeclaJuRci['activeManager'] == true) {
            $countMaSavFaisJuRci[] = $allocateDeclaJuRci;
        }
        if (isset($allocateDeclaJuRci) && $allocateDeclaJuRci['active'] == true) {
            $percentageTestsJuRci[] = $allocateDeclaJuRci;
        }
        if (isset($allocateFacJuRci) && isset($allocateDeclaJuRci) && $allocateFacJuRci['active'] == true && $allocateDeclaJuRci['active'] == true && $allocateDeclaJuRci['activeManager'] == true) {
            $testsJuRci[] = $technician;
        }
        $allocateFacSeRci = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Factuel",
                    "level" => "Senior",
                ],
            ],
        ]);
        $allocateDeclaSeRci = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Declaratif",
                    "level" => "Senior",
                ],
            ],
        ]);
        if (isset($allocateFacSeRci) && $allocateFacSeRci['active'] == true) {
            $countSavoirsSeRci[] = $allocateFacSeRci;
        }
        if (isset($allocateDeclaSeRci) && $allocateDeclaSeRci['activeManager'] == true) {
            $countMaSavFaisSeRci[] = $allocateDeclaSeRci;
        }
        if (isset($allocateDeclaSeRci) && $allocateDeclaSeRci['active'] == true) {
            $percentageTestsSeRci[] = $allocateDeclaSeRci;
        }
        if (isset($allocateFacSeRci) && isset($allocateDeclaSeRci) && $allocateFacSeRci['active'] == true && $allocateDeclaSeRci['active'] == true && $allocateDeclaSeRci['activeManager'] == true) {
            $testsSeRci[] = $technician;
        }
        $allocateFacExRci = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Factuel",
                    "level" => "Expert",
                ],
            ],
        ]);
        $allocateDeclaExRci = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Declaratif",
                    "level" => "Expert",
                ],
            ],
        ]);
        if (isset($allocateFacExRci) && $allocateFacExRci['active'] == true) {
            $countSavoirsExRci[] = $allocateFacExRci;
        }
        if (isset($allocateDeclaExRci) && $allocateDeclaExRci['activeManager'] == true) {
            $countMaSavFaisExRci[] = $allocateDeclaExRci;
        }
        if (isset($allocateDeclaExRci) && $allocateDeclaExRci['active'] == true) {
            $percentageTestsExRci[] = $allocateDeclaExRci;
        }
        if (isset($allocateFacExRci) && isset($allocateDeclaExRci) && $allocateFacExRci['active'] == true && $allocateDeclaExRci['active'] == true && $allocateDeclaExRci['activeManager'] == true) {
            $testsExRci[] = $technician;
        }
    }
        
    $techniciansGa = [];
    $techsGa = $users->find([
        '$and' => [
            [
                "subsidiary" => "CFAO MOTORS GABON",
                "active" => true,
            ],
        ],
    ])->toArray();
    foreach ($techsGa as $techn) {
        if ($techn["profile"] == "Technicien") {
            array_push($techniciansGa, new MongoDB\BSON\ObjectId($techn['_id']));
        } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
            array_push($techniciansGa, new MongoDB\BSON\ObjectId($techn['_id']));
        }
    }
    $techniciansJuGa = [];
    $techsJuGa = $users->find([
        '$and' => [
            [
                "subsidiary" => "CFAO MOTORS GABON",
                "level" => "Junior",
                "active" => true,
            ],
        ],
    ])->toArray();
    foreach ($techsJuGa as $techn) {
        if ($techn["profile"] == "Technicien") {
            array_push($techniciansJuGa, new MongoDB\BSON\ObjectId($techn['_id']));
        } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
            array_push($techniciansJuGa, new MongoDB\BSON\ObjectId($techn['_id']));
        }
    }
    $techniciansSeGa = [];
    $techsSeGa = $users->find([
        '$and' => [
            [
                "subsidiary" => "CFAO MOTORS GABON",
                "level" => "Senior",
                "active" => true,
            ],
        ],
    ])->toArray();
    foreach ($techsSeGa as $techn) {
        if ($techn["profile"] == "Technicien") {
            array_push($techniciansSeGa, new MongoDB\BSON\ObjectId($techn['_id']));
        } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
            array_push($techniciansSeGa, new MongoDB\BSON\ObjectId($techn['_id']));
        }
    }
    $techniciansExGa = [];
    $techsExGa = $users->find([
        '$and' => [
            [
                "subsidiary" => "CFAO MOTORS GABON",
                "level" => "Expert",
                "active" => true,
            ],
        ],
    ])->toArray();
    foreach ($techsExGa as $techn) {
        if ($techn["profile"] == "Technicien") {
            array_push($techniciansExGa, new MongoDB\BSON\ObjectId($techn['_id']));
        } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
            array_push($techniciansExGa, new MongoDB\BSON\ObjectId($techn['_id']));
        }
    }

    
    $testsJuGa = [];
    $countSavoirsJuGa = [];
    $countMaSavFaisJuGa = [];
    $percentageTestsJuGa = [];
    $testsSeGa = [];
    $countSavoirsSeGa = [];
    $countMaSavFaisSeGa = [];
    $percentageTestsSeGa = [];
    $testsExGa = [];
    $countSavoirsExGa = [];
    $countMaSavFaisExGa = [];
    $percentageTestsExGa = [];
    foreach ($techniciansGa as $technician) { 
        $allocateFacJuGa = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Factuel",
                    "level" => "Junior",
                ],
            ],
        ]);
        $allocateDeclaJuGa = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Declaratif",
                    "level" => "Junior",
                ],
            ],
        ]);
        if (isset($allocateFacJuGa) && $allocateFacJuGa['active'] == true) {
            $countSavoirsJuGa[] = $allocateFacJuGa;
        }
        if (isset($allocateDeclaJuGa) && $allocateDeclaJuGa['activeManager'] == true) {
            $countMaSavFaisJuGa[] = $allocateDeclaJuGa;
        }
        if (isset($allocateDeclaJuGa) && $allocateDeclaJuGa['active'] == true) {
            $percentageTestsJuGa[] = $allocateDeclaJuGa;
        }
        if (isset($allocateFacJuGa) && isset($allocateDeclaJuGa) && $allocateFacJuGa['active'] == true && $allocateDeclaJuGa['active'] == true && $allocateDeclaJuGa['activeManager'] == true) {
            $testsJuGa[] = $technician;
        }
        $allocateFacSeGa = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Factuel",
                    "level" => "Senior",
                ],
            ],
        ]);
        $allocateDeclaSeGa = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Declaratif",
                    "level" => "Senior",
                ],
            ],
        ]);
        if (isset($allocateFacSeGa) && $allocateFacSeGa['active'] == true) {
            $countSavoirsSeGa[] = $allocateFacSeGa;
        }
        if (isset($allocateDeclaSeGa) && $allocateDeclaSeGa['activeManager'] == true) {
            $countMaSavFaisSeGa[] = $allocateDeclaSeGa;
        }
        if (isset($allocateDeclaSeGa) && $allocateDeclaSeGa['active'] == true) {
            $percentageTestsSeGa[] = $allocateDeclaSeGa;
        }
        if (isset($allocateFacSeGa) && isset($allocateDeclaSeGa) && $allocateFacSeGa['active'] == true && $allocateDeclaSeGa['active'] == true && $allocateDeclaSeGa['activeManager'] == true) {
            $testsSeGa[] = $technician;
        }
        $allocateFacExGa = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Factuel",
                    "level" => "Expert",
                ],
            ],
        ]);
        $allocateDeclaExGa = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Declaratif",
                    "level" => "Expert",
                ],
            ],
        ]);
        if (isset($allocateFacExGa) && $allocateFacExGa['active'] == true) {
            $countSavoirsExGa[] = $allocateFacExGa;
        }
        if (isset($allocateDeclaExGa) && $allocateDeclaExGa['activeManager'] == true) {
            $countMaSavFaisExGa[] = $allocateDeclaExGa;
        }
        if (isset($allocateDeclaExGa) && $allocateDeclaExGa['active'] == true) {
            $percentageTestsExGa[] = $allocateDeclaExGa;
        }
        if (isset($allocateFacExGa) && isset($allocateDeclaExGa) && $allocateFacExGa['active'] == true && $allocateDeclaExGa['active'] == true && $allocateDeclaExGa['activeManager'] == true) {
            $testsExGa[] = $technician;
        }
    }
        
    $techniciansMali = [];
    $techsMali = $users->find([
        '$and' => [
            [
                "subsidiary" => "CFAO MOTORS MALI",
                "active" => true,
            ],
        ],
    ])->toArray();
    foreach ($techsMali as $techn) {
        if ($techn["profile"] == "Technicien") {
            array_push($techniciansMali, new MongoDB\BSON\ObjectId($techn['_id']));
        } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
            array_push($techniciansMali, new MongoDB\BSON\ObjectId($techn['_id']));
        }
    }
    $techniciansJuMali = [];
    $techsJuMali = $users->find([
        '$and' => [
            [
                "subsidiary" => "CFAO MOTORS MALI",
                "level" => "Junior",
                "active" => true,
            ],
        ],
    ])->toArray();
    foreach ($techsJuMali as $techn) {
        if ($techn["profile"] == "Technicien") {
            array_push($techniciansJuMali, new MongoDB\BSON\ObjectId($techn['_id']));
        } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
            array_push($techniciansJuMali, new MongoDB\BSON\ObjectId($techn['_id']));
        }
    }
    $techniciansSeMali = [];
    $techsSeMali = $users->find([
        '$and' => [
            [
                "subsidiary" => "CFAO MOTORS MALI",
                "level" => "Senior",
                "active" => true,
            ],
        ],
    ])->toArray();
    foreach ($techsSeMali as $techn) {
        if ($techn["profile"] == "Technicien") {
            array_push($techniciansSeMali, new MongoDB\BSON\ObjectId($techn['_id']));
        } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
            array_push($techniciansSeMali, new MongoDB\BSON\ObjectId($techn['_id']));
        }
    }
    $techniciansExMali = [];
    $techsExMali = $users->find([
        '$and' => [
            [
                "subsidiary" => "CFAO MOTORS MALI",
                "level" => "Expert",
                "active" => true,
            ],
        ],
    ])->toArray();
    foreach ($techsExMali as $techn) {
        if ($techn["profile"] == "Technicien") {
            array_push($techniciansExMali, new MongoDB\BSON\ObjectId($techn['_id']));
        } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
            array_push($techniciansExMali, new MongoDB\BSON\ObjectId($techn['_id']));
        }
    }

    
    $testsJuMali = [];
    $countSavoirsJuMali = [];
    $countMaSavFaisJuMali = [];
    $percentageTestsJuMali = [];
    $testsSeMali = [];
    $countSavoirsSeMali = [];
    $countMaSavFaisSeMali = [];
    $percentageTestsSeMali = [];
    $testsExMali = [];
    $countSavoirsExMali = [];
    $countMaSavFaisExMali = [];
    $percentageTestsExMali = [];
    foreach ($techniciansMali as $technician) { 
        $allocateFacJuMali = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Factuel",
                    "level" => "Junior",
                ],
            ],
        ]);
        $allocateDeclaJuMali = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Declaratif",
                    "level" => "Junior",
                ],
            ],
        ]);
        if (isset($allocateFacJuMali) && $allocateFacJuMali['active'] == true) {
            $countSavoirsJuMali[] = $allocateFacJuMali;
        }
        if (isset($allocateDeclaJuMali) && $allocateDeclaJuMali['activeManager'] == true) {
            $countMaSavFaisJuMali[] = $allocateDeclaJuMali;
        }
        if (isset($allocateDeclaJuMali) && $allocateDeclaJuMali['active'] == true) {
            $percentageTestsJuMali[] = $allocateDeclaJuMali;
        }
        if (isset($allocateFacJuMali) && isset($allocateDeclaJuMali) && $allocateFacJuMali['active'] == true && $allocateDeclaJuMali['active'] == true && $allocateDeclaJuMali['activeManager'] == true) {
            $testsJuMali[] = $technician;
        }
        $allocateFacSeMali = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Factuel",
                    "level" => "Senior",
                ],
            ],
        ]);
        $allocateDeclaSeMali = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Declaratif",
                    "level" => "Senior",
                ],
            ],
        ]);
        if (isset($allocateFacSeMali) && $allocateFacSeMali['active'] == true) {
            $countSavoirsSeMali[] = $allocateFacSeMali;
        }
        if (isset($allocateDeclaSeMali) && $allocateDeclaSeMali['activeManager'] == true) {
            $countMaSavFaisSeMali[] = $allocateDeclaSeMali;
        }
        if (isset($allocateDeclaSeMali) && $allocateDeclaSeMali['active'] == true) {
            $percentageTestsSeMali[] = $allocateDeclaSeMali;
        }
        if (isset($allocateFacSeMali) && isset($allocateDeclaSeMali) && $allocateFacSeMali['active'] == true && $allocateDeclaSeMali['active'] == true && $allocateDeclaSeMali['activeManager'] == true) {
            $testsSeMali[] = $technician;
        }
        $allocateFacExMali = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Factuel",
                    "level" => "Expert",
                ],
            ],
        ]);
        $allocateDeclaExMali = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Declaratif",
                    "level" => "Expert",
                ],
            ],
        ]);
        if (isset($allocateFacExMali) && $allocateFacExMali['active'] == true) {
            $countSavoirsExMali[] = $allocateFacExMali;
        }
        if (isset($allocateDeclaExMali) && $allocateDeclaExMali['activeManager'] == true) {
            $countMaSavFaisExMali[] = $allocateDeclaExMali;
        }
        if (isset($allocateDeclaExMali) && $allocateDeclaExMali['active'] == true) {
            $percentageTestsExMali[] = $allocateDeclaExMali;
        }
        if (isset($allocateFacExMali) && isset($allocateDeclaExMali) && $allocateFacExMali['active'] == true && $allocateDeclaExMali['active'] == true && $allocateDeclaExMali['activeManager'] == true) {
            $testsExMali[] = $technician;
        }
    }
        
    $techniciansRca = [];
    $techsRca = $users->find([
        '$and' => [
            [
                "subsidiary" => "CFAO MOTORS CENTRAFRIQUE",
                "active" => true,
            ],
        ],
    ])->toArray();
    foreach ($techsRca as $techn) {
        if ($techn["profile"] == "Technicien") {
            array_push($techniciansRca, new MongoDB\BSON\ObjectId($techn['_id']));
        } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
            array_push($techniciansRca, new MongoDB\BSON\ObjectId($techn['_id']));
        }
    }
    $techniciansJuRca = [];
    $techsJuRca = $users->find([
        '$and' => [
            [
                "subsidiary" => "CFAO MOTORS CENTRAFRIQUE",
                "level" => "Junior",
                "active" => true,
            ],
        ],
    ])->toArray();
    foreach ($techsJuRca as $techn) {
        if ($techn["profile"] == "Technicien") {
            array_push($techniciansJuRca, new MongoDB\BSON\ObjectId($techn['_id']));
        } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
            array_push($techniciansJuRca, new MongoDB\BSON\ObjectId($techn['_id']));
        }
    }
    $techniciansSeRca = [];
    $techsSeRca = $users->find([
        '$and' => [
            [
                "subsidiary" => "CFAO MOTORS CENTRAFRIQUE",
                "level" => "Senior",
                "active" => true,
            ],
        ],
    ])->toArray();
    foreach ($techsSeRca as $techn) {
        if ($techn["profile"] == "Technicien") {
            array_push($techniciansSeRca, new MongoDB\BSON\ObjectId($techn['_id']));
        } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
            array_push($techniciansSeRca, new MongoDB\BSON\ObjectId($techn['_id']));
        }
    }
    $techniciansExRca = [];
    $techsExRca = $users->find([
        '$and' => [
            [
                "subsidiary" => "CFAO MOTORS CENTRAFRIQUE",
                "level" => "Expert",
                "active" => true,
            ],
        ],
    ])->toArray();
    foreach ($techsExRca as $techn) {
        if ($techn["profile"] == "Technicien") {
            array_push($techniciansExRca, new MongoDB\BSON\ObjectId($techn['_id']));
        } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
            array_push($techniciansExRca, new MongoDB\BSON\ObjectId($techn['_id']));
        }
    }

    
    $testsJuRca = [];
    $countSavoirsJuRca = [];
    $countMaSavFaisJuRca = [];
    $percentageTestsJuRca = [];
    $testsSeRca = [];
    $countSavoirsSeRca = [];
    $countMaSavFaisSeRca = [];
    $percentageTestsSeRca = [];
    $testsExRca = [];
    $countSavoirsExRca = [];
    $countMaSavFaisExRca = [];
    $percentageTestsExRca = [];
    foreach ($techniciansRca as $technician) { 
        $allocateFacJuRca = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Factuel",
                    "level" => "Junior",
                ],
            ],
        ]);
        $allocateDeclaJuRca = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Declaratif",
                    "level" => "Junior",
                ],
            ],
        ]);
        if (isset($allocateFacJuRca) && $allocateFacJuRca['active'] == true) {
            $countSavoirsJuRca[] = $allocateFacJuRca;
        }
        if (isset($allocateDeclaJuRca) && $allocateDeclaJuRca['activeManager'] == true) {
            $countMaSavFaisJuRca[] = $allocateDeclaJuRca;
        }
        if (isset($allocateDeclaJuRca) && $allocateDeclaJuRca['active'] == true) {
            $percentageTestsJuRca[] = $allocateDeclaJuRca;
        }
        if (isset($allocateFacJuRca) && isset($allocateDeclaJuRca) && $allocateFacJuRca['active'] == true && $allocateDeclaJuRca['active'] == true && $allocateDeclaJuRca['activeManager'] == true) {
            $testsJuRca[] = $technician;
        }
        $allocateFacSeRca = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Factuel",
                    "level" => "Senior",
                ],
            ],
        ]);
        $allocateDeclaSeRca = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Declaratif",
                    "level" => "Senior",
                ],
            ],
        ]);
        if (isset($allocateFacSeRca) && $allocateFacSeRca['active'] == true) {
            $countSavoirsSeRca[] = $allocateFacSeRca;
        }
        if (isset($allocateDeclaSeRca) && $allocateDeclaSeRca['activeManager'] == true) {
            $countMaSavFaisSeRca[] = $allocateDeclaSeRca;
        }
        if (isset($allocateDeclaSeRca) && $allocateDeclaSeRca['active'] == true) {
            $percentageTestsSeRca[] = $allocateDeclaSeRca;
        }
        if (isset($allocateFacSeRca) && isset($allocateDeclaSeRca) && $allocateFacSeRca['active'] == true && $allocateDeclaSeRca['active'] == true && $allocateDeclaSeRca['activeManager'] == true) {
            $testsSeRca[] = $technician;
        }
        $allocateFacExRca = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Factuel",
                    "level" => "Expert",
                ],
            ],
        ]);
        $allocateDeclaExRca = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Declaratif",
                    "level" => "Expert",
                ],
            ],
        ]);
        if (isset($allocateFacExRca) && $allocateFacExRca['active'] == true) {
            $countSavoirsExRca[] = $allocateFacExRca;
        }
        if (isset($allocateDeclaExRca) && $allocateDeclaExRca['activeManager'] == true) {
            $countMaSavFaisExRca[] = $allocateDeclaExRca;
        }
        if (isset($allocateDeclaExRca) && $allocateDeclaExRca['active'] == true) {
            $percentageTestsExRca[] = $allocateDeclaExRca;
        }
        if (isset($allocateFacExRca) && isset($allocateDeclaExRca) && $allocateFacExRca['active'] == true && $allocateDeclaExRca['active'] == true && $allocateDeclaExRca['activeManager'] == true) {
            $testsExRca[] = $technician;
        }
    }
        
    $techniciansRdc = [];
    $techsRdc = $users->find([
        '$and' => [
            [
                "subsidiary" => "CFAO MOTORS RDC",
                "active" => true,
            ],
        ],
    ])->toArray();
    foreach ($techsRdc as $techn) {
        if ($techn["profile"] == "Technicien") {
            array_push($techniciansRdc, new MongoDB\BSON\ObjectId($techn['_id']));
        } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
            array_push($techniciansRdc, new MongoDB\BSON\ObjectId($techn['_id']));
        }
    }
    $techniciansJuRdc = [];
    $techsJuRdc = $users->find([
        '$and' => [
            [
                "subsidiary" => "CFAO MOTORS RDC",
                "level" => "Junior",
                "active" => true,
            ],
        ],
    ])->toArray();
    foreach ($techsJuRdc as $techn) {
        if ($techn["profile"] == "Technicien") {
            array_push($techniciansJuRdc, new MongoDB\BSON\ObjectId($techn['_id']));
        } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
            array_push($techniciansJuRdc, new MongoDB\BSON\ObjectId($techn['_id']));
        }
    }
    $techniciansSeRdc = [];
    $techsSeRdc = $users->find([
        '$and' => [
            [
                "subsidiary" => "CFAO MOTORS RDC",
                "level" => "Senior",
                "active" => true,
            ],
        ],
    ])->toArray();
    foreach ($techsSeRdc as $techn) {
        if ($techn["profile"] == "Technicien") {
            array_push($techniciansSeRdc, new MongoDB\BSON\ObjectId($techn['_id']));
        } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
            array_push($techniciansSeRdc, new MongoDB\BSON\ObjectId($techn['_id']));
        }
    }
    $techniciansExRdc = [];
    $techsExRdc = $users->find([
        '$and' => [
            [
                "subsidiary" => "CFAO MOTORS RDC",
                "level" => "Expert",
                "active" => true,
            ],
        ],
    ])->toArray();
    foreach ($techsExRdc as $techn) {
        if ($techn["profile"] == "Technicien") {
            array_push($techniciansExRdc, new MongoDB\BSON\ObjectId($techn['_id']));
        } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
            array_push($techniciansExRdc, new MongoDB\BSON\ObjectId($techn['_id']));
        }
    }

    
    $testsJuRdc = [];
    $countSavoirsJuRdc = [];
    $countMaSavFaisJuRdc = [];
    $percentageTestsJuRdc = [];
    $testsSeRdc = [];
    $countSavoirsSeRdc = [];
    $countMaSavFaisSeRdc = [];
    $percentageTestsSeRdc = [];
    $testsExRdc = [];
    $countSavoirsExRdc = [];
    $countMaSavFaisExRdc = [];
    $percentageTestsExRdc = [];
    foreach ($techniciansRdc as $technician) { 
        $allocateFacJuRdc = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Factuel",
                    "level" => "Junior",
                ],
            ],
        ]);
        $allocateDeclaJuRdc = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Declaratif",
                    "level" => "Junior",
                ],
            ],
        ]);
        if (isset($allocateFacJuRdc) && $allocateFacJuRdc['active'] == true) {
            $countSavoirsJuRdc[] = $allocateFacJuRdc;
        }
        if (isset($allocateDeclaJuRdc) && $allocateDeclaJuRdc['activeManager'] == true) {
            $countMaSavFaisJuRdc[] = $allocateDeclaJuRdc;
        }
        if (isset($allocateDeclaJuRdc) && $allocateDeclaJuRdc['active'] == true) {
            $percentageTestsJuRdc[] = $allocateDeclaJuRdc;
        }
        if (isset($allocateFacJuRdc) && isset($allocateDeclaJuRdc) && $allocateFacJuRdc['active'] == true && $allocateDeclaJuRdc['active'] == true && $allocateDeclaJuRdc['activeManager'] == true) {
            $testsJuRdc[] = $technician;
        }
        $allocateFacSeRdc = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Factuel",
                    "level" => "Senior",
                ],
            ],
        ]);
        $allocateDeclaSeRdc = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Declaratif",
                    "level" => "Senior",
                ],
            ],
        ]);
        if (isset($allocateFacSeRdc) && $allocateFacSeRdc['active'] == true) {
            $countSavoirsSeRdc[] = $allocateFacSeRdc;
        }
        if (isset($allocateDeclaSeRdc) && $allocateDeclaSeRdc['activeManager'] == true) {
            $countMaSavFaisSeRdc[] = $allocateDeclaSeRdc;
        }
        if (isset($allocateDeclaSeRdc) && $allocateDeclaSeRdc['active'] == true) {
            $percentageTestsSeRdc[] = $allocateDeclaSeRdc;
        }
        if (isset($allocateFacSeRdc) && isset($allocateDeclaSeRdc) && $allocateFacSeRdc['active'] == true && $allocateDeclaSeRdc['active'] == true && $allocateDeclaSeRdc['activeManager'] == true) {
            $testsSeRdc[] = $technician;
        }
        $allocateFacExRdc = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Factuel",
                    "level" => "Expert",
                ],
            ],
        ]);
        $allocateDeclaExRdc = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Declaratif",
                    "level" => "Expert",
                ],
            ],
        ]);
        if (isset($allocateFacExRdc) && $allocateFacExRdc['active'] == true) {
            $countSavoirsExRdc[] = $allocateFacExRdc;
        }
        if (isset($allocateDeclaExRdc) && $allocateDeclaExRdc['activeManager'] == true) {
            $countMaSavFaisExRdc[] = $allocateDeclaExRdc;
        }
        if (isset($allocateDeclaExRdc) && $allocateDeclaExRdc['active'] == true) {
            $percentageTestsExRdc[] = $allocateDeclaExRdc;
        }
        if (isset($allocateFacExRdc) && isset($allocateDeclaExRdc) && $allocateFacExRdc['active'] == true && $allocateDeclaExRdc['active'] == true && $allocateDeclaExRdc['activeManager'] == true) {
            $testsExRdc[] = $technician;
        }
    }
        
    $techniciansSe = [];
    $techsSe = $users->find([
        '$and' => [
            [
                "subsidiary" => "CFAO MOTORS SENEGAL",
                "active" => true,
            ],
        ],
    ])->toArray();
    foreach ($techsSe as $techn) {
        if ($techn["profile"] == "Technicien") {
            array_push($techniciansSe, new MongoDB\BSON\ObjectId($techn['_id']));
        } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
            array_push($techniciansSe, new MongoDB\BSON\ObjectId($techn['_id']));
        }
    }
    $techniciansJuSe = [];
    $techsJuSe = $users->find([
        '$and' => [
            [
                "subsidiary" => "CFAO MOTORS SENEGAL",
                "level" => "Junior",
                "active" => true,
            ],
        ],
    ])->toArray();
    foreach ($techsJuSe as $techn) {
        if ($techn["profile"] == "Technicien") {
            array_push($techniciansJuSe, new MongoDB\BSON\ObjectId($techn['_id']));
        } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
            array_push($techniciansJuSe, new MongoDB\BSON\ObjectId($techn['_id']));
        }
    }
    $techniciansSeSe = [];
    $techsSeSe = $users->find([
        '$and' => [
            [
                "subsidiary" => "CFAO MOTORS SENEGAL",
                "level" => "Senior",
                "active" => true,
            ],
        ],
    ])->toArray();
    foreach ($techsSeSe as $techn) {
        if ($techn["profile"] == "Technicien") {
            array_push($techniciansSeSe, new MongoDB\BSON\ObjectId($techn['_id']));
        } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
            array_push($techniciansSeSe, new MongoDB\BSON\ObjectId($techn['_id']));
        }
    }
    $techniciansExSe = [];
    $techsExSe = $users->find([
        '$and' => [
            [
                "subsidiary" => "CFAO MOTORS SENEGAL",
                "level" => "Expert",
                "active" => true,
            ],
        ],
    ])->toArray();
    foreach ($techsExSe as $techn) {
        if ($techn["profile"] == "Technicien") {
            array_push($techniciansExSe, new MongoDB\BSON\ObjectId($techn['_id']));
        } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
            array_push($techniciansExSe, new MongoDB\BSON\ObjectId($techn['_id']));
        }
    }

    
    $testsJuSe = [];
    $countSavoirsJuSe = [];
    $countMaSavFaisJuSe = [];
    $percentageTestsJuSe = [];
    $testsSeSe = [];
    $countSavoirsSeSe = [];
    $countMaSavFaisSeSe = [];
    $percentageTestsSeSe = [];
    $testsExSe = [];
    $countSavoirsExSe = [];
    $countMaSavFaisExSe = [];
    $percentageTestsExSe = [];
    foreach ($techniciansSe as $technician) { 
        $allocateFacJuSe = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Factuel",
                    "level" => "Junior",
                ],
            ],
        ]);
        $allocateDeclaJuSe = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Declaratif",
                    "level" => "Junior",
                ],
            ],
        ]);
        if (isset($allocateFacJuSe) && $allocateFacJuSe['active'] == true) {
            $countSavoirsJuSe[] = $allocateFacJuSe;
        }
        if (isset($allocateDeclaJuSe) && $allocateDeclaJuSe['activeManager'] == true) {
            $countMaSavFaisJuSe[] = $allocateDeclaJuSe;
        }
        if (isset($allocateDeclaJuSe) && $allocateDeclaJuSe['active'] == true) {
            $percentageTestsJuSe[] = $allocateDeclaJuSe;
        }
        if (isset($allocateFacJuSe) && isset($allocateDeclaJuSe) && $allocateFacJuSe['active'] == true && $allocateDeclaJuSe['active'] == true && $allocateDeclaJuSe['activeManager'] == true) {
            $testsJuSe[] = $technician;
        }
        $allocateFacSeSe = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Factuel",
                    "level" => "Senior",
                ],
            ],
        ]);
        $allocateDeclaSeSe = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Declaratif",
                    "level" => "Senior",
                ],
            ],
        ]);
        if (isset($allocateFacSeSe) && $allocateFacSeSe['active'] == true) {
            $countSavoirsSeSe[] = $allocateFacSeSe;
        }
        if (isset($allocateDeclaSeSe) && $allocateDeclaSeSe['activeManager'] == true) {
            $countMaSavFaisSeSe[] = $allocateDeclaSeSe;
        }
        if (isset($allocateDeclaSeSe) && $allocateDeclaSeSe['active'] == true) {
            $percentageTestsSeSe[] = $allocateDeclaSeSe;
        }
        if (isset($allocateFacSeSe) && isset($allocateDeclaSeSe) && $allocateFacSeSe['active'] == true && $allocateDeclaSeSe['active'] == true && $allocateDeclaSeSe['activeManager'] == true) {
            $testsSeSe[] = $technician;
        }
        $allocateFacExSe = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Factuel",
                    "level" => "Expert",
                ],
            ],
        ]);
        $allocateDeclaExSe = $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($technician),
                    "type" => "Declaratif",
                    "level" => "Expert",
                ],
            ],
        ]);
        if (isset($allocateFacExSe) && $allocateFacExSe['active'] == true) {
            $countSavoirsExSe[] = $allocateFacExSe;
        }
        if (isset($allocateDeclaExSe) && $allocateDeclaExSe['activeManager'] == true) {
            $countMaSavFaisExSe[] = $allocateDeclaExSe;
        }
        if (isset($allocateDeclaExSe) && $allocateDeclaExSe['active'] == true) {
            $percentageTestsExSe[] = $allocateDeclaExSe;
        }
        if (isset($allocateFacExSe) && isset($allocateDeclaExSe) && $allocateFacExSe['active'] == true && $allocateDeclaExSe['active'] == true && $allocateDeclaExSe['activeManager'] == true) {
            $testsExSe[] = $technician;
        }
    }   
    ?>
<?php include_once "partials/header.php"; ?>
<!--begin::Title-->
<title><?php echo $etat_avanacement_qcm_country ?> | CFAO Mobility Academy</title>
<!--end::Title-->

<!--begin::Body-->
<div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content"
    data-select2-id="select2-data-kt_content">
    <!--begin::Toolbar-->
    <div class="toolbar" id="kt_toolbar">
        <div class=" container-fluid  d-flex flex-stack flex-wrap flex-sm-nowrap">
            <!--begin::Info-->
            <div class="d-flex flex-column align-items-start justify-content-center flex-wrap me-2">
                <!--begin::Title-->
                <h1 class="text-dark fw-bold my-1 fs-2">
                    <?php echo $etat_avanacement_test_country ?> </h1>
                <!--end::Title-->
                <div class="card-title">
                    <!--begin::Search-->
                    <!-- <div class="d-flex align-items-center position-relative my-1">
                        <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5"><span class="path1"></span><span
                                class="path2"></span></i>
                        <input type="text" id="search" class="form-control form-control-solid w-250px ps-12"
                            placeholder="<?php echo $recherche?>">
                    </div> -->
                    <!--end::Search-->
                </div>
            </div>
            <!--end::Info-->
        </div>
    </div>
    <!--end::Toolbar-->

    <?php if (isset($success_msg)) { ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <center><strong><?php echo $success_msg; ?></strong></center>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <?php } ?>
    <?php if (isset($error_msg)) { ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <center><strong><?php echo $error_msg; ?></strong></center>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <?php } ?>

    <!--begin::Post-->
    <div class="post fs-6 d-flex flex-column-fluid" id="kt_post" data-select2-id="select2-data-kt_post">
        <!--begin::Container-->
        <div class=" container-xxl " data-select2-id="select2-data-194-27hh">
            <!--begin::Card-->
            <div class="card">
                <!--begin::Card header-->

                <!--begin::Card body-->
                <div class="card-body pt-0">
                    <!--begin::Table-->
                    <div id="kt_customers_table_wrapper" class="dataTables_wrapper dt-bootstrap4 no-footer">
                        <div class="table-responsive">
                            <table aria-describedby=""
                                class="table align-middle table-bordered  table-row-dashed fs-6 gy-4 dataTable no-footer"
                                id="kt_customers_table">
                                <thead>
                                    <tr class="text-start text-black fw-bold fs-7 text-uppercase gs-0">
                                        <th class="min-w-150px sorting text-center" tabindex="0"
                                            aria-controls="kt_customers_table" rowspan="1" colspan="1"
                                            aria-label="Customer Name: activate to sort column ascending"
                                            style="width: 100px; vertical-align: middle;">
                                            <?php echo $subsidiary ?> CFAO (<?php echo $pays ?>)
                                        </th>
                                        <th class="min-w-0px sorting text-center" tabindex="0"
                                            aria-controls="kt_customers_table" rowspan="1" colspan="1"
                                            aria-label="Company: activate to sort column ascending"
                                            style="width: 05px; vertical-align: middle;">
                                            <?php echo $level ?>
                                        </th>
                                        <th class="min-w-100px sorting text-center" tabindex="0"
                                            aria-controls="kt_customers_table" rowspan="1" colspan="1"
                                            aria-label="Company: activate to sort column ascending"
                                            style="width: 100px; vertical-align: middle;">
                                            <?php echo $nbre_test_effectue ?>
                                        </th>
                                        <th class="min-w-100px sorting text-center" tabindex="0"
                                            aria-controls="kt_customers_table" rowspan="1" colspan="1"
                                            aria-label="Company: activate to sort column ascending"
                                            style="width: 100px; vertical-align: middle;">
                                            <?php echo "Nombre de Tests effectués" ?>
                                        </th>
                                        <th class="min-w-100px sorting text-center" tabindex="0"
                                            aria-controls="kt_customers_table" rowspan="1" colspan="1"
                                            aria-label="Company: activate to sort column ascending"
                                            style="width: 100px; vertical-align: middle;">
                                            <?php echo "Pourcentage de Tests effectués " ?>
                                        </th>
                                </thead>

                                <tbody class="fw-semibold text-gray-600" id="table">
                                    <?php
// Function to calculate percentage
function calculatePercentage($numerator, $denominator) {
    return ($denominator > 0) ? ceil(($numerator * 100) / $denominator) . '%' : '0%';
}

// Function to generate a table row
function generateTableRow($locationLabel, $level, $totalTests, $totalTestsDone, $isFirstOfLocation, $isGlobal = false) {
    $percentageTestsDone = calculatePercentage($totalTestsDone, $totalTests);

    $locationCell = $isFirstOfLocation ? "<td rowspan='4' class='text-center' style='text-align: center; vertical-align: middle; height: 50px;' ><strong>{$locationLabel}</strong></td>" : "";
    $globalClass = $isGlobal ? "class='global-row'" : "";

    return "
    <tr {$globalClass} class='odd' etat=''>
        {$locationCell}
        <td class='text-center'>{$level}</td>
        <td class='text-center' style='background-color: #EDF2F6;'>{$totalTests}</td>
        <td class='text-center'>{$totalTestsDone}</td>
        <td class='text-center'>{$percentageTestsDone}</td>
    </tr>
    ";
}



// Function to generate grand total row
function generateGrandTotalRow($grandTotals) {
    return generateTableRow('Total Global', 'Global', $grandTotals['totalTests'], $grandTotals['totalTestsDone'], false, true);
}

// Prepare data for each section
function prepareData($technicians, $countSavoirs, $percentageTests, $countMaSavFais) {
    return [
        'totalTests' => count($technicians),
        'totalTestsDone' => count($countSavoirs),
        'percentageTestsDone' => count($percentageTests),
        'totalMaSavFais' => count($countMaSavFais)
    ];
}

// Data for each location
$locations = [
    'BURKINA' => [
        'Junior' => prepareData($techniciansBu, $testsJuBu, $percentageTestsJuBu, $countMaSavFaisJuBu),
        'Senior' => prepareData(array_merge($techniciansSeBu, $techniciansExBu), $testsSeBu, $percentageTestsSeBu, $countMaSavFaisSeBu),
        'Expert' => prepareData($techniciansExBu, $testsExBu, $percentageTestsExBu, $countMaSavFaisExBu)
    ],
    'CAMEROUN' => [
        'Junior' => prepareData($techniciansCa, $testsJuCa, $percentageTestsJuCa, $countMaSavFaisJuCa),
        'Senior' => prepareData(array_merge($techniciansSeCa, $techniciansExCa), $testsSeCa, $percentageTestsSeCa, $countMaSavFaisSeCa),
        'Expert' => prepareData($techniciansExCa, $testsExCa, $percentageTestsExCa, $countMaSavFaisExCa)
    ],
    "CONGO" => [
        'Junior' => prepareData($techniciansCo, $testsJuCo, $percentageTestsJuCo, $countMaSavFaisJuCo),
        'Senior' => prepareData(array_merge($techniciansSeCo, $techniciansExCo), $testsSeCo, $percentageTestsSeCo, $countMaSavFaisSeCo),
        'Expert' => prepareData($techniciansExCo, $testsExCo, $percentageTestsExCo, $countMaSavFaisExCo)
    ],
    "COTE D'IVOIRE" => [
        'Junior' => prepareData($techniciansRci, $testsJuRci, $percentageTestsJuRci, $countMaSavFaisJuRci),
        'Senior' => prepareData(array_merge($techniciansSeRci, $techniciansExRci), $testsSeRci, $percentageTestsSeRci, $countMaSavFaisSeRci),
        'Expert' => prepareData($techniciansExRci, $testsExRci, $percentageTestsExRci, $countMaSavFaisExRci)
    ],
    'GABON' => [
        'Junior' => prepareData($techniciansGa, $testsJuGa, $percentageTestsJuGa, $countMaSavFaisJuGa),
        'Senior' => prepareData(array_merge($techniciansSeGa, $techniciansExGa), $testsSeGa, $percentageTestsSeGa, $countMaSavFaisSeGa),
        'Expert' => prepareData($techniciansExGa, $testsExGa, $percentageTestsExGa, $countMaSavFaisExGa)
    ],
    'MALI' => [
        'Junior' => prepareData($techniciansMali, $testsJuMali, $percentageTestsJuMali, $countMaSavFaisJuMali),
        'Senior' => prepareData(array_merge($techniciansSeMali, $techniciansExMali), $testsSeMali, $percentageTestsSeMali, $countMaSavFaisSeMali),
        'Expert' => prepareData($techniciansExMali, $testsExMali, $percentageTestsExMali, $countMaSavFaisExMali)
    ],
    'RCA' => [
        'Junior' => prepareData($techniciansRca, $testsJuRca, $percentageTestsJuRca, $countMaSavFaisJuRca),
        'Senior' => prepareData(array_merge($techniciansSeRca, $techniciansExRca), $testsSeRca, $percentageTestsSeRca, $countMaSavFaisSeRca),
        'Expert' => prepareData($techniciansExRca, $testsExRca, $percentageTestsExRca, $countMaSavFaisExRca)
    ],
    'RDC' => [
        'Junior' => prepareData($techniciansRdc, $testsJuRdc, $percentageTestsJuRdc, $countMaSavFaisJuRdc),
        'Senior' => prepareData(array_merge($techniciansSeRdc, $techniciansExRdc), $testsSeRdc, $percentageTestsSeRdc, $countMaSavFaisSeRdc),
        'Expert' => prepareData($techniciansExRdc, $testsExRdc, $percentageTestsExRdc, $countMaSavFaisExRdc)
    ],
    'SENEGAL' => [
        'Junior' => prepareData($techniciansSe, $testsJuSe, $percentageTestsJuSe, $countMaSavFaisJuSe),
        'Senior' => prepareData(array_merge($techniciansSeSe, $techniciansExSe), $testsSeSe, $percentageTestsSeSe, $countMaSavFaisSeSe),
        'Expert' => prepareData($techniciansExSe, $testsExSe, $percentageTestsExSe, $countMaSavFaisExSe)
    ]
    // Add other locations similarly
];

// Initialize grand totals
$grandTotals = [
    'totalTests' => 0,
    'totalTestsDone' => 0,
    'percentageTestsDone' => 0,
    'totalMaSavFais' => 0
];

// Initialize aggregated totals for each level
$aggregatedTotals = [
    'Junior' => [
        'totalTests' => 0,
        'totalTestsDone' => 0,
        'percentageTestsDone' => 0,
        'totalMaSavFais' => 0
    ],
    'Senior' => [
        'totalTests' => 0,
        'totalTestsDone' => 0,
        'percentageTestsDone' => 0,
        'totalMaSavFais' => 0
    ],
    'Expert' => [
        'totalTests' => 0,
        'totalTestsDone' => 0,
        'percentageTestsDone' => 0,
        'totalMaSavFais' => 0
    ]
];

// Print rows for each location and calculate totals
foreach ($locations as $location => $levels) {
    // Initialize location totals
    $locationTotals = [
        'totalTests' => 0,
        'totalTestsDone' => 0,
        'percentageTestsDone' => 0,
        'totalMaSavFais' => 0
    ];

    // Print details for Junior, Senior, and Expert levels
    $isFirstOfLocation = true;
    foreach ($levels as $level => $data) {
        echo generateTableRow($location, ucfirst($level), $data['totalTests'], $data['totalTestsDone'], $isFirstOfLocation);

        // Update location totals
        $locationTotals['totalTests'] += $data['totalTests'];
        $locationTotals['totalTestsDone'] += $data['totalTestsDone'];

        // Update aggregated totals
        $aggregatedTotals[$level]['totalTests'] += $data['totalTests'];
        $aggregatedTotals[$level]['totalTestsDone'] += $data['totalTestsDone'];

        $isFirstOfLocation = false;
    }

    // Print location total
    echo generateTableRow($location . 'Global', $global, $locationTotals['totalTests'], $locationTotals['totalTestsDone'], false, true);

    // Update grand totals
    $grandTotals['totalTests'] += $locationTotals['totalTests'];
    $grandTotals['totalTestsDone'] += $locationTotals['totalTestsDone'];
}
echo '<tr>
    <td colspan="9" ">&nbsp;</td>
</tr>';
echo '<td rowspan="5" style="text-align: center; font-weight: bold; vertical-align: middle; height: 50px;">GROUPE CFAO</td>';


// Print aggregated totals for each level
foreach ($aggregatedTotals as $level => $totals) {
    echo generateTableRow('Total ' . ucfirst($level), $level, $totals['totalTests'], $totals['totalTestsDone'], false);
}

// Print grand total
echo generateGrandTotalRow($grandTotals);
?>
                                </tbody>
                                <style>
                                .global-row {
                                    font-weight: bold;
                                    background-color: #EDF2F6 !important;
                                    /* Set the background color to #EFF9FF */
                                }
                                </style>


                            </table>
                        </div>
                        <div class="row">
                            <div
                                class="col-sm-12 col-md-5 d-flex align-items-center justify-content-center justify-content-md-start">
                                <div class="dataTables_length">
                                    <label><select id="kt_customers_table_length" name="kt_customers_table_length"
                                            class="form-select form-select-sm form-select-solid">
                                            <option value="100">100</option>
                                            <option value="200">200</option>
                                            <option value="300">300</option>
                                            <option value="500">500</option>
                                        </select></label>
                                </div>
                            </div>
                            <div
                                class="col-sm-12 col-md-7 d-flex align-items-center justify-content-center justify-content-md-end">
                                <div class="dataTables_paginate paging_simple_numbers">
                                    <ul class="pagination" id="kt_customers_table_paginate">
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!--end::Table-->
                </div>
                <!--end::Card body-->
            </div>
            <!--end::Card-->
            <!--begin::Export dropdown-->
            <div class="d-flex justify-content-end align-items-center" style="margin-top: 20px;">
                <!--begin::Export-->
                <button type="button" id="excel" class="btn btn-light-danger me-3" data-bs-toggle="modal"
                    data-bs-target="#kt_customers_export_modal">
                    <i class="ki-duotone ki-exit-up fs-2"><span class="path1"></span><span class="path2"></span></i>
                    <?php echo $excel ?>
                </button>
                <!--end::Export-->
            </div>
            <!--end::Export dropdown-->
        </div>
        <!--end::Container-->
    </div>
    <!--end::Post-->
</div>
<!--end::Body-->
<script src="https://code.jquery.com/jquery-3.6.3.js" integrity="sha256-nQLuAZGRRcILA+6dMBOvcRh5Pe310sBpanc6+QBmyVM="
    crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/gh/linways/table-to-excel@v1.0.4/dist/tableToExcel.js">
</script>
<script src="../public/js/main.js"></script>
<script>
$(document).ready(function() {
    $("#excel").on("click", function() {
        let table = document.getElementsByTagName("table");
        debugger;
        TableToExcel.convert(table[0], {
            name: `StateCountry.xlsx`
        })
    });
});
</script>
<?php include_once "partials/footer.php"; ?>
<?php
} ?>