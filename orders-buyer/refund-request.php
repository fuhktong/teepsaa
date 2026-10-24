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

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'buyer') {
    header('Location: /login-buyer/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /orders-buyer/');
    exit;
}

csrf_verify();

$userId  = $_SESSION['user_id'];
$orderId = (int)($_POST['order_id'] ?? 0);

$pidStmt = $pdo->prepare("SELECT public_id, delivered_at, refund_rejected_at FROM orders WHERE id = ? AND buyer_user_id = ? AND status = 'delivered'");
$pidStmt->execute([$orderId, $userId]);
$existing = $pidStmt->fetch();
$orderPublicId = $existing['public_id'] ?? '';

if (!$existing) {
    header('Location: /orders-buyer/');
    exit;
}
$back = 'Location: /orders-buyer/order.php?id=' . $orderPublicId;

// A denied request is final — the order is back at 'delivered', so without
// this it could be requested again and again inside the same window.
if ($existing['refund_rejected_at']) {
    header($back);
    exit;
}

// 24-hour refund window — only enforced when delivered_at is set
if ($existing['delivered_at'] && (time() - strtotime($existing['delivered_at'])) >= PAYOUT_WINDOW_SECONDS) {
    header($back);
    exit;
}

$lang = current_lang();
$t = require __DIR__ . '/../lang/' . (in_array($lang, ['en', 'km']) ? $lang : 'en') . '.php';

[$reason, $err] = refund_reason_from(trim($_POST['reason_preset'] ?? ''), $_POST['reason_other'] ?? '');
$photos = [];
if (!$err) [$photos, $err] = refund_photos_store(refund_uploaded_files(), refund_needs_photo($reason));
if ($err) {
    $_SESSION['flash_error'] = sprintf($t['order_refund_err_' . $err] ?? $t['order_refund_err_upload_failed'], REFUND_PHOTOS_MAX);
    header($back);
    exit;
}

$stmt = $pdo->prepare("
    UPDATE orders
    SET status = ?, refund_reason = ?, refund_requested_at = NOW()
    WHERE id = ? AND buyer_user_id = ? AND status = 'delivered' AND refund_rejected_at IS NULL
");
$stmt->execute([refund_request_status($reason), $reason, $orderId, $userId]);

if ($stmt->rowCount()) {
    refund_photos_record($pdo, $orderId, 'buyer', 'request', $photos);
    refund_request_notify($pdo, $orderId);
} else {
    refund_photos_discard($photos);
}

header($back);
exit;
