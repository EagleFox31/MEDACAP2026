<?php
session_start();
include_once "../language.php";
include_once "../partials/background-manager.php"; // Système de gestion de fond d'écran

// Check if user is logged in
if (!isset($_SESSION["id"])) {
    header("Location: ../");
    exit();
}

// MongoDB connection and dependency handling
$mongoAvailable = false;
$autoloadPaths = [
    "../../vendor/autoload.php",  // Standard relative path
    $_SERVER['DOCUMENT_ROOT'] . "/vendor/autoload.php", // Absolute path from document root
];

// Try each possible autoload path
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        try {
            require_once $path;
            // Only set MongoDB as available if we can actually load the required classes
            if (class_exists('MongoDB\Client') && class_exists('MongoDB\BSON\ObjectId')) {
                $mongoAvailable = true;
                break; // Successfully loaded, exit the loop
            }
        } catch (Exception $e) {
            error_log("Error loading MongoDB dependencies from $path: " . $e->getMessage());
            // Continue to next path
        }
    }
}

if (!$mongoAvailable) {
    error_log("MongoDB not available after trying all paths, falling back to file-based storage");
}

// Helper function to safely create MongoDB ObjectId
function safeObjectId($id) {
    try {
        if (class_exists('MongoDB\BSON\ObjectId')) {
            if (is_string($id) && strlen($id) === 24 && ctype_xdigit($id)) {
                // Use string class name to avoid static analysis errors
                $className = '\MongoDB\BSON\ObjectId';
                return new $className($id);
            } else if (is_object($id) && get_class($id) === 'MongoDB\BSON\ObjectId') {
                // Already an ObjectId, return as is
                return $id;
            }
        }
    } catch (Exception $e) {
        error_log("Error creating MongoDB ObjectId: " . $e->getMessage());
    }
    return $id; // Return the ID as is if ObjectId class is not available or on error
}

// Define a fallback file storage path for calendar events if MongoDB isn't available
$calendarJsonFile = __DIR__ . '/calendar_events.json';

