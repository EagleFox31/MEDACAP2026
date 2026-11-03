<?php
/**
 * Dernière activité des utilisateurs connectés (connections.lastSeen), chargé par core/error_handler.php.
 *
 * À la fin de chaque requête d'un utilisateur en session : lastSeen = maintenant sur sa connexion active,
 * au plus une écriture par minute et par session. Lu par views/measure/connectedUsers.php.
 * Ne doit jamais lever d'exception : tout est sous try/catch (Throwable).
 */

if (defined('MEDACAP_PRESENCE')) {
    return;
}
define('MEDACAP_PRESENCE', 1);

/** Délai sans activité au-delà duquel un utilisateur n'est plus considéré en ligne. */
define('MEDACAP_PRESENCE_ONLINE_SECONDS', 900);

register_shutdown_function(function () {
    if (PHP_SAPI === 'cli' || !extension_loaded('mongodb')) {
        return;
    }
    if (!isset($_SESSION) || !is_array($_SESSION) || empty($_SESSION['id'])) {
        return;
    }
    // Module Commercial : sessions à part (identifiants hors collection users)
    $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    if (stripos($uri, '/Commercial/') !== false) {
        return;
    }
    $id = (string) $_SESSION['id'];
    if (!preg_match('/^[0-9a-f]{24}$/i', $id)) {
        return;
    }
    $now = time();
    if (isset($_SESSION['medacapLastSeen']) && $now - (int) $_SESSION['medacapLastSeen'] < 60) {
        return;
    }
    try {
        $bulk = new MongoDB\Driver\BulkWrite();
        $bulk->update(
            array('user' => new MongoDB\BSON\ObjectId($id), 'active' => true),
            array(
                '$set' => array('lastSeen' => new MongoDB\BSON\UTCDateTime($now * 1000), 'status' => 'Online'),
                '$setOnInsert' => array('start' => date('d-m-Y H:i:s'), 'end' => ''),
            ),
            array('upsert' => true)
        );
        medacapErrManager()->executeBulkWrite('academy.connections', $bulk);
        // Écrit dans la session tant qu'elle est ouverte (ignoré si la page l'a déjà fermée)
        $_SESSION['medacapLastSeen'] = $now;
    } catch (Throwable $e) {
        error_log('[MEDACAP] presence : ' . $e->getMessage());
    }
});
