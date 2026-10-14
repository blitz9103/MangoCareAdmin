<?php
// get_trees.php
require_once '../config/config.php';

// Instantiate Firebase
$firebase = new Firebase();

// 1. Fetch all trees from Firebase
$trees = $firebase->get('trees') ?? [];
$trees = is_array($trees) ? $trees : [];
unset($trees['0'], $trees[0]); // Remove placeholder array index 0

// Natural sorting for Tree IDs (tree1, tree2, ..., tree10)
ksort($trees, SORT_NATURAL | SORT_FLAG_CASE);

// 2. Fetch all User Scans to calculate disease count per tree
$users = $firebase->get('Users') ?? [];
$users = is_array($users) ? $users : [];

$diseaseCounts = [];
foreach ($users as $uid => $user) {
    if (!empty($user['scanResult']) && is_array($user['scanResult'])) {
        foreach ($user['scanResult'] as $scanId => $scan) {
            if (!empty($scan['tree_id'])) {
                $treeIdStr = strtolower((string) $scan['tree_id']);
                $diseaseCounts[$treeIdStr] = ($diseaseCounts[$treeIdStr] ?? 0) + 1;
            }
        }
    }
}

// 3. Format data to match the required JSON structure
$formattedTrees = [];

foreach ($trees as $treeId => $tree) {
    // Only include trees with valid coordinates
    if (!empty($tree['lat']) || !empty($tree['latitude'])) {
        $lat = $tree['lat'] ?? $tree['latitude'];
        $lng = $tree['lng'] ?? $tree['longitude'];

        $tidStr = strtolower((string) $treeId);
        $count = $diseaseCounts[$tidStr] ?? 0;

        $formattedTrees[$treeId] = [
            'latitude' => (float) $lat,
            'longitude' => (float) $lng,
            'diseaseCount' => $count,
            'location' => $tree['location'] ?? 'Unknown Location',
            'has_qr' => isset($tree['has_qr']) && $tree['has_qr'],
            'qr_data' => $tree['qr_data'] ?? null,
            'nextSpray' => getNextSpraySchedule($firebase, $treeId, $tree['spray_schedules'] ?? [])
        ];
    }
}

// 4. Output as JSON
header('Content-Type: application/json');
echo json_encode($formattedTrees);
exit;
?>