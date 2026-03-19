<?php
/**
 * Background Manager - Centralised background management for MEDACAP
 * 
 * Usage:
 * 1. Include this file at the beginning of your view file
 * 2. Call setPageBackground() with the desired background class or custom URL
 * 3. Open the background container with openBackgroundContainer()
 * 4. Add your page content
 * 5. Close the background container with closeBackgroundContainer()
 */

// Ensure we have the background stylesheet
if (!function_exists('includeBackgroundManagerCSS')) {
    function includeBackgroundManagerCSS() {
        static $included = false;
        if (!$included) {
            echo '<link rel="stylesheet" href="../../public/css/background-manager.css">';
            $included = true;
        }
    }
}

// Set the background for the page
if (!function_exists('setPageBackground')) {
    function setPageBackground($backgroundClass = 'bg-welcome-tech', $useGradient = true) {
        global $bgClass, $bgGradient;
        $bgClass = $backgroundClass;
        $bgGradient = $useGradient;
        
        // Include the CSS
        includeBackgroundManagerCSS();
    }
}

// Open the background container
if (!function_exists('openBackgroundContainer')) {
    function openBackgroundContainer($additionalContainerClasses = '', $additionalContainerAttributes = '') {
        global $bgClass, $bgGradient;
        
        // Default values if not set
        if (!isset($bgClass)) {
            $bgClass = 'bg-welcome-tech';
        }
        if (!isset($bgGradient)) {
            $bgGradient = true;
        }
        
        $gradientClass = $bgGradient ? 'with-gradient' : 'without-gradient';
        
        echo '<div class="content fs-6 d-flex flex-column flex-column-fluid page-background-container ' . $additionalContainerClasses . '" ' . $additionalContainerAttributes . '>';
        echo '<div class="page-background-overlay ' . $gradientClass . ' ' . $bgClass . '"></div>';
        echo '<div class="page-content-container">';
    }
}

// Close the background container
if (!function_exists('closeBackgroundContainer')) {
    function closeBackgroundContainer() {
        echo '</div>'; // Close page-content-container
        echo '</div>'; // Close page-background-container
    }
}