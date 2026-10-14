<?php
/**
 * Configuration file for MangoCare Admin System
 * Firebase configuration loaded from google-services.json (same as Android app)
 */

// Load Firebase config from google-services.json
$googleServicesPath = __DIR__ . '/google-services.json';
if (!file_exists($googleServicesPath)) {
    throw new Exception('google-services.json not found. Place your Firebase config file in the config/ directory.');
}

$googleServices = json_decode(file_get_contents($googleServicesPath), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    throw new Exception('Invalid google-services.json format.');
}

$projectInfo = $googleServices['project_info'] ?? [];
$client = $googleServices['client'][0] ?? [];

// Realtime Database URL (used by admin panel + Android app)
$firebaseUrl = rtrim($projectInfo['firebase_url'] ?? '', '/') . '/';
define('FIREBASE_DATABASE_URL', $firebaseUrl);

// Database Secret (Legacy Auth) - From Firebase Console > Project Settings > Service Accounts > Database secrets
// Leave empty for public read/write (dev); set for production.
define('FIREBASE_DATABASE_SECRET', '');

// From google-services.json (for reference / future use)
define('FIREBASE_API_KEY', $client['api_key'][0]['current_key'] ?? '');
define('FIREBASE_PROJECT_ID', $projectInfo['project_id'] ?? '');
define('FIREBASE_STORAGE_BUCKET', $projectInfo['storage_bucket'] ?? '');
define('FIREBASE_MESSAGING_SENDER_ID', $projectInfo['project_number'] ?? '');
define('FIREBASE_APP_ID', $client['client_info']['mobilesdk_app_id'] ?? '');
define('FIREBASE_AUTH_DOMAIN', (($projectInfo['project_id'] ?? '') . '.firebaseapp.com'));

// Hardcoded Admin Credentials
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin123'); // Change this in production

// System Settings
define('SITE_NAME', 'MangoCare Admin');
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds

// Spray Schedule Settings
define('DEFAULT_SPRAY_INTERVAL_MONTHS', 1); // Default calendar months between sprays
define('DEFAULT_SPRAY_TIME', '08:00'); // Default spray time (24-hour format)
define('AUTO_SCHEDULE_ENABLED', true); // Enable automatic schedule generation
define('AUTO_SCHEDULE_INTERVAL_SECONDS', 15); // Min seconds between auto-schedule maintenance runs

// Timezone
date_default_timezone_set('Asia/Manila');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Firebase REST API Helper Class
class Firebase
{
    private $databaseUrl;
    private $databaseSecret;

    public function __construct()
    {
        $this->databaseUrl = rtrim(FIREBASE_DATABASE_URL, '/') . '/';
        $this->databaseSecret = FIREBASE_DATABASE_SECRET;

        // Check if cURL is available
        if (!function_exists('curl_init')) {
            throw new Exception('cURL extension is not enabled. Please enable it in php.ini');
        }
    }

    /**
     * Build URL with optional authentication
     */
    private function buildUrl($path)
    {
        $url = $this->databaseUrl . ltrim($path, '/') . '.json';
        if (!empty($this->databaseSecret)) {
            $url .= '?auth=' . $this->databaseSecret;
        }
        return $url;
    }

    /**
     * Execute cURL request
     * Returns: decoded JSON (GET), array with 'name' key (POST push), true (success), or false (failure)
     */
    private function executeRequest($url, $method = 'GET', $data = null)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("Firebase cURL Error: " . $error);
            return false;
        }

        if ($httpCode >= 400) {
            error_log("Firebase HTTP Error {$httpCode}: " . $response);
            return false;
        }

        $decoded = json_decode($response, true);
        if ($method === 'DELETE' || ($method !== 'GET' && $decoded === null && $response !== 'null')) {
            return true;
        }
        return $decoded;
    }

    /**
     * Get data from Firebase
     */
    public function get($path)
    {
        $url = $this->buildUrl($path);
        return $this->executeRequest($url, 'GET');
    }

    /**
     * Set data in Firebase (overwrites existing data)
     */
    public function set($path, $data)
    {
        $url = $this->buildUrl($path);
        return $this->executeRequest($url, 'PUT', $data);
    }

    /**
     * Push data to Firebase (creates unique key)
     */
    public function push($path, $data)
    {
        $url = $this->buildUrl($path);
        return $this->executeRequest($url, 'POST', $data);
    }

    /**
     * Update data in Firebase (merges with existing data)
     */
    public function update($path, $data)
    {
        $url = $this->buildUrl($path);
        return $this->executeRequest($url, 'PATCH', $data);
    }

    /**
     * Delete data from Firebase
     */
    public function delete($path)
    {
        $url = $this->buildUrl($path);
        return $this->executeRequest($url, 'DELETE');
    }
}

