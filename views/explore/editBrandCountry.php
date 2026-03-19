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
    $validations = $academy->validations;

    if (isset($_POST["submit"])) {
        $qcmJunior = $_POST["qcmJunior"];
        $qcmSenior = $_POST["qcmSenior"];
        $qcmExpert = $_POST["qcmExpert"];
        $tacheJunior = $_POST["tacheJunior"];
        $tacheSenior = $_POST["tacheSenior"];
        $tacheExpert = $_POST["tacheExpert"];

        $exist = $validations->findOne([ "active" => true]);
        if (empty($qcmJunior) || empty($qcmSenior) || empty($qcmExpert) || empty($tacheJunior) || empty($tacheSenior) || empty($tacheExpert)) {
            $error = $champ_obligatoire;
        } else {
            if ($exist) {
                if ($qcmJunior) {
                    $validations->updateOne(
                        [
                            "active" => true
                        ],
                        [ '$set' => [ 
                                'qcmJunior' => +$qcmJunior, 
                                'updated' => date("d-m-Y H:i:s") 
                            ] 
                        ]
                    );
                }
                if ($qcmSenior) {
                    $validations->updateOne(
                        [
                            "active" => true
                        ],
                        [ '$set' => [ 
                                'qcmSenior' => +$qcmSenior, 
                                'updated' => date("d-m-Y H:i:s") 
                            ] 
                        ]
                    );
                }
                if ($qcmExpert) {
                    $validations->updateOne(
                        [
                            "active" => true
                        ],
                        [ '$set' => [ 
                                'qcmExpert' => +$qcmExpert, 
                                'updated' => date("d-m-Y H:i:s") 
                            ] 
                        ]
                    );
                }
                if ($tacheJunior) {
                    $validations->updateOne(
                        [
                            "active" => true
                        ],
                        [ '$set' => [ 
                                'tacheJunior' => +$tacheJunior, 
                                'updated' => date("d-m-Y H:i:s") 
                            ] 
                        ]
                    );
                }
                if ($tacheSenior) {
                    $validations->updateOne(
                        [
                            "active" => true
                        ],
                        [ '$set' => [ 
                                'tacheSenior' => +$tacheSenior, 
                                'updated' => date("d-m-Y H:i:s") 
                            ] 
                        ]
                    );
                }
                if ($tacheExpert) {
                    $validations->updateOne(
                        [
                            "active" => true
                        ],
                        [ '$set' => [ 
                                'tacheExpert' => +$tacheExpert, 
                                'updated' => date("d-m-Y H:i:s") 
                            ] 
                        ]
                    );
                }
            } else {
                $validate = [
                    'qcmJunior' => 80,
                    'qcmSenior' => 80,
                    'qcmExpert' => 80,
                    'tacheJunior' => 80,
                    'tacheSenior' => 80,
                    'tacheExpert' => 80,
                    'active' => true,
                    'created' => date("d-m-Y H:i:s")
                ];
                $insert = $validations->insertOne($validate);
                if ($qcmJunior) {
                    $validations->updateOne(
                        [
                            "_id" => new MongoDB\BSON\ObjectId(
                                $insert->getInsertedId()
                            ),
                        ],
                        [ '$set' => [ 
                                'qcmJunior' => +$qcmJunior, 
                                'updated' => date("d-m-Y H:i:s") 
                            ] 
                        ]
                    );
                }
                if ($qcmSenior) {
                    $validations->updateOne(
                        [
                            "_id" => new MongoDB\BSON\ObjectId(
                                $insert->getInsertedId()
                            ),
                        ],
                        [ '$set' => [ 
                                'qcmSenior' => +$qcmSenior, 
                                'updated' => date("d-m-Y H:i:s") 
                            ] 
                        ]
                    );
                }
                if ($qcmExpert) {
                    $validations->updateOne(
                        [
                            "_id" => new MongoDB\BSON\ObjectId(
                                $insert->getInsertedId()
                            ),
                        ],
                        [ '$set' => [ 
                                'qcmExpert' => +$qcmExpert, 
                                'updated' => date("d-m-Y H:i:s") 
                            ] 
                        ]
                    );
                }
                if ($tacheJunior) {
                    $validations->updateOne(
                        [
                            "_id" => new MongoDB\BSON\ObjectId(
                                $insert->getInsertedId()
                            ),
                        ],
                        [ '$set' => [ 
                                'tacheJunior' => +$tacheJunior, 
                                'updated' => date("d-m-Y H:i:s") 
                            ] 
                        ]
                    );
                }
                if ($tacheSenior) {
                    $validations->updateOne(
                        [
                            "_id" => new MongoDB\BSON\ObjectId(
                                $insert->getInsertedId()
                            ),
                        ],
                        [ '$set' => [ 
                                'tacheSenior' => +$tacheSenior, 
                                'updated' => date("d-m-Y H:i:s") 
                            ] 
                        ]
                    );
                }
                if ($tacheExpert) {
                    $validations->updateOne(
                        [
                            "_id" => new MongoDB\BSON\ObjectId(
                                $insert->getInsertedId()
                            ),
                        ],
                        [ '$set' => [ 
                                'tacheExpert' => +$tacheExpert, 
                                'updated' => date("d-m-Y H:i:s") 
                            ] 
                        ]
                    );
                }
            }
            $success_msg = $success_seuil_validation;
        }

    }
    ?>
