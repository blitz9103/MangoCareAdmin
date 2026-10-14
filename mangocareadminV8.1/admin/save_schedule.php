<?php
require_once '../config/config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$treeId = $_POST['tree_id'] ?? '';
$sprayDate = $_POST['spray_date'] ?? '';
$sprayTime = $_POST['spray_time'] ?? '';
$notes = trim($_POST['notes'] ?? '');

if (empty($treeId) || empty($sprayDate) || empty($sprayTime)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields (Date and Time).']);
    exit();
}

$firebase = new Firebase();

// Convert time to 24-hour format if needed
$time24 = convertTo24Hour($sprayTime);

$scheduleData = [
    'spray_date' => $sprayDate,
    'spray_time' => $time24,
    'notes' => $notes,
    'status' => 'pending',
    'created_at' => date('M d, Y g:i A'),
    'created_by' => $_SESSION['admin_username'] ?? 'Admin',
    'auto_generated' => false
];

$result = $firebase->push('trees/' . $treeId . '/spray_schedules', $scheduleData);

if ($result !== false && !empty($result['name'])) {
    syncSystemNotifications($firebase);
    echo json_encode(['success' => true, 'message' => 'Spray schedule created successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save schedule to Firebase.']);
}
