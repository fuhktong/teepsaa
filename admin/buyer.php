<?php
session_start();
require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: /login-admin/');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /admin/buyers.php'); exit; }

$stmt = $pdo->prepare('
    SELECT b.id, b.name, b.email, b.phone, b.house_number, b.address, b.khan, b.sangkat,
           b.created_at, b.banned, b.ban_reason, b.banned_at, b.admin_note,
           COUNT(DISTINCT o.id) AS order_count,
           COALESCE(SUM(CASE WHEN o.status NOT IN (\'cancelled\') THEN o.subtotal END), 0) AS total_spent,
           SUM(CASE WHEN o.status = \'refund_requested\' OR o.status LIKE \'refund%\' OR o.status LIKE \'return%\' THEN 1 ELSE 0 END) AS refund_count
    FROM buyers b
    LEFT JOIN orders o ON o.buyer_user_id = b.id
    WHERE b.id = ?
    GROUP BY b.id
');
$stmt->execute([$id]);
$b = $stmt->fetch();
if (!$b) { header('Location: /admin/buyers.php'); exit; }

$stmt = $pdo->prepare('
    SELECT o.id, o.buyer_user_id, o.subtotal, o.status, o.created_at,
           bus.name AS business_name
    FROM orders o
    JOIN businesses bus ON bus.id = o.business_id
    WHERE o.buyer_user_id = ?
    ORDER BY o.created_at DESC
');
$stmt->execute([$id]);
$orders = $stmt->fetchAll();

$success = $_SESSION['admin_success'] ?? '';
$error   = $_SESSION['admin_error']   ?? '';
unset($_SESSION['admin_success'], $_SESSION['admin_error']);

$refundCount        = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'refund_requested'")->fetchColumn();
$pendingPayoutCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered' AND delivered_at IS NOT NULL AND delivered_at < DATE_SUB(NOW(), INTERVAL " . PAYOUT_WINDOW_SECONDS . " SECOND)")->fetchColumn();
$pendingVendorCount = (int)$pdo->query("SELECT COUNT(*) FROM businesses WHERE approved = 0")->fetchColumn();

$statusClass = $b['banned'] ? 'badge-red' : 'badge-green';
$statusLabel = $b['banned'] ? 'Banned' : 'Active';
$adminSection = 'admin';
$adminTab     = 'buyers';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — <?= htmlspecialchars($b['name'] ?: $b['email']) ?></title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/admin/admin.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <?php require __DIR__ . '/admin-tabs.php'; ?>

    <a href="/admin/buyers.php" class="detail-back">← Buyers</a>

    <?php if ($success): ?><p class="admin-success"><?= htmlspecialchars($success) ?></p><?php endif; ?>
    <?php if ($error):   ?><p class="admin-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

    <div class="detail-header">
        <h1><?= htmlspecialchars($b['name'] ?: $b['email']) ?></h1>
        <span class="order-badge <?= $statusClass ?>"><?= $statusLabel ?></span>
    </div>

    <div class="detail-columns">
        <div>
            <!-- Profile -->
            <div class="detail-card">
                <div class="detail-card-title">Profile</div>
                <div class="detail-row"><span class="detail-row-label">Email</span><span class="detail-row-value"><?= htmlspecialchars($b['email']) ?></span></div>
                <?php if ($b['phone']): ?>
                <div class="detail-row"><span class="detail-row-label">Phone</span><span class="detail-row-value"><?= htmlspecialchars($b['phone']) ?></span></div>
                <?php endif; ?>
                <div class="detail-row"><span class="detail-row-label">Joined</span><span class="detail-row-value"><?= date('M j, Y', strtotime($b['created_at'])) ?></span></div>
            </div>

            <?php
            $addrParts = array_filter([
                trim(($b['house_number'] ?? '') . ' ' . ($b['address'] ?? '')),
                $b['sangkat'] ?? '',
                $b['khan'] ?? '',
            ]);
            $addrLine = implode(', ', $addrParts);
            ?>
            <?php if ($addrLine): ?>
            <div class="detail-card">
                <div class="detail-card-title">Delivery address</div>
                <div class="detail-row"><span class="detail-row-label">Address</span><span class="detail-row-value"><?= htmlspecialchars($addrLine) ?>, Phnom Penh</span></div>
            </div>
            <?php endif; ?>

            <!-- Activity -->
            <div class="detail-card">
                <div class="detail-card-title">Activity</div>
                <div class="detail-row"><span class="detail-row-label">Orders</span><span class="detail-row-value"><?= (int)$b['order_count'] ?></span></div>
                <div class="detail-row"><span class="detail-row-label">Total spent</span><span class="detail-row-value">$<?= number_format($b['total_spent'], 2) ?></span></div>
                <div class="detail-row"><span class="detail-row-label">Refund requests</span><span class="detail-row-value<?= $b['refund_count'] > 0 ? ' popup-row-value--warn' : '' ?>"><?= (int)$b['refund_count'] ?></span></div>
            </div>
        </div>

        <div>
            <!-- Internal note -->
            <div class="detail-card">
                <div class="detail-card-title">Internal note</div>
                <form method="POST" action="/admin/buyer-action.php">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="note">
                    <input type="hidden" name="buyer_id" value="<?= $b['id'] ?>">
                    <textarea name="admin_note" rows="3" class="penalty-textarea" placeholder="Internal note (not visible to buyer)…"><?= htmlspecialchars($b['admin_note'] ?? '') ?></textarea>
                    <button type="submit" class="btn-approve" style="margin-top:0.5rem;width:100%;">Save note</button>
                </form>
            </div>

            <!-- Ban / Unban -->
            <div class="detail-card">
                <?php if ($b['banned']): ?>
                <div class="detail-card-title">Suspended</div>
                <?php if ($b['ban_reason']): ?>
                <div class="detail-row"><span class="detail-row-label">Reason</span><span class="detail-row-value"><?= htmlspecialchars($b['ban_reason']) ?></span></div>
                <?php endif; ?>
                <?php if ($b['banned_at']): ?>
                <div class="detail-row"><span class="detail-row-label">Since</span><span class="detail-row-value"><?= date('M j, Y', strtotime($b['banned_at'])) ?></span></div>
                <?php endif; ?>
                <form method="POST" action="/admin/buyer-action.php" style="margin-top:0.75rem;">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="unban">
                    <input type="hidden" name="buyer_id" value="<?= $b['id'] ?>">
                    <button type="submit" class="btn-approve" style="width:100%;">Lift suspension</button>
                </form>
                <?php else: ?>
                <div class="detail-card-title">Suspend account</div>
                <form method="POST" action="/admin/buyer-action.php">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="ban">
                    <input type="hidden" name="buyer_id" value="<?= $b['id'] ?>">
                    <textarea name="ban_reason" rows="2" class="penalty-textarea" placeholder="Reason for suspension (internal only)…" required></textarea>
                    <button type="submit" class="btn-reject" style="margin-top:0.5rem;width:100%;">Suspend account</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!empty($orders)): ?>
    <div class="detail-card">
        <div class="detail-card-title">Order history</div>
        <table class="orders-table">
            <thead>
                <tr>
                    <th>Ref</th>
                    <th>Business</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $o):
                $oref = date('ymd', strtotime($o['created_at'])) . '-' . str_pad($o['id'], 4, '0', STR_PAD_LEFT);
            ?>
            <tr>
                <td><a href="/admin/order.php?id=<?= $o['id'] ?>"><?= $oref ?></a></td>
                <td><?= htmlspecialchars($o['business_name']) ?></td>
                <td>$<?= number_format($o['subtotal'], 2) ?></td>
                <td><?= htmlspecialchars($o['status']) ?></td>
                <td><?= date('M j, Y', strtotime($o['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
