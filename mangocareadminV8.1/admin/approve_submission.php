<?php
/**
 * approve_submission.php
 *
 * AJAX endpoint called when the Admin approves a pending tree submission.
 *
 * What it does:
 *  1. Validates admin session.
 *  2. Validates that Tree ID is non-empty and UNIQUE in /trees.
 *  3. Verifies the submission exists and is still pending.
 *  4. Copies submission + admin-supplied details to /trees/{treeId}.
 *  5. Generates a QR code URL (api.qrserver.com).
 *  6. Updates /tree_submissions/{submissionId}/status = "approved".
 *  7. Writes a real-time notification for the farmer in /notifications.
 *  8. Returns JSON { success, message, treeId, qrUrl }.
 */

require_once '../config/config.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

// ── Auth guard ────────────────────────────────────────────────────────────────
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in again.']);
    exit;
}

// ── Read JSON body ────────────────────────────────────────────────────────────
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request payload.']);
    exit;
}

$submissionId   = trim($input['submissionId']   ?? '');
$rawTreeId      = trim($input['treeId']         ?? '');
$treeName       = trim($input['treeName']       ?? '');
$variety        = trim($input['variety']        ?? '');
$estimatedAge   = trim($input['estimatedAge']   ?? '');
$datePlanted    = trim($input['datePlanted']    ?? '');
$additionalInfo = trim($input['additionalInfo'] ?? '');

// ── Validate inputs ───────────────────────────────────────────────────────────
if (empty($submissionId)) {
    echo json_encode(['success' => false, 'message' => 'Submission ID is missing.']);
    exit;
}

// Sanitize Tree ID using the existing helper (removes illegal Firebase chars)
$treeId = sanitizeTreeId($rawTreeId);
if (empty($treeId)) {
    echo json_encode(['success' => false, 'message' => 'Tree ID is required and cannot be empty.']);
    exit;
}

$firebase = new Firebase();

// ── Check Tree ID uniqueness ──────────────────────────────────────────────────
$existing = $firebase->get('trees/' . $treeId);
if ($existing !== null && $existing !== false) {
    echo json_encode([
        'success' => false,
        'message' => "Tree ID \"$treeId\" already exists. Please choose a unique ID.",
    ]);
    exit;
}

// ── Fetch submission ──────────────────────────────────────────────────────────
$submission = $firebase->get('tree_submissions/' . $submissionId);
if (!is_array($submission)) {
    echo json_encode(['success' => false, 'message' => 'Submission not found.']);
    exit;
}

$currentStatus = strtolower($submission['status'] ?? 'pending');
if ($currentStatus !== 'pending') {
    echo json_encode(['success' => false, 'message' => 'This submission has already been processed.']);
    exit;
}

// ── Build tree record ─────────────────────────────────────────────────────────
$lat        = (float)($submission['latitude']  ?? 0);
$lng        = (float)($submission['longitude'] ?? 0);
$farmerId   = $submission['farmerId']   ?? '';
$farmerName = $submission['farmerName'] ?? 'Unknown Farmer';
$now        = date('M d, Y g:i A');
$adminUser  = $_SESSION['admin_username'] ?? 'Admin';

// Build QR payload — same format as add_tree.php / qr-generator.php
$qrPayload = json_encode([
    'tree_id'      => $treeId,
    'location'     => $treeName ?: ('Submitted by ' . $farmerName),
    'generated_at' => time() * 1000,
    'generated_by' => $adminUser,
]);
$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($qrPayload);

$treeData = [
    // Identifiers
    'tree_id'       => $treeId,

    // Admin-supplied details
    'name'          => $treeName       ?: $treeId,
    'variety'       => $variety,
    'estimatedAge'  => $estimatedAge,
    'datePlanted'   => $datePlanted,
    'additionalInfo'=> $additionalInfo,

    // Coordinates (dual keys so both lat/lng and latitude/longitude work)
    'latitude'      => $lat,
    'longitude'     => $lng,
    'lat'           => $lat,
    'lng'           => $lng,

    // Location label
    'location'      => $treeName ?: ('Submitted by ' . $farmerName),

    // QR
    'has_qr'        => true,
    'qrCodeUrl'     => $qrUrl,
    'qr_data'       => $qrPayload,

    // Provenance
    'farmerId'      => $farmerId,
    'farmerName'    => $farmerName,
    'submissionId'  => $submissionId,
    'approvedBy'    => $adminUser,
    'approvedAt'    => $now,
    'created_at'    => $now,
    'timestamp'     => time() * 1000,
    'generated_by'  => $adminUser,
];

// ── Write to /trees ───────────────────────────────────────────────────────────
$treeResult = $firebase->set('trees/' . $treeId, $treeData);
if ($treeResult === false) {
    echo json_encode(['success' => false, 'message' => 'Failed to save tree to Firebase. Check DB rules or connectivity.']);
    exit;
}

// ── Update submission status ──────────────────────────────────────────────────
$firebase->update('tree_submissions/' . $submissionId, [
    'status'     => 'approved',
    'treeId'     => $treeId,
    'approvedBy' => $adminUser,
    'approvedAt' => $now,
]);

// ── Write farmer notification ─────────────────────────────────────────────────
if (!empty($farmerId)) {
    $notifId = 'notif_approved_' . $submissionId;
    $firebase->set('notifications/' . $notifId, [
        'id'          => $notifId,
        'title'       => '🌿 Tree Location Approved!',
        'description' => "Your submitted tree location has been approved. Official Tree ID: $treeId.",
        'category'    => 'tree',
        'priority'    => 'high',
        'tree_id'     => $treeId,
        'farmerId'    => $farmerId,
        'target_url'  => 'trees.php',
        'timestamp'   => time() * 1000,
        'read'        => false,
        'created_at'  => $now,
        'treeId'      => $treeId,
        'qrCodeUrl'   => $qrUrl,
    ]);
}

// ── Reconcile system notifications (real-time accuracy) ───────────────────────
syncSystemNotifications($firebase);

// ── Success ───────────────────────────────────────────────────────────────────
echo json_encode([
    'success'  => true,
    'message'  => "Tree \"$treeId\" created successfully! QR code generated.",
    'treeId'   => $treeId,
    'qrUrl'    => $qrUrl,
    'treeData' => $treeData,
]);
exit;
?>
