<?php
session_start();
require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/notify.php';

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

$stmt = $pdo->prepare('
    UPDATE orders SET status = ?, delivered_at = NOW()
    WHERE id = ? AND buyer_user_id = ? AND status = ?
');
$stmt->execute(['delivered', $orderId, $userId, 'dispatched']);

if ($stmt->rowCount() > 0) {
    $vendorStmt = $pdo->prepare(
        'SELECT v.id AS vendor_id, v.email, v.name, o.id AS order_id, o.created_at
         FROM orders o
         JOIN businesses b ON b.id = o.business_id
         JOIN vendors v ON v.id = b.user_id
         WHERE o.id = ? AND o.buyer_user_id = ?'
    );
    $vendorStmt->execute([$orderId, $userId]);
    $vendor = $vendorStmt->fetch();
    if ($vendor) {
        $oid = order_display_id((int)$vendor['order_id'], $vendor['created_at']);
        $msg = 'Delivery confirmed for order #' . $oid . ' — payout incoming.';
        notify($pdo, 'vendor', (int)$vendor['vendor_id'], 'delivery_confirmed', $msg, '/orders-vendor/order.php?id=' . $orderId, ['ref' => $oid]);
        [$subj, $html] = render_email_template($pdo, 'delivery_confirmed', [
            'name'    => htmlspecialchars($vendor['name']),
            'order'   => $oid,
            'cta_url' => 'https://teepsaa.com/orders-vendor/order.php?id=' . $orderId,
        ]);
        if ($html !== '') send_email($vendor['email'], $subj, $html);
    }
}

header('Location: /dashboard-buyer/order.php?id=' . $orderId);
exit;
