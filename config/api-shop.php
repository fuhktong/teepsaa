<?php
// Shared by the buyer app's public shop endpoints, api/v1/shop/*. Required
// after config/api.php, which brings db.php — and with it currency.php,
// upload.php, i18n.php and slug.php. Requiring any of those again here would
// be a redeclare and a 500.
//
// Three things every shop reply has in common, kept here so the seven
// endpoints cannot drift apart:
//
//   Language. The app names it with ?lang=en|km on every call, and
//   current_lang() already reads $_GET['lang'] first — so lang_field(),
//   pick_lang() and cat_name() resolve exactly as they do on the website, and
//   the app never picks between name and name_km itself. There is no session
//   here, so nothing is remembered between calls; a call without ?lang= gets
//   the website's default, Khmer.
//
//   Images. Absolute addresses. The app runs from https://localhost, so the
//   website's '/uploads/…' would point the phone at itself and the picture
//   would silently never load.
//
//   Prices. Sent in USD with the sale already worked out, plus khr_rate so the
//   app can show riel. Money maths in one place only.
//
// No rate limit on these, deliberately: they answer exactly what the public
// website pages answer to anyone, and those pages have none either. What they
// do have is a ceiling on every size — page length, offset, query length, ids
// per call — so no single request can be made expensive.

require_once __DIR__ . '/category.php';

const SHOP_IMG_BASE = 'https://teepsaa.com';

/** A photo's absolute address, or null. $size is 'w400' or 'w1200'; '' is the original. */
function shop_img(?string $filename, string $size = 'w400'): ?string {
    if ($filename === null || trim($filename) === '') return null;
    $path = $size === '' ? '/uploads/' . basename(trim($filename)) : image_variant($filename, $size);
    return $path === '' ? null : SHOP_IMG_BASE . $path;
}

/**
 * One product as a card in a list — home rows, search, category, a shop,
 * recently viewed. The row needs public_id, name(_km), price, sale_percent,
 * sale_ends_at, photo, business_name(_km); avg_rating and review_count are
 * optional.
 *
 * `id` is the public_id, never the table id: it is what product.php takes,
 * and it is what the website already puts in every public address.
 */
function shop_card(array $p): array {
    $price  = (float)$p['price'];
    $onSale = active_sale($p);
    return [
        'id'            => (string)$p['public_id'],
        'name'          => lang_field($p, 'name'),
        'photo'         => shop_img($p['photo'] ?? null),
        'price'         => round($price, 2),
        'on_sale'       => $onSale,
        'sale_price'    => $onSale ? sale_price_for($price, $p) : null,
        'sale_percent'  => $onSale ? (int)$p['sale_percent'] : null,
        'business_name' => pick_lang($p['business_name'] ?? '', $p['business_name_km'] ?? null),
        'avg_rating'    => round((float)($p['avg_rating'] ?? 0), 1),
        'review_count'  => (int)($p['review_count'] ?? 0),
    ];
}

/** The columns shop_card() needs, for a query with products p, businesses b, photos pp, reviews rv. */
const SHOP_CARD_COLUMNS = 'p.id, p.public_id, p.name, p.name_km, p.price, p.sale_percent, p.sale_ends_at,
           pp.filename AS photo, b.name AS business_name, b.name_km AS business_name_km,
           COALESCE(rv.avg_rating, 0) AS avg_rating, COALESCE(rv.review_count, 0) AS review_count';

const SHOP_RV_JOIN = 'LEFT JOIN (SELECT product_id, AVG(rating) AS avg_rating, COUNT(*) AS review_count
                                   FROM reviews GROUP BY product_id) rv ON rv.product_id = p.id';

/**
 * Search, filters and paging — search/index.php and api/search/ in one, for
 * search.php and category.php both.
 *
 * Kept in step with the website on purpose: the same visibility rule, the same
 * LIKE escaping, the same sorts, the same rating steps and the same variant
 * filter. The one difference is `category`, which here means the category and
 * everything under it (the category page's rule) rather than one exact id.
 * The website's search only ever offers leaf categories, where the two are the
 * same thing, so nothing the website can ask for answers differently.
 *
 * @return array{products: array, total: int, has_more: bool}
 */
