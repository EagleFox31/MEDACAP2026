<?php
/**
 * Campagne Cameroun « MEDACAP change d'adresse » : périmètre, évaluations en attente par manager,
 * historique des envois (mail_campaigns) et envoi des mails.
 * Partagé par scripts/campagne_nouvelle_adresse.php (mail, rectificatif, relance) et scripts/rapport_campagne_cameroun.php.
 */
require_once __DIR__ . '/../../views/sendMail.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use MongoDB\BSON\UTCDateTime;

const CAMPAIGN = 'nouvelle-adresse-2026-11';
const OLD_ADDRESS = '10.68.0.7';
const EN_COUNTRIES =['Rwanda', 'Kenya', 'Uganda', 'Tanzania', 'Ghana', 'Nigeria', 'Malawi', 'Zambia'];
// Périmètre : techniciens CMI importés en juin/juillet 2026 (users.created au format « jj-mm-aaaa … »)
// CMI = CFAO Mobility Cameroon : les deux libellés coexistent dans users.subsidiary
const SCOPE_SUBSIDIARY = ['CAMEROON MOTORS INDUSTRIES', 'CFAO MOBILITY CAMEROON'];
const SCOPE_CREATED = '^\d\d-0[67]-2026';
// Copie de chaque envoi réel : Annick ETOLO, support (Laurent GONIN, M.C. BENGONO et A.Y. BOKALLY retirés pour éviter le spam)
const CAMPAIGN_CC = ['aetolo-ext@cfao.com', 'jakaa-ext@cfao.com'];
// Manager sans adresse : mail adressé à Annick ETOLO, qui le fait suivre
const RELAY_TO = 'aetolo-ext@cfao.com';
const CAMPAIGN_TZ = 'Africa/Douala';
// Largeur des mails de campagne (tableaux par manager) ; les autres mails MEDACAP restent à 600 px
const CAMPAIGN_MAIL_WIDTH = 800;

// Encadré « [TEST] » placé au-dessus du mail d'essai, à la largeur de la carte (table : Outlook ignore max-width sur un div)
function campagneTestBox($innerHtml, $width = CAMPAIGN_MAIL_WIDTH)
{
    return '<table cellpadding="0" cellspacing="0" border="0" width="' . $width . '" align="center" style="width:100%;max-width:' . $width . 'px;margin:12px auto;">'
        . '<tr><td style="padding:12px 16px;border:2px dashed #B45309;background:#FFFBEB;font-family:Arial,sans-serif;font-size:13px;color:#1F2937;">'
        . $innerHtml . '</td></tr></table>';
}

/**
 * Managers ayant des collaborateurs en attente, triés par filiale puis nom :
 * [managerId => ['m' => user, 'rows' => [techId => ['name', 'mgr', 'tech']], 'mgrCount' => évaluations du manager, 'own' => ses tests]].
 * En attente = allocation Factuel/Declaratif d'un technicien actif (hors candidat), test actif, avec
 * active != true (test du technicien) ou, pour le déclaratif, activeManager != true (évaluation du manager).
 */
function campagneLoadList($db, $verbose = true)
{
    $techs = [];
    foreach ($db->users->find([
        'profile' => 'Technicien', 'active' => true, 'isCandidat' => ['$ne' => true],
        'subsidiary' => ['$in' => SCOPE_SUBSIDIARY], 'created' => new Regex(SCOPE_CREATED, ''),
    ]) as $t) {
        $techs[(string) $t['_id']] = $t;
    }
    $techIds = array_map(function ($id) { return new ObjectId($id); }, array_keys($techs));

    $pending = [];
    $allocs = $db->allocations->find([
        'type' => ['$in' => ['Factuel', 'Declaratif']],
        'activeTest' => ['$ne' => false],
        'user' => ['$in' => $techIds],
    ]);
    foreach ($allocs as $a) {
        $tid = (string) $a['user'];
        $mid = isset($techs[$tid]['manager']) ? (string) $techs[$tid]['manager'] : '';
        if ($mid === '') {
            continue;
        }
        $mgrTodo = $a['type'] === 'Declaratif' && ($a['activeManager'] ?? false) !== true;
        $techTodo = ($a['active'] ?? false) !== true;
        if (!$mgrTodo && !$techTodo) {
            continue;
        }
        if (!isset($pending[$mid][$tid])) {
            $pending[$mid][$tid] = ['mgr' => false, 'tech' => false];
        }
        $pending[$mid][$tid]['mgr'] = $pending[$mid][$tid]['mgr'] || $mgrTodo;
        $pending[$mid][$tid]['tech'] = $pending[$mid][$tid]['tech'] || $techTodo;
    }

    $managers = [];
    foreach ($db->users->find(['_id' => ['$in' => array_map(function ($id) { return new ObjectId($id); }, array_keys($pending))], 'active' => true]) as $m) {
        $managers[(string) $m['_id']] = $m;
    }

    $list = [];
    foreach ($pending as $mid => $techRows) {
        if (!isset($managers[$mid])) {
            if ($verbose) {
                echo "[IGNORÉ] manager $mid inactif ou introuvable (" . count($techRows) . " collaborateurs)\n";
            }
            continue;
        }
        $m = $managers[$mid];
        $rows = [];
        $mgrCount = 0;
        foreach ($techRows as $tid => $p) {
            $t = $techs[$tid];
            $rows[$tid] = [
                'name' => trim(($t['firstName'] ?? '') . ' ' . ($t['lastName'] ?? '')),
                'mgr' => $p['mgr'],
                'tech' => $p['tech'],
            ];
            $mgrCount += $p['mgr'] ? 1 : 0;
        }
        $list[$mid] = ['m' => $m, 'rows' => $rows, 'mgrCount' => $mgrCount, 'own' => ownPendingTests($db, $m['_id'])];
    }
    uasort($list, function ($a, $b) {
        return strcmp(($a['m']['subsidiary'] ?? '') . ($a['m']['lastName'] ?? ''), ($b['m']['subsidiary'] ?? '') . ($b['m']['lastName'] ?? ''));
    });
    return $list;
}

