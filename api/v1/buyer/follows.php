<?php
// The shops a buyer follows, most recently followed first. A shop that has
// since closed or been suspended drops out of the list, as it does from
// everywhere else a buyer looks; the follow itself is kept, so it comes back
// if the shop does.

require __DIR__ . '/../../../config/api.php';
require __DIR__ . '/../../../config/api-shop.php';

api_require_method('GET');

$buyer = api_require_buyer($pdo);

$stmt = $pdo->prepare('
    SELECT b.public_id, b.name, b.name_km, b.banner,
           (SELECT COUNT(*) FROM products p WHERE p.business_id = b.id AND p.active = 1 AND p.archived = 0) AS product_count
      FROM business_follows f
      JOIN businesses b ON b.id = f.business_id AND b.approved = 1 AND b.suspended = 0
     WHERE f.buyer_user_id = ?
     ORDER BY f.created_at DESC, f.id DESC
');
$stmt->execute([(int)$buyer['id']]);

$shops = array_map(fn($b) => [
    'id'            => $b['public_id'],
    'name'          => lang_field($b, 'name'),
    'banner'        => shop_img($b['banner'] ?? null),
    'product_count' => (int)$b['product_count'],
], $stmt->fetchAll());

shop_json(['shops' => $shops]);
