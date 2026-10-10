<?php
// Follow or unfollow a shop. The buyer then hears about its new products, from
// cron/buyer-alerts.php.
//
//   { business_id, following: bool }   set it — never a flip, so a double tap
//                                      or a retry cannot undo itself
//
// `business_id` is the public id. Unfollowing always works. Following needs a
// shop that would be shown. A new follow starts from the shop's newest product
// today, so following never sends a notification for what is already there.

require __DIR__ . '/../../../config/api.php';

api_require_method('POST');

$buyer  = api_require_buyer($pdo);
$userId = (int)$buyer['id'];

$body     = api_body();
$publicId = trim((string)($body['business_id'] ?? ''));
if ($publicId === '') api_json(['error' => 'missing_id'], 400);
if (!array_key_exists('following', $body)) api_json(['error' => 'missing_following'], 400);
$want = (bool)$body['following'];

$stmt = $pdo->prepare('SELECT id, (approved = 1 AND suspended = 0) AS visible FROM businesses WHERE public_id = ?');
$stmt->execute([$publicId]);
$business = $stmt->fetch();
if (!$business) api_json(['error' => 'not_found'], 404);
$businessId = (int)$business['id'];

if ($want) {
    if (!$business['visible']) api_json(['error' => 'unavailable'], 422);
    // INSERT IGNORE: the unique key makes a racing second tap harmless, and an
    // existing follow keeps its place in the product stream.
    $pdo->prepare('
        INSERT IGNORE INTO business_follows (buyer_user_id, business_id, seen_product_id)
        SELECT ?, ?, COALESCE(MAX(id), 0) FROM products WHERE business_id = ?
    ')->execute([$userId, $businessId, $businessId]);
} else {
    $pdo->prepare('DELETE FROM business_follows WHERE buyer_user_id = ? AND business_id = ?')
        ->execute([$userId, $businessId]);
}

api_json(['ok' => true, 'following' => $want]);
