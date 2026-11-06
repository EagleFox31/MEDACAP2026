<?php
require_once __DIR__ . '/../core/medacap_config.php';
/**
 * Rapport de fin de semaine, campagne Cameroun, en deux mails :
 *   1. bilan (avancement par manager) + classeur de suivi (update_suivi_cm_send.php) : à tous les managers, copie BILAN_CC ;
 *   2. classeur de résultats (generate_results_send_excel.php) : à RESULTS_TO, copie RESULTS_CC.
 *
 * Usage:
 *   php scripts/rapport_campagne_cameroun.php                          simulation (tableau en console, aucun envoi)
 *   php scripts/rapport_campagne_cameroun.php --test=a@cfao.com        envoie les deux mails à a@cfao.com seulement
 *   php scripts/rapport_campagne_cameroun.php --apply                  envoi réel des deux mails
 *   --url=http://10.68.0.15:8010/                                      adresse du lien (défaut : MEDACAP_PUBLIC_URL)
 */
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/includes/campagne_cameroun.php';

use MongoDB\Client;
use MongoDB\BSON\UTCDateTime;

const REPORT_CAMPAIGN = 'rapport-cameroun';
// Carte plus large que les mails aux managers : tableau à 4 colonnes + indicateurs
const REPORT_MAIL_WIDTH = 1000;
// Deux mails le vendredi :
//  1. bilan + classeur de suivi : un seul mail à tous les managers de la campagne qui ont une adresse ;
//  2. classeur de résultats : M.C. BENGONO.
const BILAN_CC = ['aetolo-ext@cfao.com', 'mcbengono@cfao.com', 'lgonin@cfao.com', 'aybokally@cfao.com', 'jakaa-ext@cfao.com'];
const RESULTS_TO = ['mcbengono@cfao.com'];
const RESULTS_CC = ['lgonin@cfao.com', 'aybokally@cfao.com', 'aetolo-ext@cfao.com', 'jakaa-ext@cfao.com'];

$opts = getopt('', ['test:', 'apply', 'url:', 'partie:']);
$testTo = isset($opts['test']) ? trim((string) $opts['test']) : '';
$apply = isset($opts['apply']);
// --partie=bilan ou --partie=resultats : un seul des 2 mails (le cron les envoie à des heures différentes)
$partie = isset($opts['partie']) ? (string) $opts['partie'] : '';
if (!in_array($partie, ['', 'bilan', 'resultats'], true)) {
    fwrite(STDERR, "[ERREUR] --partie=bilan ou --partie=resultats\n");
    exit(1);
}
$url = isset($opts['url']) ? rtrim((string) $opts['url'], '/') . '/' : medacapPublicUrl();
if ($testTo !== '' && $apply) {
    fwrite(STDERR, "[ERREUR] --test et --apply sont exclusifs\n");
    exit(1);
}

$uri = medacapMongoUri('mongodb://localhost:27017');
$db = (new Client($uri))->academy;
$now = new DateTimeImmutable('now', new DateTimeZone(CAMPAIGN_TZ));
echo "Mongo: $uri\nMode: " . ($apply ? 'ENVOI' : ($testTo !== '' ? "TEST -> $testTo" : 'SIMULATION')) . "\n\n";

/* Managers de la campagne (servis au moins une fois), avec ce qui leur reste */
$list = campagneLoadList($db, false);
$served = [];
foreach ($db->mail_campaigns->find(['campaign' => CAMPAIGN, 'ok' => true]) as $r) {
    $served[(string) $r['userId']] = $r['userId'];
}
$managers = [];
foreach ($db->users->find(['_id' => ['$in' => array_values($served)]]) as $m) {
    $managers[(string) $m['_id']] = $m;
}
// Destinataires du bilan : managers ayant une adresse en base
$bilanTo = [];
$noMail = [];
foreach ($managers as $m) {
    $email = trim((string) ($m['email'] ?? ''));
    if ($email !== '') {
        $bilanTo[strtolower($email)] = $email;
    } else {
        $noMail[] = trim(($m['firstName'] ?? '') . ' ' . ($m['lastName'] ?? ''));
    }
}
$bilanTo = array_values($bilanTo);

