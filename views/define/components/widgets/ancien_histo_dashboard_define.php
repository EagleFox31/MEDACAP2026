<?php
// views/define/components/widgets/ancien_histo_dashboard_define.php

foreach ($levelsTests as $index => $lvl):
    $testsCompleted = $statsTests[$lvl['key']]['done'];
    $totalTests = $statsTests[$lvl['key']]['total'];

    $proposedPIF = $lvl['pif'];
    $totalTechs = $lvl['total'];

    $validatedPIF = $lvl['valid'];

    // Generate unique chart ID for this level
    $chartId = "levelChart" . $index;

    // Afficher le signe "+" après chaque carte sauf la dernière
    if ($index > 0) {
        echo '<div class="d-flex align-items-center justify-content-center">';
        echo '<div class="card" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; background-color: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1);"><div style="color: black; font-size: 32px; font-weight: 900;">+</div></div>';
        echo '</div>';
    }
?>
    <div class="flex-grow-1" style="min-width: 350px; max-width:400px">
        <div class="card glass-effect depth-effect h-100">
            <div class="card-body">
                <div class="chart-title" style="background-color: white !important;">
                    <i class="ki-duotone ki-chart fs-2 me-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i><?= $lvl['label'] ?>
                </div>

                <div id="<?= $chartId ?>" class="test-levels-chart"
                    style="height: 350px; width: 110%; margin-left: -5%;">
                    <?php if ($filters['technicianId'] !== 'all'): ?>
                        <div class="technician-specific-notice"
                            style="position: absolute; top: 10px; right: 10px; background: rgba(255,255,255,0.8); padding: 5px 10px; border-radius: 5px; font-size: 11px; color: #333;">
                            <i class="fas fa-user-check"></i> Données spécifiques au technicien sélectionné
                        </div>
                    <?php endif; ?>
                </div>

                <div class="text-center mt-2">
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var options = {
                series: [{
                        name: 'Réalisé',
                        data: [
                            <?= $testsCompleted ?>,
                            <?= $proposedPIF ?>,
                            <?= $validatedPIF ?>
                        ]
                    },
                    {
                        name: 'Non réalisé',
                        data: [
                            <?= $totalTechs - $testsCompleted ?>,
                            <?= $totalTechs - $proposedPIF ?>,
                            <?= $totalTechs - $validatedPIF ?>
                        ]
                    }
                ],
                chart: {
                    type: 'bar',
                    height: 400,
                    stacked: true,
                    toolbar: {
                        show: false
                    },
                    fontFamily: 'Inter, sans-serif',
                    background: 'transparent'
                },
                plotOptions: {
                    bar: {
                        horizontal: false,
                        columnWidth: '90%',
                        endingShape: 'flat',
                        borderRadius: 4,
                        distributed: false
                    },
                },
                colors: ['#8b5cf6', '#e2e8f0'],
                dataLabels: {
                    enabled: true,
                    formatter: function(val, opt) {
                        // Afficher le label uniquement sur la première série
                        if (opt.seriesIndex === 0) {
                            var total;
                            if (opt.dataPointIndex === 0) total = <?= $totalTechs ?>;
                            else if (opt.dataPointIndex === 1) total = <?= $totalTechs ?>;
                            else total = <?= $totalTechs ?>;
                            var percent = total > 0 ? Math.round((val / total) * 100) : 0;
                            return percent + '%';
                        } else {
                            return '';
                        }
                    },
                    style: {
                        fontSize: '14px',
                        fontWeight: 'bold',
                        colors: ['#FFFFFF']
                    }
                },
                grid: {
                    show: true,
                    borderColor: '#e7e7e7',
                    strokeDashArray: 5,
                    position: 'back',
                    xaxis: {
                        lines: {
                            show: true
                        }
                    },
                    yaxis: {
                        lines: {
                            show: true
                        }
                    },
                    row: {
                        colors: ['#f8f8f8', 'transparent'],
                        opacity: 0.5
                    },
                    padding: {
                        top: 10,
                        right: 10,
                        bottom: 0,
                        left: 10
                    }
                },
                stroke: {
                    width: 0
                },
                xaxis: {
                    categories: ['Tests complétés', 'PIF Proposés', 'PIF Validés'],
                    labels: {
                        style: {
                            fontSize: '12px',
                            fontWeight: 600
                        },
                        offsetY: 5
                    },
                    axisBorder: {
                        show: true,
                        color: '#e0e0e0'
                    },
                    axisTicks: {
                        show: true,
                        color: '#e0e0e0'
                    },
                    crosshairs: {
                        show: true,
                        stroke: {
                            color: '#b6b6b6',
                            width: 1,
                            dashArray: 3
                        }
                    }
                },
                yaxis: {
                    min: 0,
                    max: Math.max(<?= $totalTests ?>, <?= $totalTechs ?>, <?= $proposedPIF ?>) * 1.2,
                    labels: {
                        style: {
                            fontSize: '12px'
                        },
                        offsetX: -8,
                        formatter: function(val) {
                            return Math.round(val);
                        }
                    },
                    tickAmount: 5,
                    forceNiceScale: true
                },
                tooltip: {
                    enabled: true,
                    shared: true,
                    intersect: false,
                    followCursor: false,
                    fixed: {
                        enabled: true,
                        position: 'topCenter',
                        offsetY: -80
                    },
                    onDatasetHover: {
                        highlightDataSeries: false,
                    },
                    y: {
                        formatter: function(val, opts) {
                            return val;
                        }
                    },
                    marker: {
                        show: false
                    },
                    custom: function({
                        series,
                        seriesIndex,
                        dataPointIndex,
                        w
                    }) {
                        var total;
                        if (dataPointIndex === 0) total = <?= $totalTests ?>;
                        else if (dataPointIndex === 1) total = <?= $totalTechs ?>;
                        else total = <?= $proposedPIF ?>;

                        var done = series[0][dataPointIndex];

                        return '<div class="custom-tooltip">' +
                            '<span style="font-size: 14px; font-weight: bold;">Etat d\'avancement : ' + done + '/' + total + '</span>' +
                            '</div>';
                    }
                },
                legend: {
                    show: false
                },
                fill: {
                    opacity: 1,
                    type: 'solid'
                },
                states: {
                    hover: {
                        filter: {
                            type: 'darken',
                            value: 0.9
                        }
                    }
                }
            };

            var chart = new ApexCharts(document.querySelector("#<?= $chartId ?>"), options);
            chart.render();
        });
    </script>
