<?php
session_start([
    'gc_maxlifetime'  => 28800,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/sales-chart.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header('Location: /login-vendor/');
    exit;
}

$userId = $_SESSION['user_id'];

// Translations loaded early — business status label is built before the header.
$lang = current_lang();
$t = require __DIR__ . '/../lang/' . (in_array($lang, ['en', 'km']) ? $lang : 'en') . '.php';

$stmt = $pdo->prepare('SELECT id, name, category, approved, suspended, suspension_reason, trial_starts_at, trial_ends_at, royalty_free_threshold FROM businesses WHERE user_id = ? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 1');
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
    WHERE b.user_id = ? AND b.deleted_at IS NULL AND o.status IN (\'pending\', \'paid\')
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

// Sales over time — the same numbers as the vendor app's chart. Each bar
// carries its own caption, so the script below only switches views and
// shows the caption of the bar clicked.
$chartViews = [];
if ($business && $business['approved'] === 1) {
    $chart = sales_chart($pdo, (int)$business['id']);
    $orderCount = fn(int $n) => $n === 1 ? $t['app_chart_order_one'] : sprintf($t['app_chart_orders'], $n);
    $money      = fn(float $v) => '$' . number_format($v, 2);
    foreach (['days' => 'date', 'weeks' => 'start'] as $view => $dateKey) {
        $points = $chart[$view];
        $max    = max(array_column($points, 'payout')) ?: 0;
        $total  = array_sum(array_column($points, 'payout'));
        $orders = array_sum(array_column($points, 'orders'));
        $label  = fn($p) => $view === 'days'
            ? fmt_date('j M', $p[$dateKey])
            : sprintf($t['app_chart_week_of'], fmt_date('j M', $p[$dateKey]));
        $chartViews[$view] = [
            'total' => sprintf($t['app_chart_total'], $money($total)) . ' · ' . $orderCount($orders),
            'max'   => $max > 0 ? $money($max) : '',
            'first' => $label($points[0]),
            'last'  => $label($points[count($points) - 1]),
            'bars'  => array_map(fn($p) => [
                'height'  => $max > 0 ? max(2, round($p['payout'] / $max * 100)) : 2,
                'zero'    => $p['payout'] <= 0,
                'caption' => $label($p) . ': ' . $money($p['payout']) . ' · ' . $orderCount($p['orders']),
            ], $points),
        ];
    }
}

// Review state and suspension are two separate things, so they get two separate
// badges. An approved shop that has been suspended is still approved — it just
// is not on the marketplace right now — and saying only "Approved" was what
// made a suspension invisible from this page.
$isSuspended = $business && (int)$business['suspended'] === 1;

if ($business) {
    if ($business['approved'] === 1)       { $statusLabel = $t['vendor_biz_approved']; $statusClass = 'status-approved'; }
    elseif ($business['approved'] === -1)  { $statusLabel = $t['vendor_biz_rejected']; $statusClass = 'status-rejected'; }
    else                                   { $statusLabel = $t['vendor_biz_pending'];  $statusClass = 'status-pending'; }
}

?>
<!DOCTYPE html>
<html lang="<?= current_lang() ?>">
<?php
$headTitle = 'Vendor Dashboard — teepsaa';
$headCss   = ['/order-status/order-status.css', '/analytics/analytics.css'];
$headSeo   = false;
require __DIR__ . '/../head/head.php';
?>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>
<?php require __DIR__ . '/../vendor-subnav/vendor-subnav.php'; ?>

<main>
    <div class="dashboard-header">
        <h1>
            <?= htmlspecialchars($business['name'] ?? $t['vendor_my_business']) ?>
            <?php if ($business): ?>
                <span class="status <?= $statusClass ?>"><?= $statusLabel ?></span>
            <?php endif; ?>
            <?php if ($isSuspended): ?>
                <span class="status status-suspended"><?= $t['vendor_biz_suspended'] ?></span>
            <?php endif; ?>
        </h1>
        <?php if (!$business): ?>
        <div class="dashboard-actions">
            <a href="/submit/" class="btn"><?= $t['vendor_submit_biz'] ?></a>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($isSuspended): ?>
    <div class="suspended-banner">
        <strong><?= $t['vendor_biz_suspended_heading'] ?></strong>
        <?php if ($business['suspension_reason']): ?>
        <p><?= $t['vendor_biz_suspended_reason'] ?> <?= htmlspecialchars($business['suspension_reason']) ?></p>
        <?php else: ?>
        <p><?= $t['vendor_biz_suspended_no_reason'] ?></p>
        <?php endif; ?>
        <p><?= $t['vendor_biz_suspended_help'] ?></p>
    </div>
    <?php endif; ?>

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
        <?php if ($chartViews): ?>
        <div class="sales-chart" data-sales-chart>
            <div class="sales-chart-head">
                <h3 class="analytics-sub-heading"><?= $t['app_chart_title'] ?></h3>
                <div class="sales-chart-pills" role="tablist">
                    <button type="button" class="sales-chart-pill is-active" data-view="days" aria-selected="true"><?= $t['app_chart_days'] ?></button>
                    <button type="button" class="sales-chart-pill" data-view="weeks" aria-selected="false"><?= $t['app_chart_weeks'] ?></button>
                </div>
            </div>
            <?php foreach ($chartViews as $view => $cv): ?>
            <div class="sales-chart-view" data-chart-view="<?= $view ?>" data-total="<?= htmlspecialchars($cv['total']) ?>"<?= $view === 'days' ? '' : ' hidden' ?>>
                <p class="sales-chart-detail" data-chart-detail><?= htmlspecialchars($cv['total']) ?></p>
                <div class="sales-chart-max"><?= $cv['max'] ?></div>
                <div class="sales-chart-bars">
                    <?php foreach ($cv['bars'] as $bar): ?>
                    <button type="button" class="sales-chart-bar<?= $bar['zero'] ? ' is-zero' : '' ?>" style="height:<?= $bar['height'] ?>%"
                            data-caption="<?= htmlspecialchars($bar['caption']) ?>" title="<?= htmlspecialchars($bar['caption']) ?>"></button>
                    <?php endforeach; ?>
                </div>
                <div class="sales-chart-axis"><span><?= $cv['first'] ?></span><span><?= $cv['last'] ?></span></div>
            </div>
            <?php endforeach; ?>
            <p class="sales-chart-hint"><?= $t['vendor_chart_hint'] ?></p>
        </div>
        <?php endif; ?>
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
        <?php elseif ($isSuspended): ?>
            <p class="empty"><?= $t['vendor_products_suspended'] ?></p>
        <?php elseif ($business['approved'] === -1): ?>
            <p class="empty"><?= $t['vendor_products_rejected'] ?></p>
        <?php elseif ($business['approved'] !== 1): ?>
            <p class="empty"><?= $t['vendor_products_pending'] ?></p>
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
<script>
// Sales chart: the pills switch between 30 days and 12 weeks; a click on a
// bar shows its day or week, a second click goes back to the total.
document.querySelectorAll('[data-sales-chart]').forEach((chart) => {
    chart.addEventListener('click', (event) => {
        const pill = event.target.closest('[data-view]');
        if (pill) {
            chart.querySelectorAll('[data-view]').forEach((p) => {
                const on = p === pill;
                p.classList.toggle('is-active', on);
                p.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            chart.querySelectorAll('[data-chart-view]').forEach((v) => {
                v.hidden = v.dataset.chartView !== pill.dataset.view;
            });
            return;
        }
        const bar = event.target.closest('.sales-chart-bar');
        if (!bar) return;
        const view   = bar.closest('[data-chart-view]');
        const detail = view.querySelector('[data-chart-detail]');
        const wasPicked = bar.classList.contains('is-picked');
        view.querySelectorAll('.sales-chart-bar').forEach((b) => b.classList.remove('is-picked'));
        if (wasPicked) {
            detail.textContent = view.dataset.total;
        } else {
            bar.classList.add('is-picked');
            detail.textContent = bar.dataset.caption;
        }
    });
});
</script>
</body>
</html>
