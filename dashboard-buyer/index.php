<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'buyer') {
    header('Location: /login-buyer/');
    exit;
}

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare('
    SELECT o.id, o.public_id, o.subtotal, o.delivery_fee, o.discount_amount, o.status, o.created_at, o.tracking_url, o.refund_reason, o.return_tracking_url,
           CASE WHEN o.delivered_at IS NULL OR TIMESTAMPDIFF(SECOND, o.delivered_at, NOW()) < ' . PAYOUT_WINDOW_SECONDS . ' THEN 1 ELSE 0 END AS refund_window_open,
           DATE_ADD(o.delivered_at, INTERVAL ' . PAYOUT_WINDOW_SECONDS . ' SECOND) AS refund_deadline,
           b.name AS business_name,
           b.house_number AS biz_house_number, b.address AS biz_address,
           b.address_notes AS biz_address_notes, b.khan AS biz_khan, b.sangkat AS biz_sangkat,
           v.name AS vendor_name, v.email AS vendor_email,
           GROUP_CONCAT(CONCAT(oi.product_name, IFNULL(CONCAT(\' (\', oi.variant_label, \')\'), \'\'), \' x\', oi.quantity) ORDER BY oi.id SEPARATOR \', \') AS items,
           SUM(CASE WHEN r.id IS NULL THEN 1 ELSE 0 END) AS unreviewed_count
    FROM orders o
    JOIN businesses b ON b.id = o.business_id
    JOIN vendors v ON v.id = b.user_id
    JOIN order_items oi ON oi.order_id = o.id
    LEFT JOIN reviews r ON r.order_item_id = oi.id
    WHERE o.buyer_user_id = ? AND o.status NOT IN (\'cancelled\')
    GROUP BY o.id
    ORDER BY o.created_at DESC
');
$stmt->execute([$userId]);
$orders = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders — teepsaa</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/order-status/order-status.css">
    <link rel="stylesheet" href="/refund-status/refund-status.css">
    <link rel="stylesheet" href="/dashboard-buyer/dashboard-buyer.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <div class="dash-header">
        <div style="display:flex;align-items:center;gap:0.75rem;">
            <h1><?= $t['orders_title'] ?></h1>
            <button class="btn-refresh" data-refresh-all-btn type="button" title="Refresh orders"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg></button>
        </div>
    </div>

    <?php if (empty($orders)): ?>
        <p class="dash-empty"><?= $t['orders_empty'] ?></p>
    <?php else: ?>
    <div class="order-cards">
        <?php foreach ($orders as $o): ?>
        <?php $oid = date('ymd', strtotime($o['created_at'])) . '-' . str_pad($o['id'], 4, '0', STR_PAD_LEFT); ?>
        <?php $isRefund = in_array($o['status'], ['refund_requested','return_approved','return_dispatched','return_received','refunded','refund_rejected']); ?>
        <a href="/dashboard-buyer/order.php?id=<?= $o['public_id'] ?>" style="text-decoration:none;color:inherit;">
        <div class="order-card" data-order-id="<?= $o['id'] ?>" data-order-ref="<?= $oid ?>" data-status="<?= $o['status'] ?>">
            <div class="order-card-head">
                <span class="order-card-id"><?= $oid ?><?php if ($isRefund): ?> <span class="refund-dot"></span><?php endif; ?></span>
                <span class="order-card-items"><?= htmlspecialchars($o['items']) ?></span>
                <span class="order-card-meta"><?= htmlspecialchars($o['business_name']) ?></span>
                <span class="order-card-date"><?= fmt_date('M j, g:ia', strtotime($o['created_at'])) ?></span>
                <span class="order-card-total">$<?= number_format($o['subtotal'] - $o['discount_amount'] + $o['delivery_fee'], 2) ?></span>
            </div>
            <div class="order-card-status" data-status-bar>
                <?php $orderStatus = $o['status']; require __DIR__ . '/../order-status/order-status.php'; ?>
            </div>
            <p class="order-pending-note" data-action-status="pending"<?= $o['status'] !== 'pending' ? ' style="display:none"' : '' ?>><?= $t['orders_awaiting_payment'] ?></p>
            <?php if (in_array($o['status'], ['delivered', 'completed']) && $o['unreviewed_count'] > 0): ?>
            <p class="order-review-prompt">★ <?= $t['orders_leave_review'] ?></p>
            <?php endif; ?>
        </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

<script type="module">
import { initStatusRefresh } from '/js/status-refresh.js';
initStatusRefresh({ loginUrl: '/login-buyer/' });
</script>
</body>
</html>
