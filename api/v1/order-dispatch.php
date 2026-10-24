<?php
// Mark one order dispatched: how it was sent, an optional tracking link or
// number, and a required photo of the packed parcel.
//
// Sent as multipart/form-data — id, shipping_method, courier, tracking and the
// file as `photo`. The rules are dispatch_fields() and order_photo_save() in
// config/order-photo.php, shared with the website's form.
//
// The app's first write. Everything analytics/dispatch.php checks is repeated
// here, because there is no browser and no form in front of this:
//
//   ownership    the UPDATE joins businesses and matches b.user_id, so a token
//                for the wrong vendor changes nothing and is told not_found.
//   status       `AND o.status = 'paid'` is in the UPDATE itself, not read
//                first and written after. Two taps that arrive together cannot
//                both dispatch: the second matches no row, so the buyer gets
//                one notification and one email, not two.
//   tracking URL run through safe_external_url(), which rejects javascript:
//                and data: links. This value is later rendered as a clickable
//                href to the buyer and to admins, so a bad one is stored XSS.
//   photo        required. The first app build sent JSON with only a Grab
//                link; it never reached a store, so it now gets missing_photo.
//                The photo is saved only once the order is known to be this
//                vendor's and still paid, so a refused call leaves no file.
//
// CSRF has no equivalent here on purpose — there is no cookie to ride on. The
// bearer token is not sent by the browser automatically, which is the whole
// reason CSRF protection exists. The reasoning is in config/api.php.

require __DIR__ . '/../../config/api.php';
require __DIR__ . '/../../config/url.php';
require __DIR__ . '/../../config/notify.php';
require __DIR__ . '/../../config/order-photo.php';

api_require_method('POST');

$vendor = api_require_vendor($pdo);
$userId = (int)$vendor['id'];

// A file larger than post_max_size is dropped before this script starts and
// PHP hands over an empty $_POST and $_FILES — see api/v1/products/photo.php.
$isMultipart = str_starts_with((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'multipart/form-data');
if ($isMultipart && empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    api_json(['error' => 'too_large', 'limit_mb' => 2], 413);
}

$body     = $_POST ?: api_body();
$publicId = trim((string)($body['id'] ?? ''));

if ($publicId === '') api_json(['error' => 'missing_id'], 400);

[$fields, $err] = dispatch_fields($body);
if ($err) api_json(['error' => $err], 400);

// Resolve the public id to a row this vendor owns, and find out what state it
// is in. Separating "no such order" from "already dispatched" matters: the
// first is a bug worth showing, the second is a vendor tapping twice.
$look = $pdo->prepare('
    SELECT o.id, o.status, o.created_at
      FROM orders o
      JOIN businesses b ON b.id = o.business_id
     WHERE o.public_id = ? AND b.user_id = ?
');
$look->execute([$publicId, $userId]);
$order = $look->fetch();

if (!$order) api_json(['error' => 'not_found'], 404);

if ($order['status'] !== 'paid') {
    api_json([
        'error'  => 'wrong_status',
        'status' => $order['status'],
    ], 409);
}

$orderId = (int)$order['id'];

[$photo, $err] = order_photo_save($_FILES['photo'] ?? null);
if ($err) {
    api_json(['error' => $err] + ($err === 'too_large' ? ['limit_mb' => 2] : []), $err === 'too_large' ? 413 : ($err === 'save_failed' ? 500 : 400));
}

$upd = $pdo->prepare("
    UPDATE orders o
      JOIN businesses b ON b.id = o.business_id
       SET o.status = 'dispatched', o.dispatched_at = NOW(), o.tracking_url = ?,
           o.shipping_method = ?, o.courier = ?, o.tracking_number = ?, o.dispatch_photo = ?
     WHERE o.id = ? AND b.user_id = ? AND o.status = 'paid'
");
$upd->execute([$fields['tracking_url'], $fields['shipping_method'], $fields['courier'],
    $fields['tracking_number'], $photo, $orderId, $userId]);

// Zero rows means something else got there first between the read above and
// this write. Nothing was changed and nothing was sent, so say so plainly
// rather than reporting a dispatch that did not happen.
if ($upd->rowCount() === 0) {
    @unlink(ORDER_PHOTO_DIR . '/' . $photo);
    $now = $pdo->prepare('SELECT status FROM orders WHERE id = ?');
    $now->execute([$orderId]);
    api_json([
        'error'  => 'wrong_status',
        'status' => $now->fetchColumn() ?: 'unknown',
    ], 409);
}

// The order is dispatched at this point. A failure past here must not be
// reported as a failed dispatch, or the vendor taps again and gets a 409 on
// an order that is already on its way.
try {
    $buyerStmt = $pdo->prepare('
        SELECT bu.id AS buyer_id, bu.email, bu.name,
               o.id AS order_id, o.public_id AS order_public_id, o.created_at
          FROM orders o JOIN buyers bu ON bu.id = o.buyer_user_id
         WHERE o.id = ?
    ');
    $buyerStmt->execute([$orderId]);
    $buyer = $buyerStmt->fetch();

    if ($buyer) {
        $oid = order_display_id((int)$buyer['order_id'], $buyer['created_at']);
        notify(
            $pdo, 'buyer', (int)$buyer['buyer_id'], 'order_dispatched',
            'Your order #' . $oid . ' has been dispatched and is on its way.',
            '/orders-buyer/order.php?id=' . $buyer['order_public_id'],
            ['ref' => $oid]
        );
        [$subj, $html] = render_email_template($pdo, 'order_dispatched', [
            'name'    => htmlspecialchars($buyer['name']),
            'order'   => $oid,
            'cta_url' => 'https://teepsaa.com/orders-buyer/order.php?id=' . $buyer['order_public_id'],
        ]);
        if ($html !== '') send_email($buyer['email'], $subj, $html);
    }
} catch (Throwable $e) {
    error_log('order-dispatch: notify failed for order ' . $orderId . ': ' . $e->getMessage());
}

api_json([
    'ok'           => true,
    'status'       => 'dispatched',
    'tracking_url'    => $fields['tracking_url'],
    'shipping_method' => $fields['shipping_method'],
    'courier'         => $fields['courier'],
    'tracking_number' => $fields['tracking_number'],
    'dispatch_photo'  => order_photo_url($photo, true),
]);
