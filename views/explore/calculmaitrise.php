<?php
require_once "../../vendor/autoload.php"; // Charger les dépendances nécessaires

function calculateNonMasteryAndPartialMastery($technicians, $managerEvaluationsMap, $questionDecla, $totalTechniciansCount) {
    $resultats = [
        'non_maitrise' => 0,
        'partiellement_maitrise' => 0
    ];

    foreach ($questionDecla as $question) {
        $questionId = (string) $question['_id'];
        $totalMaitrise = 0; // Pour vérifier combien de techniciens ont maîtrisé cette question

        foreach ($technicians as $technician) {
            $technicianId = (string) $technician['_id'];

            if (!isset($managerEvaluationsMap[$technicianId])) {
                continue; // Si pas d'évaluation de manager, ignorer
            }

            $managerEvaluation = $managerEvaluationsMap[$technicianId];
            $questionsArray = (array) $technician['questions'];
            $answersArray = (array) $technician['answers'];
            $managerQuestionsArray = (array) $managerEvaluation['questions'];
            $managerAnswersArray = (array) $managerEvaluation['answers'];

            $techIndex = array_search($questionId, array_map('strval', $questionsArray));
            $manIndex = array_search($questionId, array_map('strval', $managerQuestionsArray));

            if ($techIndex !== false && $manIndex !== false &&
                isset($answersArray[$techIndex]) && isset($managerAnswersArray[$manIndex])) {

                if ($answersArray[$techIndex] == 'Oui' && $managerAnswersArray[$manIndex] == 'Oui') {
                    $totalMaitrise++;
                }
            }
        }

        // Calculer les non-maîtrises (0)
        if ($totalMaitrise === 0) {
            $resultats['non_maitrise']++;
        }

        // Calculer les maîtrises partielles (1) si le nombre de maîtrise est inférieur à la moitié du nombre de techniciens
        if ($totalMaitrise > 0 && $totalMaitrise < ($totalTechniciansCount / 2)) {
            $resultats['partiellement_maitrise']++;
        }
    }

    return $resultats;
}

function getTechniciansByLevelAndLocation($academy, $level, $country) {
    // Filtrer les techniciens actifs pour un niveau donné et un pays spécifique
    return $academy->users->find([
        'profile' => 'Technicien',
        'level' => $level,
        'country' => $country,
        'active' => true
    ])->toArray();
}

function getDeclarativeQuestions($academy, $level) {
    return $academy->questions->find([
        'type' => 'Declarative',
        'level' => $level,
        'active' => true
    ])->toArray();
}

function getManagerEvaluations($academy, $level, $technicianIds) {
    return $academy->results->find([
        'typeR' => 'Managers',
        'level' => $level,
        'user' => ['$in' => $technicianIds],
        'active' => true
    ])->toArray();
}

?>
