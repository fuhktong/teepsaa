<?php
$_atSection = $adminSection ?? '';
$_atTab     = $adminTab     ?? '';
$_atPendingVendor = (int)$pdo->query("SELECT COUNT(*) FROM businesses WHERE approved = 0")->fetchColumn();
$_atRefundCount   = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'refund_requested'")->fetchColumn();
$_atPendingPayout = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered' AND delivered_at IS NOT NULL AND delivered_at < DATE_SUB(NOW(), INTERVAL " . PAYOUT_WINDOW_SECONDS . " SECOND)")->fetchColumn();
?>
<?php if ($_atSection === 'admin'): ?>
<nav class="admin-tabs">
    <a href="/admin/"               class="admin-tab <?= $_atTab === 'vendors'    ? 'active' : '' ?>">Vendors<?= $_atPendingVendor > 0 ? ' <span class="admin-tab-badge">' . $_atPendingVendor . '</span>' : '' ?></a>
    <a href="/admin/buyers.php"     class="admin-tab <?= $_atTab === 'buyers'     ? 'active' : '' ?>">Buyers</a>
    <a href="/admin/products.php"   class="admin-tab <?= $_atTab === 'products'   ? 'active' : '' ?>">Products</a>
    <a href="/admin/categories.php" class="admin-tab <?= $_atTab === 'categories' ? 'active' : '' ?>">Categories</a>
    <a href="/admin/reviews.php"    class="admin-tab <?= $_atTab === 'reviews'    ? 'active' : '' ?>">Reviews</a>
</nav>
<?php elseif ($_atSection === 'orders'): ?>
<nav class="admin-tabs">
    <a href="/admin/orders.php"     class="admin-tab <?= $_atTab === 'orders'     ? 'active' : '' ?>">Orders<?= $_atPendingPayout > 0 ? ' <span class="admin-tab-badge">' . $_atPendingPayout . '</span>' : '' ?></a>
    <a href="/admin/refunds.php"    class="admin-tab <?= $_atTab === 'refunds'    ? 'active' : '' ?>">Refunds<?= $_atRefundCount > 0 ? ' <span class="admin-tab-badge">' . $_atRefundCount . '</span>' : '' ?></a>
    <a href="/admin/accounting.php" class="admin-tab <?= $_atTab === 'accounting' ? 'active' : '' ?>">Accounting</a>
    <a href="/admin/payments.php"   class="admin-tab <?= $_atTab === 'payments'   ? 'active' : '' ?>">Payments</a>
    <a href="/admin/payouts.php"    class="admin-tab <?= $_atTab === 'payouts'    ? 'active' : '' ?>">Payouts</a>
</nav>
<?php elseif ($_atSection === 'marketing'): ?>
<nav class="admin-tabs">
    <a href="/admin/promo-codes.php" class="admin-tab <?= $_atTab === 'promo-codes' ? 'active' : '' ?>">Promo Codes</a>
    <a href="/admin/banners.php"     class="admin-tab <?= $_atTab === 'banners'     ? 'active' : '' ?>">Banners</a>
    <a href="/admin/vendor-map.php"  class="admin-tab <?= $_atTab === 'vendor-map'  ? 'active' : '' ?>">Vendor Map</a>
    <a href="/admin/buyer-map.php"   class="admin-tab <?= $_atTab === 'buyer-map'   ? 'active' : '' ?>">Buyer Map</a>
</nav>
<?php endif; ?>
