<?php
    // pif_dashboard.php

    // Inclure l'autoloader Composer et la classe PIFService
    require_once __DIR__ . '/../services/PIFService.php';

    // Instancier le service
    $pifService = new \views\define\services\PIFService();

    // Récupérer les indicateurs de PIF à partir de la BDD
    $indicators = $pifService->getPIFIndicators();

    // Récupérer les données pour les deux diagrammes doughnut
    $propositionData = $pifService->getPIFPropositionData();
    $validationData  = $pifService->getPIFValidationData();
    $nbPifJunior = $pifService->countPIFByExactLevel('Junior');
    $nbPifSenior = $pifService->countPIFByExactLevel('Senior');
    $nbPifExpert = $pifService->countPIFByExactLevel('Expert');

?>
<?php include "./partials/header.php"; ?>
<html>

<head>
    <meta charset="UTF-8" />
    <title>Etat d'avancement des PIF</title>
    <!-- Inclure Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
    body {
        font-family: Arial, sans-serif;
        background-color: #f8f8f8;
        margin: 0;
        padding: 0;
    }

    h1 {
        margin: 0;
        color: #333;
    }

    /* Conteneur principal */
    .container {
        max-width: 1500px;
        /* margin: auto; */
        padding: 20px;
    }

    /* Conteneur des KPI en 2 groupes (colonnes) */
    .kpi-container {
        display: flex;
        align-items: flex-start;
        gap: 20px;
        margin-bottom: 40px;
    }

    /* Le trait lui‑même */
    .kpi-separator {
        width: 0;
        /* pas de largeur propre */
        border-left: 2px solid #070707;
        /* couleur/épaisseur du trait */
        align-self: stretch;
        /* occupe toute la hauteur dispo */
        margin: 0 40px;
        /* même espacement qu’avant */
    }

    /* Pour qu’il démarre SOUS les titres (optionnel mais propre) */
    .kpi-group .section-title {
        margin-bottom: 25px;
        /* espace entre le titre et les cards */
    }

    /* Chaque groupe (colonne) de KPI */
    .kpi-group {
        flex: 1;
    }

    /* === TRAIT VERTICAL UNIQUE (KPI + donuts) === */
    .v-separator {
        width: 0;
        border-left: 2px solid #070707;
        /* même couleur/épaisseur */
        align-self: stretch;
        /* prend toute la hauteur */
        margin: 0 40px;
        /* même espace qu'avant */
    }

    /* Titres des groupes */
    .section-title {
        font-size: 1.4rem;
        font-weight: bold;
        color: #333;
        margin-top: 0;
        margin-bottom: 20px;
        text-align: center;
    }

    /* Séparateur vertical entre les groupes de KPI */
    .separator {
        width: 0;
        border-left: 2px solid #070707;
        margin: 0 40px;
    }

    /* Zone des indicateurs (cards KPI) */
    .indicateurs {
        display: flex;
        gap: 20px;
        justify-content: center;
    }

    .indicateur {
        flex: 1;
        min-width: 250px;
        background-color: #fff;
        border-radius: 8px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        transition: box-shadow 0.3s ease, transform 0.3s ease;
    }

    .indicateur:hover {
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        transform: translateY(-3px);
    }

    .indicateur h2 {
        font-size: 2.2rem;
        margin: 0;
        color: #333;
    }

    .indicateur p {
        margin-top: 5px;
        font-weight: bold;
        color: #242424;
    }

    /* Optionnel : séparateur vertical entre les cards KPI */
    .indicateurs>.indicateur:not(:last-child) {
        border-right: 2px solid #ccc;
        padding-right: 10px;
        margin-right: 10px;
    }

    /* Conteneur des graphiques (charts) en 2 colonnes */
    .charts-container {
        display: flex;
        align-items: center;
        gap: 20px;
        margin-bottom: 40px;
    }

    /* Chaque groupe de graphique */
    .chart-group {
        flex: 1;
        text-align: center;
    }

    /* La box du graphique (style déjà défini) */
    .chart-box {
        background: #fff;
        border-radius: 8px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 2px 5px rgba(0, 0, 0, .1);
        transition: box-shadow .3s, transform .3s;

        /* NEW ↓ : la box occupe toute la colonne */

        max-width: 600px;
        /* (ou mets 800‑900px si tu veux limiter) */
    }

    /* Colonne de gauche → la box se colle au trait */
    .charts-container .chart-group:first-child {
        display: flex;
        justify-content: center;
        /* colle à droite → contre le trait */
    }

    /* Colonne de droite → colle à gauche */
    .charts-container .chart-group:last-child {
        display: flex;
        justify-content: center;
        /* colle à gauche → contre le trait */
    }


    .chart-box:hover {
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        transform: translateY(-3px);
    }

    .chart-box h3 {
        margin-top: 0;
        margin-bottom: 15px;
        font-size: 1.305rem;
        color: #333;
    }

    .chart-box canvas {
        display: block;
        /* le canvas devient un bloc */
        margin: 0 auto;
        /* auto → centrage horizontal */
        width: 400px !important;
        /* taille fixe = donut plus petit   */
        height: 400px !important;
        /* idem                             */
        max-width: none;
        /* neutralise le max-width global   */
    }


    .chart-stats {
        margin: 0;
        color: #444;
        font-weight: bold;
    }

    /* Séparateur vertical entre les groupes de graphiques */
    /* On neutralise l'ancien border‑right des donuts */
    .charts-container>.chart-group:not(:last-child) {
        border-right: none;
        /* on vire le double‑trait */
        padding-right: 0;
        margin-right: 0;
    }

    /* Pour forcer le mot en gras avec un poids plus fort */
    .chart-box h3 .title-bold {
        font-weight: 900;
    }

    .fs-6 {
        font-size: 1.275rem !important;
    }

    .btn-export {
        background: #009ef7;
        color: #fff;
        border: none;
        padding: 8px 14px;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        transition: background .2s, transform .2s;
    }

    .btn-export:hover {
        background: #007ad9;
        transform: translateY(-2px);
    }

    .indicateur,
    .chart-box {
        transition: transform .25s cubic-bezier(.25, .8, .25, 1), box-shadow .25s;
        transform-origin: center center;
    }

    .indicateur:hover {
        transform: perspective(800px) rotateX(3deg) translateY(-4px);
        box-shadow: 0 12px 24px rgba(0, 0, 0, .18);
    }

    .indicateur:active,
    .chart-box:active {
        transform: scale(.98);
    }
    </style>