<?php include_once "partials/header.php"; ?>

<!--begin::Title-->
<title><?php echo $seuil_validation ?> | CFAO Mobility Academy</title>
<!--end::Title-->


<!--begin::Body-->
<div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content"
    data-select2-id="select2-data-kt_content">
    <!--begin::Post-->
    <div class="post fs-6 d-flex flex-column-fluid" id="kt_post" data-select2-id="select2-data-kt_post">
        <!--begin::Container-->
        <div class=" container-xxl " data-select2-id="select2-data-194-27hh">
            <!--begin::Modal body-->
            <div class='container mt-5 w-50'>
                <img src='../../public/images/logo.png' alt='10' height='170'
                    style='display: block; margin-left: auto; margin-right: auto; width: 50%;'>
                    <!--begin::Card body-->
                    <div class="card-body pt-0">
                        <!--begin::Table-->
                        <h1 class='my-3 text-center'><?php echo $seuil_validation ?></h1><br><br>
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
                                                    <input class="form-check-input" type="checkbox" value="1">
                                                </div>
                                            </th>
                                            <th class="min-w-125px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                                rowspan="1" colspan="1"
                                                aria-label="Customer Name: activate to sort column ascending"
                                                style="width: 125px;"><?php echo $test ?>
                                            </th>
                                            <th class="min-w-125px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                                rowspan="1" colspan="1"
                                                aria-label="Customer Name: activate to sort column ascending"
                                                style="width: 125px;"><?php echo $level ?> <?php echo $junior ?>
                                            </th>
                                            <th class="min-w-125px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                                rowspan="1" colspan="1"
                                                aria-label="Email: activate to sort column ascending"
                                                style="width: 155.266px;"><?php echo $level ?> <?php echo $senior ?></th>
                                            <th class="min-w-125px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                                rowspan="1" colspan="1"
                                                aria-label="Created Date: activate to sort column ascending"
                                                style="width: 152.719px;"><?php echo $level ?> <?php echo $expert ?></th>
                                        </tr>
                                    </thead>
                                    <tbody class="fw-semibold text-gray-600" id="table">
                                        <?php
                                        $validates = $validations->find(["active" => true]);
                                        foreach ($validates as $validates) { ?>
                                        <tr class="odd" etat="<?php echo $validates->active; ?>">
                                            <!-- <td>
                                                <div class="form-check form-check-sm form-check-custom form-check-solid">
                                                    <input class="form-check-input" id="checkbox" type="checkbox"
                                                        onclick="enable()" value="<?php echo $validates->_id; ?>">
                                                </div>
                                            </td> -->
                                            <td></td>
                                            <td class="text-center">
                                                <?php echo $connaissances ?>
                                            </td>
                                            <td class="text-center">
                                                <?php echo $validates->qcmJunior; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php echo $validates->qcmSenior; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php echo $validates->qcmExpert; ?>
                                            </td>
                                        </tr>
                                        <tr class="odd" etat="<?php echo $validates->active; ?>">
                                            <!-- <td>
                                                <div class="form-check form-check-sm form-check-custom form-check-solid">
                                                    <input class="form-check-input" id="checkbox" type="checkbox"
                                                        onclick="enable()" value="<?php echo $validates->_id; ?>">
                                                </div>
                                            </td> -->
                                            <td></td>
                                            <td class="text-center">
                                                <?php echo $tache_pro ?>
                                            </td>
                                            <td class="text-center">
                                                <?php echo $validates->tacheJunior; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php echo $validates->tacheSenior; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php echo $validates->tacheExpert; ?>
                                            </td>
                                        </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <!--end::Table-->
                    </div>
                    <!--end::Card body--><br><br>
                    <h1 class='my-3 text-center'><?php echo $edit_valivation ?></h1>

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
                                                    <input class="form-check-input" type="checkbox" value="1">
                                                </div>
                                            </th>
                                            <th class="min-w-125px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                                rowspan="1" colspan="1"
                                                aria-label="Customer Name: activate to sort column ascending"
                                                style="width: 125px;"><?php echo $test ?>
                                            </th>
                                            <th class="min-w-125px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                                rowspan="1" colspan="1"
                                                aria-label="Customer Name: activate to sort column ascending"
                                                style="width: 125px;"><?php echo $level ?> <?php echo $junior ?>
                                            </th>
                                            <th class="min-w-125px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                                rowspan="1" colspan="1"
                                                aria-label="Email: activate to sort column ascending"
                                                style="width: 155.266px;"><?php echo $level ?> <?php echo $senior ?></th>
                                            <th class="min-w-125px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                                rowspan="1" colspan="1"
                                                aria-label="Created Date: activate to sort column ascending"
                                                style="width: 152.719px;"><?php echo $level ?> <?php echo $expert ?></th>
                                        </tr>
                                    </thead>
                                    <tbody class="fw-semibold text-gray-600" id="table">
                                        <?php
                                        $validates = $validations->find(["active" => true]);
                                        foreach ($validates as $validates) { ?>
                                        <tr class="odd" etat="<?php echo $validates->active; ?>">
                                            <!-- <td>
                                                <div class="form-check form-check-sm form-check-custom form-check-solid">
                                                    <input class="form-check-input" id="checkbox" type="checkbox"
                                                        onclick="enable()" value="<?php echo $validates->_id; ?>">
                                                </div>
                                            </td> -->
                                            <td></td>
                                            <td class="text-center">
                                                <?php echo $connaissances ?>
                                            </td>
                                            <td class="text-center">
                                                <input class='form-control form-control-solid' placeholder='' name='qcmJunior' />
                                            </td>
                                            <td class="text-center">
                                                <input class='form-control form-control-solid' placeholder='' name='qcmSenior' />
                                            </td>
                                            <td class="text-center">
                                                <input class='form-control form-control-solid' placeholder='' name='qcmExpert' />
                                            </td>
                                        </tr>
                                        <tr class="odd" etat="<?php echo $validates->active; ?>">
                                            <!-- <td>
                                                <div class="form-check form-check-sm form-check-custom form-check-solid">
                                                    <input class="form-check-input" id="checkbox" type="checkbox"
                                                        onclick="enable()" value="<?php echo $validates->_id; ?>">
                                                </div>
                                            </td> -->
                                            <td></td>
                                            <td class="text-center">
                                                <?php echo $tache_pro ?>
                                            </td>
                                            <td class="text-center">
                                                <input class='form-control form-control-solid' placeholder='' name='tacheJunior' />
                                            </td>
                                            <td class="text-center">
                                                <input class='form-control form-control-solid' placeholder='' name='tacheSenior' />
                                            </td>
                                            <td class="text-center">
                                                <input class='form-control form-control-solid' placeholder='' name='tacheExpert' />
                                            </td>
                                        </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <!--end::Table-->
                    </div>
                    <!--end::Card body-->
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
