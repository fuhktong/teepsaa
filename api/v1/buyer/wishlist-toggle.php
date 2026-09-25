<?php
// Save or unsave a product — api/wishlist/toggle.php with JSON in and out.
//
//   { product_id }                    flip it, as the website's heart does
//   { product_id, wishlisted: bool }  set it — what the app sends, so a double
//                                     tap or a retry on a bad signal cannot
//                                     flip it back by accident
//
// `product_id` is the public id. Removing always works, whatever state the
// product is in now. Adding needs a product the shop would show — the website
// only ever offers the heart on one, and here nothing stands in front of the
// request.

require __DIR__ . '/../../../config/api.php';

api_require_method('POST');

$buyer  = api_require_buyer($pdo);
$userId = (int)$buyer['id'];

$body     = api_body();
$publicId = trim((string)($body['product_id'] ?? ''));
if ($publicId === '') api_json(['error' => 'missing_product_id'], 400);

$stmt = $pdo->prepare('
    SELECT p.id, (p.active = 1 AND p.archived = 0 AND b.approved = 1 AND b.suspended = 0) AS visible
      FROM products p JOIN businesses b ON b.id = p.business_id
     WHERE p.public_id = ?
');
$stmt->execute([$publicId]);
$product = $stmt->fetch();
if (!$product) api_json(['error' => 'not_found'], 404);
$productId = (int)$product['id'];

$check = $pdo->prepare('SELECT 1 FROM wishlists WHERE buyer_user_id = ? AND product_id = ?');
$check->execute([$userId, $productId]);
$saved = (bool)$check->fetchColumn();

$want = array_key_exists('wishlisted', $body) ? (bool)$body['wishlisted'] : !$saved;

if ($want && !$saved) {
    if (!$product['visible']) api_json(['error' => 'unavailable'], 422);
    // The table's unique key makes a second insert from a racing tap harmless.
    $pdo->prepare('INSERT IGNORE INTO wishlists (buyer_user_id, product_id) VALUES (?, ?)')
        ->execute([$userId, $productId]);
} elseif (!$want && $saved) {
    $pdo->prepare('DELETE FROM wishlists WHERE buyer_user_id = ? AND product_id = ?')
        ->execute([$userId, $productId]);
}

api_json(['ok' => true, 'wishlisted' => $want]);
