<?php
session_start();
include_once "../language.php";

if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
} else {
     ?>
<?php
require_once "../../vendor/autoload.php"; // Create connection
$conn = new MongoDB\Client("mongodb://localhost:27017"); // Connecting in database
$academy = $conn->academy; // Connecting in collections
$users = $academy->users;
$allocations = $academy->allocations;
$exams = $academy->exams;
?>
<?php include_once "partials/header.php"; ?>
<!--begin::Title-->
<title><?php echo $list_test_tache_pro ?> | CFAO Mobility Academy</title>
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
                    <?php echo $list_test_tache_pro ?>
                </h1>
            </div>
        </div>
    </div>
    <!--begin::Post-->
    <div class="post fs-6 d-flex flex-column-fluid" id="kt_post" data-select2-id="select2-data-kt_post">
        <!--begin::Container-->
        <div class=" container-xxl " data-select2-id="select2-data-194-27hh">
            <!--begin::Card-->
            <div class="card glass-effect depth-effect mb-5">
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
                                        <th class="min-w-200px sorting text-center" tabindex="0"
                                            aria-controls="kt_customers_table" rowspan="3"
                                            aria-label="Customer Name: activate to sort column ascending"
                                            style="width: 125px;"><?php echo $test ?>
                                        </th>
                                        <th class="min-w-150px sorting text-center" tabindex="0"
                                            aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $level ?></th>
                                        <th class="min-w-150px sorting text-center" tabindex="0"
                                            aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $etat ?></th>
                                    </tr>
                                </thead>
                                <tbody class="fw-semibold text-gray-600" id="table">
                                    <?php
                                    $allocateDeclaJu = $allocations->findOne([
                                        '$and' => [
                                            [
                                                "user" => new MongoDB\BSON\ObjectId(
                                                    $_SESSION["id"]
                                                ),
                                            ],
                                            ["type" => "Declaratif"],
                                            ["level" => "Junior"],
                                            ["activeTest" => true],
                                        ],
                                    ]);
                                    $allocateDeclaSe = $allocations->findOne([
                                        '$and' => [
                                            [
                                                "user" => new MongoDB\BSON\ObjectId(
                                                    $_SESSION["id"]
                                                ),
                                            ],
                                            ["type" => "Declaratif"],
                                            ["level" => "Senior"],
                                            ["activeTest" => true],
                                        ],
                                    ]);
                                    $allocateDeclaEx = $allocations->findOne([
                                        '$and' => [
                                            [
                                                "user" => new MongoDB\BSON\ObjectId(
                                                    $_SESSION["id"]
                                                ),
                                            ],
                                            ["type" => "Declaratif"],
                                            ["level" => "Expert"],
                                            ["activeTest" => true],
                                        ],
                                    ]);
                                    $examJuDecla = $exams->findOne([
                                        '$and' => [
                                            [
                                                "user" => new MongoDB\BSON\ObjectId(
                                                    $_SESSION["id"]
                                                ),
                                            ],
                                            [
                                                "test" => new MongoDB\BSON\ObjectId(
                                                    $allocateDeclaJu["test"]
                                                ),
                                            ],
                                            ["active" => true],
                                        ],
                                    ]);
                                    if (isset($allocateDeclaSe)) {
                                        $examSeDecla = $exams->findOne([
                                            '$and' => [
                                                [
                                                    "user" => new MongoDB\BSON\ObjectId(
                                                        $_SESSION["id"]
                                                    ),
                                                ],
                                                [
                                                    "test" => new MongoDB\BSON\ObjectId(
                                                        $allocateDeclaSe["test"]
                                                    ),
                                                ],
                                                ["active" => true],
                                            ],
                                        ]);
                                    }
                                    if (isset($allocateDeclaEx)) {
                                        $examExDecla = $exams->findOne([
                                            '$and' => [
                                                [
                                                    "user" => new MongoDB\BSON\ObjectId(
                                                        $_SESSION["id"]
                                                    ),
                                                ],
                                                [
                                                    "test" => new MongoDB\BSON\ObjectId(
                                                        $allocateDeclaEx["test"]
                                                    ),
                                                ],
                                                ["active" => true],
                                            ],
                                        ]);
                                    }
                                    ?>
                                    <tr class="odd">
                                        <td class="text-center">
                                            <?php echo $tache_pro ?>
                                        </td>
                                        <td class="text-center">
                                            <?php echo $junior ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($allocateDeclaJu) { ?>
                                        <?php if ($examJuDecla) { ?>
                                                    <a href="./userQuizDeclaratif?test=<?php echo $allocateDeclaJu[
                                                        "test"
                                                    ]; ?>&level=<?php echo $allocateDeclaJu[
                                                        "level"
                                                    ]; ?>&id=<?php echo $_SESSION["id"]; ?>"
                                                        class="btn btn-light btn-active-light-primary text-primary fw-bolder btn-sm"
                                                        title="Cliquez ici pour ouvrir le test"
                                                        data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                        <?php echo $en_cours ?>
                                                    </a>
                                            <?php } else { ?>
                                                <?php if (
                                                    $allocateDeclaJu->active ==
                                                    false
                                                ) { ?>
                                                    <a href="./userQuizDeclaratif?test=<?php echo $allocateDeclaJu[
                                                        "test"
                                                    ]; ?>&level=<?php echo $allocateDeclaJu[
                                                        "level"
                                                    ]; ?>&id=<?php echo $_SESSION["id"]; ?>"
                                                        class="btn btn-light btn-active-light-primary text-primary fw-bolder btn-sm"
                                                        title="Cliquez ici pour ouvrir le test"
                                                        data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                        <?php echo $completer_par_vous ?>
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
                                    <tr class="odd">
                                        <td class="text-center">
                                            <?php echo $tache_pro ?>
                                        </td>
                                        <td class="text-center">
                                            <?php echo $senior ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($allocateDeclaSe) { ?>
                                            <?php if ($examSeDecla) { ?>
                                                    <a href="./userQuizDeclaratif?test=<?php echo $allocateDeclaSe[
                                                        "test"
                                                    ]; ?>&level=<?php echo $allocateDeclaSe[
                                                        "level"
                                                    ]; ?>&id=<?php echo $_SESSION["id"]; ?>"
                                                        class="btn btn-light btn-active-light-primary text-primary fw-bolder btn-sm"
                                                        title="Cliquez ici pour ouvrir le test"
                                                        data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                        <?php echo $en_cours ?>
                                                    </a>
                                            <?php } else { ?>
                                                <?php if (
                                                    $allocateDeclaSe->active ==
                                                    false
                                                ) { ?>
                                                    <a href="./userQuizDeclaratif?test=<?php echo $allocateDeclaSe[
                                                        "test"
                                                    ]; ?>&level=<?php echo $allocateDeclaSe[
                                                        "level"
                                                    ]; ?>&id=<?php echo $_SESSION["id"]; ?>"
                                                        class="btn btn-light btn-active-light-primary text-primary fw-bolder btn-sm"
                                                        title="Cliquez ici pour ouvrir le test"
                                                        data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                        <?php echo $completer_par_vous ?>
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
                                    <tr class="odd">
                                        <td class="text-center">
                                            <?php echo $tache_pro ?>
                                        </td>
                                        <td class="text-center">
                                            <?php echo $expert ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($allocateDeclaEx) { ?>
                                            <?php if ($examExDecla) { ?>
                                                    <a href="./userQuizDeclaratif?test=<?php echo $allocateDeclaEx[
                                                        "test"
                                                    ]; ?>&level=<?php echo $allocateDeclaEx[
                                                        "level"
                                                    ]; ?>&id=<?php echo $_SESSION["id"]; ?>"
                                                        class="btn btn-light btn-active-light-primary text-primary fw-bolder btn-sm"
                                                        title="Cliquez ici pour ouvrir le test"
                                                        data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                        <?php echo $en_cours ?>
                                                    </a>
                                            <?php } else { ?>
                                                <?php if (
                                                    $allocateDeclaEx->active ==
                                                    false
                                                ) { ?>
                                                    <a href="./userQuizDeclaratif?test=<?php echo $allocateDeclaEx[
                                                        "test"
                                                    ]; ?>&level=<?php echo $allocateDeclaEx[
                                                        "level"
                                                    ]; ?>&id=<?php echo $_SESSION["id"]; ?>"
                                                        class="btn btn-light btn-active-light-primary text-primary fw-bolder btn-sm"
                                                        title="Cliquez ici pour ouvrir le test"
                                                        data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                        <?php echo $completer_par_vous ?>
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
