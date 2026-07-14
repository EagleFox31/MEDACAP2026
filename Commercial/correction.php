<?php
session_start();
require_once "../vendor/autoload.php";
require_once "../views/sendMail.php";
require_once "sendCommercialMail.php";

use MongoDB\Client;
use MongoDB\BSON\ObjectId;

// Guard
if (!isset($_SESSION['id'])) {
    header("Location: ../");
    exit();
}
if (!in_array($_SESSION['profile'] ?? '', ['Super Admin', 'Admin', 'Admin Commercial'])) {
    header("Location: responses.php");
    exit();
}

$client      = new Client("mongodb://localhost:27017");
$db          = $client->academy;
$responseColl = $db->commercial_responses;
$corrColl     = $db->commercial_corrections;

// Chargement dynamique des questions depuis les fichiers JSON
function getQuestionnaireDetails($questionType) {
    $jsonFile = ($questionType === 'sinotruk') ? 'questionnaire_sinotruk.json' : 'questionnaire_bus_et_chariot.json';
    $rawQuestionnaire = json_decode(file_get_contents(__DIR__ . '/' . $jsonFile), true);
    $sections = [];
    $totalQuestions = 0;
    foreach ($rawQuestionnaire['sections'] as $sec) {
        $secQuestions = [];
        foreach ($sec['questions'] as $q) {
            $secQuestions[] = [
                'id'       => $q['id'],
                'question' => $q['text_FR'],
            ];
            $totalQuestions++;
        }
        $sections[] = [
            'section'   => $sec['section_FR'],
            'questions' => $secQuestions,
        ];
    }
    return [
        'sections'       => $sections,
        'totalQuestions' => $totalQuestions
    ];
}

$TOTAL_QUESTIONS = 20; // Valeur par défaut

