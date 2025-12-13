<?php
header('Content-Type: application/json');

// Adjust paths based on location: public/api/calendar_events.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../app/Services/CalendarService.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$userId = $_SESSION['user_id'];
$role = $_SESSION['user_role'] ?? 'employe';

try {
    $calendarService = new CalendarService($pdo);
    $events = $calendarService->getEventsForUser($userId, $role);
    echo json_encode($events);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
