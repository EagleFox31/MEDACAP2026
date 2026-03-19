<?php
session_start();
include_once "language.php";

if (!isset($_SESSION["id"])) {
    header("Location: ../");
    exit();
} else {

   require_once "../vendor/autoload.php";

   // Create connection
   $conn = new MongoDB\Client("mongodb://localhost:27017");
   
   // Connecting in database
   $academy = $conn->academy;
   
   // Connecting in collections
   $calendars = $academy->calendars;

   if (isset($_POST['submit'])) {
      $eventName = $_POST["eventName"];
      $eventDescription = $_POST["eventDescription"];
      $eventLocation = $_POST["eventLocation"];
      $startDate = $_POST["startDate"];
      $endDate = $_POST["endDate"];
      $startTime = $_POST["startTime"];
      $endTime = $_POST["endTime"];
      $allDay = $_POST["allDay"];
    
      if (!$eventName || !$startDate || !$endDate) {
        $error_msg = $champ_obligatoire;
      }  else {
        $eventType = $_POST["eventType"] ?? 'regular';
        $technicianIds = $_POST["technicianIds"] ?? [];
        $testIds = $_POST["testIds"] ?? [];
        
        $calendar = [
            "name" => $eventName,
            "description" => $eventDescription,
            "location" => $eventLocation,
            "startDate" => $startDate,
            "endDate" => $endDate,
            "startTime" => $startTime,
            "endTime" => $endTime,
            "allDay" => ($allDay == 'oui'),
            "type" => $eventType,
            "active" => true,
            "created" => date("d-m-Y H:I:S"),
        ];
        
        // Ajouter les champs spécifiques aux tests si nécessaire
        if ($eventType === 'test') {
            $calendar["technicians"] = $technicianIds;
            $calendar["tests"] = $testIds;
            $calendar["status"] = "scheduled";
            $calendar["reminderStatus"] = [
                "confirmation" => true,  // Le mail de confirmation est envoyé immédiatement
                "dayBefore" => false,
                "dayOf" => false,
                "dayAfter" => false
            ];
            
            // Récupérer les documents des techniciens et admins pour envoyer un email
            $technicianDocs = [];
            foreach ($technicianIds as $techId) {
                $tech = $users->findOne(['_id' => new MongoDB\BSON\ObjectId($techId)]);
                if ($tech) {
                    $technicianDocs[] = $tech;
                }
            }
            
            if (!empty($technicianDocs)) {
                $adminDocs = getAdminsDocs($technicianDocs[0], $conn);
                
                // Envoyer l'email de confirmation de planification
                require_once "sendMail.php";
                sendTestScheduledMail($adminDocs, $technicianDocs, $calendar);
            }
        }
        
        $calendars->insertOne($calendar);
      }
   }
   
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
    
      if (!$eventName || !$startDate || !$endDate) {
        $error_msg = $champ_obligatoire;
      }  else {
        if ($allDay == 'oui') {
            $calendar1 = [
                "name" => $eventName,
                "description" => $eventDescription,
                "location" => $eventLocation,
                "startDate" => $startDate,
                "endDate" => $endDate,
                "startTime" => $startTime,
                "endTime" => $endTime,
                "allDay" => true,
                "active" => true,
                "updated" => date("d-m-Y H:I:S"),
            ];
  
            $calendars->updateOne(
                ["_id" => new MongoDB\BSON\ObjectId($id)],
                ['$set' => $calendar1]
            );
        } else {
            $calendar2 = [
                "name" => $eventName,
                "description" => $eventDescription,
                "location" => $eventLocation,
                "startDate" => $startDate,
                "endDate" => $endDate,
                "startTime" => $startTime,
                "endTime" => $endTime,
                "allDay" => false,
                "active" => true,
                "updated" => date("d-m-Y H:I:S"),
            ];
  
            $calendars->updateOne(
                ["_id" => new MongoDB\BSON\ObjectId($id)],
                ['$set' => $calendar2]
            );
        }
      }
   }

   if (isset($_POST['delete'])) {
        $id = $_POST["id"];
    
        $calendars->updateOne(
            ["_id" => new MongoDB\BSON\ObjectId($id)],
            ['$set' => ["active" => false] ]
        );
   }

    include_once "partials/header.php";
    ?>

