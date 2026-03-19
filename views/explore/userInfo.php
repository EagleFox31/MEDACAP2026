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

    $id = $_SESSION["id"];
    ?>
<?php include_once "partials/header.php"; ?>
<?php include_once "../core/background-manager.php"; setPageBackground("bg-welcome-tech"); openBackgroundContainer(); ?>
<!--begin::Title-->
<title><?php echo $list_avancement ?> | CFAO Mobility Academy</title>
<!--end::Title-->

<!--begin::Body-->
<div class='content fs-6 d-flex flex-column flex-column-fluid' id='kt_content'
    data-select2-id='select2-data-kt_content'>
    <!--begin::Toolbar-->
    <div class='toolbar' id='kt_toolbar'>
        <div class=' container-fluid  d-flex flex-stack flex-wrap flex-sm-nowrap'>
            <!--begin::Info-->
            <div class='d-flex flex-column align-items-start justify-content-center flex-wrap me-2'>
                <!--begin::Title-->
                <h1 class='text-dark fw-bolder my-1 fs-1'>
                    <?php echo $etat_avancement_user ?></h1>
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
                                        <th class="w-10px pe-2 sorting_disabled" rowspan="1" colspan="1" aria-label=""
                                            style="width: 29.8906px;">
                                            <div
                                                class="form-check form-check-sm form-check-custom form-check-solid me-3">
                                                <input class="form-check-input" type="checkbox" data-kt-check="true"
                                                    data-kt-check-target="#kt_customers_table .form-check-input"
                                                    value="1">
                                            </div>
                                        </th>
                                        <th class="min-w-125px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;"><?php echo $ussers ?></th>
                                        <th class="min-w-125px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;"><?php echo $filiale ?></th>
                                        <th class="min-w-125px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;"><?php echo $department ?></th>
                                        <th class="min-w-125px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Email: activate to sort column ascending" style="width: 200px;">
                                            <?php echo $etat_avanacement_qcm ?> </th>
                                        <th class="min-w-125px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Email: activate to sort column ascending" style="width: 200px;">
                                            <?php echo $etat_avanacement_tache ?> </th>
                                    </tr>
                                </thead>
                                <tbody class="fw-semibold text-gray-600" id="table">
                                    <?php
                                    $usersFactuel = $allocations
                                        ->find([
                                            "type" => "Factuel",
                                        ])
                                        ->toArray();
                                    $usersDeclaratif = $allocations
                                        ->find([
                                            "type" => "Declaratif",
                                        ])
                                        ->toArray();
                                    for (
                                        $i = 0;
                                        $i < count($usersFactuel);
                                        $i++
                                    ) {

                                        $user = $users->findOne([
                                            '$and' => [
                                                [
                                                    "_id" => new MongoDB\BSON\ObjectId(
                                                        $usersFactuel[$i][
                                                            "user"
                                                        ]
                                                    ),
                                                    "active" => true,
                                                ],
                                            ],
                                        ]);
                                        $vehicle = $vehicles->findOne([
                                            '$and' => [
                                                [
                                                    "_id" => new MongoDB\BSON\ObjectId(
                                                        $usersFactuel[$i][
                                                            "vehicle"
                                                        ]
                                                    ),
                                                    "active" => true,
                                                ],
                                            ],
                                        ]);
                                        ?>
                                    <tr class="odd" etat="<?php echo $user->active; ?>">
                                        <td>
                                        </td>
                                        <td data-filter="email">
                                            <?php echo $user->firstName; ?> <?php echo $user->lastName; ?>
                                        </td>
                                        <td data-filter="email">
                                            <?php echo $user->subsidiary; ?>
                                        </td>
                                        <td data-filter="email">
                                            <?php echo $user->department; ?>
                                        </td>
                                        <?php if (
                                            $usersFactuel[$i]->active == false
                                        ) { ?>
                                        <td data-filter="email">
                                            <span class="badge badge-light-danger fs-7 m-1">
                                                <?php echo $en_attente ?>
                                            </span>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                            $usersFactuel[$i]->active == true
                                        ) { ?>
                                        <td data-filter="email">
                                            <span class="badge badge-light-success fs-7 m-1">
                                                <?php echo $effectue ?>
                                            </span>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                            $usersDeclaratif[$i]->active ==
                                            false
                                        ) { ?>
                                        <td data-filter="email">
                                            <span class="badge badge-light-danger fs-7 m-1">
                                                <?php echo $en_attente ?>
                                            </span>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                            $usersDeclaratif[$i]->active == true
                                        ) { ?>
                                        <td data-filter="email">
                                            <span class="badge badge-light-success fs-7 m-1">
                                                <?php echo $effectue ?>
                                            </span>
                                        </td>
                                        <?php } ?>
                                        <!--end::Menu-->
                                    </tr>
                                    <?php
                                    }
                                    ?>
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
<?php closeBackgroundContainer(); ?>
<?php include_once "partials/footer.php"; ?>
<?php
} ?>
