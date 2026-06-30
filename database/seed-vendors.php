<?php
// DEV ONLY — seeds 5 vendors with businesses, products, and images
// Run at: http://localhost:8888/database/seed-vendors.php
// Delete this file after seeding.

if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Forbidden — localhost only.');
}

require __DIR__ . '/../config/db.php';

$uploadDir = __DIR__ . '/../uploads/';

// ── Pre-flight checks ──────────────────────────────────────────────────────

$alreadySeeded = (int)$pdo->query("SELECT COUNT(*) FROM vendors WHERE email LIKE '%@teepsaa.dev'")->fetchColumn();

$leafCats = $pdo->query("
    SELECT c.id, c.name FROM categories c
    WHERE c.id NOT IN (SELECT DISTINCT parent_id FROM categories WHERE parent_id IS NOT NULL)
    ORDER BY c.id ASC
")->fetchAll(PDO::FETCH_KEY_PAIR);

// ── Helpers ────────────────────────────────────────────────────────────────

function pick_cat(array $leafCats, string $keyword): int {
    foreach ($leafCats as $id => $name) {
        if (stripos($name, $keyword) !== false) return (int)$id;
    }
    return (int)array_key_first($leafCats);
}

function dl_img(string $seed, string $uploadDir): ?string {
    $url = 'https://picsum.photos/seed/' . urlencode($seed) . '/800/800';
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'teepsaa-seeder/1.0',
    ]);
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!$data || $code < 200 || $code >= 300) return null;
    $fn = bin2hex(random_bytes(8)) . '.jpg';
    file_put_contents($uploadDir . $fn, $data);
    return $fn;
}

// ── Seed data ──────────────────────────────────────────────────────────────

