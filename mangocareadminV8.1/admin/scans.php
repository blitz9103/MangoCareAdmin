<?php
require_once '../config/config.php';
requireLogin();

$firebase = new Firebase();

$users = $firebase->get('Users') ?? [];
$users = is_array($users) ? $users : [];

$trees = $firebase->get('trees') ?? [];
$trees = is_array($trees) ? $trees : [];

/* ---------- helpers ---------- */

function extractScanTreeId($scan)
{
    return $scan['tree_id'] ?? $scan['treeId'] ?? $scan['treeID'] ?? $scan['tree'] ?? '';
}

function extractScanDisease($scan)
{
    foreach (['disease', 'diseaseName', 'disease_final', 'final_disease', 'predicted_disease'] as $field) {
        if (!empty($scan[$field])) {
            return trim($scan[$field]);
        }
    }
    return '';
}

function resolveScanLocation($scan, $trees)
{
    $treeId = extractScanTreeId($scan);
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

function normScanConfidence($value)
{
    if ($value === null || $value === '')
        return null;
    $num = (float) $value;
    if ($num <= 0 || is_nan($num))
        return null;
    if ($num < 1)
        $num *= 100;
    return $num;
}

function normScanTimestampMs($value)
{
    $t = (int) $value;
    if ($t > 0 && $t < 100000000000)
        return $t * 1000;
    return $t;
}

/* ---------- collect scans from Users → scanResult ---------- */

$scans = [];

foreach ($users as $userId => $userData) {
    if (isset($userData['scanResult']) && is_array($userData['scanResult'])) {
        foreach ($userData['scanResult'] as $scanId => $scanData) {
            if (is_array($scanData)) {
                $scanData['_user_id'] = $userId;
                $scanData['_scan_id'] = $scanId;
                $scans[] = $scanData;
            }
        }
    }
}

// Filter parameter (ONLY Tree ID)
$filterTreeId = $_GET['tree_id'] ?? '';

// Apply filter
$filteredScans = $scans;

if (!empty($filterTreeId)) {
    $filterKey = strtolower(trim($filterTreeId));
    $filteredScans = array_filter($filteredScans, function ($scan) use ($filterKey) {
        return strpos(strtolower(extractScanTreeId($scan)), $filterKey) !== false;
    });
}

// Sort by timestamp (newest first)
usort($filteredScans, function ($a, $b) {
    $timeA = normScanTimestampMs($a['timestamp'] ?? 0);
    $timeB = normScanTimestampMs($b['timestamp'] ?? 0);
    return $timeB <=> $timeA;
});
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan History - <?php echo SITE_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/design-system.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* page-specific only */
    </style>
</head>

<body>

    <?php include '../includes/header.php'; ?>
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- PAGE BANNER -->
        <div class="page-banner">
            <div class="banner-left">
                <div class="banner-icon"><i class="fas fa-clipboard-list"></i></div>
                <div class="banner-text">
                    <h1>Scan History</h1>
                    <p>All machine-detected disease scan records — real-time updated</p>
                </div>
            </div>
            <div class="banner-badge"><i class="fas fa-database"></i> <?= count($scans); ?> Records</div>
        </div>

        <!-- FILTER PANEL -->
        <div class="filter-panel">
            <div class="filter-panel-header"><i class="fas fa-sliders-h"></i><span>Filter Records</span></div>
            <div class="filter-body">
                <div class="filter-group">
                    <label><i class="fas fa-tree"></i> Tree ID</label>
                    <input type="text" id="filterTreeId" placeholder="Enter Tree ID (e.g., tree1 or tree10)"
                        value="<?php echo htmlspecialchars($filterTreeId); ?>" onkeyup="applyFilters()">
                </div>
                <div class="filter-actions">
                    <a href="scans.php" class="btn btn-outline-danger"><i class="fas fa-times"></i> Clear</a>
                </div>
            </div>
        </div>

        <!-- STATS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon-wrapper"><i class="fas fa-filter"></i></div>
                <div class="stat-info">
                    <h3 id="filteredCount"><?= count($filteredScans); ?></h3>
                    <p><i class="fas fa-search"></i> Filtered Results</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-wrapper"><i class="fas fa-database"></i></div>
                <div class="stat-info">
                    <h3 id="totalCount"><?= count($scans); ?></h3>
                    <p><i class="fas fa-globe"></i> Total Scans</p>
                </div>
            </div>
        </div>

        <!-- TABLE -->
        <div class="data-card">
            <div class="data-card-header">
                <span class="data-card-title"><i class="fas fa-clipboard-list"></i> Scan Records</span>
                <span class="data-card-count" id="filteredBadge"><?= count($filteredScans); ?> rows</span>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th><i class="far fa-calendar-alt"></i> Date &amp; Time</th>
                            <th><i class="fas fa-virus"></i> Disease</th>
                            <th><i class="fas fa-map-marker-alt"></i> Location</th>
                            <th><i class="fas fa-tree"></i> Tree ID</th>
                            <th><i class="fas fa-percent"></i> Confidence</th>
                            <th><i class="fas fa-image"></i> Image</th>
                        </tr>
                    </thead>
                    <tbody id="scanTableBody">
                        <?php if (empty($filteredScans)): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <div class="empty-state-icon"><i class="fas fa-search"></i></div>
                                        <h3>No scans found</h3>
                                        <p>Try adjusting the filter or check back later</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($filteredScans as $scan): ?>
                                <?php
                                $conf = normScanConfidence($scan['confidence'] ?? null);
                                $confCls = $conf !== null ? ($conf >= 80 ? 'conf-high' : ($conf >= 60 ? 'conf-med' : 'conf-low')) : '';
                                $disease = extractScanDisease($scan) ?: 'Unknown';
                                $location = resolveScanLocation($scan, $trees) ?: 'N/A';
                                $treeId = extractScanTreeId($scan) ?: 'N/A';
                                $imageUrl = $scan['image_url'] ?? $scan['imageUrl'] ?? $scan['image'] ?? '';
                                $imageBase64 = $scan['imageBase64'] ?? '';
                                $imageSrc = '';
                                if (!empty($imageBase64)) {
                                    $imageSrc = (strpos($imageBase64, 'data:image') === 0) ? $imageBase64 : 'data:image/jpeg;base64,' . $imageBase64;
                                }
                                ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($scan['timestamp'])): ?>
                                            <?php $ts = normScanTimestampMs($scan['timestamp']) / 1000; ?>
                                            <div class="date-cell">
                                                <div class="date-icon"><i class="far fa-calendar-alt"></i></div>
                                                <div class="date-text">
                                                    <span class="date-main"><?= date('M d, Y', $ts) ?></span>
                                                    <span class="date-time"><?= date('h:i A', $ts) ?></span>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge badge-disease"><i class="fas fa-virus"></i>
                                            <?= htmlspecialchars($disease); ?></span></td>
                                    <td>
                                        <div class="location-cell"><i class="fas fa-map-marker-alt"></i>
                                            <?= htmlspecialchars($location); ?>
                                        </div>
                                    </td>
                                    <td><span class="badge badge-tree"><i class="fas fa-tree"></i>
                                            <?= htmlspecialchars($treeId); ?></span></td>
                                    <td>
                                        <?php if ($conf !== null): ?>
                                            <div class="conf-wrap <?= $confCls ?>">
                                                <span class="conf-value"><?= number_format($conf, 1) ?>%</span>
                                                <div class="conf-bar-bg">
                                                    <div class="conf-bar-fill" style="width:<?= min(100, $conf) ?>%"></div>
                                                </div>
                                            </div>
                                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($imageSrc)): ?>
                                            <a href="<?= htmlspecialchars($imageSrc); ?>" target="_blank"
                                                class="btn btn-sm btn-outline"><i class="fas fa-eye"></i> View</a>
                                        <?php elseif (!empty($imageUrl)): ?>
                                            <a href="<?= htmlspecialchars($imageUrl); ?>" target="_blank"
                                                class="btn btn-sm btn-outline"><i class="fas fa-eye"></i> View</a>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
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
        function applyFilters() {
            renderRealtime(window.liveScans || {}, window.liveTrees || {});
        }
    </script>

    <!-- 🔥 FIREBASE REALTIME -->
    <script type="module">
        const _fb = window.MangoFB;
        if (_fb && _fb.db) {
            const { db, ref, onValue, escHtml } = _fb;

            function extractTreeId(scan) {
                return scan.tree_id || scan.treeId || scan.treeID || scan.tree || null;
            }

            function extractDisease(scan) {
                const fields = ['disease', 'diseaseName', 'disease_final', 'final_disease', 'predicted_disease'];
                for (const f of fields) {
                    if (scan[f]) return String(scan[f]).trim();
                }
                return '';
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

            function normConfidence(confidence) {
                if (confidence == null || confidence === '') return null;
                const value = parseFloat(confidence);
                if (isNaN(value) || value <= 0) return null;
                return value < 1 ? value * 100 : value;
            }

            function normTsMs(value) {
                let t = Number(value);
                if (t > 0 && t < 100000000000) t *= 1000;
                return t;
            }

            window.liveScans = {};
            window.liveTrees = {};

            onValue(ref(db, 'Users'), (snapshot) => {
                const users = snapshot.val() || {};
                const allScans = {};
                Object.entries(users).forEach(([uid, user]) => {
                    if (!user || typeof user !== 'object' || !user.scanResult) return;
                    Object.entries(user.scanResult).forEach(([scanId, scan]) => {
                        if (scan && typeof scan === 'object') allScans[scanId] = { ...scan, _user_id: uid };
                    });
                });
                window.liveScans = allScans;
                renderRealtime(allScans, window.liveTrees);
            });

            onValue(ref(db, 'trees'), (snapshot) => {
                window.liveTrees = snapshot.val() || {};
                renderRealtime(window.liveScans, window.liveTrees);
            });

            function getConfidenceClass(confidence) {
                if (confidence == null) return '';
                if (confidence >= 80) return 'conf-high';
                if (confidence >= 60) return 'conf-med';
                return 'conf-low';
            }

            function formatDate2(timestamp) {
                if (!timestamp) return 'N/A';
                const date = new Date(normTsMs(timestamp));
                return date.toLocaleString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
            }

            function renderRealtime(scansData, trees) {

                const tbody = document.getElementById("scanTableBody");
                const filterValue = (document.getElementById("filterTreeId").value || '').toLowerCase().trim();

                let scansArray = Object.values(scansData || {});

                if (filterValue) {
                    scansArray = scansArray.filter(scan =>
                        (extractTreeId(scan) || '').toLowerCase().includes(filterValue)
                    );
                }

                scansArray.sort((a, b) => normTsMs(b.timestamp) - normTsMs(a.timestamp));

                if (scansArray.length === 0) {
                    tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="empty-state">
                        <i class="fas fa-search"></i>
                        <p>No scans found</p>
                        <button onclick="window.location.href='scans.php'" class="btn btn-sm">
                            <i class="fas fa-redo-alt"></i> Reset Filters
                        </button>
                    </td>
                </tr>
            `;
                } else {
                    tbody.innerHTML = scansArray.map(scan => {
                        const conf = normConfidence(scan.confidence);
                        const confCls = getConfidenceClass(conf);
                        const confHtml = conf !== null
                            ? `<div class="conf-wrap ${confCls}"><span class="conf-value">${conf.toFixed(1)}%</span><div class="conf-bar-bg"><div class="conf-bar-fill" style="width:${Math.min(100, conf)}%"></div></div></div>`
                            : `<span class="text-muted">—</span>`;
                        const disease = extractDisease(scan) || 'Unknown';
                        const treeId = extractTreeId(scan) || 'N/A';
                        const location = resolveLocation(scan, trees) || 'N/A';
                        const imageBase64 = scan.imageBase64 || '';
                        const imageUrl = scan.image_url || scan.imageUrl || scan.image || '';
                        const imageHref = imageBase64
                            ? (imageBase64.indexOf('data:image') === 0 ? imageBase64 : 'data:image/jpeg;base64,' + imageBase64)
                            : (imageUrl || '');
                        const imageHtml = imageHref
                            ? `<a href="${escHtml(imageHref)}" target="_blank" class="btn btn-sm btn-outline"><i class="fas fa-eye"></i> View</a>`
                            : '<span class="text-muted">—</span>';
                        const dateStr = formatDate2(scan.timestamp);
                        // split "Mon DD, YYYY, HH:MM:SS AM" for two-line display
                        const [datePart, timePart] = dateStr.split(', ').reduce((acc, p, i) => { if (i < 2) acc[0] = (acc[0] ? acc[0] + ', ' : '') + p; else acc[1] = p; return acc; }, ['', '']);
                        return `
                    <tr>
                        <td><div class="date-cell"><div class="date-icon"><i class="far fa-calendar-alt"></i></div><div class="date-text"><span class="date-main">${datePart || dateStr}</span><span class="date-time">${timePart || ''}</span></div></div></td>
                        <td><span class="badge badge-disease"><i class="fas fa-virus"></i> ${escHtml(disease)}</span></td>
                        <td><div class="location-cell"><i class="fas fa-map-marker-alt"></i> ${escHtml(location)}</div></td>
                        <td><span class="badge badge-tree"><i class="fas fa-tree"></i> ${escHtml(treeId)}</span></td>
                        <td>${confHtml}</td>
                        <td>${imageHtml}</td>
                    </tr>`;
                    }).join('');
                }

                const filteredEl = document.getElementById("filteredCount");
                if (filteredEl) filteredEl.textContent = scansArray.length;
                const totalEl = document.getElementById("totalCount");
                if (totalEl) totalEl.textContent = Object.keys(scansData || {}).length;
            }

            window.renderRealtime = renderRealtime;
        }
    </script>

</body>

</html>