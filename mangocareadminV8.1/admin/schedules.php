<?php
require_once '../config/config.php';
requireLogin();

$firebase = new Firebase();
processAutomaticSchedules($firebase);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'create') {
            $treeId = trim($_POST['tree_id'] ?? '');
            if (!$treeId) {
                $error = 'Please select a tree.';
            } else {
                $tree = $firebase->get('trees/' . $treeId);
                if (!$tree || !is_array($tree)) {
                    $error = 'Selected tree not found.';
                } else {
                    $scheduleData = [
                        'spray_date' => $_POST['spray_date'],
                        'spray_time' => $_POST['spray_time'],
                        'notes' => $_POST['notes'] ?? '',
                        'status' => 'pending',
                        'created_at' => date('M d, Y g:i A'),
                        'created_by' => $_SESSION['admin_username']
                    ];

                    $result = $firebase->push('trees/' . $treeId . '/spray_schedules', $scheduleData);
                    if ($result !== false && !empty($result['name'])) {
                        syncSystemNotifications($firebase);
                        header('Location: schedules.php?success=Schedule created successfully');
                        exit();
                    } else {
                        $error = 'Failed to create schedule in Firebase.';
                    }
                }
            }
        } elseif ($_POST['action'] === 'update_status' && isset($_POST['tree_id'], $_POST['schedule_id'])) {
            $treeId     = $_POST['tree_id'];
            $scheduleId = $_POST['schedule_id'];
            $status     = $_POST['status'] ?? 'completed';

            // Fetch schedule data before updating
            $schedule = $firebase->get('trees/' . $treeId . '/spray_schedules/' . $scheduleId);

            $update = ['status' => $status];
            if ($status === 'completed') {
                $update['completed_at'] = date('M d, Y g:i A');
            }
            $result = $firebase->update('trees/' . $treeId . '/spray_schedules/' . $scheduleId, $update);

            // When manually marked as completed → write spray_history entry
            if ($result !== false && $status === 'completed' && is_array($schedule)) {
                $sprayDate      = $schedule['spray_date'] ?? '';
                $sprayTime      = $schedule['spray_time'] ?? '08:00';
                $sprayTimestamp = ($sprayDate && $sprayTime)
                    ? strtotime($sprayDate . ' ' . $sprayTime) * 1000
                    : time() * 1000;

                $firebase->push('spray_history', [
                    'tree_id'      => $treeId,
                    'timestamp'    => $sprayTimestamp,
                    'status'       => 'completed',
                    'notes'        => $schedule['notes'] ?? '',
                    'completed_at' => $update['completed_at'] ?? date('M d, Y g:i A'),
                ]);

                // ── Auto-generate ONE next treatment (+1 month) ────────────────
                // Only create if NO future pending schedule already exists for this tree.
                // Anchor: when it was marked complete (+1 calendar month).
                ensureNextSchedule($firebase, $treeId, array_merge($schedule, $update));
                // ─────────────────────────────────────────────────────────────────
            }

            if ($result !== false) {
                syncSystemNotifications($firebase);
                header('Location: schedules.php?success=Status updated successfully');
                exit();
            } else {
                $error = 'Failed to update status in Firebase.';
            }
        } elseif ($_POST['action'] === 'delete' && isset($_POST['tree_id'], $_POST['schedule_id'])) {
            $treeId = $_POST['tree_id'];
            $scheduleId = $_POST['schedule_id'];
            $result = $firebase->delete('trees/' . $treeId . '/spray_schedules/' . $scheduleId);
            if ($result !== false) {
                syncSystemNotifications($firebase);
                header('Location: schedules.php?success=Schedule deleted successfully');
                exit();
            } else {
                $error = 'Failed to delete schedule from Firebase.';
            }
        }
    }
}

// Get all trees
$trees = $firebase->get('trees') ?? [];
$trees = is_array($trees) ? $trees : [];

