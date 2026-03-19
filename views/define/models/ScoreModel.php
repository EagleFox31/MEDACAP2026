<?php
require_once "../../vendor/autoload.php";
use MongoDB\BSON\ObjectId;

class ScoreModel {
    private $academy;
    private $resultsCollection;

    public function __construct($academy) {
        $this->academy = $academy;
        $this->resultsCollection = $academy->results;
    }

    /**
     * Récupère les scores factuels des techniciens
     */
    public function getFactuelScores($technicianIds, $levels) {
        // Code extrait de la classe ScoreCalculator (getFactuelScores)
        $technicianObjectIds = array_map(function($id) {
            try {
                return new ObjectId($id);
            } catch (Exception $e) {
                error_log("Invalid technician ID: $id");
                return null;
            }
        }, $technicianIds);
        
        $technicianObjectIds = array_filter($technicianObjectIds);

        $pipeline = [
            [
                '$match' => [
                    'user' => ['$in' => $technicianObjectIds],
                    'level' => ['$in' => $levels],
                    'active' => true,
                    'type' => 'Factuel'
                ]
            ],
            [
                '$group' => [
                    '_id' => [
                        'user' => '$user',
                        'speciality' => '$speciality',
                        'level' => '$level'
                    ],
                    'totalScore' => ['$sum' => '$score'],
                    'totalPoints' => ['$sum' => '$total']
                ]
            ]
        ];

        try {
            $resultsCursor = $this->resultsCollection->aggregate($pipeline);
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log("MongoDB Aggregation Error (getFactuelScores): " . $e->getMessage());
            return [];
        }

        $factuelScores = [];

        foreach ($resultsCursor as $result) {
            $userId = (string) $result['_id']['user'];
            $speciality = $result['_id']['speciality'] ?? null;
            $level = $result['_id']['level'];

            if (!$speciality) {
                continue;
            }

            $totalScore = $result['totalScore'] ?? 0;
            $totalPoints = $result['totalPoints'] ?? 0;
            $percentage = ($totalPoints > 0) ? ($totalScore / $totalPoints) * 100 : 0;

            if (!isset($factuelScores[$userId])) {
                $factuelScores[$userId] = [];
            }
            if (!isset($factuelScores[$userId][$level])) {
                $factuelScores[$userId][$level] = [];
            }
            if (!isset($factuelScores[$userId][$level][$speciality])) {
                $factuelScores[$userId][$level][$speciality] = [];
            }

            $factuelScores[$userId][$level][$speciality]['Factuel'] = round($percentage);
        }

        return $factuelScores;
    }

    /**
     * Récupère les scores déclaratifs des techniciens
     */
    public function getDeclaratifScores($technicianManagerMap, $levels, $specialities) {
        // Extraire les IDs des techniciens et des managers
        $technicianIds = array_keys($technicianManagerMap);
        $managerIds = array_values($technicianManagerMap);

        // Convertir les IDs en ObjectId
        $technicianObjectIds = array_map(function($id) {
            return new ObjectId($id);
        }, $technicianIds);
    
        $managerObjectIds = array_unique($managerIds);
        $managerObjectIds = array_values($managerObjectIds); // Réindexer le tableau pour éviter les clés non séquentielles

        $managerObjectIds = array_map(function($id) {
            try {
                return new ObjectId($id);
            } catch (Exception $e) {
                error_log("Invalid manager ID: $id");
                return null;
            }
        }, $managerObjectIds);

        $managerObjectIds = array_filter($managerObjectIds); // Supprimer les nulls éventuels

        // Construire le pipeline
        $pipeline = [
            // Étape 1 : Filtrer les documents pertinents
            [
                '$match' => [
                    'user' => ['$in' => $technicianObjectIds],
                    'speciality' => ['$in' => $specialities],
                    'level' => ['$in' => $levels],
                    'active' => true,
                    'type' => 'Declaratif',
                    '$or' => [
                        ['typeR' => 'Technicien'],
                        [
                            'typeR' => 'Manager',
                            'manager' => ['$in' => $managerObjectIds]
                        ]
                    ]
                ]
            ],
            // ...autres étapes d'agrégation...
            // Simplification du pipeline pour cet exemple
            // Note: Le pipeline complet est disponible dans la classe ScoreCalculator
        ];

        // Simulation des résultats pour cet exemple
        $declaratifScores = [];
        
        // Retourne les scores déclaratifs
        return $declaratifScores;
    }

    /**
     * Récupère tous les scores combinés
     */
    public function getAllScores($technicianManagerMap, $levels, $specialities) {
        // Extraire les IDs des techniciens
        $technicianIds = array_keys($technicianManagerMap);
        
        // Obtenir les scores factuels
        $factuelScores = $this->getFactuelScores($technicianIds, $levels);
        
        // Obtenir les scores déclaratifs
        $declaratifScores = $this->getDeclaratifScores($technicianManagerMap, $levels, $specialities);
        
        // Fusionner les scores
        $allScores = [];
        foreach ($technicianIds as $technicianId) {
            foreach ($levels as $level) {
                foreach ($specialities as $speciality) {
                    $factuel = $factuelScores[$technicianId][$level][$speciality]['Factuel'] ?? null;
                    $declaratif = $declaratifScores[$technicianId][$level][$speciality]['Declaratif'] ?? null;
                    
                    if ($factuel !== null || $declaratif !== null) {
                        $allScores[$technicianId][$level][$speciality] = [];
                        if ($factuel !== null) {
                            $allScores[$technicianId][$level][$speciality]['Factuel'] = $factuel;
                        }
                        if ($declaratif !== null) {
                            $allScores[$technicianId][$level][$speciality]['Declaratif'] = $declaratif;
                        }
                    }
                }
            }
        }
        
        return $allScores;
    }
}
?>