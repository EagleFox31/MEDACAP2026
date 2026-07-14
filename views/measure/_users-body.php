<title><?= htmlspecialchars(isset($modesDisponibles[$modeActuel]) ? $modesDisponibles[$modeActuel] : 'Gestion Utilisateurs') ?> | CFAO Mobility Academy</title>
<link rel="stylesheet" href="../../public/css/measure/user-list.css">

<script>(function(){var e=document.getElementById('spinner');if(e){e.classList.remove('show');e.style.display='none';}document.body.classList.remove('loading-active');})();</script>

<?php openBackgroundContainer(); ?>

<div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
    <div class="container-xxl">
        <div class="row g-3 g-xl-6 mb-3 mb-xl-6">

            <?php renderTitleMain('ki-duotone ki-people fs-2', 3, isset($modesDisponibles[$modeActuel]) ? $modesDisponibles[$modeActuel] : 'Gestion Utilisateurs'); ?>

            <!-- Filtres + Export -->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="card glassmorphism filter-card">
                        <form method="GET" action="" id="filters-form">
                            <input type="hidden" name="mode" value="<?= htmlspecialchars($modeActuel) ?>">
                            <?php if ($manager_id): ?><input type="hidden" name="manager_id" value="<?= htmlspecialchars($manager_id) ?>"><?php endif; ?>
                            <div id="users-filter-box" class="d-flex flex-wrap gap-3 w-100 align-items-end justify-content-center" style="position:relative;" data-ajax-filters="true">
                                <?php foreach ($filters as $filter) { renderFilterSelectAjax($filter); } ?>
                                <div style="position:absolute; right:0; bottom:1px;">
                                    <button type="button" id="export-excel"
                                            class="btn btn-icon btn-light-danger"
                                            title="<?= isset($export_to_excel) ? htmlspecialchars($export_to_excel) : 'Exporter Excel' ?>"
                                            data-ignore='<?= htmlspecialchars(json_encode($columnsToIgnore)) ?>'>
                                        <i class="ki-duotone ki-exit-up fs-2"><span class="path1"></span><span class="path2"></span></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Alertes -->
            <?php if ($success_msg): ?>
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="alert alert-success alert-dismissible fade show glassmorphism" role="alert">
                            <strong class="text-center d-block"><?= htmlspecialchars($success_msg) ?></strong>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="alert alert-danger alert-dismissible fade show glassmorphism" role="alert">
                            <strong class="text-center d-block"><?= htmlspecialchars($error_msg) ?></strong>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Tableau -->
            <div class="row">
                <div class="col-12">
                    <div class="card glassmorphism">
                        <div class="card-body p-0">
                            <div id="users-container" class="table-responsive">
                                <?php
                                $subRowConfig = ($modeActuel === 'tous') ? 'collaborators' : false;
                                echo renderTableComponent($headers, $rows, $subRowConfig);
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Modales (mode collaborateurs) -->
<?php if ($modeActuel === 'collaborateurs' && !empty($usersData)): ?>
    <?php foreach ($usersData as $user): ?>
        <?php $uid = (string)$user->_id; ?>

        <!-- Modal suppression -->
        <div class="modal fade" id="modal_del_<?= $uid ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered mw-450px">
                <div class="modal-content glass-effect">
                    <form method="POST">
                        <input type="hidden" name="userID" value="<?= $uid ?>">
                        <div class="modal-header">
                            <h2 class="fs-2 fw-bolder"><?= isset($delet) ? $delet : 'Supprimer' ?></h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body py-10 px-lg-17">
                            <h4><?= isset($delet_text) ? $delet_text : 'Confirmer la suppression ?' ?></h4>
                        </div>
                        <div class="modal-footer flex-center">
                            <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal"><?= isset($non) ? $non : 'Non' ?></button>
                            <button type="submit" name="delete" class="btn btn-danger"><?= isset($oui) ? $oui : 'Oui' ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal édition -->
        <div class="modal fade" id="modal_edit_<?= $uid ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered mw-650px">
                <div class="modal-content glass-effect">
                    <form method="POST">
                        <input type="hidden" name="userID" value="<?= $uid ?>">
                        <div class="modal-header">
                            <h2 class="fs-2 fw-bolder"><?= isset($editer_data) ? $editer_data : 'Éditer' ?></h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body py-10 px-lg-17">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label"><?= isset($prenomsNoms) ? $prenomsNoms : 'Prénom' ?></label>
                                    <input type="text" name="firstName" class="form-control" value="<?= htmlspecialchars($user->firstName ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Nom</label>
                                    <input type="text" name="lastName" class="form-control" value="<?= htmlspecialchars($user->lastName ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><?= isset($email) ? $email : 'Email' ?></label>
                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user->email ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><?= isset($phoneNumber) ? $phoneNumber : 'Téléphone' ?></label>
                                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user->phone ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><?= isset($levelTech) ? $levelTech : 'Niveau' ?></label>
                                    <select name="level" class="form-select">
                                        <?php foreach (['Junior','Senior','Expert'] as $lvl): ?>
                                            <option value="<?= $lvl ?>" <?= ($user->level ?? '') === $lvl ? 'selected' : '' ?>><?= $lvl ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><?= isset($department) ? $department : 'Département' ?></label>
                                    <input type="text" name="department" class="form-control" value="<?= htmlspecialchars($user->department ?? '') ?>">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer flex-center">
                            <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal"><?= isset($annuler) ? $annuler : 'Annuler' ?></button>
                            <button type="submit" name="update" class="btn btn-primary"><?= isset($valider) ? $valider : 'Valider' ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php closeBackgroundContainer(); ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    // Export Excel
    var exportBtn = document.getElementById('export-excel');
    if (exportBtn) {
        exportBtn.addEventListener('click', function () {
            if (typeof XLSX === 'undefined') { alert('Bibliothèque XLSX non chargée'); return; }
            var ignore   = JSON.parse(this.dataset.ignore || '[]');
            var headers  = <?= json_encode(array_values($headers)) ?>;
            var rows     = <?= json_encode(array_values($rows)) ?>;
            var filtered = headers.filter(function (h) { return !ignore.includes(h.key); });
            var data     = [filtered.map(function (h) { return h.label; })];
            rows.forEach(function (row) {
                data.push(filtered.map(function (h) {
                    var val = row[h.key] != null ? String(row[h.key]) : '';
                    if (val.includes('<')) {
                        var d = document.createElement('div');
                        d.innerHTML = val;
                        val = d.textContent || d.innerText || '';
                    }
                    return val;
                }));
            });
            var wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(data), 'Utilisateurs');
            XLSX.writeFile(wb, 'utilisateurs.xlsx');
        });
    }

    // Auto-fermeture des alertes après 5s
    setTimeout(function () {
        document.querySelectorAll('.alert').forEach(function (a) { a.remove(); });
    }, 5000);
    document.querySelectorAll('.alert .btn-close').forEach(function (btn) {
        btn.addEventListener('click', function () { this.closest('.alert').remove(); });
    });

});
</script>
