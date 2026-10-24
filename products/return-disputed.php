<?php
// The vendor's second button when a return arrives: "Item is used, empty or
// different", with photos of what came back. Holds the refund for an admin to
// decide (config/refund-evidence.php). The first button is return-received.php.
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

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header('Location: /login-vendor/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /orders-vendor/?tab=refunds');
    exit;
}

// A body larger than post_max_size arrives as nothing at all, CSRF token
// included — say so rather than failing the token check.
$lang = current_lang();
$t = require __DIR__ . '/../lang/' . (in_array($lang, ['en', 'km']) ? $lang : 'en') . '.php';
if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $_SESSION['flash_error'] = $t['order_refund_err_too_large'];
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/orders-vendor/?tab=refunds'));
    exit;
}

csrf_verify();

$userId  = (int)$_SESSION['user_id'];
$orderId = (int)($_POST['order_id'] ?? 0);

$pid = $pdo->prepare('SELECT o.public_id FROM orders o JOIN businesses b ON b.id = o.business_id WHERE o.id = ? AND b.user_id = ?');
$pid->execute([$orderId, $userId]);
$publicId = $pid->fetchColumn();
if (!$publicId) {
    header('Location: /orders-vendor/?tab=refunds');
    exit;
}

$err = return_dispute($pdo, $orderId, $userId, refund_uploaded_files());
// wrong_status means it already moved on; the page shows where it is.
if ($err && $err !== 'wrong_status') {
    $_SESSION['flash_error'] = sprintf($t['vorder_return_err_' . $err] ?? $t['order_refund_err_' . $err] ?? $t['order_refund_err_upload_failed'], REFUND_PHOTOS_MAX);
}

header('Location: /orders-vendor/refund.php?id=' . $publicId);
exit;
