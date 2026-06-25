<?php
session_start();
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/delivery-calc.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login-buyer/');
    exit;
}

$success = $_SESSION['checkout_success'] ?? '';
unset($_SESSION['checkout_success']);

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
           p.id AS product_id, p.name AS product_name, p.price, p.stock, p.delivery_method,
           COALESCE(pv.price_override, IF(p.sale_ends_at IS NOT NULL AND p.sale_ends_at > NOW(), p.sale_price, NULL), p.price) AS effective_price,
           pv.label AS variant_label,
           b.id AS business_id, b.name AS business_name, b.lat AS biz_lat, b.lng AS biz_lng
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
            'name'     => $item['business_name'],
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

$grandTotal  = $subtotal;
$canCheckout = empty($outOfRange) && !empty($grouped);
$abaQr = file_exists(__DIR__ . '/../uploads/aba-qr.png');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout — teepsaa</title>
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
        <h1>Order placed!</h1>
        <p><?= htmlspecialchars($success) ?></p>
        <a href="/dashboard-buyer/" class="btn-primary">View my orders</a>
    </div>

<?php elseif (empty($grouped)): ?>
    <p class="checkout-empty">Your cart is empty. <a href="/search/">Browse businesses</a></p>

<?php else: ?>
    <?php if (!empty($outOfRange)): ?>
    <div class="checkout-alert">
        Delivery unavailable for: <strong><?= htmlspecialchars(implode(', ', $outOfRange)) ?></strong> — too far from your address. Remove those items to continue.
    </div>
    <?php endif; ?>

    <?php if (!empty($savedAddresses)): ?>
    <details class="checkout-addr-switcher">
        <summary class="checkout-addr-summary">
            <span class="checkout-addr-label">Delivering to:
                <?php
                $parts = array_filter([
                    $buyer['house_number'],
                    $buyer['address'],
                    $buyer['sangkat'],
                    $buyer['khan'],
                ]);
                echo $parts ? htmlspecialchars(implode(', ', $parts)) : 'your saved address';
                ?>
            </span>
            <span class="checkout-addr-change">Change</span>
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
                    echo $saparts ? htmlspecialchars(implode(', ', $saparts)) : 'Saved address';
                    ?>
                    <?php if ($sa['is_default']): ?> <span class="checkout-addr-default-badge">Default</span><?php endif; ?>
                </div>
                <button type="submit" class="btn-addr-use">Use this address</button>
            </form>
            <?php endforeach; ?>
        </div>
    </details>
    <?php endif; ?>

    <div class="checkout-layout">

        <div class="checkout-summary">
            <h1>Order summary</h1>
            <?php foreach ($grouped as $group): ?>
            <div class="checkout-vendor">
                <h2><?= htmlspecialchars($group['name']) ?></h2>
                <?php foreach ($group['items'] as $item): ?>
                <div class="checkout-line">
                    <span>
                        <?= htmlspecialchars($item['product_name']) ?>
                        <?php if ($item['variant_label']): ?>
                            <span style="color:#9ca3af;font-size:0.85em">(<?= htmlspecialchars($item['variant_label']) ?>)</span>
                        <?php endif; ?>
                        &times; <?= (int)$item['quantity'] ?>
                    </span>
                    <span><?= format_price($item['effective_price'] * $item['quantity']) ?></span>
                </div>
                <?php endforeach; ?>
                <div class="checkout-line checkout-line--sub">
                    <span>Subtotal</span>
                    <span><?= format_price($group['subtotal'] ?? 0) ?></span>
                </div>
                <?php $d = $group['delivery']; ?>
                <?php if ($d['state'] === 'ok'): ?>
                <div class="checkout-line checkout-line--delivery">
                    <span>Est. <?= $d['vehicle_type'] === 'tuktuk' ? 'Grab Tuk-Tuk' : 'Grab Bike' ?></span>
                    <span class="checkout-delivery-est">~<?= format_price($d['fee']) ?> <span class="checkout-delivery-note">COD</span></span>
                </div>
                <?php elseif ($d['state'] === 'out_of_range'): ?>
                <div class="checkout-line checkout-line--error">
                    <span>Delivery — out of range (<?= $d['distance_km'] ?>km)</span>
                    <span>—</span>
                </div>
                <?php elseif ($d['state'] === 'no_pin'): ?>
                <div class="checkout-line checkout-line--muted">
                    <span>Est. Grab delivery</span>
                    <span class="checkout-delivery-note">Set pin for estimate</span>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <div class="checkout-total-block">
                <div class="checkout-total">
                    <span>Total</span>
                    <strong><?= format_price($grandTotal) ?></strong>
                </div>
                <p class="checkout-cod-note">Grab delivery is paid <strong>directly to the driver on arrival</strong> — cash or QR code. This is separate from your teepsaa payment.</p>
                <p class="checkout-cod-note checkout-cod-note--est">Fee shown is an estimate based on your saved address and the business location.</p>
            </div>
        </div>

        <div class="checkout-payment">
            <h2>Pay with ABA</h2>
            <?php if ($canCheckout): ?>
            <p class="checkout-instructions">Scan the QR code below in your ABA app and pay exactly <strong>$<?= number_format($grandTotal, 2) ?></strong>. Then click "I've paid" to place your order.</p>
            <?php else: ?>
            <p class="checkout-instructions checkout-instructions--error">Remove out-of-range items from your cart before paying.</p>
            <?php endif; ?>

            <?php if ($abaQr): ?>
                <img src="/uploads/aba-qr.png" alt="teepsaa ABA QR Code" class="aba-qr">
            <?php else: ?>
                <div class="aba-qr-placeholder">ABA QR code coming soon</div>
            <?php endif; ?>

            <?php if ($canCheckout): ?>
            <form method="POST" action="/checkout/confirm.php">
                <?= csrf_input() ?>
                <textarea name="buyer_notes" class="checkout-notes" maxlength="500" rows="2" placeholder="Delivery instructions — gate code, call on arrival, etc. (optional)"></textarea>
                <button type="submit" class="btn-paid">I've paid — place my order</button>
            </form>
            <?php else: ?>
            <button type="button" class="btn-paid btn-paid--disabled" disabled>I've paid — place my order</button>
            <?php endif; ?>
            <p class="checkout-note">Your order will be confirmed once we verify your payment. This usually takes less than 1 hour.</p>
        </div>

    </div>
<?php endif; ?>

</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

<?php if (!empty($grouped)): ?>
<div class="grab-notice-overlay" id="grab-notice-overlay">
    <div class="grab-notice-modal">
        <h2 class="grab-notice-title">About your Grab delivery</h2>
        <ul class="grab-notice-list">
            <li>You pay the Grab driver <strong>directly on arrival</strong> — cash or QR code. This is <strong>separate</strong> from your teepsaa order payment.</li>
            <li>The delivery fee shown is an <strong>estimate</strong> calculated from your saved address and the business location. The actual Grab fee may vary.</li>
        </ul>
        <button type="button" class="grab-notice-btn" id="grab-notice-btn">I understand</button>
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
