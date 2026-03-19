<?php
session_start();
require_once "../../vendor/autoload.php";

// Vérification stricte de l'authentification
if (!isset($_SESSION['id']) || empty($_SESSION['id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Configuration sécurisée
error_reporting(E_ALL);
ini_set('display_errors', 0); // Désactivé en production
ini_set('log_errors', 1);

// Headers de sécurité
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

/**
 * Convertit sécurisément une chaîne en ObjectId MongoDB
 * @param mixed $id L'identifiant à convertir
 * @return MongoDB\BSON\ObjectId|null
 */
function safeObjectId($id) {
    try {
        // Vérifier si c'est déjà un ObjectId
        if (is_object($id) && get_class($id) === 'MongoDB\BSON\ObjectId') {
            return $id;
        }
        
        // Validation stricte de la chaîne ObjectId
        if (is_string($id) && preg_match('/^[a-f\d]{24}$/i', $id)) {
            return new MongoDB\BSON\ObjectId($id);
        }
        
        error_log("Invalid ObjectId format: " . var_export($id, true));
        return null;
        
    } catch (Exception $e) {
        error_log("Error creating ObjectId: " . $e->getMessage());
        return null;
    }
}

/**
 * Valide et sanitise les données d'événement
 * @param array $event Données de l'événement
 * @return array Données sanitisées
 */
function sanitizeEventData($event) {
    return [
        'name' => isset($event['name']) ? htmlspecialchars(trim($event['name']), ENT_QUOTES, 'UTF-8') : '',
        'description' => isset($event['description']) ? htmlspecialchars(trim($event['description']), ENT_QUOTES, 'UTF-8') : '',
        'location' => isset($event['location']) ? htmlspecialchars(trim($event['location']), ENT_QUOTES, 'UTF-8') : '',
        'startDate' => isset($event['startDate']) ? filter_var($event['startDate'], FILTER_SANITIZE_STRING) : '',
        'endDate' => isset($event['endDate']) ? filter_var($event['endDate'], FILTER_SANITIZE_STRING) : '',
        'startTime' => isset($event['startTime']) ? filter_var($event['startTime'], FILTER_SANITIZE_STRING) : '',
        'endTime' => isset($event['endTime']) ? filter_var($event['endTime'], FILTER_SANITIZE_STRING) : '',
        'allDay' => isset($event['allDay']) ? (bool)$event['allDay'] : false,
        'status' => isset($event['status']) ? filter_var($event['status'], FILTER_SANITIZE_STRING) : 'scheduled'
    ];
}

/**
 * Traite les techniciens d'un événement
 * @param array $technicianIds Liste des IDs de techniciens
 * @param MongoDB\Collection $users Collection des utilisateurs
 * @return array Liste des techniciens traités
 */
function processTechnicians($technicianIds, $users) {
    $technicians = [];
    
    if (!is_array($technicianIds) || empty($technicianIds)) {
        return $technicians;
    }
    
    error_log("Processing " . count($technicianIds) . " technicians");
    
    foreach ($technicianIds as $index => $techId) {
        try {
            $techObjectId = safeObjectId($techId);
            
            if ($techObjectId === null) {
                error_log("Invalid technician ID at index $index: " . var_export($techId, true));
                continue;
            }
            
            // Recherche sécurisée du technicien
            $tech = $users->findOne(
                ['_id' => $techObjectId],
                ['projection' => ['firstName' => 1, 'lastName' => 1, 'username' => 1, 'level' => 1, 'active' => 1]]
            );
            
            if ($tech && (!isset($tech['active']) || $tech['active'] === true)) {
                $firstName = isset($tech['firstName']) ? trim($tech['firstName']) : '';
                $lastName = isset($tech['lastName']) ? trim($tech['lastName']) : '';
                $fullName = trim($firstName . ' ' . $lastName);
                
                if (empty($fullName)) {
                    $fullName = isset($tech['username']) ? trim($tech['username']) : 'Technicien inconnu';
                }
                
                $level = isset($tech['level']) ? filter_var($tech['level'], FILTER_SANITIZE_STRING) : 'Non défini';
                
                $technicians[] = [
                    'id' => (string)$tech['_id'],
                    'name' => htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'),
                    'level' => htmlspecialchars($level, ENT_QUOTES, 'UTF-8')
                ];
                
                error_log("✓ Technician found: $fullName ($level)");
            } else {
                error_log("✗ Technician not found or inactive with ID: " . (string)$techObjectId);
                
                // Optionnel : ajouter un placeholder pour les techniciens non trouvés
                $technicians[] = [
                    'id' => (string)$techObjectId,
                    'name' => 'Technicien indisponible',
                    'level' => 'Inconnu'
                ];
            }
        } catch (Exception $e) {
            error_log("Error processing technician at index $index: " . $e->getMessage());
        }
    }
    
    return $technicians;
}

// Initialisation de la connexion MongoDB avec gestion d'erreurs robuste
$mongoAvailable = false;
$conn = null;
$academy = null;
$calendars = null;
$users = null;

try {
    $conn = new MongoDB\Client(
        "mongodb://localhost:27017",
        [],
        ['typeMap' => ['root' => 'array', 'document' => 'array']]
    );
    
    // Test de connexion
    $conn->listDatabases();
    
    $academy = $conn->academy;
    $calendars = $academy->calendars;
    $users = $academy->users;
    $mongoAvailable = true;
    
    error_log("MongoDB connection established successfully");
    
} catch (MongoDB\Driver\Exception\ConnectionTimeoutException $e) {
    error_log("MongoDB connection timeout: " . $e->getMessage());
    http_response_code(503);
    echo json_encode(['error' => 'Database temporarily unavailable']);
    exit;
} catch (Exception $e) {
    error_log("MongoDB connection error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

if (!$mongoAvailable) {
    http_response_code(500);
    echo json_encode(['error' => 'Database not available']);
    exit;
}

error_log("====== DÉBUT TRAITEMENT CALENDARS ======");

$eventList = [];

try {
    // Requête optimisée avec projection pour limiter les données récupérées
    $projection = [
        'name' => 1,
        'description' => 1,
        'location' => 1,
        'startDate' => 1,
        'endDate' => 1,
        'startTime' => 1,
        'endTime' => 1,
        'allDay' => 1,
        'status' => 1,
        'technicians' => 1,
        'active' => 1
    ];
    
    $events = $calendars->find(
        ['active' => true],
        ['projection' => $projection, 'sort' => ['startDate' => 1]]
    );
    
    foreach ($events as $event) {
        $eventId = (string)$event['_id'];
        error_log("Processing event: " . ($event['name'] ?? 'Unnamed') . " (ID: $eventId)");
        
        // Sanitisation des données de l'événement
        $sanitizedEvent = sanitizeEventData($event);
        
        // Traitement des techniciens
        $technicians = [];
        if (isset($event['technicians'])) {
            $technicians = processTechnicians($event['technicians'], $users);
        }
        
        // Construction de l'objet événement final
        $eventData = array_merge($sanitizedEvent, [
            'id' => $eventId,
            'technicians' => $technicians
        ]);
        
        $eventList[] = $eventData;
    }
    
    error_log("Successfully processed " . count($eventList) . " events");
    
} catch (Exception $e) {
    error_log("Error processing events: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error processing calendar events']);
    exit;
}

error_log("====== FIN TRAITEMENT CALENDARS ======");

// Log statistiques finales
$eventsWithTechnicians = count(array_filter($eventList, function($e) { 
    return !empty($e['technicians']); 
}));

error_log("Final response: " . count($eventList) . " events, " . $eventsWithTechnicians . " with technicians");

// Envoi de la réponse JSON avec gestion d'erreurs
if (json_encode($eventList) === false) {
    error_log("JSON encoding error: " . json_last_error_msg());
    http_response_code(500);
    echo json_encode(['error' => 'Data encoding error']);
    exit;
}

// Réponse finale
echo json_encode($eventList, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
?>