<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/delivery-calc.php';
require __DIR__ . '/../config/coupon.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'buyer') {
    header('Location: /login-buyer/');
    exit;
}

$success  = $_SESSION['checkout_success'] ?? '';
$cartMsg  = $_SESSION['cart_success'] ?? '';
$cartErr  = $_SESSION['cart_error']   ?? '';
unset($_SESSION['checkout_success'], $_SESSION['cart_success'], $_SESSION['cart_error']);

$userId = $_SESSION['user_id'];

// Fetch buyer address — required for checkout
$stmt = $pdo->prepare('SELECT lat, lng, house_number, address, address_notes, khan, sangkat FROM buyers WHERE id = ?');
$stmt->execute([$userId]);
$buyer    = $stmt->fetch();
$buyerLat = ($buyer['lat'] !== null && $buyer['lat'] !== '') ? (float)$buyer['lat'] : null;
$buyerLng = ($buyer['lng'] !== null && $buyer['lng'] !== '') ? (float)$buyer['lng'] : null;

$savedAddrStmt = $pdo->prepare('SELECT * FROM buyer_addresses WHERE buyer_user_id = ? ORDER BY is_default DESC, created_at ASC');
$savedAddrStmt->execute([$userId]);
$savedAddresses = $savedAddrStmt->fetchAll();

if (!$success && ($buyerLat === null || $buyerLng === null)) {
    $_SESSION['cart_error'] = 'Please set your delivery address before checking out.';
    header('Location: /dashboard-buyer/settings/?tab=address');
    exit;
}

$stmt = $pdo->prepare('
    SELECT ci.id AS cart_item_id, ci.quantity, ci.variant_id,
           p.id AS product_id, p.name AS product_name, p.name_km AS product_name_km, p.price, p.stock, p.delivery_method,
           COALESCE(pv.price_override, IF(p.sale_ends_at IS NOT NULL AND p.sale_ends_at > NOW(), p.sale_price, NULL), p.price) AS effective_price,
           pv.label AS variant_label, pv.label_km AS variant_label_km,
           b.id AS business_id, b.name AS business_name, b.name_km AS business_name_km, b.lat AS biz_lat, b.lng AS biz_lng
    FROM cart_items ci
    JOIN products p ON p.id = ci.product_id AND p.active = 1
    JOIN businesses b ON b.id = p.business_id AND b.approved = 1
    LEFT JOIN product_variants pv ON pv.id = ci.variant_id
    WHERE ci.buyer_user_id = ?
    ORDER BY b.name, p.name
');
$stmt->execute([$userId]);
$items = $stmt->fetchAll();

$cfg      = require __DIR__ . '/../config/delivery.php';
$grouped  = [];
$subtotal = 0.0;

foreach ($items as $item) {
    $bid = $item['business_id'];
    if (!isset($grouped[$bid])) {
        $grouped[$bid] = [
            'name'     => pick_lang($item['business_name'], $item['business_name_km'] ?? null),
            'biz_lat'  => ($item['biz_lat'] !== null) ? (float)$item['biz_lat'] : null,
            'biz_lng'  => ($item['biz_lng'] !== null) ? (float)$item['biz_lng'] : null,
            'items'        => [],
            'subtotal'     => 0.0,
            'vehicle_type' => 'bike',
            'delivery'     => null,
        ];
    }
    $grouped[$bid]['items'][]   = $item;
    $grouped[$bid]['subtotal'] += $item['effective_price'] * $item['quantity'];
    if ($item['delivery_method'] === 'tuktuk') $grouped[$bid]['vehicle_type'] = 'tuktuk';
    $subtotal += $item['effective_price'] * $item['quantity'];
}

$outOfRange = [];
foreach ($grouped as $bid => &$group) {
    if ($group['biz_lat'] === null || $group['biz_lng'] === null || $buyerLat === null || $buyerLng === null) {
        $group['delivery'] = ['state' => 'no_pin'];
    } else {
        $dist = haversine_km($buyerLat, $buyerLng, $group['biz_lat'], $group['biz_lng']);
        if ($dist > $cfg['max_distance']) {
            $group['delivery'] = ['state' => 'out_of_range', 'distance_km' => round($dist, 1)];
            $outOfRange[] = $group['name'];
        } else {
            $d = calculate_delivery($dist, $group['vehicle_type']);
            $group['delivery'] = array_merge($d, ['state' => 'ok']);
        }
    }
}
unset($group);

$subtotalsByBusiness = array_combine(array_keys($grouped), array_column($grouped, 'subtotal'));

$couponCode       = $_SESSION['checkout_coupon_code'] ?? '';
$discount         = 0.0;
$couponMsg        = '';
$couponBusinessId = null;
if ($couponCode !== '') {
    $couponResult = validate_coupon($pdo, $couponCode, $subtotalsByBusiness, $userId);
    if ($couponResult['valid']) {
        $discount         = $couponResult['discount'];
        $couponBusinessId = $couponResult['business_id'];
    } else {
        // Cart changed since the code was applied (e.g. an item was removed) — drop it silently.
        unset($_SESSION['checkout_coupon_code']);
        $couponCode = '';
        $couponMsg  = $couponResult['message'];
    }
}

// Show the coupon input only when a code this cart could actually use exists —
// sitewide, or owned by one of the shops in the cart
$couponAvailable = false;
if (!empty($grouped)) {
    $cavPh   = implode(',', array_fill(0, count($grouped), '?'));
    $cavStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM coupons
         WHERE active = 1
           AND (business_id IS NULL OR business_id IN ($cavPh))
           AND (starts_at IS NULL OR starts_at <= NOW())
           AND (expires_at IS NULL OR expires_at >= NOW())
           AND (max_uses IS NULL OR used_count < max_uses)"
    );
    $cavStmt->execute(array_keys($grouped));
    $couponAvailable = (int)$cavStmt->fetchColumn() > 0;
}

