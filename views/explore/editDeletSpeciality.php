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
    $specialities = $academy->specialities;
    $allocations = $academy->allocations;

    if (isset($_POST["quizze"])) {
        $id = $_POST["groupID"];
        $label = $_POST["label"];
        $type = $_POST["type"];
        $level = $_POST["level"];
        $quiz = $_POST["quizze"];

        $quize = [];
        for ($i = 0; $i < count($quiz); $i++) {
            array_push($quize, new MongoDB\BSON\ObjectId($quiz[$i]));
        }
        $groups = [
            "quizzes" => $quize,
            "label" => ucfirst($label),
            "type" => $type,
            "level" => ucfirst($level),
            "total" => count($quize),
            "updated" => date("d-m-Y H:i:s"),
        ];

        $specialities->updateOne(
            ["_id" => new MongoDB\BSON\ObjectId($id)],
            ['$set' => $groups]
        );
        $success_msg = $success_speciality_edit;
    }

    if (isset($_POST["update"])) {
        $id = $_POST["groupID"];
        $label = $_POST["label"];
        $type = $_POST["type"];
        $level = $_POST["level"];

        $vehicle = [
            "label" => ucfirst($label),
            "type" => $type,
            "level" => ucfirst($level),
            "updated" => date("d-m-Y H:i:s"),
        ];

        $specialities->updateOne(
            ["_id" => new MongoDB\BSON\ObjectId($id)],
            ['$set' => $vehicle]
        );
        $success_msg = $success_speciality_edit;
    }

    if (isset($_POST["delet"])) {
        $id = new MongoDB\BSON\ObjectId($_POST["groupID"]);
        $vehicle = $specialities->findOne(["_id" => $id]);
        $vehicle->active = false;
        $vehicle->deleted = date("d-m-Y H:i:s");
        $specialities->updateOne(["_id" => $id], ['$set' => $vehicle]);
        $success_msg = $success_speciality_delet;
    }
    ?>
