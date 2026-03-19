<?php 

require_once "../../vendor/autoload.php";

// Create connection
$conn = new MongoDB\Client("mongodb://localhost:27017");

// Connecting in database
$academy = $conn->academy;

// Connecting in collections
$calendars = $academy->calendars;

$datas = $calendars->find([ 'active' => true ]);