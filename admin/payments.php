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

// One row per order so each pending payment can list its orders as links —
// confirmation itself happens on the order page
$stmt = $pdo->query('
    SELECT p.id, p.total, p.created_at,
           u.email AS buyer_email,
           o.id AS order_id, o.created_at AS order_created_at,
           b.name AS business_name
    FROM payments p
    JOIN buyers u ON u.id = p.buyer_user_id
    JOIN orders o ON o.payment_id = p.id
    JOIN businesses b ON b.id = o.business_id
    WHERE p.status = \'pending_confirmation\'
    ORDER BY p.created_at ASC, o.id ASC
');
$payments = [];
foreach ($stmt->fetchAll() as $row) {
    $payments[$row['id']]['total']       = $row['total'];
    $payments[$row['id']]['created_at']  = $row['created_at'];
    $payments[$row['id']]['buyer_email'] = $row['buyer_email'];
    $payments[$row['id']]['orders'][]    = $row;
}
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

    <?php if (empty($payments)): ?>
        <p class="empty">No payments awaiting confirmation.</p>
    <?php else: ?>
        <div class="admin-list">
            <?php foreach ($payments as $pid => $p): ?>
            <div class="admin-card">
                <div class="admin-card-info">
                    <h2>$<?= number_format($p['total'], 2) ?></h2>
                    <p class="meta">
                        Buyer: <?= htmlspecialchars($p['buyer_email']) ?>
                        &middot; <?= count($p['orders']) ?> vendor<?= count($p['orders']) !== 1 ? 's' : '' ?>
                        &middot; <?= date('M j, Y g:ia', strtotime($p['created_at'])) ?>
                    </p>
                    <p class="meta">Payment #<?= $pid ?> — open the order to verify and confirm the payment</p>
                </div>
                <div class="admin-card-actions">
                    <?php foreach ($p['orders'] as $ord):
                          $ordId = date('ymd', strtotime($ord['order_created_at'])) . '-' . str_pad($ord['order_id'], 4, '0', STR_PAD_LEFT); ?>
                    <a href="/admin/order.php?id=<?= $ord['order_id'] ?>" class="btn-approve" style="text-decoration:none;display:inline-block;">
                        <?= $ordId ?> — <?= htmlspecialchars($ord['business_name']) ?> →
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
