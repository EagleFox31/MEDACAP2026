<?php
session_start();

use MongoDB\Client as MongoClient;
use MongoDB\Exception\Exception as MongoException;

// ----------------------------------------------------------
// 1) Vérifier session / profil, puis connexion MongoDB
// ----------------------------------------------------------
include_once "../language.php";

if (!isset($_SESSION["profile"])) {
    header("Location: ../../");
    exit();
} else {
    // Logos
    $brandLogos = [
        'RENAULT TRUCK'   => 'renaultTrucks.png',
        'HINO'            => 'Hino_logo.png',
        'TOYOTA BT'       => 'bt.png',
        'SINOTRUK'        => 'sinotruk.png',
        'JCB'             => 'jcb.png',
        'MERCEDES TRUCK'  => 'mercedestruck.png',
        'TOYOTA FORKLIFT' => 'forklift.png',
        'FUSO'            => 'fuso.png',
        'LOVOL'           => 'lovol.png',
        'KING LONG'       => 'kl2.png',
        'MERCEDES'        => 'mercedestruck.png',
        'TOYOTA'          => 'toyota-logo.png',
        'SUZUKI'          => 'suzuki-logo.png',
        'MITSUBISHI'      => 'mitsubishi-logo.png',
        'BYD'             => 'byd-logo.png',
        'CITROEN'         => 'citroen-logo.png',
        'PEUGEOT'         => 'peugeot-logo.png',
    ];        

    if ($_SESSION['profile'] == 'Technicien') {
        require_once "../../vendor/autoload.php";

        // Create connection
        $conn = new MongoDB\Client("mongodb://localhost:27017");

        // Connecting in database
        $academy = $conn->academy;

        // Connecting in collections
        $users = $academy->users;
        $trainings = $academy->trainings;
        $results = $academy->results;
        $allocations = $academy->allocations;
        $applications = $academy->applications;
        $connections = $academy->connections;

        
        $levels = [
            'Junior' => ['Junior'], 
            'Senior' => ['Junior', 'Senior'], 
            'Expert' => ['Junior', 'Senior', 'Expert']
        ];

        // Technicien can only view their own dashboard
        $technicianId = $_SESSION["id"];

        // ----------------------------------------------------------
        // 2) Charger la config GF (groupes fonctionnels) et ScoreCalculator
        // ----------------------------------------------------------
        $config = require __DIR__ . "../../configGF.php";
        //  Par ex. ce configGF.php contient :
        //  'functionalGroupsByLevel' => [...],
        //  'nonSupportedGroupsByBrand' => [...], etc.

        require_once __DIR__ . "/ScoreFunctions.php";
        //  La classe ScoreCalculator fait déjà ses agrégations en interne.

        // Ensure $technicianId is a string (it should already be from $_GET or $_SESSION)
        $technicianId = isset($_GET['id']) ? (string)$_GET['id'] : (string)$_SESSION["id"];

        try {
            $techObjId = new MongoDB\BSON\ObjectId($technicianId);
        } catch (\Exception $e) {
            echo "Identifiant technicien invalide.";
            exit();
        }

        // Charger le document utilisateur
        $technicianDoc = $users->findOne([
            '_id' => $techObjId,
            'profile' => 'Technicien'
        ]);

        if (!$technicianDoc) {
            echo "Technicien introuvable.";
            exit();
        }
        
        // Récupérer les marques d'un technicien
        $allBrands = [];
        if (isset($technicianDoc['brandJunior'])) {
            foreach ($technicianDoc['brandJunior'] as $b) {
                $bTrimmed = trim((string)$b);
                if ($bTrimmed !== '') {
                    $allBrands[$bTrimmed] = true;
                }
            }
        }
        if (isset($technicianDoc['brandSenior'])) {
            foreach ($technicianDoc['brandSenior'] as $b) {
                $bTrimmed = trim((string)$b);
                if ($bTrimmed !== '') {
                    $allBrands[$bTrimmed] = true;
                }
            }
        }
        if (isset($technicianDoc['brandExpert'])) {
            foreach ($technicianDoc['brandExpert'] as $b) {
                $bTrimmed = trim((string)$b);
                if ($bTrimmed !== '') {
                    $allBrands[$bTrimmed] = true;
                }
            }
        }
        $allBrands = array_keys($allBrands);
        sort($allBrands);

        // Récupérer le niveau du technicien
        $tLevel = isset($technicianDoc['level']) ? $technicianDoc['level'] : 'Junior';
        // 3) On lit les filtres GET brand/level s'ils existent
        $filterBrand = isset($_GET['brand']) ? trim($_GET['brand']) : 'all';
        $levelFilter = isset($_GET['level']) ? trim($_GET['level']) : 'all';
        // "Tous les Niveaux", "Junior", "Senior", ou "Expert"

        // ----------------------------------------------------------
        // 4) Préparer la liste de niveaux à inclure (ex. un Expert inclut Senior + Junior, etc.)
        // ----------------------------------------------------------
        function getLevelsToInclude($level) {
            if ($level === 'Junior') return ['Junior'];
            if ($level === 'Senior') return ['Senior'];
            if ($level === 'Expert') return ['Expert'];
            if ($level === 'all') return ['Junior', 'Senior', 'Expert'];
        }
        $levels = getLevelsToInclude($levelFilter);

        // ----------------------------------------------------------

        function getBrandsToInclude($brands, $levels, $docs) {
            // Suppose qu’on a brandJunior, brandSenior, brandExpert
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

        // Ensure $managerId is a string if it's not null
        $managerId = isset($technicianDoc['manager']) ? (string)$technicianDoc['manager'] : null;

        // Now, construct the map with scalar keys and values
        $technicianManagerMap = [
            $technicianId => $managerId
        ];

        // Lister toutes les spécialités qu’on veut prendre en compte
        // (ex. toutes celles de functionalGroupsByLevel)
        $allSpecialities = [];
        foreach ($config['functionalGroupsByLevel'] as $lvl => $groups) {
            foreach ($groups as $g) {
                if (!in_array($g, $allSpecialities)) {
                    $allSpecialities[] = $g;
                }
            }
        }
        // Instancier ScoreCalculator
        $scoreCalc = new ScoreCalculator($academy);
        // Récupérer la “big array” [technicianId => [level => [speciality => [Factuel, Declaratif]]]]
        // Define a variable for the fifth parameter
        $optionalParam = null;

        // Pass the variable by reference
        $allScores = $scoreCalc->getAllScoresForTechnicians(
            $academy,
            $technicianManagerMap,
            $levels,
            $allSpecialities,
            $optionalParam
        );

        // $allScores[$technicianId][$level][$speciality] = ['Factuel'=>..., 'Declaratif'=>...]

        // ----------------------------------------------------------
        // 6) Calculer la note "globale" par marque (pour le graphe)
        //
        //    a) Récupérer la liste des marques (brand) que le technicien maîtrise
        //       ou qu'on veut afficher. (Vous pouvez définir cela via brandJunior, brandSenior, etc.
        //       Ou alors lister TOUTES les marques existantes ?
        // ----------------------------------------------------------
        $technicianDoc = $users->findOne(
            ['_id' => new MongoDB\BSON\ObjectId($technicianId), 'profile' => 'Technicien']
        );
        if (!$technicianDoc) {
            // Si le technicien n'existe pas => on fera un fallback
            $brandsToShow = [];
        } else {
            $brandsToShow = getBrandsToInclude($filterBrand, $levels, $technicianDoc);
        }

        //    b) Pour chaque marque, on détermine les groupes fonctionnels supportés
        //       => On regarde configGF.php, on enlève ce qui est "nonSupportedGroupsByBrand".
        //       => On récupère la/les notes du technicien pour ces groupes, on fait la moyenne
        // ----------------------------------------------------------
        function getSupportedGroupsForBrand($brand, $level, $config)
        {
            // On part de functionalGroupsByLevel[$level], puis on retire 
            // tout ce qui est "nonSupportedGroupsByBrand[$brand]"  
            $all = $config['functionalGroupsByLevel'][$level] ?? [];
            $nonSupp = $config['nonSupportedGroupsByBrand'][$brand] ?? [];
            return array_values(array_diff($all, $nonSupp));
        }

        $brandScores = [];  // on va y stocker [ ['x'=>'Renault Trucks', 'y'=>85, 'fillColor'=>'#198754', 'labelText'=>['10', 'Modules de Formations'], 'url'=>'#'], ... ]

        // Principe : on cumule toutes les notes trouvées (Factuel / Declaratif), on fait la moyenne globale
        foreach ($brandsToShow as $oneBrand) {
            $sumAll   = 0.0;
            $countAll = 0;

            foreach ($levels as $lvl) {
                // Récup les GF supportés
                $supportedGroups = getSupportedGroupsForBrand($oneBrand, $lvl, $config);
                // Parcourir chaque groupe => regarder $allScores[$technicianId][$lvl][$group]
                foreach ($supportedGroups as $grp) {
                    if (isset($allScores[$technicianId][$lvl][$grp])) {
                        $fact = $allScores[$technicianId][$lvl][$grp]['Factuel']    ?? null;
                        $decl = $allScores[$technicianId][$lvl][$grp]['Declaratif'] ?? null;
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
                        // si aucun, on n'ajoute rien
                    }
                }
            }

            if ($countAll > 0) {
                $finalScore = round($sumAll / $countAll);
            } else {
                // pas de note => null
                $finalScore = null;
            }

            // Définir le texte basé sur la couleur avec nombre de modules
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
                'fillColor' => ($finalScore !== null && $finalScore >= 80) ? '#198754' : (($finalScore !== null) ? '#ffc107' : '#6c757d'), // Vert pour >=80, Jaune pour <80, Gris sinon
                'labelText' => $labelText,
                'url' => './personalTraining?brand='.$oneBrand.'&level='.$getLevel // Placeholder, peut être remplacé par des URLs réelles
            ];
        }

        // ----------------------------------------------------------
        // 7) Récupérer / Compter les formations pour ce technicien 
        //    (sans nouvelle collection) :
        //    - Recommandées : training.active=true, users inclut $technicianId
        //    - Réalisées    : training.active=true, endDate != null
        //    - On compte le nombre de formations et les marques associées
        // ----------------------------------------------------------

        // a) ID du technicien en ObjectId
        $technicianObjId = new MongoDB\BSON\ObjectId($technicianId);

        // Fonction pour compter les formations recommandées
        function countRecommendedTrainings($applications, $trainings, $technicianObjId, array $levels, array $brands)
        {
            $recommendedTrainings = [];
            $query = [
                'user'  => $technicianObjId,
                'active' => true,
            ];
            $results = $applications->find($query)->toArray();
            foreach ($results as $result) {
                $filter = [
                    '_id'  => new MongoDB\BSON\ObjectId($result['training']),
                    'users'  => $technicianObjId,
                    'brand'  => ['$in' => $brands],
                    'level'  => ['$in' => $levels],
                    'active' => true,
                ];
                $trainingData = $trainings->findOne($filter);
                if (isset($trainingData)) {
                    $recommendedTrainings[] = $trainingData;
                }
            }
            return $recommendedTrainings;
        }

        // Fonction pour compter les formations réalisées
        function countCompletedTrainings($allocations, $trainings, $technicianObjId, array $levels, array $brands)
        {
            $filter = [
                'users'  => $technicianObjId,
                'brand'  => ['$in' => $brands],
                'level'  => ['$in' => $levels],
                'active' => true,
            ];
            $trainingData = $trainings->findOne($filter);

            $query = [
                'user'  => $technicianObjId,
                'training'  => new MongoDB\BSON\ObjectId($trainingData['_id']),
                'type'  => 'Training',
                'active' => true
            ];
            return $allocations->find($query)->toArray();
        }

        // b) Récupérer les formations et compter par marque
        function getTrainingsByBrand($applications, $technicianObjId, array $levels, array $brands)
        {
            // Assurez-vous d'importer les classes nécessaires
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
                        'trainingDetails.level' => ['$in' => $levels],
                        'trainingDetails.brand' => ['$in' => $brands],
                        'trainingDetails.active' => true,
                    ],
                ],
                [
                    '$group' => [
                        '_id' => [
                            'brand' => '$trainingDetails.brand',
                            'level' => '$trainingDetails.level',
                        ],
                        'count' => ['$sum' => 1],
                    ],
                ],
                [
                    '$group' => [
                        '_id' => '$_id.brand',
                        'total' => ['$sum' => '$count'],
                    ],
                ],
                [
                    '$project' => [
                        'brand' => '$_id',
                        'count' => '$total',
                        '_id' => 0,
                    ],
                ],
            ];

            // Exécuter le pipeline
            $results = $applications->aggregate($pipeline)->toArray();

            $trainingsByBrand = [];
            foreach ($results as $doc) {
                $trainingsByBrand[] = [
                    'brand' => $doc->brand,
                    'count' => $doc->count
                ];
            }
            return $trainingsByBrand;
        }

        // c) Récupérer les counts
        try {
            $numRecommended = countRecommendedTrainings($applications, $trainings, $technicianObjId, $levels, $brandsToShow);
            $numCompleted   = countCompletedTrainings($allocations, $trainings, $technicianObjId, $levels, $brandsToShow);
            $trainingsByBrand = getTrainingsByBrand($applications, $technicianObjId, $levels, $brandsToShow);
        } catch (MongoDB\Exception\Exception $e) {
            echo "Erreur lors du calcul des statistiques des formations : " . htmlspecialchars($e->getMessage());
            exit();
        }

        // On construit un "map" brand => count
        $brandFormationsMap = [];
        foreach ($trainingsByBrand as $row) {
            $brandFormationsMap[(string)$row['brand']] = (int)$row['count'];
        }

        // Fonction pour calculer les heures de formation par marque
        function getTrainingHoursByBrand($trainings, $applications, $technicianObjId, array $levels, array $brands)
        {
            $query = [
                'user'  => $technicianObjId,
                'active' => true,
            ];
            $brandHoursMap = [];
            $results = $applications->find($query)->toArray();
            foreach ($results as $result) {
                $filter = [
                    '_id'  => new MongoDB\BSON\ObjectId($result['training']),
                    'users'  => $technicianObjId,
                    'brand'  => ['$in' => $brands],
                    'level'  => ['$in' => $levels],
                    'active' => true,
                ];
                $trainingData = $trainings->findOne($filter);
                if (isset($trainingData)) {
                    $brandHoursMap[] = $trainingData['duration'];
                }
            }
            
            // Calculer la somme totale des heures
            return array_sum($brandHoursMap);
        }

        // Calculer les heures de formation par marque
        $brandHoursMap = getTrainingHoursByBrand($trainings, $applications, $technicianObjId, $levels, $brandsToShow);

        // ------------------------------
        // CRÉER ET CALCULER totalDuration
        // ------------------------------

        // 1) Définir le tableau (valeurs par défaut)
        $totalDuration = [
            'jours'  => 0,
            'heures' => 0
        ];

        // 2) Récupérer toutes les formations du technicien
        $cursorTrainings = $trainings->find([
            'active' => true,
            'users'  => $technicianObjId,
            'level'  => ['$in' => $levels],
            'brand'  => ['$ne' => ''],
            // Ajoutez d'autres filtres si nécessaire...
        ]);

        // 3) Calculer la somme en "jours" (d’après votre champ 'duree_jours')
        $daysSum = 0;
        foreach ($cursorTrainings as $trainingDoc) {
            // Si chaque doc de formation possède le champ 'duree_jours'
            if (isset($trainingDoc['duree_jours']) && $trainingDoc['duree_jours'] > 0) {
                $daysSum += (float)$trainingDoc['duree_jours'];
            }
        }

        // 4) Séparer en jours entiers et heures
        //    - 1 journée = 8 heures
        $fullDays = floor($daysSum);            // ex: 5, si $daysSum = 5.5
        $decimalPart = $daysSum - $fullDays;    // ex: 0.5
        $hours = $decimalPart * 8;              // ex: 0.5 * 8 = 4 heures

        // 5) Alimenter le tableau
        $totalDuration['jours']  = (int) $fullDays;
        $totalDuration['heures'] = (int) $hours;

        // ------------------------------
        // FIN CRÉATION totalDuration
        // ------------------------------

        // ----------------------------------------------------------
        // 8) Affichage (Bootstrap + Chart Libraries)
        // ----------------------------------------------------------
    } else {
        $profile = $_SESSION['profile'] ?? '';
        // Pour un manager, s'il n'y a pas de managerId dans l'URL, rediriger avec l'ID de session
        // if ($_SESSION["profile"] === 'Manager' && !isset($_GET['managerId'])) {
        //     header("Location: " . $_SERVER['PHP_SELF'] . "?managerId=" . $_SESSION['id']);
        //     exit();
        // }
        //     // Autoriser l'accès si l'utilisateur est Manager, Super Admin ou Directeur Filiale

        if (!in_array($profile, [
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


        if (in_array($profile, [
            'Super Admin',
            'Admin',
            'Directeur Groupe',
            'Directeur Général',
            'Directeur Pièce et Service',
            'Directeur des Opérations'
        ])) {
            // Eux aussi ont un scope "filiale" complet
            $managerId = isset($_GET['managerId']) ? $_GET['managerId'] : 'all';
        } elseif ($profile === 'Manager') {
            $managerId = $_SESSION["id"];
        } else {
            // Technicien
            $managerId = $_SESSION["managerId"] ?? 'all';
        }

        if ($profile === 'Technicien') {
            // Si le paramètre "technicianId" n'existe pas dans l'URL,
            // rediriger vers la même page en l'ajoutant.
            if (!isset($_GET['technicianId'])) {
                // On récupère l'ID du technicien depuis la session
                $techId = (string) $_SESSION["id"];
                // On reconstruit l'URL actuelle sans les autres paramètres (vous pouvez adapter pour conserver d'autres GET)
                $currentUri = $_SERVER['PHP_SELF'];
                header("Location: {$currentUri}?technicianId={$techId}");
                exit();
            }
            // Sinon, on récupère l'ID du technicien depuis l'URL
            $technicianId = (string) $_GET['technicianId'];
        }

        $technicianId = isset($_GET['id']) ? (string)$_GET['id'] : (string)$_SESSION["id"];

        try {
            $techObjId = new MongoDB\BSON\ObjectId($technicianId);
        } catch (\Exception $e) {
            echo "Identifiant technicien invalide.";
            exit();
        }

        require_once "../../vendor/autoload.php";

        // Connexion
        try {
            $mongo = new MongoDB\Client("mongodb://localhost:27017");
            $academy = $mongo->academy;  // base "academy"

            // Collections
            $usersColl     = $academy->users;
            $trainingsColl = $academy->trainings;
            $applications = $academy->applications;
            $allocationsColl = $academy->allocations;
            $applicationsColl = $academy->applications;
            $validationsColl = $academy->validations;
        } catch (MongoDB\Exception\Exception $e) {
            echo "Erreur de connexion à MongoDB : " . htmlspecialchars($e->getMessage());
            exit();
        }
        function getTechLevelFromUserId($userId)
        {
            // On utilise ici la variable globale $usersColl
            // si vous préférez, vous pouvez passer $usersColl en paramètre
            global $usersColl;

            if (empty($userId)) {
                return 'Junior'; // fallback
            }
            try {
                // Convertir la chaîne en ObjectId
                $objId = new MongoDB\BSON\ObjectId($userId);
            } catch (\Exception $e) {
                // Si le cast en ObjectId échoue
                return 'Junior'; // fallback ou "Unknown"
            }

            // Trouver le doc correspondant dans la collection users
            $doc = $usersColl->findOne(['_id' => $objId]);
            if (!$doc) {
                // Pas de document trouvé
                return 'Junior';
            }
            if (!isset($doc['profile']) || $doc['profile'] !== 'Technicien') {
                // Dans votre cas, si vous ne cherchez que les techniciens
                // vous pouvez aussi renvoyer un autre fallback
                return 'Junior';
            }

            // Renvoyer le champ 'level' s'il existe
            return $doc['level'] ?? 'Junior';
        }
        
        function getUsersByLevel($users, $subsidiary, $level = null) {
            $query = [
                'profile' => ['$in' => ['Technicien', 'Manager']], // Filtrer les techniciens et managers
                'active' => true
            ];
        
            // Filtrer uniquement les managers qui ont "test: true"
            // Cela est nécessaire pour tous les managers peu importe le niveau
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
            
            if ($_SESSION['profile'] == 'Directeur Général' || $_SESSION['profile'] == 'Directeur Pièce et Service' || $_SESSION['profile'] == 'Directeur des Opérations' || $_SESSION['profile'] == 'Admin' || $_SESSION['profile'] == 'Ressource Humaine') {
                $query['subsidiary'] = $subsidiary;
            
                if ($_SESSION["department"] != 'Equipment & Motors') {
                    $query['department'] = $_SESSION["department"];
                }
            }
        
            $countUsers = [];
            $countUser  = $users->find($query)->toArray();
            
            foreach ($countUser  as $techn) {
                array_push($countUsers, new MongoDB\BSON\ObjectId($techn['_id']));
            }
            
            return $countUsers;
        }
        
        // Compter les utilisateurs par niveau
        $countUsers = getUsersByLevel($usersColl, $_SESSION['subsidiary']);
        $countUsersJu = getUsersByLevel($usersColl, $_SESSION['subsidiary'], 'Junior');
        $countUsersSe = getUsersByLevel($usersColl, $_SESSION['subsidiary'], 'Senior');
        $countUsersEx = getUsersByLevel($usersColl, $_SESSION['subsidiary'], 'Expert');
        
        // Fonction pour recupérer les affectations terminés
        function getAllocation($allocations, $user, $level, $type, $activeManager = false) {
            $query = [
                'user' => new MongoDB\BSON\ObjectId($user),
                'level' => $level,
                'type' => $type,
                'active' => true
            ];
            if ($activeManager) {
                $query['activeManager'] = true;
            }
            return $allocations->findOne(['$and' => [$query]]);
        }
        
        // Fonction pour recupérer les affectations terminés
        function getAllocationTraining($allocations, $user, $type) {
            $query = [
                'user' => new MongoDB\BSON\ObjectId($user),
                'type' => $type,
                'active' => false
            ];
            return $allocations->findOne(['$and' => [$query]]);
        }

        // Fonction pour recupérer les tests terminés
        function getMeasuredUsers ($allocations, $technicians, $level) {
            $doneTest = [];
            foreach ($technicians as $tech) {
                $factuel = getAllocation($allocations, $tech, $level, 'Factuel');
                $declaratif = getAllocation($allocations, $tech, $level, 'Declaratif', true);

                if ($factuel && $declaratif) {
                    $doneTest[] = $tech;
                }
            }
            return count($doneTest);
        }
        
        // Compter les tests effectués par niveau
        $doneTestJu = getMeasuredUsers($allocationsColl, $countUsersJu, 'Junior');
        $doneTestSe = getMeasuredUsers($allocationsColl, $countUsersSe, 'Senior');
        $doneTestEx = getMeasuredUsers($allocationsColl, $countUsersEx, 'Expert');
        $doneTest = $doneTestJu + $doneTestSe + $doneTestEx;

        // Fonction pour recupérer les tests terminés
        function getUsersWithTraining ($allocations, $technicians) {
            $doneTraining = [];
            foreach ($technicians as $tech) {
                $training = getAllocationTraining($allocations, $tech, 'Training');
                if ($training) {
                    $doneTraining[] = $tech;
                }
            }
            return count($doneTraining);
        }

        //compter les techniciens avec des formations
        $techWithTraining = getUsersWithTraining($allocationsColl, $countUsers);
        
        // function pour recuperer les techniciens avec les formations selectionnées
        function getUsersWithTrainingSelected ($validations, $technicians) {
            $doneTraining = [];
            foreach ($technicians as $tech) {
                $techWithTrainingSelected = $validations->findOne([
                    'user' => new MongoDB\BSON\ObjectId($tech),
                    'status' => 'Validé',
                    'active' => true
                ]);
                if ($techWithTrainingSelected) {
                    $doneTraining[] = $techWithTrainingSelected;
                }
            }
            return count($doneTraining);
        }

        //compter les techniciens avec des formations sélectionnées
        $techWithTrainingSelected = getUsersWithTrainingSelected($validationsColl, $countUsers);

        class DataCollection
        {
            private $collectionManagers;
            private $collectionScores;
            private $result = [];

            public function __construct()
            {
                try {
                    $client = new MongoClient("mongodb://localhost:27017");
                    $database = $client->academy;
                    $this->collectionManagers = $database->managersBySubsidiaryAgency;
                    $this->collectionScores   = $database->technicianBrandScores;
                } catch (MongoException $e) {
                    throw $e;
                }
            }

            private function addScoreToAggregator(&$aggregator, $level, $brand, $avg)
            {
                $level = (string) $level;
                $brand = (string) $brand;
                if (!isset($aggregator[$level])) {
                    $aggregator[$level] = [];
                }
                if (!isset($aggregator[$level][$brand])) {
                    $aggregator[$level][$brand] = ['sum' => 0, 'count' => 0];
                }
                $aggregator[$level][$brand]['sum']   += $avg;
                $aggregator[$level][$brand]['count'] += 1;
            }

            private function mergeAggregatorsAccurate(&$dest, $source)
            {
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
                        $dest[$level][$brand]['sum']   += $vals['sum'];
                        $dest[$level][$brand]['count'] += $vals['count'];
                    }
                }
            }

            public static function finalizeAverages($aggregator, $withAllLevel = true)
            {
                $result = [];
                $allLevelAggregator = [];

                foreach ($aggregator as $level => $brandArr) {
                    foreach ($brandArr as $brand => $vals) {
                        $count = ($vals['count'] === 0) ? 1 : $vals['count'];
                        $avg   = round(($vals['sum'] / $count), 2);
                        $result[$level][$brand] = $avg;
                        if ($withAllLevel) {
                            if (!isset($allLevelAggregator[$brand])) {
                                $allLevelAggregator[$brand] = ['sum' => 0, 'count' => 0];
                            }
                            $allLevelAggregator[$brand]['sum']   += $vals['sum'];
                            $allLevelAggregator[$brand]['count'] += $vals['count'];
                        }
                    }
                }
                if ($withAllLevel) {
                    foreach ($allLevelAggregator as $brand => $vals) {
                        $count = ($vals['count'] === 0) ? 1 : $vals['count'];
                        $avg   = round(($vals['sum'] / $count), 2);
                        $result['ALL'][$brand] = $avg;
                    }
                }
                return $result;
            }

            private function buildHierarchy()
            {

                $cursor = $this->collectionManagers->find([]);
                foreach ($cursor as $document) {
                    $subsidiary = $document['subsidiary'] ?? 'Unknown';
                    $agencies   = $document['agencies']   ?? [];

                    if (!isset($this->result[$subsidiary])) {
                        $this->result[$subsidiary] = [
                            'agencies'   => [],
                            'aggregator' => []
                        ];
                    }
                    foreach ($agencies as $agency) {
                        $agencyName = $agency['_id'] ?? 'Unknown';
                        // Si un filtre d'agence est défini et que ce n'est pas l'agence courante, passer au suivant
                        // global $filterAgency;

                        // if ($filterAgency !== 'all' && strtolower($agencyName) !== strtolower($filterAgency)) {
                        //     continue;
                        // }

                        if (!isset($this->result[$subsidiary]['agencies'][$agencyName])) {
                            $this->result[$subsidiary]['agencies'][$agencyName] = [
                                'managers'   => [],
                                'aggregator' => []
                            ];
                        }
                        $managersArr = $agency['managers'] ?? [];
                        foreach ($managersArr as $manager) {
                            $managerName        = ($manager['firstName'] ?? '') . " " . ($manager['lastName'] ?? '');
                            $managerAggregator  = [];
                            $techniciansList    = [];

                            if (isset($manager['technicians'])) {
                                foreach ($manager['technicians'] as $technician) {
                                    $techId       = $technician['_id'] ?? null;
                                    $techName     = ($technician['firstName'] ?? '') . " " . ($technician['lastName'] ?? '');
                                    $brands       = isset($technician['distinctBrands'])
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
                                        'id'           => htmlspecialchars($techId ?? ''),
                                        'name'         => htmlspecialchars($techName ?? ''),
                                        'brands'       => $brands,
                                        'scoresLevels' => $scoresByLevel
                                    ];
                                }
                            }

                            // Ajouter 'id' au managerEntry
                            $managerEntry = [
                                'id'          => (string)($manager['_id'] ?? ''), // Ajout de l'ID du manager
                                'name'        => $managerName,
                                'technicians' => $techniciansList,
                                'aggregator'  => $managerAggregator
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
                            // On s’assure que le sous-tableau de la filiale possède un aggregator
                            if (isset($subData['aggregator']) && is_array($subData['aggregator'])) {
                                // On fusionne
                                $this->mergeAggregatorsAccurate($globalAggregator, $subData['aggregator']);
                            }
                        }

                        // On stocke l’aggregator global dans $this->result
                        // de manière à avoir un bloc "ALL_FILIALES"
                        $this->result['ALL_FILIALES'] = [
                            'aggregator' => $globalAggregator
                        ];
            }

            
            public function getFullData()
            {
                // 1) Construire la hiérarchie si ce n'est pas déjà fait
                if (empty($this->result)) {
                    $this->buildHierarchy();
                }
            
                // 2) Parcourir toutes les « filiales » (y compris ALL_FILIALES)
                foreach ($this->result as $subsidiary => &$subData) {
            
                    // a) Si on a un aggregator au niveau filiale (ou ALL_FILIALES), on calcule les averages
                    if (isset($subData['aggregator']) && is_array($subData['aggregator'])) {
                        $subData['averages'] = self::finalizeAverages($subData['aggregator'], true);
                    }
            
                    // b) Si ce n’est pas le bloc "ALL_FILIALES", alors on a potentiellement des agences + managers
                    if ($subsidiary !== 'ALL_FILIALES' && isset($subData['agencies']) && is_array($subData['agencies'])) {
                        foreach ($subData['agencies'] as &$agencyData) {
                            // Si l’agence possède un aggregator
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

        // 1) Récup filtres
        $selectedFiliale   = $_GET['filiale']     ?? 'all';
        $selectedAgence    = $_GET['agence']      ?? 'all';
        $selectedManagerId = $_GET['managerId']   ?? 'all';
        $selectedTechId    = $_GET['technicianId'] ?? 'all';
        $selectedLevel     = $_GET['level']       ?? 'all';
        $selectedBrand     = $_GET['brand']       ?? 'all';

        function passFilters($tech, $filterLevel, $filterBrand, $filterTechId): bool
        {
            // 1) Filtre sur l’ID du technicien
            if ($filterTechId !== 'all' && $tech['id'] !== $filterTechId) {
                return false;
            }

            // 2) Vérifier si son level réel (Ex: 'Expert') matche le $filterLevel
            //    ou s’il est « supérieur » hiérarchiquement.
            //    Cf. switch déjà présent :
            $lvlLower = strtolower($tech['level']);          // Ex: 'expert'
            $filterLevelLower = strtolower($filterLevel);    // Ex: 'senior'

            // Drapeau indiquant qu’on garde le tech ou non
            $includeByLevel = false;

            switch ($filterLevelLower) {
                case 'all':
                    // On inclut tous les techniciens
                    $includeByLevel = true;
                    break;
                case 'junior':
                    // Dans ton code actuel, “Junior” inclut en fait tout le monde
                    // (si tu veux limiter strictement aux Juniors, tu inverserais la logique)
                    $includeByLevel = true;
                    break;
                case 'senior':
                    // On inclut Senior et Expert
                    if (in_array($lvlLower, ['senior', 'expert'])) {
                        $includeByLevel = true;
                    }
                    break;
                case 'expert':
                    // On inclut seulement les Experts
                    if ($lvlLower === 'expert') {
                        $includeByLevel = true;
                    }
                    break;
            }

            if (!$includeByLevel) {
                return false;
            }

            // 3) Filtre par marque (si le user a choisi une marque précise)
            if ($filterBrand !== 'all') {
                $brandLower = strtolower($filterBrand);

                // Construire un tableau fusionné des marques accessibles
                // selon le niveau RÉEL du technicien (car un Expert a brandJunior + brandSenior + brandExpert).
                $junBrands = array_map('strtolower', $tech['brandJunior']  ?? []);
                $senBrands = array_map('strtolower', $tech['brandSenior']  ?? []);
                $expBrands = array_map('strtolower', $tech['brandExpert']  ?? []);

                // Par défaut
                $mergedBrands = $junBrands;

                if ($lvlLower === 'senior') {
                    $mergedBrands = array_unique(array_merge($junBrands, $senBrands));
                } elseif ($lvlLower === 'expert') {
                    $mergedBrands = array_unique(array_merge($junBrands, $senBrands, $expBrands));
                }

                // Vérifier que la marque filtrée est bien dans son ensemble de marques
                if (!in_array($brandLower, $mergedBrands)) {
                    return false;
                }
            }

            return true; // S’il passe toutes les étapes, on le garde
        }

        // ----------------------------------------------------------
        // 3) Récupération du "FullData" via DataCollection
        // ----------------------------------------------------------
        $dataCollection = new DataCollection();
        $fullData       = $dataCollection->getFullData();

        // ----------------------------------------------------------
        // 4) Récupérer la filiale depuis la session

        // ----------------------------------------------------------
        // $subsidiary = $_SESSION["subsidiary"] ?? null;
        // if (!$subsidiary) {
        //     echo "Informations de filiale manquantes (subsidiary).";
        //     exit();
        // }
        // // Si un filtre sur la filiale est envoyé en GET, on l'utilise (pour forcer l'affichage d'une autre filiale)
        // if (isset($_GET['filiale']) && $_GET['filiale'] !== 'all') {
        //     $subsidiary = trim($_GET['filiale']);
        // }
        // On récupère la valeur choisie en GET pour la filiale, "all" par défaut
        $selectedFiliale = $_GET['filiale'] ?? 'all';

        if (($profile === 'Directeur Groupe' || $profile === 'Super Admin') && $selectedFiliale === 'all') {
            // Pour le Directeur Groupe avec filiale=all, on souhaite une agrégation globale
            $subsidiary = null;
        } else {
            // Sinon, on part de la filiale en session
            $subsidiary = $_SESSION["subsidiary"] ?? null;
            if (!$subsidiary) {
                echo "Informations de filiale manquantes (subsidiary).";
                exit();
            }
            // Si l'utilisateur a sélectionné une filiale précise, on la prend
            if ($selectedFiliale !== 'all') {
                $subsidiary = trim($selectedFiliale);
            }
        }

        // Pour le filtre Agence, on récupère la valeur de GET s'il existe
        $filterAgency = (isset($_GET['agence']) && $_GET['agence'] !== 'all') ? trim($_GET['agence']) : 'all';
        // On prépare la liste de toutes les filiales en extrayant les clés de $fullData
        $allFiliales = array_keys($fullData);
        $filterBrand      = isset($_GET['brand'])        ? trim($_GET['brand']) : 'all';
        // ------------------ BLOC MANAGERS ------------------
        $managersList = [];
        if (isset($fullData[$subsidiary]) && isset($fullData[$subsidiary]['agencies'])) {
            foreach ($fullData[$subsidiary]['agencies'] as $agencyName => $agencyData) {
                // Filtrer par agence si défini
                if ($filterAgency !== 'all' && $agencyName !== $filterAgency) {
                    continue;
                }
                if (isset($agencyData['managers'])) {
                    foreach ($agencyData['managers'] as $manager) {
                        $mId   = $manager['id']   ?? null;
                        $mName = $manager['name'] ?? 'Manager Sans Nom';
                        // Vérifier que le manager possède au moins un technicien
                        if ($mId && !isset($managersList[$mId]) && !empty($manager['technicians'])) {
                            // Si un filtre de marque est sélectionné, on vérifie qu'au moins un technicien a cette marque
                            if ($filterBrand !== 'all') {
                                $hasBrand = false;
                                foreach ($manager['technicians'] as $technician) {
                                    // $technician['brands'] correspond au tableau issu de "distinctBrands"
                                    if (!empty($technician['brands']) && in_array($filterBrand, $technician['brands'])) {
                                        $hasBrand = true;
                                        break;
                                    }
                                }
                                if (!$hasBrand) {
                                    continue; // ce manager n’a aucun technicien avec la marque filtrée
                                }
                            }
                            $managersList[$mId] = $mName;
                        }
                    }
                }
            }
        }

        // **Nouvelle section : Initialiser $technicians**
        $technicians = []; // Initialisation par défaut

        if ($managerId === 'all') {
            if (isset($fullData[$subsidiary])) {
                $subsidiaryData = $fullData[$subsidiary];
                if (isset($subsidiaryData['agencies'])) {
                    foreach ($subsidiaryData['agencies'] as $agencyName => $agency) {
                        // Filtrer par agence si défini
                        if ($filterAgency !== 'all' && $agencyName !== $filterAgency) {
                            continue;
                        }
                        if (isset($agency['managers'])) {
                            foreach ($agency['managers'] as $manager) {
                                if (isset($manager['technicians'])) {
                                    foreach ($manager['technicians'] as $technician) {
                                        // Si un filtre de marque est appliqué, ne prendre que les techniciens qui ont cette marque
                                        if ($filterBrand !== 'all') {
                                            if (empty($technician['brands']) || !in_array($filterBrand, $technician['brands'])) {
                                                continue;
                                            }
                                        }
                                        $techId = $technician['id'] ?? '';
                                        $userDoc = null;
                                        if (!empty($techId)) {
                                            $userDoc = $usersColl->findOne([
                                                '_id' => new MongoDB\BSON\ObjectId($techId)
                                            ]);
                                        }
                                        $techLevel = $userDoc['level'] ?? 'Junior';
                                        if (!isset($technician['scoresLevels'])) {
                                            // Option 1 : Vous pouvez par exemple recréer 'scoresLevels' en fonction des scores (selon votre logique)
                                            $technician['scoresLevels'] = []; // ou calculer la valeur ici
                                        }
                                        $technicians[] = [
                                            'id'           => htmlspecialchars($techId),
                                            'name'         => htmlspecialchars($technician['name'] ?? ''),
                                            'level'        => htmlspecialchars($techLevel),
                                            'brands'       => !empty($technician['brands']) ? (array)$technician['brands'] : [],
                                            'brandJunior'  => isset($userDoc['brandJunior']) ? (array)$userDoc['brandJunior'] : [],
                                            'brandSenior'  => isset($userDoc['brandSenior']) ? (array)$userDoc['brandSenior'] : [],
                                            'brandExpert'  => isset($userDoc['brandExpert']) ? (array)$userDoc['brandExpert'] : [],
                                            'scoresLevels' => isset($technician['scoresLevels']) ? $technician['scoresLevels'] : []
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } else {
            // Cas où un manager spécifique est sélectionné
            if (isset($fullData[$subsidiary])) {
                $subsidiaryData = $fullData[$subsidiary];
                if (isset($subsidiaryData['agencies'])) {
                    foreach ($subsidiaryData['agencies'] as $agencyName => $agency) {
                        if ($filterAgency !== 'all' && $agencyName !== $filterAgency) {
                            continue;
                        }
                        if (isset($agency['managers'])) {
                            foreach ($agency['managers'] as $manager) {
                                if (isset($manager['id']) && $manager['id'] == $managerId) {

                                    if (isset($manager['technicians'])) {
                                        foreach ($manager['technicians'] as $technician) {
                                            if ($filterBrand !== 'all') {
                                                if (empty($technician['brands']) || !in_array($filterBrand, $technician['brands'])) {
                                                    continue;
                                                }
                                            }
                                            $techId = $technician['id'] ?? '';
                                            $userDoc = null;
                                            if (!empty($techId)) {
                                                $userDoc = $usersColl->findOne([
                                                    '_id' => new MongoDB\BSON\ObjectId($techId)
                                                ]);
                                            }
                                            $techLevel = $userDoc['level'] ?? 'Junior';
                                            if (!isset($technician['scoresLevels'])) {
                                                // Option 1 : Vous pouvez par exemple recréer 'scoresLevels' en fonction des scores (selon votre logique)
                                                $technician['scoresLevels'] = []; // ou calculer la valeur ici
                                            }
                                            $technicians[] = [
                                                'id'           => htmlspecialchars($techId),
                                                'name'         => htmlspecialchars($technician['name'] ?? ''),
                                                'level'        => htmlspecialchars($techLevel),
                                                'brands'       => !empty($technician['brands']) ? (array)$technician['brands'] : [],
                                                'brandJunior'  => isset($userDoc['brandJunior']) ? (array)$userDoc['brandJunior'] : [],
                                                'brandSenior'  => isset($userDoc['brandSenior']) ? (array)$userDoc['brandSenior'] : [],
                                                'brandExpert'  => isset($userDoc['brandExpert']) ? (array)$userDoc['brandExpert'] : [],
                                                'scoresLevels' => isset($technician['scoresLevels']) ? $technician['scoresLevels'] : []
                                            ];
                                        }
                                    }
                                    break; // sortir dès que le manager est trouvé
                                }
                            }
                        }
                    }
                }
            }
        }


        // Optionnel : Gérer le cas où aucun technicien n'est trouvé
        if (empty($technicians)) {
            // Vous pouvez choisir d'afficher un message, de désactiver le filtre, etc.
            // Par exemple :
            // echo "<p>Aucun technicien trouvé pour ce manager.</p>";
        }

        // ----------------------------------------------------------
        // 5) Construire le tableau $brandScores à partir de $fullData
        //    pour le 1er graphique (scores par marque)
        // ----------------------------------------------------------

        // Votre logique existante pour construire $brandScores

        // ----------------------------------------------------------
        // 6) Logique pour le 2ème graphique : Plans de Formations
        //    (via votre pipeline existant pour $managerId)
        // ----------------------------------------------------------
        // Seulement pour Super Admin et Directeur Filiale, autoriser le GET pour managerId
        if ($_SESSION["profile"] === 'Super Admin' || $_SESSION["profile"] === 'Directeur Général'|| $_SESSION["profile"] === 'Directeur Pièce et Service' || $_SESSION["profile"] === 'Directeur des Opérations' || $_SESSION["profile"] === 'Admin') {
            $managerId = isset($_GET['managerId']) ? $_GET['managerId'] : 'all';
        }
        // Pour les managers, on conserve l'ID de la session (déjà défini en amont)

        $filterLevel      = isset($_GET['level'])        ? trim($_GET['level']) : 'all';

        $filterTechnician = isset($_GET['technicianId']) ? trim($_GET['technicianId']) : 'all';
        // On initialise un tableau associatif pour collecter les niveaux détectés
        $foundLevels = [];

        // Déterminer le niveau maximum dans l'équipe (logique existante)
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

        // Construire la liste des niveaux pour le filtre pour Manager et Directeurs
        $levelsToShow = [];
        if ($maxLevel === 'Expert') {
            $levelsToShow = array_merge($levelsToShow, ['all', 'Junior', 'Senior', 'Expert']);
        } elseif ($maxLevel === 'Senior') {
            // Même si l’équipe affiche uniquement "Senior", on force l’inclusion de "Junior"
            $levelsToShow = array_merge($levelsToShow, ['all', 'Junior', 'Senior']);
        } else {
            $levelsToShow = array_merge($levelsToShow, ['all', 'Junior']);
        }

        // $levelsToShow ressemble maintenant à :
        // ["all", "Junior"]             si tous sont Juniors
        // ["all", "Junior", "Senior"]   s’il y a au moins un Senior
        // ["all", "Junior", "Senior", "Expert"] s’il y a un Expert, etc.

        // Construire le pipeline conditionnellement
        $pipeline2 = [];

        // Ajouter le match sur manager si managerId n'est pas 'all'
        if ($managerId === 'all') {
            // Cas TOUS LES MANAGERS de LA FILIALE
            if ($subsidiary === null) {
                // Directeur Groupe => toutes filiales
                $pipeline2[] = [
                    '$match' => [
                        'profile' => 'Manager',
                        // on ne met pas 'subsidiary'
                    ]
                ];
            } else {
                // Autre cas (Directeur Filiale ou Filiale précise)
                $pipeline2[] = [
                    '$match' => [
                        'profile'    => 'Manager',
                        'subsidiary' => $subsidiary
                    ]
                ];
            }
        } else {
            // Cas MANAGER SPÉCIFIQUE
            try {
                $managerObjectId = new MongoDB\BSON\ObjectId($managerId);
            } catch (MongoDB\Driver\Exception\InvalidArgumentException $e) {
                echo "managerId invalide: " . htmlspecialchars($e->getMessage());
                exit();
            }

            $pipeline2[] = [
                '$match' => [
                    '_id'     => $managerObjectId,
                    'profile' => 'Manager',
                    'subsidiary' => $subsidiary
                    // On peut aussi matcher 'subsidiary' => $subsidiary si on veut être sûr
                ]
            ];
        }
        // AJOUT DU FILTRE AGENCE POUR LE PIPELINE
        if ($filterAgency !== 'all') {
            $pipeline2[] = [
                '$match' => [
                    'agency' => $filterAgency
                ]
            ];
        }
        // Ajouter le lookup sur les subordinates
        $pipeline2[] = [
            '$lookup' => [
                'from' => 'users',
                'localField' => 'users',
                'foreignField' => '_id',
                'as' => 'subordinates'
            ]
        ];

        // Unwind les subordinates
        $pipeline2[] = [
            '$unwind' => '$subordinates'
        ];

        // Match seulement les techniciens
        $pipeline2[] = [
            '$match' => [
                'subordinates.profile' => 'Technicien'
            ]
        ];

        // Ajouter un match sur technicianId si nécessaire
        if ($filterTechnician !== 'all') {
            try {
                $filterTechnicianId = new MongoDB\BSON\ObjectId($filterTechnician);
                $pipeline2[] = [
                    '$match' => [
                        'subordinates._id' => $filterTechnicianId
                    ]
                ];
            } catch (MongoDB\Driver\Exception\InvalidArgumentException $e) {
                echo "technicianId invalide: " . htmlspecialchars($e->getMessage());
                exit();
            }
        }

        // Lookup sur les trainings
        $pipeline2[] = [
            '$lookup' => [
                'from' => 'trainings',
                'localField' => 'subordinates._id',
                'foreignField' => 'users',
                'as' => 'trainings'
            ]
        ];

        // Unwind les trainings
        $pipeline2[] = [
            '$unwind' => '$trainings'
        ];

        if ($filterBrand !== 'all') {
            $pipeline2[] = [
                '$match' => [
                    'trainings.brand' => $filterBrand
                ]
            ];
        }
        // Filtrer par level si nécessaire
        if ($filterLevel !== 'all') {
            $pipeline2[] = [
                '$match' => [
                    'trainings.level' => $filterLevel
                ]
            ];
        }

        // Juste avant le $facet :
        $brandAndLevelPipeline = [];

        if ($managerId === 'all' || $filterTechnician === 'all') {
            // CAS DISTINCT : group par trainingId
            $brandAndLevelPipeline = [
                // 1er group => group par brand/level/trainingId
                [
                    '$group' => [
                        '_id' => [
                            'brand'      => '$trainings.brand',
                            'level'      => '$trainings.level',
                            'trainingId' => '$trainings._id'
                        ],
                        'count' => ['$sum' => 1]
                    ]
                ],
                // 2e group => brand + level => on additionne
                [
                    '$group' => [
                        '_id' => [
                            'brand' => '$_id.brand',
                            'level' => '$_id.level'
                        ],
                        'count' => ['$sum' => 1]
                    ]
                ],
                // 3e group final
                [
                    '$group' => [
                        '_id' => '$_id.brand',
                        'totalByBrand' => ['$sum' => '$count'],
                        'totalByLevel' => [
                            '$push' => [
                                'k' => '$_id.level',
                                'v' => '$count'
                            ]
                        ]
                    ]
                ],
                [
                    '$addFields' => [
                        'totalByLevel' => ['$arrayToObject' => '$totalByLevel']
                    ]
                ],
                [
                    '$group' => [
                        '_id' => null,
                        'totalTrainingsByBrandAndLevel' => [
                            '$push' => [
                                'k' => '$_id',
                                'v' => [
                                    'totalByBrand' => '$totalByBrand',
                                    'totalByLevel' => '$totalByLevel'
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    '$addFields' => [
                        'totalTrainingsByBrandAndLevel' => [
                            '$arrayToObject' => '$totalTrainingsByBrandAndLevel'
                        ]
                    ]
                ],
                [
                    '$project' => [
                        '_id' => 0,
                        'totalTrainingsByBrandAndLevel' => 1
                    ]
                ]
            ];
        } else {
            // CAS NON-DISTINCT : group par brand/level, on compte TOUT
            $brandAndLevelPipeline = [
                // group par brand + level SANS trainingId
                [
                    '$group' => [
                        '_id' => [
                            'brand' => '$trainings.brand',
                            'level' => '$trainings.level'
                        ],
                        'count' => ['$sum' => 1]
                    ]
                ],
                // on n'a plus besoin du 1er regroupement distinct
                [
                    '$group' => [
                        '_id' => '$_id.brand',
                        'totalByBrand' => ['$sum' => '$count'],
                        'totalByLevel' => [
                            '$push' => [
                                'k' => '$_id.level',
                                'v' => '$count'
                            ]
                        ]
                    ]
                ],
                [
                    '$addFields' => [
                        'totalByLevel' => ['$arrayToObject' => '$totalByLevel']
                    ]
                ],
                [
                    '$group' => [
                        '_id' => null,
                        'totalTrainingsByBrandAndLevel' => [
                            '$push' => [
                                'k' => '$_id',
                                'v' => [
                                    'totalByBrand' => '$totalByBrand',
                                    'totalByLevel' => '$totalByLevel'
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    '$addFields' => [
                        'totalTrainingsByBrandAndLevel' => [
                            '$arrayToObject' => '$totalTrainingsByBrandAndLevel'
                        ]
                    ]
                ],
                [
                    '$project' => [
                        '_id' => 0,
                        'totalTrainingsByBrandAndLevel' => 1
                    ]
                ]
            ];
        }

        $pipeline2[] = [
            '$facet' => [
                'brandAndLevel' => $brandAndLevelPipeline,
                'totalOccurrences' => [
                    [
                        '$group' => [
                            '_id' => null,
                            'totalOccurrences' => ['$sum' => 1]
                        ]
                    ],
                    [
                        '$project' => [
                            '_id' => 0,
                            'totalOccurrences' => 1
                        ]
                    ]
                ]
            ]
        ];

        // Exécuter le pipeline
        try {
            $result2 = $usersColl->aggregate($pipeline2)->toArray();
            $document2 = $result2[0] ?? null;

            if ($document2) {
                // Aucune donnée trouvée
                $brandAndLevelArr = $document2['brandAndLevel'][0] ?? null;
                if ($brandAndLevelArr) {
                    $trainingsByBrandAndLevel = $brandAndLevelArr['totalTrainingsByBrandAndLevel'] ?? [];
                } else {
                    $trainingsByBrandAndLevel = [];
                }
                // Récupération de la partie totalOccurrences
                $occurrencesArr = $document2['totalOccurrences'][0] ?? [];
                $totalOccurrences = $occurrencesArr['totalOccurrences'] ?? 0;
            } else {
                // Pas de résultat => vides
                $trainingsByBrandAndLevel = [];
                $totalOccurrences = 0;
            }
            //     $trainingsByBrandAndLevel = [];
            // } else {
            //     $trainingsByBrandAndLevel = $document2['totalTrainingsByBrandAndLevel'] ?? [];
            // }
        } catch (MongoDB\Exception\Exception $e) {
            echo "Erreur lors du pipeline2 : " . htmlspecialchars($e->getMessage());
            exit();
        }

        // Reconstruire un simple tableau "marque" => "nombre total de formations"

        $trainingsCountsForGraph2 = [];


        // Calcul dynamique pour les autres filiales
        if ($filterLevel === 'all') {
            // totalByBrand
            foreach ($trainingsByBrandAndLevel as $brand => $data) {
                $trainingsCountsForGraph2[$brand] = (int)$data['totalByBrand'];
            }
        } else {
            // totalByLevel[$filterLevel]
            foreach ($trainingsByBrandAndLevel as $brand => $data) {
                $trainingsCountsForGraph2[$brand] = (int)($data['totalByLevel'][$filterLevel] ?? 0);
            }
        }

        // Construire le pipeline conditionnellement des trainings validates
        $query2 = [];

        // Ajouter le match sur manager si managerId n'est pas 'all'
        if ($managerId === 'all') {
            // Cas TOUS LES MANAGERS de LA FILIALE
            if ($subsidiary === null) {
                // Directeur Groupe => toutes filiales
                $query2[] = [
                    '$match' => [
                        'profile' => 'Manager',
                        // on ne met pas 'subsidiary'
                    ]
                ];
            } else {
                // Autre cas (Directeur Filiale ou Filiale précise)
                $query2[] = [
                    '$match' => [
                        'profile'    => 'Manager',
                        'subsidiary' => $subsidiary
                    ]
                ];
            }
        } else {
            // Cas MANAGER SPÉCIFIQUE
            try {
                $managerObjectId = new MongoDB\BSON\ObjectId($managerId);
            } catch (MongoDB\Driver\Exception\InvalidArgumentException $e) {
                echo "managerId invalide: " . htmlspecialchars($e->getMessage());
                exit();
            }

            $query2[] = [
                '$match' => [
                    '_id'     => $managerObjectId,
                    'profile' => 'Manager',
                    'subsidiary' => $subsidiary
                    // On peut aussi matcher 'subsidiary' => $subsidiary si on veut être sûr
                ]
            ];
        }
        // AJOUT DU FILTRE AGENCE POUR LE query
        if ($filterAgency !== 'all') {
            $query2[] = [
                '$match' => [
                    'agency' => $filterAgency
                ]
            ];
        }
        // Ajouter le lookup sur les subordinates
        $query2[] = [
            '$lookup' => [
                'from' => 'users',
                'localField' => 'users',
                'foreignField' => '_id',
                'as' => 'subordinates'
            ]
        ];

        // Unwind les subordinates
        $query2[] = [
            '$unwind' => '$subordinates'
        ];

        // Match seulement les techniciens
        $query2[] = [
            '$match' => [
                'subordinates.profile' => 'Technicien'
            ]
        ];

        // Ajouter un match sur technicianId si nécessaire
        if ($filterTechnician !== 'all') {
            try {
                $filterTechnicianId = new MongoDB\BSON\ObjectId($filterTechnician);
                $query2[] = [
                    '$match' => [
                        'subordinates._id' => $filterTechnicianId
                    ]
                ];
            } catch (MongoDB\Driver\Exception\InvalidArgumentException $e) {
                echo "technicianId invalide: " . htmlspecialchars($e->getMessage());
                exit();
            }
        }

        // Lookup sur les validations
        $query2[] = [
            '$lookup' => [
                'from' => 'validations',
                'localField' => 'subordinates._id',
                'foreignField' => 'user',
                'as' => 'validations'
            ]
        ];

        // Unwind les validations
        $query2[] = [
            '$unwind' => '$validations'
        ];

        // Match seulement les validées
        $query2[] = [
            '$match' => [
                'validations.status' => 'Validé'
            ]
        ];

        // Lookup sur les trainings
        $query2[] = [
            '$lookup' => [
                'from' => 'trainings',
                'localField' => 'validations.training',
                'foreignField' => '_id',
                'as' => 'trainings'
            ]
        ];

        // Unwind les trainings
        $query2[] = [
            '$unwind' => '$trainings'
        ];

        if ($filterBrand !== 'all') {
            $query2[] = [
                '$match' => [
                    'trainings.brand' => $filterBrand
                ]
            ];
        }
        // Filtrer par level si nécessaire
        if ($filterLevel !== 'all') {
            $query2[] = [
                '$match' => [
                    'trainings.level' => $filterLevel
                ]
            ];
        }

        // Juste avant le $facet :
        $brandAndLevelquery = [];

        if ($managerId === 'all' || $filterTechnician === 'all') {
            // CAS DISTINCT : group par trainingId
            $brandAndLevelquery = [
                // 1er group => group par brand/level/trainingId
                [
                    '$group' => [
                        '_id' => [
                            'brand'      => '$trainings.brand',
                            'level'      => '$trainings.level',
                            'trainingId' => '$trainings._id'
                        ],
                        'count' => ['$sum' => 1]
                    ]
                ],
                // 2e group => brand + level => on additionne
                [
                    '$group' => [
                        '_id' => [
                            'brand' => '$_id.brand',
                            'level' => '$_id.level'
                        ],
                        'count' => ['$sum' => 1]
                    ]
                ],
                // 3e group final
                [
                    '$group' => [
                        '_id' => '$_id.brand',
                        'totalByBrand' => ['$sum' => '$count'],
                        'totalByLevel' => [
                            '$push' => [
                                'k' => '$_id.level',
                                'v' => '$count'
                            ]
                        ]
                    ]
                ],
                [
                    '$addFields' => [
                        'totalByLevel' => ['$arrayToObject' => '$totalByLevel']
                    ]
                ],
                [
                    '$group' => [
                        '_id' => null,
                        'totalTrainingsByBrandAndLevel' => [
                            '$push' => [
                                'k' => '$_id',
                                'v' => [
                                    'totalByBrand' => '$totalByBrand',
                                    'totalByLevel' => '$totalByLevel'
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    '$addFields' => [
                        'totalTrainingsByBrandAndLevel' => [
                            '$arrayToObject' => '$totalTrainingsByBrandAndLevel'
                        ]
                    ]
                ],
                [
                    '$project' => [
                        '_id' => 0,
                        'totalTrainingsByBrandAndLevel' => 1
                    ]
                ]
            ];
        } else {
            // CAS NON-DISTINCT : group par brand/level, on compte TOUT
            $brandAndLevelquery = [
                // group par brand + level SANS trainingId
                [
                    '$group' => [
                        '_id' => [
                            'brand' => '$trainings.brand',
                            'level' => '$trainings.level'
                        ],
                        'count' => ['$sum' => 1]
                    ]
                ],
                // on n'a plus besoin du 1er regroupement distinct
                [
                    '$group' => [
                        '_id' => '$_id.brand',
                        'totalByBrand' => ['$sum' => '$count'],
                        'totalByLevel' => [
                            '$push' => [
                                'k' => '$_id.level',
                                'v' => '$count'
                            ]
                        ]
                    ]
                ],
                [
                    '$addFields' => [
                        'totalByLevel' => ['$arrayToObject' => '$totalByLevel']
                    ]
                ],
                [
                    '$group' => [
                        '_id' => null,
                        'totalTrainingsByBrandAndLevel' => [
                            '$push' => [
                                'k' => '$_id',
                                'v' => [
                                    'totalByBrand' => '$totalByBrand',
                                    'totalByLevel' => '$totalByLevel'
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    '$addFields' => [
                        'totalTrainingsByBrandAndLevel' => [
                            '$arrayToObject' => '$totalTrainingsByBrandAndLevel'
                        ]
                    ]
                ],
                [
                    '$project' => [
                        '_id' => 0,
                        'totalTrainingsByBrandAndLevel' => 1
                    ]
                ]
            ];
        }

        $query2[] = [
            '$facet' => [
                'brandAndLevel' => $brandAndLevelquery,
                'totalOccurrences' => [
                    [
                        '$group' => [
                            '_id' => null,
                            'totalOccurrences' => ['$sum' => 1]
                        ]
                    ],
                    [
                        '$project' => [
                            '_id' => 0,
                            'totalOccurrences' => 1
                        ]
                    ]
                ]
            ]
        ];

        // Exécuter le query
        try {
            $response = $usersColl->aggregate($query2)->toArray();
            $documentResp = $response[0] ?? null;

            if ($document2) {
                // Aucune donnée trouvée
                $brandAndLevelArr = $documentResp['brandAndLevel'][0] ?? null;
                if ($brandAndLevelArr) {
                    $validationsByBrandAndLevel = $brandAndLevelArr['totalTrainingsByBrandAndLevel'] ?? [];
                } else {
                    $validationsByBrandAndLevel = [];
                }
                // Récupération de la partie totalOccurrences
                $occurrencesArr = $documentResp['totalOccurrences'][0] ?? [];
                $totalOccurrencesValidations = $occurrencesArr['totalOccurrences'] ?? 0;
            } else {
                // Pas de résultat => vides
                $validationsByBrandAndLevel = [];
                $totalOccurrencesValidations = 0;
            }
            //     $trainingsByBrandAndLevel = [];
            // } else {
            //     $trainingsByBrandAndLevel = $documentResp['totalTrainingsByBrandAndLevel'] ?? [];
            // }
        } catch (MongoDB\Exception\Exception $e) {
            echo "Erreur lors du query2 : " . htmlspecialchars($e->getMessage());
            exit();
        }

        // Reconstruire un simple tableau "marque" => "nombre total de formations"

        $validationsCountsForGraph2 = [];


        // Calcul dynamique pour les autres filiales
        if ($filterLevel === 'all') {
            // totalByBrand
            foreach ($validationsByBrandAndLevel as $brand => $data) {
                $validationsCountsForGraph2[$brand] = (int)$data['totalByBrand'];
            }
        } else {
            // totalByLevel[$filterLevel]
            foreach ($validationsByBrandAndLevel as $brand => $data) {
                $validationsCountsForGraph2[$brand] = (int)($data['totalByLevel'][$filterLevel] ?? 0);
            }
        }

        // Exemple d'utilisation supplémentaire si nécessaire
        // Vous pouvez accéder à $trainingsCountsForGraph2 ici pour générer vos graphiques



        // Choisir la bonne tranche de data selon $filterLevel
        // if ($filterLevel === 'all') {
        //     $scoresArr = $fullData[$subsidiary]['averages']['ALL'] ?? [];
        // } else {
        //     // Ex : 'Junior', 'Senior', 'Expert'
        //     $scoresArr = $fullData[$subsidiary]['averages'][$filterLevel] ?? [];
        // }
        $scoresArr = [];

        if (($profile === 'Directeur Groupe' || $profile === 'Super Admin') && $selectedFiliale === 'all') {
            // On ne refait plus l’agrégation manuellement => on l’a déjà dans $fullData['ALL_FILIALES']
            if ($filterLevel === 'all') {
                $scoresArr = $fullData['ALL_FILIALES']['averages']['ALL'] ?? [];
            } else {
                $scoresArr = $fullData['ALL_FILIALES']['averages'][$filterLevel] ?? [];
            }
        //         var_dump($scoresArr);
        //    die();
        } else {
            // Cas filiale précise ou autre profil
            if ($filterLevel === 'all') {
                $scoresArr = $fullData[$subsidiary]['averages']['ALL'] ?? [];
            } else {
                $scoresArr = $fullData[$subsidiary]['averages'][$filterLevel] ?? [];
            }
        }

        $allTechnicians = $technicians; // par exemple, récupéré via DataCollection

        // Si les trois filtres sont spécifiés (différents de "all"), alors on applique passFilters

        // ----------------------------------------------------------
        // 7) Construire $scoresArr pour le 1er graphique (scores) 
        //    En tenant compte de $filterTechnician et $filterBrand
        // ----------------------------------------------------------
        // $scoresArr = [];

        // 1) Récupérer la filiale (subsidiary) dans $fullData
        if (isset($fullData[$subsidiary])) {
            // On peut décider de "fusionner" tous les techniciens du manager OU juste le "technicianFilter"
            // Option A : si $filterTechnician != 'all', ne garder que CE technicien
            // (On reconstruit un aggregator "à la main")
            $aggregator = [];

            foreach ($fullData[$subsidiary]['agencies'] as $agencyName => $agency) {
                // Filtrer par agence si défini
                if ($filterAgency !== 'all' && $agencyName !== $filterAgency) {
                    continue;
                }
                foreach ($agency['managers'] as $manager) {
                    if (isset($manager['id']) && ($managerId === 'all' || $manager['id'] == $managerId)) {
                        // c'est le manager ou tous les managers
                        foreach ($manager['technicians'] as $tech) {
                            // si $filterTechnician != 'all', skip si ce n'est pas le bon
                            if ($filterTechnician !== 'all') {
                                if ($filterTechnician !== $tech['id']) {
                                    continue;
                                }
                            }
                            // Filtre brand => skip si brand pas dans $tech['brands']
                            // (On fera un skip plus tard, ou on peut le faire ici)
                            foreach ($tech['scoresLevels'] as $lvl => $brandsArr) {
                                // Filtre level => skip si $filterLevel != 'all' et $lvl != $filterLevel
                                if ($filterLevel !== 'all' && $lvl !== $filterLevel) {
                                    continue;
                                }

                                foreach ($brandsArr as $bName => $avgVal) {
                                    // Filtre brand => si $filterBrand != 'all' && $bName != $filterBrand => skip
                                    if ($filterBrand !== 'all' && $bName !== $filterBrand) {
                                        continue;
                                    }
                                    // Sinon on ajoute
                                    if (!isset($aggregator[$lvl])) {
                                        $aggregator[$lvl] = [];
                                    }
                                    if (!isset($aggregator[$lvl][$bName])) {
                                        $aggregator[$lvl][$bName] = ['sum' => 0, 'count' => 0];
                                    }
                                    $aggregator[$lvl][$bName]['sum']   += $avgVal;
                                    $aggregator[$lvl][$bName]['count'] += 1;
                                }
                            }
                        }
                    }
                }
            }

            // Petite fonction locale pour calculer


            // Finalize aggregator
        
            $finalAverages = DataCollection::finalizeAverages($aggregator, true);
            // ex: $finalAverages['ALL'] = [ brand => avgScore, ... ]
            if (isset($finalAverages['ALL'])) {
                $scoresArr = $finalAverages['ALL'];
            }
        }

        // Maintenant on applique le filtre de marque
        // (si $filterBrand !== 'all', on ne garde QUE cette marque)
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
                'x'         => $brandName,
                'y'         => $avgScoreFloat,
                'fillColor' => $color
            ];
        }

        //     $pipelineDays = [];

        // // 1) Le match sur le manager
        // if ($managerId === 'all') {
        //     $pipelineDays[] = [
        //         '$match' => [
        //             'profile'    => 'Manager',
        //             'subsidiary' => $subsidiary
        //         ]
        //     ];
        // } else {
        //     $pipelineDays[] = [
        //         '$match' => [
        //             '_id'     => new MongoDB\BSON\ObjectId($managerId),
        //             'profile' => 'Manager',
        //             'subsidiary' => $subsidiary
        //         ]
        //     ];
        // }

        // // 2) Lookup, unwind subordinates
        // $pipelineDays[] = [
        //     '$lookup' => [
        //         'from'         => 'users',
        //         'localField'   => 'users',
        //         'foreignField' => '_id',
        //         'as'           => 'subordinates'
        //     ]
        // ];
        // $pipelineDays[] = [
        //     '$unwind' => '$subordinates'
        // ];
        // $pipelineDays[] = [
        //     '$match' => [
        //         'subordinates.profile' => 'Technicien'
        //     ]
        // ];

        // // 3) Si filterTechnician != 'all'
        // if ($filterTechnician !== 'all') {
        //     $pipelineDays[] = [
        //         '$match' => [
        //             'subordinates._id' => new MongoDB\BSON\ObjectId($filterTechnician)
        //         ]
        //     ];
        // }

        // // 4) Lookup trainings
        // $pipelineDays[] = [
        //     '$lookup' => [
        //         'from'         => 'trainings',
        //         'localField'   => 'subordinates._id',
        //         'foreignField' => 'users',
        //         'as'           => 'trainings'
        //     ]
        // ];
        // $pipelineDays[] = ['$unwind' => '$trainings'];

        // // 5) Filtrer brand, level si besoin
        // if ($filterBrand !== 'all') {
        //     $pipelineDays[] = [
        //         '$match' => ['trainings.brand' => $filterBrand]
        //     ];
        // }
        // if ($filterLevel !== 'all') {
        //     $pipelineDays[] = [
        //         '$match' => ['trainings.level' => $filterLevel]
        //     ];
        // }

        // // 6) Grouper pour compter TOUTES les occurrences
        // //    => 1 occurrence par (technicien, training) sans distinct
        // $pipelineDays[] = [
        //     '$group' => [
        //         '_id' => null,
        //         'totalOccurrences' => ['$sum' => 1]
        //     ]
        // ];

        // // 7) Projection
        // $pipelineDays[] = [
        //     '$project' => [
        //         '_id' => 0,
        //         'totalOccurrences' => 1
        //     ]
        // ];

        // // Exécuter
        // $resultDays = $usersColl->aggregate($pipelineDays)->toArray();

        // if (empty($resultDays)) {
        //     $totalOccurrences = 0;
        // } else {
        //     $totalOccurrences = $resultDays[0]['totalOccurrences'] ?? 0;
        // }

        // => numDays = totalOccurrences * 5

        // Fonction pour compter les formations réalisées
        function countCompletedTrainings($allocations, $trainings, array $technicianObjIds, array $levels, array $brands)
        {
            // Créer un tableau pour stocker les résultats
            $results = [];

            // Boucle à travers chaque technicien
            foreach ($technicianObjIds as $technicianObjId) {
                $filter = [
                    'users'  => $technicianObjId,
                    'brand'  => ['$in' => $brands],
                    'level'  => ['$in' => $levels],
                    'active' => true,
                ];
                
                // Trouver les données de formation pour le technicien actuel
                $trainingData = $trainings->findOne($filter);

                // Vérifier si des données de formation ont été trouvées
                if ($trainingData) {
                    $query = [
                        'user'  => $technicianObjId,
                        'training'  => new MongoDB\BSON\ObjectId($trainingData['_id']),
                        'type'  => 'Training',
                        'active' => true
                    ];
                    
                    // Récupérer les allocations pour le technicien actuel
                    $allocationsData = $allocations->find($query)->toArray();
                    
                    // Stocker le nombre d'allocations pour le technicien
                    $results[$technicianObjId] = count($allocationsData);
                }
            }

            return $results;
        }

        function getLevelsToInclude($level) {
            if ($level === 'Junior') return ['Junior'];
            if ($level === 'Senior') return ['Senior'];
            if ($level === 'Expert') return ['Expert'];
            if ($level === 'all') return ['Junior', 'Senior', 'Expert'];
        }
        $levels = getLevelsToInclude($selectedLevel);

        // ----------------------------------------------------------
        // 7) Préparer le HTML + Graphiques
        // ----------------------------------------------------------

        // Extraire le managerName pour l'affichage (facultatif)
        $managerDoc = ($managerId !== 'all') ? $usersColl->findOne(['_id' => new MongoDB\BSON\ObjectId($managerId)]) : null;
        $managerName = ($managerDoc && $managerId !== 'all') ? ($managerDoc['firstName'] . ' ' . $managerDoc['lastName']) : 'Tous les Managers';

        // Extraire la liste des marques via $brandScores
        // (pour l'affichage des logos éventuellement)
        $teamBrands = array_map(function ($item) {
            return isset($item['x']) ? $item['x'] : null;
        }, $brandScores);

        // Extraire la liste des marques
        $teamBrands = array_map(function ($bs) {
            return $bs['x'];
        }, $brandScores);
        $teamBrands = array_filter($teamBrands); // remove null
        // Extraction des marques selon le profil
        if ($profile !== 'Technicien') {
            // Pour Manager et directeurs, on ne prend que les marques ayant des scores pour le niveau sélectionné (si spécifié)
            $fullTeamBrands = [];
            if ($subsidiary === null) {
                // Parcourir toutes les filiales
                foreach ($fullData as $subData) {
                    if (isset($subData['agencies'])) {
                        foreach ($subData['agencies'] as $agency) {
                            if (isset($agency['managers'])) {
                                foreach ($agency['managers'] as $manager) {
                                    if (isset($manager['technicians'])) {
                                        foreach ($manager['technicians'] as $technician) {
                                            if (isset($technician['brands']) && is_array($technician['brands'])) {
                                                foreach ($technician['brands'] as $brandName) {
                                                    $trimB = trim($brandName);
                                                    if ($trimB !== '') {
                                                        $fullTeamBrands[$trimB] = true;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                // Cas classique : on parcourt uniquement la filiale sélectionnée
                if (isset($fullData[$subsidiary]['agencies'])) {
                    foreach ($fullData[$subsidiary]['agencies'] as $agency) {
                        if (isset($agency['managers'])) {
                            foreach ($agency['managers'] as $manager) {
                                if (isset($manager['technicians'])) {
                                    foreach ($manager['technicians'] as $technician) {
                                        if (isset($technician['brands']) && is_array($technician['brands'])) {
                                            foreach ($technician['brands'] as $brandName) {
                                                $trimB = trim($brandName);
                                                if ($trimB !== '') {
                                                    $fullTeamBrands[$trimB] = true;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            $allBrands = array_keys($fullTeamBrands);
            sort($allBrands);
        } else {
            // Pour un Technicien, on se base sur son document personnel.
            $allBrands = [];
            if (isset($technicianDoc['brandJunior'])) {
                foreach ($technicianDoc['brandJunior'] as $b) {
                    $bTrimmed = trim((string)$b);
                    if ($bTrimmed !== '') {
                        $allBrands[$bTrimmed] = true;
                    }
                }
            }
            if (isset($technicianDoc['brandSenior'])) {
                foreach ($technicianDoc['brandSenior'] as $b) {
                    $bTrimmed = trim((string)$b);
                    if ($bTrimmed !== '') {
                        $allBrands[$bTrimmed] = true;
                    }
                }
            }
            if (isset($technicianDoc['brandExpert'])) {
                foreach ($technicianDoc['brandExpert'] as $b) {
                    $bTrimmed = trim((string)$b);
                    if ($bTrimmed !== '') {
                        $allBrands[$bTrimmed] = true;
                    }
                }
            }
            $allBrands = array_keys($allBrands);
            sort($allBrands);
        }

        $allSubsidiaryBrands = []; // contiendra toutes les marques

        // Parcourt de la filiale pour récupérer toutes les marques
        if (isset($fullData[$subsidiary]['agencies'])) {
            foreach ($fullData[$subsidiary]['agencies'] as $agency) {
                if (isset($agency['managers'])) {
                    foreach ($agency['managers'] as $manager) {
                        if (isset($manager['technicians'])) {
                            foreach ($manager['technicians'] as $technician) {
                                // $technician['brands'] est censé lister les "distinctBrands" 
                                // faites un merge dans $allSubsidiaryBrands
                                if (!empty($technician['brands'])) {
                                    foreach ($technician['brands'] as $br) {
                                        $allSubsidiaryBrands[$br] = true;
                                        // (un simple array associatif pour éviter les doublons)
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // On extrait la liste finale :
        $allSubsidiaryBrands = array_keys($allSubsidiaryBrands);

        // Maintenant, $allSubsidiaryBrands contient toutes les marques potentiellement rencontrées.

        // Récupération du document du technicien
        $technicianDoc = $usersColl->findOne([
            '_id' => new MongoDB\BSON\ObjectId($technicianId),
            'profile' => 'Technicien'
        ]);
        $techLevel = $technicianDoc['level'] ?? 'Junior';

        if (!$technicianDoc) {
            // Si le technicien n'existe pas, on met en place des valeurs par défaut
            $brandsToShow = [];
            $techLevel = 'Junior';
        } else {
            // Récupération du niveau du technicien
            $techLevel = $technicianDoc['level'] ?? 'Junior';

            // Définir quels niveaux doivent être inclus dans la liste des marques
            // Par exemple, on considère qu'un technicien Senior a accès aux marques Junior et Senior.
            if ($techLevel === 'Junior') {
                $levels = ['Junior'];
            } elseif ($techLevel === 'Senior') {
                $levels = ['Junior', 'Senior'];
            } elseif ($techLevel === 'Expert') {
                $levels = ['Junior', 'Senior', 'Expert'];
            } else {
                $levels = ['Junior']; // cas par défaut
            }

            // Récupération des champs de marques dans le document
            $brandFieldJunior = $technicianDoc['brandJunior'] ?? [];
            $brandFieldSenior = $technicianDoc['brandSenior'] ?? [];
            $brandFieldExpert = $technicianDoc['brandExpert'] ?? [];

            if ($brandFieldJunior instanceof MongoDB\Model\BSONArray) {
                $brandFieldJunior = $brandFieldJunior->getArrayCopy();
            }
            if ($brandFieldSenior instanceof MongoDB\Model\BSONArray) {
                $brandFieldSenior = $brandFieldSenior->getArrayCopy();
            }
            if ($brandFieldExpert instanceof MongoDB\Model\BSONArray) {
                $brandFieldExpert = $brandFieldExpert->getArrayCopy();
            }

            // Construire la liste des marques en fonction des niveaux auxquels le technicien a accès
            $allBrandsInDoc = [];
            if ($filterLevel === 'all') {
                // On inclut tout
                $allBrandsInDoc = array_merge($allBrandsInDoc, $brandFieldJunior, $brandFieldSenior, $brandFieldExpert);
            } elseif ($filterLevel === 'Junior') {
                $allBrandsInDoc = array_merge($allBrandsInDoc, $brandFieldJunior);
            } elseif ($filterLevel === 'Senior') {
                // Par exemple, si Senior inclut Junior + Senior
                $allBrandsInDoc = array_merge($allBrandsInDoc, $brandFieldSenior);
            } elseif ($filterLevel === 'Expert') {
                // Par exemple, si Expert inclut Junior + Senior + Expert
                $allBrandsInDoc = array_merge($allBrandsInDoc, $brandFieldExpert);
            }

            // Maintenant on enlève les valeurs vides + on supprime les doublons
            $cleaned = [];
            foreach ($allBrandsInDoc as $b) {
                $bTrim = trim((string)$b);
                if ($bTrim !== '') {
                    // On l’ajoute
                    $cleaned[$bTrim] = true;
                }
            }
            // Convertir en array
            $allBrands = array_keys($cleaned);
            // On peut trier alphabétiquement
            sort($allBrands);
        }

        $maxLevel = 'Junior';

        // Selon le profil
        if ($profile === 'Technicien') {
            // On prend directement le niveau du technicien (déjà récupéré en BDD dans $tLevel)
            // $tLevel pourrait être "Junior", "Senior" ou "Expert".
            $maxLevel = $tLevel;
        } elseif ($profile === 'Manager') {
            // 1) On a déjà la liste des techniciens du manager dans $technicians (ou via $fullData).
            //    Pour rappel, $technicians[] = [ 'id' => ..., 'name' => ..., 'level' => ... ].

            $maxLevel = 'Junior'; // par défaut
            foreach ($technicians as $tech) {
                $levelTech = $tech['level'] ?? 'Junior';
                if ($levelTech === 'Expert') {
                    $maxLevel = 'Expert';
                    break; // on a trouvé Expert, on s’arrête
                } elseif ($levelTech === 'Senior' && $maxLevel !== 'Expert') {
                    // On met à jour $maxLevel à Senior
                    $maxLevel = 'Senior';
                    // on ne break pas, au cas où on trouve plus tard un Expert
                }
            }
        } elseif (in_array($profile, ['Directeur Général', 'Directeur Pièce et Service', 'Directeur des Opérations', 'Directeur Groupe', 'Super Admin','Admin'])) {
            // Même logique, mais sur tous les techniciens de la filiale (ou du groupe, etc.)
            // Par exemple, vous avez $fullData, il contient l’ensemble des managers/agences/techniciens de la filiale.
            // Vous parcourez tous les "technicians" et vous trouvez le plus haut niveau.
            // Ci-dessous un exemple simple :

            $maxLevel = 'Junior';
            if (($profile === 'Directeur Groupe' || $profile === 'Super Admin') && $selectedFiliale === 'all') {
                // CAS : on veut le maxLevel sur TOUTES les filiales
                foreach ($fullData as $uneFiliale => $subData) {
                    if (!isset($subData['agencies'])) continue;
                    foreach ($subData['agencies'] as $agencyData) {
                        if (!isset($agencyData['managers'])) continue;
                        foreach ($agencyData['managers'] as $mgr) {
                            if (!isset($mgr['technicians'])) continue;
                            foreach ($mgr['technicians'] as $tech) {
                                $lvl = getTechLevelFromUserId($tech['id']);
                                if ($lvl === 'Expert') {
                                    $maxLevel = 'Expert';
                                    break 4; // on sort des 4 boucles
                                } elseif ($lvl === 'Senior' && $maxLevel !== 'Expert') {
                                    $maxLevel = 'Senior';
                                    // on ne break pas => on continue pour éventuellement trouver un Expert
                                }
                            }
                        }
                    }
                }
            } else {
                $maxLevel = 'Junior';
                // CAS NORMAL : on se limite à une filiale précise
                if (isset($fullData[$subsidiary]['agencies'])) {
                    foreach ($fullData[$subsidiary]['agencies'] as $agencyData) {
                        if (!isset($agencyData['managers'])) continue;
                        foreach ($agencyData['managers'] as $mgr) {
                            if (!isset($mgr['technicians'])) continue;
                            foreach ($mgr['technicians'] as $tech) {
                                $lvl = getTechLevelFromUserId($tech['id']);
                                if ($lvl === 'Expert') {
                                    $maxLevel = 'Expert';
                                    break 3;
                                } elseif ($lvl === 'Senior' && $maxLevel !== 'Expert') {
                                    $maxLevel = 'Senior';
                                    // pas de break => on cherche potentiellement un Expert
                                }
                            }
                        }
                    }
                }
            }
        }
        if ($profile !== 'Technicien') {
            // Pour Manager et directeurs, n’inclure que les marques ayant des scores pour le niveau sélectionné
            $fullTeamBrands = [];
            foreach ($technicians as $t) {
                if ($filterLevel !== 'all') {
                    if (isset($t['scoresLevels'][$filterLevel]) && is_array($t['scoresLevels'][$filterLevel])) {
                        foreach ($t['scoresLevels'][$filterLevel] as $brandName => $score) {
                            $trimB = trim($brandName);
                            if ($trimB !== '') {
                                $fullTeamBrands[$trimB] = true;
                            }
                        }
                    }
                } else {
                    // Aucun filtre de niveau, on prend toutes les marques disponibles
                    if (isset($t['brands']) && is_array($t['brands'])) {
                        foreach ($t['brands'] as $brandName) {
                            $trimB = trim($brandName);
                            if ($trimB !== '') {
                                $fullTeamBrands[$trimB] = true;
                            }
                        }
                    }
                }
            }
            $allBrands = array_keys($fullTeamBrands);
            sort($allBrands);
        }


        // -------------------------------------------
        // Maintenant, on construit le tableau $levelsToShow
        // -------------------------------------------
        // if ($profile === 'Manager') {
        //     $availableLevels = [];
        //     foreach ($technicians as $tech) {
        //         $availableLevels[] = $tech['level'] ?? 'Junior';
        //     }
        //     $availableLevels = array_unique($availableLevels);
        //     // Définir l'ordre souhaité
        //     $orderedLevels = ['Junior', 'Senior', 'Expert'];
        //     $levelsToShow = [];
        //     $levelsToShow[] = 'all'; // Option "Tous"
        //     foreach ($orderedLevels as $level) {
        //         if (in_array($level, $availableLevels)) {
        //             $levelsToShow[] = $level;
        //         }
        //     }
        // } else {
        //     // Pour les autres profils, vous pouvez utiliser la logique existante
        //     $levelsToShow = [];
        //     $levelsToShow[] = 'all';
        //     $levelsToShow[] = 'Junior';
        //     if ($maxLevel === 'Senior') {
        //         $levelsToShow[] = 'Senior';
        //     }
        //     if ($maxLevel === 'Expert') {
        //         $levelsToShow[] = 'Expert';
        //     }
        // }

        function getFormationMessage($profile, $filterLevel, $filterBrand, $managerId, $filterTechnician, $filiale, $agence, $managerName, $technicianName)
        {
            $message = "";

            if ($profile === 'Technicien') {
                if ($filterLevel === 'all' && $filterBrand === 'all') {
                    $message = "Modules de Formation pour le Technicien, tous niveaux et toutes les marques.";
                } elseif ($filterLevel !== 'all' && $filterBrand !== 'all') {
                    $message = "Modules de Formation pour le Technicien de niveau " . htmlspecialchars($filterLevel) . " et marque " . htmlspecialchars($filterBrand) . ".";
                } elseif ($filterLevel !== 'all') {
                    $message = "Modules de Formation pour le Technicien de niveau " . htmlspecialchars($filterLevel) . ".";
                } else {
                    $message = "Modules de Formation pour le Technicien de marque " . htmlspecialchars($filterBrand) . ".";
                }
            } elseif ($profile === 'Manager') {
                if ($managerId === 'all') {
                    if ($filterTechnician === 'all') {
                        if ($filterLevel === 'all' && $filterBrand === 'all') {
                            $message = "Modules de Formation pour l'équipe de la filiale.";
                        } else {
                            $parts = [];
                            if ($filterLevel !== 'all') {
                                $parts[] = "niveau " . htmlspecialchars($filterLevel);
                            }
                            if ($filterBrand !== 'all') {
                                $parts[] = "marque " . htmlspecialchars($filterBrand);
                            }
                            $message = "Modules de Formation pour l'équipe de la filiale (" . implode(" et ", $parts) . ").";
                        }
                    } else {
                        $message = "Modules de Formation pour l'équipe de la filiale, ciblant le technicien " . htmlspecialchars($technicianName) . ".";
                        if ($filterLevel !== 'all' || $filterBrand !== 'all') {
                            $details = [];
                            if ($filterLevel !== 'all') {
                                $details[] = "niveau " . htmlspecialchars($filterLevel);
                            }
                            if ($filterBrand !== 'all') {
                                $details[] = "marque " . htmlspecialchars($filterBrand);
                            }
                            $message .= " (" . implode(" et ", $details) . ").";
                        }
                    }
                } else {
                    if ($filterTechnician === 'all') {
                        $parts = [];
                        if ($filterLevel !== 'all') {
                            $parts[] = "niveau " . htmlspecialchars($filterLevel);
                        }
                        if ($filterBrand !== 'all') {
                            $parts[] = "marque " . htmlspecialchars($filterBrand);
                        }
                        $message = "Modules de Formation pour l'équipe de " . htmlspecialchars($managerName);
                        if (!empty($parts)) {
                            $message .= " (" . implode(" et ", $parts) . ")";
                        }
                        $message .= ".";
                    } else {
                        $message = "Modules de Formation pour l'équipe de " . htmlspecialchars($managerName) . ", ciblant le technicien " . htmlspecialchars($technicianName);
                        if ($filterLevel !== 'all' || $filterBrand !== 'all') {
                            $details = [];
                            if ($filterLevel !== 'all') {
                                $details[] = "niveau " . htmlspecialchars($filterLevel);
                            }
                            if ($filterBrand !== 'all') {
                                $details[] = "marque " . htmlspecialchars($filterBrand);
                            }
                            $message .= " (" . implode(" et ", $details) . ")";
                        }
                        $message .= ".";
                    }
                }
            } elseif ($profile === 'Directeur Pièce et Service' || $profile === 'Directeur des Opérations') {
                if ($filterTechnician === 'all') {
                    $parts = [];
                    if ($filterLevel !== 'all') {
                        $parts[] = "niveau " . htmlspecialchars($filterLevel);
                    }
                    if ($filterBrand !== 'all') {
                        $parts[] = "marque " . htmlspecialchars($filterBrand);
                    }
                    $message = "Modules de Formation pour la filiale " . htmlspecialchars($filiale);
                    if ($managerId !== 'all') {
                        $message .= ", équipe de " . htmlspecialchars($managerName);
                    }
                    if (!empty($parts)) {
                        $message .= " (" . implode(" et ", $parts) . ")";
                    }
                    $message .= ".";
                } else {
                    $message = "Modules de Formation pour la filiale " . htmlspecialchars($filiale);
                    if ($managerId !== 'all') {
                        $message .= ", équipe de " . htmlspecialchars($managerName);
                    }
                    $message .= ", ciblant le technicien " . htmlspecialchars($technicianName);
                    if ($filterLevel !== 'all') {
                        $message .= " de niveau " . htmlspecialchars($filterLevel);
                    }
                    if ($filterBrand !== 'all') {
                        $message .= " et marque " . htmlspecialchars($filterBrand);
                    }
                    $message .= ".";
                }
            } elseif (($profile === 'Directeur Général')||($profile === 'Admin')) {
                if ($filterTechnician === 'all') {
                    $parts = [];
                    if ($filterLevel !== 'all') {
                        $parts[] = "niveau " . htmlspecialchars($filterLevel);
                    }
                    if ($filterBrand !== 'all') {
                        $parts[] = "marque " . htmlspecialchars($filterBrand);
                    }
                    $message = "Modules de Formation pour la filiale " . htmlspecialchars($filiale);
                    if ($agence !== 'all') {
                        $message .= " (Agence : " . htmlspecialchars($agence) . ")";
                    }
                    if ($managerId !== 'all') {
                        $message .= ", équipe de " . htmlspecialchars($managerName);
                    }
                    if (!empty($parts)) {
                        $message .= " (" . implode(" et ", $parts) . ")";
                    }
                    $message .= ".";
                } else {
                    $message = "Modules de Formation pour la filiale " . htmlspecialchars($filiale);
                    if ($agence !== 'all') {
                        $message .= " (Agence : " . htmlspecialchars($agence) . ")";
                    }
                    if ($managerId !== 'all') {
                        $message .= ", équipe de " . htmlspecialchars($managerName);
                    }
                    $message .= ", ciblant le technicien " . htmlspecialchars($technicianName);
                    if ($filterLevel !== 'all') {
                        $message .= " de niveau " . htmlspecialchars($filterLevel);
                    }
                    if ($filterBrand !== 'all') {
                        $message .= " et marque " . htmlspecialchars($filterBrand);
                    }
                    $message .= ".";
                }
            } elseif (($profile === 'Directeur Groupe')||($profile === 'Super Admin')) {
                // Pour le Directeur Groupe, le filtre technicien n'est pas disponible.
                if ($managerId === 'all') {
                    $parts = [];
                    if ($filterLevel !== 'all') {
                        $parts[] = "niveau " . htmlspecialchars($filterLevel);
                    }
                    if ($filterBrand !== 'all') {
                        $parts[] = "marque " . htmlspecialchars($filterBrand);
                    }
                    if ($filiale !== 'all') {
                        $message = "Modules de Formation pour le Groupe, Filiale : " . htmlspecialchars($filiale);
                    } else {

                        $message = "Modules de Formation pour le Groupe";
                    }
                    if ($agence !== 'all') {
                        $message .= " (Agence : " . htmlspecialchars($agence) . ")";
                    }
                    if (!empty($parts)) {
                        $message .= " (" . implode(" et ", $parts) . ")";
                    }
                    $message .= ".";
                } else {
                    $parts = [];
                    if ($filterLevel !== 'all') {
                        $parts[] = "niveau " . htmlspecialchars($filterLevel);
                    }
                    if ($filterBrand !== 'all') {
                        $parts[] = "marque " . htmlspecialchars($filterBrand);
                    }
                    $message = "Modules de Formation pour le Groupe, Filiale : " . htmlspecialchars($filiale);
                    if ($agence !== 'all') {
                        $message .= " (Agence : " . htmlspecialchars($agence) . ")";
                    }
                    $message .= ", équipe de " . htmlspecialchars($managerName);
                    if (!empty($parts)) {
                        $message .= " (" . implode(" et ", $parts) . ")";
                    }
                    $message .= ".";
                }
            } else {
                $message = "Modules de Formation pour l'ensemble des critères sélectionnés.";
            }

            return $message;
        }
        if ($_SESSION['profile'] === 'Directeur Groupe' || $_SESSION['profile'] === 'Super Admin' && $selectedFiliale === 'all') {
            $allGroupBrands = [];

            foreach ($fullData as $filialeName => $filialeData) {
                if (!isset($filialeData['agencies'])) {
                    continue;
                }
                foreach ($filialeData['agencies'] as $agencyName => $agencyData) {
                    if (!isset($agencyData['managers'])) {
                        continue;
                    }
                    foreach ($agencyData['managers'] as $manager) {
                        if (!isset($manager['technicians'])) {
                            continue;
                        }
                        foreach ($manager['technicians'] as $tech) {
                            // $tech['brands'] => array
                            if (!empty($tech['brands']) && is_array($tech['brands'])) {
                                foreach ($tech['brands'] as $brand) {
                                    $brandTrim = trim($brand);
                                    if ($brandTrim !== '') {
                                        $allGroupBrands[$brandTrim] = true;
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Convertir clés associatives en tableau
            $allGroupBrands = array_keys($allGroupBrands);
            // Trier
            sort($allGroupBrands);

            $teamBrands = $allGroupBrands;
        }
        
        // Comptabiliser la somme totale
        $numTrainings = array_sum($trainingsCountsForGraph2);
        $numCompleted = countCompletedTrainings($allocationsColl, $trainingsColl, $technicians, $levels, $teamBrands);
        $numDays = $totalOccurrences * 5;

        $agencyBrandsMapping = [];
        if (isset($fullData)) {
            // On boucle sur chaque filiale
            foreach ($fullData as $subsidiary => $data) {
                // On boucle sur chaque agence
                if (isset($data['agencies'])) {
                    foreach ($data['agencies'] as $agencyName => $agency) {
                        $brandsForAgency = [];
                        // On cherche les managers et leurs techniciens
                        if (isset($agency['managers'])) {
                            foreach ($agency['managers'] as $manager) {
                                if (isset($manager['technicians'])) {
                                    foreach ($manager['technicians'] as $tech) {
                                        // On récupère les marques associées à chaque technicien
                                        if (isset($tech['brands'])) {
                                            $brandsForAgency = array_merge($brandsForAgency, $tech['brands']);
                                        }
                                    }
                                }
                            }
                        }
                        // Supprimer les doublons et trier
                        $brandsForAgency = array_unique($brandsForAgency);
                        sort($brandsForAgency);
                        // On remplit le mapping "nomDeAgence" => "listeDeMarques"
                        $agencyBrandsMapping[$agencyName] = $brandsForAgency;
                    }
                }
            }
        }
        
        $countTeamBrands = count($teamBrands);
        
        $dataTrainings = [];
        $trainingSelected = [];
        $trainingTechSelected = [];
        if ($selectedTechId == 'all' && $selectedLevel == 'all' && $selectedBrand == 'all') {
            foreach ($technicians as $technician) {
                $trainingData = $trainingsColl->find([
                    'users' => new MongoDB\BSON\ObjectId($technician['id']),
                    'active' => true
                ])->toArray();
                foreach ($trainingData as $train) {
                    $dataTrainings[] = $train['_id'];
                }
                
                $techTrainingDatas = $applications->find([
                    'user' => new MongoDB\BSON\ObjectId($technician['id']),
                    'active' => true
                ])->toArray();
                foreach ($techTrainingDatas as $techTrainingData) {
                    if (isset($techTrainingData['period']) && isset($techTrainingData['year'])) {
                        $trainingSelected[] = $techTrainingData['training'];
                        $trainingTechSelected[] = $techTrainingData['user'];
                    }
                }
            }
        } else if ($selectedTechId == 'all') {
            // Ajouter des filtres en fonction des valeurs
            if ($selectedLevel != 'all') {
                $filter['level'] = $selectedLevel; // Ajouter le filtre de niveau
            }
    
            if ($selectedBrand != 'all') {
                $filter['brand'] = $selectedBrand; // Ajouter le filtre de marque
            }
            
            foreach ($technicians as $technician) {
                $filter['users'] = new MongoDB\BSON\ObjectId($technician['id']); // Ajouter le filtre du technicien
                $trainingData = $trainingsColl->find($filter)->toArray();
                
                foreach ($trainingData as $train) {
                    $dataTrainings[] = $train['_id'];
                }
            
                $techTrainingDatas = $applications->find([
                    'user' => new MongoDB\BSON\ObjectId($technician['id']),
                    'active' => true
                ])->toArray();
                foreach ($techTrainingDatas as $techTrainingData) {
                    if (isset($techTrainingData['period']) && isset($techTrainingData['year'])) {
                        $trainingSelected[] = $techTrainingData['training'];
                        $trainingTechSelected[] = $techTrainingData['user'];
                    }
                }
            }
        } else if ($selectedTechId != 'all') {
        // Ajouter des filtres en fonction des valeurs
        $filter['users'] = new MongoDB\BSON\ObjectId($selectedTechId); // Ajouter le filtre du technicien

        if ($selectedLevel != 'all') {
            $filter['level'] = $selectedLevel; // Ajouter le filtre de niveau
        }

        if ($selectedBrand != 'all') {
            $filter['brand'] = $selectedBrand; // Ajouter le filtre de marque
        }

        // Si un filtre technique est spécifié, récupérer les formations pour ce technicien uniquement
        $trainingData = $trainingsColl->find($filter)->toArray();
        foreach ($trainingData as $train) {
            $dataTrainings[] = $train['_id'];
        }
        
        $techTrainingDatas = $applications->find([
            'user' => new MongoDB\BSON\ObjectId($selectedTechId),
            'active' => true
        ])->toArray();
        foreach ($techTrainingDatas as $techTrainingData) {
            if (isset($techTrainingData['period']) && isset($techTrainingData['year'])) {
                $trainingSelected[] = $techTrainingData['training'];
                $trainingTechSelected[] = $techTrainingData['user'];
            }
        }
    }

    // Convertir le tableau associatif en tableau indexé
    $dataTrainings = array_unique($dataTrainings);
    $trainingSelected = array_unique($trainingSelected);
    }

    if ($_SESSION['profile'] == 'Super Admin' || $_SESSION['profile'] == 'Directeur Groupe') {
        if ($selectedFiliale === 'all') {
            $numTechniciens = 0;
            // On parcourt toutes les filiales dans $fullData
            foreach ($fullData as $subsidiaryName => $subData) {
                // Si tu as un agrégat global (par exemple 'ALL_FILIALES'), on le saute
                if ($subsidiaryName === 'ALL_FILIALES') {
                    continue;
                }
                if (isset($subData['agencies'])) {
                    foreach ($subData['agencies'] as $agencyData) {
                        if (isset($agencyData['managers'])) {
                            foreach ($agencyData['managers'] as $manager) {
                                if (isset($manager['technicians'])) {
                                    $numTechniciens += count($manager['technicians']);
                                }
                            }
                        }
                    }
                }
            }
        } else {
            // Pour une filiale spécifique, on garde la logique initiale
            $numTechniciens = count($technicians);
        }
    } else if ($_SESSION['profile'] != 'Technicien') {
        // Pour une filiale spécifique, on garde la logique initiale
        $numTechniciens = count($technicians);
    }

    // ---------------------------------------------------------
    // KPI : Nombre total de filiales (pour Super Admin / D. Groupe)
    // ---------------------------------------------------------
    $numFiliales = 0;

    if (in_array($_SESSION['profile'], ['Super Admin', 'Directeur Groupe'], true)) {
        if ($selectedFiliale === 'all') {
            // Toutes les clés de $fullData, hors agrégats spéciaux
            $clefsFiliales = array_filter(
                array_keys($fullData),
                function ($cle) {           // syntaxe anonym-fn 7.4 compatible
                    return $cle !== 'ALL_FILIALES' && $cle !== 'CFR';
                }
            );
            $numFiliales = count($clefsFiliales);
        } else {
            // L’utilisateur a filtré une filiale précise
            $numFiliales = 1;
        }
    }



?>
<?php include "./partials/header.php"; ?>

<style>
    /* Hide dropdown content by default */
    .card {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }

    /* --- Wrapper principal pour le carrousel de marques --- */
    .brand-scroll-wrapper {
        position: relative;
        /* Permet de positionner les flèches */
        padding: 0 3rem;
        /* Laisse de la place à gauche et à droite */
        overflow: hidden;
        /* Masque le débordement horizontal */
    }

    /* --- Conteneur horizontal scrollable pour les cartes --- */
    .horizontal-scroll-container {
        display: flex;
        flex-wrap: nowrap;
        /* Force une seule ligne */
        overflow-x: auto;
        /* Active le défilement horizontal */
        scroll-behavior: smooth;
        /* Défilement fluide */
        gap: 1rem;
        /* Espace entre les cartes */
        justify-content: center;
        /* Centre le contenu si pas assez large */
    }

    /* Masquer la scrollbar sur WebKit et Firefox */
    .horizontal-scroll-container::-webkit-scrollbar {
        display: none;
    }

    .horizontal-scroll-container {
        -ms-overflow-style: none;
        /* IE et Edge */
        scrollbar-width: none;
        /* Firefox */
    }

    /* --- Cartes (pour chaque marque) --- */
    .brand-card {
        flex: 0 0 auto;
        /* Empêche le rétrécissement */
        width: 18rem;
        /* Ajuste la largeur souhaitée */
    }

    /* --- Style général des cartes (effet hover) --- */
    .custom-card {
        border-radius: 15px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s, box-shadow 0.2s;
        cursor: pointer;
        border: none;
        background-color: #fff;
    }

    .custom-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 12px rgba(0, 0, 0, 0.2);
    }

    /* --- Boutons fléchés (gauche/droite) --- */
    .arrow-button {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        background: rgba(255, 255, 255, 0.8);
        /* Fond semi-transparent */
        border: none;
        font-size: 2rem;
        cursor: pointer;
        z-index: 2;
        width: 2.5rem;
        height: 2.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #198754;
        /* Couleur verte */
    }

    /* Position gauche/droite */
    #scrollLeft {
        left: 0;
    }

    #scrollRight {
        right: 0;
    }

    /* --- Logo de la marque --- */
    .brand-logo {
        width: 60px;
        height: 45px;
        margin-bottom: 0.5rem;
    }


    /* Hide dropdown content by default */
    .dropdown-content {
        display: none;
        margin-top: 25px;
        /* Adjust as needed */
        transition: opacity 0.3s ease, max-height 0.3s ease;
        /* Smooth transition for dropdown visibility */
    }

    /* Style the toggle button */
    .dropdown-toggle {
        background-color: #fff;
        color: white;
        border: none;
        padding: 10px 10px;
        cursor: pointer;
        display: flex;
        align-items: center;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        transition: background-color 0.3s ease, color 0.3s ease;
        /* Smooth transition for background and text color */

    }

    /* Scrollable Chart Container */
    .scrollable-chart-container {
        overflow-x: auto;
        white-space: nowrap;
        width: 100%;
        padding-bottom: 25px;
    }

    .scrollable-chart-container canvas {
        width: 100% !important;
        min-width: 800px;
    }

    .dropdown-toggle i {
        margin-left: 5px;
        font-size: 14px;
        /* Set a proper size for the icon */
        transition: transform 0.3s ease;
        /* Smooth rotation transition */
    }


    /* Ensure no extra content or pseudo-elements */
    .dropdown-toggle::before,
    .dropdown-toggle::after {
        content: none;
        /* Ensure no extra content or pseudo-elements */
    }

    .dropdown-toggle.open i {
        transform: rotate(180deg);
    }

    /* Optional: Style for better visibility */
    .title-and-cards-container {
        margin-bottom: 25px;
        /* Adjust as needed */
    }

    .dropdown-toggle:hover {
        background-color: #f0f0f0;
        /* Slightly darker background on hover */
        color: #333;
        /* Slightly darker text color on hover for better contrast */
    }

    /* Optional: Style for better visibility */
    .title-and-cards-container {
        margin-bottom: 25px;
        /* Adjust as needed */
    }

    /* Optional: Style for better visibility */
    .title-and-cards-container {
        margin-bottom: 20px;
        /* Adjust as needed */
    }

    /* Container for the card */
    .responsive-card {
        max-width: 100%;
        margin: 0 auto;
        padding: 1rem;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        border-radius: 8px;
        background-color: #fff;
    }

    /* Card body */
    .responsive-card-body {
        display: flex;
        align-items: center;
        padding: 1rem;
    }

    /* Card body inner */
    .responsive-card-body-inner {
        width: 100%;
        padding: 0;
    }

    /* Card header */
    .responsive-card-header {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        padding: 1rem;
    }

    /* Card title */
    .responsive-card-title {
        margin: 0;
        font-size: 1.5rem;
        line-height: 1.2;
    }

    /* Responsive adjustments for card header */
    @media (max-width: 768px) {
        .responsive-card-header {
            padding: 0.5rem;
        }

        .responsive-card-title {
            font-size: 1.25rem;
        }
    }

    /* Chart container */
    .responsive-chart-container {
        width: 100%;
        position: relative;
        /* Make sure canvas is positioned correctly */
    }

    /* Canvas styling */
    .responsive-chart-container canvas {
        width: 100% !important;
        /* Make canvas responsive */
        height: auto !important;
        /* Maintain aspect ratio */
    }

    /* Responsive adjustments for canvas */
    @media (max-width: 768px) {
        .responsive-card-body {
            padding: 0.5rem;
        }

        .responsive-card-title {
            font-size: 1.25rem;
        }
    }

    @media (max-width: 576px) {
        .responsive-card-title {
            font-size: 1rem;
        }
    }

    .title-and-cards-container {
        display: flex;
        align-items: center;
        /* Align items vertically in the center */
        justify-content: space-between;
        /* Space between title, line, and cards */
        padding: 10px;
        /* Optional: adds padding around the container */
    }

    .title-container {
        flex: 1;
        /* Allow title container to take up space */
    }

    .main-title {
        font-size: 18px;
        /* Adjust font size as needed */
        font-weight: 600;
        /* Bold title */
        text-align: left;
        /* Align text to the left */
        margin-left: 25px;
    }

    .dynamic-card-container {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        justify-content: center;
        /* Center cards horizontally */
        flex: 3;
        /* Allow card container to take up more space */
    }

    .dynamic-card-container .card {
        width: 250px;
        height: 300px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        background-color: #fff;
        border-radius: 8px;
        position: relative;
        /* for potential future use */
        /* Remove any other styles that might conflict with your existing cards */
    }

    .card-title {
        margin-bottom: 10px;
        text-align: center;
        font-size: 15px;
        font-weight: 600;
    }

    .card-canvas {
        width: 100%;
        /* Ensure canvas uses full width */
        height: 100%;
        /* Adjust height of the canvas for the doughnut chart */
        display: flex;
        justify-content: center;
        align-items: center;
        margin-bottom: 10px;
        /* Increased margin from the title */
    }

    .card-top-title {
        margin-top: 10px;
        /* Space between the top title and the chart */
        text-align: center;
        font-size: 14px;
        font-weight: bolder;
    }

    .card-secondary-top-title {
        margin-bottom: 5px;
        /* Space between the secondary top title and the chart */
        text-align: center;
        font-size: 12px;
        /* Adjust font size if needed */
        font-weight: bold;
        /* Slightly lighter weight for the Pourcentage complété : */
    }

    .plus-sign {
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3rem;
        /* Large size for visibility */
        color: #000;
        /* Adjust color if needed */
        position: relative;
        /* Allows movement relative to its normal position */
        /* top: 50px; */
        /* Moves the plus sign down by 100px */
        transition: transform 0.3s ease, color 0.3s ease;
        /* Smooth transitions for interactivity */
    }

    /* Optional: Hover effect for a modern touch */
    .plus-sign:hover {
        transform: scale(1.1);
        /* Slightly enlarges on hover */
        color: #007bff;
        /* Change color on hover for better visibility */
    }

    /* Styles pour les badges des marques */
    .badge-secondary {
        background-color: #6c757d;
    }

    .brand-list {
        display: flex;
        justify-content: space-between;
        /* Distribue les éléments avec un espace égal entre eux */
        flex-wrap: wrap;
        /* Permet aux éléments de passer à la ligne suivante si l'espace est insuffisant */
        padding: 1rem;
        border-radius: 0.25rem;
        width: 100%;
        /* Assure que le conteneur prend toute la largeur disponible */
        box-sizing: border-box;
        /* Inclut le padding dans la largeur totale */
    }

    .brand-item {
        flex: 1 1 150px;
        /* Les éléments peuvent grandir ou rétrécir, avec une largeur de base de 150px */
        text-align: center;
        margin: 0.5rem;
    }

    .brand-logo {
        width: 58px;
        height: 40px;
        margin-bottom: 0.5rem;
    }

    /* Responsive ajustements */
    @media (max-width: 768px) {
        .chart-title {
            font-size: 1rem;
        }

        .brand-logo {
            width: 40px;
            height: 30px;
        }
    }

    /* Conteneur des logos */
    #chart-container-logo-container,
    #mesure-container-logo-container {
        z-index: 10;
    }

    /* Ajustement des logos */
    #chart-container-logo-container img,
    #mesure-container-logo-container img {
        transition: transform 0.2s;
    }

    #chart-container-logo-container img:hover,
    #mesure-container-logo-container img:hover {
        transform: scale(1.1);
    }

    .brand-name {
        font-size: 0.9rem;
        font-weight: bold;
    }

    /* Autres styles personnalisés */
    .arrow {
        width: 20px;
        height: 20px;
        background: url('arrow.png') no-repeat;
        position: absolute;
        bottom: 10px;
        right: 10px;
        cursor: pointer;
        animation: pulse 2s infinite;
    }


    #chart-container-logo-container,
    #mesure-container-logo-container {
        z-index: 10;
    }

    /* Conteneur des logos */
    #chart-container-logo-container,
    #mesure-container-logo-container {
        z-index: 10;
    }

    /* Ajustement des logos */
    #chart-container-logo-container img,
    #mesure-container-logo-container img {
        transition: transform 0.2s;
    }

    #chart-container-logo-container img:hover,
    #mesure-container-logo-container img:hover {
        transform: scale(1.1);
    }

    /* Canvas des graphiques */
    canvas {
        /* width: 100% !important;
                height: auto !important; */
    }

    /* Scrollable Chart Container */
    .scrollable-chart-container {
        overflow-x: auto;
        white-space: nowrap;
        width: 100%;
        padding-bottom: 25px;
    }

    .scrollable-chart-container canvas {
        width: 100% !important;
        min-width: 800px;
    }

    /* Styles personnalisés pour Select2 */
    .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
        display: flex;
        align-items: center;
    }

    .select2-container--bootstrap4 .select2-results__option .badge {
        margin-left: 10px;
    }

    /* Conteneur horizontal scrollable */
    .horizontal-scroll-container {
        display: flex;
        flex-wrap: nowrap;
        /* Pour forcer une seule ligne */
        overflow-x: auto;
        /* Activer le défilement horizontal */
        scroll-behavior: smooth;
        /* Pour un défilement fluide */
        gap: 1rem;
        /* Espacement entre les éléments */
        padding: 0.5rem;
    }

    /* Optionnel : masquer la scrollbar (varie selon navigateurs) */
    .horizontal-scroll-container::-webkit-scrollbar {
        display: none;
    }

    .horizontal-scroll-container {
        -ms-overflow-style: none;
        /* IE et Edge */
        scrollbar-width: none;
        /* Firefox */
    }

    /* Pour les cartes, on s'assure qu'elles ne se réduisent pas */
    .brand-card {
        flex: 0 0 auto;
        width: 18rem;
        /* Ajustez la largeur souhaitée */
    }

    /* Styles personnalisés */
    /* Wrapper général pour le bloc de marques */
    .brand-scroll-wrapper {
        position: relative;
        /* Ajoute un padding pour laisser de la place aux flèches */
        padding: 0 3rem;
        overflow: hidden;
        /* Cache tout débordement */
    }

    /* Conteneur horizontal scrollable pour les cartes */
    .horizontal-scroll-container {
        display: flex;
        flex-wrap: nowrap;
        /* Forcer une seule ligne */
        overflow-x: auto;
        /* Activer le défilement horizontal */
        scroll-behavior: smooth;
        /* Défilement fluide */
        gap: 1rem;
        justify-content: center;
        /* Espacement entre les cartes */
        /* Pas besoin de padding ici, car il est géré par le wrapper */
    }

    /* Pour que la scrollbar ne soit pas visible */
    .horizontal-scroll-container::-webkit-scrollbar {
        display: none;
    }

    .horizontal-scroll-container {
        -ms-overflow-style: none;
        /* IE et Edge */
        scrollbar-width: none;
        /* Firefox */
    }

    /* Les cartes de marque : empêcher leur rétrécissement */
    .brand-card {
        flex: 0 0 auto;
        width: 18rem;
        /* Ajustez cette largeur selon vos besoins */
    }

    /* Boutons fléchés positionnés absolument par rapport au wrapper */
    .arrow-button {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        background: rgba(255, 255, 255, 0.8);
        /* Optionnel : fond semi-transparent */
        border: none;
        font-size: 2rem;
        cursor: pointer;
        z-index: 2;
        width: 2.5rem;
        /* Ajustez la taille */
        height: 2.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* Bouton gauche en haut à gauche */
    #scrollLeft {
        left: 0;
    }

    /* Bouton droit en haut à droite */
    #scrollRight {
        right: 0;
    }

    /* Style pour les flèches de navigation */
    .arrow-button {
        background: none;
        border: none;
        font-size: 2rem;
        cursor: pointer;
        color: #198754;
        /* Par exemple, couleur verte */
    }


</style>

<!--begin::Title-->
<title><?php echo $tableau ?> | CFAO Mobility Academy</title>
<!--end::Title-->

<!--begin::Content-->
<div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content">
    <?php if ($_SESSION["profile"] == 'Technicien') { ?>
        <!--begin::Toolbar-->
        <div class="toolbar" id="kt_toolbar">
            <div class=" container-fluid  d-flex flex-stack flex-wrap flex-sm-nowrap">
                <!--begin::Info-->
                <div class="d-flex flex-column align-items-start justify-content-center flex-wrap me-2">
                    <!--begin::Title-->
                    <h1 class="text-dark fw-bolder my-1 fs-1">
                        <?php echo $tableau ?>
                    </h1>
                    <h3 class="text-dark fw-bolder my-1 fs-1">
                        <?php echo "Présentation des Plans de Formations" ?>
                    </h3>
                    <!--end::Title-->
                </div>
                <!--end::Info-->
            </div>
        </div>
        <!--end::Toolbar-->
        <!--begin::Content-->
        <div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content">
            <!--begin::Post-->
            <div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
                <!--begin::Container-->
                <div class=" container-xxl ">
                    <!--begin::Row-->
                    <div class="row g-6 g-xl-9 mb-6 mb-xl-9">
                        <!--begin::Filtres -->
                        <div class="container my-4">
                            <div class="row g-3 align-items-center">
                                <!-- Filtre Level -->
                                <div class="col-md-6">
                                    <label for="level-filter" class="form-label d-flex align-items-center">
                                        <i class="bi bi-bar-chart-fill fs-2 me-2 text-warning"></i> Niveau
                                    </label>
                                    <select id="level-filter" class="form-select">
                                        <option value="all" <?php if ($levelFilter === 'all')
                                                    echo 'selected'; ?>>Tous les niveaux
                                        </option>
                                        <?php
                                                    // Ajuster la liste de niveaux possibles
                                                    $lvlsAvailable = [];
                                                    if ($tLevel === 'Expert') {
                                                        $lvlsAvailable = ['Junior', 'Senior', 'Expert'];
                                                    } elseif ($tLevel === 'Senior') {
                                                        $lvlsAvailable = ['Junior', 'Senior'];
                                                    } else {
                                                        $lvlsAvailable = ['Junior'];
                                                    }
                                                    foreach ($lvlsAvailable as $lvl) {
                                                        $selected = ($lvl === $levelFilter) ? 'selected' : '';
                                                        echo "<option value='" . htmlspecialchars($lvl) . "' $selected>" . htmlspecialchars($lvl) . "</option>";
                                                    }
                                                ?>
                                    </select>
                                </div>

                                <!-- Filtre Marques -->
                                <div class="col-md-6">
                                    <label for="brand-filter" class="form-label d-flex align-items-center">
                                        <i class="bi bi-car-front-fill fs-2 me-2 text-danger"></i> Marques
                                    </label>
                                    <select id="brand-filter" class="form-select">
                                        <option value="all" <?= ($filterBrand === 'all') ? 'selected' : '' ?>>Toutes les
                                            marques</option>
                                        <?php
                                                foreach ($allBrands as $b) {
                                                    $sel = (strcasecmp($filterBrand, $b) === 0) ? 'selected' : '';
                                                    echo "<option value=\"" . htmlspecialchars($b) . "\" $sel>" . htmlspecialchars($b) . "</option>";
                                                }
                                                ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <!--end::Filtres -->

                        <!--begin::Title-->
                        <div style="margin-top: 55px; margin-bottom : 25px">
                            <div>
                                <h6 class="text-dark fw-bold my-1 fs-2">
                                    <?php echo sprintf("%02d", count($brandsToShow)) . ' Marques sur lesquelles j\'interviens en Atelier'; ?>
                                </h6>


                            </div>
                        </div>
                        <!--end::Title-->

                        <!-- begin:: Marques du Technicien -->
                        <div class="text-center mb-4">
                            <div class="brand-scroll-wrapper">
                                <!-- Bouton flèche gauche -->
                                <button class="arrow-button" id="scrollLeft">&lsaquo;</button>

                                <!-- Conteneur scrollable pour les cartes de marques -->
                                <div id="brandContainer" class="horizontal-scroll-container">
                                    <?php
                                
                                            // Boucle sur le tableau des marques à afficher (par exemple, $teamBrands)
                                            foreach ($brandsToShow as $brand) {
                                                // Récupération du logo de la marque, avec une valeur par défaut
                                                $logoSrc = isset($brandLogos[$brand]) ? "../../public/images/" . $brandLogos[$brand] : "../../public/images/default.png";
                                            ?>
                                    <div class="card custom-card brand-card">
                                        <div class="card-body d-flex flex-column justify-content-center align-items-center">
                                            <img src="<?php echo $logoSrc; ?>"
                                                alt="<?php echo htmlspecialchars($brand); ?> Logo"
                                                class="img-fluid brand-logo">
                                            <!-- Vous pouvez ajouter ici le nom de la marque si nécessaire -->

                                        </div>
                                    </div>
                                    <?php
                                            }
                                            ?>
                                </div>

                                <!-- Bouton flèche droite -->
                                <button class="arrow-button" id="scrollRight">&rsaquo;</button>
                            </div>


                            <script>
                            document.getElementById('scrollLeft').addEventListener('click', function() {
                                document.getElementById('brandContainer').scrollBy({
                                    left: -300,
                                    behavior: 'smooth'
                                });
                            });

                            document.getElementById('scrollRight').addEventListener('click', function() {
                                document.getElementById('brandContainer').scrollBy({
                                    left: 300,
                                    behavior: 'smooth'
                                });
                            });
                            </script>
                        </div>
                        <!-- end::Marques du Technicien -->

                        <!--begin::Title-->
                        <div style="margin-top: 55px; margin-bottom : 25px">
                            <div>
                                <h6 class="text-dark fw-bold my-1 fs-2">
                                    <?php echo 'Mon Plan de Formation par Marque' ?>
                                </h6>
                            </div>
                        </div>
                        <!--end::Title-->
                        <!--begin::Col-->
                        <div class="col-md-6 col-lg-4 col-xl-2.5">
                            <!--begin::Card-->
                            <div class="card h-100 ">
                                <!--begin::Card body-->
                                <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                    <!--begin::Name-->
                                    <!--begin::Animation-->
                                    <i class="fas fa-book fs-2 text-primary mb-2"></i>
                                    <div
                                        class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                        <div class="min-w-70px" data-kt-countup="true"
                                            data-kt-countup-value="<?php echo count($numRecommended); ?>">
                                        </div>
                                    </div>
                                    <!--end::Animation-->
                                    <!--begin::Title-->
                                    <div class="fs-5 fw-bold mb-2">
                                        <?php echo $recommaded_training ?> </div>
                                    <!--end::Title-->
                                    <!--end::Name-->
                                </div>
                                <!--end::Card body-->
                            </div>
                            <!--end::Card-->
                        </div>
                        <!--end::Col-->
                        <!--begin::Col-->
                        <div class="col-md-6 col-lg-4 col-xl-2.5">
                            <!--begin::Card-->
                            <div class="card h-100 ">
                                <!--begin::Card body-->
                                <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                    <!--begin::Name-->
                                    <!--begin::Animation-->
                                    <i class="fas fa-book-open fs-2 text-success mb-2"></i>
                                    <div
                                        class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                        <div class="min-w-70px" data-kt-countup="true"
                                            data-kt-countup-value="<?php echo count($numCompleted) ?>">
                                        </div>
                                    </div>
                                    <!--end::Animation-->
                                    <!--begin::Title-->
                                    <div class="fs-5 fw-bold mb-2">
                                        <?php echo $apply_training ?> </div>
                                    <!--end::Title-->
                                    <!--end::Name-->
                                </div>
                                <!--end::Card body-->
                            </div>
                            <!--end::Card-->
                        </div>
                        <!--end::Col-->
                        <!--begin::Col-->
                        <div class="col-md-6 col-lg-4 col-xl-2.5">
                            <!--begin::Card-->
                            <div class="card h-100 ">
                                <!--begin::Card body-->
                                <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                    <!--begin::Name-->
                                    <!--begin::Animation-->
                                    <i class="fas fa-calendar-alt fs-2 text-warning mb-2"></i>
                                    <div
                                        class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                        <div class="min-w-70px" data-kt-countup="true"
                                            data-kt-countup-value="<?php echo $brandHoursMap ?>">
                                        </div>
                                    </div>
                                    <!--end::Animation-->
                                    <!--begin::Title-->
                                    <div class="fs-5 fw-bold mb-2">
                                        <?php echo $training_duration ?> </div>
                                    <!--end::Title-->
                                    <!--end::Name-->
                                </div>
                                <!--end::Card body-->
                            </div>
                            <!--end::Card-->
                        </div>
                        <!--end::Col-->

                        <!-- begin::Row -->
                        <div class="post fs-6 d-flex flex-column-fluid">
                            <!--begin::Container-->
                            <div class=" container-xxl ">
                                <!--begin::Layout Builder Notice-->
                                <div class="card mb-10">
                                    <div class="card-body d-flex align-items-center">
                                        <!--begin::Card body-->
                                        <div id="chart-container" class="responsive-chart-container">
                                            <canvas id="chartjs-container"></canvas>
                                        </div>
                                        <!--end::Card body-->
                                    </div>
                                </div>
                                <!--end::Layout Builder Notice-->
                            </div>
                            <!--end::Container-->
                        </div>
                        <!-- end::Row -->
                    </div>
                    <!--end::Container-->
                </div>
                <!--end::Container-->
            </div>
            <!--end::Post-->
        </div>
        <!--end::Content-->
    <?php } else { ?>
        <!--begin::Toolbar-->
        <div class="toolbar" id="kt_toolbar">
            <div class=" container-fluid  d-flex flex-stack flex-wrap flex-sm-nowrap">
                <!--begin::Info-->
                <div class="d-flex flex-column align-items-start justify-content-center flex-wrap me-2">
                    <!--begin::Title-->
                    <h1 class="text-dark fw-bolder my-1 fs-1">
                        <?php echo $tableau ?>
                    </h1>
                    <!--end::Title-->
                </div>
                <!--end::Info-->
            </div>
        </div>
        <!--end::Toolbar-->
        <!--begin::Content-->
        <div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content" style="margin-top: -10px">
            <!--begin::Post-->
            <div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
                <!--begin::Container-->
                <div class=" container-xxl ">
                    <!--begin::Row-->
                    <div class="row g-6 g-xl-9 mb-6 mb-xl-9">
                        <!--begin::Filtres -->
                        <div class="container my-4">
                            <div class="row g-3 align-items-center">
                                <?php if ($_SESSION["profile"] === 'Manager'): ?>
                                <input type="hidden" id="manager-hidden" value="<?= htmlspecialchars($_SESSION["id"]) ?>">
                                <?php endif; ?>

                                <!-- SECTION DES FILTRES -->
                                <?php if (in_array($_SESSION["profile"], ['Directeur Pièce et Service', 'Directeur des Opérations', 'Directeur Général','Admin'])): ?>
                                <?php
                                    // Récupérer la filiale sélectionnée
                                    $filialeSelected = isset($_GET['filiale']) && $_GET['filiale'] !== 'all'
                                        ? trim($_GET['filiale'])
                                        : ($_SESSION["subsidiary"] ?? '');
                                    // Calculer le nombre d'agences pour la filiale sélectionnée
                                    $agencesCount = 0;
                                    if (!empty($filialeSelected) && isset($fullData[$filialeSelected])) {
                                        $agences = $fullData[$filialeSelected]['agencies'] ?? [];
                                        $agencesCount = count($agences);
                                    }
                                ?>
                                <!-- Pour Directeur Filiale et Directeur Général Filiale : Filiale en lecture seule -->
                                <div class="row mb-4">
                                    <!-- Filiale (lecture seule) -->
                                    <div class="col-md-6">
                                        <label for="filiale-filter" class="form-label">
                                            <i class="fas fa-city me-2 fs-2 text-success"></i>Filiales
                                        </label>
                                        <input type="text" id="filiale-filter" class="form-control"
                                            value="<?= htmlspecialchars($_SESSION["subsidiary"] ?? ''); ?>" disabled>
                                    </div>
                                    <!-- Agence -->
                                    <div class="col-md-6">
                                        <label for="agence-filter" class="form-label">
                                            <i class="fas fa-building me-2 fs-2 text-dark"></i>Agences (<?= $agencesCount ?>)
                                        </label>
                                        <select id="agence-filter" class="form-select">
                                            <option value="all">Toutes les Agences (<?= $agencesCount ?>)</option>
                                            <?php
                                                        $filialeSelected = isset($_GET['filiale']) && $_GET['filiale'] !== 'all'
                                                            ? trim($_GET['filiale'])
                                                            : ($_SESSION["subsidiary"] ?? '');
                                                        if (!empty($filialeSelected) && isset($fullData[$filialeSelected])) {
                                                            $agences = $fullData[$filialeSelected]['agencies'] ?? [];
                                                            foreach ($agences as $agenceName => $agenceData) {
                                                                $selected = (isset($_GET['agence']) && $_GET['agence'] === $agenceName) ? 'selected' : '';
                                                                echo "<option value=\"" . htmlspecialchars($agenceName) . "\" $selected>" . htmlspecialchars($agenceName) . "</option>";
                                                            }
                                                        }
                                                        ?>
                                        </select>
                                    </div>
                                </div>
                                <?php elseif (in_array($_SESSION["profile"], ['Super Admin', 'Directeur Groupe'])): ?>
                                <!-- Pour Directeur Groupe : Filiale modifiable -->
                                <div class="row mb-4">
                                    <!-- Filiale -->
                                    <div class="col-md-6">
                                        <label for="filiale-filter" class="form-label">
                                            <i class="fas fa-city me-2 fs-2 text-success"></i>Filiales
                                        </label>
                                        <select id="filiale-filter" class="form-select">
                                            <?php
                                                        // Option pour toutes les filiales
                                                        $selectedAll = (isset($_GET['filiale']) && $_GET['filiale'] === 'all') ? 'selected' : '';
                                                        echo '<option value="all" ' . $selectedAll . '>Toutes les Filiales</option>';

                                                        // Boucle sur les filiales existantes
                                                        foreach ($allFiliales as $filiale) {
                                                            $selected = (isset($_GET['filiale']) && $_GET['filiale'] === $filiale) ? 'selected' : '';
                                                            if ($filiale === 'ALL_FILIALES' || $filiale === 'CFR') {
                                                                // On saute l'agrégat global
                                                                continue;
                                                            }
                                                            echo "<option value=\"" . htmlspecialchars($filiale) . "\" $selected>"
                                                                . htmlspecialchars($filiale) .
                                                                "</option>";
                                                        }
                                                    ?>
                                        </select>
                                    </div>
                                    <!-- Agence -->
                                    <div class="col-md-6">
                                        <label for="agence-filter" class="form-label">
                                            <i class="fas fa-building me-2 fs-2 text-dark"></i>Agences (<?= count($fullData[$selectedFiliale]['agencies'] ?? []) ?>)
                                        </label>

                                        <select id="agence-filter" class="form-select" <?php
                                        // Si on est Directeur Groupe ET que la filiale est "all", on désactive l'input
                                                    if ($selectedFiliale === 'all') {
                                                        echo 'disabled';
                                                }
                                                ?>>
                                            <option value="all">Toutes les Agences (<?= count($fullData[$selectedFiliale]['agencies'] ?? []) ?>)</option>
                                            <?php
                                                            // Afficher les agences en fonction de la filiale sélectionnée
                                                            // (Uniquement si $selectedFiliale != 'all', sinon on n’affiche pas)
                                                            if ($selectedFiliale !== 'all' && isset($fullData[$selectedFiliale])) {
                                                                $agences = $fullData[$selectedFiliale]['agencies'] ?? [];
                                                                foreach ($agences as $agenceName => $agenceData) {
                                                                    $selected = (isset($_GET['agence']) && $_GET['agence'] === $agenceName) ? 'selected' : '';
                                                                    echo "<option value=\"" . htmlspecialchars($agenceName) . "\" $selected>"
                                                                        . htmlspecialchars($agenceName) .
                                                                        "</option>";
                                                                }
                                                            }
                                                        ?>
                                        </select>
                                    </div>
                                </div>
                                <?php else: ?>
                                <?php if (isset($_GET['filiale']) && $_GET['filiale'] !== 'all'): ?>
                                <div class="row mb-4">
                                    <!-- Pour les autres profils, affichage du select si filiale spécifiée via GET -->
                                    <div class="col-md-6">
                                        <label for="filiale-filter" class="form-label">
                                            <i class="fas fa-city me-2 fs-2 text-success"></i>Filiales
                                        </label>
                                        <select id="filiale-filter" class="form-select">
                                            <?php
                                                foreach ($allFiliales as $filiale) {
                                                    $selected = ($_GET['filiale'] === $filiale) ? 'selected' : '';
                                                    if ($filiale != 'CFR') {
                                                        echo "<option value=\"" . htmlspecialchars($filiale) . "\" $selected>" . htmlspecialchars($filiale) . "</option>";
                                                    }
                                                }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php endif; ?>

                                <!-- Filtre Niveau -->
                                <div class="col-md-3 filter-col">
                                    <label class="form-label">
                                        <i class="fas fa-signal me-2 fs-2 text-warning"></i>Niveaux (4)
                                    </label>
                                    <?php if (in_array($_SESSION['profile'], [
                                                "Super Admin",
                                                "Manager",
                                                "Admin",
                                                "Directeur Pièce et Service",
                                                "Directeur des Opérations",
                                                "Directeur Groupe",
                                                "Directeur Général"
                                            ])): ?>
                                    <select id="level-filter" class="form-select" onchange="applyFilters()">
                                        <option value="all" <?= ($filterLevel === 'all') ? 'selected' : '' ?>>Tous les
                                            niveaux (4)</option>
                                        <?php foreach ($levelsToShow as $lvlOption): ?>
                                        <option value="<?= htmlspecialchars($lvlOption) ?>"
                                            <?= ($filterLevel === $lvlOption) ? 'selected' : '' ?>>
                                            <?= ($lvlOption === 'all') ? 'Tous les niveaux' : htmlspecialchars($lvlOption) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php endif; ?>
                                </div>

                                <!-- Filtre Marques -->
                                <div class="col-md-3 filter-col">
                                    <label class="form-label">
                                        <i class="fas fa-car me-2 fs-2 text-danger"></i>Marques (<?php echo $countTeamBrands; ?>)
                                    </label>
                                    <select id="brand-filter" class="form-select" onchange="applyFilters()">
                                        <option value="all" <?= ($filterBrand === 'all') ? 'selected' : '' ?>>Toutes les
                                            marques (<?php echo $countTeamBrands; ?>)</option>
                                        <?php
                                                foreach ($allBrands as $b) {
                                                    $sel = (strcasecmp($filterBrand, $b) === 0) ? 'selected' : '';
                                                    echo "<option value=\"" . htmlspecialchars($b) . "\" $sel>" . htmlspecialchars($b) . "</option>";
                                                }
                                                ?>
                                    </select>
                                </div>

                    

                                <!-- Filtre Techniciens -->
                                <div class="col-md-3">
                                    <label class="form-label">
                                        <i class="fas fa-user me-2 fs-2 text-info"></i>
                                        Techniciens (<?= $numTechniciens; ?>)
                                    </label>
                                    <select id="technician-filter" class="form-select">
                                        <!-- Option par défaut avec le count -->
                                        <option value="all" <?= ($filterTechnician === 'all') ? 'selected' : '' ?>>
                                            Tous les techniciens (<?= $numTechniciens; ?>)
                                        </option>
                                        <?php
                                            // Initialisation du compteur de techniciens affichés
                                            $techCount = 0;
                                            // Parcourir tous les techniciens et appliquer les filtres
                                            foreach ($technicians as $t):
                                                $include = false;
                                                // On convertit le niveau du technicien et le filtre en minuscules pour comparer
                                                    $tLevel = strtolower($t['level']);
                                                    $filterLevelLower = strtolower($filterLevel);

                                                    // 1. Filtrage par niveau
                                                    if ($filterLevelLower === 'all' || $filterLevelLower === 'junior') {
                                                        $include = true;
                                                    } elseif ($filterLevelLower === 'senior') {
                                                        // Pour Senior, on inclut ceux dont le niveau est "senior" ou "expert"
                                                        if (in_array($tLevel, ['senior', 'expert'])) {
                                                            $include = true;
                                                        }
                                                    } elseif ($filterLevelLower === 'expert') {
                                                        if ($tLevel === 'expert') {
                                                            $include = true;
                                                        }
                                                    }
            
                                            // 2. Filtrage par marque (comparaison insensible à la casse)
                                                    if ($filterBrand !== 'all' && $include) {
                                                        $filterBrandLower = strtolower($filterBrand);
                                                        // Pour déterminer le tableau de marques à utiliser, on procède en fonction du niveau filtré :
                                                        if ($filterLevelLower === 'all' || $filterLevelLower === 'junior') {
                                                            // On utilise le tableau global 'brands'
                                                            $brands = isset($t['brands']) ? $t['brands'] : [];
                                                        } elseif ($filterLevelLower === 'senior') {
                                                            if ($tLevel === 'senior') {
                                                                // Pour un technicien senior, on utilise uniquement 'brandSenior'
                                                                $brands = !empty($t['brandSenior']) ? $t['brandSenior'] : [];
                                                            } elseif ($tLevel === 'expert') {
                                                                // Pour un expert, on fusionne 'brandSenior' et 'brandExpert'
                                                                $brandsSenior = !empty($t['brandSenior']) ? $t['brandSenior'] : [];
                                                                $brandsExpert = !empty($t['brandExpert']) ? $t['brandExpert'] : [];
                                                                $brands = array_unique(array_merge($brandsSenior, $brandsExpert));
                                                            }
                                                        } elseif ($filterLevelLower === 'expert') {
                                                            // Pour un filtre "expert", on utilise uniquement le champ brandExpert
                                                            $brands = !empty($t['brandExpert']) ? $t['brandExpert'] : [];
                                                        }

                                                        // On transforme le tableau des marques en minuscules
                                                        $brandsLower = array_map('strtolower', $brands);
                                                        if (!in_array($filterBrandLower, $brandsLower)) {
                                                            $include = false;
                                                        }
                                                    }

                                                    if (!$include) continue;
            
                                                $techId = isset($t['id']) ? $t['id'] : (isset($t['_id']) ? (string)$t['_id'] : '');
                                                $techName = isset($t['name']) ? $t['name'] : ($t['firstName'] . ' ' . $t['lastName']);
                                                $techLevel = isset($t['level']) ? ucfirst(trim($t['level'])) : 'Junior';

                                            ?>
                                                <option value="<?= htmlspecialchars($techId) ?>"
                                                    data-level="<?= htmlspecialchars($techLevel) ?>"
                                                    <?= ($filterTechnician === $techId) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($techName) . ' (' . htmlspecialchars($techLevel) . ')' ?>
                                                </option>
                                                <?php
                                                    $techCount++;
                                                endforeach;
                                                    $numTechniciens = ($filterTechnician !== 'all') ? 1 : $techCount;
                                            ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <!--end::Filtres -->
                        <!--begin::Title-->
                        <div style="margin-top: 35px; margin-bottom : 25px">
                            <div>
                                <h6 class="text-dark fw-bold my-1 fs-2">
                                    <?php echo "Etat d'Avancement de la Mesure des Compétences et des Plans Individuels de Formation" ?>
                                </h6>
                            </div>
                        </div>
                        <!--end::Title-->
                        <!--begin::Col-->
                        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-5 g-3">
                            <div class="col-md-6 col-lg-4 col-xl-2-4">
                                <!--begin::Card-->
                                <div class="card h-100 ">
                                    <!--begin::Card body-->
                                    <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                        <!--begin::Name-->
                                        <!--begin::Animation-->
                                        <div
                                            class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                            <div class="min-w-70px" data-kt-countup="true"
                                                data-kt-countup-value="<?php echo count($countUsers) ?>">
                                            </div>
                                        </div>
                                        <!--end::Animation-->
                                        <!--begin::Title-->
                                        <div class="fs-5 fw-bold mb-2">
                                            <?php echo $technicienss.' '.$Subsidiary ?> </div>
                                        <!--end::Title-->
                                        <!--end::Name-->
                                    </div>
                                    <!--end::Card body-->
                                </div>
                                <!--end::Card-->
                            </div>
                            <!--end::Col-->
                            <!--begin::Col-->
                            <div class="col-md-6 col-lg-4 col-xl-2-4">
                                <!--begin::Card-->
                                <div class="card h-100 ">
                                    <!--begin::Card body-->
                                    <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                        <!--begin::Name-->
                                        <!--begin::Animation-->
                                        <div
                                            class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                            <div class="min-w-70px" data-kt-countup="true"
                                                data-kt-countup-value="<?php echo $doneTest ?>">
                                            </div>
                                        </div>
                                        <!--end::Animation-->
                                        <!--begin::Title-->
                                        <div class="fs-5 fw-bold mb-2">
                                            <?php echo $tech_mesure ?> </div>
                                        <!--end::Title-->
                                        <!--end::Name-->
                                    </div>
                                    <!--end::Card body-->
                                </div>
                                <!--end::Card-->
                            </div>
                            <!--end::Col-->
                            <!--begin::Col-->
                            <div class="col-md-6 col-lg-4 col-xl-2-4">
                                <!--begin::Card-->
                                <div class="card h-100 ">
                                    <!--begin::Card body-->
                                    <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                        <!--begin::Name-->
                                        <!--begin::Animation-->
                                        <div
                                            class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                            <div class="min-w-70px" data-kt-countup="true"
                                                data-kt-countup-value="<?php echo $techWithTraining ?>">
                                            </div>
                                        </div>
                                        <!--end::Animation-->
                                        <!--begin::Title-->
                                        <div class="fs-5 fw-bold mb-2">
                                            <?php echo $tech_pif ?> </div>
                                        <!--end::Title-->
                                        <!--end::Name-->
                                    </div>
                                    <!--end::Card body-->
                                </div>
                                <!--end::Card-->
                            </div>
                            <!--end::Col-->
                            <!--begin::Col-->
                            <div class="col-md-6 col-lg-4 col-xl-2-4">
                                <!--begin::Card-->
                                <div class="card h-100 ">
                                    <!--begin::Card body-->
                                    <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                        <!--begin::Name-->
                                        <!--begin::Animation-->
                                        <div
                                            class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                            <div class="min-w-70px" data-kt-countup="true"
                                                data-kt-countup-value="<?php echo $techWithTrainingSelected ?>">
                                            </div>
                                        </div>
                                        <!--end::Animation-->
                                        <!--begin::Title-->
                                        <div class="fs-5 fw-bold mb-2">
                                            <?php echo $pif_filiale ?> </div>
                                        <!--end::Title-->
                                        <!--end::Name-->
                                    </div>
                                    <!--end::Card body-->
                                </div>
                                <!--end::Card-->
                            </div>
                            <!--end::Col-->
                            <?php if (in_array($_SESSION['profile'], ['Super Admin', 'Directeur Groupe'], true)): ?>
                            <!--begin::Col-->
                            <div class="col-md-6 col-lg-4 col-xl-2-4">
                                <div class="card h-100">
                                    <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                            

                                        <div class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center">
                                            <div class="min-w-70px"
                                                data-kt-countup="true"
                                                data-kt-countup-value="<?php echo $numFiliales; ?>">
                                            </div>
                                        </div>

                                        <div class="fs-5 fw-bold mb-2">Filiales Couvertes</div>
                                    </div>
                                </div>
                            </div>
                            <!--end::Col-->
                            <?php endif; ?>
                        </div>

                        <!-- begin::Row -->
                        <div>
                            <div id="chartTraining" class="row justify-content-center mt-4">
                                <!-- Dynamic cards will be appended here -->
                            </div>
                        </div>
                        <!-- endr::Row -->

                        <!--begin::Title-->
                        <div style="margin-top: 55px; margin-bottom : 25px">
                            <div>
                                <h6 class="text-dark fw-bold my-1 fs-2">
                                    <?php echo 'Proposition des Plans de Formations par l\'Academy' ?>
                                </h6>
                            </div>
                        </div>
                        <!--end::Title-->
                        <!--begin::Col-->
                        <div class="col-md-6 col-lg-6 col-xl-2.5">
                            <!--begin::Card-->
                            <div class="card h-100 ">
                                <!--begin::Card body-->
                                <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                    <!--begin::Name-->
                                    <!--begin::Animation-->
                                    <i class="fas fa-book fs-2 text-primary mb-2"></i>
                                    <div
                                        class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                        <div class="min-w-70px" data-kt-countup="true" data-kt-countup-separator=" "
                                            data-kt-countup-value="<?php echo (int)$numTrainings; ?>">
                                        </div>
                                    </div>
                                    <!--end::Animation-->
                                    <!--begin::Title-->
                                    <div class="fs-5 fw-bold mb-2">
                                        <?php echo $recommaded_training ?> </div>
                                    <!--end::Title-->
                                    <!--end::Name-->
                                </div>
                                <!--end::Card body-->
                            </div>
                            <!--end::Card-->
                        </div>
                        <!--end::Col-->
                        <!--begin::Col-->
                        
                        <!--end::Col-->
                        <!--begin::Col-->
                        <div class="col-md-6 col-lg-6 col-xl-2.5">
                            <!--begin::Card-->
                            <div class="card h-100 ">
                                <!--begin::Card body-->
                                <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                    <!--begin::Name-->
                                    <!--begin::Animation-->
                                    <i class="fas fa-calendar-alt fs-2 text-warning mb-2"></i>
                                    <div
                                        class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                        <div class="min-w-70px" data-kt-countup="true" data-kt-countup-separator=" "
                                            data-kt-countup-value="<?php echo (int)$numDays; ?>">
                                        </div>
                                    </div>
                                    <!--end::Animation-->
                                    <!--begin::Title-->
                                    <div class="fs-5 fw-bold mb-2">
                                        <?php echo $training_duration ?> </div>
                                    <!--end::Title-->
                                    <!--end::Name-->
                                </div>
                                <!--end::Card body-->
                            </div>
                            <!--end::Card-->
                        </div>
                        <!--end::Col-->

                        <!-- begin::Row -->
                        <div class="post fs-6 d-flex flex-column-fluid">
                            <!--begin::Container-->
                            <div class=" container-xxl ">
                                <!--begin::Layout Builder Notice-->
                                <div class="card mb-10">
                                    <div class="card-body d-flex align-items-center">
                                        <!--begin::Card body-->
                                        <div class="scrollable-chart-container">
                                            <canvas id="trainingsScatterCanvas"></canvas>
                                        </div>
                                        <!--end::Card body-->
                                    </div>
                                </div>
                                <!--end::Layout Builder Notice-->
                            </div>
                            <!--end::Container-->
                        </div>
                        <!-- end::Row -->
                    </div>
                    <!--end::Row-->
                </div>
                <!--end::Container-->
            </div>
            <!--end::Post-->
        </div>
        <!--end::Content-->
    <?php } ?>
</div>
<!--end::Content-->
<?php } ?>
<?php include "./partials/footer.php"; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/apexcharts/3.50.0/apexcharts.min.js"
    integrity="sha512-h3DSSmgtvmOo5gm3pA/YcDNxtlAZORKVNAcMQhFi3JJgY41j9G06WsepipL7+l38tn9Awc5wgMzJGrUWaeUEGA=="
    crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<!-- Inclure les CDNs des bibliothèques de graphiques -->
<!-- Chart.js CDN -->
<!-- Chart.js Plugin -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom@1.2.0"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- Select2 Bootstrap Theme (Optionnel) -->
<link href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.5.2/dist/select2-bootstrap4.min.css"
    rel="stylesheet" />

<?php if ($_SESSION['profile'] == 'Technicien') { ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var levelFilter = document.getElementById('level-filter');
            var brandFilter = document.getElementById('brand-filter');

            function getParameterByName(name) {
                const urlParams = new URLSearchParams(window.location.search);
                return urlParams.get(name) || 'all';
            }

            // Initialiser les filtres au chargement
            var selectedBrand = getParameterByName('brand') || 'all';
            var selectedLevel = getParameterByName('level') || 'all';

            brandFilter.value = selectedBrand;
            levelFilter.value = selectedLevel;

            // Appliquer les filtres lorsque le niveau ou le manager change
            brandFilter.addEventListener('change', applyFilters);
            levelFilter.addEventListener('change', applyFilters);


            function applyFilters() {
                var brand = brandFilter.value;
                var level = levelFilter.value;

                var params = new URLSearchParams();

                params.append('brand', brand);
                params.append('level', level);

                // Recharger la page avec les nouveaux paramètres
                window.location.search = params.toString();
            }
        });

        // Enregistrer le plugin Chart.js Datalabels
        Chart.register(ChartDataLabels);

        const variablesPHPTech = {
            numRecommended: <?php echo json_encode($numRecommended); ?>,
            numCompleted: <?php echo json_encode($numCompleted); ?>,
            totalDuration: <?php echo json_encode($totalDuration); ?>,
            brandScores: <?php echo json_encode($brandScores); ?>,
            brandFormationsMap: <?php echo json_encode($brandFormationsMap); ?>, // Ajouté
            brandHoursMap: <?php echo json_encode($brandHoursMap); ?> // Ajouté
        };
        // Afficher les variables dans la console du navigateur
        console.log("Variables PHP dans JS:", variablesPHPTech);

        document.addEventListener('DOMContentLoaded', function() {
            // Récupérer les données PHP
            const brandScoresData = variablesPHPTech.brandScores;
            const brandLogos = <?php echo json_encode($brandLogos); ?>;
            const brandFormationsMap = variablesPHPTech.brandFormationsMap;
            const brandHoursMap = variablesPHPTech.brandHoursMap;

            const labels = brandScoresData.map(d => d.x); // Noms des marques
            console.log(labels);
            const dataValues = brandScoresData.map(d => d.y); // Scores
            const colors = brandScoresData.map(d => d.fillColor); // Couleurs des cercles
            const urls = brandScoresData.map(d => d.url || '#'); // URLs pour les clics

            // Fonction pour dessiner les logos
            function drawLogos(chart, containerId, specificLabels) {
                // Supprimer les anciens conteneurs de logos
                const oldDiv = document.getElementById(containerId + '-logo-container');
                if (oldDiv) oldDiv.remove();

                // Créer un conteneur DIV pour les logos
                const logoContainer = document.createElement('div');
                logoContainer.id = containerId + '-logo-container';
                logoContainer.style.position = 'absolute';
                logoContainer.style.top = '0';
                logoContainer.style.left = '0';
                logoContainer.style.width = '100%';
                logoContainer.style.height = '100%';
                logoContainer.style.pointerEvents = 'none'; // Permettre les événements de souris à travers

                // Obtenir les échelles du graphique
                const xScale = chart.scales.x;
                const chartArea = chart.chartArea;

                // Boucler sur les labels spécifiques pour placer les logos
                specificLabels.forEach((label, index) => {
                    const xPos = xScale.getPixelForValue(index);
                    let yPos;

                    if (containerId === 'chart-container') {
                        yPos = chartArea.bottom + 15; // Ajuster selon les besoins
                    } else if (containerId === 'mesure-container') {
                        yPos = chartArea.bottom + 15; // Ajuster selon les besoins
                    } else {
                        yPos = chartArea.bottom + 15; // Valeur par défaut
                    }

                    // Créer l'élément image
                    const img = document.createElement('img');
                    img.src = brandLogos[label] ? `../../public/images/${brandLogos[label]}` :
                        `../../public/images/default.png`;
                    img.style.position = 'absolute';
                    img.style.left = (xPos - 25) + 'px'; // Centrer l'image (ajusté pour 50px de largeur)
                    img.style.top = yPos + 'px';
                    img.style.width = '55px';
                    img.style.height = '30px';
                    img.onerror = function() {
                        console.error(`Erreur de chargement de l'image : ${img.src}`);
                        img.src = '../../public/images/default.png';
                    };

                    // Ajouter l'image au conteneur
                    logoContainer.appendChild(img);
                });

                // Ajouter le conteneur au parent
                const chartContainer = document.getElementById(containerId);
                chartContainer.appendChild(logoContainer);
            }

            // Définir le plugin pour le Scatter Chart "Mon Plan de Formation"
            const imagePluginScatter = {
                id: 'imagePluginScatter',
                afterRender: (chart) => drawLogos(chart, 'chart-container', labels),
                afterResize: (chart) => {
                    const logoContainer = document.getElementById('chart-container-logo-container');
                    if (logoContainer) {
                        logoContainer.remove();
                    }
                    drawLogos(chart, 'chart-container', labels);
                }
            };

            // Définir le plugin pour le Scatter Chart "Mesure de Compétences"
            const imagePluginScatterMesure = {
                id: 'imagePluginScatterMesure',
                afterRender: (chart) => drawLogos(chart, 'mesure-container', labels),
                afterResize: (chart) => {
                    const logoContainer = document.getElementById('mesure-container-logo-container');
                    if (logoContainer) {
                        logoContainer.remove();
                    }
                    drawLogos(chart, 'mesure-container', labels);
                }
            };

            // Initialisation du Scatter Chart "Mon Plan de Formation"
            const ctxScatter = document.getElementById('chartjs-container').getContext('2d');
            const scatterChart = new Chart(ctxScatter, {
                type: 'scatter',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Formations Recommandées : ',
                        data: labels.map((brand, i) => ({
                            x: i,
                            y: dataValues[i]
                        })),
                        backgroundColor: colors,
                        borderColor: colors,
                        borderWidth: 2,
                        pointRadius: 50, // Ajuster la taille des points
                        pointHoverRadius: 60,
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                color: '#000',
                                font: {
                                    size: 14
                                },
                                // -- Surcharge de la génération d’items dans la légende --
                                generateLabels: function(chart) {
                                    const dataset = chart.data.datasets[0];
                                    return [{
                                            // 1) Pas de pastille pour "Formations Recommandées"
                                            text: dataset.label,
                                            fillStyle: 'transparent',
                                            strokeStyle: 'transparent',
                                            lineWidth: 0,
                                            hidden: false
                                        },
                                        {
                                            text: 'Rouge : Score ≤ 50 %',
                                            fillStyle: 'rgb(241, 85, 100)',
                                            hidden: false
                                        },
                                        {
                                            text: 'Orange : 50 % < Score < 80 %',
                                            fillStyle: '#ffc107',
                                            hidden: false
                                        },
                                        {
                                            text: 'Vert : Score ≥ 80 %',
                                            fillStyle: '#1bea04',
                                            hidden: false
                                        },
                                        {
                                            // 2) Pas de pastille pour l’explication de la bulle
                                            text: 'Dans les Bulles : Chiffre = Nombre de Modules de Formations recommandés par le Centre de Formation, % = Score Total des Tests de la Mesure des Compétences',
                                            fillStyle: 'transparent',
                                            strokeStyle: 'transparent',
                                            lineWidth: 0,
                                            hidden: false
                                        }
                                    ];
                                }
                            }
                        },
                        datalabels: {
                            anchor: 'center',
                            align: 'center',
                            color: '#000',
                            font: {
                                size: 15,
                                weight: 'bold'
                            },
                            formatter: function(value, context) {
                                const i = context.dataIndex;
                                const brand = labels[context.dataIndex];
                                const modulesCount = brandFormationsMap[brand] !== undefined ?
                                    brandFormationsMap[brand] : '0';
                                const score = dataValues[i] !== null ? dataValues[i] : 'N/A';
                                return ` ${modulesCount} \n Modules \n(${score}%)`;
                            },
                            textAlign: 'center' // Centrer le texte
                        },
                        tooltip: {
                            enabled: true,
                            callbacks: {
                                label: function(context) {
                                    const i = context.dataIndex;
                                    const brand = labels[i];
                                    const score = dataValues[i] !== null ? dataValues[i] : 'N/A';
                                    const modulesCount = brandFormationsMap[brand] !== undefined ?
                                        brandFormationsMap[brand] : '0';
                                    const hours = brandHoursMap[brand] !== undefined ? brandHoursMap[
                                        brand] : '0';
                                    return [
                                        `Marque: ${brand}`,
                                        `Modules de Formations: ${modulesCount}`,
                                        `Résultat de la mesure: ${score}%`
                                    ];
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            type: 'category',
                            labels: labels,
                            offset: true, // laisse de la marge sur les côtés
                            grid: {
                                display: true, // affiche la grille
                                drawOnChartArea: true, // tire les traits
                                color: 'rgba(0,0,0,0.1)' // style des traits
                            },
                            ticks: {
                                display: false // Masquer les labels textuels
                            }
                        },
                        y: {
                            type: 'linear',
                            min: 0,
                            max: 100,
                            title: {
                                display: true,
                                text: 'Modules'
                            },
                            grid: {
                                color: '#ccc'
                            },
                            ticks: {
                                stepSize: 10,
                                padding: 10,
                                font: {
                                    size: 12
                                }
                            }
                        }
                    },
                    onClick: (evt, activeElements) => {
                        if (activeElements.length > 0) {
                            const index = activeElements[0].index;
                            if (colors[index] === '#ffc107') { // Couleur warning
                                window.open(urls[index], '_blank');
                            }
                        }
                    }
                },
                plugins: [imagePluginScatter, ChartDataLabels]
            });

            // Préparer les données pour le Scatter Chart "Mesure de Compétences"
            const scatterMesureData = brandScoresData.map((item, index) => {
                let borderColor = '#ffc107'; // Orange par défaut
                if (item.y >= 80) {
                    borderColor = '#198754'; // Vert pour >=80
                }

                return {
                    x: index,
                    y: item.y === null ? 0 : item.y,
                    pointRadius: item.y === null ? 30 : 35, // Rayon basé sur le score
                    pointBackgroundColor: '#aaaaa7', // Noir
                    pointBorderColor: '#aaaaa7',
                    pointBorderWidth: 2
                };
            });

            // Les appels directs à drawLogos sont gérés par les plugins après le rendu des graphiques
            // Vous pouvez les commenter ou les supprimer
            // drawLogos(scatterChart, 'chart-container', labels);
            // drawLogos(scatterChartMesure, 'mesure-container', labels);
        });
    </script>
<?php } else { ?>
    <script>

        // Passer les variables PHP au JavaScript via un objet centralisé
        const variablesPHP = {
            brandScores: <?php echo json_encode($brandScores, JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
            trainingsCountsForGraph2: <?php echo json_encode($trainingsCountsForGraph2, JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
            validationsCountsForGraph2: <?php echo json_encode($validationsCountsForGraph2, JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
            brandLogos: <?php echo json_encode($brandLogos, JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
            teamBrands: <?php echo json_encode($teamBrands, JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
            technicians: <?php echo json_encode($technicians, JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
            numTrainings: <?php echo json_encode($numTrainings); ?>,
            numDays: <?php echo json_encode($numDays); ?>,
            doneTest: <?php echo json_encode($doneTest); ?> // Ajouté
            // Ajoutez d'autres variables si nécessaire validationsCountsForGraph2
        };

        // Optionnel : Vérifiez les données dans la console
        console.log("Variables PHP dans JS:", variablesPHP);

        // Fonction pour dessiner les logos sur les graphiques en utilisant les images préchargées
        function drawLogos(chart, containerId, specificLabels) {
            // Supprimer les anciens conteneurs de logos
            const oldDiv = document.getElementById(containerId + '-logo-container');
            if (oldDiv) oldDiv.remove();

            // Créer un conteneur DIV pour les logos
            const logoContainer = document.createElement('div');
            logoContainer.id = containerId + '-logo-container';
            logoContainer.style.position = 'absolute';
            logoContainer.style.top = '0';
            logoContainer.style.left = '0';
            logoContainer.style.width = '100%';
            logoContainer.style.height = '100%';
            logoContainer.style.pointerEvents = 'none'; // Permettre les événements de souris à travers

            // Obtenir les échelles du graphique
            const xScale = chart.scales.x;
            const chartArea = chart.chartArea;

            const shiftRight = 0;

            // Boucler sur les labels spécifiques pour placer les logos
            specificLabels.forEach((label, index) => {
                const xPos = xScale.getPixelForValue(index);
                let yPos;

                if (containerId === 'scoreScatterCanvas') {
                    yPos = chartArea.bottom + 10; // Ajuster selon les besoins
                } else if (containerId === 'trainingsScatterCanvas') {
                    yPos = chartArea.bottom + 10; // Ajuster selon les besoins
                } else {
                    yPos = chartArea.bottom + 10; // Valeur par défaut
                }

                // Créer l'élément image
                const img = document.createElement('img');
                img.src = variablesPHP.brandLogos[label] ? `../../public/images/${variablesPHP.brandLogos[label]}` :
                    `../../public/images/default.png`;
                img.style.position = 'absolute';
                img.style.left = (xPos - 30 + shiftRight) + 'px'; // Centrer l'image (ajusté pour 60px de largeur)
                img.style.top = yPos + 'px';
                img.style.width = '60px';
                img.style.height = '35px';
                img.onerror = function() {
                    console.error(`Erreur de chargement de l'image : ${img.src}`);
                    img.src = '../../public/images/default.png';
                };

                // Ajouter l'image au conteneur
                logoContainer.appendChild(img);
            });

            // Ajouter le conteneur au parent
            const chartContainer = document.getElementById(containerId);
            chartContainer.parentElement.style.position = 'relative'; // Assurer que le parent est positionné relativement
            chartContainer.parentElement.appendChild(logoContainer);
        }

        // Plugins personnalisés pour ajouter des images après le rendu
        // const imagePluginScatter = {
        //     id: 'imagePluginScatter',
        //     afterRender: (chart) => drawLogos(chart, 'scoreScatterCanvas', variablesPHP.brandScores.map(item => item.x)),
        //     afterResize: (chart) => {
        //         const logoContainer = document.getElementById('scoreScatterCanvas-logo-container');
        //         if (logoContainer) {
        //             logoContainer.remove();
        //         }
        //         drawLogos(chart, 'scoreScatterCanvas', variablesPHP.brandScores.map(item => item.x));
        //     }
        // };

        const imagePluginScatterTrainings = {
            id: 'imagePluginScatterTrainings',
            afterRender: (chart) => drawLogos(chart, 'trainingsScatterCanvas', variablesPHP.brandScores.map(item => item
                .x)),
            afterResize: (chart) => {
                const logoContainer = document.getElementById('trainingsScatterCanvas-logo-container');
                if (logoContainer) {
                    logoContainer.remove();
                }
                drawLogos(chart, 'trainingsScatterCanvas', variablesPHP.brandScores.map(item => item.x));
            }
        };

        document.addEventListener('DOMContentLoaded', function() {
            // ------------------- Données pour Graphique 1 (Scores) -------------------
            const brandScoresPHP = variablesPHP.brandScores;
            // brandScoresPHP : [ {x: "RENAULT TRUCK", y: 78.6, fillColor: "#..."}, ... ]

            console.log("Brand Scores:", brandScoresPHP);

            const brandLabelsScores = brandScoresPHP.map(item => item.x);
            const scatterScores = brandScoresPHP.map((obj, index) => {
                return {
                    x: index,
                    y: obj.y,
                    fillColor: obj.fillColor
                };
            });

            // ------------------- Données pour Graphique 2 (Trainings) -------------------
            const trainingsCountsForGraph2 = variablesPHP.trainingsCountsForGraph2;
            // => { "RENAULT TRUCK": 5, "HINO": 2, ... }

            console.log("Trainings Counts for Graph 2:", trainingsCountsForGraph2);

            // ------------------- Liste des Techniciens -------------------
            const techniciansList = variablesPHP.technicians;
            console.log("Technicians List:", techniciansList);

            // ------------------- Liste des Marques -------------------
            const teamBrandsList = variablesPHP.teamBrands;
            console.log("Team Brands List:", teamBrandsList);

            // ------------------- Préparation des Données pour le Deuxième Graphique -------------------
            const scatterTrainings = [];
            brandLabelsScores.forEach((b, i) => {
                const count = trainingsCountsForGraph2[b] ?? 0;
                // On se cale sur l'axe Y = score ; ou 0..100
                let relevantScore = 0;
                const found = brandScoresPHP.find(x => x.x === b);
                if (found && typeof found.y === 'number') {
                    relevantScore = found.y;
                }
                scatterTrainings.push({
                    x: i,
                    y: relevantScore,
                    count: count
                });
            });

            // Préparation des données de scatter validation
            const validationsCountsForGraph2PHP = variablesPHP.validationsCountsForGraph2;
            const scatterValidations = brandScoresPHP.map((item, index) => {
                const count = validationsCountsForGraph2PHP[item.x] || 0; // Vérifier le compte ou mettre 0
                return {
                    x: index,
                    y: item.y,
                    count: count
                };
            });
            
            console.log("Scatter Trainings Data:", scatterTrainings);
            console.log("Scatter Validations Data:", scatterValidations);

            // ------------------- ChartJS : Score Scatter -------------------
            Chart.register(ChartDataLabels, ChartZoom);
            const customLabelPlugin = {
                id: 'customLabelPlugin',
                afterDraw: (chart) => {
                    const ctx = chart.ctx;
                    chart.data.datasets.forEach((dataset) => {
                        const meta = chart.getDatasetMeta(0);
                        meta.data.forEach((element, index) => {
                            // Récupérer vos données
                            const dataPoint = dataset.data[index];
                            const count = dataPoint.count; // ex: 16
                            const score = dataPoint.y; // ex: 66

                            // Position du centre du point
                            const position = element.getCenterPoint();

                            // 1) Dessiner la valeur numérique (16)
                            ctx.font = 'bold 20px Arial';
                            ctx.fillStyle = '#000';
                            ctx.textAlign = 'center';
                            ctx.fillText(Math.round(count), position.x, position.y - 20);

                            // 2) Dessiner "Module(s)"
                            ctx.font = 'bold 16px Arial';
                            ctx.fillText('Module(s)', position.x, position.y + 5);

                            // 3) Tracer le trait de séparation
                            ctx.beginPath();
                            ctx.moveTo(position.x - 50, position.y + 20);
                            ctx.lineTo(position.x + 50, position.y + 20);
                            ctx.strokeStyle = '#000';
                            ctx.lineWidth = 2;
                            ctx.stroke();

                            // 4) Dessiner le pourcentage en dessous
                            ctx.fillText(`${Math.round(score)}%`, position.x + 5, position.y +
                                35);
                        });
                    });
                }
            };
            const pseudo3DPlusPlugin = {
                id: 'pseudo3DPlusPlugin',
                afterDatasetDraw(chart, args) {
                    const {
                        ctx
                    } = chart;
                    const meta = chart.getDatasetMeta(args.index);
                    const dataset = chart.data.datasets[args.index].data;

                    meta.data.forEach((element, i) => {
                        // Récupérer coords
                        const {
                            x,
                            y
                        } = element.getProps(['x', 'y']);
                        const dataPoint = dataset[i];
                        const radius = dataPoint.pointRadius ?? 55;

                        // ------- 1) Dessiner l’ombre -------
                        ctx.save();
                        ctx.shadowColor = 'rgba(0,0,0,0.3)';
                        ctx.shadowBlur = 10;
                        ctx.shadowOffsetX = 4;
                        ctx.shadowOffsetY = 4;

                        // On va dessiner un cercle invisible juste pour poser l’ombre
                        // On dessine la "base" plus tard avec un 2e fill
                        ctx.beginPath();
                        ctx.arc(x, y, radius, 0, 2 * Math.PI);
                        ctx.fillStyle = 'transparent';
                        ctx.fill();
                        ctx.restore();

                        // ------- 2) Couche de base (dégradé radial “convexe”) -------
                        // On imagine une zone plus claire au centre, plus foncée vers l’extérieur
                        const colorMain = dataPoint.backgroundColor ?? '#ffc107';
                        // On va faire un gradient radial qui part d’une teinte plus claire que colorMain
                        ctx.save();
                        const gradientBase = ctx.createRadialGradient(
                            x, y, radius * 0.2, // point central, petit rayon
                            x, y, radius // rayon final
                        );
                        // Ajuste la couleur pour le cœur + bords
                        gradientBase.addColorStop(0, éclaircir(colorMain, 1.2)); // un peu plus clair
                        gradientBase.addColorStop(0.7, colorMain);
                        gradientBase.addColorStop(1, assombrir(colorMain, 1.1)); // un chouïa plus sombre

                        ctx.beginPath();
                        ctx.arc(x, y, radius, 0, 2 * Math.PI);
                        ctx.fillStyle = gradientBase;
                        ctx.fill();
                        ctx.restore();

                        // ------- 3) Spot highlight -------
                        // Un tout petit arc dans le coin supérieur gauche 
                        // pour un effet "reflet" plus discret
                        ctx.save();
                        const highlightRadius = radius * 0.35;
                        // On peut jouer sur l’offset pour le placer en haut-gauche
                        const hx = x - radius * 0.3;
                        const hy = y - radius * 0.3;
                        const highlightGrad = ctx.createRadialGradient(
                            hx, hy, 0,
                            hx, hy, highlightRadius
                        );
                        highlightGrad.addColorStop(0, 'rgba(255,255,255,0.8)');
                        highlightGrad.addColorStop(0.8, 'rgba(255,255,255,0)');
                        ctx.beginPath();
                        ctx.arc(hx, hy, highlightRadius, 0, 2 * Math.PI);
                        ctx.fillStyle = highlightGrad;
                        ctx.fill();
                        ctx.restore();

                        // ------- 4) Un léger contour “foncé” -------
                        ctx.save();
                        ctx.beginPath();
                        ctx.arc(x, y, radius, 0, 2 * Math.PI);
                        ctx.lineWidth = 2;
                        ctx.strokeStyle = '#000';
                        ctx.stroke();
                        ctx.restore();
                    });
                }
            };

            // Helpers pour éclaircir/assombrir la couleur
            function éclaircir(hex, factor) {
                // factor=1.2 => +20% luminosité
                return adjustColor(hex, factor);
            }

            function assombrir(hex, factor) {
                // factor=1.1 => +10% plus sombre
                return adjustColor(hex, 1 / factor);
            }

            function adjustColor(hex, factor) {
                // parse du #RRGGBB
                // petite fonction express
                let c = hex.replace('#', '');
                if (c.length === 3) c = c[0] + c[0] + c[1] + c[1] + c[2] + c[2]; // #FFF => #FFFFFF
                const r = parseInt(c.substring(0, 2), 16);
                const g = parseInt(c.substring(2, 4), 16);
                const b = parseInt(c.substring(4, 6), 16);
                // applique factor => new color
                const nr = Math.min(255, Math.round(r * factor));
                const ng = Math.min(255, Math.round(g * factor));
                const nb = Math.min(255, Math.round(b * factor));
                const sr = nr.toString(16).padStart(2, '0');
                const sg = ng.toString(16).padStart(2, '0');
                const sb = nb.toString(16).padStart(2, '0');
                return `#${sr}${sg}${sb}`;
            }

            // ------------------- ChartJS : Trainings Scatter -------------------
            const ctx2 = document.getElementById('trainingsScatterCanvas').getContext('2d');
            const dataForTrainingsChart2 = scatterTrainings.map(pt => {
                // pt = { x: ..., y: scoreMoyen, count: nombreDeModules }
                let color;
                if (pt.y <= 50) {
                    // 0..50% => warning
                    color = 'rgb(241, 85, 100)';
                } else if (pt.y < 80) {
                    // 51..79% => "couleur actuelle" (par exemple un orange un peu plus foncé, ou rouge)
                    // Ex : un orange plus foncé #fd7e14, ou un rouge #dc3545, au choix :
                    color = '#ffc107';
                    // ou color = '#dc3545'; // si vous préférez "danger"
                } else {
                    // 80..100 => success
                    color = '#1bea04';
                }

                return {
                    x: pt.x,
                    y: pt.count,
                    count: pt.y,
                    pointRadius: 55,
                    pointHoverRadius: 60
                };
            });
            const dataForValidationsChart2 = scatterValidations.map(pt => {
                // pt = { x: ..., y: scoreMoyen, count: nombreDeModules }
                let color;
                if (pt.y <= 50) {
                    // 0..50% => warning
                    color = 'rgb(241, 85, 100)';
                } else if (pt.y < 80) {
                    // 51..79% => "couleur actuelle" (par exemple un orange un peu plus foncé, ou rouge)
                    // Ex : un orange plus foncé #fd7e14, ou un rouge #dc3545, au choix :
                    color = '#ffc107';
                    // ou color = '#dc3545'; // si vous préférez "danger"
                } else {
                    // 80..100 => success
                    color = '#1bea04';
                }

                return {
                    x: pt.x,
                    y: pt.count,
                    count: pt.y,
                    pointRadius: 55,
                    pointHoverRadius: 60
                };
            });
            console.log("Validations Data:", dataForValidationsChart2);

            const chartFormations = new Chart(ctx2, {
                type: 'bar',
                data: {
                    datasets: [{
                        label: "Formations Proposées",
                        data: dataForTrainingsChart2,
                        showLine: false, // pas de ligne, juste des points
                        parsing: false, // important pour accéder directement à ctx.raw
                        pointRadius: 40,
                        pointHoverRadius: 50,
                        backgroundColor: '#FFC107',
                        borderColor: '#FFC107',
                        borderWidth: 0
                    }, {
                        label: "Formations Validées",
                        data: dataForValidationsChart2,
                        showLine: false, // pas de ligne, juste des points
                        parsing: false, // important pour accéder directement à ctx.raw
                        pointRadius: 40,
                        pointHoverRadius: 50,
                        backgroundColor: '#0275D8',
                        borderColor: '#0275D8',
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                color: '#000',
                                font: {
                                    size: 14
                                }
                            }
                        },
                        datalabels: {
                            display: false,
                            formatter: (value, ctx) => {
                                const dataPoint = ctx.dataset.data[ctx.dataIndex];
                                return `${Math.round(dataPoint.count)} modules`; // Arrondir 'count' si nécessaire
                            },
                        },
                        tooltip: {
                            enabled: true,
                            mode: 'index', // Affiche les tooltips pour tous les datasets à la même position
                            xAlign: "center",
                            yAlign: "bottom",
                            caretPadding: 60,
                            callbacks: {
                                title: function(tooltipItems) {
                                    // Retourne le titre du tooltip
                                    return `Marque: ${tooltipItems[0].label}`;
                                },
                                label: (tooltipItem) => {
                                    // Retourne le label et la valeur du point
                                    const count = Math.round(tooltipItem.raw.y); // Assurez-vous que vos données contiennent un champ 'count'
                                    const score = Math.round(tooltipItem.raw.count); // Assurez-vous que vos données contiennent un champ 'score'
                                    return [
                                        `Modules de Formations: ${count}`,
                                    ];
                                }
                            }
                        },
                        zoom: {
                            pan: {
                                enabled: true,
                                mode: 'x'
                            },
                            zoom: {
                                enabled: false
                            }
                        }
                    },
                    scales: {
                        x: {
                            type: 'category',
                            labels: brandLabelsScores,
                            offset: true, // laisse de la marge sur les côtés
                            grid: {
                                display: true, // affiche la grille
                                drawOnChartArea: true, // tire les traits
                                color: 'rgba(0,0,0,0.1)' // style des traits
                            },
                            ticks: {
                                display: false // Masquer les labels textuels
                            }
                        },
                        y: {
                            type: 'linear',
                            min: 0,
                            max: 50,
                            title: {
                                display: true,
                                text: 'Modules'
                            },
                            grid: {
                                color: '#ccc'
                            },
                            ticks: {
                                stepSize: 10,
                                padding: 10,
                                font: {
                                    size: 12
                                }
                            }
                        }
                    }
                },
                plugins: [imagePluginScatterTrainings, ChartDataLabels,  ChartZoom]
            });
        });

        // On passe la variable PHP $maxLevel en JavaScript :
        const trainingsCountsForGraph2 = <?php echo json_encode($trainingsCountsForGraph2); ?>;
        console.log("trainingsCountsForGraph2 :", trainingsCountsForGraph2);

        // Configuration initiale des variables PHP
        const CONFIG = {
            maxLevelInTeam: <?php echo json_encode($maxLevel); ?>,
            levelsToShowPHP: <?php echo json_encode($levelsToShow); ?>,
            agencyBrandsMapping: <?php echo json_encode($agencyBrandsMapping); ?>,
            userProfile: <?php echo json_encode($profile); ?>,
            technicianLevel: <?php echo json_encode($techLevel); ?>,

        };

        // Graphiques pour les resultats du groupe
        document.addEventListener('DOMContentLoaded', function() {
            // Data for each chart
            const chartData = [
                {
                    bigTitle: 'Proposition des Plans Individuels de Formation (PIF) par l\'Academy',
                    title: 'Nombre de PIF Proposé <?php echo $techWithTraining ?> / <?php echo count($countUsers) ?> Techniciens',
                    total: 100,
                    completed: <?php echo round(($techWithTraining * 100) / count($countUsers)) ?>, // Moyenne des compétences acquises
                    data: [
                        <?php echo round(($techWithTraining * 100) / count($countUsers)) ?>,
                        100 - <?php echo round(($techWithTraining * 100) / count($countUsers)) ?>
                    ], // Moyenne des compétences acquises vs. Moyenne des compétences à acquérir
                    labels: [
                        '<?php echo round(($techWithTraining * 100) / count($countUsers)) ?>% des techniciens mesurés',
                        '<?php echo 100 - round(($techWithTraining * 100) / count($countUsers)) ?>% des techniciens à mesurer'
                    ],
                    backgroundColor: ['#4303ec', '#D3D3D3']
                },
                {
                    bigTitle: 'Validation des Plans Individuels de Formation (PIF) par la Filiale',
                    title: 'Nombre de PIF Validé par la Filiale <?php echo $techWithTrainingSelected ?> / <?php echo $techWithTraining ?> Techniciens',
                    total: 100,
                    completed: <?php echo round(($techWithTrainingSelected * 100) / $techWithTraining) ?>, // Moyenne des compétences acquises
                    data: [
                        <?php echo round(($techWithTrainingSelected * 100) / $techWithTraining) ?>,
                        100 - <?php echo round(($techWithTrainingSelected * 100) / $techWithTraining) ?>
                    ], // Moyenne des compétences acquises vs. Moyenne des compétences à acquérir
                    labels: [
                        '<?php echo round(($techWithTrainingSelected * 100) / $techWithTraining) ?>% des PIF validés',
                        '<?php echo 100 - round(($techWithTrainingSelected * 100) / $techWithTraining) ?>% des PIF à valider'
                    ],
                    backgroundColor: ['#00E648', '#4303EC']
                },
            ];
        
            const container = document.getElementById('chartTraining');
        
            // Loop through the data to create and append cards
            chartData.forEach((data, index) => {
                // Calculate the completed percentage
                const completedPercentage = Math.round((data.completed / data.total) * 100);
        
                // Create the card element
                const cardHtml = `
  <div class="col-md-6 mb-4 d-flex justify-content-center">
    <div class="card h-100" >
      <div class="card-body d-flex flex-column align-items-center p-2">
        <h5 class="mb-1">${data.bigTitle}</h5>
        <div class="doughnut-wrapper mx-auto">
          <canvas id="doughnutChart${index}"></canvas>
        </div>
        <h5 class="mt-1 text-center">${data.title}</h5>
      </div>
    </div>
  </div>
`;

                

        
                // Append the card to the container
                container.insertAdjacentHTML('beforeend', cardHtml);
                // Initialize the Chart.js doughnut chart
                new Chart(document.getElementById(`doughnutChart${index}`).getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Data',
                            data: data.data,
                            backgroundColor: data.backgroundColor,
                            borderColor: data.backgroundColor,
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                            },
                            datalabels: {
                                formatter: (value, ctx) => {
                                    let sum = ctx.chart.data.datasets[0].data.reduce((a, b) => a +
                                        b, 0);
                                    let percentage = Math.round((value / sum) * 100);
                                    // Round up to the nearest whole number
                                    return percentage + '%';
                                },
                                color: '#fff',
                                display: true,
                                anchor: 'center',
                                align: 'center',
                                font: {
                                    size: 16,
                                    weight: 'bold'
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(tooltipItem) {
                                        const value = tooltipItem.raw || 0;
                                        const dataset = tooltipItem.dataset.data;
                                        let sum = dataset.reduce((a, b) => a + b, 0);
                                        let percentage = Math.round((value / sum) * 100);
                                        // Round up to the nearest whole number
                                        return `Pourcentage: ${percentage}%`;
                                    }
                                }
                            }
                        }
                    }
                });
            });
        });

        // Éléments DOM
        const DOM = {
            levelFilter: document.getElementById('level-filter'),
            brandFilter: document.getElementById('brand-filter'),
            managerFilter: document.getElementById('manager-filter'),
            technicianFilter: document.getElementById('technician-filter'),
            filialeFilter: document.getElementById('filiale-filter'),
            agenceFilter: document.getElementById('agence-filter'),
            managerHidden: document.getElementById('manager-hidden')
        };

        // Gestion des URL
        const URLManager = {
            getParams: () => new URLSearchParams(window.location.search),
            update: function(params) {
                const url = new URL(window.location.href);
                params.forEach((value, key) => url.searchParams.set(key, value));
                window.location.href = url.toString();
            }
        };

        const FilterLogic = {
            updateLevelOptions: function(selectedTechId) {
                if (!DOM.levelFilter) return;

                const params = URLManager.getParams();
                // On vide le niveau pour le recréer
                DOM.levelFilter.innerHTML = '';

                if (selectedTechId !== 'all') {
                    // Récupère le niveau réel du technicien sélectionné
                    const techLevel = this.getTechnicianLevelById(selectedTechId);
                    this.handleTechnicianLevel(params, techLevel);
                } else {
                    // Pas de technicien précis : logique en fonction du userProfile
                    if (CONFIG.userProfile === 'Technicien') {
                        this.handleTechnicianLevel(params, CONFIG.technicianLevel);
                    } else {
                        this.handleManagerLevel(params);
                    }
                }
            },

            handleTechnicianLevel: function(params, techLevel) {
                // Ajoute "Tous les niveaux"
                DOM.levelFilter.add(new Option('Tous les niveaux', 'all'));

                // Limite les options de niveau au maxLevel du tech
                const allowedLevels = this.getAvailableLevels(techLevel);
                allowedLevels.forEach(level => {
                    DOM.levelFilter.add(new Option(level, level));
                });

                // Si le niveau dans l’URL n’est pas dans les niveaux autorisés, reset en 'all'
                let currentLevel = params.get('level') || 'all';
                if (currentLevel !== 'all' && !allowedLevels.includes(currentLevel)) {
                    currentLevel = 'all';
                    params.set('level', 'all');
                    URLManager.update(params);
                }
                DOM.levelFilter.value = currentLevel;
            },

            handleManagerLevel: function(params) {
                DOM.levelFilter.add(new Option('Tous les niveaux', 'all'));
                CONFIG.levelsToShowPHP.forEach(level => {
                    if (level !== 'all') {
                        DOM.levelFilter.add(new Option(level, level));
                    }
                });
                DOM.levelFilter.value = params.get('level') || 'all';
            },

            // Renvoie la liste des niveaux (Junior -> Senior -> Expert) jusqu’au maxLevel inclus
            getAvailableLevels: function(userLevel) {
                const levels = ['Junior'];
                if (userLevel === 'Senior' || userLevel === 'Expert') levels.push('Senior');
                if (userLevel === 'Expert') levels.push('Expert');
                return levels;
            },


            // Suppose qu’on a un mapping CONFIG.technicians[techId] = { level: 'Senior' } par ex.
            getTechnicianLevelById: function(techId) {
                // Parcours les options du select de techniciens
                const options = DOM.technicianFilter.options;
                for (let i = 0; i < options.length; i++) {
                    if (options[i].value === techId) {
                        const level = options[i].getAttribute('data-level');
                        console.log('Trouvé data-level pour techId', techId, ':', level);
                        return level;
                    }
                }
                console.warn('Aucune option trouvée pour techId', techId, '- fallback utilisé');
                return CONFIG.technicianLevel; // fallback au cas où
            },

            collectFilters: function() {
                const params = new URLSearchParams();

                // Lecture des valeurs depuis les <select> (ou nullish coalescing)
                const filiale = DOM.filialeFilter?.value || 'all';
                const agence = DOM.agenceFilter?.value || 'all';
                const managerId = DOM.managerHidden?.value || DOM.managerFilter?.value || 'all';
                const brand = DOM.brandFilter?.value || 'all';
                const technicianId = DOM.technicianFilter?.value || 'all';
                const level = DOM.levelFilter?.value || 'all';

                // Construit les paramètres
                params.set('filiale', filiale);
                params.set('agence', agence);
                params.set('managerId', managerId);
                params.set('brand', brand);
                params.set('technicianId', technicianId);
                params.set('level', level);

                return params;
            },

            // Pas de reset de `technicianId` ici, pour ne pas perdre la sélection
            handleAgencyChange: function(newAgency) {
                const params = new URLSearchParams();
                params.set('filiale', DOM.filialeFilter ? DOM.filialeFilter.value : 'all');
                params.set('agence', newAgency);
                params.set('managerId', 'all');
                // **On ne touche pas** params.set('technicianId', 'all');
                params.set('level', 'all');

                // On check la marque (si l’agence en impose une)
                if (CONFIG.agencyBrandsMapping[newAgency]) {
                    const currentBrand = DOM.brandFilter?.value || 'all';
                    if (!CONFIG.agencyBrandsMapping[newAgency].includes(currentBrand)) {
                        params.set('brand', 'all');
                    }
                }

                URLManager.update(params);
            }
        };

        const EventHandlers = {
            init: function() {
                document.addEventListener('DOMContentLoaded', () => {
                    this.setupEventListeners();
                    // Premier remplissage
                    if (DOM.technicianFilter) {
                        FilterLogic.updateLevelOptions(DOM.technicianFilter.value);
                    }
                });
            },

            setupEventListeners: function() {
                if (DOM.brandFilter) {
                    DOM.brandFilter.addEventListener('change', () => {
                        URLManager.update(FilterLogic.collectFilters());
                    });
                }
                if (DOM.levelFilter) {
                    DOM.levelFilter.addEventListener('change', () => {
                        URLManager.update(FilterLogic.collectFilters());
                    });
                }
                if (DOM.managerFilter) {
                    DOM.managerFilter.addEventListener('change', this.handleManagerChange);
                }
                if (DOM.technicianFilter) {
                    DOM.technicianFilter.addEventListener('change', this.handleTechnicianChange);
                }
                if (DOM.filialeFilter) {
                    DOM.filialeFilter.addEventListener('change', this.handleFilialeChange);
                }
                if (DOM.agenceFilter) {
                    DOM.agenceFilter.addEventListener('change', this.handleAgencyChange);
                }
            },

            handleManagerChange: (e) => {
                const params = FilterLogic.collectFilters();
                params.set('managerId', e.target.value);
                // On évite de toucher au technicianId !
                params.set('level', 'all');
                params.set('brand', 'all');
                URLManager.update(params);
            },

            handleTechnicianChange: (e) => {
                FilterLogic.updateLevelOptions(e.target.value);
                URLManager.update(FilterLogic.collectFilters());
            },

            handleFilialeChange: (e) => {
                const params = new URLSearchParams();
                params.set('filiale', e.target.value);
                params.set('agence', 'all');
                params.set('managerId', 'all');
                // Encore une fois, on ne touche pas au technicianId
                params.set('brand', 'all');
                params.set('level', 'all');
                URLManager.update(params);
            },

            handleAgencyChange: (e) => {
                FilterLogic.handleAgencyChange(e.target.value);
            }
        };

        // Initialisation
        EventHandlers.init();
    </script>
<?php } ?>