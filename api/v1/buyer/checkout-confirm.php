<?php
// "I've paid — place my order" — checkout/confirm.php with JSON in and out.
//
//   { coupon?, notes?, expected_total? }
//
// Every check there is repeated, in the same order, and the order is written
// the same way: one payments row (pending_confirmation), one order per shop
// (pending), coupon use, order items, stock taken, cart emptied, all in one
// transaction. After the commit, the low-stock alerts and the confirmation
// email are best effort, exactly as on the website — the buyer has already
// paid, so nothing after the commit may report failure back to them.
//
// Where the website redirects with a message, this replies 422 with a code and
// `checkout`, the preview as it now stands, so the screen can redraw:
//
//   missing           address or phone incomplete (see `checkout.missing`)
//   cart_empty        nothing to order
//   cart_changed      a line's product or shop was taken down
//   not_enough_stock  a line wants more than is left
//   out_of_range      a shop is too far from the address
//   total_changed     the total is not `expected_total` — a sale ended, a price
//                     changed, or the coupon stopped applying while the buyer
//                     was in their bank app. `total` is the new one. The website
//                     would place the order for the new amount; the app asks
//                     first, because the buyer transferred the old one.
//   order_failed      the transaction failed (a stock race); nothing was placed
//
// Two additions, neither of which the website has:
//   - `expected_total`, above. Left out, the check is skipped.
//   - a per-buyer lock around the whole thing, so a double tap or a retry sent
//     while the first request is still running cannot place the order twice.

require __DIR__ . '/../../../config/api.php';
require __DIR__ . '/../../../config/api-shop.php';
require __DIR__ . '/../../../config/buyer-cart.php';
require __DIR__ . '/../../../config/buyer-checkout.php';
require __DIR__ . '/../../../config/notify.php';
require __DIR__ . '/../../../config/self-deal.php';

api_require_method('POST');

$me     = api_require_buyer($pdo);
$userId = (int)$me['id'];

$body          = api_body();
$couponCode    = strtoupper(trim((string)($body['coupon'] ?? '')));
$buyerNotes    = trim(mb_substr((string)($body['notes'] ?? ''), 0, 500));
$expectedTotal = isset($body['expected_total']) && is_numeric($body['expected_total'])
    ? round((float)$body['expected_total'], 2) : null;

$refuse = function (string $code, array $extra = []) use ($pdo, $userId, $couponCode): void {
    $pdo->query("SELECT RELEASE_LOCK('checkout_buyer_" . $userId . "')");
    api_json(['error' => $code] + $extra + ['checkout' => buyer_checkout($pdo, $userId, $couponCode)], 422);
};

// Held until the reply is sent. A second request waits here, then finds the
// cart already empty.
$pdo->query("SELECT GET_LOCK('checkout_buyer_" . $userId . "', 15)");

$stmt = $pdo->prepare('SELECT lat, lng, house_number, address, khan, sangkat, phone, email_verified_at, name, email FROM buyers WHERE id = ?');
$stmt->execute([$userId]);
$buyer = $stmt->fetch();

// api_require_buyer() has already refused an unverified email, so the
// website's email_verified_at check cannot fail here; the row is still needed.
if (!$buyer) api_json(['error' => 'unauthorized'], 401);

$buyerLat = ($buyer['lat'] !== null && $buyer['lat'] !== '') ? (float)$buyer['lat'] : null;
$buyerLng = ($buyer['lng'] !== null && $buyer['lng'] !== '') ? (float)$buyer['lng'] : null;

if (buyer_missing_fields($buyer)) $refuse('missing');

