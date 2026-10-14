<?php
/**
 * reject_submission.php
 *
 * AJAX endpoint called when the Admin rejects a pending tree submission.
 *
 * What it does:
 *  1. Validates admin session.
 *  2. Updates /tree_submissions/{submissionId}/status = "rejected".
 *  3. Records the rejection reason, rejectedBy, rejectedAt.
 *  4. Writes a real-time notification for the farmer in /notifications.
 *  5. Returns JSON { success, message }.
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

$submissionId = trim($input['submissionId'] ?? '');
$reason       = trim($input['reason']       ?? '');

if (empty($submissionId)) {
    echo json_encode(['success' => false, 'message' => 'Submission ID is required.']);
    exit;
}

$firebase = new Firebase();

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

$farmerId  = $submission['farmerId'] ?? '';
$now       = date('M d, Y g:i A');
$adminUser = $_SESSION['admin_username'] ?? 'Admin';

// ── Update submission status ──────────────────────────────────────────────────
$firebase->update('tree_submissions/' . $submissionId, [
    'status'          => 'rejected',
    'rejectionReason' => $reason ?: 'No reason provided.',
    'rejectedBy'      => $adminUser,
    'rejectedAt'      => $now,
]);

// ── Write farmer notification ─────────────────────────────────────────────────
if (!empty($farmerId)) {
    $notifId = 'notif_rejected_' . $submissionId;
    $desc    = $reason
        ? "Your tree location submission was rejected. Reason: $reason"
        : "Your tree location submission was rejected by the admin.";

    $firebase->set('notifications/' . $notifId, [
        'id'          => $notifId,
        'title'       => '❌ Tree Submission Rejected',
        'description' => $desc,
        'category'    => 'tree',
        'priority'    => 'medium',
        'farmerId'    => $farmerId,
        'target_url'  => '',
        'timestamp'   => time() * 1000,
        'read'        => false,
        'created_at'  => $now,
    ]);
}

// ── Reconcile system notifications (real-time accuracy) ───────────────────────
syncSystemNotifications($firebase);

echo json_encode([
    'success' => true,
    'message' => 'Submission rejected successfully.',
]);
exit;
?>
