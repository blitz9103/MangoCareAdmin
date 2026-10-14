<?php
require_once '../config/config.php';
requireLogin();

$firebase = new Firebase();

// Get UID
$uid = isset($_GET['uid']) ? trim($_GET['uid']) : '';
if (empty($uid)) {
    header('Location: manageaccount.php');
    exit;
}

// Fetch user
$user = $firebase->get("Users/{$uid}") ?? [];
if (empty($user)) {
    header('Location: manageaccount.php');
    exit;
}

$name = $user['name'] ?? 'Unknown';
$email = $user['email'] ?? 'N/A';
$role = $user['role'] ?? 'worker';
$rawStatus = strtolower(trim((string) ($user['status'] ?? 'active')));
$isActive = !in_array($rawStatus, ['disabled', 'inactive', 'banned', 'false', '0', 'no', 'off'], true);
$status = $isActive ? 'active' : 'disabled';

// ─────────────────────────────────────────────
// FIXED SCAN RESULTS SECTION
// ─────────────────────────────────────────────

$rawScans = isset($user['scanResult']) && is_array($user['scanResult'])
    ? $user['scanResult'] : [];

$scans = [];

$trees = $firebase->get('trees') ?? [];
$trees = is_array($trees) ? $trees : [];

foreach ($rawScans as $scanId => $scan) {

    // Normalize disease field
    $disease = '';
    foreach (['disease', 'diseaseName', 'disease_final', 'final_disease'] as $f) {
        if (!empty($scan[$f])) {
            $disease = trim($scan[$f]);
            break;
        }
    }
    $scan['_disease'] = $disease;

    // Get Tree ID correctly (treeId from Android)
    $treeId = null;
    if (!empty($scan['treeId'])) {
        $treeId = $scan['treeId'];
    } elseif (!empty($scan['tree_id'])) {
        $treeId = $scan['tree_id'];
    } elseif (!empty($scan['treeID'])) {
        $treeId = $scan['treeID'];
    } elseif (!empty($scan['tree'])) {
        $treeId = $scan['tree'];
    }

    $scan['_tree_label'] = $treeId;

    // Resolve location from trees node (case-insensitive) when missing/placeholder
    $location = isset($scan['location']) ? trim($scan['location']) : '';
    $placeholders = ['', 'scan result', 'camera scan', 'n/a', 'na', 'none', 'unknown'];
    if (!empty($treeId)) {
        $searchKey = strtolower((string) $treeId);
        foreach ($trees as $tId => $tData) {
            if (strtolower((string) $tId) === $searchKey) {
                if (in_array(strtolower($location), $placeholders, true) && is_array($tData) && !empty($tData['location'])) {
                    $scan['location'] = $tData['location'];
                }
                break;
            }
        }
    }

    $scans[] = $scan;
}

// Sort newest first (normalize seconds-vs-milliseconds)
usort($scans, function ($a, $b) {
    $tA = (int) ($a['timestamp'] ?? 0);
    $tB = (int) ($b['timestamp'] ?? 0);
    if ($tA > 0 && $tA < 100000000000)
        $tA *= 1000;
    if ($tB > 0 && $tB < 100000000000)
        $tB *= 1000;
    return $tB - $tA;
});

$totalScans = count($scans);

// ─────────────────────────────────────────────
// ANALYTICS
// ─────────────────────────────────────────────

$diseaseFreq = [];
foreach ($scans as $s) {
    $d = $s['_disease'];
    if ($d !== '') {
        $k = strtolower($d);
        $diseaseFreq[$k] = ($diseaseFreq[$k] ?? 0) + 1;
    }
}
arsort($diseaseFreq);

$scansToday = 0;
$scansThisWeek = 0;
$scansThisMonth = 0;

foreach ($scans as $s) {
    if (empty($s['timestamp']))
        continue;

    $t = $s['timestamp'] > 9999999999
        ? $s['timestamp'] / 1000
        : $s['timestamp'];

    if ($t >= strtotime('today'))
        $scansToday++;
    if ($t >= strtotime('-7 days'))
        $scansThisWeek++;
    if ($t >= strtotime('first day of this month'))
        $scansThisMonth++;
}

