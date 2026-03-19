<?php
session_start();
include_once "../language.php";

if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
}

$brands = [
    'FUSO' => $fuso, 
    'HINO' => $hino, 
    'JCB' => $jcb, 
    'KING LONG' => $kingLong, 
    'LOVOL' => $lovol, 
    'MERCEDES TRUCK' => $mercedesTruck, 
    'RENAULT TRUCK' => $renaultTruck, 
    'SINOTRUK' => $sinotruk, 
    'TOYOTA BT' => $toyotaBt, 
    'TOYOTA FORKLIFT' => $toyotaForklift, 
    'BYD' => $byd, 
    'CITROEN' => $citroen, 
    'MERCEDES' => $mercedes, 
    'MITSUBISHI' => $mitsubishi, 
    'PEUGEOT' => $peugeot, 
    'SUZUKI' => $suzuki, 
    'TOYOTA' => $toyota
];

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

require_once "../../vendor/autoload.php";

// Create connection
$conn = new MongoDB\Client("mongodb://localhost:27017");
$academy = $conn->academy;
$users = $academy->users;
$trainings = $academy->trainings;

function updateTraining($trainings, $data) {
    $training = [
        "label" => ucfirst($data["label"]),
        "brand" => strtoupper($data["brand"]),
        "type" => $data["type"],
        "level" => ucfirst($data["level"]),
        "places" => array_map('trim', explode(",", strtoupper($data["place"]) ?? '')),
        "startDates" => array_map('trim', explode(",", $data["startDate"] ?? '')),
        "endDates" => array_map('trim', explode(",", $data["endDate"] ?? '')),
        "specialities" => $data["specialities"],
        "link" => $data["link"] ?? '',
        "updated" => date("d-m-Y H:i:s"),
    ];
    $trainings->updateOne(["_id" => new MongoDB\BSON\ObjectId($data["trainingID"])], ['$set' => $training]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST["update"])) {
        updateTraining($trainings, $_POST);
        $success_msg = $success_training_edit;
    } elseif (isset($_POST["delet"])) {
        $id = new MongoDB\BSON\ObjectId($_POST["trainingID"]);
        $trainings->updateOne(["_id" => $id], ['$set' => ["active" => false, "deleted" => date("d-m-Y H:i:s")]]);
        $success_msg = $success_training_delet;
    }
}

include_once "partials/header.php";
<?php include_once "../core/background-manager.php"; setPageBackground("bg-welcome-tech"); openBackgroundContainer(); ?>
?>

<title><?php echo $title_edit_sup_training ?> | CFAO Mobility Academy</title>

