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

foreach ($users as $userId => $userData) {
    if (isset($userData['scanResult']) && is_array($userData['scanResult'])) {
        foreach ($userData['scanResult'] as $scanId => $scanData) {
            if (is_array($scanData)) {
                $scanData['_user_id'] = $userId;
                $scans[] = $scanData;
            }
        }
    }
}

/* =====================================================
   GET TREES
===================================================== */

$trees = $firebase->get('trees') ?? [];
$trees = is_array($trees) ? $trees : [];

/* Remove hard-coded tree with ID "0" */
if (isset($trees['0'])) {
    $firebase->delete('trees/0');
    unset($trees['0']);
}
if (isset($trees[0])) {
    $firebase->delete('trees/0');
    unset($trees[0]);
}

// Natural sorting for Tree IDs (tree1, tree2, ..., tree10)
ksort($trees, SORT_NATURAL | SORT_FLAG_CASE);

/* =====================================================
   GET SPRAY HISTORY
===================================================== */

$sprayHistory = $firebase->get('spray_history') ?? [];
$sprayHistory = is_array($sprayHistory) ? $sprayHistory : [];

/* =====================================================
   GET ALL SPRAY SCHEDULES FROM TREES
===================================================== */

$schedules = [];

foreach ($trees as $treeId => $tree) {
    if (isset($tree['spray_schedules']) && is_array($tree['spray_schedules'])) {
        foreach ($tree['spray_schedules'] as $scheduleId => $scheduleData) {
            $schedules[] = $scheduleData;
        }
    }
}

/* =====================================================
   CALCULATE STATISTICS
===================================================== */

$totalScans = count($scans);
$totalTrees = count($trees);
$totalSchedules = count($schedules);

$completedSprays = count(array_filter($sprayHistory, function ($spray) {
    return isset($spray['status']) && $spray['status'] === 'completed';
}));

/* =====================================================
   LOCATION HELPER (case-insensitive tree lookup +
   placeholder fallback)
===================================================== */

function resolveScanLocation($scan, $trees)
{
    $treeId = $scan['tree_id'] ?? $scan['treeId'] ?? $scan['treeID'] ?? $scan['tree'] ?? null;
    $location = isset($scan['location']) ? trim($scan['location']) : '';
    $placeholders = ['', 'scan result', 'camera scan', 'n/a', 'na', 'none', 'unknown'];

    if (empty($treeId))
        return $location;

    $searchKey = strtolower((string) $treeId);
    $matchedId = null;
    foreach ($trees as $tId => $tData) {
        if (strtolower((string) $tId) === $searchKey) {
            $matchedId = $tId;
            break;
        }
    }

    if ($matchedId !== null && is_array($trees[$matchedId]) && !empty($trees[$matchedId]['location'])) {
        if (in_array(strtolower($location), $placeholders, true)) {
            $location = $trees[$matchedId]['location'];
        }
    }

    return $location;
}

/* =====================================================
   DISEASE STATISTICS (FIXED EXTRACTION & NORMALIZATION)
===================================================== */

$diseaseStats = [];

foreach ($scans as $scan) {
    // Extract disease name from all possible keys used across Android & Web
    $rawDisease = '';
    foreach (['disease', 'diseaseName', 'disease_final', 'final_disease', 'predicted_disease'] as $field) {
        if (!empty($scan[$field])) {
            $rawDisease = trim($scan[$field]);
            break;
        }
    }

    if ($rawDisease === '')
        continue;

    // Normalize casing (e.g. "anthracnose" -> "Anthracnose")
    $disease = ucwords(strtolower($rawDisease));

    // Extract tree ID safely
    $treeId = $scan['tree_id'] ?? $scan['treeId'] ?? $scan['treeID'] ?? $scan['tree'] ?? null;

    // Extract location with case-insensitive fallback to trees node
    $location = resolveScanLocation($scan, $trees);

    if (!isset($diseaseStats[$disease])) {
        $diseaseStats[$disease] = [
            'count' => 0,
            'trees' => [],
            'locations' => []
        ];
    }

    $diseaseStats[$disease]['count']++;

    if (!empty($treeId)) {
        $diseaseStats[$disease]['trees'][$treeId] = true;
    }

    if (!empty($location)) {
        $diseaseStats[$disease]['locations'][$location] = true;
    }
}

