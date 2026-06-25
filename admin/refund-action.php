<?php
session_start();
require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: /login-admin/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/refunds.php');
    exit;
}

csrf_verify();

$action  = $_POST['action'] ?? '';
$orderId = (int)($_POST['order_id'] ?? 0);

if (!$orderId) {
    header('Location: /admin/refunds.php');
    exit;
}

$redirectUrl = '/admin/refund.php?id=' . $orderId;

if ($action === 'approve') {
    $stmt = $pdo->prepare("UPDATE orders SET status = 'return_approved' WHERE id = ? AND status = 'refund_requested'");
    $stmt->execute([$orderId]);
    $_SESSION['admin_success'] = 'Return approved — buyer has been notified to send item back.';

} elseif ($action === 'reject') {
    $stmt = $pdo->prepare("UPDATE orders SET status = 'refund_rejected' WHERE id = ? AND status = 'refund_requested'");
    $stmt->execute([$orderId]);
    $_SESSION['admin_success'] = 'Refund request rejected.';

} elseif ($action === 'complete') {
    $stmt = $pdo->prepare("UPDATE orders SET status = 'refunded', refunded_at = NOW() WHERE id = ? AND status = 'return_received'");
    $stmt->execute([$orderId]);
    $_SESSION['admin_success'] = 'Order marked as refunded. Remember to send the buyer their subtotal via ABA.';
}

header('Location: ' . $redirectUrl);
exit;