<!--begin::Title-->
<title><?php echo $calendar ?> | CFAO Mobility Academy</title>
<!--end::Title-->
<div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content">
                  <!--begin::Toolbar-->
                  <div class="toolbar" id="kt_toolbar">
                     <div class=" container-fluid  d-flex flex-stack flex-wrap flex-sm-nowrap">
                        <!--begin::Info-->
                        <div class="d-flex flex-column align-items-start justify-content-center flex-wrap me-2">
                           <!--begin::Title-->
                           <h1 class="text-dark fw-bold my-1 fs-2">
                           <?php echo $calendar ?>                            
                           </h1>
                           <br>
                           <!--end::Title-->
                     </div>
                  </div>
                  <!--end::Toolbar-->
                  <!--begin::Post-->
                  <div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
                     <!--begin::Container-->
                     <div class=" container-xxl ">
                        <!--begin::Card-->
                        <div class="card ">
                           <!--begin::Card header-->
                           <div class="card-header">
                              <h2 class="card-title fw-bold">
                                <?php echo $calendar ?>
                              </h2>
                              <div class="card-toolbar">
                                 <button class="btn btn-flex btn-primary" data-kt-calendar="add">
                                 <i class="ki-duotone ki-plus fs-2"></i> 
                                   <?php echo $addEvent ?>
                                 </button>
                              </div>
                           </div>
                           <!--end::Card header-->
                           <!--begin::Card body-->
                           <div class="card-body">
                              <!--begin::Calendar-->
                              <div id="kt_calendar_app"></div>
                              <!--end::Calendar-->
                           </div>
                           <!--end::Card body-->
                        </div>
                        <!--end::Card-->
                        <!--begin::Modals-->
                        <!--begin::Modal - New Product-->
                        <div class="modal fade" id="kt_modal_add_event" tabindex-="1" aria-hidden="true" data-bs-focus="false">
                           <!--begin::Modal dialog-->
                           <div class="modal-dialog modal-dialog-centered mw-650px">
                              <!--begin::Modal content-->
                              <div class="modal-content">
                                 <!--begin::Form-->
                                 <form class="form" method="post" action="#" id="kt_modal_add_event_form">
                                    <!--begin::Modal header-->
                                    <div class="modal-header">
                                       <!--begin::Modal title-->
                                       <h2 class="fw-bold" data-kt-calendar="title"><?php echo $addEvent ?></h2>
                                       <!--end::Modal title-->
                                       <!--begin::Close-->
                                       <div class="btn btn-icon btn-sm btn-active-icon-primary" id="kt_modal_add_event_close">
                                          <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>                    
                                       </div>
                                       <!--end::Close-->
                                    </div>
                                    <!--end::Modal header-->
                                    <!--begin::Modal body-->
                                    <div class="modal-body py-10 px-lg-17">
                                       <!--begin::Input group-->
                                       <div class="fv-row mb-9">
                                          <!--begin::Label-->
                                          <label class="fs-6 fw-semibold required mb-2"><?php echo $eventName ?></label>
                                          <!--end::Label-->
                                          <!--begin::Input-->
                                          <input type="text" class="form-control form-control-solid hidden" placeholder="" name="calendar_id" />
                                          <!--end::Input-->
                                          <!--begin::Input-->
                                          <input type="text" class="form-control form-control-solid" placeholder="" name="calendar_event_name" />
                                          <!--end::Input-->
                                       </div>
                                       <!--end::Input group-->
                                       <!--begin::Input group-->
                                       <div class="fv-row mb-9">
                                          <!--begin::Label-->
                                          <label class="fs-6 fw-semibold mb-2"><?php echo $eventDescription ?></label>
                                          <!--end::Label-->
                                          <!--begin::Input-->
                                          <input type="text" class="form-control form-control-solid" placeholder="" name="calendar_event_description" />
                                          <!--end::Input-->
                                       </div>
                                       <!--end::Input group-->
                                       <!--begin::Input group-->
                                       <div class="fv-row mb-9">
                                          <!--begin::Label-->
                                          <label class="fs-6 fw-semibold mb-2"><?php echo $location ?></label>
                                          <!--end::Label-->
                                          <!--begin::Input-->
                                          <input type="text" class="form-control form-control-solid" placeholder="" name="calendar_event_location" />
                                          <!--end::Input-->
                                       </div>
                                       <!--end::Input group-->
                                       
                                       <!--begin::Input group-->
                                       <div class="fv-row mb-9">
                                          <!--begin::Label-->
                                          <label class="fs-6 fw-semibold mb-2">Type d'événement</label>
                                          <!--end::Label-->
                                          <!--begin::Input-->
                                          <select class="form-select form-select-solid" name="calendar_event_type" id="calendar_event_type">
                                              <option value="regular">Événement régulier</option>
                                              <option value="test">Test</option>
                                          </select>
                                          <!--end::Input-->
                                       </div>
                                       <!--end::Input group-->
                                       
                                       <!--begin::Test options-->
                                       <div id="test_options" style="display: none;">
                                           <!--begin::Input group-->
                                           <div class="fv-row mb-9">
                                              <!--begin::Label-->
                                              <label class="fs-6 fw-semibold mb-2">Techniciens concernés</label>
                                              <!--end::Label-->
                                              <!--begin::Input-->
                                              <select class="form-select form-select-solid" name="calendar_technicians[]" id="calendar_technicians" multiple>
                                                  <?php
                                                  // Récupérer tous les techniciens actifs
                                                  $techniciens = $users->find([
                                                      'profile' => 'Technicien',
                                                      'active' => true
                                                  ]);
                                                  
                                                  foreach ($techniciens as $tech) {
                                                      echo '<option value="' . $tech['_id'] . '">' .
                                                           htmlspecialchars($tech['firstName']) . ' ' .
                                                           htmlspecialchars($tech['lastName']) . ' (' .
                                                           htmlspecialchars($tech['level']) . ')</option>';
                                                  }
                                                  ?>
                                              </select>
                                              <!--end::Input-->
                                           </div>
                                           <!--end::Input group-->
                                           
                                           <!--begin::Input group-->
                                           <div class="fv-row mb-9">
                                              <!--begin::Label-->
                                              <label class="fs-6 fw-semibold mb-2">Tests à effectuer</label>
                                              <!--end::Label-->
                                              <!--begin::Input-->
                                              <select class="form-select form-select-solid" name="calendar_tests[]" id="calendar_tests" multiple>
                                                  <?php
                                                  // Récupérer tous les tests disponibles
                                                  $availableTests = $tests->find([
                                                      'active' => true
                                                  ]);
                                                  
                                                  foreach ($availableTests as $test) {
                                                      echo '<option value="' . $test['_id'] . '">' .
                                                           htmlspecialchars($test['name']) . ' (' .
                                                           htmlspecialchars($test['level']) . ' - ' .
                                                           htmlspecialchars($test['type']) . ')</option>';
                                                  }
                                                  ?>
                                              </select>
                                              <!--end::Input-->
                                           </div>
                                           <!--end::Input group-->
                                       </div>
                                       <!--end::Test options-->
                                       <!--begin::Input group-->
                                       <div class="fv-row mb-9">
                                          <!--begin::Checkbox-->
                                          <label class="form-check form-check-custom form-check-solid">
                                          <input class="form-check-input" type="checkbox" value="" id="kt_calendar_datepicker_allday" />
                                          <span class="form-check-label fw-semibold text-black" style="margin-left:30px" for="kt_calendar_datepicker_allday">
                                            <?php echo $allDay ?>
                                          </span>
                                          </label>
                                          <!--end::Checkbox-->
                                       </div>
                                       <!--end::Input group-->
                                       <!--begin::Input group-->
                                       <div class="row row-cols-lg-2 g-10">
                                          <div class="col">
                                             <div class="fv-row mb-9">
                                                <!--begin::Label-->
                                                <label class="fs-6 fw-semibold mb-2 required"> <?php echo $startDate ?></label>
                                                <!--end::Label-->
                                                <!--begin::Input-->
                                                <input class="form-control form-control-solid" name="calendar_event_start_date" placeholder="Pick a start date" id="kt_calendar_datepicker_start_date" />
                                                <!--end::Input-->
                                             </div>
                                          </div>
                                          <div class="col" data-kt-calendar="datepicker">
                                             <div class="fv-row mb-9">
                                                <!--begin::Label-->
                                                <label class="fs-6 fw-semibold mb-2"> <?php echo $startTime ?></label>
                                                <!--end::Label-->
                                                <!--begin::Input-->
                                                <input class="form-control form-control-solid" name="calendar_event_start_time" placeholder="Pick a start time" id="kt_calendar_datepicker_start_time" />
                                                <!--end::Input-->
                                             </div>
                                          </div>
                                       </div>
                                       <!--end::Input group-->
                                       <!--begin::Input group-->
                                       <div class="row row-cols-lg-2 g-10">
                                          <div class="col">
                                             <div class="fv-row mb-9">
                                                <!--begin::Label-->
                                                <label class="fs-6 fw-semibold mb-2 required"><?php echo $endDate ?></label>
                                                <!--end::Label-->
                                                <!--begin::Input-->
                                                <input class="form-control form-control-solid" name="calendar_event_end_date" placeholder="Pick a end date" id="kt_calendar_datepicker_end_date" />
                                                <!--end::Input-->
                                             </div>
                                          </div>
                                          <div class="col" data-kt-calendar="datepicker">
                                             <div class="fv-row mb-9">
                                                <!--begin::Label-->
                                                <label class="fs-6 fw-semibold mb-2"><?php echo $endTime ?></label>
                                                <!--end::Label-->
                                                <!--begin::Input-->
                                                <input class="form-control form-control-solid" name="calendar_event_end_time" placeholder="Pick a end time" id="kt_calendar_datepicker_end_time" />
                                                <!--end::Input-->
                                             </div>
                                          </div>
                                       </div>
                                       <!--end::Input group-->
                                    </div>
                                    <!--end::Modal body-->
                                    <!--begin::Modal footer-->
                                    <div class="modal-footer flex-center">
                                       <!--begin::Button-->
                                       <button type="reset" id="kt_modal_add_event_cancel" class="btn btn-light me-3">
                                       <?php echo $annuler ?>
                                       </button>
                                       <!--end::Button-->
                                       <!--begin::Button-->
                                       <button type="button" name="submit" id="kt_modal_add_event_submit" class="btn btn-primary">
                                       <span class="indicator-label">
                                       <?php echo $valider ?>
                                       </span>
                                       <span class="indicator-progress">
                                       Please wait... <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                       </span>
                                       </button>
                                       <!--end::Button-->
                                    </div>
                                    <!--end::Modal footer-->
                                 </form>
                                 <!--end::Form-->
                              </div>
                           </div>
                        </div>
                        <!--end::Modal - New Product-->
                        <!--begin::Modal - New Product-->
                        <div class="modal fade" id="kt_modal_view_event" tabindex="-1" data-bs-focus="false" aria-hidden="true">
                           <!--begin::Modal dialog-->
                           <div class="modal-dialog modal-dialog-centered mw-650px">
                              <!--begin::Modal content-->
                              <div class="modal-content">
                                 <!--begin::Modal header-->
                                 <div class="modal-header border-0 justify-content-end">
                                    <!--begin::Edit-->
                                    <div class="btn btn-icon btn-sm btn-color-gray-400 btn-active-icon-primary me-2" data-bs-toggle="tooltip" data-bs-dismiss="click" title="Edit Event" id="kt_modal_view_event_edit">
                                       <i class="ki-duotone ki-pencil fs-2"><span class="path1"></span><span class="path2"></span></i>                
                                    </div>
                                    <!--end::Edit-->
                                    <?php if ($_SESSION['profile'] == "Super Admin") { ?>
                                    <!--begin::Edit-->
                                    <div class="btn btn-icon btn-sm btn-color-gray-400 btn-active-icon-danger me-2" data-bs-toggle="tooltip" data-bs-dismiss="click" title="Delete Event" id="kt_modal_view_event_delete">
                                       <i class="ki-duotone ki-trash fs-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>                
                                    </div>
                                    <!--end::Edit-->
                                    <?php } ?>
                                    <!--begin::Close-->
                                    <div class="btn btn-icon btn-sm btn-color-gray-500 btn-active-icon-primary" data-bs-toggle="tooltip" data-bs-dismiss="click" title="Hide Event" id="kt_modal_add_event_hide" data-bs-dismiss="modal">
                                       <i class="ki-duotone ki-cross fs-2x"><span class="path1"></span><span class="path2"></span></i>                
                                    </div>
                                    <!--end::Close-->
                                 </div>
                                 <!--end::Modal header-->
                                 <!--begin::Modal body-->
                                 <div class="modal-body pt-0 pb-20 px-lg-17">
                                    <!--begin::Row-->
                                    <div class="d-flex">
                                       <!--begin::Icon-->
                                       <i class="ki-duotone ki-calendar-8 fs-1 text-muted me-5"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span><span class="path6"></span></i>                    
                                       <!--end::Icon-->
                                       <div class="mb-9">
                                          <!--begin::Event name-->
                                          <div class="d-flex align-items-center mb-2">
                                             <span class="fs-3 fw-bold me-3" data-kt-calendar="event_name"></span> <span class="badge badge-light-success" data-kt-calendar="all_day"></span>
                                          </div>
                                          <!--end::Event name-->
                                          <!--begin::Event id-->
                                          <div class="fs-6 hidden" data-kt-calendar="event_id"></div>
                                          <!--end::Event id-->
                                          <!--begin::Event description-->
                                          <div class="fs-6" data-kt-calendar="event_description"></div>
                                          <!--end::Event description-->
                                       </div>
                                    </div>
                                    <!--end::Row-->
                                    <!--begin::Row-->
                                    <div class="d-flex align-items-center mb-2">
                                       <!--begin::Bullet-->
                                       <span class="bullet bullet-dot h-10px w-10px bg-success ms-2 me-7"></span>
                                       <!--end::Bullet-->
                                       <!--begin::Event start date/time-->
                                       <div class="fs-6"><span class="fw-bold"><?php echo $start ?></span> <span data-kt-calendar="event_start_date"></span></div>
                                       <!--end::Event start date/time-->
                                    </div>
                                    <!--end::Row-->
                                    <!--begin::Row-->
                                    <div class="d-flex align-items-center mb-9">
                                       <!--begin::Bullet-->
                                       <span class="bullet bullet-dot h-10px w-10px bg-danger ms-2 me-7"></span>
                                       <!--end::Bullet-->
                                       <!--begin::Event end date/time-->
                                       <div class="fs-6"><span class="fw-bold"><?php echo $end ?></span> <span data-kt-calendar="event_end_date"></span></div>
                                       <!--end::Event end date/time-->
                                    </div>
                                    <!--end::Row-->
                                    <!--begin::Row-->
                                    <div class="d-flex align-items-center">
                                       <!--begin::Icon-->
                                       <i class="ki-duotone ki-geolocation fs-1 text-muted me-5"><span class="path1"></span><span class="path2"></span></i>                    <!--end::Icon-->
                                       <!--begin::Event location-->
                                       <div class="fs-6" data-kt-calendar="event_location"></div>
                                       <!--end::Event location-->
                                    </div>
                                    <!--end::Row-->
                                 </div>
                                 <!--end::Modal body-->
                              </div>
                           </div>
                        </div>
                        <!--end::Modal - New Product--><!--end::Modals-->
                     </div>
                     <!--end::Container-->                	
                  </div>
                  <!--end::Post-->               	
               </div>
               <!--end::Content-->               	
            </div>
            <!--end::Body-->



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

    // View event variables
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
                formatArgs({
                    id: arg.event.id,
                    title: arg.event.title,
                    description: arg.event.extendedProps.description,
                    location: arg.event.extendedProps.location,
                    startStr: arg.event.startStr,
                    endStr: arg.event.endStr,
                    allDay: arg.event.allDay
                });
                
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

        // Handle submit form
        submitButton.addEventListener('click', function (e) {
            // Prevent default button action
            e.preventDefault();

            $.ajax({
                type: 'post',
                data: {
                    submit: 1,
                    eventName: eventName.value,
                    eventDescription: eventDescription.value,
                    eventLocation: eventLocation.value,
                    startDate: startDatepicker.value,
                    endDate: endDatepicker.value,
                    startTime: startTimepicker.value,
                    endTime: endTimepicker.value,
                    allDay: allDaypicker
                },
                success: function (response){
                    modal.hide();
                    location.reload();
                }
            })
        });
    }

    window.onload = (event) => {
        $.ajax({
            url: 'display_event.php',
            type: 'get',
            dataType: 'json',
            success: function (response){
                var result = response;
                
                $.each(result, function (i, item) {
                    datas.push({
                        eventName: result[i].name,
                        eventDescription: result[i].description,
                        eventLocation: result[i].location,
                        startDate: result[i].startDate,
                        endDate: result[i].endDate,
                        startTime: result[i].startTime,
                        endTime: result[i].endTime,
                        allDay: result[i].allDay
                    })
                    let allDayEvent = false;
                    if (result[i].allDay == false) { allDayEvent = true; }

                    // Merge date & time
                    var startDateTime = moment(result[i].startDate).format();
                    var endDateTime = moment(result[i].endDate).format();
                    if (!allDayEvent) {
                        const startDate = moment(result[i].startDate).format('YYYY-MM-DD');
                        const endDate = moment(result[i].endDate).format('YYYY-MM-DD');
                        const startTime = moment(result[i].startTime).format('HH:mm:ss');
                        const endTime = moment(result[i].endTime).format('HH:mm:ss');

                        startDateTime = startDate + 'T' + result[i].startTime;
                        endDateTime = endDate + 'T' + result[i].endTime;
                    }
                    
                    // Add new event to calendar
                    calendar.addEvent({
                        id: result[i].id,
                        title: result[i].name,
                        description: result[i].description,
                        location: result[i].location,
                        start: startDateTime,
                        end: endDateTime,
                        allDay: allDayEvent
                    });
                    calendar.render();
                })
            }
        })
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

        // Handle submit form
        submitButton.addEventListener('click', function (e) {
            // Prevent default button action
            e.preventDefault();
            // Submit form
            $.ajax({
                type: 'post',
                data: {
                    update: 1,
                    id: data.id,
                    eventName: eventName.value,
                    eventDescription: eventDescription.value,
                    eventLocation: eventLocation.value,
                    startDate: startDatepicker.value,
                    endDate: endDatepicker.value,
                    startTime: startTimepicker.value,
                    endTime: endTimepicker.value,
                    allDay: allDaypicker
                },
                success: function (response){
                    modal.hide();
                    location.reload();
                }
            })
        });
    }

    // Handle view event
    const handleViewEvent = () => {
        viewModal.show();
        

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
                    modal.hide();
                    location.reload();
                }
            })
        });
    }

    // Handle edit button
    const handleEditButton = () => {
        viewEditButton.addEventListener('click', e => {
            e.preventDefault();

            viewModal.hide();
            handleEditEvent();
        });
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

    // Handle view button
    const handleViewButton = () => {
        const viewButton = document.querySelector('#kt_calendar_event_view_button');
        viewButton.addEventListener('click', e => {
            e.preventDefault();

            hidePopovers();
            handleViewEvent();
        });
    }

    // Helper functions

    // Reset form validator on modal close
    const resetFormValidator = (element) => {
        // Target modal hidden event --- For more info: https://getbootstrap.com/docs/5.0/components/modal/#events
        element.addEventListener('hidden.bs.modal', e => {
            if (validator) {
                // Reset form validator. For more info: https://formvalidation.io/guide/api/reset-form
                validator.resetForm(true);
            }
        });
    }

    // Toggle test options based on event type selection
    const toggleTestOptions = () => {
        const eventTypeSelect = document.querySelector('#calendar_event_type');
        const testOptions = document.querySelector('#test_options');
        
        if (eventTypeSelect && testOptions) {
            eventTypeSelect.addEventListener('change', function() {
                if (this.value === 'test') {
                    testOptions.style.display = 'block';
                } else {
                    testOptions.style.display = 'none';
                }
            });
        }
    }

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
    }

    // Format FullCalendar reponses
    const formatArgs = (res) => {
        data.id = res.id;
        data.eventName = res.title;
        data.eventDescription = res.description;
        data.eventLocation = res.location;
        data.startDate = res.startStr;
        data.endDate = res.endStr;
        data.allDay = res.allDay;
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

            // View event modal
            const viewElement = document.getElementById('kt_modal_view_event');
            viewModal = new bootstrap.Modal(viewElement);
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
            handleEditButton();
            handleAddButton();
            handleDeleteEvent();
            handleCancelButton();
            handleCloseButton();
            resetFormValidator(element);
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
