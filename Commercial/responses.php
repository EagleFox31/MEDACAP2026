<?php
require_once __DIR__ . '/../core/medacap_config.php';
session_start();
require_once "../vendor/autoload.php";
require_once "../views/sendMail.php";

use MongoDB\Client;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

$allowedProfiles = ['Super Admin', 'Admin', 'Admin Commercial'];

// Charger les emails autorisés : SUPER_ADMIN + admins commerciaux en BD
$authorizedEmails = [strtolower(SUPER_ADMIN_EMAIL)];
try {
    $tmpAuthClient = new Client(medacapMongoUri());
    $commAdminCursor = $tmpAuthClient->academy->commercial_admins->find(['active' => true]);
    foreach ($commAdminCursor as $ca) {
        $caEmail = isset($ca['email']) ? strtolower(trim((string)$ca['email'])) : '';
        if ($caEmail !== '') $authorizedEmails[] = $caEmail;
    }
} catch (Exception $e) { /* garde les emails déjà chargés */ }

$isLoggedIn = isset($_SESSION['id']) && in_array($_SESSION['profile'] ?? '', $allowedProfiles);

function loginUserByEmail($email, $authorizedEmails) {
    $email = strtolower(trim($email));
    if (!in_array($email, $authorizedEmails)) return false;
    $tmpClient = new Client(medacapMongoUri());
    $db = $tmpClient->academy;

    // Super Admin : session directe sans passer par users
    if (strtolower($email) === strtolower(SUPER_ADMIN_EMAIL)) {
        $_SESSION['username']   = 'superadmin';
        $_SESSION['profile']    = 'Super Admin';
        $_SESSION['lastName']   = 'Admin';
        $_SESSION['firstName']  = 'Super';
        $_SESSION['email']      = $email;
        $_SESSION['test']       = '';
        $_SESSION['id']         = 'superadmin';
        $_SESSION['subsidiary'] = '';
        $_SESSION['agency']     = '';
        $_SESSION['department'] = '';
        $_SESSION['lang']       = 'FR';
        $_SESSION['country']    = 'CFAO';
        setcookie('cfao_admin_email', $email, time() + (365 * 24 * 3600), '/', '', false, true);
        return true;
    }

    // Chercher dans users (autres admins MEDACAP)
    $user = $db->users->findOne(['email' => $email]);
    if ($user) {
        $_SESSION['username']   = isset($user['username'])   ? (string)$user['username']   : '';
        $_SESSION['profile']    = isset($user['profile'])    ? (string)$user['profile']    : 'Super Admin';
        $_SESSION['lastName']   = isset($user['lastName'])   ? (string)$user['lastName']   : '';
        $_SESSION['firstName']  = isset($user['firstName'])  ? (string)$user['firstName']  : '';
        $_SESSION['email']      = (string)$user['email'];
        $_SESSION['test']       = isset($user['test'])       ? (string)$user['test']       : '';
        $_SESSION['id']         = (string)$user['_id'];
        $_SESSION['subsidiary'] = isset($user['subsidiary']) ? (string)$user['subsidiary'] : '';
        $_SESSION['agency']     = isset($user['agency'])     ? (string)$user['agency']     : '';
        $_SESSION['department'] = isset($user['department']) ? (string)$user['department'] : '';
        $_SESSION['lang']       = isset($user['lang'])       ? (string)$user['lang']       : 'FR';
        $_SESSION['country']    = isset($user['country'])    ? (string)$user['country']    : 'CFAO';
        setcookie('cfao_admin_email', $email, time() + (365 * 24 * 3600), '/', '', false, true);
        $db->commercial_admins->updateOne(['email' => $email], ['$set' => ['login_at' => date('d-m-Y H:i:s'), 'last_seen' => date('d-m-Y H:i:s')]], ['upsert' => false]);
        return true;
    }

    // Sinon chercher dans commercial_admins
    $commAdmin = $db->commercial_admins->findOne(['email' => $email, 'active' => true]);
    if ($commAdmin) {
        $_SESSION['username']   = '';
        $_SESSION['profile']    = 'Admin Commercial';
        $_SESSION['lastName']   = isset($commAdmin['lastName'])  ? (string)$commAdmin['lastName']  : '';
        $_SESSION['firstName']  = isset($commAdmin['firstName']) ? (string)$commAdmin['firstName'] : '';
        $_SESSION['email']      = $email;
        $_SESSION['test']       = '';
        $_SESSION['id']         = (string)$commAdmin['_id'];
        $_SESSION['subsidiary'] = '';
        $_SESSION['agency']     = '';
        $_SESSION['department'] = '';
        $_SESSION['lang']       = 'FR';
        $_SESSION['country']    = 'CFAO';
        setcookie('cfao_admin_email', $email, time() + (365 * 24 * 3600), '/', '', false, true);
        $db->commercial_admins->updateOne(['email' => $email, 'active' => true], ['$set' => ['login_at' => date('d-m-Y H:i:s'), 'last_seen' => date('d-m-Y H:i:s')]]);
        return true;
    }

    return false;
}

