<?php
require_once '../config/config.php';
requireLogin();

$firebase = new Firebase();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'delete' && isset($_POST['tree_id'])) {
            $treeId = $_POST['tree_id'];

            // Delete associated spray schedules first
            $firebase->delete('trees/' . $treeId . '/spray_schedules');

            // Delete the tree itself
            $result = $firebase->delete('trees/' . $treeId);
            if ($result !== false) {
                header('Location: trees.php?success=Tree and all associated data deleted successfully');
                exit();
            } else {
                $deleteError = 'Failed to delete tree from Firebase.';
            }
        }
    }
}

// Get all trees
$trees = $firebase->get('trees') ?? [];
$trees = is_array($trees) ? $trees : [];

// Remove hard-coded tree with ID "0" if it exists (handle both string and integer keys)
$treeKeysToRemove = [];
foreach ($trees as $treeId => $tree) {
    if ($treeId === '0' || $treeId === 0 || (string) $treeId === '0') {
        $treeKeysToRemove[] = $treeId;
    }
}

foreach ($treeKeysToRemove as $treeIdToRemove) {
    // Delete associated spray schedules first
    $firebase->delete('trees/' . $treeIdToRemove . '/spray_schedules');

    // Delete the tree itself
    $firebase->delete('trees/' . $treeIdToRemove);

    // Remove from local array
    unset($trees[$treeIdToRemove], $trees['0'], $trees[0]);
}

// Natural sorting for Tree IDs (tree1, tree2, ..., tree10)
ksort($trees, SORT_NATURAL | SORT_FLAG_CASE);

// Get spray history for each tree
$sprayHistory = $firebase->get('spray_history') ?? [];
$sprayHistory = is_array($sprayHistory) ? $sprayHistory : [];

// Get scans per tree
$scans = $firebase->get('scans') ?? [];
$scans = is_array($scans) ? $scans : [];

