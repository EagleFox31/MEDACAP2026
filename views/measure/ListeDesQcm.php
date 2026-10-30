<?php
require_once __DIR__ . '/../../core/medacap_config.php';
// Add this function at the top, after includes
function highlightKeywords($text, $keywords) {
    foreach ($keywords as $keyword) {
        $pattern = '/\b' . preg_quote($keyword, '/') . '\b/i';
        $text = preg_replace($pattern, '<span class="highlight">$0</span>', $text);
    }
    return $text;
}

function qcmProgressFillClass(int $progress, bool $silent = false): string {
    $classes = ['custom-progress-fill'];
    if ($progress >= 100) {
        $classes[] = 'progress-complete';
    } elseif ($progress > 0) {
        $classes[] = 'progress-active';
    }
    if ($silent || $progress >= 100) {
        $classes[] = 'silent-progress';
    }
    return implode(' ', $classes);
}

function qcmStatusBlock(int $progress, string $footerHtml, bool $compact = false): string {
    $labelClass = $compact
        ? 'text-center w-100 fw-semibold small progress-label'
        : 'text-center w-100 fw-semibold progress-label';
    $fillClass = qcmProgressFillClass($progress, $compact);
    return '<div class="d-flex flex-column align-items-center w-100 gap-1">'
        . '<span class="' . $labelClass . '">Progression (' . (int) $progress . '%)</span>'
        . '<div class="custom-progress mt-1 w-100"><div class="' . $fillClass . '" style="width: ' . (int) $progress . '%;"></div></div>'
        . $footerHtml
        . '</div>';
}

function qcmCompletedBadge(string $label): string {
    return '<span class="status-completed" role="status">'
        . '<i class="bi bi-check-circle-fill" aria-hidden="true"></i>'
        . '<span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>'
        . '</span>';
}

/**
 * Cellule tableau : progression + action (compact façon Apple).
 */
function qcmRenderMatrixCell($cell, string $effectueLabel): string {
    if (!$cell || !is_array($cell)) {
        return '<span class="qcm-cell-empty" aria-hidden="true">—</span>';
    }
    if (empty($cell['isAvailable'])) {
        return '<span class="qcm-badge-muted"><i class="bi bi-lock-fill me-1" style="font-size: 0.72rem;"></i>' . htmlspecialchars($cell['unavailableLabel'], ENT_QUOTES, 'UTF-8') . '</span>';
    }
    $progress = (int) ($cell['progress'] ?? 0);
    $fillClass = qcmProgressFillClass($progress, true);
    $out = '<div class="qcm-cell-inner">';
    $out .= '<div class="qcm-cell-progress-stack">';
    $out .= '<div class="custom-progress qcm-cell-bar" role="progressbar" aria-valuenow="' . $progress . '" aria-valuemin="0" aria-valuemax="100">';
    $out .= '<div class="' . $fillClass . '" style="width:' . $progress . '%;"></div></div>';
    $out .= '<span class="qcm-cell-pct">' . $progress . '%</span>';
    $out .= '</div>';
    if ($progress >= 100) {
        $out .= qcmCompletedBadge($effectueLabel);
    } elseif (!empty($cell['actionHtml'])) {
        $out .= $cell['actionHtml'];
    }
    $out .= '</div>';
    return $out;
}

function qcmRenderCollabMatrixCell($cell, string $effectueLabel): string {
    return qcmRenderMatrixCell($cell, $effectueLabel);
}