// Sort disease stats by count descending
uasort($diseaseStats, function ($a, $b) {
    return $b['count'] - $a['count'];
});

/* =====================================================
   LOCATION STATISTICS (FIXED EXTRACTION)
===================================================== */

$locationStats = [];

foreach ($scans as $scan) {
    $treeId = $scan['tree_id'] ?? $scan['treeId'] ?? $scan['treeID'] ?? $scan['tree'] ?? null;
    $location = resolveScanLocation($scan, $trees);

    if (empty($location))
        continue;

    // Extract disease name
    $rawDisease = '';
    foreach (['disease', 'diseaseName', 'disease_final', 'final_disease', 'predicted_disease'] as $field) {
        if (!empty($scan[$field])) {
            $rawDisease = trim($scan[$field]);
            break;
        }
    }
    $disease = !empty($rawDisease) ? ucwords(strtolower($rawDisease)) : '';

    if (!isset($locationStats[$location])) {
        $locationStats[$location] = [
            'scans' => 0,
            'diseases' => [],
            'trees' => []
        ];
    }

    $locationStats[$location]['scans']++;

    if (!empty($disease)) {
        $locationStats[$location]['diseases'][$disease] = true;
    }

    if (!empty($treeId)) {
        $locationStats[$location]['trees'][$treeId] = true;
    }
}

// Sort location stats by scan count descending
uasort($locationStats, function ($a, $b) {
    return $b['scans'] - $a['scans'];
});

/* =====================================================
   RECENT SCANS (LAST 30 DAYS)
===================================================== */

