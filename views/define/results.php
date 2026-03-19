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
    $results = $academy->results;
    $exams = $academy->exams;
    $tests = $academy->tests;
    $allocations = $academy->allocations;
    
    $levels = [
        'Junior' => ['Junior'], 
        'Senior' => ['Junior', 'Senior'], 
        'Expert' => ['Junior', 'Senior', 'Expert']
    ];
    
    // Extraire les données pour l'affichage
    $id = $_SESSION['id'];

    $manager = $users->findOne([
        '$and' => [
            [
                '_id'=> new MongoDB\BSON\ObjectId($id),
                "active" => true
            ],
        ],
    ]);
    
    
    function getColorClass($score) {
        if ($score >= 80) {
            return 'text-success';
        } elseif ($score >= 60) {
            return 'text-warning';
        } else {
            return 'text-danger';
        }
    }
    ?>
<?php include_once "partials/header.php"; ?>
<!--begin::Title-->
<title><?php echo $list_result ?> | CFAO Mobility Academy</title>
<!--end::Title-->

<style>
    #kt_customers_table_wrapper td:nth-child(1) {
        position: sticky;
        left: 0;
    }
    #kt_customers_table_wrapper td:nth-child(1) {
        background: #edf2f7;
    }
    #kt_customers_table_wrapper th:nth-child(1) {
        z-index:2;
    }
