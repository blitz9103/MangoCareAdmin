<?php
require_once '../config/config.php';
requireLogin();

$firebase = new Firebase();
processAutomaticSchedules($firebase);

// Pre-fetch notifications for initial server-side render
$notifications = $firebase->get('notifications') ?? [];
$notifications = is_array($notifications) ? $notifications : [];

// Sort descending by timestamp
uasort($notifications, function($a, $b) {
    return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
});

$totalNotifs   = count($notifications);
$unreadNotifs  = 0;
$criticalCount = 0;
$sprayCount    = 0;
$diseaseCount  = 0;
$scanCount     = 0;
$treeCount     = 0;

foreach ($notifications as $n) {
    if (empty($n['read'])) $unreadNotifs++;
    if (($n['priority'] ?? '') === 'critical') $criticalCount++;
    if (($n['category'] ?? '') === 'spray') $sprayCount++;
    if (($n['category'] ?? '') === 'disease') $diseaseCount++;
    if (($n['category'] ?? '') === 'scan') $scanCount++;
    if (($n['category'] ?? '') === 'tree') $treeCount++;
}

// ── Server-side helpers for the initial feed render ─────────────────────────
function nEsc($str) {
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}
function nPriIcon($p) {
    switch ($p) {
        case 'critical': return 'fa-exclamation-triangle';
        case 'high':     return 'fa-exclamation-circle';
        case 'medium':   return 'fa-info-circle';
        default:         return 'fa-check-circle';
    }
}
function nCatIcon($c) {
    switch ($c) {
        case 'spray':  return 'fa-spray-can';
        case 'disease': return 'fa-virus';
        case 'tree':   return 'fa-tree';
        case 'scan':   return 'fa-microscope';
        default:       return 'fa-shield-virus';
    }
}
function nTimeAgo($ts) {
    if (!$ts) return 'Just now';
    $seconds = max(0, time() - (int)((int) $ts / 1000));
    if ($seconds < 60) return 'Just now';
    $minutes = floor($seconds / 60);
    if ($minutes < 60) return "{$minutes}m ago";
    $hours = floor($minutes / 60);
    if ($hours < 24) return "{$hours}h ago";
    $days = floor($hours / 24);
    if ($days < 30) return "{$days}d ago";
    return floor($days / 30) . "mo ago";
}

