<?php
require_once __DIR__ . '/../core/medacap_config.php';
/**
 * Campagne « MEDACAP change d'adresse » : un mail à chaque manager (au sens users.manager des techniciens)
 * qui a encore des évaluations ou des collaborateurs avec un test en attente.
 * Contenu : nouvelle adresse, ce qui reste à faire (tableau par collaborateur), méthodes de connexion.
 *
 * Périmètre et calcul des évaluations en attente : scripts/includes/campagne_cameroun.php.
 *
 * Usage:
 *   php scripts/campagne_nouvelle_adresse.php                         simulation (liste, aucun envoi)
 *   php scripts/campagne_nouvelle_adresse.php --test=a@cfao.com [--manager=<id|email>]
 *                                                                     envoie le mail d'un seul manager à a@cfao.com
 *   php scripts/campagne_nouvelle_adresse.php --apply [--manager=<id|email>]  envoi réel (copie CAMPAIGN_CC), une seule fois par manager
 *   ... --rectif --manager=<id|email> [--test=<adresse>|--apply]  liste corrigée (copie sans Laurent GONIN)
 *   ... --relance [--jours=3] [--test=<adresse>|--apply]            rappel aux managers qui ont encore des évaluations à faire,
 *                                                                     --jours après leur dernier mail de campagne, jours ouvrés seulement
 *   --url=http://10.68.0.15:8010/                                     adresse annoncée (défaut : MEDACAP_PUBLIC_URL)
 */
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/includes/campagne_cameroun.php';

use MongoDB\Client;
use MongoDB\BSON\UTCDateTime;

$opts = getopt('', ['test:', 'manager:', 'apply', 'url:', 'rectif', 'relance', 'jours:']);
// --rectif : liste corrigée aux managers déjà servis (--manager obligatoire), copie Annick ETOLO + support seulement
$rectif = isset($opts['rectif']);
if ($rectif && empty($opts['manager'])) {
    fwrite(STDERR, "[ERREUR] --rectif exige --manager\n");
    exit(1);
}
// --relance : rappel --jours (défaut 3) après le dernier mail reçu, tant que le manager a des évaluations à faire
$relance = isset($opts['relance']);
$jours = isset($opts['jours']) ? max(1, (int) $opts['jours']) : 3;
if ($rectif && $relance) {
    fwrite(STDERR, "[ERREUR] --rectif et --relance sont exclusifs\n");
    exit(1);
}
$mode = $rectif ? 'rectif' : ($relance ? 'relance' : '');
$campaign = $mode !== '' ? CAMPAIGN . '-' . $mode : CAMPAIGN;
$cc = $rectif ? array_values(array_diff(CAMPAIGN_CC, ['lgonin@cfao.com', 'mcbengono@cfao.com', 'aybokally@cfao.com'])) : CAMPAIGN_CC;
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
echo "Mongo: $uri\nAdresse annoncée: $url\nMode: " . ($apply ? 'ENVOI' : ($testTo !== '' ? "TEST -> $testTo" : 'SIMULATION')) . ($mode !== '' ? " ($mode)" : '') . "\n\n";

$list = campagneLoadList($db);

