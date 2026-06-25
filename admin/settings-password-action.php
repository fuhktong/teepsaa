<?php
session_start();
require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: /login-admin/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/settings.php');
    exit;
}

csrf_verify();

$userId          = $_SESSION['user_id'];
$currentPassword = $_POST['current_password'] ?? '';
$newPassword     = $_POST['new_password']     ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

$stmt = $pdo->prepare('SELECT password FROM admins WHERE id = ?');
$stmt->execute([$userId]);
$admin = $stmt->fetch();

if (!$admin || !password_verify($currentPassword, $admin['password'])) {
    $_SESSION['settings_error'] = 'Current password is incorrect.';
    header('Location: /admin/settings.php');
    exit;
}

if (strlen($newPassword) < 8) {
    $_SESSION['settings_error'] = 'New password must be at least 8 characters.';
    header('Location: /admin/settings.php');
    exit;
}

if ($newPassword !== $confirmPassword) {
    $_SESSION['settings_error'] = 'Passwords do not match.';
    header('Location: /admin/settings.php');
    exit;
}

$stmt = $pdo->prepare('UPDATE admins SET password = ? WHERE id = ?');
$stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);

$_SESSION['settings_success'] = 'Password updated.';
header('Location: /admin/settings.php');
exit;
