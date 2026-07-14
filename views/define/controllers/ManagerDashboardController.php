<?php
require_once "BaseController.php";
require_once "../../vendor/autoload.php";
use MongoDB\BSON\ObjectId;

/**
 * Contrôleur spécifique pour le dashboard des managers et directeurs
 */
class ManagerDashboardController extends BaseController {
    private $userCollection;
    private $trainingCollection;
    private $allocationCollection;
    private $applicationCollection;
    private $validationCollection;
    private $profile;
    private $subsidiary;
    private $pifService;
    
    public function __construct($academy) {
        parent::__construct($academy);
        
        // Initialisation des collections MongoDB
        $this->userCollection = $academy->users;
        $this->trainingCollection = $academy->trainings;
        $this->allocationCollection = $academy->allocations;
        $this->applicationCollection = $academy->applications;
        $this->validationCollection = $academy->validations;
        
        // Récupération du profil et de la filiale
        $this->profile = $_SESSION['profile'] ?? '';
        $this->subsidiary = $_SESSION['subsidiary'] ?? '';

        // Initialisation du service PIF (matching Director style)
        require_once __DIR__ . '/../services/PIFService.php';
        $this->pifService = new \views\define\services\PIFService();
    }
    
    /**
     * Rendu du dashboard manager
     */
    public function renderDashboard($filters) {
        // Traitement spécifique selon le profil
        if (!in_array($this->profile, [
            'Manager',
            'Super Admin',
            'Admin',
            'Directeur Général',
            'Directeur Groupe',
            'Directeur Pièce et Service',
            'Directeur des Opérations'
        ])) {
            echo "Accès refusé.";
            exit();
        }
        
        // Initialisation de la collection de données
        $dataCollection = new DataCollection();
        $fullData = $dataCollection->getFullData();

        // Mappage des logos de marques
        $brandLogos = [
            'Toyota'         => '/MEDACAP/public/assets/brands/toyota-logo.png',
            'Suzuki'         => '/MEDACAP/public/assets/brands/suzuki-logo.png',
            'Mitsubishi'     => '/MEDACAP/public/assets/brands/mitsubishi-logo.png',
            'Peugeot'        => '/MEDACAP/public/assets/brands/peugeot-logo.png',
            'Fuso'           => '/MEDACAP/public/assets/brands/fuso.png',
            'Hino'           => '/MEDACAP/public/assets/brands/hino_logo.png',
            'JCB'            => '/MEDACAP/public/assets/brands/jcb.png',
            'BT'             => '/MEDACAP/public/assets/brands/bt.png',
            'Ford'           => '/MEDACAP/public/assets/brands/ford-logo.png',
            'Mercedes-Benz'  => '/MEDACAP/public/assets/brands/mercedestruck.png',
            'King Long'      => '/MEDACAP/public/assets/brands/kl.png',
            'Lovol'          => '/MEDACAP/public/assets/brands/lovol.png',
            'Sinotruk'       => '/MEDACAP/public/assets/brands/sinotruk.png',
            'Renault'        => '/MEDACAP/public/assets/brands/renaultTrucks.png'
        ];

        // Initialisation et normalisation des filtres (Sécurité Undefined Notice)
        $filterBrand      = (string)($filters['brand']      ?? 'all');
        $filterLevel      = (string)($filters['level']      ?? 'all');
        $filterTechnician = (string)($filters['technicianId'] ?? 'all');
        
        // On accepte 'filiale' ou 'subsidiary' (Directeurs utilisent subsidiary)
        $selectedFilialeRaw = $filters['filiale'] ?? ($filters['subsidiary'] ?? (($_SESSION['profile'] === 'Directeur Groupe' || $_SESSION['profile'] === 'Super Admin') ? 'all' : $this->subsidiary));
        $selectedFiliale    = (string)$selectedFilialeRaw;
        
        // On accepte 'agency' ou 'agence'
        $selectedAgence     = (string)($filters['agency'] ?? ($filters['agence'] ?? 'all'));
        
        // Gestion du managerId (on force le cast en string car MongoDB peut renvoyer un ObjectId object)
        $managerIdRaw = $this->getManagerId($filters);
        $managerId    = (string)$managerIdRaw;

        $brandLogos = [
            'RENAULT TRUCK'   => '/MEDACAP/public/assets/brands/renaultTrucks.png', 
            'RENAULT TRUCKS'  => '/MEDACAP/public/assets/brands/renaultTrucks.png', 
            'RENAULT'         => '/MEDACAP/public/assets/brands/renaultTrucks.png',
            'HINO'            => '/MEDACAP/public/assets/brands/hino_logo.png', 
            'TOYOTA BT'       => '/MEDACAP/public/assets/brands/bt.png',
            'BT'              => '/MEDACAP/public/assets/brands/bt.png',
            'SINOTRUK'        => '/MEDACAP/public/assets/brands/sinotruk.png', 
            'JCB'             => '/MEDACAP/public/assets/brands/jcb.png', 
            'MERCEDES TRUCK'  => '/MEDACAP/public/assets/brands/mercedestruck.png',
            'MERCEDES'        => '/MEDACAP/public/assets/brands/mercedestruck.png',
            'TOYOTA FORKLIFT' => '/MEDACAP/public/assets/brands/forklift.png', 
            'FUSO'            => '/MEDACAP/public/assets/brands/fuso.png', 
            'LOVOL'           => '/MEDACAP/public/assets/brands/lovol.png',
            'KING LONG'       => '/MEDACAP/public/assets/brands/kl2.png', 
            'TOYOTA'          => '/MEDACAP/public/assets/brands/toyota-logo.png',
            'SUZUKI'          => '/MEDACAP/public/assets/brands/suzuki-logo.png', 
            'PEUGEOT'         => '/MEDACAP/public/assets/brands/peugeot-logo.png',
            'MITSUBISHI'      => '/MEDACAP/public/assets/brands/mitsubishi-logo.png',
            'FORD'            => '/MEDACAP/public/assets/brands/ford-logo.png',
        ];

        // Normalisation des logos (clés en majuscules)
        $brandLogos = array_change_key_case($brandLogos, CASE_UPPER);

        // Normalisation de la filiale (case insensitive + fuzzy)
        if ($selectedFilialeRaw !== 'all') {
            $foundKey = null;
            $hierarchyKeys = array_keys($fullData);
            
            // 1. Match exact (insensible à la casse)
            foreach ($hierarchyKeys as $k) {
                if (strcasecmp($k, $selectedFilialeRaw) === 0) {
                    $foundKey = $k;
                    break;
                }
            }
            
            // 2. Match partiel (si la clé contient la valeur ou vice versa)
            if (!$foundKey) {
                foreach ($hierarchyKeys as $k) {
                    if (stripos($k, $selectedFilialeRaw) !== false || stripos($selectedFilialeRaw, $k) !== false) {
                        $foundKey = $k;
                        break;
                    }
                }
            }
            
            if ($foundKey) {
                $selectedFiliale = $foundKey;
            }
        }

        // Récupération des techniciens
        $technicians = $this->getTechniciansData($fullData, $selectedFiliale, $selectedAgence, $managerId, $filterBrand);
        
        // Extraction des IDs par niveau depuis les techniciens trouvés dans la hiérarchie pour les KPIs
        $countUsersJu = [];
        $countUsersSe = [];
        $countUsersEx = [];
        $allTechIds = [];

        foreach ($technicians as $tech) {
            $tid = new ObjectId($tech['id']);
            $allTechIds[] = $tid;
            if ($tech['level'] === 'Junior') $countUsersJu[] = $tid;
            elseif ($tech['level'] === 'Senior') $countUsersSe[] = $tid;
            elseif ($tech['level'] === 'Expert') $countUsersEx[] = $tid;
        }
        $countUsers = $allTechIds;
        
        // Nombre de techniciens mesurés (basé sur le périmètre filtré)
        $doneTestJu = $this->getMeasuredUsers($this->allocationCollection, $countUsersJu, 'Junior');
        $doneTestSe = $this->getMeasuredUsers($this->allocationCollection, $countUsersSe, 'Senior');
        $doneTestEx = $this->getMeasuredUsers($this->allocationCollection, $countUsersEx, 'Expert');
        $doneTest = $doneTestJu + $doneTestSe + $doneTestEx;
        
        // Statistiques de formation (basé sur le périmètre filtré)
        $techWithTraining = 0;
        $uniqueTechsWithPif = [];
        foreach ($allTechIds as $userId) {
            $hasPif = $this->trainingCollection->findOne([
                'users' => $userId,
                'active' => true
            ]);
            if ($hasPif) {
                $techWithTraining++;
            }
            
            $isValidated = $this->validationCollection->findOne([
                'user' => $userId,
                'status' => 'Validé',
                'active' => true
            ]);
            if ($isValidated) {
                $uniqueTechsWithPif[(string)$userId] = true;
            }
        }
        $techWithTrainingSelected = count($uniqueTechsWithPif);
        
        // Nombre de filiales (pour Directeur Groupe)
        $numFiliales = 0;
        if (in_array($this->profile, ['Super Admin', 'Directeur Groupe'])) {
            if ($selectedFiliale === 'all') {
                $clefsFiliales = array_filter(
                    array_keys($fullData),
                    function ($cle) {
                        return $cle !== 'ALL_FILIALES' && $cle !== 'CFR';
                    }
                );
                $numFiliales = count($clefsFiliales);
            } else {
                $numFiliales = 1;
            }
        }
        
        // Comptage des formations et scores par marque
        $trainingsCountsForGraph2 = $this->getTrainingsCountsByBrand($technicians, $filterBrand);
        $validationsCountsForGraph2 = $this->getValidationsCountsByBrand($technicians, $filterBrand);
        $trainingsTechsByBrand = $this->getTrainingsNamesByBrand($technicians, $filterBrand);
        $validationsTechsByBrand = $this->getValidationsNamesByBrand($technicians, $filterBrand);
        
        // Scores par marque — pour le Manager, on calcule depuis les techniciens
        // (filterController met subsidiary='all' pour le Manager, donc fullData['all'] n'existe pas)
        if ($this->profile === 'Manager') {
            $scoresArr = $this->getScoresByBrandFromTechnicians($technicians, $filterLevel);
        } else {
            $scoresArr = $this->getScoresByBrand($fullData, $selectedFiliale, $filterLevel);
        }
        $brandScores = $this->prepareBrandScores($scoresArr, $filterBrand);
        
        // Récupération des marques
        $teamBrands = $this->getTeamBrands($technicians, $filterBrand);
        
        // Niveaux disponibles selon le niveau max dans l'équipe
        $maxLevel = $this->getMaxLevel($technicians);
        $levelsToShow = $this->getLevelsToShowByMaxLevel($maxLevel);
        
        // Variables de traduction
        $recommaded_training = $this->translations['recommaded_training'] ?? 'Formations Recommandées';
        $training_duration = $this->translations['training_duration'] ?? 'Durée des Formations';
        $technicienss = $this->translations['technicienss'] ?? 'Techniciens';
        $Subsidiary = $this->translations['Subsidiary'] ?? 'de la Filiale';
        $tech_mesure = $this->translations['tech_mesure'] ?? 'Techniciens Mesurés';
        $tech_pif = $this->translations['tech_pif'] ?? 'Techniciens avec PIF';
        $pif_filiale = $this->translations['pif_filiale'] ?? 'PIF Validés par la Filiale';
        
        // Autres variables pour la vue
        $numTrainings = array_sum($trainingsCountsForGraph2);
        $numDays = $numTrainings * 5; // Estimation de la durée
        $numTechniciens = count($technicians);
        
        // Sécurité : si la hiérarchie est vide mais qu'on a des users en base
        if ($numTechniciens === 0 && count($countUsers) > 0) {
            $numTechniciens = count($countUsers);
        }

        // --- Données pour Histogrammes (Style Directeur) ---
        $pifFilters = [
            'subsidiary' => ($selectedFiliale === 'all') ? '' : (string)$selectedFiliale,
            'agency' => ($selectedAgence === 'all') ? '' : (string)$selectedAgence,
            'managerId' => ($managerId === 'all') ? '' : (string)$managerId,
            'brand' => ($filterBrand === 'all') ? '' : (string)$filterBrand,
            'userIds' => $allTechIds // PASSE LE SCOPE FILTRÉ RÉEL
        ];

        $nbPifJunior = $this->pifService->countPIFByExactLevel('Junior', $pifFilters);
        $nbPifSenior = $this->pifService->countPIFByExactLevel('Senior', $pifFilters);
        $nbPifExpert = $this->pifService->countPIFByExactLevel('Expert', $pifFilters);

        $nbValidJunior = $this->pifService->countUsersWithValidation('Junior', $pifFilters);
        $nbValidSenior = $this->pifService->countUsersWithValidation('Senior', $pifFilters);
        $nbValidExpert = $this->pifService->countUsersWithValidation('Expert', $pifFilters);

        $totalProposed = $nbPifJunior + $nbPifSenior + $nbPifExpert;
        $totalValidatedUsers = $nbValidJunior + $nbValidSenior + $nbValidExpert;

        $histograms = [
            [
                'id'=>'levelChart0','label'=>'JUNIOR','badge'=>'#0dcaf0',
                'done'=>[$doneTestJu, $nbPifJunior, $nbValidJunior],
                'todo'=>[count($countUsersJu)-$doneTestJu, count($countUsersJu)-$nbPifJunior, count($countUsersJu)-$nbValidJunior]
            ],
            [
                'id'=>'levelChart1','label'=>'SENIOR','badge'=>'#6c757d',
                'done'=>[$doneTestSe, $nbPifSenior, $nbValidSenior],
                'todo'=>[count($countUsersSe)-$doneTestSe, count($countUsersSe)-$nbPifSenior, count($countUsersSe)-$nbValidSenior]
            ],
            [
                'id'=>'levelChart2','label'=>'EXPERT','badge'=>'#198754',
                'done'=>[$doneTestEx, $nbPifExpert, $nbValidExpert],
                'todo'=>[count($countUsersEx)-$doneTestEx, count($countUsersEx)-$nbPifExpert, count($countUsersEx)-$nbValidExpert]
            ],
            [
                'id'=>'levelChart3','label'=>'TOTAL','badge'=>'#0d6efd',
                'done'=>[$doneTest, $totalProposed, $totalValidatedUsers],
                'todo'=>[count($countUsers)-$doneTest, count($countUsers)-$totalProposed, count($countUsers)-$totalValidatedUsers]
            ]
        ];

        // --- Regroupement pour JS (DASHBOARD object) ---
        $dashboardData = [
            'brandScores'                => $brandScores,
            'brandStats'                 => $teamBrands,
            'trainingsCountsForGraph2'   => $trainingsCountsForGraph2,
            'validationsCountsForGraph2' => $validationsCountsForGraph2,
            'trainingsTechsByBrand'      => $trainingsTechsByBrand,
            'validationsTechsByBrand'    => $validationsTechsByBrand,
            'brandLogos'                 => $brandLogos,
            'numTechniciens'             => $numTechniciens,
            'numTrainings'               => $numTrainings,
            'numDays'                    => $numDays,
            'doneTest'                   => $doneTest,
            'techWithTraining'           => $techWithTraining,
            'techWithTrainingSelected'   => $techWithTrainingSelected
        ];

        $levelCards = [
            ['id'=>'juniorCount','count'=>count($countUsersJu),'label'=>'Junior','sep'=>'+'],
            ['id'=>'seniorCount','count'=>count($countUsersSe),'label'=>'Senior','sep'=>'+'],
            ['id'=>'expertCount','count'=>count($countUsersEx),'label'=>'Expert','sep'=>'='],
            ['id'=>'totalCount','count'=>$numTechniciens,'label'=>'Total'],
        ];

        $tableau = "Tableau de Bord";
        
        // Filtres pour managersList
        $managersList = $this->getManagersList($fullData, $selectedFiliale, $selectedAgence, $filterBrand);
        
        // Définir la page courante avant d'inclure la vue pour éviter les boucles infinies dans le header
        $GLOBALS['currentPage'] = 'dashboard';
        
        // Inclure la vue
        include __DIR__ . "/../views/manager/dashboard.php";
    }
    
