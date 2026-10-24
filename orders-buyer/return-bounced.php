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

// The buyer's "The shop refused it / it came back to me", with photos of the
// returned parcel or the carrier's note. Holds the return for an admin
// (config/refund-evidence.php, return_bounce()).

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

$err = return_bounce($pdo, $orderId, $userId, refund_uploaded_files());
// wrong_status: already reported, or the shop got there first — the page shows which.
if ($err && $err !== 'wrong_status') {
    $lang = current_lang();
    $t = require __DIR__ . '/../lang/' . (in_array($lang, ['en', 'km']) ? $lang : 'en') . '.php';
    $key = $err === 'missing_photo' ? 'order_bounce_err_missing_photo' : 'order_refund_err_' . $err;
    $_SESSION['flash_error'] = sprintf($t[$key] ?? $t['order_refund_err_upload_failed'], REFUND_PHOTOS_MAX);
}

header('Location: /orders-buyer/order.php?id=' . $orderPublicId);
exit;
