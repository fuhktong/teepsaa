<?php
// The whole category tree, flat, in the language asked for. The app builds the
// filter list and the "browse within" links from it; the table is about fifty
// rows, so one small reply beats a call per level.
//
// `parent_id` is null at the top. `has_products` says whether anything live is
// filed in this category or under it, so the app can leave out empty branches
// the way the website's search dropdown does.

require __DIR__ . '/../../../config/api.php';
require __DIR__ . '/../../../config/api-shop.php';

api_require_method('GET');

// Categories that directly hold a live product. A parent's own count is
// always zero — vendors file against leaves — so "has products" is worked out
// for the whole branch below.
$live = array_map('intval', $pdo->query(
    'SELECT DISTINCT p.category_id FROM products p
       JOIN businesses b ON b.id = p.business_id AND b.approved = 1 AND b.suspended = 0
      WHERE p.active = 1 AND p.archived = 0 AND p.category_id IS NOT NULL'
)->fetchAll(PDO::FETCH_COLUMN));
$live = array_flip($live);

$out = [];
foreach (category_all($pdo) as $id => $c) {
    $hasProducts = false;
    foreach (category_branch_ids($pdo, (int)$id) as $branchId) {
        if (isset($live[$branchId])) { $hasProducts = true; break; }
    }
    $out[] = [
        'id'           => (int)$id,
        'parent_id'    => $c['parent_id'] !== null ? (int)$c['parent_id'] : null,
        'name'         => cat_name($c),
        'has_products' => $hasProducts,
    ];
}

shop_json(['categories' => $out]);
