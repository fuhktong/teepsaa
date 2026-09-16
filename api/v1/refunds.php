<?php
// The Refunds list — the third pill on the Orders tab, matching
// orders-vendor/index.php?tab=refunds.
//
// A refund is not a status on the order lifecycle; it runs alongside it, and a
// rejected one puts the order back to 'delivered' while recording the rejection
// in its own column. So an order belongs in this list when either its status is
// one of the refund statuses OR refund_rejected_at is set — the website uses
// exactly that pair of conditions and this endpoint must not drift from it.
//
// Closed businesses are included, flagged, like the history tab: a refund is
// money owed to a buyer and does not stop mattering because the shop shut. The
// vendor cannot act on one, which is what needs_action and can_confirm are for.

require __DIR__ . '/../../config/api.php';

api_require_method('GET');

$vendor = api_require_vendor($pdo);
$userId = (int)$vendor['id'];

$limit  = (int)($_GET['limit'] ?? 20);
$limit  = max(1, min(50, $limit));
$offset = max(0, (int)($_GET['offset'] ?? 0));

$where = "b.user_id = ?
          AND (o.status IN ('refund_requested', 'return_approved', 'return_dispatched',
                            'return_received', 'refunded', 'refund_rejected')
               OR o.refund_rejected_at IS NOT NULL)";

$countStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT o.id)
      FROM orders o
      JOIN businesses b ON b.id = o.business_id
     WHERE $where
");
$countStmt->execute([$userId]);
$total = (int)$countStmt->fetchColumn();

// What the vendor still has to do something about, for the badge on the pill.
// Only 'return_dispatched' waits on the vendor — every other state waits on the
// buyer or on teepsaa — and only at a shop that is still open.
$actionStmt = $pdo->prepare("
    SELECT COUNT(*)
      FROM orders o
      JOIN businesses b ON b.id = o.business_id
     WHERE b.user_id = ? AND b.deleted_at IS NULL AND o.status = 'return_dispatched'
");
$actionStmt->execute([$userId]);
$needsAction = (int)$actionStmt->fetchColumn();

// LIMIT and OFFSET are inlined because MySQL will not take them as strings from
// an emulated prepare. The casts above leave nothing in them to inject.
$stmt = $pdo->prepare("
    SELECT o.id, o.public_id, o.subtotal, o.delivery_fee, o.discount_amount,
           o.status, o.created_at, o.refund_rejected_at,
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

$refunds = [];
foreach ($stmt->fetchAll() as $o) {
    $subtotal = (float)$o['subtotal'];
    $discount = (float)$o['discount_amount'];
    $closed   = $o['business_closed'] !== null;

    $refunds[] = [
        'public_id'   => $o['public_id'],
        'ref'         => date('ymd', strtotime($o['created_at'])) . '-' . str_pad((string)$o['id'], 4, '0', STR_PAD_LEFT),
        'status'      => $o['status'],
        // What the screen shows. A rejection is recorded in its own column
        // rather than in status, so the two can disagree and this is the one
        // to trust for anything the vendor reads.
        'refund_state'    => $o['refund_rejected_at'] ? 'refund_rejected' : $o['status'],
        'items'           => $o['items'],
        'buyer_name'      => $o['buyer_name'] ?: $o['buyer_email'],
        'business_name'   => $o['business_name'],
        'business_closed' => $closed,
        'subtotal'        => round($subtotal, 2),
        'discount'        => round($discount, 2),
        // Always 0 — delivery is paid to the Grab driver, never to teepsaa, so
        // there is nothing of it to refund. Sent for completeness only.
        'delivery_fee'    => round((float)$o['delivery_fee'], 2),
        'refund_amount'   => round($subtotal - $discount, 2),
        'needs_action'    => $o['status'] === 'return_dispatched' && !$closed,
        'created_at'      => $o['created_at'],
    ];
}

api_json([
    'refunds'      => $refunds,
    'total'        => $total,
    'needs_action' => $needsAction,
    'has_more'     => ($offset + count($refunds)) < $total,
]);
