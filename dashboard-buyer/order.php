<?php
session_start();
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'buyer') {
    header('Location: /login-buyer/');
    exit;
}

$userId  = $_SESSION['user_id'];
$orderId = (int)($_GET['id'] ?? 0);
if (!$orderId) {
    header('Location: /dashboard-buyer/');
    exit;
}

$stmt = $pdo->prepare('
    SELECT o.id, o.subtotal, o.delivery_fee, o.status, o.created_at, o.tracking_url,
           o.refund_reason, o.return_tracking_url,
           CASE WHEN o.delivered_at IS NULL OR TIMESTAMPDIFF(SECOND, o.delivered_at, NOW()) < ' . PAYOUT_WINDOW_SECONDS . ' THEN 1 ELSE 0 END AS refund_window_open,
           DATE_ADD(o.delivered_at, INTERVAL ' . PAYOUT_WINDOW_SECONDS . ' SECOND) AS refund_deadline,
           b.name AS business_name,
           b.house_number AS biz_house_number, b.address AS biz_address,
           b.address_notes AS biz_address_notes, b.khan AS biz_khan, b.sangkat AS biz_sangkat,
           v.name AS vendor_name, v.email AS vendor_email
    FROM orders o
    JOIN businesses b ON b.id = o.business_id
    JOIN vendors v ON v.id = b.user_id
    WHERE o.id = ? AND o.buyer_user_id = ?
');
$stmt->execute([$orderId, $userId]);
$o = $stmt->fetch();

if (!$o) {
    header('Location: /dashboard-buyer/');
    exit;
}

$stmt = $pdo->prepare('SELECT id, product_name, variant_label, quantity, price_at_purchase FROM order_items WHERE order_id = ? ORDER BY id');
$stmt->execute([$orderId]);
$items = $stmt->fetchAll();

$reviewedItemIds = [];
if (in_array($o['status'], ['delivered', 'completed']) && !empty($items)) {
    $placeholders = implode(',', array_fill(0, count($items), '?'));
    $rStmt = $pdo->prepare("SELECT order_item_id FROM reviews WHERE order_item_id IN ($placeholders)");
    $rStmt->execute(array_column($items, 'id'));
    $reviewedItemIds = array_column($rStmt->fetchAll(), 'order_item_id');
}

$oid        = date('ymd', strtotime($o['created_at'])) . '-' . str_pad($o['id'], 4, '0', STR_PAD_LEFT);
$isRefund   = in_array($o['status'], ['refund_requested','return_approved','return_dispatched','return_received','refunded','refund_rejected']);
$windowOpen = (bool)$o['refund_window_open'];
$deadline   = $o['refund_deadline'] ? date('M j, g:ia', strtotime($o['refund_deadline'])) : null;

$statusClasses = [
    'pending'           => 'badge-grey',
    'paid'              => 'badge-blue',
    'dispatched'        => 'badge-yellow',
    'delivered'         => 'badge-green',
    'completed'         => 'badge-green',
    'cancelled'         => 'badge-red',
    'refund_requested'  => 'badge-red',
    'return_approved'   => 'badge-yellow',
    'return_dispatched' => 'badge-yellow',
    'return_received'   => 'badge-yellow',
    'refunded'          => 'badge-red',
    'refund_rejected'   => 'badge-grey',
];
$statusClass = $statusClasses[$o['status']] ?? 'badge-grey';
$statusLabel = ucwords(str_replace('_', ' ', $o['status']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $oid ?> — My Orders — teepsaa</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/order-status/order-status.css">
    <link rel="stylesheet" href="/refund-status/refund-status.css">
    <link rel="stylesheet" href="/popup/popup.css">
    <link rel="stylesheet" href="/dashboard-buyer/dashboard-buyer.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="order-flash-success"><?= htmlspecialchars($_SESSION['flash_success']) ?></div>
    <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <a href="/dashboard-buyer/" style="display:inline-block;font-size:0.875rem;color:#6b7280;text-decoration:none;margin-bottom:1.25rem;">← My Orders</a>

    <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1.5rem;flex-wrap:wrap;">
        <h1 style="margin-bottom:0;"><?= $oid ?><?php if ($isRefund): ?> <span class="refund-dot"></span><?php endif; ?></h1>
        <span class="order-badge <?= $statusClass ?>"><?= $statusLabel ?></span>
    </div>

    <div class="popup-section">
        <div class="popup-section-label">Order info</div>
        <div class="popup-row"><span class="popup-row-label">Date</span><span class="popup-row-value"><?= date('M j, Y g:ia', strtotime($o['created_at'])) ?></span></div>
        <div class="popup-row"><span class="popup-row-label">Business</span><span class="popup-row-value"><?= htmlspecialchars($o['business_name']) ?></span></div>
        <div class="popup-row"><span class="popup-row-label">Vendor</span><span class="popup-row-value"><?= htmlspecialchars($o['vendor_name'] ?: $o['vendor_email']) ?></span></div>
        <?php if ($o['tracking_url'] && $o['status'] === 'dispatched'): ?>
        <div class="popup-row"><span class="popup-row-label">Tracking</span><span class="popup-row-value"><a href="<?= htmlspecialchars($o['tracking_url']) ?>" target="_blank" rel="noopener">Track delivery ↗</a></span></div>
        <?php endif; ?>
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
        <div class="popup-subtotal"><span>Delivery</span><span>$<?= number_format($o['delivery_fee'], 2) ?></span></div>
        <?php endif; ?>
        <div class="popup-total"><span>Total</span><span>$<?= number_format($o['subtotal'] + $o['delivery_fee'], 2) ?></span></div>
    </div>
    <?php endif; ?>

    <?php if (in_array($o['status'], ['delivered', 'completed']) && !empty($items)): ?>
    <div class="popup-section">
        <div class="popup-section-label">Reviews</div>
        <?php foreach ($items as $item): ?>
        <div class="review-item-row">
            <span class="review-item-name">
                <?= htmlspecialchars($item['product_name']) ?>
                <?php if ($item['variant_label']): ?>
                <span class="review-item-variant">(<?= htmlspecialchars($item['variant_label']) ?>)</span>
                <?php endif; ?>
            </span>
            <?php if (in_array($item['id'], $reviewedItemIds)): ?>
            <span class="reviewed-badge">Reviewed ✓</span>
            <?php else: ?>
            <a href="/review/?item=<?= $item['id'] ?>" class="btn-leave-review">Leave a review</a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="popup-section">
        <div class="popup-section-label">Status</div>
        <?php $orderStatus = $o['status']; require __DIR__ . '/../order-status/order-status.php'; ?>
    </div>

    <?php if ($o['status'] === 'pending'): ?>
    <p class="order-pending-note">Awaiting payment confirmation — usually within 1 hour.</p>

    <?php elseif ($o['status'] === 'dispatched'): ?>
    <hr class="popup-divider">
    <div class="popup-actions">
        <form method="POST" action="/dashboard-buyer/confirm-delivery.php">
            <?= csrf_input() ?>
            <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
            <button type="submit" class="btn-confirm">Confirm delivery</button>
        </form>
        <?php if ($o['tracking_url']): ?>
        <a href="<?= htmlspecialchars($o['tracking_url']) ?>" target="_blank" rel="noopener" class="btn-secondary">Track delivery ↗</a>
        <?php endif; ?>
    </div>

    <?php elseif ($o['status'] === 'delivered'): ?>
    <hr class="popup-divider">
    <details class="order-options">
        <summary class="order-options-toggle">Options</summary>
        <div class="order-options-body">
            <div class="popup-section-label">Issue with order?</div>
            <?php if ($windowOpen): ?>
            <p style="font-size:0.875rem;color:#6b7280;margin:0.4rem 0 0.75rem;">You will need to return the item via Grab at your own cost. Your refund will be <strong>$<?= number_format($o['subtotal'], 2) ?></strong> (delivery fee is non-refundable).<?php if ($deadline): ?> <strong>Available until <?= $deadline ?>.</strong><?php endif; ?></p>
            <form method="POST" action="/dashboard-buyer/refund-request.php" class="refund-request-form">
                <?= csrf_input() ?>
                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                <select name="reason_preset" class="refund-reason-input refund-reason-select" required>
                    <option value="">— Select a reason —</option>
                    <option>Item not as described</option>
                    <option>Wrong item received</option>
                    <option>Item arrived damaged</option>
                    <option>Missing parts or accessories</option>
                    <option>Quality not as expected</option>
                    <option value="other">Other…</option>
                </select>
                <textarea name="reason_other" rows="2" class="refund-reason-input refund-reason-other" placeholder="Describe the issue…" style="display:none;margin-top:0.4rem;"></textarea>
                <button type="submit" class="btn-refund-request">Request Refund</button>
            </form>
            <?php else: ?>
            <p class="order-refund-window order-refund-window--closed" style="margin-top:0.5rem;">Refund window has closed.</p>
            <?php endif; ?>
        </div>
    </details>

    <?php elseif ($o['status'] === 'return_approved'): ?>
    <hr class="popup-divider">
    <div class="popup-section-label">Send item back</div>
    <?php
    $retParts = array_filter([
        trim(($o['biz_house_number'] ?? '') . ' ' . ($o['biz_address'] ?? '')),
        $o['biz_sangkat'] ?? '',
        $o['biz_khan'] ?? '',
        'Phnom Penh',
    ]);
    $retAddress = implode(', ', $retParts);
    ?>
    <?php if ($retAddress && $retAddress !== 'Phnom Penh'): ?>
    <p style="font-size:0.875rem;color:#6b7280;margin:0.4rem 0 0.25rem;">Send to: <strong><?= htmlspecialchars($retAddress) ?></strong><?php if ($o['biz_address_notes']): ?> — <?= htmlspecialchars($o['biz_address_notes']) ?><?php endif; ?></p>
    <?php endif; ?>
    <p style="font-size:0.875rem;color:#6b7280;margin:0.4rem 0 0.75rem;">Pack the item and send it back via Grab at your cost. Paste the Grab tracking link below once dispatched.</p>
    <form method="POST" action="/dashboard-buyer/return-dispatch.php" class="refund-request-form">
        <?= csrf_input() ?>
        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
        <input type="url" name="return_tracking_url" required placeholder="Paste Grab return tracking URL…" class="refund-reason-input" style="resize:none;height:auto;padding:0.5rem 0.65rem;">
        <button type="submit" class="btn-confirm">Mark return dispatched</button>
    </form>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

<script>
document.addEventListener('change', function(e) {
    if (!e.target.classList.contains('refund-reason-select')) return;
    var form  = e.target.closest('form');
    var other = form && form.querySelector('.refund-reason-other');
    if (!other) return;
    var isOther = e.target.value === 'other';
    other.style.display = isOther ? '' : 'none';
    other.required      = isOther;
});
</script>
</body>
</html>
