<?php
// Utilisateurs connectés, d'après connections.lastSeen (core/presence.php). Admin : sa filiale ; Super Admin : tout.
// Filtre profil « Candidat » : suivi des épreuves (avancement, temps restant, anomalies) au lieu de la liste simple.
session_start();

if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
}

$profile = $_SESSION["profile"] ?? '';
if (!in_array($profile, ['Super Admin', 'Admin'], true)) {
    header("Location: ../portal.php");
    exit();
}

require_once __DIR__ . '/../../core/medacap_config.php';
require_once "../../vendor/autoload.php";
include_once "includes/language.php";
include_once __DIR__ . "/../partials/background-manager.php";
setPageBackground("bg-dashboard");

$cuOnlineSeconds = defined('MEDACAP_PRESENCE_ONLINE_SECONDS') ? MEDACAP_PRESENCE_ONLINE_SECONDS : 900;
$cuPeriods = [15 => '15 dernières minutes', 60 => 'Dernière heure', 1440 => '24 dernières heures', 10080 => '7 derniers jours'];
$cuIsSuperAdmin = $profile === 'Super Admin';
$cuSubsidiary = $cuIsSuperAdmin ? trim((string) ($_GET['subsidiary'] ?? '')) : (string) ($_SESSION['subsidiary'] ?? '');
$cuProfile = trim((string) ($_GET['profile'] ?? ''));
$cuExamView = $cuProfile === 'Candidat';
// Suivi d'épreuve : 24 h par défaut, pour garder à l'écran un candidat déconnecté depuis plus de 15 min.
$cuMinutes = isset($_GET['period']) && isset($cuPeriods[(int) $_GET['period']]) ? (int) $_GET['period'] : ($cuExamView ? 1440 : 15);

$pipeline = [
    ['$match' => ['lastSeen' => ['$gte' => new MongoDB\BSON\UTCDateTime((time() - $cuMinutes * 60) * 1000)]]],
    ['$lookup' => ['from' => 'users', 'localField' => 'user', 'foreignField' => '_id', 'as' => 'u']],
    ['$unwind' => '$u'],
];
if (!$cuIsSuperAdmin || $cuSubsidiary !== '') {
    $pipeline[] = ['$match' => ['u.subsidiary' => $cuSubsidiary]];
}
if ($cuProfile !== '') {
    $pipeline[] = ['$match' => ['u.profile' => $cuProfile]];
}
$pipeline[] = ['$sort' => ['lastSeen' => -1]];
$pipeline[] = ['$limit' => 500];

$academy = (new MongoDB\Client(medacapMongoUri()))->academy;

// Bouton « Déconnecter » : révoque toutes les sessions de l'utilisateur (vérifié par core/presence.php à chaque page).
if (empty($_SESSION['cuToken'])) {
    $_SESSION['cuToken'] = bin2hex(random_bytes(16));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kick'])) {
    $cuKickId = (string) $_POST['kick'];
    $cuTarget = preg_match('/^[0-9a-f]{24}$/i', $cuKickId) && hash_equals($_SESSION['cuToken'], (string) ($_POST['token'] ?? ''))
        ? $academy->users->findOne(['_id' => new MongoDB\BSON\ObjectId($cuKickId)], ['projection' => ['profile' => 1, 'subsidiary' => 1, 'firstName' => 1, 'lastName' => 1]])
        : null;
    if (!$cuTarget || $cuKickId === (string) $_SESSION['id']
        || (!$cuIsSuperAdmin && (($cuTarget['profile'] ?? '') === 'Super Admin' || (string) ($cuTarget['subsidiary'] ?? '') !== $cuSubsidiary))) {
        $_SESSION['cuFlash'] = 'Déconnexion refusée.';
    } else {
        $academy->users->updateOne(['_id' => $cuTarget['_id']], ['$set' => ['sessionsRevokedAt' => new MongoDB\BSON\UTCDateTime()]]);
        $academy->connections->updateMany(['user' => $cuTarget['_id'], 'active' => true], ['$set' => ['status' => 'Offline', 'end' => date('d-m-Y H:i:s')]]);
        $_SESSION['cuFlash'] = trim(($cuTarget['firstName'] ?? '') . ' ' . ($cuTarget['lastName'] ?? '')) . ' sera déconnecté(e) à sa prochaine action.';
    }
    header('Location: ' . cuUrl([]));
    exit();
}
// Bouton « Ajouter » d'une épreuve (1 à 120 min, 15 par défaut) : temps proposé aux candidats en cours, ajouté quand ils cliquent sur OK
// (autosave de userPaperQuiz.php). Un bonus encore en attente n'est pas cumulé.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bonus'])) {
    $cuBonusPaper = (string) $_POST['bonus'];
    $cuMinutes = max(1, min(120, (int) ($_POST['minutes'] ?? 15)));
    $cuGranted = 0;
    if (preg_match('/^[0-9a-f]{24}$/i', $cuBonusPaper) && hash_equals($_SESSION['cuToken'], (string) ($_POST['token'] ?? ''))) {
        $cuFilter = ['paper' => new MongoDB\BSON\ObjectId($cuBonusPaper), 'active' => true,
            'updated' => ['$gte' => date('Y-m-d H:i:s', time() - 6 * 3600)], 'bonus.status' => ['$ne' => 'pending']];
        if (!$cuIsSuperAdmin) {
            $cuSubUsers = [];
            foreach ($academy->users->find(['subsidiary' => $cuSubsidiary], ['projection' => ['_id' => 1]]) as $u) {
                $cuSubUsers[] = $u['_id'];
            }
            $cuFilter['user'] = ['$in' => $cuSubUsers];
        }
        $cuBonusId = bin2hex(random_bytes(6));
        foreach ($academy->exams->find($cuFilter, ['projection' => ['hour' => 1, 'minute' => 1, 'second' => 1]]) as $e) {
            if ((int) ($e['hour'] ?? 0) * 3600 + (int) ($e['minute'] ?? 0) * 60 + (int) ($e['second'] ?? 0) <= 0) {
                continue;
            }
            $academy->exams->updateOne(['_id' => $e['_id']], ['$set' => ['bonus' => ['id' => $cuBonusId, 'minutes' => $cuMinutes,
                'status' => 'pending', 'grantedAt' => date('Y-m-d H:i:s'), 'grantedBy' => new MongoDB\BSON\ObjectId($_SESSION['id'])]]]);
            $cuGranted++;
        }
    }
    $_SESSION['cuFlash'] = $cuGranted ? '+' . $cuMinutes . ' min proposées à ' . $cuGranted . ' candidat(s) : elles s\'affichent à leur prochaine sauvegarde.'
        : 'Aucun candidat en cours sur cette épreuve.';
    header('Location: ' . cuUrl([]));
    exit();
}
$cuFlash = $_SESSION['cuFlash'] ?? '';
unset($_SESSION['cuFlash']);

