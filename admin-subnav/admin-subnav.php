<?php
// Admin panel subnav — the single flat tab row, shown under the header on
// every admin page. One tab per page, ordered by how often each queue is
// worked; permission-gated per tab. The header <nav> keeps only the avatar
// and lang menus. Include right after header/header.php. Activity Log and
// Manage Admins stay in the avatar dropdown only.
// Pages set $adminTab (see each page top) for the active state; the header
// has already computed $adminNavAdmin / $adminNavMessages for its mobile
// nav, so reuse them when set.
if (empty($_SESSION['admin_id']) || !function_exists('admin_can')) return;

$adminTab     = $adminTab     ?? '';
$adminSection = $adminSection ?? '';

// Per-tab queues: everything waiting behind that tab.
if (!isset($adminNavAdmin)) {
    $adminNavAdmin = admin_can('vendors')
        ? (int)$pdo->query("SELECT COUNT(*) FROM businesses WHERE deleted_at IS NULL AND (approved = 0 OR (approved = 1 AND approved_at <= NOW() - INTERVAL 7 DAY AND spot_checked_at IS NULL))")->fetchColumn()
        : 0;
}
if (!isset($adminNavMessages)) {
    $adminNavMessages = admin_can('messages')
        ? (int)$pdo->query("SELECT COUNT(*) FROM support_threads WHERE status IN ('pending','open')")->fetchColumn()
        : 0;
}
$asnRefunds  = admin_can('refunds')  ? (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('refund_requested','return_received')")->fetchColumn() : 0;
$asnPayments = admin_can('payments') ? (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending_confirmation'")->fetchColumn() : 0;
$asnPayouts  = admin_can('payouts')  ? (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered' AND delivered_at IS NOT NULL AND delivered_at < DATE_SUB(NOW(), INTERVAL " . PAYOUT_WINDOW_SECONDS . " SECOND)")->fetchColumn() : 0;

// perm => [url, label, active tab key, badge count]
$asnTabs = [
    'orders'      => ['/admin/orders.php',      'Orders',       'orders',      0],
    'refunds'     => ['/admin/refunds.php',     'Refunds',      'refunds',     $asnRefunds],
    'payments'    => ['/admin/payments.php',    'Payments',     'payments',    $asnPayments],
    'payouts'     => ['/admin/payouts.php',     'Payouts',      'payouts',     $asnPayouts],
    'vendors'     => ['/admin/vendors.php',     'Vendors',      'vendors',     $adminNavAdmin],
    'messages'    => ['/admin/messages/',       'Messages',     'messages',    $adminNavMessages],
    'buyers'      => ['/admin/buyers.php',      'Buyers',       'buyers',      0],
    'products'    => ['/admin/products.php',    'Products',     'products',    0],
    'reviews'     => ['/admin/reviews.php',     'Reviews',      'reviews',     0],
    'categories'  => ['/admin/categories.php',  'Categories',   'categories',  0],
    'accounting'  => ['/admin/accounting.php',  'Accounting',   'accounting',  0],
    'promo-codes' => ['/admin/promo-codes.php', 'Promo Codes',  'promo-codes', 0],
    'coupons'     => ['/admin/coupons.php',     'Coupons',      'coupons',     0],
    'banners'     => ['/admin/banners.php',     'Banners',      'banners',     0],
    'prospects'   => ['/admin/prospects/',      'Canvassing',   'prospects',   0],
    'vendor-map'  => ['/admin/vendor-map.php',  'Vendor Map',   'vendor-map',  0],
    'buyer-map'   => ['/admin/buyer-map.php',   'Buyer Map',    'buyer-map',   0],
    'careers'     => ['/admin/careers.php',     'Careers',      'careers',     0],
    'content'     => ['/admin/content.php',     'Pages',        'content',     0],
    'faq'         => ['/admin/faq.php',         'FAQ',          'faq',         0],
];
// Messages pages set $adminSection, not $adminTab.
$asnActive = $adminTab !== '' ? $adminTab : ($adminSection === 'messages' ? 'messages' : '');
?>
<link rel="stylesheet" href="/admin-subnav/admin-subnav.css">
<div class="admin-subnav-wrap">
    <nav class="admin-subnav">
        <?php foreach ($asnTabs as $asnPerm => [$asnUrl, $asnLabel, $asnKey, $asnCount]): if (!admin_can($asnPerm)) continue; ?>
        <a href="<?= $asnUrl ?>" class="<?= $asnActive === $asnKey ? 'active' : '' ?>"><?= $asnCount ? $asnLabel . '&nbsp;<span class="nav-msg-badge">' . $asnCount . '</span>' : $asnLabel ?></a>
        <?php endforeach; ?>
    </nav>
    <?php if (!empty($_GET['denied'])): ?>
    <div class="admin-alert admin-alert--error" style="margin-top:1rem;">You don't have access to that section.</div>
    <?php endif; ?>
</div>
