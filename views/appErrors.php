<?php
// Erreurs serveur enregistrées par core/error_handler.php (collection app_errors). Super Admin uniquement.
session_start();
if (!isset($_SESSION['id']) || ($_SESSION['profile'] ?? '') !== 'Super Admin') {
    header('Location: ./');
    exit();
}
require_once __DIR__ . '/../core/medacap_config.php';

$days = isset($_GET['days']) && in_array((int) $_GET['days'], [1, 7, 30, 90], true) ? (int) $_GET['days'] : 7;
$level = isset($_GET['level']) && $_GET['level'] === 'all' ? 'all' : 'fatal';

$filter = ['lastAt' => ['$gte' => new MongoDB\BSON\UTCDateTime((time() - $days * 86400) * 1000)]];
if ($level === 'fatal') {
    $filter['level'] = 'fatal';
}
$manager = new MongoDB\Driver\Manager(medacapMongoUri());
$rows = $manager->executeQuery('academy.app_errors', new MongoDB\Driver\Query($filter, ['sort' => ['lastAt' => -1], 'limit' => 300]))->toArray();

function e($v)
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}
function d($date)
{
    return $date instanceof MongoDB\BSON\UTCDateTime
        ? $date->toDateTime()->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('d/m/Y H:i')
        : '';
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Erreurs serveur | CFAO Mobility Academy</title>
<style>
    body { font-family: Arial, sans-serif; background: #f5f8fa; color: #181c32; margin: 0; padding: 24px 16px; }
    h1 { font-size: 20px; margin: 0 0 4px; }
    .sub { color: #7e8299; margin: 0 0 16px; font-size: 13px; }
    form { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
    select, button { padding: 6px 10px; border: 1px solid #e4e6ef; border-radius: 6px; background: #fff; }
    .wrap { overflow-x: auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
    table { border-collapse: collapse; width: 100%; font-size: 13px; min-width: 900px; }
    th, td { padding: 8px 10px; border-bottom: 1px solid #eff2f5; text-align: left; vertical-align: top; }
    th { background: #f9fafb; font-weight: 600; }
    .lvl { font-weight: bold; text-transform: uppercase; font-size: 11px; }
    .fatal { color: #f1416c; } .warning { color: #ffa800; } .notice, .deprecated { color: #7e8299; }
    code { font-size: 12px; word-break: break-all; }
    details summary { cursor: pointer; color: #009ef7; }
    pre { white-space: pre-wrap; font-size: 11px; background: #f5f8fa; padding: 8px; border-radius: 6px; max-height: 300px; overflow: auto; }
    .num { text-align: right; font-variant-numeric: tabular-nums; }
</style>
</head>
<body>
<h1>Erreurs serveur</h1>
<p class="sub"><?= count($rows) ?> erreur(s) distincte(s). Une ligne regroupe toutes les occurrences d'une même erreur (même type, fichier et ligne). Mail d'alerte : 1 par erreur bloquante et par heure.</p>
<form method="get">
    <select name="level">
        <option value="fatal" <?= $level === 'fatal' ? 'selected' : '' ?>>Erreurs bloquantes</option>
        <option value="all" <?= $level === 'all' ? 'selected' : '' ?>>Tout (avec warnings)</option>
    </select>
    <select name="days">
        <?php foreach ([1 => '24 h', 7 => '7 jours', 30 => '30 jours', 90 => '90 jours'] as $v => $label): ?>
            <option value="<?= $v ?>" <?= $days === $v ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit">Filtrer</button>
</form>
<div class="wrap">
<table>
    <thead><tr><th>Niveau</th><th>Erreur</th><th>Fichier</th><th class="num">Nb</th><th>Dernière</th><th>Dernier utilisateur / URL</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?>
        <tr><td colspan="6">Aucune erreur sur la période.</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r):
        $occ = isset($r->occurrences) ? (array) $r->occurrences : [];
        $last = $occ ? end($occ) : null; ?>
        <tr>
            <td class="lvl <?= e($r->level) ?>"><?= e($r->level) ?></td>
            <td><strong><?= e($r->class) ?></strong><br><?= e($r->message) ?>
                <details><summary>Détail</summary>
                    <?php if (!empty($r->trace)): ?><pre><?= e($r->trace) ?></pre><?php endif; ?>
                    <pre><?php foreach (array_reverse($occ) as $o) {
                        echo e(d($o->at) . '  ' . ($o->ref ?: '-') . '  ' . ($o->profile ?: 'non connecté') . ' ' . $o->userName . '  ' . $o->method . ' ' . $o->url) . "\n";
                    } ?></pre>
                </details>
            </td>
            <td><code><?= e($r->file) ?>:<?= (int) $r->line ?></code></td>
            <td class="num"><?= (int) $r->count ?></td>
            <td><?= e(d($r->lastAt)) ?><br><code><?= e($r->lastRef ?? '') ?></code></td>
            <td><?= $last ? e(($last->profile ?: 'non connecté') . ' ' . $last->userName) . '<br><code>' . e($last->url) . '</code>' : '' ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
</body>
</html>
