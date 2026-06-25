<?php
// DEV ONLY — seeds 4 seasonal banners with images from picsum
// Run at: http://localhost:8888/database/seed-banners.php
// Delete this file after seeding.

if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Forbidden — localhost only.');
}

require __DIR__ . '/../config/db.php';

$uploadDir = __DIR__ . '/../uploads/';

// ── Pre-flight ─────────────────────────────────────────────────────────────

$existing = (int) $pdo->query("SELECT COUNT(*) FROM banners")->fetchColumn();
if ($existing > 0) {
    echo '<p>Banners already seeded (' . $existing . ' rows). Delete existing banners first.</p>';
    exit;
}

// ── Image downloader ───────────────────────────────────────────────────────

function dl_banner(string $seed, string $uploadDir): ?string {
    $url = 'https://picsum.photos/seed/' . urlencode($seed) . '/1200/400';
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
    $fn = 'banner_' . bin2hex(random_bytes(8)) . '.jpg';
    file_put_contents($uploadDir . $fn, $data);
    return $fn;
}

// ── Banners ────────────────────────────────────────────────────────────────

$banners = [
    [
        'seed'     => 'spring-flowers-bloom',
        'title'    => 'Spring arrivals',
        'subtitle' => 'Fresh finds from local Phnom Penh makers',
        'link_url' => '/search/',
    ],
    [
        'seed'     => 'summer-tropical-bright',
        'title'    => 'Summer essentials',
        'subtitle' => 'Everything you need for the hot season',
        'link_url' => '/search/',
    ],
    [
        'seed'     => 'autumn-warm-harvest',
        'title'    => 'Autumn collection',
        'subtitle' => 'New styles landing every week',
        'link_url' => '/search/',
    ],
    [
        'seed'     => 'winter-cool-cozy',
        'title'    => 'Cool season picks',
        'subtitle' => 'Shop local for the cooler months',
        'link_url' => '/search/',
    ],
];

$stmt = $pdo->prepare('
    INSERT INTO banners (title, subtitle, link_url, image_filename, sort_order, active)
    VALUES (?, ?, ?, ?, ?, 1)
');

echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>body{font-family:sans-serif;max-width:700px;margin:2rem auto;padding:0 1rem}
img{width:100%;border-radius:8px;margin:0.5rem 0}
.ok{color:#15803d}.err{color:#dc2626}</style></head><body>';
echo '<h2>Banner Seeder</h2>';

foreach ($banners as $i => $b) {
    echo '<h3>' . htmlspecialchars($b['title']) . '</h3>';
    $fn = dl_banner($b['seed'], $uploadDir);
    if (!$fn) {
        echo '<p class="err">Failed to download image for seed: ' . htmlspecialchars($b['seed']) . '</p>';
        continue;
    }
    $stmt->execute([$b['title'], $b['subtitle'], $b['link_url'], $fn, $i + 1]);
    echo '<p class="ok">Inserted — ' . htmlspecialchars($fn) . '</p>';
    echo '<img src="/uploads/' . htmlspecialchars($fn) . '" alt="">';
}

echo '<hr><p class="ok"><strong>Done.</strong> Delete this file when finished.</p>';
echo '</body></html>';
