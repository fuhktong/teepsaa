<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/mapbox.php';
$locations = require __DIR__ . '/../config/phnom-penh-locations.php';

$allCatsRaw = $pdo->query('SELECT id, parent_id, name, name_km FROM categories ORDER BY name ASC')->fetchAll();
function submitBuildCatTree(array $cats, $parentId = null): array {
    $branch = [];
    foreach ($cats as $cat) {
        if ($cat['parent_id'] == $parentId) {
            $cat['children'] = submitBuildCatTree($cats, $cat['id']);
            $branch[] = $cat;
        }
    }
    return $branch;
}
function submitFlattenCatTree(array $nodes, int $depth = 0): array {
    $result = [];
    foreach ($nodes as $node) {
        $node['depth'] = $depth;
        $children = $node['children'];
        unset($node['children']);
        $result[] = $node;
        $result = array_merge($result, submitFlattenCatTree($children, $depth + 1));
    }
    return $result;
}
$allFlat = submitFlattenCatTree(submitBuildCatTree($allCatsRaw));

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header('Location: /login-vendor/');
    exit;
}

$stmt = $pdo->prepare('SELECT COUNT(*) FROM businesses WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$hasShop = $stmt->fetchColumn() > 0;

$error   = $_SESSION['submit_error'] ?? '';
$success = $_SESSION['submit_success'] ?? '';
unset($_SESSION['submit_error'], $_SESSION['submit_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit a Business — teepsaa</title>
    <link href="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css" rel="stylesheet">
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/submit/submit.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <h1><?= $t['submit_title'] ?></h1>

    <?php if ($hasShop): ?>
        <p class="form-error"><?= $t['submit_has_business'] ?></p>
        <p><a href="/dashboard-vendor/"><?= $t['submit_back_dashboard'] ?></a></p>
    <?php else: ?>

    <?php if ($error): ?>
        <p class="form-error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <?php if ($success): ?>
        <p class="form-success"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <form method="POST" action="/submit/submit.php" enctype="multipart/form-data">
        <?= csrf_input() ?>

        <div class="field">
            <label for="name"><?= $t['vendor_settings_biz_name'] ?></label>
            <input type="text" id="name" name="name" required>
        </div>

        <div class="field">
            <label for="description"><?= $t['vendor_settings_description'] ?></label>
            <textarea id="description" name="description" rows="4"></textarea>
        </div>

        <div class="field">
            <label><?= $t['submit_category'] ?> <span class="hint"><?= $t['submit_category_hint'] ?></span></label>
            <div id="cat-cascade" class="cat-cascade"></div>
            <input type="hidden" id="category_id" name="category_id" required>
        </div>

        <div class="field">
            <label for="house_number"><?= $t['settings_address_house'] ?></label>
            <input type="text" id="house_number" name="house_number" placeholder="e.g. 15">
        </div>

        <div class="field">
            <label for="address"><?= $t['settings_street'] ?></label>
            <input type="text" id="address" name="address" placeholder="e.g. Street 240">
        </div>

        <div class="field">
            <label for="khan"><?= $t['settings_address_khan'] ?></label>
            <select id="khan" name="khan" onchange="updateSangkats(this.value)">
                <option value=""><?= $t['settings_select_khan'] ?></option>
                <?php foreach (array_keys($locations) as $k): ?>
                <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($k) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="sangkat"><?= $t['settings_address_sangkat'] ?></label>
            <select id="sangkat" name="sangkat">
                <option value=""><?= $t['settings_select_sangkat'] ?></option>
            </select>
        </div>

        <div class="field">
            <label><?= $t['submit_location'] ?> <span class="hint"><?= $t['submit_location_hint'] ?></span></label>
            <div id="map"></div>
            <input type="hidden" id="lat" name="lat" required>
            <input type="hidden" id="lng" name="lng" required>
            <p id="pin-label" class="pin-label"><?= $t['submit_no_location'] ?></p>
        </div>

        <div class="field">
            <label for="photos"><?= $t['submit_photos'] ?> <span class="hint"><?= $t['submit_photos_hint'] ?></span></label>
            <input type="file" id="photos" name="photos[]" accept="image/jpeg,image/png" multiple>
        </div>

        <button type="submit"><?= $t['submit_for_review'] ?></button>
    </form>

    <?php endif; ?>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

<script src="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js"></script>
<script src="/js/boundary.js"></script>
<script>
// Category cascade
(function () {
    var allCats = <?= json_encode(array_values($allFlat)) ?>;
    var CAT_LANG = <?= json_encode($_SESSION['lang'] ?? 'km') ?>;
    var byParent = {}, byId = {};
    allCats.forEach(function (c) {
        byId[c.id] = c;
        var key = c.parent_id || 'root';
        if (!byParent[key]) byParent[key] = [];
        byParent[key].push(c);
    });
    var container = document.getElementById('cat-cascade');
    var hidden    = document.getElementById('category_id');

    function isLeaf(id) { return !byParent[id] || !byParent[id].length; }

    function trimFrom(level) {
        Array.from(container.querySelectorAll('select')).slice(level).forEach(function (s) { s.remove(); });
    }

    function renderLevel(parentKey, level) {
        var children = byParent[parentKey] || [];
        if (!children.length) return;
        var sel = document.createElement('select');
        sel.className = 'cat-level-select';
        sel.style.cssText = 'width:100%;padding:0.6rem 0.75rem;border:1px solid #d1d5db;border-radius:5px;font-size:0.9rem;margin-bottom:0.5rem;background:#fff;-webkit-appearance:none;appearance:none;';
        var ph = document.createElement('option');
        ph.value = '';
        ph.textContent = level === 0 ? '— Select a category —' : '— Select subcategory —';
        sel.appendChild(ph);
        children.forEach(function (c) {
            var opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = (CAT_LANG === 'km' && c.name_km) ? c.name_km : c.name;
            sel.appendChild(opt);
        });
        container.appendChild(sel);
        sel.addEventListener('change', function () {
            trimFrom(level + 1);
            hidden.value = '';
            var id = parseInt(sel.value);
            if (!id) return;
            if (isLeaf(id)) { hidden.value = id; } else { renderLevel(id, level + 1); }
        });
    }
    renderLevel('root', 0);

    document.querySelector('form').addEventListener('submit', function (e) {
        if (!hidden.value) {
            e.preventDefault();
            alert('Please select a category.');
            container.querySelector('select').focus();
        }
    });
})();

const LOCATIONS = <?= json_encode($locations) ?>;
function updateSangkats(khan) {
    const sel = document.getElementById('sangkat');
    sel.innerHTML = '<option value=""><?= $t['settings_select_sangkat'] ?></option>';
    if (khan && LOCATIONS[khan]) {
        LOCATIONS[khan].forEach(s => {
            const opt = document.createElement('option');
            opt.value = s;
            opt.textContent = s;
            sel.appendChild(opt);
        });
    }
}

mapboxgl.accessToken = '<?= MAPBOX_TOKEN ?>';

const map = new mapboxgl.Map({
    container: 'map',
    style: 'mapbox://styles/mapbox/streets-v12',
    center: [104.9160, 11.5564],
    zoom: 13,
    maxBounds: [[104.654628, 11.324807], [105.055619, 11.737473]]
});

map.addControl(new mapboxgl.GeolocateControl({
    positionOptions: { enableHighAccuracy: true },
    trackUserLocation: false
}));

let marker = null;

map.on('load', () => {
    addCityMask(map);
});

map.on('click', (e) => {
    const { lng, lat } = e.lngLat;

    if (!pointInPolygon(lat, lng, CITY_BOUNDARY)) {
        document.getElementById('pin-label').textContent = 'Please select a location inside Phnom Penh.';
        return;
    }

    if (marker) marker.remove();
    marker = new mapboxgl.Marker()
        .setLngLat([lng, lat])
        .addTo(map);

    document.getElementById('lat').value = lat.toFixed(7);
    document.getElementById('lng').value = lng.toFixed(7);
    document.getElementById('pin-label').textContent = `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
});
</script>
</body>
</html>
