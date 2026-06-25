<?php
session_start();
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/delivery-calc.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login-buyer/');
    exit;
}

$userId = $_SESSION['user_id'];

// Buyer address
$stmt = $pdo->prepare('SELECT lat, lng, address, khan FROM buyers WHERE id = ?');
$stmt->execute([$userId]);
$buyer      = $stmt->fetch();
$buyerLat   = ($buyer['lat'] !== null && $buyer['lat'] !== '') ? (float)$buyer['lat'] : null;
$buyerLng   = ($buyer['lng'] !== null && $buyer['lng'] !== '') ? (float)$buyer['lng'] : null;
$hasAddress = $buyerLat !== null && $buyerLng !== null;

if (empty($buyer['address']) && empty($buyer['khan'])) {
    $_SESSION['settings_success'] = 'Please set your delivery address before adding items to your cart.';
    header('Location: /dashboard-buyer/settings/?tab=address');
    exit;
}

$stmt = $pdo->prepare('
    SELECT ci.id AS cart_item_id, ci.quantity, ci.variant_id,
           p.id AS product_id, p.name AS product_name, p.price, p.stock, p.weight_g,
           COALESCE(pv.price_override, IF(p.sale_ends_at IS NOT NULL AND p.sale_ends_at > NOW(), p.sale_price, NULL), p.price) AS effective_price,
           COALESCE(pv.stock, p.stock) AS effective_stock,
           pv.label AS variant_label,
           pp.filename AS photo,
           b.id AS business_id, b.name AS business_name, b.lat AS biz_lat, b.lng AS biz_lng
    FROM cart_items ci
    JOIN products p ON p.id = ci.product_id AND p.active = 1
    JOIN businesses b ON b.id = p.business_id AND b.approved = 1
    LEFT JOIN product_variants pv ON pv.id = ci.variant_id
    LEFT JOIN product_photos pp ON pp.product_id = p.id AND pp.is_primary = 1
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
            'items'    => [],
            'subtotal' => 0.0,
            'weight_g' => 0,
            'delivery' => null,
        ];
    }
    $grouped[$bid]['items'][]   = $item;
    $grouped[$bid]['subtotal'] += $item['effective_price'] * $item['quantity'];
    $grouped[$bid]['weight_g'] += ($item['weight_g'] ?: 100) * $item['quantity'];
    $subtotal += $item['effective_price'] * $item['quantity'];
}

foreach ($grouped as $bid => &$group) {
    if (!$hasAddress) {
        $group['delivery'] = ['state' => 'no_address'];
    } elseif ($group['biz_lat'] === null || $group['biz_lng'] === null) {
        $group['delivery'] = ['state' => 'no_pin'];
    } else {
        $dist = haversine_km($buyerLat, $buyerLng, $group['biz_lat'], $group['biz_lng']);
        if ($dist > $cfg['max_distance']) {
            $group['delivery'] = ['state' => 'out_of_range', 'distance_km' => round($dist, 1)];
        } else {
            $d = calculate_delivery($dist, $group['weight_g']);
            $group['delivery'] = array_merge($d, ['state' => 'ok']);
        }
    }
}
unset($group);

$grandTotal = $subtotal;

$success = $_SESSION['cart_success'] ?? '';
$error   = $_SESSION['cart_error']   ?? '';
unset($_SESSION['cart_success'], $_SESSION['cart_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cart — teepsaa</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/cart/cart.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <h1 class="cart-title">Your cart</h1>

    <?php if ($error): ?>
        <p class="cart-msg cart-msg--error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <?php if ($success): ?>
        <p class="cart-msg cart-msg--success"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <?php if (empty($grouped)): ?>
        <p class="cart-empty">Your cart is empty. <a href="/search/">Browse businesses</a></p>
    <?php else: ?>

        <?php foreach ($grouped as $businessId => $group): ?>
        <div class="cart-vendor">
            <h2 class="cart-vendor-name"><?= htmlspecialchars($group['name']) ?></h2>
            <?php foreach ($group['items'] as $item): ?>
            <div class="cart-item">
                <?php if ($item['photo']): ?>
                    <img src="/uploads/<?= htmlspecialchars($item['photo']) ?>" alt="" class="cart-item-photo">
                <?php else: ?>
                    <div class="cart-item-photo cart-item-photo--empty"></div>
                <?php endif; ?>
                <div class="cart-item-info">
                    <strong><?= htmlspecialchars($item['product_name']) ?></strong>
                    <?php if ($item['variant_label']): ?>
                        <span class="cart-item-variant"><?= htmlspecialchars($item['variant_label']) ?></span>
                    <?php endif; ?>
                    <span class="cart-item-price"><?= format_price($item['effective_price']) ?> each</span>
                </div>
                <form method="POST" action="/cart/update.php" class="cart-item-controls">
                    <?= csrf_input() ?>
                    <input type="hidden" name="cart_item_id" value="<?= $item['cart_item_id'] ?>">
                    <input type="number" name="quantity" value="<?= (int)$item['quantity'] ?>"
                           min="0" max="<?= (int)$item['effective_stock'] ?>" class="cart-qty">
                    <button type="submit" name="action" value="update" class="btn-qty">Update</button>
                    <button type="submit" name="action" value="remove" class="btn-remove">Remove</button>
                </form>
                <span class="cart-item-subtotal"><?= format_price($item['effective_price'] * $item['quantity']) ?></span>
            </div>
            <?php endforeach; ?>

            <div class="cart-vendor-footer">
                <div class="cart-vendor-subtotal">
                    <span>Subtotal</span>
                    <span><?= format_price($group['subtotal']) ?></span>
                </div>
                <?php $d = $group['delivery']; ?>
                <?php if ($d['state'] === 'no_address'): ?>
                    <div class="cart-delivery cart-delivery--cta">
                        Grab delivery — <a href="/dashboard-buyer/settings/?tab=address">set your address to see estimate</a>
                    </div>
                <?php elseif ($d['state'] === 'no_pin'): ?>
                    <div class="cart-delivery cart-delivery--muted">
                        Grab delivery — <a href="/dashboard-buyer/settings/?tab=address">set your pin for estimate</a>
                    </div>
                <?php elseif ($d['state'] === 'out_of_range'): ?>
                    <div class="cart-delivery cart-delivery--error">
                        Delivery unavailable — <?= $d['distance_km'] ?>km away (max <?= $cfg['max_distance'] ?>km)
                    </div>
                <?php else: ?>
                    <div class="cart-delivery cart-delivery--muted">
                        <span>Est. Grab delivery</span>
                        <span>~<?= format_price($d['fee']) ?> <span class="cart-cod-note">COD</span></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="cart-footer">
            <div class="cart-totals">
                <div class="cart-total">
                    Total <strong><?= format_price($grandTotal) ?></strong>
                </div>
                <p class="cart-cod-info">Grab delivery is paid cash to the driver on arrival.</p>
            </div>
            <a href="/checkout/" class="btn-checkout">Checkout</a>
        </div>

    <?php endif; ?>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
