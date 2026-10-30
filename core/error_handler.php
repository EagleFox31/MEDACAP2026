<?php
/**
 * Gestionnaire d'erreurs centralisé, chargé avant chaque script par auto_prepend_file (docker/Dockerfile).
 *
 * - Exception non rattrapée ou fatale : référence ERR-XXXXXX dans le journal, document dans `app_errors`,
 *   mail d'alerte (1 par erreur et par heure), réponse JSON success:false en AJAX, page propre sinon.
 * - Warnings / notices : journal PHP inchangé, plus un compteur par erreur dans `app_errors` (sans mail).
 *
 * Variables d'environnement : MEDACAP_ERROR_MAIL (destinataire, défaut jakaa-ext@cfao.com),
 * MEDACAP_ERROR_MAIL=off pour couper les mails.
 * Ne doit jamais lever d'exception lui-même : tout est sous try/catch (Throwable).
 */

if (defined('MEDACAP_ERROR_HANDLER')) {
    return;
}
define('MEDACAP_ERROR_HANDLER', 1);

require_once __DIR__ . '/medacap_config.php';

// Réserve libérée à l'arrêt : permet de traiter un « Allowed memory size exhausted »
$GLOBALS['medacapErrReserve'] = str_repeat(' ', 65536);
$GLOBALS['medacapErrWarnings'] = array();
$GLOBALS['medacapErrHandled'] = false;

function medacapErrRef()
{
    try {
        return 'ERR-' . strtoupper(bin2hex(random_bytes(3)));
    } catch (Throwable $e) {
        return 'ERR-' . strtoupper(substr(md5(uniqid('', true)), 0, 6));
    }
}

function medacapErrRelPath($file)
{
    $root = dirname(__DIR__) . '/';
    return strpos((string) $file, $root) === 0 ? substr($file, strlen($root)) : (string) $file;
}

/** Contexte de la requête, sans données POST (mots de passe). */
function medacapErrContext()
{
    $ctx = array(
        'sapi' => PHP_SAPI,
        'url' => PHP_SAPI === 'cli'
            ? implode(' ', isset($GLOBALS['argv']) ? $GLOBALS['argv'] : array())
            : (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : ''),
        'method' => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '',
        'userId' => '',
        'profile' => '',
        'userName' => '',
    );
    if (isset($_SESSION) && is_array($_SESSION)) {
        $ctx['userId'] = isset($_SESSION['id']) ? (string) $_SESSION['id'] : '';
        $ctx['profile'] = isset($_SESSION['profile']) ? (string) $_SESSION['profile'] : '';
        $ctx['userName'] = trim((isset($_SESSION['firstName']) ? $_SESSION['firstName'] : '') . ' '
            . (isset($_SESSION['lastName']) ? $_SESSION['lastName'] : ''));
    }
    return $ctx;
}

function medacapErrManager()
{
    static $manager = null;
    if ($manager === null) {
        $manager = new MongoDB\Driver\Manager(medacapMongoUri(), array(
            'serverSelectionTimeoutMS' => 2000,
            'connectTimeoutMS' => 2000,
            'socketTimeoutMS' => 3000,
        ));
    }
    return $manager;
}

/**
 * Un document par erreur (empreinte classe + fichier + ligne) : compteur, dernières occurrences.
 * Renvoie true si le mail d'alerte doit partir (aucun envoi pour cette empreinte depuis 1 h).
 */
function medacapErrFingerprint($class, $file, $line)
{
    return md5($class . '|' . $file . '|' . $line);
}

function medacapErrAddUpsert($bulk, $level, $class, $message, $file, $line, $ref, $ctx, $trace, $count)
{
    $now = new MongoDB\BSON\UTCDateTime();
    $fingerprint = medacapErrFingerprint($class, $file, $line);
    $occurrence = array(
        'ref' => $ref, 'at' => $now, 'url' => $ctx['url'], 'method' => $ctx['method'],
        'userId' => $ctx['userId'], 'profile' => $ctx['profile'], 'userName' => $ctx['userName'],
        'message' => mb_substr($message, 0, 1000),
    );
    $bulk->update(
        array('fingerprint' => $fingerprint),
        array(
            '$set' => array(
                'level' => $level, 'class' => $class, 'file' => $file, 'line' => (int) $line,
                'message' => mb_substr($message, 0, 1000), 'lastAt' => $now, 'lastRef' => $ref,
                'trace' => mb_substr($trace, 0, 8000),
            ),
            '$setOnInsert' => array('fingerprint' => $fingerprint, 'firstAt' => $now),
            '$inc' => array('count' => (int) $count),
            '$push' => array('occurrences' => array('$each' => array($occurrence), '$slice' => -20)),
        ),
        array('upsert' => true)
    );
}

