<?php
// Adding to the cart from a product — cart/add.php with JSON in and out.
// Every rule there is repeated:
//
//   - the product must be switched on;
//   - a product with variants needs one, and it must belong to that product
//     and be in stock; a product without variants must itself be in stock;
//   - the quantity is clamped to 1…stock, never refused for being too many;
//   - a line already in the cart is topped up, never past stock, and a line
//     already at stock is refused;
//   - the NULL-variant line is found by hand, as the website does, because
//     ON DUPLICATE KEY cannot match a NULL.
//
// One addition: the shop must be approved and not suspended, and the product
// not archived — the product page would not have shown it otherwise, and the
// cart would hide it anyway, so adding it would look like nothing happened.
//
// `product_id` is the public id the product page has. `variant_id` is the
// variant's own number from the same reply.

require __DIR__ . '/../../../config/api.php';
require __DIR__ . '/../../../config/buyer-cart.php';

api_require_method('POST');

$buyer  = api_require_buyer($pdo);
$userId = (int)$buyer['id'];

$body      = api_body();
$publicId  = trim((string)($body['product_id'] ?? ''));
$variantId = (int)($body['variant_id'] ?? 0) ?: null;

$stmt = $pdo->prepare('
    SELECT p.id, p.stock
      FROM products p
      JOIN businesses b ON b.id = p.business_id
     WHERE p.public_id = ? AND p.active = 1 AND p.archived = 0 AND b.approved = 1 AND b.suspended = 0
');
$stmt->execute([$publicId]);
$product = $publicId !== '' ? $stmt->fetch() : false;
if (!$product) api_json(['error' => 'unavailable'], 422);

$productId = (int)$product['id'];

if ($variantId !== null) {
    $stmt = $pdo->prepare('SELECT id, stock FROM product_variants WHERE id = ? AND product_id = ?');
    $stmt->execute([$variantId, $productId]);
    $variant = $stmt->fetch();
    if (!$variant || (int)$variant['stock'] < 1) api_json(['error' => 'variant_unavailable'], 422);
    $stockLimit = (int)$variant['stock'];
} else {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM product_variants WHERE product_id = ?');
    $stmt->execute([$productId]);
    if ($stmt->fetchColumn() > 0) api_json(['error' => 'variant_required'], 422);
    if ((int)$product['stock'] < 1) api_json(['error' => 'unavailable'], 422);
    $stockLimit = (int)$product['stock'];
}

$asked = max(1, (int)($body['quantity'] ?? 1));
$qty   = $asked;
if ($qty > $stockLimit) $qty = $stockLimit;

if ($variantId !== null) {
    $stmt = $pdo->prepare('SELECT id, quantity FROM cart_items WHERE buyer_user_id = ? AND product_id = ? AND variant_id = ?');
    $stmt->execute([$userId, $productId, $variantId]);
} else {
    $stmt = $pdo->prepare('SELECT id, quantity FROM cart_items WHERE buyer_user_id = ? AND product_id = ? AND variant_id IS NULL');
    $stmt->execute([$userId, $productId]);
}
$existing = $stmt->fetch();

if ($existing) {
    if ((int)$existing['quantity'] >= $stockLimit) {
        api_json(['error' => 'cart_max', 'in_cart' => (int)$existing['quantity']], 422);
    }
    $newQty = min((int)$existing['quantity'] + $qty, $stockLimit);
    $pdo->prepare('UPDATE cart_items SET quantity = ? WHERE id = ?')->execute([$newQty, $existing['id']]);
} else {
    $newQty = $qty;
    $pdo->prepare('INSERT INTO cart_items (buyer_user_id, product_id, variant_id, quantity) VALUES (?, ?, ?, ?)')
        ->execute([$userId, $productId, $variantId, $qty]);
}

api_json([
    'ok'      => true,
    // How many of this line are in the cart now, and whether that is fewer
    // than asked for because stock ran out first.
    'in_cart' => $newQty,
    'capped'  => $newQty < ($existing ? (int)$existing['quantity'] : 0) + $asked,
    'count'   => buyer_cart_count($pdo, $userId),
]);
