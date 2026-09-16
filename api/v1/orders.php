<?php
// The Orders tab's list, paged.
//
// Two lists live here, matching the two tabs on orders-vendor/index.php:
//
//   tab=orders   the work queue — pending, paid, dispatched. Live businesses
//                only: a closed business has no outstanding obligations, so its
//                orders must never sit in someone's to-do list.
//   tab=history  the sales record — delivered and completed. Deliberately
//                includes closed businesses, flagged so the app can say so.
//
// Refunds are a third list on the website with their own statuses and their own
// screen; they get their own endpoint rather than a third tab bolted on here.
//
// Paged because a busy vendor can have hundreds of orders and a phone on mobile
// data should not download all of them to show the first twenty.

require __DIR__ . '/../../config/api.php';

api_require_method('GET');

$vendor = api_require_vendor($pdo);
$userId = (int)$vendor['id'];

$tab = ($_GET['tab'] ?? 'orders') === 'history' ? 'history' : 'orders';

// Clamped, not trusted. Without the ceiling a caller could ask for a million
// rows and turn one request into an outage.
$limit  = (int)($_GET['limit'] ?? 20);
$limit  = max(1, min(50, $limit));
$offset = max(0, (int)($_GET['offset'] ?? 0));

if ($tab === 'orders') {
    $where = "b.user_id = ? AND b.deleted_at IS NULL
              AND o.status IN ('pending', 'paid', 'dispatched')";
} else {
    $where = "b.user_id = ? AND o.status IN ('delivered', 'completed')";
}

// The count is of orders, but the listing joins order_items and groups by order,
// so counting rows there would count items. COUNT(DISTINCT o.id) is the honest
// number and it is what drives "has_more".
$countStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT o.id)
      FROM orders o
      JOIN businesses b ON b.id = o.business_id
     WHERE $where
");
$countStmt->execute([$userId]);
$total = (int)$countStmt->fetchColumn();

// LIMIT and OFFSET are inlined rather than bound because MySQL will not take
// them as strings from an emulated prepare. They are integers by the casts
// above, so there is nothing left in them to inject.
$stmt = $pdo->prepare("
    SELECT o.id, o.public_id, o.subtotal, o.delivery_fee, o.discount_amount,
           o.status, o.created_at,
           b.name AS business_name,
           b.deleted_at AS business_closed,
           u.name AS buyer_name, u.email AS buyer_email,
           GROUP_CONCAT(
               CONCAT(oi.product_name,
                      IFNULL(CONCAT(' (', oi.variant_label, ')'), ''),
                      ' x', oi.quantity)
               ORDER BY oi.id SEPARATOR ', '
           ) AS items
      FROM orders o
      JOIN businesses b ON b.id = o.business_id
      JOIN buyers u ON u.id = o.buyer_user_id
      JOIN order_items oi ON oi.order_id = o.id
     WHERE $where
     GROUP BY o.id
     ORDER BY o.created_at DESC, o.id DESC
     LIMIT $limit OFFSET $offset
");
$stmt->execute([$userId]);

$orders = [];
foreach ($stmt->fetchAll() as $o) {
    $subtotal = (float)$o['subtotal'];
    $discount = (float)$o['discount_amount'];
    $delivery = (float)$o['delivery_fee'];

    $orders[] = [
        'public_id'   => $o['public_id'],
        'ref'         => date('ymd', strtotime($o['created_at'])) . '-' . str_pad((string)$o['id'], 4, '0', STR_PAD_LEFT),
        'status'      => $o['status'],
        'items'       => $o['items'],
        // A buyer who never set a name still has to be identifiable, and the
        // website falls back to the email for exactly that reason.
        'buyer_name'  => $o['buyer_name'] ?: $o['buyer_email'],
        'business_name'   => $o['business_name'],
        'business_closed' => $o['business_closed'] !== null,
        'subtotal'    => round($subtotal, 2),
        'discount'    => round($discount, 2),
        'delivery_fee'=> round($delivery, 2),
        // Worked out here, not in the app. Money maths in two places is money
        // maths that will eventually disagree with itself.
        'total'       => round($subtotal - $discount, 2),
        'created_at'  => $o['created_at'],
    ];
}

api_json([
    'tab'      => $tab,
    'orders'   => $orders,
    'total'    => $total,
    'has_more' => ($offset + count($orders)) < $total,
]);
