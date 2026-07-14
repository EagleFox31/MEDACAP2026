<div class="row">
    <div class="col-12">
        <div class="mb-5">
            <?php
            $brandsTitle = !empty($supervisorView) && !empty($selectedTechnicianName)
                ? sprintf('%02d', count($brandsToShow)) . ' marques — ' . $selectedTechnicianName
                : 'Mes ' . sprintf('%02d', count($brandsToShow)) . ' marques sur lesquelles j\'interviens en Atelier';
            $brandsTitleSuffix = (!empty($supervisorView) && !empty($selectedTechnicianName))
                ? ' ' . collaboratorDashboardRenderLevelBadge($techLevel ?? 'Junior')
                : '';
            renderTitleSection('ki-duotone ki-setting-2 fs-2 me-3', 2, $brandsTitle, 'py-3', '100%', $brandsTitleSuffix);
            ?>
            <div class="mt-4">
                <?php include __DIR__ . '/brand_carousel.php'; ?>
            </div>
        </div>

        <div class="mt-15 mb-4">
            <?php
            $volumeTitle = !empty($supervisorView) ? 'Volume de Formation' : 'Mon Volume de Formation';
            renderTitleSection('ki-duotone ki-chart-line-star fs-1 me-3', 1, $volumeTitle, 'py-3', '100%');
            ?>
        </div>

        <div class="row g-4 mb-15" id="kpiRow">
            <div class="col-md-4">
                <div class="card stats-card h-100 kpi-card" style="background: rgba(255, 255, 255, 0.4); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.3); border-radius: 12px; overflow: hidden;">
                    <div class="card-header-bg py-3 px-4" style="background: #ffffff; border-bottom: 1px solid rgba(0,0,0,0.05);">
                        <h5 class="card-title text-dark mb-0 text-uppercase fs-6 fw-bold">FORMATIONS A FAIRE</h5>
                    </div>
                    <div class="card-body text-center d-flex flex-column justify-content-center p-6">
                        <i class="fas fa-book-reader fs-1 text-primary mb-3"></i>
                        <div class="big-number mb-1 fw-bold text-dark" style="font-size:4.5rem;line-height:1;" data-kt-countup="true" data-kt-countup-value="<?php echo count($numRecommended); ?>">
                            <?php echo count($numRecommended); ?>
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
                        <div class="big-number mb-1 fw-bold text-dark" style="font-size:4.5rem;line-height:1;" data-kt-countup="true" data-kt-countup-value="0">0</div>
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

        <?php
        $_levelFilter  = $levelFilter ?? 'all';
        $_techLevel    = $techLevel ?? 'Junior';
        $_filterBrand  = $filterBrand ?? 'all';
        $_pageBase     = !empty($supervisorView) ? ($supervisorPage ?? './collaboratorDashboard') : '';
        $_agencyParam  = !empty($supervisorView) ? '&agency=' . urlencode($selectedAgency ?? 'all') : '';
        $_tidParam     = !empty($supervisorView)
            ? '&technicianId=' . urlencode($selectedTechnicianId ?? ($_GET['technicianId'] ?? ''))
            : (isset($_GET['technicianId']) ? '&technicianId=' . urlencode($_GET['technicianId']) : '');
        $_queryPrefix  = $_pageBase !== '' ? htmlspecialchars($_pageBase) . '?' : '?';
        $availLevels   = ['Junior'];
        if (in_array($_techLevel, ['Senior', 'Expert'])) $availLevels[] = 'Senior';
        if ($_techLevel === 'Expert') $availLevels[] = 'Expert';
        $_brandParamForLevel = $_filterBrand !== 'all' ? '&brand=' . urlencode($_filterBrand) : '';
        $_navClass = !empty($supervisorView) ? ' supervisor-dashboard-nav' : '';
        $_embedView = !empty($GLOBALS['defineEmbed']);
        $_scopeParam = '';
        if ($_embedView) {
            $_scope = $_GET['scope'] ?? $_GET['year'] ?? 'pluri';
            if ($_scope !== '' && $_scope !== 'all') {
                $_scopeParam = '&scope=' . urlencode((string) $_scope);
            }
        }
        $_embedParam = $_embedView ? '&embed=1' : '';
        $_returnParam = $_embedView && !empty($_GET['returnUrl'])
            ? '&returnUrl=' . urlencode((string) $_GET['returnUrl'])
            : '';
        ?>
        <div class="card mb-8" style="background: rgba(255, 255, 255, 0.4); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.3); border-radius: 12px; overflow: hidden;">
            <div class="card-header-bg py-4 px-6 d-flex align-items-center justify-content-between flex-wrap gap-3" style="background: #ffffff; border-bottom: 1px solid rgba(0,0,0,0.05);">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <h5 class="card-title text-dark mb-0 text-uppercase fw-bold fs-5">RESULTATS DE LA MESURE ET VOLUME DE FORMATION</h5>
                    <?php if ($_filterBrand !== 'all' && !$_embedView): ?>
                    <span class="badge bg-warning text-dark fw-bold fs-6 px-3 py-2">
                        <?= htmlspecialchars($_filterBrand) ?>
                        <a href="<?= $_queryPrefix ?>level=<?= urlencode($_levelFilter) . $_tidParam . $_agencyParam . $_scopeParam . $_embedParam . $_returnParam ?>" class="ms-2 text-dark text-decoration-none<?= $_navClass ?>" title="Effacer le filtre marque">&#10005;</a>
                    </span>
                    <?php endif; ?>
                </div>
                <?php if (!$_embedView): ?>
                <div class="d-flex gap-2">
                    <a href="<?= $_queryPrefix ?>level=all<?= $_tidParam . $_agencyParam . $_brandParamForLevel . $_scopeParam . $_embedParam . $_returnParam ?>" class="btn btn-sm <?= $_levelFilter === 'all' ? 'btn-primary' : 'btn-outline-secondary' ?><?= $_navClass ?>">Tous</a>
                    <?php foreach ($availLevels as $lvl): ?>
                    <a href="<?= $_queryPrefix ?>level=<?= $lvl . $_tidParam . $_agencyParam . $_brandParamForLevel . $_scopeParam . $_embedParam . $_returnParam ?>" class="btn btn-sm <?= $_levelFilter === $lvl ? 'btn-warning text-dark fw-bold' : 'btn-outline-secondary' ?><?= $_navClass ?>"><?= $lvl ?></a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
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

