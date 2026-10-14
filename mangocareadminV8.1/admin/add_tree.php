<?php
require_once '../config/config.php';

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: application/json');

// Read JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (isset($input['id']) && isset($input['lat']) && isset($input['lng'])) {
    // Check if user is logged in (JSON friendly)
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized: Please log in again.']);
        exit;
    }

    $firebase = new Firebase();
    
    // Sanitize tree ID (remove illegal Firebase characters and pad numbers)
    $treeId = sanitizeTreeId($input['id']);
    
    if (empty($treeId)) {
        echo json_encode(['success' => false, 'message' => 'Tree ID cannot be empty or invalid.']);
        exit;
    }

    // Check if tree ID already exists
    $existingTree = $firebase->get('trees/' . $treeId);
    if ($existingTree !== null && $existingTree !== false) {
        echo json_encode(['success' => false, 'message' => "Tree ID '$treeId' already exists. Please use a unique ID."]);
        exit;
    }
    
    // Prepare QR data structure to match qr-generator.php
    $qrData = [
        'tree_id' => $treeId,
        'location' => 'Marked from Map',
        'generated_at' => time() * 1000,
        'generated_by' => $_SESSION['admin_username'] ?? 'Admin'
    ];

    // Create or update tree with QR integration
    $treeData = [
        'tree_id' => $treeId,
        'latitude' => (float)$input['lat'],
        'longitude' => (float)$input['lng'],
        'lat' => (float)$input['lat'],
        'lng' => (float)$input['lng'],
        'location' => 'Marked from Map',
        'has_qr' => true,
        'qr_data' => json_encode($qrData),
        'created_at' => date('M d, Y g:i A'),
        'timestamp' => time() * 1000,
        'generated_by' => $_SESSION['admin_username'] ?? 'Admin'
    ];
    
    $result = $firebase->update('trees/' . $treeId, $treeData);
    
    if ($result !== false) {
        syncSystemNotifications($firebase);
        echo json_encode(['success' => true, 'message' => "Tree $treeId (Lat: {$input['lat']}, Lng: {$input['lng']}) marked successfully!"]);
    } else {
        // Log more details internally and return a clearer message
        error_log("Firebase save failed for Tree ID: $treeId. Check config/config.php for valid FIREBASE_DATABASE_URL and SECRET.");
        echo json_encode(['success' => false, 'message' => 'Failed to save to Firebase. Ensure your Database Rules allow writing or check your Connection/Secret.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid or missing data (lat/lng/id).']);
}

?>
