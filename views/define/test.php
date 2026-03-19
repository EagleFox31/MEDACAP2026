<?php
// performance_test.php

require_once "../../vendor/autoload.php"; // Ajustez le chemin si nécessaire
require_once "configGF.php";
require_once "TrainingRecommendation.php"; // Ajustez le chemin si nécessaire

// Initialiser MongoDB Client
$client = new MongoDB\Client("mongodb://localhost:27017"); // Ajustez l'URI si nécessaire
$academy = $client->academy; // Remplacez 'academy' par le nom réel de votre base de données

// Charger la configuration
$config = require 'configGF.php';

// Initialiser la classe TrainingRecommendation
$trainingRecommendation = new TrainingRecommendation($academy);

// Préparer les données de test
// Remplacez ces IDs par des ObjectId valides de votre base de données
$technicianIds = ['663a599afa77000054002687', '663a599afa77000054002694'];
$managerIds = ['663a5999fa7700005400267a', '663a5999fa7700005400267a'];
$levels = ['Junior', 'Senior', 'Expert'];
$specialities = ['Moteur Diesel', 'Boite de Vitesse']; // Remplacez par des spécialités valides

// Map des techniciens aux managers (IDs corrigés)
$technicianManagerMap = [
    '663a599afa77000054002687' => '663a5999fa7700005400267a',
    '663a599afa77000054002694' => '663a5999fa7700005400267a'
];

// Vérifier la validité des IDs
foreach ($technicianManagerMap as $techId => $mgrId) {
    if (!preg_match('/^[a-f\d]{24}$/i', $techId)) {
        throw new InvalidArgumentException("Invalid Technician ID: $techId");
    }
    if (!preg_match('/^[a-f\d]{24}$/i', $mgrId)) {
        throw new InvalidArgumentException("Invalid Manager ID: $mgrId");
    }
}

// Préparer les données d'échantillon
$sampleTechnicianId = $technicianIds[0];
$sampleManagerId = $managerIds[0];
$sampleSpeciality = $specialities[0];
$sampleLevel = $levels[0];
$sampleBrandName = array_keys($config['nonSupportedGroupsByBrand'])[0]; // Premier nom de marque du config
$debug = [];

// **Récupérer un technicien de test depuis la base de données**
$testTechnician = $academy->users->findOne(['_id' => new MongoDB\BSON\ObjectId($sampleTechnicianId)]);

if (!$testTechnician) {
    throw new Exception("Technician not found in the database.");
}

// Récupérer tous les techniciens actifs
$technicians = $trainingRecommendation->getAllTechnicians('Technicien', null, null, null);
$debug[] = "Retrieved Technicians: " . json_encode($technicians, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

// Obtenir tous les scores pour les techniciens
$scores = $trainingRecommendation->getAllScoresForTechnicians($technicianManagerMap, $levels, $specialities, $debug);
$debug[] = "Retrieved Scores: " . json_encode($scores, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

// Initialiser le tableau des temps d'exécution
$executionTimes = [];
$functionsToMeasure = [
    'getAllSpecialities' => function() use ($trainingRecommendation, &$debug) {
        $trainingRecommendation->getAllSpecialities();
    },
    'getFactuelScores' => function() use ($trainingRecommendation, $technicianIds, $levels, &$debug) {
        $trainingRecommendation->getFactuelScores($technicianIds, $levels);
    },
    'getDeclaratifMatchingPercentages' => function() use ($trainingRecommendation, $technicianManagerMap, $levels, $specialities, &$debug) {
        $trainingRecommendation->getDeclaratifMatchingPercentages($technicianManagerMap, $levels, $specialities, $debug);
    },
    'getAllScoresForTechnicians' => function() use ($trainingRecommendation, $technicianManagerMap, $levels, $specialities, &$debug) {
        $trainingRecommendation->getAllScoresForTechnicians($technicianManagerMap, $levels, $specialities, $debug);
    },
    'determineAccompagnement' => function() use ($trainingRecommendation) {
        $taskScore = 70;
        $knowledgeScore = 80;
        $trainingRecommendation->determineAccompagnement($taskScore, $knowledgeScore);
    },
    'getValidBrandsByLevel' => function() use ($trainingRecommendation, $testTechnician, $sampleLevel) {
        $trainingRecommendation->getValidBrandsByLevel($testTechnician, $sampleLevel);
    },
    'getNonSupportedGroups' => function() use ($trainingRecommendation, $sampleBrandName, $config) {
        $trainingRecommendation->getNonSupportedGroups($sampleBrandName, $config);
    },
    'getRecommendedTrainingsForTechnicians' => function() use ($trainingRecommendation, $technicians, $scores, $config, &$debug) {
        $trainingRecommendation->getRecommendedTrainingsForTechnicians($technicians, $scores, $config, $debug);
    }
];

// Initialiser le tableau des temps d'exécution
$executionTimes = [];
foreach ($functionsToMeasure as $functionName => $function) {
    $executionTimes[$functionName] = [];
}

// Nombre d'itérations
$iterations = 15;

// Exécuter les tests de performance
for ($i = 0; $i < $iterations; $i++) {
    foreach ($functionsToMeasure as $functionName => $function) {
        $startTime = microtime(true);
        $function(); // Appeler la fonction
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        $executionTimes[$functionName][] = $executionTime;
    }
}

// Calculer les temps moyens
$averageTimes = [];
foreach ($executionTimes as $functionName => $times) {
    $averageTimes[$functionName] = array_sum($times) / count($times);
}

// Trier les fonctions par temps moyen d'exécution (ordre décroissant)
arsort($averageTimes);

// Afficher les résultats dans un tableau HTML
?>
<!DOCTYPE html>
<html>
<head>
    <title>Performance des Fonctions</title>
    <!-- Inclure Bootstrap CSS pour le style -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
<div class="container">
    <h1 class="mt-5">Temps d'Exécution des Fonctions</h1>
    <table class="table table-bordered mt-3">
        <thead>
            <tr>
                <th>Fonction</th>
                <?php for ($i = 1; $i <= $iterations; $i++): ?>
                    <th>Itération <?php echo $i; ?> (s)</th>
                <?php endfor; ?>
                <th>Moyenne (s)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($averageTimes as $functionName => $avgTime): ?>
                <tr>
                    <td><?php echo htmlspecialchars($functionName); ?></td>
                    <?php foreach ($executionTimes[$functionName] as $time): ?>
                        <td><?php echo number_format($time, 6); ?></td>
                    <?php endforeach; ?>
                    <td><strong><?php echo number_format($avgTime, 6); ?></strong></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
  </div>
</body>
</html>
