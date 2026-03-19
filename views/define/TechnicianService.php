<?php
require_once "../../vendor/autoload.php";
require_once __DIR__ . "/ScoreFunctions.php"; // Assurez-vous que ce chemin est correct

use MongoDB\Client;
use MongoDB\BSON\ObjectId;

try {
    $mongo   = new Client("mongodb://localhost:27017");
    $academy = $mongo->selectDatabase('academy');
} catch (MongoDB\Exception\Exception $e) {
    echo "Erreur de connexion : " . htmlspecialchars($e->getMessage());
    exit();
}

class TechnicianService {
    private $academy;
    private $usersColl;
    private $trainingsColl;
    private $scoreCalc;

    public function __construct($mongoUri = "mongodb://localhost:27017", $dbName = "academy") {
        try {
            $mongo = new Client($mongoUri);
            $this->academy = $mongo->selectDatabase($dbName);
            $this->usersColl = $this->academy->users;
            $this->trainingsColl = $this->academy->trainings;
            $this->scoreCalc = new ScoreCalculator($this->academy);
        } catch (MongoDB\Exception\Exception $e) {
            throw new Exception("Erreur de connexion à MongoDB : " . htmlspecialchars($e->getMessage()));
        }
    }

    /**
     * Récupère tous les techniciens actifs sous un manager spécifique.
     *
     * @param string $managerId L'ID du manager.
     * @return array Liste des techniciens.
     * @throws Exception Si l'ID du manager est invalide.
     */
    public function getTechniciansByManager($managerId) {
        try {
            $managerObjId = new ObjectId($managerId);
        } catch (\Exception $e) {
            throw new Exception("Identifiant manager invalide.");
        }

        $technicians = $this->usersColl->find([
            'profile' => 'Technicien',
            'active'  => true,
            'manager' => $managerObjId
        ])->toArray();

        return $technicians;
    }

    /**
     * Récupère les plans de formation pour un technicien spécifique.
     *
     * @param string $technicianId L'ID du technicien.
     * @param array $levels Niveaux à inclure (e.g., ['Junior', 'Senior', 'Expert']).
     * @return array Les plans de formation du technicien.
     * @throws Exception Si l'ID du technicien est invalide.
     */
    public function getTrainingPlans($technicianId, $levels) {
        try {
            $techObjId = new ObjectId($technicianId);
        } catch (\Exception $e) {
            throw new Exception("Identifiant technicien invalide.");
        }

        // Requêtes de formation recommandées
        $numRecommended = $this->countRecommendedTrainings($techObjId, $levels);
        $numCompleted = $this->countCompletedTrainings($techObjId, $levels);
        $trainingsByBrand = $this->getTrainingsByBrand($techObjId, $levels);
        $brandFormationsMap = $this->mapTrainingsByBrand($trainingsByBrand);
        $brandHoursMap = $this->getTrainingHoursByBrand($techObjId, $levels);

        // Calcul de la durée totale
        $totalDuration = $this->calculateTotalDuration($techObjId, $levels);

        return [
            'numRecommended'    => $numRecommended,
            'numCompleted'      => $numCompleted,
            'trainingsByBrand'  => $trainingsByBrand,
            'brandFormationsMap'=> $brandFormationsMap,
            'brandHoursMap'     => $brandHoursMap,
            'totalDuration'     => $totalDuration
        ];
    }

    /**
     * Récupère les scores (factuels et déclaratifs) pour plusieurs techniciens.
     *@param MongoDB\Database $academy
     * @param array $technicianIds Liste des IDs des techniciens.
     * @param array $levels Niveaux à inclure.
     * @param array $specialities Liste des spécialités.
     * @return array Les scores pour chaque technicien.
     */
    public function getScoresForTechnicians($technicianIds, $levels, $specialities) {
        // Construire une map technicien => manager (ici, le manager n'est pas utilisé, mais nécessaire pour la méthode)
        // Puisque nous récupérons les scores pour plusieurs techniciens indépendamment, nous pouvons passer une map technicien => null
        $technicianManagerMap = [];
        foreach ($technicianIds as $techId) {
            $technicianManagerMap[$techId] = null; // Manager non pertinent ici
        }

        // Obtenir tous les scores
        $debug = [];
        $allScores = $this->scoreCalc->getAllScoresForTechnicians($academy, $technicianManagerMap, $levels, $specialities, $debug);

        // Retourner les scores sans la structure manager
        return $allScores;
    }

