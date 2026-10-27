<?php
// Connexion à MongoDB — même résolution que dashboard.php (10.68.0.7 en dev WAMP).
require_once __DIR__ . "/../../../vendor/autoload.php";
require_once __DIR__ . '/../core/portal-mongo.php';
$academy = definePortalMongoDatabase();
$profilesCollection = $academy->profiles;
$functionalitiesCollection = $academy->functionalities;

// Inclure le fichier de langue (FR/EN)
require_once __DIR__ . '/../../partials/medacap_topbar.php';
$lang = medacapTopbarSyncLang(__DIR__ . '/../..');

// Récupérer le profil de l'utilisateur
$currentProfileName = $_SESSION['profile'] ?? '';

// if ($currentProfileName == 'Manager' && $_SESSION['test'] == true) {
//     $profile = $profilesCollection->findOne(['name' => 'Manager - Technicien']);
// } else {
//     $profile = $profilesCollection->findOne(['name'=> $currentProfileName]);
// }

$profile = $profilesCollection->findOne(['name'=> $currentProfileName]);
$assignedFunctionalities = $profile['functionalities'] ?? [];

if ($assignedFunctionalities instanceof MongoDB\Model\BSONArray) {
    // Convertir en tableau PHP standard
    $assignedFunctionalities = $assignedFunctionalities->getArrayCopy();
}

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
    'acquire' => '4 - Acquerir des compétences techniques',
    'define'  => '3 - Définir les plans individuels et collectifs de formation',
    'explore' => '2 - Exploiter Les données de la Mesure des Compétences des techniciens',
    'measure' => '1 - Mesurer les compétences des techniciens',
];


// Obtenir le module actuel
$currentModule = getCurrentModule();
$displayModule = isset($moduleLabels[$currentModule])
? $moduleLabels[$currentModule]
: '';

// Personnalisation pour le profil Technicien
if ($currentProfileName === 'Technicien' && $currentModule === 'define') {
    $currentPage = basename($_SERVER['PHP_SELF']);
    if ($currentPage === 'dashboard2026.php') {
        $displayModule = '3 - Mon Planning Individuel de Formation 2026 (PIF 2026)';
    } else {
        $displayModule = '3 - Mon Plan Individuel de Formation Pluri-annuel (PIF)';
    }
}

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
        'sort' => ['group_order' => 1, 'order' => 1]
    ]
);

$allFunctionalities = iterator_to_array($functionalitiesCursor);



$menuStructure = [];

