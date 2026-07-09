<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/admin-auth.php';
require __DIR__ . '/../config/mapbox.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: /login-admin/');
    exit;
}

admin_require('buyer-map');

$pendingVendorCount = (int)$pdo->query("SELECT COUNT(*) FROM businesses WHERE approved = 0")->fetchColumn();
$refundCount        = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'refund_requested'")->fetchColumn();
$pendingPayoutCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered' AND delivered_at IS NOT NULL AND delivered_at < DATE_SUB(NOW(), INTERVAL " . PAYOUT_WINDOW_SECONDS . " SECOND)")->fetchColumn();

// Exact buyer pins (buyers with lat/lng set)
$buyerRows = $pdo->query("
    SELECT name, email, house_number, address, sangkat, khan, lat, lng
    FROM buyers
    WHERE lat IS NOT NULL AND lng IS NOT NULL
    ORDER BY created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Khan bubble aggregation
$khanRows = $pdo->query("
    SELECT khan, COUNT(*) AS cnt
    FROM buyers
    WHERE khan IS NOT NULL AND khan != ''
    GROUP BY khan
    ORDER BY cnt DESC
")->fetchAll(PDO::FETCH_ASSOC);

$totalBuyers   = (int)$pdo->query("SELECT COUNT(*) FROM buyers")->fetchColumn();
$noKhanBuyers  = (int)$pdo->query("SELECT COUNT(*) FROM buyers WHERE khan IS NULL OR khan = ''")->fetchColumn();
$withKhanCount = $totalBuyers - $noKhanBuyers;

// Approximate centroids for each Phnom Penh Khan
$khanCentroids = [
    'Doun Penh'       => [104.9220, 11.5700],
    'Prampir Makara'  => [104.9105, 11.5675],
    'Chamkar Mon'     => [104.9215, 11.5530],
    'Boeng Keng Kang' => [104.9300, 11.5555],
    'Tuol Kouk'       => [104.8975, 11.5760],
    'Mean Chey'       => [104.9155, 11.5280],
    'Chbar Ampov'     => [104.9510, 11.5350],
    'Russey Keo'      => [104.9065, 11.6100],
    'Sen Sok'         => [104.8810, 11.5960],
    'Pou Senchey'     => [104.8600, 11.5210],
    'Chroy Changvar'  => [104.9550, 11.5950],
    'Dangkao'         => [104.9080, 11.4870],
    'Prek Pnov'       => [104.8760, 11.6510],
    'Kamboul'         => [104.8450, 11.4520],
];

$khanData = [];
foreach ($khanRows as $row) {
    $khan = $row['khan'];
    if (isset($khanCentroids[$khan])) {
        $khanData[] = [
            'khan' => $khan,
            'cnt'  => (int)$row['cnt'],
            'lng'  => $khanCentroids[$khan][0],
            'lat'  => $khanCentroids[$khan][1],
        ];
    }
}

$maxCount = $khanData ? max(array_column($khanData, 'cnt')) : 1;
$adminSection = 'marketing';
$adminTab     = 'buyer-map';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Buyer Map</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/admin/admin.css">
    <link href="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css" rel="stylesheet">
    <script src="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js"></script>
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <?php require __DIR__ . '/admin-tabs.php'; ?>
    <h1>Buyer Map</h1>

    <div class="admin-map-legend">
        <div class="buyer-map-toggle">
            <button id="btn-exact" class="map-view-btn active" onclick="setView('exact')">By Address</button>
            <button id="btn-khan" class="map-view-btn" onclick="setView('khan')">By Khan</button>
        </div>
        <span class="admin-map-legend-total"><?= $totalBuyers ?> buyers total</span>
    </div>

    <div id="map" class="admin-map"></div>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

<style>
.buyer-map-toggle {
    display: flex;
    gap: 0;
    border: 1px solid var(--border-strong);
    border-radius: var(--radius-sm);
    overflow: hidden;
}
.map-view-btn {
    padding: 0.3rem 0.85rem;
    font-size: 0.8rem;
    border: none;
    background: #fff;
    color: var(--text-muted);
    cursor: pointer;
    font-family: inherit;
}
.map-view-btn + .map-view-btn {
    border-left: 1px solid var(--border-strong);
}
.map-view-btn.active {
    background: var(--primary);
    color: #fff;
}
</style>

<script>
const BUYERS   = <?= json_encode($buyerRows, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const KHAN_DATA = <?= json_encode($khanData, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const MAX_COUNT = <?= (int)$maxCount ?>;

mapboxgl.accessToken = <?= json_encode(MAPBOX_TOKEN) ?>;

const map = new mapboxgl.Map({
    container: 'map',
    style: 'mapbox://styles/mapbox/streets-v12',
    center: [104.9160, 11.5564],
    zoom: 11
});

map.addControl(new mapboxgl.NavigationControl(), 'top-right');

function escHtml(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

const exactMarkers = [];
const khanMarkers  = [];

map.on('load', () => {
    // Exact pins — same style as vendor map
    BUYERS.forEach(b => {
        const el = document.createElement('div');
        el.style.cssText = 'width:12px;height:12px;border-radius:50%;background:#2d3a6b;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,0.35);cursor:pointer;';

        const parts = [];
        if (b.house_number) parts.push(b.house_number);
        if (b.address)      parts.push(b.address);
        if (b.sangkat)      parts.push(b.sangkat);
        if (b.khan)         parts.push(b.khan);
        const addrLine = parts.join(', ');

        const popup = new mapboxgl.Popup({ offset: 10, maxWidth: '240px' }).setHTML(
            '<div style="padding:2px 0">' +
            '<strong style="font-size:0.9rem;display:block;margin-bottom:3px">' + escHtml(b.name) + '</strong>' +
            '<div style="font-size:0.8rem;color:#6b7280">' + escHtml(b.email) + '</div>' +
            (addrLine ? '<div style="font-size:0.78rem;color:#9ca3af;margin-top:3px">' + escHtml(addrLine) + '</div>' : '') +
            '</div>'
        );

        const marker = new mapboxgl.Marker({ element: el })
            .setLngLat([parseFloat(b.lng), parseFloat(b.lat)])
            .setPopup(popup)
            .addTo(map);

        exactMarkers.push(marker);
    });

    // Khan bubbles
    KHAN_DATA.forEach(k => {
        const ratio = MAX_COUNT > 0 ? k.cnt / MAX_COUNT : 0;
        const size  = Math.round(28 + ratio * 36);
        const alpha = (0.55 + ratio * 0.35).toFixed(2);

        const el = document.createElement('div');
        el.style.cssText = [
            'width:' + size + 'px',
            'height:' + size + 'px',
            'border-radius:50%',
            'background:rgba(45,58,107,' + alpha + ')',
            'border:2px solid rgba(45,58,107,0.8)',
            'color:#fff',
            'font-size:' + (size < 38 ? '0.72' : '0.8') + 'rem',
            'font-weight:700',
            'display:flex',
            'align-items:center',
            'justify-content:center',
            'cursor:pointer',
            'line-height:1',
            'display:none',
        ].join(';');
        el.textContent = k.cnt;

        const popup = new mapboxgl.Popup({ offset: size / 2 + 4, maxWidth: '200px' }).setHTML(
            '<div style="padding:2px 0">' +
            '<strong style="font-size:0.9rem;display:block;margin-bottom:4px">' + escHtml(k.khan) + '</strong>' +
            '<div style="font-size:0.85rem;color:#374151">' + k.cnt + ' buyer' + (k.cnt !== 1 ? 's' : '') + '</div>' +
            '</div>'
        );

        const marker = new mapboxgl.Marker({ element: el, anchor: 'center' })
            .setLngLat([k.lng, k.lat])
            .setPopup(popup)
            .addTo(map);

        khanMarkers.push({ marker, el });
    });
});

function setView(mode) {
    document.getElementById('btn-exact').classList.toggle('active', mode === 'exact');
    document.getElementById('btn-khan').classList.toggle('active', mode === 'khan');

    exactMarkers.forEach(m => m.getElement().style.display = mode === 'exact' ? '' : 'none');
    khanMarkers.forEach(({ el }) => el.style.display = mode === 'khan' ? 'flex' : 'none');
}
</script>
</body>
</html>
