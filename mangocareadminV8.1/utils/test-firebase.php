<?php
/**
 * Firebase Connection Test
 * Use this file to test your Firebase configuration
 * Access: http://localhost/mangocareadmin/test-firebase.php
 */

require_once '../config/config.php';

// Check if user is logged in (optional - remove if you want to test without login)
// requireLogin();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firebase Connection Test - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .test-result {
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
        }
        .test-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #059669;
        }
        .test-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #dc2626;
        }
        .test-info {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            color: #2563eb;
        }
        pre {
            background: #f3f4f6;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="main-content" style="max-width: 800px; margin: 50px auto;">
        <div class="page-header">
            <h1>Firebase Connection Test</h1>
            <p>Test your Firebase configuration and connection</p>
        </div>
        
        <?php
        $tests = [];
        
        // Test 1: Check cURL
        $tests['cURL'] = function_exists('curl_init');
        
        // Test 2: Check Firebase URL (from google-services.json)
        $tests['Firebase URL'] = !empty(FIREBASE_DATABASE_URL) && strpos(FIREBASE_DATABASE_URL, 'firebasedatabase') !== false;
        
        // Test 3: Check Database Secret (optional)
        $tests['Database Secret'] = !empty(FIREBASE_DATABASE_SECRET);
        
        // Test 4: Try to connect to Firebase
        $firebaseConnected = false;
        $firebaseError = '';
        try {
            $firebase = new Firebase();
            $testData = $firebase->get('test');
            $firebaseConnected = true;
        } catch (Exception $e) {
            $firebaseError = $e->getMessage();
        }
        $tests['Firebase Connection'] = $firebaseConnected;
        
        // Test 5: Try to write data
        $writeTest = false;
        $writeError = '';
        if ($firebaseConnected) {
            try {
                $firebase = new Firebase();
                $testWriteData = [
                    'test' => 'connection',
                    'timestamp' => time() * 1000,
                    'php_version' => PHP_VERSION
                ];
                $result = $firebase->push('test_connection', $testWriteData);
                $writeTest = !empty($result);
            } catch (Exception $e) {
                $writeError = $e->getMessage();
            }
        }
        $tests['Write Test'] = $writeTest;
        
        // Test 6: Try to read data back
        $readTest = false;
        if ($writeTest) {
            try {
                $firebase = new Firebase();
                $readData = $firebase->get('test_connection');
                $readTest = !empty($readData);
            } catch (Exception $e) {
                $readTest = false;
            }
        }
        $tests['Read Test'] = $readTest;
        
        // Display results
        foreach ($tests as $testName => $result) {
            $class = $result ? 'test-success' : 'test-error';
            $icon = $result ? '✅' : '❌';
            echo "<div class='test-result {$class}'>";
            echo "<strong>{$icon} {$testName}:</strong> " . ($result ? 'PASSED' : 'FAILED');
            echo "</div>";
        }
        
        // Display configuration info
        echo "<div class='test-result test-info'>";
        echo "<strong>📋 Configuration (from google-services.json):</strong><br>";
        echo "<strong>Project ID:</strong> " . (defined('FIREBASE_PROJECT_ID') ? htmlspecialchars(FIREBASE_PROJECT_ID) : '—') . "<br>";
        $dbHost = parse_url(FIREBASE_DATABASE_URL ?? '', PHP_URL_HOST);
        echo "<strong>Database URL:</strong> " . (!empty(FIREBASE_DATABASE_URL) ? 'Configured (' . htmlspecialchars($dbHost ?: '') . ')' : '❌ Not configured') . "<br>";
        echo "<strong>Database Secret:</strong> " . (!empty(FIREBASE_DATABASE_SECRET) ? 'Set' : 'Not set (using public access)') . "<br>";
        echo "<strong>PHP Version:</strong> " . PHP_VERSION . "<br>";
        echo "<strong>cURL Enabled:</strong> " . (function_exists('curl_init') ? 'Yes' : 'No');
        echo "</div>";
        
        // Display errors if any
        if ($firebaseError) {
            echo "<div class='test-result test-error'>";
            echo "<strong>Connection Error:</strong> " . htmlspecialchars($firebaseError);
            echo "</div>";
        }
        
        if ($writeError) {
            echo "<div class='test-result test-error'>";
            echo "<strong>Write Error:</strong> " . htmlspecialchars($writeError);
            echo "</div>";
        }
        
        // Display sample data structure
        if ($readTest) {
            echo "<div class='test-result test-success'>";
            echo "<strong>✅ Sample Data Retrieved:</strong>";
            echo "<pre>" . print_r($readData, true) . "</pre>";
            echo "</div>";
        }
        
        // Instructions
        echo "<div class='card' style='margin-top: 20px;'>";
        echo "<div class='card-header'><h2>Next Steps</h2></div>";
        echo "<div class='card-body'>";
        if (!$tests['Firebase URL']) {
            echo "<p>❌ <strong>Action Required:</strong> Update FIREBASE_DATABASE_URL in config.php</p>";
        }
        if (!$firebaseConnected) {
            echo "<p>❌ <strong>Action Required:</strong> Check your Firebase configuration and database rules</p>";
        }
        if ($firebaseConnected && $writeTest && $readTest) {
            echo "<p>✅ <strong>All tests passed!</strong> Your Firebase connection is working correctly.</p>";
            echo "<p>You can now:</p>";
            echo "<ul>";
            echo "<li><a href='../auth/login.php'>Login to the admin panel</a></li>";
            echo "<li>Start using the system</li>";
            echo "</ul>";
        }
        echo "</div>";
        echo "</div>";
        ?>
        
        <div class="action-buttons" style="margin-top: 20px;">
            <a href="../auth/login.php" class="btn btn-primary">Go to Login</a>
            <a href="../admin/index.php" class="btn btn-outline">Go to Dashboard</a>
        </div>
    </div>
</body>
</html>
