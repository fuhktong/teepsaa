<?php
// One shop — business/index.php without the HTML. The banner, the name and
// description, the shop's rating, its featured product and every other live
// product in the vendor's own storefront order.
//
// Asked for by `id` (the full public id) or `token` (the short form in a
// website address), with the same one-match rule and the same not_found /
// gone split as product.php.
//
// All products in one reply, as the website sends them — a shop is tens of
// products, not thousands. The ceiling is only there so that stays true.

require __DIR__ . '/../../../config/api.php';
require __DIR__ . '/../../../config/api-shop.php';

api_require_method('GET');

$fullId = trim((string)($_GET['id'] ?? ''));
$token  = strtolower(trim((string)($_GET['token'] ?? '')));

if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $fullId)) {
    $idWhere = 'public_id = ?';
    $idParam = $fullId;
} elseif (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}$/', $token)) {
    $idWhere = 'public_id LIKE ?';
    $idParam = $token . '%';
} else {
    api_json(['error' => 'not_found'], 404);
}

$stmt = $pdo->prepare("SELECT * FROM businesses WHERE $idWhere AND approved = 1 AND suspended = 0 LIMIT 2");
$stmt->execute([$idParam]);
$matches = $stmt->fetchAll();

if (count($matches) !== 1) {
    $existed = $pdo->prepare("SELECT 1 FROM businesses WHERE $idWhere");
    $existed->execute([$idParam]);
    $gone = (bool)$existed->fetchColumn();
    api_json(['error' => $gone ? 'gone' : 'not_found'], $gone ? 410 : 404);
}

$business = $matches[0];
$id       = (int)$business['id'];

// The same row shape shop_card() takes. business_name is filled in below
// rather than joined, since every product here belongs to this one shop.
$stmt = $pdo->prepare('
    SELECT p.id, p.public_id, p.name, p.name_km, p.price, p.sale_percent, p.sale_ends_at, p.is_featured,
           pp.filename AS photo,
           COALESCE(rv.avg_rating, 0) AS avg_rating, COALESCE(rv.review_count, 0) AS review_count
      FROM products p
      LEFT JOIN product_photos pp ON pp.product_id = p.id AND pp.is_primary = 1
      ' . SHOP_RV_JOIN . '
     WHERE p.business_id = ? AND p.active = 1 AND p.archived = 0
     ORDER BY (p.storefront_order IS NULL), p.storefront_order ASC, p.name ASC
     LIMIT 300');
$stmt->execute([$id]);

$featured = null;
$products = [];
foreach ($stmt->fetchAll() as $row) {
    $row['business_name']    = $business['name'];
    $row['business_name_km'] = $business['name_km'];
    $card = shop_card($row);
    // One featured product at most (products/feature.php enforces it). It gets
    // its own place at the top and is left out of the grid, as on the website.
    if ($featured === null && (int)$row['is_featured'] === 1) {
        $featured = $card;
    } else {
        $products[] = $card;
    }
}

$stmt = $pdo->prepare('SELECT COALESCE(AVG(rating), 0) AS avg_rating, COUNT(*) AS review_count FROM reviews WHERE business_id = ?');
$stmt->execute([$id]);
$rating = $stmt->fetch();

shop_json([
    'business' => [
        'id'           => $business['public_id'],
        'name'         => lang_field($business, 'name'),
        'description'  => lang_field($business, 'description') ?: null,
        'banner'       => shop_img($business['banner'] ?? null, 'w1200'),
        'city'         => $business['city'] ?: null,
        // Every shop that answers here is approved — that is what the website's
        // "✓ Verified" badge means. Sent so the app does not have to know that.
        'verified'     => true,
        'avg_rating'   => round((float)$rating['avg_rating'], 1),
        'review_count' => (int)$rating['review_count'],
        'url'          => 'https://teepsaa.com' . business_path($business),
    ],
    'featured' => $featured,
    'products' => $products,
]);
