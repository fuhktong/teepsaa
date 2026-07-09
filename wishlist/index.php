<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/seo.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'buyer') {
    header('Location: /login-buyer/');
    exit;
}

$buyerId = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare('
    SELECT p.id, p.public_id, p.name, p.name_km, p.price, p.sale_price, p.sale_ends_at, p.stock, p.active, p.archived,
           b.id AS business_id, b.name AS business_name, b.name_km AS business_name_km, b.approved,
           pp.filename AS photo,
           COALESCE(rv.avg_rating, 0) AS avg_rating,
           COALESCE(rv.review_count, 0) AS review_count,
           w.created_at AS saved_at
    FROM wishlists w
    JOIN products p ON p.id = w.product_id
    JOIN businesses b ON b.id = p.business_id
    LEFT JOIN product_photos pp ON pp.product_id = p.id AND pp.is_primary = 1
    LEFT JOIN (SELECT product_id, AVG(rating) AS avg_rating, COUNT(*) AS review_count FROM reviews GROUP BY product_id) rv ON rv.product_id = p.id
    WHERE w.buyer_user_id = ?
    ORDER BY w.created_at DESC
');
$stmt->execute([$buyerId]);
$items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wishlist — teepsaa</title>
    <?= seo_meta('Wishlist — teepsaa', '', '', 'https://teepsaa.com/wishlist/') ?>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/wishlist/wishlist.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main class="wishlist-main">
    <h1 class="wishlist-title"><?= $t['wishlist_title'] ?></h1>

    <?php if (empty($items)): ?>
        <div class="wishlist-empty">
            <p><?= $t['wishlist_empty'] ?></p>
            <a href="/search/" class="btn-browse"><?= $t['wishlist_browse'] ?></a>
        </div>
    <?php else: ?>
        <div class="wishlist-grid">
            <?php foreach ($items as $p): ?>
                <?php
                    $unavailable = !$p['active'] || $p['archived'] || !$p['approved'] || $p['stock'] < 1;
                    $photo = $p['photo']
                        ? '<img src="/uploads/' . htmlspecialchars($p['photo']) . '" alt="" class="wl-card-photo">'
                        : '<div class="wl-card-photo wl-card-photo--empty"></div>';
                    $rating = ($p['review_count'] > 0)
                        ? '<span class="wl-card-rating">★ ' . number_format((float)$p['avg_rating'], 1) . ' (' . (int)$p['review_count'] . ')</span>'
                        : '';
                ?>
                <div class="wl-card<?= $unavailable ? ' wl-card--unavailable' : '' ?>">
                    <a href="/product/?id=<?= htmlspecialchars($p['public_id']) ?>" class="wl-card-inner">
                        <?= $photo ?>
                        <div class="wl-card-body">
                            <strong class="wl-card-name"><?= htmlspecialchars(lang_field($p, 'name')) ?></strong>
                            <span class="wl-card-price"><?= price_html($p) ?></span>
                            <span class="wl-card-seller"><?= htmlspecialchars(pick_lang($p['business_name'], $p['business_name_km'] ?? null)) ?></span>
                            <?= $rating ?>
                            <?php if ($unavailable): ?>
                                <span class="wl-card-unavail"><?= $t['wishlist_unavailable'] ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                    <button class="wl-remove" data-product-id="<?= (int)$p['id'] ?>" aria-label="Remove from wishlist">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

<script>
document.querySelectorAll('.wl-remove').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var card = this.closest('.wl-card');
        var productId = this.dataset.productId;
        fetch('/api/wishlist/toggle.php', {
            method: 'POST',
            credentials: 'same-origin',
            body: new URLSearchParams({ product_id: productId })
        }).then(function () {
            card.style.opacity = '0';
            card.style.transition = 'opacity 0.2s';
            setTimeout(function () { card.remove(); }, 200);
        });
    });
});
</script>

</body>
</html>
