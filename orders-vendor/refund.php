<?php
session_start();
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header('Location: /login-vendor/');
    exit;
}

$userId  = $_SESSION['user_id'];
$orderId = (int)($_GET['id'] ?? 0);
if (!$orderId) {
    header('Location: /orders-vendor/?tab=refunds');
    exit;
}

$refundStatuses = ['refund_requested','return_approved','return_dispatched','return_received','refunded','refund_rejected'];
$statusIn = implode(',', array_map([$pdo, 'quote'], $refundStatuses));

$stmt = $pdo->prepare("
    SELECT o.id, o.subtotal, o.delivery_fee, o.status, o.created_at,
           o.refund_reason, o.return_tracking_url,
           b.name AS business_name,
           u.name AS buyer_name, u.email AS buyer_email
    FROM orders o
    JOIN businesses b ON b.id = o.business_id
    JOIN buyers u ON u.id = o.buyer_user_id
    WHERE o.id = ? AND b.user_id = ? AND o.status IN ($statusIn)
");
$stmt->execute([$orderId, $userId]);
$o = $stmt->fetch();

if (!$o) {
    header('Location: /orders-vendor/?tab=refunds');
    exit;
}

$stmt = $pdo->prepare('SELECT product_name, variant_label, quantity, price_at_purchase FROM order_items WHERE order_id = ? ORDER BY id');
$stmt->execute([$orderId]);
$items = $stmt->fetchAll();

$oid = date('ymd', strtotime($o['created_at'])) . '-' . str_pad($o['id'], 4, '0', STR_PAD_LEFT);

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
$statusClass = $statusClasses[$o['status']] ?? 'badge-grey';
$statusLabel = $statusLabels[$o['status']] ?? ucwords(str_replace('_', ' ', $o['status']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $oid ?> — Refund — teepsaa</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/refund-status/refund-status.css">
    <link rel="stylesheet" href="/popup/popup.css">
    <link rel="stylesheet" href="/dashboard-vendor/dashboard-vendor.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <a href="/orders-vendor/?tab=refunds" style="display:inline-block;font-size:0.875rem;color:#6b7280;text-decoration:none;margin-bottom:1.25rem;">← Refunds</a>

    <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1.5rem;flex-wrap:wrap;">
        <h1 style="margin-bottom:0;"><?= $oid ?> — Refund</h1>
        <span class="order-badge <?= $statusClass ?>"><?= $statusLabel ?></span>
    </div>

    <div class="popup-section">
        <div class="popup-section-label">Order info</div>
        <div class="popup-row"><span class="popup-row-label">Date</span><span class="popup-row-value"><?= date('M j, Y g:ia', strtotime($o['created_at'])) ?></span></div>
        <div class="popup-row"><span class="popup-row-label">Customer</span><span class="popup-row-value"><?= htmlspecialchars($o['buyer_name'] ?: $o['buyer_email']) ?></span></div>
        <div class="popup-row"><span class="popup-row-label">Business</span><span class="popup-row-value"><?= htmlspecialchars($o['business_name']) ?></span></div>
    </div>

    <?php if (!empty($items)): ?>
    <div class="popup-section">
        <div class="popup-section-label">Items</div>
        <table class="popup-items">
            <thead><tr><th>Product</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <?= htmlspecialchars($item['product_name']) ?>
                        <?php if ($item['variant_label']): ?>
                            <br><small style="color:#9ca3af"><?= htmlspecialchars($item['variant_label']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= (int)$item['quantity'] ?></td>
                    <td>$<?= number_format($item['price_at_purchase'], 2) ?></td>
                    <td>$<?= number_format($item['price_at_purchase'] * $item['quantity'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="popup-subtotal"><span>Subtotal</span><span>$<?= number_format($o['subtotal'], 2) ?></span></div>
        <?php if ($o['delivery_fee'] > 0): ?>
        <div class="popup-subtotal"><span>Delivery (non-refundable)</span><span>$<?= number_format($o['delivery_fee'], 2) ?></span></div>
        <?php endif; ?>
        <div class="popup-total"><span>Refund to buyer</span><span>$<?= number_format($o['subtotal'], 2) ?></span></div>
    </div>
    <?php endif; ?>

    <?php if ($o['refund_reason']): ?>
    <div class="popup-section">
        <div class="popup-section-label">Buyer's reason</div>
        <p style="font-size:0.875rem;color:#374151;font-style:italic;margin:0;">"<?= htmlspecialchars($o['refund_reason']) ?>"</p>
    </div>
    <?php endif; ?>

    <div class="popup-section">
        <div class="popup-section-label">Refund status</div>
        <?php $refundStatus = $o['status']; require __DIR__ . '/../refund-status/refund-status.php'; ?>
    </div>

    <?php if ($o['status'] === 'return_dispatched'): ?>
    <hr class="popup-divider">
    <div class="popup-section-label" style="margin-bottom:0.5rem;">Return delivery</div>
    <?php if ($o['return_tracking_url']): ?>
    <div class="popup-row" style="margin-bottom:0.75rem;"><span class="popup-row-label">Grab link</span><span class="popup-row-value"><a href="<?= htmlspecialchars($o['return_tracking_url']) ?>" target="_blank" rel="noopener">Track return ↗</a></span></div>
    <?php endif; ?>
    <p style="font-size:0.875rem;color:#6b7280;margin:0 0 0.75rem;">Click below once you have received the item back.</p>
    <form method="POST" action="/products/return-received.php">
        <?= csrf_input() ?>
        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
        <button type="submit" class="btn-confirm">Confirm item received</button>
    </form>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
