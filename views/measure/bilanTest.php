<?php
require_once __DIR__ . '/../../core/medacap_config.php';
session_start();

include_once "../partials/background-manager.php"; // Système de gestion de fond d'écran

// Check if user is logged in
if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
} else {
    // Manager : arrivée directe sur les notes ; l'avancement reste accessible via ?view=progress
    if ($_SESSION['profile'] == "Manager" && !isset($_GET['view'])) {
        header("Location: ./bilanTestNotes");
        exit();
    }
    require_once "../../vendor/autoload.php";

    // Create MongoDB connection and collections
    $conn = new MongoDB\Client(medacapMongoUri());
    $academy = $conn->academy;
    $users = $academy->users;
    $results = $academy->results;
    $exams = $academy->exams;
    $tests = $academy->tests;
    $allocations = $academy->allocations;
    
        if (isset($_GET['lang']) && ($_GET['lang'] === 'EN' || $_GET['lang'] === 'FR')) {
        $_SESSION['lang'] = $_GET['lang'];
    }
    $lang = $_SESSION['lang'] ?? 'FR';
    if ($lang === 'EN') {
        include_once "../language_EN.php";
    } else {
        include_once "../language.php";
    }

    // Initialize technicians array
    $tech = [];

    // Get technicians based on user profile
    if ($_SESSION['profile'] == "Admin" || $_SESSION["profile"] == "Ressource Humaine" || 
        $_SESSION['profile'] == "Directeur Pièce et Service" || $_SESSION['profile'] == "Directeur des Opérations") {
        
        $query = [
            "subsidiary" => $_SESSION['subsidiary'],
            "active" => true,
        ];
        
        if ($_SESSION["department"] != 'Equipment & Motors') {
            $query['department'] = $_SESSION["department"];
        }
        
        $technicians = $users->find($query)->toArray();
        foreach ($technicians as $techn) {
            if ($techn["profile"] == "Technicien" || 
                ($techn["profile"] == "Manager" && $techn["test"] == true)) {
                array_push($tech, new MongoDB\BSON\ObjectId($techn['_id']));
            }
        }
    } 
    elseif ($_SESSION['profile'] == "Manager") {
        // Manager can only see their direct reports
        $query = [
            "manager" => new MongoDB\BSON\ObjectId($_SESSION['id']),
            "active" => true,
        ];
        
        $technicians = $users->find($query)->toArray();
        foreach ($technicians as $techn) {
            if ($techn["profile"] == "Technicien" || 
                ($techn["profile"] == "Manager" && $techn["test"] == true)) {
                array_push($tech, new MongoDB\BSON\ObjectId($techn['_id']));
            }
        }
    }
    elseif ($_SESSION['profile'] == "Super Admin" || $_SESSION['profile'] == "Directeur Groupe") {
        // Super Admin with country filter
        if (isset($_GET['country'])) {
            $selectedCountry = $_GET['country'];

            $technicians = $users->find([
                '$and' => [
                    [
                        "country" => $selectedCountry,
                        "active" => true,
                    ],
                ],
            ])->toArray();
        } 
        // Super Admin without country filter
        else {
            $technicians = $users->find([
                '$and' => [
                    [
                        "active" => true,
                    ],
                ],
            ])->toArray();
        }
        
        // Process technicians for Super Admin
        foreach ($technicians as $techn) {
            if ($techn["profile"] == "Technicien" || 
                ($techn["profile"] == "Manager" && $techn["test"] == true)) {
                array_push($tech, new MongoDB\BSON\ObjectId($techn['_id']));
            }
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
            background: #edf2f7; /* Restored original light blue color */
        }
        #kt_customers_table_wrapper th:nth-child(1) {
            z-index: 2;
        }

        /* Option 1: Hauteur fixe pour la card */
        .card {
            min-height: 40px; /* Ajustez selon vos besoins */
        }

        .table-responsive {
            min-height: 500px; /* Ajustez selon vos besoins */
            max-height: 80vh; /* Limite la hauteur à 80% de la fenêtre */
            overflow-y: auto; /* Ajoute une barre de défilement si nécessaire */
        }
        
        /* Glassmorphism effect for cards */
        .glassmorphism {
            background: rgba(255, 255, 255, 0.85) !important;
            backdrop-filter: blur(18px) !important;
            border-radius: 15px !important;
            border: 1px solid rgba(255, 255, 255, 0.18) !important;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.2) !important;
        }
        
        /* Table header styling */
        .table thead tr {
            background-color: #edf2f7 !important; /* Light blue header background */
        }
        
        /* Regular cell styling */
        .table td {
            background-color: #ffffff !important; /* White background for regular cells */
        }
        
        /* Empty cells styling */
        .table td[style="background-color: #f9f9f9;"] {
            background-color: #f9f9f9 !important; /* Gray background for empty cells */
        }
        
        /* First column styling override */
        #kt_customers_table_wrapper td:nth-child(1) {
            position: sticky;
            left: 0;
            background: #edf2f7 !important; /* Light blue for first column */
        }
    </style>

    <!--begin::Body-->
    <?php
    // Définir le fond d'écran pour cette page
    setPageBackground('bg-dashboard', true);
    
    // Ouvrir le conteneur de fond d'écran
    openBackgroundContainer('', 'id="kt_content" data-select2-id="select2-data-kt_content"');
    ?>
        <!-- Main title card - compact version -->
        <div class="container-xxl">
            <div class="card shadow-sm mb-5">
                <div class="card-body py-3">
                    <h1 class="text-dark fw-bold text-center fs-1 m-0">
                        <i class="fas fa-chalkboard-teacher text-dark me-3"></i>
                        <?php echo $bilan_test_tech_level ?>
                    </h1>
                </div>
            </div>
        </div>
        
        <!-- Search bar with glassmorphism -->
        <div class="container-xxl mb-4">
            <div class="card bg-opacity-50 bg-white border-0 glassmorphism" style="backdrop-filter: blur(10px);">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center position-relative">
                            <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5"><span class="path1"></span><span class="path2"></span></i>
                            <input type="text" id="search" class="form-control form-control-solid w-250px ps-12" placeholder="Recherche...">
                        </div>
                        <div class="d-flex gap-2">
                        <?php if ($_SESSION['profile'] == "Manager" || $_SESSION['profile'] == "Admin" || $_SESSION['profile'] == "Super Admin" || $_SESSION['profile'] == "Directeur Groupe") { ?>
                        <a href="./bilanTestNotes" class="btn btn-light-primary">
                            <i class="ki-duotone ki-chart-simple fs-2 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>
                            Voir les notes
                        </a>
                        <button type="button" id="toggle-recruitment" class="btn btn-light-info" onclick="toggleRecruitmentCandidates()">
                            <i class="ki-duotone ki-user-tick fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                            <span id="recruitment-btn-text"><?php echo $voir_candidats_recrutement; ?></span>
                        </button>
                        <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php 
        // Check if user has appropriate permissions
        if ($_SESSION['profile'] == "Super Admin" || $_SESSION['profile'] == "Directeur Groupe" ||
            $_SESSION['profile'] == "Admin" || $_SESSION["profile"] == "Ressource Humaine" ||
            $_SESSION['profile'] == "Directeur Pièce et Service" || $_SESSION['profile'] == "Directeur des Opérations" ||
            $_SESSION['profile'] == "Manager") {
        ?>
        
        <!--begin::Post-->
        <div class="post fs-6 d-flex flex-column-fluid" id="kt_post" data-select2-id="select2-data-kt_post">
            <!--begin::Container-->
            <div class="container-xxl" data-select2-id="select2-data-194-27hh">
                <!--begin::Card-->
                <div class="card glassmorphism">
                    <!--begin::Card body-->
                    <div class="card-body pt-0">
                        <?php if ($_SESSION['profile'] === "Super Admin") { ?>
                            <!--begin::Filtres -->
                            <div class="container my-4">
                                <div class="row g-3 align-items-center">
                                    <!-- Filtre Pays -->
                                    <div class="col-md-6">
                                        <label for="country-select" class="form-label d-flex align-items-center">
                                            <i class="bi bi-geo-alt-fill fs-2 me-2 text-primary"></i> Pays
                                        </label>
                                        <select id="country-select" onchange="applyFilters()" name="country" class="form-select">
                                            <option value="all" <?php if (isset($selectedCountry) && $selectedCountry === 'all') echo 'selected'; ?>>Tous les pays</option>
                                            <?php 
                                            if (isset($selectedCountry)) {
                                                foreach ($countries as $countryOption): ?>
                                                    <option value="<?php echo htmlspecialchars($countryOption); ?>" 
                                                            <?php if ($selectedCountry === $countryOption) echo 'selected'; ?>>
                                                        <?php echo htmlspecialchars($countryOption); ?>
                                                    </option>
                                                <?php endforeach; 
                                            } else { 
                                                foreach ($countries as $countryOption): ?>
                                                    <option value="<?php echo htmlspecialchars($countryOption); ?>">
                                                        <?php echo htmlspecialchars($countryOption); ?>
                                                    </option>
                                                <?php endforeach; 
                                            } ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <!--end::Filtres -->
                        <?php } ?>

                        <!--begin::Table-->
                        <div id="kt_customers_table_wrapper" class="dataTables_wrapper dt-bootstrap4 no-footer">
                            <div class="table-responsive">
                                <table aria-describedby=""
                                    class="table align-middle table-bordered fs-6 gy-5 dataTable no-footer"
                                    id="kt_customers_table">
                                    <thead>
                                        <tr class="text-start text-black fw-bold fs-7 text-uppercase gs-0">
                                        <?php if ($_SESSION['profile'] == "Super Admin" && !isset($_GET['country'])) { ?>
                                            <th class="w-100 sorting text-center" tabindex="0"
                                                aria-controls="kt_customers_table" colspan="3"
                                                aria-label="Customer Name: activate to sort column ascending"
                                                style="width: 125px;"><?php echo $technicienss ?>
                                            </th>
                                        <?php } elseif ($_SESSION['profile'] == "Admin") { ?>
                                            <th class="w-100 sorting text-center" tabindex="0"
                                                aria-controls="kt_customers_table" colspan="3"
                                                aria-label="Customer Name: activate to sort column ascending"
                                                style="width: 125px;"><?php echo $technicienss ?>
                                            </th>
                                        <?php } else { ?>
                                            <th class="w-100 sorting text-center" tabindex="0"
                                                aria-controls="kt_customers_table" colspan="2"
                                                aria-label="Customer Name: activate to sort column ascending"
                                                style="width: 125px;"><?php echo $technicienss ?>
                                            </th>
                                        <?php } ?>
                                            <th class="w-100 sorting text-center" tabindex="0"
                                                aria-controls="kt_customers_table" colspan="3"
                                                aria-label="Email: activate to sort column ascending"
                                                style="width: 155.266px;">
                                                <?php echo $Level ?> <?php echo $junior ?></th>
                                            <th class="w-100 sorting text-center" tabindex="0"
                                                aria-controls="kt_customers_table" colspan="3"
                                                aria-label="Email: activate to sort column ascending"
                                                style="width: 155.266px;">
                                                <?php echo $Level ?> <?php echo $senior ?></th>
                                            <th class="w-100 sorting text-center" tabindex="0"
                                                aria-controls="kt_customers_table" colspan="3"
                                                aria-label="Email: activate to sort column ascending"
                                                style="width: 155.266px;">
                                                <?php echo $Level ?> <?php echo $expert ?></th>
                                        </tr>
                                        <tr>
                                        <?php if ($_SESSION['profile'] == "Super Admin") { ?>
                                            <th class="min-w-200px sorting text-center text-black fw-bold" tabindex="0"
                                                aria-controls="kt_customers_table"
                                                aria-label="Email: activate to sort column ascending"
                                                style="width: 155.266px;">
                                                <?php echo $prenomsNoms ?></th>
                                            <th class="min-w-200px sorting text-center text-black fw-bold" tabindex="0"
                                                aria-controls="kt_customers_table"
                                                aria-label="Email: activate to sort column ascending"
                                                style="width: 155.266px;">
                                                <?php echo $manager ?></th>
                                            <?php if ($_SESSION['profile'] == "Super Admin" && !isset($_GET['country'])) { ?>
                                            <th class="w-100 sorting text-center text-black fw-bold" tabindex="0"
                                                aria-controls="kt_customers_table"
                                                aria-label="Email: activate to sort column ascending"
                                                style="width: 155.266px;">
                                                <?php echo $pays ?></th>
                                            <?php } ?>
                                        <?php } elseif ($_SESSION['profile'] == "Admin") { ?>
                                            <th class="min-w-200px sorting text-center text-black fw-bold" tabindex="0"
                                                aria-controls="kt_customers_table"
                                                aria-label="Email: activate to sort column ascending"
                                                style="width: 155.266px;">
                                                <?php echo $prenomsNoms ?></th>
                                            <th class="min-w-100px sorting text-center text-black fw-bold" tabindex="0"
                                                aria-controls="kt_customers_table"
                                                aria-label="Email: activate to sort column ascending"
                                                style="width: 100px;">
                                                <?php echo $Level ?></th>
                                            <th class="min-w-150px sorting text-center text-black fw-bold" tabindex="0"
                                                aria-controls="kt_customers_table"
                                                aria-label="Email: activate to sort column ascending"
                                                style="width: 150px;">
                                                <?php echo $agence ?></th>
                                        <?php } else { ?>
                                            <th class="min-w-200px sorting text-center text-black fw-bold" tabindex="0"
                                                aria-controls="kt_customers_table"
                                                aria-label="Email: activate to sort column ascending"
                                                style="width: 155.266px;">
                                                <?php echo $prenomsNoms ?></th>
                                            <th class="min-w-200px sorting text-center text-black fw-bold" tabindex="0"
                                                aria-controls="kt_customers_table"
                                                aria-label="Email: activate to sort column ascending"
                                                style="width: 155.266px;">
                                                <?php echo $agence ?></th>
                                        <?php } ?>
                                            <!-- Columns for Junior, Senior, Expert levels -->
                                            <?php
                                            // Define reusable headers for test categories
                                            $testHeaders = [
                                                $qcm_connaissances_tech,
                                                $qcm_tache_pro_tech,
                                                $qcm_tache_pro_manager,
                                            ];
                                            
                                            // Output headers for each level
                                            foreach (['Junior', 'Senior', 'Expert'] as $level) {
                                                // Output headers for QCM categories
                                                foreach ($testHeaders as $header) {
                                                    echo '<th class="min-w-80px sorting text-center text-black fw-bold" tabindex="0"
                                                        aria-controls="kt_customers_table"
                                                        aria-label="Email: activate to sort column ascending"
                                                        style="width: 155.266px;">' . $header . '</th>';
                                                }
                                            }
                                            ?>
                                        </tr>
                                    </thead>
                                    <tbody class="fw-semibold text-gray-600" id="table">
                                    <?php
                                    // Define all helper functions OUTSIDE the loop to avoid redeclaration
                                    
                                    // Helper function to find test
                                    function findTest($tests, $userId, $level, $type) {
                                        return $tests->findOne([
                                            '$and' => [
                                                [
                                                    "user" => new MongoDB\BSON\ObjectId($userId),
                                                    "level" => $level,
                                                    "type" => $type,
                                                    "active" => true,
                                                ],
                                            ],
                                        ]);
                                    }
                                    
                                    // Helper function to find exam
                                    function findExam($exams, $userId, $testId, $type = null, $managerId = null) {
                                        $query = [
                                            '$and' => [
                                                [
                                                    "user" => new MongoDB\BSON\ObjectId($userId),
                                                    "test" => new MongoDB\BSON\ObjectId($testId),
                                                ],
                                            ],
                                        ];
                                        
                                        if ($type) {
                                            $query['$and'][0]["type"] = $type;
                                        }
                                        
                                        if ($managerId) {
                                            $query['$and'][0]["manager"] = new MongoDB\BSON\ObjectId($managerId);
                                        }
                                        
                                        return $exams->findOne($query);
                                    }
                                    
                                    // Helper function to find allocation
                                    function findAllocation($allocations, $userId, $level, $type) {
                                        return $allocations->findOne([
                                            '$and' => [
                                                [
                                                    "user" => new MongoDB\BSON\ObjectId($userId),
                                                    "level" => $level,
                                                    "type" => $type,
                                                ],
                                            ],
                                        ]);
                                    }
                                    
                                    // Helper function to find result
                                    function findResult($results, $userId, $level, $type, $typeR) {
                                        return $results->findOne([
                                            '$and' => [
                                                [
                                                    "user" => new MongoDB\BSON\ObjectId($userId),
                                                    "level" => $level,
                                                    "type" => $type,
                                                    "typeR" => $typeR,
                                                    "active" => true,
                                                ],
                                            ],
                                        ]);
                                    }
                                    
                                    // Helper function to calculate percentage
                                    function calculatePercentage($result) {
                                        if (isset($result) && isset($result["score"]) && isset($result["total"]) && $result["total"] > 0) {
                                            return ceil(($result["score"] * 100) / $result["total"]);
                                        }
                                        return null;
                                    }
                                    
                                    // Helper function to render score cell
                                    function renderScoreCell($percentage, $examActive = false, $allocActive = true, $inProgress = false, $completerText = null) {
                                       global $en_cours, $completer, $completed;
                                        
                                        if ($inProgress || ($examActive && isset($examActive['active']) && $examActive['active'] == true)) {
                                            return '<td class="text-center">
                                                <span class="badge text-primary fs-7 m-1">' . $en_cours . '</span>
                                            </td>';
                                        }
                                        elseif ($allocActive && isset($allocActive['active']) && $allocActive['active'] == true) {
                                            if ($percentage !== null) {
                                                return '<td class="text-center">
                                                    <span class="badge text-success fs-7 m-1">' . htmlspecialchars($completed) . '</span>
                                                </td>';
                                            }
                                            // Si allocation active mais pas de résultat, afficher "À compléter"
                                            return '<td class="text-center"><span class="badge text-danger fs-7 m-1">' . ($completerText ? $completerText : $completer) . '</span></td>';
                                        }
                                        elseif ($allocActive && isset($allocActive['active']) && $allocActive['active'] == false) {
                                            return '<td class="text-center"><span class="badge text-danger fs-7 m-1">' . ($completerText ? $completerText : $completer) . '</span></td>';
                                        }
                                        
                                        return '<td class="text-center" style="background-color: #f9f9f9;"></td>';
                                    }
                                    
                                    // Helper function to render result button
                                    function renderResultButton($score, $resultFac, $level, $userId) {
                                        global $completer;
                                        
                                        if ($score !== null) {
                                            return '<td class="text-center">
                                                <a href="./result.php?numberTest=' . $resultFac["numberTest"] . '&level=' . $level . '&user=' . $userId . '"
                                                    class="btn btn-light btn-active-light-success text-success btn-sm"
                                                    title="Cliquez ici pour voir le résultat du technicien pour le niveau ' . strtolower($level) . '"
                                                    data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                    Complété
                                                </a>
                                            </td>';
                                        }
                                        
                                        return '<td class="text-center"><span class="badge text-danger fs-7 m-1">' . $completer . '</span></td>';
                                    }
                                    
                                    // Process each technician
                                    for ($i = 0; $i < count($tech); $i++) {
                                        // Find user
                                        $user = $users->findOne([
                                            '$and' => [
                                                [
                                                    "_id" => new MongoDB\BSON\ObjectId($tech[$i]),
                                                    "active" => true
                                                ],
                                            ],
                                        ]);
                                        
                                        // Check if user has recruitment role
                                        $isRecruitment = isset($user['role']) && $user['role'] == "Test pour recrutement";
                                        
                                        // Initialize manager variable
                                        $manager = null;
                                        
                                        // Find manager if exists
                                        if (!empty($user['manager'])) {
                                            $manager = $users->findOne([
                                                '$and' => [
                                                    ["_id" => new MongoDB\BSON\ObjectId($user['manager'])],
                                                    ["active" => true]
                                                ],
                                            ]);
                                        }
                                        
                                        // Get tests for different levels and types
                                        $testFacJu = findTest($tests, $user['_id'], "Junior", "Factuel");
                                        $testDeclaJu = findTest($tests, $user['_id'], "Junior", "Declaratif");
                                        $testFacSe = findTest($tests, $user['_id'], "Senior", "Factuel");
                                        $testDeclaSe = findTest($tests, $user['_id'], "Senior", "Declaratif");
                                        $testFacEx = findTest($tests, $user['_id'], "Expert", "Factuel");
                                        $testDeclaEx = findTest($tests, $user['_id'], "Expert", "Declaratif");
                                        
                                        // Find exams if tests exist
                                        $examFacJu = null;
                                        $examFacSe = null;
                                        $examFacEx = null;
                                        $examDeclaJu = null;
                                        $examDeclaSe = null;
                                        $examDeclaEx = null;
                                        $examDeclaMaJu = null;
                                        $examDeclaMaSe = null;
                                        $examDeclaMaEx = null;
                                        
                                        if (isset($testFacJu)) {
                                            $examFacJu = findExam($exams, $user['_id'], $testFacJu['_id']);
                                        }
                                        
                                        if (isset($testFacSe)) {
                                            $examFacSe = findExam($exams, $user['_id'], $testFacSe['_id']);
                                        }
                                        
                                        if (isset($testFacEx)) {
                                            $examFacEx = findExam($exams, $user['_id'], $testFacEx['_id']);
                                        }
                                        
                                        if (isset($testDeclaJu)) {
                                            $examDeclaJu = findExam($exams, $user['_id'], $testDeclaJu['_id'], "Technicien");
                                        }
                                        
                                        if (isset($testDeclaSe)) {
                                            $examDeclaSe = findExam($exams, $user['_id'], $testDeclaSe['_id'], "Technicien");
                                        }
                                        
                                        if (isset($testDeclaEx)) {
                                            $examDeclaEx = findExam($exams, $user['_id'], $testDeclaEx['_id'], "Technicien");
                                        }
                                        
                                        // Find manager exams if manager exists
                                        if (!empty($user['manager'])) {
                                            if (isset($testDeclaJu)) {
                                                $examDeclaMaJu = findExam($exams, $user['_id'], $testDeclaJu['_id'], "Manager", $user['manager']);
                                            }
                                            
                                            if (isset($testDeclaSe)) {
                                                $examDeclaMaSe = findExam($exams, $user['_id'], $testDeclaSe['_id'], "Manager", $user['manager']);
                                            }
                                            
                                            if (isset($testDeclaEx)) {
                                                $examDeclaMaEx = findExam($exams, $user['_id'], $testDeclaEx['_id'], "Manager", $user['manager']);
                                            }
                                        }
                                        
                                        // Get allocations
                                        $allocateFacJu = findAllocation($allocations, $tech[$i], "Junior", "Factuel");
                                        $allocateFacSe = findAllocation($allocations, $tech[$i], "Senior", "Factuel");
                                        $allocateFacEx = findAllocation($allocations, $tech[$i], "Expert", "Factuel");
                                        $allocateDeclaJu = findAllocation($allocations, $tech[$i], "Junior", "Declaratif");
                                        $allocateDeclaSe = findAllocation($allocations, $tech[$i], "Senior", "Declaratif");
                                        $allocateDeclaEx = findAllocation($allocations, $tech[$i], "Expert", "Declaratif");
                                        
                                        // Get results
                                        $resultFacJu = findResult($results, $tech[$i], "Junior", "Factuel", "Technicien");
                                        $resultFacSe = findResult($results, $tech[$i], "Senior", "Factuel", "Technicien");
                                        $resultFacEx = findResult($results, $tech[$i], "Expert", "Factuel", "Technicien");
                                        
                                        $resultDeclaJu = findResult($results, $tech[$i], "Junior", "Declaratif", "Technicien - Manager");
                                        $resultDeclaSe = findResult($results, $tech[$i], "Senior", "Declaratif", "Technicien - Manager");
                                        $resultDeclaEx = findResult($results, $tech[$i], "Expert", "Declaratif", "Technicien - Manager");
                                        
                                        $resultDeclaJuTech = findResult($results, $tech[$i], "Junior", "Declaratif", "Techniciens");
                                        $resultDeclaSeTech = findResult($results, $tech[$i], "Senior", "Declaratif", "Techniciens");
                                        $resultDeclaExTech = findResult($results, $tech[$i], "Expert", "Declaratif", "Techniciens");
                                        
                                        $resultDeclaJuMa = findResult($results, $tech[$i], "Junior", "Declaratif", "Managers");
                                        $resultDeclaSeMa = findResult($results, $tech[$i], "Senior", "Declaratif", "Managers");
                                        $resultDeclaExMa = findResult($results, $tech[$i], "Expert", "Declaratif", "Managers");
                                        
                                        // Calculate percentages
                                        $percentageFacJu = calculatePercentage($resultFacJu);
                                        $percentageDeclaJu = calculatePercentage($resultDeclaJu);
                                        $percentageDeclaJuTech = calculatePercentage($resultDeclaJuTech);
                                        $percentageDeclaJuMa = calculatePercentage($resultDeclaJuMa);
                                        
                                        $percentageFacSe = calculatePercentage($resultFacSe);
                                        $percentageDeclaSe = calculatePercentage($resultDeclaSe);
                                        $percentageDeclaSeTech = calculatePercentage($resultDeclaSeTech);
                                        $percentageDeclaSeMa = calculatePercentage($resultDeclaSeMa);
                                        
                                        $percentageFacEx = calculatePercentage($resultFacEx);
                                        $percentageDeclaEx = calculatePercentage($resultDeclaEx);
                                        $percentageDeclaExTech = calculatePercentage($resultDeclaExTech);
                                        $percentageDeclaExMa = calculatePercentage($resultDeclaExMa);
                                        
                                        // Calculate overall level scores
                                        $junior = null;
                                        $senior = null;
                                        $expert = null;
                                        
                                        if (isset($percentageFacJu) && isset($percentageDeclaJu)) {
                                            $junior = ceil(($percentageFacJu + $percentageDeclaJu) / 2);
                                        }
                                        
                                        if (isset($percentageFacSe) && isset($percentageDeclaSe)) {
                                            $senior = ceil(($percentageFacSe + $percentageDeclaSe) / 2);
                                        }
                                        
                                        if (isset($percentageFacEx) && isset($percentageDeclaEx)) {
                                            $expert = ceil(($percentageFacEx + $percentageDeclaEx) / 2);
                                        }
                                        ?>
                                        <tr class="odd <?php echo $isRecruitment ? 'recruitment-candidate' : 'regular-technician'; ?>" 
                                            data-role="<?php echo $isRecruitment ? 'recruitment' : 'regular'; ?>"
                                            style="<?php echo $isRecruitment ? 'display: none;' : ''; ?>">
                                            <!-- Name -->
                                            <td class="text-center">
                                                <?php echo $user["firstName"]; ?> <?php echo $user["lastName"]; ?>
                                            </td>
                                            
                                            <!-- Manager/Agency Column -->
                                            <?php if ($_SESSION['profile'] == "Super Admin") { ?>
                                                <td class="text-center">
                                                    <?php if (!empty($user['manager']) && isset($manager)) { ?>
                                                        <?php echo $manager["firstName"]; ?> <?php echo $manager["lastName"]; ?>
                                                    <?php } ?>
                                                </td>
                                                <?php if ($_SESSION['profile'] == "Super Admin" && !isset($_GET['country'])) { ?>
                                                    <td class="text-center">
                                                        <?php echo $user["country"]; ?>
                                                    </td>
                                                <?php } ?>
                                            <?php } elseif ($_SESSION['profile'] == "Admin") { ?>
                                                <td class="text-center py-2 px-1">
                                                    <?php echo $user["level"]; ?>
                                                </td>
                                                <td class="text-center py-2 px-1">
                                                    <?php echo $user["agency"]; ?>
                                                </td>
                                            <?php } else { ?>
                                                <td class="text-center">
                                                    <?php echo $user["agency"]; ?>
                                                </td>
                                            <?php } ?>
                                            
                                            <?php 
                                            // For recruitment candidates, only show QCM Connaissances, mark others as N/A
                                            if ($isRecruitment) {
                                                // Junior level - only Connaissances
                                                echo renderScoreCell($percentageFacJu, isset($examFacJu) ? $examFacJu : null, isset($allocateFacJu) ? $allocateFacJu : null);
                                                echo '<td class="text-center"><span class="badge badge-light-secondary fs-7 m-1">Non Applicable</span></td>';
                                                echo '<td class="text-center"><span class="badge badge-light-secondary fs-7 m-1">Non Applicable</span></td>';
                                                
                                                // Senior level - only Connaissances if allocated
                                                if (isset($allocateFacSe)) {
                                                    echo renderScoreCell($percentageFacSe, isset($examFacSe) ? $examFacSe : null, isset($allocateFacSe) ? $allocateFacSe : null);
                                                    echo '<td class="text-center"><span class="badge badge-light-secondary fs-7 m-1">Non Applicable</span></td>';
                                                    echo '<td class="text-center"><span class="badge badge-light-secondary fs-7 m-1">Non Applicable</span></td>';
                                                } else {
                                                    echo '<td class="text-center" style="background-color: #f9f9f9;"></td>';
                                                    echo '<td class="text-center" style="background-color: #f9f9f9;"></td>';
                                                    echo '<td class="text-center" style="background-color: #f9f9f9;"></td>';
                                                }
                                                
                                                // Expert level - only Connaissances if allocated
                                                if (isset($allocateFacEx)) {
                                                    echo renderScoreCell($percentageFacEx, isset($examFacEx) ? $examFacEx : null, isset($allocateFacEx) ? $allocateFacEx : null);
                                                    echo '<td class="text-center"><span class="badge badge-light-secondary fs-7 m-1">Non Applicable</span></td>';
                                                    echo '<td class="text-center"><span class="badge badge-light-secondary fs-7 m-1">Non Applicable</span></td>';
                                                } else {
                                                    echo '<td class="text-center" style="background-color: #f9f9f9;"></td>';
                                                    echo '<td class="text-center" style="background-color: #f9f9f9;"></td>';
                                                    echo '<td class="text-center" style="background-color: #f9f9f9;"></td>';
                                                }
                                            } else {
                                                // Regular technicians - show all columns
                                                // Render Junior level cells
                                                echo renderScoreCell($percentageFacJu, isset($examFacJu) ? $examFacJu : null, isset($allocateFacJu) ? $allocateFacJu : null);
                                                echo renderScoreCell($percentageDeclaJuTech, isset($examDeclaJu) ? $examDeclaJu : null, isset($allocateDeclaJu) ? $allocateDeclaJu : null);
                                                echo renderScoreCell($percentageDeclaJuMa, isset($examDeclaMaJu) ? $examDeclaMaJu : null, isset($allocateDeclaJu) && isset($allocateDeclaJu['activeManager']) ? ['active' => $allocateDeclaJu['activeManager']] : null);
                                                
                                                // Render Senior level cells
                                                if (isset($allocateFacSe) && isset($allocateDeclaSe)) {
                                                    echo renderScoreCell($percentageFacSe, isset($examFacSe) ? $examFacSe : null, isset($allocateFacSe) ? $allocateFacSe : null);
                                                    echo renderScoreCell($percentageDeclaSeTech, isset($examDeclaSe) ? $examDeclaSe : null, isset($allocateDeclaSe) ? $allocateDeclaSe : null);
                                                    echo renderScoreCell($percentageDeclaSeMa, isset($examDeclaMaSe) ? $examDeclaMaSe : null, isset($allocateDeclaSe) && isset($allocateDeclaSe['activeManager']) ? ['active' => $allocateDeclaSe['activeManager']] : null);
                                                } else {
                                                    echo '<td class="text-center" style="background-color: #f9f9f9;"></td>';
                                                    echo '<td class="text-center" style="background-color: #f9f9f9;"></td>';
                                                    echo '<td class="text-center" style="background-color: #f9f9f9;"></td>';
                                                }
                                                
                                                // Render Expert level cells
                                                if (isset($allocateFacEx) && isset($allocateDeclaEx)) {
                                                    echo renderScoreCell($percentageFacEx, isset($examFacEx) ? $examFacEx : null, isset($allocateFacEx) ? $allocateFacEx : null);
                                                    echo renderScoreCell($percentageDeclaExTech, isset($examDeclaEx) ? $examDeclaEx : null, isset($allocateDeclaEx) ? $allocateDeclaEx : null);
                                                    echo renderScoreCell($percentageDeclaExMa, isset($examDeclaMaEx) ? $examDeclaMaEx : null, isset($allocateDeclaEx) && isset($allocateDeclaEx['activeManager']) ? ['active' => $allocateDeclaEx['activeManager']] : null);
                                                } else {
                                                    echo '<td class="text-center" style="background-color: #f9f9f9;"></td>';
                                                    echo '<td class="text-center" style="background-color: #f9f9f9;"></td>';
                                                    echo '<td class="text-center" style="background-color: #f9f9f9;"></td>';
                                                }
                                            }
                                            ?>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="row">
                                <div class="col-sm-12 col-md-5 d-flex align-items-center justify-content-center justify-content-md-start">
                                    <div class="dataTables_length">
                                        <label>
                                            <select id="kt_customers_table_length" name="kt_customers_table_length"
                                                class="form-select form-select-sm form-select-solid">
                                                <option value="100">100</option>
                                                <option value="200">200</option>
                                                <option value="300">300</option>
                                                <option value="500">500</option>
                                            </select>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-sm-12 col-md-7 d-flex align-items-center justify-content-center justify-content-md-end">
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
                
                <!--begin::Export buttons-->
                <div class="d-flex justify-content-end align-items-center mt-5">
                    <!-- Export button: Tous (All) -->
                    <button type="button" id="export-all" title="Exporter tous les techniciens" class="btn btn-light-primary">
                        <i class="ki-duotone ki-exit-up fs-2 me-1"><span class="path1"></span><span class="path2"></span></i>
                        Excel
                    </button>
                    <?php if ($_SESSION['profile'] == "Manager" || $_SESSION['profile'] == "Admin" || $_SESSION['profile'] == "Super Admin") { ?>
                    <!-- Export button: Candidats au recrutement -->
                    <button type="button" id="export-recruitment" title="Exporter les candidats au recrutement" class="btn btn-light-info ms-2" style="display: none;">
                        <i class="ki-duotone ki-user-tick fs-2 me-1"><span class="path1"></span><span class="path2"></span></i>
                        Excel Candidats
                    </button>
                    <?php } ?>
                </div>
                <!--end::Export dropdown-->
            </div>
            <!--end::Container-->
        </div>
        <!--end::Post-->
        <?php } ?>
    <?php closeBackgroundContainer(); ?>
    <!--end::Body-->
    <!-- Helper functions for export -->
    <?php
    /**
     * Check if a technician has any incomplete evaluations
     * @param string $userId The technician's user ID
     * @return bool True if has incomplete evaluations, false otherwise
     */
    function hasIncompleteTests($userId, $tests, $allocations, $exams, $managerId = null) {
        // Check Junior level
        $hasIncomplete = checkLevelIncomplete($userId, "Junior", $tests, $allocations, $exams, $managerId);
        if ($hasIncomplete) return true;
        
        // Check Senior level
        $hasIncomplete = checkLevelIncomplete($userId, "Senior", $tests, $allocations, $exams, $managerId);
        if ($hasIncomplete) return true;
        
        // Check Expert level
        $hasIncomplete = checkLevelIncomplete($userId, "Expert", $tests, $allocations, $exams, $managerId);
        if ($hasIncomplete) return true;
        
        return false;
    }
    
    /**
     * Check if a technician has all evaluations completed
     * @param string $userId The technician's user ID
     * @return bool True if all evaluations are complete, false otherwise
     */
    function hasCompleteTests($userId, $tests, $allocations, $exams, $managerId = null) {
        // If any test is incomplete, then not all tests are complete
        return !hasIncompleteTests($userId, $tests, $allocations, $exams, $managerId);
    }
    
    /**
     * Check if a specific level has incomplete evaluations
     * @param string $userId The technician's user ID
     * @param string $level The level to check (Junior, Senior, Expert)
     * @param object $tests Tests collection
     * @param object $allocations Allocations collection
     * @param object $exams Exams collection
     * @param string|null $managerId The manager ID if available
     * @return bool True if the level has incomplete evaluations, false otherwise
     */
    function checkLevelIncomplete($userId, $level, $tests, $allocations, $exams, $managerId = null) {
        // Check factual test
        $testFac = findTest($tests, $userId, $level, "Factuel");
        $allocateFac = findAllocation($allocations, $userId, $level, "Factuel");
        
        // Check declarative test
        $testDecla = findTest($tests, $userId, $level, "Declaratif");
        $allocateDecla = findAllocation($allocations, $userId, $level, "Declaratif");
        
        // Check if allocation exists and is active
        if (isset($allocateFac) && $allocateFac['active'] == true) {
            // Check if exam exists and is active
            $examFac = isset($testFac) ? findExam($exams, $userId, $testFac['_id']) : null;
            if (isset($examFac) && $examFac['active'] == true) {
                return true; // Incomplete - exam in progress
            }
        }
        
        // Check declarative test (both technician and manager parts)
        if (isset($allocateDecla) && $allocateDecla['active'] == true) {
            // Check technician part
            $examDecla = isset($testDecla) ? findExam($exams, $userId, $testDecla['_id'], "Technicien") : null;
            if (isset($examDecla) && $examDecla['active'] == true) {
                return true; // Incomplete - exam in progress
            }
            
            // Check manager part
            if (isset($allocateDecla['activeManager']) && $allocateDecla['activeManager'] == true) {
                $examDeclaMa = isset($testDecla) && isset($userId) ?
                    findExam($exams, $userId, $testDecla['_id'], "Manager", $managerId) : null;
                if (isset($examDeclaMa) && $examDeclaMa['active'] == true) {
                    return true; // Incomplete - manager exam in progress
                }
            }
        }
        
        return false;
    }
    
    /**
     * Generate Excel file with filtered technicians data
     * @param array $technicians Array of technician data
     * @param string $type The export type (incomplete, complete, all)
     * @param string $filename The filename for the Excel file
     */
    function generateExcel($technicians, $type, $filename) {
        require_once '../../vendor/autoload.php';
        
        // Create new Spreadsheet object
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set column headers
        $sheet->setCellValue('A1', 'Nom');
        $sheet->setCellValue('B1', 'Prénom');
        $sheet->setCellValue('C1', 'Email');
        $sheet->setCellValue('D1', 'Pays');
        $sheet->setCellValue('E1', 'Filiale');
        $sheet->setCellValue('F1', 'Agence');
        $sheet->setCellValue('G1', 'Manager');
        $sheet->setCellValue('H1', 'Junior - Statut');
        $sheet->setCellValue('I1', 'Senior - Statut');
        $sheet->setCellValue('J1', 'Expert - Statut');
        $sheet->setCellValue('K1', 'Statut');
        
        // Apply styling to header row
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ]
        ];
        $sheet->getStyle('A1:K1')->applyFromArray($headerStyle);
        
        // Auto-size columns
        foreach(range('A','K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Fill data
        $row = 2;
        foreach ($technicians as $tech) {
            $sheet->setCellValue('A' . $row, $tech['lastName']);
            $sheet->setCellValue('B' . $row, $tech['firstName']);
            $sheet->setCellValue('C' . $row, $tech['email']);
            $sheet->setCellValue('D' . $row, $tech['country']);
            $sheet->setCellValue('E' . $row, $tech['subsidiary']);
            $sheet->setCellValue('F' . $row, $tech['agency']);
            
            // Manager name
            $managerName = '';
            if (!empty($tech['manager_name'])) {
                $managerName = $tech['manager_name'];
            }
            $sheet->setCellValue('G' . $row, $managerName);
            
            // Status instead of scores
            $juniorScore = isset($tech['junior_score']) ? 'Complété' : (isset($tech['junior_allocated']) ? 'À compléter' : 'Non Applicable');
            $seniorScore = isset($tech['senior_score']) ? 'Complété' : (isset($tech['senior_allocated']) ? 'À compléter' : 'Non Applicable');
            $expertScore = isset($tech['expert_score']) ? 'Complété' : (isset($tech['expert_allocated']) ? 'À compléter' : 'Non Applicable');
            
            $sheet->setCellValue('H' . $row, $juniorScore);
            $sheet->setCellValue('I' . $row, $seniorScore);
            $sheet->setCellValue('J' . $row, $expertScore);
            
            // Apply color coding based on status
            foreach (['H' => ['junior_score', 'junior_allocated'],
                    'I' => ['senior_score', 'senior_allocated'],
                    'J' => ['expert_score', 'expert_allocated']] as $col => $fields) {
                $scoreField = $fields[0];
                $allocField = $fields[1];
                
                // Determine color based on status
                if (isset($tech[$scoreField])) {
                    $color = '00B050'; // Green for Complété
                } elseif (isset($tech[$allocField])) {
                    $color = 'FF0000'; // Red for À compléter
                } else {
                    $color = '808080'; // Gray for Non Applicable
                }
                
                $sheet->getStyle($col . $row)->applyFromArray([
                    'font' => ['color' => ['rgb' => $color]],
                    'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
                ]);
            }
            
            // Status column
            $status = '';
            switch ($type) {
                case 'incomplete':
                    $status = 'À compléter';
                    break;
                case 'complete':
                    $status = 'Terminé';
                    break;
                default:
                    $status = isset($tech['status']) ? $tech['status'] : '';
            }
            $sheet->setCellValue('K' . $row, $status);
            
            $row++;
        }
        
        // Create Excel file
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($filename);
        
        // Send file to browser for download
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        exit;
    }
    ?>

    <script>
        // Filter application function
        function applyFilters() {
            var country = document.querySelector("#country-select").value;
            var urlParams = new URLSearchParams(window.location.search);

            // Update or add the 'country' parameter in the URL based on profile
            if (country && country !== "all") {
                urlParams.set('country', country);
            } else {
                urlParams.delete('country');
            }

            // Redirect to the updated URL
            window.location.search = urlParams.toString();
        }
        
        // Toggle recruitment candidates visibility
        let showingRecruitment = false;
        function toggleRecruitmentCandidates() {
            showingRecruitment = !showingRecruitment;
            const btnText = document.getElementById('recruitment-btn-text');
            const recruitmentRows = document.querySelectorAll('tr[data-role="recruitment"]');
            const regularRows = document.querySelectorAll('tr[data-role="regular"]');
            const exportRecruitmentBtn = document.getElementById('export-recruitment');
            const exportAllBtn = document.getElementById('export-all');

            if (showingRecruitment) {
                // Show recruitment candidates, hide regular technicians
                btnText.textContent = 'Masquer les candidats au recrutement';
                recruitmentRows.forEach(row => row.style.display = '');
                regularRows.forEach(row => row.style.display = 'none');
                // Show export recruitment button
                if (exportRecruitmentBtn) {
                    exportRecruitmentBtn.style.display = '';
                }
                // Hide export all button
                if (exportAllBtn) {
                    exportAllBtn.style.display = 'none';
                }
            } else {
                // Show regular technicians, hide recruitment candidates
                btnText.textContent = 'Voir les candidats au recrutement';
                recruitmentRows.forEach(row => row.style.display = 'none');
                regularRows.forEach(row => row.style.display = '');
                // Hide export recruitment button
                if (exportRecruitmentBtn) {
                    exportRecruitmentBtn.style.display = 'none';
                }
                // Show export all button
                if (exportAllBtn) {
                    exportAllBtn.style.display = '';
                }
            }
        }
        
        // Export button click handlers
        document.addEventListener('DOMContentLoaded', function() {
            // Export All button
            document.getElementById('export-all').addEventListener('click', function() {
                window.location.href = 'export_excel.php?type=all' + getCountryParam();
            });

            // Export Recruitment button
            const exportRecruitmentBtn = document.getElementById('export-recruitment');
            if (exportRecruitmentBtn) {
                exportRecruitmentBtn.addEventListener('click', function() {
                    window.location.href = 'export_excel.php?type=recruitment' + getCountryParam();
                });
            }

            // Helper function to get country parameter if it exists
            function getCountryParam() {
                var countrySelect = document.querySelector("#country-select");
                if (countrySelect && countrySelect.value && countrySelect.value !== "all") {
                    return '&country=' + countrySelect.value;
                }
                return '';
            }
        });
    </script>
    <?php include_once "partials/footer.php"; ?>
<?php } ?>