$cuRows =$academy->connections->aggregate($pipeline, [
    'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array'],
]);

// Une ligne par utilisateur : sa connexion la plus récente (une reconnexion crée une nouvelle connexion).
$cuByUser = [];
foreach ($cuRows as $r) {
    $k = (string) $r['u']['_id'];
    if (!isset($cuByUser[$k])) {
        $cuByUser[$k] = $r;
    }
}
$cuRows = array_values($cuByUser);

$cuSubsidiaries = [];
if ($cuIsSuperAdmin) {
    foreach ($academy->users->distinct('subsidiary', ['active' => true]) as $s) {
        if (is_string($s) && trim($s) !== '') {
            $cuSubsidiaries[] = $s;
        }
    }
    sort($cuSubsidiaries);
}
$cuProfiles = [];
foreach ($academy->users->distinct('profile', ['active' => true]) as $p) {
    if (is_string($p) && trim($p) !== '') {
        $cuProfiles[] = $p;
    }
}
sort($cuProfiles);

function cuEsc($v)
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

/** « il y a 3 min », « il y a 2 h », sinon la date. */
function cuAgo($seconds, $date)
{
    if ($seconds < 60) {
        return "à l'instant";
    }
    if ($seconds < 3600) {
        return 'il y a ' . floor($seconds / 60) . ' min';
    }
    if ($seconds < 86400) {
        return 'il y a ' . floor($seconds / 3600) . ' h';
    }
    return 'le ' . $date->format('d/m/Y à H:i');
}

function cuDuration($seconds)
{
    $seconds = max(0, (int) $seconds);
    return sprintf('%d:%02d:%02d', floor($seconds / 3600), floor($seconds % 3600 / 60), $seconds % 60);
}

$now = time();
$cuOnlineCount = 0;
foreach ($cuRows as $i => $r) {
    $seen = $r['lastSeen']->toDateTime()->setTimezone(new DateTimeZone(date_default_timezone_get()));
    $cuRows[$i]['seenAgo'] = $now - $seen->getTimestamp();
    $cuRows[$i]['seenDate'] = $seen;
    $cuRows[$i]['isOnline'] = ($r['status'] ?? '') === 'Online' && $cuRows[$i]['seenAgo'] < $cuOnlineSeconds;
    if ($cuRows[$i]['isOnline']) {
        $cuOnlineCount++;
    }
}

// --- Suivi d'épreuve (vue Candidat) ---
// exams est sauvegardé à chaque changement de question (userPaperQuiz.php, autosave) :
// un long silence = candidat bloqué sur une question, ou connexion perdue s'il n'est plus en ligne.
$cuStallSeconds = 360;
$cuStates = [
    'lost' => ['Déconnecté', 'st-bad'],
    'stalled' => ['Bloqué ?', 'st-warn'],
    'timeup' => ['Temps écoulé, non clôturée', 'st-bad'],
    'running' => ['En cours', 'st-run'],
    'none' => ['Pas commencée', 'st-none'],
    'done' => ['Terminée', 'st-done'],
];

