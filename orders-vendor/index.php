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

$userId = $_SESSION['user_id'];
$tab    = ($_GET['tab'] ?? '') === 'refunds' ? 'refunds' : 'orders';

$stmt = $pdo->prepare('SELECT id, name FROM businesses WHERE user_id = ? AND approved = 1 ORDER BY name ASC');
$stmt->execute([$userId]);
$businesses = $stmt->fetchAll();
$bizIds     = array_column($businesses, 'id');

$vendorRefundCount = 0;
$unreadNotifs      = [];
$activePenalties   = [];

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

    $stmt = $pdo->prepare('SELECT id, message FROM vendor_notifications WHERE vendor_user_id = ? AND read_at IS NULL ORDER BY created_at DESC');
    $stmt->execute([$userId]);
    $unreadNotifs = $stmt->fetchAll();

    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE business_id IN ($ph) AND status IN ('return_dispatched')");
    $cntStmt->execute(array_values($bizIds));
    $vendorRefundCount = (int)$cntStmt->fetchColumn();
}

$orders = [];
if ($tab === 'orders') {
    $stmt = $pdo->prepare('
        SELECT o.id, o.public_id, o.subtotal, o.delivery_fee, o.discount_amount,
               o.status, o.created_at,
               b.name AS business_name,
               u.name AS buyer_name, u.email AS buyer_email,
               GROUP_CONCAT(CONCAT(oi.product_name, IFNULL(CONCAT(\' (\', oi.variant_label, \')\'), \'\'), \' x\', oi.quantity) ORDER BY oi.id SEPARATOR \', \') AS items
        FROM orders o
        JOIN businesses b ON b.id = o.business_id
        JOIN buyers u ON u.id = o.buyer_user_id
        JOIN order_items oi ON oi.order_id = o.id
        WHERE b.user_id = ? AND o.status IN (\'pending\', \'paid\', \'dispatched\', \'delivered\', \'completed\')
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ');
    $stmt->execute([$userId]);
    $orders = $stmt->fetchAll();
}

$refundOrders = [];
if ($tab === 'refunds' && !empty($bizIds)) {
    $ph2  = implode(',', array_fill(0, count($bizIds), '?'));
    $stmt = $pdo->prepare("
        SELECT o.id, o.public_id, o.subtotal, o.delivery_fee, o.discount_amount,
               o.status, o.created_at, o.refund_reason,
               b.name AS business_name,
               u.name AS buyer_name, u.email AS buyer_email,
               GROUP_CONCAT(CONCAT(oi.product_name, IFNULL(CONCAT(' (', oi.variant_label, ')'), ''), ' x', oi.quantity) ORDER BY oi.id SEPARATOR ', ') AS items
        FROM orders o
        JOIN businesses b ON b.id = o.business_id
        JOIN buyers u ON u.id = o.buyer_user_id
        JOIN order_items oi ON oi.order_id = o.id
        WHERE b.id IN ($ph2)
          AND o.status IN ('refund_requested','return_approved','return_dispatched','return_received','refunded','refund_rejected')
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ");
    $stmt->execute(array_values($bizIds));
    $refundOrders = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $tab === 'refunds' ? 'Refunds' : 'Orders' ?> — teepsaa</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/products/products.css">
    <link rel="stylesheet" href="/dashboard-vendor/dashboard-vendor.css">
    <link rel="stylesheet" href="/order-status/order-status.css">
    <link rel="stylesheet" href="/refund-status/refund-status.css">
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
        $totalPenalty  = array_sum(array_column($activePenalties, 'rate_increase'));
        $hasIndefinite = false; $soonestExpiry = null;
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

    <nav class="products-subnav">
        <a href="/orders-vendor/" class="<?= $tab === 'orders' ? 'active' : '' ?>"><?= $t['vendor_orders'] ?></a>
        <a href="/orders-vendor/?tab=refunds" class="<?= $tab === 'refunds' ? 'active' : '' ?>"><?= $t['vendor_refunds'] ?><?php if ($vendorRefundCount > 0): ?> <span class="admin-tab-badge"><?= $vendorRefundCount ?></span><?php endif; ?></a>
    </nav>

    <?php if ($tab === 'orders'): ?>

        <div class="page-header">
            <div style="display:flex;align-items:center;gap:0.75rem;">
                <h1><?= $t['vendor_orders'] ?></h1>
                <button class="btn-refresh" data-refresh-all-btn type="button" title="Refresh orders"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg></button>
            </div>
        </div>

        <?php if (empty($orders)): ?>
            <p class="notice"><?= $t['vendor_no_orders'] ?></p>
        <?php else: ?>
        <div class="order-cards">
            <?php foreach ($orders as $o): ?>
            <?php $oid = date('ymd', strtotime($o['created_at'])) . '-' . str_pad($o['id'], 4, '0', STR_PAD_LEFT); ?>
            <a href="/orders-vendor/order.php?id=<?= $o['public_id'] ?>" style="text-decoration:none;color:inherit;">
            <div class="order-card" data-order-id="<?= $o['id'] ?>" data-order-ref="<?= $oid ?>" data-status="<?= $o['status'] ?>">
                <div class="order-card-head">
                    <span class="order-card-id"><?= $oid ?></span>
                    <span class="order-card-items"><?= htmlspecialchars($o['items']) ?></span>
                    <span class="order-card-meta"><?= htmlspecialchars($o['buyer_name'] ?: $o['buyer_email']) ?></span>
                    <span class="order-card-date"><?= fmt_date('M j, g:ia', strtotime($o['created_at'])) ?></span>
                    <span class="order-card-total">$<?= number_format($o['subtotal'] - $o['discount_amount'] + $o['delivery_fee'], 2) ?></span>
                </div>
                <div class="order-card-status" data-status-bar>
                    <?php $orderStatus = $o['status']; require __DIR__ . '/../order-status/order-status.php'; ?>
                </div>
            </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    <?php elseif ($tab === 'refunds'): ?>

        <div class="page-header">
            <h1><?= $t['vendor_refunds'] ?></h1>
        </div>

        <?php if (empty($refundOrders)): ?>
            <p class="notice"><?= $t['vendor_no_refunds'] ?></p>
        <?php else: ?>
        <div class="order-cards">
            <?php foreach ($refundOrders as $o): ?>
            <?php $oid = date('ymd', strtotime($o['created_at'])) . '-' . str_pad($o['id'], 4, '0', STR_PAD_LEFT); ?>
            <a href="/orders-vendor/refund.php?id=<?= $o['public_id'] ?>" style="text-decoration:none;color:inherit;">
            <div class="order-card">
                <div class="order-card-head">
                    <span class="order-card-id"><?= $oid ?> <span class="refund-dot" title="Refund in progress"></span></span>
                    <span class="order-card-items"><?= htmlspecialchars($o['items']) ?></span>
                    <span class="order-card-meta"><?= htmlspecialchars($o['buyer_name'] ?: $o['buyer_email']) ?></span>
                    <span class="order-card-date"><?= fmt_date('M j, g:ia', strtotime($o['created_at'])) ?></span>
                    <span class="order-card-total">$<?= number_format($o['subtotal'] - $o['discount_amount'], 2) ?> refund</span>
                </div>
                <div class="order-card-status">
                    <?php $refundStatus = $o['status']; require __DIR__ . '/../refund-status/refund-status.php'; ?>
                </div>
                <?php if ($o['refund_reason']): ?>
                <div class="refund-row-reason" style="font-size:0.82rem;color:#6b7280;font-style:italic;margin-top:0.25rem;">"<?= htmlspecialchars($o['refund_reason']) ?>"</div>
                <?php endif; ?>
            </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    <?php endif; ?>

</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

<?php if ($tab === 'orders'): ?>
<script type="module">
import { initStatusRefresh } from '/js/status-refresh.js';
initStatusRefresh({ loginUrl: '/login-vendor/' });
</script>
<?php endif; ?>
</body>
</html>