    /**
     * Récupère l'ID du manager selon le profil
     */
    private function getManagerId($filters) {
        if (in_array($this->profile, [
            'Super Admin',
            'Admin',
            'Directeur Groupe',
            'Directeur Général',
            'Directeur Pièce et Service',
            'Directeur des Opérations'
        ])) {
            return $filters['managerId'] ?? 'all';
        } elseif ($this->profile === 'Manager') {
            return $_SESSION["id"];
        } else {
            return $_SESSION["managerId"] ?? 'all';
        }
    }
    
    /**
     * Récupère les données des techniciens
     */
    private function getTechniciansData($fullData, $selectedFiliale, $selectedAgence, $managerId, $filterBrand) {
        $technicians = [];
        
        $subsidiariesToProcess = [];
        if ($selectedFiliale === 'all') {
            foreach ($fullData as $k => $v) {
                if ($k !== 'ALL_FILIALES' && $k !== 'CFR') {
                    $subsidiariesToProcess[$k] = $v;
                }
            }
        } elseif (isset($fullData[$selectedFiliale])) {
            $subsidiariesToProcess[$selectedFiliale] = $fullData[$selectedFiliale];
        }

        foreach ($subsidiariesToProcess as $subName => $subsidiaryData) {
            if (isset($subsidiaryData['agencies'])) {
                foreach ($subsidiaryData['agencies'] as $agencyName => $agency) {
                    // Filtrer par agence si défini
                    if ($selectedAgence !== 'all' && $agencyName !== $selectedAgence) {
                        continue;
                    }
                    if (isset($agency['managers'])) {
                        foreach ($agency['managers'] as $manager) {
                            // Si un managerId est filtré, on ne prend que celui-là
                            if ($managerId !== 'all' && isset($manager['id']) && (string)$manager['id'] !== (string)$managerId) {
                                continue;
                            }

                            if (isset($manager['technicians'])) {
                                foreach ($manager['technicians'] as $technician) {
                                    // Filtre de marque
                                    if ($filterBrand !== 'all') {
                                        $techBrands = !empty($technician['brands']) ? (array)$technician['brands'] : [];
                                        if (!in_array($filterBrand, $techBrands)) {
                                            continue;
                                        }
                                    }

                                    $techId = $technician['id'] ?? '';
                                    if (empty($techId)) continue;

                                    $userDoc = $this->userCollection->findOne(['_id' => new ObjectId($techId)]);
                                    $techLevel = $userDoc['level'] ?? 'Junior';

                                    $technicians[] = [
                                        'id' => (string)$techId,
                                        'name' => htmlspecialchars($technician['name'] ?? ''),
                                        'level' => htmlspecialchars($techLevel),
                                        'brands' => !empty($technician['brands']) ? (array)$technician['brands'] : [],
                                        'brandJunior' => isset($userDoc['brandJunior']) ? (array)$userDoc['brandJunior'] : [],
                                        'brandSenior' => isset($userDoc['brandSenior']) ? (array)$userDoc['brandSenior'] : [],
                                        'brandExpert' => isset($userDoc['brandExpert']) ? (array)$userDoc['brandExpert'] : [],
                                        'scoresLevels' => isset($technician['scoresLevels']) ? $technician['scoresLevels'] : []
                                    ];
                                }
                            }
                        }
                    }
                }
            }
        }
        
        return $technicians;
    }
    
