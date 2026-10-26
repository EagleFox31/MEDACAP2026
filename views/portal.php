<?php
session_start();

// Déterminer la langue - Français par défaut si non spécifié dans l'URL
$lang = isset($_GET['lang']) ? $_GET['lang'] : 'FR';
$_SESSION['lang'] = $lang;
$lang_lower = ($lang === 'EN') ? 'en' : 'fr';

// Inclure le fichier de langue approprié
if ($lang === 'EN') {
    include_once "language_EN.php";
} else {
    include_once "language.php";
}

if (!isset($_SESSION["profile"])) {
    header("Location: ../");
    exit();
}

$profile = $_SESSION["profile"];
$department = isset($_SESSION['department']) ? $_SESSION['department'] : '';

if (strcasecmp((string)$profile, 'Candidat') === 0) {
    header('Location: measure/candidatePaperHome');
    exit();
}

// Truncate user name
$firstName = $_SESSION["firstName"] ?? '';
$lastName = $_SESSION["lastName"] ?? '';
$fullName = trim($firstName . ' ' . $lastName);
$truncatedName = strlen($fullName) > 10 ? substr($fullName, 0, 10) . '...' : $fullName;

// Images par département
$equipmentImages = [
    ['url' => '../public/images/renaults.png', 'animation' => 'panLeft'],
    ['url' => '../public/images/truck.png', 'animation' => 'panLeft'],
    ['url' => '../public/images/truck.png.png', 'animation' => 'panLeft'],
    ['url' => '../public/images/tof_dashboard_tech.png', 'animation' => 'panLeft'],
    ['url' => '../public/images/truck2025.png', 'animation' => 'panLeft'],
    ['url' => '../public/images/jcb2.png', 'animation' => 'panLeft'],
    ['url' => '../public/images/renaulttech.png', 'animation' => 'panLeft'],
    ['url' => '../public/images/mercedestruck2.png', 'animation' => 'panLeft']
];

$motorsImages = [
    ['url' => '../public/images/prado3.png', 'animation' => 'panLeft'],
    ['url' => '../public/images/welc_tech.png', 'animation' => 'panLeft'],
    ['url' => '../public/images/labmoteurs_portal.png', 'animation' => 'panLeft'],
    ['url' => '../public/images/peugeot_portal2.png', 'animation' => 'panLeft']
];

$otherImages = [
    ['url' => '../public/images/mercedestruck2.png', 'animation' => 'panLeft'],
    ['url' => '../public/images/prado3.png', 'animation' => 'panLeft'],
    ['url' => '../public/images/tof_dashboard_tech.png', 'animation' => 'panLeft'],
    ['url' => '../public/images/peugeot_portal2.png', 'animation' => 'panLeft']
];

$show_brands = true;
$carouselImages = $otherImages;

if (!empty($department)) {
    $depts = array_map('trim', explode('&', $department));
    $invalid = false;
    $has_equipment = false;
    $has_motors = false;
    foreach ($depts as $d) {
        if ($d === 'Equipment') {
            $has_equipment = true;
        } elseif ($d === 'Motors') {
            $has_motors = true;
        } else {
            $invalid = true;
        }
    }
    if ($invalid) {
        $carouselImages = [['url' => '../public/images/backgrounds/home.png', 'animation' => 'panLeft']];
        $show_brands = false;
    } else {
        if ($has_equipment) {
            $carouselImages = $equipmentImages;
            if ($has_motors) {
                $carouselImages = array_merge($equipmentImages, $motorsImages);
            }
        } elseif ($has_motors) {
            $carouselImages = $motorsImages;
        }
    }
}

// Textes pour les étapes
$texts = [
    'en' => [
        'numbers' => ['1.', '2.', '3.', '4.', '5.', '6.', '7.'],
        'labels' => ['Measure', 'Explore', 'Define', 'Acquire', 'Certify', 'Apply', 'Perform'],
        'intro' => 'Please click on one of the active steps to continue.'
    ],
    'fr' => [
        'numbers' => ['1.', '2.', '3.', '4.', '5.', '6.', '7.'],
        'labels' => ['Mesurer', 'Exploiter', 'Définir', 'Acquérir', 'Certifier', 'Appliquer', 'Performer'],
        'intro' => 'Veuillez cliquer sur l\'une des étapes actives pour continuer.'
    ]
];
$step_numbers = $texts[$lang_lower]['numbers'];
$step_labels = $texts[$lang_lower]['labels'];

