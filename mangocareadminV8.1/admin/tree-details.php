<?php
require_once '../config/config.php';
requireLogin();

$firebase = new Firebase();
$treeId = $_GET['id'] ?? '';

if (empty($treeId)) {
    header('Location: trees.php');
    exit();
}

// Get Tree Details
$tree = $firebase->get('trees/' . $treeId);
if (!$tree) {
    header('Location: trees.php?error=Tree not found');
    exit();
}

// Get Scan History
$allScans = $firebase->get('scans') ?? [];
$treeScans = array_filter($allScans, function($scan) use ($treeId) {
    return isset($scan['tree_id']) && $scan['tree_id'] === $treeId;
});

// Sort scans newest first
usort($treeScans, function($a, $b) {
    return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
});

// Get Treatment History
$allHistory = $firebase->get('spray_history') ?? [];
$treatmentHistory = array_filter($allHistory, function($h) use ($treeId) {
    return isset($h['tree_id']) && $h['tree_id'] === $treeId;
});

// Sort history newest first
usort($treatmentHistory, function($a, $b) {
    return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
});

// Get Future Schedules
$schedules = $tree['spray_schedules'] ?? [];
$pendingSchedules = array_filter($schedules, function($s) {
    return isset($s['status']) && $s['status'] === 'pending';
});

// Helper for image URLs
function getImageUrl($path) {
    if (empty($path)) return '../assets/images/no-image.png';
    if (strpos($path, 'http') === 0) return $path;
    return $path;
}

// Statistics
$totalScans = count($treeScans);
$totalTreatments = count($treatmentHistory);
$lastTreatment = !empty($treatmentHistory) ? reset($treatmentHistory) : null;

