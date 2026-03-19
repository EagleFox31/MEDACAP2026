<?php
require_once "../../vendor/autoload.php";
$config = require '../configGF.php';

/**
 * Déterminer les types d'accompagnement en fonction des scores.
 */
function determineAccompagnement($taskScore, $knowledgeScore) {
    // Create connection
    $conn = new MongoDB\Client("mongodb://localhost:27017");
    // Connecting in database
    $academy = $conn->academy;
    // Connecting in collections
    $seuilsCollection = $academy->seuils;
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

    // recupérer le seuil de recommandation
    $validation = $seuilsCollection->findOne([
        '$and' => [
            ["type" => 'Training'],
            ["active" => true]
        ],
    ]);

    $level1 = $validation['level1'];
    $level2 = $validation['level2'];

    // Fonction pour catégoriser les scores
    $categorizeScore = function ($score, $low, $mid) {
        if ($score <= $low) return 'low';
        if ($score <= $mid) return 'mid';
        return 'high';
    };

    // Catégoriser les scores
    $taskCategory = $categorizeScore($taskScore, $level1, $level2);
    $knowledgeCategory = $categorizeScore($knowledgeScore, $level1, $level2);

    // Retourner l'accompagnement correspondant
    return $accompagnementMatrix[$taskCategory][$knowledgeCategory] ?? [];
}



function getValidBrandsByLevel($technician, $level) {
    // Construct the field name for the given level
    $brandField = 'brand' . ucfirst($level); // E.g., 'brandJunior', 'brandSenior'

    // Check if the brand field exists
    if (isset($technician[$brandField])) {
        $brands = $technician[$brandField];

        // Convert BSONArray to PHP array if necessary
        if ($brands instanceof MongoDB\Model\BSONArray) {
            $brands = $brands->getArrayCopy();
        }

        // Ensure it's an array and filter out invalid values
        if (is_array($brands)) {
            $validBrands = array_filter($brands, function($brand) {
                return is_string($brand) && trim($brand) !== ''; // Remove empty strings
            });

            return array_values($validBrands); // Re-index the array
        }
    }

    // Return an empty array if no valid brands exist
    return [];
}
function getNonSupportedGroups($brandName, $config) {
    return isset($config['nonSupportedGroupsByBrand'][$brandName]) 
        ? $config['nonSupportedGroupsByBrand'][$brandName] 
        : [];
}

/**
 * Obtenir les formations recommandées pour un technicien et un niveau.
 */