if (!$isLoggedIn) {
    $authError = null;

    // Auto-login via cookie
    if (isset($_COOKIE['cfao_admin_email'])) {
        if (loginUserByEmail($_COOKIE['cfao_admin_email'], $authorizedEmails)) {
            header("Location: responses.php");
            exit();
        }
    }

    // Login via formulaire
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auth_email'])) {
        if (loginUserByEmail($_POST['auth_email'], $authorizedEmails)) {
            header("Location: responses.php");
            exit();
        }
        $authError = "Email non autorisé ou introuvable.";
    }

    // KPI stats for login panel
    $kpiCountries = 0; $kpiEvals = 0; $kpiBrands = 0;
    try {
        $kpiClient    = new Client(medacapMongoUri());
        $kpiDb        = $kpiClient->academy;
        $kpiCountries = count($kpiDb->users->distinct('country', ['country' => ['$ne' => '']]));
        $kpiEvals     = (int)$kpiDb->results->count(['type' => ['$in' => ['Factuel', 'Declaratif']]])
                      + (int)$kpiDb->validations->count();
        $kpiBrands    = count($kpiDb->vehicles->distinct('brand', ['brand' => ['$ne' => '']]));
    } catch (Exception $e) { /* defaults remain 0 */ }

    $africaSvg = @file_get_contents(__DIR__ . '/africa.svg');
    if (!$africaSvg) {
        $africaSvg = '<svg viewBox="0 0 239 218" xmlns="http://www.w3.org/2000/svg"></svg>';
    }
    ?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" type="image/x-icon" href="../public/images/logos/cfao_academy.ico">
    <title>Accès Administration — CFAO Academy</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; font-family: 'Inter', -apple-system, sans-serif; }
        body { display: flex; min-height: 100vh; }

        /* ── Left panel ── */
        .panel-left {
            flex: 0 0 55%;
            min-width: 0;
            background: #080d1a url('../public/images/cfao_academy.jpg') center/cover no-repeat;
            display: flex;
            flex-direction: column;
            padding: 2.5rem 3rem;
            position: relative;
            overflow: hidden;
        }
        .lp-inner {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .lp-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 0.5rem;
            flex-shrink: 0;
        }
        .lp-logo-row {
            display: flex;
            align-items: center;
            gap: 0.9rem;
        }
        .lp-logo img {
            height: 40px;
            width: auto;
        }
        .lp-title {
            color: #fff;
            font-size: 1.25rem;
            font-weight: 800;
            line-height: 1.2;
            text-shadow: 0 2px 14px rgba(0,0,0,0.7);
        }
        .lp-sub {
            color: rgba(255,255,255,0.75);
            font-size: 0.7rem;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-bottom: 0.75rem;
            flex-shrink: 0;
            text-shadow: 0 1px 6px rgba(0,0,0,0.6);
        }
        .status-badge {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            background: rgba(34,197,94,0.10);
            border: 1px solid rgba(34,197,94,0.20);
            color: #4ade80;
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 0.28rem 0.7rem;
            border-radius: 20px;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .status-dot {
            width: 5px; height: 5px;
            background: #4ade80;
            border-radius: 50%;
            animation: blink 2s infinite;
        }
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50%       { opacity: 0.3; }
        }

        /* Map */
        .map-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 0;
            padding: 0.5rem 0;
        }
        .map-wrapper {
            position: relative;
            width: 95%;
            max-width: 540px;
        }
        .map-wrapper svg:first-child {
            width: 100%;
            height: auto;
            display: block;
        }
        .map-wrapper svg:first-child {
            overflow: visible;
        }
        .map-wrapper svg:first-child path {
            fill: rgba(0, 0, 0, 0.50);
            stroke: rgba(255,255,255,0.25);
            stroke-width: 0.5;
        }
        /* Pays réellement présents en base */
        .map-wrapper svg:first-child #CI,
        .map-wrapper svg:first-child #SN,
        .map-wrapper svg:first-child #CM,
        .map-wrapper svg:first-child #CG,
        .map-wrapper svg:first-child #GA,
        .map-wrapper svg:first-child #MG,
        .map-wrapper svg:first-child #CD,
        .map-wrapper svg:first-child #BJ,
        .map-wrapper svg:first-child #BF,
        .map-wrapper svg:first-child #ML,
        .map-wrapper svg:first-child #CF,
        .map-wrapper svg:first-child #RW {
            fill: rgba(215,0,6,0.70);
            stroke: rgba(215,0,6,1);
            stroke-width: 0.8;
            filter: drop-shadow(0 0 3px rgba(215,0,6,0.95));
        }
        .dots-overlay {
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            pointer-events: none;
        }

        /* Tooltip SVG */
        .map-tooltip {
            position: fixed;
            background: rgba(15,23,42,0.92);
            backdrop-filter: blur(8px);
            color: #fff;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.35rem 0.7rem;
            border-radius: 6px;
            border: 1px solid rgba(255,255,255,0.15);
            pointer-events: none;
            white-space: nowrap;
            display: none;
            z-index: 9999;
            box-shadow: 0 4px 16px rgba(0,0,0,0.4);
        }
        .map-tooltip.covered { border-color: rgba(215,0,6,0.6); color: #fca5a5; }

        /* KPI grid */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.6rem;
            margin-top: 1.25rem;
            margin-bottom: 0;
            flex-shrink: 0;
        }
        .kpi-card {
            background: rgba(255,255,255,0.18);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.55);
            border-radius: 14px;
            padding: 0.8rem 0.9rem;
            box-shadow: 0 8px 24px -8px rgba(0,0,0,0.3);
        }
        .kpi-val {
            color: #fff;
            font-size: 1.3rem;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 0.2rem;
            text-shadow: 0 1px 4px rgba(0,0,0,0.4);
        }
        .kpi-lbl {
            color: rgba(255,255,255,0.80);
            font-size: 0.62rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            text-shadow: 0 1px 4px rgba(0,0,0,0.3);
        }

        /* ── Right panel ── */
        .panel-right {
            flex: 1;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 3.5rem;
            border-left: 1px solid #e8ecf0;
        }
        .panel-right > * {
            width: 100%;
            max-width: 400px;
        }
        .logo-wrap { margin-bottom: 2.5rem; }
        .logo-wrap img { height: 56px; width: auto; }
        .form-header { margin-bottom: 2rem; }
        .form-header h1 {
            color: #0f172a;
            font-size: 2rem;
            font-weight: 800;
            line-height: 1.15;
            margin-bottom: 0.5rem;
        }
        .form-header h1 em { color: #D70006; font-style: italic; }
        .form-header p {
            color: #64748b;
            font-size: 0.875rem;
            line-height: 1.55;
        }
        label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.4rem;
            letter-spacing: 0.02em;
        }
        .field { margin-bottom: 1.25rem; }
        input[type=email] {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1.5px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.95rem;
            color: #0f172a;
            background: #fff;
            outline: none;
            transition: border-color .15s, box-shadow .15s;
            font-family: inherit;
        }
        input[type=email]::placeholder { color: #9ca3af; }
        input[type=email]:focus {
            border-color: #0f172a;
            box-shadow: 0 0 0 3px rgba(15,23,42,0.08);
        }
        .btn {
            width: 100%;
            padding: 0.85rem;
            background: #0f172a;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: background .15s;
            font-family: inherit;
            margin-top: 0.25rem;
        }
        .btn:hover  { background: #1e293b; }
        .btn:active { background: #0a1020; }
        .error {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            font-size: 0.82rem;
            padding: 0.65rem 0.9rem;
            border-radius: 8px;
            margin-bottom: 1.25rem;
        }
        .footer-note {
            margin-top: 2.5rem;
            color: #9ca3af;
            font-size: 0.75rem;
            border-top: 1px solid #f1f5f9;
            padding-top: 1.5rem;
        }
        @media (max-width: 820px) {
            .panel-left  { display: none; }
            .panel-right { width: 100%; padding: 2rem 1.5rem; }
        }
    </style>
</head>
<body>

<div class="panel-left">
    <div class="lp-inner">

        <div class="lp-header">
            <div class="lp-logo-row">
                <div class="lp-logo"><img src="../public/images/logo-cfao.png" alt="CFAO"></div>
                <div class="lp-title">Présent dans toute l'Afrique</div>
            </div>
            <span class="status-badge">
                <span class="status-dot"></span>Système opérationnel
            </span>
        </div>

        <div class="lp-sub">Évaluation commerciale &bull; CFAO Academy</div>

        <div class="map-container">
            <div class="map-wrapper">
                <?php echo $africaSvg; ?>
                <svg class="dots-overlay" viewBox="0 0 239 218" xmlns="http://www.w3.org/2000/svg">
                    <!-- SN Sénégal --><g><circle cx="32" cy="70" r="2.2" fill="#ff4444"/><circle cx="32" cy="70" r="2.2" fill="none" stroke="#ff4444" stroke-width="1.5"><animate attributeName="r" from="2.2" to="7" dur="2.5s" begin="0s" repeatCount="indefinite"/><animate attributeName="opacity" from="0.8" to="0" dur="2.5s" begin="0s" repeatCount="indefinite"/></circle></g>
                    <!-- ML Mali --><g><circle cx="60" cy="61" r="2.2" fill="#ff4444"/><circle cx="60" cy="61" r="2.2" fill="none" stroke="#ff4444" stroke-width="1.5"><animate attributeName="r" from="2.2" to="7" dur="2.5s" begin="0.4s" repeatCount="indefinite"/><animate attributeName="opacity" from="0.8" to="0" dur="2.5s" begin="0.4s" repeatCount="indefinite"/></circle></g>
                    <!-- BF Burkina --><g><circle cx="66" cy="76" r="2.2" fill="#ff4444"/><circle cx="66" cy="76" r="2.2" fill="none" stroke="#ff4444" stroke-width="1.5"><animate attributeName="r" from="2.2" to="7" dur="2.5s" begin="0.8s" repeatCount="indefinite"/><animate attributeName="opacity" from="0.8" to="0" dur="2.5s" begin="0.8s" repeatCount="indefinite"/></circle></g>
                    <!-- CI Côte d'Ivoire --><g><circle cx="53" cy="91" r="2.2" fill="#ff4444"/><circle cx="53" cy="91" r="2.2" fill="none" stroke="#ff4444" stroke-width="1.5"><animate attributeName="r" from="2.2" to="7" dur="2.5s" begin="0.2s" repeatCount="indefinite"/><animate attributeName="opacity" from="0.8" to="0" dur="2.5s" begin="0.2s" repeatCount="indefinite"/></circle></g>
                    <!-- BJ Bénin --><g><circle cx="78" cy="84" r="2.2" fill="#ff4444"/><circle cx="78" cy="84" r="2.2" fill="none" stroke="#ff4444" stroke-width="1.5"><animate attributeName="r" from="2.2" to="7" dur="2.5s" begin="1.0s" repeatCount="indefinite"/><animate attributeName="opacity" from="0.8" to="0" dur="2.5s" begin="1.0s" repeatCount="indefinite"/></circle></g>
                    <!-- CM Cameroun --><g><circle cx="105" cy="97" r="2.2" fill="#ff4444"/><circle cx="105" cy="97" r="2.2" fill="none" stroke="#ff4444" stroke-width="1.5"><animate attributeName="r" from="2.2" to="7" dur="2.5s" begin="0.6s" repeatCount="indefinite"/><animate attributeName="opacity" from="0.8" to="0" dur="2.5s" begin="0.6s" repeatCount="indefinite"/></circle></g>
                    <!-- CF RCA --><g><circle cx="130" cy="91" r="2.2" fill="#ff4444"/><circle cx="130" cy="91" r="2.2" fill="none" stroke="#ff4444" stroke-width="1.5"><animate attributeName="r" from="2.2" to="7" dur="2.5s" begin="1.3s" repeatCount="indefinite"/><animate attributeName="opacity" from="0.8" to="0" dur="2.5s" begin="1.3s" repeatCount="indefinite"/></circle></g>
                    <!-- GA Gabon --><g><circle cx="104" cy="115" r="2.2" fill="#ff4444"/><circle cx="104" cy="115" r="2.2" fill="none" stroke="#ff4444" stroke-width="1.5"><animate attributeName="r" from="2.2" to="7" dur="2.5s" begin="0.9s" repeatCount="indefinite"/><animate attributeName="opacity" from="0.8" to="0" dur="2.5s" begin="0.9s" repeatCount="indefinite"/></circle></g>
                    <!-- CG Congo --><g><circle cx="115" cy="117" r="2.2" fill="#ff4444"/><circle cx="115" cy="117" r="2.2" fill="none" stroke="#ff4444" stroke-width="1.5"><animate attributeName="r" from="2.2" to="7" dur="2.5s" begin="1.5s" repeatCount="indefinite"/><animate attributeName="opacity" from="0.8" to="0" dur="2.5s" begin="1.5s" repeatCount="indefinite"/></circle></g>
                    <!-- CD RDC --><g><circle cx="138" cy="125" r="2.2" fill="#ff4444"/><circle cx="138" cy="125" r="2.2" fill="none" stroke="#ff4444" stroke-width="1.5"><animate attributeName="r" from="2.2" to="7" dur="2.5s" begin="0.3s" repeatCount="indefinite"/><animate attributeName="opacity" from="0.8" to="0" dur="2.5s" begin="0.3s" repeatCount="indefinite"/></circle></g>
                    <!-- RW Rwanda --><g><circle cx="155" cy="118" r="2.2" fill="#ff4444"/><circle cx="155" cy="118" r="2.2" fill="none" stroke="#ff4444" stroke-width="1.5"><animate attributeName="r" from="2.2" to="7" dur="2.5s" begin="1.7s" repeatCount="indefinite"/><animate attributeName="opacity" from="0.8" to="0" dur="2.5s" begin="1.7s" repeatCount="indefinite"/></circle></g>
                    <!-- MG Madagascar --><g><circle cx="203" cy="169" r="2.2" fill="#ff4444"/><circle cx="203" cy="169" r="2.2" fill="none" stroke="#ff4444" stroke-width="1.5"><animate attributeName="r" from="2.2" to="7" dur="2.5s" begin="1.1s" repeatCount="indefinite"/><animate attributeName="opacity" from="0.8" to="0" dur="2.5s" begin="1.1s" repeatCount="indefinite"/></circle></g>
                </svg>
            </div>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-val"><?php echo $kpiCountries > 0 ? $kpiCountries : '—'; ?></div>
                <div class="kpi-lbl">Pays couverts</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-val"><?php echo $kpiEvals > 0 ? number_format($kpiEvals, 0, ',', ' ') : '—'; ?></div>
                <div class="kpi-lbl">Évaluations traitées</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-val"><?php echo $kpiBrands > 0 ? $kpiBrands : '—'; ?></div>
                <div class="kpi-lbl">Marques distribuées</div>
            </div>
        </div>

    </div>
</div>

<div class="panel-right">
    <div class="logo-wrap">
        <img src="../public/images/logo-cfao.png" alt="CFAO Mobility">
    </div>

    <div class="form-header">
        <h1>Accès <em>privé</em><br>aux résultats.</h1>
        <p>Saisissez votre adresse email professionnelle pour accéder aux réponses commerciales.</p>
    </div>

    <?php if ($authError): ?>
        <div class="error">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?php echo htmlspecialchars($authError); ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="field">
            <label for="auth_email">Adresse email</label>
            <input type="email" id="auth_email" name="auth_email" placeholder="vous@cfao.com" required autofocus>
        </div>
        <button type="submit" class="btn">Accéder aux résultats &rarr;</button>
    </form>

    <p class="footer-note">CFAO Mobility Academy &mdash; <?php echo date('Y'); ?></p>
</div>

<div class="map-tooltip" id="mapTooltip"></div>
<script>
(function() {
    var names = {
        'AE':'Émirats arabes unis','AO':'Angola','BF':'Burkina Faso','BH':'Bahreïn',
        'BI':'Burundi','BJ':'Bénin','BW':'Botswana','CD':'RD Congo','CF':'Rép. Centrafricaine',
        'CG':'Congo','CI':"Côte d'Ivoire",'CM':'Cameroun','CV':'Cap-Vert','DJ':'Djibouti',
        'DZ':'Algérie','EG':'Égypte','EH':'Sahara occidental','ER':'Érythrée','ET':'Éthiopie',
        'GA':'Gabon','GH':'Ghana','GI':'Gibraltar','GM':'Gambie','GN':'Guinée',
        'GQ':'Guinée équatoriale','GW':'Guinée-Bissau','IL':'Israël','IQ':'Irak',
        'JO':'Jordanie','KE':'Kenya','KM':'Comores','KW':'Koweït','LB':'Liban',
        'LR':'Libéria','LS':'Lesotho','LY':'Libye','MA':'Maroc','MG':'Madagascar',
        'ML':'Mali','MR':'Mauritanie','MU':'Maurice','MW':'Malawi','MZ':'Mozambique',
        'NA':'Namibie','NE':'Niger','NG':'Nigéria','OM':'Oman','PS':'Palestine',
        'QA':'Qatar','RE':'La Réunion','RW':'Rwanda','SA':'Arabie saoudite',
        'SC':'Seychelles','SD':'Soudan','SH':'Sainte-Hélène','SL':'Sierra Leone',
        'SN':'Sénégal','SO':'Somalie','SS':'Soudan du Sud','ST':'São Tomé','SY':'Syrie',
        'SZ':'Eswatini','TD':'Tchad','TG':'Togo','TN':'Tunisie','TZ':'Tanzanie',
        'UG':'Ouganda','YE':'Yémen','YT':'Mayotte','ZA':'Afrique du Sud','ZM':'Zambie','ZW':'Zimbabwe'
    };
    var covered = {'CI':1,'SN':1,'CM':1,'CG':1,'GA':1,'MG':1,'CD':1,'BJ':1,'BF':1,'ML':1,'CF':1,'RW':1};
    var tip = document.getElementById('mapTooltip');

    var svgEl = document.querySelector('.map-wrapper svg:first-child');
    if (!svgEl) return;

    svgEl.addEventListener('mousemove', function(e) {
        var path = e.target.closest('path[id]');
        if (!path) { tip.style.display = 'none'; return; }
        var id = path.id;
        var label = names[id] || id;
        if (covered[id]) {
            label = '✦ ' + label;
            tip.className = 'map-tooltip covered';
        } else {
            tip.className = 'map-tooltip';
        }
        tip.textContent = label;
        tip.style.display = 'block';
        tip.style.left = (e.clientX + 14) + 'px';
        tip.style.top  = (e.clientY - 28) + 'px';
    });

    svgEl.addEventListener('mouseleave', function() { tip.style.display = 'none'; });

    document.addEventListener('mousemove', function(e) {
        if (tip.style.display === 'none') return;
        tip.style.left = (e.clientX + 14) + 'px';
        tip.style.top  = (e.clientY - 28) + 'px';
    });
})();
</script>
</body>
</html><?php
    exit();
}
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'update_candidate') {
    header('Content-Type: application/json; charset=utf-8');
    if (strtolower($_SESSION['email'] ?? '') !== strtolower(SUPER_ADMIN_EMAIL)) {
        echo json_encode(['ok' => false, 'error' => 'Accès refusé']);
        exit();
    }
    $candId = trim($_POST['id'] ?? '');
    try {
        $objId = new MongoDB\BSON\ObjectId($candId);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => 'ID invalide']);
        exit();
    }
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName  = trim($_POST['lastName']  ?? '');
    $country   = trim($_POST['country']   ?? '');
    $medacapCountries = ['Burkina Faso', 'Cameroun', "Cote d'Ivoire", 'Gabon', 'Madagascar', 'Mali', 'RCA', 'RDC', 'Senegal', 'Congo', 'Rwanda'];
    if ($firstName === '' || $lastName === '') {
        echo json_encode(['ok' => false, 'error' => 'Prénom et nom requis']);
        exit();
    }
    if (!in_array($country, $medacapCountries)) {
        echo json_encode(['ok' => false, 'error' => 'Pays invalide']);
        exit();
    }
    $tmpClient = new MongoDB\Client(medacapMongoUri());
    $tmpDb = $tmpClient->academy;
    $tmpDb->commercial_responses->updateOne(
        ['_id' => $objId],
        ['$set' => ['firstName' => $firstName, 'lastName' => $lastName, 'country' => $country]]
    );
    echo json_encode(['ok' => true, 'fullName' => trim($firstName . ' ' . $lastName)]);
    exit();
}
if (isset($_GET['ajax_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    $candId = trim($_GET['ajax_id']);
    try {
        $objId = new MongoDB\BSON\ObjectId($candId);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => 'ID invalide']);
        exit();
    }

    $client = new MongoDB\Client(medacapMongoUri());
    $db = $client->academy;
    
    $row = $db->commercial_responses->findOne(['_id' => $objId]);
    if (!$row) {
        echo json_encode(['ok' => false, 'error' => 'Candidat introuvable']);
        exit();
    }

    $corr = $db->commercial_corrections->findOne(['response_id' => $candId]);

    // Format Candidate metadata
    $firstName  = (string)($row['firstName']  ?? '');
    $lastName   = (string)($row['lastName']   ?? '');
    $fullName   = trim($firstName . ' ' . $lastName) ?: 'Candidat';
    $email      = (string)($row['email']      ?? '—');
    $country    = (string)($row['country']    ?? '—');
    $brand      = (string)($row['brand']      ?? '—');
    $qType      = (string)($row['questionnaire_type'] ?? 'sinotruk');
    $submittedAt= (string)($row['submitted_at'] ?? '—');
    $violations = (int)($row['violations']    ?? 0);
    $totalTime  = (int)($row['total_time_seconds'] ?? 0);
    $timeStr    = $totalTime > 0 ? floor($totalTime / 60) . 'min ' . ($totalTime % 60) . 's' : '—';
    $isBanned   = !empty($row['is_banned']);

    $corrStatus = $corr['status'] ?? 'pending';
    $scoreCorrect = (int)($corr['score']['correct'] ?? 0);
    $scorePartiel = (int)($corr['score']['partiel'] ?? 0);
    $scoreTotal   = (int)($corr['score']['total'] ?? 0);

    // Load questionnaire sections & answers
    $jsonFile = __DIR__ . '/' . ($qType === 'sinotruk' ? 'questionnaire_sinotruk.json' : 'questionnaire_bus_et_chariot.json');
    $sections = [];
    $totalQuestionsCount = 0;
    if (file_exists($jsonFile)) {
        $raw = json_decode(file_get_contents($jsonFile), true);
        $answers = (array)($row['answers'] ?? []);
        $grades  = (array)($corr['grades'] ?? []);
        foreach ((array)($raw['sections'] ?? []) as $sec) {
            $secQuestions = [];
            foreach ((array)($sec['questions'] ?? []) as $q) {
                $qid = $q['id'];
                $ans = $answers[$qid] ?? '';
                $grade = isset($grades[$qid]) ? (array)$grades[$qid] : [];
                $secQuestions[] = [
                    'id'       => $qid,
                    'question' => $q['text_FR'],
                    'answer'   => $ans,
                    'verdict'  => $grade['verdict'] ?? '',
                    'comment'  => $grade['comment'] ?? '',
                ];
                $totalQuestionsCount++;
            }
            $sections[] = [
                'title' => $sec['section_FR'],
                'questions' => $secQuestions,
            ];
        }
    }

    echo json_encode([
        'ok'          => true,
        'isSuperAdmin' => strtolower($_SESSION['email'] ?? '') === strtolower(SUPER_ADMIN_EMAIL),
        'candidate' => [
            'id'          => $candId,
            'fullName'    => $fullName,
            'firstName'   => $firstName,
            'lastName'    => $lastName,
            'email'       => $email,
            'country'     => $country,
            'brand'       => $brand,
            'qType'       => $qType,
            'submittedAt' => $submittedAt,
            'violations'  => $violations,
            'timeStr'     => $timeStr,
            'isBanned'    => $isBanned,
            'corrStatus'  => $corrStatus,
            'score'       => [
                'correct' => $scoreCorrect,
                'partiel' => $scorePartiel,
                'total'   => $scoreTotal,
                'max'     => $totalQuestionsCount,
            ]
        ],
        'sections' => $sections,
    ]);
    exit();
}

