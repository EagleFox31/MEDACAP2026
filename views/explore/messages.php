<?php
session_start();
include_once "../language.php";

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use MongoDB\Client;

if (!isset($_SESSION["profile"])) {
    header("Location: ../../");
    exit();
}

require_once "../../vendor/autoload.php";

// Create connection
$conn = new Client("mongodb://localhost:27017");

// Connecting to database
$academy = $conn->academy;
// Connecting to collections
$users = $academy->users;
$chats = $academy->chats;

// Save "État" functionality
if (isset($_POST['save_etat'])) {
    $states = $_POST['states'];

    foreach ($states as $state) {
        $userId = $state['userId'];
        $etat = $state['etat'];

        // Update the chat document with the new state
        $chats->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($userId)],
            ['$set' => ['etat' => $etat]]
        );
    }

    echo "<script>alert('States saved successfully!');</script>";
}

// Export to Excel functionality
if (isset($_POST["excel"])) {
    $spreadsheet = new Spreadsheet();
    $excel_writer = new Xlsx($spreadsheet);
    $spreadsheet->setActiveSheetIndex(0);
    $activeSheet = $spreadsheet->getActiveSheet();

    // Set headers
    $headers = ["Prénoms et nom", "Commentaire", "QCM", "Niveau", "Date et heure", "État"];
    $column = 'A';
    foreach ($headers as $header) {
        $activeSheet->setCellValue("{$column}1", $header);
        $column++;
    }

    // Fetch chat messages from MongoDB
    $chatMessages = $chats->find();

    // Write data to Excel
    $i = 2;
    foreach ($chatMessages as $chat) {
        // Extract data from the document
        $fullName = isset($chat['userName']) ? $chat['userName'] : 'No Name';
        $messageContent = isset($chat['message']) ? $chat['message'] : 'No Content';
        $timestamp = isset($chat['timestamp']) ? $chat['timestamp'] : 'No Timestamp';
        $qcm = isset($chat['qcm']) ? $chat['qcm'] : 'No QCM';
        $level = isset($chat['level']) ? $chat['level'] : 'No Level';
        $etat = isset($chat['etat']) ? $chat['etat'] : 'Non traité'; // Default to 'Non traité' if not set

        // Populate Excel sheet
        $activeSheet->setCellValue("A{$i}", $fullName);
        $activeSheet->setCellValue("B{$i}", $messageContent);
        $activeSheet->setCellValue("C{$i}", $qcm);
        $activeSheet->setCellValue("D{$i}", $level);
        $activeSheet->setCellValue("E{$i}", $timestamp);
        $activeSheet->setCellValue("F{$i}", $etat); // Add "État" column
        $i++;
    }

    // Output Excel file
    $filename = "Feedback.xlsx";
    header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
    header("Content-Disposition: attachment;filename={$filename}");
    header("Cache-Control: max-age=0");
    $excel_writer->save("php://output");
    exit();
}

if (isset($_POST["data"])) {
    $id = $_POST["id"];
    $data = $_POST["data"];

    $chats->updateOne(
        ["_id" => new MongoDB\BSON\ObjectId($id)],
        ['$set' => ['status' => $data]]
    );
}

?>

<?php include_once "partials/header.php"; ?>

<!--begin::Title-->
<title><?php echo $list_comment_users ?> | CFAO Mobility Academy</title>
<!--end::Title-->