// Initialize MongoDB collections if available
if ($mongoAvailable) {
    try {
        // We already verified MongoDB availability, now attempt connection
        $conn = new \MongoDB\Client("mongodb://localhost:27017");
        
        // Connecting to database
        $academy = $conn->academy;
        
        // Connecting to collections
        $calendars = $academy->calendars;
        $users = $academy->users;
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

// Create the calendar JSON file if it doesn't exist
if (!file_exists($calendarJsonFile)) {
    saveEventsToFile([], $calendarJsonFile);
}

// Handle event submission (Create)
if (isset($_POST['submit'])) {
    // Debug : log all POST data
    error_log("POST data: " . print_r($_POST, true));
    
    $eventName = $_POST["eventName"] ?? $_POST["calendar_event_name"] ?? '';
    $eventDescription = $_POST["eventDescription"] ?? $_POST["calendar_event_description"] ?? '';
    $eventLocation = $_POST["eventLocation"] ?? $_POST["calendar_event_location"] ?? '';
    $startDate = $_POST["startDate"] ?? $_POST["calendar_event_start_date"] ?? '';
    $endDate = $_POST["endDate"] ?? $_POST["calendar_event_end_date"] ?? '';
    $startTime = $_POST["startTime"] ?? $_POST["calendar_event_start_time"] ?? '';
    $endTime = $_POST["endTime"] ?? $_POST["calendar_event_end_time"] ?? '';
    $allDay = $_POST["allDay"] ?? ($_POST["kt_calendar_datepicker_allday"] ?? 'non');
    
    // Get technicians - test multiple possible names
    $technicianIds = $_POST["calendar_technicians"] ?? $_POST["technicianIds"] ?? [];
    
    // Debug : show technician IDs
    error_log("Technician IDs received: " . print_r($technicianIds, true));
    
    if (!$eventName || !$startDate || !$endDate) {
        $error_msg = $champ_obligatoire;
    } else {
        // Clean and validate technician IDs
        $validTechnicianIds = [];
        if (!empty($technicianIds) && is_array($technicianIds)) {
            foreach ($technicianIds as $techId) {
                if (!empty($techId) && $techId !== '') {
                    $validTechnicianIds[] = trim($techId);
                }
            }
        }
        
        error_log("Valid Technician IDs: " . print_r($validTechnicianIds, true));
        
        // Convert technician IDs to ObjectIds for MongoDB
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
        
        // Create calendar event object
        $calendar = [
            "id" => time() . rand(1000, 9999), // Generate a unique ID for file storage
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
            "status" => "scheduled"
        ];
        
        // Store the event
        if ($mongoAvailable) {
            try {
                $result = $calendars->insertOne($calendar);
                error_log("MongoDB insert result: " . print_r($result->getInsertedId(), true));
                $success_msg = "Événement ajouté avec succès";
                
                // Send notifications if needed
                if (file_exists("sendMail.php")) {
                    require_once "sendMail.php";
                    if (function_exists('sendTestScheduledMail')) {
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
                        
                        // Get admin docs for notification
                        $adminDocs = [];
                        $admins = $users->find([
                            'profile' => ['$in' => ['Admin', 'Super Admin']],
                            'active' => true,
                            'subsidiary' => $_SESSION["subsidiary"] ?? null
                        ]);
                        
                        foreach ($admins as $admin) {
                            $adminDocs[] = $admin;
                        }
                        
                        if (!empty($adminDocs)) {
                            $emailSent = sendTestScheduledMail($adminDocs, $technicianDocs, $calendar);
                            error_log("Email notification sent: " . ($emailSent ? 'true' : 'false'));
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Error inserting calendar event: " . $e->getMessage());
                $error_msg = "Erreur lors de l'ajout de l'événement: " . $e->getMessage();
                
                // Fallback to file storage - use string IDs for JSON
                $calendarForFile = $calendar;
                $calendarForFile['technicians'] = $validTechnicianIds; // Use string IDs for file
                $events = getEventsFromFile($calendarJsonFile);
                $events[] = $calendarForFile;
                saveEventsToFile($events, $calendarJsonFile);
                $success_msg = "Événement ajouté avec succès (stockage fichier)";
            }
        } else {
            // Use file storage - use string IDs
            $events = getEventsFromFile($calendarJsonFile);
            $events[] = $calendar;
            saveEventsToFile($events, $calendarJsonFile);
            $success_msg = "Événement ajouté avec succès (stockage fichier)";
        }
    }
}

// Handle event update (Edit)
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
    
    // Get technicians
    $technicianIds = $_POST["calendar_technicians"] ?? [];
    
    // Debug : show technician IDs
    error_log("Update - Technician IDs received: " . print_r($technicianIds, true));
    
    if (!$eventName || !$startDate || !$endDate) {
        $error_msg = $champ_obligatoire;
    } else {
        // Clean and validate technician IDs
        $validTechnicianIds = [];
        if (!empty($technicianIds) && is_array($technicianIds)) {
            foreach ($technicianIds as $techId) {
                if (!empty($techId) && $techId !== '') {
                    $validTechnicianIds[] = trim($techId);
                }
            }
        }
        
        error_log("Update - Valid Technician IDs: " . print_r($validTechnicianIds, true));
        
        // Convert technician IDs to ObjectIds for MongoDB
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
        
        $updatedEvent = [
            "name" => $eventName,
            "description" => $eventDescription,
            "location" => $eventLocation,
            "startDate" => $startDate,
            "endDate" => $endDate,
            "startTime" => $startTime,
            "endTime" => $endTime,
            "allDay" => ($allDay == 'oui' || $allDay == 'on'),
            "active" => true,
            "updated" => date("d-m-Y H:i:s"),
            "technicians" => $mongoAvailable ? $technicianObjectIds : $validTechnicianIds
        ];
        
        if ($mongoAvailable) {
            try {
                // Use our helper function for ObjectId
                $result = $calendars->updateOne(
                    ["_id" => safeObjectId($id)],
                    ['$set' => $updatedEvent]
                );
                
                error_log("MongoDB update result: " . print_r($result->getModifiedCount(), true));
                $success_msg = "Événement mis à jour avec succès";
            } catch (Exception $e) {
                error_log("Error updating calendar event: " . $e->getMessage());
                $error_msg = "Erreur lors de la mise à jour de l'événement: " . $e->getMessage();
                
                // Fallback to file storage
                $events = getEventsFromFile($calendarJsonFile);
                foreach ($events as $key => $event) {
                    if ($event['id'] == $id) {
                        $events[$key] = array_merge($event, $updatedEvent);
                        break;
                    }
                }
                saveEventsToFile($events, $calendarJsonFile);
                $success_msg = "Événement mis à jour avec succès (stockage fichier)";
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
            $success_msg = "Événement mis à jour avec succès (stockage fichier)";
        }
    }
}

// Handle event deletion (Delete)
if (isset($_POST['delete'])) {
    $id = $_POST["id"];
    
    if ($mongoAvailable) {
        try {
            // Use our helper function for ObjectId
            $result = $calendars->updateOne(
                ["_id" => safeObjectId($id)],
                ['$set' => ["active" => false]]
            );
            
            error_log("MongoDB delete result: " . print_r($result->getModifiedCount(), true));
            $success_msg = "Événement supprimé avec succès";
        } catch (Exception $e) {
            error_log("Error deleting calendar event: " . $e->getMessage());
            $error_msg = "Erreur lors de la suppression de l'événement: " . $e->getMessage();
            
            // Fallback to file storage
            $events = getEventsFromFile($calendarJsonFile);
            foreach ($events as $key => $event) {
                if ($event['id'] == $id) {
                    $events[$key]['active'] = false;
                    break;
                }
            }
            saveEventsToFile($events, $calendarJsonFile);
            $success_msg = "Événement supprimé avec succès (stockage fichier)";
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
        $success_msg = "Événement supprimé avec succès (stockage fichier)";
    }
}

include_once "partials/header.php";
?>

<!--begin::Title-->
<title><?= $calendar ?> | CFAO Mobility Academy</title>
<!--end::Title-->

<?php
// Définir le fond d'écran pour cette page
setPageBackground('bg-dashboard', true);

// Ouvrir le conteneur de fond d'écran
openBackgroundContainer('', 'id="kt_content"');
?>
    <!--begin::Toolbar-->
    <div class="toolbar" id="kt_toolbar">
        <div class="container-fluid d-flex flex-stack flex-wrap flex-sm-nowrap">
            <!--begin::Info-->
            <div class="d-flex flex-column align-items-start justify-content-center flex-wrap me-2">
                <h1 class="text-dark fw-bold my-1 fs-2">
                    <?= $calendar ?>
                </h1>
                <?php if(isset($success_msg)): ?>
                <div class="alert alert-success mt-2">
                    <?= $success_msg ?>
                </div>
                <?php endif; ?>
                <?php if(isset($error_msg)): ?>
                <div class="alert alert-danger mt-2">
                    <?= $error_msg ?>
                </div>
                <?php endif; ?>
            </div>
            <!--end::Info-->
        </div>
    </div>
    <!--end::Toolbar-->

    <!--begin::Post-->
    <div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
        <!--begin::Container-->
        <div class="container-xxl">
            <!--begin::Card-->
            <div class="card">
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
                                <!-- Hidden ID field for edits -->
                                <input type="hidden" name="id" class="form-control">
                                
                                <!-- Event Name -->
                                <div class="fv-row mb-9">
                                    <label class="fs-6 fw-semibold required mb-2"><?= $eventName ?></label>
                                    <input type="text" class="form-control form-control-solid"
                                           name="eventName">
                                </div>

                                <!-- Event Description -->
                                <div class="fv-row mb-9">
                                    <label class="fs-6 fw-semibold mb-2"><?= $eventDescription ?></label>
                                    <input type="text" class="form-control form-control-solid"
                                           name="eventDescription">
                                </div>

                                <!-- Event Location -->
                                <div class="fv-row mb-9">
                                    <label class="fs-6 fw-semibold mb-2"><?= $location ?></label>
                                    <input type="text" class="form-control form-control-solid"
                                           name="eventLocation">
                                </div>

                                <!-- Technicians -->
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

                                <!-- All Day Option -->
                                <div class="fv-row mb-9">
                                    <label class="form-check form-check-custom form-check-solid">
                                        <input class="form-check-input" type="checkbox" name="allDay" id="allDayCheckbox">
                                        <span class="form-check-label fw-semibold text-black ms-4"><?= $allDay ?></span>
                                    </label>
                                </div>

                                <!-- Date/Time Fields -->
                                <div class="row row-cols-lg-2 g-10">
                                    <div class="col">
                                        <div class="fv-row mb-9">
                                            <label class="fs-6 fw-semibold mb-2 required"><?= $startDate ?></label>
                                            <input class="form-control form-control-solid"
                                                   id="startDatepicker"
                                                   name="startDate">
                                        </div>
                                    </div>
                                    <div class="col" data-kt-calendar="datepicker">
                                        <div class="fv-row mb-9">
                                            <label class="fs-6 fw-semibold mb-2"><?= $startTime ?></label>
                                            <input class="form-control form-control-solid"
                                                   id="startTimepicker"
                                                   name="startTime">
                                        </div>
                                    </div>
                                </div>

                                <div class="row row-cols-lg-2 g-10">
                                    <div class="col">
                                        <div class="fv-row mb-9">
                                            <label class="fs-6 fw-semibold mb-2 required"><?= $endDate ?></label>
                                            <input class="form-control form-control-solid"
                                                   id="endDatepicker"
                                                   name="endDate">
                                        </div>
                                    </div>
                                    <div class="col" data-kt-calendar="datepicker">
                                        <div class="fv-row mb-9">
                                            <label class="fs-6 fw-semibold mb-2"><?= $endTime ?></label>
                                            <input class="form-control form-control-solid"
                                                   id="endTimepicker"
                                                   name="endTime">
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
                                    <span class="indicator-progress">Please wait...
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

                            <div class="d-flex align-items-center mb-9" id="technicians-row">
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

<!-- Required Libraries -->
<script src="https://cdn.jsdelivr.net/npm/moment@2.30.1/min/moment.min.js"></script>

<script>
    "use strict";

    // Class definition
    var KTAppCalendar = function () {
        // Shared variables
        var calendar;
        var data = {
            id: '',
            eventName: '',
            eventDescription: '',
            eventLocation: '',
            startDate: '',
            endDate: '',
            allDay: false,
            technicians: []
        };

        // Form variables
        var form, modal, modalTitle;
        var submitButton, cancelButton, closeButton;
        
        // Form input elements
        var idInput, eventNameInput, eventDescriptionInput, eventLocationInput;
        var startDatepicker, startTimepicker, endDatepicker, endTimepicker;
        var allDayCheckbox;
        
        // View modal variables
        var viewModal, viewDom;
        var viewId, viewEventName, viewAllDay, viewEventDescription, viewEventLocation;
        var viewStartDate, viewEndDate, viewTechnicians;
        var viewEditButton, viewDeleteButton;
        
        // Buttons
        var addButton;
        
        // Store events data
        var eventsData = [];

        // Initialize calendar app
        const initCalendarApp = function () {
            // Define calendar element
            const calendarEl = document.getElementById('kt_calendar_app');
            const todayDate = moment().startOf('day');
            
            // Initialize FullCalendar
            calendar = new FullCalendar.Calendar(calendarEl, {
                locale: 'fr', 
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                initialDate: todayDate.format('YYYY-MM-DD'),
                navLinks: true,
                selectable: true,
                selectMirror: true,

                // Select dates action
                select: function (arg) {
                    clearForm();
                    
                    // Set initial dates from selection
                    startDatepicker.value = moment(arg.start).format('YYYY-MM-DD');
                    endDatepicker.value = moment(arg.end).subtract(1, 'days').format('YYYY-MM-DD');
                    
                    // Show the modal
                    modal.show();
                    modalTitle.innerText = "<?= $addEvent ?>";
                },

                // Click event
                eventClick: function (arg) {
                    // Store event data
                    data.id = arg.event.id;
                    data.eventName = arg.event.title;
                    data.eventDescription = arg.event.extendedProps.description || '';
                    data.eventLocation = arg.event.extendedProps.location || '';
                    data.startDate = moment(arg.event.start).format('YYYY-MM-DD');
                    data.endDate = moment(arg.event.end || arg.event.start).format('YYYY-MM-DD');
                    data.allDay = arg.event.allDay;
                    data.technicians = arg.event.extendedProps.technicians || [];
                    
                    // Debug log to check technician data
                    console.log('Event clicked, technicians:', data.technicians);
                    
                    // Show view modal
                    handleViewEvent();
                },

                editable: true,
                dayMaxEvents: true,
                events: [], // Will be populated from AJAX
            });

            calendar.render();
            
            // Load events from server
            loadEvents();
        }
        
        // Load events from server
        const loadEvents = function () {
            // Show loading indicator
            document.querySelector('.card-body').classList.add('overlay', 'overlay-block');
            const loadingEl = document.createElement('div');
            loadingEl.classList.add('overlay-layer', 'bg-transparent');
            loadingEl.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>';
            document.querySelector('.card-body').appendChild(loadingEl);
            
            // Fetch events from server
            fetch('display_event.php')
                .then(response => response.json())
                .then(events => {
                    // Store events data
                    eventsData = events;
                    
                    // Debug log to check events data
                    console.log('Events loaded:', events);
                    
                    // Clear existing events
                    calendar.removeAllEvents();
                    
                    // Add events to calendar
                    events.forEach(event => {
                        // Format dates
                        const startDate = moment(event.startDate).format('YYYY-MM-DD');
                        const endDate = moment(event.endDate).format('YYYY-MM-DD');
                        
                        // Debug log for technicians
                        console.log(`Event ${event.name} technicians:`, event.technicians);
                        
                        // Ensure technicians is always an array with valid data
                        let technicians = [];
                        if (event.technicians) {
                            if (Array.isArray(event.technicians)) {
                                technicians = event.technicians;
                            } else if (typeof event.technicians === 'object') {
                                technicians = [event.technicians];
                            }
                        }
                        
                        // Create event object for calendar
                        const calendarEvent = {
                            id: event.id,
                            title: event.name,
                            start: event.allDay ? startDate : startDate + 'T' + (event.startTime || '00:00:00'),
                            end: event.allDay ? endDate : endDate + 'T' + (event.endTime || '23:59:59'),
                            allDay: event.allDay,
                            extendedProps: {
                                description: event.description,
                                location: event.location,
                                technicians: technicians
                            }
                        };
                        
                        // Add event to calendar
                        calendar.addEvent(calendarEvent);
                    });
                    
                    // Remove loading indicator
                    document.querySelector('.card-body').classList.remove('overlay', 'overlay-block');
                    if (loadingEl.parentNode) {
                        loadingEl.parentNode.removeChild(loadingEl);
                    }
                })
                .catch(error => {
                    console.error('Error loading events:', error);
                    
                    // Remove loading indicator
                    document.querySelector('.card-body').classList.remove('overlay', 'overlay-block');
                    if (loadingEl.parentNode) {
                        loadingEl.parentNode.removeChild(loadingEl);
                    }
                    
                    // Show error message
                    const alertEl = document.createElement('div');
                    alertEl.className = 'alert alert-danger';
                    alertEl.textContent = 'Erreur lors du chargement des événements.';
                    document.querySelector('.card-body').prepend(alertEl);
                    
                    // Hide alert after 3 seconds
                    setTimeout(() => {
                        if (alertEl.parentNode) {
                            alertEl.parentNode.removeChild(alertEl);
                        }
                    }, 3000);
                });
        }

        // Initialize Select2 for technicians
        const initSelect2 = function () {
            if ($('#calendar_technicians').length) {
                $('#calendar_technicians').select2({
                    placeholder: "Sélectionner des techniciens",
                    allowClear: true,
                    width: '100%'
                });
            }
        }
        
        // Initialize date pickers
        const initDatepickers = function () {
            // Initialize flatpickr for date fields
            flatpickr(startDatepicker, {
                enableTime: false,
                dateFormat: "Y-m-d",
            });
            
            flatpickr(endDatepicker, {
                enableTime: false,
                dateFormat: "Y-m-d",
            });
            
            // Initialize flatpickr for time fields
            flatpickr(startTimepicker, {
                enableTime: true,
                noCalendar: true,
                dateFormat: "H:i",
            });
            
            flatpickr(endTimepicker, {
                enableTime: true,
                noCalendar: true,
                dateFormat: "H:i",
            });
            
            // Handle all day toggle
            allDayCheckbox.addEventListener('change', function(e) {
                const timeFields = document.querySelectorAll('[data-kt-calendar="datepicker"]');
                
                if (e.target.checked) {
                    // Hide time fields for all-day events
                    timeFields.forEach(field => {
                        field.classList.add('d-none');
                    });
                } else {
                    // Show time fields for non-all-day events
                    timeFields.forEach(field => {
                        field.classList.remove('d-none');
                    });
                }
            });
        }
        
        // Handle add button
        const handleAddButton = function () {
            addButton.addEventListener('click', function(e) {
                clearForm();
                
                // Set default dates
                const today = moment();
                startDatepicker.value = today.format('YYYY-MM-DD');
                endDatepicker.value = today.format('YYYY-MM-DD');
                
                // Show modal
                modal.show();
                modalTitle.innerText = "<?= $addEvent ?>";
            });
        }
        
        // Clear form
        const clearForm = function () {
            // Reset form fields
            form.reset();
            
            // Clear Select2
            if ($('#calendar_technicians').length) {
                $('#calendar_technicians').val(null).trigger('change');
            }
            
            // Reset data object
            data = {
                id: '',
                eventName: '',
                eventDescription: '',
                eventLocation: '',
                startDate: '',
                endDate: '',
                allDay: false,
                technicians: []
            };
            
            // Show time fields
            document.querySelectorAll('[data-kt-calendar="datepicker"]').forEach(field => {
                field.classList.remove('d-none');
            });
        }
        
        // Populate form with data
        const populateForm = function () {
            // Set form values
            idInput.value = data.id;
            eventNameInput.value = data.eventName;
            eventDescriptionInput.value = data.eventDescription;
            eventLocationInput.value = data.eventLocation;
            startDatepicker.value = data.startDate;
            endDatepicker.value = data.endDate;
            
            // Set time values if not all day
            if (!data.allDay) {
                const eventStart = calendar.getEventById(data.id).start;
                const eventEnd = calendar.getEventById(data.id).end || eventStart;
                
                startTimepicker.value = moment(eventStart).format('HH:mm');
                endTimepicker.value = moment(eventEnd).format('HH:mm');
            }
            
            // Set all day checkbox
            allDayCheckbox.checked = data.allDay;
            
            // Toggle time fields visibility
            const timeFields = document.querySelectorAll('[data-kt-calendar="datepicker"]');
            if (data.allDay) {
                timeFields.forEach(field => field.classList.add('d-none'));
            } else {
                timeFields.forEach(field => field.classList.remove('d-none'));
            }
            
            // Set technicians if available
            if (data.technicians && data.technicians.length > 0) {
                const techIds = data.technicians.map(tech => {
                    if (typeof tech === 'string') return tech;
                    return tech.id || '';
                }).filter(id => id !== '');
                
                if (techIds.length > 0 && $('#calendar_technicians').length) {
                    $('#calendar_technicians').val(techIds).trigger('change');
                }
            }
        }
        
        // Handle view event
        const handleViewEvent = function () {
            // Show view modal
            viewModal.show();
            
            // Populate view data
            viewId.innerText = data.id;
            viewEventName.innerText = data.eventName;
            viewAllDay.innerText = data.allDay ? 'All Day' : '';
            viewEventDescription.innerText = data.eventDescription || '--';
            viewEventLocation.innerText = data.eventLocation || '--';
            
            // Format dates
            const startDateStr = data.allDay 
                ? moment(data.startDate).format('Do MMM, YYYY')
                : moment(data.startDate + ' ' + (data.startTime || '00:00')).format('Do MMM, YYYY - h:mm a');
                
            const endDateStr = data.allDay
                ? moment(data.endDate).format('Do MMM, YYYY')
                : moment(data.endDate + ' ' + (data.endTime || '23:59')).format('Do MMM, YYYY - h:mm a');
            
            viewStartDate.innerText = startDateStr;
            viewEndDate.innerText = endDateStr;
            
            // Populate technicians
            const techniciansRow = document.getElementById('technicians-row');
            
            console.log('Handling view event, technicians data:', data.technicians);
            console.log('Technicians element:', viewTechnicians);
            
            if (data.technicians && Array.isArray(data.technicians) && data.technicians.length > 0) {
                let techniciansHtml = '';
                
                // Loop through each technician and create HTML
                data.technicians.forEach(tech => {
                    console.log('Processing technician:', tech);
                    
                    // Handle different data formats
                    let techName = 'Technicien';
                    let techLevel = '';
                    
                    if (typeof tech === 'string') {
                        techName = tech;
                    } else if (tech && typeof tech === 'object') {
                        techName = tech.name || (tech.firstName ? `${tech.firstName} ${tech.lastName || ''}` : 'Technicien');
                        techLevel = tech.level ? ` <span class="badge badge-light-info">${tech.level}</span>` : '';
                    }
                    
                    // Create HTML for this technician
                    techniciansHtml += `
                        <div class="d-flex align-items-center mb-2">
                            <span class="bullet bullet-dot h-6px w-6px bg-success me-2"></span>
                            <span class="text-gray-600">${techName}</span>${techLevel}
                        </div>
                    `;
                });
                
                console.log('Generated technicians HTML:', techniciansHtml);
                viewTechnicians.innerHTML = techniciansHtml;
                techniciansRow.style.display = 'flex';
            } else {
                console.log('No technicians found or empty array');
                // Show a message instead of hiding the row completely
                viewTechnicians.innerHTML = '<div class="fst-italic text-muted">Aucun technicien assigné à cet événement</div>';
                techniciansRow.style.display = 'flex';
            }
        }
        
        // Handle edit event
        const handleEditButton = function () {
            viewEditButton.addEventListener('click', function(e) {
                // Hide view modal
                viewModal.hide();
                
                // Show edit modal
                modal.show();
                modalTitle.innerText = "Modifier un événement";
                
                // Populate form
                populateForm();
            });
        }
        
        // Handle delete event
        const handleDeleteButton = function () {
            if (viewDeleteButton) {
                viewDeleteButton.addEventListener('click', function(e) {
                    if (confirm('Êtes-vous sûr de vouloir supprimer cet événement ?')) {
                        // Create form data
                        const formData = new FormData();
                        formData.append('delete', '1');
                        formData.append('id', data.id);
                        
                        // Send delete request
                        fetch(window.location.href, {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => {
                            // Hide modal
                            viewModal.hide();
                            
                            // Reload page to show confirmation message
                            window.location.reload();
                        })
                        .catch(error => {
                            console.error('Error deleting event:', error);
                            alert('Erreur lors de la suppression de l\'événement.');
                        });
                    }
                });
            }
        }
        
        // Handle form submission
        const handleFormSubmit = function () {
            submitButton.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Show loading indicator
                submitButton.setAttribute('data-kt-indicator', 'on');
                submitButton.disabled = true;
                
                // Get form data
                const formData = new FormData(form);
                
                // Add action based on whether it's a new event or edit
                if (idInput.value) {
                    formData.append('update', '1');
                } else {
                    formData.append('submit', '1');
                }
                
                // Make sure allDay value is set correctly
                if (!formData.has('allDay')) {
                    formData.append('allDay', allDayCheckbox.checked ? 'oui' : 'non');
                }
                
                // Send form data
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    // Hide modal
                    modal.hide();
                    
                    // Reload page to show confirmation message and refresh events
                    window.location.reload();
                })
                .catch(error => {
                    console.error('Error saving event:', error);
                    alert('Erreur lors de l\'enregistrement de l\'événement.');
                    
                    // Hide loading indicator
                    submitButton.removeAttribute('data-kt-indicator');
                    submitButton.disabled = false;
                });
            });
        }
        
        // Handle cancel button
        const handleCancelButton = function () {
            cancelButton.addEventListener('click', function(e) {
                e.preventDefault();
                modal.hide();
            });
        }
        
        // Handle close button
        const handleCloseButton = function () {
            closeButton.addEventListener('click', function(e) {
                e.preventDefault();
                modal.hide();
            });
        }

        // Public methods
        return {
            init: function () {
                // Get form elements
                form = document.getElementById('kt_modal_add_event_form');
                
                if (!form) {
                    return;
                }
                
                // Get modal elements
                modal = new bootstrap.Modal(document.getElementById('kt_modal_add_event'));
                modalTitle = form.querySelector('[data-kt-calendar="title"]');
                
                // Get form inputs
                idInput = form.querySelector('input[name="id"]');
                eventNameInput = form.querySelector('input[name="eventName"]');
                eventDescriptionInput = form.querySelector('input[name="eventDescription"]');
                eventLocationInput = form.querySelector('input[name="eventLocation"]');
                startDatepicker = form.querySelector('#startDatepicker');
                startTimepicker = form.querySelector('#startTimepicker');
                endDatepicker = form.querySelector('#endDatepicker');
                endTimepicker = form.querySelector('#endTimepicker');
                allDayCheckbox = form.querySelector('#allDayCheckbox');
                
                // Get buttons
                addButton = document.querySelector('[data-kt-calendar="add"]');
                submitButton = form.querySelector('#kt_modal_add_event_submit');
                cancelButton = form.querySelector('#kt_modal_add_event_cancel');
                closeButton = document.querySelector('#kt_modal_add_event_close');
                
                // Get view modal elements
                viewDom = document.getElementById('kt_modal_view_event');
                viewModal = new bootstrap.Modal(viewDom);
                viewId = viewDom.querySelector('[data-kt-calendar="event_id"]');
                viewEventName = viewDom.querySelector('[data-kt-calendar="event_name"]');
                viewAllDay = viewDom.querySelector('[data-kt-calendar="all_day"]');
                viewEventDescription = viewDom.querySelector('[data-kt-calendar="event_description"]');
                viewEventLocation = viewDom.querySelector('[data-kt-calendar="event_location"]');
                viewStartDate = viewDom.querySelector('[data-kt-calendar="event_start_date"]');
                viewEndDate = viewDom.querySelector('[data-kt-calendar="event_end_date"]');
                viewTechnicians = viewDom.querySelector('[data-kt-calendar="event_technicians"]');
                
                // Debug log to verify DOM elements
                console.log('DOM elements initialized:', {
                    viewDom,
                    viewTechnicians,
                    techniciansRow: document.getElementById('technicians-row')
                });
                
                viewEditButton = viewDom.querySelector('#kt_modal_view_event_edit');
                viewDeleteButton = viewDom.querySelector('#kt_modal_view_event_delete');
                
                // Initialize components
                initCalendarApp();
                initSelect2();
                initDatepickers();
                
                // Handle buttons
                handleAddButton();
                handleEditButton();
                handleDeleteButton();
                handleFormSubmit();
                handleCancelButton();
                handleCloseButton();
            }
        };
    }();

    // On document ready
    document.addEventListener("DOMContentLoaded", function () {
        KTAppCalendar.init();
    });
</script>

<?php include_once "partials/footer.php"; ?>