<?php
require_once "BaseController.php";
require_once "../../vendor/autoload.php";
use MongoDB\BSON\ObjectId;

/**
 * Contrôleur spécifique pour le dashboard des techniciens
 */
class TechnicianDashboardController extends BaseController {
    private $technicianId;
    private $technicianDoc;
    private $userCollection;
    private $trainingCollection;
    private $resultsCollection;
    private $allocationsCollection;
    private $applicationsCollection;
    
    public function __construct($academy, $technicianId = null) {
        parent::__construct($academy);
        
        // Initialisation des collections MongoDB
        $this->userCollection = $academy->users;
        $this->trainingCollection = $academy->trainings;
        $this->resultsCollection = $academy->results;
        $this->allocationsCollection = $academy->allocations;
        $this->applicationsCollection = $academy->applications;
        
        // Récupération de l'ID du technicien
        // Force le cast en string pour éviter les erreurs "Illegal offset type" si l'ID est un ObjectId
        $this->technicianId = (string)($technicianId ?? $_GET['technicianId'] ?? $_SESSION["id"]);
    }
    
    /**
     * Rendu du dashboard technicien
     */
    public function renderDashboard($filters) {
        // Récupération des informations du technicien
        $this->loadTechnicianData();
        
        if (!$this->technicianDoc) {
            echo "Technicien introuvable.";
            return;
        }
        
        // Récupération des marques et niveaux
        $allBrands = $this->getTechnicianBrands();
        $techLevel = $this->technicianDoc['level'] ?? 'Junior';
        
        // Filtrage selon les paramètres
        $filterBrand = $filters['brand'] ?? 'all';
        $levelFilter = $filters['level'] ?? 'all';
        $levels = $this->getLevelsToInclude($levelFilter);
        $brandsToShow = $this->getBrandsToInclude($filterBrand, $levels, $this->technicianDoc);
        
        // Chargement des configurations
        $config = require __DIR__ . "/../../configGF.php";
        require_once __DIR__ . "/../scoreFunctions.php";
        
        // Récupération des scores
        $technicianManagerMap = [$this->technicianId => $this->technicianDoc['manager'] ?? null];
        $allSpecialities = $this->getSpecialitiesFromConfig();
        
        $scoreCalc = new ScoreCalculator($this->academy);
        $optionalParam = null; // Paramètre optionnel pour getAllScoresForTechnicians
        $allScores = $scoreCalc->getAllScoresForTechnicians(
            $this->academy,
            $technicianManagerMap,
            $levels,
            $allSpecialities,
            $optionalParam
        );
        
        // Préparation des données pour les graphiques
        $brandScores = $this->prepareBrandScores($allScores, $brandsToShow, $config);
        
        // Statistiques de formation
        $brandFormationsMap = $this->getBrandFormationsMap($brandsToShow);
        $numRecommended = $this->countRecommendedTrainings($brandsToShow, $levels);
        $numCompleted = $this->countCompletedTrainings($brandsToShow, $levels);
        $brandHoursMap = $this->getTrainingHoursByBrand($brandsToShow, $levels);
        $totalDuration = $this->calculateTotalDuration($levels);
        
        // Variables de traduction
        $recommaded_training = $this->translations['recommaded_training'] ?? 'Formations Recommandées';
        $apply_training = $this->translations['apply_training'] ?? 'Formations Réalisées';
        $training_duration = $this->translations['training_duration'] ?? 'Durée des Formations';
        
        // Autres variables pour la vue
        $tableau = "Tableau de Bord";
        $brandLogos = $GLOBALS['brandLogos'] ?? [];
        
        // Définir la page courante avant d'inclure la vue pour éviter les boucles infinies dans le header
        $GLOBALS['currentPage'] = 'dashboard';
        
        // Inclure la vue technicien - les redirections des directeurs sont maintenant gérées dans dashboard.php
        include __DIR__ . "/../views/technician/dashboard.php";
    }
    