<script>
(function () {
    function initTechnicianDashboardChart() {
        if (window.__technicianDashboardChart) {
            window.__technicianDashboardChart.destroy();
            window.__technicianDashboardChart = null;
        }

        var brandScores = <?php echo json_encode($brandScores); ?>;
        var brandLogos = {
            'RENAULT TRUCK': 'renaultTrucks.png', 'HINO': 'hino_logo.png', 'TOYOTA BT': 'bt.png',
            'SINOTRUK': 'sinotruk.png', 'JCB': 'jcb.png', 'MERCEDES TRUCK': 'mercedestruck.png',
            'TOYOTA FORKLIFT': 'forklift.png', 'FUSO': 'fuso.png', 'LOVOL': 'lovol.png',
            'KING LONG': 'kl2.png', 'MERCEDES': 'mercedestruck.png', 'TOYOTA': 'toyota-logo.png',
            'SUZUKI': 'suzuki-logo.png', 'PEUGEOT': 'peugeot-logo.png', 'MITSUBISHI': 'mitsubishi-logo.png'
        };
        var brandFormationsMap = <?php echo json_encode($brandFormationsMap ?? []); ?>;
        var canvas = document.getElementById('chartjs-container');
        if (!canvas) return;

        var ctx = canvas.getContext('2d');
        var labels = brandScores.map(function (d) { return d.x; });
        var dataValues = brandScores.map(function (d) { return d.y; });
        var colors = brandScores.map(function (d) { return d.fillColor; });
        var BUBBLE_R = 58;
        var currentLevel = <?php echo json_encode($_levelFilter); ?>;
        var tidParam = <?php echo json_encode($_tidParam); ?>;
        var queryPrefix = <?php echo json_encode($_queryPrefix); ?>;
        var agencyParam = <?php echo json_encode($_agencyParam); ?>;
        var supervisorAjax = <?php echo !empty($supervisorView) ? 'true' : 'false'; ?>;

        function navigateChart(brand) {
            var params = 'brand=' + encodeURIComponent(brand);
            params += '&level=' + encodeURIComponent(currentLevel);
            if (tidParam) params += tidParam;
            if (agencyParam) params += agencyParam;
            var url = queryPrefix + params;
            if (supervisorAjax && window.CollaboratorDashboard && window.CollaboratorDashboard.loadFromUrl) {
                window.CollaboratorDashboard.loadFromUrl(url);
            } else {
                window.location.href = url;
            }
        }

        var bubbleTextPlugin = {
            id: 'bubbleText',
            afterDatasetsDraw: function (chart) {
                var c = chart.ctx;
                var meta = chart.getDatasetMeta(0);
                meta.data.forEach(function (pt, i) {
                    var modsRaw = brandFormationsMap[labels[i]] !== undefined ? parseInt(brandFormationsMap[labels[i]], 10) : 0;
                    var mods = modsRaw < 10 ? '0' + modsRaw : String(modsRaw);
                    c.save();
                    c.textAlign = 'center';
                    c.textBaseline = 'middle';
                    c.fillStyle = '#ffffff';
                    var lineModule = (modsRaw < 2) ? 'Module' : 'Modules';
                    var lines = [mods, lineModule, 'de', 'formation'];
                    var lineHeight = 16;
                    var totalHeight = lines.length * lineHeight;
                    var startY = pt.y - (totalHeight / 2) + (lineHeight / 2);
                    lines.forEach(function (line, idx) {
                        var y = startY + (idx * lineHeight);
                        if (idx === 0) {
                            c.font = 'bold 26px sans-serif';
                            c.fillText(line, pt.x, y - 4);
                            c.font = 'bold 11px sans-serif';
                        } else {
                            c.fillText(line, pt.x, y + (idx === 1 ? 6 : 0));
                        }
                    });
                    c.restore();
                });
            }
        };

        var scoreLabelPlugin = {
            id: 'scoreLabel',
            afterDatasetsDraw: function (chart) {
                var c = chart.ctx;
                var meta = chart.getDatasetMeta(0);
                meta.data.forEach(function (pt, i) {
                    var score = dataValues[i] !== null ? dataValues[i] + '%' : '0%';
                    c.save();
                    var labelW = 150, labelH = 45;
                    var x = pt.x - (labelW / 2);
                    var y = pt.y - BUBBLE_R - labelH - 10;
                    c.fillStyle = '#ffffff';
                    c.fillRect(x, y, labelW, labelH);
                    c.textAlign = 'center';
                    c.fillStyle = '#666666';
                    c.font = 'bold 11px sans-serif';
                    c.fillText('RÉSULTAT DE LA MESURE', pt.x, y + 16);
                    c.fillStyle = colors[i];
                    c.font = 'bold 20px sans-serif';
                    c.fillText(score, pt.x, y + 34);
                    c.restore();
                });
            }
        };

        var dashedLinesPlugin = {
            id: 'dashedLines',
            beforeDatasetsDraw: function (chart) {
                var c = chart.ctx;
                var yScale = chart.scales.y;
                var yBase = yScale.getPixelForValue(0);
                var meta = chart.getDatasetMeta(0);
                c.save();
                c.setLineDash([6, 5]);
                c.strokeStyle = 'rgba(255,255,255,0.45)';
                c.lineWidth = 1.5;
                meta.data.forEach(function (pt) {
                    var lineEnd = pt.y + BUBBLE_R;
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

        function drawLogos(chart) {
            var old = document.getElementById('chart-container-logo-container');
            if (old) old.remove();
            var wrap = document.createElement('div');
            wrap.id = 'chart-container-logo-container';
            Object.assign(wrap.style, { position: 'absolute', top: '0', left: '0', width: '100%', height: '100%', pointerEvents: 'none' });
            var xScale = chart.scales.x;
            var chartArea = chart.chartArea;
            labels.forEach(function (label, index) {
                var xPos = xScale.getPixelForValue(index);
                var img = document.createElement('img');
                img.src = brandLogos[label] ? '/MEDACAP/public/images/' + brandLogos[label] : '/MEDACAP/public/images/default.png';
                Object.assign(img.style, {
                    position: 'absolute',
                    left: (xPos - 35) + 'px',
                    top: (chartArea.bottom + 15) + 'px',
                    width: '70px',
                    height: '60px',
                    objectFit: 'contain'
                });
                wrap.appendChild(img);
            });
            document.getElementById('chart-container').appendChild(wrap);
        }

        var imagePluginScatter = {
            id: 'imagePluginScatter',
            afterRender: function (chart) { drawLogos(chart); },
            afterResize: function (chart) {
                var old = document.getElementById('chart-container-logo-container');
                if (old) old.remove();
                drawLogos(chart);
            }
        };

        window.__technicianDashboardChart = new Chart(ctx, {
            type: 'scatter',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Avancement par Marque (%)',
                    data: labels.map(function (b, i) { return { x: i, y: dataValues[i] }; }),
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
                onClick: function (event, elements) {
                    if (elements.length === 0) return;
                    navigateChart(labels[elements[0].index]);
                },
                onHover: function (event, elements) {
                    var target = event.native ? event.native.target : event.target;
                    if (target) target.style.cursor = elements.length > 0 ? 'pointer' : 'default';
                },
                animation: { duration: 900, easing: 'easeOutQuart' },
                layout: { padding: { left: 40, bottom: 80 } },
                plugins: { legend: { display: false } },
                scales: {
                    x: { type: 'category', labels: labels, offset: true, ticks: { display: false }, grid: { display: false } },
                    y: {
                        min: 0, max: 110,
                        title: { display: true, text: 'RÉSULTAT DE LA MESURE EN POURCENTAGE', color: '#000000', font: { size: 12, weight: 'bold' } },
                        ticks: { stepSize: 10, color: '#000000', font: { weight: 'bold', size: 12 } },
                        grid: { color: 'rgba(0,0,0,0.3)', lineWidth: 1.5 }
                    }
                }
            },
            plugins: [bubbleTextPlugin, scoreLabelPlugin, dashedLinesPlugin, imagePluginScatter]
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTechnicianDashboardChart);
    } else {
        initTechnicianDashboardChart();
    }
})();
</script>
