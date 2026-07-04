<?php
session_start();
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: /search/');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM businesses WHERE id = ? AND approved = 1');
$stmt->execute([$id]);
$business = $stmt->fetch();

if (!$business) {
    header('Location: /search/');
    exit;
}

$stmt = $pdo->prepare('SELECT id, filename FROM photos WHERE business_id = ? ORDER BY id ASC');
$stmt->execute([$id]);
$photos = $stmt->fetchAll();

$stmt = $pdo->prepare('
    SELECT p.id, p.name, p.name_km, p.description, p.description_km, p.price, p.sale_price, p.sale_ends_at, p.stock,
           pp.filename AS photo,
           COALESCE(rv.avg_rating, 0) AS avg_rating,
           COALESCE(rv.review_count, 0) AS review_count
    FROM products p
    LEFT JOIN product_photos pp ON pp.product_id = p.id AND pp.is_primary = 1
    LEFT JOIN (SELECT product_id, AVG(rating) AS avg_rating, COUNT(*) AS review_count FROM reviews GROUP BY product_id) rv ON rv.product_id = p.id
    WHERE p.business_id = ? AND p.active = 1 AND p.archived = 0
    ORDER BY p.name ASC
');
$stmt->execute([$id]);
$products = $stmt->fetchAll();

$bizRating = $pdo->prepare('SELECT COALESCE(AVG(rating), 0) AS avg_rating, COUNT(*) AS review_count FROM reviews WHERE business_id = ?');
$bizRating->execute([$id]);
$bizRatingRow = $bizRating->fetch();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($business['name']) ?> — teepsaa</title>
    <?php
        require_once __DIR__ . '/../config/seo.php';
        $bizPhoto = $photos[0]['filename'] ?? '';
        $bizDesc  = !empty($business['description'])
            ? $business['name'] . ' — ' . $business['description']
            : 'Shop ' . $business['name'] . ' on teepsaa. Browse products and order for delivery in Phnom Penh.';
        echo seo_meta(
            $business['name'] . ' — teepsaa',
            $bizDesc,
            $bizPhoto,
            'https://teepsaa.com/business/?id=' . $business['id']
        );
    ?>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/business/business.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <?php if ($business['banner']): ?>
    <div class="business-banner">
        <img src="/uploads/<?= htmlspecialchars($business['banner']) ?>" alt="">
    </div>
    <?php endif; ?>

    <div class="store-header">
        <h1 class="store-name"><?= htmlspecialchars(lang_field($business, 'name')) ?></h1>
        <?php if ((int)$bizRatingRow['review_count'] > 0): ?>
        <p class="store-rating">★ <?= number_format((float)$bizRatingRow['avg_rating'], 1) ?> <span class="store-rating-count">(<?= (int)$bizRatingRow['review_count'] ?> <?= (int)$bizRatingRow['review_count'] === 1 ? $t['store_review'] : $t['store_reviews'] ?>)</span></p>
        <?php endif; ?>
        <?php if (lang_field($business, 'description')): ?>
            <p class="store-desc"><?= htmlspecialchars(lang_field($business, 'description')) ?></p>
        <?php endif; ?>
    </div>

    <?php if (!empty($photos)): ?>
    <div class="store-gallery">
        <?php foreach ($photos as $ph): ?>
            <img src="/uploads/<?= htmlspecialchars($ph['filename']) ?>" alt="">
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($products)): ?>
    <section class="products-section">
        <h2><?= $t['vendor_products'] ?></h2>
        <div class="product-grid">
            <?php foreach ($products as $p): ?>
            <a href="/product/?id=<?= $p['id'] ?>" class="product-card">
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
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