$lastScanDate = !empty($scans[0]['timestamp'])
    ? date('M d, Y H:i', $scans[0]['timestamp'] / 1000)
    : 'Never';

// Most scanned location
$locationFreq = [];
foreach ($scans as $s) {
    if (!empty($s['location'])) {
        $loc = trim($s['location']);
        $locationFreq[$loc] = ($locationFreq[$loc] ?? 0) + 1;
    }
}
arsort($locationFreq);
$topLocation = !empty($locationFreq) ? array_key_first($locationFreq) : 'N/A';

// Chart colours
$diseaseColors = [
    '#004737',
    '#70B9A1',
    '#f59e0b',
    '#ef4444',
    '#3b82f6',
    '#8b5cf6',
    '#ec4899',
    '#06b6d4',
    '#84cc16',
    '#f97316'
];

// 7-day activity data
$last7Labels = [];
$last7Data = [];
for ($i = 6; $i >= 0; $i--) {
    $last7Labels[] = date('M d', strtotime("-$i days"));
    $dayKey = date('Y-m-d', strtotime("-$i days"));
    $count = 0;
    foreach ($scans as $s) {
        if (empty($s['timestamp']))
            continue;
        $t = $s['timestamp'] > 9999999999 ? $s['timestamp'] / 1000 : $s['timestamp'];
        if (date('Y-m-d', $t) === $dayKey)
            $count++;
    }
    $last7Data[] = $count;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($name); ?> – Profile · <?php echo SITE_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/design-system.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* ── Profile hero ── */
        .profile-hero {
            background: linear-gradient(135deg, #004737 0%, #005a46 60%, #006B55 100%);
            border-radius: var(--radius-xl);
            padding: 2rem 2.5rem;
            margin-bottom: 1.75rem;
            display: flex;
            align-items: center;
            gap: 2rem;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-hover);
        }

        .profile-hero::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: rgba(112, 185, 161, .12);
            pointer-events: none;
        }

        .profile-avatar {
            width: 90px;
            height: 90px;
            background: rgba(255, 255, 255, .15);
            border: 3px solid rgba(255, 255, 255, .3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            font-weight: 800;
            color: #fff;
            flex-shrink: 0;
            text-transform: uppercase;
            z-index: 1;
        }

        .profile-info {
            z-index: 1;
            flex: 1;
        }

        .profile-info h1 {
            font-size: 1.75rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: .3rem;
        }

        .profile-info p {
            font-size: .9rem;
            color: rgba(255, 255, 255, .75);
            display: flex;
            align-items: center;
            gap: .5rem;
            margin-bottom: .5rem;
        }

        .profile-badges {
            display: flex;
            gap: .6rem;
            flex-wrap: wrap;
            margin-top: .75rem;
        }

        .profile-badge {
            background: rgba(255, 255, 255, .15);
            border: 1px solid rgba(255, 255, 255, .25);
            color: #fff;
            padding: .3rem .85rem;
            border-radius: var(--radius-full);
            font-size: .78rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: .4rem;
        }

        .profile-badge.active {
            background: rgba(34, 197, 94, .25);
            border-color: rgba(34, 197, 94, .4);
        }

        .profile-badge.disabled {
            background: rgba(239, 68, 68, .25);
            border-color: rgba(239, 68, 68, .4);
        }

        /* ── Back row (above hero) ── */
        .back-row {
            width: 100%;
            background: transparent;
            padding: 0 0 .6rem 0;
            display: flex;
            align-items: center;
        }

        .hero-back {
            color: var(--primary, #004737);
            font-size: .8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .35rem .9rem;
            border-radius: var(--radius-lg, 8px);
            text-decoration: none;
            transition: background .2s, border-color .2s;
        }

        .hero-back:hover {
            background: rgba(0, 71, 55, .15);
            border-color: rgba(0, 71, 55, .35);
            color: var(--primary, #004737);
        }

        /* ── Charts grid ── */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.75rem;
        }

        .chart-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-card);
            overflow: hidden;
        }

        .chart-card-header {
            padding: .875rem 1.25rem;
            background: var(--bg-header);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .chart-card-title {
            font-size: .85rem;
            font-weight: 700;
            color: #fff;
            display: flex;
            align-items: center;
            gap: .5rem;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .chart-card-body {
            padding: 1.25rem;
        }

        canvas {
            max-height: 260px;
        }

        /* ── Disease breakdown list ── */
        .disease-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: .75rem;
        }

        .disease-list li {
            display: flex;
            align-items: center;
            gap: .75rem;
        }

        .disease-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .disease-name {
            flex: 1;
            font-size: .875rem;
            font-weight: 600;
            color: var(--text-heading);
            text-transform: capitalize;
        }

        .disease-bar-wrap {
            flex: 2;
        }

        .disease-bar-bg {
            height: 8px;
            background: #e2e8f0;
            border-radius: 99px;
            overflow: hidden;
        }

        .disease-bar-fill {
            height: 100%;
            border-radius: 99px;
            transition: width .5s ease;
        }

        .disease-count {
            font-size: .8rem;
            font-weight: 700;
            color: var(--primary);
            min-width: 32px;
            text-align: right;
        }

        /* ── No data state ── */
        .no-data {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-muted);
        }

        .no-data i {
            font-size: 2.5rem;
            display: block;
            margin-bottom: .75rem;
            color: var(--accent-light);
        }
    </style>
