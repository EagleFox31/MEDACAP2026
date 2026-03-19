<?php
// insertTechnicianScores.php

require_once "../../vendor/autoload.php"; // Assurez-vous que le chemin est correct
use MongoDB\Client;
use MongoDB\BSON\ObjectId;

// Configuration de la connexion MongoDB
$mongoUri = "mongodb://localhost:27017"; // Modifiez si nécessaire
$databaseName = "academy"; // Nom de votre base de données
$collectionName = "technicianScores"; // Nom de la collection cible

// Initialiser la connexion MongoDB
try {
    $mongoClient = new Client($mongoUri);
    $academy = $mongoClient->selectDatabase($databaseName);
    echo "Connexion à MongoDB réussie.\n";
} catch (Exception $e) {
    die("Erreur de connexion à MongoDB : " . $e->getMessage() . "\n");
}

// Définir le chemin du fichier JSON
$jsonFile = __DIR__ . '/managerData.json';

// Vérifier si le fichier existe
if (!file_exists($jsonFile)) {
    die("Le fichier 'managerData.json' n'existe pas. Veuillez exécuter 'getPureManagersAndTechScores.php' d'abord.\n");
}

// Lire le contenu du fichier JSON
$jsonData = file_get_contents($jsonFile);

// Décoder le JSON en tableau PHP associatif
$managerData = json_decode($jsonData, true);

// Vérifier si le JSON a été correctement décodé
if (json_last_error() !== JSON_ERROR_NONE) {
    die("Erreur lors du décodage du JSON : " . json_last_error_msg() . "\n");
}

// Définir une table de correspondance entre spécialités et marques
function getBrandBySpeciality($speciality) {
    $mapping = [
        'Arbre de Transmission' => 'HINO',
        'Assistance à la Conduite' => 'JCB',
        'Boite de Transfert' => 'KING LONG',
        'Boite de Vitesse' => 'SINOTRUK',
        'Climatisation' => 'TOYOTA FORKLIFT',
        // Ajoutez toutes les correspondances nécessaires
        'Direction' => 'TOYOTA FORKLIFT',
        'Electricité et Electronique' => 'HINO',
        'Freinage' => 'JCB',
        // ...
    ];

    return isset($mapping[$speciality]) ? $mapping[$speciality] : null;
}

// Préparer les documents à insérer
$documents = [];

