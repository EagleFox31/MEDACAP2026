<?php
require_once __DIR__ . '/../core/medacap_config.php';
/**
 * Campagne « MEDACAP change d'adresse » : un mail à chaque manager (au sens users.manager des techniciens)
 * qui a encore des évaluations ou des collaborateurs avec un test en attente.
 * Contenu : nouvelle adresse, ce qui reste à faire (tableau par collaborateur), méthodes de connexion.
 *
 * En attente = allocation Factuel/Declaratif d'un technicien actif (hors candidat), test actif, avec
 * active != true (test du technicien) ou, pour le déclaratif, activeManager != true (évaluation du manager).
 *
 * Usage:
 *   php scripts/campagne_nouvelle_adresse.php                         simulation (liste, aucun envoi)
 *   php scripts/campagne_nouvelle_adresse.php --test=a@cfao.com [--manager=<id|email>]
 *                                                                     envoie le mail d'un seul manager à a@cfao.com
 *   php scripts/campagne_nouvelle_adresse.php --apply                 envoi réel, une seule fois par manager
 *   --url=http://10.68.0.15:8010/                                     adresse annoncée (défaut : MEDACAP_PUBLIC_URL)
 */
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../views/sendMail.php';

use MongoDB\Client;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

const CAMPAIGN = 'nouvelle-adresse-2026-11';
const OLD_ADDRESS = '10.68.0.7';
const EN_COUNTRIES =['Rwanda', 'Kenya', 'Uganda', 'Tanzania', 'Ghana', 'Nigeria', 'Malawi', 'Zambia'];

$opts = getopt('', ['test:', 'manager:', 'apply', 'url:']);
$testTo = isset($opts['test']) ? trim((string) $opts['test']) : '';
$apply = isset($opts['apply']);
$url = isset($opts['url']) ? rtrim((string) $opts['url'], '/') . '/' : medacapPublicUrl();
if ($url === '') {
    fwrite(STDERR, "[ERREUR] Adresse inconnue : MEDACAP_PUBLIC_URL vide, passer --url=\n");
    exit(1);
}
if ($testTo !== '' && $apply) {
    fwrite(STDERR, "[ERREUR] --test et --apply sont exclusifs\n");
    exit(1);
}

$uri = medacapMongoUri('mongodb://localhost:27017');
$db = (new Client($uri))->academy;
echo "Mongo: $uri\nAdresse annoncée: $url\nMode: " . ($apply ? 'ENVOI' : ($testTo !== '' ? "TEST -> $testTo" : 'SIMULATION')) . "\n\n";

