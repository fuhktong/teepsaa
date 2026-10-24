<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/app.php';
require __DIR__ . '/../config/notify.php';
require __DIR__ . '/../config/refund-evidence.php';
require __DIR__ . '/../config/strikes.php';

$ship = require __DIR__ . '/../config/shipping.php';

// Dispatched orders the buyer never confirmed. Grab is same-day, so 24 hours.
// A courier parcel waits by distance (config/shipping.php). An order counts as
// courier when the vendor dispatched it that way, or when it was past Grab's
// reach at checkout and went some other way (a bus company, say).
$courier = "(o.shipping_method = 'courier' OR o.delivery_distance_km > " . (float)$ship['grab_max_km'] . ")";
$days = 'CASE';
foreach ($ship['courier_confirm_days'] as $tier) {
    $days .= ' WHEN o.delivery_distance_km <= ' . (float)$tier['max_km'] . ' THEN ' . (int)$tier['days'];
}
$days .= ' ELSE ' . (int)$ship['courier_confirm_days_far'] . ' END';
$hours = "CASE WHEN $courier THEN ($days) * 24 ELSE 24 END";

$stmt = $pdo->query("
    SELECT o.id, o.subtotal, o.created_at, $hours AS waited_hours,
           b.name AS business_name,
           bu.name AS buyer_name, bu.email AS buyer_email
    FROM orders o
    JOIN businesses b ON b.id = o.business_id
    JOIN buyers bu ON bu.id = o.buyer_user_id
    WHERE o.status = 'dispatched'
      AND o.dispatched_at IS NOT NULL
      AND o.dispatched_at < NOW() - INTERVAL ($hours) HOUR
");
$orders = $stmt->fetchAll();

if ($orders) {
    // Mark them all delivered
    $ids          = array_column($orders, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $pdo->prepare("UPDATE orders SET status = 'delivered', delivered_at = NOW() WHERE id IN ($placeholders)")->execute($ids);

    // Build email body
    $count = count($orders);
    $lines = [];
    foreach ($orders as $o) {
        $oid     = date('ymd', strtotime($o['created_at'])) . '-' . str_pad($o['id'], 4, '0', STR_PAD_LEFT);
        $buyer   = $o['buyer_name'] ?: $o['buyer_email'];
        $wait    = (int)$o['waited_hours'] > 24 ? ((int)$o['waited_hours'] / 24) . ' days' : '24 hours';
        $lines[] = "  {$oid}  {$o['business_name']}  \${$o['subtotal']}  (buyer: {$buyer}, after {$wait})";
    }

    $plural = $count > 1 ? 's' : '';
    $body   = "{$count} order{$plural} auto-confirmed with no buyer response:\n\n"
            . implode("\n", $lines)
            . "\n\nProcess vendor payout{$plural} at:\n"
            . SITE_URL . "/admin/orders.php?status=delivered\n";

    mail(
        ADMIN_EMAIL,
        "[teepsaa] {$count} order{$plural} auto-confirmed — payout{$plural} ready",
        $body,
        'From: ' . FROM_EMAIL
    );
}

// Returns the shop never marked received in time: the courier days for the
// distance plus RETURN_OVERDUE_GRACE_DAYS (config/refund-evidence.php). Held for
// an admin, who refunds the buyer or starts the clock again. The shop can
// still press Received meanwhile.
$late = $pdo->query("
    SELECT o.id, o.public_id, o.subtotal, o.created_at, b.user_id AS vendor_id, b.name AS business_name
      FROM orders o
      JOIN businesses b ON b.id = o.business_id
     WHERE o.status = 'return_dispatched'
       AND o.return_bounced_at IS NULL AND o.return_overdue_at IS NULL
       AND o.return_dispatched_at IS NOT NULL
       AND o.return_dispatched_at < NOW() - INTERVAL " . return_overdue_days_sql() . " DAY
")->fetchAll();

if ($late) {
    $flag  = $pdo->prepare("UPDATE orders SET return_overdue_at = NOW() WHERE id = ? AND status = 'return_dispatched' AND return_overdue_at IS NULL");
    $lines = [];
    foreach ($late as $o) {
        $flag->execute([$o['id']]);
        if (!$flag->rowCount()) continue;
        $oid = order_display_id((int)$o['id'], $o['created_at']);
        notify($pdo, 'vendor', (int)$o['vendor_id'], 'return_overdue',
            'The return for order #' . $oid . ' has not been marked received. teepsaa is checking.',
            '/orders-vendor/refund.php?id=' . $o['public_id'], ['ref' => $oid]);
        $lines[] = "  {$oid}  {$o['business_name']}  \${$o['subtotal']}";
    }
    if ($lines) {
        $n = count($lines);
        mail(
            ADMIN_EMAIL,
            "[teepsaa] {$n} return" . ($n > 1 ? 's' : '') . " overdue — decide the refund",
            "Never marked received by the shop:\n\n" . implode("\n", $lines)
                . "\n\nRefund the buyer or keep waiting at:\n" . SITE_URL . "/admin/refunds.php\n",
            'From: ' . FROM_EMAIL
        );
    }
}

// Shops whose 30-day suspension from a 3rd strike has run out go back up,
// and the vendor is emailed (config/strikes.php).
strike_lift_suspensions($pdo);
