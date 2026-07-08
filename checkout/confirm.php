<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/delivery-calc.php';
require __DIR__ . '/../config/notify.php';
require __DIR__ . '/../config/coupon.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'buyer') {
    header('Location: /login-buyer/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /checkout/');
    exit;
}

csrf_verify();

$userId = $_SESSION['user_id'];

// Require verified email and delivery address
$stmt = $pdo->prepare('SELECT lat, lng, email_verified_at, name, email FROM buyers WHERE id = ?');
$stmt->execute([$userId]);
$buyer = $stmt->fetch();

if (!$buyer['email_verified_at']) {
    $_SESSION['cart_error'] = 'Please verify your email address before placing an order.';
    header('Location: /resend-verification/');
    exit;
}

$buyerLat = ($buyer['lat'] !== null && $buyer['lat'] !== '') ? (float)$buyer['lat'] : null;
$buyerLng = ($buyer['lng'] !== null && $buyer['lng'] !== '') ? (float)$buyer['lng'] : null;

if ($buyerLat === null || $buyerLng === null) {
    $_SESSION['cart_error'] = 'Please set your delivery address before checking out.';
    header('Location: /dashboard-buyer/settings/?tab=address');
    exit;
}

$stmt = $pdo->prepare('
    SELECT ci.id AS cart_item_id, ci.quantity, ci.variant_id,
           p.id AS product_id, p.name AS product_name, p.name_km AS product_name_km, p.price, p.stock, p.delivery_method,
           COALESCE(pv.price_override, IF(p.sale_ends_at IS NOT NULL AND p.sale_ends_at > NOW(), p.sale_price, NULL), p.price) AS effective_price,
           COALESCE(pv.stock, p.stock) AS effective_stock,
           pv.label AS variant_label, pv.label_km AS variant_label_km,
           b.id AS business_id, b.name AS business_name, b.lat AS biz_lat, b.lng AS biz_lng,
           COALESCE(cat.royalty_rate, 0) AS royalty_rate,
           COALESCE(b.royalty_add_on, 0) AS business_royalty_add_on,
           COALESCE(p.royalty_add_on, 0) AS product_royalty_add_on
    FROM cart_items ci
    JOIN products p ON p.id = ci.product_id AND p.active = 1
    JOIN businesses b ON b.id = p.business_id AND b.approved = 1
    LEFT JOIN product_variants pv ON pv.id = ci.variant_id
    LEFT JOIN categories cat ON cat.id = p.category_id
    WHERE ci.buyer_user_id = ?
');
$stmt->execute([$userId]);
$items = $stmt->fetchAll();

// The joins above filter out deactivated products and unapproved businesses —
// if that dropped anything, send the buyer back rather than silently ordering less.
$cartCountStmt = $pdo->prepare('SELECT COUNT(*) FROM cart_items WHERE buyer_user_id = ?');
$cartCountStmt->execute([$userId]);
if (count($items) < (int)$cartCountStmt->fetchColumn()) {
    $_SESSION['cart_error'] = 'Some items in your cart are no longer available. Please update your cart.';
    header('Location: /cart/');
    exit;
}

if (empty($items)) {
    header('Location: /cart/');
    exit;
}

foreach ($items as $item) {
    if ($item['quantity'] > $item['effective_stock']) {
        $label = $item['product_name'] . ($item['variant_label'] ? ' (' . $item['variant_label'] . ')' : '');
        $_SESSION['cart_error'] = htmlspecialchars($label) . ' does not have enough stock.';
        header('Location: /cart/');
        exit;
    }
}

$cfg      = require __DIR__ . '/../config/delivery.php';
$grouped  = [];
$subtotal = 0.0;

// Fetch active penalty rate totals per business
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
    $grouped[$bid]['items'][]        = $item;
    $grouped[$bid]['subtotal']      += $lineTotal;
    $grouped[$bid]['royalty_amount'] += $lineTotal * $effectiveRate;
    if ($item['delivery_method'] === 'tuktuk') $grouped[$bid]['vehicle_type'] = 'tuktuk';
    $subtotal += $lineTotal;
}