/** Avancement de la dernière épreuve d'un candidat et son état (clé de $cuStates). */
function cuExamInfo($e, $paper, $now, $isOnline, $stallSeconds, $rightAnswers)
{
    if (!$e) {
        return ['state' => 'none', 'paper' => '', 'detail' => ''];
    }
    $answered = 0;
    $correct = 0;
    $qids = isset($e['questions']) && is_array($e['questions']) ? array_values($e['questions']) : [];
    foreach ((isset($e['answers']) && is_array($e['answers']) ? array_values($e['answers']) : []) as $i => $a) {
        if ($a !== 'null' && $a !== '' && $a !== null) {
            $answered++;
            $k = isset($qids[$i]) ? (string) $qids[$i] : '';
            if (isset($rightAnswers[$k]) && (string) $a === $rightAnswers[$k]) {
                $correct++;
            }
        }
    }
    // Orientation : réponses enregistrées par l'autosave (une entrée par question, vide tant qu'elle n'est pas répondue).
    $oriTotal = count($paper['orientation'] ?? []);
    $oriDone = 0;
    foreach ((isset($e['orientationAnswers']) && is_array($e['orientationAnswers']) ? $e['orientationAnswers'] : []) as $choices) {
        $oriDone += !empty($choices) ? 1 : 0;
    }
    $count = !empty($e['questions']) ? count($e['questions']) : count($paper['questions'] ?? []);
    $remaining = (int) ($e['hour'] ?? 0) * 3600 + (int) ($e['minute'] ?? 0) * 60 + (int) ($e['second'] ?? 0);
    $duration = (int) ($paper['hour'] ?? 0) * 3600 + (int) ($paper['minute'] ?? 0) * 60;
    $savedAt = strtotime((string) ($e['updated'] ?? $e['created'] ?? ''));
    $saveAgo = $savedAt ? max(0, $now - $savedAt) : 0;

    $detail = '';
    if (($e['active'] ?? true) === false) {
        $state = 'done';
    } elseif ($duration > 0 && $remaining <= 0 && $answered > 0) {
        $state = 'timeup';
    } elseif ($saveAgo > $stallSeconds) {
        $state = $isOnline ? 'stalled' : 'lost';
        $detail = floor($saveAgo / 60) . ' min sans réponse';
    } else {
        $state = 'running';
    }

    return [
        'state' => $state,
        'detail' => $detail,
        'paper' => (string) ($paper['label'] ?? 'Épreuve'),
        'answered' => $answered,
        'count' => $count,
        'percent' => $count > 0 ? min(100, (int) round($answered * 100 / $count)) : 0,
        // Le chrono continue côté navigateur depuis la dernière sauvegarde : estimation tant que le candidat est en ligne.
        'remaining' => $state === 'running' || $state === 'stalled' ? max(0, $remaining - $saveAgo) : $remaining,
        'remainingFrozen' => $state === 'lost',
        'pace' => $duration > 0 && $answered >= 3 ? (int) round(($duration - $remaining) / $answered) : null,
        'saveAgo' => $saveAgo,
        'correct' => $correct,
        'scorePercent' => $count > 0 ? (int) round($correct * 100 / $count) : 0,
        'oriDone' => min($oriDone, $oriTotal),
        'oriTotal' => $oriTotal,
        'paperId' => (string) ($e['paper'] ?? ''),
        'bonus' => isset($e['bonus']['status']) ? $e['bonus'] : null,
        'bonusMinutes' => (int) ($e['bonusMinutes'] ?? 0),
    ];
}

/** Minutes signées : « +15 min », « −13 min ». */
function cuSignedMinutes($m)
{
    return ($m < 0 ? '−' . abs($m) : '+' . $m) . ' min';
}

/** Statut du temps supplémentaire d'un candidat (bouton « +15 min »). */
function cuBonusLabel($x)
{
    if (empty($x['bonus'])) {
        return '';
    }
    if ($x['bonus']['status'] === 'pending') {
        $html = '<span class="st st-warn">' . cuSignedMinutes((int) $x['bonus']['minutes']) . ' en attente</span>';
        return $x['bonusMinutes'] ? $html . '<span class="st-detail">' . cuSignedMinutes($x['bonusMinutes']) . ' déjà appliquées</span>' : $html;
    }
    $at = strtotime((string) ($x['bonus']['acceptedAt'] ?? ''));
    return '<span class="st ' . ($x['bonusMinutes'] < 0 ? 'st-none' : 'st-done') . '">' . cuSignedMinutes($x['bonusMinutes']) . '</span>'
        . ($at ? '<span class="st-detail">appliqué à ' . date('H:i', $at) . '</span>' : '');
}

/** Premier prénom + premier nom, le nom complet reste en infobulle. */
function cuShortName($u)
{
    $first = preg_split('/\s+/', trim((string) ($u['firstName'] ?? '')));
    $last = preg_split('/\s+/', trim((string) ($u['lastName'] ?? '')));
    return trim($first[0] . ' ' . $last[0]);
}

function cuPace($seconds)
{
    return $seconds >= 60 ? floor($seconds / 60) . ' min ' . sprintf('%02d', $seconds % 60) . ' s' : $seconds . ' s';
}