</head>

<body>
    <!-- Contenu principal -->
    <div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content">
        <div class="container">

            <!-- Ligne des KPI -->
            <div class="kpi-container">
                <!-- Groupe de KPI de gauche -->
                <div class="kpi-group">
                    <h1 class="section-title">État d'avancement de la mesure des compétences :</h1>
                    <div class="indicateurs">
                        <div class="indicateur">
                            <h2 id="techniciensFiliales"><?php echo $indicators['techniciensFiliales']; ?></h2>
                            <p>Techniciens Filiales</p>
                        </div>
                        <div class="indicateur">
                            <h2 id="techniciensEvalues"><?php echo $indicators['techniciensEvalues']; ?></h2>
                            <p>Techniciens mesurés</p>
                        </div>
                    </div>
                </div>

                <div class="v-separator"></div>

                <!-- Groupe de KPI de droite -->
                <div class="kpi-group">
                    <h1 class="section-title">État d'avancement des PIF :</h1>
                    <div class="indicateurs">
                        <div class="indicateur">
                            <h2 id="techniciensAvecPIF"><?php echo $indicators['techniciensAvecPIF']; ?></h2>
                            <p>Techniciens avec PIF</p>
                        </div>
                        <div class="indicateur">
                            <h2 id="pifValides"><?php echo $indicators['pifValides']; ?></h2>
                            <p>PIF validés filiales</p>
                        </div>
                    </div>
                </div>
            </div> <!-- .kpi-container -->

            <!-- Ligne des graphiques Doughnut regroupés en 2 colonnes -->
            <div class="charts-container">
                <!-- Groupe du graphique "Proposition" -->
                <div class="chart-group">
                    <div class="chart-box">
                        <h3><span class="title-bold">Proposition</span> des Plans Individuels de Formation (PIF) par
                            l'Academy</h3>
                        <canvas id="propositionChart"></canvas>
                        <p class="chart-stats">
                            Nombre de PIF proposé <?php echo $indicators['techniciensAvecPIF']; ?> /
                            <?php echo $indicators['techniciensFiliales']; ?>
                        </p>
                    </div>
                </div>

                <!-- Séparateur vertical entre les groupes de graphiques -->

                <div class="v-separator"></div>
                <!-- Groupe du graphique "Validation" -->
                <div class="chart-group">
                    <div class="chart-box">
                        <h3><span class="title-bold">Validation</span> des Plans Individuels de Formation (PIF) par la
                            Filiale</h3>
                        <canvas id="validationChart"></canvas>
                        <p class="chart-stats">
                            Nombre de PIF validé par la filiale <?php echo $validationData['values'][1]; ?> /
                            <?php echo $indicators['techniciensAvecPIF']; ?>
                        </p>

                    </div>
                </div>
            </div> <!-- .charts-container -->
        </div><!-- .container -->
    </div><!-- .content -->

    <!-- Script : initialisation des graphiques avec Chart.js -->
    <script>
    // Données pour le donut "Proposition"
    const dataProposition = {
        labels: <?php echo json_encode($propositionData['labels']); ?>,
        values: <?php echo json_encode($propositionData['values']); ?>,
        colors: <?php echo json_encode($propositionData['colors']); ?>
    };

    const ctxProp = document.getElementById('propositionChart').getContext('2d');
    new Chart(ctxProp, {
        type: 'doughnut',
        data: {
            labels: dataProposition.labels,
            datasets: [{
                data: dataProposition.values,
                backgroundColor: dataProposition.colors,
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            cutout: '45%', // Définit l'épaisseur de l'anneau
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        font: {
                            size: 16,
                            weight: 'bold'
                        },
                        color: '#333',
                        generateLabels: function(chart) {
                            const data = chart.data;
                            if (data.labels.length && data.datasets.length) {
                                return data.labels.map((label, i) => {
                                    let dataset = data.datasets[0];
                                    let value = dataset.data[i];
                                    return {
                                        text: value + label,
                                        fillStyle: dataset.backgroundColor[i],
                                        strokeStyle: dataset.borderColor[i],
                                        lineWidth: dataset.borderWidth,
                                        hidden: isNaN(dataset.data[i]),
                                        index: i
                                    };
                                });
                            }
                            return [];
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        title: function(context) {
                            // context[0] = l'élément correspondant au segment survolé
                            const dataIndex = context[0].dataIndex; // L'index du segment
                            const dataLabel = context[0].chart.data.labels[dataIndex]; // ex: "% PIF validé"
                            const dataset = context[0].dataset;
                            const value = dataset.data[dataIndex]; // ex: 117
                            // On construit la chaîne de la 1ère ligne
                            return value + dataLabel; // ex: "117% PIF validé"
                        },
                        label: function(context) {
                            // Par défaut, la ligne 'label' renvoie le label du dataset
                            // On peut la vider, ou mettre un texte différent :
                            return ''; // si on ne veut qu'une seule ligne
                        }
                    },

                    titleFont: {
                        size: 16,
                        weight: 'bold'
                    },
                    bodyFont: {
                        size: 14
                    }
                }
            }
        }
    });

    // Données pour le donut "Validation"
    const dataValidation = {
        labels: <?php echo json_encode($validationData['labels']); ?>,
        values: <?php echo json_encode($validationData['values']); ?>,
        colors: <?php echo json_encode($validationData['colors']); ?>
    };

    const ctxVal = document.getElementById('validationChart').getContext('2d');
    new Chart(ctxVal, {
        type: 'doughnut',
        data: {
            labels: dataValidation.labels,
            datasets: [{
                data: dataValidation.values,
                backgroundColor: dataValidation.colors,
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            cutout: '45%',
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        font: {
                            size: 16,
                            weight: 'bold'
                        },
                        color: '#333',
                        generateLabels: function(chart) {
                            const data = chart.data;
                            if (data.labels.length && data.datasets.length) {
                                return data.labels.map((label, i) => {
                                    let dataset = data.datasets[0];
                                    let value = dataset.data[i];
                                    return {
                                        text: value + label,
                                        fillStyle: dataset.backgroundColor[i],
                                        strokeStyle: dataset.borderColor[i],
                                        lineWidth: dataset.borderWidth,
                                        hidden: isNaN(dataset.data[i]),
                                        index: i
                                    };
                                });
                            }
                            return [];
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        title: function(context) {
                            // context[0] = l'élément correspondant au segment survolé
                            const dataIndex = context[0].dataIndex; // L'index du segment
                            const dataLabel = context[0].chart.data.labels[dataIndex]; // ex: "% PIF validé"
                            const dataset = context[0].dataset;
                            const value = dataset.data[dataIndex]; // ex: 117
                            // On construit la chaîne de la 1ère ligne
                            return value + dataLabel; // ex: "117% PIF validé"
                        },
                        label: function(context) {
                            // Par défaut, la ligne 'label' renvoie le label du dataset
                            // On peut la vider, ou mettre un texte différent :
                            return ''; // si on ne veut qu'une seule ligne
                        }

                    },

                    titleFont: {
                        size: 16,
                        weight: 'bold'
                    },
                    bodyFont: {
                        size: 14
                    }
                }
            }
        }
    });

    window.addEventListener('load', function() {
        const spinner = document.getElementById('spinner');
        if (spinner) {
            spinner.classList.remove('show');
            spinner.classList.add('d-none');
        }
    });
    </script>

    <!-- Script pour affichage de debug -->
    <script>
    const indicators = <?php echo json_encode($indicators); ?>;
    const propositionDataDump = <?php echo json_encode($propositionData); ?>;
    const validationDataDump = <?php echo json_encode($validationData); ?>;
    console.log("Indicateurs :", indicators);
    console.log("Données Proposition :", propositionDataDump);
    console.log("Données Validation :", validationDataDump);
    </script>
</body>

</html>