<?php
session_start();
require __DIR__ . '/../config/db.php';

$q          = trim($_GET['q'] ?? '');
$sort       = $_GET['sort'] ?? 'newest';
$minPrice   = trim($_GET['min_price'] ?? '');
$maxPrice   = trim($_GET['max_price'] ?? '');
$categoryId = (int)($_GET['category'] ?? 0);
$rawRating  = (int)($_GET['min_rating'] ?? 0);
$minRating        = in_array($rawRating, [2, 3, 4]) ? (float)$rawRating : 0.0;
$selectedValueIds = array_values(array_unique(array_filter(array_map('intval', (array)($_GET['variant_values'] ?? [])), fn($v) => $v > 0)));

$validSorts = ['newest', 'price_asc', 'price_desc', 'rating', 'popular'];
if (!in_array($sort, $validSorts, true)) $sort = 'newest';
if ($minPrice !== '' && !is_numeric($minPrice)) $minPrice = '';
if ($maxPrice !== '' && !is_numeric($maxPrice)) $maxPrice = '';

$hasActiveFilters = $minPrice !== '' || $maxPrice !== '' || $categoryId > 0 || $minRating > 0 || !empty($selectedValueIds);

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

// ── JOINs ────────────────────────────────────────────────────────────
$rvJoin    = 'LEFT JOIN (SELECT product_id, AVG(rating) AS avg_rating, COUNT(*) AS review_count
               FROM reviews GROUP BY product_id) rv ON rv.product_id = p.id';
$salesJoin = $sort === 'popular'
    ? 'LEFT JOIN (SELECT product_id, SUM(quantity) AS total_sold FROM order_items GROUP BY product_id) sales ON sales.product_id = p.id'
    : '';

// ── Variant filter ───────────────────────────────────────────────────
// Save base WHERE (without variant filter) for sidebar options query
$baseWhere  = $where;
$baseParams = $params;
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

