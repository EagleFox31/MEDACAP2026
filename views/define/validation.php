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
        $level1 = $_POST["level1"];
        $level2 = $_POST["level2"];
        $level3 = $_POST["level3"];

        $exist = $validations->findOne(["type" => "Training", "active" => true]);
        if ($exist) {
            if ($level1) {
                $validations->updateOne(
                    [
                        "type" => 'Training'
                    ],
                    [ '$set' => [ 
                            'level1' => +$level1, 
                            'updated' => date("d-m-Y H:I:S") 
                        ] 
                    ]
                );
            }
            if ($level2) {
                $validations->updateOne(
                    [
                        "type" => 'Training'
                    ],
                    [ '$set' => [ 
                            'level2' => +$level2, 
                            'updated' => date("d-m-Y H:I:S") 
                        ] 
                    ]
                );
            }
            if ($level3) {
                $validations->updateOne(
                    [
                        "type" => 'Training'
                    ],
                    [ '$set' => [ 
                            'level3' => +$level3, 
                            'updated' => date("d-m-Y H:I:S") 
                        ] 
                    ]
                );
            }
        } else {
            if (empty($level1) || empty($level2) || empty($level3)) {
                $error = $champ_obligatoire;
            } else {
                $validate = [
                    'level1' => +$level1,
                    'level2' => +$level2,
                    'level3' => +$level3,
                    'type' => 'Training',
                    'active' => true,
                    'created' => date("d-m-Y H:I:S")
                ];
                $insert = $validations->insertOne($validate);
            }
        }
        $success_msg = $success_seuil_recommandation;
    }
    ?>
<?php include_once "partials/header.php"; ?>

<!--begin::Title-->
<title><?php echo $seuil_recommandation ?> | CFAO Mobility Academy</title>
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
                        <h1 class='my-3 text-center'><?php echo $seuil_recommandation ?></h1><br><br>
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
                                                style="width: 125px;"><?php echo $qcm ?>
                                            </th>
                                            <th class="min-w-100px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                                rowspan="1" colspan="1"
                                                aria-label="Customer Name: activate to sort column ascending"
                                                style="width: 100px;"><?php echo $Level ?> 1
                                            </th>
                                            <th class="min-w-100px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                                rowspan="1" colspan="1"
                                                aria-label="Email: activate to sort column ascending"
                                                style="width: 155.266px;"><?php echo $Level ?> 2
                                            </th>
                                            <th class="min-w-100px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                                rowspan="1" colspan="1"
                                                aria-label="Created Date: activate to sort column ascending"
                                                style="width: 100px"><?php echo $Level ?> 3
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="fw-semibold text-gray-600" id="table">
                                        <?php
                                        $validates = $validations->find(["type" => "Training", "active" => true]);
                                        foreach ($validates as $validates) { ?>
                                        <tr class="odd" etat="<?php echo $validates->active; ?>">
                                            <td></td>
                                            <td class="text-center">
                                                <?php echo $seuil_recommandation ?>
                                            </td>
                                            <td class="text-center">
                                                <?php echo $validates->level1; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php echo $validates->level2; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php echo $validates->level3; ?>
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
                    <h1 class='my-3 text-center'><?php echo $edit_recommandation ?></h1>

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
                                                style="width: 125px;"><?php echo $qcm ?>
                                            </th>
                                            <th class="min-w-100px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                                rowspan="1" colspan="1"
                                                aria-label="Customer Name: activate to sort column ascending"
                                                style="width: 100px;"><?php echo $Level ?> 1
                                            </th>
                                            <th class="min-w-100px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                                rowspan="1" colspan="1"
                                                aria-label="Email: activate to sort column ascending"
                                                style="width: 155.266px;"><?php echo $Level ?> 2
                                            </th>
                                            <th class="min-w-100px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                                rowspan="1" colspan="1"
                                                aria-label="Created Date: activate to sort column ascending"
                                                style="width: 100px"><?php echo $Level ?> 3
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="fw-semibold text-gray-600" id="table">
                                        <tr class="odd">
                                            <td></td>
                                            <td class="text-center">
                                                <?php echo $seuil_recommandation ?>
                                            </td>
                                            <td class="text-center">
                                                <input class='form-control form-control-solid' placeholder='' name='level1' />
                                            </td>
                                            <td class="text-center">
                                                <input class='form-control form-control-solid' placeholder='' name='level2' />
                                            </td>
                                            <td class="text-center">
                                                <input class='form-control form-control-solid' placeholder='' name='level3' />
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <!--end::Table-->
                    </div><br>
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
