<?php
session_start();
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header('Location: /login-vendor/');
    exit;
}

$userId  = $_SESSION['user_id'];
$success = $_SESSION['product_success'] ?? '';
$error   = $_SESSION['product_error']   ?? '';
unset($_SESSION['product_success'], $_SESSION['product_error']);

$action = $_GET['action'] ?? '';
$tab    = (!$action && ($_GET['tab'] ?? '') === 'archive') ? 'archive' : 'products';

// Categories (always needed for add/edit form)
$allCatsRaw = $pdo->query('SELECT id, parent_id, name, name_km, royalty_rate FROM categories ORDER BY name ASC')->fetchAll();

function buildVendorCatTree(array $cats, $parentId = null): array {
    $branch = [];
    foreach ($cats as $cat) {
        if ($cat['parent_id'] == $parentId) {
            $cat['children'] = buildVendorCatTree($cats, $cat['id']);
            $branch[] = $cat;
        }
    }
    return $branch;
}

function flattenVendorCatTree(array $nodes, int $depth = 0): array {
    $result = [];
    foreach ($nodes as $node) {
        $node['depth'] = $depth;
        $children = $node['children'];
        unset($node['children']);
        $result[] = $node;
        $result = array_merge($result, flattenVendorCatTree($children, $depth + 1));
    }
    return $result;
}

$allFlat    = flattenVendorCatTree(buildVendorCatTree($allCatsRaw));
$parentIds  = array_column(array_filter($allCatsRaw, fn($c) => $c['parent_id'] !== null), 'parent_id');
$categories = array_values(array_filter($allFlat, fn($c) => !in_array($c['id'], $parentIds)));

// Products data (always needed for add/edit; needed on products tab)
$stmt = $pdo->prepare('SELECT id, name, royalty_add_on FROM businesses WHERE user_id = ? AND approved = 1 ORDER BY name ASC');
$stmt->execute([$userId]);
$businesses = $stmt->fetchAll();
$companyAddOn = !empty($businesses) ? (float)$businesses[0]['royalty_add_on'] : 0.0;
$bizIds             = array_column($businesses, 'id');
$vendorRefundCount  = 0;
$refundOrders       = [];
$refundItemsByOrder = [];

