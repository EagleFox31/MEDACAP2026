<?php
// Add this function at the top, after includes
function highlightKeywords($text, $keywords) {
    foreach ($keywords as $keyword) {
        $pattern = '/\b' . preg_quote($keyword, '/') . '\b/i';
        $text = preg_replace($pattern, '<span class="highlight">$0</span>', $text);
    }
    return $text;
}
// Define keywords for intro and guide
$introKeywords = ['QCM', 'Question à Choix Multiples', 'connaissances', 'tâches professionnelles', 'compétences', 'Junior', 'Senior', 'Expert', 'maîtrise', 'tâches professionnelles'];
$guideKeywords = ['QCM', 'Continuer', 'Navigation', 'Précédent/Suivant', 'questions', 'Réponse', 'option de réponse', 'réponses', 'progrès', 'questions répondues', 'question actuelle', 'Finalisation', 'questionnaire', 'Finaliser', 'évaluation', 'Évaluer', 'Notation', 'réponse appropriée', 'répondues'];
session_start();
include_once "./includes/language.php";
require_once "../../vendor/autoload.php";
include_once "./components/ui/search-bar.php";
require_once "./components/ui/title-section.php";
include_once "./includes/background-manager.php";
require_once "./components/ui/filterHelpers.php";
require_once "./components/ui/render-level-badge.php";
setPageBackground("bg-dashboard");
if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
}
$conn = new MongoDB\Client("mongodb://localhost:27017");
$academy = $conn->academy;
$users = $academy->users;
$allocations = $academy->allocations;
$exams = $academy->exams;
$filter = $_GET['filter_value'] ?? null;
$testTypeFilter = $_GET['test_type'] ?? 'all';
$levelFilter = $_GET['level'] ?? 'all';
$factuelCards = [];
$declaratifCards = [];
$collaboratorCards = [];
$levelData = [
    'Junior' => [1, $junior],
    'Senior' => [2, $senior],
    'Expert' => [3, $expert]
];
$testTypes = [
    'Factuel' => [
        'label' => 'QCM de mesure théorique',
        'url' => 'quiz',
        'icon' => 'bi bi-clipboard-data'
    ],
    'Declaratif' => [
        'label' => 'QCM de mesure pratique',
        'url' => 'quiz',
        'icon' => 'bi bi-journal-text'
    ]
];
$id = $_SESSION["id"];
$currentUser = $users->findOne([
    '$and' => [
        ["_id" => new MongoDB\BSON\ObjectId($id)],
        ["active" => true],
    ],
]);
$hasPersonalExperience = $exams->count(["user" => new MongoDB\BSON\ObjectId($id)]) > 0;
$guidePersonal = $hasPersonalExperience ? 'false' : 'true';
$isTechnician = isset($_SESSION['profile']) && strtolower($_SESSION['profile']) === 'technicien';
$canTakeTests = $isTechnician || (isset($currentUser->test) && $currentUser->test === true);
// LOGIQUE CORRIGÉE : Les techniciens ne voient PAS les collaborateurs
$showPersonal = $canTakeTests;
$showCollaborators = !$isTechnician && isset($currentUser->users) && !empty($currentUser->users);
$showSubsectionTitle = $showPersonal && $showCollaborators && !$isTechnician;
$hasEvalExperience = false;
$guideEval = 'true';
if ($showCollaborators) {
    $hasEvalExperience = $exams->count(["manager" => new MongoDB\BSON\ObjectId($id)]) > 0;
    $guideEval = $hasEvalExperience ? 'false' : 'true';
}
if ($showPersonal) {
    foreach ($testTypes as $type => $typeData) {
        if ($testTypeFilter !== 'all' && $type !== $testTypeFilter) continue;
        foreach ($levelData as $levelKey => $data) {
            $levelNum = $data[0];
            $levelLabel = $data[1];
            if ($levelFilter !== 'all' && $levelKey !== $levelFilter) continue;
            $allocate = $allocations->findOne([
                '$and' => [
                    ["user" => new MongoDB\BSON\ObjectId($id)],
                    ["type" => $type],
                    ["level" => $levelKey],
                ],
            ]);
            if (!$allocate) continue;
            $isAvailable = isset($allocate['activeTest']) && $allocate['activeTest'] === true;
            $exam = null;
            $completedExam = null;
            $progress = 0;
            $statusHtml = '';
            $linkParams = '?test=' . $allocate["test"] . '&level=' . $allocate["level"] . '&type=' . $type . '&id=' . $id . '&guide=' . $guidePersonal;
            $linkHref = './' . $typeData['url'] . $linkParams;
            $progressBarHtml = '<div class="d-flex flex-column align-items-center w-100 gap-1"><span class="text-center w-100 fw-bold">Progression (' . (int)$progress . '%)</span><div class="custom-progress mt-1 w-100"><div class="custom-progress-fill" style="width: ' . (int)$progress . '%;"></div></div></div>';
            if (!$isAvailable) {
                $statusHtml = '<div class="d-flex flex-column align-items-center w-100 gap-1"><span class="text-center w-100 fw-bold">Progression (0%)</span><div class="custom-progress mt-1 w-100"><div class="custom-progress-fill" style="width: 0%;"></div></div><span class="badge bg-secondary fs-6 mt-1 w-100 fw-bold">' . $non_disponible . '</span></div>';
            } else {
                $exam = $exams->findOne([
                    '$and' => [
                        ["user" => new MongoDB\BSON\ObjectId($id)],
                        ["test" => new MongoDB\BSON\ObjectId($allocate["test"])],
                        ["active" => true],
                    ],
                ], ['sort' => ['_id' => -1]]);
                $completedExam = $exams->findOne([
                    '$and' => [
                        ["user" => new MongoDB\BSON\ObjectId($id)],
                        ["test" => new MongoDB\BSON\ObjectId($allocate["test"])],
                        ["active" => false],
                    ],
                ], ['sort' => ['_id' => -1]]);
                if ($completedExam) {
                    $progress = 100;
                    $statusHtml = '<div class="d-flex flex-column align-items-center w-100 gap-1"><span class="text-center w-100 fw-bold">Progression (100%)</span><div class="custom-progress mt-1 w-100"><div class="custom-progress-fill" style="width: 100%;"></div></div><span class="badge bg-success fs-6 mt-1 w-100 fw-bold">' . $effectue . '</span></div>';
                } elseif ($exam) {
                    $questions = isset($exam['questions']) && $exam['questions'] instanceof \MongoDB\Model\BSONArray ? iterator_to_array($exam['questions']) : (array) ($exam['questions'] ?? []);
                    if (!is_array($questions)) $questions = [];
                    $answers = isset($exam['answers']) && $exam['answers'] instanceof \MongoDB\Model\BSONArray ? iterator_to_array($exam['answers']) : (array) ($exam['answers'] ?? []);
                    if (!is_array($answers)) $answers = [];
                    $totalQuestions = count($questions);
                    $answeredQuestions = array_filter($answers, function($ans) { return $ans !== null && $ans !== '' && $ans !== 'null'; });
                    $progress = 0;
                    if (is_int($totalQuestions) && $totalQuestions > 0) {
                        $answeredCount = count($answeredQuestions);
                        $ratio = $answeredCount / $totalQuestions;
                        if (is_numeric($ratio)) {
                            $progress = round($ratio * 100);
                        }
                    }
                    $progress = max(0, min(100, $progress));
                    $statusHtml = '<div class="d-flex flex-column align-items-center w-100 gap-1"><span class="text-center w-100 fw-bold">Progression (' . (int)$progress . '%)</span><div class="custom-progress mt-1 w-100"><div class="custom-progress-fill" style="width: ' . (int)$progress . '%;"></div></div><a href="' . $linkHref . '" class="btn btn-primary btn-sm w-100 fw-bold mt-1" title="Continuer le QCM">Continuer</a></div>';
                } else {
                    $statusHtml = '<div class="d-flex flex-column align-items-center w-100 gap-1"><span class="text-center w-100 fw-bold">Progression (0%)</span><div class="custom-progress mt-1 w-100"><div class="custom-progress-fill" style="width: 0%;"></div></div><a href="' . $linkHref . '" class="btn btn-primary btn-sm w-100 fw-bold mt-1" title="Commencer le QCM">Commencer le QCM</a></div>';
                    if (isset($allocate['active']) && $allocate['active'] == false) {
                        $statusHtml = '<div class="d-flex flex-column align-items-center w-100 gap-1"><span class="text-center w-100 fw-bold">Progression (0%)</span><div class="custom-progress mt-1 w-100"><div class="custom-progress-fill" style="width: 0%;"></div></div><a href="' . $linkHref . '" class="btn btn-primary btn-sm w-100 fw-bold mt-1" title="Compléter le QCM">' . $completer_par_vous . '</a></div>';
                    }
                }
            }
            $levelComponent = renderLevelBadge($levelNum, $levelLabel);
            $card = [
                'test' => $typeData['label'],
                'icon' => $typeData['icon'],
                'level' => $levelComponent,
                'status' => $statusHtml,
                'type' => $type,
                'isAvailable' => $isAvailable,
                'progress' => $progress
            ];
            if ($type === 'Factuel') {
                $factuelCards[] = $card;
            } else {
                $declaratifCards[] = $card;
            }
        }
    }
}
// LOGIQUE CORRIGÉE : Ne charger les collaborateurs que si l'utilisateur n'est pas technicien
if ($showCollaborators) {
    $collaborators = ($currentUser->users instanceof MongoDB\Model\BSONArray)
        ? $currentUser->users->getArrayCopy()
        : (array) $currentUser->users;
    foreach ($collaborators as $collaborator) {
        $user = $users->findOne([
            '$and' => [
                ["_id" => new MongoDB\BSON\ObjectId($collaborator)],
                ["active" => true],
            ],
        ]);
        if (!$user) continue;
        $userName = htmlspecialchars($user->firstName . ' ' . $user->lastName);
        if ($filter && stripos($userName, strtolower($filter)) === false) continue;
        $userLevels = [];
        foreach ($levelData as $levelKey => $data) {
            if ($levelFilter !== 'all' && $levelKey !== $levelFilter) continue;
            $levelNum = $data[0];
            $levelLabel = $data[1];
            $allocate = $allocations->findOne([
                '$and' => [
                    ["user" => $collaborator],
                    ["type" => "Declaratif"],
                    ["level" => $levelKey],
                ],
            ]);
            if (!$allocate) continue;
            $isAvailable = isset($allocate['activeTest']) && $allocate['activeTest'] === true;
            $statusHtml = '';
            $progress = 0;
            $linkParams = '?test=' . $allocate["test"] . '&level=' . $allocate["level"] . '&id=' . $id . '&user=' . $user["_id"] . '&guide=' . $guideEval;
            $linkHref = './quiz' . $linkParams;
            if (!$isAvailable) {
                $statusHtml = '<div class="d-flex flex-column align-items-center w-100 gap-1"><span class="text-center w-100 fw-bold small">Progression (0%)</span><div class="custom-progress mt-1 w-100"><div class="custom-progress-fill" style="width: 0%;"></div></div><span class="badge bg-secondary fs-6 mt-1 w-100 fw-bold">' . $non_disponible . '</span></div>';
            } else {
                $exam = $exams->findOne([
                    '$and' => [
                        ["manager" => new MongoDB\BSON\ObjectId($id)],
                        ["test" => new MongoDB\BSON\ObjectId($allocate["test"])],
                        ["active" => true],
                    ],
                ], ['sort' => ['_id' => -1]]);
                if ($exam) {
                    $questions = isset($exam['questions']) && $exam['questions'] instanceof \MongoDB\Model\BSONArray ? iterator_to_array($exam['questions']) : (array) ($exam['questions'] ?? []);
                    if (!is_array($questions)) $questions = [];
                    $answers = isset($exam['answers']) && $exam['answers'] instanceof \MongoDB\Model\BSONArray ? iterator_to_array($exam['answers']) : (array) ($exam['answers'] ?? []);
                    if (!is_array($answers)) $answers = [];
                    $totalQuestions = count($questions);
                    $answeredQuestions = array_filter($answers, function($ans) { return $ans !== null && $ans !== '' && $ans !== 'null'; });
                    $progress = 0;
                    if (is_int($totalQuestions) && $totalQuestions > 0) {
                        $answeredCount = count($answeredQuestions);
                        $ratio = $answeredCount / $totalQuestions;
                        if (is_numeric($ratio)) {
                            $progress = round($ratio * 100);
                        }
                    }
                    $progress = max(0, min(100, $progress));
                    $statusHtml = '<div class="d-flex flex-column align-items-center w-100 gap-1"><span class="text-center w-100 fw-bold small">Progression (' . (int)$progress . '%)</span><div class="custom-progress mt-1 w-100"><div class="custom-progress-fill silent-progress" style="width: ' . (int)$progress . '%;"></div></div><a href="' . $linkHref . '" class="btn btn-primary btn-xs w-100 fw-bold mt-1" title="' . $click_to_open_test . '">Continuer</a></div>';
                } else {
                    if (isset($allocate['activeManager']) && $allocate['activeManager'] == false) {
                        $statusHtml = '<div class="d-flex flex-column align-items-center w-100 gap-1"><span class="text-center w-100 fw-bold small">Progression (0%)</span><div class="custom-progress mt-1 w-100"><div class="custom-progress-fill" style="width: 0%;"></div></div><a href="' . $linkHref . '" class="btn btn-primary btn-xs w-100 fw-bold mt-1" title="' . $click_to_open_test . '">' . $evaluer_par_vous . '</a></div>';
                    } else {
                        $progress = 100;
                        $statusHtml = '<div class="d-flex flex-column align-items-center w-100 gap-1"><span class="text-center w-100 fw-bold small">Progression (100%)</span><div class="custom-progress mt-1 w-100"><div class="custom-progress-fill silent-progress" style="width: 100%;"></div></div><span class="badge bg-success fs-6 mt-1 w-100 fw-bold">' . $effectue . '</span></div>';
                    }
                }
            }
            $levelComponent = renderLevelBadge($levelNum, $levelLabel);
            $userLevels[] = [
                'level' => $levelComponent,
                'status' => $statusHtml,
                'progress' => $progress,
                'isAvailable' => $isAvailable
            ];
        }
        if (!empty($userLevels)) {
            $collaboratorCards[] = [
                'name' => $userName,
                'test' => $maitrise_tache_pro,
                'icon' => 'bi bi-journal-text',
                'levels' => $userLevels,
                'userId' => (string)$user->_id
            ];
        }
    }
}
$filters = [];
$noPersonalMessage = $showPersonal && empty($factuelCards) && empty($declaratifCards) ? '
    <div class="text-center py-10">
        <i class="bi bi-search fs-1 text-light mb-3"></i>
        <h5 class="text-light mb-1">Aucun test personnel trouvé</h5>
        <p class="text-light mb-0">Aucun test ne correspond à vos critères.</p>
    </div>' : '';