<!--begin::Body-->
<div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content">
    <!--begin::Toolbar-->
    <div class="toolbar" id="kt_toolbar">
        <div class="container-fluid d-flex flex-stack flex-wrap flex-sm-nowrap">
            <!--begin::Info-->
            <div class="d-flex flex-column align-items-start justify-content-left flex-wrap me-2">
                <!--begin::Title-->
                <h1 class="text-dark fw-bolder my-1 fs-1"><?php echo $list_comment_users ?></h1>
                <!--end::Title-->
                <div class="card-title">
                    <div class="d-flex align-items-center position-relative my-1">
                        <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5"><span class="path1"></span><span
                                class="path2"></span></i>
                        <input type="text" id="search" class="form-control form-control-solid w-250px ps-12"
                            placeholder="<?php echo $recherche ?>">
                    </div>
                </div>
            </div>
            <!--end::Info-->
        </div>
    </div>
    <!--end::Toolbar-->

    <?php if (isset($success_msg)) { ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <left><strong><?php echo $success_msg; ?></strong></left>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <?php } ?>

    <?php if (isset($error_msg)) { ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <left><strong><?php echo $error_msg; ?></strong></left>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <?php } ?>

    <!--begin::Post-->
    <div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
        <!--begin::Container-->
        <div class="container-xxl">
            <!--begin::Card-->
            <div class="card">
                <div class="card-body pt-0">
                    <!--begin::Table-->
                    <div id="kt_customers_table_wrapper" class="dataTables_wrapper dt-bootstrap4 no-footer">
                        <div class="table-responsive">
                            <table aria-describedby=""
                                class="table align-middle table-row-dashed fs-6 gy-5 dataTable no-footer"
                                id="kt_customers_table">
                                <thead>
                                    <tr class="text-start text-black fw-bold fs-7 text-uppercase gs-0">
                                        <th class="w-10px pe-2 sorting_disabled"></th>
                                        <th class="min-w-200px sorting"><?php echo $prenomsNoms ?></th>
                                        <th class="min-w-300px sorting"><?php echo $comment ?></th>
                                        <th class="min-w-100px sorting text-left"><?php echo $qcm ?></th>
                                        <th class="min-w-100px sorting text-left"><?php echo $level ?></th>
                                        <th class="min-w-200px sorting text-left"><?php echo $date_time ?></th>
                                        <th class="min-w-100px sorting text-left"><?php echo $status ?></th>
                                        <!-- New column for État -->
                                    </tr>
                                </thead>

                                <tbody class="text-gray-600 fw-semibold">
                                    <?php
                                        // Fetch chat messages from MongoDB
                                        $chatMessages = $chats->find();

                                        foreach ($chatMessages as $chat) {
                                            $user = $users->findOne(['_id' => new MongoDB\BSON\ObjectId($chat['user'])]);
                                            ?>
                                    <tr>
                                        <td></td>
                                        <td><?php echo $user->firstName; ?> <?php echo $user->lastName; ?></td>
                                        <td><?php echo $chat->message; ?></td>
                                        <td><?php echo $chat->qcm; ?></td>
                                        <td><?php echo $chat->level; ?></td>
                                        <td><?php echo $chat->created; ?></td>
                                        <form method="post">
                                            <td>
                                                <input name="id" class="hidden" type="text"
                                                    value="<?php echo $chat->_id; ?>">
                                                <select name="data" class="form-select form-select-sm etat-select"
                                                    onchange="this.form.submit();">
                                                    <?php if ($chat['status'] == "Non traité") { ?>
                                                    <option value="Non traité" selected><?php echo $non_traite ?></option>
                                                    <option value="Traité"><?php echo $traite ?></option>
                                                    <?php } else { ?>
                                                    <option value="Non traité"><?php echo $non_traite ?></option>
                                                    <option value="Traité" selected><?php echo $traite ?></option>
                                                    <?php } ?>
                                                </select>
                                            </td> <!-- New column for État -->
                                        </form>
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
                <!--begin::Export-->
                <button type="button" id="excel" class="btn btn-light-danger me-3" data-bs-toggle="modal"
                    data-bs-target="#kt_customers_export_modal">
                    <i class="ki-duotone ki-exit-up fs-2"><span class="path1"></span><span class="path2"></span></i>
                    <?php echo $excel ?>
                </button>
                <!--end::Export-->
            </div>
            <!--end::Export dropdown-->
        </div>
        <!--end::Container-->
    </div>
    <!--end::Post-->
</div>
<!--end::Body-->
<script src="https://code.jquery.com/jquery-3.6.3.js" integrity="sha256-nQLuAZGRRcILA+6dMBOvcRh5Pe310sBpanc6+QBmyVM="
    crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/gh/linways/table-to-excel@v1.0.4/dist/tableToExcel.js">
</script>
<script src="../../public/js/main.js"></script>
<script>
$(document).ready(function() {
    $("#excel").on("click", function() {
        let table = document.getElementsByTagName("table");
        debugger;
        TableToExcel.convert(table[0], {
            name: `Feed-back.xlsx`
        })
    });
});
</script>

<?php include_once "partials/footer.php"; ?>

<script>
// document.addEventListener("DOMContentLoaded", function() {
//     const searchInput = document.getElementById("search");
//     const table = document.getElementById("kt_customers_table");
//     const rows = table.getElementsByTagName("tbody")[0].getElementsByTagName("tr");

//     searchInput.addEventListener("keyup", function() {
//         const filter = searchInput.value.toLowerCase();

//         Array.from(rows).forEach(function(row) {
//             const cells = row.getElementsByTagName("td");
//             let match = false;

//             Array.from(cells).forEach(function(cell) {
//                 if (cell.textContent.toLowerCase().includes(filter)) {
//                     match = true;
//                 }
//             });

//             if (match) {
//                 row.style.display = "";
//             } else {
//                 row.style.display = "none";
//             }
//         });
//     });
// });
</script>