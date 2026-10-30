<?php
require_once __DIR__ . '/../../core/medacap_config.php';
/**
 * Core Bootstrap File for Concours
 * Initializes core functionality and configurations for the competition platform
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define base paths
define('CONCOURS_BASE_PATH', dirname(__DIR__));
define('CONCOURS_VIEWS_PATH', CONCOURS_BASE_PATH . '/views');

// Include essential files from main app
require_once CONCOURS_BASE_PATH . '/../vendor/autoload.php';

// MongoDB Connection for Concours
try {
    $mongoClientConcours = new MongoDB\Client(medacapMongoUri('mongodb://10.68.0.7:27017'));
    // Assuming the database name is "concours".
    $databaseConcours = $mongoClientConcours->concours; 
} catch (Exception $e) {
    die("MongoDB connection failed for Concours: " . $e->getMessage());
}

// Make database available globally
$GLOBALS['mongoClientConcours'] = $mongoClientConcours;
$GLOBALS['databaseConcours'] = $databaseConcours;

?>
