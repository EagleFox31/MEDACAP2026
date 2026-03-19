<?php
session_start();
//dashboardtech.php
// ----------------------------------------------------------
// 1) Vérifier session / profil, puis connexion MongoDB
// ----------------------------------------------------------
if (!isset($_SESSION["profile"])) {
    header("Location: /");
    exit();
} else {
    // Allow access if the user is a Technicien or Super Admin
    if ($_SESSION["profile"] !== 'Technicien' && $_SESSION["profile"] !== 'Super Admin') {
        echo "Accès refusé.";
        exit();
    }
    if ($_SESSION["profile"] === 'Super Admin') {
        // Super Admin can specify userId via GET
        if (isset($_GET['id'])) {
            $technicianId = $_GET['id'];
        } else {
            echo "Paramètre userId requis pour les Super Admin.";
            exit();
        }
    } else {
        // Technicien can only view their own dashboard
        $technicianId = $_SESSION["id"];
    }

    require_once "../../vendor/autoload.php";

    // Connexion
    try {
        $mongo   = new MongoDB\Client("mongodb://localhost:27017");
        $academy = $mongo->academy;  // base "academy"

        // Collections
        $usersColl     = $academy->users;
        $trainingsColl = $academy->trainings;
    } catch (MongoDB\Exception\Exception $e) {
        echo "Erreur de connexion à MongoDB : " . htmlspecialchars($e->getMessage());
        exit();
    }

    // ----------------------------------------------------------
    // 2) Charger la config GF (groupes fonctionnels) et ScoreCalculator
    // ----------------------------------------------------------
    $config = require __DIR__ . "/configGF.php";
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
    $technicianDoc = $usersColl->findOne([
        '_id' => $techObjId,
        'profile' => 'Technicien'
    ]);

    if (!$technicianDoc) {
        echo "Technicien introuvable.";
        exit();
    }
    // Récupérer le niveau du technicien
    $tLevel = isset($technicianDoc['level']) ? $technicianDoc['level'] : 'Junior';
    // Récupérer le filtre de niveau depuis GET ou défaut à 'Tous les Niveaux'
    $levelFilter = isset($_GET['levelFilter']) ? $_GET['levelFilter'] : 'Tous les Niveaux';
    // "Tous les Niveaux", "Junior", "Senior", ou "Expert"

    // ----------------------------------------------------------
    // 4) Préparer la liste de niveaux à inclure (ex. un Expert inclut Senior + Junior, etc.)
    // ----------------------------------------------------------
    function getLevelsToInclude($level)
    {
        if ($level === 'Junior') return ['Junior'];
        if ($level === 'Senior') return ['Senior'];
        if ($level === 'Expert') return ['Expert'];
        if ($level === 'Tous les Niveaux') return ['Junior', 'Senior', 'Expert'];

        return ['Junior', 'Senior', 'Expert'];
    }
    $levels = getLevelsToInclude($levelFilter);

    // ----------------------------------------------------------

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

    $debug = [];

    // Appeler la méthode correcte pour obtenir $allScores
    $allScores = $scoreCalc->getAllScoresForTechnicians(
        $academy,
        $technicianManagerMap,
        $levels,
        $allSpecialities,
        $debug
    );
    // $allScores[$technicianId][$level][$speciality] = ['Factuel'=>..., 'Declaratif'=>...]

    // ----------------------------------------------------------
    // 6) Calculer la note "globale" par marque (pour le graphe)
    //
    //    a) Récupérer la liste des marques (brand) que le technicien maîtrise
    //       ou qu'on veut afficher. (Vous pouvez définir cela via brandJunior, brandSenior, etc.
    //       Ou alors lister TOUTES les marques existantes ?
    // ----------------------------------------------------------
    $technicianDoc = $usersColl->findOne(
        ['_id' => new MongoDB\BSON\ObjectId($technicianId), 'profile' => 'Technicien']
    );
    if (!$technicianDoc) {
        // Si le technicien n'existe pas => on fera un fallback
        $brandsToShow = [];
    } else {
        // Suppose qu’on a brandJunior, brandSenior, brandExpert
        // On combine en fonction des niveaux
        $brandFieldJunior = $technicianDoc['brandJunior'] ?? [];
        $brandFieldSenior = $technicianDoc['brandSenior'] ?? [];
        $brandFieldExpert = $technicianDoc['brandExpert'] ?? [];
        // On reconstruit la liste de marques
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
        $brandsToShow = array_unique($allBrandsInDoc);
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

        $brandScores[] = [
            'x' => $oneBrand,
            'y' => $finalScore,
            'fillColor' => ($finalScore !== null && $finalScore >= 80) ? '#198754' : (($finalScore !== null) ? '#ffca38' : '#6c757d'), // Vert pour >=80, Jaune pour <80, Gris sinon
            'labelText' => $labelText,
            'url' => '#' // Placeholder, peut être remplacé par des URLs réelles
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
    function countRecommendedTrainings($trainingsColl, $technicianObjId, array $levels)
    {
        $query = [
            'active'   => true,
            'level'    => ['$in' => $levels],
            'users'    => $technicianObjId,
            'brand'    => ['$ne' => '']  // Exclure les marques vides
        ];
        return $trainingsColl->count($query); // Utiliser count
    }

    // Fonction pour compter les formations réalisées
    function countCompletedTrainings($trainingsColl, $technicianObjId, array $levels)
    {
        $query = [
            'active' => true,
            'level'  => ['$in' => $levels],
            'endDate' => ['$exists' => true, '$ne' => null],
            'users'  => $technicianObjId,
            'brand'  => ['$ne' => '']  // Exclure les marques vides
        ];
        return $trainingsColl->count($query);
    }

    // b) Récupérer les formations et compter par marque
    function getTrainingsByBrand($trainingsColl, $technicianObjId, array $levels)
    {
        $pipeline = [
            // 1) Étape $match
            [
                '$match' => [
                    'active' => true,
                    'level'  => ['$in' => $levels],
                    'users'  => $technicianObjId,
                    'brand'  => ['$ne' => '']  // Exclure les marques vides
                ]
            ],
            // 2) Étape $group
            [
                '$group' => [
                    '_id'   => '$brand',
                    'count' => ['$sum' => 1]
                ]
            ],
        ];

        $results = $trainingsColl->aggregate($pipeline);

        $trainingsByBrand = [];
        foreach ($results as $doc) {
            $trainingsByBrand[] = [
                'brand' => $doc->_id,
                'count' => $doc->count
            ];
        }
        return $trainingsByBrand;
    }

    // c) Récupérer les counts
    try {
        $numRecommended = countRecommendedTrainings($trainingsColl, $technicianObjId, $levels);
        $numCompleted   = countCompletedTrainings($trainingsColl, $technicianObjId, $levels);
        $trainingsByBrand = getTrainingsByBrand($trainingsColl, $technicianObjId, $levels);
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
    function getTrainingHoursByBrand($trainingsColl, $technicianObjId, array $levels)
    {
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

        $results = $trainingsColl->aggregate($pipeline);

        $brandHoursMap = [];
        foreach ($results as $doc) {
            $totalHours = $doc->totalDureeJours; // 1 jour = 8 heures
            $brandHoursMap[(string)$doc->_id] = $totalHours;
        }

        return $brandHoursMap;
    }

    // Calculer les heures de formation par marque
    $brandHoursMap = getTrainingHoursByBrand($trainingsColl, $technicianObjId, $levels);



    // ------------------------------
    // CRÉER ET CALCULER totalDuration
    // ------------------------------

    // 1) Définir le tableau (valeurs par défaut)
    $totalDuration = [
        'jours'  => 0,
        'heures' => 0
    ];

    // 2) Récupérer toutes les formations du technicien
    $cursorTrainings = $trainingsColl->find([
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
    ];

    // ----------------------------------------------------------
    // 8) Affichage (Bootstrap + Chart Libraries)
    // ----------------------------------------------------------
?>
    <!DOCTYPE html>
    <html lang="fr">

    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Dashboard Technicien | CFAO Mobility Academy</title>
        <!-- Inclure Bootstrap CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

        <!-- Inclure Font Awesome pour les icônes (si nécessaire) -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
            integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw=="
            crossorigin="anonymous" referrerpolicy="no-referrer" />

        <!-- Inclure les CDNs des bibliothèques de graphiques -->
        <!-- Chart.js CDN -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <!-- jQuery CDN -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <!-- Chart.js Datalabels Plugin -->
        <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
        <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom@1.2.0"></script>

        <style>
            /* Styles personnalisés */

            /* Style général des cartes */
            .custom-card {
                border-radius: 15px;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                transition: transform 0.2s, box-shadow 0.2s;
                cursor: pointer;
                border: none;
                /* Enlever les bordures existantes */
            }

            .custom-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 8px 12px rgba(0, 0, 0, 0.2);
            }

            /* Logos des marques */
            .brand-logo {
                width: 60px;
                height: 35px;

                margin-bottom: 0.5rem;
            }

            /* Conteneur des graphiques */
            .chart-dashboard-container {
                position: relative;
                padding: 20px;
                width: 100%;
                /* Utiliser la pleine largeur */
                box-sizing: border-box;
                margin: 0 auto;
            }

            /* Titre des graphiques avec icône */
            .chart-title {
                display: flex;
                align-items: center;
                margin-bottom: 1rem;
                font-size: 1.25rem;
                font-weight: bold;
            }

            .chart-title i {
                margin-right: 0.5rem;
                color: #198754;
                /* Couleur verte pour les icônes */
            }

            /* Responsive ajustements */
            @media (max-width: 768px) {
                .chart-title {
                    font-size: 1rem;
                }

                .brand-logo {
                    width: 40px;
                    height: 25px;
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


            /* Canvas des graphiques */
            canvas {
                /* width: 100% !important;
    height: auto !important; */
            }
        </style>
    </head>

    <body>
        <?php include "./partials/header.php"; ?>

        <div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content">
            <?php if ($_SESSION["profile"] == "Technicien") { ?>
                <div class="toolbar" id="kt_toolbar">
                    <div class="container-fluid d-flex flex-stack flex-wrap flex-sm-nowrap">
                        <div class="d-flex flex-column align-items-start justify-content-center flex-wrap me-2">
                            <h1 class="text-dark fw-bold my-1 fs-2">
                                <?php
                                $firstName = isset($technicianDoc['firstName']) ? htmlspecialchars($technicianDoc['firstName']) : '';
                                $lastName  = isset($technicianDoc['lastName']) ? htmlspecialchars($technicianDoc['lastName']) : '';
                                ?>
                                Tableau de Bord du Technicien: <i class="fas fa-user-circle text-success"></i> <?php echo "$firstName $lastName"; ?>
                            </h1>
                        </div>
                    </div>
                </div>
                <!-- Main Content -->
                <div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content">
                    <div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
                        <div class="container-xxl">
                            <!-- Filtre de Niveau -->
                            <div class="row mb-4 justify-content-center">
                                <div class="col-md-6">
                                    <i class="fas fa-filter me-2 text-warning"></i>
                                    <label for="level-filter" class="form-label">Filtrer par Niveau</label>
                                    <select id="level-filter" class="form-select" onchange="location.href=this.value">
                                        <option value="?id=<?php echo htmlspecialchars($technicianId); ?>&levelFilter=Tous les Niveaux" <?php if ($levelFilter === 'Tous les Niveaux') echo 'selected'; ?>>Tous les niveaux</option>
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
                                            echo "<option value='?id=" . htmlspecialchars($technicianId) . "&levelFilter=" . htmlspecialchars($lvl) . "' $selected>" . htmlspecialchars($lvl) . "</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <hr>

                            <!-- Marques du Technicien -->
                            <div class="text-center mb-4">
                                <h5 class="mb-3">Mes Marques sur lesquelles j'interviens en Atelier :</h5>
                                <div class="row justify-content-center">
                                    <?php
                                    if (!empty($brandsToShow)) {
                                        foreach ($brandsToShow as $brand) {
                                            $logoSrc = isset($brandLogos[$brand]) ? "brands/" . $brandLogos[$brand] : "brands/default.png";
                                            echo "<div class='col-6 col-sm-4 col-md-3 col-lg-2 mb-4'>";
                                            echo "<div class='card custom-card h-100'>";
                                            echo "<div class='card-body d-flex flex-column justify-content-center align-items-center'>";
                                            echo "<img src='$logoSrc' alt='$brand Logo' class='img-fluid brand-logo' aria-label='Logo $brand'>";
                                            // Optionnel: Afficher le nom de la marque
                                            // echo "<h6 class='card-title text-center'>$brand</h6>";
                                            echo "</div>";
                                            echo "</div>";
                                            echo "</div>";
                                        }
                                    } else {
                                        echo "<span class='badge bg-secondary me-2 mb-2'>Aucune marque</span>";
                                    }
                                    ?>
                                </div>
                            </div>


                            <hr><br>

                            <!-- Section des Graphiques -->
                            <div class="chart-dashboard-container">
                                <!-- Graphique 1: Mes Résultats aux Tests -->
                                <div class="row align-items-center mb-4">
                                    <!-- Carte d'explication à gauche avec collapse -->
                                    <div class="col-lg-3 mb-4 mb-lg-0">
                                        <div class="card custom-card">
                                            <div class="card-header d-flex justify-content-between align-items-center">
                                                <!-- Bouton de toggle -->
                                                <button class="btn btn-sm btn-outline-secondary toggle-info" type="button" data-bs-toggle="collapse" data-bs-target="#infoMesure" aria-expanded="true" aria-controls="infoMesure">
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                                <div id="infoMesure" class="collapse show">
                                                    <div class="card-body">
                                                        <p class="card-text">Visualisez vos performances sur différents tests par marque.</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Graphique à droite -->
                                    <div class="col-lg-9">
                                        <div class="card custom-card h-100">
                                            <div class="card-body">
                                                <div id="mesure-container" class="d-flex flex-column align-items-center mb-5">
                                                    <h3 class="text-center mb-4">1. Mes Résultats aux Tests</h3>
                                                    <canvas id="myChartMesure" aria-label="Graphique des Résultats aux Tests" role="img"></canvas>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row align-items-center mb-4">


                                    <!-- Graphique 2: Mon Plan de Formation par Marque -->
                                    <div class="row align-items-center mb-4">
                                        <!-- Carte d'explication à gauche -->
                                        <div class="col-lg-3 mb-4 mb-lg-0">
                                            <div class="card custom-card">
                                            <div class="card-header d-flex justify-content-between align-items-center">
                                                <!-- Bouton de toggle -->
                                                <button class="btn btn-sm btn-outline-secondary toggle-info" type="button" data-bs-toggle="collapse" data-bs-target="#infoMesure" aria-expanded="true" aria-controls="infoMesure">
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                                <div id="infoMesure" class="collapse show">
                                                <div class="card-body">

<p class="card-text">Suivez vos modules et jours de formation par marque.</p>
</div>
                                                </div>
                                                </div>
                                                
                                            </div>
                                        </div>
                                        <!-- Graphique à droite -->
                                        <div class="col-lg-9">
                                            <div class="card custom-card h-100">
                                                <div class="card-body">
                                                    <div class="row mb-3 justify-content-center">
                                                        <div id="chart-container" class="w-100 mb-4">
                                                            <h3 class="text-center mb-4">2. Mon Plan de Formation par Marque</h3>
                                                            <div class="row justify-content-center">
                                                                <!-- Carte Modules de Formation -->
                                                                <div class="col-md-4 mb-16 text-center">
                                                                    <div class="card shadow h-100 custom-card text-center">
                                                                        <div class="card-body">
                                                                            <i class="fas fa-book-open fa-2x text-primary mb-2"></i>
                                                                            <p class="fs-3 fw-bold"><?php echo htmlspecialchars($numRecommended); ?></p>
                                                                            <h5 class="card-title">Modules de Formation</h5>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <!-- Carte Jours de Formation -->
                                                                <div class="col-md-4 mb-16 text-center">
                                                                    <div class="card shadow h-100 custom-card text-center">
                                                                        <div class="card-body">
                                                                            <i class="fas fa-calendar-alt fa-2x text-success mb-2"></i>
                                                                            <p class="fs-3 fw-bold">
                                                                                <?php
                                                                                // Récupération des valeurs
                                                                                $jours = htmlspecialchars($totalDuration['jours']);
                                                                                $heures = htmlspecialchars($totalDuration['heures']);

                                                                                // Affichage
                                                                                if ($heures == 0) {
                                                                                    // Afficher uniquement les jours
                                                                                    echo "<span class='min-w-70px'>{$jours}</span>";
                                                                                } else {
                                                                                    // Afficher les jours et les heures sur la même ligne avec un slash
                                                                                    echo "<span class='min-w-70px'>{$jours}</span> / <span class='min-w-70px'>{$heures}</span>";
                                                                                }
                                                                                ?>
                                                                            </p>
                                                                            <h5 class="card-title">Jours de Formation</h5>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <canvas id="chartjs-container" aria-label="Graphique du Plan de Formation par Marque" role="img"></canvas>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

            <?php } ?>
        </div>

        <?php include "./partials/footer.php"; ?>

        <!-- Scripts JavaScript -->
        <script>
            // Enregistrer le plugin Chart.js Datalabels
            Chart.register(ChartDataLabels, Zoom);
        </script>

        <!-- Passer les variables PHP au JavaScript -->
        <script>
            const variablesPHP = {
                numRecommended: <?php echo json_encode($numRecommended); ?>,
                numCompleted: <?php echo json_encode($numCompleted); ?>,
                totalDuration: <?php echo json_encode($totalDuration); ?>,
                brandScores: <?php echo json_encode($brandScores); ?>,
                brandFormationsMap: <?php echo json_encode($brandFormationsMap); ?>, // Ajouté
                brandHoursMap: <?php echo json_encode($brandHoursMap); ?> // Ajouté
            };

            // Afficher les variables dans la console du navigateur
            console.log("Variables PHP dans JS:", variablesPHP);
        </script>


        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Sélectionnez tous les boutons de toggle
                const toggleButtons = document.querySelectorAll('.toggle-info');

                toggleButtons.forEach(button => {
                    const target = document.querySelector(button.getAttribute('data-bs-target'));
                    const icon = button.querySelector('i');

                    // Écouter les événements de collapse
                    target.addEventListener('hidden.bs.collapse', () => {
                        icon.classList.remove('fa-minus');
                        icon.classList.add('fa-info-circle');
                        // Ajuster la largeur de la colonne
                        const col = button.closest('.col-lg-3');
                        col.classList.remove('col-lg-3');
                        col.classList.add('col-lg-1'); // Ajustez selon vos besoins
                    });

                    target.addEventListener('shown.bs.collapse', () => {
                        icon.classList.remove('fa-info-circle');
                        icon.classList.add('fa-minus');
                        // Réinitialiser la largeur de la colonne
                        const col = button.closest('.col-lg-1');
                        col.classList.remove('col-lg-1');
                        col.classList.add('col-lg-3'); // Ajustez selon vos besoins
                    });
                });
                // Récupérer les données PHP
                const brandScoresData = variablesPHP.brandScores;
                const brandLogos = <?php echo json_encode($brandLogos); ?>;
                const brandFormationsMap = variablesPHP.brandFormationsMap;
                const brandHoursMap = variablesPHP.brandHoursMap;

                const numBrands = brandScoresData.length;

                const labels = brandScoresData.map(d => d.x); // Noms des marques
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

                    const shiftRight = 25;

                    // Boucler sur les labels spécifiques pour placer les logos
                    specificLabels.forEach((label, index) => {
                        const xPos = xScale.getPixelForValue(index);
                        let yPos;

                        if (containerId === 'chart-container') {
                            yPos = chartArea.bottom + 280; // Ajuster selon les besoins
                        } else if (containerId === 'mesure-container') {
                            yPos = chartArea.bottom + 70; // Ajuster selon les besoins
                        } else {
                            yPos = chartArea.bottom + 70; // Valeur par défaut
                        }

                        // Créer l'élément image
                        const img = document.createElement('img');
                        img.src = brandLogos[label] ? `brands/${brandLogos[label]}` : `brands/default.png`;
                        img.style.position = 'absolute';
                        img.style.left = (xPos - 25 + shiftRight) + 'px'; // Centrer l'image (ajusté pour 50px de largeur)
                        img.style.top = yPos + 'px';
                        img.style.width = '60px';
                        img.style.height = '35px';
                        img.onerror = function() {
                            console.error(`Erreur de chargement de l'image : ${img.src}`);
                            img.src = 'brands/default.png';
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
                            label: 'Nombres de Modules de Formations proposés par Marque',
                            data: labels.map((brand, i) => ({
                                x: i,
                                y: dataValues[i]
                            })),
                            backgroundColor: colors,
                            pointRadius: 35, // Ajuster la taille des points
                            pointHoverRadius: 65,
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            zoom: {
                                pan: {
                                    enabled: numBrands >= 6,
                                    mode: 'x',
                                    speed: 20
                                },
                                zoom: {
                                    enabled: false
                                }
                            },
                            datalabels: {
                                anchor: 'center',
                                align: 'center',
                                color: '#000',
                                font: {
                                    size: 14,
                                    weight: 'bold'
                                },
                                formatter: function(value, context) {
                                    const i = context.dataIndex;
                                    const brand = labels[context.dataIndex];
                                    const modulesCount = brandFormationsMap[brand] !== undefined ? brandFormationsMap[brand] : '0';
                                    const score = dataValues[i] !== null ? dataValues[i] : 'N/A';
                                    return ` ${modulesCount} \n Modules \n(${score}%)`;
                                },
                                textAlign: 'center' // Centrer le texte
                            },
                            tooltip: {
                                enabled: true,

                                position: 'nearest',
                                yAlign: 'bottom',
                                xAlign: 'center',

                                callbacks: {
                                    label: function(context) {
                                        const i = context.dataIndex;
                                        const brand = labels[i];
                                        const score = dataValues[i] !== null ? dataValues[i] : 'N/A';
                                        const modulesCount = brandFormationsMap[brand] !== undefined ? brandFormationsMap[brand] : '0';
                                        const hours = brandHoursMap[brand] !== undefined ? brandHoursMap[brand] : '0';
                                        return [
                                            `Marque: ${brand}`,
                                            `Score: ${score}%`,
                                            `Modules de Formations: ${modulesCount}`,
                                            `Volume de Formation: ${hours} Jours`
                                        ];
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                type: 'linear',
                                position: 'bottom',
                                ticks: {
                                    display: false // Masquer les labels textuels
                                },
                                grid: {
                                    display: true,
                                    color: '#ccc'
                                },
                                min: -0.5,
                                max: labels.length - 0.5
                            },
                            y: {
                                type: 'linear',
                                beginAtZero: true,
                                min: 0,
                                max: 100,
                                title: {
                                    display: true,
                                    text: 'Score (%)',
                                    padding: 30
                                },
                                grid: {
                                    display: true,
                                    color: '#ccc'
                                },
                                ticks: {
                                    stepSize: 10,
                                    padding: 10
                                }
                            }
                        },
                        onClick: (evt, activeElements) => {
                            if (activeElements.length > 0) {
                                const index = activeElements[0].index;
                                if (colors[index] === '#fbfb65') { // Couleur warning
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

                // Initialisation du Scatter Chart "Mesure de Compétences"
                const ctxScatterMesure = document.getElementById('myChartMesure').getContext('2d');
                const scatterChartMesure = new Chart(ctxScatterMesure, {
                    type: 'scatter',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Mes Notes aux Tests par Marque (%)',
                            data: scatterMesureData,
                            backgroundColor: scatterMesureData.map(d => d.pointBackgroundColor),
                            borderColor: '#aaaaa7',
                            borderWidth: scatterMesureData.map(d => d.pointBorderWidth),
                            pointRadius: scatterMesureData.map(d => d.pointRadius),
                            pointHoverRadius: 40,
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            zoom: {
                                pan: {
                                    enabled: numBrands >= 6,
                                    mode: 'x',
                                    speed: 20
                                },
                                zoom: {
                                    enabled: false
                                }
                            },
                            datalabels: {
                                color: function(context) {
                                    const score = context.dataset.data[context.dataIndex].y;
                                    if (score >= 80) {
                                        return '#000'; // Vert
                                    } else if (score >= 0 && score < 80) {
                                        return '#000'; // Orange
                                    } else {
                                        return '#000'; // Noir pour les autres cas
                                    }
                                },
                                font: {
                                    size: 17,
                                    weight: 'bold'
                                },
                                align: 'center',
                                anchor: 'center',
                                formatter: function(value) {
                                    return (value.y === 0) ? '0%' : value.y + '%';
                                }
                            },
                            tooltip: {
                                enabled: false
                            }
                        },
                        scales: {
                            x: {
                                type: 'linear',
                                min: -0.5,
                                max: labels.length - 0.5,
                                grid: {
                                    color: '#ccc'
                                },
                                ticks: {
                                    display: false
                                }
                            },
                            y: {
                                type: 'linear',
                                min: 0,
                                max: 100,
                                title: {
                                    display: true,
                                    text: 'Résultats aux Tests (%)'
                                },
                                grid: {
                                    color: '#ccc'
                                },
                                ticks: {
                                    stepSize: 10,
                                    padding: 10
                                }
                            }
                        },
                        onClick: (evt, activeElements) => {
                            if (activeElements.length > 0) {
                                const index = activeElements[0].index;
                                if (scatterMesureData[index].pointBorderColor === '#ffc107') { // Couleur warning
                                    window.open(urls[index], '_blank');
                                }
                            }
                        }
                    },
                    plugins: [imagePluginScatterMesure, ChartDataLabels]
                });

                // Les appels directs à drawLogos sont gérés par les plugins après le rendu des graphiques
                // Vous pouvez les commenter ou les supprimer
                // drawLogos(scatterChart, 'chart-container', labels);
                // drawLogos(scatterChartMesure, 'mesure-container', labels);
            });
        </script>
    </body>

    </html>
<?php } ?>