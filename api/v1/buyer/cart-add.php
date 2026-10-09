<?php
// Adding to the cart from a product — cart/add.php with JSON in and out. The
// rules live in buyer_cart_put() (config/buyer-cart.php), shared with
// buy-again.php.
//
// One addition to the website: the shop must be approved and not suspended,
// and the product not archived — the product page would not have shown it
// otherwise, and the cart would hide it anyway, so adding it would look like
// nothing happened.
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

$stmt = $pdo->prepare('SELECT id FROM products WHERE public_id = ?');
$stmt->execute([$publicId]);
$productId = $publicId !== '' ? (int)$stmt->fetchColumn() : 0;
if (!$productId) api_json(['error' => 'unavailable'], 422);

$put = buyer_cart_put($pdo, $userId, $productId, $variantId, (int)($body['quantity'] ?? 1));
if (isset($put['error'])) api_json($put, 422);

api_json([
    'ok'      => true,
    // How many of this line are in the cart now, and whether that is fewer
    // than asked for because stock ran out first.
    'in_cart' => $put['in_cart'],
    'capped'  => $put['capped'],
    'count'   => buyer_cart_count($pdo, $userId),
]);