// Parcourir chaque manager
foreach ($managerData as $manager) {
    $managerId = $manager['managerId'];
    $managerName = $manager['managerName'];

    // Séparer le prénom et le nom du manager
    $nameParts = explode(' ', $managerName, 2);
    $managerFirstName = $nameParts[0];
    $managerLastName = isset($nameParts[1]) ? $nameParts[1] : '';

    // Parcourir chaque technicien sous ce manager
    foreach ($manager['technicians'] as $tech) {
        $technicianId = $tech['technicianId'];
        $technicianName = $tech['technicianName'];

        // Séparer le prénom et le nom du technicien
        $techNameParts = explode(' ', $technicianName, 2);
        $techFirstName = $techNameParts[0];
        $techLastName = isset($techNameParts[1]) ? $techNameParts[1] : '';

        // Récupérer les informations de la filiale depuis la collection 'users'
        $user = $academy->users->findOne(['_id' => new ObjectId($technicianId)]);
        if (!$user || !isset($user['subsidiary'])) {
            echo "Technicien avec ID $technicianId n'a pas de filiale associée.\n";
            continue; // Passer à l'itération suivante
        }

        $subsidiaryId = $user['subsidiary']; // Supposant que c'est un ObjectId ou une chaîne valide
        $subsidiaryDoc = $academy->subsidiaries->findOne(['name' => $subsidiaryId]);
        if (!$subsidiaryDoc) {
            echo "Filiale avec ID $subsidiaryId non trouvée.\n";
            $subsidiaryName = 'Unknown Subsidiary';
        } else {
            $subsidiaryName = $subsidiaryDoc['name'];
        }

        // Récupérer les marques par niveau
        $brandJunior = isset($tech['brandJunior']) ? $tech['brandJunior'] : [];
        $brandSenior = isset($tech['brandSenior']) ? $tech['brandSenior'] : [];
        $brandExpert = isset($tech['brandExpert']) ? $tech['brandExpert'] : [];

        // Fusionner les marques et éviter les doublons
        $allBrands = array_unique(array_merge($brandJunior, $brandSenior, $brandExpert));

        // Structurer les marques avec les niveaux
        $brands = [];
        foreach ($allBrands as $brandName) {
            $levels = [
                'Junior' => in_array($brandName, $brandJunior) ? 0.0 : null,
                'Senior' => in_array($brandName, $brandSenior) ? 0.0 : null,
                'Expert' => in_array($brandName, $brandExpert) ? 0.0 : null,
            ];

            // Remplacer les valeurs nulles par 0.0 si nécessaire
            foreach ($levels as $level => $score) {
                if (is_null($score)) {
                    $levels[$level] = 0.0;
                }
            }

            $brands[] = [
                'brandName' => $brandName,
                'averageScore' => 0.0, // Placeholder, à calculer si possible
                'levels' => $levels
            ];
        }

        // Calculer les scores moyens (Factuel et Déclaratif)
        $totalFactuel = 0;
        $totalDeclaratif = 0;
        $countScores = 0;

        foreach ($tech['scores'] as $scoreByLevel) {
            foreach ($scoreByLevel['specialities'] as $specScore) {
                if (isset($specScore['factuelScore']) && is_numeric($specScore['factuelScore'])) {
                    $totalFactuel += $specScore['factuelScore'];
                }
                if (isset($specScore['declaratifScore']) && is_numeric($specScore['declaratifScore'])) {
                    $totalDeclaratif += $specScore['declaratifScore'];
                }
                $countScores++;
            }
        }

        // Calcul de la moyenne
        $averageScore = $countScores > 0 ? ($totalFactuel + $totalDeclaratif) / (2 * $countScores) : 0;

        // Calcul des statistiques
        $totalDistinctBrands = count($allBrands);
        $totalTrainings = $countScores; // Supposant une formation par score, ajustez si nécessaire

        // Compter les formations par niveau
        $totalTrainingsByLevel = [
            'Junior' => 0,
            'Senior' => 0,
            'Expert' => 0
        ];

        foreach ($tech['scores'] as $scoreByLevel) {
            $level = $scoreByLevel['level'];
            if (isset($totalTrainingsByLevel[$level])) {
                $totalTrainingsByLevel[$level] += count($scoreByLevel['specialities']);
            }
        }

        // Compter les formations par marque et niveau
        $totalTrainingsByBrandAndLevel = [];
        foreach ($tech['scores'] as $scoreByLevel) {
            $level = $scoreByLevel['level'];
            foreach ($scoreByLevel['specialities'] as $specScore) {
                $speciality = $specScore['speciality'];
                // Associer chaque spécialité à une marque
                $brandName = getBrandBySpeciality($speciality);

                if (!$brandName) {
                    echo "Aucune correspondance trouvée pour la spécialité '$speciality'.\n";
                    $brandName = "Unknown Brand"; // Vous pouvez choisir de sauter ces entrées ou de les traiter différemment
                }

                // Initialiser la structure si elle n'existe pas
                if (!isset($totalTrainingsByBrandAndLevel[$brandName])) {
                    $totalTrainingsByBrandAndLevel[$brandName] = [
                        'totalByBrand' => 0,
                        'totalByLevel' => [
                            'Junior' => 0,
                            'Senior' => 0,
                            'Expert' => 0
                        ]
                    ];
                }

                // Incrémenter les compteurs
                $totalTrainingsByBrandAndLevel[$brandName]['totalByBrand'] += 1;
                if (isset($totalTrainingsByBrandAndLevel[$brandName]['totalByLevel'][$level])) {
                    $totalTrainingsByBrandAndLevel[$brandName]['totalByLevel'][$level] += 1;
                }
            }
        }

        // Calcul des moyennes par marque et par marque-niveau
        function calculateAverageScoreByBrand($totalTrainingsByBrandAndLevel) {
            $averageScoreByBrand = [];

            foreach ($totalTrainingsByBrandAndLevel as $brand => $data) {
                if ($data['totalByBrand'] > 0) {
                    // Exemple de calcul : moyenne pondérée ou autre logique
                    // Ici, nous utilisons simplement le nombre total pour le placeholder
                    $averageScore = $data['totalByBrand']; // À remplacer par le calcul réel
                    $averageScoreByBrand[$brand] = $averageScore;
                } else {
                    $averageScoreByBrand[$brand] = 0.0;
                }
            }

            return $averageScoreByBrand;
        }

        function calculateAverageScoreByBrandAndLevel($totalTrainingsByBrandAndLevel) {
            $averageScoreByBrandAndLevel = [];

            foreach ($totalTrainingsByBrandAndLevel as $brand => $data) {
                foreach ($data['totalByLevel'] as $level => $count) {
                    if ($count > 0) {
                        // Exemple de calcul : moyenne pondérée ou autre logique
                        // Ici, nous utilisons simplement le nombre total pour le placeholder
                        $averageScore = $count; // À remplacer par le calcul réel
                        $averageScoreByBrandAndLevel[$brand][$level] = $averageScore;
                    } else {
                        $averageScoreByBrandAndLevel[$brand][$level] = 0.0;
                    }
                }
            }

            return $averageScoreByBrandAndLevel;
        }

        $averageScoreByBrand = calculateAverageScoreByBrand($totalTrainingsByBrandAndLevel);
        $averageScoreByBrandAndLevel = calculateAverageScoreByBrandAndLevel($totalTrainingsByBrandAndLevel);

        // Préparer le document à insérer
        $document = [
            'subsidiary' => [
                'subsidiaryId' => new ObjectId($subsidiaryId),
                'name' => $subsidiaryName,
                'averageScore' => $averageScore,
                'managers' => [
                    [
                        'managerId' => new ObjectId($managerId),
                        'firstName' => $managerFirstName,
                        'lastName' => $managerLastName,
                        'averageScore' => $averageScore, // Placeholder, ajustez si nécessaire
                        'technicians' => [
                            [
                                'technicianId' => new ObjectId($technicianId),
                                'firstName' => $techFirstName,
                                'lastName' => $techLastName,
                                'averageScore' => $averageScore,
                                'brands' => $brands,
                                'stats' => [
                                    'totalManagers' => 1, // Ajustez si nécessaire
                                    'totalTechnicians' => 1, // À ajuster selon le contexte
                                    'totalDistinctBrands' => $totalDistinctBrands,
                                    'totalTrainings' => $totalTrainings,
                                    'totalTrainingsByLevel' => $totalTrainingsByLevel,
                                    'totalTrainingsByBrandAndLevel' => $totalTrainingsByBrandAndLevel,
                                    'averageScoreBySubsidiary' => $averageScore, // Placeholder, ajustez
                                    'averageScoreByManager' => $averageScore, // Placeholder, ajustez
                                    'averageScoreByBrand' => $averageScoreByBrand,
                                    'averageScoreByBrandAndLevel' => $averageScoreByBrandAndLevel
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        // Ajouter le document au tableau des documents à insérer
        $documents[] = $document;
    }

// Insertion ou Mise à Jour des Documents dans MongoDB
if (!empty($documents)) {
    try {
        // Utiliser insertMany pour insérer tous les documents
        // Si vous souhaitez mettre à jour les documents existants, utilisez un loop avec updateOne et upsert
        foreach ($documents as $doc) {
            $subsidiaryId = $doc['subsidiary']['subsidiaryId'];
            $managerId = $doc['subsidiary']['managers'][0]['managerId'];
            $technicianId = $doc['subsidiary']['managers'][0]['technicians'][0]['technicianId'];

            // Définir le filtre pour l'upsert
            $filter = [
                'subsidiary.subsidiaryId' => $subsidiaryId,
                'subsidiary.managers.managerId' => $managerId,
                'subsidiary.managers.technicians.technicianId' => $technicianId
            ];

            // Définir l'update avec les nouvelles données
            $update = ['$set' => $doc];

            // Effectuer l'upsert
            $academy->$collectionName->updateOne($filter, $update, ['upsert' => true]);
        }

        echo "Les documents ont été insérés ou mis à jour avec succès dans la collection '$collectionName'.\n";
    } catch (MongoDB\Driver\Exception\Exception $e) {
        echo "Erreur lors de l'insertion ou de la mise à jour des documents : " . $e->getMessage() . "\n";
    }
} else {
    echo "Aucun document à insérer ou mettre à jour.\n";
}
}
?>