<?php include_once "partials/header.php"; ?>
<!--begin::Title-->
<title><?php echo $title_edit_sup_speciality ?> | CFAO Mobility Academy</title>
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
                    <?php echo $title_edit_sup_speciality ?> </h1>
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
            <!--begin::Actions-->
            <div class="d-flex align-items-center flex-nowrap text-nowrap py-1">
                <!-- <div class="d-flex justify-content-end align-items-center" style="margin-left: 10px;">
                    <button type="button" id="users" title="Cliquez ici pour voir la liste des techniciens"
                        data-bs-toggle="modal" class="btn btn-primary">
                        Liste techniciens
                    </button>
                </div>
                <div class="d-flex justify-content-end align-items-center" style="margin-left: 10px;">
                    <button type="button" id="questions" title="Cliquez ici pour voir la liste des questions"
                        data-bs-toggle="modal" class="btn btn-primary">
                        Liste questionnaires
                    </button>
                </div>
                <div class="d-flex justify-content-end align-items-center" style="margin-left: 10px;">
                    <button type="button" id="edit" title="Cliquez ici pour modifier le questionnaire"
                        data-bs-toggle="modal" class="btn btn-primary">
                        Modifier
                    </button>
                </div>
                <div class="d-flex justify-content-end align-items-center" style="margin-left: 10px;">
                    <button type="button" id="delete" title="Cliquez ici pour supprimer le questionnaire"
                        data-bs-toggle="modal" class="btn btn-danger">
                        Supprimer
                    </button>
                </div> -->
            </div>
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
                                                <input class="form-check-input" type="checkbox" data-kt-check="true"
                                                    data-kt-check-target="#kt_customers_table .form-check-input"
                                                    value="1">
                                            </div>
                                        </th>
                                        <th class="min-w-125px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Customer Name: activate to sort column ascending"
                                            style="width: 125px;"><?php echo $Speciality ?>
                                        </th>
                                        <th class="min-w-125px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Company: activate to sort column ascending"
                                            style="width: 134.188px;"><?php echo $Type ?>
                                        </th>
                                        <th class="min-w-125px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Created Date: activate to sort column ascending"
                                            style="width: 152.719px;"><?php echo $Level ?>
                                        </th>
                                        <th class="min-w-125px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Created Date: activate to sort column ascending"
                                            style="width: 152.719px;"><?php echo $total_quiz ?>
                                        </th>
                                        <th class="min-w-100px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Created Date: activate to sort column ascending"
                                            style="width: 152.719px;"><?php echo $edit ?>
                                        </th>
                                        <th class="min-w-100px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Created Date: activate to sort column ascending"
                                            style="width: 152.719px;"><?php echo $delete ?>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="fw-semibold text-gray-600" id="table">
                                    <?php
                                    $group = $specialities->find([
                                        "active" => true,
                                    ]);
                                    foreach ($group as $group) { ?>
                                    <tr class="odd" etat="<?php echo $group->active; ?>">
                                        <!-- <td>
                                            <div class="form-check form-check-sm form-check-custom form-check-solid">
                                                <input class="form-check-input" id="checkbox" type="checkbox"
                                                    onclick="enable()" value="<?php echo $group->_id; ?>">
                                            </div>
                                        </td> -->
                                        <td></td>
                                        <td data-filter=" search">
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#kt_modal_add_customer"
                                                class="text-gray-800 text-hover-primary mb-1">
                                                <?php echo $group->label; ?>
                                            </a>
                                        </td>
                                        <td data-filter="phone">
                                            <?php if (
                                                $group->type == "Factuelle"
                                            ) { ?>
                                            <span class="badge badge-light-success fs-7 m-1">
                                                <?php echo $group->type; ?>
                                            </span>
                                            <?php } ?>
                                            <?php if (
                                                $group->type == "Declarative"
                                            ) { ?>
                                            <span class="badge badge-light-warning  fs-7 m-1">
                                                <?php echo $group->type; ?>
                                            </span>
                                            <?php } ?>
                                        </td>
                                        <td data-order="department">
                                            <?php if (
                                                $group->level == "Junior"
                                            ) { ?>
                                            <span class="badge badge-light-success fs-7 m-1">
                                                <?php echo $group->level; ?>
                                            </span>
                                            <?php } ?>
                                            <?php if (
                                                $group->level == "Senior"
                                            ) { ?>
                                            <span class="badge badge-light-danger fs-7 m-1">
                                                <?php echo $group->level; ?>
                                            </span>
                                            <?php } ?>
                                            <?php if (
                                                $group->level == "Expert"
                                            ) { ?>
                                            <span class="badge badge-light-warning  fs-7 m-1">
                                                <?php echo $group->level; ?>
                                            </span>
                                            <?php } ?>
                                        </td>
                                        <td data-order="subsidiary">
                                            <?php echo $group->total; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-icon btn-light-success w-30px h-30px me-3" data-bs-toggle="modal" data-bs-target="#kt_modal_update_details<?php echo $group->_id; ?>">
                                                <i class="fas fa-edit fs-5"></i></button>
                                        </td>
                                        <td>
                                            <button class="btn btn-icon btn-light-danger w-30px h-30px" data-bs-toggle="modal" data-bs-target="#kt_modal_desactivate<?php echo $group->_id; ?>">
                                                <i class="fas fa-trash fs-5"></i></button>
                                        </td>
                                    </tr>
                                    <!-- begin:: Modal - Confirm suspend -->
                                    <div class="modal" id="kt_modal_desactivate<?php echo $group->_id; ?>"
                                        tabindex="-1" aria-hidden="true">
                                        <!--begin::Modal dialog-->
                                        <div class="modal-dialog modal-dialog-centered mw-450px">
                                            <!--begin::Modal content-->
                                            <div class="modal-content">
                                                <!--begin::Form-->
                                                <form class="form" method="POST" id="kt_modal_update_user_form">
                                                    <input type="hidden" name="groupID"
                                                        value="<?php echo $group->_id; ?>">
                                                    <!--begin::Modal header-->
                                                    <div class="modal-header" id="kt_modal_update_user_header">
                                                        <!--begin::Modal title-->
                                                        <h2 class="fs-2 fw-bolder">
                                                            <?php echo $delet ?>
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
                                                            <?php echo $delet_text ?>
                                                        </h4>
                                                    </div>
                                                    <!--end::Modal body-->
                                                    <!--begin::Modal footer-->
                                                    <div class="modal-footer flex-center">
                                                        <!--begin::Button-->
                                                        <button type="reset" class="btn btn-light me-3"
                                                            id="closeDesactivate" data-bs-dismiss="modal"
                                                            data-kt-users-modal-action="cancel">
                                                            <?php echo $non ?>
                                                        </button>
                                                        <!--end::Button-->
                                                        <!--begin::Button-->
                                                        <button type="submit" name="delet" class=" btn btn-danger">
                                                            <?php echo $oui ?>
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
                                    <!--begin::Modal - Update group details-->
                                    <div class="modal" id="kt_modal_update_details<?php echo $group->_id; ?>"
                                        tabindex="-1" aria-hidden="true">
                                        <!--begin::Modal dialog-->
                                        <div class="modal-dialog modal-dialog-centered mw-650px">
                                            <!--begin::Modal content-->
                                            <div class="modal-content">
                                                <!--begin::Form-->
                                                <form class="form" method="POST" id="kt_modal_update_user_form">
                                                    <input type="hidden" name="groupID"
                                                        value="<?php echo $group->_id; ?>">
                                                    <!--begin::Modal header-->
                                                    <div class="modal-header" id="kt_modal_update_user_header">
                                                        <!--begin::Modal title-->
                                                        <h2 class="fs-2 fw-bolder">
                                                            <?php echo $editer_data ?></h2>
                                                        <!--end::Modal title-->
                                                        <!--begin::Close-->
                                                        <div class="btn btn-icon btn-sm btn-active-icon-primary"
                                                            data-kt-users-modal-action="close" data-bs-dismiss="modal"
                                                            data-kt-menu-dismiss="true">
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
                                                        <!--begin::Scroll-->
                                                        <div class="d-flex flex-column scroll-y me-n7 pe-7"
                                                            id="kt_modal_update_user_scroll" data-kt-scroll="true"
                                                            data-kt-scroll-activate="{default: false, lg: true}"
                                                            data-kt-scroll-max-height="auto"
                                                            data-kt-scroll-dependencies="#kt_modal_update_user_header"
                                                            data-kt-scroll-wrappers="#kt_modal_update_user_scroll"
                                                            data-kt-scroll-offset="300px">
                                                            <!--begin::User form-->
                                                            <div id="kt_modal_update_user_user_info"
                                                                class="collapse show">
                                                                <!--begin::Input group-->
                                                                <div class="fv-row mb-7">
                                                                    <!--begin::Label-->
                                                                    <label
                                                                        class="fs-6 fw-bold mb-2"><?php echo $Speciality ?></label>
                                                                    <!--end::Label-->
                                                                    <!--begin::Input-->
                                                                    <input type="text"
                                                                        class="form-control form-control-solid"
                                                                        placeholder="" name="label"
                                                                        value="<?php echo $group->label; ?>" />
                                                                    <!--end::Input-->
                                                                </div>
                                                                <!--end::Input group-->
                                                                <!--begin::Input group-->
                                                                <div class="fv-row mb-7">
                                                                    <!--begin::Label-->
                                                                    <label class="fs-6 fw-bold mb-2"><?php echo $Type ?></label>
                                                                    <!--end::Label-->
                                                                    <!--begin::Input-->
                                                                    <input type="text"
                                                                        class="form-control form-control-solid"
                                                                        placeholder="" name="type"
                                                                        value="<?php echo $group->type; ?>" />
                                                                    <!--end::Input-->
                                                                </div>
                                                                <!--end::Input group-->
                                                                <!--begin::Input group-->
                                                                <div class='d-flex flex-column mb-7 fv-row'>
                                                                  <!--begin::Label-->
                                                                  <label class='form-label fw-bolder text-dark fs-6'>
                                                                    <span><?php echo $Level ?></span> <span class='ms-1' data-bs-toggle='tooltip' title="Votre niveau technique">
                                                                      <i class='ki-duotone ki-information fs-7'><span class='path1'></span><span class='path2'></span><span class='path3'></span></i>
                                                                    </span>
                                                                  </label>
                                                                  <!--end::Label-->
                                                                  <!--begin::Input-->
                                                                  <select name='level' aria-label='Select a Country' data-placeholder='Niveau de la formation' 
                                                                      class='form-select form-select-solid fw-bold'>
                                                                          <?php 
                                                                          // On ne montre pas l'option correspondant à la valeur actuelle
                                                                          $levels = ['Junior' => $junior, 'Senior' => $senior, 'Expert' => $expert];
                                                                          
                                                                          foreach ($levels as $level => $label): 
                                                                          ?>
                                                                              <option value="<?php echo htmlspecialchars($level); ?>" <?php echo ($level == $group['level']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                                                                          <?php 
                                                                          endforeach; 
                                                                          ?>
                                                                  </select>
                                                                  <!--end::Input-->
                                                                </div>
                                                                <!--end::Input group-->
                                                                <!--begin::Input group-->
                                                                <div class="d-flex flex-column mb-7 fv-row">
                                                                    <!--begin::Label-->
                                                                    <label class="form-label fw-bolder text-dark fs-6">
                                                                        <span><?php echo $quizs ?></span>
                                                                        <span class="ms-1" data-bs-toggle="tooltip"
                                                                            title="Choississez les questionnaires">
                                                                            <i class="ki-duotone ki-information fs-7"><span
                                                                                    class="path1"></span><span
                                                                                    class="path2"></span><span
                                                                                    class="path3"></span></i>
                                                                        </span>
                                                                    </label>
                                                                    <!--end::Label-->
                                                                    <!--begin::Input-->
                                                                    <select name="quizze[]" multiple aria-label="Select a Country"
                                                                        data-control="select2"
                                                                        data-placeholder="<?php echo $select_quiz ?>"
                                                                        class="form-select form-select-solid fw-bold">
                                                                        <?php
                                                                        if ($group['type'] == 'Factuelle') {
                                                                            $quizz = $quizzes->find(
                                                                                [
                                                                                    '$and' => [
                                                                                        [
                                                                                            "type" => 'Factuel',
                                                                                        ],
                                                                                        [
                                                                                            "level" =>
                                                                                                $group->level,
                                                                                        ],
                                                                                        [
                                                                                            "active" => true,
                                                                                        ],
                                                                                    ],
                                                                                ]
                                                                            )->toArray();
                                                                        } else {
                                                                            $quizz = $quizzes->find(
                                                                                [
                                                                                    '$and' => [
                                                                                        [
                                                                                            "type" => 'Declaratif',
                                                                                        ],
                                                                                        [
                                                                                            "level" =>
                                                                                                $group->level,
                                                                                        ],
                                                                                        [
                                                                                            "active" => true,
                                                                                        ],
                                                                                    ],
                                                                                ]
                                                                            )->toArray();
                                                                        }
                                                                        $vehicleDatas = []; 
                                                                        foreach ($group['quizzes'] as $bra) {
                                                                            array_push($vehicleDatas, $bra);
                                                                        }
                                                                        foreach (
                                                                            $quizz
                                                                            as $quizz
                                                                        ) { ?>
                                                                        <option value="<?php echo $quizz->_id; ?>" <?php echo in_array($quizz['_id'], $vehicleDatas) ? 'selected' : ''; ?>>
                                                                            <?php echo $quizz->label; ?>
                                                                        </option>
                                                                        <?php }
                                                                        ?>
                                                                    </select>
                                                                    <!--end::Input-->
                                                                </div>
                                                                <!--end::Input group-->
                                                            </div>
                                                            <!--end::User form-->
                                                        </div>
                                                        <!--end::Scroll-->
                                                    </div>
                                                    <!--end::Modal body-->
                                                    <!--begin::Modal footer-->
                                                    <div class="modal-footer flex-center">
                                                        <!--begin::Button-->
                                                        <button type="reset" class="btn btn-light me-3"
                                                            data-bs-dismiss="modal" data-kt-menu-dismiss="true"
                                                            data-kt-users-modal-action="cancel"><?php echo $annuler ?></button>
                                                        <!--end::Button-->
                                                        <!--begin::Button-->
                                                        <button type="submit" name="update" class="btn btn-primary">
                                                            <?php echo $valider ?>
                                                        </button>
                                                        <!--end::Button-->
                                                    </div>
                                                    <!--end::Modal footer-->
                                                </form>
                                                <!--end::Form-->
                                            </div>
                                        </div>
                                    </div>
                                    <!--end::Modal - Update user details-->
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
<script>
    // Function to handle closing of the alert message
    document.addEventListener('DOMContentLoaded', function() {
        const closeButtons = document.querySelectorAll('.alert .close');
        closeButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                const alert = this.closest('.alert');
                alert.remove();
            });
        });
    });
</script>
<?php include_once "partials/footer.php"; ?>
<?php
} ?>