$cuGroups = [];
$cuStats = ['running' => 0, 'alert' => 0, 'done' => 0];
if ($cuExamView && $cuRows) {
    $cuUserIds = [];
    foreach ($cuRows as $r) {
        $cuUserIds[] = $r['u']['_id'];
    }
    $cuExams = [];
    $cuPaperIds = [];
    $cuExamDocs = $academy->exams->find(['user' => ['$in' => $cuUserIds]], [
        'sort' => ['updated' => -1],
        'projection' => ['user' => 1, 'paper' => 1, 'active' => 1, 'answers' => 1, 'questions' => 1,
            'hour' => 1, 'minute' => 1, 'second' => 1, 'updated' => 1, 'created' => 1, 'bonus' => 1, 'bonusMinutes' => 1,
            'orientationAnswers' => 1],
        'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array'],
    ]);
    foreach ($cuExamDocs as $e) {
        $k = (string) $e['user'];
        if (!isset($cuExams[$k])) {
            $cuExams[$k] = $e;
            if (isset($e['paper'])) {
                $cuPaperIds[(string) $e['paper']] = $e['paper'];
            }
        }
    }
    // Bonnes réponses des questions en cours, pour le score provisoire (même calcul que la correction de userPaperQuiz.php).
    $cuQuestionIds = [];
    foreach ($cuExams as $e) {
        foreach ($e['questions'] ?? [] as $q) {
            $cuQuestionIds[(string) $q] = $q;
        }
    }
    $cuRightAnswers = [];
    if ($cuQuestionIds) {
        foreach ($academy->questions->find(['_id' => ['$in' => array_values($cuQuestionIds)]], ['projection' => ['answer' => 1]]) as $q) {
            $cuRightAnswers[(string) $q['_id']] = (string) ($q['answer'] ?? '');
        }
    }
    $cuPapers = [];
    if ($cuPaperIds) {
        $cuPaperDocs = $academy->papers->find(['_id' => ['$in' => array_values($cuPaperIds)]], [
            'projection' => ['label' => 1, 'hour' => 1, 'minute' => 1, 'questions' => 1, 'orientation' => 1],
            'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array'],
        ]);
        foreach ($cuPaperDocs as $p) {
            $cuPapers[(string) $p['_id']] = $p;
        }
    }

    // Questions signalées (image manquante) par le candidat sur son épreuve : userPaperQuiz.php, un document par
    // épreuve et question, au nom du premier candidat qui signale.
    $cuReports = [];
    $cuReportDocs = $academy->quiz_question_reports->find(
        ['reportedBy' => ['$in' => array_map('strval', $cuUserIds)]],
        ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]
    );
    foreach ($cuReportDocs as $rep) {
        $k = (string) ($rep['reportedBy'] ?? '');
        if (isset($cuExams[$k]['paper']) && (string) $cuExams[$k]['paper'] === (string) ($rep['paperId'] ?? '')) {
            $cuReports[$k][] = (string) ($rep['questionRef'] ?? '') !== '' ? (string) $rep['questionRef'] : 'question ' . ($rep['questionId'] ?? '');
        }
    }

    $order = array_flip(array_keys($cuStates));
    foreach ($cuRows as $r) {
        $r['reports'] = $cuReports[(string) $r['u']['_id']] ?? [];
        $e = $cuExams[(string) $r['u']['_id']] ?? null;
        $paper = $e && isset($e['paper']) ? ($cuPapers[(string) $e['paper']] ?? []) : [];
        $r['exam'] = cuExamInfo($e, $paper, $now, $r['isOnline'], $cuStallSeconds, $cuRightAnswers);
        $g = $r['exam']['paper'] !== '' ? $r['exam']['paper'] : 'Aucune épreuve commencée';
        $cuGroups[$g][] = $r;
        $s = $r['exam']['state'];
        if ($s === 'done') {
            $cuStats['done']++;
        } elseif ($s !== 'none') {
            $cuStats[$s === 'running' ? 'running' : 'alert']++;
        }
    }
    // Groupes par épreuve (sans épreuve en dernier) ; dans chaque groupe, les anomalies d'abord.
    uksort($cuGroups, function ($a, $b) {
        if ($a === 'Aucune épreuve commencée' || $b === 'Aucune épreuve commencée') {
            return $a === 'Aucune épreuve commencée' ? 1 : -1;
        }
        return strcmp($a, $b);
    });
    foreach ($cuGroups as $g => $list) {
        usort($list, function ($a, $b) use ($order) {
            $d = $order[$a['exam']['state']] - $order[$b['exam']['state']];
            return $d !== 0 ? $d : strcmp((string) ($a['u']['lastName'] ?? ''), (string) ($b['u']['lastName'] ?? ''));
        });
        $cuGroups[$g] = $list;
    }
}

/** Bouton « Déconnecter » d'une ligne (sauf soi-même, un Super Admin vu par un Admin, ou une session déjà fermée). */
function cuKickButton($r, $isSuperAdmin)
{
    $u = $r['u'];
    if ((string) $u['_id'] === (string) $_SESSION['id'] || (!$isSuperAdmin && ($u['profile'] ?? '') === 'Super Admin')
        || ($r['status'] ?? '') === 'Offline') {
        return '';
    }
    $name = trim(($u['firstName'] ?? '') . ' ' . ($u['lastName'] ?? ''));
    return '<form method="post" class="cu-kick" data-confirm="' . cuEsc($name . ' sera déconnecté et devra se reconnecter pour continuer.') . '"'
        . ' data-confirm-title="Déconnecter ce candidat ?" data-confirm-ok="Déconnecter" data-confirm-tone="danger">'
        . '<input type="hidden" name="token" value="' . cuEsc($_SESSION['cuToken']) . '">'
        . '<button type="submit" name="kick" value="' . cuEsc($u['_id']) . '" title="Fermer sa session : il devra se reconnecter">Déconnecter</button>'
        . '</form>';
}

