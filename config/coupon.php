<?php
// Shared coupon validation — used by api/coupon/validate.php (live preview),
// checkout/apply-coupon.php (session apply), and checkout/confirm.php
// (authoritative re-check at order placement). Keeping one implementation
// avoids the preview and the charge ever disagreeing.
//
// A coupon is either sitewide (business_id NULL, admin-created, platform
// absorbs the discount) or vendor-owned (business_id set, only applies to
// that vendor's items in the cart, and comes out of that vendor's own
// payout). $subtotalsByBusiness is [business_id => subtotal] for every
// vendor group currently in the buyer's cart.
if (!function_exists('validate_coupon')) {
    function validate_coupon(PDO $pdo, string $code, array $subtotalsByBusiness, int $buyerId): array {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return ['valid' => false, 'message' => 'Enter a code.'];
        }

        $stmt = $pdo->prepare('SELECT * FROM coupons WHERE code = ?');
        $stmt->execute([$code]);
        $c = $stmt->fetch();

        if (!$c || !$c['active']) {
            return ['valid' => false, 'message' => 'Invalid code.'];
        }
        if ($c['starts_at'] && strtotime($c['starts_at']) > time()) {
            return ['valid' => false, 'message' => 'This code is not active yet.'];
        }
        if ($c['expires_at'] && strtotime($c['expires_at']) < time()) {
            return ['valid' => false, 'message' => 'This code has expired.'];
        }
        if ($c['max_uses'] !== null && (int)$c['used_count'] >= (int)$c['max_uses']) {
            return ['valid' => false, 'message' => 'This code has reached its usage limit.'];
        }

        $businessId = $c['business_id'] !== null ? (int)$c['business_id'] : null;
        if ($businessId !== null) {
            if (!isset($subtotalsByBusiness[$businessId])) {
                return ['valid' => false, 'message' => 'This code only applies to items from a specific shop, which isn\'t in your cart.'];
            }
            $scopeSubtotal = (float)$subtotalsByBusiness[$businessId];
        } else {
            $scopeSubtotal = array_sum($subtotalsByBusiness);
        }

        if ($scopeSubtotal < (float)$c['min_order']) {
            return ['valid' => false, 'message' => 'Minimum order of $' . number_format((float)$c['min_order'], 2) . ' required.'];
        }

        $usedStmt = $pdo->prepare('SELECT COUNT(*) FROM coupon_uses WHERE coupon_id = ? AND buyer_id = ?');
        $usedStmt->execute([$c['id'], $buyerId]);
        if ((int)$usedStmt->fetchColumn() > 0) {
            return ['valid' => false, 'message' => 'You have already used this code.'];
        }

        $discount = $c['type'] === 'percent'
            ? $scopeSubtotal * ((float)$c['value'] / 100)
            : (float)$c['value'];
        $discount = round(min($discount, $scopeSubtotal), 2);

        $label = $c['type'] === 'percent'
            ? rtrim(rtrim(number_format((float)$c['value'], 2), '0'), '.') . '% off'
            : '$' . number_format((float)$c['value'], 2) . ' off';

        return [
            'valid'       => true,
            'coupon'      => $c,
            'business_id' => $businessId,
            'discount'    => $discount,
            'message'     => 'Code applied — ' . $label . '.',
        ];
    }
}
