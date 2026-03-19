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
    $quizzes = $academy->quizzes;
    $vehicles = $academy->vehicles;
    $tests = $academy->tests;
    ?>
<?php include_once "partials/header.php"; ?>
<?php include_once "../core/background-manager.php"; setPageBackground("bg-welcome-tech"); openBackgroundContainer(); ?>
<!--begin::Title-->
<title><?php echo $list_test ?> | CFAO Mobility Academy</title>
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
                    <?php echo $tab_Group_marques ?> </h1>
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

    <?php if (isset($success_msg)) { ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <center><strong><?php echo $success_msg; ?></strong></center>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <?php } ?>
    <?php if (isset($error_msg)) { ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <center><strong><?php echo $error_msg; ?></strong></center>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <?php } ?>

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
                                            aria-label="Customer Name: activate to sort column ascending"
                                            style="width: 125px;"><?php echo $technicienss ?>
                                        </th>
                                        <th class="min-w-125px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Company: activate to sort column ascending"
                                            style="width: 134.188px;"><?php echo $type ?>
                                        </th>
                                        <th class="min-w-125px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Created Date: activate to sort column ascending"
                                            style="width: 134.719px;"><?php echo $level ?>
                                        </th>
                                        <th class="min-w-125px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Created Date: activate to sort column ascending"
                                            style="width: 100px;"><?php echo $total_quiz ?>
                                        </th>
                                        <th class="min-w-100px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Created Date: activate to sort column ascending"
                                            style="width: 152.719px;"><?php echo $groupes_fonctionnels ?>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="fw-semibold text-gray-600" id="table">
                                    <?php
                                    $testt = $tests->find([
                                        "active" => true,
                                    ]);
                                    foreach ($testt as $testt) { 
                                        $user = $users->findOne([
                                            '$and' => [
                                                ["_id" => new MongoDB\BSON\ObjectId($testt->user)],
                                                ["active" => true],
                                            ],
                                        ]);
                                        if (isset($user)) {
                                        ?>
                                    <tr class="odd" etat="<?php echo $testt->active; ?>">
                                        <td>
                                            <!-- <div class="form-check form-check-sm form-check-custom form-check-solid">
                                                <input class="form-check-input" id="checkbox" type="checkbox"
                                                    onclick="enable()" value="<?php echo $testt->_id; ?>">
                                            </div> -->
                                        </td>
                                        <td data-filter=" search">
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#kt_modal_add_customer"
                                                class="text-gray-800 text-hover-primary mb-1">
                                                <?php echo $user->firstName; ?> <?php echo $user->lastName; ?>
                                            </a>
                                        </td>
                                        <td data-filter="phone">
                                            <?php if (
                                                $testt->type == "Factuel"
                                            ) { ?>
                                            <span class="badge badge-light-success fs-7 m-1">
                                                <?php echo $testt->type; ?>
                                            </span>
                                            <?php } ?>
                                            <?php if (
                                                $testt->type == "Declaratif"
                                            ) { ?>
                                            <span class="badge badge-light-warning  fs-7 m-1">
                                                <?php echo $testt->type; ?>
                                            </span>
                                            <?php } ?>
                                        </td>
                                        <td data-order="department">
                                            <?php if (
                                                $testt->level == "Junior"
                                            ) { ?>
                                            <span class="badge badge-light-success fs-7 m-1">
                                                <?php echo $testt->level; ?>
                                            </span>
                                            <?php } ?>
                                            <?php if (
                                                $testt->level == "Senior"
                                            ) { ?>
                                            <span class="badge badge-light-danger fs-7 m-1">
                                                <?php echo $testt->level; ?>
                                            </span>
                                            <?php } ?>
                                            <?php if (
                                                $testt->level == "Expert"
                                            ) { ?>
                                            <span class="badge badge-light-warning  fs-7 m-1">
                                                <?php echo $testt->level; ?>
                                            </span>
                                            <?php } ?>
                                        </td>
                                        <td class="text-center">
                                            <?php echo $testt->total; ?>
                                        </td>
                                        <td data-order="department">
                                            <a href="#"
                                                class="btn btn-light btn-active-light-primary text-primary fw-bolder btn-sm"
                                                title="Cliquez ici pour voir les questions"
                                                 data-bs-toggle="modal" data-bs-target="#kt_modal_invite_questions<?php echo $testt->_id; ?>">
                                                <?php echo $voir_quiz ?>
                                            </a>
                                        </td>
                                    </tr>
                                    <!--begin::Modal - Invite Friends-->
                                    <div class="modal fade" id="kt_modal_invite_questions<?php echo $testt->_id; ?>"
                                        tabindex="-1" aria-hidden="true">
                                        <!--begin::Modal dialog-->
                                        <div class="modal-dialog mw-650px">
                                            <!--begin::Modal content-->
                                            <div class="modal-content">
                                                <!--begin::Modal header-->
                                                <div class="modal-header pb-0 border-0 justify-content-end">
                                                    <!--begin::Close-->
                                                    <div class="btn btn-sm btn-icon btn-active-color-primary"
                                                        data-bs-dismiss="modal">
                                                        <i class="ki-duotone ki-cross fs-1"><span
                                                                class="path1"></span><span class="path2"></span></i>
                                                    </div>
                                                    <!--end::Close-->
                                                </div>
                                                <!--begin::Modal header-->
                                                <!--begin::Modal body-->
                                                <div class="modal-body scroll-y mx-5 mx-xl-18 pt-0 pb-15">
                                                    <!--begin::Heading-->
                                                    <div class="text-center mb-13">
                                                        <!--begin::Title-->
                                                        <h1 class="mb-3">
                                                            <?php echo $list_quiz ?>
                                                        </h1>
                                                        <!--end::Title-->
                                                    </div>
                                                    <!--end::Heading-->
                                                    <!--begin::Users-->
                                                    <div class="mb-10">
                                                        <!--begin::List-->
                                                        <div class="mh-300px scroll-y me-n7 pe-7">
                                                            <!--begin::User-->
                                                            <?php
                                                            $quiz = $quizzes->find(
                                                                [
                                                                    '$and' => [
                                                                        [
                                                                            "_id" => [
                                                                                '$in' =>
                                                                                    $testt[
                                                                                        "quizzes"
                                                                                    ],
                                                                            ],
                                                                            "active" => true,
                                                                        ],
                                                                    ],
                                                                ]
                                                            );
                                                            foreach (
                                                                $quiz
                                                                as $quiz
                                                            ) { ?>
                                                            <div
                                                                class="d-flex flex-stack py-4 border-bottom border-gray-300 border-bottom-dashed">
                                                                <!--begin::Details-->
                                                                <div class="d-flex align-items-center">
                                                                    <div class="ms-5">
                                                                        <a href="#"
                                                                            class="fs-5 fw-bold text-gray-900 text-hover-primary mb-2">
                                                                            <?php echo $quiz->label; ?>
                                                                        </a>
                                                                    </div>
                                                                    <!--end::Details-->
                                                                </div>
                                                                <!--end::Details-->
                                                                <!--begin::Access menu-->
                                                                <!-- <div data-kt-menu-trigger="click">
                                                                    <form method="POST">
                                                                        <input type="hidden" name="quizID"
                                                                            value="<?php echo $quiz->_id; ?>">
                                                                        <input type="hidden" name="testtID"
                                                                            value="<?php echo $testt->_id; ?>">
                                                                        <button
                                                                            class="btn btn-light btn-active-light-primary btn-sm"
                                                                            type="submit" name="retire-question-testt"
                                                                            title="Cliquez ici pour enlever la question du questionnaire">Supprimer</button>
                                                                    </form>
                                                                </div> -->
                                                                <!--end::Access menu-->
                                                            </div>
                                                            <!--end::User-->
                                                            <?php }
                                                            ?>
                                                        </div>
                                                        <!--end::List-->
                                                    </div>
                                                    <!--end::Users-->
                                                </div>
                                                <!--end::Modal body-->
                                            </div>
                                            <!--end::Modal content-->
                                        </div>
                                        <!--end::Modal dialog-->
                                    </div>
                                    <!--end::Modal - Invite Friend-->
                                    <?php } }
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
