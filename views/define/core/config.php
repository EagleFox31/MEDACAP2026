<?php

// views/define/core/config.php
/**
 * Configuration générale de l'application
 */

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_PORT', '27017');
define('DB_NAME', 'academy');

// Configuration des chemins
define('BASE_PATH', realpath(__DIR__ . '/../../..'));
define('VIEWS_PATH', BASE_PATH . '/views');
define('ASSETS_PATH', BASE_PATH . '/assets');

// Configuration de l'application
define('APP_NAME', 'MEDACAP');
define('APP_VERSION', '2.0.0');
define('DEBUG_MODE', true);

// Configuration des timeouts
define('SESSION_TIMEOUT', 3600); // 1 heure

// Gestion des erreurs
if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

// Configuration des marques (logos)
$brandLogos = [
    'BMW' => '../assets/img/brands/bmw.png',
    'MINI' => '../assets/img/brands/mini.png',
    'Alpina' => '../assets/img/brands/alpina.png',
    'Toyota' => '../assets/img/brands/toyota.png',
    'Lexus' => '../assets/img/brands/lexus.png',
    'Ford' => '../assets/img/brands/ford.png',
    'Mazda' => '../assets/img/brands/mazda.png',
    'Suzuki' => '../assets/img/brands/suzuki.png',
    'Volvo' => '../assets/img/brands/volvo.png',
    'Jaguar' => '../assets/img/brands/jaguar.png',
    'Land Rover' => '../assets/img/brands/land_rover.png',
    'Peugeot' => '../assets/img/brands/peugeot.png',
    'Citroën' => '../assets/img/brands/citroen.png',
    'DS' => '../assets/img/brands/ds.png',
    'Opel' => '../assets/img/brands/opel.png',
    'Hyundai' => '../assets/img/brands/hyundai.png',
    'Kia' => '../assets/img/brands/kia.png',
    'Honda' => '../assets/img/brands/honda.png',
    'Nissan' => '../assets/img/brands/nissan.png',
    'Infiniti' => '../assets/img/brands/infiniti.png',
    'Mercedes-Benz' => '../assets/img/brands/mercedes.png',
    'Mitsubishi' => '../assets/img/brands/mitsubishi.png',
    'Volkswagen' => '../assets/img/brands/volkswagen.png',
    'Audi' => '../assets/img/brands/audi.png',
    'Seat' => '../assets/img/brands/seat.png',
    'Skoda' => '../assets/img/brands/skoda.png',
    'Porsche' => '../assets/img/brands/porsche.png',
    'Renault' => '../assets/img/brands/renault.png',
    'Dacia' => '../assets/img/brands/dacia.png',
    'Fiat' => '../assets/img/brands/fiat.png',
    'Alfa Romeo' => '../assets/img/brands/alfa_romeo.png',
    'Jeep' => '../assets/img/brands/jeep.png',
    'Chevrolet' => '../assets/img/brands/chevrolet.png',
    'Cadillac' => '../assets/img/brands/cadillac.png',
];

// Configuration des traductions
$translations = [
    'recommaded_training' => 'Formations Recommandées',
    'training_duration' => 'Durée des Formations',
    'technicienss' => 'Techniciens',
    'Subsidiary' => 'de la Filiale',
    'tech_mesure' => 'Techniciens Mesurés',
    'tech_pif' => 'Techniciens avec PIF',
    'pif_filiale' => 'PIF Validés par la Filiale',
];

// Rendre les variables globales
$GLOBALS['brandLogos'] = $brandLogos;
$GLOBALS['translations'] = $translations;