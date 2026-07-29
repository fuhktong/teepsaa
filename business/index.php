<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';

$publicId = $_GET['id'] ?? '';
if ($publicId === '') {
    header('Location: /search/');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM businesses WHERE public_id = ? AND approved = 1');
$stmt->execute([$publicId]);
$business = $stmt->fetch();

if (!$business) {
    header('Location: /search/');
    exit;
}

$id = (int)$business['id'];

$stmt = $pdo->prepare('
    SELECT p.id, p.public_id, p.name, p.name_km, p.description, p.description_km, p.price, p.sale_price, p.sale_ends_at, p.stock,
           p.storefront_order,
           pp.filename AS photo,
           COALESCE(rv.avg_rating, 0) AS avg_rating,
           COALESCE(rv.review_count, 0) AS review_count
    FROM products p
    LEFT JOIN product_photos pp ON pp.product_id = p.id AND pp.is_primary = 1
    LEFT JOIN (SELECT product_id, AVG(rating) AS avg_rating, COUNT(*) AS review_count FROM reviews GROUP BY product_id) rv ON rv.product_id = p.id
    WHERE p.business_id = ? AND p.active = 1 AND p.archived = 0
    ORDER BY (p.storefront_order IS NULL), p.storefront_order ASC, p.name ASC
');
$stmt->execute([$id]);
$products = $stmt->fetchAll();

$bizRating = $pdo->prepare('SELECT COALESCE(AVG(rating), 0) AS avg_rating, COUNT(*) AS review_count FROM reviews WHERE business_id = ?');
$bizRating->execute([$id]);
$bizRatingRow = $bizRating->fetch();

// Featured product — the one the vendor ticked as "featured" (at most one per
// shop, enforced in products/feature.php). Rendered as the hero tile up top.
$featStmt = $pdo->prepare('
    SELECT p.id, p.public_id, p.name, p.name_km, p.description, p.description_km,
           p.price, p.sale_price, p.sale_ends_at, p.stock,
           pp.filename AS photo,
           COALESCE(rv.avg_rating, 0) AS avg_rating,
           COALESCE(rv.review_count, 0) AS review_count
    FROM products p
    LEFT JOIN product_photos pp ON pp.product_id = p.id AND pp.is_primary = 1
    LEFT JOIN (SELECT product_id, AVG(rating) AS avg_rating, COUNT(*) AS review_count FROM reviews GROUP BY product_id) rv ON rv.product_id = p.id
    WHERE p.business_id = ? AND p.is_featured = 1 AND p.active = 1 AND p.archived = 0
    LIMIT 1
');
$featStmt->execute([$id]);
$featured   = $featStmt->fetch();
$featuredId = $featured ? (int)$featured['id'] : 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($business['name']) ?> — teepsaa</title>
    <?php
        require_once __DIR__ . '/../config/seo.php';
        $bizPhoto = $featured['photo'] ?? ($products[0]['photo'] ?? '');
        $bizDesc  = !empty($business['description'])
            ? $business['name'] . ' — ' . $business['description']
            : 'Shop ' . $business['name'] . ' on teepsaa. Browse products and order for delivery in Phnom Penh.';
        echo seo_meta(
            $business['name'] . ' — teepsaa',
            $bizDesc,
            $bizPhoto,
            'https://teepsaa.com/business/?id=' . $business['public_id']
        );
    ?>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/business/business.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<?php
    $storeName   = htmlspecialchars(lang_field($business, 'name'));
    $storeDesc   = lang_field($business, 'description');
    $reviewCount = (int)$bizRatingRow['review_count'];
    $reviewWord  = $reviewCount === 1 ? $t['store_review'] : $t['store_reviews'];
    // Products for the grid: everything except the featured one (it has its own hero).
    $gridProducts = array_values(array_filter($products, fn($p) => (int)$p['id'] !== $featuredId));
?>

<?php if ($business['banner']): ?>
<!-- Full-bleed banner: sits outside <main> (like the homepage carousel) so it
     spans the whole viewport. Store name + rating are overlaid on a scrim. -->
<div class="business-banner business-banner--hero">
    <img src="/uploads/<?= htmlspecialchars($business['banner']) ?>" alt="">
    <div class="banner-overlay">
        <div class="banner-overlay-inner">
            <div class="store-eyebrow">
                <span class="banner-badge">✓ <?= $t['store_verified'] ?></span>
                <?php if (!empty($business['city'])): ?>
                <span class="banner-city"><?= htmlspecialchars($business['city']) ?></span>
                <?php endif; ?>
            </div>
            <h1 class="banner-store-name"><?= $storeName ?></h1>
            <?php if ($storeDesc || $reviewCount > 0): ?>
            <div class="banner-store-meta">
                <?php if ($reviewCount > 0): ?>
                <span class="banner-rating">★ <?= number_format((float)$bizRatingRow['avg_rating'], 1) ?> <span class="banner-rating-count">(<?= $reviewCount ?> <?= $reviewWord ?>)</span></span>
                <?php endif; ?>
                <?php if ($storeDesc): ?>
                <?php if ($reviewCount > 0): ?><span class="banner-dot">·</span><?php endif; ?>
                <span class="banner-tagline"><?= htmlspecialchars(mb_strimwidth($storeDesc, 0, 110, '…')) ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<main>
    <?php if (!$business['banner']): ?>
    <div class="store-header">
        <div class="store-eyebrow store-eyebrow--dark">
            <span class="store-badge">✓ <?= $t['store_verified'] ?></span>
            <?php if (!empty($business['city'])): ?>
            <span class="store-city">📍 <?= htmlspecialchars($business['city']) ?></span>
            <?php endif; ?>
        </div>
        <h1 class="store-name"><?= $storeName ?></h1>
        <?php if ($reviewCount > 0): ?>
        <p class="store-header-meta">
            <span class="store-rating">★ <?= number_format((float)$bizRatingRow['avg_rating'], 1) ?> <span class="store-rating-count">(<?= $reviewCount ?> <?= $reviewWord ?>)</span></span>
        </p>
        <?php endif; ?>
        <?php if ($storeDesc): ?>
            <p class="store-desc"><?= htmlspecialchars($storeDesc) ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Storefront column: a narrower centered band so product images read at a
         comfortable size instead of filling the full 1200px content width. -->
    <div class="storefront-inner">
    <?php if ($featured): ?>
    <!-- Featured product hero — the vendor's one "featured" pick. -->
    <section class="featured-section">
        <div class="featured-head">
            <span class="featured-eyebrow"><?= $t['store_featured'] ?></span>
        </div>
        <div class="featured-card">
            <a href="/product/?id=<?= $featured['public_id'] ?>" class="featured-media">
                <?php if (active_sale($featured)): ?><span class="sale-badge"><?= $t['store_sale'] ?></span><?php endif; ?>
                <?php if ($featured['photo']): ?>
                    <img src="/uploads/<?= htmlspecialchars($featured['photo']) ?>" alt="">
                <?php else: ?>
                    <span class="featured-media--empty"></span>
                <?php endif; ?>
            </a>
            <div class="featured-body">
                <a href="/product/?id=<?= $featured['public_id'] ?>" class="featured-name"><?= htmlspecialchars(lang_field($featured, 'name')) ?></a>
                <?php if ((int)$featured['review_count'] > 0): ?>
                <div class="featured-rating">★ <?= number_format((float)$featured['avg_rating'], 1) ?> <span>(<?= (int)$featured['review_count'] ?> <?= (int)$featured['review_count'] === 1 ? $t['store_review'] : $t['store_reviews'] ?>)</span></div>
                <?php endif; ?>
                <?php if (lang_field($featured, 'description')): ?>
                <p class="featured-desc"><?= htmlspecialchars(mb_strimwidth(lang_field($featured, 'description'), 0, 240, '…')) ?></p>
                <?php endif; ?>
                <div class="featured-price"><?= price_html($featured) ?></div>
                <?php if ((int)$featured['stock'] > 0 && (int)$featured['stock'] <= 10): ?>
                <div class="featured-stock"><?= (int)$featured['stock'] ?> <?= $t['store_in_stock'] ?></div>
                <?php elseif ((int)$featured['stock'] <= 0): ?>
                <div class="featured-stock featured-stock--out"><?= $t['product_out_of_stock'] ?></div>
                <?php endif; ?>
                <a href="/product/?id=<?= $featured['public_id'] ?>" class="featured-cta"><?= $t['store_view_product'] ?></a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($gridProducts)): ?>
    <section class="products-section">
        <h2><?= $t['store_shop_all'] ?></h2>
        <div class="product-grid">
            <?php foreach ($gridProducts as $p): ?>
            <a href="/product/?id=<?= $p['public_id'] ?>" class="product-card">
                <?php if (active_sale($p)): ?><span class="sale-badge"><?= $t['store_sale'] ?></span><?php endif; ?>
                <?php if ($p['photo']): ?>
                    <img src="/uploads/<?= htmlspecialchars($p['photo']) ?>" alt="" class="product-photo">
                <?php else: ?>
                    <div class="product-photo product-photo--empty"></div>
                <?php endif; ?>
                <div class="product-body">
                    <strong class="product-name"><?= htmlspecialchars(lang_field($p, 'name')) ?></strong>
                    <?php if (lang_field($p, 'description')): ?>
                        <p class="product-desc"><?= htmlspecialchars(mb_strimwidth(lang_field($p, 'description'), 0, 100, '…')) ?></p>
                    <?php endif; ?>
                    <div class="product-footer">
                        <span class="product-price"><?= price_html($p) ?></span>
                        <?php if ($p['review_count'] > 0): ?>
                        <span class="product-rating">★ <?= number_format($p['avg_rating'], 1) ?> (<?= (int)$p['review_count'] ?>)</span>
                        <?php else: ?>
                        <span class="product-stock"><?= $p['stock'] > 0 ? (int)$p['stock'] . ' ' . $t['store_in_stock'] : $t['product_out_of_stock'] ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
    </div><!-- /.storefront-inner -->
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
