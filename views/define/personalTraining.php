<?php
require_once __DIR__ . '/../../core/medacap_config.php';
    session_start();
    include_once "../language.php";

    if (!isset($_SESSION["id"])) {
        header("Location: ../../");
        exit();
    } else {
        require_once "../../vendor/autoload.php";
        
        // Create connection
        $conn = new MongoDB\Client(medacapMongoUri());
        
        // Connecting in database
        $academy = $conn->academy;
        
        // Connecting in collections
        $users = $academy->users;
        $trainings = $academy->trainings;
        $applications = $academy->applications;
        $allocations = $academy->allocations;

        $technicianId = $_SESSION["id"];
        
        $levels = [
            'Junior' => ['Junior'], 
            'Senior' => ['Junior', 'Senior'], 
            'Expert' => ['Junior', 'Senior', 'Expert']
        ];

        // Récupérer les valeurs des filtres depuis les paramètres GET (maintenant multiples)
        $selectedBrandParam = $_GET['brand'] ?? 'all';
        $selectedLevelParam = $_GET['level'] ?? 'all';

        $selectedBrands = ($selectedBrandParam === 'all' || empty($selectedBrandParam)) ? ['all'] : explode(',', $selectedBrandParam);
        $selectedLevels = ($selectedLevelParam === 'all' || empty($selectedLevelParam)) ? ['all'] : explode(',', $selectedLevelParam);

        $userData = $users->findOne([
            '$and' => [
                [
                    "_id" => new mongodb\bson\objectid($technicianId),
                    "active" => true
                ]
            ],
        ]);
        
        $trainingTypes = ['Coaching', 'Présentielle', 'Distancielle', 'E-learning', 'Mentoring'];
        $trainingsData = [];
        
        $query = [
            '$and' => [
                ["users" => new mongodb\bson\objectid($technicianId)],
                ["active" => true]
            ]
        ];

        if (!in_array('all', $selectedBrands)) {
            $query['$and'][] = ["brand" => ['$in' => $selectedBrands]];
        }

        if (!in_array('all', $selectedLevels)) {
            $query['$and'][] = ["level" => ['$in' => $selectedLevels]];
        }

        $trainingDatas = $trainings->find($query)->toArray();
        
        // Initialiser le tableau $trainingsData avant la boucle
        $trainingsData = [
            'Coaching' => [],
            'Présentielle' => [],
            'Distancielle' => [],
            'E-learning' => [],
            'Mentoring' => []
        ];

        foreach ($trainingDatas as $trainingData) {
            $applicationData = $applications->findOne([
                '$and' => [
                    ["user" => new mongodb\bson\objectid($technicianId)],
                    ["training" => new mongodb\bson\objectid($trainingData['_id'])],
                    ["active" => true]
                ]
            ]);
            
            if (isset($applicationData)) {
                // Vérifier le type de formation et l'ajouter au tableau approprié
                foreach ($trainingTypes as $type) {
                    if ($trainingData['type'] == $type) {
                        $trainingsData[$type][] = $trainingData; // Ajoute le trainingData au type correspondant
                    }
                }
            }
        }  

        function getBootstrapClass($level) {
            return $level == 'Senior' ? 'danger' : ($level == 'Junior' ? 'warning' : 'success');
        }
    
        function getImageClass($type) {
            if ($type == 'Coaching' || $type == 'Mentoring') {
                return 'mentoring & coaching.png';
            } elseif ($type == 'Distancielle' || $type == 'E-learning') {
                return 'formation_distantielle.png';
            } elseif ($type == 'Présentielle') {
                return 'formation_presentiel.webp';
            }
        }
    
        function renderTrainingNotNecessaryCard() {
            return '
                <div class="training-card-soon">
                    <div class="d-flex flex-column justify-content-center align-items-center h-100 p-5">
                        <i class="ki-duotone ki-information fs-3x text-white mb-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                        <h3 class="text-white fw-bold fs-4">Aucune formation de ce type sélectionnée</h3>
                        <p class="text-white opacity-75 mb-0">Je suis à jour dans mes compétences pour cette catégorie.</p>
                    </div>
                </div>
            ';
        }
    
        function renderTrainingCard($technicianId, $training) {
            $levelClass = getBootstrapClass($training['level']);
            $imageClass = getImageClass($training['type']);
            return '
                <div class="training-card" data-bs-toggle="modal" data-bs-target="#kt_modal_' . $training['_id'] . '_' . $technicianId . '">
                    <div class="card-img-wrapper">
                        <img src="../../public/images/' . $imageClass . '" alt="Image" class="card-image">
                        <div class="overlay"></div>
                    </div>
                    <div class="card-content">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                           <span class="text-uppercase text-primary fw-bolder fs-7 tracking-wider">' . htmlspecialchars($training['brand']) . '</span>
                           <span class="badge badge-light-' . $levelClass . ' fw-bolder px-3 py-2 rounded-pill">' . htmlspecialchars($training['level']) . '</span>
                        </div>
                        <h2 class="card-title fw-bolder text-dark fs-5 mb-auto limited-lines" title="' . htmlspecialchars($training['label']) . '">' . htmlspecialchars($training['label']) . '</h2>
                        <div class="d-flex align-items-center mt-4 pt-3 border-top border-gray-200">
                            <i class="ki-duotone ki-time fs-2 text-warning me-2"><span class="path1"></span><span class="path2"></span></i>
                            <span class="fw-bold text-gray-700 fs-7">' . htmlspecialchars($training['type']) . '</span>
                        </div>
                    </div>
                </div>
            ';
        }
    
        function renderModal($technicianId, $applications, $training, $data, $training_code, $label_training, $Brand, $Type, $Level, $specialities_studies, $training_location, $training_link, $trainingDate, $voir_calendar) {
            $trainingApply = $applications->findOne([
                '$and' => [
                    [
                        'user' => new MongoDB\BSON\ObjectId($technicianId),
                        'training' => new MongoDB\BSON\ObjectId($training['_id']),
                        'active' => true
                    ]
                ],
            ]);

            $details = [
                $training_code => $training['code'],
                $label_training => $training['label'],
                $Type => $training['type'],
                $Brand => $training['brand'],
                $Level => $training['level'],
                $trainingDate => $trainingApply['period'],
                $training_location => $trainingApply['place'],
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

            return '
                <div class="modal fade" id="kt_modal_' . $training['_id'] . '_' . $technicianId . '" tabindex="-1" aria-hidden="true">
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
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            ';
        }
?>
<?php include "partials/header.php"; ?>
<script>
    const trainingsData = <?php echo json_encode($trainingsData); ?>;

    console.log("trainingsData:", trainingsData);
</script>
<style>
    body {
        background: linear-gradient(rgba(0,0,0,0.2), rgba(0,0,0,0.1)), url('/MEDACAP/public/images/welcome_tech.png') no-repeat center center fixed !important;
        background-size: cover !important;
    }

    #kt_content, .wrapper, .post {
        background: transparent !important;
    }

    .training-card {
        background: rgba(255, 255, 255, 0.4);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.3);
        border-radius: 16px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        margin: 15px;
        width: 320px;
        height: 100%;
        overflow: hidden;
        transition: transform 0.3s cubic-bezier(0.25, 0.8, 0.25, 1), box-shadow 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        cursor: pointer;
        display: flex;
        flex-direction: column;
    }

    .training-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
    }

    .training-card-soon {
        background: rgba(0, 0, 0, 0.2);
        backdrop-filter: blur(10px);
        border: 1px dashed rgba(255,255,255,0.4);
        border-radius: 16px;
        margin: 15px;
        width: 100%;
        max-width: 630px;
        height: 180px;
        display: flex;
        justify-content: center;
        align-items: center;
        text-align: center;
        transition: transform 0.2s;
    }

    .card-img-wrapper {
        position: relative;
        width: 100%;
        height: 180px;
        overflow: hidden;
    }

    .card-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }

    .training-card:hover .card-image {
        transform: scale(1.05);
    }

    .overlay {
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 40%;
        background: linear-gradient(to top, rgba(0,0,0,0.5), transparent);
        pointer-events: none;
    }

    .card-content {
        padding: 20px;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
    }

    /* Hide dropdown content by default */
    .dropdown-content {
        display: none;
        margin-top: 15px;
        transition: opacity 0.3s ease, max-height 0.3s ease;
    }
    
    /* Style the toggle button */
    .dropdown-toggle {
        background: rgba(255, 255, 255, 0.4);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.3);
        color: #1a1a1a;
        font-weight: bold;
        padding: 12px 25px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        border-radius: 8px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }

    .dropdown-toggle:hover {
        background: rgba(255, 255, 255, 0.6);
        transform: translateY(-2px);
    }
    
    .dropdown-toggle i {
        margin-left: 8px;
        font-size: 14px;
        transition: transform 0.3s ease;
    }
    
    .dropdown-toggle::before,
    .dropdown-toggle::after {
        content: none;
    }
    
    .dropdown-toggle.open i {
        transform: rotate(180deg);
    }
    
    .title-and-cards-container {
        margin-bottom: 25px;
    }
    
    .limited-lines {
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
        overflow: hidden;
        text-overflow: ellipsis;
    }
