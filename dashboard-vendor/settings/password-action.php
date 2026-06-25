<?php
session_start();
require __DIR__ . '/../../config/db.php';
require __DIR__ . '/../../config/csrf.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header('Location: /login-vendor/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dashboard-vendor/settings/?tab=password');
    exit;
}

csrf_verify();

$userId  = $_SESSION['user_id'];
$current = $_POST['current_password'] ?? '';
$new     = $_POST['new_password']     ?? '';
$confirm = $_POST['confirm_password'] ?? '';

$stmt = $pdo->prepare('SELECT password FROM vendors WHERE id = ?');
$stmt->execute([$userId]);
$row = $stmt->fetch();

if (!password_verify($current, $row['password'])) {
    $_SESSION['settings_error'] = 'Current password is incorrect.';
    header('Location: /dashboard-vendor/settings/?tab=password');
    exit;
}

if (strlen($new) < 8) {
    $_SESSION['settings_error'] = 'New password must be at least 8 characters.';
    header('Location: /dashboard-vendor/settings/?tab=password');
    exit;
}

if ($new !== $confirm) {
    $_SESSION['settings_error'] = 'New passwords do not match.';
    header('Location: /dashboard-vendor/settings/?tab=password');
    exit;
}

$stmt = $pdo->prepare('UPDATE vendors SET password = ? WHERE id = ?');
$stmt->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);

$_SESSION['settings_success'] = 'Password updated.';
header('Location: /dashboard-vendor/settings/?tab=password');
exit;