$grandTotal  = max(0, $subtotal - $discount);
$canCheckout = empty($outOfRange) && !empty($grouped);
$abaQr = file_exists(__DIR__ . '/../uploads/aba-qr.png');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout — teepsaa</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/checkout/checkout.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>

<?php if ($success): ?>
    <div class="checkout-success">
        <h1><?= $t['checkout_order_placed'] ?></h1>
        <p><?= htmlspecialchars($success) ?></p>
        <a href="/dashboard-buyer/" class="btn-primary"><?= $t['checkout_view_orders'] ?></a>
    </div>

<?php elseif (empty($grouped)): ?>
    <p class="checkout-empty"><?= $t['checkout_empty'] ?> <a href="/search/"><?= $t['cart_browse'] ?></a></p>

<?php else: ?>
    <?php if ($cartMsg): ?>
    <div class="checkout-coupon-flash checkout-coupon-flash--ok"><?= htmlspecialchars($cartMsg) ?></div>
    <?php endif; ?>
    <?php if ($cartErr || $couponMsg): ?>
    <div class="checkout-coupon-flash checkout-coupon-flash--error"><?= htmlspecialchars($cartErr ?: $couponMsg) ?></div>
    <?php endif; ?>

    <?php if (!empty($outOfRange)): ?>
    <div class="checkout-alert">
        <?= $t['checkout_oos_pre'] ?> <strong><?= htmlspecialchars(implode(', ', $outOfRange)) ?></strong> <?= $t['checkout_oos_post'] ?>
    </div>
    <?php endif; ?>

    <div class="checkout-layout">

        <div class="checkout-summary">
            <h1><?= $t['checkout_order_summary'] ?></h1>
            <?php foreach ($grouped as $group): ?>
            <div class="checkout-vendor">
                <h2><?= htmlspecialchars($group['name']) ?></h2>
                <?php foreach ($group['items'] as $item): ?>
                <div class="checkout-line">
                    <span>
                        <?= htmlspecialchars($lang === 'km' && !empty($item['product_name_km']) ? $item['product_name_km'] : $item['product_name']) ?>
                        <?php if ($item['variant_label']): ?>
                            <span style="color:#9ca3af;font-size:0.85em">(<?= htmlspecialchars(pick_lang($item['variant_label'], $item['variant_label_km'] ?? null)) ?>)</span>
                        <?php endif; ?>
                        &times; <?= (int)$item['quantity'] ?>
                    </span>
                    <span><?= format_price($item['effective_price'] * $item['quantity']) ?></span>
                </div>
                <?php endforeach; ?>
                <div class="checkout-line checkout-line--sub">
                    <span><?= $t['checkout_subtotal'] ?></span>
                    <span><?= format_price($group['subtotal'] ?? 0) ?></span>
                </div>
                <?php $d = $group['delivery']; ?>
                <?php if ($d['state'] === 'ok'): ?>
                <div class="checkout-line checkout-line--delivery">
                    <span><?= $t['checkout_est'] ?> <?= $d['vehicle_type'] === 'tuktuk' ? 'Grab Tuk-Tuk' : 'Grab Bike' ?></span>
                    <span class="checkout-delivery-est">~<?= format_price($d['fee']) ?> <span class="checkout-delivery-note"><?= $t['checkout_delivery_cod'] ?></span></span>
                </div>
                <?php elseif ($d['state'] === 'out_of_range'): ?>
                <div class="checkout-line checkout-line--error">
                    <span><?= $t['checkout_delivery_range'] ?> (<?= $d['distance_km'] ?>km)</span>
                    <span>—</span>
                </div>
                <?php elseif ($d['state'] === 'no_pin'): ?>
                <div class="checkout-line checkout-line--muted">
                    <span><?= $t['checkout_delivery'] ?></span>
                    <span class="checkout-delivery-note"><?= $t['checkout_set_pin'] ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <?php if ($couponCode !== '' || $couponAvailable): ?>
            <div class="checkout-coupon-row">
                <?php if ($couponCode !== ''): ?>
                <div class="checkout-line checkout-line--sub">
                    <span><?= $t['checkout_coupon_applied'] ?> <strong><?= htmlspecialchars($couponCode) ?></strong><?php if ($couponBusinessId !== null && isset($grouped[$couponBusinessId])): ?> <span style="color:#9ca3af;font-size:0.85em">(<?= htmlspecialchars($grouped[$couponBusinessId]['name']) ?> only)</span><?php endif; ?></span>
                    <span>&minus;<?= format_price($discount) ?></span>
                </div>
                <form method="POST" action="/checkout/apply-coupon.php" class="checkout-coupon-form">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="remove">
                    <button type="submit" class="checkout-coupon-remove"><?= $t['checkout_coupon_remove'] ?></button>
                </form>
                <?php else: ?>
                <form method="POST" action="/checkout/apply-coupon.php" class="checkout-coupon-form">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="apply">
                    <input type="text" name="code" maxlength="32" placeholder="<?= htmlspecialchars($t['checkout_coupon_placeholder']) ?>" class="checkout-coupon-input" style="text-transform:uppercase">
                    <button type="submit" class="btn-addr-use"><?= $t['checkout_coupon_apply'] ?></button>
                </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="checkout-total-block">
                <?php if ($discount > 0): ?>
                <div class="checkout-line checkout-line--sub">
                    <span><?= $t['checkout_subtotal'] ?></span>
                    <span><?= format_price($subtotal) ?></span>
                </div>
                <?php endif; ?>
                <div class="checkout-total">
                    <span><?= $t['checkout_total'] ?></span>
                    <strong><?= format_price($grandTotal) ?></strong>
                </div>
                <p class="checkout-cod-note"><?= $t['checkout_cod_note1'] ?></p>
                <p class="checkout-cod-note checkout-cod-note--est"><?= $t['checkout_cod_note2'] ?></p>
            </div>
        </div>

        <div class="checkout-payment">
            <h2><?= $t['checkout_pay_aba'] ?></h2>
            <?php if ($canCheckout): ?>
            <p class="checkout-instructions"><?= sprintf($t['checkout_scan_instructions'], '<strong>$' . number_format($grandTotal, 2) . '</strong>') ?></p>
            <?php else: ?>
            <p class="checkout-instructions checkout-instructions--error"><?= $t['checkout_remove_range'] ?></p>
            <?php endif; ?>

            <?php if ($abaQr): ?>
                <img src="/uploads/aba-qr.png" alt="teepsaa ABA QR Code" class="aba-qr">
            <?php else: ?>
                <div class="aba-qr-placeholder"><?= $t['checkout_aba_coming_soon'] ?></div>
            <?php endif; ?>

            <?php
            // Final review before "I've paid": the address being delivered to,
            // its saved instructions, then the one-off note for this order
            $parts = array_filter([
                $buyer['house_number'],
                $buyer['address'],
                $buyer['sangkat'],
                $buyer['khan'],
            ]);
            $deliveringTo = $parts ? htmlspecialchars(implode(', ', $parts)) : $t['checkout_your_saved_address'];
            ?>
            <?php if (!empty($savedAddresses)): ?>
            <details class="checkout-addr-switcher">
                <summary class="checkout-addr-summary">
                    <span class="checkout-addr-label"><?= $t['checkout_delivering_to'] ?> <?= $deliveringTo ?></span>
                    <span class="checkout-addr-change"><?= $t['checkout_change'] ?></span>
                </summary>
                <div class="checkout-addr-list">
                    <?php foreach ($savedAddresses as $sa): ?>
                    <form method="POST" action="/checkout/set-address.php" class="checkout-addr-option">
                        <?= csrf_input() ?>
                        <input type="hidden" name="address_id" value="<?= (int)$sa['id'] ?>">
                        <div class="checkout-addr-option-text">
                            <?php if ($sa['label']): ?><strong><?= htmlspecialchars($sa['label']) ?></strong> — <?php endif; ?>
                            <?php
                            $saparts = array_filter([$sa['house_number'], $sa['address'], $sa['sangkat'], $sa['khan']]);
                            echo $saparts ? htmlspecialchars(implode(', ', $saparts)) : $t['checkout_saved_address'];
                            ?>
                            <?php if ($sa['is_default']): ?> <span class="checkout-addr-default-badge"><?= $t['checkout_default'] ?></span><?php endif; ?>
                        </div>
                        <button type="submit" class="btn-addr-use"><?= $t['checkout_use_address'] ?></button>
                    </form>
                    <?php endforeach; ?>
                </div>
            </details>
            <?php else: ?>
            <div class="checkout-addr-switcher">
                <div class="checkout-addr-summary checkout-addr-summary--static">
                    <span class="checkout-addr-label"><?= $t['checkout_delivering_to'] ?> <?= $deliveringTo ?></span>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($buyer['address_notes']): ?>
            <div class="checkout-addr-instructions">
                <span class="checkout-field-label"><?= $t['checkout_delivery_instructions'] ?></span>
                <?= htmlspecialchars($buyer['address_notes']) ?>
            </div>
            <?php endif; ?>

            <?php if ($canCheckout): ?>
            <form method="POST" action="/checkout/confirm.php">
                <?= csrf_input() ?>
                <label class="checkout-field-label" for="buyer_notes"><?= $t['checkout_order_instructions'] ?></label>
                <textarea id="buyer_notes" name="buyer_notes" class="checkout-notes" maxlength="500" rows="2" placeholder="<?= htmlspecialchars($t['checkout_notes_placeholder']) ?>"></textarea>
                <button type="submit" class="btn-paid"><?= $t['checkout_ive_paid'] ?></button>
            </form>
            <?php else: ?>
            <button type="button" class="btn-paid btn-paid--disabled" disabled><?= $t['checkout_ive_paid'] ?></button>
            <?php endif; ?>
            <p class="checkout-note"><?= $t['checkout_confirm_note'] ?></p>
        </div>

    </div>
<?php endif; ?>

</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

<?php if (!empty($grouped)): ?>
<div class="grab-notice-overlay" id="grab-notice-overlay">
    <div class="grab-notice-modal">
        <h2 class="grab-notice-title"><?= $t['checkout_about_grab'] ?></h2>
        <ul class="grab-notice-list">
            <li><?= $t['checkout_grab_modal_1'] ?></li>
            <li><?= $t['checkout_grab_modal_2'] ?></li>
        </ul>
        <button type="button" class="grab-notice-btn" id="grab-notice-btn"><?= $t['checkout_i_understand'] ?></button>
    </div>
</div>
<script>
(function () {
    var overlay = document.getElementById('grab-notice-overlay');
    var btn     = document.getElementById('grab-notice-btn');
    if (!overlay || !btn) return;
    overlay.classList.add('open');
    btn.addEventListener('click', function () {
        overlay.classList.remove('open');
    });
})();
</script>
<?php endif; ?>

</body>
</html>
