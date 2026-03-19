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
    $vehicles = $academy->vehicles;
    $allocations = $academy->allocations;

    $i = 5;

    function getTechnicians($users, $subsidiary, $level) {
        return $users->find([
            "subsidiary" => $subsidiary,
            "level" => ['$in' => (array) $level], // Convertit $level en tableau si ce n'est pas déjà un tableau
            "active" => true,
        ])->toArray();
    }

    function filterTechnicians($technicians) {
        $filtered = [];
        foreach ($technicians as $techn) {
            if ($techn["profile"] == "Technicien" || ($techn["profile"] == "Manager" && $techn["test"] == true)) {
                array_push($filtered, new MongoDB\BSON\ObjectId($techn['_id']));
            }
        }
        return $filtered;
    }

    function getAllocations($allocations, $technician, $level) {
        $allocateFac = $allocations->findOne([
            "user" => new MongoDB\BSON\ObjectId($technician),
            "type" => "Factuel",
            "level" => $level,
        ]);
        $allocateDecla = $allocations->findOne([
            "user" => new MongoDB\BSON\ObjectId($technician),
            "type" => "Declaratif",
            "level" => $level,
        ]);
        return [$allocateFac, $allocateDecla];
    }

    function processTechnicians($allocations, $technicians, $level) {
        $tests = [];
        $countSavoirs = [];
        $countMaSavFais = [];
        $countTechSavFais = [];

        foreach ($technicians as $technician) {
            list($allocateFac, $allocateDecla) = getAllocations($allocations, $technician, $level);

            if (isset($allocateFac) && $allocateFac['active'] == true) {
                $countSavoirs[] = $allocateFac;
            }
            if (isset($allocateDecla)) {
                if ($allocateDecla['activeManager'] == true) {
                    $countMaSavFais[] = $allocateDecla;
                }
                if ($allocateDecla['active'] == true) {
                    $countTechSavFais[] = $allocateDecla;
                }
                if ($allocateFac['active'] == true && $allocateDecla['active'] == true && $allocateDecla['activeManager'] == true) {
                    $tests[] = $technician;
                }
            }
        }
        $response = [
            'technicians' => count($technicians),
            'test' => count($tests),
            'countSavoirs' => count($countSavoirs),
            'countMaSavFais' => count($countMaSavFais),
            'countTechSavFais' => count($countTechSavFais)
        ];

        return $response;
    }

    $subsidiaries = [
        "CFAO MOTORS BURKINA",
        "CAMEROON MOTORS INDUSTRIES",
        "CFAO MOTORS COTE D'IVOIRE",
        "CFAO MOTORS CONGO",
        "CFAO MOTORS GABON",
        "CFAO MOTORS MADAGASCAR",
        "CFAO MOTORS MALI",
        "CFAO MOTORS CENTRAFRIQUE",
        "CFAO MOTORS RDC",
        "CFAO MOTORS SENEGAL"
    ];

    $subCountries = [
        "CFAO MOTORS BURKINA" => "Burkina Faso",
        "CAMEROON MOTORS INDUSTRIES" => "Cameroon",
        "CFAO MOTORS COTE D'IVOIRE" => "Côte d'Ivoire",
        "CFAO MOTORS CONGO" => "Congo",
        "CFAO MOTORS GABON" => "Gabon",
        "CFAO MOTORS MADAGASCAR" => "Madagascar",
        "CFAO MOTORS MALI" => "Mali",
        "CFAO MOTORS CENTRAFRIQUE" => "RCA",
        "CFAO MOTORS RDC" => "RDC",
        "CFAO MOTORS SENEGAL" => "Senegal"
    ];

    $levels = ['Junior', 'Senior', 'Expert'];

    $results = [];

    foreach ($subsidiaries as $subsidiary) {
        // Calculer le total global
        $results[$subsidiary]['Global'] = [
            'technicians' => 0,
            'test' => 0,
            'countSavoirs' => 0,
            'countMaSavFais' => 0,
            'countTechSavFais' => 0
        ];
        // Calculer le total global
        $results['GROUPE CFAO']['Global'] = [
            'technicians' => 0,
            'test' => 0,
            'countSavoirs' => 0,
            'countMaSavFais' => 0,
            'countTechSavFais' => 0
        ];
        
        // Initialiser un tableau pour stocker les techniciens par niveau
        $techniciansByLevel = [];
        
        foreach ($levels as $level) {
            // Calculer le total global
            $results['GROUPE CFAO'][$level] = [
                'technicians' => 0,
                'test' => 0,
                'countSavoirs' => 0,
                'countMaSavFais' => 0,
                'countTechSavFais' => 0
            ];

            if ($level == 'Junior') {
                $technicians = filterTechnicians(getTechnicians($users, $subsidiary, ['Junior', 'Senior', 'Expert']));
            }
            if ($level == 'Senior') {
                $technicians = filterTechnicians(getTechnicians($users, $subsidiary, ['Senior', 'Expert']));
            }
            if ($level == 'Expert') {
                $technicians = filterTechnicians(getTechnicians($users, $subsidiary, ['Expert']));
            }

            // Stocker les techniciens par niveau
            $techniciansByLevel[$level] = $technicians;

            // Traiter les techniciens et les stocker dans les résultats
            $results[$subsidiary][$level] = processTechnicians($allocations, $technicians, $level);       

            // Calculer le total des techniciens pour la filiale
            $results[$subsidiary]['Global']['technicians'] += $results[$subsidiary][$level]['technicians'];
            $results[$subsidiary]['Global']['test'] += $results[$subsidiary][$level]['test'];
            $results[$subsidiary]['Global']['countSavoirs'] += $results[$subsidiary][$level]['countSavoirs'];
            $results[$subsidiary]['Global']['countMaSavFais'] += $results[$subsidiary][$level]['countMaSavFais'];
            $results[$subsidiary]['Global']['countTechSavFais'] += $results[$subsidiary][$level]['countTechSavFais'];
        }
    }