$vendors_data = [
    [
        'vendor'   => ['name' => 'Sok Dara',     'email' => 'sokdara@teepsaa.dev',    'phone' => '+855 12 111 001'],
        'business' => [
            'name'        => "Sok's Threads",
            'category'    => 'Clothing & Fashion',
            'description' => 'Fresh everyday streetwear for men. All items cut and printed for the Phnom Penh climate — breathable, affordable, and made to last.',
            'address'     => 'St 63, Daun Penh, Phnom Penh',
            'lat'         => 11.5658,
            'lng'         => 104.9281,
        ],
        'cat_keyword' => 'T-Shirt',
        'products' => [
            ['name' => 'Classic White Tee',   'desc' => 'Soft 100% cotton crew neck. Breathable and easy to wash — a Phnom Penh wardrobe staple.',         'price' => 12.00, 'stock' => 45, 'img' => 'teepsaa-tee-white'],
            ['name' => 'Black Graphic Tee',   'desc' => 'Relaxed fit with a bold street print. Pre-shrunk, so it stays true to size after washing.',        'price' => 14.00, 'stock' => 30, 'img' => 'teepsaa-tee-black'],
            ['name' => 'Striped Polo Shirt',  'desc' => 'Smart-casual polo in breathable piqué fabric. Works at the office or on weekends.',                'price' => 18.00, 'stock' => 20, 'img' => 'teepsaa-polo-stripe'],
            ['name' => 'Linen Button Shirt',  'desc' => 'Lightweight linen-blend in stone white. Oversized fit, easy to style with shorts or trousers.',     'price' => 22.00, 'stock' => 15, 'img' => 'teepsaa-linen-shirt'],
        ],
    ],
    [
        'vendor'   => ['name' => 'Chantha Meas', 'email' => 'chanthameas@teepsaa.dev', 'phone' => '+855 12 222 002'],
        'business' => [
            'name'        => 'Khmer Weave',
            'category'    => 'Traditional & Cultural Wear',
            'description' => 'Handcrafted Khmer traditional wear and silk garments made by local artisans at the Russian Market. Every piece tells a story.',
            'address'     => 'Toul Tom Poung (Russian Market), Phnom Penh',
            'lat'         => 11.5398,
            'lng'         => 104.9179,
        ],
        'cat_keyword' => 'Khmer',
        'products' => [
            ['name' => 'Silk Sampot Hol',        'desc' => 'Traditional woven silk sampot with ikat pattern. Hand-loomed by artisans in Siem Reap.',              'price' => 85.00, 'stock' =>  8, 'img' => 'teepsaa-sampot-hol'],
            ['name' => 'Krama Scarf — Blue',      'desc' => 'Iconic Khmer checked cotton krama. Versatile, lightweight, and proudly Cambodian.',                   'price' =>  9.00, 'stock' => 60, 'img' => 'teepsaa-krama-blue'],
            ['name' => 'Khmer Ceremony Blouse',   'desc' => 'Embroidered silk blouse for weddings and formal Khmer occasions. Available in two sizes.',             'price' => 55.00, 'stock' => 12, 'img' => 'teepsaa-ceremony-blouse'],
            ['name' => 'Indigo Batik Shirt',      'desc' => 'Natural indigo dye on hand-stamped cotton. Each one is unique — no two prints are the same.',        'price' => 28.00, 'stock' => 18, 'img' => 'teepsaa-batik-indigo'],
        ],
    ],
    [
        'vendor'   => ['name' => 'Virak Lim',    'email' => 'viraklim@teepsaa.dev',    'phone' => '+855 12 333 003'],
        'business' => [
            'name'        => 'City Steps',
            'category'    => 'Footwear',
            'description' => "Trendy sneakers and casual shoes at fair prices. Phnom Penh's most talked-about shoe spot near Orussey Market.",
            'address'     => 'Orussey Market, 7 Makara, Phnom Penh',
            'lat'         => 11.5720,
            'lng'         => 104.9190,
        ],
        'cat_keyword' => 'Sneaker',
        'products' => [
            ['name' => 'Low-Top Canvas Sneaker', 'desc' => 'Classic canvas upper with rubber sole. Available in white and black.',                                 'price' => 22.00, 'stock' => 35, 'img' => 'teepsaa-sneaker-canvas'],
            ['name' => 'Running Trainer',        'desc' => 'Breathable mesh upper with cushioned midsole for all-day comfort on Phnom Penh streets.',              'price' => 38.00, 'stock' => 20, 'img' => 'teepsaa-trainer-run'],
            ['name' => 'Slip-On Loafer',         'desc' => 'Minimalist leather-effect slip-on. Works for the office or a casual evening out.',                    'price' => 32.00, 'stock' => 15, 'img' => 'teepsaa-loafer-slip'],
        ],
    ],
    [
        'vendor'   => ['name' => 'Sreymom Chan', 'email' => 'sreymomc@teepsaa.dev',    'phone' => '+855 12 444 004'],
        'business' => [
            'name'        => 'Moon Boutique',
            'category'    => "Women's Fashion",
            'description' => "Elegant everyday dresses and women's fashion picked for the Phnom Penh lifestyle. New arrivals every week in BKK1.",
            'address'     => 'St 278, BKK1, Chamkarmon, Phnom Penh',
            'lat'         => 11.5540,
            'lng'         => 104.9220,
        ],
        'cat_keyword' => 'Dress',
        'products' => [
            ['name' => 'Floral Wrap Dress',  'desc' => 'Lightweight chiffon wrap dress with tropical floral print. Free size, adjustable tie waist.',            'price' => 29.00, 'stock' => 25, 'img' => 'teepsaa-dress-floral'],
            ['name' => 'Linen Shirt Dress',  'desc' => 'Relaxed linen blend in sand beige. Midi length — cool and effortless in the Cambodian heat.',           'price' => 35.00, 'stock' => 18, 'img' => 'teepsaa-dress-linen'],
            ['name' => 'Black Mini Dress',   'desc' => 'Classic stretch-fabric LBD. The one that pairs with absolutely everything.',                             'price' => 24.00, 'stock' => 30, 'img' => 'teepsaa-dress-black'],
            ['name' => 'Boho Maxi Dress',    'desc' => 'Tiered bohemian maxi with elastic waist. Lightweight for long hot days.',                                'price' => 42.00, 'stock' => 12, 'img' => 'teepsaa-dress-boho'],
        ],
    ],
    [
        'vendor'   => ['name' => 'Bunna Pech',   'email' => 'bunnapech@teepsaa.dev',   'phone' => '+855 12 555 005'],
        'business' => [
            'name'        => 'Golden Bag Co.',
            'category'    => 'Accessories',
            'description' => 'Handstitched bags and leather accessories crafted by skilled local makers right here in Phnom Penh. Built to last.',
            'address'     => 'Kandal Market, Daun Penh, Phnom Penh',
            'lat'         => 11.5680,
            'lng'         => 104.9285,
        ],
        'cat_keyword' => 'Bag',
        'products' => [
            ['name' => 'Tan Leather Tote',    'desc' => 'Spacious veg-tan tote. Fits a 15" laptop. Hand-stitched with solid brass hardware.',                    'price' => 68.00, 'stock' => 10, 'img' => 'teepsaa-bag-tote'],
            ['name' => 'Crossbody Mini Bag',  'desc' => 'Compact dark brown crossbody with adjustable strap. Fits phone, keys, and wallet perfectly.',           'price' => 35.00, 'stock' => 22, 'img' => 'teepsaa-bag-crossbody'],
            ['name' => 'Woven Rattan Clutch', 'desc' => 'Handwoven rattan with cotton lining. A natural statement piece for evenings out.',                      'price' => 20.00, 'stock' => 18, 'img' => 'teepsaa-clutch-rattan'],
        ],
    ],
];

