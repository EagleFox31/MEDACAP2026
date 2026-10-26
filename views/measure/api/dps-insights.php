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

    // 1. Lecture directe des valeurs définitives enregistrées en BD (< 50ms)
    $cached = $db->portalDpsInsights->findOne([
        'subsidiary' => ['$in' => [$ctx['subsidiary'], 'Cameroun', 'CAMEROON MOTORS INDUSTRIES']],
        'department' => 'Equipment',
    ]);

    if ($cached) {
        $row = $cached instanceof \MongoDB\Model\BSONDocument ? $cached->getArrayCopy() : (array) $cached;
        $evals = isset($row['collaboratorEvaluations']) ? (array) $row['collaboratorEvaluations'] : [];
        if (!empty($evals)) {
            portalApiJson([
                'userId' => $ctx['userId'],
                'subsidiary' => $ctx['subsidiary'],
                'brandStats' => $row['brandStats'] ?? [],
                'collaboratorEvaluations' => $evals,
                'recentLogs' => $row['recentLogs'] ?? [],
                'computedAt' => $row['computedAt'] ?? null,
                'source' => 'portalDpsInsights',
            ]);
            return;
        }
    }

    // 2. Fallback calcul live
    $insights = portalApiDpsFilialeInsights(
        $ctx['technicianDocs'],
        $db,
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
        'source' => 'live',
    ]);
} catch (Throwable $e) {
    portalApiJson(['error' => 'Server error', 'message' => $e->getMessage()], 500);
}
