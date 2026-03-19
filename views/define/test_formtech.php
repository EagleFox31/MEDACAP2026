<?php
// Démarrer la session
session_start();

// Inclure les fichiers nécessaires
include_once "../language.php";
// include_once "/getValidatedResults.php"; // Fonction pour obtenir les résultats validés
include_once "../userFilters.php"; // Fonction pour filtrer les utilisateurs

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
} else {

    require_once "../../vendor/autoload.php";

    // Create connection
    $conn = new MongoDB\Client("mongodb://localhost:27017");

    // Récupérer le profil utilisateur de la session
    $profile = $_SESSION['profile'];
    $userCountry = isset($_SESSION['country']) ? $_SESSION['country'] : null;

    // Récupérer les paramètres depuis l'URL
    $selectedCountry = isset($_GET['country']) ? $_GET['country'] : null;
    $selectedAgency = isset($_GET['agency']) ? $_GET['agency'] : null;
    $selectedLevel = isset($_GET['level']) ? $_GET['level'] : null;

    // Connexion à MongoDB
    $academy = $conn->academy;

    // Fonction pour récupérer tous les techniciens actifs
    function getAllTechnicians($academy, $profile, $selectedCountry = null, $selectedLevel = null, $selectedAgency = null) {
        // Filtrer pour obtenir uniquement les techniciens selon le profil de l'utilisateur
        $technicians = filterUsersByProfile($academy, $profile, $selectedCountry, $selectedLevel, $selectedAgency);

        return $technicians;
    }

    // Récupérer tous les techniciens
    $technicians = getAllTechnicians($academy, $profile, $selectedCountry, $selectedLevel, $selectedAgency);

    // Fonction pour obtenir les scores d'un technicien par niveau
    function getScoresByTechnicianAndLevel($academy, $technicianId, $level) {
        $resultsCollection = $academy->results;
    
        // Préparer les critères de requête communs
        $commonCriteria = [
            'user' => new MongoDB\BSON\ObjectId($technicianId),
            'level' => $level,
            'active' => true
        ];
    
        // Récupérer les résultats 'Factuel'
        $factuelCriteria = array_merge($commonCriteria, ['type' => 'Factuel']);
        $factuelCursor = $resultsCollection->find($factuelCriteria);
    
        // Récupérer les résultats 'Declaratif' avec 'typeR' = 'Technicien'
        $declaratifCriteria = array_merge($commonCriteria, [
            'type' => 'Declaratif',
            'typeR' => 'Technicien'
        ]);
        $declaratifCursor = $resultsCollection->find($declaratifCriteria);
    
        $scores = [];
    
        // Traiter les résultats 'Factuel'
        foreach ($factuelCursor as $result) {
            $speciality = isset($result['speciality']) ? $result['speciality'] : 'Inconnu';
    
            $score = isset($result['score']) ? $result['score'] : 0;
            $total = isset($result['total']) ? $result['total'] : 0;
            $percentage = ($total > 0) ? ($score / $total) * 100 : 0;
    
            if (!isset($scores[$speciality])) {
                $scores[$speciality] = [];
            }
    
            $scores[$speciality]['Factuel'] = $percentage;
        }
    
        // Traiter les résultats 'Declaratif'
        foreach ($declaratifCursor as $result) {

            $speciality = isset($result['speciality']) ? $result['speciality'] : 'Inconnu';
    
            $score = isset($result['score']) ? $result['score'] : 0;
            $total = isset($result['total']) ? $result['total'] : 0;
            $percentage = ($total > 0) ? ($score / $total) * 100 : 0;
    
            if (!isset($scores[$speciality])) {
                $scores[$speciality] = [];
            }
    
            $scores[$speciality]['Declaratif'] = $percentage;
        }
    
        return $scores;
    }
    

    // Fonction pour déterminer les types d'accompagnement en fonction des scores
    function determineAccompagnement($taskScore, $knowledgeScore) {
        // Conditions combinées selon les plages définies
        // Vous pouvez ajuster ces conditions selon vos besoins

        $accompagnement = [];

        if ($taskScore >= 0 && $taskScore <= 60) {
            if ($knowledgeScore >= 0 && $knowledgeScore <= 60) {
                $accompagnement = ['Présentielle', 'Distancielle', 'E-learning', 'Coaching', 'Mentoring'];
            } elseif ($knowledgeScore > 60 && $knowledgeScore <= 80) {
                $accompagnement = ['Présentielle', 'E-learning', 'Coaching', 'Mentoring'];
            } elseif ($knowledgeScore > 80 && $knowledgeScore <= 100) {
                $accompagnement = ['Présentielle', 'Coaching', 'Mentoring'];
            }
        } elseif ($taskScore > 60 && $taskScore <= 80) {
            if ($knowledgeScore >= 0 && $knowledgeScore <= 60) {
                $accompagnement = ['Présentielle', 'Distancielle', 'E-learning', 'Coaching'];
            } elseif ($knowledgeScore > 60 && $knowledgeScore <= 80) {
                $accompagnement = ['Présentielle', 'E-learning', 'Coaching'];
            } elseif ($knowledgeScore > 80 && $knowledgeScore <= 100) {
                $accompagnement = ['Présentielle', 'Coaching'];
            }
        } elseif ($taskScore > 80 && $taskScore <= 100) {
            if ($knowledgeScore >= 0 && $knowledgeScore <= 60) {
                $accompagnement = ['Distancielle', 'E-learning'];
            } elseif ($knowledgeScore > 60 && $knowledgeScore <= 80) {
                $accompagnement = ['E-learning'];
            } elseif ($knowledgeScore > 80 && $knowledgeScore <= 100) {
                $accompagnement = [];
            }
        }

        return $accompagnement;
    }

    // Fonction pour récupérer les formations recommandées pour un technicien et un niveau
    function getRecommendedTrainings($academy, $technician, $level, $scores) {
        $trainingsCollection = $academy->trainings;

        $recommendations = [];

        foreach ($scores as $speciality => $scoreData) {
            $taskScore = isset($scoreData['Declaratif']) ? $scoreData['Declaratif'] : 0;
            $knowledgeScore = isset($scoreData['Factuel']) ? $scoreData['Factuel'] : 0;

            // Déterminer les types d'accompagnement
            $typesAccompagnement = determineAccompagnement($taskScore, $knowledgeScore);

            if (empty($typesAccompagnement)) {
                continue; // Aucun type d'accompagnement applicable
            }

            // Récupérer les formations correspondantes
            $brandField = 'brand' . $level; // Ex: 'brandJunior', 'brandSenior', 'brandExpert'
            $brands = isset($technician[$brandField]) ? $technician[$brandField] : [];

            $query = [
                'brand' => ['$in' => $brands],
                'speciality' => $speciality,
                'level' => $level,
                'type' => ['$in' => $typesAccompagnement],
                'active' => true
            ];

            $cursor = $trainingsCollection->find($query);

            foreach ($cursor as $training) {
                $trainingCode = $training['code'];
                $recommendations[$trainingCode] = $training;
            }
        }

        return array_values($recommendations);
    }

    // Préparer les recommandations pour chaque technicien
    $recommendations = [];

    foreach ($technicians as $technician) {
        $technicianId = (string)$technician['_id'];
        $technicianLevel = $technician['level'];

        // Déterminer les niveaux applicables
        $levels = [];
        if ($technicianLevel == 'Junior') {
            $levels[] = 'Junior';
        } elseif ($technicianLevel == 'Senior') {
            $levels[] = 'Junior';
            $levels[] = 'Senior';
        } elseif ($technicianLevel == 'Expert') {
            $levels[] = 'Junior';
            $levels[] = 'Senior';
            $levels[] = 'Expert';
        }

        // Pour chaque niveau applicable, générer les recommandations
        foreach ($levels as $level) {
            // Récupérer les scores du technicien pour ce niveau
            $scores = getScoresByTechnicianAndLevel($academy, $technicianId, $level);

            // Obtenir les formations recommandées
            $levelRecommendations = getRecommendedTrainings($academy, $technician, $level, $scores);

            $recommendations[$technicianId][$level] = $levelRecommendations;


        }
    }
    ?>
<?php include_once "partials/header.php"; ?>
<!--begin::Title-->
<title><?php echo $train_tech ?> | CFAO Mobility Academy</title>
<!--end::Title-->
<style>
    table {
        border-collapse: collapse;
        width: 100%;
        font-size: 14px;
    }

    table th, table td {
        border: 1px solid #ddd;
        padding: 8px;
        text-align: center;
    }

    table th {
        background-color: #f2f2f2;
        font-weight: bold;
        text-transform: uppercase;
    }

    /* Highlight every other row */
    table tr:nth-child(even) {
        background-color: #f9f9f9;
    }

    /* Hover effect */
    table tr:hover {
        background-color: #f1f1f1;
    }

    /* Center text */
    .text-center {
        text-align: center;
    }

    /* Badge colors for levels */
    .badge {
        display: inline-block;
        padding: 0.25em 0.5em;
        font-size: 85%;
        font-weight: bold;
        color: #fff;
        text-align: center;
        border-radius: 0.25rem;
    }

    .badge-junior {
        background-color: #007bff;
    }

    .badge-senior {
        background-color: #ffc107;
    }

    .badge-expert {
        background-color: #28a745;
    }

    .badge-training {
        background-color: #6c757d;
    }

    /* Highlight missing information */
    .text-danger {
        color: #dc3545;
        font-weight: bold;
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
                <h1 class="text-dark fw-bold my-1 fs-2">
                    <?php echo $list_training ?> </h1>
                <!--end::Title-->
                <div class="card-title">
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
                                class="table align-middle table-row-dashed fs-6 gy-5 dataTable no-footer"
                                id="kt_customers_table">
                                <thead>
                                    <tr class="text-start text-black fw-bold fs-7 text-uppercase gs-0">

                                        <th class="min-w-125px sorting text-center " tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Payment Method: activate to sort column ascending"
                                            style="width: 126.516px; text-align: center;"><?php echo $prenomsNomsTechs ?>
                                        </th>
                                        <th class="min-w-125px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Customer Name: activate to sort column ascending"
                                            style="width: 125px; text-align: center;"><?php echo $levelTech ?>
                                        </th>
                                        <th class="min-w-125px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Customer Name: activate to sort column ascending"
                                            style="width: 125px; text-align: center;"><?php echo $junior_level ?>
                                        </th>
                                        <th class="min-w-125px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Company: activate to sort column ascending"
                                            style="width: 134.188px; text-align: center;"><?php echo $senior_level ?>
                                        </th>
                                        <th class="min-w-125px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Payment Method: activate to sort column ascending"
                                            style="width: 126.516px; text-align: center;"><?php echo $expert_level ?>
                                        </th>

                                    </tr>
                                </thead>
                                <tbody class="fw-semibold text-gray-600" id="table">
                                    <?php foreach ($technicians as $technician): ?>
                                        <?php $technicianId = (string)$technician['_id']; ?>
                                        <tr>
                                            <td class="text-center">    <a href="technicianDetails.php?id=<?php echo $technicianId; ?>">
                                                        <?php echo htmlspecialchars($technician['firstName'] . ' ' . $technician['lastName']); ?>
                                                    </a></td>
                                            <td class="text-center">
                                                <span class="badge badge-<?php echo strtolower(htmlspecialchars($technician['level'])); ?>">
                                                        <?php echo htmlspecialchars($technician['level']); ?>
                                                    </span></td>
                                            <?php foreach (['Junior', 'Senior', 'Expert'] as $level): ?>
                                                <td class="text-center">
                                                <?php if (!empty($recommendations[$technicianId][$level])): ?>
                                                        <ul>
                                                            <?php foreach ($recommendations[$technicianId][$level] as $training): ?>
                                                                <span class="badge badge-training">
                                                                    <?php echo htmlspecialchars($training['code']); ?>
                                                                </span>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    <?php else: ?>
                                                        <span>Aucune formation recommandée</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
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
                <button type="button" id="excel" class="btn btn-light-primary me-3" data-bs-toggle="modal"
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
            name: `Vehicles.xlsx`
        })
    });
});
<?php include_once "partials/footer.php"; ?>
<?php
} ?>