$noCollaboratorsMessage = $showCollaborators && empty($collaboratorCards) ? '
    <div class="text-center py-10">
        <i class="bi bi-search fs-1 text-light mb-3"></i>
        <h5 class="text-light mb-1">Aucun collaborateur trouvé</h5>
        <p class="text-light mb-0">Aucun collaborateur ne correspond à vos critères.</p>
    </div>' : '';
// LOGIQUE CORRIGÉE : Adaptation des titres en fonction du type d'utilisateur
if ($isTechnician) {
    $title = $list_test_qcm;
    $introTitle = "Mesurer mes compétences";
    $introText1 = highlightKeywords("Répondez aux QCM (Question à Choix Multiples) sur les connaissances et les tâches professionnelles pour mesurer vos compétences.", $introKeywords);
    $introText2 = highlightKeywords('Cliquez sur le bouton "Commencer le QCM" pour démarrer un nouveau QCM ou "Continuer" pour reprendre un QCM en cours.', $guideKeywords);
    $titleIcon = "bi bi-clipboard-check fs-1";
} elseif ($showPersonal && $showCollaborators) {
    $title = "Mesurer mes compétences et celles de mes collaborateurs";
    $introTitle = "Mesurer mes compétences et celles de mes collaborateurs";
    $introText1 = highlightKeywords("Complétez les QCM (Question à Choix Multiples) pour Mesurer vos compétences ainsi que la maîtrise des tâches professionnelles de vos collaborateurs.", $introKeywords);
    $introText2 = highlightKeywords('Cliquez sur le bouton "Commencer le QCM" pour démarrer un nouveau QCM ou "Continuer" pour reprendre un QCM en cours.', $guideKeywords);
    $titleIcon = "bi bi-clipboard-check fs-1";
} elseif ($showPersonal && !$showCollaborators) {
    $title = $list_test_qcm;
    $introTitle = "Mesurez mes compétences techniques";
    $introText1 = highlightKeywords("Répondez aux QCM (Question à Choix Multiples) sur les connaissances et les tâches pour mesurer vos compétences.", $introKeywords);
    $introText2 = highlightKeywords('Cliquez sur le bouton "Commencer le QCM" pour démarrer un nouveau QCM ou "Continuer" pour reprendre un QCM en cours.', $guideKeywords);
    $titleIcon = "bi bi-clipboard-check fs-1";
} else {
    $title = $list_evalue_collab;
    $introTitle = "Mesurer les compétences de mes collaborateurs";
    $introText1 = highlightKeywords("Complétez les QCM (Question à Choix Multiples) de maîtrise des tâches professionnelles pour Mesurer les compétences de vos collaborateurs.", $introKeywords);
    $introText2 = highlightKeywords('Cliquez sur le bouton "Évaluer" pour démarrer une nouvelle évaluation ou "Continuer" pour reprendre.', $guideKeywords);
    $titleIcon = "bi bi-people fs-1";
}
$personalContainerId = "personal-tests";
$collabContainerId = "collab-tests";
?>
<?php include_once "./partials/header.php"; ?>
<title><?php echo $title; ?> | CFAO Mobility Academy</title>
<link rel="stylesheet" href="../../public/css/measure/evaluations.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="../../public/css/core/utilities.css">
<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #24235c, #e62244);
        --glass-bg: rgba(255, 255, 255, 0.15);
        --glass-border: rgba(255, 255, 255, 0.25);
        --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.08);
        --shadow-md: 0 8px 24px rgba(0, 0, 0, 0.12);
        --transition: all 0.3s ease;
        --border-radius: 12px;
        --font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        --primary-color: #e62244;
        --secondary-color: #24235c;
    }
    body {
        font-family: var(--font-family);
        line-height: 1.6;
        color: white;
        font-size: 1.125rem;
    }
    .glass-card {
        background: var(--glass-bg);
        backdrop-filter: blur(16px);
        border: 1px solid var(--glass-border);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-md);
        transition: var(--transition);
        overflow: hidden;
        color: white;
    }
    .glass-card:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(0, 0, 0, 0.15); }
    .intro-section {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: var(--border-radius);
        padding: 2.5rem;
        margin-bottom: 2.5rem;
        text-align: center;
        color: white;
    }
    .personal-section, .collaborator-section {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: var(--border-radius);
        padding: 2rem;
        margin-bottom: 2.5rem;
        color: white;
    }
    .intro-section h4 {
        font-size: 2rem;
        font-weight: 600;
        margin-bottom: 1.25rem;
        color: white;
    }
    .intro-section p {
        font-size: 1.125rem;
        margin-bottom: 1rem;
        opacity: 0.9;
    }
    .highlight {
        background: rgba(230, 34, 68, 0.2);
        color: white;
        font-weight: 700;
        padding: 0 2px;
        border-radius: 3px;
    }
    .filters-row {
        display: flex;
        gap: 1.25rem;
        flex-wrap: wrap;
        margin-bottom: 2.5rem;
        justify-content: center;
        align-items: flex-end;
    }
    .filter-group {
        flex: 1;
        min-width: 260px;
        max-width: 300px;
    }
    .filter-group label {
        display: block;
        font-weight: 500;
        margin-bottom: 0.5rem;
        color: white;
        font-size: 1.125rem;
    }
    .filter-group select {
        width: 100%;
        padding: 0.875rem;
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.1);
        color: white;
        font-size: 1.125rem;
        transition: var(--transition);
    }
    .filter-group select option {
        background: rgba(0, 0, 0, 0.8);
        color: white;
    }
    .filter-group select:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(230, 34, 68, 0.1);
    }
    .section-title {
        font-size: 1.75rem;
        font-weight: 600;
        color: white;
        margin-bottom: 1.25rem;
        padding-left: 1rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        background: rgba(255, 255, 255, 0.1);
        padding: 1rem;
        border-radius: var(--border-radius);
        border-left: 4px solid var(--primary-color);
    }
    .cards-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        justify-content: center;
        gap: 1.75rem;
        padding: 0;
        max-height: 70vh;
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: var(--primary-color) transparent;
    }
    .cards-grid.collab-grid {
        grid-template-columns: repeat(3, 1fr);
    }
    .cards-grid::-webkit-scrollbar {
        width: 6px;
    }
    .cards-grid::-webkit-scrollbar-track { background: transparent; }
    .cards-grid::-webkit-scrollbar-thumb {
        background: var(--primary-color);
        border-radius: 3px;
    }
    .cards-grid .test-card,
    .cards-grid .collab-card {
        width: 100%;
    }
    .test-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: var(--border-radius);
        padding: 1.75rem;
        box-shadow: var(--shadow-sm);
        transition: var(--transition);
        display: flex;
        flex-direction: column;
        height: 220px;
        color: #333;
        position: relative;
        overflow: hidden;
    }
    .test-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.15);
    }
    .test-card.unavailable {
        filter: grayscale(100%) brightness(0.7);
        pointer-events: none;
    }
    .test-card.unavailable:hover {
        transform: none;
        box-shadow: var(--shadow-sm);
    }
    .test-top-row {
        display: flex;
        flex-direction: row;
        flex: 1;
        width: 100%;
        margin-bottom: 1rem;
    }
    .test-icon-container {
        width: 70px;
        height: 70px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #e9ecef;
        border-radius: 50%;
        margin-right: 1rem;
        flex-shrink: 0;
    }
    .test-icon {
        font-size: 3.5rem;
        color: #333 !important;
    }
    .test-icon::before {
        color: #333 !important;
    }
    .test-content-right-top {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .test-info-div {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }
    .test-title {
        font-size: 1.125rem;
        font-weight: 700;
        color: var(--secondary-color);
        line-height: 1.2;
        margin-bottom: 0.5rem;
    }
    .test-level {
        font-size: 0.95rem;
        color: #666;
        font-weight: 500;
    }
    .test-status {
        width: 100%;
        margin-top: auto;
        padding-top: 0.75rem;
        border-top: 1px solid rgba(0, 0, 0, 0.1);
    }
    .test-status .btn,
    .test-status .badge {
        font-size: 0.875rem;
        padding: 0.625rem 1rem;
        color: white;
        height: auto;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        line-height: 1.3;
        transition: var(--transition);
    }
    .test-status .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(230, 34, 68, 0.3);
    }
    .custom-progress {
        height: 6px;
        background-color: #e9ecef;
        border-radius: 3px;
        overflow: hidden;
        position: relative;
        margin: 0.5rem 0;
    }
    .custom-progress-fill {
        height: 100%;
        background: #007bff;
        border-radius: 3px;
        transition: width 0.4s ease;
        position: relative;
        overflow: hidden;
    }
    .custom-progress-fill::after {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
        animation: shimmer 2s infinite;
    }
    @keyframes shimmer {
        0% { left: -100%; }
        100% { left: 100%; }
    }
    .silent-progress::after {
        display: none;
    }
    .collab-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: var(--border-radius);
        padding: 1.75rem;
        box-shadow: var(--shadow-sm);
        transition: var(--transition);
        display: flex;
        flex-direction: column;
        height: 260px;
        color: #333;
        position: relative;
        overflow: hidden;
    }
    .collab-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.15);
    }
    .collab-header {
        display: flex;
        flex-direction: row;
        align-items: center;
        margin-bottom: 1rem;
        flex: 1;
    }
    .collab-icon {
        font-size: 3.5rem;
        color: #333;
        background: #e9ecef;
        width: 70px;
        height: 70px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        margin-right: 1rem;
        flex-shrink: 0;
    }
    .collab-content {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .collab-name {
        font-size: 1.125rem;
        font-weight: 700;
        color: var(--secondary-color);
        line-height: 1.2;
        margin-bottom: 0.25rem;
    }
    .collab-test {
        font-size: 0.95rem;
        color: #666;
        font-weight: 500;
    }
    .levels-container {
        width: 100%;
        margin-top: auto;
        padding-top: 0.75rem;
        border-top: 1px solid rgba(0, 0, 0, 0.1);
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }
    .levels-row {
        display: flex;
        gap: 0.25rem;
        justify-content: space-around;
    }
    .level-item {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        text-align: center;
    }
    .level-item.unavailable {
        filter: grayscale(100%) brightness(0.7);
        pointer-events: none;
    }
    .level-badge {
        font-size: 0.875rem;
        margin-bottom: 0.25rem;
        color: #333;
    }
    .level-status {
        width: 100%;
    }
    .level-status .btn,
    .level-status .badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
        color: white;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        line-height: 1.2;
        transition: var(--transition);
        height: auto;
    }
    .level-status .custom-progress {
        height: 4px;
        margin: 0.125rem 0;
        width: 100%;
        background-color: #e9ecef;
        border-radius: 2px;
    }
    .level-status .custom-progress-fill {
        background: #007bff;
        transition: width 0.3s ease;
        height: 100%;
    }
    .level-status .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 6px rgba(230, 34, 68, 0.3);
    }
    .small { font-size: 0.8rem; }
    .btn-xs { padding: 0.25rem 0.5rem; font-size: 0.75rem; }
    @media (max-width: 768px) {
        .cards-grid {
            grid-template-columns: 1fr;
        }
        .cards-grid.collab-grid {
            grid-template-columns: 1fr;
        }
        .test-top-row,
        .collab-header {
            flex-direction: column;
        }
        .test-icon-container,
        .collab-icon {
            width: 100%;
            height: auto;
            padding: 1rem 0;
            margin-right: 0;
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }
        .test-content-right-top,
        .collab-content {
            width: 100%;
            padding-left: 0;
        }
        .test-card,
        .collab-card {
            height: auto;
        }
        .filters-row { flex-direction: column; align-items: stretch; }
        .intro-section { padding: 1.75rem; }
        .levels-row { flex-direction: column; gap: 0.5rem; }
    }
