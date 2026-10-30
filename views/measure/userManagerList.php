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
    $exams = $academy->exams;
    $quizzes = $academy->quizzes;
    $allocations = $academy->allocations;

    $id = $_SESSION["id"];

    $managerDoc = $users->findOne([
        '$and' => [
            [
                "_id" => new MongoDB\BSON\ObjectId($id),
                "active" => true,
            ],
        ],
    ]);
    ?>
<?php include_once "partials/header.php"; ?>
<!--begin::Title-->
<title><?php echo $list_evalue_collab ?> | CFAO Mobility Academy</title>
<!--end::Title-->

<?php include_once "../partials/background-manager.php"; ?>
<?php setPageBackground("bg-dashboard"); ?>

<?php
    $umlEn = isset($_SESSION['lang']) && $_SESSION['lang'] === 'EN';
    $umlTxt = $umlEn ? [
        'subtitle'   => 'Manager assessment',
        'todo'       => 'tests to take',
        'todo_one'   => 'test to take',
        'progress'   => 'tests in progress',
        'progress_one' => 'test in progress',
        'done'       => 'tests completed',
        'done_one'   => 'test completed',
        'search'     => 'Search a team member…',
        'evaluate'   => 'Evaluate',
        'continue'   => 'Resume',
        'completed'  => 'evaluations completed',
        'no_match'   => 'No team member matches your search.',
        'empty'      => 'No team member is assigned to you.',
        'not_found'  => 'Manager not found.',
        'open'       => 'Open the evaluation',
    ] : [
        'subtitle'   => 'Évaluation manager',
        'todo'       => 'tests à réaliser',
        'todo_one'   => 'test à réaliser',
        'progress'   => 'tests en cours',
        'progress_one' => 'test en cours',
        'done'       => 'tests terminés',
        'done_one'   => 'test terminé',
        'search'     => 'Rechercher un collaborateur…',
        'evaluate'   => 'Évaluer',
        'continue'   => 'Reprendre',
        'completed'  => 'évaluations terminées',
        'no_match'   => 'Aucun collaborateur ne correspond à la recherche.',
        'empty'      => 'Aucun collaborateur ne vous est rattaché.',
        'not_found'  => 'Manager introuvable.',
        'open'       => "Ouvrir l'évaluation",
    ];

    $umlLevels = ['Junior' => $junior, 'Senior' => $senior, 'Expert' => $expert];
    $umlRows = [];
    $umlCounts = ['todo' => 0, 'progress' => 0, 'done' => 0];
    $umlError = '';

    if (!$managerDoc) {
        $umlError = $umlTxt['not_found'];
    } else {
        $collaborators = [];
        if (isset($managerDoc->users)) {
            $collaborators = ($managerDoc->users instanceof MongoDB\Model\BSONArray)
                ? $managerDoc->users->getArrayCopy()
                : (array) $managerDoc->users;
        }

        foreach ($collaborators as $collaborator) {
            $user = $users->findOne([
                '$and' => [
                    [
                        "_id" => new MongoDB\BSON\ObjectId($collaborator),
                        "active" => true,
                    ],
                ],
            ]);
            if (!$user) {
                continue;
            }

            $cells = [];
            $available = 0;
            $doneCount = 0;
            $pending = 0;
            foreach ($umlLevels as $levelKey => $levelLabel) {
                $allocate = $allocations->findOne([
                    '$and' => [
                        ["user" => $collaborator],
                        ["type" => "Declaratif"],
                        ["level" => $levelKey],
                    ],
                ]);
                if (!$allocate) {
                    $cells[$levelKey] = ['state' => 'na', 'url' => ''];
                    continue;
                }
                $available++;
                $exam = $exams->findOne([
                    '$and' => [
                        ["manager" => new MongoDB\BSON\ObjectId($_SESSION["id"])],
                        ["test" => new MongoDB\BSON\ObjectId($allocate["test"])],
                        ["active" => true],
                    ],
                ]);
                if ($exam) {
                    $state = 'progress';
                } elseif ($allocate->activeManager == false) {
                    $state = 'todo';
                } else {
                    $state = 'done';
                }
                $umlCounts[$state]++;
                if ($state === 'done') {
                    $doneCount++;
                } else {
                    $pending++;
                }
                $cells[$levelKey] = [
                    'state' => $state,
                    'url'   => './userEvaluation?test=' . urlencode((string) $allocate["test"])
                        . '&level=' . urlencode((string) $allocate["level"])
                        . '&id=' . urlencode((string) $_SESSION["id"])
                        . '&user=' . urlencode((string) $user["_id"]),
                ];
            }

            $fullName = trim($user->firstName . ' ' . $user->lastName);
            $initials = '';
            foreach ([$user->firstName, $user->lastName] as $part) {
                $part = trim((string) $part);
                if ($part !== '') {
                    $initials .= function_exists('mb_substr') ? mb_strtoupper(mb_substr($part, 0, 1)) : strtoupper(substr($part, 0, 1));
                }
            }

            $umlRows[] = [
                'name'      => $fullName,
                'initials'  => $initials,
                'cells'     => $cells,
                'available' => $available,
                'done'      => $doneCount,
                'pending'   => $pending,
            ];
        }

        // Collaborateurs avec des évaluations à faire en premier, puis ordre alphabétique
        usort($umlRows, function ($a, $b) {
            if (($a['pending'] > 0) !== ($b['pending'] > 0)) {
                return $a['pending'] > 0 ? -1 : 1;
            }
            return strcasecmp($a['name'], $b['name']);
        });

        if (empty($umlRows)) {
            $umlError = $umlTxt['empty'];
        }
    }