function medacapErrStore($level, $class, $message, $file, $line, $ref, $ctx, $trace, $count)
{
    if (!extension_loaded('mongodb')) {
        return $level === 'fatal';
    }
    $fingerprint = medacapErrFingerprint($class, $file, $line);
    $now = new MongoDB\BSON\UTCDateTime();
    $bulk = new MongoDB\Driver\BulkWrite();
    medacapErrAddUpsert($bulk, $level, $class, $message, $file, $line, $ref, $ctx, $trace, $count);
    $manager = medacapErrManager();
    $manager->executeBulkWrite('academy.app_errors', $bulk);
    if ($level !== 'fatal') {
        return false;
    }
    // Verrou atomique : seul le premier à poser mailedAt dans l'heure envoie le mail
    $hourAgo = new MongoDB\BSON\UTCDateTime((time() - 3600) * 1000);
    $lock = new MongoDB\Driver\BulkWrite();
    $lock->update(
        array('fingerprint' => $fingerprint, '$or' => array(
            array('mailedAt' => array('$exists' => false)),
            array('mailedAt' => array('$lt' => $hourAgo)),
        )),
        array('$set' => array('mailedAt' => $now))
    );
    return $manager->executeBulkWrite('academy.app_errors', $lock)->getModifiedCount() === 1;
}

function medacapErrMail($ref, $class, $message, $file, $line, $ctx, $trace)
{
    $to = medacapEnvString('MEDACAP_ERROR_MAIL');
    if ($to === '') {
        $to = 'jakaa-ext@cfao.com';
    }
    if (strtolower($to) === 'off' || !function_exists('mail')) {
        return;
    }
    $host = medacapPublicUrl() !== '' ? medacapPublicUrl() : (gethostname() ?: 'serveur');
    $subject = '[MEDACAP] ' . $ref . ' ' . $class . ' : ' . basename($file) . ':' . $line;
    $body = "Erreur serveur MEDACAP (" . $host . ")\n\n"
        . "Référence : " . $ref . "\n"
        . "Date      : " . date('d/m/Y H:i:s') . "\n"
        . "URL       : " . $ctx['method'] . ' ' . $ctx['url'] . "\n"
        . "Utilisateur : " . ($ctx['userName'] !== '' ? $ctx['userName'] : '-') . ' (' . ($ctx['profile'] !== '' ? $ctx['profile'] : 'non connecté') . ', ' . $ctx['userId'] . ")\n\n"
        . $class . ' : ' . $message . "\n"
        . 'Fichier : ' . $file . ':' . $line . "\n\n"
        . "Trace :\n" . mb_substr($trace, 0, 4000) . "\n\n"
        . "Les occurrences suivantes de la même erreur sont comptées dans la collection app_errors, sans nouveau mail pendant 1 h.\n";
    $headers = "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n";
    @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
}

function medacapErrIsAjax()
{
    $with = isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) : '';
    $accept = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '';
    $type = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
    $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    // fetch() n'envoie pas X-Requested-With : un script qui a déjà annoncé du JSON est un endpoint AJAX
    foreach (headers_list() as $h) {
        if (stripos($h, 'content-type:') === 0 && stripos($h, 'json') !== false) {
            return true;
        }
    }
    return $with === 'xmlhttprequest'
        || (strpos($accept, 'application/json') !== false && strpos($accept, 'text/html') === false)
        || strpos($type, 'application/json') !== false
        || strpos($uri, '/api/') !== false;
}

function medacapErrRespond($ref)
{
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Erreur serveur, référence " . $ref . "\n");
        return;
    }
    if (!headers_sent()) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(500);
    }
    if (medacapErrIsAjax()) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(array(
            'success' => false,
            'error' => 'Erreur serveur (référence ' . $ref . ')',
            'message' => 'Erreur serveur (référence ' . $ref . ')',
            'ref' => $ref,
        ), JSON_UNESCAPED_UNICODE);
        return;
    }
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Erreur | CFAO Mobility Academy</title></head>'
        . '<body style="font-family:Arial,sans-serif;background:#f5f8fa;margin:0;padding:48px 16px;color:#181c32">'
        . '<div style="max-width:520px;margin:0 auto;background:#fff;border-radius:8px;padding:32px;box-shadow:0 2px 8px rgba(0,0,0,.08)">'
        . '<h1 style="font-size:20px;margin:0 0 12px">Une erreur est survenue</h1>'
        . '<p style="margin:0 0 16px;line-height:1.5">L\'équipe support a été prévenue. Si le problème persiste, écrivez à '
        . '<a href="mailto:jakaa-ext@cfao.com?subject=MEDACAP%20' . $ref . '">jakaa-ext@cfao.com</a> en indiquant la référence :</p>'
        . '<p style="font-size:22px;font-weight:bold;letter-spacing:1px;margin:0 0 24px">' . $ref . '</p>'
        . '<a href="javascript:history.back()" style="color:#009ef7">← Revenir à la page précédente</a></div></body></html>';
}

