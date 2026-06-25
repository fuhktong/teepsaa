<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/app.php';
require __DIR__ . '/../config/notify.php';

// Buyers with cart items added 24h+ ago who haven't been notified yet
$stmt = $pdo->query("
    SELECT bu.id, bu.email, bu.name,
           MIN(ci.added_at) AS oldest_item
    FROM cart_items ci
    JOIN buyers bu ON bu.id = ci.buyer_user_id
    WHERE ci.added_at <= NOW() - INTERVAL 24 HOUR
      AND bu.abandoned_cart_notified_at IS NULL
    GROUP BY bu.id, bu.email, bu.name
");
$buyers = $stmt->fetchAll();

foreach ($buyers as $buyer) {
    // Skip if they placed an order after the oldest cart item was added
    $check = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE buyer_user_id = ? AND created_at >= ?');
    $check->execute([$buyer['id'], $buyer['oldest_item']]);
    if ((int)$check->fetchColumn() > 0) {
        $pdo->prepare('UPDATE buyers SET abandoned_cart_notified_at = NOW() WHERE id = ?')->execute([$buyer['id']]);
        continue;
    }

    $itemStmt = $pdo->prepare('
        SELECT p.name, ci.quantity
        FROM cart_items ci
        JOIN products p ON p.id = ci.product_id
        WHERE ci.buyer_user_id = ?
        ORDER BY ci.added_at ASC
        LIMIT 5
    ');
    $itemStmt->execute([$buyer['id']]);
    $items = $itemStmt->fetchAll();

    $rows = '';
    foreach ($items as $item) {
        $rows .= '<li>' . htmlspecialchars($item['name']) . ($item['quantity'] > 1 ? ' &times; ' . (int)$item['quantity'] : '') . '</li>';
    }

    $greeting = $buyer['name'] ? htmlspecialchars($buyer['name']) : 'there';
    $html = notification_email_html(
        'You left something in your cart',
        "Hi {$greeting}, you have item" . (count($items) > 1 ? 's' : '') . " waiting in your teepsaa cart:<ul style=\"margin:12px 0;padding-left:20px\">{$rows}</ul>Your cart is saved — head back whenever you're ready.",
        'Go to my cart',
        SITE_URL . '/cart/'
    );

    send_email($buyer['email'], 'Your teepsaa cart is waiting', $html);
    notify($pdo, 'buyer', $buyer['id'], 'abandoned_cart', 'You have items waiting in your cart.', '/cart/');
    $pdo->prepare('UPDATE buyers SET abandoned_cart_notified_at = NOW() WHERE id = ?')->execute([$buyer['id']]);
}
