<?php
// create_technician_scores.php
require_once "ScoreFunctions.php";
require_once "../../vendor/autoload.php"; 
include_once "technicianFunctions.php"; // contient votre fonction getAllTechnicians

use MongoDB\Client;

try {
    // Connexion à MongoDB
    $mongoUri = "mongodb://localhost:27017"; 
    $databaseName = "academy"; 
    $client = new Client($mongoUri);
    $academy = $client->$databaseName;
    echo "Connexion à MongoDB réussie.\n";

    // Instanciation de ScoreCalculator
    $scoreCalculator = new ScoreCalculator($academy);
    echo "Instanciation de ScoreCalculator réussie.\n";

    // Récupération automatique de tous les techniciens
    $profile = 'Technicien'; // selon vos besoins
    $technicians = getAlltechnicians($academy, $profile);
    echo "Nombre de techniciens récupérés: " . count($technicians) . "\n";

    // Construction automatique du mapping Tech => Manager
    $technicianManagerMap = [];
    foreach ($technicians as $technician) {
        $techId = (string)$technician['_id'];
        if (isset($technician['manager'])) {
            $managerId = (string)$technician['manager'];
            if (!empty($managerId)) {
                $technicianManagerMap[$techId] = $managerId;
            }
        }
    }
    echo "Mapping Technicien => Manager: " . json_encode($technicianManagerMap) . "\n";

    // Définir les niveaux et spécialités
    $levels = ["Junior", "Senior", "Expert"];
    $specialities = $scoreCalculator->getAllSpecialities();
    echo "Spécialités récupérées: " . json_encode($specialities) . "\n";

    // Calculer et sauvegarder tous les scores
    $debug = [];
    $allScores = $scoreCalculator->getAllScoresForTechnicians(
        $academy,
        $technicianManagerMap,
        $levels,
        $specialities,
        $debug
    );
    echo "Scores calculés.\n";

    // Afficher les logs de débogage
    foreach ($debug as $log) {
        echo $log . "\n";
    }

    // Sauvegarder les scores
    $scoreCalculator->saveScores($academy, $allScores);
    echo "Scores sauvegardés dans la collection technicianScores.\n";

    echo "Scores des techniciens calculés et sauvegardés avec succès.\n";

} catch (Exception $e) {
    error_log("Erreur lors de l'exécution du script: " . $e->getMessage());
    echo "Une erreur est survenue. Consultez les logs pour plus de détails.\n";
}
?>
