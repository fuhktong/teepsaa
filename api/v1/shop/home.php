<?php
// The buyer app's Home tab — index.php without the HTML. Public: anyone gets
// the banners, the category tiles and the rows; a signed-in buyer also gets
// "You might like", built from what they have ordered before.
//
// Every query is the website's own, row for row, so the app and the homepage
// show the same shop. Recently viewed is not here: it lives on the phone, and
// products-by-id.php fills it in.
//
// Rows come as a list of { key, products }. `key` is the heading's word in
// lang/*.php, so the app draws the title with t() and a row it does not know
// yet is still drawn. Empty rows are left out, as the homepage leaves them out.

require __DIR__ . '/../../../config/api.php';
require __DIR__ . '/../../../config/api-shop.php';

api_require_method('GET');

$buyer = api_optional_buyer($pdo);

$rv    = SHOP_RV_JOIN;
$cols  = 'p.id, p.public_id, p.name, p.name_km, p.price, p.sale_percent, p.sale_ends_at,
          b.name AS business_name, b.name_km AS business_name_km';
$photo = '(SELECT filename FROM product_photos WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS photo';
$live  = 'p.active = 1 AND p.archived = 0 AND p.stock > 0';
$shop  = 'JOIN businesses b ON p.business_id = b.id AND b.approved = 1 AND b.suspended = 0';
$stars = 'COALESCE(rv.avg_rating, 0) AS avg_rating, COALESCE(rv.review_count, 0) AS review_count';

$rows = [];

$rows['home_featured'] = $pdo->query(
    "SELECT $cols, $photo, $stars FROM products p $shop $rv
      WHERE $live ORDER BY RAND() LIMIT 8"
)->fetchAll();

$rows['home_best_sellers'] = $pdo->query(
    "SELECT $cols, $photo, $stars, SUM(oi.quantity) AS total_sold
       FROM order_items oi JOIN products p ON oi.product_id = p.id $shop $rv
      WHERE $live
      GROUP BY p.id, rv.avg_rating, rv.review_count
      ORDER BY total_sold DESC LIMIT 8"
)->fetchAll();

$rows['home_trending'] = $pdo->query(
    "SELECT $cols, $photo, $stars, SUM(oi.quantity) AS total_sold
       FROM order_items oi JOIN orders o ON oi.order_id = o.id
       JOIN products p ON oi.product_id = p.id $shop $rv
      WHERE $live
        AND o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        AND o.status NOT IN ('pending','cancelled')
      GROUP BY p.id, rv.avg_rating, rv.review_count
      ORDER BY total_sold DESC LIMIT 8"
)->fetchAll();

$rows['home_new_arrivals'] = $pdo->query(
    "SELECT $cols, $photo, $stars FROM products p $shop $rv
      WHERE $live ORDER BY p.created_at DESC LIMIT 8"
)->fetchAll();

$rows['home_top_rated'] = $pdo->query(
    "SELECT $cols, $photo, AVG(r.rating) AS avg_rating, COUNT(r.id) AS review_count
       FROM reviews r JOIN products p ON r.product_id = p.id AND $live $shop
      GROUP BY p.id
      ORDER BY avg_rating DESC, review_count DESC LIMIT 8"
)->fetchAll();

$rows['home_under_15'] = $pdo->query(
    "SELECT $cols, $photo, $stars FROM products p $shop $rv
      WHERE $live AND p.price < 15 ORDER BY RAND() LIMIT 8"
)->fetchAll();

// You might like — the three categories this buyer orders from most, minus
// anything they have already bought. index.php, with the token for the session.
if ($buyer) {
    $buyerId = (int)$buyer['id'];
    $stmt = $pdo->prepare(
        'SELECT p.category_id FROM order_items oi
           JOIN orders o ON oi.order_id = o.id
           JOIN products p ON oi.product_id = p.id
          WHERE o.buyer_user_id = ? AND p.category_id IS NOT NULL
          GROUP BY p.category_id ORDER BY COUNT(*) DESC LIMIT 3'
    );
    $stmt->execute([$buyerId]);
    $catIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if ($catIds) {
        $ph   = implode(',', array_fill(0, count($catIds), '?'));
        $stmt = $pdo->prepare(
            "SELECT $cols, $photo, $stars FROM products p $shop $rv
              WHERE $live AND p.category_id IN ($ph)
                AND p.id NOT IN (
                    SELECT DISTINCT oi2.product_id FROM order_items oi2
                      JOIN orders o2 ON oi2.order_id = o2.id
                     WHERE o2.buyer_user_id = ? AND oi2.product_id IS NOT NULL)
              ORDER BY RAND() LIMIT 8"
        );
        $stmt->execute(array_merge($catIds, [$buyerId]));
        $rows['home_you_might_like'] = $stmt->fetchAll();
    }
}

$rowList = [];
foreach ($rows as $key => $products) {
    if ($products) $rowList[] = ['key' => $key, 'products' => array_map('shop_card', $products)];
}

// ── Category tiles — the ten categories with the most live products ──
$tiles = $pdo->query(
    "SELECT c.id, c.name, c.name_km, COUNT(p.id) AS product_count,
            (SELECT pp.filename FROM products pr
               JOIN product_photos pp ON pp.product_id = pr.id AND pp.is_primary = 1
               JOIN businesses biz ON biz.id = pr.business_id AND biz.approved = 1 AND biz.suspended = 0
              WHERE pr.category_id = c.id AND pr.active = 1 AND pr.stock > 0
              LIMIT 1) AS sample_photo
       FROM categories c
       JOIN products p ON p.category_id = c.id AND p.active = 1 AND p.stock > 0
       JOIN businesses b ON b.id = p.business_id AND b.approved = 1 AND b.suspended = 0
      GROUP BY c.id, c.name
      ORDER BY product_count DESC LIMIT 10"
)->fetchAll();

$categories = array_map(fn($c) => [
    'id'    => (int)$c['id'],
    'name'  => cat_name($c),
    'photo' => shop_img($c['sample_photo']),
], $tiles);

// ── Banners ──────────────────────────────────────────────────────────
// `link` is the website address the banner points at, as the admin typed it.
// The app opens the matching screen when it recognises the address (a
// product, a shop, a category, a search) and does nothing otherwise.
try {
    $bannerRows = $pdo->query(
        'SELECT title, title_km, subtitle, subtitle_km, link_url, image_filename
           FROM banners WHERE active = 1 ORDER BY sort_order ASC, id ASC'
    )->fetchAll();
} catch (PDOException $e) {
    $bannerRows = [];    // the homepage tolerates a missing table, so this does too
}

$banners = array_map(fn($b) => [
    'title'    => lang_field($b, 'title') ?: null,
    'subtitle' => lang_field($b, 'subtitle') ?: null,
    'image'    => shop_img($b['image_filename'], 'w1200'),
    'link'     => $b['link_url'] ?: null,
], $bannerRows);

shop_json([
    'banners'    => $banners,
    'categories' => $categories,
    'rows'       => $rowList,
]);
