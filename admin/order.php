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

admin_require('orders');

$orderId = (int)($_GET['id'] ?? 0);
if (!$orderId) {
    header('Location: /admin/orders.php');
    exit;
}

$stmt = $pdo->prepare('
    SELECT o.id, o.subtotal, o.delivery_fee, o.vendor_delivery_bonus,
           o.royalty_rate, o.royalty_amount, o.vendor_payout,
           o.coupon_code, o.discount_amount,
           o.delivery_distance_km,
           o.status, o.created_at, o.delivered_at, o.tracking_url,
           o.refund_reason, o.return_tracking_url, o.admin_note, o.buyer_notes,
           b.id AS business_id, b.name AS business_name,
           b.house_number AS biz_house_number, b.address AS biz_address,
           b.khan AS biz_khan, b.sangkat AS biz_sangkat,
           v.id AS vendor_id, v.name AS vendor_name, v.email AS vendor_email, v.aba_qr AS vendor_aba_qr,
           bu.id AS buyer_id, bu.name AS buyer_name, bu.email AS buyer_email,
           bu.phone AS buyer_phone,
           bu.house_number AS buyer_house_number, bu.address AS buyer_address,
           bu.address_notes AS buyer_address_notes,
           bu.khan AS buyer_khan, bu.sangkat AS buyer_sangkat,
           p.id AS payment_id, p.status AS payment_status, p.total AS payment_total
    FROM orders o
    JOIN businesses b ON b.id = o.business_id
    JOIN vendors v ON v.id = b.user_id
    JOIN buyers bu ON bu.id = o.buyer_user_id
    JOIN payments p ON p.id = o.payment_id
    WHERE o.id = ?
');
$stmt->execute([$orderId]);
$o = $stmt->fetch();

if (!$o) {
    header('Location: /admin/orders.php');
    exit;
}

$stmt = $pdo->prepare('SELECT product_name, quantity, price_at_purchase FROM order_items WHERE order_id = ? ORDER BY id');
$stmt->execute([$orderId]);
$items = $stmt->fetchAll();

$oid = date('ymd', strtotime($o['created_at'])) . '-' . str_pad($o['id'], 4, '0', STR_PAD_LEFT);

$royaltyAmt   = round((float)($o['royalty_amount'] ?? ($o['subtotal'] * ($o['royalty_rate'] ?? 0))), 2);
$royaltyPct   = round(($o['royalty_rate'] ?? 0) * 100, 1);
// A vendor-owned coupon is deducted from vendor_payout at checkout (a sitewide/admin
// coupon isn't — the platform absorbs that one). Derive the vendor-funded portion from
// the stored numbers so this recomputed breakdown stays correct either way.
$vendorCouponDiscount = max(0, round($o['subtotal'] - $royaltyAmt - (float)$o['vendor_payout'], 2));
$vendorPayout = round($o['subtotal'] - $royaltyAmt - $vendorCouponDiscount + $o['delivery_fee'] + $o['vendor_delivery_bonus'], 2);
$windowPassed = $o['delivered_at'] && (time() - strtotime($o['delivered_at'])) >= PAYOUT_WINDOW_SECONDS;
$windowTime   = $o['delivered_at'] ? date('M j, g:ia', strtotime($o['delivered_at']) + PAYOUT_WINDOW_SECONDS) : null;

$statusClasses = [
    'pending'          => 'badge-grey',
    'paid'             => 'badge-blue',
    'dispatched'       => 'badge-yellow',
    'delivered'        => 'badge-green',
    'completed'        => 'badge-green',
    'cancelled'        => 'badge-red',
    'refund_requested' => 'badge-red',
    'return_approved'  => 'badge-yellow',
    'return_dispatched'=> 'badge-yellow',
    'return_received'  => 'badge-yellow',
    'refunded'         => 'badge-red',
    'refund_rejected'  => 'badge-grey',
];
$statusClass = $statusClasses[$o['status']] ?? 'badge-grey';
$statusLabel = ucwords(str_replace('_', ' ', $o['status']));

$refundCount        = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'refund_requested'")->fetchColumn();
$pendingPayoutCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered' AND delivered_at IS NOT NULL AND delivered_at < DATE_SUB(NOW(), INTERVAL " . PAYOUT_WINDOW_SECONDS . " SECOND)")->fetchColumn();
$unreadMsgCount     = (int)$pdo->query("SELECT COUNT(DISTINCT thread_id) FROM support_messages WHERE sender IN ('buyer','vendor') AND read_at IS NULL")->fetchColumn();

$grabParts = array_filter([
    trim(($o['buyer_house_number'] ?? '') . ' ' . ($o['buyer_address'] ?? '')),
    $o['buyer_sangkat'] ?? '',
    $o['buyer_khan'] ?? '',
    'Phnom Penh',
]);
$grabAddress = implode(', ', $grabParts);
$adminSection = 'orders';
$adminTab     = 'orders';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order <?= $oid ?> — Admin</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/admin/admin.css">
    <link rel="stylesheet" href="/admin/order-detail.css">
    <link rel="stylesheet" href="/popup/popup.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <?php if (!isset($pendingVendorCount)) { $pendingVendorCount = (int)$pdo->query("SELECT COUNT(*) FROM businesses WHERE approved = 0")->fetchColumn(); } ?>
    <?php require __DIR__ . '/admin-tabs.php'; ?>

    <div class="od-header">
                <h1><?= $oid ?></h1>
        <span class="order-badge <?= $statusClass ?>"><?= $statusLabel ?></span>

    </div>

    <div class="od-grid">

        <!-- Left column -->
        <div class="od-col">

            <div class="od-card">
                <div class="od-card-title">Items</div>
                <div class="od-table">
                    <div class="od-table-head">
                        <span>Product</span><span>Qty</span><span>Price</span><span>Total</span>
                    </div>
                    <div class="od-table-body">
                    <?php foreach ($items as $item): ?>
                        <div class="od-table-row">
                            <span><?= htmlspecialchars($item['product_name']) ?></span>
                            <span><?= (int)$item['quantity'] ?></span>
                            <span>$<?= number_format($item['price_at_purchase'], 2) ?></span>
                            <span>$<?= number_format($item['price_at_purchase'] * $item['quantity'], 2) ?></span>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>
                <div class="od-totals">
                    <div class="od-total-row">
                        <span>Subtotal</span>
                        <span>$<?= number_format($o['subtotal'], 2) ?></span>
                    </div>
                    <?php if ($o['discount_amount'] > 0): ?>
                    <div class="od-total-row">
                        <span>Coupon (<?= htmlspecialchars($o['coupon_code']) ?>)</span>
                        <span>−$<?= number_format($o['discount_amount'], 2) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="od-total-row od-total-row--note">
                        <span>Grab delivery</span>
                        <span>Buyer pays driver on arrival</span>
                    </div>
                    <?php if ($o['delivery_distance_km']): ?>
                    <div class="od-total-row od-total-row--note">
                        <span>Distance</span>
                        <span><?= $o['delivery_distance_km'] ?> km</span>
                    </div>
                    <?php endif; ?>
                    <div class="od-total-row od-total-row--bold">
                        <span>Total charged</span>
                        <span>$<?= number_format($o['subtotal'] - $o['discount_amount'], 2) ?></span>
                    </div>
                </div>
            </div>

            <div class="od-card">
                <div class="od-card-title">Payout</div>
                <div class="od-row"><span>Subtotal</span><span>$<?= number_format($o['subtotal'], 2) ?></span></div>
                <div class="od-row"><span>Royalty (<?= $royaltyPct ?>%)</span><span>−$<?= number_format($royaltyAmt, 2) ?></span></div>
                <div class="od-row od-row--bold"><span>Vendor payout</span><span>$<?= number_format($o['vendor_payout'], 2) ?></span></div>
            </div>

            <?php if ($o['refund_reason'] || in_array($o['status'], ['refund_requested','return_approved','return_dispatched','return_received','refunded','refund_rejected'])): ?>
            <div class="od-card od-card--warn">
                <div class="od-card-title">Refund / Return</div>
                <div class="od-row"><span>Status</span><span><span class="order-badge <?= $statusClass ?>"><?= $statusLabel ?></span></span></div>
                <?php if ($o['refund_reason']): ?>
                <div class="od-row"><span>Reason</span><span><?= htmlspecialchars($o['refund_reason']) ?></span></div>
                <?php endif; ?>
                <?php if ($o['return_tracking_url']): ?>
                <div class="od-row"><span>Return tracking</span><span><a href="<?= htmlspecialchars($o['return_tracking_url']) ?>" target="_blank" rel="noopener">View ↗</a></span></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div>

        <!-- Right column -->
        <div class="od-col">

            <div class="od-card">
                <div class="od-card-title">Buyer</div>
                <div class="od-row"><span>Name</span><span><a href="/admin/buyers.php"><?= htmlspecialchars($o['buyer_name']) ?></a></span></div>
                <div class="od-row"><span>Email</span><span><?= htmlspecialchars($o['buyer_email']) ?></span></div>
                <?php if ($o['buyer_phone']): ?>
                <div class="od-row"><span>Phone</span><span><?= htmlspecialchars($o['buyer_phone']) ?></span></div>
                <?php endif; ?>
                <?php if ($grabAddress && $grabAddress !== 'Phnom Penh'): ?>
                <div class="od-row"><span>Delivery address</span><span><?= htmlspecialchars($grabAddress) ?></span></div>
                <?php endif; ?>
                <?php if ($o['buyer_address_notes']): ?>
                <div class="od-row"><span>Floor / Unit</span><span><?= htmlspecialchars($o['buyer_address_notes']) ?></span></div>
                <?php endif; ?>
                <?php if ($o['buyer_notes']): ?>
                <div class="od-row"><span>Delivery note</span><span><?= htmlspecialchars($o['buyer_notes']) ?></span></div>
                <?php endif; ?>
            </div>

            <div class="od-card">
                <div class="od-card-title">Business</div>
                <div class="od-row"><span>Business Name</span><span><?= htmlspecialchars($o['business_name']) ?></span></div>
                <div class="od-row"><span>Vendor Contact Name</span><span><?= htmlspecialchars($o['vendor_name'] ?: $o['vendor_email']) ?></span></div>
                <div class="od-row"><span>Email</span><span><?= htmlspecialchars($o['vendor_email']) ?></span></div>
            </div>

            <div class="od-card">
                <div class="od-card-title">Payment</div>
                <div class="od-row"><span>Date</span><span><?= date('M j, Y g:ia', strtotime($o['created_at'])) ?></span></div>
                <div class="od-row"><span>Amount</span><span>$<?= number_format($o['payment_total'], 2) ?></span></div>
                <div class="od-row"><span>Status</span><span><?= htmlspecialchars(ucwords(str_replace('_', ' ', $o['payment_status']))) ?></span></div>
            </div>

            <?php if ($o['tracking_url']): ?>
            <div class="od-card">
                <div class="od-card-title">Dispatch</div>
                <div class="od-row"><span>Grab tracking</span><span><a href="<?= htmlspecialchars($o['tracking_url']) ?>" target="_blank" rel="noopener">View tracking ↗</a></span></div>
                <?php if ($o['delivered_at']): ?>
                <div class="od-row"><span>Delivered at</span><span><?= date('M j, Y g:ia', strtotime($o['delivered_at'])) ?></span></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($o['payment_status'] === 'pending_confirmation'): ?>
            <?php // Confirming applies to the whole payment, which can span several
                  // vendors' orders from one cart — so verify the payment total
                  $payCountStmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE payment_id = ?');
                  $payCountStmt->execute([$o['payment_id']]);
                  $payOrderCount = (int)$payCountStmt->fetchColumn(); ?>
            <div class="od-card">
                <div class="od-card-title">Payment confirmation</div>
                <p style="font-size:0.875rem;color:#6b7280;margin:0 0 0.75rem;">Verify <strong>$<?= number_format($o['payment_total'], 2) ?></strong> received in ABA before confirming.<?= $payOrderCount > 1 ? ' This payment covers <strong>' . $payOrderCount . ' orders</strong> from one cart — confirming marks all of them paid.' : '' ?></p>
                <div class="popup-actions">
                    <form method="POST" action="/admin/payments-action.php">
                        <?= csrf_input() ?>
                        <input type="hidden" name="payment_id" value="<?= $o['payment_id'] ?>">
                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                        <button type="submit" name="action" value="confirm" class="btn-approve">Confirm payment</button>
                    </form>
                    <form method="POST" action="/admin/payments-action.php">
                        <?= csrf_input() ?>
                        <input type="hidden" name="payment_id" value="<?= $o['payment_id'] ?>">
                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                        <button type="submit" name="action" value="reject" class="btn-reject">Reject</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($o['status'] === 'delivered'): ?>
            <div class="od-card">
                <div class="od-card-title">Vendor payout</div>
                <div class="od-row"><span>Subtotal</span><span>$<?= number_format($o['subtotal'], 2) ?></span></div>
                <div class="od-row"><span>Royalty (<?= $royaltyPct ?>%)</span><span>−$<?= number_format($royaltyAmt, 2) ?></span></div>
                <?php if ($vendorCouponDiscount > 0): ?>
                <div class="od-row"><span>Coupon (<?= htmlspecialchars($o['coupon_code']) ?>)</span><span>−$<?= number_format($vendorCouponDiscount, 2) ?></span></div>
                <?php endif; ?>
                <?php if ($o['delivery_fee'] > 0): ?>
                <div class="od-row"><span>Delivery</span><span>+$<?= number_format($o['delivery_fee'], 2) ?></span></div>
                <?php endif; ?>
                <?php if ($o['vendor_delivery_bonus'] > 0): ?>
                <div class="od-row"><span>Delivery buffer</span><span>+$<?= number_format($o['vendor_delivery_bonus'], 2) ?></span></div>
                <?php endif; ?>
                <div class="od-row od-row--bold"><span>Vendor payout</span><span>$<?= number_format($vendorPayout, 2) ?></span></div>
                <?php if (!$windowPassed && $windowTime): ?>
                <p style="font-size:0.8rem;color:#92400e;background:#fffbeb;border:1px solid #fde68a;border-radius:4px;padding:0.4rem 0.75rem;margin:0.75rem 0 0;">Refund window closes <?= $windowTime ?> — pay out after this time.</p>
                <?php endif; ?>
                <details class="payout-confirm" style="margin-top:0.75rem;"<?= $windowPassed ? ' open' : '' ?>>
                    <summary class="payout-confirm-toggle">Has the refund window closed? Confirm payout</summary>
                    <div class="payout-confirm-body">
                        <p style="font-size:0.875rem;color:#6b7280;margin:0 0 0.75rem;">Scan the vendor's ABA QR and send <strong>$<?= number_format($vendorPayout, 2) ?></strong>, then mark as completed.</p>
                        <?php if ($o['vendor_aba_qr']): ?>
                            <img src="/uploads/<?= htmlspecialchars($o['vendor_aba_qr']) ?>" alt="Vendor ABA QR" style="width:160px;height:160px;object-fit:contain;border:1px solid #e5e7eb;border-radius:6px;display:block;margin-bottom:1rem;">
                        <?php else: ?>
                            <p style="font-size:0.875rem;color:#ef4444;margin-bottom:1rem;">Vendor has not uploaded an ABA QR code yet.</p>
                        <?php endif; ?>
                        <div class="popup-actions">
                            <form method="POST" action="/admin/payouts-action.php">
                                <?= csrf_input() ?>
                                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                <button type="submit" class="btn-approve">Mark completed</button>
                            </form>
                        </div>
                    </div>
                </details>
            </div>
            <?php endif; ?>

            <!-- Admin note -->
            <div class="od-card">
                <div class="od-card-title">Internal note</div>
                <form method="POST" action="/admin/order-action.php">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="save_note">
                    <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                    <textarea name="admin_note" rows="3" style="width:100%;padding:0.4rem 0.55rem;border:1px solid #d1d5db;border-radius:4px;font-size:0.875rem;font-family:inherit;resize:vertical;box-sizing:border-box;" placeholder="Internal note (not visible to buyer or vendor)…"><?= htmlspecialchars($o['admin_note'] ?? '') ?></textarea>
                    <button type="submit" class="btn-approve" style="margin-top:0.5rem;width:100%;">Save note</button>
                </form>
            </div>

            <?php if (in_array($o['status'], ['pending', 'paid'])): ?>
            <!-- Cancel order -->
            <div class="od-card od-card--warn">
                <div class="od-card-title">Cancel order</div>
                <p style="font-size:0.875rem;color:#6b7280;margin:0 0 0.75rem;">Stock will be restored automatically. <?= $o['status'] === 'paid' ? '<strong>Payment has already been confirmed — handle the buyer refund separately via ABA.</strong>' : '' ?></p>
                <form method="POST" action="/admin/order-action.php" onsubmit="return confirm('Cancel this order? Stock will be restored.')">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                    <textarea name="cancel_reason" rows="2" style="width:100%;padding:0.4rem 0.55rem;border:1px solid #d1d5db;border-radius:4px;font-size:0.875rem;font-family:inherit;resize:vertical;box-sizing:border-box;margin-bottom:0.5rem;" placeholder="Reason for cancellation…" required></textarea>
                    <button type="submit" class="btn-reject" style="width:100%;">Cancel order</button>
                </form>
            </div>
            <?php endif; ?>

        </div>
    </div>

</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
