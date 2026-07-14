<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

portalApiCors();

global $portalBridgeKey, $mongoUri, $mongoDb;

$providedKey = $_GET['key'] ?? ($_SERVER['HTTP_X_PORTAL_KEY'] ?? '');
if ($providedKey !== $portalBridgeKey) {
    portalApiJson(['error' => 'Unauthorized'], 401);
}

$userIdParam = isset($_GET['userId']) ? trim((string) $_GET['userId']) : null;
$username = isset($_GET['username']) ? trim((string) $_GET['username']) : null;
$email = isset($_GET['email']) ? trim((string) $_GET['email']) : null;

try {
    $client = portalApiDb($mongoUri, $mongoDb);
    $db = $client->selectDatabase($mongoDb);
    $users = $db->users;

    $userOid = portalApiResolveUserId($users, $userIdParam, $username, $email);
    if (!$userOid) {
        portalApiJson(['error' => 'User not found', 'evaluations' => []], 404);
    }

    $userDoc = $users->findOne(['_id' => $userOid, 'active' => true]);
    if (!$userDoc) {
        portalApiJson(['error' => 'User inactive', 'evaluations' => []], 404);
    }

    $userIdHex = (string) $userOid;
    $userArr = $userDoc instanceof \MongoDB\Model\BSONDocument
        ? $userDoc->getArrayCopy()
        : (array) $userDoc;

    $evaluations = portalApiUserCompetencyEvaluations(
        $userOid,
        $userArr,
        $db->allocations,
        $db->exams,
        $db->results,
        $portalBridgeKey
    );

    portalApiJson([
        'userId' => $userIdHex,
        'username' => $userArr['username'] ?? null,
        'profile' => portalApiUserProfilePayload($userArr, $userIdHex),
        'evaluations' => $evaluations,
    ]);
} catch (Throwable $e) {
    portalApiJson(['error' => 'Server error', 'message' => $e->getMessage()], 500);
}
