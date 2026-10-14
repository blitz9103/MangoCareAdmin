<?php
require_once '../config/config.php';
requireLogin();

$firebase = new Firebase();
processAutomaticSchedules($firebase);

/* =====================================================
   GET ALL SCANS FROM Users → scanResult
===================================================== */

$users = $firebase->get('Users') ?? [];
$users = is_array($users) ? $users : [];

$scans = [];
$diseaseFrequency = [];
$scansByMarker = ['Admin' => 0, 'Farmer' => 0]; // Grouped by who marked it

foreach ($users as $uid => $user) {
    if (!is_array($user))
        continue;

    $userScans = [];
    if (!empty($user['scanResult']) && is_array($user['scanResult'])) {
        foreach ($user['scanResult'] as $sId => $sData) {
            if (is_array($sData))
                $userScans[$sId] = $sData;
        }
    }

    foreach ($userScans as $scanId => $scan) {

        // Attach extra info
        $scan['scan_id'] = $scanId;
        $scan['user_name'] = $user['name'] ?? 'Unknown';
        $scan['user_email'] = $user['email'] ?? 'N/A';

        // Normalize tree id field (Firebase uses "treeID")
        $scan['tree_id'] = $scan['tree_id'] ?? $scan['treeID'] ?? $scan['treeId'] ?? '';

        // Normalize disease field
        $disease = '';
        if (!empty($scan['disease'])) {
            $disease = trim($scan['disease']);
        } elseif (!empty($scan['diseaseName'])) {
            $disease = trim($scan['diseaseName']);
        } elseif (!empty($scan['disease_final'])) {
            $disease = trim($scan['disease_final']);
        }

        $scan['final_disease'] = $disease;

        $scans[] = $scan;

        /* ===== Disease Count ===== */
        if (!empty($disease)) {
            $key = strtolower($disease);
            $diseaseFrequency[$key] = ($diseaseFrequency[$key] ?? 0) + 1;
        }

        /* ===== Marker Type: Admin vs Farmer ===== */
        // Determine if scan was marked by admin or a farmer/app user
        $markedBy = strtolower($scan['marked_by'] ?? $scan['scannedBy'] ?? $scan['recorded_by'] ?? '');
        if (str_contains($markedBy, 'admin') || $markedBy === 'admin') {
            $scansByMarker['Admin']++;
        } else {
            // Any scan from the mobile app / farmer user
            $scansByMarker['Farmer']++;
        }
    }
}


/* =====================================================
   TOTAL CALCULATIONS
===================================================== */

$totalScans = count($scans);
$diseaseTypes = array_keys($diseaseFrequency);

// Sort scans newest-first so Recent Scans card shows the latest 5 correctly
usort($scans, function ($a, $b) {
    $ta = isset($a['timestamp']) ? (int) $a['timestamp'] : 0;
    $tb = isset($b['timestamp']) ? (int) $b['timestamp'] : 0;
    return $tb - $ta;
});

/* =====================================================
   TREES
===================================================== */

$trees = $firebase->get('trees') ?? [];
$trees = is_array($trees) ? $trees : [];

// Remove tree ID 0 properly
unset($trees['0'], $trees[0]);

// Natural sorting for Tree IDs (tree1, tree2, ..., tree10)
ksort($trees, SORT_NATURAL | SORT_FLAG_CASE);

$totalTrees = count($trees);

/* =====================================================
   SCHEDULES
===================================================== */

$schedules = [];
$todayDateStr = date('Y-m-d');
$todayScheduledTrees = [];

foreach ($trees as $treeId => $tree) {
    $treeSchedules = $tree['spray_schedules'] ?? [];
    if (is_array($treeSchedules)) {
        foreach ($treeSchedules as $schedule) {
            $schedules[] = $schedule;

            // Determine schedule date
            $sDate = $schedule['spray_date'] ?? $schedule['date'] ?? '';
            if (empty($sDate) && !empty($schedule['timestamp'])) {
                $ts = $schedule['timestamp'] > 9999999999 ? $schedule['timestamp'] / 1000 : $schedule['timestamp'];
                $sDate = date('Y-m-d', (int) $ts);
            }

            $status = strtolower($schedule['status'] ?? 'pending');
            if ($sDate === $todayDateStr && $status === 'pending') {
                $todayScheduledTrees[$treeId] = true;
            }
        }
    }
}

$todayScheduledTreesCount = count($todayScheduledTrees);
$totalSchedules = count($schedules);
$completedSchedules = 0;
$pendingSchedules = 0;
$overdueSchedules = 0;
$todaySchedules = 0;
$tomorrowSchedules = 0;

$tomorrowDateStr = date('Y-m-d', strtotime('+1 day'));

foreach ($schedules as $schedule) {
    $sStatus = strtolower($schedule['status'] ?? 'pending');
    $sDate = $schedule['spray_date'] ?? $schedule['date'] ?? '';
    if (empty($sDate) && !empty($schedule['timestamp'])) {
        $ts = $schedule['timestamp'] > 9999999999 ? $schedule['timestamp'] / 1000 : $schedule['timestamp'];
        $sDate = date('Y-m-d', (int) $ts);
    }

    if ($sStatus === 'completed') {
        $completedSchedules++;
    } elseif ($sStatus === 'overdue') {
        $overdueSchedules++;    // date passed without being marked as sprayed
    } elseif ($sStatus === 'pending') {
        if ($sDate < $todayDateStr) {
            $overdueSchedules++;    // past due, never completed
        } elseif ($sDate === $todayDateStr) {
            $todaySchedules++;      // due today
        } elseif ($sDate === $tomorrowDateStr) {
            $tomorrowSchedules++;   // due tomorrow
        } else {
            $pendingSchedules++;    // future pending (> tomorrow)
        }
    }
}

$scheduleCompletionRate =
    $totalSchedules > 0
    ? ($completedSchedules / $totalSchedules) * 100
    : 0;

/* =====================================================
   SPRAY HISTORY
===================================================== */

$sprayHistory = $firebase->get('spray_history') ?? [];
$sprayHistory = is_array($sprayHistory) ? $sprayHistory : [];

/* Count total completed sprays */
$totalSprays = 0;

/* Initialize time analytics for sprays */
$spraysLast24Hours = 0;
$spraysLast7Days = 0;
$spraysLast30Days = 0;

$now = time();
$oneDayAgo = $now - 86400;
$sevenDaysAgo = $now - 604800;
$thirtyDaysAgo = $now - 2592000;

foreach ($sprayHistory as $spray) {

    if (($spray['status'] ?? '') === 'completed') {

        $totalSprays++;

        if (!empty($spray['timestamp'])) {

            // Support milliseconds and seconds
            $sprayTime = $spray['timestamp'] > 9999999999
                ? $spray['timestamp'] / 1000
                : $spray['timestamp'];

            if ($sprayTime >= $thirtyDaysAgo) {
                $spraysLast30Days++;

                if ($sprayTime >= $sevenDaysAgo) {
                    $spraysLast7Days++;

                    if ($sprayTime >= $oneDayAgo) {
                        $spraysLast24Hours++;
                    }
                }
            }
        }
    }
}

/* =====================================================
   TIME ANALYTICS
===================================================== */

$now = time();
$oneDayAgo = $now - 86400;
$sevenDaysAgo = $now - 604800;
$thirtyDaysAgo = $now - 2592000;

$scansLast24Hours = 0;
$scansLast7Days = 0;
$scansLast30Days = 0;

$scansByDay = [];

foreach ($scans as $scan) {

    if (!empty($scan['timestamp'])) {

        // Support both milliseconds & seconds
        $scanTime = $scan['timestamp'] > 9999999999
            ? $scan['timestamp'] / 1000
            : $scan['timestamp'];

        $dayKey = date('Y-m-d', $scanTime);
        $scansByDay[$dayKey] =
            ($scansByDay[$dayKey] ?? 0) + 1;

        if ($scanTime >= $thirtyDaysAgo) {
            $scansLast30Days++;

            if ($scanTime >= $sevenDaysAgo) {
                $scansLast7Days++;

                if ($scanTime >= $oneDayAgo) {
                    $scansLast24Hours++;
                }
            }
        }
    }
}

/* =====================================================
   LAST 7 DAYS CHART DATA
===================================================== */

$last7DaysLabels = [];
$last7DaysScans = [];
$last7DaysSprays = [];

for ($i = 6; $i >= 0; $i--) {

    $date = date('Y-m-d', strtotime("-$i days"));
    $label = date('M d', strtotime("-$i days"));

    $last7DaysLabels[] = $label;
    $last7DaysScans[] = $scansByDay[$date] ?? 0;
    $last7DaysSprays[] = 0; // You can add spray chart later
}

/* =====================================================
   SYSTEM METRICS
===================================================== */

$treesWithQR = count(array_filter($trees, function ($tree) {
    return !empty($tree['has_qr']);
}));

$treesWithSchedules = 0;
foreach ($trees as $treeId => $tree) {
    $treeSchedules = $tree['spray_schedules'] ?? [];
    if (is_array($treeSchedules) && count($treeSchedules) > 0) {
        $treesWithSchedules++;
    }
}

$scheduleCoverageRate =
    $totalTrees > 0
    ? ($treesWithSchedules / $totalTrees) * 100
    : 0;

$avgScansPerTree =
    $totalTrees > 0
    ? round($totalScans / $totalTrees, 2)
    : 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=3">
    <link rel="stylesheet" href="../assets/css/design-system.css?v=3">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Google Maps API -->
    <script
        src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDrbSIXqYwXA00EgMbd-cRUKsHGCXv42Rg&libraries=geometry"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.75rem;
        }

        .chart-card {
            background: #fff;
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-card);
            border: 1px solid var(--border);
        }

        .chart-card h2 {
            font-size: 1.1rem;
            color: var(--primary);
            margin-bottom: 1rem;
            padding-bottom: .5rem;
            border-bottom: 2px solid var(--accent);
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
        }

        .metric-item {
            text-align: center;
            padding: 1.5rem 1rem;
            background: var(--primary-light);
            border-radius: 12px;
            border: 1px solid var(--accent-light);
            transition: transform .2s;
        }

        .metric-item:hover {
            transform: translateY(-3px);
        }

        .metric-value {
            font-size: 2.25rem;
            font-weight: 800;
            color: var(--primary);
            line-height: 1.2;
            margin-bottom: .5rem;
        }

        .metric-label {
            color: var(--text-muted);
            font-size: .85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .progress-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .progress-item {
            padding: 1.25rem;
            background: var(--primary-light);
            border-radius: 12px;
            border: 1px solid var(--accent-light);
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: .75rem;
        }

        .progress-header h3 {
            font-size: 1rem;
            margin: 0;
            color: var(--text-heading);
            font-weight: 600;
        }

        .progress-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary);
        }

        .progress-bar {
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: .5rem;
        }

        .progress-fill {
            height: 100%;
            border-radius: 4px;
            transition: width .5s ease;
        }

        .progress-fill.success {
            background: #22c55e;
        }

        .progress-fill.info {
            background: #3b82f6;
        }

        .progress-fill.warning {
            background: #f59e0b;
        }

        .progress-footer {
            font-size: .85rem;
            color: var(--text-muted);
        }

        .stat-info small {
            color: var(--accent);
            font-size: .82rem;
            display: block;
            font-weight: 500;
        }

        .stat-link {
            margin-top: .5rem;
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            min-width: 320px;
            padding: 1rem 1.25rem;
            background: #fff;
            border-left: 4px solid var(--accent);
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 71, 55, .15);
            animation: slideIn .3s ease-out;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0
            }

            to {
                transform: translateX(0);
                opacity: 1
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1
            }

            to {
                transform: translateX(100%);
                opacity: 0
            }
        }

        /* ── Schedule Modal Premium UI Design ── */
        #scheduleModal.modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100vw;
            height: 100vh;
            z-index: 999999;
            background: rgba(10, 30, 25, 0.65);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        #scheduleModal.modal.show {
            display: flex !important;
        }

        #scheduleModal .modal-content {
            background: #ffffff;
            border-radius: 20px;
            width: min(540px, 95%);
            padding: 0;
            overflow: hidden;
            box-shadow: 0 25px 60px -15px rgba(0, 71, 55, 0.35), 0 0 0 1px rgba(0, 71, 55, 0.08);
            position: relative;
            animation: modalPopIn 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes modalPopIn {
            0% {
                transform: scale(0.92) translateY(18px);
                opacity: 0;
            }

            100% {
                transform: scale(1) translateY(0);
                opacity: 1;
            }
        }

        /* Modal Header */
        #scheduleModal .sched-modal-header {
            background: linear-gradient(135deg, #004737 0%, #005A47 60%, #006B55 100%);
            padding: 1.5rem 1.75rem;
            color: #ffffff;
            position: relative;
            display: flex;
            align-items: center;
            gap: 1.15rem;
        }

        #scheduleModal .sched-modal-icon {
            width: 46px;
            height: 46px;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: #70B9A1;
            flex-shrink: 0;
            box-shadow: inset 0 0 10px rgba(255, 255, 255, 0.1);
        }

        #scheduleModal .sched-modal-title-box h2 {
            font-size: 1.2rem;
            font-weight: 700;
            color: #ffffff;
            margin: 0 0 0.2rem 0;
            border: none;
            padding: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        #scheduleModal .sched-modal-subtitle {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.8);
            margin: 0;
        }

        #scheduleModal .sched-modal-close {
            position: absolute;
            top: 1.15rem;
            right: 1.25rem;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.2s ease;
        }

        #scheduleModal .sched-modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        /* Modal Body & Form */
        #scheduleModal .sched-modal-body {
            padding: 1.75rem;
        }

        #scheduleModal .sched-tree-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: #EAF6F2;
            color: #004737;
            border: 1px solid #B8E2D5;
            padding: 0.35rem 0.85rem;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 700;
            margin-bottom: 1.25rem;
        }

        /* Form Grid */
        #scheduleModal .sched-form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        @media (max-width: 480px) {
            #scheduleModal .sched-form-row {
                grid-template-columns: 1fr;
            }
        }

        #scheduleModal .sched-form-group {
            margin-bottom: 1.15rem;
        }

        #scheduleModal .sched-form-group label {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.82rem;
            font-weight: 600;
            color: #2D3748;
            margin-bottom: 0.4rem;
        }

        #scheduleModal .sched-form-group label i {
            color: #006B55;
        }

        #scheduleModal .sched-input {
            width: 100%;
            padding: 0.7rem 0.9rem;
            border: 1.5px solid #E2E8F0;
            border-radius: 10px;
            font-size: 0.88rem;
            font-family: 'Inter', sans-serif;
            color: #1A202C;
            background: #F8FAFC;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }

        #scheduleModal .sched-input:focus {
            outline: none;
            border-color: #006B55;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(0, 107, 85, 0.12);
        }

        #scheduleModal textarea.sched-input {
            resize: vertical;
            min-height: 80px;
        }

        /* Quick Presets & Treatment Pills */
        #scheduleModal .sched-presets {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.15rem;
            flex-wrap: wrap;
        }

        #scheduleModal .sched-preset-btn {
            background: #F1F5F9;
            border: 1px solid #CBD5E1;
            color: #475569;
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        #scheduleModal .sched-preset-btn:hover,
        #scheduleModal .sched-preset-btn.active {
            background: #004737;
            border-color: #004737;
            color: #ffffff;
        }

        #scheduleModal .sched-type-pills {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
            margin-bottom: 1.15rem;
        }

        #scheduleModal .sched-type-pill {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #334155;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            transition: all 0.2s ease;
        }

        #scheduleModal .sched-type-pill:hover {
            border-color: #006B55;
            background: #EAF6F2;
            color: #004737;
        }

        #scheduleModal .sched-type-pill.active {
            border-color: #004737;
            background: #004737;
            color: #ffffff;
        }

        /* Live Summary Box */
        #scheduleModal .sched-live-summary {
            background: #F0FDF4;
            border: 1px solid #BBF7D0;
            color: #166534;
            padding: 0.75rem 1rem;
            border-radius: 10px;
            font-size: 0.83rem;
            margin-top: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Footer Actions */
        #scheduleModal .sched-modal-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.25rem;
        }

        #scheduleModal .btn-save-sched {
            flex: 2;
            background: linear-gradient(135deg, #005343 0%, #007A60 100%);
            color: #ffffff;
            border: none;
            padding: 0.85rem 1.25rem;
            border-radius: 10px;
            font-size: 0.92rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 4px 14px rgba(0, 83, 67, 0.3);
            transition: all 0.2s ease;
        }

        #scheduleModal .btn-save-sched:hover {
            background: linear-gradient(135deg, #004737 0%, #006B55 100%);
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(0, 83, 67, 0.4);
        }

        #scheduleModal .btn-cancel-sched {
            flex: 1;
            background: #F8FAFC;
            color: #64748B;
            border: 1.5px solid #E2E8F0;
            padding: 0.85rem 1.25rem;
            border-radius: 10px;
            font-size: 0.92rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        /* Color Presets for Boundary Modal */
        .color-preset {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            display: inline-block;
            border: 2px solid #fff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .color-preset:hover,
        .color-preset.active {
            transform: scale(1.15);
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.3);
        }

        #boundarySaveModal.modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100vw;
            height: 100vh;
            z-index: 999999;
            background: rgba(10, 30, 25, 0.65);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        #boundarySaveModal.modal.show {
            display: flex !important;
        }

        #boundarySaveModal .modal-content {
            background: #ffffff;
            border-radius: 20px;
            width: min(520px, 95%);
            padding: 0;
            overflow: hidden;
            box-shadow: 0 25px 60px -15px rgba(0, 71, 55, 0.35), 0 0 0 1px rgba(0, 71, 55, 0.08);
            position: relative;
            animation: modalPopIn 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
    </style>
</head>