</style>
<?php openBackgroundContainer(); ?>
<div class="post fs-5 d-flex flex-column-fluid" id="kt_post">
    <div class="container-xxl">
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="intro-section glass-card">
                    <?php renderTitleSection($titleIcon, [], $introTitle); ?>
                    <p><?php echo $introText1; ?></p>
                    <p><?php echo $introText2; ?></p>
                </div>
            </div>
            <?php if ($showPersonal): ?>
                <div class="col-12">
                    <div class="personal-section glass-card">
                        <?php if ($showSubsectionTitle && !$isTechnician): ?>
                            <?php renderTitleSection('ki-duotone ki-chart fs-2 me-3', [], 'Mesurer mes compétences'); ?>
                        <?php endif; ?>
                        <?php if (empty($factuelCards) && empty($declaratifCards)): ?>
                            <?php echo $noPersonalMessage; ?>
                        <?php else: ?>
                            <div class="row mb-4">
                                <?php if (($testTypeFilter == 'all' || $testTypeFilter == 'Factuel') && !empty($factuelCards)): ?>
                                    <div class="col-lg-<?php echo ($testTypeFilter == 'all' && !empty($declaratifCards)) ? '6' : '12'; ?>">
                                        <?php renderTitleSection('bi bi-clipboard-data fs-2 me-3', [], $connaissances); ?>
                                        <div class="cards-grid" id="factuel-tests">
                                            <?php foreach ($factuelCards as $card): ?>
                                                <div class="test-card glass-card <?php echo $card['isAvailable'] ? '' : 'unavailable'; ?>">
                                                    <div class="test-top-row">
                                                        <div class="test-icon-container">
                                                            <i class="<?php echo htmlspecialchars($card['icon']); ?> test-icon"></i>
                                                        </div>
                                                        <div class="test-content-right-top">
                                                            <div class="test-info-div">
                                                                <div class="test-title"><?php echo htmlspecialchars($card['test']); ?></div>
                                                                <div class="test-level"><?php echo $card['level']; ?></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="test-status"><?php echo $card['status']; ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php if (($testTypeFilter == 'all' || $testTypeFilter == 'Declaratif') && !empty($declaratifCards)): ?>
                                    <div class="col-lg-<?php echo ($testTypeFilter == 'all' && !empty($factuelCards)) ? '6' : '12'; ?>">
                                        <?php renderTitleSection('bi bi-journal-text fs-2 me-3', [], $tache_pro); ?>
                                        <div class="cards-grid" id="declaratif-tests">
                                            <?php foreach ($declaratifCards as $card): ?>
                                                <div class="test-card glass-card <?php echo $card['isAvailable'] ? '' : 'unavailable'; ?>">
                                                    <div class="test-top-row">
                                                        <div class="test-icon-container">
                                                            <i class="<?php echo htmlspecialchars($card['icon']); ?> test-icon"></i>
                                                        </div>
                                                        <div class="test-content-right-top">
                                                            <div class="test-info-div">
                                                                <div class="test-title"><?php echo htmlspecialchars($card['test']); ?></div>
                                                                <div class="test-level"><?php echo $card['level']; ?></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="test-status"><?php echo $card['status']; ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($showCollaborators): ?>
                <div class="col-12">
                    <div class="collaborator-section glass-card">
                        <?php renderTitleSection('ki-duotone ki-chart fs-2 me-3', [], 'Mesurer les compétences de mes collaborateurs'); ?>
                        <div class="cards-grid collab-grid" id="<?php echo $collabContainerId; ?>">
                            <?php if (!empty($collaboratorCards)): ?>
                                <?php foreach ($collaboratorCards as $card): ?>
                                    <div class="collab-card glass-card">
                                        <div class="collab-header">
                                            <div class="collab-icon">
                                                <i class="<?php echo htmlspecialchars($card['icon']); ?>"></i>
                                            </div>
                                            <div class="collab-content">
                                                <div class="collab-name"><?php echo $card['name']; ?></div>
                                                <div class="collab-test"><?php echo $card['test']; ?></div>
                                            </div>
                                        </div>
                                        <div class="levels-container">
                                            <div class="levels-row">
                                                <?php foreach ($card['levels'] as $level): ?>
                                                    <div class="level-item <?php echo $level['isAvailable'] ? '' : 'unavailable'; ?>">
                                                        <div class="level-badge"><?php echo $level['level']; ?></div>
                                                        <div class="level-status"><?php echo $level['status']; ?></div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php echo $noCollaboratorsMessage; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php closeBackgroundContainer(); ?>
