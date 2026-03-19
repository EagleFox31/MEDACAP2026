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
    $vehicles = $academy->vehicles;
    $quizzes = $academy->quizzes;
    $allocations = $academy->allocations;

    if (isset($_POST["submit"])) {
        $Junior = $_POST["junior"];
        $Senior = $_POST["senior"];
        $Expert = $_POST["expert"];

        if ($Junior == "Activé") {
            $allocates = $allocations->find(["level" => "Junior"])->toArray();

            foreach ($allocates as $allocate) {
                $allocate->activeTest = true;

                $allocations->updateOne(
                    ["_id" => new MongoDB\BSON\ObjectId($allocate["_id"])],
                    ['$set' => $allocate]
                );
            }

            $success_msg = $success_active;
        }
        if ($Junior == "Désactivé") {
            $allocates = $allocations->find(["level" => "Junior"])->toArray();

            foreach ($allocates as $allocate) {
                $allocate->activeTest = false;

                $allocations->updateOne(
                    ["_id" => new MongoDB\BSON\ObjectId($allocate["_id"])],
                    ['$set' => $allocate]
                );
            }

            $success_msg = $success_desactive;
        }
        if ($Senior == "Activé") {
            $allocates = $allocations->find(["level" => "Senior"])->toArray();

            foreach ($allocates as $allocate) {
                $allocate->activeTest = true;

                $allocations->updateOne(
                    ["_id" => new MongoDB\BSON\ObjectId($allocate["_id"])],
                    ['$set' => $allocate]
                );
            }
            $success_msg = $success_active;
        }
        if ($Senior == "Désactivé") {
            $allocates = $allocations->find(["level" => "Senior"])->toArray();

            foreach ($allocates as $allocate) {
                $allocate->activeTest = false;

                $allocations->updateOne(
                    ["_id" => new MongoDB\BSON\ObjectId($allocate["_id"])],
                    ['$set' => $allocate]
                );
            }

            $success_msg = $success_desactive;
        }
        if ($Expert == "Activé") {
            $allocates = $allocations->find(["level" => "Expert"])->toArray();

            foreach ($allocates as $allocate) {
                $allocate->activeTest = true;

                $allocations->updateOne(
                    ["_id" => new MongoDB\BSON\ObjectId($allocate["_id"])],
                    ['$set' => $allocate]
                );
            }

            $success_msg = $success_active;
        }
        if ($Expert == "Désactivé") {
            $allocates = $allocations->find(["level" => "Expert"])->toArray();

            foreach ($allocates as $allocate) {
                $allocate->activeTest = false;

                $allocations->updateOne(
                    ["_id" => new MongoDB\BSON\ObjectId($allocate["_id"])],
                    ['$set' => $allocate]
                );
            }

            $success_msg = $success_desactive;
        }
    }
    ?>
<?php include_once "partials/header.php"; ?>

<!--begin::Title-->
<title><?php echo $title_activation; ?> | CFAO Mobility Academy</title>
<!--end::Title-->


