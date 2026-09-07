<?php
session_start([
    'gc_maxlifetime'  => 28800,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/category.php';

// A category page is the page that can rank for the broad searches — "bags
// Phnom Penh", "សំពត់" — in a way no single listing can: it stays at one
// address, it is about one clear thing, and it accumulates links over time.
// The homepage tiles used to point at /search/?q=Bags, which is a *text*
// search across names and descriptions, so it silently missed every bag whose
// listing didn't happen to contain the word. This page filters on the category
// itself, and on the whole branch beneath it.

$lang = current_lang();
$t = require __DIR__ . '/../lang/' . (in_array($lang, ['en', 'km'], true) ? $lang : 'en') . '.php';

$reqPath = rawurldecode(strtok((string)($_SERVER['REQUEST_URI'] ?? ''), '?'));
$slug    = preg_match('~^/category/([^/]+)/?$~', $reqPath, $m) ? $m[1] : '';

$category = $slug !== '' ? category_by_slug($pdo, $slug) : null;

// /category/?id=19 is not an address anything links to, but the admin panel
// and hand-typed links reach for it, and it costs one line to answer and
// forward. Everything non-canonical ends up at the canonical address below.
if (!$category && ($legacyCatId = (int)($_GET['id'] ?? 0)) > 0) {
    $category = category_all($pdo)[$legacyCatId] ?? null;
}

if (!$category) {
    http_response_code(404);
    require __DIR__ . '/../404/index.php';
    exit;
}

$catId         = (int)$category['id'];
$canonicalPath = category_path($pdo, $category);
if (rawurldecode($canonicalPath) !== $reqPath) {
    $qs = $_GET;
    unset($qs['id']);
    header('Location: ' . $canonicalPath . ($qs ? '?' . http_build_query($qs) : ''), true, 301);
    exit;
}

// ── The branch ───────────────────────────────────────────────────────
// Vendors file products against leaves ("Dresses"), never against "Women's",
// so a parent category has to ask for everything beneath it or it shows an
// empty grid on a page that plainly has products under it.
$branchIds  = category_branch_ids($pdo, $catId);
$branchPh   = implode(',', array_fill(0, count($branchIds), '?'));
$catWhere   = "p.active = 1 AND p.archived = 0 AND b.approved = 1 AND b.suspended = 0
               AND p.category_id IN ($branchPh)";

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p JOIN businesses b ON b.id = p.business_id WHERE $catWhere");
$countStmt->execute($branchIds);
$total = (int)$countStmt->fetchColumn();

$perPage    = 24;
$totalPages = max(1, (int)ceil($total / $perPage));
$page       = max(1, (int)($_GET['page'] ?? 1));

// A page number past the end is not a page. Serving the last page's contents
// under it instead would be a soft 404 — an address that answers 200 with
// nothing new on it, which Google keeps re-crawling forever.
if ($page > $totalPages) {
    http_response_code(404);
    require __DIR__ . '/../404/index.php';
    exit;
}

$dataStmt = $pdo->prepare("
    SELECT p.id, p.public_id, p.name, p.name_km, p.description, p.description_km,
           p.price, p.sale_percent, p.sale_ends_at,
           pp.filename AS photo,
           b.name AS business_name, b.name_km AS business_name_km,
           COALESCE(rv.avg_rating, 0) AS avg_rating,
           COALESCE(rv.review_count, 0) AS review_count
    FROM products p
    JOIN businesses b ON b.id = p.business_id
    LEFT JOIN product_photos pp ON pp.product_id = p.id AND pp.is_primary = 1
    LEFT JOIN (SELECT product_id, AVG(rating) AS avg_rating, COUNT(*) AS review_count FROM reviews GROUP BY product_id) rv
           ON rv.product_id = p.id
    WHERE $catWhere
    ORDER BY p.id DESC
    LIMIT $perPage OFFSET " . (($page - 1) * $perPage));
$dataStmt->execute($branchIds);
$catProducts = $dataStmt->fetchAll();

// ── Children, for browsing further in ────────────────────────────────
// Also the internal links that let a crawler walk from /category/womens/ down
// to /category/dresses/ without going through the sitemap.
$childCats = [];
foreach (category_all($pdo) as $cid => $row) {
    if ((int)($row['parent_id'] ?? 0) === $catId) $childCats[] = $row;
}

$catName  = cat_name($category);
$catTitle = $catName . ' — teepsaa';

// ── Intro copy ───────────────────────────────────────────────────────
// Two or three sentences of real writing is most of what separates a page
// worth indexing from a bare grid. Kept in a file rather than the database so
// it deploys with the code — see category/intros.php.
$catIntros = require __DIR__ . '/intros.php';
$catSlug   = category_slugs($pdo)[$catId] ?? '';
$catIntro  = trim((string)($catIntros[$catSlug][$lang] ?? ($catIntros[$catSlug]['en'] ?? '')));

$pgUrl = function (int $n) use ($canonicalPath): string {
    return htmlspecialchars(lang_href($canonicalPath . ($n > 1 ? '?page=' . $n : '')), ENT_QUOTES, 'UTF-8');
};
?>
<!DOCTYPE html>
<html lang="<?= current_lang() ?>">
<?php
require_once __DIR__ . '/../config/schema.php';

$headTitle = $catTitle . ($page > 1 ? ' — ' . sprintf($t['pg_page'], $page) : '');
$headDesc  = $catIntro !== '' ? $catIntro : sprintf($t['seo_cat_desc'], $catName);
$headImage = $catProducts[0]['photo'] ?? '';
// Page 2 and beyond canonicalise to themselves. Pointing them all at page 1
// (the old advice) tells Google the products only on page 3 don't exist
// anywhere it should look.
$headUrl   = 'https://teepsaa.com' . $canonicalPath . ($page > 1 ? '?page=' . $page : '');

// The product grid and card are search.css's; duplicating a hundred lines of
// them here would only let the two drift apart.
$headCss = ['/breadcrumb/breadcrumb.css', '/search/search.css',
            '/pagination/pagination.css', '/category/category.css'];

// Home › Clothing › Women's › Dresses. One array, two consumers: the visible
// trail below and the hidden block here — Google shows a breadcrumb in a
// result only when the two agree.
$crumbs = [[$t['crumb_home'], '/']];
foreach (category_ancestors($pdo, $catId) as $ancestor) {
    $isSelf   = (int)$ancestor['id'] === $catId;
    $crumbs[] = [cat_name($ancestor), $isSelf ? '' : category_path($pdo, $ancestor)];
}
$headExtra = schema_graph(schema_breadcrumb($crumbs));
require __DIR__ . '/../head/head.php';
?>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <?php require __DIR__ . '/../breadcrumb/breadcrumb.php'; ?>

    <header class="cat-head">
        <h1 class="cat-title"><?= htmlspecialchars($catName) ?></h1>
        <?php if ($catIntro !== ''): ?>
            <p class="cat-intro"><?= htmlspecialchars($catIntro) ?></p>
        <?php endif; ?>
        <p class="cat-meta">
            <span class="cat-count"><?= $total ?> <?= $t['search_products'] ?></span>
            <?php if ($total > 0): ?>
                <a class="cat-refine" href="<?= htmlspecialchars(lang_href('/search/?category=' . $catId)) ?>"><?= $t['cat_refine'] ?> &rsaquo;</a>
            <?php endif; ?>
        </p>
    </header>

    <?php if ($childCats): ?>
    <nav class="cat-children" aria-label="<?= htmlspecialchars($t['cat_subcategories']) ?>">
        <h2 class="cat-children-title"><?= $t['cat_subcategories'] ?></h2>
        <ul class="cat-children-list">
            <?php foreach ($childCats as $child): ?>
            <li><a href="<?= htmlspecialchars(lang_href(category_path($pdo, $child))) ?>"><?= htmlspecialchars(cat_name($child)) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </nav>
    <?php endif; ?>

    <?php if (empty($catProducts)): ?>
        <p class="no-results"><?= $t['cat_empty'] ?></p>
    <?php else: ?>
    <div class="product-grid">
        <?php foreach ($catProducts as $p): ?>
        <a href="<?= htmlspecialchars(lang_href(product_path($p))) ?>" class="product-card">
            <?php if ($p['photo']): ?>
                <img src="<?= htmlspecialchars(image_variant($p['photo'])) ?>" alt="<?= htmlspecialchars(lang_field($p, 'name')) ?>" class="card-photo" width="400" height="400" loading="lazy" decoding="async">
            <?php else: ?>
                <div class="card-photo card-photo--empty"></div>
            <?php endif; ?>
            <div class="card-body">
                <strong class="card-name"><?= htmlspecialchars(lang_field($p, 'name')) ?></strong>
                <span class="card-price"><?= price_html($p) ?></span>
                <span class="card-seller"><?= htmlspecialchars(pick_lang($p['business_name'], $p['business_name_km'] ?? null)) ?></span>
                <?php if ($p['review_count'] > 0): ?>
                <span class="card-rating">★ <?= number_format($p['avg_rating'], 1) ?> (<?= (int)$p['review_count'] ?>)</span>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <?php
        $pgCurrent = $page;
        $pgTotal   = $totalPages;
        require __DIR__ . '/../pagination/pagination.php';
    ?>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>
</body>
</html>
