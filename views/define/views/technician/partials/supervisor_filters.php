<?php
/**
 * Filtres agence / collaborateur pour la vue superviseur (DPS).
 */
require_once __DIR__ . '/../../../components/filterAgency.php';

$pageBase = $supervisorPage ?? './collaboratorDashboard';
$lvl = $levelFilter ?? ($_GET['level'] ?? 'all');
$brd = $filterBrand ?? ($_GET['brand'] ?? 'all');
?>
<div class="card mb-5" style="background: rgba(255,255,255,0.85); backdrop-filter: blur(10px); border-radius: 12px;">
    <div class="card-body py-4">
        <form id="supervisorFilterForm" method="get" action="<?= htmlspecialchars($pageBase) ?>" class="row g-3 align-items-end">
            <input type="hidden" name="level" value="<?= htmlspecialchars($lvl) ?>">
            <input type="hidden" name="brand" value="<?= htmlspecialchars($brd) ?>">
            <div class="col-md-4">
                <?php renderFilterAgency($selectedAgency ?? 'all', $agencyOptions ?? [], true); ?>
            </div>
            <div class="col-md-8">
                <label for="filterTechnician" class="form-label">Collaborateur</label>
                <select id="filterTechnician" name="technicianId" class="form-select" aria-label="Filtrer par collaborateur">
                    <option value="all" <?= (($selectedTechnicianId ?? 'all') === 'all') ? 'selected' : '' ?>>Tous les collaborateurs</option>
                    <?php foreach ($technicianChoices ?? [] as $id => $name): ?>
                        <option value="<?= htmlspecialchars((string) $id) ?>" <?= ((string) ($selectedTechnicianId ?? 'all') === (string) $id) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>