    /**
     * Récupère les utilisateurs par niveau
     */
    private function getUsersByLevel($users, $subsidiary, $level = null) {
        $query = [
            'profile' => ['$in' => ['Technicien', 'Manager']], // Filtrer les techniciens et managers
            'active' => true
        ];
        
        // Filtrer uniquement les managers qui ont "test: true"
        $query['$or'] = [
            ['profile' => 'Technicien'], // Inclure les techniciens (en fonction des autres filtres)
            [
                'profile' => 'Manager',
                'test' => true // Inclure uniquement les managers qui ont passé un test
            ]
        ];
        
        if ($level) {
            $query['level'] = $level;
        }
        
        // Si on filtre par filiale (et que ce n'est pas 'all')
        if ($subsidiary !== 'all') {
            $query['subsidiary'] = $subsidiary;
        }
        
        // Filtre additionnel par département si applicable
        if (in_array($this->profile, ['Directeur Général', 'Directeur Pièce et Service', 'Directeur des Opérations', 'Admin', 'Ressource Humaine'])) {
            if ($_SESSION["department"] != 'Equipment & Motors') {
                $query['department'] = $_SESSION["department"];
            }
        }
        
        $countUsers = [];
        $countUser = $users->find($query)->toArray();
        
        foreach ($countUser as $techn) {
            array_push($countUsers, new ObjectId($techn['_id']));
        }
        
        return $countUsers;
    }
    

    
    /**
     * Récupère une allocation
     */
    private function getAllocation($allocations, $user, $level, $type, $activeManager = false) {
        $query = [
            'user' => new ObjectId($user),
            'level' => $level,
            'type' => $type,
            'active' => true
        ];
        if ($activeManager) {
            $query['activeManager'] = true;
        }
        return $allocations->findOne(['$and' => [$query]]);
    }
    