foreach ($allFunctionalities as $func) {
    $funcKey = $func['key'] ?? '';
    
    
    if ($funcKey === 'connect_app') {
        continue; // Passer à la prochaine fonctionnalité
    }

    $groupName = $func['group'] ?? 'Autres';
    $order = $func['order'] ?? 999;

    // Éviter les doublons
    if (!isset($menuStructure[$groupName])) {
        $menuStructure[$groupName] = [];
    }

    // Ajouter la fonctionnalité au groupe
    $menuStructure[$groupName][] = [
        'key' => $func['key'],
        'name' => $func['name'],
        'icon' => $func['icon'],
        'icon_type' => $func['icon_type'],
        'url' => $func['url'],
        'order' => $order
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
    // Add more countries and their agencies here
];

// Map countries
$countries = [
    "Burkina Faso","Cameroun", "Congo", "Cote d'Ivoire", "Gabon", "Madagascar", "Mali", "RCA", "RDC", "Senegal"
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
        $pageKey = $GLOBALS['currentPage'] ?? pathinfo($_SERVER['PHP_SELF'] ?? '', PATHINFO_FILENAME);
        $pageTitles = [
            'dashboard' => $tableau ?? 'Tableau de bord',
            'collaboratorDashboard' => 'Plans de formation de mes collaborateurs',
            'selectTraining' => 'Validation des Plans de Formations',
            'recommandationTraining' => $train_collab ?? 'Formations recommandées',
            'planning' => 'Planning des formations',
            'trainings' => $list_training ?? 'Liste des formations',
            'results' => $list_result ?? 'Résultats',
        ];
        $documentTitle = $pageTitles[$pageKey] ?? ($displayModule ?: 'CFAO Mobility Academy');
    }
    ?>
    <title><?= htmlspecialchars($documentTitle) ?> | CFAO Mobility Academy</title>
    <meta property="og:locale" content="en_US" />
    <meta property="og:type" content="article" />
    <meta property="og:title" content="Craft - Bootstrap 5 HTML Admin Dashboard Theme" />
    <meta property="og:url" content="https://themes.getbootstrap.com/product/craft-bootstrap-5-admin-dashboard-theme" />
    <meta property="og:site_name" content="Keenthemes | Craft" />
    <link rel="canonical" href="https://preview.keenthemes.com/craft" />
    <link rel="shortcut icon" href="/images/logo-cfao.png" />
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

    <?php medacapTopbarIncludeAssets(); ?>

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
                $_SESSION["profile"] == "Admin" || $_SESSION["profile"] == "Ressource Humaine" || $_SESSION["profile"] == "Directeur Pièce et Service"  || $_SESSION["profile"] == "Directeur des Opérations" || $_SESSION["profile"] == "Directeur Groupe" || $_SESSION["profile"] == "Directeur Général" || $_SESSION["profile"] == "Manager" || $_SESSION["profile"] == "Technicien"
            ) { ?>
                <div id="kt_aside" class="aside aside-default  aside-hoverable " data-kt-drawer="true"
                    data-kt-drawer-name="aside" data-kt-drawer-activate="{default: true, lg: false}"
                    data-kt-drawer-overlay="true" data-kt-drawer-width="{default:'200px', '300px': '250px'}"
                    data-kt-drawer-direction="start" data-kt-drawer-toggle="#kt_aside_toggle">
                    <!--begin::Brand-->
                    <div style="background:#e6e6e6;" class="aside-logo flex-column-auto px-10 pt-9 pb-5" id="kt_aside_logo">
                        <!--begin::Action-->
                        <a href="../portal.php"
                        class="btn btn-sm btn-light align-self-center"><?php echo 'Portail MEDACAP' ?></a>
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
                                <span class="fw-bolder text-black text-uppercase fs-4"><?php echo 'Module Define' ?></span>
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
                                        class="fw-bolder text-black text-uppercase fs-5"><?php echo isset($dps_space) ? $dps_space : "DPS"; ?></span>
                                </div>
                            <?php } elseif ($_SESSION["profile"] == "Directeur des Opérations") { ?>
                                <div class="menu-content text-center"><span
                                        class="fw-bolder text-black text-uppercase fs-5"><?php echo $dop_space ?></span>
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
                               <div data-kt-menu-trigger="click" class="menu-item here show menu-accordion">
                                    <!--begin:Menu link-->
                                    <?php
                                        $currentPageFile = pathinfo(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), PATHINFO_FILENAME);
                                        $isDashboardPage = ($currentPageFile === 'dashboard');
                                        $dashLinkClass = 'menu-link' . ($isDashboardPage ? ' active' : '');
                                    ?>
                                    <div class="menu-item <?php echo $isDashboardPage ? 'here' : ''; ?>">
                                        <?php
                                            if ($_SESSION["profile"] == 'Technicien') {
                                                if (isset($_GET['brand']) && isset($_GET['level'])) {
                                                    echo '<a class="' . $dashLinkClass . '" href="./dashboard?brand=' . $_GET['brand'] . '&level=' . $_GET['level'] . '"><span class="menu-icon">';
                                                } else {
                                                    echo '<a class="' . $dashLinkClass . '" href="./dashboard"><span class="menu-icon">';
                                                }
                                            } else if ($_SESSION["profile"] == 'Manager') {
                                                if (isset($_GET['brand'], $_GET['level'], $_GET['technicianId'])) {
                                                    echo '<a class="' . $dashLinkClass . '" href="./dashboard?brand=' . $_GET['brand'] . '&technicianId=' . $_GET['technicianId'] . '&level=' . $_GET['level'] . '"><span class="menu-icon">';
                                                } else {
                                                    echo '<a class="' . $dashLinkClass . '" href="./dashboard"><span class="menu-icon">';
                                                }
                                            } else if (in_array($_SESSION['profile'], ["Admin", "Directeur Pièce et Service", "Directeur des Opérations", "Directeur Général"])) {
                                                if (isset($_SESSION['subsidiary'], $_GET['agency'], $_GET['brand'], $_GET['level'], $_GET['managerId'], $_GET['technicianId'])) {
                                                    echo '<a class="' . $dashLinkClass . '" href="./dashboard?filiale=' . $_SESSION['subsidiary'] . '&agence=' . $_GET['agency'] . '&managerId=' . $_GET['managerId'] . '&brand=' . $_GET['brand'] . '&technicianId=' . $_GET['technicianId'] . '&level=' . $_GET['level'] . '"><span class="menu-icon">';
                                                } else {
                                                    echo '<a class="' . $dashLinkClass . '" href="' . ($isDashboardPage ? '#' : './dashboard') . '"><span class="menu-icon">';
                                                }
                                            } else {
                                                if (isset($_GET['filiale'], $_GET['agency'], $_GET['brand'], $_GET['level'], $_GET['managerId'], $_GET['technicianId'])) {
                                                    echo '<a class="' . $dashLinkClass . '" href="./dashboard?filiale=' . $_GET['filiale'] . '&agence=' . $_GET['agency'] . '&managerId=' . $_GET['managerId'] . '&brand=' . $_GET['brand'] . '&technicianId=' . $_GET['technicianId'] . '&level=' . $_GET['level'] . '"><span class="menu-icon">';
                                                } else {
                                                    echo '<a class="' . $dashLinkClass . '" href="./dashboard"><span class="menu-icon">';
                                                }
                                            }
                                         ?>
                                                <i class="ki-duotone ki-element-11 fs-2">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                    <span class="path3"></span>
                                                    <span class="path4"></span>
                                                </i></span><span class="menu-title"><?php echo $tableau ?></span>
                                            </a>
                                    </div>
                                    <!-- Dynamic Menu Items -->
                                    <?php
                                    foreach ($menuStructure as $groupName => $items) {
                                        // Afficher le nom du groupe
                                        echo '<div class="menu-content">';
                                        echo '<span class="fw-bold text-black text-uppercase fs-70">' . htmlspecialchars($groupName) . '</span>';
                                        echo '</div>';

                                        // Afficher les fonctionnalités du groupe
                                        foreach ($items as $item) {
                                            // Vérifiez la key
                                            $key = $item['key'] ?? '';
                                            // Vérifiez le type d'icône
                                            $iconType = $item['icon_type'] ?? 'font_awesome';

                                            echo '<div class="menu-item">';
                                            if ($key === 'list_training_tech' || $key === 'state_training') {
                                                if ($_SESSION["profile"] == 'Directeur Pièce et Service' || $_SESSION["profile"]  == 'Directeur des Opérations' || $_SESSION["profile"] == 'Admin' || $_SESSION["profile"] == 'Ressource Humaine') {
                                                    echo '<a class="menu-link" href="' . htmlspecialchars($item['url']) . '?country=' . ($_GET['country'] ??  $_SESSION['country']) . '&agency=' . ($_GET['agency'] ?? 'all') . '&manager=' . ($_GET['manager'] ?? 'all') . '">';
                                                } else {
                                                    echo '<a class="menu-link" href="' . htmlspecialchars($item['url']) . '?country=' . ($_GET['country'] ?? 'all') . '&agency=' . ($_GET['agency'] ?? 'all') . '&manager=' . ($_GET['manager'] ?? 'all') . '">';
                                                }
                                            } else if ($key == 'list_training_collab' || $key === 'state_training_collab') {
                                                echo '<a class="menu-link" href="' . htmlspecialchars($item['url']) . '?country=' . ($_GET['country'] ?? $_SESSION['country']) . '&agency=' . ($_GET['agency'] ?? $_SESSION['agency']) . '&manager=' . ($_GET['manager'] ?? $_SESSION['id']) . '">';
                                            } else if ($key == 'personal_training') {
                                                echo '<a class="menu-link" href="' . htmlspecialchars($item['url']) . '?brand=' . ($_GET['brand'] ?? 'all') . '&level=' . ($_GET['level'] ?? 'all') . '">';
                                            } else if ($key == 'apply_to_training') {
                                                echo '<a class="menu-link" href="' . htmlspecialchars($item['url']) . '?user=' . ($_GET['user'] ?? 'all') . '&training=' . ($_GET['training'] ?? 'all') . '">';
                                            } else if ($key == 'select_training') {
                                                echo '<a class="menu-link" href="' . htmlspecialchars($item['url']) . '?user=' . ($_GET['user'] ?? 'all') . '&training=' . ($_GET['training'] ?? 'all') . '&brand=' . ($_GET['brand'] ?? 'all') . '&level=' . ($_GET['level'] ?? 'all') . '">';
                                            } else if ($key === 'collaborator_dashboard') {
                                                echo '<a class="menu-link" href="' . htmlspecialchars($item['url']) . '?agency=' . urlencode($_GET['agency'] ?? 'all') . '&technicianId=' . urlencode($_GET['technicianId'] ?? 'all') . '">';
                                            } else {
                                                echo '<a class="menu-link" href="' . htmlspecialchars($item['url']) . '">';
                                            }
                                            echo '<span class="menu-icon">';
                                            if ($iconType === 'ki_duotone') {
                                                echo '<i class="' . htmlspecialchars($item['icon']) . ' fs-2">';
                                                echo '<span class="path1"></span>';
                                                echo '<span class="path2"></span>';
                                                echo '</i>';
                                            } else {
                                                echo '<i class="' . htmlspecialchars($item['icon']) . ' fs-2"></i>';
                                            }
                                            echo '</span>';
                                            echo '<span class="menu-title">' . htmlspecialchars($item['name']) . '</span>';
                                            echo '</a>';
                                            echo '</div>';
                                        }

                                        // Séparateur entre les groupes
                                        echo '<div class="menu-item">';
                                        echo '<div class="menu-content">';
                                        // echo '<div class="separator mx-1 my-4"></div>';
                                        echo '</div>';
                                        echo '</div>';
                                    }
                                    ?>

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
                    'mobileLogoHref' => './dashboard.php',
                    'dashboardHref' => './dashboard.php',
                    'profileHref' => './profile.php?id=' . urlencode((string) ($_SESSION['id'] ?? '')),
                    'portalHref' => '../portal.php',
                    'logoutHref' => '../logout.php',
                    'logoutRedirect' => './logout.php',
                ));
                ?>
                <!--end::Header-->
                                                        