// Calculate distance for records; delivery is COD (buyer pays Grab driver directly)
foreach ($grouped as $bid => &$group) {
    $group['delivery_fee']          = 0.0;
    $group['vendor_delivery_bonus'] = 0.0;
    $group['delivery_distance_km']  = null;

    if ($group['biz_lat'] !== null && $group['biz_lng'] !== null && $buyerLat !== null && $buyerLng !== null) {
        $dist = haversine_km($buyerLat, $buyerLng, $group['biz_lat'], $group['biz_lng']);
        if ($dist > $cfg['max_distance']) {
            $_SESSION['cart_error'] = 'One or more businesses is outside the delivery area. Please update your cart.';
            header('Location: /cart/');
            exit;
        }
        $group['delivery_distance_km'] = round($dist, 2);
    }
    $group['delivery_weight_g'] = null;

    // Check vendor promo trial — zero out royalty if trial still active
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

$buyerNotes  = trim(mb_substr($_POST['buyer_notes'] ?? '', 0, 500));

// Coupon — re-validated here against the final subtotal regardless of what
// the checkout page showed. If it's no longer valid (cart changed, race on
// max_uses, etc.) the order simply proceeds at full price rather than erroring.
$subtotalsByBusiness = array_map(fn($g) => $g['subtotal'], $grouped);

$couponCode       = $_SESSION['checkout_coupon_code'] ?? '';
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

try {
    $pdo->beginTransaction();

    if ($couponId) {
        // Atomic re-check under the transaction — closes the race where two
        // buyers spend a coupon's last remaining use at the same time.
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
    // Sitewide coupons (couponBusinessId === null) split proportionally across every
    // vendor's order and are absorbed by the platform — royalty/vendor_payout above
    // are already computed on the pre-discount subtotal, so vendor pay is unaffected.
    // Vendor-owned coupons apply entirely to that one vendor's order instead, and are
    // deducted from that vendor's own payout further down — the vendor funds their own discount.
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
                // Vendor-owned coupon — the full discount applies only to that vendor's order.
                $groupDiscount = ($businessId === $couponBusinessId) ? $discount : 0.0;
            } else {
                $groupDiscount = ($businessId === $lastGroupKey)
                    ? $discountRemaining
                    : round($discount * $group['subtotal'] / $subtotal, 2);
                $discountRemaining -= $groupDiscount;
            }
        }

        // Vendor-owned coupons come out of that vendor's own payout; sitewide
        // coupons are platform-absorbed, so vendor_payout is untouched.
        $vendorPayout = ($couponBusinessId !== null && $businessId === $couponBusinessId)
            ? round($group['vendor_payout'] - $groupDiscount, 2)
            : $group['vendor_payout'];

        $stmt = $pdo->prepare('
            INSERT INTO orders
                (payment_id, buyer_user_id, business_id, subtotal, delivery_fee, vendor_delivery_bonus,
                 delivery_distance_km, delivery_weight_g, royalty_rate, royalty_amount, vendor_payout, buyer_notes,
                 coupon_id, coupon_code, discount_amount, status, public_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
        ]);
        $orderId = $pdo->lastInsertId();

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
                // Keep product stock in sync
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

    $stmt = $pdo->prepare('DELETE FROM cart_items WHERE buyer_user_id = ?');
    $stmt->execute([$userId]);

    $pdo->prepare('UPDATE buyers SET abandoned_cart_notified_at = NULL WHERE id = ?')->execute([$userId]);

    $pdo->commit();
    unset($_SESSION['checkout_coupon_code']);

    // Low stock alerts — best-effort, run after commit
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

    // Order confirmation email
    $itemLines = '';
    foreach ($grouped as $group) {
        $itemLines .= '<tr><td colspan="2" style="padding:8px 0 2px;font-size:0.8rem;color:#999;text-transform:uppercase;letter-spacing:0.04em">'
            . htmlspecialchars($group['items'][0]['business_name'] ?? '') . '</td></tr>';
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
    // Shared order summary (item names + prices) — shown once, under the Khmer block.
    $emailSummary = '<table style="width:100%;border-collapse:collapse">' . $itemLines . '</table>'
        . '<hr style="border:none;border-top:1px solid #eee;margin:16px 0">'
        . $discountRow
        . '<p style="margin:0;font-size:0.95rem"><strong>សរុប · Total: $' . number_format($grandTotal, 2) . '</strong></p>'
        . $notesRow;
    [$subj, $html] = render_email_template($pdo, 'order_received', [
        'summary' => $emailSummary,
        'cta_url' => 'https://teepsaa.com/dashboard-buyer/',
    ]);
    if ($html !== '') send_email($buyer['email'], $subj, $html);

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['cart_error'] = 'Something went wrong. Please try again.';
    header('Location: /cart/');
    exit;
}

$_SESSION['checkout_success'] = 'Your order has been placed. We\'ll confirm your payment and notify you within 1 hour.';
header('Location: /checkout/');
exit;