    /**
     * Récupère les techniciens avec des formations
     */
    private function getUsersWithTraining($allocations, $technicians) {
        $doneTraining = [];
        foreach ($technicians as $tech) {
            $training = $this->getAllocationTraining($allocations, $tech, 'Training');
            if ($training) {
                $doneTraining[] = $tech;
            }
        }
        return count($doneTraining);
    }
    
    /**
     * Récupère une allocation de formation
     */
    private function getAllocationTraining($allocations, $user, $type) {
        $query = [
            'user' => new ObjectId($user),
            'type' => $type,
            'active' => false
        ];
        return $allocations->findOne(['$and' => [$query]]);
    }
    
    /**
     * Récupère le nombre de techniciens mesurés (ayant evaluationResults non vide),
     * en cohérence avec la méthode utilisée par l'AnalyticsService du dashboard Directeur.
     */
    private function getMeasuredUsers($allocationCollection, $userIds, $level = 'all') {
        if (empty($userIds)) return 0;

        try {
            // Même syntaxe que AnalyticsService::getGlobalStats (array() requis pour compat BSON)
            $count = $this->userCollection->count(array_merge(
                array('_id' => array('$in' => $userIds)),
                array('evaluationResults' => array('$exists' => true, '$ne' => array()))
            ));
            error_log('getMeasuredUsers [' . $level . '] userIds=' . count($userIds) . ' measured=' . $count);
            return (int)$count;
        } catch (\Exception $e) {
            error_log('getMeasuredUsers error: ' . $e->getMessage());
            return 0;
        }
    }


