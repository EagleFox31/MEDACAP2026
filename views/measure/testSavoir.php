<?php
session_start();
include_once "../language.php";

if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
} else {
?>
<?php
require_once "../../vendor/autoload.php";

// Connexion Mongo
$conn      = new MongoDB\Client("mongodb://localhost:27017");
$academy   = $conn->academy;
$users     = $academy->users;
$allocations = $academy->allocations;
$exams     = $academy->exams;

/** ========================= Helpers ========================= */

/**
 * Retourne l'allocation active pour (user,type,level).
 * Si non trouvée au level demandé, on tente un fallback sur 'Mixed'
 * (utile pour les profils Candidat où on n’alloue que Factuel/Mixed).
 */
function getActiveAllocationWithFallback($allocations, string $userId, string $type, string $level) {
    $userOid = new MongoDB\BSON\ObjectId($userId);

    // 1) Essaye le niveau demandé
    $alloc = $allocations->findOne([
        'user'       => $userOid,
        'type'       => $type,
        'level'      => $level,
        'activeTest' => true,
    ]);
    if ($alloc) return $alloc;

    // 2) Fallback sur Mixed (si différent)
    if ($level !== 'Mixed') {
        $alloc = $allocations->findOne([
            'user'       => $userOid,
            'type'       => $type,
            'level'      => 'Mixed',
            'activeTest' => true,
        ]);
        if ($alloc) return $alloc;
    }

    return null;
}

/** Charge l’exam actif correspondant à une allocation (si présente). */
function getActiveExamForAllocation($exams, string $userId, $allocationOrNull) {
    if (!$allocationOrNull) return null;
    if (!isset($allocationOrNull['test'])) return null;
    if (!($allocationOrNull['test'] instanceof MongoDB\BSON\ObjectId)) return null;

    return $exams->findOne([
        'user'   => new MongoDB\BSON\ObjectId($userId),
        'test'   => $allocationOrNull['test'],
        'active' => true,
    ]);
}

/** Première allocation non nulle parmi 3, sinon null. */
function firstNonNull(...$items) {
    foreach ($items as $it) if ($it) return $it;
    return null;
}
?>
<?php include_once "partials/header.php"; ?>
<!--begin::Title-->
<title><?php echo $list_test_qcm ?> | CFAO Mobility Academy</title>
<!--end::Title-->

<?php include_once "../partials/background-manager.php"; ?>
<?php setPageBackground("bg-dashboard"); ?>

<!--begin::Content-->
<?php openBackgroundContainer(); ?>
    <!-- Main Title Card -->
    <div class="container-xxl">
       <div class="card shadow-sm mb-5">
            <div class="card-body p-4">
                <h1 class="text-dark fw-bold text-center fs-1">
                    <i class="fas fa-book fs-2 me-3"></i>
                    <?php echo $list_test_qcm ?>
                </h1>
            </div>
        </div>
    </div>

    <!--begin::Post-->
    <div class="post fs-6 d-flex flex-column-fluid" id="kt_post" data-select2-id="select2-data-kt_post">
        <!--begin::Container-->
        <div class="container-xxl" data-select2-id="select2-data-194-27hh">
            <!--begin::Card-->
            <div class="card glass-effect depth-effect mb-5">
                <div class="card-body pt-0">
                    <!--begin::Table-->
                    <div id="kt_customers_table_wrapper" class="dataTables_wrapper dt-bootstrap4 no-footer">
                        <div class="table-responsive">
                            <table class="table align-middle table-row-dashed fs-6 gy-5 dataTable no-footer" id="kt_customers_table">
                                <thead>
                                    <tr class="text-start text-black fw-bold fs-7 text-uppercase gs-0">
                                        <th class="min-w-200px text-center" style="width:125px;"><?php echo $test ?></th>
                                        <th class="min-w-150px text-center" style="width:155px;"><?php echo $level ?></th>
                                        <th class="min-w-150px text-center" style="width:155px;"><?php echo $etat ?></th>
                                    </tr>
                                </thead>
                                <tbody class="fw-semibold text-gray-600" id="table">
                                    <?php
                                    $userId = $_SESSION["id"];

                                    // Récupère les allocations actives (avec fallback sur Mixed)
                                    $allocateFacJu = getActiveAllocationWithFallback($allocations, $userId, 'Factuel', 'Junior');
                                    $allocateFacSe = getActiveAllocationWithFallback($allocations, $userId, 'Factuel', 'Senior');
                                    $allocateFacEx = getActiveAllocationWithFallback($allocations, $userId, 'Factuel', 'Expert');

                                    // Récupère les exams actifs correspondants (si alloc existe)
                                    $examJuFac = getActiveExamForAllocation($exams, $userId, $allocateFacJu);
                                    $examSeFac = getActiveExamForAllocation($exams, $userId, $allocateFacSe);
                                    $examExFac = getActiveExamForAllocation($exams, $userId, $allocateFacEx);

                                    // Si une allocation est de niveau Mixed, on affiche UNE seule ligne "Mixte"
                                    $hasMixed =
                                        ($allocateFacJu && (($allocateFacJu['level'] ?? '') === 'Mixed')) ||
                                        ($allocateFacSe && (($allocateFacSe['level'] ?? '') === 'Mixed')) ||
                                        ($allocateFacEx && (($allocateFacEx['level'] ?? '') === 'Mixed'));

                                    if ($hasMixed) {
                                        $alloc = firstNonNull($allocateFacJu, $allocateFacSe, $allocateFacEx);
                                        $exam  = firstNonNull($examJuFac, $examSeFac, $examExFac);
                                        ?>
                                        <tr class="odd">
                                            <td class="text-center"><?php echo $connaissances; ?></td>
                                            <td class="text-center">Mixte</td>
                                            <td class="text-center">
                                                <?php if ($alloc): ?>
                                                    <?php if ($exam): ?>
                                                        <a href="./userQuizFactuel?test=<?php echo htmlspecialchars((string)$alloc['test'], ENT_QUOTES, 'UTF-8'); ?>&level=<?php echo htmlspecialchars($alloc['level'] ?? 'Mixed', ENT_QUOTES, 'UTF-8'); ?>&id=<?php echo htmlspecialchars($userId, ENT_QUOTES, 'UTF-8'); ?>"
                                                           class="btn btn-light btn-active-light-primary text-primary fw-bolder btn-sm"
                                                           title="Cliquez ici pour ouvrir le test">
                                                           <?php echo $en_cours; ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <?php if ((isset($alloc['active']) && $alloc['active'] === false)): ?>
                                                            <a href="./userQuizFactuel?test=<?php echo htmlspecialchars((string)$alloc['test'], ENT_QUOTES, 'UTF-8'); ?>&level=<?php echo htmlspecialchars($alloc['level'] ?? 'Mixed', ENT_QUOTES, 'UTF-8'); ?>&id=<?php echo htmlspecialchars($userId, ENT_QUOTES, 'UTF-8'); ?>"
                                                               class="btn btn-light btn-active-light-primary text-primary fw-bolder btn-sm"
                                                               title="Cliquez ici pour ouvrir le test">
                                                               <?php echo $completer_par_vous; ?>
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="badge badge-light-success fs-7 m-1"><?php echo $effectue; ?></span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="badge badge-light-danger fs-7 m-1"><?php echo $non_disponible; ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php
                                    } else {
                                        // Sinon, on affiche J/S/E individuellement (comme avant), mais de façon sécure
                                        ?>
                                        <tr class="odd">
                                            <td class="text-center"><?php echo $connaissances; ?></td>
                                            <td class="text-center"><?php echo $junior; ?></td>
                                            <td class="text-center">
                                                <?php if ($allocateFacJu): ?>
                                                    <?php if ($examJuFac): ?>
                                                        <a href="./userQuizFactuel?test=<?php echo htmlspecialchars((string)$allocateFacJu['test'], ENT_QUOTES, 'UTF-8'); ?>&level=<?php echo htmlspecialchars($allocateFacJu['level'] ?? 'Junior', ENT_QUOTES, 'UTF-8'); ?>&id=<?php echo htmlspecialchars($userId, ENT_QUOTES, 'UTF-8'); ?>"
                                                           class="btn btn-light btn-active-light-primary text-primary fw-bolder btn-sm"
                                                           title="Cliquez ici pour ouvrir le test">
                                                           <?php echo $en_cours; ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <?php if ((isset($allocateFacJu['active']) && $allocateFacJu['active'] === false)): ?>
                                                            <a href="./userQuizFactuel?test=<?php echo htmlspecialchars((string)$allocateFacJu['test'], ENT_QUOTES, 'UTF-8'); ?>&level=<?php echo htmlspecialchars($allocateFacJu['level'] ?? 'Junior', ENT_QUOTES, 'UTF-8'); ?>&id=<?php echo htmlspecialchars($userId, ENT_QUOTES, 'UTF-8'); ?>"
                                                               class="btn btn-light btn-active-light-primary text-primary fw-bolder btn-sm"
                                                               title="Cliquez ici pour ouvrir le test">
                                                               <?php echo $completer_par_vous; ?>
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="badge badge-light-success fs-7 m-1"><?php echo $effectue; ?></span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="badge badge-light-danger fs-7 m-1"><?php echo $non_disponible; ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>

                                        <tr class="odd">
                                            <td class="text-center"><?php echo $connaissances; ?></td>
                                            <td class="text-center"><?php echo $senior; ?></td>
                                            <td class="text-center">
                                                <?php if ($allocateFacSe): ?>
                                                    <?php if ($examSeFac): ?>
                                                        <a href="./userQuizFactuel?test=<?php echo htmlspecialchars((string)$allocateFacSe['test'], ENT_QUOTES, 'UTF-8'); ?>&level=<?php echo htmlspecialchars($allocateFacSe['level'] ?? 'Senior', ENT_QUOTES, 'UTF-8'); ?>&id=<?php echo htmlspecialchars($userId, ENT_QUOTES, 'UTF-8'); ?>"
                                                           class="btn btn-light btn-active-light-primary text-primary fw-bolder btn-sm"
                                                           title="Cliquez ici pour ouvrir le test">
                                                           <?php echo $en_cours; ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <?php if ((isset($allocateFacSe['active']) && $allocateFacSe['active'] === false)): ?>
                                                            <a href="./userQuizFactuel?test=<?php echo htmlspecialchars((string)$allocateFacSe['test'], ENT_QUOTES, 'UTF-8'); ?>&level=<?php echo htmlspecialchars($allocateFacSe['level'] ?? 'Senior', ENT_QUOTES, 'UTF-8'); ?>&id=<?php echo htmlspecialchars($userId, ENT_QUOTES, 'UTF-8'); ?>"
                                                               class="btn btn-light btn-active-light-primary text-primary fw-bolder btn-sm"
                                                               title="Cliquez ici pour ouvrir le test">
                                                               <?php echo $completer_par_vous; ?>
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="badge badge-light-success fs-7 m-1"><?php echo $effectue; ?></span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="badge badge-light-danger fs-7 m-1"><?php echo $non_disponible; ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>

                                        <tr class="odd">
                                            <td class="text-center"><?php echo $connaissances; ?></td>
                                            <td class="text-center"><?php echo $expert; ?></td>
                                            <td class="text-center">
                            <?php if ($allocateFacEx): ?>
                                <?php if ($examExFac): ?>
                                    <a href="./userQuizFactuel?test=<?php echo htmlspecialchars((string)$allocateFacEx['test'], ENT_QUOTES, 'UTF-8'); ?>&level=<?php echo htmlspecialchars($allocateFacEx['level'] ?? 'Expert', ENT_QUOTES, 'UTF-8'); ?>&id=<?php echo htmlspecialchars($userId, ENT_QUOTES, 'UTF-8'); ?>"
                                       class="btn btn-light btn-active-light-primary text-primary fw-bolder btn-sm"
                                       title="Cliquez ici pour ouvrir le test">
                                       <?php echo $en_cours; ?>
                                    </a>
                                <?php else: ?>
                                    <?php if ((isset($allocateFacEx['active']) && $allocateFacEx['active'] === false)): ?>
                                        <a href="./userQuizFactuel?test=<?php echo htmlspecialchars((string)$allocateFacEx['test'], ENT_QUOTES, 'UTF-8'); ?>&level=<?php echo htmlspecialchars($allocateFacEx['level'] ?? 'Expert', ENT_QUOTES, 'UTF-8'); ?>&id=<?php echo htmlspecialchars($userId, ENT_QUOTES, 'UTF-8'); ?>"
                                           class="btn btn-light btn-active-light-primary text-primary fw-bolder btn-sm"
                                           title="Cliquez ici pour ouvrir le test">
                                           <?php echo $completer_par_vous; ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="badge badge-light-success fs-7 m-1"><?php echo $effectue; ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge badge-light-danger fs-7 m-1"><?php echo $non_disponible; ?></span>
                            <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php
                                    } // fin else hasMixed
                                    ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="row">
                            <div class="col-sm-12 col-md-5 d-flex align-items-center justify-content-center justify-content-md-start">
                                <div class="dataTables_length">
                                    <label>
                                        <select id="kt_customers_table_length" name="kt_customers_table_length" class="form-select form-select-sm form-select-solid">
                                            <option value="100">100</option>
                                            <option value="200">200</option>
                                            <option value="300">300</option>
                                            <option value="500">500</option>
                                        </select>
                                    </label>
                                </div>
                            </div>
                            <div class="col-sm-12 col-md-7 d-flex align-items-center justify-content-center justify-content-md-end">
                                <div class="dataTables_paginate paging_simple_numbers">
                                    <ul class="pagination" id="kt_customers_table_paginate"></ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!--end::Table-->
                </div>
                <!--end::Card body-->
            </div>
            <!--end::Card-->
        </div>
        <!--end::Container-->
    </div>
    <!--end::Post-->
<?php closeBackgroundContainer(); ?>
<!--end::Content-->

<?php include_once "partials/footer.php"; ?>
<?php
} // end session guard
?>
