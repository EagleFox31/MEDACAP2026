<?php
require_once "../../vendor/autoload.php";
use MongoDB\BSON\ObjectId;

class ScoreCalculator {
    private $resultsCollection;

    public function __construct($academy) {
        $this->resultsCollection = $academy->results;
    }

    public function getAllSpecialities() {
        try {
            $specialitiesCursor = $this->resultsCollection->distinct('speciality', ['active' => true]);
            $specialities = array_filter($specialitiesCursor, function($spec) {
                return is_string($spec) && trim($spec) !== '';
            });
            return array_values($specialities);
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log("MongoDB Error (getAllSpecialities): " . $e->getMessage());
            return [];
        }
    }

    public function getFactuelScores($technicianIds, $levels) {
        $technicianObjectIds = array_map(function($id) {
            try {
                return new MongoDB\BSON\ObjectId($id);
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

    public function getDeclaratifMatchingPercentages($technicianManagerMap, $levels, $specialities, &$debug) {
        // Extraire les IDs des techniciens et des managers
        $technicianIds = array_keys($technicianManagerMap);
        $managerIds = array_values($technicianManagerMap);

            // Ajouter des logs pour les IDs des techniciens et des managers
        $debug[] = "Technician IDs: " . json_encode($technicianIds, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $debug[] = "Manager IDs: " . json_encode($managerIds, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);


    
        // Convertir les IDs en ObjectId
        $technicianObjectIds = array_map(function($id) {
            return new MongoDB\BSON\ObjectId($id);
        }, $technicianIds);
    
        $managerObjectIds = array_unique($managerIds);
        $managerObjectIds = array_values($managerObjectIds); // Réindexer le tableau pour éviter les clés non séquentielles

        $managerObjectIds = array_map(function($id) {
            try {
                return new MongoDB\BSON\ObjectId($id);
            } catch (Exception $e) {
                error_log("Invalid manager ID: $id");
                return null;
            }
        }, $managerObjectIds);

        $managerObjectIds = array_filter($managerObjectIds); // Supprimer les nulls éventuels

            // Ajouter des logs pour les ObjectIds
        $debug[] = "Technician Object IDs: " . json_encode($technicianObjectIds, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $debug[] = "Manager Object IDs: " . json_encode($managerObjectIds, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $debug[] = "Building aggregation pipeline.";
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
            // Étape 2 : Ajouter les champs technicianId et managerId
            [
                '$addFields' => [
                    'technicianId' => '$user', // Toujours l'ID du technicien
                    'managerId' => [
                        '$cond' => [
                            ['$eq' => ['$typeR', 'Manager']],
                            '$manager', // Dans les documents du manager
                            null        // Dans les documents du technicien
                        ]
                    ]
                ]
            ],
            // Étape 3 : Créer les paires question-réponse
            [
                '$project' => [
                    'technicianId' => 1,
                    'managerId' => 1,
                    'level' => 1,
                    'speciality' => 1,
                    'typeR' => 1,
                    'questionAnswerPairs' => [
                        '$zip' => [
                            'inputs' => ['$questions', '$answers']
                        ]
                    ]
                ]
            ],
            // Étape 4 : Déplier les paires question-réponse
            ['$unwind' => '$questionAnswerPairs'],
            // Étape 5 : Séparer les questions et les réponses
            [
                '$project' => [
                    'technicianId' => 1,
                    'managerId' => 1,
                    'level' => 1,
                    'speciality' => 1,
                    'typeR' => 1,
                    'questionId' => ['$arrayElemAt' => ['$questionAnswerPairs', 0]],
                    'answer' => ['$arrayElemAt' => ['$questionAnswerPairs', 1]]
                ]
            ],
            // Étape 6 : Regrouper par technicien, niveau, spécialité et question
            [
                '$group' => [
                    '_id' => [
                        'technicianId' => '$technicianId',
                        'level' => '$level',
                        'speciality' => '$speciality',
                        'questionId' => '$questionId'
                    ],
                    'technicianAnswers' => [
                        '$push' => [
                            '$cond' => [
                                ['$eq' => ['$typeR', 'Technicien']],
                                ['$toLower' => '$answer'],
                                '$$REMOVE'
                            ]
                        ]
                    ],
                    'managerAnswers' => [
                        '$push' => [
                            '$cond' => [
                                ['$eq' => ['$typeR', 'Manager']],
                                ['$toLower' => '$answer'],
                                '$$REMOVE'
                            ]
                        ]
                    ]
                ]
            ],
            // Étape 7 : Filtrer les groupes avec réponses des deux parties
            [
                '$match' => [
                    'technicianAnswers' => ['$exists' => true, '$ne' => []],
                    'managerAnswers' => ['$exists' => true, '$ne' => []]
                ]
            ],
            // Étape 8 : Comparer les réponses pour chaque question
            [
                '$project' => [
                    'technicianId' => '$_id.technicianId',
                    'level' => '$_id.level',
                    'speciality' => '$_id.speciality',
                    'isMatchingYes' => [
                        '$cond' => [
                            [
                                '$and' => [
                                    ['$in' => ['oui', '$technicianAnswers']],
                                    ['$in' => ['oui', '$managerAnswers']]
                                ]
                            ],
                            1,
                            0
                        ]
                    ]
                ]
            ],
            // Étape 9 : Calculer le pourcentage de correspondance
            [
                '$group' => [
                    '_id' => [
                        'technicianId' => '$technicianId',
                        'level' => '$level',
                        'speciality' => '$speciality'
                    ],
                    'totalMatchingYes' => ['$sum' => '$isMatchingYes'],
                    'totalQuestions' => ['$sum' => 1]
                ]
            ],
            // Étape 10 : Calculer le pourcentage final
            [
                '$project' => [
                    '_id' => 0,
                    'technicianId' => '$_id.technicianId',
                    'level' => '$_id.level',
                    'speciality' => '$_id.speciality',
                    'percentageMatchingYes' => [
                        '$cond' => [
                            ['$gt' => ['$totalQuestions', 0]],
                            [
                                '$round' => [
                                    [
                                        '$multiply' => [
                                            ['$divide' => ['$totalMatchingYes', '$totalQuestions']],
                                            100
                                        ]
                                    ],
                                    0
                                ]
                            ],
                            0
                        ]
                    ]
                ]
            ]
        ];
        $debug[] = "Aggregation pipeline constructed.";
    
         // Exécution du pipeline
        try {
            $debug[] = "Executing aggregation pipeline.";
            $resultsCursor = $this->resultsCollection->aggregate($pipeline);
            $debug[] = "Aggregation executed successfully.";
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log("MongoDB Aggregation Error (getDeclaratifMatchingPercentages): " . $e->getMessage());
            $debug[] = "MongoDB Aggregation Error (getDeclaratifMatchingPercentages): " . $e->getMessage();
            return [];
        }

         // Convertir le curseur en tableau pour le débogage
        $resultsArray = iterator_to_array($resultsCursor);
        $debug[] = "Results from aggregation: " . json_encode($resultsArray, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    
        // Traitement des résultats
        $declaratifScores = [];
        $debug[] = "Processing aggregation results.";

        foreach ($resultsArray as $doc) {
            $debug[] = "Processing document: " . json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            
            if (!isset($doc['technicianId'], $doc['level'], $doc['speciality'], $doc['percentageMatchingYes'])) {
                error_log("Missing fields in document: " . json_encode($doc));
                $debug[] = "Missing fields in document: " . json_encode($doc);
                continue;
            }
            // Extraction des informations
            $technicianId = (string) $doc['technicianId'];
            $level = $doc['level'];
            $speciality = $doc['speciality'];
            $percentage = $doc['percentageMatchingYes'];

            $debug[] = "Extracted - Technician ID: $technicianId, Level: $level, Speciality: $speciality, Percentage: $percentage";

            // Stockage des résultats
            if (!isset($declaratifScores[$technicianId])) {
                $declaratifScores[$technicianId] = [];
                $debug[] = "Initialized declaratifScores for Technician ID: $technicianId";
            }
            if (!isset($declaratifScores[$technicianId][$level])) {
                $declaratifScores[$technicianId][$level] = [];
                $debug[] = "Initialized level '$level' for Technician ID: $technicianId";
            }
            if (!isset($declaratifScores[$technicianId][$level][$speciality])) {
                $declaratifScores[$technicianId][$level][$speciality] = [];
                $debug[] = "Initialized speciality '$speciality' for Technician ID: $technicianId, Level: $level";
            }

            $declaratifScores[$technicianId][$level][$speciality]['Declaratif'] = $percentage;
            $debug[] = "Assigned Declaratif score: $percentage% for Technician ID: $technicianId, Level: $level, Speciality: $speciality";
        }

        $debug[] = "Final Declaratif Scores: " . json_encode($declaratifScores, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return $declaratifScores;
    }
    
    

    function getAllScoresForTechnicians($academy, $technicianManagerMap, $levels, $specialities,&$debug) {
        $debug = [];
        // Extraire les IDs des techniciens
        $technicianIds = array_keys($technicianManagerMap);
        
        // Obtenir les scores factuels
        $factuelScores = $this->getFactuelScores($technicianIds, $levels);
        
        // Initialiser les scores déclaratifs
        // Obtenir les scores déclaratifs
        $declaratifScores = $this->getDeclaratifMatchingPercentages($technicianManagerMap, $levels, $specialities, $debug);
        $debug[] = "Declaratif Scores: " . json_encode($declaratifScores, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
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

        // Gérer les cas où certains scores Declaratif existent sans scores Factuels
        foreach ($declaratifScores as $userId => $userLevels) {
            foreach ($userLevels as $level => $levelSpecialities) {
                foreach ($levelSpecialities as $speciality => $scores) {
                    if (!isset($allScores[$userId][$level][$speciality])) {
                        $allScores[$userId][$level][$speciality] = [];
                    }
                    if (isset($scores['Declaratif'])) {
                        $allScores[$userId][$level][$speciality]['Declaratif'] = $scores['Declaratif'];
                    }
                }
            }
        }

        $debug[] = "All Scores (Factuel + Declaratif): " . json_encode($allScores, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return $allScores;
    }
    public function saveScores($academy, $allScores) {
        $scoresCollection = $academy->technicianScores;
        
        foreach ($allScores as $technicianId => $levels) {
            // Récupérer les informations du technicien
            $technician = $academy->users->findOne(['_id' => new ObjectId($technicianId)]);
            if (!$technician) {
                error_log("Technician not found: $technicianId");
                continue;
            }
            
            // Préparer le document
            $document = [
                'userId' => new ObjectId($technicianId),
                'firstName' => $technician['firstName'],
                'lastName' => $technician['lastName'],
                'levels' => []
            ];
            
            foreach ($levels as $level => $specialities) {
                foreach ($specialities as $speciality => $scores) {
                    if (!isset($document['levels'][$level])) {
                        $document['levels'][$level] = ['specialities' => []];
                    }
                    $document['levels'][$level]['specialities'][$speciality] = [
                        'factuel' => $scores['Factuel'] ?? null,
                        'declaratif' => $scores['Declaratif'] ?? null,
                        'moyenne' => null
                    ];
                    // Calculer la moyenne si les deux scores sont disponibles
                    if (isset($scores['Factuel']) && isset($scores['Declaratif'])) {
                        $document['levels'][$level]['specialities'][$speciality]['moyenne'] = round(($scores['Factuel'] + $scores['Declaratif']) / 2);
                    }
                }
            }
            
            // Insérer ou mettre à jour le document
            try {
                $scoresCollection->updateOne(
                    ['userId' => new ObjectId($technicianId)],
                    ['$set' => $document],
                    ['upsert' => true]
                );
            } catch (MongoDB\Driver\Exception\Exception $e) {
                error_log("MongoDB Update Error (saveScores): " . $e->getMessage());
            }
        }
    }

}
?>
