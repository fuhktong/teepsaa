<?php
// Cards for a list of products the app already knows by id — Recently viewed,
// which the app keeps on the phone. api/recently-viewed/ without the session.
//
// `ids` is comma-separated public ids. Up to 20, in the order given; a product
// taken down since is simply left out, so the app can drop it from its list.

require __DIR__ . '/../../../config/api.php';
require __DIR__ . '/../../../config/api-shop.php';

api_require_method('GET');

$uuidRe = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
$ids = array_values(array_unique(array_filter(
    array_map('trim', explode(',', (string)($_GET['ids'] ?? ''))),
    fn($id) => preg_match($uuidRe, $id)
)));
$ids = array_slice($ids, 0, 20);

if (!$ids) shop_json(['products' => []]);

$stmt = $pdo->prepare('SELECT ' . SHOP_CARD_COLUMNS . '
    FROM products p
    JOIN businesses b ON b.id = p.business_id
    LEFT JOIN product_photos pp ON pp.product_id = p.id AND pp.is_primary = 1
    ' . SHOP_RV_JOIN . '
    WHERE p.public_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')
      AND p.active = 1 AND p.archived = 0 AND b.approved = 1 AND b.suspended = 0');
$stmt->execute($ids);

$byId = [];
foreach ($stmt->fetchAll() as $row) $byId[strtolower($row['public_id'])] = shop_card($row);

$ordered = [];
foreach ($ids as $id) {
    if (isset($byId[strtolower($id)])) $ordered[] = $byId[strtolower($id)];
}

shop_json(['products' => $ordered]);