    /**
     * Récupère les techniciens avec des formations sélectionnées
     */
    private function getUsersWithTrainingSelected($validations, $technicians) {
        $doneTraining = [];
        foreach ($technicians as $tech) {
            $techWithTrainingSelected = $validations->findOne([
                'user' => new ObjectId($tech),
                'status' => 'Validé',
                'active' => true
            ]);
            if ($techWithTrainingSelected) {
                $doneTraining[] = $techWithTrainingSelected;
            }
        }
        return count($doneTraining);
    }
    
    /**
     * Compte les modules de formation (trainings.brand) par marque,
     * pour les techniciens du manager — identique à selectTraining.php.
     */
    private function getTrainingsCountsByBrand($technicians, $filterBrand) {
        $allTechIds = array_map(function($t) { return new ObjectId($t['id']); }, $technicians);
        if (empty($allTechIds)) return [];

        $query = ['users' => ['$in' => $allTechIds], 'active' => true];
        if ($filterBrand !== 'all') $query['brand'] = $filterBrand;

        $counts = [];
        foreach ($this->trainingCollection->find($query, ['projection' => ['brand' => 1]]) as $training) {
            $brand = (string)($training['brand'] ?? '');
            if ($brand === '') continue;
            if (!isset($counts[$brand])) $counts[$brand] = 0;
            $counts[$brand]++;
        }
        arsort($counts);
        return $counts;
    }

    /**
     * Compte les modules de formation validés (status='Validé') par marque.
     */
    private function getValidationsCountsByBrand($technicians, $filterBrand) {
        $allTechIds = array_map(function($t) { return new ObjectId($t['id']); }, $technicians);
        if (empty($allTechIds)) return [];

        // Récupère les IDs de formation distincts validés
        $validTrainingIds = [];
        foreach ($this->validationCollection->find([
            'user'   => ['$in' => $allTechIds],
            'status' => 'Validé',
            'active' => true
        ], ['projection' => ['training' => 1]]) as $val) {
            if (isset($val['training'])) {
                $validTrainingIds[(string)$val['training']] = true;
            }
        }
        if (empty($validTrainingIds)) return [];

        $trainingOids = array_map(function($id) { return new ObjectId($id); }, array_keys($validTrainingIds));
        $query = ['_id' => ['$in' => $trainingOids]];
        if ($filterBrand !== 'all') $query['brand'] = $filterBrand;

        $counts = [];
        foreach ($this->trainingCollection->find($query, ['projection' => ['brand' => 1]]) as $training) {
            $brand = (string)($training['brand'] ?? '');
            if ($brand === '') continue;
            if (!isset($counts[$brand])) $counts[$brand] = 0;
            $counts[$brand]++;
        }
        return $counts;
    }

    /**
     * Retourne les noms des techniciens ayant des formations par marque (pour le tooltip).
     */
    private function getTrainingsNamesByBrand($technicians, $filterBrand) {
        $names = [];
        foreach ($technicians as $tech) {
            $techId = new ObjectId($tech['id']);
            $query  = ['users' => $techId, 'active' => true];
            if ($filterBrand !== 'all') $query['brand'] = $filterBrand;

            foreach ($this->trainingCollection->find($query, ['projection' => ['brand' => 1]]) as $training) {
                $brand = (string)($training['brand'] ?? '');
                if ($brand === '') continue;
                if (!isset($names[$brand])) $names[$brand] = [];
                if (!in_array($tech['name'], $names[$brand])) {
                    $names[$brand][] = $tech['name'];
                }
            }
        }
        return $names;
    }

