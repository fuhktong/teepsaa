<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header('Location: /login-vendor/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /products/?tab=orders');
    exit;
}

csrf_verify();

$userId   = $_SESSION['user_id'];
$orderId  = (int)($_POST['order_id'] ?? 0);
$url      = trim($_POST['tracking_url'] ?? '');

// Verify vendor owns this order
$stmt = $pdo->prepare('
    SELECT o.id FROM orders o
    JOIN businesses b ON b.id = o.business_id
    WHERE o.id = ? AND b.user_id = ?
');
$stmt->execute([$orderId, $userId]);
if (!$stmt->fetch()) {
    header('Location: /products/?tab=orders');
    exit;
}

if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
    $_SESSION['settings_error'] = 'Invalid tracking URL.';
    header('Location: /products/?tab=orders');
    exit;
}

$stmt = $pdo->prepare('UPDATE orders SET tracking_url = ? WHERE id = ?');
$stmt->execute([$url ?: null, $orderId]);

$_SESSION['settings_success'] = 'Tracking link saved.';
header('Location: /products/?tab=orders');
exit;
