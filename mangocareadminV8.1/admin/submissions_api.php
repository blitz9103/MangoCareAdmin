<?php
/**
 * submissions_api.php
 * Returns all PENDING tree submissions as JSON.
 * Called by tree_map.php (initial marker load) and by the sidebar badge counter.
 */
require_once '../config/config.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

$firebase = new Firebase();

$submissions = $firebase->get('tree_submissions') ?? [];
$submissions = is_array($submissions) ? $submissions : [];

$result = [];

foreach ($submissions as $submissionId => $submission) {
    $status = strtolower($submission['status'] ?? 'pending');

    // Only surface pending submissions
    if ($status !== 'pending') {
        continue;
    }

    $farmerId   = $submission['farmerId']   ?? '';
    $farmerName = $submission['farmerName'] ?? '';
    $farmerEmail= $submission['farmerEmail']?? '';

    // If the farmer name was not embedded at submission time, look it up from /Users
    if ($farmerId && empty($farmerName)) {
        $user = $firebase->get('Users/' . $farmerId);
        if (is_array($user)) {
            $farmerName  = $user['name']     ?? $user['username'] ?? 'Unknown Farmer';
            $farmerEmail = $user['email']    ?? '';
        }
    }

    if (empty($farmerName)) {
        $farmerName = 'Unknown Farmer';
    }

    $result[$submissionId] = [
        'submissionId' => $submissionId,
        'farmerId'     => $farmerId,
        'farmerName'   => $farmerName,
        'farmerEmail'  => $farmerEmail,
        'latitude'     => (float)($submission['latitude']  ?? 0),
        'longitude'    => (float)($submission['longitude'] ?? 0),
        'notes'        => $submission['notes'] ?? '',
        'status'       => 'pending',
        'submittedAt'  => $submission['submittedAt'] ?? null,
    ];
}

echo json_encode($result);
exit;
?>
