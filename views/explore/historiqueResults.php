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
    $results = $academy->results;

    $resultFacJu = $results
        ->find([
            '$and' => [
                [
                    "level" => "Junior",
                    "type" => "Factuel",
                    "typeR" => "Technicien",
                    "active" => false,
                ],
            ],
        ])
        ->toArray();

    $resultFacSe = $results
        ->find([
            '$and' => [
                [
                    "level" => "Senior",
                    "type" => "Factuel",
                    "typeR" => "Technicien",
                    "active" => false,
                ],
            ],
        ])
        ->toArray();
    $resultFacEx = $results
        ->find([
            '$and' => [
                [
                    "level" => "Expert",
                    "type" => "Factuel",
                    "typeR" => "Technicien",
                    "active" => false,
                ],
            ],
        ])
        ->toArray();

    $resultDeclaJu = $results
        ->find([
            '$and' => [
                [
                    "level" => "Junior",
                    "type" => "Declaratif",
                    "typeR" => "Technicien - Manager",
                    "active" => false,
                ],
            ],
        ])
        ->toArray();
    $resultDeclaSe = $results
        ->find([
            '$and' => [
                [
                    "level" => "Senior",
                    "type" => "Declaratif",
                    "typeR" => "Technicien - Manager",
                    "active" => false,
                ],
            ],
        ])
        ->toArray();
    $resultDeclaEx = $results
        ->find([
            '$and' => [
                [
                    "level" => "Expert",
                    "type" => "Declaratif",
                    "typeR" => "Technicien - Manager",
                    "active" => false,
                ],
            ],
        ])
        ->toArray();

    $resultDeclaJuTech = $results
        ->find([
            '$and' => [
                [
                    "level" => "Junior",
                    "type" => "Declaratif",
                    "typeR" => "Techniciens",
                    "active" => false,
                ],
            ],
        ])
        ->toArray();
    $resultDeclaSeTech = $results
        ->find([
            '$and' => [
                [
                    "level" => "Senior",
                    "type" => "Declaratif",
                    "typeR" => "Techniciens",
                    "active" => false,
                ],
            ],
        ])
        ->toArray();
    $resultDeclaExTech = $results
        ->find([
            '$and' => [
                [
                    "level" => "Expert",
                    "type" => "Declaratif",
                    "typeR" => "Techniciens",
                    "active" => false,
                ],
            ],
        ])
        ->toArray();

    $resultDeclaJuMa = $results
        ->find([
            '$and' => [
                [
                    "level" => "Junior",
                    "type" => "Declaratif",
                    "typeR" => "Managers",
                    "active" => false,
                ],
            ],
        ])
        ->toArray();
    $resultDeclaSeMa = $results
        ->find([
            '$and' => [
                [
                    "level" => "Senior",
                    "type" => "Declaratif",
                    "typeR" => "Managers",
                    "active" => false,
                ],
            ],
        ])
        ->toArray();
    $resultDeclaExMa = $results
        ->find([
            '$and' => [
                [
                    "level" => "Expert",
                    "type" => "Declaratif",
                    "typeR" => "Managers",
                    "active" => false,
                ],
            ],
        ])
        ->toArray();
    ?>
