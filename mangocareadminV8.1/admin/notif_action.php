<?php
require_once '../config/config.php';
requireLogin();

header('Content-Type: application/json');

$firebase = new Firebase();

$action = $_REQUEST['action'] ?? '';
$id     = $_REQUEST['id'] ?? '';
$limit  = isset($_REQUEST['limit']) ? (int) $_REQUEST['limit'] : null;

function countUnreadServer($notifications)
{
    $unread = 0;
    foreach ($notifications as $not) {
        if (is_array($not) && empty($not['read'])) $unread++;
    }
    return $unread;
}

try {
    $notifications = $firebase->get('notifications') ?? [];
    $notifications = is_array($notifications) ? $notifications : [];

    switch ($action) {

        case 'list':
            uasort($notifications, function ($a, $b) {
                return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
            });
            $items = [];
            foreach ($notifications as $key => $not) {
                if (!is_array($not)) continue;
                $items[] = array_merge($not, ['id' => (string) $key]);
            }
            if ($limit !== null) {
                $items = array_slice($items, 0, $limit);
            }
            echo json_encode([
                'success' => true,
                'items' => $items,
                'unread' => countUnreadServer($notifications)
            ]);
            exit;

        case 'mark_read':
        case 'mark_unread':
            if ($id === '' || !isset($notifications[$id])) {
                echo json_encode(['success' => false, 'error' => 'Notification not found.']);
                exit;
            }
            $firebase->update('notifications/' . $id, ['read' => ($action === 'mark_read')]);
            break;

        case 'mark_all':
            $updates = [];
            foreach ($notifications as $key => $not) {
                if (is_array($not) && empty($not['read'])) {
                    $updates[$key] = ['read' => true];
                }
            }
            if (!empty($updates)) {
                $firebase->update('notifications', $updates);
            }
            break;

        case 'clear_read':
            $updates = [];
            foreach ($notifications as $key => $not) {
                if (is_array($not) && !empty($not['read'])) {
                    $updates[$key] = null;
                }
            }
            if (!empty($updates)) {
                $firebase->update('notifications', $updates);
            }
            break;

        case 'delete':
            if ($id === '' || !isset($notifications[$id])) {
                echo json_encode(['success' => false, 'error' => 'Notification not found.']);
                exit;
            }
            $firebase->delete('notifications/' . $id);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action: ' . $action]);
            exit;
    }

    $notificationsAfter = $firebase->get('notifications') ?? [];
    $notificationsAfter = is_array($notificationsAfter) ? $notificationsAfter : [];
    echo json_encode([
        'success' => true,
        'unread' => countUnreadServer($notificationsAfter)
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Action failed: ' . $e->getMessage()]);
}