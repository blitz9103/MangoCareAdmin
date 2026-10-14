<?php
require_once '../config/config.php';
requireLogin();

$firebase = new Firebase();

// Run auto-complete + auto-generate before loading data
processAutomaticSchedules($firebase);

$success = false;
$error = '';
$generatedTreeId = '';
$generatedLocation = '';
$qrCodeUrl = '';

// Handle QR code generation (NO automatic spray schedule)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'generate') {
        $treeId = sanitizeTreeId($_POST['tree_id'] ?? '');
        $location = trim($_POST['location'] ?? '');

        if ($treeId && $location) {
            // Check if tree already exists
            $existingTree = $firebase->get('trees/' . $treeId);
            if ($existingTree && isset($existingTree['has_qr']) && $existingTree['has_qr']) {
                $error = 'A QR code already exists for this Tree ID.';
            } else {
                $qrData = [
                    'tree_id' => $treeId,
                    'location' => $location,
                    'generated_at' => time() * 1000,
                    'generated_by' => $_SESSION['admin_username']
                ];

                $treeData = [
                    'tree_id' => $treeId,
                    'location' => $location,
                    'has_qr' => true,
                    'qr_data' => json_encode($qrData),
                    'created_at' => date('M d, Y g:i A'),
                    'generated_by' => $_SESSION['admin_username']
                ];

                // Use update instead of set to preserve existing fields like latitude/longitude
                $firebase->update('trees/' . $treeId, $treeData);
                syncSystemNotifications($firebase);

                $success = true;
                $generatedTreeId = $treeId;
                $generatedLocation = $location;
                $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode(json_encode($qrData));
            }
        } else {
            $error = 'Please fill in all required fields';
        }
    } elseif ($_POST['action'] === 'delete_qr' && isset($_POST['tree_id'])) {
        $treeId = $_POST['tree_id'];

        // Delete associated spray schedules first
        $firebase->delete('trees/' . $treeId . '/spray_schedules');

        // Delete the tree/QR code
        $result = $firebase->delete('trees/' . $treeId);
        if ($result !== false) {
            syncSystemNotifications($firebase);
            header('Location: qr-generator.php?success=QR code and all associated data deleted successfully');
            exit();
        } else {
            $error = 'Failed to delete QR code from Firebase.';
        }
    } elseif ($_POST['action'] === 'create_schedule' && isset($_POST['tree_id'])) {
        $treeId = trim($_POST['tree_id'] ?? '');
        $sprayDate = $_POST['spray_date'] ?? '';
        $sprayTime = $_POST['spray_time'] ?? '';

        if ($treeId && $sprayDate && $sprayTime) {
            // Normalize time to 24-hour format for storage.
            // If the value includes AM/PM we convert it; otherwise we assume it's already 24-hour (from the time picker).
            $time24 = $sprayTime;
            if (stripos($sprayTime, 'AM') !== false || stripos($sprayTime, 'PM') !== false) {
                $converted = convertTo24Hour($sprayTime);
                if (!empty($converted)) {
                    $time24 = $converted;
                }
            }

            $scheduleData = [
                'spray_date' => $sprayDate,
                'spray_time' => $time24,
                'notes' => $_POST['notes'] ?? '',
                'status' => 'pending',
                'created_at' => date('M d, Y g:i A'),
                'created_by' => $_SESSION['admin_username'],
                'auto_generated' => false
            ];

            $result = $firebase->push('trees/' . $treeId . '/spray_schedules', $scheduleData);
            if ($result !== false && !empty($result['name'])) {
                syncSystemNotifications($firebase);
                header('Location: qr-generator.php?success=Spray schedule created successfully');
                exit();
            } else {
                $error = 'Failed to create schedule in Firebase.';
            }
        } else {
            $error = 'Please fill in all required fields for the schedule.';
        }
    } elseif ($_POST['action'] === 'update_schedule_status' && isset($_POST['tree_id'], $_POST['schedule_id'])) {
        $treeId = $_POST['tree_id'];
        $scheduleId = $_POST['schedule_id'];
        $status = $_POST['status'] ?? 'completed';
        // #region agent log
        file_put_contents(__DIR__ . '/../.cursor/debug.log', json_encode(['sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'C', 'location' => 'qr-generator.php:100', 'message' => 'BEFORE: Marking schedule as completed', 'data' => ['treeId' => $treeId, 'scheduleId' => $scheduleId, 'status' => $status], 'timestamp' => time() * 1000]) . "\n", FILE_APPEND);
        // #endregion

        // Get schedule data before updating
        $schedule = $firebase->get('trees/' . $treeId . '/spray_schedules/' . $scheduleId);

        $update = ['status' => $status];
        if ($status === 'completed') {
            $update['completed_at'] = date('M d, Y g:i A');
        }
        $result = $firebase->update('trees/' . $treeId . '/spray_schedules/' . $scheduleId, $update);

        // Create spray_history entry when marking as completed
        if ($result !== false && $status === 'completed' && is_array($schedule)) {
            $sprayDate = $schedule['spray_date'] ?? '';
            $sprayTime = $schedule['spray_time'] ?? '';
            $sprayTimestamp = 0;
            if ($sprayDate && $sprayTime) {
                $sprayTimestamp = strtotime($sprayDate . ' ' . $sprayTime) * 1000;
            } else {
                $sprayTimestamp = time() * 1000;
            }

            $sprayHistoryEntry = [
                'tree_id' => $treeId,
                'timestamp' => $sprayTimestamp,
                'status' => 'completed',
                'notes' => $schedule['notes'] ?? '',
                'completed_at' => $update['completed_at'] ?? date('M d, Y g:i A')
            ];
            $firebase->push('spray_history', $sprayHistoryEntry);

            // Auto-generate ONE next treatment (+1 month) from the completion
            // moment, unless a future pending schedule already exists.
            ensureNextSchedule($firebase, $treeId, array_merge($schedule, $update));
            // #region agent log
            file_put_contents(__DIR__ . '/../.cursor/debug.log', json_encode(['sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'C', 'location' => 'qr-generator.php:125', 'message' => 'CREATED: spray_history entry', 'data' => ['treeId' => $treeId, 'sprayHistoryEntry' => $sprayHistoryEntry, 'sprayTimestamp' => $sprayTimestamp], 'timestamp' => time() * 1000]) . "\n", FILE_APPEND);
            // #endregion
        }

        // #region agent log
        file_put_contents(__DIR__ . '/../.cursor/debug.log', json_encode(['sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'C', 'location' => 'qr-generator.php:128', 'message' => 'AFTER: Schedule status updated and spray_history created', 'data' => ['treeId' => $treeId, 'scheduleId' => $scheduleId, 'updateResult' => $result !== false, 'sprayHistoryAfter' => $firebase->get('spray_history') ?? []], 'timestamp' => time() * 1000]) . "\n", FILE_APPEND);
        // #endregion
        if ($result !== false) {
            syncSystemNotifications($firebase);
            header('Location: qr-generator.php?success=Schedule status updated successfully');
            exit();
        } else {
            $error = 'Failed to update schedule status in Firebase.';
        }
    } elseif ($_POST['action'] === 'delete_schedule' && isset($_POST['tree_id'], $_POST['schedule_id'])) {
        $treeId = $_POST['tree_id'];
        $scheduleId = $_POST['schedule_id'];
        $result = $firebase->delete('trees/' . $treeId . '/spray_schedules/' . $scheduleId);
        if ($result !== false) {
            syncSystemNotifications($firebase);
            header('Location: qr-generator.php?success=Schedule deleted successfully');
            exit();
        } else {
            $error = 'Failed to delete schedule from Firebase.';
        }
    }
}

// Get all trees
$trees = $firebase->get('trees') ?? [];
$trees = is_array($trees) ? $trees : [];

// Remove hard-coded tree with ID "0" if it exists
if (isset($trees['0'])) {
    // Delete associated spray schedules first
    $firebase->delete('trees/0/spray_schedules');
    // Delete the tree itself
    $firebase->delete('trees/0');
    // Remove from local array
    unset($trees['0'], $trees[0]);
}

// Natural sorting for Tree IDs (tree1, tree2, ..., tree10)
ksort($trees, SORT_NATURAL | SORT_FLAG_CASE);

// Get message and pre-fill data from URL
$message = $_GET['success'] ?? $_GET['error'] ?? '';
if ($message && !$error) {
    $success = true;
}

$prefilledTreeId = $_GET['tree_id'] ?? '';
$prefilledLocation = $_GET['location'] ?? '';
$scheduleFor = $_GET['schedule_for'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code Generator - <?php echo SITE_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/design-system.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .qr-code-container {
            text-align: center;
            padding: 1.5rem;
        }

        .qr-code-image {
            max-width: 260px;
            width: 100%;
            height: auto;
            margin: 1.25rem auto;
            display: block;
            border-radius: 12px;
            box-shadow: var(--shadow-card);
            border: 3px solid #fff;
        }

        .qr-print-container {
            text-align: center;
            padding: 30px;
            background: white;
        }

        .qr-print-details {
            margin-top: 20px;
            font-family: Arial, sans-serif;
        }

        .qr-print-text {
            font-size: 18px;
            margin: 10px 0;
            color: #000;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.75rem;
        }

        @media print {
            body * {
                visibility: hidden;
            }

            #printQRContainer,
            #printQRContainer * {
                visibility: visible;
            }

            #printQRContainer {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                display: block !important;
            }

            .qr-print-container {
                padding: 50px;
                background: white;
            }
        }

        /* ── Bulk Print Styles ── */
        .qr-select-all {
            cursor: pointer;
            width: 18px;
            height: 18px;
            accent-color: #004737;
        }

        .qr-checkbox {
            cursor: pointer;
            width: 18px;
            height: 18px;
            accent-color: #004737;
        }

        .bulk-print-bar {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 9999;
            background: linear-gradient(135deg, #004737 0%, #005343 100%);
            color: #fff;
            padding: 14px 24px;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 -4px 20px rgba(0, 71, 55, 0.3);
            animation: slideUpBar 0.3s ease-out;
        }

        .bulk-print-bar.show {
            display: flex;
        }

        @keyframes slideUpBar {
            from { transform: translateY(100%); }
            to { transform: translateY(0); }
        }

        .bulk-print-bar .bulk-info {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95rem;
            font-weight: 600;
        }

        .bulk-print-bar .bulk-count {
            background: rgba(255,255,255,0.2);
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.85rem;
        }

        .bulk-print-bar .bulk-actions {
            display: flex;
            gap: 10px;
        }

        .bulk-print-bar .btn-bulk-print {
            background: #fff;
            color: #004737;
            border: none;
            padding: 10px 22px;
            border-radius: 10px;
            font-size: 0.92rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .bulk-print-bar .btn-bulk-print:hover {
            background: #e8f5e9;
            transform: translateY(-1px);
        }

        .bulk-print-bar .btn-bulk-cancel {
            background: rgba(255,255,255,0.15);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 10px 18px;
            border-radius: 10px;
            font-size: 0.92rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .bulk-print-bar .btn-bulk-cancel:hover {
            background: rgba(255,255,255,0.25);
        }

        /* Bulk print page (hidden, used only during print) */
        .bulk-print-page {
            width: 100%;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
            page-break-after: always;
            text-align: center;
            font-family: 'Inter', Arial, sans-serif;
            position: relative;
        }

        .bulk-print-page .bp-header {
            font-size: 14px;
            color: #004737;
            font-weight: 700;
            margin-bottom: 30px;
            letter-spacing: 1px;
            text-transform: uppercase;
            opacity: 0.6;
        }

        .bulk-print-page .bp-qr {
            width: 280px;
            height: 280px;
            border: 4px solid #004737;
            border-radius: 16px;
            margin-bottom: 30px;
            object-fit: contain;
            background: #fff;
        }

        .bulk-print-page .bp-info {
            margin-bottom: 20px;
        }

        .bulk-print-page .bp-tree-id {
            font-size: 26px;
            font-weight: 800;
            color: #004737;
            margin-bottom: 8px;
        }

        .bulk-print-page .bp-location {
            font-size: 16px;
            color: #555;
            margin-bottom: 6px;
        }

        .bulk-print-page .bp-divider {
            width: 60px;
            height: 3px;
            background: #004737;
            border-radius: 2px;
            margin: 15px auto;
            opacity: 0.4;
        }

        .bulk-print-page .bp-footer {
            font-size: 11px;
            color: #999;
            margin-top: auto;
            padding-top: 20px;
        }

        @media print {
            body * {
                visibility: hidden;
            }

            #bulkPrintContainer,
            #bulkPrintContainer * {
                visibility: visible;
            }

            #bulkPrintContainer {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                display: block !important;
            }

            .bulk-print-page {
                position: relative;
                min-height: 100vh;
                page-break-after: always;
                page-break-inside: avoid;
            }

            .bulk-print-page:last-child {
                page-break-after: auto;
            }

            #printQRContainer {
                display: none !important;
            }
        }

        @media (max-width: 768px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }

            .bulk-print-bar {
                flex-direction: column;
                gap: 10px;
                padding: 12px 16px;
            }

            .bulk-print-bar .bulk-actions {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>

<body>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- PAGE BANNER -->
        <div class="page-banner">
            <div class="banner-left">
                <div class="banner-icon"><i class="fas fa-qrcode"></i></div>
                <div class="banner-text">
                    <h1>QR Code Generator</h1>
                    <p>Generate and manage QR codes for mango trees, plus spray schedules</p>
                </div>
            </div>
            <div class="banner-badge" id="qrTreeCountBanner"><i class="fas fa-tree"></i> <?= count($trees); ?> Trees</div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="charts-grid">
            <!-- Generate QR Code Form -->
            <div class="data-card">
                <div class="data-card-header">
                    <span class="data-card-title"><i class="fas fa-plus-circle"></i> Generate New QR Code</span>
                </div>
                <div class="card-body">
                    <form method="POST" action="qr-generator.php">
                        <input type="hidden" name="action" value="generate">
                        <div class="form-group">
                            <label for="tree_id"><i class="fas fa-tag"></i> Tree ID *</label>
                            <input type="text" id="tree_id" name="tree_id" required
                                value="<?php echo htmlspecialchars($prefilledTreeId); ?>" placeholder="e.g., TREE-001"
                                pattern="[A-Za-z0-9\-]+">
                            <small style="color:var(--text-muted);font-size:.78rem;">Unique identifier for the mango
                                tree</small>
                        </div>
                        <div class="form-group">
                            <label for="location"><i class="fas fa-map-marker-alt"></i> Location *</label>
                            <input type="text" id="location" name="location" required
                                value="<?php echo htmlspecialchars($prefilledLocation); ?>"
                                placeholder="e.g., Farm A, Block 1">
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">
                            <i class="fas fa-qrcode"></i> Generate QR Code
                        </button>
                    </form>
                </div>
            </div>

            <!-- Display Generated QR Code -->
            <?php if ($success && isset($qrCodeUrl) && $qrCodeUrl): ?>
                <div class="card" id="generatedQRCard">
                    <div class="card-header">
                        <h2><i class="fas fa-check-circle" style="color: var(--success-color);"></i>Generated QR Code</h2>
                    </div>
                    <div class="card-body">
                        <div class="qr-code-container">
                            <img src="<?php echo htmlspecialchars($qrCodeUrl); ?>" alt="QR Code" class="qr-code-image"
                                id="generatedQRImage">
                            <div
                                style="margin: 1rem 0; padding: 1rem; background: var(--light-color); border-radius: 10px;">
                                <p><i class="fas fa-tag" style="color: var(--secondary-color);"></i> <strong>Tree
                                        ID:</strong> <span
                                        id="generatedTreeId"><?php echo htmlspecialchars($generatedTreeId); ?></span></p>
                                <p><i class="fas fa-map-marker-alt" style="color: var(--secondary-color);"></i>
                                    <strong>Location:</strong> <span
                                        id="generatedLocation"><?php echo htmlspecialchars($generatedLocation); ?></span>
                                </p>
                            </div>
                            <div class="alert alert-info" style="margin: 16px 0;">
                                <i class="fas fa-info-circle"></i>
                                <strong>Next Step:</strong> After generating the QR code, set the spray schedule manually
                                using the form below.
                            </div>
                            <div class="action-buttons" style="margin-top: 20px;">
                                <button type="button"
                                    onclick="downloadQRCode('<?php echo htmlspecialchars($generatedTreeId); ?>', '<?php echo htmlspecialchars($generatedLocation); ?>', '<?php echo htmlspecialchars($qrCodeUrl); ?>')"
                                    class="btn btn-primary">
                                    <i class="fas fa-download"></i> Download
                                </button>
                                <button type="button"
                                    onclick="printQRCode('<?php echo htmlspecialchars($generatedTreeId); ?>', '<?php echo htmlspecialchars($generatedLocation); ?>', '<?php echo htmlspecialchars($qrCodeUrl); ?>')"
                                    class="btn btn-outline">
                                    <i class="fas fa-print"></i> Print
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Existing Trees with QR Codes -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-tree"></i>Existing Trees with QR Codes</h2>
                <span class="badge badge-success" id="qrActiveCount"><i class="fas fa-qrcode"></i>
                    <?php echo count(array_filter($trees, function ($t) {
                        return isset($t['has_qr']) && $t['has_qr'];
                    })); ?>
                    active</span>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 40px; text-align: center;">
                                <input type="checkbox" class="qr-select-all" id="selectAllQR" onchange="toggleSelectAll(this)">
                            </th>
                            <th><i class="fas fa-tag"></i> Tree ID</th>
                            <th><i class="fas fa-map-marker-alt"></i> Location</th>
                            <th><i class="fas fa-calendar-alt"></i> Next Spray</th>
                            <th><i class="far fa-calendar-check"></i> Generated At</th>
                            <th><i class="fas fa-user"></i> Generated By</th>
                            <th><i class="fas fa-cog"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody id="qrTreeTableBody">
                        <?php if (empty($trees)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">
                                            <i class="fas fa-qrcode"></i>
                                        </div>
                                        <h3>No QR codes generated yet</h3>
                                        <p>Generate your first QR code using the form above</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($trees as $treeId => $tree): ?>
                                <?php if (isset($tree['has_qr']) && $tree['has_qr'] && $treeId !== '0'): ?>
                                    <?php $nextSpray = getNextSpraySchedule($firebase, $treeId, $tree['spray_schedules'] ?? []); ?>
                                    <?php
                                    $qrData = [
                                        'tree_id' => $treeId,
                                        'location' => $tree['location'] ?? '',
                                        'generated_at' => isset($tree['created_at']) && is_numeric($tree['created_at']) ? $tree['created_at'] : time() * 1000
                                    ];
                                    $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode(json_encode($qrData));
                                    ?>
                                    <tr>
                                        <td style="text-align: center; vertical-align: middle;">
                                            <input type="checkbox" class="qr-checkbox"
                                                data-tree-id="<?php echo htmlspecialchars($treeId); ?>"
                                                data-location="<?php echo htmlspecialchars($tree['location'] ?? 'N/A'); ?>"
                                                data-qr-url="<?php echo htmlspecialchars($qrUrl); ?>"
                                                onchange="updateBulkBar()">
                                        </td>
                                        <td>
                                            <span class="tree-id-badge">
                                                <i class="fas fa-tag"></i>
                                                <?php echo htmlspecialchars($treeId); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <i class="fas fa-map-marker-alt" style="color: var(--secondary-color);"></i>
                                            <?php echo htmlspecialchars($tree['location'] ?? 'N/A'); ?>
                                        </td>
                                        <td>
                                            <?php if ($nextSpray): ?>
                                                <span class="badge badge-warning">
                                                    <i class="far fa-clock"></i> <?php echo formatNextSprayDisplay($nextSpray); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge" style="background: var(--text-secondary); color: white;">
                                                    <i class="fas fa-minus"></i> No schedule
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <i class="far fa-calendar-alt" style="color: var(--secondary-color);"></i>
                                            <?php
                                            if (isset($tree['created_at'])) {
                                                if (is_numeric($tree['created_at'])) {
                                                    echo date('M d, Y g:i A', $tree['created_at'] / 1000);
                                                } else {
                                                    echo htmlspecialchars($tree['created_at']);
                                                }
                                            } else {
                                                echo 'N/A';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <i class="fas fa-user" style="color: var(--secondary-color);"></i>
                                            <?php echo htmlspecialchars($tree['generated_by'] ?? 'Admin'); ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons" style="flex-direction: column; gap: 8px;">
                                                <button type="button"
                                                    onclick="showQRModal('<?php echo htmlspecialchars($treeId); ?>', '<?php echo htmlspecialchars($tree['location'] ?? 'N/A'); ?>', '<?php echo htmlspecialchars($qrUrl); ?>')"
                                                    class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <form method="POST" action="qr-generator.php" style="display: block; margin: 0;"
                                                    onsubmit="return confirm('⚠️ Are you sure you want to delete this QR code and all its associated data? This action cannot be undone.');">
                                                    <input type="hidden" name="action" value="delete_qr">
                                                    <input type="hidden" name="tree_id"
                                                        value="<?php echo htmlspecialchars($treeId); ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" style="width: 100%;">
                                                        <i class="fas fa-trash-alt"></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Spray Schedule Management -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-calendar-alt"></i>Spray Schedule Management</h2>
                <span class="badge badge-info"><i class="fas fa-clock"></i> Philippine Time (12-hour format)</span>
            </div>
            <div class="card-body">
                <!-- Create New Schedule Form -->
                <div id="schedule-form"
                    style="margin-bottom: 30px; padding: 20px; background: var(--light-color); border-radius: 12px; border: 1px solid rgba(112, 185, 161, 0.2);">
                    <h3 style="margin-bottom: 20px; color: var(--primary-color);">
                        <i class="fas fa-plus-circle" style="color: var(--secondary-color); margin-right: 8px;"></i>
                        Create New Spray Schedule
                    </h3>
                    <form method="POST" action="qr-generator.php">
                        <input type="hidden" name="action" value="create_schedule">

                        <div class="form-group">
                            <label for="tree_choice"><i class="fas fa-tree"></i>Tree *</label>
                            <input list="tree_list" id="tree_choice" name="tree_id"
                                placeholder="Start typing Tree ID or Location..." required class="form-control"
                                autocomplete="off" value="<?php echo htmlspecialchars($scheduleFor); ?>">
                            <datalist id="tree_list">
                                <?php foreach ($trees as $tid => $t): ?>
                                    <?php if ($tid !== '0'): ?>
                                        <option value="<?php echo htmlspecialchars($tid); ?>">
                                            <?php echo htmlspecialchars($tid); ?> —
                                            <?php echo htmlspecialchars($t['location'] ?? 'N/A'); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </datalist>
                            <small><i class="fas fa-search"></i> Type to filter the list of trees</small>
                        </div>

                        <div class="form-group">
                            <label for="spray_date"><i class="fas fa-calendar-day"></i>Spray Date *</label>
                            <input type="date" id="spray_date" name="spray_date" required>
                        </div>

                        <div class="form-group">
                            <label for="spray_time"><i class="fas fa-clock"></i>Spray Time *</label>
                            <input type="time" id="spray_time" name="spray_time" required step="60"
                                value="<?php echo htmlspecialchars(DEFAULT_SPRAY_TIME); ?>">
                            <small><i class="fas fa-info-circle"></i> Use the time picker to select the spray
                                time.</small>
                        </div>

                        <div class="form-group">
                            <label for="notes"><i class="fas fa-sticky-note"></i>Notes</label>
                            <textarea id="notes" name="notes" rows="3" placeholder="Additional notes..."></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus-circle"></i> Create Schedule
                        </button>
                    </form>
                </div>

                <!-- All Schedules Table -->
                <?php
                $schedules = [];
                foreach ($trees as $treeId => $tree) {
                    if (!isset($tree['has_qr']) || !$tree['has_qr'] || $treeId === '0')
                        continue;
                    $treeSchedules = $tree['spray_schedules'] ?? [];
                    if (!is_array($treeSchedules))
                        continue;
                    foreach ($treeSchedules as $scheduleKey => $schedule) {
                        $schedule['_tree_id'] = $treeId;
                        $schedule['_schedule_key'] = $scheduleKey;
                        $schedule['location'] = $tree['location'] ?? 'N/A';
                        $schedules[] = $schedule;
                    }
                }

                // Sort by date
                usort($schedules, function ($a, $b) {
                    $dateA = strtotime(($a['spray_date'] ?? '') . ' ' . ($a['spray_time'] ?? ''));
                    $dateB = strtotime(($b['spray_date'] ?? '') . ' ' . ($b['spray_time'] ?? ''));
                    return $dateA <=> $dateB;
                });
                ?>

                <h3 style="margin-bottom: 20px; color: var(--primary-color);">
                    <i class="fas fa-list" style="color: var(--secondary-color); margin-right: 8px;"></i>
                    All Spray Schedules
                </h3>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><i class="fas fa-tag"></i> Tree ID</th>
                                <th><i class="fas fa-map-marker-alt"></i> Location</th>
                                <th><i class="fas fa-calendar-day"></i> Date</th>
                                <th><i class="fas fa-clock"></i> Time</th>
                                <th><i class="fas fa-info-circle"></i> Status</th>
                                <th><i class="fas fa-user"></i> Created By</th>
                                <th><i class="fas fa-cog"></i> Actions</th>
                            </tr>
                        </thead>
                        <tbody id="qrSchedTableBody">
                            <?php if (empty($schedules)): ?>
                                <tr>
                                    <td colspan="7">
                                        <div class="empty-state">
                                            <div class="empty-state-icon">
                                                <i class="fas fa-calendar-times"></i>
                                            </div>
                                            <h3>No schedules found</h3>
                                            <p>Create a new schedule above to get started</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($schedules as $s): ?>
                                    <?php
                                    $status = $s['status'] ?? 'pending';
                                    $badgeClass = $status === 'completed' ? 'badge-success' : ($status === 'cancelled' || $status === 'overdue' ? 'badge-danger' : 'badge-warning');
                                    $treeId = $s['_tree_id'];
                                    $scheduleKey = $s['_schedule_key'];
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="tree-id-badge">
                                                <i class="fas fa-tag"></i> <?php echo htmlspecialchars($treeId); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <i class="fas fa-map-marker-alt" style="color: var(--secondary-color);"></i>
                                            <?php echo htmlspecialchars($s['location'] ?? 'N/A'); ?>
                                        </td>
                                        <td>
                                            <i class="far fa-calendar-alt" style="color: var(--secondary-color);"></i>
                                            <?php echo isset($s['spray_date']) ? date('M d, Y', strtotime($s['spray_date'])) : 'N/A'; ?>
                                        </td>
                                        <td>
                                            <i class="far fa-clock" style="color: var(--secondary-color);"></i>
                                            <?php echo convertTo12Hour($s['spray_time'] ?? '') ?: 'N/A'; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $badgeClass; ?>">
                                                <i
                                                    class="fas fa-<?php echo $status === 'completed' ? 'check-circle' : ($status === 'cancelled' || $status === 'overdue' ? 'times-circle' : 'clock'); ?>"></i>
                                                <?php echo ucfirst($status); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <i class="fas fa-user" style="color: var(--secondary-color);"></i>
                                            <?php echo htmlspecialchars($s['created_by'] ?? 'Admin'); ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons" style="flex-direction: column; gap: 8px;">
                                                <?php if ($status !== 'completed'): ?>
                                                    <form method="POST" style="display: block; width: 100%;">
                                                        <input type="hidden" name="action" value="update_schedule_status">
                                                        <input type="hidden" name="tree_id"
                                                            value="<?php echo htmlspecialchars($treeId); ?>">
                                                        <input type="hidden" name="schedule_id"
                                                            value="<?php echo htmlspecialchars($scheduleKey); ?>">
                                                        <input type="hidden" name="status" value="completed">
                                                        <button type="submit" class="btn btn-sm btn-success" style="width: 100%;">
                                                            <i class="fas fa-check-circle"></i> Complete
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <form method="POST" style="display: block; width: 100%;"
                                                    onsubmit="return confirm('⚠️ Are you sure you want to delete this schedule?');">
                                                    <input type="hidden" name="action" value="delete_schedule">
                                                    <input type="hidden" name="tree_id"
                                                        value="<?php echo htmlspecialchars($treeId); ?>">
                                                    <input type="hidden" name="schedule_id"
                                                        value="<?php echo htmlspecialchars($scheduleKey); ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" style="width: 100%;">
                                                        <i class="fas fa-trash-alt"></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- QR Code Modal -->
    <div id="qrModal" class="modal">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <h2><i class="fas fa-qrcode"></i>Generated QR Code</h2>
            <div id="modalQRContent">
                <!-- QR code content will be inserted here -->
            </div>
        </div>
    </div>

    <!-- Bulk Print Action Bar -->
    <div class="bulk-print-bar" id="bulkPrintBar">
        <div class="bulk-info">
            <i class="fas fa-qrcode"></i>
            <span>QR Code Print</span>
            <span class="bulk-count" id="bulkSelectedCount">0 selected</span>
        </div>
        <div class="bulk-actions">
            <button type="button" class="btn-bulk-cancel" onclick="clearSelection()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="button" class="btn-bulk-print" onclick="bulkPrintSelected()">
                <i class="fas fa-print"></i> Print Selected
            </button>
        </div>
    </div>

    <!-- Bulk Print Container (hidden, for print output) -->
    <div id="bulkPrintContainer" style="display: none;"></div>

    <!-- Print-only QR Code Container (hidden by default) -->
    <div id="printQRContainer" style="display: none;">
        <div class="qr-print-container">
            <img id="printQRImage" src="" alt="QR Code" class="qr-code-image-print">
            <div class="qr-print-details">
                <p class="qr-print-text"><strong>Tree ID:</strong> <span id="printTreeId"></span></p>
                <p class="qr-print-text"><strong>Location:</strong> <span id="printLocation"></span></p>
            </div>
        </div>
    </div>

    <script type="module">
        const _fb = window.MangoFB;
        if (_fb && _fb.db) {
            const { db, ref, onValue, escHtml, to12Hour, formatDate, scheduleDateStr, badgeFor } = _fb;

            function nextSprayDisplay(tree) {
                const scheds = (tree && tree.spray_schedules) || {};
                let best = null, bestTs = Infinity;
                Object.values(scheds).forEach(s => {
                    if (!s || typeof s !== 'object') return;
                    if ((s.status || 'pending') !== 'pending') return;
                    const dateStr = s.spray_date || '';
                    const timeStr = s.spray_time || '';
                    if (!dateStr || !timeStr) return;
                    const ts = new Date(dateStr + 'T' + timeStr).getTime();
                    if (ts > Date.now() && ts < bestTs) { bestTs = ts; best = s; }
                });
                if (!best) return '';
                const d = new Date(bestTs);
                return formatDate(d, 'M j, Y') + ' at ' + to12Hour(best.spray_time || '00:00');
            }

            function renderTrees(snapshot) {
                const trees = snapshot.val() || {};
                const tbody = document.getElementById('qrTreeTableBody');
                const qrList = Object.entries(trees).filter(([treeId, tree]) =>
                    tree && tree.has_qr && treeId !== '0' && treeId !== 0 && String(treeId) !== '0'
                );

                if (tbody) {
                    if (qrList.length === 0) {
                        tbody.innerHTML = `
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <div class="empty-state-icon"><i class="fas fa-qrcode"></i></div>
                                        <h3>No QR codes generated yet</h3>
                                        <p>Generate your first QR code using the form above</p>
                                    </div>
                                </td>
                            </tr>`;
                    } else {
                        tbody.innerHTML = qrList.map(([treeId, tree]) => {
                            const treeE = escHtml(treeId);
                            const loc = escHtml(tree.location || 'N/A');
                            const next = nextSprayDisplay(tree);
                            const createdBy = escHtml(tree.generated_by || 'Admin');
                            const createdVal = tree.created_at;
                            let createdFmt = 'N/A';
                            if (createdVal != null && createdVal !== '') {
                                if (/^\d+$/.test(String(createdVal))) {
                                    createdFmt = formatDate(Number(createdVal) > 9999999999 ? Number(createdVal) : Number(createdVal) * 1000, 'M d, Y g:i A');
                                } else {
                                    createdFmt = escHtml(createdVal);
                                }
                            }
                            const qrData = {
                                tree_id: treeId,
                                location: tree.location || '',
                                generated_at: (/^\d+$/.test(String(createdVal || ''))) ? Number(createdVal) : Date.now()
                            };
                            const qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' + encodeURIComponent(JSON.stringify(qrData));
                            const nextCell = next
                                ? `<span class="badge badge-warning"><i class="far fa-clock"></i> ${escHtml(next)}</span>`
                                : `<span class="badge" style="background: var(--text-secondary); color: white;"><i class="fas fa-minus"></i> No schedule</span>`;
                            return `
                                <tr>
                                    <td style="text-align: center; vertical-align: middle;">
                                        <input type="checkbox" class="qr-checkbox"
                                            data-tree-id="${treeE}"
                                            data-location="${loc}"
                                            data-qr-url="${escHtml(qrUrl)}"
                                            onchange="updateBulkBar()">
                                    </td>
                                    <td><span class="tree-id-badge"><i class="fas fa-tag"></i> ${treeE}</span></td>
                                    <td><i class="fas fa-map-marker-alt" style="color: var(--secondary-color);"></i> ${loc}</td>
                                    <td>${nextCell}</td>
                                    <td><i class="far fa-calendar-alt" style="color: var(--secondary-color);"></i> ${createdFmt}</td>
                                    <td><i class="fas fa-user" style="color: var(--secondary-color);"></i> ${createdBy}</td>
                                    <td>
                                        <div class="action-buttons" style="flex-direction: column; gap: 8px;">
                                            <button type="button" onclick="showQRModal('${treeE}', '${loc.replace(/'/g, "\\'")}', '${escHtml(qrUrl)}')" class="btn btn-sm btn-primary">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <form method="POST" action="qr-generator.php" style="display: block; margin: 0;"
                                                onsubmit="return confirm('⚠️ Are you sure you want to delete this QR code and all its associated data? This action cannot be undone.');">
                                                <input type="hidden" name="action" value="delete_qr">
                                                <input type="hidden" name="tree_id" value="${treeE}">
                                                <button type="submit" class="btn btn-sm btn-danger" style="width: 100%;">
                                                    <i class="fas fa-trash-alt"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>`;
                        }).join('');
                    }
                }

                const activeCount = qrList.length;
                const ac = document.getElementById('qrActiveCount');
                if (ac) ac.innerHTML = `<i class="fas fa-qrcode"></i> ${activeCount} active`;
                const bc = document.getElementById('qrTreeCountBanner');
                if (bc) bc.innerHTML = `<i class="fas fa-tree"></i> ${Object.keys(trees).filter(t => t !== '0' && String(t) !== '0').length} Trees`;
            }

            function renderSchedules(snapshot) {
                const trees = snapshot.val() || {};
                const list = [];
                Object.entries(trees).forEach(([treeId, tree]) => {
                    if (!tree || treeId === '0' || treeId === 0 || String(treeId) === '0') return;
                    const treeSchedules = tree.spray_schedules || {};
                    Object.entries(treeSchedules).forEach(([scheduleKey, sched]) => {
                        if (!sched || typeof sched !== 'object') return;
                        list.push({ ...sched, _tree_id: treeId, _schedule_key: scheduleKey, location: tree.location || 'N/A' });
                    });
                });
                list.sort((a, b) => {
                    const da = (scheduleDateStr(a) || '') + ' ' + (a.spray_time || '');
                    const db2 = (scheduleDateStr(b) || '') + ' ' + (b.spray_time || '');
                    return da < db2 ? -1 : da > db2 ? 1 : 0;
                });

                const tbody = document.getElementById('qrSchedTableBody');
                if (tbody) {
                    if (list.length === 0) {
                        tbody.innerHTML = `
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <div class="empty-state-icon"><i class="fas fa-calendar-times"></i></div>
                                        <h3>No schedules found</h3>
                                        <p>Create a new schedule above to get started</p>
                                    </div>
                                </td>
                            </tr>`;
                    } else {
                        tbody.innerHTML = list.map(s => {
                            const b = badgeFor(s.status);
                            const stLow = String(s.status || 'pending').toLowerCase();
                            const stIcon = (stLow === 'completed' || stLow === 'done') ? 'check-circle'
                                : (stLow === 'overdue' || stLow === 'cancelled') ? 'times-circle' : 'clock';
                            const dateFmt = formatDate(scheduleDateStr(s), 'M d, Y') || 'N/A';
                            const timeFmt = to12Hour(s.spray_time) || 'N/A';
                            const tree = escHtml(s._tree_id);
                            const schedId = escHtml(s._schedule_key);
                            const loc = escHtml(s.location);
                            const created = escHtml(s.created_by || 'Admin');
                            const completeBtn = s.status !== 'completed' ? `
                                <form method="POST" style="display: block; width: 100%;">
                                    <input type="hidden" name="action" value="update_schedule_status">
                                    <input type="hidden" name="tree_id" value="${tree}">
                                    <input type="hidden" name="schedule_id" value="${schedId}">
                                    <input type="hidden" name="status" value="completed">
                                    <button type="submit" class="btn btn-sm btn-success" style="width: 100%;"><i class="fas fa-check-circle"></i> Complete</button>
                                </form>` : '';
                            return `
                                <tr>
                                    <td><span class="tree-id-badge"><i class="fas fa-tag"></i> ${tree}</span></td>
                                    <td><i class="fas fa-map-marker-alt" style="color: var(--secondary-color);"></i> ${loc}</td>
                                    <td><i class="far fa-calendar-alt" style="color: var(--secondary-color);"></i> ${dateFmt}</td>
                                    <td><i class="far fa-clock" style="color: var(--secondary-color);"></i> ${timeFmt}</td>
                                    <td><span class="badge ${b.cls}"><i class="fas fa-${stIcon}"></i> ${b.label}</span></td>
                                    <td><i class="fas fa-user" style="color: var(--secondary-color);"></i> ${created}</td>
                                    <td>
                                        <div class="action-buttons" style="flex-direction: column; gap: 8px;">
                                            ${completeBtn}
                                            <form method="POST" style="display: block; width: 100%;"
                                                onsubmit="return confirm('⚠️ Are you sure you want to delete this schedule?');">
                                                <input type="hidden" name="action" value="delete_schedule">
                                                <input type="hidden" name="tree_id" value="${tree}">
                                                <input type="hidden" name="schedule_id" value="${schedId}">
                                                <button type="submit" class="btn btn-sm btn-danger" style="width: 100%;"><i class="fas fa-trash-alt"></i> Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>`;
                        }).join('');
                    }
                }
            }

            onValue(ref(db, 'trees'), (snap) => { renderTrees(snap); renderSchedules(snap); });
        }
    </script>
    <script src="../assets/js/main.js"></script>
    <script>
        // QR Code Modal functionality
        const modal = document.getElementById('qrModal');
        const modalClose = document.querySelector('.modal-close');

        function showQRModal(treeId, location, qrUrl) {
            const modalContent = document.getElementById('modalQRContent');
            modalContent.innerHTML = `
                <div class="qr-code-container">
                    <img src="${qrUrl}" alt="QR Code" class="qr-code-image" style="max-width: 300px; margin: 20px auto; display: block;">
                    <div style="margin: 1rem 0; padding: 1rem; background: var(--light-color); border-radius: 10px;">
                        <p><i class="fas fa-tag" style="color: var(--secondary-color);"></i> <strong>Tree ID:</strong> ${treeId}</p>
                        <p><i class="fas fa-map-marker-alt" style="color: var(--secondary-color);"></i> <strong>Location:</strong> ${location}</p>
                    </div>
                    <div class="alert alert-info" style="margin: 16px 0; text-align: left;">
                        <i class="fas fa-info-circle"></i>
                        <strong>Next Step:</strong> After generating the QR code, set the spray schedule manually using the form below.
                    </div>
                    <div class="action-buttons" style="margin-top: 24px; justify-content: center; gap: 16px;">
                        <button type="button" onclick="downloadQRCode('${treeId}', '${location}', '${qrUrl}')" class="btn btn-primary">
                            <i class="fas fa-download"></i> Download
                        </button>
                        <button type="button" onclick="printQRCode('${treeId}', '${location}', '${qrUrl}')" class="btn btn-outline">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                </div>
            `;
            modal.style.display = 'block';
        }

        function downloadQRCode(treeId, location, qrUrl) {
            // Check if canvas is supported
            if (!document.createElement('canvas').getContext) {
                // Fallback: direct download of QR code
                const a = document.createElement('a');
                a.href = qrUrl;
                a.download = 'qr-' + treeId + '.png';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                return;
            }

            // Create a new image to load the QR code
            const img = new Image();
            img.crossOrigin = 'anonymous'; // Handle CORS if needed

            img.onload = function () {
                try {
                    // Create canvas
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');

                    if (!ctx) {
                        throw new Error('Canvas context not available');
                    }

                    // Calculate dimensions
                    const padding = 40;
                    const textHeight = 80;
                    const qrSize = Math.min(img.width, img.height, 400); // Max 400px for QR code

                    canvas.width = qrSize + (padding * 2);
                    canvas.height = qrSize + (padding * 2) + textHeight;

                    // Fill white background
                    ctx.fillStyle = '#FFFFFF';
                    ctx.fillRect(0, 0, canvas.width, canvas.height);

                    // Draw QR code (centered)
                    const qrX = (canvas.width - qrSize) / 2;
                    const qrY = padding;
                    ctx.drawImage(img, qrX, qrY, qrSize, qrSize);

                    // Draw text
                    ctx.fillStyle = '#000000';
                    ctx.font = 'bold 18px Arial';
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'top';

                    const textY = qrY + qrSize + 20;
                    ctx.fillText('Tree ID: ' + treeId, canvas.width / 2, textY);
                    ctx.fillText('Location: ' + location, canvas.width / 2, textY + 30);

                    // Convert to blob and download
                    canvas.toBlob(function (blob) {
                        if (blob) {
                            const url = URL.createObjectURL(blob);
                            const a = document.createElement('a');
                            a.href = url;
                            a.download = 'qr-' + treeId + '.png';
                            document.body.appendChild(a);
                            a.click();
                            document.body.removeChild(a);
                            URL.revokeObjectURL(url);
                        } else {
                            // Fallback if blob creation fails
                            fallbackDownload();
                        }
                    }, 'image/png');
                } catch (error) {
                    console.error('Error creating download image:', error);
                    fallbackDownload();
                }
            };

            function fallbackDownload() {
                // Fallback: direct download of QR code without text
                const a = document.createElement('a');
                a.href = qrUrl;
                a.download = 'qr-' + treeId + '.png';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
            }

            img.onerror = fallbackDownload;
            img.src = qrUrl;
        }

        function printQRCode(treeId, location, qrUrl) {
            // Set print container content
            const printContainer = document.getElementById('printQRContainer');
            const printImg = document.getElementById('printQRImage');
            const printTreeId = document.getElementById('printTreeId');
            const printLocation = document.getElementById('printLocation');

            printTreeId.textContent = treeId;
            printLocation.textContent = location;

            // Show print container
            printContainer.style.display = 'block';

            // Function to trigger print after image loads
            function triggerPrint() {
                // Ensure image is properly sized
                printImg.style.maxWidth = '350px';
                printImg.style.maxHeight = '350px';
                printImg.style.width = 'auto';
                printImg.style.height = 'auto';

                // Small delay to ensure rendering
                setTimeout(function () {
                    window.print();
                    // Hide print container after print dialog closes
                    setTimeout(function () {
                        printContainer.style.display = 'none';
                    }, 500);
                }, 200);
            }

            // Wait for image to load before printing
            if (printImg.complete && printImg.naturalWidth > 0) {
                triggerPrint();
            } else {
                printImg.onload = triggerPrint;
                printImg.onerror = function () {
                    alert('Error loading QR code image. Please try again.');
                    printContainer.style.display = 'none';
                };
                printImg.src = qrUrl;
            }
        }

        /* ── Bulk Print Functions ──────────────────────────────────── */
        function toggleSelectAll(checkbox) {
            const boxes = document.querySelectorAll('.qr-checkbox');
            boxes.forEach(cb => { cb.checked = checkbox.checked; });
            updateBulkBar();
        }

        function updateBulkBar() {
            const checked = document.querySelectorAll('.qr-checkbox:checked');
            const bar = document.getElementById('bulkPrintBar');
            const countEl = document.getElementById('bulkSelectedCount');
            if (checked.length > 0) {
                bar.classList.add('show');
                countEl.textContent = checked.length + ' selected';
            } else {
                bar.classList.remove('show');
            }
            // Keep select-all checkbox in sync
            const allBoxes = document.querySelectorAll('.qr-checkbox');
            const selectAll = document.getElementById('selectAllQR');
            if (selectAll) {
                selectAll.checked = allBoxes.length > 0 && checked.length === allBoxes.length;
            }
        }

        function clearSelection() {
            document.querySelectorAll('.qr-checkbox').forEach(cb => { cb.checked = false; });
            const selectAll = document.getElementById('selectAllQR');
            if (selectAll) selectAll.checked = false;
            updateBulkBar();
        }

        function bulkPrintSelected() {
            const checked = document.querySelectorAll('.qr-checkbox:checked');
            if (checked.length === 0) return;

            const container = document.getElementById('bulkPrintContainer');
            container.innerHTML = '';

            let loadedCount = 0;
            const totalCount = checked.length;
            const pages = [];

            checked.forEach(cb => {
                const treeId = cb.getAttribute('data-tree-id');
                const location = cb.getAttribute('data-location');
                const qrUrl = cb.getAttribute('data-qr-url');

                const page = document.createElement('div');
                page.className = 'bulk-print-page';
                page.innerHTML = `
                    <div class="bp-header">MangoCare Admin</div>
                    <img class="bp-qr" src="${qrUrl}" alt="QR Code for ${treeId}" crossorigin="anonymous">
                    <div class="bp-info">
                        <div class="bp-tree-id">${treeId}</div>
                        <div class="bp-location"><i class="fas fa-map-marker-alt" style="margin-right:4px;"></i>${location}</div>
                    </div>
                    <div class="bp-divider"></div>
                    <div class="bp-footer">MangoCare Admin System &bull; Printed ${new Date().toLocaleDateString()}</div>
                `;
                container.appendChild(page);
                pages.push({ page, img: page.querySelector('.bp-qr') });
            });

            function tryPrint() {
                const allReady = pages.every(p => p.img.complete);
                if (allReady) {
                    setTimeout(() => {
                        window.print();
                        setTimeout(() => { container.innerHTML = ''; }, 500);
                    }, 300);
                }
            }

            pages.forEach(p => {
                if (p.img.complete) {
                    loadedCount++;
                    tryPrint();
                } else {
                    p.img.onload = function () {
                        loadedCount++;
                        tryPrint();
                    };
                    p.img.onerror = function () {
                        loadedCount++;
                        p.img.alt = 'Failed to load QR';
                        tryPrint();
                    };
                }
            });

            // Fallback: print after 5 seconds even if images fail
            setTimeout(() => {
                if (container.innerHTML !== '') {
                    window.print();
                    setTimeout(() => { container.innerHTML = ''; }, 500);
                }
            }, 5000);
        }

        if (modalClose) {
            modalClose.onclick = function () {
                modal.style.display = 'none';
            };
        }

        window.onclick = function (event) {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
    };
    </script>
</body>

</html>