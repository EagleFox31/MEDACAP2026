<?php
session_start();
include_once "../language.php";

if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
} else {

    include_once "partials/header.php";

    // Helpers & widgets (nécessaires pour les fonctions comme asset() et renderTitleMain())
    require_once 'components/helpers/helpers.php';
    require_once 'components/widgets/title-main.php';
    ?>

<!--begin::Title-->
<title><?php echo $calendarTraining ?> | CFAO Mobility Academy</title>
<!--end::Title-->
<!-- Styles -->
<link rel="stylesheet" href="<?= asset('../public/assets/css/dashboard.css') ?>">
<div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content"
     style="position: relative; background: none;">
    <!-- Background décoratif (identique au dashboard) -->
    <div style="position: absolute; top:0; left:0; width:100%; height:100%; z-index:-1; background-size:cover; background-position:center; border-radius:20px; overflow:hidden;">
        <div style="position:absolute; top:0; left:0; width:100%; height:100%; filter:blur(10px); transform:scale(1.05);
                    background-image: linear-gradient(rgba(0,0,0,0.2), rgba(0,0,0,0.1)), url('/MEDACAP/public/images/welcome_tech.png'); background-size:cover; background-position:center;">
        </div>
        <img src="/MEDACAP/views/define/views/director/tof_dashboard_tech.png" alt="Background"
             style="width:100%; height:100%; object-fit:cover; display:block; opacity:0;">
        <img src="/MEDACAP/views/define/views/director/tof_dashboard_tech.png" alt="Background"
             style="width:100%; height:100%; object-fit:cover; display:block; opacity:0;">
    </div>

    <!--begin::Toolbar-->
    <div class="toolbar" id="kt_toolbar">
        <div class=" container-fluid  d-flex flex-stack flex-wrap flex-sm-nowrap">
            <!--begin::Info-->
            <div class="d-flex flex-column align-items-start justify-content-center flex-wrap me-2">
                <!--end::Title-->
            </div>
        </div>
        <!--end::Toolbar-->
        <br>
        <div class="container-xxl">
        <?php
        renderTitleMain('ki-duotone ki-calendar fs-2 me-5', 3, $calendarTraining);
        ?>
        </div>
        <br>
        <!--begin::Post-->
        <div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
            <!--begin::Container-->
            <div class=" container-xxl ">
                <!--begin::Card-->
                <div class="card glass-effect depth-effect">
                    <!--begin::Card header-->
                    <div class="card-header">
                        <!-- <h2 class="card-title fw-bold">
                            <?php echo $calendarTraining ?>
                        </h2> -->
                        <!-- <div class="card-toolbar">
                            <button class="btn btn-flex btn-primary" data-kt-calendar="add">
                                <i class="ki-duotone ki-plus fs-2"></i>
                                <?php echo $addEvent ?>
                            </button>
                        </div> -->
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
                                    <div class="btn btn-icon btn-sm btn-active-icon-primary"
                                        id="kt_modal_add_event_close">
                                        <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span
                                                class="path2"></span></i>
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
                                        <input type="text" class="form-control form-control-solid hidden" placeholder=""
                                            name="calendar_id" />
                                        <!--end::Input-->
                                        <!--begin::Input-->
                                        <input type="text" class="form-control form-control-solid" placeholder=""
                                            name="calendar_event_name" />
                                        <!--end::Input-->
                                    </div>
                                    <!--end::Input group-->
                                    <!--begin::Input group-->
                                    <div class="fv-row mb-9">
                                        <!--begin::Label-->
                                        <label class="fs-6 fw-semibold mb-2"><?php echo $eventDescription ?></label>
                                        <!--end::Label-->
                                        <!--begin::Input-->
                                        <input type="text" class="form-control form-control-solid" placeholder=""
                                            name="calendar_event_description" />
                                        <!--end::Input-->
                                    </div>
                                    <!--end::Input group-->
                                    <!--begin::Input group-->
                                    <div class="fv-row mb-9">
                                        <!--begin::Label-->
                                        <label class="fs-6 fw-semibold mb-2"><?php echo $location ?></label>
                                        <!--end::Label-->
                                        <!--begin::Input-->
                                        <input type="text" class="form-control form-control-solid" placeholder=""
                                            name="calendar_event_location" />
                                        <!--end::Input-->
                                    </div>
                                    <!--end::Input group-->
                                    <!--begin::Input group-->
                                    <div class="fv-row mb-9">
                                        <!--begin::Checkbox-->
                                        <label class="form-check form-check-custom form-check-solid">
                                            <input class="form-check-input" type="checkbox" value=""
                                                id="kt_calendar_datepicker_allday" />
                                            <span class="form-check-label fw-semibold text-black"
                                                style="margin-left:30px" for="kt_calendar_datepicker_allday">
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
                                                <label class="fs-6 fw-semibold mb-2 required">
                                                    <?php echo $startDate ?></label>
                                                <!--end::Label-->
                                                <!--begin::Input-->
                                                <input class="form-control form-control-solid"
                                                    name="calendar_event_start_date" placeholder="Pick a start date"
                                                    id="kt_calendar_datepicker_start_date" />
                                                <!--end::Input-->
                                            </div>
                                        </div>
                                        <div class="col" data-kt-calendar="datepicker">
                                            <div class="fv-row mb-9">
                                                <!--begin::Label-->
                                                <label class="fs-6 fw-semibold mb-2"> <?php echo $startTime ?></label>
                                                <!--end::Label-->
                                                <!--begin::Input-->
                                                <input class="form-control form-control-solid"
                                                    name="calendar_event_start_time" placeholder="Pick a start time"
                                                    id="kt_calendar_datepicker_start_time" />
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
                                                <label
                                                    class="fs-6 fw-semibold mb-2 required"><?php echo $endDate ?></label>
                                                <!--end::Label-->
                                                <!--begin::Input-->
                                                <input class="form-control form-control-solid"
                                                    name="calendar_event_end_date" placeholder="Pick a end date"
                                                    id="kt_calendar_datepicker_end_date" />
                                                <!--end::Input-->
                                            </div>
                                        </div>
                                        <div class="col" data-kt-calendar="datepicker">
                                            <div class="fv-row mb-9">
                                                <!--begin::Label-->
                                                <label class="fs-6 fw-semibold mb-2"><?php echo $endTime ?></label>
                                                <!--end::Label-->
                                                <!--begin::Input-->
                                                <input class="form-control form-control-solid"
                                                    name="calendar_event_end_time" placeholder="Pick a end time"
                                                    id="kt_calendar_datepicker_end_time" />
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
                                    <button type="button" name="submit" id="kt_modal_add_event_submit"
                                        class="btn btn-primary">
                                        <span class="indicator-label">
                                            <?php echo $valider ?>
                                        </span>
                                        <span class="indicator-progress">
                                            Please wait... <span
                                                class="spinner-border spinner-border-sm align-middle ms-2"></span>
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
                                <!--begin::Close-->
                                <div class="btn btn-icon btn-sm btn-color-gray-500 btn-active-icon-primary"
                                    title="Hide Event"
                                    data-kt-users-modal-action="close" data-bs-dismiss="modal"
                                    data-kt-menu-dismiss="true">
                                    <i class="ki-duotone ki-cross fs-2x"><span class="path1"></span><span
                                            class="path2"></span></i>
                                </div>
                                <!--end::Close-->
                            </div>
                            <!--end::Modal header-->
                            <!--begin::Modal body-->
                            <div class="modal-body pt-0 pb-20 px-lg-17">
                                <!--begin::Row-->
                                <div class="d-flex">
                                    <!--begin::Icon-->
                                    <i class="ki-duotone ki-calendar-8 fs-1 text-muted me-5"><span
                                            class="path1"></span><span class="path2"></span><span
                                            class="path3"></span><span class="path4"></span><span
                                            class="path5"></span><span class="path6"></span></i>
                                    <!--end::Icon-->
                                    <div class="mb-9">
                                        <!--begin::Event name-->
                                        <div class="d-flex align-items-center mb-2">
                                            <span class="fs-3 fw-bold me-3" data-kt-calendar="event_name"></span> <span
                                                class="badge badge-light-success" data-kt-calendar="all_day"></span>
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
                                    <div class="fs-6"><span class="fw-bold"><?php echo $start ?></span> <span
                                            data-kt-calendar="event_start_date"></span></div>
                                    <!--end::Event start date/time-->
                                </div>
                                <!--end::Row-->
                                <!--begin::Row-->
                                <div class="d-flex align-items-center mb-9">
                                    <!--begin::Bullet-->
                                    <span class="bullet bullet-dot h-10px w-10px bg-danger ms-2 me-7"></span>
                                    <!--end::Bullet-->
                                    <!--begin::Event end date/time-->
                                    <div class="fs-6"><span class="fw-bold"><?php echo $end ?></span> <span
                                            data-kt-calendar="event_end_date"></span></div>
                                    <!--end::Event end date/time-->
                                </div>
                                <!--end::Row-->
                                <!--begin::Row-->
                                <div class="d-flex align-items-center">
                                    <!--begin::Icon-->
                                    <i class="ki-duotone ki-geolocation fs-1 text-muted me-5"><span
                                            class="path1"></span><span class="path2"></span></i>
                                    <!--end::Icon-->
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
                <!--end::Modal - New Product-->
                <!--end::Modals-->
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
var KTAppCalendar = function() {
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
    var initCalendarApp = function() {
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
            select: function(arg) {
                formatArgs(arg);
                // handleNewEvent();
            },

            // Click event --- more info: https://fullcalendar.io/docs/eventClick
            eventClick: function(arg) {
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
            datesSet: function() {
                // do some stuff
            }
        });

        calendar.render();
    }

    // Init validator
    const initValidator = () => {
        // Init form validation rules. For more info check the FormValidation plugin's official documentation:https://formvalidation.io/
        validator = FormValidation.formValidation(
            form, {
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
    // const handleAddButton = () => {
    //     addButton.addEventListener('click', e => {
    //         // Reset form data
    //         data = {
    //             id: '',
    //             eventName: '',
    //             eventDescription: '',
    //             startDate: new Date(),
    //             endDate: new Date(),
    //             allDay: false
    //         };
    //         handleNewEvent();
    //     });
    // }

    // Handle add new event
    // const handleNewEvent = () => {
    //     // Update modal title
    //     modalTitle.innerText = "<?php echo $addEvent ?>";

    //     modal.show();

    //     // Select datepicker wrapper elements
    //     const datepickerWrappers = form.querySelectorAll('[data-kt-calendar="datepicker"]');

    //     // Handle all day toggle
    //     const allDayToggle = form.querySelector('#kt_calendar_datepicker_allday');
    //     allDayToggle.addEventListener('click', e => {
    //         if (e.target.checked) {
    //             allDaypicker = "non";
    //             datepickerWrappers.forEach(dw => {
    //                 dw.classList.add('d-none');
    //             });
    //         } else {
    //             allDaypicker = "oui";
    //             endFlatpickr.setDate(data.startDate, true, 'Y-m-d');
    //             datepickerWrappers.forEach(dw => {
    //                 dw.classList.remove('d-none');
    //             });
    //         }
    //     });

    //     populateForm(data);

    //     // Handle submit form
    //     submitButton.addEventListener('click', function(e) {
    //         // Prevent default button action
    //         e.preventDefault();

    //         $.ajax({
    //             type: 'post',
    //             data: {
    //                 submit: 1,
    //                 eventName: eventName.value,
    //                 eventDescription: eventDescription.value,
    //                 eventLocation: eventLocation.value,
    //                 startDate: startDatepicker.value,
    //                 endDate: endDatepicker.value,
    //                 startTime: startTimepicker.value,
    //                 endTime: endTimepicker.value,
    //                 allDay: allDaypicker
    //             },
    //             success: function(response) {
    //                 modal.hide();
    //                 location.reload();
    //             }
    //         })
    //     });
    // }

    window.onload = (event) => {
        $.ajax({
            url: 'display_event.php',
            type: 'get',
            dataType: 'json',
            success: function(response) {
                var result = response;

                var events = [];
                $.each(result, function(i, item) {
                    datas.push({
                        id: result[i].id,
                        code: result[i].code,
                        name: result[i].name,
                        location: result[i].location,
                        brand: result[i].brand,
                        type: result[i].type,
                        startDate: result[i].startDate,
                        endDate: result[i].endDate
                    });
                    
                    // Merge date & time
                    var startDate = Object.values(datas[i].startDate);

                    // Fonction pour convertir la date au format dd/mm/yyyy en objet Date
                    function parseDate(dateString) {
                        // Séparer la chaîne de date en jour, mois et année
                        var parts = dateString.split("/");
                        var day = parseInt(parts[0], 10); // Jour
                        var month = parseInt(parts[1], 10) - 1; // Mois (0-11)
                        var year = parseInt(parts[2], 10); // Année

                        // Créer un objet Date
                        return new Date(year, month, day);
                    }

                    // Fonction pour changer la color
                    function backGroundColor(type, location) {
                        if (location == 'CFR') {
                            return 'green';
                        } else {
                            return 'orange';
                        }
                        if (type == 'Distancielle') {
                            return '#ff5e08';
                        }
                    }

                    startDate.forEach((start, j) => {
                        var startDte = start;
                        var endDte = datas[i].endDate[j];
                        var startDateTime = moment(parseDate(startDte)).set({ hour: 8, minute: 0, second: 0 }).format();
                        var endDateTime = moment(parseDate(endDte)).set({ hour: 17, minute: 0, second: 0 }).format();

                        
                        events.push({
                            id: datas[i].id,
                            title: `${datas[i].code} : ${datas[i].name}`,
                            description: '',
                            location: datas[i].location[j],
                            start: startDateTime,
                            end: endDateTime,
                            color: backGroundColor(datas[i].type, datas[i].location[j])
                        })
                    });
                });

                events.forEach((event, i) => {                    
                    // Add new event to calendar
                    calendar.addEvent(event);
                    calendar.render();
                });
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
        submitButton.addEventListener('click', function(e) {
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
                success: function(response) {
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
                success: function(response) {
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
        cancelButton.addEventListener('click', function(e) {
            e.preventDefault();

            modal.hide(); // Hide modal	
        });
    }

    // Handle close button
    const handleCloseButton = () => {
        // Edit event modal close button
        closeButton.addEventListener('click', function(e) {
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
        init: function() {
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