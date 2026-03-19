<?php
require "../../vendor/autoload.php"; // Assurez-vous que le MongoDB PHP Driver est installé via Composer

use MongoDB\Client;
use MongoDB\Exception\Exception;

/**
 * Convertit récursivement un BSONDocument ou BSONArray en tableau PHP natif.
 *
 * @param mixed $bson L'objet BSONArray, BSONDocument ou valeur scalaire.
 * @return mixed Le tableau PHP converti ou la valeur scalaire.
 */
function bsonToPhp($bson) {
    if ($bson instanceof MongoDB\Model\BSONDocument || $bson instanceof MongoDB\Model\BSONArray) {
        $array = $bson->getArrayCopy();
        foreach ($array as $key => $value) {
            $array[$key] = bsonToPhp($value);
        }
        return $array;
    }
    return $bson;
}

/**
 * Récupère et agrège les statistiques de formation des managers et techniciens.
 *
 * @return array|false Retourne le tableau agrégé des statistiques ou false en cas d'erreur.
 */
function getTrainingStatistics() {
    try {
        // ------------------------------------------------------------------
        // 1) Connexion aux collections
        // ------------------------------------------------------------------
        $client = new Client("mongodb://localhost:27017");
        $database = $client->academy;

        $collectionManagers = $database->managersBySubsidiaryAgency;
        $collectionTrainingStats = $database->allManagersTrainingStats;
        $collectionUsers = $database->users;
        $collectionScores = $database->technicianBrandScores; // Si nécessaire

        // ------------------------------------------------------------------
        // 2) Précharger les données de 'users' et 'allManagersTrainingStats'
        // ------------------------------------------------------------------

        // a) Précharger les utilisateurs dans un tableau associatif par managerId
        $usersCursor = $collectionUsers->find([], [
            'projection' => [
                '_id' => 1,
                'firstName' => 1,
                'lastName' => 1,
                'subsidiary' => 1,
                'agency' => 1
            ]
        ]);

        $usersMap = [];
        foreach ($usersCursor as $user) {
            // Convertir ObjectId en string
            $userId = (string)$user['_id'];
            $usersMap[$userId] = [
                'firstName' => $user['firstName'],
                'lastName' => $user['lastName'],
                'subsidiary' => isset($user['subsidiary']) ? $user['subsidiary'] : 'Unknown',
                'agency' => isset($user['agency']) ? $user['agency'] : 'Unknown'
            ];
        }

        // b) Précharger les statistiques de formation des managers dans un tableau associatif par managerId
        $trainingStatsCursor = $collectionTrainingStats->find([], [
            'projection' => [
                '_id' => 1, // Utilisation de '_id' au lieu de 'managerId'
                'totalTrainingsByLevel' => 1,
                'trainingsByBrandAndLevel' => 1, // Assurez-vous que ce champ existe dans la collection
                'technicians' => 1 // Ajout pour précharger les données des techniciens
            ]
        ]);

        $trainingStatsMap = [];
        foreach ($trainingStatsCursor as $stat) {
            if (isset($stat['_id']) && isset($stat['totalTrainingsByLevel'])) {
                // Convertir ObjectId en string et convertir BSONDocument en tableau
                $managerId = (string)$stat['_id'];
                $trainingStatsMap[$managerId] = [
                    'totalTrainingsByLevel' => bsonToPhp($stat['totalTrainingsByLevel']),
                    'trainingsByBrandAndLevel' => isset($stat['trainingsByBrandAndLevel']) ? bsonToPhp($stat['trainingsByBrandAndLevel']) : [],
                    'technicians' => isset($stat['technicians']) ? bsonToPhp($stat['technicians']) : []
                ];
            }
        }

        // ------------------------------------------------------------------
        // 3) Lecture de la collection managersBySubsidiaryAgency
        //    et construction de la hiérarchie + agrégation
        // ------------------------------------------------------------------
        $cursor = $collectionManagers->find([]);
        $result = [];

        foreach ($cursor as $document) {
            $agencies = bsonToPhp($document['agencies']);

            // Parcourir les agences
            foreach ($agencies as $agency) {
                $agencyName = $agency['_id']; // Nom de l'agence
                $managers = isset($agency['managers']) ? bsonToPhp($agency['managers']) : [];

                // Parcourir les managers de l'agence
                foreach ($managers as $manager) {
                    // Convertir ObjectId en string
                    $managerId = (string)$manager['_id']; // ID du manager (supposé correspondre à 'users' _id)
                    $managerName = trim($manager['firstName']) . " " . trim($manager['lastName']);

                    // Récupérer les informations de l'utilisateur
                    if (isset($usersMap[$managerId])) {
                        $subsidiary = $usersMap[$managerId]['subsidiary'];
                        $agencyFromUser = $usersMap[$managerId]['agency'];
                    } else {
                        $subsidiary = 'Unknown';
                        $agencyFromUser = $agencyName; // Utiliser le nom de l'agence de 'managersBySubsidiaryAgency' si pas trouvé dans 'users'
                    }

                    // Récupérer les formations par niveau et par marque depuis 'allManagersTrainingStats'
                    $trainingStats = isset($trainingStatsMap[$managerId]) ? $trainingStatsMap[$managerId] : [
                        'totalTrainingsByLevel' => [],
                        'trainingsByBrandAndLevel' => [],
                        'technicians' => []
                    ];

                    $totalTrainingsByLevel = $trainingStats['totalTrainingsByLevel'];
                    $trainingsByBrandAndLevel = $trainingStats['trainingsByBrandAndLevel'];
                    $techniciansData = $trainingStats['technicians'];

                    // Calculer le total des formations
                    $totalTrainings = array_sum($totalTrainingsByLevel);

                    // Initialiser la structure de la filiale
                    if (!isset($result[$subsidiary])) {
                        $result[$subsidiary] = [
                            'agencies'   => [],
                            'totalTrainingsByBrandAndLevel' => [] // Initialisation pour les totaux par marque
                        ];
                    }

                    // Initialiser la structure de l'agence
                    if (!isset($result[$subsidiary]['agencies'][$agencyFromUser])) {
                        $result[$subsidiary]['agencies'][$agencyFromUser] = [
                            'managers' => [],
                            'totalTrainingsByBrandAndLevel' => [] // Initialisation pour les totaux par marque
                        ];
                    }

                    // Initialisation de trainingsByBrandAndLevel pour le manager
                    $managerTrainingsByBrand = [];

                    // Intégrer les totaux par marque et par niveau depuis 'allManagersTrainingStats' si disponibles
                    if (!empty($trainingsByBrandAndLevel)) {
                        foreach ($trainingsByBrandAndLevel as $brandName => $brandData) {
                            // Calculer 'totalByBrand' en sommant les formations par niveau
                            $totalByBrand = 0;
                            $totalByLevel = [];
                            foreach ($brandData as $level => $count) {
                                // Assurez-vous que $count est un entier
                                if (is_int($count) || is_float($count)) {
                                    $totalByBrand += $count;
                                    $totalByLevel[$level] = $count;
                                } elseif (is_string($count) && is_numeric($count)) {
                                    $count = (int)$count;
                                    $totalByBrand += $count;
                                    $totalByLevel[$level] = $count;
                                } else {
                                    // Si $count est encore un objet, essayez de le convertir
                                    $count = bsonToPhp($count);
                                    if (is_numeric($count)) {
                                        $totalByBrand += $count;
                                        $totalByLevel[$level] = $count;
                                    } else {
                                        $totalByBrand += 0;
                                        $totalByLevel[$level] = 0;
                                    }
                                }
                            }

                            $managerTrainingsByBrand[$brandName] = [
                                'totalByBrand' => $totalByBrand,
                                'totalByLevel' => $totalByLevel
                            ];
                        }
                    }

                    // Initialiser la liste des techniciens pour ce manager
                    $techniciansList = [];

                    // Parcourir les techniciens du manager
                    if (!empty($techniciansData)) {
                        foreach ($techniciansData as $technician) {
                            // Extraction des informations du technicien
                            $technicianId = isset($technician['userId']) ? (string)$technician['userId'] : (string)$technician['_id'];
                            $technicianName = trim($technician['firstName']) . " " . trim($technician['lastName']);
                            $trainingsByLevel = isset($technician['trainingsByLevel']) ? bsonToPhp($technician['trainingsByLevel']) : [];
                            $trainingsByBrandAndLevel = isset($technician['trainingsByBrandAndLevel']) ? bsonToPhp($technician['trainingsByBrandAndLevel']) : [];

                            // Initialiser les totaux par marque et par niveau pour le technicien
                            $technicianTrainingsByBrand = [];

                            if (!empty($trainingsByBrandAndLevel)) {
                                foreach ($trainingsByBrandAndLevel as $brandName => $brandData) {
                                    // Calculer 'totalByBrand' en sommant les formations par niveau
                                    $totalByBrand = 0;
                                    $totalByLevel = [];
                                    foreach ($brandData as $level => $count) {
                                        // Assurez-vous que $count est un entier
                                        if (is_int($count) || is_float($count)) {
                                            $totalByBrand += $count;
                                            $totalByLevel[$level] = $count;
                                        } elseif (is_string($count) && is_numeric($count)) {
                                            $count = (int)$count;
                                            $totalByBrand += $count;
                                            $totalByLevel[$level] = $count;
                                        } else {
                                            // Si $count est encore un objet, essayez de le convertir
                                            $count = bsonToPhp($count);
                                            if (is_numeric($count)) {
                                                $totalByBrand += $count;
                                                $totalByLevel[$level] = $count;
                                            } else {
                                                $totalByBrand += 0;
                                                $totalByLevel[$level] = 0;
                                            }
                                        }
                                    }

                                    $technicianTrainingsByBrand[$brandName] = [
                                        'totalByBrand' => $totalByBrand,
                                        'totalByLevel' => $totalByLevel
                                    ];
                                }
                            }

                            // Créer l’entrée technicien
                            $technicianEntry = [
                                'name' => $technicianName,
                                'totalTrainingsByLevel' => $trainingsByLevel, // Ajout du détail par niveau
                                'totalTrainingsByBrandAndLevel' => $technicianTrainingsByBrand // Ajout des totaux par marque et niveau
                            ];

                            // Ajouter le technicien à la liste
                            $techniciansList[] = $technicianEntry;

                            // Fusionner les totaux par marque et niveau dans l'agence (si vous souhaitez les inclure)
                            foreach ($technicianTrainingsByBrand as $brandName => $brandData) {
                                if (!isset($result[$subsidiary]['agencies'][$agencyFromUser]['totalTrainingsByBrandAndLevel'][$brandName])) {
                                    $result[$subsidiary]['agencies'][$agencyFromUser]['totalTrainingsByBrandAndLevel'][$brandName] = [
                                        'totalByBrand' => 0,
                                        'totalByLevel' => []
                                    ];
                                }

                                // Ajouter au totalByBrand
                                $result[$subsidiary]['agencies'][$agencyFromUser]['totalTrainingsByBrandAndLevel'][$brandName]['totalByBrand'] += $brandData['totalByBrand'];

                                // Ajouter au totalByLevel
                                foreach ($brandData['totalByLevel'] as $level => $count) {
                                    if (!isset($result[$subsidiary]['agencies'][$agencyFromUser]['totalTrainingsByBrandAndLevel'][$brandName]['totalByLevel'][$level])) {
                                        $result[$subsidiary]['agencies'][$agencyFromUser]['totalTrainingsByBrandAndLevel'][$brandName]['totalByLevel'][$level] = 0;
                                    }
                                    $result[$subsidiary]['agencies'][$agencyFromUser]['totalTrainingsByBrandAndLevel'][$brandName]['totalByLevel'][$level] += $count;
                                }
                            }
                        }
                    }

                    // Créer l’entrée manager avec les techniciens
                    $managerEntry = [
                        'name' => $managerName,
                        'totalTrainings' => $totalTrainings,
                        'totalTrainingsByLevel' => $totalTrainingsByLevel, // Ajout du détail par niveau
                        'totalTrainingsByBrandAndLevel' => $managerTrainingsByBrand, // Ajout des totaux par marque et niveau
                        'technicians' => $techniciansList // Ajout des techniciens
                    ];

                    // Ajouter le manager dans la liste
                    $result[$subsidiary]['agencies'][$agencyFromUser]['managers'][] = $managerEntry;

                    // Fusionner les totaux par marque et niveau dans l'agence
                    foreach ($managerTrainingsByBrand as $brandName => $brandData) {
                        if (!isset($result[$subsidiary]['agencies'][$agencyFromUser]['totalTrainingsByBrandAndLevel'][$brandName])) {
                            $result[$subsidiary]['agencies'][$agencyFromUser]['totalTrainingsByBrandAndLevel'][$brandName] = [
                                'totalByBrand' => 0,
                                'totalByLevel' => []
                            ];
                        }

                        // Ajouter au totalByBrand
                        $result[$subsidiary]['agencies'][$agencyFromUser]['totalTrainingsByBrandAndLevel'][$brandName]['totalByBrand'] += $brandData['totalByBrand'];

                        // Ajouter au totalByLevel
                        foreach ($brandData['totalByLevel'] as $level => $count) {
                            if (!isset($result[$subsidiary]['agencies'][$agencyFromUser]['totalTrainingsByBrandAndLevel'][$brandName]['totalByLevel'][$level])) {
                                $result[$subsidiary]['agencies'][$agencyFromUser]['totalTrainingsByBrandAndLevel'][$brandName]['totalByLevel'][$level] = 0;
                            }
                            $result[$subsidiary]['agencies'][$agencyFromUser]['totalTrainingsByBrandAndLevel'][$brandName]['totalByLevel'][$level] += $count;
                        }
                    }

                    // Ajouter les totaux par marque et niveau à la filiale
                    foreach ($managerTrainingsByBrand as $brandName => $brandData) {
                        if (!isset($result[$subsidiary]['totalTrainingsByBrandAndLevel'][$brandName])) {
                            $result[$subsidiary]['totalTrainingsByBrandAndLevel'][$brandName] = [
                                'totalByBrand' => 0,
                                'totalByLevel' => []
                            ];
                        }

                        // Ajouter au totalByBrand
                        $result[$subsidiary]['totalTrainingsByBrandAndLevel'][$brandName]['totalByBrand'] += $brandData['totalByBrand'];

                        // Ajouter au totalByLevel
                        foreach ($brandData['totalByLevel'] as $level => $count) {
                            if (!isset($result[$subsidiary]['totalTrainingsByBrandAndLevel'][$brandName]['totalByLevel'][$level])) {
                                $result[$subsidiary]['totalTrainingsByBrandAndLevel'][$brandName]['totalByLevel'][$level] = 0;
                            }
                            $result[$subsidiary]['totalTrainingsByBrandAndLevel'][$brandName]['totalByLevel'][$level] += $count;
                        }
                    }
                }
            }
        }

        // ------------------------------------------------------------------
        // 4) AFFICHAGE final : Hiérarchie + Agrégats
        //    (Commenté pour transformer en fonction réutilisable)
        // ------------------------------------------------------------------
        /*
        foreach ($result as $subsidiary => $subsidiaryData) {
            echo "<strong>Filiale:</strong> " . htmlspecialchars($subsidiary) . "<br>";

            // -- Affichage de totalTrainingsByLevel pour la filiale --
            if (!empty($subsidiaryData['totalTrainingsByLevel'])) {
                echo str_repeat("&nbsp;", 4) . "<u>Total Trainings by Level (Filiale):</u><br>";
                foreach ($subsidiaryData['totalTrainingsByLevel'] as $level => $count) {
                    echo str_repeat("&nbsp;", 8) . "Level: " . htmlspecialchars($level) . " => Trainings: " . htmlspecialchars($count) . "<br>";
                }
                echo "<br>";
            }

            // -- Affichage de totalTrainingsByBrandAndLevel pour la filiale --
            if (!empty($subsidiaryData['totalTrainingsByBrandAndLevel'])) {
                echo str_repeat("&nbsp;", 4) . "<u>Total Trainings by Brand and Level (Filiale):</u><br>";
                foreach ($subsidiaryData['totalTrainingsByBrandAndLevel'] as $brand => $data) {
                    echo str_repeat("&nbsp;", 8) . "Brand: " . htmlspecialchars($brand) . "<br>";
                    echo str_repeat("&nbsp;", 12) . "Total by Brand: " . htmlspecialchars($data['totalByBrand']) . "<br>";
                    echo str_repeat("&nbsp;", 12) . "<u>Total by Level:</u><br>";
                    foreach ($data['totalByLevel'] as $level => $count) {
                        echo str_repeat("&nbsp;", 16) . "Level: " . htmlspecialchars($level) . " => Trainings: " . htmlspecialchars($count) . "<br>";
                    }
                }
                echo "<br>";
            }

            // -- Parcourir les agences --
            foreach ($subsidiaryData['agencies'] as $agencyName => $agencyData) {
                echo str_repeat("&nbsp;", 2) . "<strong>Agence:</strong> " . htmlspecialchars($agencyName) . "<br>";

                // -- Affichage de totalTrainingsByLevel pour l’agence --
                if (!empty($agencyData['totalTrainingsByLevel'])) {
                    echo str_repeat("&nbsp;", 6) . "<u>Total Trainings by Level (Agence):</u><br>";
                    foreach ($agencyData['totalTrainingsByLevel'] as $level => $count) {
                        echo str_repeat("&nbsp;", 10) . "Level: " . htmlspecialchars($level) . " => Trainings: " . htmlspecialchars($count) . "<br>";
                    }
                    echo "<br>";
                }

                // -- Affichage de totalTrainingsByBrandAndLevel pour l’agence --
                if (!empty($agencyData['totalTrainingsByBrandAndLevel'])) {
                    echo str_repeat("&nbsp;", 6) . "<u>Total Trainings by Brand and Level (Agence):</u><br>";
                    foreach ($agencyData['totalTrainingsByBrandAndLevel'] as $brand => $data) {
                        echo str_repeat("&nbsp;", 10) . "Brand: " . htmlspecialchars($brand) . "<br>";
                        echo str_repeat("&nbsp;", 14) . "Total by Brand: " . htmlspecialchars($data['totalByBrand']) . "<br>";
                        echo str_repeat("&nbsp;", 14) . "<u>Total by Level:</u><br>";
                        foreach ($data['totalByLevel'] as $level => $count) {
                            echo str_repeat("&nbsp;", 18) . "Level: " . htmlspecialchars($level) . " => Trainings: " . htmlspecialchars($count) . "<br>";
                        }
                    }
                    echo "<br>";
                }

                // ---- Parcourir les managers de l’agence
                foreach ($agencyData['managers'] as $manager) {
                    $managerName = htmlspecialchars($manager['name']);
                    $totalTrainings = htmlspecialchars($manager['totalTrainings']);
                    echo str_repeat("&nbsp;", 8) . "<strong>Manager:</strong> " . $managerName . " (Total Trainings: " . $totalTrainings . ")<br>";

                    // -- Affichage des formations par niveau
                    if (!empty($manager['totalTrainingsByLevel'])) {
                        echo str_repeat("&nbsp;", 12) . "<u>Total Trainings by Level:</u><br>";
                        foreach ($manager['totalTrainingsByLevel'] as $level => $count) {
                            echo str_repeat("&nbsp;", 16) . "Level: " . htmlspecialchars($level) . " => Trainings: " . htmlspecialchars($count) . "<br>";
                        }
                    } else {
                        echo str_repeat("&nbsp;", 12) . "No training data available.<br>";
                    }
                    echo "<br>";

                    // -- Affichage de trainingsByBrandAndLevel pour le manager --
                    if (!empty($manager['totalTrainingsByBrandAndLevel'])) {
                        echo str_repeat("&nbsp;", 12) . "<u>Total Trainings by Brand and Level (Manager):</u><br>";
                        foreach ($manager['totalTrainingsByBrandAndLevel'] as $brand => $data) {
                            echo str_repeat("&nbsp;", 16) . "Brand: " . htmlspecialchars($brand) . "<br>";
                            echo str_repeat("&nbsp;", 20) . "Total by Brand: " . htmlspecialchars($data['totalByBrand']) . "<br>";
                            echo str_repeat("&nbsp;", 20) . "<u>Total by Level:</u><br>";
                            foreach ($data['totalByLevel'] as $level => $count) {
                                echo str_repeat("&nbsp;", 24) . "Level: " . htmlspecialchars($level) . " => Trainings: " . htmlspecialchars($count) . "<br>";
                            }
                        }
                        echo "<br>";
                    }

                    // ---- Parcourir les techniciens de ce manager
                    if (!empty($manager['technicians'])) {
                        echo str_repeat("&nbsp;", 12) . "<u>Techniciens:</u><br>";
                        foreach ($manager['technicians'] as $technician) {
                            $technicianName = htmlspecialchars($technician['name']);
                            echo str_repeat("&nbsp;", 16) . "<strong>Technicien:</strong> " . $technicianName . "<br>";

                            // -- Affichage des formations par niveau pour le technicien
                            if (!empty($technician['totalTrainingsByLevel'])) {
                                echo str_repeat("&nbsp;", 20) . "<u>Total Trainings by Level:</u><br>";
                                foreach ($technician['totalTrainingsByLevel'] as $level => $count) {
                                    echo str_repeat("&nbsp;", 24) . "Level: " . htmlspecialchars($level) . " => Trainings: " . htmlspecialchars($count) . "<br>";
                                }
                            } else {
                                echo str_repeat("&nbsp;", 20) . "No training data available.<br>";
                            }

                            // -- Affichage de trainingsByBrandAndLevel pour le technicien --
                            if (!empty($technician['totalTrainingsByBrandAndLevel'])) {
                                echo str_repeat("&nbsp;", 20) . "<u>Total Trainings by Brand and Level (Technicien):</u><br>";
                                foreach ($technician['totalTrainingsByBrandAndLevel'] as $brand => $data) {
                                    echo str_repeat("&nbsp;", 24) . "Brand: " . htmlspecialchars($brand) . "<br>";
                                    echo str_repeat("&nbsp;", 28) . "Total by Brand: " . htmlspecialchars($data['totalByBrand']) . "<br>";
                                    echo str_repeat("&nbsp;", 28) . "<u>Total by Level:</u><br>";
                                    foreach ($data['totalByLevel'] as $level => $count) {
                                        echo str_repeat("&nbsp;", 32) . "Level: " . htmlspecialchars($level) . " => Trainings: " . htmlspecialchars($count) . "<br>";
                                    }
                                }
                                echo "<br>";
                            }

                            echo "<br>";
                        }
                    }

                    echo "--------------------------------------------------<br>";
                }

                echo "=========================================================<br><br>";
            }
        }
        */

        // Retourner le résultat agrégé
        return $result;

    } catch (Exception $e) {
        // En cas d'erreur, vous pouvez soit retourner false, soit gérer l'erreur différemment
        // Ici, nous retournons false et vous pouvez gérer l'affichage de l'erreur en dehors de la fonction
        // echo "Erreur de connexion ou de requête : " . htmlspecialchars($e->getMessage());
        return false;
    }
}

// Exemple d'utilisation de la fonction
$trainingStatistics = getTrainingStatistics();

if ($trainingStatistics !== false) {
    // Vous pouvez traiter les données ici, par exemple les encoder en JSON
    // echo json_encode($trainingStatistics, JSON_PRETTY_PRINT);

    // Ou décommenter la partie d'affichage dans la fonction si nécessaire
} else {
    echo "Erreur lors de la récupération des statistiques de formation.";
}
?>
