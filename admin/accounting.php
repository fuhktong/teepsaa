<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/admin-auth.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: /login-admin/');
    exit;
}

admin_require('accounting');

// Date range filter
$from = $_GET['from'] ?? '';
$to   = $_GET['to']   ?? '';
if ($from && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = '';
if ($to   && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = '';

$swhere  = ["o.status NOT IN ('pending','cancelled')"];
$sparams = [];
if ($from !== '') { $swhere[] = 'o.created_at >= ?'; $sparams[] = $from . ' 00:00:00'; }
if ($to   !== '') { $swhere[] = 'o.created_at <= ?'; $sparams[] = $to   . ' 23:59:59'; }
$swhereSql = implode(' AND ', $swhere);

// Summary stats
$stmtSum = $pdo->prepare("
    SELECT
        COUNT(*)                                                                                            AS order_count,
        COALESCE(SUM(o.subtotal), 0)                                                                       AS total_gmv,
        COALESCE(SUM(o.royalty_amount), 0)                                                                 AS royalty_earned,
        COALESCE(SUM(CASE WHEN o.status = 'completed' THEN o.royalty_amount ELSE 0 END), 0)               AS royalty_collected,
        COALESCE(SUM(CASE WHEN o.status = 'delivered' THEN o.royalty_amount ELSE 0 END), 0)               AS royalty_pending,
        COALESCE(SUM(CASE WHEN o.status = 'completed' THEN o.vendor_payout ELSE 0 END), 0)               AS payouts_made,
        COALESCE(SUM(CASE WHEN o.status IN ('paid','dispatched','delivered') THEN o.vendor_payout ELSE 0 END), 0) AS payouts_outstanding
    FROM orders o
    WHERE $swhereSql
");
$stmtSum->execute($sparams);
$s = $stmtSum->fetch();

// Monthly breakdown — last 24 months (ignores date range filter, always shows full history)
$months = $pdo->query("
    SELECT
        DATE_FORMAT(o.created_at, '%Y-%m')                                                                 AS month,
        COUNT(*)                                                                                            AS order_count,
        COALESCE(SUM(o.subtotal), 0)                                                                       AS gmv,
        COALESCE(SUM(o.royalty_amount), 0)                                                                 AS royalty,
        COALESCE(SUM(CASE WHEN o.status = 'completed' THEN o.vendor_payout ELSE 0 END), 0)               AS payouts_made,
        COALESCE(SUM(CASE WHEN o.status IN ('paid','dispatched','delivered') THEN o.vendor_payout ELSE 0 END), 0) AS payouts_outstanding
    FROM orders o
    WHERE o.status NOT IN ('pending','cancelled')
      AND o.created_at >= DATE_SUB(NOW(), INTERVAL 24 MONTH)
    GROUP BY DATE_FORMAT(o.created_at, '%Y-%m')
    ORDER BY month DESC
")->fetchAll();

// Top vendors by royalty contributed (respects date filter)
$stmtTop = $pdo->prepare("
    SELECT v.name AS vendor_name, b.name AS business_name,
           COUNT(o.id)             AS order_count,
           SUM(o.subtotal)         AS gmv,
           SUM(o.royalty_amount)   AS royalty
    FROM orders o
    JOIN businesses b ON b.id = o.business_id
    JOIN vendors v ON v.id = b.user_id
    WHERE $swhereSql
    GROUP BY b.id
    ORDER BY royalty DESC
    LIMIT 10
");
$stmtTop->execute($sparams);
$topVendors = $stmtTop->fetchAll();

// Badge counts for tab nav
$pendingVendorCount = (int)$pdo->query("SELECT COUNT(*) FROM businesses WHERE approved = 0")->fetchColumn();
$refundCount        = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'refund_requested'")->fetchColumn();
require __DIR__ . '/../config/delivery.php';
$pendingPayoutCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered' AND delivered_at IS NOT NULL AND delivered_at < DATE_SUB(NOW(), INTERVAL " . PAYOUT_WINDOW_SECONDS . " SECOND)")->fetchColumn();
$unreadMsgCount     = (int)$pdo->query("SELECT COUNT(DISTINCT thread_id) FROM support_messages WHERE sender IN ('buyer','vendor') AND read_at IS NULL")->fetchColumn();
$adminSection = 'orders';
$adminTab     = 'accounting';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Accounting</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/admin/admin.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <?php require __DIR__ . '/admin-tabs.php'; ?>
    <h1>Accounting</h1>

    <form method="GET" action="/admin/accounting.php" class="acct-filter-form">
        <span class="acct-filter-label">Date range</span>
        <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" class="admin-date-input">
        <span class="admin-date-sep">to</span>
        <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" class="admin-date-input">
        <button type="submit" class="acct-filter-btn">Apply</button>
        <?php if ($from || $to): ?>
        <a href="/admin/accounting.php" class="admin-filter-clear">Clear</a>
        <?php endif; ?>
    </form>

    <div class="acct-stats">
        <div class="acct-stat">
            <div class="acct-stat-label">Confirmed GMV</div>
            <div class="acct-stat-value">$<?= number_format((float)$s['total_gmv'], 2) ?></div>
            <div class="acct-stat-sub"><?= (int)$s['order_count'] ?> orders</div>
        </div>
        <div class="acct-stat">
            <div class="acct-stat-label">Royalty earned</div>
            <div class="acct-stat-value">$<?= number_format((float)$s['royalty_earned'], 2) ?></div>
            <div class="acct-stat-sub">on confirmed orders</div>
        </div>
        <div class="acct-stat acct-stat--highlight">
            <div class="acct-stat-label">Platform revenue</div>
            <div class="acct-stat-value">$<?= number_format((float)$s['royalty_collected'], 2) ?></div>
            <div class="acct-stat-sub">royalty on completed orders</div>
        </div>
        <div class="acct-stat">
            <div class="acct-stat-label">Royalty pending</div>
            <div class="acct-stat-value">$<?= number_format((float)$s['royalty_pending'], 2) ?></div>
            <div class="acct-stat-sub">awaiting payout confirmation</div>
        </div>
        <div class="acct-stat acct-stat--highlight">
            <div class="acct-stat-label">Payouts made</div>
            <div class="acct-stat-value">$<?= number_format((float)$s['payouts_made'], 2) ?></div>
            <div class="acct-stat-sub">to vendors on completed orders</div>
        </div>
        <div class="acct-stat acct-stat--warn">
            <div class="acct-stat-label">Payouts outstanding</div>
            <div class="acct-stat-value">$<?= number_format((float)$s['payouts_outstanding'], 2) ?></div>
            <div class="acct-stat-sub">owed to vendors, not yet paid</div>
        </div>
    </div>

    <?php if (!empty($topVendors)): ?>
    <h2 class="acct-section-heading">Top vendors by royalty</h2>
    <table class="orders-table acct-table">
        <thead>
            <tr>
                <th>Business</th>
                <th>Vendor</th>
                <th>Orders</th>
                <th>GMV</th>
                <th>Royalty contributed</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($topVendors as $tv): ?>
            <tr>
                <td><?= htmlspecialchars($tv['business_name']) ?></td>
                <td style="color:#6b7280"><?= htmlspecialchars($tv['vendor_name']) ?></td>
                <td><?= (int)$tv['order_count'] ?></td>
                <td>$<?= number_format((float)$tv['gmv'], 2) ?></td>
                <td style="font-weight:600">$<?= number_format((float)$tv['royalty'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php if (!empty($months)): ?>
    <h2 class="acct-section-heading">Monthly breakdown <span class="acct-section-note">(last 24 months)</span></h2>
    <table class="orders-table acct-table">
        <thead>
            <tr>
                <th>Month</th>
                <th>Orders</th>
                <th>GMV</th>
                <th>Royalty</th>
                <th>Payouts made</th>
                <th>Outstanding</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($months as $m): ?>
            <tr>
                <td style="font-weight:600;white-space:nowrap"><?= date('M Y', strtotime($m['month'] . '-01')) ?></td>
                <td><?= (int)$m['order_count'] ?></td>
                <td>$<?= number_format((float)$m['gmv'], 2) ?></td>
                <td>$<?= number_format((float)$m['royalty'], 2) ?></td>
                <td>$<?= number_format((float)$m['payouts_made'], 2) ?></td>
                <td><?= $m['payouts_outstanding'] > 0 ? '<span style="color:#dc2626;font-weight:600">$' . number_format((float)$m['payouts_outstanding'], 2) . '</span>' : '<span style="color:#9ca3af">—</span>' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p class="empty" style="margin-top:2rem">No confirmed orders yet — accounting data will appear here once orders are processed.</p>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>
</body>
</html>
