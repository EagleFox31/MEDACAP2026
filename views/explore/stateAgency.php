<?php
require_once __DIR__ . '/../../core/medacap_config.php';
session_start();
include_once "../language.php";
include "./partials/header.php";
include_once "../partials/background-manager.php"; 

if (!isset($_SESSION["profile"])) {
    header("Location: ../../");
    exit();
} else {

    require_once "../../vendor/autoload.php";

    // Create connection
    $conn = new MongoDB\Client(medacapMongoUri());

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

    $i = 5;

    if($_SESSION["profile"] == "Super Admin") {
        function getTechnicians($users, $agency, $level) {
            return $users->find([
                "agency" => $agency,
                "level" => ['$in' => (array) $level], // Convertit $level en tableau si ce n'est pas déjà un tableau
                "active" => true,
            ])->toArray();
        }
    
        function filterTechnicians($technicians) {
            $filtered = [];
            foreach ($technicians as $techn) {
                if ($techn["profile"] == "Technicien" || ($techn["profile"] == "Manager" && $techn["test"] == true)) {
                    array_push($filtered, new MongoDB\BSON\ObjectId($techn['_id']));
                }
            }
            return $filtered;
        }
    
        function getAllocations($allocations, $technician, $level) {
            $factuel = $allocations->findOne([
                "user" => new MongoDB\BSON\ObjectId($technician),
                "type" => "Factuel",
                "level" => $level,
            ]);
            $declaratif = $allocations->findOne([
                "user" => new MongoDB\BSON\ObjectId($technician),
                "type" => "Declaratif",
                "level" => $level,
            ]);
            return [$factuel, $declaratif];
        }
    
        function processTechnicians($allocations, $technicians, $agency, $agencies, $level) {
            $tests = [];
            $countSavoirs = [];
            $countMaSavFais = [];
            $countTechSavFais = [];
    
            foreach ($technicians as $technician) {
                list($factuel, $declaratif) = getAllocations($allocations, $technician, $level);
    
                if (isset($factuel) && $factuel['active'] == true) {
                    $countSavoirs[] = $factuel;
                }
                if (isset($declaratif)) {
                    if ($declaratif['activeManager'] == true) {
                        $countMaSavFais[] = $declaratif;
                    }
                    if ($declaratif['active'] == true) {
                        $countTechSavFais[] = $declaratif;
                    }
                    if ($factuel['active'] == true && $declaratif['active'] == true && $declaratif['activeManager'] == true) {
                        $tests[] = $technician;
                    }
                }
            }
            $country = getCountryByAgency($agency, $agencies);
            $response = [
                'tests' => count($tests),
                'country' => $country,
                'technicians' => count($technicians),
                'countSavoirs' => count($countSavoirs),
                'countMaSavFais' => count($countMaSavFais),
                'countTechSavFais' => count($countTechSavFais)
            ];
    
            return $response;
        }

        function getCountryByAgency($agency, $agencies) {
            foreach ($agencies as $country => $agencyList) {
                if (in_array($agency, $agencyList)) {
                    return $country; // Retourne le pays si l'agence est trouvée
                }
            }
        }

        $levels = ['Junior', 'Senior', 'Expert'];

        // Initialisation des tableaux
        $results = [];
        
        // Map countries to their respective agencies
        $countryAgencies = [
            "Ankorondrano",
            "Anosizato",
            "Bafoussam",
            "Bamako",
            "Bangui",
            "Brazzaville",
            "Bertoua",
            "Dakar",
            "Diego",
            "Douala",
            "Kinshasa",
            "Kolwezi",
            "Libreville",
            "Lubumbashi",
            "Moramanga",
            "Ngaoundere",
            "Ouaga",
            "Tamatave",
            "Pointe-Noire",
            "Vridi - Equip",
            "Yaoundé"
            // Add more countries and their agencies here
        ];

        // Traitement des techniciens
        foreach ($countryAgencies as $agency) {
            // Calculer le total global
            $results[$agency]['Global'] = [
                'tests' => 0,
                'country' => "",
                'technicians' => 0,
                'countSavoirs' => 0,
                'countMaSavFais' => 0,
                'countTechSavFais' => 0
            ];
            // Calculer le total global
            $results['GROUPE CFAO']['Global'] = [
                'tests' => 0,
                'technicians' => 0,
                'countSavoirs' => 0,
                'countMaSavFais' => 0,
                'countTechSavFais' => 0
            ];
            
            // Initialiser un tableau pour stocker les techniciens par niveau
            $techniciansByLevel = [];
            
            foreach ($levels as $level) {
                // Calculer le total global
                $results['GROUPE CFAO'][$level] = [
                    'tests' => 0,
                    'technicians' => 0,
                    'countSavoirs' => 0,
                    'countMaSavFais' => 0,
                    'countTechSavFais' => 0
                ];

                if ($level == 'Junior') {
                    $technicians = filterTechnicians(getTechnicians($users, $agency, ['Junior', 'Senior', 'Expert']));
                }
                if ($level == 'Senior') {
                    $technicians = filterTechnicians(getTechnicians($users, $agency, ['Senior', 'Expert']));
                }
                if ($level == 'Expert') {
                    $technicians = filterTechnicians(getTechnicians($users, $agency, ['Expert']));
                }

                // Stocker les techniciens par niveau
                $techniciansByLevel[$level] = $technicians;

                // Traiter les techniciens et les stocker dans les résultats
                $results[$agency][$level] = processTechnicians($allocations, $technicians, $agency, $agencies, $level);

                // Calculer le total des techniciens pour la filiale
                $results[$agency]['Global']['tests'] += $results[$agency][$level]['tests'];
                $results[$agency]['Global']['country'] = $results[$agency][$level]['country'];
                $results[$agency]['Global']['technicians'] += $results[$agency][$level]['technicians'];
                $results[$agency]['Global']['countSavoirs'] += $results[$agency][$level]['countSavoirs'];
                $results[$agency]['Global']['countMaSavFais'] += $results[$agency][$level]['countMaSavFais'];
                $results[$agency]['Global']['countTechSavFais'] += $results[$agency][$level]['countTechSavFais'];
            }
        }
    } else {
        // Déterminer le pays depuis l'agence en session
        $country = null;
        if (isset($_SESSION['agency'])) {
            foreach ($agencies as $countryKey => $agencyList) {
                if (in_array($_SESSION['agency'], $agencyList)) {
                    $country = $countryKey;
                    break;
                }
            }
        }

        // Compter les techniciens par agence et trier
        $agencyTechCounts = [];
        if ($country && isset($agencies[$country])) {
            foreach ($agencies[$country] as $agencyOption) {
                $query = ["subsidiary" => $_SESSION["subsidiary"], "agency" => $agencyOption, "active" => true];
                if ($_SESSION["department"] != 'Equipment & Motors') {
                    $query['department'] = $_SESSION["department"];
                }
                $techs = $users->find($query)->toArray();
                $count = 0;
                foreach ($techs as $t) {
                    if ($t["profile"] == "Technicien" || ($t["profile"] == "Manager" && $t["test"] == true)) {
                        $count++;
                    }
                }
                $agencyTechCounts[$agencyOption] = $count;
            }
        }
        $agenciesWithTechs    = array_filter($agencies[$country] ?? [], function($a) use ($agencyTechCounts) { return ($agencyTechCounts[$a] ?? 0) > 0; });
        $agenciesWithoutTechs = array_filter($agencies[$country] ?? [], function($a) use ($agencyTechCounts) { return ($agencyTechCounts[$a] ?? 0) === 0; });
        $sortedAgencies = array_merge(array_values($agenciesWithTechs), array_values($agenciesWithoutTechs));

        // Agence sélectionnée : GET param sinon "Toutes les agences" par défaut
        if (isset($_GET['agency']) && $_GET['agency'] !== '') {
            $agency = $_GET['agency']; // peut être 'all'
        } else {
            $agency = 'all';
        }

        function getTechnicians($users, $agency, $level = null) {
            $query = [
                "subsidiary" => $_SESSION["subsidiary"],
                "active" => true,
            ];
            if ($agency !== 'all' && $agency !== '') {
                $query["agency"] = $agency;
            }

            if ($_SESSION["department"] != 'Equipment & Motors') {
                $query['department'] = $_SESSION["department"];
            }

            if ($level) {
                $query["level"] = $level; // Ajout de la condition pour le niveau si spécifié
            }

            $technicians = [];
            $techs = $users->find($query)->toArray(); // Pas besoin de $and ici
            foreach ($techs as $techn) {
                if ($techn["profile"] == "Technicien" || ($techn["profile"] == "Manager" && $techn["test"] == true)) {
                    array_push($technicians, new MongoDB\BSON\ObjectId($techn['_id']));
                }
            }
            return $technicians;
        }

        // Récupération des techniciens par niveau
        $technicians = getTechnicians($users, $agency);
        $techniciansJu = getTechnicians($users, $agency, "Junior");
        $techniciansSe = getTechnicians($users, $agency, "Senior");
        $techniciansEx = getTechnicians($users, $agency, "Expert");

        function processTechnicians($allocations, $results, $technicians, $level) {
            $resultsFacScore = [];
            $resultsDeclaScore = [];
            $resultsFacTotal = [];
            $resultsDeclaTotal = [];

            foreach ($technicians as $tech) {
                $alloFac = $allocations->findOne([
                    'user' => new MongoDB\BSON\ObjectId($tech),
                    'level' => $level,
                    'type' => 'Factuel',
                    'active' => true
                ]);
                $alloDecla = $allocations->findOne([
                    'user' => new MongoDB\BSON\ObjectId($tech),
                    'level' => $level,
                    'type' => 'Declaratif',
                    'activeManager' => true,
                    'active' => true
                ]);

                if ($alloFac && $alloDecla) {
                    $resultFac = $results->findOne([
                        "user" => new MongoDB\BSON\ObjectId($tech),
                        "level" => $level,
                        'typeR' => 'Technicien',
                        "type" => "Factuel",
                    ]);
                    if (isset($resultFac)) {
                        array_push($resultsFacScore, $resultFac['score']);
                        array_push($resultsFacTotal, $resultFac['total']);
                    }

                    $resultDecla = $results->findOne([
                        "user" => new MongoDB\BSON\ObjectId($tech),
                        "level" => $level,
                        "typeR" => "Technicien - Manager",
                        "type" => "Declaratif",
                    ]);
                    if (isset($resultDecla)) {
                        array_push($resultsDeclaScore, $resultDecla['score']);
                        array_push($resultsDeclaTotal, $resultDecla['total']);
                    }
                }
            }

            return [
                'resultsFacScore' => $resultsFacScore,
                'resultsFacTotal' => $resultsFacTotal,
                'resultsDeclaScore' => $resultsDeclaScore,
                'resultsDeclaTotal' => $resultsDeclaTotal,
            ];
        }

        function calculateAverage($scores, $totals) {
            $totalScore = array_sum($scores);
            $totalResult = array_sum($totals);
            return $totalResult == 0 ? 0 : ($totalScore * 100 / $totalResult);
        }

        // Traitement des techniciens Junior
        $resultsJu = processTechnicians($allocations, $results, $techniciansJu, "Junior");
        $percentageFacJuTj = calculateAverage($resultsJu['resultsFacScore'], $resultsJu['resultsFacTotal']);
        $percentageDeclaJuTj = calculateAverage($resultsJu['resultsDeclaScore'], $resultsJu['resultsDeclaTotal']);

        // Traitement des techniciens Senior
        $resultsSu = processTechnicians($allocations, $results, $techniciansSe, "Junior");
        $percentageFacJuTs = calculateAverage($resultsSu['resultsFacScore'], $resultsSu['resultsFacTotal']);
        $percentageDeclaJuTs = calculateAverage($resultsSu['resultsDeclaScore'], $resultsSu['resultsDeclaTotal']);

        $resultsSe = processTechnicians($allocations, $results, $techniciansSe, "Senior");
        $percentageFacSeTs = calculateAverage($resultsSe['resultsFacScore'], $resultsSe['resultsFacTotal']);
        $percentageDeclaSeTs = calculateAverage($resultsSe['resultsDeclaScore'], $resultsSe['resultsDeclaTotal']);

        // Traitement des techniciens Expert
        $resultsEx = processTechnicians($allocations, $results, $techniciansEx, "Junior");
        $percentageFacJuTx = calculateAverage($resultsEx['resultsFacScore'], $resultsEx['resultsFacTotal']);
        $percentageDeclaJuTx = calculateAverage($resultsEx['resultsDeclaScore'], $resultsEx['resultsDeclaTotal']);

        $resultsEs = processTechnicians($allocations, $results, $techniciansEx, "Senior");
        $percentageFacSeTx = calculateAverage($resultsEs['resultsFacScore'], $resultsEs['resultsFacTotal']);
        $percentageDeclaSeTx = calculateAverage($resultsEs['resultsDeclaScore'], $resultsEs['resultsDeclaTotal']);
        
        // Traitement des Résultats Junior
        $resultJu = processTechnicians($allocations, $results, $technicians, "Junior");
        $percentageFacJu = calculateAverage($resultJu['resultsFacScore'], $resultJu['resultsFacTotal']);
        $percentageDeclaJu = calculateAverage($resultJu['resultsDeclaScore'], $resultJu['resultsDeclaTotal']);

        // Traitement des Résultats Senior
        $resultSe = processTechnicians($allocations, $results, $technicians, "Senior");
        $percentageFacSe = calculateAverage($resultSe['resultsFacScore'], $resultSe['resultsFacTotal']);
        $percentageDeclaSe = calculateAverage($resultSe['resultsDeclaScore'], $resultSe['resultsDeclaTotal']);

        // Traitement des Résultats Expert
        $resultEx = processTechnicians($allocations, $results, $technicians, "Expert");
        $percentageFacEx = calculateAverage($resultEx['resultsFacScore'], $resultEx['resultsFacTotal']);
        $percentageDeclaEx = calculateAverage($resultEx['resultsDeclaScore'], $resultEx['resultsDeclaTotal']);

        function processTechnicianAllocations($allocations, $technician, $level) {
            $countSavoir = [];
            $countMaSavFai = [];
            $countTechSavFai = [];
            $countSavFai = [];
            $testsUser  = [];
            $testsTotal = [];

            $alloFac = $allocations->findOne([
                'user' => new MongoDB\BSON\ObjectId($technician),
                'type' => 'Factuel',
                'level' => $level,
            ]);
            $alloDecla = $allocations->findOne([
                'user' => new MongoDB\BSON\ObjectId($technician),
                'type' => 'Declaratif',
                'level' => $level,
            ]);

            if (isset($alloFac) && $alloFac['active'] == true) {
                $countSavoir[] = $alloFac;
            }
            if (isset($alloDecla)) {
                if ($alloDecla['activeManager'] == true) {
                    $countMaSavFai[] = $alloDecla;
                }
                if ($alloDecla['active'] == true) {
                    $countTechSavFai[] = $alloDecla;
                }
                if ($alloDecla['active'] == true && $alloDecla['activeManager'] == true) {
                    $countSavFai[] = $alloDecla;
                }
            }
            if (isset($alloFac) && isset($alloDecla) && $alloFac['active'] == true && $alloDecla['active'] == true && $alloDecla['activeManager'] == true) {
                $testsUser [] = $technician;
            }
            if (isset($alloFac) && isset($alloDecla)) {
                $testsTotal[] = $technician;
            }

            return [
                'countSavoir' => $countSavoir,
                'countMaSavFai' => $countMaSavFai,
                'countTechSavFai' => $countTechSavFai,
                'countSavFai' => $countSavFai,
                'testsUser ' => $testsUser ,
                'testsTotal' => $testsTotal,
            ];
        }

        // Initialisation des tableaux
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

        // Traitement des techniciens
        foreach ($technicians as $technician) {
            // Junior
            $resultJu = processTechnicianAllocations($allocations, $technician, "Junior");
            $countSavoirJu = array_merge($countSavoirJu, $resultJu['countSavoir']);
            $countMaSavFaiJu = array_merge($countMaSavFaiJu, $resultJu['countMaSavFai']);
            $countTechSavFaiJu = array_merge($countTechSavFaiJu, $resultJu['countTechSavFai']);
            $countSavFaiJu = array_merge($countSavFaiJu, $resultJu['countSavFai']);
            $testsUserJu = array_merge($testsUserJu, $resultJu['testsUser ']);
            $testsTotalJu = array_merge($testsTotalJu, $resultJu['testsTotal']);

            // Senior
            $resultSe = processTechnicianAllocations($allocations, $technician, "Senior");
            $countSavoirSe = array_merge($countSavoirSe, $resultSe['countSavoir']);
            $countMaSavFaiSe = array_merge($countMaSavFaiSe, $resultSe['countMaSavFai']);
            $countTechSavFaiSe = array_merge($countTechSavFaiSe, $resultSe['countTechSavFai']);
            $countSavFaiSe = array_merge($countSavFaiSe, $resultSe['countSavFai']);
            $testsUserSe = array_merge($testsUserSe, $resultSe['testsUser ']);
            $testsTotalSe = array_merge($testsTotalSe, $resultSe['testsTotal']);

            // Expert
            $resultEx = processTechnicianAllocations($allocations, $technician, "Expert");
            $countSavoirEx = array_merge($countSavoirEx, $resultEx['countSavoir']);
            $countMaSavFaiEx = array_merge($countMaSavFaiEx, $resultEx['countMaSavFai']);
            $countTechSavFaiEx = array_merge($countTechSavFaiEx, $resultEx['countTechSavFai']);
            $countSavFaiEx = array_merge($countSavFaiEx, $resultEx['countSavFai']);
            $testsUserEx = array_merge($testsUserEx, $resultEx['testsUser ']);
            $testsTotalEx = array_merge($testsTotalEx, $resultEx['testsTotal']);
        }

    }
    $selectedAgency = $agency ?? '';
    ?>

<style>
    /* Glassmorphism effect for cards */
    .glass-effect {
        background: rgba(255, 255, 255, 0.7) !important;
        backdrop-filter: blur(18px) !important; /* Set to 18px as requested */
        -webkit-backdrop-filter: blur(18px) !important;
        border: 1px solid rgba(255, 255, 255, 0.18) !important;
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15) !important;
        border-radius: 15px !important;
        transition: all 0.3s ease !important;
    }
    
    .glass-effect:hover {
        background: rgba(255, 255, 255, 0.8) !important;
        box-shadow: 0 10px 40px 0 rgba(31, 38, 135, 0.2) !important;
        transform: translateY(-3px) !important;
    }
    
    /* Style for doughnut chart cards with stronger blur */
    .chart-glass-effect {
        background: rgba(255, 255, 255, 0.7) !important;
        backdrop-filter: blur(25px) !important; /* Set to 25px as requested */
        -webkit-backdrop-filter: blur(25px) !important;
        border: 1px solid rgba(255, 255, 255, 0.18) !important;
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15) !important;
        border-radius: 15px !important;
        transition: all 0.3s ease !important;
    }
    
    /* Depth effect for cards */
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
</style>
<?php
// Définir le fond d'écran pour cette page
setPageBackground('bg-dashboard', true);

