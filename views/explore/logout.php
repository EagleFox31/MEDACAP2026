<?php
session_start();

require_once "../../vendor/autoload.php";

// Create connection
$conn = new MongoDB\Client("mongodb://localhost:27017");

// Connecting in database
$academy = $conn->academy;

// Connecting in collections
$connections = $academy->connections;

$userConnected = $connections->findOne([
    '$and' => [
        [
            "user" => new MongoDB\BSON\ObjectId($_SESSION["id"]),
            "active" => true,
        ],
    ],
]);
if ($userConnected) {
    $userConnected->status = "Offline";
    $userConnected->end = date("d-m-Y H:i:s");
    $connections->updateOne(
        ["_id" => new MongoDB\BSON\ObjectId($userConnected->_id)],
        ['$set' => $userConnected]
    );
} else {
    $connection = [
        "user" => new MongoDB\BSON\ObjectId($_SESSION["id"]),
        "status" => "Offline",
        "start" => "",
        "end" => date("d-m-Y H:i:s"),
        "active" => true
    ];

    $connections->insertOne($connection);
}

unset($_SESSION["id"]);

session_destroy();

header('Location:  ../../');
?>