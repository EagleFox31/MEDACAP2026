<?php
/**
 * Vue DPS : dashboard technicien avec filtres agence / collaborateur (AJAX).
 * ?embed=1 — contenu seul, sans header/sidebar/footer MEDACAP (intégration portail).
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id']) || !isset($_SESSION['profile'])) {
    header('Location: ../../');
    exit();
}

$allowedProfiles = [
    'Directeur Pièce et Service',
    'Directeur des Opérations',
    'Super Admin',
    'Admin',
];

$profile = $_SESSION['profile'] ?? '';
if (!in_array($profile, $allowedProfiles, true)) {
    header('Location: ./dashboard');
    exit();
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/core/portal-mongo.php';
require_once __DIR__ . '/includes/collaborator_dashboard_lib.php';

try {
    $academy = definePortalMongoDatabase();
    $GLOBALS['defineAcademy'] = $academy;
} catch (Exception $e) {
    error_log('collaboratorDashboard MongoDB: ' . $e->getMessage());
    die('Erreur de connexion à la base de données.');
}

$ctx = collaboratorDashboardResolveContext($academy);
extract($ctx, EXTR_SKIP);

$GLOBALS['defineEmbed'] = $isEmbed = isset($_GET['embed']) && in_array((string) $_GET['embed'], ['1', 'true'], true);
$isPartial = isset($_GET['partial']) && $_GET['partial'] === '1';

if ($isPartial) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'html' => collaboratorDashboardRenderPane($academy, $ctx),
        'technicians' => collaboratorDashboardTechniciansForJson($technicians),
        'technicianId' => $technicianId,
        'agency' => $selectedAgency,
        'title' => collaboratorDashboardDocumentTitle($ctx) . ' | CFAO Mobility Academy',
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if ($isEmbed) {
    $GLOBALS['pageTitle'] = collaboratorDashboardDocumentTitle($ctx);
    require __DIR__ . '/partials/define_embed_head.php';
    require __DIR__ . '/partials/define_embed_close.php';
} else {
    $GLOBALS['currentPage'] = 'collaboratorDashboard';
    require_once __DIR__ . '/partials/header.php';
}
?>
<style>
    body {
        background: linear-gradient(rgba(0,0,0,0.2), rgba(0,0,0,0.1)), url('/MEDACAP/public/images/welcome_tech.png') no-repeat center center fixed !important;
        background-size: cover !important;
    }
    #kt_content { background: transparent !important; }
    .wrapper { background: transparent !important; }
    #supervisor-dashboard-loading {
        min-height: 120px;
    }
    .medacap-level-badge {
        display: inline-block;
        vertical-align: middle;
        margin-left: 0.65rem;
        font-size: 11px;
        font-weight: 700;
        padding: 4px 12px;
        border-radius: 999px;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        background: #fff;
        line-height: 1.2;
    }
    .medacap-level-badge.medacap-tier-junior {
        color: #1a56db;
        box-shadow: inset 0 0 0 1.5px #1a56db;
    }
    .medacap-level-badge.medacap-tier-senior {
        color: #e67e00;
        box-shadow: inset 0 0 0 1.5px #e67e00;
    }
    .medacap-level-badge.medacap-tier-expert {
        color: #1aab5b;
        box-shadow: inset 0 0 0 1.5px #1aab5b;
    }
</style>

<div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content">
    <div class="container-fluid mt-4 px-4">
        <?php if (!$isEmbed): ?>
            <?php include __DIR__ . '/views/technician/partials/supervisor_filters.php'; ?>
        <?php else: ?>
            <?php include __DIR__ . '/partials/embed_plan_filters.php'; ?>
        <?php endif; ?>

        <div id="supervisor-dashboard-loading" class="d-none text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
        </div>

        <div id="supervisor-dashboard-pane"
             data-endpoint="./collaboratorDashboard"
             aria-live="polite">
            <?php include __DIR__ . '/views/technician/partials/collaborator_dashboard_pane.php'; ?>
        </div>
    </div>
</div>

<script src="/MEDACAP/public/assets/js/define/collaborator-dashboard.js"></script>
<?php
if ($isEmbed) {
    require __DIR__ . '/partials/define_embed_foot.php';
} else {
    require_once __DIR__ . '/partials/footer.php';
}