    /**
     * Récupère les scores (factuels et déclaratifs) pour un technicien spécifique.
     *
     * @param string $technicianId L'ID du technicien.
     * @param array $levels Niveaux à inclure.
     * @param array $specialities Liste des spécialités.
     * @return array Les scores du technicien.
     */
    public function getScoresForTechnician($technicianId, $levels, $specialities) {
        $scores = $this->getScoresForTechnicians([$technicianId], $levels, $specialities);

        // Retourner uniquement les scores du technicien demandé
        return $scores[$technicianId] ?? [];
    }

    /**
     * Récupère les techniciens et leurs plans de formation et scores sous un manager.
     *
     * @param string $managerId L'ID du manager.
     * @param array $levels Niveaux à inclure.
     * @return array Les données des techniciens.
     * @throws Exception Si l'ID du manager est invalide.
     */
    public function getTechniciansDataByManager($managerId, $levels) {
        $technicians = $this->getTechniciansByManager($managerId);
        $specialities = $this->scoreCalc->getAllSpecialities();

        // Extraire les IDs des techniciens
        $technicianIds = array_map(function($tech) {
            return (string)$tech['_id'];
        }, $technicians);

        // Obtenir tous les scores pour ces techniciens
        $debug = [];
        $allScores = $this->getScoresForTechnicians($technicianIds, $levels, $specialities);

        $results = [];

        foreach ($technicians as $tech) {
            $tId   = (string)$tech['_id'];
            $tName = ($tech['firstName'] ?? '') . ' ' . ($tech['lastName'] ?? '');

            // Récupérer les plans de formation
            $trainingPlans = $this->getTrainingPlans($tId, $levels);

            // Récupérer les scores
            $scores = $allScores[$tId] ?? [];

            // Récupérer les marques par niveau
            $brands = $this->getBrandsByTechnician($tech, $levels);

            $results[] = [
                'technicianId'      => $tId,
                'technicianName'    => $tName,
                'trainingPlans'     => $trainingPlans,
                'scores'            => $scores,
                'brands'            => $brands
            ];
        }

        return $results;
    }

    /**
     * Récupère les marques d'un technicien en fonction des niveaux.
     *
     * @param array $tech Document technicien.
     * @param array $levels Niveaux à inclure.
     * @return array Liste des marques.
     */
    private function getBrandsByTechnician($tech, $levels) {
        // Supposons que les marques sont stockées dans les champs brandJunior, brandSenior, brandExpert
        $allBrandsInDoc = [];

        if (in_array('Junior', $levels) && isset($tech['brandJunior'])) {
            foreach ($tech['brandJunior'] as $b) {
                $bTrimmed = trim((string)$b);
                if ($bTrimmed !== '') {
                    $allBrandsInDoc[] = $bTrimmed;
                }
            }
        }

        if (in_array('Senior', $levels) && isset($tech['brandSenior'])) {
            foreach ($tech['brandSenior'] as $b) {
                $bTrimmed = trim((string)$b);
                if ($bTrimmed !== '') {
                    $allBrandsInDoc[] = $bTrimmed;
                }
            }
        }

        if (in_array('Expert', $levels) && isset($tech['brandExpert'])) {
            foreach ($tech['brandExpert'] as $b) {
                $bTrimmed = trim((string)$b);
                if ($bTrimmed !== '') {
                    $allBrandsInDoc[] = $bTrimmed;
                }
            }
        }

        return array_unique($allBrandsInDoc);
    }

    /**
     * Fonction privée pour compter les formations recommandées.
     *
     * @param ObjectId $technicianObjId
     * @param array $levels
     * @return int
     */
    private function countRecommendedTrainings($technicianObjId, array $levels) {
        $query = [
            'active'   => true,
            'level'    => ['$in' => $levels],
            'users'    => $technicianObjId,
            'brand'    => ['$ne' => '']
        ];
        return $this->trainingsColl->countDocuments($query);
    }

    /**
     * Fonction privée pour compter les formations complétées.
     *
     * @param ObjectId $technicianObjId
     * @param array $levels
     * @return int
     */
    private function countCompletedTrainings($technicianObjId, array $levels) {
        $query = [
            'active' => true,
            'level'  => ['$in' => $levels],
            'endDate' => ['$exists' => true, '$ne' => null],
            'users'  => $technicianObjId,
            'brand'  => ['$ne' => '']
        ];
        return $this->trainingsColl->countDocuments($query);
    }

