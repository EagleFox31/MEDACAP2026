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
    $questions = $academy->questions;
    $vehicles = $academy->vehicles;


    if (isset($_POST["active"])) {
        $id = new MongoDB\BSON\ObjectId($_POST["quizID"]);
        $quiz = $quizzes->findOne(["_id" => $id]);
        $quiz->active = true;
        $quizzes->updateOne(["_id" => $id], ['$set' => $quiz]);
        $success_msg = $quiz_restore;
    }
    ?>
<?php include_once "partials/header.php"; ?>
<!--begin::Title-->
<title><?php echo $list_question_sup; ?> | CFAO Mobility Academy</title>
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
                    <?php echo $list_question_sup; ?> </h1>
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
            <!--begin::Actions-->
            <!-- <div class="d-flex align-items-center flex-nowrap text-nowrap py-1">
                <div class="d-flex justify-content-end align-items-center" style="margin-left: 10px;">
                    <button type="button" id="questions" title="Cliquez ici pour voir la liste des questions"
                        data-bs-toggle="modal" class="btn btn-primary">
                        Liste questions
                    </button>
                </div>
                <div class="d-flex justify-content-end align-items-center" style="margin-left: 10px;">
                    <button type="button" id="edit" title="Cliquez ici pour modifier le questionnaire"
                        data-bs-toggle="modal" class="btn btn-primary">
                        Modifier
                    </button>
                </div>
                <div class="d-flex justify-content-end align-items-center" style="margin-left: 10px;">
                    <button type="button" id="delete" title="Cliquez ici pour restorer le questionnaire"
                        data-bs-toggle="modal" class="btn btn-primary">
                        Restorer
                    </button>
                </div>
            </div> -->
            <!--end::Actions-->
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
                <!-- <div class="w-150px me-3" id="etat"> -->
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
                                        Active</option>
                                    <option value="false">
                                        Supprimé</option>
                                </select> -->
                <!--end::Select2-->
                <!-- </div> -->
                <!--end::Filter-->
                <!--begin::Export dropdown-->
                <!-- <button type="button" id="excel"
                                class="btn btn-light-primary">
                                <i class="ki-duotone ki-exit-up fs-2"><span
                                        class="path1"></span><span
                                        class="path2"></span></i>
                                Excel
                            </button> -->
                <!--end::Export dropdown-->
                <!--begin::Group actions-->
                <!-- <div class="d-flex justify-content-end align-items-center"
                                style="margin-left: 10px;">
                                <button type="button" id="users"
                                    data-bs-toggle="modal"
                                    class="btn btn-primary">
                                    Liste des techniciens
                                </button>
                            </div> -->
                <!--end::Group actions-->
                <!--begin::Group actions-->
                <!-- <div class="d-flex justify-content-end align-items-center"
                                style="margin-left: 10px;">
                                <button type="button" id="questions"
                                    data-bs-toggle="modal"
                                    class="btn btn-primary">
                                    Liste des questions
                                </button>
                            </div> -->
                <!--end::Group actions-->
                <!--begin::Group actions-->
                <!-- <div class="d-flex justify-content-end align-items-center"
                                style="margin-left: 10px;">
                                <button type="button" id="edit"
                                    data-bs-toggle="modal"
                                    class="btn btn-primary">
                                    Modifier
                                </button>
                            </div> -->
                <!--end::Group actions-->
                <!--begin::Group actions-->
                <!-- <div class="d-flex justify-content-end align-items-center"
                                style="margin-left: 10px;">
                                <button type="button" id="delete"
                                    data-bs-toggle="modal"
                                    class="btn btn-danger">
                                    Supprimer
                                </button>
                            </div> -->
                <!--end::Group actions-->
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
                                            </div>
                                        </th>
                                        <th class="min-w-125px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Customer Name: activate to sort column ascending"
                                            style="width: 125px;"><?php echo $quizLabel; ?>
                                        </th>
                                        <th class="min-w-125px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Company: activate to sort column ascending"
                                            style="width: 134.188px;"><?php echo $type; ?>
                                        </th>
                                        <th class="min-w-125px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Payment Method: activate to sort column ascending"
                                            style="width: 126.516px;"><?php echo $speciality; ?>
                                        </th>
                                        <th class="min-w-125px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Created Date: activate to sort column ascending"
                                            style="width: 152.719px;"><?php echo $level; ?>
                                        </th>
                                        <th class="min-w-125px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Created Date: activate to sort column ascending"
                                            style="width: 152.719px;"><?php echo $restaurer; ?>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="fw-semibold text-gray-600" id="table">
                                    <?php
                                    $quiz = $quizzes->find(["active" => false]);
                                    foreach ($quiz as $quiz) { ?>
                                    <tr class="odd" etat="<?php echo $quiz->active; ?>">
                                        <td>
                                        </td>
                                        <td data-filter=" search">
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#kt_modal_add_customer"
                                                class="text-gray-800 text-hover-primary mb-1">
                                                <?php echo $quiz->label; ?>
                                            </a>
                                        </td>
                                        <td data-filter="phone">
                                            <?php if (
                                                $quiz->type == "Factuel"
                                            ) { ?>
                                            <span class="badge badge-light-success fs-7 m-1">
                                                <?php echo $quiz->type; ?>
                                            </span>
                                            <?php } ?>
                                            <?php if (
                                                $quiz->type == "Declaratif"
                                            ) { ?>
                                            <span class="badge badge-light-warning  fs-7 m-1">
                                                <?php echo $quiz->type; ?>
                                            </span>
                                            <?php } ?>
                                        </td>
                                        <td data-order="subsidiary">
                                            <?php echo $quiz->speciality; ?>
                                        </td>
                                        <td data-order="department">
                                            <?php if (
                                                $quiz->level == "Junior"
                                            ) { ?>
                                            <span class="badge badge-light-success fs-7 m-1">
                                                <?php echo $quiz->level; ?>
                                            </span>
                                            <?php } ?>
                                            <?php if (
                                                $quiz->level == "Senior"
                                            ) { ?>
                                            <span class="badge badge-light-danger fs-7 m-1">
                                                <?php echo $quiz->level; ?>
                                            </span>
                                            <?php } ?>
                                            <?php if (
                                                $quiz->level == "Expert"
                                            ) { ?>
                                            <span class="badge badge-light-warning  fs-7 m-1">
                                                <?php echo $quiz->level; ?>
                                            </span>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-icon btn-light-warning w-30px h-30px me-3" data-bs-toggle="modal" data-bs-target="#kt_modal_desactivate<?php echo $quiz->_id; ?>">
                                                <i class="fas fa-history fs-2"><span class="path1"></span><span
                                                    class="path2"></span></i></button>
                                        </td>
                                    </tr>
                                    <!-- begin:: Modal - Confirm suspend -->
                                    <div class="modal" id="kt_modal_desactivate<?php echo $quiz->_id; ?>" tabindex="-1"
                                        aria-hidden="true">
                                        <!--begin::Modal dialog-->
                                        <div class="modal-dialog modal-dialog-centered mw-450px">
                                            <!--begin::Modal content-->
                                            <div class="modal-content">
                                                <!--begin::Form-->
                                                <form class="form" method="POST" id="kt_modal_update_user_form">
                                                    <input type="hidden" name="quizID" value="<?php echo $quiz->_id; ?>">
                                                    <!--begin::Modal header-->
                                                    <div class="modal-header" id="kt_modal_update_user_header">
                                                        <!--begin::Modal title-->
                                                        <h2 class="fs-2 fw-bolder">
                                                            <?php echo $restauration; ?>
                                                        </h2>
                                                        <!--end::Modal title-->
                                                        <!--begin::Close-->
                                                        <div class="btn btn-icon btn-sm btn-active-icon-primary"
                                                            data-kt-users-modal-action="close" data-bs-dismiss="modal">
                                                            <!--begin::Svg Icon | path: icons/duotune/arrows/arr061.svg-->
                                                            <span class="svg-icon svg-icon-1">
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="24"
                                                                    height="24" viewBox="0 0 24 24" fill="none">
                                                                    <rect opacity="0.5" x="6" y="17.3137" width="16"
                                                                        height="2" rx="1"
                                                                        transform="rotate(-45 6 17.3137)"
                                                                        fill="black" />
                                                                    <rect x="7.41422" y="6" width="16" height="2" rx="1"
                                                                        transform="rotate(45 7.41422 6)" fill="black" />
                                                                </svg>
                                                            </span>
                                                            <!--end::Svg Icon-->
                                                        </div>
                                                        <!--end::Close-->
                                                    </div>
                                                    <!--end::Modal header-->
                                                    <!--begin::Modal body-->
                                                    <div class="modal-body py-10 px-lg-17">
                                                        <h4>
                                                            <?php echo $restore_text; ?>
                                                        </h4>
                                                    </div>
                                                    <!--end::Modal body-->
                                                    <!--begin::Modal footer-->
                                                    <div class="modal-footer flex-center">
                                                        <!--begin::Button-->
                                                        <button type="reset" class="btn btn-light me-3"
                                                            id="closeDesactivate" data-bs-dismiss="modal"
                                                            data-kt-users-modal-action="cancel">
                                                            <?php echo $non; ?>
                                                        </button>
                                                        <!--end::Button-->
                                                        <!--begin::Button-->
                                                        <button type="submit" name="active" class=" btn btn-primary">
                                                            <?php echo $oui; ?>
                                                        </button>
                                                        <!--end::Button-->
                                                    </div>
                                                    <!--end::Modal footer-->
                                                </form>
                                                <!--end::Form-->
                                            </div>
                                        </div>
                                        <!-- end Modal dialog -->
                                    </div>
                                    <!-- end:: Modal - Confirm suspend -->
                        <!--begin::Modal - Invite Friends-->
                        <div class="modal fade" id="kt_modal_invite_users<?php echo $quiz->_id; ?>" tabindex="-1"
                            aria-hidden="true">
                            <!--begin::Modal dialog-->
                            <div class="modal-dialog mw-650px">
                                <!--begin::Modal content-->
                                <div class="modal-content">
                                    <!--begin::Modal header-->
                                    <div class="modal-header pb-0 border-0 justify-content-end">
                                        <!--begin::Close-->
                                        <div class="btn btn-sm btn-icon btn-active-color-primary"
                                            data-bs-dismiss="modal">
                                            <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span
                                                    class="path2"></span></i>
                                        </div>
                                        <!--end::Close-->
                                    </div>
                                    <!--begin::Modal header-->
                                </div>
                                <!--end::Modal content-->
                            </div>
                            <!--end::Modal dialog-->
                        </div>
                        <!--end::Modal - Invite Friend-->
                        <?php }
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
<?php include_once "partials/footer.php"; ?>
<?php
} ?>
