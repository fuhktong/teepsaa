<?php
session_start();
require __DIR__ . '/../../config/db.php';
require __DIR__ . '/../../config/csrf.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header('Location: /login-vendor/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dashboard-vendor/settings/');
    exit;
}

csrf_verify();

$userId = $_SESSION['user_id'];
$name   = trim($_POST['name'] ?? '');
$phone  = trim($_POST['phone'] ?? '');

if (!$name) {
    $_SESSION['settings_error'] = 'Full name is required.';
    header('Location: /dashboard-vendor/settings/?tab=account');
    exit;
}

$stmt = $pdo->prepare('UPDATE vendors SET name = ?, phone = ? WHERE id = ?');
$stmt->execute([$name, $phone ?: null, $userId]);

$_SESSION['user_name'] = $name;
$_SESSION['settings_success'] = 'Account updated.';
header('Location: /dashboard-vendor/settings/?tab=account');
exit;