function getRecommendedTrainingsForTechnicians($academy, $technicians, $scores, $config, &$debug) {
    $trainingsCollection = $academy->trainings;
    $allocationsCollection = $academy->allocations;
    $recommendations = [];
    $missingGroups = [];

    $functionalGroupsByLevel = $config['functionalGroupsByLevel'];
    $nonSupportedGroupsByBrand = $config['nonSupportedGroupsByBrand'];
    
    // Étape 1 : Collecter tous les IDs des techniciens
    $technicianIds = array_map(function($tech) {
        return (string)$tech['_id'];
    }, $technicians);

    // Étape 2 : Pré-fetche toutes les allocations existantes
    $existingAllocations = fetchExistingAllocations($allocationsCollection, $technicianIds, $debug);

    // Étape 3 : Préparer les opérations en masse
    $bulkOperations = prepareBulkOperations($technicians, $scores, $functionalGroupsByLevel, $nonSupportedGroupsByBrand, $existingAllocations, $debug);

    // Étape 4 : Exécuter les opérations en masse avec le cinquième argument
    list($allocationsToInsert, $trainingsToUpdate, $recommendations, $missingGroups) = executeBulkOperations($bulkOperations, $allocationsCollection, $trainingsCollection, $debug, $existingAllocations);

    // Étape 5 : Exécuter les insertions et mises à jour en masse
    performBulkWrites($allocationsCollection, $trainingsCollection, $allocationsToInsert, $trainingsToUpdate, $debug);

    // Informations de débogage finales
    $debug[] = "Final Recommendations: " . json_encode($recommendations, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $debug[] = "Missing Groups: " . json_encode($missingGroups, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    return [
        'recommendations' => $recommendations,
        'missingGroups' => $missingGroups
    ];
}


function fetchExistingAllocations($allocationsCollection, $technicianIds, &$debug) {
    $existingAllocationsCursor = $allocationsCollection->find([
        'user' => ['$in' => array_map(function($id) { return new MongoDB\BSON\ObjectId($id); }, $technicianIds)],
        'type' => 'Training',
        'active' => false
    ], [
        'projection' => ['user' => 1, 'training' => 1]
    ]);

    $existingAllocations = [];
    foreach ($existingAllocationsCursor as $alloc) {
        $userId = (string)$alloc['user'];
        $trainingId = (string)$alloc['training'];
        $existingAllocations[$userId][$trainingId] = true;
    }

    $debug[] = "Fetched existing allocations for " . count($existingAllocations) . " technicians.";
    return $existingAllocations;
}

function prepareBulkOperations($technicians, $scores, $functionalGroupsByLevel, $nonSupportedGroupsByBrand, &$existingAllocations, &$debug) {
    $bulkOperations = [];
    foreach ($technicians as $technician) {
        $technicianId = (string)$technician['_id'];

        if (!isset($scores[$technicianId])) {
            $debug[] = "No scores found for Technician ID: $technicianId.";
            continue;
        }

        foreach ($scores[$technicianId] as $level => $scoreData) {
            $applicableGroups = $functionalGroupsByLevel[$level] ?? [];

            if (empty($applicableGroups)) {
                $debug[] = "No applicable groups for Technician ID: $technicianId at Level: $level.";
                continue;
            }

            $validBrands = getValidBrandsByLevel($technician, $level);
            $debug[] = "Technician ID: $technicianId, Level: $level, Valid Brands: " . json_encode($validBrands);

            if (empty($validBrands)) {
                $debug[] = "No valid brands for Technician ID: $technicianId at Level: $level.";
                continue;
            }

            $filteredGroups = filterApplicableGroups($validBrands, $applicableGroups, $nonSupportedGroupsByBrand, $debug, $technicianId, $level);

            foreach ($filteredGroups as $speciality) {
                $taskScore = $scoreData[$speciality]['Declaratif'] ?? 0;
                $knowledgeScore = $scoreData[$speciality]['Factuel'] ?? 0;

                $typesAccompagnement = determineAccompagnement($taskScore, $knowledgeScore);
                $debug[] = "Speciality: $speciality, Task Score: $taskScore, Knowledge Score: $knowledgeScore, Types Accompagnement: " . json_encode($typesAccompagnement);

                if (empty($typesAccompagnement)) {
                    $debug[] = "No accompaniment types applicable for Speciality: $speciality.";
                    continue;
                }

                $bulkOperations[] = [
                    'technicianId' => $technicianId,
                    'level' => $level,
                    'typesAccompagnement' => $typesAccompagnement,
                    'validBrands' => $validBrands,
                    'speciality' => $speciality
                ];
            }
        }
    }

    return $bulkOperations;
}

function filterApplicableGroups($validBrands, $applicableGroups, $nonSupportedGroupsByBrand, &$debug, $technicianId, $level) {
    $nonSupportedGroups = [];
    foreach ($validBrands as $brand) {
        if (isset($nonSupportedGroupsByBrand[$brand])) {
            foreach ($nonSupportedGroupsByBrand[$brand] as $group) {
                $nonSupportedGroups[$group] = true;
            }
        }
    }
    $nonSupportedGroups = array_keys($nonSupportedGroups);
    $debug[] = "Non-Supported Groups for Technician ID: $technicianId at Level: $level: " . json_encode($nonSupportedGroups);

    $filteredGroups = array_diff($applicableGroups, $nonSupportedGroups);
    sort($filteredGroups);

    return $filteredGroups;
}

function executeBulkOperations($bulkOperations, $allocationsCollection, $trainingsCollection, &$debug, &$existingAllocations) {
    $allocationsToInsert = [];
    $trainingsToUpdate = [];
    $recommendations = [];
    $missingGroups = [];

    // Préparer les requêtes de formation en masse
    $trainingQueries = prepareTrainingQueries($bulkOperations, $debug);

    // Rechercher toutes les formations correspondantes
    $allTrainings = findTrainings($trainingsCollection, $trainingQueries, $debug);

    foreach ($bulkOperations as $operation) {
        $technicianId = $operation['technicianId'];
        $level = $operation['level'];
        $typesAccompagnement = $operation['typesAccompagnement'];
        $validBrands = $operation['validBrands'];
        $speciality = $operation['speciality'];

        foreach ($allTrainings as $training) {
            // Gérer le champ 'speciality' qui peut être une chaîne ou un BSONArray
            if (isset($training['specialities'])) {
                if ($training['specialities'] instanceof MongoDB\Model\BSONArray) {
                    // Convertir BSONArray en tableau PHP
                    $specialities = $training['specialities']->getArrayCopy();
                } elseif (is_array($training['specialities'])) {
                    // Si c'est déjà un tableau
                    $specialities = $training['specialities'];
                } else {
                    // Sinon, traiter comme une chaîne
                    $specialities = [(string)$training['specialities']];
                }
            } else {
                $specialities = [];
            }

            // Normaliser les spécialités de la formation
            $normalizedTrainingSpecialities = array_map(function($s) {
                return strtolower(trim((string)$s));
            }, $specialities);

            // Normaliser la spécialité recherchée
            $normalizedSpeciality = strtolower(trim((string)$speciality));

            // Vérifier si la spécialité recherchée est présente dans les spécialités de la formation
            $matchSpeciality = in_array($normalizedSpeciality, $normalizedTrainingSpecialities);

            if (
                in_array($training['type'], $typesAccompagnement) &&
                in_array($training['brand'], $validBrands) &&
                $matchSpeciality &&
                $training['level'] == $level &&
                $training['active']
            ) {
                $trainingId = (string)$training['_id'];
                if (!isset($existingAllocations[$technicianId][$trainingId])) {
                    $allocationsToInsert[] = [
                        'user' => new MongoDB\BSON\ObjectId($technicianId),
                        'training' => new MongoDB\BSON\ObjectId($trainingId),
                        'type' => 'Training',
                        'level' => $training['level'],
                        // 'periods' => [],
                        // 'years' => [],
                        'active' => false,
                        "created" => date("d-m-Y H:i:s")
                    ];
                    $existingAllocations[$technicianId][$trainingId] = true;
                }

                $trainingsToUpdate[] = [
                    'updateOne' => [
                        ['_id' => new MongoDB\BSON\ObjectId($trainingId)],
                        ['$addToSet' => ['users' => new MongoDB\BSON\ObjectId($technicianId)]]
                    ]
                ];

                // Gérer le champ 'code' pour éviter les erreurs similaires
                if (isset($training['code']) && is_scalar($training['code'])) {
                    $trainingCode = strtolower(trim((string)$training['code']));
                } else {
                    $trainingCode = '';
                }
                $recommendations[$technicianId][$level][$trainingCode] = $training;
                $debug[] = "Found Training: " . json_encode($training, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }
        }

        // Si aucune formation n'a été trouvée pour ce groupe
        if (!isset($recommendations[$technicianId][$level])) {
            $missingGroups[$technicianId][$level][] = [
                'groupName' => $speciality,
                'trainingTypes' => $typesAccompagnement
            ];
            $debug[] = "No trainings found for Speciality: $speciality with Types: " . json_encode($typesAccompagnement);
        }
    }

    return [$allocationsToInsert, $trainingsToUpdate, $recommendations, $missingGroups];
}


function prepareTrainingQueries($bulkOperations, &$debug) {
    $queries = [];
    foreach ($bulkOperations as $op) {
        $queries[] = [
            'brand' => ['$in' => $op['validBrands']],
            'specialities' => new MongoDB\BSON\Regex("\\b" . preg_quote($op['speciality'], '/') . "\\b", 'i'),
            'level' => $op['level'],
            'type' => ['$in' => $op['typesAccompagnement']],
            'active' => true
        ];
    }
    $debug[] = "Prepared " . count($queries) . " training queries.";
    return $queries;
}

function findTrainings($trainingsCollection, $trainingQueries, &$debug) {
    if (empty($trainingQueries)) {
        return [];
    }

    try {
        $cursor = $trainingsCollection->find([
            '$or' => $trainingQueries
        ], [
            'projection' => [
                'code' => 1,
                'label' => 1,
                'brand' => 1,
                'specialities' => 1,
                'level' => 1,
                'type' => 1,
                'active' => 1
            ]
        ]);

        $trainings = iterator_to_array($cursor);
        $debug[] = "Fetched " . count($trainings) . " trainings from the database.";
        return $trainings;
    } catch (Exception $e) {
        error_log("MongoDB Training Fetch Error: " . $e->getMessage());
        $debug[] = "MongoDB Training Fetch Error: " . $e->getMessage();
        return [];
    }
}

function performBulkWrites($allocationsCollection, $trainingsCollection, $allocationsToInsert, $trainingsToUpdate, &$debug) {
    // Exécuter les insertions en masse pour les allocations
    if (!empty($allocationsToInsert)) {
        try {
            $result = $allocationsCollection->insertMany($allocationsToInsert);
            $debug[] = "Inserted " . $result->getInsertedCount() . " new allocations.";
        } catch (Exception $e) {
            error_log("MongoDB Bulk Insert Error: " . $e->getMessage());
            $debug[] = "MongoDB Bulk Insert Error: " . $e->getMessage();
        }
    }

    // Exécuter les mises à jour en masse pour les formations
    if (!empty($trainingsToUpdate)) {
        try {
            $result = $trainingsCollection->bulkWrite($trainingsToUpdate);
            $debug[] = "Updated " . $result->getModifiedCount() . " trainings with new users.";
        } catch (Exception $e) {
            error_log("MongoDB Bulk Update Error: " . $e->getMessage());
            $debug[] = "MongoDB Bulk Update Error: " . $e->getMessage();
        }
    }
}
?>
