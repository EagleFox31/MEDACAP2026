<?php
require_once "../../vendor/autoload.php";
use MongoDB\Client;
use MongoDB\BSON\ObjectId;

// Connexion MongoDB
try {
    $mongoClient = new Client("mongodb://localhost:27017");
    $academy = $mongoClient->selectDatabase("academy");
} catch (\MongoDB\Exception\Exception $e) {
    die(json_encode(["error" => "Erreur de connexion à MongoDB: " . $e->getMessage()]));
}

// Récupération des paramètres GET
$managerId = $_GET['managerId'] ?? null;
$filterLevel = $_GET['level'] ?? 'all';
$filterBrand = $_GET['brand'] ?? 'all';
$filterTechnician = $_GET['technicianId'] ?? 'all';

if (!$managerId) {
    die(json_encode(["error" => "Paramètre managerId manquant."]));
}

// Vérification de l'ID du manager
try {
    $managerId = new ObjectId($managerId);
} catch (\Exception $e) {
    die(json_encode(["error" => "Format d'ID invalide pour managerId."]));
}

// Construction du pipeline MongoDB
$pipeline = [
    ['$match' => ['_id' => $managerId, 'profile' => 'Manager']],
    ['$lookup' => [
        'from' => 'users',
        'localField' => 'users',
        'foreignField' => '_id',
        'as' => 'subordinates'
    ]],
];

// Ajout des filtres
if ($filterTechnician !== 'all') {
    $pipeline[] = ['$match' => ['subordinates._id' => new ObjectId($filterTechnician)]];
}
if ($filterBrand !== 'all') {
    $pipeline[] = ['$match' => ['subordinates.brand' => $filterBrand]];
}
if ($filterLevel !== 'all') {
    $pipeline[] = ['$match' => ['subordinates.level' => $filterLevel]];
}

// Finaliser le pipeline
$pipeline[] = [
    '$project' => [
        '_id' => 0,
        'managerDetails' => ['firstName' => '$firstName', 'lastName' => '$lastName'],
        'subordinates' => 1
    ]
];

// Exécution de la requête
try {
    $result = $academy->users->aggregate($pipeline)->toArray();
    header('Content-Type: application/json');
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (\MongoDB\Exception\Exception $e) {
    die(json_encode(["error" => "Erreur lors de l'exécution du pipeline: " . $e->getMessage()]));
}
?>
