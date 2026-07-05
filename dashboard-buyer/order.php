<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'buyer') {
    header('Location: /login-buyer/');
    exit;
}

// Translations loaded early — the status badge label is built before the header.
$lang = $_SESSION['lang'] ?? 'km';
$t = require __DIR__ . '/../lang/' . (in_array($lang, ['en', 'km']) ? $lang : 'en') . '.php';

$userId   = $_SESSION['user_id'];
$publicId = $_GET['id'] ?? '';
if ($publicId === '') {
    header('Location: /dashboard-buyer/');
    exit;
}

$stmt = $pdo->prepare('
    SELECT o.id, o.subtotal, o.delivery_fee, o.status, o.created_at, o.tracking_url,
           o.refund_reason, o.return_tracking_url, o.coupon_code, o.discount_amount,
           CASE WHEN o.delivered_at IS NULL OR TIMESTAMPDIFF(SECOND, o.delivered_at, NOW()) < ' . PAYOUT_WINDOW_SECONDS . ' THEN 1 ELSE 0 END AS refund_window_open,
           DATE_ADD(o.delivered_at, INTERVAL ' . PAYOUT_WINDOW_SECONDS . ' SECOND) AS refund_deadline,
           b.name AS business_name,
           b.house_number AS biz_house_number, b.address AS biz_address,
           b.address_notes AS biz_address_notes, b.khan AS biz_khan, b.sangkat AS biz_sangkat,
           v.name AS vendor_name, v.email AS vendor_email
    FROM orders o
    JOIN businesses b ON b.id = o.business_id
    JOIN vendors v ON v.id = b.user_id
    WHERE o.public_id = ? AND o.buyer_user_id = ?
');
$stmt->execute([$publicId, $userId]);
$o = $stmt->fetch();

if (!$o) {
    header('Location: /dashboard-buyer/');
    exit;
}
$orderId = (int)$o['id'];

$stmt = $pdo->prepare('SELECT id, product_name, product_name_km, variant_label, variant_label_km, quantity, price_at_purchase FROM order_items WHERE order_id = ? ORDER BY id');
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
$deadline   = $o['refund_deadline'] ? fmt_date('M j, g:ia', strtotime($o['refund_deadline'])) : null;

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
$statusLabel = $t['order_badge_' . $o['status']] ?? ucwords(str_replace('_', ' ', $o['status']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $oid ?> — My Orders — teepsaa</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
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
        <div class="popup-section-label"><?= $t['order_info'] ?></div>
        <div class="popup-row"><span class="popup-row-label"><?= $t['order_date'] ?></span><span class="popup-row-value"><?= fmt_date('M j, Y g:ia', strtotime($o['created_at'])) ?></span></div>
        <div class="popup-row"><span class="popup-row-label"><?= $t['order_business'] ?></span><span class="popup-row-value"><?= htmlspecialchars($o['business_name']) ?></span></div>
        <div class="popup-row"><span class="popup-row-label"><?= $t['order_vendor'] ?></span><span class="popup-row-value"><?= htmlspecialchars($o['vendor_name'] ?: $o['vendor_email']) ?></span></div>
        <?php if ($o['tracking_url'] && $o['status'] === 'dispatched'): ?>
        <div class="popup-row"><span class="popup-row-label"><?= $t['order_tracking'] ?></span><span class="popup-row-value"><a href="<?= htmlspecialchars($o['tracking_url']) ?>" target="_blank" rel="noopener"><?= $t['order_track_delivery'] ?></a></span></div>
        <?php endif; ?>
    </div>

    <?php if (!empty($items)): ?>
    <div class="popup-section">
        <div class="popup-section-label"><?= $t['order_items'] ?></div>
        <table class="popup-items">
            <thead><tr><th><?= $t['order_col_product'] ?></th><th><?= $t['order_col_qty'] ?></th><th><?= $t['order_col_price'] ?></th><th><?= $t['order_col_total'] ?></th></tr></thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <?= htmlspecialchars(pick_lang($item['product_name'], $item['product_name_km'] ?? null)) ?>
                        <?php if ($item['variant_label']): ?>
                            <br><small style="color:#9ca3af"><?= htmlspecialchars(pick_lang($item['variant_label'], $item['variant_label_km'] ?? null)) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= (int)$item['quantity'] ?></td>
                    <td>$<?= number_format($item['price_at_purchase'], 2) ?></td>
                    <td>$<?= number_format($item['price_at_purchase'] * $item['quantity'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="popup-subtotal"><span><?= $t['checkout_subtotal'] ?></span><span>$<?= number_format($o['subtotal'], 2) ?></span></div>
        <?php if ($o['discount_amount'] > 0): ?>
        <div class="popup-subtotal"><span><?= $t['checkout_coupon_applied'] ?> <?= htmlspecialchars($o['coupon_code']) ?></span><span>&minus;$<?= number_format($o['discount_amount'], 2) ?></span></div>
        <?php endif; ?>
        <?php if ($o['delivery_fee'] > 0): ?>
        <div class="popup-subtotal"><span><?= $t['order_delivery'] ?></span><span>$<?= number_format($o['delivery_fee'], 2) ?></span></div>
        <?php endif; ?>
        <div class="popup-total"><span><?= $t['checkout_total'] ?></span><span>$<?= number_format($o['subtotal'] - $o['discount_amount'] + $o['delivery_fee'], 2) ?></span></div>
    </div>
    <?php endif; ?>

    <?php if (in_array($o['status'], ['delivered', 'completed']) && !empty($items)): ?>
    <div class="popup-section">
        <div class="popup-section-label"><?= $t['product_reviews'] ?></div>
        <?php foreach ($items as $item): ?>
        <div class="review-item-row">
            <span class="review-item-name">
                <?= htmlspecialchars(pick_lang($item['product_name'], $item['product_name_km'] ?? null)) ?>
                <?php if ($item['variant_label']): ?>
                <span class="review-item-variant">(<?= htmlspecialchars(pick_lang($item['variant_label'], $item['variant_label_km'] ?? null)) ?>)</span>
                <?php endif; ?>
            </span>
            <?php if (in_array($item['id'], $reviewedItemIds)): ?>
            <span class="reviewed-badge"><?= $t['order_reviewed'] ?></span>
            <?php else: ?>
            <a href="/review/?item=<?= $item['id'] ?>" class="btn-leave-review"><?= $t['order_leave_review'] ?></a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="popup-section">
        <div class="popup-section-label"><?= $t['order_status_heading'] ?></div>
        <?php $orderStatus = $o['status']; require __DIR__ . '/../order-status/order-status.php'; ?>
    </div>

    <?php if ($o['status'] === 'pending'): ?>
    <p class="order-pending-note"><?= $t['orders_awaiting_payment'] ?></p>

    <?php elseif ($o['status'] === 'dispatched'): ?>
    <hr class="popup-divider">
    <div class="popup-actions">
        <form method="POST" action="/dashboard-buyer/confirm-delivery.php">
            <?= csrf_input() ?>
            <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
            <button type="submit" class="btn-confirm"><?= $t['order_confirm_delivery'] ?></button>
        </form>
        <?php if ($o['tracking_url']): ?>
        <a href="<?= htmlspecialchars($o['tracking_url']) ?>" target="_blank" rel="noopener" class="btn-secondary"><?= $t['order_track_delivery'] ?></a>
        <?php endif; ?>
    </div>

    <?php elseif ($o['status'] === 'delivered'): ?>
    <hr class="popup-divider">
    <details class="order-options">
        <summary class="order-options-toggle"><?= $t['order_options'] ?></summary>
        <div class="order-options-body">
            <div class="popup-section-label"><?= $t['order_issue'] ?></div>
            <?php if ($windowOpen): ?>
            <p style="font-size:0.875rem;color:#6b7280;margin:0.4rem 0 0.75rem;"><?= sprintf($t['order_refund_info'], '<strong>$' . number_format($o['subtotal'] - $o['discount_amount'], 2) . '</strong>') ?><?php if ($deadline): ?> <strong><?= sprintf($t['order_available_until'], htmlspecialchars($deadline)) ?></strong><?php endif; ?></p>
            <form method="POST" action="/dashboard-buyer/refund-request.php" class="refund-request-form">
                <?= csrf_input() ?>
                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                <select name="reason_preset" class="refund-reason-input refund-reason-select" required>
                    <option value=""><?= $t['order_select_reason'] ?></option>
                    <option value="Item not as described"><?= $t['order_reason_1'] ?></option>
                    <option value="Wrong item received"><?= $t['order_reason_2'] ?></option>
                    <option value="Item arrived damaged"><?= $t['order_reason_3'] ?></option>
                    <option value="Missing parts or accessories"><?= $t['order_reason_4'] ?></option>
                    <option value="Quality not as expected"><?= $t['order_reason_5'] ?></option>
                    <option value="other"><?= $t['order_reason_other'] ?></option>
                </select>
                <textarea name="reason_other" rows="2" class="refund-reason-input refund-reason-other" placeholder="<?= htmlspecialchars($t['order_describe_issue']) ?>" style="display:none;margin-top:0.4rem;"></textarea>
                <button type="submit" class="btn-refund-request"><?= $t['order_request_refund'] ?></button>
            </form>
            <?php else: ?>
            <p class="order-refund-window order-refund-window--closed" style="margin-top:0.5rem;"><?= $t['order_refund_closed'] ?></p>
            <?php endif; ?>
        </div>
    </details>

    <?php elseif ($o['status'] === 'return_approved'): ?>
    <hr class="popup-divider">
    <div class="popup-section-label"><?= $t['order_send_back'] ?></div>
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
    <p style="font-size:0.875rem;color:#6b7280;margin:0.4rem 0 0.25rem;"><?= $t['order_send_to'] ?> <strong><?= htmlspecialchars($retAddress) ?></strong><?php if ($o['biz_address_notes']): ?> — <?= htmlspecialchars($o['biz_address_notes']) ?><?php endif; ?></p>
    <?php endif; ?>
    <p style="font-size:0.875rem;color:#6b7280;margin:0.4rem 0 0.75rem;"><?= $t['order_return_instructions'] ?></p>
    <form method="POST" action="/dashboard-buyer/return-dispatch.php" class="refund-request-form">
        <?= csrf_input() ?>
        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
        <input type="url" name="return_tracking_url" required placeholder="<?= htmlspecialchars($t['order_return_url_placeholder']) ?>" class="refund-reason-input" style="resize:none;height:auto;padding:0.5rem 0.65rem;">
        <button type="submit" class="btn-confirm"><?= $t['order_mark_dispatched'] ?></button>
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
