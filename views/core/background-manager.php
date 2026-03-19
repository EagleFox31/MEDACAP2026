<?php
/**
 * Background Manager — MEDACAP
 * Gestion centralisée des backgrounds de page.
 *
 * Usage :
 * 1. include_once "../core/background-manager.php"  (depuis views/[module]/)
 * 2. setPageBackground("bg-welcome-tech")
 * 3. openBackgroundContainer()
 * 4. ... contenu de la page ...
 * 5. closeBackgroundContainer()
 *
 * Classes disponibles :
 * - bg-welcome-tech  → welcome_tech.png
 * - bg-dashboard     → tof_dashboard_tech.png
 * - bg-welcome       → welcome.jpg
 */

if (!function_exists('includeBackgroundManagerCSS')) {
    function includeBackgroundManagerCSS() {
        static $included = false;
        if (!$included) {
            echo '<link rel="stylesheet" href="/MEDACAP/public/css/background-manager.css">';
            $included = true;
        }
    }
}

if (!function_exists('setPageBackground')) {
    function setPageBackground($backgroundClass = 'bg-welcome-tech', $useGradient = true) {
        global $bgClass, $bgGradient;
        $bgClass     = $backgroundClass;
        $bgGradient  = $useGradient;
        includeBackgroundManagerCSS();
    }
}

if (!function_exists('openBackgroundContainer')) {
    function openBackgroundContainer($additionalClasses = '', $additionalAttributes = '') {
        global $bgClass, $bgGradient;
        if (!isset($bgClass))    $bgClass    = 'bg-welcome-tech';
        if (!isset($bgGradient)) $bgGradient = true;
        $gradientClass = $bgGradient ? 'with-gradient' : 'without-gradient';
        echo '<div class="content fs-6 d-flex flex-column flex-column-fluid page-background-container ' . $additionalClasses . '" ' . $additionalAttributes . '>';
        echo '<div class="page-background-overlay ' . $gradientClass . ' ' . $bgClass . '"></div>';
        echo '<div class="page-content-container">';
    }
}

if (!function_exists('closeBackgroundContainer')) {
    function closeBackgroundContainer() {
        echo '</div>'; // close page-content-container
        echo '</div>'; // close page-background-container
    }
}
?>
