<?php
session_start();
include_once "../language.php";
include_once "../partials/background-manager.php"; // Système de gestion de fond d'écran

// Flag to track if MongoDB is available
$mongoAvailable = false;

// Try to load MongoDB dependencies
$autoloadPath = "../../vendor/autoload.php";
if (file_exists($autoloadPath)) {
    try {
        require_once $autoloadPath;
        // Only set MongoDB as available if we can actually load the required classes
        $mongoAvailable = class_exists('MongoDB\Client') && class_exists('MongoDB\BSON\ObjectId');
    } catch (Exception $e) {
        error_log("Error loading MongoDB dependencies: " . $e->getMessage());
        $mongoAvailable = false;
    }
} else {
    // Fallback if it doesn't exist in the expected location
    $altPath = $_SERVER['DOCUMENT_ROOT'] . "/vendor/autoload.php";
    if (file_exists($altPath)) {
        try {
            require_once $altPath;
            // Only set MongoDB as available if we can actually load the required classes
            $mongoAvailable = class_exists('MongoDB\Client') && class_exists('MongoDB\BSON\ObjectId');
        } catch (Exception $e) {
            error_log("Error loading MongoDB dependencies: " . $e->getMessage());
            $mongoAvailable = false;
        }
    }
}

// Ensure we check if MongoDB exists as early as possible
if (!$mongoAvailable) {
    error_log("MongoDB not available, falling back to file-based storage");
}

// Helper function to safely create MongoDB ObjectId
function safeObjectId($id) {
    try {
        if (class_exists('MongoDB\BSON\ObjectId')) {
            // Use string class name to avoid static analysis errors
            $className = '\MongoDB\BSON\ObjectId';
            return new $className($id);
        }
    } catch (Exception $e) {
        error_log("Error creating MongoDB ObjectId: " . $e->getMessage());
    }
    return $id; // Return the ID as is if ObjectId class is not available or on error
}

// Define a fallback file storage path for calendar events if MongoDB isn't available
$calendarJsonFile = __DIR__ . '/calendar_events.json';

// Check if user is logged in
if (!isset($_SESSION["id"])) {
    header("Location: ../");
    exit();
} else {
    // Initialize storage mechanism
    if ($mongoAvailable) {
        try {
            // We already verified MongoDB availability, now attempt connection
            $conn = new \MongoDB\Client("mongodb://localhost:27017");
            
            // Connecting in database
            $academy = $conn->academy;
            
            // Connecting in collections
            $calendars = $academy->calendars;
            $users = $academy->users;
            $tests = $academy->tests;
        } catch (Exception $e) {
            // If MongoDB connection fails, fall back to file storage
            $mongoAvailable = false;
            error_log("MongoDB connection error: " . $e->getMessage());
        }
    }

    // Function to get events from file
    function getEventsFromFile($filePath) {
        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);
            return json_decode($content, true) ?: [];
        }
        return [];
    }

    // Function to save events to file
    function saveEventsToFile($events, $filePath) {
        return file_put_contents($filePath, json_encode($events, JSON_PRETTY_PRINT));
    }

    // Handle event submission
