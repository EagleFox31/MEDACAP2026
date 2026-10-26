<?php
require_once __DIR__ . '/../../core/medacap_config.php';
session_start();
include_once "../language.php";

if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
} else {

    require_once "../../vendor/autoload.php";

    // Create connection
    $conn = new MongoDB\Client(medacapMongoUri());

    // Connecting in database
    $academy = $conn->academy;

    // Connecting in collections
    $users = $academy->users;
    $results = $academy->results;
    $allocations = $academy->allocations;

    $id = $_SESSION["id"];
    $manager = $users->findOne(["_id" => new MongoDB\BSON\ObjectId($id)]);
   ?>
<?php include_once "partials/header.php"; ?>
<?php include_once "../core/background-manager.php"; setPageBackground("bg-welcome-tech"); openBackgroundContainer(); ?>
<!--begin::Title-->
<title><?php echo $list_result_collab ?> | CFAO Mobility Academy</title>
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
                    <?php echo $list_result_collab ?></h1>
                <!--end::Title-->
                <div class="card-title">
                    <!--begin::Search-->
                    <div class="d-flex align-items-center position-relative my-1">
                        <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5"><span class="path1"></span><span
                                class="path2"></span></i>
                        <input type="text" id="search" class="form-control form-control-solid w-250px ps-12"
                            placeholder="Recherche...">
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
                                class="table align-middle table-bordered fs-6 gy-5 dataTable no-footer"
                                id="kt_customers_table">
                                <thead>
                                    <tr class="text-start text-black fw-bold fs-7 text-uppercase gs-0">
                                        <th class="min-w-200px sorting text-center" tabindex="0"
                                            aria-controls="kt_customers_table" rowspan="3"
                                            aria-label="Customer Name: activate to sort column ascending"
                                            style="width: 125px;"><?php echo $collaborators ?>
                                        </th>
                                        <th class="min-w-150px sorting text-center" tabindex="0"
                                            aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $global ?> <?php echo $level ?> <?php echo $junior ?></th>
                                        <th class="min-w-150px sorting text-center" tabindex="0"
                                            aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $global ?> <?php echo $level ?> <?php echo $senior ?></th>
                                        <th class="min-w-150px sorting text-center" tabindex="0"
                                            aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $global ?> <?php echo $level ?> <?php echo $expert ?></th>
                                    </tr>
                                </thead>
                                <tbody class="fw-semibold text-gray-600" id="table">
                                    <?php foreach ($manager["users"] as $tech) {

                                            $user = $users->findone([
                                                '$and' => [
                                                    [
                                                        "_id" => new MongoDB\BSON\ObjectId($tech),
                                                        "active" => true,
                                                    ],
                                                ],
                                            ]);

                                            $allocateFacJu = $allocations
                                                ->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId($tech),
                                                            "level" => "Junior",
                                                            "type" => "Factuel",
                                                        ],
                                                    ],
                                                ]);
                                        
                                            $allocateFacSe = $allocations
                                                ->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId($tech),
                                                            "level" => "Senior",
                                                            "type" => "Factuel",
                                                        ],
                                                    ],
                                                ]);
                                        
                                            $allocateFacEx = $allocations
                                                ->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId($tech),
                                                            "level" => "Expert",
                                                            "type" => "Factuel",
                                                        ],
                                                    ],
                                                ]);
                                                
                                            $allocateDeclaJu = $allocations
                                                ->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId($tech),
                                                            "level" => "Junior",
                                                            "type" => "Declaratif",
                                                        ],
                                                    ],
                                                ]);
                                                
                                            $allocateDeclaSe = $allocations
                                                ->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId($tech),
                                                            "level" => "Senior",
                                                            "type" => "Declaratif",
                                                        ],
                                                    ],
                                                ]);
                                        
                                            $allocateDeclaEx = $allocations
                                                ->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId($tech),
                                                            "level" => "Expert",
                                                            "type" => "Declaratif",
                                                        ],
                                                    ],
                                                ]);

                                            $resultFacJu = $results
                                                ->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId($tech),
                                                            "level" => "Junior",
                                                            "type" => "Factuel",
                                                            "typeR" => "Technicien",
                                                            "active" => true,
                                                        ],
                                                    ],
                                                ]);
                                        
                                            $resultFacSe = $results
                                                ->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId($tech),
                                                            "level" => "Senior",
                                                            "type" => "Factuel",
                                                            "typeR" => "Technicien",
                                                            "active" => true,
                                                        ],
                                                    ],
                                                ]);
                                            $resultFacEx = $results
                                                ->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId($tech),
                                                            "level" => "Expert",
                                                            "type" => "Factuel",
                                                            "typeR" => "Technicien",
                                                            "active" => true,
                                                        ],
                                                    ],
                                                ]);
                                        
                                            $resultDeclaJu = $results
                                                ->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId($tech),
                                                            "level" => "Junior",
                                                            "type" => "Declaratif",
                                                            "typeR" => "Technicien - Manager",
                                                            "active" => true,
                                                        ],
                                                    ],
                                                ]);
                                            $resultDeclaSe = $results
                                                ->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId($tech),
                                                            "level" => "Senior",
                                                            "type" => "Declaratif",
                                                            "typeR" => "Technicien - Manager",
                                                            "active" => true,
                                                        ],
                                                    ],
                                                ]);
                                            $resultDeclaEx = $results
                                                ->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId($tech),
                                                            "level" => "Expert",
                                                            "type" => "Declaratif",
                                                            "typeR" => "Technicien - Manager",
                                                            "active" => true,
                                                        ],
                                                    ],
                                                ]);
                                        
                                            $resultDeclaJuTech = $results
                                                ->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId($tech),
                                                            "level" => "Junior",
                                                            "type" => "Declaratif",
                                                            "typeR" => "Techniciens",
                                                            "active" => true,
                                                        ],
                                                    ],
                                                ]);
                                            $resultDeclaSeTech = $results
                                                ->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId($tech),
                                                            "level" => "Senior",
                                                            "type" => "Declaratif",
                                                            "typeR" => "Techniciens",
                                                            "active" => true,
                                                        ],
                                                    ],
                                                ]);
                                            $resultDeclaExTech = $results
                                                ->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId($tech),
                                                            "level" => "Expert",
                                                            "type" => "Declaratif",
                                                            "typeR" => "Techniciens",
                                                            "active" => true,
                                                        ],
                                                    ],
                                                ]);
                                        
                                            $resultDeclaJuMa = $results
                                                ->findOne([
                                                    '$and' => [
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId($id),
                                                            "level" => "Junior",
                                                            "type" => "Declaratif",
                                                            "typeR" => "Managers",
                                                            "active" => true,
                                                        ],
                                                    ],
                                                ]);
                                            $resultDeclaSeMa = $results
                                                ->findOne([
                                                    '$and' => [
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId($id),
                                                            "level" => "Senior",
                                                            "type" => "Declaratif",
                                                            "typeR" => "Managers",
                                                            "active" => true,
                                                        ],
                                                    ],
                                                ]);
                                            $resultDeclaExMa = $results
                                                ->findOne([
                                                    '$and' => [
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId($id),
                                                            "level" => "Expert",
                                                            "type" => "Declaratif",
                                                            "typeR" => "Managers",
                                                            "active" => true,
                                                        ],
                                                    ],
                                                ]);
                                         
                                             if (isset($resultFacJu)) {
                                                 $percentageFacJu =
                                                     ceil(($resultFacJu["score"] *
                                                         100) /
                                                    $resultFacJu["total"]);
                                             }
                                            if (isset($resultDeclaJu)) {
                                                $percentageDeclaJu =
                                                    ceil(($resultDeclaJu[
                                                        "score"
                                                    ] *
                                                        100) /
                                                    $resultDeclaJu["total"]);
                                            }
                                            if (isset($resultDeclaJuTech)) {
                                                $percentageDeclaJuTech =
                                                    ceil(($resultDeclaJuTech[
                                                        "score"
                                                    ] *
                                                        100) /
                                                    $resultDeclaJuTech["total"]);
                                            }
                                            if (isset($resultDeclaJuMa)) {
                                                $percentageDeclaJuMa =
                                                    ceil(($resultDeclaJuMa[
                                                        "score"
                                                    ] *
                                                        100) /
                                                    $resultDeclaJuMa["total"]);
                                            }
                                            if (isset($resultFacSe)) {
                                                $percentageFacSe =
                                                    ceil(($resultFacSe["score"] *
                                                        100) /
                                                    $resultFacSe["total"]);
                                            }
                                            if (isset($resultDeclaSe)) {
                                                $percentageDeclaSe =
                                                    ceil(($resultDeclaSe[
                                                        "score"
                                                    ] *
                                                        100) /
                                                    $resultDeclaSe["total"]);
                                            }
                                            if (isset($resultDeclaSeTech)) {
                                                $percentageDeclaSeTech =
                                                    ceil(($resultDeclaSeTech[
                                                        "score"
                                                    ] *
                                                        100) /
                                                    $resultDeclaSeTech["total"]);
                                            }
                                            if (isset($resultDeclaSeMa)) {
                                                $percentageDeclaSeMa =
                                                    ceil(($resultDeclaSeMa[
                                                        "score"
                                                    ] *
                                                        100) /
                                                    $resultDeclaSeMa["total"]);
                                            }
                                            if (isset($resultFacEx)) {
                                                $percentageFacEx =
                                                    ceil(($resultFacEx["score"] *
                                                        100) /
                                                    $resultFacEx["total"]);
                                            }
                                            if (isset($resultDeclaEx)) {
                                                $percentageDeclaEx =
                                                    ceil(($resultDeclaEx[
                                                        "score"
                                                    ] *
                                                        100) /
                                                    $resultDeclaEx["total"]);
                                            }
                                            if (isset($resultDeclaExTech)) {
                                                $percentageDeclaExTech =
                                                    ceil(($resultDeclaExTech[
                                                        "score"
                                                    ] *
                                                        100) /
                                                    $resultDeclaExTech["total"]);
                                            }
                                            if (isset($resultDeclaExMa)) {
                                                $percentageDeclaExMa =
                                                    ceil(($resultDeclaExMa[
                                                        "score"
                                                    ] *
                                                        100) /
                                                    $resultDeclaExMa["total"]);
                                            }
                                            if (isset($resultDeclaJu) && isset($resultFacJu)) {
                                                $junior = ceil(($percentageFacJu + $percentageDeclaJu) / 2);
                                            }
                                            if (isset($resultDeclaSe) && isset($resultFacSe)) {
                                                $senior = ceil(($percentageFacSe + $percentageDeclaSe) / 2);
                                            }
                                            if (isset($resultDeclaEx) && isset($resultFacEx)) {
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
                                        <?php if($allocateDeclaJu['activeManager'] == true && $allocateDeclaJu['active'] == true && $allocateFacJu['active'] == true) { ?>
                                        <td class="text-center">
                                            <a href="./managerBrandResult.php?numberTest=<?php echo $resultFacJu["numberTest"] ?>&level=Junior&user=<?php echo $user->_id; ?>"
                                                class="btn btn-light btn-active-light-success text-success btn-sm"
                                                title="Cliquez ici pour voir le résultat du technicien pour le niveau junior"
                                                data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                <?php echo $junior."%" ?>
                                            </a>
                                        </td>
                                        <?php } else { ?>
                                        <td class="text-center">
                                                -
                                        </td>
                                        <?php } ?>
                                        <?php if (isset($allocateFacSe) && isset($allocateDeclaSe)) { ?>
                                        <?php if($allocateDeclaSe['activeManager'] == true && $allocateDeclaSe['active'] == true && $allocateFacSe['active'] == true) { ?>
                                        <td class="text-center">
                                            <a href="./managerBrandResult.php?numberTest=<?php echo $resultFacSe["numberTest"] ?>&level=Senior&user=<?php echo $user->_id; ?>"
                                                class="btn btn-light btn-active-light-success text-success btn-sm"
                                                title="Cliquez ici pour voir le résultat du technicien pour le niveau senior"
                                                data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                <?php echo $senior."%" ?>
                                            </a>
                                        </td>
                                        <?php } else { ?>
                                        <td class="text-center">
                                                -
                                        </td>
                                        <?php } ?>
                                        <?php } else { ?>
                                        <td class="text-center" style="background-color: #f9f9f9;">
                                        </td>
                                        <?php } ?>
                                        <?php if (isset($allocateFacEx) && isset($allocateDeclaEx)) { ?>
                                        <?php if($allocateDeclaEx['activeManager'] == true && $allocateDeclaEx['active'] == true && $allocateFacEx['active'] == true) { ?>
                                        <td class="text-center">
                                            <a href="./managerBrandResult.php?numberTest=<?php echo $resultFacEx["numberTest"] ?>&level=Expert&user=<?php echo $user->_id; ?>"
                                                class="btn btn-light btn-active-light-success text-success btn-sm"
                                                title="Cliquez ici pour voir le résultat du technicien pour le niveau expert"
                                                data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                <?php echo $expert."%" ?>
                                            </a>
                                        </td>
                                        <?php } else { ?>
                                        <td class="text-center">
                                                -
                                        </td>
                                        <?php } ?>
                                        <?php } else { ?>
                                        <td class="text-center" style="background-color: #f9f9f9;">
                                        </td>
                                        <?php } ?>
                                        <!--end::Menu-->
                                    </tr>
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
            <!--begin::Export dropdown-->
            <!-- <div class="d-flex justify-content-end align-items-center" style="margin-top: 20px;">
                <button type="button" id="excel" title="Cliquez ici pour importer la table" class="btn btn-primary">
                    <i class="ki-duotone ki-exit-up fs-2"><span class="path1"></span><span class="path2"></span></i>
                    Excel
                </button>
            </div> -->
            <!--end::Export dropdown-->
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
