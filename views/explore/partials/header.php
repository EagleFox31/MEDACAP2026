<?php
// Connexion à MongoDB
require_once "../../vendor/autoload.php";
$client = new MongoDB\Client("mongodb://localhost:27017");
$academy = $client->academy;
$profilesCollection = $academy->profiles;
$functionalitiesCollection = $academy->functionalities;

// Inclure le fichier de langue (si nécessaire)
include_once "../language.php";

// Récupérer le profil de l'utilisateur
$currentProfileName = $_SESSION['profile'] ?? '';

// Route dashboard selon profil (stateAgency pour les directeurs filiale)
$dashRoute = (($currentProfileName === 'Directeur Pièce et Service') || ($currentProfileName === 'Directeur des Opérations'))
    ? './stateAgency'
    : './dashboard';

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
    "Congo" => ["Pointe - Noire"],
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

// Première agence avec des techniciens (pour le lien menu par défaut)
$defaultMenuAgency = isset($agencies[$country]) ? $agencies[$country][0] : '';
if ($country && isset($agencies[$country]) && isset($_SESSION['subsidiary'])) {
    try {
        $matchQuery = [
            'subsidiary' => $_SESSION['subsidiary'],
            'active' => true,
            '$or' => [['profile' => 'Technicien'], ['profile' => 'Manager', 'test' => true]]
        ];
        if (isset($_SESSION['department']) && $_SESSION['department'] !== 'Equipment & Motors') {
            $matchQuery['department'] = $_SESSION['department'];
        }
        $menuAgencyCounts = [];
        foreach ($academy->users->aggregate([
            ['$match' => $matchQuery],
            ['$group' => ['_id' => '$agency', 'count' => ['$sum' => 1]]]
        ])->toArray() as $doc) {
            $menuAgencyCounts[(string)$doc['_id']] = (int)$doc['count'];
        }
        foreach ($agencies[$country] as $agencyOption) {
            if (($menuAgencyCounts[$agencyOption] ?? 0) > 0) {
                $defaultMenuAgency = $agencyOption;
                break;
            }
        }
    } catch (Exception $e) {
        // Garder l'agence par défaut en cas d'erreur
    }
}

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
                                    
?>
<style>
.title-banner {
    /* Fond blanc translucide */
    background: rgba(255, 255, 255, 0.85);

    /* Effet de flou en arrière-plan */
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(8px);

    /* Léger contour + coins arrondis */
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 12px;

    /* Ombre discrète */
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);

    /* Couleur du texte */
    color: #333;
    /* Ajuste en #fff si tu préfères un texte clair */
    justify-content: center;
    /* Centre verticalement */
    align-items: center;
    /* Centre horizontalement */
    text-align: center;
    /* Centre le texte */
}
</style>
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
    
    .title-banner {
        /* Effet de flou en arrière-plan */
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);

        /* Couleur du texte */
        color: #333;
        /* Ajuste en #fff si tu préfères un texte clair */
    }
    
    /* Hide header spinner when page-specific loader is active */
    body.loading-active #spinner {
        display: none !important;
    }
</style>