// Handle event submission
if (isset($_POST['submit'])) {
    // Debug : afficher toutes les données POST
    error_log("POST data: " . print_r($_POST, true));
    
    $eventName = $_POST["eventName"] ?? $_POST["calendar_event_name"] ?? '';
    $eventDescription = $_POST["eventDescription"] ?? $_POST["calendar_event_description"] ?? '';
    $eventLocation = $_POST["eventLocation"] ?? $_POST["calendar_event_location"] ?? '';
    $startDate = $_POST["startDate"] ?? $_POST["calendar_event_start_date"] ?? '';
    $endDate = $_POST["endDate"] ?? $_POST["calendar_event_end_date"] ?? '';
    $startTime = $_POST["startTime"] ?? $_POST["calendar_event_start_time"] ?? '';
    $endTime = $_POST["endTime"] ?? $_POST["calendar_event_end_time"] ?? '';
    $allDay = $_POST["allDay"] ?? ($_POST["kt_calendar_datepicker_allday"] ?? 'non');
    
    // Récupérer les techniciens - tester plusieurs noms possibles
    $technicianIds = $_POST["calendar_technicians"] ?? $_POST["technicianIds"] ?? [];
    
    // Debug : afficher les IDs des techniciens
    error_log("Technician IDs received: " . print_r($technicianIds, true));
    
    if (!$eventName || !$startDate || !$endDate) {
        $error_msg = $champ_obligatoire;
    } else {
        // Nettoyer et valider les IDs des techniciens
        $validTechnicianIds = [];
        if (!empty($technicianIds) && is_array($technicianIds)) {
            foreach ($technicianIds as $techId) {
                if (!empty($techId) && $techId !== '') {
                    $validTechnicianIds[] = trim($techId);
                }
            }
        }
        
        error_log("Valid Technician IDs: " . print_r($validTechnicianIds, true));
        
        // Convertir les IDs des techniciens en ObjectId pour MongoDB
        $technicianObjectIds = [];
        if ($mongoAvailable && !empty($validTechnicianIds)) {
            foreach ($validTechnicianIds as $techId) {
                try {
                    $objectId = safeObjectId($techId);
                    $technicianObjectIds[] = $objectId;
                    error_log("Converted $techId to ObjectId: " . (string)$objectId);
                } catch (Exception $e) {
                    error_log("Error converting technician ID '$techId' to ObjectId: " . $e->getMessage());
                }
            }
        }
        
        error_log("Final ObjectIds: " . print_r($technicianObjectIds, true));
        
        $calendar = [
            "id" => time() . rand(1000, 9999), // Generate a unique ID
            "name" => $eventName,
            "description" => $eventDescription,
            "location" => $eventLocation,
            "startDate" => $startDate,
            "endDate" => $endDate,
            "startTime" => $startTime,
            "endTime" => $endTime,
            "allDay" => ($allDay == 'oui' || $allDay == 'on'),
            "active" => true,
            "created" => date("d-m-Y H:i:s"),
            "technicians" => $mongoAvailable ? $technicianObjectIds : $validTechnicianIds,
            "status" => "scheduled",
            "reminderStatus" => [
                "confirmation" => true,
                "dayBefore" => false,
                "dayOf" => false,
                "dayAfter" => false
            ]
        ];
        
        // Debug : afficher l'objet calendar final
        error_log("Calendar object to save: " . print_r($calendar, true));
        
        // Après la sauvegarde de l'événement
        if ($mongoAvailable) {
            // Get technician information for email
            $technicianDocs = [];
            if (!empty($technicianObjectIds)) {
                foreach ($technicianObjectIds as $techObjectId) {
                    try {
                        $tech = $users->findOne(['_id' => $techObjectId]);
                        if ($tech) {
                            $technicianDocs[] = $tech;
                        }
                    } catch (Exception $e) {
                        error_log("Error finding technician: " . $e->getMessage());
                    }
                }
            }
            
            // Debug log
            error_log("Technician docs found: " . count($technicianDocs));
            
            // Get admin docs
            $adminDocs = [];
            $admins = $users->find([
                'profile' => ['$in' => ['Admin', 'Super Admin']],
                'active' => true,
                'subsidiary' => $_SESSION["subsidiary"] ?? null
            ]);
            
            foreach ($admins as $admin) {
                $adminDocs[] = $admin;
            }
            
            // Send email même si pas de techniciens (optionnel)
            if (!empty($adminDocs)) {
                if (file_exists("sendMail.php")) {
                    require_once "sendMail.php";
                    if (function_exists('sendTestScheduledMail')) {
                        $emailSent = sendTestScheduledMail($adminDocs, $technicianDocs, $calendar);
                        error_log("Email sent: " . ($emailSent ? 'true' : 'false'));
                    }
                }
            }
        }
        
        // Store the event
        if ($mongoAvailable) {
            try {
                $result = $calendars->insertOne($calendar);
                error_log("MongoDB insert result: " . print_r($result, true));
            } catch (Exception $e) {
                error_log("Error inserting calendar event: " . $e->getMessage());
                
                // Fallback to file storage - utiliser les IDs string pour le fichier JSON
                $calendarForFile = $calendar;
                $calendarForFile['technicians'] = $validTechnicianIds; // Utiliser les IDs string pour le fichier
                $events = getEventsFromFile($calendarJsonFile);
                $events[] = $calendarForFile;
                saveEventsToFile($events, $calendarJsonFile);
            }
        } else {
            // Use file storage - utiliser les IDs string
            $events = getEventsFromFile($calendarJsonFile);
            $events[] = $calendar;
            saveEventsToFile($events, $calendarJsonFile);
        }
    }
}
    
    // Handle event update
    if (isset($_POST['update'])) {
        $id = $_POST["id"];
        $eventName = $_POST["eventName"];
        $eventDescription = $_POST["eventDescription"];
        $eventLocation = $_POST["eventLocation"];
        $startDate = $_POST["startDate"];
        $endDate = $_POST["endDate"];
        $startTime = $_POST["startTime"];
        $endTime = $_POST["endTime"];
        $allDay = $_POST["allDay"];
        
        // Récupérer les techniciens
        $technicianIds = $_POST["calendar_technicians"] ?? [];
        
        // Debug : afficher les IDs des techniciens
        error_log("Update - Technician IDs received: " . print_r($technicianIds, true));
        
        if (!$eventName || !$startDate || !$endDate) {
            $error_msg = $champ_obligatoire;
        } else {
            // Nettoyer et valider les IDs des techniciens
            $validTechnicianIds = [];
            if (!empty($technicianIds) && is_array($technicianIds)) {
                foreach ($technicianIds as $techId) {
                    if (!empty($techId) && $techId !== '') {
                        $validTechnicianIds[] = trim($techId);
                    }
                }
            }
            
            error_log("Update - Valid Technician IDs: " . print_r($validTechnicianIds, true));
            
            // Convertir les IDs des techniciens en ObjectId pour MongoDB
            $technicianObjectIds = [];
            if ($mongoAvailable && !empty($validTechnicianIds)) {
                foreach ($validTechnicianIds as $techId) {
                    try {
                        $objectId = safeObjectId($techId);
                        $technicianObjectIds[] = $objectId;
                        error_log("Update - Converted $techId to ObjectId: " . (string)$objectId);
                    } catch (Exception $e) {
                        error_log("Update - Error converting technician ID '$techId' to ObjectId: " . $e->getMessage());
                    }
                }
            }
            
            error_log("Update - Final ObjectIds: " . print_r($technicianObjectIds, true));
            
            $updatedEvent = [
                "name" => $eventName,
                "description" => $eventDescription,
                "location" => $eventLocation,
                "startDate" => $startDate,
                "endDate" => $endDate,
                "startTime" => $startTime,
                "endTime" => $endTime,
                "allDay" => ($allDay == 'oui'),
                "active" => true,
                "updated" => date("d-m-Y H:i:s"),
                "technicians" => $mongoAvailable ? $technicianObjectIds : $validTechnicianIds
            ];
            
            if ($mongoAvailable) {
                try {
                    // Use our helper function for ObjectId
                    $calendars->updateOne(
                        ["_id" => safeObjectId($id)],
                        ['$set' => $updatedEvent]
                    );
                } catch (Exception $e) {
                    error_log("Error updating calendar event: " . $e->getMessage());
                    
                    // Fallback to file storage
                    $events = getEventsFromFile($calendarJsonFile);
                    foreach ($events as $key => $event) {
                        if ($event['id'] == $id) {
                            $events[$key] = array_merge($event, $updatedEvent);
                            break;
                        }
                    }
                    saveEventsToFile($events, $calendarJsonFile);
                }
            } else {
                // Use file storage
                $events = getEventsFromFile($calendarJsonFile);
                foreach ($events as $key => $event) {
                    if ($event['id'] == $id) {
                        $events[$key] = array_merge($event, $updatedEvent);
                        break;
                    }
                }
                saveEventsToFile($events, $calendarJsonFile);
            }
        }
    }
    
    // Handle event deletion
    if (isset($_POST['delete'])) {
        $id = $_POST["id"];
        
        if ($mongoAvailable) {
            try {
                // Use our helper function for ObjectId
                $calendars->updateOne(
                    ["_id" => safeObjectId($id)],
                    ['$set' => ["active" => false]]
                );
            } catch (Exception $e) {
                error_log("Error deleting calendar event: " . $e->getMessage());
                
                // Fallback to file storage
                $events = getEventsFromFile($calendarJsonFile);
                foreach ($events as $key => $event) {
                    if ($event['id'] == $id) {
                        $events[$key]['active'] = false;
                        break;
                    }
                }
                saveEventsToFile($events, $calendarJsonFile);
            }
        } else {
            // Use file storage
            $events = getEventsFromFile($calendarJsonFile);
            foreach ($events as $key => $event) {
                if ($event['id'] == $id) {
                    $events[$key]['active'] = false;
                    break;
                }
            }
            saveEventsToFile($events, $calendarJsonFile);
        }
    }
    
    // Make sure the calendar JSON file exists
    if (!file_exists($calendarJsonFile)) {
        file_put_contents($calendarJsonFile, json_encode([]));
    }
    
    // Create the display_event.php file if it doesn't exist
    $displayEventPath = __DIR__ . '/display_event.php';
    if (!file_exists($displayEventPath)) {
        $displayEventContent = '<?php
session_start();
include_once "../language.php";

// Flag to track if MongoDB is available
$mongoAvailable = false;

// Try to load MongoDB dependencies
$autoloadPath = "../../vendor/autoload.php";
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
    $mongoAvailable = class_exists(\'MongoDB\\\\Client\') && class_exists(\'MongoDB\\\\BSON\\\\ObjectId\');
} else {
    // Fallback if it doesn\'t exist in the expected location
    $altPath = $_SERVER[\'DOCUMENT_ROOT\'] . "/vendor/autoload.php";
    if (file_exists($altPath)) {
        require_once $altPath;
        $mongoAvailable = class_exists(\'MongoDB\\\\Client\') && class_exists(\'MongoDB\\\\BSON\\\\ObjectId\');
    }
}

// Define a fallback file storage path for calendar events if MongoDB isn\'t available
$calendarJsonFile = __DIR__ . \'/calendar_events.json\';

if (!isset($_SESSION["id"])) {
    header("Location: ../");
    exit();
}

// Function to get events from file
function getEventsFromFile($filePath) {
    if (file_exists($filePath)) {
        $content = file_get_contents($filePath);
        return json_decode($content, true) ?: [];
    }
    return [];
}

// Get events
$eventList = [];

if ($mongoAvailable) {
    try {
        // Create connection
        $conn = new MongoDB\Client("mongodb://localhost:27017");
        
        // Connecting in database
        $academy = $conn->academy;
        
        // Connecting in collections
        $calendars = $academy->calendars;
        
        // Check if the MongoDB classes are properly loaded
        if (class_exists(\'MongoDB\\\\BSON\\\\ObjectId\')) {
            // Get active calendar events
            $events = $calendars->find([\'active\' => true]);
            
            foreach ($events as $event) {
                $eventList[] = [
                    \'id\' => (string)$event[\'_id\'],
                    \'name\' => $event[\'name\'],
                    \'description\' => $event[\'description\'] ?? \'\',
                    \'location\' => $event[\'location\'] ?? \'\',
                    \'startDate\' => $event[\'startDate\'],
                    \'endDate\' => $event[\'endDate\'],
                    \'startTime\' => $event[\'startTime\'] ?? \'\',
                    \'endTime\' => $event[\'endTime\'] ?? \'\',
                    \'allDay\' => $event[\'allDay\']
                ];
            }
        } else {
            // Fallback to file storage as MongoDB classes aren\'t properly loaded
            throw new Exception("MongoDB BSON classes not available");
        }
    } catch (Exception $e) {
        // Fallback to file storage
        $events = getEventsFromFile($calendarJsonFile);
        foreach ($events as $event) {
            if ($event[\'active\']) {
                $eventList[] = $event;
            }
        }
    }
} else {
    // Use file storage
    $events = getEventsFromFile($calendarJsonFile);
    foreach ($events as $event) {
        if ($event[\'active\']) {
            $eventList[] = $event;
        }
    }
}

// Return JSON response
header(\'Content-Type: application/json\');
echo json_encode($eventList);
';
        file_put_contents($displayEventPath, $displayEventContent);
    }

    include_once "partials/header.php";
?>

<!--begin::Title-->
<title><?= $calendar ?> | CFAO Mobility Academy</title>
<!--end::Title-->

<style>
/* Glassmorphism effect for cards */
.glassmorphism {
    background: rgba(255, 255, 255, 0.8) !important;
    backdrop-filter: blur(18px) !important; /* Higher blur value as requested */
    border-radius: 15px !important;
    border: 1px solid rgba(255, 255, 255, 0.18) !important;
    box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.2) !important;
}

/* White background for the calendar container only */
#kt_calendar_app {
    background-color: #ffffff !important;
}
</style>

<?php
// Définir le fond d'écran pour cette page
setPageBackground('bg-dashboard', true);

// Ouvrir le conteneur de fond d'écran
openBackgroundContainer('', 'id="kt_content"');
?>
    <!-- Main title card -->
    <div class="container-xxl">
        <div class="card shadow-sm mb-5 w-75 mx-auto">
            <div class="card-body py-3">
                <h1 class="text-dark fw-bold text-center fs-1 m-0">
                    <?= $calendar ?>
                </h1>
            </div>
        </div>
    </div>

    <!--begin::Post-->
    <div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
        <!--begin::Container-->
        <div class="container-xxl">
            <!--begin::Card-->
            <div class="card glassmorphism">
                <!--begin::Card header-->
                <div class="card-header">
                    <h2 class="card-title fw-bold"><?= $calendar ?></h2>
                    <div class="card-toolbar">
                        <button class="btn btn-flex btn-primary" data-kt-calendar="add">
                            <i class="ki-duotone ki-plus fs-2"></i> <?= $addEvent ?>
                        </button>
                    </div>
                </div>
                <!--end::Card header-->

                <!--begin::Card body-->
                <div class="card-body">
                    <div id="kt_calendar_app"></div>
                </div>
                <!--end::Card body-->
            </div>
            <!--end::Card-->

            <!-- ================= MODALES ================= -->

            <!--begin::Modal – add / edit-->
            <div class="modal fade" id="kt_modal_add_event" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered mw-650px">
                    <div class="modal-content">
                        <form class="form" id="kt_modal_add_event_form" method="post" action="#">
                            <div class="modal-header">
                                <h2 class="fw-bold" data-kt-calendar="title"><?= $addEvent ?></h2>
                                <button type="button" class="btn btn-icon btn-sm"
                                        id="kt_modal_add_event_close"
                                        data-bs-dismiss="modal" aria-label="Close">
                                    <i class="ki-duotone ki-cross fs-1"></i>
                                </button>
                            </div>

                            <div class="modal-body py-10 px-lg-17">
                                <!-- ====== champs de saisie (inchangés) ====== -->
                                <div class="fv-row mb-9">
                                    <label class="fs-6 fw-semibold required mb-2"><?= $eventName ?></label>
                                    <input type="text" class="form-control form-control-solid hidden"
                                           name="calendar_id">
                                    <input type="text" class="form-control form-control-solid"
                                           name="calendar_event_name">
                                </div>

                                <div class="fv-row mb-9">
                                    <label class="fs-6 fw-semibold mb-2"><?= $eventDescription ?></label>
                                    <input type="text" class="form-control form-control-solid"
                                           name="calendar_event_description">
                                </div>

                                <div class="fv-row mb-9">
                                    <label class="fs-6 fw-semibold mb-2"><?= $location ?></label>
                                    <input type="text" class="form-control form-control-solid"
                                           name="calendar_event_location">
                                </div>

                                <!-- Techniciens -->
                                <div class="fv-row mb-9">
                                    <label class="fs-6 fw-semibold mb-2">Techniciens concernés</label>

                                    <select id="calendar_technicians"
                                            name="calendar_technicians[]"
                                            multiple
                                            data-control="select2"
                                            data-placeholder="<?= $select_technician ?? 'Sélectionner des techniciens' ?>"
                                            class="form-select form-select-solid fw-bold"
                                            aria-label="Select Technicians">
                                        <option value="">-- <?= $select_technician ?? 'Sélectionner des techniciens' ?> --</option>
                                        <?php
                                        if ($mongoAvailable) {
                                            try {
                                                $subs = $_SESSION['subsidiary'] ?? '';
                                                $query = ['profile'=>'Technicien','active'=>true];
                                                if ($subs) $query['subsidiary'] = $subs;

                                                foreach ($users->find($query) as $tech) {
                                                    echo '<option value="'.$tech['_id'].'">'.
                                                         htmlspecialchars($tech['firstName']).' '.
                                                         htmlspecialchars($tech['lastName']).' ('.
                                                         htmlspecialchars($tech['level']).')</option>';
                                                }
                                            } catch (Exception $e) {
                                                echo '<option disabled>MongoDB error – Techniciens non disponibles</option>';
                                            }
                                        } else {
                                            echo '<option disabled>MongoDB non disponible</option>';
                                        }
                                        ?>
                                    </select>
                                </div>

                                <div class="fv-row mb-9">
                                    <label class="form-check form-check-custom form-check-solid">
                                        <input class="form-check-input" type="checkbox" id="kt_calendar_datepicker_allday">
                                        <span class="form-check-label fw-semibold text-black ms-4"><?= $allDay ?></span>
                                    </label>
                                </div>

                                <!-- Dates / heures -->
                                <div class="row row-cols-lg-2 g-10">
                                    <div class="col">
                                        <div class="fv-row mb-9">
                                            <label class="fs-6 fw-semibold mb-2 required"><?= $startDate ?></label>
                                            <input class="form-control form-control-solid"
                                                   id="kt_calendar_datepicker_start_date"
                                                   name="calendar_event_start_date">
                                        </div>
                                    </div>
                                    <div class="col" data-kt-calendar="datepicker">
                                        <div class="fv-row mb-9">
                                            <label class="fs-6 fw-semibold mb-2"><?= $startTime ?></label>
                                            <input class="form-control form-control-solid"
                                                   id="kt_calendar_datepicker_start_time"
                                                   name="calendar_event_start_time">
                                        </div>
                                    </div>
                                </div>

                                <div class="row row-cols-lg-2 g-10">
                                    <div class="col">
                                        <div class="fv-row mb-9">
                                            <label class="fs-6 fw-semibold mb-2 required"><?= $endDate ?></label>
                                            <input class="form-control form-control-solid"
                                                   id="kt_calendar_datepicker_end_date"
                                                   name="calendar_event_end_date">
                                        </div>
                                    </div>
                                    <div class="col" data-kt-calendar="datepicker">
                                        <div class="fv-row mb-9">
                                            <label class="fs-6 fw-semibold mb-2"><?= $endTime ?></label>
                                            <input class="form-control form-control-solid"
                                                   id="kt_calendar_datepicker_end_time"
                                                   name="calendar_event_end_time">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="modal-footer flex-center">
                                <button type="reset" class="btn btn-light me-3"
                                        id="kt_modal_add_event_cancel"><?= $annuler ?></button>
                                <button type="button" class="btn btn-primary"
                                        id="kt_modal_add_event_submit">
                                    <span class="indicator-label"><?= $valider ?></span>
                                    <span class="indicator-progress">Please wait…
                                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                    </span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <!--end::Modal – add / edit-->

            <!--begin::Modal – view-->
            <div class="modal fade" id="kt_modal_view_event" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered mw-650px">
                    <div class="modal-content">
                        <div class="modal-header border-0 justify-content-end">
                            <button class="btn btn-icon btn-sm btn-color-gray-400 me-2"
                                    id="kt_modal_view_event_edit"
                                    data-bs-toggle="tooltip" title="Edit Event">
                                <i class="ki-duotone ki-pencil fs-2"></i>
                            </button>
                            <?php if ($_SESSION['profile'] === 'Super Admin') : ?>
                            <button class="btn btn-icon btn-sm btn-color-gray-400 me-2"
                                    id="kt_modal_view_event_delete"
                                    data-bs-toggle="tooltip" title="Delete Event">
                                <i class="ki-duotone ki-trash fs-2"></i>
                            </button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-icon btn-sm btn-color-gray-500"
                                    data-bs-dismiss="modal" aria-label="Hide Event">
                                <i class="ki-duotone ki-cross fs-2x"></i>
                            </button>
                        </div>

                        <div class="modal-body pt-0 pb-20 px-lg-17">
                            <div class="d-flex">
                                <i class="ki-duotone ki-calendar-8 fs-1 text-muted me-5"></i>
                                <div class="mb-9">
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="fs-3 fw-bold me-3" data-kt-calendar="event_name"></span>
                                        <span class="badge badge-light-success"
                                              data-kt-calendar="all_day"></span>
                                    </div>
                                    <div class="fs-6 hidden" data-kt-calendar="event_id"></div>
                                    <div class="fs-6" data-kt-calendar="event_description"></div>
                                </div>
                            </div>

                            <div class="d-flex align-items-center mb-9" id="technicians-row" style="display:none;">
                                <i class="ki-duotone ki-people fs-1 text-muted me-5"></i>
                                <div class="fs-6">
                                    <span class="fw-bold">Techniciens :</span>
                                    <div data-kt-calendar="event_technicians"></div>
                                </div>
                            </div>

                            <div class="d-flex align-items-center mb-2">
                                <span class="bullet bullet-dot h-10px w-10px bg-success ms-2 me-7"></span>
                                <div class="fs-6"><span class="fw-bold"><?= $start ?></span>
                                    <span data-kt-calendar="event_start_date"></span>
                                </div>
                            </div>

                            <div class="d-flex align-items-center mb-9">
                                <span class="bullet bullet-dot h-10px w-10px bg-danger ms-2 me-7"></span>
                                <div class="fs-6"><span class="fw-bold"><?= $end ?></span>
                                    <span data-kt-calendar="event_end_date"></span>
                                </div>
                            </div>

                            <div class="d-flex align-items-center">
                                <i class="ki-duotone ki-geolocation fs-1 text-muted me-5"></i>
                                <div class="fs-6" data-kt-calendar="event_location"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!--end::Modal – view-->

            <!-- =============== /MODALES =============== -->
        </div>
        <!--end::Container-->
    </div>
    <!--end::Post-->
<?php closeBackgroundContainer(); ?>
<!--end::Content-->
<!--end::Body-->

<script src="https://cdn.jsdelivr.net/npm/moment@2.30.1/min/moment.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/moment@2.30.1/min/moment.min.js"></script>
<script>
    "use strict";

    // Class definition
    var KTAppCalendar = function () {
        // Shared variables
        // Calendar variables
        var calendar;
        var data = {
            id: '',
            eventName: '',
            eventDescription: '',
            eventLocation: '',
            startDate: '',
            endDate: '',
            allDay: false
        };

        // Add event variables
        var id;
        var eventName;
        var eventDescription;
        var eventLocation;
        var startDatepicker;
        var startFlatpickr;
        var endDatepicker;
        var endFlatpickr;
        var startTimepicker;
        var startTimeFlatpickr;
        var endTimepicker
        var endTimeFlatpickr;
        var allDaypicker;
        var modal;
        var modalTitle;
        var form;
        var validator;
        var addButton;
        var submitButton;
        var cancelButton;
        var closeButton;
        var datas = new Array();

        // View event variables - DÉCLARATION DÉPLACÉE ICI
        var viewDom;
        var viewId;
        var viewEventName;
        var viewAllDay;
        var viewEventDescription;
        var viewEventLocation;
        var viewStartDate;
        var viewEndDate;
        var viewModal;
        var viewEditButton;
        var viewDeleteButton;

        // Fonction pour récupérer les techniciens sélectionnés
        const getSelectedTechnicians = () => {
            const technicianSelect = document.getElementById('calendar_technicians');
            const selectedTechnicians = [];
            
            if (technicianSelect) {
                // Pour Select2, utiliser la méthode Select2 pour récupérer les valeurs
                try {
                    const select2Data = $('#calendar_technicians').select2('data');
                    select2Data.forEach(function(item) {
                        if (item.id && item.id !== '') {
                            selectedTechnicians.push(item.id);
                        }
                    });
                } catch (e) {
                    // Si Select2 n'est pas initialisé, utiliser la méthode standard
                    const selectedOptions = technicianSelect.selectedOptions;
                    for (let option of selectedOptions) {
                        if (option.value && option.value !== '') {
                            selectedTechnicians.push(option.value);
                        }
                    }
                }
            }
            
            console.log('Selected technicians:', selectedTechnicians);
            return selectedTechnicians;
        };

        // Private functions
        var initCalendarApp = function () {
            // Define variables
            var calendarEl = document.getElementById('kt_calendar_app');
            var todayDate = moment().startOf('day');
            var YM = todayDate.format('YYYY-MM');
            var YESTERDAY = todayDate.clone().subtract(1, 'day').format('YYYY-MM-DD');
            var TODAY = todayDate.format('YYYY-MM-DD');
            var TOMORROW = todayDate.clone().add(1, 'day').format('YYYY-MM-DD');

            // Init calendar --- more info: https://fullcalendar.io/docs/initialize-globals
            calendar = new FullCalendar.Calendar(calendarEl, {
                locale: 'fr', // Set local --- more info: https://fullcalendar.io/docs/locale
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                initialDate: TODAY,
                navLinks: true, // can click day/week names to navigate views
                selectable: true,
                selectMirror: true,

                // Select dates action --- more info: https://fullcalendar.io/docs/select-callback
                select: function (arg) {
                    formatArgs(arg);
                    handleNewEvent();
                },

                // Click event --- more info: https://fullcalendar.io/docs/eventClick
                eventClick: function (arg) {
                    console.log("Événement cliqué:", arg.event);
                    console.log("Propriétés étendues:", arg.event.extendedProps);
                    console.log("Techniciens dans l'événement:", arg.event.extendedProps.technicians);
                    
                    // Récupérer les données de l'événement avec vérification approfondie des techniciens
                    const technicians = arg.event.extendedProps.technicians;
                    console.log("Type de technicians:", typeof technicians, Array.isArray(technicians));
                    console.log("Contenu brut des techniciens:", JSON.stringify(technicians));
                    
                    // Vérifier et traiter les techniciens
                    let processedTechnicians = [];
                    if (technicians && Array.isArray(technicians)) {
                        processedTechnicians = technicians;
                        console.log("Techniciens traités:", processedTechnicians);
                    }
                    
                    const eventData = {
                        id:          arg.event.id,
                        title:       arg.event.title,
                        description: arg.event.extendedProps.description,
                        location:    arg.event.extendedProps.location,
                        technicians: processedTechnicians,
                        startStr:    arg.event.startStr,
                        endStr:      arg.event.endStr,
                        allDay:      arg.event.allDay
                    };
                    
                    console.log("Données formatées pour affichage:", eventData);
                    formatArgs(eventData);
                    handleViewEvent();
                },

                editable: true,
                dayMaxEvents: true, // allow "more" link when too many events
                events: datas,

                // Handle changing calendar views --- more info: https://fullcalendar.io/docs/datesSet
                datesSet: function(){
                    // do some stuff
                }
            });

            calendar.render();
        }

        // Init validator
        const initValidator = () => {
            // Init form validation rules. For more info check the FormValidation plugin's official documentation:https://formvalidation.io/
            validator = FormValidation.formValidation(
                form,
                {
                    fields: {
                        'calendar_event_name': {
                            validators: {
                                notEmpty: {
                                    message: 'Event name is required'
                                }
                            }
                        },
                        'calendar_event_start_date': {
                            validators: {
                                notEmpty: {
                                    message: 'Start date is required'
                                }
                            }
                        },
                        'calendar_event_end_date': {
                            validators: {
                                notEmpty: {
                                    message: 'End date is required'
                                }
                            }
                        }
                    },

                    plugins: {
                        trigger: new FormValidation.plugins.Trigger(),
                        bootstrap: new FormValidation.plugins.Bootstrap5({
                            rowSelector: '.fv-row',
                            eleInvalidClass: '',
                            eleValidClass: ''
                        })
                    }
                }
            );
        }

        // Initialize datepickers --- more info: https://flatpickr.js.org/
        const initDatepickers = () => {
            startFlatpickr = flatpickr(startDatepicker, {
                enableTime: false,
                dateFormat: "Y-m-d",
            });

            endFlatpickr = flatpickr(endDatepicker, {
                enableTime: false,
                dateFormat: "Y-m-d",
            });

            startTimeFlatpickr = flatpickr(startTimepicker, {
                enableTime: true,
                noCalendar: true,
                dateFormat: "H:i",
            });

            endTimeFlatpickr = flatpickr(endTimepicker, {
                enableTime: true,
                noCalendar: true,
                dateFormat: "H:i",
            });
        }

        // Initialize Select2 for technicians
        const initSelect2 = () => {
            $(document).ready(function() {
                if ($('#calendar_technicians').length) {
                    $('#calendar_technicians').select2({
                        placeholder: "Sélectionner des techniciens",
                        allowClear: true,
                        width: '100%'
                    });
                }
            });
        }

        // Handle add button
        const handleAddButton = () => {
            addButton.addEventListener('click', e => {
                // Reset form data
                data = {
                    id: '',
                    eventName: '',
                    eventDescription: '',
                    startDate: new Date(),
                    endDate: new Date(),
                    allDay: false
                };
                handleNewEvent();
            });
        }

        // Handle add new event
        const handleNewEvent = () => {
            // Update modal title
            modalTitle.innerText = "<?php echo $addEvent ?>";

            modal.show();

            // Select datepicker wrapper elements
            const datepickerWrappers = form.querySelectorAll('[data-kt-calendar="datepicker"]');

            // Handle all day toggle
            const allDayToggle = form.querySelector('#kt_calendar_datepicker_allday');
            allDayToggle.addEventListener('click', e => {
                if (e.target.checked) {
                    allDaypicker = "non";
                    datepickerWrappers.forEach(dw => {
                        dw.classList.add('d-none');
                    });
                } else {
                allDaypicker = "oui";
                    endFlatpickr.setDate(data.startDate, true, 'Y-m-d');
                    datepickerWrappers.forEach(dw => {
                        dw.classList.remove('d-none');
                    });
                }
            });

            populateForm(data);

            // Handle submit form - VERSION MODIFIÉE AVEC GESTION DES TECHNICIENS
            submitButton.addEventListener('click', function (e) {
                // Prevent default button action
                e.preventDefault();

                // Récupérer les techniciens sélectionnés
                const selectedTechnicians = getSelectedTechnicians();

                // Créer les données à envoyer
                const formData = new FormData();
                formData.append('submit', '1');
                formData.append('eventName', eventName.value);
                formData.append('eventDescription', eventDescription.value);
                formData.append('eventLocation', eventLocation.value);
                formData.append('startDate', startDatepicker.value);
                formData.append('endDate', endDatepicker.value);
                formData.append('startTime', startTimepicker.value);
                formData.append('endTime', endTimepicker.value);
                formData.append('allDay', allDaypicker);
                
                // Ajouter les techniciens sélectionnés
                selectedTechnicians.forEach(function(techId) {
                    formData.append('calendar_technicians[]', techId);
                });

                // Debug : afficher toutes les données
                console.log('Form data to send:');
                for (let pair of formData.entries()) {
                    console.log(pair[0] + ': ' + pair[1]);
                }

                // Envoyer via fetch
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(data => {
                    console.log('Response:', data);
                    modal.hide();
                    location.reload();
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            });
        }

        window.onload = (event) => {
            $.ajax({
                url: 'display_event.php',
                type: 'get',
                dataType: 'json',
                success: function (response){
                    var result = response;
                    
                    console.log("Données complètes reçues du serveur:", result);
                    console.log("Format des données complètes:", typeof result);
                    
                    if (result && result.length > 0) {
                        $.each(result, function (i, item) {
                            // Log détaillé pour chaque événement
                            console.log("Événement #" + i + ":", result[i]);
                            console.log("Type de l'événement:", typeof result[i]);
                            console.log("Techniciens pour l'événement #" + i + ":", result[i].technicians);
                            console.log("Type des techniciens:", typeof result[i].technicians, Array.isArray(result[i].technicians));
                            
                            // Validation et préparation des techniciens
                            let validatedTechnicians = [];
                            if (result[i].technicians && Array.isArray(result[i].technicians)) {
                                validatedTechnicians = result[i].technicians.map(tech => {
                                    // Normaliser le format des techniciens pour un traitement cohérent
                                    if (typeof tech === 'string') {
                                        return { id: tech, name: tech };
                                    } else if (tech && typeof tech === 'object') {
                                        // Vérifier si c'est un objet avec au moins id ou name
                                        return {
                                            id: tech.id || '',
                                            name: tech.name || tech.id || 'Technicien',
                                            level: tech.level || ''
                                        };
                                    }
                                    return null;
                                }).filter(tech => tech !== null); // Supprimer les valeurs nulles
                                
                                console.log("Techniciens validés et normalisés:", validatedTechnicians);
                            } else {
                                console.warn("Aucun technicien valide pour l'événement #" + i);
                            }
                            
                            datas.push({
                                eventName: result[i].name,
                                eventDescription: result[i].description,
                                eventLocation: result[i].location,
                                startDate: result[i].startDate,
                                endDate: result[i].endDate,
                                startTime: result[i].startTime,
                                endTime: result[i].endTime,
                                allDay: result[i].allDay,
                                technicians: validatedTechnicians // Utiliser les techniciens validés
                            });
                        
                            // Fix allDay logic
                            let allDayEvent = result[i].allDay ? true : false;

                            // Handle date formatting correctly
                            const startDate = moment(result[i].startDate).format('YYYY-MM-DD');
                            const endDate = moment(result[i].endDate).format('YYYY-MM-DD');
                            
                            // Merge date & time
                            var startDateTime, endDateTime;
                            
                            if (allDayEvent) {
                                // For all-day events
                                startDateTime = startDate;
                                endDateTime = endDate;
                            } else {
                                // For events with specific times
                                const startTime = result[i].startTime ? result[i].startTime : '00:00:00';
                                const endTime = result[i].endTime ? result[i].endTime : '23:59:59';
                                
                                startDateTime = startDate + 'T' + startTime;
                                endDateTime = endDate + 'T' + endTime;
                            }
                            
                            // Log des techniciens validés avant ajout à l'événement
                            console.log("Techniciens validés avant ajout à l'événement:", validatedTechnicians);
                            
                            // Add new event to calendar avec les techniciens validés
                            calendar.addEvent({
                                id: result[i].id,
                                title: result[i].name,
                                description: result[i].description,
                                location: result[i].location,
                                start: startDateTime,
                                end: endDateTime,
                                allDay: allDayEvent,
                                extendedProps: {
                                    description : result[i].description,
                                    location    : result[i].location,
                                    technicians : validatedTechnicians // Utiliser les techniciens validés
                                }
                            });
                        
                        // Vérifier que l'événement a bien été ajouté avec ses techniciens
                        const addedEvents = calendar.getEvents();
                        const lastEvent = addedEvents[addedEvents.length - 1];
                        console.log("Événement ajouté:", lastEvent.title);
                        console.log("Techniciens dans l'événement ajouté:", lastEvent.extendedProps.technicians);
                        calendar.render();
                    });
                }
                },
                error: function(xhr, status, error) {
                    console.error("Error loading calendar events:", error);
                    alert("Erreur lors du chargement des événements. Le calendrier sera vide.");
                    // Create empty calendar even if ajax fails
                    calendar.render();
                }
            });
        };

        // Handle edit event
        const handleEditEvent = () => {
            // Update modal title
            modalTitle.innerText = "Modifier un événement";

            modal.show();

            // Select datepicker wrapper elements
            const datepickerWrappers = form.querySelectorAll('[data-kt-calendar="datepicker"]');

            // Handle all day toggle
            const allDayToggle = form.querySelector('#kt_calendar_datepicker_allday');
            allDayToggle.addEventListener('click', e => {
                if (e.target.checked) {
                    allDaypicker = "non";
                    datepickerWrappers.forEach(dw => {
                        dw.classList.add('d-none');
                    });
                } else {
                    allDaypicker = "oui";
                    endFlatpickr.setDate(data.startDate, true, 'Y-m-d');
                    datepickerWrappers.forEach(dw => {
                        dw.classList.remove('d-none');
                    });
                }
            });

            populateForm(data);

            // Handle submit form - VERSION MODIFIÉE AVEC GESTION DES TECHNICIENS
            submitButton.addEventListener('click', function (e) {
                // Prevent default button action
                e.preventDefault();

                // Récupérer les techniciens sélectionnés
                const selectedTechnicians = getSelectedTechnicians();

                // Créer les données à envoyer
                const formData = new FormData();
                formData.append('update', '1');
                formData.append('id', data.id);
                formData.append('eventName', eventName.value);
                formData.append('eventDescription', eventDescription.value);
                formData.append('eventLocation', eventLocation.value);
                formData.append('startDate', startDatepicker.value);
                formData.append('endDate', endDatepicker.value);
                formData.append('startTime', startTimepicker.value);
                formData.append('endTime', endTimepicker.value);
                formData.append('allDay', allDaypicker);
                
                // Ajouter les techniciens sélectionnés
                selectedTechnicians.forEach(function(techId) {
                    formData.append('calendar_technicians[]', techId);
                });

                // Envoyer via fetch
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(data => {
                    console.log('Response:', data);
                    modal.hide();
                    location.reload();
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            });
        }

        // Handle view event
        const handleViewEvent = () => {
            viewModal.show();

            // Récupération forcée des techniciens depuis FullCalendar
            const fcEvent = calendar.getEventById(data.id);
            console.log("Event from FullCalendar:", fcEvent);
            
            if (fcEvent && fcEvent.extendedProps && fcEvent.extendedProps.technicians) {
                console.log("Techniciens récupérés depuis FullCalendar:", fcEvent.extendedProps.technicians);
                data.technicians = fcEvent.extendedProps.technicians;
            }

            // Detect all day event
            var eventNameMod;
            var startDateMod;
            var endDateMod;

            // Generate labels
            if (data.allDay) {
                eventNameMod = 'All Day';
                startDateMod = moment(data.startDate).format('Do MMM, YYYY');
                endDateMod = moment(data.endDate).format('Do MMM, YYYY');
            } else {
                eventNameMod = '';
                startDateMod = moment(data.startDate).format('Do MMM, YYYY - h:mm a');
                endDateMod = moment(data.endDate).format('Do MMM, YYYY - h:mm a');
            }
            
            // Debug: Vérifier les données des techniciens avec plus de détails
            console.log('Data technicians (détaillé):', JSON.stringify(data.technicians));
            console.log('ViewDom:', viewDom);
            
            // Tentative de récupération des données des techniciens directement via AJAX si vide
            if (!data.technicians || !data.technicians.length) {
                console.log("Aucun technicien trouvé, tentative de récupération via AJAX");
                
                // Effectuer une requête AJAX pour récupérer les données fraîches
                fetch('display_event.php')
                    .then(response => response.json())
                    .then(events => {
                        // Trouver l'événement correspondant
                        const event = events.find(e => e.id === data.id);
                        
                        if (event && event.technicians && event.technicians.length > 0) {
                            console.log("Techniciens récupérés via AJAX:", event.technicians);
                            data.technicians = event.technicians;
                            
                            // Mettre à jour l'affichage des techniciens
                            displayTechnicians();
                        } else {
                            console.log("Aucun technicien trouvé via AJAX non plus");
                        }
                    })
                    .catch(error => {
                        console.error("Erreur lors de la récupération des techniciens via AJAX:", error);
                    });
            }
            
            // Fonction d'affichage des techniciens
            const displayTechnicians = () => {
                // Populate technicians data avec vérifications multiples
                const techniciansElement = viewDom ? viewDom.querySelector('[data-kt-calendar="event_technicians"]') : null;
                const techniciansRow = document.getElementById('technicians-row');
                
                console.log('Technicians element trouvé:', techniciansElement ? 'Oui' : 'Non');
                console.log('Technicians row trouvé:', techniciansRow ? 'Oui' : 'Non');
                
                if (data.technicians && data.technicians.length > 0) {
                    let techniciansHtml = '';
                    
                    // Vérifier le format des données des techniciens
                    data.technicians.forEach(tech => {
                        console.log('Tech data (objet):', tech);
                        
                        // Structure d'affichage améliorée pour les techniciens
                        if (typeof tech === 'string') {
                            // Si c'est juste un string (ID ou nom)
                            techniciansHtml += `<div class="d-flex align-items-center mb-2">
                                <span class="bullet bullet-dot h-6px w-6px bg-success me-2"></span>
                                <span class="text-gray-600">${tech}</span>
                            </div>`;
                        } else if (tech && typeof tech === 'object') {
                            if (tech.name) {
                                // Format provenant de display_event.php (format attendu)
                                const techName = tech.name;
                                const techLevel = tech.level ? ` <span class="badge badge-light-info">${tech.level}</span>` : '';
                                
                                techniciansHtml += `<div class="d-flex align-items-center mb-2">
                                    <span class="bullet bullet-dot h-6px w-6px bg-success me-2"></span>
                                    <span class="text-gray-600">${techName}</span>${techLevel}
                                </div>`;
                            } else if (tech.id) {
                                // Autre format possible (fallback)
                                const name = tech.id || 'Technicien';
                                techniciansHtml += `<div class="d-flex align-items-center mb-2">
                                    <span class="bullet bullet-dot h-6px w-6px bg-warning me-2"></span>
                                    <span class="text-gray-600">${name}</span>
                                </div>`;
                            } else {
                                // Dernier recours: afficher n'importe quelle propriété disponible
                                const firstProp = Object.values(tech)[0];
                                techniciansHtml += `<div class="d-flex align-items-center mb-2">
                                    <span class="bullet bullet-dot h-6px w-6px bg-warning me-2"></span>
                                    <span class="text-gray-600">${firstProp || 'Technicien inconnu'}</span>
                                </div>`;
                            }
                        }
                    });
                    
                    console.log('Technicians HTML:', techniciansHtml);
                    
                    // Mise à jour de l'élément avec les données des techniciens
                    if (techniciansElement) {
                        techniciansElement.innerHTML = techniciansHtml;
                        console.log('Contenu des techniciens mis à jour avec succès');
                    }
                    
                    // Afficher la ligne des techniciens
                    if (techniciansRow) {
                        techniciansRow.style.display = 'flex';
                    }
                    
                    // Alternative: essayer de trouver l'élément par un autre sélecteur si l'élément principal n'est pas trouvé
                    if (!techniciansElement) {
                        const altElement = document.querySelector('.technicians-display, .event-technicians, [data-technicians]');
                        if (altElement) {
                            altElement.innerHTML = techniciansHtml;
                            console.log('Élément alternatif utilisé pour afficher les techniciens');
                        } else {
                            console.error('Aucun élément trouvé pour afficher les techniciens');
                        }
                    }
                } else {
                    console.log('No technicians data found');
                    if (techniciansRow) {
                        techniciansRow.style.display = 'none';
                    }
                }
            };
            
            // Afficher les techniciens immédiatement si disponibles
            displayTechnicians();

            // Populate view data
            viewId.innerText = data.id;
            viewEventName.innerText = data.eventName;
            viewAllDay.innerText = eventNameMod;
            viewEventDescription.innerText = data.eventDescription ? data.eventDescription : '--';
            viewEventLocation.innerText = data.eventLocation ? data.eventLocation : '--';
            viewStartDate.innerText = startDateMod;
            viewEndDate.innerText = endDateMod;
        }

        // Handle delete event
        const handleDeleteEvent = () => {
            if (viewDeleteButton) {  
                viewDeleteButton.addEventListener('click', e => {
                    e.preventDefault();

                    // Submit form
                    $.ajax({
                        type: 'post',
                        data: {
                            delete: 1,
                            id: data.id,
                        },
                        success: function (response){
                            viewModal.hide();
                            location.reload();
                        }
                    });
                });
            }
        }

        // Handle edit button
        const handleEditButton = () => {
            if (viewEditButton) {
                viewEditButton.addEventListener('click', e => {
                    e.preventDefault();

                    viewModal.hide();
                    handleEditEvent();
                });
            }
        }

        // Handle cancel button
        const handleCancelButton = () => {
            // Edit event modal cancel button
            cancelButton.addEventListener('click', function (e) {
                e.preventDefault();

                modal.hide(); // Hide modal	
            });
        }

        // Handle close button
        const handleCloseButton = () => {
            // Edit event modal close button
            closeButton.addEventListener('click', function (e) {
                e.preventDefault();

                modal.hide(); // Hide modal	
            });
        }

        // Fonction utilitaire pour récupérer les techniciens d'un événement
        const fetchEventTechnicians = (eventId) => {
            return new Promise((resolve, reject) => {
                if (!eventId) {
                    resolve([]);
                    return;
                }
                
                console.log("Récupération des techniciens pour l'événement:", eventId);
                
                fetch('display_event.php')
                    .then(response => response.json())
                    .then(events => {
                        const event = events.find(e => e.id === eventId);
                        if (event && event.technicians) {
                            console.log("Techniciens trouvés:", event.technicians);
                            resolve(event.technicians);
                        } else {
                            console.log("Aucun technicien trouvé pour l'événement");
                            resolve([]);
                        }
                    })
                    .catch(error => {
                        console.error("Erreur lors de la récupération des techniciens:", error);
                        reject(error);
                    });
            });
        };
        
        // Populate form
        const populateForm = () => {
            eventName.value = data.eventName ? data.eventName : '';
            eventDescription.value = data.eventDescription ? data.eventDescription : '';
            eventLocation.value = data.eventLocation ? data.eventLocation : '';
            startFlatpickr.setDate(data.startDate, true, 'Y-m-d');

            // Handle null end dates
            const endDate = data.endDate ? data.endDate : moment(data.startDate).format();
            endFlatpickr.setDate(endDate, true, 'Y-m-d');

            const allDayToggle = form.querySelector('#kt_calendar_datepicker_allday');
            const datepickerWrappers = form.querySelectorAll('[data-kt-calendar="datepicker"]');
            if (data.allDay) {
                allDayToggle.checked = true;
                datepickerWrappers.forEach(dw => {
                    dw.classList.add('d-none');
                });
            } else {
                startTimeFlatpickr.setDate(data.startDate, true, 'Y-m-d H:i');
                endTimeFlatpickr.setDate(data.endDate, true, 'Y-m-d H:i');
                endFlatpickr.setDate(data.startDate, true, 'Y-m-d');
                allDayToggle.checked = false;
                datepickerWrappers.forEach(dw => {
                    dw.classList.remove('d-none');
                });
            }
            
            // Gestion des techniciens pour le formulaire d'édition
            if (data.id && $('#calendar_technicians').length) {
                // Réinitialiser les sélections existantes
                $('#calendar_technicians').val(null).trigger('change');
                
                // Si l'événement a des techniciens, les sélectionner
                if (data.technicians && Array.isArray(data.technicians) && data.technicians.length > 0) {
                    console.log("Sélection des techniciens:", data.technicians);
                    
                    // Extraire les IDs des techniciens
                    const techIds = data.technicians.map(tech => {
                        if (typeof tech === 'string') return tech;
                        return tech.id || '';
                    }).filter(id => id !== '');
                    
                    if (techIds.length > 0) {
                        // Sélectionner les techniciens dans le dropdown
                        $('#calendar_technicians').val(techIds).trigger('change');
                        console.log("Techniciens sélectionnés:", techIds);
                    }
                } else if (data.id) {
                    // Si aucun technicien n'est disponible, essayer de les récupérer depuis le serveur
                    console.log("Tentative de récupération des techniciens depuis le serveur");
                    
                    fetchEventTechnicians(data.id)
                        .then(technicians => {
                            if (technicians && technicians.length > 0) {
                                console.log("Techniciens récupérés depuis le serveur:", technicians);
                                
                                // Mettre à jour les données de l'événement
                                data.technicians = technicians;
                                
                                // Extraire les IDs des techniciens
                                const techIds = technicians.map(tech => {
                                    if (typeof tech === 'string') return tech;
                                    return tech.id || '';
                                }).filter(id => id !== '');
                                
                                if (techIds.length > 0) {
                                    // Sélectionner les techniciens dans le dropdown
                                    $('#calendar_technicians').val(techIds).trigger('change');
                                    console.log("Techniciens sélectionnés après récupération:", techIds);
                                }
                            }
                        })
                        .catch(error => {
                            console.error("Erreur lors de la récupération des techniciens:", error);
                        });
                }
            }
        }

        // Format FullCalendar reponses
        const formatArgs = (res) => {
            // Vérification détaillée des techniciens avant assignation
            console.log("formatArgs - données d'entrée:", res);
            console.log("formatArgs - techniciens reçus:", res.technicians);
            
            data.id = res.id;
            data.eventName = res.title;
            data.eventDescription = res.description;
            data.eventLocation = res.location;
            
            // S'assurer que les techniciens sont bien assignés avec vérification renforcée
            if (res.technicians && Array.isArray(res.technicians)) {
                // Vérifier chaque technicien pour s'assurer qu'il a le bon format
                const validTechnicians = res.technicians.filter(tech => {
                    // Un technicien valide doit être un objet avec au moins une propriété id ou name
                    return tech && (typeof tech === 'object' || typeof tech === 'string');
                });
                
                data.technicians = validTechnicians;
                console.log("formatArgs - techniciens valides assignés:", data.technicians);
            } else {
                // Si les techniciens ne sont pas un tableau, essayer de les récupérer depuis FullCalendar
                try {
                    const fcEvent = calendar.getEventById(res.id);
                    if (fcEvent && fcEvent.extendedProps && fcEvent.extendedProps.technicians) {
                        data.technicians = fcEvent.extendedProps.technicians;
                        console.log("formatArgs - techniciens récupérés depuis FullCalendar:", data.technicians);
                    } else {
                        data.technicians = [];
                        console.log("formatArgs - aucun technicien trouvé dans FullCalendar");
                    }
                } catch (e) {
                    console.error("Erreur lors de la récupération des techniciens:", e);
                    data.technicians = [];
                }
            }
            
            data.startDate = res.startStr;
            data.endDate = res.endStr;
            data.allDay = res.allDay;
            
            // Debug final des données
            console.log("formatArgs - données finales:", {...data});
        }

        // Generate unique IDs for events
        const uid = () => {
            return Date.now().toString() + Math.floor(Math.random() * 1000).toString();
        }

        return {
            // Public Functions
            init: function () {
                // Define variables
                // Add event modal
                const element = document.getElementById('kt_modal_add_event');
                form = element.querySelector('#kt_modal_add_event_form');
                id = form.querySelector('[name="calendar_id"]');
                eventName = form.querySelector('[name="calendar_event_name"]');
                eventDescription = form.querySelector('[name="calendar_event_description"]');
                eventLocation = form.querySelector('[name="calendar_event_location"]');
                startDatepicker = form.querySelector('#kt_calendar_datepicker_start_date');
                endDatepicker = form.querySelector('#kt_calendar_datepicker_end_date');
                startTimepicker = form.querySelector('#kt_calendar_datepicker_start_time');
                endTimepicker = form.querySelector('#kt_calendar_datepicker_end_time');
                addButton = document.querySelector('[data-kt-calendar="add"]');
                submitButton = form.querySelector('#kt_modal_add_event_submit');
                cancelButton = form.querySelector('#kt_modal_add_event_cancel');
                closeButton = element.querySelector('#kt_modal_add_event_close');
                modalTitle = form.querySelector('[data-kt-calendar="title"]');
                modal = new bootstrap.Modal(element);

                // View event modal - INITIALISATION DÉPLACÉE ICI
                const viewElement = document.getElementById('kt_modal_view_event');
                viewModal = new bootstrap.Modal(viewElement);
                viewDom = viewElement; // ASSIGNATION CORRECTE
  
                viewId = viewElement.querySelector('[data-kt-calendar="event_id"]');
                viewEventName = viewElement.querySelector('[data-kt-calendar="event_name"]');
                viewAllDay = viewElement.querySelector('[data-kt-calendar="all_day"]');
                viewEventDescription = viewElement.querySelector('[data-kt-calendar="event_description"]');
                viewEventLocation = viewElement.querySelector('[data-kt-calendar="event_location"]');
                viewStartDate = viewElement.querySelector('[data-kt-calendar="event_start_date"]');
                viewEndDate = viewElement.querySelector('[data-kt-calendar="event_end_date"]');
                viewEditButton = viewElement.querySelector('#kt_modal_view_event_edit');
                viewDeleteButton = viewElement.querySelector('#kt_modal_view_event_delete');

                initCalendarApp();
                initValidator();
                initDatepickers();
                initSelect2(); // Initialiser Select2
                handleEditButton();
                handleAddButton();
                handleDeleteEvent();
                handleCancelButton();
                handleCloseButton();
                // resetFormValidator(element);
            }
        };
    }();

    // On document ready
    document.addEventListener("DOMContentLoaded", (event) => {
        KTAppCalendar.init();
    });
</script>
<?php include_once "partials/footer.php"; ?>
<?php
} ?>
