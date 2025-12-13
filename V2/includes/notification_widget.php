<?php
/**
 * Widget de notifications à inclure dans les headers
 */
if (!isset($_SESSION['user_id'])) {
    return;
}

$userId = $_SESSION['user_id'];
$unreadCount = countUnreadNotifications($userId);
$notifications = getUnreadNotifications($userId);
?>
<!-- Widget Notifications -->
<li class="nav-item dropdown">
    <a class="nav-link text-white position-relative" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-bell"></i>
        <?php if ($unreadCount > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notificationBadge">
                <?php echo $unreadCount > 99 ? '99+' : $unreadCount; ?>
            </span>
        <?php endif; ?>
    </a>
    <ul class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notificationDropdown" style="min-width: 350px; max-height: 500px; overflow-y: auto;">
        <li class="dropdown-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-bell"></i> Notifications</span>
            <?php if ($unreadCount > 0): ?>
                <button class="btn btn-sm btn-link text-decoration-none p-0" onclick="markAllNotificationsRead()">
                    <small>Tout marquer comme lu</small>
                </button>
            <?php endif; ?>
        </li>
        <li><hr class="dropdown-divider"></li>
        <div id="notificationsList">
            <?php if (empty($notifications)): ?>
                <li class="px-3 py-2 text-center text-muted">
                    <small>Aucune notification</small>
                </li>
            <?php else: ?>
                <?php foreach (array_slice($notifications, 0, 10) as $notif): ?>
                    <li>
                        <a class="dropdown-item notification-item <?php echo !$notif['read'] ? 'bg-light' : ''; ?>" 
                           href="<?php echo !empty($notif['link']) ? htmlspecialchars($notif['link']) : '#'; ?>"
                           data-id="<?php echo htmlspecialchars($notif['id']); ?>"
                           onclick="event.preventDefault(); markNotificationRead('<?php echo htmlspecialchars($notif['id']); ?>', '<?php echo !empty($notif['link']) ? htmlspecialchars($notif['link']) : ''; ?>');">
                            <div class="d-flex w-100 justify-content-between">
                                <div class="flex-grow-1">
                                    <small class="text-<?php echo $notif['type']; ?>">
                                        <i class="bi bi-<?php 
                                            echo $notif['type'] === 'success' ? 'check-circle' : 
                                                ($notif['type'] === 'danger' ? 'x-circle' : 
                                                ($notif['type'] === 'warning' ? 'exclamation-triangle' : 'info-circle')); 
                                        ?>"></i>
                                    </small>
                                    <span class="ms-2"><?php echo htmlspecialchars($notif['message']); ?></span>
                                </div>
                            </div>
                            <small class="text-muted d-block mt-1">
                                <?php echo date('d/m/Y H:i', strtotime($notif['created_at'])); ?>
                            </small>
                        </a>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </ul>
</li>

<script>
// Fonction pour marquer une notification comme lue
function markNotificationRead(notificationId, link) {
    // Déterminer le chemin correct selon l'emplacement
    let basePath = '';
    const path = window.location.pathname;
    if (path.includes('/admin/') || path.includes('app/Views/admin/')) {
        basePath = '../../../';
    } else if (path.includes('/manager/') || path.includes('app/Views/manager/')) {
        basePath = '../../../';
    } else if (path.includes('/employee/') || path.includes('app/Views/employee/')) {
        basePath = '../../../';
    }
    fetch(basePath + 'includes/notifications_api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=mark_read&id=' + encodeURIComponent(notificationId)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Retirer le style "non lu"
            const item = document.querySelector(`[data-id="${notificationId}"]`);
            if (item) {
                item.classList.remove('bg-light');
            }
            updateNotificationCount();
            // Rediriger si un lien est fourni
            if (link && link !== '#') {
                window.location.href = link;
            }
        }
    });
}

