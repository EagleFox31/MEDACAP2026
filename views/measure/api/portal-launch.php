<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

global $portalBridgeKey, $mongoUri, $mongoDb;

$key = $_GET['key'] ?? '';
if ($key !== $portalBridgeKey) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$userId = trim((string) ($_GET['userId'] ?? ''));
$testId = trim((string) ($_GET['test'] ?? ''));
$level = trim((string) ($_GET['level'] ?? ''));
$type = trim((string) ($_GET['type'] ?? ''));

$allowedLevels = ['Junior', 'Senior', 'Expert'];
$allowedTypes = ['Factuel', 'Declaratif'];

if (
    !preg_match('/^[a-f0-9]{24}$/i', $userId)
    || !preg_match('/^[a-f0-9]{24}$/i', $testId)
    || !in_array($level, $allowedLevels, true)
    || !in_array($type, $allowedTypes, true)
) {
    http_response_code(400);
    echo 'Invalid parameters';
    exit;
}

try {
    $client = portalApiDb($mongoUri, $mongoDb);
    $db = $client->selectDatabase($mongoDb);
    $users = $db->users;

    $userOid = new MongoDB\BSON\ObjectId($userId);
    $login = $users->findOne(['_id' => $userOid, 'active' => true]);
    if (!$login) {
        http_response_code(404);
        echo 'User not found';
        exit;
    }

    session_start();
    $_SESSION['username'] = $login['username'] ?? '';
    $_SESSION['profile'] = $login['profile'] ?? 'Technicien';
    $_SESSION['lastName'] = $login['lastName'] ?? '';
    $_SESSION['firstName'] = $login['firstName'] ?? '';
    $_SESSION['email'] = $login['email'] ?? '';
    $_SESSION['test'] = $login['test'] ?? false;
    $_SESSION['id'] = $login['_id'];
    $_SESSION['subsidiary'] = $login['subsidiary'] ?? null;
    $_SESSION['agency'] = $login['agency'] ?? null;
    $_SESSION['department'] = $login['department'] ?? null;
    $_SESSION['country'] = $login['country'] ?? null;
    $_SESSION['medacapLoginAt'] = time();

    $guide = 'true';
    $params = http_build_query([
        'test' => $testId,
        'level' => $level,
        'type' => $type,
        'id' => $userId,
        'guide' => $guide,
    ]);

    header('Location: ../quiz.php?' . $params);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Server error';
    exit;
}
