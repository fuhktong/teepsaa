<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

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

$pidStmt = $pdo->prepare("SELECT public_id, delivered_at FROM orders WHERE id = ? AND buyer_user_id = ? AND status = 'delivered'");
$pidStmt->execute([$orderId, $userId]);
$existing = $pidStmt->fetch();
$orderPublicId = $existing['public_id'] ?? '';

if (!$orderId || !$reason) {
    header($orderId ? 'Location: /dashboard-buyer/order.php?id=' . $orderPublicId : 'Location: /dashboard-buyer/');
    exit;
}

if (!$existing) {
    header('Location: /dashboard-buyer/order.php?id=' . $orderPublicId);
    exit;
}

// 24-hour refund window — only enforced when delivered_at is set
if ($existing['delivered_at'] && (time() - strtotime($existing['delivered_at'])) >= PAYOUT_WINDOW_SECONDS) {
    header('Location: /dashboard-buyer/order.php?id=' . $orderPublicId);
    exit;
}

$stmt = $pdo->prepare("
    UPDATE orders
    SET status = 'refund_requested', refund_reason = ?, refund_requested_at = NOW()
    WHERE id = ? AND buyer_user_id = ? AND status = 'delivered'
");
$stmt->execute([$reason, $orderId, $userId]);

header('Location: /dashboard-buyer/order.php?id=' . $orderPublicId);
exit;
