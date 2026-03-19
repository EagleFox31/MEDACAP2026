<?php
require_once "../../vendor/autoload.php";

use MongoDB\Client;
use MongoDB\BSON\ObjectId;

session_start();

// Vérifiez si l'utilisateur est connecté
if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
}

// Connexion à MongoDB
$mongoClient = new Client("mongodb://localhost:27017");
$academy = $mongoClient->selectDatabase('academy');

// Récupérer les paramètres GET
$selectedCountry = $_GET['country'] ?? 'all';
$selectedAgency = $_GET['agency'] ?? 'all';

// Construire le filtre de base pour les managers
$baseFilter = [
    'profile' => 'Manager',
    'active' => true
];

// Ajouter le filtre pour le pays
if ($selectedCountry !== 'all') {
    $baseFilter['country'] = $selectedCountry;
}

// Ajouter le filtre pour l'agence
if ($selectedAgency !== 'all') {
    $baseFilter['agency'] = $selectedAgency;
}

try {
    // Requête pour récupérer les managers correspondant aux filtres
    $managersCursor = $academy->users->find($baseFilter);

    // Préparer la liste des managers
    $managers = [];
    foreach ($managersCursor as $manager) {
        $managers[] = [
            'id' => (string)$manager['_id'],
            'name' => $manager['firstName'] . ' ' . $manager['lastName']
        ];
    }

    // Retourner la réponse JSON
    header('Content-Type: application/json');
    echo json_encode($managers);
} catch (Exception $e) {
    // Gérer les erreurs de MongoDB
    file_put_contents('get_managers_error.log', $e->getMessage());
    die("Erreur lors de la récupération des managers : " . $e->getMessage());
}
