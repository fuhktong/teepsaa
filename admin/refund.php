<?php
session_start([
    'gc_maxlifetime'  => 28800,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/url.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/admin-auth.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: /login-admin/');
    exit;
}

admin_require('refunds');

$orderId = (int)($_GET['id'] ?? 0);
if (!$orderId) {
    header('Location: /admin/refunds.php');
    exit;
}

$refundStatuses = ['refund_requested','return_approved','return_dispatched','return_received','refunded','refund_rejected'];
$statusIn = implode(',', array_map([$pdo, 'quote'], $refundStatuses));

$stmt = $pdo->prepare("
    SELECT o.id, o.subtotal, o.delivery_fee, o.coupon_code, o.discount_amount, o.status, o.created_at,
           o.refund_reason, o.refund_requested_at, o.refund_rejected_at, o.refund_closed_at,
           o.return_tracking_url, o.delivered_at, o.paid_out_at,
           b.name AS business_name,
           bu.name AS buyer_name, bu.email AS buyer_email,
           v.name AS vendor_name, v.aba_qr AS vendor_aba_qr
    FROM orders o
    JOIN businesses b ON b.id = o.business_id
    JOIN buyers bu ON bu.id = o.buyer_user_id
    JOIN vendors v ON v.id = b.user_id
    WHERE o.id = ? AND (o.status IN ($statusIn) OR o.refund_rejected_at IS NOT NULL)
");
$stmt->execute([$orderId]);
$o = $stmt->fetch();

// A link typed in by the other party. Anything that isn't a real http(s)
// URL becomes null here, so the surrounding `if` drops the link entirely
// rather than rendering a javascript: href (config/url.php).
if (isset($o['return_tracking_url'])) $o['return_tracking_url'] = safe_external_url($o['return_tracking_url']);

if (!$o) {
    header('Location: /admin/refunds.php');
    exit;
}

$oid = date('ymd', strtotime($o['created_at'])) . '-' . str_pad($o['id'], 4, '0', STR_PAD_LEFT);

$success = $_SESSION['admin_success'] ?? '';
$error   = $_SESSION['admin_error']   ?? '';
unset($_SESSION['admin_success'], $_SESSION['admin_error']);

$refundCount        = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'refund_requested'")->fetchColumn();
$pendingPayoutCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered' AND delivered_at IS NOT NULL AND (refund_closed_at IS NOT NULL OR delivered_at < DATE_SUB(NOW(), INTERVAL " . PAYOUT_WINDOW_SECONDS . " SECOND))")->fetchColumn();

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
// A rejected refund leaves the order back at 'delivered', so the refund state
// shown here comes from the column, not from status.
$refundState = $o['refund_rejected_at'] ? 'refund_rejected' : $o['status'];
$statusClass = $statusClasses[$refundState] ?? 'badge-grey';
$statusLabel = $statusLabels[$refundState] ?? ucwords(str_replace('_', ' ', $refundState));
$adminSection = 'orders';
$adminTab     = 'refunds';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $oid ?> — Refund — Admin</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/order-status/order-status.css">
    <link rel="stylesheet" href="/refund-status/refund-status.css">
    <link rel="stylesheet" href="/admin/admin.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>
<?php require __DIR__ . '/../admin-subnav/admin-subnav.php'; ?>

<main>
    <?php require __DIR__ . '/admin-tabs.php'; ?>
    <?php if (!isset($pendingVendorCount)) { $pendingVendorCount = (int)$pdo->query("SELECT COUNT(*) FROM businesses WHERE approved = 0")->fetchColumn(); } ?>


    <?php if ($success): ?><p class="admin-success"><?= htmlspecialchars($success) ?></p><?php endif; ?>
    <?php if ($error):   ?><p class="admin-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

    <div class="detail-header">
        <h1><?= $oid ?> — Refund</h1>
        <span class="order-badge <?= $statusClass ?>"><?= $statusLabel ?></span>
    </div>

    <div class="detail-columns">
        <!-- Left column -->
        <div>
            <div class="detail-card">
                <div class="detail-card-title">Order info</div>
                <div class="detail-row"><span class="detail-row-label">Date</span><span class="detail-row-value"><?= date('M j, Y g:ia', strtotime($o['created_at'])) ?></span></div>
                <div class="detail-row"><span class="detail-row-label">Customer</span><span class="detail-row-value"><?= htmlspecialchars($o['buyer_name'] ?: $o['buyer_email']) ?></span></div>
                <div class="detail-row"><span class="detail-row-label">Email</span><span class="detail-row-value"><?= htmlspecialchars($o['buyer_email']) ?></span></div>
                <div class="detail-row"><span class="detail-row-label">Business</span><span class="detail-row-value"><?= htmlspecialchars($o['business_name']) ?></span></div>
                <div class="detail-row"><span class="detail-row-label">Requested</span><span class="detail-row-value"><?= $o['refund_requested_at'] ? date('M j, Y g:ia', strtotime($o['refund_requested_at'])) : '—' ?></span></div>
            </div>

            <div class="detail-card">
                <div class="detail-card-title">Refund amount</div>
                <div class="detail-row"><span class="detail-row-label">Order total</span><span class="detail-row-value">$<?= number_format($o['subtotal'] - $o['discount_amount'] + $o['delivery_fee'], 2) ?></span></div>
                <?php if ($o['discount_amount'] > 0): ?>
                <div class="detail-row"><span class="detail-row-label">Coupon (<?= htmlspecialchars($o['coupon_code']) ?>)</span><span class="detail-row-value">−$<?= number_format($o['discount_amount'], 2) ?></span></div>
                <?php endif; ?>
                <div class="detail-row"><span class="detail-row-label">Delivery (non-refundable)</span><span class="detail-row-value">−$<?= number_format($o['delivery_fee'], 2) ?></span></div>
                <div class="detail-row"><span class="detail-row-label" style="font-weight:700;">Refund to buyer</span><span class="detail-row-value" style="font-weight:700;">$<?= number_format($o['subtotal'] - $o['discount_amount'], 2) ?></span></div>
            </div>
        </div>

        <!-- Right column -->
        <div>
            <?php if ($o['refund_reason']): ?>
            <div class="detail-card">
                <div class="detail-card-title">Buyer's reason</div>
                <p style="font-size:0.875rem;color:#374151;font-style:italic;margin:0;">"<?= htmlspecialchars($o['refund_reason']) ?>"</p>
            </div>
            <?php endif; ?>

            <div class="detail-card">
                <div class="detail-card-title">Status</div>
                <?php $refundStatus = $refundState; require __DIR__ . '/../refund-status/refund-status.php'; ?>
            </div>

            <div class="detail-card">
                <div class="detail-card-title">Actions</div>
                <?php if ($refundState === 'refund_requested'): ?>
                <p style="font-size:0.875rem;color:#6b7280;margin:0 0 0.75rem;">Buyer will return item via Grab at their own cost. Approve to proceed, or reject if request is invalid.</p>
                <div class="popup-actions">
                    <form method="POST" action="/admin/refund-action.php">
                        <?= csrf_input() ?>
                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                        <button type="submit" name="action" value="approve" class="btn-approve">Approve return</button>
                    </form>
                    <form method="POST" action="/admin/refund-action.php">
                        <?= csrf_input() ?>
                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                        <button type="submit" name="action" value="reject" class="btn-reject">Reject request</button>
                    </form>
                </div>

                <?php elseif ($refundState === 'return_approved'): ?>
                <p style="font-size:0.875rem;color:#6b7280;margin:0;">Waiting for buyer to pack and dispatch the return via Grab.</p>

                <?php elseif ($refundState === 'return_dispatched'): ?>
                <?php if ($o['return_tracking_url']): ?>
                <div class="detail-row" style="margin-bottom:0.5rem;"><span class="detail-row-label">Return tracking</span><span class="detail-row-value"><a href="<?= htmlspecialchars($o['return_tracking_url']) ?>" target="_blank" rel="noopener">Track via Grab</a></span></div>
                <?php endif; ?>
                <p style="font-size:0.875rem;color:#6b7280;margin:0;">Buyer has dispatched the return. Waiting for vendor to confirm receipt.</p>

                <?php elseif ($refundState === 'return_received'): ?>
                <p style="font-size:0.875rem;color:#6b7280;margin:0 0 0.75rem;">Vendor confirmed receipt. Send <strong>$<?= number_format($o['subtotal'] - $o['discount_amount'], 2) ?></strong> to the buyer via ABA, then mark as refunded.</p>
                <div class="detail-row" style="margin-bottom:0.75rem;"><span class="detail-row-label">Buyer email</span><span class="detail-row-value"><?= htmlspecialchars($o['buyer_email']) ?></span></div>
                <?php if ($o['vendor_aba_qr']): ?>
                <p style="font-size:0.8rem;color:#6b7280;margin:0 0 0.4rem;">Buyer's refund — scan to pay</p>
                <img src="/uploads/<?= htmlspecialchars($o['vendor_aba_qr']) ?>" alt="Vendor ABA QR" style="width:160px;height:160px;object-fit:contain;border:1px solid #e5e7eb;border-radius:6px;display:block;margin-bottom:1rem;">
                <?php else: ?>
                <p style="font-size:0.875rem;color:#ef4444;margin-bottom:1rem;">Vendor has not uploaded an ABA QR code.</p>
                <?php endif; ?>
                <div class="popup-actions">
                    <form method="POST" action="/admin/refund-action.php">
                        <?= csrf_input() ?>
                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                        <button type="submit" name="action" value="complete" class="btn-approve">Mark refunded</button>
                    </form>
                </div>

                <?php elseif ($refundState === 'refunded'): ?>
                <p style="font-size:0.875rem;color:#1e7e34;margin:0;">Refund has been completed.</p>

                <?php elseif ($refundState === 'refund_rejected'): ?>
                <?php
                // The order is back at 'delivered' and the vendor is due their payout.
                // Until the payout actually goes out, the rejection is still reversible —
                // a buyer who makes a good case in the support thread shouldn't need a
                // workaround. Once paid_out_at is set the money has moved and it isn't.
                $windowEnds  = $o['delivered_at'] ? strtotime($o['delivered_at']) + PAYOUT_WINDOW_SECONDS : null;
                $payoutHeld  = !$o['refund_closed_at'] && $windowEnds && time() < $windowEnds;
                ?>
                <p style="font-size:0.875rem;color:#6b7280;margin:0 0 0.75rem;">
                    Refund request was rejected<?= $o['refund_rejected_at'] ? ' on ' . date('M j, g:ia', strtotime($o['refund_rejected_at'])) : '' ?>.
                    The order is back to delivered and the vendor is due their payout.
                </p>
                <?php if ($o['paid_out_at']): ?>
                <p style="font-size:0.875rem;color:#1e7e34;margin:0;">Payout was released on <?= date('M j, g:ia', strtotime($o['paid_out_at'])) ?> — this rejection can no longer be overturned.</p>
                <?php else: ?>
                <?php if ($o['refund_closed_at']): ?>
                <p style="font-size:0.875rem;color:#1e7e34;margin:0 0 0.75rem;">Rejection confirmed on <?= date('M j, g:ia', strtotime($o['refund_closed_at'])) ?> — the payout is cleared to go out.</p>
                <?php elseif ($payoutHeld): ?>
                <p style="font-size:0.875rem;color:#6b7280;margin:0 0 0.75rem;">The payout is still held until <?= date('M j, g:ia', $windowEnds) ?>. The buyer can't file again, so that wait is only there in case you change your mind — confirm below to release it now.</p>
                <?php endif; ?>
                <div class="popup-actions">
                    <form method="POST" action="/admin/refund-action.php">
                        <?= csrf_input() ?>
                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                        <button type="submit" name="action" value="overturn" class="btn-approve">Overturn — grant refund</button>
                    </form>
                    <?php if (!$o['refund_closed_at']): ?>
                    <form method="POST" action="/admin/refund-action.php">
                        <?= csrf_input() ?>
                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                        <button type="submit" name="action" value="confirm" class="btn-reject">Confirm rejection — release payout</button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