</style>
<!--begin::Title-->
<title><?php echo $list_formation_recommandee ?> | CFAO Mobility Academy</title>
<!--end::Title-->
<!--begin::Content-->
<div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content">
    <!--begin::Toolbar-->
    <div class="toolbar" id="kt_toolbar" style="background: transparent; border: none;">
        <div class="container-fluid d-flex flex-stack flex-wrap flex-sm-nowrap">
            <!--begin::Info-->
            <div class="d-flex flex-column align-items-start justify-content-center flex-wrap me-2 pt-4 w-100">
                <!--begin::Title Card-->
                <div class="card w-100 shadow-sm" style="background: #ffffff; border: 1px solid rgba(0,0,0,0.05); border-radius: 12px;">
                    <div class="card-body p-5 d-flex align-items-center justify-content-center">
                        <h1 class="text-dark fw-bolder m-0 fs-1 text-uppercase">
                            <i class="ki-duotone ki-book-open fs-1 text-primary me-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i> 
                            Mon Plan de Formation
                        </h1>
                    </div>
                </div>
                <!--end::Title Card-->
            </div>
            <!--end::Info-->
        </div>
    </div>
    <!--end::Toolbar-->

    <!--begin::Filtres -->
    <div class="container-fluid my-6">
        <div class="card mx-auto shadow-sm" style="background: rgba(255, 255, 255, 0.4); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.3); border-radius: 12px; max-width: 900px; overflow: hidden;">
            <div class="py-4 px-6" style="background: #ffffff; border-bottom: 1px solid rgba(0,0,0,0.05);">
                <h3 class="card-title text-dark fw-bolder fs-4 text-center w-100 m-0 text-uppercase">NOS FILTRES DE FORMATION</h3>
            </div>
            <div class="card-body p-5">
                <div class="row g-5 justify-content-center">
                    <!-- Filtre Level -->
                    <div class="col-md-5">
                        <label for="level-filter" class="form-label d-flex justify-content-center align-items-center fw-bold text-dark fs-5">
                            <i class="ki-duotone ki-chart-simple fs-2 me-2 text-primary"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i> Mon Niveau
                        </label>
                        <select id="level-filter" name="level[]" class="form-select form-select-solid fw-bolder text-center" data-control="select2" data-placeholder="Tous mes niveaux" data-allow-clear="true" multiple="multiple" style="background: rgba(255,255,255,0.8); border: 1px solid rgba(0,0,0,0.1);">
                            <?php foreach ($levels[$userData['level']] as $levelOption): ?>
                            <option value="<?php echo htmlspecialchars($levelOption); ?>" 
                                    <?php if (in_array($levelOption, $selectedLevels)) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($levelOption); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Filtre Brand -->
                    <div class="col-md-5">
                        <label for="brand-filter" class="form-label d-flex justify-content-center align-items-center fw-bold text-dark fs-5">
                            <i class="ki-duotone ki-setting-2 fs-2 me-2 text-danger"><span class="path1"></span><span class="path2"></span></i> Mes Marques
                        </label>
                        <select id="brand-filter" name="brand[]" class="form-select form-select-solid fw-bolder text-center" data-control="select2" data-placeholder="La totalité de mes marques" data-allow-clear="true" multiple="multiple" style="background: rgba(255,255,255,0.8); border: 1px solid rgba(0,0,0,0.1);">
                            <?php 
                            $userBrands = [];
                            foreach (['Junior', 'Senior', 'Expert'] as $levelKey) {
                                if (isset($userData['brand'.$levelKey]) && is_iterable($userData['brand'.$levelKey])) {
                                    foreach ($userData['brand'.$levelKey] as $brand) {
                                        $userBrands[] = (string)$brand;
                                    }
                                }
                            }
                            // Filtrer le tableau pour éliminer les doublons et les vides
                            $filteredBrands = array_unique(array_filter($userBrands, function($brandUser) {
                                return trim($brandUser) !== ""; // Garder uniquement les valeurs non vides
                            }));
                            foreach ($filteredBrands as $brandOption): ?>
                            <option value="<?php echo htmlspecialchars($brandOption); ?>" 
                                    <?php if (in_array($brandOption, $selectedBrands)) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($brandOption); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--end::Filtres -->
    <!--begin::Post-->
    <div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
        <!--begin::Container-->
        <div class="container-fluid">
            <!--begin::Title Card-->
            <div class="card mx-auto mt-8 mb-6 shadow-sm w-100" style="background: #ffffff; border-radius: 12px; border: 1px solid rgba(0,0,0,0.05);">
                <div class="card-body py-4 px-6 d-flex align-items-center justify-content-center">
                    <h2 class="text-dark fw-bolder m-0 fs-2 text-uppercase text-center">
                        <span class="text-primary me-2"><?php echo sprintf('%02d', count($trainingsData['Présentielle'])); ?></span> Formations Présentielles
                    </h2>
                </div>
            </div>
            <!--end::Title Card-->
            <?php if (count($trainingsData['Présentielle']) == 0) { ?>
                <div class="d-flex justify-content-center">
                    <?php echo renderTrainingNotNecessaryCard(); ?>
                </div>
            <?php } else { ?>
                <!--begin::Layout Builder Notice-->
                <div class="row justify-content-center" style="margin-top: 10px;">
                    <?php  
                        $limit = 4; // Limite d'affichage
                        $count = 0; // Compteur d'éléments affichés
                        foreach ($trainingsData['Présentielle'] as $training) { 
                            if ($count >= $limit) {
                                break; // Sortir de la boucle si la limite est atteinte
                            }
                    ?>
                        <?php echo renderTrainingCard($technicianId, $training); ?>
                        <?php echo renderModal($technicianId, $applications, $training, $data, $training_code, $label_training, $Brand, $Type, $Level, $specialities_studies, $training_location, $training_link, $trainingDate, $voir_calendar); ?>
                    <?php 
                        $count++; // Incrémenter le compteur
                    } 
                    ?>
                </div>
                <!--end::Layout Builder Notice-->
                <?php if (count($trainingsData['Présentielle']) > 4) { ?>
                    <!-- Dropdown Container -->
                    <div class="dropdown-container ms-3">
                        <button class="dropdown-toggle">Voir toutes mes formations présentielles
                            <i class="fas fa-chevron-down"></i></button>
                        <!-- Hidden Content -->
                        <div class="dropdown-content">
                            <!-- Begin::Row -->
                            <div class="row justify-content-center" style="margin-top: 10px;">
                                <?php  
                                    $startIndex = 4; // Index à partir duquel commencer l'affichage
                                    foreach ($trainingsData['Présentielle'] as $index => $training) { 
                                        if ($index < $startIndex) {
                                            continue; // Passer les 4 premiers éléments
                                        }
                                    ?>
                                    <?php echo renderTrainingCard($technicianId, $training); ?>
                                    <?php echo renderModal($technicianId, $applications, $training, $data, $training_code, $label_training, $Brand, $Type, $Level, $specialities_studies, $training_location, $training_link, $trainingDate, $voir_calendar); ?>
                                <?php } ?>
                            </div>
                            <!-- End::Row -->
                        </div>
                    </div>
                <?php } ?>
            <?php } ?>
            <!--begin::Title Card-->
            <div class="card mx-auto mt-12 mb-6 shadow-sm w-100" style="background: #ffffff; border-radius: 12px; border: 1px solid rgba(0,0,0,0.05);">
                <div class="card-body py-4 px-6 d-flex align-items-center justify-content-center">
                    <h2 class="text-dark fw-bolder m-0 fs-2 text-uppercase text-center">
                        <span class="text-success me-2"><?php echo sprintf('%02d', count($trainingsData['E-learning'])); ?></span> Formations E-learning
                    </h2>
                </div>
            </div>
            <!--end::Title Card-->
            <?php if (count($trainingsData['E-learning']) == 0) { ?>
                <div class="d-flex justify-content-center">
                    <?php echo renderTrainingNotNecessaryCard(); ?>
                </div>
            <?php } else { ?>
                <!--begin::Layout Builder Notice-->
                <div class="row justify-content-center" style="margin-top: 10px;">
                    <?php 
                        $limit = 4; // Limite d'affichage
                        $count = 0; // Compteur d'éléments affichés
                        foreach ($trainingsData['E-learning'] as $training) { 
                            if ($count >= $limit) {
                                break; // Sortir de la boucle si la limite est atteinte
                            }
                    ?>
                        <?php echo renderTrainingCard($technicianId, $training); ?>
                        <?php echo renderModal($technicianId, $applications, $training, $data, $training_code, $label_training, $Brand, $Type, $Level, $specialities_studies, $training_location, $training_link, $trainingDate, $voir_calendar); ?>
                    <?php 
                        $count++; // Incrémenter le compteur
                    } 
                    ?>
                </div>
                <!--end::Layout Builder Notice-->
                <?php if (count($trainingsData['E-learning']) > 4) { ?>
                    <!-- Dropdown Container -->
                    <div class="dropdown-container ms-3">
                        <button class="dropdown-toggle">Voir tous mes E-learnings
                            <i class="fas fa-chevron-down"></i></button>
                        <!-- Hidden Content -->
                        <div class="dropdown-content">
                            <!-- Begin::Row -->
                            <div class="row justify-content-center" style="margin-top: 10px;">
                                <?php  
                                    $startIndex = 4; // Index à partir duquel commencer l'affichage
                                    foreach ($trainingsData['E-learning'] as $index => $training) { 
                                        if ($index < $startIndex) {
                                            continue; // Passer les 4 premiers éléments
                                        }
                                    ?>
                                    <?php echo renderTrainingCard($technicianId, $training); ?>
                                    <?php echo renderModal($technicianId, $applications, $training, $data, $training_code, $label_training, $Brand, $Type, $Level, $specialities_studies, $training_location, $training_link, $trainingDate, $voir_calendar); ?>
                                <?php } ?>
                            </div>
                            <!-- End::Row -->
                        </div>
                    </div>
                <?php } ?>
            <?php } ?>
            <!--begin::Title Card-->
            <div class="card mx-auto mt-12 mb-6 shadow-sm w-100" style="background: #ffffff; border-radius: 12px; border: 1px solid rgba(0,0,0,0.05);">
                <div class="card-body py-4 px-6 d-flex align-items-center justify-content-center">
                    <h2 class="text-dark fw-bolder m-0 fs-2 text-uppercase text-center">
                        <span class="text-warning me-2"><?php echo sprintf('%02d', count($trainingsData['Distancielle'])); ?></span> Formations Distancielles
                    </h2>
                </div>
            </div>
            <!--end::Title Card-->
            <?php if (count($trainingsData['Distancielle']) == 0) { ?>
                <div class="d-flex justify-content-center">
                    <?php echo renderTrainingNotNecessaryCard(); ?>
                </div>
            <?php } else { ?>
                <!--begin::Layout Builder Notice-->
                <div class="row justify-content-center" style="margin-top: 10px;">
                    <?php
                        $limit = 4; // Limite d'affichage
                        $count = 0; // Compteur d'éléments affichés
                        foreach ($trainingsData['Distancielle'] as $training) { 
                            if ($count >= $limit) {
                                break; // Sortir de la boucle si la limite est atteinte
                            }
                    ?>
                        <?php echo renderTrainingCard($technicianId, $training); ?>
                        <?php echo renderModal($technicianId, $applications, $training, $data, $training_code, $label_training, $Brand, $Type, $Level, $specialities_studies, $training_location, $training_link, $trainingDate, $voir_calendar); ?>
                    <?php 
                        $count++; // Incrémenter le compteur
                    } 
                    ?>
                </div>
                <!--end::Layout Builder Notice-->
                <?php if (count($trainingsData['Distancielle']) > 4) { ?>
                    <!-- Dropdown Container -->
                    <div class="dropdown-container ms-3">
                        <button class="dropdown-toggle">Voir toutes mes formations distancielles
                            <i class="fas fa-chevron-down"></i></button>
                        <!-- Hidden Content -->
                        <div class="dropdown-content">
                            <!-- Begin::Row -->
                            <div class="row justify-content-center" style="margin-top: 10px;">
                                <?php  
                                    $startIndex = 4; // Index à partir duquel commencer l'affichage
                                    foreach ($trainingsData['Distancielle'] as $index => $training) { 
                                        if ($index < $startIndex) {
                                            continue; // Passer les 4 premiers éléments
                                        }
                                    ?>
                                    <?php echo renderTrainingCard($technicianId, $training); ?>
                                    <?php echo renderModal($technicianId, $applications, $training, $data, $training_code, $label_training, $Brand, $Type, $Level, $specialities_studies, $training_location, $training_link, $trainingDate, $voir_calendar); ?>
                                <?php } ?>
                            </div>
                            <!-- End::Row -->
                        </div>
                    </div>
                <?php } ?>
            <?php } ?>
        </div>
        <!--end::Container-->
    </div>
    <!--end::Post-->
