<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

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

$userId      = $_SESSION['user_id'];
$orderId     = (int)($_POST['order_id'] ?? 0);
$trackingUrl = trim($_POST['return_tracking_url'] ?? '');

$pidStmt = $pdo->prepare('SELECT public_id FROM orders WHERE id = ? AND buyer_user_id = ?');
$pidStmt->execute([$orderId, $userId]);
$orderPublicId = $pidStmt->fetchColumn() ?: '';

if (!$orderId || !$trackingUrl) {
    header($orderId ? 'Location: /dashboard-buyer/order.php?id=' . $orderPublicId : 'Location: /dashboard-buyer/');
    exit;
}

$stmt = $pdo->prepare("
    UPDATE orders
    SET status = 'return_dispatched', return_tracking_url = ?
    WHERE id = ? AND buyer_user_id = ? AND status = 'return_approved'
");
$stmt->execute([$trackingUrl, $orderId, $userId]);

if ($stmt->rowCount()) {
    // Tell the vendor a return is on its way so they can mark it received
    $vStmt = $pdo->prepare(
        'SELECT o.created_at, v.id AS vendor_id
         FROM orders o
         JOIN businesses b ON b.id = o.business_id
         JOIN vendors v ON v.id = b.user_id
         WHERE o.id = ?'
    );
    $vStmt->execute([$orderId]);
    if ($vendor = $vStmt->fetch()) {
        $oid = order_display_id($orderId, $vendor['created_at']);
        notify($pdo, 'vendor', (int)$vendor['vendor_id'], 'return_dispatched',
            'The buyer shipped a return for order #' . $oid . ' — mark it received when it arrives.',
            '/orders-vendor/order.php?id=' . $orderPublicId, ['ref' => $oid]);
    }
}

header('Location: /dashboard-buyer/order.php?id=' . $orderPublicId);
exit;
