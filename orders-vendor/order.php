<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header('Location: /login-vendor/');
    exit;
}

$userId  = $_SESSION['user_id'];

// Translations loaded early — status badge label is built before the header.
$lang = $_SESSION['lang'] ?? 'km';
$t = require __DIR__ . '/../lang/' . (in_array($lang, ['en', 'km']) ? $lang : 'en') . '.php';

$publicId = $_GET['id'] ?? '';
if ($publicId === '') {
    header('Location: /orders-vendor/');
    exit;
}

$stmt = $pdo->prepare('
    SELECT o.id, o.subtotal, o.delivery_fee, o.vendor_delivery_bonus,
           o.royalty_rate, o.royalty_amount, o.vendor_payout,
           o.coupon_code, o.discount_amount,
           o.status, o.created_at, o.tracking_url, o.buyer_notes,
           b.name AS business_name,
           u.name AS buyer_name, u.email AS buyer_email, u.phone AS buyer_phone,
           u.house_number AS buyer_house_number, u.address AS buyer_address,
           u.address_notes AS buyer_address_notes,
           u.khan AS buyer_khan, u.sangkat AS buyer_sangkat
    FROM orders o
    JOIN businesses b ON b.id = o.business_id
    JOIN buyers u ON u.id = o.buyer_user_id
    WHERE o.public_id = ? AND b.user_id = ?
      AND o.status IN (\'pending\', \'paid\', \'dispatched\', \'delivered\', \'completed\')
');
$stmt->execute([$publicId, $userId]);
$o = $stmt->fetch();

if (!$o) {
    header('Location: /orders-vendor/');
    exit;
}
$orderId = (int)$o['id'];

$stmt = $pdo->prepare('SELECT product_name, product_name_km, variant_label, variant_label_km, quantity, price_at_purchase FROM order_items WHERE order_id = ? ORDER BY id');
$stmt->execute([$orderId]);
$items = $stmt->fetchAll();

$bizStmt = $pdo->prepare('SELECT id FROM businesses WHERE user_id = ? AND approved = 1');
$bizStmt->execute([$userId]);
$bizIds = array_column($bizStmt->fetchAll(), 'id');
$vendorRefundCount = 0;
if (!empty($bizIds)) {
    $ph = implode(',', array_fill(0, count($bizIds), '?'));
    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE business_id IN ($ph) AND status IN ('return_dispatched')");
    $cntStmt->execute(array_values($bizIds));
    $vendorRefundCount = (int)$cntStmt->fetchColumn();
}

$oid = date('ymd', strtotime($o['created_at'])) . '-' . str_pad($o['id'], 4, '0', STR_PAD_LEFT);

$royaltyAmt   = round((float)($o['royalty_amount'] ?? ($o['subtotal'] * ($o['royalty_rate'] ?? 0))), 2);
$royaltyPct   = round(($o['royalty_rate'] ?? 0) * 100, 1);
// A vendor-owned coupon is deducted from vendor_payout at checkout (a sitewide/admin
// coupon isn't — the platform absorbs that one). Derive the vendor-funded portion from
// the stored numbers so this recomputed breakdown stays correct either way.
$vendorCouponDiscount = max(0, round($o['subtotal'] - $royaltyAmt - (float)$o['vendor_payout'], 2));
$vendorPayout = round($o['subtotal'] - $royaltyAmt - $vendorCouponDiscount + $o['delivery_fee'] + $o['vendor_delivery_bonus'], 2);

$grabParts = array_filter([
    trim(($o['buyer_house_number'] ?? '') . ' ' . ($o['buyer_address'] ?? '')),
    $o['buyer_sangkat'] ?? '',
    $o['buyer_khan'] ?? '',
    'Phnom Penh',
]);
$grabAddress = implode(', ', $grabParts);

$statusClasses = [
    'pending'    => 'badge-grey',
    'paid'       => 'badge-blue',
    'dispatched' => 'badge-yellow',
    'delivered'  => 'badge-green',
    'completed'  => 'badge-green',
    'cancelled'  => 'badge-red',
];
$statusClass = $statusClasses[$o['status']] ?? 'badge-grey';
$statusLabel = $t['order_badge_' . $o['status']] ?? ucwords(str_replace('_', ' ', $o['status']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $oid ?> — Orders — teepsaa</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/order-status/order-status.css">
    <link rel="stylesheet" href="/popup/popup.css">
    <link rel="stylesheet" href="/dashboard-vendor/dashboard-vendor.css">
    <link rel="stylesheet" href="/products/products.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <nav class="products-subnav">
        <a href="/orders-vendor/" class="active"><?= $t['vendor_orders'] ?></a>
        <a href="/orders-vendor/?tab=refunds"><?= $t['vendor_refunds'] ?><?php if ($vendorRefundCount > 0): ?> <span class="admin-tab-badge"><?= $vendorRefundCount ?></span><?php endif; ?></a>
    </nav>

    <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1.5rem;flex-wrap:wrap;">
        <h1 style="margin-bottom:0;"><?= $oid ?></h1>
        <span class="order-badge <?= $statusClass ?>"><?= $statusLabel ?></span>
    </div>

    <div class="popup-section">
        <div class="popup-section-label"><?= $t['order_info'] ?></div>
        <div class="popup-row"><span class="popup-row-label"><?= $t['order_date'] ?></span><span class="popup-row-value"><?= fmt_date('M j, Y g:ia', strtotime($o['created_at'])) ?></span></div>
        <div class="popup-row"><span class="popup-row-label"><?= $t['vorder_customer'] ?></span><span class="popup-row-value"><?= htmlspecialchars($o['buyer_name'] ?: $o['buyer_email']) ?></span></div>
        <?php if ($o['buyer_phone']): ?>
        <div class="popup-row"><span class="popup-row-label"><?= $t['settings_phone'] ?></span><span class="popup-row-value"><?= htmlspecialchars($o['buyer_phone']) ?></span></div>
        <?php endif; ?>
    </div>

    <div class="popup-section">
        <div class="popup-section-label"><?= $t['settings_delivery_address'] ?></div>
        <?php if ($grabAddress && $grabAddress !== 'Phnom Penh'): ?>
        <div class="popup-row"><span class="popup-row-label"><?= $t['vorder_grab_address'] ?></span><span class="popup-row-value"><?= htmlspecialchars($grabAddress) ?></span></div>
        <?php endif; ?>
        <?php if ($o['buyer_address_notes']): ?>
        <div class="popup-row"><span class="popup-row-label"><?= $t['vorder_floor_unit'] ?></span><span class="popup-row-value"><?= htmlspecialchars($o['buyer_address_notes']) ?></span></div>
        <?php endif; ?>
        <?php if ($o['buyer_notes']): ?>
        <div class="popup-row" style="background:#fefce8"><span class="popup-row-label"><?= $t['vorder_delivery_note'] ?></span><span class="popup-row-value"><?= htmlspecialchars($o['buyer_notes']) ?></span></div>
        <?php endif; ?>
        <?php if (!$o['buyer_address'] && !$o['buyer_khan']): ?>
        <p style="font-size:0.875rem;color:#9ca3af;margin:0;"><?= $t['vorder_no_address'] ?></p>
        <?php endif; ?>
    </div>

    <?php if (!empty($items)): ?>
    <div class="popup-section">
        <div class="popup-section-label"><?= $t['order_items'] ?></div>
        <table class="popup-items">
            <thead><tr><th><?= $t['order_col_product'] ?></th><th><?= $t['order_col_qty'] ?></th><th><?= $t['order_col_price'] ?></th><th><?= $t['order_col_total'] ?></th></tr></thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <?= htmlspecialchars(pick_lang($item['product_name'], $item['product_name_km'] ?? null)) ?>
                        <?php if ($item['variant_label']): ?>
                            <br><small style="color:#9ca3af"><?= htmlspecialchars(pick_lang($item['variant_label'], $item['variant_label_km'] ?? null)) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= (int)$item['quantity'] ?></td>
                    <td>$<?= number_format($item['price_at_purchase'], 2) ?></td>
                    <td>$<?= number_format($item['price_at_purchase'] * $item['quantity'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="popup-subtotal"><span><?= $t['checkout_subtotal'] ?></span><span>$<?= number_format($o['subtotal'], 2) ?></span></div>
        <?php if ($o['discount_amount'] > 0): ?>
        <div class="popup-subtotal"><span><?= $t['checkout_coupon_applied'] ?> <?= htmlspecialchars($o['coupon_code']) ?></span><span>&minus;$<?= number_format($o['discount_amount'], 2) ?></span></div>
        <?php endif; ?>
        <?php if ($o['delivery_fee'] > 0): ?>
        <div class="popup-subtotal"><span><?= $t['order_delivery'] ?></span><span>$<?= number_format($o['delivery_fee'], 2) ?></span></div>
        <?php endif; ?>
        <div class="popup-total"><span><?= $t['checkout_total'] ?></span><span>$<?= number_format($o['subtotal'] - $o['discount_amount'] + $o['delivery_fee'], 2) ?></span></div>

        <div class="popup-payout-box">
            <?php if ($royaltyPct > 0): ?>
            <div class="popup-subtotal"><span><?= $t['vorder_royalty_fee'] ?> (<?= $royaltyPct ?>%)</span><span>−$<?= number_format($royaltyAmt, 2) ?></span></div>
            <?php endif; ?>
            <?php if ($vendorCouponDiscount > 0): ?>
            <div class="popup-subtotal"><span><?= $t['checkout_coupon_applied'] ?> <?= htmlspecialchars($o['coupon_code']) ?></span><span>−$<?= number_format($vendorCouponDiscount, 2) ?></span></div>
            <?php endif; ?>
            <?php if ($o['delivery_fee'] > 0): ?>
            <div class="popup-subtotal"><span><?= $t['vorder_delivery_reimburse'] ?></span><span>+$<?= number_format($o['delivery_fee'], 2) ?></span></div>
            <?php endif; ?>
            <?php if ($o['vendor_delivery_bonus'] > 0): ?>
            <div class="popup-subtotal"><span><?= $t['vorder_delivery_buffer'] ?></span><span>+$<?= number_format($o['vendor_delivery_bonus'], 2) ?></span></div>
            <?php endif; ?>
            <div class="popup-total popup-total--payout"><span><?= $t['vorder_your_payout'] ?></span><span>$<?= number_format($vendorPayout, 2) ?></span></div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($o['tracking_url'] && in_array($o['status'], ['dispatched', 'delivered', 'completed'])): ?>
    <div class="popup-section">
        <div class="popup-section-label"><?= $t['vorder_tracking_link'] ?></div>
        <div class="popup-row"><span class="popup-row-label">Grab</span><span class="popup-row-value"><a href="<?= htmlspecialchars($o['tracking_url']) ?>" target="_blank" rel="noopener"><?= $t['vorder_view_tracking'] ?></a></span></div>
    </div>
    <?php endif; ?>

    <div class="popup-section">
        <div class="popup-section-label"><?= $t['order_status_heading'] ?></div>
        <?php $orderStatus = $o['status']; require __DIR__ . '/../order-status/order-status.php'; ?>
    </div>

    <?php if ($o['status'] === 'paid'): ?>
    <hr class="popup-divider">
    <div class="popup-section-label" style="margin-bottom:0.5rem;"><?= $t['vorder_dispatch'] ?></div>
    <div class="dispatch-cod-warning">
        <?= $t['vorder_cod_warning'] ?>
    </div>
    <p style="font-size:0.85rem;color:#6b7280;margin:0 0 0.75rem;"><?= $t['vorder_dispatch_hint'] ?></p>
    <form method="POST" action="/dashboard-vendor/dispatch.php" class="dispatch-form">
        <?= csrf_input() ?>
        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
        <input type="url" name="tracking_url" required placeholder="<?= htmlspecialchars($t['vorder_tracking_placeholder']) ?>"
               oninput="this.closest('form').querySelector('[type=submit]').disabled=!this.value.trim()">
        <button type="submit" class="btn-dispatch" disabled><?= $t['vorder_mark_dispatched'] ?></button>
    </form>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
