<?php
// Mark one order dispatched, with its Grab tracking link.
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
//
// CSRF has no equivalent here on purpose — there is no cookie to ride on. The
// bearer token is not sent by the browser automatically, which is the whole
// reason CSRF protection exists. The reasoning is in config/api.php.

require __DIR__ . '/../../config/api.php';
require __DIR__ . '/../../config/url.php';
require __DIR__ . '/../../config/notify.php';

api_require_method('POST');

$vendor = api_require_vendor($pdo);
$userId = (int)$vendor['id'];

$body        = api_body();
$publicId    = trim((string)($body['id'] ?? ''));
$trackingUrl = safe_external_url((string)($body['tracking_url'] ?? ''));

if ($publicId === '')      api_json(['error' => 'missing_id'], 400);
if ($trackingUrl === null) api_json(['error' => 'invalid_tracking_url'], 400);

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

$upd = $pdo->prepare("
    UPDATE orders o
      JOIN businesses b ON b.id = o.business_id
       SET o.status = 'dispatched', o.dispatched_at = NOW(), o.tracking_url = ?
     WHERE o.id = ? AND b.user_id = ? AND o.status = 'paid'
");
$upd->execute([$trackingUrl, $orderId, $userId]);

// Zero rows means something else got there first between the read above and
// this write. Nothing was changed and nothing was sent, so say so plainly
// rather than reporting a dispatch that did not happen.
if ($upd->rowCount() === 0) {
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
    'tracking_url' => $trackingUrl,
]);