// ── Run seeder ─────────────────────────────────────────────────────────────

$log    = [];
$imgLog = [];

if (!$alreadySeeded && !empty($leafCats)) {
    $password = password_hash('password123', PASSWORD_DEFAULT);

    foreach ($vendors_data as $vd) {
        $v = $vd['vendor'];
        $b = $vd['business'];

        $pdo->prepare('INSERT INTO vendors (name, email, password, phone, email_verified_at) VALUES (?, ?, ?, ?, NOW())')
            ->execute([$v['name'], $v['email'], $password, $v['phone']]);
        $vendorId = (int)$pdo->lastInsertId();

        $pdo->prepare('INSERT INTO businesses (user_id, name, category, description, address, lat, lng, approved) VALUES (?, ?, ?, ?, ?, ?, ?, 1)')
            ->execute([$vendorId, $b['name'], $b['category'], $b['description'], $b['address'], $b['lat'], $b['lng']]);
        $bizId = (int)$pdo->lastInsertId();

        $catId    = pick_cat($leafCats, $vd['cat_keyword']);
        $products = [];

        foreach ($vd['products'] as $p) {
            $pdo->prepare('INSERT INTO products (business_id, category_id, name, description, price, stock, active, delivery_method) VALUES (?, ?, ?, ?, ?, ?, 1, \'bike\')')
                ->execute([$bizId, $catId, $p['name'], $p['desc'], $p['price'], $p['stock']]);
            $prodId = (int)$pdo->lastInsertId();

            $fn = dl_img($p['img'], $uploadDir);
            if ($fn) {
                $pdo->prepare('INSERT INTO product_photos (product_id, filename, sort_order, is_primary) VALUES (?, ?, 0, 1)')
                    ->execute([$prodId, $fn]);
                $imgLog[] = ['product' => $p['name'], 'file' => $fn, 'ok' => true];
            } else {
                $imgLog[] = ['product' => $p['name'], 'file' => null, 'ok' => false];
            }

            $products[] = ['name' => $p['name'], 'price' => $p['price'], 'img' => $fn];
        }

        $log[] = ['vendor' => $v, 'business' => $b, 'cat' => $leafCats[$catId] ?? '?', 'products' => $products];
    }
}

