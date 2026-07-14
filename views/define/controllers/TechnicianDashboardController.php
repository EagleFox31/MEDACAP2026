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
    private $yearScope = 'pluri';
    public $viewPath;
    
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
        $this->viewPath = __DIR__ . "/../views/technician/dashboard.php";
    }
    
    /**
     * Rendu du dashboard technicien
     *
     * @param array $filters
     * @param array $supervisorOptions supervisorView, supervisorPage, technicianChoices, agencyOptions, selectedAgency, selectedTechnicianId
     */
    public function renderDashboard($filters, array $supervisorOptions = []) {
        $supervisorView = !empty($supervisorOptions['supervisorView']);
        $supervisorPage = $supervisorOptions['supervisorPage'] ?? './collaboratorDashboard';
        $technicianChoices = $supervisorOptions['technicianChoices'] ?? [];
        $agencyOptions = $supervisorOptions['agencyOptions'] ?? [];
        $selectedAgency = $supervisorOptions['selectedAgency'] ?? ($filters['agency'] ?? 'all');
        $selectedTechnicianId = $supervisorOptions['selectedTechnicianId'] ?? $this->technicianId;

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
        $this->yearScope = isset($filters['scope']) ? trim((string) $filters['scope']) : 'pluri';
        if ($this->yearScope === '' || $this->yearScope === 'all') {
            $this->yearScope = 'pluri';
        }
        $levels = $this->getLevelsToInclude($levelFilter);
        $brandsToShow = $this->getBrandsToInclude($filterBrand, $levels, $this->technicianDoc);
        
        // Préparation des données pour les graphiques
        // Lecture depuis technicianBrandScores (pré-calculés, même source que le dashboard manager)
        $brandScores = $this->prepareBrandScoresFromPrecomputed($brandsToShow, $levelFilter, $filterBrand);
        
        // Statistiques de formation
        $brandFormationsMap = $this->getBrandFormationsMap($brandsToShow, $levelFilter);
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
        
        $GLOBALS['currentPage'] = $supervisorView ? 'collaboratorDashboard' : 'dashboard';

        $selectedTechnicianName = '';
        if ($supervisorView && isset($technicianChoices[$selectedTechnicianId])) {
            $selectedTechnicianName = $technicianChoices[$selectedTechnicianId];
        } elseif ($supervisorView && $this->technicianDoc) {
            $selectedTechnicianName = trim(
                ($this->technicianDoc['firstName'] ?? '') . ' ' . ($this->technicianDoc['lastName'] ?? '')
            );
        }

        if (!empty($supervisorOptions['contentOnly'])) {
            require_once __DIR__ . '/../components/helpers/helpers.php';
            require_once __DIR__ . '/../components/widgets/card-level.php';
            require_once __DIR__ . '/../components/widgets/title-main.php';
            require_once __DIR__ . '/../components/widgets/title-section.php';
            require_once __DIR__ . '/../components/widgets/histogram-row.php';
            include __DIR__ . '/../views/technician/dashboard_content.php';
            return;
        }
        
        // Inclure la vue spécifiée
        include $this->viewPath;
    }

    /**
     * Fragment HTML du dashboard (sans header / footer / filtres).
     */
    public function renderDashboardContent($filters, array $supervisorOptions = []) {
        $supervisorOptions['contentOnly'] = true;
        $this->renderDashboard($filters, $supervisorOptions);
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
     * Lit les scores depuis technicianBrandScores (pré-calculés par cronjob).
     * Même source de données que le dashboard manager.
     */
    private function prepareBrandScoresFromPrecomputed($brandsToShow, $levelFilter, $filterBrand) {
        try {
            $scoreDoc = $this->academy->technicianBrandScores->findOne(
                ['userId' => new ObjectId($this->technicianId)]
            );
        } catch (\Exception $e) {
            error_log('technicianBrandScores lookup error: ' . $e->getMessage());
            return [];
        }

        if (!$scoreDoc || !isset($scoreDoc['scores'])) return [];

        $brandAggregator = [];
        foreach ($scoreDoc['scores'] as $level => $brandData) {
            if ($levelFilter !== 'all' && $level !== $levelFilter) continue;
            foreach ($brandData as $brand => $scoreDetails) {
                $brand = (string)$brand;
                if (!empty($brandsToShow) && !in_array($brand, $brandsToShow)) continue;
                if ($filterBrand !== 'all' && $filterBrand !== $brand) continue;
                $avg = isset($scoreDetails['averageTotalWithPenalty'])
                    ? (float)$scoreDetails['averageTotalWithPenalty']
                    : (float)($scoreDetails['averageTotal'] ?? 0);
                if (!isset($brandAggregator[$brand])) {
                    $brandAggregator[$brand] = ['sum' => 0, 'count' => 0];
                }
                $brandAggregator[$brand]['sum']   += $avg;
                $brandAggregator[$brand]['count'] += 1;
            }
        }

        if (empty($brandAggregator)) return [];

        $allBrandsForMap = !empty($brandsToShow) ? $brandsToShow : array_keys($brandAggregator);
        $brandFormationsMap = $this->getBrandFormationsMap($allBrandsForMap, $levelFilter);
        $getLevel = $_GET['level'] ?? 'all';
        $brandScores = [];

        foreach ($brandAggregator as $brand => $vals) {
            $finalScore = (int)round($vals['sum'] / max(1, $vals['count']));
            if ($finalScore >= 80) {
                $labelText = [$brandFormationsMap[$brand] ?? 0, 'Modules de Formations'];
            } elseif ($finalScore > 0) {
                $labelText = ['Accès', 'Formations'];
            } else {
                $labelText = ['Accès', 'Tests'];
            }
            $brandScores[] = [
                'x'         => $brand,
                'y'         => $finalScore > 0 ? $finalScore : null,
                'fillColor' => $finalScore >= 80 ? '#198754' : ($finalScore > 0 ? '#ffc107' : '#6c757d'),
                'labelText' => $labelText,
                'url'       => './personalTraining?brand=' . urlencode($brand) . '&level=' . $getLevel,
            ];
        }
        return $brandScores;
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
     * Restreint les formations à une année (applications.year) ; pluri = pas de filtre.
     */
    private function filterTrainingsByYearScope(array $trainings) {
        if ($this->yearScope === 'pluri' || !is_numeric($this->yearScope)) {
            return $trainings;
        }

        if ($this->technicianId === 'all' || empty($trainings)) {
            return $trainings;
        }

        $year = (int) $this->yearScope;
        $technicianObjId = new ObjectId($this->technicianId);
        $trainingIds = [];
        foreach ($trainings as $training) {
            $trainingIds[] = $training['_id'];
        }

        $allowed = [];
        $appQuery = [
            'user' => $technicianObjId,
            'training' => ['$in' => $trainingIds],
            'active' => true,
            '$or' => [
                ['year' => $year],
                ['year' => (string) $year],
            ],
        ];
        foreach ($this->applicationsCollection->find($appQuery, ['projection' => ['training' => 1]]) as $app) {
            $allowed[(string) $app['training']] = true;
        }

        return array_values(array_filter($trainings, function ($training) use ($allowed) {
            return isset($allowed[(string) $training['_id']]);
        }));
    }

    /**
     * Compte les formations recommandées (requête directe sur trainings).
     */
    private function countRecommendedTrainings($brands, $levels) {
        if (empty($brands)) return [];
        $technicianObjId = new ObjectId($this->technicianId);
        $query = [
            'users'  => $technicianObjId,
            'active' => true,
            'brand'  => ['$in' => $brands],
            'level'  => ['$in' => $levels],
        ];
        return $this->filterTrainingsByYearScope($this->trainingCollection->find($query)->toArray());
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
        $trainings = $this->filterTrainingsByYearScope($trainings);
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
     * Obtient une map des formations par marque (filtrée par niveau).
     * Requête directe sur trainings (même source que selectTraining.php).
     */
    private function getBrandFormationsMap($brands, $levelFilter = 'all') {
        if (empty($brands)) return [];
        $technicianObjId = new ObjectId($this->technicianId);
        $query = [
            'users'  => $technicianObjId,
            'active' => true,
            'brand'  => ['$in' => $brands],
        ];
        if ($levelFilter !== 'all') {
            $query['level'] = $levelFilter;
        }
        $counts = [];
        foreach ($this->filterTrainingsByYearScope($this->trainingCollection->find($query)->toArray()) as $t) {
            $brand = (string)($t['brand'] ?? '');
            if ($brand !== '') {
                if (!isset($counts[$brand])) $counts[$brand] = 0;
                $counts[$brand]++;
            }
        }
        return $counts;
    }
    
    /**
     * Calcule les heures de formation par marque (requête directe sur trainings).
     * On convertit les jours en heures (1 jour = 8h).
     */
    private function getTrainingHoursByBrand($brands, $levels) {
        if (empty($brands)) return 0;
        $technicianObjId = new ObjectId($this->technicianId);
        $query = [
            'users'  => $technicianObjId,
            'active' => true,
            'brand'  => ['$in' => $brands],
            'level'  => ['$in' => $levels],
        ];
        $totalHours = 0;
        foreach ($this->filterTrainingsByYearScope($this->trainingCollection->find($query)->toArray()) as $t) {
            // On privilégie 'duree_jours', sinon 'duration'
            $val = (float)($t['duree_jours'] ?? $t['duration'] ?? 0);
            $type = $t['type'] ?? 'p'; // 'p' pour présentiel par défaut
            
            // Si la valeur est 0, on applique les règles par défaut (en jours)
            if ($val <= 0) {
                // p = présentiel (5j), d = distanciel/e-learning (2j)
                $val = (strtolower($type) === 'd') ? 2 : 5;
            }
            
            // Si la valeur est typiquement un nombre de jours (ex: < 15), on multiplie par 8h
            if ($val < 15) {
                $totalHours += ($val * 8);
            } else {
                $totalHours += $val;
            }
        }
        return round($totalHours);
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