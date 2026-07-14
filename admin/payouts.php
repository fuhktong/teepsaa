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

admin_require('payouts');

$success = $_SESSION['admin_success'] ?? '';
unset($_SESSION['admin_success']);

// One row per delivered order, rendered like the Orders page — clicking a row
// opens the order, where the payout QR, refund-window check and "Mark
// completed" button live
$stmt = $pdo->query('
    SELECT o.id, o.subtotal, o.delivery_fee, o.vendor_delivery_bonus, o.created_at,
           o.delivered_at,
           CASE WHEN c.business_id = o.business_id THEN o.discount_amount ELSE 0 END AS vendor_coupon_discount,
           b.name AS business_name,
           v.email AS vendor_email
    FROM orders o
    JOIN businesses b ON b.id = o.business_id
    JOIN vendors v ON v.id = b.user_id
    LEFT JOIN coupons c ON c.id = o.coupon_id
    WHERE o.status = \'delivered\'
    ORDER BY o.delivered_at ASC
');
$payouts = $stmt->fetchAll();
$adminSection = 'orders';
$adminTab     = 'payouts';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Payouts</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/order-status/order-status.css">
    <link rel="stylesheet" href="/admin/admin.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <?php if (!isset($pendingVendorCount)) { $pendingVendorCount = (int)$pdo->query("SELECT COUNT(*) FROM businesses WHERE approved = 0")->fetchColumn(); } ?>
    <?php require __DIR__ . '/admin-tabs.php'; ?>
    <h1>Vendor Payouts</h1>

    <?php if ($success): ?>
        <p class="admin-success"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <?php if (empty($payouts)): ?>
        <p class="empty">No payouts pending.</p>
    <?php else: ?>
    <div class="order-list">
        <?php foreach ($payouts as $p): ?>
        <?php
        $payout       = $p['subtotal'] - $p['vendor_coupon_discount'] + $p['vendor_delivery_bonus'];
        $oid          = date('ymd', strtotime($p['created_at'])) . '-' . str_pad($p['id'], 4, '0', STR_PAD_LEFT);
        $windowPassed = $p['delivered_at'] && (time() - strtotime($p['delivered_at'])) >= PAYOUT_WINDOW_SECONDS;
        $windowTime   = $p['delivered_at'] ? date('M j, g:ia', strtotime($p['delivered_at']) + PAYOUT_WINDOW_SECONDS) : null;
        ?>
        <a href="/admin/order.php?id=<?= $p['id'] ?>" style="text-decoration:none;color:inherit;">
        <div class="order-row">
            <div class="order-row-top">
                <span class="order-row-id"><?= $oid ?></span>
                <span class="order-row-biz"><?= htmlspecialchars($p['business_name']) ?></span>
                <span class="order-row-customer"><?= htmlspecialchars($p['vendor_email']) ?></span>
                <?php if ($windowPassed): ?>
                <span class="order-badge badge-green">Ready to pay</span>
                <?php elseif ($windowTime): ?>
                <span class="order-badge badge-yellow">Refund window closes <?= $windowTime ?></span>
                <?php endif; ?>
                <span class="order-row-total">$<?= number_format($payout, 2) ?></span>
            </div>
            <div class="order-row-bar">
                <?php $orderStatus = 'delivered'; require __DIR__ . '/../order-status/order-status.php'; ?>
            </div>
        </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
