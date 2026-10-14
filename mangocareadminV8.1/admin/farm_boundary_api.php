<?php
/**
 * farm_boundary_api.php
 * Handles CRUD operations for Farm Boundaries in Firebase (/farm_boundaries).
 * Returns fetch-safe JSON responses.
 */

ob_start();
require_once __DIR__ . '/../config/config.php';

ob_clean();
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.', 'code' => 401]);
    exit();
}

$firebase = new Firebase();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// GET / LIST BOUNDARIES
if ($method === 'GET' || $action === 'list') {
    $boundaries = $firebase->get('farm_boundaries') ?? [];
    $boundaries = is_array($boundaries) ? array_values($boundaries) : [];
    echo json_encode(['success' => true, 'boundaries' => $boundaries]);
    exit();
}

// SAVE / UPDATE BOUNDARY
if ($method === 'POST' && ($action === 'save' || empty($action))) {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $input = $_POST;
    }

    $id = !empty($input['id']) ? trim($input['id']) : ('boundary_' . time() . '_' . rand(100, 999));
    $name = !empty($input['name']) ? trim($input['name']) : 'Unnamed Boundary';
    $description = !empty($input['description']) ? trim($input['description']) : '';
    $color = !empty($input['color']) ? trim($input['color']) : '#2e7d32';
    $coordinates = isset($input['coordinates']) && is_array($input['coordinates']) ? $input['coordinates'] : [];

    if (count($coordinates) < 3) {
        echo json_encode(['success' => false, 'message' => 'A valid boundary polygon requires at least 3 coordinates.']);
        exit();
    }

    $boundaryData = [
        'id' => $id,
        'name' => $name,
        'description' => $description,
        'color' => $color,
        'coordinates' => $coordinates,
        'updated_at' => time()
    ];

    $result = $firebase->update("farm_boundaries/$id", $boundaryData);

    if ($result !== false) {
        echo json_encode(['success' => true, 'message' => 'Farm boundary saved successfully.', 'boundary' => $boundaryData]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save farm boundary to Firebase.']);
    }
    exit();
}

// DELETE BOUNDARY
if ($method === 'POST' && $action === 'delete') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $input = $_POST;
    }

    $id = !empty($input['id']) ? trim($input['id']) : ($_GET['id'] ?? '');

    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'Boundary ID is required for deletion.']);
        exit();
    }

    $result = $firebase->delete("farm_boundaries/$id");

    if ($result !== false) {
        echo json_encode(['success' => true, 'message' => 'Farm boundary deleted successfully.', 'id' => $id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete farm boundary from Firebase.']);
    }
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid request method or action.']);
exit();
