<?php 

require_once __DIR__ . '/../../core/medacap_config.php';
require_once "../../vendor/autoload.php";

// Create connection
$conn = new MongoDB\Client(medacapMongoUri());

// Connecting in database
$academy = $conn->academy;

// Connecting in collections
$calendars = $academy->calendars;

$datas = $calendars->find([ 'active' => true ]);