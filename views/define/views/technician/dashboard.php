<!--
Vue du dashboard pour les techniciens
Cette vue est incluse par TechnicianDashboardController
-->

<?php
// Ensure the currentPage is set before including the header
$GLOBALS['currentPage'] = !empty($supervisorView) ? 'collaboratorDashboard' : 'dashboard';
require_once __DIR__ . '/../../partials/header.php';

// Helpers & widgets (matching DPS style)
require_once __DIR__ . "/../../components/helpers/helpers.php";
require_once __DIR__ . '/../../components/widgets/card-level.php';
require_once __DIR__ . '/../../components/widgets/title-main.php';
require_once __DIR__ . '/../../components/widgets/title-section.php';
require_once __DIR__ . '/../../components/widgets/histogram-row.php';
?>

<style>
    body {
        background: linear-gradient(rgba(0,0,0,0.2), rgba(0,0,0,0.1)), url('/MEDACAP/public/images/welcome_tech.png') no-repeat center center fixed !important;
        background-size: cover !important;
    }
    #kt_content {
        background: transparent !important;
    }
    .wrapper {
        background: transparent !important;
    }
</style>

<div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content" style="position: relative; background: none;">

    <!-- Overlay chargement -->
    <div id="loadingOverlay" class="position-fixed top-0 start-0 w-100 h-100 d-none"
         style="background-color: rgba(27, 24, 24, 0.85); z-index: 9999;">
        <div class="d-flex justify-content-center align-items-center h-100">
            <div class="spinner-grow text-primary" role="status" style="width:3rem; height:3rem;">
                <span class="visually-hidden">Chargement...</span>
            </div>
        </div>
    </div>

    <div class="container-fluid mt-4 px-4">
        <?php if (!empty($supervisorView)): ?>
            <?php include __DIR__ . '/partials/supervisor_filters.php'; ?>
        <?php endif; ?>
        <?php include __DIR__ . '/dashboard_content.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
