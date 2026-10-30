<?php
/**
 * Topbar MEDACAP partagée (measure / explore / define).
 * PHP 7.3
 */

if (!function_exists('medacapTopbarSyncLang')) {
    /**
     * @param string|null $languageDir Dossier contenant language.php (ex. views/)
     * @return string FR|EN
     */
    function medacapTopbarSyncLang($languageDir = null)
    {
        if (isset($_GET['lang']) && ($_GET['lang'] === 'EN' || $_GET['lang'] === 'FR')) {
            $_SESSION['lang'] = $_GET['lang'];
        }

        $lang = isset($_SESSION['lang']) ? (string) $_SESSION['lang'] : 'FR';
        if ($lang !== 'EN') {
            $lang = 'FR';
        }

        if ($languageDir !== null && is_dir($languageDir)) {
            if ($lang === 'EN') {
                include_once $languageDir . '/language_EN.php';
            } else {
                include_once $languageDir . '/language.php';
            }
        }

        return $lang;
    }
}

if (!function_exists('medacapTopbarIncludeAssets')) {
    /**
     * CSS + JS topbar (à appeler une fois dans le <head> / fin header).
     *
     * @param string $assetPrefix ex. ../../public
     */
    function medacapTopbarIncludeAssets($assetPrefix = '../../public')
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $prefix = rtrim($assetPrefix, '/');
        echo '<link rel="stylesheet" href="' . htmlspecialchars($prefix . '/css/modules/medacap-topbar.css', ENT_QUOTES, 'UTF-8') . '">' . "\n";
        echo '<script defer src="' . htmlspecialchars($prefix . '/js/modules/medacap-topbar.js', ENT_QUOTES, 'UTF-8') . '"></script>' . "\n";
    }
}

if (!function_exists('medacapTopbarNormalizeOptions')) {
    function medacapTopbarNormalizeOptions($options)
    {
        $defaults = array(
            'displayModule' => '',
            'lang' => isset($_SESSION['lang']) ? (string) $_SESSION['lang'] : 'FR',
            'assetPrefix' => '../../public',
            'dashboardHref' => './dashboard.php',
            'mobileLogoHref' => './dashboard.php',
            'profileHref' => './profile.php?id=' . urlencode((string) (isset($_SESSION['id']) ? $_SESSION['id'] : '')),
            'portalHref' => '../portal.php',
            'logoutHref' => '../logout.php',
            'logoutRedirect' => './logout.php',
            'avatarSrc' => '../../public/assets/media/avatars/300-1.png',
        );

        if (!is_array($options)) {
            $options = array();
        }

        return array_merge($defaults, $options);
    }
}

if (!function_exists('medacapTopbarRenderHeader')) {
    /**
     * Rendu complet #kt_header (navbar measure).
     *
     * @param array $options
     */
    function medacapTopbarRenderHeader($options = array())
    {
        $o = medacapTopbarNormalizeOptions($options);
        $lang = ($o['lang'] === 'EN') ? 'EN' : 'FR';
        $assetPrefix = rtrim($o['assetPrefix'], '/');
        $displayModule = trim((string) $o['displayModule']);
        $requestUri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';

        medacapTopbarIncludeAssets($assetPrefix);

        echo '<div style="background:#e6e6e6;" id="kt_header" class="header" data-kt-sticky="true" data-kt-sticky-name="header" data-kt-sticky-offset="{default: \'200px\', lg: \'300px\'}">';
        echo '<div class="container-fluid d-flex align-items-center justify-content-between position-relative">';

        medacapTopbarRenderLogoBar($o);
        medacapTopbarRenderModuleTitle($displayModule);
        medacapTopbarRenderTopbarRight($o, $lang, $requestUri);

        echo '</div>';
        echo '</div>';
    }
}

if (!function_exists('medacapTopbarRenderLogoBar')) {
    function medacapTopbarRenderLogoBar($o)
    {
        $mobileLogoHref = htmlspecialchars((string) $o['mobileLogoHref'], ENT_QUOTES, 'UTF-8');
        $logoSrc = htmlspecialchars(rtrim($o['assetPrefix'], '/') . '/images/logo.png', ENT_QUOTES, 'UTF-8');

        echo '<div class="d-flex align-items-center flex-grow-1 flex-lg-grow-0">';
        echo '<div class="d-flex align-items-center d-lg-none">';
        echo '<div class="btn btn-icon btn-active-color-primary ms-n2 me-1" id="kt_aside_toggle">';
        echo '<i class="ki-duotone ki-abstract-14 fs-1"><span class="path1"></span><span class="path2"></span></i>';
        echo '</div></div>';
        echo '<a href="' . $mobileLogoHref . '" class="d-lg-none">';
        echo '<img alt="Logo" src="' . $logoSrc . '" class="mh-40px" />';
        echo '</a>';
        echo '<div class="btn btn-icon w-auto ps-0 btn-active-color-danger d-none d-lg-inline-flex me-2 me-lg-5" data-kt-toggle="true" data-kt-toggle-state="active" data-kt-toggle-target="body" data-kt-toggle-name="aside-minimize">';
        echo '<i class="ki-duotone ki-black-left-line fs-1 rotate-180"><span class="path1"></span><span class="path2"></span></i>';
        echo '</div>';
        echo '</div>';
    }
}