// ── AJAX Handler ──────────────────────────────────────────────────────────────
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action     = $_GET['action'];
    $responseId = trim($_GET['id'] ?? '');

    try {
        $objId = new ObjectId($responseId);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => 'ID invalide']);
        exit();
    }

    $existing = $corrColl->findOne(['response_id' => $responseId]);

    // ── save_grade ────────────────────────────────────────────────────────────
    if ($action === 'save_grade') {
        $qid     = preg_replace('/[^a-z0-9_]/', '', $_POST['qid'] ?? '');
        $verdict = $_POST['verdict'] ?? '';
        $comment = trim($_POST['comment'] ?? '');

        if (!in_array($verdict, ['correct', 'partiel', 'incorrect', ''])) {
            echo json_encode(['ok' => false, 'error' => 'Verdict invalide']);
            exit();
        }
        if (empty($qid)) {
            echo json_encode(['ok' => false, 'error' => 'QID manquant']);
            exit();
        }

        $correctorName = trim(($_SESSION['firstName'] ?? '') . ' ' . ($_SESSION['lastName'] ?? ''));
        $gradeData = [
            'verdict' => $verdict,
            'comment' => $comment,
            'by'      => $correctorName,
            'at'      => date('d-m-Y H:i:s'),
        ];

        if ($existing) {
            // Sécurité : si grades est stocké comme un array BSON (legacy / vide), le convertir en objet stdClass
            $existingGrades = $existing['grades'] ?? null;
            if ($existingGrades instanceof MongoDB\Model\BSONArray && count($existingGrades) === 0) {
                $corrColl->updateOne(
                    ['response_id' => $responseId],
                    ['$set' => ['grades' => (object)[]]]
                );
            }

            $corrColl->updateOne(
                ['response_id' => $responseId],
                ['$set' => [
                    'grades.' . $qid => $gradeData,
                    'status'         => 'in_progress',
                ]]
            );
        } else {
            $corrColl->insertOne([
                'response_id'    => $responseId,
                'status'         => 'in_progress',
                'correctors'     => [[
                    'email'    => $_SESSION['email'] ?? '',
                    'name'     => $correctorName,
                    'added_at' => date('d-m-Y H:i:s'),
                ]],
                'grades'         => [$qid => $gradeData],
                'global_comment' => '',
                'score'          => ['correct' => 0, 'partiel' => 0, 'incorrect' => 0, 'total' => 0],
                'completed_at'   => null,
                'completed_by'   => null,
            ]);
        }

        // Recalculate score
        $updated = $corrColl->findOne(['response_id' => $responseId]);
        $grades  = $updated ? (array)($updated['grades'] ?? []) : [];
        $score   = ['correct' => 0, 'partiel' => 0, 'incorrect' => 0];
        foreach ($grades as $g) {
            $ga = (array)$g;
            $v  = $ga['verdict'] ?? '';
            if (isset($score[$v])) $score[$v]++;
        }
        $score['total'] = array_sum($score);

        $corrColl->updateOne(
            ['response_id' => $responseId],
            ['$set' => ['score' => $score]]
        );

        echo json_encode(['ok' => true, 'score' => $score]);
        exit();
    }

    // ── add_corrector ─────────────────────────────────────────────────────────
    if ($action === 'add_corrector') {
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['ok' => false, 'error' => 'Adresse email invalide']);
            exit();
        }

        $alreadyIn = false;
        if ($existing) {
            foreach ((array)($existing['correctors'] ?? []) as $c) {
                $ca = (array)$c;
                if (($ca['email'] ?? '') === $email) {
                    $alreadyIn = true;
                    break;
                }
            }
        }

        if (!$alreadyIn) {
            $corrector = ['email' => $email, 'name' => $email, 'added_at' => date('d-m-Y H:i:s')];
            if ($existing) {
                $corrColl->updateOne(
                    ['response_id' => $responseId],
                    ['$push' => ['correctors' => $corrector]]
                );
            } else {
                $myName  = trim(($_SESSION['firstName'] ?? '') . ' ' . ($_SESSION['lastName'] ?? ''));
                $myEmail = $_SESSION['email'] ?? '';
                $corrColl->insertOne([
                    'response_id'    => $responseId,
                    'status'         => 'pending',
                    'correctors'     => [
                        ['email' => $myEmail, 'name' => $myName, 'added_at' => date('d-m-Y H:i:s')],
                        $corrector,
                    ],
                    'grades'         => (object)[],
                    'global_comment' => '',
                    'score'          => ['correct' => 0, 'partiel' => 0, 'incorrect' => 0, 'total' => 0],
                    'completed_at'   => null,
                    'completed_by'   => null,
                ]);
            }
        }

        echo json_encode(['ok' => true, 'already_in' => $alreadyIn]);
        exit();
    }

    // ── remove_corrector ──────────────────────────────────────────────────────
    if ($action === 'remove_corrector') {
        $email = trim($_POST['email'] ?? '');
        if ($existing && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $corrColl->updateOne(
                ['response_id' => $responseId],
                ['$pull' => ['correctors' => ['email' => $email]]]
            );
        }
        echo json_encode(['ok' => true]);
        exit();
    }

    // ── save_comment ──────────────────────────────────────────────────────────
    if ($action === 'save_comment') {
        if ($existing) {
            $corrColl->updateOne(
                ['response_id' => $responseId],
                ['$set' => ['global_comment' => trim($_POST['comment'] ?? '')]]
            );
        }
        echo json_encode(['ok' => true]);
        exit();
    }

    // ── start_recorrection ───────────────────────────────────────────────────
    if ($action === 'start_recorrection') {
        $corr = $corrColl->findOne(['response_id' => $responseId]);
        if (!$corr || ($corr['status'] ?? '') !== 'completed') {
            echo json_encode(['ok' => false, 'error' => 'La correction doit être finalisée.']);
            exit();
        }
        $existingHistory = (array)($corr['history'] ?? []);
        $roundNum = count($existingHistory) + 1;
        $historyEntry = [
            'round'          => $roundNum,
            'grades'         => $corr['grades'] ?? (object)[],
            'score'          => (array)($corr['score'] ?? ['correct' => 0, 'partiel' => 0, 'incorrect' => 0, 'total' => 0]),
            'global_comment' => $corr['global_comment'] ?? '',
            'completed_at'   => $corr['completed_at']   ?? '',
            'completed_by'   => $corr['completed_by']   ?? '',
        ];
        $corrColl->updateOne(
            ['response_id' => $responseId],
            [
                '$push' => ['history' => $historyEntry],
                '$set'  => [
                    'grades'         => (object)[],
                    'status'         => 'in_progress',
                    'score'          => ['correct' => 0, 'partiel' => 0, 'incorrect' => 0, 'total' => 0],
                    'global_comment' => '',
                    'completed_at'   => null,
                    'completed_by'   => null,
                ]
            ]
        );
        echo json_encode(['ok' => true]);
        exit();
    }

    // ── complete ──────────────────────────────────────────────────────────────
    if ($action === 'complete') {
        $completedBy = trim(($_SESSION['firstName'] ?? '') . ' ' . ($_SESSION['lastName'] ?? ''));

        $corr   = $corrColl->findOne(['response_id' => $responseId]);
        $grades = $corr ? (array)($corr['grades'] ?? []) : [];
        $score  = ['correct' => 0, 'partiel' => 0, 'incorrect' => 0];
        foreach ($grades as $g) {
            $ga = (array)$g;
            $v  = $ga['verdict'] ?? '';
            if (isset($score[$v])) $score[$v]++;
        }
        $score['total'] = array_sum($score);

        $corrColl->updateOne(
            ['response_id' => $responseId],
            ['$set' => [
                'status'       => 'completed',
                'score'        => $score,
                'completed_at' => date('d-m-Y H:i:s'),
                'completed_by' => $completedBy,
            ]]
        );

        // Load response for email content
        $resp  = $responseColl->findOne(['_id' => $objId]);
        $name  = trim(($resp['firstName'] ?? '') . ' ' . ($resp['lastName'] ?? ''));
        $brand = $resp['brand'] ?? '';
        $qType = $resp['questionnaire_type'] ?? 'sinotruk';
        $detailsData = getQuestionnaireDetails($qType);
        $TOTAL_QUESTIONS = $detailsData['totalQuestions'];

        // Generate download token if not present
        $downloadToken = $resp['download_token'] ?? '';
        if (empty($downloadToken)) {
            $downloadToken = bin2hex(random_bytes(24));
            $responseColl->updateOne(
                ['_id' => $objId],
                ['$set' => ['download_token' => $downloadToken]]
            );
        }

        // Build links dynamically using medacapLinkForCountry
        $country = $resp['country'] ?? '';
        $baseUrl = medacapLinkForCountry($country);
        if (substr($baseUrl, -1) !== '/') {
            $baseUrl .= '/';
        }
        $platformLink = $baseUrl . "Commercial/correction.php?id=" . $responseId;
        $downloadLink = $baseUrl . "Commercial/download-response.php?token=" . $downloadToken;

        // Build recipients: admin + all correctors + current user
        $recipients = [SUPER_ADMIN_EMAIL];
        if ($corr) {
            foreach ((array)($corr['correctors'] ?? []) as $c) {
                $ca = (array)$c;
                $em = $ca['email'] ?? '';
                if (filter_var($em, FILTER_VALIDATE_EMAIL) && !in_array($em, $recipients)) {
                    $recipients[] = $em;
                }
            }
        }
        $sessEmail = $_SESSION['email'] ?? '';
        if (filter_var($sessEmail, FILTER_VALIDATE_EMAIL) && !in_array($sessEmail, $recipients)) {
            $recipients[] = $sessEmail;
        }

        $subject = "[CFAO Academy] Correction terminée — {$name} ({$brand})";
        
        $emailSentSuccessfully = false;
        try {
            foreach ($recipients as $recipient) {
                $sent = sendCommercialEmail($recipient, $subject, [
                    'userName' => 'Équipe ' . $brand,
                    'title'    => 'Correction finalisée',
                    'message'  => "La correction du questionnaire de <strong>{$name}</strong> (marque&nbsp;: <strong>{$brand}</strong>) a été finalisée par <strong>{$completedBy}</strong>.<br><br>
                    Vous pouvez maintenant accéder directement aux réponses en utilisant les actions ci-dessous :<br><br>
                    <div style=\"margin:20px 0;\">
                        <a href=\"" . htmlspecialchars($platformLink, ENT_QUOTES, 'UTF-8') . "\" style=\"display: inline-block; background-color: #0b3c6e; color: #ffffff; text-decoration: none; padding: 12px 24px; font-weight: 700; border-radius: 6px; font-size: 14px; font-family: system-ui, -apple-system, sans-serif; margin-right: 12px; margin-bottom: 12px;\">
                            Voir la correction sur la plateforme
                        </a>
                        <a href=\"" . htmlspecialchars($downloadLink, ENT_QUOTES, 'UTF-8') . "\" style=\"display: inline-block; background-color: #198754; color: #ffffff; text-decoration: none; padding: 12px 24px; font-weight: 700; border-radius: 6px; font-size: 14px; font-family: system-ui, -apple-system, sans-serif; margin-bottom: 12px;\">
                            Télécharger les réponses (Excel)
                        </a>
                    </div>",
                    'details'  => [
                        ['label' => 'Candidat',   'value' => $name],
                        ['label' => 'Marque',     'value' => $brand],
                        ['label' => 'Correct',    'value' => $score['correct']   . ' question(s)', 'highlight' => true],
                        ['label' => 'Partiel',    'value' => $score['partiel']   . ' question(s)'],
                        ['label' => 'Incorrect',  'value' => $score['incorrect'] . ' question(s)'],
                        ['label' => 'Total noté', 'value' => $score['total']     . ' / ' . $TOTAL_QUESTIONS],
                    ],
                ]);
                if ($sent) {
                    $emailSentSuccessfully = true;
                }
            }

            if ($emailSentSuccessfully) {
                // Mark as successfully sent in commercial_responses
                $responseColl->updateOne(
                    ['_id' => $objId],
                    ['$set' => [
                        'mail_sent_at'    => date('d-m-Y H:i:s'),
                        'mail_sent_count' => (int)($resp['mail_sent_count'] ?? 0) + 1
                    ]]
                );
            }
        } catch (\Throwable $e) {
            // Ignore email failure so the correction completion is NOT blocked!
        }

        echo json_encode(['ok' => true, 'score' => $score]);
        exit();
    }

    echo json_encode(['ok' => false, 'error' => 'Action inconnue']);
    exit();
}

// ── Page Display ──────────────────────────────────────────────────────────────
$responseId = trim($_GET['id'] ?? '');
if (!$responseId) { header("Location: responses.php"); exit(); }

try {
    $objId = new ObjectId($responseId);
} catch (Exception $e) {
    header("Location: responses.php"); exit();
}

$response = $responseColl->findOne(['_id' => $objId]);
if (!$response) { header("Location: responses.php"); exit(); }

$correction = $corrColl->findOne(['response_id' => $responseId]);

// Auto-create correction; auto-add current user as corrector
$myEmail = $_SESSION['email']     ?? '';
$myName  = trim(($_SESSION['firstName'] ?? '') . ' ' . ($_SESSION['lastName'] ?? ''));

