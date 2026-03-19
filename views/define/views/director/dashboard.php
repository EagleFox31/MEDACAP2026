<?php
// Fichier : views/define/views/director/dashboard.php
// NOTE: Cette vue consomme les données injectées par DirectorDashboardController.
// Pas de connexion MongoDB ici, pas d'ancien FilterController.

// Ensure the currentPage is set before including the header
$GLOBALS['currentPage'] = 'dashboard';

// Helpers & widgets (identiques)
require_once __DIR__ . "/../../components/helpers/helpers.php";
require_once __DIR__ . '/../../components/widgets/card-level.php';
require_once __DIR__ . '/../../components/widgets/title-main.php';
require_once __DIR__ . '/../../components/widgets/title-section.php';
require_once __DIR__ . '/../../components/widgets/histogram-row.php';

// Filtres UI
require_once __DIR__ . '/../../components/filterCountry.php';
require_once __DIR__ . '/../../components/filterAgency.php';
require_once __DIR__ . '/../../components/filterLevel.php';
require_once __DIR__ . '/../../components/filterBrand.php';
require_once __DIR__ . '/../../components/filterTechnician.php';

// Services annexes existants (PIFService); pas de nouveau JS ni logique ici
require_once __DIR__ . '/../../services/PIFService.php';
$pifService = new \views\define\services\PIFService();

/** ------------------------------------------------------------------
 * Données injectées par DirectorDashboardController::prepareViewData()
 * (on force les types tableau pour éviter les P1006 d’Intelephense)
 * ------------------------------------------------------------------ */
$filters                 = isset($filters)                 && is_array($filters)                 ? $filters                 : (isset($data['filters'])                 && is_array($data['filters'])                 ? $data['filters']                 : []);
$globalStats             = isset($globalStats)             && is_array($globalStats)             ? $globalStats             : (isset($data['globalStats'])             && is_array($data['globalStats'])             ? $data['globalStats']             : []);
$brandScores             = isset($brandScores)             && is_array($brandScores)             ? $brandScores             : (isset($data['brandScores'])             && is_array($data['brandScores'])             ? $data['brandScores']             : []);
$brandStatsMap           = isset($brandStats)              && is_array($brandStats)              ? $brandStats              : (isset($data['brandStats'])              && is_array($data['brandStats'])              ? $data['brandStats']              : []);
$trainingStats           = isset($trainingStats)           && is_array($trainingStats)           ? $trainingStats           : (isset($data['trainingStats'])           && is_array($data['trainingStats'])           ? $data['trainingStats']           : []);
$trainingValidationStats = isset($trainingValidationStats) && is_array($trainingValidationStats) ? $trainingValidationStats : (isset($data['trainingValidationStats']) && is_array($data['trainingValidationStats']) ? $data['trainingValidationStats'] : []);
$technicianSummary       = isset($technicianSummary)       && is_array($technicianSummary)       ? $technicianSummary       : (isset($data['technicianSummary'])       && is_array($data['technicianSummary'])       ? $data['technicianSummary']       : []);
$subsidiaries            = isset($subsidiaries)            && is_array($subsidiaries)            ? $subsidiaries            : (isset($data['subsidiaries'])            && is_array($data['subsidiaries'])            ? $data['subsidiaries']            : []);
$agencies                = isset($agencies)                && is_array($agencies)                ? $agencies                : (isset($data['agencies'])                && is_array($data['agencies'])                ? $data['agencies']                : []);
$brands                  = isset($brands)                  && is_array($brands)                  ? $brands                  : (isset($data['brands'])                  && is_array($data['brands'])                  ? $data['brands']                  : []);
$technicians             = isset($technicians)             && is_array($technicians)             ? $technicians             : (isset($data['technicians'])             && is_array($data['technicians'])             ? $data['technicians']             : []);
$filterController        = $filterController        ?? ($data['filterController']        ?? null);