$stmt = $pdo->prepare('
    SELECT ci.id AS cart_item_id, ci.quantity, ci.variant_id,
           p.id AS product_id, p.name AS product_name, p.name_km AS product_name_km, p.price, p.stock, p.delivery_method,
           ROUND(COALESCE(pv.price_override, p.price)
                 * (100 - IF(p.sale_ends_at IS NOT NULL AND p.sale_ends_at > NOW() AND p.sale_percent IS NOT NULL, p.sale_percent, 0)) / 100, 2) AS effective_price,
           COALESCE(pv.stock, p.stock) AS effective_stock,
           pv.label AS variant_label, pv.label_km AS variant_label_km,
           b.id AS business_id, b.name AS business_name, b.lat AS biz_lat, b.lng AS biz_lng,
           COALESCE(cat.royalty_rate, 0) AS royalty_rate,
           COALESCE(b.royalty_add_on, 0) AS business_royalty_add_on,
           COALESCE(p.royalty_add_on, 0) AS product_royalty_add_on
    FROM cart_items ci
    JOIN products p ON p.id = ci.product_id AND p.active = 1
    JOIN businesses b ON b.id = p.business_id AND b.approved = 1 AND b.suspended = 0
    LEFT JOIN product_variants pv ON pv.id = ci.variant_id
    LEFT JOIN categories cat ON cat.id = p.category_id
    WHERE ci.buyer_user_id = ?
');
$stmt->execute([$userId]);
$items = $stmt->fetchAll();

// The joins drop switched-off products and closed shops. If that dropped
// anything, refuse rather than silently order less.
$cartCountStmt = $pdo->prepare('SELECT COUNT(*) FROM cart_items WHERE buyer_user_id = ?');
$cartCountStmt->execute([$userId]);
if (count($items) < (int)$cartCountStmt->fetchColumn()) $refuse('cart_changed');

if (empty($items)) $refuse('cart_empty');

foreach ($items as $item) {
    if ($item['quantity'] > $item['effective_stock']) $refuse('not_enough_stock');
}

$cfg      = require __DIR__ . '/../../../config/delivery.php';
$grouped  = [];
$subtotal = 0.0;

// Active penalty rate totals per business
$uniqueBizIds = array_values(array_unique(array_column($items, 'business_id')));
$ph = implode(',', array_fill(0, count($uniqueBizIds), '?'));
$stmt = $pdo->prepare("
    SELECT business_id, SUM(rate_increase) AS penalty_rate
    FROM vendor_penalties
    WHERE business_id IN ($ph)
      AND cleared_at IS NULL
      AND start_date <= CURDATE()
      AND (end_date IS NULL OR end_date >= CURDATE())
    GROUP BY business_id
");
$stmt->execute($uniqueBizIds);
$penaltyByBiz = [];
foreach ($stmt->fetchAll() as $row) {
    $penaltyByBiz[(int)$row['business_id']] = (float)$row['penalty_rate'];
}

foreach ($items as $item) {
    $bid = $item['business_id'];
    if (!isset($grouped[$bid])) {
        $grouped[$bid] = [
            'items'          => [],
            'subtotal'       => 0.0,
            'royalty_amount' => 0.0,
            'vehicle_type'   => 'bike',
            'biz_lat'        => ($item['biz_lat'] !== null) ? (float)$item['biz_lat'] : null,
            'biz_lng'        => ($item['biz_lng'] !== null) ? (float)$item['biz_lng'] : null,
        ];
    }
    $lineTotal     = $item['effective_price'] * $item['quantity'];
    $effectiveRate = (float)$item['royalty_rate'] + ($penaltyByBiz[$bid] ?? 0.0)
                   + (float)$item['business_royalty_add_on'] + (float)$item['product_royalty_add_on'];
    $grouped[$bid]['items'][]         = $item;
    $grouped[$bid]['subtotal']       += $lineTotal;
    $grouped[$bid]['royalty_amount'] += $lineTotal * $effectiveRate;
    if ($item['delivery_method'] === 'tuktuk') $grouped[$bid]['vehicle_type'] = 'tuktuk';
    $subtotal += $lineTotal;
}

// Distance for the record; delivery is COD (buyer pays the Grab driver directly)
foreach ($grouped as $bid => &$group) {
    $group['delivery_fee']          = 0.0;
    $group['vendor_delivery_bonus'] = 0.0;
    $group['delivery_distance_km']  = null;

    if ($group['biz_lat'] !== null && $group['biz_lng'] !== null && $buyerLat !== null && $buyerLng !== null) {
        $dist = haversine_km($buyerLat, $buyerLng, $group['biz_lat'], $group['biz_lng']);
        if ($dist > $cfg['max_distance']) $refuse('out_of_range');
        $group['delivery_distance_km'] = round($dist, 2);
    }
    $group['delivery_weight_g'] = null;

    // Vendor promo trial — zero out royalty if the trial is still running
    $trialStmt = $pdo->prepare('
        SELECT b.trial_starts_at, b.trial_ends_at, b.royalty_free_threshold, b.royalty_waived,
               COALESCE(SUM(o2.subtotal), 0) AS completed_sales
        FROM businesses b
        LEFT JOIN orders o2 ON o2.business_id = b.id AND o2.status IN (\'delivered\', \'completed\')
        WHERE b.id = ?
        GROUP BY b.id
    ');
    $trialStmt->execute([$bid]);
    $trial = $trialStmt->fetch();
    if ($trial && $trial['royalty_waived']) {
        $group['royalty_amount'] = 0.0;
        $group['royalty_rate']   = 0.0;
        $group['vendor_payout']  = round($group['subtotal'], 2);
        continue;
    }
    if ($trial && $trial['trial_starts_at']) {
        $withinTime     = strtotime($trial['trial_ends_at']) > time();
        $belowThreshold = (float)$trial['completed_sales'] < (float)$trial['royalty_free_threshold'];
        if ($withinTime || $belowThreshold) {
            $group['royalty_amount'] = 0.0;
            $group['royalty_rate']   = 0.0;
            $group['vendor_payout']  = round($group['subtotal'], 2);
            continue;
        }
    }

    $royaltyAmount = round($group['royalty_amount'], 2);
    $group['royalty_amount'] = $royaltyAmount;
    $group['royalty_rate']   = $group['subtotal'] > 0
        ? round($royaltyAmount / $group['subtotal'], 4)
        : 0.0;
    $group['vendor_payout']  = round($group['subtotal'] - $royaltyAmount, 2);
}
unset($group);

// Coupon — re-validated against the final subtotals. A code that no longer
// applies is dropped, as on the website; expected_total below is what stops
// that turning into a surprise.
$subtotalsByBusiness = array_map(fn($g) => $g['subtotal'], $grouped);

$couponId         = null;
$couponBusinessId = null;
$discount         = 0.0;
if ($couponCode !== '') {
    $couponResult = validate_coupon($pdo, $couponCode, $subtotalsByBusiness, $userId);
    if ($couponResult['valid']) {
        $couponId         = (int)$couponResult['coupon']['id'];
        $couponBusinessId = $couponResult['business_id'];
        $discount         = $couponResult['discount'];
    }
}

if ($expectedTotal !== null && abs(round(max(0, $subtotal - $discount), 2) - $expectedTotal) >= 0.005) {
    $refuse('total_changed', ['total' => round(max(0, $subtotal - $discount), 2)]);
}

// Self-dealing check — advisory flags that ride along on the order. See
// config/self-deal.php.
$selfDealByBusiness = [];
if ($grouped) {
    $sdIds = array_keys($grouped);
    $sdIn  = implode(',', array_fill(0, count($sdIds), '?'));
    $sdStmt = $pdo->prepare("
        SELECT b.id AS business_id, v.email, v.phone,
               b.house_number AS biz_house_number, b.address AS biz_address,
               b.khan AS biz_khan, b.sangkat AS biz_sangkat
        FROM businesses b JOIN vendors v ON v.id = b.user_id
        WHERE b.id IN ($sdIn)
    ");
    $sdStmt->execute($sdIds);
    foreach ($sdStmt->fetchAll() as $sdRow) {
        $sdFlags = self_deal_flags($buyer, $sdRow);
        if ($sdFlags) {
            $selfDealByBusiness[(int)$sdRow['business_id']] = implode(',', $sdFlags);
        }
    }
}

$buyerIp = null;
foreach ([$_SERVER['HTTP_X_FORWARDED_FOR'] ?? '', $_SERVER['REMOTE_ADDR'] ?? ''] as $sdIpRaw) {
    $sdIp = trim(explode(',', (string)$sdIpRaw)[0]);
    if ($sdIp !== '' && filter_var($sdIp, FILTER_VALIDATE_IP)) { $buyerIp = $sdIp; break; }
}

try {
    $pdo->beginTransaction();

    if ($couponId) {
        // Atomic re-check under the transaction — the last use of a coupon
        // spent by two buyers at once.
        $incStmt = $pdo->prepare('UPDATE coupons SET used_count = used_count + 1 WHERE id = ? AND (max_uses IS NULL OR used_count < max_uses)');
        $incStmt->execute([$couponId]);
        if ($incStmt->rowCount() === 0) {
            $couponId         = null;
            $couponBusinessId = null;
            $discount         = 0.0;
            $couponCode       = '';
        }
    }

    $grandTotal = max(0, $subtotal - $discount);

    // The coupon's last use went to someone else a moment ago. Same answer as
    // above: the buyer paid the old total, so ask before charging a new one.
    if ($expectedTotal !== null && abs(round($grandTotal, 2) - $expectedTotal) >= 0.005) {
        $pdo->rollBack();
        $refuse('total_changed', ['total' => round($grandTotal, 2)]);
    }

    $discountRemaining = $discount;
    $groupKeys         = array_keys($grouped);
    $lastGroupKey      = end($groupKeys);

    $stmt = $pdo->prepare('INSERT INTO payments (buyer_user_id, total, status) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $grandTotal, 'pending_confirmation']);
    $paymentId = $pdo->lastInsertId();

    foreach ($grouped as $businessId => $group) {
        $groupDiscount = 0.0;
        if ($discount > 0) {
            if ($couponBusinessId !== null) {
                // Vendor-owned coupon — the full discount goes on that vendor's order.
                $groupDiscount = ($businessId === $couponBusinessId) ? $discount : 0.0;
            } else {
                $groupDiscount = ($businessId === $lastGroupKey)
                    ? $discountRemaining
                    : round($discount * $group['subtotal'] / $subtotal, 2);
                $discountRemaining -= $groupDiscount;
            }
        }

        // Vendor-owned coupons come out of that vendor's payout, floored at
        // zero; sitewide coupons are absorbed by the platform.
        $vendorPayout = ($couponBusinessId !== null && $businessId === $couponBusinessId)
            ? max(0.0, round($group['vendor_payout'] - $groupDiscount, 2))
            : $group['vendor_payout'];

        $stmt = $pdo->prepare('
            INSERT INTO orders
                (payment_id, buyer_user_id, business_id, subtotal, delivery_fee, vendor_delivery_bonus,
                 delivery_distance_km, delivery_weight_g, royalty_rate, royalty_amount, vendor_payout, buyer_notes,
                 coupon_id, coupon_code, discount_amount, status, public_id, self_deal_flags, buyer_ip)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $paymentId, $userId, $businessId,
            $group['subtotal'],
            $group['delivery_fee'],
            $group['vendor_delivery_bonus'],
            $group['delivery_distance_km'],
            $group['delivery_weight_g'],
            $group['royalty_rate'],
            $group['royalty_amount'],
            $vendorPayout,
            $buyerNotes ?: null,
            $couponId,
            $couponId ? $couponCode : null,
            $groupDiscount,
            'pending',
            uuid_v4(),
            $selfDealByBusiness[(int)$businessId] ?? null,
            $buyerIp,
        ]);
        $orderId = $pdo->lastInsertId();
        $grouped[$businessId]['order_id'] = (int)$orderId;

        if ($couponId) {
            $pdo->prepare('INSERT INTO coupon_uses (coupon_id, buyer_id, order_id, discount_amount) VALUES (?, ?, ?, ?)')
                ->execute([$couponId, $userId, $orderId, $groupDiscount]);
        }

        foreach ($group['items'] as $item) {
            $stmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, variant_id, variant_label, variant_label_km, product_name, product_name_km, price_at_purchase, quantity) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$orderId, $item['product_id'], $item['variant_id'], $item['variant_label'], $item['variant_label_km'] ?: null, $item['product_name'], $item['product_name_km'] ?: null, $item['effective_price'], $item['quantity']]);

            if ($item['variant_id']) {
                $stmt = $pdo->prepare('UPDATE product_variants SET stock = stock - ? WHERE id = ? AND stock >= ?');
                $stmt->execute([$item['quantity'], $item['variant_id'], $item['quantity']]);
                if ($stmt->rowCount() === 0) {
                    throw new \RuntimeException('Stock unavailable for: ' . $item['product_name'] . ' (' . $item['variant_label'] . ')');
                }
                $pdo->prepare('UPDATE products p SET p.stock = (SELECT COALESCE(SUM(v.stock),0) FROM product_variants v WHERE v.product_id = p.id) WHERE p.id = ?')
                    ->execute([$item['product_id']]);
            } else {
                $stmt = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');
                $stmt->execute([$item['quantity'], $item['product_id'], $item['quantity']]);
                if ($stmt->rowCount() === 0) {
                    throw new \RuntimeException('Stock unavailable for: ' . $item['product_name']);
                }
            }
        }
    }

    $pdo->prepare('DELETE FROM cart_items WHERE buyer_user_id = ?')->execute([$userId]);
    $pdo->prepare('UPDATE buyers SET abandoned_cart_notified_at = NULL WHERE id = ?')->execute([$userId]);

    $pdo->commit();

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('checkout-confirm: ' . $e->getMessage());
    $refuse('order_failed');
}

$pdo->query("SELECT RELEASE_LOCK('checkout_buyer_" . $userId . "')");

// Past this point the orders are committed and the buyer has paid, so nothing
// below may report failure. Same split, and the same messages, as the website.
try {
    foreach ($grouped as $group) {
        foreach ($group['items'] as $item) {
            $lowStmt = $pdo->prepare('
                SELECT p.id, p.public_id, p.name, p.stock, p.low_stock_threshold,
                       v.id AS vendor_id, v.email AS vendor_email, v.name AS vendor_name
                FROM products p
                JOIN businesses b ON b.id = p.business_id
                JOIN vendors v ON v.id = b.user_id
                WHERE p.id = ?
                  AND p.low_stock_threshold > 0
                  AND p.stock <= p.low_stock_threshold
                  AND (p.low_stock_notified_at IS NULL OR p.low_stock_notified_at < DATE_SUB(NOW(), INTERVAL 24 HOUR))
            ');
            $lowStmt->execute([$item['product_id']]);
            $lp = $lowStmt->fetch();
            if ($lp) {
                $units = (int)$lp['stock'];
                $unitWord = $units !== 1 ? 'units' : 'unit';
                notify($pdo, 'vendor', (int)$lp['vendor_id'], 'low_stock',
                    'Low stock: "' . $lp['name'] . '" — ' . $units . ' ' . $unitWord . ' remaining.',
                    '/products/?action=edit&id=' . $lp['public_id'],
                    ['name' => $lp['name'], 'units' => $units]
                );
                [$subj, $html] = render_email_template($pdo, 'low_stock', [
                    'name'    => htmlspecialchars($lp['vendor_name']),
                    'product' => htmlspecialchars($lp['name']),
                    'units'   => $units,
                    'cta_url' => 'https://teepsaa.com/products/?action=edit&id=' . $lp['public_id'],
                ]);
                if ($html !== '') send_email($lp['vendor_email'], $subj, $html);
                $pdo->prepare('UPDATE products SET low_stock_notified_at = NOW() WHERE id = ?')
                    ->execute([$lp['id']]);
            }
        }
    }

    $itemLines = '';
    foreach ($grouped as $group) {
        $orderRef = date('ymd') . '-' . str_pad((string)($group['order_id'] ?? 0), 4, '0', STR_PAD_LEFT);
        $itemLines .= '<tr><td colspan="2" style="padding:8px 0 2px;font-size:0.8rem;color:#999;text-transform:uppercase;letter-spacing:0.04em">'
            . 'Order ' . $orderRef . ' &middot; ' . htmlspecialchars($group['items'][0]['business_name'] ?? '') . '</td></tr>';
        foreach ($group['items'] as $item) {
            $label = htmlspecialchars($item['product_name'])
                . ($item['variant_label'] ? ' <span style="color:#999">(' . htmlspecialchars($item['variant_label']) . ')</span>' : '');
            $linePrice = '$' . number_format($item['effective_price'] * $item['quantity'], 2);
            $itemLines .= '<tr>'
                . '<td style="padding:3px 0;font-size:0.9rem">' . $label . ' &times; ' . (int)$item['quantity'] . '</td>'
                . '<td style="padding:3px 0;font-size:0.9rem;text-align:right">' . $linePrice . '</td>'
                . '</tr>';
        }
    }
    $notesRow = $buyerNotes
        ? '<p style="margin:16px 0 0;font-size:0.85rem;color:#555"><strong>កំណត់ចំណាំដឹកជញ្ជូន · Delivery note:</strong> ' . htmlspecialchars($buyerNotes) . '</p>'
        : '';
    $discountRow = $discount > 0
        ? '<p style="margin:4px 0 0;font-size:0.9rem;color:#555">Discount (' . htmlspecialchars($couponCode) . '): &minus;$' . number_format($discount, 2) . '</p>'
        : '';
    $splitNote = count($grouped) > 1
        ? '<p style="margin:0 0 12px;font-size:0.85rem;color:#555">ការបញ្ជាទិញរបស់អ្នកនឹងត្រូវដឹកជញ្ជូនដោយឡែកពីគ្នា ហើយអាចមកដល់នៅពេលខុសៗគ្នា។<br>Your orders will be delivered separately and may arrive at different times.</p>'
        : '';
    $emailSummary = $splitNote
        . '<table style="width:100%;border-collapse:collapse">' . $itemLines . '</table>'
        . '<hr style="border:none;border-top:1px solid #eee;margin:16px 0">'
        . $discountRow
        . '<p style="margin:0;font-size:0.95rem"><strong>សរុប · Total: $' . number_format($grandTotal, 2) . '</strong></p>'
        . $notesRow;
    [$subj, $html] = render_email_template($pdo, 'order_received', [
        'summary' => $emailSummary,
        'cta_url' => 'https://teepsaa.com/orders-buyer/',
    ]);
    if ($html !== '') send_email($buyer['email'], $subj, $html);

} catch (Throwable $e) {
    // Best effort. The order stands.
}

api_json([
    'ok'              => true,
    'payment_id'      => (int)$paymentId,
    'order_count'     => count($grouped),
    'total'           => round($grandTotal, 2),
    'last_payment_id' => (int)$paymentId,
    'count'           => 0,
]);
