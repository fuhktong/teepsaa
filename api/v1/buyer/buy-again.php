<?php
// "Buy again" on an order — every item it held goes back in the cart, in the
// same option and number, through buyer_cart_put(), the rules adding from a
// product page follows. Nothing is bought here; the buyer still checks out.
//
// { order_id } is the public id. Only the buyer's own order, and only once it
// arrived (delivered or completed) — the same orders that offer a review, and
// what buyer_order_detail() sends as actions.buy_again.
//
// Items that cannot go back are named in `skipped`, never silently dropped:
// the product was deleted or switched off, its shop closed, the option it was
// bought in was deleted or sold out, or it is out of stock. A line already in
// the cart at full stock counts as added — it is there. `capped` lists items
// that went in, but fewer than last time, because stock is lower.

require __DIR__ . '/../../../config/api.php';
require __DIR__ . '/../../../config/buyer-cart.php';

api_require_method('POST');

$buyer  = api_require_buyer($pdo);
$userId = (int)$buyer['id'];

$body     = api_body();
$publicId = trim((string)($body['order_id'] ?? ''));
if ($publicId === '') api_json(['error' => 'missing_id'], 400);

$stmt = $pdo->prepare('SELECT id, status FROM orders WHERE public_id = ? AND buyer_user_id = ?');
$stmt->execute([$publicId, $userId]);
$order = $stmt->fetch();
if (!$order) api_json(['error' => 'not_found'], 404);
if (!in_array($order['status'], ['delivered', 'completed'], true)) {
    api_json(['error' => 'wrong_status', 'status' => $order['status']], 409);
}

$stmt = $pdo->prepare('
    SELECT product_id, variant_id, variant_label, product_name, product_name_km, quantity
      FROM order_items
     WHERE order_id = ?
     ORDER BY id
');
$stmt->execute([(int)$order['id']]);

$added   = 0;
$skipped = [];
$capped  = [];
foreach ($stmt->fetchAll() as $item) {
    $name = pick_lang($item['product_name'], $item['product_name_km'] ?? null);

    // Deleted product, or deleted option: variant_id was set to NULL but the
    // label survives, and adding it without one would pick nothing.
    if ($item['product_id'] === null || ($item['variant_id'] === null && $item['variant_label'] !== null)) {
        $skipped[] = $name;
        continue;
    }

    $put = buyer_cart_put($pdo, $userId, (int)$item['product_id'],
        $item['variant_id'] !== null ? (int)$item['variant_id'] : null, (int)$item['quantity']);

    if (isset($put['error']) && $put['error'] !== 'cart_max') {
        $skipped[] = $name;
        continue;
    }
    $added++;
    if (!empty($put['capped'])) $capped[] = $name;
}

api_json([
    'ok'      => true,
    'added'   => $added,
    'skipped' => $skipped,
    'capped'  => $capped,
    'count'   => buyer_cart_count($pdo, $userId),
]);
