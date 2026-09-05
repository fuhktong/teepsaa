<?php
// Vendor portal subnav — the main vendor tabs, shown under the header on every
// vendor page. The header <nav> keeps only the bell, avatar and lang menus.
// Include right after header/header.php; the header already computes the badge
// counts and active section for its mobile nav, so reuse them when set.
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') return;

if (!isset($t)) {
    $vsnLang = current_lang();
    $t = require __DIR__ . '/../lang/' . (in_array($vsnLang, ['en', 'km']) ? $vsnLang : 'en') . '.php';
}

$vsnPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
if (!isset($vendorSection)) {
    $vendorSection = '';
    if (strpos($vsnPath, '/orders-vendor/') === 0)        $vendorSection = 'orders';
    elseif (strpos($vsnPath, '/products/') === 0)         $vendorSection = 'products';
    elseif (strpos($vsnPath, '/messages-vendor/') === 0)  $vendorSection = 'messages';
    elseif (strpos($vsnPath, '/analytics/') === 0)        $vendorSection = 'analytics';
    elseif (strpos($vsnPath, '/business-vendor/') === 0)  $vendorSection = 'business';
}

if (!isset($vendorOrdersTodo)) {
    $vsnStmt = $pdo->prepare("SELECT COUNT(*) FROM orders o JOIN businesses b ON b.id = o.business_id WHERE b.user_id = ? AND o.status IN ('paid','return_dispatched')");
    $vsnStmt->execute([$_SESSION['user_id']]);
    $vendorOrdersTodo = (int)$vsnStmt->fetchColumn();
}
if (!isset($vendorUnread)) {
    $vsnStmt = $pdo->prepare('SELECT COUNT(*) FROM support_messages sm JOIN support_threads t ON t.id = sm.thread_id WHERE t.sender_id = ? AND t.sender_role = \'vendor\' AND sm.sender = \'admin\' AND sm.read_at IS NULL');
    $vsnStmt->execute([$_SESSION['user_id']]);
    $vendorUnread = (int)$vsnStmt->fetchColumn();
}

// Storefront link only when the shop is actually live
$vsnStmt = $pdo->prepare('SELECT public_id FROM businesses WHERE user_id = ? AND deleted_at IS NULL AND approved = 1 AND suspended = 0');
$vsnStmt->execute([$_SESSION['user_id']]);
$vsnStorefrontId = $vsnStmt->fetchColumn();
?>
<link rel="stylesheet" href="/vendor-subnav/vendor-subnav.css">
<div class="vendor-subnav-wrap">
    <nav class="vendor-subnav">
        <a href="/orders-vendor/" class="<?= $vendorSection === 'orders' ? 'active' : '' ?>"><?= $vendorOrdersTodo ? $t['nav_orders'] . '&nbsp;<span class="nav-msg-badge">' . $vendorOrdersTodo . '</span>' : $t['nav_orders'] ?></a>
        <a href="/products/" class="<?= $vendorSection === 'products' ? 'active' : '' ?>"><?= $t['nav_products'] ?></a>
        <a href="/messages-vendor/" class="<?= $vendorSection === 'messages' ? 'active' : '' ?>"><?= $vendorUnread ? $t['nav_messages'] . '&nbsp;<span class="nav-msg-badge">' . $vendorUnread . '</span>' : $t['nav_messages'] ?></a>
        <a href="/analytics/" class="<?= $vendorSection === 'analytics' ? 'active' : '' ?>"><?= $t['nav_vendor'] ?></a>
        <a href="/business-vendor/" class="<?= $vendorSection === 'business' ? 'active' : '' ?>"><?= $t['vendor_settings_tab_business'] ?></a>
        <?php if ($vsnStorefrontId): ?>
        <a href="/business/?id=<?= htmlspecialchars($vsnStorefrontId) ?>" target="_blank" rel="noopener"><?= $t['nav_storefront'] ?></a>
        <?php endif; ?>
    </nav>
</div>
