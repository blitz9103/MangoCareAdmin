<?php
/**
 * QR Code Download Handler
 * Generates an image file with QR code and tree details
 */

require_once '../config/config.php';
requireLogin();

// Check if GD library is available
if (!function_exists('imagecreatetruecolor')) {
    header('HTTP/1.1 500 Internal Server Error');
    die('GD library is not available. Please enable it in php.ini');
}

$treeId = $_GET['tree_id'] ?? '';
$location = $_GET['location'] ?? '';
$qrUrl = $_GET['qr_url'] ?? '';

if (empty($treeId) || empty($location) || empty($qrUrl)) {
    header('HTTP/1.1 400 Bad Request');
    die('Missing required parameters');
}

// Fetch QR code image
$qrImageData = @file_get_contents($qrUrl);
if ($qrImageData === false) {
    header('HTTP/1.1 500 Internal Server Error');
    die('Failed to fetch QR code image');
}

// Create image from QR code
$qrImage = @imagecreatefromstring($qrImageData);
if ($qrImage === false) {
    header('HTTP/1.1 500 Internal Server Error');
    die('Failed to create image from QR code');
}

// Get QR code dimensions
$qrWidth = imagesx($qrImage);
$qrHeight = imagesy($qrImage);

// Calculate canvas size (QR code + padding + text)
$padding = 40;
$textHeight = 80;
$canvasWidth = $qrWidth + ($padding * 2);
$canvasHeight = $qrHeight + ($padding * 2) + $textHeight;

// Create canvas
$canvas = imagecreatetruecolor($canvasWidth, $canvasHeight);

// Set background to white
$white = imagecolorallocate($canvas, 255, 255, 255);
imagefill($canvas, 0, 0, $white);

// Copy QR code to canvas (centered horizontally, at top)
$qrX = ($canvasWidth - $qrWidth) / 2;
$qrY = $padding;
imagecopy($canvas, $qrImage, $qrX, $qrY, 0, 0, $qrWidth, $qrHeight);

// Set text color (black)
$black = imagecolorallocate($canvas, 0, 0, 0);

// Calculate text position (below QR code, centered)
$textY = $qrY + $qrHeight + 30;
$textX = $canvasWidth / 2;

// Use a built-in font (5 is the largest built-in font)
$font = 5;

// Calculate text width for centering
$treeIdText = "Tree ID: " . $treeId;
$locationText = "Location: " . $location;

$treeIdWidth = imagefontwidth($font) * strlen($treeIdText);
$locationWidth = imagefontwidth($font) * strlen($locationText);

// Draw Tree ID text (centered)
imagestring($canvas, $font, ($canvasWidth - $treeIdWidth) / 2, $textY, $treeIdText, $black);

// Draw Location text (centered, below Tree ID)
imagestring($canvas, $font, ($canvasWidth - $locationWidth) / 2, $textY + 30, $locationText, $black);

// Set headers for download
header('Content-Type: image/png');
header('Content-Disposition: attachment; filename="qr-' . htmlspecialchars($treeId) . '.png"');

// Output image to buffer first to get size
ob_start();
imagepng($canvas);
$imageData = ob_get_contents();
ob_end_clean();

// Set content length
header('Content-Length: ' . strlen($imageData));

// Output image
echo $imageData;

// Clean up
imagedestroy($canvas);
imagedestroy($qrImage);
?>
