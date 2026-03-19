<?php
require_once "../../vendor/autoload.php";
include_once "../language.php";
include_once "../userFilters.php";
include_once "technicianFunctions.php";
include_once "scoreFunctions.php"; // Ce fichier contient maintenant la classe ScoreCalculator
include_once "trainingFunctions.php";

// Connexion à MongoDB
$mongoClient = new MongoDB\Client("mongodb://localhost:27017");
$academy = $mongoClient->selectDatabase('academy'); // Assurez-vous que le nom de la base de données est correct

// Charger la configuration
$config = require '../configGF.php';

// Instancier la classe ScoreCalculator
$scoreCalculator = new ScoreCalculator($academy);

$debug = [];
// Récupérer les valeurs des filtres
$selectedCountry = $_GET['country'] ?? 'all';
$selectedAgency = $_GET['agency'] ?? 'all';
$selectedLevel = $_GET['level'] ?? 'all';
$selectedManagerId = $_GET['manager'] ?? 'all';


// Récupérer les techniciens
$profile = $_SESSION['profile'];
$technicians = getAllTechnicians($academy, $profile);


// Préparer le mapping technicien => manager
$technicianManagerMap = [];
foreach ($technicians as $technician) {
    $technicianId = (string)$technician['_id'];
    // Utiliser 'manager' au lieu de 'managerId'
    $managerId = isset($technician['manager']) ? (string)$technician['manager'] : null;
    if ($managerId) {
        $technicianManagerMap[$technicianId] = $managerId;
    }
}

// Filtrer les techniciens en fonction des filtres// Filtrer les techniciens en fonction des filtres
$filteredTechnicians = array_filter($technicians, function ($technician) use ($selectedCountry, $selectedAgency, $selectedLevel, $selectedManagerId) {
    $technicianCountry = $technician['country'] ?? 'Unknown';
    $technicianAgency = $technician['agency'] ?? 'Unknown';
    $technicianLevel = $technician['level'] ?? 'Unknown';
    $technicianManagerId = isset($technician['manager']) ? (string)$technician['manager'] : 'none';

    $countryMatch = ($selectedCountry === 'all') || ($technicianCountry === $selectedCountry);
    $agencyMatch = ($selectedAgency === 'all') || ($technicianAgency === $selectedAgency);
    $levelMatch = ($selectedLevel === 'all') || ($technicianLevel === $selectedLevel);
    $managerMatch = ($selectedManagerId === 'all') || ($technicianManagerId === $selectedManagerId);

    return $countryMatch && $agencyMatch && $levelMatch && $managerMatch;
});


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
                '_id' => $training['_id'] ?? 'ID manquant',
                'code' => $training['code'] ?? 'Code manquant',
                'name' => $training['label'] ?? 'Nom manquant',
                'type' => $training['type'] ?? 'type manquant',
                'brand' => $training['brand'] ?? 'Marque de véhicule manquant',
                'level' => $training['level'] ?? 'Niveau manquant',
                'place' => $training['places'] ?? 'Lieu manquant',
                'link' => $training['link'] ?? 'Lien manquant',
                'specialities' => $training['specialities'] ?? 'Specialité manquant',
                'startDate' => $training['startDate'] ?? 'Date de Début manquant',
                'endDate' => $training['endDate'] ?? 'Date de Fin manquant',
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

// Préparer les données pour l'affichage ou le retour
$response = [
    'technicians' => $technicians,
    'scores' => $allScores,
    'trainings' => $formattedTrainings, // Utiliser cette structure dans l'affichage
    'missingGroups' => $formattedMissingGroups, // Inclure les groupes manquants
    'debug' => $debug
];

return $response;

?>
