<?php
require_once '../config/config.php';
requireLogin();

header('Content-Type: application/json');

$firebase = new Firebase();

try {
    $stats = processAutomaticSchedules($firebase, null, true);
    syncSystemNotifications($firebase);

    $count = 0;
    $existing = $firebase->get('notifications') ?? [];
    if (is_array($existing)) {
        $count = count($existing);
    }

    echo json_encode([
        'success' => true,
        'notifications' => $count,
        'generated' => $stats['generated'] ?? 0,
        'completed' => $stats['completed'] ?? 0,
        'errors' => $stats['errors'] ?? []
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Sync failed: ' . $e->getMessage()]);
}
