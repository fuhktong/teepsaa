<?php
session_start();
require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/notify.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header('Location: /login-vendor/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /orders-vendor/');
    exit;
}

csrf_verify();

$userId      = $_SESSION['user_id'];
$orderId     = (int)($_POST['order_id'] ?? 0);
$trackingUrl = trim($_POST['tracking_url'] ?? '');

if (empty($trackingUrl) || !filter_var($trackingUrl, FILTER_VALIDATE_URL)) {
    $_SESSION['settings_error'] = 'A valid Grab tracking URL is required before dispatching.';
    header('Location: /orders-vendor/order.php?id=' . $orderId);
    exit;
}

$stmt = $pdo->prepare('
    UPDATE orders o
    JOIN businesses b ON b.id = o.business_id
    SET o.status = ?, o.dispatched_at = NOW(), o.tracking_url = ?
    WHERE o.id = ? AND b.user_id = ? AND o.status = ?
');
$stmt->execute(['dispatched', $trackingUrl, $orderId, $userId, 'paid']);

if ($stmt->rowCount() > 0) {
    $buyerStmt = $pdo->prepare(
        'SELECT bu.id AS buyer_id, bu.email, bu.name, o.id AS order_id, o.created_at
         FROM orders o JOIN buyers bu ON bu.id = o.buyer_user_id
         WHERE o.id = ?'
    );
    $buyerStmt->execute([$orderId]);
    $buyer = $buyerStmt->fetch();
    if ($buyer) {
        $oid = order_display_id((int)$buyer['order_id'], $buyer['created_at']);
        $msg = 'Your order #' . $oid . ' has been dispatched and is on its way.';
        notify($pdo, 'buyer', (int)$buyer['buyer_id'], 'order_dispatched', $msg, '/dashboard-buyer/order.php?id=' . $orderId, ['ref' => $oid]);
        [$subj, $html] = render_email_template($pdo, 'order_dispatched', [
            'name'    => htmlspecialchars($buyer['name']),
            'order'   => $oid,
            'cta_url' => 'https://teepsaa.com/dashboard-buyer/order.php?id=' . $orderId,
        ]);
        if ($html !== '') send_email($buyer['email'], $subj, $html);
    }
}

header('Location: /orders-vendor/order.php?id=' . $orderId);
exit;