if (!$correction) {
    $corrColl->insertOne([
        'response_id'    => $responseId,
        'status'         => 'pending',
        'correctors'     => [['email' => $myEmail, 'name' => $myName, 'added_at' => date('d-m-Y H:i:s')]],
        'grades'         => (object)[],
        'global_comment' => '',
        'score'          => ['correct' => 0, 'partiel' => 0, 'incorrect' => 0, 'total' => 0],
        'completed_at'   => null,
        'completed_by'   => null,
    ]);
    $correction = $corrColl->findOne(['response_id' => $responseId]);
} elseif ($myEmail !== '') {
    $alreadyIn = false;
    foreach ((array)($correction['correctors'] ?? []) as $c) {
        $ca = (array)$c;
        if (($ca['email'] ?? '') === $myEmail) { $alreadyIn = true; break; }
    }
    if (!$alreadyIn) {
        $corrColl->updateOne(
            ['response_id' => $responseId],
            ['$push' => ['correctors' => ['email' => $myEmail, 'name' => $myName, 'added_at' => date('d-m-Y H:i:s')]]]
        );
        $correction = $corrColl->findOne(['response_id' => $responseId]);
    }
}

// Extract correction data
$grades        = $correction ? (array)($correction['grades']        ?? []) : [];
$correctors    = $correction ? (array)($correction['correctors']    ?? []) : [];
$globalComment = $correction ? ($correction['global_comment'] ?? '') : '';
$status        = $correction ? ($correction['status']         ?? 'pending') : 'pending';
$completedAt   = $correction ? ($correction['completed_at']   ?? '') : '';
$completedBy   = $correction ? ($correction['completed_by']   ?? '') : '';
$scoreData     = $correction ? (array)($correction['score']   ?? []) : [];

$scoreCorrect   = (int)($scoreData['correct']   ?? 0);
$scorePartiel   = (int)($scoreData['partiel']   ?? 0);
$scoreIncorrect = (int)($scoreData['incorrect'] ?? 0);
$scoreTotal     = (int)($scoreData['total']     ?? 0);

$correctionHistory = $correction ? (array)($correction['history'] ?? []) : [];
$correctionRound   = count($correctionHistory) + 1;

// Candidate info
$candidateName    = trim(($response['firstName'] ?? '') . ' ' . ($response['lastName'] ?? ''));
$candidateEmail   = $response['email']              ?? '';
$candidateCountry = $response['country']             ?? '—';
$candidateBrand   = $response['brand']               ?? '';
$questionType     = $response['questionnaire_type']  ?? 'sinotruk';
$violations       = (int)($response['violations']    ?? 0);
$isBanned         = !empty($response['is_banned']);
$submittedAt      = $response['submitted_at']        ?? '';
$totalTimeSec     = (int)($response['total_time_seconds'] ?? 0);
$timeStr          = ($totalTimeSec > 0)
    ? floor($totalTimeSec / 60) . 'min ' . ($totalTimeSec % 60) . 's'
    : '—';
$answers          = (array)($response['answers'] ?? []);

// Status labels
$statusLabels = ['pending' => 'En attente', 'in_progress' => 'En cours', 'completed' => 'Terminée'];
$statusLabel  = $statusLabels[$status] ?? $status;

// Question definitions (from JSON files)
$detailsData = getQuestionnaireDetails($questionType);
$sections = $detailsData['sections'];
$TOTAL_QUESTIONS = $detailsData['totalQuestions'];

