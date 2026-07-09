<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/config/db.php';

function product_card(array $p): string {
    $photo = $p['photo']
        ? '<img src="/uploads/' . htmlspecialchars($p['photo']) . '" alt="" class="card-photo">'
        : '<div class="card-photo card-photo--empty"></div>';
    $rating = (!empty($p['review_count']) && $p['review_count'] > 0)
        ? '<span class="card-rating">★ ' . number_format((float)$p['avg_rating'], 1) . ' (' . (int)$p['review_count'] . ')</span>'
        : '';
    return '<a href="/product/?id=' . htmlspecialchars($p['public_id']) . '" class="product-card">'
        . $photo
        . '<div class="card-body">'
        . '<strong class="card-name">' . htmlspecialchars(lang_field($p, 'name')) . '</strong>'
        . '<span class="card-price">' . price_html($p) . '</span>'
        . '<span class="card-seller">' . htmlspecialchars(pick_lang($p['business_name'], $p['business_name_km'] ?? null)) . '</span>'
        . $rating
        . '</div></a>';
}


$rv = 'LEFT JOIN (SELECT product_id, AVG(rating) AS avg_rating, COUNT(*) AS review_count FROM reviews GROUP BY product_id) rv ON rv.product_id = p.id';

// ── Featured — random in-stock products ──────────────────────────
$featured = $pdo->query(
    "SELECT p.id, p.public_id, p.name, p.name_km, p.price, p.sale_price, p.sale_ends_at, b.name AS business_name, b.name_km AS business_name_km,
            pp.filename AS photo,
            COALESCE(rv.avg_rating, 0) AS avg_rating, COALESCE(rv.review_count, 0) AS review_count
     FROM products p
     JOIN businesses b ON p.business_id = b.id AND b.approved = 1
     LEFT JOIN product_photos pp ON pp.product_id = p.id AND pp.is_primary = 1
     $rv
     WHERE p.active = 1 AND p.archived = 0 AND p.stock > 0
     ORDER BY RAND() LIMIT 8"
)->fetchAll();

