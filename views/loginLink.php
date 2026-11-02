<?php
require_once __DIR__ . '/../core/medacap_config.php';
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/login_session.php';

$conn = new MongoDB\Client(medacapMongoUri());
$academy = $conn->academy;

$token = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['t'])) {
    $token = (string) $_POST['t'];
} elseif (isset($_GET['t'])) {
    $token = (string) $_GET['t'];
}

function loginLinkFindValid($links, $token)
{
    if (strlen($token) !== 64 || !ctype_xdigit($token)) {
        return null;
    }
    try {
        return $links->findOne(array(
            'tokenHash' => hash('sha256', $token),
            'usedAt' => null,
            'expiresAt' => array('$gt' => new MongoDB\BSON\UTCDateTime()),
        ));
    } catch (Throwable $e) {
        error_log('loginLink: ' . get_class($e) . ' ' . $e->getMessage());
        return null;
    }
}

function loginLinkRefuse()
{
    $lang = isset($_COOKIE['lang']) ? $_COOKIE['lang'] : 'fr';
    $title = ($lang === 'en') ? 'This link is no longer valid.' : 'Ce lien n\'est plus valable.';
    $action = ($lang === 'en') ? 'Request a new link' : 'Demander un nouveau lien';
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>MEDACAP</title></head><body style="margin:0;font-family:system-ui,sans-serif;background:#F4F6F8;color:#1F2937;">'
        . '<div style="max-width:420px;margin:12vh auto;padding:32px 28px;background:#fff;border-radius:12px;text-align:center;">'
        . '<p style="font-size:18px;font-weight:700;margin:0 0 12px;">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<a href="../?lien=1" style="display:inline-block;margin-top:8px;background:#D70006;color:#fff;text-decoration:none;font-weight:700;padding:12px 22px;border-radius:8px;">'
        . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '</a></div></body></html>';
    exit;
}

$linkDoc = loginLinkFindValid($academy->login_links, $token);
if (!$linkDoc || empty($linkDoc['user'])) {
    loginLinkRefuse();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $lang = isset($_COOKIE['lang']) ? $_COOKIE['lang'] : 'fr';
    $text = ($lang === 'en')
        ? 'Click the button to open your MEDACAP session.'
        : 'Cliquez sur le bouton pour ouvrir votre session MEDACAP.';
    $button = ($lang === 'en') ? 'Open my session' : 'Ouvrir ma session';
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>MEDACAP</title></head><body style="margin:0;font-family:system-ui,sans-serif;background:#F4F6F8;color:#1F2937;">'
        . '<div style="max-width:420px;margin:12vh auto;padding:32px 28px;background:#fff;border-radius:12px;text-align:center;">'
        . '<p style="font-size:16px;margin:0 0 20px;">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<form method="post" action="loginLink.php">'
        . '<input type="hidden" name="t" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">'
        . '<button type="submit" style="background:#D70006;color:#fff;border:0;font-weight:700;padding:12px 22px;border-radius:8px;cursor:pointer;">'
        . htmlspecialchars($button, ENT_QUOTES, 'UTF-8') . '</button></form></div></body></html>';
    exit;
}

try {
    $now = new MongoDB\BSON\UTCDateTime();
    $result = $academy->login_links->updateOne(
        array(
            'tokenHash' => hash('sha256', $token),
            'usedAt' => null,
            'expiresAt' => array('$gt' => $now),
        ),
        array('$set' => array('usedAt' => $now, 'usedVia' => 'link'))
    );
    $modified = $result->getModifiedCount();
    $consumed = ($modified === 1 || ($modified === null && $result->getMatchedCount() === 1));
} catch (Throwable $e) {
    error_log('loginLink: ' . get_class($e) . ' ' . $e->getMessage());
    $consumed = false;
}

if (!$consumed) {
    loginLinkRefuse();
}
require_once __DIR__ . '/../core/login_links.php';
medacapRevokeOtherLoginLinks($academy->login_links, $linkDoc['user'], $linkDoc['_id']);

$user = $academy->users->findOne(array('_id' => $linkDoc['user'], 'active' => true));
if (!$user) {
    header('Location: ../?lien=1');
    exit;
}

medacapOpenLoginSession($user, $academy->connections);
$path = medacapLoginLandingPath($user, $academy);
if (strpos($path, 'views/') === 0) {
    $path = substr($path, strlen('views/'));
}
header('Location: ' . $path);
exit;
