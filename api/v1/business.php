<?php
// Everything the app's Business screen draws, in one call: the shop's details,
// its categories, its banner, the storefront layout, the address, and the bank
// details payouts are sent to.
//
// The website spreads this across business-vendor/index.php and four action
// files. The app has no server-rendered page, so it asks for the lot at once.
//
// One thing this does that the website's page does not: it says plainly which
// state the shop is in. On the website the state is inferred from which warning
// box happens to be rendered. Here it is a field, so the app cannot get it
// wrong.
//
// image_variant() arrives with this: api.php requires db.php, which requires
// upload.php. Requiring either here is a redeclare and a 500 before any code
// runs.
require __DIR__ . '/../../config/api.php';

api_require_method('GET');
$vendor = api_require_vendor($pdo);
$userId = (int)$vendor['id'];

$stmt = $pdo->prepare('
    SELECT id, name, name_km, category, description, description_km,
           house_number, address, address_notes, khan, sangkat, city, lat, lng,
           banner, approved, rejection_reason, suspended, suspension_reason
      FROM businesses
     WHERE user_id = ? AND deleted_at IS NULL
     ORDER BY created_at DESC
     LIMIT 1
');
$stmt->execute([$userId]);
$business = $stmt->fetch();

// No shop at all is its own answer: there is nothing to edit and nothing to
// show, and the app sends the vendor to the website to apply for one.
if (!$business) {
    api_json([
        'state'      => 'none',
        'reason'     => null,
        'business'   => null,
        'address'    => null,
        'categories' => [],
        'khans'      => new stdClass(),
        'cities'     => [],
        'storefront' => null,
        'bank'       => null,
        'limits'     => null,
    ]);
}

// Suspension outranks approval: a suspended shop that was approved is still
// off the marketplace, and that is the fact the vendor needs first.
if ((int)$business['suspended'] === 1) {
    $state  = 'suspended';
    $reason = $business['suspension_reason'] ?: null;
} elseif ((int)$business['approved'] === -1) {
    $state  = 'rejected';
    $reason = $business['rejection_reason'] ?: null;
} elseif ((int)$business['approved'] === 0) {
    $state  = 'pending';
    $reason = null;
} else {
    $state  = 'approved';
    $reason = null;
}

// ── Categories ───────────────────────────────────────────────────────
// A shop may sit in any category, at any depth — unlike a product, which only
// goes on a leaf. The stored value is a comma-separated list of English names,
// so `name` is what goes back on a save and `label` is the full path the
// vendor reads ("Clothing > Dresses"), which is the only way to tell two
// leaves with the same name apart.
$allCats = $pdo->query('SELECT id, parent_id, name, name_km FROM categories ORDER BY name ASC')->fetchAll();

$byId = [];
foreach ($allCats as $c) {
    $byId[(int)$c['id']] = $c;
}

function biz_cat_path(array $byId, int $id): string {
    $parts = [];
    $guard = 0;
    while (isset($byId[$id]) && $guard++ < 10) {
        $parts[] = (string)$byId[$id]['name'];
        $parent  = $byId[$id]['parent_id'];
        if ($parent === null) break;
        $id = (int)$parent;
    }
    return implode(' > ', array_reverse($parts));
}

$categories = [];
foreach ($allCats as $c) {
    $categories[] = [
        'name'  => $c['name'],
        'label' => biz_cat_path($byId, (int)$c['id']),
    ];
}
usort($categories, fn($a, $b) => strcasecmp($a['label'], $b['label']));

// The shop's current picks, in the order they were saved. A name that no
// longer matches a category is dropped rather than shown as a dead chip — the
// website's save does the same on the way in.
$known  = array_column($categories, 'label', 'name');
$chosen = [];
foreach (array_filter(array_map('trim', explode(',', (string)$business['category']))) as $name) {
    if (isset($known[$name])) {
        $chosen[] = ['name' => $name, 'label' => $known[$name]];
    }
}

// ── Storefront layout ────────────────────────────────────────────────
$stmt = $pdo->prepare('
    SELECT id, name, is_featured, storefront_order
      FROM products
     WHERE business_id = ? AND active = 1 AND archived = 0
     ORDER BY name ASC
');
$stmt->execute([(int)$business['id']]);
$sfRows = $stmt->fetchAll();

$featured = 0;
$slotMap  = [];
$sfList   = [];
foreach ($sfRows as $p) {
    $sfList[] = ['id' => (int)$p['id'], 'name' => $p['name']];
    if ((int)$p['is_featured'] === 1)   $featured = (int)$p['id'];
    if ($p['storefront_order'] !== null) $slotMap[(int)$p['storefront_order']] = (int)$p['id'];
}
ksort($slotMap);

// ── Address ──────────────────────────────────────────────────────────
$locations = require __DIR__ . '/../../config/phnom-penh-locations.php';
$cities    = require __DIR__ . '/../../config/cities.php';

$addrParts = array_filter([
    trim(($business['house_number'] ?? '') . ' ' . ($business['address'] ?? '')),
    $business['sangkat'] ?? '',
    $business['khan'] ?? '',
    $business['city'] ?: ($cities[0] ?? ''),
]);

$hasPin = $business['lat'] !== null && $business['lng'] !== null;

// ── Bank ─────────────────────────────────────────────────────────────
$stmt = $pdo->prepare('SELECT aba_qr, aba_account_name FROM vendors WHERE id = ?');
$stmt->execute([$userId]);
$bank = $stmt->fetch() ?: ['aba_qr' => null, 'aba_account_name' => null];

api_json([
    'state'  => $state,
    'reason' => $reason,

    'business' => [
        'id'             => (int)$business['id'],
        'name'           => $business['name'],
        'name_km'        => $business['name_km'] ?: '',
        'description'    => $business['description'] ?: '',
        'description_km' => $business['description_km'] ?: '',
        'banner'         => $business['banner'] ? 'https://teepsaa.com' . image_variant($business['banner'], 'w1200') : null,
        'chosen'         => $chosen,
    ],

    'address' => [
        'house_number' => $business['house_number'] ?: '',
        'street'       => $business['address'] ?: '',
        'notes'        => $business['address_notes'] ?: '',
        'khan'         => $business['khan'] ?: '',
        'sangkat'      => $business['sangkat'] ?: '',
        'city'         => $business['city'] ?: ($cities[0] ?? ''),
        // Written out for the app's summary line so the two never word it
        // differently — the website builds the same string in its own page.
        'line'         => implode(', ', $addrParts),
        'has_address'  => !empty($business['address']) || !empty($business['khan']),
        'has_pin'      => $hasPin,
        'pin'          => $hasPin
            ? number_format((float)$business['lat'], 5, '.', '') . ', ' . number_format((float)$business['lng'], 5, '.', '')
            : null,
    ],

    'categories' => $categories,
    'khans'      => $locations,
    'cities'     => array_values($cities),

    'storefront' => [
        'products' => $sfList,
        'featured' => $featured,
        'slots'    => array_values($slotMap),
    ],

    'bank' => [
        'account_name' => $bank['aba_account_name'] ?: '',
        'qr'           => $bank['aba_qr'] ? 'https://teepsaa.com/uploads/' . $bank['aba_qr'] : null,
    ],

    // The app shows counts and refuses over-long values before sending, but
    // these are the server's numbers so one place decides them.
    'limits' => [
        'description'  => 160,
        'name'         => 255,
        'account_name' => 100,
        // businesses.category is VARCHAR(100) and holds the joined list, so
        // this is a real ceiling on how many categories fit, not a style rule.
        'category'     => 100,
    ],
]);