$imgOk   = count(array_filter($imgLog, fn($i) => $i['ok']));
$imgFail = count($imgLog) - $imgOk;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Seed vendors — teepsaa</title>
<style>
  body { font-family: -apple-system, sans-serif; max-width: 860px; margin: 2rem auto; padding: 0 1.5rem; color: #111; line-height: 1.5; }
  h1 { font-size: 1.4rem; margin-bottom: 0.25rem; }
  .subtitle { color: #6b7280; font-size: 0.9rem; margin-bottom: 2rem; }
  .alert { padding: 0.8rem 1.1rem; border-radius: var(--radius-sm); margin-bottom: 1.5rem; font-size: 0.9rem; }
  .alert-warn  { background: #fef3c7; border: 1px solid #fcd34d; color: #92400e; }
  .alert-error { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; }
  .alert-ok    { background: #f0fdf4; border: 1px solid #86efac; color: #166534; }
  .vendor-block { border: 1px solid #e5e7eb; border-radius: var(--radius); margin-bottom: 1.25rem; overflow: hidden; }
  .vendor-head { background: #f9fafb; padding: 0.75rem 1.1rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e5e7eb; }
  .vendor-name { font-weight: 700; font-size: 0.95rem; }
  .vendor-email { font-size: 0.8rem; color: #6b7280; }
  .vendor-body { padding: 0.9rem 1.1rem; }
  .biz-name { font-weight: 600; margin-bottom: 0.5rem; }
  .product-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.3rem; }
  .product-list li { font-size: 0.875rem; display: flex; justify-content: space-between; color: #374151; }
  .product-list li .img-ok  { color: #16a34a; font-size: 0.78rem; }
  .product-list li .img-fail { color: #dc2626; font-size: 0.78rem; }
  .product-price { color: #6b7280; }
  .creds { background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: var(--radius-sm); padding: 0.9rem 1.1rem; margin-top: 2rem; font-size: 0.875rem; }
  .creds h2 { font-size: 0.95rem; margin: 0 0 0.5rem; }
  .creds code { background: #e2e8f0; padding: 0.15rem 0.4rem; border-radius: var(--radius-sm); font-size: 0.85rem; }
  .stats { display: flex; gap: 1.5rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
  .stat { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: var(--radius-sm); padding: 0.65rem 1rem; }
  .stat-num { font-size: 1.4rem; font-weight: 700; }
  .stat-label { font-size: 0.75rem; color: #6b7280; }
</style>
</head>
<body>
<h1>Vendor seeder — teepsaa dev</h1>
<p class="subtitle">Local development only. Delete this file when done.</p>

<?php if ($alreadySeeded): ?>
<div class="alert alert-warn">
    Already seeded — found <?= $alreadySeeded ?> vendor(s) with <code>@teepsaa.dev</code> emails.
    To re-seed, delete those records from the database first, then reload this page.
</div>
<?php elseif (empty($leafCats)): ?>
<div class="alert alert-error">
    <strong>No leaf categories found.</strong> Run <code>database/seed-categories.sql</code> in phpMyAdmin first, then reload this page.
</div>
<?php else: ?>
<div class="alert alert-ok">
    Seeded successfully — <?= count($log) ?> vendors, <?= array_sum(array_map(fn($v) => count($v['products']), $log)) ?> products.
    <?php if ($imgFail > 0): ?>
        <strong><?= $imgFail ?> image(s) failed to download</strong> (picsum.photos may be unavailable — products will show no image).
    <?php endif; ?>
</div>

<div class="stats">
    <div class="stat"><div class="stat-num"><?= count($log) ?></div><div class="stat-label">Vendors created</div></div>
    <div class="stat"><div class="stat-num"><?= count($log) ?></div><div class="stat-label">Businesses approved</div></div>
    <div class="stat"><div class="stat-num"><?= array_sum(array_map(fn($v) => count($v['products']), $log)) ?></div><div class="stat-label">Products added</div></div>
    <div class="stat"><div class="stat-num"><?= $imgOk ?>/<?= $imgOk + $imgFail ?></div><div class="stat-label">Images downloaded</div></div>
</div>

<?php foreach ($log as $entry): ?>
<div class="vendor-block">
    <div class="vendor-head">
        <div>
            <div class="vendor-name"><?= htmlspecialchars($entry['business']['name']) ?></div>
            <div class="vendor-email"><?= htmlspecialchars($entry['vendor']['email']) ?> &middot; <?= htmlspecialchars($entry['vendor']['name']) ?></div>
        </div>
        <div style="font-size:0.8rem;color:#6b7280">Category: <?= htmlspecialchars($entry['cat']) ?></div>
    </div>
    <div class="vendor-body">
        <ul class="product-list">
            <?php foreach ($entry['products'] as $p): ?>
            <li>
                <span><?= htmlspecialchars($p['name']) ?> <span class="product-price">$<?= number_format($p['price'], 2) ?></span></span>
                <?php if ($p['img']): ?>
                <span class="img-ok">image ok</span>
                <?php else: ?>
                <span class="img-fail">no image</span>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endforeach; ?>

<div class="creds">
    <h2>Login credentials (all vendors)</h2>
    <p>Password: <code>password123</code></p>
    <p>Login at <a href="/login-vendor/">/login-vendor/</a></p>
    <?php foreach ($log as $entry): ?>
    <p style="margin:0.2rem 0"><code><?= htmlspecialchars($entry['vendor']['email']) ?></code> — <?= htmlspecialchars($entry['business']['name']) ?></p>
    <?php endforeach; ?>
</div>

<?php endif; ?>

</body>
</html>
