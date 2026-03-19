<?php
// TrainingRecommendation.php

require_once "../../vendor/autoload.php"; // Ajustez le chemin si nécessaire
use MongoDB\BSON\ObjectId;

class TrainingRecommendation {
    private $resultsCollection;
    private $usersCollection;
    private $trainingsCollection;

    public function __construct($academy) {
        $this->resultsCollection = $academy->results;
        $this->usersCollection = $academy->users;
        $this->trainingsCollection = $academy->trainings;
    }

    /**
     * Récupérer toutes les spécialités actives.
     */
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

    /**
     * Récupérer les scores factuels pour les techniciens donnés et niveaux spécifiés.
     */
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

    /**
     * Récupérer les pourcentages de correspondance déclaratifs pour les techniciens et leurs managers.
     */
    public function getDeclaratifMatchingPercentages($technicianManagerMap, $levels, $specialities, &$debug) {
        // Extraire les IDs des techniciens et des managers
        $technicianIds = array_keys($technicianManagerMap);
        $managerIds = array_values($technicianManagerMap);

        // Ajouter des logs pour les IDs des techniciens et des managers
        $debug[] = "Technician IDs: " . json_encode($technicianIds, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $debug[] = "Manager IDs: " . json_encode($managerIds, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        // Convertir les IDs en ObjectId
        $technicianObjectIds = array_map(function($id) {
            try {
                return new MongoDB\BSON\ObjectId($id);
            } catch (Exception $e) {
                error_log("Invalid technician ID: $id");
                return null;
            }
        }, $technicianIds);

        $technicianObjectIds = array_filter($technicianObjectIds);

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

        // Ajouter des logs pour les ObjectIds après réindexation
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
                                    ['$in' => ['oui', '$technicianAnswers']], // Le technicien a répondu "oui"
                                    ['$in' => ['oui', '$managerAnswers']]      // Le manager a aussi répondu "oui"
                                ]
                            ],
                            1, // Match
                            0  // Pas de match
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
                                    0 // Nombre de décimales
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

    /**
     * Récupérer tous les techniciens actifs selon les filtres spécifiés.
     */
    public function getAllTechnicians($profile, $selectedCountry = null, $selectedLevel = null, $selectedAgency = null) {
        // Filtrer les utilisateurs par profil, pays, niveau, et agence
        return $this->filterUsersByProfile($profile, $selectedCountry, $selectedLevel, $selectedAgency);
    }

    /**
     * Filtrer les utilisateurs par profil, pays, niveau, et agence.
     */
    private function filterUsersByProfile($profile, $selectedCountry = null, $selectedLevel = null, $selectedAgency = null) {
        $filter = [
            'active' => true,
            'profile' => ['$in' => ['Technicien', 'Manager']]
        ];

        // Filtrer uniquement les managers qui ont "test: true"
        $filter['$or'] = [
            ['profile' => 'Technicien'],
            [
                'profile' => 'Manager',
                'test' => true
            ]
        ];

        // Ajouter des filtres basés sur le profil utilisateur
        if ($profile === "Directeur Groupe" || $profile === "Super Admin") {
            // Directeur Groupe peut choisir de filtrer par n'importe quel pays, niveau, agence
            if ($selectedCountry && $selectedCountry !== 'tous') {
                // Si un pays spécifique est sélectionné
                $filter['country'] = $selectedCountry;
            }
            if ($selectedLevel) {
                $filter['level'] = $selectedLevel;
            }
        } elseif ($profile === "Directeur Filiale") {
            // Directeur Filiale ne peut voir que son pays et peut filtrer par niveau
            if ($selectedCountry) {
                $filter['country'] = $selectedCountry;
            } else {
                // Supposer que $_SESSION contient le pays du Directeur Filiale
                // Remplacez ceci par la méthode appropriée pour obtenir le pays du Directeur Filiale
                // Exemple :
                // $filter['country'] = $_SESSION['country'];
                // Pour le contexte de ce script, nous allons omettre cette partie
                // et considérer que le pays est fourni
            }
            if ($selectedLevel) {
                $filter['level'] = $selectedLevel;
            }
        }

        // Ajouter un filtre d'agence si spécifié
        if ($selectedAgency) {
            $filter['agency'] = $selectedAgency;
        }

        try {
            $usersCursor = $this->usersCollection->find($filter);
            $users = iterator_to_array($usersCursor);
            return $users;
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log("MongoDB Error (filterUsersByProfile): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Déterminer les types d'accompagnement en fonction des scores.
     */
    public function determineAccompagnement($taskScore, $knowledgeScore) {
        // Correspondances pour les accompagnements
        $accompagnementMatrix = [
            'low' => [
                'low' => ['Présentielle', 'Distancielle', 'E-learning', 'Coaching', 'Mentoring'],
                'mid' => ['Présentielle', 'E-learning', 'Coaching', 'Mentoring'],
                'high' => ['Présentielle', 'Coaching', 'Mentoring'],
            ],
            'mid' => [
                'low' => ['Présentielle', 'Distancielle', 'E-learning', 'Coaching'],
                'mid' => ['Présentielle', 'E-learning', 'Coaching'],
                'high' => ['Présentielle', 'Coaching'],
            ],
            'high' => [
                'low' => ['Distancielle', 'E-learning'],
                'mid' => ['E-learning'],
                'high' => [],
            ],
        ];

        // Fonction pour catégoriser les scores
        $categorizeScore = function ($score) {
            if ($score <= 60) return 'low';
            if ($score <= 80) return 'mid';
            return 'high';
        };

        // Catégoriser les scores
        $taskCategory = $categorizeScore($taskScore);
        $knowledgeCategory = $categorizeScore($knowledgeScore);

        // Retourner l'accompagnement correspondant
        return $accompagnementMatrix[$taskCategory][$knowledgeCategory] ?? [];
    }

    /**
     * Obtenir les marques valides pour un technicien donné et un niveau spécifique.
     */
    public function getValidBrandsByLevel($technician, $level) {
        // Construire le nom du champ pour le niveau donné
        $brandField = 'brand' . ucfirst($level); // Ex: 'brandJunior', 'brandSenior'

        // Vérifier si le champ de marque existe
        if (isset($technician[$brandField])) {
            $brands = $technician[$brandField];

            // Convertir BSONArray en tableau PHP si nécessaire
            if ($brands instanceof MongoDB\Model\BSONArray) {
                $brands = $brands->getArrayCopy();
            }

            // S'assurer que c'est un tableau et filtrer les valeurs invalides
            if (is_array($brands)) {
                $validBrands = array_filter($brands, function($brand) {
                    return is_string($brand) && trim($brand) !== ''; // Supprimer les chaînes vides
                });

                return array_values($validBrands); // Réindexer le tableau
            }
        }

        // Retourner un tableau vide s'il n'y a pas de marques valides
        return [];
    }

    /**
     * Obtenir les groupes non supportés pour une marque donnée selon la configuration.
     */
    public function getNonSupportedGroups($brandName, $config) {
        return isset($config['nonSupportedGroupsByBrand'][$brandName]) 
            ? $config['nonSupportedGroupsByBrand'][$brandName] 
            : [];
    }

    /**
     * Obtenir les formations recommandées pour les techniciens.
     */
    public function getRecommendedTrainingsForTechnicians($technicians, $scores, $config, &$debug) {
        $recommendations = [];
        $missingGroups = []; // Tableau pour stocker les groupes sans formation

        $functionalGroupsByLevel = $config['functionalGroupsByLevel'];
        $nonSupportedGroupsByBrand = $config['nonSupportedGroupsByBrand'];

        foreach ($technicians as $technician) {
            $technicianId = (string)$technician['_id'];

            foreach ($scores[$technicianId] ?? [] as $level => $scoreData) {
                // 1. Déterminer les groupes fonctionnels applicables par niveau
                $applicableGroups = $functionalGroupsByLevel[$level] ?? [];

                // 2. Appliquer la contrainte des marques associées au technicien
                $validBrands = $this->getValidBrandsByLevel($technician, $level);
                $debug[] = "Technician ID: $technicianId, Level: $level, Valid Brands: " . json_encode($validBrands);

                if (empty($validBrands)) {
                    $debug[] = "No valid brands for Technician ID: $technicianId at Level: $level.";
                    continue; // Aucun brand valide, ignorer ce niveau
                }

                // Récupérer tous les groupes non supportés par les marques
                $nonSupportedGroups = [];
                foreach ($validBrands as $brand) {
                    if (isset($nonSupportedGroupsByBrand[$brand])) {
                        $nonSupportedGroups = array_merge($nonSupportedGroups, $nonSupportedGroupsByBrand[$brand]);
                    }
                }
                // Éliminer les doublons
                $nonSupportedGroups = array_unique($nonSupportedGroups);
                $debug[] = "Non-Supported Groups for Brands: " . json_encode($nonSupportedGroups);

                // Filtrer les groupes fonctionnels applicables en excluant les non supportés
                $filteredGroups = array_diff($applicableGroups, $nonSupportedGroups);
                // Trier les groupes fonctionnels par ordre alphabétique
                sort($filteredGroups);

                foreach ($filteredGroups as $speciality) {
                    $taskScore = $scoreData[$speciality]['Declaratif'] ?? 0;
                    $knowledgeScore = $scoreData[$speciality]['Factuel'] ?? 0;

                    // 4. Appliquer les règles sur les scores pour déterminer les types de formations
                    $typesAccompagnement = $this->determineAccompagnement($taskScore, $knowledgeScore);
                    $debug[] = "Speciality: $speciality, Task Score: $taskScore, Knowledge Score: $knowledgeScore, Types Accompagnement: " . json_encode($typesAccompagnement);

                    if (empty($typesAccompagnement)) {
                        $debug[] = "No accompaniment types applicable for Speciality: $speciality.";
                        continue; // Aucun type d'accompagnement applicable
                    }

                    // 5. Identifier les formations pertinentes du type requis
                    $query = [
                        'brand' => ['$in' => $validBrands],
                        'speciality' => ['$regex' => new MongoDB\BSON\Regex("\b" . preg_quote($speciality, '/') . "\b", 'i')],
                        'level' => $level,
                        'type' => ['$in' => $typesAccompagnement],
                        'active' => true
                    ];

                    $debug[] = "Training Query: " . json_encode($query, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

                    try {
                        // Exécuter la requête MongoDB
                        $cursor = $this->trainingsCollection->find($query);

                        $found = false; // Flag pour vérifier si une formation est trouvée

                        foreach ($cursor as $training) {
                            $found = true;
                            // Utiliser le code comme clé pour éviter les doublons
                            $trainingCode = strtolower(trim($training['code']));
                            $recommendations[$technicianId][$level][$trainingCode] = $training;
                            $debug[] = "Found Training: " . json_encode($training, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                        }

                        if (!$found) {
                            // Ajouter tous les types de formation manquants
                            $missingGroups[$technicianId][$level][] = [
                                'groupName' => $speciality,
                                'trainingTypes' => $typesAccompagnement // Inclure tous les types
                            ];
                            $debug[] = "No trainings found for Speciality: $speciality with Types: " . json_encode($typesAccompagnement);
                        }
                    } catch (Exception $e) {
                        error_log("MongoDB Error for Technician {$technicianId}: " . $e->getMessage());
                        $debug[] = "MongoDB Error for Technician {$technicianId}: " . $e->getMessage();
                    }
                }
            }
        }

        // Retourner les recommandations et les groupes manquants
        $debug[] = "Final Recommendations: " . json_encode($recommendations, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $debug[] = "Missing Groups: " . json_encode($missingGroups, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return [
            'recommendations' => $recommendations,
            'missingGroups' => $missingGroups
        ];
    }

    /**
     * Obtenir toutes les scores (Factuel + Declaratif) pour les techniciens.
     */
    function getAllScoresForTechnicians($technicianManagerMap, $levels, $specialities,&$debug) {
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
}
?>