<?php include_once "./components/partials/footer.php"; ?>
<script src="../../public/js/core/event-manager.js" defer></script>
<script src="../../public/js/core/ajax-manager.js" defer></script>
<script src="../../public/js/core/ui-manager.js" defer></script>
<script src="../../public/js/core/animations.js"></script>
<script src="../../public/js/core/functions.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    <?php if ($showCollaborators): ?>
    document.addEventListener('DOMContentLoaded', function() {
        eventManager.on('input', '#mySearchInput', debounce((e, input) => {
            const value = input.value.trim();
            const params = new URLSearchParams(window.location.search);
            const currentFilters = {
                level: '<?= $levelFilter ?>'
            };
            if (value) {
                params.set('filter_value', value);
            } else {
                params.delete('filter_value');
            }
            if (currentFilters.level !== 'all') params.set('level', currentFilters.level);
            window.history.replaceState({}, '', window.location.pathname + (params.toString() ? '?' + params.toString() : ''));
            ajaxManager.reloadFilteredContent("<?php echo $collabContainerId; ?>");
        }, 300));
        document.addEventListener('ajaxManager:beforeSend', function() {
            const containerCollab = document.getElementById('<?php echo $collabContainerId; ?>');
            if (containerCollab) {
                containerCollab.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary mb-2" style="width: 3rem; height: 3rem;" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                    <p class="text-light mb-0">Chargement des collaborateurs...</p>
                </div>
                `;
            }
        });
    });
    <?php endif; ?>
</script>