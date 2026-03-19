<?php
session_start();

// Vérifiez si l'utilisateur est connecté
if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
} else {
    require_once "../../vendor/autoload.php";
    // Inclure le fichier de traitement
    $recommendationData = include "processRecommendations.php";

    // Create connection
    $conn = new MongoDB\Client("mongodb://localhost:27017");

    // Connecting in database
    $academy = $conn->academy;

    // Connecting in collections
    $allocations = $academy->allocations;

    // Extraire les données pour l'affichage
    $technicians = $recommendationData['technicians'];
    $scores = $recommendationData['scores'];
    $trainings = $recommendationData['trainings'];
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
    $selectedManagerId = $_GET['manager'] ?? 'all';

    // Récupérer les techniciens
    $profileSession = $_SESSION['profile'];

    if (isset($_POST['submit'])) {
        $technicianID = $_POST['technicianID'];
        $trainingID = $_POST['trainingID'];

        $allocations->updateOne(
            [
                "user" => new MongoDB\BSON\ObjectId($technicianID),
                "training" => new MongoDB\BSON\ObjectId($trainingID)
            ],
            [
                '$set' => ['active' => true]
            ]
        );
        
    }

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

    function renderTrainingCard($userId, $training, $allocations) {
        
        $trainingAllocation = $allocations->findOne([
            '$and' => [
                [
                    'user' => new MongoDB\BSON\ObjectId($userId),
                    'training' => new MongoDB\BSON\ObjectId($training['_id'])
                ]
            ],
        ]);

        if ($trainingAllocation['active'] == true ) {
            return '
                <span class="badgee badge-training" style="background-color: #50cd89;" data-bs-toggle="modal" data-bs-target="#kt_modal_' . $training['_id'] . '_' . $userId . '"
                title="' . htmlspecialchars($training['name']) . '">
                    ' . htmlspecialchars($training['code']) . '
                </span>
            ';
        } else {
            return '
                <span class="badgee badge-training" style="background-color: #6c757d;" data-bs-toggle="modal" data-bs-target="#kt_modal_' . $training['_id'] . '_' . $userId . '"
                title="' . htmlspecialchars($training['name']) . '">
                    ' . htmlspecialchars($training['code']) . '
                </span>
            ';
        }
    }
        
    function renderModal($userId, $allocations, $training, $data, $training_code, $label_training, $Brand, $Type, $Level, $specialities_studies, $training_location, $training_link, $trainingDate, $voir_calendar) {
        $profile = $_SESSION["profile"];
        
        $trainingAllocation = $allocations->findOne([
            '$and' => [
                [
                    'user' => new MongoDB\BSON\ObjectId($userId),
                    'training' => new MongoDB\BSON\ObjectId($training['_id'])
                ]
            ],
        ]);
        
        $details = [
            $training_code => $training['code'],
            $label_training => $training['name'],
            $Type => $training['type'],
            $Brand => $training['brand'],
            $Level => $training['level']
        ];

        $specialities = [];
        foreach ($training['specialities'] as $speciality) {
            $specialities[] = $speciality;
        }
        $specialities = implode(', ', $specialities);
        
        if ($training['type'] == 'Distancielle' || $training['type'] == 'E-learning') {
            $details[$training_link] = $training['link'];
        }

        $detailsHtml = '';
        foreach ($details as $label => $value) {
            $detailsHtml .= '
                <div class="d-flex flex-stack py-4 border-bottom border-gray-300 border-bottom-dashed">
                    <div class="d-flex align-items-center">
                        <label class="fs-4 fw-bolder mb-2">' . htmlspecialchars($label) . ' :</label>
                        <div class="ms-5 fs-5 fw-bold text-gray-900 mb-2">' . htmlspecialchars($value) . '</div>
                    </div>
                </div>
            ';
        }
                
        $applyHtml = '';
        if ($profile == 'Admin' || $profile == 'Directeur Filiale') {
            if ($trainingAllocation['active'] == false ) {
                $applyHtml .= '
                    <a href="./registerTechnician?user=' . $userId . '&training=' . $training['_id'] . '" type="button"
                        class="btn btn-primary text-white fw-bolder">
                            Inscrire le technicien à cette formation
                    </a>
                ';
            }
        }


        return '
            <div class="modal fade" id="kt_modal_' . $training['_id'] . '_' . $userId . '" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog mw-650px">
                    <div class="modal-content">
                        <div class="modal-header pb-0 border-0 justify-content-end">
                            <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                                <i class="ki-duotone ki-cross fs-1"></i>
                            </div>
                            <div class="btn btn-icon btn-sm btn-active-icon-primary"
                                data-kt-users-modal-action="close" data-bs-dismiss="modal"
                                data-kt-menu-dismiss="true">
                                <span class="svg-icon svg-icon-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24"
                                        height="24" viewBox="0 0 24 24" fill="none">
                                        <rect opacity="0.5" x="6" y="17.3137" width="16"
                                            height="2" rx="1"
                                            transform="rotate(-45 6 17.3137)"
                                            fill="black" />
                                        <rect x="7.41422" y="6" width="16" height="2" rx="1"
                                            transform="rotate(45 7.41422 6)" fill="black" />
                                    </svg>
                                </span>
                            </div>
                        </div>
                        <div class="modal-body scroll-y mx-5 mx-xl-18 pt-0 pb-15">
                            <div class="text-center mb-13">
                                <h1 class="mb-3">' . htmlspecialchars($data) . '</h1>
                            </div>
                            <div class="mb-10 ">
                                <div class="mh-300px scroll-y me-n7 pe-7">
                                    ' . $detailsHtml . '
                                    <div class="d-flex flex-stack py-4 border-bottom border-gray-300 border-bottom-dashed">
                                        <div class="d-flex align-items-center">
                                            <label class="fs-4 fw-bolder mb-2">' . $specialities_studies . ' :</label>
                                            <div class="ms-5 fs-5 fw-bold text-gray-900 mb-2">' . htmlspecialchars($specialities) . '</div>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-stack py-4 border-bottom border-gray-300 border-bottom-dashed">
                                        <div class="d-flex align-items-center">
                                            <label class="fs-4 fw-bolder mb-2">' . $trainingDate . ' :</label>
                                            <a href="./planning"
                                                class="btn btn-light btn-active-light-primary text-primary fw-bolder btn-sm"
                                                title="Cliquez ici pour voir le calendrier des formations">
                                                ' . htmlspecialchars($voir_calendar) . '
                                            </a>
                                        </div>
                                    </div>
                                </div><br>
                                '.$applyHtml.'
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        ';
        
        return '
            <div class="modal fade" id="applyModal' . $training['_id'] . '" tabindex="-2" aria-hidden="true">
                <div class="modal-dialog mw-650px">
                    <div class="modal-content">
                        <div class="modal-header pb-0 border-0 justify-content-end">
                            <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                                <i class="ki-duotone ki-cross fs-1"></i>
                            </div>
                        </div>
                        <div class="modal-body scroll-y mx-5 mx-xl-18 pt-0 pb-15">
                            <div class="text-center mb-13">
                                <h1 class="mb-3">' . htmlspecialchars($data) . '</h1>
                            </div>
                            <div class="mb-10 ">
                                <div class="mh-300px scroll-y me-n7 pe-7">
                                    ' . $detailsHtml . '
                                    <div class="d-flex flex-stack py-4 border-bottom border-gray-300 border-bottom-dashed">
                                        <div class="d-flex align-items-center">
                                            <label class="fs-4 fw-bolder mb-2">' . $specialities_studies . ' :</label>
                                            <div class="ms-5 fs-5 fw-bold text-gray-900 mb-2">' . htmlspecialchars($specialities) . '</div>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-stack py-4 border-bottom border-gray-300 border-bottom-dashed">
                                        <div class="d-flex align-items-center">
                                            <label class="fs-4 fw-bolder mb-2">' . $trainingDate . ' :</label>
                                            <a href="./planning"
                                                class="btn btn-light btn-active-light-primary text-primary fw-bolder btn-sm"
                                                title="Cliquez ici pour voir le calendrier des formations">
                                                ' . htmlspecialchars($voir_calendar) . '
                                            </a>
                                        </div>
                                    </div>
                                </div><br>
                                '.$applyHtml.'
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        ';
    }
    ?>
    <?php include_once "partials/header.php"; ?>
    <script>
        var countries = <?php echo json_encode($countries); ?>;
        var agencies = <?php echo json_encode($agencies); ?>;
    </script>
    <!--begin::Title-->
    <?php if ($_SESSION['profile'] == 'Manager') { ?>
        <title><?php echo $train_collab ?> | CFAO Mobility Academy</title>
    <?php } else { ?>
        <title><?php echo $train_tech ?> | CFAO Mobility Academy</title>
    <?php } ?> 


    <!--end::Title-->
    <style>
        :root {
            /* Couleurs de l'entreprise */
            --primary-black: #1a1a1a;
            --primary-red: #dc2626;
            --primary-navy: #1e3a8a;
            --secondary-navy: #3b82f6;
            --light-gray: #f8fafc;
            --medium-gray: #64748b;
            --dark-gray: #334155;
            --white: #ffffff;
            --border-color: #e2e8f0;
            --shadow-light: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-medium: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-large: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --border-radius: 12px;
            --border-radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Effet de glassmorphisme pour les cartes */
        .glass-effect {
            background: rgba(255, 255, 255, 0.7) !important;
            backdrop-filter: blur(10px) !important;
            -webkit-backdrop-filter: blur(10px) !important;
            border: 1px solid rgba(255, 255, 255, 0.18) !important;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15) !important;
            border-radius: 15px !important;
            transition: all 0.3s ease !important;
        }
        
        /* Effet hover sur les titres */
        .glass-effect:hover {
            background: rgba(255, 255, 255, 0.8) !important;
            box-shadow: 0 10px 40px 0 rgba(31, 38, 135, 0.2) !important;
            transform: translateY(-3px) !important;
        }
        
        /* Style pastel pour les en-têtes de filtre et formations */
        .filter-header, .training-header {
            background: linear-gradient(135deg,rgb(248, 250, 251) 0%,rgb(250, 251, 251) 100%) !important;
            color: #333 !important;
            text-align: center !important;
            padding: 1rem !important;
        }
        
        .filter-header h5, .training-header h5 {
            color: #333 !important;
            font-weight: 700 !important;
            display: inline-block !important;
            margin: 0 auto !important;
        }
        
        /* Effet de profondeur pour les cartes */
        .depth-effect {
            transform: translateZ(0);
            box-shadow:
                0 4px 6px -1px rgba(0, 0, 0, 0.1),
                0 2px 4px -1px rgba(0, 0, 0, 0.06),
                0 12px 20px -2px rgba(0, 0, 0, 0.05) !important;
            transition: all 0.3s ease-in-out !important;
        }
        
        .depth-effect:hover {
            transform: translateY(-5px) translateZ(0);
            box-shadow:
                0 10px 15px -3px rgba(0, 0, 0, 0.1),
                0 4px 6px -2px rgba(0, 0, 0, 0.05),
                0 20px 25px -5px rgba(0, 0, 0, 0.03) !important;
        }

        /* Cards principales */
        .card {
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: var(--border-radius);
            box-shadow:
                0 4px 6px -1px rgba(0, 0, 0, 0.1),
                0 2px 4px -1px rgba(0, 0, 0, 0.06),
                0 0 0 1px rgba(255, 255, 255, 0.3) inset;
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            overflow: hidden;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        
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
        .badgee {
            display: inline-block;
            padding: 0.3em 0.6em;
            font-size: 0.9em;
            font-weight: bold;
            color: #fff;
            /* background-color: #6c757d; */
            border-radius: 5px;
            margin: 3px 5px; /* Ajout de marges pour espacement */
            text-transform: uppercase;
            cursor: pointer; /* Apparence interactive */
        }

        .badgee:hover {
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
        style="position: relative; border-radius: 25px; overflow: hidden;"
        data-select2-id="select2-data-kt_content">
        
        <!-- Background overlay with blur effect -->
        <div style="
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            filter: blur(10px);
            transform: scale(1.05);
            background-image: linear-gradient(rgba(0, 0, 0, 0.2), rgba(0, 0, 0, 0.1)), url('../../public/images/welcome_tech.png');
            background-size: cover;
            background-position: center;
            border-radius: 25px;
            z-index: 0;">
        </div>
        
        <!-- Content container -->
        <div style="position: relative; z-index: 1; border-radius: 25px; overflow: hidden;">
        
        <!-- Titre principal dans une carte glassmorphisme -->
        <div class="container-xxl pt-5">
            <div class="card mb-5" style="background-color: #FFFFFF !important; border-radius: 25px !important; box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15); border: 1px solid rgba(255, 255, 255, 0.18);">
                <div class="card-body text-center py-4">
                    <h1 class="card-title mb-0"><i class="ki-duotone ki-document fs-2 me-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i><?php if ($_SESSION['profile'] == 'Manager') { echo $train_collab; } else { echo $train_tech; } ?></h1>
                </div>
            </div>
        </div>

        <!--begin::Toolbar-->
        <div class="toolbar" id="kt_toolbar">
            <div class="container-fluid d-flex flex-stack flex-wrap flex-sm-nowrap">
                <!--begin::Info-->
                <div class="d-flex flex-column align-items-start justify-content-center flex-wrap me-2">
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
            <div class="container-xxl" data-select2-id="select2-data-194-27hh">
                <div class="row d-flex align-items-stretch">
                    <!-- Left column for table - 9 columns -->
                    <div class="col-md-9 d-flex flex-column">
                        <!--begin::Card-->
                        <div class="card glass-effect">
                            <!--begin::Card body-->
                            <div class="card-body pt-0">
                                <!--begin::Table-->
                                <div id="kt_customers_table_wrapper" class="dataTables_wrapper dt-bootstrap4 no-footer">
                                    <div class="table-responsive depth-effect">
                                        <table aria-describedby=""
                                            class="table align-middle table-bordered fs-6 gy-5 dataTable no-footer"
                                            id="kt_customers_table">
                                            <thead>
                                                <tr class="text-start text-black fw-bold fs-7 text-uppercase gs-0">
                                                    <th class="min-w-150px sorting text-center " tabindex="0" aria-controls="kt_customers_table"
                                                        rowspan="1" colspan="1"
                                                        aria-label="Payment Method: activate to sort column ascending"
                                                        style="width: 150px; text-align: center;"><?php echo $prenomsNomsTechs ?>
                                                    </th>
                                                    <?php foreach (['Junior', 'Senior', 'Expert'] as $level): ?>
                                                        <th class="min-w-125px sorting text-center" tabindex="0" aria-controls="kt_customers_table"
                                                            rowspan="1" colspan="1"
                                                            aria-label="Customer Name: activate to sort column ascending"
                                                            style="width: 125px; text-align: center;"><?php echo $training_done_by_tech.' '.$level ?>
                                                        </th>
                                                    <?php endforeach; ?>
                                                </tr>
                                            </thead>
                                            <tbody class="fw-semibold text-gray-600" id="table">
                                                <?php foreach ($filteredTechnicians as $technician): ?>
                                                    <?php 
                                                        $technicianId = (string)$technician['_id']; 
                                                        $technicianLevel = $technician['level'];
                                                        $technicianLevelOrder = $levelOrder[$technicianLevel] ?? 1; // Par défaut à 1 si niveau inconnu
                                                        $technicianMissingGroups = $missingGroups[$technicianId] ?? [];
                                                    ?>
                                                    <tr>
                                                        <!-- Nom et Prénom -->
                                                        <td class="text-center">
                                                            <?php echo htmlspecialchars($technician['firstName'] . ' ' . $technician['lastName']); ?>    
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
                                                                                <?php echo renderTrainingCard($technicianId, $training, $allocations); ?>
                                                                                <?php echo renderModal($technicianId, $allocations, $training, $data, $training_code, $label_training, $Brand, $Type, $Level, $specialities_studies, $training_location, $training_link, $trainingDate, $voir_calendar); ?>
                                                                            <?php endforeach; ?>
                                                                        </ul>
                                                                    <?php else: ?>
                                                                    <span class="empty-level"><?php echo $no_training_recommandation; ?></span>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                            <!-- Ajouter l'Icône du Popover si des groupes manquants existent pour ce niveau -->
                                                                <?php 
                                                                    $missingGroupsForLevel = $missingGroups[$technicianId][$level] ?? [];
                                                                    if (!empty($missingGroupsForLevel)):
                                                                        // Construire le contenu du popover pour ce niveau
                                                                        $popoverContent = '<strong>Besoins retenus dans les spécialités suivantes :</strong><br>';
                                                                        foreach ($missingGroupsForLevel as $group) {
                                                                            $popoverContent .= '<strong>' . htmlspecialchars($group['groupName']) . ' :</strong><br> ' 
                                                                                . '<em>Type(s)   de formation :</em> ' . htmlspecialchars(implode(', ', $group['trainingTypes'])) . '<br>';
                                                                        }
                                                                        // Nettoyer le contenu en supprimant les <br> supplémentaires
                                                                        $popoverContent = rtrim($popoverContent, '<br><br>');
                                                                ?>
                                                                        <!-- Commented out info icons per client request
                                                                        <i
                                                                            class="bi bi-info-circle-fill text-warning ms-2"
                                                                            style="cursor: pointer;"
                                                                            data-bs-toggle="popover"
                                                                            data-bs-html="true"
                                                                            data-bs-trigger="hover"
                                                                            data-bs-content="<?php echo htmlspecialchars($popoverContent, ENT_QUOTES, 'UTF-8'); ?>"
                                                                            title="Formations En Production">
                                                                        </i>
                                                                        -->
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
                    </div>
                    
                    <!-- Filters Panel - same height as table -->
                    <div class="col-md-3 d-flex flex-column">
                        <div class="card glass-effect depth-effect h-100 d-flex flex-column">
                            <div class="card-header-bg filter-header text-start">
                                <i class="ki-duotone ki-filter fs-6 me-1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <h5 class="card-title mb-0 d-inline">FILTRE DES DONNÉES</h5>
                            </div>
                            <div class="card-body d-flex flex-column flex-grow-1">
                                <div id="dynamicFilters">
                                    <!-- Filtre Marques -->
                                    <div class="mb-4">
                                        <label for="brand-filter" class="form-label d-flex align-items-center">
                                            <i class="bi bi-car-front-fill fs-2 me-2 text-danger"></i> Marques
                                        </label>
                                        <select id="brand-filter" class="form-select" onchange="applyFilters()">
                                            <option value="all">Toutes les marques</option>
                                            <?php if(isset($teamBrands)): ?>
                                                <?php foreach ($teamBrands as $b): ?>
                                                <option value="<?php echo htmlspecialchars($b); ?>">
                                                    <?php echo htmlspecialchars($b); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>

                                    <!-- Filtre Techniciens -->
                                    <div class="mb-4">
                                        <label for="tech-filter" class="form-label d-flex align-items-center">
                                            <i class="bi bi-person-fill fs-2 me-2 text-info"></i> Techniciens
                                        </label>
                                        <select id="tech-filter" class="form-select" onchange="applyFilters()">
                                            <option value="all">Tous les techniciens</option>
                                            <?php if(isset($technicians)): ?>
                                                <?php foreach ($technicians as $t): ?>
                                                <option value="<?php echo htmlspecialchars($t['_id']); ?>">
                                                    <?php echo htmlspecialchars($t['firstName'] .' '. $t['lastName']); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
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
                name: `Mes Formations.xlsx`
            })
        });
    });

    </script>
    <script>
        const recommendationData = <?php echo json_encode($recommendationData); ?>;

        console.log("Technicians:", recommendationData.technicians);
        console.log("Scores:", recommendationData.scores);
        console.log("Trainings:", recommendationData.trainings);
        console.log("Debug Logs:", recommendationData.debug);
        console.log("Missing groups:", recommendationData.missingGroups);
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
        document.addEventListener('DOMContentLoaded', function () {
            // Simple search functionality
            document.getElementById('search').addEventListener('keyup', function() {
                let searchTerm = this.value.toLowerCase();
                let rows = document.querySelectorAll('#table tr');
                
                rows.forEach(row => {
                    let text = row.textContent.toLowerCase();
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                });
            });
        });

        // Function to apply filters and reload the page with new parameters
        function applyFilters() {
            const techFilter = document.getElementById('tech-filter').value;
            const brandFilter = document.getElementById('brand-filter').value;
            
            // Show loading indicator if needed
            document.getElementById('loading-overlay').style.display = 'flex';
            
            // Build query string
            let query = `?user=${encodeURIComponent(techFilter)}&brand=${encodeURIComponent(brandFilter)}`;
            window.location.href = query;
        }
    </script>
    <?php include_once "partials/footer.php"; ?>
<?php } ?>