$brandLogos = [
    'RENAULT TRUCK'   => 'renaultTrucks.png', 'HINO' => 'hino_logo.png', 'TOYOTA BT' => 'bt.png',
    'SINOTRUK'        => 'sinotruk.png', 'JCB' => 'jcb.png', 'MERCEDES TRUCK' => 'mercedestruck.png',
    'TOYOTA FORKLIFT' => 'forklift.png', 'FUSO' => 'fuso.png', 'LOVOL' => 'lovol.png',
    'KING LONG'       => 'kl2.png', 'MERCEDES' => 'mercedestruck.png', 'TOYOTA' => 'toyota-logo.png',
    'SUZUKI'          => 'suzuki-logo.png', 'PEUGEOT' => 'peugeot-logo.png',
];

// Injection pour stats_tests.php (inchangé)
$GLOBALS['filtersForStats'] = $filters;
$statsTests = require __DIR__ . '/../../services/stats_tests.php';

/* ----------  infos droits utilisateur (sans appeler l'ancien controller) ---------- */
$isAdmin = in_array($_SESSION['profile'] ?? '', ['Super Admin', 'Directeur Groupe', 'Admin'], true);
$canSelectSubsidiary = in_array($_SESSION['profile'] ?? '', ['Super Admin', 'Directeur Groupe'], true);

/* ----------  datasets (recyclés depuis le controller)  ---------- */
$levelStats = [
    'juniorCount' => (int)($globalStats['juniorCount'] ?? 0),
    'seniorCount' => (int)($globalStats['seniorCount'] ?? 0),
    'expertCount' => (int)($globalStats['expertCount'] ?? 0),
];

// Si un technicien est ciblé, on invalide une liste pré-hydratée potentielle
if (($filters['technicianId'] ?? 'all') !== 'all') {
    unset($filters['availableBrands']);
}

/* ----------  agency options pour la filiale courante  ---------- */
$currentSubsidiary = $filters['subsidiary'] ?? 'all';
$agencyOptions = [];
// $agencies peut être déjà une liste simple OU une map par filiale
if (isset($agencies[$currentSubsidiary]) && is_array($agencies[$currentSubsidiary])) {
    $agencyOptions = $agencies[$currentSubsidiary];
} elseif (array_values($agencies) === $agencies) {
    // liste plate déjà prête
    $agencyOptions = $agencies;
}

/* ----------  disponibilité niveaux pour le sélecteur  ---------- */
$maxLevel = 'Junior';
if ($levelStats['expertCount'] > 0) {
    $maxLevel = 'Expert';
} elseif ($levelStats['seniorCount'] > 0) {
    $maxLevel = 'Senior';
}
if ($filterController && method_exists($filterController, 'getLevelsToInclude')) {
    $availableLevels = $filterController->getLevelsToInclude($maxLevel);
    if (!is_array($availableLevels)) $availableLevels = ['Junior']; // garde-fou
} else {
    $availableLevels = ($maxLevel === 'Expert')
        ? ['Junior', 'Senior', 'Expert']
        : (($maxLevel === 'Senior') ? ['Junior', 'Senior'] : ['Junior']);
}

/* ----------  technicien sélectionné (affichage éventuel)  ---------- */
$technicianSelected     = (($filters['technicianId'] ?? 'all') !== 'all');
$countrySelected        = (($filters['subsidiary']   ?? 'all') !== 'all');
$selectedTechnicianInfo = null;
if ($technicianSelected) {
    $selectedId = is_array($filters['technicianId']) ? $filters['technicianId'][0] : $filters['technicianId'];
    foreach ($technicians as $id => $name) {
        if ((string)$id === (string)$selectedId) {
            $selectedTechnicianInfo = ['_id' => $id, 'name' => $name];
            break;
        }
    }
}

