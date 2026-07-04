<?php
session_start();
require __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

$q          = trim($_GET['q'] ?? '');
$offset     = max(0, (int)($_GET['offset'] ?? 0));
$sort       = $_GET['sort'] ?? 'newest';
$minPrice   = trim($_GET['min_price'] ?? '');
$maxPrice   = trim($_GET['max_price'] ?? '');
$categoryId = (int)($_GET['category'] ?? 0);
$rawRating        = (int)($_GET['min_rating'] ?? 0);
$minRating        = in_array($rawRating, [2, 3, 4]) ? (float)$rawRating : 0.0;
$selectedValueIds = array_values(array_unique(array_filter(array_map('intval', (array)($_GET['variant_values'] ?? [])), fn($v) => $v > 0)));
$limit            = 20;

$validSorts = ['newest', 'price_asc', 'price_desc', 'rating', 'popular'];
if (!in_array($sort, $validSorts, true)) $sort = 'newest';
if ($minPrice !== '' && !is_numeric($minPrice)) $minPrice = '';
if ($maxPrice !== '' && !is_numeric($maxPrice)) $maxPrice = '';

// ── WHERE ────────────────────────────────────────────────────────────
$where  = 'p.active = 1 AND p.archived = 0 AND b.approved = 1';
$params = [];
if ($q !== '') {
    $where   .= ' AND (p.name LIKE ? OR p.description LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($minPrice !== '') {
    $where   .= ' AND p.price >= ?';
    $params[] = (float)$minPrice;
}
if ($maxPrice !== '') {
    $where   .= ' AND p.price <= ?';
    $params[] = (float)$maxPrice;
}
if ($categoryId > 0) {
    $where   .= ' AND p.category_id = ?';
    $params[] = $categoryId;
}
if ($minRating > 0) {
    $where   .= ' AND COALESCE(rv.avg_rating, 0) >= ?';
    $params[] = $minRating;
}
if (!empty($selectedValueIds)) {
    $vph    = implode(',', array_fill(0, count($selectedValueIds), '?'));
    $vtStmt = $pdo->prepare("SELECT id AS value_id, option_type_id FROM product_option_values WHERE id IN ($vph)");
    $vtStmt->execute($selectedValueIds);
    $variantTypeGroups = [];
    foreach ($vtStmt->fetchAll() as $row) {
        $variantTypeGroups[(int)$row['option_type_id']][] = (int)$row['value_id'];
    }
    foreach ($variantTypeGroups as $valueIds) {
        $innerPh = implode(',', array_fill(0, count($valueIds), '?'));
        $where  .= " AND EXISTS (
            SELECT 1 FROM product_variants pv2
            JOIN product_variant_options pvo2 ON pvo2.variant_id = pv2.id
            WHERE pv2.product_id = p.id AND pvo2.option_value_id IN ($innerPh)
        )";
        foreach ($valueIds as $vid) { $params[] = $vid; }
    }
}

// ── JOINs ────────────────────────────────────────────────────────────
$rvJoin    = 'LEFT JOIN (SELECT product_id, AVG(rating) AS avg_rating, COUNT(*) AS review_count
               FROM reviews GROUP BY product_id) rv ON rv.product_id = p.id';
$salesJoin = $sort === 'popular'
    ? 'LEFT JOIN (SELECT product_id, SUM(quantity) AS total_sold FROM order_items GROUP BY product_id) sales ON sales.product_id = p.id'
    : '';

$orderBy = match($sort) {
    'price_asc'  => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'rating'     => 'COALESCE(rv.avg_rating, 0) DESC, COALESCE(rv.review_count, 0) DESC',
    'popular'    => 'COALESCE(sales.total_sold, 0) DESC',
    default      => 'p.id DESC',
};

$fetchParams = array_merge($params, [$limit + 1, $offset]);

$stmt = $pdo->prepare("
    SELECT p.id, p.name, p.name_km, p.description, p.description_km, p.price, p.sale_price, p.sale_ends_at,
           pp.filename AS photo,
           b.id AS business_id, b.name AS business_name, b.name_km AS business_name_km,
           COALESCE(rv.avg_rating, 0) AS avg_rating,
           COALESCE(rv.review_count, 0) AS review_count
    FROM products p
    JOIN businesses b ON b.id = p.business_id
    LEFT JOIN product_photos pp ON pp.product_id = p.id AND pp.is_primary = 1
    $rvJoin
    $salesJoin
    WHERE $where
    ORDER BY $orderBy
    LIMIT ? OFFSET ?");
$stmt->execute($fetchParams);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$hasMore  = count($rows) > $limit;
$products = array_slice($rows, 0, $limit);

foreach ($products as &$p) {
    // Resolve display language server-side so the client just uses name/description
    $p['name']          = lang_field($p, 'name');
    $p['description']   = lang_field($p, 'description');
    $p['business_name'] = pick_lang($p['business_name'], $p['business_name_km'] ?? null);
    unset($p['name_km'], $p['description_km'], $p['business_name_km']);
    $p['id']           = (int)$p['id'];
    $p['business_id']  = (int)$p['business_id'];
    $p['price']        = (float)$p['price'];
    $p['sale_price']   = $p['sale_price'] !== null ? (float)$p['sale_price'] : null;
    $p['avg_rating']   = (float)$p['avg_rating'];
    $p['review_count'] = (int)$p['review_count'];
}
unset($p);

echo json_encode(['products' => $products, 'has_more' => $hasMore]);
