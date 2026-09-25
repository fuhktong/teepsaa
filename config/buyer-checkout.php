<?php
// Checkout as the app sees it — checkout/index.php without the HTML and
// without the session. Used by api/v1/buyer/checkout.php (the preview) and
// by checkout-confirm.php (which answers a refusal with the same preview, so
// the screen redraws from what the server now holds).
//
// Required after config/api.php, config/api-shop.php and config/buyer-cart.php.
//
// What the website keeps in the session travels in the request instead: the
// coupon code comes in with every preview and with the confirm, and is checked
// afresh each time by validate_coupon(), the one the website uses.
//
// Everything that would stop confirm-checkout.php placing the order is named
// up front in `blockers`, so "I've paid" is only offered when it will work:
//
//   empty         nothing in the cart
//   missing       street, khan, sangkat, pin or phone (with `missing` and `fix`)
//   unavailable   a line whose product was switched off or whose shop closed.
//                 The cart hides these, and the website's confirm refuses while
//                 any are there — with no way to see or remove them. The app
//                 lists them in `unavailable` with their cart_item_id so they
//                 can be removed through cart-update.php.
//   stock         more in the cart than is in stock (listed in `short_stock`)
//   out_of_range  a shop too far from the address (named in `out_of_range`)

require_once __DIR__ . '/coupon.php';
require_once __DIR__ . '/buyer-addresses.php';

