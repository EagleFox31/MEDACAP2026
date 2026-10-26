<?php
require_once __DIR__ . '/../core/medacap_config.php';
session_start();
require_once "../vendor/autoload.php";
require_once "../views/sendMail.php";

use MongoDB\Client;

header('Content-Type: application/json');

if (!isset($_SESSION['commercial_session']) || $_SESSION['commercial_session'] !== true) {
    echo json_encode(['ok' => false]);
    exit();
}

$sid   = session_id();
$now   = date('d-m-Y H:i:s');
$client = new Client(medacapMongoUri());
$db     = $client->academy;

$db->commercial_live->updateOne(
    ['session_id' => $sid],
    ['$set' => ['last_seen' => $now]],
    ['upsert' => false]
);

echo json_encode(['ok' => true]);
