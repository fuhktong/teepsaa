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

// Translations loaded early — business status label is built before the header.
$lang = $_SESSION['lang'] ?? 'km';
$t = require __DIR__ . '/../lang/' . (in_array($lang, ['en', 'km']) ? $lang : 'en') . '.php';

$stmt = $pdo->prepare('SELECT id, name, category, approved, trial_starts_at, trial_ends_at, royalty_free_threshold FROM businesses WHERE user_id = ? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 1');
$stmt->execute([$userId]);
$business = $stmt->fetch();

$trial = null;
if ($business && $business['trial_starts_at']) {
    $salesStmt = $pdo->prepare('SELECT COALESCE(SUM(subtotal), 0) FROM orders WHERE business_id = ? AND status IN (\'delivered\', \'completed\')');
    $salesStmt->execute([$business['id']]);
    $completedSales = (float)$salesStmt->fetchColumn();
    $withinTime     = strtotime($business['trial_ends_at']) > time();
    $belowThreshold = $completedSales < (float)$business['royalty_free_threshold'];
    if ($withinTime || $belowThreshold) {
        $trial = [
            'ends_at'    => $business['trial_ends_at'],
            'sales'      => $completedSales,
            'threshold'  => (float)$business['royalty_free_threshold'],
            'within_time'=> $withinTime,
        ];
    }
}

$bizIds   = $business ? [$business['id']] : [];
$products = [];
if ($business) {
    $stmt = $pdo->prepare('SELECT id, name, price, stock, active, low_stock_threshold FROM products WHERE business_id = ? ORDER BY name ASC');
    $stmt->execute([$business['id']]);
    $products = $stmt->fetchAll();
}

