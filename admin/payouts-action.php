<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

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
        'SELECT v.id AS vendor_id, v.email, v.name, o.id AS order_id, o.public_id AS order_public_id, o.created_at
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
        notify($pdo, 'vendor', (int)$vendor['vendor_id'], 'payout_sent', $msg, '/orders-vendor/order.php?id=' . $vendor['order_public_id'], ['ref' => $oid]);
        [$subj, $html] = render_email_template($pdo, 'payout_sent', [
            'name'    => htmlspecialchars($vendor['name']),
            'order'   => $oid,
            'cta_url' => 'https://teepsaa.com/orders-vendor/order.php?id=' . $vendor['order_public_id'],
        ]);
        if ($html !== '') send_email($vendor['email'], $subj, $html);
    }
}

$_SESSION['admin_success'] = 'Order marked as completed. Payout recorded.';
header('Location: /admin/order.php?id=' . $orderId);
exit;