    /**
     * Retourne les noms des techniciens ayant des validations par marque (pour le tooltip).
     */
    private function getValidationsNamesByBrand($technicians, $filterBrand) {
        $names = [];
        foreach ($technicians as $tech) {
            $techId = new ObjectId($tech['id']);
            $validTrainingIds = [];
            foreach ($this->validationCollection->find([
                'user'   => $techId,
                'status' => 'Validé',
                'active' => true
            ], ['projection' => ['training' => 1]]) as $val) {
                if (isset($val['training'])) {
                    $validTrainingIds[(string)$val['training']] = true;
                }
            }
            if (empty($validTrainingIds)) continue;

            $trainingOids = array_map(function($id) { return new ObjectId($id); }, array_keys($validTrainingIds));
            $query = ['_id' => ['$in' => $trainingOids]];
            if ($filterBrand !== 'all') $query['brand'] = $filterBrand;

            foreach ($this->trainingCollection->find($query, ['projection' => ['brand' => 1]]) as $training) {
                $brand = (string)($training['brand'] ?? '');
                if ($brand === '') continue;
                if (!isset($names[$brand])) $names[$brand] = [];
                if (!in_array($tech['name'], $names[$brand])) {
                    $names[$brand][] = $tech['name'];
                }
            }
        }
        return $names;
    }

    /**
     * Calcule les scores par marque directement depuis les données des techniciens.
     * Utilisé pour le Manager car filterController fixe subsidiary='all'.
     */
    private function getScoresByBrandFromTechnicians($technicians, $filterLevel) {
        $brandAggregator = [];
        foreach ($technicians as $tech) {
            foreach ($tech['scoresLevels'] as $level => $bScores) {
                if ($filterLevel !== 'all' && $level !== $filterLevel) continue;
                foreach ($bScores as $brand => $score) {
                    if (!isset($brandAggregator[$brand])) {
                        $brandAggregator[$brand] = ['sum' => 0, 'count' => 0];
                    }
                    $brandAggregator[$brand]['sum'] += (float)$score;
                    $brandAggregator[$brand]['count'] += 1;
                }
            }
        }
        $result = [];
        foreach ($brandAggregator as $brand => $vals) {
            $count = $vals['count'] > 0 ? $vals['count'] : 1;
            $result[$brand] = round($vals['sum'] / $count, 2);
        }
        return $result;
    }

    /**
     * Récupère les scores par marque
     */
    private function getScoresByBrand($fullData, $selectedFiliale, $filterLevel) {
        if (($this->profile === 'Directeur Groupe' || $this->profile === 'Super Admin') && $selectedFiliale === 'all') {
            if ($filterLevel === 'all') {
                return $fullData['ALL_FILIALES']['averages']['ALL'] ?? [];
            } else {
                return $fullData['ALL_FILIALES']['averages'][$filterLevel] ?? [];
            }
        } else {
            if ($filterLevel === 'all') {
                return $fullData[$selectedFiliale]['averages']['ALL'] ?? [];
            } else {
                return $fullData[$selectedFiliale]['averages'][$filterLevel] ?? [];
            }
        }
    }
    
    /**
     * Prépare les scores pour le graphique
     */
    private function prepareBrandScores($scoresArr, $filterBrand) {
        $brandScores = [];
        foreach ($scoresArr as $brandName => $avgScore) {
            // Si on a filtré par brand et que ce n'est pas la même => skip
            if ($filterBrand !== 'all' && $filterBrand !== $brandName) {
                continue;
            }
            
            // Déterminer la couleur selon le score
            $avgScoreFloat = (float)$avgScore;
            if ($avgScoreFloat >= 80) {
                $color = '#198754'; // Vert
            } elseif ($avgScoreFloat >= 60) {
                $color = '#ffc107'; // Jaune
            } else {
                $color = '#dc3545'; // Rouge
            }
            
            $brandScores[] = [
                'x' => $brandName,
                'y' => $avgScoreFloat,
                'fillColor' => $color
            ];
        }
        return $brandScores;
    }
    
    /**
     * Récupère les marques de l'équipe
     */
    private function getTeamBrands($technicians, $filterBrand) {
        $brands = [];
        foreach ($technicians as $tech) {
            if (!empty($tech['brands'])) {
                foreach ($tech['brands'] as $b) {
                    $b = trim($b);
                    if ($b !== '') {
                        if ($filterBrand === 'all' || $filterBrand === $b) {
                            $brands[$b] = true;
                        }
                    }
                }
            }
        }
        $brandList = array_keys($brands);
        sort($brandList);
        return $brandList;
    }
    
    /**
     * Récupère le niveau maximum dans l'équipe
     */
    private function getMaxLevel($technicians) {
        $maxLevel = 'Junior';
        foreach ($technicians as $tech) {
            $lvl = $tech['level'] ?? 'Junior';
            if ($lvl === 'Expert') {
                $maxLevel = 'Expert';
                break;
            } elseif ($lvl === 'Senior' && $maxLevel !== 'Expert') {
                $maxLevel = 'Senior';
            }
        }
        return $maxLevel;
    }
    
    /**
     * Récupère les niveaux à afficher selon le niveau max
     */
    private function getLevelsToShowByMaxLevel($maxLevel) {
        $levelsToShow = [];
        if ($maxLevel === 'Expert') {
            $levelsToShow = array_merge($levelsToShow, ['all', 'Junior', 'Senior', 'Expert']);
        } elseif ($maxLevel === 'Senior') {
            $levelsToShow = array_merge($levelsToShow, ['all', 'Junior', 'Senior']);
        } else {
            $levelsToShow = array_merge($levelsToShow, ['all', 'Junior']);
        }
        return $levelsToShow;
    }
    