</style>

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
                    <?php echo $list_result ?> </h1>
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
                                class="table align-middle table-bordered fs-6 gy-5 dataTable no-footer"
                                id="kt_customers_table">
                                <thead>
                                    <tr class="text-start text-black fw-bold fs-7 text-uppercase gs-0">
                                        <th class="min-w-150px sorting text-center" tabindex="0"
                                            aria-controls="kt_customers_table" colspan="2"
                                            aria-label="Customer Name: activate to sort column ascending"
                                            style="width: 125px;"><?php echo $technicienss ?>
                                        </th>
                                        <th class="min-w-150px sorting text-center" tabindex="0"
                                            aria-controls="kt_customers_table" colspan="4"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $Level ?> <?php echo $junior ?></th>
                                        <th class="min-w-150px sorting text-center" tabindex="0"
                                            aria-controls="kt_customers_table" colspan="4"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $Level ?> <?php echo $senior ?></th>
                                        <th class="min-w-150px sorting text-center" tabindex="0"
                                            aria-controls="kt_customers_table" colspan="4"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $Level ?> <?php echo $expert ?></th>
                                        <tr></tr>
                                        <th class="min-w-200px sorting text-center text-black fw-bold" tabindex="0"
                                            aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $prenomsNoms ?></th>
                                        <th class="min-w-80px sorting text-center text-black fw-bold" tabindex="0"
                                            aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $qcm_connaissances_tech ?></th>
                                        <th class="min-w-80px sorting text-center text-black fw-bold" tabindex="0"
                                            aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $qcm_tache_pro_tech ?></th>
                                        <th class="min-w-80px sorting text-center text-black fw-bold" tabindex="0"
                                            aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $qcm_tache_pro_manager ?></th>
                                        <th class="min-w-80px sorting text-center text-black fw-bold" tabindex="0"
                                            aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $note_test_junior ?></th>
                                        <th class="min-w-80px sorting text-center text-black fw-bold" tabindex="0"
                                            aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $qcm_connaissances_tech ?></th>
                                        <th class="min-w-80px sorting text-center text-black fw-bold" tabindex="0"
                                            aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $qcm_tache_pro_tech ?></th>
                                        <th class="min-w-80px sorting text-center text-black fw-bold" tabindex="0"
                                            aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $qcm_tache_pro_manager ?></th>
                                        <th class="min-w-80px sorting text-center text-black fw-bold" tabindex="0"
                                            aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $note_test_senior ?></th>
                                        <th class="min-w-80px sorting text-center text-black fw-bold" tabindex="0"
                                            aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $qcm_connaissances_tech ?></th>
                                        <th class="min-w-80px sorting text-center text-black fw-bold" tabindex="0"
                                            aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $qcm_tache_pro_tech ?></th>
                                        <th class="min-w-80px sorting text-center text-black fw-bold" tabindex="0"
                                            aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $qcm_tache_pro_manager ?></th>
                                        <th class="min-w-80px sorting text-center text-black fw-bold" tabindex="0"
                                            aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $note_test_expert ?>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="fw-semibold text-gray-600" id="table">
                                    <?php 
                                    // Fonction pour calculer le pourcentage
                                    function calculatePercentage($scores, $totals) {
                                        $scoreSum = array_sum($scores);
                                        $totalSum = array_sum($totals);
                                        return $totalSum == 0 ? 0 : ($scoreSum * 100 / $totalSum);
                                    }

                                    foreach ($manager['users'] as $technicians) { 

                                        // Initialiser les résultats
                                        $resultsFacScore = [
                                            'Junior' => [],
                                            'Senior' => [],
                                            'Expert' => []
                                        ];

                                        $resultsDeclaScore = [
                                            'Junior' => [],
                                            'Senior' => [],
                                            'Expert' => []
                                        ];

                                        $resultsDeclaTechScore = [
                                            'Junior' => [],
                                            'Senior' => [],
                                            'Expert' => []
                                        ];

                                        $resultsDeclaManScore = [
                                            'Junior' => [],
                                            'Senior' => [],
                                            'Expert' => []
                                        ];
                                        

                                        $resultsFacTotal = [
                                            'Junior' => [],
                                            'Senior' => [],
                                            'Expert' => []
                                        ];

                                        $resultsDeclaTotal = [
                                            'Junior' => [],
                                            'Senior' => [],
                                            'Expert' => []
                                        ];

                                        $resultsDeclaTechTotal = [
                                            'Junior' => [],
                                            'Senior' => [],
                                            'Expert' => []
                                        ];

                                        $resultsDeclaManTotal = [
                                            'Junior' => [],
                                            'Senior' => [],
                                            'Expert' => []
                                        ];

                                        $user = $users->findOne([
                                            '$and' => [
                                                [
                                                    '_id'=> new MongoDB\BSON\ObjectId($technicians),
                                                    "active" => true
                                                ],
                                            ],
                                        ]);
                                        $id = $user['_id'];
                                        
                                        foreach ($levels[$user['level']] as $level) {
                                            $alloFac = $allocations->findOne([
                                                'user' => new MongoDB\BSON\ObjectId($id),
                                                'level' => $level,
                                                'type' => 'Factuel',
                                                'active' => true
                                            ]);

                                            $alloDecla = $allocations->findOne([
                                                'user' => new MongoDB\BSON\ObjectId($id),
                                                'level' => $level,
                                                'type' => 'Declaratif',
                                                'activeManager' => true,
                                                'active' => true
                                            ]);

                                            if ($alloFac && $alloDecla) {
                                                // Récupérer les résultats pour le type Factuel
                                                $resultFac = $results->findOne([
                                                    'user' => new MongoDB\BSON\ObjectId($id),
                                                    'level' => $level,
                                                    'typeR' => 'Technicien',
                                                    'type' => 'Factuel',
                                                    "active" => true
                                                ]);
                                                if ($resultFac) {
                                                    $resultsFacScore[$level][] = $resultFac['score'];
                                                    $resultsFacTotal[$level][] = $resultFac['total'];
                                                }

                                                // Récupérer les résultats pour le type Déclaratif Technicien
                                                $resultDeclaTech = $results->findOne([
                                                    'user' => new MongoDB\BSON\ObjectId($id),
                                                    'level' => $level,
                                                    'typeR' => 'Techniciens',
                                                    'type' => 'Declaratif',
                                                    "active" => true
                                                ]);
                                                if ($resultDeclaTech) {
                                                    $resultsDeclaTechScore[$level][] = $resultDeclaTech['score'];
                                                    $resultsDeclaTechTotal[$level][] = $resultDeclaTech['total'];
                                                }

                                                // Récupérer les résultats pour le type Déclaratif Manager
                                                $resultDeclaMan = $results->findOne([
                                                    'user' => new MongoDB\BSON\ObjectId($id),
                                                    'level' => $level,
                                                    'typeR' => 'Managers',
                                                    'type' => 'Declaratif',
                                                    "active" => true
                                                ]);
                                                if ($resultDeclaMan) {
                                                    $resultsDeclaManScore[$level][] = $resultDeclaMan['score'];
                                                    $resultsDeclaManTotal[$level][] = $resultDeclaMan['total'];
                                                }

                                                // Récupérer les résultats pour le type Déclaratif
                                                $resultDecla = $results->findOne([
                                                    'user' => new MongoDB\BSON\ObjectId($id),
                                                    'level' => $level,
                                                    'typeR' => 'Technicien - Manager',
                                                    'type' => 'Declaratif',
                                                    "active" => true
                                                ]);
                                                if ($resultDecla) {
                                                    $resultsDeclaScore[$level][] = $resultDecla['score'];
                                                    $resultsDeclaTotal[$level][] = $resultDecla['total'];
                                                }
                                            }
                                        }

                                        // Calculer les pourcentages pour chaque niveau
                                        $percentagesFac = [];
                                        $percentagesDecla = [];
                                        $percentagesDeclaTech = [];
                                        $percentagesDeclaMan = [];

                                        foreach ($levels as $level => $subLevels) {
                                            foreach ($subLevels as $subLevel) {
                                                $percentagesFac[$subLevel] = calculatePercentage($resultsFacScore[$subLevel], $resultsFacTotal[$subLevel]);
                                                $percentagesDecla[$subLevel] = calculatePercentage($resultsDeclaScore[$subLevel], $resultsDeclaTotal[$subLevel]);
                                                $percentagesDeclaTech[$subLevel] = calculatePercentage($resultsDeclaTechScore[$subLevel], $resultsDeclaTechTotal[$subLevel]);
                                                $percentagesDeclaMan[$subLevel] = calculatePercentage($resultsDeclaManScore[$subLevel], $resultsDeclaManTotal[$subLevel]);
                                            }
                                        }
                                    ?>
                                        <tr class="odd">
                                            <td class="text-center">
                                                <?php echo $user["firstName"].' '.$user["lastName"]; ?>
                                            </td>
                                            <?php foreach (['Junior', 'Senior', 'Expert'] as $level) { ?>
                                                <?php if (!empty($percentagesFac[$level])) { ?>
                                                    <td class="text-center <?php echo getColorClass(round($percentagesFac[$level])) ?>">
                                                        <?php echo round($percentagesFac[$level]); ?>%
                                                    </td>
                                                <?php } else { ?>
                                                    <td class="text-center" style="background-color: #f9f9f9;">
                                                    </td>
                                                <?php } ?>
                                                <?php if (!empty($percentagesDeclaTech[$level])) { ?>
                                                    <td class="text-center <?php echo getColorClass(round($percentagesDeclaTech[$level])) ?>">
                                                        <?php echo round($percentagesDeclaTech[$level]); ?>%
                                                    </td>
                                                <?php } else { ?>
                                                    <td class="text-center" style="background-color: #f9f9f9;">
                                                    </td>
                                                <?php } ?>
                                                <?php if (!empty($percentagesDeclaMan[$level])) { ?>
                                                    <td class="text-center <?php echo getColorClass(round($percentagesDeclaMan[$level])) ?>">
                                                        <?php echo round($percentagesDeclaMan[$level]); ?>%
                                                    </td>
                                                <?php } else { ?>
                                                    <td class="text-center" style="background-color: #f9f9f9;">
                                                    </td>
                                                <?php } ?>
                                                <?php if (!empty(round(($percentagesFac[$level] + $percentagesDecla[$level]) / 2))) { ?>
                                                    <td class="text-center <?php echo getColorClass(round(($percentagesFac[$level] + $percentagesDecla[$level]) / 2)) ?>">
                                                        <?php echo round(($percentagesFac[$level] + $percentagesDecla[$level]) / 2); ?>%
                                                    </td>
                                                <?php } else { ?>
                                                    <td class="text-center" style="background-color: #f9f9f9;">
                                                    </td>
                                                <?php } ?>
                                            <?php } ?>
                                            <!--end::Menu-->
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
            <!-- <div class="d-flex justify-content-end align-items-center" style="margin-top: 20px;">
                <button type="button" id="excel" title="Cliquez ici pour importer la table" class="btn btn-primary">
                    <i class="ki-duotone ki-exit-up fs-2"><span class="path1"></span><span class="path2"></span></i>
                    Excel
                </button>
            </div> -->
            <!--end::Export dropdown-->
        </div>
        <!--end::Container-->
    </div>
    <!--end::Post-->
</div>
<!--end::Body-->
<?php include_once "partials/footer.php"; ?>
<?php } ?>