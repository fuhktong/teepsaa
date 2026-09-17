<?php
// Everything the app needs to draw the add/edit product form, in one call:
// the vendor's sellable businesses, the pickable categories, the royalty
// numbers behind the payout hint, and — when an id is given — the product's
// current values and its variants.
//
// The website builds all of this inside products/index.php while rendering the
// form. The app has no server-rendered page, so it has to ask for it.
//
// image_variant() arrives with this: api.php requires db.php, which requires
// upload.php and currency.php. Adding a require for either here is a redeclare
// and a 500 before any code runs.
require __DIR__ . '/../../config/api.php';

api_require_method('GET');
$vendor = api_require_vendor($pdo);
$userId = (int)$vendor['id'];

// ── The businesses a product may be listed under ─────────────────────
// Sellable only: approved and not suspended. Same rule as products.php.
$stmt = $pdo->prepare('
    SELECT id, name, royalty_add_on, royalty_waived
      FROM businesses
     WHERE user_id = ? AND approved = 1 AND suspended = 0
     ORDER BY name ASC
');
$stmt->execute([$userId]);
$businesses = $stmt->fetchAll();
$bizIds = array_map('intval', array_column($businesses, 'id'));

// Why the most recent business is looked up separately: an empty list above has
// four different meanings and the vendor needs to be told which one.
$stmt = $pdo->prepare('
    SELECT approved, suspended FROM businesses
     WHERE user_id = ? AND deleted_at IS NULL
     ORDER BY created_at DESC LIMIT 1
');
$stmt->execute([$userId]);
$latest = $stmt->fetch();

if (!empty($bizIds))                     { $businessState = 'ok'; }
elseif (!$latest)                        { $businessState = 'none'; }
elseif ((int)$latest['suspended'] === 1) { $businessState = 'suspended'; }
elseif ((int)$latest['approved'] === -1) { $businessState = 'rejected'; }
else                                     { $businessState = 'pending'; }

if ($businessState !== 'ok') {
    api_json([
        'business_state' => $businessState,
        'businesses'     => [],
        'categories'     => [],
        'penalty_rate'   => 0,
        'product'        => null,
        'variants'       => [],
    ]);
}

// ── Categories ───────────────────────────────────────────────────────
// Only leaves can hold a product — the same rule save.php enforces. The app
// gets a flat list with the full path as the label ("Clothing > Dresses"), so
// two leaves that share a name are still tellable apart. The website shows a
// cascade of dropdowns instead; that is a layout choice, not a data one.
$allCats = $pdo->query('SELECT id, parent_id, name, name_km, royalty_rate FROM categories ORDER BY name ASC')->fetchAll();

$byId      = [];
$parentIds = [];
foreach ($allCats as $c) {
    $byId[(int)$c['id']] = $c;
    if ($c['parent_id'] !== null) $parentIds[(int)$c['parent_id']] = true;
}

function cat_path(array $byId, int $id, string $field): string {
    $parts = [];
    $guard = 0;
    while (isset($byId[$id]) && $guard++ < 10) {
        $name = trim((string)($byId[$id][$field] ?? ''));
        // A category with no Khmer name would otherwise put an empty segment in
        // the middle of the path. Fall back to English for that segment only.
        if ($name === '') $name = (string)$byId[$id]['name'];
        array_unshift($parts, $name);
        $id = (int)($byId[$id]['parent_id'] ?? 0);
    }
    return implode(' > ', $parts);
}

$categories = [];
foreach ($allCats as $c) {
    $id = (int)$c['id'];
    if (isset($parentIds[$id])) continue;   // has children, so not pickable
    $categories[] = [
        'id'           => $id,
        'name'         => $c['name'],
        'name_km'      => $c['name_km'],
        'path'         => cat_path($byId, $id, 'name'),
        'path_km'      => cat_path($byId, $id, 'name_km'),
        'royalty_rate' => (float)$c['royalty_rate'],
    ];
}
usort($categories, fn($a, $b) => strcasecmp($a['path'], $b['path']));

// ── The royalty penalty behind the payout hint ───────────────────────
$ph = implode(',', array_fill(0, count($bizIds), '?'));
$stmt = $pdo->prepare("
    SELECT rate_increase, end_date
      FROM vendor_penalties
     WHERE business_id IN ($ph)
       AND cleared_at IS NULL
       AND start_date <= CURDATE()
       AND (end_date IS NULL OR end_date >= CURDATE())
");
$stmt->execute($bizIds);
$penalties = $stmt->fetchAll();

$penaltyRate       = 0.0;
$penaltyExpiry     = null;
$penaltyIndefinite = false;
foreach ($penalties as $p) {
    $penaltyRate += (float)$p['rate_increase'];
    if ($p['end_date'] === null) { $penaltyIndefinite = true; }
    elseif ($penaltyExpiry === null || $p['end_date'] < $penaltyExpiry) { $penaltyExpiry = $p['end_date']; }
}

// ── The product being edited, if any ─────────────────────────────────
$product  = null;
$variants = [];
$wantedId = (int)($_GET['id'] ?? 0);

if ($wantedId > 0) {
    $stmt = $pdo->prepare("
        SELECT p.id, p.public_id, p.business_id, p.category_id, p.name, p.name_km,
               p.description, p.description_km, p.price, p.stock, p.delivery_method,
               p.active, p.archived, p.sale_percent, p.sale_ends_at, p.royalty_add_on,
               pp.filename AS photo
          FROM products p
          LEFT JOIN product_photos pp ON pp.product_id = p.id AND pp.is_primary = 1
         WHERE p.id = ? AND p.business_id IN ($ph)
    ");
    $stmt->execute(array_merge([$wantedId], $bizIds));
    $row = $stmt->fetch();

    if (!$row) api_json(['error' => 'not_found'], 404);

    $product = [
        'id'              => (int)$row['id'],
        'public_id'       => $row['public_id'],
        'business_id'     => (int)$row['business_id'],
        'category_id'     => $row['category_id'] === null ? null : (int)$row['category_id'],
        'name'            => $row['name'],
        'name_km'         => $row['name_km'],
        'description'     => $row['description'],
        'description_km'  => $row['description_km'],
        // A string, not a float. The form shows it in a text field and a float
        // would arrive as 20 where the vendor typed 20.00.
        'price'           => number_format((float)$row['price'], 2, '.', ''),
        'stock'           => (int)$row['stock'],
        'delivery_method' => $row['delivery_method'],
        'active'          => (bool)$row['active'],
        'archived'        => (bool)$row['archived'],
        'sale_percent'    => $row['sale_percent'] === null ? null : (int)$row['sale_percent'],
        'sale_ends_at'    => $row['sale_ends_at'],
        'royalty_add_on'  => (float)$row['royalty_add_on'],
        // Absolute, not the '/uploads/...' the website uses. The app runs from
        // https://localhost, so a root-relative src would point the phone at
        // itself and the image would silently never load.
        'photo'           => $row['photo'] ? 'https://teepsaa.com' . image_variant($row['photo']) : null,
    ];

    $vStmt = $pdo->prepare('
        SELECT id, label, label_km, stock, price_override
          FROM product_variants
         WHERE product_id = ?
         ORDER BY sort_order ASC, id ASC
    ');
    $vStmt->execute([(int)$row['id']]);
    foreach ($vStmt->fetchAll() as $v) {
        $variants[] = [
            'id'       => (int)$v['id'],
            'label'    => $v['label'],
            'label_km' => $v['label_km'],
            'stock'    => (int)$v['stock'],
            'price'    => $v['price_override'] === null ? null : number_format((float)$v['price_override'], 2, '.', ''),
        ];
    }
}

api_json([
    'business_state'     => 'ok',
    'businesses'         => array_map(fn($b) => [
        'id'             => (int)$b['id'],
        'name'           => $b['name'],
        'royalty_add_on' => (float)$b['royalty_add_on'],
        'royalty_waived' => (bool)$b['royalty_waived'],
    ], $businesses),
    'categories'         => $categories,
    'penalty_rate'       => $penaltyRate,
    'penalty_expiry'     => $penaltyExpiry,
    'penalty_indefinite' => $penaltyIndefinite,
    'product'            => $product,
    'variants'           => $variants,
]);
