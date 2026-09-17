<?php
// GET /api/v1/coupons.php
//
// The vendor's discount codes — the "Coupons" tab of products/index.php.
//
// Which business they belong to: the website reads and writes coupons for
// $businesses[0], the alphabetically-first sellable business, and its
// coupon-action.php picks the same one with ORDER BY name ASC LIMIT 1. There
// is no business picker on that tab. This endpoint does exactly the same and
// says which business it landed on, so the app can name it on screen rather
// than leave a vendor with two shops guessing whose codes these are.
//
// Everything a coupon row needs to be drawn is computed here — the discount
// label, whether it has expired, whether it can still be deleted — because the
// same three rules decide what the website prints and what its buttons do, and
// two copies of them would drift apart.
require __DIR__ . '/../../config/api.php';

api_require_method('GET');
$vendor = api_require_vendor($pdo);
$userId = (int)$vendor['id'];

// Sellable only: approved and not suspended. Same rule as products.php.
$stmt = $pdo->prepare('
    SELECT id, name FROM businesses
     WHERE user_id = ? AND approved = 1 AND suspended = 0
     ORDER BY name ASC
');
$stmt->execute([$userId]);
$businesses = $stmt->fetchAll();

// An empty list above has four different meanings and the vendor needs to be
// told which one — the same four-way split products.php makes.
$stmt = $pdo->prepare('
    SELECT approved, suspended FROM businesses
     WHERE user_id = ? AND deleted_at IS NULL
     ORDER BY created_at DESC LIMIT 1
');
$stmt->execute([$userId]);
$latest = $stmt->fetch();

if (!empty($businesses))                 { $businessState = 'ok'; }
elseif (!$latest)                        { $businessState = 'none'; }
elseif ((int)$latest['suspended'] === 1) { $businessState = 'suspended'; }
elseif ((int)$latest['approved'] === -1) { $businessState = 'rejected'; }
else                                     { $businessState = 'pending'; }

if ($businessState !== 'ok') {
    api_json([
        'business_state' => $businessState,
        'business'       => null,
        'coupons'        => [],
    ]);
}

$business = $businesses[0];

$stmt = $pdo->prepare('
    SELECT id, code, type, value, min_order, max_uses, used_count,
           starts_at, expires_at, active
      FROM coupons
     WHERE business_id = ?
     ORDER BY created_at DESC
');
$stmt->execute([(int)$business['id']]);
$rows = $stmt->fetchAll();

$now = time();
$coupons = [];

foreach ($rows as $r) {
    $expired = $r['expires_at'] && strtotime($r['expires_at']) < $now;
    $value   = (float)$r['value'];

    // Two more ways a coupon can be switched on and still do nothing at
    // checkout: it has not started yet, or it has been redeemed as many times
    // as it was allowed. config/coupon.php turns buyers away for both. The
    // website's table shows neither, so a vendor sees "Active" on a code
    // nobody can use; the app says which it is.
    $notStarted   = $r['starts_at'] && strtotime($r['starts_at']) > $now;
    $limitReached = $r['max_uses'] !== null && (int)$r['used_count'] >= (int)$r['max_uses'];

    $coupons[] = [
        'id'    => (int)$r['id'],
        'code'  => $r['code'],
        'type'  => $r['type'],
        // Strings, not floats. These land in text boxes, and a float would
        // arrive as 10 where the vendor typed 10.00.
        'value'     => number_format($value, 2, '.', ''),
        'min_order' => number_format((float)$r['min_order'], 2, '.', ''),
        // The website trims a percent to "10%" but keeps two decimals on a
        // dollar amount, because 10.5% is a real discount and $10.5 is not a
        // real price. Same rule, computed once.
        'discount_label' => $r['type'] === 'percent'
            ? rtrim(rtrim(number_format($value, 2), '0'), '.') . '%'
            : '$' . number_format($value, 2),
        'max_uses'   => $r['max_uses'] !== null ? (int)$r['max_uses'] : null,
        'used_count' => (int)$r['used_count'],
        // Dates only. The times stored alongside them are always 00:00:00 and
        // 23:59:59 — coupon-action.php puts them there — so sending the time
        // would only invite the app to show a meaningless midnight.
        'starts_at'  => $r['starts_at']  ? date('Y-m-d', strtotime($r['starts_at']))  : null,
        'expires_at' => $r['expires_at'] ? date('Y-m-d', strtotime($r['expires_at'])) : null,
        'active'     => (int)$r['active'] === 1,
        'expired'       => (bool)$expired,
        'not_started'   => (bool)$notStarted,
        'limit_reached' => (bool)$limitReached,
        // An expired coupon cannot be edited or switched on and off; a used one
        // can never be deleted, only deactivated, because coupon_uses rows
        // cascade and an order would lose the discount it was given.
        'can_edit'   => !$expired,
        'can_delete' => (int)$r['used_count'] === 0,
    ];
}

api_json([
    'business_state' => $businessState,
    'business'       => ['id' => (int)$business['id'], 'name' => $business['name']],
    'coupons'        => $coupons,
]);