    /**
     * Récupère la liste des managers
     */
    private function getManagersList($fullData, $selectedFiliale, $selectedAgence, $filterBrand) {
        $managersList = [];
        
        $subsidiariesToProcess = [];
        if ($selectedFiliale === 'all') {
            foreach ($fullData as $k => $v) {
                if ($k !== 'ALL_FILIALES' && $k !== 'CFR') {
                    $subsidiariesToProcess[$k] = $v;
                }
            }
        } elseif (isset($fullData[$selectedFiliale])) {
            $subsidiariesToProcess[$selectedFiliale] = $fullData[$selectedFiliale];
        }

        foreach ($subsidiariesToProcess as $subName => $subsidiaryData) {
            if (isset($subsidiaryData['agencies'])) {
                foreach ($subsidiaryData['agencies'] as $agencyName => $agencyData) {
                    // Filtrer par agence si défini
                    if ($selectedAgence !== 'all' && $agencyName !== $selectedAgence) {
                        continue;
                    }
                    if (isset($agencyData['managers'])) {
                        foreach ($agencyData['managers'] as $manager) {
                            $mId = (string)($manager['id'] ?? '');
                            $mName = $manager['name'] ?? 'Manager Sans Nom';
                            if (empty($mId)) continue;

                            // Vérifier que le manager possède au moins un technicien
                            if (!isset($managersList[$mId]) && !empty($manager['technicians'])) {
                                // Si un filtre de marque est sélectionné, on vérifie qu'au moins un technicien a cette marque
                                if ($filterBrand !== 'all') {
                                    $hasBrand = false;
                                    foreach ($manager['technicians'] as $technician) {
                                        if (!empty($technician['brands']) && in_array($filterBrand, $technician['brands'])) {
                                            $hasBrand = true;
                                            break;
                                        }
                                    }
                                    if (!$hasBrand) continue;
                                }
                                $managersList[$mId] = $mName;
                            }
                        }
                    }
                }
            }
        }
        return $managersList;
    }
}

/**
 * Classe pour récupérer les données des managers, techniciens et scores
 */
class DataCollection {
    private $collectionManagers;
    private $collectionScores;
    private $result = [];

    public function __construct() {
        try {
            require_once __DIR__ . '/../core/portal-mongo.php';
            $database = definePortalMongoDatabase();
            $this->collectionManagers = $database->managersBySubsidiaryAgency;
            $this->collectionScores = $database->technicianBrandScores;
        } catch (MongoDB\Exception\Exception $e) {
            throw $e;
        }
    }

    private function addScoreToAggregator(&$aggregator, $level, $brand, $avg) {
        $level = (string) $level;
        $brand = (string) $brand;
        if (!isset($aggregator[$level])) {
            $aggregator[$level] = [];
        }
        if (!isset($aggregator[$level][$brand])) {
            $aggregator[$level][$brand] = ['sum' => 0, 'count' => 0];
        }
        $aggregator[$level][$brand]['sum'] += $avg;
        $aggregator[$level][$brand]['count'] += 1;
    }

    private function mergeAggregatorsAccurate(&$dest, $source) {
        foreach ($source as $level => $brandArr) {
            $level = (string)$level;
            if (!isset($dest[$level])) {
                $dest[$level] = [];
            }
            foreach ($brandArr as $brand => $vals) {
                $brand = (string)$brand;
                if (!isset($dest[$level][$brand])) {
                    $dest[$level][$brand] = ['sum' => 0, 'count' => 0];
                }
                $dest[$level][$brand]['sum'] += $vals['sum'];
                $dest[$level][$brand]['count'] += $vals['count'];
            }
        }
    }

    public static function finalizeAverages($aggregator, $withAllLevel = true) {
        $result = [];
        $allLevelAggregator = [];

        foreach ($aggregator as $level => $brandArr) {
            foreach ($brandArr as $brand => $vals) {
                $count = ($vals['count'] === 0) ? 1 : $vals['count'];
                $avg = round(($vals['sum'] / $count), 2);
                $result[$level][$brand] = $avg;
                if ($withAllLevel) {
                    if (!isset($allLevelAggregator[$brand])) {
                        $allLevelAggregator[$brand] = ['sum' => 0, 'count' => 0];
                    }
                    $allLevelAggregator[$brand]['sum'] += $vals['sum'];
                    $allLevelAggregator[$brand]['count'] += $vals['count'];
                }
            }
        }
        if ($withAllLevel) {
            foreach ($allLevelAggregator as $brand => $vals) {
                $count = ($vals['count'] === 0) ? 1 : $vals['count'];
                $avg = round(($vals['sum'] / $count), 2);
                $result['ALL'][$brand] = $avg;
            }
        }
        return $result;
    }

