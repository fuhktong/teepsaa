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

admin_require('payments');

$success = $_SESSION['admin_success'] ?? '';
unset($_SESSION['admin_success']);

// One row per order in a pending payment, rendered like the Orders page —
// clicking a row opens the order, where the payment is confirmed or rejected
$stmt = $pdo->query('
    SELECT o.id, o.subtotal, o.delivery_fee, o.discount_amount, o.status, o.created_at,
           b.name AS business_name,
           bu.name AS buyer_name, bu.email AS buyer_email
    FROM payments p
    JOIN orders o ON o.payment_id = p.id
    JOIN businesses b ON b.id = o.business_id
    JOIN buyers bu ON bu.id = o.buyer_user_id
    WHERE p.status = \'pending_confirmation\'
    ORDER BY p.created_at ASC, o.id ASC
');
$orders = $stmt->fetchAll();
$adminSection = 'orders';
$adminTab     = 'payments';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Payments</title>
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
    <h1>Pending Payments</h1>

    <?php if ($success): ?>
        <p class="admin-success"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <?php if (empty($orders)): ?>
        <p class="empty">No payments awaiting confirmation.</p>
    <?php else: ?>
    <div class="order-list">
        <?php foreach ($orders as $o): ?>
        <?php $oid = date('ymd', strtotime($o['created_at'])) . '-' . str_pad($o['id'], 4, '0', STR_PAD_LEFT); ?>
        <a href="/admin/order.php?id=<?= $o['id'] ?>" style="text-decoration:none;color:inherit;">
        <div class="order-row">
            <div class="order-row-top">
                <span class="order-row-id"><?= $oid ?></span>
                <span class="order-row-biz"><?= htmlspecialchars($o['business_name']) ?></span>
                <span class="order-row-customer"><?= htmlspecialchars($o['buyer_name'] ?: $o['buyer_email']) ?></span>
                <span class="order-row-total">$<?= number_format($o['subtotal'] - $o['discount_amount'] + $o['delivery_fee'], 2) ?></span>
            </div>
            <div class="order-row-bar">
                <?php $orderStatus = $o['status']; require __DIR__ . '/../order-status/order-status.php'; ?>
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
