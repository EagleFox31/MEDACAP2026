<?php
// managerTrainingFunctions.php

require_once "trainingFunctions.php";

/**
 * Récupère les formations recommandées pour tous les techniciens du Manager.
 *
 * @param MongoDB\Database $academy
 * @param array $technicians
 * @param array $config
 * @param array &$debug
 * @return array
 */
function getTrainingsForManager($academy, $technicians, $config, $managerId, &$debug) {
    $scoreCalc = new ScoreCalculator($academy);
    
    // Créer le map [techId => managerId]
    $technicianManagerMap = [];
    foreach ($technicians as $tech) {
        $techId = (string)$tech['_id'];
        $technicianManagerMap[$techId] = $managerId;
    }
    
    // Extraire la liste des spécialités à partir de 'functionalGroupsByLevel'
    $specialities = [];
    foreach ($config['functionalGroupsByLevel'] as $level => $groups) {
        if (is_array($groups)) {
            $specialities = array_merge($specialities, $groups);
        }
    }
    $specialities = array_unique($specialities);
    
    // Appeler getAllScoresForTechnicians avec les bons paramètres
    $scores = $scoreCalc->getAllScoresForTechnicians(
        $academy,
        $technicianManagerMap,
        ['Junior', 'Senior', 'Expert'], // Niveaux
        $specialities, // Liste des spécialités
        $debug
    );

    // Récupérer les formations recommandées pour les techniciens
    $trainings = getRecommendedTrainingsForTechnicians(
        $academy,
        $technicians,
        $scores,
        $config,
        $debug
    );

    // Retourner les scores et les formations recommandées
    return [
        'scores' => $scores,
        'trainings' => $trainings
    ];
}


?>
