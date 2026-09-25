<?php
// The buyer's cart as the app sees it — cart/index.php's query and delivery
// estimate, without the HTML. Shared by api/v1/buyer/cart.php and
// cart-update.php, which both reply with the whole cart so the screen always
// redraws from what the server holds.
//
// Required after config/api.php and config/api-shop.php (for shop_img() and
// the language helpers).
//
// Kept in step with the website on purpose:
//   - the same visibility rule: a product that is switched off, or whose shop
//     is unapproved or suspended, drops out of the cart without a word;
//   - the same price: the variant's override or the product's price, less a
//     sale that is still running, worked out in SQL exactly as the cart page
//     and checkout do;
//   - the same delivery estimate per shop: no address, no pin on the shop, too
//     far, or a fee — tuk-tuk for the whole shop when any item needs one.
//
// One difference: `count` counts only what is shown. The website's header
// counts every row, hidden ones too, so its badge can say 3 over a cart of 2.

require_once __DIR__ . '/delivery-calc.php';
require_once __DIR__ . '/delivery-address.php';

/** The lines the cart shows. Same joins as cart/index.php. */
function buyer_cart_rows(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('
        SELECT ci.id AS cart_item_id, ci.quantity, ci.variant_id,
               p.id AS product_id, p.public_id, p.name, p.name_km, p.price, p.weight_g, p.delivery_method,
               p.sale_percent, p.sale_ends_at,
               COALESCE(pv.price_override, p.price) AS base_price,
               ROUND(COALESCE(pv.price_override, p.price)
                     * (100 - IF(p.sale_ends_at IS NOT NULL AND p.sale_ends_at > NOW() AND p.sale_percent IS NOT NULL, p.sale_percent, 0)) / 100, 2) AS effective_price,
               COALESCE(pv.stock, p.stock) AS effective_stock,
               pv.label AS variant_label, pv.label_km AS variant_label_km,
               pp.filename AS photo,
               b.id AS business_id, b.public_id AS business_public_id, b.name AS business_name, b.name_km AS business_name_km,
               b.lat AS biz_lat, b.lng AS biz_lng
          FROM cart_items ci
          JOIN products p ON p.id = ci.product_id AND p.active = 1
          JOIN businesses b ON b.id = p.business_id AND b.approved = 1 AND b.suspended = 0
          LEFT JOIN product_variants pv ON pv.id = ci.variant_id
          LEFT JOIN product_photos pp ON pp.product_id = p.id AND pp.is_primary = 1
         WHERE ci.buyer_user_id = ?
         ORDER BY b.name, p.name
    ');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/** How many items, for the tab badge. Only lines the cart would show. */
function buyer_cart_count(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare('
        SELECT COALESCE(SUM(ci.quantity), 0)
          FROM cart_items ci
          JOIN products p ON p.id = ci.product_id AND p.active = 1
          JOIN businesses b ON b.id = p.business_id AND b.approved = 1 AND b.suspended = 0
         WHERE ci.buyer_user_id = ?
    ');
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

/**
 * The whole cart: shops with their lines, subtotals and delivery estimates,
 * the total, the count, and `missing` — what checkout will refuse on.
 *
 * The website will not show the cart at all while anything is missing; it
 * sends the buyer to settings instead. The app shows the cart and names what
 * is missing above it, with `fix` saying which screen puts it right:
 * 'account' when only the phone is missing, else 'addresses'.
 */
function buyer_cart(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT lat, lng, address, khan, sangkat, phone FROM buyers WHERE id = ?');
    $stmt->execute([$userId]);
    $buyer = $stmt->fetch() ?: [];

    $buyerLat   = isset($buyer['lat']) && $buyer['lat'] !== '' ? (float)$buyer['lat'] : null;
    $buyerLng   = isset($buyer['lng']) && $buyer['lng'] !== '' ? (float)$buyer['lng'] : null;
    $hasAddress = $buyerLat !== null && $buyerLng !== null;

    $missing = $buyer ? buyer_missing_fields($buyer) : [];
    $cfg     = require __DIR__ . '/delivery.php';

    $groups   = [];
    $subtotal = 0.0;
    $count    = 0;

    foreach (buyer_cart_rows($pdo, $userId) as $row) {
        $bid = (int)$row['business_id'];
        if (!isset($groups[$bid])) {
            $groups[$bid] = [
                'business' => [
                    'id'   => $row['business_public_id'],
                    'name' => pick_lang($row['business_name'], $row['business_name_km'] ?? null),
                ],
                'items'    => [],
                'subtotal' => 0.0,
                '_lat'     => $row['biz_lat'] !== null ? (float)$row['biz_lat'] : null,
                '_lng'     => $row['biz_lng'] !== null ? (float)$row['biz_lng'] : null,
                '_vehicle' => 'bike',
            ];
        }

        $price = (float)$row['effective_price'];
        $base  = round((float)$row['base_price'], 2);
        $qty   = (int)$row['quantity'];

        $groups[$bid]['items'][] = [
            'cart_item_id'   => (int)$row['cart_item_id'],
            'product_id'     => $row['public_id'],
            'name'           => lang_field($row, 'name'),
            'variant_label'  => $row['variant_label'] !== null
                ? pick_lang($row['variant_label'], $row['variant_label_km'] ?? null) : null,
            'photo'          => shop_img($row['photo'] ?? null),
            'price'          => $price,
            // The price before the sale, when there is one, for the crossed-out figure.
            'original_price' => $base > $price ? $base : null,
            'quantity'       => $qty,
            'stock'          => max(0, (int)$row['effective_stock']),
            'line_total'     => round($price * $qty, 2),
        ];
        $groups[$bid]['subtotal'] += $price * $qty;
        // One tuk-tuk item makes the whole shop's delivery a tuk-tuk run.
        if ($row['delivery_method'] === 'tuktuk') $groups[$bid]['_vehicle'] = 'tuktuk';
        $subtotal += $price * $qty;
        $count    += $qty;
    }

    foreach ($groups as &$g) {
        if (!$hasAddress) {
            $g['delivery'] = ['state' => 'no_address'];
        } elseif ($g['_lat'] === null || $g['_lng'] === null) {
            $g['delivery'] = ['state' => 'no_pin'];
        } else {
            $dist = haversine_km($buyerLat, $buyerLng, $g['_lat'], $g['_lng']);
            if ($dist > $cfg['max_distance']) {
                $g['delivery'] = ['state' => 'out_of_range', 'distance_km' => round($dist, 1), 'max_km' => $cfg['max_distance']];
            } else {
                $d = calculate_delivery($dist, $g['_vehicle']);
                $g['delivery'] = ['state' => 'ok', 'fee' => $d['fee'], 'distance_km' => $d['distance_km'], 'vehicle_type' => $d['vehicle_type']];
            }
        }
        $g['subtotal'] = round($g['subtotal'], 2);
        unset($g['_lat'], $g['_lng'], $g['_vehicle']);
    }
    unset($g);

    return [
        'groups'   => array_values($groups),
        'subtotal' => round($subtotal, 2),
        // The website's cart total is the goods only; delivery is paid to the
        // driver on arrival, so it is an estimate beside each shop.
        'total'    => round($subtotal, 2),
        'count'    => $count,
        'missing'  => $missing,
        'fix'      => $missing ? ($missing === ['phone'] ? 'account' : 'addresses') : null,
        'khr_rate' => KHR_RATE,
    ];
}
