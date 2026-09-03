<?php
session_start([
    'gc_maxlifetime'  => 28800,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../../config/db.php';
require __DIR__ . '/../../config/admin-auth.php';
require __DIR__ . '/../../config/mapbox.php';
require __DIR__ . '/../../config/prospects.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: /login-admin/');
    exit;
}

admin_require('prospects');

$adminSection = 'marketing';
$adminTab     = 'prospects';

$prospects = $pdo->query('
    SELECT id, business_name, status, phone, address, lat, lng
      FROM prospects
     WHERE lat IS NOT NULL AND lng IS NOT NULL
')->fetchAll(PDO::FETCH_ASSOC);

// Approved vendors are drawn underneath so a street you have already signed up
// is obvious before you walk it again.
$vendors = $pdo->query('
    SELECT b.name, b.lat, b.lng
      FROM businesses b
     WHERE b.deleted_at IS NULL AND b.approved = 1 AND NOT (b.lat = 0 AND b.lng = 0)
')->fetchAll(PDO::FETCH_ASSOC);

$counts = [];
foreach ($prospects as $p) {
    $counts[$p['status']] = ($counts[$p['status']] ?? 0) + 1;
}

// Centre on the prospects if there are any, otherwise Phnom Penh.
$center = [104.9160, 11.5564];
if ($prospects) {
    $center = [
        array_sum(array_map(fn($p) => (float)$p['lng'], $prospects)) / count($prospects),
        array_sum(array_map(fn($p) => (float)$p['lat'], $prospects)) / count($prospects),
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Canvassing Map</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/admin/admin.css">
    <link rel="stylesheet" href="/admin/prospects/prospects.css">
    <?php require __DIR__ . '/app-head.php'; ?>
    <link href="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css" rel="stylesheet">
    <script src="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js"></script>
</head>
<body>

<?php require __DIR__ . '/../../header/header.php'; ?>
<?php require __DIR__ . '/../../admin-subnav/admin-subnav.php'; ?>

<main>
    <?php require __DIR__ . '/../admin-tabs.php'; ?>

    <div class="psp-head">
        <h1>Canvassing map</h1>
        <div class="psp-head-actions">
            <a href="/admin/prospects/" class="psp-btn">List</a>
            <a href="/admin/prospects/new.php" class="psp-btn psp-btn-primary">+ Add prospect</a>
        </div>
    </div>

    <div class="admin-map-legend">
        <?php foreach (PROSPECT_STATUSES as $key => $label): ?>
            <span class="admin-map-legend-item">
                <span class="admin-map-dot" style="background:<?= prospect_status_color($key) ?>"></span>
                <?= htmlspecialchars($label) ?> (<?= $counts[$key] ?? 0 ?>)
            </span>
        <?php endforeach; ?>
        <span class="admin-map-legend-item">
            <span class="admin-map-dot psp-dot-vendor"></span> Signed vendors (<?= count($vendors) ?>)
        </span>
        <span class="admin-map-legend-total">Pinned prospects: <?= count($prospects) ?></span>
    </div>

    <div id="map" class="admin-map"></div>
</main>

<?php require __DIR__ . '/../../footer/footer.php'; ?>

<script>
const PROSPECTS = <?= json_encode($prospects, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const VENDORS   = <?= json_encode($vendors,   JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const COLORS    = <?= json_encode(PROSPECT_COLORS, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const LABELS    = <?= json_encode(PROSPECT_STATUSES, JSON_HEX_TAG | JSON_HEX_AMP) ?>;

mapboxgl.accessToken = <?= json_encode(MAPBOX_TOKEN) ?>;

const map = new mapboxgl.Map({
    container: 'map',
    style: 'mapbox://styles/mapbox/streets-v12',
    center: <?= json_encode($center) ?>,
    zoom: 13
});

map.addControl(new mapboxgl.NavigationControl(), 'top-right');
map.addControl(new mapboxgl.GeolocateControl({
    positionOptions: { enableHighAccuracy: true },
    trackUserLocation: true,
    showUserHeading: true
}), 'top-right');

function escHtml(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

function dot(color, size, ring) {
    const el = document.createElement('div');
    el.style.cssText = 'width:' + size + 'px;height:' + size + 'px;border-radius:50%;background:' + color +
        ';border:2px solid ' + ring + ';box-shadow:0 1px 4px rgba(0,0,0,0.35);cursor:pointer;';
    return el;
}

map.on('load', () => {
    // Existing vendors first, so prospect pins sit on top of them.
    VENDORS.forEach(v => {
        new mapboxgl.Marker({ element: dot('#ffffff', 9, '#16a34a') })
            .setLngLat([parseFloat(v.lng), parseFloat(v.lat)])
            .setPopup(new mapboxgl.Popup({ offset: 10, maxWidth: '240px' }).setHTML(
                '<div style="padding:2px 0"><strong style="font-size:0.9rem">' + escHtml(v.name) + '</strong>' +
                '<div style="font-size:0.78rem;color:#16a34a;margin-top:3px">Signed vendor</div></div>'
            ))
            .addTo(map);
    });

    PROSPECTS.forEach(p => {
        const color = COLORS[p.status] || '#6b7280';
        const label = LABELS[p.status] || p.status;

        new mapboxgl.Marker({ element: dot(color, 13, '#fff') })
            .setLngLat([parseFloat(p.lng), parseFloat(p.lat)])
            .setPopup(new mapboxgl.Popup({ offset: 10, maxWidth: '240px' }).setHTML(
                '<div style="padding:2px 0">' +
                '<strong style="font-size:0.9rem;display:block;margin-bottom:3px">' + escHtml(p.business_name) + '</strong>' +
                (p.address ? '<div style="font-size:0.78rem;color:#9ca3af">' + escHtml(p.address) + '</div>' : '') +
                (p.phone ? '<div style="font-size:0.78rem;color:#6b7280">' + escHtml(p.phone) + '</div>' : '') +
                '<div style="margin-top:6px"><span style="background:' + color + ';color:#fff;font-size:0.72rem;padding:2px 8px;border-radius:99px;font-weight:600">' + escHtml(label) + '</span></div>' +
                '<div style="margin-top:6px"><a href="/admin/prospects/prospect.php?id=' + encodeURIComponent(p.id) + '" style="font-size:0.8rem">Open</a></div>' +
                '</div>'
            ))
            .addTo(map);
    });
});
</script>
</body>
</html>