<!--begin::Body-->
<div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content"
    data-select2-id="select2-data-kt_content">
    <!--begin::Post-->
    <div class="post fs-6 d-flex flex-column-fluid" id="kt_post" data-select2-id="select2-data-kt_post">
        <!--begin::Container-->
        <div class=" container-xxl " data-select2-id="select2-data-194-27hh">
            <!--begin::Modal body-->
            <div class="container mt-5 w-50 text-center">
                <img src="../../public/images/logo.png" alt="10" height="170" style="display: block; max-width: 75%; height: auto; margin-left: 25px;">
                    <!--begin::Card body-->
                    <div class="card-body pt-0">
                        <!--begin::Table-->
                        <h1 class='my-3 text-center'><?php echo $etat_tests; ?></h1><br><br>
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
                                                style="width: 125px;"><?php echo $level; ?> <?php echo $junior; ?>
                                            </th>
                                            <th class="min-w-125px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                                rowspan="1" colspan="1"
                                                aria-label="Email: activate to sort column ascending"
                                                style="width: 155.266px;"><?php echo $level; ?> <?php echo $senior; ?></th>
                                            <th class="min-w-125px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                                rowspan="1" colspan="1"
                                                aria-label="Created Date: activate to sort column ascending"
                                                style="width: 152.719px;"><?php echo $level; ?> <?php echo $expert; ?></th>
                                        </tr>
                                    </thead>
                                    <tbody class="fw-semibold text-gray-600" id="table">
                                        <?php
                                        $allocateJunior = $allocations->findOne(
                                            ["level" => "Junior"]
                                        );
                                        $allocateSenior = $allocations->findOne(
                                            ["level" => "Senior"]
                                        );
                                        $allocateExpert = $allocations->findOne(
                                            ["level" => "Expert"]
                                        );
                                        ?>
                                        <tr class="odd" etat="">
                                            <!-- <td>
                                                <div class="form-check form-check-sm form-check-custom form-check-solid">
                                                    <input class="form-check-input" id="checkbox" type="checkbox"
                                                        onclick="enable()" value="<?php echo $allocates->_id; ?>">
                                                </div>
                                            </td> -->
                                            <td></td>
                                            <?php if (
                                                $allocateJunior->activeTest ==
                                                true
                                            ) { ?>
                                            <td class="text-center">
                                                <?php echo $active; ?>
                                            </td>
                                            <?php } else { ?>
                                            <td class="text-center">
                                                <?php echo $desactive; ?>
                                            </td>
                                            <?php } ?>
                                            <?php if ($allocateSenior->activeTest == true
                                            ) { ?>
                                            <td class="text-center">
                                                <?php echo $active; ?>
                                            </td>
                                            <?php } else { ?>
                                            <td class="text-center">
                                                <?php echo $desactive; ?>
                                            </td>
                                            <?php } ?>
                                            <?php if ($allocateExpert->activeTest == true
                                            ) { ?>
                                            <td class="text-center">
                                                <?php echo $active; ?>
                                            </td>
                                            <?php } else { ?>
                                            <td class="text-center">
                                                <?php echo $desactive; ?>
                                            </td>
                                            <?php } ?>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <!--end::Table-->
                    </div>
                    <!--end::Card body--><br><br>
                <h1 class='my-3 text-center'><?php echo $title_activation; ?></h1>

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
                                                style="width: 125px;"><?php echo $level; ?> <?php echo $junior; ?>
                                            </th>
                                            <th class="min-w-125px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                                rowspan="1" colspan="1"
                                                aria-label="Email: activate to sort column ascending"
                                                style="width: 155.266px;"><?php echo $level; ?> <?php echo $senior; ?></th>
                                            <th class="min-w-125px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                                rowspan="1" colspan="1"
                                                aria-label="Created Date: activate to sort column ascending"
                                                style="width: 152.719px;"><?php echo $level; ?> <?php echo $expert; ?></th>
                                        </tr>
                                    </thead>
                                    <tbody class="fw-semibold text-gray-600" id="table">
                                        <tr class="odd" etat="">
                                            <!-- <td>
                                                <div class="form-check form-check-sm form-check-custom form-check-solid">
                                                    <input class="form-check-input" id="checkbox" type="checkbox"
                                                        onclick="enable()" value="<?php echo $validates->_id; ?>">
                                                </div>
                                            </td> -->
                                            <td></td>
                                            <td class="text-center">
                                                <!--begin::Input group-->
                                                <div class="d-flex flex-column mb-7 fv-row">
                                                    <!--begin::Input-->
                                                        <select name="junior" aria-label="Select a Country" data-control="select2"
                                                            data-placeholder="Sélectionnez..."
                                                            class="form-select form-select-solid fw-bold">
                                                            <option>Sélectionnez...</option>
                                                            <option value="Activé">
                                                                <?php echo $active; ?>
                                                            </option>
                                                            <option value="Désactivé">
                                                                <?php echo $desactive; ?>
                                                            </option>
                                                        </select>
                                                    <!--end::Input-->
                                                    <?php if (
                                                        isset($error)
                                                    ) { ?>
                                                    <span class='text-danger'>
                                                        <?php echo $error; ?>
                                                    </span>
                                                    <?php } ?>
                                                </div>
                                                <!--end::Input group-->
                                            </td>
                                            <td class="text-center">
                                                <!--begin::Input group-->
                                                <div class="d-flex flex-column mb-7 fv-row">
                                                    <!--begin::Input-->
                                                        <select name="senior" aria-label="Select a Country" data-control="select2"
                                                            data-placeholder="Sélectionnez..."
                                                            class="form-select form-select-solid fw-bold">
                                                            <option>Sélectionnez...</option>
                                                            <option value="Activé">
                                                                <?php echo $active; ?>
                                                            </option>
                                                            <option value="Désactivé">
                                                                <?php echo $desactive; ?>
                                                            </option>
                                                        </select>
                                                    <!--end::Input-->
                                                    <?php if (
                                                        isset($error)
                                                    ) { ?>
                                                    <span class='text-danger'>
                                                        <?php echo $error; ?>
                                                    </span>
                                                    <?php } ?>
                                                </div>
                                                <!--end::Input group-->
                                            </td>
                                            <td class="text-center">
                                                <!--begin::Input group-->
                                                <div class="d-flex flex-column mb-7 fv-row">
                                                    <!--begin::Input-->
                                                        <select name="expert" aria-label="Select a Country" data-control="select2"
                                                            data-placeholder="Sélectionnez..."
                                                            class="form-select form-select-solid fw-bold">
                                                            <option>Sélectionnez...</option>
                                                            <option value="Activé">
                                                                <?php echo $active; ?>
                                                            </option>
                                                            <option value="Désactivé">
                                                                <?php echo $desactive; ?>
                                                            </option>
                                                        </select>
                                                    <!--end::Input-->
                                                    <?php if (
                                                        isset($error)
                                                    ) { ?>
                                                    <span class='text-danger'>
                                                        <?php echo $error; ?>
                                                    </span>
                                                    <?php } ?>
                                                </div>
                                                <!--end::Input group-->
                                            </td>
                                        </tr>
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
                                <?php echo $valider; ?>
                            </span>
                            <span class="indicator-progress">
                                Patientez... <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                            </span>
                        </button>
                        <!--end::Button-->
                    </div>
                    <!--end::Modal footer-->
                </form>
            <!--end::Modal body-->
        </div>
        <!--end::Container-->
    </div>
    <!--end::Post-->
</div>
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
