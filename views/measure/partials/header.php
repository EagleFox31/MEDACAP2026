    <?php
require_once __DIR__ . '/../../../core/medacap_config.php';
    // Connexion à MongoDB
    require_once "../../vendor/autoload.php";
    $client = new MongoDB\Client(medacapMongoUri());
    $academy = $client->academy;
    $profilesCollection = $academy->profiles;
    $functionalitiesCollection = $academy->functionalities;

    // Gestion du changement de langue (FR/EN)
    if (isset($_GET['lang']) && ($_GET['lang'] === 'EN' || $_GET['lang'] === 'FR')) {
        $_SESSION['lang'] = $_GET['lang'];
    }
    $lang = $_SESSION['lang'] ?? 'FR';
    if ($lang === 'EN') {
        include_once "../language_EN.php";
    } else {
        include_once "../language.php";
    }

    require_once __DIR__.'/helpersMenu.php';
    require_once __DIR__ . '/../../partials/medacap_topbar.php';


    // Récupérer le profil de l'utilisateur
    $currentProfileName = $_SESSION['profile'] ?? '';
    if ($currentProfileName == 'Manager' && $_SESSION['test'] == true) {
        $profile = $profilesCollection->findOne(['name' => 'Manager - Technicien']);
    } else {
        $profile = $profilesCollection->findOne(['name'=> $currentProfileName]);
    }
    $assignedFunctionalities = $profile['functionalities'] ?? [];

    if ($assignedFunctionalities instanceof MongoDB\Model\BSONArray) {
        // Convertir en tableau PHP standard
        $assignedFunctionalities = $assignedFunctionalities->getArrayCopy();
    }
    // ===== Routing du dashboard par profil =====
    $profileForRouting = isset($_SESSION['profile']) ? trim($_SESSION['profile']) : '';

    $dashMap = [
        // Admins
        'Super Admin'                 => 'dashboardAdmin',
        'Admin'                       => 'dashboardAdmin',
        'Directeur Pièce et Service'  => 'dashboardAdmin',
        'Directeur Groupe'   => 'dashboardAdmin',
        'Directeur Général'  => 'dashboardAdmin',

        // RH
        'Ressource Humaine'           => 'dashboardrh',

        // Profils standard
        'Trainer'                     => 'dashboard',
        'Technicien'                  => 'dashboardAdmin',
        'Manager'                     => 'dashboardAdmin',
        'Candidat'                    => 'dashboard',
    ];

    // Fallback sur "dashboard" si profil inconnu
    $dashboardBase  = $dashMap[$profileForRouting] ?? 'dashboard';

    // Deux helpers : route "jolie" et version .php (utile pour certains liens)
    $DASHBOARD_ROUTE = "./{$dashboardBase}";
    $DASHBOARD_FILE  = "./{$dashboardBase}.php";


    // Fonction pour obtenir le module actuel à partir de l'URL
    function getCurrentModule() {
        $currentPath = $_SERVER['REQUEST_URI'];
        $pathParts = explode('/', $currentPath);
        // Supposons que le module est toujours après 'views'
        $moduleIndex = array_search('views', $pathParts) + 1;
        $module = $pathParts[$moduleIndex] ?? '';
        return strtolower($module);
    }

    // Définir un tableau associatif : module => libellé complet
    $moduleLabels = [
        'acquire' => $module_acquire,
        'define'  => $module_define,
        'explore' => $module_explore,
        'measure' => $module_measure,
    ];



    // Obtenir le module actuel
    $currentModule = getCurrentModule();
    $displayModule = isset($moduleLabels[$currentModule])
    ? $moduleLabels[$currentModule]
    : '';

    // Supprimer les doublons après la conversion
    $assignedFunctionalities = array_unique($assignedFunctionalities);

    $allFunctionalities = [];
    // Récupérer les fonctionnalités assignées et actives, triées par group_order et order
    $functionalitiesCursor = $functionalitiesCollection->find(
    [
        '_id' => ['$in' => $assignedFunctionalities],
        'modules' => $currentModule,
        'active' => true,
    ],
    [
        'sort' => ['group_order' => 1, 'order' => 1],
        // Assurez-vous que tous les champs nécessaires sont récupérés
        'projection' => [
            'key' => 1,
            'name' => 1,
            'name_EN' => 1,
            'group' => 1,
            'group_EN' => 1,
            'icon' => 1,
            'icon_type' => 1,
            'url' => 1,
            'order' => 1,
            'modules' => 1,
            'active' => 1
        ]
    ]
);