    /**
     * Charge les données du technicien
     */
    private function loadTechnicianData() {
        try {
            // Handle special case for "all" technicians
            if ($this->technicianId === 'all') {
                // Use the first available technician for dashboard display
                // or create a default document with necessary structure
                $this->technicianDoc = $this->userCollection->findOne([
                    'profile' => 'Technicien',
                    'active' => true
                ]);
                
                // If still no technician found, create a minimal structure
                if (!$this->technicianDoc) {
                    $this->technicianDoc = [
                        'level' => 'Junior',
                        'brandJunior' => [],
                        'brandSenior' => [],
                        'brandExpert' => []
                    ];
                }
            } else {
                // Normal case: convert ID to ObjectId and find the technician
                $techObjId = new ObjectId($this->technicianId);
                $this->technicianDoc = $this->userCollection->findOne([
                    '_id' => $techObjId,
                    'profile' => 'Technicien'
                ]);
            }
        } catch (\Exception $e) {
            error_log("Erreur lors du chargement des données du technicien: " . $e->getMessage());
            echo "Identifiant technicien invalide.";
            exit();
        }
    }
    
    /**
     * Récupère toutes les marques associées au technicien
     */
    private function getTechnicianBrands() {
        $allBrands = [];
        
        // Récupérer les marques d'un technicien
        if (isset($this->technicianDoc['brandJunior'])) {
            foreach ($this->technicianDoc['brandJunior'] as $b) {
                $bTrimmed = trim((string)$b);
                if ($bTrimmed !== '') {
                    $allBrands[$bTrimmed] = true;
                }
            }
        }
        
        if (isset($this->technicianDoc['brandSenior'])) {
            foreach ($this->technicianDoc['brandSenior'] as $b) {
                $bTrimmed = trim((string)$b);
                if ($bTrimmed !== '') {
                    $allBrands[$bTrimmed] = true;
                }
            }
        }
        
        if (isset($this->technicianDoc['brandExpert'])) {
            foreach ($this->technicianDoc['brandExpert'] as $b) {
                $bTrimmed = trim((string)$b);
                if ($bTrimmed !== '') {
                    $allBrands[$bTrimmed] = true;
                }
            }
        }
        
        $allBrands = array_keys($allBrands);
        sort($allBrands);
        
        return $allBrands;
    }
    
    /**
     * Récupère les niveaux à inclure selon le filtre
     */
    private function getLevelsToInclude($levelFilter) {
        // Définition des niveaux disponibles
        $allLevels = ['Junior', 'Senior', 'Expert'];
        
        // Si le filtre est 'all', on renvoie tous les niveaux
        if ($levelFilter === 'all') {
            return $allLevels;
        }
        
        // Sinon on renvoie uniquement le niveau spécifié s'il est valide
        if (in_array($levelFilter, $allLevels)) {
            return [$levelFilter];
        }
        
        // Par défaut, on renvoie tous les niveaux
        return $allLevels;
    }
    
    /**
     * Récupère les marques à inclure selon les filtres
     */
    private function getBrandsToInclude($brands, $levels, $docs) {
        // Suppose qu'on a brandJunior, brandSenior, brandExpert
        // On combine en fonction des niveaux
        $brandFieldJunior = $docs['brandJunior'] ?? [];
        $brandFieldSenior = $docs['brandSenior'] ?? [];
        $brandFieldExpert = $docs['brandExpert'] ?? [];
        
        // On reconstruit la liste de marques
        if ($brands == 'all') {
            $allBrandsInDoc = [];
            if (in_array('Junior', $levels)) {
                foreach ($brandFieldJunior as $b) {
                    $bTrimmed = trim((string)$b);
                    if ($bTrimmed !== '') {
                        $allBrandsInDoc[] = $bTrimmed;
                    }
                }
            }
            if (in_array('Senior', $levels)) {
                foreach ($brandFieldSenior as $b) {
                    $bTrimmed = trim((string)$b);
                    if ($bTrimmed !== '') {
                        $allBrandsInDoc[] = $bTrimmed;
                    }
                }
            }
            if (in_array('Expert', $levels)) {
                foreach ($brandFieldExpert as $b) {
                    $bTrimmed = trim((string)$b);
                    if ($bTrimmed !== '') {
                        $allBrandsInDoc[] = $bTrimmed;
                    }
                }
            }
            return array_unique($allBrandsInDoc);
        } else {
            return [$brands];
        }
    }
    
