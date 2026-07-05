<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header('Location: /login-vendor/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /products/?tab=refunds');
    exit;
}

csrf_verify();

$userId  = $_SESSION['user_id'];
$orderId = (int)($_POST['order_id'] ?? 0);

if (!$orderId) {
    header('Location: /products/?tab=refunds');
    exit;
}

$stmt = $pdo->prepare("
    UPDATE orders o
    JOIN businesses b ON b.id = o.business_id
    SET o.status = 'return_received'
    WHERE o.id = ? AND b.user_id = ? AND o.status = 'return_dispatched'
");
$stmt->execute([$orderId, $userId]);

header('Location: /products/?tab=refunds');
exit;
