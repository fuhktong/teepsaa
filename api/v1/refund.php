<?php
// One refund in full, matching orders-vendor/refund.php.
//
// Read-only. The single thing a vendor can do from here — confirm the returned
// item arrived — is its own endpoint, refund-received.php, because it writes.

require __DIR__ . '/../../config/api.php';
require __DIR__ . '/../../config/url.php';

api_require_method('GET');

$vendor = api_require_vendor($pdo);
$userId = (int)$vendor['id'];

$publicId = trim((string)($_GET['id'] ?? ''));
if ($publicId === '') api_json(['error' => 'missing_id'], 400);

// Same pair of conditions as the list: status, or a recorded rejection that
// has already put status back to 'delivered'.
$stmt = $pdo->prepare("
    SELECT o.id, o.public_id, o.subtotal, o.delivery_fee, o.discount_amount, o.coupon_code,
           o.status, o.created_at, o.refund_reason, o.refund_rejected_at, o.return_tracking_url,
           b.name AS business_name,
           b.deleted_at AS business_closed,
           u.name AS buyer_name, u.email AS buyer_email
      FROM orders o
      JOIN businesses b ON b.id = o.business_id
      JOIN buyers u ON u.id = o.buyer_user_id
     WHERE o.public_id = ? AND b.user_id = ?
       AND (o.status IN ('refund_requested', 'return_approved', 'return_dispatched',
                         'return_received', 'refunded', 'refund_rejected')
            OR o.refund_rejected_at IS NOT NULL)
");
$stmt->execute([$publicId, $userId]);
$o = $stmt->fetch();

// One reply for "not yours", "no such order" and "not a refund". Telling them
// apart would confirm that an id exists to someone guessing at ids.
if (!$o) api_json(['error' => 'not_found'], 404);

$orderId = (int)$o['id'];
$closed  = $o['business_closed'] !== null;

$itemStmt = $pdo->prepare('
    SELECT product_name, variant_label, quantity, price_at_purchase
      FROM order_items WHERE order_id = ? ORDER BY id
');
$itemStmt->execute([$orderId]);

$items = [];
foreach ($itemStmt->fetchAll() as $i) {
    $price = (float)$i['price_at_purchase'];
    $qty   = (int)$i['quantity'];
    $items[] = [
        'name'     => $i['product_name'],
        'variant'  => $i['variant_label'],
        'quantity' => $qty,
        'price'    => round($price, 2),
        'line_total' => round($price * $qty, 2),
    ];
}

$subtotal = (float)$o['subtotal'];
$discount = (float)$o['discount_amount'];

// A link typed in by the buyer. safe_external_url turns anything that is not a
// real http(s) address into null, so a javascript: or data: link can never
// reach the app as something tappable. Re-checked here on the way out even
// though it was checked on the way in.
$returnUrl = safe_external_url($o['return_tracking_url'] ?? null);

// The website only offers the return link while the return is in transit, and
// only then is there anything for the vendor to do with it.
$inTransit = $o['status'] === 'return_dispatched';

api_json([
    'public_id'       => $o['public_id'],
    'ref'             => date('ymd', strtotime($o['created_at'])) . '-' . str_pad((string)$orderId, 4, '0', STR_PAD_LEFT),
    'status'          => $o['status'],
    'refund_state'    => $o['refund_rejected_at'] ? 'refund_rejected' : $o['status'],
    'created_at'      => $o['created_at'],
    'business_name'   => $o['business_name'],
    'business_closed' => $closed,
    'buyer_name'      => $o['buyer_name'] ?: $o['buyer_email'],
    'items'           => $items,
    'subtotal'        => round($subtotal, 2),
    'discount'        => round($discount, 2),
    'coupon_code'     => $discount > 0 ? $o['coupon_code'] : null,
    // Delivery was paid to the Grab driver, not to teepsaa, so it is not part
    // of what goes back to the buyer. Always 0 today; sent so the app never has
    // to work out a refund figure of its own.
    'delivery_fee'    => round((float)$o['delivery_fee'], 2),
    'refund_amount'   => round($subtotal - $discount, 2),
    'refund_reason'   => $o['refund_reason'] ?: null,
    'return_tracking_url' => $inTransit ? $returnUrl : null,
    // Confirming receipt is the vendor's only move here, it only exists while
    // the return is in transit, and a closed shop cannot make it — teepsaa
    // finishes those by hand.
    'can_confirm'     => $inTransit && !$closed,
]);
