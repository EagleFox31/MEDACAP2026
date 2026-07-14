<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

portalApiCors();
portalApiAuthorizePortalRequest();

global $mongoUri, $mongoDb;

$userIdParam = isset($_GET['userId']) ? trim((string) $_GET['userId']) : null;
$username = isset($_GET['username']) ? trim((string) $_GET['username']) : null;
$email = isset($_GET['email']) ? trim((string) $_GET['email']) : null;

try {
    $db = portalApiDb($mongoUri, $mongoDb)->selectDatabase($mongoDb);
    $users = $db->users;

    $userOid = portalApiResolveUserId($users, $userIdParam, $username, $email);
    if (!$userOid) {
        portalApiJson(['error' => 'User not found'], 404);
    }

    $userDoc = $users->findOne(['_id' => $userOid, 'active' => true]);
    if (!$userDoc) {
        portalApiJson(['error' => 'User inactive'], 404);
    }

    $pif = portalApiLearnerPifPayload($db, $userOid);

    portalApiJson([
        'userId' => (string) $userOid,
        'planningStatus' => $pif['planningStatus'],
        'planPathway' => $pif['planPathway'],
        'brandPifStats' => $pif['brandPifStats'],
    ]);
} catch (Throwable $e) {
    portalApiJson(['error' => 'Server error', 'message' => $e->getMessage()], 500);
}
