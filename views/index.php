<?php
require_once __DIR__ . '/../core/medacap_config.php';
session_start();
include_once "language.php";

require_once __DIR__ . "/../vendor/autoload.php";

// Create connection
$conn = new MongoDB\Client(medacapMongoUri());

// Connecting in database
$academy = $conn->academy;

// Connecting in collections
$users = $academy->users;
$connections = $academy->connections;

// Priorité : ?lang=  →  session  →  cookie  →  fr
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en','fr'])) {
    $_SESSION['lang'] = $_GET['lang'];
    setcookie('lang', $_GET['lang'], time() + 31536000, '/');
}

$lang = $_SESSION['lang'] ?? ($_COOKIE['lang'] ?? 'fr');

if ($lang === 'en') {
    include_once "language_EN.php";
} else {
    include_once "language.php";
}

$error_msg = '';
$magic_msg = '';
$magic_ok = isset($_GET['ok']);
$show_magic = isset($_GET['lien']) || $magic_ok;
$magic_motif = isset($_POST['magic_motif']) ? (string) $_POST['magic_motif'] : (isset($_GET['motif']) ? (string) $_GET['motif'] : '');
if ($magic_motif !== 'mdp') {
    $magic_motif = 'mail';
}
if ($lang === 'en') {
    $magic_link_label = 'Sign in with your email';
    $magic_forgot_label = 'Forgot your password?';
    $magic_or = 'or';
    $magic_title = 'Sign in with your email';
    $magic_intro = 'Get a 6-digit code (10 min) and a sign-in link (30 min). No password needed.';
    $magic_email_placeholder = 'Your email address';
    $magic_submit_label = 'Send me the code';
    $magic_back_label = 'Back to password sign-in';
    $magic_sent_title = 'Check your inbox';
    $magic_sent_to = 'Request received for';
    $magic_resend_label = 'Send again';
    $magic_ok_text = 'If an active account matches this address, you will receive an email with a 6-digit code and a link (check your junk folder too). Nothing after 5 minutes? Write to jakaa-ext@cfao.com.';
    $magic_invalid_text = 'Enter a valid email address.';
    $magic_fail_text = 'The message could not be sent. Try again or write to jakaa-ext@cfao.com.';
    $code_label = 'Code from the email';
    $code_hint = 'Reading the email on your phone? Type the code here. Only the latest code works, for 10 minutes.';
    $code_submit_label = 'Verify code';
    $code_error_text = 'Incorrect or expired code. Check the latest email, or request a new code.';
    $code_have_label = 'I already have a code';
} else {
    $magic_link_label = 'Connectez-vous avec votre mail';
    $magic_forgot_label = 'Mot de passe oublié ?';
    $magic_or = 'ou';
    $magic_title = 'Connectez-vous avec votre mail';
    $magic_intro = 'Recevez un code à 6 chiffres (10 min) et un lien (30 min), sans mot de passe.';
    $magic_email_placeholder = 'Votre adresse mail';
    $magic_submit_label = 'Recevoir le code';
    $magic_back_label = 'Retour à la connexion par mot de passe';
    $magic_sent_title = 'Vérifiez votre boîte mail';
    $magic_sent_to = 'Demande enregistrée pour';
    $magic_resend_label = 'Renvoyer';
    $magic_ok_text = 'Si un compte actif correspond à cette adresse, vous recevrez un mail avec un code à 6 chiffres et un lien (pensez aux courriers indésirables). Rien reçu sous 5 minutes ? Écrivez à jakaa-ext@cfao.com.';
    $magic_invalid_text = 'Indiquez une adresse mail valide.';
    $magic_fail_text = 'L\'envoi a échoué. Réessayez, ou écrivez à jakaa-ext@cfao.com.';
    $code_label = 'Code reçu par mail';
    $code_hint = 'Mail lu sur votre téléphone ? Tapez le code ici. Seul le dernier code reçu est valable, 10 minutes.';
    $code_submit_label = 'Valider le code';
    $code_error_text = 'Code incorrect ou expiré. Vérifiez le dernier mail reçu, ou demandez un nouveau code.';
    $code_have_label = 'J\'ai déjà un code';
}
// ?code=1 : saisie d'un code reçu, depuis un navigateur qui n'a pas fait la demande
if (isset($_GET['code'])) {
    $magic_ok = true;
    $show_magic = true;
}
if ($magic_ok) {
    $magic_msg = $magic_ok_text;
}
$code_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['magic_submit'])) {
    $show_magic = true;
    $email = strtolower(trim((string) (isset($_POST['magic_email']) ? $_POST['magic_email'] : '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $magic_msg = $magic_invalid_text;
    } else {
        require_once __DIR__ . '/sendMail.php';
        require_once __DIR__ . '/../core/login_links.php';
        $matches = medacapFindActiveUsersByEmail($users, $email);
        if (count($matches) !== 1) {
            // Même réponse qu'un envoi réussi : ne pas révéler l'existence du compte
            error_log('login link: ' . count($matches) . ' active account(s) for ' . $email);
            sendLoginSupportAlert($academy, $email, $magic_motif, count($matches) === 0 ? 'none' : 'shared', $matches, $lang === 'en' ? 'en' : 'fr');
            $magic_msg = $magic_ok_text;
            $magic_ok = true;
            $_SESSION['login_code_email'] = $email;
        } else {
            $account = $matches[0];
            $links = $academy->login_links;
            $since = new MongoDB\BSON\UTCDateTime((time() - 15 * 60) * 1000);
            $recent = $links->count(array(
                'user' => $account['_id'],
                'created' => array('$gte' => $since),
            ));
            if ($recent >= 3) {
                $magic_msg = $magic_ok_text;
                $magic_ok = true;
                $_SESSION['login_code_email'] = $email;
            } else {
                $issued = medacapIssueLoginLink($links, $account['_id'], 30 * 60, 10 * 60);
                $link = medacapLoginLinkBase($account) . '/views/loginLink.php?t=' . $issued['token'];
                $gender = strtolower(trim((string) (isset($account['gender']) ? $account['gender'] : '')));
                $isFemale = ($gender === 'feminin' || $gender === 'féminin');
                $prefix = $lang === 'en' ? ($isFemale ? 'Ms' : 'Mr') : ($isFemale ? 'Mme' : 'M.');
                $lastName = trim((string) (isset($account['lastName']) ? $account['lastName'] : ''));
                $greetingName = $lastName !== '' ? $prefix . ' ' . $lastName : trim((string) $account['firstName']);
                $sent = sendLoginLinkEmail($email, $greetingName, $link, $lang === 'en' ? 'en' : 'fr', $issued['code']);
                if ($sent) {
                    $magic_msg = $magic_ok_text;
                    $magic_ok = true;
                    $_SESSION['login_code_email'] = $email;
                } else {
                    $links->deleteOne(array('_id' => $issued['id']));
                    sendLoginSupportAlert($academy, $email, $magic_motif, 'send_failed', array($account), $lang === 'en' ? 'en' : 'fr');
                    $magic_msg = $magic_fail_text;
                }
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['code_submit'])) {
    require_once __DIR__ . '/../core/login_links.php';
    $show_magic = true;
    $magic_ok = true;
    $magic_msg = $magic_ok_text;
    $email = isset($_SESSION['login_code_email'])
        ? (string) $_SESSION['login_code_email']
        : strtolower(trim((string) (isset($_POST['code_email']) ? $_POST['code_email'] : '')));
    $code = medacapNormalizeLoginCode(isset($_POST['login_code']) ? $_POST['login_code'] : '');
    // Saisie mal formée : refusée sans consommer d'essai
    $login = ($code !== null && filter_var($email, FILTER_VALIDATE_EMAIL)) ? medacapConsumeLoginCode($academy, $email, $code) : null;
    if (!$login) {
        $code_error = $code_error_text;
    } else {
        unset($_SESSION['login_code_email']);
        require_once __DIR__ . '/../core/login_session.php';
        medacapOpenLoginSession($login, $connections);
        $landing = medacapLoginLandingPath($login, $academy);
        if (basename(dirname($_SERVER['SCRIPT_NAME'] ?? '')) === 'views' && strpos($landing, 'views/') === 0) {
            $landing = substr($landing, strlen('views/'));
        }
        header('Location: ' . $landing);
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userName = trim($_POST["username"]);
    $password = trim($_POST["password"]); // No sha1, assuming plain text in DB
//    $selectedProfile = isset($_POST["selected_profile"]) ? $_POST["selected_profile"] : null;

    $data = [
        "username" => $userName,
        "password" => sha1($_POST["password"]),
    ];

    $login = $users->findOne($data);

    // Identifiant introuvable : accepter l'adresse e-mail, si un seul compte la porte avec ce mot de passe
    if (empty($login) && strpos($userName, '@') !== false) {
        $byEmail = iterator_to_array($users->find(
            array(
                'email' => new MongoDB\BSON\Regex('^' . preg_quote($userName, '/') . '$', 'i'),
                'password' => sha1($_POST["password"]),
            ),
            array('limit' => 2)
        ));
        if (count($byEmail) === 1) {
            $login = reset($byEmail);
        } elseif (count($byEmail) > 1) {
            error_log('login: ' . count($byEmail) . ' accounts share email and password for ' . $userName);
        }
    }

    if (empty($login)) {
        $error_msg = $error_username_pass;
    } elseif ($login->active == false) {
        $error_msg = $error_authentication;
    } else {
        if (!empty($selectedProfile)) {
            $profileData = [
                "username" => $userName,
                "password" => $password,
                "firstName" => $login["firstName"],
                "lastName" => $login["lastName"],
                "profile" => $selectedProfile,
                "active" => true
            ];

            $profileLogin = $users->findOne($profileData);
            if ($profileLogin) {
                $login = $profileLogin;
            }
        }
        require_once __DIR__ . '/../core/email_gate.php';
        if (medacapEmailGateNeeded($login)) {
            // Pas de session tant que l'adresse n'est pas ajoutée (ou « Plus tard »)
            session_regenerate_id(true);
            unset($_SESSION['id']);
            $_SESSION['email_gate'] = array('user' => (string) $login['_id'], 'since' => time());
            $gate = basename(dirname($_SERVER['SCRIPT_NAME'] ?? '')) === 'views' ? 'addEmail.php' : 'views/addEmail.php';
            header('Location: ' . $gate);
            exit;
        }
        require_once __DIR__ . '/../core/login_session.php';
        medacapOpenLoginSession($login, $connections);
        $landing = medacapLoginLandingPath($login, $academy);
        // Chemin relatif à la racine : retirer "views/" quand la page est ouverte en /views/index.php.
        if (basename(dirname($_SERVER['SCRIPT_NAME'] ?? '')) === 'views' && strpos($landing, 'views/') === 0) {
            $landing = substr($landing, strlen('views/'));
        }
        header('Location: ' . $landing);
        exit;
    }
}

$magic_email_value = (isset($_POST['magic_submit']) && isset($email)) ? (string) $email : '';
$code_session_email = isset($_SESSION['login_code_email']) ? (string) $_SESSION['login_code_email'] : '';
$magic_sent_email = '';
$masked_source = $magic_email_value !== '' ? $magic_email_value : $code_session_email;
if ($magic_ok && $masked_source !== '' && strpos($masked_source, '@') !== false) {
    $magic_sent_email = substr($masked_source, 0, 1) . '•••' . substr($masked_source, strpos($masked_source, '@'));
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CFAO Academy - <?php echo htmlspecialchars($title); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, rgba(36, 35, 92, 1), rgba(230, 34, 68, 1));
            --secondary-gradient: linear-gradient(135deg, rgba(229, 85, 79, 1), rgba(54, 60, 90, 1));
            --button-gradient: linear-gradient(135deg, rgba(230, 34, 68, 1), rgba(229, 85, 79, 1));
            --button-alt-gradient: linear-gradient(135deg, rgba(54, 60, 90, 1), rgba(36, 35, 92, 1));
            --button-shadow: 0 10px 20px -8px rgba(230, 34, 68, 0.45);
            --button-alt-shadow: 0 10px 20px -8px rgba(54, 60, 90, 0.4);
            --color-0: rgb(36, 35, 92);
            --color-1: rgb(230, 34, 68);
            --color-2: rgb(229, 85, 79);
            --color-3: rgb(54, 60, 90);
            --bg-overlay: linear-gradient(135deg, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0.1));
            --panel-width: 32rem;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --transparency-bg: rgba(255, 255, 255, 0.2);
            --transparency-border: rgba(255, 255, 255, 0.18);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-overlay);
            color: white; overflow-x: hidden; min-height: 100vh;
            position: relative;
            display: flex; flex-direction: column;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 110%;
            height: 110%;
            background: url('public/images/backgrounds/home.png') center/cover no-repeat fixed;
            filter: blur(8px);
            z-index: -10;
            transform: translate(-5%, -5%);
        }

        @keyframes parallaxFloat1 {
            0% { transform: translate(0, 0) rotate(0deg); }
            25% { transform: translate(60px, -80px) rotate(45deg); }
            50% { transform: translate(-40px, 120px) rotate(-30deg); }
            75% { transform: translate(80px, -60px) rotate(60deg); }
            100% { transform: translate(0, 0) rotate(0deg); }
        }

        @keyframes parallaxFloat2 {
            0% { transform: translate(0, 0) scale(1) rotate(0deg); }
            33% { transform: translate(100px, -70px) scale(1.1) rotate(120deg); }
            66% { transform: translate(-80px, 90px) scale(0.9) rotate(-90deg); }
            100% { transform: translate(0, 0) scale(1) rotate(0deg); }
        }

        @keyframes parallaxFloat3 {
            0% { transform: translate(0, 0) rotate(0deg); }
            20% { transform: translate(-60px, -100px) rotate(-60deg); }
            40% { transform: translate(90px, 40px) rotate(90deg); }
            60% { transform: translate(-30px, 140px) rotate(-120deg); }
            80% { transform: translate(70px, -90px) rotate(150deg); }
            100% { transform: translate(0, 0) rotate(0deg); }
        }

        .parallax-shapes { position: fixed; inset: 0; pointer-events: none; z-index: -1; }
        .parallax-shape {
            position: absolute; animation: var(--anim-name) var(--duration) ease-in-out infinite; animation-delay: var(--delay); opacity: var(--opacity); filter: var(--filter);
        }

        header {
            position: sticky; top: 0; z-index: 50; padding: 0.5rem 1rem;
            backdrop-filter: blur(10px);
            background: var(--transparency-bg);
            border-bottom: 1px solid var(--transparency-border);
        }
        nav { max-width: 1400px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; }
        .logo-section { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; transition: var(--transition); }
        .logo-section:hover { transform: scale(1.05); }
        .logo { width: 3rem; height: 3rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .logo img { width: 100%; height: 100%; object-fit: contain; }
        .brand-name { font-size: 1.25rem; font-weight: 700; color: white; }

        .language-toggle {
            display: flex; gap: 0;
            background: var(--transparency-bg);
            border-radius: 999px; overflow: hidden;
        }
        .lang-btn {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            border: none;
            background: transparent;
            color: rgba(255,255,255,0.8);
            cursor: pointer;
            transition: var(--transition);
        }
        .lang-btn.active, .lang-btn:hover { background: var(--transparency-bg); color: white; }

        .main-layout {
            display: flex;
            justify-content: center;
            align-items: center;
            flex: 1;
            padding: 1rem;
        }
        .right-panel {
            width: 100%;
            max-width: var(--panel-width);
        }

        .login-card {
            padding: 2rem 2.75rem;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.7);
            box-shadow: 0 15px 30px -10px rgba(36, 35, 92, 0.35);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .login-card .logo-section { justify-content: center; margin-bottom: 0.75rem; }
        .login-card .logo { width: 4rem; height: 4rem; border-radius: 0.75rem; }
        .login-card .brand-name {
            font-size: 1.5rem;
            font-weight: bold;
            color: white;
        }
        .greeting { color: white; font-weight: 500; text-align: center; margin-bottom: 0.25rem; opacity: 0.9; }
        .login-card form { display: flex; flex-direction: column; gap: 1rem; margin-top: 1.25rem; }
        .form-group { position: relative; }
        .login-card input {
            appearance: none;
            width: 100%;
            padding: 0.75rem 3rem 0.75rem 1rem;
            border: none;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 0.5rem;
            color: #374151;
            font-size: 0.875rem;
            transition: all 0.2s;
        }
        .login-card input::placeholder { color: rgba(107, 114, 128, 0.8); }
        .login-card input:focus { outline: none; box-shadow: 0 0 0 2px rgba(230, 34, 68, 0.2); border-color: rgba(230, 34, 68, 0.3); }
        .login-card label {
            position: absolute;
            left: -10000px;
            top: auto;
            width: 1px;
            height: 1px;
            overflow: hidden;
        }
        .error {
            padding: 0.75rem;
            border-radius: 0.5rem;
            background: rgba(230, 34, 68, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(230, 34, 68, 0.3);
            margin-bottom: 1rem;
        }
        .error p { font-size: 0.875rem; font-weight: 500; text-align: center; color: rgb(230, 34, 68); margin: 0; }
        .magic-note {
            padding: 0.75rem;
            border-radius: 0.5rem;
            background: rgba(255, 255, 255, 0.72);
            margin: 1rem 0 0 0;
        }
        .magic-note p { font-size: 0.875rem; font-weight: 500; text-align: center; color: rgb(36, 35, 92); margin: 0; }
        .magic-note.ok p { color: rgb(22, 101, 52); }
        .magic-back, .magic-links a {
            color: white;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            background: rgba(36, 35, 92, 0.65);
            padding: 0.45rem 0.9rem;
            border-radius: 9999px;
            transition: var(--transition);
        }
        .magic-back:hover, .magic-links a:hover { background: rgba(36, 35, 92, 0.85); }
        .magic-back { display: table; margin: 1rem auto 0; }
        .magic-forgot {
            align-self: flex-end;
            margin-top: -0.5rem;
            color: white;
            font-size: 0.8125rem;
            font-weight: 600;
            text-decoration: underline;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.7);
        }
        .magic-or {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-top: 1rem;
            color: white;
            font-size: 0.8125rem;
            opacity: 0.9;
        }
        .magic-or::before, .magic-or::after { content: ''; flex: 1; height: 1px; background: rgba(255, 255, 255, 0.7); }
        .magic-alt {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 0.75rem;
            padding: 0.75rem 1rem;
            border: 1.5px solid white;
            border-radius: 9999px;
            color: white;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
        }
        .magic-alt:hover { background: rgba(255, 255, 255, 0.18); }
        .magic-title { color: white; font-weight: 700; font-size: 1.125rem; text-align: center; margin-bottom: 0.5rem; }
        .magic-intro { color: white; font-size: 0.875rem; text-align: center; opacity: 0.9; }
        .magic-sent {
            margin-top: 1rem;
            padding: 1.5rem 1.25rem;
            border-radius: 0.75rem;
            background: rgba(255, 255, 255, 0.72);
            text-align: center;
            color: rgb(36, 35, 92);
        }
        .magic-sent i { font-size: 2rem; color: rgb(22, 101, 52); }
        .magic-sent-title { font-weight: 700; font-size: 1.0625rem; margin: 0.5rem 0 0.75rem; }
        .magic-sent p { font-size: 0.875rem; margin-top: 0.25rem; }
        .magic-links { display: flex; justify-content: center; flex-wrap: wrap; gap: 0.5rem; margin-top: 1rem; }
        .submit-btn {
            padding: 0.875rem 1rem;
            font-size: 1rem;
            font-weight: bold;
            background: var(--button-gradient);
            border: none;
            border-radius: 9999px;
            color: white;
            cursor: pointer;
            transition: var(--transition);
            width: 100%;
            margin-top: 0.25rem;
            box-shadow: var(--button-shadow);
        }
        .submit-btn:hover:not(:disabled) { transform: scale(1.05); }
        .submit-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .password-viewer {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: rgba(0, 0, 0, 0.7);
            z-index: 10;
            font-size: 1rem;
        }

        @media (max-width: 480px) {
            .login-card { padding: 1.5rem 1.25rem; }
            .brand-name { font-size: 1.1rem; }
        }
        @media (max-height: 720px) {
            .login-card { padding-top: 1.25rem; padding-bottom: 1.25rem; }
            .login-card .logo { width: 3rem; height: 3rem; }
            .login-card form { margin-top: 0.75rem; }
            .greeting { font-size: 0.9375rem; }
            footer { padding: 0.5rem; }
        }

        footer {
            text-align: center; padding: 0.75rem;
            background: var(--transparency-bg);
            backdrop-filter: blur(10px);
            color: rgba(255,255,255,0.7);
            font-size: 0.875rem;
            border-top: 1px solid var(--transparency-border);
        }
    </style>
</head>
<body>
<div class="parallax-shapes" id="parallax-shapes"></div>

<header>
    <nav>
        <div class="logo-section" onclick="location.reload()">
            <div class="logo"><img src="public/images/logos/cfao_academy.jpg" alt="CFAO Academy"></div>
            <span class="brand-name"><?php echo htmlspecialchars(mb_strtoupper($title)); ?></span>
        </div>
        <div class="language-toggle">
            <button class="lang-btn <?php echo ($lang === 'en') ? 'active' : ''; ?>" onclick="window.location.href='?lang=en'">EN</button>
            <button class="lang-btn <?php echo ($lang === 'fr') ? 'active' : ''; ?>" onclick="window.location.href='?lang=fr'">FR</button>
        </div>
    </nav>
</header>

<main class="main-layout">
    <aside class="right-panel">
        <div class="login-card">
            <div class="logo-section" onclick="location.reload()">
                <div class="logo"><img src="public/images/logos/cfao_academy.jpg" alt="CFAO Academy"></div>
                <span class="brand-name"><?php echo strtoupper(htmlspecialchars($title)); ?></span>
            </div>
            <?php if (!$show_magic): ?>
            <p class="greeting"><?php echo htmlspecialchars($greeting); ?></p>
            <?php endif; ?>
            <?php $lang_q = $lang === 'en' ? 'en' : 'fr'; ?>
            <?php if (!$show_magic): ?>
            <?php if ($error_msg): ?>
                <div class="error">
                    <p><?php echo htmlspecialchars($error_msg); ?></p>
                </div>
            <?php endif; ?>
            <form method="POST" id="loginForm">
                <div class="form-group">
                    <input id="username" name="username" type="text" autocomplete="username" required placeholder="<?php echo htmlspecialchars($usernamePlaceholder); ?>">
                </div>
                <div class="form-group">
                    <input id="password" name="password" type="password" autocomplete="current-password" required placeholder="<?php echo htmlspecialchars($passwordPlaceholder); ?>">
                    <span class="password-viewer" onclick="togglePasswordVisibility()" title="<?php echo htmlspecialchars($show_password); ?>">
                        <i class="bi bi-eye"></i>
                    </span>
                </div>
                <a class="magic-forgot" href="?lien=1&motif=mdp&lang=<?php echo $lang_q; ?>"><?php echo htmlspecialchars($magic_forgot_label); ?></a>
                <button type="submit" name="login" class="submit-btn" id="submitBtn"><?php echo htmlspecialchars($acceder); ?></button>
            </form>
            <div class="magic-or"><?php echo htmlspecialchars($magic_or); ?></div>
            <a class="magic-alt" href="?lien=1&motif=mail&lang=<?php echo $lang_q; ?>"><i class="bi bi-envelope"></i><?php echo htmlspecialchars($magic_link_label); ?></a>
            <?php elseif ($magic_ok): ?>
                <div class="magic-sent">
                    <i class="bi bi-envelope-check"></i>
                    <p class="magic-sent-title"><?php echo htmlspecialchars($magic_sent_title); ?></p>
                    <?php if ($magic_sent_email !== ''): ?>
                        <p><?php echo htmlspecialchars($magic_sent_to); ?> <strong><?php echo htmlspecialchars($magic_sent_email); ?></strong></p>
                    <?php endif; ?>
                    <p><?php echo htmlspecialchars($magic_ok_text); ?></p>
                </div>
                <?php if ($code_error): ?>
                    <div class="error">
                        <p><?php echo htmlspecialchars($code_error); ?></p>
                    </div>
                <?php endif; ?>
                <form method="POST" id="codeForm">
                    <p class="magic-intro"><?php echo htmlspecialchars($code_hint); ?></p>
                    <?php if ($code_session_email === ''): ?>
                    <div class="form-group">
                        <input id="code_email" name="code_email" type="email" autocomplete="email" required placeholder="<?php echo htmlspecialchars($magic_email_placeholder); ?>" value="<?php echo htmlspecialchars(isset($_POST['code_email']) ? (string) $_POST['code_email'] : ''); ?>">
                    </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <input id="login_code" name="login_code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required autofocus placeholder="<?php echo htmlspecialchars($code_label); ?>">
                    </div>
                    <button type="submit" name="code_submit" class="submit-btn" value="1"><?php echo htmlspecialchars($code_submit_label); ?></button>
                </form>
                <p class="magic-links">
                    <a href="?lien=1&motif=<?php echo $magic_motif; ?>&lang=<?php echo $lang_q; ?>"><?php echo htmlspecialchars($magic_resend_label); ?></a>
                    <a href="?lang=<?php echo $lang_q; ?>"><?php echo htmlspecialchars($magic_back_label); ?></a>
                </p>
            <?php else: ?>
                <p class="magic-title"><?php echo htmlspecialchars($magic_title); ?></p>
                <p class="magic-intro"><?php echo htmlspecialchars($magic_intro); ?></p>
                <?php if ($magic_msg): ?>
                    <div class="magic-note">
                        <p><?php echo htmlspecialchars($magic_msg); ?></p>
                    </div>
                <?php endif; ?>
                <form method="POST" id="magicForm">
                    <input type="hidden" name="magic_motif" value="<?php echo $magic_motif; ?>">
                    <div class="form-group">
                        <input id="magic_email" name="magic_email" type="email" autocomplete="email" required autofocus placeholder="<?php echo htmlspecialchars($magic_email_placeholder); ?>" value="<?php echo htmlspecialchars($magic_email_value); ?>">
                    </div>
                    <button type="submit" name="magic_submit" class="submit-btn" value="1"><?php echo htmlspecialchars($magic_submit_label); ?></button>
                </form>
                <p class="magic-links">
                    <a href="?code=1&motif=<?php echo $magic_motif; ?>&lang=<?php echo $lang_q; ?>"><?php echo htmlspecialchars($code_have_label); ?></a>
                </p>
                <a class="magic-back" href="?lang=<?php echo $lang_q; ?>">&larr; <?php echo htmlspecialchars($magic_back_label); ?></a>
            <?php endif; ?>
        </div>
    </aside>
</main>

<footer><?php echo htmlspecialchars($footer); ?></footer>

<script>
    const shapesContainer = document.getElementById('parallax-shapes');
    const animations = ['parallaxFloat1', 'parallaxFloat2', 'parallaxFloat3'];
    const colors = [
        {r: 36, g: 35, b: 92}, {r: 230, g: 34, b: 68},
        {r: 229, g: 85, b: 79}, {r: 54, g: 60, b: 90}
    ];

    for (let i = 0; i < 30; i++) {
        const size = 20 + Math.random() * 80;
        const animName = animations[Math.floor(Math.random() * animations.length)];
        const duration = (15 + Math.random() * 20) + 's';
        const opacity = 0.12 + Math.random() * 0.25;
        const blur = Math.random() > 0.4 ? Math.random() * 2 + 'px' : '0px';
        const left = Math.random() * 100 + '%';
        const top = Math.random() * 100 + '%';
        const baseColor = colors[i % colors.length];
        const nextColor = colors[(i + 1) % colors.length];
        const bgGradient = `linear-gradient(135deg, rgba(${baseColor.r},${baseColor.g},${baseColor.b},0.65), rgba(${nextColor.r},${nextColor.g},${nextColor.b},0.65))`;
        const filter = `blur(${blur}) drop-shadow(0 0 15px rgba(${baseColor.r},${baseColor.g},${baseColor.b},0.25))`;
        const borderRadius = i % 2 === 0 ? '50%' : '10%';
        const delay = (i * 0.4) + 's';

        const shape = document.createElement('div');
        shape.className = 'parallax-shape';
        shape.style.width = size + 'px';
        shape.style.height = size + 'px';
        shape.style.left = left;
        shape.style.top = top;
        shape.style.background = bgGradient;
        shape.style.borderRadius = borderRadius;
        shape.style.animation = `${animName} ${duration} ease-in-out infinite`;
        shape.style.animationDelay = delay;
        shape.style.opacity = opacity;
        shape.style.filter = filter;
        shape.style.setProperty('--anim-name', animName);
        shape.style.setProperty('--duration', duration);
        shape.style.setProperty('--delay', delay);
        shape.style.setProperty('--opacity', opacity);
        shape.style.setProperty('--filter', filter);
        shape.style.pointerEvents = 'none';

        shapesContainer.appendChild(shape);
    }

    function togglePasswordVisibility() {
        const passwordInput = document.getElementById("password");
        const eyeIcon = document.querySelector(".password-viewer i");
        const viewerSpan = document.querySelector(".password-viewer");
        if (passwordInput.type === "password") {
            passwordInput.type = "text";
            eyeIcon.classList.remove("bi-eye");
            eyeIcon.classList.add("bi-eye-slash");
            viewerSpan.title = "<?php echo htmlspecialchars($hide_password ?? 'Masquer le mot de passe'); ?>";
        } else {
            passwordInput.type = "password";
            eyeIcon.classList.remove("bi-eye-slash");
            eyeIcon.classList.add("bi-eye");
            viewerSpan.title = "<?php echo htmlspecialchars($show_password ?? 'Afficher le mot de passe'); ?>";
        }
    }

    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', () => {
            const btn = document.getElementById('submitBtn');
            btn.textContent = '<?php echo htmlspecialchars($loadingButton); ?>';
            btn.disabled = true;
        });
    }
</script>
</body>
</html>