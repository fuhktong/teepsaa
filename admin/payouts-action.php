<?php
session_start();
require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/notify.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: /login-admin/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/orders.php');
    exit;
}

csrf_verify();

$orderId = (int)($_POST['order_id'] ?? 0);

$stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ? AND status = ?');
$stmt->execute(['completed', $orderId, 'delivered']);

if ($stmt->rowCount() > 0) {
    $vendorStmt = $pdo->prepare(
        'SELECT v.id AS vendor_id, v.email, v.name, o.id AS order_id, o.created_at
         FROM orders o
         JOIN businesses b ON b.id = o.business_id
         JOIN vendors v ON v.id = b.user_id
         WHERE o.id = ?'
    );
    $vendorStmt->execute([$orderId]);
    $vendor = $vendorStmt->fetch();
    if ($vendor) {
        $oid = order_display_id((int)$vendor['order_id'], $vendor['created_at']);
        $msg = 'Your payout for order #' . $oid . ' has been sent to your ABA account.';
        notify($pdo, 'vendor', (int)$vendor['vendor_id'], 'payout_sent', $msg, '/orders-vendor/order.php?id=' . $orderId);
        send_email(
            $vendor['email'],
            'Your payout has been sent',
            notification_email_html(
                'Payout sent',
                'Hi ' . htmlspecialchars($vendor['name']) . ', your payout for order <strong>#' . $oid . '</strong> has been sent to your ABA account.',
                'View order',
                'https://teepsaa.com/orders-vendor/order.php?id=' . $orderId
            )
        );
    }
}

$_SESSION['admin_success'] = 'Order marked as completed. Payout recorded.';
header('Location: /admin/order.php?id=' . $orderId);
exit;
