<!-- FullCalendar CSS -->
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/main.min.css' rel='stylesheet' />
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>
<!-- Bootstrap 5 Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
    #smart-calendar {
        max-width: 1100px;
        margin: 0 auto;
        background: white;
        padding: 20px;
        border-radius: 15px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    }

    .fc-event {
        cursor: pointer;
        border: none;
        border-radius: 4px;
        padding: 2px 4px;
        font-size: 0.85em;
        transition: transform 0.2s;
    }

    .fc-event:hover {
        transform: scale(1.02);
        z-index: 100 !important;
    }

    /* Simple header */
    .fc-toolbar-title {
        font-size: 1.5rem !important;
        font-weight: 500;
        color: #333;
    }

    /* Clean events */
    .fc-event {
        border-radius: 4px;
        padding: 4px 6px;
        font-size: 0.9em;
        border: none;
    }

    /* Filters sidebar if needed later */
    .calendar-legend {
        display: flex;
        gap: 15px;
        margin-bottom: 20px;
        flex-wrap: wrap;
        justify-content: center;
    }

    .legend-item {
        display: flex;
        align-items: center;
        font-size: 0.9em;
        color: #666;
    }

    .legend-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin-right: 5px;
    }
</style>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h3 class="fw-bold text-primary"><i class="bi bi-calendar3"></i> Calendrier</h3>
        </div>
    </div>



    <!-- Detail Modal -->
    <div class="modal fade" id="eventDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="eventModalTitle">Détails de la demande</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="eventModalDescription" style="white-space: pre-line;"></p>
                    <div id="eventModalStatusBadge" class="mb-3"></div>
                    <!-- Future: More details via AJAX could go here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                    <a href="#" id="eventModalActionBtn" class="btn btn-primary">Voir / Traiter</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Legend -->
    <?php
    $role = $_SESSION['user_role'] ?? 'employe';
    ?>
    <div class="calendar-legend">
        <?php if ($role === 'employe'): ?>
            <div class="legend-item"><span class="legend-dot" style="background: #ffc107;"></span> En attente</div>
            <div class="legend-item"><span class="legend-dot" style="background: #198754;"></span> Validée/Remboursée</div>
            <div class="legend-item"><span class="legend-dot" style="background: #dc3545;"></span> Rejetée</div>
        <?php elseif ($role === 'manager'): ?>
            <div class="legend-item"><span class="legend-dot" style="background: #6f42c1;"></span> À Valider (Normal)</div>
            <div class="legend-item"><span class="legend-dot" style="background: #dc3545;"></span> À Valider (Urgent > 3j)</div>
            <div class="legend-item"><span class="legend-dot" style="background: #e9ecef; border: 1px solid #ccc;"></span> Mission (Planning)</div>
            <div class="legend-item"><span class="legend-dot" style="background: #343a40;"></span> Rejetée (Historique)</div>
        <?php elseif ($role === 'admin'): ?>
            <div class="legend-item"><span class="legend-dot" style="background: #6f42c1;"></span> Validation (Standard)</div>
            <div class="legend-item"><span class="legend-dot" style="background: #dc3545;"></span> Validation (Urgente)</div>
            <div class="legend-item"><span class="legend-dot" style="background: #198754;"></span> Remboursée</div>
            <div class="legend-item"><span class="legend-dot" style="background: #343a40;"></span> Rejetée (Historique)</div>
        <?php endif; ?>
    </div>

    <div id='smart-calendar'></div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var calendarEl = document.getElementById('smart-calendar');
        var eventModal = new bootstrap.Modal(document.getElementById('eventDetailModal'));
        var modalTitle = document.getElementById('eventModalTitle');
        var modalDesc = document.getElementById('eventModalDescription');
        var modalBtn = document.getElementById('eventModalActionBtn');

        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            locale: 'fr',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,listMonth'
            },
            buttonText: {
                today: "Aujourd'hui",
                month: 'Mois',
                list: 'Liste'
            },
            events: '../../../public/api/calendar_events.php',
            
            // Locked Down (Read Only)
            editable: false,
            selectable: false,
            eventStartEditable: false,
            eventDurationEditable: false,
            eventResizableFromStart: false,

            eventClick: function (info) {
                info.jsEvent.preventDefault();

                // Set content
                modalTitle.textContent = info.event.title;
                var props = info.event.extendedProps;
                modalDesc.textContent = props.description || "Aucun détail.";

                // Action Button Logic
                var detailId = props.detail_id;
                var roleView = props.role_view;
                var isRejected = props.is_rejected; // Boolean from PHP
                var actionUrl = '#';
                var btnText = '';
                var showBtn = false;

                if (isRejected) {
                    // Rejected: No action button, just view
                    showBtn = false;
                    modalTitle.textContent += " (Rejetée)";
                } else {
                    if (roleView === 'admin' && detailId) {
                        actionUrl = 'details_demande.php?id=' + detailId;
                        btnText = "Traiter le dossier";
                        showBtn = true;
                    } else if (roleView === 'manager' && detailId) {
                        // Managers see planning (gray) + validation (purple/red)
                        // If status is 'valide_manager' etc (planning), they can still view details but maybe not "Action"
                        if (props.status === 'soumis') {
                             btnText = "Voir détails / Valider";
                        } else {
                             btnText = "Voir la demande";
                        }
                        actionUrl = 'demandes.php?action=voir&id=' + detailId;
                        showBtn = true;
                    } else if (roleView === 'employee' && detailId) {
                        actionUrl = 'mes_demandes.php?action=voir&id=' + detailId;
                        btnText = "Voir ma demande";
                        showBtn = true;
                    }
                }

                if (showBtn && actionUrl !== '#') {
                    modalBtn.style.display = 'inline-block';
                    modalBtn.textContent = btnText;
                    modalBtn.href = actionUrl;
                    modalBtn.target = "_self";
                } else {
                    modalBtn.style.display = 'none';
                }

                eventModal.show();
            },

            eventDidMount: function (info) {
                // Optional tooltip
                if (info.event.extendedProps.description) {
                    new bootstrap.Tooltip(info.el, {
                        title: info.event.extendedProps.description,
                        placement: 'top',
                        trigger: 'hover',
                        container: 'body'
                    });
                }
            }
        });

        calendar.render();
    });
</script>