<div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content">
    <div class="toolbar" id="kt_toolbar">
        <div class="container-fluid d-flex flex-stack flex-wrap flex-sm-nowrap">
            <div class="d-flex flex-column align-items-start justify-content-center flex-wrap me-2">
                <h1 class="text-dark fw-bold my-1 fs-2"><?php echo $title_edit_sup_training ?></h1>
                <div class="card-title">
                    <div class="d-flex align-items-center position-relative my-1">
                        <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5"></i>
                        <input type="text" id="search" class="form-control form-control-solid w-250px ps-12" placeholder="Recherche...">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($success_msg)) { ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <center><strong><?php echo $success_msg; ?></strong></center>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">&times;</button>
        </div>
    <?php } ?>

    <div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
        <div class="container-xxl">
            <div class="card">
                <div class="card-body pt-0">
                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed fs-6 gy-5" id="kt_customers_table">
                            <thead>
                                <tr class="text-start text-black fw-bold fs-7 text-uppercase gs-0">
                                    <th class="w-10px pe-2 sorting_disabled">
                                        <input class="form-check-input" type="checkbox"
                                         data-kt-check="true" data-kt-check-target="#kt_customers_table .form-check-input">
                                    </th>
                                    <th><?php echo $training_code ?></th>
                                    <th><?php echo $label_training ?></th>
                                    <th><?php echo $Type ?></th>
                                    <th><?php echo $Brand ?></th>
                                    <th><?php echo $Level ?></th>
                                    <th><?php echo $training_location ?></th>
                                    <th><?php echo $edit ?></th>
                                    <th><?php echo $delete ?></th>
                                </tr>
                            </thead>
                            <tbody class="fw-semibold text-gray-600" id="table">
                                <?php
                                $trainingsList = $trainings->find(["active" => true]);
                                foreach ($trainingsList as $training) { ?>
                                    <tr>
                                        <td></td>
                                        <td>
                                            <?php echo $training->code; ?>
                                        </td>
                                        <td><?php echo $training->label; ?></td>
                                        <td><?php echo $training->type; ?></td>
                                        <td><?php echo $training->brand; ?></td>
                                        <td data-order="department">
                                            <?php if (
                                                $training->level == "Junior"
                                            ) { ?>
                                            <span class="badge badge-light-success fs-7 m-1">
                                                <?php echo $training->level; ?>
                                            </span>
                                            <?php } ?>
                                            <?php if (
                                                $training->level == "Senior"
                                            ) { ?>
                                            <span class="badge badge-light-danger fs-7 m-1">
                                                <?php echo $training->level; ?>
                                            </span>
                                            <?php } ?>
                                            <?php if (
                                                $training->level == "Expert"
                                            ) { ?>
                                            <span class="badge badge-light-warning  fs-7 m-1">
                                                <?php echo $training->level; ?>
                                            </span>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <?php
                                                $places = [];
                                                foreach ($training['places'] as $place) {
                                                    $places[] = $place;
                                                }
                                                echo implode(", ", $places); 
                                            ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-icon btn-light-success" data-bs-toggle="modal" data-bs-target="#kt_modal_update_details<?php echo $training->_id; ?>">
                                                <i class="fas fa-edit fs-5"></i>
                                            </button>
                                        </td>
                                        <td>
                                            <button class="btn btn-icon btn-light-danger" data-bs-toggle="modal" data-bs-target="#kt_modal_desactivate<?php echo $training->_id; ?>">
                                                <i class="fas fa-trash fs-5"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <!--begin::Modal - Update group details-->
                                    <div class="modal" id="kt_modal_update_details<?php echo $training->_id; ?>"
                                        tabindex="-1" aria-hidden="true">
                                        <!--begin::Modal dialog-->
                                        <div class="modal-dialog modal-dialog-centered mw-650px">
                                            <!--begin::Modal content-->
                                            <div class="modal-content">
                                                <!--begin::Form-->
                                                <form class="form" method="POST" id="kt_modal_update_user_form">
                                                    <input type="hidden" name="trainingID"
                                                        value="<?php echo $training->_id; ?>">
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
                                                                        class="fs-6 fw-bold mb-2"><?php echo $training_code ?></label>
                                                                    <!--end::Label-->
                                                                    <!--begin::Input-->
                                                                    <input type="text"
                                                                        class="form-control form-control-solid"
                                                                        placeholder="" name="code"
                                                                        value="<?php echo $training->code; ?>" />
                                                                    <!--end::Input-->
                                                                </div>
                                                                <!--end::Input group-->
                                                                <!--begin::Input group-->
                                                                <div class="fv-row mb-7">
                                                                    <!--begin::Label-->
                                                                    <label class="fs-6 fw-bold mb-2"><?php echo $label_training ?></label>
                                                                    <!--end::Label-->
                                                                    <!--begin::Input-->
                                                                    <input type="text"
                                                                        class="form-control form-control-solid"
                                                                        placeholder="" name="label"
                                                                        value="<?php echo $training->label; ?>" />
                                                                    <!--end::Input-->
                                                                </div>
                                                                <!--end::Input group-->
                                                                <!--begin::Input group-->
                                                                <div class='d-flex flex-column mb-7 fv-row'>
                                                                    <!--begin::Label-->
                                                                    <label class='form-label fw-bold text-dark fs-6'>
                                                                    <span><?php echo $Type ?></span> 
                                                                    </label>
                                                                    <!--end::Label-->
                                                                    <!--begin::Input-->
                                                                    <select name='type' aria-label='Select a Country' data-placeholder='Niveau de la formation' 
                                                                        class='form-select form-select-solid fw-bold'>
                                                                            <?php 
                                                                            // On ne montre pas l'option correspondant à la valeur actuelle
                                                                            $types = ['Coaching', 'Distancielle', 'Présentielle', 'E-learning', 'Mentoring'];
                                                                            
                                                                            foreach ($types as $type): 
                                                                            ?>
                                                                                <option value="<?php echo htmlspecialchars($type); ?>" <?php echo ($type == $training['type']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($type); ?></option>
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
                                                                    <label class="form-label fw-bold text-dark fs-6">
                                                                        <span><?php echo $Brand ?></span>
                                                                    </label>
                                                                    <!--end::Label-->
                                                                    <!--begin::Input-->
                                                                    <select name="brand" aria-label="Select a Country"
                                                                        class="form-select form-select-solid fw-bold">
                                                                        <?php foreach ($brands as $key => $brand): ?>
                                                                            <option value="<?php echo htmlspecialchars($key); ?>" <?php echo ($key == $training['brand']) ? 'selected' : ''; ?>>
                                                                                <?php echo htmlspecialchars($brand); ?>
                                                                            </option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                    <!--end::Input-->
                                                                </div>
                                                                <!--end::Input group-->
                                                                <!--begin::Input group-->
                                                                <div class='d-flex flex-column mb-7 fv-row'>
                                                                    <!--begin::Label-->
                                                                    <label class='form-label fw-bold text-dark fs-6'>
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
                                                                                <option value="<?php echo htmlspecialchars($level); ?>" <?php echo ($level == $training['level']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                                                                            <?php 
                                                                            endforeach; 
                                                                            ?>
                                                                    </select>
                                                                    <!--end::Input-->
                                                                </div>
                                                                <!--end::Input group-->
                                                                <?php if($training['type'] == 'E-learning' || $training['type'] == 'Distancielle'): ?>
                                                                    <!--begin::Input group-->
                                                                    <div class="fv-row mb-7">
                                                                        <!--begin::Label-->
                                                                        <label class="fs-6 fw-bold mb-2"><?php echo $training_link ?></label>
                                                                        <!--end::Label-->
                                                                        <!--begin::Input-->
                                                                        <input type="text"
                                                                            class="form-control form-control-solid"
                                                                            placeholder="" name="link"
                                                                            value="<?php echo $training->link; ?>" />
                                                                        <!--end::Input-->
                                                                    </div>
                                                                    <!--end::Input group-->
                                                                <?php endif; ?>
                                                                <!--begin::Input group-->
                                                                <div class="fv-row mb-7">
                                                                    <!--begin::Label-->
                                                                    <label class="fs-6 fw-bold mb-2"><?php echo $training_location ?></label>
                                                                    <!--end::Label-->
                                                                    <!--begin::Input-->
                                                                    <input type="text"
                                                                        class="form-control form-control-solid"
                                                                        placeholder="" name="place"
                                                                        value="<?php echo implode(", ", $places);  ?>" />
                                                                    <!--end::Input-->
                                                                </div>
                                                                <!--end::Input group-->
                                                                <!--begin::Input group-->
                                                                <div class="d-flex flex-column mb-7 fv-row">
                                                                    <!--begin::Label-->
                                                                    <label class="form-label fw-bold text-dark fs-6">
                                                                        <span><?php echo $Speciality ?></span>
                                                                    </label>
                                                                    <!--end::Label-->
                                                                    <!--begin::Input-->
                                                                    <select name="specialities[]" multiple aria-label="Select a Country"
                                                                        data-control="select2"
                                                                        data-placeholder="<?php echo $select_speciality ?>"
                                                                        class="form-select form-select-solid fw-bold">
                                                                        <?php 
                                                                            $specialities = []; 
                                                                            foreach ($training['specialities'] as $bra) {
                                                                                array_push($specialities, $bra);
                                                                            }
                                                                            foreach ($options as $value => $label) : ?>
                                                                            <option value="<?php echo $value; ?>" <?php echo in_array($value, $specialities) ? 'selected' : ''; ?>>
                                                                                <?php echo $label; ?></option>
                                                                            <?php endforeach; ?>
                                                                    </select>
                                                                    <!--end::Input-->
                                                                </div>
                                                                <!--end::Input group-->
                                                                <!--begin::Input group-->
                                                                <?php
                                                                    $stDate = [];
                                                                    foreach ($training['startDates'] as $starDate) :
                                                                        $stDate[] = $starDate;
                                                                    endforeach;
                                                                ?>
                                                                <div class="fv-row mb-7">
                                                                    <!--begin::Label-->
                                                                    <label class="fs-6 fw-bold mb-2"><?php echo $startDate ?></label>
                                                                    <!--end::Label-->
                                                                    <!--begin::Input-->
                                                                    <input type="text"
                                                                        class="form-control form-control-solid"
                                                                        placeholder="" name="startDate"
                                                                        value="<?php echo implode(", ", $stDate);  ?>" />
                                                                    <!--end::Input-->
                                                                </div>
                                                                <!--end::Input group-->
                                                                <!--begin::Input group-->
                                                                <?php
                                                                    $edDate = [];
                                                                    foreach ($training['endDates'] as $endDte) :
                                                                        $edDate[] = $endDte;
                                                                    endforeach;
                                                                ?>
                                                                <div class="fv-row mb-7">
                                                                    <!--begin::Label-->
                                                                    <label class="fs-6 fw-bold mb-2"><?php echo $endDate ?></label>
                                                                    <!--end::Label-->
                                                                    <!--begin::Input-->
                                                                    <input type="text"
                                                                        class="form-control form-control-solid"
                                                                        placeholder="" name="endDate"
                                                                        value="<?php echo implode(", ", $edDate);  ?>" />
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
                                    <!-- Modal for deletion confirmation -->
                                    <div class="modal" id="kt_modal_desactivate<?php echo $training->_id; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered mw-450px">
                                            <div class="modal-content">
                                                <form method="POST">
                                                    <input type="hidden" name="trainingID" value="<?php echo $training->_id; ?>">
                                                    <div class="modal-header">
                                                        <h2 class="fs-2 fw-bolder"><?php echo $delet ?></h2>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <h4><?php echo $delet_text ?></h4>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?php echo $non ?></button>
                                                        <button type="submit" name="delet" class="btn btn-danger"><?php echo $oui ?></button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const closeButtons = document.querySelectorAll('.alert .close');
        closeButtons.forEach(button => {
            button.addEventListener('click', function() {
                this.closest('.alert').remove();
            });
        });
    });
</script>

<?php closeBackgroundContainer(); ?>
<?php include_once "partials/footer.php"; ?>