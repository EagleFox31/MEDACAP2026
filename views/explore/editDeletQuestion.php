<?php
session_start();
include_once "../language.php";

if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
}

require_once "../../vendor/autoload.php";

// Create connection
$conn = new MongoDB\Client("mongodb://localhost:27017");
// Connecting to database collections
$academy = $conn->academy;
$users = $academy->users;
$quizzes = $academy->quizzes;
$questions = $academy->questions;
$allocations = $academy->allocations;

// Function to update question with image
function updateQuestionWithImage($questions, $id, $data, $file) {
    $tmp_name = $file["tmp_name"];
    $picture = basename($file["name"]);
    $folder = "../../public/files/" . $picture;

    // Validate file type
    $valid_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (in_array($file['type'], $valid_types) && move_uploaded_file($tmp_name, $folder)) {
        $data['image'] = $picture;
        $questions->updateOne(["_id" => new MongoDB\BSON\ObjectId($id)], ['$set' => $data]);
        return true;
    }
    return false;
}

// Update question data
if (isset($_POST["update"])) {
    $id = $_POST["questionID"];
    $question = $questions->findOne(["_id" => new MongoDB\BSON\ObjectId($id)]);

    $updateData = [
        "ref" => $_POST["ref"] ?? null,
        "label" => ucfirst($_POST["label"] ?? ''),
        "proposal1" => ucfirst($_POST["proposal1"] ?? ''),
        "proposal2" => ucfirst($_POST["proposal2"] ?? ''),
        "proposal3" => ucfirst($_POST["proposal3"] ?? ''),
        "proposal4" => ucfirst($_POST["proposal4"] ?? ''),
        "answer" => ucfirst($_POST["answer"] ?? ''),
        "updated" => date("d-m-Y H:i:s"),
    ];

    if ($question->type == "Declarative") {
        $updateData["proposal1"] = "1-{$question->speciality}-{$question->level}-{$updateData['label']}-1";
        $updateData["proposal2"] = "2-{$question->speciality}-{$question->level}-{$updateData['label']}-2";
        $updateData["proposal3"] = "3-{$question->speciality}-{$question->level}-{$updateData['label']}-3";
    }

    if (!empty($_FILES["image"]["name"])) {
        if (updateQuestionWithImage($questions, $id, $updateData, $_FILES["image"])) {
            $success_msg = $success_question_edit;
        } else {
            $error_msg = "Failed to upload image.";
        }
    } else {
        $questions->updateOne(["_id" => new MongoDB\BSON\ObjectId($id)], ['$set' => $updateData]);
        $success_msg = $success_question_edit;
    }
}

// Update question title
if (isset($_POST['title'])) {
    $questions->updateOne(
        ["_id" => new MongoDB\BSON\ObjectId($_POST["questionID"])],
        ['$set' => ["title" => ucfirst($_POST["title"])]]
    );
    $success_msg = $success_question_edit;
}

// Update question speciality
if (isset($_POST['speciality'])) {
    $id = $_POST["questionID"];
    $speciality = ucfirst($_POST["speciality"]);
    $question = $questions->findOne(["_id" => new MongoDB\BSON\ObjectId($id)]);
    $quizD = $quizzes->findOne(["questions" => new MongoDB\BSON\ObjectId($id)]);

    $quiz = $quizzes->findOne([
        "speciality" => $speciality,
        "level" => $question->level,
        "active" => true,
    ]);

    if ($question->speciality != $speciality) {
        $quizzes->updateOne(
            ["_id" => new MongoDB\BSON\ObjectId($quizD['_id'])],
            ['$pull' => ["questions" => new MongoDB\BSON\ObjectId($id)]]
        );
        $quizzes->updateOne(
            ["_id" => new MongoDB\BSON\ObjectId($quizD['_id'])],
            ['$set' => ['total' => $quizD['total'] - 1]]
        );

        $quizzes->updateOne(
            ["_id" => new MongoDB\BSON\ObjectId($quiz['_id'])],
            ['$push' => ["questions" => new MongoDB\BSON\ObjectId($id)]]
        );
        $quizzes->updateOne(
            ["_id" => new MongoDB\BSON\ObjectId($quiz['_id'])],
            ['$set' => ['total' => $quiz['total'] + 1]]
        );

        $questions->updateOne(
            ["_id" => new MongoDB\BSON\ObjectId($id)],
            ['$set' => ["speciality" => $speciality]]
        );
        $success_msg = $success_question_edit;
    }
}

