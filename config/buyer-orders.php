<?php
// A buyer's orders as the app sees them — orders-buyer/index.php and
// orders-buyer/order.php without the HTML. Used by api/v1/buyer/orders.php,
// order.php and order-action.php, so an action answers with exactly what the
// order screen draws and the two can never disagree.
//
// Required after config/api.php, which brings db.php (PAYOUT_WINDOW_SECONDS,
// pick_lang) with it.
//
// What the buyer may do next is decided here and sent as `actions`, not left
// for the app to work out from the status. order-action.php checks every one
// of them again before it writes anything.

require_once __DIR__ . '/url.php';
require_once __DIR__ . '/order-photo.php';
require_once __DIR__ . '/refund-evidence.php';

const BUYER_REFUND_STATUSES = ['refund_requested', 'return_approved', 'return_dispatched', 'return_received', 'refunded', 'refund_rejected'];

function buyer_order_ref(int $id, string $createdAt): string
{
    return date('ymd', strtotime($createdAt)) . '-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT);
}

/** The Orders tab: every order but cancelled ones, newest first — as the website. */
function buyer_order_list(PDO $pdo, int $userId, int $limit, int $offset): array
{
    $count = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE buyer_user_id = ? AND status <> 'cancelled'");
    $count->execute([$userId]);
    $total = (int)$count->fetchColumn();

    // Integers by the caller's casts, so inlining LIMIT is safe (see api/v1/orders.php).
    $stmt = $pdo->prepare("
        SELECT o.id, o.public_id, o.subtotal, o.discount_amount, o.delivery_fee, o.status, o.created_at,
               b.name AS business_name,
               GROUP_CONCAT(CONCAT(oi.product_name, IFNULL(CONCAT(' (', oi.variant_label, ')'), ''), ' x', oi.quantity)
                            ORDER BY oi.id SEPARATOR ', ') AS items,
               SUM(CASE WHEN r.id IS NULL THEN 1 ELSE 0 END) AS unreviewed_count
          FROM orders o
          JOIN businesses b ON b.id = o.business_id
          JOIN order_items oi ON oi.order_id = o.id
          LEFT JOIN reviews r ON r.order_item_id = oi.id
         WHERE o.buyer_user_id = ? AND o.status <> 'cancelled'
         GROUP BY o.id
         ORDER BY o.created_at DESC, o.id DESC
         LIMIT $limit OFFSET $offset
    ");
    $stmt->execute([$userId]);

    $orders = [];
    foreach ($stmt->fetchAll() as $o) {
        $reviewable = in_array($o['status'], ['delivered', 'completed'], true);
        $orders[] = [
            'public_id'     => $o['public_id'],
            'ref'           => buyer_order_ref((int)$o['id'], $o['created_at']),
            'status'        => $o['status'],
            'is_refund'     => in_array($o['status'], BUYER_REFUND_STATUSES, true),
            'items'         => $o['items'],
            'business_name' => $o['business_name'],
            'total'         => round((float)$o['subtotal'] - (float)$o['discount_amount'] + (float)$o['delivery_fee'], 2),
            'created_at'    => $o['created_at'],
            // Only counted where a review can be left, so the app can say
            // "leave a review" off this number alone.
            'unreviewed_count' => $reviewable ? (int)$o['unreviewed_count'] : 0,
        ];
    }

    return [
        'orders'   => $orders,
        'total'    => $total,
        'has_more' => ($offset + count($orders)) < $total,
    ];
}

/** One order in full, or null when it is not this buyer's. */
function buyer_order_detail(PDO $pdo, int $userId, string $publicId): ?array
{
    $stmt = $pdo->prepare('
        SELECT o.id, o.public_id, o.subtotal, o.delivery_fee, o.status, o.created_at, o.tracking_url,
               o.shipping_method, o.courier, o.tracking_number, o.dispatch_photo,
               o.refund_reason, o.refund_rejected_at, o.return_tracking_url, o.coupon_code, o.discount_amount,
               o.delivered_at, o.return_disputed_at, o.refund_paid,
               CASE WHEN o.delivered_at IS NULL OR TIMESTAMPDIFF(SECOND, o.delivered_at, NOW()) < ' . PAYOUT_WINDOW_SECONDS . ' THEN 1 ELSE 0 END AS refund_window_open,
               DATE_ADD(o.delivered_at, INTERVAL ' . PAYOUT_WINDOW_SECONDS . ' SECOND) AS refund_deadline,
               b.name AS business_name,
               b.house_number AS biz_house_number, b.address AS biz_address,
               b.address_notes AS biz_address_notes, b.khan AS biz_khan, b.sangkat AS biz_sangkat,
               v.name AS vendor_name, v.email AS vendor_email
          FROM orders o
          JOIN businesses b ON b.id = o.business_id
          JOIN vendors v ON v.id = b.user_id
         WHERE o.public_id = ? AND o.buyer_user_id = ?
    ');
    $stmt->execute([$publicId, $userId]);
    $o = $stmt->fetch();
    if (!$o) return null;

    $orderId    = (int)$o['id'];
    $status     = $o['status'];
    $reviewable = in_array($status, ['delivered', 'completed'], true);

    $stmt = $pdo->prepare('
        SELECT oi.id, oi.product_name, oi.product_name_km, oi.variant_label, oi.variant_label_km,
               oi.quantity, oi.price_at_purchase, r.id AS review_id
          FROM order_items oi
          LEFT JOIN reviews r ON r.order_item_id = oi.id
         WHERE oi.order_id = ?
         ORDER BY oi.id
    ');
    $stmt->execute([$orderId]);
    $items = [];
    foreach ($stmt->fetchAll() as $it) {
        $price = (float)$it['price_at_purchase'];
        $qty   = (int)$it['quantity'];
        $items[] = [
            'id'         => (int)$it['id'],
            'name'       => pick_lang($it['product_name'], $it['product_name_km'] ?? null),
            'variant'    => $it['variant_label'] !== null ? pick_lang($it['variant_label'], $it['variant_label_km'] ?? null) : null,
            'quantity'   => $qty,
            'price'      => round($price, 2),
            'line_total' => round($price * $qty, 2),
            'reviewed'   => $it['review_id'] !== null,
        ];
    }

    $subtotal = (float)$o['subtotal'];
    $discount = (float)$o['discount_amount'];

    // Stored links are re-checked on the way out (config/url.php). Shown only
    // while dispatched, as on the website.
    $tracking = safe_external_url($o['tracking_url']);

    $denied     = !empty($o['refund_rejected_at']);
    $windowOpen = (bool)$o['refund_window_open'];

    $returnAddress = null;
    if ($status === 'return_approved') {
        $parts = array_filter([
            trim(($o['biz_house_number'] ?? '') . ' ' . ($o['biz_address'] ?? '')),
            $o['biz_sangkat'] ?? '',
            $o['biz_khan'] ?? '',
            'Phnom Penh',
        ]);
        $addr = implode(', ', $parts);
        if ($addr !== '' && $addr !== 'Phnom Penh') {
            $returnAddress = ['address' => $addr, 'notes' => $o['biz_address_notes'] ?: null];
        }
    }

    $canRefund = $status === 'delivered' && !$denied && $windowOpen;

    $reasons = [];
    if ($canRefund) {
        $t = buyer_order_words();
        foreach (BUYER_REFUND_REASONS as $value => $key) {
            $reasons[] = ['value' => $value, 'label' => $t[$key], 'needs_photo' => refund_needs_photo($value)];
        }
    }

    return [
        'public_id'     => $o['public_id'],
        'ref'           => buyer_order_ref($orderId, $o['created_at']),
        'status'        => $status,
        'is_refund'     => in_array($status, BUYER_REFUND_STATUSES, true),
        'created_at'    => $o['created_at'],
        'business_name' => $o['business_name'],
        'vendor_name'   => $o['vendor_name'] ?: $o['vendor_email'],
        'tracking_url'  => $status === 'dispatched' ? $tracking : null,

        // How it was sent, from dispatch on. All null on older orders.
        'shipping_method' => $o['shipping_method'],
        'courier'         => $o['courier'],
        'tracking_number' => $o['tracking_number'],
        'dispatch_photo'  => order_photo_url($o['dispatch_photo'], true),

        'items'        => $items,
        'subtotal'     => round($subtotal, 2),
        'discount'     => round($discount, 2),
        'coupon_code'  => $o['coupon_code'],
        'delivery_fee' => round((float)$o['delivery_fee'], 2),
        // What the buyer paid, delivery included. A refund never returns
        // delivery, so the refund figure is its own field.
        'total'        => round($subtotal - $discount + (float)$o['delivery_fee'], 2),
        // After a disputed return, what teepsaa decided to send back.
        'refund_amount'=> refund_figure($o),

        'reviewable'   => $reviewable,

        'refund' => [
            'window_open' => $windowOpen,
            'deadline'    => $o['delivered_at'] ? $o['refund_deadline'] : null,
            'denied'      => $denied,
            'reason'      => $o['refund_reason'],
            'reasons'     => $reasons,
            // Every reason but a change of mind needs at least one photo, up
            // to photos_max; 'other' always needs one.
            'photos_max'  => REFUND_PHOTOS_MAX,
            'photos'      => array_map(fn($f) => order_photo_url($f, true), refund_photos_of($pdo, $orderId)['buyer_request'] ?? []),
            // The shop said the return came back used, empty or different. While
            // the status is 'return_received' the refund is held for teepsaa to
            // decide; with `denied` it was refused and the item is posted back.
            'disputed'    => !empty($o['return_disputed_at']),
            // What was actually sent back, once refunded. Below refund_amount's
            // full figure only for a part refund. Null before that.
            'paid'        => $o['refund_paid'] !== null ? round((float)$o['refund_paid'], 2) : null,
            'part'        => $o['refund_paid'] !== null && round((float)$o['refund_paid'], 2) < round($subtotal - $discount, 2),
        ],
        'return_address'      => $returnAddress,
        'return_tracking_url' => safe_external_url($o['return_tracking_url']),

        'actions' => [
            'confirm_delivery' => $status === 'dispatched',
            'request_refund'   => $canRefund,
            'send_return'      => $status === 'return_approved',
            // Put the items back in the cart — buy-again.php checks it again.
            'buy_again'        => $reviewable,
        ],
    ];
}

/** The words for the language this request asked for — the same file the website loads. */
function buyer_order_words(): array
{
    static $t = null;
    if ($t === null) {
        $lang = current_lang();
        $t = require __DIR__ . '/../lang/' . (in_array($lang, ['en', 'km'], true) ? $lang : 'en') . '.php';
    }
    return $t;
}
