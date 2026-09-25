<?php
// What review/index.php checks before it shows the form.
//
//   ?item=<order_item id>   that one item, if it can be reviewed
//   (no item)               every item still waiting for a review
//
// An item can be reviewed when it is in one of this buyer's orders, the order
// is delivered or completed, and it has no review yet. The refusals are named
// (not_found, not_reviewable, already_reviewed) so the app can say which.

require __DIR__ . '/../../../config/api.php';
require __DIR__ . '/../../../config/buyer-orders.php';

api_require_method('GET');

$buyer  = api_require_buyer($pdo);
$userId = (int)$buyer['id'];

$shape = function (array $r): array {
    return [
        'id'       => (int)$r['id'],
        'name'     => pick_lang($r['product_name'], $r['product_name_km'] ?? null),
        'variant'  => $r['variant_label'] !== null ? pick_lang($r['variant_label'], $r['variant_label_km'] ?? null) : null,
        'order_id' => $r['order_public_id'],
        'ref'      => buyer_order_ref((int)$r['order_id'], $r['created_at']),
    ];
};

$select = '
    SELECT oi.id, oi.product_name, oi.product_name_km, oi.variant_label, oi.variant_label_km,
           o.id AS order_id, o.public_id AS order_public_id, o.status, o.created_at,
           r.id AS review_id
      FROM order_items oi
      JOIN orders o ON o.id = oi.order_id
      LEFT JOIN reviews r ON r.order_item_id = oi.id
';

if (isset($_GET['item'])) {
    $itemId = (int)$_GET['item'];
    $stmt = $pdo->prepare($select . ' WHERE oi.id = ? AND o.buyer_user_id = ?');
    $stmt->execute([$itemId, $userId]);
    $r = $stmt->fetch();
    if (!$r) api_json(['error' => 'not_found'], 404);
    if (!in_array($r['status'], ['delivered', 'completed'], true)) api_json(['error' => 'not_reviewable'], 409);
    if ($r['review_id'] !== null) api_json(['error' => 'already_reviewed'], 409);
    api_json(['item' => $shape($r)]);
}

$stmt = $pdo->prepare($select . "
     WHERE o.buyer_user_id = ? AND o.status IN ('delivered', 'completed') AND r.id IS NULL
     ORDER BY o.created_at DESC, oi.id
     LIMIT 100
");
$stmt->execute([$userId]);
api_json(['items' => array_map($shape, $stmt->fetchAll())]);
