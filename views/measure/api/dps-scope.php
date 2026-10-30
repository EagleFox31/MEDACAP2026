<?php
declare(strict_types=1);

use MongoDB\BSON\ObjectId;

require_once __DIR__ . '/bootstrap.php';

portalApiCors();
portalApiAuthorizePortalRequest();

global $portalBridgeKey, $mongoUri, $mongoDb;

$userIdParam = isset($_GET['userId']) ? trim((string) $_GET['userId']) : null;
$username = isset($_GET['username']) ? trim((string) $_GET['username']) : null;
$email = isset($_GET['email']) ? trim((string) $_GET['email']) : null;

$includeInsights = !isset($_GET['insights'])
    || $_GET['insights'] === '1'
    || $_GET['insights'] === 'true';

$includePif = !isset($_GET['pif'])
    || $_GET['pif'] === '1'
    || $_GET['pif'] === 'true';

try {
    $db = portalApiDb($mongoUri, $mongoDb)->selectDatabase($mongoDb);
    $ctx = portalApiResolveDpsFilialeContext($db, $userIdParam, $username, $email);

    $insights = [
        'brandStats' => [],
        'collaboratorEvaluations' => [],
        'recentLogs' => [],
    ];

    if ($includeInsights) {
        $insights = portalApiDpsFilialeInsights(
            $ctx['technicianDocs'],
            $db,
            $db->allocations,
            $db->exams,
            $db->results,
            $portalBridgeKey
        );
    }

    $planningStatus = [];
    $brandPifStats = [];
    $brandPlanningSummary = [];
    $pifTechnicians = [];
    if ($includePif) {
        $techOids = array_map(static function (array $row): ObjectId {
            return $row['_id'];
        }, $ctx['technicianDocs']);
        $pif = portalApiDpsPifPayload($db, $techOids);
        $planningStatus = $pif['planningStatus'];
        $brandPifStats = $pif['brandPifStats'];
        $brandPlanningSummary = $pif['brandPlanningSummary'] ?? [];
        $pifTechnicians = $pif['pifTechnicians'] ?? [];
    }

    portalApiJson([
        'userId' => $ctx['userId'],
        'subsidiary' => $ctx['subsidiary'],
        'department' => $ctx['department'],
        'technicianDepartment' => $ctx['technicianDepartment'],
        'totalTechnicians' => $ctx['totalTechnicians'],
        'brands' => $ctx['brands'],
        'agencies' => $ctx['agencies'],
        'technicians' => $ctx['technicians'],
        'brandStats' => $insights['brandStats'],
        'collaboratorEvaluations' => $insights['collaboratorEvaluations'],
        'recentLogs' => $insights['recentLogs'],
        'planningStatus' => $planningStatus,
        'brandPifStats' => $brandPifStats,
        'brandPlanningSummary' => $brandPlanningSummary,
        'pifTechnicians' => $pifTechnicians,
    ]);
} catch (Throwable $e) {
    portalApiJson(['error' => 'Server error', 'message' => $e->getMessage()], 500);
}