// ── Best sellers — most ordered all time ─────────────────────────
$bestSellers = $pdo->query(
    "SELECT p.id, p.public_id, p.name, p.name_km, p.price, p.sale_price, p.sale_ends_at, b.name AS business_name, b.name_km AS business_name_km,
            (SELECT filename FROM product_photos WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS photo,
            SUM(oi.quantity) AS total_sold,
            COALESCE(rv.avg_rating, 0) AS avg_rating, COALESCE(rv.review_count, 0) AS review_count
     FROM order_items oi
     JOIN products p ON oi.product_id = p.id
     JOIN businesses b ON p.business_id = b.id AND b.approved = 1
     $rv
     WHERE p.active = 1 AND p.archived = 0 AND p.stock > 0
     GROUP BY p.id, p.name, p.price, p.sale_price, p.sale_ends_at, b.name, rv.avg_rating, rv.review_count
     ORDER BY total_sold DESC
     LIMIT 8"
)->fetchAll();

// ── Trending this week — order volume last 7 days ─────────────────
$trending = $pdo->query(
    "SELECT p.id, p.public_id, p.name, p.name_km, p.price, p.sale_price, p.sale_ends_at, b.name AS business_name, b.name_km AS business_name_km,
            (SELECT filename FROM product_photos WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS photo,
            SUM(oi.quantity) AS total_sold,
            COALESCE(rv.avg_rating, 0) AS avg_rating, COALESCE(rv.review_count, 0) AS review_count
     FROM order_items oi
     JOIN orders o ON oi.order_id = o.id
     JOIN products p ON oi.product_id = p.id
     JOIN businesses b ON p.business_id = b.id AND b.approved = 1
     $rv
     WHERE p.active = 1 AND p.archived = 0 AND p.stock > 0
       AND o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
       AND o.status NOT IN ('pending','cancelled')
     GROUP BY p.id, p.name, p.price, p.sale_price, p.sale_ends_at, b.name, rv.avg_rating, rv.review_count
     ORDER BY total_sold DESC
     LIMIT 8"
)->fetchAll();

// ── New arrivals — most recently added ───────────────────────────
$newArrivals = $pdo->query(
    "SELECT p.id, p.public_id, p.name, p.name_km, p.price, p.sale_price, p.sale_ends_at, b.name AS business_name, b.name_km AS business_name_km,
            pp.filename AS photo,
            COALESCE(rv.avg_rating, 0) AS avg_rating, COALESCE(rv.review_count, 0) AS review_count
     FROM products p
     JOIN businesses b ON p.business_id = b.id AND b.approved = 1
     LEFT JOIN product_photos pp ON pp.product_id = p.id AND pp.is_primary = 1
     $rv
     WHERE p.active = 1 AND p.archived = 0 AND p.stock > 0
     ORDER BY p.created_at DESC
     LIMIT 8"
)->fetchAll();

// ── Top rated — highest-reviewed products ────────────────────────
$topRated = $pdo->query(
    "SELECT p.id, p.public_id, p.name, p.name_km, p.price, p.sale_price, p.sale_ends_at, b.name AS business_name, b.name_km AS business_name_km,
            (SELECT filename FROM product_photos WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS photo,
            AVG(r.rating) AS avg_rating, COUNT(r.id) AS review_count
     FROM reviews r
     JOIN products p ON r.product_id = p.id AND p.active = 1 AND p.archived = 0 AND p.stock > 0
     JOIN businesses b ON p.business_id = b.id AND b.approved = 1
     GROUP BY p.id, p.name, p.price, p.sale_price, p.sale_ends_at, b.name
     HAVING review_count >= 1
     ORDER BY avg_rating DESC, review_count DESC
     LIMIT 8"
)->fetchAll();

// ── Under $15 ────────────────────────────────────────────────────
$underFifteen = $pdo->query(
    "SELECT p.id, p.public_id, p.name, p.name_km, p.price, p.sale_price, p.sale_ends_at, b.name AS business_name, b.name_km AS business_name_km,
            (SELECT filename FROM product_photos WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS photo,
            COALESCE(rv.avg_rating, 0) AS avg_rating, COALESCE(rv.review_count, 0) AS review_count
     FROM products p
     JOIN businesses b ON p.business_id = b.id AND b.approved = 1
     $rv
     WHERE p.active = 1 AND p.archived = 0 AND p.stock > 0 AND p.price < 15
     ORDER BY RAND()
     LIMIT 8"
)->fetchAll();

// ── Category tiles ───────────────────────────────────────────────
$catTiles = $pdo->query(
    "SELECT c.id, c.name, c.name_km, COUNT(p.id) AS product_count,
            (SELECT pp.filename
             FROM products pr
             JOIN product_photos pp ON pp.product_id = pr.id AND pp.is_primary = 1
             JOIN businesses biz ON biz.id = pr.business_id AND biz.approved = 1
             WHERE pr.category_id = c.id AND pr.active = 1 AND pr.stock > 0
             LIMIT 1) AS sample_photo
     FROM categories c
     JOIN products p ON p.category_id = c.id AND p.active = 1 AND p.stock > 0
     JOIN businesses b ON b.id = p.business_id AND b.approved = 1
     GROUP BY c.id, c.name
     ORDER BY product_count DESC
     LIMIT 10"
)->fetchAll();

// ── You might like — buyer purchase history ──────────────────────
$recommended = [];
if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'buyer') {
    $buyerId = (int)$_SESSION['user_id'];
    $stmt = $pdo->prepare(
        'SELECT p.category_id
         FROM order_items oi
         JOIN orders o ON oi.order_id = o.id
         JOIN products p ON oi.product_id = p.id
         WHERE o.buyer_user_id = ? AND p.category_id IS NOT NULL
         GROUP BY p.category_id
         ORDER BY COUNT(*) DESC
         LIMIT 3'
    );
    $stmt->execute([$buyerId]);
    $catIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if ($catIds) {
        $ph     = implode(',', array_fill(0, count($catIds), '?'));
        $params = array_merge($catIds, [$buyerId]);
        $stmt   = $pdo->prepare(
            "SELECT p.id, p.public_id, p.name, p.name_km, p.price, p.sale_price, p.sale_ends_at, b.name AS business_name, b.name_km AS business_name_km,
                    pp.filename AS photo,
                    COALESCE(rv.avg_rating, 0) AS avg_rating, COALESCE(rv.review_count, 0) AS review_count
             FROM products p
             JOIN businesses b ON p.business_id = b.id AND b.approved = 1
             LEFT JOIN product_photos pp ON pp.product_id = p.id AND pp.is_primary = 1
             $rv
             WHERE p.active = 1 AND p.archived = 0 AND p.stock > 0
               AND p.category_id IN ($ph)
               AND p.id NOT IN (
                   SELECT DISTINCT oi2.product_id FROM order_items oi2
                   JOIN orders o2 ON oi2.order_id = o2.id
                   WHERE o2.buyer_user_id = ? AND oi2.product_id IS NOT NULL
               )
             ORDER BY RAND() LIMIT 8"
        );
        $stmt->execute($params);
        $recommended = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>teepsaa — Shop local businesses in Phnom Penh</title>
    <?php
        require_once __DIR__ . '/config/seo.php';
        echo seo_meta(
            'teepsaa — Shop local businesses in Phnom Penh',
            'Discover and order from local Phnom Penh businesses on teepsaa. Fast Grab delivery, great products.',
            '',
            'https://teepsaa.com/'
        );
    ?>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <style>

        /* Homepage-only: let the banner span the full container, edge-to-edge
           and flush under the header. Side gutter moves onto each section. */
        main { padding: 0; }
        .banner-carousel {
            border-radius: 0;
            width: 100vw;
            margin-left: calc(50% - 50vw);
            margin-right: calc(50% - 50vw);
        }

        .home-section { margin: 3rem 0; padding: 0 1.5rem; }
        .home-section:first-of-type { margin-top: 2rem; }

        .home-section-head {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            padding: 0 0.25rem 1rem;
        }
        .home-section-head h2 { font-size: 1.5rem; font-weight: 700; letter-spacing: -0.01em; display: flex; align-items: center; gap: 0.6rem; }
        .home-section-head h2::before { content: ""; flex-shrink: 0; width: 4px; height: 1.05em; background: var(--primary); border-radius: var(--radius-pill); }
        .home-section-head h2 a { color: inherit; text-decoration: none; }
        .home-section-head h2 a:hover { text-decoration: underline; text-underline-offset: 3px; }

        /* Horizontal scroll row */
        .scroll-wrap { position: relative; }
        .home-scroll {
            display: flex;
            gap: 1rem;
            overflow-x: auto;
            /* overflow-x:auto also clips vertically, shearing the hover shadow.
               Padding enlarges the clip-box; equal negative margins cancel it
               so card alignment and section spacing stay unchanged. */
            margin: -12px -1.5rem -38px;
            padding: 12px 1.5rem 38px;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        .home-scroll::-webkit-scrollbar { display: none; }

        /* Scroll arrows */
        .scroll-arrow {
            position: absolute;
            top: 0;
            z-index: 10;
            width: 36px;
            height: 200px;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            background: rgba(255,255,255,0.92);
            box-shadow: 0 2px 8px rgba(0,0,0,0.12);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #111;
            padding: 0;
            transition: opacity 0.2s, background 0.15s;
        }
        .scroll-arrow:hover { background: #fff; box-shadow: 0 3px 12px rgba(0,0,0,0.18); }
        .scroll-arrow--left  { left: -10px; }
        .scroll-arrow--right { right: -10px; }

        /* Product card */
        .home-scroll .product-card {
            flex: 0 0 200px;
            text-decoration: none;
            color: inherit;
            background: #fff;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.07);
            transition: box-shadow 0.18s;
        }
        .home-scroll .product-card:hover {
            box-shadow: 4px 4px 6px rgba(var(--primary-rgb), 0.55),
                        6px 10px 16px rgba(0,0,0,0.10);
        }

        .card-photo { width: 100%; aspect-ratio: 1; object-fit: cover; display: block; }
        .card-photo--empty { background: #efefef; aspect-ratio: 1; }
        .card-body { padding: 0.65rem 0.75rem; display: flex; flex-direction: column; gap: 0.15rem; }
        .card-name { font-size: 0.88rem; color: #111; line-height: 1.35; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .card-price { font-size: 0.95rem; font-weight: 700; color: #111; }
        .card-seller { font-size: 0.75rem; color: #aaa; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .card-rating { font-size: 0.72rem; color: #f59e0b; }

        /* Category preview cards (inside .home-scroll) */
        .home-scroll .cat-preview {
            flex: 0 0 200px;
            position: relative;
            border-radius: var(--radius);
            overflow: hidden;
            aspect-ratio: 1;
            text-decoration: none;
            display: block;
            background: var(--border);
        }
        .cat-preview-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.25s ease;
        }
        .home-scroll .cat-preview:hover .cat-preview-img { transform: scale(1.05); }
        .cat-preview-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to bottom, transparent 40%, rgba(0,0,0,0.65) 100%);
        }
        .cat-preview-name {
            position: absolute;
            bottom: 0.65rem;
            left: 0.75rem;
            right: 0.75rem;
            color: #fff;
            font-size: 0.875rem;
            font-weight: 700;
            line-height: 1.3;
            text-shadow: 0 1px 4px rgba(0,0,0,0.5);
        }
        .cat-preview-placeholder {
            width: 100%;
            height: 100%;
            background: var(--border-strong);
        }
    </style>
</head>
<body>

<?php require __DIR__ . '/header/header.php'; ?>

<main>
    <?php require __DIR__ . '/includes/banner-carousel.php'; ?>

    <?php if (!empty($catTiles)): ?>
    <section class="home-section">
        <div class="home-section-head">
            <h2><?= $t['home_shop_by_category'] ?></h2>
        </div>
        <div class="home-scroll">
            <?php foreach ($catTiles as $cat): ?>
            <a href="/search/?q=<?= urlencode($cat['name']) ?>" class="cat-preview">
                <?php if ($cat['sample_photo']): ?>
                    <img src="/uploads/<?= htmlspecialchars($cat['sample_photo']) ?>" alt="" class="cat-preview-img">
                <?php else: ?>
                    <div class="cat-preview-placeholder"></div>
                <?php endif; ?>
                <div class="cat-preview-overlay"></div>
                <span class="cat-preview-name"><?= htmlspecialchars(cat_name($cat)) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($featured)): ?>
    <section class="home-section">
        <div class="home-section-head">
            <h2><a href="/search/"><?= $t['home_featured'] ?></a></h2>
        </div>
        <div class="home-scroll">
            <?php foreach ($featured as $p): echo product_card($p); endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($bestSellers)): ?>
    <section class="home-section">
        <div class="home-section-head">
            <h2><a href="/search/"><?= $t['home_best_sellers'] ?></a></h2>
        </div>
        <div class="home-scroll">
            <?php foreach ($bestSellers as $p): echo product_card($p); endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($trending)): ?>
    <section class="home-section">
        <div class="home-section-head">
            <h2><a href="/search/"><?= $t['home_trending'] ?></a></h2>
        </div>
        <div class="home-scroll">
            <?php foreach ($trending as $p): echo product_card($p); endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($newArrivals)): ?>
    <section class="home-section">
        <div class="home-section-head">
            <h2><a href="/search/"><?= $t['home_new_arrivals'] ?></a></h2>
        </div>
        <div class="home-scroll">
            <?php foreach ($newArrivals as $p): echo product_card($p); endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($topRated)): ?>
    <section class="home-section">
        <div class="home-section-head">
            <h2><a href="/search/"><?= $t['home_top_rated'] ?></a></h2>
        </div>
        <div class="home-scroll">
            <?php foreach ($topRated as $p): echo product_card($p); endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($underFifteen)): ?>
    <section class="home-section">
        <div class="home-section-head">
            <h2><a href="/search/"><?= $t['home_under_15'] ?></a></h2>
        </div>
        <div class="home-scroll">
            <?php foreach ($underFifteen as $p): echo product_card($p); endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($recommended)): ?>
    <section class="home-section">
        <div class="home-section-head">
            <h2><a href="/search/"><?= $t['home_you_might_like'] ?></a></h2>
        </div>
        <div class="home-scroll">
            <?php foreach ($recommended as $p): echo product_card($p); endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <div id="recently-viewed-section" style="display:none" class="home-section">
        <div class="home-section-head">
            <h2><?= $t['home_recently_viewed'] ?></h2>
        </div>
        <div id="recently-viewed-scroll" class="home-scroll"></div>
    </div>
