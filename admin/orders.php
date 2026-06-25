<?php
session_start();
require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: /login-admin/');
    exit;
}

$success = $_SESSION['admin_success'] ?? '';
$error   = $_SESSION['admin_error']   ?? '';
unset($_SESSION['admin_success'], $_SESSION['admin_error']);

$validStatuses = ['all', 'pending', 'paid', 'dispatched', 'delivered', 'completed', 'cancelled'];
$filter = in_array($_GET['status'] ?? '', $validStatuses) ? ($_GET['status'] ?? 'all') : 'all';

$search = trim($_GET['search'] ?? '');
$from   = $_GET['from'] ?? '';
$to     = $_GET['to']   ?? '';
if ($from && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = '';
if ($to   && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = '';

$owhere  = [];
$oparams = [];
if ($filter !== 'all') {
    $owhere[]  = 'o.status = ?';
    $oparams[] = $filter;
}
if ($search !== '') {
    $like  = '%' . $search . '%';
    $conds = ['bu.name LIKE ?', 'b.name LIKE ?', 'bu.email LIKE ?'];
    $args  = [$like, $like, $like];
    if (ctype_digit($search)) {
        $conds[] = 'o.id = ?';
        $args[]  = (int)$search;
    } elseif (preg_match('/^\d{6}-0*(\d+)$/', $search, $m)) {
        $conds[] = 'o.id = ?';
        $args[]  = (int)$m[1];
    }
    $owhere[] = '(' . implode(' OR ', $conds) . ')';
    $oparams  = array_merge($oparams, $args);
}
if ($from !== '') { $owhere[] = 'o.created_at >= ?'; $oparams[] = $from . ' 00:00:00'; }
if ($to   !== '') { $owhere[] = 'o.created_at <= ?'; $oparams[] = $to   . ' 23:59:59'; }

$sql = '
    SELECT o.id, o.subtotal, o.delivery_fee, o.vendor_delivery_bonus,
           o.royalty_rate, o.royalty_amount, o.vendor_payout,
           o.status, o.created_at, o.delivered_at,
           b.name AS business_name,
           v.name AS vendor_name, v.email AS vendor_email, v.aba_qr AS vendor_aba_qr,
           bu.name AS buyer_name, bu.email AS buyer_email,
           p.id AS payment_id, p.status AS payment_status
    FROM orders o
    JOIN businesses b ON b.id = o.business_id
    JOIN vendors v ON v.id = b.user_id
    JOIN buyers bu ON bu.id = o.buyer_user_id
    JOIN payments p ON p.id = o.payment_id'
    . (!empty($owhere) ? ' WHERE ' . implode(' AND ', $owhere) : '')
    . ' ORDER BY o.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($oparams);
$orders = $stmt->fetchAll();

$refundCount        = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'refund_requested'")->fetchColumn();
$pendingPayoutCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered' AND delivered_at IS NOT NULL AND delivered_at < DATE_SUB(NOW(), INTERVAL " . PAYOUT_WINDOW_SECONDS . " SECOND)")->fetchColumn();
$unreadMsgCount     = (int)$pdo->query("SELECT COUNT(DISTINCT thread_id) FROM support_messages WHERE sender IN ('buyer','vendor') AND read_at IS NULL")->fetchColumn();

// Status counts for filter bar
$counts = $pdo->query('SELECT status, COUNT(*) as n FROM orders GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
$totalCount = array_sum($counts);

$statusLabels = ['all' => 'All', 'pending' => 'Pending', 'paid' => 'Paid', 'dispatched' => 'Dispatched', 'delivered' => 'Delivered', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
$statusClasses = ['pending' => 'badge-grey', 'paid' => 'badge-blue', 'dispatched' => 'badge-yellow', 'delivered' => 'badge-green', 'completed' => 'badge-green', 'cancelled' => 'badge-red'];
$adminSection = 'orders';
$adminTab     = 'orders';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Orders</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/order-status/order-status.css">
    <link rel="stylesheet" href="/admin/admin.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <?php require __DIR__ . '/admin-tabs.php'; ?>
    <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1rem;">
        <h1 style="margin-bottom:0">Orders</h1>
        <button class="btn-refresh" data-refresh-all-btn type="button" title="Refresh orders"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg></button>
    </div>

    <?php if ($success): ?>
        <p class="admin-success"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p class="admin-error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php
    $odSearchQ = $search !== '' ? '&search=' . urlencode($search) : '';
    $odFromQ   = $from   !== '' ? '&from='   . urlencode($from)   : '';
    $odToQ     = $to     !== '' ? '&to='     . urlencode($to)     : '';
    ?>
    <div class="admin-filters-row">
        <form method="GET" action="/admin/orders.php" class="admin-search-form">
            <?php if ($filter !== 'all'): ?><input type="hidden" name="status" value="<?= htmlspecialchars($filter) ?>"><?php endif; ?>
            <?php if ($from !== ''): ?><input type="hidden" name="from" value="<?= htmlspecialchars($from) ?>"><?php endif; ?>
            <?php if ($to   !== ''): ?><input type="hidden" name="to"   value="<?= htmlspecialchars($to)   ?>"><?php endif; ?>
            <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Order ID, buyer, or business…" class="admin-search-input" autocomplete="off">
        </form>
        <form method="GET" action="/admin/orders.php" class="admin-date-form">
            <?php if ($filter !== 'all'): ?><input type="hidden" name="status" value="<?= htmlspecialchars($filter) ?>"><?php endif; ?>
            <?php if ($search !== ''): ?><input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
            <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" class="admin-date-input">
            <span class="admin-date-sep">–</span>
            <input type="date" name="to"   value="<?= htmlspecialchars($to)   ?>" class="admin-date-input">
            <button type="submit" class="btn-save">Apply</button>
            <?php if ($from !== '' || $to !== ''): ?>
            <a href="/admin/orders.php?status=<?= $filter ?><?= $odSearchQ ?>" class="admin-filter-clear">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="order-filters">
        <?php foreach ($statusLabels as $key => $label): ?>
        <?php $n = $key === 'all' ? $totalCount : ($counts[$key] ?? 0); ?>
        <a href="?status=<?= $key ?><?= $odSearchQ . $odFromQ . $odToQ ?>" class="filter-btn <?= $filter === $key ? 'active' : '' ?>">
            <?= $label ?> <span class="filter-count"><?= $n ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($orders)): ?>
        <p class="empty">No orders found.</p>
    <?php else: ?>
    <div class="order-list">
        <?php foreach ($orders as $o): ?>
        <?php $oid = date('ymd', strtotime($o['created_at'])) . '-' . str_pad($o['id'], 4, '0', STR_PAD_LEFT); ?>
        <a href="/admin/order.php?id=<?= $o['id'] ?>" style="text-decoration:none;color:inherit;">
        <div class="order-row" data-order-id="<?= $o['id'] ?>" data-order-ref="<?= $oid ?>" data-status="<?= $o['status'] ?>">
            <div class="order-row-top">
                <span class="order-row-id"><?= $oid ?></span>
                <span class="order-row-biz"><?= htmlspecialchars($o['business_name']) ?></span>
                <span class="order-row-customer"><?= htmlspecialchars($o['buyer_name'] ?: $o['buyer_email']) ?></span>
                <span class="order-row-total">$<?= number_format($o['subtotal'] + $o['delivery_fee'], 2) ?></span>
            </div>
            <div class="order-row-bar" data-status-bar>
                <?php $orderStatus = $o['status']; require __DIR__ . '/../order-status/order-status.php'; ?>
            </div>
        </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

<script type="module">
import { initStatusRefresh } from '/js/status-refresh.js';
initStatusRefresh({ loginUrl: '/login-admin/', isAdminFilter: <?= json_encode($filter !== 'all') ?> });
</script>
</body>
</html>