</div>
<!--end::Content-->
<script>
    const trainingsData = <?php echo json_encode($trainingsData); ?>;

    console.log("trainingsData:", trainingsData);
</script>
<script>
    $(document).ready(function() {
        $('.dropdown-toggle').click(function() {
            // Cible le contenu du dropdown associé au bouton cliqué
            var $dropdownContent = $(this).next('.dropdown-content');
            var isVisible = $dropdownContent.is(':visible');

            // Basculer l'affichage du contenu du dropdown
            $dropdownContent.slideToggle();
            
            // Ajouter ou retirer la classe 'open' en fonction de l'état
            $(this).toggleClass('open', !isVisible);
        });
    });
    
    document.addEventListener('DOMContentLoaded', function () {
        var levelFilter = document.getElementById('level-filter');
        var brandFilter = document.getElementById('brand-filter');

        function getParameterByName(name) {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get(name) || 'all';
        }

        // Initialiser les filtres au chargement
        var selectedBrand= getParameterByName('brand') || 'all';
        var selectedLevel = getParameterByName('level') || 'all';

        brandFilter.value = selectedBrand;
        levelFilter.value = selectedLevel;

        // Appliquer les filtres lorsque le niveau ou la marque change (en utilisant jQuery pour Select2)
        $(brandFilter).on('change', function() {
            // On peut ajouter une petite temporisation si on veut éviter trop de rechargements
            applyFilters();
        });
        $(levelFilter).on('change', function() {
            applyFilters();
        });

        function applyFilters() {
            var selectedBrands = $(brandFilter).val() || ['all'];
            var selectedLevels = $(levelFilter).val() || ['all'];

            var params = new URLSearchParams();
            params.append('brand', selectedBrands.join(','));
            params.append('level', selectedLevels.join(','));

            // Recharger la page avec les nouveaux paramètres
            window.location.search = params.toString();
        }
    });
</script>
<?php include "partials/footer.php"; ?>
<?php } ?>