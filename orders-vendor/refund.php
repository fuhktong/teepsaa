<?php
session_start([
    'gc_maxlifetime'  => 28800,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/url.php';
require __DIR__ . '/../config/refund-evidence.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header('Location: /login-vendor/');
    exit;
}

// Translations loaded early — status badge label is built before the header.
$lang = current_lang();
$t = require __DIR__ . '/../lang/' . (in_array($lang, ['en', 'km']) ? $lang : 'en') . '.php';

$userId   = $_SESSION['user_id'];
$publicId = $_GET['id'] ?? '';
if ($publicId === '') {
    header('Location: /orders-vendor/?tab=refunds');
    exit;
}

$refundStatuses = ['refund_requested','return_approved','return_dispatched','return_received','refunded','refund_rejected'];
$statusIn = implode(',', array_map([$pdo, 'quote'], $refundStatuses));

$stmt = $pdo->prepare("
    SELECT o.id, o.subtotal, o.delivery_fee, o.status, o.created_at,
           o.refund_reason, o.refund_rejected_at, o.return_tracking_url, o.coupon_code, o.discount_amount,
           o.return_disputed_at, o.refund_paid, o.refund_requested_at, o.vendor_reply, o.vendor_reply_at,
           b.name AS business_name,
           u.name AS buyer_name, u.email AS buyer_email
    FROM orders o
    JOIN businesses b ON b.id = o.business_id
    JOIN buyers u ON u.id = o.buyer_user_id
    WHERE o.public_id = ? AND b.user_id = ? AND b.deleted_at IS NULL
      AND (o.status IN ($statusIn) OR o.refund_rejected_at IS NOT NULL)
");
$stmt->execute([$publicId, $userId]);
$o = $stmt->fetch();

// A link typed in by the other party. Anything that isn't a real http(s)
// URL becomes null here, so the surrounding `if` drops the link entirely
// rather than rendering a javascript: href (config/url.php).
if (isset($o['return_tracking_url'])) $o['return_tracking_url'] = safe_external_url($o['return_tracking_url']);

if (!$o) {
    header('Location: /orders-vendor/?tab=refunds');
    exit;
}
$orderId = (int)$o['id'];

$stmt = $pdo->prepare('SELECT product_name, product_name_km, variant_label, variant_label_km, quantity, price_at_purchase FROM order_items WHERE order_id = ? ORDER BY id');
$stmt->execute([$orderId]);
$items = $stmt->fetchAll();

$bizStmt = $pdo->prepare('SELECT id FROM businesses WHERE user_id = ? AND approved = 1');
$bizStmt->execute([$userId]);
$bizIds = array_column($bizStmt->fetchAll(), 'id');
$vendorRefundCount = 0;
if (!empty($bizIds)) {
    $ph = implode(',', array_fill(0, count($bizIds), '?'));
    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM orders o WHERE o.business_id IN ($ph) AND " . VENDOR_REFUND_ACTION_SQL);
    $cntStmt->execute(array_values($bizIds));
    $vendorRefundCount = (int)$cntStmt->fetchColumn();
}

$oid = date('ymd', strtotime($o['created_at'])) . '-' . str_pad($o['id'], 4, '0', STR_PAD_LEFT);

$statusClasses = [
    'refund_requested'  => 'badge-red',
    'return_approved'   => 'badge-yellow',
    'return_dispatched' => 'badge-yellow',
    'return_received'   => 'badge-blue',
    'return_disputed'   => 'badge-yellow',
    'refunded'          => 'badge-green',
    'refund_rejected'   => 'badge-grey',
];
// A denied refund puts the order back to 'delivered' and a disputed return is
// held at 'return_received', so the refund state comes from refund_state().
$refundState = refund_state($o);
$statusClass = $statusClasses[$refundState] ?? 'badge-grey';
$statusLabel = $t['order_badge_' . $refundState] ?? ucwords(str_replace('_', ' ', $refundState));

// teepsaa holds the buyer's money until the refund window closes, so a refund
// is paid out of that, never billed to the vendor. The label says so — and
// says it in the right tense, because 'refunded' is a lie before it happens
// and after it is refused.
$refundTotalLabels = [
    'refunded'          => $t['vorder_refunded_by'],
    'return_approved'   => $t['vorder_will_refund'],
    'return_dispatched' => $t['vorder_will_refund'],
    'return_received'   => $t['vorder_will_refund'],
    'return_disputed'   => $t['vorder_may_refund'],
    'refund_requested'  => $t['vorder_may_refund'],
    'refund_rejected'   => $t['vorder_not_refunded'],
];
$refundTotalLabel = $refundTotalLabels[$refundState] ?? $t['vorder_may_refund'];
?>
<!DOCTYPE html>
<html lang="<?= current_lang() ?>">
<?php
$headTitle = $oid . ' — Refund — teepsaa';
$headCss   = ['/order-status/order-status.css', '/refund-status/refund-status.css', '/popup/popup.css', '/analytics/analytics.css', '/products/products.css'];
$headSeo   = false;
require __DIR__ . '/../head/head.php';
?>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>
<?php require __DIR__ . '/../vendor-subnav/vendor-subnav.php'; ?>

<main>
    <nav class="products-subnav pill-tabs">
        <a href="/orders-vendor/"><?= $t['vendor_orders'] ?></a>
        <a href="/orders-vendor/?tab=history"><?= $t['vendor_orders_history'] ?></a>
        <a href="/orders-vendor/?tab=refunds" class="active"><?= $t['vendor_refunds'] ?><?php if ($vendorRefundCount > 0): ?> <span class="admin-tab-badge"><?= $vendorRefundCount ?></span><?php endif; ?></a>
    </nav>

    <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1.5rem;flex-wrap:wrap;">
        <h1 style="margin-bottom:0;"><?= $oid ?> — <?= $t['vorder_refund_word'] ?></h1>
        <span class="order-badge <?= $statusClass ?>"><?= $statusLabel ?></span>
    </div>

    <?php if (!empty($_SESSION['flash_error'])): ?>
    <p class="form-error" style="margin-bottom:1rem;"><?= htmlspecialchars($_SESSION['flash_error']) ?></p>
    <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <div class="popup-section">
        <div class="popup-section-label"><?= $t['order_info'] ?></div>
        <div class="popup-row"><span class="popup-row-label"><?= $t['order_date'] ?></span><span class="popup-row-value"><?= fmt_date('M j, Y g:ia', strtotime($o['created_at'])) ?></span></div>
        <div class="popup-row"><span class="popup-row-label"><?= $t['vorder_customer'] ?></span><span class="popup-row-value"><?= htmlspecialchars($o['buyer_name'] ?: $o['buyer_email']) ?></span></div>
        <div class="popup-row"><span class="popup-row-label"><?= $t['order_business'] ?></span><span class="popup-row-value"><?= htmlspecialchars($o['business_name']) ?></span></div>
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
        <div class="popup-subtotal"><span><?= $t['vorder_delivery_nonrefund'] ?></span><span>$<?= number_format($o['delivery_fee'], 2) ?></span></div>
        <?php endif; ?>
        <div class="popup-total"><span><?= $refundTotalLabel ?></span><span>$<?= number_format(refund_figure($o), 2) ?></span></div>
    </div>
    <?php endif; ?>

    <?php if ($o['refund_reason']): ?>
    <div class="popup-section">
        <div class="popup-section-label"><?= $t['vorder_buyer_reason'] ?></div>
        <p style="font-size:0.875rem;color:#374151;font-style:italic;margin:0;">"<?= htmlspecialchars($o['refund_reason']) ?>"</p>
        <?php $buyerPhotos = refund_photos_of($pdo, $orderId)['buyer_request'] ?? []; ?>
        <?php if ($buyerPhotos): ?>
        <div class="popup-section-label" style="margin-top:0.75rem;"><?= $t['vorder_buyer_photos'] ?></div>
        <div style="display:flex;flex-wrap:wrap;gap:0.5rem;">
            <?php foreach ($buyerPhotos as $f): $ph = htmlspecialchars(order_photo_url($f)); ?>
            <a href="<?= $ph ?>" target="_blank" rel="noopener"><img class="dispatch-photo" src="<?= $ph ?>" alt="<?= htmlspecialchars($t['vorder_buyer_photos']) ?>" style="max-width:120px;"></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php
    // The vendor's one reply to a dispute (config/refund-evidence.php).
    $replyUntil  = refund_reply_until($o);
    $replyPhotos = refund_photos_of($pdo, $orderId)['vendor_reply'] ?? [];
    $replyDraft  = $_SESSION['vendor_reply_draft'] ?? '';
    unset($_SESSION['vendor_reply_draft']);
    ?>
    <?php if ($o['vendor_reply_at']): ?>
    <div class="popup-section">
        <div class="popup-section-label"><?= $t['vorder_reply_title'] ?></div>
        <p style="font-size:0.875rem;color:#374151;white-space:pre-line;margin:0 0 0.5rem;"><?= htmlspecialchars($o['vendor_reply']) ?></p>
        <?php if ($replyPhotos): ?>
        <div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-bottom:0.5rem;">
            <?php foreach ($replyPhotos as $f): $ph = htmlspecialchars(order_photo_url($f)); ?>
            <a href="<?= $ph ?>" target="_blank" rel="noopener"><img class="dispatch-photo" src="<?= $ph ?>" alt="<?= htmlspecialchars($t['order_your_photos']) ?>" style="max-width:120px;"></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if ($o['status'] === 'refund_requested'): ?>
        <p style="font-size:0.875rem;color:#6b7280;margin:0;"><?= sprintf($t['vorder_reply_sent'], fmt_date('M j, g:ia', strtotime($o['vendor_reply_at']))) ?></p>
        <?php endif; ?>
    </div>
    <?php elseif (refund_reply_open($o)): ?>
    <div class="popup-section">
        <div class="popup-section-label"><?= $t['vorder_reply_title'] ?></div>
        <p style="font-size:0.875rem;color:#6b7280;margin:0 0 0.5rem;"><?= sprintf($t['vorder_reply_hint'], fmt_date('M j, g:ia', $replyUntil), REFUND_PHOTOS_MAX) ?></p>
        <form method="POST" action="/products/refund-reply.php" enctype="multipart/form-data">
            <?= csrf_input() ?>
            <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
            <textarea name="reply" rows="4" required maxlength="<?= REFUND_REPLY_MAX_LENGTH ?>" placeholder="<?= htmlspecialchars($t['vorder_reply_placeholder']) ?>" style="width:100%;"><?= htmlspecialchars($replyDraft) ?></textarea>
            <input type="file" name="photos[]" accept="image/jpeg,image/png" multiple data-max="<?= REFUND_PHOTOS_MAX ?>" class="reply-photos" style="margin-top:0.5rem;">
            <button type="submit" class="btn-confirm" style="margin-top:0.75rem;"><?= $t['vorder_reply_send'] ?></button>
        </form>
        <script>
        (function () {
            var input = document.querySelector('.reply-photos');
            input.addEventListener('change', function () {
                input.setCustomValidity(input.files.length > +input.dataset.max ? <?= json_encode(sprintf($t['order_refund_err_too_many_photos'], REFUND_PHOTOS_MAX)) ?> : '');
            });
        })();
        </script>
    </div>
    <?php elseif ($replyUntil !== null): ?>
    <div class="popup-section">
        <div class="popup-section-label"><?= $t['vorder_reply_title'] ?></div>
        <p style="font-size:0.875rem;color:#6b7280;margin:0;"><?= $t['vorder_reply_closed'] ?></p>
    </div>
    <?php endif; ?>

    <div class="popup-section">
        <div class="popup-section-label"><?= $t['vorder_refund_status'] ?></div>
        <?php $refundStatus = $refundState; require __DIR__ . '/../refund-status/refund-status.php'; ?>
    </div>

    <?php if ($o['status'] === 'return_dispatched'): ?>
    <hr class="popup-divider">
    <div class="popup-section-label" style="margin-bottom:0.5rem;"><?= $t['vorder_return_delivery'] ?></div>
    <?php if ($o['return_tracking_url']): ?>
    <div class="popup-row" style="margin-bottom:0.75rem;"><span class="popup-row-label"><?= $t['vorder_grab_link'] ?></span><span class="popup-row-value"><a href="<?= htmlspecialchars($o['return_tracking_url']) ?>" target="_blank" rel="noopener"><?= $t['vorder_track_return'] ?></a></span></div>
    <?php endif; ?>
    <p style="font-size:0.875rem;color:#6b7280;margin:0 0 0.75rem;"><?= $t['vorder_received_hint'] ?></p>
    <form method="POST" action="/products/return-received.php">
        <?= csrf_input() ?>
        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
        <button type="submit" class="btn-confirm"><?= $t['vorder_confirm_received'] ?></button>
    </form>

    <button type="button" class="btn-secondary return-problem-open" style="margin-top:0.75rem;"><?= $t['vorder_return_problem'] ?></button>
    <form method="POST" action="/products/return-disputed.php" enctype="multipart/form-data" class="return-problem-form" hidden style="margin-top:0.75rem;">
        <?= csrf_input() ?>
        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
        <p style="font-size:0.875rem;color:#6b7280;margin:0 0 0.5rem;"><?= sprintf($t['vorder_return_problem_hint'], REFUND_PHOTOS_MAX) ?></p>
        <input type="file" name="photos[]" accept="image/jpeg,image/png" multiple required data-max="<?= REFUND_PHOTOS_MAX ?>">
        <button type="submit" class="btn-confirm" style="margin-top:0.75rem;"><?= $t['vorder_return_problem_send'] ?></button>
    </form>
    <script>
    (function () {
        var open = document.querySelector('.return-problem-open');
        var form = document.querySelector('.return-problem-form');
        var input = form.querySelector('input[type=file]');
        open.addEventListener('click', function () { form.hidden = false; open.hidden = true; });
        input.addEventListener('change', function () {
            var max = +input.dataset.max;
            input.setCustomValidity(input.files.length > max ? <?= json_encode(sprintf($t['order_refund_err_too_many_photos'], REFUND_PHOTOS_MAX)) ?> : '');
        });
    })();
    </script>
    <?php endif; ?>

    <?php $vendorPhotos = refund_photos_of($pdo, $orderId)['vendor_return'] ?? []; ?>
    <?php if ($o['return_disputed_at']): ?>
    <hr class="popup-divider">
    <div class="popup-section-label" style="margin-bottom:0.5rem;"><?= $t['vorder_return_problem'] ?></div>
    <?php if ($refundState === 'return_disputed'): ?>
    <p style="font-size:0.875rem;color:#6b7280;margin:0 0 0.75rem;"><?= $t['vorder_return_disputed'] ?></p>
    <?php elseif ($refundState === 'refund_rejected'): ?>
    <p style="font-size:0.875rem;color:#6b7280;margin:0 0 0.75rem;"><?= $t['vorder_return_post_back'] ?></p>
    <?php endif; ?>
    <?php if ($vendorPhotos): ?>
    <div class="popup-section-label" style="margin-top:0.75rem;"><?= $t['order_your_photos'] ?></div>
    <div style="display:flex;flex-wrap:wrap;gap:0.5rem;">
        <?php foreach ($vendorPhotos as $f): $ph = htmlspecialchars(order_photo_url($f)); ?>
        <a href="<?= $ph ?>" target="_blank" rel="noopener"><img class="dispatch-photo" src="<?= $ph ?>" alt="<?= htmlspecialchars($t['order_your_photos']) ?>" style="max-width:120px;"></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
