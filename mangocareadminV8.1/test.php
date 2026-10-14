<?php
/**
 * Simple test file to verify PHP is working
 */
echo "PHP is working!<br>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "Current directory: " . __DIR__ . "<br>";
echo "Script name: " . $_SERVER['PHP_SELF'] . "<br>";

// Test if we can load config
try {
    require_once 'config/config.php';
    echo "✓ Config loaded successfully<br>";
    echo "Site Name: " . SITE_NAME . "<br>";
} catch (Exception $e) {
    echo "✗ Config error: " . $e->getMessage() . "<br>";
}
?>