$lang = $_SESSION['lang'] ?? 'FR';
if ($lang === 'EN') {
    include_once "language_EN.php";
} else {
    include_once "language.php";
}

$client      = new Client(medacapMongoUri());
$db          = $client->academy;
$responses   = $db->commercial_responses;

// ── Filtres ──────────────────────────────────────────────────────────────
$filterBrand = isset($_GET['brand']) && in_array($_GET['brand'], ['SINOTRUK', 'KING LONG']) ? $_GET['brand'] : '';

// Date : accepte YYYY-MM-DD (natif navigateur) et DD-MM-YYYY (format stocké)
$filterDate = '';
if (isset($_GET['date']) && $_GET['date'] !== '') {
    $d = trim($_GET['date']);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
        // YYYY-MM-DD → DD-MM-YYYY
        $p = explode('-', $d);
        $d = $p[2] . '-' . $p[1] . '-' . $p[0];
    }
    if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $d)) {
        $filterDate = $d;
    }
}

$mongoFilter = [];
if ($filterBrand !== '') {
    $mongoFilter['brand'] = $filterBrand;
}
if ($filterDate !== '') {
    $mongoFilter['submitted_at'] = ['$regex' => '^' . preg_quote($filterDate, '/')];
}

$cursor = $responses->find($mongoFilter, ['sort' => ['submitted_at' => -1]]);
$rows   = iterator_to_array($cursor);

// ── Correction status map ─────────────────────────────────────────────────
$responseIds = [];
foreach ($rows as $row) {
    $responseIds[] = (string)$row['_id'];
}
$corrMap = [];
if (!empty($responseIds)) {
    $corrCursor = $db->commercial_corrections->find(['response_id' => ['$in' => $responseIds]]);
    foreach ($corrCursor as $c) {
        $rid = $c['response_id'] ?? '';
        if ($rid !== '') {
            $scoreArr = (array)($c['score'] ?? []);
            $corrMap[$rid] = [
                'status'        => $c['status'] ?? 'pending',
                'correct'       => (int)($scoreArr['correct']  ?? 0),
                'partiel'       => (int)($scoreArr['partiel']  ?? 0),
                'total'         => (int)($scoreArr['total']    ?? 0),
                'history_count' => count((array)($c['history'] ?? [])),
            ];
        }
    }
}

