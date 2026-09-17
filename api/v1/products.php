<?php
// The Products tab's list, paged. Matches the two product tabs on
// products/index.php:
//
//   tab=products   everything not archived — the working catalogue.
//   tab=archive    archived products, which are always inactive. The website
//                  offers exactly two things here, unarchive and delete.
//
// Coupons are the third tab on the website and get their own endpoint.
//
// The empty list is not one state but four, and the website goes to real
// trouble to tell them apart: no business submitted, one waiting for approval,
// one rejected, one suspended. A vendor waiting on approval must not be invited
// to submit a second business, and a suspended one must not be told nothing at
// all. `business_state` carries that distinction so the app can say the same
// things without asking a second endpoint.
//
// Photos are the first images any v1 endpoint has returned, and they are sent
// as ABSOLUTE urls on purpose — see the note further down.

require __DIR__ . '/../../config/api.php';
require __DIR__ . '/../../config/upload.php';
// active_sale() and sale_price_for(). Its format_price() reads $_SESSION, but
// nothing here calls it — the app formats money itself.
require __DIR__ . '/../../config/currency.php';

api_require_method('GET');

$vendor = api_require_vendor($pdo);
$userId = (int)$vendor['id'];

$tab = ($_GET['tab'] ?? 'products') === 'archive' ? 'archive' : 'products';

// Clamped, not trusted — the same ceiling orders.php uses. Without it a caller
// could ask for a million rows and turn one request into an outage.
$limit  = (int)($_GET['limit'] ?? 20);
$limit  = max(1, min(50, $limit));
$offset = max(0, (int)($_GET['offset'] ?? 0));

// Sellable businesses only: approved and not suspended. This is the same list
// products/index.php builds, and it is what every ownership check below is
// based on, so a suspended vendor can neither see nor change anything.
$bizStmt = $pdo->prepare('
    SELECT id FROM businesses
     WHERE user_id = ? AND approved = 1 AND suspended = 0
     ORDER BY name ASC
');
$bizStmt->execute([$userId]);
$bizIds = $bizStmt->fetchAll(PDO::FETCH_COLUMN);

// Why the newest business and not all of them: this only exists to explain an
// empty list, and the website reads the most recent one for the same reason.
$latestStmt = $pdo->prepare('
    SELECT approved, suspended FROM businesses
     WHERE user_id = ? AND deleted_at IS NULL
     ORDER BY created_at DESC LIMIT 1
');
$latestStmt->execute([$userId]);
$latest = $latestStmt->fetch();

if (!empty($bizIds)) {
    $businessState = 'ok';
} elseif (!$latest) {
    $businessState = 'none';                  // never submitted one
} elseif ((int)$latest['suspended'] === 1) {
    $businessState = 'suspended';
} elseif ((int)$latest['approved'] === -1) {
    $businessState = 'rejected';
} else {
    $businessState = 'pending';               // approved = 0, still waiting
}

// No sellable business means no products, and no query worth running.
if (empty($bizIds)) {
    api_json([
        'tab'            => $tab,
        'business_state' => $businessState,
        'products'       => [],
        'total'          => 0,
        'has_more'       => false,
    ]);
}

$ph     = implode(',', array_fill(0, count($bizIds), '?'));
$bizIds = array_map('intval', $bizIds);

// archived is a tinyint on products with no migration file behind it — it
// exists in production and both website list queries rely on it.
$archived = $tab === 'archive' ? 1 : 0;

$countStmt = $pdo->prepare("
    SELECT COUNT(*) FROM products
     WHERE business_id IN ($ph) AND archived = $archived
");
$countStmt->execute($bizIds);
$total = (int)$countStmt->fetchColumn();

// LIMIT and OFFSET are inlined rather than bound because MySQL will not take
// them as strings from an emulated prepare. They are integers by the casts
// above, so there is nothing left in them to inject.
$stmt = $pdo->prepare("
    SELECT p.id, p.public_id, p.name, p.name_km, p.price, p.stock, p.active,
           p.archived, p.sale_percent, p.sale_ends_at, p.low_stock_threshold,
           c.name AS category_name,
           pp.filename AS photo,
           (SELECT COUNT(*) FROM product_variants pv WHERE pv.product_id = p.id) AS variant_count,
           COALESCE(rv.avg_rating, 0)   AS avg_rating,
           COALESCE(rv.review_count, 0) AS review_count
      FROM products p
      LEFT JOIN categories c ON c.id = p.category_id
      LEFT JOIN product_photos pp ON pp.product_id = p.id AND pp.is_primary = 1
      LEFT JOIN (
            SELECT product_id, AVG(rating) AS avg_rating, COUNT(*) AS review_count
              FROM reviews GROUP BY product_id
      ) rv ON rv.product_id = p.id
     WHERE p.business_id IN ($ph) AND p.archived = $archived
     ORDER BY p.name ASC, p.id ASC
     LIMIT $limit OFFSET $offset
");
$stmt->execute($bizIds);

$products = [];
foreach ($stmt->fetchAll() as $p) {
    $price   = (float)$p['price'];
    $onSale  = active_sale($p);
    $variants = (int)$p['variant_count'];
    // A product with variants keeps its stock in the variant rows, and the
    // website's own column shows the variant count instead of p.stock for
    // exactly that reason. Sent as both so the app never has to guess which
    // number means something.
    $stock   = (int)$p['stock'];

    $products[] = [
        // The numeric id is what every action takes, matching the website's
        // hidden product_id field. public_id is for the edit screen, which is
        // addressed by it.
        'id'            => (int)$p['id'],
        'public_id'     => $p['public_id'],
        // Both languages are sent even though the app is English-only until
        // Chapter 8. Adding a field later is safe; this saves changing the
        // endpoint when the language switch lands.
        'name'          => $p['name'],
        'name_km'       => $p['name_km'] ?: null,
        'category_name' => $p['category_name'] ?: null,
        // Absolute, not the '/uploads/...' the website uses. The app runs from
        // https://localhost, so a root-relative src would point the phone at
        // itself and the image would silently never load — nothing in the
        // console, just an empty box.
        'photo'         => $p['photo'] ? 'https://teepsaa.com' . image_variant($p['photo']) : null,
        'price'         => round($price, 2),
        'on_sale'       => $onSale,
        // Worked out here, not in the app. Money maths in two places is money
        // maths that will eventually disagree with itself.
        'sale_price'    => $onSale ? sale_price_for($price, $p) : null,
        'sale_percent'  => $onSale ? (int)$p['sale_percent'] : null,
        'sale_ends_at'  => $onSale ? $p['sale_ends_at'] : null,
        'stock'         => $stock,
        'variant_count' => $variants,
        'low_stock'     => $variants === 0 && $stock <= (int)$p['low_stock_threshold'],
        'active'        => (int)$p['active'] === 1,
        'archived'      => (int)$p['archived'] === 1,
        'avg_rating'    => round((float)$p['avg_rating'], 1),
        'review_count'  => (int)$p['review_count'],
    ];
}

api_json([
    'tab'            => $tab,
    'business_state' => $businessState,
    'products'       => $products,
    'total'          => $total,
    'has_more'       => ($offset + count($products)) < $total,
]);
