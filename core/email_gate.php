<?php
/**
 * Adresse e-mail demandée à la connexion par mot de passe, pour les comptes qui
 * n'en ont pas (sauf Candidat). L'adresse n'est enregistrée qu'après saisie du
 * code reçu dessus. Seules les adresses @cfao.com sont acceptées.
 */

require_once __DIR__ . '/login_links.php';

if (!defined('MEDACAP_EMAIL_GATE_TTL')) {
    define('MEDACAP_EMAIL_GATE_TTL', 30 * 60);
}

/**
 * @param mixed $user document utilisateur
 */
function medacapEmailGateNeeded($user)
{
    $profile = isset($user['profile']) ? (string) $user['profile'] : '';
    if (strcasecmp($profile, 'Candidat') === 0) {
        return false;
    }
    $email = isset($user['email']) ? trim((string) $user['email']) : '';
    return !filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * « Plus tard » : techniciens seulement. Beaucoup n'ont pas d'adresse @cfao.com,
 * l'écran ne doit pas les empêcher de passer leurs QCM.
 */
function medacapEmailGateCanSkip($user)
{
    return isset($user['profile']) && (string) $user['profile'] === 'Technicien';
}

function medacapEmailGateAllowed($email)
{
    return (bool) preg_match('/^[^@\s]+@cfao\.com$/i', (string) $email);
}

/**
 * Vérifie le code envoyé à $email pour ce compte et le consomme.
 * Mêmes limites que le code de connexion : 5 essais par code, 15 échecs par compte sur 24 h.
 */
function medacapConsumeEmailCode($academy, $userId, $email, $code)
{
    try {
        $now = new MongoDB\BSON\UTCDateTime();
        $since = new MongoDB\BSON\UTCDateTime((time() - 86400) * 1000);
        $failures = medacapCountDocuments($academy->login_code_failures, array(
            'user' => $userId,
            'created' => array('$gte' => $since),
        ));
        $doc = $academy->login_links->findOneAndUpdate(
            array(
                'user' => $userId,
                'purpose' => 'email_verify',
                'email' => (string) $email,
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
            return false;
        }
        $valid = $failures < MEDACAP_LOGIN_CODE_DAILY_FAILURES
            && hash_equals((string) $doc['codeHash'], medacapLoginCodeHash($userId, $doc['_id'], $code));
        if (!$valid) {
            $academy->login_code_failures->insertOne(array('user' => $userId, 'created' => $now));
            return false;
        }
        $used = $academy->login_links->updateOne(
            array('_id' => $doc['_id'], 'usedAt' => null),
            array('$set' => array('usedAt' => $now, 'usedVia' => 'code'))
        );
        return $used->getModifiedCount() === 1;
    } catch (Throwable $e) {
        error_log('email gate code: ' . get_class($e) . ' ' . $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine());
        return false;
    }
}