// Penalty and notification data
$activePenalties = [];
$unreadNotifs    = [];
if (!empty($bizIds)) {
    $ph = implode(',', array_fill(0, count($bizIds), '?'));

    $stmt = $pdo->prepare("
        SELECT vp.id, vp.rate_increase, vp.end_date
        FROM vendor_penalties vp
        WHERE vp.business_id IN ($ph)
          AND vp.cleared_at IS NULL
          AND vp.start_date <= CURDATE()
          AND (vp.end_date IS NULL OR vp.end_date >= CURDATE())
        ORDER BY vp.start_date DESC
    ");
    $stmt->execute(array_values($bizIds));
    $activePenalties = $stmt->fetchAll();

    // Detect newly expired penalties and create notifications
    $stmt = $pdo->prepare("
        SELECT id, rate_increase, end_date
        FROM vendor_penalties
        WHERE business_id IN ($ph)
          AND cleared_at IS NULL
          AND end_date IS NOT NULL
          AND end_date < CURDATE()
          AND notified_at IS NULL
    ");
    $stmt->execute(array_values($bizIds));
    foreach ($stmt->fetchAll() as $expired) {
        $msg = 'Your royalty penalty of +' . number_format($expired['rate_increase'] * 100, 1) . '% expired on ' . fmt_date('M j, Y', strtotime($expired['end_date'])) . '. Your rate is back to normal.';
        $pdo->prepare('INSERT INTO vendor_notifications (vendor_user_id, message) VALUES (?, ?)')->execute([$userId, $msg]);
        $pdo->prepare('UPDATE vendor_penalties SET notified_at = NOW() WHERE id = ?')->execute([$expired['id']]);
    }

    $stmt = $pdo->prepare('SELECT id, message FROM vendor_notifications WHERE vendor_user_id = ? AND read_at IS NULL ORDER BY created_at DESC');
    $stmt->execute([$userId]);
    $unreadNotifs = $stmt->fetchAll();

    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE business_id IN ($ph) AND status IN ('return_dispatched')");
    $cntStmt->execute(array_values($bizIds));
    $vendorRefundCount = (int)$cntStmt->fetchColumn();
}

$products = [];
if ($tab === 'products' && !$action && !empty($businesses)) {
    $ids          = array_column($businesses, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT p.*, b.name AS business_name, c.name AS category_name,
               pp.filename AS photo,
               (SELECT COUNT(*) FROM product_variants pv WHERE pv.product_id = p.id) AS variant_count,
               COALESCE(rv.avg_rating, 0) AS avg_rating,
               COALESCE(rv.review_count, 0) AS review_count
        FROM products p
        JOIN businesses b ON b.id = p.business_id
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN product_photos pp ON pp.product_id = p.id AND pp.is_primary = 1
        LEFT JOIN (SELECT product_id, AVG(rating) AS avg_rating, COUNT(*) AS review_count FROM reviews GROUP BY product_id) rv ON rv.product_id = p.id
        WHERE p.business_id IN ($placeholders) AND p.archived = 0
        ORDER BY p.name ASC
    ");
    $stmt->execute($ids);
    $products = $stmt->fetchAll();
}

$archivedProducts = [];
if ($tab === 'archive' && !empty($businesses)) {
    $ids = array_column($businesses, 'id');
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT p.*, pp.filename AS photo
        FROM products p
        LEFT JOIN product_photos pp ON pp.product_id = p.id AND pp.is_primary = 1
        WHERE p.business_id IN ($ph) AND p.archived = 1
        ORDER BY p.name ASC
    ");
    $stmt->execute($ids);
    $archivedProducts = $stmt->fetchAll();
}

$editing = null;
$editingVariants = [];
if ($action === 'edit' && isset($_GET['id']) && !empty($businesses)) {
    $ids          = array_column($businesses, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT p.*, pp.filename AS primary_photo
        FROM products p
        LEFT JOIN product_photos pp ON pp.product_id = p.id AND pp.is_primary = 1
        WHERE p.id = ? AND p.business_id IN ($placeholders)
    ");
    $stmt->execute(array_merge([(int)$_GET['id']], $ids));
    $editing = $stmt->fetch();
    if (!$editing) $action = '';
}

if ($editing) {
    $vStmt = $pdo->prepare('SELECT id, label, label_km, stock, price_override FROM product_variants WHERE product_id = ? ORDER BY sort_order ASC, id ASC');
    $vStmt->execute([$editing['id']]);
    $editingVariants = $vStmt->fetchAll();
}

$editingOptionTypes = [];
$editingCombos = [];
if ($editing) {
    $otStmt = $pdo->prepare('SELECT id, name, name_km, display_order FROM product_option_types WHERE product_id = ? ORDER BY display_order, id');
    $otStmt->execute([$editing['id']]);
    $editingOptionTypes = $otStmt->fetchAll();
    foreach ($editingOptionTypes as &$ot) {
        $ovStmt = $pdo->prepare('SELECT id, label, label_km, display_order FROM product_option_values WHERE option_type_id = ? ORDER BY display_order, id');
        $ovStmt->execute([$ot['id']]);
        $ot['values'] = $ovStmt->fetchAll();
    }
    unset($ot);
    if (!empty($editingOptionTypes)) {
        $cStmt = $pdo->prepare("
            SELECT pv.id, pv.stock, pv.price_override,
                   GROUP_CONCAT(pvo.option_value_id ORDER BY pvo.option_value_id ASC SEPARATOR ',') AS value_ids
            FROM product_variants pv
            LEFT JOIN product_variant_options pvo ON pvo.variant_id = pv.id
            WHERE pv.product_id = ?
            GROUP BY pv.id, pv.stock, pv.price_override");
        $cStmt->execute([$editing['id']]);
        $editingCombos = $cStmt->fetchAll();
    }
}
$useLegacyVariantUI = !empty($editing) && empty($editingOptionTypes) && !empty($editingVariants);

$editCatName = '—';
if ($editing) {
    foreach ($allFlat as $cat) {
        if ($cat['id'] == $editing['category_id']) { $editCatName = cat_name($cat); break; }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $tab === 'archive' ? 'Archive' : 'My Products' ?> — teepsaa</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/products/products.css">
    <?php if (false): ?>
    <link rel="stylesheet" href="/order-status/order-status.css">
    <link rel="stylesheet" href="/refund-status/refund-status.css">
    <link rel="stylesheet" href="/popup/popup.css">
    <?php endif; ?>
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>

    <?php foreach ($unreadNotifs as $notif): ?>
    <div class="vendor-notif-banner">
        <span><?= htmlspecialchars($notif['message']) ?></span>
        <form method="POST" action="/products/dismiss-notification.php">
            <?= csrf_input() ?>
            <input type="hidden" name="notification_id" value="<?= $notif['id'] ?>">
            <button type="submit" class="vendor-notif-dismiss" title="Dismiss">&times;</button>
        </form>
    </div>
    <?php endforeach; ?>

    <?php if (!empty($activePenalties)):
        $totalPenalty = array_sum(array_column($activePenalties, 'rate_increase'));
        $soonestExpiry = null;
        $hasIndefinite = false;
        foreach ($activePenalties as $p) {
            if ($p['end_date'] === null) { $hasIndefinite = true; }
            elseif ($soonestExpiry === null || $p['end_date'] < $soonestExpiry) { $soonestExpiry = $p['end_date']; }
        }
    ?>
    <div class="vendor-penalty-notice">
        A royalty penalty of <strong>+<?= number_format($totalPenalty * 100, 1) ?>%</strong> is active on your account<?php
            if ($hasIndefinite) echo ' with no set expiry date';
            elseif ($soonestExpiry) echo ', expiring ' . fmt_date('M j, Y', strtotime($soonestExpiry));
        ?>.
    </div>
    <?php endif; ?>

    <?php if (!$action): ?>
    <nav class="products-subnav">
        <a href="/products/" class="<?= $tab === 'products' ? 'active' : '' ?>"><?= $t['vendor_products'] ?></a>
        <a href="/products/?tab=archive" class="<?= $tab === 'archive' ? 'active' : '' ?>"><?= $t['prod_archive'] ?></a>
    </nav>
    <?php endif; ?>

    <?php if ($action === 'add' || $action === 'edit'): ?>

        <?php if ($action === 'edit' && $editing): ?>

        <div class="page-header" style="margin-bottom:1.25rem">
            <a href="/products/" class="btn-back">← <?= $t['vendor_products'] ?></a>
        </div>

        <?php
            $prevPhotosStmt = $pdo->prepare('SELECT filename FROM product_photos WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC, id ASC');
            $prevPhotosStmt->execute([$editing['id']]);
            $previewPhotos = array_column($prevPhotosStmt->fetchAll(), 'filename');
        ?>
        <div class="product-preview-card">
            <div class="product-preview-media">
                <?php if (!empty($previewPhotos)): ?>
                    <img src="/uploads/<?= htmlspecialchars($previewPhotos[0]) ?>" alt="" class="product-preview-main-photo" id="preview-main-img">
                    <?php if (count($previewPhotos) > 1): ?>
                    <div class="product-preview-thumbs">
                        <?php foreach ($previewPhotos as $i => $fn): ?>
                        <img src="/uploads/<?= htmlspecialchars($fn) ?>" alt=""
                             class="product-preview-thumb <?= $i === 0 ? 'product-preview-thumb--active' : '' ?>"
                             data-src="/uploads/<?= htmlspecialchars($fn) ?>">
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="product-preview-main-photo product-preview-main-photo--empty"></div>
                <?php endif; ?>
            </div>
            <div class="product-preview-info">
                <div class="product-preview-name"><?= htmlspecialchars(lang_field($editing, 'name')) ?></div>
                <div class="product-preview-row"><span><?= $t['search_category'] ?></span><span><?= htmlspecialchars($editCatName) ?></span></div>
                <div class="product-preview-row">
                    <span><?= $t['vendor_col_price'] ?></span>
                    <span style="display:flex;align-items:center;gap:0.6rem;">
                        <?= price_html($editing) ?>
                        <?php if (active_sale($editing)): ?>
                        <form method="POST" action="/products/cancel-sale.php" style="margin:0">
                            <?= csrf_input() ?>
                            <input type="hidden" name="product_id" value="<?= $editing['id'] ?>">
                            <button type="submit" class="btn-cancel-sale"><?= $t['prod_cancel_sale'] ?></button>
                        </form>
                        <?php endif; ?>
                    </span>
                </div>
                <?php if (!empty($editingVariants)): ?>
                <div class="product-preview-row">
                    <span><?= $t['product_variants'] ?></span>
                    <span style="display:flex;flex-wrap:wrap;gap:0.3rem;">
                        <?php foreach ($editingVariants as $v): ?>
                        <span style="font-size:0.8rem;background:#f3f4f6;border-radius:4px;padding:0.15rem 0.5rem;white-space:nowrap;">
                            <?= htmlspecialchars($v['label']) ?> &middot; <?= (int)$v['stock'] ?>
                        </span>
                        <?php endforeach; ?>
                    </span>
                </div>
                <?php else: ?>
                <div class="product-preview-row"><span><?= $t['vendor_col_stock'] ?></span><span><?= (int)$editing['stock'] ?></span></div>
                <?php endif; ?>
                <div class="product-preview-row"><span><?= $t['order_delivery'] ?></span><span><?= ($editing['delivery_method'] ?? 'bike') === 'tuktuk' ? 'Grab Tuk-Tuk' : 'Grab Bike' ?></span></div>
                <?php if ($editing['description']): ?>
                <div class="product-preview-row"><span><?= $t['vendor_settings_description'] ?></span><span><?= htmlspecialchars($editing['description']) ?></span></div>
                <?php endif; ?>
                <div class="product-preview-row">
                    <span><?= $t['vendor_col_status'] ?></span>
                    <span class="status-action-wrap">
                        <span class="status <?= $editing['active'] ? 'status-active' : 'status-inactive' ?>"><?= $editing['active'] ? $t['vendor_status_active'] : $t['vendor_status_inactive'] ?></span>
                        <div class="status-dropdown-container">
                            <button type="button" class="status-edit-btn" id="status-edit-btn"><?= $t['prod_edit'] ?></button>
                            <div class="status-dropdown" id="status-dropdown">
                                <form method="POST" action="/products/toggle.php">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="product_id" value="<?= $editing['id'] ?>">
                                    <button type="submit"><?= $editing['active'] ? $t['prod_deactivate'] : $t['prod_activate'] ?></button>
                                </form>
                                <form method="POST" action="/products/archive.php">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="product_id" value="<?= $editing['id'] ?>">
                                    <button type="submit"><?= $t['prod_archive'] ?></button>
                                </form>
                                <form method="POST" action="/products/delete.php" id="preview-delete-form">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="product_id" value="<?= $editing['id'] ?>">
                                    <button type="button" class="status-dropdown-danger" onclick="if(confirm('Permanently delete this product? This cannot be undone.')) document.getElementById('preview-delete-form').submit()"><?= $t['prod_delete'] ?></button>
                                </form>
                            </div>
                        </div>
                    </span>
                </div>
            </div>
            <div class="product-preview-actions">
                <button type="button" id="edit-toggle-btn" class="btn"><?= $t['prod_edit'] ?></button>
                <a href="/product/?id=<?= $editing['id'] ?>" target="_blank" class="btn btn-secondary"><?= $t['prod_preview'] ?></a>
            </div>
        </div>
        <script>
        (function () {
            var btn  = document.getElementById('status-edit-btn');
            var drop = document.getElementById('status-dropdown');
            if (!btn || !drop) return;
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                drop.classList.toggle('open');
            });
            document.addEventListener('click', function () { drop.classList.remove('open'); });
            drop.addEventListener('click', function (e) { e.stopPropagation(); });
        })();
        </script>
        <script>
        (function () {
            var main = document.getElementById('preview-main-img');
            if (!main) return;
            document.querySelectorAll('.product-preview-thumb').forEach(function (t) {
                t.addEventListener('click', function () {
                    main.src = this.dataset.src;
                    document.querySelectorAll('.product-preview-thumb').forEach(function (x) { x.classList.remove('product-preview-thumb--active'); });
                    this.classList.add('product-preview-thumb--active');
                });
            });
        })();
        </script>

        <div id="edit-form-wrap" style="display:none">

        <?php
            $galleryStmt = $pdo->prepare('SELECT id, filename, is_primary FROM product_photos WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC, id ASC');
            $galleryStmt->execute([$editing['id']]);
            $galleryPhotos = $galleryStmt->fetchAll();
            $totalPhotos   = count($galleryPhotos);
            $remaining     = 9 - $totalPhotos;
        ?>

        <?php endif; ?>

        <?php if (empty($businesses)): ?>
            <p class="notice"><?= $t['prod_need_business'] ?></p>
        <?php else: ?>
        <form method="POST" action="/products/save.php" enctype="multipart/form-data" class="product-form" id="product-edit-form">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="<?= $action ?>">
            <?php if ($editing): ?>
                <input type="hidden" name="product_id" value="<?= $editing['id'] ?>">
            <?php endif; ?>

            <div class="field">
                <label for="business_id"><?= $t['order_business'] ?></label>
                <select id="business_id" name="business_id" required <?= $editing ? 'disabled' : '' ?>>
                    <?php foreach ($businesses as $b): ?>
                        <option value="<?= $b['id'] ?>"
                            <?= ($editing && $editing['business_id'] == $b['id']) || (!$editing && count($businesses) === 1) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($editing): ?>
                    <input type="hidden" name="business_id" value="<?= $editing['business_id'] ?>">
                <?php endif; ?>
            </div>

            <div class="field">
                <label><?= $t['search_category'] ?></label>
                <div id="cat-cascade" class="cat-cascade"></div>
                <input type="hidden" id="category_id" name="category_id" value="<?= htmlspecialchars((string)($editing['category_id'] ?? '')) ?>">
            </div>

            <div class="field">
                <label for="name"><?= $t['prod_name'] ?></label>
                <input type="text" id="name" name="name" required value="<?= htmlspecialchars($editing['name'] ?? '') ?>">
            </div>

            <div class="field">
                <label for="name_km"><?= $t['prod_name'] ?> <span class="hint"><?= $t['form_km_field'] ?></span></label>
                <input type="text" id="name_km" name="name_km" value="<?= htmlspecialchars($editing['name_km'] ?? '') ?>" placeholder="ឈ្មោះផលិតផលជាភាសាខ្មែរ">
            </div>

            <div class="field">
                <label for="description"><?= $t['vendor_settings_description'] ?></label>
                <textarea id="description" name="description" rows="3"><?= htmlspecialchars($editing['description'] ?? '') ?></textarea>
            </div>

            <div class="field">
                <label for="description_km"><?= $t['vendor_settings_description'] ?> <span class="hint"><?= $t['form_km_field'] ?></span></label>
                <textarea id="description_km" name="description_km" rows="3" placeholder="ការពិពណ៌នាជាភាសាខ្មែរ"><?= htmlspecialchars($editing['description_km'] ?? '') ?></textarea>
            </div>

            <div class="field-row">
                <div class="field">
                    <label for="price"><?= $t['search_price_usd'] ?></label>
                    <input type="number" id="price" name="price" required min="0" step="1"
                           value="<?= $editing ? number_format($editing['price'], 2, '.', '') : '' ?>">
                    <span class="hint" id="payout-preview"></span>
                </div>
                <div class="field" id="stock-field-wrap">
                    <label for="stock"><?= $t['vendor_col_stock'] ?></label>
                    <input type="number" id="stock" name="stock" required min="0"
                           value="<?= $editing['stock'] ?? 0 ?>">
                    <span class="hint" id="stock-variant-hint" style="display:none;color:#9ca3af"><?= $t['prod_stock_variant_hint'] ?></span>
                </div>
                <div class="field">
                    <label for="delivery_method"><?= $t['prod_delivery_method'] ?></label>
                    <select id="delivery_method" name="delivery_method">
                        <option value="bike"   <?= ($editing['delivery_method'] ?? 'bike') === 'bike'   ? 'selected' : '' ?>>Grab Bike</option>
                        <option value="tuktuk" <?= ($editing['delivery_method'] ?? 'bike') === 'tuktuk' ? 'selected' : '' ?>>Grab Tuk-Tuk</option>
                    </select>
                </div>
            </div>

            <div class="field-row">
                <div class="field">
                    <label for="sale_price"><?= $t['prod_sale_price'] ?> <span class="hint"><?= $t['prod_optional'] ?></span></label>
                    <input type="number" id="sale_price" name="sale_price" min="0" step="0.01" placeholder="e.g. 8.00"
                           value="<?= ($editing && $editing['sale_price'] !== null) ? number_format((float)$editing['sale_price'], 2, '.', '') : '' ?>">
                </div>
                <div class="field">
                    <label for="sale_date"><?= $t['prod_sale_date'] ?></label>
                    <input type="date" id="sale_date" name="sale_date"
                           value="<?= ($editing && $editing['sale_ends_at']) ? date('Y-m-d', strtotime($editing['sale_ends_at'])) : '' ?>">
                </div>
                <div class="field">
                    <label for="sale_time"><?= $t['prod_sale_time'] ?></label>
                    <select id="sale_time" name="sale_time">
                        <option value=""><?= $t['prod_time_placeholder'] ?></option>
                        <?php
                            $editSaleTime = ($editing && $editing['sale_ends_at']) ? fmt_date('H:i', strtotime($editing['sale_ends_at'])) : '';
                            for ($h = 0; $h < 24; $h++) {
                                foreach ([0, 30] as $m) {
                                    $val  = sprintf('%02d:%02d', $h, $m);
                                    $disp = sprintf('%02d%02d', $h, $m);
                                    $sel  = $editSaleTime === $val ? ' selected' : '';
                                    echo "<option value=\"$val\"$sel>$disp</option>";
                                }
                            }
                        ?>
                    </select>
                </div>
            </div>

            <div class="field" id="variant-field">
                <label><?= $t['product_variants'] ?> <span class="hint"><?= $t['prod_variants_hint'] ?></span></label>

                <?php if ($useLegacyVariantUI): ?>
                <div id="variant-list"></div>
                <button type="button" class="btn-add-variant" id="add-variant-btn"><?= $t['prod_add_variant'] ?></button>
                <?php else: ?>
                <div id="option-type-list"></div>
                <button type="button" id="add-option-type-btn" class="btn-add-variant"><?= $t['prod_add_option_type'] ?></button>
                <div id="combo-section" style="display:none;margin-top:1rem">
                    <p class="combo-heading"><?= $t['prod_variant_combos'] ?></p>
                    <table class="combo-table">
                        <thead>
                            <tr>
                                <th class="combo-th"><?= $t['product_variants'] ?></th>
                                <th class="combo-th"><?= $t['vendor_col_stock'] ?></th>
                                <th class="combo-th"><?= $t['prod_price_override'] ?> <span class="hint"><?= $t['prod_optional'] ?></span></th>
                            </tr>
                        </thead>
                        <tbody id="combo-tbody"></tbody>
                    </table>
                </div>
                <input type="hidden" name="options_json" id="options_json">
                <?php endif; ?>
            </div>

            <?php if ($action === 'add'): ?>
            <div class="field">
                <label><?= $t['submit_photos'] ?> <span class="hint"><?= $t['prod_photos_hint'] ?></span></label>
                <input type="file" name="gallery_photos[]" multiple accept="image/jpeg,image/png">
            </div>
            <?php endif; ?>

            <?php if ($editing): ?>
                <input type="hidden" name="active" value="<?= $editing['active'] ? '1' : '0' ?>">
            <?php endif; ?>

            <?php if ($action === 'add'): ?>
            <div class="form-actions">
                <button type="submit"><?= $t['prod_add_product'] ?></button>
            </div>
            <?php endif; ?>
        </form>
        <?php if ($action === 'edit' && $editing): ?>

        <div class="gallery-display-field">
            <div class="gallery-display-label"><?= $t['submit_photos'] ?> <span class="hint"><?= $totalPhotos ?>/9</span></div>
            <?php if (!empty($galleryPhotos)): ?>
            <div class="gallery-grid" id="gallery-grid">
                <?php foreach ($galleryPhotos as $i => $gp): ?>
                <div class="gallery-item" draggable="true" data-photo-id="<?= $gp['id'] ?>">
                    <img src="/uploads/<?= htmlspecialchars($gp['filename']) ?>" alt="" class="gallery-thumb-img">
                    <?php if ($i === 0): ?>
                        <span class="gallery-primary-badge"><?= $t['prod_main'] ?></span>
                    <?php endif; ?>
                    <form method="POST" action="/products/photo-delete.php">
                        <?= csrf_input() ?>
                        <input type="hidden" name="photo_id" value="<?= $gp['id'] ?>">
                        <input type="hidden" name="product_id" value="<?= $editing['id'] ?>">
                        <button type="submit" class="gallery-delete-btn" title="Remove">×</button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($remaining > 0): ?>
        <div class="gallery-add-field">
            <input type="file" name="gallery_photos[]" multiple accept="image/jpeg,image/png" form="product-edit-form">
            <p class="hint">jpg or png · max 2MB each · <?= $remaining ?> slot<?= $remaining > 1 ? 's' : '' ?> remaining</p>
        </div>
        <?php else: ?>
        <p class="hint" style="margin-bottom:1rem">9/9 — remove a photo to add more.</p>
        <?php endif; ?>

        <div class="form-actions">
            <button type="submit" form="product-edit-form" class="btn"><?= $t['prod_save_changes'] ?></button>
        </div>

        </div>
        <script>
        (function () {
            var grid = document.getElementById('gallery-grid');
            if (!grid) return;

            var productId = <?= (int)$editing['id'] ?>;
            var dragging  = null;

            function getCsrf() {
                var el = grid.querySelector('input[name="csrf_token"]');
                return el ? el.value : '';
            }

            function updateBadge() {
                grid.querySelectorAll('.gallery-item').forEach(function (item, i) {
                    var badge = item.querySelector('.gallery-primary-badge');
                    if (i === 0) {
                        if (!badge) {
                            badge = document.createElement('span');
                            badge.className = 'gallery-primary-badge';
                            badge.textContent = '<?= $t['prod_main'] ?>';
                            item.appendChild(badge);
                        }
                    } else {
                        if (badge) badge.remove();
                    }
                });
            }

            function saveOrder() {
                var ids = Array.from(grid.querySelectorAll('.gallery-item')).map(function (el) {
                    return el.dataset.photoId;
                });
                var fd = new FormData();
                fd.append('csrf_token', getCsrf());
                fd.append('product_id', productId);
                ids.forEach(function (id) { fd.append('photo_ids[]', id); });
                fetch('/products/photo-reorder.php', { method: 'POST', body: fd });
            }

            grid.addEventListener('dragstart', function (e) {
                dragging = e.target.closest('.gallery-item');
                if (!dragging) return;
                e.dataTransfer.effectAllowed = 'move';
                setTimeout(function () { dragging.classList.add('dragging'); }, 0);
            });

            grid.addEventListener('dragend', function () {
                if (dragging) { dragging.classList.remove('dragging'); dragging = null; }
            });

            grid.addEventListener('dragover', function (e) {
                e.preventDefault();
                if (!dragging) return;
                var target = e.target.closest('.gallery-item');
                if (!target || target === dragging) return;
                var rect  = target.getBoundingClientRect();
                var after = e.clientX > rect.left + rect.width / 2;
                grid.insertBefore(dragging, after ? target.nextSibling : target);
                updateBadge();
            });

            grid.addEventListener('drop', function (e) {
                e.preventDefault();
                saveOrder();
            });
        })();
        </script>
        <script>
        (function () {
            var btn  = document.getElementById('edit-toggle-btn');
            var wrap = document.getElementById('edit-form-wrap');
            btn.addEventListener('click', function () {
                var open = wrap.style.display === 'none';
                wrap.style.display = open ? 'block' : 'none';
                btn.textContent = open ? '<?= $t['prod_cancel'] ?>' : '<?= $t['prod_edit'] ?>';
            });
        })();
        </script>
        <?php endif; ?>
        <script>
        (function () {
            var allCats      = <?= json_encode(array_values($allFlat)) ?>;
            var CAT_LANG     = <?= json_encode($_SESSION['lang'] ?? 'km') ?>;
            var editLeafId   = <?= json_encode($editing ? (int)$editing['category_id'] : null) ?>;

            var byParent = {}, byId = {};
            allCats.forEach(function (c) {
                byId[c.id] = c;
                var key = c.parent_id || 'root';
                if (!byParent[key]) byParent[key] = [];
                byParent[key].push(c);
            });

            var container    = document.getElementById('cat-cascade');
            var hidden       = document.getElementById('category_id');
            var priceIn      = document.getElementById('price');
            var preview      = document.getElementById('payout-preview');
            var COMPANY_ADDN = <?= json_encode($companyAddOn) ?>;
            var PRODUCT_ADDN = <?= json_encode($editing ? (float)$editing['royalty_add_on'] : 0.0) ?>;

            function isLeaf(id) { return !byParent[id] || !byParent[id].length; }

            function updatePayout() {
                var id    = parseInt(hidden.value);
                var cat   = id ? byId[id] : null;
                var catRate = cat ? parseFloat(cat.royalty_rate) : 0;
                var total = catRate + COMPANY_ADDN + PRODUCT_ADDN;
                var price = parseFloat(priceIn.value) || 0;
                if (!price || !total) { preview.textContent = ''; return; }
                preview.textContent = '<?= $t['prod_payout_at'] ?> ' + Math.round(total * 100) + '<?= $t['prod_payout_mid'] ?>' + (price * (1 - total)).toFixed(2);
            }

            priceIn.addEventListener('input', updatePayout);

            function trimFrom(level) {
                Array.from(container.querySelectorAll('select')).slice(level).forEach(function (s) { s.remove(); });
            }

            function renderLevel(parentKey, level, preselectId, chain) {
                var children = byParent[parentKey] || [];
                if (!children.length) return;

                var sel = document.createElement('select');
                sel.className = 'cat-level-select';

                var ph = document.createElement('option');
                ph.value = '';
                ph.textContent = level === 0 ? '<?= $t['prod_select_category'] ?>' : '<?= $t['prod_select_subcategory'] ?>';
                sel.appendChild(ph);

                children.forEach(function (c) {
                    var opt = document.createElement('option');
                    opt.value = c.id;
                    opt.textContent = (CAT_LANG === 'km' && c.name_km) ? c.name_km : c.name;
                    if (preselectId && c.id == preselectId) opt.selected = true;
                    sel.appendChild(opt);
                });

                container.appendChild(sel);

                if (preselectId) {
                    var pid = parseInt(preselectId);
                    if (isLeaf(pid)) {
                        hidden.value = pid;
                        updatePayout();
                    } else {
                        renderLevel(pid, level + 1, chain ? chain[level + 1] : null, chain);
                    }
                }

                sel.addEventListener('change', function () {
                    trimFrom(level + 1);
                    hidden.value = '';
                    preview.textContent = '';
                    var id = parseInt(sel.value);
                    if (!id) return;
                    if (isLeaf(id)) {
                        hidden.value = id;
                        updatePayout();
                    } else {
                        renderLevel(id, level + 1, null, null);
                    }
                });
            }

            function buildChain(leafId) {
                var chain = [], c = byId[leafId];
                while (c) { chain.unshift(c.id); c = c.parent_id ? byId[c.parent_id] : null; }
                return chain;
            }

            if (editLeafId) {
                var chain = buildChain(editLeafId);
                renderLevel('root', 0, chain[0], chain);
            } else {
                renderLevel('root', 0, null, null);
            }

            document.querySelector('.product-form').addEventListener('submit', function (e) {
                if (!hidden.value) {
                    e.preventDefault();
                    alert('<?= $t['prod_please_select_category'] ?>');
                    container.querySelector('select').focus();
                }
            });
        })();
        </script>
        <?php if ($useLegacyVariantUI): ?>
        <script>
        (function () {
            var list      = document.getElementById('variant-list');
            var addBtn    = document.getElementById('add-variant-btn');
            var stockIn   = document.getElementById('stock');
            var stockHint = document.getElementById('stock-variant-hint');
            var EXISTING  = <?= json_encode(array_values($editingVariants)) ?>;

            function updateStockField() {
                var hasRows = list.querySelectorAll('.variant-row').length > 0;
                stockIn.disabled = hasRows;
                stockIn.style.opacity = hasRows ? '0.4' : '';
                stockHint.style.display = hasRows ? '' : 'none';
            }

            function escAttr(s) {
                return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;');
            }

            function makeRow(id, label, labelKm, stock, price) {
                var row = document.createElement('div');
                row.className = 'variant-row';
                row.innerHTML =
                    '<input type="hidden" name="variant_id[]" value="' + (id || '') + '">' +
                    '<input type="text" name="variant_label[]" placeholder="<?= $t['prod_variant_label_placeholder'] ?>" value="' + escAttr(label || '') + '" required>' +
                    '<input type="text" name="variant_label_km[]" placeholder="ខ្មែរ" value="' + escAttr(labelKm || '') + '">' +
                    '<input type="number" name="variant_stock[]" placeholder="<?= $t['prod_stock_word'] ?>" min="0" value="' + (stock != null ? stock : '') + '" required>' +
                    '<input type="number" name="variant_price[]" placeholder="<?= $t['prod_price_override_opt'] ?>" min="0" step="0.01" value="' + (price != null ? price : '') + '">' +
                    '<button type="button" class="variant-row-remove" title="Remove">&times;</button>';
                row.querySelector('.variant-row-remove').addEventListener('click', function () {
                    row.remove(); updateStockField();
                });
                return row;
            }

            EXISTING.forEach(function (v) { list.appendChild(makeRow(v.id, v.label, v.label_km, v.stock, v.price_override)); });
            updateStockField();

            addBtn.addEventListener('click', function () {
                list.appendChild(makeRow('', '', '', '', ''));
                updateStockField();
                list.lastElementChild.querySelector('input[type="text"]').focus();
            });
        })();
        </script>
        <style>
        .variant-row { display:flex; gap:0.5rem; align-items:center; margin-bottom:0.4rem; }
        .variant-row input[type="text"] { flex:2; min-width:0; padding:0.45rem 0.6rem; border:1px solid var(--border-strong); border-radius: var(--radius-sm); font-size:0.875rem; font-family:inherit; }
        .variant-row input[type="number"] { flex:1; min-width:0; padding:0.45rem 0.6rem; border:1px solid var(--border-strong); border-radius: var(--radius-sm); font-size:0.875rem; font-family:inherit; }
        .variant-row input[name="variant_label_km[]"] { background:#fafafa; }
        .variant-row-remove { background:none; border:none; color:#9ca3af; font-size:1.1rem; cursor:pointer; padding:0 0.25rem; line-height:1; }
        .variant-row-remove:hover { color:var(--error-fg); }
        .btn-add-variant { margin-top:0.35rem; padding:0.35rem 0.85rem; font-size:0.85rem; border:1px dashed var(--border-strong); border-radius: var(--radius-sm); background:#fff; color:var(--text-muted); cursor:pointer; font-family:inherit; }
        .btn-add-variant:hover { border-color:#9ca3af; color:var(--text-soft); }
        </style>
        <?php else: ?>
        <script>
        (function () {
            var stockIn      = document.getElementById('stock');
            var stockHint    = document.getElementById('stock-variant-hint');
            var addOptBtn    = document.getElementById('add-option-type-btn');
            var optTypeList  = document.getElementById('option-type-list');
            var comboSection = document.getElementById('combo-section');
            var comboTbody   = document.getElementById('combo-tbody');
            var optionsJson  = document.getElementById('options_json');
            var form         = document.getElementById('product-edit-form');

            var tidCounter = 1000;
            var optionTypes = [];   // [{id, name, values:[{id,tid,label}]}]
            var comboData   = {};   // comboKey → {variantId, stock, price}

            var INIT_TYPES  = <?= json_encode(array_map(function($ot) {
                return ['id' => (int)$ot['id'], 'name' => $ot['name'], 'name_km' => $ot['name_km'] ?? '',
                        'values' => array_map(function($v){ return ['id'=>(int)$v['id'],'label'=>$v['label'],'label_km'=>$v['label_km'] ?? '']; }, $ot['values'])];
            }, $editingOptionTypes)) ?>;
            var INIT_COMBOS = <?= json_encode(array_map(function($c) {
                return ['id'=>(int)$c['id'],'stock'=>$c['stock'],'price_override'=>$c['price_override'],'value_ids'=>$c['value_ids']];
            }, $editingCombos)) ?>;

            function nextTid() { return tidCounter++; }
            function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
            function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

            function updateStockField() {
                var hasOpts = optionTypes.length > 0 && optionTypes.some(function(ot){ return ot.values.some(function(v){ return v.label.trim(); }); });
                stockIn.disabled = hasOpts;
                stockIn.style.opacity = hasOpts ? '0.4' : '';
                stockHint.style.display = hasOpts ? '' : 'none';
            }

            function cartesian(arrays) {
                if (!arrays.length) return [[]];
                return arrays.reduce(function(acc, arr) {
                    var result = [];
                    acc.forEach(function(a) { arr.forEach(function(b) { result.push(a.concat([b])); }); });
                    return result;
                }, [[]]);
            }

            function comboKey(vals) {
                return vals.map(function(v){ return v.tid; }).slice().sort(function(a,b){return a-b;}).join(',');
            }

            function regenerateCombos() {
                var validTypes = optionTypes.filter(function(ot){
                    return ot.values.some(function(v){ return v.label.trim(); });
                });
                if (!validTypes.length) { comboSection.style.display = 'none'; comboTbody.innerHTML = ''; return; }
                var valueArrays = validTypes.map(function(ot){ return ot.values.filter(function(v){ return v.label.trim(); }); });
                var combos = cartesian(valueArrays);
                comboSection.style.display = '';
                comboTbody.innerHTML = '';
                combos.forEach(function(combo) {
                    var key = comboKey(combo);
                    var d   = comboData[key] || {variantId:0, stock:'', price:''};
                    var label = combo.map(function(v){ return v.label.trim(); }).join(' / ');
                    var tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td class="combo-td">' + escHtml(label) + '</td>' +
                        '<td class="combo-td"><input type="number" class="combo-stock" min="0" placeholder="0" value="' + esc(d.stock) + '"></td>' +
                        '<td class="combo-td"><input type="number" class="combo-price" min="0" step="0.01" placeholder="<?= $t['prod_base_price'] ?>" value="' + esc(d.price) + '"></td>';
                    (function(k){ // capture key per row
                        if (!comboData[k]) comboData[k] = {variantId:0, stock:'', price:''};
                        tr.querySelector('.combo-stock').addEventListener('input', function(){ comboData[k].stock = this.value; });
                        tr.querySelector('.combo-price').addEventListener('input', function(){ comboData[k].price = this.value; });
                    })(key);
                    comboTbody.appendChild(tr);
                });
            }

            function renderOptionTypes() {
                optTypeList.innerHTML = '';
                optionTypes.forEach(function(ot, otIdx) {
                    var block = document.createElement('div');
                    block.className = 'option-type-block';

                    var header = document.createElement('div');
                    header.className = 'option-type-header';
                    var nameInput = document.createElement('input');
                    nameInput.type = 'text'; nameInput.className = 'option-type-name';
                    nameInput.placeholder = '<?= $t['prod_option_name_ph'] ?>';
                    nameInput.value = ot.name;
                    (function(idx){ nameInput.addEventListener('input', function(){ optionTypes[idx].name = this.value; }); })(otIdx);
                    var nameKmInput = document.createElement('input');
                    nameKmInput.type = 'text'; nameKmInput.className = 'option-type-name option-type-name-km';
                    nameKmInput.placeholder = 'ខ្មែរ';
                    nameKmInput.value = ot.name_km || '';
                    (function(idx){ nameKmInput.addEventListener('input', function(){ optionTypes[idx].name_km = this.value; }); })(otIdx);
                    var removeBtn = document.createElement('button');
                    removeBtn.type = 'button'; removeBtn.className = 'option-type-remove'; removeBtn.textContent = '<?= $t['prod_remove'] ?>';
                    (function(idx){ removeBtn.addEventListener('click', function(){ optionTypes.splice(idx,1); renderAll(); }); })(otIdx);
                    header.appendChild(nameInput); header.appendChild(nameKmInput); header.appendChild(removeBtn);

                    var valList = document.createElement('div');
                    valList.className = 'option-value-list';
                    ot.values.forEach(function(val, vIdx) {
                        var row = document.createElement('div');
                        row.className = 'option-value-row';
                        var valInput = document.createElement('input');
                        valInput.type = 'text'; valInput.className = 'option-value-input';
                        valInput.placeholder = '<?= $t['prod_value_ph'] ?>'; valInput.value = val.label;
                        (function(oi,vi){ valInput.addEventListener('input', function(){ optionTypes[oi].values[vi].label = this.value; regenerateCombos(); }); })(otIdx,vIdx);
                        var valKmInput = document.createElement('input');
                        valKmInput.type = 'text'; valKmInput.className = 'option-value-input option-value-input-km';
                        valKmInput.placeholder = 'ខ្មែរ'; valKmInput.value = val.label_km || '';
                        (function(oi,vi){ valKmInput.addEventListener('input', function(){ optionTypes[oi].values[vi].label_km = this.value; }); })(otIdx,vIdx);
                        var valRemove = document.createElement('button');
                        valRemove.type = 'button'; valRemove.className = 'option-value-remove'; valRemove.textContent = '×';
                        (function(oi,vi){ valRemove.addEventListener('click', function(){ optionTypes[oi].values.splice(vi,1); renderAll(); }); })(otIdx,vIdx);
                        row.appendChild(valInput); row.appendChild(valKmInput); row.appendChild(valRemove);
                        valList.appendChild(row);
                    });

                    var addValBtn = document.createElement('button');
                    addValBtn.type = 'button'; addValBtn.className = 'btn-add-value'; addValBtn.textContent = '<?= $t['prod_add_value'] ?>';
                    (function(idx){
                        addValBtn.addEventListener('click', function(){
                            optionTypes[idx].values.push({id:0, tid:nextTid(), label:'', label_km:''});
                            renderAll();
                            var lists = optTypeList.querySelectorAll('.option-value-list');
                            if (lists[idx]) { var ins = lists[idx].querySelectorAll('.option-value-input'); if (ins.length) ins[ins.length-1].focus(); }
                        });
                    })(otIdx);

                    block.appendChild(header); block.appendChild(valList); block.appendChild(addValBtn);
                    optTypeList.appendChild(block);
                });
            }

            function renderAll() { renderOptionTypes(); regenerateCombos(); updateStockField(); }

            form.addEventListener('submit', function() {
                var validTypes = optionTypes.filter(function(ot){
                    return ot.name.trim() && ot.values.some(function(v){ return v.label.trim(); });
                });
                var payload = {
                    optionTypes: validTypes.map(function(ot){
                        return { id: ot.id, name: ot.name.trim(), name_km: (ot.name_km || '').trim(), values: ot.values.filter(function(v){ return v.label.trim(); }).map(function(v){ return {id:v.id,tid:v.tid,label:v.label.trim(),label_km:(v.label_km || '').trim()}; }) };
                    }),
                    variants: []
                };
                var valueArrays = validTypes.map(function(ot){ return ot.values.filter(function(v){ return v.label.trim(); }); });
                var combos = validTypes.length ? cartesian(valueArrays) : [];
                combos.forEach(function(combo) {
                    var key = comboKey(combo);
                    var d   = comboData[key] || {variantId:0, stock:'0', price:''};
                    payload.variants.push({
                        variantId: d.variantId || 0,
                        stock:     parseInt(d.stock) || 0,
                        price:     (d.price !== '' && d.price != null) ? parseFloat(d.price) : null,
                        valueRefs: combo.map(function(v){ return {tid:v.tid, dbId:v.id}; })
                    });
                });
                optionsJson.value = JSON.stringify(payload);
            });

            // Initialize from PHP editing data
            INIT_TYPES.forEach(function(ot) {
                var type = {id: ot.id, name: ot.name, name_km: ot.name_km || '', values: []};
                (ot.values || []).forEach(function(v){ type.values.push({id:v.id, tid:nextTid(), label:v.label, label_km: v.label_km || ''}); });
                optionTypes.push(type);
            });

            var dbIdToTid = {};
            optionTypes.forEach(function(ot){ ot.values.forEach(function(v){ dbIdToTid[v.id] = v.tid; }); });

            INIT_COMBOS.forEach(function(c) {
                if (!c.value_ids) return;
                var dbIds = c.value_ids.split(',').map(Number).sort(function(a,b){return a-b;});
                var tids  = dbIds.map(function(id){ return dbIdToTid[id]||0; }).filter(Boolean);
                if (tids.length !== dbIds.length) return;
                var key = tids.sort(function(a,b){return a-b;}).join(',');
                comboData[key] = {variantId:c.id, stock:c.stock!=null?String(c.stock):'0', price:c.price_override!=null?String(c.price_override):''};
            });

            addOptBtn.addEventListener('click', function(){
                optionTypes.push({id:0, name:'', name_km:'', values:[]});
                renderAll();
                var inputs = optTypeList.querySelectorAll('.option-type-name');
                if (inputs.length) inputs[inputs.length-1].focus();
            });

            renderAll();
        })();
        </script>
        <style>
        .btn-add-variant { margin-top:0.35rem; padding:0.35rem 0.85rem; font-size:0.85rem; border:1px dashed var(--border-strong); border-radius: var(--radius-sm); background:#fff; color:var(--text-muted); cursor:pointer; font-family:inherit; }
        .btn-add-variant:hover { border-color:#9ca3af; color:var(--text-soft); }
        .option-type-block { border:1px solid var(--border); border-radius: var(--radius-sm); padding:0.85rem; margin-bottom:0.75rem; }
        .option-type-header { display:flex; gap:0.5rem; align-items:center; margin-bottom:0.6rem; }
        .option-type-name { flex:1; height:34px; padding:0 0.6rem; border:1px solid var(--border-strong); border-radius: var(--radius-sm); font-size:0.875rem; font-family:inherit; }
        .option-type-remove { padding:0.3rem 0.65rem; border:1px solid #fca5a5; border-radius: var(--radius-sm); background:#fff; color:#dc2626; font-size:0.8rem; cursor:pointer; font-family:inherit; }
        .option-type-remove:hover { background:#fee2e2; }
        .option-value-list { display:flex; flex-wrap:wrap; gap:0.4rem; margin-bottom:0.5rem; }
        .option-value-row { display:flex; gap:0.3rem; align-items:center; }
        .option-value-input { height:32px; padding:0 0.55rem; border:1px solid var(--border-strong); border-radius: var(--radius-sm); font-size:0.875rem; font-family:inherit; width:110px; }
        .option-type-name-km, .option-value-input-km { background:#fafafa; }
        .option-value-remove { background:none; border:none; color:#9ca3af; font-size:1.1rem; cursor:pointer; padding:0 0.2rem; line-height:1; }
        .option-value-remove:hover { color:var(--error-fg); }
        .btn-add-value { padding:0.25rem 0.6rem; border:1px dashed var(--border-strong); border-radius: var(--radius-sm); background:#fff; color:var(--text-muted); font-size:0.8rem; cursor:pointer; font-family:inherit; }
        .btn-add-value:hover { border-color:#9ca3af; color:var(--text-soft); }
        .combo-heading { font-size:0.8rem; font-weight:700; color:var(--text-soft); text-transform:uppercase; letter-spacing:0.04em; margin:0.75rem 0 0.5rem; }
        .combo-table { width:100%; border-collapse:collapse; }
        .combo-th { text-align:left; padding:0.4rem 0.75rem; font-size:0.78rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.04em; border-bottom:1px solid var(--border); }
        .combo-td { padding:0.4rem 0.75rem; border-bottom:1px solid #f3f4f6; font-size:0.875rem; vertical-align:middle; }
        .combo-td input { width:90px; padding:0.35rem 0.5rem; border:1px solid var(--border-strong); border-radius: var(--radius-sm); font-size:0.875rem; font-family:inherit; }
        </style>
        <?php endif; ?>
        <?php endif; ?>

    <?php elseif ($tab === 'archive'): ?>

        <div class="page-header">
            <h1><?= $t['prod_archive'] ?></h1>
        </div>

        <?php if (empty($archivedProducts)): ?>
            <p class="notice"><?= $t['prod_no_archived'] ?></p>
        <?php else: ?>
        <table class="product-table">
            <thead>
                <tr>
                    <th><?= $t['prod_col_photo'] ?></th>
                    <th><?= $t['vendor_col_name'] ?></th>
                    <th><?= $t['vendor_col_price'] ?></th>
                    <th><?= $t['vendor_col_stock'] ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($archivedProducts as $p): ?>
                <tr>
                    <td>
                        <?php if ($p['photo']): ?>
                            <img src="/uploads/<?= htmlspecialchars($p['photo']) ?>" alt="" class="thumb">
                        <?php else: ?>
                            <div class="thumb thumb--empty"></div>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars(lang_field($p, 'name')) ?></td>
                    <td>$<?= number_format($p['price'], 2) ?></td>
                    <td><?= (int)$p['stock'] ?></td>
                    <td class="actions">
                        <form method="POST" action="/products/unarchive.php" style="display:inline">
                            <?= csrf_input() ?>
                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                            <button type="submit" class="btn-link"><?= $t['prod_unarchive'] ?></button>
                        </form>
                        <form method="POST" action="/products/delete.php" style="display:inline">
                            <?= csrf_input() ?>
                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                            <input type="hidden" name="from" value="archive">
                            <button type="button" class="btn-link btn-link--danger"
                                    onclick="if(confirm('Permanently delete this product? This cannot be undone.')) this.closest('form').submit()"><?= $t['prod_delete'] ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

    <?php else: ?>

        <div class="page-header">
            <h1><?= $t['prod_my_products'] ?></h1>
            <?php if (!empty($businesses)): ?>
                <a href="/products/?action=add" class="btn"><?= $t['prod_add_product'] ?></a>
            <?php endif; ?>
        </div>

        <?php if ($success): ?>
            <p class="form-success"><?= htmlspecialchars($success) ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
            <p class="form-error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <?php if (empty($businesses)): ?>
            <p class="notice"><?= $t['prod_need_business'] ?> <a href="/submit/"><?= $t['prod_submit_business'] ?></a>.</p>
        <?php elseif (empty($products)): ?>
            <p class="notice"><?= $t['vendor_no_products'] ?> <a href="/products/?action=add"><?= $t['vendor_add_product'] ?></a>.</p>
        <?php else: ?>
            <table class="product-table">
                <thead>
                    <tr>
                        <th><?= $t['prod_col_photo'] ?></th>
                        <th class="sortable" data-col="1" data-type="text"><?= $t['vendor_col_name'] ?></th>
                        <th class="sortable" data-col="2" data-type="text"><?= $t['search_category'] ?></th>
                        <th class="sortable" data-col="3" data-type="num"><?= $t['vendor_col_price'] ?></th>
                        <th class="sortable" data-col="4" data-type="num"><?= $t['vendor_col_stock'] ?></th>
                        <th class="sortable" data-col="5" data-type="text"><?= $t['vendor_col_status'] ?></th>
                        <th><?= $t['prod_col_rating'] ?></th>
                    </tr>
                </thead>
                <tbody id="product-tbody">
                <?php foreach ($products as $p): ?>
                    <tr class="product-row <?= $p['active'] ? '' : 'inactive-row' ?>" onclick="location.href='/products/?action=edit&id=<?= $p['id'] ?>'">
                        <td>
                            <?php if ($p['photo']): ?>
                                <img src="/uploads/<?= htmlspecialchars($p['photo']) ?>" alt="" class="thumb">
                            <?php else: ?>
                                <div class="thumb thumb--empty"></div>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars(lang_field($p, 'name')) ?></td>
                        <td><?= htmlspecialchars($p['category_name'] ?? '—') ?></td>
                        <td>$<?= number_format($p['price'], 2) ?></td>
                        <td><?= (int)$p['variant_count'] > 0 ? (int)$p['variant_count'] . ' ' . $t['prod_variants_suffix'] : (int)$p['stock'] ?></td>
                        <td><span class="status <?= $p['active'] ? 'status-active' : 'status-inactive' ?>"><?= $p['active'] ? $t['vendor_status_active'] : $t['vendor_status_inactive'] ?></span></td>
                        <td style="color:#f59e0b;font-size:0.85rem;"><?= $p['review_count'] > 0 ? '★ ' . number_format($p['avg_rating'], 1) . ' (' . (int)$p['review_count'] . ')' : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <script>
            (function () {
                var tbody = document.getElementById('product-tbody');
                if (!tbody) return;
                var currentCol = null, asc = true;

                document.querySelectorAll('.product-table .sortable').forEach(function (th) {
                    th.addEventListener('click', function () {
                        var col  = parseInt(this.dataset.col);
                        var type = this.dataset.type;
                        if (currentCol === col) { asc = !asc; } else { asc = true; }
                        currentCol = col;

                        document.querySelectorAll('.product-table .sortable').forEach(function (h) {
                            h.classList.remove('sort-asc', 'sort-desc');
                        });
                        this.classList.add(asc ? 'sort-asc' : 'sort-desc');

                        var rows = Array.from(tbody.querySelectorAll('tr'));
                        rows.sort(function (a, b) {
                            var av = a.cells[col] ? a.cells[col].textContent.trim() : '';
                            var bv = b.cells[col] ? b.cells[col].textContent.trim() : '';
                            if (type === 'num') {
                                av = parseFloat(av.replace(/[^0-9.-]/g, '')) || 0;
                                bv = parseFloat(bv.replace(/[^0-9.-]/g, '')) || 0;
                                return asc ? av - bv : bv - av;
                            }
                            return asc ? av.localeCompare(bv) : bv.localeCompare(av);
                        });
                        rows.forEach(function (r) { tbody.appendChild(r); });
                    });
                });
            })();
            </script>
        <?php endif; ?>

    <?php endif; ?>

</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