?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" type="image/x-icon" href="../public/images/logos/cfao_academy.ico">
    <title>Correction — <?php echo htmlspecialchars($candidateName, ENT_QUOTES, 'UTF-8'); ?> — CFAO Academy</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; }
        body { font-family: 'Inter', sans-serif; min-height: 100vh; display: flex; flex-direction: column; }
        .me-1 { margin-right: .25rem !important; }
        .me-2 { margin-right: .5rem !important; }

        /* ── Topbar ── */
        .topbar {
            background: #0f172a; padding: .85rem 2rem;
            display: flex; align-items: center; justify-content: space-between;
            box-shadow: 0 2px 8px rgba(0,0,0,.25);
            position: fixed; top: 0; left: 0; right: 0; z-index: 200;
        }
        .topbar-left { display: flex; align-items: center; gap: 1rem; }
        .topbar-logo { background: #fff; border-radius: 6px; padding: 3px 8px; display: flex; align-items: center; }
        .topbar-logo img { height: 26px; width: auto; display: block; }
        .topbar-sep { width: 1px; height: 22px; background: rgba(255,255,255,.18); flex-shrink: 0; }
        .topbar-title { color: #fff; font-size: .88rem; font-weight: 700; }
        .topbar-sub { color: rgba(255,255,255,.4); font-size: .68rem; margin-top: 1px; }
        .topbar-right { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; }
        .topbar-user { color: rgba(255,255,255,.55); font-size: .75rem; white-space: nowrap; }
        .topbar-btn {
            color: rgba(255,255,255,.7); font-size: .75rem; font-weight: 500;
            text-decoration: none; display: inline-flex; align-items: center; gap: .35rem;
            padding: .32rem .75rem; border-radius: 6px;
            border: 1px solid rgba(255,255,255,.15); transition: all .15s;
            background: transparent; cursor: pointer; font-family: inherit;
        }
        .topbar-btn:hover { background: rgba(255,255,255,.08); color: #fff; }

        /* ── Background ── */
        .page-bg-wrap { flex: 1; position: relative; overflow: visible; padding-top: 3.5rem; }
        .bg-overlay {
            position: absolute; inset: 0;
            background: url('../public/images/tof_dashboard_tech.png') center/cover no-repeat;
            filter: blur(8px); transform: scale(1.08); z-index: 0;
        }
        .page-inner { position: relative; z-index: 1; padding: 1.5rem; min-height: 100%; }

        /* ── Header card ── */
        .header-card {
            position: sticky;
            top: 3.7rem;
            z-index: 100;
            background: rgba(255,255,255,.96);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border-radius: 16px;
            padding: 1rem 1.5rem .9rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,.12);
        }

        /* ── Footer ── */
        .page-footer {
            background: #0f172a; border-top: 1px solid rgba(255,255,255,.06);
            padding: .8rem 2rem; text-align: center;
            color: rgba(255,255,255,.3); font-size: .72rem; flex-shrink: 0;
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body>

<div class="topbar">
    <div class="topbar-left">
        <div class="topbar-logo"><img src="../public/images/logo-cfao.png" alt="CFAO"></div>
        <div class="topbar-sep"></div>
        <div>
            <div class="topbar-title">Correction &mdash; <?php echo htmlspecialchars($candidateName, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="topbar-sub">CFAO Mobility Academy</div>
        </div>
    </div>
    <div class="topbar-right">
        <span class="topbar-user">
            <i class="fas fa-user-circle" style="margin-right:.3rem;color:rgba(255,255,255,.4)"></i><?php echo htmlspecialchars($_SESSION['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
        </span>
        <a href="responses.php" class="topbar-btn"><i class="fas fa-arrow-left"></i> Réponses</a>
    </div>
</div>

<div class="page-bg-wrap">
    <div style="position: fixed; inset: 0; overflow: hidden; pointer-events: none; z-index: 0;">
        <div class="bg-overlay"></div>
    </div>
    <div class="page-inner">
<?php
?>

<style>
/* ── Animation ─────────────────────────────────────────────────────── */
@keyframes slideInRight {
    from { opacity: 0; transform: translateX(30px); }
    to   { opacity: 1; transform: translateX(0); }
}
.corr-page {
    animation: slideInRight 0.35s cubic-bezier(0.25, 0.46, 0.45, 0.94) both;
    max-width: 1500px;
    margin: 0 auto;
}

/* ── Top bar ───────────────────────────────────────────────────────── */
.corr-topbar {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: .2rem 0 .8rem;
    flex-wrap: wrap;
}
.back-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 36px; height: 36px;
    background: #fff;
    border: 1.5px solid #dde3ec;
    border-radius: 10px;
    color: #0b3c6e;
    text-decoration: none;
    font-size: 1rem;
    flex-shrink: 0;
    transition: all .18s;
}
.back-btn:hover { background: #0b3c6e; color: #fff; border-color: #0b3c6e; }
.breadcrumb-bar {
    display: flex;
    align-items: center;
    gap: .5rem;
    flex: 1;
    font-size: .9rem;
    min-width: 0;
}
.breadcrumb-bar a { color: #0b3c6e; text-decoration: none; font-weight: 600; }
.breadcrumb-bar a:hover { text-decoration: underline; }
.bc-sep { color: #bbb; font-size: 1rem; }
.bc-current { font-weight: 700; color: #222; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.status-badge {
    display: inline-block;
    padding: .2rem .7rem;
    border-radius: 20px;
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: .3px;
    white-space: nowrap;
}
.status-pending   { background: rgba(108,117,125,.12); color: #6c757d; }
.status-in_progress { background: rgba(255,140,0,.14); color: #b06a00; }
.status-completed { background: rgba(25,135,84,.14);  color: #146c43; }
.btn-complete {
    margin-left: auto;
    background: #0b3c6e;
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: .55rem 1.3rem;
    font-size: .875rem;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex; align-items: center; gap: .5rem;
    transition: all .18s;
    white-space: nowrap;
}
.btn-complete:hover:not(:disabled) { background: #083060; }
.btn-complete:disabled { opacity: .6; cursor: default; }
.completed-info {
    font-size: .82rem;
    color: #198754;
    font-weight: 600;
    display: flex; align-items: center; gap: .4rem;
    white-space: nowrap;
}
.btn-recorrect {
    display: inline-flex; align-items: center; gap: .4rem;
    padding: .45rem 1rem;
    background: #e8711a; color: #fff;
    border: none; border-radius: 8px;
    font-size: .82rem; font-weight: 700;
    cursor: pointer; transition: background .15s;
    font-family: inherit;
}
.btn-recorrect:hover { background: #c9600f; }

/* ── Correction history per question ──────────────────────────────── */
.q-history-block { margin: .5rem 0 .75rem; display: flex; flex-direction: column; gap: .35rem; }
.hist-entry {
    background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px;
    padding: .55rem .85rem; display: flex; flex-wrap: wrap; align-items: center; gap: .45rem;
}
.hist-round-label { font-size: .73rem; font-weight: 700; color: #475569; flex: 1 1 100%; margin-bottom: .2rem; }
.hist-comment { font-size: .78rem; color: #64748b; font-style: italic; flex: 1 1 100%; margin-top: .2rem; }
.hist-new-label {
    font-size: .73rem; font-weight: 700; color: #0b3c6e;
    padding: .35rem 0 0; border-top: 1px dashed #c7d2e0; margin-top: .15rem;
    letter-spacing: .2px; text-transform: uppercase;
}

/* ── Candidate info bar ────────────────────────────────────────────── */
.cand-info-bar {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
    margin-bottom: 0;
}
.cand-chip {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    background: #fff;
    border-radius: 20px;
    padding: .3rem .85rem;
    font-size: .8rem;
    color: #555;
    box-shadow: 0 1px 4px rgba(0,0,0,.07);
}
.cand-chip i { color: #0b3c6e; font-size: .75rem; }
.chip-warn  { background: rgba(255,140,0,.1); color: #b06a00; }
.chip-warn i { color: #b06a00; }
.chip-danger { background: rgba(220,53,69,.1); color: #dc3545; }
.chip-ok     { background: rgba(25,135,84,.1); color: #198754; }

/* ── Main body layout ──────────────────────────────────────────────── */
.corr-body {
    display: flex;
    gap: 1.5rem;
    align-items: flex-start;
}
.corr-main {
    flex: 1 1 0;
    min-width: 0;
}
.corr-sidebar {
    width: 300px;
    flex-shrink: 0;
    position: sticky;
    top: 10.5rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

/* ── Section block ─────────────────────────────────────────────────── */
.section-block { margin-bottom: 1rem; }
.section-header {
    font-size: .92rem;
    font-weight: 800;
    color: #0b3c6e;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: .85rem 1.25rem;
    background: rgba(255, 255, 255, 0.93);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border-left: 4px solid #0b3c6e;
    border-radius: 10px;
    margin-bottom: 1.25rem;
    box-shadow: 0 4px 12px rgba(0,0,0,.05);
    display: flex;
    align-items: center;
}

/* ── Question card ─────────────────────────────────────────────────── */
.q-card {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 1px 6px rgba(0,0,0,.06);
    padding: 1.2rem 1.4rem;
    margin-bottom: .75rem;
    border-left: 4px solid transparent;
    transition: border-color .2s;
}
.q-card.graded-correct   { border-color: #198754; }
.q-card.graded-partiel   { border-color: #ff8c00; }
.q-card.graded-incorrect { border-color: #dc3545; }

.q-header {
    display: flex;
    align-items: flex-start;
    gap: .75rem;
    margin-bottom: .9rem;
}
.q-num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px; height: 26px;
    background: #edf0f4;
    border-radius: 8px;
    font-size: .75rem;
    font-weight: 800;
    color: #555;
    flex-shrink: 0;
    margin-top: 1px;
}
.q-text {
    font-size: .9rem;
    font-weight: 700;
    color: #0b3c6e;
    flex: 1;
    line-height: 1.45;
}
.q-verdict-indicator {
    font-size: 1.2rem;
    flex-shrink: 0;
    width: 26px;
    text-align: center;
}

/* ── Answer block ──────────────────────────────────────────────────── */
.q-answer-block { margin-bottom: 1rem; }
.q-answer-label {
    font-size: .7rem;
    font-weight: 700;
    color: #aaa;
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-bottom: .35rem;
}
.q-answer-text {
    background: #f8fafc;
    border-left: 3px solid #dde3ec;
    padding: .6rem .9rem;
    border-radius: 0 8px 8px 0;
    font-size: .875rem;
    color: #333;
    white-space: pre-wrap;
    line-height: 1.5;
    min-height: 2.5rem;
}
.q-answer-text.empty { color: #bbb; font-style: italic; }

/* ── Grade row ─────────────────────────────────────────────────────── */
.q-grade-row {
    display: flex;
    align-items: center;
    gap: .5rem;
    flex-wrap: wrap;
}
.verdict-btn {
    border: 2px solid transparent;
    border-radius: 8px;
    padding: .35rem .9rem;
    font-size: .8rem;
    font-weight: 700;
    cursor: pointer;
    background: #f1f3f5;
    color: #555;
    transition: all .15s;
}
.verdict-btn:hover { opacity: .85; }
.btn-correct:hover,  .btn-correct.active  { background: rgba(25,135,84,.12);  border-color: #198754; color: #198754; }
.btn-partiel:hover,  .btn-partiel.active  { background: rgba(255,140,0,.12);  border-color: #ff8c00; color: #b06a00; }
.btn-incorrect:hover,.btn-incorrect.active { background: rgba(220,53,69,.12); border-color: #dc3545; color: #dc3545; }
.save-indicator {
    font-size: .78rem;
    font-weight: 600;
    color: #888;
    margin-left: .25rem;
}

/* ── Comment area ──────────────────────────────────────────────────── */
.q-comment-row { margin-top: .7rem; }
.q-comment-area {
    width: 100%;
    border: 1.5px solid #dde3ec;
    border-radius: 8px;
    padding: .5rem .75rem;
    font-size: .82rem;
    color: #333;
    resize: vertical;
    font-family: inherit;
    transition: border-color .15s;
    background: #f8fafc;
}
.q-comment-area:focus { outline: none; border-color: #0b3c6e; background: #fff; }

/* ── Read-only state ───────────────────────────────────────────────── */
.q-grade-row.readonly { flex-direction: column; align-items: flex-start; }
.verdict-chip {
    display: inline-block;
    padding: .28rem .75rem;
    border-radius: 20px;
    font-size: .78rem;
    font-weight: 700;
}
.chip-correct   { background: rgba(25,135,84,.12);  color: #198754; }
.chip-partiel   { background: rgba(255,140,0,.12);  color: #b06a00; }
.chip-incorrect { background: rgba(220,53,69,.12);  color: #dc3545; }
.chip-empty     { background: #f1f3f5;              color: #aaa; }
.readonly-comment {
    margin-top: .5rem;
    font-size: .82rem;
    color: #555;
    font-style: italic;
    padding: .4rem .7rem;
    background: #f8fafc;
    border-radius: 6px;
}

/* ── Sidebar cards ─────────────────────────────────────────────────── */
.sidebar-card {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 1px 8px rgba(0,0,0,.07);
    padding: 1.1rem 1.2rem;
}
.sidebar-card-title {
    font-size: .8rem;
    font-weight: 800;
    color: #0b3c6e;
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-bottom: .9rem;
}

/* ── Score grid ────────────────────────────────────────────────────── */
.score-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: .6rem;
    margin-bottom: .9rem;
}
.score-item {
    text-align: center;
    padding: .6rem .3rem;
    border-radius: 10px;
}
.score-item.score-correct   { background: rgba(25,135,84,.08); }
.score-item.score-partiel   { background: rgba(255,140,0,.1); }
.score-item.score-incorrect { background: rgba(220,53,69,.08); }
.score-val {
    font-size: 1.6rem;
    font-weight: 800;
    line-height: 1;
}
.score-correct   .score-val { color: #198754; }
.score-partiel   .score-val { color: #b06a00; }
.score-incorrect .score-val { color: #dc3545; }
.score-lbl { font-size: .7rem; color: #888; font-weight: 600; margin-top: .15rem; }
.score-progress {
    background: #edf0f4;
    border-radius: 99px;
    height: 6px;
    overflow: hidden;
    margin-bottom: .45rem;
}
.score-progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #0b3c6e, #2563eb);
    border-radius: 99px;
    transition: width .4s ease;
}
.score-total-label {
    font-size: .75rem;
    color: #888;
    text-align: right;
    font-weight: 600;
}

/* ── Correctors list ───────────────────────────────────────────────── */
.correctors-list {
    list-style: none;
    margin: 0 0 .75rem;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: .5rem;
}
.corrector-item {
    display: flex;
    align-items: center;
    gap: .6rem;
}
.corrector-avatar {
    width: 32px; height: 32px;
    background: #0b3c6e;
    color: #fff;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: .8rem;
    font-weight: 800;
    flex-shrink: 0;
}
.corrector-info { flex: 1; min-width: 0; }
.corrector-name  { font-size: .8rem; font-weight: 700; color: #222; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.corrector-email { font-size: .7rem; color: #aaa; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.corrector-remove {
    background: none; border: none; color: #ccc; cursor: pointer;
    font-size: .85rem; padding: .2rem; border-radius: 4px;
    transition: color .15s;
    flex-shrink: 0;
}
.corrector-remove:hover { color: #dc3545; }
.add-corrector-form {
    display: flex;
    gap: .4rem;
}
.corr-input {
    flex: 1;
    border: 1.5px solid #dde3ec;
    border-radius: 8px;
    padding: .38rem .6rem;
    font-size: .78rem;
    color: #333;
    font-family: inherit;
}
.corr-input:focus { outline: none; border-color: #0b3c6e; }
.btn-add-corr {
    background: #0b3c6e;
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: .38rem .7rem;
    font-size: .78rem;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
}
.btn-add-corr:hover { background: #083060; }
.add-corr-error { font-size: .75rem; color: #dc3545; margin-top: .3rem; min-height: 1rem; }

/* ── Global comment ────────────────────────────────────────────────── */
.global-comment-area {
    width: 100%;
    border: 1.5px solid #dde3ec;
    border-radius: 8px;
    padding: .6rem .75rem;
    font-size: .82rem;
    color: #333;
    resize: vertical;
    font-family: inherit;
    background: #f8fafc;
    transition: border-color .15s;
}
.global-comment-area:focus { outline: none; border-color: #0b3c6e; background: #fff; }
.save-comment-indicator { font-size: .72rem; color: #198754; font-weight: 600; margin-top: .3rem; min-height: 1rem; }
.readonly-global-comment { font-size: .85rem; color: #555; font-style: italic; line-height: 1.5; }

/* ── Completed card ────────────────────────────────────────────────── */
.completed-card { text-align: center; }
.completed-checkmark { font-size: 2.2rem; color: #198754; margin-bottom: .4rem; }
.completed-text { font-size: 1rem; font-weight: 800; color: #198754; }
.completed-by   { font-size: .78rem; color: #555; margin-top: .25rem; }
.completed-at   { font-size: .72rem; color: #aaa; }

/* ── Responsive ────────────────────────────────────────────────────── */
@media (max-width: 900px) {
    .corr-body { flex-direction: column; }
    .corr-sidebar { width: 100%; position: static; }
}
</style>

<div class="corr-page">

    <!-- Header card -->
    <div class="header-card">
    <!-- Top bar -->
    <div class="corr-topbar">
        <a href="responses.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
        <div class="breadcrumb-bar">
            <a href="responses.php">Réponses</a>
            <span class="bc-sep">›</span>
            <span class="bc-current"><?php echo htmlspecialchars($candidateName, ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="bc-sep">›</span>
            <span class="bc-current"><?php echo htmlspecialchars($candidateBrand, ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="status-badge status-<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>
            </span>
        </div>
        <?php if ($status !== 'completed'): ?>
        <button class="btn-complete" id="btnComplete" onclick="completeCorrection()">
            <i class="fas fa-check-circle"></i> Terminer la correction
        </button>
        <?php else: ?>
        <span class="completed-info">
            <i class="fas fa-check-circle"></i>
            Terminée par <?php echo htmlspecialchars($completedBy, ENT_QUOTES, 'UTF-8'); ?>
            le <?php echo htmlspecialchars($completedAt, ENT_QUOTES, 'UTF-8'); ?>
        </span>
        <button class="btn-recorrect" onclick="startRecorrection()">
            <i class="fas fa-redo"></i> Recorriger
        </button>
        <?php endif; ?>
    </div>

    <!-- Candidate info chips -->
    <div class="cand-info-bar" style="padding-top:.6rem;border-top:1px solid #f0f3f7;">
        <div class="cand-chip"><i class="fas fa-envelope me-1" style="color: #3b82f6;"></i><?php echo htmlspecialchars($candidateEmail, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="cand-chip"><i class="fas fa-globe me-1" style="color: #10b981;"></i><?php echo htmlspecialchars($candidateCountry, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="cand-chip"><i class="fas fa-clock me-1" style="color: #6366f1;"></i><?php echo htmlspecialchars($timeStr, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="cand-chip <?php echo $violations > 0 ? 'chip-warn' : ''; ?>">
            <i class="fas fa-exclamation-triangle me-1" style="color: #f59e0b;"></i><?php echo $violations; ?> violation(s)
        </div>
        <div class="cand-chip <?php echo $isBanned ? 'chip-danger' : 'chip-ok'; ?>">
            <?php echo $isBanned ? '<i class="fas fa-ban me-1" style="color: #ef4444;"></i> Session bannie' : '<i class="fas fa-check-circle me-1" style="color: #10b981;"></i> Session complète'; ?>
        </div>
        <div class="cand-chip"><i class="fas fa-calendar-alt me-1" style="color: #8b5cf6;"></i><?php echo htmlspecialchars($submittedAt, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
    </div><!-- /.header-card -->

    <!-- Main body -->
    <div class="corr-body">

        <!-- Questions column -->
        <div class="corr-main">
            <?php
            $qCounter = 0;
            foreach ($sections as $sec):
                $sectionQuestions = $sec['questions'];
            ?>
            <div class="section-block">
                <div class="section-header">
                    <i class="fas fa-layer-group me-2" style="color: #0b3c6e; opacity: 0.85;"></i>
                    <?php echo htmlspecialchars($sec['section'], ENT_QUOTES, 'UTF-8'); ?>
                </div>

                <?php foreach ($sectionQuestions as $qi => $q):
                    $qid     = $q['id'];
                    $answer  = isset($answers[$qid]) ? $answers[$qid] : '';
                    $grade   = isset($grades[$qid]) ? (array)$grades[$qid] : [];
                    $verdict = $grade['verdict'] ?? '';
                    $gcomment= $grade['comment'] ?? '';
                    $qCounter++;

                    $cardClass = '';
                    if ($verdict === 'correct')   $cardClass = 'graded-correct';
                    elseif ($verdict === 'partiel')   $cardClass = 'graded-partiel';
                    elseif ($verdict === 'incorrect') $cardClass = 'graded-incorrect';

                    $indIcon = '';
                    if ($verdict === 'correct')   $indIcon = '<i class="fas fa-check-circle" style="color:#198754;"></i>';
                    elseif ($verdict === 'partiel')   $indIcon = '<i class="fas fa-exclamation-circle" style="color:#ff8c00;"></i>';
                    elseif ($verdict === 'incorrect') $indIcon = '<i class="fas fa-times-circle" style="color:#dc3545;"></i>';
                ?>
                <div class="q-card <?php echo $cardClass; ?>" id="card-<?php echo $qid; ?>" data-qid="<?php echo $qid; ?>">
                    <div class="q-header">
                        <span class="q-num"><?php echo $qCounter; ?></span>
                        <div class="q-text"><?php echo htmlspecialchars($q['question'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="q-verdict-indicator" id="ind-<?php echo $qid; ?>"><?php echo $indIcon; ?></div>
                    </div>

                    <div class="q-answer-block">
                        <div class="q-answer-label">Réponse du candidat</div>
                        <div class="q-answer-text <?php echo empty($answer) ? 'empty' : ''; ?>">
                            <?php echo empty($answer) ? 'Sans réponse' : htmlspecialchars($answer, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    </div>

                    <?php if (!empty($correctionHistory)): ?>
                    <div class="q-history-block">
                        <?php foreach ($correctionHistory as $hRaw):
                            $hEntry  = (array)$hRaw;
                            $hRound  = (int)($hEntry['round'] ?? 1);
                            $hGrades = (array)($hEntry['grades'] ?? []);
                            $hGrade  = isset($hGrades[$qid]) ? (array)$hGrades[$qid] : [];
                            $hV      = $hGrade['verdict'] ?? '';
                            $hCmt    = $hGrade['comment'] ?? '';
                            $hBy     = $hGrade['by']      ?? '';
                            $hAt     = $hGrade['at']      ?? '';
                            $hChipCls = ($hV === 'correct') ? 'chip-correct' : (($hV === 'partiel') ? 'chip-partiel' : (($hV === 'incorrect') ? 'chip-incorrect' : 'chip-empty'));
                        ?>
                        <div class="hist-entry">
                            <div class="hist-round-label">
                                Correction <?php echo $hRound; ?>
                                <?php if ($hBy): ?> &mdash; <span style="font-weight:600"><?php echo htmlspecialchars($hBy, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                                <?php if ($hAt): ?> <span style="opacity:.55;font-size:.72rem;font-weight:400;">(<?php echo htmlspecialchars($hAt, ENT_QUOTES, 'UTF-8'); ?>)</span><?php endif; ?>
                            </div>
                            <span class="verdict-chip <?php echo $hChipCls; ?>">
                                <?php if ($hV === 'correct'): ?><i class="fas fa-check-circle me-1" style="color:#198754;"></i> Correct
                                <?php elseif ($hV === 'partiel'): ?><i class="fas fa-exclamation-circle me-1" style="color:#ff8c00;"></i> Partiel
                                <?php elseif ($hV === 'incorrect'): ?><i class="fas fa-times-circle me-1" style="color:#dc3545;"></i> Incorrect
                                <?php else: ?>— Non noté<?php endif; ?>
                            </span>
                            <?php if (!empty($hCmt)): ?>
                            <div class="hist-comment"><?php echo htmlspecialchars($hCmt, ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        <?php if ($status !== 'completed'): ?>
                        <div class="hist-new-label"><i class="fas fa-pencil-alt me-1"></i> Correction <?php echo $correctionRound; ?> (en cours)</div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($status !== 'completed'): ?>
                    <div class="q-grade-row">
                        <button class="verdict-btn btn-correct <?php echo $verdict === 'correct'   ? 'active' : ''; ?>"
                                data-verdict="correct"
                                onclick="setVerdict('<?php echo $qid; ?>', 'correct', this)"><i class="fas fa-check me-1" style="color: #198754;"></i> Correct</button>
                        <button class="verdict-btn btn-partiel <?php echo $verdict === 'partiel'   ? 'active' : ''; ?>"
                                data-verdict="partiel"
                                onclick="setVerdict('<?php echo $qid; ?>', 'partiel', this)"><i class="fas fa-exclamation me-1" style="color: #ff8c00;"></i> Partiel</button>
                        <button class="verdict-btn btn-incorrect <?php echo $verdict === 'incorrect' ? 'active' : ''; ?>"
                                data-verdict="incorrect"
                                onclick="setVerdict('<?php echo $qid; ?>', 'incorrect', this)"><i class="fas fa-times me-1" style="color: #dc3545;"></i> Incorrect</button>
                        <span class="save-indicator" id="si-<?php echo $qid; ?>"></span>
                    </div>
                    <div class="q-comment-row">
                        <textarea class="q-comment-area"
                                  id="cmt-<?php echo $qid; ?>"
                                  placeholder="Commentaire (optionnel)..."
                                  rows="2"
                                  onblur="onCommentBlur('<?php echo $qid; ?>')"
                        ><?php echo htmlspecialchars($gcomment, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <?php else: ?>
                    <div class="q-grade-row readonly">
                        <?php if ($verdict === 'correct'): ?>
                            <span class="verdict-chip chip-correct"><i class="fas fa-check-circle me-1" style="color: #198754;"></i> Correct</span>
                        <?php elseif ($verdict === 'partiel'): ?>
                            <span class="verdict-chip chip-partiel"><i class="fas fa-exclamation-circle me-1" style="color: #ff8c00;"></i> Partiel</span>
                        <?php elseif ($verdict === 'incorrect'): ?>
                            <span class="verdict-chip chip-incorrect"><i class="fas fa-times-circle me-1" style="color: #dc3545;"></i> Incorrect</span>
                        <?php else: ?>
                            <span class="verdict-chip chip-empty">— Non noté</span>
                        <?php endif; ?>
                        <?php if (!empty($gcomment)): ?>
                        <div class="readonly-comment"><?php echo htmlspecialchars($gcomment, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Sidebar -->
        <div class="corr-sidebar">

            <!-- Score -->
            <div class="sidebar-card">
                <div class="sidebar-card-title"><i class="fas fa-chart-pie me-2"></i>Score</div>
                <div class="score-grid">
                    <div class="score-item score-correct">
                        <div class="score-val" id="sc-correct"><?php echo $scoreCorrect; ?></div>
                        <div class="score-lbl">Correct</div>
                    </div>
                    <div class="score-item score-partiel">
                        <div class="score-val" id="sc-partiel"><?php echo $scorePartiel; ?></div>
                        <div class="score-lbl">Partiel</div>
                    </div>
                    <div class="score-item score-incorrect">
                        <div class="score-val" id="sc-incorrect"><?php echo $scoreIncorrect; ?></div>
                        <div class="score-lbl">Incorrect</div>
                    </div>
                </div>
                <?php $progPct = $TOTAL_QUESTIONS > 0 ? round($scoreTotal / $TOTAL_QUESTIONS * 100) : 0; ?>
                <div class="score-progress">
                    <div class="score-progress-bar" id="sc-bar" style="width:<?php echo $progPct; ?>%"></div>
                </div>
                <div class="score-total-label" id="sc-label">
                    <?php echo $scoreTotal; ?> / <?php echo $TOTAL_QUESTIONS; ?> questions notées
                </div>
            </div>

            <!-- Correctors -->
            <div class="sidebar-card">
                <div class="sidebar-card-title"><i class="fas fa-users-cog me-2"></i>Correcteurs</div>
                <ul class="correctors-list" id="correctorsList">
                    <?php foreach ($correctors as $c):
                        $ca = (array)$c;
                        $cEmail = $ca['email'] ?? '';
                        $cName  = $ca['name']  ?? $cEmail;
                        $cAvatar = strtoupper(mb_substr($cEmail, 0, 1, 'UTF-8'));
                    ?>
                    <li class="corrector-item" id="corr-<?php echo htmlspecialchars(md5($cEmail), ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="corrector-avatar"><?php echo $cAvatar; ?></div>
                        <div class="corrector-info">
                            <div class="corrector-name"><?php echo htmlspecialchars($cName, ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="corrector-email"><?php echo htmlspecialchars($cEmail, ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                        <?php if ($status !== 'completed'): ?>
                        <button class="corrector-remove"
                                onclick="removeCorrector('<?php echo htmlspecialchars($cEmail, ENT_QUOTES, 'UTF-8'); ?>', this)"
                                title="Retirer">
                            <i class="fas fa-times"></i>
                        </button>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>

                <?php if ($status !== 'completed'): ?>
                <div class="add-corrector-form">
                    <input type="email" id="newCorrEmail" class="corr-input" placeholder="Email du correcteur...">
                    <button class="btn-add-corr" onclick="addCorrector()"><i class="fas fa-plus"></i></button>
                </div>
                <div class="add-corr-error" id="addCorrError"></div>
                <?php endif; ?>
            </div>

            <!-- Global comment -->
            <?php if ($status !== 'completed'): ?>
            <div class="sidebar-card">
                <div class="sidebar-card-title"><i class="fas fa-comment-alt me-2"></i>Commentaire général</div>
                <textarea class="global-comment-area"
                          id="globalComment"
                          placeholder="Remarques générales sur cette correction..."
                          rows="4"
                          onblur="saveGlobalComment()"
                ><?php echo htmlspecialchars($globalComment, ENT_QUOTES, 'UTF-8'); ?></textarea>
                <div class="save-comment-indicator" id="gcSaveInd"></div>
            </div>
            <?php elseif (!empty($globalComment)): ?>
            <div class="sidebar-card">
                <div class="sidebar-card-title"><i class="fas fa-comment-alt me-2"></i>Commentaire général</div>
                <div class="readonly-global-comment"><?php echo htmlspecialchars($globalComment, ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <?php endif; ?>

            <!-- Completed confirmation -->
            <?php if ($status === 'completed'): ?>
            <div class="sidebar-card completed-card">
                <div class="completed-checkmark"><i class="fas fa-check-circle"></i></div>
                <div class="completed-text">Correction terminée</div>
                <?php if (!empty($completedBy)): ?>
                <div class="completed-by">par <?php echo htmlspecialchars($completedBy, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                <?php if (!empty($completedAt)): ?>
                <div class="completed-at"><?php echo htmlspecialchars($completedAt, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- Re-correction modal -->
<div id="recorrModal" style="display:none;position:fixed;inset:0;z-index:1000;align-items:center;justify-content:center;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);">
    <div style="background:#fff;border-radius:18px;padding:2rem 2rem 1.75rem;max-width:420px;width:90%;box-shadow:0 24px 64px rgba(0,0,0,.3);text-align:center;">
        <div style="font-size:2.2rem;color:#e8711a;margin-bottom:.6rem;"><i class="fas fa-redo"></i></div>
        <div style="font-size:1.05rem;font-weight:800;color:#0b3c6e;margin-bottom:.5rem;">Démarrer une re-correction ?</div>
        <div style="font-size:.875rem;color:#64748b;line-height:1.55;margin-bottom:1.5rem;">
            La correction précédente sera <strong>archivée</strong> et visible question par question.<br>Vous pourrez saisir un nouveau verdict pour chaque réponse.
        </div>
        <div style="display:flex;gap:.75rem;justify-content:center;">
            <button onclick="closeRecorrModal()" style="padding:.6rem 1.4rem;border-radius:10px;border:1.5px solid #e2e8f0;background:#fff;color:#64748b;font-size:.88rem;font-weight:600;cursor:pointer;font-family:inherit;">Annuler</button>
            <button onclick="doStartRecorrection()" style="padding:.6rem 1.4rem;border-radius:10px;border:none;background:#e8711a;color:#fff;font-size:.88rem;font-weight:700;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:.4rem;"><i class="fas fa-redo"></i> Démarrer</button>
        </div>
    </div>
</div>

<!-- Confirmation modal -->
<div id="completeModal" style="display:none;position:fixed;inset:0;z-index:1000;align-items:center;justify-content:center;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);">
    <div style="background:#fff;border-radius:18px;padding:2rem 2rem 1.75rem;max-width:420px;width:90%;box-shadow:0 24px 64px rgba(0,0,0,.3);text-align:center;">
        <div style="font-size:2.2rem;color:#0b3c6e;margin-bottom:.6rem;"><i class="fas fa-check-circle"></i></div>
        <div style="font-size:1.05rem;font-weight:800;color:#0b3c6e;margin-bottom:.5rem;">Terminer la correction ?</div>
        <div style="font-size:.875rem;color:#64748b;line-height:1.55;margin-bottom:1.5rem;">
            Cette action est <strong>irréversible</strong>.<br>Un email de synthèse sera envoyé à tous les correcteurs.
        </div>
        <div id="completeModalError" style="display:none;font-size:.82rem;color:#dc3545;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:.5rem .8rem;margin-bottom:1rem;text-align:left;"></div>
        <div style="display:flex;gap:.75rem;justify-content:center;">
            <button onclick="closeCompleteModal()"
                    style="padding:.6rem 1.4rem;border-radius:8px;border:1.5px solid #dde3ec;background:#fff;color:#555;font-size:.875rem;font-weight:600;cursor:pointer;font-family:inherit;transition:all .15s;">
                Annuler
            </button>
            <button onclick="doCompleteCorrection()"
                    style="padding:.6rem 1.5rem;border-radius:8px;border:none;background:#0b3c6e;color:#fff;font-size:.875rem;font-weight:700;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:.45rem;transition:all .15s;">
                <i class="fas fa-check"></i> Confirmer
            </button>
        </div>
    </div>
</div>

<script>
var RESPONSE_ID   = '<?php echo htmlspecialchars($responseId, ENT_QUOTES, 'UTF-8'); ?>';
var TOTAL_Q       = <?php echo (int)$TOTAL_QUESTIONS; ?>;

// ── Grading ──────────────────────────────────────────────────────────────────
function setVerdict(qid, verdict, btn) {
    var card = document.getElementById('card-' + qid);
    var btns = card.querySelectorAll('.verdict-btn');
    for (var i = 0; i < btns.length; i++) {
        btns[i].classList.remove('active');
    }
    btn.classList.add('active');

    var icons = {
        correct: '<i class="fas fa-check-circle" style="color:#198754;"></i>',
        partiel: '<i class="fas fa-exclamation-circle" style="color:#ff8c00;"></i>',
        incorrect: '<i class="fas fa-times-circle" style="color:#dc3545;"></i>'
    };
    var ind = document.getElementById('ind-' + qid);
    if (ind) ind.innerHTML = icons[verdict] || '';

    // Update card border class
    card.classList.remove('graded-correct', 'graded-partiel', 'graded-incorrect');
    card.classList.add('graded-' + verdict);

    updateScoreLocally();
    saveGrade(qid, verdict);
}

function getActiveVerdict(qid) {
    var card = document.getElementById('card-' + qid);
    if (!card) return '';
    var activeBtn = card.querySelector('.verdict-btn.active');
    return activeBtn ? activeBtn.getAttribute('data-verdict') : '';
}

function onCommentBlur(qid) {
    var verdict = getActiveVerdict(qid);
    if (verdict) saveGrade(qid, verdict);
}

function saveGrade(qid, verdict) {
    var cmtEl   = document.getElementById('cmt-' + qid);
    var comment = cmtEl ? cmtEl.value : '';
    var si      = document.getElementById('si-' + qid);
    if (si) { si.innerHTML = '<i class="fas fa-spinner fa-spin" style="color:#aaa;"></i>'; }

    var formData = new FormData();
    formData.append('qid',     qid);
    formData.append('verdict', verdict);
    formData.append('comment', comment);

    fetch('correction.php?action=save_grade&id=' + RESPONSE_ID, {
        method: 'POST',
        body:   formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.ok) {
            if (si) {
                si.innerHTML = '<i class="fas fa-check" style="color:#198754;"></i> Enregistré';
                setTimeout(function() { if (si) si.innerHTML = ''; }, 2000);
            }
            updateScore(data.score);
        } else {
            if (si) { si.innerHTML = '<i class="fas fa-exclamation-triangle" style="color:#dc3545;"></i> Erreur'; }
        }
    })
    .catch(function() {
        if (si) { si.innerHTML = '<i class="fas fa-times" style="color:#dc3545;"></i>'; }
    });
}

// ── Score display ─────────────────────────────────────────────────────────────
function updateScoreLocally() {
    var correct = 0, partiel = 0, incorrect = 0;
    var allActive = document.querySelectorAll('.verdict-btn.active');
    for (var i = 0; i < allActive.length; i++) {
        var v = allActive[i].getAttribute('data-verdict');
        if (v === 'correct')   correct++;
        else if (v === 'partiel')   partiel++;
        else if (v === 'incorrect') incorrect++;
    }
    updateScore({correct: correct, partiel: partiel, incorrect: incorrect, total: correct + partiel + incorrect});
}

function updateScore(score) {
    var correct   = score.correct   || 0;
    var partiel   = score.partiel   || 0;
    var incorrect = score.incorrect || 0;
    var total     = score.total     || 0;

    var elC = document.getElementById('sc-correct');
    var elP = document.getElementById('sc-partiel');
    var elI = document.getElementById('sc-incorrect');
    var elL = document.getElementById('sc-label');
    var elB = document.getElementById('sc-bar');

    if (elC) elC.textContent = correct;
    if (elP) elP.textContent = partiel;
    if (elI) elI.textContent = incorrect;
    if (elL) elL.textContent = total + ' / ' + TOTAL_Q + ' questions notées';
    if (elB) elB.style.width = (TOTAL_Q > 0 ? Math.round(total / TOTAL_Q * 100) : 0) + '%';
}

// ── Correctors ────────────────────────────────────────────────────────────────
function addCorrector() {
    var input = document.getElementById('newCorrEmail');
    var errEl = document.getElementById('addCorrError');
    var email = input ? input.value.trim() : '';
    if (errEl) errEl.textContent = '';

    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        if (errEl) errEl.textContent = 'Veuillez entrer une adresse email valide.';
        return;
    }

    var formData = new FormData();
    formData.append('email', email);

    fetch('correction.php?action=add_corrector&id=' + RESPONSE_ID, {
        method: 'POST',
        body:   formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (!data.ok) {
            if (errEl) errEl.textContent = data.error || 'Erreur';
            return;
        }
        if (data.already_in) {
            if (errEl) errEl.textContent = 'Ce correcteur est déjà dans la liste.';
            return;
        }
        // Add to list
        var list   = document.getElementById('correctorsList');
        var avatar = email.charAt(0).toUpperCase();
        var li     = document.createElement('li');
        li.className = 'corrector-item';
        li.id = 'corr-' + md5Simple(email);
        li.innerHTML =
            '<div class="corrector-avatar">' + avatar + '</div>' +
            '<div class="corrector-info">' +
                '<div class="corrector-name">' + escHtml(email) + '</div>' +
                '<div class="corrector-email">' + escHtml(email) + '</div>' +
            '</div>' +
            '<button class="corrector-remove" onclick="removeCorrector(\'' + email.replace(/'/g, "\\'") + '\', this)" title="Retirer">' +
                '<i class="fas fa-times"></i>' +
            '</button>';
        list.appendChild(li);
        if (input) input.value = '';
    })
    .catch(function() {
        if (errEl) errEl.textContent = 'Erreur réseau.';
    });
}

function removeCorrector(email, btn) {
    var li = btn.closest('li');
    var formData = new FormData();
    formData.append('email', email);

    fetch('correction.php?action=remove_corrector&id=' + RESPONSE_ID, {
        method: 'POST',
        body:   formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.ok && li) li.remove();
    });
}

// ── Global comment ────────────────────────────────────────────────────────────
function saveGlobalComment() {
    var ta  = document.getElementById('globalComment');
    var ind = document.getElementById('gcSaveInd');
    if (!ta) return;

    var formData = new FormData();
    formData.append('comment', ta.value);

    fetch('correction.php?action=save_comment&id=' + RESPONSE_ID, {
        method: 'POST',
        body:   formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.ok && ind) {
            ind.innerHTML = '<i class="fas fa-check" style="color:#198754;"></i> Enregistré';
            setTimeout(function() { if (ind) ind.innerHTML = ''; }, 2000);
        }
    });
}

// ── Re-correction ─────────────────────────────────────────────────────────────
function startRecorrection() {
    document.getElementById('recorrModal').style.display = 'flex';
}
function closeRecorrModal() {
    document.getElementById('recorrModal').style.display = 'none';
}
function doStartRecorrection() {
    closeRecorrModal();
    fetch('correction.php?action=start_recorrection&id=' + RESPONSE_ID, { method: 'POST' })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.ok) {
            location.reload();
        } else {
            alert('Erreur : ' + (data.error || 'Inconnue'));
        }
    })
    .catch(function() { alert('Erreur réseau.'); });
}

// ── Complete ──────────────────────────────────────────────────────────────────
function completeCorrection() {
    var errEl = document.getElementById('completeModalError');
    if (errEl) { errEl.textContent = ''; errEl.style.display = 'none'; }
    document.getElementById('completeModal').style.display = 'flex';
}

function closeCompleteModal() {
    document.getElementById('completeModal').style.display = 'none';
}

function doCompleteCorrection() {
    closeCompleteModal();
    var btn = document.getElementById('btnComplete');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi en cours...'; }

    fetch('correction.php?action=complete&id=' + RESPONSE_ID, {
        method: 'POST'
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.ok) {
            if (data.score) updateScore(data.score);
            setTimeout(function() { location.reload(); }, 800);
        } else {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check-circle"></i> Terminer la correction'; }
            document.getElementById('completeModal').style.display = 'flex';
            var errEl = document.getElementById('completeModalError');
            if (errEl) { errEl.textContent = 'Erreur : ' + (data.error || 'Inconnue'); errEl.style.display = 'block'; }
        }
    })
    .catch(function() {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check-circle"></i> Terminer la correction'; }
    });
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function md5Simple(str) {
    var hash = 0;
    for (var i = 0; i < str.length; i++) {
        hash = ((hash << 5) - hash) + str.charCodeAt(i);
        hash |= 0;
    }
    return Math.abs(hash).toString(16);
}
</script>

    </div><!-- /.page-inner -->
</div><!-- /.page-bg-wrap -->
<footer class="page-footer">
    <?php echo date('Y'); ?>&copy; CFAO Mobility Academy &mdash; All rights reserved
</footer>
<script>
(function() {
    function ping() { fetch('ping.php', { method: 'POST' }).catch(function() {}); }
    ping();
    setInterval(ping, 60000);
})();
</script>
</body>
</html>
