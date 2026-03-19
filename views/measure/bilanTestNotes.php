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

    $tech = [];

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
    } elseif ($_SESSION['profile'] == "Manager") {
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
    } elseif ($_SESSION['profile'] == "Super Admin") {
        if (isset($_GET['country'])) {
            $selectedCountry = $_GET['country'];
            $technicians = $users->find([
                '$and' => [["country" => $selectedCountry, "active" => true]],
            ])->toArray();
        } else {
            $technicians = $users->find([
                '$and' => [["active" => true]],
            ])->toArray();
        }
        foreach ($technicians as $techn) {
            if ($techn["profile"] == "Technicien" ||
                ($techn["profile"] == "Manager" && $techn["test"] == true)) {
                array_push($tech, new MongoDB\BSON\ObjectId($techn['_id']));
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
        #kt_customers_table_wrapper th:nth-child(1) {
            z-index: 2;
        }
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
        .table td[style="background-color: #f9f9f9;"] { background-color: #f9f9f9 !important; }
    </style>

    <!--begin::Body-->
    <?php
    setPageBackground('bg-dashboard', true);
    openBackgroundContainer('', 'id="kt_content" data-select2-id="select2-data-kt_content"');
    ?>
        <div class="container-xxl">
            <div class="card shadow-sm mb-5">
                <div class="card-body py-3 d-flex align-items-center justify-content-between">
                    <h1 class="text-dark fw-bold fs-1 m-0">
                        <i class="fas fa-chart-bar text-primary me-3"></i>
                        Notes des Techniciens par Niveau
                    </h1>
                    <a href="./bilanTest" class="btn btn-light-secondary">
                        <i class="ki-duotone ki-arrow-left fs-2 me-1"><span class="path1"></span><span class="path2"></span></i>
                        Retour au bilan
                    </a>
                </div>
            </div>
        </div>

        <?php if ($_SESSION['profile'] == "Super Admin") { ?>
        <div class="container-xxl mb-4">
            <div class="card bg-opacity-50 bg-white border-0 glassmorphism">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center">
                        <label for="country-select" class="form-label fw-bold me-3 mb-0">
                            <i class="bi bi-geo-alt-fill fs-2 me-2 text-primary"></i>Pays
                        </label>
                        <select id="country-select" onchange="applyFilters()" name="country" class="form-select w-auto">
                            <option value="all">Tous les pays</option>
                            <?php
                            $countries = $users->distinct('country', ['active' => true]);
                            foreach ($countries as $countryOption): ?>
                                <option value="<?php echo htmlspecialchars($countryOption); ?>"
                                    <?php if (isset($selectedCountry) && $selectedCountry === $countryOption) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($countryOption); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <?php } ?>

        <?php
        if ($_SESSION['profile'] == "Super Admin" || $_SESSION['profile'] == "Admin" ||
            $_SESSION["profile"] == "Ressource Humaine" || $_SESSION['profile'] == "Directeur Pièce et Service" ||
            $_SESSION['profile'] == "Directeur des Opérations" || $_SESSION['profile'] == "Manager") {
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
                                        <?php if ($_SESSION['profile'] == "Super Admin" && !isset($_GET['country'])) { ?>
                                            <th class="w-100 sorting text-center" colspan="3" style="width:125px;">Techniciens</th>
                                        <?php } elseif ($_SESSION['profile'] == "Admin") { ?>
                                            <th class="w-100 sorting text-center" colspan="3" style="width:125px;"><?php echo $technicienss ?></th>
                                        <?php } else { ?>
                                            <th class="w-100 sorting text-center" colspan="2" style="width:125px;"><?php echo $technicienss ?></th>
                                        <?php } ?>
                                            <th class="w-100 sorting text-center" colspan="3" style="width:155px;"><?php echo $Level ?> <?php echo $junior ?></th>
                                            <th class="w-100 sorting text-center" colspan="3" style="width:155px;"><?php echo $Level ?> <?php echo $senior ?></th>
                                            <th class="w-100 sorting text-center" colspan="3" style="width:155px;"><?php echo $Level ?> <?php echo $expert ?></th>
                                        </tr>
                                        <tr>
                                        <?php if ($_SESSION['profile'] == "Super Admin") { ?>
                                            <th class="min-w-200px sorting text-center text-black fw-bold" style="width:155px;"><?php echo $prenomsNoms ?></th>
                                            <th class="min-w-200px sorting text-center text-black fw-bold" style="width:155px;"><?php echo $manager ?></th>
                                            <?php if (!isset($_GET['country'])) { ?>
                                            <th class="w-100 sorting text-center text-black fw-bold" style="width:155px;"><?php echo $pays ?></th>
                                            <?php } ?>
                                        <?php } elseif ($_SESSION['profile'] == "Admin") { ?>
                                            <th class="min-w-200px sorting text-center text-black fw-bold" style="width:155px;"><?php echo $prenomsNoms ?></th>
                                            <th class="min-w-100px sorting text-center text-black fw-bold" style="width:100px;"><?php echo $Level ?></th>
                                            <th class="min-w-150px sorting text-center text-black fw-bold" style="width:150px;"><?php echo $agence ?></th>
                                        <?php } else { ?>
                                            <th class="min-w-200px sorting text-center text-black fw-bold" style="width:155px;"><?php echo $prenomsNoms ?></th>
                                            <th class="min-w-200px sorting text-center text-black fw-bold" style="width:155px;"><?php echo $agence ?></th>
                                        <?php } ?>
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
                                    function findTestN($tests, $userId, $level, $type) {
                                        return $tests->findOne([
                                            '$and' => [[
                                                "user" => $userId,
                                                "level" => $level,
                                                "type" => $type,
                                                "active" => true,
                                            ]],
                                        ]);
                                    }

                                    function findResultN($results, $userId, $level, $type, $typeR) {
                                        return $results->findOne([
                                            '$and' => [[
                                                "user" => new MongoDB\BSON\ObjectId($userId),
                                                "level" => $level,
                                                "type" => $type,
                                                "typeR" => $typeR,
                                                "active" => true,
                                            ]],
                                        ]);
                                    }

                                    function calcPct($result) {
                                        if (isset($result) && isset($result["score"]) && isset($result["total"]) && $result["total"] > 0) {
                                            return ceil(($result["score"] * 100) / $result["total"]);
                                        }
                                        return null;
                                    }

                                    function renderNoteCell($percentage) {
                                        if ($percentage === null) {
                                            return '<td class="text-center" style="background-color: #f9f9f9;"></td>';
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

                                    for ($i = 0; $i < count($tech); $i++) {
                                        $user = $users->findOne([
                                            '$and' => [[
                                                "_id" => new MongoDB\BSON\ObjectId($tech[$i]),
                                                "active" => true
                                            ]],
                                        ]);
                                        if (!$user) continue;

                                        $manager_obj = null;
                                        if (!empty($user['manager'])) {
                                            $manager_obj = $users->findOne([
                                                '$and' => [
                                                    ["_id" => new MongoDB\BSON\ObjectId($user['manager'])],
                                                    ["active" => true]
                                                ],
                                            ]);
                                        }

                                        $allocateFacJu  = $allocations->findOne(['$and' => [["user" => new MongoDB\BSON\ObjectId($tech[$i]), "level" => "Junior",  "type" => "Factuel",    "active" => true]]]);
                                        $allocateFacSe  = $allocations->findOne(['$and' => [["user" => new MongoDB\BSON\ObjectId($tech[$i]), "level" => "Senior",  "type" => "Factuel",    "active" => true]]]);
                                        $allocateFacEx  = $allocations->findOne(['$and' => [["user" => new MongoDB\BSON\ObjectId($tech[$i]), "level" => "Expert",  "type" => "Factuel",    "active" => true]]]);
                                        $allocateDeclaJu = $allocations->findOne(['$and' => [["user" => new MongoDB\BSON\ObjectId($tech[$i]), "level" => "Junior", "type" => "Declaratif", "active" => true]]]);
                                        $allocateDeclaSe = $allocations->findOne(['$and' => [["user" => new MongoDB\BSON\ObjectId($tech[$i]), "level" => "Senior", "type" => "Declaratif", "active" => true]]]);
                                        $allocateDeclaEx = $allocations->findOne(['$and' => [["user" => new MongoDB\BSON\ObjectId($tech[$i]), "level" => "Expert", "type" => "Declaratif", "active" => true]]]);

                                        $pFacJu  = calcPct(findResultN($results, $tech[$i], "Junior", "Factuel",    "Technicien"));
                                        $pFacSe  = calcPct(findResultN($results, $tech[$i], "Senior", "Factuel",    "Technicien"));
                                        $pFacEx  = calcPct(findResultN($results, $tech[$i], "Expert", "Factuel",    "Technicien"));

                                        $pDeclaTechJu = calcPct(findResultN($results, $tech[$i], "Junior", "Declaratif", "Techniciens"));
                                        $pDeclaTechSe = calcPct(findResultN($results, $tech[$i], "Senior", "Declaratif", "Techniciens"));
                                        $pDeclaTechEx = calcPct(findResultN($results, $tech[$i], "Expert", "Declaratif", "Techniciens"));

                                        $pDeclaMaJu = calcPct(findResultN($results, $tech[$i], "Junior", "Declaratif", "Managers"));
                                        $pDeclaMaSe = calcPct(findResultN($results, $tech[$i], "Senior", "Declaratif", "Managers"));
                                        $pDeclaMaEx = calcPct(findResultN($results, $tech[$i], "Expert", "Declaratif", "Managers"));
                                    ?>
                                    <tr class="odd">
                                        <td class="text-center">
                                            <?php echo htmlspecialchars($user["firstName"] . ' ' . $user["lastName"]); ?>
                                        </td>

                                        <?php if ($_SESSION['profile'] == "Super Admin") { ?>
                                            <td class="text-center">
                                                <?php if (!empty($user['manager']) && isset($manager_obj)) {
                                                    echo htmlspecialchars($manager_obj["firstName"] . ' ' . $manager_obj["lastName"]);
                                                } ?>
                                            </td>
                                            <?php if (!isset($_GET['country'])) { ?>
                                            <td class="text-center"><?php echo htmlspecialchars($user["country"] ?? ''); ?></td>
                                            <?php } ?>
                                        <?php } elseif ($_SESSION['profile'] == "Admin") { ?>
                                            <td class="text-center py-2 px-1"><?php echo htmlspecialchars($user["level"] ?? ''); ?></td>
                                            <td class="text-center py-2 px-1"><?php echo htmlspecialchars($user["agency"] ?? ''); ?></td>
                                        <?php } else { ?>
                                            <td class="text-center"><?php echo htmlspecialchars($user["agency"] ?? ''); ?></td>
                                        <?php } ?>

                                        <?php
                                        // Junior
                                        echo renderNoteCell($pFacJu);
                                        echo isset($allocateDeclaJu) ? renderNoteCell($pDeclaTechJu) : '<td class="text-center" style="background-color:#f9f9f9;"></td>';
                                        echo isset($allocateDeclaJu) ? renderNoteCell($pDeclaMaJu)  : '<td class="text-center" style="background-color:#f9f9f9;"></td>';

                                        // Senior
                                        if (isset($allocateFacSe) && isset($allocateDeclaSe)) {
                                            echo renderNoteCell($pFacSe);
                                            echo renderNoteCell($pDeclaTechSe);
                                            echo renderNoteCell($pDeclaMaSe);
                                        } else {
                                            echo '<td class="text-center" style="background-color:#f9f9f9;"></td>';
                                            echo '<td class="text-center" style="background-color:#f9f9f9;"></td>';
                                            echo '<td class="text-center" style="background-color:#f9f9f9;"></td>';
                                        }

                                        // Expert
                                        if (isset($allocateFacEx) && isset($allocateDeclaEx)) {
                                            echo renderNoteCell($pFacEx);
                                            echo renderNoteCell($pDeclaTechEx);
                                            echo renderNoteCell($pDeclaMaEx);
                                        } else {
                                            echo '<td class="text-center" style="background-color:#f9f9f9;"></td>';
                                            echo '<td class="text-center" style="background-color:#f9f9f9;"></td>';
                                            echo '<td class="text-center" style="background-color:#f9f9f9;"></td>';
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
                                        <ul class="pagination" id="kt_customers_table_paginate"></ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php } ?>
    <?php closeBackgroundContainer(); ?>

    <?php if ($_SESSION['profile'] === "Super Admin") { ?>
    <script>
        function applyFilters() {
            var country = document.querySelector("#country-select").value;
            var urlParams = new URLSearchParams(window.location.search);
            if (country && country !== "all") {
                urlParams.set('country', country);
            } else {
                urlParams.delete('country');
            }
            window.location.search = urlParams.toString();
        }
    </script>
    <?php } ?>

    <script>
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