    /**
     * Fonction privée pour récupérer les formations par marque.
     *
     * @param ObjectId $technicianObjId
     * @param array $levels
     * @return array
     */
    private function getTrainingsByBrand($technicianObjId, array $levels) {
        $pipeline = [
            [
                '$match' => [
                    'active' => true,
                    'level'  => ['$in' => $levels],
                    'users'  => $technicianObjId,
                    'brand'  => ['$ne' => '']
                ]
            ],
            [
                '$group' => [
                    '_id'   => '$brand',
                    'count' => ['$sum' => 1]
                ]
            ],
        ];

        $results = $this->trainingsColl->aggregate($pipeline);
        $trainingsByBrand = [];
        foreach ($results as $doc) {
            $trainingsByBrand[] = [
                'brand' => (string)$doc->_id,
                'count' => (int)$doc->count
            ];
        }
        return $trainingsByBrand;
    }

    /**
     * Fonction privée pour mapper les formations par marque.
     *
     * @param array $trainingsByBrand
     * @return array
     */
    private function mapTrainingsByBrand($trainingsByBrand) {
        $map = [];
        foreach ($trainingsByBrand as $row) {
            $map[$row['brand']] = $row['count'];
        }
        return $map;
    }

    /**
     * Fonction privée pour récupérer les heures de formation par marque.
     *
     * @param ObjectId $technicianObjId
     * @param array $levels
     * @return array
     */
    private function getTrainingHoursByBrand($technicianObjId, array $levels) {
        $pipeline = [
            [
                '$match' => [
                    'active' => true,
                    'level'  => ['$in' => $levels],
                    'users'  => $technicianObjId,
                    'brand'  => ['$ne' => ''],
                    'duree_jours' => ['$exists' => true, '$ne' => null]
                ]
            ],
            [
                '$group' => [
                    '_id' => '$brand',
                    'totalDureeJours' => ['$sum' => '$duree_jours']
                ]
            ]
        ];

        $results = $this->trainingsColl->aggregate($pipeline);
        $brandHoursMap = [];
        foreach ($results as $doc) {
            $brandHoursMap[(string)$doc->_id] = (int)($doc->totalDureeJours * 8); // Conversion en heures
        }
        return $brandHoursMap;
    }

    /**
     * Fonction privée pour calculer la durée totale de formation.
     *
     * @param ObjectId $technicianObjId
     * @param array $levels
     * @return array ['jours' => int, 'heures' => int]
     */
    private function calculateTotalDuration($technicianObjId, array $levels) {
        $cursorTrainings = $this->trainingsColl->find([
            'active' => true,
            'users'  => $technicianObjId,
            'level'  => ['$in' => $levels],
            'brand'  => ['$ne' => '']
        ]);

        $daysSum = 0;
        foreach ($cursorTrainings as $trainingDoc) {
            if (isset($trainingDoc['duree_jours']) && $trainingDoc['duree_jours'] > 0) {
                $daysSum += (float)$trainingDoc['duree_jours'];
            }
        }

        $fullDays = floor($daysSum);
        $decimalPart = $daysSum - $fullDays;
        $hours = round($decimalPart * 8);

        return [
            'jours'  => (int) $fullDays,
            'heures' => (int) $hours
        ];
    }

    /**
     * Récupère les données complètes (formation et scores) pour tous les techniciens sous un manager.
     *
     * @param string $managerId L'ID du manager.
     * @param array $levels Niveaux à inclure.
     * @return array Les données des techniciens.
     * @throws Exception Si l'ID du manager est invalide.
     */
    public function getTechniciansData($managerId, $levels) {
        $technicians = $this->getTechniciansByManager($managerId);
        $specialities = $this->scoreCalc->getAllSpecialities();

        // Extraire les IDs des techniciens
        $technicianIds = array_map(function($tech) {
            return (string)$tech['_id'];
        }, $technicians);

        // Construire une map technicien => manager
        $technicianManagerMap = [];
        foreach ($technicianIds as $techId) {
            $technicianManagerMap[$techId] = (string)$managerId;
        }

        // Obtenir tous les scores pour ces techniciens
        $debug = [];
        $allScores = $this->scoreCalc->getAllScoresForTechnicians($academy, $technicianManagerMap, $levels, $specialities, $debug);

        $results = [];

        foreach ($technicians as $tech) {
            $tId   = (string)$tech['_id'];
            $tName = ($tech['firstName'] ?? '') . ' ' . ($tech['lastName'] ?? '');

            // Récupérer les plans de formation
            $trainingPlans = $this->getTrainingPlans($tId, $levels);

            // Récupérer les scores
            $scores = $allScores[$tId] ?? [];

            // Récupérer les marques par niveau
            $brands = $this->getBrandsByTechnician($tech, $levels);

            $results[] = [
                'technicianId'      => $tId,
                'technicianName'    => $tName,
                'trainingPlans'     => $trainingPlans,
                'scores'            => $scores,
                'brands'            => $brands
            ];
        }

        return $results;
    }
}
?>