// Build flattened list of schedules from trees (each under trees/{id}/spray_schedules)
$schedules = [];
foreach ($trees as $treeId => $tree) {
    $treeSchedules = $tree['spray_schedules'] ?? [];
    if (!is_array($treeSchedules)) {
        continue;
    }
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

$message = $_GET['success'] ?? $_GET['error'] ?? '';
$showForm = isset($_GET['new']) || isset($error);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spray Schedules - <?php echo SITE_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/design-system.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- PAGE BANNER -->
        <div class="page-banner">
            <div class="banner-left">
                <div class="banner-icon"><i class="fas fa-calendar-alt"></i></div>
                <div class="banner-text">
                    <h1>Spray Schedules</h1>
                    <p>Create and manage spray schedules for mango trees</p>
                </div>
            </div>
            <div class="banner-badge" id="schedCountBanner"><i class="fas fa-list"></i> <?= count($schedules); ?> Schedules</div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div style="margin-bottom: 1.25rem; display:flex; gap:.75rem; align-items:center; flex-wrap:wrap;">
            <a href="?new=1" class="btn btn-primary"><i class="fas fa-plus"></i> Create New Schedule</a>
            <div class="alert alert-info" style="margin:0; flex:1;">
                <i class="fas fa-info-circle"></i>
                <strong>Scheduling rules (Philippine Time):</strong> If a schedule is not marked as sprayed on its
                date, it becomes <strong>Overdue</strong> (it is never auto-completed). When a schedule is actually
                marked <strong>Completed</strong>, a new one is auto-generated for the next cycle. Default interval:
                every <strong><?php echo DEFAULT_SPRAY_INTERVAL_MONTHS; ?> month</strong> at
                <strong><?php echo convertTo12Hour(DEFAULT_SPRAY_TIME); ?></strong>.
            </div>
        </div>

        <?php if ($showForm): ?>
            <div class="data-card">
                <div class="data-card-header">
                    <span class="data-card-title"><i class="fas fa-plus-circle"></i> Create New Spray Schedule</span>
                </div>
                <div class="card-body">
                    <?php if (empty($trees)): ?>
                        <div class="alert alert-info"><i class="fas fa-info-circle"></i> No trees registered yet. Create trees
                            first via <a href="qr-generator.php">QR Code Generator</a>, then add schedules.</div>
                    <?php else: ?>
                        <form method="POST" action="schedules.php">
                            <input type="hidden" name="action" value="create">
                            <div class="form-group">
                                <label for="tree_id">Tree *</label>
                                <select id="tree_id" name="tree_id" required>
                                    <option value="">— Select tree —</option>
                                    <?php foreach ($trees as $tid => $t): ?>
                                        <option value="<?php echo htmlspecialchars($tid); ?>">
                                            <?php echo htmlspecialchars($tid); ?> —
                                            <?php echo htmlspecialchars($t['location'] ?? 'N/A'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="spray_date">Spray Date *</label>
                                <input type="date" id="spray_date" name="spray_date" required>
                            </div>
                            <div class="form-group">
                                <label for="spray_time">Spray Time * <small
                                        style="font-weight:400;text-transform:none;color:var(--text-muted)">(Philippine
                                        Time)</small></label>
                                <input type="time" id="spray_time" name="spray_time" required
                                    value="<?php echo DEFAULT_SPRAY_TIME; ?>">
                            </div>
                            <div class="form-group">
                                <label for="notes">Notes</label>
                                <textarea id="notes" name="notes" rows="3" placeholder="Additional notes..."></textarea>
                            </div>
                            <div class="action-buttons">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Create
                                    Schedule</button>
                                <a href="schedules.php" class="btn btn-outline"><i class="fas fa-times"></i> Cancel</a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="data-card">
            <div class="data-card-header">
                <span class="data-card-title"><i class="fas fa-calendar-check"></i> All Schedules</span>
                <span class="data-card-count" id="schedCountCard"><?= count($schedules); ?> total &middot; PH Time (12-hour)</span>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-tree"></i> Tree ID</th>
                            <th><i class="fas fa-map-marker-alt"></i> Location</th>
                            <th><i class="far fa-calendar-alt"></i> Date</th>
                            <th><i class="fas fa-clock"></i> Time</th>
                            <th><i class="fas fa-tag"></i> Status</th>
                            <th><i class="fas fa-user"></i> Created By</th>
                            <th><i class="fas fa-cog"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody id="schedTableBody">
                        <?php if (empty($schedules)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <div class="empty-state-icon"><i class="fas fa-calendar-times"></i></div>
                                        <h3>No schedules found</h3>
                                        <p>Create a new schedule to get started</p>
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
                                    <td><span class="badge badge-tree"><i class="fas fa-tree"></i>
                                            <?= htmlspecialchars($treeId); ?></span></td>
                                    <td>
                                        <div class="location-cell"><i class="fas fa-map-marker-alt"></i>
                                            <?= htmlspecialchars($s['location'] ?? 'N/A'); ?></div>
                                    </td>
                                    <td><?= isset($s['spray_date']) ? date('M d, Y', strtotime($s['spray_date'])) : 'N/A'; ?>
                                    </td>
                                    <td><?= convertTo12Hour($s['spray_time'] ?? '') ?: 'N/A'; ?></td>
                                    <td><span class="badge <?= $badgeClass; ?>"><?= ucfirst($status); ?></span></td>
                                    <td><?= htmlspecialchars($s['created_by'] ?? 'Admin'); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($status !== 'completed'): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="tree_id" value="<?= htmlspecialchars($treeId); ?>">
                                                    <input type="hidden" name="schedule_id"
                                                        value="<?= htmlspecialchars($scheduleKey); ?>">
                                                    <input type="hidden" name="status" value="completed">
                                                    <button type="submit" class="btn btn-sm btn-success"><i
                                                            class="fas fa-check"></i> Complete</button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" style="display:inline;"
                                                onsubmit="return confirm('Delete this schedule?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="tree_id" value="<?= htmlspecialchars($treeId); ?>">
                                                <input type="hidden" name="schedule_id"
                                                    value="<?= htmlspecialchars($scheduleKey); ?>">
                                                <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i>
                                                    Delete</button>
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

        <script type="module">
            const _fb = window.MangoFB;
            if (_fb && _fb.db) {
                const { db, ref, onValue, escHtml, to12Hour, formatDate, scheduleDateStr, badgeFor } = _fb;

                function renderSchedules(snapshot) {
                    const trees = snapshot.val() || {};
                    const list = [];

                    Object.entries(trees).forEach(([treeId, tree]) => {
                        if (!tree || treeId === '0' || treeId === 0 || String(treeId) === '0') return;
                        const treeSchedules = tree.spray_schedules || {};
                        Object.entries(treeSchedules).forEach(([scheduleKey, sched]) => {
                            if (!sched || typeof sched !== 'object') return;
                            list.push({
                                ...sched,
                                _tree_id: treeId,
                                _schedule_key: scheduleKey,
                                location: tree.location || 'N/A'
                            });
                        });
                    });

                    list.sort((a, b) => {
                        const da = (scheduleDateStr(a) || '') + ' ' + (a.spray_time || '');
                        const dbb = (scheduleDateStr(b) || '') + ' ' + (b.spray_time || '');
                        return da < dbb ? -1 : da > dbb ? 1 : 0;
                    });

                    const tbody = document.getElementById('schedTableBody');
                    if (tbody) {
                        if (list.length === 0) {
                            tbody.innerHTML = `
                                <tr>
                                    <td colspan="7">
                                        <div class="empty-state">
                                            <div class="empty-state-icon"><i class="fas fa-calendar-times"></i></div>
                                            <h3>No schedules found</h3>
                                            <p>Create a new schedule to get started</p>
                                        </div>
                                    </td>
                                </tr>`;
                        } else {
                            tbody.innerHTML = list.map(s => {
                                const b = badgeFor(s.status);
                                const dateFmt = formatDate(scheduleDateStr(s), 'M d, Y') || 'N/A';
                                const timeFmt = to12Hour(s.spray_time) || 'N/A';
                                const tree = escHtml(s._tree_id);
                                const schedId = escHtml(s._schedule_key);
                                const loc = escHtml(s.location);
                                const created = escHtml(s.created_by || 'Admin');
                                const completeBtn = s.status !== 'completed' ? `
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="tree_id" value="${tree}">
                                        <input type="hidden" name="schedule_id" value="${schedId}">
                                        <input type="hidden" name="status" value="completed">
                                        <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check"></i> Complete</button>
                                    </form>` : '';
                                return `
                                    <tr>
                                        <td><span class="badge badge-tree"><i class="fas fa-tree"></i> ${tree}</span></td>
                                        <td><div class="location-cell"><i class="fas fa-map-marker-alt"></i> ${loc}</div></td>
                                        <td>${dateFmt}</td>
                                        <td>${timeFmt}</td>
                                        <td><span class="badge ${b.cls}">${b.label}</span></td>
                                        <td>${created}</td>
                                        <td><div class="action-buttons">${completeBtn}
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this schedule?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="tree_id" value="${tree}">
                                                <input type="hidden" name="schedule_id" value="${schedId}">
                                                <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i> Delete</button>
                                            </form>
                                        </div></td>
                                    </tr>`;
                            }).join('');
                        }
                    }

                    const c1 = document.getElementById('schedCountBanner');
                    if (c1) c1.innerHTML = `<i class="fas fa-list"></i> ${list.length} Schedules`;
                    const c2 = document.getElementById('schedCountCard');
                    if (c2) c2.textContent = `${list.length} total · PH Time (12-hour)`;
                }

                onValue(ref(db, 'trees'), renderSchedules);
            }
        </script>
        <script src="../assets/js/main.js"></script>
</body>

</html>