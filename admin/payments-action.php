<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/admin-auth.php';
require __DIR__ . '/../config/notify.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: /login-admin/');
    exit;
}

admin_require('payments');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/orders.php');
    exit;
}

csrf_verify();

$paymentId = (int)($_POST['payment_id'] ?? 0);
$action    = $_POST['action'] ?? '';
$orderId   = (int)($_POST['order_id'] ?? 0);

$stmt = $pdo->prepare('SELECT id, status FROM payments WHERE id = ?');
$stmt->execute([$paymentId]);
$payment = $stmt->fetch();

if (!$payment || $payment['status'] !== 'pending_confirmation') {
    header('Location: /admin/orders.php');
    exit;
}

if ($action === 'confirm') {
    $pdo->beginTransaction();
    $pdo->prepare('UPDATE payments SET status = ? WHERE id = ?')
        ->execute(['confirmed', $paymentId]);
    $pdo->prepare('UPDATE orders SET status = ? WHERE payment_id = ?')
        ->execute(['paid', $paymentId]);
    $pdo->commit();

    $buyerStmt = $pdo->prepare(
        'SELECT bu.id AS buyer_id, bu.email, bu.name, o.id AS order_id, o.public_id AS order_public_id, o.created_at
         FROM orders o JOIN buyers bu ON bu.id = o.buyer_user_id
         WHERE o.payment_id = ? LIMIT 1'
    );
    $buyerStmt->execute([$paymentId]);
    $buyer = $buyerStmt->fetch();
    if ($buyer) {
        $oid = order_display_id((int)$buyer['order_id'], $buyer['created_at']);
        $msg = 'Your payment has been confirmed — order #' . $oid . ' is being prepared.';
        notify($pdo, 'buyer', (int)$buyer['buyer_id'], 'payment_confirmed', $msg, '/dashboard-buyer/order.php?id=' . $buyer['order_public_id'], ['ref' => $oid]);
        [$subj, $html] = render_email_template($pdo, 'payment_confirmed', [
            'name'    => htmlspecialchars($buyer['name']),
            'order'   => $oid,
            'cta_url' => 'https://teepsaa.com/dashboard-buyer/order.php?id=' . $buyer['order_public_id'],
        ]);
        if ($html !== '') send_email($buyer['email'], $subj, $html);
    }

    $_SESSION['admin_success'] = 'Payment confirmed. Vendors have been notified.';

} elseif ($action === 'reject') {
    $pdo->beginTransaction();
    $pdo->prepare('UPDATE payments SET status = ? WHERE id = ?')
        ->execute(['rejected', $paymentId]);
    $pdo->prepare('UPDATE orders SET status = ? WHERE payment_id = ?')
        ->execute(['cancelled', $paymentId]);
    // Restore stock for all items in this payment's orders
    $pdo->prepare('
        UPDATE products p
        JOIN order_items oi ON oi.product_id = p.id
        JOIN orders o ON o.id = oi.order_id
        SET p.stock = p.stock + oi.quantity
        WHERE o.payment_id = ?
    ')->execute([$paymentId]);
    $pdo->commit();
    $_SESSION['admin_success'] = 'Payment rejected.';
}

if ($orderId) {
    header('Location: /admin/order.php?id=' . $orderId);
} else {
    header('Location: /admin/orders.php');
}
exit;
