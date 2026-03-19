<?php
// coverage.php

// Activer l'affichage des erreurs (à désactiver en production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Démarrer la session si elle n'est pas déjà démarrée
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include_once "../language.php";

// Vérifiez si l'utilisateur est connecté et a le bon profil
if (!isset($_SESSION["profile"])) {
    header("Location: ../../");
    exit();
} else {
    require_once "../../vendor/autoload.php";

    // Connexion à MongoDB
    try {
        $mongoClient = new MongoDB\Client("mongodb://localhost:27017");
        $academy = $mongoClient->selectDatabase('academy'); // Assurez-vous que le nom de la base de données est correct
    } catch (MongoDB\Driver\Exception\Exception $e) {
        die("Erreur de connexion à MongoDB : " . $e->getMessage());
    }

    // Charger la configuration
    $configArray = require 'configGF.php';

    // Inclure les fichiers nécessaires
    require_once "coverageCalculator.php";
    require_once "scoreFunctions.php"; // Inclure ScoreCalculator

    // Initialiser le tableau de débogage
    $debug = [];

    // Appeler processRecommendations.php et récupérer les données
    $recommendationData = include_once "processRecommendations.php";

    // Vérifier que les données ont été correctement récupérées
    if (!is_array($recommendationData)) {
        die("Erreur lors de la récupération des données de recommandations.");
    }

    // Extraire les données nécessaires
    $filteredTechnicians = $recommendationData['technicians']; // Liste des techniciens filtrés
    $allScores = $recommendationData['scores']; // Scores factuels et déclaratifs
    $formattedTrainings = $recommendationData['trainings']; // Recommandations de formations formatées
    $formattedMissingGroups = $recommendationData['missingGroups']; // Besoins manquants formatés
    $debug = $recommendationData['debug']; // Informations de débogage



    // Convertir les BSONDocuments en tableaux associatifs
    $filteredTechniciansArray = [];
    foreach ($filteredTechnicians as $tech) {
        // Vérifiez si $tech est un objet BSONDocument et convertissez-le en tableau
        if (method_exists($tech, 'getArrayCopy')) {
            $filteredTechniciansArray[] = $tech->getArrayCopy();
        } else {
            // Sinon, assumez qu'il est déjà un tableau
            $filteredTechniciansArray[] = (array)$tech;
        }
    }

    // Instanciation des classes pour CoverageCalculator
    $configuration = new Configuration($configArray);
    $needDetector = new NeedDetector(80.0);
    $brandSupportChecker = new BrandSupportChecker($configuration);
    $needCollector = new NeedCollector($needDetector, $brandSupportChecker, $configuration);
    $coverageDeterminer = new CoverageDeterminer();
    $durationCalculator = new DurationCalculator();
    $kpiCalculator = new KpiCalculator($configuration, $durationCalculator);

    $coverageCalculator = new CoverageCalculator($needCollector, $coverageDeterminer, $kpiCalculator);

    // Définir les niveaux à considérer
    $levels = ['Junior', 'Senior', 'Expert'];

    // Calculer la couverture
    $resultCoverage = $coverageCalculator->calculateCoverage($filteredTechniciansArray, $allScores, $formattedTrainings, $levels);

    // Extraire les KPI et la couverture par marque
    $kpi = $resultCoverage['kpi'];
    $brandCoverage = $resultCoverage['brandCoverage'];

    // Calculer les besoins par marque pour l'histogramme
    $trainingsByBrand = [];
    foreach ($brandCoverage as $brand => $levelsData) {
        foreach ($levelsData as $level => $specData) {
            foreach ($specData as $spec => $data) {
                if (!isset($trainingsByBrand[$brand])) {
                    $trainingsByBrand[$brand] = 0;
                }
                $trainingsByBrand[$brand] += $data['totalBesoin'];
            }
        }
    }
    try {
        $connections = $academy->connections;
        $users = $academy->users;
        $trainings = $academy->trainings;
        $allocations = $academy->allocations;
        $connections = $academy->connections;
        $countApplyTraining = $academy->allocations->find(['status' => 'applied'])->toArray(); // Assurez-vous que la collection "connections" existe dans la base de données "academy".
    } catch (MongoDB\Driver\Exception\Exception $e) {
        die("Erreur de connexion à la collection connections : " . $e->getMessage());
    }
    
// Comptage des managers et autres utilisateurs
if ($_SESSION['profile'] == 'Directeur Groupe' || $_SESSION['profile'] == 'Super Admin') {
    // Extraire les données pour l'affichage
    $filterUsers = [
        'active' => true,
        'profile' => ['$in' => ['Technicien', 'Manager']] // Filtrer les techniciens et managers
    ];
    
    // Filtrer uniquement les managers qui ont "test: true"
    // Cela est nécessaire pour tous les managers peu importe le niveau
    $filterUsers['$or'] = [
        ['profile' => 'Technicien'], // Inclure les techniciens (en fonction des autres filtres)
        [
            'profile' => 'Manager',
            'test' => true // Inclure uniquement les managers qui ont passé un test
        ]
    ];
    
    // Extraire les données pour l'affichage
    $filterOthers = [
        'active' => true,
        'profile' => ['$in' => ['Admin', 'Directeur Filiale', 'Directeur Groupe', 'Ressource Humaine']] // Filtrer les techniciens et managers
    ];

    // Requête pour determiner le nombre d'utilisateur en ligne
    $countOnlineUser = $connections->find([
        '$and' => [
            [
                "status" => "Online",
                "active" => true,
            ],
        ],
    ])->toArray();
    $countOnlineUsers = count($countOnlineUser);

    // Requête pour determiner les techniciens
    $countUsers = $users->find($filterUsers)->toArray();

    // Requête pour determiner les managers
    $countManagers = $users->find([
        '$and' => [
            [
                'profile' => 'Manager',
                "active" => true
            ],
        ],
    ])->toArray();

    // Requête pour determiner les managers
    $countManagers = $users->find([
        '$and' => [
            [
                'profile' => 'Manager',
                "active" => true
            ],
        ],
    ])->toArray();

    // Requête pour determiner les autres profils
    $countOthers = $users->find($filterOthers)->toArray();
}


    // Variables d'affichage (à adapter selon votre texte)
    $tableau = "Votre Tableau de Bord";
    $technicienss = "Techniciens";
    $manageur = "Managers";
    $otherUsers = "Autres Utilisateurs";
    $recommaded_training = "Formations Recommandées";
    $apply_training = "Formations Appliquées";
    $user_online = "Utilisateurs en Ligne";
    $etat_avancement_training_tech = "État d'Avancement des Formations Techniques";
}
?>