// Mots-clés mis en avant (2–3 max) — le reste reste en texte normal
$introKeywords = ['QCM', 'compétences', 'tâches professionnelles'];
$guideKeywords = ['Commencer le QCM', 'Continuer'];
session_start();
include_once "./includes/language.php";
require_once "../../vendor/autoload.php";
include_once "./components/ui/search-bar.php";
require_once "./components/ui/title-section.php";
include_once "./includes/background-manager.php";
require_once "./components/ui/filterHelpers.php";
require_once "./components/ui/render-level-badge.php";
require_once __DIR__ . '/includes/allocation_progression.php';
setPageBackground("bg-dashboard");
if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
}
$conn = new MongoDB\Client(medacapMongoUri());
$academy = $conn->academy;
$users = $academy->users;
$allocations = $academy->allocations;
$exams = $academy->exams;
$results = $academy->results;
$filter = $_GET['filter_value'] ?? null;
$testTypeFilter = $_GET['test_type'] ?? 'all';
$levelFilter = $_GET['level'] ?? 'all';
$factuelCards = [];
$declaratifCards = [];
$collaboratorCards = [];
$personalMatrix = array();
$collaboratorMatrix = array();
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
$userObjectId = new MongoDB\BSON\ObjectId($id);
$currentUser = $users->findOne([
    '$and' => [
        ["_id" => $userObjectId],
        ["active" => true],
    ],
]);
$hasPersonalExperience = $exams->count(["user" => $userObjectId]) > 0;
$guidePersonal = $hasPersonalExperience ? 'false' : 'true';
$isTechnician = isset($_SESSION['profile']) && strtolower($_SESSION['profile']) === 'technicien';
$canTakeTests = $isTechnician || (isset($currentUser->test) && $currentUser->test === true);
if ($canTakeTests) {
    try {
        allocationProgression_syncUnlockedLevels($allocations, $userObjectId);
    } catch (Throwable $e) {
        error_log('[ListeDesQcm] sync level unlock: ' . $e->getMessage());
    }
}
// LOGIQUE CORRIGÉE : Les techniciens ne voient PAS les collaborateurs
$showPersonal = $canTakeTests;
$showCollaborators = !$isTechnician && isset($currentUser->users) && !empty($currentUser->users);
// Managers : section collaborateurs visible meme sans equipe (message vide)
$isManagerProfile = isset($_SESSION['profile']) && $_SESSION['profile'] === 'Manager';
$showCollaboratorsSection = !$isTechnician && ($isManagerProfile || $showCollaborators);
$showSubsectionTitle = $showPersonal && $showCollaboratorsSection && !$isTechnician;
// « Mes Tests à Passer » : uniquement ses propres tests. L'évaluation des collaborateurs
// se fait dans « Liste des collaborateurs à évaluer ».
if ($canTakeTests) {
    $showCollaborators = false;
    $showCollaboratorsSection = false;
    $showSubsectionTitle = false;
}
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
            if (!$isAvailable) {
                $cell = array(
                    'isAvailable' => false,
                    'unavailableLabel' => 'Verrouillé · terminez le niveau précédent',
                    'progress' => 0,
                );
            } else {
                // L'évaluation du manager porte sur le même test : ses examens et résultats ne comptent pas ici.
                $exam = $exams->findOne([
                    '$and' => [
                        ["user" => new MongoDB\BSON\ObjectId($id)],
                        ["test" => new MongoDB\BSON\ObjectId($allocate["test"])],
                        ["active" => true],
                        ["type" => ['$ne' => 'Manager']],
                    ],
                ], ['sort' => ['_id' => -1]]);
                $completedExam = $exams->findOne([
                    '$and' => [
                        ["user" => new MongoDB\BSON\ObjectId($id)],
                        ["test" => new MongoDB\BSON\ObjectId($allocate["test"])],
                        ["active" => false],
                        ["type" => ['$ne' => 'Manager']],
                    ],
                ], ['sort' => ['_id' => -1]]);
                $hasCompletedResult = $results->findOne([
                    'user' => new MongoDB\BSON\ObjectId($id),
                    'test' => new MongoDB\BSON\ObjectId($allocate['test']),
                    'type' => $type,
                    'level' => $levelKey,
                    'typeR' => ['$nin' => ['Manager', 'Managers', 'Technicien - Manager']],
                    'active' => true,
                ]);
                $progress = 0;
                $actionHtml = '';
                $actionKind = '';
                $actionLabel = '';
                if ($completedExam || $hasCompletedResult) {
                    $progress = 100;
                } elseif ($exam) {
                    $questions = isset($exam['questions']) && $exam['questions'] instanceof \MongoDB\Model\BSONArray ? iterator_to_array($exam['questions']) : (array) ($exam['questions'] ?? []);
                    if (!is_array($questions)) {
                        $questions = array();
                    }
                    $answers = isset($exam['answers']) && $exam['answers'] instanceof \MongoDB\Model\BSONArray ? iterator_to_array($exam['answers']) : (array) ($exam['answers'] ?? []);
                    if (!is_array($answers)) {
                        $answers = array();
                    }
                    $totalQuestions = count($questions);
                    $answeredQuestions = array_filter($answers, function ($ans) {
                        return $ans !== null && $ans !== '' && $ans !== 'null';
                    });
                    if ($totalQuestions > 0) {
                        $progress = max(0, min(100, round((count($answeredQuestions) / $totalQuestions) * 100)));
                    }
                    $actionHtml = '<a href="' . htmlspecialchars($linkHref, ENT_QUOTES, 'UTF-8') . '" class="btn btn-action btn-sm qcm-cell-btn" title="Continuer le QCM"><i class="bi bi-play-fill me-1"></i>Continuer</a>';
                    $actionKind = 'resume';
                    $actionLabel = 'Continuer';
                } else {
                    $startLabel = 'Commencer le QCM';
                    if (isset($allocate['active']) && $allocate['active'] == false) {
                        $startLabel = !empty($completer_par_vous) ? $completer_par_vous : 'À compléter';
                    }
                    $actionHtml = '<a href="' . htmlspecialchars($linkHref, ENT_QUOTES, 'UTF-8') . '" class="btn btn-action btn-sm qcm-cell-btn" title="' . htmlspecialchars($startLabel, ENT_QUOTES, 'UTF-8') . '"><i class="bi bi-play-fill me-1"></i>' . htmlspecialchars($startLabel, ENT_QUOTES, 'UTF-8') . '</a>';
                    $actionKind = 'start';
                    $actionLabel = $startLabel;
                }
                $cell = array(
                    'isAvailable' => true,
                    'progress' => $progress,
                    'actionHtml' => $actionHtml,
                    'href' => $linkHref,
                    'actionKind' => $actionKind,
                    'actionLabel' => $actionLabel,
                );
            }
            $personalMatrix[$levelKey][$type] = $cell;
            $levelComponent = renderLevelBadge($levelNum, $levelLabel);
            $card = array(
                'test' => $typeData['label'],
                'icon' => $typeData['icon'],
                'level' => $levelComponent,
                'status' => '',
                'type' => $type,
                'isAvailable' => $isAvailable,
                'progress' => isset($cell['progress']) ? $cell['progress'] : 0,
            );
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
        $userLevels = array();
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
            // Evaluation manager : possible a tout niveau, independamment de activeTest (pratique technicien).
            $isAvailable = true;
            $statusHtml = '';
            $progress = 0;
            $linkParams = '?test=' . $allocate["test"] . '&level=' . $allocate["level"] . '&id=' . $id . '&user=' . $user["_id"] . '&guide=' . $guideEval;
            $linkHref = './quiz' . $linkParams;
            if (!$isAvailable) {
                $levelCell = array(
                    'isAvailable' => false,
                    'unavailableLabel' => $non_disponible,
                    'progress' => 0,
                );
            } else {
                $exam = $exams->findOne([
                    '$and' => [
                        ["manager" => new MongoDB\BSON\ObjectId($id)],
                        ["test" => new MongoDB\BSON\ObjectId($allocate["test"])],
                        ["active" => true],
                    ],
                ], ['sort' => ['_id' => -1]]);
                $progress = 0;
                $actionHtml = '';
                if ($exam) {
                    $questions = isset($exam['questions']) && $exam['questions'] instanceof \MongoDB\Model\BSONArray ? iterator_to_array($exam['questions']) : (array) ($exam['questions'] ?? []);
                    if (!is_array($questions)) {
                        $questions = array();
                    }
                    $answers = isset($exam['answers']) && $exam['answers'] instanceof \MongoDB\Model\BSONArray ? iterator_to_array($exam['answers']) : (array) ($exam['answers'] ?? []);
                    if (!is_array($answers)) {
                        $answers = array();
                    }
                    $totalQuestions = count($questions);
                    $answeredQuestions = array_filter($answers, function ($ans) {
                        return $ans !== null && $ans !== '' && $ans !== 'null';
                    });
                    if ($totalQuestions > 0) {
                        $progress = max(0, min(100, round((count($answeredQuestions) / $totalQuestions) * 100)));
                    }
                    $actionHtml = '<a href="' . htmlspecialchars($linkHref, ENT_QUOTES, 'UTF-8') . '" class="btn btn-action btn-sm qcm-cell-btn" title="' . htmlspecialchars($click_to_open_test, ENT_QUOTES, 'UTF-8') . '"><i class="bi bi-play-fill me-1"></i>Continuer</a>';
                } elseif (isset($allocate['activeManager']) && $allocate['activeManager'] == false) {
                    $evalLabel = !empty($evaluer_par_vous) ? $evaluer_par_vous : 'Évaluer';
                    $actionHtml = '<a href="' . htmlspecialchars($linkHref, ENT_QUOTES, 'UTF-8') . '" class="btn btn-action btn-sm qcm-cell-btn" title="' . htmlspecialchars($click_to_open_test, ENT_QUOTES, 'UTF-8') . '"><i class="bi bi-pencil-square me-1"></i>' . htmlspecialchars($evalLabel, ENT_QUOTES, 'UTF-8') . '</a>';
                } else {
                    $progress = 100;
                }
                $levelCell = array(
                    'isAvailable' => true,
                    'progress' => $progress,
                    'actionHtml' => $actionHtml,
                );
            }
            $userLevels[$levelKey] = $levelCell;
        }
        if (!empty($userLevels)) {
            $collaboratorMatrix[] = array(
                'name' => $userName,
                'userId' => (string) $user->_id,
                'levels' => $userLevels,
            );
            $collaboratorCards[] = array(
                'name' => $userName,
                'levels' => $userLevels,
            );
        }
    }
}
$filters = [];
$noPersonalMessage = $showPersonal && empty($personalMatrix) ? '
    <div class="qcm-empty-state">
        <div class="qcm-empty-icon" aria-hidden="true"><i class="bi bi-clipboard-x"></i></div>
        <h5>Aucun QCM disponible</h5>
        <p>Aucune épreuve ne correspond à vos critères pour le moment.</p>
    </div>' : '';
