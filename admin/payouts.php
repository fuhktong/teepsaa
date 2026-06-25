<?php
session_start();
require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: /login-admin/');
    exit;
}

$success = $_SESSION['admin_success'] ?? '';
unset($_SESSION['admin_success']);

$stmt = $pdo->query('
    SELECT o.id, o.subtotal, o.delivery_fee, o.vendor_delivery_bonus, o.created_at,
           b.name AS business_name,
           v.id AS vendor_id, v.email AS vendor_email, v.aba_qr,
           bu.email AS buyer_email
    FROM orders o
    JOIN businesses b ON b.id = o.business_id
    JOIN vendors v ON v.id = b.user_id
    JOIN buyers bu ON bu.id = o.buyer_user_id
    WHERE o.status = \'delivered\'
    ORDER BY o.created_at ASC
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
    <h1>Vendor Payouts</h1>

    <?php if ($success): ?>
        <p class="admin-success"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <?php if (empty($payouts)): ?>
        <p class="empty">No payouts pending.</p>
    <?php else: ?>
        <div class="admin-list">
            <?php foreach ($payouts as $p): ?>
            <?php $payout = $p['subtotal'] + $p['vendor_delivery_bonus']; ?>
            <div class="admin-card payout-card">
                <div class="admin-card-info">
                    <h2>$<?= number_format($payout, 2) ?> → <?= htmlspecialchars($p['vendor_email']) ?></h2>
                    <p class="meta">
                        Order #<?= $p['id'] ?>
                        &middot; <?= htmlspecialchars($p['business_name']) ?>
                        &middot; Buyer: <?= htmlspecialchars($p['buyer_email']) ?>
                        &middot; <?= date('M j, Y g:ia', strtotime($p['created_at'])) ?>
                    </p>
                    <p class="meta payout-note">
                        Products: <strong>$<?= number_format($p['subtotal'], 2) ?></strong>
                        <?php if ($p['vendor_delivery_bonus'] > 0): ?>
                        + Delivery buffer: <strong>$<?= number_format($p['vendor_delivery_bonus'], 2) ?></strong>
                        <?php endif; ?>
                        = Send <strong>$<?= number_format($payout, 2) ?></strong> (minus your commission), then mark as completed.
                    </p>
                    <?php if ($p['aba_qr']): ?>
                        <img src="/uploads/<?= htmlspecialchars($p['aba_qr']) ?>" alt="Vendor ABA QR" class="payout-qr">
                    <?php else: ?>
                        <p class="payout-no-qr">⚠️ Vendor has not uploaded an ABA QR code yet.</p>
                    <?php endif; ?>
                </div>
                <div class="admin-card-actions">
                    <form method="POST" action="/admin/payouts-action.php">
                        <?= csrf_input() ?>
                        <input type="hidden" name="order_id" value="<?= $p['id'] ?>">
                        <button type="submit" class="btn-approve">Mark completed</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
