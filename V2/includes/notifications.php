<?php
/**
 * Système de notifications
 * Gère les notifications internes et l'envoi d'emails
 */

// Dossier pour stocker les notifications (fichier JSON)
define('NOTIFICATIONS_FILE', __DIR__ . '/../data/notifications.json');

// Créer le dossier data s'il n'existe pas
if (!file_exists(dirname(NOTIFICATIONS_FILE))) {
    mkdir(dirname(NOTIFICATIONS_FILE), 0755, true);
}

/**
 * Créer une notification
 */
function createNotification($userId, $type, $message, $link = '', $sendEmail = false) {
    $notifications = loadNotifications();
    
    $notification = [
        'id' => uniqid('notif_', true),
        'user_id' => $userId,
        'type' => $type, // 'info', 'success', 'warning', 'danger'
        'message' => $message,
        'link' => $link,
        'read' => false,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $notifications[] = $notification;
    saveNotifications($notifications);
    
    // Envoyer un email si demandé
    if ($sendEmail) {
        sendNotificationEmail($userId, $message, $link);
    }
    
    return $notification['id'];
}

/**
 * Charger les notifications depuis le fichier JSON
 */
function loadNotifications() {
    if (!file_exists(NOTIFICATIONS_FILE)) {
        return [];
    }
    
    $content = file_get_contents(NOTIFICATIONS_FILE);
    $notifications = json_decode($content, true);
    
    return $notifications ? $notifications : [];
}

/**
 * Sauvegarder les notifications
 */
function saveNotifications($notifications) {
    file_put_contents(NOTIFICATIONS_FILE, json_encode($notifications, JSON_PRETTY_PRINT));
}

/**
 * Obtenir les notifications non lues d'un utilisateur
 */
function getUnreadNotifications($userId) {
    $notifications = loadNotifications();
    $userNotifications = [];
    
    foreach ($notifications as $notif) {
        // Vérifier que la notification est pour cet utilisateur et non lue
        if (isset($notif['user_id']) && $notif['user_id'] == $userId && (!isset($notif['read']) || !$notif['read'])) {
            $userNotifications[] = $notif;
        }
    }
    
    // Trier par date (plus récentes en premier)
    usort($userNotifications, function($a, $b) {
        $dateA = isset($a['created_at']) ? strtotime($a['created_at']) : 0;
        $dateB = isset($b['created_at']) ? strtotime($b['created_at']) : 0;
        return $dateB - $dateA;
    });
    
    return $userNotifications;
}

/**
 * Compter les notifications non lues
 */
function countUnreadNotifications($userId) {
    return count(getUnreadNotifications($userId));
}

/**
 * Marquer une notification comme lue
 */
function markNotificationAsRead($notificationId, $userId) {
    $notifications = loadNotifications();
    
    foreach ($notifications as &$notif) {
        if ($notif['id'] === $notificationId && $notif['user_id'] == $userId) {
            $notif['read'] = true;
            break;
        }
    }
    
    saveNotifications($notifications);
}

/**
 * Marquer toutes les notifications comme lues
 */
function markAllNotificationsAsRead($userId) {
    $notifications = loadNotifications();
    
    foreach ($notifications as &$notif) {
        if ($notif['user_id'] == $userId && !$notif['read']) {
            $notif['read'] = true;
        }
    }
    
    saveNotifications($notifications);
}

/**
 * Envoyer un email de notification
 * DÉSACTIVÉ - Utilise maintenant PHPMailer via send_mail.php
 */
function sendNotificationEmail($userId, $message, $link = '') {
    // Fonction désactivée - les emails sont maintenant gérés par PHPMailer
    return true;
    // Vérifier si PDO est disponible
    if (!isset($GLOBALS['pdo'])) {
        // Essayer de se connecter si nécessaire
        if (!file_exists(__DIR__ . '/../config.php')) {
            return false;
        }
        require_once __DIR__ . '/../config.php';
    }
    
    global $pdo;
    
    // Récupérer l'email de l'utilisateur
    $stmt = $pdo->prepare("SELECT email, nom FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user || empty($user['email'])) {
        return false;
    }
    
    $to = $user['email'];
    $subject = "Notification - Gestion des Frais de Déplacement";
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #0d6efd; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f8f9fa; }
            .button { display: inline-block; padding: 10px 20px; background-color: #0d6efd; color: white; text-decoration: none; border-radius: 5px; margin-top: 10px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Gestion des Frais de Déplacement</h2>
            </div>
            <div class='content'>
                <p>Bonjour " . htmlspecialchars($user['nom']) . ",</p>
                <p>" . nl2br(htmlspecialchars($message)) . "</p>
    ";
    
    if (!empty($link)) {
        $appUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
        $fullLink = $appUrl . '/' . $link;
        $body .= "<a href='" . htmlspecialchars($fullLink) . "' class='button'>Voir les détails</a>";
    }
    
    $body .= "
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Gestion Frais <noreply@example.com>" . "\r\n";
    
    return @mail($to, $subject, $body, $headers);
}

/**
 * Nettoyer les anciennes notifications (plus de 30 jours)
 */
function cleanOldNotifications() {
    $notifications = loadNotifications();
    $cleaned = [];
    $thirtyDaysAgo = date('Y-m-d H:i:s', strtotime('-30 days'));
    
    foreach ($notifications as $notif) {
        if (strtotime($notif['created_at']) >= strtotime($thirtyDaysAgo)) {
            $cleaned[] = $notif;
        }
    }
    
    saveNotifications($cleaned);
}

// Nettoyer les anciennes notifications à chaque chargement
cleanOldNotifications();
?>