<head>
    <meta charset="utf-8" />
    <meta name="description"
        content="Craft admin dashboard live demo. Check out all the features of the admin panel. A large number of settings, additional services and widgets." />
    <meta name="keywords"
        content="Craft, bootstrap, bootstrap 5, admin themes, dark mode, free admin themes, bootstrap admin, bootstrap dashboard" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
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
                        <a href="<?= $dashRoute ?>">
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
                                <span class="fw-bolder text-black text-uppercase fs-4"><?php echo 'Module Explore' ?></span>
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
                                        class="fw-bolder text-black text-uppercase fs-4"><?php echo 'Directeur Général' ?></span>
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
                                    <?php if ($currentProfileName !== 'Directeur Pièce et Service' && $currentProfileName !== 'Directeur des Opérations'): ?>
                                    <div class="menu-item">
                                        <a class="menu-link" href="<?= $dashRoute ?>"><span
                                                class="menu-icon">
                                                <i class="ki-duotone ki-element-11 fs-2">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                    <span class="path3"></span>
                                                    <span class="path4"></span>
                                                </i></span><span class="menu-title"><?php echo $tableau ?></span>
                                            </a>
                                    </div>
                                    <?php endif; ?>
                                    <!-- Dynamic Menu Items -->
                                    <?php
                                    // Récupération de l'URL actuelle
                                    $currentUrl = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
                                    $isResultsPage = strpos($currentUrl, 'results.php') !== false;
                                    $isResultPage = strpos($currentUrl, 'result.php') !== false;
                                    $isDetailPage = strpos($currentUrl, 'detail.php') !== false;
                                    $isBrandResultPage = strpos($currentUrl, 'brandResult.php') !== false;
                                    $isSystemPage = strpos($currentUrl, 'system.php') !== false;
                                    $isStateAgencyPage = strpos($currentUrl, 'stateAgency') !== false;

                                    // Paramètres dynamiques
                                    $list_result = "Liste des résultats";
                                    $result_detaillé = "Résultats détaillés";
                                    $result_brand = "Résultats par marque";
                                    
                                    foreach ($menuStructure as $groupName => $items) {
                                        // Afficher le nom du groupe
                                        echo '<div class="menu-content">';
                                        echo '<span class="fw-bold text-black text-uppercase fs-70">' . htmlspecialchars($groupName) . '</span>';
                                        echo '</div>';

                                        // Affichage des fonctionnalités du groupe
                                        foreach ($items as $item) {
                                            // Vérifiez la key
                                            $key = $item['key'] ?? '';
                                            // Vérifiez le type d'icône
                                            $iconType = $item['icon_type'] ?? 'font_awesome';
                                            
                                            $itemActive = ($key === 'state_agency' && $isStateAgencyPage);
                                            echo '<div class="menu-item' . ($itemActive ? ' here' : '') . '">';
                                            if ($key == 'state_agency') {
                                                $agencyParam = isset($_GET['agency']) ? $_GET['agency'] : 'all';
                                                echo '<a class="menu-link' . ($itemActive ? ' active' : '') . '" href="' . htmlspecialchars($item['url']) . '?agency=' . $agencyParam . '">';
                                            } else if ($key == 'state_subsidiary') {
                                                echo '<a class="menu-link" href="' . htmlspecialchars($item['url']) . '?country=' . ($_GET['country'] ?? 'all') . '">';
                                            } else if ($key == 'result_by_subsidiary') {
                                                echo '<a class="menu-link" href="' . htmlspecialchars($item['url']) . '?level=' . ($_GET['level'] ?? 'Junior') . '">';
                                            } else if ($key == 'result_tech_subsidiary') {
                                                echo '<a class="menu-link" href="' . htmlspecialchars($item['url']) . '?level=' . ($_GET['level'] ?? 'Junior') . '&subsidiary=' . $_SESSION["subsidiary"] . '">';
                                            } else if ($key == 'taux_cover') {
                                                if ($profile == 'Directeur Pièce et Service' || $profile == 'Directeur des Opérations') {
                                                    if (isset($_GET['agency'])) {
                                                        echo '<a class="menu-link" href="' . htmlspecialchars($item['url']) . '?level=' . ($_GET['level'] ?? 'Junior') .'&agency=' . $_GET['agency'] . '">';
                                                    } else {
                                                        echo '<a class="menu-link" href="' . htmlspecialchars($item['url']) . '?level=' . ($_GET['level'] ?? 'Junior') .'">';
                                                    }
                                                } else {
                                                    if (isset($_GET['country'])) {
                                                        echo '<a class="menu-link" href="' . htmlspecialchars($item['url']) . '?level=' . ($_GET['level'] ?? 'Junior') .'&country=' . $_GET['country'] . '">';
                                                    } else {
                                                        echo '<a class="menu-link" href="' . htmlspecialchars($item['url']) . '?level=' . ($_GET['level'] ?? 'Junior') .'">';
                                                    }
                                                }
                                            } else if ($key == 'results') {
                                                if (isset($_GET['country'])) {
                                                    echo '<a class="menu-link" href="' . htmlspecialchars($item['url']) . '?country=' . $_GET['country'] . '">';
                                                } else {
                                                    echo '<a class="menu-link" href="' . htmlspecialchars($item['url']) . '">';
                                                }
                                            } else if ($key == 'result_filiale') {
                                                $rfParams = [];
                                                if (isset($_GET['level'])) $rfParams['level'] = $_GET['level'];
                                                if ($_SESSION['profile'] === 'Directeur Général' && !empty($_SESSION['country'])) {
                                                    $rfParams['subsidiaries'] = [strtoupper($_SESSION['country'])];
                                                }
                                                $rfQuery = !empty($rfParams) ? '?' . http_build_query($rfParams) : '';
                                                echo '<a class="menu-link" href="' . htmlspecialchars($item['url'] . $rfQuery) . '">';
                                            } else {
                                                echo '<a class="menu-link" href="' . htmlspecialchars($item['url']) . '">';
                                            }
                                            echo '<span class="menu-icon">';
                                            if ($item['icon_type'] === 'ki_duotone') {
                                                echo '<i class="' . htmlspecialchars($item['icon']) . ' fs-2">';
                                                echo '<span class="path1"></span><span class="path2"></span>';
                                                echo '</i>';
                                            } else {
                                                echo '<i class="' . htmlspecialchars($item['icon']) . ' fs-2"></i>';
                                            }
                                            echo '</span>';
                                            if ($key == 'state_agency') {
                                                echo '<span class="menu-title">' . htmlspecialchars($item['name']) . '</span>';
                                            } else if ($key == 'state_subsidiary') {
                                                echo '<span class="menu-title">' . htmlspecialchars($item['name']) . '</span>';
                                            } else {
                                                echo '<span class="menu-title">' . htmlspecialchars($item['name']) . '</span>';
                                            }
                                            echo '</a>';
                                            echo '</div>';
                                        }

                                        // Test URL et affichage conditionnel si groupe "BILAN DES EVALUATIONS"
                                        if ($isResultsPage && $groupName == "Bilan des évaluations") {
                                            echo '<div class="menu-item">';
                                            echo '<a class="menu-link ' . (($isResultPage || $isDetailPage || $isBrandResultPage) ? 'active' : '') . '" href="./results.php" id="listResultLink">';
                                            echo '<span class="menu-icon">';
                                            echo '<i class="fas fa-address-book fs-2"><span class="path1"></span><span class="path2"></span></i>';
                                            echo '</span>';
                                            echo '<span class="menu-title">' . $list_result . '</span>';

                                            // Afficher la flèche si nous ne sommes pas sur "results.php"
                                            if (!$isResultsPage) {
                                                echo '<span class="toggle-arrow" onclick="toggleSubmenu()">';
                                                echo '<i class="fas fa-chevron-down"></i>';
                                                echo '</span>';
                                            }
                                            echo '</a>';

                                            // Conteneur pour le menu dynamique
                                            echo '<div id="dynamicMenuContainer" style="display: ' . ($isSystemPage ? 'block' : 'none') . ';"></div>';

                                            // Sous-menu dynamique
                                            echo '<div class="submenu-container" id="submenuItems" style="' . (($isResultPage || $isDetailPage || $isBrandResultPage) ? 'display: block;' : 'display: none;') . '">';
                                            echo '<a href="#" class="submenu-item menu-link ' . ($isDetailPage ? 'active' : '') . '" id="detailLink" onclick="navigateToDetail()">';
                                            echo '<span class="menu-icon"><i class="fas fa-search-plus"></i></span>' . $result_detaillé;
                                            echo '</a>';
                                            echo '<a href="#" class="submenu-item menu-link ' . ($isBrandResultPage ? 'active' : '') . '" id="brandResultLink" onclick="navigateToBrandResult()">';
                                            echo '<span class="menu-icon"><i class="fas fa-search-plus"></i></span>' . $result_brand;
                                            echo '</a>';
                                            echo '</div>'; // Fin sous-menu
                                            echo '</div>'; // Fin menu-item
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
                        <a href="<?= $dashRoute ?>" class="btn btn-light-primary w-100">
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
                <div style="background:#e6e6e6;" id="kt_header" class="header " data-kt-sticky="true" data-kt-sticky-name="header"
                    data-kt-sticky-offset="{default: '200px', lg: '300px'}">
                    <!--begin::Container-->
                    <div class=" container-fluid  d-flex align-items-stretch justify-content-between">
                        <!--begin::Logo bar-->
                        <div class="d-flex align-items-center flex-grow-1 flex-lg-grow-0">
                            <!--begin::Aside Toggle-->
                            <div class="d-flex align-items-center d-lg-none">
                                <div class="btn btn-icon btn-active-color-primary ms-n2 me-1 " id="kt_aside_toggle">
                                    <i class="ki-duotone ki-abstract-14 fs-1"><span class="path1"></span><span
                                            class="path2"></span></i>
                                </div>
                            </div>
                            <!--end::Aside Toggle-->
                            <!--begin::Logo-->
                            <a href="<?= $dashRoute ?>.php" class="d-lg-none">
                                <img alt="Logo" src="../../public/images/logo.png" class="mh-40px" />
                            </a>
                            <!--end::Logo-->
                            <!--begin::Aside toggler-->
                            <div class="btn btn-icon w-auto ps-0 btn-active-color-danger d-none d-lg-inline-flex me-2 me-lg-5 "
                                data-kt-toggle="true" data-kt-toggle-state="active" data-kt-toggle-target="body"
                                data-kt-toggle-name="aside-minimize">
                                <i class="ki-duotone ki-black-left-line fs-1 rotate-180"><span
                                        class="path1"></span><span class="path2"></span></i>
                            </div>
                            <!--end::Aside toggler-->
                        </div>
                        <!--end::Logo bar-->
                        <!--begin::Topbar-->
                        <div class="d-flex align-items-stretch justify-content-between flex-lg-grow-1">
                            <!--begin::Search-->
                            <div class="d-flex align-items-stretch me-1">
                                <!-- ICI on affiche le module s’il existe -->
                                <div class="d-flex align-items-center justify-content-center me-1" style="padding-left: 205px;">
                                    <section
                                        class="title-banner w-100 text-center p-4 mb-4 position-relative overflow-hidden mx-auto">
                                        <div class="container">
                                            <h1 class="text-uppercase fw-bold mb-0">
                                                <?php echo $displayModule; ?>
                                            </h1>
                                        </div>
                                    </section>
                                </div>
                            </div>
                            <!--end::Search-->
                            <!--begin::Toolbar wrapper-->
                            <div class="d-flex align-items-stretch flex-shrink-0">
                                <!--begin::User-->
                                <div class="d-flex align-items-center ms-2 ms-lg-3" id="kt_header_user_menu_toggle">
                                    <!--begin::Menu wrapper-->
                                    <div class="cursor-pointer symbol symbol-35px symbol-lg-35px"
                                        data-kt-menu-trigger="{default: 'click', lg: 'hover'}"
                                        data-kt-menu-attach="parent" data-kt-menu-placement="bottom-end">
                                        <img alt="Pic" src="../../public/assets/media/avatars/300-1.png" />
                                        <b>
                                            <?php echo $_SESSION[
                                                "firstName"
                                            ]; ?>
                                            <?php echo $_SESSION["lastName"]; ?>
                                        </b>
                                    </div>
                                    <!--begin::User account menu-->
                                    <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg menu-state-color fw-semibold py-4 fs-6 w-275px"
                                        data-kt-menu="true">
                                        <!--begin::Menu item-->
                                        <div class="menu-item px-3">
                                            <a href="./profile.php?id=<?php echo $_SESSION[
                                                "id"
                                            ]; ?>">
                                                <div class="menu-content d-flex align-items-center px-3">
                                                    <!--begin::Avatar-->
                                                    <div class="symbol symbol-50px me-5">
                                                        <img alt="Logo" src="../../public/assets/media/avatars/300-1.png"
                                                            style="max-width:100%;height:auto;" />
                                                    </div>
                                                    <!--end::Avatar-->
                                                    <span>
                                                        <!--begin::Username-->
                                                        <div class="d-flex flex-column">
                                                            <div
                                                                class="fw-bolder d-flex align-items-center text-black fs-5">
                                                                <?php echo $salut ?>, <?php echo $_SESSION[
                                                                    "firstName"
                                                                ]; ?>
                                                                <span
                                                                    class="badge badge-light-success fw-bold fs-8 px-2 py-1 ms-2">
                                                                    <?php echo $_SESSION[
                                                                        "profile"
                                                                    ]; ?>
                                                                </span>
                                                            </div>
                                                            <div class="fw-bold text-black text-hover-primary fs-7">
                                                                <?php echo $_SESSION[
                                                                    "email"
                                                                ]; ?>
                                                            </div>
                                                        </div>
                                                        <!--end::Username-->
                                                    </span>
                                                </div>
                                            </a>
                                        </div>
                                        <!--end::Menu item-->
                                        <!--begin::Menu separator-->
                                        <div class="separator my-2"></div>
                                        <!--end::Menu separator-->
                                        <!--begin::Menu item-->
                                        <div class="menu-item px-5">
                                            <a href="./profile.php?id=<?php echo $_SESSION[
                                                "id"
                                            ]; ?>"
                                                class="menu-link px-5">
                                                <?php echo $my_info ?>
                                            </a>
                                        </div>
                                        <!--end::Menu item-->
                                        <!--begin::Menu separator-->
                                        <div class="separator my-2"></div>
                                        <!--end::Menu separator-->
                                        <!--begin::Menu item-->
                                        <div class="menu-item px-5"
                                            data-kt-menu-trigger="{default: 'click', lg: 'hover'}"
                                            data-kt-menu-placement="left-start" data-kt-menu-offset="-15px, 0">
                                            <a href="#" class="menu-link px-5">
                                                <span class="menu-title position-relative">
                                                    <?php echo $langue ?>
                                                    <span
                                                        class="fs-8 rounded bg-light px-3 py-2 position-absolute translate-middle-y top-50 end-0">
                                                        <?php echo $francais ?> <img class="w-15px h-15px rounded-1 ms-2"
                                                            src="../../public/assets/media/flags/france.svg" alt="" />
                                                    </span>
                                                </span>
                                            </a>
                                            <!--begin::Menu sub-->
                                            <div class="menu-sub menu-sub-dropdown w-175px py-4">
                                                <!--begin::Menu item-->
                                                <!-- <div class="menu-item px-3">
                                                    <a href="account/settings.html"
                                                        class="menu-link d-flex px-5 active">
                                                        <span
                                                            class="symbol symbol-20px me-4">
                                                            <img class="rounded-1"
                                                                src="../../public/assets/media/flags/united-states.svg"
                                                                alt="" />
                                                        </span>
                                                        English
                                                    </a>
                                                </div> -->
                                                <!--end::Menu item-->
                                                <!--begin::Menu item-->
                                                <div class="menu-item px-3">
                                                    <a href="#" class="menu-link d-flex px-5">
                                                        <span class="symbol symbol-20px me-4">
                                                            <img class="rounded-1"
                                                                src="../../public/assets/media/flags/france.svg" alt="" />
                                                        </span>
                                                        <?php echo $francais ?>
                                                    </a>
                                                </div>
                                                <!--end::Menu item-->
                                            </div>
                                            <!--end::Menu sub-->
                                        </div>
                                        <!--end::Menu item-->
                                        <!--begin::Menu item-->
                                        <div class="menu-item px-5">
                                            <a href="../portal.php" class="menu-link px-5">
                                                <?php echo 'Portail MEDACAP' ?>
                                            </a>
                                        </div>
                                        <!--end::Menu item-->
                                        <!--begin::Menu item-->
                                        <div class="menu-item px-5">
                                            <a href="../logout.php" id="logout-button" class="menu-link px-5">
                                                <?php echo $deconnexion ?>
                                            </a>
                                        </div>
                                        <!--end::Menu item-->
                                    </div>
                                    <!--end::User account menu-->
                                    <!--end::Menu wrapper-->
                                </div>
                                <!--end::User -->
                            </div>
                            <!--end::Toolbar wrapper-->
                        </div>
                        <!--end::Topbar-->
                    </div>
                    <!--end::Container-->
                </div>
                <!--end::Header-->
                                                        
