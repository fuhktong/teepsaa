<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header('Location: /login-vendor/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /products/');
    exit;
}

csrf_verify();

$notifId = (int)($_POST['notification_id'] ?? 0);
if ($notifId) {
    $stmt = $pdo->prepare('UPDATE vendor_notifications SET read_at = NOW() WHERE id = ? AND vendor_user_id = ? AND read_at IS NULL');
    $stmt->execute([$notifId, $_SESSION['user_id']]);
}

header('Location: /products/');
exit;