<?php include_once "partials/header.php"; ?>
<!--begin::Title-->
<title><?php echo htmlspecialchars($tableau) ?> | CFAO Mobility Academy</title>
<!--end::Title-->

<!--begin::Content-->
<div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content">
    <?php if ($_SESSION["profile"] == "Super Admin" || $_SESSION["profile"] == "Directeur Groupe") { ?>
        <!--begin::Toolbar-->
        <div class="toolbar" id="kt_toolbar">
            <div class="container-fluid d-flex flex-stack flex-wrap flex-sm-nowrap">
                <!--begin::Info-->
                <div class="d-flex flex-column align-items-start justify-content-center flex-wrap me-2">
                    <!--begin::Title-->
                    <h1 class="text-dark fw-bold my-1 fs-2">
                        <?php echo htmlspecialchars($tableau) ?>
                    </h1>
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
                <div class="container-xxl">
                    <!--begin::Row-->
                    <div class="row g-6 g-xl-9 mb-6 mb-xl-9">
                        <!-- KPI Cards (existants) -->
                        <!-- Techniciens -->
                        <div class="col-md-6 col-lg-4 col-xl-2.5">
                            <div class="card h-100">
                                <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                    <div class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                        <div class="min-w-70px" data-kt-countup="true" data-kt-countup-value="<?php echo count($filteredTechniciansArray) ?>">
                                        </div>
                                    </div>
                                    <div class="fs-5 fw-bold mb-2">
                                        <?php echo htmlspecialchars($technicienss) ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Managers -->
                        <div class="col-md-6 col-lg-4 col-xl-2.5">
                            <div class="card h-100">
                                <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                    <div class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                        <div class="min-w-70px" data-kt-countup="true" data-kt-countup-value="<?php echo count($countManagers); ?>">
                                        </div>
                                    </div>
                                    <div class="fs-5 fw-bold mb-2">
                                        <?php echo htmlspecialchars($manageur) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Autres Utilisateurs -->
                        <div class="col-md-6 col-lg-4 col-xl-2.5">
                            <div class="card h-100">
                                <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                    <div class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                        <div class="min-w-70px" data-kt-countup="true" data-kt-countup-value="<?php echo count($countOthers); ?>">
                                        </div>
                                    </div>
                                    <div class="fs-5 fw-bold mb-2">
                                        <?php echo htmlspecialchars($otherUsers) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Formations Recommandées -->
                        <div class="col-md-6 col-lg-4 col-xl-2.5">
                            <div class="card h-100">
                                <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                    <div class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                        <div class="min-w-70px" data-kt-countup="true" data-kt-countup-value="<?php echo count($formattedTrainings); ?>">
                                        </div>
                                    </div>
                                    <div class="fs-5 fw-bold mb-2">
                                        <?php echo htmlspecialchars($recommaded_training) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Formations Appliquées -->
                        <div class="col-md-6 col-lg-4 col-xl-2.5">
                            <div class="card h-100">
                                <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                    <div class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                        <div class="min-w-70px" data-kt-countup="true" data-kt-countup-value="<?php echo count($countApplyTraining) ?>">
                                        </div>
                                    </div>
                                    <div class="fs-5 fw-bold mb-2">
                                        <?php echo htmlspecialchars($apply_training) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Utilisateurs en Ligne -->
                        <div class="col-md-6 col-lg-4 col-xl-2.5">
                            <div class="card h-100">
                                <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                    <div class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                        <div class="min-w-70px" data-kt-countup="true" data-kt-countup-value="<?php echo $countOnlineUsers ?>">
                                        </div>
                                    </div>
                                    <div class="fs-5 fw-bold mb-2">
                                        <?php echo htmlspecialchars($user_online) ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- KPI : Pourcentage Global de Couverture -->
                        <div class="col-md-6 col-lg-4 col-xl-2.5">
                            <div class="card h-100">
                                <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                    <div class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                        <div class="min-w-70px" data-kt-countup="true"
                                            data-kt-countup-value="<?php echo round($kpi['globalCoveragePercent'], 2); ?>"
                                            data-kt-countup-suffix="%"></div>
                                    </div>
                                    <div class="fs-5 fw-bold mb-2">
                                        Pourcentage Global de Couverture
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- KPI : Total des Besoins -->
                        <div class="col-md-6 col-lg-4 col-xl-2.5">
                            <div class="card h-100">
                                <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                    <div class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                        <?php echo $kpi['totalNeeds']; ?>
                                    </div>
                                    <div class="fs-5 fw-bold mb-2">
                                        Total des Besoins
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- KPI : Besoins Couverts -->
                        <div class="col-md-6 col-lg-4 col-xl-2.5">
                            <div class="card h-100">
                                <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                    <div class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                        <?php echo $kpi['totalCovered']; ?>
                                    </div>
                                    <div class="fs-5 fw-bold mb-2">
                                        Besoins Couverts
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- KPI : Durée Totale des Formations Recommandées -->
                        <div class="col-md-6 col-lg-4 col-xl-2.5">
                            <div class="card h-100">
                                <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                    <div class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                        <?php
                                        echo htmlspecialchars($kpi['totalDuration']['totalDays'] . ' Jours et ' . $kpi['totalDuration']['totalHours'] . ' Heures');
                                        ?>
                                    </div>
                                    <div class="fs-5 fw-bold mb-2">
                                        Durée Totale des Formations Recommandées
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Histogramme par Marque -->
                        <div class="col-md-12">
                            <div class="card h-100">
                                <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                    <h5 class="fs-5 fw-bold mb-4">Histogramme des Besoins par Marque</h5>
                                    <canvas id="brandHistogram" width="400" height="200"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Titre répartition couverts / non couverts -->
                        <div style="margin-top: 55px; margin-bottom: 25px">
                            <div>
                                <h6 class="text-dark fw-bold my-1 fs-2">
                                    Répartition des Besoins
                                </h6>
                            </div>
                        </div>

                        <!-- Pie Chart (Couverts / Non couverts) -->
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                    <canvas id="coveragePieChart" height="100"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Titre état d'avancement des formations -->
                        <div style="margin-top: 55px; margin-bottom : 25px">
                            <div>
                                <h6 class="text-dark fw-bold my-1 fs-2">
                                    <?php echo htmlspecialchars($etat_avancement_training_tech) ?>
                                </h6>
                            </div>
                        </div>

                        <!-- Row pour les charts des formations -->
                        <div>
                            <div id="chartTraining" class="row">
                                <!-- Les cartes dynamiques seront insérées ici via JS -->
                            </div>
                        </div>
                    </div>
                    <!--end::Row-->

                    <!-- Row pour l'histogramme par marque (si nécessaire) -->
                    <!-- Vous avez déjà un histogramme par marque ci-dessus, cette section peut être supprimée si redondante -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <canvas id="brandHistogram" height="100"></canvas>
                        </div>
                    </div>
                </div>
                <!--end::Container-->
            </div>
            <!--end::Post-->
        </div>
        <!--end::Content-->
    <?php } ?>