    /**
     * Prépare les données de scores par marque pour le graphique
     */
    private function prepareBrandScores($allScores, $brandsToShow, $config) {
        $brandScores = [];
        
        foreach ($brandsToShow as $oneBrand) {
            $sumAll = 0.0;
            $countAll = 0;
            
            foreach ($this->getLevelsToInclude('all') as $lvl) {
                // Récup les GF supportés
                $supportedGroups = $this->getSupportedGroupsForBrand($oneBrand, $lvl, $config);
                
                // Parcourir chaque groupe => regarder $allScores[$technicianId][$lvl][$group]
                foreach ($supportedGroups as $grp) {
                    $techIdStr = (string)$this->technicianId;
                    if (isset($allScores[$techIdStr][$lvl][$grp])) {
                        $fact = $allScores[$techIdStr][$lvl][$grp]['Factuel'] ?? null;
                        $decl = $allScores[$techIdStr][$lvl][$grp]['Declaratif'] ?? null;
                        
                        if ($fact !== null && $decl !== null) {
                            // On fait la moyenne fact+decl
                            $grpScore = ($fact + $decl) / 2;
                            $sumAll += $grpScore;
                            $countAll++;
                        } elseif ($fact !== null) {
                            $sumAll += $fact;
                            $countAll++;
                        } elseif ($decl !== null) {
                            $sumAll += $decl;
                            $countAll++;
                        }
                    }
                }
            }
            
            if ($countAll > 0) {
                $finalScore = round($sumAll / $countAll);
            } else {
                $finalScore = null;
            }
            
            // Définir le texte basé sur la couleur avec nombre de modules
            $brandFormationsMap = $this->getBrandFormationsMap($brandsToShow);
            if ($finalScore !== null && $finalScore >= 80) {
                $modulesCount = $brandFormationsMap[$oneBrand] ?? 0;
                $labelText = [$modulesCount, 'Modules de Formations'];
            } elseif ($finalScore !== null) {
                $labelText = ['Accès', 'Formations'];
            } else {
                $labelText = ['Accès', 'Tests'];
            }
            
            $getLevel = $_GET['level'] ?? 'all';
            
            $brandScores[] = [
                'x' => $oneBrand,
                'y' => $finalScore,
                'fillColor' => ($finalScore !== null && $finalScore >= 80) ? '#198754' : (($finalScore !== null) ? '#ffc107' : '#6c757d'),
                'labelText' => $labelText,
                'url' => './personalTraining?brand='.$oneBrand.'&level='.$getLevel
            ];
        }
        
        return $brandScores;
    }
    
    /**
     * Récupère les spécialités depuis la configuration
     */
    private function getSpecialitiesFromConfig() {
        // Chargement des configurations
        $config = require __DIR__ . "/../../configGF.php";
        
        // Récupération des spécialités depuis la configuration
        $allSpecialities = $config['specialities'] ?? [];
        
        // Si aucune spécialité n'est configurée, utiliser un tableau par défaut
        if (empty($allSpecialities)) {
            $allSpecialities = [
                'Mécanique',
                'Carrosserie',
                'Électrique',
                'Diagnostic'
            ];
        }
        
        return $allSpecialities;
    }
    
    /**
     * Récupère les groupes fonctionnels supportés pour une marque et un niveau
     */
    private function getSupportedGroupsForBrand($brand, $level, $config) {
        // On part de functionalGroupsByLevel[$level], puis on retire 
        // tout ce qui est "nonSupportedGroupsByBrand[$brand]"  
        $all = $config['functionalGroupsByLevel'][$level] ?? [];
        $nonSupp = $config['nonSupportedGroupsByBrand'][$brand] ?? [];
        return array_values(array_diff($all, $nonSupp));
    }
    
    /**
     * Compte les formations recommandées
     */
    private function countRecommendedTrainings($brands, $levels) {
        $technicianObjId = new ObjectId($this->technicianId);
        $recommendedTrainings = [];
        
        $query = [
            'user' => $technicianObjId,
            'active' => true,
        ];
        
        $results = $this->applicationsCollection->find($query)->toArray();
        
        foreach ($results as $result) {
            $filter = [
                '_id' => new ObjectId($result['training']),
                'users' => $technicianObjId,
                'brand' => ['$in' => $brands],
                'level' => ['$in' => $levels],
                'active' => true,
            ];
            
            $trainingData = $this->trainingCollection->findOne($filter);
            
            if (isset($trainingData)) {
                $recommendedTrainings[] = $trainingData;
            }
        }
        
        return $recommendedTrainings;
    }
    
