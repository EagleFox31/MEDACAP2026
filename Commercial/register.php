<?php
require_once __DIR__ . '/../core/medacap_config.php';
session_start();
require_once "../vendor/autoload.php";
require_once "../views/sendMail.php";
require_once "sendCommercialMail.php";

use MongoDB\Client;

// Si session commerciale active → redirection directe
if (isset($_SESSION['commercial_session']) && $_SESSION['commercial_session'] === true) {
    $t = $_SESSION['commercial_type'] ?? '';
    header("Location: open-questionnaire.php" . ($t ? "?type={$t}" : ""));
    exit();
}

$validTypes = ['sinotruk', 'bus_chariot'];
$type = (isset($_GET['type']) && in_array($_GET['type'], $validTypes)) ? $_GET['type'] : '';

$typeConfig = [
    'sinotruk' => [
        'label'    => 'SINOTRUK',
        'brand'    => 'SINOTRUK',
        'logo'     => '../public/images/sinotruk.png',
        'subtitle' => 'Questionnaire Commercial — Gamme Camions',
        'accent'   => '#d70006',
    ],
    'bus_chariot' => [
        'label'    => 'KING LONG & CHARIOTS',
        'brand'    => 'KING LONG',
        'logo'     => '../public/images/king-long-seeklogo.png',
        'subtitle' => 'Questionnaire Commercial — Bus & Chariots',
        'accent'   => '#0b3c6e',
    ],
];

$config = $type ? $typeConfig[$type] : null;

$client      = new Client(medacapMongoUri());
$db          = $client->academy;
$commercials = $db->commercials;

$errors      = [];
$fieldErrors = ['email' => ''];
$formData    = ['email' => ''];