/** URL de la page avec les filtres courants, modifiés par $set. */
function cuUrl(array $set)
{
    $q = array_merge(['period' => $_GET['period'] ?? null, 'subsidiary' => $_GET['subsidiary'] ?? null, 'profile' => $_GET['profile'] ?? null], $set);
    return '?' . http_build_query(array_filter($q, function ($v) { return $v !== null && $v !== ''; }));
}

$title = "Utilisateurs connectés";
include_once "partials/header.php";
openBackgroundContainer();
?>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
:root {
  --primary: #d70006;
  --primary-light: #fef2f2;
  --card-bg: rgba(255, 255, 255, 0.9);
  --shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  --text: #1a1f36;
  --sub: #4b5563;
  --r: 16px;
}
.online-page {
    font-family: 'Inter', system-ui, sans-serif;
    padding: 30px 20px 80px;
}
.online-page .shell {
    max-width: 1100px;
    margin: 0 auto;
}
.online-page .page-title {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
    padding: 20px;
    background: var(--card-bg);
    backdrop-filter: blur(10px);
    border-radius: var(--r);
    box-shadow: var(--shadow);
}
.online-page .page-title .icon {
    width: 50px;
    height: 50px;
    background: var(--primary-light);
    color: var(--primary);
    border-radius: 12px;
    display: grid;
    place-items: center;
    font-size: 1.5rem;
    flex-shrink: 0;
}
.online-page .page-title .icon i {
    color: var(--primary);
}
.online-page .page-title h1 {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--text);
    margin: 0;
}
.online-page .page-title p {
    font-size: 0.85rem;
    color: var(--sub);
    margin: 4px 0 0;
}
.online-page .counts {
    margin-left: auto;
    display: flex;
    gap: 22px;
    flex-shrink: 0;
}
.online-page .count {
    text-align: right;
}
.online-page .count strong {
    display: block;
    font-size: 1.8rem;
    line-height: 1;
    color: #16a34a;
}
.online-page .count.run strong { color: #2563eb; }
.online-page .count.bad strong { color: #b91c1c; }
.online-page .count.zero strong { color: #9ca3af; }
.online-page .count span {
    font-size: 0.8rem;
    color: var(--sub);
}
.online-page .card-list {
    background: var(--card-bg);
    backdrop-filter: blur(15px);
    border-radius: var(--r);
    box-shadow: var(--shadow);
    padding: 20px;
}
.online-page form {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
    margin-bottom: 16px;
}
.online-page select {
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    background: #fff;
    font-size: 0.85rem;
}
.online-page .shortcut {
    margin-left: auto;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--primary);
    text-decoration: none;
}
.online-page .table-wrap {
    overflow-x: auto;
}
.online-page table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
    min-width: 700px;
}
.online-page th, .online-page td {
    padding: 10px 12px;
    border-bottom: 1px solid #eef0f3;
    text-align: left;
    color: var(--text);
    vertical-align: middle;
}
.online-page th {
    font-weight: 600;
    color: var(--sub);
    background: #f9fafb;
    white-space: nowrap;
}
.online-page .dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 6px;
    background: #9ca3af;
}
.online-page .dot.on { background: #16a34a; }
.online-page .muted { color: var(--sub); }
.online-page .num { font-variant-numeric: tabular-nums; white-space: nowrap; }
.online-page .empty {
    text-align: center;
    padding: 30px;
    color: var(--sub);
}
.online-page tr.group td {
    background: #f3f4f6;
    font-weight: 700;
    padding-top: 14px;
}
.online-page tr.group .muted { font-weight: 400; margin-left: 8px; }
.online-page .cu-prog { min-width: 140px; }
.online-page .cu-bar {
    height: 6px;
    background: #eef0f3;
    border-radius: 3px;
    margin-top: 4px;
    overflow: hidden;
}
.online-page .cu-bar span { display: block; height: 100%; background: #2563eb; }
.online-page .st {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 600;
    white-space: nowrap;
}
.online-page .st-run { background: #eff6ff; color: #1d4ed8; }
.online-page .st-done { background: #f0fdf4; color: #15803d; }
.online-page .st-warn { background: #fff7ed; color: #c2410c; }
.online-page .st-bad { background: #fef2f2; color: #b91c1c; }
.online-page .st-none { background: #f3f4f6; color: #4b5563; }
.online-page .st-detail { display: block; font-size: 0.75rem; color: var(--sub); margin-top: 3px; }
.online-page th + th, .online-page td + td { border-left: 1px solid #e5e7eb; }
.online-page tr.group td { border-left: 0; }
.online-page .cu-note { font-size: 0.85rem; }
.online-page tbody.cu-grp tr.group td { cursor: pointer; user-select: none; }
.online-page tbody.cu-grp tr.group:hover td { background: #e5e7eb; }
.online-page .cu-chev {
    display: inline-block;
    width: 8px;
    height: 8px;
    margin: 0 10px 2px 2px;
    border-right: 2px solid #6b7280;
    border-bottom: 2px solid #6b7280;
    transform: rotate(45deg);
    transition: transform 0.15s;
}
.online-page tbody.cu-grp.collapsed .cu-chev { transform: rotate(-45deg); }
.online-page tbody.cu-grp.collapsed tr:not(.group) { display: none; }
.online-page td.cu-name { white-space: nowrap; border-left: 4px solid transparent; }
.online-page td.cu-name .st-detail { white-space: normal; }
.online-page tr.row-running td.cu-name { border-left-color: #2563eb; }
.online-page tr.row-done td { background: #f6fdf8; }
.online-page tr.row-done td.cu-name { border-left-color: #16a34a; }
.online-page tr.row-stalled td { background: #fffaf3; }
.online-page tr.row-stalled td.cu-name { border-left-color: #f97316; }
.online-page tr.row-lost td, .online-page tr.row-timeup td { background: #fef6f6; }
.online-page tr.row-lost td.cu-name, .online-page tr.row-timeup td.cu-name { border-left-color: #dc2626; }
.online-page tr.row-none td.cu-name { border-left-color: #d1d5db; }
.online-page tr.row-stalled .st-label { color: #c2410c; font-weight: 600; }
.online-page tr.row-lost .st-label, .online-page tr.row-timeup .st-label { color: #b91c1c; font-weight: 600; }
.online-page tr.row-done .cu-bar span { background: #16a34a; }
.online-page .cu-low { color: #b91c1c; font-weight: 700; }
.online-page .cu-legend { display: flex; flex-wrap: wrap; gap: 14px; font-size: 0.78rem; color: var(--sub); margin: 0 0 8px; }
.online-page .cu-legend i { display: inline-block; width: 4px; height: 12px; margin-right: 5px; vertical-align: -1px; border-radius: 2px; }
.online-page form.cu-kick { display: inline; margin: 0; }
.online-page form.cu-kick button {
    padding: 4px 10px;
    border: 1px solid #fecaca;
    border-radius: 8px;
    background: #fff;
    color: #b91c1c;
    font-size: 0.78rem;
    font-weight: 600;
    white-space: nowrap;
    cursor: pointer;
}
.online-page form.cu-kick button:hover { background: #fef2f2; }
.online-page form.cu-bonus { margin-left: 12px; font-size: 0.78rem; color: var(--sub); }
.online-page form.cu-bonus input[type=number] { width: 58px; padding: 3px 6px; border: 1px solid #bfdbfe; border-radius: 8px; font-size: 0.78rem; }
.online-page form.cu-bonus button { border-color: #bfdbfe; color: #1d4ed8; }
.online-page form.cu-bonus button:hover { background: #eff6ff; }
.online-page .flash {
    margin-bottom: 14px;
    padding: 10px 14px;
    border-radius: 8px;
    background: #f0fdf4;
    color: #15803d;
    font-size: 0.85rem;
}
</style>

<div class="online-page">
    <div class="shell">

        <div class="page-title">
            <div class="icon"><i class="fas <?= $cuExamView ? 'fa-tasks' : 'fa-user-clock' ?>"></i></div>
            <div>
                <h1><?= $cuExamView ? 'Suivi des épreuves des candidats' : 'Utilisateurs connectés' ?></h1>
                <p>
                    <?= $cuIsSuperAdmin ? ($cuSubsidiary !== '' ? cuEsc($cuSubsidiary) : 'Toutes les filiales') : cuEsc($cuSubsidiary) ?> —
                    <?php if ($cuExamView): ?>
                        alerte si aucune réponse enregistrée depuis <?= (int) ($cuStallSeconds / 60) ?> min.<?= $cuStats['running'] + $cuStats['alert'] ? ' Page actualisée chaque minute.' : '' ?>
                    <?php else: ?>
                        « En ligne » = une page ouverte dans les <?= (int) round($cuOnlineSeconds / 60) ?> dernières minutes.
                    <?php endif; ?>
                </p>
            </div>
            <div class="counts">
                <?php if ($cuExamView): ?>
                    <div class="count run<?= $cuStats['running'] ? '' : ' zero' ?>"><strong><?= $cuStats['running'] ?></strong><span>en cours</span></div>
                    <div class="count bad<?= $cuStats['alert'] ? '' : ' zero' ?>"><strong><?= $cuStats['alert'] ?></strong><span>anomalie(s)</span></div>
                    <div class="count<?= $cuStats['done'] ? '' : ' zero' ?>"><strong><?= $cuStats['done'] ?></strong><span>terminée(s)</span></div>
                <?php else: ?>
                    <div class="count"><strong><?= $cuOnlineCount ?></strong><span>en ligne</span></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card-list">
            <?php if ($cuFlash !== ''): ?><div class="flash"><?= cuEsc($cuFlash) ?></div><?php endif; ?>
            <datalist id="cu-minutes"><option value="5"><option value="10"><option value="15"><option value="20"><option value="30"></datalist>
            <form method="get">
                <select name="profile" onchange="this.form.period.value = ''; this.form.submit()">
                    <option value="">Tous les profils</option>
                    <?php foreach ($cuProfiles as $p): ?>
                        <option value="<?= cuEsc($p) ?>" <?= $cuProfile === $p ? 'selected' : '' ?>><?= cuEsc($p) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="period" onchange="this.form.submit()">
                    <?php foreach ($cuPeriods as $v => $label): ?>
                        <option value="<?= $v ?>" <?= $cuMinutes === $v ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($cuIsSuperAdmin): ?>
                    <select name="subsidiary" onchange="this.form.submit()">
                        <option value="">Toutes les filiales</option>
                        <?php foreach ($cuSubsidiaries as $s): ?>
                            <option value="<?= cuEsc($s) ?>" <?= $cuSubsidiary === $s ? 'selected' : '' ?>><?= cuEsc($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <noscript><button type="submit">Filtrer</button></noscript>
                <?php if (!$cuExamView): ?>
                    <a class="shortcut" href="<?= cuEsc(cuUrl(['profile' => 'Candidat', 'period' => null])) ?>">Suivi des épreuves des candidats →</a>
                <?php endif; ?>
            </form>

            <div class="table-wrap">
            <?php if ($cuExamView): ?>
                <div class="cu-legend">
                    <span><i style="background:#2563eb"></i>En cours</span>
                    <span><i style="background:#f97316"></i>Bloqué ?</span>
                    <span><i style="background:#dc2626"></i>Déconnecté / temps écoulé</span>
                    <span><i style="background:#16a34a"></i>Terminée</span>
                    <span><i style="background:#d1d5db"></i>Pas commencée</span>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Candidat</th>
                            <th>Avancement</th>
                            <th title="Bonnes réponses sur le nombre total de questions, recalculé à chaque sauvegarde">Score provisoire</th>
                            <th title="Moins de 10 : rouge · de 10 à 15,9 : orange · 16 et plus : vert">Note / 20</th>
                            <th>Orientation</th>
                            <th>Temps restant</th>
                            <th>Dernière réponse</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$cuGroups): ?>
                        <tr><td class="empty" colspan="8">Aucun candidat connecté sur la période.</td></tr>
                    <?php endif; ?>
                    </tbody>
                    <?php foreach ($cuGroups as $g => $list):
                        $gDone = 0;
                        $gReports = 0;
                        $gPaper = '';
                        foreach ($list as $r) {
                            $gDone += $r['exam']['state'] === 'done' ? 1 : 0;
                            $gReports += count($r['reports']);
                            if (in_array($r['exam']['state'], ['running', 'stalled', 'lost'], true)) {
                                $gPaper = $r['exam']['paperId'];
                            }
                        } ?>
                    <tbody class="cu-grp" data-group="<?= cuEsc($g) ?>">
                        <tr class="group" title="Cliquer pour replier / déplier">
                            <td colspan="8"><span class="cu-chev" aria-hidden="true"></span><?= cuEsc($g) ?><span class="muted"><?= count($list) ?> candidat(s)<?= $gDone ? ' · ' . $gDone . ' terminée(s)' : '' ?><?= $gReports ? ' · ' . $gReports . ' question(s) signalée(s)' : '' ?></span>
                                <?php if ($gPaper !== ''): ?>
                                    <form method="post" class="cu-kick cu-bonus" data-confirm="<?= cuEsc('{minutes} minutes seront proposées à tous les candidats en cours sur « ' . $g . ' ». Le temps est ajouté quand le candidat clique sur OK.') ?>" data-confirm-title="Ajouter du temps ?" data-confirm-ok="Ajouter">
                                        <input type="hidden" name="token" value="<?= cuEsc($_SESSION['cuToken']) ?>">
                                        <input type="number" name="minutes" value="15" min="1" max="120" step="1" list="cu-minutes" required aria-label="Minutes à ajouter"> min
                                        <button type="submit" name="bonus" value="<?= cuEsc($gPaper) ?>" title="Une fenêtre s'affiche chez chaque candidat en cours : le temps est ajouté quand il clique sur OK">Ajouter aux candidats en cours</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php foreach ($list as $r):
                            $u = $r['u'];
                            $x = $r['exam'];
                            $st = $cuStates[$x['state']];
                            $fullName = trim(($u['firstName'] ?? '') . ' ' . ($u['lastName'] ?? '')); ?>
                            <tr class="row-<?= $x['state'] ?>" title="<?= cuEsc($st[0]) ?>">
                                <td class="cu-name" title="<?= cuEsc($fullName) ?>">
                                    <span class="dot<?= $r['isOnline'] ? ' on' : '' ?>"></span><?= cuEsc(cuShortName($u)) ?>
                                    <?php if (!in_array($x['state'], ['running', 'done'], true)): ?><span class="st-detail st-label"><?= $st[0] ?></span><?php endif; ?>
                                    <?php if ($x['detail'] !== ''): ?><span class="st-detail"><?= cuEsc($x['detail']) ?></span><?php endif; ?>
                                    <?php if ($x['state'] === 'none'): ?><span class="st-detail"><?= $r['isOnline'] ? 'en ligne' : 'vu ' . cuEsc(cuAgo($r['seenAgo'], $r['seenDate'])) ?></span><?php endif; ?>
                                    <?php if ($r['reports']): ?>
                                        <span class="st-detail"><span class="st st-warn" title="<?= cuEsc('Image manquante ou non affichée : ' . implode(', ', $r['reports'])) ?>"><?= count($r['reports']) ?> question(s) signalée(s)</span></span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($x['state'] === 'none'): ?>
                                    <td class="muted">—</td><td class="muted">—</td><td class="muted">—</td><td class="muted">—</td><td class="muted">—</td><td class="muted">—</td>
                                <?php else: ?>
                                    <td class="cu-prog">
                                        <span class="num"><strong><?= $x['answered'] ?></strong> / <?= $x['count'] ?></span>
                                        <div class="cu-bar"><span style="width: <?= $x['percent'] ?>%"></span></div>
                                    </td>
                                    <?php $note = $x['count'] ? $x['correct'] * 20 / $x['count'] : 0; ?>
                                    <td class="num" title="<?= $x['correct'] ?> bonne(s) réponse(s) sur <?= $x['count'] ?>"><strong><?= $x['scorePercent'] ?> %</strong></td>
                                    <td class="num"><span class="st cu-note <?= $note < 10 ? 'st-bad' : ($note < 16 ? 'st-warn' : 'st-done') ?>"><?= str_replace('.', ',', (string) round($note, 1)) ?> / 20</span></td>
                                    <td class="num">
                                        <?php if ($x['oriTotal'] === 0): ?><span class="muted">—</span>
                                        <?php else: ?>
                                            <span class="st <?= $x['oriDone'] === $x['oriTotal'] ? 'st-done' : ($x['oriDone'] ? 'st-warn' : 'st-none') ?>"><?= $x['oriDone'] ?> / <?= $x['oriTotal'] ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="num">
                                        <?php if ($x['state'] === 'done'): ?><span class="muted">—</span>
                                        <?php else: ?><span class="<?= $x['remaining'] < 300 ? 'cu-low' : '' ?>"><?= cuDuration($x['remaining']) ?></span><?= $x['remainingFrozen'] ? ' <span class="muted">(figé)</span>' : '' ?><?php endif; ?>
                                        <?php $bonusLabel = cuBonusLabel($x); if ($bonusLabel !== ''): ?><div><?= $bonusLabel ?></div><?php endif; ?>
                                    </td>
                                    <td class="num">
                                        <?= cuEsc(cuAgo($x['saveAgo'], new DateTime('@' . ($now - $x['saveAgo'])))) ?>
                                        <?php if ($x['pace'] !== null): ?><span class="st-detail"><?= cuPace($x['pace']) ?> / question</span><?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <td><?= cuKickButton($r, $cuIsSuperAdmin) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Utilisateur</th>
                            <th>Profil</th>
                            <?php if ($cuIsSuperAdmin): ?><th>Filiale</th><?php endif; ?>
                            <th>Agence</th>
                            <th>Dernière activité</th>
                            <th>Connecté le</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$cuRows): ?>
                        <tr><td class="empty" colspan="<?= $cuIsSuperAdmin ? 7 : 6 ?>">Aucune activité sur la période.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($cuRows as $r):
                        $u = $r['u']; ?>
                        <tr>
                            <td><span class="dot<?= $r['isOnline'] ? ' on' : '' ?>"></span><?= cuEsc(trim(($u['firstName'] ?? '') . ' ' . ($u['lastName'] ?? ''))) ?></td>
                            <td><?= cuEsc($u['profile'] ?? '') ?></td>
                            <?php if ($cuIsSuperAdmin): ?><td><?= cuEsc($u['subsidiary'] ?? '') ?></td><?php endif; ?>
                            <td><?= cuEsc($u['agency'] ?? '') ?></td>
                            <td>
                                <?= $r['isOnline'] ? 'En ligne' : '' ?>
                                <span class="muted"><?= cuEsc(cuAgo($r['seenAgo'], $r['seenDate'])) ?></span>
                                <?php if (($r['status'] ?? '') === 'Offline'): ?><span class="muted">(déconnecté)</span><?php endif; ?>
                            </td>
                            <td class="muted"><?= cuEsc($r['start'] ?? '') ?></td>
                            <td><?= cuKickButton($r, $cuIsSuperAdmin) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script src="../../public/js/medacap-modal.js"></script>
<script>
(function () {
    // Campagnes repliées, mémorisées pour survivre au rafraîchissement automatique.
    var key = 'cuCollapsedGroups', collapsed = [];
    try { collapsed = JSON.parse(localStorage.getItem(key)) || []; } catch (e) {}
    document.querySelectorAll('tbody.cu-grp').forEach(function (body) {
        var name = body.getAttribute('data-group');
        body.classList.toggle('collapsed', collapsed.indexOf(name) !== -1);
        body.querySelector('tr.group').addEventListener('click', function (e) {
            if (e.target.closest('form')) {
                return;
            }
            var isCollapsed = body.classList.toggle('collapsed');
            collapsed = collapsed.filter(function (n) { return n !== name; });
            if (isCollapsed) {
                collapsed.push(name);
            }
            try { localStorage.setItem(key, JSON.stringify(collapsed)); } catch (e2) {}
        });
    });
})();
</script>
<?php if ($cuExamView && $cuStats['running'] + $cuStats['alert']): ?>
<script>
setInterval(function () {
    if (!MedacapModal.isOpen()) {
        window.location.reload();
    }
}, 60000);
</script>
<?php endif; ?>
<?php
closeBackgroundContainer();
include_once "partials/footer.php";
?>