// ── Export Excel ──────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'excel') {

    // Charge les questions depuis un JSON (cache par type)
    $qCache = [];
    $loadQuestions = function($qType) use (&$qCache) {
        if (isset($qCache[$qType])) return $qCache[$qType];
        $file = __DIR__ . '/' . ($qType === 'sinotruk' ? 'questionnaire_sinotruk.json' : 'questionnaire_bus_et_chariot.json');
        if (!file_exists($file)) return [];
        $raw = json_decode(file_get_contents($file), true);
        $out = [];
        foreach ((array)($raw['sections'] ?? []) as $sec) {
            foreach ((array)($sec['questions'] ?? []) as $q) {
                $out[$q['id']] = $q['text_FR'];
            }
        }
        $qCache[$qType] = $out;
        return $out;
    };

    $spreadsheet = new Spreadsheet();
    $spreadsheet->removeSheetByIndex(0);

    $styleInfoLabel = [
        'font' => ['bold' => true, 'color' => ['rgb' => '0B3C6E']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EFF6FF']],
    ];
    $styleQHeader = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0B3C6E']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'wrapText' => true],
    ];
    $styleRowAlt = [
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']],
    ];

    foreach ($rows as $i => $row) {
        $qType      = (string)($row['questionnaire_type'] ?? 'sinotruk');
        $firstName  = (string)($row['firstName']  ?? '');
        $lastName   = (string)($row['lastName']   ?? '');
        $fullName   = trim($firstName . ' ' . $lastName);
        $email      = (string)($row['email']      ?? '');
        $country    = (string)($row['country']    ?? '');
        $brand      = (string)($row['brand']      ?? '');
        $submAt     = (string)($row['submitted_at'] ?? '');
        $violations = (int)($row['violations']    ?? 0);
        $totalTime  = (int)($row['total_time_seconds'] ?? 0);
        $timeStr    = $totalTime > 0 ? round($totalTime / 60, 1) . ' min' : '—';
        $isBanned   = (!empty($row['is_banned'])) ? 'Oui' : 'Non';
        $answers    = (array)($row['answers']     ?? []);

        // Correction info
        $rid       = (string)$row['_id'];
        $corrInfo  = isset($corrMap[$rid]) ? $corrMap[$rid] : ['status' => 'pending', 'correct' => 0, 'total' => 0];
        $corrLabels = ['pending' => 'En attente', 'in_progress' => 'En cours', 'completed' => 'Terminée'];
        $corrLabel = isset($corrLabels[$corrInfo['status']]) ? $corrLabels[$corrInfo['status']] : $corrInfo['status'];

        // Sheet name: 31 chars max, no forbidden chars
        $rawName = $fullName ?: ('Candidat_' . ($i + 1));
        $sheetName = mb_substr(preg_replace('/[\\\\\/?*\[\]:]+/', '_', $rawName), 0, 31, 'UTF-8');

        // Deduplicate sheet names
        $baseName = $sheetName;
        $suffix   = 2;
        while ($spreadsheet->sheetNameExists($sheetName)) {
            $sheetName = mb_substr($baseName, 0, 28, 'UTF-8') . '_' . $suffix;
            $suffix++;
        }

        $sheet = new Worksheet($spreadsheet, $sheetName);
        $spreadsheet->addSheet($sheet);

        // ── Section 1 : infos candidat ────────────────────────────────────
        $infoData = [
            ['Nom complet',          $fullName ?: '—'],
            ['Email',                $email],
            ['Pays',                 $country],
            ['Marque',               $brand],
            ['Type questionnaire',   $qType],
            ['Date soumission',      $submAt],
            ['Violations',           $violations],
            ['Temps',                $timeStr],
            ['Session bannie',       $isBanned],
            ['Statut correction',    $corrLabel],
        ];
        if ($corrInfo['total'] > 0) {
            $infoData[] = ['Score correction', $corrInfo['correct'] . ' / ' . $corrInfo['total'] . ' correct(s)'];
        }

        $r = 1;
        foreach ($infoData as $pair) {
            $sheet->setCellValue('A' . $r, $pair[0]);
            $sheet->setCellValue('B' . $r, $pair[1]);
            $sheet->getStyle('A' . $r)->applyFromArray($styleInfoLabel);
            $sheet->getColumnDimension('A')->setWidth(26);
            $r++;
        }

        $r++; // ligne vide

        // ── Section 2 : questions / réponses ─────────────────────────────
        $sheet->setCellValue('A' . $r, 'Question');
        $sheet->setCellValue('B' . $r, 'Réponse du candidat');
        $sheet->getStyle('A' . $r . ':B' . $r)->applyFromArray($styleQHeader);
        $sheet->getRowDimension($r)->setRowHeight(22);
        $r++;

        $questions = $loadQuestions($qType);
        $qNum = 0;
        foreach ($questions as $qid => $qText) {
            $answer = isset($answers[$qid]) ? (string)$answers[$qid] : '';
            $sheet->setCellValue('A' . $r, $qText);
            $sheet->setCellValue('B' . $r, $answer);
            $sheet->getStyle('A' . $r)->getAlignment()->setWrapText(true);
            $sheet->getStyle('B' . $r)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
            $sheet->getStyle('A' . $r)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
            if ($qNum % 2 === 0) {
                $sheet->getStyle('A' . $r . ':B' . $r)->applyFromArray($styleRowAlt);
            }
            // Hauteur de ligne adaptée à la longueur de la réponse
            $lines = max(1, ceil(mb_strlen($answer, 'UTF-8') / 80));
            $sheet->getRowDimension($r)->setRowHeight(max(18, $lines * 15));
            $r++;
            $qNum++;
        }

        // Largeurs colonnes
        $sheet->getColumnDimension('A')->setWidth(60);
        $sheet->getColumnDimension('B')->setWidth(80);
    }

    if ($spreadsheet->getSheetCount() === 0) {
        $empty = new Worksheet($spreadsheet, 'Aucune donnée');
        $spreadsheet->addSheet($empty);
        $empty->setCellValue('A1', 'Aucune réponse trouvée.');
    }

    $spreadsheet->setActiveSheetIndex(0);

    $filename = 'reponses_commerciales_' . date('Ymd_His') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit();
}