// Ouvrir le conteneur de fond d'écran
openBackgroundContainer('', 'id="kt_content"');
?>
<!--begin::Title-->
<?php if ( $_SESSION["profile"] == "Super Admin") { ?>
    <title><?php echo $etat_avanacement_qcm_agences ?>| CFAO Mobility Academy</title> 
<?php } else { ?>
    <title><?php echo $etat_avanacement_agence ?> <?php echo $_GET['agency'] ?> | CFAO Mobility Academy</title> 
<?php } ?>
<!--end::Title-->
<!--begin::Content-->
<div class="content fs-6 d-flex flex-column flex-column-fluid w-100" id="kt_content" style="background: none;">
    <!--begin::Toolbar-->
    <div class="toolbar w-100" id="kt_toolbar">
        <div class="container-fluid d-flex flex-stack flex-wrap flex-sm-nowrap w-100">
            <!--begin::Info-->
            <div class="d-flex flex-column align-items-start justify-content-center flex-wrap me-2 w-100">
                <!--begin::Title-->
                <?php if ( $_SESSION["profile"] == "Super Admin") { ?>
                    <!-- Main title card -->
                    <div class="container-fluid mt-5 w-100">
                        <div class="card shadow-sm mb-5 w-100">
                            <div class="card-body p-4">
                                <h1 class="text-dark fw-bold text-center fs-1">
                                    <?php echo $etat_avanacement_qcm_agences ?>
                                </h1>
                            </div>
                        </div>
                    </div>
                <?php } else { ?>
                    <div class="container-fluid mt-2 w-100">
                        <div class="card shadow-sm mb-2 w-100">
                            <div class="card-body p-4">
                                <h1 class="text-dark fw-bold text-center fs-1">
                                    <?php if ($selectedAgency && $selectedAgency !== 'all'): ?>
                                        <?php echo $etat_avanacement_agence ?> : <?php echo htmlspecialchars($selectedAgency) ?>
                                    <?php else: ?>
                                        Etat d'Avancement de Toutes les Agences
                                    <?php endif; ?>
                                </h1>
                            </div>
                        </div>
                    </div>
                <?php } ?>
                <!--end::Title-->
            </div>
            <!--end::Info-->
        </div>
    </div>
    <!--end::Toolbar-->
    <?php if ( $_SESSION["profile"] != "Super Admin") { ?>
<!--begin::Filtres -->
<div class="container my-4" style="margin-bottom: 10px;">
    <div class="card glassmorphism-card shadow-lg border-0">
        <div class="card-body p-4">
            <div class="row g-3 align-items-center justify-content-center">
                <!-- Filtre Agences -->
                <div class="col-md-6 text-center">
                    <label for="agency-filter" class="form-label d-flex align-items-center justify-content-center">
                        <i class="bi bi-building me-2 text-warning"></i> Sélectionnez une agence : 
                    </label>
                    <select id="agency-filter" onchange="agency()" name="agency" class="form-select glassmorphism-select">
                        <option value="all" <?php if ($selectedAgency === 'all') echo 'selected'; ?>>— Toutes les agences —</option>
                        <?php foreach ($sortedAgencies as $agencyOption):
                            $hasNoTechs = ($agencyTechCounts[$agencyOption] === 0);
                        ?>
                            <option value="<?php echo htmlspecialchars($agencyOption); ?>"
                                <?php if ($selectedAgency === $agencyOption) echo 'selected'; ?>
                                <?php if ($hasNoTechs) echo 'disabled'; ?>
                                style="<?php echo $hasNoTechs ? 'color:#aaa;font-style:italic;' : ''; ?>">
                                <?php echo htmlspecialchars($agencyOption); ?><?php echo $hasNoTechs ? ' (0 techniciens)' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>
</div>
<!--end::Filtres -->

<style>
.glassmorphism-card {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(35px);
    -webkit-backdrop-filter: blur(35px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 16px;
    box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
}

.glassmorphism-select {
    background: rgb(255, 255, 255);
    backdrop-filter: blur(5px);
    -webkit-backdrop-filter: blur(25px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 8px;
    color: #333;
    transition: all 0.3s ease;
}

.glassmorphism-select:focus {
    background: rgba(255, 255, 255, 0.98);
    border: 1px solid rgba(255, 255, 255, 0.4);
    box-shadow: 0 0 0 0.2rem rgba(255, 255, 255, 0.25);
}

.glassmorphism-select option {
    background: rgba(255, 255, 255, 0.95);
    color: #333;
}

.form-label {
    font-weight: 600;
    color: #ffffff;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
}

.text-warning {
    filter: drop-shadow(0 1px 1px rgba(255, 255, 255, 0.5));
}
</style>
    <?php } ?>
    <!--begin::Post-->
    <div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
        <!--begin::Container-->
        <div class=" container-xxl ">
            <!--end::Layout Builder Notice-->
            <!--begin::Row-->
            <?php if ( $_SESSION["profile"] != "Super Admin") { ?>
<div class="row g-6 g-xl-9 mb-6 mb-xl-9">
    <!-- Card principale effectif - sortie de la row -->
</div>

<!-- Card effectif en dehors de la row pour éviter les conflits -->
<div class="w-100" style="margin-top: 40px; margin-bottom: 10px;">
    <div class="card shadow-sm mb-5 w-100">
        <div class="card-body py-3">
            <h1 class="text-dark fw-bold text-center fs-2">
                <?php echo $effectif_agence ?>
            </h1>
        </div>
    </div>
</div>

<!-- Row pour les cards des techniciens -->
<div class="row g-6 g-xl-9 mb-6 mb-xl-9">
    <!--begin::Col-->
    <div class="col-md-6 col-lg-4 col-xl-3">
        <!--begin::Card-->
        <div class="card h-100 glass-effect depth-effect">
            <!--begin::Card body-->
            <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                <!--begin::Name-->
                <!--begin::Animation-->
                <div class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
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
        <div class="card h-100 glass-effect depth-effect">
            <!--begin::Card body-->
            <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                <!--begin::Name-->
                <!--begin::Animation-->
                <div class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
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
        <div class="card h-100 glass-effect depth-effect">
            <!--begin::Card body-->
            <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                <!--begin::Name-->
                <!--begin::Animation-->
                <div class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
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
        <div class="card h-100 glass-effect depth-effect">
            <!--begin::Card body-->
            <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                <!--begin::Name-->
                <!--begin::Animation-->
                <div class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
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
</div>

<!-- Reste du contenu -->
<!--begin::Title-->
<div class="w-100" style="margin-top: 55px; margin-bottom : 25px">
    <div class="card shadow-sm mb-5 w-100">
        <div class="card-body py-3">
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
                <h4 style="color : white; margin-top: 30px;"><?php echo $result_entre_0_60 ?></h4>
            </div>

            <!-- Group 2 -->
            <div style="display: flex; align-items: center; gap: 10px;">
                <canvas id='c2' width="75" height="37.5"></canvas>
                <h4 style="color : white; margin-top: 30px;"><?php echo $result_entre_60_80 ?></h4>
            </div>

            <!-- Group 3 -->
            <div style="display: flex; align-items: center; gap: 10px;">
                <canvas id='c3' width="75" height="37.5"></canvas>
                <h4 style="color : white; margin-top: 30px;"><?php echo $result_entre_80_100 ?></h4>
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
        <div class="row g-6">
            <!-- Card 1 -->
            <div class="col-xl-3">
                <div class="card chart-glass-effect depth-effect" style="height: 430px; margin-bottom: 25px;">
                    <center>
                        <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                            Résultats Niveau Junior</h5>
                    </center>
                    <div id="result_junior_filiale"
                        style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                </div>
            </div>

            <!-- Card 2 -->
            <div class="col-xl-3">
                <div class="card chart-glass-effect depth-effect" style="height: 430px; margin-bottom: 25px;">
                    <center>
                        <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                            Résultats Niveau Senior</h5>
                    </center>
                    <div id="result_senior_filiale"
                        style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                </div>
            </div>

            <!-- Card 3 -->
            <div class="col-xl-3">
                <div class="card chart-glass-effect depth-effect" style="height: 430px; margin-bottom: 25px;">
                    <center>
                        <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                            Résultats Niveau Expert</h5>
                    </center>
                    <div id="result_expert_filiale"
                        style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                </div>
            </div>

            <!-- Card 4 -->
            <div class="col-xl-3">
                <div class="card chart-glass-effect depth-effect" style="height: 430px; margin-bottom: 25px;">
                    <center>
                        <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">Total
                            : 03 niveaux</h5>
                    </center>
                    <div id="result_total_filiale"
                        style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                </div>
            </div>
        </div>
    </div>
</div>
<!--begin::Title-->
<div class="w-100" style="margin-top: 55px; margin-bottom : 25px">
    <div class="card shadow-sm mb-5 w-100">
        <div class="card-body py-3">
            <h6 class="text-dark fw-bold text-center fs-2">
                <?php echo $result_mesure_competence_agence_niveau ?>
            </h6>
        </div>
    </div>
</div>

<!--end::Title-->
<div class="row g-6">
    <!-- Card 1 -->
    <div class="col-xl-3">
        <div class="card chart-glass-effect depth-effect" style="height: 430px; margin-bottom: 25px;">
            <center>
                <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                    Résultats Niveau Junior</h5>
            </center>
            <div id="chart_junior_filiale"
                style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
        </div>
    </div>

    <!-- Card 2 -->
    <div class="col-xl-3">
        <div class="card chart-glass-effect depth-effect" style="height: 430px; margin-bottom: 25px;">
            <center>
                <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                    Résultats Niveau Senior</h5>
            </center>
            <div id="chart_senior_filiale"
                style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
        </div>
    </div>

    <!-- Card 3 -->
    <div class="col-xl-3">
        <div class="card chart-glass-effect depth-effect" style="height: 430px; margin-bottom: 25px;">
            <center>
                <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                    Résultats Niveau Expert</h5>
            </center>
            <div id="chart_expert_filiale"
                style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
        </div>
    </div>

    <!-- Card 4 -->
    <div class="col-xl-3">
        <div class="card chart-glass-effect depth-effect" style="height: 430px; margin-bottom: 25px;">
            <center>
                <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                    Total : 03 Niveaux</h5>
            </center>
            <div id="chart_total_filiale"
                style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
        </div>
    </div>
</div>
<!--begin::Title-->
<div class="w-100" style="margin-top: 55px; margin-bottom : 25px">
    <div class="card shadow-sm mb-5 w-100">
        <div class="card-body py-3">
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
<!-- endr::Row -->
<!-- Dropdown Toggle Button -->
<div class="dropdown-container">
    <button class="dropdown-toggle" style="color: black">Plus de détails sur les tests
        <i class="fas fa-chevron-down"></i></button>
    <!-- Hidden Content -->
    <div class="dropdown-content">
        <!-- Begin::Row -->
        <div class="row g-6">
            <!-- Card 1 -->
            <div class="col-xl-3">
                <div class="card chart-glass-effect depth-effect" style="height: 430px; margin-bottom: 25px;">
                    <center>
                        <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                            QCM Niveau Junior</h5>
                    </center>
                    <div id="qcm_junior"
                        style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                </div>
            </div>
            <!-- Card 2 -->
            <div class="col-xl-3">
                <div class="card chart-glass-effect depth-effect" style="height: 430px; margin-bottom: 25px;">
                    <center>
                        <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                            QCM Niveau Senior</h5>
                    </center>
                    <div id="qcm_senior"
                        style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                </div>
            </div>
            <!-- Card 3 -->
            <div class="col-xl-3">
                <div class="card chart-glass-effect depth-effect" style="height: 430px; margin-bottom: 25px;">
                    <center>
                        <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                            QCM Niveau Expert</h5>
                    </center>
                    <div id="qcm_expert"
                        style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                </div>
            </div>
            <!-- Card 4 -->
            <div class="col-xl-3">
                <div class="card chart-glass-effect depth-effect" style="height: 430px; margin-bottom: 25px;">
                    <center>
                        <h5 class="mt-2" style="align-text: center; margin-top: 18px !important;">
                            Total : 03 Niveaux</h5>
                    </center>
                    <div id="qcm_total"
                        style="height: 100%; width: 100%; margin-top: 10px; margin-left: -4px;"></div>
                </div>
            </div>
        </div>
        <!-- End::Row -->
    </div>
</div>
<!-- Dropdown Toggle Button -->
            <?php } ?>
            <!--end:Row-->
            <?php if ($_SESSION["profile"] == "Super Admin") { ?>
                <!--begin::Card-->
                <div class="card">
                    <!--begin::Card body-->
                    <div class="card-body pt-0">
                        <!--begin::Filtres -->
                        <div class="container my-4">
                            <div class="row g-3 align-items-center">
                                <!-- Filtre Pays -->
                                <div class="col-md-6">
                                    <label for="country-filter" class="form-label d-flex align-items-center">
                                        <i class="bi bi-geo-alt-fill fs-2 me-2 text-primary"></i> Pays
                                    </label>
                                    <!--begin::Select2-->
                                    <select id="select"
                                        class="form-select form-select-solid"
                                        data-control="select2"
                                        data-hide-search="true"
                                        data-placeholder="Pays"
                                        data-kt-ecommerce-order-filter="etat">
                                        <option value="tous">Tous les pays</option>
                                        <?php foreach ($agencies as $country => $agency) { ?>
                                            <option value="<?php echo $country ?>"><?php echo $country ?></option>
                                        <?php } ?>
                                    </select>
                                    <!--end::Select2-->
                                </div>
                            </div>
                        </div>
                        <!--end::Filtres -->
                        <!--begin::Table-->
                        <div id="kt_customers_table_wrapper" class="dataTables_wrapper dt-bootstrap4 no-footer">
                            <div class="table-responsive">
                                <table aria-describedby=""
                                    class="table align-middle table-bordered  table-row-dashed fs-6 gy-4 dataTable no-footer"
                                    id="kt_customers_table">
                                    <thead>
                                        <tr class="text-start text-black fw-bold fs-7 text-uppeGase gs-0">
                                            <th class="min-w-150px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                                rowspan="1" colspan="1"
                                                aria-label="Customer Name: activate to sort column ascending"
                                                style="width: 125px;"><?php echo $agence ?>
                                            </th>
                                            <th class="min-w-0px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                                rowspan="1" colspan="1"
                                                aria-label="Company: activate to sort column ascending"
                                                style="width: 05px;"><?php echo $Level ?>
                                            </th>
                                            <th class="min-w-100px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                                rowspan="1" colspan="2"
                                                aria-label="Company: activate to sort column ascending"
                                                style="width: 100px;"><?php echo $nbre_qcm_effectue ?>
                                            </th>
                                            <th class="min-w-200px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                                rowspan="1" colspan="2"
                                                aria-label="Payment Method: activate to sort column ascending"
                                                style="width: 150.516px;"><?php echo $qcm_realises ?>
                                            </th>
                                            <!-- <th class="min-w-0px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                                rowspan="1" colspan="1"
                                                aria-label="Company: activate to sort column ascending"
                                                style="width: 05px;"><?php echo $taux_evolution_qcm ?>
                                            </th> -->
                                            <th class="min-w-200px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                                rowspan="1" colspan="2"
                                                aria-label="Created Date: activate to sort column ascending"
                                                style="width: 152.719px;"><?php echo $qcm_techs_realises ?>
                                            </th>
                                            <!-- <th class="min-w-0px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                                rowspan="1" colspan="1"
                                                aria-label="Company: activate to sort column ascending"
                                                style="width: 05px;"><?php echo $taux_evolution_tache_tech ?>
                                            </th> -->
                                            <th class="min-w-200px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                                rowspan="1" colspan="2"
                                                aria-label="Created Date: activate to sort column ascending"
                                                style="width: 152.719px;"><?php echo $qcm_manager_realises ?>
                                            </th>
                                            <!-- <th class="min-w-0px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                                rowspan="1" colspan="1"
                                                aria-label="Company: activate to sort column ascending"
                                                style="width: 05px;"><?php echo $taux_evolution_tache_manager ?>
                                            </th> -->
                                        </tr>
                                    </thead>
                                    <tbody class="fw-semibold text-gray-600" id="table">
                                        <?php foreach ($countryAgencies as $agency) { ?>
                                            <tr  etat="<?php echo $results[$agency]['Junior']['country']?>">
                                                <th class="text-uppercase text-center" style='text-align: center; vertical-align: middle; height: 50px;' rowspan="<?php echo $i ?>">
                                                    <?php echo $agency ?>
                                                </th>
                                                <?php foreach (['Junior', 'Senior', 'Expert', 'Global'] as $level) { ?>
                                                    <tr class="odd <?php if ($level === 'Global') echo 'fw-bolder'; ?>" etat="<?php echo $results[$agency][$level]['country']?>" <?php if ($level === 'Global') echo 'style="background-color: #edf2f7;"'; ?>>
                                                        <td class="text-center" style='text-align: center; vertical-align: middle; height: 50px;'>
                                                            <?php echo $level ?>
                                                        </td>
                                                        <td class="text-center" style="background-color: #edf2f7; text-align: center; vertical-align: middle; height: 50px;">
                                                            <?php echo $results[$agency][$level]['countSavoirs'] + $results[$agency][$level]['countTechSavFais'] + $results[$agency][$level]['countMaSavFais'] ?> / <?php echo $results[$agency][$level]['technicians'] * 3 ?>
                                                        </td>
                                                        <td class="text-center" style="background-color: #edf2f7; text-align: center; vertical-align: middle; height: 50px;">
                                                            <?php $technicianCount = $results[$agency][$level]['technicians'];
                                                            if ($technicianCount > 0) {
                                                                $percentage = ceil(($results[$agency][$level]['countSavoirs'] + $results[$agency][$level]['countTechSavFais'] + $results[$agency][$level]['countMaSavFais']) * 100 / ($technicianCount * 3));
                                                            } else {
                                                                $percentage = 0; // or any other appropriate value or message
                                                            }
                                                            echo $percentage . '%'; ?>
                                                        </td>
                                                        <td class="text-center" style='text-align: center; vertical-align: middle; height: 50px;'>
                                                            <?php echo $results[$agency][$level]['countSavoirs'] ?> / <?php echo $results[$agency][$level]['technicians'] ?>
                                                        </td>
                                                        <td class="text-center" style='text-align: center; vertical-align: middle; height: 50px;'>
                                                            <?php
                                                            if ($technicianCount > 0) {
                                                                $percentage = ceil(($results[$agency][$level]['countSavoirs']) * 100 / $technicianCount);
                                                            } else {
                                                                $percentage = 0; // or any other appropriate value or message
                                                            }
                                                            echo $percentage . '%'; ?>
                                                        </td>
                                                        <td class="text-center" style='text-align: center; vertical-align: middle; height: 50px;'>
                                                            <?php echo $results[$agency][$level]['countTechSavFais'] ?> / <?php echo $results[$agency][$level]['technicians'] ?>
                                                        </td>
                                                        <td class="text-center" style='text-align: center; vertical-align: middle; height: 50px;'>
                                                            <?php $technicianCount = $results[$agency][$level]['technicians'];
                                                            if ($technicianCount > 0) {
                                                                $percentage = ceil(($results[$agency][$level]['countTechSavFais']) * 100 / $technicianCount);
                                                            } else {
                                                                $percentage = 0; // or any other appropriate value or message
                                                            }
                                                            echo $percentage . '%'; ?>
                                                        </td>
                                                        <td class="text-center" style='text-align: center; vertical-align: middle; height: 50px;'>
                                                            <?php echo $results[$agency][$level]['countMaSavFais'] ?> / <?php echo $results[$agency][$level]['technicians'] ?>
                                                        </td>
                                                        <td class="text-center" style='text-align: center; vertical-align: middle; height: 50px;'>
                                                            <?php $technicianCount = $results[$agency][$level]['technicians'];
                                                            if ($technicianCount > 0) {
                                                                $percentage = ceil(($results[$agency][$level]['countMaSavFais']) * 100 / $technicianCount);
                                                            } else {
                                                                $percentage = 0; // or any other appropriate value or message
                                                            }
                                                            echo $percentage . '%'; ?>
                                                        </td>
                                                    </tr>
                                                    <?php 
                                                        // Calculer le total pour les filiales
                                                        $results['GROUPE CFAO'][$level]['technicians'] += $results[$agency][$level]['technicians'];
                                                        $results['GROUPE CFAO'][$level]['tests'] += $results[$agency][$level]['tests'];
                                                        $results['GROUPE CFAO'][$level]['countSavoirs'] += $results[$agency][$level]['countSavoirs'];
                                                        $results['GROUPE CFAO'][$level]['countMaSavFais'] += $results[$agency][$level]['countMaSavFais'];
                                                        $results['GROUPE CFAO'][$level]['countTechSavFais'] += $results[$agency][$level]['countTechSavFais'];
                                                    ?>
                                                <?php } ?>
                                            </tr>
                                        <?php } ?>
                                        <tr class="odd" etat="<?php echo $results[$agency][$level]['country']?>">
                                            <th class=" text-center" colspan="10">
                                            </th>
                                        </tr>
                                        <th class="text-uppercase text-center" style='text-align: center; vertical-align: middle; height: 50px;' rowspan="<?php echo $i ?>">
                                            <?php echo 'GROUPE CFAO' ?>
                                        </th>
                                        <?php foreach (['Junior', 'Senior', 'Expert', 'Global'] as $level) { ?>
                                            <tr class="odd <?php if ($level === 'Global') echo 'fw-bolder'; ?>" <?php if ($level === 'Global') echo 'style="background-color: #edf2f7; font-size: bolder;"'; ?>>
                                                <td class="text-center" style='text-align: center; vertical-align: middle; height: 50px;'>
                                                    <?php echo $level ?>
                                                </td>
                                                <td class="text-center" style="background-color: #edf2f7; text-align: center; vertical-align: middle; height: 50px;">
                                                    <?php echo $results['GROUPE CFAO'][$level]['countSavoirs'] + $results['GROUPE CFAO'][$level]['countTechSavFais'] + $results['GROUPE CFAO'][$level]['countMaSavFais'] ?> / <?php echo $results['GROUPE CFAO'][$level]['technicians'] * 3 ?>
                                                </td>
                                                <td class="text-center" style="background-color: #edf2f7; text-align: center; vertical-align: middle; height: 50px;">
                                                    <?php $technicianCount = $results['GROUPE CFAO'][$level]['technicians'];
                                                    if ($technicianCount > 0) {
                                                        $percentage = ceil(($results['GROUPE CFAO'][$level]['countSavoirs'] + $results['GROUPE CFAO'][$level]['countTechSavFais'] + $results['GROUPE CFAO'][$level]['countMaSavFais']) * 100 / ($technicianCount * 3));
                                                    } else {
                                                        $percentage = 0; // or any other appropriate value or message
                                                    }
                                                    echo $percentage . '%'; ?>
                                                </td>
                                                <td class="text-center" style='text-align: center; vertical-align: middle; height: 50px;'>
                                                    <?php echo $results['GROUPE CFAO'][$level]['countSavoirs'] ?> / <?php echo $results['GROUPE CFAO'][$level]['technicians'] ?>
                                                </td>
                                                <td class="text-center" style='text-align: center; vertical-align: middle; height: 50px;'>
                                                    <?php
                                                    if ($technicianCount > 0) {
                                                        $percentage = ceil(($results['GROUPE CFAO'][$level]['countSavoirs']) * 100 / $technicianCount);
                                                    } else {
                                                        $percentage = 0; // or any other appropriate value or message
                                                    }
                                                    echo $percentage . '%'; ?>
                                                </td>
                                                <td class="text-center" style='text-align: center; vertical-align: middle; height: 50px;'>
                                                    <?php echo $results['GROUPE CFAO'][$level]['countTechSavFais'] ?> / <?php echo $results['GROUPE CFAO'][$level]['technicians'] ?>
                                                </td>
                                                <td class="text-center" style='text-align: center; vertical-align: middle; height: 50px;'>
                                                    <?php $technicianCount = $results['GROUPE CFAO'][$level]['technicians'];
                                                    if ($technicianCount > 0) {
                                                        $percentage = ceil(($results['GROUPE CFAO'][$level]['countTechSavFais']) * 100 / $technicianCount);
                                                    } else {
                                                        $percentage = 0; // or any other appropriate value or message
                                                    }
                                                    echo $percentage . '%'; ?>
                                                </td>
                                                <td class="text-center" style='text-align: center; vertical-align: middle; height: 50px;'>
                                                    <?php echo $results['GROUPE CFAO'][$level]['countMaSavFais'] ?> / <?php echo $results['GROUPE CFAO'][$level]['technicians'] ?>
                                                </td>
                                                <td class="text-center" style='text-align: center; vertical-align: middle; height: 50px;'>
                                                    <?php $technicianCount = $results['GROUPE CFAO'][$level]['technicians'];
                                                    if ($technicianCount > 0) {
                                                        $percentage = ceil(($results['GROUPE CFAO'][$level]['countMaSavFais']) * 100 / $technicianCount);
                                                    } else {
                                                        $percentage = 0; // or any other appropriate value or message
                                                    }
                                                    echo $percentage . '%'; ?>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
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
            <?php } ?>
        </div>
        <!--end::Container-->
    </div>
    <!--end::Post-->
    </div>
    <!--end:Row-->
<?php closeBackgroundContainer(); ?>
    <script src="https://code.jquery.com/jquery-3.6.3.js" integrity="sha256-nQLuAZGRRcILA+6dMBOvcRh5Pe310sBpanc6+QBmyVM="
        crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/gh/linways/table-to-excel@v1.0.4/dist/tableToExcel.js">
    </script>
    <script src="../../public/js/main.js"></script>
    <script>
        // Passer les variables PHP au JavaScript via un objet centralisé
        const variablesPHP = <?php echo json_encode($results, JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

        // Optionnel : Vérifiez les données dans la console
        console.log("Variables PHP dans JS:", variablesPHP);
    </script>
    <?php if ($_SESSION["profile"] != "Super Admin") { ?>
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

                function agency() {
                    var agency = document.querySelector("#agency-filter")
                    if (agency.value != "<?php echo $selectedAgency ?>") {
                        window.location.search = `?agency=${agency.value}`;
                    }
                }
                
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
                                <div class="card h-100 chart-glass-effect depth-effect">
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
                            <div class="card h-100 chart-glass-effect depth-effect">
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
                        width: '100%'
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
                        width: '100%'
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
            
            console.log(juniorScore);
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
                        width: '100%'
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
    <?php } ?>
<?php include "./partials/footer.php"; ?>
