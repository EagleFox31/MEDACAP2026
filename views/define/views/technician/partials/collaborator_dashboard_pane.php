<?php
/** @var MongoDB\Database $academy */
/** @var array $filters */
/** @var array $technicians */
/** @var string $technicianId */
/** @var string $selectedAgency */

if (empty($technicians)) {
    ?>
    <div class="alert alert-warning mt-2">
        Aucun collaborateur trouvé dans votre périmètre pour les filtres sélectionnés.
    </div>
    <?php
    return;
}

if ($technicianId === 'all') {
    $agencyLabel = $selectedAgency !== 'all'
        ? ' — agence ' . htmlspecialchars($selectedAgency)
        : '';
    ?>
    <div class="alert alert-info mt-2 mb-4">
        Sélectionnez un collaborateur pour consulter son plan de formation individuel.
    </div>
    <p class="text-muted mb-3"><?= count($technicians) ?> collaborateur(s) dans le périmètre<?= $agencyLabel ?>.</p>
    <?php
    return;
}

try {
    $techController = new TechnicianDashboardController($academy, $technicianId);
    $techController->renderDashboardContent($filters, [
        'supervisorView' => true,
        'supervisorPage' => $supervisorPage ?? './collaboratorDashboard',
        'technicianChoices' => $technicianChoices ?? $technicians,
        'agencyOptions' => $agencyOptions ?? [],
        'selectedAgency' => $selectedAgency ?? 'all',
        'selectedTechnicianId' => $technicianId,
    ]);
} catch (Exception $e) {
    error_log('collaborator_dashboard_pane: ' . $e->getMessage());
    ?>
    <div class="alert alert-danger mt-2">Erreur lors du chargement du tableau de bord collaborateur.</div>
    <?php
}