</head>

<body>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">

        <!-- BACK ROW (above hero) -->
        <div class="back-row">
            <a href="manageaccount.php" class="hero-back">
                <i class="fas fa-arrow-left"></i> Back to Accounts
            </a>
        </div>

        <!-- PROFILE HERO BANNER -->
        <div class="profile-hero">
            <div class="profile-avatar"><?= strtoupper(substr($name, 0, 2)); ?></div>
            <div class="profile-info">
                <h1><?= htmlspecialchars($name); ?></h1>
                <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($email); ?></p>
                <div class="profile-badges">
                    <span class="profile-badge">
                        <i class="fas <?= $role === 'admin' ? 'fa-crown' : 'fa-hard-hat'; ?>"></i>
                        <?= ucfirst($role); ?>
                    </span>
                    <span class="profile-badge <?= $isActive ? 'active' : 'disabled'; ?>">
                        <i class="fas <?= $isActive ? 'fa-check-circle' : 'fa-ban'; ?>"></i>
                        <?= $isActive ? 'Active' : 'Disabled'; ?>
                    </span>
                    <span class="profile-badge">
                        <i class="fas fa-microscope"></i> <?= $totalScans; ?> Total Scans
                    </span>
                    <span class="profile-badge">
                        <i class="fas fa-clock"></i> Last scan: <?= $lastScanDate; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- STATS GRID -->
        <div class="stats-grid" style="margin-bottom:1.75rem;">
            <div class="stat-card">
                <div class="stat-icon-wrapper"><i class="fas fa-microscope"></i></div>
                <div class="stat-info">
                    <h3><?= $totalScans; ?></h3>
                    <p><i class="fas fa-list"></i> Total Scans</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-wrapper"><i class="fas fa-calendar-day"></i></div>
                <div class="stat-info">
                    <h3><?= $scansToday; ?></h3>
                    <p><i class="fas fa-sun"></i> Scans Today</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-wrapper"><i class="fas fa-calendar-week"></i></div>
                <div class="stat-info">
                    <h3><?= $scansThisWeek; ?></h3>
                    <p><i class="fas fa-dot-circle"></i> This Week</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-wrapper"><i class="fas fa-map-marker-alt"></i></div>
                <div class="stat-info">
                    <h3 style="font-size:1.1rem;"><?= htmlspecialchars($topLocation); ?></h3>
                    <p><i class="fas fa-star"></i> Top Location</p>
                </div>
            </div>
        </div>

        <!-- CHARTS ROW -->
        <div class="charts-grid">

            <!-- Disease Pie Chart -->
            <div class="chart-card">
                <div class="chart-card-header">
                    <span class="chart-card-title"><i class="fas fa-virus"></i> Disease Distribution</span>
                    <span style="font-size:.75rem;color:rgba(255,255,255,.6);"><?= count($diseaseFreq); ?> types</span>
                </div>
                <div class="chart-card-body">
                    <?php if (empty($diseaseFreq)): ?>
                        <div class="no-data"><i class="fas fa-virus-slash"></i> No disease data yet</div>
                    <?php else: ?>
                        <canvas id="diseaseChart"></canvas>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 7-Day Activity Chart -->
            <div class="chart-card">
                <div class="chart-card-header">
                    <span class="chart-card-title"><i class="fas fa-chart-line"></i> 7-Day Scan Activity</span>
                </div>
                <div class="chart-card-body">
                    <?php if ($totalScans === 0): ?>
                        <div class="no-data"><i class="fas fa-chart-bar"></i> No scan activity yet</div>
                    <?php else: ?>
                        <canvas id="activityChart"></canvas>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- DISEASE BREAKDOWN LIST -->
        <?php if (!empty($diseaseFreq)): ?>
            <div class="data-card" style="margin-bottom:1.75rem;">
                <div class="data-card-header">
                    <span class="data-card-title"><i class="fas fa-biohazard"></i> Disease Breakdown</span>
                    <span class="data-card-count"><?= $totalScans; ?> scans total</span>
                </div>
                <div class="card-body">
                    <ul class="disease-list">
                        <?php
                        $colorIdx = 0;
                        foreach ($diseaseFreq as $disease => $count):
                            $color = $diseaseColors[$colorIdx % count($diseaseColors)];
                            $pct = $totalScans > 0 ? round(($count / $totalScans) * 100, 1) : 0;
                            $colorIdx++;
                            ?>
                            <li>
                                <span class="disease-dot" style="background:<?= $color; ?>;"></span>
                                <span class="disease-name"><?= htmlspecialchars(ucwords($disease)); ?></span>
                                <div class="disease-bar-wrap">
                                    <div class="disease-bar-bg">
                                        <div class="disease-bar-fill" style="width:<?= $pct; ?>%;background:<?= $color; ?>;">
                                        </div>
                                    </div>
                                </div>
                                <span class="disease-count"><?= $count; ?></span>
                                <span
                                    style="font-size:.75rem;color:var(--text-muted);min-width:42px;text-align:right;"><?= $pct; ?>%</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <!-- RECENT SCAN HISTORY TABLE -->
        <div class="data-card">
            <div class="data-card-header">
                <span class="data-card-title"><i class="fas fa-history"></i> Scan History</span>
                <span class="data-card-count"><?= $totalScans; ?> records</span>
            </div>
            <?php if (empty($scans)): ?>
                <div class="empty-state" style="padding:3rem;">
                    <div class="empty-state-icon"><i class="fas fa-microscope"></i></div>
                    <h3>No scans yet</h3>
                    <p>This user hasn't recorded any disease scans yet.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><i class="far fa-calendar-alt"></i> Date & Time</th>
                                <th><i class="fas fa-virus"></i> Disease Detected</th>
                                <th><i class="fas fa-tree"></i> Tree ID</th>
                                <th><i class="fas fa-map-marker-alt"></i> Location</th>
                                <th><i class="fas fa-percentage"></i> Confidence</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($scans as $scan): ?>
                                <?php
                                $disease = $scan['_disease'] ?: 'Unknown';
                                $treeId = $scan['_tree_label'] ?? null; // resolved human-readable label
                                $location = $scan['location'] ?? 'N/A';
                                $confidence = null;
                                if (isset($scan['confidence'])) {
                                    $raw = (float) $scan['confidence'];
                                    // Firebase stores as 0–1 decimal OR already 0–100 percentage
                                    $confidence = $raw > 1 ? round($raw, 1) : round($raw * 100, 1);
                                }
                                $ts = isset($scan['timestamp'])
                                    ? date('M d, Y H:i', $scan['timestamp'] / 1000)
                                    : 'N/A';

                                // Disease badge colour
                                $lowerD = strtolower($disease);
                                if (str_contains($lowerD, 'anthracnose'))
                                    $badgeClass = 'badge-warning';
                                elseif (str_contains($lowerD, 'scab'))
                                    $badgeClass = 'badge-danger';
                                elseif (str_contains($lowerD, 'healthy'))
                                    $badgeClass = 'badge-success';
                                else
                                    $badgeClass = 'badge-info';

                                // Confidence class
                                if ($confidence !== null) {
                                    if ($confidence >= 75)
                                        $confClass = 'conf-high';
                                    elseif ($confidence >= 50)
                                        $confClass = 'conf-med';
                                    else
                                        $confClass = 'conf-low';
                                }
                                ?>
                                <tr>
                                    <td>
                                        <div class="date-cell">
                                            <div class="date-icon"><i class="far fa-calendar"></i></div>
                                            <div class="date-text">
                                                <span class="date-main"><?= $ts; ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?= $badgeClass; ?>">
                                            <i class="fas fa-virus"></i>
                                            <?= htmlspecialchars(ucwords($disease)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($treeId)): ?>
                                            <span class="badge badge-tree"><i class="fas fa-tag"></i>
                                                <?= htmlspecialchars($treeId); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="location-cell">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <?= htmlspecialchars($location); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($confidence !== null): ?>
                                            <div class="conf-wrap <?= $confClass; ?>">
                                                <span class="conf-value"><?= $confidence; ?>%</span>
                                                <div class="conf-bar-bg">
                                                    <div class="conf-bar-fill" style="width:<?= min($confidence, 100); ?>%;"></div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <script>
        <?php if (!empty($diseaseFreq)): ?>
            // ── Disease Pie Chart ──────────────────────────────────────
            const diseaseLabels = <?= json_encode(array_map('ucwords', array_keys($diseaseFreq))); ?>;
            const diseaseCounts = <?= json_encode(array_values($diseaseFreq)); ?>;
            const diseaseColors = <?= json_encode(array_slice($diseaseColors, 0, count($diseaseFreq))); ?>;

            new Chart(document.getElementById('diseaseChart'), {
                type: 'doughnut',
                data: {
                    labels: diseaseLabels,
                    datasets: [{
                        data: diseaseCounts,
                        backgroundColor: diseaseColors,
                        borderWidth: 3,
                        borderColor: '#fff',
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { padding: 16, usePointStyle: true, pointStyle: 'circle', font: { size: 12, family: 'Inter' } }
                        },
                        tooltip: {
                            callbacks: {
                                label: ctx => ` ${ctx.label}: ${ctx.parsed} scans (${Math.round(ctx.parsed / diseaseCounts.reduce((a, b) => a + b, 0) * 100)}%)`
                            }
                        }
                    },
                    cutout: '60%'
                }
            });
        <?php endif; ?>

        <?php if ($totalScans > 0): ?>
            // ── 7-Day Activity Chart ──────────────────────────────────
            new Chart(document.getElementById('activityChart'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode($last7Labels); ?>,
                    datasets: [{
                        label: 'Scans',
                        data: <?= json_encode($last7Data); ?>,
                        backgroundColor: 'rgba(0,71,55,.15)',
                        borderColor: '#004737',
                        borderWidth: 2,
                        borderRadius: 6,
                        borderSkipped: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1, color: '#6c757d', font: { family: 'Inter' } },
                            grid: { color: 'rgba(112,185,161,.1)' }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { color: '#6c757d', font: { family: 'Inter', size: 11 } }
                        }
                    }
                }
            });
        <?php endif; ?>
    </script>
</body>

</html>