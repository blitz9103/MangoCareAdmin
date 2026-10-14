<?php
require_once '../config/config.php';
requireLogin();

if (isset($_GET['uid']) && isset($_GET['status'])) {
    $uid = trim($_GET['uid']);
    $rawStatus = strtolower(trim($_GET['status']));
    $isActive = !in_array($rawStatus, ['disabled', 'inactive', 'banned', 'false', '0', 'no', 'off'], true);
    $newStatus = $isActive ? 'disabled' : 'active';

    $firebase = new Firebase();
    $result = $firebase->update("Users/$uid", ['status' => $newStatus]);

    if ($result !== false) {
        $msg = "User status updated to " . ucfirst($newStatus);
        header("Location: manageaccount.php?msg=" . urlencode($msg));
    } else {
        header("Location: manageaccount.php?err=" . urlencode("Failed to update user status"));
    }
    exit();
}

header("Location: manageaccount.php");
exit();
