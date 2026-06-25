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

$userId  = $_SESSION['user_id'];
$orderId = (int)($_POST['order_id'] ?? 0);
$preset  = trim($_POST['reason_preset'] ?? '');
$reason  = $preset === 'other' ? trim($_POST['reason_other'] ?? '') : $preset;

if (!$orderId || !$reason) {
    header($orderId ? 'Location: /dashboard-buyer/order.php?id=' . $orderId : 'Location: /dashboard-buyer/');
    exit;
}

$check = $pdo->prepare("SELECT delivered_at FROM orders WHERE id = ? AND buyer_user_id = ? AND status = 'delivered'");
$check->execute([$orderId, $userId]);
$existing = $check->fetch();

if (!$existing) {
    header('Location: /dashboard-buyer/order.php?id=' . $orderId);
    exit;
}

// 24-hour refund window — only enforced when delivered_at is set
if ($existing['delivered_at'] && (time() - strtotime($existing['delivered_at'])) >= PAYOUT_WINDOW_SECONDS) {
    header('Location: /dashboard-buyer/order.php?id=' . $orderId);
    exit;
}

$stmt = $pdo->prepare("
    UPDATE orders
    SET status = 'refund_requested', refund_reason = ?, refund_requested_at = NOW()
    WHERE id = ? AND buyer_user_id = ? AND status = 'delivered'
");
$stmt->execute([$reason, $orderId, $userId]);

header('Location: /dashboard-buyer/order.php?id=' . $orderId);
exit;
