<?php
function renderCalendarComponent(array $events, bool $canAddEvent = false): string {
    // Transformer les événements pour FullCalendar
    $formattedEvents = array_map(function($event) {
        return [
            'id' => $event['id'],
            'title' => $event['name'],
            'start' => $event['startDate'],
            'end' => $event['endDate'],
            'allDay' => $event['allDay'],
            'extendedProps' => [
                'description' => $event['description'],
                'location' => $event['location']
            ]
        ];
    }, $events);

    ob_start();
    ?>
    <!-- Calendrier -->
    <div id="calendar-container" class="glass-effect flex-fill overflow-hidden d-flex flex-column h-100 p-4">
        <?php if ($canAddEvent): ?>
            <!--begin::Card header-->
            <div class="card-header d-flex justify-content-between align-items-center mb-4">
                <h3 class="card-title fw-bold text-primary">Calendrier</h3>
                <button class="btn btn-primary btn-sm rounded-pill px-4 py-2 shadow-sm" data-kt-calendar="add">
                    <i class="ki-duotone ki-plus fs-2 me-2"></i> <?= $addEvent ?? 'Ajouter Événement' ?>
                </button>
            </div>
            <!--end::Card header-->
        <?php endif; ?>

        <!--begin::Card body-->
        <div class="card-body p-0 h-100">
            <div id="kt_calendar_app" class="h-100 rounded"></div>
        </div>
        <!--end::Card body-->
    </div>

    <!-- Modal événement -->
    <div class="modal fade" id="kt_modal_view_event" tabindex="-1" data-bs-focus="false" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered mw-650px">
            <div class="modal-content rounded-3 shadow-lg">
                <div class="modal-header border-0 justify-content-end p-4">
                    <div class="btn btn-icon btn-sm btn-color-gray-500 btn-active-icon-primary"
                         data-kt-users-modal-action="close" data-bs-dismiss="modal" data-kt-menu-dismiss="true">
                        <i class="ki-duotone ki-cross fs-2x"><span class="path1"></span><span class="path2"></span></i>
                    </div>
                </div>
                <div class="modal-body pt-0 pb-20 px-lg-17">
                    <div class="d-flex align-items-start">
                        <i class="ki-duotone ki-calendar-8 fs-1 text-primary me-5">
                            <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                            <span class="path4"></span><span class="path5"></span><span class="path6"></span>
                        </i>
                        <div class="mb-9 flex-grow-1">
                            <div class="d-flex align-items-center mb-3">
                                <span class="fs-3 fw-bold text-dark me-3" data-kt-calendar="event_name"></span>
                                <span class="badge badge-light-success" data-kt-calendar="all_day"></span>
                            </div>
                            <div class="fs-6 hidden" data-kt-calendar="event_id"></div>
                            <div class="fs-6 text-muted" data-kt-calendar="event_description"></div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center mb-3">
                        <span class="bullet bullet-dot h-10px w-10px bg-success ms-2 me-7"></span>
                        <div class="fs-6"><span class="fw-bold"><?php echo lang('start') ?></span>
                            <span data-kt-calendar="event_start_date"></span>
                        </div>
                    </div>
                    <div class="d-flex align-items-center mb-9">
                        <span class="bullet bullet-dot h-10px w-10px bg-danger ms-2 me-7"></span>
                        <div class="fs-6"><span class="fw-bold"><?php echo lang('end') ?></span>
                            <span data-kt-calendar="event_end_date"></span>
                        </div>
                    </div>
                    <div class="d-flex align-items-center mb-2">
                        <i class="ki-duotone ki-geolocation fs-1 text-primary me-5">
                            <span class="path1"></span><span class="path2"></span>
                        </i>
                        <div class="fs-6 text-muted" data-kt-calendar="event_location"></div>
                    </div>
                    <div class="d-flex justify-content-end mt-5">
                        <button class="btn btn-sm btn-primary me-2 rounded-pill" data-kt-calendar="edit_event"><?php echo lang('edit') ?></button>
                        <button class="btn btn-sm btn-danger rounded-pill" data-kt-calendar="delete_event"><?php echo lang('delete') ?></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal d'ajout/édition -->
    <div class="modal fade" id="kt_modal_add_event" tabindex="-1" data-bs-focus="false" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered mw-650px">
            <div class="modal-content rounded-3 shadow-lg">
                <form id="kt_modal_add_event_form" method="POST" action="">
                    <div class="modal-header border-0 p-4">
                        <h5 class="modal-title text-primary"><?php echo lang('add_event') ?></h5>
                        <div class="btn btn-icon btn-sm btn-active-light-primary" data-bs-dismiss="modal">
                            <i class="ki-duotone ki-cross fs-2x"><span class="path1"></span><span class="path2"></span></i>
                        </div>
                    </div>
                    <div class="modal-body px-lg-17">
                        <input type="hidden" name="id" data-kt-calendar="event_id">
                        <div class="mb-5">
                            <label class="required form-label"><?php echo lang('event_name') ?></label>
                            <input type="text" class="form-control rounded" name="eventName" data-kt-calendar="event_name">
                        </div>
                        <div class="mb-5">
                            <label class="form-label"><?php echo lang('event_description') ?></label>
                            <textarea class="form-control rounded" name="eventDescription" data-kt-calendar="event_description"></textarea>
                        </div>
                        <div class="mb-5">
                            <label class="form-label"><?php echo lang('event_location') ?></label>
                            <input type="text" class="form-control rounded" name="eventLocation" data-kt-calendar="event_location">
                        </div>
                        <div class="mb-5">
                            <label class="required form-label"><?php echo lang('start_date') ?></label>
                            <input type="date" class="form-control rounded" name="startDate" data-kt-calendar="event_start_date">
                        </div>
                        <div class="mb-5">
                            <label class="required form-label"><?php echo lang('end_date') ?></label>
                            <input type="date" class="form-control rounded" name="endDate" data-kt-calendar="event_end_date">
                        </div>
                        <div class="mb-5">
                            <label class="form-check form-check-custom">
                                <input class="form-check-input" type="checkbox" name="allDay" value="oui" checked>
                                <span class="form-check-label"><?php echo lang('all_day') ?></span>
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal"><?php echo lang('cancel') ?></button>
                        <button type="submit" name="submit" class="btn btn-primary rounded-pill"><?php echo lang('save') ?></button>
                        <button type="submit" name="update" class="btn btn-primary rounded-pill d-none" data-kt-calendar="update"><?php echo lang('update') ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var KTAppCalendar = function() {
                var calendar;
                var viewModal = new bootstrap.Modal(document.getElementById('kt_modal_view_event'));
                var addModal = new bootstrap.Modal(document.getElementById('kt_modal_add_event'));
                var form = document.getElementById('kt_modal_add_event_form');

                var initCalendarApp = function() {
                    var calendarEl = document.getElementById('kt_calendar_app');

                    calendar = new FullCalendar.Calendar(calendarEl, {
                        contentHeight: 1,
                        locale: 'fr',
                        headerToolbar: {
                            left: 'prev,next today',
                            center: 'title',
                            right: 'dayGridMonth,timeGridWeek,timeGridDay'
                        },
                        navLinks: true,
                        selectable: true,
                        selectMirror: true,
                        editable: true,
                        dayMaxEvents: true,
                        events: <?php echo json_encode($formattedEvents); ?>,
                        eventClick: function(arg) {
                            var data = {
                                id: arg.event.id,
                                eventName: arg.event.title,
                                eventDescription: arg.event.extendedProps.description || '',
                                eventLocation: arg.event.extendedProps.location || '',
                                startDate: arg.event.startStr.split('T')[0],
                                endDate: arg.event.endStr.split('T')[0],
                                allDay: arg.event.allDay
                            };
                            document.querySelector('[data-kt-calendar="event_id"]').textContent = data.id;
                            document.querySelector('[data-kt-calendar="event_name"]').textContent = data.eventName;
                            document.querySelector('[data-kt-calendar="all_day"]').textContent = data.allDay ? '<?php echo lang('all_day') ?>' : '';
                            document.querySelector('[data-kt-calendar="event_description"]').textContent = data.eventDescription || '--';
                            document.querySelector('[data-kt-calendar="event_location"]').textContent = data.eventLocation || '--';
                            document.querySelector('[data-kt-calendar="event_start_date"]').textContent = moment(data.startDate).format('Do MMM, YYYY');
                            document.querySelector('[data-kt-calendar="event_end_date"]').textContent = moment(data.endDate).format('Do MMM, YYYY');
                            viewModal.show();
                        },
                        select: function(info) {
                            form.reset();
                            document.querySelector('[data-kt-calendar="event_id"]').value = '';
                            document.querySelector('[data-kt-calendar="event_name"]').value = '';
                            document.querySelector('[data-kt-calendar="event_description"]').value = '';
                            document.querySelector('[data-kt-calendar="event_location"]').value = '';
                            document.querySelector('[data-kt-calendar="event_start_date"]').value = info.startStr.split('T')[0];
                            document.querySelector('[data-kt-calendar="event_end_date"]').value = info.endStr.split('T')[0];
                            document.querySelector('[data-kt-calendar="update"]').classList.add('d-none');
                            document.querySelector('[name="submit"]').classList.remove('d-none');
                            addModal.show();
                        },
                        dateClick: function(info) {
                            form.reset();
                            document.querySelector('[data-kt-calendar="event_id"]').value = '';
                            document.querySelector('[data-kt-calendar="event_start_date"]').value = info.dateStr;
                            document.querySelector('[data-kt-calendar="event_end_date"]').value = info.dateStr;
                            document.querySelector('[data-kt-calendar="update"]').classList.add('d-none');
                            document.querySelector('[name="submit"]').classList.remove('d-none');
                            addModal.show();
                        }
                    });

                    calendar.render();

                    // Gestion des boutons Modifier/Supprimer
                    document.querySelector('[data-kt-calendar="edit_event"]').addEventListener('click', function() {
                        var id = document.querySelector('[data-kt-calendar="event_id"]').textContent;
                        var event = <?php echo json_encode($events); ?>.find(e => e.id == id);
                        if (event) {
                            document.querySelector('[data-kt-calendar="event_id"]').value = event.id;
                            document.querySelector('[data-kt-calendar="event_name"]').value = event.name;
                            document.querySelector('[data-kt-calendar="event_description"]').value = event.description || '';
                            document.querySelector('[data-kt-calendar="event_location"]').value = event.location || '';
                            document.querySelector('[data-kt-calendar="event_start_date"]').value = event.startDate;
                            document.querySelector('[data-kt-calendar="event_end_date"]').value = event.endDate;
                            document.querySelector('[name="allDay"]').checked = event.allDay;
                            document.querySelector('[data-kt-calendar="update"]').classList.remove('d-none');
                            document.querySelector('[name="submit"]').classList.add('d-none');
                            viewModal.hide();
                            addModal.show();
                        }
                    });

                    document.querySelector('[data-kt-calendar="delete_event"]').addEventListener('click', function() {
                        if (confirm('<?php echo lang('confirm_delete') ?>')) {
                            var id = document.querySelector('[data-kt-calendar="event_id"]').textContent;
                            var formData = new FormData();
                            formData.append('id', id);
                            formData.append('delete', true);
                            fetch('', {
                                method: 'POST',
                                body: formData
                            }).then(() => {
                                calendar.refetchEvents();
                                viewModal.hide();
                            });
                        }
                    });
                };

                return {
                    init: function() { initCalendarApp(); }
                };
            }();

            KTAppCalendar.init();
        });
    </script>
    <?php
    return ob_get_clean();
}
?>