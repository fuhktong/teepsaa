<?php
// Leave a review — review/submit.php with JSON in and out.
//
//   { order_item_id, rating: 1-5, comment }
//
// The same checks as the page that shows the form, made again because nothing
// stands in front of this: the item is this buyer's, its order is delivered or
// completed, and it has no review yet. The comment is cut at 1000 characters,
// as on the website. The table's order_item_id is checked here rather than
// trusted to the insert, so a double tap is told already_reviewed.

require __DIR__ . '/../../../config/api.php';

api_require_method('POST');

$buyer  = api_require_buyer($pdo);
$userId = (int)$buyer['id'];

$body    = api_body();
$itemId  = (int)($body['order_item_id'] ?? 0);
$rating  = (int)($body['rating'] ?? 0);
$comment = trim((string)($body['comment'] ?? ''));

if (!$itemId) api_json(['error' => 'missing_id'], 400);
if ($rating < 1 || $rating > 5) api_json(['error' => 'bad_rating'], 400);
if (mb_strlen($comment) > 1000) $comment = mb_substr($comment, 0, 1000);

$stmt = $pdo->prepare('
    SELECT oi.id, oi.product_id, o.status, o.business_id, o.public_id AS order_public_id
      FROM order_items oi
      JOIN orders o ON o.id = oi.order_id
     WHERE oi.id = ? AND o.buyer_user_id = ?
');
$stmt->execute([$itemId, $userId]);
$item = $stmt->fetch();

if (!$item) api_json(['error' => 'not_found'], 404);
if (!in_array($item['status'], ['delivered', 'completed'], true)) api_json(['error' => 'not_reviewable'], 409);

$check = $pdo->prepare('SELECT id FROM reviews WHERE order_item_id = ?');
$check->execute([$itemId]);
if ($check->fetch()) api_json(['error' => 'already_reviewed'], 409);

$pdo->prepare('
    INSERT INTO reviews (order_item_id, buyer_id, product_id, business_id, rating, comment)
    VALUES (?, ?, ?, ?, ?, ?)
')->execute([
    $itemId,
    $userId,
    $item['product_id'] ?: null,
    $item['business_id'],
    $rating,
    $comment !== '' ? $comment : null,
]);

api_json(['ok' => true, 'order_id' => $item['order_public_id']]);
