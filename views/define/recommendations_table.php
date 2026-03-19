<?php
require_once "../../vendor/autoload.php";

// Inclure le fichier de traitement
$recommendationData = include "processRecommendations.php";

// Extraire les données pour l'affichage
$technicians = $recommendationData['technicians'];

$scores = $recommendationData['scores'];
$trainings = $recommendationData['trainings'];
$managersList = $recommendationData['managersList'];
$teams = $recommendationData['teams']; 
$missingGroups = $recommendationData['missingGroups'];
$debug = $recommendationData['debug'];
$levelOrder = [
    'Junior' => 1,
    'Senior' => 2,
    'Expert' => 3
];
// Récupérer les valeurs des filtres depuis les paramètres GET
$selectedCountry = $_GET['country'] ?? 'all';
$selectedAgency = $_GET['agency'] ?? 'all';
$selectedLevel = $_GET['level'] ?? 'all';
$selectedManager = $_GET['manager'] ?? 'all';



// Map countries to their respective agencies
$agencies = [
    "Burkina Faso" => ["Ouaga"],
    "Cameroun" => ["Bafoussam","Bertoua", "Douala", "Garoua", "Ngaoundere", "Yaoundé"],
    "Cote d'Ivoire" => ["Vridi - Equip"],
    "Gabon" => ["Libreville"],
    "Mali" => ["Bamako"],
    "RCA" => ["Bangui"],
    "RDC" => ["Kinshasa", "Kolwezi", "Lubumbashi"],
    "Senegal" => ["Dakar"],

];

// List of countries
$countries = [
    "Burkina Faso","Cameroun", "Cote d'Ivoire", "Gabon", "Mali", "RCA", "RDC", "Senegal"
];        

// Map countries to their respective subsidiary name
$subsidiaries = [
    "CFAO MOTORS BURKINA",
    "CAMEROON MOTORS INDUSTRIES",
    "CFAO MOTORS COTE D'IVOIRE",
    "CFAO MOTORS GABON",
    "CFAO MOTORS MALI",
    "CFAO MOTORS CENTRAFRIQUE",
    "CFAO MOTORS RDC",
    "CFAO MOTORS SENEGAL",
   
];

// Fonction utilitaire pour obtenir l'ordre des niveaux
function getLevelOrder($level) {
    $levelOrder = [
        'Junior' => 1,
        'Senior' => 2,
        'Expert' => 3
    ];
    return isset($levelOrder[$level]) ? $levelOrder[$level] : 1;
}


?>!
<script>
    var countries = <?php echo json_encode($countries); ?>;
    var agencies = <?php echo json_encode($agencies); ?>;
