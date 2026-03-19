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
    $vehicles = $academy->vehicles;
    $allocations = $academy->allocations;

    $user = $_SESSION["id"];
    $technician = $users->findOne([
        '$and' => [
            [
                "_id" => new MongoDB\BSON\ObjectId($user),
                "active" => true,
            ],
        ],
    ]);
}
?>
<?php include_once "partials/header.php"; ?>
<!--begin::Title-->
<title><?php echo $title_userResult ?> | CFAO Mobility Academy</title>
<!--end::Title-->

<!--begin::Body-->
<div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content"
    data-select2-id="select2-data-kt_content">
    <!--begin::Toolbar-->
    <div class="toolbar" id="kt_toolbar">
        <div class=" container-fluid  d-flex flex-stack flex-wrap flex-sm-nowrap">
            <!--begin::Info-->
            <div class="d-flex flex-column align-items-start justify-content-center flex-wrap me-2">
                <!--begin::Title-->
                <h1 class="text-dark fw-bolder my-1 fs-1">
                    <?php echo $title_userResult ?></h1>
                <!--end::Title-->
                <div class="card-title">
                    <!--begin::Search-->
                    <div class="d-flex align-items-center position-relative my-1">
                        <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5"><span class="path1"></span><span
                                class="path2"></span></i> <input type="text" data-kt-customer-table-filter="search"
                            id="search" class="form-control form-control-solid w-250px ps-12" placeholder="Recherche">
                    </div>
                    <!--end::Search-->
                </div>
            </div>
            <!--end::Info-->
        </div>
    </div>
    <!--end::Toolbar-->
    <!--begin::Post-->
    <div class="post fs-6 d-flex flex-column-fluid" id="kt_post" data-select2-id="select2-data-kt_post">
        <!--begin::Container-->
        <div class=" container-xxl " data-select2-id="select2-data-194-27hh">
            <!--begin::Card-->
            <div class="card">
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
                                        <th class="min-w-125px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Email: activate to sort column ascending" style="width: 250px;">
                                            <?php echo $test ?></th>
                                        <th class="min-w-125px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $vehicle ?></th>
                                        <th class="min-w-125px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $brand ?></th>
                                        <th class="min-w-125px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;"><?php echo $level ?> <?php echo $junior ?>
                                        </th>
                                        <th class="min-w-125px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $level ?> <?php echo $senior ?></th>
                                        <th class="min-w-125px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;"><?php echo $level ?> <?php echo $expert ?>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="fw-semibold text-gray-600" id="table">
                                    <?php
                                    $testFac = $allocations->find([
                                        '$and' => [
                                            [
                                                "user" => new MongoDB\BSON\ObjectId(
                                                    $_SESSION["id"]
                                                ),
                                            ],
                                            ["type" => "Factuel"],
                                            ["active" => true],
                                        ],
                                    ]);
                                    $testDecla = $allocations->find([
                                        '$and' => [
                                            [
                                                "user" => new MongoDB\BSON\ObjectId(
                                                    $_SESSION["id"]
                                                ),
                                            ],
                                            ["type" => "Declaratif"],
                                            ["active" => true],
                                        ],
                                    ]);
                                    foreach ($testFac as $test) {
                                        $vehicle = $vehicles->findOne([
                                            '$and' => [
                                                [
                                                    "_id" => new MongoDB\BSON\ObjectId(
                                                        $test["vehicle"]
                                                    ),
                                                ],
                                                ["active" => true],
                                            ],
                                        ]); ?>
                                    <?php if ($test) { ?>
                                    <tr>
                                        <td class="pe-0">
                                            <span class="text-gray-800 fw-bolder fs-5 d-block">
                                                <?php echo $connaissances ?>
                                            </span>
                                        </td>
                                        <td class="pe-0">
                                            <span class="text-gray-800 fw-bolder fs-5 d-block">
                                                <?php echo $vehicle["label"]; ?>
                                            </span>
                                        </td>
                                        <td class="pe-0">
                                            <span class="text-gray-800 fw-bolder fs-5 d-block">
                                                <?php echo $vehicle["brand"]; ?>
                                            </span>
                                        </td>
                                        <?php if ($test->level == "Junior") { ?>
                                        <td>
                                            <span class="badge badge-light-success fs-7 m-1">
                                                <?php echo $effectue ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-light-danger fs-7 m-1">
                                                <?php echo $non_disponible ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-light-danger fs-7 m-1">
                                                <?php echo $non_disponible ?>
                                            </span>
                                        </td>
                                        <?php } ?>
                                        <?php if ($test->level == "Senior") { ?>
                                        <td>
                                            <span class="badge badge-light-success fs-7 m-1">
                                                <?php echo $effectue ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-light-success fs-7 m-1">
                                                <?php echo $effectue ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-light-danger fs-7 m-1">
                                                <?php echo $non_disponible ?>
                                            </span>
                                        </td>
                                        <?php } ?>
                                        <?php if ($test->level == "Expert") { ?>
                                        <td>
                                            <span class="badge badge-light-success fs-7 m-1">
                                                <?php echo $effectue ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-light-success fs-7 m-1">
                                                <?php echo $effectue ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-light-success fs-7 m-1">
                                                <?php echo $effectue ?>
                                            </span>
                                        </td>
                                        <?php } ?>
                                    </tr>
                                    <?php } ?>
                                    <?php
                                    }
                                    ?>
                                    <?php foreach ($testDecla as $test) {
                                        $vehicle = $vehicles->findOne([
                                            '$and' => [
                                                [
                                                    "_id" => new MongoDB\BSON\ObjectId(
                                                        $test["vehicle"]
                                                    ),
                                                ],
                                                ["active" => true],
                                            ],
                                        ]); ?>
                                    <?php if ($test) { ?>
                                    <tr>
                                        <td class="pe-0">
                                            <span class="text-gray-800 fw-bolder fs-5 d-block">
                                                <?php echo $tache_pro ?>
                                            </span>
                                        </td>
                                        <td class="pe-0">
                                            <span class="text-gray-800 fw-bolder fs-5 d-block">
                                                <?php echo $vehicle["label"]; ?>
                                            </span>
                                        </td>
                                        <td class="pe-0">
                                            <span class="text-gray-800 fw-bolder fs-5 d-block">
                                                <?php echo $vehicle["brand"]; ?>
                                            </span>
                                        </td>
                                        <?php if ($test->level == "Junior") { ?>
                                        <td>
                                            <span class="badge badge-light-success fs-7 m-1">
                                                <?php echo $effectue ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-light-danger fs-7 m-1">
                                                <?php echo $non_disponible ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-light-danger fs-7 m-1">
                                                <?php echo $non_disponible ?>
                                            </span>
                                        </td>
                                        <?php } ?>
                                        <?php if ($test->level == "Senior") { ?>
                                        <td>
                                            <span class="badge badge-light-success fs-7 m-1">
                                                <?php echo $effectue ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-light-success fs-7 m-1">
                                                <?php echo $effectue ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-light-danger fs-7 m-1">
                                                <?php echo $non_disponible ?>
                                            </span>
                                        </td>
                                        <?php } ?>
                                        <?php if ($test->level == "Expert") { ?>
                                        <td>
                                            <span class="badge badge-light-success fs-7 m-1">
                                                <?php echo $effectue ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-light-success fs-7 m-1">
                                                <?php echo $effectue ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-light-success fs-7 m-1">
                                                <?php echo $effectue ?>
                                            </span>
                                        </td>
                                        <?php } ?>
                                    </tr>
                                    <?php } ?>
                                    <?php
                                    } ?>
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
</div>
<!--end::Body-->
<?php include_once "partials/footer.php"; ?>