/** Cart lines the cart would hide: product off, archived shop, closed shop. */
function buyer_hidden_cart_rows(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('
        SELECT ci.id AS cart_item_id, p.name, p.name_km, pv.label AS variant_label, pv.label_km AS variant_label_km
          FROM cart_items ci
          LEFT JOIN products p ON p.id = ci.product_id
          LEFT JOIN businesses b ON b.id = p.business_id
          LEFT JOIN product_variants pv ON pv.id = ci.variant_id
         WHERE ci.buyer_user_id = ?
           AND (p.id IS NULL OR p.active <> 1 OR b.id IS NULL OR b.approved <> 1 OR b.suspended <> 0)
    ');
    $stmt->execute([$userId]);

    $out = [];
    foreach ($stmt->fetchAll() as $r) {
        $out[] = [
            'cart_item_id'  => (int)$r['cart_item_id'],
            'name'          => $r['name'] !== null ? lang_field($r, 'name') : '',
            'variant_label' => $r['variant_label'] !== null
                ? pick_lang($r['variant_label'], $r['variant_label_km'] ?? null) : null,
        ];
    }
    return $out;
}

/** The newest payment's id, or null. How the app tells a lost reply from a failed order. */
function buyer_last_payment_id(PDO $pdo, int $userId): ?int
{
    $stmt = $pdo->prepare('SELECT MAX(id) FROM payments WHERE buyer_user_id = ?');
    $stmt->execute([$userId]);
    $id = $stmt->fetchColumn();
    return $id ? (int)$id : null;
}

/**
 * The whole checkout screen: buyer_cart()'s groups and totals, then the
 * coupon, the discount and the total to pay, the QR, the address being
 * delivered to and the book to switch it from, and `blockers`.
 */
function buyer_checkout(PDO $pdo, int $userId, string $couponCode = ''): array
{
    $cart = buyer_cart($pdo, $userId);

    // Private ids for the coupon rules, which work on businesses.id. The cart
    // groups carry only the public id, so the subtotals are summed again here.
    $stmt = $pdo->prepare('
        SELECT b.id AS business_id, b.public_id,
               ROUND(COALESCE(pv.price_override, p.price)
                     * (100 - IF(p.sale_ends_at IS NOT NULL AND p.sale_ends_at > NOW() AND p.sale_percent IS NOT NULL, p.sale_percent, 0)) / 100, 2) AS effective_price,
               ci.quantity
          FROM cart_items ci
          JOIN products p ON p.id = ci.product_id AND p.active = 1
          JOIN businesses b ON b.id = p.business_id AND b.approved = 1 AND b.suspended = 0
          LEFT JOIN product_variants pv ON pv.id = ci.variant_id
         WHERE ci.buyer_user_id = ?
    ');
    $stmt->execute([$userId]);
    $subtotalsByBusiness = [];
    $publicIdOf          = [];
    foreach ($stmt->fetchAll() as $row) {
        $bid = (int)$row['business_id'];
        $subtotalsByBusiness[$bid] = ($subtotalsByBusiness[$bid] ?? 0.0) + $row['effective_price'] * $row['quantity'];
        $publicIdOf[$bid] = $row['public_id'];
    }

    // ── The coupon ──
    $couponCode = strtoupper(trim($couponCode));
    $coupon     = null;
    $discount   = 0.0;
    if ($couponCode !== '' && $subtotalsByBusiness) {
        $r = validate_coupon($pdo, $couponCode, $subtotalsByBusiness, $userId);
        if ($r['valid']) {
            $discount = (float)$r['discount'];
            $shop     = null;
            if ($r['business_id'] !== null) {
                foreach ($cart['groups'] as $g) {
                    if ($g['business']['id'] === ($publicIdOf[$r['business_id']] ?? null)) { $shop = $g['business']; break; }
                }
            }
            $coupon = ['code' => $couponCode, 'valid' => true, 'discount' => $discount, 'business' => $shop];
        } else {
            $coupon = ['code' => $couponCode, 'valid' => false, 'reason' => $r['reason'] ?? 'invalid']
                    + (isset($r['min_order']) ? ['min_order' => $r['min_order']] : []);
        }
    }

    // Offer the code box only when a code this cart could use exists — sitewide,
    // or belonging to a shop in the cart. Same query as the website.
    $couponAvailable = false;
    if ($subtotalsByBusiness) {
        $ph   = implode(',', array_fill(0, count($subtotalsByBusiness), '?'));
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM coupons
             WHERE active = 1
               AND (business_id IS NULL OR business_id IN ($ph))
               AND (starts_at IS NULL OR starts_at <= NOW())
               AND (expires_at IS NULL OR expires_at >= NOW())
               AND (max_uses IS NULL OR used_count < max_uses)
        ");
        $stmt->execute(array_keys($subtotalsByBusiness));
        $couponAvailable = (int)$stmt->fetchColumn() > 0;
    }

    // ── What would stop the order ──
    $blockers   = [];
    $outOfRange = [];
    $shortStock = [];
    foreach ($cart['groups'] as $g) {
        if ($g['delivery']['state'] === 'out_of_range') $outOfRange[] = $g['business']['name'];
        foreach ($g['items'] as $i) {
            if ($i['quantity'] > $i['stock']) {
                $shortStock[] = ['cart_item_id' => $i['cart_item_id'], 'name' => $i['name'],
                                 'variant_label' => $i['variant_label'], 'quantity' => $i['quantity'], 'stock' => $i['stock']];
            }
        }
    }
    $hidden = buyer_hidden_cart_rows($pdo, $userId);

    if (!$cart['groups'])  $blockers[] = 'empty';
    if ($cart['missing'])  $blockers[] = 'missing';
    if ($hidden)           $blockers[] = 'unavailable';
    if ($shortStock)       $blockers[] = 'stock';
    if ($outOfRange)       $blockers[] = 'out_of_range';

    // ── The address being delivered to ──
    // The buyers table is what delivery runs on; its label lives on the
    // default row of the address book, as on the website.
    $stmt = $pdo->prepare('SELECT house_number, address, address_notes, khan, sangkat FROM buyers WHERE id = ?');
    $stmt->execute([$userId]);
    $b         = $stmt->fetch() ?: [];
    $addresses = buyer_address_list($pdo, $userId);
    $label     = null;
    foreach ($addresses as $a) { if ($a['is_default']) { $label = $a['label']; break; } }
    $street = implode(', ', array_filter([$b['house_number'] ?? '', $b['address'] ?? '']));
    $area   = implode(', ', array_filter([$b['sangkat'] ?? '', $b['khan'] ?? '']));

    $qrFile = __DIR__ . '/../uploads/aba-qr.png';

    return [
        'groups'           => $cart['groups'],
        'subtotal'         => $cart['subtotal'],
        'discount'         => round($discount, 2),
        'total'            => round(max(0, $cart['subtotal'] - $discount), 2),
        'count'            => $cart['count'],
        'coupon'           => $coupon,
        'coupon_available' => $couponAvailable,
        'missing'          => $cart['missing'],
        'fix'              => $cart['fix'],
        'blockers'         => $blockers,
        'can_checkout'     => !$blockers,
        'out_of_range'     => $outOfRange,
        'short_stock'      => $shortStock,
        'unavailable'      => $hidden,
        // The one teepsaa ABA QR, the same picture for every order, with no
        // amount inside it — the buyer types the amount in their bank app.
        'qr'               => file_exists($qrFile) ? SHOP_IMG_BASE . '/uploads/aba-qr.png?v=' . filemtime($qrFile) : null,
        'address'          => ($street !== '' || $area !== '')
            ? ['label' => $label, 'street' => $street, 'notes' => $b['address_notes'] ?? '', 'area' => $area]
            : null,
        'addresses'        => $addresses,
        'last_payment_id'  => buyer_last_payment_id($pdo, $userId),
        'khr_rate'         => KHR_RATE,
    ];
}
