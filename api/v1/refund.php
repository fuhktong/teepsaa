<?php
// One refund in full, matching orders-vendor/refund.php.
//
// Read-only. The things a vendor can do from here — reply to a dispute,
// confirm the returned item arrived, or say it is used, empty or different —
// are their own endpoints, refund-reply.php, refund-received.php and
// refund-disputed.php, because they write.

require __DIR__ . '/../../config/api.php';
require __DIR__ . '/../../config/url.php';
require __DIR__ . '/../../config/refund-evidence.php';

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
           o.return_disputed_at, o.refund_paid, o.return_bounced_at,
           o.refund_requested_at, o.vendor_reply, o.vendor_reply_at,
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
// The buyer says the shop refused it or it came back to them: teepsaa decides,
// and the shop's buttons are closed (config/refund-evidence.php).
$bounced = !empty($o['return_bounced_at']);

api_json([
    'public_id'       => $o['public_id'],
    'ref'             => date('ymd', strtotime($o['created_at'])) . '-' . str_pad((string)$orderId, 4, '0', STR_PAD_LEFT),
    'status'          => $o['status'],
    // 'return_disputed' is a return the vendor reported as used, empty or
    // different, held at 'return_received' until teepsaa decides.
    // 'return_bounced' is one the buyer says was refused or came back to them,
    // held at 'return_dispatched' the same way.
    'refund_state'    => refund_state($o),
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
    // What the buyer gets back: the product price, or what teepsaa decided
    // after a disputed return (part of it, or 0).
    'refund_amount'   => refund_figure($o),
    'refund_reason'   => $o['refund_reason'] ?: null,
    // The buyer's photos of the problem, sent with the request. Empty for a
    // change of mind and for requests made before photos were asked for.
    'buyer_photos'    => array_map(fn($f) => order_photo_url($f, true), refund_photos_of($pdo, $orderId)['buyer_request'] ?? []),
    // The vendor's photos of what came back, sent with "used, empty or different".
    'vendor_photos'   => array_map(fn($f) => order_photo_url($f, true), refund_photos_of($pdo, $orderId)['vendor_return'] ?? []),
    'photos_max'      => REFUND_PHOTOS_MAX,
    // The vendor's one reply to a dispute. can_reply while it is open, with
    // reply_until its deadline; vendor_reply once sent. reply_until stays set
    // after the deadline while teepsaa has not decided, so the screen can say
    // the time is up.
    'can_reply'       => refund_reply_open($o) && !$closed,
    'reply_until'     => ($u = refund_reply_until($o)) !== null ? date('Y-m-d H:i:s', $u) : null,
    'reply_max'       => REFUND_REPLY_MAX_LENGTH,
    'vendor_reply'    => $o['vendor_reply_at'] ? [
        'text'   => $o['vendor_reply'],
        'at'     => $o['vendor_reply_at'],
        'photos' => array_map(fn($f) => order_photo_url($f, true), refund_photos_of($pdo, $orderId)['vendor_reply'] ?? []),
    ] : null,
    'return_tracking_url' => $inTransit ? $returnUrl : null,
    // Confirming receipt is the vendor's only move here, it only exists while
    // the return is in transit, and a closed shop cannot make it — teepsaa
    // finishes those by hand.
    'can_confirm'     => $inTransit && !$bounced && !$closed,
]);
