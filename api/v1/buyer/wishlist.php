<?php
// Saved items — wishlist/index.php without the HTML. Newest saved first. A
// product that has since been switched off, archived, sold out or whose shop
// is closed stays in the list, marked `available: false`, as the website
// greys it out rather than hiding it.
//
// Each item is a shop_card() — the same card every product list in the app
// draws — plus `available`.

require __DIR__ . '/../../../config/api.php';
require __DIR__ . '/../../../config/api-shop.php';

api_require_method('GET');

$buyer = api_require_buyer($pdo);

$stmt = $pdo->prepare('
    SELECT ' . SHOP_CARD_COLUMNS . ', p.stock, p.active, p.archived, b.approved, b.suspended
      FROM wishlists w
      JOIN products p ON p.id = w.product_id
      JOIN businesses b ON b.id = p.business_id
      LEFT JOIN product_photos pp ON pp.product_id = p.id AND pp.is_primary = 1
      ' . SHOP_RV_JOIN . '
     WHERE w.buyer_user_id = ?
     ORDER BY w.created_at DESC
');
$stmt->execute([(int)$buyer['id']]);

$items = array_map(fn($p) => shop_card($p) + [
    'available' => $p['active'] && !$p['archived'] && $p['approved'] && !$p['suspended'] && (int)$p['stock'] >= 1,
], $stmt->fetchAll());

shop_json(['items' => $items]);