/** Tests encore à passer par le manager lui-même. */
function ownPendingTests($db, $managerId)
{
    return (int) $db->allocations->count([
        'user' => $managerId,
        'type' => ['$in' => ['Factuel', 'Declaratif']],
        'activeTest' => ['$ne' => false],
        'active' => ['$ne' => true],
    ]);
}

/** Mails réels reçus par un manager pendant la campagne (mail, rectificatif, relances), du plus ancien au plus récent. */
function campagneMailHistory($db, $managerId)
{
    $out = [];
    foreach ($db->mail_campaigns->find(
        ['campaign' => new Regex('^' . preg_quote(CAMPAIGN, '/'), ''), 'userId' => $managerId, 'ok' => true],
        ['sort' => ['sentAt' => 1]]
    ) as $r) {
        $out[] = $r;
    }
    return $out;
}

/** Date locale (Douala) d'une trace mail_campaigns. */
function campagneLocalDate(UTCDateTime $d)
{
    return $d->toDateTime()->setTimezone(new DateTimeZone(CAMPAIGN_TZ));
}

function h($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

// « M. Clovis Forbang » / « Mme … » selon users.gender, nom remis en casse normale
function managerGreeting($manager, $isEn)
{
    $civ = ['Masculin' => $isEn ? 'Mr' : 'M.', 'Feminin' => $isEn ? 'Ms' : 'Mme'];
    $gender = (string) ($manager['gender'] ?? '');
    $name = mb_convert_case(mb_strtolower(trim(($manager['firstName'] ?? '') . ' ' . ($manager['lastName'] ?? '')), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    return trim((isset($civ[$gender]) ? $civ[$gender] : '') . ' ' . $name);
}

/** $attachments : [nom affiché => chemin du fichier] (classeurs Excel). */
function sendCampaignMail($to, $subject, $html, array $cc = [], array $attachments = [])
{
    $cc = array_values(array_diff($cc, [$to]));
    $headers = buildHeaders($cc, SUPER_ADMIN_EMAIL, false);
    $subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    if (!$attachments) {
        return mail($to, $subject, quoted_printable_encode($html), $headers . "\r\nContent-Transfer-Encoding: quoted-printable");
    }
    $boundary = '=_Medacap_' . md5((string) microtime(true));
    $headers = str_replace('Content-Type: text/html; charset=UTF-8', 'Content-Type: multipart/mixed; boundary="' . $boundary . '"', $headers);
    $body = "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
        . quoted_printable_encode($html) . "\r\n";
    foreach ($attachments as $name => $path) {
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name);
        $body .= "--$boundary\r\n"
            // Type générique : Outlook bloque ces mails (relais anonyme) quand le type Excel est déclaré (essais du 05/11)
            . 'Content-Type: application/octet-stream; name="' . $name . "\"\r\n"
            . "Content-Transfer-Encoding: base64\r\n"
            . 'Content-Disposition: attachment; filename="' . $name . "\"\r\n\r\n"
            . chunk_split(base64_encode((string) file_get_contents($path))) . "\r\n";
    }
    return mail($to, $subject, $body . "--$boundary--", $headers);
}
