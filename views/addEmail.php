<?php
/**
 * Après une connexion par mot de passe d'un compte sans adresse : demande une
 * adresse @cfao.com, envoie un code dessus, l'enregistre une fois le code saisi,
 * puis ouvre la session. Le technicien peut passer (« Plus tard »).
 */
require_once __DIR__ . '/../core/medacap_config.php';
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/login_session.php';
require_once __DIR__ . '/../core/email_gate.php';

$academy = (new MongoDB\Client(medacapMongoUri()))->academy;

$gate = isset($_SESSION['email_gate']) && is_array($_SESSION['email_gate']) ? $_SESSION['email_gate'] : null;
$user = null;
if ($gate && isset($gate['since']) && time() - (int) $gate['since'] < MEDACAP_EMAIL_GATE_TTL) {
    try {
        $user = $academy->users->findOne(array('_id' => new MongoDB\BSON\ObjectId((string) $gate['user']), 'active' => true));
    } catch (Throwable $e) {
        error_log('addEmail: ' . get_class($e) . ' ' . $e->getMessage());
    }
}
if (!$user) {
    unset($_SESSION['email_gate']);
    header('Location: ../');
    exit;
}

function addEmailOpenSession($user, $academy)
{
    unset($_SESSION['email_gate']);
    medacapOpenLoginSession($user, $academy->connections);
    $path = medacapLoginLandingPath($user, $academy);
    if (strpos($path, 'views/') === 0) {
        $path = substr($path, strlen('views/'));
    }
    header('Location: ' . $path);
    exit;
}

// Adresse ajoutée entre-temps (autre onglet, support) : rien à demander
if (!medacapEmailGateNeeded($user)) {
    addEmailOpenSession($user, $academy);
}

$lang = isset($_SESSION['lang']) ? strtolower((string) $_SESSION['lang']) : (isset($_COOKIE['lang']) ? (string) $_COOKIE['lang'] : 'fr');
$isEn = $lang === 'en';
$t = $isEn ? array(
    'title' => 'Add your email address',
    'intro' => 'Your account has no email address. Add your CFAO address: you will be able to sign in without a password and reset it yourself.',
    'placeholder' => 'firstname.lastname@cfao.com',
    'send' => 'Send me a code',
    'code_intro' => 'A 6-digit code was sent to',
    'code_label' => 'Code from the email',
    'verify' => 'Confirm',
    'change' => 'Use another address',
    'resend' => 'Send again',
    'later' => 'Later',
    'logout' => 'Back to sign-in',
    'invalid' => 'Enter a valid email address.',
    'not_cfao' => 'Only @cfao.com addresses are accepted.',
    'taken' => 'This address is already used by another account. Write to jakaa-ext@cfao.com.',
    'limit' => 'Too many codes requested. Wait 15 minutes, or use the last code received.',
    'send_failed' => 'The email could not be sent. Try again, or write to jakaa-ext@cfao.com.',
    'bad_code' => 'Incorrect or expired code. Check the latest email, or request a new code.',
) : array(
    'title' => 'Ajoutez votre adresse mail',
    'intro' => 'Votre compte n\'a pas d\'adresse mail. Ajoutez votre adresse CFAO : vous pourrez vous connecter sans mot de passe et le réinitialiser vous-même.',
    'placeholder' => 'prenom.nom@cfao.com',
    'send' => 'Recevoir un code',
    'code_intro' => 'Un code à 6 chiffres a été envoyé à',
    'code_label' => 'Code reçu par mail',
    'verify' => 'Valider',
    'change' => 'Utiliser une autre adresse',
    'resend' => 'Renvoyer',
    'later' => 'Plus tard',
    'logout' => 'Retour à la connexion',
    'invalid' => 'Indiquez une adresse mail valide.',
    'not_cfao' => 'Seules les adresses @cfao.com sont acceptées.',
    'taken' => 'Cette adresse est déjà utilisée par un autre compte. Écrivez à jakaa-ext@cfao.com.',
    'limit' => 'Trop de codes demandés. Attendez 15 minutes, ou utilisez le dernier code reçu.',
    'send_failed' => 'L\'envoi a échoué. Réessayez, ou écrivez à jakaa-ext@cfao.com.',
    'bad_code' => 'Code incorrect ou expiré. Vérifiez le dernier mail reçu, ou demandez un nouveau code.',
);

$userId = $user['_id'];
$canSkip = medacapEmailGateCanSkip($user);
$pending = isset($gate['email']) ? (string) $gate['email'] : '';
$error = '';

/**
 * @return string message d'erreur, ou '' si le code est parti
 */