</main>

<?php require __DIR__ . '/footer/footer.php'; ?>

<script>
(function () {
    var CHEVRON_L = '<svg width="8" height="13" viewBox="0 0 8 13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="7 1 1 6.5 7 12"/></svg>';
    var CHEVRON_R = '<svg width="8" height="13" viewBox="0 0 8 13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 1 7 6.5 1 12"/></svg>';

    document.querySelectorAll('.home-scroll').forEach(function (scroll) {
        var wrap = document.createElement('div');
        wrap.className = 'scroll-wrap';
        scroll.parentNode.insertBefore(wrap, scroll);
        wrap.appendChild(scroll);

        var btnL = document.createElement('button');
        btnL.className = 'scroll-arrow scroll-arrow--left';
        btnL.setAttribute('aria-label', 'Scroll left');
        btnL.innerHTML = CHEVRON_L;

        var btnR = document.createElement('button');
        btnR.className = 'scroll-arrow scroll-arrow--right';
        btnR.setAttribute('aria-label', 'Scroll right');
        btnR.innerHTML = CHEVRON_R;

        wrap.appendChild(btnL);
        wrap.appendChild(btnR);

        var amount = 216 * 3;

        function update() {
            var atStart = scroll.scrollLeft <= 2;
            var atEnd   = scroll.scrollLeft + scroll.clientWidth >= scroll.scrollWidth - 2;
            btnL.style.opacity       = atStart ? '0' : '1';
            btnL.style.pointerEvents = atStart ? 'none' : '';
            btnR.style.opacity       = atEnd ? '0' : '1';
            btnR.style.pointerEvents = atEnd ? 'none' : '';
        }

        btnL.addEventListener('click', function () { scroll.scrollBy({ left: -amount, behavior: 'smooth' }); });
        btnR.addEventListener('click', function () { scroll.scrollBy({ left:  amount, behavior: 'smooth' }); });
        scroll.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update, { passive: true });
        update();
    });
}());
</script>

<script>
(function () {
    var CURRENCY = '<?= $_SESSION['currency'] ?? 'USD' ?>';
    var KHR_RATE = <?= KHR_RATE ?>;

    function fmtPrice(usd) {
        if (CURRENCY === 'KHR') return '៛' + Math.round(usd * KHR_RATE).toLocaleString();
        return '$' + parseFloat(usd).toFixed(2);
    }

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function cardHtml(p) {
        var photo = p.photo
            ? '<img src="/uploads/' + p.photo + '" alt="" class="card-photo">'
            : '<div class="card-photo card-photo--empty"></div>';
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
            + '</div></a>';
    }

    try {
        var ids = JSON.parse(localStorage.getItem('teepsaa_rv') || '[]');
        if (!ids.length) return;
        fetch('/api/recently-viewed/?ids=' + ids.slice(0, 8).join(','))
            .then(function (r) { return r.json(); })
            .then(function (products) {
                if (!products.length) return;
                var scroll  = document.getElementById('recently-viewed-scroll');
                var section = document.getElementById('recently-viewed-section');
                products.forEach(function (p) { scroll.insertAdjacentHTML('beforeend', cardHtml(p)); });
                section.style.display = '';
            });
    } catch (e) {}
})();
</script>
</body>
</html>