// Delete question
if (isset($_POST["delet"])) {
    $id = new MongoDB\BSON\ObjectId($_POST["questionID"]);
    $questions->updateOne(
        ["_id" => $id],
        ['$set' => ["active" => false, "deleted" => date("d-m-Y H:i:s")]]
    );

    $quiz = $quizzes->findOne(["questions" => $id, "active" => true]);
    if ($quiz) {
        $quizzes->updateOne(
            ["_id" => new MongoDB\BSON\ObjectId($quiz->_id)],
            ['$set' => ["total" => $quiz['total'] - 1]]
        );
        $quizzes->updateOne(
            ["_id" => new MongoDB\BSON\ObjectId($quiz->_id)],
            ['$pull' => ["questions" => $id]]
        );
    }
    $success_msg = $success_question_delet;
}

$options = [
    "Arbre de Transmission" => $arbre,
    "Assistance à la Conduite" => $assistanceConduite,
    "Boite de Transfert" => $transfert,
    "Boite de Vitesse" => $boite_vitesse,
    "Boite de Vitesse Automatique" => $boite_vitesse_auto,
    "Boite de Vitesse Mécanique" => $boite_vitesse_meca,
    "Boite de Vitesse à Variation Continue" => $boite_vitesse_VC,
    "Climatisation" => $clim,
    "Demi Arbre de Roue" => $demi,
    "Direction" => $direction,
    "Electricité et Electronique" => $elec,
    "Freinage" => $freinage,
    "Freinage Electromagnétique" => $freinageElec,
    "Freinage Hydraulique" => $freinageHydro,
    "Freinage Pneumatique" => $freinagePneu,
    "Hydraulique" => $hydraulique,
    "Moteur Diesel" => $moteurDiesel,
    "Moteur Electrique" => $moteurElectrique,
    "Moteur Essence" => $moteurEssence,
    "Moteur Thermique" => $moteurThermique,
    "Réseaux de Communication" => $multiplexage,
    "Pneumatique" => $pneu,
    "Pont" => $pont,
    "Reducteur" => $reducteur,
    "Suspension" => $suspension,
    "Suspension à Lame" => $suspensionLame,
    "Suspension Ressort" => $suspensionRessort,
    "Suspension Pneumatique" => $suspensionPneu,
    "Transversale" => $transversale
];


include_once "partials/header.php";
?>



