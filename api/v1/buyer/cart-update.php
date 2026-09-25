<?php
// Changing a cart line — cart/update.php with JSON in and out.
//
//   { cart_item_id, quantity }      set the quantity, capped at stock
//   { cart_item_id, action: 'remove' }
//
// As on the website: quantity 0 removes the line, a quantity over stock is cut
// to stock, a line whose stock has gone to 0 is removed, and a line that is not
// this buyer's is left alone without a word — the reply is simply the cart as
// it is. Every reply is the whole cart, the same shape as cart.php.

require __DIR__ . '/../../../config/api.php';
require __DIR__ . '/../../../config/api-shop.php';
require __DIR__ . '/../../../config/buyer-cart.php';

api_require_method('POST');

$buyer  = api_require_buyer($pdo);
$userId = (int)$buyer['id'];

$body       = api_body();
$cartItemId = (int)($body['cart_item_id'] ?? 0);
$action     = (string)($body['action'] ?? 'update');
$quantity   = max(0, (int)($body['quantity'] ?? 0));

if ($action === 'remove' || $quantity === 0) {
    $pdo->prepare('DELETE FROM cart_items WHERE id = ? AND buyer_user_id = ?')->execute([$cartItemId, $userId]);
} else {
    $stmt = $pdo->prepare('
        SELECT ci.id, COALESCE(pv.stock, p.stock) AS effective_stock
          FROM cart_items ci
          JOIN products p ON p.id = ci.product_id
          LEFT JOIN product_variants pv ON pv.id = ci.variant_id
         WHERE ci.id = ? AND ci.buyer_user_id = ?
    ');
    $stmt->execute([$cartItemId, $userId]);
    $item = $stmt->fetch();

    if ($item) {
        $quantity = min($quantity, max(0, (int)$item['effective_stock']));
        if ($quantity === 0) {
            $pdo->prepare('DELETE FROM cart_items WHERE id = ? AND buyer_user_id = ?')->execute([$cartItemId, $userId]);
        } else {
            $pdo->prepare('UPDATE cart_items SET quantity = ? WHERE id = ? AND buyer_user_id = ?')->execute([$quantity, $cartItemId, $userId]);
        }
    }
}

api_json(buyer_cart($pdo, $userId));
