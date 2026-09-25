<?php
// One product — product/index.php without the HTML. Photos, price and sale,
// stock, the variant picker's data, the seller, reviews, and — for a signed-in
// buyer — whether it is on their wishlist.
//
// Asked for by `id`, the full public id the cards carry, or by `token`, the
// short form in a website address (/product/silk-scarf-8f14e45f-ab3c/), which
// is what a banner link has. A token is only a prefix, so as on the website it
// counts only when it matches exactly one product.
//
// Not found is `not_found` (404) for an id that never existed and `gone` (410)
// for a listing that did and was taken down, the same split the website makes,
// so the app can say "no longer available" rather than "not found".

require __DIR__ . '/../../../config/api.php';
require __DIR__ . '/../../../config/api-shop.php';

api_require_method('GET');

$fullId = trim((string)($_GET['id'] ?? ''));
$token  = strtolower(trim((string)($_GET['token'] ?? '')));

if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $fullId)) {
    $idWhere = 'p.public_id = ?';
    $idParam = $fullId;
} elseif (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}$/', $token)) {
    $idWhere = 'p.public_id LIKE ?';
    $idParam = $token . '%';
} else {
    api_json(['error' => 'not_found'], 404);
}

$stmt = $pdo->prepare("
    SELECT p.*, b.public_id AS business_public_id, b.name AS business_name, b.name_km AS business_name_km
      FROM products p
      JOIN businesses b ON b.id = p.business_id
     WHERE $idWhere AND p.active = 1 AND p.archived = 0 AND b.approved = 1 AND b.suspended = 0
     LIMIT 2
");
$stmt->execute([$idParam]);
$matches = $stmt->fetchAll();

if (count($matches) !== 1) {
    $existed = $pdo->prepare('SELECT 1 FROM products p WHERE ' . $idWhere);
    $existed->execute([$idParam]);
    $gone = (bool)$existed->fetchColumn();
    api_json(['error' => $gone ? 'gone' : 'not_found'], $gone ? 410 : 404);
}

$product = $matches[0];
$id      = (int)$product['id'];
$onSale  = active_sale($product);
$price   = (float)$product['price'];

// ── Photos — the primary first, as the website orders them ───────────
$stmt = $pdo->prepare('SELECT filename FROM product_photos WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC, id ASC');
$stmt->execute([$id]);
$photos = array_map(fn($f) => [
    'small' => shop_img($f, 'w400'),     // thumbnails
    'large' => shop_img($f, 'w1200'),    // the main picture
    'full'  => shop_img($f, ''),         // the zoomed view — the original, as the website's lightbox uses
], $stmt->fetchAll(PDO::FETCH_COLUMN));

// ── Variants ─────────────────────────────────────────────────────────
// Two shapes, as on the website. With option types (Size × Colour) the app
// picks one value per type and looks the combination up in `variants` by
// `value_ids`. Without them, `variants` is a flat list picked by label.
//
// A variant's `price` is its own override or the product's price; the sale,
// when one runs, applies to whichever it is — sale_price_for(), as the cart
// and checkout work it out.
$stmt = $pdo->prepare('SELECT id, label, label_km, stock, price_override FROM product_variants WHERE product_id = ? ORDER BY sort_order ASC, id ASC');
$stmt->execute([$id]);
$variantRows = $stmt->fetchAll();

$valueIdsByVariant = [];
$options = [];
if ($variantRows) {
    $stmt = $pdo->prepare('SELECT id, name, name_km FROM product_option_types WHERE product_id = ? ORDER BY display_order, id');
    $stmt->execute([$id]);
    $valStmt = $pdo->prepare('SELECT id, label, label_km FROM product_option_values WHERE option_type_id = ? ORDER BY display_order, id');
    foreach ($stmt->fetchAll() as $ot) {
        $valStmt->execute([$ot['id']]);
        $options[] = [
            'id'     => (int)$ot['id'],
            'name'   => lang_field($ot, 'name'),
            'values' => array_map(fn($v) => ['id' => (int)$v['id'], 'label' => lang_field($v, 'label')], $valStmt->fetchAll()),
        ];
    }
    if ($options) {
        $stmt = $pdo->prepare('
            SELECT pvo.variant_id, pvo.option_value_id FROM product_variant_options pvo
              JOIN product_variants pv ON pv.id = pvo.variant_id
             WHERE pv.product_id = ?
             ORDER BY pvo.option_value_id');
        $stmt->execute([$id]);
        foreach ($stmt->fetchAll() as $row) $valueIdsByVariant[(int)$row['variant_id']][] = (int)$row['option_value_id'];
    }
}

$variants = array_map(function ($v) use ($product, $onSale, $price, $valueIdsByVariant) {
    $base = $v['price_override'] !== null ? (float)$v['price_override'] : $price;
    return [
        'id'         => (int)$v['id'],
        'label'      => lang_field($v, 'label'),
        'stock'      => max(0, (int)$v['stock']),
        'price'      => round($base, 2),
        'sale_price' => $onSale ? sale_price_for($base, $product) : null,
        'value_ids'  => $valueIdsByVariant[(int)$v['id']] ?? [],
    ];
}, $variantRows);

// ── Reviews ──────────────────────────────────────────────────────────
$stmt = $pdo->prepare('SELECT COALESCE(AVG(rating), 0) AS avg_rating, COUNT(*) AS review_count FROM reviews WHERE product_id = ?');
$stmt->execute([$id]);
$summary = $stmt->fetch();

// The newest fifty. The website prints every one; a phone scrolling past
// hundreds gains nothing, and the average above still counts them all.
$stmt = $pdo->prepare('
    SELECT r.rating, r.comment, r.created_at, b.name AS buyer_name
      FROM reviews r JOIN buyers b ON b.id = r.buyer_id
     WHERE r.product_id = ?
     ORDER BY r.created_at DESC LIMIT 50');
$stmt->execute([$id]);
$reviews = array_map(function ($r) {
    // "Sokha P." — first name and last initial, never the full name, as the
    // website shows it. mb_ because a Khmer initial is more than one byte.
    $parts = preg_split('/\s+/u', trim((string)$r['buyer_name'])) ?: [''];
    $name  = $parts[0] . (count($parts) > 1 ? ' ' . mb_strtoupper(mb_substr(end($parts), 0, 1)) . '.' : '');
    return [
        'rating'     => (int)$r['rating'],
        'comment'    => $r['comment'] !== null && trim($r['comment']) !== '' ? $r['comment'] : null,
        'name'       => $name,
        'created_at' => $r['created_at'],
    ];
}, $stmt->fetchAll());

// ── The signed-in part ───────────────────────────────────────────────
// null for someone just looking, so the app can tell "not saved" from "can't
// save until you sign in".
$buyer = api_optional_buyer($pdo);
$wishlisted = null;
if ($buyer) {
    $stmt = $pdo->prepare('SELECT 1 FROM wishlists WHERE buyer_user_id = ? AND product_id = ?');
    $stmt->execute([(int)$buyer['id'], $id]);
    $wishlisted = (bool)$stmt->fetchColumn();
}

$trail = [];
if (!empty($product['category_id'])) {
    $trail = array_map(fn($c) => ['id' => (int)$c['id'], 'name' => cat_name($c)],
        category_ancestors($pdo, (int)$product['category_id']));
}

shop_json([
    'product' => [
        'id'                  => $product['public_id'],
        'name'                => lang_field($product, 'name'),
        'description'         => lang_field($product, 'description') ?: null,
        'price'               => round($price, 2),
        'on_sale'             => $onSale,
        'sale_price'          => $onSale ? sale_price_for($price, $product) : null,
        'sale_percent'        => $onSale ? (int)$product['sale_percent'] : null,
        // With variants the product's own stock means nothing; each variant has its own.
        'stock'               => $variants ? null : max(0, (int)$product['stock']),
        // "Only 3 left" shows at or under this, when it is above zero.
        'low_stock_threshold' => (int)($product['low_stock_threshold'] ?? 0),
        'photos'              => $photos,
        'avg_rating'          => round((float)$summary['avg_rating'], 1),
        'review_count'        => (int)$summary['review_count'],
        'wishlisted'          => $wishlisted,
        'url'                 => 'https://teepsaa.com' . product_path($product),
    ],
    'business' => [
        'id'   => $product['business_public_id'],
        'name' => pick_lang($product['business_name'], $product['business_name_km'] ?? null),
    ],
    'trail'    => $trail,
    'options'  => $options,
    'variants' => $variants,
    'reviews'  => $reviews,
]);