?>
<style>
    .page-background-container.uml-page-scope { overflow: visible !important; height: auto !important; min-height: calc(100vh - 80px); }
    .page-background-container.uml-page-scope .page-content-container { overflow: visible; }
    .uml-scope { --uml-ink: #101828; --uml-muted: #667085; --uml-line: #eaecf0; --uml-head: #f9fafb;
        --uml-blue: #175cd3; --uml-blue-bg: #eff8ff; --uml-amber: #b54708; --uml-amber-bg: #fffaeb;
        --uml-green: #067647; --uml-green-bg: #ecfdf3; --uml-grey-bg: #f2f4f7; --uml-brand: #d71920;
        font-family: Inter, system-ui, -apple-system, "Segoe UI", sans-serif; color: var(--uml-ink); }
    /* Même découpage en cartes que bilanTestNotes : titre / barre / tableau */
    .uml-title-card { background: #fff !important; border-radius: 25px !important;
        box-shadow: 0 8px 32px 0 rgba(31,38,135,.15); border: 1px solid rgba(255,255,255,.18); }
    .uml-glass { background: rgba(255,255,255,.85) !important; backdrop-filter: blur(18px);
        border-radius: 15px !important; border: 1px solid rgba(255,255,255,.18) !important;
        box-shadow: 0 8px 32px 0 rgba(31,38,135,.2) !important; }
    .uml-glass .table-responsive { background: #fff; border: 1px solid var(--uml-line); border-radius: 12px; }
    .uml-title { margin: 0; font-size: 1.9rem; font-weight: 700; letter-spacing: -.01em; color: var(--uml-ink); }
    .uml-subtitle { margin: 6px 0 0; font-size: 1.1rem; color: var(--uml-muted); }
    .uml-search { position: relative; width: 300px; max-width: 100%; }
    .uml-search i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--uml-muted); font-size: .9rem; }
    .uml-search input { width: 100%; height: 46px; padding: 0 14px 0 38px; border: 1px solid #d0d5dd; border-radius: 10px;
        font-size: 1.05rem; color: var(--uml-ink); background: #fff; outline: none; transition: border-color .15s, box-shadow .15s; }
    .uml-search input:focus { border-color: #84adff; box-shadow: 0 0 0 4px rgba(41,112,255,.12); }
    .uml-kpi { display: flex; align-items: baseline; gap: 8px; padding: 6px 16px; background: #fff;
        border: 1px solid var(--uml-line); border-radius: 10px; }
    .uml-kpi-label { font-size: 1.05rem; font-weight: 600; color: var(--uml-ink); }
    .uml-kpi-value { font-size: 1.8rem; font-weight: 800; line-height: 1; font-variant-numeric: tabular-nums; }
    .uml-table { width: 100%; border-collapse: separate; border-spacing: 0; margin: 0; }
    .uml-table thead th { background: var(--uml-head); padding: 14px 20px; font-size: .9rem; font-weight: 700;
        color: var(--uml-muted); text-transform: uppercase; letter-spacing: .04em; border-bottom: 1px solid var(--uml-line); white-space: nowrap; }
    .uml-table thead th.uml-lvl { text-align: center; width: 18%; }
    .uml-table tbody td { padding: 18px 20px; border-bottom: 1px solid var(--uml-line); vertical-align: middle; }
    .uml-table tbody tr:last-child td { border-bottom: 0; }
    .uml-table tbody tr:hover td { background: #fcfcfd; }
    .uml-table td.uml-lvl { text-align: center; }
    .uml-person { display: flex; align-items: center; gap: 12px; min-width: 460px; }
    .uml-avatar { width: 48px; height: 48px; border-radius: 50%; flex: none; display: flex; align-items: center; justify-content: center;
        background: #fef3f2; color: var(--uml-brand); font-weight: 700; font-size: 1.05rem; }
    .uml-name { white-space: nowrap; font-weight: 600; font-size: 1.15rem; color: var(--uml-ink); }
    .uml-progress { display: flex; align-items: center; gap: 8px; margin-left: auto; padding-left: 24px; font-size: .95rem; color: var(--uml-muted); white-space: nowrap; }
    .uml-bar { width: 90px; height: 8px; border-radius: 99px; background: var(--uml-grey-bg); overflow: hidden; }
    .uml-bar span { display: block; height: 100%; border-radius: 99px; background: #17b26a; }
    .uml-btn { display: inline-flex; align-items: center; gap: 6px; height: 40px; padding: 0 18px; border-radius: 8px;
        font-size: 1.05rem; font-weight: 600; text-decoration: none; white-space: nowrap; transition: background .15s, box-shadow .15s; }
    .uml-btn:focus-visible { outline: none; box-shadow: 0 0 0 4px rgba(41,112,255,.24); }
    .uml-btn-primary { background: var(--uml-blue); color: #fff; box-shadow: 0 1px 2px rgba(16,24,40,.08); }
    .uml-btn-primary:hover { background: #1849a9; color: #fff; }
    .uml-btn-resume { background: var(--uml-amber-bg); color: var(--uml-amber); border: 1px solid #fedf89; }
    .uml-btn-resume:hover { background: #fef0c7; color: var(--uml-amber); }
    .uml-pill { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 99px;
        font-size: 1rem; font-weight: 500; white-space: nowrap; }
    .uml-btn i, .uml-pill i { color: inherit; }
    .uml-pill-done { background: var(--uml-green-bg); color: var(--uml-green); }
    .uml-pill-na { background: var(--uml-grey-bg); color: var(--uml-muted); }
    .uml-empty { padding: 48px 24px; text-align: center; color: var(--uml-muted); font-size: 1.1rem; }
    .uml-empty i { display: block; font-size: 1.75rem; margin-bottom: 10px; color: #98a2b3; }
    @media (max-width: 767.98px) {
        .uml-search { width: 100%; }
        .uml-kpi-value { font-size: 1.5rem; }
        .uml-table thead th, .uml-table tbody td { padding: 12px 14px; }
    }
</style>

<!--begin::Content-->
<?php openBackgroundContainer('uml-page-scope'); ?>
    <!--begin::Titre-->
    <div class="container-xxl pt-5 uml-scope">
        <div class="card mb-3 uml-title-card">
            <div class="card-body text-center py-4 px-6">
                <h1 class="uml-title">
                    <i class="fas fa-user-check text-secondary me-3" aria-hidden="true"></i><?php echo $list_evalue_collab ?>
                </h1>
                <p class="uml-subtitle"><?php echo htmlspecialchars($umlTxt['subtitle']) ?> · <?php echo $maitrise_tache_pro ?></p>
            </div>
        </div>
    </div>
    <!--end::Titre-->

    <!--begin::Recherche + compteurs-->
    <div class="container-xxl mb-4 uml-scope">
        <div class="card uml-glass">
            <div class="card-body py-3 px-5">
                <div class="d-flex flex-wrap align-items-center justify-content-center gap-4">
                    <?php if (count($umlRows) > 0) { ?>
                    <div class="uml-search">
                        <i class="fas fa-search" aria-hidden="true"></i>
                        <input type="search" id="umlSearch" placeholder="<?php echo htmlspecialchars($umlTxt['search']) ?>"
                            aria-label="<?php echo htmlspecialchars($umlTxt['search']) ?>" autocomplete="off">
                    </div>
                    <?php } ?>
                    <?php foreach (['todo' => '#2e90fa', 'progress' => '#f79009', 'done' => '#17b26a'] as $kpiKey => $kpiColor) { ?>
                    <div class="uml-kpi">
                        <span class="uml-kpi-value" style="color: <?php echo $kpiColor ?>"><?php echo $umlCounts[$kpiKey] ?></span>
                        <span class="uml-kpi-label"><?php echo $umlCounts[$kpiKey] > 1 ? $umlTxt[$kpiKey] : $umlTxt[$kpiKey . '_one'] ?></span>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
    <!--end::Recherche + compteurs-->

    <!--begin::Post-->
    <div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
        <div class="container-xxl uml-scope">
            <div class="card uml-glass mb-5">
                <div class="card-body p-4">
                <?php if ($umlError !== '') { ?>
                    <div class="uml-empty">
                        <i class="fas fa-user-friends" aria-hidden="true"></i>
                        <?php echo htmlspecialchars($umlError) ?>
                    </div>
                <?php } else { ?>
                <div class="table-responsive">
                    <table class="uml-table" id="umlTable">
                        <thead>
                            <tr>
                                <th scope="col"><?php echo $technicien ?></th>
                                <?php foreach ($umlLevels as $levelLabel) { ?>
                                    <th scope="col" class="uml-lvl"><?php echo $level ?> <?php echo $levelLabel ?></th>
                                <?php } ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($umlRows as $row) {
                                $pct = $row['available'] > 0 ? round($row['done'] * 100 / $row['available']) : 0;
                            ?>
                            <tr data-name="<?php echo htmlspecialchars(function_exists('mb_strtolower') ? mb_strtolower($row['name']) : strtolower($row['name'])) ?>">
                                <td>
                                    <div class="uml-person">
                                        <div class="uml-avatar" aria-hidden="true"><?php echo htmlspecialchars($row['initials']) ?></div>
                                        <div class="uml-name"><?php echo htmlspecialchars($row['name']) ?></div>
                                        <?php if ($row['available'] > 0) { ?>
                                        <div class="uml-progress">
                                            <span class="uml-bar"><span style="width: <?php echo $pct ?>%"></span></span>
                                            <?php echo $row['done'] ?>/<?php echo $row['available'] ?> <?php echo $umlTxt['completed'] ?>
                                        </div>
                                        <?php } ?>
                                    </div>
                                </td>
                                <?php foreach ($umlLevels as $levelKey => $levelLabel) {
                                    $cell = $row['cells'][$levelKey];
                                    $aria = htmlspecialchars($umlTxt['open'] . ' – ' . $row['name'] . ' – ' . $levelLabel);
                                ?>
                                <td class="uml-lvl">
                                    <?php if ($cell['state'] === 'todo') { ?>
                                        <a href="<?php echo htmlspecialchars($cell['url']) ?>" class="uml-btn uml-btn-primary" aria-label="<?php echo $aria ?>">
                                            <?php echo $umlTxt['evaluate'] ?> <i class="fas fa-arrow-right " aria-hidden="true"></i>
                                        </a>
                                    <?php } elseif ($cell['state'] === 'progress') { ?>
                                        <a href="<?php echo htmlspecialchars($cell['url']) ?>" class="uml-btn uml-btn-resume" aria-label="<?php echo $aria ?>">
                                            <i class="fas fa-hourglass-half " aria-hidden="true"></i> <?php echo $umlTxt['continue'] ?>
                                        </a>
                                    <?php } elseif ($cell['state'] === 'done') { ?>
                                        <span class="uml-pill uml-pill-done">
                                            <i class="fas fa-check-circle " aria-hidden="true"></i> <?php echo $effectue ?>
                                        </span>
                                    <?php } else { ?>
                                        <span class="uml-pill uml-pill-na">
                                            <i class="fas fa-minus " aria-hidden="true"></i> <?php echo $non_disponible ?>
                                        </span>
                                    <?php } ?>
                                </td>
                                <?php } ?>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                    <div class="uml-empty" id="umlNoMatch" style="display:none">
                        <i class="fas fa-search" aria-hidden="true"></i>
                        <?php echo htmlspecialchars($umlTxt['no_match']) ?>
                    </div>
                </div>
                <?php } ?>
                </div>
            </div>
        </div>
    </div>
    <!--end::Post-->
<?php closeBackgroundContainer(); ?>
<!--end::Content-->
<script>
    (function () {
        var input = document.getElementById('umlSearch');
        if (!input) { return; }
        var rows = document.querySelectorAll('#umlTable tbody tr');
        var noMatch = document.getElementById('umlNoMatch');
        input.addEventListener('input', function () {
            var q = input.value.trim().toLowerCase();
            var visible = 0;
            for (var i = 0; i < rows.length; i++) {
                var show = q === '' || rows[i].getAttribute('data-name').indexOf(q) !== -1;
                rows[i].style.display = show ? '' : 'none';
                if (show) { visible++; }
            }
            noMatch.style.display = visible === 0 ? '' : 'none';
        });
    })();
</script>
<?php include_once "partials/footer.php"; ?>
<?php
} ?>
