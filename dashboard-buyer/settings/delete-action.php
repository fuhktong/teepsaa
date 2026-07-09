<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../../config/db.php';
require __DIR__ . '/../../config/csrf.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'buyer') {
    header('Location: /login-buyer/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dashboard-buyer/settings/?tab=danger');
    exit;
}

csrf_verify();

$userId   = $_SESSION['user_id'];
$password = $_POST['password'] ?? '';

$stmt = $pdo->prepare('SELECT password FROM buyers WHERE id = ?');
$stmt->execute([$userId]);
$row = $stmt->fetch();

if (!password_verify($password, $row['password'])) {
    $_SESSION['settings_error'] = 'Incorrect password.';
    header('Location: /dashboard-buyer/settings/?tab=danger');
    exit;
}

$stmt = $pdo->prepare('DELETE FROM buyers WHERE id = ?');
$stmt->execute([$userId]);

session_destroy();
header('Location: /');
exit;
