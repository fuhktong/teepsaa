<?php
// One order, in full — the screen a vendor opens from the Orders tab.
//
// Mirrors orders-vendor/order.php. Two things that page does are worth
// repeating here, because they are easy to lose in a rewrite:
//
//   1. Only the five live statuses are served. A refund has its own statuses,
//      its own money rules and its own screen on the website, so an order in
//      one of those is a 404 here rather than a half-filled order screen.
//   2. A closed (soft-deleted) business keeps its sales record but loses the
//      buyer's phone and address. There is nothing left to deliver, so a shut
//      shop must not stay a lookup window on where past customers live.
//
// Read-only. Dispatching is a separate POST endpoint (order-dispatch.php), so
// nothing here can change an order by being loaded twice.

require __DIR__ . '/../../config/api.php';
require __DIR__ . '/../../config/url.php';

api_require_method('GET');

$vendor = api_require_vendor($pdo);
$userId = (int)$vendor['id'];

$publicId = trim((string)($_GET['id'] ?? ''));
if ($publicId === '') api_json(['error' => 'missing_id'], 400);

// public_id, never the numeric id. The app is handed a random identifier so a
// vendor cannot walk other people's orders by counting upwards — and the
// b.user_id check means guessing one correctly still gets them nothing.
$stmt = $pdo->prepare("
    SELECT o.id, o.public_id, o.subtotal, o.delivery_fee, o.vendor_delivery_bonus,
           o.royalty_rate, o.royalty_amount, o.vendor_payout,
           o.coupon_code, o.discount_amount,
           o.status, o.created_at, o.tracking_url, o.buyer_notes,
           b.name AS business_name, b.deleted_at AS business_closed,
           u.name AS buyer_name, u.email AS buyer_email, u.phone AS buyer_phone,
           u.house_number AS buyer_house_number, u.address AS buyer_address,
           u.address_notes AS buyer_address_notes,
           u.khan AS buyer_khan, u.sangkat AS buyer_sangkat
      FROM orders o
      JOIN businesses b ON b.id = o.business_id
      JOIN buyers u ON u.id = o.buyer_user_id
     WHERE o.public_id = ? AND b.user_id = ?
       AND o.status IN ('pending', 'paid', 'dispatched', 'delivered', 'completed')
");
$stmt->execute([$publicId, $userId]);
$o = $stmt->fetch();

if (!$o) api_json(['error' => 'not_found'], 404);

$orderId        = (int)$o['id'];
$businessClosed = !empty($o['business_closed']);

$itemStmt = $pdo->prepare('
    SELECT product_name, variant_label, quantity, price_at_purchase
      FROM order_items WHERE order_id = ? ORDER BY id
');
$itemStmt->execute([$orderId]);

$items = [];
foreach ($itemStmt->fetchAll() as $it) {
    $price = (float)$it['price_at_purchase'];
    $qty   = (int)$it['quantity'];
    $items[] = [
        'name'       => $it['product_name'],
        'variant'    => $it['variant_label'],
        'quantity'   => $qty,
        'price'      => round($price, 2),
        'line_total' => round($price * $qty, 2),
    ];
}

$subtotal = (float)$o['subtotal'];
$discount = (float)$o['discount_amount'];

// Money maths lives here, not in the app. delivery_fee is excluded on purpose:
// delivery is cash on delivery, so the buyer pays the driver directly and that
// figure is an estimate for their benefit, never money teepsaa moves.
$total = round($subtotal - $discount, 2);

$royaltyAmt = round((float)($o['royalty_amount'] ?? ($subtotal * (float)($o['royalty_rate'] ?? 0))), 2);
$royaltyPct = round((float)($o['royalty_rate'] ?? 0) * 100, 1);

// A vendor's own coupon comes out of their payout; a sitewide one is absorbed by
// the platform. Rather than guess which kind this was, derive the vendor-funded
// part from what checkout actually stored — that stays right either way.
$vendorCouponDiscount = max(0, round($subtotal - $royaltyAmt - (float)$o['vendor_payout'], 2));
$payout = round($subtotal - $royaltyAmt - $vendorCouponDiscount, 2);

// Typed in by the vendor, shown as a link. Stored rows are re-checked on the
// way out because some predate the validation (config/url.php).
$trackingUrl = safe_external_url($o['tracking_url']);
$showTracking = $trackingUrl !== null
    && in_array($o['status'], ['dispatched', 'delivered', 'completed'], true);

$reply = [
    'public_id'  => $o['public_id'],
    'ref'        => date('ymd', strtotime($o['created_at'])) . '-' . str_pad((string)$orderId, 4, '0', STR_PAD_LEFT),
    'status'     => $o['status'],
    'created_at' => $o['created_at'],

    'business_name'   => $o['business_name'],
    'business_closed' => $businessClosed,

    'buyer_name' => $o['buyer_name'] ?: $o['buyer_email'],

    'items'        => $items,
    'subtotal'     => round($subtotal, 2),
    'discount'     => round($discount, 2),
    'coupon_code'  => $o['coupon_code'],
    'delivery_fee' => round((float)$o['delivery_fee'], 2),
    'total'        => $total,

    'payout' => [
        'royalty_percent' => $royaltyPct,
        'royalty_amount'  => $royaltyAmt,
        'coupon_discount' => $vendorCouponDiscount,
        'delivery_bonus'  => round((float)$o['vendor_delivery_bonus'], 2),
        'amount'          => $payout,
    ],

    'tracking_url' => $showTracking ? $trackingUrl : null,

    // The app should not re-derive this from the status: the rule for who may
    // dispatch lives on the server, and order-dispatch.php enforces it again.
    'can_dispatch' => $o['status'] === 'paid',
];

// Contact and delivery details only while the shop is open.
if ($businessClosed) {
    $reply['buyer_phone']   = null;
    $reply['grab_address']  = null;
    $reply['address_notes'] = null;
    $reply['buyer_notes']   = null;
} else {
    $grabParts = array_filter([
        trim(($o['buyer_house_number'] ?? '') . ' ' . ($o['buyer_address'] ?? '')),
        $o['buyer_sangkat'] ?? '',
        $o['buyer_khan'] ?? '',
        'Phnom Penh',
    ]);
    $grabAddress = implode(', ', $grabParts);

    $reply['buyer_phone']   = $o['buyer_phone'] ?: null;
    $reply['grab_address']  = ($grabAddress !== '' && $grabAddress !== 'Phnom Penh') ? $grabAddress : null;
    $reply['address_notes'] = $o['buyer_address_notes'] ?: null;
    $reply['buyer_notes']   = $o['buyer_notes'] ?: null;
}

api_json($reply);