// Fonction pour marquer toutes les notifications comme lues
function markAllNotificationsRead() {
    let basePath = '';
    const path = window.location.pathname;
    if (path.includes('/admin/') || path.includes('app/Views/admin/')) {
        basePath = '../../../';
    } else if (path.includes('/manager/') || path.includes('app/Views/manager/')) {
        basePath = '../../../';
    } else if (path.includes('/employee/') || path.includes('app/Views/employee/')) {
        basePath = '../../../';
    }
    fetch(basePath + 'includes/notifications_api.php?action=mark_all_read')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateNotificationCount();
            loadNotifications();
        }
    });
}

// Fonction pour mettre à jour le compteur
function updateNotificationCount() {
    let basePath = '';
    const path = window.location.pathname;
    if (path.includes('/admin/') || path.includes('app/Views/admin/')) {
        basePath = '../../../';
    } else if (path.includes('/manager/') || path.includes('app/Views/manager/')) {
        basePath = '../../../';
    } else if (path.includes('/employee/') || path.includes('app/Views/employee/')) {
        basePath = '../../../';
    }
    fetch(basePath + 'includes/notifications_api.php?action=count')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const badge = document.getElementById('notificationBadge');
            if (data.count > 0) {
                if (!badge) {
                    const link = document.getElementById('notificationDropdown');
                    const span = document.createElement('span');
                    span.id = 'notificationBadge';
                    span.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
                    span.textContent = data.count > 99 ? '99+' : data.count;
                    link.appendChild(span);
                } else {
                    badge.textContent = data.count > 99 ? '99+' : data.count;
                }
            } else {
                if (badge) {
                    badge.remove();
                }
            }
        }
    });
}

// Fonction pour charger les notifications
function loadNotifications() {
    let basePath = '';
    const path = window.location.pathname;
    if (path.includes('/admin/') || path.includes('app/Views/admin/')) {
        basePath = '../../../';
    } else if (path.includes('/manager/') || path.includes('app/Views/manager/')) {
        basePath = '../../../';
    } else if (path.includes('/employee/') || path.includes('app/Views/employee/')) {
        basePath = '../../../';
    }
    fetch(basePath + 'includes/notifications_api.php?action=get')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const list = document.getElementById('notificationsList');
            if (data.notifications.length === 0) {
                list.innerHTML = '<li class="px-3 py-2 text-center text-muted"><small>Aucune notification</small></li>';
            } else {
                let html = '';
                data.notifications.slice(0, 10).forEach(notif => {
                    const icon = notif.type === 'success' ? 'check-circle' : 
                                (notif.type === 'danger' ? 'x-circle' : 
                                (notif.type === 'warning' ? 'exclamation-triangle' : 'info-circle'));
                    const date = new Date(notif.created_at).toLocaleString('fr-FR');
                    html += `
                        <li>
                            <a class="dropdown-item notification-item ${!notif.read ? 'bg-light' : ''}" 
                               href="${notif.link || '#'}"
                               data-id="${notif.id}"
                               onclick="event.preventDefault(); markNotificationRead('${notif.id}', '${notif.link || ''}');">
                                <div class="d-flex w-100 justify-content-between">
                                    <div class="flex-grow-1">
                                        <small class="text-${notif.type}">
                                            <i class="bi bi-${icon}"></i>
                                        </small>
                                        <span class="ms-2">${notif.message}</span>
                                    </div>
                                </div>
                                <small class="text-muted d-block mt-1">${date}</small>
                            </a>
                        </li>
                    `;
                });
                list.innerHTML = html;
            }
        }
    });
}

// Actualiser les notifications toutes les 30 secondes
setInterval(function() {
    updateNotificationCount();
    // Recharger seulement si le dropdown est ouvert
    const dropdown = document.getElementById('notificationDropdown');
    if (dropdown && dropdown.getAttribute('aria-expanded') === 'true') {
        loadNotifications();
    }
}, 30000);

// Actualiser au clic sur le dropdown
document.getElementById('notificationDropdown')?.addEventListener('click', function() {
    loadNotifications();
});
</script>

<style>
.notification-dropdown {
    max-height: 500px;
    overflow-y: auto;
}

.notification-item {
    white-space: normal;
    word-wrap: break-word;
}

.notification-item:hover {
    background-color: #f8f9fa !important;
}
</style>

