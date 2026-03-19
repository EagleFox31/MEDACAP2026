<?php
require_once "../../vendor/autoload.php";
include_once "../language.php";
include_once "../userFilters.php";
include_once "technicianFunctions.php";
include_once "scoreFunctions.php";
include_once "trainingFunctions.php";

use MongoDB\BSON\ObjectId;

session_start();

// Vérifiez si l'utilisateur est connecté
if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
} else {

    // Connexion à MongoDB
    $mongoClient = new MongoDB\Client("mongodb://localhost:27017");
    $academy = $mongoClient->selectDatabase('academy'); // Assurez-vous que le nom de la base de données est correct

    // Charger la configuration
    $config = require 'configGF.php';

    // Instancier la classe ScoreCalculator
    $scoreCalculator = new ScoreCalculator($academy);

    $debug = [];
    // Récupérer les valeurs des filtres
    $selectedCountry = $_GET['country'] ?? 'all';
    $selectedAgency = $_GET['agency'] ?? 'all';
    $selectedManager = $_GET['manager'] ?? 'all';
    $selectedLevel = $_GET['level'] ?? 'all';

    


    // Récupérer les techniciens
    $profile = $_SESSION['profile'];
    $connectedUserId = $_SESSION['id'];


    
        // Déterminer si l'utilisateur est un Manager
    $isManager = ($profile === "Manager");
        // Définir le managerId en fonction du profil et des filtres
    $managerId = null;

        // Appeler getAlltechnician avec ou sans managerId selon le profil
        if ($isManager) {
            if ($selectedManager === 'all') {
                // Si l'utilisateur est un Manager et aucun manager spécifique n'est sélectionné,
                // récupérer les techniciens et managers rattachés à lui-même
                $managerId = $connectedUserId;
            } else {
                // Si un manager spécifique est sélectionné par le filtre, utiliser cet ID
                $managerId = $selectedManager;
            }
        } else {
            if ($selectedManager !== 'all') {
                // Pour Super Admin et Directeur Groupe, si un manager est sélectionné, utiliser cet ID
                $managerId = $selectedManager;
            }
        }

        file_put_contents('process_recommendation_debug.log', json_encode($selectedCountry) . "\n", FILE_APPEND);
        file_put_contents('process_recommendation_debug.log', json_encode($selectedLevel) . "\n", FILE_APPEND);
        file_put_contents('process_recommendation_debug.log', json_encode($selectedAgency) . "\n", FILE_APPEND);
        $technicians = getAlltechnicians($academy, $profile);
        if (empty($technicians)) {
            $debug[] = "Aucun technicien trouvé avec les filtres appliqués.";
            file_put_contents('process_recommendation_technicians_debug.log', json_encode($technicians) . "\n", FILE_APPEND);
        } else {
            $debug[] = "Nombre de techniciens trouvés : " . count($technicians);
            file_put_contents('process_recommendation_technicians_debug.log', json_encode($technicians) . "\n", FILE_APPEND);
        }

    // Filtrer les techniciens en fonction des filtres     
    $filteredTechnicians = array_filter($technicians, function ($technician) use ($selectedCountry, $selectedAgency, $selectedLevel) {
        $technicianCountry = $technician['country'] ?? 'Unknown';
        $technicianAgency = $technician['agency'] ?? 'Unknown';
        $technicianLevel = $technician['level'] ?? 'Unknown';

        $countryMatch = ($selectedCountry === 'all') || ($technicianCountry === $selectedCountry);
        $agencyMatch = ($selectedAgency === 'all') || ($technicianAgency === $selectedAgency);
        $levelMatch = ($selectedLevel === 'all') || ($technicianLevel === $selectedLevel);
        return $countryMatch && $agencyMatch && $levelMatch;
    });   
   

    // Préparer le mapping technicien => manager
    $technicianManagerMap = [];
    foreach ($technicians as $technician) {
        $technicianId = (string)$technician['_id'];
        // Utiliser 'manager' 
        $managerIdTech = isset($technician['manager']) ? (string)$technician['manager'] : null;
        if ($managerIdTech) {
            $technicianManagerMap[$technicianId] = $managerIdTech;
        }
    }

    // Log des relations techniciens-managers
    $debug[] = "Technician-Manager Map: " . json_encode($technicianManagerMap);

    $managersWithTechnicians = [];

    foreach ($technicianManagerMap as $technicianId => $managerId) {
        if (!isset($managersWithTechnicians[$managerId])) {
            $managersWithTechnicians[$managerId] = [];
        }
        $managersWithTechnicians[$managerId][] = $technicianId;
    }

    // Log de la liste des managers avec techniciens
    $debug[] = "Managers with Technicians: " . json_encode($managersWithTechnicians);


;


    // Filtrer uniquement les techniciens ayant un manager associé
    $technicianManagerMap = array_filter($technicianManagerMap);

    // Définir les niveaux à considérer
    $levels = ['Junior', 'Senior', 'Expert'];

    // Récupérer toutes les spécialités dynamiquement via la classe
    $specialities = $scoreCalculator->getAllSpecialities();

    // Récupérer tous les scores factuels et déclaratifs via la classe
    $allScores = $scoreCalculator->getAllScoresForTechnicians($academy, $technicianManagerMap, $levels, $specialities, $debug);

    // Préparer les critères pour les formations
    /*
    $recommendationCriteria = [
        'brands' => [],
        'specialities' => [],
        'levels' => [],
        'types' => []
    ];

    foreach ($technicians as $technician) {
        $technicianId = (string)$technician['_id'];
        $technicianLevel = $technician['level'];
        $applicableLevels = ($technicianLevel === 'Junior') ? ['Junior'] : (
                            ($technicianLevel === 'Senior') ? ['Junior', 'Senior'] : ['Junior', 'Senior', 'Expert']
                          );

        foreach ($applicableLevels as $level) {
            if (isset($allScores[$technicianId][$level])) {
                foreach ($allScores[$technicianId][$level] as $speciality => $scoreData) {
                    $taskScore = $scoreData['Declaratif'] ?? 0;
                    $knowledgeScore = $scoreData['Factuel'] ?? 0;

                    // Ajouter les types d'accompagnement
                    $typesAccompagnement = determineAccompagnement($taskScore, $knowledgeScore);
                    $recommendationCriteria['brands'] = array_merge($recommendationCriteria['brands'], getValidBrandsByLevel($technician, $level));
                    $recommendationCriteria['specialities'][] = $speciality;
                    $recommendationCriteria['levels'][] = $level;
                    $recommendationCriteria['types'] = array_merge($recommendationCriteria['types'], $typesAccompagnement);
                }
            }
        }
    }

    // Supprimer les doublons dans les critères
    $recommendationCriteria['brands'] = array_unique($recommendationCriteria['brands']);
    $recommendationCriteria['specialities'] = array_unique($recommendationCriteria['specialities']);
    $recommendationCriteria['levels'] = array_unique($recommendationCriteria['levels']);
    $recommendationCriteria['types'] = array_unique($recommendationCriteria['types']);
    */

    // Récupérer toutes les formations recommandées
    $trainings = getRecommendedTrainingsForTechnicians($academy, $technicians, $allScores, $config, $debug);

    // Structure des données pour faciliter l'affichage
    $formattedTrainings = [];
    foreach ($trainings['recommendations'] as $technicianId => $levelsData) {
        foreach ($levelsData as $level => $trainingList) {
            foreach ($trainingList as $trainingCode => $training) {
                // Ajouter le formatage si nécessaire
                $formattedTrainings[$technicianId][$level][$trainingCode] = [
                    'code' => $training['code'] ?? 'Code manquant',
                    'name' => $training['label'] ?? 'Nom manquant'
                ];
            }
        }
    }

    // Préparer les groupes manquants avec détails
    $formattedMissingGroups = [];
    foreach ($trainings['missingGroups'] as $technicianId => $levelsData) {
        foreach ($levelsData as $level => $groups) {
            foreach ($groups as $group) {
                // Supposons que $group contienne déjà les détails requis
                // Sinon, vous devrez enrichir ces données ici
                $formattedMissingGroups[$technicianId][$level][] = [
                    'groupName' => $group['groupName'] ?? 'Nom du groupe manquant',
                    'trainingTypes' => $group['trainingTypes'] ?? 'Type de formation non spécifié'
                ];
            }
        }
    }

    // Construire le filtre pour les managers
    $managerFilter = [
        'profile' => 'Manager',
        'active' => true
    ];

    // Ajouter le filtre pour le pays
    if ($selectedCountry !== 'all') {
        $managerFilter['country'] = $selectedCountry;
    }

    // Ajouter le filtre pour l'agence
    if ($selectedAgency !== 'all') {
        $managerFilter['agency'] = $selectedAgency;
    }

    // Récupérer les managers et leurs techniciens associés
        $managersCursor = $academy->users->aggregate([
            [
                '$match' => $managerFilter
            ],
            [
                '$lookup' => [
                    'from' => 'users', // Même collection
                    'localField' => '_id',
                    'foreignField' => 'manager',
                    'as' => 'managedUsers'
                ]
            ],
            [
                '$unwind' => '$managedUsers'
            ],
            [
                '$match' => [
                    'managedUsers.profile' => ['$in' => ['Technicien', 'Manager']]
                ]
            ],
            [
                '$project' => [
                    '_id' => 0,
                    'managerName' => ['$concat' => ['$firstName', ' ', '$lastName']],
                    'managerId' => '$_id',
                    'managedUserId' => '$managedUsers._id',
                    'managedUserName' => ['$concat' => ['$managedUsers.firstName', ' ', '$managedUsers.lastName']],
                    'managedUserProfile' => '$managedUsers.profile',
                    'managedUserCountry' => '$managedUsers.country',
                    'managedUserAgency' => '$managedUsers.agency'
                ]
            ]
        ]);

        // Construire les résultats
        $managersList = [];
        $teams = [];
        foreach ($managersCursor as $result) {
            $managerId = (string)$result['managerId'];
            $managersList[$managerId] = $result['managerName'];

            $teams[$managerId][] = [
                'id' => (string)$result['managedUserId'],
                'firstName' => $result['managedUserName'],
                'profile' => $result['managedUserProfile']
            ];
        }

    // Préparer les équipes de chaque manager
    $teams = [];
    foreach ($technicians as $technician) {
        if (isset($technician['manager'])) {
            $managerIdTech = (string)$technician['manager'];
            $teams[$managerIdTech][] = [
                'id' => (string)$technician['_id'],
                'firstName' => $technician['firstName'],
                'lastName' => $technician['lastName'],
                'level' => $technician['level']
                // Ajouter d'autres champs si nécessaire
            ];
        }
    }
    // Débogage : Enregistrer les équipes
    file_put_contents('teams_debug.log', json_encode($teams));

    // Débogage : Enregistrer la valeur de $selectedManager et la liste des managers
    file_put_contents('selected_manager_debug.log', $selectedManager);
    file_put_contents('managers_debug.log', json_encode($managersList));

    // Préparer les données pour l'affichage ou le retour
    $response = [
        'technicians' => $technicians,
        'scores' => $allScores,
        'trainings' => $formattedTrainings, // Utiliser cette structure dans l'affichage
        'missingGroups' => $formattedMissingGroups, // Inclure les groupes manquants
        'managersList' => $managersList,
        'teams' => $teams,
        'debug' => $debug
    ];

    return $response;

}
?>
