<?php
session_start();
require_once __DIR__ . '/../core/bootstrap.php';

if (!isset($_SESSION["concours_profile"])) {
    http_response_code(401);
    exit(json_encode(["error" => "Unauthorized"]));
}

// Ensure session is still valid (Anti Double-Login)
$userId = new MongoDB\BSON\ObjectId($_SESSION["concours_id"]);
$userConnected = $databaseConcours->connections->findOne(["user" => $userId, "active" => true]);

if (!$userConnected || $userConnected->session_token !== $_SESSION["concours_session_token"]) {
    // Session invalidated by another login
    http_response_code(403);
    exit(json_encode(["error" => "Session invalidated"]));
}

$data = json_decode(file_get_contents('php://input'), true);
if (isset($data['image'])) {
    $snapshot = [
        "user_id" => $userId,
        "username" => $_SESSION["concours_username"],
        "image_base64" => $data['image'],
        "timestamp" => date("d-m-Y H:i:s"),
        "unix_time" => time()
    ];
    $databaseConcours->concours_snapshots->insertOne($snapshot);
    echo json_encode(["status" => "success"]);
} else {
    http_response_code(400);
    echo json_encode(["error" => "No image provided"]);
}
