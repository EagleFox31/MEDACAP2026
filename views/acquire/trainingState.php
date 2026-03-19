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
        $trainingsCollection = $academy->trainings;
        $allocations = $academy->allocations;
        
        $levelOrder = [
            'Junior' => 1,
            'Senior' => 2,
            'Expert' => 3
        ];
        // Récupérer les valeurs des filtres depuis les paramètres GET
        $selectedCountry = $_GET['country'] ?? 'all';
        $selectedAgency = $_GET['agency'] ?? 'all';
        $selectedLevel = $_GET['level'] ?? 'all';
        $selectedManagerId = $_GET['manager'] ?? 'all';
        
        
        // Votre code pour les pays et agences
        $country = isset($_SESSION["country"]) ? $_SESSION["country"] : 'all'; // 'all' par défaut
        
        // Fonction utilitaire pour obtenir l'ordre des niveaux
        function getLevelOrder($level) {
            $levelOrder = [
                'Junior' => 1,
                'Senior' => 2,
                'Expert' => 3
            ];
            return isset($levelOrder[$level]) ? $levelOrder[$level] : 1;
        }

        // Récupérer les techniciens
        $manager = $_SESSION['id'];
        $profileSession = $_SESSION['profile'];
        $subsidiary = $_SESSION['subsidiary'];

        // Extraire les données pour l'affichage
        $filters = [
            'active' => true,
                //'test' => true, // Seuls les utilisateurs qui ont effectué un test
            'profile' => ['$in' => ['Technicien', 'Manager']] // Filtrer les techniciens et managers
        ];

       
        // Filtrer uniquement les managers qui ont "test: true"
        // Cela est nécessaire pour tous les managers peu importe le niveau
        $filters['$or'] = [
            ['profile' => 'Technicien'], // Inclure les techniciens (en fonction des autres filtres)
            [
                'profile' => 'Manager',
                'test' => true // Inclure uniquement les managers qui ont passé un test
            ]
        ];

        if ($profileSession === 'Manager') {
            $filters['manager'] = new MongoDB\BSON\ObjectId($manager);
        } elseif (in_array($profileSession, ['Directeur Filiale', 'Ressource Humaine'])) {
            $filters['subsidiary'] = $subsidiary;
        }

        $technicians = $users->find($filters)->toArray();

        // Filtrer les techniciens en fonction des filtres// Filtrer les techniciens en fonction des filtres
        $filteredTechnicians = array_filter($technicians, function ($technician) use ($selectedCountry, $selectedAgency, $selectedLevel, $selectedManagerId) {
            $technicianCountry = $technician['country'] ?? 'Unknown';
            $technicianAgency = $technician['agency'] ?? 'Unknown';
            $technicianLevel = $technician['level'] ?? 'Unknown';
            $technicianManagerId = isset($technician['manager']) ? (string)$technician['manager'] : 'none';

            $countryMatch = ($selectedCountry === 'all') || ($technicianCountry === $selectedCountry);
            $agencyMatch = ($selectedAgency === 'all') || ($technicianAgency === $selectedAgency);
            $levelMatch = ($selectedLevel === 'all') || ($technicianLevel === $selectedLevel);
            $managerMatch = ($selectedManagerId === 'all') || ($technicianManagerId === $selectedManagerId);

            return $countryMatch && $agencyMatch && $levelMatch && $managerMatch;
        });

        function getBootstrapClass($pourcentage) {
            if ($pourcentage <= 60) {
                return 'danger'; 
            } else if ($pourcentage <= 80) {
                return 'warning';
            } else {
                return 'success'; 
            }
        }

        function getclasses($percent){
            $bootstrapclass = getBootstrapClass($percent);
            return $bootstrapclass;                                    
        }
