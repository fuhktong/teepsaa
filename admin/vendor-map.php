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

admin_require('vendor-map');

$pendingVendorCount = (int)$pdo->query("SELECT COUNT(*) FROM businesses WHERE approved = 0")->fetchColumn();
$refundCount        = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'refund_requested'")->fetchColumn();
$pendingPayoutCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered' AND delivered_at IS NOT NULL AND delivered_at < DATE_SUB(NOW(), INTERVAL " . PAYOUT_WINDOW_SECONDS . " SECOND)")->fetchColumn();

$vendors = $pdo->query("
    SELECT v.name AS vendor_name, v.email,
           b.name AS business_name, b.lat, b.lng, b.approved, b.address
    FROM vendors v
    JOIN businesses b ON b.user_id = v.id
    WHERE NOT (b.lat = 0 AND b.lng = 0)
    ORDER BY b.approved DESC, v.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$total    = count($vendors);
$approved = count(array_filter($vendors, fn($v) => (int)$v['approved'] === 1));
$pending  = count(array_filter($vendors, fn($v) => (int)$v['approved'] === 0));
$rejected = count(array_filter($vendors, fn($v) => (int)$v['approved'] === -1));
$adminSection = 'marketing';
$adminTab     = 'vendor-map';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Vendor Map</title>
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
    <h1>Vendor Map</h1>

    <div class="admin-map-legend">
        <span class="admin-map-legend-item"><span class="admin-map-dot" style="background:#16a34a"></span> Approved (<?= $approved ?>)</span>
        <span class="admin-map-legend-item"><span class="admin-map-dot" style="background:#d97706"></span> Pending (<?= $pending ?>)</span>
        <span class="admin-map-legend-item"><span class="admin-map-dot" style="background:#dc2626"></span> Rejected (<?= $rejected ?>)</span>
        <span class="admin-map-legend-total">Total with location: <?= $total ?></span>
    </div>

    <div id="map" class="admin-map"></div>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

<script>
const VENDORS = <?= json_encode($vendors, JSON_HEX_TAG | JSON_HEX_AMP) ?>;

mapboxgl.accessToken = <?= json_encode(MAPBOX_TOKEN) ?>;

const map = new mapboxgl.Map({
    container: 'map',
    style: 'mapbox://styles/mapbox/streets-v12',
    center: [104.9160, 11.5564],
    zoom: 12
});

map.addControl(new mapboxgl.NavigationControl(), 'top-right');

const STATUS_COLOR = { '1': '#16a34a', '0': '#d97706', '-1': '#dc2626' };
const STATUS_LABEL = { '1': 'Approved', '0': 'Pending', '-1': 'Rejected' };

function escHtml(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

map.on('load', () => {
    VENDORS.forEach(v => {
        const color = STATUS_COLOR[String(v.approved)] ?? '#6b7280';
        const label = STATUS_LABEL[String(v.approved)] ?? 'Unknown';

        const el = document.createElement('div');
        el.style.cssText = 'width:12px;height:12px;border-radius:50%;background:' + color + ';border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,0.35);cursor:pointer;';

        const popup = new mapboxgl.Popup({ offset: 10, maxWidth: '240px' }).setHTML(
            '<div style="padding:2px 0">' +
            '<strong style="font-size:0.9rem;display:block;margin-bottom:3px">' + escHtml(v.business_name) + '</strong>' +
            '<div style="font-size:0.8rem;color:#6b7280">' + escHtml(v.vendor_name) + '</div>' +
            '<div style="font-size:0.78rem;color:#9ca3af">' + escHtml(v.email) + '</div>' +
            (v.address ? '<div style="font-size:0.78rem;color:#9ca3af;margin-top:3px">' + escHtml(v.address) + '</div>' : '') +
            '<div style="margin-top:6px"><span style="background:' + color + ';color:#fff;font-size:0.72rem;padding:2px 8px;border-radius:99px;font-weight:600">' + label + '</span></div>' +
            '</div>'
        );

        new mapboxgl.Marker({ element: el })
            .setLngLat([parseFloat(v.lng), parseFloat(v.lat)])
            .setPopup(popup)
            .addTo(map);
    });
});
</script>
</body>
</html>
