<?php
require_once "../../vendor/autoload.php"; // Charger les dépendances nécessaires

function getValidatedResults($level = "Junior") {
    // Définir le nom du fichier de cache basé sur le niveau
    $cacheFile = __DIR__ . "/../../caches/results_cache_" . strtolower($level) . ".json";
    $cacheDuration = 3600; // Durée de validité du cache en secondes (ex : 1 heure)

    // Vérifier si le fichier de cache existe et est encore valide
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheDuration) {
        return json_decode(file_get_contents($cacheFile), true);
    }

    try {
        $conn = new MongoDB\Client("mongodb://localhost:27017");
    } catch (Exception $e) {
        die("Erreur de connexion à MongoDB: " . $e->getMessage());
    }

    $academy = $conn->academy;

    // Étape 1: Récupérer tous les techniciens actifs pour le niveau spécifié
    $technicians = $academy->results->find([
        'typeR' => 'Techniciens',
        'level' => $level,
        'active' => true
    ])->toArray();

    if (empty($technicians)) {
        die("Aucun technicien trouvé pour le niveau $level.");
    }

    // Étape 2: Récupérer les évaluations des managers pour les techniciens récupérés
    $technicianIds = array_map(function($technician) {
        return $technician['user'];
    }, $technicians);

    $managerEvaluations = $academy->results->find([
        'typeR' => 'Managers',
        'level' => $level,
        'user' => ['$in' => $technicianIds],
        'active' => true
    ])->toArray();

    // Transformer les évaluations des managers en tableau associatif pour un accès rapide
    $managerEvaluationsMap = [];
    foreach ($managerEvaluations as $evaluation) {
        $managerEvaluationsMap[(string) $evaluation['user']] = $evaluation;
    }

    // Étape 3: Récupérer les questions "Declarative" du niveau spécifié
    $questionDecla = $academy->questions->find([
        'type' => 'Declarative',
        'level' => $level,
        'active' => true
    ])->toArray();

    if (empty($questionDecla)) {
        die("Aucune question trouvée pour le niveau $level.");
    }

    // Étape 4: Créer un tableau associatif contenant les résultats pour chaque technicien et chaque question
    $tableauResultats = [];

    foreach ($technicians as $technician) {
        $technicianId = (string) $technician['user'];

        // Vérifier si le technicien a été évalué par un manager
        if (!isset($managerEvaluationsMap[$technicianId])) {
            continue; // Si aucune évaluation par un manager, passer au technicien suivant
        }

        $managerEvaluation = $managerEvaluationsMap[$technicianId];

        // Assurer que les champs 'questions' sont des tableaux PHP
        $questionsArray = (array) $technician['questions']; // Questions du technicien
        $answersArray = (array) $technician['answers']; // Réponses du technicien
        $managerQuestionsArray = (array) $managerEvaluation['questions']; // Questions du manager
        $managerAnswersArray = (array) $managerEvaluation['answers']; // Réponses du manager

        foreach ($questionDecla as $question) {
            $questionId = (string) $question['_id'];

            // Déterminer si la compétence est maîtrisée
            $isMastered = false;

            // Vérifier si la question est présente dans les réponses du technicien et du manager
            $techIndex = array_search($questionId, array_map('strval', $questionsArray));
            $manIndex = array_search($questionId, array_map('strval', $managerQuestionsArray));

            if ($techIndex !== false && $manIndex !== false &&
                isset($answersArray[$techIndex]) && isset($managerAnswersArray[$manIndex]) &&
                $answersArray[$techIndex] == 'Oui' &&
                $managerAnswersArray[$manIndex] == 'Oui') {
                $isMastered = true;
            }

            // Ajouter au tableau des résultats
            $tableauResultats[$technicianId][$questionId] = $isMastered ? "Maîtrisé" : "Non maîtrisé";
        }
    }

    // Étape 5: Stocker les résultats dans le fichier de cache
    file_put_contents($cacheFile, json_encode($tableauResultats));

    // Retourner les résultats calculés
    return $tableauResultats;
}
// Exemple d'appel de la fonction avec un niveau dynamique
$results = getValidatedResults("Senior"); // Vous pouvez changer "Senior" par "Expert", "Junior", etc.
?>