?>
<?php include_once "partials/header.php"; ?>
<!--begin::Title-->
<?php if ($_SESSION['profile'] == 'Manager') { ?>
    <title><?php echo $etat_avancement_training_collab ?> | CFAO Mobility Academy</title>
<?php } else { ?>
    <title><?php echo $etat_avancement_training_tech ?> | CFAO Mobility Academy</title>
<?php } ?> 
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
        padding: 0.3em 0.6em;
        font-size: 0.9em;
        font-weight: bold;
        color: #fff;
        background-color: #6c757d;
        border-radius: 5px;
        margin: 3px 5px; /* Ajout de marges pour espacement */
        text-transform: uppercase;
        cursor: pointer; /* Apparence interactive */
    }

    .badge:hover {
        background-color: #5a6268; /* Couleur plus foncée au survol */
    }

    .level-column {
        vertical-align: top; /* Alignement vertical des colonnes */
        text-align: center; /* Centrage des badges */
    }

    .empty-level {
        color: #888; /* Couleur pour "Aucune formation recommandée" */
        font-style: italic; /* Texte en italique */
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

    .badge-training {
        position: relative;
        cursor: pointer;
    }

    .badge-training:hover::after {
        content: attr(title); /* Affiche le contenu de l'attribut title */
        position: absolute;
        top: 100%;
        left: 50%;
        transform: translateX(-50%);
        background-color: #333;
        color: #fff;
        padding: 5px;
        border-radius: 4px;
        white-space: nowrap;
        z-index: 10;
        font-size: 12px;
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
                <?php if ($_SESSION['profile'] == 'Manager') { ?>
                    <!--begin::Title-->
                    <h1 class="text-dark fw-bolder my-1 fs-1">
                        <?php echo $etat_avancement_training_collab ?> 
                    </h1>
                    <!--end::Title-->
                <?php } else { ?>
                    <!--begin::Title-->
                    <h1 class="text-dark fw-bolder my-1 fs-1">
                        <?php echo $etat_avancement_training_tech ?> 
                    </h1>
                    <!--end::Title-->
                <?php } ?> 
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
                    <!--begin::Filtres -->
                    <div class="container my-4">
                        <div class="row g-3 align-items-center">
                            <!-- Filtre Pays -->
                            <div class="col-md-4">
                                <label for="country-filter" class="form-label d-flex align-items-center">
                                    <i class="bi bi-geo-alt-fill me-2 text-primary"></i> Pays
                                </label>
                                <select id="country-filter" name="country" class="form-select" <?php if ($profileSession == 'Admin' || $profileSession == 'Resource Humaine' || $profileSession == 'Manager' || $profileSession == 'Directeur Filiale') echo 'disabled'; ?>>
                                    <option value="all" <?php if ($selectedCountry === 'all') echo 'selected'; ?>>Tous les pays</option>
                                    <?php foreach ($countries as $countryOption): ?>
                                    <option value="<?php echo htmlspecialchars($countryOption); ?>" 
                                            <?php if ($selectedCountry === $countryOption) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($countryOption); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Filtre Agences -->
                            <div class="col-md-4">
                                <label for="agency-filter" class="form-label d-flex align-items-center">
                                    <i class="bi bi-building me-2 text-warning"></i> Agence
                                </label>
                                <select id="agency-filter" name="agency" class="form-select">
                                    <option value="all" <?php if ($selectedAgency === 'all') echo 'selected'; ?>>Toutes les agences</option>
                                    <?php
                                    if ($selectedCountry !== 'all' && isset($agencies[$selectedCountry])) {
                                        foreach ($agencies[$selectedCountry] as $agencyOption) {
                                            ?>
                                            <option value="<?php echo htmlspecialchars($agencyOption); ?>" 
                                                    <?php if ($selectedAgency === $agencyOption) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($agencyOption); ?>
                                            </option>
                                            <?php
                                        }
                                    }
                                    ?>
                                </select>
                            </div>

                            <!-- Filtre Manager -->
                            <div class="col-md-4">
                                <label for="manager-filter" class="form-label d-flex align-items-center">
                                    <i class="bi bi-person-fill me-2 text-info"></i> Manager
                                </label>
                                <select id="manager-filter" name="manager" class="form-select" <?php if ($profileSession == 'Manager') echo 'disabled'; ?>>
                                    <option value="all" selected>Tous les managers</option>
                                    <!-- Options des managers seront insérées ici dynamiquement -->
                                </select>
                            </div>
                        </div>
                    </div>
                    <!--end::Filtres -->
                    <!--begin::Table-->
                    <div id="kt_customers_table_wrapper" class="dataTables_wrapper dt-bootstrap4 no-footer">
                        <div class="table-responsive">
                            <table aria-describedby=""
                                class="table align-middle table-bordered fs-6 gy-5 dataTable no-footer"
                                id="kt_customers_table">
                                <thead>
                                    <tr class="text-start text-black fw-bold fs-7 text-uppercase gs-0">

                                        <th class="min-w-200px sorting text-center " tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Payment Method: activate to sort column ascending"
                                            style="width: 200px; text-align: center;"><?php echo $prenomsNoms ?>
                                        </th>
                                        <th class="min-w-125px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="2"
                                            aria-label="Customer Name: activate to sort column ascending"
                                            style="width: 125px; text-align: center;"><?php echo $training_done_by_tech.' '.$junior ?>
                                        </th>
                                        <th class="min-w-125px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="2"
                                            aria-label="Company: activate to sort column ascending"
                                            style="width: 125px; text-align: center;"><?php echo $training_done_by_tech.' '.$senior ?>
                                        </th>
                                        <th class="min-w-125px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="2"
                                            aria-label="Payment Method: activate to sort column ascending"
                                            style="width: 126.516px; text-align: center;"><?php echo $training_done_by_tech.' '.$expert ?>
                                        </th>

                                    </tr>
                                </thead>
                                <tbody class="fw-semibold text-gray-600" id="table">
                                    <?php 
                                        // Fonction pour récupérer les allocations
                                        function getAllocation($allocations, $userId, $level, $type) {
                                            return $allocations->findOne([
                                                "user" => new MongoDB\BSON\ObjectId($userId),
                                                "level" => $level,
                                                "type" => $type,
                                            ]);
                                        }
                                        foreach ($filteredTechnicians as $technician):
                                            $technicianId = (string)$technician['_id'];
                                            // Récupérer les allocations pour chaque niveau et type
                                            $levels = ['Junior', 'Senior', 'Expert'];
                                            $types = ['Factuel', 'Declaratif'];
                                            
                                            
                                            $allocationsData = [];
                                            $allocationsDatas = [];
                                            // Récupérer les formations pour chaque niveau d'un technicien
                                            $trainingsData = [];
                                            
                                            foreach ($levels as $level) {
                                                $trainingsData[$level] = $trainingsCollection->find([
                                                    'users' => new MongoDB\BSON\ObjectId($technicianId),
                                                    'level' => $level,
                                                    'active' => true,
                                                ])->toArray();
                                            }
                                            foreach ($levels as $level) {
                                                $allocationsDatas[$level] = $allocations->find([
                                                    'user' => new MongoDB\BSON\ObjectId($technicianId),
                                                    'level' => $level,
                                                    'type' => 'Training',
                                                    'active' => true,
                                                ])->toArray();
                                            }
                                            foreach ($levels as $level) {
                                                foreach ($types as $type) {
                                                    $allocationsData["allocate{$type}{$level}"] = getAllocation($allocations, $technicianId, $level, $type);
                                                }
                                            }
                                            
                                            $totalAllocationJu = count($allocationsDatas['Junior']);
                                            $totalTrainingJu = count($trainingsData['Junior']) ? count($trainingsData['Junior']) : 1;
                                            $totalAllocationSe = count($allocationsDatas['Senior']);
                                            $totalTrainingSe = count($trainingsData['Senior']) ? count($trainingsData['Senior']) : 1;
                                            $totalAllocationEx = count($allocationsDatas['Expert']);
                                            $totalTrainingEx = count($trainingsData['Expert']) ? count($trainingsData['Expert']) : 1;
                                            // Accéder aux allocations
                                            $allocateFacJu = $allocationsData['allocateFactuelJunior'];
                                            $allocateFacSe = $allocationsData['allocateFactuelSenior'];
                                            $allocateFacEx = $allocationsData['allocateFactuelExpert'];
                                            $allocateDeclaJu = $allocationsData['allocateDeclaratifJunior'];
                                            $allocateDeclaSe = $allocationsData['allocateDeclaratifSenior'];
                                            $allocateDeclaEx = $allocationsData['allocateDeclaratifExpert']; 
                                        ?>
                                        <tr>
                                            <td class="text-center">
                                                <?php echo htmlspecialchars($technician['firstName'].' '.$technician['lastName']); ?>
                                            </td>
                                            <td class="text-center">
                                                <?php echo count($allocationsDatas['Junior']); ?> / <?php echo count($trainingsData['Junior']); ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex flex-column w-100 me-2">
                                                    <span class="text-gray-400 me-2 fw-bolder mb-2">
                                                        <?php
                                                            $percentageJu = ($totalAllocationJu / $totalTrainingJu) * 100;
                                                            echo $percentageJu; 
                                                        ?>%
                                                    </span>
                                                    <div class="progress bg-light-<?php echo getclasses($percentageJu); ?> w-100 h-5px">
                                                        <div class="progress-bar bg-<?php echo getclasses($percentageJu); ?>" role="progressbar" style="width: <?php echo $percentageJu; ?>%%;"></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <?php if (isset($allocateFacSe) && isset($allocateDeclaSe)) { ?>
                                                <td class="text-center">
                                                    <?php echo count($allocationsDatas['Senior']); ?> / <?php echo count($trainingsData['Senior']); ?>
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-flex flex-column w-100 me-2">
                                                        <span class="text-gray-400 me-2 fw-bolder mb-2">
                                                            <?php 
                                                                $percentageSe = ($totalAllocationSe / $totalTrainingSe) * 100;
                                                                echo $percentageSe; 
                                                            ?>%
                                                        </span>
                                                        <div class="progress bg-light-<?php echo getclasses($percentageSe); ?> w-100 h-5px">
                                                            <div class="progress-bar bg-<?php echo getclasses($percentageSe); ?>" role="progressbar" style="width: <?php echo $percentageSe; ?>%;"></div>
                                                        </div>
                                                    </div>
                                                </td>
                                            <?php } else { ?>
                                                <td class="text-center" style="background-color: #f9f9f9;">
                                                </td>
                                                <td class="text-center" style="background-color: #f9f9f9;">
                                                </td>
                                            <?php } ?>
                                            <?php if (isset($allocateFacEx) && isset($allocateDeclaEx)) { ?>
                                                <td class="text-center">
                                                    <?php echo count($allocationsDatas['Expert']); ?> / <?php echo count($trainingsData['Expert']); ?>
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-flex flex-column w-100 me-2">
                                                        <span class="text-gray-400 me-2 fw-bolder mb-2">
                                                            <?php 
                                                                $percentageEx = ($totalAllocationEx / $totalTrainingEx) * 100;
                                                                echo $percentageEx; 
                                                            ?>%
                                                        </span>
                                                        <div class="progress bg-light-<?php echo getclasses($percentageEx); ?> w-100 h-5px">
                                                            <div class="progress-bar bg-<?php echo getclasses($percentageEx); ?>" role="progressbar" style="width: <?php echo $percentageEx; ?>%;"></div>
                                                        </div>
                                                    </div>
                                                </td>
                                            <?php } else { ?>
                                                <td class="text-center" style="background-color: #f9f9f9;">
                                                </td>
                                                <td class="text-center" style="background-color: #f9f9f9;">
                                                </td>
                                            <?php } ?>
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
            name: `TrainingState.xlsx`
        })
    });
});
</script>
<script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    var profile = '<?php echo $profileSession; ?>';

    document.addEventListener('DOMContentLoaded', function () {
        var countryFilter = document.getElementById('country-filter');
        var agencyFilter = document.getElementById('agency-filter');
        // var levelFilter = document.getElementById('level-filter');
        var managerFilter = document.getElementById('manager-filter');

        var agenciesByCountry = <?php echo json_encode($agencies); ?>;

        function getParameterByName(name) {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get(name) || 'all';
        }


        function updateAgencyFilter() {
            var selectedCountry = countryFilter.value;
            if (selectedCountry === 'all') {
                // Désactiver et vider le filtre Agences
                agencyFilter.disabled = true;
                agencyFilter.innerHTML = '<option value="all" selected>Toutes les agences</option>';
            } else if (profile === 'Manager') {
                // Désactiver et vider le filtre Agences
                agencyFilter.disabled = true;
            } else {
                // Activer le filtre Agences et charger les agences correspondantes
                agencyFilter.disabled = false;
                var agenciesForCountry = agenciesByCountry[selectedCountry] || [];
                var options = '<option value="all"' + (agencyFilter.value === 'all' ? ' selected' : '') + '>Toutes les agences</option>';
                agenciesForCountry.forEach(function (agency) {
                    var selected = agencyFilter.value === agency ? ' selected' : '';
                    options += '<option value="' + agency + '"' + selected + '>' + agency + '</option>';
                });
                agencyFilter.innerHTML = options;
            }
        }

        function updateManagerFilter() {
            var selectedCountry = countryFilter.value;
            var selectedAgency = agencyFilter.value;
            var selectedManagerId = getParameterByName('manager') || 'all';

            console.log("Dans updateManagerFilter");
            console.log("Pays sélectionné :", selectedCountry);
            console.log("Agence sélectionnée :", selectedAgency);

            var params = new URLSearchParams();
            params.append('country', selectedCountry);
            params.append('agency', selectedAgency);

            console.log('URL de la requête fetch :', 'getManagers.php?' + params.toString());

            // Requête AJAX pour récupérer les managers
            fetch('getManagers.php?' + params.toString())
                .then(response => {
                    console.log('Réponse brute de fetch :', response);
                    return response.json();
                })
                .then(data => {
                    console.log('Données reçues :', data);
                    // Vérifier si le manager sélectionné est dans la nouvelle liste
                    var managerExists = data.some(manager => manager.id === selectedManagerId);
                    if (!managerExists) {
                        selectedManagerId = 'all';
                    }
                    // Mettre à jour le sélecteur des managers
                    var options = '<option value="all"' + (selectedManagerId === 'all' ? ' selected' : '') + '>Tous les managers</option>';
                    data.forEach(function (manager) {
                        var selected = (manager.id === selectedManagerId) ? ' selected' : '';
                        options += '<option value="' + manager.id + '"' + selected + '>' + manager.name + '</option>';
                    });
                    managerFilter.innerHTML = options;
                })
                .catch(error => {
                    console.error('Erreur lors du chargement des managers :', error);
                });
        }

        // Initialiser les filtres au chargement
        var selectedCountry = getParameterByName('country') || 'all';
        var selectedLevel = getParameterByName('level') || 'all';

        countryFilter.value = selectedCountry;
        // levelFilter.value = selectedLevel;

        updateAgencyFilter();
        updateManagerFilter();


        // Écouter les changements sur le filtre Pays
        countryFilter.addEventListener('change', function () {
            // Mettre à jour le filtre Agences
            updateAgencyFilter();
            updateManagerFilter();
            // Appliquer les filtres
            applyFilters();

        });

        // Écouter les changements sur le filtre Agences
        agencyFilter.addEventListener('change', function () {
            updateManagerFilter();
            applyFilters();

        });

        // Appliquer les filtres lorsque le niveau ou le manager change
        // levelFilter.addEventListener('change', applyFilters);
        managerFilter.addEventListener('change', applyFilters);


        function applyFilters() {
            var country = countryFilter.value;
            var agency = agencyFilter.value;
            // var level = levelFilter.value;
            var manager = managerFilter.value;

            var params = new URLSearchParams();

            params.append('country', country);
            params.append('agency', agency);
            // params.append('level', level);
            params.append('manager', manager);

            // Recharger la page avec les nouveaux paramètres
            window.location.search = params.toString();
        }
    });


    // // Ajouter des écouteurs d'événements sur les filtres
    // document.getElementById('country-filter').addEventListener('change', applyFilters);
    // document.getElementById('agency-filter').addEventListener('change', applyFilters);
    // document.getElementById('level-filter').addEventListener('change', applyFilters);
    // document.getElementById('manager-filter').addEventListener('change', applyFilters);


</script>
<?php include_once "partials/footer.php"; ?>
<?php } ?>