    /**
     * Compte les formations complétées
     */
    private function countCompletedTrainings($brands, $levels) {
        $technicianObjId = new ObjectId($this->technicianId);
        $completedTrainings = [];
        
        // Find all trainings that match the criteria
        $filter = [
            'users' => $technicianObjId,
            'brand' => ['$in' => $brands],
            'level' => ['$in' => $levels],
            'active' => true,
        ];
        
        $trainings = $this->trainingCollection->find($filter)->toArray();
        $trainingIds = [];
        foreach ($trainings as $t) {
            $trainingIds[] = new ObjectId($t['_id']);
        }
        
        if (empty($trainingIds)) {
            return [];
        }
        
        $query = [
            'user' => $technicianObjId,
            'training' => ['$in' => $trainingIds],
            'type' => 'Training',
            'active' => true
        ];
        
        return $this->allocationsCollection->find($query)->toArray();
    }
    
    /**
     * Obtient une map des formations par marque
     */
    private function getBrandFormationsMap($brands) {
        $technicianObjId = new ObjectId($this->technicianId);
        
        $pipeline = [
            [
                '$match' => [
                    'user' => $technicianObjId,
                    'active' => true
                ],
            ],
            [
                '$lookup' => [
                    'from' => 'trainings',
                    'localField' => 'training',
                    'foreignField' => '_id',
                    'as' => 'trainingDetails',
                ],
            ],
            [
                '$unwind' => [
                    'path' => '$trainingDetails',
                    'preserveNullAndEmptyArrays' => true,
                ]
            ],
            [
                '$match' => [
                    'trainingDetails.brand' => ['$in' => $brands],
                    'trainingDetails.active' => true,
                ],
            ],
            [
                '$group' => [
                    '_id' => '$trainingDetails.brand',
                    'count' => ['$sum' => 1],
                ],
            ],
            [
                '$project' => [
                    'brand' => '$_id',
                    'count' => 1,
                    '_id' => 0,
                ],
            ],
        ];
        
        $results = $this->applicationsCollection->aggregate($pipeline)->toArray();
        
        $brandFormationsMap = [];
        foreach ($results as $doc) {
            $brandFormationsMap[(string)$doc->brand] = (int)$doc->count;
        }
        
        return $brandFormationsMap;
    }
    
    /**
     * Calcule les heures de formation par marque
     */
    private function getTrainingHoursByBrand($brands, $levels) {
        $technicianObjId = new ObjectId($this->technicianId);
        
        $query = [
            'user' => $technicianObjId,
            'active' => true,
        ];
        
        $brandHoursMap = 0; // Initialiser avec 0 pour faire une somme
        $results = $this->applicationsCollection->find($query)->toArray();
        
        foreach ($results as $result) {
            $filter = [
                '_id' => new ObjectId($result['training']),
                'users' => $technicianObjId,
                'brand' => ['$in' => $brands],
                'level' => ['$in' => $levels],
                'active' => true,
            ];
            
            $trainingData = $this->trainingCollection->findOne($filter);
            
            if (isset($trainingData) && isset($trainingData['duration'])) {
                $brandHoursMap += (int)$trainingData['duration'];
            }
        }
        
        return $brandHoursMap;
    }
    
    /**
     * Calcule la durée totale des formations
     */
    private function calculateTotalDuration($levels) {
        $technicianObjId = new ObjectId($this->technicianId);
        
        $totalDuration = [
            'jours' => 0,
            'heures' => 0
        ];
        
        $cursorTrainings = $this->trainingCollection->find([
            'active' => true,
            'users' => $technicianObjId,
            'level' => ['$in' => $levels],
            'brand' => ['$ne' => ''],
        ]);
        
        $daysSum = 0;
        foreach ($cursorTrainings as $trainingDoc) {
            if (isset($trainingDoc['duree_jours']) && $trainingDoc['duree_jours'] > 0) {
                $daysSum += (float)$trainingDoc['duree_jours'];
            }
        }
        
        $fullDays = floor($daysSum);
        $decimalPart = $daysSum - $fullDays;
        $hours = $decimalPart * 8;
        
        $totalDuration['jours'] = (int)$fullDays;
        $totalDuration['heures'] = (int)$hours;
        
        return $totalDuration;
    }
}
?>