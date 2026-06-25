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
    SELECT p.id, p.total, p.status, p.created_at,
           u.email AS buyer_email,
           COUNT(o.id) AS order_count
    FROM payments p
    JOIN buyers u ON u.id = p.buyer_user_id
    LEFT JOIN orders o ON o.payment_id = p.id
    WHERE p.status = \'pending_confirmation\'
    GROUP BY p.id
    ORDER BY p.created_at ASC
');
$payments = $stmt->fetchAll();
$adminSection = 'orders';
$adminTab     = 'payments';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Payments</title>
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
            <?php foreach ($payments as $p): ?>
            <div class="admin-card">
                <div class="admin-card-info">
                    <h2>$<?= number_format($p['total'], 2) ?></h2>
                    <p class="meta">
                        Buyer: <?= htmlspecialchars($p['buyer_email']) ?>
                        &middot; <?= (int)$p['order_count'] ?> vendor<?= $p['order_count'] != 1 ? 's' : '' ?>
                        &middot; <?= date('M j, Y g:ia', strtotime($p['created_at'])) ?>
                    </p>
                    <p class="meta">Payment #<?= $p['id'] ?> — verify this amount in your ABA app before confirming</p>
                </div>
                <div class="admin-card-actions">
                    <form method="POST" action="/admin/payments-action.php">
                        <?= csrf_input() ?>
                        <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                        <button type="submit" name="action" value="confirm" class="btn-approve">Confirm Payment</button>
                        <button type="submit" name="action" value="reject" class="btn-reject">Reject</button>
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