function detectCountry()
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($ip === '127.0.0.1' || $ip === '::1' || $ip === '') {
        return '';
    }
    $ctx      = stream_context_create(['http' => ['timeout' => 3]]);
    $response = @file_get_contents("http://ip-api.com/json/{$ip}?fields=country", false, $ctx);
    if ($response !== false) {
        $data = json_decode($response, true);
        if (isset($data['country'])) {
            return (string) $data['country'];
        }
    }
    return '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $config) {
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $formData = ['email' => $email];

    $blockedDomains = [
        'gmail.com', 'yahoo.com', 'yahoo.fr', 'hotmail.com', 'hotmail.fr',
        'outlook.com', 'outlook.fr', 'live.com', 'live.fr', 'msn.com',
        'icloud.com', 'me.com', 'mac.com', 'aol.com', 'wanadoo.fr',
        'orange.fr', 'free.fr', 'sfr.fr', 'laposte.net', 'bbox.fr',
        'protonmail.com', 'proton.me', 'tutanota.com', 'yandex.com',
    ];

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $fieldErrors['email'] = "Adresse email invalide.";
    } else {
        $domain = substr($email, strpos($email, '@') + 1);
        if (in_array(strtolower($domain), $blockedDomains)) {
            $fieldErrors['email'] = "Veuillez utiliser votre adresse email professionnelle (ex\u{00A0}: prenom.nom@cfao.com).";
        }
    }

    $errors = array_filter($fieldErrors);

    if (empty($errors)) {
        $existing = $commercials->findOne(['email' => $email, 'questionnaire_type' => $type]);
        $hasSubmitted = $existing
            ? (bool) $db->commercial_responses->findOne(['email' => $email, 'brand' => $config['brand']])
            : false;

        if ($existing && $hasSubmitted) {
            $fieldErrors['email'] = "Vous avez déjà soumis ce questionnaire.";
            $errors['email'] = $fieldErrors['email'];
        } elseif ($existing && !$hasSubmitted) {
            // Inscrit mais pas encore soumis → incrémenter compteur + restaurer session
            $commercials->updateOne(
                ['_id' => $existing['_id']],
                ['$inc' => ['login_count' => 1], '$push' => ['login_dates' => date('d-m-Y H:i:s')]]
            );
            $_SESSION['commercial_session'] = true;
            $_SESSION['id']                 = (string) $existing['_id'];
            $_SESSION['firstName']          = isset($existing['firstName']) ? (string)$existing['firstName'] : '';
            $_SESSION['lastName']           = isset($existing['lastName'])  ? (string)$existing['lastName']  : '';
            $_SESSION['email']              = $email;
            $_SESSION['profile']            = 'Commercial';
            $_SESSION['commercial_type']    = $type;
            $_SESSION['lang']               = 'FR';
            $_SESSION['country']            = isset($existing['country']) ? (string)$existing['country'] : '';
            header("Location: open-questionnaire.php?type=" . urlencode($type));
            exit();
        } else {
            $country = detectCountry();

            // Extraire prénom/nom depuis l'email : jdoe-ext@cfao.com → J / DOE
            $localPart = explode('@', $email)[0];
            $localPart = preg_replace('/-ext$/i', '', $localPart);
            $firstName = strtoupper(substr($localPart, 0, 1));
            $lastName  = strtoupper(substr($localPart, 1));

            $doc = [
                "firstName"          => $firstName,
                "lastName"           => $lastName,
                "email"              => $email,
                "brand"              => $config['brand'],
                "questionnaire_type" => $type,
                "country"            => $country,
                "ip"                 => $_SERVER['REMOTE_ADDR'] ?? '',
                "created_at"         => date("d-m-Y H:i:s"),
                "login_count"        => 1,
                "login_dates"        => [date("d-m-Y H:i:s")],
            ];

            $result = $commercials->insertOne($doc);
            $userId = $result->getInsertedId();

            $_SESSION['commercial_session'] = true;
            $_SESSION['id']                 = (string) $userId;
            $_SESSION['firstName']          = $firstName;
            $_SESSION['lastName']           = $lastName;
            $_SESSION['email']              = $email;
            $_SESSION['profile']            = 'Commercial';
            $_SESSION['commercial_type']    = $type;
            $_SESSION['lang']               = 'FR';
            $_SESSION['country']            = $country;

            $brandLabel = $config['label'];
            sendCommercialEmail(
                SUPER_ADMIN_EMAIL,
                'MEDACAP — Accès Questionnaire Commercial : ' . $email,
                [
                    'companyName'    => 'CFAO Academy',
                    'companyTagline' => 'Questionnaires Commerciaux',
                    'userName'       => 'Équipe CFAO Academy',
                    'title'          => 'Nouvel accès au questionnaire commercial',
                    'message'        => 'Un commercial vient d\'accéder à la plateforme de questionnaire.',
                    'details'        => [
                        ['label' => 'Nom',        'value' => $firstName . ' ' . $lastName, 'highlight' => true],
                        ['label' => 'Email',      'value' => $email],
                        ['label' => 'Marque',     'value' => $brandLabel,                  'highlight' => true],
                        ['label' => 'Pays (IP)',  'value' => $country ?: 'Non détecté'],
                        ['label' => 'Date/heure', 'value' => date('d/m/Y H:i:s')],
                    ],
                    'additionalInfo' => 'Le questionnaire est en cours. Vous recevrez une seconde notification à la soumission.',
                    'supportEmail'   => SUPER_ADMIN_EMAIL,
                ]
            );

            header("Location: open-questionnaire.php?type=" . urlencode($type));
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="image/x-icon" href="../public/images/logos/cfao_academy.ico">
    <title>CFAO Equipment — Questionnaire Commercial</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --cfao-blue: #0b3c6e;
            --cfao-red:  #d70006;
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', system-ui, sans-serif;
            padding: 2rem 1rem;
            position: relative;
            overflow-x: hidden;
        }
        body::before {
            content: '';
            position: fixed;
            inset: -10%;
            background: url('../public/images/backgrounds/home.png') center/cover no-repeat;
            filter: blur(6px) brightness(.55);
            z-index: -1;
        }

        .register-wrapper {
            width: 100%;
            max-width: 520px;
        }

        /* ── Card header avec logos aux extrêmes ── */
        .card-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            padding-bottom: 1.6rem;
            margin-bottom: 1.6rem;
            border-bottom: 1px solid #f1f5f9;
        }
        .cfao-logo, .brand-logo {
            flex-shrink: 0;
            height: 42px;
            width: 100px;
            object-fit: contain;
        }
        .card-top-center {
            flex: 1;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: .5rem;
        }
        .card-top-center h2 {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--cfao-blue);
            margin: 0;
            letter-spacing: -.2px;
            line-height: 1.2;
        }
        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .28rem .85rem;
            border-radius: 30px;
            background: rgba(215,0,6,.07);
            border: 1px solid rgba(215,0,6,.18);
            color: #c00;
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .5px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        /* ── Card ── */
        .register-card {
            background: #fff;
            border-radius: 24px;
            padding: 2.4rem 2rem;
            box-shadow: 0 25px 60px rgba(0,0,0,.28);
        }
        .register-card .intro-text {
            text-align: center;
            color: #94a3b8;
            font-size: .82rem;
            margin-bottom: 1.8rem;
            line-height: 1.5;
        }

        /* ── Floating label fields ── */
        .field-group {
            position: relative;
            margin-bottom: 1.1rem;
        }
        .field-group input {
            width: 100%;
            height: 56px;
            padding: 18px 16px 6px;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            font-size: .95rem;
            color: #1e293b;
            background: #f8fafc;
            outline: none;
            transition: border-color .2s, box-shadow .2s, background .2s;
            appearance: none;
        }
        .field-group input:focus {
            border-color: var(--cfao-blue);
            background: #fff;
            box-shadow: 0 0 0 3.5px rgba(11,60,110,.1);
        }
        .field-group input.error {
            border-color: var(--cfao-red);
            background: #fff8f8;
        }
        .field-group input.valid {
            border-color: #22c55e;
            background: #fff;
        }
        .field-group label {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            font-size: .95rem;
            color: #94a3b8;
            pointer-events: none;
            transition: top .18s ease, font-size .18s ease, color .18s ease;
            background: transparent;
        }
        .field-group input:focus ~ label,
        .field-group input:not(:placeholder-shown) ~ label {
            top: 14px;
            font-size: .72rem;
            color: var(--cfao-blue);
            font-weight: 600;
            letter-spacing: .3px;
        }
        .field-group input.error ~ label { color: var(--cfao-red); }
        .field-error {
            display: none;
            font-size: .78rem;
            color: var(--cfao-red);
            margin-top: .35rem;
            padding-left: 4px;
        }
        .field-error.visible { display: block; }

        /* ── Submit button ── */
        .btn-submit {
            width: 100%;
            height: 52px;
            margin-top: 1.6rem;
            background: var(--cfao-red);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: .95rem;
            font-weight: 700;
            letter-spacing: .4px;
            cursor: pointer;
            transition: background .2s, transform .15s, box-shadow .2s;
            box-shadow: 0 8px 20px rgba(215,0,6,.28);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
        }
        .btn-submit:hover  { background: #b50005; transform: translateY(-1px); box-shadow: 0 12px 28px rgba(215,0,6,.38); }
        .btn-submit:active { transform: translateY(0); }
        .btn-submit:disabled { opacity: .55; cursor: not-allowed; transform: none; }

        /* ── No-type screen ── */
        .no-type-screen {
            text-align: center;
            color: rgba(255,255,255,.85);
            padding: 3rem 1rem;
        }
        .no-type-screen .fa { font-size: 3.5rem; margin-bottom: 1rem; opacity: .7; }
        .no-type-screen h2 { font-size: 1.3rem; font-weight: 700; margin-bottom: .5rem; }
        .no-type-screen p  { font-size: .9rem; opacity: .8; }

        /* ── Footer note ── */
        .footer-note {
            text-align: center;
            color: rgba(255,255,255,.5);
            font-size: .75rem;
            margin-top: 1.5rem;
        }
    </style>
</head>
<body>
<div class="register-wrapper">

    <?php if (!$config): ?>
    <!-- Lien invalide ou manquant -->
    <div class="no-type-screen">
        <div><i class="fas fa-exclamation-triangle"></i></div>
        <h2>Lien invalide</h2>
        <p>Ce lien de questionnaire n'est pas valide. Veuillez utiliser le lien fourni par votre responsable.</p>
    </div>

    <?php else: ?>
    <!-- Card unique -->
    <div class="register-card">
        <!-- Ligne : logo gauche | titre centre | logo droit -->
        <div class="card-top">
            <img src="../public/images/logo-cfao.png" alt="CFAO Academy" class="cfao-logo"
                 onerror="this.onerror=null;this.src='../public/images/logo.png';">

            <div class="card-top-center">
                <h2>Vos informations</h2>
                <span class="brand-badge"><i class="fas fa-shield-alt"></i> Session sécurisée</span>
            </div>

            <img src="<?php echo htmlspecialchars($config['logo'], ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($config['label'], ENT_QUOTES); ?>" class="brand-logo">
        </div>

        <p class="intro-text">Saisissez votre adresse email pour accéder au questionnaire.</p>

        <form method="POST" action="?type=<?php echo urlencode($type); ?>" novalidate id="regForm">

            <!-- Email -->
            <div class="field-group">
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder=" "
                    value="<?php echo htmlspecialchars($formData['email'], ENT_QUOTES, 'UTF-8'); ?>"
                    autocomplete="email"
                    class="<?php echo $fieldErrors['email'] ? 'error' : ($formData['email'] !== '' ? 'valid' : ''); ?>"
                    autofocus
                >
                <label for="email">Adresse email</label>
                <div class="field-error <?php echo $fieldErrors['email'] ? 'visible' : ''; ?>">
                    <?php echo htmlspecialchars($fieldErrors['email'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">
                <i class="fas fa-arrow-right"></i> Accéder au questionnaire
            </button>
        </form>
    </div>

    <div class="footer-note">
        &copy; <?php echo date('Y'); ?> CFAO Academy &mdash; Tous droits réservés
    </div>
    <?php endif; ?>

</div>

<script>
document.getElementById('regForm') && document.getElementById('regForm').addEventListener('submit', function() {
    var btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Chargement...';
});
</script>
</body>
</html>
