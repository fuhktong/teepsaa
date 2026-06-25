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

$refundStatuses = ['refund_requested', 'return_approved', 'return_dispatched', 'return_received', 'refunded', 'refund_rejected'];
$validFilters   = array_merge(['all'], $refundStatuses);
$filter = in_array($_GET['status'] ?? '', $validFilters) ? ($_GET['status'] ?? 'all') : 'all';

$statusIn = '\'refund_requested\',\'return_approved\',\'return_dispatched\',\'return_received\',\'refunded\',\'refund_rejected\'';
$filterClause = $filter !== 'all' ? ' AND o.status = ' . $pdo->quote($filter) : '';

$sql = "
    SELECT o.id, o.subtotal, o.delivery_fee, o.status, o.created_at,
           o.refund_reason, o.refund_requested_at, o.return_tracking_url,
           b.name AS business_name,
           bu.name AS buyer_name, bu.email AS buyer_email,
           v.name AS vendor_name, v.aba_qr AS vendor_aba_qr,
           GROUP_CONCAT(oi.product_name, ' x', oi.quantity ORDER BY oi.id SEPARATOR ', ') AS items
    FROM orders o
    JOIN businesses b ON b.id = o.business_id
    JOIN buyers bu ON bu.id = o.buyer_user_id
    JOIN vendors v ON v.id = b.user_id
    JOIN order_items oi ON oi.order_id = o.id
    WHERE o.status IN ($statusIn) $filterClause
    GROUP BY o.id
    ORDER BY o.refund_requested_at DESC
";
$orders = $pdo->query($sql)->fetchAll();

$counts = $pdo->query("SELECT status, COUNT(*) AS n FROM orders WHERE status IN ('refund_requested','return_approved','return_dispatched','return_received','refunded','refund_rejected') GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$totalCount = array_sum($counts);

$refundCount        = (int)($counts['refund_requested'] ?? 0);
$pendingPayoutCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered' AND delivered_at IS NOT NULL AND delivered_at < DATE_SUB(NOW(), INTERVAL " . PAYOUT_WINDOW_SECONDS . " SECOND)")->fetchColumn();
$unreadMsgCount     = (int)$pdo->query("SELECT COUNT(DISTINCT thread_id) FROM support_messages WHERE sender IN ('buyer','vendor') AND read_at IS NULL")->fetchColumn();

$filterLabels = [
    'all'               => 'All',
    'refund_requested'  => 'Requested',
    'return_approved'   => 'Return Approved',
    'return_dispatched' => 'Return Sent',
    'return_received'   => 'Item Received',
    'refunded'          => 'Refunded',
    'refund_rejected'   => 'Rejected',
];
$statusClasses = [
    'refund_requested'  => 'badge-red',
    'return_approved'   => 'badge-yellow',
    'return_dispatched' => 'badge-yellow',
    'return_received'   => 'badge-blue',
    'refunded'          => 'badge-green',
    'refund_rejected'   => 'badge-grey',
];
$statusLabels = [
    'refund_requested'  => 'Requested',
    'return_approved'   => 'Return Approved',
    'return_dispatched' => 'Return Sent',
    'return_received'   => 'Item Received',
    'refunded'          => 'Refunded',
    'refund_rejected'   => 'Rejected',
];
$adminSection = 'orders';
$adminTab     = 'refunds';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Refunds</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/order-status/order-status.css">
    <link rel="stylesheet" href="/refund-status/refund-status.css">
    <link rel="stylesheet" href="/admin/admin.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <?php if (!isset($pendingVendorCount)) { $pendingVendorCount = (int)$pdo->query("SELECT COUNT(*) FROM businesses WHERE approved = 0")->fetchColumn(); } ?>
    <?php require __DIR__ . '/admin-tabs.php'; ?>
    <h1>Refunds</h1>

    <?php if ($success): ?>
        <p class="admin-success"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p class="admin-error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <div class="order-filters">
        <?php foreach ($filterLabels as $key => $label): ?>
        <?php $n = $key === 'all' ? $totalCount : ($counts[$key] ?? 0); ?>
        <a href="?status=<?= $key ?>" class="filter-btn <?= $filter === $key ? 'active' : '' ?>">
            <?= $label ?> <span class="filter-count"><?= $n ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($orders)): ?>
        <p class="empty">No refunds found.</p>
    <?php else: ?>
    <div class="order-list">
        <?php foreach ($orders as $o): ?>
        <?php $oid = date('ymd', strtotime($o['created_at'])) . '-' . str_pad($o['id'], 4, '0', STR_PAD_LEFT); ?>
        <a href="/admin/refund.php?id=<?= $o['id'] ?>" style="text-decoration:none;color:inherit;">
        <div class="order-row">
            <div class="order-row-top">
                <span class="order-row-id"><?= $oid ?></span>
                <span class="order-row-biz"><?= htmlspecialchars($o['business_name']) ?></span>
                <span class="order-row-customer"><?= htmlspecialchars($o['buyer_name'] ?: $o['buyer_email']) ?></span>
                <span class="order-row-total">$<?= number_format($o['subtotal'], 2) ?> refund</span>
                <span class="order-badge <?= $statusClasses[$o['status']] ?>"><?= $statusLabels[$o['status']] ?></span>
            </div>
            <?php if ($o['items']): ?>
            <div class="refund-row-reason" style="font-style:normal;color:#374151;"><?= htmlspecialchars($o['items']) ?></div>
            <?php endif; ?>
            <?php if ($o['refund_reason']): ?>
            <div class="refund-row-reason">"<?= htmlspecialchars($o['refund_reason']) ?>"</div>
            <?php endif; ?>
        </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
