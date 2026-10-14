<?php
require_once '../config/config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: manageaccount.php');
    exit;
}

$name = trim($_POST['name'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$role = in_array($_POST['role'] ?? 'worker', ['worker', 'admin'], true) ? $_POST['role'] : 'worker';

if ($name === '' || $email === '' || $password === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: manageaccount.php?err=' . urlencode('All fields are required and a valid email must be provided'));
    exit;
}

$firebase = new Firebase();

$users = $firebase->get('Users') ?? [];
$users = is_array($users) ? $users : [];

// Duplicate email check (case-insensitive)
foreach ($users as $u) {
    if (is_array($u) && strtolower(trim($u['email'] ?? '')) === $email) {
        header('Location: manageaccount.php?err=' . urlencode('A user with this email already exists'));
        exit;
    }
}

// Create Firebase Auth account via Identity Toolkit
$apiKey = FIREBASE_API_KEY;
$authPayload = json_encode(['email' => $email, 'password' => $password, 'returnSecureToken' => true]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://identitytoolkit.googleapis.com/v1/accounts:signUp?key=' . $apiKey);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($ch, CURLOPT_POSTFIELDS, $authPayload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$authResponse = curl_exec($ch);
$authError = curl_error($ch);
curl_close($ch);

$auth = json_decode($authResponse, true);

if ($authError || !isset($auth['localId'])) {
    $reason = isset($auth['error']['message']) ? $auth['error']['message'] : ($authError ?: 'unknown error');
    header('Location: manageaccount.php?err=' . urlencode('Failed to create authentication account: ' . $reason));
    exit;
}

$uid = $auth['localId'];

$profile = [
    'name' => $name,
    'email' => $email,
    'username' => explode('@', $email)[0],
    'role' => $role,
    'status' => 'active',
    'uid' => $uid,
    'createdAt' => date('F j, Y - h:i A'),
    'createdAtTimestamp' => round(microtime(true) * 1000),
];

$result = $firebase->set("Users/{$uid}", $profile);

if ($result !== false) {
    header('Location: manageaccount.php?msg=' . urlencode('User account created successfully'));
} else {
    header('Location: manageaccount.php?err=' . urlencode('Account created but failed to save the profile record'));
}
exit;