// Build the initial feed markup so the page shows data even before the
// real-time Firebase module finishes loading on the client.
$feedHtml = '';
if (!empty($notifications)) {
    foreach ($notifications as $n) {
        if (!is_array($n)) continue;
        $id        = nEsc($n['id'] ?? '');
        $title     = nEsc($n['title'] ?? 'Notification');
        $desc      = nEsc($n['description'] ?? '');
        $treeId    = nEsc($n['tree_id'] ?? '');
        $category  = nEsc($n['category'] ?? 'general');
        $priority  = nEsc($n['priority'] ?? 'low');
        $target    = nEsc($n['target_url'] ?? 'schedules.php');
        $prioClass = 'notif-icon-' . nEsc($n['priority'] ?? 'low');
        $prioBadge = 'priority-' . nEsc($n['priority'] ?? 'low');
        $prioIcon  = nPriIcon($n['priority'] ?? 'low');
        $catIcon   = nCatIcon($n['category'] ?? 'general');
        $timeAgo   = nTimeAgo($n['timestamp'] ?? 0);
        $fullDate  = !empty($n['timestamp']) ? date('M j, Y g:i A', (int)($n['timestamp'] / 1000)) : '';
        $isUnread  = empty($n['read']) ? 'unread' : '';

        $feedHtml .= '<div class="notif-card ' . $isUnread . '">';
        $feedHtml .= '<div class="notif-card-icon ' . $prioClass . '"><i class="fas ' . $prioIcon . '"></i></div>';
        $feedHtml .= '<div class="notif-card-body">';
        $feedHtml .= '<div class="notif-card-header-row">';
        $feedHtml .= '<h3 class="notif-card-title">' . $title . '</h3>';
        $feedHtml .= '<span class="notif-card-time" title="' . $fullDate . '"><i class="far fa-clock"></i> ' . $timeAgo . '</span>';
        $feedHtml .= '</div>';
        $feedHtml .= '<p class="notif-card-desc">' . $desc . '</p>';
        $feedHtml .= '<div class="notif-card-footer-row">';
        $feedHtml .= '<span class="notif-badge-pill ' . $prioBadge . '"><i class="fas fa-flag"></i> ' . $priority . '</span>';
        $feedHtml .= '<span class="notif-badge-pill" style="background:#f1f5f9; color:#475569;"><i class="fas ' . $catIcon . '"></i> ' . $category . '</span>';
        if ($treeId !== '') {
            $feedHtml .= '<a href="tree_map.php" class="notif-badge-pill" style="background:#e2e8f0; color:#004737; text-decoration:none;"><i class="fas fa-tree"></i> ' . $treeId . '</a>';
        }
        $feedHtml .= '<div class="notif-card-actions">';
        $jsId = str_replace(['\\', "'"], ['\\\\', "\\'"], $n['id'] ?? '');
        $feedHtml .= '<button type="button" class="btn-card-action" onclick="window.MangoNotifs.markRead(\'' . $jsId . '\');">';
        $feedHtml .= '<i class="fas ' . (empty($n['read']) ? 'fa-check' : 'fa-envelope') . '"></i> ' . (empty($n['read']) ? 'Mark Read' : 'Mark Unread') . '</button>';
        $feedHtml .= '<a href="' . $target . '" class="btn-card-action"><i class="fas fa-external-link-alt"></i> View</a>';
        $feedHtml .= '<button type="button" class="btn-card-action danger" onclick="window.MangoNotifs.deleteNotif(\'' . $jsId . '\');"><i class="fas fa-trash"></i> Delete</button>';
        $feedHtml .= '</div>';
        $feedHtml .= '</div>';
        $feedHtml .= '</div>';
        $feedHtml .= '</div>';
    }
} else {
    $feedHtml = '<div style="background:#fff; border-radius:12px; padding:48px 24px; text-align:center; border:1px solid #e2e8f0; color:#94a3b8;">';
    $feedHtml .= '<i class="fas fa-bell-slash" style="font-size:3rem; color:#cbd5e1; margin-bottom:12px;"></i>';
    $feedHtml .= '<h3 style="color:#334155; font-weight:700; margin:0 0 6px 0;">No Notifications Found</h3>';
    $feedHtml .= '<p style="margin:0; font-size:0.9rem;">No records match your selected filter criteria.</p>';
    $feedHtml .= '</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Center - <?php echo SITE_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/design-system.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ── DEDICATED NOTIFICATION PAGE STYLES ── */
        .notif-kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
            margin-bottom: 1.5rem;
        }

        .notif-kpi-card {
            background: #ffffff;
            border-radius: 14px;
            padding: 1.25rem 1.5rem;
            box-shadow: var(--shadow-card, 0 4px 12px rgba(0,71,55,0.06));
            border: 1px solid rgba(112, 185, 161, 0.2);
            display: flex;
            align-items: center;
            gap: 1.25rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .notif-kpi-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 71, 55, 0.12);
        }

        .notif-kpi-icon {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
        }

        .kpi-icon-total    { background: #e0f2fe; color: #0284c7; }
        .kpi-icon-unread   { background: #dcfce7; color: #16a34a; }
        .kpi-icon-critical { background: #fee2e2; color: #dc2626; }
        .kpi-icon-spray    { background: #fef9c3; color: #ca8a04; }

        .notif-kpi-info h3 {
            font-size: 1.6rem;
            font-weight: 800;
            color: #004737;
            margin: 0 0 2px 0;
            line-height: 1;
        }

        .notif-kpi-info p {
            font-size: 0.85rem;
            color: #64748b;
            margin: 0;
            font-weight: 500;
        }

        /* Filter Tabs */
        .notif-tabs-bar {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            overflow-x: auto;
            padding-bottom: 0.5rem;
            margin-bottom: 1.25rem;
            border-bottom: 2px solid #e2e8f0;
            scrollbar-width: thin;
        }

        .notif-tab-btn {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            color: #475569;
            padding: 0.55rem 1.1rem;
            border-radius: 20px;
            font-size: 0.88rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            transition: all 0.2s ease;
        }

        .notif-tab-btn:hover {
            background: #f1f5f9;
            color: #004737;
            border-color: #70B9A1;
        }

        .notif-tab-btn.active {
            background: #004737;
            color: #ffffff;
            border-color: #004737;
            box-shadow: 0 4px 10px rgba(0, 71, 55, 0.2);
        }

        .notif-tab-badge {
            background: rgba(0,0,0,0.12);
            color: inherit;
            padding: 1px 7px;
            border-radius: 10px;
            font-size: 0.75rem;
        }

        .notif-tab-btn.active .notif-tab-badge {
            background: rgba(255,255,255,0.25);
            color: #ffffff;
        }

        /* Toolbar */
        .notif-toolbar {
            background: #ffffff;
            border-radius: 12px;
            padding: 0.85rem 1.25rem;
            margin-bottom: 1.25rem;
            border: 1px solid rgba(112, 185, 161, 0.2);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .notif-search-box {
            position: relative;
            flex: 1;
            min-width: 240px;
        }

        .notif-search-box input {
            width: 100%;
            padding: 0.55rem 1rem 0.55rem 2.4rem;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 0.9rem;
            outline: none;
            transition: border-color 0.2s;
        }

        .notif-search-box input:focus {
            border-color: #004737;
        }

        .notif-search-box i {
            position: absolute;
            left: 0.85rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        .notif-action-btns {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Notifications Feed Cards */
        .notif-feed-container {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .notif-card {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 1.1rem 1.25rem;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            transition: all 0.2s ease;
            position: relative;
        }

        .notif-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border-color: #cbd5e1;
        }

        .notif-card.unread {
            background: #f0fdf4;
            border-left: 5px solid #004737;
        }

        .notif-card-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .notif-card-body {
            flex: 1;
            min-width: 0;
        }

        .notif-card-header-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            margin-bottom: 4px;
        }

        .notif-card-title {
            font-size: 0.98rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
        }

        .notif-card-time {
            font-size: 0.78rem;
            color: #94a3b8;
            white-space: nowrap;
        }

        .notif-card-desc {
            font-size: 0.88rem;
            color: #475569;
            margin: 0 0 10px 0;
            line-height: 1.45;
        }

        .notif-card-footer-row {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .notif-badge-pill {
            font-size: 0.75rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            text-transform: uppercase;
        }

        .notif-card-actions {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-card-action {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            color: #475569;
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s;
        }

        .btn-card-action:hover {
            background: #004737;
            color: #ffffff;
            border-color: #004737;
        }

        .btn-card-action.danger:hover {
            background: #dc2626;
            color: #ffffff;
            border-color: #dc2626;
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
                <div class="banner-icon"><i class="fas fa-bell"></i></div>
                <div class="banner-text">
                    <h1>Notification Center</h1>
                    <p>Real-time farm activity alerts, spray reminders, and critical disease monitoring</p>
                </div>
            </div>
            <div class="banner-badge"><i class="fas fa-layer-group"></i> Real-time Monitoring</div>
        </div>

        <!-- KPI SUMMARY CARDS -->
        <div class="notif-kpi-grid">
            <div class="notif-kpi-card">
                <div class="notif-kpi-icon kpi-icon-total">
                    <i class="fas fa-bell"></i>
                </div>
                <div class="notif-kpi-info">
                    <h3 id="kpiTotal"><?php echo $totalNotifs; ?></h3>
                    <p>Total Notifications</p>
                </div>
            </div>

            <div class="notif-kpi-card">
                <div class="notif-kpi-icon kpi-icon-unread">
                    <i class="fas fa-envelope-open-text"></i>
                </div>
                <div class="notif-kpi-info">
                    <h3 id="kpiUnread"><?php echo $unreadNotifs; ?></h3>
                    <p>Unread Alerts</p>
                </div>
            </div>

            <div class="notif-kpi-card">
                <div class="notif-kpi-icon kpi-icon-critical">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="notif-kpi-info">
                    <h3 id="kpiCritical"><?php echo $criticalCount; ?></h3>
                    <p>Critical Alerts</p>
                </div>
            </div>

            <div class="notif-kpi-card">
                <div class="notif-kpi-icon kpi-icon-spray">
                    <i class="fas fa-spray-can"></i>
                </div>
                <div class="notif-kpi-info">
                    <h3 id="kpiSpray"><?php echo $sprayCount; ?></h3>
                    <p>Spray Reminders</p>
                </div>
            </div>
        </div>

        <!-- FILTER TABS BAR -->
        <div class="notif-tabs-bar">
            <button type="button" class="notif-tab-btn active" data-filter="all" onclick="filterNotifs('all', this)">
                <i class="fas fa-list"></i> All Notifications <span class="notif-tab-badge" id="tabBadgeAll"><?php echo $totalNotifs; ?></span>
            </button>

            <button type="button" class="notif-tab-btn" data-filter="unread" onclick="filterNotifs('unread', this)">
                <i class="fas fa-envelope"></i> Unread <span class="notif-tab-badge" id="tabBadgeUnread"><?php echo $unreadNotifs; ?></span>
            </button>

            <button type="button" class="notif-tab-btn" data-filter="critical" onclick="filterNotifs('critical', this)">
                <i class="fas fa-shield-virus" style="color: #dc2626;"></i> Critical Alerts <span class="notif-tab-badge" id="tabBadgeCritical"><?php echo $criticalCount; ?></span>
            </button>

            <button type="button" class="notif-tab-btn" data-filter="spray" onclick="filterNotifs('spray', this)">
                <i class="fas fa-spray-can" style="color: #ea580c;"></i> Spray Schedules <span class="notif-tab-badge" id="tabBadgeSpray"><?php echo $sprayCount; ?></span>
            </button>

            <button type="button" class="notif-tab-btn" data-filter="disease" onclick="filterNotifs('disease', this)">
                <i class="fas fa-virus" style="color: #16a34a;"></i> Disease Alerts <span class="notif-tab-badge" id="tabBadgeDisease"><?php echo $diseaseCount; ?></span>
            </button>

            <button type="button" class="notif-tab-btn" data-filter="scan" onclick="filterNotifs('scan', this)">
                <i class="fas fa-microscope" style="color: #0284c7;"></i> Scan Activity <span class="notif-tab-badge" id="tabBadgeScan"><?php echo $scanCount; ?></span>
            </button>

            <button type="button" class="notif-tab-btn" data-filter="tree" onclick="filterNotifs('tree', this)">
                <i class="fas fa-tree" style="color: #16a34a;"></i> Tree Management <span class="notif-tab-badge" id="tabBadgeTree"><?php echo $treeCount; ?></span>
            </button>
        </div>

        <!-- TOOLBAR & SEARCH -->
        <div class="notif-toolbar">
            <div class="notif-search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="notifSearchInput" placeholder="Search by title, description, or Tree ID..." onkeyup="renderFeed()">
            </div>

            <div class="notif-action-btns">
                <button type="button" class="btn btn-sm btn-outline" onclick="window.MangoNotifs.markAllRead()">
                    <i class="fas fa-check-double"></i> Mark All as Read
                </button>
                <button type="button" class="btn btn-sm btn-outline danger" onclick="window.MangoNotifs.clearReadNotifs()">
                    <i class="fas fa-trash-alt"></i> Clear Read
                </button>
            </div>
        </div>

        <!-- NOTIFICATION FEED LIST -->
        <?php /* Feed initially rendered server-side; real-time listener re-renders once MangoNotifs._ready */ ?>
        <div class="notif-feed-container" id="notifFeed">
            <?php echo $feedHtml; ?>
        </div>
    </main>

    <script src="../assets/js/main.js"></script>
    <script>
        let currentFilter = 'all';

        function esc(str) {
            return String(str ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        }

        function filterNotifs(filter, btn) {
            currentFilter = filter;
            document.querySelectorAll('.notif-tab-btn').forEach(b => b.classList.remove('active'));
            if (btn) btn.classList.add('active');
            renderFeed();
        }

        function renderFeed() {
            const feedEl = document.getElementById('notifFeed');
            const searchVal = (document.getElementById('notifSearchInput')?.value || '').toLowerCase().trim();
            if (!window.MangoNotifs || !Array.isArray(window.MangoNotifs.items) || !window.MangoNotifs._ready) return;
            const items = window.MangoNotifs.items;

            // 1. Update KPI stats
            const total = items.length;
            const unread = items.filter(i => !i.read).length;
            const critical = items.filter(i => i.priority === 'critical').length;
            const spray = items.filter(i => i.category === 'spray').length;
            const disease = items.filter(i => i.category === 'disease').length;
            const scan = items.filter(i => i.category === 'scan').length;
            const tree = items.filter(i => i.category === 'tree').length;

            document.getElementById('kpiTotal').textContent = total;
            document.getElementById('kpiUnread').textContent = unread;
            document.getElementById('kpiCritical').textContent = critical;
            document.getElementById('kpiSpray').textContent = spray;

            document.getElementById('tabBadgeAll').textContent = total;
            document.getElementById('tabBadgeUnread').textContent = unread;
            document.getElementById('tabBadgeCritical').textContent = critical;
            const tabBadgeSpray = document.getElementById('tabBadgeSpray');
            if (tabBadgeSpray) tabBadgeSpray.textContent = spray;
            const tabBadgeDisease = document.getElementById('tabBadgeDisease');
            if (tabBadgeDisease) tabBadgeDisease.textContent = disease;
            const tabBadgeScan = document.getElementById('tabBadgeScan');
            if (tabBadgeScan) tabBadgeScan.textContent = scan;
            const tabBadgeTree = document.getElementById('tabBadgeTree');
            if (tabBadgeTree) tabBadgeTree.textContent = tree;

            // 2. Filter items
            const filtered = items.filter(item => {
                // Category/Status filter
                if (currentFilter === 'unread' && item.read) return false;
                if (currentFilter === 'critical' && item.priority !== 'critical') return false;
                if (currentFilter === 'spray' && item.category !== 'spray') return false;
                if (currentFilter === 'disease' && item.category !== 'disease') return false;
                if (currentFilter === 'scan' && item.category !== 'scan') return false;
                if (currentFilter === 'tree' && item.category !== 'tree') return false;

                // Search query filter
                if (searchVal) {
                    const title = (item.title || '').toLowerCase();
                    const desc = (item.description || '').toLowerCase();
                    const treeId = (item.tree_id || '').toLowerCase();
                    return title.includes(searchVal) || desc.includes(searchVal) || treeId.includes(searchVal);
                }

                return true;
            });

            // 3. Render HTML
            if (filtered.length === 0) {
                feedEl.innerHTML = `
                    <div style="background:#fff; border-radius:12px; padding:48px 24px; text-align:center; border:1px solid #e2e8f0; color:#94a3b8;">
                        <i class="fas fa-bell-slash" style="font-size:3rem; color:#cbd5e1; margin-bottom:12px;"></i>
                        <h3 style="color:#334155; font-weight:700; margin:0 0 6px 0;">No Notifications Found</h3>
                        <p style="margin:0; font-size:0.9rem;">No records match your selected filter criteria.</p>
                    </div>`;
                return;
            }

            feedEl.innerHTML = filtered.map(item => {
                const iconClass = window.MangoNotifs.getPriorityIcon(item.priority);
                const prioClass = `notif-icon-${item.priority || 'low'}`;
                const prioBadge = `priority-${item.priority || 'low'}`;
                const isUnread  = !item.read ? 'unread' : '';
                const timeAgo   = window.MangoNotifs.getTimeAgo(item.timestamp);
                const ts        = Number(item.timestamp) || 0;
                const fullDate  = ts ? new Date(ts).toLocaleString() : '';
                const target    = esc(item.target_url || 'schedules.php');
                const title     = esc(item.title || 'Notification');
                const desc      = esc(item.description || '');
                const treeId    = esc(item.tree_id || '');
                const category  = esc(item.category || 'general');
                const priority  = esc(item.priority || 'low');
                const id        = esc(item.id || '');

                return `
                    <div class="notif-card ${isUnread}">
                        <div class="notif-card-icon ${prioClass}">
                            <i class="fas ${iconClass}"></i>
                        </div>
                        <div class="notif-card-body">
                            <div class="notif-card-header-row">
                                <h3 class="notif-card-title">${title}</h3>
                                <span class="notif-card-time" title="${fullDate}">
                                    <i class="far fa-clock"></i> ${timeAgo}
                                </span>
                            </div>
                            <p class="notif-card-desc">${desc}</p>
                            <div class="notif-card-footer-row">
                                <span class="notif-badge-pill ${prioBadge}">
                                    <i class="fas fa-flag"></i> ${priority}
                                </span>
                                <span class="notif-badge-pill" style="background:#f1f5f9; color:#475569;">
                                    <i class="fas ${window.MangoNotifs.getCategoryIcon(item.category)}"></i> ${category}
                                </span>
                                ${treeId ? `
                                    <a href="tree_map.php" class="notif-badge-pill" style="background:#e2e8f0; color:#004737; text-decoration:none;">
                                        <i class="fas fa-tree"></i> ${treeId}
                                    </a>` : ''}

                                <div class="notif-card-actions">
                                    <button type="button" class="btn-card-action" onclick="window.MangoNotifs.markRead('${id}');">
                                        <i class="fas ${item.read ? 'fa-envelope' : 'fa-check'}"></i> ${item.read ? 'Mark Unread' : 'Mark Read'}
                                    </button>
                                    <a href="${target}" class="btn-card-action">
                                        <i class="fas fa-external-link-alt"></i> View
                                    </a>
                                    <button type="button" class="btn-card-action danger" onclick="window.MangoNotifs.deleteNotif('${id}');">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>`;
            }).join('');
        }

        // Listen for updates from real-time Firebase listener in header
        window.addEventListener('mangoNotifsUpdated', () => {
            renderFeed();
        });

        // Initial render fallback
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(renderFeed, 400);
        });
    </script>
</body>
</html>