    private function buildHierarchy() {
        $cursor = $this->collectionManagers->find([]);
        foreach ($cursor as $document) {
            $subsidiary = $document['subsidiary'] ?? 'Unknown';
            $agencies = $document['agencies'] ?? [];

            if (!isset($this->result[$subsidiary])) {
                $this->result[$subsidiary] = [
                    'agencies' => [],
                    'aggregator' => []
                ];
            }
            foreach ($agencies as $agency) {
                $agencyName = $agency['_id'] ?? 'Unknown';

                if (!isset($this->result[$subsidiary]['agencies'][$agencyName])) {
                    $this->result[$subsidiary]['agencies'][$agencyName] = [
                        'managers' => [],
                        'aggregator' => []
                    ];
                }
                $managersArr = $agency['managers'] ?? [];
                foreach ($managersArr as $manager) {
                    $managerName = ($manager['firstName'] ?? '') . " " . ($manager['lastName'] ?? '');
                    $managerAggregator = [];
                    $techniciansList = [];

                    if (isset($manager['technicians'])) {
                        foreach ($manager['technicians'] as $technician) {
                            $techId = $technician['_id'] ?? null;
                            $techName = ($technician['firstName'] ?? '') . " " . ($technician['lastName'] ?? '');
                            $brands = isset($technician['distinctBrands'])
                                ? (array)$technician['distinctBrands']
                                : [];

                            // Chercher les scores du technicien dans technicianBrandScores
                            if ($techId) {
                                $scoreDoc = $this->collectionScores->findOne(['userId' => $techId]);
                            } else {
                                $scoreDoc = null;
                            }

                            $scoresByLevel = [];
                            if ($scoreDoc && isset($scoreDoc['scores'])) {
                                foreach ($scoreDoc['scores'] as $level => $brandScores) {
                                    $tempBrandScore = [];
                                    foreach ($brandScores as $brandName => $scoreDetails) {
                                        $avgTotal = isset($scoreDetails['averageTotalWithPenalty'])
                                            ? (float)$scoreDetails['averageTotalWithPenalty']
                                            : (float)$scoreDetails['averageTotal'];
                                        $tempBrandScore[$brandName] = $avgTotal;
                                        $this->addScoreToAggregator($managerAggregator, $level, $brandName, $avgTotal);
                                    }
                                    $scoresByLevel[$level] = $tempBrandScore;
                                }
                            }

                            // Utiliser $techId et $techName
                            $techniciansList[] = [
                                'id' => htmlspecialchars($techId ?? ''),
                                'name' => htmlspecialchars($techName ?? ''),
                                'brands' => $brands,
                                'scoresLevels' => $scoresByLevel
                            ];
                        }
                    }

                    // Ajouter 'id' au managerEntry
                    $managerEntry = [
                        'id' => (string)($manager['_id'] ?? ''), // Ajout de l'ID du manager
                        'name' => $managerName,
                        'technicians' => $techniciansList,
                        'aggregator' => $managerAggregator
                    ];
                    $this->result[$subsidiary]['agencies'][$agencyName]['managers'][] = $managerEntry;

                    // Fusion dans l'agence
                    $this->mergeAggregatorsAccurate(
                        $this->result[$subsidiary]['agencies'][$agencyName]['aggregator'],
                        $managerAggregator
                    );
                }
                // Fusion dans la filiale
                $this->mergeAggregatorsAccurate(
                    $this->result[$subsidiary]['aggregator'],
                    $this->result[$subsidiary]['agencies'][$agencyName]['aggregator']
                );
            }
        }
                // AJOUT : fusionner toutes les filiales dans un seul aggregator
                // -----------------------------
                $globalAggregator = [];  // on va y fusionner tous les aggregator de chaque filiale

                foreach ($this->result as $subsidiaryName => $subData) {
                    // On s'assure que le sous-tableau de la filiale possède un aggregator
                    if (isset($subData['aggregator']) && is_array($subData['aggregator'])) {
                        // On fusionne
                        $this->mergeAggregatorsAccurate($globalAggregator, $subData['aggregator']);
                    }
                }

                // On stocke l'aggregator global dans $this->result
                // de manière à avoir un bloc "ALL_FILIALES"
                $this->result['ALL_FILIALES'] = [
                    'aggregator' => $globalAggregator
                ];
    }

    
    public function getFullData() {
        // 1) Construire la hiérarchie si ce n'est pas déjà fait
        if (empty($this->result)) {
            $this->buildHierarchy();
        }
    
        // 2) Parcourir toutes les « filiales » (y compris ALL_FILIALES)
        foreach ($this->result as $subsidiary => &$subData) {
    
            // a) Si on a un aggregator au niveau filiale (ou ALL_FILIALES), on calcule les averages
            if (isset($subData['aggregator']) && is_array($subData['aggregator'])) {
                $subData['averages'] = self::finalizeAverages($subData['aggregator'], true);
            }
    
            // b) Si ce n'est pas le bloc "ALL_FILIALES", alors on a potentiellement des agences + managers
            if ($subsidiary !== 'ALL_FILIALES' && isset($subData['agencies']) && is_array($subData['agencies'])) {
                foreach ($subData['agencies'] as &$agencyData) {
                    // Si l'agence possède un aggregator
                    if (isset($agencyData['aggregator']) && is_array($agencyData['aggregator'])) {
                        $agencyData['averages'] = self::finalizeAverages($agencyData['aggregator'], true);
                    }
    
                    // c) Parcourir ses managers
                    if (isset($agencyData['managers']) && is_array($agencyData['managers'])) {
                        foreach ($agencyData['managers'] as &$managerInfo) {
                            if (isset($managerInfo['aggregator']) && is_array($managerInfo['aggregator'])) {
                                $managerInfo['averages'] = self::finalizeAverages($managerInfo['aggregator'], true);
                            }
                        }
                        unset($managerInfo);
                    }
                }
                unset($agencyData);
            }
        }
        unset($subData);
    
        // 3) Retourner le tableau final
        return $this->result;
    }
}
?>