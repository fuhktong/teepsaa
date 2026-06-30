<?php
session_start();
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: /search/');
    exit;
}

$stmt = $pdo->prepare('
    SELECT p.*, b.id AS business_id, b.name AS business_name
    FROM products p
    JOIN businesses b ON b.id = p.business_id
    WHERE p.id = ? AND p.active = 1 AND p.archived = 0 AND b.approved = 1
');
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: /search/');
    exit;
}

$galleryStmt = $pdo->prepare('SELECT filename FROM product_photos WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC, id ASC');
$galleryStmt->execute([$id]);
$galleryPhotos = $galleryStmt->fetchAll();

$variantStmt = $pdo->prepare('SELECT id, label, stock, price_override FROM product_variants WHERE product_id = ? ORDER BY sort_order ASC, id ASC');
$variantStmt->execute([$id]);
$variants = $variantStmt->fetchAll();

$optionTypes = [];
$variantMap  = []; // value_ids (sorted CSV) → row data
if (!empty($variants)) {
    $otStmt = $pdo->prepare('SELECT id, name FROM product_option_types WHERE product_id = ? ORDER BY display_order, id');
    $otStmt->execute([$id]);
    $optionTypes = $otStmt->fetchAll();
    if (!empty($optionTypes)) {
        foreach ($optionTypes as &$ot) {
            $ovStmt = $pdo->prepare('SELECT id, label FROM product_option_values WHERE option_type_id = ? ORDER BY display_order, id');
            $ovStmt->execute([$ot['id']]);
            $ot['values'] = $ovStmt->fetchAll();
        }
        unset($ot);
        $vMapStmt = $pdo->prepare("
            SELECT pv.id AS variant_id, pv.stock, pv.price_override,
                   GROUP_CONCAT(pvo.option_value_id ORDER BY pvo.option_value_id ASC SEPARATOR ',') AS value_ids
            FROM product_variants pv
            JOIN product_variant_options pvo ON pvo.variant_id = pv.id
            WHERE pv.product_id = ?
            GROUP BY pv.id, pv.stock, pv.price_override
        ");
        $vMapStmt->execute([$id]);
        foreach ($vMapStmt->fetchAll() as $row) {
            $variantMap[$row['value_ids']] = $row;
        }
    }
}
$hasOptionTypes = !empty($optionTypes);

$cartSuccess = $_SESSION['cart_success'] ?? '';
$cartError   = $_SESSION['cart_error'] ?? '';
unset($_SESSION['cart_success'], $_SESSION['cart_error']);

$isBuyer = isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'buyer';

$rSummary = $pdo->prepare('SELECT COALESCE(AVG(rating), 0) AS avg_rating, COUNT(*) AS review_count FROM reviews WHERE product_id = ?');
$rSummary->execute([$id]);
$rSummaryRow = $rSummary->fetch();
$avgRating   = (float)$rSummaryRow['avg_rating'];
$reviewCount = (int)$rSummaryRow['review_count'];

$rStmt = $pdo->prepare('
    SELECT r.rating, r.comment, r.created_at, b.name AS buyer_name
    FROM reviews r
    JOIN buyers b ON b.id = r.buyer_id
    WHERE r.product_id = ?
    ORDER BY r.created_at DESC
');
$rStmt->execute([$id]);
$reviews = $rStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> — teepsaa</title>
    <?php
        require_once __DIR__ . '/../config/seo.php';
        $seoImg = $galleryPhotos[0]['filename'] ?? '';
        echo seo_meta(
            $product['name'] . ' — teepsaa',
            $product['description'] ?? '',
            $seoImg,
            'https://teepsaa.com/product/?id=' . $product['id']
        );
    ?>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/product/product.css">
    <style>
    .variant-selector { margin-bottom: 0.85rem; }
    .variant-selector-label { font-size: 0.8rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.4rem; }
    .variant-options { display: flex; flex-wrap: wrap; gap: 0.4rem; }
    .variant-opt { display: inline-block; }
    .variant-opt input[type="radio"] { position: absolute; opacity: 0; width: 0; height: 0; }
    .variant-opt-label {
        display: block;
        padding: 0.35rem 0.85rem;
        border: 1.5px solid var(--border-strong);
        border-radius: var(--radius-sm);
        font-size: 0.875rem;
        cursor: pointer;
        user-select: none;
        transition: border-color 0.1s, background 0.1s;
        color: var(--text-soft);
    }
    .variant-opt input[type="radio"]:checked + .variant-opt-label {
        border-color: var(--primary);
        background: var(--primary);
        color: #fff;
    }
    .variant-opt--oos .variant-opt-label {
        color: var(--border-strong);
        border-color: #f3f4f6;
        background: #f9fafb;
        cursor: not-allowed;
        text-decoration: line-through;
    }
    .cart-select-hint {
        color: var(--error-fg);
        font-size: 0.85rem;
        font-weight: 600;
        margin-top: 0.6rem;
    }
    .variant-selector--error .variant-selector-label { color: var(--error-fg); }
    .variant-selector--error .variant-opt-label { border-color: var(--error-fg); }
    </style>
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <div class="product-layout">
        <div class="product-photo-wrap">
            <?php $allPhotos = array_column($galleryPhotos, 'filename'); ?>
            <?php if (!empty($allPhotos)): ?>
                <img src="/uploads/<?= htmlspecialchars($allPhotos[0]) ?>" alt="" class="product-main-photo" id="product-main-img">
                <?php if (count($allPhotos) > 1): ?>
                <div class="product-thumbs">
                    <?php foreach ($allPhotos as $i => $fn): ?>
                    <img src="/uploads/<?= htmlspecialchars($fn) ?>" alt=""
                         class="product-thumb <?= $i === 0 ? 'product-thumb--active' : '' ?>"
                         data-src="/uploads/<?= htmlspecialchars($fn) ?>">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="product-main-photo product-main-photo--empty"></div>
            <?php endif; ?>
        </div>

        <div class="product-info">
            <p class="product-seller">Sold by <a href="/business/?id=<?= $product['business_id'] ?>"><?= htmlspecialchars($product['business_name']) ?></a></p>
            <h1 class="product-name"><?= htmlspecialchars($product['name']) ?></h1>
            <p class="product-price" id="product-price"><?= price_html($product) ?></p>

            <?php if ($product['description']): ?>
                <p class="product-desc"><?= nl2br(htmlspecialchars($product['description'])) ?></p>
            <?php endif; ?>

            <?php
                $isWishlisted = false;
                if ($isBuyer) {
                    $wStmt = $pdo->prepare('SELECT id FROM wishlists WHERE buyer_user_id = ? AND product_id = ?');
                    $wStmt->execute([$_SESSION['user_id'], $product['id']]);
                    $isWishlisted = (bool)$wStmt->fetch();
                }
            ?>
            <?php if ($isBuyer || !isset($_SESSION['user_id'])): ?>
            <form method="POST" action="/cart/add.php" class="cart-form" id="cart-form">
                <?= csrf_input() ?>
                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                <input type="hidden" name="redirect" value="/product/?id=<?= $product['id'] ?>">

                <?php if (!empty($variants)): ?>
                <?php if ($hasOptionTypes): ?>
                    <?php foreach ($optionTypes as $ot): ?>
                    <div class="variant-selector">
                        <div class="variant-selector-label"><?= htmlspecialchars($ot['name']) ?></div>
                        <div class="variant-options" data-type-id="<?= (int)$ot['id'] ?>">
                            <?php foreach ($ot['values'] as $val): ?>
                            <label class="variant-opt">
                                <input type="radio" name="opt_type_<?= (int)$ot['id'] ?>" value="<?= (int)$val['id'] ?>" data-val-id="<?= (int)$val['id'] ?>">
                                <span class="variant-opt-label"><?= htmlspecialchars($val['label']) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <input type="hidden" name="variant_id" id="selected_variant_id" value="">
                <?php else: ?>
                    <div class="variant-selector">
                        <div class="variant-selector-label">Variants</div>
                        <div class="variant-options">
                            <?php foreach ($variants as $v): ?>
                            <label class="variant-opt <?= $v['stock'] < 1 ? 'variant-opt--oos' : '' ?>">
                                <input type="radio" name="variant_id" value="<?= $v['id'] ?>"
                                       data-stock="<?= (int)$v['stock'] ?>"
                                       data-price="<?= $v['price_override'] !== null ? htmlspecialchars($v['price_override']) : '' ?>"
                                       <?= $v['stock'] < 1 ? 'disabled' : '' ?>>
                                <span class="variant-opt-label"><?= htmlspecialchars($v['label']) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php endif; ?>

                <p class="product-stock" id="stock-display">
                    <?php if (!empty($variants)): ?>
                        <?= $hasOptionTypes ? 'Select options' : 'Select a variant' ?>
                    <?php elseif ($product['stock'] > 0): ?>
                        In stock
                    <?php else: ?>
                        Out of stock
                    <?php endif; ?>
                </p>

                <?php if ($isBuyer): ?>
                    <?php $outOfStock = empty($variants) && $product['stock'] < 1; ?>
                    <button type="submit" class="btn-add-cart <?= $outOfStock ? 'btn-add-cart--disabled' : (!empty($variants) ? 'btn-add-cart--pending' : '') ?>"
                            id="add-cart-btn"
                            <?= $outOfStock ? 'disabled' : '' ?>>
                        Add to cart
                    </button>
                    <p class="cart-select-hint" id="cart-select-hint" style="display:none"></p>
                <?php else: ?>
                    <a href="/login-buyer/" class="btn-login-to-buy">Login to buy</a>
                <?php endif; ?>
            </form>
            <?php endif; ?>

            <?php if ($isBuyer): ?>
            <button class="wishlist-heart <?= $isWishlisted ? 'wishlist-heart--saved' : '' ?>"
                    id="wishlist-btn" data-product-id="<?= $product['id'] ?>" type="button">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="<?= $isWishlisted ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                <span id="wishlist-label"><?= $isWishlisted ? 'Saved' : 'Save' ?></span>
            </button>
            <?php endif; ?>

            <?php if ($cartSuccess): ?>
                <div class="flash flash--success flash--btn">Added to cart — <a href="/cart/" class="flash-link">Proceed to checkout</a></div>
            <?php elseif ($cartError): ?>
                <div class="flash flash--error flash--btn"><?= htmlspecialchars($cartError) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Lightbox -->
    <div id="lightbox" class="lightbox" aria-hidden="true">
        <button class="lightbox-close" id="lightbox-close" aria-label="Close">&times;</button>
        <button class="lightbox-prev" id="lightbox-prev" aria-label="Previous">&#8249;</button>
        <button class="lightbox-next" id="lightbox-next" aria-label="Next">&#8250;</button>
        <img class="lightbox-img" id="lightbox-img" src="" alt="">
        <div class="lightbox-counter" id="lightbox-counter"></div>
    </div>

    <div class="product-reviews">
        <h2 class="product-reviews-heading">Reviews</h2>
        <?php if ($reviewCount === 0): ?>
        <p class="product-reviews-empty">No reviews yet.</p>
        <?php else: ?>
        <div class="product-reviews-summary">
            <span class="product-reviews-stars-filled"><?= str_repeat('★', (int)round($avgRating)) . str_repeat('☆', 5 - (int)round($avgRating)) ?></span>
            <span class="product-reviews-avg"><?= number_format($avgRating, 1) ?></span>
            <span class="product-reviews-count"><?= $reviewCount ?> <?= $reviewCount === 1 ? 'review' : 'reviews' ?></span>
        </div>
        <div class="product-reviews-list">
            <?php foreach ($reviews as $r):
                $nameParts   = explode(' ', trim($r['buyer_name']));
                $displayName = $nameParts[0] . (count($nameParts) > 1 ? ' ' . strtoupper(substr(end($nameParts), 0, 1)) . '.' : '');
            ?>
            <div class="product-review">
                <div class="product-review-meta">
                    <span class="product-review-stars"><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?></span>
                    <span class="product-review-author"><?= htmlspecialchars($displayName) ?></span>
                    <span class="product-review-date"><?= date('M j, Y', strtotime($r['created_at'])) ?></span>
                </div>
                <?php if ($r['comment']): ?>
                <p class="product-review-comment"><?= htmlspecialchars($r['comment']) ?></p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

<script>
(function () {
    var mainImg = document.getElementById('product-main-img');
    if (mainImg) {
        document.querySelectorAll('.product-thumb').forEach(function (thumb) {
            thumb.addEventListener('click', function () {
                mainImg.src = this.dataset.src;
                document.querySelectorAll('.product-thumb').forEach(function (t) { t.classList.remove('product-thumb--active'); });
                this.classList.add('product-thumb--active');
            });
        });
    }
})();
</script>
<script>
(function () {
    var photos = <?= json_encode(array_values($allPhotos)) ?>;
    if (!photos.length) return;

    var lb      = document.getElementById('lightbox');
    var lbImg   = document.getElementById('lightbox-img');
    var lbCount = document.getElementById('lightbox-counter');
    var current = 0;

    if (photos.length === 1) lb.classList.add('lightbox--single');

    function show(idx) {
        current = ((idx % photos.length) + photos.length) % photos.length;
        lbImg.src = '/uploads/' + photos[current];
        lbCount.textContent = (current + 1) + ' / ' + photos.length;
        lb.classList.add('is-open');
        lb.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function close() {
        lb.classList.remove('is-open');
        lb.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    var mainImg = document.getElementById('product-main-img');
    if (mainImg) {
        mainImg.addEventListener('click', function () {
            var fn  = mainImg.src.split('/').pop();
            var idx = photos.indexOf(fn);
            show(idx >= 0 ? idx : 0);
        });
    }

    document.getElementById('lightbox-close').addEventListener('click', close);
    document.getElementById('lightbox-prev').addEventListener('click', function () { show(current - 1); });
    document.getElementById('lightbox-next').addEventListener('click', function () { show(current + 1); });
    lb.addEventListener('click', function (e) { if (e.target === lb) close(); });

    document.addEventListener('keydown', function (e) {
        if (!lb.classList.contains('is-open')) return;
        if (e.key === 'Escape')     close();
        if (e.key === 'ArrowLeft')  show(current - 1);
        if (e.key === 'ArrowRight') show(current + 1);
    });
})();
</script>
<?php if (!empty($variants)): ?>
<script>
<?php if ($hasOptionTypes): ?>
(function () {
    var basePrice    = <?= (float)$product['price'] ?>;
    var salePrice    = <?= $product['sale_price'] !== null ? (float)$product['sale_price'] : 'null' ?>;
    var saleEndsAt   = <?= ($product['sale_ends_at'] !== null) ? json_encode($product['sale_ends_at']) : 'null' ?>;
    var saleActive   = salePrice !== null && saleEndsAt !== null && (new Date(saleEndsAt).getTime() / 1000) > Date.now() / 1000;
    var stockEl      = document.getElementById('stock-display');
    var priceEl      = document.getElementById('product-price');
    var addBtn       = document.getElementById('add-cart-btn');
    var variantIn    = document.getElementById('selected_variant_id');

    function fmtPrice(usd) { return '$' + usd.toFixed(2); }
    function setPriceHtml(override) {
        if (override !== null) {
            priceEl.innerHTML = fmtPrice(override);
        } else if (saleActive) {
            priceEl.innerHTML = '<span class="price-sale">' + fmtPrice(salePrice) + '</span>'
                + '<span class="price-original">' + fmtPrice(basePrice) + '</span>';
        } else {
            priceEl.innerHTML = fmtPrice(basePrice);
        }
    }

    var VARIANT_MAP = <?= json_encode(array_map(function($row) {
        return ['variant_id' => (int)$row['variant_id'], 'stock' => (int)$row['stock'],
                'price_override' => $row['price_override'], 'value_ids' => $row['value_ids']];
    }, array_values($variantMap))) ?>;

    var variantLookup = {};
    VARIANT_MAP.forEach(function(v){ variantLookup[v.value_ids] = v; });

    var typeIds    = <?= json_encode(array_column($optionTypes, 'id')) ?>;
    var selections = {};

    function comboKey() {
        return Object.values(selections).map(Number).sort(function(a,b){return a-b;}).join(',');
    }

    function allSelected() {
        return typeIds.every(function(tid){ return selections[tid] != null; });
    }

    function setPending(p) { if (addBtn) addBtn.classList.toggle('btn-add-cart--pending', p); }

    function updateState() {
        if (!allSelected()) {
            stockEl.textContent = 'Select options';
            if (variantIn) variantIn.value = '';
            setPending(true);
            return;
        }
        var key   = comboKey();
        var match = variantLookup[key];
        if (!match) {
            stockEl.textContent = 'Combination not available';
            if (variantIn) variantIn.value = '';
            setPending(true);
            return;
        }
        if (variantIn) variantIn.value = match.variant_id;
        stockEl.textContent = match.stock > 0 ? 'In stock' : 'Out of stock';
        var override = match.price_override !== null ? parseFloat(match.price_override) : null;
        setPriceHtml(override);
        setPending(match.stock < 1);
    }

    var formEl     = document.getElementById('cart-form');
    var hintEl     = document.getElementById('cart-select-hint');
    var TYPE_NAMES = <?= json_encode(array_column($optionTypes, 'name', 'id')) ?>;

    document.querySelectorAll('[data-type-id]').forEach(function(optDiv) {
        var tid = parseInt(optDiv.dataset.typeId);
        optDiv.querySelectorAll('input[type="radio"]').forEach(function(r) {
            r.addEventListener('change', function() {
                selections[tid] = parseInt(this.dataset.valId);
                var sel = optDiv.closest('.variant-selector');
                if (sel) sel.classList.remove('variant-selector--error');
                if (hintEl) hintEl.style.display = 'none';
                updateState();
            });
        });
    });

    if (formEl) formEl.addEventListener('submit', function(e) {
        if (hintEl) hintEl.style.display = 'none';
        document.querySelectorAll('.variant-selector--error').forEach(function(s){ s.classList.remove('variant-selector--error'); });

        var missing = typeIds.filter(function(tid){ return selections[tid] == null; });
        if (missing.length) {
            e.preventDefault();
            var names = missing.map(function(tid){ return TYPE_NAMES[tid]; });
            if (hintEl) { hintEl.textContent = 'Please select ' + names.join(' and ') + '.'; hintEl.style.display = ''; }
            missing.forEach(function(tid){
                var div = document.querySelector('[data-type-id="' + tid + '"]');
                var sel = div && div.closest('.variant-selector');
                if (sel) sel.classList.add('variant-selector--error');
            });
            return;
        }
        var match = variantLookup[comboKey()];
        if (!match) {
            e.preventDefault();
            if (hintEl) { hintEl.textContent = "That combination isn't available."; hintEl.style.display = ''; }
            return;
        }
        if (match.stock < 1) {
            e.preventDefault();
            if (hintEl) { hintEl.textContent = 'Sorry, that option is out of stock.'; hintEl.style.display = ''; }
        }
    });

    updateState();
})();
<?php else: ?>
(function () {
    var basePrice  = <?= (float)$product['price'] ?>;
    var salePrice  = <?= $product['sale_price'] !== null ? (float)$product['sale_price'] : 'null' ?>;
    var saleEndsAt = <?= ($product['sale_ends_at'] !== null) ? json_encode($product['sale_ends_at']) : 'null' ?>;
    var saleActive = salePrice !== null && saleEndsAt !== null && (new Date(saleEndsAt).getTime() / 1000) > Date.now() / 1000;
    var stockEl    = document.getElementById('stock-display');
    var priceEl    = document.getElementById('product-price');
    var addBtn     = document.getElementById('add-cart-btn');

    function fmtPrice(usd) { return '$' + usd.toFixed(2); }
    function setPriceHtml(override) {
        if (override !== null) {
            priceEl.innerHTML = fmtPrice(override);
        } else if (saleActive) {
            priceEl.innerHTML = '<span class="price-sale">' + fmtPrice(salePrice) + '</span>'
                + '<span class="price-original">' + fmtPrice(basePrice) + '</span>';
        } else {
            priceEl.innerHTML = fmtPrice(basePrice);
        }
    }

    var formEl = document.getElementById('cart-form');
    var hintEl = document.getElementById('cart-select-hint');
    var groupEl = document.querySelector('.variant-selector');

    document.querySelectorAll('input[name="variant_id"]').forEach(function (r) {
        r.addEventListener('change', function () {
            var stock    = parseInt(this.dataset.stock, 10);
            var rawPrice = this.dataset.price;
            stockEl.textContent = stock > 0 ? 'In stock' : 'Out of stock';
            setPriceHtml(rawPrice !== '' ? parseFloat(rawPrice) : null);
            if (addBtn) addBtn.classList.toggle('btn-add-cart--pending', stock < 1);
            if (groupEl) groupEl.classList.remove('variant-selector--error');
            if (hintEl) hintEl.style.display = 'none';
        });
    });

    if (formEl) formEl.addEventListener('submit', function (e) {
        var checked = document.querySelector('input[name="variant_id"]:checked');
        if (!checked) {
            e.preventDefault();
            if (hintEl) { hintEl.textContent = 'Please select a variant.'; hintEl.style.display = ''; }
            if (groupEl) groupEl.classList.add('variant-selector--error');
            return;
        }
        if (parseInt(checked.dataset.stock, 10) < 1) {
            e.preventDefault();
            if (hintEl) { hintEl.textContent = 'Sorry, that variant is out of stock.'; hintEl.style.display = ''; }
        }
    });
})();
<?php endif; ?>
</script>
<?php endif; ?>
<script>
(function () {
    var id = <?= $product['id'] ?>;
    try {
        var key  = 'teepsaa_rv';
        var list = JSON.parse(localStorage.getItem(key) || '[]');
        list = list.filter(function (x) { return x !== id; });
        list.unshift(id);
        localStorage.setItem(key, JSON.stringify(list.slice(0, 20)));
    } catch (e) {}
})();
</script>
<style>
/* ── Lightbox ── */
.lightbox {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.92);
    z-index: 2000;
    align-items: center;
    justify-content: center;
}
.lightbox.is-open { display: flex; }
.lightbox-img {
    max-width: 90vw;
    max-height: 88vh;
    object-fit: contain;
    border-radius: var(--radius-sm);
    display: block;
    user-select: none;
}
.lightbox-close {
    position: absolute;
    top: 1rem;
    right: 1.25rem;
    background: none;
    border: none;
    color: #fff;
    font-size: 2.25rem;
    line-height: 1;
    cursor: pointer;
    padding: 0.2rem 0.5rem;
    opacity: 0.75;
}
.lightbox-close:hover { opacity: 1; }
.lightbox-prev,
.lightbox-next {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(255,255,255,0.12);
    border: none;
    color: #fff;
    font-size: 2.5rem;
    line-height: 1;
    cursor: pointer;
    padding: 0.4rem 0.7rem;
    border-radius: var(--radius-sm);
    opacity: 0.75;
}
.lightbox-prev:hover,
.lightbox-next:hover { opacity: 1; background: rgba(255,255,255,0.22); }
.lightbox-prev { left: 1rem; }
.lightbox-next { right: 1rem; }
.lightbox-counter {
    position: absolute;
    bottom: 1.25rem;
    left: 50%;
    transform: translateX(-50%);
    color: rgba(255,255,255,0.55);
    font-size: 0.85rem;
    letter-spacing: 0.04em;
}
.lightbox--single .lightbox-prev,
.lightbox--single .lightbox-next,
.lightbox--single .lightbox-counter { display: none; }
#product-main-img { cursor: zoom-in; }
</style>
<style>
.wishlist-heart {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    margin-top: 0.6rem;
    background: none;
    border: 1.5px solid var(--border-strong);
    border-radius: var(--radius-sm);
    padding: 0.45rem 0.9rem;
    font-size: 0.875rem;
    color: var(--text-muted);
    cursor: pointer;
    font-family: inherit;
    transition: border-color 0.15s, color 0.15s;
}
.wishlist-heart:hover,
.wishlist-heart--saved {
    border-color: #e53935;
    color: #e53935;
}
</style>
<script>
(function () {
    var btn   = document.getElementById('wishlist-btn');
    var label = document.getElementById('wishlist-label');
    if (!btn) return;
    btn.addEventListener('click', function () {
        var pid = this.dataset.productId;
        fetch('/api/wishlist/toggle.php', {
            method: 'POST',
            credentials: 'same-origin',
            body: new URLSearchParams({ product_id: pid })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            var saved = data.wishlisted;
            btn.classList.toggle('wishlist-heart--saved', saved);
            label.textContent = saved ? 'Saved' : 'Save';
            btn.querySelector('svg path').setAttribute('fill', saved ? 'currentColor' : 'none');
        })
        .catch(function () {});
    });
})();
</script>
</body>
</html>