<?php include_once "partials/header.php"; ?>
<!--begin::Title-->
<title><?php echo $histo_result ?> | CFAO Mobility Academy</title>
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
                    <?php echo $histo_result ?> </h1>
                <!--end::Title-->
                <div class="card-title">
                    <!--begin::Search-->
                    <div class="d-flex align-items-center position-relative my-1">
                        <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5"><span class="path1"></span><span
                                class="path2"></span></i>
                        <input type="text" id="search" class="form-control form-control-solid w-250px ps-12"
                            placeholder="<?php echo $recherche?>">
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
                                class="table align-middle table-bordered fs-6 gy-5 dataTable no-footer"
                                id="kt_customers_table">
                                <thead>
                                    <tr class="text-start text-black fw-bold fs-7 text-uppercase gs-0">
                                        <th class="min-w-200px sorting text-center" tabindex="0"
                                            aria-controls="kt_customers_table" rowspan="3"
                                            aria-label="Customer Name: activate to sort column ascending"
                                            style="width: 125px;"><?php echo $technicien ?>
                                        </th>
                                        <!-- <th class="min-w-200px sorting text-center" tabindex="0"
                                            aria-controls="kt_customers_table" rowspan="3"
                                            aria-label="Customer Name: activate to sort column ascending"
                                            style="width: 125px;">Filiale
                                        </th>
                                        <th class="min-w-200px sorting text-center" tabindex="0"
                                            aria-controls="kt_customers_table" rowspan="3"
                                            aria-label="Customer Name: activate to sort column ascending"
                                            style="width: 125px;">Departement
                                        </th> -->
                                        <th class="min-w-150px sorting text-center" tabindex="0"
                                            aria-controls="kt_customers_table" colspan="4"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $level ?> <?php echo $junior ?></th>
                                        <th class="min-w-150px sorting text-center" tabindex="0"
                                            aria-controls="kt_customers_table" colspan="4"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $level ?> <?php echo $senior ?></th>
                                        <th class="min-w-150px sorting text-center" tabindex="0"
                                            aria-controls="kt_customers_table" colspan="4"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $level ?> <?php echo $expert ?></th>
                                        <tr></tr>
                                        <th class="min-w-80px sorting text-center text-black fw-bold" tabindex="0"
                                            aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $connaissances ?></th>
                                        <th class="min-w-80px sorting text-center text-black fw-bold" tabindex="0"
                                            aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $tache_pro_tech ?></th>
                                        <th class="min-w-80px sorting text-center text-black fw-bold" tabindex="0"
                                            aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $tache_pro_manager ?></th>
                                        <th class="min-w-80px sorting text-center text-black fw-bold" tabindex="0"
                                            aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $global ?></th>
                                        <th class="min-w-80px sorting text-center text-black fw-bold" tabindex="0"
                                            aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $connaissances ?></th>
                                        <th class="min-w-80px sorting text-center text-black fw-bold" tabindex="0"
                                            aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $tache_pro_tech ?></th>
                                        <th class="min-w-80px sorting text-center text-black fw-bold" tabindex="0"
                                            aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $tache_pro_manager ?></th>
                                        <th class="min-w-80px sorting text-center text-black fw-bold" tabindex="0"
                                            aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $global ?></th>
                                        <th class="min-w-80px sorting text-center text-black fw-bold" tabindex="0"
                                            aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $connaissances ?></th>
                                        <th class="min-w-80px sorting text-center text-black fw-bold" tabindex="0"
                                            aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $tache_pro_tech ?></th>
                                        <th class="min-w-80px sorting text-center text-black fw-bold" tabindex="0"
                                            aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $tache_pro_manager ?></th>
                                        <th class="min-w-80px sorting text-center text-black fw-bold" tabindex="0"
                                            aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $global ?></th>
                                    </tr>
                                </thead>
                                <tbody class="fw-semibold text-gray-600" id="table">
                                    <?php if ($resultFacJu && $resultDeclaJu) {
                                        for (
                                            $i = 0;
                                            $i < count($resultFacJu);
                                            $i++
                                        ) {

                                            $user = $users->findone([
                                                '$and' => [
                                                    [
                                                        "_id" => new MongoDB\BSON\ObjectId(
                                                            $resultFacJu[$i][
                                                                "user"
                                                            ]
                                                        ),
                                                        "active" => true,
                                                    ],
                                                ],
                                            ]);
                                            $percentageFacJu =
                                                ceil(($resultFacJu[$i]["score"] *
                                                    100) /
                                                $resultFacJu[$i]["total"]);
                                            if ($resultDeclaJu) {
                                                $percentageDeclaJu =
                                                    ceil(($resultDeclaJu[$i][
                                                        "score"
                                                    ] *
                                                        100) /
                                                    $resultDeclaJu[$i]["total"]);
                                            }
                                            if ($resultDeclaJuTech) {
                                                $percentageDeclaJuTech =
                                                    ceil(($resultDeclaJuTech[$i][
                                                        "score"
                                                    ] *
                                                        100) /
                                                    $resultDeclaJuTech[$i]["total"]);
                                            }
                                            if ($resultDeclaJuMa) {
                                                $percentageDeclaJuMa =
                                                    ceil(($resultDeclaJuMa[$i][
                                                        "score"
                                                    ] *
                                                        100) /
                                                    $resultDeclaJuMa[$i]["total"]);
                                            }
                                            if ($resultFacSe) {
                                                $percentageFacSe =
                                                    ceil(($resultFacSe[$i]["score"] *
                                                        100) /
                                                    $resultFacSe[$i]["total"]);
                                            }
                                            if ($resultDeclaSe) {
                                                $percentageDeclaSe =
                                                    ceil(($resultDeclaSe[$i][
                                                        "score"
                                                    ] *
                                                        100) /
                                                    $resultDeclaSe[$i]["total"]);
                                            }
                                            if ($resultDeclaSeTech) {
                                                $percentageDeclaSeTech =
                                                    ceil(($resultDeclaSeTech[$i][
                                                        "score"
                                                    ] *
                                                        100) /
                                                    $resultDeclaSeTech[$i]["total"]);
                                            }
                                            if ($resultDeclaSeMa) {
                                                $percentageDeclaSeMa =
                                                    ceil(($resultDeclaSeMa[$i][
                                                        "score"
                                                    ] *
                                                        100) /
                                                    $resultDeclaSeMa[$i]["total"]);
                                            }
                                            if ($resultFacEx) {
                                                $percentageFacEx =
                                                    ceil(($resultFacEx[$i]["score"] *
                                                        100) /
                                                    $resultFacEx[$i]["total"]);
                                            }
                                            if ($resultDeclaEx) {
                                                $percentageDeclaEx =
                                                    ceil(($resultDeclaEx[$i][
                                                        "score"
                                                    ] *
                                                        100) /
                                                    $resultDeclaEx[$i]["total"]);
                                            }
                                            if ($resultDeclaExTech) {
                                                $percentageDeclaExTech =
                                                    ceil(($resultDeclaExTech[$i][
                                                        "score"
                                                    ] *
                                                        100) /
                                                    $resultDeclaExTech[$i]["total"]);
                                            }
                                            if ($resultDeclaExMa) {
                                                $percentageDeclaExMa =
                                                    ceil(($resultDeclaExMa[$i][
                                                        "score"
                                                    ] *
                                                        100) /
                                                    $resultDeclaExMa[$i]["total"]);
                                            }
                                            $junior = ceil(($percentageFacJu + $percentageDeclaJu) / 2);
                                            if ($resultDeclaSe && $resultFacSe) {
                                                $senior = ceil(($percentageFacSe + $percentageDeclaSe) / 2);
                                            }
                                            if ($resultDeclaEx && $resultFacEx) {
                                                $expert = ceil(($percentageFacEx + $percentageDeclaEx) / 2);
                                            }
                                            ?>
                                    <tr class="odd">
                                        <td class="text-center">
                                            <?php echo $user[
                                                "firstName"
                                            ]; ?> <?php echo $user[
                                                "lastName"
                                            ]; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-light-success fs-7 m-1">
                                                <?php echo $percentageFacJu."%" ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-light-success fs-7 m-1">
                                                <?php echo $percentageDeclaJuTech."%" ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-light-success fs-7 m-1">
                                                <?php echo $percentageDeclaJuMa."%" ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <a href="./historiqueResult.php?numberTest=<?php echo $resultFacJu[$i]["numberTest"] ?>&level=Junior&user=<?php echo $user->_id; ?>"
                                                class="btn btn-light btn-active-light-success text-success btn-sm"
                                                title="Cliquez ici pour voir le résultat du technicien pour le niveau junior"
                                                data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                <?php echo $junior."%" ?>
                                            </a>
                                        </td>
                                        <?php if ($resultFacSe && $resultDeclaSeTech && $resultDeclaSeMa) { ?>
                                        <td class="text-center">
                                            <span class="badge badge-light-success fs-7 m-1">
                                                <?php echo $percentageFacSe."%" ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-light-success fs-7 m-1">
                                                <?php echo $percentageDeclaSeTech."%" ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-light-success fs-7 m-1">
                                                <?php echo $percentageDeclaSeMa."%" ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <a href="./historiqueResult.php?numberTest=<?php echo $resultFacSe[$i]["numberTest"] ?>&level=Senior&user=<?php echo $user->_id; ?>"
                                                class="btn btn-light btn-active-light-success text-success btn-sm"
                                                title="Cliquez ici pour voir le résultat du technicien pour le niveau senior"
                                                data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                <?php echo $senior."%" ?>
                                            </a>
                                        </td>
                                        <?php } else { ?>
                                        <td class="text-center">
                                            <span class="badge badge-light-danger fs-7 m-1">
                                                <?php echo $non_disponible ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-light-danger fs-7 m-1">
                                                <?php echo $non_disponible ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-light-danger fs-7 m-1">
                                                <?php echo $non_disponible ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-light-danger fs-7 m-1">
                                                <?php echo $non_disponible ?>
                                            </span>
                                        </td>
                                        <?php } ?>
                                        <?php if ($resultFacEx && $resultDeclaExTech && $resultDeclaExMa) { ?>
                                        <td class="text-center">
                                            <span class="badge badge-light-success fs-7 m-1">
                                                <?php echo $percentageFacEx."%" ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-light-success fs-7 m-1">
                                                <?php echo $percentageDeclaExTech."%" ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-light-success fs-7 m-1">
                                                <?php echo $percentageDeclaExMa."%" ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <a href="./historiqueResult.php?numberTest=<?php echo $resultFacEx[$i]["numberTest"] ?>&level=Expert&user=<?php echo $user->_id; ?>"
                                                class="btn btn-light btn-active-light-success text-success btn-sm"
                                                title="Cliquez ici pour voir le résultat du technicien pour le niveau expert"
                                                data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                <?php echo $expert."%" ?>
                                            </a>
                                        </td>
                                        <?php } else {
                                             ?>
                                        <td class="text-center">
                                            <span class="badge badge-light-danger fs-7 m-1">
                                                <?php echo $non_disponible ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-light-danger fs-7 m-1">
                                                <?php echo $non_disponible ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-light-danger fs-7 m-1">
                                                <?php echo $non_disponible ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-light-danger fs-7 m-1">
                                                <?php echo $non_disponible ?>
                                            </span>
                                        </td>
                                        <?php
                                        } ?>
                                        <!--end::Menu-->
                                    </tr>
                                    <?php
                                        }
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
            <!--begin::Export dropdown-->
            <div class="d-flex justify-content-end align-items-center" style="margin-top: 20px;">
                <button type="button" id="excel" title="Cliquez ici pour importer la table" class="btn btn-primary">
                    <i class="ki-duotone ki-exit-up fs-2"><span class="path1"></span><span class="path2"></span></i>
                    Excel
                </button>
            </div>
            <!--end::Export dropdown-->
        </div>
        <!--end::Container-->
    </div>
    <!--end::Post-->
</div>
<!--end::Body-->
<?php include_once "partials/footer.php"; ?>
<?php
} ?>
