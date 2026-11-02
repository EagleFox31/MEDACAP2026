<?php
/**
 * Connexion sans mot de passe : un envoi = un document login_links qui porte
 * le jeton du lien et un code à 6 chiffres. Ils partagent usedAt : utiliser
 * l'un invalide l'autre.
 */

require_once __DIR__ . '/medacap_config.php';
require_once __DIR__ . '/mongo_compat.php';

if (!defined('MEDACAP_LOGIN_CODE_MAX_ATTEMPTS')) {
    define('MEDACAP_LOGIN_CODE_MAX_ATTEMPTS', 5);
    define('MEDACAP_LOGIN_CODE_DAILY_FAILURES', 15);
}

/**
 * Comptes actifs portant cette adresse (au plus 2 : au-delà de 1, c'est ambigu).
 *
 * @return array
 */
function medacapFindActiveUsersByEmail($users, $email)
{
    return iterator_to_array($users->find(
        array(
            'active' => true,
            'email' => new MongoDB\BSON\Regex('^' . preg_quote((string) $email, '/') . '$', 'i'),
        ),
        array('limit' => 2)
    ), false);
}

/**
 * Base des liens envoyés par mail : MEDACAP_PUBLIC_URL si elle est posée. Sinon,
 * même hôte que la demande quand il est dans la liste fermée, pour ne pas suivre un Host forgé.
 *
 * @param mixed $account compte destinataire (pays / filiale), ou array() s'il n'y en a pas
 */
function medacapLoginLinkBase($account)
{
    $configured = medacapPublicUrl();
    if ($configured !== '') {
        return rtrim($configured, '/');
    }
    $host = isset($_SERVER['HTTP_HOST']) ? strtolower((string) $_SERVER['HTTP_HOST']) : '';
    if (in_array($host, array('10.68.0.7', '129.0.64.34'), true)) {
        return 'http://' . $host . '/medacap';
    }
    $country = isset($account['country']) ? (string) $account['country'] : '';
    $subsidiary = isset($account['subsidiary']) ? (string) $account['subsidiary'] : '';
    if ($country === '' && stripos($subsidiary, 'CAMEROON') !== false) {
        $country = 'Cameroun';
    }
    return rtrim(medacapAppBaseUrl($country), '/');
}

/**
 * Lié au compte et au document : un hash ne sert pas d'un document à l'autre.
 */
function medacapLoginCodeHash($userId, $linkId, $code)
{
    $key = 'medacap-login-code|' . (string) $userId . '|' . (string) $linkId . '|' . medacapEnvString('MEDACAP_LOGIN_CODE_PEPPER');
    return hash_hmac('sha256', (string) $code, $key);
}

/**
 * "123 456" ou "123-456" → "123456". Null si ce ne sont pas 6 chiffres.
 */
function medacapNormalizeLoginCode($input)
{
    $digits = preg_replace('/\D/', '', (string) $input);
    return strlen($digits) === 6 ? $digits : null;
}

/**
 * Crée l'envoi. Les codes encore valides du compte sont neutralisés : seul le dernier compte.
 *
 * @return array id (ObjectId), token, code
 */
function medacapIssueLoginLink($links, $userId, $linkTtl, $codeTtl, $purpose = 'login')
{
    $now = new MongoDB\BSON\UTCDateTime();
    $links->updateMany(
        array('user' => $userId, 'usedAt' => null, 'codeExpiresAt' => array('$gt' => $now)),
        array('$set' => array('codeExpiresAt' => $now))
    );
    $id = new MongoDB\BSON\ObjectId();
    $token = bin2hex(random_bytes(32));
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $links->insertOne(array(
        '_id' => $id,
        'user' => $userId,
        'purpose' => $purpose,
        'tokenHash' => hash('sha256', $token),
        // Le TTL de la collection porte sur expiresAt : garder la plus longue des deux durées
        'expiresAt' => new MongoDB\BSON\UTCDateTime((time() + max($linkTtl, $codeTtl)) * 1000),
        'usedAt' => null,
        'created' => $now,
        'codeHash' => medacapLoginCodeHash($userId, $id, $code),
        'codeExpiresAt' => new MongoDB\BSON\UTCDateTime((time() + $codeTtl) * 1000),
        'codeAttempts' => 0,
    ));
    return array('id' => $id, 'token' => $token, 'code' => $code);
}

/**
 * Après une connexion réussie, ferme les autres envois encore ouverts du compte.
 */
function medacapRevokeOtherLoginLinks($links, $userId, $keepId)
{
    try {
        $links->updateMany(
            array('user' => $userId, 'usedAt' => null, '_id' => array('$ne' => $keepId)),
            array('$set' => array('usedAt' => new MongoDB\BSON\UTCDateTime(), 'usedVia' => 'revoked'))
        );
    } catch (Throwable $e) {
        error_log('login links revoke: ' . get_class($e) . ' ' . $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine());
    }
}

/**
 * Vérifie le code saisi pour cette adresse et le consomme.
 * Même suite de requêtes que le compte existe ou non. 5 essais par code,
 * 15 échecs par compte sur 24 h.
 *
 * @return mixed le compte, ou null
 */
function medacapConsumeLoginCode($academy, $email, $code)
{
    try {
        $matches = medacapFindActiveUsersByEmail($academy->users, $email);
        $userId = count($matches) === 1 ? $matches[0]['_id'] : new MongoDB\BSON\ObjectId();
        $now = new MongoDB\BSON\UTCDateTime();
        $since = new MongoDB\BSON\UTCDateTime((time() - 86400) * 1000);
        $failures = medacapCountDocuments($academy->login_code_failures, array(
            'user' => $userId,
            'created' => array('$gte' => $since),
        ));
        // Essai compté avant la comparaison : des essais en parallèle ne dépassent pas la limite
        $doc = $academy->login_links->findOneAndUpdate(
            array(
                'user' => $userId,
                'codeHash' => array('$exists' => true),
                'usedAt' => null,
                'codeExpiresAt' => array('$gt' => $now),
                'codeAttempts' => array('$lt' => MEDACAP_LOGIN_CODE_MAX_ATTEMPTS),
            ),
            array('$inc' => array('codeAttempts' => 1)),
            array(
                'sort' => array('created' => -1),
                'returnDocument' => MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER,
            )
        );
        if (!$doc) {
            return null;
        }
        $valid = $failures < MEDACAP_LOGIN_CODE_DAILY_FAILURES
            && hash_equals((string) $doc['codeHash'], medacapLoginCodeHash($userId, $doc['_id'], $code));
        if (!$valid) {
            $academy->login_code_failures->insertOne(array('user' => $userId, 'created' => $now));
            return null;
        }
        $used = $academy->login_links->updateOne(
            array('_id' => $doc['_id'], 'usedAt' => null),
            array('$set' => array('usedAt' => $now, 'usedVia' => 'code'))
        );
        if ($used->getModifiedCount() !== 1) {
            return null;
        }
        medacapRevokeOtherLoginLinks($academy->login_links, $userId, $doc['_id']);
        return $matches[0];
    } catch (Throwable $e) {
        error_log('login code: ' . get_class($e) . ' ' . $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine());
        return null;
    }
}
