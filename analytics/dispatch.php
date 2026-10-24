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

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header('Location: /login-vendor/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /orders-vendor/');
    exit;
}

csrf_verify();

$lang = current_lang();
$t    = require __DIR__ . '/../lang/' . (in_array($lang, ['en', 'km']) ? $lang : 'en') . '.php';

$userId  = $_SESSION['user_id'];
$orderId = (int)($_POST['order_id'] ?? 0);

$pidStmt = $pdo->prepare('
    SELECT o.public_id FROM orders o
    JOIN businesses b ON b.id = o.business_id
    WHERE o.id = ? AND b.user_id = ? AND o.status = ?
');
$pidStmt->execute([$orderId, $userId, 'paid']);
$orderPublicId = $pidStmt->fetchColumn() ?: '';
$back = '/orders-vendor/order.php?id=' . $orderPublicId;

[$fields, $err] = dispatch_fields($_POST);
$photo = null;
if (!$err && $orderPublicId !== '') {
    [$photo, $err] = order_photo_save($_FILES['photo'] ?? null);
}
if ($err) {
    $_SESSION['dispatch_error'] = $t['vorder_dispatch_err_' . $err] ?? $t['vorder_dispatch_err_upload_failed'];
    header('Location: ' . $back);
    exit;
}

$stmt = $pdo->prepare('
    UPDATE orders o
    JOIN businesses b ON b.id = o.business_id
    SET o.status = ?, o.dispatched_at = NOW(), o.tracking_url = ?,
        o.shipping_method = ?, o.courier = ?, o.tracking_number = ?, o.dispatch_photo = ?
    WHERE o.id = ? AND b.user_id = ? AND o.status = ?
');
$stmt->execute(['dispatched', $fields['tracking_url'], $fields['shipping_method'], $fields['courier'],
    $fields['tracking_number'], $photo, $orderId, $userId, 'paid']);

if ($stmt->rowCount() > 0) dispatch_notify_buyer($pdo, $orderId);

if ($photo && $stmt->rowCount() === 0) @unlink(ORDER_PHOTO_DIR . '/' . $photo);

header('Location: ' . ($orderPublicId !== '' ? $back : '/orders-vendor/'));
exit;
