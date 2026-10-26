<?php
require_once __DIR__ . '/../../core/medacap_config.php';
session_start();
include_once "../language.php";

if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
} else {
    if (isset($_POST["submit"])) {
        require_once "../../vendor/autoload.php";

        // Create connection
        $conn = new MongoDB\Client(medacapMongoUri());

        // Connecting in database
        $academy = $conn->academy;

        // Connecting in collections
        $trainings = $academy->trainings;

        $label = $_POST["label"];
        $code = $_POST["code"];
        $marques = strtoupper($_POST["brand"]);
        $specialite = $_POST["speciality"];
        $niveau = $_POST["level"];
        $startDte = array_map('trim', explode(",", $_POST["startDate"] ?? ''));
        $endDte = array_map('trim', explode(",", $_POST["endDate"] ?? ''));
        $typ = $_POST["type"];
        $place = array_map('trim', explode(",", $_POST["place"] ?? ''));
        $link = $_POST["link"];
        $trainer = $_POST["trainer"];
        $duration = $_POST["duration"];

        
        if(empty($code) || empty($label) || empty($typ) || empty($niveau)) {
            $error = $champ_obligatoire;
        } else {
            $exist = $trainings->findOne([
                '$and' => [
                    ["label" => $label], 
                    ["level" => $niveau], 
                    ["type" => $typ]
                ],
            ]);

            // $startDte = [];
            // $endDte = [];
            // foreach($startDt as $i => $start) {
            //     array_push($startDte, $start.' 08:00:00');
            //     array_push($endDte, $endDt[$i].' 17:00:00');
            // }

            if ($exist) {
                $error_msg = $error_training;
            } else {
                $training = [
                    "users" => [],
                    "code" => $code,
                    "label" => $label,
                    "type" => $typ,
                    "brand" => $marques,
                    "level" => $niveau,
                    "link" => $link,
                    "places" => $place,
                    "trainer" => $trainer,
                    "specialities" => $specialite,
                    "duration" => $duration,
                    "startDates" => $startDte,
                    "endDates" => $endDte,
                    "active" => true,
                    "created" => date("d-m-Y H:i:s")
                ];
                $trainings->insertOne($training);
    
                $success_msg = $success_training;
            }
        }
    } 
?>
<?php include_once "partials/header.php"; ?>
<?php include_once "../core/background-manager.php"; setPageBackground("bg-welcome-tech"); openBackgroundContainer(); ?>

<!--begin::Title-->
<title><?php echo $title_training ?> | CFAO Mobility Academy</title>
<!--end::Title-->


<!--begin::Body-->
<div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content"
    data-select2-id="select2-data-kt_content">
    <!--begin::Post-->
    <div class="post fs-6 d-flex flex-column-fluid" id="kt_post" data-select2-id="select2-data-kt_post">
        <!--begin::Container-->
        <div class=" container-xxl " data-select2-id="select2-data-194-27hh">
            <!--begin::Modal body-->
            <div class="container mt-5 w-50">
                <img src="../../public/images/logo.png" alt="10" height="170" style="display: block; max-width: 75%; height: auto; margin-left: 25px;">
                <h1 class='my-3 text-center'><?php echo $title_training ?></h1>

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

                <form method="POST"><br>
                    <!--begin::Input group-->
                    <div id='container' class="row fv-row mb-7">
                        <!--begin::Input group-->
                        <div class="d-flex flex-column mb-7 fv-row">
                            <!--begin::Label-->
                            <label class="form-label fw-bolder text-dark fs-6">
                                <span class="required"><?php echo $training_code ?></span>
                            </label>
                            <!--end::Label-->
                            <!--begin::Input-->
                            <input type='text' class='form-control form-control-solid' placeholder='' name='code'/>
                            <!--end::Input-->
                            <?php if (isset($error)) { ?>
                                    <span class='text-danger'>
                                        <?php echo $error; ?>
                                    </span>
                            <?php } ?>
                        </div>
                        <!--end::Input group-->
                        <!--begin::Input group-->
                        <div class="d-flex flex-column mb-7 fv-row">
                            <!--begin::Label-->
                            <label class="form-label fw-bolder text-dark fs-6">
                                <span class="required"><?php echo $label_training ?></span>
                            </label>
                            <!--end::Label-->
                            <!--begin::Input-->
                            <input type='text' class='form-control form-control-solid' placeholder='' name='label'/>
                            <!--end::Input-->
                            <?php if (isset($error)) { ?>
                                    <span class='text-danger'>
                                        <?php echo $error; ?>
                                    </span>
                            <?php } ?>
                        </div>
                        <!--end::Input group-->
                        <!--begin::Input group-->
                        <div class='d-flex flex-column mb-7 fv-row'>
                            <!--begin::Label-->
                            <label class='form-label fw-bolder text-dark fs-6'>
                                <span class='required'><?php echo $type; ?></span>
                                </span>
                            </label>
                            <!--end::Label-->
                            <!--begin::Input-->
                            <select name='type' aria-label='Select a Country'
                                data-placeholder='<?php echo $select_type; ?>' class='form-select form-select-solid fw-bold bg-white'>
                                <option disabled selected><?php echo $select_type; ?></option>
                                <option value='Coaching'>
                                    <?php echo $coaching; ?>
                                </option>
                                <option value='Distancielle'>
                                    <?php echo $formation_distancielle; ?>
                                </option>
                                <option value='E-learning'>
                                    <?php echo $formation_online; ?>
                                </option>
                                <option value='Présentielle'>
                                    <?php echo $formation_presentielle; ?>
                                </option>
                                <option value='Mentoring'>
                                    <?php echo $mentoring; ?>
                                </option>
                            </select>
                            <!--end::Input-->
                            <?php if (isset($error)) { ?>
                            <span class='text-danger'>
                                <?php echo $error; ?>
                            </span>
                            <?php } ?>
                        </div>
                        <!--end::Input group-->
                        <!--begin::Input group-->
                        <div class='d-flex flex-column mb-7 fv-row'>
                            <!--begin::Label-->
                            <label class='form-label fw-bolder text-dark fs-6'>
                                <span class='required'><?php echo $Level; ?></span>
                                </span>
                            </label>
                            <!--end::Label-->
                            <!--begin::Input-->
                            <select name='level' aria-label='Select a Country'
                                data-placeholder='<?php echo $select_level; ?>'
                                data-dropdown-parent='#kt_modal_add_customer' class='form-select form-select-solid fw-bold bg-white'>
                                <option disabled selected><?php echo $select_level; ?></option>
                                <option value='Junior'>
                                    <?php echo $junior; ?>
                                </option>
                                <option value='Senior'>
                                    <?php echo $senior; ?>
                                </option>
                                <option value='Expert'>
                                    <?php echo $expert; ?>
                                </option>
                            </select>
                            <!--end::Input-->
                            <?php if (isset($error)) { ?>
                            <span class='text-danger'>
                                <?php echo $error; ?>
                            </span>
                            <?php } ?>
                        </div>
                        <!--end::Input group-->
                        <!--begin::Input group-->
                        <div class="d-flex flex-column mb-7 fv-row" style="margin-top: 10px">
                            <!--begin::Label-->
                            <label class="form-label fw-bolder text-dark fs-6">
                                <span class="required"><?php echo $Brand ?></span>
                            </label>
                            <!--end::Label-->
                            <!--begin::Input-->
                            <select name="brand" aria-label="Select a Country" data-control="select2"
                                data-placeholder="<?php echo $select_brand ?>"
                                class="form-select form-select-solid fw-bold bg-white">
                                <option disabled selected><?php echo $select_brand ?></option>
                                <option value="FUSO">
                                    <?php echo $fuso ?>
                                </option>
                                <option value="HINO">
                                    <?php echo $hino ?>
                                </option>
                                <option value="JCB">
                                    <?php echo $jcb ?>
                                </option>
                                <option value="KING LONG">
                                    <?php echo $kingLong ?>
                                </option>
                                <option value="LOVOL">
                                    <?php echo $lovol ?>
                                </option>
                                <option value="MERCEDES TRUCK">
                                    <?php echo $mercedesTruck ?>
                                </option>
                                <option value="RENAULT TRUCK">
                                    <?php echo $renaultTruck ?>
                                </option>
                                <option value="SINOTRUCK">
                                    <?php echo $sinotruk ?>
                                </option>
                                <option value="TOYOTA BT">
                                    <?php echo $toyotaBt ?>
                                </option>
                                <option value="TOYOTA FORKLIFT">
                                    <?php echo $toyotaForklift ?>
                                </option>
                                <option value="BYD">
                                    <?php echo $byd ?>
                                </option>
                                <option value="CITROEN">
                                    <?php echo $citroen ?>
                                </option>
                                <option value="MERCEDES">
                                    <?php echo $mercedes ?>
                                </option>
                                <option value="MITSUBISHI">
                                    <?php echo $mitsubishi ?>
                                </option>
                                <option value="PEUGEOT">
                                    <?php echo $peugeot ?>
                                </option>
                                <option value="SUZUKI">
                                    <?php echo $suzuki ?>
                                </option>
                                <option value="TOYOTA">
                                    <?php echo $toyota ?>
                                </option>
                                <option value="YAMAHA BATEAU">
                                    <?php echo $yamahaBateau ?>
                                </option>
                                <option value="YAMAHA MOTO">
                                    <?php echo $yamahaMoto ?>
                                </option>
                            </select>
                            <!--end::Input-->
                            <?php if (isset($error)) { ?>
                            <span class='text-danger'>
                                <?php echo $error; ?>
                            </span>
                            <?php } ?>
                        </div>
                        <!--end::Input group-->
                        <!--begin::Input group-->
                        <div class="d-flex flex-column mb-7 fv-row">
                            <!--begin::Label-->
                            <label class="form-label fw-bolder text-dark fs-6">
                                <span class="required"><?php echo $Speciality ?></span>
                            </label>
                            <!--end::Label-->
                            <!--begin::Input-->
                            <select name="speciality[]" multiple aria-label="Select a Country" data-control="select2"
                                data-placeholder="<?php echo $select_speciality ?>"
                                class="form-select form-select-solid fw-bold bg-white">
                                <option disabled selected><?php echo $select_speciality ?></option>
                                <option value="Arbre de Transmission">
                                    <?php echo $arbre ?>
                                </option>
                                <option value="Assistance à la Conduite">
                                    <?php echo $assistanceConduite ?>
                                </option>
                                <option value="Boite de Transfert">
                                    <?php echo $transfert ?>
                                </option>
                                <option value="Boite de Vitesse">
                                    <?php echo $boite_vitesse ?>
                                </option>
                                <option value="Boite de Vitesse Automatique">
                                    <?php echo $boite_vitesse_auto ?>
                                </option>
                                <option value="Boite de Vitesse Mécanique">
                                    <?php echo $boite_vitesse_meca ?>
                                </option>
                                <option value="Boite de Vitesse à Variation Continue">
                                    <?php echo $boite_vitesse_VC ?>
                                </option>
                                <option value="Climatisation">
                                    <?php echo $clim ?>
                                </option>
                                <option value="Demi Arbre de Roue">
                                    <?php echo $demi ?>
                                </option>
                                <option value="Direction">
                                    <?php echo $direction ?>
                                </option>
                                <option value="Electricité et Electronique">
                                    <?php echo $elec ?>
                                </option>
                                <option value="Freinage">
                                    <?php echo $freinage ?>
                                </option>
                                <option value="Freinage Electromagnétique">
                                    <?php echo $freinageElec ?>
                                </option>
                                <option value="Freinage Hydraulique">
                                    <?php echo $freinageHydro ?>
                                </option>
                                <option value="Freinage Pneumatique">
                                    <?php echo $freinagePneu ?>
                                </option>
                                <option value="Hydraulique">
                                    <?php echo $hydraulique ?>
                                </option>
                                <option value="Moteur Diesel">
                                    <?php echo $moteurDiesel ?>
                                </option>
                                <option value="Moteur Electrique">
                                    <?php echo $moteurElectrique ?>
                                </option>
                                <option value="Moteur Essence">
                                    <?php echo $moteurEssence ?>
                                </option>
                                <option value="Moteur Thermique">
                                    <?php echo $moteurThermique ?>
                                </option>
                                <option value="Réseaux de Communication">
                                    <?php echo $multiplexage ?>
                                </option>
                                <option value="Pneumatique">
                                   <?php echo $pneu ?>
                                </option>
                                <option value="Pont">
                                    <?php echo $pont ?>
                                </option>
                                <option value="Réducteur">
                                    <?php echo $reducteur ?>
                                </option>
                                <option value="Suspension">
                                    <?php echo $suspension ?>
                                </option>
                                <option value="Suspension à Lame">
                                    <?php echo $suspensionLame ?>
                                </option>
                                <option value="Suspension Ressort">
                                    <?php echo $suspensionRessort ?>
                                </option>
                                <option value="Suspension Pneumatique">
                                    <?php echo $suspensionPneu ?>
                                </option>
                                <option value="Transversale">
                                    <?php echo $transversale ?>
                                </option>
                            </select>
                            <!--end::Input-->
                            <?php if (isset($error)) { ?>
                            <span class='text-danger'>
                                <?php echo $error; ?>
                            </span>
                            <?php } ?>
                        </div>
                        <!--end::Input group-->
                        <!--begin::Input group-->
                        <div class="d-flex flex-column mb-7 fv-row">
                            <!--begin::Label-->
                            <label class="form-label fw-bolder text-dark fs-6">
                                <span class=""><?php echo $training_link ?></span>
                            </label>
                            <!--end::Label-->
                            <!--begin::Input-->
                            <input type='text' class='form-control form-control-solid' placeholder='' name='link'/>
                            <!--end::Input-->
                            <?php if (isset($error)) { ?>
                                    <span class='text-danger'>
                                        <?php echo $error; ?>
                                    </span>
                            <?php } ?>
                        </div>
                        <!--end::Input group-->
                        <!--begin::Input group-->
                        <div class="d-flex flex-column mb-7 fv-row">
                            <!--begin::Label-->
                            <label class="form-label fw-bolder text-dark fs-6">
                                <span class="required"><?php echo $training_location ?></span>
                            </label>
                            <!--end::Label-->
                            <!--begin::Input-->
                            <input type='text' class='form-control form-control-solid' placeholder='' name='place'/>
                            <!--end::Input-->
                            <?php if (isset($error)) { ?>
                                    <span class='text-danger'>
                                        <?php echo $error; ?>
                                    </span>
                            <?php } ?>
                        </div>
                        <!--end::Input group-->
                        <!--begin::Input group-->
                        <div class="d-flex flex-column mb-7 fv-row">
                            <!--begin::Label-->
                            <label class="form-label fw-bolder text-dark fs-6">
                                <span class="required"><?php echo $trainer_name ?></span>
                            </label>
                            <!--end::Label-->
                            <!--begin::Input-->
                            <input type='text' class='form-control form-control-solid' placeholder='' name='trainer'/>
                            <!--end::Input-->
                            <?php if (isset($error)) { ?>
                                    <span class='text-danger'>
                                        <?php echo $error; ?>
                                    </span>
                            <?php } ?>
                        </div>
                        <!--end::Input group-->
                        <!--begin::Input group-->
                        <div class='row mb-7'>
                            <!--begin::Col-->
                            <div class='col-md-6 fv-row'>
                                <!--begin::Label-->
                                <label class='required form-label fw-bolder text-dark fs-6'><?php echo $startDate; ?></label>
                                <!--end::Label-->
                                <!--begin::Input-->
                                <input class='form-control form-control-solid' type='date' placeholder='' name='startDate[]' />
                                <!--end::Input-->
                                <?php if (isset($error)) { ?>
                                <span class='text-danger'>
                                    <?php echo $error; ?>
                                </span>
                                <?php } ?>
                            </div>
                            <!--end::Col-->
                            <!--begin::Col-->
                            <div class='col-md-6 fv-row'>
                                <!--begin::Label-->
                                <label class='required form-label fw-bolder text-dark fs-6'><?php echo $endDate; ?></label>
                                <!--end::Label-->
                                <!--begin::Input-->
                                <input class='form-control form-control-solid' type='date' placeholder='' name='endDate[]' />
                                <!--end::Input-->
                                <?php if (isset($error)) { ?>
                                <span class='text-danger'>
                                    <?php echo $error; ?>
                                </span>
                                <?php } ?>
                            </div>
                            <!--end::Col-->
                        </div>
                        <!--end::Input group-->
                        <!--begin::Button-->
                        <button type="button" onclick='addDate()' class="btn btn-primary btn-sm mb-7">Ajouter une date</button>
                        <!--end::Button-->
                    </div>
                    <!--end::Scroll-->
                    <!--end::Modal body-->
                    <!--begin::Modal footer-->
                    <div class="modal-footer flex-center">
                        <!--begin::Button-->
                        <button type="submit" name="submit" class=" btn btn-primary">
                            <span class="indicator-label">
                                <?php echo $valider ?>
                            </span>
                            <span class="indicator-progress">
                                Patientez... <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                            </span>
                        </button>
                        <!--end::Button-->
                    </div>
                    <!--end::Modal footer-->
                </form>
            </div>
            <!--end::Modal body-->
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

    function addDate() {
        var container = document.querySelector('#container');
        var newDateInput = document.createElement("div");
        newDateInput.classList.add("row", "mb-7")
        newDateInput.innerHTML = `
                            <!--begin::Col-->
                            <div class='col-md-6 fv-row'>
                                <!--begin::Label-->
                                <label class='required form-label fw-bolder text-dark fs-6'><?php echo $startDate; ?></label>
                                <!--end::Label-->
                                <!--begin::Input-->
                                <input class='form-control form-control-solid' type='date' placeholder='' name='startDate[]' />
                                <!--end::Input-->
                                <?php if (isset($error)) { ?>
                                <span class='text-danger'>
                                    <?php echo $error; ?>
                                </span>
                                <?php } ?>
                            </div>
                            <!--end::Col-->
                            <!--begin::Col-->
                            <div class='col-md-6 fv-row'>
                                <!--begin::Label-->
                                <label class='required form-label fw-bolder text-dark fs-6'><?php echo $endDate; ?></label>
                                <!--end::Label-->
                                <!--begin::Input-->
                                <input class='form-control form-control-solid' type='date' placeholder='' name='endDate[]' />
                                <!--end::Input-->
                                <?php if (isset($error)) { ?>
                                <span class='text-danger'>
                                    <?php echo $error; ?>
                                </span>
                                <?php } ?>
                            </div>
                            <!--end::Col-->`;

        container.appendChild(newDateInput);
    }
</script>
<?php closeBackgroundContainer(); ?>
<?php include_once "partials/footer.php"; ?>
<?php } ?>
