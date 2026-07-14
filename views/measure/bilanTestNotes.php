<?php
session_start();

include_once "../partials/background-manager.php";

if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
} else {
    require_once "../../vendor/autoload.php";

    $conn = new MongoDB\Client("mongodb://localhost:27017");
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

    // ===== FILTRES GET =====
    $selectedSubsidiary = $_GET['subsidiary'] ?? '';
    $selectedLevel      = $_GET['level']      ?? '';
    $selectedDepartment = $_GET['department']  ?? '';

    $isGroupScope = in_array($_SESSION['profile'], ['Super Admin', 'Directeur Groupe']);

    // ===== RÉCUPÉRATION DES OPTIONS DE FILTRES =====
    $subsidiaryOptions  = [];
    $departmentOptions  = [];
    $levelOptions       = ['Junior', 'Senior', 'Expert'];

    if ($isGroupScope) {
        $rawSubs = $users->distinct('subsidiary', ['active' => true]);
        foreach ($rawSubs as $s) {
            $s = trim((string)$s);
            if ($s !== '' && !in_array(strtoupper($s), ['CFR', 'CFAO'])) {
                $subsidiaryOptions[] = $s;
            }
        }
        sort($subsidiaryOptions);
    }

    // Départements disponibles selon périmètre
    $deptQuery = ['active' => true];
    if (!$isGroupScope) {
        $deptQuery['subsidiary'] = $_SESSION['subsidiary'];
    } elseif ($selectedSubsidiary && $selectedSubsidiary !== 'all') {
        $deptQuery['subsidiary'] = $selectedSubsidiary;
    }
    $rawDepts = $users->distinct('department', $deptQuery);
    foreach ($rawDepts as $d) {
        $d = trim((string)$d);
        if ($d !== '' && $d !== 'null') {
            $departmentOptions[] = $d;
        }
    }
    sort($departmentOptions);

    // ===== REQUÊTE TECHNICIENS =====
    $tech = [];

    if ($isGroupScope) {
        $query = ['active' => true, 'subsidiary' => ['$exists' => true, '$nin' => ['', null]], 'level' => ['$in' => ['Junior', 'Senior', 'Expert']], 'isCandidat' => ['$ne' => true]];
        if ($selectedSubsidiary && $selectedSubsidiary !== 'all') {
            $query['subsidiary'] = $selectedSubsidiary;
        }
        if ($selectedDepartment && $selectedDepartment !== 'all') {
            $query['department'] = $selectedDepartment;
        }
        if ($selectedLevel && $selectedLevel !== 'all') {
            $query['level'] = $selectedLevel;
        }
        $technicians = $users->find($query)->toArray();
        foreach ($technicians as $techn) {
            if ($techn["profile"] == "Technicien" ||
                ($techn["profile"] == "Manager" && $techn["test"] == true)) {
                $tech[] = new MongoDB\BSON\ObjectId($techn['_id']);
            }
        }

    } elseif ($_SESSION['profile'] == "Directeur Général") {

        $query = ["subsidiary" => $_SESSION['subsidiary'], "active" => true, 'isCandidat' => ['$ne' => true]];
        if ($selectedDepartment && $selectedDepartment !== 'all') {
            $query['department'] = $selectedDepartment;
        }
        if ($selectedLevel && $selectedLevel !== 'all') {
            $query['level'] = $selectedLevel;
        }
        $technicians = $users->find($query)->toArray();
        foreach ($technicians as $techn) {
            if ($techn["profile"] == "Technicien" ||
                ($techn["profile"] == "Manager" && $techn["test"] == true)) {
                $tech[] = new MongoDB\BSON\ObjectId($techn['_id']);
            }
        }

    } elseif ($_SESSION['profile'] == "Admin" || $_SESSION["profile"] == "Ressource Humaine" ||
              $_SESSION['profile'] == "Directeur Pièce et Service" || $_SESSION['profile'] == "Directeur des Opérations") {

        $query = ["subsidiary" => $_SESSION['subsidiary'], "active" => true];
        if ($_SESSION["department"] != 'Equipment & Motors') {
            $query['department'] = $_SESSION["department"];
        } elseif ($selectedDepartment && $selectedDepartment !== 'all') {
            $query['department'] = $selectedDepartment;
        }
        if ($selectedLevel && $selectedLevel !== 'all') {
            $query['level'] = $selectedLevel;
        }
        $technicians = $users->find($query)->toArray();
        foreach ($technicians as $techn) {
            if ($techn["profile"] == "Technicien" ||
                ($techn["profile"] == "Manager" && $techn["test"] == true)) {
                $tech[] = new MongoDB\BSON\ObjectId($techn['_id']);
            }
        }

    } elseif ($_SESSION['profile'] == "Manager") {
        $query = ["manager" => new MongoDB\BSON\ObjectId($_SESSION['id']), "active" => true];
        if ($selectedLevel && $selectedLevel !== 'all') {
            $query['level'] = $selectedLevel;
        }
        if ($selectedDepartment && $selectedDepartment !== 'all') {
            $query['department'] = $selectedDepartment;
        }
        $technicians = $users->find($query)->toArray();
        foreach ($technicians as $techn) {
            if ($techn["profile"] == "Technicien" ||
                ($techn["profile"] == "Manager" && $techn["test"] == true)) {
                $tech[] = new MongoDB\BSON\ObjectId($techn['_id']);
            }
        }
    }
    ?>
    <?php include_once "partials/header.php"; ?>
    <title>Notes des Techniciens | CFAO Mobility Academy</title>

    <style>
        #kt_customers_table_wrapper td:nth-child(1) {
            position: sticky;
            left: 0;
            background: #edf2f7;
        }
        #kt_customers_table_wrapper th:nth-child(1) { z-index: 2; }
        .card { min-height: 40px; }
        .table-responsive {
            min-height: 500px;
            max-height: 80vh;
            overflow-y: auto;
        }
        .glassmorphism {
            background: rgba(255, 255, 255, 0.85) !important;
            backdrop-filter: blur(18px) !important;
            border-radius: 15px !important;
            border: 1px solid rgba(255, 255, 255, 0.18) !important;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.2) !important;
        }
        .table thead tr { background-color: #edf2f7 !important; }
        .table td { background-color: #ffffff !important; }
        .table td.td-grey { background-color: #f9f9f9 !important; }
        .filter-label {
            font-weight: 600;
            white-space: nowrap;
            margin-bottom: 0;
        }
    </style>

    <!--begin::Body-->
    <?php
    setPageBackground('bg-dashboard', true);
    openBackgroundContainer('', 'id="kt_content"');
    ?>

    <!-- ===== TITRE ===== -->
    <div class="container-xxl pt-5">
        <div class="card mb-3" style="background-color:#FFFFFF !important; border-radius:25px !important; box-shadow:0 8px 32px 0 rgba(31,38,135,0.15); border:1px solid rgba(255,255,255,0.18);">
            <div class="card-body text-center py-4 d-flex align-items-center justify-content-between px-6">
                <div style="flex:1;"></div>
                <h1 class="card-title mb-0 flex-grow-1">
                    <i class="fas fa-chart-bar text-secondary me-3"></i>
                    Résultats des Techniciens par Niveau
                </h1>
                <div style="flex:1;" class="d-flex justify-content-end">
                    <a href="./bilanTest" class="btn btn-dark-secondary btn-sm">
                        <i class="ki-duotone ki-arrow-left fs-2 me-1"><span class="path1"></span><span class="path2"></span></i>
                        Retour au bilan
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== FILTRES ===== -->
    <div class="container-xxl mb-4">
        <div class="card glassmorphism">
            <div class="card-body py-3 px-5">
                <div class="d-flex flex-wrap align-items-center justify-content-center gap-4">

                    <?php if ($isGroupScope): ?>
                    <!-- Filiale -->
                    <div class="d-flex align-items-center gap-2">
                        <label class="filter-label">
                            <i class="bi bi-building text-primary me-1"></i>Filiale
                        </label>
                        <select id="filter-subsidiary" class="form-select form-select-sm w-auto" onchange="applyFilters()">
                            <option value="all">Toutes les filiales</option>
                            <?php foreach ($subsidiaryOptions as $sub): ?>
                                <option value="<?php echo htmlspecialchars($sub); ?>"
                                    <?php if ($selectedSubsidiary === $sub) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($sub); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <!-- Niveau -->
                    <div class="d-flex align-items-center gap-2">
                        <label class="filter-label">
                            <i class="bi bi-bar-chart-steps text-success me-1"></i>Niveau
                        </label>
                        <select id="filter-level" class="form-select form-select-sm w-auto" onchange="applyFilters()">
                            <option value="all">Tous les niveaux</option>
                            <?php foreach ($levelOptions as $lvl): ?>
                                <option value="<?php echo $lvl; ?>"
                                    <?php if ($selectedLevel === $lvl) echo 'selected'; ?>>
                                    <?php echo $lvl; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Département -->
                    <?php if (!empty($departmentOptions)): ?>
                    <div class="d-flex align-items-center gap-2">
                        <label class="filter-label">
                            <i class="bi bi-diagram-3 text-warning me-1"></i>Département
                        </label>
                        <select id="filter-department" class="form-select form-select-sm w-auto" onchange="applyFilters()">
                            <option value="all">Tous les départements</option>
                            <?php foreach ($departmentOptions as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept); ?>"
                                    <?php if ($selectedDepartment === $dept) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($dept); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <!-- Compteur -->
                    <div>
                        <span class="badge badge-light-primary fs-7">
                            <i class="bi bi-people me-1"></i><?php echo count($tech); ?> technicien(s)
                        </span>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <?php
    if (in_array($_SESSION['profile'], ['Super Admin', 'Directeur Groupe', 'Admin', 'Ressource Humaine',
                                        'Directeur Pièce et Service', 'Directeur des Opérations', 'Directeur Général', 'Manager'])):
    ?>

    <div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
        <div class="container-xxl">
            <div class="card glassmorphism">
                <div class="card-body pt-0">
                    <div id="kt_customers_table_wrapper" class="dataTables_wrapper dt-bootstrap4 no-footer">
                        <div class="table-responsive">
                            <table aria-describedby=""
                                class="table align-middle table-bordered fs-6 gy-5 dataTable no-footer"
                                id="kt_customers_table">
                                <thead>
                                    <tr class="text-start text-black fw-bold fs-7 text-uppercase gs-0">
                                        <?php
                                        $colspanTech = ($isGroupScope) ? 3 : (($_SESSION['profile'] == 'Admin') ? 3 : 2);
                                        ?>
                                        <th class="w-100 sorting text-center" colspan="<?php echo $colspanTech; ?>" style="width:125px;"><?php echo $technicienss ?></th>
                                        <th class="w-100 sorting text-center" colspan="3" style="width:155px;"><?php echo $Level ?> <?php echo $junior ?></th>
                                        <th class="w-100 sorting text-center" colspan="3" style="width:155px;"><?php echo $Level ?> <?php echo $senior ?></th>
                                        <th class="w-100 sorting text-center" colspan="3" style="width:155px;"><?php echo $Level ?> <?php echo $expert ?></th>
                                    </tr>
                                    <tr>
                                        <?php if ($isGroupScope): ?>
                                            <th class="min-w-200px sorting text-center text-black fw-bold"><?php echo $prenomsNoms ?></th>
                                            <th class="min-w-150px sorting text-center text-black fw-bold">Filiale</th>
                                            <th class="min-w-150px sorting text-center text-black fw-bold"><?php echo $agence ?></th>
                                        <?php elseif ($_SESSION['profile'] == "Admin"): ?>
                                            <th class="min-w-200px sorting text-center text-black fw-bold"><?php echo $prenomsNoms ?></th>
                                            <th class="min-w-100px sorting text-center text-black fw-bold"><?php echo $Level ?></th>
                                            <th class="min-w-150px sorting text-center text-black fw-bold"><?php echo $agence ?></th>
                                        <?php else: ?>
                                            <th class="min-w-200px sorting text-center text-black fw-bold"><?php echo $prenomsNoms ?></th>
                                            <th class="min-w-200px sorting text-center text-black fw-bold"><?php echo $agence ?></th>
                                        <?php endif; ?>
                                        <?php
                                        $testHeaders = [$qcm_connaissances_tech, $qcm_tache_pro_tech, $qcm_tache_pro_manager];
                                        foreach (['Junior', 'Senior', 'Expert'] as $lvl) {
                                            foreach ($testHeaders as $header) {
                                                echo '<th class="min-w-80px sorting text-center text-black fw-bold" style="width:155px;">' . $header . '</th>';
                                            }
                                        }
                                        ?>
                                    </tr>
                                </thead>
                                <tbody class="fw-semibold text-gray-600" id="table">
                                <?php
                                function countryCodeFromName($name) {
                                    static $map = [
                                        "Côte d'Ivoire" => 'ci', "Cote d'Ivoire" => 'ci', 'Ivory Coast' => 'ci',
                                        'Sénégal' => 'sn', 'Senegal' => 'sn', 'Cameroun' => 'cm', 'Cameroon' => 'cm',
                                        'Gabon' => 'ga', 'Ghana' => 'gh', 'Nigeria' => 'ng', 'Kenya' => 'ke',
                                        'Togo' => 'tg', 'Bénin' => 'bj', 'Benin' => 'bj', 'Congo' => 'cg',
                                        'République du Congo' => 'cg', 'RDC' => 'cd', 'République Démocratique du Congo' => 'cd',
                                        'Mali' => 'ml', 'Burkina Faso' => 'bf', 'Niger' => 'ne', 'Tchad' => 'td',
                                        'Madagascar' => 'mg', 'Mauritanie' => 'mr', 'Maroc' => 'ma', 'Tunisie' => 'tn',
                                        'Algérie' => 'dz', 'Sierra Leone' => 'sl', 'Liberia' => 'lr',
                                        'Ouganda' => 'ug', 'Uganda' => 'ug', 'Tanzanie' => 'tz', 'Tanzania' => 'tz',
                                        'Ethiopie' => 'et', 'Éthiopie' => 'et', 'Ethiopia' => 'et',
                                        'Afrique du Sud' => 'za', 'South Africa' => 'za',
                                        'Guinée' => 'gn', 'Guinea' => 'gn', 'Guinée-Bissau' => 'gw',
                                        'Guinée équatoriale' => 'gq', 'Equatorial Guinea' => 'gq',
                                        'RCA' => 'cf', 'République Centrafricaine' => 'cf', 'Rwanda' => 'rw',
                                    ];
                                    if (isset($map[$name])) return $map[$name];
                                    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT', $name);
                                    return strtolower(substr(preg_replace('/[^A-Za-z]/', '', $ascii ?: $name), 0, 2));
                                }

                                function getFlagWebPath($countryName) {
                                    $code    = countryCodeFromName($countryName);
                                    $baseFs  = __DIR__ . "/../define/flags/4x3/$code";
                                    $baseWeb = "../define/flags/4x3/$code";
                                    foreach (['.svg', '.png', '.jpg', '.webp'] as $ext) {
                                        if (file_exists($baseFs . $ext)) return $baseWeb . $ext;
                                    }
                                    return null;
                                }

                                function findTestN($tests, $userId, $level, $type) {
                                    return $tests->findOne([
                                        '$and' => [[
                                            "user" => $userId, "level" => $level,
                                            "type" => $type, "active" => true,
                                        ]],
                                    ]);
                                }

                                function findResultN($results, $userId, $level, $type, $typeR) {
                                    return $results->findOne([
                                        '$and' => [[
                                            "user" => new MongoDB\BSON\ObjectId($userId),
                                            "level" => $level, "type" => $type,
                                            "typeR" => $typeR, "active" => true,
                                        ]],
                                    ]);
                                }

                                function calcPct($result) {
                                    if (isset($result["score"]) && isset($result["total"]) && $result["total"] > 0) {
                                        return ceil(($result["score"] * 100) / $result["total"]);
                                    }
                                    return null;
                                }

                                function renderNoteCell($percentage) {
                                    if ($percentage === null) {
                                        return '<td class="text-center"><span class="badge badge-light-secondary text-muted fs-8">Pas complété</span></td>';
                                    }
                                    if ($percentage >= 70) {
                                        $class = 'badge-light-success text-success';
                                    } elseif ($percentage >= 50) {
                                        $class = 'badge-light-warning text-warning';
                                    } else {
                                        $class = 'badge-light-danger text-danger';
                                    }
                                    return '<td class="text-center"><span class="badge ' . $class . ' fs-7">' . $percentage . '%</span></td>';
                                }

                                $isGroupScopeInLoop = in_array($_SESSION['profile'], ['Super Admin', 'Directeur Groupe']);

                                for ($i = 0; $i < count($tech); $i++) {
                                    $user = $users->findOne(['_id' => new MongoDB\BSON\ObjectId($tech[$i]), "active" => true]);
                                    if (!$user) continue;

                                    $allocateFacJu   = $allocations->findOne(['$and' => [["user" => new MongoDB\BSON\ObjectId($tech[$i]), "level" => "Junior",  "type" => "Factuel",    "active" => true]]]);
                                    $allocateFacSe   = $allocations->findOne(['$and' => [["user" => new MongoDB\BSON\ObjectId($tech[$i]), "level" => "Senior",  "type" => "Factuel",    "active" => true]]]);
                                    $allocateFacEx   = $allocations->findOne(['$and' => [["user" => new MongoDB\BSON\ObjectId($tech[$i]), "level" => "Expert",  "type" => "Factuel",    "active" => true]]]);
                                    $allocateDeclaJu = $allocations->findOne(['$and' => [["user" => new MongoDB\BSON\ObjectId($tech[$i]), "level" => "Junior",  "type" => "Declaratif", "active" => true]]]);
                                    $allocateDeclaSe = $allocations->findOne(['$and' => [["user" => new MongoDB\BSON\ObjectId($tech[$i]), "level" => "Senior",  "type" => "Declaratif", "active" => true]]]);
                                    $allocateDeclaEx = $allocations->findOne(['$and' => [["user" => new MongoDB\BSON\ObjectId($tech[$i]), "level" => "Expert",  "type" => "Declaratif", "active" => true]]]);

                                    $pFacJu       = calcPct(findResultN($results, $tech[$i], "Junior", "Factuel",    "Technicien"));
                                    $pFacSe       = calcPct(findResultN($results, $tech[$i], "Senior", "Factuel",    "Technicien"));
                                    $pFacEx       = calcPct(findResultN($results, $tech[$i], "Expert", "Factuel",    "Technicien"));
                                    $pDeclaTechJu = calcPct(findResultN($results, $tech[$i], "Junior", "Declaratif", "Techniciens"));
                                    $pDeclaTechSe = calcPct(findResultN($results, $tech[$i], "Senior", "Declaratif", "Techniciens"));
                                    $pDeclaTechEx = calcPct(findResultN($results, $tech[$i], "Expert", "Declaratif", "Techniciens"));
                                    $pDeclaMaJu   = calcPct(findResultN($results, $tech[$i], "Junior", "Declaratif", "Managers"));
                                    $pDeclaMaSe   = calcPct(findResultN($results, $tech[$i], "Senior", "Declaratif", "Managers"));
                                    $pDeclaMaEx   = calcPct(findResultN($results, $tech[$i], "Expert", "Declaratif", "Managers"));
                                ?>
                                <tr class="odd">
                                    <td class="text-center">
                                        <?php echo htmlspecialchars($user["firstName"] . ' ' . $user["lastName"]); ?>
                                    </td>
                                    <?php if ($isGroupScopeInLoop): ?>
                                        <td class="text-center">
                                            <?php
                                            $flagSrc = getFlagWebPath((string)($user['country'] ?? ''));
                                            if ($flagSrc): ?>
                                                <img src="<?php echo htmlspecialchars($flagSrc); ?>"
                                                     alt="<?php echo htmlspecialchars($user['country'] ?? ''); ?>"
                                                     style="width:24px;height:16px;object-fit:cover;border-radius:2px;border:1px solid rgba(0,0,0,.08);vertical-align:middle;margin-right:5px;">
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($user['subsidiary'] ?? ''); ?>
                                        </td>
                                        <td class="text-center"><?php echo htmlspecialchars($user["agency"] ?? ''); ?></td>
                                    <?php elseif ($_SESSION['profile'] == "Admin"): ?>
                                        <td class="text-center py-2 px-1"><?php echo htmlspecialchars($user["level"] ?? ''); ?></td>
                                        <td class="text-center py-2 px-1"><?php echo htmlspecialchars($user["agency"] ?? ''); ?></td>
                                    <?php else: ?>
                                        <td class="text-center"><?php echo htmlspecialchars($user["agency"] ?? ''); ?></td>
                                    <?php endif; ?>

                                    <?php
                                    $levelOrder  = ['Junior' => 1, 'Senior' => 2, 'Expert' => 3];
                                    $userLevelN  = $levelOrder[(string)($user['level'] ?? 'Junior')] ?? 1;
                                    $grey        = '<td class="text-center td-grey"></td>';

                                    // Junior (1)
                                    echo $userLevelN >= 1 ? renderNoteCell($pFacJu)      : $grey;
                                    echo $userLevelN >= 1 ? renderNoteCell($pDeclaTechJu) : $grey;
                                    echo $userLevelN >= 1 ? renderNoteCell($pDeclaMaJu)   : $grey;
                                    // Senior (2)
                                    echo $userLevelN >= 2 ? renderNoteCell($pFacSe)      : $grey;
                                    echo $userLevelN >= 2 ? renderNoteCell($pDeclaTechSe) : $grey;
                                    echo $userLevelN >= 2 ? renderNoteCell($pDeclaMaSe)   : $grey;
                                    // Expert (3)
                                    echo $userLevelN >= 3 ? renderNoteCell($pFacEx)      : $grey;
                                    echo $userLevelN >= 3 ? renderNoteCell($pDeclaTechEx) : $grey;
                                    echo $userLevelN >= 3 ? renderNoteCell($pDeclaMaEx)   : $grey;
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
                                    <ul class="pagination" id="kt_customers_table_paginate"></ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>
    <?php closeBackgroundContainer(); ?>

    <script>
        function applyFilters() {
            var params = new URLSearchParams(window.location.search);
            <?php if ($isGroupScope): ?>
            var subsidiary = document.getElementById('filter-subsidiary');
            if (subsidiary) {
                if (subsidiary.value && subsidiary.value !== 'all') {
                    params.set('subsidiary', subsidiary.value);
                } else {
                    params.delete('subsidiary');
                }
            }
            <?php endif; ?>
            var level = document.getElementById('filter-level');
            if (level) {
                if (level.value && level.value !== 'all') {
                    params.set('level', level.value);
                } else {
                    params.delete('level');
                }
            }
            var department = document.getElementById('filter-department');
            if (department) {
                if (department.value && department.value !== 'all') {
                    params.set('department', department.value);
                } else {
                    params.delete('department');
                }
            }
            window.location.search = params.toString();
        }

        document.addEventListener('DOMContentLoaded', function () {
            var searchInput = document.getElementById('search');
            if (searchInput) {
                searchInput.addEventListener('keyup', function () {
                    var val = this.value.toLowerCase();
                    document.querySelectorAll('#kt_customers_table tbody tr').forEach(function (row) {
                        row.style.display = row.textContent.toLowerCase().includes(val) ? '' : 'none';
                    });
                });
            }
        });
    </script>
    <?php include_once "partials/footer.php"; ?>
<?php } ?>
