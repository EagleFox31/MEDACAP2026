<?php
declare(strict_types=1);

/**
 * SSO portail CFAO → session Define (DPS) + paramètres dashboard.
 * Déployer vers : views/measure/api/portal-launch-define.php
 *
 * Paramètres utiles portail :
 * - userId | username | email : utilisateur connecté (souvent le DPS)
 * - view=pluri|annual : dashboard.php ou dashboard2026.php
 * - technicianId | technicianUsername : technicien cible
 * - viewAs=technician : vue TechnicienDashboardController (session reste le DPS)
 */

require_once __DIR__ . '/bootstrap.php';

global $portalBridgeKey, $mongoUri, $mongoDb;

$key = $_GET['key'] ?? '';
if ($key !== $portalBridgeKey) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$userIdParam = isset($_GET['userId']) ? trim((string) $_GET['userId']) : null;
$username = isset($_GET['username']) ? trim((string) $_GET['username']) : null;
$email = isset($_GET['email']) ? trim((string) $_GET['email']) : null;

try {
    $client = portalApiDb($mongoUri, $mongoDb);
    $users = $client->selectDatabase($mongoDb)->users;

    $userOid = portalApiResolveUserId($users, $userIdParam, $username, $email);
    if (!$userOid) {
        http_response_code(404);
        echo 'User not found';
        exit;
    }

    $login = $users->findOne(['_id' => $userOid, 'active' => true]);
    if (!$login) {
        http_response_code(404);
        echo 'User not found';
        exit;
    }

    if (session_status() === PHP_SESSION_NONE) {
        if (!@session_start()) {
            throw new RuntimeException('session_start failed');
        }
    }

    $userIdHex = (string) $login['_id'];

    $_SESSION['username'] = $login['username'] ?? '';
    $_SESSION['profile'] = $login['profile'] ?? '';
    $_SESSION['lastName'] = $login['lastName'] ?? '';
    $_SESSION['firstName'] = $login['firstName'] ?? '';
    $_SESSION['email'] = $login['email'] ?? '';
    $_SESSION['test'] = $login['test'] ?? false;
    $_SESSION['id'] = $userIdHex;
    $_SESSION['medacapLoginAt'] = time();
    $_SESSION['subsidiary'] = $login['subsidiary'] ?? null;
    $_SESSION['agency'] = $login['agency'] ?? null;
    $_SESSION['department'] = $login['department'] ?? null;
    $_SESSION['country'] = $login['country'] ?? null;

    $view = isset($_GET['view']) ? strtolower(trim((string) $_GET['view'])) : 'pluri';
    $target = isset($_GET['target']) ? strtolower(trim((string) $_GET['target'])) : '';

    if ($target === 'collaboratordashboard' || $target === 'collaborator') {
        $targetFile = 'collaboratorDashboard.php';
    } elseif ($view === 'annual' || $view === '2026') {
        $targetFile = 'dashboard2026.php';
    } elseif ($view === 'collaborator' || $view === 'collaboratordashboard') {
        $targetFile = 'collaboratorDashboard.php';
    } else {
        $targetFile = 'dashboard.php';
    }

    $passthrough = ['brand', 'agency', 'level', 'subsidiary', 'technicianId', 'managerId', 'viewAs', 'embed', 'returnUrl', 'scope', 'year'];
    $extra = [];
    foreach ($passthrough as $param) {
        if (!isset($_GET[$param])) {
            continue;
        }
        $value = trim((string) $_GET[$param]);
        if ($value !== '') {
            $extra[$param] = $value;
        }
    }

    $technicianUsername = isset($_GET['technicianUsername']) ? trim((string) $_GET['technicianUsername']) : '';
    if ($technicianUsername !== '' && empty($extra['technicianId'])) {
        $tech = $users->findOne([
            'username' => $technicianUsername,
            'profile' => 'Technicien',
            'active' => true,
        ]);
        if ($tech) {
            $extra['technicianId'] = (string) $tech['_id'];
        }
    }

    $location = '../../define/' . $targetFile;
    if ($extra) {
        $location .= '?' . http_build_query($extra);
    }

    header('Location: ' . $location);
    exit;
} catch (Throwable $e) {
    error_log('portal-launch-define: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $isLocal = strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') === 0;
    echo $isLocal ? ('Server error: ' . $e->getMessage()) : 'Server error';
    exit;
}