?>

<style>
    tr {
    }
</style>

<?php include_once "partials/header.php"; ?>
<!--begin::Title-->
<title><?php echo $etat_avanacement_qcm_country ?> | CFAO Mobility Academy</title>
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
                <h1 class="text-dark fw-bold my-1 fs-2">
                    <?php echo $etat_avanacement_qcm_country ?> </h1>
                <!--end::Title-->
                <div class="card-title">
                </div>
            </div>
            <!--end::Info-->
        </div>
    </div>
    <!--end::Toolbar-->
    <!--begin::Post-->
    <div class="post fs-6 d-flex flex-column-fluid" id="kt_post" data-select2-id="select2-data-kt_post">
        <!--begin::Container-->
        <div class=" container-xxl " data-select2-id="select2-data-194-27hh">
            <!--begin::Card-->
            <div class="card">
                <!--begin::Card body-->
                <div class="card-body pt-0">
                    <!--begin::Table-->
                    <div id="kt_customers_table_wrapper" class="dataTables_wrapper dt-bootstrap4 no-footer">
                        <div class="table-responsive">
                            <table aria-describedby=""
                                class="table align-middle table-bordered  table-row-dashed fs-6 gy-4 dataTable no-footer"
                                id="kt_customers_table">
                                <thead>
                                    <tr class="text-start text-black fw-bold fs-7 text-uppercase gs-0">
                                        <th class="min-w-150px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Customer Name: activate to sort column ascending"
                                            style="width: 125px; text-align: center; vertical-align: middle; height: 50px;"><?php echo $Subsidiary ?> CFAO (<?php echo $pays ?>)
                                        </th>
                                        <th class="min-w-0px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Company: activate to sort column ascending"
                                            style="width: 05px; text-align: center; vertical-align: middle; height: 50px;"><?php echo $Level ?>
                                        </th>
                                        <th class="min-w-150px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="2"
                                            aria-label="Company: activate to sort column ascending"
                                            style="width: 150px; text-align: center; vertical-align: middle; height: 50px;"><?php echo $nbre_qcm_effectue ?>
                                        </th>
                                        <th class="min-w-200px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="2"
                                            aria-label="Payment Method: activate to sort column ascending"
                                            style="width: 150.516px;"><?php echo $qcm_realises ?>
                                        </th>
                                        <th class="min-w-200px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="2"
                                            aria-label="Created Date: activate to sort column ascending"
                                            style="width: 152.719px;"><?php echo $qcm_techs_realises ?>
                                        </th>
                                        <th class="min-w-200px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="2"
                                            aria-label="Created Date: activate to sort column ascending"
                                            style="width: 152.719px;"><?php echo $qcm_manager_realises ?>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="fw-semibold text-gray-600" id="table">
                                    <?php foreach ($subCountries as $subsidiary => $country) { ?>
                                        <th class="text-uppercase text-center" style='text-align: center; vertical-align: middle; height: 50px;' rowspan="<?php echo $i ?>">
                                            <?php echo $country ?>
                                        </th>
                                        <?php foreach (['Junior', 'Senior', 'Expert', 'Global'] as $level) { ?>
                                            <tr class="odd <?php if ($level === 'Global') echo 'fw-bolder'; ?>" etat="" <?php if ($level === 'Global') echo 'style="background-color: #edf2f7;"'; ?>>
                                                <td class="text-center" style='text-align: center; vertical-align: middle; height: 50px;'>
                                                    <?php echo $level ?>
                                                </td>
                                                <td class="text-center" style="background-color: #edf2f7; text-align: center; vertical-align: middle; height: 50px;">
                                                    <?php echo $results[$subsidiary][$level]['countSavoirs'] + $results[$subsidiary][$level]['countTechSavFais'] + $results[$subsidiary][$level]['countMaSavFais'] ?> / <?php echo $results[$subsidiary][$level]['technicians'] * 3 ?>
                                                </td>
                                                <td class="text-center" style="background-color: #edf2f7; text-align: center; vertical-align: middle; height: 50px;">
                                                    <?php $technicianCount = $results[$subsidiary][$level]['technicians'];
                                                    if ($technicianCount > 0) {
                                                        $percentage = ceil(($results[$subsidiary][$level]['countSavoirs'] + $results[$subsidiary][$level]['countTechSavFais'] + $results[$subsidiary][$level]['countMaSavFais']) * 100 / ($technicianCount * 3));
                                                    } else {
                                                        $percentage = 0; // or any other appropriate value or message
                                                    }
                                                    echo $percentage . '%'; ?>
                                                </td>
                                                <td class="text-center" style='text-align: center; vertical-align: middle; height: 50px;'>
                                                    <?php echo $results[$subsidiary][$level]['countSavoirs'] ?> / <?php echo $results[$subsidiary][$level]['technicians'] ?>
                                                </td>
                                                <td class="text-center" style='text-align: center; vertical-align: middle; height: 50px;'>
                                                    <?php
                                                    if ($technicianCount > 0) {
                                                        $percentage = ceil(($results[$subsidiary][$level]['countSavoirs']) * 100 / $technicianCount);
                                                    } else {
                                                        $percentage = 0; // or any other appropriate value or message
                                                    }
                                                    echo $percentage . '%'; ?>
                                                </td>
                                                <td class="text-center" style='text-align: center; vertical-align: middle; height: 50px;'>
                                                    <?php echo $results[$subsidiary][$level]['countTechSavFais'] ?> / <?php echo $results[$subsidiary][$level]['technicians'] ?>
                                                </td>
                                                <td class="text-center" style='text-align: center; vertical-align: middle; height: 50px;'>
                                                    <?php $technicianCount = $results[$subsidiary][$level]['technicians'];
                                                    if ($technicianCount > 0) {
                                                        $percentage = ceil(($results[$subsidiary][$level]['countTechSavFais']) * 100 / $technicianCount);
                                                    } else {
                                                        $percentage = 0; // or any other appropriate value or message
                                                    }
                                                    echo $percentage . '%'; ?>
                                                </td>
                                                <td class="text-center" style='text-align: center; vertical-align: middle; height: 50px;'>
                                                    <?php echo $results[$subsidiary][$level]['countMaSavFais'] ?> / <?php echo $results[$subsidiary][$level]['technicians'] ?>
                                                </td>
                                                <td class="text-center" style='text-align: center; vertical-align: middle; height: 50px;'>
                                                    <?php $technicianCount = $results[$subsidiary][$level]['technicians'];
                                                    if ($technicianCount > 0) {
                                                        $percentage = ceil(($results[$subsidiary][$level]['countMaSavFais']) * 100 / $technicianCount);
                                                    } else {
                                                        $percentage = 0; // or any other appropriate value or message
                                                    }
                                                    echo $percentage . '%'; ?>
                                                </td>
                                            </tr>
                                            <?php 
                                                // Calculer le total pour les filiales
                                                $results['GROUPE CFAO'][$level]['technicians'] += $results[$subsidiary][$level]['technicians'];
                                                $results['GROUPE CFAO'][$level]['test'] += $results[$subsidiary][$level]['test'];
                                                $results['GROUPE CFAO'][$level]['countSavoirs'] += $results[$subsidiary][$level]['countSavoirs'];
                                                $results['GROUPE CFAO'][$level]['countMaSavFais'] += $results[$subsidiary][$level]['countMaSavFais'];
                                                $results['GROUPE CFAO'][$level]['countTechSavFais'] += $results[$subsidiary][$level]['countTechSavFais'];
                                            ?>
                                        <?php } ?>
                                    <?php } ?>
                                    <tr class="odd" etat="">
                                        <th class=" text-center" colspan="10">
                                        </th>
                                    </tr>
                                        <th class="text-uppercase text-center" style='text-align: center; vertical-align: middle; height: 50px;' rowspan="<?php echo $i ?>">
                                            <?php echo 'GROUPE CFAO' ?>
                                        </th>
                                        <?php foreach (['Junior', 'Senior', 'Expert', 'Global'] as $level) { ?>
                                            <tr class="odd <?php if ($level === 'Global') echo 'fw-bolder'; ?>" etat="" <?php if ($level === 'Global') echo 'style="background-color: #edf2f7; font-size: bolder;"'; ?>>
                                                <td class="text-center" style='text-align: center; vertical-align: middle; height: 50px;'>
                                                    <?php echo $level ?>
                                                </td>
                                                <td class="text-center" style="background-color: #edf2f7; text-align: center; vertical-align: middle; height: 50px;">
                                                    <?php echo $results['GROUPE CFAO'][$level]['countSavoirs'] + $results['GROUPE CFAO'][$level]['countTechSavFais'] + $results['GROUPE CFAO'][$level]['countMaSavFais'] ?> / <?php echo $results['GROUPE CFAO'][$level]['technicians'] * 3 ?>
                                                </td>
                                                <td class="text-center" style="background-color: #edf2f7; text-align: center; vertical-align: middle; height: 50px;">
                                                    <?php $technicianCount = $results['GROUPE CFAO'][$level]['technicians'];
                                                    if ($technicianCount > 0) {
                                                        $percentage = ceil(($results['GROUPE CFAO'][$level]['countSavoirs'] + $results['GROUPE CFAO'][$level]['countTechSavFais'] + $results['GROUPE CFAO'][$level]['countMaSavFais']) * 100 / ($technicianCount * 3));
                                                    } else {
                                                        $percentage = 0; // or any other appropriate value or message
                                                    }
                                                    echo $percentage . '%'; ?>
                                                </td>
                                                <td class="text-center" style='text-align: center; vertical-align: middle; height: 50px;'>
                                                    <?php echo $results['GROUPE CFAO'][$level]['countSavoirs'] ?> / <?php echo $results['GROUPE CFAO'][$level]['technicians'] ?>
                                                </td>
                                                <td class="text-center" style='text-align: center; vertical-align: middle; height: 50px;'>
                                                    <?php
                                                    if ($technicianCount > 0) {
                                                        $percentage = ceil(($results['GROUPE CFAO'][$level]['countSavoirs']) * 100 / $technicianCount);
                                                    } else {
                                                        $percentage = 0; // or any other appropriate value or message
                                                    }
                                                    echo $percentage . '%'; ?>
                                                </td>
                                                <td class="text-center" style='text-align: center; vertical-align: middle; height: 50px;'>
                                                    <?php echo $results['GROUPE CFAO'][$level]['countTechSavFais'] ?> / <?php echo $results['GROUPE CFAO'][$level]['technicians'] ?>
                                                </td>
                                                <td class="text-center" style='text-align: center; vertical-align: middle; height: 50px;'>
                                                    <?php $technicianCount = $results['GROUPE CFAO'][$level]['technicians'];
                                                    if ($technicianCount > 0) {
                                                        $percentage = ceil(($results['GROUPE CFAO'][$level]['countTechSavFais']) * 100 / $technicianCount);
                                                    } else {
                                                        $percentage = 0; // or any other appropriate value or message
                                                    }
                                                    echo $percentage . '%'; ?>
                                                </td>
                                                <td class="text-center" style='text-align: center; vertical-align: middle; height: 50px;'>
                                                    <?php echo $results['GROUPE CFAO'][$level]['countMaSavFais'] ?> / <?php echo $results['GROUPE CFAO'][$level]['technicians'] ?>
                                                </td>
                                                <td class="text-center" style='text-align: center; vertical-align: middle; height: 50px;'>
                                                    <?php $technicianCount = $results['GROUPE CFAO'][$level]['technicians'];
                                                    if ($technicianCount > 0) {
                                                        $percentage = ceil(($results['GROUPE CFAO'][$level]['countMaSavFais']) * 100 / $technicianCount);
                                                    } else {
                                                        $percentage = 0; // or any other appropriate value or message
                                                    }
                                                    echo $percentage . '%'; ?>
                                                </td>
                                            </tr>
                                        <?php } ?>
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
                <button type="tton" id="excel" class="btn btn-light-danger me-3" data-bs-toggle="modal"
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
<script>
    var result = <?php echo json_encode($results); ?>

    console.log('results', result);
</script>
<?php include_once "partials/footer.php"; ?>
<?php } ?>