$noCollaboratorsMessage = '
    <div class="qcm-empty-state">
        <div class="qcm-empty-icon" aria-hidden="true"><i class="bi bi-people"></i></div>
        <h5>Aucun collaborateur à évaluer</h5>
        <p>Lorsque des techniciens seront rattachés à votre compte manager, leurs évaluations apparaîtront dans ce tableau.</p>
    </div>';
// LOGIQUE CORRIGÉE : Adaptation des titres en fonction du type d'utilisateur
if ($isTechnician || ($showPersonal && !$showCollaboratorsSection)) {
    $title = "Tests à passer";
    $introTitle = "Tests à passer";
    $introText1 = "Chaque niveau comporte 2 tests : <strong>Théorique</strong> (connaissances) et <strong>Pratique</strong> (tâches professionnelles).<br>Le niveau suivant s'ouvre quand les 2 tests du niveau sont terminés.";
    $introText2 = highlightKeywords('Cliquez sur le bouton "Commencer le QCM" pour démarrer un nouveau QCM ou "Continuer" pour reprendre un QCM en cours.', $guideKeywords);
    $titleIcon = "bi bi-clipboard-check fs-1";
} elseif ($showPersonal && $showCollaborators) {
    $title = "Mesurer mes compétences et celles de mes collaborateurs";
    $introTitle = "Mesurer mes compétences et celles de mes collaborateurs";
    $introText1 = highlightKeywords("Complétez les QCM (Question à Choix Multiples) pour Mesurer vos compétences ainsi que la maîtrise des tâches professionnelles de vos collaborateurs.", $introKeywords);
    $introText2 = highlightKeywords('Cliquez sur le bouton "Commencer le QCM" pour démarrer un nouveau QCM ou "Continuer" pour reprendre un QCM en cours.', $guideKeywords);
    $titleIcon = "bi bi-clipboard-check fs-1";
} elseif ($showPersonal && !$showCollaboratorsSection) {
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
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="../../public/css/core/utilities.css">
<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #1e1b4b 0%, #24235c 45%, #e62244 100%);
        --glass-bg: rgba(255, 255, 255, 0.16);
        --glass-border: rgba(255, 255, 255, 0.28);
        --glass-blur: 20px;
        --shadow-apple-sm: 0 4px 14px rgba(0, 0, 0, 0.06);
        --shadow-apple-md: 0 16px 40px rgba(0, 0, 0, 0.12), inset 0 1px 0 rgba(255, 255, 255, 0.35);
        --shadow-apple-lg: 0 24px 50px rgba(0, 0, 0, 0.16), inset 0 1px 0 rgba(255, 255, 255, 0.4);
        --transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        --border-radius: 20px;
        --font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'SF Pro Text', 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        --primary-color: #e62244;
        --secondary-color: #24235c;
    }
    body {
        font-family: var(--font-family);
        line-height: 1.5;
        color: #ffffff;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }
    .glass-card {
        background: var(--glass-bg);
        backdrop-filter: blur(var(--glass-blur)) saturate(180%);
        -webkit-backdrop-filter: blur(var(--glass-blur)) saturate(180%);
        border: 1px solid var(--glass-border);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-apple-md);
        transition: var(--transition);
        overflow: hidden;
        color: #ffffff;
    }
    .intro-section {
        background: var(--glass-bg);
        backdrop-filter: blur(var(--glass-blur)) saturate(180%);
        -webkit-backdrop-filter: blur(var(--glass-blur)) saturate(180%);
        border: 1px solid var(--glass-border);
        border-radius: var(--border-radius);
        padding: 1.85rem 2rem;
        margin-bottom: 1.75rem;
        text-align: center;
        color: #ffffff;
        box-shadow: var(--shadow-apple-md);
    }
    .intro-section .card.glass-effect {
        background: rgba(255, 255, 255, 0.2) !important;
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(255, 255, 255, 0.38) !important;
        border-radius: 9999px !important;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08), inset 0 1px 0 rgba(255, 255, 255, 0.4) !important;
        margin-bottom: 1.25rem !important;
    }
    .intro-section h1 {
        font-size: 1.4rem !important;
        font-weight: 700 !important;
        color: #ffffff !important;
        letter-spacing: -0.01em;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }
    .intro-section p {
        font-size: 1.05rem;
        color: rgba(255, 255, 255, 0.92);
        margin-bottom: 0;
        line-height: 1.55;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.15);
    }
    .highlight {
        background: rgba(230, 34, 68, 0.25);
        color: #ffffff;
        font-weight: 600;
        padding: 0.12em 0.45em;
        border-radius: 6px;
        box-decoration-break: clone;
        -webkit-box-decoration-break: clone;
    }
    .qcm-table-section {
        background: var(--glass-bg);
        backdrop-filter: blur(var(--glass-blur)) saturate(180%);
        -webkit-backdrop-filter: blur(var(--glass-blur)) saturate(180%);
        border: 1px solid var(--glass-border);
        border-radius: var(--border-radius);
        padding: 0;
        margin-bottom: 1.75rem;
        color: #ffffff;
        box-shadow: var(--shadow-apple-md);
        overflow: hidden;
    }
    .qcm-section-head {
        padding: 1.6rem 1.75rem 1.25rem;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
    }
    .qcm-section-title-wrap {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        text-align: center;
    }
    .qcm-section-icon {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.22);
        color: #ffffff;
        border: 1px solid rgba(255, 255, 255, 0.4);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
    .qcm-section-head h3 {
        margin: 0;
        font-size: 1.35rem;
        font-weight: 700;
        color: #ffffff;
        letter-spacing: -0.01em;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }
    .qcm-section-head p {
        margin: 0.35rem 0 0;
        font-size: 0.925rem;
        color: rgba(255, 255, 255, 0.9);
        line-height: 1.45;
        text-align: center;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.15);
    }
    .qcm-table-wrap {
        padding: 0 1.5rem 1.5rem;
        overflow-x: auto;
    }
    .qcm-matrix-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        background: #ffffff;
        border-radius: 14px;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.35);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
        table-layout: fixed;
        font-size: 0.9375rem;
    }
    .qcm-matrix-table thead th {
        background: #f8fafc;
        color: #475569;
        font-weight: 700;
        font-size: 0.8125rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        text-align: center;
        padding: 1rem 0.85rem;
        border: none;
        border-bottom: 1px solid #e2e8f0;
        white-space: nowrap;
        vertical-align: middle;
    }
    .qcm-matrix-table tbody td,
    .qcm-matrix-table tbody th {
        padding: 1.25rem 1rem;
        border: none;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
        text-align: center;
        background: #ffffff;
        color: #1e293b;
        transition: background 0.2s ease;
    }
    .qcm-matrix-table tbody tr:last-child td,
    .qcm-matrix-table tbody tr:last-child th {
        border-bottom: none;
    }
    .qcm-matrix-table tbody tr:hover td,
    .qcm-matrix-table tbody tr:hover th[scope="row"] {
        background: #f8fafc;
    }
    .qcm-matrix-table tbody th[scope="row"] {
        font-weight: 600;
        color: var(--secondary-color);
        white-space: nowrap;
    }
    .qcm-level-cell {
        display: inline-flex;
        justify-content: center;
        align-items: center;
    }
    /* Level Badges Apple Style (Junior = Bronze, Senior = Argent, Expert = Or) */
    .badge-level-bronze,
    .badge-bronze,
    .badge-light-bronze,
    .badge-light-success,
    .qcm-badge-junior {
        background: linear-gradient(135deg, rgba(217, 119, 6, 0.14) 0%, rgba(180, 83, 9, 0.20) 100%) !important;
        color: #8c3e07 !important;
        border: 1px solid rgba(180, 83, 9, 0.35) !important;
        border-radius: 9999px !important;
        padding: 0.42rem 1.15rem !important;
        font-weight: 700 !important;
        font-size: 0.82rem !important;
        letter-spacing: 0.03em !important;
        box-shadow: 0 1px 3px rgba(140, 62, 7, 0.12), inset 0 1px 0 rgba(255, 255, 255, 0.7) !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 0.35rem !important;
        text-shadow: 0 1px 0 rgba(255, 255, 255, 0.4);
    }
    .badge-level-silver,
    .badge-silver,
    .badge-light-silver,
    .badge-light-danger,
    .qcm-badge-senior {
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%) !important;
        color: #334155 !important;
        border: 1px solid rgba(148, 163, 184, 0.55) !important;
        border-radius: 9999px !important;
        padding: 0.42rem 1.15rem !important;
        font-weight: 700 !important;
        font-size: 0.82rem !important;
        letter-spacing: 0.03em !important;
        box-shadow: 0 1px 3px rgba(100, 116, 139, 0.14), inset 0 1px 0 rgba(255, 255, 255, 0.95) !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 0.35rem !important;
        text-shadow: 0 1px 0 rgba(255, 255, 255, 0.6);
    }
    .badge-level-gold,
    .badge-gold,
    .badge-light-gold,
    .badge-light-warning,
    .qcm-badge-expert {
        background: linear-gradient(135deg, rgba(254, 240, 138, 0.65) 0%, rgba(245, 158, 11, 0.28) 100%) !important;
        color: #78350f !important;
        border: 1px solid rgba(217, 119, 6, 0.45) !important;
        border-radius: 9999px !important;
        padding: 0.42rem 1.15rem !important;
        font-weight: 700 !important;
        font-size: 0.82rem !important;
        letter-spacing: 0.03em !important;
        box-shadow: 0 1px 4px rgba(202, 138, 4, 0.2), inset 0 1px 0 rgba(255, 255, 255, 0.85) !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 0.35rem !important;
        text-shadow: 0 1px 0 rgba(255, 255, 255, 0.5);
    }
    /* Cell Inner & Progress Stack */
    .qcm-cell-inner {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.6rem;
        width: min(220px, 100%);
        margin: 0 auto;
    }
    .qcm-cell-progress-stack {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: 0.25rem;
        width: 100%;
    }
    .custom-progress {
        height: 7px;
        background-color: #e2e8f0;
        border-radius: 9999px;
        overflow: hidden;
        position: relative;
        margin: 0;
        box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.06);
    }
    .custom-progress-fill {
        height: 100%;
        background: #cbd5e1;
        border-radius: 9999px;
        transition: width 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        position: relative;
        overflow: hidden;
    }
    .custom-progress-fill.progress-active {
        background: linear-gradient(90deg, #3b82f6 0%, #2563eb 100%);
        box-shadow: 0 1px 3px rgba(37, 99, 235, 0.3);
    }
    .custom-progress-fill.progress-complete {
        background: linear-gradient(90deg, #34d399 0%, #059669 100%);
        box-shadow: 0 1px 3px rgba(5, 150, 105, 0.3);
    }
    .qcm-cell-pct {
        font-size: 0.75rem;
        font-weight: 600;
        color: #64748b;
        text-align: center;
        line-height: 1.2;
        font-variant-numeric: tabular-nums;
        letter-spacing: 0.01em;
    }
    /* Buttons & Status Pill Badges */
    .btn-action,
    .qcm-cell-btn {
        background: var(--primary-gradient) !important;
        border: 1px solid rgba(255, 255, 255, 0.15) !important;
        color: #ffffff !important;
        font-size: 0.8125rem !important;
        font-weight: 600 !important;
        padding: 0.45rem 1.15rem !important;
        border-radius: 9999px !important;
        white-space: nowrap !important;
        letter-spacing: 0.015em !important;
        box-shadow: 0 4px 12px rgba(230, 34, 68, 0.28), 0 1px 2px rgba(0, 0, 0, 0.08) !important;
        transition: var(--transition) !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 0.35rem !important;
        text-decoration: none !important;
        width: 100%;
        max-width: 200px;
    }
    .btn-action:hover,
    .qcm-cell-btn:hover {
        color: #ffffff !important;
        filter: brightness(1.08) !important;
        transform: translateY(-1.5px) !important;
        box-shadow: 0 6px 18px rgba(230, 34, 68, 0.38), 0 2px 4px rgba(0, 0, 0, 0.12) !important;
    }
    .btn-action:active,
    .qcm-cell-btn:active {
        transform: translateY(0) scale(0.98) !important;
        box-shadow: 0 2px 6px rgba(230, 34, 68, 0.25) !important;
    }
    .status-completed {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        padding: 0.42rem 1.1rem;
        font-size: 0.8125rem;
        font-weight: 600;
        letter-spacing: 0.015em;
        color: #047857;
        background: linear-gradient(180deg, #ecfdf5 0%, #d1fae5 100%);
        border: 1px solid rgba(16, 185, 129, 0.35);
        border-radius: 9999px;
        box-shadow: 0 1px 3px rgba(16, 185, 129, 0.12), inset 0 1px 0 rgba(255, 255, 255, 0.9);
        width: 100%;
        max-width: 200px;
    }
    .status-completed i {
        font-size: 0.95rem;
        color: #10b981;
    }
    .qcm-badge-muted {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        padding: 0.4rem 1rem;
        border-radius: 9999px;
        font-size: 0.8rem;
        font-weight: 500;
        color: #94a3b8;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        letter-spacing: 0.01em;
    }
    .qcm-cell-empty {
        color: #cbd5e1;
        font-size: 1.1rem;
        font-weight: 400;
    }
    /* Collaborator Row Details */
    .collab-name-cell {
        font-weight: 600;
        color: var(--secondary-color);
        text-align: left !important;
        padding-left: 1.5rem !important;
    }
    .collab-user-info {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    .collab-user-avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
        color: #64748b;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
        border: 1px solid #e2e8f0;
    }
    .collab-user-name {
        font-weight: 600;
        color: #0f172a;
        font-size: 0.925rem;
    }
    /* Empty State */
    .qcm-empty-state {
        text-align: center;
        padding: 3rem 1.5rem;
        color: #475569;
    }
    .qcm-empty-icon {
        width: 60px;
        height: 60px;
        margin: 0 auto 1rem;
        border-radius: 16px;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.65rem;
        color: #94a3b8;
    }
    .qcm-empty-state h5 {
        color: #0f172a;
        font-weight: 700;
        margin-bottom: 0.35rem;
        font-size: 1.1rem;
    }
    .qcm-empty-state p {
        margin: 0 auto;
        max-width: 420px;
        line-height: 1.55;
        font-size: 0.9rem;
        color: #64748b;
    }
    /* Lisibilité : cartes opaques, texte sombre (le fond flouté rouge écrasait le texte blanc). */
    .intro-section,
    .qcm-table-section.glass-card {
        background: rgba(255, 255, 255, 0.97);
        color: #1e293b;
    }
    .intro-section .card.glass-effect {
        background: transparent !important;
        border: 0 !important;
        box-shadow: none !important;
        margin-bottom: 0.5rem !important;
    }
    .intro-section h1 {
        color: #24235c !important;
        text-shadow: none;
    }
    .intro-section h1 i { color: #e62244; }
    .intro-section p {
        color: #475569;
        text-shadow: none;
        font-size: 1rem;
    }
    .intro-section p strong { color: #24235c; }
    .qcm-matrix-table thead th { color: #24235c; font-size: 0.95rem; }
    .qcm-col-sub {
        display: block;
        font-size: 0.75rem;
        font-weight: 500;
        color: #64748b;
        text-transform: none;
        letter-spacing: 0;
    }
    @media (max-width: 768px) {
        .intro-section { padding: 1.25rem; }
        .qcm-table-wrap { padding: 0.75rem; }
        .qcm-section-head { padding: 1rem 1.25rem; }
    }

    /* ===== Parcours personnel : cartes par niveau ===== */
    .lq-title-card { background: #fff !important; border-radius: 25px !important;
        box-shadow: 0 8px 32px 0 rgba(31,38,135,.15); border: 1px solid rgba(255,255,255,.18); }
    .lq-title { margin: 0; font-size: 1.9rem; font-weight: 700; color: #101828; }
    .lq-subtitle { margin: 8px auto 0; max-width: 760px; font-size: 1.05rem; line-height: 1.55; color: #475467; }
    .lq-glass { background: rgba(255,255,255,.88) !important; backdrop-filter: blur(18px);
        border-radius: 18px !important; border: 1px solid rgba(255,255,255,.18) !important;
        box-shadow: 0 8px 32px 0 rgba(31,38,135,.18) !important; }

    .lq-summary { display: flex; align-items: center; gap: 20px; flex-wrap: wrap; }
    .lq-summary-text { display: flex; flex-direction: column; min-width: 180px; }
    .lq-summary-label { font-size: .8rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #667085; }
    .lq-summary-count { font-size: 1.15rem; color: #344054; }
    .lq-summary-count strong { font-size: 1.6rem; color: #101828; }
    .lq-summary-bar { flex: 1 1 240px; height: 12px; border-radius: 99px; background: #eaecf0; overflow: hidden; }
    .lq-summary-bar span { display: block; height: 100%; border-radius: 99px; background: linear-gradient(90deg, #12b76a, #039855); }
    .lq-summary-pct { font-size: 1.6rem; font-weight: 800; color: #039855; font-variant-numeric: tabular-nums; }

    .lq-table-wrap { background: #fff; border: 1px solid #eaecf0; border-radius: 12px; }
    .lq-table { width: 100%; border-collapse: separate; border-spacing: 0; margin: 0; }
    .lq-table thead th { background: #f9fafb; padding: 14px 20px; font-size: .9rem; font-weight: 700; color: #667085;
        text-transform: uppercase; letter-spacing: .04em; border-bottom: 1px solid #eaecf0; white-space: nowrap; }
    .lq-table thead th.lq-lvl { text-align: center; width: 22%; }
    .lq-table tbody td { padding: 18px 20px; border-bottom: 1px solid #eaecf0; vertical-align: middle; }
    .lq-table tbody tr:last-child td { border-bottom: 0; }
    .lq-table tbody tr:hover td { background: #fcfcfd; }
    .lq-table td.lq-lvl { text-align: center; }
    .lq-test { display: flex; align-items: center; gap: 12px; min-width: 220px; }
    .lq-test-icon { width: 42px; height: 42px; border-radius: 10px; flex: none; display: flex; align-items: center; justify-content: center;
        background: #eff8ff; color: #175cd3; font-size: 1.15rem; }
    .lq-test-icon i { color: inherit; }
    .lq-test-title { font-size: 1.15rem; font-weight: 600; color: #101828; }
    .lq-test-sub { font-size: .9rem; color: #667085; }
    .lq-btn { display: inline-flex; align-items: center; gap: 6px; height: 40px; padding: 0 18px; border-radius: 8px;
        font-size: 1.05rem; font-weight: 600; text-decoration: none; white-space: nowrap; transition: background .15s, box-shadow .15s; }
    .lq-btn:focus-visible { outline: none; box-shadow: 0 0 0 4px rgba(41,112,255,.24); }
    .lq-btn-primary { background: #175cd3; color: #fff; box-shadow: 0 1px 2px rgba(16,24,40,.08); }
    .lq-btn-primary:hover { background: #1849a9; color: #fff; }
    .lq-btn-resume { background: #fffaeb; color: #b54708; border: 1px solid #fedf89; }
    .lq-btn-resume:hover { background: #fef0c7; color: #b54708; }
    .lq-pill { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 99px;
        font-size: 1rem; font-weight: 500; white-space: nowrap; }
    .lq-btn i, .lq-pill i { color: inherit; }
    .lq-pill-done { background: #ecfdf3; color: #067647; }
    .lq-pill-na { background: #f2f4f7; color: #667085; }
    @media (max-width: 767px) {
        .lq-table thead th, .lq-table tbody td { padding: 12px 14px; }
    }
</style>
<?php openBackgroundContainer(); ?>
<div class="post fs-5 d-flex flex-column-fluid" id="kt_post">
    <div class="container-xxl">
        <div class="row g-4 mb-4">
            <!-- Carte titre (même gabarit que bilanTestNotes / userManagerList) -->
            <div class="col-12 pt-5">
                <div class="card lq-title-card">
                    <div class="card-body text-center py-4 px-6">
                        <h1 class="lq-title"><i class="<?php echo htmlspecialchars($titleIcon, ENT_QUOTES, 'UTF-8'); ?> text-secondary me-3" aria-hidden="true"></i><?php echo $introTitle; ?></h1>
                        <p class="lq-subtitle"><?php echo $introText1; ?></p>
                    </div>
                </div>
            </div>
            <?php if ($showPersonal):
                // Parcours personnel : une carte par niveau, 2 tests (Théorique / Pratique) par carte
                $lqTypes = array(
                    'Factuel'    => array('Théorique', $connaissances, 'bi-book'),
                    'Declaratif' => array('Pratique', $tache_pro, 'bi-tools'),
                );
                $lqLevels = array();
                $lqTotal = 0;
                $lqDone = 0;
                foreach ($levelData as $levelKey => $data) {
                    if ($levelFilter !== 'all' && $levelKey !== $levelFilter) {
                        continue;
                    }
                    $tiles = array();
                    $avail = 0;
                    $done = 0;
                    foreach ($lqTypes as $type => $meta) {
                        if ($testTypeFilter !== 'all' && $testTypeFilter !== $type) {
                            continue;
                        }
                        if (empty($personalMatrix[$levelKey][$type])) {
                            continue;
                        }
                        $cell = $personalMatrix[$levelKey][$type];
                        $tiles[$type] = $cell;
                        $lqTotal++;
                        if (!empty($cell['isAvailable'])) {
                            $avail++;
                            if ((int) $cell['progress'] >= 100) {
                                $done++;
                                $lqDone++;
                            }
                        }
                    }
                    if (empty($tiles)) {
                        continue;
                    }
                    if ($avail === 0) {
                        $state = 'locked';
                    } elseif ($done === count($tiles)) {
                        $state = 'done';
                    } else {
                        $state = 'current';
                    }
                    $lqLevels[$levelKey] = array('label' => $data[1], 'num' => $data[0], 'tiles' => $tiles, 'state' => $state, 'done' => $done);
                }
                $lqPct = $lqTotal > 0 ? (int) round($lqDone * 100 / $lqTotal) : 0;
            ?>
                <?php if (empty($lqLevels)): ?>
                <div class="col-12">
                    <div class="qcm-table-section glass-card"><?php echo $noPersonalMessage; ?></div>
                </div>
                <?php else: ?>
                <!-- Barre de progression globale -->
                <div class="col-12">
                    <div class="card lq-glass">
                        <div class="card-body py-4 px-5 lq-summary">
                            <div class="lq-summary-text">
                                <span class="lq-summary-label"><?php echo $showCollaboratorsSection ? 'Mes tests' : 'Mon parcours'; ?></span>
                                <span class="lq-summary-count"><strong><?php echo $lqDone; ?></strong> / <?php echo $lqTotal; ?> tests terminés</span>
                            </div>
                            <div class="lq-summary-bar" role="progressbar" aria-valuenow="<?php echo $lqPct; ?>" aria-valuemin="0" aria-valuemax="100">
                                <span style="width: <?php echo $lqPct; ?>%"></span>
                            </div>
                            <span class="lq-summary-pct"><?php echo $lqPct; ?>%</span>
                        </div>
                    </div>
                </div>

                <!-- Tableau : une ligne par type de test, une colonne par niveau (même gabarit que userManagerList) -->
                <div class="col-12">
                    <div class="card lq-glass">
                        <div class="card-body p-4">
                            <div class="table-responsive lq-table-wrap">
                                <table class="lq-table">
                                    <thead>
                                        <tr>
                                            <th scope="col">Test</th>
                                            <?php foreach ($lqLevels as $levelKey => $lvl): ?>
                                            <th scope="col" class="lq-lvl"><?php echo htmlspecialchars($level . ' ' . $lvl['label'], ENT_QUOTES, 'UTF-8'); ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($lqTypes as $type => $meta):
                                            if ($testTypeFilter !== 'all' && $testTypeFilter !== $type) {
                                                continue;
                                            }
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="lq-test">
                                                    <span class="lq-test-icon"><i class="bi <?php echo $meta[2]; ?>" aria-hidden="true"></i></span>
                                                    <div>
                                                        <div class="lq-test-title"><?php echo $meta[0]; ?></div>
                                                        <div class="lq-test-sub"><?php echo htmlspecialchars($meta[1], ENT_QUOTES, 'UTF-8'); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <?php foreach ($lqLevels as $levelKey => $lvl):
                                                $cell = isset($lvl['tiles'][$type]) ? $lvl['tiles'][$type] : null;
                                                $available = $cell && !empty($cell['isAvailable']);
                                                $progress = $available ? (int) $cell['progress'] : 0;
                                            ?>
                                            <td class="lq-lvl">
                                                <?php if (!$cell): ?>
                                                    <span class="lq-pill lq-pill-na"><i class="bi bi-dash" aria-hidden="true"></i><?php echo $non_disponible; ?></span>
                                                <?php elseif (!$available): ?>
                                                    <span class="lq-pill lq-pill-na" title="<?php echo htmlspecialchars($cell['unavailableLabel'], ENT_QUOTES, 'UTF-8'); ?>"><i class="bi bi-lock-fill" aria-hidden="true"></i>Verrouillé</span>
                                                <?php elseif ($progress >= 100): ?>
                                                    <span class="lq-pill lq-pill-done"><i class="bi bi-check-circle-fill" aria-hidden="true"></i><?php echo $effectue; ?></span>
                                                <?php elseif (!empty($cell['href'])): ?>
                                                    <?php if ($cell['actionKind'] === 'resume'): ?>
                                                    <a href="<?php echo htmlspecialchars($cell['href'], ENT_QUOTES, 'UTF-8'); ?>" class="lq-btn lq-btn-resume"><i class="bi bi-hourglass-split" aria-hidden="true"></i><?php echo htmlspecialchars($cell['actionLabel'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo $progress; ?>%</a>
                                                    <?php else: ?>
                                                    <a href="<?php echo htmlspecialchars($cell['href'], ENT_QUOTES, 'UTF-8'); ?>" class="lq-btn lq-btn-primary"><?php echo htmlspecialchars($cell['actionLabel'], ENT_QUOTES, 'UTF-8'); ?> <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                            <?php endforeach; ?>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($showCollaboratorsSection): ?>
                <div class="col-12">
                    <div class="qcm-table-section glass-card">
                        <div class="qcm-section-head">
                            <div class="qcm-section-title-wrap">
                                <span class="qcm-section-icon"><i class="bi bi-people-fill"></i></span>
                                <h3>Mesure les compétences de mes collaborateurs</h3>
                            </div>
                            <p>Évaluation des tâches professionnelles par technicien et par niveau</p>
                        </div>
                        <?php if (empty($collaboratorMatrix)): ?>
                            <?php echo $noCollaboratorsMessage; ?>
                        <?php else: ?>
                            <div class="qcm-table-wrap">
                                <table class="qcm-matrix-table" id="<?php echo htmlspecialchars($collabContainerId, ENT_QUOTES, 'UTF-8'); ?>">
                                    <thead>
                                        <tr>
                                            <th scope="col" style="width: 28%;">Collaborateur</th>
                                            <?php foreach ($levelData as $levelKey => $data):
                                                if ($levelFilter !== 'all' && $levelKey !== $levelFilter) {
                                                    continue;
                                                }
                                                ?>
                                                <th scope="col"><?php echo renderLevelBadge($data[0], $data[1]); ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($collaboratorMatrix as $row): ?>
                                            <tr>
                                                <td class="collab-name-cell">
                                                    <div class="collab-user-info">
                                                        <span class="collab-user-avatar"><i class="bi bi-person-fill"></i></span>
                                                        <span class="collab-user-name"><?php echo $row['name']; ?></span>
                                                    </div>
                                                </td>
                                                <?php foreach ($levelData as $levelKey => $data):
                                                    if ($levelFilter !== 'all' && $levelKey !== $levelFilter) {
                                                        continue;
                                                    }
                                                    $cell = isset($row['levels'][$levelKey]) ? $row['levels'][$levelKey] : null;
                                                    ?>
                                                    <td><?php echo qcmRenderCollabMatrixCell($cell, $effectue); ?></td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
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