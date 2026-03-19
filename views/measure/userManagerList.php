<?php
session_start();
include_once "../language.php";

if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
} else {

    require_once "../../vendor/autoload.php";

    // Create connection
    $conn = new MongoDB\Client("mongodb://localhost:27017");

    // Connecting in database
    $academy = $conn->academy;

    // Connecting in collections
    $users = $academy->users;
    $exams = $academy->exams;
    $quizzes = $academy->quizzes;
    $allocations = $academy->allocations;

    $id = $_SESSION["id"];

    $manager = $users->findOne([
        '$and' => [
            [
                "_id" => new MongoDB\BSON\ObjectId($id),
                "active" => true,
            ],
        ],
    ]);
    ?>
<?php include_once "partials/header.php"; ?>
<!--begin::Title-->
<title><?php echo $list_evalue_collab ?> | CFAO Mobility Academy</title>
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
                    <i class="fas fa-users fs-2 me-3"></i>
                    <?php echo $list_evalue_collab ?>
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
                <!--begin::Card header-->
                <!-- <div class="card-header border-0 pt-6"> -->
                <!--begin::Card title-->
                <!-- <div class="card-title"> -->
                <!--begin::Search-->
                <!-- <div
                            class="d-flex align-items-center position-relative my-1">
                            <i
                                class="ki-duotone ki-magnifier fs-3 position-absolute ms-5"><span
                                    class="path1"></span><span
                                    class="path2"></span></i> <input type="text"
                                data-kt-customer-table-filter="search"
                                id="search"
                                class="form-control form-control-solid w-250px ps-12"
                                placeholder="Recherche">
                        </div> -->
                <!--end::Search-->
                <!-- </div> -->
                <!--begin::Card title-->
                <!--begin::Card toolbar-->
                <!-- <div class="card-toolbar"> -->
                <!--begin::Toolbar-->
                <!-- <div class="d-flex justify-content-end"
                            data-kt-customer-table-toolbar="base"> -->
                <!--begin::Filter-->
                <!-- <div class="w-150px me-3"> -->
                <!--begin::Select2-->
                <!-- <select id="select"
                                    class="form-select form-select-solid"
                                    data-control="select2"
                                    data-hide-search="true"
                                    data-placeholder="Etat"
                                    data-kt-ecommerce-order-filter="etat">
                                    <option></option>
                                    <option value="tous">Tous
                                    </option>
                                    <option value="true">
                                        Effectué</option>
                                    <option value="false">
                                        En attente</option>
                                </select> -->
                <!--end::Select2-->
                <!-- </div> -->
                <!--end::Filter-->
                <!--begin::Export-->
                <!-- <button type="button" id="excel"
                                class="btn btn-light-primary me-3"
                                data-bs-toggle="modal"
                                data-bs-target="#kt_customers_export_modal">
                                <i class="ki-duotone ki-exit-up fs-2"><span
                                        class="path1"></span><span
                                        class="path2"></span></i> Excel
                            </button> -->
                <!--end::Export-->
                <!-- </div> -->
                <!--end::Toolbar-->
                <!-- </div> -->
                <!--end::Card toolbar-->
                <!-- </div> -->
                <!--end::Card header-->
                <!--begin::Card body-->
                <div class="card-body pt-0">
                    <!--begin::Table-->
                    <div id="kt_customers_table_wrapper" class="dataTables_wrapper dt-bootstrap4 no-footer">
                        <div class="table-responsive">
                            <table aria-describedby=""
                                class="table align-middle table-row-dashed fs-6 gy-5 dataTable no-footer"
                                id="kt_customers_table">
                                <thead>
                                    <tr class="text-start text-black fw-bold fs-7 text-uppercase gs-0">
                                        <th class="w-10px pe-2 sorting_disabled" rowspan="1" colspan="1" aria-label=""
                                            style="width: 29.8906px;">
                                            <div
                                                class="form-check form-check-sm form-check-custom form-check-solid me-3">
                                                <input class="form-check-input" type="checkbox" data-kt-check="true"
                                                    data-kt-check-target="#kt_customers_table .form-check-input"
                                                    value="1">
                                            </div>
                                        </th>
                                        <th class="min-w-125px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $technicien ?></th>
                                        <th class="min-w-250px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Email: activate to sort column ascending" style="width: 200px;">
                                            <?php echo $qcm ?></th>
                                        <th class="min-w-125px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $level ?> <?php echo $junior ?></th>
                                        <th class="min-w-125px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $level ?> <?php echo $senior ?></th>
                                        <th class="min-w-125px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $level ?> <?php echo $expert ?></th>
                                    </tr>
                                </thead>
                                <tbody class="fw-semibold text-gray-600" id="table">
                                    <?php 
                                    if (!$manager) {
                                        /* 1) ID de manager inconnu */
                                        echo '<tr><td colspan="6" class="text-center">Manager not found</td></tr>';

                                    } else {

                                        /* 2) On convertit le champ users (BSONArray → array PHP) */
                                        $collaborators = [];
                                        if (isset($manager->users)) {
                                            $collaborators = ($manager->users instanceof MongoDB\Model\BSONArray)
                                                        ? $manager->users->getArrayCopy()
                                                        : (array) $manager->users;
                                        }

                                        if (empty($collaborators)) {
                                            /* 3) Manager trouvé, mais aucun collaborateur assigné */
                                            echo '<tr><td colspan="6" class="text-center">No users assigned to this manager</td></tr>';

                                        } else {
                                            /* 4) Boucle normale sur chaque collaborateur */
                                            foreach ($collaborators as $collaborator) {

                                        
                                        $allocateJu = $allocations->findOne([
                                            '$and' => [
                                                ["user" => $collaborator],
                                                ["type" => "Declaratif"],
                                                ["level" => "Junior"],
                                                ["activeTest" => true],
                                            ],
                                        ]);
                                        $allocateSe = $allocations->findOne([
                                            '$and' => [
                                                ["user" => $collaborator],
                                                ["type" => "Declaratif"],
                                                ["level" => "Senior"],
                                                ["activeTest" => true],
                                            ],
                                        ]);
                                        $allocateEx = $allocations->findOne([
                                            '$and' => [
                                                ["user" => $collaborator],
                                                ["type" => "Declaratif"],
                                                ["level" => "Expert"],
                                                ["activeTest" => true],
                                            ],
                                        ]);
                                        if (isset($allocateJu)) {
                                            $examJu = $exams->findOne([
                                                '$and' => [
                                                    [
                                                        "manager" => new MongoDB\BSON\ObjectId(
                                                            $_SESSION["id"]
                                                        ),
                                                    ],
                                                    [
                                                        "test" => new MongoDB\BSON\ObjectId(
                                                            $allocateJu["test"]
                                                        ),
                                                    ],
                                                    ["active" => true],
                                                ],
                                            ]);
                                        }
                                        if (isset($allocateSe)) {
                                            $examSe = $exams->findOne([
                                                '$and' => [
                                                    [
                                                        "manager" => new MongoDB\BSON\ObjectId(
                                                            $_SESSION["id"]
                                                        ),
                                                    ],
                                                    [
                                                        "test" => new MongoDB\BSON\ObjectId(
                                                            $allocateSe["test"]
                                                        ),
                                                    ],
                                                    ["active" => true],
                                                ],
                                            ]);
                                        }
                                        if (isset($allocateEx)) {
                                            $examEx = $exams->findOne([
                                                '$and' => [
                                                    [
                                                        "manager" => new MongoDB\BSON\ObjectId(
                                                            $_SESSION["id"]
                                                        ),
                                                    ],
                                                    [
                                                        "test" => new MongoDB\BSON\ObjectId(
                                                            $allocateEx["test"]
                                                        ),
                                                    ],
                                                    ["active" => true],
                                                ],
                                            ]);
                                        }
                                        $user = $users->findOne([
                                            '$and' => [
                                                [
                                                    "_id" => new MongoDB\BSON\ObjectId(
                                                        $collaborator
                                                    ),
                                                    "active" => true,
                                                ],
                                            ],
                                        ]);

                                        if (!$user) {
                                            continue;                         // saute directement à l’ID suivant
                                        }
                                        ?>
                                    <tr class="odd" etat="">
                                        <td>
                                        </td>
                                        <?php if ($user): ?>
                                            <td class="text-center">
                                                <?= htmlspecialchars($user->firstName . ' ' . $user->lastName) ?>
                                            </td>
                                        <?php endif; ?>

                                        <td class="text-center">
                                            <?php echo $maitrise_tache_pro ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($allocateJu) { ?>
                                            <?php if ($examJu) { ?>
                                                    <a href="./userEvaluation?test=<?php echo $allocateJu[
                                                        "test"
                                                    ]; ?>&level=<?php echo $allocateJu[
                                                        "level"
                                                    ]; ?>&id=<?php echo $_SESSION["id"]; ?>&user=<?php echo $user["_id"]; ?>"
                                                        class="btn btn-light btn-active-light-primary text-primary fw-bolder btn-sm"
                                                        title="Cliquez ici pour ouvrir le test"
                                                        data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                        <?php echo $en_cours ?>
                                                    </a>
                                            <?php } else { ?>
                                                <?php if (
                                                    $allocateJu->activeManager ==
                                                    false
                                                ) { ?>
                                                    <a href="./userEvaluation?test=<?php echo $allocateJu[
                                                        "test"
                                                    ]; ?>&level=<?php echo $allocateJu[
                                                        "level"
                                                    ]; ?>&id=<?php echo $_SESSION["id"]; ?>&user=<?php echo $user["_id"]; ?>"
                                                        class="btn btn-light btn-active-light-primary text-primary fw-bolder btn-sm"
                                                        title="Cliquez ici pour ouvrir le test"
                                                        data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                        <?php echo $evaluer_par_vous ?>
                                                    </a>
                                                <?php } else { ?>
                                                    <span class="badge badge-light-success fs-7 m-1">
                                                        <?php echo $effectue ?>
                                                    </span>
                                                <?php } ?>
                                            <?php } ?>
                                            <?php } else { ?>
                                                <span class="badge badge-light-danger fs-7 m-1">
                                                    <?php echo $non_disponible ?>
                                                </span>
                                            <?php } ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($allocateSe) { ?>
                                            <?php if ($examSe) { ?>
                                                    <a href="./userEvaluation?test=<?php echo $allocateSe[
                                                        "test"
                                                    ]; ?>&level=<?php echo $allocateSe[
                                                        "level"
                                                    ]; ?>&id=<?php echo $_SESSION["id"]; ?>&user=<?php echo $user["_id"]; ?>"
                                                        class="btn btn-light btn-active-light-primary text-primary fw-bolder btn-sm"
                                                        title="Cliquez ici pour ouvrir le test"
                                                        data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                        <?php echo $en_cours ?>
                                                    </a>
                                            <?php } else { ?>
                                                <?php if (
                                                    $allocateSe->activeManager ==
                                                    false
                                                ) { ?>
                                                    <a href="./userEvaluation?test=<?php echo $allocateSe[
                                                        "test"
                                                    ]; ?>&level=<?php echo $allocateSe[
                                                        "level"
                                                    ]; ?>&id=<?php echo $_SESSION["id"]; ?>&user=<?php echo $user["_id"]; ?>"
                                                        class="btn btn-light btn-active-light-primary text-primary fw-bolder btn-sm"
                                                        title="Cliquez ici pour ouvrir le test"
                                                        data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                        <?php echo $evaluer_par_vous ?>
                                                    </a>
                                                <?php } else { ?>
                                                    <span class="badge badge-light-success fs-7 m-1">
                                                        <?php echo $effectue ?>
                                                    </span>
                                                <?php } ?>
                                            <?php } ?>
                                            <?php } else { ?>
                                                <span class="badge badge-light-danger fs-7 m-1">
                                                    <?php echo $non_disponible ?>
                                                </span>
                                            <?php } ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($allocateEx) { ?>
                                            <?php if ($examEx) { ?>
                                                    <a href="./userEvaluation?test=<?php echo $allocateEx[
                                                        "test"
                                                    ]; ?>&level=<?php echo $allocateEx[
                                                        "level"
                                                    ]; ?>&id=<?php echo $_SESSION["id"]; ?>&user=<?php echo $user["_id"]; ?>"
                                                        class="btn btn-light btn-active-light-primary text-primary fw-bolder btn-sm"
                                                        title="Cliquez ici pour ouvrir le test"
                                                        data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                        <?php echo $en_cours ?>
                                                    </a>
                                            <?php } else { ?>
                                                <?php if (
                                                    $allocateEx->activeManager ==
                                                    false
                                                ) { ?>
                                                    <a href="./userEvaluation?test=<?php echo $allocateEx[
                                                        "test"
                                                    ]; ?>&level=<?php echo $allocateEx[
                                                        "level"
                                                    ]; ?>&id=<?php echo $_SESSION["id"]; ?>&user=<?php echo $user["_id"]; ?>"
                                                        class="btn btn-light btn-active-light-primary text-primary fw-bolder btn-sm"
                                                        title="Cliquez ici pour ouvrir le test"
                                                        data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                        <?php echo $evaluer_par_vous ?>
                                                    </a>
                                                <?php } else { ?>
                                                    <span class="badge badge-light-success fs-7 m-1">
                                                        <?php echo $effectue ?>
                                                    </span>
                                                <?php } ?>
                                            <?php } ?>
                                            <?php } else { ?>
                                                <span class="badge badge-light-danger fs-7 m-1">
                                                    <?php echo $non_disponible ?>
                                                </span>
                                            <?php } ?>
                                        </td>
                                        <!--end::Menu-->
                                    </tr>
                                    <?php
                                        }
                                    } }?>
                                </tbody>
                            </table>
                        </div>
                        <div class="row">
                            <div
                                class="col-sm-12 col-md-5 d-flex align-items-center justify-content-center justify-content-md-start">
                                <div class="dataTables_length">
                                    <label><select id="kt_customers_table_length" name="kt_customers_table_length"
                                            class="form-select form-select-sm form-select-solid">
                                            <option value="100">100</option>
                                            <option value="200">200</option>
                                            <option value="300">300</option>
                                            <option value="500">500</option>
                                        </select></label>
                                </div>
                            </div>
                            <div
                                class="col-sm-12 col-md-7 d-flex align-items-center justify-content-center justify-content-md-end">
                                <div class="dataTables_paginate paging_simple_numbers">
                                    <ul class="pagination" id="kt_customers_table_paginate">
                                    </ul>
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
} ?>
