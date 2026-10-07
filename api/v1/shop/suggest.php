<?php
// GET /api/v1/shop/suggest.php?q=dre&lang=en
//   →  {"categories":[{"id":12,"name":"Dresses"}], "products":[{"id":"…","name":"White Dress"}]}
//
// What the app lists under the search box while the buyer is still typing:
// up to three categories and six products whose name holds what was typed.
// Tapping one opens it directly; pressing Search still runs the full search.
//
// Matches both the English and the Khmer name, whichever language the app is
// in — a buyer reading Khmer may well type an English word, and the reverse.
// Names starting with the text come first, the way typing is usually meant.
//
// Asked on nearly every keystroke (the app waits for a pause first), so it is
// kept small: names only, no photos or prices, and the same visibility rule as
// shop_search() so it never suggests something a search would not find. No
// rate limit, as with the other shop endpoints; the query length is capped.

require __DIR__ . '/../../../config/api.php';
require __DIR__ . '/../../../config/api-shop.php';

api_require_method('GET');

$q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 50);
if ($q === '') shop_json(['categories' => [], 'products' => []]);

$like   = '%' . addcslashes($q, '%_\\') . '%';
$prefix = addcslashes($q, '%_\\') . '%';

// Categories: only ones with something live in them or under them, as the
// category list does, so a suggestion never opens onto an empty page.
$live = array_flip(array_map('intval', $pdo->query(
    'SELECT DISTINCT p.category_id FROM products p
       JOIN businesses b ON b.id = p.business_id AND b.approved = 1 AND b.suspended = 0
      WHERE p.active = 1 AND p.archived = 0 AND p.category_id IS NOT NULL'
)->fetchAll(PDO::FETCH_COLUMN)));

$stmt = $pdo->prepare(
    'SELECT id, name, name_km FROM categories
      WHERE name LIKE ? OR name_km LIKE ?
      ORDER BY (name LIKE ? OR name_km LIKE ?) DESC, name'
);
$stmt->execute([$like, $like, $prefix, $prefix]);
$categories = [];
foreach ($stmt->fetchAll() as $c) {
    foreach (category_branch_ids($pdo, (int)$c['id']) as $branchId) {
        if (isset($live[$branchId])) {
            $categories[] = ['id' => (int)$c['id'], 'name' => cat_name($c)];
            break;
        }
    }
    if (count($categories) === 3) break;
}

$stmt = $pdo->prepare(
    'SELECT p.public_id, p.name, p.name_km FROM products p
       JOIN businesses b ON b.id = p.business_id
      WHERE p.active = 1 AND p.archived = 0 AND b.approved = 1 AND b.suspended = 0
        AND (p.name LIKE ? OR p.name_km LIKE ?)
      ORDER BY (p.name LIKE ? OR p.name_km LIKE ?) DESC, p.name
      LIMIT 6'
);
$stmt->execute([$like, $like, $prefix, $prefix]);
$products = array_map(fn($p) => [
    'id'   => $p['public_id'],
    'name' => lang_field($p, 'name'),
], $stmt->fetchAll());

shop_json(['categories' => $categories, 'products' => $products]);