$message = isset($deleteError) ? $deleteError : ($_GET['success'] ?? $_GET['error'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mango Trees Management - <?php echo SITE_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/design-system.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .action-buttons {
            flex-direction: column;
            min-width: 90px;
        }

        .action-buttons form,
        .action-buttons a,
        .action-buttons button {
            width: 100%;
            text-align: center;
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
                <div class="banner-icon"><i class="fas fa-tree"></i></div>
                <div class="banner-text">
                    <h1>Mango Trees</h1>
                    <p>View and manage all registered mango trees</p>
                </div>
            </div>
            <a href="qr-generator.php" class="banner-badge"><i class="fas fa-qrcode"></i> Generate QR</a>
        </div>

        <?php if ($message): ?>
            <div class="alert <?= (isset($deleteError) || isset($_GET['error'])) ? 'alert-error' : 'alert-success'; ?>">
                <i
                    class="fas <?= (isset($deleteError) || isset($_GET['error'])) ? 'fa-exclamation-circle' : 'fa-check-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon-wrapper"><i class="fas fa-tree"></i></div>
                <div class="stat-info">
                    <h3 id="treeStatTotal">
                        <?= count(array_filter($trees, function ($treeId) {
                            return $treeId !== '0'; }, ARRAY_FILTER_USE_KEY)); ?>
                    </h3>
                    <p><i class="fas fa-chart-line"></i> Total Trees</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-wrapper"><i class="fas fa-qrcode"></i></div>
                <div class="stat-info">
                    <h3 id="treeStatQr">
                        <?= count(array_filter($trees, function ($tree, $treeId) {
                            return $treeId !== '0' && isset($tree['has_qr']) && $tree['has_qr']; }, ARRAY_FILTER_USE_BOTH)); ?>
                    </h3>
                    <p><i class="fas fa-check-circle"></i> With QR Code</p>
                </div>
            </div>
        </div>

        <!-- Trees Table -->
        <div class="data-card">
            <div class="data-card-header">
                <span class="data-card-title"><i class="fas fa-tree"></i> Registered Trees</span>
                <a href="qr-generator.php" class="btn btn-sm btn-primary"><i class="fas fa-qrcode"></i> Generate QR</a>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-tag"></i> Tree ID</th>
                            <th><i class="fas fa-map-marker-alt"></i> Location</th>
                            <th><i class="fas fa-qrcode"></i> QR Code</th>
                            <th><i class="fas fa-map"></i> GPS</th>
                            <th><i class="fas fa-microscope"></i> Scans</th>
                            <th><i class="fas fa-tint"></i> Last Spray</th>
                            <th><i class="fas fa-clock"></i> Spray Status</th>
                            <th><i class="fas fa-cog"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody id="treeTableBody">
                        <?php if (empty($trees)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">
                                            <i class="fas fa-tree"></i>
                                        </div>
                                        <h3>No trees registered</h3>
                                        <p>Generate QR codes to register trees in the system</p>
                                        <a href="qr-generator.php" class="btn btn-primary">
                                            <i class="fas fa-qrcode"></i> Generate QR Code
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($trees as $treeId => $tree): ?>
                                <?php
                                // Skip tree ID "0" (hardcoded/default tree) - handle all variations
                                if ($treeId === '0' || $treeId === 0 || (string) $treeId === '0' || trim((string) $treeId) === '0') {
                                    continue;
                                }

                                // Count scans for this tree
                                $treeScans = array_filter($scans, function ($scan) use ($treeId) {
                                    return isset($scan['tree_id']) && $scan['tree_id'] === $treeId;
                                });
                                $scanCount = count($treeScans);

                                // Get last spray
                                $treeSprays = array_filter($sprayHistory, function ($spray) use ($treeId) {
                                    return isset($spray['tree_id']) && $spray['tree_id'] === $treeId;
                                });
                                $lastSpray = null;
                                if (!empty($treeSprays)) {
                                    usort($treeSprays, function ($a, $b) {
                                        return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
                                    });
                                    $lastSpray = reset($treeSprays);
                                }

                                // Check if tree has been sprayed recently (within last 7 days)
                                $recentlySprayed = false;
                                if ($lastSpray && isset($lastSpray['timestamp'])) {
                                    $sprayTime = $lastSpray['timestamp'] / 1000;
                                    $recentlySprayed = (time() - $sprayTime) < (7 * 24 * 60 * 60);
                                }
                                ?>
                                <tr>
                                    <td>
                                        <span class="badge badge-tree"><i class="fas fa-tag"></i>
                                            <?= htmlspecialchars($treeId); ?></span>
                                    </td>
                                    <td>
                                        <i class="fas fa-map-marker-alt"
                                            style="color: var(--secondary-color); margin-right: 5px;"></i>
                                        <?php echo htmlspecialchars($tree['location'] ?? 'N/A'); ?>
                                    </td>
                                    <td>
                                        <?php if (isset($tree['has_qr']) && $tree['has_qr']): ?>
                                            <span class="badge badge-success">
                                                <i class="fas fa-check-circle"></i> Yes
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">
                                                <i class="fas fa-exclamation-triangle"></i> No
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge" style="background: var(--secondary-color); color: white;">
                                            <i class="fas fa-microscope"></i> <?php echo $scanCount; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (isset($tree['lat']) || isset($tree['latitude'])): ?>
                                            <span class="badge"
                                                style="background: #e3f2fd; color: #1976d2; border: 1px solid #bbdefb;">
                                                <i class="fas fa-satellite"></i> Linked
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted" style="font-size: 0.85rem;">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($lastSpray): ?>
                                            <i class="far fa-calendar-alt"
                                                style="color: var(--secondary-color); margin-right: 5px;"></i>
                                            <?php echo date('M d, Y H:i', $lastSpray['timestamp'] / 1000); ?>
                                        <?php else: ?>
                                            <span class="text-muted">
                                                <i class="fas fa-minus-circle"></i> Never
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($recentlySprayed): ?>
                                            <span class="badge badge-success">
                                                <i class="fas fa-check-circle"></i> Sprayed
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">
                                                <i class="fas fa-clock"></i> Pending
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="tree-details.php?id=<?php echo urlencode($treeId); ?>"
                                                class="btn btn-sm btn-primary" title="View tree details">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <form method="POST" action="trees.php"
                                                onsubmit="return confirm('⚠️ Are you sure you want to delete this tree? This action cannot be undone.');"
                                                style="display: block;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="tree_id"
                                                    value="<?php echo htmlspecialchars($treeId); ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Delete tree">
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
    </main>

    <script type="module">
        const _fb = window.MangoFB;
        if (_fb && _fb.db) {
            const { db, ref, onValue, escHtml, formatDate } = _fb;
            const _snaps = { trees: null, scans: null, history: null };
            function maybeRender() {
                if (_snaps.trees && _snaps.scans && _snaps.history) renderTrees();
            }
            onValue(ref(db, 'trees'), (s) => { _snaps.trees = s; maybeRender(); });
            onValue(ref(db, 'scans'), (s) => { _snaps.scans = s; maybeRender(); });
            onValue(ref(db, 'spray_history'), (s) => { _snaps.history = s; maybeRender(); });

            function renderTrees() {
                const trees = _snaps.trees.val() || {};
                const scans = Object.values(_snaps.scans.val() || {});
                const history = Object.values(_snaps.history.val() || {});

                const entries = Object.entries(trees).filter(([treeId]) => treeId !== '0' && treeId !== 0 && String(treeId) !== '0');
                const total = entries.length;
                const withQr = entries.filter(([, t]) => t && t.has_qr).length;

                const setText = (id, txt) => { const el = document.getElementById(id); if (el) el.textContent = txt; };
                setText('treeStatTotal', total);
                setText('treeStatQr', withQr);

                const tbody = document.getElementById('treeTableBody');
                if (!tbody) return;
                if (entries.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <div class="empty-state-icon"><i class="fas fa-tree"></i></div>
                                    <h3>No trees registered</h3>
                                    <p>Generate QR codes to register trees in the system</p>
                                    <a href="qr-generator.php" class="btn btn-primary"><i class="fas fa-qrcode"></i> Generate QR Code</a>
                                </div>
                            </td>
                        </tr>`;
                    return;
                }

                tbody.innerHTML = entries.map(([treeId, tree]) => {
                    const treeE = escHtml(treeId);
                    const loc = escHtml(tree.location || 'N/A');
                    const treeScans = scans.filter(s => s && s.tree_id === treeId);
                    const scanCount = treeScans.length;

                    const treeSprays = history.filter(h => h && h.tree_id === treeId)
                        .sort((a, b) => (b.timestamp || 0) - (a.timestamp || 0));
                    const lastSpray = treeSprays[0] || null;
                    let recentlySprayed = false;
                    if (lastSpray && lastSpray.timestamp) {
                        const st = Number(lastSpray.timestamp) > 9999999999 ? Number(lastSpray.timestamp) : Number(lastSpray.timestamp) * 1000;
                        recentlySprayed = (Date.now() - st) < (7 * 24 * 60 * 60 * 1000);
                    }

                    const qrBadge = tree.has_qr
                        ? `<span class="badge badge-success"><i class="fas fa-check-circle"></i> Yes</span>`
                        : `<span class="badge badge-warning"><i class="fas fa-exclamation-triangle"></i> No</span>`;
                    const gpsBadge = (tree.lat || tree.latitude)
                        ? `<span class="badge" style="background: #e3f2fd; color: #1976d2; border: 1px solid #bbdefb;"><i class="fas fa-satellite"></i> Linked</span>`
                        : `<span class="text-muted" style="font-size: 0.85rem;">None</span>`;
                    const scanCell = `<span class="badge" style="background: var(--secondary-color); color: white;"><i class="fas fa-microscope"></i> ${scanCount}</span>`;
                    const lastSprayCell = lastSpray
                        ? `<i class="far fa-calendar-alt" style="color: var(--secondary-color); margin-right: 5px;"></i> ${formatDate(Number(lastSpray.timestamp) > 9999999999 ? Number(lastSpray.timestamp) : Number(lastSpray.timestamp) * 1000, 'M d, Y H:i')}`
                        : `<span class="text-muted"><i class="fas fa-minus-circle"></i> Never</span>`;
                    const statusCell = recentlySprayed
                        ? `<span class="badge badge-success"><i class="fas fa-check-circle"></i> Sprayed</span>`
                        : `<span class="badge badge-warning"><i class="fas fa-clock"></i> Pending</span>`;

                    return `
                        <tr>
                            <td><span class="badge badge-tree"><i class="fas fa-tag"></i> ${treeE}</span></td>
                            <td><i class="fas fa-map-marker-alt" style="color: var(--secondary-color); margin-right: 5px;"></i> ${loc}</td>
                            <td>${qrBadge}</td>
                            <td>${gpsBadge}</td>
                            <td>${scanCell}</td>
                            <td>${lastSprayCell}</td>
                            <td>${statusCell}</td>
                            <td>
                                <div class="action-buttons">
                                    <a href="tree-details.php?id=${encodeURIComponent(treeId)}" class="btn btn-sm btn-primary" title="View tree details">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <form method="POST" action="trees.php" onsubmit="return confirm('⚠️ Are you sure you want to delete this tree? This action cannot be undone.');" style="display: block;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="tree_id" value="${treeE}">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Delete tree"><i class="fas fa-trash-alt"></i> Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>`;
                }).join('');
            }
        }
    </script>
    <script src="../assets/js/main.js"></script>
</body>

</html>