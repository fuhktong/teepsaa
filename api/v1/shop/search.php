<?php
// Search, for the app's Search tab — search/index.php and api/search/ as one
// endpoint. Every filter the website takes, taken the same way:
//
//   q, sort (newest | price_asc | price_desc | rating | popular), min_price,
//   max_price, min_rating (2 | 3 | 4), category, variant_values[], offset, limit
//
// The query itself is shop_search() in config/api-shop.php, shared with
// category.php. `shops` is the website's "Shops" strip — businesses tagged
// with a category the query names — and is only worked out on the first page.

require __DIR__ . '/../../../config/api.php';
require __DIR__ . '/../../../config/api-shop.php';

api_require_method('GET');

[$offset, $limit] = shop_paging(20);

$result = shop_search($pdo, $_GET, $offset, $limit);

$q          = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100);
$categoryId = (int)($_GET['category'] ?? 0);
$shops      = [];

if ($offset === 0 && $q !== '') {
    // As search/index.php: the chosen category's name, or every category whose
    // name the query matches, then the shops tagged with any of those names.
    if ($categoryId > 0) {
        $stmt = $pdo->prepare('SELECT name FROM categories WHERE id = ?');
        $stmt->execute([$categoryId]);
        $names = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $qLike = '%' . addcslashes($q, '%_\\') . '%';
        $stmt  = $pdo->prepare('SELECT name FROM categories WHERE name LIKE ? OR name_km LIKE ?');
        $stmt->execute([$qLike, $qLike]);
        $names = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    if ($names) {
        $conds = implode(' OR ', array_fill(0, count($names), "FIND_IN_SET(?, REPLACE(b.category, ', ', ','))"));
        $stmt  = $pdo->prepare(
            "SELECT b.public_id, b.name, b.name_km, b.banner FROM businesses b
              WHERE b.approved = 1 AND b.suspended = 0 AND b.deleted_at IS NULL AND ($conds)
              ORDER BY b.name LIMIT 12"
        );
        $stmt->execute($names);
        foreach ($stmt->fetchAll() as $b) {
            $shops[] = [
                'id'     => $b['public_id'],
                'name'   => lang_field($b, 'name'),
                'banner' => shop_img($b['banner']),
            ];
        }
    }
}

shop_json($result + ['shops' => $shops]);
