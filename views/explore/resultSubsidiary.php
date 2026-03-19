<?php
    session_start();
    include_once "../language.php";
    include_once "../partials/background-manager.php";
    setPageBackground("bg-dashboard");

    if (!isset($_SESSION["profile"])) {
        header("Location: ../../");
        exit();
    } else {
        require_once "../../vendor/autoload.php";
        date_default_timezone_set('Africa/Douala');

        // --- Connexion et Collections ---
        $conn = new MongoDB\Client("mongodb://localhost:27017");
        $academy = $conn->academy;
        $reports_collection = $academy->reports_subsidiary_results;

        // --- Vérification et génération du cache ---
        $today_midnight_ts = strtotime('today midnight');
        $today_start = new MongoDB\BSON\UTCDateTime($today_midnight_ts * 1000);

        $reportExists = $reports_collection->count(['date' => $today_start]);

        if ($reportExists === 0) {
            // Silently include and run the generation script.
            ob_start();
            include_once __DIR__ . '/../../cronjobs/generate_subsidiary_reports.php';
            ob_end_clean();
        }

        // --- Récupération des données pré-calculées ---
        $report_data = $reports_collection->find(['date' => $today_start])->toArray();
        
        // --- Préparation des données pour la page ---
        $page_data = [];
        $all_subsidiaries_for_filter = [];

        foreach ($report_data as $doc) {
            $doc_array = json_decode(json_encode($doc), true);
            $page_data[] = $doc_array;
            $all_subsidiaries_for_filter[] = [
                'country' => $doc_array['country'],
                'flag' => $doc_array['flag']
            ];
        }
        
        usort($all_subsidiaries_for_filter, function($a, $b) {
            return strcmp($a['country'], $b['country']);
        });

        // --- Données pour les filtres statiques ---
        $BRAND_GROUP_LABELS = [
            'VL'       => 'Véhicules légers',
            'TRUCKS'   => 'Camions',
            'BUS'      => 'Bus',
            'CHARIOTS' => 'Chariots',
            'ENGINS'   => 'Engins',
        ];
        $BRAND_GROUPS = [
            'VL'       => ['TOYOTA', 'BYD', 'MITSUBISHI', 'MERCEDES', 'PEUGEOT', 'CITROEN', 'SUZUKI'],
            'TRUCKS'   => ['RENAULT TRUCK', 'MERCEDES TRUCK', 'SINOTRUK', 'HINO', 'FUSO'],
            'BUS'      => ['KING LONG'],
            'CHARIOTS' => ['TOYOTA FORKLIFT', 'TOYOTA BT'],
            'ENGINS'   => ['JCB', 'LOVOL'],
        ];
        $BRAND_GROUP_FA = [
            'VL'       => 'fa-solid fa-car-side',
            'TRUCKS'   => 'fa-solid fa-truck-moving',
            'BUS'      => 'fa-solid fa-bus-simple',
            'CHARIOTS' => 'fa-solid fa-dolly',
            'ENGINS'   => 'fa-solid fa-tractor',
        ];
        $ALL_BRANDS = array_unique(call_user_func_array('array_merge', array_values($BRAND_GROUPS)));
        sort($ALL_BRANDS);

        $brandLogos = [
            'RENAULT TRUCK'   => 'renaultTrucks.png', 'HINO' => 'Hino_logo.png', 'TOYOTA BT' => 'bt.png',
            'SINOTRUK'        => 'sinotruk.png', 'JCB' => 'jcb.png', 'MERCEDES TRUCK' => 'mercedestruck.png',
            'TOYOTA FORKLIFT' => 'forklift.png', 'FUSO' => 'fuso.png', 'LOVOL' => 'lovol.png',
            'KING LONG'       => 'kl2.png', 'MERCEDES' => 'mercedestruck.png', 'TOYOTA' => 'toyota-logo.png',
            'SUZUKI'          => 'suzuki-logo.png', 'MITSUBISHI' => 'mitsubishi-logo.png',
            'BYD'             => 'byd-logo.png', 'CITROEN' => 'citroen-logo.png', 'PEUGEOT' => 'peugeot-logo.png',
        ];
        $brandLogoBase = '../../public/images/';

        $levelFilter = $_GET['level'] ?? 'all_levels';
        $brandGroupsFilter = isset($_GET['brandGroups']) ? (array)$_GET['brandGroups'] : [];
        $brandsFilter = isset($_GET['brands']) ? (array)$_GET['brands'] : [];
        $subsidiariesFilter = isset($_GET['subsidiaries']) ? (array)$_GET['subsidiaries'] : [];
    ?>
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <title>Résultats Filiales | CFAO Mobility Academy</title>
            <meta charset="utf-8" />

    ?>
        <!DOCTYPE html>

        <html lang="fr">

        <head>
            <?php if ($levelFilter && $levelFilter !== 'all_levels') { ?>
                <title><?php echo 'État d\'Avancement des Résultats des Filiales Niveau ' . htmlspecialchars($levelFilter); ?> | CFAO Mobility Academy</title>
            <?php } else { ?>
                <title>État d'Avancement des Résultats des Filiales Tous Niveaux | CFAO Mobility Academy</title>
            <?php } ?>

            <meta charset="utf-8" />
            <style>
                .glass-effect {
                    background: rgba(255, 255, 255, .72) !important;
                    backdrop-filter: blur(18px) !important;
                    -webkit-backdrop-filter: blur(18px) !important;
                    border: 1px solid rgba(255, 255, 255, .18) !important;
                    box-shadow: 0 8px 32px rgba(31, 38, 135, .15) !important;
                    border-radius: 16px !important;
                    transition: transform .25s ease, box-shadow .25s ease, background .25s ease !important;
                }

                .glass-effect:hover {
                    background: rgba(255, 255, 255, .82) !important;
                    transform: translateY(-3px);
                }

                .depth-effect {
                    transform: translateZ(0);
                    box-shadow:
                        0 4px 6px -1px rgba(0, 0, 0, .10),
                        0 2px 4px -1px rgba(0, 0, 0, .06),
                        0 12px 20px -2px rgba(0, 0, 0, .05) !important;
                    transition: transform .25s ease, box-shadow .25s ease !important;
                }

                .depth-effect:hover {
                    transform: translateY(-5px) translateZ(0);
                    box-shadow:
                        0 10px 15px -3px rgba(0, 0, 0, .10),
                        0 4px 6px -2px rgba(0, 0, 0, .05),
                        0 20px 25px -5px rgba(0, 0, 0, .03) !important;
                }

                .card {
                    background: transparent;
                    border: none;
                }

                .card-body {
                    padding: .75rem;
                }

                .responsive-chart-container {
                    width: 100%;
                    position: relative;
                }

                .responsive-chart-container canvas {
                    width: 100% !important;
                    height: auto !important;
                }

                /* KPI cards */
                .kpi-card {
                    border-radius: 14px;
                    text-align: center;
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    align-items: center;
                    min-height: 120px;
                }

.kpi-value {
    font-weight: 900;
    font-size: 36px;
    text-shadow: 
        1px 1px 2px rgba(0,0,0,0.25),
        0 0 8px rgba(0,0,0,0.15);
    transition: all 0.3s ease;
}

/* Glow en conservant TES couleurs */
.kpi-value.green {
    color: #6CF95D;
    text-shadow: 0 0 6px rgba(108,249,93,0.7),
                 0 0 12px rgba(108,249,93,0.4);
}
.kpi-value.yellow {
    color: #FAF75A;
    text-shadow: 0 0 6px rgba(250,247,90,0.7),
                 0 0 12px rgba(250,247,90,0.4);
}
.kpi-value.red {
    color: #FB9258;
    text-shadow: 0 0 6px rgba(251,146,88,0.7),
                 0 0 12px rgba(251,146,88,0.4);
}


                .kpi-sub {
                    font-size: 14px;
                    color: #0b0b0b;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 6px;
                    min-height: 24px;
                }

                .kpi-card-flag {
                    width: 28px;
                    height: 20px;
                    border-radius: 3px;
                    box-shadow: 0 1px 3px rgba(0, 0, 0, .1);
                }

                .kpi-sub img.kpi-card-flag {
                    width: 20px;
                    height: 14px;
                    border-radius: 3px;
                    box-shadow: 0 1px 2px rgba(0,0,0,.15);
                }  
                
                .kpi-sub-label {
                    font-size: 13px;
                    color: black;
                }

                /* Legend in header */
                .legend-dot {
                    width: 14px;
                    height: 14px;
                    border-radius: 4px;
                    display: inline-block;
                    margin-right: 6px;
                }

                .legend-item {
                    display: flex;
                    align-items: center;
                    gap: 6px;
                    margin-left: 14px;
                    font-size: 12px;
                }

                /* Flags in table */
                .flag-badge img {
                    width: 28px;
                    height: 20px;
                    object-fit: cover;
                    border: 1px solid rgba(0, 0, 0, .08);
                    border-radius: 3px;
                    display: block;
                    margin: 6px auto 0 auto;
                }

                /* Table centering and no striping */
                .table-centered th,
                .table-centered td {
                    text-align: center;
                    vertical-align: middle;
                }

                .table.no-striped>tbody>tr:nth-of-type(odd) {
                    background-color: transparent !important;
                }

                .table.no-striped>tbody>tr:hover {
                    background-color: rgba(255, 255, 255, 0.1) !important;
                }


                /* Filter header white */
                .filter-header-white .card-title {
                    color: black !important;

                }

                .filter-header-white .card-title .ki-duotone,
                .filter-header-white .card-title .bi {
                    color: black !important;
                }

                .ki-duotone.ki-chart-pie-3 .path1,
                .ki-duotone.ki-chart-pie-3 .path2,
                .ki-duotone.ki-filter .path1,
                .ki-duotone.ki-filter .path2 {
                    fill: currentColor !important;
                }

                #filters-card .card-body {
                    overflow-y: auto;
                }

                #filters-card {
                    box-sizing: border-box;
                    margin-bottom: 0 !important;
                    /* remplace l'effet de mb-6 */
                }

                /* Drapeaux dans Select2 */
                .sel2-flag-row {
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                }

                .sel2-flag {
                    width: 18px;
                    height: 12px;
                    object-fit: cover;
                    border-radius: 2px;
                    box-shadow: 0 0 0 1px rgba(0, 0, 0, .15);
                }
                .sel2-brand-logo {
                    height: 20px;
                    width: 30px;
                    object-fit: contain;
                    margin-right: 8px;
                }

                /* Emoji/icon pour select2 (groupes) */
                .sel2-emoji {
                    width: 18px;
                    height: 18px;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 14px;
                }

                :root { --veh-icon-color: #8B5CF6; } /* violet */

                /* Icônes dans les listes Select2 */
                .sel2-fa, .sel2-ki {
                width: 18px; height: 18px;
                display: inline-flex; align-items: center; justify-content: center;
                font-size: 16px; line-height: 1;
                color: var(--veh-icon-color) !important; /* <- la couleur */
                }

                /* Spécifique KeenIcons duotone : forcer la couleur sur les paths */
                .sel2-ki .path1, .sel2-ki .path2, .sel2-ki .path3 {
                fill: currentColor !important;
                }

                /* Icônes aussi violettes dans les â€œtagsâ€ sélectionnés de Select2 */
                .select2-container--bootstrap4 .select2-selection__choice .sel2-fa,
                .select2-container--bootstrap4 .select2-selection__choice .sel2-ki {
                color: var(--veh-icon-color) !important;
                }


                /* Jolis tags sélectionnés avec drapeau */
                .select2-container--bootstrap4 .select2-selection__choice {
                    display: flex;
                    align-items: center;
                    gap: 6px;
                }

                .select2-container--bootstrap4 .select2-selection__choice .sel2-flag {
                    margin-right: 4px;
                }
                /* Cache le 0 du champ inline de recherche */
                #subsidiaries-filter + .select2 .select2-search__field {
                    width: 0 !important;
                    padding: 0 !important;
                    margin: 0 !important;
                    opacity: 0 !important;   /* il existe toujours, mais invisible */
                }

                /* Personnalisation des croix (tags Select2) */
                .select2-container--bootstrap4 .select2-selection__choice__remove {
                border: none !important;
                background: #ff4d4d !important;       /* rouge clair pour le fond */
                color: white !important;              /* croix blanche */
                font-weight: bold !important;
                font-size: 12px !important;
                width: 18px;
                height: 18px;
                border-radius: 50%;                   /* rond */
                display: flex;
                align-items: center;
                justify-content: center;
                margin-right: 6px;                    /* espace entre drapeau et croix */
                cursor: pointer;
                transition: background 0.2s ease;
                }

                .select2-container--bootstrap4 .select2-selection__choice__remove:hover {
                background: #e60000 !important;       /* rouge plus foncé au hover */
                }

                /* Bouton Clear All (remove all sélectionnés) */
                #subsidiaries-filter + .select2 .select2-selection__clear {
                position: absolute;
                top: 8px;        /* espace du haut */
                right: 12px;     /* collé à  droite */
                z-index: 5;
                font-size: 14px;
                font-weight: bold;
                color: #666;
                cursor: pointer;
                transition: color 0.2s ease;
                }

                #subsidiaries-filter + .select2 .select2-selection__clear:hover {
                color: #e60000;  /* rouge au hover */
                }

                #subsidiaries-filter + .select2 .select2-selection--multiple {
                padding-right: 28px !important; /* espace réservé pour la croix */
                position: relative;             /* nécessaire pour position absolue du bouton */
                }

