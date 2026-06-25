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
    header('Location: /orders-vendor/');
    exit;
}

$stmt = $pdo->prepare('
    SELECT o.id, o.subtotal, o.delivery_fee, o.vendor_delivery_bonus,
           o.royalty_rate, o.royalty_amount, o.vendor_payout,
           o.status, o.created_at, o.tracking_url, o.buyer_notes,
           b.name AS business_name,
           u.name AS buyer_name, u.email AS buyer_email, u.phone AS buyer_phone,
           u.house_number AS buyer_house_number, u.address AS buyer_address,
           u.address_notes AS buyer_address_notes,
           u.khan AS buyer_khan, u.sangkat AS buyer_sangkat
    FROM orders o
    JOIN businesses b ON b.id = o.business_id
    JOIN buyers u ON u.id = o.buyer_user_id
    WHERE o.id = ? AND b.user_id = ?
      AND o.status IN (\'pending\', \'paid\', \'dispatched\', \'delivered\', \'completed\')
');
$stmt->execute([$orderId, $userId]);
$o = $stmt->fetch();

if (!$o) {
    header('Location: /orders-vendor/');
    exit;
}

$stmt = $pdo->prepare('SELECT product_name, variant_label, quantity, price_at_purchase FROM order_items WHERE order_id = ? ORDER BY id');
$stmt->execute([$orderId]);
$items = $stmt->fetchAll();

$oid = date('ymd', strtotime($o['created_at'])) . '-' . str_pad($o['id'], 4, '0', STR_PAD_LEFT);

$royaltyAmt   = round((float)($o['royalty_amount'] ?? ($o['subtotal'] * ($o['royalty_rate'] ?? 0))), 2);
$royaltyPct   = round(($o['royalty_rate'] ?? 0) * 100, 1);
$vendorPayout = round($o['subtotal'] - $royaltyAmt + $o['delivery_fee'] + $o['vendor_delivery_bonus'], 2);

$grabParts = array_filter([
    trim(($o['buyer_house_number'] ?? '') . ' ' . ($o['buyer_address'] ?? '')),
    $o['buyer_sangkat'] ?? '',
    $o['buyer_khan'] ?? '',
    'Phnom Penh',
]);
$grabAddress = implode(', ', $grabParts);

$statusClasses = [
    'pending'    => 'badge-grey',
    'paid'       => 'badge-blue',
    'dispatched' => 'badge-yellow',
    'delivered'  => 'badge-green',
    'completed'  => 'badge-green',
    'cancelled'  => 'badge-red',
];
$statusClass = $statusClasses[$o['status']] ?? 'badge-grey';
$statusLabel = ucwords(str_replace('_', ' ', $o['status']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $oid ?> — Orders — teepsaa</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/order-status/order-status.css">
    <link rel="stylesheet" href="/popup/popup.css">
    <link rel="stylesheet" href="/dashboard-vendor/dashboard-vendor.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <a href="/orders-vendor/" style="display:inline-block;font-size:0.875rem;color:#6b7280;text-decoration:none;margin-bottom:1.25rem;">← Orders</a>

    <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1.5rem;flex-wrap:wrap;">
        <h1 style="margin-bottom:0;"><?= $oid ?></h1>
        <span class="order-badge <?= $statusClass ?>"><?= $statusLabel ?></span>
    </div>

    <div class="popup-section">
        <div class="popup-section-label">Order info</div>
        <div class="popup-row"><span class="popup-row-label">Date</span><span class="popup-row-value"><?= date('M j, Y g:ia', strtotime($o['created_at'])) ?></span></div>
        <div class="popup-row"><span class="popup-row-label">Customer</span><span class="popup-row-value"><?= htmlspecialchars($o['buyer_name'] ?: $o['buyer_email']) ?></span></div>
        <?php if ($o['buyer_phone']): ?>
        <div class="popup-row"><span class="popup-row-label">Phone</span><span class="popup-row-value"><?= htmlspecialchars($o['buyer_phone']) ?></span></div>
        <?php endif; ?>
    </div>

    <div class="popup-section">
        <div class="popup-section-label">Delivery address</div>
        <?php if ($grabAddress && $grabAddress !== 'Phnom Penh'): ?>
        <div class="popup-row"><span class="popup-row-label">Grab address</span><span class="popup-row-value"><?= htmlspecialchars($grabAddress) ?></span></div>
        <?php endif; ?>
        <?php if ($o['buyer_address_notes']): ?>
        <div class="popup-row"><span class="popup-row-label">Floor / Unit</span><span class="popup-row-value"><?= htmlspecialchars($o['buyer_address_notes']) ?></span></div>
        <?php endif; ?>
        <?php if ($o['buyer_notes']): ?>
        <div class="popup-row" style="background:#fefce8"><span class="popup-row-label">Delivery note</span><span class="popup-row-value"><?= htmlspecialchars($o['buyer_notes']) ?></span></div>
        <?php endif; ?>
        <?php if (!$o['buyer_address'] && !$o['buyer_khan']): ?>
        <p style="font-size:0.875rem;color:#9ca3af;margin:0;">No address set by buyer.</p>
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

        <div class="popup-payout-box">
            <?php if ($royaltyPct > 0): ?>
            <div class="popup-subtotal"><span>Royalty fee (<?= $royaltyPct ?>%)</span><span>−$<?= number_format($royaltyAmt, 2) ?></span></div>
            <?php endif; ?>
            <?php if ($o['delivery_fee'] > 0): ?>
            <div class="popup-subtotal"><span>Delivery reimbursement</span><span>+$<?= number_format($o['delivery_fee'], 2) ?></span></div>
            <?php endif; ?>
            <?php if ($o['vendor_delivery_bonus'] > 0): ?>
            <div class="popup-subtotal"><span>Delivery buffer</span><span>+$<?= number_format($o['vendor_delivery_bonus'], 2) ?></span></div>
            <?php endif; ?>
            <div class="popup-total popup-total--payout"><span>Your payout</span><span>$<?= number_format($vendorPayout, 2) ?></span></div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($o['tracking_url'] && in_array($o['status'], ['dispatched', 'delivered', 'completed'])): ?>
    <div class="popup-section">
        <div class="popup-section-label">Tracking link</div>
        <div class="popup-row"><span class="popup-row-label">Grab</span><span class="popup-row-value"><a href="<?= htmlspecialchars($o['tracking_url']) ?>" target="_blank" rel="noopener">View tracking ↗</a></span></div>
    </div>
    <?php endif; ?>

    <div class="popup-section">
        <div class="popup-section-label">Status</div>
        <?php $orderStatus = $o['status']; require __DIR__ . '/../order-status/order-status.php'; ?>
    </div>

    <?php if ($o['status'] === 'paid'): ?>
    <hr class="popup-divider">
    <div class="popup-section-label" style="margin-bottom:0.5rem;">Dispatch</div>
    <div class="dispatch-cod-warning">
        <strong>Do not use Cash on Delivery (COD)</strong> when booking your Grab delivery. The buyer has already paid teepsaa. If you enable COD, the buyer will be charged a second time by the driver. This will result in an immediate ban from the platform.
    </div>
    <p style="font-size:0.85rem;color:#6b7280;margin:0 0 0.75rem;">Book the delivery in Grab, then paste the tracking link below.</p>
    <form method="POST" action="/dashboard-vendor/dispatch.php" class="dispatch-form">
        <?= csrf_input() ?>
        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
        <input type="url" name="tracking_url" required placeholder="Paste Grab tracking URL…"
               oninput="this.closest('form').querySelector('[type=submit]').disabled=!this.value.trim()">
        <button type="submit" class="btn-dispatch" disabled>Mark dispatched</button>
    </form>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