$recentScans = array_filter($scans, function ($scan) {

    if (!isset($scan['timestamp']))
        return false;

    $scanTime = (int) $scan['timestamp'];
    if ($scanTime > 100000000000)
        $scanTime /= 1000;

    return (time() - $scanTime) < (30 * 24 * 60 * 60);
});
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - <?php echo SITE_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/design-system.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .percentage-badge {
            display: inline-block;
            padding: .25rem .5rem;
            border-radius: 20px;
            font-size: .85rem;
            font-weight: 600;
            background: var(--gradient-secondary);
            color: #fff;
        }

        .recent-activity-card {
            background: linear-gradient(135deg, rgba(112, 185, 161, .1) 0%, rgba(0, 71, 55, .05) 100%);
            border-radius: 12px;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            border: 1px solid rgba(112, 185, 161, .3);
            margin-bottom: 1.25rem;
        }

        .recent-activity-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--gradient-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: #fff;
            flex-shrink: 0;
        }

        .recent-activity-content h4 {
            color: var(--primary);
            margin-bottom: .25rem;
            font-size: 1.1rem;
        }

        .recent-activity-content strong {
            color: var(--primary);
            font-size: 1.5rem;
            margin-right: .25rem;
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
                <div class="banner-icon"><i class="fas fa-chart-bar"></i></div>
                <div class="banner-text">
                    <h1>Reports & Analytics</h1>
                    <p>Disease analysis, location stats and spray schedule overview</p>
                </div>
            </div>
            <div class="banner-badge" id="reportsBannerScans"><i class="fas fa-microscope"></i> <?= $totalScans; ?>
                Scans</div>
        </div>

        <!-- Summary Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon-wrapper">
                    <i class="fas fa-microscope"></i>
                </div>
                <div class="stat-info">
                    <h3 id="repStatScans"><?php echo $totalScans; ?></h3>
                    <p><i class="fas fa-chart-line"></i> Total Scans</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-wrapper">
                    <i class="fas fa-tree"></i>
                </div>
                <div class="stat-info">
                    <h3 id="repStatTrees"><?php echo $totalTrees; ?></h3>
                    <p><i class="fas fa-map-marker-alt"></i> Registered Trees</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-wrapper">
                    <i class="fas fa-virus"></i>
                </div>
                <div class="stat-info">
                    <h3 id="repStatDiseases"><?php echo count($diseaseStats); ?></h3>
                    <p><i class="fas fa-biohazard"></i> Disease Types</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-wrapper">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3 id="repStatSprays"><?php echo $completedSprays; ?></h3>
                    <p><i class="fas fa-tint"></i> Completed Sprays</p>
                </div>
            </div>
        </div>

        <!-- Disease Analysis -->
        <div class="data-card">
            <div class="data-card-header">
                <span class="data-card-title"><i class="fas fa-virus"></i> Disease Analysis</span>
                <span class="data-card-count" id="repDiseaseCount"><?= count($diseaseStats); ?> types</span>
            </div>

            <?php if (!empty($diseaseStats)): ?>
                <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border);">
                    <div style="height: 240px; position: relative;">
                        <canvas id="diseaseReportChart"></canvas>
                    </div>
                </div>
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        const ctx = document.getElementById('diseaseReportChart');
                        if (ctx) {
                            new Chart(ctx, {
                                type: 'bar',
                                data: {
                                    labels: <?= json_encode(array_keys($diseaseStats)); ?>,
                                    datasets: [{
                                        label: 'Occurrences',
                                        data: <?= json_encode(array_column(array_values($diseaseStats), 'count')); ?>,
                                        backgroundColor: '#005343',
                                        borderRadius: 6
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: { display: false }
                                    },
                                    scales: {
                                        y: { beginAtZero: true, ticks: { precision: 0 } }
                                    }
                                }
                            });
                            window.reportChart = window.reportChart || Chart.getChart(ctx);
                        }
                    });
                </script>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-disease"></i> Disease Type</th>
                            <th><i class="fas fa-hashtag"></i> Occurrences</th>
                            <th><i class="fas fa-tree"></i> Affected Trees</th>
                            <th><i class="fas fa-map-marker-alt"></i> Locations</th>
                            <th><i class="fas fa-percent"></i> Percentage</th>
                        </tr>
                    </thead>
                    <tbody id="repDiseaseTbody">
                        <?php if (empty($diseaseStats)): ?>
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">
                                            <i class="fas fa-virus-slash"></i>
                                        </div>
                                        <h3>No disease data available</h3>
                                        <p>Scans will appear here once diseases are detected</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php
                            foreach ($diseaseStats as $disease => $stats):
                                $percentage = $totalScans > 0 ? ($stats['count'] / $totalScans) * 100 : 0;
                                ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <i class="fas fa-virus"
                                                style="color: var(--secondary-color); margin-right: 5px;"></i>
                                            <?php echo htmlspecialchars($disease); ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <span class="badge badge-warning">
                                            <i class="fas fa-hashtag"></i> <?php echo $stats['count']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <i class="fas fa-tree" style="color: var(--secondary-color); margin-right: 5px;"></i>
                                        <?php echo count($stats['trees']); ?>
                                    </td>
                                    <td>
                                        <i class="fas fa-map-marker-alt"
                                            style="color: var(--secondary-color); margin-right: 5px;"></i>
                                        <?php echo count($stats['locations']); ?>
                                    </td>
                                    <td>
                                        <span class="percentage-badge">
                                            <?php echo number_format($percentage, 2); ?>%
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Location Analysis -->
        <div class="data-card">
            <div class="data-card-header">
                <span class="data-card-title"><i class="fas fa-map-marked-alt"></i> Location Analysis</span>
                <span class="data-card-count" id="repLocationCount"><?= count($locationStats); ?> locations</span>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-map-pin"></i> Location</th>
                            <th><i class="fas fa-microscope"></i> Total Scans</th>
                            <th><i class="fas fa-tree"></i> Unique Trees</th>
                            <th><i class="fas fa-virus"></i> Disease Types</th>
                        </tr>
                    </thead>
                    <tbody id="repLocationTbody">
                        <?php if (empty($locationStats)): ?>
                            <tr>
                                <td colspan="4">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">
                                            <i class="fas fa-map-marked-alt"></i>
                                        </div>
                                        <h3>No location data available</h3>
                                        <p>Location information will appear here once scans are recorded</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php
                            foreach ($locationStats as $location => $stats):
                                ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <i class="fas fa-map-marker-alt"
                                                style="color: var(--secondary-color); margin-right: 5px;"></i>
                                            <?php echo htmlspecialchars($location); ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <span class="badge badge-warning">
                                            <i class="fas fa-hashtag"></i> <?php echo $stats['scans']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <i class="fas fa-tree" style="color: var(--secondary-color); margin-right: 5px;"></i>
                                        <?php echo count($stats['trees']); ?>
                                    </td>
                                    <td>
                                        <i class="fas fa-virus" style="color: var(--secondary-color); margin-right: 5px;"></i>
                                        <?php echo count($stats['diseases']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Spray Schedule Status -->
        <div class="data-card">
            <div class="data-card-header">
                <span class="data-card-title"><i class="fas fa-calendar-alt"></i> Spray Schedule Status</span>
            </div>
            <div class="card-body">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon-wrapper">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-info">
                            <h3 id="repSchedTotal"><?php echo $totalSchedules; ?></h3>
                            <p><i class="fas fa-list"></i> Total Schedules</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon-wrapper" style="background: var(--success-color);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3 id="repSchedCompleted">
                                <?php echo count(array_filter($schedules, function ($s) {
                                    return ($s['status'] ?? 'pending') === 'completed';
                                })); ?>
                            </h3>
                            <p><i class="fas fa-check"></i> Completed</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon-wrapper" style="background: var(--warning-color);">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <h3 id="repSchedPending">
                                <?php echo count(array_filter($schedules, function ($s) {
                                    return ($s['status'] ?? 'pending') === 'pending';
                                })); ?>
                            </h3>
                            <p><i class="fas fa-hourglass-half"></i> Pending</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon-wrapper" style="background: var(--danger-color);">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-info">
                            <h3 id="repSchedOverdue">
                                <?php echo count(array_filter($schedules, function ($s) {
                                    return ($s['status'] ?? 'pending') === 'overdue';
                                })); ?>
                            </h3>
                            <p><i class="fas fa-calendar-times"></i> Overdue</p>
                        </div>
                    </div>
                </div>

                <!-- Schedule Completion Progress -->
                <div id="repCompletionBox" style="display:<?php echo $totalSchedules > 0 ? 'block' : 'none'; ?>;">
                    <?php if ($totalSchedules > 0): ?>
                        <?php
                        $completedCount = count(array_filter($schedules, function ($s) {
                            return ($s['status'] ?? 'pending') === 'completed';
                        }));
                        $completionRate = ($completedCount / $totalSchedules) * 100;
                        ?>
                        <div style="margin-top: 20px; padding: 20px; background: var(--light-color); border-radius: 12px;">
                            <div
                                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                <h4 style="color: var(--primary-color);">
                                    <i class="fas fa-chart-line"
                                        style="color: var(--secondary-color); margin-right: 5px;"></i>
                                    Overall Completion Rate
                                </h4>
                                <span style="font-size: 1.25rem; font-weight: 700; color: var(--primary-color);"
                                    id="repCompletionPct">
                                    <?php echo number_format($completionRate, 1); ?>%
                                </span>
                            </div>
                            <div style="background: #e9ecef; height: 10px; border-radius: 5px; overflow: hidden;">
                                <div id="repCompletionBar"
                                    style="background: var(--success-color); height: 100%; width: <?php echo min($completionRate, 100); ?>%; transition: width 0.5s;">
                                </div>
                            </div>
                            <p style="margin-top: 12px; color: var(--text-secondary);" id="repCompletionText">
                                <i class="fas fa-info-circle"></i>
                                <?php echo $completedCount; ?> of <?php echo $totalSchedules; ?> schedules completed
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-history"></i>Recent Activity</h2>
                <span class="badge badge-info"><i class="far fa-clock"></i> Last 30 Days</span>
            </div>
            <div class="card-body">
                <div class="recent-activity-card">
                    <div class="recent-activity-icon">
                        <i class="fas fa-microscope"></i>
                    </div>
                    <div class="recent-activity-content">
                        <h4><i class="fas fa-chart-line" style="color: var(--secondary-color);"></i> Recent Scans</h4>
                        <p>
                            <strong id="repRecentScans"><?php echo count($recentScans); ?></strong> scans recorded in
                            the last 30 days
                        </p>
                        <?php if (count($recentScans) > 0): ?>
                            <div style="margin-top: 10px;">
                                <span class="badge badge-success">
                                    <i class="fas fa-arrow-up"></i>
                                    <?php echo number_format((count($recentScans) / max(1, $totalScans)) * 100, 1); ?>% of
                                    total scans
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (count($recentScans) > 0): ?>
                    <div
                        style="margin-top: 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <div
                            style="background: var(--light-color); padding: 15px; border-radius: 10px; text-align: center;">
                            <div style="font-size: 24px; color: var(--secondary-color); margin-bottom: 5px;">
                                <i class="fas fa-calendar-day"></i>
                            </div>
                            <div style="font-size: 1.5rem; font-weight: 700; color: var(--primary-color);"
                                id="repTodayScans">
                                <?php
                                $today = date('Y-m-d');
                                $todayScans = count(array_filter($recentScans, function ($scan) use ($today) {
                                    if (!isset($scan['timestamp']))
                                        return false;
                                    $scanTime = (int) $scan['timestamp'];
                                    if ($scanTime > 100000000000)
                                        $scanTime /= 1000;
                                    $scanDate = date('Y-m-d', $scanTime);
                                    return $scanDate === $today;
                                }));
                                echo $todayScans;
                                ?>
                            </div>
                            <div style="color: var(--text-secondary);">Today</div>
                        </div>

                        <div
                            style="background: var(--light-color); padding: 15px; border-radius: 10px; text-align: center;">
                            <div style="font-size: 24px; color: var(--secondary-color); margin-bottom: 5px;">
                                <i class="fas fa-calendar-week"></i>
                            </div>
                            <div style="font-size: 1.5rem; font-weight: 700; color: var(--primary-color);"
                                id="repWeekScans">
                                <?php
                                $weekAgo = time() - (7 * 24 * 60 * 60);
                                $weekScans = count(array_filter($recentScans, function ($scan) use ($weekAgo) {
                                    if (!isset($scan['timestamp']))
                                        return false;
                                    $scanTime = (int) $scan['timestamp'];
                                    if ($scanTime > 100000000000)
                                        $scanTime /= 1000;
                                    return $scanTime >= $weekAgo;
                                }));
                                echo $weekScans;
                                ?>
                            </div>
                            <div style="color: var(--text-secondary);">This Week</div>
                        </div>

                        <div
                            style="background: var(--light-color); padding: 15px; border-radius: 10px; text-align: center;">
                            <div style="font-size: 24px; color: var(--secondary-color); margin-bottom: 5px;">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div style="font-size: 1.5rem; font-weight: 700; color: var(--primary-color);"
                                id="repMonthScans">
                                <?php
                                $monthAgo = time() - (30 * 24 * 60 * 60);
                                $monthScans = count(array_filter($scans, function ($scan) use ($monthAgo) {
                                    if (!isset($scan['timestamp']))
                                        return false;
                                    $scanTime = (int) $scan['timestamp'];
                                    if ($scanTime > 100000000000)
                                        $scanTime /= 1000;
                                    return $scanTime >= $monthAgo;
                                }));
                                echo $monthScans;
                                ?>
                            </div>
                            <div style="color: var(--text-secondary);">This Month</div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script type="module">
        const _fb = window.MangoFB;
        if (_fb && _fb.db) {
            const { db, ref, onValue, escHtml } = _fb;

            function ucwords(s) {
                return String(s).toLowerCase().replace(/\b\w/g, c => c.toUpperCase());
            }

            function extractDisease(scan) {
                const fields = ['disease', 'diseaseName', 'disease_final', 'final_disease', 'predicted_disease'];
                for (const f of fields) {
                    if (scan[f]) return String(scan[f]).trim();
                }
                return '';
            }

            function extractTreeId(scan) {
                return scan.tree_id || scan.treeId || scan.treeID || scan.tree || null;
            }

            function resolveLocation(scan, trees) {
                const placeholders = ['', 'scan result', 'camera scan', 'n/a', 'na', 'none', 'unknown'];
                let location = (scan.location || '').trim();
                if (placeholders.indexOf(location.toLowerCase()) === -1) return location;
                const treeId = extractTreeId(scan);
                if (!treeId) return location;
                const key = String(treeId).toLowerCase();
                const match = Object.keys(trees).find(k => String(k).toLowerCase() === key);
                if (match && trees[match] && trees[match].location) return trees[match].location;
                return location;
            }

            function computeScans(usersData) {
                const scans = [];
                Object.entries(usersData || {}).forEach(([userId, user]) => {
                    if (!user || typeof user !== 'object' || !user.scanResult) return;
                    Object.entries(user.scanResult).forEach(([scanId, scan]) => {
                        if (scan && typeof scan === 'object') scans.push({ ...scan, _user_id: userId });
                    });
                });
                return scans;
            }

            function buildDiseaseStats(scans, trees) {
                const stats = {};
                scans.forEach(scan => {
                    const raw = extractDisease(scan);
                    if (!raw) return;
                    const disease = ucwords(raw);
                    const treeId = extractTreeId(scan);
                    const location = resolveLocation(scan, trees);
                    if (!stats[disease]) stats[disease] = { count: 0, trees: {}, locations: {} };
                    stats[disease].count++;
                    if (treeId) stats[disease].trees[treeId] = true;
                    if (location) stats[disease].locations[location] = true;
                });
                return stats;
            }

            function buildLocationStats(scans, trees) {
                const stats = {};
                scans.forEach(scan => {
                    const treeId = extractTreeId(scan);
                    const location = resolveLocation(scan, trees);
                    if (!location) return;
                    const disease = ucwords(extractDisease(scan));
                    if (!stats[location]) stats[location] = { scans: 0, diseases: {}, trees: {} };
                    stats[location].scans++;
                    if (disease) stats[location].diseases[disease] = true;
                    if (treeId) stats[location].trees[treeId] = true;
                });
                return stats;
            }

            function flattenSchedules(trees) {
                const list = [];
                Object.entries(trees || {}).forEach(([treeId, tree]) => {
                    if (!tree || treeId === '0' || treeId === 0 || String(treeId) === '0') return;
                    const treeSchedules = tree.spray_schedules || {};
                    Object.entries(treeSchedules).forEach(([scheduleKey, sched]) => {
                        if (sched && typeof sched === 'object') list.push(sched);
                    });
                });
                return list;
            }

            function renderReports(treesSnap, usersSnap, historySnap) {
                const trees = treesSnap.val() || {};
                const users = usersSnap.val() || {};
                const history = historySnap.val() || {};
                const scans = computeScans(users);
                const diseaseStats = buildDiseaseStats(scans, trees);
                const locationStats = buildLocationStats(scans, trees);
                const schedules = flattenSchedules(trees);

                const totalScans = scans.length;
                const totalTrees = Object.keys(trees).filter(t => t !== '0' && String(t) !== '0').length;
                const completedSprays = Object.values(history).filter(s => s && s.status === 'completed').length;
                const totalSchedules = schedules.length;
                const completedSched = schedules.filter(s => (s.status || 'pending') === 'completed').length;
                const pendingSched = schedules.filter(s => (s.status || 'pending') === 'pending').length;
                const overdueSched = schedules.filter(s => (s.status || 'pending') === 'overdue').length;

                const setText = (id, txt) => { const el = document.getElementById(id); if (el) el.textContent = txt; };
                setText('repStatScans', totalScans);
                setText('repStatTrees', totalTrees);
                setText('repStatDiseases', Object.keys(diseaseStats).length);
                setText('repStatSprays', completedSprays);
                setText('repSchedTotal', totalSchedules);
                setText('repSchedCompleted', completedSched);
                setText('repSchedPending', pendingSched);
                setText('repSchedOverdue', overdueSched);
                const banner = document.getElementById('reportsBannerScans');
                if (banner) banner.innerHTML = `<i class="fas fa-microscope"></i> ${totalScans} Scans`;

                // Disease chart
                if (window.reportChart) {
                    window.reportChart.data.labels = Object.keys(diseaseStats);
                    window.reportChart.data.datasets[0].data = Object.values(diseaseStats).map(d => d.count);
                    window.reportChart.update();
                }

                // Disease table
                const dtb = document.getElementById('repDiseaseTbody');
                if (dtb) {
                    const dc = document.getElementById('repDiseaseCount');
                    if (dc) dc.textContent = `${Object.keys(diseaseStats).length} types`;
                    if (Object.keys(diseaseStats).length === 0) {
                        dtb.innerHTML = `
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <div class="empty-state-icon"><i class="fas fa-virus-slash"></i></div>
                                        <h3>No disease data available</h3>
                                        <p>Scans will appear here once diseases are detected</p>
                                    </div>
                                </td>
                            </tr>`;
                    } else {
                        const sorted = Object.entries(diseaseStats).sort((a, b) => b[1].count - a[1].count);
                        dtb.innerHTML = sorted.map(([disease, stats]) => {
                            const pct = totalScans > 0 ? (stats.count / totalScans) * 100 : 0;
                            return `
                                <tr>
                                    <td><strong><i class="fas fa-virus" style="color: var(--secondary-color); margin-right: 5px;"></i> ${escHtml(disease)}</strong></td>
                                    <td><span class="badge badge-warning"><i class="fas fa-hashtag"></i> ${stats.count}</span></td>
                                    <td><i class="fas fa-tree" style="color: var(--secondary-color); margin-right: 5px;"></i> ${Object.keys(stats.trees).length}</td>
                                    <td><i class="fas fa-map-marker-alt" style="color: var(--secondary-color); margin-right: 5px;"></i> ${Object.keys(stats.locations).length}</td>
                                    <td><span class="percentage-badge">${pct.toFixed(2)}%</span></td>
                                </tr>`;
                        }).join('');
                    }
                }

                // Location table
                const ltb = document.getElementById('repLocationTbody');
                if (ltb) {
                    const lc = document.getElementById('repLocationCount');
                    if (lc) lc.textContent = `${Object.keys(locationStats).length} locations`;
                    if (Object.keys(locationStats).length === 0) {
                        ltb.innerHTML = `
                            <tr>
                                <td colspan="4">
                                    <div class="empty-state">
                                        <div class="empty-state-icon"><i class="fas fa-map-marked-alt"></i></div>
                                        <h3>No location data available</h3>
                                        <p>Location information will appear here once scans are recorded</p>
                                    </div>
                                </td>
                            </tr>`;
                    } else {
                        const sorted = Object.entries(locationStats).sort((a, b) => b[1].scans - a[1].scans);
                        ltb.innerHTML = sorted.map(([location, stats]) => `
                            <tr>
                                <td><strong><i class="fas fa-map-marker-alt" style="color: var(--secondary-color); margin-right: 5px;"></i> ${escHtml(location)}</strong></td>
                                <td><span class="badge badge-warning"><i class="fas fa-hashtag"></i> ${stats.scans}</span></td>
                                <td><i class="fas fa-tree" style="color: var(--secondary-color); margin-right: 5px;"></i> ${Object.keys(stats.trees).length}</td>
                                <td><i class="fas fa-virus" style="color: var(--secondary-color); margin-right: 5px;"></i> ${Object.keys(stats.diseases).length}</td>
                            </tr>`).join('');
                    }
                }

                // Completion rate
                const compBox = document.getElementById('repCompletionBox');
                if (compBox) {
                    if (totalSchedules > 0) {
                        compBox.style.display = 'block';
                        const rate = (completedSched / totalSchedules) * 100;
                        const pctEl = document.getElementById('repCompletionPct');
                        const barEl = document.getElementById('repCompletionBar');
                        const txtEl = document.getElementById('repCompletionText');
                        if (pctEl) pctEl.textContent = rate.toFixed(1) + '%';
                        if (barEl) barEl.style.width = Math.min(rate, 100) + '%';
                        if (txtEl) txtEl.innerHTML = `<i class="fas fa-info-circle"></i> ${completedSched} of ${totalSchedules} schedules completed`;
                    } else {
                        compBox.style.display = 'none';
                    }
                }

                // Recent activity
                const tsMs = v => { const t = Number(v); return t > 0 && t < 100000000000 ? t * 1000 : t; };
                const now = Date.now();
                const recents = scans.filter(s => s.timestamp && (now - tsMs(s.timestamp)) < 30 * 24 * 60 * 60 * 1000);
                setText('repRecentScans', recents.length);
                const todayStr = (() => { const d = new Date(); const p = n => String(n).padStart(2, '0'); return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`; })();
                const todayCount = recents.filter(s => { const d = new Date(tsMs(s.timestamp)); const p = n => String(n).padStart(2, '0'); return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}` === todayStr; }).length;
                setText('repTodayScans', todayCount);
                setText('repWeekScans', recents.filter(s => (now - tsMs(s.timestamp)) <= 7 * 24 * 60 * 60 * 1000).length);
                setText('repMonthScans', scans.filter(s => s.timestamp && (now - tsMs(s.timestamp)) <= 30 * 24 * 60 * 60 * 1000).length);
            }

            const _snaps = { trees: null, users: null, history: null };
            function maybeRender() {
                if (_snaps.trees && _snaps.users && _snaps.history) {
                    renderReports(_snaps.trees, _snaps.users, _snaps.history);
                }
            }
            onValue(ref(db, 'trees'), (s) => { _snaps.trees = s; maybeRender(); });
            onValue(ref(db, 'Users'), (s) => { _snaps.users = s; maybeRender(); });
            onValue(ref(db, 'spray_history'), (s) => { _snaps.history = s; maybeRender(); });
        }
    </script>
    <script src="../assets/js/main.js"></script>


</body>

</html>