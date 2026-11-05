<?php
/**
 * Session ouverte par mot de passe ou par lien de connexion.
 * PHP 8.4. $login est un document utilisateur MongoDB.
 */

function medacapOpenLoginSession($login, $connections)
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    $_SESSION['username'] = $login['username'];
    $_SESSION['profile'] = $login['profile'];
    $_SESSION['lastName'] = $login['lastName'];
    $_SESSION['firstName'] = $login['firstName'];
    $_SESSION['email'] = isset($login['email']) ? $login['email'] : '';
    $_SESSION['test'] = isset($login['test']) ? $login['test'] : null;
    $_SESSION['id'] = $login['_id'];
    $_SESSION['subsidiary'] = isset($login['subsidiary']) ? $login['subsidiary'] : '';
    $_SESSION['agency'] = isset($login['agency']) ? $login['agency'] : '';
    $_SESSION['department'] = isset($login['department']) ? $login['department'] : '';
    $_SESSION['country'] = isset($login['country']) ? $login['country'] : '';
    // Comparé à users.sessionsRevokedAt par core/presence.php (bouton « Déconnecter » de connectedUsers.php)
    $_SESSION['medacapLoginAt'] = time();

    $userId = new MongoDB\BSON\ObjectId((string) $_SESSION['id']);
    $userConnected = $connections->findOne(array(
        'user' => $userId,
        'active' => true,
    ));
    if ($userConnected) {
        $connections->updateOne(
            array('_id' => $userConnected['_id']),
            array('$set' => array(
                'status' => 'Online',
                'start' => date('d-m-Y H:i:s'),
            ))
        );
    } else {
        $connections->insertOne(array(
            'user' => $userId,
            'status' => 'Online',
            'start' => date('d-m-Y H:i:s'),
            'end' => '',
            'active' => true,
        ));
    }

    if (!isset($_SESSION['lang']) || $_SESSION['lang'] === '') {
        $_SESSION['lang'] = 'fr';
    }
    setcookie('lang', $_SESSION['lang'], time() + 31536000, '/');
}

function medacapLoginLandingPath($login, $academy)
{
    $redirectAfterLogin = 'views/portal';
    $profile = isset($login['profile']) ? (string) $login['profile'] : '';
    if (strcasecmp($profile, 'Candidat') === 0) {
        $redirectAfterLogin = 'views/measure/candidatePaperHome';
    }

    $measureLanding = array(
        'Technicien' => 'views/measure/ListeDesQcm',
        'Manager' => 'views/measure/bilanTestNotes',
    );
    if (isset($measureLanding[$profile])) {
        $redirectAfterLogin = $measureLanding[$profile];
        if ($profile === 'Manager') {
            $team = isset($login['users']) ? $login['users'] : array();
            $team = $team instanceof MongoDB\Model\BSONArray ? $team->getArrayCopy() : (array) $team;
            if (!empty($team)) {
                $evalTodo = $academy->allocations->findOne(array(
                    'user' => array('$in' => array_values($team)),
                    'type' => 'Declaratif',
                    'activeManager' => array('$ne' => true),
                ));
                if ($evalTodo) {
                    $redirectAfterLogin = 'views/measure/userManagerList';
                }
            }
        }
        $_SESSION['lang'] = strtolower((string) $_SESSION['lang']) === 'en' ? 'EN' : 'FR';
    }

    return $redirectAfterLogin;
}
