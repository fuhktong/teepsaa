<?php
session_start();
require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'buyer') {
    header('Location: /login-buyer/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dashboard-buyer/');
    exit;
}

csrf_verify();

$userId      = $_SESSION['user_id'];
$orderId     = (int)($_POST['order_id'] ?? 0);
$trackingUrl = trim($_POST['return_tracking_url'] ?? '');

if (!$orderId || !$trackingUrl) {
    header($orderId ? 'Location: /dashboard-buyer/order.php?id=' . $orderId : 'Location: /dashboard-buyer/');
    exit;
}

$stmt = $pdo->prepare("
    UPDATE orders
    SET status = 'return_dispatched', return_tracking_url = ?
    WHERE id = ? AND buyer_user_id = ? AND status = 'return_approved'
");
$stmt->execute([$trackingUrl, $orderId, $userId]);

header('Location: /dashboard-buyer/order.php?id=' . $orderId);
exit;
