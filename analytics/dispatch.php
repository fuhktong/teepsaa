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

if ($stmt->rowCount() > 0) {
    $buyerStmt = $pdo->prepare(
        'SELECT bu.id AS buyer_id, bu.email, bu.name, o.id AS order_id, o.public_id AS order_public_id, o.created_at
         FROM orders o JOIN buyers bu ON bu.id = o.buyer_user_id
         WHERE o.id = ?'
    );
    $buyerStmt->execute([$orderId]);
    $buyer = $buyerStmt->fetch();
    if ($buyer) {
        $oid = order_display_id((int)$buyer['order_id'], $buyer['created_at']);
        $msg = 'Your order #' . $oid . ' has been dispatched and is on its way.';
        notify($pdo, 'buyer', (int)$buyer['buyer_id'], 'order_dispatched', $msg, '/orders-buyer/order.php?id=' . $buyer['order_public_id'], ['ref' => $oid]);
        [$subj, $html] = render_email_template($pdo, 'order_dispatched', [
            'name'    => htmlspecialchars($buyer['name']),
            'order'   => $oid,
            'cta_url' => 'https://teepsaa.com/orders-buyer/order.php?id=' . $buyer['order_public_id'],
        ]);
        if ($html !== '') send_email($buyer['email'], $subj, $html);
    }
}

if ($photo && $stmt->rowCount() === 0) @unlink(ORDER_PHOTO_DIR . '/' . $photo);

header('Location: ' . ($orderPublicId !== '' ? $back : '/orders-vendor/'));
exit;
