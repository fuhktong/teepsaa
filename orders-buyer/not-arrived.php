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
require __DIR__ . '/../config/notify.php';
require __DIR__ . '/../config/refund-evidence.php';

// The buyer's "I didn't receive it", on a parcel still on its way or inside
// the refund window after delivery. No photo — there is nothing to photograph.
// Becomes a dispute for the vendor's reply and an admin's decision
// (config/refund-evidence.php, parcel_not_arrived()).

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'buyer') {
    header('Location: /login-buyer/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /orders-buyer/');
    exit;
}

csrf_verify();

$userId  = (int)$_SESSION['user_id'];
$orderId = (int)($_POST['order_id'] ?? 0);

$pidStmt = $pdo->prepare('SELECT public_id FROM orders WHERE id = ? AND buyer_user_id = ?');
$pidStmt->execute([$orderId, $userId]);
$orderPublicId = $pidStmt->fetchColumn();
if (!$orderPublicId) {
    header('Location: /orders-buyer/');
    exit;
}

// Any refusal means the order already moved on — the page shows where.
parcel_not_arrived($pdo, $orderId, $userId);

header('Location: /orders-buyer/order.php?id=' . $orderPublicId);
exit;
