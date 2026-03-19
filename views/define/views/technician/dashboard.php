<!--
Vue du dashboard pour les techniciens
Cette vue est incluse par TechnicianDashboardController
-->

<?php
// Ensure the currentPage is set before including the header
$GLOBALS['currentPage'] = 'dashboard';
require_once __DIR__ . '/../../partials/header.php';

// Helpers & widgets (matching DPS style)
require_once __DIR__ . "/../../components/helpers/helpers.php";
require_once __DIR__ . '/../../components/widgets/card-level.php';
require_once __DIR__ . '/../../components/widgets/title-main.php';
require_once __DIR__ . '/../../components/widgets/title-section.php';
require_once __DIR__ . '/../../components/widgets/histogram-row.php';
?>

<style>
    body {
        background: linear-gradient(rgba(0,0,0,0.2), rgba(0,0,0,0.1)), url('/MEDACAP/public/images/welcome_tech.png') no-repeat center center fixed !important;
        background-size: cover !important;
    }
    #kt_content {
        background: transparent !important;
    }
    .wrapper {
        background: transparent !important;
    }
</style>

<div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content" style="position: relative; background: none;">

    <!-- Overlay chargement -->
    <div id="loadingOverlay" class="position-fixed top-0 start-0 w-100 h-100 d-none"
         style="background-color: rgba(27, 24, 24, 0.85); z-index: 9999;">
        <div class="d-flex justify-content-center align-items-center h-100">
            <div class="spinner-grow text-primary" role="status" style="width:3rem; height:3rem;">
                <span class="visually-hidden">Chargement...</span>
            </div>
        </div>
    </div>

    <div class="container-fluid mt-4 px-4">
        <div class="row">
            <div class="col-12">
                <!-- Titres Premium -->
                <?php renderTitleMain('ki-duotone ki-user fs-2 me-5', 3,
                    ($tableau ?? "Tableau de Bord") . " - " . strtoupper($this->technicianDoc['fullname'] ?? 'Technicien')); ?>

                <!-- Marques du Technicien -->
                <div class="mb-5">
                    <?php renderTitleSection('ki-duotone ki-setting-2 fs-2 me-3', 2, 
                        sprintf("%02d", count($brandsToShow)) . ' Marques sur lesquelles j\'interviens en Atelier'); ?>
                    
                    <div class="mt-4">
                        <?php include __DIR__ . "/brand_carousel.php"; ?>
                    </div>
                </div>

                <!-- Plan de Formation -->
                <div class="mt-9 mb-4">
                    <?php renderTitleSection('ki-duotone ki-chart-line-star fs-2 me-3', 2, 'Mon Plan de Formation par Marque'); ?>
                </div>

                <!-- KPI Cards (Style DPS strict) -->
                <div class="row g-4 mb-6" id="kpiRow">
                    <div class="col-md-4">
                        <div class="card stats-card h-100 kpi-card" style="background: rgba(255, 255, 255, 0.4); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.3); border-radius: 12px; overflow: hidden;">
                            <div class="card-header-bg py-3 px-4" style="background: #ffffff; border-bottom: 1px solid rgba(0,0,0,0.05);">
                                <h5 class="card-title text-dark mb-0 text-uppercase fs-6 fw-bold">FORMATIONS PROPOSÉES</h5>
                            </div>
                            <div class="card-body text-center d-flex flex-column justify-content-center p-6">
                                <i class="fas fa-book-reader fs-1 text-primary mb-3"></i>
                                <div class="big-number mb-1 fs-1 fw-bold text-dark" data-kt-countup="true" data-kt-countup-value="<?php echo count($numRecommended); ?>">
                                    <?php echo count($numRecommended); ?>
                                </div>
                                <div class="fs-6 fw-bold text-dark">Formations Proposées</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stats-card h-100 kpi-card" style="background: rgba(255, 255, 255, 0.4); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.3); border-radius: 12px; overflow: hidden;">
                            <div class="card-header-bg py-3 px-4" style="background: #ffffff; border-bottom: 1px solid rgba(0,0,0,0.05);">
                                <h5 class="card-title text-dark mb-0 text-uppercase fs-6 fw-bold">FORMATIONS COMPLÉTÉES</h5>
                            </div>
                            <div class="card-body text-center d-flex flex-column justify-content-center p-6">
                                <i class="fas fa-check-circle fs-1 text-success mb-3"></i>
                                <div class="big-number mb-1 fs-1 fw-bold text-dark" data-kt-countup="true" data-kt-countup-value="<?php echo count($numCompleted); ?>">
                                    <?php echo count($numCompleted); ?>
                                </div>
                                <div class="fs-6 fw-bold text-dark">Formations Complétées</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stats-card h-100 kpi-card" style="background: rgba(255, 255, 255, 0.4); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.3); border-radius: 12px; overflow: hidden;">
                            <div class="card-header-bg py-3 px-4" style="background: #ffffff; border-bottom: 1px solid rgba(0,0,0,0.05);">
                                <h5 class="card-title text-dark mb-0 text-uppercase fs-6 fw-bold">DURÉE TOTALE (H)</h5>
                            </div>
                            <div class="card-body text-center d-flex flex-column justify-content-center p-6">
                                <i class="fas fa-clock fs-1 text-warning mb-3"></i>
                                <div class="big-number mb-1 fs-1 fw-bold text-dark" data-kt-countup="true" data-kt-countup-value="<?php echo $brandHoursMap ?>">
                                    <?php echo $brandHoursMap ?>
                                </div>
                                <div class="fs-6 fw-bold text-dark">Jours de Formation (H)</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Graphique Principal -->
                <div class="card mb-8" style="background: rgba(255, 255, 255, 0.4); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.3); border-radius: 12px; overflow: hidden;">
                    <div class="card-header-bg py-4 px-6" style="background: #ffffff; border-bottom: 1px solid rgba(0,0,0,0.05);">
                        <h5 class="card-title text-dark mb-0 text-uppercase fw-bold fs-5">ANALYSE DE MON PLAN DE FORMATION</h5>
                    </div>
                    <div class="card-body">
                        <?php 
                        $isChartEmpty = true;
                        if (!empty($brandScores)) {
                            foreach ($brandScores as $d) {
                                if ($d['y'] !== null && $d['y'] !== 0) {
                                    $isChartEmpty = false;
                                    break;
                                }
                            }
                        }
                        if ($isChartEmpty): ?>
                            <div class="d-flex flex-column align-items-center justify-content-center py-10 my-5">
                                <i class="ki-duotone ki-information fs-3x text-white mb-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                <h3 class="text-white fw-bold text-center">En attente des Résultats de la mesure des compétences</h3>
                                <p class="text-white text-center mt-2">Aucune donnée n'est encore disponible pour générer ce graphique.</p>
                            </div>
                        <?php else: ?>
                            <div id="chart-container" class="responsive-chart-container" style="position: relative;">
                                <canvas id="chartjs-container" style="min-height: 400px;"></canvas>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Enregistrer le plugin Chart.js Datalabels
    if (typeof Chart !== 'undefined' && typeof ChartDataLabels !== 'undefined') {
        Chart.register(ChartDataLabels);
    }

    // Initialisation du graphique
    const brandScores = <?php echo json_encode($brandScores); ?>;
    const brandLogos = {
        'RENAULT TRUCK': 'renaultTrucks.png', 'HINO': 'hino_logo.png', 'TOYOTA BT': 'bt.png',
        'SINOTRUK': 'sinotruk.png', 'JCB': 'jcb.png', 'MERCEDES TRUCK': 'mercedestruck.png',
        'TOYOTA FORKLIFT': 'forklift.png', 'FUSO': 'fuso.png', 'LOVOL': 'lovol.png',
        'KING LONG': 'kl2.png', 'MERCEDES': 'mercedestruck.png', 'TOYOTA': 'toyota-logo.png',
        'SUZUKI': 'suzuki-logo.png', 'PEUGEOT': 'peugeot-logo.png', 'MITSUBISHI': 'mitsubishi-logo.png'
    };
    const brandFormationsMap = <?php echo json_encode($brandFormationsMap ?? []); ?>;

    if (document.getElementById('chartjs-container')) {
        const ctx = document.getElementById('chartjs-container').getContext('2d');
        
        const labels = brandScores.map(d => d.x);
        const dataValues = brandScores.map(d => d.y);
        const colors = brandScores.map(d => d.fillColor);
        
        function drawLogos(chart) {
            const oldDiv = document.getElementById('chart-container-logo-container');
            if (oldDiv) oldDiv.remove();

            const logoContainer = document.createElement('div');
            logoContainer.id = 'chart-container-logo-container';
            logoContainer.style.position = 'absolute';
            logoContainer.style.top = '0';
            logoContainer.style.left = '0';
            logoContainer.style.width = '100%';
            logoContainer.style.height = '100%';
            logoContainer.style.pointerEvents = 'none';

            const xScale = chart.scales.x;
            const chartArea = chart.chartArea;

            labels.forEach((label, index) => {
                const xPos = xScale.getPixelForValue(index);
                const yPos = chartArea.bottom + 15;

                const img = document.createElement('img');
                img.src = brandLogos[label] ? `/MEDACAP/public/images/${brandLogos[label]}` : `/MEDACAP/public/images/default.png`;
                img.style.position = 'absolute';
                img.style.left = (xPos - 27) + 'px';
                img.style.top = yPos + 'px';
                img.style.width = '55px';
                img.style.height = '30px';

                logoContainer.appendChild(img);
            });

            document.getElementById('chart-container').appendChild(logoContainer);
        }
        
        const imagePluginScatter = {
            id: 'imagePluginScatter',
            afterRender: (chart) => drawLogos(chart),
            afterResize: (chart) => {
                const logoContainer = document.getElementById('chart-container-logo-container');
                if (logoContainer) logoContainer.remove();
                drawLogos(chart);
            }
        };
        
        if (typeof Chart !== 'undefined') {
            new Chart(ctx, {
                type: 'scatter',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Avancement par Marque (%)',
                        data: labels.map((brand, i) => ({
                            x: i,
                            y: dataValues[i]
                        })),
                        backgroundColor: colors,
                        borderColor: '#fff',
                        borderWidth: 3,
                        pointRadius: 40,
                        pointHoverRadius: 50,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        datalabels: {
                            color: '#fff',
                            font: { weight: 'bold', size: 11 },
                            formatter: (value, ctx) => {
                                const brand = labels[ctx.dataIndex];
                                const modulesCount = brandFormationsMap[brand] !== undefined ? brandFormationsMap[brand] : '0';
                                const score = dataValues[ctx.dataIndex] !== null ? dataValues[ctx.dataIndex] : 'N/A';
                                return `${modulesCount} Mod.\n(${score}%)`;
                            },
                            textAlign: 'center'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const brand = labels[context.dataIndex];
                                    const score = dataValues[context.dataIndex] !== null ? dataValues[context.dataIndex] : 'N/A';
                                    const modulesCount = brandFormationsMap[brand] !== undefined ? brandFormationsMap[brand] : '0';
                                    return [
                                        `Marque: ${brand}`,
                                        `Modules: ${modulesCount}`,
                                        `Score: ${score}%`
                                    ];
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            type: 'category',
                            labels: labels,
                            ticks: { display: false }
                        },
                        y: {
                            min: 0,
                            max: 110,
                            ticks: { stepSize: 20 }
                        }
                    }
                },
                plugins: [imagePluginScatter]
            });
        }
    }
});
</script>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
