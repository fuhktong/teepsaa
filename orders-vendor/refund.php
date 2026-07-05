<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header('Location: /login-vendor/');
    exit;
}

// Translations loaded early — status badge label is built before the header.
$lang = $_SESSION['lang'] ?? 'km';
$t = require __DIR__ . '/../lang/' . (in_array($lang, ['en', 'km']) ? $lang : 'en') . '.php';

$userId   = $_SESSION['user_id'];
$publicId = $_GET['id'] ?? '';
if ($publicId === '') {
    header('Location: /orders-vendor/?tab=refunds');
    exit;
}

$refundStatuses = ['refund_requested','return_approved','return_dispatched','return_received','refunded','refund_rejected'];
$statusIn = implode(',', array_map([$pdo, 'quote'], $refundStatuses));

$stmt = $pdo->prepare("
    SELECT o.id, o.subtotal, o.delivery_fee, o.status, o.created_at,
           o.refund_reason, o.return_tracking_url, o.coupon_code, o.discount_amount,
           b.name AS business_name,
           u.name AS buyer_name, u.email AS buyer_email
    FROM orders o
    JOIN businesses b ON b.id = o.business_id
    JOIN buyers u ON u.id = o.buyer_user_id
    WHERE o.public_id = ? AND b.user_id = ? AND o.status IN ($statusIn)
");
$stmt->execute([$publicId, $userId]);
$o = $stmt->fetch();

if (!$o) {
    header('Location: /orders-vendor/?tab=refunds');
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

$statusClasses = [
    'refund_requested'  => 'badge-red',
    'return_approved'   => 'badge-yellow',
    'return_dispatched' => 'badge-yellow',
    'return_received'   => 'badge-blue',
    'refunded'          => 'badge-green',
    'refund_rejected'   => 'badge-grey',
];
$statusClass = $statusClasses[$o['status']] ?? 'badge-grey';
$statusLabel = $t['order_badge_' . $o['status']] ?? ucwords(str_replace('_', ' ', $o['status']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $oid ?> — Refund — teepsaa</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/refund-status/refund-status.css">
    <link rel="stylesheet" href="/popup/popup.css">
    <link rel="stylesheet" href="/dashboard-vendor/dashboard-vendor.css">
    <link rel="stylesheet" href="/products/products.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <nav class="products-subnav">
        <a href="/orders-vendor/"><?= $t['vendor_orders'] ?></a>
        <a href="/orders-vendor/?tab=refunds" class="active"><?= $t['vendor_refunds'] ?><?php if ($vendorRefundCount > 0): ?> <span class="admin-tab-badge"><?= $vendorRefundCount ?></span><?php endif; ?></a>
    </nav>

    <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1.5rem;flex-wrap:wrap;">
        <h1 style="margin-bottom:0;"><?= $oid ?> — <?= $t['vorder_refund_word'] ?></h1>
        <span class="order-badge <?= $statusClass ?>"><?= $statusLabel ?></span>
    </div>

    <div class="popup-section">
        <div class="popup-section-label"><?= $t['order_info'] ?></div>
        <div class="popup-row"><span class="popup-row-label"><?= $t['order_date'] ?></span><span class="popup-row-value"><?= fmt_date('M j, Y g:ia', strtotime($o['created_at'])) ?></span></div>
        <div class="popup-row"><span class="popup-row-label"><?= $t['vorder_customer'] ?></span><span class="popup-row-value"><?= htmlspecialchars($o['buyer_name'] ?: $o['buyer_email']) ?></span></div>
        <div class="popup-row"><span class="popup-row-label"><?= $t['order_business'] ?></span><span class="popup-row-value"><?= htmlspecialchars($o['business_name']) ?></span></div>
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
        <div class="popup-subtotal"><span><?= $t['vorder_delivery_nonrefund'] ?></span><span>$<?= number_format($o['delivery_fee'], 2) ?></span></div>
        <?php endif; ?>
        <div class="popup-total"><span><?= $t['vorder_refund_to_buyer'] ?></span><span>$<?= number_format($o['subtotal'] - $o['discount_amount'], 2) ?></span></div>
    </div>
    <?php endif; ?>

    <?php if ($o['refund_reason']): ?>
    <div class="popup-section">
        <div class="popup-section-label"><?= $t['vorder_buyer_reason'] ?></div>
        <p style="font-size:0.875rem;color:#374151;font-style:italic;margin:0;">"<?= htmlspecialchars($o['refund_reason']) ?>"</p>
    </div>
    <?php endif; ?>

    <div class="popup-section">
        <div class="popup-section-label"><?= $t['vorder_refund_status'] ?></div>
        <?php $refundStatus = $o['status']; require __DIR__ . '/../refund-status/refund-status.php'; ?>
    </div>

    <?php if ($o['status'] === 'return_dispatched'): ?>
    <hr class="popup-divider">
    <div class="popup-section-label" style="margin-bottom:0.5rem;"><?= $t['vorder_return_delivery'] ?></div>
    <?php if ($o['return_tracking_url']): ?>
    <div class="popup-row" style="margin-bottom:0.75rem;"><span class="popup-row-label"><?= $t['vorder_grab_link'] ?></span><span class="popup-row-value"><a href="<?= htmlspecialchars($o['return_tracking_url']) ?>" target="_blank" rel="noopener"><?= $t['vorder_track_return'] ?></a></span></div>
    <?php endif; ?>
    <p style="font-size:0.875rem;color:#6b7280;margin:0 0 0.75rem;"><?= $t['vorder_received_hint'] ?></p>
    <form method="POST" action="/products/return-received.php">
        <?= csrf_input() ?>
        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
        <button type="submit" class="btn-confirm"><?= $t['vorder_confirm_received'] ?></button>
    </form>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
