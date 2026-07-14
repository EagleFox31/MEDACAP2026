<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

portalApiCors();
portalApiAuthorizePortalRequest();

global $portalBridgeKey, $mongoUri, $mongoDb;

$userIdParam = isset($_GET['userId']) ? trim((string) $_GET['userId']) : null;
$username = isset($_GET['username']) ? trim((string) $_GET['username']) : null;
$email = isset($_GET['email']) ? trim((string) $_GET['email']) : null;

try {
    $db = portalApiDb($mongoUri, $mongoDb)->selectDatabase($mongoDb);
    $ctx = portalApiResolveDpsFilialeContext($db, $userIdParam, $username, $email);

    $insights = portalApiDpsFilialeInsights(
        $ctx['technicianDocs'],
        $db->allocations,
        $db->exams,
        $db->results,
        $portalBridgeKey
    );

    portalApiJson([
        'userId' => $ctx['userId'],
        'subsidiary' => $ctx['subsidiary'],
        'brandStats' => $insights['brandStats'],
        'collaboratorEvaluations' => $insights['collaboratorEvaluations'],
        'recentLogs' => $insights['recentLogs'],
    ]);
} catch (Throwable $e) {
    portalApiJson(['error' => 'Server error', 'message' => $e->getMessage()], 500);
}
