<?php
// Utilisateurs connectés, d'après connections.lastSeen (core/presence.php). Admin : sa filiale ; Super Admin : tout.
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
$cuMinutes = isset($_GET['period']) && isset($cuPeriods[(int) $_GET['period']]) ? (int) $_GET['period'] : 15;
$cuIsSuperAdmin = $profile === 'Super Admin';
$cuSubsidiary = $cuIsSuperAdmin ? trim((string) ($_GET['subsidiary'] ?? '')) : (string) ($_SESSION['subsidiary'] ?? '');

$pipeline = [
    ['$match' => ['lastSeen' => ['$gte' => new MongoDB\BSON\UTCDateTime((time() - $cuMinutes * 60) * 1000)]]],
    ['$lookup' => ['from' => 'users', 'localField' => 'user', 'foreignField' => '_id', 'as' => 'u']],
    ['$unwind' => '$u'],
];
if (!$cuIsSuperAdmin || $cuSubsidiary !== '') {
    $pipeline[] = ['$match' => ['u.subsidiary' => $cuSubsidiary]];
}
$pipeline[] = ['$sort' => ['lastSeen' => -1]];
$pipeline[] = ['$limit' => 500];

$academy = (new MongoDB\Client(medacapMongoUri()))->academy;
$cuRows = $academy->connections->aggregate($pipeline, [
    'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array'],
]);
$cuRows = iterator_to_array($cuRows, false);

$cuSubsidiaries = [];
if ($cuIsSuperAdmin) {
    foreach ($academy->users->distinct('subsidiary', ['active' => true]) as $s) {
        if (is_string($s) && trim($s) !== '') {
            $cuSubsidiaries[] = $s;
        }
    }
    sort($cuSubsidiaries);
}

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
.online-page .count {
    margin-left: auto;
    text-align: right;
    flex-shrink: 0;
}
.online-page .count strong {
    display: block;
    font-size: 1.8rem;
    line-height: 1;
    color: #16a34a;
}
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
    margin-bottom: 16px;
}
.online-page select {
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    background: #fff;
    font-size: 0.85rem;
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
}
.online-page th {
    font-weight: 600;
    color: var(--sub);
    background: #f9fafb;
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
.online-page .empty {
    text-align: center;
    padding: 30px;
    color: var(--sub);
}
</style>

<div class="online-page">
    <div class="shell">

        <div class="page-title">
            <div class="icon"><i class="fas fa-user-clock"></i></div>
            <div>
                <h1>Utilisateurs connectés</h1>
                <p>
                    <?= $cuIsSuperAdmin ? 'Toutes les filiales' : cuEsc($cuSubsidiary) ?> —
                    « En ligne » = une page ouverte dans les <?= (int) round($cuOnlineSeconds / 60) ?> dernières minutes.
                </p>
            </div>
            <div class="count">
                <strong><?= $cuOnlineCount ?></strong>
                <span>en ligne</span>
            </div>
        </div>

        <div class="card-list">
            <form method="get">
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
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Utilisateur</th>
                            <th>Profil</th>
                            <?php if ($cuIsSuperAdmin): ?><th>Filiale</th><?php endif; ?>
                            <th>Agence</th>
                            <th>Dernière activité</th>
                            <th>Connecté le</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$cuRows): ?>
                        <tr><td class="empty" colspan="<?= $cuIsSuperAdmin ? 6 : 5 ?>">Aucune activité sur la période.</td></tr>
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
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?php
closeBackgroundContainer();
include_once "partials/footer.php";
?>
