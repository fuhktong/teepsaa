<?php
// One category — category/index.php without the HTML. The category, its intro
// text, the trail above it, the categories under it, and the first page of its
// products. Later pages, and any sort or filter, come from search.php with
// category=<id>, which applies the same whole-branch rule.
//
// Asked for by `id`, or by `slug` — the last part of a website address like
// /category/dresses/, which is what a banner link carries.

require __DIR__ . '/../../../config/api.php';
require __DIR__ . '/../../../config/api-shop.php';

api_require_method('GET');

$all  = category_all($pdo);
$id   = (int)($_GET['id'] ?? 0);
$slug = trim((string)($_GET['slug'] ?? ''));

$category = $id > 0 ? ($all[$id] ?? null) : ($slug !== '' ? category_by_slug($pdo, $slug) : null);
if (!$category) api_json(['error' => 'not_found'], 404);

$id = (int)$category['id'];

$intros = require __DIR__ . '/../../../category/intros.php';
$lang   = current_lang();
$slug   = category_slugs($pdo)[$id] ?? '';
$intro  = trim((string)($intros[$slug][$lang] ?? ($intros[$slug]['en'] ?? '')));

$children = [];
foreach ($all as $cid => $row) {
    if ((int)($row['parent_id'] ?? 0) === $id) $children[] = ['id' => (int)$cid, 'name' => cat_name($row)];
}

$trail = array_map(fn($c) => ['id' => (int)$c['id'], 'name' => cat_name($c)], category_ancestors($pdo, $id));

[$offset, $limit] = shop_paging(20);
$result = shop_search($pdo, ['category' => $id, 'sort' => $_GET['sort'] ?? 'newest'], $offset, $limit);

shop_json([
    'category' => ['id' => $id, 'name' => cat_name($category), 'intro' => $intro !== '' ? $intro : null],
    'trail'    => $trail,
    'children' => $children,
] + $result);
