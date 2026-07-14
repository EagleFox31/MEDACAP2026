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

// Simulation pour l'utilisateur spécifique
$isSimulation = (isset($_SESSION['id']) && (string)$_SESSION['id'] === '66694d8e582300001b005963');
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
                <!-- Marques du Technicien -->
                <div class="mb-5">
                    <?php renderTitleSection('ki-duotone ki-setting-2 fs-2 me-3', 2, 
                        'Mes ' . sprintf("%02d", count($brandsToShow)) . ' marques sur lesquelles j\'interviens en Atelier', 'py-3', '100%'); ?>
                    
                    <div class="mt-4">
                        <?php include __DIR__ . "/brand_carousel.php"; ?>
                    </div>
                </div>

                <!-- Plan de Formation -->
                <div class="mt-15 mb-4">
                    <?php renderTitleSection('ki-duotone ki-chart-line-star fs-1 me-3', 1, 'Mon Volume de Formation', 'py-3', '100%'); ?>
                </div>

                <!-- KPI Cards (Style DPS strict) -->
                <div class="row g-4 mb-15" id="kpiRow">
                    <div class="col-md-4">
                        <div class="card stats-card h-100 kpi-card" style="background: rgba(255, 255, 255, 0.4); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.3); border-radius: 12px; overflow: hidden;">
                            <div class="card-header-bg py-3 px-4" style="background: #ffffff; border-bottom: 1px solid rgba(0,0,0,0.05);">
                                <h5 class="card-title text-dark mb-0 text-uppercase fs-6 fw-bold">FORMATIONS A FAIRE</h5>
                            </div>
                            <div class="card-body text-center d-flex flex-column justify-content-center p-6">
                                <i class="fas fa-book-reader fs-1 text-primary mb-3"></i>
                                <div class="big-number mb-1 fw-bold text-dark" style="font-size:4.5rem;line-height:1;" data-kt-countup="true" data-kt-countup-value="<?php echo $isSimulation ? 4 : count($numRecommended); ?>">
                                    <?php echo $isSimulation ? 4 : count($numRecommended); ?>
                                </div>
                                <div class="fs-5 fw-bold text-dark mt-2">Formations à faire</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stats-card h-100 kpi-card" style="background: rgba(255, 255, 255, 0.4); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.3); border-radius: 12px; overflow: hidden;">
                            <div class="card-header-bg py-3 px-4" style="background: #ffffff; border-bottom: 1px solid rgba(0,0,0,0.05);">
                                <h5 class="card-title text-dark mb-0 text-uppercase fs-6 fw-bold">FORMATIONS EN COURS</h5>
                            </div>
                            <div class="card-body text-center d-flex flex-column justify-content-center p-6">
                                <i class="fas fa-clock fs-1 text-warning mb-3"></i>
                                <?php // Logique pour les formations en cours à implémenter ?>
                                <div class="big-number mb-1 fw-bold text-dark" style="font-size:4.5rem;line-height:1;" data-kt-countup="true" data-kt-countup-value="0">
                                    0
                                </div>
                                <div class="fs-5 fw-bold text-dark mt-2">Formations en cours</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stats-card h-100 kpi-card" style="background: rgba(255, 255, 255, 0.4); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.3); border-radius: 12px; overflow: hidden;">
                            <div class="card-header-bg py-3 px-4" style="background: #ffffff; border-bottom: 1px solid rgba(0,0,0,0.05);">
                                <h5 class="card-title text-dark mb-0 text-uppercase fs-6 fw-bold">FORMATIONS FAITES</h5>
                            </div>
                            <div class="card-body text-center d-flex flex-column justify-content-center p-6">
                                <i class="fas fa-check-circle fs-1 text-success mb-3"></i>
                                <div class="big-number mb-1 fw-bold text-dark" style="font-size:4.5rem;line-height:1;" data-kt-countup="true" data-kt-countup-value="<?php echo count($numCompleted); ?>">
                                    <?php echo count($numCompleted); ?>
                                </div>
                                <div class="fs-5 fw-bold text-dark mt-2">Formations faites</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Graphique Principal -->
                <?php
                $_levelFilter  = $levelFilter ?? 'all';
                $_techLevel    = $techLevel ?? 'Junior';
                $_filterBrand  = $filterBrand ?? 'all';
                $_tidParam     = isset($_GET['technicianId']) ? '&technicianId=' . urlencode($_GET['technicianId']) : '';
                $_lvlParam     = $_levelFilter !== 'all' ? '&level=' . urlencode($_levelFilter) : '';
                $availLevels   = ['Junior'];
                if (in_array($_techLevel, ['Senior', 'Expert'])) $availLevels[] = 'Senior';
                if ($_techLevel === 'Expert') $availLevels[] = 'Expert';
                // URL de base pour les filtres de niveau (préserve la marque si active)
                $_brandParamForLevel = $_filterBrand !== 'all' ? '&brand=' . urlencode($_filterBrand) : '';
                ?>
                <div class="card mb-8" style="background: rgba(255, 255, 255, 0.4); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.3); border-radius: 12px; overflow: hidden;">
                    <div class="card-header-bg py-4 px-6 d-flex align-items-center justify-content-between flex-wrap gap-3" style="background: #ffffff; border-bottom: 1px solid rgba(0,0,0,0.05);">
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <h5 class="card-title text-dark mb-0 text-uppercase fw-bold fs-5">RESULTATS DE LA MESURE ET VOLUME DE FORMATION</h5>
                            <?php if ($_filterBrand !== 'all'): ?>
                            <span class="badge bg-warning text-dark fw-bold fs-6 px-3 py-2">
                                <?= htmlspecialchars($_filterBrand) ?>
                                <a href="?level=<?= urlencode($_levelFilter) . $_tidParam ?>" class="ms-2 text-dark text-decoration-none" title="Effacer le filtre marque">&#10005;</a>
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="?level=all<?= $_tidParam . $_brandParamForLevel ?>" class="btn btn-sm <?= $_levelFilter === 'all' ? 'btn-primary' : 'btn-outline-secondary' ?>">Tous</a>
                            <?php foreach ($availLevels as $lvl): ?>
                            <a href="?level=<?= $lvl . $_tidParam . $_brandParamForLevel ?>" class="btn btn-sm <?= $_levelFilter === $lvl ? 'btn-warning text-dark fw-bold' : 'btn-outline-secondary' ?>"><?= $lvl ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="card-body p-0">
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
                            <div id="chart-container" class="responsive-chart-container mt-n12" style="position: relative;">
                                <canvas id="chartjs-container" style="min-height: 550px;"></canvas>
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
    var brandScores      = <?php echo json_encode($brandScores); ?>;
    var brandLogos = {
        'RENAULT TRUCK': 'renaultTrucks.png', 'HINO': 'hino_logo.png', 'TOYOTA BT': 'bt.png',
        'SINOTRUK': 'sinotruk.png', 'JCB': 'jcb.png', 'MERCEDES TRUCK': 'mercedestruck.png',
        'TOYOTA FORKLIFT': 'forklift.png', 'FUSO': 'fuso.png', 'LOVOL': 'lovol.png',
        'KING LONG': 'kl2.png', 'MERCEDES': 'mercedestruck.png', 'TOYOTA': 'toyota-logo.png',
        'SUZUKI': 'suzuki-logo.png', 'PEUGEOT': 'peugeot-logo.png', 'MITSUBISHI': 'mitsubishi-logo.png'
    };
    var brandFormationsMap = <?php echo json_encode($brandFormationsMap ?? []); ?>;
    var isSimu = <?php echo json_encode($isSimulation); ?>;

    if (isSimu) {
        // Appliquer les valeurs de simulation demandées
        if (brandFormationsMap['RENAULT TRUCK'] !== undefined) brandFormationsMap['RENAULT TRUCK'] = 2;
        
        // Simulation des modules par niveau
        brandScores.forEach(function(d) {
            if (d.x === 'Junior') {
                brandFormationsMap[d.x] = 1;
                d.y = 83; // Exemple de score pour la simulation visuelle
            }
            if (d.x === 'Senior') {
                brandFormationsMap[d.x] = 1;
                d.y = 72; // Exemple de score
            }
            // Si "Renault Truck" est présent dans les labels
            if (d.x === 'RENAULT TRUCK') {
                brandFormationsMap[d.x] = 2;
            }
        });
        
        // S'assurer que les dataValues sont synchronisés avec les scores modifiés
        dataValues = brandScores.map(function(d) { return d.y; });
    }

    if (!document.getElementById('chartjs-container')) return;

    var ctx         = document.getElementById('chartjs-container').getContext('2d');
    var labels      = brandScores.map(function(d) { return d.x; });
    var dataValues  = brandScores.map(function(d) { return d.y; });
    var colors      = brandScores.map(function(d) { return d.fillColor; });
    var BUBBLE_R    = 58;
    var currentLevel = <?php echo json_encode($_levelFilter); ?>;
    var currentBrand = <?php echo json_encode($_filterBrand); ?>;
    var tidParam     = <?php echo json_encode($_tidParam); ?>;

    /* ── Plugin 0 : texte dessiné directement sur les bulles (Modules) ── */
    var bubbleTextPlugin = {
        id: 'bubbleText',
        afterDatasetsDraw: function(chart) {
            var c    = chart.ctx;
            var meta = chart.getDatasetMeta(0);
            meta.data.forEach(function(pt, i) {
                var modsRaw = brandFormationsMap[labels[i]] !== undefined ? parseInt(brandFormationsMap[labels[i]]) : 0;
                // Padding à 2 chiffres (01, 02, etc.)
                var mods = modsRaw < 10 ? '0' + modsRaw : String(modsRaw);
                
                c.save();
                c.textAlign    = 'center';
                c.textBaseline = 'middle';
                c.fillStyle    = '#ffffff';
                c.font         = 'bold 12px sans-serif';

                // Dessin multi-ligne : XX / Module(s) / de / formation
                var lineModule = (modsRaw < 2) ? 'Module' : 'Modules';
                var lines = [mods, lineModule, 'de', 'formation'];
                var lineHeight = 16; // Interligne un peu plus grand
                var totalHeight = lines.length * lineHeight;
                var startY = pt.y - (totalHeight / 2) + (lineHeight / 2);

                lines.forEach(function(line, idx) {
                    var y = startY + (idx * lineHeight);
                    if (idx === 0) {
                        c.font = 'bold 26px sans-serif'; // Chiffres bien plus gros
                        c.fillText(line, pt.x, y - 4);
                        c.font = 'bold 11px sans-serif';
                    } else {
                        // Petit décalage pour le mot "Modules" pour créer l'espace visuel d'un saut de ligne supplémentaire
                        var offset = (idx === 1) ? 6 : 0; 
                        c.fillText(line, pt.x, y + offset);
                    }
                });
                c.restore();
            });
        }
    };

    /* ── Plugin Score : Mini card au-dessus de la bulle ── */
    var scoreLabelPlugin = {
        id: 'scoreLabel',
        afterDatasetsDraw: function(chart) {
            var c    = chart.ctx;
            var meta = chart.getDatasetMeta(0);
            meta.data.forEach(function(pt, i) {
                var score = dataValues[i] !== null ? dataValues[i] + '%' : '0%';
                
                c.save();
                var labelW = 150; 
                var labelH = 45; // Hauteur augmentée pour plus d'espace
                var x = pt.x - (labelW / 2);
                var y = pt.y - BUBBLE_R - labelH - 10;

                // Background mini-card
                c.shadowColor = 'rgba(0,0,0,0.15)';
                c.shadowBlur = 8;
                c.shadowOffsetY = 3;
                c.fillStyle = '#ffffff';
                
                var r = 8;
                c.beginPath();
                c.moveTo(x + r, y);
                c.lineTo(x + labelW - r, y);
                c.quadraticCurveTo(x + labelW, y, x + labelW, y + r);
                c.lineTo(x + labelW, y + labelH - r);
                c.quadraticCurveTo(x + labelW, y + labelH, x + labelW - r, y + labelH);
                c.lineTo(x + r, y + labelH);
                c.quadraticCurveTo(x, y + labelH, x, y + labelH - r);
                c.lineTo(x, y + r);
                c.quadraticCurveTo(x, y, x + r, y);
                c.closePath();
                c.fill();

                // Texte Label
                c.shadowBlur = 0;
                c.shadowOffsetY = 0;
                c.textAlign = 'center';
                c.fillStyle = '#666666';
                c.font = 'bold 11px sans-serif';
                c.fillText('RÉSULTAT DE LA MESURE', pt.x, y + 16);

                // Texte Score (Couleur de la bulle)
                c.fillStyle = colors[i];
                c.font = 'bold 20px sans-serif'; // Score plus gros
                c.fillText(score, pt.x, y + 34); // Positionné plus bas pour l'espace
                
                c.restore();
            });
        }
    };

    /* ── Plugin 1 : traits verticaux pointillés (dessinés AVANT les bulles) ── */
    var dashedLinesPlugin = {
        id: 'dashedLines',
        beforeDatasetsDraw: function(chart) {
            var c      = chart.ctx;
            var yScale = chart.scales.y;
            var yBase  = yScale.getPixelForValue(0);
            var meta   = chart.getDatasetMeta(0);

            c.save();
            c.setLineDash([6, 5]);
            c.strokeStyle = 'rgba(255,255,255,0.45)';
            c.lineWidth   = 1.5;

            meta.data.forEach(function(pt) {
                var lineEnd = pt.y + BUBBLE_R; // bord bas de la bulle (canvas y = bas)
                if (lineEnd < yBase) {
                    c.beginPath();
                    c.moveTo(pt.x, yBase);
                    c.lineTo(pt.x, lineEnd);
                    c.stroke();
                }
            });
            c.restore();
        }
    };

    /* ── Plugin 2 : logos marques sous l'axe X ── */
    function drawLogos(chart) {
        var old = document.getElementById('chart-container-logo-container');
        if (old) old.remove();
        var wrap = document.createElement('div');
        wrap.id  = 'chart-container-logo-container';
        Object.assign(wrap.style, { position:'absolute', top:'0', left:'0', width:'100%', height:'100%', pointerEvents:'none' });

        var xScale    = chart.scales.x;
        var chartArea = chart.chartArea;

        labels.forEach(function(label, index) {
            var xPos = xScale.getPixelForValue(index);
            var img  = document.createElement('img');
            img.src  = brandLogos[label] ? '/MEDACAP/public/images/' + brandLogos[label] : '/MEDACAP/public/images/default.png';
            Object.assign(img.style, {
                position : 'absolute',
                left     : (xPos - 35) + 'px', // Centré (70 / 2)
                top      : (chartArea.bottom + 15) + 'px',
                width    : '70px',
                height   : '60px', // Hauteur augmentée
                objectFit: 'contain' // Évite le rognage
            });
            wrap.appendChild(img);
        });
        document.getElementById('chart-container').appendChild(wrap);
    }

    var imagePluginScatter = {
        id: 'imagePluginScatter',
        afterRender:  function(chart) { drawLogos(chart); },
        afterResize:  function(chart) {
            var old = document.getElementById('chart-container-logo-container');
            if (old) old.remove();
            drawLogos(chart);
        }
    };

    /* ── Graphique ── */
    new Chart(ctx, {
        type: 'scatter',
        data: {
            labels: labels,
            datasets: [{
                label: 'Avancement par Marque (%)',
                data: labels.map(function(b, i) { return { x: i, y: dataValues[i] }; }),
                backgroundColor: colors,
                borderColor: '#fff',
                borderWidth: 3,
                pointRadius: BUBBLE_R,
                pointHoverRadius: BUBBLE_R + 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            /* ── Clic sur une bulle → filtre par marque ── */
            onClick: function(event, elements) {
                if (elements.length === 0) return;
                var brand = labels[elements[0].index];
                var params = 'brand=' + encodeURIComponent(brand);
                params += '&level=' + encodeURIComponent(currentLevel);
                if (tidParam) params += tidParam;
                window.location.href = '?' + params;
            },
            onHover: function(event, elements) {
                var canvas = event.native ? event.native.target : event.target;
                if (canvas) canvas.style.cursor = elements.length > 0 ? 'pointer' : 'default';
            },
            /* ── Animation rebond ── */
            animation: {
                duration: 1400,
                easing: 'easeOutBounce'
            },
            animations: {
                y: {
                    from: function(context) {
                        var s = context.chart.scales;
                        return s && s.y ? s.y.getPixelForValue(0) : 500;
                    }
                }
            },
            layout: { padding: { left: 40, bottom: 80 } },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            var brand = labels[context.dataIndex];
                            var score = dataValues[context.dataIndex] !== null ? dataValues[context.dataIndex] : 'N/A';
                            var mods  = brandFormationsMap[brand] !== undefined ? brandFormationsMap[brand] : '0';
                            return ['Marque : ' + brand, 'Modules : ' + mods, 'Résultat de la Mesure de compétences : ' + score + '%'];
                        }
                    }
                }
            },
            scales: {
                x: {
                    type: 'category',
                    labels: labels,
                    offset: true,          // aucune bulle ne touche l'axe Y
                    ticks: { display: false },
                    grid: { display: false }
                },
                y: {
                    min: 0,
                    max: 110,
                    title: {
                        display: true,
                        text: 'RÉSULTAT DE LA MESURE EN POURCENTAGE',
                        color: '#000000',
                        font: { size: 12, weight: 'bold' },
                        padding: { bottom: 10 }
                    },
                    ticks: {
                        stepSize: 10,
                        color: '#000000',
                        font: { weight: 'bold', size: 12 }
                    },
                    grid: {
                        color: 'rgba(0,0,0,0.3)', // Plus foncé en noir semi-transparent
                        lineWidth: 1.5 // Légèrement plus épais
                    }
                }
            }
        },
        plugins: [bubbleTextPlugin, scoreLabelPlugin, dashedLinesPlugin, imagePluginScatter]
    });
});
</script>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