$stmt = $pdo->prepare('
    SELECT o.id, o.public_id, o.subtotal, o.discount_amount, o.status, o.created_at, o.tracking_url,
           b.name AS business_name,
           u.name AS buyer_name, u.email AS buyer_email, u.phone AS buyer_phone,
           u.house_number AS buyer_house_number, u.address AS buyer_address,
           u.address_notes AS buyer_address_notes,
           u.khan AS buyer_khan, u.sangkat AS buyer_sangkat,
           GROUP_CONCAT(oi.product_name, \' x\', oi.quantity ORDER BY oi.id SEPARATOR \', \') AS items
    FROM orders o
    JOIN businesses b ON b.id = o.business_id
    JOIN buyers u ON u.id = o.buyer_user_id
    JOIN order_items oi ON oi.order_id = o.id
    WHERE b.user_id = ? AND o.status IN (\'pending\', \'paid\')
    GROUP BY o.id
    ORDER BY o.created_at DESC
');
$stmt->execute([$userId]);
$orders = $stmt->fetchAll();

$stats = ['total_payout' => 0, 'month_payout' => 0, 'total_orders' => 0, 'month_orders' => 0];
$bestSellers = [];
if ($business && $business['approved'] === 1) {
    $stmtStats = $pdo->prepare('
        SELECT
            COALESCE(SUM(vendor_payout), 0) AS total_payout,
            COALESCE(SUM(CASE WHEN YEAR(o.created_at) = YEAR(NOW()) AND MONTH(o.created_at) = MONTH(NOW()) THEN vendor_payout ELSE 0 END), 0) AS month_payout,
            COUNT(*) AS total_orders,
            COALESCE(SUM(CASE WHEN YEAR(o.created_at) = YEAR(NOW()) AND MONTH(o.created_at) = MONTH(NOW()) THEN 1 ELSE 0 END), 0) AS month_orders
        FROM orders o
        JOIN businesses b ON b.id = o.business_id
        WHERE b.user_id = ? AND o.status IN (\'delivered\', \'completed\')
    ');
    $stmtStats->execute([$userId]);
    $stats = $stmtStats->fetch() ?: $stats;

    $stmtBest = $pdo->prepare('
        SELECT oi.product_name,
               SUM(oi.quantity) AS total_sold,
               SUM(oi.price_at_purchase * oi.quantity) AS revenue
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        JOIN businesses b ON b.id = o.business_id
        WHERE b.user_id = ? AND o.status IN (\'delivered\', \'completed\')
        GROUP BY oi.product_name
        ORDER BY total_sold DESC
        LIMIT 5
    ');
    $stmtBest->execute([$userId]);
    $bestSellers = $stmtBest->fetchAll();
}

if ($business) {
    if ($business['approved'] === 1)       { $statusLabel = $t['vendor_biz_approved']; $statusClass = 'status-approved'; }
    elseif ($business['approved'] === -1)  { $statusLabel = $t['vendor_biz_rejected']; $statusClass = 'status-rejected'; }
    else                                   { $statusLabel = $t['vendor_biz_pending'];  $statusClass = 'status-pending'; }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Dashboard — teepsaa</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/order-status/order-status.css">
    <link rel="stylesheet" href="/dashboard-vendor/dashboard-vendor.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <div class="dashboard-header">
        <h1>
            <?= htmlspecialchars($business['name'] ?? $t['vendor_my_business']) ?>
            <?php if ($business): ?>
                <span class="status <?= $statusClass ?>"><?= $statusLabel ?></span>
            <?php endif; ?>
        </h1>
        <?php if (!$business): ?>
        <div class="dashboard-actions">
            <a href="/submit/" class="btn"><?= $t['vendor_submit_biz'] ?></a>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($trial): ?>
    <div class="trial-banner">
        <div class="trial-banner-text">
            <strong><?= $t['vendor_trial_active'] ?></strong>
            <?= $t['vendor_trial_no_commission'] ?>
            <?= sprintf($t['vendor_trial_ends'], fmt_date('d M Y', strtotime($trial['ends_at']))) ?>
        </div>
        <div class="trial-banner-progress">
            <div class="trial-progress-row">
                <span><?= $t['vendor_sales_progress'] ?></span>
                <span><?= sprintf($t['vendor_of'], '$' . number_format($trial['sales'], 2), '$' . number_format($trial['threshold'], 0)) ?></span>
            </div>
            <div class="trial-progress-bar">
                <div class="trial-progress-fill" style="width:<?= min(100, round($trial['sales'] / $trial['threshold'] * 100)) ?>%"></div>
            </div>
            <p class="trial-progress-note">
                <?php if (!$trial['within_time']): ?>
                    <?= sprintf($t['vendor_trial_time_ended'], '$' . number_format($trial['threshold'], 0)) ?>
                <?php else: ?>
                    <?= sprintf($t['vendor_trial_normal_fees'], fmt_date('d M Y', strtotime($trial['ends_at'])), '$' . number_format($trial['threshold'], 0)) ?>
                <?php endif; ?>
            </p>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($business && $business['approved'] === 1): ?>
    <div class="dashboard-section analytics-section">
        <div class="dashboard-section-header">
            <h2><?= $t['vendor_analytics'] ?></h2>
        </div>
        <div class="analytics-stats">
            <div class="analytics-stat">
                <div class="analytics-stat-value">$<?= number_format((float)$stats['total_payout'], 2) ?></div>
                <div class="analytics-stat-label"><?= $t['vendor_all_time_rev'] ?></div>
            </div>
            <div class="analytics-stat">
                <div class="analytics-stat-value">$<?= number_format((float)$stats['month_payout'], 2) ?></div>
                <div class="analytics-stat-label"><?= $t['vendor_this_month_revenue'] ?></div>
            </div>
            <div class="analytics-stat">
                <div class="analytics-stat-value"><?= (int)$stats['total_orders'] ?></div>
                <div class="analytics-stat-label"><?= $t['vendor_total_orders'] ?></div>
            </div>
            <div class="analytics-stat">
                <div class="analytics-stat-value"><?= (int)$stats['month_orders'] ?></div>
                <div class="analytics-stat-label"><?= $t['vendor_this_month_orders'] ?></div>
            </div>
        </div>
        <?php if (!empty($bestSellers)): ?>
        <h3 class="analytics-sub-heading"><?= $t['vendor_best_sellers'] ?></h3>
        <table class="business-table analytics-table">
            <thead>
                <tr>
                    <th><?= $t['vendor_col_product'] ?></th>
                    <th><?= $t['vendor_units_sold'] ?></th>
                    <th><?= $t['vendor_col_revenue'] ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bestSellers as $bs): ?>
                <tr>
                    <td><?= htmlspecialchars($bs['product_name']) ?></td>
                    <td><?= (int)$bs['total_sold'] ?></td>
                    <td>$<?= number_format((float)$bs['revenue'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p class="empty analytics-empty"><?= $t['vendor_analytics_empty'] ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="dashboard-section">
        <div class="dashboard-section-header">
            <h2><a href="/orders-vendor/" class="section-header-link"><?= $t['vendor_orders'] ?></a></h2>
            <?php if (!empty($orders)): ?>
                <button class="btn-refresh" data-refresh-all-btn type="button" title="Refresh orders"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg></button>
            <?php endif; ?>
        </div>

        <?php if (empty($orders)): ?>
            <p class="empty"><?= $t['vendor_no_orders'] ?></p>
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
                    <span class="order-card-total">$<?= number_format($o['subtotal'] - $o['discount_amount'], 2) ?></span>
                </div>
                <div class="order-card-status" data-status-bar>
                    <?php $orderStatus = $o['status']; require __DIR__ . '/../order-status/order-status.php'; ?>
                </div>
            </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="dashboard-section">
        <div class="dashboard-section-header">
            <h2><a href="/products/" class="section-header-link"><?= $t['vendor_products'] ?></a></h2>
        </div>

        <?php if (!$business): ?>
            <p class="empty"><?= $t['vendor_submit_to_add'] ?></p>
        <?php elseif (empty($products)): ?>
            <p class="empty"><?= $t['vendor_no_products'] ?> <a href="/products/?action=add"><?= $t['vendor_add_product'] ?></a>.</p>
        <?php else: ?>
        <table class="business-table">
            <thead>
                <tr>
                    <th><?= $t['vendor_col_name'] ?></th>
                    <th><?= $t['vendor_col_price'] ?></th>
                    <th><?= $t['vendor_col_stock'] ?></th>
                    <th><?= $t['vendor_col_status'] ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                <tr class="product-row" onclick="location.href='/products/?action=edit&id=<?= $p['id'] ?>'">
                    <td><?= htmlspecialchars($p['name']) ?></td>
                    <td>$<?= number_format($p['price'], 2) ?></td>
                    <td>
                        <?= (int)$p['stock'] ?>
                        <?php if ($p['stock'] > 0 && $p['stock'] <= $p['low_stock_threshold']): ?>
                            <span class="stock-low-badge"><?= $t['vendor_stock_low'] ?></span>
                        <?php elseif ($p['stock'] === 0): ?>
                            <span class="stock-low-badge"><?= $t['vendor_stock_out'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td><span class="status <?= $p['active'] ? 'status-approved' : 'status-rejected' ?>"><?= $p['active'] ? $t['vendor_status_active'] : $t['vendor_status_inactive'] ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

<script type="module">
import { initStatusRefresh } from '/js/status-refresh.js';
initStatusRefresh({ loginUrl: '/login-vendor/' });
</script>
</body>
</html>