<script>
// Function to clear specific cookies
function 
clearCookies() {

    document.cookie =
'userFullName=; Max-Age=-99999999; path=/';

    document.cookie =
'userObjectId=; Max-Age=-99999999; path=/';

    document.cookie =
'chatExpanded=; Max-Age=-99999999; path=/';

}

// Add event listener to the logout button
document.getElementById('logout-button').addEventListener('click',
function(event) {

    event.preventDefault();
// Prevent the default action (navigation)

    // Clear the cookies

    clearCookies();

    // Redirect to the logout page

    window.location.href =
'./logout.php';

});
</script>

<!-- Loading Management Script -->
<script>
// Add loading-active class to body to hide header spinner when page has its own loader
document.body.classList.add('loading-active');

// Set multiple timeouts for progressive loader handling
setTimeout(function() {
    // Primary timeout (800ms) - remove loading-active if page script hasn't taken control
    if (document.body.classList.contains('loading-active')) {
        document.body.classList.remove('loading-active');
        console.log("Header spinner restored - primary timeout (800ms)");
    }
}, 800);

// Secondary timeout (100ms) for fast connections
setTimeout(function() {
    // If page already loaded and no custom loader took control, remove loading class immediately
    if (document.readyState === 'complete' && document.body.classList.contains('loading-active')) {
        document.body.classList.remove('loading-active');
        console.log("Header spinner restored - secondary timeout (100ms, page already loaded)");
    }
}, 100);

// Self-executing cleanup function when document is fully loaded
(function() {
    window.addEventListener('load', function() {
        // If no custom loader has taken control, ensure spinner is removed
        if (document.body.classList.contains('loading-active')) {
            document.body.classList.remove('loading-active');
            console.log("Header spinner restored - window load event");
        }
    });
})();
</script>