function shop_search(PDO $pdo, array $f, int $offset, int $limit): array {
    $q          = mb_substr(trim((string)($f['q'] ?? '')), 0, 100);
    $sort       = (string)($f['sort'] ?? 'newest');
    $minPrice   = trim((string)($f['min_price'] ?? ''));
    $maxPrice   = trim((string)($f['max_price'] ?? ''));
    $categoryId = (int)($f['category'] ?? 0);
    $rawRating  = (int)($f['min_rating'] ?? 0);
    $minRating  = in_array($rawRating, [2, 3, 4], true) ? (float)$rawRating : 0.0;
    $valueIds   = array_slice(array_values(array_unique(array_filter(
        array_map('intval', (array)($f['variant_values'] ?? [])), fn($v) => $v > 0
    ))), 0, 20);

    if (!in_array($sort, ['newest', 'price_asc', 'price_desc', 'rating', 'popular'], true)) $sort = 'newest';
    if ($minPrice !== '' && !is_numeric($minPrice)) $minPrice = '';
    if ($maxPrice !== '' && !is_numeric($maxPrice)) $maxPrice = '';

    $where  = 'p.active = 1 AND p.archived = 0 AND b.approved = 1 AND b.suspended = 0';
    $params = [];

    if ($q !== '') {
        // Escaped as the website does it — "%" alone must not return everything.
        $qLike    = '%' . addcslashes($q, '%_\\') . '%';
        $where   .= ' AND (p.name LIKE ? OR p.description LIKE ?)';
        $params[] = $qLike;
        $params[] = $qLike;
    }
    if ($minPrice !== '') { $where .= ' AND p.price >= ?'; $params[] = (float)$minPrice; }
    if ($maxPrice !== '') { $where .= ' AND p.price <= ?'; $params[] = (float)$maxPrice; }
    if ($categoryId > 0) {
        $branch   = category_branch_ids($pdo, $categoryId);
        $where   .= ' AND p.category_id IN (' . implode(',', array_fill(0, count($branch), '?')) . ')';
        $params   = array_merge($params, $branch);
    }
    if ($minRating > 0) {
        $where   .= ' AND COALESCE(rv.avg_rating, 0) >= ?';
        $params[] = $minRating;
    }
    if ($valueIds) {
        $vtStmt = $pdo->prepare('SELECT id, option_type_id FROM product_option_values WHERE id IN ('
            . implode(',', array_fill(0, count($valueIds), '?')) . ')');
        $vtStmt->execute($valueIds);
        $groups = [];
        foreach ($vtStmt->fetchAll() as $row) $groups[(int)$row['option_type_id']][] = (int)$row['id'];
        // Within one option type the values are OR (red or blue); across types, AND.
        foreach ($groups as $ids) {
            $where .= ' AND EXISTS (
                SELECT 1 FROM product_variants pv2
                JOIN product_variant_options pvo2 ON pvo2.variant_id = pv2.id
                WHERE pv2.product_id = p.id AND pvo2.option_value_id IN (' . implode(',', array_fill(0, count($ids), '?')) . '))';
            $params = array_merge($params, $ids);
        }
    }

    $salesJoin = $sort === 'popular'
        ? 'LEFT JOIN (SELECT product_id, SUM(quantity) AS total_sold FROM order_items GROUP BY product_id) sales ON sales.product_id = p.id'
        : '';
    $orderBy = match ($sort) {
        'price_asc'  => 'p.price ASC',
        'price_desc' => 'p.price DESC',
        'rating'     => 'COALESCE(rv.avg_rating, 0) DESC, COALESCE(rv.review_count, 0) DESC',
        'popular'    => 'COALESCE(sales.total_sold, 0) DESC',
        default      => 'p.id DESC',
    };

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM products p JOIN businesses b ON b.id = p.business_id '
        . SHOP_RV_JOIN . " WHERE $where");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    // LIMIT and OFFSET are ints the caller has already clamped, so they are
    // written in rather than bound — PDO would quote a bound one as a string.
    $stmt = $pdo->prepare('SELECT ' . SHOP_CARD_COLUMNS . '
        FROM products p
        JOIN businesses b ON b.id = p.business_id
        LEFT JOIN product_photos pp ON pp.product_id = p.id AND pp.is_primary = 1
        ' . SHOP_RV_JOIN . "
        $salesJoin
        WHERE $where
        ORDER BY $orderBy, p.id DESC
        LIMIT " . (int)$limit . ' OFFSET ' . (int)$offset);
    $stmt->execute($params);

    $products = array_map('shop_card', $stmt->fetchAll());
    return [
        'products' => $products,
        'total'    => $total,
        'has_more' => ($offset + count($products)) < $total,
    ];
}

/** Paging from the query string, clamped. */
function shop_paging(int $defaultLimit = 20): array {
    $limit  = max(1, min(50, (int)($_GET['limit'] ?? $defaultLimit)));
    // A ceiling on offset too: MySQL still reads and discards every skipped
    // row, so offset=10000000 is an expensive way to get nothing.
    $offset = max(0, min(5000, (int)($_GET['offset'] ?? 0)));
    return [$offset, $limit];
}

/** Every shop reply ends here, so each one carries the riel rate. */
function shop_json(array $data): void {
    api_json($data + ['khr_rate' => KHR_RATE]);
}