// Dernière activité : lastSeen (présence) sinon début de la dernière connexion (chaîne jj-mm-aaaa hh:mm:ss)
function lastActivity($db, $userId)
{
    $c = $db->connections->findOne(['user' => $userId], ['sort' => ['_id' => -1]]);
    if (!$c) {
        return null;
    }
    if (isset($c['lastSeen']) && $c['lastSeen'] instanceof UTCDateTime) {
        return campagneLocalDate($c['lastSeen']);
    }
    $d = DateTime::createFromFormat('d-m-Y H:i:s', (string) ($c['start'] ?? ''), new DateTimeZone(CAMPAIGN_TZ));
    return $d ?: null;
}

// Connexion récente : au cours des 7 derniers jours
$weekAgo = $now->modify('-7 days');
$rows = [];
$totEvals = $totTechTests = $upToDate = 0;
foreach ($managers as $mid => $m) {
    $e = isset($list[$mid]) ? $list[$mid] : null;
    $evals = $e ? $e['mgrCount'] : 0;
    $techTests = $e ? count(array_filter($e['rows'], function ($r) { return $r['tech']; })) : 0;
    $seen = lastActivity($db, $m['_id']);
    $rows[] = [
        'name' => trim(($m['firstName'] ?? '') . ' ' . ($m['lastName'] ?? '')),
        'agency' => trim((string) ($m['agency'] ?? '')),
        'evals' => $evals, 'techTests' => $techTests,
        'seen' => $seen,
        'seenSince' => $seen && $seen >= $weekAgo,
    ];
    $totEvals += $evals;
    $totTechTests += $techTests;
    $upToDate += $evals === 0 ? 1 : 0;
}
usort($rows, function ($a, $b) { return ($b['evals'] <=> $a['evals']) ?: strcmp($a['name'], $b['name']); });
$connected = count(array_filter($rows, function ($r) { return $r['seenSince']; }));

foreach ($rows as $r) {
    printf("%-34s | %-12s | techniciens à évaluer %2d | techniciens avec un test à passer %2d | %s\n",
        $r['name'], $r['agency'] !== '' ? $r['agency'] : '-', $r['evals'], $r['techTests'], $r['seen'] ? $r['seen']->format('d/m/Y H:i') : 'jamais');
}
echo "\nManagers : " . count($rows) . " | ont testé tous leurs techniciens : $upToDate | connectés depuis 7 jours : $connected"
    . " | techniciens à évaluer : $totEvals | techniciens avec un test à passer : $totTechTests\n";
echo "\nMail 1, bilan -> " . implode(', ', $bilanTo) . "\n  copie : " . implode(', ', BILAN_CC) . "\n";
if ($noMail) {
    echo "  [SANS ADRESSE] " . implode(', ', $noMail) . " : ne recevront pas le bilan\n";
}
echo "Mail 2, résultats -> " . implode(', ', RESULTS_TO) . "\n  copie : " . implode(', ', RESULTS_CC) . "\n";

/* Classeurs joints : régénérés à partir de la base */
function runGenerator($script, $pattern)
{
    $out = [];
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/' . $script) . ' 2>&1', $out, $code);
    $path = null;
    foreach ($out as $line) {
        if (preg_match($pattern, $line, $mm)) {
            $path = trim($mm[1]);
        }
    }
    if ($code !== 0 || $path === null || !is_file($path)) {
        fwrite(STDERR, "[ERREUR] $script (code $code) :\n" . implode("\n", array_slice($out, -10)) . "\n");
        exit(1);
    }
    return realpath($path);
}

if (!$apply && $testTo === '') {
    echo "\n[SIMULATION] Aucun envoi, classeurs non régénérés. --test=<adresse> pour un essai, --apply pour envoyer.\n";
    exit(0);
}

$stamp = $now->format('Y-m-d');
$suiviAtt = $resultatsAtt = [];
if ($partie !== 'resultats') {
    $suiviAtt = ["suivi_qcm_techniciens_cm_$stamp.xlsx" => runGenerator('update_suivi_cm_send.php', '/->\s+(\S+-send\.xlsx)$/')];
}
if ($partie !== 'bilan') {
    $resultatsAtt = ["resultats_qcm_techniciens_cm_$stamp.xlsx" => runGenerator('generate_results_send_excel.php', '/^Export : (\S+)$/')];
}

