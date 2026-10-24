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
require __DIR__ . '/../config/url.php';
require __DIR__ . '/../config/order-photo.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'buyer') {
    header('Location: /login-buyer/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /orders-buyer/');
    exit;
}

csrf_verify();

$userId      = $_SESSION['user_id'];
$orderId     = (int)($_POST['order_id'] ?? 0);
$trackingUrl = trim($_POST['return_tracking_url'] ?? '');

$pidStmt = $pdo->prepare('SELECT public_id FROM orders WHERE id = ? AND buyer_user_id = ?');
$pidStmt->execute([$orderId, $userId]);
$orderPublicId = $pidStmt->fetchColumn() ?: '';

$rdLang = current_lang();
$rdT = require __DIR__ . '/../lang/' . (in_array($rdLang, ['en', 'km'], true) ? $rdLang : 'en') . '.php';
$back = $orderId ? 'Location: /orders-buyer/order.php?id=' . $orderPublicId : 'Location: /orders-buyer/';

// The link is optional — a courier may hand over only a waybill, which the
// photo of the label covers. When one is given it is shown to the vendor and
// to admins as a clickable link, so it has to be a real http(s) URL and not a
// javascript: payload. The whole URL including the scheme: we do not guess a
// missing "https://", because guessing wrong sends the vendor somewhere the
// buyer never intended.
if ($trackingUrl !== '') {
    $trackingUrl = safe_external_url($trackingUrl);
    if ($trackingUrl === null) {
        if ($orderId) $_SESSION['flash_error'] = $rdT['order_return_url_invalid'];
        header($back);
        exit;
    }
} else {
    $trackingUrl = null;
}

if (!$orderPublicId) {
    header($back);
    exit;
}

// The photo of the parcel and its label is the proof it was sent — what an
// admin looks at if the shop later says nothing arrived.
[$photo, $photoErr] = order_photo_save($_FILES['photo'] ?? null);
if ($photoErr) {
    $_SESSION['flash_error'] = $rdT['order_return_err_' . $photoErr] ?? $rdT['order_return_err_upload_failed'];
    header($back);
    exit;
}

$stmt = $pdo->prepare("
    UPDATE orders
    SET status = 'return_dispatched', return_tracking_url = ?, return_photo = ?, return_dispatched_at = NOW()
    WHERE id = ? AND buyer_user_id = ? AND status = 'return_approved'
");
$stmt->execute([$trackingUrl, $photo, $orderId, $userId]);

if (!$stmt->rowCount()) @unlink(ORDER_PHOTO_DIR . '/' . $photo);

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

header('Location: /orders-buyer/order.php?id=' . $orderPublicId);
exit;