/* ----------  PIF  ---------- */
$nbPifJunior   = $pifService->countPIFByExactLevel('Junior', $filters);
$nbPifSenior   = $pifService->countPIFByExactLevel('Senior', $filters);
$nbPifExpert   = $pifService->countPIFByExactLevel('Expert', $filters);
$nbValidJunior = $pifService->countUsersWithValidation('Junior', $filters);
$nbValidSenior = $pifService->countUsersWithValidation('Senior', $filters);
$nbValidExpert = $pifService->countUsersWithValidation('Expert', $filters);

/* ----------  Histogramme Formations proposées/validées par marque  ---------- */
$trainingsCountsForGraph2    = isset($trainingValidationStats['trainingsCounts'])   && is_array($trainingValidationStats['trainingsCounts'])   ? $trainingValidationStats['trainingsCounts']   : [];
$validationsCountsForGraph2  = isset($trainingValidationStats['validationsCounts']) && is_array($trainingValidationStats['validationsCounts']) ? $trainingValidationStats['validationsCounts'] : [];

// Nettoyage des marques sans données ; on travaille sur une liste de noms distincte
$brandNamesForDashboard = array_keys($brandStatsMap);
foreach ($brandNamesForDashboard as $idx => $brand) {
    if (!isset($trainingsCountsForGraph2[$brand]))   $trainingsCountsForGraph2[$brand]   = 0;
    if (!isset($validationsCountsForGraph2[$brand])) $validationsCountsForGraph2[$brand] = 0;
    if (($trainingsCountsForGraph2[$brand] + $validationsCountsForGraph2[$brand]) === 0) {
        unset($brandNamesForDashboard[$idx], $trainingsCountsForGraph2[$brand], $validationsCountsForGraph2[$brand]);
    }
}
$brandNamesForDashboard = array_values($brandNamesForDashboard); // réindexe

