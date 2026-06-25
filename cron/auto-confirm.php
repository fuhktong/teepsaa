<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/app.php';

// Find dispatched orders with no buyer confirmation after 24 hours
$stmt = $pdo->query('
    SELECT o.id, o.subtotal, o.created_at,
           b.name AS business_name,
           bu.name AS buyer_name, bu.email AS buyer_email
    FROM orders o
    JOIN businesses b ON b.id = o.business_id
    JOIN buyers bu ON bu.id = o.buyer_user_id
    WHERE o.status = \'dispatched\'
      AND o.dispatched_at IS NOT NULL
      AND o.dispatched_at < NOW() - INTERVAL 24 HOUR
');
$orders = $stmt->fetchAll();

if (empty($orders)) {
    exit;
}

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
    $lines[] = "  {$oid}  {$o['business_name']}  \${$o['subtotal']}  (buyer: {$buyer})";
}

$plural = $count > 1 ? 's' : '';
$body   = "{$count} order{$plural} auto-confirmed after 24 hours with no buyer response:\n\n"
        . implode("\n", $lines)
        . "\n\nProcess vendor payout{$plural} at:\n"
        . SITE_URL . "/admin/orders.php?status=delivered\n";

mail(
    ADMIN_EMAIL,
    "[teepsaa] {$count} order{$plural} auto-confirmed — payout{$plural} ready",
    $body,
    'From: ' . FROM_EMAIL
);
