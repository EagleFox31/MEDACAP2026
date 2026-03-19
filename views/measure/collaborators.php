<?php
session_start();
include_once "../language.php";

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!isset($_SESSION["profile"])) {
    header("Location: ../../");
    exit();
} else {
     ?>
<?php
require_once "../../vendor/autoload.php"; // Create connection
$conn = new MongoDB\Client("mongodb://localhost:27017");
// Connecting in database
$academy = $conn->academy; // Connecting in collections
$users = $academy->users;
$allocations = $academy->allocations;

if (isset($_POST["excel"])) {
    $spreadsheet = new Spreadsheet();
    $excel_writer = new Xlsx($spreadsheet);
    $spreadsheet->setActiveSheetIndex(0);
    $activeSheet = $spreadsheet->getActiveSheet();
    $activeSheet->setCellValue("A1", "Nom d'utilisateur");
    $activeSheet->setCellValue("B1", "Matricule");
    $activeSheet->setCellValue("C1", "Prénoms");
    $activeSheet->setCellValue("D1", "Noms");
    $activeSheet->setCellValue("E1", "Email");
    $activeSheet->setCellValue("F1", "Numéro de téléphone");
    $activeSheet->setCellValue("G1", "Sexe");
    $activeSheet->setCellValue("H1", "Date de naissance");
    $activeSheet->setCellValue("I1", "Niveau technique");
    $activeSheet->setCellValue("J1", "Pays");
    $activeSheet->setCellValue("K1", "Profil");
    $activeSheet->setCellValue("L1", "Diplôme");
    $activeSheet->setCellValue("M1", "Filiale");
    $activeSheet->setCellValue("N1", "Département");
    $activeSheet->setCellValue("O1", "Fonction");
    $activeSheet->setCellValue("P1", "Date de recrutement");
    $activeSheet->setCellValue("Q1", "Manager");
    $myObj = $users->find([
        '$and' => [
            [
                "manager" => new MongoDB\BSON\ObjectId($_SESSION["id"]),
                "active" => true,
            ],
        ],
    ]);
    $i = 2;
    foreach ($myObj as $row) {
        $manager = $users->findOne(["_id" => $row->manager]);
        if ($manager) {
            $activeSheet->setCellValue("A" . $i, $row->username);
            $activeSheet->setCellValue("B" . $i, $row->matricule);
            $activeSheet->setCellValue("C" . $i, $row->firstName);
            $activeSheet->setCellValue("D" . $i, $row->lastName);
            $activeSheet->setCellValue("E" . $i, $row->email);
            $activeSheet->setCellValue("F" . $i, $row->phone);
            $activeSheet->setCellValue("G" . $i, $row->gender);
            $activeSheet->setCellValue("H" . $i, $row->birthdate);
            $activeSheet->setCellValue("I" . $i, $row->level);
            $activeSheet->setCellValue("J" . $i, $row->country);
            $activeSheet->setCellValue("K" . $i, $row->profile);
            $activeSheet->setCellValue("L" . $i, $row->certificate);
            $activeSheet->setCellValue("M" . $i, $row->subsidiary);
            $activeSheet->setCellValue("N" . $i, $row->department);
            $activeSheet->setCellValue("O" . $i, $row->role);
            $activeSheet->setCellValue("P" . $i, $row->recrutmentDate);
            $activeSheet->setCellValue(
                "Q" . $i,
                $_SESSION["firstName"] . " " . $_SESSION["lastName"]
            );
            $i++;
        }
    }
    $filename = "Collaborateurs.xlsx";
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment;filename=" . $filename);
    header("cache-Control: max-age=0");
    $excel_writer->save("php://output");
}
?>

<?php include_once "partials/header.php"; ?>
<!--begin::Title-->
<title><?php echo $title_collaborators ?> | CFAO Mobility Academy</title>
<!--end::Title-->

<?php include_once "../partials/background-manager.php"; ?>
<?php setPageBackground("bg-default"); ?>

<!--begin::Body-->
<?php openBackgroundContainer(); ?>
    <!-- Main Title Card -->
    <div class="container-xxl">
        <div class="card shadow-sm mb-5 w-75 mx-auto">
            <div class="card-body p-4">
                <h1 class="text-dark fw-bold text-center fs-1">
                    <?php echo $title_collaborators ?>
                </h1>
            </div>
        </div>
    </div>
    
    <!-- Search Card with Glassmorphism -->
    <div class="container-xxl mb-5">
        <div class="card bg-opacity-50 bg-white border-0" style="backdrop-filter: blur(10px);">
            <div class="card-body p-4">
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
    </div>

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
        <div class="container-xxl" data-select2-id="select2-data-194-27hh">
            <!--begin::Card with Glassmorphism-->
            <div class="card bg-opacity-50 bg-white border-0" style="backdrop-filter: blur(10px);">
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
                                    class="path2"></span></i>
                            <input type="text" id="search"
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
                                <button type="button" id="password"
                                    data-bs-toggle="modal"
                                    class="btn btn-primary">
                                    Modifier mot de passe
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
                                                <input class="form-check-input" type="checkbox" value="1">
                                            </div>
                                        </th>
                                        <th class="min-w-225px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Customer Name: activate to sort column ascending"
                                            style="width: 125px;"><?php echo $prenomsNoms ?>
                                        </th>
                                        <th class="min-w-125px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;"><?php echo $email ?></th>
                                        <th class="min-w-125px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Created Date: activate to sort column ascending"
                                            style="width: 152.719px;">
                                            <?php echo $phoneNumber ?></th>
                                        <th class="min-w-125px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Created Date: activate to sort column ascending"
                                            style="width: 152.719px;"><?php echo $profil ?>
                                        </th>
                                        <th class="min-w-125px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Created Date: activate to sort column ascending"
                                            style="width: 152.719px;"><?php echo $levelTech ?>
                                        </th>
                                        <th class="min-w-125px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Created Date: activate to sort column ascending"
                                            style="width: 152.719px;">
                                            <?php echo $department ?></th>
                                    </tr>
                                </thead>
                                <tbody class="fw-semibold text-gray-600" id="table">
                                    <?php
                                    $manager = $users->findOne([
                                        '$and' => [
                                            [
                                                "_id" => new MongoDB\BSON\ObjectId(
                                                    $_SESSION["id"]
                                                ),
                                                "active" => true,
                                            ],
                                        ],
                                    ]);
                                    foreach ($manager->users as $person) {
                                        $user = $users->findOne([
                                            '_id'    => new MongoDB\BSON\ObjectId($person),
                                            'active' => true,                 // garde ou retire selon ton besoin
                                        ]);
                                    
                                        /* ⛔️ ID orphelin ou utilisateur inactif → on n’affiche rien */
                                        if (!$user) {
                                            continue;                         // saute directement à l’ID suivant
                                        }
                                    ?>
                                        <tr class="odd" etat="<?= $user->active ?>">
                                            <td></td>
                                    
                                            <td data-filter="search">
                                                <?= htmlspecialchars($user->firstName) ?> <?= htmlspecialchars($user->lastName) ?>
                                            </td>
                                    
                                            <td data-filter="email">
                                                <?= htmlspecialchars($user->email) ?>
                                            </td>
                                    
                                            <td data-order="subsidiary">
                                                <?= htmlspecialchars($user->phone) ?>
                                            </td>
                                    
                                            <td data-order="subsidiary">
                                                <?php
                                                if ($user['profile'] === 'Manager' && $user['test'] === true) {
                                                    echo 'Manager';
                                                } else {
                                                    echo htmlspecialchars($user->profile);
                                                }
                                                ?>
                                            </td>
                                    
                                            <td data-order="subsidiary">
                                                <?= htmlspecialchars($user->level) ?>
                                            </td>
                                    
                                            <td data-order="department">
                                                <?= htmlspecialchars($user->department) ?>
                                            </td>
                                        </tr>
                                    <?php
                                    }
                                    
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
            <div class="d-flex justify-content-end align-items-center" style="margin-top: 20px;">
                <form method="post">
                    <button type="submit" name="excel" title="Cliquez ici pour importer la table" class="btn btn-primary">
                        <i class="ki-duotone ki-exit-up fs-2"><span class="path1"></span><span class="path2"></span></i>
                        <?php echo $excel ?>
                    </button>
                </form>
            </div>
            <!--end::Export dropdown-->
        </div>
        <!--end::Container-->
    </div>
    <!--end::Post-->
<?php closeBackgroundContainer(); ?>
<!--end::Body-->
<?php include_once "partials/footer.php"; ?>
<?php
} ?>