function addEmailSendCode($academy, $user, $email, $t, $isEn)
{
    $email = strtolower(trim((string) $email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $t['invalid'];
    }
    if (!medacapEmailGateAllowed($email)) {
        return $t['not_cfao'];
    }
    if (count(medacapFindActiveUsersByEmail($academy->users, $email)) > 0) {
        return $t['taken'];
    }
    $links = $academy->login_links;
    $since = new MongoDB\BSON\UTCDateTime((time() - 15 * 60) * 1000);
    if ($links->count(array('user' => $user['_id'], 'created' => array('$gte' => $since))) >= 3) {
        return $t['limit'];
    }
    require_once __DIR__ . '/sendMail.php';
    $issued = medacapIssueLoginLink($links, $user['_id'], 10 * 60, 10 * 60, 'email_verify');
    $links->updateOne(array('_id' => $issued['id']), array('$set' => array('email' => $email)));
    $greeting = trim((string) (isset($user['firstName']) ? $user['firstName'] : '') . ' ' . (string) (isset($user['lastName']) ? $user['lastName'] : ''));
    if (!sendEmailVerifyCodeEmail($email, $greeting, $issued['code'], $isEn ? 'en' : 'fr')) {
        $links->deleteOne(array('_id' => $issued['id']));
        return $t['send_failed'];
    }
    $_SESSION['email_gate']['email'] = $email;
    return '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
    if ($action === 'later' && $canSkip) {
        addEmailOpenSession($user, $academy);
    } elseif ($action === 'logout') {
        unset($_SESSION['email_gate']);
        header('Location: ../');
        exit;
    } elseif ($action === 'change') {
        unset($_SESSION['email_gate']['email']);
        $pending = '';
    } elseif ($action === 'send') {
        $error = addEmailSendCode($academy, $user, isset($_POST['email']) ? $_POST['email'] : '', $t, $isEn);
        $pending = isset($_SESSION['email_gate']['email']) ? (string) $_SESSION['email_gate']['email'] : '';
    } elseif ($action === 'resend' && $pending !== '') {
        $error = addEmailSendCode($academy, $user, $pending, $t, $isEn);
    } elseif ($action === 'verify' && $pending !== '') {
        $code = medacapNormalizeLoginCode(isset($_POST['code']) ? $_POST['code'] : '');
        if ($code === null || !medacapConsumeEmailCode($academy, $userId, $pending, $code)) {
            $error = $t['bad_code'];
        } elseif (count(medacapFindActiveUsersByEmail($academy->users, $pending)) > 0) {
            // Prise par un autre compte entre l'envoi et la saisie
            unset($_SESSION['email_gate']['email']);
            $pending = '';
            $error = $t['taken'];
        } else {
            $academy->users->updateOne(
                array('_id' => $userId),
                array('$set' => array('email' => $pending, 'updated' => date('d-m-Y H:i:s')))
            );
            $user = $academy->users->findOne(array('_id' => $userId));
            addEmailOpenSession($user, $academy);
        }
    }
}

$h = function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};
$input = 'width:100%;box-sizing:border-box;padding:12px 14px;border:1px solid #D1D5DB;border-radius:8px;font-size:16px;';
$primary = 'width:100%;background:#D70006;color:#fff;border:0;font-weight:700;font-size:15px;padding:12px 22px;border-radius:8px;cursor:pointer;';
$link = 'background:none;border:0;padding:0;color:#4B5563;text-decoration:underline;cursor:pointer;font-size:14px;';
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="<?php echo $isEn ? 'en' : 'fr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>MEDACAP - <?php echo $h($t['title']); ?></title>
    <link href="../public/images/logo-cfao.png" rel="icon">
</head>
<body style="margin:0;font-family:system-ui,sans-serif;background:#F4F6F8;color:#1F2937;">
<div style="max-width:440px;margin:10vh auto;padding:32px 28px;background:#fff;border-radius:12px;">
    <p style="font-size:20px;font-weight:700;margin:0 0 12px;"><?php echo $h($t['title']); ?></p>
    <?php if ($pending === ''): ?>
        <p style="font-size:15px;line-height:1.5;margin:0 0 20px;"><?php echo $h($t['intro']); ?></p>
        <form method="post" action="addEmail.php">
            <input type="hidden" name="action" value="send">
            <input name="email" type="email" required autocomplete="email" autofocus placeholder="<?php echo $h($t['placeholder']); ?>" value="<?php echo $h(isset($_POST['email']) ? $_POST['email'] : ''); ?>" style="<?php echo $input; ?>">
            <?php if ($error !== ''): ?><p id="gate-error" style="color:#B91C1C;font-size:14px;margin:10px 0 0;"><?php echo $h($error); ?></p><?php endif; ?>
            <button type="submit" style="<?php echo $primary; ?>margin-top:16px;"><?php echo $h($t['send']); ?></button>
        </form>
    <?php else: ?>
        <p style="font-size:15px;line-height:1.5;margin:0 0 20px;"><?php echo $h($t['code_intro']); ?> <strong><?php echo $h($pending); ?></strong>.</p>
        <form method="post" action="addEmail.php">
            <input type="hidden" name="action" value="verify">
            <label for="code" style="display:block;font-size:14px;margin-bottom:6px;"><?php echo $h($t['code_label']); ?></label>
            <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required autofocus maxlength="7" placeholder="123 456" style="<?php echo $input; ?>letter-spacing:4px;">
            <?php if ($error !== ''): ?><p id="gate-error" style="color:#B91C1C;font-size:14px;margin:10px 0 0;"><?php echo $h($error); ?></p><?php endif; ?>
            <button type="submit" style="<?php echo $primary; ?>margin-top:16px;"><?php echo $h($t['verify']); ?></button>
        </form>
        <form method="post" action="addEmail.php" style="display:flex;gap:16px;justify-content:center;margin-top:16px;">
            <button type="submit" name="action" value="resend" style="<?php echo $link; ?>"><?php echo $h($t['resend']); ?></button>
            <button type="submit" name="action" value="change" style="<?php echo $link; ?>"><?php echo $h($t['change']); ?></button>
        </form>
    <?php endif; ?>
    <form method="post" action="addEmail.php" style="display:flex;gap:16px;justify-content:center;margin-top:20px;padding-top:16px;border-top:1px solid #E5E7EB;">
        <?php if ($canSkip): ?>
            <button type="submit" name="action" value="later" style="<?php echo $link; ?>"><?php echo $h($t['later']); ?></button>
        <?php endif; ?>
        <button type="submit" name="action" value="logout" style="<?php echo $link; ?>"><?php echo $h($t['logout']); ?></button>
    </form>
</div>
</body>
</html>
