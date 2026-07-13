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
    if ($stmt->rowCount()) $emailKey = 'refund_approved';
    $_SESSION['admin_success'] = 'Return approved — buyer has been notified to send item back.';

} elseif ($action === 'reject') {
    $stmt = $pdo->prepare("UPDATE orders SET status = 'refund_rejected' WHERE id = ? AND status = 'refund_requested'");
    $stmt->execute([$orderId]);
    if ($stmt->rowCount()) $emailKey = 'refund_rejected';
    $_SESSION['admin_success'] = 'Refund request rejected.';

} elseif ($action === 'complete') {
    $stmt = $pdo->prepare("UPDATE orders SET status = 'refunded', refunded_at = NOW() WHERE id = ? AND status = 'return_received'");
    $stmt->execute([$orderId]);
    if ($stmt->rowCount()) $emailKey = 'refund_sent';
    $_SESSION['admin_success'] = 'Order marked as refunded. Remember to send the buyer their subtotal via ABA.';
}

if ($emailKey) {
    $bStmt = $pdo->prepare(
        'SELECT o.public_id, o.created_at, bu.name, bu.email
         FROM orders o JOIN buyers bu ON bu.id = o.buyer_user_id
         WHERE o.id = ?'
    );
    $bStmt->execute([$orderId]);
    if ($buyer = $bStmt->fetch()) {
        $oid = order_display_id($orderId, $buyer['created_at']);
        [$subj, $html] = render_email_template($pdo, $emailKey, [
            'name'    => htmlspecialchars($buyer['name']),
            'order'   => $oid,
            'cta_url' => 'https://teepsaa.com/dashboard-buyer/order.php?id=' . $buyer['public_id'],
        ]);
        if ($html !== '') send_email($buyer['email'], $subj, $html);
    }
}

header('Location: ' . $redirectUrl);
exit;