// Icônes des étapes
$step_icons = [
    0 => 'fa-clipboard-check',
    1 => 'fa-chart-pie',
    2 => 'fa-edit',
    3 => 'fa-book-reader',
    4 => 'fa-award',
    5 => 'fa-tools',
    6 => 'fa-medal'
];

// Profils autorisés communs
$admin_profiles = ['Super Admin', 'Directeur Groupe', 'Admin', 'Directeur Pièce et Service', 'Directeur des Opérations', 'Directeur Général','Ressource Humaine'];
$user_profiles = ['Trainer', 'Technicien', 'Manager', 'Candidat'];

// Configuration des landing pages par module et par profil
$landing_pages = [
    'measure' => [
        'default' => 'measure/dashboard',
        'Technicien' => 'measure/ListeDesQcm',
        'Manager' => 'measure/dashboardAdmin',
        'Directeur Groupe' => 'measure/dashboardAdmin',
        'Directeur Pièce et Service' => 'measure/dashboardAdmin',
        'Directeur des Opérations' => 'measure/dashboardAdmin',
        'Directeur Général' => 'measure/dashboardAdmin',
        'Admin' => 'measure/dashboardAdmin',
        'Super Admin' => 'measure/dashboardAdmin',
        'Candidat' => 'measure/candidatePaperHome',
        'Ressource Humaine' => 'measure/dashboardrh'
    ],
    'explore' => [
        'default' => 'explore/dashboard',
        'Directeur Pièce et Service' => 'explore/stateAgency',
        'Directeur des Opérations'   => 'explore/stateAgency',
    ],
    'define' => [
        'default' => 'define/dashboard',
        'Technicien' => 'define/personalTraining'
    ],
    'acquire' => [
        'default' => '404',
    ],
    'certify' => [
        'default' => '404',
    ],
    'apply' => [
        'default' => '404',
    ],
    'perform' => [
        'default' => '404',
    ]
];

// Mapping des étapes aux modules
$module_names = [
    0 => 'measure',
    1 => 'explore',
    2 => 'define',
    3 => 'acquire',
    4 => 'certify',
    5 => 'apply',
    6 => 'perform'
];

// Calcul des liens et classes pour chaque étape
$step_links = [];
$step_classes = [];

$step_links[0] = '404';
$step_classes[0] = 'card-step unauthorized';
if (in_array($profile, array_merge($admin_profiles, $user_profiles))) {
    $module = $module_names[0];
    $link = $landing_pages[$module]['default'];
    if (isset($landing_pages[$module][$profile])) {
        $link = $landing_pages[$module][$profile];
    }
    $step_links[0] = $link;
    $step_classes[0] = 'card-step authorized';
}

$step_links[1] = '404';
$step_classes[1] = 'card-step unauthorized';
if (!in_array($profile, ['Technicien', 'Manager', 'Ressource Humaine'])) {
    $module = $module_names[1];
    $link = $landing_pages[$module]['default'];
    if (isset($landing_pages[$module][$profile])) {
        $link = $landing_pages[$module][$profile];
    }
    $step_links[1] = $link;
    $step_classes[1] = 'card-step authorized';
}

$step_links[2] = '404';
$step_classes[2] = 'card-step unauthorized';
if (in_array($profile, array_merge($admin_profiles, ['Technicien', 'Manager'])) && $profile !== 'Ressource Humaine') {
    $module = $module_names[2];
    $link = $landing_pages[$module]['default'];
    if (isset($landing_pages[$module][$profile])) {
        $link = $landing_pages[$module][$profile];
    }
    $step_links[2] = $link;
    $step_classes[2] = 'card-step authorized';
}

for ($i = 3; $i <= 6; $i++) {
    $module = $module_names[$i];
    $link = $landing_pages[$module]['default'];
    if (isset($landing_pages[$module][$profile])) {
        $link = $landing_pages[$module][$profile];
    }
    $step_links[$i] = $link;
    $step_classes[$i] = ($link !== '404' ? 'card-step authorized' : 'card-step unauthorized');
}