if (!function_exists('medacapTopbarRenderModuleTitle')) {
    function medacapTopbarRenderModuleTitle($displayModule)
    {
        if ($displayModule === '') {
            return;
        }

        echo '<div class="header-module-title-wrap">';
        echo '<section class="title-banner">';
        echo '<span class="header-status-dot me-2"></span>';
        echo '<h1 class="text-uppercase fw-bold mb-0">' . htmlspecialchars($displayModule, ENT_QUOTES, 'UTF-8') . '</h1>';
        echo '</section>';
        echo '</div>';
    }
}

if (!function_exists('medacapTopbarRenderTopbarRight')) {
    function medacapTopbarRenderTopbarRight($o, $lang, $requestUri)
    {
        $assetPrefix = rtrim($o['assetPrefix'], '/');
        $firstName = isset($_SESSION['firstName']) ? (string) $_SESSION['firstName'] : '';
        $lastName = isset($_SESSION['lastName']) ? (string) $_SESSION['lastName'] : '';
        $fullName = trim($firstName . ' ' . $lastName);

        echo '<div class="d-flex align-items-center justify-content-end flex-lg-grow-1 h-100">';
        echo '<div class="d-flex align-items-center flex-shrink-0 ms-auto position-relative h-100" style="z-index: 2;">';

        echo '<div class="d-flex align-items-center ms-2 ms-lg-3 h-100">';
        echo '<form method="get" action="' . htmlspecialchars($requestUri, ENT_QUOTES, 'UTF-8') . '" class="d-flex align-items-center m-0 p-0">';
        echo '<div class="lang-segmented-control">';
        echo '<button type="submit" name="lang" value="FR" class="lang-segment-btn' . ($lang === 'FR' ? ' is-active' : '') . '" title="Passer en français">';
        echo '<img src="' . htmlspecialchars($assetPrefix . '/assets/media/flags/france.svg', ENT_QUOTES, 'UTF-8') . '" alt="FR" class="lang-flag" /><span>FR</span>';
        echo '</button>';
        echo '<button type="submit" name="lang" value="EN" class="lang-segment-btn' . ($lang === 'EN' ? ' is-active' : '') . '" title="Switch to English">';
        echo '<img src="' . htmlspecialchars($assetPrefix . '/assets/media/flags/united-states.svg', ENT_QUOTES, 'UTF-8') . '" alt="EN" class="lang-flag" /><span>EN</span>';
        echo '</button>';
        echo '</div></form></div>';

        medacapTopbarRenderUserMenu($o, $fullName);

        echo '</div></div>';
    }
}