// ── Sidebar option types/values (from base results, not variant-filtered) ──
$optionTypes = [];
if ($q !== '') {
    $optStmt = $pdo->prepare("
        SELECT pot.id AS type_id, pot.name AS type_name,
               pov.id AS value_id, pov.label AS value_label
        FROM products p
        JOIN businesses b ON b.id = p.business_id
        $rvJoin
        JOIN product_option_types pot ON pot.product_id = p.id
        JOIN product_option_values pov ON pov.option_type_id = pot.id
        WHERE $baseWhere
        GROUP BY pot.id, pot.name, pov.id, pov.label
        ORDER BY pot.name ASC, pov.display_order ASC, pov.id ASC
    ");
    $optStmt->execute($baseParams);
    foreach ($optStmt->fetchAll() as $row) {
        $tid = (int)$row['type_id'];
        if (!isset($optionTypes[$tid])) {
            $optionTypes[$tid] = ['name' => $row['type_name'], 'values' => []];
        }
        $optionTypes[$tid]['values'][] = ['id' => (int)$row['value_id'], 'label' => $row['value_label']];
    }
}

$orderBy = match($sort) {
    'price_asc'  => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'rating'     => 'COALESCE(rv.avg_rating, 0) DESC, COALESCE(rv.review_count, 0) DESC',
    'popular'    => 'COALESCE(sales.total_sold, 0) DESC',
    default      => 'p.id DESC',
};

// ── Count ────────────────────────────────────────────────────────────
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p
    JOIN businesses b ON b.id = p.business_id
    $rvJoin
    WHERE $where");
$countStmt->execute($params);
$count = (int)$countStmt->fetchColumn();

// ── First 20 results ─────────────────────────────────────────────────
$dataStmt = $pdo->prepare("
    SELECT p.id, p.name, p.description, p.price, p.sale_price, p.sale_ends_at,
           pp.filename AS photo,
           b.id AS business_id, b.name AS business_name,
           COALESCE(rv.avg_rating, 0) AS avg_rating,
           COALESCE(rv.review_count, 0) AS review_count
    FROM products p
    JOIN businesses b ON b.id = p.business_id
    LEFT JOIN product_photos pp ON pp.product_id = p.id AND pp.is_primary = 1
    $rvJoin
    $salesJoin
    WHERE $where
    ORDER BY $orderBy
    LIMIT 20");
$dataStmt->execute($params);
$products = $dataStmt->fetchAll();

$hasMore = $count > 20;

// ── Category dropdown ─────────────────────────────────────────────────
$categories = [];
if ($q !== '') {
    $categories = $pdo->query(
        "SELECT c.id, c.name
         FROM categories c
         WHERE c.id NOT IN (SELECT DISTINCT parent_id FROM categories WHERE parent_id IS NOT NULL)
           AND EXISTS (
               SELECT 1 FROM products p2
               JOIN businesses b2 ON b2.id = p2.business_id
               WHERE p2.category_id = c.id AND p2.active = 1 AND p2.archived = 0 AND b2.approved = 1
           )
         ORDER BY c.name"
    )->fetchAll();
}

$title = $q ? htmlspecialchars($q) . ' — teepsaa' : 'Search — teepsaa';

$sortLabels = [
    'newest'     => 'Newest',
    'price_asc'  => 'Price: low to high',
    'price_desc' => 'Price: high to low',
    'rating'     => 'Top rated',
    'popular'    => 'Most popular',
];

// ── Build filter chips ───────────────────────────────────────────────
function searchUrl(string $q, string $sort, string $minPrice, string $maxPrice, int $categoryId, float $minRating, array $selVals = []): string {
    $p = ['q' => $q];
    if ($sort !== 'newest')  $p['sort']           = $sort;
    if ($minPrice !== '')    $p['min_price']       = $minPrice;
    if ($maxPrice !== '')    $p['max_price']       = $maxPrice;
    if ($categoryId > 0)     $p['category']        = $categoryId;
    if ($minRating > 0)      $p['min_rating']      = (int)$minRating;
    if (!empty($selVals))    $p['variant_values']  = $selVals;
    return '/search/?' . http_build_query($p);
}

$chips = [];
if ($sort !== 'newest') {
    $chips[] = [
        'label' => $sortLabels[$sort],
        'url'   => searchUrl($q, 'newest', $minPrice, $maxPrice, $categoryId, $minRating, $selectedValueIds),
    ];
}
if ($categoryId > 0) {
    $catName = '';
    foreach ($categories as $cat) {
        if ((int)$cat['id'] === $categoryId) { $catName = $cat['name']; break; }
    }
    $chips[] = [
        'label' => $catName ?: 'Category',
        'url'   => searchUrl($q, $sort, $minPrice, $maxPrice, 0, $minRating, $selectedValueIds),
    ];
}
if ($minPrice !== '' && $maxPrice !== '') {
    $chips[] = [
        'label' => '$' . $minPrice . ' – $' . $maxPrice,
        'url'   => searchUrl($q, $sort, '', '', $categoryId, $minRating, $selectedValueIds),
    ];
} elseif ($minPrice !== '') {
    $chips[] = [
        'label' => 'From $' . $minPrice,
        'url'   => searchUrl($q, $sort, '', $maxPrice, $categoryId, $minRating, $selectedValueIds),
    ];
} elseif ($maxPrice !== '') {
    $chips[] = [
        'label' => 'Up to $' . $maxPrice,
        'url'   => searchUrl($q, $sort, $minPrice, '', $categoryId, $minRating, $selectedValueIds),
    ];
}
if ($minRating > 0) {
    $chips[] = [
        'label' => '★ ' . (int)$minRating . ' & up',
        'url'   => searchUrl($q, $sort, $minPrice, $maxPrice, $categoryId, 0, $selectedValueIds),
    ];
}
// Variant value chips
$valueChipMap = [];
foreach ($optionTypes as $type) {
    foreach ($type['values'] as $val) {
        $valueChipMap[$val['id']] = ['label' => $val['label'], 'type' => $type['name']];
    }
}
foreach ($selectedValueIds as $vid) {
    if (isset($valueChipMap[$vid])) {
        $remaining = array_values(array_filter($selectedValueIds, fn($v) => $v !== $vid));
        $chips[] = [
            'label' => $valueChipMap[$vid]['type'] . ': ' . $valueChipMap[$vid]['label'],
            'url'   => searchUrl($q, $sort, $minPrice, $maxPrice, $categoryId, $minRating, $remaining),
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <?php
        require_once __DIR__ . '/../config/seo.php';
        $searchDesc = $q
            ? 'Search results for "' . htmlspecialchars($q) . '" on teepsaa — ' . $count . ' product' . ($count !== 1 ? 's' : '') . ' found.'
            : 'Browse all products on teepsaa — local Phnom Penh businesses, fast Grab delivery.';
        echo seo_meta($title, $searchDesc);
    ?>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/search/search.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
<?php if ($q !== ''): ?>

    <div class="search-layout">

        <!-- ── Left sidebar ── -->
        <aside class="filter-sidebar" id="filter-sidebar">
            <button class="filter-toggle-btn" id="filter-toggle" aria-expanded="false">
                <svg width="14" height="12" viewBox="0 0 14 12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                    <line x1="0" y1="1" x2="14" y2="1"/>
                    <line x1="2" y1="6" x2="12" y2="6"/>
                    <line x1="4" y1="11" x2="10" y2="11"/>
                </svg>
                Filters<?= $hasActiveFilters ? ' <span class="filter-active-dot"></span>' : '' ?>
            </button>

            <div class="sidebar-body" id="sidebar-body">
                <p class="sidebar-heading">Filters</p>

                <form method="get" action="/search/" id="filter-form">
                    <input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>">

                    <div class="filter-group">
                        <label class="filter-label">Sort by</label>
                        <select name="sort" class="filter-select" onchange="this.form.submit()">
                            <?php foreach ($sortLabels as $val => $label): ?>
                            <option value="<?= $val ?>"<?= $sort === $val ? ' selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if (!empty($categories)): ?>
                    <div class="filter-group">
                        <label class="filter-label">Category</label>
                        <select name="category" class="filter-select" onchange="this.form.submit()">
                            <option value="">All categories</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>"<?= $categoryId === (int)$cat['id'] ? ' selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="filter-group">
                        <label class="filter-label">Price (USD)</label>
                        <div class="price-inputs">
                            <input type="number" name="min_price" class="filter-input" placeholder="Min"
                                   min="0" step="0.01" value="<?= htmlspecialchars($minPrice) ?>">
                            <span class="price-dash">–</span>
                            <input type="number" name="max_price" class="filter-input" placeholder="Max"
                                   min="0" step="0.01" value="<?= htmlspecialchars($maxPrice) ?>">
                        </div>
                        <button type="submit" class="price-apply">Apply</button>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Rating</label>
                        <select name="min_rating" class="filter-select" onchange="this.form.submit()">
                            <option value="">Any rating</option>
                            <option value="4"<?= $minRating === 4.0 ? ' selected' : '' ?>>★ 4 &amp; up</option>
                            <option value="3"<?= $minRating === 3.0 ? ' selected' : '' ?>>★ 3 &amp; up</option>
                            <option value="2"<?= $minRating === 2.0 ? ' selected' : '' ?>>★ 2 &amp; up</option>
                        </select>
                    </div>

                    <?php foreach ($optionTypes as $typeId => $type): ?>
                    <div class="filter-group">
                        <label class="filter-label"><?= htmlspecialchars($type['name']) ?></label>
                        <div class="filter-checkboxes">
                            <?php foreach ($type['values'] as $val): ?>
                            <label class="filter-checkbox-label">
                                <input type="checkbox" name="variant_values[]" value="<?= $val['id'] ?>"
                                       <?= in_array($val['id'], $selectedValueIds, true) ? 'checked' : '' ?>
                                       onchange="this.form.submit()">
                                <?= htmlspecialchars($val['label']) ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <?php if ($hasActiveFilters): ?>
                    <a href="/search/?q=<?= urlencode($q) ?>" class="filter-clear">Clear filters</a>
                    <?php endif; ?>
                </form>
            </div>
        </aside>

        <!-- ── Right results ── -->
        <div class="search-results">
            <div class="browse-toolbar">
                <p class="browse-count">
                    <?= $count ?> product<?= $count !== 1 ? 's' : '' ?> for <em><?= htmlspecialchars($q) ?></em>
                </p>
            </div>

            <?php if (!empty($chips)): ?>
            <div class="filter-chips">
                <?php foreach ($chips as $chip): ?>
                <a href="<?= htmlspecialchars($chip['url']) ?>" class="filter-chip">
                    <?= htmlspecialchars($chip['label']) ?><span class="chip-x" aria-hidden="true">×</span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (empty($products)): ?>
                <p class="no-results">No products found<?= $hasActiveFilters ? ' — try removing some filters' : '' ?>.</p>
            <?php else: ?>
            <div class="product-grid" id="product-grid">
                <?php foreach ($products as $p): ?>
                <a href="/product/?id=<?= $p['id'] ?>" class="product-card">
                    <?php if ($p['photo']): ?>
                        <img src="/uploads/<?= htmlspecialchars($p['photo']) ?>" alt="" class="card-photo">
                    <?php else: ?>
                        <div class="card-photo card-photo--empty"></div>
                    <?php endif; ?>
                    <div class="card-body">
                        <strong class="card-name"><?= htmlspecialchars($p['name']) ?></strong>
                        <span class="card-price"><?= price_html($p) ?></span>
                        <span class="card-seller"><?= htmlspecialchars($p['business_name']) ?></span>
                        <?php if ($p['review_count'] > 0): ?>
                        <span class="card-rating">★ <?= number_format($p['avg_rating'], 1) ?> (<?= (int)$p['review_count'] ?>)</span>
                        <?php endif; ?>
                        <?php if ($p['description']): ?>
                            <p class="card-desc"><?= htmlspecialchars(mb_strimwidth($p['description'], 0, 100, '…')) ?></p>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>

            <?php if ($hasMore): ?>
            <div id="scroll-sentinel"></div>
            <div id="scroll-spinner" class="scroll-spinner" style="display:none">
                <div class="spinner"></div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div><!-- /.search-results -->

    </div><!-- /.search-layout -->

<?php else: ?>

    <div class="browse-toolbar">
        <p class="browse-count"><?= $count ?> product<?= $count !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($products)): ?>
        <p class="no-results">No products found.</p>
    <?php else: ?>
    <div class="product-grid" id="product-grid">
        <?php foreach ($products as $p): ?>
        <a href="/product/?id=<?= $p['id'] ?>" class="product-card">
            <?php if ($p['photo']): ?>
                <img src="/uploads/<?= htmlspecialchars($p['photo']) ?>" alt="" class="card-photo">
            <?php else: ?>
                <div class="card-photo card-photo--empty"></div>
            <?php endif; ?>
            <div class="card-body">
                <strong class="card-name"><?= htmlspecialchars($p['name']) ?></strong>
                <span class="card-price"><?= price_html($p) ?></span>
                <span class="card-seller"><?= htmlspecialchars($p['business_name']) ?></span>
                <?php if ($p['review_count'] > 0): ?>
                <span class="card-rating">★ <?= number_format($p['avg_rating'], 1) ?> (<?= (int)$p['review_count'] ?>)</span>
                <?php endif; ?>
                <?php if ($p['description']): ?>
                    <p class="card-desc"><?= htmlspecialchars(mb_strimwidth($p['description'], 0, 100, '…')) ?></p>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if ($hasMore): ?>
    <div id="scroll-sentinel"></div>
    <div id="scroll-spinner" class="scroll-spinner" style="display:none">
        <div class="spinner"></div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

<?php endif; ?>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

<?php if ($hasMore): ?>
<script>
(function () {
    var offset   = 20;
    var loading  = false;
    var done     = false;
    var filters  = <?= json_encode([
        'q'             => $q,
        'sort'          => $sort,
        'min_price'     => $minPrice,
        'max_price'     => $maxPrice,
        'category'      => $categoryId ?: '',
        'min_rating'    => $minRating > 0 ? (string)(int)$minRating : '',
        'variant_values'=> $selectedValueIds,
    ]) ?>;
    var currency = '<?= htmlspecialchars($_SESSION['currency'] ?? 'USD') ?>';
    var khrRate  = <?= KHR_RATE ?>;
    var grid     = document.getElementById('product-grid');
    var sentinel = document.getElementById('scroll-sentinel');
    var spinner  = document.getElementById('scroll-spinner');

    function buildUrl(off) {
        var url = '/api/search/?offset=' + off;
        if (filters.q)          url += '&q='          + encodeURIComponent(filters.q);
        if (filters.sort)       url += '&sort='        + encodeURIComponent(filters.sort);
        if (filters.min_price)  url += '&min_price='   + encodeURIComponent(filters.min_price);
        if (filters.max_price)  url += '&max_price='   + encodeURIComponent(filters.max_price);
        if (filters.category)   url += '&category='    + encodeURIComponent(filters.category);
        if (filters.min_rating) url += '&min_rating='  + encodeURIComponent(filters.min_rating);
        if (filters.variant_values && filters.variant_values.length) {
            filters.variant_values.forEach(function (v) { url += '&variant_values[]=' + encodeURIComponent(v); });
        }
        return url;
    }

    function fmtPrice(usd) {
        if (currency === 'KHR') return '៛' + Math.round(usd * khrRate).toLocaleString();
        return '$' + parseFloat(usd).toFixed(2);
    }

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function cardHtml(p) {
        var photo = p.photo
            ? '<img src="/uploads/' + escHtml(p.photo) + '" alt="" class="card-photo" loading="lazy">'
            : '<div class="card-photo card-photo--empty"></div>';
        var rating = p.review_count > 0
            ? '<span class="card-rating">★ ' + parseFloat(p.avg_rating).toFixed(1) + ' (' + p.review_count + ')</span>'
            : '';
        var desc = p.description
            ? '<p class="card-desc">' + escHtml(p.description.substring(0, 100)) + (p.description.length > 100 ? '…' : '') + '</p>'
            : '';
        var now = Date.now() / 1000;
        var onSale = p.sale_price && p.sale_ends_at && (new Date(p.sale_ends_at).getTime() / 1000) > now;
        var priceHtml = onSale
            ? '<span class="price-sale">' + fmtPrice(p.sale_price) + '</span>'
              + '<span class="price-original">' + fmtPrice(p.price) + '</span>'
            : fmtPrice(p.price);
        return '<a href="/product/?id=' + p.id + '" class="product-card">'
            + photo
            + '<div class="card-body">'
            + '<strong class="card-name">' + escHtml(p.name) + '</strong>'
            + '<span class="card-price">' + priceHtml + '</span>'
            + '<span class="card-seller">' + escHtml(p.business_name) + '</span>'
            + rating
            + desc
            + '</div></a>';
    }

    function loadMore() {
        if (loading || done) return;
        loading = true;
        spinner.style.display = 'flex';

        fetch(buildUrl(offset))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                loading = false;
                spinner.style.display = 'none';
                data.products.forEach(function (p) {
                    grid.insertAdjacentHTML('beforeend', cardHtml(p));
                });
                offset += data.products.length;
                if (!data.has_more) {
                    done = true;
                    sentinel.remove();
                    spinner.remove();
                    observer.disconnect();
                }
            })
            .catch(function () {
                loading = false;
                spinner.style.display = 'none';
            });
    }

    var observer = new IntersectionObserver(function (entries) {
        if (entries[0].isIntersecting) loadMore();
    }, { rootMargin: '300px' });

    observer.observe(sentinel);
}());
</script>
<?php endif; ?>

<script>
(function () {
    var btn     = document.getElementById('filter-toggle');
    var sidebar = document.getElementById('filter-sidebar');
    if (!btn || !sidebar) return;

    btn.addEventListener('click', function () {
        var open = sidebar.classList.toggle('is-open');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
}());
</script>

</body>
</html>
