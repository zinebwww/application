<?php
/**
 * API pour les notifications (AJAX)
 */

ob_start();
session_start();
require_once __DIR__ . '/../config/config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit();
}

// Charger les fonctions de notification
require_once __DIR__ . '/notifications.php';

ob_clean();
header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté (déjà fait plus haut, mais on garde pour sécurité)
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$userId = $_SESSION['user_id'];

switch ($action) {
    case 'get':
        // Récupérer les notifications non lues
        $notifications = getUnreadNotifications($userId);
        echo json_encode([
            'success' => true,
            'count' => count($notifications),
            'notifications' => $notifications
        ]);
        exit();
        
    case 'mark_read':
        // Marquer une notification comme lue
        $notificationId = $_POST['id'] ?? '';
        if (!empty($notificationId)) {
            markNotificationAsRead($notificationId, $userId);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'ID manquant']);
        }
        exit();
        
    case 'mark_all_read':
        // Marquer toutes les notifications comme lues
        markAllNotificationsAsRead($userId);
        echo json_encode(['success' => true]);
        exit();
        
    case 'count':
        // Compter les notifications non lues
        $count = countUnreadNotifications($userId);
        echo json_encode([
            'success' => true,
            'count' => $count
        ]);
        exit();
        
    default:
        echo json_encode(['success' => false, 'message' => 'Action invalide']);
        exit();
}
?>

