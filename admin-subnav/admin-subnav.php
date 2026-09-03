<?php
// Admin panel subnav — the five section tabs (Admin, Orders, Marketing,
// Content, Messages), shown under the header on every admin page. The header
// <nav> keeps only the avatar and lang menus. Include right after
// header/header.php; the header already computes the badge counts and active
// section for its mobile nav, so reuse them when set.
if (empty($_SESSION['admin_id']) || !function_exists('admin_can')) return;

if (!isset($t)) {
    $asnLang = $_SESSION['lang'] ?? 'km';
    $t = require __DIR__ . '/../lang/' . (in_array($asnLang, ['en', 'km']) ? $asnLang : 'en') . '.php';
}

$adminSection = $adminSection ?? '';

if (!isset($adminNavMessages)) {
    $adminNavMessages = admin_can('messages')
        ? (int)$pdo->query("SELECT COUNT(*) FROM support_threads WHERE status IN ('pending','open')")->fetchColumn()
        : 0;
}
if (!isset($adminNavAdmin)) {
    $adminNavAdmin = admin_can('vendors')
        ? (int)$pdo->query("SELECT COUNT(*) FROM businesses WHERE deleted_at IS NULL AND (approved = 0 OR (approved = 1 AND approved_at <= NOW() - INTERVAL 7 DAY AND spot_checked_at IS NULL))")->fetchColumn()
        : 0;
}
if (!isset($adminNavOrders)) {
    $adminNavOrders = 0;
    if (admin_can('payments')) $adminNavOrders += (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending_confirmation'")->fetchColumn();
    if (admin_can('refunds'))  $adminNavOrders += (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('refund_requested','return_received')")->fetchColumn();
    if (admin_can('payouts'))  $adminNavOrders += (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered' AND delivered_at IS NOT NULL AND delivered_at < DATE_SUB(NOW(), INTERVAL " . PAYOUT_WINDOW_SECONDS . " SECOND)")->fetchColumn();
}
?>
<link rel="stylesheet" href="/admin-subnav/admin-subnav.css">
<div class="admin-subnav-wrap">
    <nav class="admin-subnav">
        <?php if (admin_can('vendors')): ?><a href="/admin/vendors.php" class="<?= $adminSection === 'admin' ? 'active' : '' ?>"><?= $adminNavAdmin ? $t['nav_admin'] . '&nbsp;<span class="nav-msg-badge">' . $adminNavAdmin . '</span>' : $t['nav_admin'] ?></a><?php endif; ?>
        <?php if (admin_can('orders')): ?><a href="/admin/orders.php" class="<?= $adminSection === 'orders' ? 'active' : '' ?>"><?= $adminNavOrders ? $t['nav_orders'] . '&nbsp;<span class="nav-msg-badge">' . $adminNavOrders . '</span>' : $t['nav_orders'] ?></a><?php endif; ?>
        <?php if (admin_can('promo-codes')): ?><a href="/admin/promo-codes.php" class="<?= $adminSection === 'marketing' ? 'active' : '' ?>"><?= $t['nav_marketing'] ?></a><?php endif; ?>
        <?php if (admin_can('content')): ?><a href="/admin/content.php" class="<?= $adminSection === 'content' ? 'active' : '' ?>"><?= $t['nav_content'] ?></a><?php endif; ?>
        <?php if (admin_can('messages')): ?><a href="/admin/messages/" class="<?= $adminSection === 'messages' ? 'active' : '' ?>"><?= $adminNavMessages ? $t['nav_messages'] . '&nbsp;<span class="nav-msg-badge">' . $adminNavMessages . '</span>' : $t['nav_messages'] ?></a><?php endif; ?>
    </nav>
</div>