</script>
<?php include_once "partials/header.php"; ?>
<!--begin::Title-->
<title><?php echo $train_tech ?> | CFAO Mobility Academy</title>
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">


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
    .table-secondary {
        background-color: #f8f9fa;
        color: #6c757d; /* Optionnel: couleur du texte */
        pointer-events: none; /* Désactiver les interactions */
    }
    .popover {
        max-width: 300px; /* Limite la largeur du popover */
    }

    .popover-header {
        background-color: #ffc107; /* Couleur d'arrière-plan de l'en-tête */
        color: #fff; /* Couleur du texte de l'en-tête */
    }

    .popover-body {
        background-color: #fff; /* Couleur d'arrière-plan du corps */
        color: #333; /* Couleur du texte du corps */
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
                     <!--begin::Filtres -->
                        <div class="container my-4">
                        <div class="row g-4 align-items-center">
                            <!-- Filtre Pays -->
                            <div class="col-md-3">
                            <label for="country-filter" class="form-label d-flex align-items-center">
                                <i class="bi bi-geo-alt-fill me-2 text-primary"></i> Pays
                            </label>
                            <select id="country-filter" name="country" class="form-select">
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
                            <div class="col-md-3">
                            <label for="agency-filter" class="form-label d-flex align-items-center">
                                <i class="bi bi-building me-2 text-warning"></i> Agence
                            </label>
                            <select id="agency-filter" name="agency" class="form-select" 
                                    <?php echo ($selectedCountry === 'all') ? 'disabled' : ''; ?>>
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
                            <div class="col-md-3">
                                <label for="manager-filter" class="form-label d-flex align-items-center">
                                    <i class="bi bi-person-fill me-2 text-info"></i> Manager
                                </label>
                                <select id="manager-filter" name="manager" class="form-select">
                                    <option value="all" <?php if ($selectedManager === 'all') echo 'selected'; ?>>Tous les managers</option>
                                    <?php foreach ($managersList as $managerId => $managerName): ?>
                                        <option value="<?php echo htmlspecialchars($managerId); ?>" <?php if ($selectedManager === $managerId) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($managerName); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <!-- Filtre Niveau -->
                            <div class="col-md-3">
                            <label for="level-filter" class="form-label d-flex align-items-center">
                                <i class="bi bi-bar-chart-fill me-2 text-success"></i> Niveau
                            </label>
                            <select id="level-filter" name="level" class="form-select">
                                <option value="all" <?php if ($selectedLevel === 'all') echo 'selected'; ?>>Tous les niveaux</option>
                                <option value="Junior" <?php if ($selectedLevel === 'Junior') echo 'selected'; ?>>Junior</option>
                                <option value="Senior" <?php if ($selectedLevel === 'Senior') echo 'selected'; ?>>Senior</option>
                                <option value="Expert" <?php if ($selectedLevel === 'Expert') echo 'selected'; ?>>Expert</option>
                            </select>
                            </div>
                        </div>
                        </div>
                        <!--end::Filtres -->


                    <!--begin::Table-->
                    <div id="kt_customers_table_wrapper" class="dataTables_wrapper dt-bootstrap4 no-footer">
                        <div class="table-responsive">
                            <table aria-describedby=""
                                class="table align-middle table-bordered table-row-dashed fs-6 gy-5 dataTable no-footer"
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
                                        <?php foreach (['Junior', 'Senior', 'Expert'] as $level): ?>
                                            <th class="min-w-125px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                                rowspan="1" colspan="2"
                                                aria-label="Customer Name: activate to sort column ascending"
                                                style="width: 125px; text-align: center;">FORMATIONS recommandées POUR LE <?php echo $level ?>
                                            </th>
                                        <?php endforeach; ?>

                                    </tr>
                                </thead>
                                <tbody class="fw-semibold text-gray-600" id="table">
                                    <?php foreach ($managersList as $managerId => $managerName): ?>
                                        <?php
                                            // Récupérer les techniciens rattachés à ce manager
                                            $techniciansForManager = isset($teams[$managerId]) ? $teams[$managerId] : [];
                                        ?>
                                        <?php foreach ($techniciansForManager as $technician): ?>
                                            <?php 
                                                $technicianId = $technician['id']; 
                                                $technicianLevel = $technician['level'];
                                                $technicianLevelOrder = $levelOrder[$technicianLevel] ?? 1; // Par défaut à 1 si niveau inconnu
                                                $technicianMissingGroups = $missingGroups[$technicianId] ?? [];
                                            ?>

                                            <tr>
                                                <!-- Nom et Prénom -->
                                                <td class="text-center">
                                                    <?php echo htmlspecialchars($technician['firstName'] . ' ' . $technician['lastName']); ?>
                                                </td>
                                                <!-- Niveau technique -->
                                                <td class="text-center">
                                                    <span class="badge badge-<?php echo strtolower(htmlspecialchars($technician['level'])); ?>">
                                                        <?php echo htmlspecialchars($technician['level']); ?>
                                                    </span>
                                                </td>
                                                <?php foreach (['Junior', 'Senior', 'Expert'] as $level): ?>
                                                    <?php 
                                                        $currentLevelOrder = getLevelOrder($level);
                                                        $isHigher = $currentLevelOrder > $technicianLevelOrder;
                                                    ?>
                                                    <!-- Cellule de Formation Recommandée -->
                                                    <td class="text-center <?php echo $isHigher ? 'table-secondary' : ''; ?>">
                                                        <?php if (!$isHigher): ?>
                                                            <?php if (!empty($trainings[$technicianId][$level])): ?>
                                                                <ul style="list-style: none; padding: 0; margin: 0;">
                                                                    <?php foreach ($trainings[$technicianId][$level] as $training): ?>
                                                                        <span class="badge badge-training"
                                                                        title="<?php echo htmlspecialchars($training['name']); ?>">
                                                                            <?php echo htmlspecialchars($training['code']); ?>
                                                                        </span>
                                                                    <?php endforeach; ?>
                                                                </ul>
                                                            <?php else: ?>
                                                                <span class="empty-level">Aucune formation recommandée</span>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                        <!-- Ajouter l'Icône du Popover si des groupes manquants existent pour ce niveau -->
                                                        <?php 
                                                            $missingGroupsForLevel = $technicianMissingGroups[$level] ?? [];
                                                            if (!empty($missingGroupsForLevel)):
                                                                // Construire le contenu du popover pour ce niveau
                                                                $popoverContent = '<strong>Besoins retenus dans les spécialités suivantes :</strong><br>';
                                                                foreach ($missingGroupsForLevel as $group) {
                                                                    $popoverContent .= '<strong>' . htmlspecialchars($group['groupName']) . ' :</strong><br> ' 
                                                                        . '<em>Type(s) de formation :</em> ' . htmlspecialchars(implode(', ', $group['trainingTypes'])) . '<br>';
                                                                }
                                                                // Nettoyer le contenu en supprimant les <br> supplémentaires
                                                                $popoverContent = rtrim($popoverContent, '<br><br>');
                                                        ?>
                                                                <i 
                                                                    class="bi bi-info-circle-fill text-warning ms-2" 
                                                                    style="cursor: pointer;" 
                                                                    data-bs-toggle="popover" 
                                                                    data-bs-html="true" 
                                                                    data-bs-trigger="hover" 
                                                                    data-bs-content="<?php echo htmlspecialchars($popoverContent, ENT_QUOTES, 'UTF-8'); ?>"
                                                                    title="Formations À Venir">
                                                                </i>
                                                        <?php endif; ?>
                                                    </td>
                                                    <!-- Nouvelle colonne : nombre de formations -->
                                                    <td class="text-center <?php echo $isHigher ? 'table-secondary' : ''; ?>">
                                                        <?php if (!$isHigher): ?>
                                                            <?php echo !empty($trainings[$technicianId][$level]) 
                                                                ? count($trainings[$technicianId][$level]) 
                                                                : 0; ?>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
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

</script>
<script>
    const recommendationData = <?php echo json_encode($recommendationData); ?>;

    console.log("Technicians:", recommendationData.technicians);
    console.log("Scores:", recommendationData.scores);
    console.log("Trainings:", recommendationData.trainings);
    console.log("Missing Groups:", recommendationData.missingGroups);
    console.log("Managers List:", recommendationData.managersList);
    console.log("Teams:", recommendationData.teams);
    console.log("Debug Logs:", recommendationData.debug);


</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
        var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl, {
                placement: 'right', 
                trigger: 'hover'
            })
        })
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    var selectedManager = "<?php echo htmlspecialchars($selectedManager); ?>";
    console.log("Selected Manager avant récupération AJAX:", selectedManager);
    document.addEventListener('DOMContentLoaded', function () {
        var countryFilter = document.getElementById('country-filter');
        var agencyFilter = document.getElementById('agency-filter');
        var managerFilter = document.getElementById('manager-filter');
        var levelFilter = document.getElementById('level-filter');

        var agenciesByCountry = <?php echo json_encode($agencies); ?>;

        function updateAgencyFilter() {
            var selectedCountry = countryFilter.value;
            if (selectedCountry === 'all') {
                // Désactiver et vider le filtre Agences
                agencyFilter.disabled = true;
                agencyFilter.innerHTML = '<option value="all" selected>Toutes les agences</option>';
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
            updateManagerFilter();
        }

        function updateManagerFilter() {
            var selectedCountry = countryFilter.value;
            var selectedAgency = agencyFilter.value;
            console.log("Dans UpdateManagerFilter");
            // Faire une requête AJAX pour récupérer les managers filtrés
            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'getManagers.php?country=' + encodeURIComponent(selectedCountry) + '&agency=' + encodeURIComponent(selectedAgency), true);
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    var managers = JSON.parse(xhr.responseText);
                    // Vider le filtre Manager et ajouter une option "Tous les managers"
                    managerFilter.innerHTML = '<option value="all" selected>Tous les managers</option>';
                    // Ajouter les managers récupérés
                    managers.forEach(function (manager) {
                        var option = document.createElement('option');
                        option.value = manager.id;
                        option.textContent = manager.name;
                        if (manager.id === '<?php echo htmlspecialchars($selectedManager); ?>') {
                            option.selected = true;
                        }
                        managerFilter.appendChild(option);
                    });
                }
            };
            xhr.send();
        }

        // Appeler la fonction au chargement pour initialiser le filtre Agences
        updateAgencyFilter();

    

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
            // Mettre à jour le filtre Managers lorsque l'agence change
            updateManagerFilter();
            // Appliquer les filtres
            applyFilters();
        });

        // Écouter les changements sur le filtre Manager
        managerFilter.addEventListener('change', applyFilters);

        // Écouter les changements sur le filtre Niveau
        levelFilter.addEventListener('change', applyFilters);

        function applyFilters() {
            var country = countryFilter.value;
            var agency = agencyFilter.value;
            var manager = managerFilter.value;
            var level = levelFilter.value;

            var params = new URLSearchParams();

            params.append('country', country);
            params.append('level', level);

            if (agencyFilter.disabled === false) {
                params.append('agency', agency);
            }

            if (manager !== 'all') {
                params.append('manager', manager);
            }

            // Recharger la page avec les nouveaux paramètres
            window.location.search = params.toString();
        }
    });


function applyFilters() {
    var country = document.getElementById('country-filter').value;
    var agency = document.getElementById('agency-filter').value;
    var manager = document.getElementById('manager-filter').value;
    var level = document.getElementById('level-filter').value;

    var params = new URLSearchParams();
    if (country !== 'all') params.append('country', country);
    if (agency !== 'all') params.append('agency', agency);
    if (manager !== 'all') params.append('manager', manager);
    if (level !== 'all') params.append('level', level);

    window.location.search = params.toString();
}

    // Ajouter des écouteurs d'événements sur les filtres
    document.getElementById('country-filter').addEventListener('change', applyFilters);
    document.getElementById('agency-filter').addEventListener('change', applyFilters);
    document.getElementById('manager-filter').addEventListener('change', applyFilters);
    document.getElementById('level-filter').addEventListener('change', applyFilters);


</script>


<?php include_once "partials/footer.php"; ?>