// Helper function to check if user is logged in
function isLoggedIn()
{
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

// Helper function to require login
function requireLogin()
{
    if (!isLoggedIn()) {
        header('Location: ../auth/login.php');
        exit();
    }
}

/**
 * Convert 24-hour time to 12-hour format with AM/PM
 * @param string $time24 Time in 24-hour format (HH:MM)
 * @return string Time in 12-hour format (h:MM AM/PM)
 */
function convertTo12Hour($time24)
{
    if (empty($time24))
        return '';
    $time = DateTime::createFromFormat('H:i', $time24);
    if ($time === false) {
        // Try H:i:s format
        $time = DateTime::createFromFormat('H:i:s', $time24);
    }
    if ($time === false)
        return $time24;
    return $time->format('g:i A');
}

/**
 * Convert 12-hour time to 24-hour format
 * @param string $time12 Time in 12-hour format (h:MM AM/PM)
 * @return string Time in 24-hour format (HH:MM)
 */
function convertTo24Hour($time12)
{
    if (empty($time12))
        return '';
    $time = DateTime::createFromFormat('g:i A', $time12);
    if ($time === false) {
        // Try h:i A format
        $time = DateTime::createFromFormat('h:i A', $time12);
    }
    if ($time === false)
        return $time12;
    return $time->format('H:i');
}

/**
 * Sanitize Tree ID for Firebase (remove illegal characters)
 * @param string $id Raw Tree ID
 * @return string Sanitized ID
 */
function sanitizeTreeId($id)
{
    // Convert spaces/dots to underscores, remove illegal Firebase chars
    return preg_replace('/[^a-zA-Z0-9]/', '_', trim($id));
}

/**
 * Find the earliest future pending schedule from an in-memory schedules array.
 * @param array $schedules Tree's spray_schedules array (may be empty)
 * @return array|null Next schedule (with _timestamp) or null
 */
function findNextSpraySchedule($schedules)
{
    if (!is_array($schedules))
        return null;

    $now = time();
    $nextSchedule = null;
    $nextTimestamp = PHP_INT_MAX;

    foreach ($schedules as $schedule) {
        if (($schedule['status'] ?? 'pending') !== 'pending')
            continue;

        $dateStr = $schedule['spray_date'] ?? '';
        $timeStr = $schedule['spray_time'] ?? '';
        if (empty($dateStr) || empty($timeStr))
            continue;

        $timestamp = strtotime($dateStr . ' ' . $timeStr);
        if ($timestamp > $now && $timestamp < $nextTimestamp) {
            $nextTimestamp = $timestamp;
            $nextSchedule = $schedule;
            $nextSchedule['_timestamp'] = $timestamp;
        }
    }

    return $nextSchedule;
}

/**
 * Get next spray schedule for a tree (earliest future pending).
 * Uses the supplied in-memory schedules array when given (avoids a per-tree Firebase call);
 * otherwise falls back to fetching from Firebase.
 * @param Firebase $firebase Firebase instance
 * @param string $treeId Tree ID
 * @param array|null $schedules Pre-fetched spray_schedules for the tree (optional)
 * @return array|null Next schedule or null
 */
function getNextSpraySchedule($firebase, $treeId, $schedules = null)
{
    if ($schedules === null) {
        $schedules = $firebase->get('trees/' . $treeId . '/spray_schedules') ?? [];
    }
    return findNextSpraySchedule($schedules);
}

/**
 * Format next spray for display (Philippine Time, 12-hour)
 * @param array $schedule Schedule with spray_date, spray_time, and optionally _timestamp
 * @return string e.g. "Jan 25, 2026 at 8:00 AM"
 */
function formatNextSprayDisplay($schedule)
{
    $ts = $schedule['_timestamp'] ?? null;
    if ($ts === null) {
        $dateStr = $schedule['spray_date'] ?? '';
        $timeStr = $schedule['spray_time'] ?? '';
        if (empty($dateStr) || empty($timeStr))
            return '';
        $ts = strtotime($dateStr . ' ' . $timeStr);
    }
    $dateFormatted = date('M j, Y', $ts);
    $timeFormatted = convertTo12Hour(date('H:i', $ts));
    return $dateFormatted . ' at ' . $timeFormatted;
}
function getAllUsers($firebase)
{
    $users = $firebase->get('Users');

    if (!is_array($users)) {
        return [];
    }

    return $users;
}

/**
 * Get single user by UID
 * @param Firebase $firebase
 * @param string $uid
 * @return array|null
 */
function getUserById($firebase, $uid)
{
    $user = $firebase->get('Users/' . $uid);
    return is_array($user) ? $user : null;
}
function getAllDiseaseDetections($firebase)
{

    $users = $firebase->get('Users') ?? [];
    $users = is_array($users) ? $users : [];

    $allScans = [];
    $diseaseFrequency = [];
    $total = 0;

    foreach ($users as $uid => $user) {

        if (!isset($user['scanResults']) || !is_array($user['scanResults'])) {
            continue;
        }

        foreach ($user['scanResults'] as $scanId => $scan) {

            $allScans[] = $scan;
            $total++;

            $diseaseName =
                $scan['diseaseName']
                ?? $scan['disease']
                ?? null;

            if ($diseaseName) {
                $diseaseFrequency[$diseaseName] =
                    ($diseaseFrequency[$diseaseName] ?? 0) + 1;
            }
        }
    }


    return [
        'total' => $total,
        'scans' => $allScans,
        'frequency' => $diseaseFrequency
    ];
}

/**
 * Compute the next treatment date: same day next calendar month, clamped to
 * the last day of that month when the day does not exist (e.g. Jan 31 → Feb 28).
 * Mirrors the app's nextTreatmentDateFrom() so the "next treatment" display
 * matches the actual recurrence.
 *
 * @param int $anchor Unix timestamp of the completion moment
 * @return string Next treatment date in YYYY-MM-DD format
 */
function nextTreatmentDateFrom($anchor)
{
    $d = new DateTime();
    $d->setTimestamp((int) $anchor);
    $day = (int) $d->format('j');
    $y = (int) $d->format('Y');
    $m = (int) $d->format('n');

    // Next calendar month (handle year rollover)
    $targetM = $m + 1;
    $targetY = $y;
    if ($targetM > 12) {
        $targetM = 1;
        $targetY++;
    }

    $lastDay = (int) date('t', mktime(0, 0, 0, $targetM, 1, $targetY));
    $d = new DateTime();
    $d->setDate($targetY, $targetM, min($day, $lastDay));
    return $d->format('Y-m-d');
}

/**
 * Ensure a tree has a next pending spray schedule after a completion.
 *
 * Creates exactly ONE new pending schedule dated INTERVAL calendar months
 * (default 1) from the completion moment (completed_at when available,
 * otherwise now). Never creates one if the tree already has a pending
 * schedule dated today or later (duplicate guard).
 *
 * @param Firebase $firebase Firebase instance
 * @param string $treeId Tree ID
 * @param array|null $schedule The completed schedule (optional; used for completed_at)
 * @param int $intervalMonths Calendar months ahead for the next spray
 * @return bool True if a schedule was created
 */
function ensureNextSchedule($firebase, $treeId, $schedule = null, $intervalMonths = DEFAULT_SPRAY_INTERVAL_MONTHS)
{
    $todayStr = date('Y-m-d');

    // Duplicate guard: skip if a future pending schedule already exists.
    $allSchedules = $firebase->get('trees/' . $treeId . '/spray_schedules') ?? [];
    $allSchedules = is_array($allSchedules) ? $allSchedules : [];
    foreach ($allSchedules as $sc) {
        if (!is_array($sc))
            continue;
        if (strtolower($sc['status'] ?? 'pending') === 'pending') {
            $scDate = $sc['spray_date'] ?? '';
            if ($scDate !== '' && $scDate >= $todayStr) {
                return false;
            }
        }
    }

    // Anchor: when the spray was marked as sprayed, falling back to now.
    $anchor = time();
    if (is_array($schedule)) {
        $completedAt = $schedule['completed_at'] ?? '';
        if (!empty($completedAt)) {
            $parsed = strtotime($completedAt);
            if ($parsed !== false) {
                $anchor = $parsed;
            }
        }
    }

    $nextDate = nextTreatmentDateFrom($anchor);

    $newSchedule = [
        'spray_date' => $nextDate,
        'spray_time' => DEFAULT_SPRAY_TIME,
        'status' => 'pending',
        'notes' => 'Auto-generated: ' . $intervalMonths . '-month treatment cycle',
        'created_at' => date('M d, Y g:i A'),
        'created_by' => 'system',
        'auto_generated' => true,
    ];

    $result = $firebase->push('trees/' . $treeId . '/spray_schedules', $newSchedule);
    return $result !== false && !empty($result['name']);
}

/**
 * Process automatic schedule updates (Philippine Time).
 *
 * Behaviour:
 *  1. For every PENDING schedule whose spray date has fully passed and was
 *     not marked as sprayed: mark it as "overdue" (never auto-complete).
 *  2. After processing completions, if a tree has NO future pending schedule
 *     and has a previous completion:
 *     - Create exactly ONE new pending schedule dated +INTERVAL days
 *       (default 30) from the completion moment.
 *     - Never create another one if a future pending already exists.
 *
 * @param Firebase $firebase Firebase instance
 * @param array|null $trees Pre-fetched trees array (optional; fetched once here if not supplied)
 * @param bool $force Skip the throttle when true (used by the real-time sync endpoint)
 * @return array Statistics ['completed', 'generated', 'errors']
 */
function processAutomaticSchedules($firebase, $trees = null, $force = false)
{
    $stats = ['completed' => 0, 'generated' => 0, 'errors' => []];

    if (!AUTO_SCHEDULE_ENABLED)
        return $stats;

    // Throttle: only run once per interval so page loads are not blocked.
    $lastRun = $firebase->get('system_meta/last_auto_schedule');
    if (!$force && is_numeric($lastRun) && (time() - (int) $lastRun) < AUTO_SCHEDULE_INTERVAL_SECONDS) {
        return $stats;
    }
    $firebase->set('system_meta/last_auto_schedule', time());

    if ($trees === null) {
        $trees = $firebase->get('trees') ?? [];
    }
    $trees = is_array($trees) ? $trees : [];
    $now = time();
    $todayStr = date('Y-m-d');

    // Completion signal source: spray_history is the canonical "farmer sprayed"
    // record. system_meta/spray_sync stores the last-processed history timestamp
    // per tree so old completions are never reprocessed.
    $sprayHistory = $firebase->get('spray_history') ?? [];
    $sprayHistory = is_array($sprayHistory) ? $sprayHistory : [];
    $spraySync = $firebase->get('system_meta/spray_sync') ?? [];
    $spraySync = is_array($spraySync) ? $spraySync : [];

    $doneStatuses = ['completed', 'done', 'sprayed', 'finished', 'complete', '1', 'true'];

    foreach ($trees as $treeId => $tree) {
        if ($treeId === '0' || $treeId === 0 || (string) $treeId === '0')
            continue;

        $schedules = $tree['spray_schedules'] ?? [];
        if (!is_array($schedules))
            continue;

        /* ── Completion signal from spray_history (farmer "Mark as Sprayed") ── */
        $historyTsSec = 0;
        $historyDateStr = '';
        foreach ($sprayHistory as $h) {
            if (!is_array($h))
                continue;
            if (!isset($h['tree_id']) || (string) $h['tree_id'] !== (string) $treeId)
                continue;
            $hStatus = strtolower($h['status'] ?? 'completed');
            if (!in_array($hStatus, $doneStatuses, true))
                continue;
            $hTs = isset($h['timestamp']) ? (int) $h['timestamp'] : 0;
            if ($hTs <= 0)
                continue;
            $hTsSec = $hTs > 9999999999 ? (int) ($hTs / 1000) : $hTs;
            if ($hTsSec > $historyTsSec) {
                $historyTsSec = $hTsSec;
                $historyDateStr = date('Y-m-d', $hTsSec);
            }
        }
        $lastProcessedTs = (int) ($spraySync[$treeId] ?? 0);
        $isNewCompletion = ($historyTsSec > 0 && $historyTsSec > $lastProcessedTs);
        $completedThisRun = [];

        /* ── Step 1: reconcile schedule statuses (complete / overdue / future) ── */
        $lastCompletedDate = null; // YYYY-MM-DD of most-recently completed spray
        $hasFuturePending = false;
        $pastDueNotDone = []; // scheduleId => dateStr (pending/overdue, date passed)

        foreach ($schedules as $scheduleId => $schedule) {
            $status = strtolower($schedule['status'] ?? 'pending');
            $dateStr = $schedule['spray_date'] ?? $schedule['date'] ?? $schedule['scheduled_date'] ?? '';
            if (empty($dateStr))
                continue;
            $isDone = in_array($status, $doneStatuses, true);

            if ($isDone) {
                if ($lastCompletedDate === null || $dateStr > $lastCompletedDate) {
                    $lastCompletedDate = $dateStr;
                }
                continue;
            }

            // Upcoming pending schedule (today or later)
            if ($status === 'pending' && $dateStr >= $todayStr) {
                $hasFuturePending = true;
                continue;
            }

            // Past-due schedule that was never marked done
            if ($dateStr < $todayStr && ($status === 'pending' || $status === 'overdue')) {
                $pastDueNotDone[$scheduleId] = $dateStr;

                if ($isNewCompletion && $historyDateStr !== '' && $dateStr === $historyDateStr) {
                    // The farmer sprayed this exact schedule → auto-complete it.
                    $firebase->update(
                        'trees/' . $treeId . '/spray_schedules/' . $scheduleId,
                        ['status' => 'completed', 'completed_at' => date('M d, Y g:i A')]
                    );
                    $completedThisRun[] = $scheduleId;
                    if ($lastCompletedDate === null || $dateStr > $lastCompletedDate) {
                        $lastCompletedDate = $dateStr;
                    }
                    continue;
                }

                if ($status === 'pending') {
                    // No matching completion signal → mark overdue.
                    // Never fabricate a completion that did not happen.
                    $result = $firebase->update(
                        'trees/' . $treeId . '/spray_schedules/' . $scheduleId,
                        ['status' => 'overdue', 'overdue_at' => date('M d, Y g:i A')]
                    );
                    if ($result === false) {
                        $stats['errors'][] = "Failed to mark overdue: {$treeId}/{$scheduleId}";
                    }
                }
            }
        }

        // Fallback: a NEW completion with no exact date match → complete the most
        // recent past-due schedule so the schedule reflects that it was sprayed.
        if ($isNewCompletion && empty($completedThisRun) && !empty($pastDueNotDone)) {
            uasort($pastDueNotDone, function ($a, $b) {
                return $b <=> $a;
            }); // most recent first
            $latestId = key($pastDueNotDone);
            $firebase->update(
                'trees/' . $treeId . '/spray_schedules/' . $latestId,
                ['status' => 'completed', 'completed_at' => date('M d, Y g:i A')]
            );
            $completedThisRun[] = $latestId;
            $latestDate = $pastDueNotDone[$latestId];
            if ($lastCompletedDate === null || $latestDate > $lastCompletedDate) {
                $lastCompletedDate = $latestDate;
            }
        }

        // Advance the per-tree marker so old completions aren't reprocessed.
        if ($isNewCompletion) {
            $firebase->set('system_meta/spray_sync/' . $treeId, $historyTsSec);
        }

        /* ── Step 2: Create ONE next schedule (+interval months) if none pending exists ── */
        if (!$hasFuturePending && ($lastCompletedDate !== null || $isNewCompletion)) {
            if (ensureNextSchedule($firebase, $treeId, null, DEFAULT_SPRAY_INTERVAL_MONTHS)) {
                $stats['generated']++;
            } else {
                $stats['errors'][] = "Failed to generate next schedule for: {$treeId}";
            }
        }
    }

    // Synchronize system notifications to Firebase /notifications
    syncSystemNotifications($firebase, $trees);

    return $stats;
}

/**
 * Synchronize system notifications into Firebase Realtime Database (/notifications).
 * Analyzes trees, spray schedules, disease scans, and activities to auto-populate notifications.
 * Accepts pre-fetched trees/users to avoid duplicate Firebase reads.
 *
 * @param Firebase $firebase Firebase instance
 * @param array|null $trees Pre-fetched trees array (optional)
 * @param array|null $users Pre-fetched Users array (optional)
 */
function syncSystemNotifications($firebase, $trees = null, $users = null)
{
    $existingNotifications = $firebase->get('notifications') ?? [];
    $existingNotifications = is_array($existingNotifications) ? $existingNotifications : [];

    $now = time();
    $todayStr = date('Y-m-d');
    $tomorrowStr = date('Y-m-d', strtotime('+1 day'));

    if ($trees === null) {
        $trees = $firebase->get('trees') ?? [];
    }
    $trees = is_array($trees) ? $trees : [];

    if ($users === null) {
        $users = $firebase->get('Users') ?? [];
    }
    $users = is_array($users) ? $users : [];

    $updates = [];

    $buildNotif = function ($id, $title, $description, $category, $priority, $treeId = null, $targetUrl = 'schedules.php', $customTs = null) use (&$updates, $existingNotifications, $now) {
        $isRead = false;
        $timestamp = $customTs ?? ($now * 1000);

        if (isset($existingNotifications[$id])) {
            $isRead = !empty($existingNotifications[$id]['read']);
            $timestamp = $existingNotifications[$id]['timestamp'] ?? $timestamp;
        }

        $updates[$id] = [
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'category' => $category,  // 'spray' | 'disease' | 'tree' | 'scan' | 'critical'
            'priority' => $priority,  // 'critical' | 'high' | 'medium' | 'low'
            'tree_id' => $treeId ?? '',
            'target_url' => $targetUrl,
            'timestamp' => (int) $timestamp,
            'read' => (bool) $isRead,
            'created_at' => date('M d, Y g:i A', (int) ($timestamp / 1000))
        ];
    };

    foreach ($trees as $treeId => $tree) {
        if ($treeId === '0' || $treeId === 0 || (string) $treeId === '0')
            continue;

        $schedules = $tree['spray_schedules'] ?? [];
        $hasPending = false;
        $diseaseCount = 0;

        if (is_array($schedules)) {
            foreach ($schedules as $schedId => $sched) {
                $st = strtolower($sched['status'] ?? 'pending');
                $sDate = $sched['spray_date'] ?? $sched['date'] ?? '';
                if (empty($sDate) && !empty($sched['timestamp'])) {
                    $ts = $sched['timestamp'] > 9999999999 ? $sched['timestamp'] / 1000 : $sched['timestamp'];
                    $sDate = date('Y-m-d', (int) $ts);
                }

                if ($st === 'pending' || $st === 'overdue') {
                    // Overdue schedules no longer count as an upcoming pending treatment,
                    // so unscheduled-treatment alerts can fire for these trees.
                    if ($st === 'pending') {
                        $hasPending = true;
                    }

                    if ($st === 'overdue') {
                        $buildNotif(
                            "notif_overdue_{$treeId}_{$sDate}",
                            "Missed Spray - Overdue",
                            "Tree {$treeId} was scheduled for spray on {$sDate} but was not marked as sprayed and is now overdue.",
                            "critical",
                            "critical",
                            $treeId,
                            "schedules.php"
                        );
                    } elseif (!empty($sDate)) {
                        if ($sDate < $todayStr) {
                            $buildNotif(
                                "notif_overdue_{$treeId}_{$sDate}",
                                "Missed Spray - Overdue",
                                "Tree {$treeId} was scheduled for spray on {$sDate} but was not marked as sprayed and is now overdue.",
                                "critical",
                                "critical",
                                $treeId,
                                "schedules.php"
                            );
                        } elseif ($sDate === $todayStr) {
                            $buildNotif(
                                "notif_today_{$treeId}_{$sDate}",
                                "Spray Scheduled Today",
                                "Treatment spray for Tree {$treeId} is scheduled for today.",
                                "spray",
                                "high",
                                $treeId,
                                "schedules.php"
                            );
                        } elseif ($sDate === $tomorrowStr) {
                            $buildNotif(
                                "notif_tomorrow_{$treeId}_{$sDate}",
                                "Spray Scheduled Tomorrow",
                                "Treatment spray for Tree {$treeId} is scheduled for tomorrow.",
                                "spray",
                                "medium",
                                $treeId,
                                "schedules.php"
                            );
                        }
                    }
                } elseif ($st === 'completed') {
                    $buildNotif(
                        "notif_comp_{$treeId}_{$schedId}",
                        "Spray Treatment Completed",
                        "Treatment spray for Tree {$treeId} was marked as completed.",
                        "spray",
                        "low",
                        $treeId,
                        "schedules.php"
                    );
                }
            }
        }

        // Scan activity & disease detections
        foreach ($users as $uid => $user) {
            if (!empty($user['scanResult']) && is_array($user['scanResult'])) {
                foreach ($user['scanResult'] as $scanId => $scan) {
                    $sTree = $scan['tree_id'] ?? '';
                    if (strcasecmp((string) $sTree, (string) $treeId) === 0) {
                        $disease = $scan['disease'] ?? $scan['diseaseName'] ?? $scan['disease_final'] ?? '';
                        $ts = $scan['timestamp'] ?? ($now * 1000);
                        $ts = (int) $ts > 9999999999 ? (int) $ts : (int) $ts * 1000;

                        if (!empty($disease)) {
                            $diseaseCount++;
                            $buildNotif(
                                "notif_disease_{$treeId}_{$scanId}",
                                "New Disease Detected: {$disease}",
                                "Disease '{$disease}' detected on Tree {$treeId} (" . ($scan['location'] ?? 'Map') . ").",
                                "disease",
                                "high",
                                $treeId,
                                "tree_map.php",
                                $ts
                            );
                        } else {
                            $buildNotif(
                                "notif_scan_{$treeId}_{$scanId}",
                                "New Scan Activity",
                                "A scan was uploaded for Tree {$treeId}.",
                                "scan",
                                "medium",
                                $treeId,
                                "scans.php",
                                $ts
                            );
                        }
                    }
                }
            }
        }

        // Tree management alerts
        if (!empty($tree['created_at']) || !empty($tree['timestamp'])) {
            $treeTs = $tree['timestamp'] ?? ($now * 1000);
            $treeTs = (int) $treeTs > 9999999999 ? (int) $treeTs : (int) $treeTs * 1000;
            $buildNotif(
                "notif_tree_{$treeId}",
                "New Tree Registered",
                "Tree {$treeId} registered at " . ($tree['location'] ?? 'Map') . ".",
                "tree",
                "low",
                $treeId,
                "trees.php",
                $treeTs
            );
        }

        // Critical: High Disease Severity
        if ($diseaseCount >= 3) {
            $buildNotif(
                "notif_highdis_{$treeId}",
                "High Disease Severity Alert",
                "Tree {$treeId} has {$diseaseCount} disease scan detections requiring urgent care.",
                "critical",
                "critical",
                $treeId,
                "tree_map.php"
            );
        }

        // Critical: Unscheduled Tree with Diseases
        if ($diseaseCount > 0 && !$hasPending) {
            $buildNotif(
                "notif_nosched_{$treeId}",
                "Unscheduled Treatment Alert",
                "Tree {$treeId} has active disease detections but no upcoming spray schedule.",
                "critical",
                "critical",
                $treeId,
                "schedules.php"
            );
        }
    }

    // Include existing manual/pushed notifications that aren't auto-generated
    foreach ($existingNotifications as $id => $notif) {
        if (!isset($updates[$id])) {
            $updates[$id] = $notif;
        }
    }

    // Sort descending by timestamp
    uasort($updates, function ($a, $b) {
        return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
    });

    // Only rewrite when the resulting set actually changed. This keeps the
    // real-time poll from re-PUTting an identical /notifications node every
    // interval (read state and original timestamps are already preserved).
    $existingForCompare = [];
    foreach ($existingNotifications as $id => $notif) {
        if (!is_array($notif))
            continue;
        $existingForCompare[$id] = [
            'id' => $id,
            'title' => $notif['title'] ?? '',
            'description' => $notif['description'] ?? '',
            'category' => $notif['category'] ?? '',
            'priority' => $notif['priority'] ?? '',
            'tree_id' => $notif['tree_id'] ?? '',
            'target_url' => $notif['target_url'] ?? '',
            'timestamp' => (int) ($notif['timestamp'] ?? 0),
            'read' => !empty($notif['read']),
            'created_at' => $notif['created_at'] ?? ''
        ];
    }
    $updatesForCompare = [];
    foreach ($updates as $id => $notif) {
        if (!is_array($notif))
            continue;
        $updatesForCompare[$id] = [
            'id' => $id,
            'title' => $notif['title'] ?? '',
            'description' => $notif['description'] ?? '',
            'category' => $notif['category'] ?? '',
            'priority' => $notif['priority'] ?? '',
            'tree_id' => $notif['tree_id'] ?? '',
            'target_url' => $notif['target_url'] ?? '',
            'timestamp' => (int) ($notif['timestamp'] ?? 0),
            'read' => !empty($notif['read']),
            'created_at' => $notif['created_at'] ?? ''
        ];
    }

    if (json_encode($updatesForCompare) !== json_encode($existingForCompare)) {
        $firebase->set('notifications', $updates);
    }
}
?>