// QR Code Data
$hasQr = isset($tree['has_qr']) && $tree['has_qr'];
$qrCodeUrl = '';
$qrLocation = $tree['location'] ?? 'N/A';
if ($hasQr && isset($tree['qr_data'])) {
    $decodedQr = json_decode($tree['qr_data'], true);
    if ($decodedQr) {
        $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode(json_encode($decodedQr));
        $qrLocation = $decodedQr['location'] ?? $qrLocation;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tree Details: <?= htmlspecialchars($treeId) ?> - <?= SITE_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/design-system.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-app.js"></script>
    <script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-database.js"></script>
    <style>
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
                    <h1>Tree Info: <?= htmlspecialchars($treeId) ?></h1>
                    <p>Detailed view of records and schedules</p>
                </div>
            </div>
            <div class="filter-actions">
                <?php if ($hasQr): ?>
                    <button type="button" onclick="showQRModal('<?= htmlspecialchars($treeId) ?>', '<?= htmlspecialchars($qrLocation) ?>', '<?= htmlspecialchars($qrCodeUrl) ?>')" class="btn btn-secondary" style="margin-right: 10px;">
                        <i class="fas fa-qrcode"></i> View QR Code
                    </button>
                <?php else: ?>
                    <a href="qr-generator.php?tree_id=<?= urlencode($treeId) ?>&location=<?= urlencode($tree['location'] ?? '') ?>" class="btn btn-outline" style="margin-right: 10px;">
                        <i class="fas fa-qrcode"></i> Generate QR Code
                    </a>
                <?php endif; ?>
                <a href="qr-generator.php?schedule_for=<?= urlencode($treeId) ?>#schedule-form" class="btn btn-primary" style="margin-right: 10px; background: var(--secondary-color); border-color: var(--secondary-color);">
                    <i class="fas fa-calendar-plus"></i> Schedule Spray
                </a>
                <a href="trees.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Back to List</a>
            </div>
        </div>

        <!-- QUICK STATS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon-wrapper"><i class="fas fa-microscope"></i></div>
                <div class="stat-info">
                    <h3 id="tdStatScans"><?= $totalScans ?></h3>
                    <p>Total Scans</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-wrapper"><i class="fas fa-tint"></i></div>
                <div class="stat-info">
                    <h3 id="tdStatHistory"><?= $totalTreatments ?></h3>
                    <p>History</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-wrapper"><i class="fas fa-clock"></i></div>
                <div class="stat-info">
                    <h3 id="tdStatPending"><?= count($pendingSchedules) ?></h3>
                    <p>Pending</p>
                </div>
            </div>
        </div>

        <!-- TREE INFORMATION -->
        <div class="data-card" style="margin-bottom: 25px;">
            <div class="data-card-header">
                <span class="data-card-title"><i class="fas fa-info-circle"></i> Basic Metadata</span>
            </div>
            <div style="padding: 20px;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div>
                        <small style="color: grey; text-transform: uppercase; font-size: 10px; display: block; margin-bottom: 4px;">Location</small>
                        <strong><?= htmlspecialchars($tree['location'] ?? 'N/A') ?></strong>
                    </div>
                    <div>
                        <small style="color: grey; text-transform: uppercase; font-size: 10px; display: block; margin-bottom: 4px;">Coordinates</small>
                        <strong><?= isset($tree['lat']) ? $tree['lat'] . ', ' . $tree['lng'] : 'Not Linked' ?></strong>
                    </div>
                    <div>
                        <small style="color: grey; text-transform: uppercase; font-size: 10px; display: block; margin-bottom: 4px;">Registration Date</small>
                        <strong><?= isset($tree['timestamp']) ? date('M d, Y', $tree['timestamp'] / 1000) : 'N/A' ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- SCAN HISTORY -->
        <div class="data-card" style="margin-bottom: 25px;">
            <div class="data-card-header">
                <span class="data-card-title"><i class="fas fa-history"></i> Scan Results</span>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Disease Found</th>
                            <th>Confidence</th>
                            <th>Image</th>
                        </tr>
                    </thead>
                    <tbody id="tdScanTbody">
                        <?php if (empty($treeScans)): ?>
                            <tr><td colspan="4" style="text-align: center;">No scans recorded for this tree.</td></tr>
                        <?php else: ?>
                            <?php foreach ($treeScans as $scan): ?>
                                <tr>
                                    <td><?= date('M d, Y H:i', ($scan['timestamp'] ?? 0) / 1000) ?></td>
                                    <td>
                                        <span class="badge badge-warning"><?= htmlspecialchars($scan['disease'] ?? 'Unknown') ?></span>
                                    </td>
                                    <td><?= round(($scan['confidence'] ?? 0) * 100, 1) ?>%</td>
                                    <td>
                                        <?php if (isset($scan['image_url']) || isset($scan['imageUrl'])): ?>
                                            <a href="<?= htmlspecialchars($scan['image_url'] ?? $scan['imageUrl']) ?>" target="_blank" class="badge badge-tree">View Captured</a>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TREATMENT HISTORY -->
        <div class="data-card">
            <div class="data-card-header">
                <span class="data-card-title"><i class="fas fa-check-circle"></i> Maintenance History</span>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Activity Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="tdHistTbody">
                        <?php if (empty($treatmentHistory)): ?>
                            <tr><td colspan="2" style="text-align: center;">No maintenance history found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($treatmentHistory as $h): ?>
                                <tr>
                                    <td><?= date('M d, Y H:i', ($h['timestamp'] ?? 0) / 1000) ?></td>
                                    <td><span class="badge badge-success">Completed</span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- QR Code Modal -->
    <div id="qrModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeQRModal()">&times;</span>
            <h2><i class="fas fa-qrcode"></i>Tree QR Code</h2>
            <div id="modalQRContent"></div>
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

    <script type="module">
        const _fb = window.MangoFB;
        if (_fb && _fb.db) {
            const { db, ref, onValue, escHtml, formatDate } = _fb;
            const TREE_ID = <?= json_encode($treeId); ?>;
            const _snaps = { trees: null, scans: null, history: null };
            function maybeRender() {
                if (_snaps.trees && _snaps.scans && _snaps.history) renderDetails();
            }
            onValue(ref(db, 'trees'), (s) => { _snaps.trees = s; maybeRender(); });
            onValue(ref(db, 'scans'), (s) => { _snaps.scans = s; maybeRender(); });
            onValue(ref(db, 'spray_history'), (s) => { _snaps.history = s; maybeRender(); });

            function fmt(ts) {
                const v = Number(ts || 0);
                if (!v) return 'N/A';
                return formatDate(v > 9999999999 ? v : v * 1000, 'M d, Y H:i');
            }

            function renderDetails() {
                const trees = _snaps.trees.val() || {};
                const tree = trees[TREE_ID] || {};
                const scans = Object.values(_snaps.scans.val() || {}).filter(s => s && s.tree_id === TREE_ID);
                const history = Object.values(_snaps.history.val() || {}).filter(h => h && h.tree_id === TREE_ID)
                    .sort((a, b) => (b.timestamp || 0) - (a.timestamp || 0));
                const scheds = (tree.spray_schedules && typeof tree.spray_schedules === 'object') ? Object.values(tree.spray_schedules) : [];
                const pending = scheds.filter(s => s && (s.status || 'pending') === 'pending').length;

                const setText = (id, txt) => { const el = document.getElementById(id); if (el) el.textContent = txt; };
                setText('tdStatScans', scans.length);
                setText('tdStatHistory', history.length);
                setText('tdStatPending', pending);

                const scanTb = document.getElementById('tdScanTbody');
                if (scanTb) {
                    if (scans.length === 0) {
                        scanTb.innerHTML = `<tr><td colspan="4" style="text-align: center;">No scans recorded for this tree.</td></tr>`;
                    } else {
                        scanTb.innerHTML = scans.sort((a, b) => (b.timestamp || 0) - (a.timestamp || 0)).map(scan => {
                            const conf = Math.round(Number(scan.confidence || 0) * 100 * 10) / 10;
                            const img = scan.image_url || scan.imageUrl || '';
                            const imgCell = img
                                ? `<a href="${escHtml(img)}" target="_blank" class="badge badge-tree">View Captured</a>`
                                : 'N/A';
                            return `
                                <tr>
                                    <td>${fmt(scan.timestamp)}</td>
                                    <td><span class="badge badge-warning">${escHtml(scan.disease || 'Unknown')}</span></td>
                                    <td>${conf}%</td>
                                    <td>${imgCell}</td>
                                </tr>`;
                        }).join('');
                    }
                }

                const histTb = document.getElementById('tdHistTbody');
                if (histTb) {
                    if (history.length === 0) {
                        histTb.innerHTML = `<tr><td colspan="2" style="text-align: center;">No maintenance history found.</td></tr>`;
                    } else {
                        histTb.innerHTML = history.map(h => `
                            <tr>
                                <td>${fmt(h.timestamp)}</td>
                                <td><span class="badge badge-success">Completed</span></td>
                            </tr>`).join('');
                    }
                }
            }
        }
    </script>
    <script src="../assets/js/main.js"></script>
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
            qrModal.classList.add('show');
        }

        function closeQRModal() {
            qrModal.classList.remove('show');
        }

        window.onclick = function(event) {
            if (event.target === qrModal) {
                closeQRModal();
            }
        };

        function downloadQRCode(treeId, location, qrUrl) {
            const img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = function() {
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
                    
                    canvas.toBlob(function(blob) {
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
            printImg.onload = function() {
                setTimeout(function() {
                    window.print();
                    printContainer.style.display = 'none';
                }, 200);
            };
            printImg.src = qrUrl;
        }
    </script>
</body>
</html>