#brand-groups + .select2 .select2-search__field,
#brands + .select2 .select2-search__field {
  width: 0 !important; padding: 0 !important; margin: 0 !important; opacity: 0 !important;
}


                /* Griser les options déjà  sélectionnées dans le dropdown Select2 */
                .select2-container--bootstrap4 .select2-results__option[aria-selected="true"] {
                    opacity: 0.5;
                }


            </style>

            <?php include "./partials/header.php"; ?>

            <!-- Select2 -->
            <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
            <link href="https://cdnjs.cloudflare.com/ajax/libs/select2-bootstrap-theme/0.1.0-beta.10/select2-bootstrap.min.css" rel="stylesheet" />

            <!-- jQuery + Select2 -->
            <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>

            <!-- Chart.js + Datalabels -->
            <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/chartjs-plugin-datalabels/2.2.0/chartjs-plugin-datalabels.min.js"></script>
        </head>

        <body>
            <?php openBackgroundContainer(); ?>
            <!--begin::Content-->

            <?php if ($_SESSION["profile"] == "Super Admin" || $_SESSION["profile"] == "Directeur Groupe") { ?>


                <!-- Layout -->
                <div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
                    <div class="container-xxl">
                        <!-- Title -->
                        <div style="margin-top: 25px; margin-bottom: 25px">
                            <div class="card " style="background-color: white">
                                <div class="card-body text-center p-4 d-flex justify-content-center align-items-center">
                                    <i class="ki-duotone ki-chart-pie-3 fs-2x me-3">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>

                                    </i>
                                    <h1 class="text-dark fw-bolder my-1 fs-2">
                                        <?php
                                        if ($levelFilter && $levelFilter !== 'all_levels') {
                                            echo 'État d\'Avancement des Résultats des Filiales Niveau ' . htmlspecialchars($levelFilter);
                                        } else {
                                            echo 'État d\'Avancement des Résultats des Filiales Tous Niveaux';
                                        }
                                        ?>
                                    </h1>
                                </div>
                            </div>
                        </div>
                        <div class="row g-6">
                            <!-- Main -->
                            <div class="col-xl-9 col-lg-8" id="left-col">
                                <!-- KPI ROW -->
                                <div class="row g-4 mb-4" id="kpi-row">
                                    <div class="col-sm-6 col-xl-3">
                                        <div class="card glass-effect depth-effect kpi-card p-4 h-100 text-center">
                                            <div id="kpi-count" class="kpi-value">0</div>
                                            <div class="kpi-sub-label" style="color : black">Filiales affichées</div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-xl-3">
                                        <div class="card glass-effect depth-effect kpi-card p-4 h-100 text-center">
                                            <div id="kpi-avg" class="kpi-value">0%</div>
                                            <div class="kpi-sub-label" style="color : black">Moyenne générale</div>
                                        </div>
                                    </div>
                                   <div class="col-sm-6 col-xl-3">
                                        <div class="card glass-effect depth-effect kpi-card p-4 h-100 text-center">
                                            <div class="kpi-value"><span id="kpi-top">0%</span></div>
                                            <div class="kpi-sub-label">
                                            Meilleur résultat : <span id="kpi-top-country"></span>
                                            </div>
                                        </div>
                                        </div>

                                        <div class="col-sm-6 col-xl-3">
                                        <div class="card glass-effect depth-effect kpi-card p-4 h-100 text-center">
                                            <div class="kpi-value"><span id="kpi-bottom">0%</span></div>
                                            <div class="kpi-sub-label">
                                            Plus faible résultat : <span id="kpi-bottom-country"></span>
                                            </div>
                                        </div>
                                        </div>

                                </div>

                                <!-- Chart Card -->
                                <div class="card glass-effect depth-effect mb-6" id="chart-card">
                                    <div class="card-header d-flex justify-content-between align-items-center" style="background-color: white;">
                                        <div class="d-flex align-items-center">
                                            <i class="ki-duotone ki-chart-pie-3 fs-2 me-2">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                                <span class="path3"></span>

                                            </i>
                                            <h3 class="card-title mb-0">Résultats par filiale</h3>
                                        </div>
                                        <!-- Légende -->
                                        <div class="d-flex align-items-center">
                                            <div class="legend-item"><span class="legend-dot" style="background:#6CF95D;"></span>Résultat compris entre 80 et 100%</div>
                                            <div class="legend-item"><span class="legend-dot" style="background:#FAF75A;"></span>Résultat compris entre 60 et 80%</div>
                                            <div class="legend-item"><span class="legend-dot" style="background:#FB9258;"></span>Résultat compris entre 0 et 60%</div>
                                            <div class="legend-item"><span class="legend-dot" style="background:#d44242ff; border: 2px dashed #d44242ff;"></span>Moyenne générale</div>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div id="chart-container" class="responsive-chart-container" style="height: 520px;">
                                            <canvas id="chart"></canvas>
                                        </div>
                                    </div>
                                </div>



                            </div>

                            <!-- Sidebar Filtres -->
                            <div class="col-xl-3 col-lg-4">
                                <div class="card glass-effect depth-effect mb-6" id="filters-card">
                                    <div class="card-header filter-header-white" style="background-color: white;">
                                        <h3 class="card-title">
                                            <i class="ki-duotone ki-filter fs-2 me-2">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i> Filtres
                                        </h3>
                                    </div>
                                    <div class="card-body">
                                        <!-- Niveaux -->
                                        <div class="mb-6">
                                            <label for="level-filter" class="form-label d-flex align-items-center mb-3">
                                                <i class="bi bi-bar-chart-fill fs-4 me-2 text-warning"></i><strong>Niveaux</strong>
                                            </label>
                                            <select id="level-filter" name="level" class="form-select form-select-lg" onchange="applyClientFilters()">
                                                <option value="all_levels" <?php if ($levelFilter === 'all_levels') echo 'selected'; ?>>Tous les niveaux</option>
                                                <?php foreach (['Junior', 'Senior', 'Expert'] as $levelOption): ?>
                                                    <option value="<?php echo htmlspecialchars($levelOption); ?>" <?php if ($levelFilter === $levelOption) echo 'selected'; ?>>
                                                        <?php echo htmlspecialchars($levelOption); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>

                                            <div class="form-text">Sélectionnez un niveau pour filtrer les résultats</div>
                                        </div>

                                        <div class="separator border-gray-300 mb-6"></div>

                                        <!-- Filiales -->
                                        <div class="mb-6">
                                            <label for="subsidiaries-filter" class="form-label d-flex align-items-center mb-3">
                                                <i class="bi bi-building fs-4 me-2 text-primary"></i><strong>Comparaison de filiales</strong>
                                            </label>
                                            <select id="subsidiaries-filter" name="subsidiaries[]" class="form-select form-select-lg"
                                                data-control="select2" data-placeholder="Toutes les filiales..."
                                                data-allow-clear="true" multiple="multiple" onchange="applyClientFilters()">
                                                <?php foreach ($all_subsidiaries_for_filter as $data): ?>
                                                    <option
                                                        value="<?php echo htmlspecialchars(strtoupper($data['country'])); ?>"
                                                        data-flag="<?php echo htmlspecialchars($data['flag'] ?? ''); ?>"
                                                        <?php if (in_array(strtoupper($data['country']), $subsidiariesFilter)) echo 'selected'; ?>>
                                                        <?php echo htmlspecialchars($data['country']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>

                                            <div class="form-text"><i class="bi bi-info-circle me-1"></i>Sélectionnez une ou plusieurs filiales pour les comparer</div>
                                        </div>


                                        <div class="separator border-gray-300 mb-6"></div>

                                        <!-- Groupes de marques -->
                                        <div class="mb-6">
                                            <label for="brand-groups" class="form-label d-flex align-items-center mb-3">
                                                <i class="bi bi-diagram-3 fs-4 me-2 text-success"></i><strong>Types de Véhicules</strong>
                                            </label>
                                            <select id="brand-groups" name="brandGroups[]" class="form-select form-select-lg" data-control="select2" multiple="multiple" data-placeholder="Tous les groupes...">

                                                <?php foreach ($BRAND_GROUPS as $gKey => $gBrands): ?>
                                                    <option value="<?php echo htmlspecialchars($gKey); ?>" 
                                                            data-icon="<?php echo htmlspecialchars($BRAND_GROUP_FA[$gKey] ?? ''); ?>"
                                                            <?php if (in_array($gKey, $brandGroupsFilter)) echo 'selected'; ?>>
                                                        <?php echo htmlspecialchars($BRAND_GROUP_LABELS[$gKey] ?? $gKey); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>


                                            <div class="form-text">Choisissez un ou plusieurs groupes (VL, Camions, Bus, Chariots, Engins)</div>
                                        </div>

                                        <!-- Marques -->
                                        <div class="mb-6">
                                            <label for="brands" class="form-label d-flex align-items-center mb-3">
                                                <i class="bi bi-tag fs-4 me-2 text-info"></i><strong>Marques</strong>
                                            </label>
                                            <select id="brands" name="brands[]" class="form-select form-select-lg" data-control="select2" multiple="multiple" data-placeholder="Toutes les marques...">
                                                <?php foreach ($ALL_BRANDS as $brand): ?>
                                                    <option value="<?php echo htmlspecialchars($brand); ?>"
                                                            data-logo="<?php echo isset($brandLogos[$brand]) ? htmlspecialchars($brandLogoBase . $brandLogos[$brand]) : ''; ?>"
                                                            <?php if (in_array($brand, $brandsFilter)) echo 'selected'; ?>>
                                                        <?php echo htmlspecialchars($brand); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="form-text">Laissez vide pour toutes les marques.</div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                            <!-- /Sidebar -->
                        </div>
                    </div>
                </div>
            <?php } ?>

            <!--end::Content-->

            <?php closeBackgroundContainer(); ?>
            <?php include "./partials/footer.php"; ?>
<script>
  /* === Données & constantes injectées par PHP === */
  const allReportData    = <?php echo json_encode($page_data); ?>;
  const BRAND_GROUPS_MAP = <?php echo json_encode($BRAND_GROUPS); ?>;
  const ALL_BRANDS_LIST  = <?php echo json_encode($ALL_BRANDS); ?>;

  const APP_WEB_BASE     = '/MEDACAP';

  let chart;

  /* 2) fonctions utilitaires de filtrage de la liste Marques */
    function brandsForGroups(groups) {
    if (!groups || groups.length === 0) return [...ALL_BRANDS_LIST]; // aucun groupe â†’ toutes les marques
    const set = new Set();
    groups.forEach(g => (BRAND_GROUPS_MAP[g] || []).forEach(b => set.add(b)));
    return Array.from(set).sort();
    }

      const BRAND_LOGOS = <?php
        // construit { "TOYOTA": "/MEDACAP/public/images/toyota-logo.png", ... }
        $logosForJs = [];
        foreach ($brandLogos as $name => $file) {
        $logosForJs[$name] = $brandLogoBase . $file; // ex: '../../public/images/...'
        }
        echo json_encode($logosForJs);
    ?>;

    /**
     * Rafraà®chit les options du select #brands en fonction des groupes sélectionnés.
     * - conserve seulement l'intersection des marques déjà  sélectionnées
     * - déclenche un 'change' pour mettre à  jour le graphe + lâ€™URL
     */
    function refreshBrandDropdown() {
        const $brands = $('#brands');
        const selectedGroups = $('#brand-groups').val() || [];
        const allowed = brandsForGroups(selectedGroups);

        // valeurs sélectionnées encore valides
        const current = $brands.val() || [];
        const nextSelected = current.filter(v => allowed.includes(v));

        // reconstruit les <option> AVEC data-logo
        $brands.empty();
        allowed.forEach(b => {
            const opt = new Option(b, b, false, nextSelected.includes(b));
            opt.setAttribute('data-logo', BRAND_LOGOS[b] || '');
            $brands.append(opt);
        });

        // refresh select2 (options + chips) puis déclenche ton filtrage
        $brands.trigger('change.select2');
        $brands.val(nextSelected).trigger('change'); // applyClientFilters() est déjà  sur 'change'
    }


  /* === Helpers de chemin (drapeaux) === */
  function normalizeFlag(url) {
    if (!url) return '';
    url = String(url).replace(/\\/g, '/');

    // Absolu ?
    if (/^https?:\/\//i.test(url) || url.startsWith('/')) return url;

    // Enlève éventuel préfixe "MEDACAP/" ou "/MEDACAP/"
    url = url.replace(/^\/?medacap\//i, '');

    // Enlève "./" et "../" initiaux
    url = url.replace(/^(\.\/|(\.\.\/)+)/, '');

    // Cas fréquents de la base de données
    if (url.startsWith('views/'))  return APP_WEB_BASE.replace(/\/$/, '') + '/' + url;
    if (url.startsWith('define/')) return APP_WEB_BASE.replace(/\/$/, '') + '/views/' + url;

    // Fallback
    return APP_WEB_BASE.replace(/\/$/, '') + '/' + url.replace(/^\/+/, '');
  }

  function buildFlagImages(flagUrls) {
    return flagUrls.map(src => {
      const img = new Image();
      img.decoding = 'async';
      img.loading = 'eager';
      img.src = normalizeFlag(src || '');
      if (!img.complete) {
        const tryUpdate = () => { try { if (typeof chart !== 'undefined' && chart && typeof chart.update === 'function') chart.update('none'); } catch(e) {} };
        img.onload = tryUpdate;
        img.onerror = tryUpdate;
      }
      return img;
    });
  }

  /* === UI helpers === */
  function kpiColor(v){ if (v>=80) return '#6CF95D'; if (v>=60) return '#FAF75A'; return '#FB9258'; }

  function animateCountUp(el, end, duration=1200, suffix=''){
    if (!el) return;
    const start = parseInt(el.textContent.replace(/\D/g,''))||0;
    const range = end - start;
    if (range===0){ el.textContent=end+suffix; return; }
    const t0 = performance.now();
    const step = now => {
      const p = Math.min((now - t0)/duration, 1);
      el.textContent = (Math.floor(start + range*p)) + suffix;
      if (p<1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  }

  // Filtrage logique
  function hasAnyBrand(resultsForLevel, brands){
    if (!resultsForLevel || !resultsForLevel.by_brand) return false;
    return brands.some(b => resultsForLevel.by_brand[b]);
  }
  function hasAnyGroup(resultsForLevel, groups){
    if (!resultsForLevel || !resultsForLevel.by_brand_group) return false;
    return groups.some(g => resultsForLevel.by_brand_group[g]);
  }

  // MAJ URL sans recharger
  function updateUrlFromFilters() {
    const level  = $('#level-filter').val() || 'all_levels';
    const groups = $('#brand-groups').val() || [];
    const brands = $('#brands').val() || [];
    const subs   = $('#subsidiaries-filter').val() || [];

    const url = new URL(window.location.href);
    if (level && level !== 'all_levels') url.searchParams.set('level', level);
    else url.searchParams.delete('level');

    url.searchParams.delete('brandGroups');
    url.searchParams.delete('brands');
    url.searchParams.delete('subsidiaries');

    groups.forEach(g => url.searchParams.append('brandGroups', g));
    brands.forEach(b => url.searchParams.append('brands', b));
    subs.forEach(s => url.searchParams.append('subsidiaries', s));

    history.replaceState(null, '', url.toString());
  }

  /* === KPI + Graphe === */
  function updateKpis(chartData){
    // Ne considérer que les valeurs strictement > 0
    const entries = (chartData.data || []).map((v,i)=>({v,i})).filter(e => typeof e.v === 'number' && e.v > 0);
    const n   = entries.length;
    const avg = n ? Math.round(entries.reduce((a,c)=>a+c.v,0)/n) : 0;

    let topV=-1, topI=-1, botV=101, botI=-1;
    entries.forEach(({v,i})=>{ if(v>topV){topV=v;topI=i;} if(v<botV){botV=v;botI=i;} });

    animateCountUp(document.getElementById('kpi-count'),   n,   1200, '');
    animateCountUp(document.getElementById('kpi-avg'),     avg, 1200, '%');
    animateCountUp(document.getElementById('kpi-top'),     topV>=0?topV:0, 1200, '%');
    animateCountUp(document.getElementById('kpi-bottom'),  botV<=100?botV:0,1200, '%');

    document.getElementById('kpi-avg').style.color    = kpiColor(avg);
    document.getElementById('kpi-top').style.color    = kpiColor(topV);
    document.getElementById('kpi-bottom').style.color = kpiColor(botV);

    const topCountryEl    = document.getElementById('kpi-top-country');
    const bottomCountryEl = document.getElementById('kpi-bottom-country');

    if (topI>=0 && chartData.flags[topI]) topCountryEl.innerHTML = `<img src="${normalizeFlag(chartData.flags[topI])}" class="kpi-card-flag" alt="${chartData.labels[topI]}">`;
    else topCountryEl.textContent = topI>=0 ? chartData.labels[topI] : '';

    if (botI>=0 && chartData.flags[botI]) bottomCountryEl.innerHTML = `<img src="${normalizeFlag(chartData.flags[botI])}" class="kpi-card-flag" alt="${chartData.labels[botI]}">`;
    else bottomCountryEl.textContent = botI>=0 ? chartData.labels[botI] : '';
  }

  function updateChart(chartData){
    if (!chart) return;
    // Filtrer toute valeur non numérique ou <= 0 pour éviter des espaces et drapeaux orphelins
    const filteredLabels = [];
    const filteredData   = [];
    const filteredColors = [];
    const filteredFlags  = [];
    (chartData.data || []).forEach((val, i) => {
      if (typeof val === 'number' && isFinite(val) && val > 0) {
        filteredLabels.push((chartData.labels || [])[i]);
        filteredData.push(val);
        filteredColors.push((chartData.colors || [])[i]);
        filteredFlags.push((chartData.flags || [])[i]);
      }
    });

    // Calculer la moyenne pour la ligne horizontale
    const avg = filteredData.length > 0 ? Math.round(filteredData.reduce((a, b) => a + b, 0) / filteredData.length) : 0;
    const avgLineData = new Array(filteredData.length).fill(avg);

    chart.data.labels = filteredLabels;
    chart.data.datasets[0].data = filteredData;
    chart.data.datasets[0].backgroundColor = filteredColors;
    chart.data.datasets[1].data = avgLineData; // Ligne de moyenne
    chart.$flags = buildFlagImages(filteredFlags);
    chart.update('active');
    afterChartUpdatedSyncHeight();
  }

  function applyClientFilters(){
    const level          = $('#level-filter').val() || 'all_levels';
    const selectedGroups = $('#brand-groups').val() || [];
    const selectedBrands = $('#brands').val() || [];
    const selectedSubs   = ($('#subsidiaries-filter').val() || []).map(s => s.toUpperCase());

    updateUrlFromFilters();

    let filtered = allReportData;
    if (selectedSubs.length > 0) {
      filtered = filtered.filter(doc => selectedSubs.includes(String(doc.country).toUpperCase()));
    }

    const chartData = { labels: [], data: [], flags: [], colors: [] };

    for (const doc of filtered) {
      const resultsForLevel = (doc.results && doc.results[level]) ? doc.results[level] : null;
      let score = 0;

      if (selectedBrands.length > 0) {
        if (!hasAnyBrand(resultsForLevel, selectedBrands)) continue;
        const vals = selectedBrands
          .map(b => resultsForLevel.by_brand && resultsForLevel.by_brand[b] ? resultsForLevel.by_brand[b].average : null)
          .filter(v => typeof v === 'number');
        if (vals.length === 0) continue;
        score = vals.reduce((a,b)=>a+b,0)/vals.length;

      } else if (selectedGroups.length > 0) {
        if (!hasAnyGroup(resultsForLevel, selectedGroups)) continue;
        const vals = selectedGroups
          .map(g => resultsForLevel.by_brand_group && resultsForLevel.by_brand_group[g] ? resultsForLevel.by_brand_group[g].average : null)
          .filter(v => typeof v === 'number');
        if (vals.length === 0) continue;
        score = vals.reduce((a,b)=>a+b,0)/vals.length;

      } else {
        score = (resultsForLevel && typeof resultsForLevel.average === 'number') ? resultsForLevel.average : 0;
      }

      const rounded = Math.round(score);
      chartData.labels.push(String(doc.country).toUpperCase());
      chartData.data.push(rounded);
      chartData.flags.push(doc.flag || '');
      chartData.colors.push(kpiColor(rounded));
    }

    updateChart(chartData);
    updateKpis(chartData);
  }
  window.applyClientFilters = applyClientFilters;

  /* === Templates Select2 === */
  function formatCountry(state){
    if (!state.id) return state.text;
    const flag = $(state.element).data('flag');
    return $(`
      <span class="sel2-flag-row">
        ${flag ? `<img class="sel2-flag" src="${normalizeFlag(flag)}" alt="">` : ''}
        <span>${state.text}</span>
      </span>
    `);
  }
  function formatBrandGroup(state){
    if (!state.id) return state.text;
    const icon = $(state.element).data('icon');
    return $(`<span class="sel2-flag-row"><i class="${icon} sel2-fa"></i><span>${state.text}</span></span>`);
  }
  function formatBrand(state){
    if (!state.id) return state.text;
    const logoUrl = state.element.getAttribute('data-logo');
    let logoHtml = '';
    if (logoUrl) {
        logoHtml = `<img src="${logoUrl}" class="sel2-brand-logo" alt="">`;
    }
    return $(`<span class="sel2-flag-row">${logoHtml}<span>${state.text}</span></span>`);
  }




  /* === Hauteur colonne filtres sync === */
  function syncFilterHeight(){
    if (!window.matchMedia('(min-width: 992px)').matches) {
      const fc = document.getElementById('filters-card');
      if (fc) {
        fc.style.height = '';
        const b = fc.querySelector('.card-body');
        if (b) b.style.maxHeight = '';
      }
      return;
    }
    const filtersCard = document.getElementById('filters-card');
    const chartCard   = document.getElementById('chart-card');
    if (!filtersCard || !chartCard) return;

    const topRight  = filtersCard.getBoundingClientRect().top;
    const bottomLeft= chartCard.getBoundingClientRect().bottom;
    const mb = parseFloat(getComputedStyle(filtersCard).marginBottom) || 0;

    const targetH = Math.max(0, Math.round(bottomLeft - topRight - mb - 1));
    filtersCard.style.height = targetH + 'px';

    const header  = filtersCard.querySelector('.card-header');
    const body    = filtersCard.querySelector('.card-body');
    const headerH = header ? Math.ceil(header.getBoundingClientRect().height) : 0;
    if (body) body.style.maxHeight = Math.max(0, targetH - headerH) + 'px';
  }
  function afterChartUpdatedSyncHeight(){ setTimeout(syncFilterHeight, 50); }

  /* === Init au chargement === */
  document.addEventListener('DOMContentLoaded', function(){
    // Select2
    $('#subsidiaries-filter').select2({
      theme: 'bootstrap4', width: '100%',
      templateResult: formatCountry, templateSelection: formatCountry,
      escapeMarkup: m => m
    });
    $('#brand-groups').select2({
      theme: 'bootstrap4', width: '100%', closeOnSelect: false,
      templateResult: formatBrandGroup, templateSelection: formatBrandGroup,
      escapeMarkup: m => m
    });
    $('#brands').select2({
      theme: 'bootstrap4', width: '100%', closeOnSelect: false,
      templateResult: formatBrand, templateSelection: formatBrand,
      escapeMarkup: m => m
    }).on('change', applyClientFilters);

    // quand les groupes changent -> on filtre la liste des marques
    $('#brand-groups').on('change', function() {
        refreshBrandDropdown();
        applyClientFilters();
    });

    // Manually trigger the initial setup
    refreshBrandDropdown();


    // Chart.js + plugins
    const flagLabelPlugin = {
      id: 'flagLabelPlugin',
      afterDatasetsDraw(chart) {
        const { ctx, chartArea } = chart;
        const meta  = chart.getDatasetMeta(0);
        const flags = chart.$flags || [];
        meta.data.forEach((bar, i) => {
          const img = flags[i];
          if (img && img.complete) ctx.drawImage(img, bar.x - 14, chartArea.bottom + 10, 28, 20);
        });
      }
    };
    const percentLabelPlugin = {
      id: 'percentLabelPlugin',
      afterDatasetsDraw(chart) {
        const { ctx } = chart;
        ctx.save();
        ctx.textAlign = 'center';
        ctx.textBaseline = 'bottom';
        ctx.font = 'bold 16px sans-serif';
        ctx.fillStyle = '#111';
        ctx.strokeStyle = '#fff';
        ctx.lineWidth = 3;
        const values = chart.data.datasets[0].data || [];
        chart.getDatasetMeta(0).data.forEach((bar, i) => {
          const val = values[i];
          if (!(typeof val === 'number' && isFinite(val) && val > 0)) return; // masque 0% et valeurs invalides
          const label = val + '%';
          ctx.strokeText(label, bar.x, bar.y - 6);
          ctx.fillText(label, bar.x, bar.y - 6);
        });
        ctx.restore();
      }
    };

    chart = new Chart(document.getElementById('chart'), {
      type: 'bar',
      data: {
        labels: [],
        datasets: [
          { label: 'Résultat Général', data: [], backgroundColor: [], order: 1 },
          {
            label: 'Moyenne générale',
            type: 'line',
            data: [],
            borderColor: '#d44242ff',
            borderWidth: 3,
            borderDash: [10, 5],
            pointRadius: 0,
            pointHoverRadius: 0,
            fill: false,
            tension: 0,
            spanGaps: true,
            order: 0
          }
        ]
      },
      plugins: [flagLabelPlugin, percentLabelPlugin],
      options: {
        responsive: true, maintainAspectRatio: false,
        layout: { padding: { top: 24, right: 12, left: 12, bottom: 60 } },
        scales: {
          y: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' }, grid: { color: 'rgba(0,0,0,0.05)' } },
          x: { ticks: { display: false }, grid: { display: false } }
        },
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: ctx => ctx.dataset.label + ': ' + ctx.parsed.y + '%' } }
        },
        animation: { duration: 800, easing: 'easeInOutCubic' },
        animations: {
          y: {
            duration: 800,
            easing: 'easeInOutCubic',
            from: ctx => {
              // Anim d'entrée plus douce (barres partent du 0)
              if (ctx.type === 'data' && ctx.mode === 'default' && !ctx.dropped) {
                ctx.dropped = true;
                return 0;
              }
            },
            delay: ctx => (ctx.type === 'data') ? ctx.dataIndex * 25 : 0
          },
          x: {
            duration: 500,
            easing: 'easeOutCubic'
          }
        }
      }
    });

    // Premier rendu
    applyClientFilters();

    // Sync hauteur colonne filtres
    window.addEventListener('load',  syncFilterHeight);
    window.addEventListener('resize', () => {
      clearTimeout(window._sh_to);
      window._sh_to = setTimeout(syncFilterHeight, 150);
    });
  });
</script>




        </body>

        </html>
    <?php } ?>

