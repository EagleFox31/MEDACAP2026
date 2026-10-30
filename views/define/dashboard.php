<?php
// Fichier : views/define/dashboard.php
/**
 * Point d'entrée du dashboard modularisé
 * Ce fichier détermine le profil de l'utilisateur et route vers le contrôleur approprié
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);


// Initialisation de la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérification de l'authentification
if (!isset($_SESSION['id']) || !isset($_SESSION['profile'])) {
    header("Location: ../../");
    exit();
}

// Inclusion des fichiers core avec vérification d'existence
$requiredFiles = [
    __DIR__ . "/core/config.php",
    __DIR__ . "/core/database.php", 
    __DIR__ . "/core/constants.php",
    __DIR__ . "/../../vendor/autoload.php"
];

foreach ($requiredFiles as $file) {
    if (!file_exists($file)) {
        die("Fichier requis manquant: " . htmlspecialchars($file));
    }
    require_once $file;
}

// Importation des contrôleurs avec vérification
$controllerFiles = [
    "controllers/TechnicianDashboardController.php",
    "controllers/ManagerDashboardController.php", 
    "controllers/DirectorDashboardController.php",
    "controllers/FilterController.php"
];

foreach ($controllerFiles as $controllerFile) {
    $fullPath = __DIR__ . "/" . $controllerFile;
    if (!file_exists($fullPath)) {
        die("Contrôleur manquant: " . htmlspecialchars($controllerFile));
    }
    require_once $fullPath;
}

// Connexion à MongoDB — même résolution que l'API portail (127.0.0.1 → 10.68.0.7 en dev).
require_once __DIR__ . '/core/portal-mongo.php';

try {
    $academy = definePortalMongoDatabase();
    $GLOBALS['defineAcademy'] = $academy;

    // Test de connexion
    $academy->command(['ping' => 1]);
} catch (Exception $e) {
    error_log("Erreur MongoDB: " . $e->getMessage());
    die("Erreur de connexion à la base de données. Veuillez réessayer plus tard.");
}

// Initialisation du contrôleur de filtres
try {
    $filterController = new FilterController($academy);
    $filters = $filterController->getFilters();
} catch (Exception $e) {
    error_log("Erreur FilterController: " . $e->getMessage());
    $filters = []; // Valeur par défaut en cas d'erreur
}

// Récupération sécurisée des paramètres
$technicianId = isset($_GET['technicianId']) ? strip_tags((string) $_GET['technicianId']) : null;
if ($technicianId) {
    // ➜ on le remet dans $_GET pour que getFilters() le voie tout de suite
    $_GET['technicianId'] = $technicianId;
}
$filterController = new FilterController($academy);
$filters = $filterController->getFilters();   // 🟢 maintenant hydrateFiltersFromTechnician() s’exécutera
$profile = $_SESSION['profile'] ?? '';
$currentUserId = $_SESSION['id'] ?? '';

// Validation des données utilisateur
if (empty($profile) || empty($currentUserId)) {
    session_destroy();
    header("Location: ../../");
    exit();
}

// Définition des profils de directeurs (incluant Super Admin et Admin)
$directorProfiles = [
    'Super Admin',
    'Admin',
    'Directeur Général',
    'Directeur Groupe',
    'Directeur Pièce et Service',
    'Directeur des Opérations'
];

// Portail CFAO — DPS connecté, vue dashboard technicien (?viewAs=technician)
$viewAs = isset($_GET['viewAs']) ? strtolower(trim((string) $_GET['viewAs'])) : '';
$viewerProfilesForTechnicianView = array_merge($directorProfiles, ['Manager']);

if (!empty($technicianId) && $viewAs === 'technician' && in_array($profile, $viewerProfilesForTechnicianView, true)) {
    try {
        $techController = new TechnicianDashboardController($academy, $technicianId);
        $techController->renderDashboard($filters);
    } catch (Exception $e) {
        error_log('Erreur TechnicianDashboard (viewAs): ' . $e->getMessage());
        echo "<div class='alert alert-danger'>Erreur lors du chargement du tableau de bord du technicien.</div>";
    }
    exit();
}

// Gestion du technicien spécifique ou de la vue "all" pour les directeurs
if (!empty($technicianId)) {
    // Vérifier si c'est un directeur (incluant Super Admin et Admin)
    $isDirector = in_array($profile, $directorProfiles);
    
    if ($isDirector) {
        // Les directeurs doivent toujours voir le tableau de bord directeur, peu importe la valeur de technicianId
        try {
            $directorController = new DirectorDashboardController($academy);
            // Passer le technicianId aux filtres pour que le contrôleur directeur puisse l'utiliser
            $filters['technicianId'] = $technicianId;
            $directorController->renderDashboard($filters);
        } catch (Exception $e) {
            error_log("Erreur DirectorDashboard: " . $e->getMessage());
            echo "<div class='alert alert-danger'>Erreur lors du chargement du tableau de bord directeur.</div>";
        }
    } else {
        // Uniquement les non-directeurs avec technicianId spécifié vont au dashboard technicien
        try {
            $techController = new TechnicianDashboardController($academy, $technicianId);
            $techController->renderDashboard($filters);
        } catch (Exception $e) {
            error_log("Erreur TechnicianDashboard: " . $e->getMessage());
            echo "<div class='alert alert-danger'>Erreur lors du chargement du tableau de bord du technicien.</div>";
        }
    }
    exit();
}

// Routage basé sur le profil utilisateur
try {
    switch ($profile) {
        case 'Technicien':
            $controller = new TechnicianDashboardController($academy, $currentUserId);
            $controller->renderDashboard($filters);
            break;
            
        case 'Manager':
            $controller = new ManagerDashboardController($academy);
            $controller->renderDashboard($filters);
            break;
            
        case 'Super Admin':
        case 'Admin':
        case 'Directeur Général':
        case 'Directeur Groupe':
        case 'Directeur Pièce et Service':
        case 'Directeur des Opérations':
            $controller = new DirectorDashboardController($academy);
            $controller->renderDashboard($filters);
            break;
            
        default:
            error_log("Profil non reconnu: " . $profile);
            echo "<div class='alert alert-warning'>Profil non autorisé pour ce tableau de bord.</div>";
            echo "<p><a href='../header.php' class='btn btn-primary'>Retour à la connexion</a></p>";
            break;
    }
} catch (Exception $e) {
    error_log("Erreur lors du rendu du dashboard: " . $e->getMessage());
    echo "<div class='alert alert-danger'>Erreur lors du chargement du tableau de bord.</div>";
    echo "<p><a href='..header.php' class='btn btn-primary'>Retour à la connexion</a></p>";
}
?>