/* ----------  Helpers locaux (inchangés)  ---------- */
if (!function_exists('sanitizeOutput')) {
    function sanitizeOutput($data) {
        if (is_array($data)) return array_map('sanitizeOutput', $data);
        return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('calculatePercentage')) {
    function calculatePercentage($numerator, $denominator) {
        if ($denominator <= 0) return 0;
        return min(100, max(0, round(($numerator / $denominator) * 100)));
    }
}
if (!function_exists('getProgressClass')) {
    function getProgressClass($percentage) {
        if ($percentage >= 80) return 'bg-success';
        if ($percentage >= 50) return 'bg-warning';
        return 'bg-danger';
    }
}

/* ----------  KPI cartes niveaux  ---------- */
$levelCards = [
    ['id'=>'juniorTechnicians','count'=>(int)($globalStats['juniorCount'] ?? 0),'label'=>'Techniciens Junior','sep'=>'+'],
    ['id'=>'seniorTechnicians','count'=>(int)($globalStats['seniorCount'] ?? 0),'label'=>'Techniciens Senior','sep'=>'+'],
    ['id'=>'expertTechnicians','count'=>(int)($globalStats['expertCount'] ?? 0),'label'=>'Techniciens Expert','sep'=>'='],
    ['id'=>'allLevelsTechnicians','count'=>(int)($globalStats['totalTechnicians'] ?? 0),'label'=>'Techniciens Tous Niveaux Filiales','sep'=>null],
];

// % d’avancement (utilisé plus bas si besoin)
$measuredPercentage      = calculatePercentage((int)($globalStats['measuredCount']          ?? 0), (int)($globalStats['totalTechnicians'] ?? 0));
$withTrainingPercentage  = calculatePercentage((int)($globalStats['withTrainingCount']      ?? 0), (int)($globalStats['totalTechnicians'] ?? 0));
$validatedPercentage     = calculatePercentage((int)($globalStats['validatedTrainingCount'] ?? 0), (int)($globalStats['withTrainingCount'] ?? 0));
?>

<?php require_once __DIR__ . '/../../partials/header.php'; ?>
<!-- Styles -->
<link rel="stylesheet" href="<?= asset('../public/assets/css/dashboard.css') ?>">

<!-- CountUp.js (existant) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/countup.js/2.0.8/countUp.umd.min.js"></script>
<!-- ApexCharts (existant) -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<!-- Chart.js + datalabels (existant) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.3.0"></script>

<!--begin::Content-->
<div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content"
     style="position: relative; background: none;">
    <!-- Background décoratif (identique) -->
    <div style="position: absolute; top:0; left:0; width:100%; height:100%; z-index:-1; background-size:cover; background-position:center; border-radius:20px; overflow:hidden;">
        <div style="position:absolute; top:0; left:0; width:100%; height:100%; filter:blur(10px); transform:scale(1.05);
                    background-image: linear-gradient(rgba(0,0,0,0.2), rgba(0,0,0,0.1)), url('/MEDACAP/public/images/welcome_tech.png'); background-size:cover; background-position:center;">
        </div>
        <img src="/MEDACAP/views/define/views/director/tof_dashboard_tech.png" alt="Background"
             style="width:100%; height:100%; object-fit:cover; display:block; opacity:0;">
        <img src="/MEDACAP/views/define/views/director/tof_dashboard_tech.png" alt="Background"
             style="width:100%; height:100%; object-fit:cover; display:block; opacity:0;">
    </div>

    <!-- Overlay chargement -->
    <div id="loadingOverlay" class="position-fixed top-0 start-0 w-100 h-100 d-none"
         style="background-color: rgba(27, 24, 24, 0.85); z-index: 9999;">
        <div class="d-flex justify-content-center align-items-center h-100">
            <div class="spinner-grow text-primary" role="status" style="width:3rem; height:3rem;">
                <span class="visually-hidden">Chargement...</span>
            </div>
        </div>
    </div>

    <div class="container-fluid mt-4 px-0">
        <div class="row">
            <div class="col-12">
                <!-- Titres -->
                <?php renderTitleMain('ki-duotone ki-document fs-2 me-5', 3,
                    'PLAN INDIVIDUEL DE FORMATION (PIF) VALIDÉS PAR LES FILIALE'); ?>
                <?php renderTitleSection('ki-duotone ki-people fs-2 me-3', 4, 'Effectifs par Niveau'); ?>
                <br>

                <!-- Spinner -->
                <div class="loading-spinner" id="loadingSpinner">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                </div>

                <!-- KPI Niveaux -->
                <?php renderLevelRow($levelCards); ?>
                <br>

                <!-- Titre PIF validations -->
                <?php renderTitleSection('ki-duotone ki-check-circle fs-2 me-3', 2,
                    'PIFs proposés par l\'Academy par Niveau'); ?>

                <div class="d-flex flex-nowrap align-items-center justify-content-center mb-3 mt-3 fade-in overflow-auto"
                     style="animation-delay:.2s; gap:5px; padding:5px 0;margin-left:10px;margin-right:10px;">
                    <?php
                    $totalTechnicians = (int)($globalStats['totalTechnicians'] ?? 0);
                    $histograms = [
                        [
                            'id'=>'levelChart0','label'=>'JUNIOR','done'=>[
                                $statsTests['Junior']['done'],
                                $nbPifJunior,
                                $nbValidJunior
                            ],
                            'todo'=>[
                                (int)($globalStats['juniorCount'] ?? 0) - $statsTests['Junior']['done'],
                                (int)($globalStats['juniorCount'] ?? 0) - $nbPifJunior,
                                (int)($globalStats['juniorCount'] ?? 0) - $nbValidJunior
                            ],
                            'badge'=>'#8b5cf6',
                        ],
                        [
                            'id'=>'levelChart1','label'=>'SENIOR','done'=>[
                                $statsTests['Senior']['done'],
                                $nbPifSenior,
                                $nbValidSenior
                            ],
                            'todo'=>[
                                (int)($globalStats['seniorCount'] ?? 0) - $statsTests['Senior']['done'],
                                (int)($globalStats['seniorCount'] ?? 0) - $nbPifSenior,
                                (int)($globalStats['seniorCount'] ?? 0) - $nbValidSenior
                            ],
                            'badge'=>'#8b5cf6',
                        ],
                        [
                            'id'=>'levelChart2','label'=>'EXPERT','done'=>[
                                $statsTests['Expert']['done'],
                                $nbPifExpert,
                                $nbValidExpert
                            ],
                            'todo'=>[
                                (int)($globalStats['expertCount'] ?? 0) - $statsTests['Expert']['done'],
                                (int)($globalStats['expertCount'] ?? 0) - $nbPifExpert,
                                (int)($globalStats['expertCount'] ?? 0) - $nbValidExpert
                            ],
                            'badge'=>'#8b5cf6',
                        ],
                        [
                            'id'=>'levelChart3','label'=>'TOUS LES NIVEAUX','done'=>[
                                $statsTests['Total']['done'],
                                $totalProposed  = $nbPifJunior + $nbPifSenior + $nbPifExpert,
                                $totalValidatedUsers = $nbValidJunior + $nbValidSenior + $nbValidExpert
                            ],
                            'todo'=>[
                                $statsTests['Total']['total'] - $statsTests['Total']['done'],
                                $totalTechnicians - $totalProposed,
                                $totalTechnicians - $totalValidatedUsers
                            ],
                            'badge'=>'#6366f1',
                        ],
                    ];

                    // Calcul du total des formations validées APRÈS les histogrammes (somme des validations par marque)
                    $totalValidated = array_sum($validationsCountsForGraph2);

                    // Données sérialisées pour le JS existant (on n'écrase pas $brandStatsMap)
                    $dashboardData = [
                        'brandScores'                => $brandScores,
                        'brandStats'                 => $brandNamesForDashboard, // <— liste de marques
                        'trainingStats'              => $trainingStats,
                        'trainingsCountsForGraph2'   => $trainingsCountsForGraph2,
                        'validationsCountsForGraph2' => $validationsCountsForGraph2,
                        'brandHours'                 => (int)($globalStats['trainingDays'] ?? 0),
                        'totalValidated'             => $totalValidated,
                        'brandLogos'                 => $brandLogos,
                        'agencyOptions'              => $agencyOptions,
                        'filtersInitial'             => $filters,
                    ];
                    ?>
                </div>
                <?php
                echo '<div class="d-flex flex-nowrap align-items-center justify-content-center mb-3 mt-3 fade-in overflow-auto"
                           style="animation-delay:.2s; gap:5px; padding:5px 0;">';
                renderHistogramRow($histograms);
                echo '</div>';
                ?>

                <script>
                    // JS existant inchangé (ApexCharts histogrammes)
                    document.addEventListener('DOMContentLoaded', () => {
                        document.querySelectorAll('.histogram-card').forEach(card => {
                            if (card.dataset.chartInit === '1') return;
                            card.dataset.chartInit = '1';

                            const doneArr = JSON.parse(card.dataset.done || '[]');
                            const todoArr = JSON.parse(card.dataset.todo || '[]');
                            const color   = card.dataset.color || '#8b5cf6';
                            const target  = card.querySelector('.histogram');

                            const doneRaw = [doneArr[0] || 0, doneArr[1] || 0, doneArr[2] || 0];
                            const todoRaw = [todoArr[0] || 0, todoArr[1] || 0, todoArr[2] || 0];
                            const totals = [doneRaw[0] + todoRaw[0], doneRaw[1] + todoRaw[1], doneRaw[2] + todoRaw[2]];
                            
                            // Convertir en pourcentages pour l'affichage
                            const d = totals.map((total, i) => total > 0 ? (doneRaw[i] / total) * 100 : 0);
                            const t = totals.map((total, i) => total > 0 ? (todoRaw[i] / total) * 100 : 0);

                            Object.assign(target.style, {
                                background:'#ffffff', borderRadius:'10px', padding:'8px 8px 4px 8px',
                                width:'100%', marginLeft:'0'
                            });

                            // Force une échelle fixe de 0 à 100 pour tous les histogrammes
                            const yMax = 100;
                            const step = 10;
                            const tickAmount = 10; // 0, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100
                            const categories = ['Tests complétés', 'PIF Proposés', 'PIF Validés'];

                            const options = {
                                series:[{name:'Réalisé',data:d},{name:'Non réalisé',data:t}],
                                chart:{type:'bar',height:300,stacked:true,toolbar:{show:false},fontFamily:'Inter, sans-serif',background:'transparent'},
                                plotOptions:{bar:{horizontal:false,columnWidth:'90%',endingShape:'flat',borderRadius:4,distributed:false}},
                                colors:[color,'#e2e8f0'],
                                dataLabels:{
                                    enabled:true,
                                    formatter:function(val,ctx){
                                        if (ctx.seriesIndex !== 0) return '';
                                        const i = ctx.dataPointIndex;
                                        return Math.round(d[i])+'%';
                                    },
                                    style:{fontSize:'14px',fontWeight:'bold',colors:['#FFFFFF']}
                                },
                                grid:{show:true,borderColor:'#e7e7e7',strokeDashArray:5,position:'back',
                                      xaxis:{lines:{show:true}},yaxis:{lines:{show:true}},
                                      row:{colors:['#f8f8f8','transparent'],opacity:0.5},
                                      padding:{top:10,right:10,bottom:10,left:10}},
                                stroke:{width:0},
                                xaxis:{categories:categories,labels:{rotate:0,hideOverlappingLabels:false,trim:false,
                                       style:{fontSize:'10px',fontWeight:600},offsetY:5},
                                       axisBorder:{show:true,color:'#e0e0e0'},
                                       axisTicks:{show:true,color:'#e0e0e0'},
                                       crosshairs:{show:true,stroke:{color:'#b6b6b6',width:1,dashArray:3}}},
                                yaxis:{min:0,max:yMax,tickAmount:tickAmount,forceNiceScale:false,decimalsInFloat:0,
                                       labels:{style:{fontSize:'12px'},offsetX:-8,formatter:function(val){return Math.round(val) + '%';}},
                                       title:{text:'Pourcentage',style:{fontSize:'12px',fontWeight:600}}},
                                tooltip:{enabled:true,shared:true,intersect:false,followCursor:false,
                                         fixed:{enabled:true,position:'topCenter',offsetY:-80},
                                         onDatasetHover:{highlightDataSeries:false},
                                         y:{formatter:function(val){return val;}},
                                         marker:{show:false},
                                         custom:function({series,dataPointIndex}){
                                            const doneCount=doneRaw[dataPointIndex]||0;
                                            const tot=totals[dataPointIndex]||0;
                                            const pct = Math.round(d[dataPointIndex]) || 0;
                                            return '<div class="custom-tooltip"><span style="font-size:14px;font-weight:bold;">' +
                                                "État d'avancement : "+doneCount+'/'+tot + ' ('+pct+'%)' + '</span></div>';}},
                                legend:{show:false},
                                fill:{opacity:1,type:'solid'},
                                responsive:[
                                    {breakpoint:1200,options:{plotOptions:{bar:{columnWidth:'85%'}},
                                        dataLabels:{style:{fontSize:'12px'}},
                                        xaxis:{labels:{style:{fontSize:'10px'},rotate:0}}}},
                                    {breakpoint:992,options:{plotOptions:{bar:{columnWidth:'80%'}},
                                        xaxis:{labels:{style:{fontSize:'9px'},rotate:0}},
                                        dataLabels:{style:{fontSize:'12px'}}}},
                                    {breakpoint:768,options:{plotOptions:{bar:{columnWidth:'72%'}},
                                        xaxis:{categories:['Tests\ncomplétés','PIF\nProposés','PIF\nValidés'],
                                            labels:{style:{fontSize:'9px'},rotate:0}},
                                        dataLabels:{style:{fontSize:'11px'}}}},
                                    {breakpoint:576,options:{plotOptions:{bar:{columnWidth:'65%'}},
                                        xaxis:{categories:['Tests','PIF Prop.','PIF Val.'],
                                            labels:{style:{fontSize:'8px'},rotate:0}},
                                        dataLabels:{style:{fontSize:'10px'}}}}
                                ]
                            };
                            const chart = new ApexCharts(target, options);
                            chart.render();
                        });
                    });
                </script>

                <?php renderTitleMain('ki-duotone ki-document fs-2 me-5', 3, "Proposition des Plans de Formations par l'Academy"); ?>

                <div class="row mb-5 mt-3">
                    <div class="col-md-9">
                        <!-- KPI Cards -->
                        <div id="kpiRow" class="row mb-4">
                            <div class="col-md-6">
                                <div class="card stats-card glass-effect depth-effect h-100 mb-3 kpi-card-trainings">
                                    <div class="card-header-bg"><h5 class="card-title mb-0">FORMATIONS </h5></div>
                                    <div class="card-body text-center">
                                        <i class="fas fa-file-alt fs-2 text-primary mb-2"></i>
                                        <div class="big-number mb-3" id="totalTrainings"
                                             data-value="<?php echo (int)($trainingStats['totalTrainings'] ?? 0); ?>">
                                            <?php echo number_format((int)($trainingStats['totalTrainings'] ?? 0)); ?>
                                        </div>
                                        <div style="font-weight:500;font-size:1rem;">
                                            Formations Proposées par l'Academy
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card stats-card glass-effect depth-effect h-100 mb-3 kpi-card-validated">
                                    <div class="card-header-bg"><h5 class="card-title mb-0">FORMATIONS VALIDÉES</h5></div>
                                    <div class="card-body text-center">
                                        <i class="fas fa-check-circle fs-2 text-success mb-2"></i>
                                        <div class="big-number mb-3" id="totalValidated"
                                             data-value="<?php echo $totalValidated; ?>">
                                            <?php echo number_format($totalValidated); ?>
                                        </div>
                                        <div style="font-weight:500;font-size:1rem;">
                                            Formations Validées par l'Academy
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Training Chart container (JS existant côté assets) -->
                        <div class="card glass-effect depth-effect mb-4">
                            <div class="card-header-bg training-header">
                                <h5 class="card-title mb-0">FORMATIONS PAR MARQUE</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container" style="height: 600px; position: relative;">
                                    <div id="trainingChart"></div>
                                    <div id="brandLogosContainer"
                                         style="position:absolute; bottom:110px; left:0; width:100%; height:40px; text-align:center;">
                                    </div>
                                    <?php if (($filters['technicianId'] ?? 'all') !== 'all'): ?>
                                        <div class="technician-specific-notice"
                                             style="position:absolute; top:10px; right:10px; background:rgba(255,255,255,0.8); padding:5px 10px; border-radius:5px; font-size:11px; color:#333;">
                                            <i class="fas fa-user-check"></i> Données spécifiques au technicien sélectionné
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Panneau filtres -->
                    <div class="col-md-3">
                        <div class="card glass-effect depth-effect h-100">
                            <div class="card-header-bg filter-header">
                                <i class="ki-duotone ki-filter fs-6 me-1"><span class="path1"></span><span class="path2"></span></i>
                                <h5 class="card-title mb-0 d-inline">FILTRE DES DONNÉES</h5>
                            </div>
                            <div class="card-body">
                                <div id="dynamicFilters">
                                    <div class="mb-3">
                                        <?php if (function_exists('renderFilterCountry')) {
                                            renderFilterCountry($filters['subsidiary'] ?? 'all', $subsidiaries, !$canSelectSubsidiary);
                                        } ?>
                                    </div>

                                    <div class="mb-3">
                                        <div id="agencyFilterWrapper" class="<?php echo !$countrySelected ? 'filter-disabled' : ''; ?>">
                                            <?php if (function_exists('renderFilterAgency')) {
                                                renderFilterAgency($filters['agency'] ?? 'all', $agencyOptions, $countrySelected);
                                            } ?>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div id="levelFilterWrapper">
                                            <?php if (function_exists('renderFilterLevel')) {
                                                renderFilterLevel($filters['level'] ?? 'all', $availableLevels, false, false);
                                            } ?>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div id="brandFilterWrapper">
                                            <?php if (function_exists('renderFilterBrand')) {
                                                renderFilterBrand($filters['brand'] ?? 'all', $brands, false, false);
                                            } ?>
                                        </div>
                                    </div>

                                    <!-- Manager Filter supprimé côté vue -->

                                    <div class="mb-3">
                                        <?php if (function_exists('renderFilterTechnician')) {
                                            renderFilterTechnician($filters['technicianId'] ?? 'all', $technicians, false, false);
                                        } ?>
                                    </div>
                                    <br><br>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tableau récap par filiale -->
                <?php if (($filters['subsidiary'] ?? 'all') === 'all' && !empty($technicianSummary) && ($_SESSION['profile'] ?? '') !== 'Directeur Groupe'): ?>
                    <div class="card glass-effect depth-effect mb-5 mt-5">
                        <div class="card-header"><h5 class="card-title mb-0">Récapitulatif par filiale</h5></div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover summary-table">
                                    <thead class="table-light">
                                    <tr>
                                        <th>Filiale</th>
                                        <th>Total Techniciens</th>
                                        <th>Junior</th>
                                        <th>Senior</th>
                                        <th>Expert</th>
                                        <th>Actions</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($technicianSummary as $subsidiary => $stats): ?>
                                        <tr>
                                            <td><strong><?php echo sanitizeOutput($subsidiary); ?></strong></td>
                                            <td><?php echo number_format((int)($stats['totalTechnicians'] ?? 0)); ?></td>
                                            <td><span class="badge bg-info"><?php echo number_format((int)($stats['juniorCount'] ?? 0)); ?></span></td>
                                            <td><span class="badge bg-secondary"><?php echo number_format((int)($stats['seniorCount'] ?? 0)); ?></span></td>
                                            <td><span class="badge bg-dark"><?php echo number_format((int)($stats['expertCount'] ?? 0)); ?></span></td>
                                            <td>
                                                <a href="?subsidiary=<?php echo urlencode($subsidiary); ?>" class="btn btn-sm btn-outline-primary">
                                                    Détails
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<!--end::Content-->

<!-- Données sérialisées pour le JS existant -->
<script id="chart-data">
window.DASHBOARD = <?= json_encode($dashboardData, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
window.chartData = window.DASHBOARD;
</script>

<!-- JS existant -->
<script src="<?= asset('../public/assets/js/define/dashboard.js') ?>"></script>

<script>
    // Debug (existant)
    console.log('Agencies available on load:', <?php echo json_encode($agencyOptions); ?>);
    console.log('Filters on load:', <?php echo json_encode($filters); ?>);

    document.addEventListener('DOMContentLoaded', function() {
        const techFilter = document.getElementById('filterTechnician');
        if (techFilter && techFilter.value !== 'all') {
            console.log('🔍 PAGE LOADED with technician selected:', techFilter.value);
            console.log('  - Current level:', document.getElementById('filterLevel')?.value);
            console.log('  - Current brand:', document.getElementById('filterBrand')?.value);

            setTimeout(() => {
                try {
                    const chartData = window.DASHBOARD;
                    console.log('📊 Initial chart data for technician:');
                    console.log('  - Training counts:', chartData.trainingsCountsForGraph2);
                    console.log('  - Validation counts:', chartData.validationsCountsForGraph2);
                    const techBrands = Object.keys(chartData.trainingsCountsForGraph2)
                        .filter(brand => chartData.trainingsCountsForGraph2[brand] > 0 ||
                                         chartData.validationsCountsForGraph2[brand] > 0);
                    console.log('  - Brands with data:', techBrands);
                } catch (e) {
                    console.error('Error parsing initial chart data:', e);
                }
            }, 500);
        }
    });
</script>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
