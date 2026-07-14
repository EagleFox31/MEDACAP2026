<?php
declare(strict_types=1);

use MongoDB\BSON\ObjectId;

require_once __DIR__ . '/bootstrap.php';

portalApiCors();
portalApiAuthorizePortalRequest();

global $mongoUri, $mongoDb;

$userIdParam = isset($_GET['userId']) ? trim((string) $_GET['userId']) : null;
$username = isset($_GET['username']) ? trim((string) $_GET['username']) : null;
$email = isset($_GET['email']) ? trim((string) $_GET['email']) : null;

try {
    $db = portalApiDb($mongoUri, $mongoDb)->selectDatabase($mongoDb);
    $ctx = portalApiResolveDpsFilialeContext($db, $userIdParam, $username, $email);

    $techOids = array_map(static function (array $row): ObjectId {
        return $row['_id'];
    }, $ctx['technicianDocs']);

    $pif = portalApiDpsPifPayload($db, $techOids);

    portalApiJson([
        'userId' => $ctx['userId'],
        'subsidiary' => $ctx['subsidiary'],
        'planningStatus' => $pif['planningStatus'],
        'brandPifStats' => $pif['brandPifStats'],
        'brandPlanningSummary' => $pif['brandPlanningSummary'] ?? [],
    ]);
} catch (Throwable $e) {
    portalApiJson(['error' => 'Server error', 'message' => $e->getMessage()], 500);
}