$template = function ($userName, $title, $message) {
    return generateEmailTemplate([
        'lang' => 'fr',
        'companyName' => 'MEDACAP',
        'companyTagline' => 'CFAO Mobility Academy',
        'teamName' => TEAM_NAME,
        'supportEmail' => SUPER_ADMIN_EMAIL,
        'userName' => $userName,
        'title' => $title,
        'message' => $message,
        'width' => REPORT_MAIL_WIDTH,
    ]);
};

/* Mail 1 : bilan + suivi, à tous les managers */
/* Mail */
$td = 'padding:8px 10px;border-bottom:1px solid #E5E7EB;font-size:14px;';
$tdL = $td . 'text-align:left;';
$tdC = $td . 'text-align:center;';
$table = '<table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:8px 0 12px;">'
    . '<tr style="background:#F4F6F8;">'
    . '<th style="' . $tdL . 'width:30%;">Manager</th><th style="' . $tdL . 'width:16%;">Agence</th>'
    . '<th style="' . $tdC . 'width:18%;">Techniciens à évaluer</th>'
    . '<th style="' . $tdC . 'width:18%;">Techniciens avec un test à passer</th>'
    . '<th style="' . $tdC . 'width:18%;">Dernière connexion</th></tr>';
foreach ($rows as $r) {
    $table .= '<tr><td style="' . $tdL . '">' . h($r['name']) . '</td>'
        . '<td style="' . $tdL . '">' . ($r['agency'] !== '' ? h($r['agency']) : '-') . '</td>'
        . '<td style="' . $tdC . '">' . ($r['evals'] > 0 ? '<b style="color:#B45309;">' . $r['evals'] . '</b>' : '<span style="color:#047857;">Terminé</span>') . '</td>'
        . '<td style="' . $tdC . '">' . $r['techTests'] . '</td>'
        . '<td style="' . $tdC . 'white-space:nowrap;' . ($r['seenSince'] ? '' : 'color:#B91C1C;') . '">' . ($r['seen'] ? $r['seen']->format('d/m H:i') : 'Jamais') . '</td></tr>';
}
$table .= '</table>';

$kpi = function ($value, $label) {
    return '<td style="padding:10px 12px;background:#F4F6F8;border-radius:6px;text-align:center;width:33%;">'
        . '<div style="font-size:22px;font-weight:700;color:#1F2937;">' . $value . '</div>'
        . '<div style="font-size:12px;color:#4B5563;">' . h($label) . '</div></td>';
};
$message = '<p>Voici l\'avancement au ' . $now->format('d/m/Y à H:i') . ' de la Mesure de compétences des Techniciens de CFAO Equipment Cameroun - vague 2026.</p>'
    . '<table cellpadding="0" cellspacing="6" style="width:100%;margin:8px 0 14px;"><tr>'
    . $kpi($upToDate . ' / ' . count($rows), 'managers ayant testé tous leurs techniciens')
    . $kpi($totTechTests, 'techniciens ayant encore un test à passer')
    . $kpi($connected . ' / ' . count($rows), 'managers connectés depuis la semaine passée')
    . '</tr></table>'
    . '<h3 style="margin:18px 0 6px;font-size:17px;">Par manager</h3>' . $table
    . '<p style="font-size:13px;color:#374151;margin:12px 0 4px;"><b>Comment lire ce tableau</b></p>'
    . '<ul style="font-size:13px;color:#4B5563;padding-left:20px;margin:0 0 8px;line-height:1.6;">'
    . '<li><b>Techniciens à évaluer</b> : techniciens dont le manager n\'a pas encore rempli l\'évaluation. « Terminé » : tous sont évalués.</li>'
    . '<li><b>Techniciens avec un test à passer</b> : techniciens de l\'équipe qui n\'ont pas encore terminé leur QCM ou leur auto-évaluation.</li>'
    . '<li><b>Dernière connexion</b> : en rouge, le manager ne s\'est pas connecté à MEDACAP depuis 7 jours.</li>'
    . '<li>Tant qu\'il reste des techniciens à évaluer, le manager reçoit un rappel automatique par mail tous les 3 jours ouvrés.</li></ul>'
    . '<h3 style="margin:18px 0 6px;font-size:17px;">Pièce jointe</h3>'
    . '<p style="margin:0;"><b>Suivi QCM</b> : statut de chaque QCM par technicien, synthèse par agence.</p>'
    // Adresse en texte, sans lien ni bouton : un lien http vers une IP fait bloquer les pièces jointes par Outlook (essais du 05/11)
    . ($url !== '' ? '<p style="margin:16px 0 0;">Adresse de MEDACAP, à saisir dans le navigateur : <b>' . h(preg_replace('#^https?://#', '', rtrim($url, '/'))) . '</b></p>' : '');
