<?php
/**
 * Core Bootstrap File for MEDACAP
 * Initializes core functionality and configurations
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define base paths
define('BASE_PATH', dirname(__DIR__));
define('VIEWS_PATH', BASE_PATH . '/views');
define('PUBLIC_PATH', BASE_PATH . '/public');

// Include essential files
require_once BASE_PATH . '/vendor/autoload.php';

// MongoDB Connection
try {
    $mongoClient = new MongoDB\Client("mongodb://localhost:27017");
    $database = $mongoClient->academy;
} catch (Exception $e) {
    die("MongoDB connection failed: " . $e->getMessage());
}

// Make database available globally
$GLOBALS['mongoClient'] = $mongoClient;
$GLOBALS['database'] = $database;

// Include helper functions
require_once VIEWS_PATH . '/userFilters.php';
require_once VIEWS_PATH . '/language.php';

// Initialize language variables
if (!isset($GLOBALS['language_loaded'])) {
    // Language variables will be loaded by language.php
    $GLOBALS['language_loaded'] = true;
}
?>