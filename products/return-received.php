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

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header('Location: /login-vendor/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /products/?tab=refunds');
    exit;
}

csrf_verify();

$userId  = $_SESSION['user_id'];
$orderId = (int)($_POST['order_id'] ?? 0);

if (!$orderId) {
    header('Location: /products/?tab=refunds');
    exit;
}

$stmt = $pdo->prepare("
    UPDATE orders o
    JOIN businesses b ON b.id = o.business_id
    SET o.status = 'return_received'
    WHERE o.id = ? AND b.user_id = ? AND o.status = 'return_dispatched'
");
$stmt->execute([$orderId, $userId]);

if ($stmt->rowCount()) {
    // Reassure the buyer their return arrived and the refund is moving
    $bStmt = $pdo->prepare('SELECT public_id, created_at, buyer_user_id FROM orders WHERE id = ?');
    $bStmt->execute([$orderId]);
    if ($buyer = $bStmt->fetch()) {
        $oid = order_display_id((int)$orderId, $buyer['created_at']);
        notify($pdo, 'buyer', (int)$buyer['buyer_user_id'], 'return_received',
            'Your return for order #' . $oid . ' was received — your refund is being processed.',
            '/dashboard-buyer/order.php?id=' . $buyer['public_id'], ['ref' => $oid]);
    }
}

header('Location: /products/?tab=refunds');
exit;