$_isSuperAdmin = strtolower($_SESSION['email'] ?? '') === strtolower(SUPER_ADMIN_EMAIL);
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" type="image/x-icon" href="../public/images/logos/cfao_academy.ico">
    <title>Suivi des Évaluations Techniques — CFAO Academy</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; }
        body { font-family: 'Inter', sans-serif; min-height: 100vh; color: #0f172a; display: flex; flex-direction: column; }
        body::before {
            content: '';
            position: fixed; inset: 0; z-index: -1;
            background: url('../public/images/cfao_academy.jpg') center/cover no-repeat;
        }
        .me-1 { margin-right: 0.25rem !important; }
        .me-2 { margin-right: 0.5rem !important; }
        .topbar {
            background: rgba(255,255,255,0.55);
            backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(255,255,255,0.6);
            padding: 0.85rem 2rem;
            display: flex; align-items: center; justify-content: space-between;
            box-shadow: 0 2px 16px rgba(0,0,0,0.08);
            position: fixed; top: 0; left: 0; right: 0; z-index: 200;
        }
        .topbar-left { display: flex; align-items: center; gap: 1rem; }
        .topbar-logo { background: rgba(255,255,255,0.8); border-radius: 6px; padding: 3px 8px; display: flex; align-items: center; }
        .topbar-logo img { height: 26px; width: auto; display: block; }
        .topbar-sep { width: 1px; height: 22px; background: rgba(0,0,0,0.12); flex-shrink: 0; }
        .topbar-title { color: #0b1a2e; font-size: 0.88rem; font-weight: 700; }
        .topbar-sub { color: rgba(0,0,0,0.4); font-size: 0.68rem; margin-top: 1px; }
        .topbar-right { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; }
        .topbar-user { color: rgba(0,0,0,0.5); font-size: 0.75rem; white-space: nowrap; }
        .topbar-btn {
            color: rgba(0,0,0,0.65); font-size: 0.75rem; font-weight: 500;
            text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem;
            padding: 0.32rem 0.75rem; border-radius: 6px;
            border: 1px solid rgba(0,0,0,0.12); transition: all .15s;
            background: rgba(255,255,255,0.5); cursor: pointer; font-family: inherit;
        }
        .topbar-btn:hover { background: rgba(255,255,255,0.85); color: #0b1a2e; border-color: rgba(0,0,0,0.2); }
        .topbar-btn.accent { background: #D70006; border-color: #D70006; color: #fff; }
        .topbar-btn.accent:hover { background: #b50005; border-color: #b50005; color: #fff; }
        .title-card {
            background: rgba(255,255,255,.92); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            border-radius: 16px; padding: 1.4rem 1.8rem;
            box-shadow: 0 4px 20px rgba(0,0,0,.12); margin-bottom: 1.5rem;
            display: flex; align-items: center; gap: 1rem;
        }
        .title-card-brand {
            flex-shrink: 0; height: 36px; width: 90px; object-fit: contain;
        }
        .title-card-center {
            flex: 1; text-align: center;
        }
        .title-card-h1 { font-size: 1.4rem; font-weight: 800; color: #0b3c6e; margin-bottom: 0.25rem; }
        .title-card-p { color: #94a3b8; font-size: 0.82rem; }
        .page-footer {
            position: fixed; bottom: 0; left: 0; right: 0; z-index: 10;
            background: rgba(255,255,255,0.55);
            backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
            border-top: 1px solid rgba(255,255,255,0.6);
            box-shadow: 0 -2px 16px rgba(0,0,0,0.06);
            padding: 0.6rem 2rem; text-align: center; color: rgba(0,0,0,0.4); font-size: 0.75rem;
        }
    </style>
</head>
<body>

<div class="topbar">
    <div class="topbar-left">
        <div class="topbar-logo"><img src="../public/images/logo-cfao.png" alt="CFAO"></div>
        <div class="topbar-sep"></div>
        <div>
            <div class="topbar-title">Suivi des Évaluations Techniques</div>
            <div class="topbar-sub">CFAO Mobility Academy</div>
        </div>
    </div>
    <div class="topbar-right">
        <?php if ($_isSuperAdmin): ?>
        <a href="online-users.php" class="topbar-btn">
            <i class="fas fa-circle" style="color:#22c55e;font-size:.5rem;"></i> En ligne
        </a>
        <a href="correctors.php" class="topbar-btn">
            <i class="fas fa-users-cog me-1"></i>Correcteurs
        </a>
        <a href="mail-manager.php" class="topbar-btn accent">
            <i class="fas fa-envelope me-1"></i>Mails
        </a>
        <?php endif; ?>
        <span class="topbar-user">
            <i class="fas fa-user-circle" style="margin-right:.3rem;color:rgba(255,255,255,0.4)"></i><?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>
        </span>
        <a href="logout.php" class="topbar-btn">
            <i class="fas fa-sign-out-alt"></i> Déconnexion
        </a>
    </div>
</div>

<?php
?>

<style>
    .responses-container { max-width: 1400px; width: 100%; margin: 0 auto; padding: 5.5rem 1.5rem 4rem; }

    .page-title {
        font-size: 1.6rem;
        font-weight: 800;
        color: #0b3c6e;
        margin-bottom: .3rem;
    }
    .page-subtitle { color: #888; font-size: .9rem; margin-bottom: 2rem; }

    /* Stats cards */
    .stats-row { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 2rem; }
    .stat-card {
        flex: 1 1 160px;
        background: rgba(255,255,255,.9);
        backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
        border-radius: 14px;
        padding: 1.2rem 1.5rem;
        box-shadow: 0 2px 12px rgba(0,0,0,.07);
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    .stat-icon {
        width: 46px; height: 46px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.3rem;
    }
    .stat-icon.blue  { background: rgba(11,60,110,.1);  color: #0b3c6e; }
    .stat-icon.red   { background: rgba(215,0,6,.1);    color: #d70006; }
    .stat-icon.green { background: rgba(25,135,84,.1);  color: #198754; }
    .stat-icon.orange{ background: rgba(255,140,0,.1);  color: #ff8c00; }
    .stat-value { font-size: 1.7rem; font-weight: 800; color: #222; line-height: 1; }
    .stat-label { font-size: .78rem; color: #888; margin-top: .15rem; }
    .stat-icon.purple { background: rgba(99,102,241,.1); color: #6366f1; }

    /* Sortable table */
    th.sortable { cursor: pointer; user-select: none; white-space: nowrap; }
    th.sortable:hover { background: rgba(255,255,255,.08); }
    th.sortable .sort-icon { margin-left: .35rem; opacity: .4; font-size: .7rem; }
    th.sortable.asc  .sort-icon::after { content: '▲'; opacity: 1; }
    th.sortable.desc .sort-icon::after { content: '▼'; opacity: 1; }
    th.sortable:not(.asc):not(.desc) .sort-icon::after { content: '⇅'; }

    /* Filters */
    /* Compact table toolbar */
    .table-toolbar {
        display: flex; align-items: center; gap: .75rem; flex-wrap: wrap;
        padding: .75rem 1.2rem;
        border-bottom: 1px solid #f0f3f7;
        background: #fafbfc;
        border-radius: 14px 14px 0 0;
    }
    .brand-filters { display: flex; align-items: center; gap: .4rem; }
    .brand-btn {
        display: inline-flex; align-items: center; gap: .35rem;
        padding: .3rem .75rem; border-radius: 20px;
        border: 1.5px solid #e2e8f0; background: #fff;
        font-size: .75rem; font-weight: 600; color: #64748b;
        cursor: pointer; transition: all .15s; white-space: nowrap;
    }
    .brand-btn img { height: 16px; width: auto; object-fit: contain; }
    .brand-btn:hover { border-color: #0b3c6e; color: #0b3c6e; }
    .brand-btn.active { border-color: #0b3c6e; background: #fff; color: #0b3c6e; box-shadow: 0 2px 8px rgba(11,60,110,.15); }
    .brand-btn.active img { filter: none; }
    .toolbar-sep { width: 1px; height: 20px; background: #e2e8f0; flex-shrink: 0; }
    .date-filter-wrap { display: flex; align-items: center; gap: .4rem; }
    .date-filter-wrap label { font-size: .78rem; font-weight: 600; color: #64748b; white-space: nowrap; }
    .date-filter-wrap input[type=date] {
        border: 1.5px solid #e2e8f0; border-radius: 8px;
        padding: .28rem .7rem; font-size: .8rem; color: #333;
        background: #fff; cursor: pointer;
    }
    .date-clear { font-size: .75rem; color: #94a3b8; text-decoration: none; cursor: pointer; }
    .date-clear:hover { color: #dc3545; }
    .btn-export {
        margin-left: auto;
        background: #198754; color: #fff;
        border: none; border-radius: 8px;
        padding: .38rem 1rem; font-size: .8rem; font-weight: 600;
        cursor: pointer; text-decoration: none;
        display: inline-flex; align-items: center; gap: .4rem;
    }
    .btn-export:hover { background: #157347; color: #fff; }

    /* Table */
    .table-card {
        background: rgba(255,255,255,.93);
        backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
        border-radius: 14px;
        box-shadow: 0 4px 20px rgba(0,0,0,.12);
        overflow: hidden;
    }
    .table-responsive {
        overflow-x: auto;
        overflow-y: auto;
        max-height: 580px;
    }
    .table-responsive::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }
    .table-responsive::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.05);
        border-radius: 4px;
    }
    .table-responsive::-webkit-scrollbar-thumb {
        background: rgba(11, 60, 110, 0.2);
        border-radius: 4px;
    }
    .table-responsive::-webkit-scrollbar-thumb:hover {
        background: rgba(11, 60, 110, 0.4);
    }
    table.resp-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: .875rem;
    }
    table.resp-table thead th {
        position: sticky;
        top: 0;
        z-index: 10;
        background: #0b3c6e !important;
        box-shadow: inset 0 -1px 0 rgba(0,0,0,0.1);
    }
    table.resp-table thead {
        color: #fff;
    }
    table.resp-table th {
        padding: .9rem 1rem;
        font-weight: 700;
        white-space: nowrap;
        text-align: left;
    }
    table.resp-table td {
        padding: .8rem 1rem;
        border-bottom: 1px solid #f0f0f0;
        vertical-align: middle;
    }
    table.resp-table tbody tr:hover { background: #f8fafd; }
    table.resp-table tbody tr:last-child td { border-bottom: none; }

    .badge-brand {
        display: inline-block;
        padding: .25rem .7rem;
        border-radius: 20px;
        font-size: .75rem;
        font-weight: 700;
    }
    .badge-sinotruk { background: rgba(215,0,6,.1); color: #d70006; }
    .badge-kinglong { background: rgba(11,60,110,.1); color: #0b3c6e; }
    .badge-banned   { background: rgba(220,53,69,.12); color: #dc3545; }
    .badge-ok       { background: rgba(25,135,84,.1);  color: #198754; }

    .badge-corr {
        display: inline-block;
        padding: .22rem .65rem;
        border-radius: 20px;
        font-size: .72rem;
        font-weight: 700;
        white-space: nowrap;
    }
    .corr-pending    { background: #f1f3f5; color: #888; }
    .corr-inprogress { background: rgba(255,140,0,.12); color: #b06a00; }
    .corr-done       { background: rgba(25,135,84,.12); color: #146c43; }

    .btn-view {
        background: none;
        border: 1.5px solid #0b3c6e;
        color: #0b3c6e;
        border-radius: 7px;
        padding: .3rem .75rem;
        font-size: .8rem;
        font-weight: 600;
        cursor: pointer;
        white-space: nowrap;
    }
    .btn-view:hover { background: #0b3c6e; color: #fff; }
    .btn-recorrect {
        background: none;
        border: 1.5px solid #e8711a;
        color: #e8711a;
        border-radius: 7px;
        padding: .3rem .75rem;
        font-size: .8rem;
        font-weight: 600;
        cursor: pointer;
        white-space: nowrap;
        text-decoration: none;
        display: inline-flex; align-items: center; gap: .3rem;
    }
    .btn-recorrect:hover { background: #e8711a; color: #fff; }

    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        color: #aaa;
    }
    .empty-state .fa { font-size: 3rem; margin-bottom: 1rem; }
    .candidate-name-link:hover {
        color: #d70006 !important;
        text-decoration: underline !important;
    }

    /* Slide-in Drawer Overlay */
    .drawer-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.4);
        backdrop-filter: blur(4px);
        z-index: 1000;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.3s ease, visibility 0.3s ease;
    }
    .drawer-overlay.active {
        opacity: 1;
        visibility: visible;
    }

    /* Drawer Panel */
    .drawer-panel {
        position: fixed;
        top: 0;
        right: 0;
        bottom: 0;
        width: 650px;
        max-width: 100%;
        background: #f8fafc;
        box-shadow: -10px 0 30px rgba(0, 0, 0, 0.15);
        z-index: 1001;
        transform: translateX(100%);
        transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1);
        display: flex;
        flex-direction: column;
    }
    .drawer-panel.active {
        transform: translateX(0);
    }

    /* Drawer Header */
    .drawer-header {
        background: #0f172a;
        color: #fff;
        padding: 1.25rem 1.5rem;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }
    .drawer-header-top {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    .drawer-back-btn {
        background: none;
        border: 1px solid rgba(255,255,255,0.2);
        color: rgba(255,255,255,0.85);
        border-radius: 8px;
        width: 34px;
        height: 34px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.15s;
    }
    .drawer-back-btn:hover {
        background: rgba(255,255,255,0.1);
        color: #fff;
        border-color: rgba(255,255,255,0.4);
    }
    .drawer-breadcrumbs {
        font-size: 0.85rem;
        color: rgba(255,255,255,0.5);
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }
    .drawer-breadcrumbs a {
        color: inherit;
        text-decoration: none;
    }
    .drawer-breadcrumbs span.curr {
        color: #fff;
        font-weight: 600;
    }
    .drawer-title {
        font-size: 1.15rem;
        font-weight: 700;
        color: #fff;
    }

    /* Drawer Content */
    .drawer-body {
        flex: 1;
        overflow-y: auto;
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }

    /* Detail Cards */
    .detail-card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 1px 4px rgba(0,0,0,0.05);
        padding: 1.2rem;
        border: 1px solid #e2e8f0;
    }
    .detail-card-title {
        font-size: 0.85rem;
        font-weight: 800;
        color: #0b3c6e;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        border-bottom: 1px solid #f1f5f9;
        padding-bottom: 0.5rem;
    }
    .btn-drawer-edit {
        margin-left: auto;
        background: #f1f5f9;
        border: 1px solid #cbd5e1;
        color: #0b3c6e;
        border-radius: 6px;
        padding: 0.2rem 0.6rem;
        font-size: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 4px;
        text-transform: none;
        letter-spacing: 0;
    }
    .btn-drawer-edit:hover { background: #e2e8f0; }
    .edit-field-row {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
        margin-bottom: 0.65rem;
    }
    .edit-field-lbl {
        font-size: 0.72rem;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }
    .edit-field-input {
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        padding: 0.4rem 0.65rem;
        font-size: 0.85rem;
        color: #1e293b;
        background: #f8fafc;
        outline: none;
        width: 100%;
        box-sizing: border-box;
    }
    .edit-field-input:focus { border-color: #0b3c6e; background: #fff; }
    .btn-edit-save {
        background: #0b3c6e;
        color: #fff;
        border: none;
        border-radius: 6px;
        padding: 0.4rem 1rem;
        font-size: 0.82rem;
        font-weight: 600;
        cursor: pointer;
    }
    .btn-edit-save:hover { background: #0d4a8a; }
    .btn-edit-save:disabled { opacity: .6; cursor: not-allowed; }
    .btn-edit-cancel {
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        padding: 0.4rem 1rem;
        font-size: 0.82rem;
        font-weight: 600;
        cursor: pointer;
    }
    .btn-edit-cancel:hover { background: #e2e8f0; }
    .info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }
    .info-item {
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
    }
    .info-lbl {
        font-size: 0.72rem;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
    }
    .info-val {
        font-size: 0.88rem;
        font-weight: 600;
        color: #1e293b;
    }

    /* Q&A Section inside Drawer */
    .drawer-section-header {
        font-size: 0.88rem;
        font-weight: 800;
        color: #0b3c6e;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 0.75rem 1rem;
        background: #f1f5f9;
        border-left: 3px solid #0b3c6e;
        border-radius: 6px;
        margin: 0.75rem 0;
    }
    .drawer-q-card {
        background: #fff;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        padding: 1rem;
        margin-bottom: 0.75rem;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }
    .drawer-q-card.verdict-correct { border-left: 3px solid #198754; }
    .drawer-q-card.verdict-partiel { border-left: 3px solid #ff8c00; }
    .drawer-q-card.verdict-incorrect { border-left: 3px solid #dc3545; }

    .drawer-q-text {
        font-size: 0.85rem;
        font-weight: 700;
        color: #0b3c6e;
        display: flex;
        gap: 0.5rem;
    }
    .drawer-q-num {
        background: #edf2f7;
        color: #475569;
        border-radius: 6px;
        width: 22px;
        height: 22px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.72rem;
        font-weight: 800;
        flex-shrink: 0;
    }
    .drawer-ans-block {
        background: #f8fafc;
        border-radius: 8px;
        padding: 0.6rem 0.8rem;
        border-left: 2px solid #cbd5e1;
    }
    .drawer-ans-lbl {
        font-size: 0.68rem;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        margin-bottom: 0.25rem;
    }
    .drawer-ans-val {
        font-size: 0.82rem;
        color: #334155;
        white-space: pre-wrap;
    }
    .drawer-grade-block {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
        border-top: 1px dashed #e2e8f0;
        padding-top: 0.6rem;
    }
    .drawer-comment {
        font-size: 0.78rem;
        color: #64748b;
        font-style: italic;
        background: #fff8f2;
        padding: 0.4rem 0.6rem;
        border-radius: 6px;
        border: 1px solid #ffe8d6;
        width: 100%;
    }

</style>

<?php
// ── Données pour les stats ────────────────────────────────────────────────
$totalAll         = $db->commercial_responses->count([]);
$totalSinotruk    = $db->commercial_responses->count(['brand' => 'SINOTRUK']);
$totalKL          = $db->commercial_responses->count(['brand' => 'KING LONG']);
$totalBanned      = $db->commercial_responses->count(['is_banned' => true]);
$totalCorrDone    = $db->commercial_corrections->count(['status' => 'completed']);


// Construire l'URL de filtrage pour export
$exportParams = http_build_query(array_filter([
    'export' => 'excel',
    'brand'  => $filterBrand,
    'date'   => $filterDate,
]));

// Convertir la date filtre pour le champ date HTML (YYYY-MM-DD)
$filterDateHtml = '';
if ($filterDate !== '') {
    $parts = explode('-', $filterDate);
    if (count($parts) === 3) {
        $filterDateHtml = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
    }
}
?>

<div class="responses-container">
    <div class="title-card">
        <img src="../public/images/sinotruk.png" alt="SINOTRUK" class="title-card-brand"
             onerror="this.style.display='none'">
        <div class="title-card-center">
            <div class="title-card-h1"><i class="fas fa-clipboard-list" style="color:#D70006;margin-right:.5rem"></i>Suivi des Évaluations Techniques</div>
            <div class="title-card-p">Consultez et exportez les réponses aux questionnaires SINOTRUK et KING LONG.</div>
        </div>
        <img src="../public/images/king-long-seeklogo.png" alt="KING LONG" class="title-card-brand"
             onerror="this.style.display='none'">
    </div>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-users"></i></div>
            <div>
                <div class="stat-value"><?php echo $totalAll; ?></div>
                <div class="stat-label">Total réponses</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red"><i class="fas fa-truck"></i></div>
            <div>
                <div class="stat-value"><?php echo $totalSinotruk; ?></div>
                <div class="stat-label">SINOTRUK</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-bus"></i></div>
            <div>
                <div class="stat-value"><?php echo $totalKL; ?></div>
                <div class="stat-label">KING LONG</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange"><i class="fas fa-ban"></i></div>
            <div>
                <div class="stat-value"><?php echo $totalBanned; ?></div>
                <div class="stat-label">Sessions bannies</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple"><i class="fas fa-check-double"></i></div>
            <div>
                <div class="stat-value"><?php echo $totalCorrDone; ?> <span style="font-size:1rem;font-weight:600;color:#aaa;">/ <?php echo $totalAll; ?></span></div>
                <div class="stat-label">Corrections terminées</div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="table-card">
        <!-- Toolbar -->
        <div class="table-toolbar">
            <div class="brand-filters">
                <button class="brand-btn active" data-brand="" onclick="filterBrand(this, '')">Toutes</button>
                <button class="brand-btn" data-brand="SINOTRUK" onclick="filterBrand(this, 'SINOTRUK')">
                    <img src="../public/images/sinotruk.png" alt="SINOTRUK"> SINOTRUK
                </button>
                <button class="brand-btn" data-brand="KING LONG" onclick="filterBrand(this, 'KING LONG')">
                    <img src="../public/images/king-long-seeklogo.png" alt="KING LONG"> KING LONG
                </button>
            </div>
            <div class="toolbar-sep"></div>
            <form class="date-filter-wrap" method="GET" action="" id="dateForm">
                <label for="dateInput">Date :</label>
                <input type="date" id="dateInput" name="date"
                       value="<?php echo htmlspecialchars($filterDateHtml, ENT_QUOTES); ?>"
                       onchange="this.form.submit()">
                <?php if ($filterDate !== ''): ?>
                <a href="responses.php" class="date-clear" title="Effacer"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </form>
            <a href="?<?php echo htmlspecialchars($exportParams, ENT_QUOTES); ?>" class="btn-export" id="exportLink">
                <i class="fas fa-file-excel"></i> Exporter Excel
            </a>
        </div>

        <?php if (empty($rows)): ?>
        <div class="empty-state">
            <div><i class="fas fa-inbox"></i></div>
            <div style="font-size:1rem;font-weight:600;color:#bbb;">Aucune réponse trouvée</div>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="resp-table" id="respTable">
                <thead>
                    <tr>
                        <th class="sortable" data-col="0">#<span class="sort-icon"></span></th>
                        <th class="sortable" data-col="1">Nom complet<span class="sort-icon"></span></th>
                        <th class="sortable" data-col="2">Email<span class="sort-icon"></span></th>
                        <th class="sortable" data-col="3" style="text-align:center;">Marque<span class="sort-icon"></span></th>
                        <th class="sortable" data-col="4" style="text-align:center;">Violations<span class="sort-icon"></span></th>
                        <th class="sortable" data-col="5">Temps<span class="sort-icon"></span></th>
                        <th class="sortable" data-col="6">Statut<span class="sort-icon"></span></th>
                        <th class="sortable" data-col="7">Correction<span class="sort-icon"></span></th>
                        <th class="sortable" data-col="8">Résultat<span class="sort-icon"></span></th>
                        <th class="sortable" data-col="9">Date<span class="sort-icon"></span></th>
                        <th>Corriger</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $i => $row):
                        $totalTime  = isset($row['total_time_seconds']) ? (int)$row['total_time_seconds'] : 0;
                        $timeStr    = ($totalTime > 0)
                             ? floor($totalTime / 60) . 'min ' . ($totalTime % 60) . 's'
                             : '—';
                        $isBanned   = isset($row['is_banned']) && $row['is_banned'];
                        $brand      = $row['brand'] ?? '';
                        $isKL       = ($brand === 'KING LONG');
                        $rid        = (string)$row['_id'];
                        $corrInfo   = isset($corrMap[$rid]) ? $corrMap[$rid] : ['status' => 'pending', 'correct' => 0, 'partiel' => 0, 'total' => 0];
                        $corrStatus = $corrInfo['status'];
                        $corrStatusLabels = [
                            'pending'     => '<i class="fas fa-hourglass-half me-1" style="color: #ff8c00;"></i> En attente',
                            'in_progress' => '<i class="fas fa-spinner fa-spin me-1" style="color: #3b82f6;"></i> En cours',
                            'completed'   => '<i class="fas fa-check-circle me-1" style="color: #198754;"></i> Terminée'
                        ];
                        $corrStatusClasses = ['pending' => 'corr-pending', 'in_progress' => 'corr-inprogress', 'completed' => 'corr-done'];
                        $corrStatusLabel = isset($corrStatusLabels[$corrStatus]) ? $corrStatusLabels[$corrStatus] : $corrStatus;
                        $corrStatusClass = isset($corrStatusClasses[$corrStatus]) ? $corrStatusClasses[$corrStatus] : 'corr-pending';
                    ?>
                    <tr data-brand="<?php echo htmlspecialchars($brand, ENT_QUOTES, 'UTF-8'); ?>">
                        <td><?php echo $i + 1; ?></td>
                        <td>
                            <a href="#" class="candidate-name-link" data-id="<?php echo $rid; ?>" style="color: #0b3c6e; text-decoration: none; font-weight: 700; transition: color 0.15s;">
                                <?php echo htmlspecialchars(($row['firstName'] ?? '') . ' ' . ($row['lastName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </td>
                        <td><?php echo htmlspecialchars($row['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td style="text-align:center;">
                            <?php if ($isKL): ?>
                            <img src="../public/images/king-long-seeklogo.png" alt="KING LONG" style="height:22px;width:auto;object-fit:contain;display:inline-block;">
                            <?php else: ?>
                            <img src="../public/images/sinotruk.png" alt="SINOTRUK" style="height:22px;width:auto;object-fit:contain;display:inline-block;">
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;"><?php echo (int)($row['violations'] ?? 0); ?></td>
                        <td><?php echo $timeStr; ?></td>
                        <td>
                            <span class="badge-brand <?php echo $isBanned ? 'badge-banned' : 'badge-ok'; ?>">
                                <?php echo $isBanned ? '<i class="fas fa-ban me-1" style="color: #dc3545;"></i> Banni' : '<i class="fas fa-check-circle me-1" style="color: #198754;"></i> Complété'; ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge-corr <?php echo $corrStatusClass; ?>"><?php echo $corrStatusLabel; ?></span>
                        </td>
                        <td>
                            <?php if ($corrStatus === 'completed' && $corrInfo['total'] > 0): ?>
                            <strong style="color: #198754; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 4px; white-space: nowrap;">
                                <i class="fas fa-check-circle" style="color:#198754;"></i><?php echo $corrInfo['correct']; ?> / <?php echo $corrInfo['total']; ?>
                            </strong>
                            <?php else: ?>
                            <span style="color:#888;font-size:0.8rem;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="white-space:nowrap;"><?php echo htmlspecialchars($row['submitted_at'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <?php
                            $histCount = (int)($corrInfo['history_count'] ?? 0);
                            if ($corrStatus === 'completed'):
                            ?>
                            <a href="correction.php?id=<?php echo urlencode($rid); ?>" class="btn-recorrect">
                                <i class="fas fa-redo"></i> Recorriger<?php echo $histCount > 0 ? ' (' . ($histCount + 1) . 'e)' : ''; ?>
                            </a>
                            <?php else: ?>
                            <a href="correction.php?id=<?php echo urlencode($rid); ?>" class="btn-view">
                                <i class="fas fa-pen me-1"></i> Corriger
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div style="padding:.8rem 1rem;font-size:.8rem;color:#aaa;border-top:1px solid #f0f0f0;">
            <?php echo count($rows); ?> réponse(s) affichée(s)
        </div>
        <?php endif; ?>
    </div>
<!-- Drawer overlay and panel -->
<div class="drawer-overlay" id="drawerOverlay" onclick="closeCandidateDrawer()"></div>
<div class="drawer-panel" id="drawerPanel">
    <div class="drawer-header">
        <div class="drawer-header-top">
            <button class="drawer-back-btn" id="drawerBackBtn" onclick="closeCandidateDrawer()" title="Retour"><i class="fas fa-arrow-left"></i></button>
            <div>
                <div class="drawer-breadcrumbs">
                    <span>Réponses</span>
                    <span class="bc-sep">›</span>
                    <span class="curr" id="drawerBreadcrumbName">Détails du candidat</span>
                </div>
                <div class="drawer-title" id="drawerTitle">Nom complet du candidat</div>
            </div>
        </div>
    </div>
    <div class="drawer-body" id="drawerBody">
        <!-- Content loaded dynamically via JS -->
    </div>
</div>

<footer class="page-footer">
    <?php echo date('Y'); ?>&copy; CFAO Mobility Academy &mdash; All rights reserved
</footer>

<script>
// ── Brand filter ──────────────────────────────────────────────────────────────
var _activeBrand = '';
function filterBrand(btn, brand) {
    _activeBrand = brand;
    document.querySelectorAll('.brand-btn').forEach(function(b) { b.classList.remove('active'); });
    btn.classList.add('active');
    var rows = document.querySelectorAll('#respTable tbody tr');
    rows.forEach(function(r) {
        r.style.display = (brand === '' || r.getAttribute('data-brand') === brand) ? '' : 'none';
    });
    // Update export link with brand filter
    var exportLink = document.getElementById('exportLink');
    if (exportLink) {
        var url = new URL(exportLink.href, location.origin);
        if (brand) { url.searchParams.set('brand', brand); }
        else        { url.searchParams.delete('brand'); }
        exportLink.href = url.toString();
    }
}

// ── Sort ──────────────────────────────────────────────────────────────────────
(function() {
    var table = document.getElementById('respTable');
    if (!table) return;
    var tbody = table.querySelector('tbody');
    var headers = table.querySelectorAll('th.sortable');
    var currentCol = -1, currentDir = 0;

    function getCellVal(row, col) {
        var cell = row.querySelectorAll('td')[col];
        return cell ? cell.innerText.trim() : '';
    }

    function parseVal(str, col) {
        // Temps: "1min 24s" → total seconds
        if (col === 5) {
            var m = str.match(/(\d+)min\s*(\d+)s/);
            if (m) return parseInt(m[1]) * 60 + parseInt(m[2]);
            return 0;
        }
        // Résultat: "12 / 20" → 12
        if (col === 8) {
            var n = str.match(/^(\d+)/);
            return n ? parseInt(n[1]) : -1;
        }
        // Numérique pur
        if (!isNaN(str) && str !== '') return parseFloat(str);
        return str.toLowerCase();
    }

    function sortTable(col, dir) {
        var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
        rows.sort(function(a, b) {
            var va = parseVal(getCellVal(a, col), col);
            var vb = parseVal(getCellVal(b, col), col);
            if (va < vb) return -1 * dir;
            if (va > vb) return  1 * dir;
            return 0;
        });
        rows.forEach(function(r) {
            tbody.appendChild(r);
            // Réappliquer le filtre marque après réordonnancement
            r.style.display = (_activeBrand === '' || r.getAttribute('data-brand') === _activeBrand) ? '' : 'none';
        });
    }

    headers.forEach(function(th) {
        th.addEventListener('click', function() {
            var col = parseInt(th.getAttribute('data-col'));
            // Cycler : neutre → asc → desc → asc...
            if (currentCol === col) {
                currentDir = currentDir === 1 ? -1 : 1;
            } else {
                currentCol = col;
                currentDir = 1;
            }
            headers.forEach(function(h) { h.classList.remove('asc', 'desc'); });
            th.classList.add(currentDir === 1 ? 'asc' : 'desc');
            sortTable(col, currentDir);
        });
    });
    // Drawer handlers
    document.querySelectorAll('.candidate-name-link').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            var id = this.getAttribute('data-id');
            window.openCandidateDrawer(id);
        });
    });

    var _drawerCandId = null;

    window.openCandidateDrawer = function(id) {
        _drawerCandId = id;
        var overlay = document.getElementById('drawerOverlay');
        var panel = document.getElementById('drawerPanel');
        var body = document.getElementById('drawerBody');
        var bcName = document.getElementById('drawerBreadcrumbName');
        var title = document.getElementById('drawerTitle');

        // Reset content & show loading
        bcName.textContent = 'Détails';
        title.textContent = 'Chargement...';
        body.innerHTML = `
            <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:200px;color:#0b3c6e;gap:1rem;">
                <i class="fas fa-circle-notch fa-spin" style="font-size:2.5rem;"></i>
                <span style="font-size:0.9rem;font-weight:600;">Récupération des informations...</span>
            </div>
        `;

        overlay.classList.add('active');
        panel.classList.add('active');
        document.body.style.overflow = 'hidden'; // block page scrolling

        fetch('responses.php?ajax_id=' + encodeURIComponent(id))
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (!data.ok) {
                    body.innerHTML = `
                        <div class="alert alert-danger" style="margin: 1rem; padding: 1rem; background: #fee2e2; border: 1px solid #fca5a5; color: #b91c1c; border-radius:8px;">
                            <i class="fas fa-exclamation-circle me-2"></i> ${data.error || 'Erreur inconnue'}
                        </div>
                    `;
                    title.textContent = 'Erreur';
                    return;
                }

                var cand = data.candidate;
                bcName.textContent = cand.fullName;
                title.textContent = cand.fullName;

                // Render stats/score card
                var scoreHtml = '';
                if (cand.corrStatus === 'completed' && cand.score.max > 0) {
                    var pct = Math.round((cand.score.correct / cand.score.max) * 100);
                    scoreHtml = `
                        <div class="detail-card">
                            <div class="detail-card-title"><i class="fas fa-chart-pie"></i> Résultat de la correction</div>
                            <div style="display:flex;align-items:center;justify-content:between;margin-bottom:0.5rem;">
                                <strong style="color: #198754; font-size: 1.1rem; display: inline-flex; align-items: center; gap: 6px;">
                                    <i class="fas fa-check-circle"></i> ${cand.score.correct} / ${cand.score.max} correct(s)
                                </strong>
                                <span style="margin-left:auto;font-size:0.9rem;font-weight:700;color:#555;">${pct}%</span>
                            </div>
                            <div style="height:8px;background:#e2e8f0;border-radius:4px;overflow:hidden;">
                                <div style="height:100%;width:${pct}%;background:#198754;border-radius:4px;"></div>
                            </div>
                        </div>
                    `;
                }

                // General Info Card
                var violationsStyle = cand.violations > 0 ? 'color: #dc3545; font-weight:700;' : '';
                var bannedHtml = cand.isBanned 
                    ? '<span class="badge-brand badge-banned"><i class="fas fa-ban me-1"></i> Banni</span>'
                    : '<span class="badge-brand badge-ok"><i class="fas fa-check-circle me-1"></i> Complété</span>';

                var corrStatusLabel = '';
                if (cand.corrStatus === 'completed') {
                    corrStatusLabel = '<span class="badge-corr corr-done"><i class="fas fa-check-circle me-1"></i> Terminée</span>';
                } else if (cand.corrStatus === 'in_progress') {
                    corrStatusLabel = '<span class="badge-corr corr-inprogress"><i class="fas fa-spinner fa-spin me-1"></i> En cours</span>';
                } else {
                    corrStatusLabel = '<span class="badge-corr corr-pending"><i class="fas fa-hourglass-half me-1"></i> En attente</span>';
                }

                var editBtnHtml = data.isSuperAdmin
                    ? '<button class="btn-drawer-edit" onclick="toggleDrawerEdit()" title="Modifier les informations"><i class="fas fa-pencil-alt"></i> Modifier</button>'
                    : '';

                var medacapCountries = ['Burkina Faso','Cameroun',"Cote d'Ivoire",'Gabon','Madagascar','Mali','RCA','RDC','Senegal','Congo','Rwanda'];
                var countryOptions = medacapCountries.map(function(c) {
                    var sel = c === cand.country ? ' selected' : '';
                    return '<option value="' + escapeHtml(c) + '"' + sel + '>' + escapeHtml(c) + '</option>';
                }).join('');

                var generalInfoHtml = `
                    <div class="detail-card" id="infoDetailCard">
                        <div class="detail-card-title" style="display:flex;align-items:center;justify-content:space-between;">
                            <span><i class="fas fa-info-circle"></i> Informations Générales</span>
                            ${editBtnHtml}
                        </div>
                        <div id="infoViewMode" class="info-grid">
                            <div class="info-item">
                                <span class="info-lbl">Prénom</span>
                                <span class="info-val" id="infoViewFirstName">${escapeHtml(cand.firstName)}</span>
                            </div>
                            <div class="info-item">
                                <span class="info-lbl">Nom</span>
                                <span class="info-val" id="infoViewLastName">${escapeHtml(cand.lastName)}</span>
                            </div>
                            <div class="info-item">
                                <span class="info-lbl">Email</span>
                                <span class="info-val" style="word-break:break-all;">${cand.email}</span>
                            </div>
                            <div class="info-item">
                                <span class="info-lbl">Pays</span>
                                <span class="info-val" id="infoViewCountry">${escapeHtml(cand.country)}</span>
                            </div>
                            <div class="info-item">
                                <span class="info-lbl">Marque</span>
                                <span class="info-val">${cand.brand}</span>
                            </div>
                            <div class="info-item">
                                <span class="info-lbl">Temps de passage</span>
                                <span class="info-val">${cand.timeStr}</span>
                            </div>
                            <div class="info-item">
                                <span class="info-lbl">Violations</span>
                                <span class="info-val" style="${violationsStyle}">${cand.violations}</span>
                            </div>
                            <div class="info-item">
                                <span class="info-lbl">Date de soumission</span>
                                <span class="info-val">${cand.submittedAt}</span>
                            </div>
                            <div class="info-item">
                                <span class="info-lbl">Session</span>
                                <span class="info-val" style="margin-top:0.2rem;">${bannedHtml}</span>
                            </div>
                            <div class="info-item">
                                <span class="info-lbl">Correction</span>
                                <span class="info-val" style="margin-top:0.2rem;">${corrStatusLabel}</span>
                            </div>
                        </div>
                        ${data.isSuperAdmin ? `
                        <div id="infoEditMode" style="display:none;padding-top:0.75rem;">
                            <div class="edit-field-row">
                                <label class="edit-field-lbl">Prénom</label>
                                <input type="text" class="edit-field-input" id="editFirstName" value="${escapeHtml(cand.firstName)}">
                            </div>
                            <div class="edit-field-row">
                                <label class="edit-field-lbl">Nom</label>
                                <input type="text" class="edit-field-input" id="editLastName" value="${escapeHtml(cand.lastName)}">
                            </div>
                            <div class="edit-field-row">
                                <label class="edit-field-lbl">Pays</label>
                                <select class="edit-field-input" id="editCountry">${countryOptions}</select>
                            </div>
                            <div id="editInfoMsg" style="font-size:0.8rem;margin:.4rem 0;min-height:1.2em;"></div>
                            <div style="display:flex;gap:.5rem;margin-top:.5rem;">
                                <button class="btn-edit-save" onclick="saveDrawerEdit()"><i class="fas fa-check me-1"></i>Enregistrer</button>
                                <button class="btn-edit-cancel" onclick="toggleDrawerEdit()"><i class="fas fa-times me-1"></i>Annuler</button>
                            </div>
                        </div>` : ''}
                    </div>
                `;

                // Loop Sections & Q&A
                var qaHtml = '';
                var qCount = 0;
                if (data.sections && data.sections.length > 0) {
                    data.sections.forEach(function(sec) {
                        qaHtml += `<div class="drawer-section-header">${sec.title}</div>`;
                        if (sec.questions && sec.questions.length > 0) {
                            sec.questions.forEach(function(q) {
                                qCount++;
                                var qCardClass = '';
                                var verdictChip = '';
                                if (q.verdict === 'correct') {
                                    qCardClass = 'verdict-correct';
                                    verdictChip = '<span class="badge-corr corr-done" style="font-size:0.68rem;padding:0.15rem 0.5rem;"><i class="fas fa-check-circle me-1"></i> Correct</span>';
                                } else if (q.verdict === 'partiel') {
                                    qCardClass = 'verdict-partiel';
                                    verdictChip = '<span class="badge-corr corr-inprogress" style="font-size:0.68rem;padding:0.15rem 0.5rem;"><i class="fas fa-exclamation-circle me-1"></i> Partiel</span>';
                                } else if (q.verdict === 'incorrect') {
                                    qCardClass = 'verdict-incorrect';
                                    verdictChip = '<span class="badge-corr corr-pending" style="font-size:0.68rem;padding:0.15rem 0.5rem;background:rgba(220,53,69,0.1);color:#dc3545;"><i class="fas fa-times-circle me-1"></i> Incorrect</span>';
                                } else {
                                    verdictChip = '<span class="badge-corr corr-pending" style="font-size:0.68rem;padding:0.15rem 0.5rem;"><i class="fas fa-minus-circle me-1"></i> Non noté</span>';
                                }

                                var answerValHtml = q.answer 
                                    ? `<span class="drawer-ans-val">${escapeHtml(q.answer)}</span>`
                                    : `<span class="drawer-ans-val" style="color:#aaa;font-style:italic;">Sans réponse</span>`;

                                var commentHtml = q.comment
                                    ? `<div class="drawer-comment"><strong>Commentaire :</strong> ${escapeHtml(q.comment)}</div>`
                                    : '';

                                qaHtml += `
                                    <div class="drawer-q-card ${qCardClass}">
                                        <div class="drawer-q-text">
                                            <span class="drawer-q-num">${qCount}</span>
                                            <span>${escapeHtml(q.question)}</span>
                                        </div>
                                        <div class="drawer-ans-block">
                                            <div class="drawer-ans-lbl">Réponse du candidat</div>
                                            ${answerValHtml}
                                        </div>
                                        <div class="drawer-grade-block">
                                            ${verdictChip}
                                            ${commentHtml}
                                        </div>
                                    </div>
                                `;
                            });
                        }
                    });
                }

                body.innerHTML = generalInfoHtml + scoreHtml + qaHtml;
            })
            .catch(function(err) {
                body.innerHTML = `
                    <div class="alert alert-danger" style="margin: 1rem; padding: 1rem; background: #fee2e2; border: 1px solid #fca5a5; color: #b91c1c; border-radius:8px;">
                        <i class="fas fa-exclamation-circle me-2"></i> Une erreur réseau ou serveur est survenue.
                    </div>
                `;
                title.textContent = 'Erreur';
            });
    };

    window.toggleDrawerEdit = function() {
        var viewMode = document.getElementById('infoViewMode');
        var editMode = document.getElementById('infoEditMode');
        var editBtn  = document.querySelector('.btn-drawer-edit');
        if (!viewMode || !editMode) return;
        var isEditing = editMode.style.display !== 'none';
        viewMode.style.display = isEditing ? '' : 'none';
        editMode.style.display = isEditing ? 'none' : '';
        if (editBtn) editBtn.innerHTML = isEditing ? '<i class="fas fa-pencil-alt"></i> Modifier' : '<i class="fas fa-times"></i> Annuler';
        var msg = document.getElementById('editInfoMsg');
        if (msg) msg.textContent = '';
    };

    window.saveDrawerEdit = function() {
        var firstName = (document.getElementById('editFirstName') || {}).value || '';
        var lastName  = (document.getElementById('editLastName')  || {}).value || '';
        var country   = (document.getElementById('editCountry')   || {}).value || '';
        var msg = document.getElementById('editInfoMsg');
        if (!firstName.trim() || !lastName.trim()) {
            if (msg) { msg.style.color = '#dc3545'; msg.textContent = 'Prénom et nom requis.'; }
            return;
        }
        var saveBtn = document.querySelector('.btn-edit-save');
        if (saveBtn) saveBtn.disabled = true;
        var fd = new FormData();
        fd.append('ajax_action', 'update_candidate');
        fd.append('id', _drawerCandId);
        fd.append('firstName', firstName.trim());
        fd.append('lastName',  lastName.trim());
        fd.append('country',   country);
        fetch('responses.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (saveBtn) saveBtn.disabled = false;
                if (!res.ok) {
                    if (msg) { msg.style.color = '#dc3545'; msg.textContent = res.error || 'Erreur serveur.'; }
                    return;
                }
                // Update view values
                var viewFN = document.getElementById('infoViewFirstName');
                var viewLN = document.getElementById('infoViewLastName');
                var viewCY = document.getElementById('infoViewCountry');
                if (viewFN) viewFN.textContent = firstName.trim();
                if (viewLN) viewLN.textContent = lastName.trim();
                if (viewCY) viewCY.textContent = country;
                // Update drawer title
                var newFullName = firstName.trim() + ' ' + lastName.trim();
                document.getElementById('drawerTitle').textContent = newFullName;
                document.getElementById('drawerBreadcrumbName').textContent = newFullName;
                // Update table row name link
                var link = document.querySelector('.candidate-name-link[data-id="' + _drawerCandId + '"]');
                if (link) link.textContent = newFullName;
                window.toggleDrawerEdit();
                if (msg) { msg.style.color = '#198754'; msg.textContent = 'Modifications enregistrées.'; }
            })
            .catch(function() {
                if (saveBtn) saveBtn.disabled = false;
                if (msg) { msg.style.color = '#dc3545'; msg.textContent = 'Erreur réseau.'; }
            });
    };

    window.closeCandidateDrawer = function() {
        document.getElementById('drawerOverlay').classList.remove('active');
        document.getElementById('drawerPanel').classList.remove('active');
        document.body.style.overflow = ''; // restore scrolling
    };

    function escapeHtml(text) {
        if (!text) return '';
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
    }
})();

// Heartbeat — ping toutes les 60s pour tracker la présence
(function() {
    function ping() { fetch('ping.php', { method: 'POST' }).catch(function() {}); }
    ping();
    setInterval(ping, 60000);
})();
</script>
</body>
</html>