if (!function_exists('medacapTopbarRenderUserMenu')) {
    function medacapTopbarRenderUserMenu($o, $fullName)
    {
        global $salut, $my_info, $deconnexion, $langue, $francais;

        $avatarSrc = htmlspecialchars((string) $o['avatarSrc'], ENT_QUOTES, 'UTF-8');
        $profileHref = htmlspecialchars((string) $o['profileHref'], ENT_QUOTES, 'UTF-8');
        $portalHref = htmlspecialchars((string) $o['portalHref'], ENT_QUOTES, 'UTF-8');
        $logoutHref = htmlspecialchars((string) $o['logoutHref'], ENT_QUOTES, 'UTF-8');
        $logoutRedirect = htmlspecialchars((string) $o['logoutRedirect'], ENT_QUOTES, 'UTF-8');
        $assetPrefix = rtrim($o['assetPrefix'], '/');
        $flagFr = htmlspecialchars($assetPrefix . '/assets/media/flags/france.svg', ENT_QUOTES, 'UTF-8');

        $salutLabel = isset($salut) ? $salut : 'Bonjour';
        $myInfoLabel = isset($my_info) ? $my_info : 'Mes informations';
        $logoutLabel = isset($deconnexion) ? $deconnexion : 'Déconnexion';
        $langueLabel = isset($langue) ? $langue : 'Langue';
        $francaisLabel = isset($francais) ? $francais : 'Français';

        $sessionFirst = isset($_SESSION['firstName']) ? (string) $_SESSION['firstName'] : '';
        $sessionLast = isset($_SESSION['lastName']) ? (string) $_SESSION['lastName'] : '';
        $sessionEmail = isset($_SESSION['email']) ? (string) $_SESSION['email'] : '';
        $sessionProfile = isset($_SESSION['profile']) ? (string) $_SESSION['profile'] : '';

        echo '<div class="d-flex align-items-center ms-2 ms-lg-3" id="kt_header_user_menu_toggle">';
        echo '<div class="user-chip-btn cursor-pointer d-flex align-items-center" data-kt-menu-trigger="{default: \'click\', lg: \'hover\'}" data-kt-menu-attach="parent" data-kt-menu-placement="bottom-end">';
        echo '<div class="user-chip-avatar me-2"><img alt="Pic" src="' . $avatarSrc . '" /><span class="user-online-badge"></span></div>';
        echo '<span class="user-chip-name fw-semibold fs-7 me-1">' . htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') . '</span>';
        echo '<i class="ki-duotone ki-down fs-8 text-gray-500 ms-1"><span class="path1"></span><span class="path2"></span></i>';
        echo '</div>';

        echo '<div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg menu-state-color fw-semibold py-4 fs-6 w-275px" data-kt-menu="true">';
        echo '<div class="menu-item px-3"><a href="' . $profileHref . '"><div class="menu-content d-flex align-items-center px-3">';
        echo '<div class="symbol symbol-50px me-5"><img alt="Logo" src="' . $avatarSrc . '" style="max-width:100%;height:auto;" /></div>';
        echo '<span><div class="d-flex flex-column">';
        echo '<div class="fw-bolder d-flex align-items-center text-black fs-5">' . htmlspecialchars($salutLabel, ENT_QUOTES, 'UTF-8') . ', ' . htmlspecialchars($sessionFirst, ENT_QUOTES, 'UTF-8');
        echo ' <span class="badge badge-light-success fw-bold fs-8 px-2 py-1 ms-2">' . htmlspecialchars($sessionProfile, ENT_QUOTES, 'UTF-8') . '</span></div>';
        echo '<div class="fw-bold text-black text-hover-primary fs-7">' . htmlspecialchars($sessionEmail, ENT_QUOTES, 'UTF-8') . '</div>';
        echo '</div></span></div></a></div>';
        echo '<div class="separator my-2"></div>';
        echo '<div class="menu-item px-5"><a href="' . $profileHref . '" class="menu-link px-5">' . htmlspecialchars($myInfoLabel, ENT_QUOTES, 'UTF-8') . '</a></div>';
        echo '<div class="separator my-2"></div>';
        echo '<div class="menu-item px-5" data-kt-menu-trigger="{default: \'click\', lg: \'hover\'}" data-kt-menu-placement="left-start" data-kt-menu-offset="-15px, 0">';
        echo '<a href="#" class="menu-link px-5"><span class="menu-title position-relative">' . htmlspecialchars($langueLabel, ENT_QUOTES, 'UTF-8');
        echo '<span class="fs-8 rounded bg-light px-3 py-2 position-absolute translate-middle-y top-50 end-0">' . htmlspecialchars($francaisLabel, ENT_QUOTES, 'UTF-8');
        echo ' <img class="w-15px h-15px rounded-1 ms-2" src="' . $flagFr . '" alt="" /></span></span></a>';
        echo '<div class="menu-sub menu-sub-dropdown w-175px py-4">';
        echo '<div class="menu-item px-3"><a href="#" class="menu-link d-flex px-5"><span class="symbol symbol-20px me-4">';
        echo '<img class="rounded-1" src="' . $flagFr . '" alt="" /></span>' . htmlspecialchars($francaisLabel, ENT_QUOTES, 'UTF-8') . '</a></div>';
        echo '</div></div>';
        echo '<div class="menu-item px-5"><a href="' . $portalHref . '" class="menu-link px-5">Portail MEDACAP</a></div>';
        echo '<div class="menu-item px-5"><a href="' . $logoutHref . '" id="medacap-logout-button" class="menu-link px-5" data-logout-redirect="' . $logoutRedirect . '">' . htmlspecialchars($logoutLabel, ENT_QUOTES, 'UTF-8') . '</a></div>';
        echo '</div></div>';
    }
}