$techs = [];
foreach ($db->users->find(['profile' => 'Technicien', 'active' => true, 'isCandidat' => ['$ne' => true]]) as $t) {
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

function h($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function buildCampaignMail($manager, array $rows, $url, $ownTests)
{
    $isEn = in_array((string) ($manager['country'] ?? ''), EN_COUNTRIES, true);
    $L = $isEn ? [
        'subject' => 'MEDACAP has moved: new address and pending assessments',
        'title' => 'MEDACAP has a new address',
        'intro' => 'MEDACAP is now available at this address, from any computer on the CFAO network:',
        'old' => 'The former address (' . OLD_ADDRESS . ') is no longer in service. Please update your bookmarks.',
        'todo' => 'What is still pending in your team',
        'todoIntro' => count($rows) . ' of your team members are still waiting for an action:',
        'colName' => 'Team member', 'colMgr' => 'Your assessment', 'colTech' => 'Their test',
        'yes' => 'To do', 'no' => 'Done',
        'howEval' => 'To assess a team member: menu <b>“évaluer vos collaborateurs” › “Liste des collaborateurs à évaluer”</b>. Team members take their own tests with their own account.',
        'own' => 'You also have ' . $ownTests . ' test(s) of your own to take: menu <b>“Mesure des compétences” › “Mes Tests à Passer”</b>.',
        'how' => 'How to sign in',
        'guide' => 'The steps below are simplified; the real screens are in the illustrated guide:',
        'guideLink' => 'Open the illustrated guide',
        'm1' => '<b>Username or email + password</b>: your username is <b>%s</b>; you can also type your email address. Your password has not changed.',
        'm2' => '<b>Without a password</b>, in 3 steps:',
        's1' => 'Click <b>“Sign in with your email”</b>, enter your @cfao.com address, then <b>“Send me the code”</b>.',
        's2' => 'You receive this email: a 6-digit code (valid 10 minutes) and a button (30 minutes) that works from a CFAO network computer.',
        's3' => 'Type the code from the email on the MEDACAP page, then <b>“Verify code”</b>.',
        'm3' => '<b>Forgotten password</b>: click <b>“Forgot your password?”</b> and follow the same 3 steps.',
        'cta' => 'Open MEDACAP',
    ] : [
        'subject' => 'MEDACAP change d\'adresse : nouvelle adresse et évaluations en attente',
        'title' => 'MEDACAP change d\'adresse',
        'intro' => 'MEDACAP est désormais accessible à cette adresse, depuis un poste du réseau CFAO :',
        'old' => 'L\'ancienne adresse (' . OLD_ADDRESS . ') n\'est plus en service. Pensez à mettre à jour vos favoris.',
        'todo' => 'Ce qui reste à faire dans votre équipe',
        'todoIntro' => count($rows) . ' de vos collaborateurs attendent encore une action :',
        'colName' => 'Collaborateur', 'colMgr' => 'Votre évaluation', 'colTech' => 'Son test',
        'yes' => 'À faire', 'no' => 'Fait',
        'howEval' => 'Pour évaluer un collaborateur : menu <b>« évaluer vos collaborateurs » › « Liste des collaborateurs à évaluer »</b>. Vos collaborateurs passent leurs tests avec leur propre compte.',
        'own' => 'Vous avez aussi ' . $ownTests . ' test(s) à passer vous-même : menu <b>« Mesure des compétences » › « Mes Tests à Passer »</b>.',
        'how' => 'Comment vous connecter',
        'guide' => 'Les écrans ci-dessous sont simplifiés ; les vrais écrans sont dans le guide illustré :',
        'guideLink' => 'Voir le guide illustré',
        'm1' => '<b>Identifiant ou adresse mail + mot de passe</b> : votre identifiant est <b>%s</b> ; vous pouvez aussi taper votre adresse mail. Votre mot de passe n\'a pas changé.',
        'm2' => '<b>Sans mot de passe</b>, en 3 étapes :',
        's1' => 'Cliquez sur <b>« Connectez-vous avec votre mail »</b>, saisissez votre adresse @cfao.com puis <b>« Recevoir le code »</b>.',
        's2' => 'Vous recevez ce mail : un code à 6 chiffres (valable 10 minutes) et un bouton (30 minutes) utilisable depuis un poste du réseau CFAO.',
        's3' => 'Tapez sur la page MEDACAP le code reçu par mail, puis <b>« Valider le code »</b>.',
        'm3' => '<b>Mot de passe oublié</b> : cliquez sur <b>« Mot de passe oublié ? »</b> et suivez les 3 mêmes étapes.',
        'cta' => 'Ouvrir MEDACAP',
    ];

    /* Maquettes HTML des écrans : Outlook CFAO bloque les images jointes (même png/jpg) et distantes */
    $S = $isEn ? [
        'login' => 'Access your area', 'or' => 'or', 'magic' => 'Sign in with your email', 'forgot' => 'Forgot your password?',
        'send' => 'Send me the code', 'codeHint' => 'Code from the email', 'verify' => 'Verify code',
    ] : [
        'login' => 'Accedez à votre espace', 'or' => 'ou', 'magic' => 'Connectez-vous avec votre mail', 'forgot' => 'Mot de passe oublié ?',
        'send' => 'Recevoir le code', 'codeHint' => 'Code reçu par mail', 'verify' => 'Valider le code',
    ];
    $field = function ($value) {
        return '<tr><td style="padding:0 0 10px;"><table width="100%" cellpadding="0" cellspacing="0"><tr><td style="background:#FFFFFF;border:1px solid #D1D5DB;border-radius:6px;padding:8px 10px;font-size:14px;color:#374151;">' . h($value) . '</td></tr></table></td></tr>';
    };
    $button = function ($label, $outline = false) {
        $style = $outline
            ? 'border:1px solid #FFFFFF;color:#FFFFFF;'
            : 'background:#E5294A;border:1px solid #E5294A;color:#FFFFFF;';
        return '<tr><td style="padding:0 0 10px;"><table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="' . $style . 'border-radius:18px;padding:8px 10px;font-size:14px;font-weight:700;">' . h($label) . '</td></tr></table></td></tr>';
    };
    $text = function ($html, $align = 'center') {
        return '<tr><td align="' . $align . '" style="padding:0 0 10px;font-size:13px;color:#FFFFFF;">' . $html . '</td></tr>';
    };
    $screen = function ($rows) {
        return '<table cellpadding="0" cellspacing="0" width="300" style="width:300px;margin:8px 0 4px;background:#5B6170;border-radius:10px;">'
            . '<tr><td style="padding:14px 18px 4px;"><table width="100%" cellpadding="0" cellspacing="0">'
            . '<tr><td align="center" style="padding:0 0 12px;font-size:15px;font-weight:700;color:#FFFFFF;">cfao ACADEMY</td></tr>'
            . $rows . '</table></td></tr></table>';
    };
    $shot = function ($name) use ($S, $field, $button, $text, $screen, $isEn) {
        switch ($name) {
            case 'etape1':
                return $screen($field('prenom.nom@cfao.com') . $field('••••••••••')
                    . $text('<u>' . h($S['forgot']) . '</u>', 'right') . $button($S['login'])
                    . $text(h($S['or'])) . $button('✉ ' . $S['magic'], true));
            case 'etape2':
                return $screen($text('<b style="font-size:14px;">' . h($S['magic']) . '</b>') . $field('prenom.nom@cfao.com') . $button($S['send']));
            case 'etape3':
                return '<div style="max-width:400px;margin:8px 0 4px;padding:12px 16px 2px;border:1px solid #E5E7EB;border-top:4px solid #D7000F;border-radius:6px;">'
                    . '<div style="font-size:15px;font-weight:700;color:#1F2937;">MEDACAP - ' . ($isEn ? 'Your sign-in code' : 'Votre code de connexion') . '</div>'
                    . medacapLoginCodeBlockHtml('579525', 10, $isEn ? 'en' : 'fr') . '</div>';
            default:
                return $screen($text(h($S['codeHint'])) . $field('579 525') . $button($S['verify']));
        }
    };

    $td = 'padding:6px 8px;border-bottom:1px solid #E5E7EB;font-size:14px;';
    $badge = function ($todo) use ($L) {
        return $todo
            ? '<span style="color:#B45309;font-weight:700;">' . h($L['yes']) . '</span>'
            : '<span style="color:#047857;">' . h($L['no']) . '</span>';
    };
    uasort($rows, function ($a, $b) { return ($b['mgr'] <=> $a['mgr']) ?: strcmp($a['name'], $b['name']); });
    $table = '<table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:8px 0 12px;">'
        . '<tr style="background:#F4F6F8;text-align:left;">'
        . '<th style="' . $td . '">' . h($L['colName']) . '</th>'
        . '<th style="' . $td . '">' . h($L['colMgr']) . '</th><th style="' . $td . '">' . h($L['colTech']) . '</th></tr>';
    foreach ($rows as $r) {
        $table .= '<tr><td style="' . $td . '">' . h($r['name']) . '</td>'
            . '<td style="' . $td . '">' . $badge($r['mgr']) . '</td><td style="' . $td . '">' . $badge($r['tech']) . '</td></tr>';
    }
    $table .= '</table>';

    $message = '<p>' . $L['intro'] . '</p>'
        . '<p style="margin:12px 0;font-size:20px;font-weight:700;"><a href="' . h($url) . '" style="color:#1F5FBF;">' . h($url) . '</a></p>'
        . '<p>' . $L['old'] . '</p>'
        . '<h3 style="margin:22px 0 6px;font-size:17px;">' . h($L['todo']) . '</h3>'
        . '<p>' . h($L['todoIntro']) . '</p>' . $table
        . '<p>' . $L['howEval'] . '</p>'
        . ($ownTests > 0 ? '<p>' . $L['own'] . '</p>' : '')
        . '<h3 style="margin:22px 0 6px;font-size:17px;">' . h($L['how']) . '</h3>'
        . '<p>' . $L['guide'] . ' <a href="' . h($url . 'public/guide-connexion/' . ($isEn ? 'en' : 'fr') . '.html') . '" style="color:#1F5FBF;font-weight:700;">' . h($L['guideLink']) . '</a></p>'
        . '<ol style="padding-left:20px;margin:0;">'
        . '<li style="margin-bottom:8px;">' . sprintf($L['m1'], h($manager['username'] ?? '')) . $shot('etape1') . '</li>'
        . '<li style="margin-bottom:8px;">' . $L['m2']
        . '<ol type="a" style="padding-left:20px;margin:8px 0 0;">'
        . '<li style="margin-bottom:8px;">' . $L['s1'] . $shot('etape2') . '</li>'
        . '<li style="margin-bottom:8px;">' . $L['s2'] . $shot('etape3') . '</li>'
        . '<li style="margin-bottom:8px;">' . $L['s3'] . $shot('etape4') . '</li>'
        . '</ol></li>'
        . '<li>' . $L['m3'] . '</li></ol>';

    $html = generateEmailTemplate([
        'lang' => $isEn ? 'en' : 'fr',
        'companyName' => 'MEDACAP',
        'companyTagline' => 'CFAO Mobility Academy',
        'teamName' => TEAM_NAME,
        'supportEmail' => SUPER_ADMIN_EMAIL,
        'userName' => trim(($manager['firstName'] ?? '') . ' ' . ($manager['lastName'] ?? '')),
        'title' => $L['title'],
        'message' => $message,
        'ctaText' => $L['cta'],
        'ctaLink' => $url,
    ]);
    return [$L['subject'], $html];
}

function sendCampaignMail($to, $subject, $html)
{
    $headers = buildHeaders([], SUPER_ADMIN_EMAIL, false) . "\r\nContent-Transfer-Encoding: quoted-printable";
    return mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', quoted_printable_encode($html), $headers);
}

/* Destinataires, triés par filiale puis nom */
$list = [];
foreach ($pending as $mid => $techRows) {
    if (!isset($managers[$mid])) {
        echo "[IGNORÉ] manager $mid inactif ou introuvable (" . count($techRows) . " collaborateurs)\n";
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

$withMail = 0;
foreach ($list as $mid => $e) {
    $m = $e['m'];
    $email = trim((string) ($m['email'] ?? ''));
    $withMail += $email !== '' ? 1 : 0;
    printf("%-26s | %-24s | %-34s | %-28s | évals %2d | collab. %2d | ses tests %d\n",
        $m['subsidiary'] ?? '', $m['profile'] ?? '', trim(($m['firstName'] ?? '') . ' ' . ($m['lastName'] ?? '')),
        $email !== '' ? $email : '(SANS ADRESSE)', $e['mgrCount'], count($e['rows']), $e['own']);
}
echo "\nManagers : " . count($list) . " (avec adresse : $withMail)\n";

if ($testTo !== '') {
    $pick = null;
    $want = isset($opts['manager']) ? strtolower(trim((string) $opts['manager'])) : '';
    foreach ($list as $mid => $e) {
        if ($want === '' ? ($pick === null || count($e['rows']) > count($pick['rows'])) : ($want === strtolower($mid) || $want === strtolower((string) ($e['m']['email'] ?? '')))) {
            $pick = $e;
        }
    }
    if ($pick === null) {
        fwrite(STDERR, "[ERREUR] Manager introuvable : $want\n");
        exit(1);
    }
    list($subject, $html) = buildCampaignMail($pick['m'], $pick['rows'], $url, $pick['own']);
    $ok = sendCampaignMail($testTo, '[TEST] ' . $subject, $html);
    echo "\nTest : mail de " . trim($pick['m']['firstName'] . ' ' . $pick['m']['lastName']) . " envoyé à $testTo : " . ($ok ? 'OK' : 'ÉCHEC') . "\n";
    exit($ok ? 0 : 1);
}

if (!$apply) {
    echo "\n[SIMULATION] Aucun envoi. --test=<adresse> pour un essai, --apply pour envoyer.\n";
    exit(0);
}

$sent = $skipped = $failed = 0;
foreach ($list as $mid => $e) {
    $email = trim((string) ($e['m']['email'] ?? ''));
    if ($email === '') {
        continue;
    }
    if ($db->mail_campaigns->findOne(['campaign' => CAMPAIGN, 'userId' => $e['m']['_id'], 'ok' => true])) {
        $skipped++;
        continue;
    }
    list($subject, $html) = buildCampaignMail($e['m'], $e['rows'], $url, $e['own']);
    $ok = sendCampaignMail($email, $subject, $html);
    $db->mail_campaigns->insertOne([
        'campaign' => CAMPAIGN, 'userId' => $e['m']['_id'], 'email' => $email, 'ok' => $ok,
        'pendingTechs' => count($e['rows']), 'sentAt' => new UTCDateTime(),
    ]);
    $ok ? $sent++ : $failed++;
    echo ($ok ? '[OK]    ' : '[ÉCHEC] ') . $email . "\n";
}
echo "\nEnvoyés : $sent, déjà envoyés : $skipped, échecs : $failed\n";
exit($failed > 0 ? 1 : 0);