/** $mode : '' (mail de campagne), 'rectif' (liste corrigée) ou 'relance' (rappel). */
function buildCampaignMail($manager, array $rows, $url, $ownTests, $mode = '')
{
    // Seules les évaluations du manager figurent dans le tableau
    $rows = array_filter($rows, function ($r) { return $r['mgr']; });
    $isEn = in_array((string) ($manager['country'] ?? ''), EN_COUNTRIES, true);
    $L = $isEn ? [
        'subject' => 'MEDACAP has moved: new address and pending assessments',
        'title' => 'MEDACAP has a new address',
        'intro' => 'MEDACAP is now available at this address, from any computer on the CFAO network:',
        'old' => 'The former address (' . OLD_ADDRESS . ') is no longer in service. Please update your bookmarks.',
        'rSubject' => 'Correction - MEDACAP: team members to evaluate',
        'rTitle' => 'Correction',
        'rIntro' => 'The list of team members to evaluate in our previous message was wrong, following a reassignment. Here is the updated list (' . count($rows) . ' team member(s)):',
        'rLink' => 'MEDACAP is available at this address, from a CFAO network computer:',
        'kSubject' => 'Reminder - MEDACAP: ' . count($rows) . ' team member(s) to evaluate',
        'kTitle' => 'Reminder: assessments pending',
        'kIntro' => count($rows) . ' of your team members are still waiting for your assessment in MEDACAP:',
        'kIntroOne' => 'One of your team members is still waiting for your assessment in MEDACAP:',
        'kLogin' => 'To sign in: your username <b>%s</b> (or your email address) and your usual password, or <b>“Sign in with your email”</b> to receive a code.',
        'kHelp' => 'If you have any difficulty, simply reply to this email.',
        'todo' => 'What is still pending in your team',
        'todoIntro' => count($rows) . ' of your team members are still waiting for an action:',
        'todoOne' => 'You have 1 team member to evaluate:',
        'colName' => 'Team member', 'colMgr' => 'Your assessment',
        'yes' => 'To evaluate', 'no' => 'Evaluated',
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
        'rSubject' => 'Rectificatif - MEDACAP : vos collaborateurs à évaluer',
        'rTitle' => 'Rectificatif',
        'rIntro' => 'La liste de vos collaborateurs à évaluer figurant dans notre précédent message était erronée, suite à une réaffectation. Voici la liste à jour (' . count($rows) . ' collaborateur(s)) :',
        'rLink' => 'MEDACAP est accessible à cette adresse, depuis un poste du réseau CFAO :',
        'kSubject' => 'Rappel - MEDACAP : ' . count($rows) . ' collaborateur(s) à évaluer',
        'kTitle' => 'Rappel : évaluations en attente',
        'kIntro' => count($rows) . ' de vos collaborateurs attendent encore votre évaluation dans MEDACAP :',
        'kIntroOne' => 'Un de vos collaborateurs attend encore votre évaluation dans MEDACAP :',
        'kLogin' => 'Pour vous connecter : votre identifiant <b>%s</b> (ou votre adresse mail) et votre mot de passe habituel, ou <b>« Connectez-vous avec votre mail »</b> pour recevoir un code.',
        'kHelp' => 'En cas de difficulté, répondez simplement à ce mail.',
        'subject' => 'MEDACAP change d\'adresse : nouvelle adresse et évaluations en attente',
        'title' => 'MEDACAP change d\'adresse',
        'intro' => 'MEDACAP est désormais accessible à cette adresse, depuis un poste du réseau CFAO :',
        'old' => 'L\'ancienne adresse (' . OLD_ADDRESS . ') n\'est plus en service. Pensez à mettre à jour vos favoris.',
        'todo' => 'Ce qui reste à faire dans votre équipe',
        'todoIntro' => count($rows) . ' de vos collaborateurs attendent encore une action :',
        'todoOne' => 'Vous avez 1 collaborateur à évaluer :',
        'colName' => 'Collaborateur', 'colMgr' => 'Votre évaluation',
        'yes' => 'À évaluer', 'no' => 'Évalué',
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
        . '<th style="' . $td . '">' . h($L['colMgr']) . '</th></tr>';
    foreach ($rows as $r) {
        $table .= '<tr><td style="' . $td . '">' . h($r['name']) . '</td>'
            . '<td style="' . $td . '">' . $badge($r['mgr']) . '</td></tr>';
    }
    $table .= '</table>';
    if (count($rows) === 1) {
        // Un seul collaborateur : encadré plutôt qu'un tableau d'une ligne
        $r = reset($rows);
        $table = '<div style="margin:8px 0 12px;padding:12px 16px;background:#F4F6F8;border-left:4px solid #1F5FBF;font-size:15px;">'
            . '<strong>' . h($r['name']) . '</strong> &mdash; ' . $badge($r['mgr']) . '</div>';
        $L['todoIntro'] = $L['todoOne'];
    }

    $message = '<p>' . $L['intro'] . '</p>'
        . '<p style="margin:12px 0;font-size:20px;font-weight:700;"><a href="' . h($url) . '" style="color:#1F5FBF;">' . h($url) . '</a></p>'
        . '<p>' . $L['old'] . '</p>'
        . ($rows ? '<h3 style="margin:22px 0 6px;font-size:17px;">' . h($L['todo']) . '</h3>'
            . '<p>' . h($L['todoIntro']) . '</p>' . $table : '')
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
    if ($mode === 'rectif') {
        // Rectificatif : liste corrigée et adresse seulement
        $message = '<p>' . h($L['rIntro']) . '</p>' . $table
            . '<p style="margin-top:18px;">' . h($L['rLink']) . ' <a href="' . h($url) . '" style="color:#1F5FBF;font-weight:700;">' . h($url) . '</a></p>';
        $L['subject'] = $L['rSubject'];
        $L['title'] = $L['rTitle'];
    } elseif ($mode === 'relance') {
        // Relance : liste à jour, adresse et rappel court de la connexion
        $message = '<p>' . h(count($rows) === 1 ? $L['kIntroOne'] : $L['kIntro']) . '</p>' . $table
            . ($ownTests > 0 ? '<p>' . $L['own'] . '</p>' : '')
            . '<p style="margin-top:18px;">' . h($L['rLink']) . ' <a href="' . h($url) . '" style="color:#1F5FBF;font-weight:700;">' . h($url) . '</a></p>'
            . '<p>' . sprintf($L['kLogin'], h($manager['username'] ?? '')) . ' <a href="' . h($url . 'public/guide-connexion/' . ($isEn ? 'en' : 'fr') . '.html') . '" style="color:#1F5FBF;">' . h($L['guideLink']) . '</a></p>'
            . '<p>' . h($L['kHelp']) . '</p>';
        $L['subject'] = $L['kSubject'];
        $L['title'] = $L['kTitle'];
    }

    $html = generateEmailTemplate([
        'lang' => $isEn ? 'en' : 'fr',
        'companyName' => 'MEDACAP',
        'companyTagline' => 'CFAO Mobility Academy',
        'teamName' => TEAM_NAME,
        'supportEmail' => SUPER_ADMIN_EMAIL,
        'userName' => managerGreeting($manager, $isEn),
        'title' => $L['title'],
        'message' => $message,
        'ctaText' => $L['cta'],
        'ctaLink' => $url,
        'width' => CAMPAIGN_MAIL_WIDTH,
    ]);
    return [$L['subject'], $html];
}

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

/**
 * Relances : managers déjà servis qui ont encore des évaluations à faire.
 * Échéance = date (Douala) du dernier mail reçu + $jours ; envoi le premier jour ouvré à partir de l'échéance.
 */
function relancePlan($db, array $list, $jours, DateTimeImmutable $today)
{
    $plan = [];
    foreach ($list as $mid => $e) {
        if ($e['mgrCount'] === 0) {
            continue;
        }
        $history = campagneMailHistory($db, $e['m']['_id']);
        if (!$history) {
            continue;
        }
        $last = campagneLocalDate(end($history)['sentAt']);
        $due = DateTimeImmutable::createFromMutable($last)->setTime(0, 0)->modify("+$jours days");
        $run = $due > $today ? $due : $today;
        while ((int) $run->format('N') >= 6) {
            $run = $run->modify('+1 day');
        }
        $plan[$mid] = [
            'last' => $last, 'due' => $due, 'run' => $run, 'isDue' => $run == $today,
            'relances' => count(array_filter($history, function ($r) { return $r['campaign'] === CAMPAIGN . '-relance'; })),
        ];
    }
    return $plan;
}

$today = new DateTimeImmutable('today', new DateTimeZone(CAMPAIGN_TZ));
$plan = $relance ? relancePlan($db, $list, $jours, $today) : [];
if ($relance) {
    echo "\nRelances (J+$jours, jours ouvrés) au " . $today->format('d/m/Y') . " :\n";
    foreach ($plan as $mid => $p) {
        $m = $list[$mid]['m'];
        printf("  %-34s | dernier mail %s | relances %d | évals %2d | %s\n",
            trim(($m['firstName'] ?? '') . ' ' . ($m['lastName'] ?? '')), $p['last']->format('d/m H:i'), $p['relances'],
            $list[$mid]['mgrCount'], $p['isDue'] ? 'À RELANCER' : 'prévue le ' . $p['run']->format('d/m'));
    }
}

// Aperçu joint au test de relance : qui sera relancé, et quand
function relanceTestBox(array $plan, array $list, $jours)
{
    $days = ['', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.', 'dim.'];
    $td = 'padding:4px 8px;border-bottom:1px solid #E5E7EB;font-size:13px;';
    $rows = '';
    foreach ($plan as $mid => $p) {
        $m = $list[$mid]['m'];
        $rows .= '<tr><td style="' . $td . '">' . h(trim(($m['firstName'] ?? '') . ' ' . ($m['lastName'] ?? ''))) . '</td>'
            . '<td style="' . $td . '">' . $p['last']->format('d/m H:i') . '</td>'
            . '<td style="' . $td . '">' . $list[$mid]['mgrCount'] . '</td>'
            . '<td style="' . $td . '"><b>' . $days[(int) $p['run']->format('N')] . ' ' . $p['run']->format('d/m') . '</b></td></tr>';
    }
    return campagneTestBox('<b>[TEST] Relance automatique, à valider</b><br>'
        . 'Envoi chaque jour ouvré à 8 h, ' . $jours . ' jours après le dernier mail reçu, tant que le manager a des évaluations à faire '
        . '(copie : ' . h(implode(', ', CAMPAIGN_CC)) . '). Ci-dessous, le mail tel qu\'il sera reçu par un manager.'
        . '<table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin-top:8px;">'
        . '<tr style="text-align:left;background:#FEF3C7;"><th style="' . $td . '">Manager</th><th style="' . $td . '">Dernier mail</th>'
        . '<th style="' . $td . '">Évaluations</th><th style="' . $td . '">Relance prévue</th></tr>' . $rows . '</table>');
}

if ($testTo !== '') {
    $pick = null;
    $want = isset($opts['manager']) ? strtolower(trim((string) $opts['manager'])) : '';
    foreach ($list as $mid => $e) {
        if ($relance && !isset($plan[$mid])) {
            continue;
        }
        if ($want === '' ? ($pick === null || count($e['rows']) > count($pick['rows'])) : ($want === strtolower($mid) || $want === strtolower((string) ($e['m']['email'] ?? '')))) {
            $pick = $e;
        }
    }
    if ($pick === null) {
        fwrite(STDERR, "[ERREUR] Manager introuvable : $want\n");
        exit(1);
    }
    list($subject, $html) = buildCampaignMail($pick['m'], $pick['rows'], $url, $pick['own'], $mode);
    if ($relance) {
        $html = preg_replace('/<body[^>]*>/i', '$0' . relanceTestBox($plan, $list, $jours), $html, 1);
    }
    $ok = sendCampaignMail($testTo, '[TEST] ' . $subject, $html);
    echo "\nTest : mail de " . trim($pick['m']['firstName'] . ' ' . $pick['m']['lastName']) . " envoyé à $testTo : " . ($ok ? 'OK' : 'ÉCHEC') . "\n";
    exit($ok ? 0 : 1);
}

// Compteur de campagne : mails réels envoyés (rectificatifs compris) et techniciens couverts
function printCounter($db, array $list)
{
    $mails = $rectifs = $relances = $doneTechs = $doneManagers = $totalTechs = 0;
    foreach ($db->mail_campaigns->find(['campaign' => ['$in' => [CAMPAIGN, CAMPAIGN . '-rectif', CAMPAIGN . '-relance']], 'ok' => true]) as $r) {
        $mails++;
        $rectifs += $r['campaign'] === CAMPAIGN . '-rectif' ? 1 : 0;
        $relances += $r['campaign'] === CAMPAIGN . '-relance' ? 1 : 0;
    }
    foreach ($list as $e) {
        $totalTechs += count($e['rows']);
        if ($db->mail_campaigns->findOne(['campaign' => CAMPAIGN, 'userId' => $e['m']['_id'], 'ok' => true])) {
            $doneManagers++;
            $doneTechs += count($e['rows']);
        }
    }
    echo "\n=== COMPTEUR ===\n"
        . "Mails envoyés      : $mails (dont $rectifs rectificatif(s), $relances relance(s))\n"
        . "Managers servis    : $doneManagers / " . count($list) . "\n"
        . "Techniciens        : $doneTechs / $totalTechs\n";
}

if (!$apply) {
    printCounter($db, $list);
    echo "\n[SIMULATION] Aucun envoi. --test=<adresse> pour un essai, --apply pour envoyer.\n";
    exit(0);
}

if ($relance && (int) $today->format('N') >= 6) {
    echo "\nWeek-end : aucune relance.\n";
    exit(0);
}
$want = isset($opts['manager']) ? strtolower(trim((string) $opts['manager'])) : '';
$sent = $skipped = $failed = 0;
foreach ($list as $mid => $e) {
    $email = trim((string) ($e['m']['email'] ?? ''));
    if ($want !== '' && $want !== strtolower($mid) && $want !== strtolower($email)) {
        continue;
    }
    // Relance : seulement à échéance (la relance envoyée repousse la suivante de $jours) ; sinon une seule fois par manager
    if ($relance ? !(isset($plan[$mid]) && $plan[$mid]['isDue'])
        : $db->mail_campaigns->findOne(['campaign' => $campaign, 'userId' => $e['m']['_id'], 'ok' => true])) {
        $skipped++;
        continue;
    }
    list($subject, $html) = buildCampaignMail($e['m'], $e['rows'], $url, $e['own'], $mode);
    if ($email === '') {
        // Relais : l'objet indique à qui transmettre
        $subject = 'À transmettre à ' . managerGreeting($e['m'], false) . ' - ' . $subject;
        $email = RELAY_TO;
    }
    $ok = sendCampaignMail($email, $subject, $html, $cc);
    $db->mail_campaigns->insertOne([
        'campaign' => $campaign, 'userId' => $e['m']['_id'], 'email' => $email, 'cc' => $cc, 'ok' => $ok,
        'pendingTechs' => count($e['rows']), 'pendingEvals' => $e['mgrCount'], 'sentAt' => new UTCDateTime(),
    ]);
    $ok ? $sent++ : $failed++;
    echo ($ok ? '[OK]    ' : '[ÉCHEC] ') . $email . "\n";
}
echo "\nEnvoyés : $sent, " . ($relance ? 'pas encore à échéance' : 'déjà envoyés') . " : $skipped, échecs : $failed\n";
printCounter($db, $list);
exit($failed > 0 ? 1 : 0);
