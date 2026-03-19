<?php
// getTechnicians.php

require_once "../../vendor/autoload.php";
require_once "userFilters.php";

use MongoDB\Client;
use MongoDB\BSON\ObjectId;

// Start session
session_start();

// Verify if the user is logged in and has the 'Directeur Filiale' profile
if (!isset($_SESSION["profile"]) || $_SESSION["profile"] !== 'Directeur Filiale') {
    header("HTTP/1.1 403 Forbidden");
    echo json_encode(['error' => 'Accès refusé.']);
    exit();
}

$filialeCountry = $_SESSION["country"] ?? null;

if (!$filialeCountry) {
    echo json_encode(['error' => 'Informations de filiale manquantes.']);
    exit();
}

$managerId = $_GET['managerId'] ?? 'all';

try {
    $mongo = new Client("mongodb://localhost:27017");
    $academy = $mongo->academy;

    $usersColl = $academy->users;

    $profile = $_SESSION['profile'] ?? '';

    // Fetch filtered technicians using existing function
    $technicians = getAlltechnicians($academy, $profile, $filialeCountry, null, null, ($managerId !== 'all') ? $managerId : null);

    $technicianOptions = [];
    foreach ($technicians as $tech) {
        $technicianOptions[] = [
            'id'   => (string)$tech['_id'],
            'name' => htmlspecialchars($tech['firstName'] . ' ' . $tech['lastName'])
        ];
    }

    header('Content-Type: application/json');
    echo json_encode($technicianOptions);
} catch (Exception $e) {
    header("HTTP/1.1 500 Internal Server Error");
    echo json_encode(['error' => 'Erreur du serveur.']);
}
?>
