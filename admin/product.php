<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/admin-auth.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: /login-admin/');
    exit;
}

admin_require('products');

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /admin/products.php'); exit; }

$stmt = $pdo->prepare('
    SELECT p.id, p.name AS product_name, p.description, p.price, p.stock, p.active,
           p.delivery_method, p.royalty_add_on AS product_royalty_add_on,
           c.name AS category_name, c.royalty_rate AS category_rate,
           b.id AS business_id, b.name AS business_name,
           b.royalty_add_on AS company_royalty_add_on,
           v.id AS vendor_id, v.name AS vendor_name, v.email AS vendor_email
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    JOIN businesses b ON b.id = p.business_id
    JOIN vendors v ON v.id = b.user_id
    WHERE p.id = ?
');
$stmt->execute([$id]);
$p = $stmt->fetch();
if (!$p) { header('Location: /admin/products.php'); exit; }

$photos = [];
$stmt = $pdo->prepare('SELECT id, filename, is_primary FROM product_photos WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC, id ASC');
$stmt->execute([$id]);
$photos = $stmt->fetchAll();

$success = $_SESSION['admin_success'] ?? '';
$error   = $_SESSION['admin_error']   ?? '';
unset($_SESSION['admin_success'], $_SESSION['admin_error']);

$rStmt = $pdo->prepare('
    SELECT r.id, r.rating, r.comment, r.created_at, b.name AS buyer_name
    FROM reviews r
    JOIN buyers b ON b.id = r.buyer_id
    WHERE r.product_id = ?
    ORDER BY r.created_at DESC
');
$rStmt->execute([$id]);
$productReviews = $rStmt->fetchAll();

$refundCount        = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'refund_requested'")->fetchColumn();
$pendingPayoutCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered' AND delivered_at IS NOT NULL AND delivered_at < DATE_SUB(NOW(), INTERVAL " . PAYOUT_WINDOW_SECONDS . " SECOND)")->fetchColumn();
$pendingVendorCount = (int)$pdo->query("SELECT COUNT(*) FROM businesses WHERE approved = 0")->fetchColumn();

$badgeClass = $p['active'] ? 'badge-green' : 'badge-grey';
$badgeLabel = $p['active'] ? 'Active' : 'Inactive';

$catRate     = (float)($p['category_rate'] ?? 0);
$companyRate = (float)$p['company_royalty_add_on'];
$productRate = (float)$p['product_royalty_add_on'];
$totalRate   = $catRate + $companyRate + $productRate;
$adminSection = 'admin';
$adminTab     = 'products';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — <?= htmlspecialchars($p['product_name']) ?></title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/admin/admin.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <?php require __DIR__ . '/admin-tabs.php'; ?>


    <?php if ($success): ?><p class="admin-success"><?= htmlspecialchars($success) ?></p><?php endif; ?>
    <?php if ($error):   ?><p class="admin-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

    <div class="detail-header">
        <h1><?= htmlspecialchars($p['product_name']) ?></h1>
        <span class="order-badge <?= $badgeClass ?>"><?= $badgeLabel ?></span>
    </div>

    <div class="detail-columns">
        <div>
            <!-- Product info -->
            <div class="detail-card">
                <div class="detail-card-title">Product</div>
                <div class="detail-row"><span class="detail-row-label">Price</span><span class="detail-row-value">$<?= number_format($p['price'], 2) ?></span></div>
                <div class="detail-row"><span class="detail-row-label">Stock</span><span class="detail-row-value"><?= (int)$p['stock'] ?></span></div>
                <div class="detail-row"><span class="detail-row-label">Delivery</span><span class="detail-row-value"><?= $p['delivery_method'] === 'tuktuk' ? 'Grab Tuk-Tuk' : 'Grab Bike' ?></span></div>
                <?php if ($p['category_name']): ?>
                <div class="detail-row"><span class="detail-row-label">Category</span><span class="detail-row-value"><?= htmlspecialchars($p['category_name']) ?></span></div>
                <?php endif; ?>
                <?php if ($p['description']): ?>
                <div class="detail-row"><span class="detail-row-label">Description</span><span class="detail-row-value"><?= htmlspecialchars($p['description']) ?></span></div>
                <?php endif; ?>
            </div>

            <!-- Sold by -->
            <div class="detail-card">
                <div class="detail-card-title">Sold by</div>
                <div class="detail-row">
                    <span class="detail-row-label">Business</span>
                    <span class="detail-row-value"><a href="/admin/vendor.php?id=<?= $p['vendor_id'] ?>"><?= htmlspecialchars($p['business_name']) ?></a></span>
                </div>
                <div class="detail-row"><span class="detail-row-label">Vendor</span><span class="detail-row-value"><?= htmlspecialchars($p['vendor_name'] ?: $p['vendor_email']) ?></span></div>
                <div class="detail-row"><span class="detail-row-label">Email</span><span class="detail-row-value"><?= htmlspecialchars($p['vendor_email']) ?></span></div>
            </div>

            <!-- Reviews -->
            <div class="detail-card">
                <div class="detail-card-title">Reviews (<?= count($productReviews) ?>)</div>
                <?php if (empty($productReviews)): ?>
                <p style="font-size:0.875rem;color:#9ca3af;margin:0;">No reviews yet.</p>
                <?php else: ?>
                <div class="admin-reviews-list">
                    <?php foreach ($productReviews as $r):
                        $nameParts   = explode(' ', trim($r['buyer_name']));
                        $displayName = $nameParts[0] . (count($nameParts) > 1 ? ' ' . strtoupper(substr(end($nameParts), 0, 1)) . '.' : '');
                    ?>
                    <div class="admin-review-row">
                        <div class="admin-review-meta">
                            <span class="admin-review-stars"><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?></span>
                            <span style="font-size:0.875rem;font-weight:600;"><?= htmlspecialchars($displayName) ?></span>
                            <span style="font-size:0.8rem;color:#9ca3af;"><?= date('M j, Y', strtotime($r['created_at'])) ?></span>
                        </div>
                        <?php if ($r['comment']): ?>
                        <p class="admin-review-comment"><?= htmlspecialchars($r['comment']) ?></p>
                        <?php endif; ?>
                        <form method="POST" action="/admin/review-action.php" onsubmit="return confirm('Delete this review?')">
                            <?= csrf_input() ?>
                            <input type="hidden" name="review_id" value="<?= $r['id'] ?>">
                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                            <button type="submit" class="btn-reject" style="font-size:0.75rem;padding:0.2rem 0.6rem;">Delete</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($photos)): ?>
            <div class="detail-card">
                <div class="detail-card-title">Photos</div>
                <div class="detail-photos">
                    <?php foreach ($photos as $ph): ?>
                    <div class="detail-photo-wrap">
                        <img src="/uploads/<?= htmlspecialchars($ph['filename']) ?>" alt="">
                        <form method="POST" action="/admin/product-action.php" onsubmit="return confirm('Remove this photo?')">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="delete_photo">
                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                            <input type="hidden" name="photo_id" value="<?= $ph['id'] ?>">
                            <button type="submit" class="detail-photo-delete" title="Remove">&times;</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div>
            <!-- Royalty breakdown -->
            <div class="detail-card">
                <div class="detail-card-title">Royalty rates</div>
                <div class="detail-row">
                    <span class="detail-row-label">Category rate</span>
                    <span class="detail-row-value"><?= $catRate > 0 ? number_format($catRate * 100, 1) . '%' : '—' ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-row-label">Company add-on</span>
                    <span class="detail-row-value"><?= $companyRate > 0 ? '+' . number_format($companyRate * 100, 1) . '%' : '—' ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-row-label">Product add-on</span>
                    <span class="detail-row-value"><?= $productRate > 0 ? '+' . number_format($productRate * 100, 1) . '%' : '—' ?></span>
                </div>
                <div class="detail-row" style="font-weight:700;">
                    <span class="detail-row-label">Total rate</span>
                    <span class="detail-row-value"><?= number_format($totalRate * 100, 1) ?>%</span>
                </div>
                <?php if ($totalRate > 0): ?>
                <p style="font-size:0.8rem;color:#6b7280;margin:0.5rem 0 0">
                    At $<?= number_format($p['price'], 2) ?> → vendor receives ~$<?= number_format($p['price'] * (1 - $totalRate), 2) ?> per unit (before penalties)
                </p>
                <?php endif; ?>
            </div>

            <!-- Product royalty add-on -->
            <div class="detail-card">
                <div class="detail-card-title">Product royalty add-on</div>
                <p style="font-size:0.875rem;color:#6b7280;margin:0 0 0.75rem">Added on top of the category rate and company add-on for this product only.</p>
                <form method="POST" action="/admin/product-action.php" class="add-on-form">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="set_royalty_add_on">
                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                    <input type="number" name="royalty_add_on" min="0" max="100" step="1"
                           value="<?= number_format($productRate * 100, 1) ?>">
                    <span class="add-on-pct">%</span>
                    <button type="submit" class="btn-approve">Save</button>
                </form>
            </div>

            <!-- Toggle active -->
            <div class="detail-card">
                <div class="detail-card-title">Status</div>
                <div class="detail-row"><span class="detail-row-label">Current</span><span class="detail-row-value"><span class="order-badge <?= $badgeClass ?>"><?= $badgeLabel ?></span></span></div>
                <form method="POST" action="/admin/product-action.php" style="margin-top:0.75rem;">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="toggle_active">
                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                    <button type="submit" class="<?= $p['active'] ? 'btn-reject' : 'btn-approve' ?>" style="width:100%;">
                        <?= $p['active'] ? 'Deactivate' : 'Activate' ?>
                    </button>
                </form>
            </div>

            <!-- Delete product -->
            <div class="detail-card">
                <div class="detail-card-title">Delete product</div>
                <p style="font-size:0.875rem;color:#6b7280;margin:0 0 0.75rem;">Permanently removes this product and all its photos. Cannot be undone.</p>
                <form method="POST" action="/admin/product-action.php" onsubmit="return confirm('Permanently delete this product and all its photos? This cannot be undone.')">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="delete_product">
                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                    <button type="submit" class="btn-reject" style="width:100%;">Delete product</button>
                </form>
            </div>
        </div>
    </div>

</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