/** Traitement commun d'une erreur bloquante (exception non rattrapée ou fatale). */
function medacapErrFatal($class, $message, $file, $line, $trace)
{
    $GLOBALS['medacapErrHandled'] = true;
    $ref = medacapErrRef();
    $file = medacapErrRelPath($file);
    $ctx = medacapErrContext();
    error_log(sprintf('[MEDACAP %s] %s: %s @ %s:%d | %s %s | user=%s (%s)',
        $ref, $class, $message, $file, $line, $ctx['method'], $ctx['url'], $ctx['userId'], $ctx['profile']));
    try {
        if (medacapErrStore('fatal', $class, $message, $file, $line, $ref, $ctx, $trace, 1)) {
            medacapErrMail($ref, $class, $message, $file, $line, $ctx, $trace);
        }
    } catch (Throwable $e) {
        error_log('[MEDACAP ' . $ref . '] enregistrement impossible : ' . get_class($e) . ': ' . $e->getMessage());
    }
    medacapErrRespond($ref);
}

set_exception_handler(function ($e) {
    try {
        medacapErrFatal(get_class($e), $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
    } catch (Throwable $inner) {
        error_log('[MEDACAP] gestionnaire en échec : ' . $inner->getMessage() . ' / origine : ' . $e->getMessage());
    }
});

// Warnings, notices, deprecated : regroupés par empreinte, écrits en une fois à la fin de la requête.
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false; // opérateur @ ou niveau désactivé
    }
    $labels = array(
        E_WARNING => 'Warning', E_NOTICE => 'Notice', E_DEPRECATED => 'Deprecated',
        E_USER_WARNING => 'Warning', E_USER_NOTICE => 'Notice', E_USER_DEPRECATED => 'Deprecated',
        E_USER_ERROR => 'User Error', E_RECOVERABLE_ERROR => 'Recoverable Error',
    );
    $label = isset($labels[$errno]) ? $labels[$errno] : 'E' . $errno;
    $key = $label . '|' . $errfile . '|' . $errline;
    if (count($GLOBALS['medacapErrWarnings']) < 200) {
        if (isset($GLOBALS['medacapErrWarnings'][$key])) {
            $GLOBALS['medacapErrWarnings'][$key]['count']++;
        } else {
            $GLOBALS['medacapErrWarnings'][$key] = array(
                'label' => $label, 'message' => $errstr, 'file' => $errfile, 'line' => $errline, 'count' => 1,
            );
        }
    }
    return false; // le journal PHP habituel continue de recevoir l'entrée
});

register_shutdown_function(function () {
    $GLOBALS['medacapErrReserve'] = null;
    $last = error_get_last();
    $fatalTypes = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR);
    if ($last && in_array($last['type'], $fatalTypes, true) && !$GLOBALS['medacapErrHandled']) {
        try {
            medacapErrFatal('Fatal error', $last['message'], $last['file'], $last['line'], '');
        } catch (Throwable $e) {
            error_log('[MEDACAP] gestionnaire en échec : ' . $e->getMessage());
        }
    }
    if (empty($GLOBALS['medacapErrWarnings'])) {
        return;
    }
    if (!extension_loaded('mongodb')) {
        return;
    }
    try {
        $ctx = medacapErrContext();
        $bulk = new MongoDB\Driver\BulkWrite(array('ordered' => false));
        foreach ($GLOBALS['medacapErrWarnings'] as $w) {
            medacapErrAddUpsert($bulk, strtolower($w['label']), $w['label'], $w['message'],
                medacapErrRelPath($w['file']), $w['line'], '', $ctx, '', $w['count']);
        }
        medacapErrManager()->executeBulkWrite('academy.app_errors', $bulk);
    } catch (Throwable $e) {
        error_log('[MEDACAP] enregistrement des warnings impossible : ' . $e->getMessage());
    }
});
