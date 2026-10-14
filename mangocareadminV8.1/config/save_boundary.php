<?php
/**
 * save_boundary.php
 * Saves and loads the farm boundary polygon coordinates to/from Firebase.
 * Called via fetch() from the admin dashboard — must always return JSON, never redirect.
 */

// Buffer output so no accidental whitespace/warnings break JSON response
ob_start();

require_once __DIR__ . '/config.php';

// Always return JSON — clear any buffered output before headers
ob_clean();
header('Content-Type: application/json');

// ─── Auth check: return JSON 401 instead of redirecting (fetch-safe) ─────────
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.', 'code' => 401]);
    exit();
}

$firebase = new Firebase();

// ─── GET: Load existing boundary ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' || ($_GET['action'] ?? '') === 'get') {
    $boundary = $firebase->get('settings/farm_boundary');
    if (is_array($boundary)) {
        echo json_encode(['success' => true, 'boundary' => array_values($boundary)]);
    } else {
        echo json_encode(['success' => true, 'boundary' => []]);
    }
    exit();
}

// ─── POST: Save boundary ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input    = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE || !isset($input['boundary']) || !is_array($input['boundary'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid boundary data.']);
        exit();
    }

    $coords = $input['boundary'];

    // Validate each coordinate pair
    foreach ($coords as $c) {
        if (!isset($c['lat'], $c['lng']) || !is_numeric($c['lat']) || !is_numeric($c['lng'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid coordinate format.']);
            exit();
        }
    }

    // If empty array, clear the boundary
    if (count($coords) === 0) {
        $firebase->delete('settings/farm_boundary');
        echo json_encode(['success' => true, 'message' => 'Boundary cleared.']);
        exit();
    }

    $result = $firebase->set('settings/farm_boundary', $coords);
    if ($result !== false) {
        echo json_encode(['success' => true, 'message' => 'Boundary saved.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to write to Firebase. Check Firebase rules/secret.']);
    }
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
