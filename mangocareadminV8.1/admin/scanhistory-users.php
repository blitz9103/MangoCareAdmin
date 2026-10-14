<?php
require_once '../config/config.php';
requireLogin();

$firebase = new Firebase();

/* =========================================
   GET ALL SCANS FROM ALL USERS
========================================= */

$users = $firebase->get('Users') ?? [];
$users = is_array($users) ? $users : [];

/* =========================================
   GET TREE DATA FOR ENHANCED DISPLAY
========================================= */

$trees = $firebase->get('trees') ?? [];
$trees = is_array($trees) ? $trees : [];

/* ---------- helpers ---------- */

function scanhistory_extract_tree_id($scan)
{
    return $scan['treeId'] ?? $scan['tree_id'] ?? $scan['treeID'] ?? $scan['tree'] ?? '';
}

function scanhistory_resolve_location($scan, $trees)
{
    $treeId = scanhistory_extract_tree_id($scan);
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

function scanhistory_norm_confidence($value)
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

function scanhistory_norm_timestamp_ms($value)
{
    $t = (int) $value;
    if ($t > 0 && $t < 100000000000)
        return $t * 1000;
    return $t;
}

/* =========================================
   COLLECT SCANS (Users → scanResult)
========================================= */

$scans = [];

foreach ($users as $uid => $user) {

    if (isset($user['scanResult']) && is_array($user['scanResult'])) {

        foreach ($user['scanResult'] as $scanId => $scan) {

            if (!is_array($scan))
                continue;

            $scan['scan_id'] = $scanId;
            $scan['treeId'] = scanhistory_extract_tree_id($scan) ?: 'N/A';

            // Attach user info
            $scan['user_name'] = $user['name'] ?? 'Unknown';
            $scan['user_email'] = $user['email'] ?? 'N/A';

            // Resolve placeholder location from tree (case-insensitive)
            $scan['location'] = scanhistory_resolve_location($scan, $trees);

            $scans[] = $scan;
        }
    }
}

/* =========================================
   FILTERS
========================================= */

$filterDisease = $_GET['disease'] ?? '';
$filterLocation = $_GET['location'] ?? '';
$filterTreeId = $_GET['treeId'] ?? '';

$filteredScans = $scans;

/* ---- Disease Filter ---- */
if (!empty($filterDisease)) {
    $filteredScans = array_filter($filteredScans, function ($scan) use ($filterDisease) {

        $disease = $scan['disease'] ?? $scan['diseaseName'] ?? '';

        return strtolower(trim($disease)) ===
            strtolower(trim($filterDisease));
    });
}

/* ---- Location Filter ---- */
if (!empty($filterLocation)) {
    $filteredScans = array_filter($filteredScans, function ($scan) use ($filterLocation) {

        return isset($scan['location']) &&
            strtolower(trim($scan['location'])) ===
            strtolower(trim($filterLocation));
    });
}

/* ---- Tree ID Filter ---- */
if (!empty($filterTreeId)) {
    $filteredScans = array_filter($filteredScans, function ($scan) use ($filterTreeId) {

        return isset($scan['treeId']) &&
            strtolower(trim($scan['treeId'])) ===
            strtolower(trim($filterTreeId));
    });
}

/* =========================================
   UNIQUE FILTER VALUES
========================================= */

$diseases = [];
$locations = [];
$treeIds = [];

foreach ($scans as $scan) {

    $disease = $scan['disease'] ?? $scan['diseaseName'] ?? '';

    if (!empty($disease)) {
        $diseases[] = trim($disease);
    }

    if (!empty($scan['location'])) {
        $locations[] = trim($scan['location']);
    }

    if (!empty($scan['treeId']) && $scan['treeId'] !== 'N/A') {
        $treeId = trim($scan['treeId']);
        // Enhance with tree data if available
        if (isset($trees[$treeId])) {
            $treeIds[$treeId] = [
                'id' => $treeId,
                'species' => $trees[$treeId]['species'] ?? 'Unknown',
                'location' => $trees[$treeId]['location'] ?? ''
            ];
        } else {
            $treeIds[$treeId] = [
                'id' => $treeId,
                'species' => 'Unknown',
                'location' => ''
            ];
        }
    }
}

// Sort tree IDs for better display
ksort($treeIds);

$diseases = array_values(array_unique($diseases));
$locations = array_values(array_unique($locations));

/* =========================================
   SORT NEWEST FIRST
========================================= */

usort($filteredScans, function ($a, $b) {
    return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
});
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan History - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <style>
        /* ================================================
           60-30-10 COLOR RULE
           60% — Neutrals: #f4f6f9, #ffffff (backgrounds)
           30% — Primary:  #004737    (headings, table header bg, text)
           10% — Accent:   #70B9A1    (borders, badges, buttons, icons)
        ================================================ */
        :root {
            --primary: #004737;
            --primary-light: #1b6b58;
            --primary-ultra: #e8f2ef;
            --accent: #70B9A1;
            --accent-light: #a8d5c5;
            --accent-bg: #f0f9f5;
            --warning: #F59E0B;
            --warning-bg: #fffbeb;
            --success: #10B981;
            --danger: #EF4444;
            --bg-page: #f0f4f3;
            --bg-card: #ffffff;
            --text-heading: #004737;
            --text-body: #374151;
            --text-muted: #9CA3AF;
            --text-light: #ffffff;
            --border: #E5E7EB;
            --border-accent: rgba(112, 185, 161, 0.35);
            --shadow-sm: 0 1px 3px rgba(0, 71, 55, 0.07), 0 1px 2px rgba(0, 71, 55, 0.04);
            --shadow-md: 0 4px 12px rgba(0, 71, 55, 0.09), 0 2px 4px rgba(0, 71, 55, 0.05);
            --shadow-lg: 0 10px 28px rgba(0, 71, 55, 0.12), 0 4px 8px rgba(0, 71, 55, 0.07);
            --radius-sm: 8px;
            --radius-md: 14px;
            --radius-lg: 20px;
            --transition: all 0.22s cubic-bezier(.4, 0, .2, 1);
        }

        *,
        *::before,
        *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-page);
            color: var(--text-body);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Layout ── */
        .main-content {
            margin-left: 250px;
            padding: 2.5rem 2.5rem 3rem;
            transition: var(--transition);
        }

        /* ── Page Banner ── */
        .page-banner {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: var(--radius-lg);
            padding: 2rem 2.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }

        .page-banner::before {
            content: '';
            position: absolute;
            top: -40px;
            right: -40px;
            width: 220px;
            height: 220px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
        }

        .page-banner::after {
            content: '';
            position: absolute;
            bottom: -60px;
            right: 80px;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.04);
            border-radius: 50%;
        }

        .banner-left {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            z-index: 1;
        }

        .banner-icon {
            width: 58px;
            height: 58px;
            background: rgba(112, 185, 161, 0.25);
            border: 1.5px solid rgba(112, 185, 161, 0.5);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            color: var(--accent-light);
            flex-shrink: 0;
        }

        .banner-text h1 {
            font-size: 1.75rem;
            font-weight: 800;
            color: #ffffff;
            line-height: 1.2;
            letter-spacing: -0.5px;
        }

        .banner-text p {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.65);
            margin-top: 0.25rem;
            font-weight: 400;
        }

        .banner-badge {
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            padding: 0.45rem 1rem;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            z-index: 1;
            letter-spacing: 0.3px;
        }

        .banner-badge i {
            color: var(--accent-light);
        }

        /* ── Stat Cards ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.25rem;
            margin-bottom: 1.75rem;
        }

        .stat-card {
            background: var(--bg-card);
            border-radius: var(--radius-md);
            padding: 1.5rem 1.75rem;
            display: flex;
            align-items: center;
            gap: 1.25rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--accent);
            border-radius: 0 4px 4px 0;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
            border-color: var(--border-accent);
        }

        .stat-icon-wrapper {
            width: 56px;
            height: 56px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            background: var(--accent-bg);
            color: var(--primary);
            flex-shrink: 0;
            border: 1px solid var(--border-accent);
        }

        .stat-info h3 {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text-heading);
            line-height: 1;
            margin-bottom: 0.3rem;
            letter-spacing: -1px;
        }

        .stat-info p {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        .stat-info p i {
            color: var(--accent);
            margin-right: 4px;
        }

        /* ── Filter Panel ── */
        .filter-panel {
            background: var(--bg-card);
            border-radius: var(--radius-md);
            padding: 1.25rem 1.75rem 1.5rem;
            margin-bottom: 1.75rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
        }

        .filter-panel-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border);
        }

        .filter-panel-header span {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--primary);
        }

        .filter-panel-header i {
            color: var(--accent);
            font-size: 0.8rem;
        }

        .filter-body {
            display: flex;
            gap: 1rem;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .filter-group {
            flex: 1;
            min-width: 180px;
        }

        .filter-group label {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 0.45rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--primary);
        }

        .filter-group label i {
            color: var(--accent);
            font-size: 0.75rem;
        }

        .filter-group select {
            width: 100%;
            padding: 0.65rem 2.5rem 0.65rem 0.9rem;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            font-family: 'Inter', sans-serif;
            transition: var(--transition);
            background: #fafafa;
            color: var(--text-body);
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%2370B9A1' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.9rem center;
        }

        .filter-group select:focus {
            outline: none;
            border-color: var(--accent);
            background-color: var(--bg-card);
            box-shadow: 0 0 0 3px rgba(112, 185, 161, 0.15);
        }

        /* ── Buttons ── */
        .filter-actions {
            display: flex;
            gap: 0.6rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.65rem 1.25rem;
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            border: none;
            white-space: nowrap;
            letter-spacing: 0.2px;
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
            border: 1.5px solid var(--primary);
        }

        .btn-primary:hover {
            background: var(--primary-light);
            border-color: var(--primary-light);
            box-shadow: 0 4px 12px rgba(0, 71, 55, 0.25);
            transform: translateY(-1px);
        }

        .btn-primary i {
            color: var(--accent-light);
        }

        .btn-outline-danger {
            background: transparent;
            color: var(--danger);
            border: 1.5px solid #FECACA;
        }

        .btn-outline-danger:hover {
            background: #FEF2F2;
            border-color: var(--danger);
            transform: translateY(-1px);
        }

        /* ── Data Card (table wrapper) ── */
        .data-card {
            background: var(--bg-card);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .data-card-header {
            padding: 1.1rem 1.75rem;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .data-card-title {
            font-size: 0.82rem;
            font-weight: 700;
            color: rgba(255, 255, 255, 0.9);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .data-card-title i {
            color: var(--accent);
        }

        .data-card-count {
            background: rgba(112, 185, 161, 0.2);
            border: 1px solid rgba(112, 185, 161, 0.35);
            color: var(--accent-light);
            font-size: 0.78rem;
            font-weight: 700;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            letter-spacing: 0.3px;
        }

        /* ── Table ── */
        .table-responsive {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table thead tr {
            background: var(--primary-ultra);
            border-bottom: 2px solid var(--border-accent);
        }

        .table th {
            padding: 0.9rem 1.25rem;
            font-size: 0.73rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--primary);
            text-align: left;
            white-space: nowrap;
        }

        .table th i {
            color: var(--accent);
            margin-right: 5px;
            font-size: 0.7rem;
        }

        /* Sortable headers */
        .table th.sortable {
            cursor: pointer;
            user-select: none;
            transition: var(--transition);
        }

        .table th.sortable:hover {
            background: rgba(112, 185, 161, 0.15);
            color: var(--primary);
        }

        .table th.sortable.sort-asc,
        .table th.sortable.sort-desc {
            background: rgba(0, 71, 55, 0.07);
            color: var(--primary);
        }

        .sort-icon {
            display: inline-flex;
            flex-direction: column;
            gap: 1px;
            vertical-align: middle;
            margin-left: 5px;
            opacity: 0.3;
            transition: opacity 0.2s;
        }

        .table th.sortable:hover .sort-icon {
            opacity: 0.6;
        }

        .table th.sortable.sort-asc .sort-icon,
        .table th.sortable.sort-desc .sort-icon {
            opacity: 1;
            color: var(--primary);
        }

        .sort-icon .arrow-up,
        .sort-icon .arrow-down {
            display: block;
            width: 0;
            height: 0;
            border-left: 4px solid transparent;
            border-right: 4px solid transparent;
        }

        .sort-icon .arrow-up {
            border-bottom: 5px solid currentColor;
            margin-bottom: 1px;
        }

        .sort-icon .arrow-down {
            border-top: 5px solid currentColor;
        }

        .table th.sortable.sort-asc .sort-icon .arrow-down {
            opacity: 0.2;
        }

        .table th.sortable.sort-desc .sort-icon .arrow-up {
            opacity: 0.2;
        }

        /* Table body */
        .table td {
            padding: 0.9rem 1.25rem;
            border-bottom: 1px solid var(--border);
            font-size: 0.88rem;
            color: var(--text-body);
            vertical-align: middle;
        }

        .table tbody tr {
            transition: background 0.15s;
        }

        .table tbody tr:hover {
            background: var(--accent-bg);
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        /* ── Date Cell ── */
        .date-cell {
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .date-icon {
            width: 34px;
            height: 34px;
            background: var(--accent-bg);
            border: 1px solid var(--border-accent);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent);
            font-size: 0.75rem;
            flex-shrink: 0;
        }

        .date-text {
            display: flex;
            flex-direction: column;
            gap: 1px;
        }

        .date-main {
            font-weight: 600;
            color: var(--primary);
            font-size: 0.85rem;
            line-height: 1.2;
        }

        .date-time {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        /* ── Badges ── */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.3rem 0.75rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.2px;
            white-space: nowrap;
        }

        .badge i {
            font-size: 0.65rem;
        }

        .badge-disease {
            background: #FEF3C7;
            color: #92400E;
            border: 1px solid #FDE68A;
        }

        .badge-tree {
            background: var(--accent-bg);
            color: var(--primary);
            border: 1px solid var(--border-accent);
        }

        /* ── Location Cell ── */
        .location-cell {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.875rem;
        }

        .location-cell i {
            color: var(--accent);
            font-size: 0.75rem;
        }

        /* ── Confidence Cell ── */
        .conf-wrap {
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 90px;
        }

        .conf-value {
            font-size: 0.85rem;
            font-weight: 700;
        }

        .conf-bar-bg {
            height: 4px;
            background: var(--border);
            border-radius: 10px;
            overflow: hidden;
        }

        .conf-bar-fill {
            height: 100%;
            border-radius: 10px;
        }

        .conf-high .conf-value {
            color: #059669;
        }

        .conf-high .conf-bar-fill {
            background: #10B981;
        }

        .conf-med .conf-value {
            color: #D97706;
        }

        .conf-med .conf-bar-fill {
            background: #F59E0B;
        }

        .conf-low .conf-value {
            color: #DC2626;
        }

        .conf-low .conf-bar-fill {
            background: #EF4444;
        }

        /* ── User Cell ── */
        .user-cell {
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .user-avatar-sm {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent) 0%, var(--primary) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 0.7rem;
            font-weight: 700;
            flex-shrink: 0;
            text-transform: uppercase;
        }

        .user-meta {
            display: flex;
            flex-direction: column;
            gap: 1px;
        }

        .user-name-text {
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--text-heading);
            line-height: 1.2;
        }

        .user-email-text {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        /* ── Tree sub-text ── */
        .tree-species {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 3px;
            display: flex;
            align-items: center;
            gap: 3px;
        }

        .tree-species i {
            color: var(--accent);
            font-size: 0.65rem;
        }

        .text-muted {
            color: var(--text-muted);
            font-size: 0.82rem;
        }

        /* ── Empty State ── */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
        }

        .empty-state-icon {
            width: 72px;
            height: 72px;
            margin: 0 auto 1.25rem;
            background: var(--accent-bg);
            border: 1.5px dashed var(--border-accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            color: var(--accent);
        }

        .empty-state h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-heading);
            margin-bottom: 0.4rem;
        }

        .empty-state p {
            font-size: 0.875rem;
            color: var(--text-muted);
            margin-bottom: 1.25rem;
        }

        /* ── Print ── */
        @media print {
            body * {
                display: none !important;
            }

            .data-card,
            .data-card * {
                display: block !important;
            }

            .data-card {
                position: absolute !important;
                top: 0;
                left: 0;
                width: 100% !important;
                background: white !important;
                box-shadow: none !important;
            }

            .table {
                width: 100% !important;
                border-collapse: collapse !important;
            }

            .table th,
            .table td {
                border: 1px solid #000 !important;
                padding: 8px !important;
                color: #000 !important;
            }

            i {
                display: none !important;
            }
        }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .page-banner {
                padding: 1.5rem;
                flex-direction: column;
                align-items: flex-start;
            }

            .filter-body {
                flex-direction: column;
            }

            .filter-group {
                width: 100%;
            }

            .filter-actions {
                width: 100%;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .banner-badge {
                display: none;
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
                <div class="banner-icon">
                    <i class="fas fa-microscope"></i>
                </div>
                <div class="banner-text">
                    <h1>Scan History</h1>
                    <p>All disease scan records from every registered user</p>
                </div>
            </div>
            <div class="banner-badge" id="shBannerCount">
                <i class="fas fa-database"></i>
                <?= count($scans); ?> Total Records
            </div>
        </div>

        <!-- STATISTICS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon-wrapper">
                    <i class="fas fa-microscope"></i>
                </div>
                <div class="stat-info">
                    <h3 id="shStatScans"><?= count($scans); ?></h3>
                    <p><i class="fas fa-virus"></i> Total Scans</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon-wrapper">
                    <i class="fas fa-tree"></i>
                </div>
                <div class="stat-info">
                    <h3 id="shStatTrees"><?= count($treeIds); ?></h3>
                    <p><i class="fas fa-tag"></i> Unique Trees</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon-wrapper">
                    <i class="fas fa-filter"></i>
                </div>
                <div class="stat-info">
                    <h3 id="shStatFiltered"><?= count($filteredScans); ?></h3>
                    <p><i class="fas fa-search"></i> Filtered Results</p>
                </div>
            </div>
        </div>

        <!-- FILTER PANEL -->
        <div class="filter-panel">
            <div class="filter-panel-header">
                <i class="fas fa-sliders-h"></i>
                <span>Filter Records</span>
            </div>
            <div class="filter-body">
                <div class="filter-group">
                    <label><i class="fas fa-virus"></i> Disease Type</label>
                    <select onchange="applyFilters()" id="filterDisease">
                        <option value="">All Diseases</option>
                        <?php foreach ($diseases as $d): ?>
                            <option value="<?= htmlspecialchars($d); ?>" <?= $filterDisease === $d ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($d); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label><i class="fas fa-map-marker-alt"></i> Location</label>
                    <select onchange="applyFilters()" id="filterLocation">
                        <option value="">All Locations</option>
                        <?php foreach ($locations as $l): ?>
                            <option value="<?= htmlspecialchars($l); ?>" <?= $filterLocation === $l ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($l); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label><i class="fas fa-tree"></i> Tree ID</label>
                    <select onchange="applyFilters()" id="filterTreeId">
                        <option value="">All Trees</option>
                        <?php foreach ($treeIds as $treeId => $treeInfo): ?>
                            <?php
                            $treeDisplay = $treeId;
                            if (is_array($treeInfo) && isset($treeInfo['species']) && $treeInfo['species'] !== 'Unknown') {
                                $treeDisplay = $treeId . ' - ' . $treeInfo['species'];
                            }
                            ?>
                            <option value="<?= htmlspecialchars($treeId); ?>" <?= $filterTreeId === $treeId ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($treeDisplay); ?>
                                <?php if (!empty($treeInfo['location'])): ?>
                                    (<?= htmlspecialchars($treeInfo['location']); ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- ACTION BUTTONS -->
                <div class="filter-actions">
                    <button onclick="printReports()" class="btn btn-primary">
                        <i class="fas fa-print"></i> Print Report
                    </button>

                    <?php if (!empty($filterDisease) || !empty($filterLocation) || !empty($filterTreeId)): ?>
                        <a href="scanhistory-users.php" class="btn btn-outline-danger">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- TABLE -->
        <div class="data-card">
            <div class="data-card-header">
                <span class="data-card-title"><i class="fas fa-table"></i> Scan Records</span>
                <span class="data-card-count" id="shRowCount"><?= count($filteredScans); ?> rows</span>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="sortable sort-desc" data-col="0" onclick="sortTable(0)">
                                <i class="far fa-calendar-alt"></i> Date
                                <span class="sort-icon"><span class="arrow-up"></span><span
                                        class="arrow-down"></span></span>
                            </th>
                            <th class="sortable" data-col="1" onclick="sortTable(1)">
                                <i class="fas fa-virus"></i> Disease
                                <span class="sort-icon"><span class="arrow-up"></span><span
                                        class="arrow-down"></span></span>
                            </th>
                            <th class="sortable" data-col="2" onclick="sortTable(2)">
                                <i class="fas fa-tree"></i> Tree ID
                                <span class="sort-icon"><span class="arrow-up"></span><span
                                        class="arrow-down"></span></span>
                            </th>
                            <th class="sortable" data-col="3" onclick="sortTable(3)">
                                <i class="fas fa-map-marker-alt"></i> Location
                                <span class="sort-icon"><span class="arrow-up"></span><span
                                        class="arrow-down"></span></span>
                            </th>
                            <th class="sortable" data-col="4" onclick="sortTable(4)">
                                <i class="fas fa-percent"></i> Confidence
                                <span class="sort-icon"><span class="arrow-up"></span><span
                                        class="arrow-down"></span></span>
                            </th>
                            <th class="sortable" data-col="5" onclick="sortTable(5)">
                                <i class="fas fa-user"></i> User
                                <span class="sort-icon"><span class="arrow-up"></span><span
                                        class="arrow-down"></span></span>
                            </th>
                            <th class="sortable" data-col="6" onclick="sortTable(6)">
                                <i class="fas fa-envelope"></i> Email
                                <span class="sort-icon"><span class="arrow-up"></span><span
                                        class="arrow-down"></span></span>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="shTbody">

                        <?php if (empty($filteredScans)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">
                                            <i class="fas fa-search"></i>
                                        </div>
                                        <h3>No scans found</h3>
                                        <p>Try adjusting your filters or check back later</p>
                                        <a href="scanhistory-users.php" class="btn btn-outline-danger"
                                            style="display:inline-flex; margin: 0 auto;">
                                            <i class="fas fa-times"></i> Clear Filters
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($filteredScans as $scan): ?>
                                <?php
                                $confidence = scanhistory_norm_confidence($scan['confidence'] ?? null);
                                $confClass = '';
                                if ($confidence !== null) {
                                    if ($confidence >= 80)
                                        $confClass = 'conf-high';
                                    elseif ($confidence >= 60)
                                        $confClass = 'conf-med';
                                    else
                                        $confClass = 'conf-low';
                                }
                                $treeId = $scan['treeId'] ?? 'N/A';
                                $treeInfo = null;
                                $treeKey = strtolower((string) $treeId);
                                foreach ($trees as $tId => $tData) {
                                    if (strtolower((string) $tId) === $treeKey) {
                                        $treeInfo = $tData;
                                        break;
                                    }
                                }
                                $nameInitials = mb_strtoupper(mb_substr($scan['user_name'] ?? 'U', 0, 2));
                                ?>
                                <tr>
                                    <!-- Col 0: Date -->
                                    <td data-value="<?= isset($scan['timestamp']) ? scanhistory_norm_timestamp_ms($scan['timestamp']) : 0 ?>">
                                        <?php if (isset($scan['timestamp'])): ?>
                                            <?php $ts = scanhistory_norm_timestamp_ms($scan['timestamp']) / 1000; ?>
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

                                    <!-- Col 1: Disease -->
                                    <td data-value="<?= htmlspecialchars(strtolower($scan['diseaseName'] ?? 'unknown')) ?>">
                                        <span class="badge badge-disease">
                                            <i class="fas fa-virus"></i>
                                            <?= htmlspecialchars($scan['diseaseName'] ?? 'Unknown'); ?>
                                        </span>
                                    </td>

                                    <!-- Col 2: Tree ID -->
                                    <td data-value="<?= htmlspecialchars(strtolower($treeId)) ?>">
                                        <?php if ($treeId !== 'N/A'): ?>
                                            <span class="badge badge-tree">
                                                <i class="fas fa-tree"></i>
                                                <?= htmlspecialchars($treeId); ?>
                                            </span>
                                            <?php if ($treeInfo && isset($treeInfo['species'])): ?>
                                                <div class="tree-species">
                                                    <i class="fas fa-leaf"></i> <?= htmlspecialchars($treeInfo['species']); ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Col 3: Location -->
                                    <td data-value="<?= htmlspecialchars(strtolower($scan['location'] ?? 'n/a')) ?>">
                                        <div class="location-cell">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <?= htmlspecialchars($scan['location'] ?? 'N/A'); ?>
                                        </div>
                                    </td>

                                    <!-- Col 4: Confidence -->
                                    <td data-value="<?= $confidence !== null ? $confidence : -1 ?>">
                                        <?php if ($confidence !== null): ?>
                                            <div class="conf-wrap <?= $confClass ?>">
                                                <span class="conf-value"><?= number_format($confidence, 1) ?>%</span>
                                                <div class="conf-bar-bg">
                                                    <div class="conf-bar-fill" style="width:<?= min(100, $confidence) ?>%"></div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Col 5 & 6: User + Email combined in user cell -->
                                    <td data-value="<?= htmlspecialchars(strtolower($scan['user_name'])) ?>">
                                        <div class="user-cell">
                                            <div class="user-avatar-sm"><?= $nameInitials ?></div>
                                            <div class="user-meta">
                                                <span class="user-name-text"><?= htmlspecialchars($scan['user_name']); ?></span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Col 6: Email -->
                                    <td data-value="<?= htmlspecialchars(strtolower($scan['user_email'])) ?>">
                                        <span style="color: var(--text-muted); font-size:0.82rem;">
                                            <?= htmlspecialchars($scan['user_email']); ?>
                                        </span>
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
        /* =========================================
           SORTABLE COLUMNS
           Tracks: currentSortCol (index), currentSortDir ('asc'|'desc')
           Col 0 = Date (numeric timestamp), Col 4 = Confidence (numeric)
           All others are string comparisons
        ========================================= */
        const NUMERIC_COLS = new Set([0, 4]);
        let currentSortCol = 0;       // Default: Date column
        let currentSortDir = 'desc';  // Default: newest first (matches PHP usort)

        function sortTable(colIndex) {
            const table = document.querySelector('.table');
            const tbody = table.querySelector('tbody');
            const headers = table.querySelectorAll('th.sortable');

            // Toggle direction if clicking same column, else default asc (or desc for Date)
            if (colIndex === currentSortCol) {
                currentSortDir = (currentSortDir === 'asc') ? 'desc' : 'asc';
            } else {
                currentSortCol = colIndex;
                // Date column: default newest-first (desc); others: default asc
                currentSortDir = (colIndex === 0) ? 'desc' : 'asc';
            }

            // Update header classes to reflect active sort
            headers.forEach(th => {
                th.classList.remove('sort-asc', 'sort-desc');
                if (parseInt(th.dataset.col) === currentSortCol) {
                    th.classList.add(currentSortDir === 'asc' ? 'sort-asc' : 'sort-desc');
                }
            });

            // Gather all rows and sort
            const rows = Array.from(tbody.querySelectorAll('tr'));

            // If only 1 row (empty-state) skip
            if (rows.length <= 1 && rows[0] && rows[0].querySelector('.empty-state')) return;

            rows.sort((a, b) => {
                const aCell = a.querySelectorAll('td')[colIndex];
                const bCell = b.querySelectorAll('td')[colIndex];
                if (!aCell || !bCell) return 0;

                const aVal = aCell.dataset.value ?? '';
                const bVal = bCell.dataset.value ?? '';

                let cmp;
                if (NUMERIC_COLS.has(colIndex)) {
                    cmp = parseFloat(aVal) - parseFloat(bVal);
                } else {
                    cmp = aVal.localeCompare(bVal, undefined, { numeric: true, sensitivity: 'base' });
                }

                return currentSortDir === 'asc' ? cmp : -cmp;
            });

            // Re-append sorted rows
            rows.forEach(row => tbody.appendChild(row));
        }

        function applyFilters() {
            const disease = document.getElementById('filterDisease').value;
            const location = document.getElementById('filterLocation').value;
            const treeId = document.getElementById('filterTreeId').value;

            const params = new URLSearchParams();
            if (disease) params.append('disease', disease);
            if (location) params.append('location', location);
            if (treeId) params.append('treeId', treeId);

            window.location.href = 'scanhistory-users.php?' + params.toString();
        }

        function printReports() {
            const tableContent = document.querySelector('.data-card').innerHTML;

            const printWindow = window.open('', '', 'width=1000,height=700');

            printWindow.document.write(`
        <html>
        <head>
            <title>Scan Report</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    padding: 20px;
                }
                h2 {
                    text-align: center;
                    margin-bottom: 20px;
                    color: #004737;
                }
                .report-header {
                    text-align: center;
                    margin-bottom: 30px;
                    padding-bottom: 20px;
                    border-bottom: 2px solid #70B9A1;
                }
                .report-date {
                    color: #666;
                    font-size: 14px;
                    margin-top: 5px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 20px;
                }
                th, td {
                    border: 1px solid #000;
                    padding: 8px;
                    text-align: left;
                }
                th {
                    background: #f0f0f0;
                    font-weight: bold;
                }
                i {
                    display: none;
                }
                .badge {
                    display: inline-block;
                    padding: 3px 8px;
                    border-radius: 3px;
                    font-size: 12px;
                }
                .text-muted {
                    color: #999;
                }
                .tree-id-cell div {
                    font-size: 11px;
                    color: #666;
                }
            </style>
        </head>
        <body>
            <div class="report-header">
                <h2>Tree Disease Scan Report</h2>
                <div class="report-date">Generated on: ${new Date().toLocaleString()}</div>
            </div>
            ${tableContent}
    <script type="module">
        const _fb = window.MangoFB;
        if (_fb && _fb.db) {
            const { db, ref, onValue, escHtml, formatDate } = _fb;
            const _params = new URLSearchParams(location.search);
            const fDisease = (_params.get('disease') || '').toLowerCase().trim();
            const fLocation = (_params.get('location') || '').toLowerCase().trim();
            const fTreeId = (_params.get('treeId') || '').toLowerCase().trim();
            const _snaps = { users: null, trees: null };
            function resolveLocation(scan, trees) {
                const placeholders = ['', 'scan result', 'camera scan', 'n/a', 'na', 'none', 'unknown'];
                let location = (scan.location || '').trim();
                if (placeholders.indexOf(location.toLowerCase()) === -1) return location;
                const treeId = scan.treeId || scan.tree_id || scan.treeID || scan.tree || null;
                if (!treeId) return location;
                const key = String(treeId).toLowerCase();
                const match = Object.keys(trees).find(k => String(k).toLowerCase() === key);
                if (match && trees[match] && trees[match].location) return trees[match].location;
                return location;
            }
            function maybeRender() {
                if (_snaps.users && _snaps.trees) renderHistory();
            }
            onValue(ref(db, 'Users'), (s) => { _snaps.users = s; maybeRender(); });
            onValue(ref(db, 'trees'), (s) => { _snaps.trees = s; maybeRender(); });

            function renderHistory() {
                const usersData = _snaps.users.val() || {};
                const trees = _snaps.trees.val() || {};
                const scans = [];
                Object.entries(usersData).forEach(([uid, user]) => {
                    if (!user || typeof user !== 'object' || !user.scanResult) return;
                    Object.entries(user.scanResult).forEach(([scanId, scan]) => {
                        if (!scan || typeof scan !== 'object') return;
                        scans.push({
                            ...scan,
                            scan_id: scanId,
                            treeId: scan.treeId || scan.tree_id || scan.treeID || scan.tree || 'N/A',
                            location: resolveLocation(scan, trees),
                            user_name: user.name || 'Unknown',
                            user_email: user.email || 'N/A'
                        });
                    });
                });

                let filtered = scans;
                if (fDisease) filtered = filtered.filter(s => String(s.disease || s.diseaseName || '').toLowerCase().trim() === fDisease);
                if (fLocation) filtered = filtered.filter(s => (s.location || '') && String(s.location).toLowerCase().trim() === fLocation);
                if (fTreeId) filtered = filtered.filter(s => s.treeId && String(s.treeId).toLowerCase().trim() === fTreeId);

                const uniqueTrees = new Set(scans.map(s => s.treeId).filter(t => t && t !== 'N/A'));
                const setText = (id, txt) => { const el = document.getElementById(id); if (el) el.textContent = txt; };
                setText('shStatScans', scans.length);
                setText('shStatTrees', uniqueTrees.size);
                setText('shStatFiltered', filtered.length);
                setText('shRowCount', `${ filtered.length } rows`);
                const banner = document.getElementById('shBannerCount');
                if (banner) banner.innerHTML = `< i class= "fas fa-database" ></i > ${ scans.length } Total Records`;

                const tbody = document.getElementById('shTbody');
                if (!tbody) return;
                if (filtered.length === 0) {
                    tbody.innerHTML = `
            < tr >
            <td colspan="7">
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="fas fa-search"></i></div>
                    <h3>No scans found</h3>
                    <p>Try adjusting your filters or check back later</p>
                    <a href="scanhistory-users.php" class="btn btn-outline-danger" style="display:inline-flex; margin: 0 auto;">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                </div>
            </td>
                        </tr > `;
                    return;
                }

                // Respect the page's current sort state (matches sortTable's defaults)
                const _NUMERIC = new Set([0, 4]);
                const sortCol = (typeof currentSortCol !== 'undefined') ? currentSortCol : 0;
                const sortDir = (typeof currentSortDir !== 'undefined') ? currentSortDir : (sortCol === 0 ? 'desc' : 'asc');
                const sortVal = (s, c) => {
                    switch (c) {
                        case 0: return Number(s.timestamp || 0);
                        case 1: return String(s.diseaseName || s.disease || '').toLowerCase();
                        case 2: return String(s.treeId || '').toLowerCase();
                        case 3: return String(s.location || '').toLowerCase();
                        case 4: { const cv = (s.confidence != null && s.confidence !== '') ? parseFloat(s.confidence) : null; return (cv !== null && !isNaN(cv)) ? cv : -1; }
                        case 5: return String(s.user_name || '').toLowerCase();
                        case 6: return String(s.user_email || '').toLowerCase();
                        default: return '';
                    }
                };
                filtered = filtered.slice().sort((a, b) => {
                    const av = sortVal(a, sortCol), bv = sortVal(b, sortCol);
                    let cmp = _NUMERIC.has(sortCol) ? (av - bv) : String(av).localeCompare(String(bv), undefined, { numeric: true, sensitivity: 'base' });
                    return sortDir === 'asc' ? cmp : -cmp;
                });
                    tbody.innerHTML = filtered.map(scan => {
                        const ts = Number(scan.timestamp || 0);
                        const dateCell = ts
                            ? `< div class= "date-cell" >
                                <div class="date-icon"><i class="far fa-calendar-alt"></i></div>
                                <div class="date-text">
                                    <span class="date-main">${formatDate(ts > 9999999999 ? ts : ts * 1000, 'M d, Y')}</span>
                                    <span class="date-time">${formatDate(ts > 9999999999 ? ts : ts * 1000, 'h:i A')}</span>
                                </div>
                              </div > `
                            : `< span class= "text-muted" > N / A</span > `;

                        const disease = scan.diseaseName || scan.disease || scan.disease_final || scan.final_disease || scan.predicted_disease || 'Unknown';
                        const treeId = scan.treeId || 'N/A';
                        const treeInfo = Object.entries(trees).find(([k]) => String(k).toLowerCase() === String(treeId).toLowerCase())?.[1] || null;
                        let loc = (scan.location || '').trim();
                        if (['', 'scan result', 'camera scan', 'n/a', 'na', 'none', 'unknown'].indexOf(loc.toLowerCase()) !== -1 && treeInfo && treeInfo.location) loc = treeInfo.location;
                        if (!loc) loc = 'N/A';

                        let confidence = null;
                        if (scan.confidence != null && scan.confidence !== '') {
                            confidence = parseFloat(scan.confidence);
                            if (confidence < 1) confidence *= 100;
                        }
                        const confClass = confidence !== null && !isNaN(confidence)
                            ? (confidence >= 80 ? 'conf-high' : confidence >= 60 ? 'conf-med' : 'conf-low') : '';
                        const confCell = confidence !== null && !isNaN(confidence)
                            ? `< div class= "conf-wrap ${confClass}" >
                                <span class="conf-value">${confidence.toFixed(1)}%</span>
                                <div class="conf-bar-bg"><div class="conf-bar-fill" style="width:${Math.min(100, confidence)}%"></div></div>
                              </div > `
                            : `< span class= "text-muted" >—</span > `;

                        const initials = String(scan.user_name || 'U').slice(0, 2).toUpperCase();
                        const treeCell = treeId !== 'N/A'
                            ? `< span class= "badge badge-tree" > <i class="fas fa-tree"></i> ${ escHtml(treeId) }</span > `
                                + (treeInfo && treeInfo.species ? `< div class= "tree-species" > <i class="fas fa-leaf"></i> ${ escHtml(treeInfo.species)
        }</div > ` : '')
                            : `< span class="text-muted" >—</span > `;

                        return `
            < tr >
                                <td data-value="${ts}">${dateCell}</td>
                                <td data-value="${escHtml(String(disease).toLowerCase())}">
                                    <span class="badge badge-disease"><i class="fas fa-virus"></i> ${escHtml(disease)}</span>
                                </td>
                                <td data-value="${escHtml(String(treeId).toLowerCase())}">${treeCell}</td>
                                <td data-value="${escHtml(String(loc).toLowerCase())}">
                                    <div class="location-cell"><i class="fas fa-map-marker-alt"></i> ${escHtml(loc)}</div>
                                </td>
                                <td data-value="${confidence !== null && !isNaN(confidence) ? confidence : -1}">${confCell}</td>
                                <td data-value="${escHtml(String(scan.user_name || '').toLowerCase())}">
                                    <div class="user-cell">
                                        <div class="user-avatar-sm">${escHtml(initials)}</div>
                                        <div class="user-meta"><span class="user-name-text">${escHtml(scan.user_name)}</span></div>
                                    </div>
                                </td>
                                <td data-value="${escHtml(String(scan.user_email || '').toLowerCase())}">
                                    <span style="color: var(--text-muted); font-size:0.82rem;">${escHtml(scan.user_email)}</span>
                                </td>
                            </tr >`;
                    }).join('');
                }
            }
    </script>
</body>

</html>
`);

printWindow.document.close();
printWindow.focus();

setTimeout(() => {
printWindow.print();
printWindow.close();
}, 500);
}

// Add loading state to print button
document.addEventListener('DOMContentLoaded', function() {
const printBtn = document.querySelector('.btn.btn-primary');
if (printBtn) {
printBtn.addEventListener('click', function() {
const originalText = this.innerHTML;
this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Preparing...';
this.disabled = true;
setTimeout(() => {
this.innerHTML = originalText;
this.disabled = false;
}, 1000);
});
}
});
</script>

</body>

</html>