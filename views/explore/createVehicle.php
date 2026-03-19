<?php
session_start();
include_once "../language.php";

if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
} else {
    if (isset($_POST["submit"])) {
        require_once "../../vendor/autoload.php";

        // Create connection
        $conn = new MongoDB\Client("mongodb://localhost:27017");

        // Connecting in database
        $academy = $conn->academy;

        // Connecting in collections
        $users = $academy->users;
        $vehicles = $academy->vehicles;
        $quizzes = $academy->quizzes;
        $allocations = $academy->allocations;

        $label = $_POST["label"];
        $brands = strtoupper($_POST["brand"]);
        $specialite = $_POST["speciality"];

        $facJu = [];
        $facSe = [];
        $facEx = [];
        $declaJu = [];
        $declaSe = [];
        $declaEx = [];

        $exist = $vehicles->findOne([
            '$and' => [["label" => $label], ["brand" => $brands]],
        ]);

        if ($exist) {
            $error_msg = $error_vehicle;
        } elseif(empty($label) || empty($brands) || empty($specialite)) {
            $error = $champ_obligatoire;
        }else {
            for ($i = 0; $i < count($specialite); $i++) {
                $quizJuFac = $quizzes->findOne([
                    '$and' => [
                        ["speciality" => $specialite[$i]],
                        ["type" => "Factuel"],
                        ["level" => "Junior"],
                        ["active" => true],
                    ],
                ]);
                if ($quizJuFac) {
                    array_push($facJu, $quizJuFac->_id);
                }
            }
            for ($i = 0; $i < count($specialite); $i++) {
                $quizJuDecla = $quizzes->findOne([
                    '$and' => [
                        ["speciality" => $specialite[$i]],
                        ["type" => "Declaratif"],
                        ["level" => "Junior"],
                        ["active" => true],
                    ],
                ]);
                if ($quizJuDecla) {
                    array_push($declaJu, $quizJuDecla->_id);
                }
            }
            for ($i = 0; $i < count($specialite); $i++) {
                $quizSeFac = $quizzes->findOne([
                    '$and' => [
                        ["speciality" => $specialite[$i]],
                        ["type" => "Factuel"],
                        ["level" => "Senior"],
                        ["active" => true],
                    ],
                ]);
                if ($quizSeFac) {
                    array_push($facSe, $quizSeFac->_id);
                }
            }
            for ($i = 0; $i < count($specialite); $i++) {
                $quizSeDecla = $quizzes->findOne([
                    '$and' => [
                        ["speciality" => $specialite[$i]],
                        ["type" => "Declaratif"],
                        ["level" => "Senior"],
                        ["active" => true],
                    ],
                ]);
                if ($quizSeDecla) {
                    array_push($declaSe, $quizSeDecla->_id);
                }
            }
            for ($i = 0; $i < count($specialite); $i++) {
                $quizExFac = $quizzes->findOne([
                    '$and' => [
                        ["speciality" => $specialite[$i]],
                        ["type" => "Factuel"],
                        ["level" => "Expert"],
                        ["active" => true],
                    ],
                ]);
                if ($quizExFac) {
                    array_push($facEx, $quizExFac->_id);
                }
            }
            for ($i = 0; $i < count($specialite); $i++) {
                $quizExDecla = $quizzes->findOne([
                    '$and' => [
                        ["speciality" => $specialite[$i]],
                        ["type" => "Declaratif"],
                        ["level" => "Expert"],
                        ["active" => true],
                    ],
                ]);
                if ($quizExDecla) {
                    array_push($declaEx, $quizExDecla->_id);
                }
            }

            $vehicleJuFac = [
                "users" => [],
                "quizzes" => $facJu,
                "label" => $label,
                "type" => "Factuel",
                "brand" => $brands,
                "level" => "Junior",
                "total" => count($facJu),
                "test" => false,
                "active" => true,
                "created" => date("d-m-Y H:i:s"),
            ];
            $vehicles->insertOne($vehicleJuFac);

            $vehicleSeFac = [
                "users" => [],
                "quizzes" => $facSe,
                "label" => $label,
                "type" => "Factuel",
                "brand" => $brands,
                "level" => "Senior",
                "total" => count($facSe),
                "test" => false,
                "active" => true,
                "created" => date("d-m-Y H:i:s"),
            ];
            $vehicles->insertOne($vehicleSeFac);

            $vehicleExFac = [
                "users" => [],
                "quizzes" => $facEx,
                "label" => $label,
                "type" => "Factuel",
                "brand" => $brands,
                "level" => "Expert",
                "total" => count($facEx),
                "test" => false,
                "active" => true,
                "created" => date("d-m-Y H:i:s"),
            ];
            $vehicles->insertOne($vehicleExFac);

            $vehicleJuDecla = [
                "users" => [],
                "quizzes" => $declaJu,
                "label" => $label,
                "type" => "Factuel",
                "brand" => $brands,
                "level" => "Junior",
                "total" => count($declaJu),
                "test" => false,
                "active" => true,
                "created" => date("d-m-Y H:i:s"),
            ];
            $vehicles->insertOne($vehicleJuDecla);

            $vehicleSeDecla = [
                "users" => [],
                "quizzes" => $declaSe,
                "label" => $label,
                "type" => "Factuel",
                "brand" => $brands,
                "level" => "Senior",
                "total" => count($declaSe),
                "test" => false,
                "active" => true,
                "created" => date("d-m-Y H:i:s"),
            ];
            $vehicles->insertOne($vehicleSeDecla);

            $vehicleExDecla = [
                "users" => [],
                "quizzes" => $declaEx,
                "label" => $label,
                "type" => "Factuel",
                "brand" => $brands,
                "level" => "Expert",
                "total" => count($declaEx),
                "test" => false,
                "active" => true,
                "created" => date("d-m-Y H:i:s"),
            ];
            $vehicles->insertOne($vehicleExDecla);

            $success_msg = $success_vehicle;
        }
    } ?>
<?php include_once "partials/header.php"; ?>

<!--begin::Title-->
<title><?php echo $title_vehicle ?> | CFAO Mobility Academy</title>
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
                <h1 class='my-3 text-center'><?php echo $title_vehicle ?></h1>

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
                    <div class="row fv-row mb-7">
                        <!--begin::Input group-->
                        <div class="d-flex flex-column mb-7 fv-row">
                            <!--begin::Label-->
                            <label class="form-label fw-bolder text-dark fs-6">
                                <span class="required"><?php echo $type_vehicle ?></span>
                                </span>
                            </label>
                            <!--end::Label-->
                            <!--begin::Input-->
                                <select name="label" aria-label="Select a Country"
                                    data-placeholder="-- <?php echo $select_vehicle ?> --"
                                    class="form-select bg-white form-select-solid fw-bold">
                                    <option disabled selected>-- <?php echo $select_vehicle ?> --</option>
                                    <option value="Bâteaux">
                                        <?php echo $bateaux ?>
                                    </option>
                                    <option value="Bus">
                                        <?php echo $bus ?>
                                    </option>
                                    <option value="Camions">
                                        <?php echo $camions ?>
                                    </option>
                                    <option value="Chariots">
                                        <?php echo $chariots ?>
                                    </option>
                                    <option value="Engins">
                                        <?php echo $engins ?>
                                    </option>
                                    <option value="Motos">
                                        <?php echo $motos ?>
                                    </option>
                                    <option value="Voitures">
                                        <?php echo $vl ?>
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
                                <span class="required"><?php echo $brand ?></span>
                            </label>
                            <!--end::Label-->
                            <!--begin::Input-->
                            <input type='text' class='form-control form-control-solid' placeholder='' name='brand'/>
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
                                </span>
                            </label>
                            <!--end::Label-->
                            <!--begin::Input-->
                            <select name="speciality[]" multiple aria-label="Select a Country" data-control="select2"
                                data-placeholder="<?php echo $select_speciality ?>"
                                class="form-select bg-white form-select-solid fw-bold">
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
</script>
<?php include_once "partials/footer.php"; ?>
<?php
} ?>