<body>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/sidebar.php'; ?>

    <div class="permanent-spray-panel" id="sprayPanel" onclick="window.toggleSprayPanel(event)">
        <h3 title="Toggle Spray Panel" id="sprayPanelHeader" style="cursor: pointer;"
            onclick="window.toggleSprayPanel(event)">
            <span>
                <i class="fas fa-calendar-alt"></i>
                <span class="panel-title-text" onclick="window.toggleSprayPanel(event)">Spray Schedule Monitor</span>
            </span>
            <i class="fas fa-chevron-down toggle-icon" id="sprayPanelToggleIcon"
                onclick="window.toggleSprayPanel(event)"></i>
        </h3>
        <div id="permanentSprayContent">
            Loading schedules...
        </div>
    </div>

    <script>
        // showScheduleModal / closeScheduleModal are defined once in the main dashboard
        // script block (see window.showScheduleModal / window.closeScheduleModal below).
        // Removed the duplicate copies here to avoid version skew.

        window.toggleSprayPanel = function (e) {
            if (e) {
                e.stopPropagation();
                e.preventDefault();
            }

            const panel = document.getElementById('sprayPanel');
            const icon = document.getElementById('sprayPanelToggleIcon');

            if (!panel) return;

            // If event target is the panel itself, only toggle if currently collapsed
            if (e && e.currentTarget === panel && !panel.classList.contains('collapsed')) {
                return;
            }

            panel.classList.toggle('collapsed');
            const isCollapsed = panel.classList.contains('collapsed');

            if (icon) {
                if (isCollapsed) {
                    icon.classList.remove('fa-chevron-down');
                    icon.classList.add('fa-chevron-up');
                } else {
                    icon.classList.remove('fa-chevron-up');
                    icon.classList.add('fa-chevron-down');
                }
            }

            try {
                localStorage.setItem('sprayPanelCollapsed', isCollapsed ? 'true' : 'false');
            } catch (err) { }
        };

        // Restore state immediately to prevent jumping
        (function () {
            try {
                if (localStorage.getItem('sprayPanelCollapsed') === 'true') {
                    const panel = document.getElementById('sprayPanel');
                    const icon = document.getElementById('sprayPanelToggleIcon');
                    if (panel) panel.classList.add('collapsed');
                    if (icon) {
                        icon.classList.remove('fa-chevron-down');
                        icon.classList.add('fa-chevron-up');
                    }
                }
            } catch (err) { }
        })();
    </script>

    <main class="main-content">
        <!-- PAGE BANNER -->
        <div class="page-banner">
            <div class="banner-left">
                <div class="banner-icon"><i class="fas fa-tachometer-alt"></i></div>
                <div class="banner-text">
                    <h1>Dashboard</h1>
                    <p><?= htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?> &bull; <?= date('l, F j, Y'); ?>
                    </p>
                </div>
            </div>
            <div class="banner-badge"><i class="fas fa-tree"></i> <?= $totalTrees; ?> Trees &bull; <i
                    class="fas fa-microscope"></i> <?= $totalScans; ?> Scans &bull; <i class="fas fa-calendar-day"></i>
                <?= $todayScheduledTreesCount; ?> Scheduled Today
            </div>
        </div>

        <!-- DASHBOARD MAP -->
        <div class="card" style="margin-bottom: 1.75rem; border-radius: 12px; overflow: hidden; padding: 0;">
            <div id="dashboardMap" style="width: 100%; height: 600px; background: #e0e0e0;">
                <div style="display:flex; justify-content:center; align-items:center; height:100%; color: #666;">
                    <p><i class="fas fa-spinner fa-spin"></i> Loading Map...</p>
                </div>
            </div>
        </div>

        <!-- Key Performance Indicators (KPIs) -->
        <div class="data-card">
            <div class="data-card-header">
                <span class="data-card-title"><i class="fas fa-tachometer-alt"></i> Key Performance Indicators</span>
                <span class="data-card-count">Real-time metrics</span>
            </div>
            <div class="card-body">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon-wrapper">
                            <i class="fas fa-tree"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $totalTrees; ?></h3>
                            <p>Registered Mango Trees</p>
                            <small><i class="fas fa-qrcode"></i> <?php echo $treesWithQR; ?> with QR codes</small>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon-wrapper"
                            style="background: linear-gradient(135deg, #1565c0 0%, #1e88e5 100%); color: #fff;">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $todayScheduledTreesCount; ?> <span
                                    style="font-size: 0.9rem; font-weight: 500; color: var(--text-muted);">Trees
                                    Today</span></h3>
                            <p><i class="fas fa-calendar-day" style="color: #1e88e5; margin-right: 4px;"></i> Scheduled
                                Today</p>
                            <small><i class="fas fa-list"></i> <?php echo $totalSchedules; ?> total schedules
                                (<?php echo $pendingSchedules; ?> pending)</small>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon-wrapper">
                            <i class="fas fa-tint"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $totalSprays; ?></h3>
                            <p>Completed Spray Actions</p>
                            <small><i class="far fa-clock"></i> <?php echo $spraysLast24Hours; ?> in last 24h</small>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon-wrapper">
                            <i class="fas fa-microscope"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $totalScans; ?></h3>
                            <p>Total Disease Scans</p>
                            <small><i class="fas fa-virus"></i> All recorded detections</small>
                            <div class="stat-link">
                                <a href="scanhistory-users.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye"></i> View All
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Analytics Objectives & Progress -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-chart-pie" style="margin-right: 8px; color: var(--secondary-color);"></i>Analytics
                    & Progress</h2>
                <span class="badge badge-success"><i class="fas fa-check-circle"></i> Goal tracking</span>
            </div>
            <div class="card-body">
                <div class="progress-grid">
                    <!-- Schedule Completion Rate -->
                    <div class="progress-item">
                        <div class="progress-header">
                            <h3><i class="fas fa-check-circle"
                                    style="color: var(--success-color); margin-right: 5px;"></i>Schedule Completion Rate
                            </h3>
                            <span
                                class="progress-value"><?php echo number_format($scheduleCompletionRate, 1); ?>%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill success"
                                style="width: <?php echo min($scheduleCompletionRate, 100); ?>%;"></div>
                        </div>
                        <div class="progress-footer">
                            <i class="fas fa-chart-line"></i> <?php echo $completedSchedules; ?> of
                            <?php echo $totalSchedules; ?> schedules completed
                        </div>
                    </div>

                    <!-- Schedule Coverage Rate -->
                    <div class="progress-item">
                        <div class="progress-header">
                            <h3><i class="fas fa-umbrella"
                                    style="color: var(--info-color); margin-right: 5px;"></i>Schedule Coverage</h3>
                            <span class="progress-value"><?php echo number_format($scheduleCoverageRate, 1); ?>%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill info"
                                style="width: <?php echo min($scheduleCoverageRate, 100); ?>%;"></div>
                        </div>
                        <div class="progress-footer">
                            <i class="fas fa-tree"></i> <?php echo $treesWithSchedules; ?> of <?php echo $totalTrees; ?>
                            trees have schedules
                        </div>
                    </div>

                    <!-- QR Code Adoption -->
                    <div class="progress-item">
                        <div class="progress-header">
                            <h3><i class="fas fa-qrcode" style="color: var(--warning-color); margin-right: 5px;"></i>QR
                                Code Adoption</h3>
                            <span
                                class="progress-value"><?php echo $totalTrees > 0 ? number_format(($treesWithQR / $totalTrees) * 100, 1) : 0; ?>%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill warning"
                                style="width: <?php echo $totalTrees > 0 ? min(($treesWithQR / $totalTrees) * 100, 100) : 0; ?>%;">
                            </div>
                        </div>
                        <div class="progress-footer">
                            <i class="fas fa-check"></i> <?php echo $treesWithQR; ?> of <?php echo $totalTrees; ?> trees
                            have QR codes
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity Trends -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-chart-line" style="margin-right: 8px; color: var(--secondary-color);"></i>Activity
                    Trends (Last 7 Days)</h2>
                <span class="badge badge-info"><i class="far fa-calendar-alt"></i> Daily monitoring</span>
            </div>
            <div class="card-body">
                <canvas id="activityTrendChart" style="max-height: 300px;"></canvas>
            </div>
        </div>

        <!-- Activity Metrics -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-chart-bar" style="margin-right: 8px; color: var(--secondary-color);"></i>Activity
                    Metrics</h2>
                <span class="badge badge-success"><i class="far fa-clock"></i> Time-based analysis</span>
            </div>
            <div class="card-body">
                <div class="metrics-grid">
                    <div class="metric-item">
                        <div class="metric-value"><?php echo $scansLast24Hours; ?></div>
                        <div class="metric-label"><i class="fas fa-microscope" style="margin-right: 4px;"></i> Scans
                            (24h)</div>
                    </div>
                    <div class="metric-item">
                        <div class="metric-value"><?php echo $scansLast7Days; ?></div>
                        <div class="metric-label"><i class="fas fa-microscope"></i> Scans (7d)</div>
                    </div>
                    <div class="metric-item">
                        <div class="metric-value"><?php echo $scansLast30Days; ?></div>
                        <div class="metric-label"><i class="fas fa-microscope"></i> Scans (30d)</div>
                    </div>
                    <div class="metric-item">
                        <div class="metric-value"><?php echo $spraysLast24Hours; ?></div>
                        <div class="metric-label"><i class="fas fa-tint"></i> Sprays (24h)</div>
                    </div>
                    <div class="metric-item">
                        <div class="metric-value"><?php echo $spraysLast7Days; ?></div>
                        <div class="metric-label"><i class="fas fa-tint"></i> Sprays (7d)</div>
                    </div>
                    <div class="metric-item">
                        <div class="metric-value"><?php echo $spraysLast30Days; ?></div>
                        <div class="metric-label"><i class="fas fa-tint"></i> Sprays (30d)</div>
                    </div>
                </div>
            </div>
        </div>



        <!-- Charts Section -->
        <div class="charts-grid">
            <div class="chart-card">
                <h2><i class="fas fa-chart-pie" style="margin-right: 8px; color: var(--secondary-color);"></i>Disease
                    Distribution</h2>
                <canvas id="diseaseChart"></canvas>
            </div>

            <!-- Spray Schedule Status Chart -->
            <div class="chart-card" id="sprayStatusChartCard">
                <h2>
                    <i class="fas fa-calendar-check" style="margin-right: 8px; color: var(--secondary-color);"></i>
                    Spray Schedule Status
                </h2>
                <canvas id="sprayStatusChart"></canvas>
                <div id="sprayStatusEmpty"
                    style="display:none; text-align:center; padding:40px; color:var(--text-secondary);">
                    <i class="fas fa-calendar-times"
                        style="font-size:48px; color:var(--secondary-color); margin-bottom:10px;"></i>
                    <br>No spray schedules available.
                </div>
            </div>
        </div>

        <!-- Recent Scans -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-history" style="margin-right: 8px; color: var(--secondary-color);"></i>Recent Scans
                </h2>
                <a href="scans.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-eye"></i> View All
                </a>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th><i class="far fa-calendar-alt"></i> Date</th>
                            <th><i class="fas fa-virus"></i> Disease</th>
                            <th><i class="fas fa-map-marker-alt"></i> Location</th>
                            <th><i class="fas fa-tree"></i> Tree ID</th>
                            <th><i class="fas fa-info-circle"></i> Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $recentScans = array_slice($scans, 0, 10); // $scans already sorted newest-first
                        if (empty($recentScans)):
                            ?>
                            <tr>
                                <td colspan="5" class="text-center">
                                    <i class="fas fa-info-circle"></i> No scans recorded yet
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentScans as $scan):
                                $treeId = $scan['tree_id'] ?? '';
                                $loc = $scan['location'] ?? '';
                                if (empty($loc) && $treeId !== '') {
                                    foreach ($trees as $tId => $tData) {
                                        if (strtolower((string) $tId) === strtolower($treeId)) {
                                            if (!empty($tData['location'])) {
                                                $loc = $tData['location'];
                                            }
                                            break;
                                        }
                                    }
                                }
                                ?>
                                <tr>
                                    <td><i class="far fa-clock" style="color: var(--secondary-color);"></i>
                                        <?php echo isset($scan['timestamp']) ? date('M d, Y H:i', $scan['timestamp'] / 1000) : 'N/A'; ?>
                                    </td>
                                    <td><span class="badge badge-warning"><i class="fas fa-virus"></i>
                                            <?php echo htmlspecialchars(!empty($scan['final_disease']) ? $scan['final_disease'] : ($scan['disease'] ?? $scan['diseaseName'] ?? $scan['disease_final'] ?? 'Unknown')); ?></span>
                                    </td>
                                    <td><i class="fas fa-map-marker-alt" style="color: var(--secondary-color);"></i>
                                        <?php echo htmlspecialchars($loc ?: 'N/A'); ?></td>
                                    <td><i class="fas fa-tag"></i> <?php echo htmlspecialchars($treeId ?: 'N/A'); ?>
                                    </td>
                                    <td><span class="badge badge-success"><i class="fas fa-check-circle"></i> Recorded</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        // Global chart instances for real-time updates
        let activityTrendChart = null;
        let diseaseChart = null;
        let locationChart = null;

        // Initialize charts with initial data
        const activityLabels = <?php echo json_encode($last7DaysLabels); ?>;
        const activityScans = <?php echo json_encode($last7DaysScans); ?>;
        const activitySprays = <?php echo json_encode($last7DaysSprays); ?>;

        const diseaseData = <?php echo json_encode($diseaseFrequency); ?>;

        // Initial spray schedule status counts from PHP
        const initialSprayStatus = {
            pending: <?php echo $pendingSchedules; ?>,
            today: <?php echo $todaySchedules; ?>,
            tomorrow: <?php echo $tomorrowSchedules; ?>,
            completed: <?php echo $completedSchedules; ?>,
            overdue: <?php echo $overdueSchedules; ?>
        };

        // Activity Trend Chart
        const activityCtx = document.getElementById('activityTrendChart');
        if (activityCtx) {
            activityTrendChart = new Chart(activityCtx, {
                type: 'line',
                data: {
                    labels: activityLabels,
                    datasets: [
                        {
                            label: 'Scans',
                            data: activityScans,
                            borderColor: '#004737',
                            backgroundColor: 'rgba(0, 71, 55, 0.1)',
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: '#004737'
                        },
                        {
                            label: 'Completed Sprays',
                            data: activitySprays,
                            borderColor: '#70B9A1',
                            backgroundColor: 'rgba(112, 185, 161, 0.1)',
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: '#70B9A1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                color: '#6c757d'
                            },
                            grid: {
                                color: 'rgba(112, 185, 161, 0.1)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
                    }
                }
            });
        }

        // Disease Distribution Chart (with normalized names)
        // Normalize disease names from initial PHP data
        const normalizedDiseaseData = {};
        Object.keys(diseaseData).forEach(disease => {
            const normalized = disease.trim()
                .split(' ')
                .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
                .join(' ');
            normalizedDiseaseData[normalized] = (normalizedDiseaseData[normalized] || 0) + diseaseData[disease];
        });

        const diseaseLabels = Object.keys(normalizedDiseaseData);
        const diseaseValues = Object.values(normalizedDiseaseData);
        const diseaseCtx = document.getElementById('diseaseChart');

        if (diseaseLabels.length > 0 && diseaseCtx) {
            diseaseChart = new Chart(diseaseCtx, {
                type: 'doughnut',
                data: {
                    labels: diseaseLabels,
                    datasets: [{
                        data: diseaseValues,
                        backgroundColor: [
                            '#004737',
                            '#70B9A1',
                            '#FFB347',
                            '#FF6384',
                            '#36A2EB',
                            '#9966FF'
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        } else if (diseaseCtx) {
            diseaseCtx.parentElement.innerHTML = '<p style="text-align: center; padding: 40px; color: var(--text-secondary);"><i class="fas fa-chart-pie" style="font-size: 48px; color: var(--secondary-color); margin-bottom: 10px;"></i><br>No disease data available yet. Scans will appear here in real-time.</p>';
        }

        // ── Spray Schedule Status Chart ─────────────────────────────────────
        const _sprayStatusLabels = ['📅 Pending', '🟣 Today', '🟠 Tomorrow', '✅ Completed', '⚫ Overdue'];
        const _sprayStatusKeys = ['pending', 'today', 'tomorrow', 'completed', 'overdue'];
        const _sprayStatusColors = [
            'rgba(33,  150, 243, 0.85)',  // Pending  – blue
            'rgba(156,  39, 176, 0.85)',  // Today    – purple
            'rgba(255, 152,   0, 0.85)',  // Tomorrow – orange
            'rgba(76,  175,  80, 0.85)',  // Completed– green
            'rgba(84,  110, 122, 0.85)'  // Overdue  – dark gray
        ];
        const _sprayStatusBorders = ['#1565c0', '#6a1b9a', '#e65100', '#2e7d32', '#263238'];

        const sprayStatusCtx = document.getElementById('sprayStatusChart');
        let sprayStatusChart = null;

        if (sprayStatusCtx) {
            sprayStatusChart = new Chart(sprayStatusCtx, {
                type: 'bar',
                data: {
                    labels: _sprayStatusLabels,
                    datasets: [{
                        label: 'Schedules',
                        data: _sprayStatusKeys.map(k => initialSprayStatus[k] || 0),
                        backgroundColor: _sprayStatusColors,
                        borderColor: _sprayStatusBorders,
                        borderWidth: 2,
                        borderRadius: 10,
                        borderSkipped: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    onClick(event, elements) {
                        if (!elements.length) return;
                        const idx = elements[0].index;
                        const key = _sprayStatusKeys[idx];
                        const label = _sprayStatusLabels[idx];
                        window._showSprayStatusModal(key, label);
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                title: ctx => ctx[0].label.replace(/^\S+\s/, ''),
                                label(ctx) {
                                    const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                    const pct = total > 0 ? ((ctx.parsed.y / total) * 100).toFixed(1) : 0;
                                    return `${ctx.parsed.y} schedules (${pct}%)`;
                                }
                            }
                        },
                        // Show count labels above bars
                        datalabels: undefined  // handled inline below via afterDraw
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1, color: '#6c757d', precision: 0 },
                            grid: { color: 'rgba(112,185,161,0.1)' }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { font: { weight: '600' }, color: '#444' }
                        }
                    }
                },
                // Inline plugin: render count on top of each bar
                plugins: [{
                    id: 'sprayBarLabels',
                    afterDatasetsDraw(chart) {
                        const { ctx, data, chartArea: { top } } = chart;
                        ctx.save();
                        chart.getDatasetMeta(0).data.forEach((bar, i) => {
                            const val = data.datasets[0].data[i];
                            if (val === 0) return;
                            ctx.fillStyle = '#333';
                            ctx.font = 'bold 12px Inter, sans-serif';
                            ctx.textAlign = 'center';
                            ctx.textBaseline = 'bottom';
                            ctx.fillText(val, bar.x, bar.y - 2);
                        });
                        ctx.restore();
                    }
                }]
            });
        }

        // Export chart instances for real-time updates
        window.dashboardCharts = {
            activityTrend: activityTrendChart,
            disease: diseaseChart,
            sprayStatus: sprayStatusChart
        };
        // ── Spray Schedule Status – Bar Click Modal ───────────────────────────
        window._showSprayStatusModal = function (statusKey, statusLabel) {
            const _todayStr = new Date().toISOString().slice(0, 10);
            const _tomorrowObj = new Date(); _tomorrowObj.setDate(_tomorrowObj.getDate() + 1);
            const _tomorrowStr = _tomorrowObj.toISOString().slice(0, 10);

            const matching = (window.globalSchedules || []).filter(s => {
                const st = (s.status || 'pending').toLowerCase();
                let rawDate = s.spray_date || s.date || '';
                if (!rawDate && s.timestamp) {
                    const ts = Number(s.timestamp) > 9999999999
                        ? Number(s.timestamp) / 1000 : Number(s.timestamp);
                    rawDate = new Date(ts * 1000).toISOString().slice(0, 10);
                }
                if (statusKey === 'completed') return st === 'completed';
                if (statusKey === 'overdue') return st === 'overdue' || (st === 'pending' && rawDate < _todayStr);
                if (statusKey === 'today') return st === 'pending' && rawDate === _todayStr;
                if (statusKey === 'tomorrow') return st === 'pending' && rawDate === _tomorrowStr;
                if (statusKey === 'pending') return st === 'pending' && rawDate > _tomorrowStr;
                return false;
            });

            const icon = { pending: '📅', today: '🟣', tomorrow: '🟠', completed: '✅', overdue: '⚫' }[statusKey] || '📋';
            const cleanLabel = statusLabel.replace(/^\S+\s/, '');

            const rows = matching.length > 0
                ? matching.map(s => {
                    const dateStr = s.spray_date || s.date || 'N/A';
                    const time = s.spray_time ? ' ' + s.spray_time : '';
                    const tree = s.treeId || 'N/A';
                    const notes = s.notes || '—';
                    const stLabel = (s.status || 'Pending');
                    const _stLow = stLabel.toLowerCase();
                    const badgeCls = _stLow === 'completed' ? 'badge-success'
                        : (_stLow === 'overdue' || _stLow === 'cancelled' ? 'badge-danger'
                            : 'badge-warning');
                    return `<tr>
                        <td><span class="badge badge-tree"><i class="fas fa-tree"></i> ${tree}</span></td>
                        <td>${dateStr}${time}</td>
                        <td><span class="badge ${badgeCls}">${stLabel}</span></td>
                        <td>${notes}</td>
                    </tr>`;
                }).join('')
                : `<tr><td colspan="4" style="text-align:center;padding:20px;color:#888;">No records found.</td></tr>`;

            document.getElementById('sprayModalTitle').textContent = icon + ' ' + cleanLabel + ' Schedules';
            document.getElementById('sprayModalCount').textContent = matching.length + ' record(s)';
            document.getElementById('sprayModalBody').innerHTML = `
                <table class="table">
                    <thead><tr>
                        <th><i class="fas fa-tree"></i> Tree ID</th>
                        <th><i class="fas fa-calendar-alt"></i> Date / Time</th>
                        <th><i class="fas fa-tag"></i> Status</th>
                        <th><i class="fas fa-sticky-note"></i> Notes</th>
                    </tr></thead>
                    <tbody>${rows}</tbody>
                </table>`;
            document.getElementById('sprayStatusModal').style.display = 'flex';
        };

        document.addEventListener('DOMContentLoaded', () => {
            const modal = document.getElementById('sprayStatusModal');
            const closeBtn = document.getElementById('sprayModalClose');
            if (closeBtn) closeBtn.addEventListener('click', () => { modal.style.display = 'none'; });
            if (modal) modal.addEventListener('click', e => { if (e.target === modal) modal.style.display = 'none'; });
            document.addEventListener('keydown', e => { if (e.key === 'Escape') { modal.style.display = 'none'; } });
        });
    </script>

    <!-- Spray Schedule Status – Click Detail Modal -->
    <div id="sprayStatusModal" role="dialog" aria-modal="true" aria-labelledby="sprayModalTitle" style="display:none; position:fixed; inset:0; z-index:9999;
                background:rgba(0,0,0,0.52); align-items:center; justify-content:center; padding:1rem;">
        <div style="background:#fff; border-radius:14px; width:100%; max-width:740px;
                    max-height:85vh; overflow:hidden; display:flex; flex-direction:column;
                    box-shadow:0 20px 60px rgba(0,0,0,0.35);">
            <div style="display:flex; align-items:center; justify-content:space-between;
                        padding:16px 22px; border-bottom:1px solid #e8f5e9;
                        background:linear-gradient(135deg,#004737 0%,#006b4f 100%);
                        color:#fff; border-radius:14px 14px 0 0;">
                <div>
                    <h3 id="sprayModalTitle" style="margin:0;font-size:16px;font-weight:700;letter-spacing:.3px;"></h3>
                    <small id="sprayModalCount" style="opacity:.75;font-size:12px;"></small>
                </div>
                <button id="sprayModalClose" aria-label="Close" style="background:rgba(255,255,255,0.15);border:none;color:#fff;
                               font-size:20px;cursor:pointer;width:32px;height:32px;
                               border-radius:50%;display:flex;align-items:center;justify-content:center;
                               transition:background .2s;" onmouseover="this.style.background='rgba(255,255,255,0.3)'"
                    onmouseout="this.style.background='rgba(255,255,255,0.15)'">&times;</button>
            </div>
            <div id="sprayModalBody" style="overflow-y:auto;padding:16px 20px;flex:1;"></div>
            <div style="padding:12px 20px;border-top:1px solid #eee;text-align:right;
                        background:#fafafa;border-radius:0 0 14px 14px;">
                <a href="schedules.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-external-link-alt"></i> View All Schedules
                </a>
                <button onclick="document.getElementById('sprayStatusModal').style.display='none'"
                    class="btn btn-outline btn-sm" style="margin-left:8px;">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>

    <!-- Firebase Realtime Analytics - Complete Real-Time Updates -->
    <script type="module">
        import { initializeApp } from "https://www.gstatic.com/firebasejs/10.12.2/firebase-app.js";
        import { getDatabase, ref, onValue } from "https://www.gstatic.com/firebasejs/10.12.2/firebase-database.js";

        const firebaseConfig = {
            apiKey: "<?php echo addslashes(FIREBASE_API_KEY); ?>",
            authDomain: "<?php echo addslashes(FIREBASE_AUTH_DOMAIN); ?>",
            databaseURL: "<?php echo addslashes(FIREBASE_DATABASE_URL); ?>",
            projectId: "<?php echo addslashes(FIREBASE_PROJECT_ID); ?>",
            storageBucket: "<?php echo addslashes(FIREBASE_STORAGE_BUCKET); ?>",
            messagingSenderId: "<?php echo addslashes(FIREBASE_MESSAGING_SENDER_ID); ?>",
            appId: "<?php echo addslashes(FIREBASE_APP_ID); ?>"
        };

        const app = initializeApp(firebaseConfig);
        const db = getDatabase(app);

        // Helper function to normalize disease names (handle case variations)
        function normalizeDiseaseName(diseaseName) {
            if (!diseaseName || typeof diseaseName !== 'string') return 'Unknown';

            // Trim and capitalize first letter of each word
            const normalized = diseaseName.trim()
                .split(' ')
                .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
                .join(' ');

            // Handle common disease name variations
            const diseaseMap = {
                'Anthracnose': 'Anthracnose',
                'anthracnose': 'Anthracnose',
                'ANTHRACNOSE': 'Anthracnose',
                'Mango Scab': 'Mango Scab',
                'mango scab': 'Mango Scab',
                'MANGO SCAB': 'Mango Scab',
                'Mango scab': 'Mango Scab',
                'Powdery Mildew': 'Powdery Mildew',
                'powdery mildew': 'Powdery Mildew',
                'POWDERY MILDEW': 'Powdery Mildew',
                'Bacterial Spot': 'Bacterial Spot',
                'bacterial spot': 'Bacterial Spot',
                'BACTERIAL SPOT': 'Bacterial Spot',
                'Black Spot': 'Black Spot',
                'black spot': 'Black Spot',
                'BLACK SPOT': 'Black Spot'
            };

            return diseaseMap[normalized] || normalized;
        }

        // Helper function to calculate time-based metrics
        function calculateTimeMetrics(data, timestampKey = 'timestamp') {
            const now = Date.now();
            const oneDayAgo = now - (24 * 60 * 60 * 1000);
            const sevenDaysAgo = now - (7 * 24 * 60 * 60 * 1000);
            const thirtyDaysAgo = now - (30 * 24 * 60 * 60 * 1000);

            let last24h = 0, last7d = 0, last30d = 0;
            const byDay = {};

            Object.values(data).forEach(item => {
                const ts = item[timestampKey];
                if (!ts) return;

                const dayKey = new Date(ts).toISOString().split('T')[0];
                byDay[dayKey] = (byDay[dayKey] || 0) + 1;

                if (ts >= thirtyDaysAgo) {
                    last30d++;
                    if (ts >= sevenDaysAgo) {
                        last7d++;
                        if (ts >= oneDayAgo) {
                            last24h++;
                        }
                    }
                }
            });

            return { last24h, last7d, last30d, byDay };
        }

        function updatePermanentSprayPanel() {

            const container = document.getElementById('permanentSprayContent');
            if (!container) return;

            if (!globalSchedules || globalSchedules.length === 0) {
                container.innerHTML = `
                <div class="spray-item">
                    <strong>No pending spray schedules</strong>
                    <small>No schedules found in database</small>
                </div>
            `;
                return;
            }

            // Remove completed + overdue schedules (overdue sprays are handled via the overdue count/alert)
            const pendingSchedules = globalSchedules.filter(s =>
                (s.status || '').toLowerCase() !== 'completed' &&
                (s.status || '').toLowerCase() !== 'overdue'
            );

            if (pendingSchedules.length === 0) {
                container.innerHTML = `
                <div class="spray-item">
                    <strong>No pending spray schedules</strong>
                    <small>All schedules are completed ✅</small>
                </div>
            `;
                return;
            }

            // Sort by date
            pendingSchedules.sort((a, b) =>
                (a.scheduled_date || a.timestamp || 0) -
                (b.scheduled_date || b.timestamp || 0)
            );

            let html = '';

            pendingSchedules.forEach(schedule => {

                let scheduleTimestamp =
                    schedule.scheduled_date ||
                    schedule.schedule_date ||      // 🔥 ADD
                    schedule.spray_date ||         // 🔥 ADD
                    schedule.date ||               // 🔥 ADD
                    schedule.timestamp ||
                    null;

                let dateText = 'No schedule date set';
                if (scheduleTimestamp) {

                    if (typeof scheduleTimestamp === 'number') {

                        // If timestamp is in seconds → convert to milliseconds
                        if (scheduleTimestamp < 1000000000000) {
                            scheduleTimestamp = scheduleTimestamp * 1000;
                        }

                        dateText = new Date(scheduleTimestamp).toLocaleString(
                            "en-PH",
                            {
                                year: "numeric",
                                month: "numeric",
                                day: "numeric",
                                hour: "numeric",
                                minute: "2-digit",
                                hour12: true
                            }
                        );
                    }
                    else if (typeof scheduleTimestamp === 'string') {

                        const parsedDate = new Date(scheduleTimestamp);

                        if (!isNaN(parsedDate)) {
                            dateText = parsedDate.toLocaleString(
                                "en-PH",
                                {
                                    year: "numeric",
                                    month: "numeric",
                                    day: "numeric",
                                    hour: "numeric",
                                    minute: "2-digit",
                                    hour12: true
                                }
                            );
                        }
                    }
                }

                let itemTreeId = schedule.treeId || schedule.tree_id || schedule.id || '';
                let safeTreeId = String(itemTreeId).replace(/'/g, "\\'");
                html += `
                <div class="spray-item" style="cursor: pointer;" onclick="event.stopPropagation(); window._suppressMapClick=true; if (typeof window.focusTreeOnMap==='function'){window.focusTreeOnMap('${safeTreeId}');} window.showScheduleModal('${safeTreeId}');">
                    <strong>Tree ID: ${itemTreeId || 'Unknown'}</strong>
                    <small>Status: Pending ⏳</small>
                    <small>Schedule Date: ${dateText}</small>
                </div>
            `;
            });

            container.innerHTML = html;
        }

        // Process disease data from scans with normalization
        function processDiseaseData(scans) {
            const diseaseFrequency = {};
            const diseaseDetails = {};

            Object.values(scans).forEach(scan => {

                // 🔥 FIX: Support all possible disease fields
                let rawDisease = '';
                if (typeof scan.disease === 'string' && scan.disease.trim() !== '') {
                    rawDisease = scan.disease;
                }
                else if (typeof scan.diseaseName === 'string' && scan.diseaseName.trim() !== '') {
                    rawDisease = scan.diseaseName;
                }
                else if (typeof scan.disease_final === 'string' && scan.disease_final.trim() !== '') {
                    rawDisease = scan.disease_final;
                }
                else {
                    rawDisease = 'Unknown';
                }

                if (!rawDisease) return;

                const normalizedDisease = normalizeDiseaseName(rawDisease);

                diseaseFrequency[normalizedDisease] =
                    (diseaseFrequency[normalizedDisease] || 0) + 1;

                if (!diseaseDetails[normalizedDisease]) {
                    diseaseDetails[normalizedDisease] = {
                        count: 0,
                        firstDetected: scan.timestamp,
                        lastDetected: scan.timestamp,
                        locations: new Set(),
                        trees: new Set(),
                        avgConfidence: 0,
                        confidenceSum: 0
                    };
                }

                diseaseDetails[normalizedDisease].count++;

                if (scan.timestamp) {
                    let ts = scan.timestamp;

                    // normalize milliseconds / seconds
                    if (ts < 9999999999) {
                        ts = ts * 1000;
                    }

                    diseaseDetails[normalizedDisease].lastDetected = Math.max(
                        diseaseDetails[normalizedDisease].lastDetected || 0,
                        ts
                    );
                }

                if (scan.location) {
                    diseaseDetails[normalizedDisease].locations.add(scan.location);
                }

                if (scan.tree_id) {
                    diseaseDetails[normalizedDisease].trees.add(scan.tree_id);
                }

                if (scan.confidence) {
                    diseaseDetails[normalizedDisease].confidenceSum += scan.confidence;
                    diseaseDetails[normalizedDisease].avgConfidence =
                        diseaseDetails[normalizedDisease].confidenceSum /
                        diseaseDetails[normalizedDisease].count;
                }
            });

            return { diseaseFrequency, diseaseDetails };
        }

        // Global data storage (attached to window for cross-script access)
        window.globalScans = {};
        window.globalTrees = {};
        window.globalSprays = {};
        window.globalSchedules = [];
        let previousDiseaseCount = 0;
        let previousScanCount = 0;

        // Update all dashboard components
        function updateDashboard() {
            // Process trees
            let validTrees = 0;
            let treesWithQR = 0;

            Object.keys(globalTrees).forEach(treeId => {
                if (treeId !== '0' && treeId !== 0 && String(treeId) !== '0') {
                    validTrees++;
                    if (globalTrees[treeId]?.has_qr) {
                        treesWithQR++;
                    }
                }
            });

            // Process scans
            const scanArray = Object.values(globalScans);
            const totalScans = scanArray.length;
            const scanMetrics = calculateTimeMetrics(globalScans);

            // Check for new scans
            if (totalScans > previousScanCount && previousScanCount > 0) {
                // New scan detected - analytics will update automatically
                console.log(`New scan detected! Total scans: ${totalScans}`);
            }
            previousScanCount = totalScans;

            // Process disease data with normalization
            const { diseaseFrequency, diseaseDetails } = processDiseaseData(globalScans);

            // Process spray schedule status counts from globalSchedules
            const _todayStr = new Date().toISOString().slice(0, 10);
            const _tomorrow = new Date(); _tomorrow.setDate(_tomorrow.getDate() + 1);
            const _tomorrowStr = _tomorrow.toISOString().slice(0, 10);

            const sprayStatusCounts = { pending: 0, today: 0, tomorrow: 0, completed: 0, overdue: 0 };
            window.globalSchedules.forEach(s => {
                const st = (s.status || 'pending').toLowerCase();
                let rawDate = s.spray_date || s.date || '';
                if (!rawDate && s.timestamp) {
                    const ts = Number(s.timestamp) > 9999999999 ? Number(s.timestamp) / 1000 : Number(s.timestamp);
                    rawDate = new Date(ts * 1000).toISOString().slice(0, 10);
                }
                if (st === 'completed') {
                    sprayStatusCounts.completed++;
                } else if (st === 'overdue') {
                    sprayStatusCounts.overdue++;
                } else if (st === 'pending') {
                    if (rawDate < _todayStr) sprayStatusCounts.overdue++;
                    else if (rawDate === _todayStr) sprayStatusCounts.today++;
                    else if (rawDate === _tomorrowStr) sprayStatusCounts.tomorrow++;
                    else sprayStatusCounts.pending++;
                }
            });
            // scansByLocation no longer used for the chart — kept as variable for legacy compatibility
            const scansByLocation = sprayStatusCounts;

            // Process sprays
            const completedSprays = Object.values(globalSprays).filter(s => s.status === 'completed');
            const sprayMetrics = calculateTimeMetrics(completedSprays);

            // Update KPI Cards (using normalized disease count)
            const uniqueDiseaseTypes = Object.keys(diseaseFrequency).length;
            updateKPICards(
                validTrees,
                treesWithQR,
                globalSchedules.length,
                completedSprays.length,
                sprayMetrics.last24h
            );

            // Update Activity Metrics
            updateActivityMetrics(scanMetrics, sprayMetrics);

            // Update Charts (including disease summary)
            updateCharts(diseaseFrequency, scansByLocation, scanMetrics, sprayMetrics, diseaseDetails);

            // Update Progress Bars
            updateProgressBars(validTrees, treesWithQR, globalSchedules, completedSprays);

            // Update Recent Scans Table
            updateRecentScansTable(scanArray);
        }

        // Update KPI Cards
        function updateKPICards(totalTrees, treesWithQR, totalSchedules, totalSprays, sprays24h) {
            const cards = document.querySelectorAll('.stats-grid .stat-card');
            cards.forEach(card => {
                const iconWrapper = card.querySelector('.stat-icon-wrapper i');
                const h3 = card.querySelector('h3');
                const small = card.querySelector('small');

                if (!iconWrapper || !h3) return;

                if (iconWrapper.classList.contains('fa-tree')) {
                    h3.textContent = totalTrees;
                    if (small) small.innerHTML = '<i class="fas fa-qrcode"></i> ' + treesWithQR + ' with QR codes';
                }
                else if (iconWrapper.classList.contains('fa-calendar-alt')) {
                    h3.textContent = totalSchedules;
                    if (small) small.innerHTML = '<i class="fas fa-hourglass-half"></i> Total schedules';
                }
                else if (iconWrapper.classList.contains('fa-tint')) {
                    h3.textContent = totalSprays;
                    if (small) small.innerHTML = '<i class="far fa-clock"></i> ' + sprays24h + ' in last 24h';
                }
            });
        }

        // Update Activity Metrics
        function updateActivityMetrics(scanMetrics, sprayMetrics) {
            const metrics = document.querySelectorAll('.metric-item');
            if (metrics.length >= 6) {
                metrics[0].querySelector('.metric-value').textContent = scanMetrics.last24h;
                metrics[1].querySelector('.metric-value').textContent = scanMetrics.last7d;
                metrics[2].querySelector('.metric-value').textContent = scanMetrics.last30d;
                metrics[3].querySelector('.metric-value').textContent = sprayMetrics.last24h;
                metrics[4].querySelector('.metric-value').textContent = sprayMetrics.last7d;
                metrics[5].querySelector('.metric-value').textContent = sprayMetrics.last30d;
            }
        }

        // Show notification for new disease detection
        function showDiseaseDetectionNotification(diseaseName, scanCount) {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = 'notification';
            notification.innerHTML = `
                <strong><i class="fas fa-virus" style="color: var(--secondary-color);"></i> New Disease Detected!</strong>
                <span><i class="fas fa-disease"></i> ${diseaseName} - ${scanCount} detection${scanCount > 1 ? 's' : ''}</span>
            `;

            document.body.appendChild(notification);

            // Remove after 5 seconds
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease-out';
                setTimeout(() => notification.remove(), 300);
            }, 5000);
        }

        // Update Disease Summary Table
        function updateDiseaseSummary(diseaseDetails) {
            const tbody = document.getElementById('diseaseSummaryBody');
            if (!tbody) return;

            const diseases = Object.keys(diseaseDetails).sort((a, b) =>
                diseaseDetails[b].count - diseaseDetails[a].count
            );

            if (diseases.length === 0) {
                tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center">
                        <i class="fas fa-info-circle"></i> No disease detections yet.
                    </td>
                </tr>
            `;
                return;
            }

            tbody.innerHTML = diseases.map(disease => {

                const details = diseaseDetails[disease];

                /* ===== FORCE LOCATION TO PANABO CITY ===== */
                const locationText = "Panabo City";

                /* ===== FORCE AFFECTED TREES TO N/A ===== */
                const affectedTrees = "N/A";

                /* ===== FIX CONFIDENCE DISPLAY ===== */
                let avgConfidence = "N/A";
                if (details.avgConfidence && details.avgConfidence > 0) {
                    avgConfidence = details.avgConfidence.toFixed(1) + "%";
                }

                /* ===== FIX LAST DETECTED DATE ===== */
                let lastDetected = "N/A";
                if (details.lastDetected) {
                    lastDetected = new Date(details.lastDetected).toLocaleString(
                        "en-US",
                        {
                            month: "short",
                            day: "numeric",
                            year: "numeric",
                            hour: "2-digit",
                            minute: "2-digit"
                        }
                    );
                }

                return `
                <tr>
                    <td>
                        <strong>
                            <i class="fas fa-virus" style="color: var(--secondary-color);"></i>
                            ${disease}
                        </strong>
                    </td>

                    <td>
                        <span class="badge badge-warning">
                            <i class="fas fa-hashtag"></i> ${details.count}
                        </span>
                    </td>

                    <td>
                        <i class="fas fa-tree" style="color: var(--primary-color);"></i>
                        ${affectedTrees}
                    </td>

                    <td>
                        <i class="fas fa-map-marker-alt" style="color: var(--primary-color);"></i>
                        ${locationText}
                    </td>

                    <td>
                        <i class="fas fa-percent" style="color: var(--primary-color);"></i>
                        ${avgConfidence}
                    </td>

                    <td>
                        <i class="far fa-clock" style="color: var(--primary-color);"></i>
                        ${lastDetected}
                    </td>
                </tr>
            `;

            }).join('');
        }

        // Update Charts
        function updateCharts(diseaseFrequency, scansByLocation, scanMetrics, sprayMetrics, diseaseDetails) {
            // Update Disease Chart with normalized names
            if (window.dashboardCharts?.disease) {
                const diseaseKeys = Object.keys(diseaseFrequency);
                if (diseaseKeys.length > 0) {
                    window.dashboardCharts.disease.data.labels = diseaseKeys;
                    window.dashboardCharts.disease.data.datasets[0].data = Object.values(diseaseFrequency);
                    window.dashboardCharts.disease.update();
                } else {
                    // Show empty state
                    const chartContainer = document.getElementById('diseaseChart').parentElement;
                    if (!chartContainer.querySelector('p')) {
                        chartContainer.innerHTML = '<p style="text-align: center; padding: 40px; color: var(--text-secondary);"><i class="fas fa-chart-pie" style="font-size: 48px; color: var(--secondary-color); margin-bottom: 10px;"></i><br>No disease data available yet. Scans will appear here in real-time.</p>';
                    }
                }
            }

            // ── Update Spray Schedule Status Chart ──────────────────────────
            if (window.dashboardCharts?.sprayStatus) {
                const _keys = ['pending', 'today', 'tomorrow', 'completed', 'overdue'];
                const newData = _keys.map(k => scansByLocation[k] || 0);
                window.dashboardCharts.sprayStatus.data.datasets[0].data = newData;
                window.dashboardCharts.sprayStatus.update();
                const total = newData.reduce((a, b) => a + b, 0);
                const emptyEl = document.getElementById('sprayStatusEmpty');
                const canvasEl = document.getElementById('sprayStatusChart');
                if (emptyEl && canvasEl) {
                    emptyEl.style.display = total === 0 ? 'block' : 'none';
                    canvasEl.style.display = total === 0 ? 'none' : 'block';
                }
            }

            // Update Activity Trend Chart
            if (window.dashboardCharts?.activityTrend) {
                const labels = [];
                const scansData = [];
                const spraysData = [];

                for (let i = 6; i >= 0; i--) {
                    const date = new Date();
                    date.setDate(date.getDate() - i);
                    const dayKey = date.toISOString().split('T')[0];
                    const dayLabel = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });

                    labels.push(dayLabel);
                    scansData.push(scanMetrics.byDay[dayKey] || 0);
                    spraysData.push(sprayMetrics.byDay[dayKey] || 0);
                }

                window.dashboardCharts.activityTrend.data.labels = labels;
                window.dashboardCharts.activityTrend.data.datasets[0].data = scansData;
                window.dashboardCharts.activityTrend.data.datasets[1].data = spraysData;
                window.dashboardCharts.activityTrend.update();
            }

            // Update Disease Summary Table
            if (diseaseDetails) {
                updateDiseaseSummary(diseaseDetails);
            }
        }

        // Update Progress Bars
        function updateProgressBars(totalTrees, treesWithQR, schedules, completedSprays) {
            const totalSchedules = schedules.length;
            const completedSchedules = schedules.filter(s => s.status === 'completed').length;
            const completionRate = totalSchedules > 0 ? (completedSchedules / totalSchedules) * 100 : 0;

            // Count unique trees that have schedules
            const treesWithSchedulesSet = new Set();
            // We'll approximate: if we have schedules, assume at least some trees have them
            // For accurate count, we'd need to track which tree each schedule belongs to
            // For now, use a reasonable estimate: trees with schedules ≈ min(schedules.length, totalTrees)
            const treesWithSchedules = Math.min(Math.ceil(totalSchedules / 2), totalTrees);
            const coverageRate = totalTrees > 0 ? (treesWithSchedules / totalTrees) * 100 : 0;
            const qrRate = totalTrees > 0 ? (treesWithQR / totalTrees) * 100 : 0;

            // Update progress bars
            const progressFills = document.querySelectorAll('.progress-fill');
            if (progressFills.length >= 3) {
                progressFills[0].style.width = Math.min(completionRate, 100) + '%';
                progressFills[1].style.width = Math.min(coverageRate, 100) + '%';
                progressFills[2].style.width = Math.min(qrRate, 100) + '%';
            }

            // Update percentages
            const progressValues = document.querySelectorAll('.progress-value');
            if (progressValues.length >= 3) {
                progressValues[0].textContent = completionRate.toFixed(1) + '%';
                progressValues[1].textContent = coverageRate.toFixed(1) + '%';
                progressValues[2].textContent = qrRate.toFixed(1) + '%';
            }

            // Update progress bar text
            const progressFooters = document.querySelectorAll('.progress-footer');
            if (progressFooters.length >= 3) {
                progressFooters[0].innerHTML = '<i class="fas fa-chart-line"></i> ' + completedSchedules + ' of ' + totalSchedules + ' schedules completed';
                progressFooters[1].innerHTML = '<i class="fas fa-tree"></i> ' + treesWithSchedules + ' of ' + totalTrees + ' trees have schedules';
                progressFooters[2].innerHTML = '<i class="fas fa-check"></i> ' + treesWithQR + ' of ' + totalTrees + ' trees have QR codes';
            }
        }

        function updateSchedulesMetrics(schedules) {
            const totalSchedules = schedules.length;
            const completedSchedules = schedules.filter(s => s.status === 'completed').length;

            // Update schedule card
            const scheduleCards = document.querySelectorAll('.stats-grid .stat-card');
            scheduleCards.forEach(card => {
                const icon = card.querySelector('.stat-icon-wrapper i');
                if (icon && icon.classList.contains('fa-calendar-alt')) {
                    const h3 = card.querySelector('h3');
                    const small = card.querySelector('small');
                    if (h3) h3.textContent = totalSchedules;
                    if (small) small.innerHTML = '<i class="fas fa-hourglass-half"></i> ' + (totalSchedules - completedSchedules) + ' pending';
                }
            });
        }

        // Update Recent Scans Table
        function updateRecentScansTable(scans) {
            const tbody = document.querySelector('.table tbody');
            if (!tbody) return;

            // Sort by timestamp (newest first)
            const sortedScans = scans.sort((a, b) => (b.timestamp || 0) - (a.timestamp || 0)).slice(0, 5);

            if (sortedScans.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center"><i class="fas fa-info-circle"></i> No scans recorded yet</td></tr>';
                return;
            }

            tbody.innerHTML = sortedScans.map(scan => {
                const date = scan.timestamp ? new Date(scan.timestamp).toLocaleString('en-US', {
                    month: 'short', day: 'numeric', year: 'numeric',
                    hour: '2-digit', minute: '2-digit'
                }) : 'N/A';

                // Resolve Disease Name
                let diseaseName = 'Unknown';
                if (typeof scan.disease === 'string' && scan.disease.trim() !== '') {
                    diseaseName = scan.disease;
                } else if (typeof scan.diseaseName === 'string' && scan.diseaseName.trim() !== '') {
                    diseaseName = scan.diseaseName;
                } else if (typeof scan.disease_final === 'string' && scan.disease_final.trim() !== '') {
                    diseaseName = scan.disease_final;
                }

                // Resolve Tree ID
                const treeId = scan.tree_id || scan.treeId || scan.treeID || 'Unknown';

                // Resolve Location
                let location = scan.location || scan.tree_location || scan.treeLocation || '';
                if (!location || location === 'N/A') {
                    if (treeId !== 'Unknown' && window.globalTrees) {
                        const matchedKey = Object.keys(window.globalTrees).find(
                            k => k.toLowerCase() === treeId.toLowerCase()
                        );
                        if (matchedKey) {
                            const tree = window.globalTrees[matchedKey];
                            location = tree.location || (tree.lat && tree.lng ? `${parseFloat(tree.lat).toFixed(4)}, ${parseFloat(tree.lng).toFixed(4)}` : '');
                        }
                    }
                }
                if (!location) {
                    location = 'N/A';
                }

                return `
                    <tr>
                        <td><i class="far fa-clock" style="color: var(--secondary-color);"></i> ${date}</td>
                        <td><span class="badge badge-warning"><i class="fas fa-virus"></i> ${diseaseName}</span></td>
                        <td><i class="fas fa-map-marker-alt" style="color: var(--secondary-color);"></i> ${location}</td>
                        <td><i class="fas fa-tag"></i> ${treeId}</td>
                        <td><span class="badge badge-success"><i class="fas fa-check-circle"></i> Recorded</span></td>
                    </tr>
                `;
            }).join('');
        }

        // Set up real-time listeners
        const usersRef = ref(db, 'Users');
        const treesRef = ref(db, 'trees');
        const sprayHistoryRef = ref(db, 'spray_history');

        // Listen to scans changes
        onValue(usersRef, (snapshot) => {
            const usersData = snapshot.val() || {};
            const extractedScans = {};

            Object.keys(usersData).forEach(uid => {
                const user = usersData[uid];

                if (user.scanResult) {
                    Object.keys(user.scanResult).forEach(scanId => {
                        extractedScans[scanId] = user.scanResult[scanId];
                    });
                }
            });

            window.globalScans = extractedScans;
            updateDashboard();
            if (typeof window.updateMapMarkers === 'function') {
                window.updateMapMarkers();
            }
        });

        // Function to load schedules for all trees
        function loadAllSchedules() {
            globalSchedules = [];
            const validTreeIds = Object.keys(globalTrees).filter(tId => tId !== '0' && tId !== 0 && String(tId) !== '0');

            if (validTreeIds.length === 0) {
                updateDashboard();
                return;
            }

            let loadedCount = 0;
            globalSchedules = []; // 🔥 reset before loading

            validTreeIds.forEach(treeId => {

                const schedulesRef = ref(db, `trees/${treeId}/spray_schedules`);

                onValue(schedulesRef, (scheduleSnapshot) => {

                    const treeSchedules = scheduleSnapshot.val() || {};

                    Object.entries(treeSchedules).forEach(([scheduleId, schedule]) => {

                        globalSchedules.push({
                            ...schedule,
                            treeId: treeId,
                            scheduleId: scheduleId
                        });

                    });

                    loadedCount++;

                    if (loadedCount === validTreeIds.length) {
                        updateDashboard();
                        updatePermanentSprayPanel();
                        if (typeof window.updateMapMarkers === 'function') {
                            window.updateMapMarkers();
                        }
                    }

                }, { onlyOnce: true });

            });
        }

        // Listen to trees changes
        onValue(treesRef, (snapshot) => {
            window.globalTrees = snapshot.val() || {};
            loadAllSchedules();
            if (typeof window.updateMapMarkers === 'function') {
                window.updateMapMarkers();
            }
            // Refresh boundary stats when tree data changes
            if (typeof farmBoundaryPolygon !== 'undefined' && farmBoundaryPolygon) {
                updateBoundaryStats(farmBoundaryPolygon);
            }
        });

        // Listen to spray history changes
        onValue(sprayHistoryRef, (snapshot) => {
            globalSprays = snapshot.val() || {};
            updateDashboard();

        });

        // Also listen to individual tree schedule changes for real-time updates
        onValue(treesRef, (snapshot) => {
            const trees = snapshot.val() || {};
            Object.keys(trees).forEach(treeId => {
                if (treeId !== '0' && treeId !== 0 && String(treeId) !== '0') {
                    const schedulesRef = ref(db, `trees/${treeId}/spray_schedules`);
                    onValue(schedulesRef, () => {
                        loadAllSchedules();
                    });
                }
            });
        });
    </script>



    <!-- Map Initialization -->
    <script>
        let googleMap;
        let markers = [];
        let farmBoundaryPolygon = null;
        // Manual boundary draw state (replaces deprecated google.maps.drawing.DrawingManager)
        let drawModeActive = false;
        let draftPolyline = null;
        let draftMarkers = [];
        let draftCoords = [];
        let markMarker = null;
        let markInfoWindow = null;
        // markingMode toggles whether map clicks place a temporary marker to get coordinates
        window.markingMode = false;

        // ─── Farm Boundary: Save to Firebase ───────────────────────────────────
        function saveFarmBoundaryToFirebase(coords) {
            fetch('../config/save_boundary.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ boundary: coords })
            }).then(r => r.json()).then(d => {
                if (d.success) showMapToast('Farm boundary saved!', 'success');
                else showMapToast('Failed to save boundary.', 'error');
            }).catch(() => showMapToast('Network error saving boundary.', 'error'));
        }

        // ─── Farm Boundary: Update area stats overlay ───────────────────────────
        function updateBoundaryStats(polygon) {
            if (!polygon || !window.globalTrees) return;
            let treesInside = 0, diseaseInside = 0;
            Object.entries(window.globalTrees).forEach(([treeId, tree]) => {
                if (treeId === '0') return;
                const lat = parseFloat(tree.lat || tree.latitude);
                const lng = parseFloat(tree.lng || tree.longitude);
                if (isNaN(lat) || isNaN(lng)) return;
                const point = new google.maps.LatLng(lat, lng);
                if (google.maps.geometry.poly.containsLocation(point, polygon)) {
                    treesInside++;
                    if (window.globalScans) {
                        Object.values(window.globalScans).forEach(scan => {
                            if (String(scan.tree_id).toLowerCase() === String(treeId).toLowerCase()) {
                                diseaseInside++;
                            }
                        });
                    }
                }
            });
            const statsEl = document.getElementById('boundaryStatsBox');
            if (statsEl) {
                document.getElementById('bsTrees').textContent = treesInside;
                document.getElementById('bsDisease').textContent = diseaseInside;
                statsEl.style.display = 'flex';
            }
        }

        // ─── Toast Notification ────────────────────────────────────────────────
        function showMapToast(msg, type) {
            const t = document.createElement('div');
            t.style.cssText = `position:fixed;bottom:30px;right:30px;z-index:9999;padding:10px 18px;
                background:${type === 'success' ? '#004737' : '#c0392b'};color:#fff;border-radius:8px;
                font-family:'Inter',sans-serif;font-size:13px;font-weight:600;
                box-shadow:0 4px 18px rgba(0,0,0,0.25);animation:slideIn .3s ease-out;`;
            t.textContent = msg;
            document.body.appendChild(t);
            setTimeout(() => t.remove(), 3500);
        }

        // ─── Attach polygon events (edit / path change) ────────────────────────
        function attachPolygonEvents(polygon) {
            const updateBoth = () => {
                const coords = polygon.getPath().getArray().map(p => ({ lat: p.lat(), lng: p.lng() }));
                saveFarmBoundaryToFirebase(coords);
                updateBoundaryStats(polygon);
            };
            google.maps.event.addListener(polygon.getPath(), 'set_at', updateBoth);
            google.maps.event.addListener(polygon.getPath(), 'insert_at', updateBoth);
            google.maps.event.addListener(polygon.getPath(), 'remove_at', updateBoth);
        }

        function initMap() {
            const defaultLocation = { lat: 7.3442, lng: 125.5725 };

            googleMap = new google.maps.Map(document.getElementById('dashboardMap'), {
                zoom: 14,
                center: defaultLocation,
                disableDefaultUI: false,
                zoomControl: true,
                streetViewControl: false,
                mapTypeControl: true,
                mapTypeId: 'hybrid'
            });
            window.googleMap = googleMap;

            // ─── Top-right "Draw Boundary" button (manual click-to-draw) ───────────
            const drawBtnWrap = document.createElement('div');
            drawBtnWrap.style.margin = '10px 0 0 0';
            const drawBtn = document.createElement('button');
            drawBtn.id = 'drawBoundaryBtn';
            drawBtn.innerHTML = '<i class="fas fa-draw-polygon"></i> Draw Boundary';
            Object.assign(drawBtn.style, {
                background: '#004737', color: '#fff', border: 'none',
                borderRadius: '6px', padding: '7px 14px', cursor: 'pointer',
                fontFamily: "'Inter',sans-serif", fontSize: '13px', fontWeight: '600',
                boxShadow: '0 2px 6px rgba(0,0,0,.3)', display: 'flex',
                alignItems: 'center', gap: '6px'
            });
            drawBtn.onclick = () => {
                window.toggleDrawBoundary();
            };
            drawBtnWrap.appendChild(drawBtn);

            googleMap.controls[google.maps.ControlPosition.TOP_RIGHT].push(drawBtnWrap);

            // "Mark Location" button (place a temporary marker and get lat/lng)
            const markBtnWrap = document.createElement('div');
            markBtnWrap.style.margin = '5px 0 0 0';
            const markBtn = document.createElement('button');
            markBtn.id = 'markLocationBtn';
            markBtn.innerHTML = '<i class="fas fa-map-marker-alt"></i> Mark Location';
            Object.assign(markBtn.style, {
                background: '#fff', color: '#004737', border: '1px solid #ccc',
                borderRadius: '6px', padding: '7px 14px', cursor: 'pointer',
                fontFamily: "'Inter',sans-serif", fontSize: '13px', fontWeight: '600',
                boxShadow: '0 2px 6px rgba(0,0,0,.2)', display: 'flex',
                alignItems: 'center', gap: '6px'
            });
            markBtn.onclick = () => {
                window.markingMode = !window.markingMode;
                if (window.markingMode) {
                    markBtn.style.background = '#004737';
                    markBtn.style.color = '#fff';
                    markBtn.innerHTML = '<i class="fas fa-times"></i> Cancel Marking';
                    showMapToast('Click on the map to place a marker and get coordinates', 'info');
                } else {
                    markBtn.style.background = '#fff';
                    markBtn.style.color = '#004737';
                    markBtn.innerHTML = '<i class="fas fa-map-marker-alt"></i> Mark Location';
                    if (markMarker) { markMarker.setMap(null); markMarker = null; }
                    if (markInfoWindow) { markInfoWindow.close(); markInfoWindow = null; }
                }
            };
            markBtnWrap.appendChild(markBtn);
            googleMap.controls[google.maps.ControlPosition.TOP_RIGHT].push(markBtnWrap);

            // "Most Diseased" filter button: ranks trees by disease detections
            // "Diseases Detected" filter button: highlights every tree with a
            // disease detection (all detections ever) and opens their details.
            const mostBtnWrap = document.createElement('div');
            mostBtnWrap.style.margin = '5px 0 0 0';
            const mostBtn = document.createElement('button');
            mostBtn.id = 'diseaseFilterBtn';
            mostBtn.title = 'Show all trees with disease detections and open their details';
            mostBtn.innerHTML = '<i class="fas fa-virus"></i> Diseases Detected';
            Object.assign(mostBtn.style, {
                background: '#fff', color: '#004737', border: '1px solid #ccc',
                borderRadius: '6px', padding: '7px 14px', cursor: 'pointer',
                fontFamily: "'Inter',sans-serif", fontSize: '13px', fontWeight: '600',
                boxShadow: '0 2px 6px rgba(0,0,0,.2)', display: 'flex',
                alignItems: 'center', gap: '6px'
            });
            mostBtn.onclick = () => {
                if (typeof window.toggleDiseaseFilter === 'function') {
                    window.toggleDiseaseFilter();
                }
            };
            mostBtnWrap.appendChild(mostBtn);
            googleMap.controls[google.maps.ControlPosition.TOP_RIGHT].push(mostBtnWrap);
            window._diseaseFilterBtn = mostBtn;

            // Expose markBtn for global helper functions
            window._markBtn = markBtn;
            window.finishMarking = function () {
                window.markingMode = false;
                if (window._markBtn) {
                    window._markBtn.style.background = '#fff';
                    window._markBtn.style.color = '#004737';
                    window._markBtn.innerHTML = '<i class="fas fa-map-marker-alt"></i> Mark Location';
                }
                if (markInfoWindow) { markInfoWindow.close(); markInfoWindow = null; }
            };

            // Map click handler: unified Click-to-Mark flow
            // Flag to suppress map click when clicking InfoWindow buttons
            window._suppressMapClick = false;
            google.maps.event.addListener(googleMap, 'click', function (e) {
                // If manual boundary draw mode is active, add a vertex and ignore tree registration
                if (window.drawModeActive && typeof window.addDraftVertex === 'function') {
                    window.addDraftVertex(e.latLng);
                    return;
                }
                // Skip if an InfoWindow button or boundary draw is active
                if (window._suppressMapClick) {
                    window._suppressMapClick = false;
                    return;
                }
                const lat = e.latLng.lat();
                const lng = e.latLng.lng();

                // Clear any existing temporary marker and infowindow
                if (markMarker) {
                    markMarker.setMap(null);
                    markMarker = null;
                }
                if (markInfoWindow) {
                    markInfoWindow.close();
                    markInfoWindow = null;
                }

                showConfirmModal(
                    "Register New Tree",
                    `Would you like to register a new mango tree at these GPS coordinates?<br><br><strong>Lat:</strong> ${lat.toFixed(5)}<br><strong>Lng:</strong> ${lng.toFixed(5)}`,
                    () => {
                        showPromptModal(
                            "Identify Tree",
                            "Please enter a unique identifier for this tree:",
                            "Tree-000",
                            (treeId) => {
                                if (treeId && treeId.trim() !== '') {
                                    // Create temporary blue marker
                                    markMarker = new google.maps.Marker({
                                        position: { lat, lng },
                                        map: googleMap,
                                        title: `Saving ${treeId}...`,
                                        icon: {
                                            path: google.maps.SymbolPath.CIRCLE,
                                            scale: 8,
                                            fillOpacity: 0.8,
                                            fillColor: '#2196F3',
                                            strokeColor: '#1976D2',
                                            strokeWeight: 2
                                        }
                                    });

                                    markInfoWindow = new google.maps.InfoWindow({
                                        content: `<b>Saving Tree ${treeId}...</b>`
                                    });
                                    markInfoWindow.open(googleMap, markMarker);

                                    // Save to Firebase via PHP
                                    fetch('add_tree.php', {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json' },
                                        body: JSON.stringify({ id: treeId, lat, lng })
                                    })
                                        .then(response => response.json())
                                        .then(data => {
                                            if (data.success) {
                                                markInfoWindow.setContent(`
                                                <div style="padding:5px; text-align:center; font-family: 'Inter', sans-serif;">
                                                    <b style="color:#005343; font-size: 14px;">Tree ${treeId} Marked!</b><br>
                                                    <span style="font-size:11px; color:#666;">QR code auto-generated in system.</span><br>
                                                    <a href="qr-generator.php" class="btn btn-sm btn-primary" style="margin-top:8px; display:inline-block; font-size:10px; padding:4px 8px; text-decoration:none; color:white; background: #005343; border-radius:4px;">
                                                        <i class="fas fa-print"></i> Print QR Code
                                                    </a>
                                                </div>
                                            `);

                                                // Show toast
                                                if (typeof window.showMapToast === 'function') {
                                                    window.showMapToast(`🌳 Tree "${treeId}" saved successfully!`, 'success');
                                                }

                                                // Auto-remove after 3 seconds
                                                setTimeout(() => {
                                                    if (markMarker) {
                                                        markMarker.setMap(null);
                                                        markMarker = null;
                                                    }
                                                    if (markInfoWindow) {
                                                        markInfoWindow.close();
                                                        markInfoWindow = null;
                                                    }
                                                }, 3000);
                                            } else {
                                                markInfoWindow.setContent(`<b>Error:</b><br>${data.message}`);
                                            }
                                        })
                                        .catch(err => {
                                            console.error('Error:', err);
                                            markInfoWindow.setContent(`<b>Network Error</b>`);
                                        });
                                }
                            }
                        );
                    }
                );
            });

            // "Show All Details" control
            const controlDiv = document.createElement('div');
            controlDiv.style.margin = '5px 0 0 0';
            const controlUI = document.createElement('div');
            controlUI.style.backgroundColor = '#fff';
            controlUI.style.border = '2px solid #fff';
            controlUI.style.borderRadius = '3px';
            controlUI.style.boxShadow = '0 2px 6px rgba(0,0,0,.3)';
            controlUI.style.cursor = 'pointer';
            controlUI.style.textAlign = 'center';
            controlUI.title = 'Click to show all tree details';
            controlDiv.appendChild(controlUI);

            const controlText = document.createElement('div');
            controlText.style.color = 'rgb(25,25,25)';
            controlText.style.fontFamily = 'Roboto,Arial,sans-serif';
            controlText.style.fontSize = '14px';
            controlText.style.lineHeight = '32px';
            controlText.style.paddingLeft = '10px';
            controlText.style.paddingRight = '10px';
            controlText.innerHTML = '<i class="fas fa-eye"></i> Show All Details';
            controlUI.appendChild(controlText);

            googleMap.controls[google.maps.ControlPosition.TOP_RIGHT].push(controlDiv);

            // ─── Farm Boundary Map Control ───────────────────────────────────────
            const drawControlDiv = document.createElement('div');
            drawControlDiv.style.margin = '10px 0 0 10px';
            drawControlDiv.innerHTML = `
                <div id="btnDrawBoundary" onclick="window.toggleDrawBoundary()" title="Click to draw a farm boundary polygon" style="background:#ffffff; border:2px solid #ffffff; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.3); cursor:pointer; padding:6px 14px; font-family:'Inter',sans-serif; font-size:13px; font-weight:700; color:#005343; display:flex; align-items:center; gap:8px; transition:all 0.2s ease;">
                    <i class="fas fa-draw-polygon" style="color:#2e7d32; font-size:15px;"></i>
                    <span>Draw Boundary</span>
                </div>
            `;
            googleMap.controls[google.maps.ControlPosition.TOP_LEFT].push(drawControlDiv);

            // Load saved boundaries from /farm_boundaries
            setTimeout(() => {
                if (typeof window.loadFarmBoundaries === 'function') {
                    window.loadFarmBoundaries();
                }
            }, 500);

            // Add Health Status Legend
            const legendDiv = document.createElement('div');
            legendDiv.style.margin = '10px';
            legendDiv.style.backgroundColor = 'rgba(255, 255, 255, 0.9)';
            legendDiv.style.padding = '10px';
            legendDiv.style.borderRadius = '8px';
            legendDiv.style.boxShadow = '0 2px 6px rgba(0,0,0,0.3)';
            legendDiv.style.fontFamily = "'Inter', sans-serif";
            legendDiv.style.fontSize = '12px';
            legendDiv.innerHTML = `
                <div style="font-weight: bold; margin-bottom: 6px; color: #333; border-bottom: 1px solid #eee; padding-bottom: 4px; font-size: 13px;">
                    <i class="fas fa-circle-info" style="margin-right:4px; color:#005343;"></i>Map Legend
                </div>
                <div style="display: flex; align-items: center; margin-bottom: 4px;">
                    <span style="width: 13px; height: 13px; border-radius: 50%; background: #9C27B0; display: inline-block; margin-right: 8px; border: 2px solid #6a1b9a; flex-shrink:0;"></span>
                    <span>🟣 Spray Today</span>
                </div>
                <div style="display: flex; align-items: center; margin-bottom: 4px;">
                    <span style="width: 13px; height: 13px; border-radius: 50%; background: #FF9800; display: inline-block; margin-right: 8px; border: 2px solid #e65100; flex-shrink:0;"></span>
                    <span>🟠 Spray Tomorrow</span>
                </div>
                <div style="display: flex; align-items: center; margin-bottom: 4px;">
                    <span style="width: 13px; height: 13px; border-radius: 50%; background: #FFC107; display: inline-block; margin-right: 8px; border: 2px solid #ff8f00; flex-shrink:0;"></span>
                    <span>🟡 Spray Within 3 Days</span>
                </div>
                <div style="display: flex; align-items: center; margin-bottom: 4px;">
                    <span style="width: 13px; height: 13px; border-radius: 50%; background: #2196F3; display: inline-block; margin-right: 8px; border: 2px solid #1565c0; flex-shrink:0;"></span>
                    <span>🔵 Spray Scheduled (7+ days)</span>
                </div>
                <div style="display: flex; align-items: center; margin-bottom: 4px;">
                    <span style="width: 13px; height: 13px; border-radius: 50%; background: #F44336; display: inline-block; margin-right: 8px; border: 2px solid #c62828; flex-shrink:0;"></span>
                    <span>🔴 High Disease Severity (last 30 days)</span>
                </div>
                <div style="display: flex; align-items: center; margin-bottom: 4px;">
                    <span style="width: 13px; height: 13px; border-radius: 50%; background: #4CAF50; display: inline-block; margin-right: 8px; border: 2px solid #2e7d32; flex-shrink:0;"></span>
                    <span>🟢 Healthy / No Disease</span>
                </div>
                <div style="display: flex; align-items: center;">
                    <span style="width: 13px; height: 13px; border-radius: 50%; background: #546E7A; display: inline-block; margin-right: 8px; border: 2px solid #263238; flex-shrink:0;"></span>
                    <span>⚫ Overdue Spray</span>
                </div>
            `;
            // Add Farm Area Stats Box to legend area
            const statsBoxOuter = document.createElement('div');
            statsBoxOuter.style.margin = '5px 0 0 0';
            statsBoxOuter.innerHTML = `
                <div id="boundaryStatsBox" style="display:none; background:rgba(255,255,255,0.95); border-radius:8px;
                    padding:10px 12px; box-shadow:0 2px 6px rgba(0,0,0,0.3); font-family:'Inter',sans-serif;
                    font-size:12px; border-left:4px solid #E53935; flex-direction:column; gap:4px;">
                    <div style="font-weight:700;color:#b71c1c;margin-bottom:4px;padding-bottom:4px;border-bottom:1px solid #f5c6c6;">
                        <i class="fas fa-vector-square" style="margin-right:5px;"></i>Farm Area Analytics
                    </div>
                    <div style="display:flex;align-items:center;gap:6px;">
                        <i class="fas fa-tree" style="color:#004737;width:14px;"></i>
                        <span>Trees Inside: <strong id="bsTrees">0</strong></span>
                    </div>
                    <div style="display:flex;align-items:center;gap:6px;">
                        <i class="fas fa-virus" style="color:#c0392b;width:14px;"></i>
                        <span>Disease Scans: <strong id="bsDisease">0</strong></span>
                    </div>
                </div>
            `;
            googleMap.controls[google.maps.ControlPosition.LEFT_BOTTOM].push(legendDiv);
            googleMap.controls[google.maps.ControlPosition.LEFT_BOTTOM].push(statsBoxOuter);

            // Add styles for QR modal if not present
            if (!document.getElementById('qrModalStyles')) {
                const style = document.createElement('style');
                style.id = 'qrModalStyles';
                style.innerHTML = `
                    .qr-code-container { text-align: center; padding: 1.5rem; }
                    .qr-code-image { max-width: 260px; width: 100%; height: auto; margin: 1.25rem auto; display: block; border-radius: 12px; box-shadow: var(--shadow-card); border: 3px solid #fff; }
                    .qr-print-container { text-align: center; padding: 30px; background: white; }
                    .qr-print-details { margin-top: 20px; font-family: Arial, sans-serif; }
                    .qr-print-text { font-size: 18px; margin: 10px 0; color: #000; }
                    @media print {
                        body * { visibility: hidden; }
                        #printQRContainer, #printQRContainer * { visibility: visible; }
                        #printQRContainer { position: absolute; left: 0; top: 0; width: 100%; height: 100%; display: block !important; }
                        .qr-print-container { padding: 50px; background: white; }
                    }
                `;
                document.head.appendChild(style);
            }

            window._allInfoWindowsVisible = false;
            controlUI.addEventListener('click', () => {
                window._allInfoWindowsVisible = !window._allInfoWindowsVisible;
                controlText.innerHTML = window._allInfoWindowsVisible ?
                    '<i class="fas fa-eye-slash"></i> Hide All Details' :
                    '<i class="fas fa-eye"></i> Show All Details';

                // This is a bit tricky with Google Maps, as InfoWindows usually auto-close others.
                // We'll just open the first one if toggle is off, or use labels if we had them.
                // But for now, let's just make the markers more prominent by default.
                if (window._allInfoWindowsVisible) {
                    // Open all currently visible markers' infowindows
                }
                window.updateMapMarkers();
            });

            // Conflicts removed. Using unified click handler above.

            // Modal Helper Functions (Professional registration)
            function showConfirmModal(title, message, onConfirm) {
                const modalHtml = `
                    <div class="custom-modal-overlay" id="confirmModal">
                        <div class="custom-modal-container">
                            <div class="custom-modal-header">
                                <i class="fas fa-map-marker-alt"></i>
                                <h2>${title}</h2>
                            </div>
                            <div class="custom-modal-body">${message}</div>
                            <div class="custom-modal-footer">
                                <button class="custom-modal-btn custom-modal-btn-cancel" id="confirmCancel">Cancel</button>
                                <button class="custom-modal-btn custom-modal-btn-confirm" id="confirmOk">Register Tree</button>
                            </div>
                        </div>
                    </div>
                `;
                document.body.insertAdjacentHTML('beforeend', modalHtml);
                const modal = document.getElementById('confirmModal');
                setTimeout(() => modal.classList.add('active'), 10);

                const cleanup = () => {
                    modal.classList.remove('active');
                    setTimeout(() => modal.remove(), 300);
                };

                document.getElementById('confirmCancel').onclick = cleanup;
                document.getElementById('confirmOk').onclick = () => {
                    cleanup();
                    onConfirm();
                };
            }

            function showPromptModal(title, message, placeholder, onConfirm) {
                const modalHtml = `
                    <div class="custom-modal-overlay" id="promptModal">
                        <div class="custom-modal-container">
                            <div class="custom-modal-header">
                                <i class="fas fa-edit"></i>
                                <h2>${title}</h2>
                            </div>
                            <div class="custom-modal-body">
                                ${message}
                                <div class="custom-modal-input-container">
                                    <input type="text" class="custom-modal-input" id="promptInput" placeholder="${placeholder}" autofocus>
                                </div>
                            </div>
                            <div class="custom-modal-footer">
                                <button class="custom-modal-btn custom-modal-btn-cancel" id="promptCancel">Cancel</button>
                                <button class="custom-modal-btn custom-modal-btn-confirm" id="promptOk">Confirm Details</button>
                            </div>
                        </div>
                    </div>
                `;
                document.body.insertAdjacentHTML('beforeend', modalHtml);
                const modal = document.getElementById('promptModal');
                const input = document.getElementById('promptInput');
                setTimeout(() => {
                    modal.classList.add('active');
                    input.focus();
                }, 10);

                const cleanup = () => {
                    modal.classList.remove('active');
                    setTimeout(() => modal.remove(), 300);
                };

                document.getElementById('promptCancel').onclick = cleanup;
                document.getElementById('promptOk').onclick = () => {
                    const val = input.value;
                    cleanup();
                    onConfirm(val);
                };
                input.onkeyup = (e) => {
                    if (e.key === 'Enter') document.getElementById('promptOk').click();
                };
            }

            // 30-day rolling window for disease scan counts (mirrors the 30-day
            // treatment cycle). Scans older than this no longer count, so a
            // tree's health status resets to green over time.
            const SCAN_WINDOW_MS = 30 * 24 * 60 * 60 * 1000;

            function scanAgeMs(scan) {
                const t = Number(scan && scan.timestamp);
                if (!t || t <= 0) return null;
                return t > 9999999999 ? t : t * 1000;
            }

            // Shared disease ranking: counts each tree's scans. When allTime is
            // true, EVERY detection ever recorded counts (no 30-day cutoff);
            // otherwise only scans inside the 30-day window count, exactly
            // matching how marker colors are computed. Returns { treeId: count }.
            function computeTreeDiseaseCounts(allTime) {
                const counts = {};
                if (!window.globalTrees || !window.globalScans) return counts;
                const cutoff = allTime ? -Infinity : Date.now() - SCAN_WINDOW_MS;
                Object.keys(window.globalTrees).forEach(treeId => {
                    if (treeId === '0' || treeId === 0 || String(treeId) === '0') return;
                    let count = 0;
                    Object.values(window.globalScans).forEach(scan => {
                        const scanTree = scan.tree_id || scan.treeId || scan.treeID || '';
                        if (scanTree && String(scanTree).toLowerCase() === String(treeId).toLowerCase()) {
                            const ts = scanAgeMs(scan);
                            if (ts !== null && ts >= cutoff) {
                                count++;
                            }
                        }
                    });
                    counts[treeId] = count;
                });
                return counts;
            }

            // "Diseases Detected" filter toggle (exposed via window for the map
            // button). On: highlights EVERY tree with at least one disease
            // detection (all detections ever) and auto-opens their marker
            // details. Off: restores the full view.
            window.toggleDiseaseFilter = function () {
                const btn = window._diseaseFilterBtn;

                if (window._diseaseFilterMode) {
                    // ── Toggle OFF: close popups + restore the full view ──
                    window._diseaseFilterMode = false;
                    window._affectedDiseaseCounts = null;
                    markers.forEach(m => { if (m._infowindow) m._infowindow.close(); });
                    if (btn) {
                        btn.style.background = '#fff';
                        btn.style.color = '#004737';
                        btn.innerHTML = '<i class="fas fa-virus"></i> Diseases Detected';
                    }
                    window.hasFittedBounds = false;
                    if (typeof window.updateMapMarkers === 'function') window.updateMapMarkers();
                    showMapToast('Disease filter cleared.', 'info');
                    return;
                }

                // ── Toggle ON ──
                const counts = computeTreeDiseaseCounts(true);
                const affected = Object.keys(counts).filter(tId => counts[tId] > 0);
                if (affected.length === 0) {
                    showMapToast('No disease detections recorded.', 'info');
                    return;
                }

                window._diseaseFilterMode = true;
                window._affectedDiseaseCounts = counts;
                if (btn) {
                    btn.style.background = '#004737';
                    btn.style.color = '#fff';
                    btn.innerHTML = '<i class="fas fa-times"></i> Clear Filter';
                }

                // Fit the camera to the whole affected cluster (cap zoom like the default fit)
                const focusBounds = new google.maps.LatLngBounds();
                let placed = 0;
                affected.forEach(tId => {
                    const tree = window.globalTrees[tId];
                    const lat = parseFloat(tree && (tree.lat || tree.latitude));
                    const lng = parseFloat(tree && (tree.lng || tree.longitude));
                    if (!isNaN(lat) && !isNaN(lng)) {
                        focusBounds.extend({ lat, lng });
                        placed++;
                    }
                });
                if (placed > 0) {
                    if (placed === 1) {
                        googleMap.setCenter(focusBounds.getCenter());
                        googleMap.setZoom(16);
                    } else {
                        googleMap.fitBounds(focusBounds);
                        google.maps.event.addListenerOnce(googleMap, 'idle', function () {
                            try {
                                if (googleMap.getZoom() > 16) googleMap.setZoom(16);
                            } catch (e) {}
                        });
                    }
                }

                // Rebuild markers so affected trees get highlighted and their
                // marker details auto-open (handled inside updateMapMarkers).
                if (typeof window.updateMapMarkers === 'function') window.updateMapMarkers();

                showMapToast(`Showing ${affected.length} tree${affected.length === 1 ? '' : 's'} with disease detections`, 'success');
            };

            // Define global marker update function
            window.updateMapMarkers = function () {
                if (!window.globalTrees || !googleMap) return;

                // Clear old markers
                markers.forEach(m => m.setMap(null));
                markers = [];
                window._activeTreeInfoWindow = null;

                const bounds = new google.maps.LatLngBounds();
                let hasMarkers = false;

                const diseaseCounts = computeTreeDiseaseCounts();
                // Keep the all-disease set fresh while the filter is active so
                // the highlight follows live scan data.
                let affectedCounts = null;
                if (window._diseaseFilterMode) {
                    affectedCounts = computeTreeDiseaseCounts(true);
                    window._affectedDiseaseCounts = affectedCounts;
                }

                Object.entries(window.globalTrees).forEach(([treeId, tree]) => {
                    const treeLat = parseFloat(tree.lat || tree.latitude);
                    const treeLng = parseFloat(tree.lng || tree.longitude);

                    if (!isNaN(treeLat) && !isNaN(treeLng) && treeId !== '0') {
                        const diseaseCount = diseaseCounts[treeId] || 0;
                        const isAffected = window._diseaseFilterMode && (affectedCounts[treeId] || 0) > 0;
                        const affectedCount = isAffected ? (affectedCounts[treeId] || 0) : 0;

                        // ── 7-Color Priority Marker System ───────────────────────────
                        // Priority (highest → lowest):
                        //  1. 🟣 Purple   – Spray TODAY
                        //  2. 🟠 Orange   – Spray TOMORROW
                        //  3. 🟡 Yellow   – Spray within 3 days
                        //  4. 🔵 Blue     – Spray in 4–7+ days (upcoming)
                        //  5. 🔴 Red      – High disease severity (no urgent schedule)
                        //  6. ⚫ Dark Gray – Overdue schedule (no future schedule)
                        //  7. 🟢 Green    – Healthy / No disease / No schedule

                        let markerColor = '#4CAF50'; // Default: Green (healthy)
                        let markerStroke = '#2e7d32';
                        let sprayStatus = 'healthy';

                        // Build reference dates (local midnight, no timezone drift)
                        const _now = new Date();
                        _now.setHours(0, 0, 0, 0);
                        const _tomorrow = new Date(_now);
                        _tomorrow.setDate(_now.getDate() + 1);
                        const _in3Days = new Date(_now);
                        _in3Days.setDate(_now.getDate() + 3);

                        // Normalise & parse all non-completed schedules for this tree
                        let pendingSchedules = [];
                        if (window.globalSchedules) {
                            pendingSchedules = window.globalSchedules
                                .filter(s => String(s.treeId).toLowerCase() === String(treeId).toLowerCase()
                                    && String(s.status || 'pending').toLowerCase() !== 'completed')
                                .map(s => {
                                    let rawDate = s.spray_date || s.date || '';
                                    if (!rawDate && s.timestamp) {
                                        const ts = Number(s.timestamp) > 9999999999 ? Number(s.timestamp) / 1000 : Number(s.timestamp);
                                        const d = new Date(ts * 1000);
                                        rawDate = d.getFullYear() + '-'
                                            + String(d.getMonth() + 1).padStart(2, '0') + '-'
                                            + String(d.getDate()).padStart(2, '0');
                                    }
                                    const parts = rawDate ? rawDate.split('-') : [];
                                    let dateObj = null;
                                    if (parts.length === 3) {
                                        dateObj = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
                                        dateObj.setHours(0, 0, 0, 0);
                                    }
                                    return { ...s, _dateObj: dateObj };
                                })
                                .filter(s => s._dateObj !== null)
                                .sort((a, b) => a._dateObj - b._dateObj);
                        }

                        const overdueSchedules = pendingSchedules.filter(s => s._dateObj < _now);
                        const upcomingSchedules = pendingSchedules.filter(s => s._dateObj >= _now);

                        // 🔴 Critical: 10+ cumulative disease scans — highest priority,
                        // overrides all spray-schedule colors.
                        if (diseaseCount >= 10) {
                            markerColor = '#F44336'; markerStroke = '#c62828'; sprayStatus = 'disease';
                        } else if (upcomingSchedules.length > 0) {
                            const nextDate = upcomingSchedules[0]._dateObj;

                            if (nextDate.getTime() === _now.getTime()) {
                                // 🟣 Spray TODAY – highest urgency
                                markerColor = '#9C27B0'; markerStroke = '#6a1b9a'; sprayStatus = 'today';

                            } else if (nextDate.getTime() === _tomorrow.getTime()) {
                                // 🟠 Spray TOMORROW
                                markerColor = '#FF9800'; markerStroke = '#e65100'; sprayStatus = 'tomorrow';

                            } else if (nextDate <= _in3Days) {
                                // 🟡 Spray within 3 days
                                markerColor = '#FFC107'; markerStroke = '#ff8f00'; sprayStatus = 'soon';

                            } else {
                                // 🔵 Spray scheduled 4+ / 7+ days ahead
                                markerColor = '#2196F3'; markerStroke = '#1565c0'; sprayStatus = 'upcoming';
                            }

                        } else if (overdueSchedules.length > 0) {
                            // ⚫ Overdue – had a schedule that has passed
                            markerColor = '#546E7A'; markerStroke = '#263238'; sprayStatus = 'overdue';

                        } else {
                            // No schedule at all – warning amber for any disease (1–9 scans).
                            // 🔴 Red at 10+ is handled by the top-priority rule above.
                            if (diseaseCount > 0) {
                                // 🟠 Warning – disease detected but below critical threshold
                                markerColor = '#FF8F00'; markerStroke = '#F57C00'; sprayStatus = 'warning';
                            }
                            // else stays 🟢 Green (healthy)
                        }

                        let statusColor = markerStroke;
                        let bgColor = markerColor + '15'; // Very light version for BG

                        const marker = new google.maps.Marker({
                            position: { lat: treeLat, lng: treeLng },
                            map: googleMap,
                            icon: {
                                path: google.maps.SymbolPath.CIRCLE,
                                scale: isAffected ? 11 : 8,
                                fillOpacity: 0.9,
                                fillColor: markerColor,
                                strokeColor: isAffected ? '#B71C1C' : markerStroke,
                                strokeWeight: isAffected ? 3 : 2
                            }
                        });

                        // Pulse colors matched to 7-color scheme
                        marker._baseColor = markerColor;
                        marker._pulseColor =
                            sprayStatus === 'today' ? '#ce93d8' : // Purple → light purple
                                sprayStatus === 'tomorrow' ? '#ffcc80' : // Orange → light orange
                                    sprayStatus === 'soon' ? '#fff176' : // Yellow → light yellow
sprayStatus === 'upcoming' ? '#90caf9' : // Blue   → light blue
                                        sprayStatus === 'disease' ? '#ef9a9a' : // Red    → light red
                                            sprayStatus === 'warning' ? '#ffcc80' : // Amber  → light orange
                                                sprayStatus === 'overdue' ? '#90a4ae' : // DkGray → lighter blue-gray
                                                    '#a5d6a7';  // Green → light green (healthy)
                        marker._isPulsing = true; // All markers pulse

                        // Find next spray for this tree from globalSchedules
                        let nextSprayText = "No schedule set";
                        if (window.globalSchedules) {
                            const treeSchedules = window.globalSchedules
                                .filter(s => s.treeId === treeId
                                    && String(s.status || 'pending').toLowerCase() === 'pending'
                                    && s.spray_date && s.spray_time)
                                .sort((a, b) => new Date(a.spray_date + ' ' + a.spray_time).getTime() - new Date(b.spray_date + ' ' + b.spray_time).getTime());

                            if (treeSchedules.length > 0) {
                                const s = treeSchedules[0];
                                const ts = new Date(s.spray_date + ' ' + s.spray_time);
                                if (!isNaN(ts.getTime())) {
                                    const dateFormatted = ts.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
                                    let [h, m] = s.spray_time.split(':');
                                    h = parseInt(h, 10);
                                    const ampm = h >= 12 ? 'PM' : 'AM';
                                    h = h % 12 || 12;
                                    nextSprayText = `${dateFormatted} at ${h}:${m} ${ampm}`;
                                }
                            }
                        }

                        let safeTreeId = String(treeId).replace(/'/g, "\\'");
                        let safeLocation = String(tree.location || 'N/A').replace(/'/g, "\\'");
                        const infowindow = new google.maps.InfoWindow({
                            content: `
                                <div style="padding:5px; min-width:200px; font-family: 'Inter', sans-serif;">
                                    <h4 style="margin:0 0 8px 0; color:#005343; border-bottom: 1px solid #eee; padding-bottom: 5px;">
                                        <i class="fas fa-leaf"></i> Tree Details
                                    </h4>
                                    <div style="margin-bottom: 5px;">
                                        <span style="color: #666; font-size: 11px; text-transform: uppercase; display: block;">Tree Identifier:</span>
                                        <strong style="color: #333; font-size: 14px;">${treeId}</strong>
                                    </div>
                                    <div style="margin-bottom: 8px;">
                                        <span style="color: #666; font-size: 11px; text-transform: uppercase; display: block;">Health Status:</span>
                                        <span style="padding: 2px 6px; border-radius: 4px; font-size: 12px; font-weight: bold; background: ${bgColor}; color: ${statusColor};">
                                            <i class="fas ${diseaseCount >= 10 ? 'fa-exclamation-triangle' : diseaseCount > 0 ? 'fa-virus' : 'fa-check-circle'}"></i> 
                                            ${diseaseCount >= 10 ? '🔴 Critical' : diseaseCount > 0 ? '⚠️ Warning' : 'Healthy'} — ${diseaseCount} Disease Scan${diseaseCount === 1 ? '' : 's'}
                                        </span>
                                    </div>
                                    ${isAffected ? `
                                    <div style="margin-bottom: 8px;">
                                        <span style="display:inline-block; background:#B71C1C; color:#fff; padding:3px 8px; border-radius:4px; font-size:12px; font-weight:700;">
                                            <i class="fas fa-virus"></i> Disease Detected — ${affectedCount} scan${affectedCount === 1 ? '' : 's'}
                                        </span>
                                    </div>` : ''}
                                    <div style="margin-bottom: 10px; border-top: 1px solid #f0f0f0; padding-top: 8px;">
                                        <span style="color: #666; font-size: 11px; text-transform: uppercase; display: block;">Next Treatment:</span>
                                        <span style="font-size: 13px; font-weight: 600; color: #1b5e20;">
                                            <i class="fas fa-calendar-alt" style="margin-right: 4px;"></i> 
                                            ${nextSprayText}
                                        </span>
                                    </div>
                                    <div style="margin-top: 10px; border-top: 1px solid #f0f0f0; padding-top: 10px; text-align: center;">
                                        ${tree.has_qr ? `
                                            <button type="button" onmousedown="event.stopPropagation(); window._suppressMapClick=true;" onclick="event.stopPropagation(); window._suppressMapClick=true; showQRModal('${safeTreeId}', '${safeLocation}', 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' + encodeURIComponent(JSON.stringify({tree_id: '${safeTreeId}', location: '${safeLocation}', generated_at: ${tree.timestamp || Date.now()}})))" class="btn btn-sm btn-secondary" style="width: 100%; justify-content: center; margin-bottom: 8px;">
                                                <i class="fas fa-qrcode"></i> View QR Code
                                            </button>
                                        ` : ''}
                                        <button type="button" onmousedown="event.stopPropagation(); window._suppressMapClick=true;" onclick="event.stopPropagation(); showScheduleModal('${String(treeId).replace(/'/g, "\\'")}')" class="btn btn-sm btn-primary" style="width: 100%; justify-content: center; background: #2e7d32;">
                                            <i class="fas fa-calendar-plus"></i> Create Spray Schedule
                                        </button>
                                    </div>
                                </div>
                            `
                        });

                        marker._treeId = treeId;
                        marker._diseaseCount = diseaseCount;
                        marker._infowindow = infowindow;

                        marker.addListener('mouseover', () => {
                            if (window._allInfoWindowsVisible) {
                                infowindow.open(googleMap, marker);
                            } else {
                                if (window._activeTreeInfoWindow && window._activeTreeInfoWindow !== infowindow) {
                                    window._activeTreeInfoWindow.close();
                                }
                                infowindow.open(googleMap, marker);
                                window._activeTreeInfoWindow = infowindow;
                            }
                        });

                        marker.addListener('click', () => {
                            window._suppressMapClick = true;
                            if (window._allInfoWindowsVisible) {
                                infowindow.open(googleMap, marker);
                            } else {
                                if (window._activeTreeInfoWindow && window._activeTreeInfoWindow !== infowindow) {
                                    window._activeTreeInfoWindow.close();
                                }
                                infowindow.open(googleMap, marker);
                                window._activeTreeInfoWindow = infowindow;
                            }
                        });

                        if (window._allInfoWindowsVisible) {
                            infowindow.open(googleMap, marker);
                        }

                        markers.push(marker);
                        bounds.extend(marker.getPosition());
                        hasMarkers = true;
                    }
                });

                // While the disease filter is active, auto-open the marker
                // details of every affected tree so all detections are visible.
                if (window._diseaseFilterMode && window._affectedDiseaseCounts) {
                    markers.forEach(m => {
                        const cnt = window._affectedDiseaseCounts[m._treeId] || 0;
                        if (cnt > 0 && m._infowindow) {
                            m._infowindow.open(googleMap, m);
                        }
                    });
                }

                window.focusTreeOnMap = function (targetId) {
                    if (!targetId || !googleMap || !markers) return;
                    const targetMarker = markers.find(m => m._treeId === targetId || (m._treeId && String(m._treeId).toLowerCase() === String(targetId).toLowerCase()));
                    if (targetMarker) {
                        googleMap.panTo(targetMarker.getPosition());
                        googleMap.setZoom(16);
                        if (targetMarker._infowindow) {
                            targetMarker._infowindow.open(googleMap, targetMarker);
                        }
                    }
                };

                if (hasMarkers && !window.hasFittedBounds) {
                    // If there's only one marker, center and set a sensible zoom level
                    if (markers.length === 1) {
                        googleMap.setCenter(bounds.getCenter());
                        googleMap.setZoom(16); // reasonable close-up for a single tree
                    } else {
                        // Fit bounds with markers; then cap the maximum zoom to avoid over-zooming
                        googleMap.fitBounds(bounds);
                        google.maps.event.addListenerOnce(googleMap, 'idle', function () {
                            const MAX_ZOOM = 16;
                            try {
                                if (googleMap.getZoom() > MAX_ZOOM) {
                                    googleMap.setZoom(MAX_ZOOM);
                                }
                            } catch (e) {
                                // ignore errors
                            }
                        });
                    }
                    window.hasFittedBounds = true;
                }
            };

            // Remove loading text
            const mapDiv = document.getElementById('dashboardMap');
            const loadingText = mapDiv.querySelector('div[style*="justify-content:center"]');
            if (loadingText) loadingText.remove();

            // Initial marker load
            if (window.globalTrees) window.updateMapMarkers();

            // Set marker pulsing interval (every 5s)
            if (!window._markerPulseInterval) {
                window._markerPulseToggle = false;
                window._markerPulseInterval = setInterval(() => {
                    window._markerPulseToggle = !window._markerPulseToggle;
                    if (window.markers) {
                        window.markers.forEach(m => {
                            if (m._isPulsing) {
                                let icon = m.getIcon();
                                icon.fillColor = window._markerPulseToggle ? m._pulseColor : m._baseColor;
                                m.setIcon(icon);
                            }
                        });
                    }
                }, 5000);
            }
        }

        // Initialize when window loads
        window.onload = function () {
            if (typeof google !== 'undefined') initMap();
        };
    </script>

    <!-- ═══════════════════════════════════════════════════════════
         FARMER TREE SUBMISSIONS — Real-time markers (Dashboard map)
         Listens to /tree_submissions. Every node with status ===
         'pending' and a latitude/longitude gets a distinct blue
         marker. Clicking opens a read-only InfoWindow; the marker is
         removed automatically when the submission is approved/rejected.
    ═══════════════════════════════════════════════════════════════ -->
    <script type="module">
        const _fb = window.MangoFB;
        const _db = _fb.db;
        const _ref = _fb.ref;
        const _onValue = _fb.onValue;

        const _esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

        window.pendingMarkers = window.pendingMarkers || {};
        window._activePendingInfoWindow = null;

        function renderPendingSubmissions() {
            if (!window.googleMap || !window._pendingSubs) return;
            const data = window._pendingSubs;

            // 1) Remove markers for submissions no longer pending
            Object.keys(window.pendingMarkers).forEach(sid => {
                const sub = data[sid];
                if (!sub || (sub.status || '').toLowerCase() !== 'pending') {
                    window.pendingMarkers[sid].setMap(null);
                    delete window.pendingMarkers[sid];
                }
            });

            // 2) Add markers for pending submissions (skip already-rendered)
            Object.keys(data).forEach(submissionId => {
                const sub = data[submissionId];
                if ((sub.status || '').toLowerCase() !== 'pending') return;
                const lat = parseFloat(sub.latitude);
                const lng = parseFloat(sub.longitude);
                if (isNaN(lat) || isNaN(lng)) return;
                if (window.pendingMarkers[submissionId]) return;

                const farmerName  = sub.farmerName  || 'Unknown Farmer';
                const farmerEmail = sub.farmerEmail || '';
                const notes       = sub.notes       || '';
                const submittedAt = sub.submittedAt
                    ? new Date(sub.submittedAt).toLocaleDateString('en-PH', {
                        month: 'short', day: 'numeric', year: 'numeric',
                        hour: '2-digit', minute: '2-digit'
                    })
                    : 'Unknown';

                const pendingMarker = new google.maps.Marker({
                    position: { lat, lng },
                    map: window.googleMap,
                    zIndex: 999,
                    title: 'Pending: ' + farmerName,
                    icon: {
                        path: google.maps.SymbolPath.CIRCLE,
                        scale: 11,
                        fillOpacity: 0.92,
                        fillColor: '#2563EB',
                        strokeColor: '#1D4ED8',
                        strokeWeight: 2.5
                    }
                });
                window.pendingMarkers[submissionId] = pendingMarker;

                const notesHtml = notes
                    ? `<div style="margin:6px 0 10px;padding:6px 8px;background:#eff6ff;border-radius:7px;font-size:11px;color:#3b5998;border-left:3px solid #2563EB;">&ldquo;${_esc(notes)}&rdquo;</div>`
                    : '';

                const infoContent = `
                    <div style="padding:4px;min-width:240px;font-family:'Inter',sans-serif;">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;padding-bottom:8px;border-bottom:1px solid #e2e8f0;">
                            <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#2563EB,#1D4ED8);display:flex;align-items:center;justify-content:center;color:white;font-size:15px;flex-shrink:0;">⏳</div>
                            <div>
                                <div style="font-weight:700;font-size:13px;color:#1e293b;">Farmer Submission</div>
                                <div style="font-size:11px;color:#64748b;">${_esc(submittedAt)}</div>
                            </div>
                        </div>
                        <div style="margin-bottom:9px;">
                            <div style="font-size:10px;color:#94a3b8;text-transform:uppercase;font-weight:700;letter-spacing:.05em;">Farmer</div>
                            <div style="font-size:13px;font-weight:700;color:#1e293b;">${_esc(farmerName)}</div>
                            ${farmerEmail ? `<div style="font-size:11px;color:#64748b;">${_esc(farmerEmail)}</div>` : ''}
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:10px;">
                            <div style="background:#f8fafc;border-radius:8px;padding:6px 8px;">
                                <div style="font-size:10px;color:#94a3b8;font-weight:700;">LATITUDE</div>
                                <div style="font-size:12px;font-weight:700;color:#1e293b;">${lat.toFixed(6)}</div>
                            </div>
                            <div style="background:#f8fafc;border-radius:8px;padding:6px 8px;">
                                <div style="font-size:10px;color:#94a3b8;font-weight:700;">LONGITUDE</div>
                                <div style="font-size:12px;font-weight:700;color:#1e293b;">${lng.toFixed(6)}</div>
                            </div>
                        </div>
                        ${notesHtml}
                        <a href="tree_map.php" style="display:block;text-align:center;background:#f0f9ff;border:1.5px solid #bae6fd;color:#0369a1;padding:9px 0;border-radius:9px;font-size:12px;font-weight:700;text-decoration:none;">
                            <i class="fas fa-external-link-alt"></i> Open Tree Map &amp; Approve
                        </a>
                    </div>
                `;

                const pendingInfoWindow = new google.maps.InfoWindow({ content: infoContent, maxWidth: 280 });

                pendingMarker.addListener('click', () => {
                    if (window._activeTreeInfoWindow) {
                        window._activeTreeInfoWindow.close();
                        window._activeTreeInfoWindow = null;
                    }
                    if (window._activePendingInfoWindow) window._activePendingInfoWindow.close();
                    pendingInfoWindow.open(window.googleMap, pendingMarker);
                    window._activePendingInfoWindow = pendingInfoWindow;
                });
            });
        }

        _onValue(_ref(_db, 'tree_submissions'), (snap) => {
            window._pendingSubs = snap.val() || {};
            renderPendingSubmissions();
        });

        // Render once the dashboard map is ready (it initializes on load).
        let _mapWait = setInterval(() => {
            if (window.googleMap) {
                clearInterval(_mapWait);
                renderPendingSubmissions();
            }
        }, 250);
        setTimeout(() => clearInterval(_mapWait), 15000);
    </script>

    <!-- QR Code Modal -->
    <div id="qrModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeQRModal()">&times;</span>
            <h2><i class="fas fa-qrcode"></i>Tree QR Code</h2>
            <div id="modalQRContent"></div>
        </div>
    </div>

    <!-- Add Tree Modal (used by map 'Save as Tree') -->
    <div id="addTreeModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeAddTreeModal()">&times;</span>
            <h2><i class="fas fa-tree"></i> Add Tree</h2>
            <form id="addTreeForm" onsubmit="submitAddTreeForm(event)">
                <div class="form-group">
                    <label for="add_tree_id">Tree ID *</label>
                    <input type="text" id="add_tree_id" name="id" required placeholder="e.g., TREE-001">
                </div>
                <div class="form-group">
                    <label for="add_tree_location">Location</label>
                    <input type="text" id="add_tree_location" name="location"
                        placeholder="Optional location description">
                </div>
                <div class="form-group">
                    <label for="add_tree_lat">Latitude</label>
                    <input type="text" id="add_tree_lat" name="lat" readonly>
                </div>
                <div class="form-group">
                    <label for="add_tree_lng">Longitude</label>
                    <input type="text" id="add_tree_lng" name="lng" readonly>
                </div>
                <div id="addTreeMessage" style="margin-bottom:10px; display:none;"></div>
                <div class="modal-actions">
                    <button type="submit" id="addTreeSaveBtn" class="btn btn-primary" style="flex:1;">Save Tree</button>
                    <button type="button" onclick="closeAddTreeModal()" class="btn btn-outline"
                        style="flex:1;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Print-only QR Code Container (hidden by default) -->
    <div id="printQRContainer" style="display: none;">
        <div class="qr-print-container">
            <img id="printQRImage" src="" alt="QR Code" style="max-width: 350px; margin: 0 auto;">
            <div class="qr-print-details">
                <p class="qr-print-text"><strong>Tree ID:</strong> <span id="printTreeId"></span></p>
                <p class="qr-print-text"><strong>Location:</strong> <span id="printLocation"></span></p>
            </div>
        </div>
    </div>

    <script>
        const qrModal = document.getElementById('qrModal');

        function showQRModal(treeId, location, qrUrl) {
            const modalContent = document.getElementById('modalQRContent');
            modalContent.innerHTML = `
                <div class="qr-code-container">
                    <img src="${qrUrl}" alt="QR Code" class="qr-code-image" style="max-width: 300px; margin: 20px auto; display: block;">
                    <div style="margin: 1rem 0; padding: 1rem; background: var(--light-color); border-radius: 10px; text-align: left;">
                        <p><i class="fas fa-tag" style="color: var(--secondary-color);"></i> <strong>Tree ID:</strong> ${treeId}</p>
                        <p><i class="fas fa-map-marker-alt" style="color: var(--secondary-color);"></i> <strong>Location:</strong> ${location}</p>
                    </div>
                    <div class="modal-actions">
                        <button type="button" onclick="downloadQRCode('${treeId}', '${location}', '${qrUrl}')" class="btn btn-primary">
                            <i class="fas fa-download"></i> Download
                        </button>
                        <button type="button" onclick="printQRCode('${treeId}', '${location}', '${qrUrl}')" class="btn btn-outline">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                </div>
            `;
            qrModal.style.cssText = 'display: flex !important; position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important; width: 100vw !important; height: 100vh !important; z-index: 999999 !important; background: rgba(0, 0, 0, 0.6) !important; align-items: center !important; justify-content: center !important; backdrop-filter: blur(4px) !important;';
            qrModal.classList.add('show');
        }

        function closeQRModal() {
            if (qrModal) {
                qrModal.style.cssText = 'display: none !important;';
                qrModal.classList.remove('show');
            }
        }

        // Close on outside click
        window.addEventListener('click', function (event) {
            if (event.target === qrModal) {
                closeQRModal();
            }
        });

        function downloadQRCode(treeId, location, qrUrl) {
            const img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = function () {
                try {
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');
                    const padding = 40;
                    const textHeight = 80;
                    const qrSize = 300;

                    canvas.width = qrSize + (padding * 2);
                    canvas.height = qrSize + (padding * 2) + textHeight;

                    ctx.fillStyle = '#FFFFFF';
                    ctx.fillRect(0, 0, canvas.width, canvas.height);
                    ctx.drawImage(img, padding, padding, qrSize, qrSize);

                    ctx.fillStyle = '#000000';
                    ctx.font = 'bold 18px Arial';
                    ctx.textAlign = 'center';
                    ctx.fillText('Tree ID: ' + treeId, canvas.width / 2, padding + qrSize + 30);
                    ctx.fillText('Location: ' + location, canvas.width / 2, padding + qrSize + 60);

                    canvas.toBlob(function (blob) {
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = 'qr-' + treeId + '.png';
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                    }, 'image/png');
                } catch (e) {
                    const a = document.createElement('a');
                    a.href = qrUrl;
                    a.download = 'qr-' + treeId + '.png';
                    a.click();
                }
            };
            img.src = qrUrl;
        }

        function printQRCode(treeId, location, qrUrl) {
            document.getElementById('printTreeId').textContent = treeId;
            document.getElementById('printLocation').textContent = location;
            const printImg = document.getElementById('printQRImage');
            const printContainer = document.getElementById('printQRContainer');

            printContainer.style.display = 'block';
            printImg.onload = function () {
                setTimeout(function () {
                    window.print();
                    printContainer.style.display = 'none';
                }, 200);
            };
            printImg.src = qrUrl;
        }

        // ----- Add Tree Modal functions -----
        function showAddTreeModal(lat, lng) {
            const modal = document.getElementById('addTreeModal');
            if (!modal) return;
            document.getElementById('add_tree_lat').value = lat;
            document.getElementById('add_tree_lng').value = lng;
            document.getElementById('add_tree_id').value = '';
            document.getElementById('add_tree_location').value = 'Marked from Map';
            const msg = document.getElementById('addTreeMessage');
            msg.style.display = 'none'; msg.className = '';
            modal.classList.add('show');
        }

        function closeAddTreeModal() {
            const modal = document.getElementById('addTreeModal');
            if (!modal) return;
            modal.classList.remove('show');
        }

        async function submitAddTreeForm(e) {
            e.preventDefault();
            const id = document.getElementById('add_tree_id').value.trim();
            const lat = document.getElementById('add_tree_lat').value.trim();
            const lng = document.getElementById('add_tree_lng').value.trim();
            const saveBtn = document.getElementById('addTreeSaveBtn');
            const msg = document.getElementById('addTreeMessage');
            if (!id || !lat || !lng) {
                msg.style.display = 'block'; msg.className = 'alert alert-error'; msg.textContent = 'Please provide a unique Tree ID and ensure coordinates are present.';
                return;
            }

            saveBtn.disabled = true; saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            try {
                const resp = await fetch('add_tree.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id, lat: lat, lng: lng })
                });
                const data = await resp.json();
                if (data.success) {
                    msg.style.display = 'block'; msg.className = 'alert alert-success'; msg.textContent = data.message || 'Tree saved successfully';
                    window.showMapToast('Tree saved: ' + id, 'success');
                    // Refresh data
                    if (typeof window.updateMapMarkers === 'function') window.updateMapMarkers();
                    if (typeof window.updateDashboard === 'function') window.updateDashboard();
                    setTimeout(() => { closeAddTreeModal(); }, 900);
                } else {
                    msg.style.display = 'block'; msg.className = 'alert alert-error'; msg.textContent = data.message || 'Failed to save tree.';
                }
            } catch (err) {
                console.error(err);
                msg.style.display = 'block'; msg.className = 'alert alert-error'; msg.textContent = 'Network error while saving tree.';
            } finally {
                saveBtn.disabled = false; saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Tree';
            }
        }
    </script>
    <!-- Schedule Modal (Premium Redesign) -->
    <div id="scheduleModal" class="modal">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="sched-modal-header">
                <div class="sched-modal-icon">
                    <i class="fas fa-calendar-plus"></i>
                </div>
                <div class="sched-modal-title-box">
                    <h2>Schedule Spray Treatment</h2>
                    <p class="sched-modal-subtitle">Automate disease control or set a field maintenance plan</p>
                </div>
                <button type="button" class="sched-modal-close" onclick="closeScheduleModal()">&times;</button>
            </div>

            <!-- Modal Body -->
            <div class="sched-modal-body">
                <div class="sched-tree-badge">
                    <i class="fas fa-tree"></i> Target Tree: <span id="modalTreeId">TREE-000</span>
                </div>

                <form id="scheduleForm" onsubmit="submitScheduleForm(event)">
                    <input type="hidden" id="sched_tree_id" name="tree_id">

                    <!-- Quick Date Presets -->
                    <div style="margin-bottom: 0.4rem; font-size: 0.78rem; font-weight: 600; color: #64748B;">Quick Date
                        Presets:</div>
                    <div class="sched-presets">
                        <button type="button" class="sched-preset-btn" onclick="setPresetDate(0, event)">⚡
                            Today</button>
                        <button type="button" class="sched-preset-btn" onclick="setPresetDate(1, event)">🌅
                            Tomorrow</button>
                        <button type="button" class="sched-preset-btn" onclick="setPresetDate(15, event)">📅 +15
                            Days</button>
                        <button type="button" class="sched-preset-btn active" onclick="setPresetDate(30, event)">🗓️ +30
                            Days (Default)</button>
                    </div>

                    <div class="sched-form-row">
                        <div class="sched-form-group">
                            <label for="spray_date"><i class="fas fa-calendar-day"></i> Spray Date *</label>
                            <input type="date" id="spray_date" class="sched-input" name="spray_date" required
                                min="<?= date('Y-m-d') ?>" onchange="updateLiveSummary()">
                        </div>
                        <div class="sched-form-group">
                            <label for="spray_time"><i class="fas fa-clock"></i> Spray Time *</label>
                            <input type="time" id="spray_time" class="sched-input" name="spray_time" required
                                value="<?= DEFAULT_SPRAY_TIME ?>" onchange="updateLiveSummary()">
                        </div>
                    </div>

                    <div class="sched-form-group">
                        <label for="notes"><i class="fas fa-sticky-note"></i> Treatment Notes</label>
                        <textarea id="notes" class="sched-input" name="notes"
                            placeholder="e.g. Fungicide spray for anthracnose prevention..."></textarea>
                    </div>

                    <!-- Live Summary Banner -->
                    <div class="sched-live-summary" id="schedSummaryBox">
                        <i class="fas fa-calendar-check" style="font-size: 1.1rem; color: #166534;"></i>
                        <span id="schedSummaryText">Scheduled for <strong>Today at 08:00 AM</strong></span>
                    </div>

                    <div id="scheduleMessage" style="margin-top: 15px; margin-bottom: 0; display: none;"></div>

                    <div class="sched-modal-actions">
                        <button type="submit" id="scheduleSubmitBtn" class="btn-save-sched">
                            <i class="fas fa-paper-plane"></i> Save Schedule
                        </button>
                        <button type="button" onclick="closeScheduleModal()" class="btn-cancel-sched">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Create/Edit Farm Boundary Modal -->
    <div id="boundarySaveModal" class="modal">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="sched-modal-header" style="background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%);">
                <div class="sched-modal-icon" style="color: #a5d6a7;">
                    <i class="fas fa-vector-square"></i>
                </div>
                <div class="sched-modal-title-box">
                    <h2 id="boundaryModalTitle">Save Farm Boundary</h2>
                    <p class="sched-modal-subtitle">Define a farm section, block, or orchard zone</p>
                </div>
                <button type="button" class="sched-modal-close" onclick="closeBoundaryModal()">&times;</button>
            </div>

            <!-- Modal Body -->
            <div class="sched-modal-body">
                <form id="boundaryForm" onsubmit="submitBoundaryForm(event)">
                    <input type="hidden" id="boundary_id" name="id">

                    <div class="sched-form-group">
                        <label for="boundary_name"><i class="fas fa-tag"></i> Boundary Name *</label>
                        <input type="text" id="boundary_name" class="sched-input" name="name"
                            placeholder="e.g. North Orchard - Block A" required>
                    </div>

                    <div class="sched-form-group">
                        <label for="boundary_description"><i class="fas fa-align-left"></i> Description
                            (Optional)</label>
                        <textarea id="boundary_description" class="sched-input" name="description"
                            placeholder="e.g. Primary Carabao mango section planted 2020..."></textarea>
                    </div>

                    <div class="sched-form-group">
                        <label><i class="fas fa-palette"></i> Boundary Color</label>
                        <div style="display: flex; gap: 10px; align-items: center; margin-top: 6px;">
                            <input type="color" id="boundary_color" name="color" value="#2e7d32"
                                style="width: 45px; height: 38px; border: 1.5px solid #CBD5E1; border-radius: 8px; cursor: pointer; padding: 2px;"
                                onchange="syncColorPresets(this.value)">
                            <div style="display: flex; gap: 6px; align-items: center;" id="boundaryColorPresets">
                                <span class="color-preset" style="background:#2e7d32;"
                                    onclick="selectColorPreset('#2e7d32')"></span>
                                <span class="color-preset" style="background:#004737;"
                                    onclick="selectColorPreset('#004737')"></span>
                                <span class="color-preset" style="background:#1565c0;"
                                    onclick="selectColorPreset('#1565c0')"></span>
                                <span class="color-preset" style="background:#c62828;"
                                    onclick="selectColorPreset('#c62828')"></span>
                                <span class="color-preset" style="background:#e65100;"
                                    onclick="selectColorPreset('#e65100')"></span>
                                <span class="color-preset" style="background:#6a1b9a;"
                                    onclick="selectColorPreset('#6a1b9a')"></span>
                            </div>
                        </div>
                    </div>

                    <div id="boundaryMessage" style="margin-top: 15px; margin-bottom: 0; display: none;"></div>

                    <div class="sched-modal-actions">
                        <button type="submit" id="boundarySubmitBtn" class="btn-save-sched"
                            style="background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%);">
                            <i class="fas fa-save"></i> Save Boundary
                        </button>
                        <button type="button" onclick="closeBoundaryModal()" class="btn-cancel-sched">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function setPresetDate(days, evt) {
            const d = new Date();
            d.setDate(d.getDate() + days);
            const dateStr = d.toISOString().split('T')[0];
            const sprayDateEl = document.getElementById('spray_date');
            if (sprayDateEl) sprayDateEl.value = dateStr;

            if (evt && evt.currentTarget) {
                document.querySelectorAll('.sched-preset-btn').forEach(btn => btn.classList.remove('active'));
                evt.currentTarget.classList.add('active');
            }
            updateLiveSummary();
        }
        window.setPresetDate = setPresetDate;

        function selectTreatmentType(type, defaultNote, evt) {
            const notesEl = document.getElementById('notes');
            if (notesEl) {
                notesEl.value = defaultNote;
            }
            if (evt && evt.currentTarget) {
                document.querySelectorAll('.sched-type-pill').forEach(pill => pill.classList.remove('active'));
                evt.currentTarget.classList.add('active');
            }
            updateLiveSummary();
        }
        window.selectTreatmentType = selectTreatmentType;

        function updateLiveSummary() {
            const dateInput = document.getElementById('spray_date');
            const timeInput = document.getElementById('spray_time');
            const summaryText = document.getElementById('schedSummaryText');
            if (!dateInput || !timeInput || !summaryText) return;

            if (!dateInput.value) {
                summaryText.innerHTML = 'Please select a valid spray date';
                return;
            }

            try {
                const dateObj = new Date(dateInput.value + 'T' + (timeInput.value || '08:00'));
                const formattedDate = dateObj.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
                let [h, m] = (timeInput.value || '08:00').split(':');
                h = parseInt(h);
                const ampm = h >= 12 ? 'PM' : 'AM';
                h = h % 12 || 12;
                m = String(m).padStart(2, '0');
                const formattedTime = `${h}:${m} ${ampm}`;

                const now = new Date();
                now.setHours(0, 0, 0, 0);
                const targetDate = new Date(dateInput.value);
                targetDate.setHours(0, 0, 0, 0);
                const diffTime = targetDate - now;
                const diffDays = Math.round(diffTime / (1000 * 60 * 60 * 24));

                let relativeStr = '';
                if (diffDays === 0) relativeStr = ' (Today)';
                else if (diffDays === 1) relativeStr = ' (Tomorrow)';
                else if (diffDays > 1) relativeStr = ` (${diffDays} days from now)`;
                else if (diffDays < 0) relativeStr = ` (${Math.abs(diffDays)} days ago)`;

                summaryText.innerHTML = `Scheduled for <strong>${formattedDate} at ${formattedTime}</strong>${relativeStr}`;
            } catch (e) {
                summaryText.innerHTML = `Scheduled for <strong>${dateInput.value} ${timeInput.value}</strong>`;
            }
        }
        window.updateLiveSummary = updateLiveSummary;

        function showScheduleModal(treeId, location) {
            window._suppressMapClick = true;
            var modal = document.getElementById('scheduleModal');
            if (!modal) {
                console.error('scheduleModal element not found!');
                return;
            }

            // Force modal to top layer FIRST so it always displays, even if a sub-step below throws
            modal.style.cssText = 'display: flex !important; position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important; width: 100vw !important; height: 100vh !important; z-index: 999999 !important; background: rgba(0, 0, 0, 0.6) !important; align-items: center !important; justify-content: center !important; backdrop-filter: blur(4px) !important;';
            modal.classList.add('show');

            try {
                var formEl = document.getElementById('scheduleForm');
                if (formEl) formEl.reset();

                var treeIdEl = document.getElementById('modalTreeId');
                if (treeIdEl) treeIdEl.textContent = treeId;

                var schedTreeIdEl = document.getElementById('sched_tree_id');
                if (schedTreeIdEl) schedTreeIdEl.value = treeId;

                var sprayDateEl = document.getElementById('spray_date');
                if (sprayDateEl) {
                    var defaultSprayDate = new Date();
                    defaultSprayDate.setDate(defaultSprayDate.getDate() + 30);
                    sprayDateEl.value = defaultSprayDate.toISOString().split('T')[0];
                }

                var messageEl = document.getElementById('scheduleMessage');
                if (messageEl) messageEl.style.display = 'none';

                var submitBtn = document.getElementById('scheduleSubmitBtn');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-save"></i> Save Schedule';
                }

                if (typeof updateLiveSummary === 'function') updateLiveSummary();
            } catch (e) {
                console.error('showScheduleModal populate error:', e);
            }
        }

        // Expose to window globally
        window.showScheduleModal = showScheduleModal;

        function closeScheduleModal() {
            var modal = document.getElementById('scheduleModal');
            if (modal) {
                modal.style.cssText = 'display: none !important;';
                modal.classList.remove('show');
            }
        }
        window.closeScheduleModal = closeScheduleModal;

        function submitScheduleForm(event) {
            event.preventDefault();
            const form = event.target;
            const submitBtn = document.getElementById('scheduleSubmitBtn');
            const messageDiv = document.getElementById('scheduleMessage');

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            const formData = new FormData(form);
            fetch('save_schedule.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        messageDiv.className = 'alert alert-success';
                        messageDiv.innerHTML = `<i class="fas fa-check-circle"></i> ${data.message}`;
                        messageDiv.style.display = 'block';
                        setTimeout(() => {
                            closeScheduleModal();
                            if (typeof window.updateMapMarkers === 'function') {
                                window.updateMapMarkers();
                            }
                        }, 1200);
                    } else {
                        messageDiv.className = 'alert alert-error';
                        messageDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${data.message}`;
                        messageDiv.style.display = 'block';
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-save"></i> Save Schedule';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    messageDiv.className = 'alert alert-error';
                    messageDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> An error occurred. Please try again.';
                    messageDiv.style.display = 'block';
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-save"></i> Save Schedule';
                });
        }

        // Add additional click listener for schedule modal
        window.addEventListener('click', function (event) {
            const modal = document.getElementById('scheduleModal');
            if (modal && event.target === modal) {
                closeScheduleModal();
            }
            const bModal = document.getElementById('boundarySaveModal');
            if (bModal && event.target === bModal) {
                closeBoundaryModal();
            }
        });

        // ─── FARM BOUNDARY SYSTEM (Drawing, Loading, Editing, Telemetry) ──────
        window.farmBoundaries = [];
        window.activeDrawingOverlay = null;
        window.drawModeActive = false;
        window.activeBoundaryInfoWindow = null;
        let boundaryDrawListenersAttached = false;

        function clearDraftVisuals() {
            if (draftPolyline) { draftPolyline.setMap(null); draftPolyline = null; }
            draftMarkers.forEach(m => m.setMap(null));
            draftMarkers = [];
        }

        function destroyDraft() {
            clearDraftVisuals();
            draftCoords = [];
        }

        // Rebuild the live outline (vertex markers + connecting polyline) as the user clicks
        function redrawDraft() {
            const pts = draftCoords.slice();
            clearDraftVisuals();
            if (!window.googleMap || pts.length === 0) return;

            pts.forEach(p => {
                const marker = new google.maps.Marker({
                    position: p,
                    map: window.googleMap,
                    draggable: false,
                    icon: {
                        path: google.maps.SymbolPath.CIRCLE,
                        scale: 6,
                        fillColor: '#2e7d32',
                        fillOpacity: 1,
                        strokeColor: '#ffffff',
                        strokeWeight: 2
                    }
                });
                draftMarkers.push(marker);
            });

            draftPolyline = new google.maps.Polyline({
                path: pts.map(p => new google.maps.LatLng(p.lat, p.lng)),
                geodesic: true,
                strokeColor: '#2e7d32',
                strokeOpacity: 0.9,
                strokeWeight: 2.5,
                map: window.googleMap
            });
        }

        function updateDrawButtons(isDrawing) {
            const btnTopRight = document.getElementById('drawBoundaryBtn');
            const btnTopLeft = document.getElementById('btnDrawBoundary');
            [btnTopRight, btnTopLeft].forEach(btn => {
                if (!btn) return;
                if (isDrawing) {
                    btn.innerHTML = '<i class="fas fa-times" style="color:#ffffff;"></i> <span>Cancel Drawing</span>';
                    btn.style.background = '#c0392b';
                    btn.style.color = '#ffffff';
                } else {
                    btn.innerHTML = '<i class="fas fa-draw-polygon" style="color:#2e7d32;"></i> <span>Draw Boundary</span>';
                    btn.style.background = '#ffffff';
                    btn.style.color = '#005343';
                }
            });
        }

        window.addDraftVertex = function (latLng) {
            if (!window.drawModeActive) return;
            const lat = latLng.lat();
            const lng = latLng.lng();

            // Ignore near-duplicate clicks (a double-click emits two single clicks)
            const last = draftCoords[draftCoords.length - 1];
            if (last && Math.abs(last.lat - lat) < 1e-6 && Math.abs(last.lng - lng) < 1e-6) {
                return;
            }

            draftCoords.push({ lat: lat, lng: lng });
            redrawDraft();
        };

        window.finishBoundaryDraw = function () {
            if (!window.googleMap) return;
            if (draftCoords.length < 3) {
                if (typeof showMapToast === 'function') showMapToast('Boundary needs at least 3 points. Please click more points on the map.', 'error');
                return;
            }

            const polygon = new google.maps.Polygon({
                paths: draftCoords.map(p => new google.maps.LatLng(p.lat, p.lng)),
                strokeColor: '#2e7d32',
                strokeOpacity: 0.85,
                strokeWeight: 2.5,
                fillColor: '#2e7d32',
                fillOpacity: 0.25,
                clickable: true,
                editable: false,
                draggable: false,
                map: window.googleMap
            });

            window.activeDrawingOverlay = polygon;
            window._suppressMapClick = true;

            // Exit draw mode (keeps the finished polygon visible for the Save modal)
            window.stopBoundaryDraw();

            if (typeof openBoundaryModalForCreate === 'function') {
                openBoundaryModalForCreate(polygon);
            }
        };

        function attachDrawListeners() {
            if (boundaryDrawListenersAttached || !window.googleMap || typeof google === 'undefined') return;
            boundaryDrawListenersAttached = true;

            window.googleMap.addListener('dblclick', function (e) {
                if (window.drawModeActive) {
                    e.stop();
                    window.finishBoundaryDraw();
                }
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && window.drawModeActive) {
                    window.stopBoundaryDraw();
                    if (typeof showMapToast === 'function') showMapToast('Boundary drawing cancelled.', 'info');
                }
            });
        }

        window.startBoundaryDraw = function () {
            if (!window.googleMap) return;
            window.drawModeActive = true;
            drawModeActive = true;
            attachDrawListeners();
            window._suppressMapClick = true;
            window.googleMap.setOptions({ draggableCursor: 'crosshair', draggingCursor: 'crosshair' });
            updateDrawButtons(true);
            if (typeof showMapToast === 'function') showMapToast('✏️ Draw Mode Active: Click map points to outline boundary. Double-click to complete. Press ESC to cancel.', 'success');
        };

        window.stopBoundaryDraw = function () {
            window.drawModeActive = false;
            drawModeActive = false;
            destroyDraft();
            if (window.googleMap) {
                window.googleMap.setOptions({ draggableCursor: null, draggingCursor: null });
            }
            updateDrawButtons(false);
            window._suppressMapClick = false;
        };

        function toggleDrawBoundary() {
            if (window.drawModeActive) {
                window.stopBoundaryDraw();
                if (typeof showMapToast === 'function') showMapToast('Boundary drawing mode turned off.', 'info');
            } else {
                window.startBoundaryDraw();
            }
        }
        window.toggleDrawBoundary = toggleDrawBoundary;

        function openBoundaryModalForCreate(overlay) {
            var modal = document.getElementById('boundarySaveModal');
            var form = document.getElementById('boundaryForm');
            if (form) form.reset();

            document.getElementById('boundary_id').value = '';
            document.getElementById('boundaryModalTitle').textContent = 'Save Farm Boundary';
            document.getElementById('boundary_color').value = '#2e7d32';
            syncColorPresets('#2e7d32');

            var msg = document.getElementById('boundaryMessage');
            if (msg) msg.style.display = 'none';

            var btn = document.getElementById('boundarySubmitBtn');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save"></i> Save Boundary';
            }

            modal.style.cssText = 'display: flex !important; position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important; width: 100vw !important; height: 100vh !important; z-index: 999999 !important; background: rgba(0, 0, 0, 0.6) !important; align-items: center !important; justify-content: center !important; backdrop-filter: blur(4px) !important;';
            modal.classList.add('show');
        }

        function openBoundaryModalForEdit(id) {
            const item = window.farmBoundaries.find(b => b.id === id);
            if (!item) return;

            var modal = document.getElementById('boundarySaveModal');
            document.getElementById('boundary_id').value = item.data.id;
            document.getElementById('boundary_name').value = item.data.name || '';
            document.getElementById('boundary_description').value = item.data.description || '';
            const color = item.data.color || '#2e7d32';
            document.getElementById('boundary_color').value = color;
            syncColorPresets(color);

            document.getElementById('boundaryModalTitle').textContent = 'Edit Farm Boundary Details';
            var msg = document.getElementById('boundaryMessage');
            if (msg) msg.style.display = 'none';

            var btn = document.getElementById('boundarySubmitBtn');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save"></i> Update Details';
            }

            modal.style.cssText = 'display: flex !important; position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important; width: 100vw !important; height: 100vh !important; z-index: 999999 !important; background: rgba(0, 0, 0, 0.6) !important; align-items: center !important; justify-content: center !important; backdrop-filter: blur(4px) !important;';
            modal.classList.add('show');
        }
        window.openBoundaryModalForEdit = openBoundaryModalForEdit;

        function closeBoundaryModal() {
            var modal = document.getElementById('boundarySaveModal');
            if (modal) {
                modal.style.cssText = 'display: none !important;';
                modal.classList.remove('show');
            }
            if (window.activeDrawingOverlay && !document.getElementById('boundary_id').value) {
                window.activeDrawingOverlay.setMap(null);
                window.activeDrawingOverlay = null;
            }
            window._suppressMapClick = false;
        }
        window.closeBoundaryModal = closeBoundaryModal;

        function selectColorPreset(color) {
            document.getElementById('boundary_color').value = color;
            syncColorPresets(color);
        }
        window.selectColorPreset = selectColorPreset;

        function syncColorPresets(color) {
            document.querySelectorAll('.color-preset').forEach(el => {
                const bg = el.style.backgroundColor;
                if (color.toLowerCase() === bg.toLowerCase() || bg.includes(color.toLowerCase())) {
                    el.classList.add('active');
                } else {
                    el.classList.remove('active');
                }
            });
        }
        window.syncColorPresets = syncColorPresets;

        function submitBoundaryForm(event) {
            event.preventDefault();
            const id = document.getElementById('boundary_id').value;
            const name = document.getElementById('boundary_name').value.trim();
            const description = document.getElementById('boundary_description').value.trim();
            const color = document.getElementById('boundary_color').value;

            let coordinates = [];
            if (!id && window.activeDrawingOverlay) {
                const path = window.activeDrawingOverlay.getPath();
                for (let i = 0; i < path.getLength(); i++) {
                    const pt = path.getAt(i);
                    coordinates.push({ lat: pt.lat(), lng: pt.lng() });
                }
            } else if (id) {
                const item = window.farmBoundaries.find(b => b.id === id);
                if (item && item.data.coordinates) {
                    coordinates = item.data.coordinates;
                }
            }

            if (coordinates.length < 3) {
                alert('Invalid polygon boundary points.');
                return;
            }

            const payload = {
                id: id || ('boundary_' + Date.now()),
                name: name,
                description: description,
                color: color,
                coordinates: coordinates
            };

            const submitBtn = document.getElementById('boundarySubmitBtn');
            const messageDiv = document.getElementById('boundaryMessage');

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            fetch('farm_boundary_api.php?action=save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        messageDiv.className = 'alert alert-success';
                        messageDiv.innerHTML = `<i class="fas fa-check-circle"></i> ${data.message}`;
                        messageDiv.style.display = 'block';

                        if (window.activeDrawingOverlay) {
                            window.activeDrawingOverlay.setMap(null);
                            window.activeDrawingOverlay = null;
                        }

                        setTimeout(() => {
                            closeBoundaryModal();
                            loadFarmBoundaries();
                        }, 800);
                    } else {
                        messageDiv.className = 'alert alert-error';
                        messageDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${data.message}`;
                        messageDiv.style.display = 'block';
                        submitBtn.disabled = false;
                    }
                })
                .catch(err => {
                    console.error(err);
                    messageDiv.className = 'alert alert-error';
                    messageDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Network error saving boundary.';
                    messageDiv.style.display = 'block';
                    submitBtn.disabled = false;
                });
        }
        window.submitBoundaryForm = submitBoundaryForm;

        function loadFarmBoundaries() {
            if (!window.googleMap) return;

            fetch('farm_boundary_api.php?action=list')
                .then(res => res.json())
                .then(data => {
                    if (data.success && Array.isArray(data.boundaries)) {
                        clearFarmBoundariesFromMap();
                        data.boundaries.forEach(b => {
                            renderBoundaryOnMap(b);
                        });
                    }
                })
                .catch(err => console.error('Error loading boundaries:', err));
        }
        window.loadFarmBoundaries = loadFarmBoundaries;

        function clearFarmBoundariesFromMap() {
            if (window.farmBoundaries) {
                window.farmBoundaries.forEach(item => {
                    if (item.polygon) item.polygon.setMap(null);
                });
            }
            window.farmBoundaries = [];
        }

        function renderBoundaryOnMap(bData) {
            if (!bData.coordinates || bData.coordinates.length < 3) return;

            const coords = bData.coordinates.map(c => new google.maps.LatLng(parseFloat(c.lat), parseFloat(c.lng)));
            const color = bData.color || '#2e7d32';

            const polygon = new google.maps.Polygon({
                paths: coords,
                strokeColor: color,
                strokeOpacity: 0.85,
                strokeWeight: 2.5,
                fillColor: color,
                fillOpacity: 0.22,
                clickable: true,
                editable: false,
                draggable: false,
                map: window.googleMap
            });

            polygon._boundaryId = bData.id;
            polygon._boundaryData = bData;

            polygon.addListener('click', function (event) {
                window._suppressMapClick = true;
                openBoundaryInfoWindow(polygon, event.latLng);
            });

            window.farmBoundaries.push({
                id: bData.id,
                data: bData,
                polygon: polygon
            });
        }

        function openBoundaryInfoWindow(polygon, latLng) {
            const bData = polygon._boundaryData;
            const color = bData.color || '#2e7d32';

            let totalTreesInside = 0;
            let diseasedTreesInside = 0;
            let pendingSpraysInside = 0;

            if (window.globalTreesData) {
                Object.keys(window.globalTreesData).forEach(tId => {
                    const tree = window.globalTreesData[tId];
                    if (tree.lat && tree.lng) {
                        const treePt = new google.maps.LatLng(parseFloat(tree.lat), parseFloat(tree.lng));
                        if (google.maps.geometry.poly.containsLocation(treePt, polygon)) {
                            totalTreesInside++;
                            if ((tree.diseaseCount || 0) > 0) {
                                diseasedTreesInside++;
                            }
                            if (window.globalSchedules) {
                                const hasPending = window.globalSchedules.some(s => s.treeId === tId
                                    && String(s.status || 'pending').toLowerCase() === 'pending');
                                if (hasPending) pendingSpraysInside++;
                            }
                        }
                    }
                });
            }

            const infoContent = `
                <div style="padding:8px; min-width:230px; font-family:'Inter',sans-serif;">
                    <div style="display:flex; align-items:center; gap:8px; border-bottom:2px solid ${color}; padding-bottom:6px; margin-bottom:8px;">
                        <span style="width:14px; height:14px; border-radius:4px; background:${color}; display:inline-block;"></span>
                        <h4 style="margin:0; font-size:15px; color:#1b5e20; font-weight:700;">${bData.name}</h4>
                    </div>
                    ${bData.description ? `<p style="margin:0 0 8px 0; font-size:12px; color:#64748B;">${bData.description}</p>` : ''}
                    <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:8px 10px; margin-bottom:10px; display:flex; flex-direction:column; gap:5px; font-size:12px;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="color:#475569;"><i class="fas fa-tree" style="color:#004737; width:16px;"></i> Total Trees:</span>
                            <strong style="color:#1E293B; font-size:13px;">${totalTreesInside}</strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="color:#475569;"><i class="fas fa-virus" style="color:#dc2626; width:16px;"></i> Diseased Scans:</span>
                            <strong style="color:${diseasedTreesInside > 0 ? '#dc2626' : '#166534'}; font-size:13px;">${diseasedTreesInside}</strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="color:#475569;"><i class="fas fa-calendar-alt" style="color:#2563eb; width:16px;"></i> Upcoming Sprays:</span>
                            <strong style="color:#2563eb; font-size:13px;">${pendingSpraysInside}</strong>
                        </div>
                    </div>
                    <div style="display:flex; gap:6px;">
                        <button type="button" onmousedown="event.stopPropagation(); window._suppressMapClick=true;" onclick="event.stopPropagation(); window._suppressMapClick=true; window.toggleBoundaryGeometryEdit('${bData.id}')" class="btn btn-sm btn-secondary" style="flex:1; font-size:11px; justify-content:center; padding:4px 8px;">
                            <i class="fas fa-edit"></i> Geometry
                        </button>
                        <button type="button" onmousedown="event.stopPropagation(); window._suppressMapClick=true;" onclick="event.stopPropagation(); window._suppressMapClick=true; window.openBoundaryModalForEdit('${bData.id}')" class="btn btn-sm btn-primary" style="flex:1; font-size:11px; justify-content:center; padding:4px 8px; background:${color}; border-color:${color};">
                            <i class="fas fa-pen"></i> Details
                        </button>
                        <button type="button" onmousedown="event.stopPropagation(); window._suppressMapClick=true;" onclick="event.stopPropagation(); window._suppressMapClick=true; window.deleteFarmBoundary('${bData.id}')" class="btn btn-sm btn-danger" style="flex:1; font-size:11px; justify-content:center; padding:4px 8px;">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;

            if (window.activeBoundaryInfoWindow) {
                window.activeBoundaryInfoWindow.close();
            }
            window.activeBoundaryInfoWindow = new google.maps.InfoWindow({
                content: infoContent,
                position: latLng
            });
            window.activeBoundaryInfoWindow.open(window.googleMap);
        }

        function toggleBoundaryGeometryEdit(boundaryId) {
            const item = window.farmBoundaries.find(b => b.id === boundaryId);
            if (!item || !item.polygon) return;

            const isEditable = item.polygon.getEditable();
            item.polygon.setEditable(!isEditable);

            if (!isEditable) {
                if (typeof showMapToast === 'function') showMapToast('Boundary nodes editable. Drag points to reshape.', 'info');
                const path = item.polygon.getPath();
                const savePathFn = () => savePolygonPath(item);
                google.maps.event.addListener(path, 'set_at', savePathFn);
                google.maps.event.addListener(path, 'insert_at', savePathFn);
                google.maps.event.addListener(path, 'remove_at', savePathFn);
            } else {
                if (typeof showMapToast === 'function') showMapToast('Geometry editing locked.', 'success');
                savePolygonPath(item);
            }
        }
        window.toggleBoundaryGeometryEdit = toggleBoundaryGeometryEdit;

        function savePolygonPath(item) {
            const path = item.polygon.getPath();
            const coords = [];
            for (let i = 0; i < path.getLength(); i++) {
                const pt = path.getAt(i);
                coords.push({ lat: pt.lat(), lng: pt.lng() });
            }
            item.data.coordinates = coords;

            fetch('farm_boundary_api.php?action=save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(item.data)
            });
        }

        function deleteFarmBoundary(id) {
            if (!confirm('⚠️ Are you sure you want to delete this farm boundary?')) return;

            fetch('farm_boundary_api.php?action=delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        if (typeof showMapToast === 'function') showMapToast('Boundary deleted successfully.', 'success');
                        if (window.activeBoundaryInfoWindow) window.activeBoundaryInfoWindow.close();
                        loadFarmBoundaries();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(err => console.error(err));
        }
        window.deleteFarmBoundary = deleteFarmBoundary;
    </script>
</body>

</html>