<!--begin::Title-->
<title><?php echo $title_edit_sup_question ?> | CFAO Mobility Academy</title>
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
                    <?php echo $title_edit_sup_question ?> </h1>
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
                <!--begin::Card body-->
                <div class="card-body pt-0">
                    <!--begin::Table-->
                    <div id="kt_customers_table_wrapper" class="dataTables_wrapper dt-bootstrap4 no-footer">
                        <div class="table-responsive">
                            <table class="table align-middle table-row-dashed fs-6 gy-5 dataTable no-footer"
                                id="kt_customers_table">
                                <thead>
                                    <tr class="text-start text-black fw-bold fs-7 text-uppercase gs-0">
                                        <th class="w-10px pe-2"></th>
                                        <?php 
                    $headers = [$Ref, $questionType, $Answer, $Type, $Level, $Speciality, $edit, $delete];
                    foreach ($headers as $header) {
                        echo "<th class='min-w-125px sorting'>" . htmlspecialchars($header, ENT_QUOTES, 'UTF-8') . "</th>";
                    }
                    ?>
                                    </tr>
                                </thead>
                                <tbody class="fw-semibold text-gray-600" id="table">
                                    <?php
                $questions = $questions->find(["active" => true]);

                foreach ($questions as $question) {
                    $questionID = htmlspecialchars($question->_id, ENT_QUOTES, 'UTF-8');
                    $active = htmlspecialchars($question->active, ENT_QUOTES, 'UTF-8');
                    $levelBadge = getBadgeClass($question->level);
                    $typeBadge = getBadgeClass($question->type);
                    ?>
                                    <tr class="odd" etat="<?php echo $active; ?>">
                                        <td></td>
                                        <td>
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#kt_modal_add_customer"
                                                class="text-gray-800 text-hover-primary mb-1">
                                                <?php echo htmlspecialchars($question->ref, ENT_QUOTES, 'UTF-8'); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#kt_modal_add_customer"
                                                class="text-gray-800 text-hover-primary mb-1">
                                                <?php echo htmlspecialchars($question->label, ENT_QUOTES, 'UTF-8'); ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($question->answer ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td><span
                                                class="badge <?php echo $typeBadge; ?> fs-7 m-1"><?php echo htmlspecialchars($question->type, ENT_QUOTES, 'UTF-8'); ?></span>
                                        </td>
                                        <td><span
                                                class="badge <?php echo $levelBadge; ?> fs-7 m-1"><?php echo htmlspecialchars($question->level, ENT_QUOTES, 'UTF-8'); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($question->speciality, ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-icon btn-light-success w-30px h-30px me-3"
                                                data-bs-toggle="modal"
                                                data-bs-target="#kt_modal_update_details<?php echo $questionID; ?>">
                                                <i class="fas fa-edit fs-5"></i>
                                            </button>
                                        </td>
                                        <td>
                                            <button class="btn btn-icon btn-light-danger w-30px h-30px"
                                                data-bs-toggle="modal"
                                                data-bs-target="#kt_modal_desactivate<?php echo $questionID; ?>">
                                                <i class="fas fa-trash fs-5"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <!-- Modals for this question -->
                                    <!-- begin:: Modal - Confirm suspend -->
                                    <div class="modal" id="kt_modal_desactivate<?php echo $question->_id; ?>"
                                        tabindex="-1" aria-hidden="true">
                                        <!--begin::Modal dialog-->
                                        <div class="modal-dialog modal-dialog-centered mw-450px">
                                            <!--begin::Modal content-->
                                            <div class="modal-content">
                                                <!--begin::Form-->
                                                <form class="form" method="POST" id="kt_modal_update_user_form">
                                                    <input type="hidden" name="questionID"
                                                        value="<?php echo $question->_id; ?>">
                                                    <!--begin::Modal header-->
                                                    <div class="modal-header">
                                                        <!--begin::Modal title-->
                                                        <h2 class="fs-2 fw-bolder"><?php echo $delet; ?></h2>
                                                        <!--end::Modal title-->
                                                        <!--begin::Close-->
                                                        <button type="button"
                                                            class="btn btn-icon btn-sm btn-active-icon-primary"
                                                            data-bs-dismiss="modal">
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
                                                        </button>
                                                        <!--end::Close-->
                                                    </div>
                                                    <!--end::Modal header-->
                                                    <!--begin::Modal body-->
                                                    <div class="modal-body py-10 px-lg-17">
                                                        <h4><?php echo $delet_text; ?></h4>
                                                    </div>
                                                    <!--end::Modal body-->
                                                    <!--begin::Modal footer-->
                                                    <div class="modal-footer flex-center">
                                                        <!--begin::Button-->
                                                        <button type="button" class="btn btn-light me-3"
                                                            data-bs-dismiss="modal"><?php echo $non; ?></button>
                                                        <!--end::Button-->
                                                        <!--begin::Button-->
                                                        <button type="submit" name="delet"
                                                            class="btn btn-danger"><?php echo $oui; ?></button>
                                                        <!--end::Button-->
                                                    </div>
                                                    <!--end::Modal footer-->
                                                </form>
                                                <!--end::Form-->
                                            </div>
                                        </div>
                                        <!--end::Modal dialog-->
                                    </div>
                                    <!-- end:: Modal - Confirm suspend -->

                                    <!--begin::Modal - Update question details-->
                                    <div class="modal" id="kt_modal_update_details<?php echo $question->_id; ?>"
                                        tabindex="-1" aria-hidden="true">
                                        <!--begin::Modal dialog-->
                                        <div class="modal-dialog modal-dialog-centered mw-650px">
                                            <!--begin::Modal content-->
                                            <div class="modal-content">
                                                <!--begin::Form-->
                                                <form class="form" enctype="multipart/form-data" method="POST"
                                                    id="kt_modal_update_user_form">
                                                    <input type="hidden" name="questionID"
                                                        value="<?php echo $question->_id; ?>">
                                                    <!--begin::Modal header-->
                                                    <div class="modal-header" id="kt_modal_update_user_header">
                                                        <!--begin::Modal title-->
                                                        <h2 class="fs-2 fw-bolder"><?php echo $editer_data; ?></h2>
                                                        <!--end::Modal title-->
                                                        <!--begin::Close-->
                                                        <div class="btn btn-icon btn-sm btn-active-icon-primary"
                                                            data-kt-users-modal-action="close"
                                                            data-kt-menu-dismiss="true" data-bs-dismiss="modal">
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
                                                                    <label
                                                                        class="fs-6 fw-bold mb-2"><?php echo $Ref; ?></label>
                                                                    <input type="text"
                                                                        class="form-control form-control-solid"
                                                                        name="ref"
                                                                        value="<?php echo $question->ref ?? ''; ?>" />
                                                                </div>
                                                                <!--end::Input group-->

                                                                <?php if ($question->level === "Expert") : ?>
                                                                <div class="fv-row mb-7">
                                                                    <label
                                                                        class="fs-6 fw-bold mb-2"><?php echo $titre_question; ?></label>
                                                                    <input type="text"
                                                                        class="form-control form-control-solid"
                                                                        name="title"
                                                                        value="<?php echo $question->title ?? ''; ?>" />
                                                                </div>
                                                                <?php endif; ?>

                                                                <!--begin::Input group-->
                                                                <div class="fv-row mb-7">
                                                                    <label
                                                                        class="fs-6 fw-bold mb-2"><?php echo $label_question; ?></label>
                                                                    <input type="text"
                                                                        class="form-control form-control-solid"
                                                                        name="label"
                                                                        value="<?php echo $question->label; ?>" />
                                                                </div>
                                                                <!--end::Input group-->

                                                                <!--begin::Input group-->
                                                                <div class="fv-row mb-7">
                                                                    <label
                                                                        class="fs-6 fw-bold mb-2"><?php echo $image; ?></label>
                                                                    <input type="file"
                                                                        class="form-control form-control-solid"
                                                                        name="image" />
                                                                </div>
                                                                <!--end::Input group-->

                                                                <?php if ($question->type === "Factuelle") : ?>
                                                                <!--begin::Input group-->
                                                                <?php for ($i = 1; $i <= 4; $i++) : ?>
                                                                <div class="fv-row mb-7">
                                                                    <label
                                                                        class="fs-6 fw-bold mb-2"><?php echo $proposal . ' ' . $i; ?></label>
                                                                    <input type="text"
                                                                        class="form-control form-control-solid"
                                                                        name="proposal<?php echo $i; ?>"
                                                                        value="<?php echo $question->{'proposal' . $i} ?? ''; ?>" />
                                                                </div>
                                                                <?php endfor; ?>
                                                                <!--end::Input group-->

                                                                <!--begin::Input group-->
                                                                <div class="fv-row mb-7">
                                                                    <label
                                                                        class="fs-6 fw-bold mb-2"><span><?php echo $Answer; ?></span></label>
                                                                    <input type="text"
                                                                        class="form-control form-control-solid"
                                                                        name="answer"
                                                                        value="<?php echo $question->answer ?? ''; ?>" />
                                                                </div>
                                                                <!--end::Input group-->
                                                                <?php endif; ?>

                                                                <!--begin::Input group-->
                                                                <div class="d-flex flex-column mb-7 fv-row">
                                                                    <label
                                                                        class="form-label fw-bolder text-dark fs-6"><span><?php echo $Speciality; ?></span></label>
                                                                    <!--begin::Input-->
                                                                    <select name="speciality"
                                                                        aria-label="Select a Country"
                                                                        data-control="select2"
                                                                        data-placeholder="<?php echo $select_speciality; ?>"
                                                                        class="form-select form-select-solid fw-bold">
                                                                        <?php foreach ($options as $value => $label) : ?>
                                                                        <option value="<?php echo $value; ?>" <?php echo ($value == $question['speciality']) ? 'selected' : ''; ?>>
                                                                            <?php echo $label; ?></option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                    <!--end::Input-->

                                                                    <?php if (isset($error)) : ?>
                                                                    <span
                                                                        class='text-danger'><?php echo $error; ?></span>
                                                                    <?php endif; ?>
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
                                                        <button type="reset" class="btn btn-light me-3"
                                                            data-kt-menu-dismiss="true" data-bs-dismiss="modal"
                                                            data-kt-users-modal-action="cancel">
                                                            <?php echo $annuler; ?>
                                                        </button>
                                                        <button type="submit" name="update"
                                                            class="btn btn-primary"><?php echo $valider; ?></button>
                                                    </div>
                                                    <!--end::Modal footer-->
                                                </form>
                                                <!--end::Form-->
                                            </div>
                                        </div>
                                    </div>
                                    <!--end::Modal - Update question details-->
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="row">
                            <div
                                class="col-sm-12 col-md-5 d-flex align-items-center justify-content-center justify-content-md-start">
                                <div class="dataTables_length">
                                    <label>
                                        <select id="kt_customers_table_length" name="kt_customers_table_length"
                                            class="form-select form-select-sm form-select-solid">
                                            <?php 
                        $options = [100, 200, 300, 500];
                        foreach ($options as $opt) {
                            echo "<option value='$opt'>$opt</option>";
                        }
                        ?>
                                        </select>
                                    </label>
                                </div>
                            </div>
                            <div
                                class="col-sm-12 col-md-7 d-flex align-items-center justify-content-center justify-content-md-end">
                                <div class="dataTables_paginate paging_simple_numbers">
                                    <ul class="pagination" id="kt_customers_table_paginate"></ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!--end::Table-->

                    <?php
                    function getBadgeClass($value) {
                        if ($value == 'Factuelle') {
                            return 'badge-light-success';
                        } elseif ($value == 'Declarative') {
                            return 'badge-light-warning';
                        } elseif ($value == 'Junior') {
                            return 'badge-light-success';
                        } elseif ($value == 'Senior') {
                            return 'badge-light-danger';
                        } elseif ($value == 'Expert') {
                            return 'badge-light-warning';
                        }
                        return ''; // Default class
                    }

                    function renderModals($question) {
                        // Implement the rendering for modals here, similar to how they were presented above
                        // This function can handle both the update and delete modals
                    }
                    ?>

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