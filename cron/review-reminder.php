<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/app.php';
require __DIR__ . '/../config/notify.php';

// Orders delivered 24h+ ago with at least one unreviewed item, not yet reminded
$stmt = $pdo->query("
    SELECT DISTINCT o.id, o.public_id, o.created_at, o.delivered_at,
           bu.id AS buyer_id, bu.email, bu.name AS buyer_name
    FROM orders o
    JOIN buyers bu ON bu.id = o.buyer_user_id
    JOIN order_items oi ON oi.order_id = o.id
    LEFT JOIN reviews r ON r.order_item_id = oi.id
    WHERE o.status IN ('delivered', 'completed')
      AND o.delivered_at IS NOT NULL
      AND o.delivered_at <= NOW() - INTERVAL 24 HOUR
      AND o.review_reminder_sent_at IS NULL
      AND r.id IS NULL
");
$orders = $stmt->fetchAll();

foreach ($orders as $order) {
    $displayId = date('ymd', strtotime($order['created_at'])) . '-' . str_pad($order['id'], 4, '0', STR_PAD_LEFT);
    $link      = SITE_URL . '/dashboard-buyer/order.php?id=' . $order['public_id'];
    [$subj, $html] = render_email_template($pdo, 'review_reminder', [
        'name'    => $order['buyer_name'] ? htmlspecialchars($order['buyer_name']) : 'អ្នក',
        'order'   => $displayId,
        'cta_url' => $link,
    ]);
    if ($html !== '') send_email($order['email'], $subj, $html);
    notify($pdo, 'buyer', $order['buyer_id'], 'review_reminder',
        "How was order {$displayId}? Leave a review.", '/dashboard-buyer/order.php?id=' . $order['public_id'], ['ref' => $displayId]);
    $pdo->prepare('UPDATE orders SET review_reminder_sent_at = NOW() WHERE id = ?')->execute([$order['id']]);
}