<?php endforeach; ?>

<!-- Histogramme TOTAL -->
<?php
$totalTestsCompleted = $statsTests['Total']['done'];
$totalTestsAll = $statsTests['Total']['total'];

$totalProposed = $nbPifJunior + $nbPifSenior + $nbPifExpert;
$totalTechnicians = $globalStats['totalTechnicians'];

$totalValidated = $nbValidJunior + $nbValidSenior + $nbValidExpert;
?>
<!-- Signe égal avant la carte "Tous Niveaux" -->
<div class="d-flex align-items-center justify-content-center">
    <div class="card"
        style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; background-color: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
        <div style="color: black; font-size: 32px; font-weight: 900;">=</div>
    </div>
</div>

<div class="flex-grow-1" style="min-width: 350px; max-width:400px">
    <div class="card glass-effect depth-effect h-100">
        <div class="card-body">
            <div class="chart-title" style="background-color: white !important;">
                <i class="ki-duotone ki-chart fs-2 me-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>TOUS LES NIVEAUX
            </div>

            <div id="totalLevelChart" class="test-levels-chart"
                style="height: 350px; width: 110%; margin-left: -5%;">
                <?php if ($filters['technicianId'] !== 'all'): ?>
                    <div class="technician-specific-notice"
                        style="position: absolute; top: 10px; right: 10px; background: rgba(255,255,255,0.8); padding: 5px 10px; border-radius: 5px; font-size: 11px; color: #333;">
                        <i class="fas fa-user-check"></i> Données spécifiques au technicien sélectionné
                    </div>
                <?php endif; ?>
            </div>

            <div class="text-center mt-2">
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var options = {
            series: [{
                    name: 'Réalisé',
                    data: [
                        <?= $totalTestsCompleted ?>,
                        <?= $totalProposed ?>,
                        <?= $totalValidated ?>
                    ]
                },
                {
                    name: 'Non réalisé',
                    data: [
                        <?= $totalTechnicians - $totalTestsCompleted ?>,
                        <?= $totalTechnicians - $totalProposed ?>,
                        <?= $totalTechnicians - $totalValidated ?>
                    ]
                }
            ],
            chart: {
                type: 'bar',
                height: 400,
                stacked: true,
                toolbar: {
                    show: false
                },
                fontFamily: 'Inter, sans-serif',
                background: 'transparent'
            },
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '90%',
                    endingShape: 'flat',
                    borderRadius: 4,
                    barHeight: '70%',
                    distributed: false
                },
            },
            colors: ['#8b5cf6', '#e2e8f0'],
            dataLabels: {
                enabled: true,
                formatter: function(val, opt) {
                    // Afficher le label uniquement sur la première série
                    if (opt.seriesIndex === 0) {
                        var total;
                        if (opt.dataPointIndex === 0) total = <?= $totalTechnicians ?>;
                        else if (opt.dataPointIndex === 1) total = <?= $totalTechnicians ?>;
                        else total = <?= $totalTechnicians ?>;
                        var percent = total > 0 ? Math.round((val / total) * 100) : 0;
                        return percent + '%';
                    } else {
                        return '';
                    }
                },
                style: {
                    fontSize: '14px',
                    fontWeight: 'bold',
                    colors: ['#FFFFFF']
                }
            },
            grid: {
                show: true,
                borderColor: 'rgba(136, 136, 136, 0.1)',
                strokeDashArray: 5,
                position: 'back',
                xaxis: {
                    lines: {
                        show: true,
                        opacity: 0.3
                    }
                },
                yaxis: {
                    lines: {
                        show: true,
                        opacity: 0.5
                    }
                },
                row: {
                    colors: ['#f9f9f9', 'transparent'],
                    opacity: 0.2
                },
                column: {
                    colors: ['#f9f9f9', 'transparent'],
                    opacity: 0.2
                },
                padding: {
                    top: 15,
                    right: 15,
                    bottom: 10,
                    left: 15
                }
            },
            stroke: {
                width: 0
            },
            xaxis: {
                categories: ['Tests complétés', 'PIF Proposés', 'PIF Validés'],
                labels: {
                    style: {
                        fontSize: '12px',
                        fontWeight: 600
                    },
                    offsetY: 5
                },
                axisBorder: {
                    show: true,
                    color: '#e0e0e0'
                },
                axisTicks: {
                    show: true,
                    color: '#e0e0e0',
                    height: 6
                },
                crosshairs: {
                    show: true,
                    stroke: {
                        color: '#b6b6b6',
                        width: 1,
                        dashArray: 3
                    }
                }
            },
            yaxis: {
                max: function() {
                    return Math.max(<?= $totalTestsAll ?>, <?= $totalTechnicians ?>, <?= $totalProposed ?>) * 1.2;
                },
                labels: {
                    style: {
                        fontSize: '10px'
                    },
                    offsetX: -8,
                    formatter: function(val) {
                        return Math.round(val);
                    }
                },
                tickAmount: 5,
                forceNiceScale: true
            },
            tooltip: {
                enabled: true,
                shared: true,
                intersect: false,
                followCursor: false,
                fixed: {
                    enabled: true,
                    position: 'topCenter',
                    offsetY: -80
                },
                onDatasetHover: {
                    highlightDataSeries: false,
                },
                y: {
                    formatter: function(val, opts) {
                        return val;
                    }
                },
                marker: {
                    show: false
                },
                custom: function({
                    series,
                    seriesIndex,
                    dataPointIndex,
                    w
                }) {
                    var total;
                    if (dataPointIndex === 0) total = <?= $totalTestsAll ?>;
                    else if (dataPointIndex === 1) total = <?= $totalTechnicians ?>;
                    else total = <?= $totalProposed ?>;

                    var done = series[0][dataPointIndex];

                    return '<div class="custom-tooltip">' +
                        '<span style="font-size: 14px; font-weight: bold;">Etat d\'avancement : ' + done + '/' + total + '</span>' +
                        '</div>';
                }
            },
            legend: {
                show: false
            },
            fill: {
                opacity: 1,
                type: 'solid'
            },
            states: {
                hover: {
                    filter: {
                        type: 'darken',
                        value: 0.9
                    }
                }
            }
        };

        var chart = new ApexCharts(document.querySelector("#totalLevelChart"), options);
        chart.render();
    });
</script>