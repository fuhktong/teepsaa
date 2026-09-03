<?php
session_start([
    'gc_maxlifetime'  => 28800,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/admin-auth.php';
require __DIR__ . '/../config/audit.php';
require __DIR__ . '/../config/notify.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: /login-admin/');
    exit;
}

admin_require('refunds');

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

$emailKey = null;

if ($action === 'approve') {
    $stmt = $pdo->prepare("UPDATE orders SET status = 'return_approved' WHERE id = ? AND status = 'refund_requested'");
    $stmt->execute([$orderId]);
    if ($stmt->rowCount()) {
        $emailKey = 'refund_approved';
        audit_log($pdo, 'refund.approve', 'order', $orderId);
    }
    $_SESSION['admin_success'] = 'Return approved — buyer has been notified to send item back.';

} elseif ($action === 'reject') {
    // The request came from a delivered order, so denying it puts the order
    // back exactly where it was — otherwise it can never reach 'completed'
    // and falls out of the payout queue, and the vendor is never paid.
    // The rejection is recorded in its own column instead of in status.
    $stmt = $pdo->prepare("UPDATE orders SET status = 'delivered', refund_rejected_at = NOW() WHERE id = ? AND status = 'refund_requested'");
    $stmt->execute([$orderId]);
    if ($stmt->rowCount()) {
        $emailKey = 'refund_rejected';
        audit_log($pdo, 'refund.reject', 'order', $orderId);
    }
    $_SESSION['admin_success'] = 'Refund request rejected.';

} elseif ($action === 'complete') {
    $stmt = $pdo->prepare("UPDATE orders SET status = 'refunded', refunded_at = NOW() WHERE id = ? AND status = 'return_received'");
    $stmt->execute([$orderId]);
    if ($stmt->rowCount()) {
        $emailKey = 'refund_sent';
        // Money out — same weight as a payout, so log the amount with it.
        $amtStmt = $pdo->prepare('SELECT subtotal FROM orders WHERE id = ?');
        $amtStmt->execute([$orderId]);
        audit_log($pdo, 'refund.complete', 'order', $orderId, [
            'amount' => (float)$amtStmt->fetchColumn(),
        ]);
    }
    $_SESSION['admin_success'] = 'Order marked as refunded. Remember to send the buyer their subtotal via ABA.';
}

if ($emailKey) {
    // The bell notification type matches the email template key one-to-one.
    $notifMsgs = [
        'refund_approved' => 'Your refund was approved for order #%s — please send the item back.',
        'refund_rejected' => 'Your refund request for order #%s was declined.',
        'refund_sent'     => 'Your refund for order #%s has been sent.',
    ];
    $bStmt = $pdo->prepare(
        'SELECT o.public_id, o.created_at, bu.id AS buyer_id, bu.name, bu.email
         FROM orders o JOIN buyers bu ON bu.id = o.buyer_user_id
         WHERE o.id = ?'
    );
    $bStmt->execute([$orderId]);
    if ($buyer = $bStmt->fetch()) {
        $oid = order_display_id($orderId, $buyer['created_at']);
        [$subj, $html] = render_email_template($pdo, $emailKey, [
            'name'    => htmlspecialchars($buyer['name']),
            'order'   => $oid,
            'cta_url' => 'https://teepsaa.com/orders-buyer/order.php?id=' . $buyer['public_id'],
        ]);
        if ($html !== '') send_email($buyer['email'], $subj, $html);
        notify($pdo, 'buyer', (int)$buyer['buyer_id'], $emailKey,
            sprintf($notifMsgs[$emailKey] ?? 'Update on order #%s.', $oid),
            '/orders-buyer/order.php?id=' . $buyer['public_id'], ['ref' => $oid]);
    }
}

header('Location: ' . $redirectUrl);
exit;
