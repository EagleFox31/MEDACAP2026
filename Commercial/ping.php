<?php
require_once __DIR__ . '/../core/medacap_config.php';
session_start();
require_once "../vendor/autoload.php";
require_once "../views/sendMail.php";

use MongoDB\Client;

header('Content-Type: application/json');

$allowedProfiles = ['Super Admin', 'Admin', 'Admin Commercial'];
if (!isset($_SESSION['id']) || !in_array($_SESSION['profile'] ?? '', $allowedProfiles)) {
    echo json_encode(['ok' => false]);
    exit();
}

$email = strtolower(trim($_SESSION['email'] ?? ''));
if (!$email) {
    echo json_encode(['ok' => false]);
    exit();
}

$now = date('d-m-Y H:i:s');
$client = new Client(medacapMongoUri());
$db     = $client->academy;

// Super Admin ou Admin MEDACAP → upsert dans commercial_admins avec flag is_medacap_admin
if (in_array($_SESSION['profile'] ?? '', ['Super Admin', 'Admin'])) {
    $existing = $db->commercial_admins->findOne(['email' => $email]);
    if (!$existing) {
        $db->commercial_admins->insertOne([
            'email'           => $email,
            'firstName'       => (string)($_SESSION['firstName'] ?? ''),
            'lastName'        => (string)($_SESSION['lastName']  ?? ''),
            'active'          => true,
            'is_medacap_admin'=> true,
            'login_at'        => $now,
            'last_seen'       => $now,
        ]);
    } else {
        $setData = ['last_seen' => $now];
        if (empty($existing['login_at'])) {
            $setData['login_at'] = $now;
        }
        $db->commercial_admins->updateOne(['email' => $email], ['$set' => $setData]);
    }
} else {
    // Correcteur commercial_admin
    $setData = ['last_seen' => $now];
    $existing = $db->commercial_admins->findOne(['email' => $email]);
    if ($existing && empty($existing['login_at'])) {
        $setData['login_at'] = $now;
    }
    $db->commercial_admins->updateOne(['email' => $email], ['$set' => $setData]);
}

echo json_encode(['ok' => true, 'ts' => $now]);