// Marques pour le carrousel
$all_brands = [
    ['name' => 'BYD', 'icon' => 'car'],
    ['name' => 'CITROEN', 'icon' => 'car'],
    ['name' => 'FUSO', 'icon' => 'truck'],
    ['name' => 'HINO', 'icon' => 'truck'],
    ['name' => 'JCB', 'icon' => 'truck-monster'],
    ['name' => 'KING LONG', 'icon' => 'bus'],
    ['name' => 'LOVOL', 'icon' => 'truck-pickup'],
    ['name' => 'MERCEDES', 'icon' => 'car'],
    ['name' => 'MERCEDES TRUCK', 'icon' => 'truck'],
    ['name' => 'MITSUBISHI', 'icon' => 'car'],
    ['name' => 'PEUGEOT', 'icon' => 'car'],
    ['name' => 'RENAULT TRUCK', 'icon' => 'truck'],
    ['name' => 'SINOTRUK', 'icon' => 'truck'],
    ['name' => 'SUZUKI', 'icon' => 'car'],
    ['name' => 'TOYOTA', 'icon' => 'car'],
    ['name' => 'TOYOTA BT', 'icon' => 'truck-pickup'],
    ['name' => 'TOYOTA FORKLIFT', 'icon' => 'truck-pickup']
];

$equipment_brand_names = ['FUSO', 'HINO', 'JCB', 'KING LONG', 'LOVOL', 'MERCEDES TRUCK', 'RENAULT TRUCK', 'SINOTRUK', 'MERCEDES', 'TOYOTA BT', 'TOYOTA FORKLIFT'];
$motors_brand_names = ['BYD', 'CITROEN', 'MITSUBISHI', 'PEUGEOT', 'SUZUKI', 'TOYOTA'];

$brands = $all_brands;