$mails = [
    'bilan' => [
        'to' => $bilanTo, 'cc' => BILAN_CC, 'att' => $suiviAtt,
        'subject' => 'MEDACAP - Suivi hebdomadaire Cameroun au ' . $now->format('d/m/Y'),
        'html' => $template('à tous', 'Suivi hebdomadaire - Cameroun', $message),
    ],
];

/* Mail 2 : résultats, à M.C. BENGONO */
$resultsUser = $db->users->findOne(['email' => RESULTS_TO[0]]);
$resultsMessage = '<p>Veuillez trouver ci-joint les résultats au ' . $now->format('d/m/Y à H:i')
    . ' de la Mesure de compétences des Techniciens de CFAO Equipment Cameroun - vague 2026.</p>'
    . '<ul style="padding-left:20px;margin:0 0 8px;line-height:1.6;">'
    . '<li><b>Résultats QCM</b> : notes théoriques et pratiques de chaque technicien, par niveau.</li>'
    . '<li>Vert : 80 % et plus, jaune : 60 à 79 %, rouge : moins de 60 %.</li>'
    . '<li>« En attente évaluation manager » : la note pratique sera connue quand le manager aura évalué le technicien.</li></ul>';
$mails['resultats'] = [
    'to' => RESULTS_TO, 'cc' => RESULTS_CC, 'att' => $resultatsAtt,
    'subject' => 'MEDACAP - Résultats QCM Cameroun au ' . $now->format('d/m/Y'),
    'html' => $template($resultsUser ? managerGreeting($resultsUser, false) : 'à tous', 'Résultats QCM - Cameroun', $resultsMessage),
];

if ($partie !== '') {
    $mails = [$partie => $mails[$partie]];
}

$allOk = true;
foreach ($mails as $kind => $mail) {
    if (!$mail['to']) {
        echo "\n[$kind] aucun destinataire, mail non envoyé\n";
        $allOk = false;
        continue;
    }
    if ($testTo !== '') {
        $html = preg_replace('/<body[^>]*>/i', '$0' . campagneTestBox(
            '<b>[TEST] Rapport du vendredi, mail ' . ($kind === 'bilan' ? '1/2 (bilan)' : '2/2 (résultats)') . '</b><br>'
            . 'Envoi réel à : ' . h(implode(', ', $mail['to'])) . '<br>Copie : ' . h(implode(', ', $mail['cc'])), REPORT_MAIL_WIDTH), $mail['html'], 1);
        $ok = sendCampaignMail($testTo, '[TEST] ' . $mail['subject'], $html, [], $mail['att']);
        echo "\nTest $kind envoyé à $testTo : " . ($ok ? 'OK' : 'ÉCHEC') . "\n";
    } else {
        $ok = sendCampaignMail(implode(', ', $mail['to']), $mail['subject'], $mail['html'], $mail['cc'], $mail['att']);
        $db->mail_campaigns->insertOne([
            'campaign' => REPORT_CAMPAIGN . '-' . $kind, 'email' => implode(', ', $mail['to']), 'cc' => $mail['cc'], 'ok' => $ok,
            'pendingEvals' => $totEvals, 'sentAt' => new UTCDateTime(),
        ]);
        echo "\nMail $kind envoyé : " . ($ok ? 'OK' : 'ÉCHEC') . "\n";
    }
    $allOk = $allOk && $ok;
}
exit($allOk ? 0 : 1);
