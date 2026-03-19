<?php

require_once "../../vendor/autoload.php"; // Charger les dépendances nécessaires

use MongoDB\Client;
// scor_decla.php (pour les questions déclaratives)
function getTechnicianResults($selectedLevel = "Junior") {
    // Connexion à MongoDB
    try {
        $conn = new Client("mongodb://localhost:27017");
    } catch (Exception $e) {
        die("Erreur de connexion à MongoDB: " . $e->getMessage());
    }

    $academy = $conn->academy;
    $resultsCollection = $academy->results;

    // Requête pour récupérer les résultats des techniciens pour le niveau et le type spécifiés
    $cursor = $resultsCollection->find([
        'typeR' => 'Technicien - Manager',
        'type' => 'Declaratif',
        'level' => $selectedLevel,
        'active' => true
    ]);

    $tableauResultats = [];

    foreach ($cursor as $result) {
        $techId = (string)$result['user'];
        $score = isset($result['score']) ? $result['score'] : 0;
        $total = isset($result['total']) ? $result['total'] : 0;

        // Éviter la division par zéro
        if ($total > 0) {
            $percentage = ($score / $total) * 100;
            $tableauResultats[$techId] = $percentage;
        } else {
            $tableauResultats[$techId] = 0;
        }
    }

    return $tableauResultats;
}
?>