if (!empty($department) && $show_brands) {
    $depts = array_map('trim', explode('&', $department));
    $allowed_names = [];
    if (in_array('Equipment', $depts)) {
        $allowed_names = array_merge($allowed_names, $equipment_brand_names);
    }
    if (in_array('Motors', $depts)) {
        $allowed_names = array_merge($allowed_names, $motors_brand_names);
    }
    if (!empty($allowed_names)) {
        $brands = array_filter($all_brands, function($brand) use ($allowed_names) {
            return in_array($brand['name'], $allowed_names);
        });
        $brands = array_values($brands);
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang_lower) ?>">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($portal) ?> | CFAO Mobility Academy</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link href="../public/images/logo-cfao.png" rel="icon">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700" />
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/5.0.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, rgba(36, 35, 92, 1), rgba(230, 34, 68, 1));
            --secondary-gradient: linear-gradient(135deg, rgba(229, 85, 79, 1), rgba(54, 60, 90, 1));
            --button-gradient: linear-gradient(135deg, rgba(230, 34, 68, 1), rgba(229, 85, 79, 1));
            --button-alt-gradient: linear-gradient(135deg, rgba(54, 60, 90, 1), rgba(36, 35, 92, 1));
            --button-shadow: 0 10px 20px -8px rgba(230, 34, 68, 0.45);
            --button-alt-shadow: 0 10px 20px -8px rgba(54, 60, 90, 0.4);
            --color-0: rgb(36, 35, 92);
            --color-1: rgb(230, 34, 68);
            --color-2: rgb(229, 85, 79);
            --color-3: rgb(54, 60, 90);
            --bg-overlay: linear-gradient(135deg, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0.1));
            --transparency-bg: rgba(255, 255, 255, 0.2);
            --transparency-border: rgba(255, 255, 255, 0.18);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        html, body {
            height: 100vh;
            margin: 0;
            padding: 0;
            overflow: hidden;
            font-family: 'Inter', sans-serif;
            color: #ffffff;
        }
        body {
            background: #000;
            position: relative;
        }
        .main-container {
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }
        .mist-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.2);
            z-index: -1;
        }
        #kt_header {
            backdrop-filter: blur(10px);
            background: var(--transparency-bg);
            border-bottom: 1px solid var(--transparency-border);
            height: 60px;
            z-index: 2;
            flex-shrink: 0;
        }
        .header {
            display: flex;
            align-items: stretch;
        }
        .header-nav {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            width: 100%;
            height: 100%;
        }
        .header-left,
        .header-right {
            display: flex;
            align-items: center;
            flex-shrink: 0;
            width: 200px;
        }
        .header-center {
            flex-grow: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .language-toggle {
            display: flex;
            gap: 0;
            background: var(--transparency-bg);
            border-radius: 999px;
            overflow: hidden;
            flex-shrink: 0;
        }
        .lang-btn {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            border: none;
            background: transparent;
            color: #ffffff;
            cursor: pointer;
            transition: var(--transition);
            white-space: nowrap;
        }
        .lang-btn.active, .lang-btn:hover {
            background: #ffffff;
            color: #000000;
        }
        .header-right {
            justify-content: flex-end;
            gap: 1rem;
        }
        .user-symbol {
            display: flex !important;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            cursor: pointer;
        }
        .user-symbol img {
            width: 30px !important;
            height: 30px !important;
            max-width: 30px !important;
            border-radius: 50%;
        }
        .user-name {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 120px;
            display: block;
            font-size: 0.8rem;
            margin-top: 2px;
            color: #ffffff;
        }

        /* Styles pour le menu utilisateur */
        .user-menu-container {
            position: relative;
        }

        .user-dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            min-width: 275px;
            z-index: 9999;
            display: none;
            flex-direction: column;
            margin-top: 10px;
        }

        .user-dropdown-menu.show {
            display: flex;
        }

        .user-info-section {
            padding: 1rem;
            border-bottom: 1px solid #e0e0e0;
        }

        .user-info-content {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .user-details {
            flex: 1;
            min-width: 0;
        }

        .user-profile-badge {
            background-color: #d1ecf1;
            color: #0c5460;
            padding: 0.25em 0.6em;
            border-radius: 0.25rem;
            font-size: 0.75em;
            display: inline-block;
            margin-bottom: 4px;
        }

        .user-fullname {
            font-weight: bold;
            font-size: 0.85rem;
            color: #000;
            margin-bottom: 2px;
        }

        .user-email {
            font-size: 0.875rem;
            color: #666;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .user-greeting {
            padding: 0.5rem 1rem;
            font-weight: bold;
            color: #000;
            border-bottom: 1px solid #e0e0e0;
        }

        .user-menu-item {
            padding: 0.75rem 1rem;
        }

        .user-menu-link {
            color: #333;
            text-decoration: none;
            display: block;
            transition: color 0.3s;
        }

        .user-menu-link:hover {
            color: #3699ff;
        }

        .header-section {
            position: relative;
            text-align: center;
            padding: 20px;
            z-index: -1;
            background-color: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            margin: 10px;
            display: inline-block;
            flex-shrink: 0;
        }
        .header-section h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: white;
            margin-bottom: 5px;
            display: inline-block;
            text-shadow: 0 4px 10px rgba(0,0,0,0.3);
        }
        .user-profile-label {
            display: inline-block;
            padding: 6px 20px;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(10px);
            border-radius: 30px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            font-size: 1rem !important;
            margin-bottom: 20px !important;
            color: #ffffff !important;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .user-profile-label strong {
            color: #ffd700;
            margin-left: 5px;
        }
        .header-section p {
            font-size: 1.4rem;
            color: white;
            animation: focusInContract 2s ease-in-out;
            margin: 0;
        }
        @keyframes typing {
            from { width: 0; }
            to { width: 100%; }
        }
        @keyframes blink-caret {
            from, to { border-color: transparent; }
            50% { border-color: transparent; }
        }
        @keyframes focusInContract {
            0% { letter-spacing: 1em; filter: blur(12px); opacity: 0; }
            100% { filter: blur(0); opacity: 1; }
        }
        .content-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 10px;
            position: relative;
            z-index: 1;
            overflow: hidden;
        }
        .pyramid-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            overflow: hidden;
        }
        .pyramid-level {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .pyramid-level:last-child { margin-bottom: 0; }
        .card-step {
            background-color: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(15px);
            border-radius: 15px;
            padding: 15px;
            color: white;
            text-align: center;
            width: 180px;
            margin: 5px;
            transition: transform 0.3s ease, box-shadow 0.5s ease;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
            text-decoration: none;
            border: 1px solid rgba(255, 255, 255, 0.18);
            position: relative;
        }
        .card-step:hover {
            transform: scale(1.1);
            box-shadow: 0 16px 32px rgba(255, 255, 255, 255);
            text-decoration: none;
        }
        .card-step h3 {
            font-size: 1.2rem;
            font-weight: 500;
            margin-bottom: 10px;
            color: inherit;
        }
        .step-number {
            font-size: 1.8rem;
            color: white;
            font-weight: bold;
            margin-right: 5px;
        }
        .card-step i {
            font-size: 2rem;
            color: #d70006 !important;
            margin-bottom: 5px;
            transition: none;
        }
        .card-step.active {
            background-color: #d70006;
            color: #fff;
        }
        .card-step.active h3 { color: #fff; }
        .card-step.active i { color: #d70006 !important; }
        .card-step h3::first-letter {
            display: inline-block;
            animation: zoomLetter 2s infinite;
        }
        @keyframes zoomLetter {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.5); }
        }
        .arrow-container {
            display: flex;
            justify-content: center;
            margin-bottom: 5px;
        }
        .arrow {
            width: 25px;
            height: 25px;
            background: rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(5px);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.3s;
            animation: bounce 4s infinite;
        }
        .arrow::before {
            content: '';
            display: block;
            width: 8px;
            height: 8px;
            border: solid #fff;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
            transition: border-color 0.3s;
        }
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-15px); }
            60% { transform: translateY(-8px); }
        }
        .arrow-horizontal {
            width: 20px;
            height: 2px;
            background: #fff;
            margin: 0 3px;
            position: relative;
            animation: moveArrow 4s infinite;
        }
        .arrow-horizontal::after {
            content: '';
            position: absolute;
            top: -2px;
            right: -2px;
            width: 10px;
            height: 8px;
            border: solid #fff;
            border-width: 0 2px 2px 0;
            transform: rotate(-45deg);
        }
        @keyframes moveArrow {
            0%, 100% { transform: translateX(0); }
            50% { transform: translateX(4px); }
        }
        .brand-carousel {
            overflow: hidden;
            position: relative;
            width: 100%;
            height: 50px;
            margin-bottom: 10px;
            background-color: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .brand-container {
            display: flex;
            width: auto;
            animation: marquee 100s linear infinite;
            white-space: nowrap;
            position: relative;
        }
        .brand-slide { display: flex; flex-wrap: nowrap; }
        .brand-item {
            display: inline-flex;
            align-items: center;
            margin: 0 15px;
            font-size: 1rem;
            color: white;
            white-space: nowrap;
            transition: transform 0.3s ease;
        }
        .brand-item.animate { animation: brandZoom 10s infinite; }
        @keyframes brandZoom {
            0%, 10%, 100% { transform: scale(1); }
            5% { transform: scale(1.3); }
        }
        .brand-item h4 {
            margin: 0;
            display: flex;
            align-items: center;
        }
        .brand-name { margin-right: 8px; }
        .icon-zoom {
            animation: zoomInOut 2s infinite;
            color: #d70006;
        }
        @keyframes marquee {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
        @keyframes zoomInOut {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        footer {
            background-color: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            color: #ffffff;
            text-align: center;
            padding: 10px;
            font-size: 0.9rem;
            position: relative;
            z-index: 1;
            border-top: 1px solid rgba(255, 255, 255, 0.18);
            flex-shrink: 0;
        }
        @media (max-width: 1200px) {
            .pyramid-level { margin-bottom: 8px; }
            .arrow-container { margin-bottom: 3px; }
            .card-step { margin: 3px; width: 160px; padding: 12px; }
            .pyramid-level:nth-child(2) .card-step {
                flex: 1 1 calc(50% - 10px);
                max-width: calc(50% - 10px);
            }
        }
        @media (max-width: 768px) {
            .card-step { width: 100%; margin: 8px 0; padding: 10px; }
            .pyramid-level { flex-direction: column; }
            .arrow-horizontal { display: none; }
            .header-section h1 { font-size: 1.5rem; }
            .header-section p { font-size: 1.2rem; }
            .card-step i { font-size: 1.8rem; }
            .brand-carousel { height: 40px; }
            .brand-container { animation: marquee 40s linear infinite; }
            .brand-item { margin: 0 10px; font-size: 0.9rem; }
            .user-dropdown-menu {
                position: fixed;
                top: 60px;
                left: 0;
                right: 0;
                margin: 0;
                border-radius: 0;
            }
        }
        .background-carousel {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -2;
            background-size: cover;
            background-position: center;
            transition: background-image 1s ease-in-out;
        }
        .svg-inline--fa { height: 1.5em; }
        @keyframes panLeft {
            from { background-position: right center; }
            to { background-position: left center; }
        }
        .card-step.unauthorized {
            cursor: not-allowed;
            filter: grayscale(100%) opacity(0.5);
        }
        .card-step.unauthorized:hover {
            transform: none;
            opacity: 0.6;
        }
        .card-step.authorized { cursor: pointer; }
        .mh-40px { max-height: 40px; }
    </style>
</head>
<body>
<div class="mist-overlay"></div>
<div class="background-carousel"></div>
<div class="main-container">
    <!-- Header -->
    <div id="kt_header" class="header">
        <div class="header-nav">
            <!-- Left section -->
            <div class="header-left">
                <img alt="Logo" src="../public/images/logo-cfao.png" class="mh-40px me-2" />
            </div>

            <!-- Center section - empty for nav only -->
            <div class="header-center"></div>

            <!-- Right section -->
            <div class="header-right">
                <!-- Language Toggle -->
                <div class="language-toggle me-3">
                    <button class="lang-btn <?= ($lang === 'EN') ? 'active' : ''; ?>" onclick="window.location.href='?lang=EN'">EN</button>
                    <button class="lang-btn <?= ($lang === 'FR') ? 'active' : ''; ?>" onclick="window.location.href='?lang=FR'">FR</button>
                </div>

                <!-- User Menu -->
                <div class="user-menu-container">
                    <div class="user-symbol" id="userMenuToggle">
                        <img alt="Pic" src="../public/assets/media/avatars/300-1.png" />
                        <b class="user-name"><?= htmlspecialchars($truncatedName) ?></b>
                    </div>

                    <!-- User dropdown menu -->
                    <div class="user-dropdown-menu" id="userDropdownMenu">
                        <!-- User info section -->
                        <div class="user-info-section">
                            <div class="user-info-content">
                                <div class="user-avatar-container">
                                    <img src="../public/assets/media/avatars/300-1.png" alt="Avatar" class="user-avatar">
                                </div>
                                <div class="user-details">
                                    <span class="user-profile-badge">
                                        <?= htmlspecialchars($profile) ?>
                                    </span>
                                    <div class="user-fullname">
                                        <?= htmlspecialchars($fullName) ?>
                                    </div>
                                    <div class="user-email">
                                        <?= htmlspecialchars($_SESSION["email"] ?? '') ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Greeting -->
                        <div class="user-greeting">
                            <?= $salut ?? 'Hello' ?>, <?= htmlspecialchars($_SESSION["firstName"] ?? '') ?>
                        </div>

                        <!-- Logout -->
                        <div class="user-menu-item">
                            <a href="measure/logout.php" id="logout-button" class="user-menu-link">
                                <?= $deconnexion ?? 'Logout' ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- End Header -->

    <!-- Title Section -->
    <div class="header-section">
        <h1>
            <?= $lang === 'FR' ? 'Bienvenue' : 'Welcome' ?> 
            <span style="color: #ffffff;"><?= htmlspecialchars($firstName) ?></span> 
            <?= $lang === 'FR' ? 'sur' : 'on' ?> <span>ACADEMY</span>
        </h1>
        <p class="user-profile-label">
            <i class="fas fa-user-tag me-2"></i>
            <?= $lang === 'FR' ? 'en tant que' : 'as' ?> <strong><?= htmlspecialchars($profile) ?></strong>
        </p>
        <p style="font-size: 1rem; opacity: 0.8; font-weight: 400;"><?= htmlspecialchars($texts[$lang_lower]['intro']) ?></p>
    </div>

    <div class="content-section">
        <div class="pyramid-container">
            <!-- Niveau 1: Étape 0 -->
            <div class="pyramid-level">
                <a href="<?= htmlspecialchars($step_links[0]) ?>" class="<?= htmlspecialchars($step_classes[0]) ?>">
                    <i style="color: #d70006;" class="fas <?= $step_icons[0] ?> fs-2"></i>
                    <h3><span class="step-number"><?= htmlspecialchars($step_numbers[0]) ?></span><?= htmlspecialchars($step_labels[0]) ?></h3>
                </a>
            </div>
            <div class="arrow-container"></div>
            <!-- Niveau 2: Étapes 1 et 2 -->
            <div class="pyramid-level">
                <a href="<?= htmlspecialchars($step_links[1]) ?>" class="<?= htmlspecialchars($step_classes[1]) ?>">
                    <i style="color: #d70006;" class="fas <?= $step_icons[1] ?> fs-2"></i>
                    <h3><span class="step-number"><?= htmlspecialchars($step_numbers[1]) ?></span><?= htmlspecialchars($step_labels[1]) ?></h3>
                </a>
                <div class="arrow-horizontal"></div>
                <a href="<?= htmlspecialchars($step_links[2]) ?>" class="<?= htmlspecialchars($step_classes[2]) ?>" title="en cours de développement">
                    <i style="color: #d70006;" class="fas <?= $step_icons[2] ?> fs-2"></i>
                    <h3><span class="step-number"><?= htmlspecialchars($step_numbers[2]) ?></span><?= htmlspecialchars($step_labels[2]) ?></h3>
                </a>
            </div>
            <div class="arrow-container"></div>
            <!-- Niveau 3: Étapes 3 à 6 -->
            <div class="pyramid-level">
                <a href="<?= htmlspecialchars($step_links[3]) ?>" class="<?= htmlspecialchars($step_classes[3]) ?>" title="en cours de développement">
                    <i style="color: #d70006;" class="fas <?= $step_icons[3] ?> fs-2"></i>
                    <h3><span class="step-number"><?= htmlspecialchars($step_numbers[3]) ?></span><?= htmlspecialchars($step_labels[3]) ?></h3>
                </a>
                <div class="arrow-horizontal"></div>
                <a href="<?= htmlspecialchars($step_links[4]) ?>" class="<?= htmlspecialchars($step_classes[4]) ?>" title="en cours de développement">
                    <i style="color: #d70006;" class="fas <?= $step_icons[4] ?> fs-2"></i>
                    <h3><span class="step-number"><?= htmlspecialchars($step_numbers[4]) ?></span><?= htmlspecialchars($step_labels[4]) ?></h3>
                </a>
                <div class="arrow-horizontal"></div>
                <a href="<?= htmlspecialchars($step_links[5]) ?>" class="<?= htmlspecialchars($step_classes[5]) ?>" title="en cours de développement">
                    <i style="color: #d70006;" class="fas <?= $step_icons[5] ?> fs-2"></i>
                    <h3><span class="step-number"><?= htmlspecialchars($step_numbers[5]) ?></span><?= htmlspecialchars($step_labels[5]) ?></h3>
                </a>
                <div class="arrow-horizontal"></div>
                <a href="<?= htmlspecialchars($step_links[6]) ?>" class="<?= htmlspecialchars($step_classes[6]) ?>" title="en cours de développement">
                    <i style="color: #d70006;" class="fas <?= $step_icons[6] ?> fs-2"></i>
                    <h3><span class="step-number"><?= htmlspecialchars($step_numbers[6]) ?></span><?= htmlspecialchars($step_labels[6]) ?></h3>
                </a>
            </div>
        </div>
        </div>
    </div>

    <?php if (in_array($profile, ['Super Admin', 'Admin', 'Ressource Humaine', 'Directeur Groupe', 'Directeur Général'])): ?>
    <div style="margin-top: 20px; text-align: center; z-index: 1; position: relative;">
        <a href="measure/manageRecruitment.php" class="card-step" style="display: inline-block; width: auto; padding: 12px 25px; border-radius: 30px; background: rgba(230, 34, 68, 0.2); border-color: rgba(230, 34, 68, 0.4);">
            <i class="fas fa-users" style="font-size: 1.5rem; margin-right: 10px; display: inline-block; vertical-align: middle;"></i>
            <h3 style="display: inline-block; margin: 0; vertical-align: middle; font-size: 1.1rem;"><?= $lang === 'FR' ? 'Gérer une Campagne de Recrutement' : 'Manage Recruitment Campaign' ?></h3>
        </a>
    </div>
    <?php endif; ?>

    <?php if ($show_brands): ?>
        <div class="brand-carousel">
            <div class="brand-container">
                <div class="brand-slide">
                    <?php
                    $all_brands_display = array_merge($brands, $brands);
                    foreach ($all_brands_display as $brand): ?>
                        <div class="brand-item">
                            <h4><span class="brand-name"><?= htmlspecialchars($brand['name']) ?></span> <i class="fas fa-<?= htmlspecialchars($brand['icon']) ?> icon-zoom"></i></h4>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <footer>
        <strong><span class="text-muted fw-semibold me-2"><?= date('Y') ?>&copy;</span>
            <a href="#" class="text-white-800 text-hover-primary">CFAO Mobility Academy</a></strong>, All rights reserved
    </footer>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
<script>
    const carouselImages = <?= json_encode($carouselImages) ?>;
    let currentImageIndex = 0;
    const carouselElement = document.querySelector('.background-carousel');
    function changeBackgroundImage() {
        const image = carouselImages[currentImageIndex];
        carouselElement.style.backgroundImage = `url('${image.url}')`;
        carouselElement.style.animation = `${image.animation} 20s linear forwards`;
        currentImageIndex = (currentImageIndex + 1) % carouselImages.length;
    }
    changeBackgroundImage();
    setInterval(changeBackgroundImage, 30000);

    <?php if ($show_brands): ?>
    const brandItems = document.querySelectorAll('.brand-item');
    brandItems.forEach((item, index) => {
        item.style.animationDelay = `${index * 10}s`;
        item.classList.add('animate');
    });
    <?php endif; ?>

    const cardSteps = document.querySelectorAll('.card-step');
    cardSteps.forEach(card => {
        card.addEventListener('click', (e) => {
            if (card.classList.contains('unauthorized')) {
                e.preventDefault();
                alert("Vous n'êtes pas autorisé à accéder à cette page.");
            } else {
                cardSteps.forEach(c => c.classList.remove('active'));
                card.classList.add('active');
            }
        });
    });

    // Function to clear specific cookies
    function clearCookies() {
        document.cookie = 'userFullName=; Max-Age=-99999999; path=/';
        document.cookie = 'userObjectId=; Max-Age=-99999999; path=/';
        document.cookie = 'chatExpanded=; Max-Age=-99999999; path=/';
    }

    // Add event listener to the logout button
    document.getElementById('logout-button').addEventListener('click', function(event) {
        event.preventDefault(); // Prevent the default action (navigation)

        // Clear the cookies
        clearCookies();

        // Redirect to the logout page
        window.location.href = 'measure/logout.php';
    });

    // Gestion du menu utilisateur sur survol
    const userMenuToggle = document.getElementById('userMenuToggle');
    const userDropdownMenu = document.getElementById('userDropdownMenu');
    let hideTimeout;

    userMenuToggle.addEventListener('mouseenter', () => {
        clearTimeout(hideTimeout);
        userDropdownMenu.classList.add('show');
    });

    userMenuToggle.addEventListener('mouseleave', () => {
        hideTimeout = setTimeout(() => {
            userDropdownMenu.classList.remove('show');
        }, 150);
    });

    userDropdownMenu.addEventListener('mouseenter', () => {
        clearTimeout(hideTimeout);
    });

    userDropdownMenu.addEventListener('mouseleave', () => {
        hideTimeout = setTimeout(() => {
            userDropdownMenu.classList.remove('show');
        }, 150);
    });

    // Fermer le menu en scrollant
    window.addEventListener('scroll', () => {
        userDropdownMenu.classList.remove('show');
    });
</script>
</body>
</html>