<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

require __DIR__ . '/../../config/db.php';
require __DIR__ . '/../../config/csrf.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header('Location: /login-vendor/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dashboard-vendor/settings/?tab=account');
    exit;
}

csrf_verify();

$color = (int)($_POST['color'] ?? 0);
if ($color < 0 || $color > 4) $color = 0;

$pdo->prepare('UPDATE vendors SET avatar_color = ? WHERE id = ?')->execute([$color, $_SESSION['user_id']]);
$_SESSION['user_avatar_color']  = $color;
$_SESSION['settings_success']   = 'Avatar color updated.';
header('Location: /dashboard-vendor/settings/?tab=account');
exit;