</div>
<!--end::Content-->
<?php include "./partials/footer.php"; ?>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    $(document).ready(function() {
        $('.dropdown-toggle').click(function() {
            var $dropdownContent = $('.dropdown-content');
            var isVisible = $dropdownContent.is(':visible');

            $dropdownContent.slideToggle();
            $(this).toggleClass('open', !isVisible);
        });
        $('.dropdown-toggle1').click(function() {
            var $dropdownContent = $('.dropdown-content1');
            var isVisible = $dropdownContent.is(':visible');

            $dropdownContent.slideToggle();
            $(this).toggleClass('open', !isVisible);
        });
    });
</script>
<?php if ($_SESSION['profile'] == 'Super Admin' || $_SESSION['profile'] == 'Directeur Groupe') { ?>
    <script>
        // Histogramme par Marque
        document.addEventListener('DOMContentLoaded', function() {
            const brandData = <?php echo json_encode(array_values($trainingsByBrand)); ?>;
            const brandLabels = <?php echo json_encode(array_keys($trainingsByBrand)); ?>;

            console.log('brandLabels:', brandLabels);
            console.log('brandData:', brandData);

            new Chart(document.getElementById('brandHistogram').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: brandLabels,
                    datasets: [{
                        label: 'Besoins par Marque',
                        data: brandData,
                        backgroundColor: 'rgba(54, 162, 235, 0.6)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        title: {
                            display: true,
                            text: 'Besoins par Marque'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Nombre de Besoins'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Marques'
                            }
                        }
                    }
                }
            });

            // Pie Chart Répartition Couvert / Non couvert
            const coverageDistribution = <?php echo json_encode($kpi['coverageDistribution']); ?>;
            const coverageLabels = Object.keys(coverageDistribution);
            const coverageData = Object.values(coverageDistribution);

            new Chart(document.getElementById('coveragePieChart').getContext('2d'), {
                type: 'pie',
                data: {
                    labels: coverageLabels,
                    datasets: [{
                        data: coverageData,
                        backgroundColor: [
                            'rgba(75, 192, 192, 0.7)', // Couverts
                            'rgba(255, 99, 132, 0.7)'  // Non Couverts
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: true,
                            text: 'Répartition des Besoins Couverts vs Non Couverts'
                        }
                    }
                }
            });

            // Graphiques pour les Formations du groupe
            // Data for each chart
            const chartData = [{
                    title: 'Formation Niveau Junior',
                    total: <?php echo count($countRecommandedTrainingJu) + count($countApplyTrainingJu) ?>,
                    completed: <?php echo count($countApplyTrainingJu) ?>, 
                    data: [<?php echo count($countApplyTrainingJu) ?>,
                        <?php echo ((count($countRecommandedTrainingJu) + count($countApplyTrainingJu)) - count($countApplyTrainingJu)) ?>
                    ],
                    labels: ['Formations réalisés', 'Formations restants à réaliser'],
                    backgroundColor: ['#4303ec', '#D3D3D3']
                },
                {
                    title: 'Formation Niveau Senior',
                    total: <?php echo count($countRecommandedTrainingSe) + count($countApplyTrainingSe) ?>,
                    completed: <?php echo count($countApplyTrainingSe) ?>,
                    data: [<?php echo count($countApplyTrainingSe) ?>,
                        <?php echo ((count($countRecommandedTrainingSe) + count($countApplyTrainingSe)) - count($countApplyTrainingSe)) ?>
                    ],
                    labels: ['Formations réalisés', 'Formations restants à réaliser'],
                    backgroundColor: ['#4303ec', '#D3D3D3']
                },
                {
                    title: 'Formation Niveau Expert',
                    total: <?php echo count($countRecommandedTrainingEx) + count($countApplyTrainingEx) ?>,
                    completed: <?php echo count($countApplyTrainingEx) ?>,
                    data: [<?php echo count($countApplyTrainingEx) ?>,
                        <?php echo ((count($countRecommandedTrainingEx) + count($countApplyTrainingEx)) - count($countApplyTrainingEx)) ?>
                    ],
                    labels: ['Formations réalisés', 'Formations restants à réaliser'],
                    backgroundColor: ['#4303ec', '#D3D3D3']
                },
            ];

            // Average for total 3 niveaux
            const averageCompleted = chartData[0].completed + chartData[1].completed + chartData[2].completed;
            const averageTotal = chartData[0].total + chartData[1].total + chartData[2].total;
            const averageData = [averageCompleted, averageTotal - averageCompleted];

            chartData.push({
                title: 'Total : 03 Niveaux',
                total: averageTotal,
                completed: averageCompleted,
                data: averageData,
                labels: ['Formations réalisés', 'Formations restants à réaliser'],
                backgroundColor: ['#4303ec', '#D3D3D3']
            });

            const container = document.getElementById('chartTraining');

            chartData.forEach((data, index) => {
                const completedPercentage = Math.round((data.completed / data.total) * 100);

                const cardHtml = `
                    <div class="col-md-6 col-lg-3 col-xl-2.5 mb-4">
                        <div class="card h-100">
                            <div class="card-body d-flex justify-content-center text-center flex-column p-4">
                                <h5>Total des Formations à réaliser: ${data.total}</h5>
                                <h5><strong>${completedPercentage}%</strong> des Formations réalisés</h5>
                                <canvas id="doughnutChart${index}" width="200" height="200"></canvas>
                                <h5 class="mt-2">${data.title}</h5>
                            </div>
                        </div>
                    </div>
                `;

                container.insertAdjacentHTML('beforeend', cardHtml);

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
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    generateLabels: function(chart) {
                                        const data = chart.data;
                                        return data.labels.map((label, i) => ({
                                            text: `${label}: ${data.datasets[0].data[i]}`,
                                            fillStyle: data.datasets[0].backgroundColor[i],
                                            strokeStyle: data.datasets[0].borderColor[i],
                                            lineWidth: data.datasets[0].borderWidth,
                                            hidden: false
                                        }));
                                    }
                                }
                            },
                            datalabels: {
                                formatter: (value, ctx) => {
                                    let sum = ctx.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                                    let percentage = Math.round((value / sum) * 100);
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
                                        return `Nombre: ${value}, Pourcentage: ${percentage}%`;
                                    }
                                }
                            }
                        }
                    }
                });
            });
        });
    </script>
<?php } ?>
</body>
</html>