$allFunctionalities = iterator_to_array($functionalitiesCursor);






    $menuStructure = [];

    foreach ($allFunctionalities as $func) {
        $funcKey = $func['key'] ?? '';
        
        
        if ($funcKey === 'connect_app') {
            continue; // Passer à la prochaine fonctionnalité
        }

        $groupName = ($lang === 'EN' && !empty($func['group_EN'])) ? $func['group_EN'] : ($func['group'] ?? 'Autres');
        // Si le groupe n'est pas défini, on le met dans "Autres"
        $order = $func['order'] ?? 999;

        // Éviter les doublons
        if (!isset($menuStructure[$groupName])) {
            $menuStructure[$groupName] = [];
        }

        // Ajouter la fonctionnalité au groupe
        $menuStructure[$groupName][] = [
            'key' => $func['key'],
            'name' => ($lang === 'EN' && !empty($func['name_EN'])) ? $func['name_EN'] : $func['name'],
            'icon' => $func['icon'],
            'icon_type' => $func['icon_type'],
            'url' => $func['url'],
            'order' => $order,
            'group_EN' => $func['group_EN'] ?? '',
            'group'      => $func['group'] ?? '',  
        ];
    }
    // Variables supplémentaires (si nécessaires)
    $country = $_SESSION["country"] ?? '';

    // Map countries to their respective agencies
    $agencies = [
        "Burkina Faso" => ["Ouaga"],
        "Cameroun" => ["Bafoussam","Bertoua", "Douala", "Garoua", "Ngaoundere", "Yaoundé"],
        "Cote d'Ivoire" => ["Vridi - Equip"],
        "Gabon" => ["Libreville"],
        "Madagascar" => ["Ankorondrano", "Anosizato", "Diego", "Moramanga", "Tamatave"],
        "Mali" => ["Bamako"],
        "RCA" => ["Bangui"],
        "RDC" => ["Kinshasa", "Kolwezi", "Lubumbashi"],
        "Senegal" => ["Dakar"],
        "Congo" => ["Brazzaville","Pointe-Noire"],
        "Rwanda" => ["Kigali"],  
          // Add more countries and their agencies here
    ];

    // Map countries
    $countries = [
        "Burkina Faso","Cameroun", "Congo", "Cote d'Ivoire", "Gabon", "Madagascar", "Mali", "RCA", "RDC", "Senegal", "Rwanda"
        // Add more countries here
    ];        

    // Map countries to their respective subsidiary name
    $subsidiaries = [
        "CFAO MOTORS BURKINA",
        "CFAO MOBILITY CAMEROON",
        "CFAO MOTORS CONGO",
        "CFAO MOTORS COTE D'IVOIRE",
        "CFAO MOTORS GABON",
        "CFAO MOTORS MADAGASCAR",
        "CFAO MOTORS MALI",
        "CFAO MOTORS CENTRAFRIQUE",
        "CFAO MOTORS RDC",
        "CFAO MOTORS SENEGAL",
        "CFAO MOTORS RWANDA"
        // Add more subsidiaries here
    ];

    if (($_SESSION['profile'] ?? '') === 'Candidat') {
        include __DIR__ . '/candidateHeader.php';
        return;
    }
    ?>

    <!DOCTYPE html>
    <html lang="en">
    <!--begin::Head-->
    <!-- Mirrored from preview.keenthemes.com/craft/ by HTTrack Website Copier/3.x [XR&CO'2014], Fri, 14 Jul 2023 10:40:27 GMT -->
    <!-- Added by HTTrack -->
    <meta http-equiv="content-type" content="text/html; charset=UTF-8" />
    <!-- /Added by HTTrack -->
    <!-- Favicon -->
    <link href="../../public/images/logo-cfao.png" rel="icon">
    <style>
        .card {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
    </style>
    <?php medacapTopbarIncludeAssets(); ?>

    <head>
        <meta charset="utf-8" />
        <meta name="description"
            content="Craft admin dashboard live demo. Check out all the features of the admin panel. A large number of settings, additional services and widgets." />
        <meta name="keywords"
            content="Craft, bootstrap, bootstrap 5, admin themes, dark mode, free admin themes, bootstrap admin, bootstrap dashboard" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <?php
        $documentTitle = $GLOBALS['pageTitle'] ?? null;
        if ($documentTitle === null) {
            $pageKey = pathinfo($_SERVER['PHP_SELF'] ?? '', PATHINFO_FILENAME);
            $pageTitles = [
                'importCandidat' => 'Importation de candidats',
                'candidats' => "État d'avancement des campagnes",
                'resend_import_mail' => 'Rattrapage mail import',
            ];
            $documentTitle = $pageTitles[$pageKey] ?? ($displayModule ?: 'CFAO Mobility Academy');
        }
        ?>
        <title><?= htmlspecialchars((string)$documentTitle, ENT_QUOTES, 'UTF-8') ?> | CFAO Mobility Academy</title>
        <meta property="og:locale" content="en_US" />
        <meta property="og:type" content="article" />
        <meta property="og:title" content="Craft - Bootstrap 5 HTML Admin Dashboard Theme" />
        <meta property="og:url" content="https://themes.getbootstrap.com/product/craft-bootstrap-5-admin-dashboard-theme" />
        <meta property="og:site_name" content="Keenthemes | Craft" />
        <link rel="canonical" href="https://preview.keenthemes.com/craft" />
        <link rel="shortcut icon" href="../../public/images/logo-cfao.png" />
        <!--begin::Fonts(mandatory for all pages)-->
        <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700" />
        <!--end::Fonts-->
        <!--begin::Vendor Stylesheets(used for this page only)-->
        <link href="../../public/assets/plugins/custom/leaflet/leaflet.bundle.css" rel="stylesheet" type="text/css" />
        <link href="../../public/assets/plugins/custom/datatables/datatables.bundle.css" rel="stylesheet" type="text/css" />
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
        <!--end::Vendor Stylesheets-->
        <!--begin::Global Stylesheets Bundle(mandatory for all pages)-->
        <link href="../../public/assets/plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css" />
        <link href="../../public/assets/css/style.bundle.css" rel="stylesheet" type="text/css" />
        <link rel="stylesheet"
            href="https://cdn.jsdelivr.net/gh/habibmhamadi/multi-select-tag/dist/css/multi-select-tag.css">
            <link href="../../public/assets/css/ki-duotone-icons.css" rel="stylesheet" type="text/css" />
        <!--end::Global Stylesheets Bundle-->
        <!--Begin::Google Tag Manager -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
        <script src='https://cdn.jsdelivr.net/npm/fullcalendar/index.global.min.js'></script>
        <script>
        (function(w, d, s, l, i) {
            w[l] = w[l] || [];
            w[l].push({
                'gtm.start': new Date().getTime(),
                event: 'gtm.js'
            });
            var f = d.getElementsByTagName(s)[0],
                j = d.createElement(s),
                dl = l != 'dataLayer' ? '&l=' + l : '';
            j.async = true;
            j.src =
                '../../../../www.googletagmanager.com/gtm5445.html?id=' + i + dl;
            f.parentNode.insertBefore(j, f);
        })(window, document, 'script', 'dataLayer', 'GTM-5FS8GGP');
        </script>
        <!--End::Google Tag Manager -->
        <script>
        // Frame-busting to prevent site from being loaded within a frame without permission (click-jacking)
        if (window.top != window.self) {
            window.top.location.replace(window.self.location.href);
        }
        </script>
        <script src="https://code.jquery.com/jquery-3.6.3.js"
            integrity="sha256-nQLuAZGRRcILA+6dMBOvcRh5Pe310sBpanc6+QBmyVM=" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/gh/linways/table-to-excel@v1.0.4/dist/tableToExcel.js">
        </script>


    </head>
    <!--end::Head-->
    <!--begin::Body-->

    <body id="kt_body"
        class="header-fixed header-tablet-and-mobile-fixed toolbar-enabled aside-fixed aside-default-enabled">
        <!-- Spinner Start -->
        <div id="spinner"
            class="show bg-transparent position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                <span class="sr-only">Chargement...</span>
            </div>
        </div>
        <!-- Spinner End -->
        <!--begin::Theme mode setup on page load-->
        <script>
        var defaultThemeMode = "light";
        var themeMode;
        if (document.documentElement) {
            if (document.documentElement.hasAttribute("data-bs-theme-mode")) {
                themeMode = document.documentElement.getAttribute(
                    "data-bs-theme-mode");
            } else {  
                if (localStorage.getItem("data-bs-theme") !== null) {
                    themeMode = localStorage.getItem("data-bs-theme");
                } else {
                    themeMode = defaultThemeMode;
                }
            }
            if (themeMode === "system") {
                themeMode = window.matchMedia("(prefers-color-scheme: dark)")
                    .matches ? "dark" : "light";
            }
            document.documentElement.setAttribute("data-bs-theme", themeMode);
        }
        </script>
        <!--end::Theme mode setup on page load-->
        <!--Begin::Google Tag Manager (noscript) -->
        <noscript>
            <iframe src="https://www.googletagmanager.com/ns.html?id=GTM-5FS8GGP" height="0" width="0"
                style="display:none;visibility:hidden"></iframe></noscript>
        <!--End::Google Tag Manager (noscript) -->
        <!--begin::Main-->
        <!--begin::Root-->
        <div class="d-flex flex-column flex-root">
            <!--begin::Page-->
            <div class="page d-flex flex-row flex-column-fluid">    
                <!--begin::Aside-->
                <?php if (
                    $_SESSION["profile"] == "Super Admin" ||
                    $_SESSION["profile"] == "Admin" || $_SESSION["profile"] == "Ressource Humaine" || $_SESSION["profile"] == "Directeur Pièce et Service"  || $_SESSION["profile"] == "Directeur des Opérations" || $_SESSION["profile"] == "Directeur Groupe" || $_SESSION["profile"] == "Directeur Général" || $_SESSION["profile"] == "Manager" || $_SESSION["profile"] == "Technicien" ||
    $_SESSION["profile"] == "Candidat"
                ) { ?>
                    <div id="kt_aside" class="aside aside-default  aside-hoverable " data-kt-drawer="true"
                        data-kt-drawer-name="aside" data-kt-drawer-activate="{default: true, lg: false}"
                        data-kt-drawer-overlay="true" data-kt-drawer-width="{default:'200px', '300px': '250px'}"
                        data-kt-drawer-direction="start" data-kt-drawer-toggle="#kt_aside_toggle">
                        <!--begin::Brand-->
                        <div style="background:#e6e6e6;" class="aside-logo flex-column-auto px-10 pt-9 pb-5" id="kt_aside_logo">
                            <!--begin::Action-->
                            <a href="../portal.php" class="btn btn-sm btn-light align-self-center">
                                <span class="portal-text"><?php echo $portal; ?></span>
                                <i class="ki-duotone ki-element-11 portal-icon fs-2 d-none"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>
                            </a>
                            <!--end::Action-->
                            <!--begin::Logo-->
                            <a href="./dashboard">
                                <img alt="Logo" src="../../public/images/logo.png" class="h-50px logo-default" style="margin-right: 50px;" />
                                <img alt="Logo" src="../../public/images/logo.png" class="h-50px logo-minimize" />
                            </a>
                            <!--end::Logo-->
                        </div>
                        <!--end::Brand-->
                        <!--begin::Aside menu-->
                        <div style="background:#e6e6e6;" class="aside-menu flex-column-fluid ps-3 pe-1">
                            <!--begin::Aside Menu-->
                            <!--begin:Menu item-->
                            <div style="margin-top: -15px">
                                <!-- Display profile space name -->
                                <!-- <div class="menu-content text-center">
                                    <span class="fw-bolder text-black text-uppercase fs-4"><?php echo 'Module Mesure' ?></span>
                                </div> -->
                                <?php if ($_SESSION["profile"] == "Super Admin") { ?>
                                    <div class="menu-content text-center"><span
                                            class="fw-bolder text-black text-uppercase fs-4"><?php echo $super_admin_space ?></span>
                                    </div>
                                <?php } elseif ($_SESSION["profile"] == "Admin") { ?>
                                    <div class="menu-content text-center"><span
                                            class="fw-bolder text-black text-uppercase fs-4"><?php echo $admin_space ?></span>
                                    </div>
                                <?php } elseif ($_SESSION["profile"] == "Ressource Humaine") { ?>
                                    <div class="menu-content text-center"><span
                                            class="fw-bolder text-black text-uppercase fs-4"><?php echo $rh_space ?></span>
                                    </div>
                                <?php } elseif ($_SESSION["profile"] == "Directeur Pièce et Service") { ?>
                                    <div class="menu-content text-center"><span
                                            class="fw-bolder text-black text-uppercase fs-5"><?php echo $dps_space ?></span>
                                    </div>
                                <?php } elseif ($_SESSION["profile"] == "Directeur des Opérations") { ?>
                                    <div class="menu-content text-center"><span
                                            class="fw-bolder text-black text-uppercase fs-5"><?php echo $dop_space ?></span>
                                    </div>
                                <?php } elseif ($_SESSION["profile"] == "Directeur Général") { ?>
                                    <div class="menu-content text-center"><span
                                            class="fw-bolder text-black text-uppercase fs-4"><?php echo $directeur_filiale ?></span>
                                    </div>
                                <?php } elseif ($_SESSION["profile"] == "Directeur Groupe") { ?>
                                    <div class="menu-content text-center"><span
                                            class="fw-bolder text-black text-uppercase fs-4"><?php echo $dir_grp_space ?></span>
                                    </div>
                                <?php } elseif ($_SESSION["profile"] == "Manager" && $_SESSION["test"] == false) { ?>
                                    <div class="menu-content text-center"><span
                                            class="fw-bolder text-black text-uppercase fs-4"><?php echo $manager_space ?></span>
                                    </div>
                                <?php } elseif ($_SESSION["profile"] == "Manager" && $_SESSION["test"] == true) { ?>
                                    <div class="menu-content text-center"><span
                                            class="fw-bolder text-black text-uppercase fs-4"><?php echo $manager_space.' - '.$technicien ?></span>
                                    </div>
                                <?php } elseif ($_SESSION["profile"] == "Technicien") { ?>
                                    <div class="menu-content text-center"><span
                                            class="fw-bolder text-black text-uppercase fs-4"><?php echo $tech_space ?></span>
                                    </div>
                                <?php } elseif ($_SESSION["profile"] == "Candidat") { ?>
                                <div class="menu-content text-center"><span
                                        class="fw-bolder text-black text-uppercase fs-4"><?php echo $Candidate_space ?></span>
                                </div>
                                <?php } ?>
                                <?php if ($_SESSION["profile"] != "Super Admin") { ?>
                                <div class="menu-content text-center"><span
                                        class="fw-bolder text-black text-uppercase fs-4"><?php echo $_SESSION["country"] ?></span>
                                </div>
                                <?php } ?>
                            </div> <br>
                            <!--end:Menu item-->
                            <!--begin::Menu-->
                            <div class="menu menu-sub-indention menu-column menu-rounded menu-title-gray-600 menu-icon-gray-400 menu-active-bg menu-state-primary menu-arrow-gray-500 fw-semibold fs-6 my-5 mt-lg-2 mb-lg-0"
                                id="kt_aside_menu" data-kt-menu="true">
                                <div class="hover-scroll-y mx-4" id="kt_aside_menu_wrapper" data-kt-scroll="true"
                                    data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-height="auto"
                                    data-kt-scroll-wrappers="#kt_aside_menu" data-kt-scroll-offset="20px"
                                    data-kt-scroll-dependencies="#kt_aside_logo, #kt_aside_footer">
                                    <!--begin:Menu item-->
                                <?php
                                    $currentPageMeasure = pathinfo(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), PATHINFO_FILENAME);
                                    $isDashboardMeasure = ($currentPageMeasure === $dashboardBase);
                                    $usersCollection = $academy->users;
                                    $sessionUserId = new MongoDB\BSON\ObjectId($_SESSION['id']);
                                    $hasCollaborators = menuCurrentUserHasCollaborators($usersCollection, $sessionUserId);
                                    $isManagerTechnicien = ($_SESSION['profile'] === 'Manager' && !empty($_SESSION['test']));
                                ?>
                                <div class="menu-item show">
                                        <!-- begin:Menu link -->
                                        <div class="menu-item <?php echo $isDashboardMeasure ? 'here' : ''; ?>">

                                                <!-- Pour Directeur Groupe : afficher "Participation aux tests" -->
                                                <a class="menu-link <?php echo $isDashboardMeasure ? 'active' : ''; ?>" href="<?= htmlspecialchars($DASHBOARD_ROUTE, ENT_QUOTES, 'UTF-8') ?>">
                                                    <span class="menu-icon">
                                                        <i class="ki-duotone ki-element-11 fs-2">
                                                            <span class="path1"></span>
                                                            <span class="path2"></span>
                                                            <span class="path3"></span>
                                                            <span class="path4"></span>
                                                        </i>
                                                    </span>
                                                    <span class="menu-title"><?= $participation_aux_tests ?></span>
                                                </a>
                                            
                                        </div>
                                        <!-- Dynamic Menu Items -->
                                        <?php
                                            /* ——— Directeur Général ——— */
                                            if ($_SESSION['profile'] === 'Directeur Général') {
                                                foreach ($menuStructure as $groupName => $items) {
                                                    echo '<div class="menu-content">';
                                                    echo '<span class="fw-bold text-black text-uppercase fs-70">'
                                                        . htmlspecialchars(groupLabel($items, $lang))
                                                        . '</span></div>';
                                                    foreach ($items as $item) {
                                                        echoItem($item, $lang);
                                                    }
                                                    echo '<div class="menu-item"><div class="menu-content"></div></div>';
                                                }

                                            /* ——— Directeur Groupe ——— */
                                            } elseif ($_SESSION['profile'] === 'Directeur Groupe') {
                                                foreach ($menuStructure as $groupName => $items) {
                                                    echo '<div class="menu-content">';
                                                    echo '<span class="fw-bold text-black text-uppercase fs-70">'
                                                        . htmlspecialchars(groupLabel($items, $lang))
                                                        . '</span></div>';

                                                    foreach ($items as $item) {
                                                        echoItem($item, $lang);
                                                    }

                                                    echo '<div class="menu-item"><div class="menu-content"></div></div>';
                                                }

                                            }

                                        /* ——— Manager ——— */
                                        elseif ($_SESSION['profile'] === 'Manager') {
                                            /* Manager-technicien sans equipe : menu epure (comme Technicien) */
                                            if ($isManagerTechnicien && !$hasCollaborators) {
                                                foreach ($menuStructure as $groupName => $items) {
                                                    if ($groupName === '') {
                                                        foreach ($items as $item) {
                                                            if (!menuIsSidebarExcludedKey($item['key'] ?? '')) {
                                                                echoItem($item, $lang, '?id=' . $_SESSION['id']);
                                                            }
                                                        }
                                                    } elseif (groupLabel($items, $lang) === $mesure_des_competences) {
                                                        echoMesureCompetencesGroup($items, $lang, '');
                                                    }
                                                }
                                            } else {
                                            foreach ($menuStructure as $groupName => $items) {

                                                /* === MON ÉQUIPE ============================= */
                                                if ($groupName === $mon_equipe) {
                                                    if (!$hasCollaborators) {
                                                        continue;
                                                    }
                                                    /* titre de groupe */
                                                    echo '<div class="menu-content">';
                                                    echo '<span class="fw-bold text-black text-uppercase fs-70">'
                                                        . htmlspecialchars(groupLabel($items, $lang))
                                                        . '</span></div>';

                                                    /* items */
                                                    foreach ($items as $item) {
                                                        echoItem($item, $lang);
                                                    }
                                                    /* séparateur */
                                                    echo '<div class="menu-item"><div class="menu-content"></div></div>';
                                                }

                                                /* === ÉVALUER VOS COLLABORATEURS ============== */
                                                if ($hasCollaborators && conditionPourAfficherEvalCollab()) {
                                                    if ($groupName === $evaluer_vos_collaborateurs) {

                                                        echo '<div class="menu-content"><span class="fw-bold text-black text-uppercase fs-70">'
                                                        . htmlspecialchars(groupLabel($items, $lang))
                                                        . '</span></div>';

                                                        foreach ($items as $item) {
                                                            echoItem($item, $lang);
                                                        }

                                                        echo '<div class="menu-item"><div class="menu-content"></div></div>';
                                                    }
                                                }

                                                /* === BILAN DES TESTS / MESURE / AUTRES ====== */
                                                $firstItem = reset($items);
                                                $groupFR = $firstItem['group'] ?? '';
                                                $groupEN = $firstItem['group_EN'] ?? '';

                                                if (!$hasCollaborators && menuIsBilanTestsGroup($items)) {
                                                    continue;
                                                }

                                                if (groupLabel($items, $lang) === $mesure_des_competences) {
                                                    echoMesureCompetencesGroup($items, $lang, '');
                                                    continue;
                                                }

                                                $isKnownGroup = ($groupName === $mon_equipe)
                                                    || ($groupName === $evaluer_vos_collaborateurs)
                                                    || menuIsBilanTestsGroup($items);

                                                if (menuIsBilanTestsGroup($items) || !$isKnownGroup) {

                                                    echo '<div class="menu-content"><span class="fw-bold text-black text-uppercase fs-70">'
                                                        . htmlspecialchars(groupLabel($items, $lang))
                                                        . '</span></div>';

                                                    foreach ($items as $item) {
                                                        if (menuIsSidebarExcludedKey($item['key'] ?? '')) {
                                                            continue;
                                                        }
                                                        echoItem($item, $lang);
                                                    }

                                                    echo '<div class="menu-item"><div class="menu-content"></div></div>';
                                                }
                                            }
                                            }
                                        } elseif ($_SESSION['profile'] === 'Admin') {
                                            foreach ($menuStructure as $groupName => $items) {
                                                /* Afficher le titre du groupe */
                                                echo '<div class="menu-content"><span class="fw-bold text-black text-uppercase fs-70">'
                                                    . htmlspecialchars(groupLabel($items, $lang))
                                                    . '</span></div>';

                                                foreach ($items as $item) {
                                                    echoItem($item, $lang);
                                                }

                                                echo '<div class="menu-item"><div class="menu-content"></div></div>';
                                            }

                                        /* ——— Technicien / autres profils ——— */
                                        } elseif ($_SESSION['profile'] === 'Technicien') {
                                            foreach ($menuStructure as $groupName => $items) {
                                                /* groupe vide (= raccourcis perso) */
                                                if ($groupName === '') {
                                                    foreach ($items as $item) {
                                                        echoItem($item, $lang, '?id='.$_SESSION['id']);
                                                    }
                                                }
                                                /* groupe Mesure des compétences */
                                                elseif (groupLabel($items, $lang) === $mesure_des_competences) {
                                                    echoMesureCompetencesGroup($items, $lang, '');
                                                }
                                            }

                                        /* ——— Tous les autres profils (RH, Directeurs...) ——— */
                                        } else {
                                            foreach ($menuStructure as $groupName => $items) {
                                                echo '<div class="menu-content"><span class="fw-bold text-black text-uppercase fs-70">'
                                                    . htmlspecialchars(groupLabel($items, $lang))
                                                    . '</span></div>';
                                                foreach ($items as $item) {
                                                    echoItem($item, $lang, '');
                                                }
                                                echo '<div class="menu-item"><div class="menu-content"></div></div>';
                                            }
                                        }
                                        ?>


                                        <?php if ($_SESSION['profile'] === 'Super Admin'): ?>
                                        <!-- ===== OUTILS SUPER ADMIN (hardcodés) ===== -->
                                        <div class="menu-content">
                                            <span class="fw-bold text-black text-uppercase fs-70">Outils Admin</span>
                                        </div>
                                        <div class="menu-item">
                                            <a class="menu-link <?php echo (strpos($_SERVER['REQUEST_URI'], 'fix_username') !== false) ? 'active' : ''; ?>"
                                               href="/medacap/fix_username.php">
                                                <span class="menu-icon">
                                                    <i class="ki-duotone ki-pencil fs-2">
                                                        <span class="path1"></span><span class="path2"></span>
                                                    </i>
                                                </span>
                                                <span class="menu-title">Correction username</span>
                                            </a>
                                        </div>
                                        <div class="menu-item">
                                            <a class="menu-link <?php echo (strpos($_SERVER['REQUEST_URI'], 'activation') !== false) ? 'active' : ''; ?>"
                                               href="/medacap/views/explore/createUser.php">
                                                <span class="menu-icon">
                                                    <i class="ki-duotone ki-user-tick fs-2">
                                                        <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                                                    </i>
                                                </span>
                                                <span class="menu-title">Créer un utilisateur</span>
                                            </a>
                                        </div>
                                        <div class="menu-item">
                                            <a class="menu-link <?php echo (strpos($_SERVER['REQUEST_URI'], 'create_fixed_paper') !== false) ? 'active' : ''; ?>"
                                               href="/medacap/views/measure/create_fixed_paper.php">
                                                <span class="menu-icon">
                                                    <i class="ki-duotone ki-notepad-edit fs-2">
                                                        <span class="path1"></span><span class="path2"></span>
                                                    </i>
                                                </span>
                                                <span class="menu-title">Générateur d'épreuve figée</span>
                                            </a>
                                        </div>
                                        <div class="menu-item">
                                            <a class="menu-link <?php echo (strpos($_SERVER['REQUEST_URI'], 'loadtest') !== false) ? 'active' : ''; ?>"
                                               href="/medacap/views/measure/loadtest.php">
                                                <span class="menu-icon">
                                                    <i class="ki-duotone ki-rocket fs-2">
                                                        <span class="path1"></span><span class="path2"></span>
                                                    </i>
                                                </span>
                                                <span class="menu-title">Test de charge candidats</span>
                                            </a>
                                        </div>
                                        <div class="menu-item"><div class="menu-content"></div></div>
                                        <?php endif; ?>

                                        <!--begin:Menu item-->
                                        <div class="menu-item">
                                            <!--begin:Menu content-->
                                            <div class="menu-content">
                                                <div class="separator mx-1 my-4"></div>
                                            </div>
                                            <!--end:Menu content-->
                                        </div>
                                        <!--end:Menu item-->
                                    </div>
                                    <!--end:Menu item-->
                                </div>
                            </div>
                            <!--end::Menu-->
                        </div>
                        <!--end::Aside menu-->
                        <!--begin::Footer-->
                        <div class="aside-footer flex-column-auto pb-5 d-none" id="kt_aside_footer">
                            <a href="./dashboard" class="btn btn-light-primary w-100">
                                Button
                            </a>
                        </div>
                        <!--end::Footer-->
                    </div>
                <?php } ?>
                <!--end::Aside-->
                <!--begin::Wrapper-->
                <div style="background:#e6e6e6;" class="wrapper d-flex flex-column flex-row-fluid" id="kt_wrapper">
                    <!--begin::Header-->
                    <?php
                    medacapTopbarRenderHeader(array(
                        'displayModule' => $displayModule,
                        'lang' => $lang,
                        'mobileLogoHref' => $DASHBOARD_FILE,
                        'dashboardHref' => $DASHBOARD_FILE,
                        'profileHref' => './profile.php?id=' . urlencode((string) ($_SESSION['id'] ?? '')),
                        'portalHref' => '../portal.php',
                        'logoutHref' => '../logout.php',
                        'logoutRedirect' => './logout.php',
                    ));
                    ?>
                    <!--end::Header-->
