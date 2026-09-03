<?php
// Second-level tabs for the multi-page sections (Orders, Marketing,
// Canvassing, Content). Single-page sections — Vendors, Buyers, Products,
// Categories, Reviews, Messages — have no row here; their main tab in
// /admin-subnav/ is the whole navigation.
$_atSection = $adminSection ?? '';
$_atTab     = $adminTab     ?? '';
// Refunds badge = requests to review + returns received awaiting the refund transfer
$_atRefundCount    = admin_can('refunds') ? (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('refund_requested','return_received')")->fetchColumn() : 0;
$_atPendingPayment = admin_can('payments') ? (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending_confirmation'")->fetchColumn() : 0;
$_atPendingPayout  = admin_can('payouts') ? (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered' AND delivered_at IS NOT NULL AND delivered_at < DATE_SUB(NOW(), INTERVAL " . PAYOUT_WINDOW_SECONDS . " SECOND)")->fetchColumn() : 0;
?>
<?php /* the ?denied=1 alert renders in admin-subnav, not here */ ?>
<?php if ($_atSection === 'orders'): ?>
<nav class="admin-tabs">
    <?php if (admin_can('orders')): ?><a href="/admin/orders.php"     class="admin-tab <?= $_atTab === 'orders'     ? 'active' : '' ?>">Orders</a><?php endif; ?>
    <?php if (admin_can('refunds')): ?><a href="/admin/refunds.php"    class="admin-tab <?= $_atTab === 'refunds'    ? 'active' : '' ?>">Refunds<?= $_atRefundCount > 0 ? ' <span class="admin-tab-badge">' . $_atRefundCount . '</span>' : '' ?></a><?php endif; ?>
    <?php if (admin_can('accounting')): ?><a href="/admin/accounting.php" class="admin-tab <?= $_atTab === 'accounting' ? 'active' : '' ?>">Accounting</a><?php endif; ?>
    <?php if (admin_can('payments')): ?><a href="/admin/payments.php"   class="admin-tab <?= $_atTab === 'payments'   ? 'active' : '' ?>">Payments<?= $_atPendingPayment > 0 ? ' <span class="admin-tab-badge">' . $_atPendingPayment . '</span>' : '' ?></a><?php endif; ?>
    <?php if (admin_can('payouts')): ?><a href="/admin/payouts.php"    class="admin-tab <?= $_atTab === 'payouts'    ? 'active' : '' ?>">Payouts<?= $_atPendingPayout > 0 ? ' <span class="admin-tab-badge">' . $_atPendingPayout . '</span>' : '' ?></a><?php endif; ?>
    <?php if (admin_can('audit')): ?><a href="/admin/audit.php"      class="admin-tab <?= $_atTab === 'audit'      ? 'active' : '' ?>">Activity Log</a><?php endif; ?>
</nav>
<?php elseif ($_atSection === 'marketing'): ?>
<nav class="admin-tabs">
    <?php if (admin_can('promo-codes')): ?><a href="/admin/promo-codes.php" class="admin-tab <?= $_atTab === 'promo-codes' ? 'active' : '' ?>">Promo Codes</a><?php endif; ?>
    <?php if (admin_can('coupons')): ?><a href="/admin/coupons.php"     class="admin-tab <?= $_atTab === 'coupons'     ? 'active' : '' ?>">Coupons</a><?php endif; ?>
    <?php if (admin_can('banners')): ?><a href="/admin/banners.php"     class="admin-tab <?= $_atTab === 'banners'     ? 'active' : '' ?>">Banners</a><?php endif; ?>
    <?php if (admin_can('careers')): ?><a href="/admin/careers.php"     class="admin-tab <?= $_atTab === 'careers'     ? 'active' : '' ?>">Careers</a><?php endif; ?>
</nav>
<?php elseif ($_atSection === 'canvassing'): ?>
<nav class="admin-tabs">
    <?php if (admin_can('vendor-map')): ?><a href="/admin/vendor-map.php" class="admin-tab <?= $_atTab === 'vendor-map' ? 'active' : '' ?>">Vendor Map</a><?php endif; ?>
    <?php if (admin_can('buyer-map')): ?><a href="/admin/buyer-map.php"  class="admin-tab <?= $_atTab === 'buyer-map'  ? 'active' : '' ?>">Buyer Map</a><?php endif; ?>
    <?php if (admin_can('prospects')): ?><a href="/admin/prospects/"     class="admin-tab <?= $_atTab === 'prospects'  ? 'active' : '' ?>">Canvassing</a><?php endif; ?>
</nav>
<?php elseif ($_atSection === 'content'): ?>
<nav class="admin-tabs">
    <?php if (admin_can('content')): ?><a href="/admin/content.php" class="admin-tab <?= $_atTab === 'content' ? 'active' : '' ?>">Pages</a><?php endif; ?>
    <?php if (admin_can('faq')): ?><a href="/admin/faq.php"     class="admin-tab <?= $_atTab === 'faq'     ? 'active' : '' ?>">FAQ</a><?php endif; ?>
</nav>
<?php endif; ?>
