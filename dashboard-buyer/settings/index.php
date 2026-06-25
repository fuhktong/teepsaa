<?php
session_start();
require __DIR__ . '/../../config/db.php';
require __DIR__ . '/../../config/csrf.php';
require __DIR__ . '/../../config/mapbox.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'buyer') {
    header('Location: /login-buyer/');
    exit;
}

$userId    = $_SESSION['user_id'];
$validTabs = ['account', 'address', 'password', 'danger'];
$tab       = in_array($_GET['tab'] ?? '', $validTabs) ? $_GET['tab'] : 'account';
$locations = ($tab === 'address') ? require __DIR__ . '/../../config/phnom-penh-locations.php' : [];

$savedAddresses = [];
if ($tab === 'address') {
    $addrStmt = $pdo->prepare('SELECT * FROM buyer_addresses WHERE buyer_user_id = ? ORDER BY is_default DESC, created_at ASC');
    $addrStmt->execute([$userId]);
    $savedAddresses = $addrStmt->fetchAll();
}

$stmt = $pdo->prepare('SELECT name, email, phone, avatar, avatar_color, house_number, address, address_notes, khan, sangkat, lat, lng FROM buyers WHERE id = ?');
$stmt->execute([$userId]);
$buyer = $stmt->fetch();

$success = $_SESSION['settings_success'] ?? '';
$error   = $_SESSION['settings_error']   ?? '';
unset($_SESSION['settings_success'], $_SESSION['settings_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings — teepsaa</title>
    <?php if ($tab === 'address'): ?>
    <link href="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css" rel="stylesheet">
    <?php endif; ?>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/dashboard-buyer/settings/settings.css">
</head>
<body>

<?php require __DIR__ . '/../../header/header.php'; ?>

<main>
    <h1 style="margin-bottom:1.5rem">Settings</h1>

    <div class="settings-wrap">

        <nav class="settings-nav">
            <a href="?tab=account"   class="<?= $tab === 'account'   ? 'active' : '' ?>">Account</a>
            <a href="?tab=address"   class="<?= $tab === 'address'   ? 'active' : '' ?>">Address</a>
            <a href="?tab=password"  class="<?= $tab === 'password'  ? 'active' : '' ?>">Password</a>
            <a href="?tab=danger"    class="danger-link <?= $tab === 'danger' ? 'active' : '' ?>">Delete account</a>
        </nav>

        <div class="settings-content">

            <?php if ($success): ?>
            <p class="settings-msg settings-msg--success"><?= htmlspecialchars($success) ?></p>
            <?php endif; ?>
            <?php if ($error): ?>
            <p class="settings-msg settings-msg--error"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            <?php if ($tab === 'account'): ?>
            <div class="settings-section">
                <h2>Account</h2>

                <?php $bColorIdx = isset($buyer['avatar_color']) ? (int)$buyer['avatar_color'] : (abs($userId) % 5); ?>
                <div class="avatar-preview-wrap">
                    <?php if ($buyer['avatar']): ?>
                        <img src="/uploads/<?= htmlspecialchars($buyer['avatar']) ?>" alt="" class="avatar-preview">
                    <?php else: ?>
                        <?= _avatar_svg($userId, $bColorIdx, 64) ?>
                    <?php endif; ?>
                    <div>
                        <form method="POST" action="/dashboard-buyer/settings/avatar-action.php" enctype="multipart/form-data" style="display:inline">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="photo">
                            <label for="avatar" class="btn-upload">Choose photo</label>
                            <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png" style="display:none" onchange="this.form.submit()">
                        </form>
                        <?php if ($buyer['avatar']): ?>
                        <form method="POST" action="/dashboard-buyer/settings/avatar-action.php" style="display:inline;margin-left:0.5rem">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="btn-remove-avatar">Remove</button>
                        </form>
                        <?php endif; ?>
                        <p class="field-hint" style="margin-top:0.35rem">JPG or PNG, max 2MB. Updates your header avatar.</p>
                    </div>
                </div>

                <?php $avPalette = ['#4a86e8','#e06055','#f6b026','#57bb8a','#8e63ce']; ?>
                <div style="margin-top:1.1rem">
                    <label class="settings-field-label">Avatar color <span class="field-hint" style="font-weight:400">— shown when no photo is set</span></label>
                    <form method="POST" action="/dashboard-buyer/settings/avatar-color-action.php">
                        <?= csrf_input() ?>
                        <div class="avatar-color-picker">
                            <?php foreach ($avPalette as $i => $bg): ?>
                            <label class="avatar-color-swatch <?= $bColorIdx === $i ? 'selected' : '' ?>" style="--ac:<?= $bg ?>">
                                <input type="radio" name="color" value="<?= $i ?>" onchange="this.form.submit()"<?= $bColorIdx === $i ? ' checked' : '' ?>>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </form>
                </div>

                <hr class="form-divider">

                <form method="POST" action="/dashboard-buyer/settings/profile-action.php">
                    <?= csrf_input() ?>
                    <div class="settings-field">
                        <label for="name">Full name</label>
                        <input type="text" id="name" name="name" value="<?= htmlspecialchars($buyer['name']) ?>" required>
                    </div>
                    <div class="settings-field">
                        <label for="email">Email</label>
                        <input type="email" id="email" value="<?= htmlspecialchars($buyer['email']) ?>" readonly>
                        <p class="field-hint">Email cannot be changed here. Contact support.</p>
                    </div>
                    <div class="settings-field">
                        <label for="phone">Phone</label>
                        <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($buyer['phone'] ?? '') ?>" placeholder="e.g. 012 345 678">
                    </div>
                    <button type="submit" class="btn-save">Save</button>
                </form>
            </div>

            <?php elseif ($tab === 'address'): ?>
            <div class="settings-section">
                <h2>Delivery address</h2>
                <?php
                $addrParts = array_filter([
                    trim(($buyer['house_number'] ?? '') . ' ' . ($buyer['address'] ?? '')),
                    $buyer['sangkat'] ?? '',
                    $buyer['khan'] ?? '',
                    'Phnom Penh',
                ]);
                $addrLine   = implode(', ', $addrParts);
                $hasAddress = !empty($buyer['address']) || !empty($buyer['khan']);
                ?>
                <?php if ($hasAddress): ?>
                <div class="addr-display">
                    <p class="addr-display-line"><?= htmlspecialchars($addrLine) ?></p>
                    <?php if ($buyer['address_notes']): ?>
                    <p class="addr-display-notes"><?= htmlspecialchars($buyer['address_notes']) ?></p>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <p class="addr-display-empty">No address saved yet.</p>
                <?php endif; ?>
                <details class="addr-edit"<?= !$hasAddress ? ' open' : '' ?>>
                    <summary class="addr-edit-toggle">Edit address</summary>
                    <div class="addr-edit-body">
                <form method="POST" action="/dashboard-buyer/settings/address-action.php">
                    <?= csrf_input() ?>
                    <div class="settings-field">
                        <label for="phone">Phone number</label>
                        <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($buyer['phone'] ?? '') ?>" placeholder="e.g. 012 345 678">
                    </div>
                    <div class="settings-field">
                        <label for="house_number">House / Unit #</label>
                        <input type="text" id="house_number" name="house_number" value="<?= htmlspecialchars($buyer['house_number'] ?? '') ?>" placeholder="e.g. 15">
                    </div>
                    <div class="settings-field">
                        <label for="address">Street</label>
                        <input type="text" id="address" name="address" value="<?= htmlspecialchars($buyer['address'] ?? '') ?>" placeholder="e.g. Street 240">
                    </div>
                    <div class="settings-field">
                        <label for="address_notes">Floor / Unit / Landmark</label>
                        <input type="text" id="address_notes" name="address_notes" value="<?= htmlspecialchars($buyer['address_notes'] ?? '') ?>" placeholder="e.g. Apt 4B, blue gate">
                    </div>
                    <div class="settings-field">
                        <label for="khan">Khan</label>
                        <select id="khan" name="khan" onchange="updateSangkats(this.value)">
                            <option value="">Select Khan</option>
                            <?php foreach (array_keys($locations) as $k): ?>
                            <option value="<?= htmlspecialchars($k) ?>" <?= ($buyer['khan'] === $k) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($k) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="settings-field">
                        <label for="sangkat">Sangkat</label>
                        <select id="sangkat" name="sangkat">
                            <option value="">Select Sangkat</option>
                            <?php if ($buyer['khan'] && isset($locations[$buyer['khan']])): ?>
                                <?php foreach ($locations[$buyer['khan']] as $s): ?>
                                <option value="<?= htmlspecialchars($s) ?>" <?= ($buyer['sangkat'] === $s) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s) ?>
                                </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="settings-field">
                        <label>Drop pin <span class="field-hint" style="font-weight:400;display:inline"> — click the map to set your precise delivery location</span></label>
                        <div id="addr-map"></div>
                        <p id="pin-label" class="pin-label">
                            <?= ($buyer['lat'] && $buyer['lng'])
                                ? number_format((float)$buyer['lat'], 5) . ', ' . number_format((float)$buyer['lng'], 5)
                                : 'No pin set' ?>
                        </p>
                        <input type="hidden" id="lat" name="lat" value="<?= htmlspecialchars($buyer['lat'] ?? '') ?>">
                        <input type="hidden" id="lng" name="lng" value="<?= htmlspecialchars($buyer['lng'] ?? '') ?>">
                    </div>
                    <button type="submit" class="btn-save">Save address</button>
                </form>
                    </div>
                </details>

                <hr class="form-divider">

                <h2>Saved addresses</h2>
                <?php if (!empty($savedAddresses)): ?>
                <div class="saved-addr-list">
                    <?php foreach ($savedAddresses as $a): ?>
                    <?php
                        $aParts = array_filter([$a['house_number'], $a['address'], $a['sangkat'], $a['khan'], 'Phnom Penh']);
                        $aLine  = implode(', ', $aParts);
                    ?>
                    <div class="saved-addr-item<?= $a['is_default'] ? ' saved-addr-item--default' : '' ?>">
                        <div class="saved-addr-info">
                            <p class="saved-addr-label">
                                <?= htmlspecialchars($a['label'] ?: 'Unnamed') ?>
                                <?php if ($a['is_default']): ?><span class="saved-addr-badge">Default</span><?php endif; ?>
                            </p>
                            <p class="saved-addr-text"><?= htmlspecialchars($aLine) ?></p>
                            <?php if ($a['address_notes']): ?>
                            <p class="saved-addr-notes"><?= htmlspecialchars($a['address_notes']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="saved-addr-actions">
                            <?php if (!$a['is_default']): ?>
                            <form method="POST" action="/dashboard-buyer/settings/address-book-action.php">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="set_default">
                                <input type="hidden" name="address_id" value="<?= $a['id'] ?>">
                                <button type="submit" class="btn-addr-action">Set as default</button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" action="/dashboard-buyer/settings/address-book-action.php"
                                  onsubmit="return confirm('Remove this address?')">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="address_id" value="<?= $a['id'] ?>">
                                <button type="submit" class="btn-addr-delete btn-addr-action">Remove</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <details class="addr-edit" style="margin-top:1rem" id="new-addr-details">
                    <summary class="addr-edit-toggle">+ Add new address</summary>
                    <div class="addr-edit-body">
                        <form method="POST" action="/dashboard-buyer/settings/address-book-action.php">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="add">
                            <div class="settings-field">
                                <label for="new_label">Label</label>
                                <input type="text" id="new_label" name="label" placeholder="e.g. Home, Work" maxlength="100">
                            </div>
                            <div class="settings-field">
                                <label for="new_house_number">House / Unit #</label>
                                <input type="text" id="new_house_number" name="house_number" placeholder="e.g. 15">
                            </div>
                            <div class="settings-field">
                                <label for="new_address">Street</label>
                                <input type="text" id="new_address" name="address" placeholder="e.g. Street 240">
                            </div>
                            <div class="settings-field">
                                <label for="new_address_notes">Floor / Unit / Landmark</label>
                                <input type="text" id="new_address_notes" name="address_notes" placeholder="e.g. Apt 4B, blue gate">
                            </div>
                            <div class="settings-field">
                                <label for="new_khan">Khan</label>
                                <select id="new_khan" name="khan" onchange="updateNewSangkats(this.value)">
                                    <option value="">Select Khan</option>
                                    <?php foreach (array_keys($locations) as $k): ?>
                                    <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($k) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="settings-field">
                                <label for="new_sangkat">Sangkat</label>
                                <select id="new_sangkat" name="sangkat">
                                    <option value="">Select Sangkat</option>
                                </select>
                            </div>
                            <div class="settings-field">
                                <label>Drop pin <span class="field-hint" style="font-weight:400;display:inline"> — click the map to set the precise delivery location</span></label>
                                <div id="new-addr-map"></div>
                                <p id="new-pin-label" class="pin-label">No pin set</p>
                                <input type="hidden" id="new_lat" name="lat" value="">
                                <input type="hidden" id="new_lng" name="lng" value="">
                            </div>
                            <button type="submit" class="btn-save">Save address</button>
                        </form>
                    </div>
                </details>
            </div>

            <?php elseif ($tab === 'password'): ?>
            <div class="settings-section">
                <h2>Password</h2>
                <form method="POST" action="/dashboard-buyer/settings/password-action.php">
                    <?= csrf_input() ?>
                    <div class="settings-field">
                        <label for="current_password">Current password</label>
                        <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                    </div>
                    <div class="settings-field">
                        <label for="new_password">New password</label>
                        <input type="password" id="new_password" name="new_password" required autocomplete="new-password" minlength="8">
                        <p class="field-hint">At least 8 characters.</p>
                    </div>
                    <div class="settings-field">
                        <label for="confirm_password">Confirm new password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn-save">Update password</button>
                </form>
            </div>

            <?php elseif ($tab === 'danger'): ?>
            <div class="settings-section">
                <h2>Delete account</h2>
                <div class="danger-zone">
                    <p>Deleting your account is permanent. Your order history will be removed.</p>
                    <form method="POST" action="/dashboard-buyer/settings/delete-action.php">
                        <?= csrf_input() ?>
                        <div class="settings-field">
                            <label for="delete_password">Confirm your password</label>
                            <input type="password" id="delete_password" name="password" required autocomplete="current-password">
                        </div>
                        <button type="submit" class="btn-danger">Delete my account</button>
                    </form>
                </div>
            </div>

            <?php endif; ?>

        </div>
    </div>
</main>

<?php require __DIR__ . '/../../footer/footer.php'; ?>

<?php if ($tab === 'address'): ?>
<script src="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js"></script>
<script src="/js/boundary.js"></script>
<script>
const LOCATIONS = <?= json_encode($locations) ?>;

function updateSangkats(khan) {
    const sel = document.getElementById('sangkat');
    sel.innerHTML = '<option value="">Select Sangkat</option>';
    if (khan && LOCATIONS[khan]) {
        LOCATIONS[khan].forEach(s => {
            const opt = document.createElement('option');
            opt.value = s; opt.textContent = s;
            sel.appendChild(opt);
        });
    }
}

function updateNewSangkats(khan) {
    const sel = document.getElementById('new_sangkat');
    sel.innerHTML = '<option value="">Select Sangkat</option>';
    if (khan && LOCATIONS[khan]) {
        LOCATIONS[khan].forEach(s => {
            const opt = document.createElement('option');
            opt.value = s; opt.textContent = s;
            sel.appendChild(opt);
        });
    }
}

mapboxgl.accessToken = '<?= MAPBOX_TOKEN ?>';
const existingLat = <?= $buyer['lat'] ? (float)$buyer['lat'] : 'null' ?>;
const existingLng = <?= $buyer['lng'] ? (float)$buyer['lng'] : 'null' ?>;

const map = new mapboxgl.Map({
    container: 'addr-map',
    style: 'mapbox://styles/mapbox/streets-v12',
    center: (existingLat && existingLng) ? [existingLng, existingLat] : [104.9160, 11.5564],
    zoom: (existingLat && existingLng) ? 15 : 13,
    maxBounds: [[104.654628, 11.324807], [105.055619, 11.737473]]
});

map.addControl(new mapboxgl.GeolocateControl({
    positionOptions: { enableHighAccuracy: true },
    trackUserLocation: false
}));

let marker = null;

map.on('load', () => {
    addCityMask(map);
    if (existingLat && existingLng) {
        marker = new mapboxgl.Marker().setLngLat([existingLng, existingLat]).addTo(map);
    }
});

map.on('click', e => {
    const { lng, lat } = e.lngLat;
    if (!pointInPolygon(lat, lng, CITY_BOUNDARY)) {
        document.getElementById('pin-label').textContent = 'Please select a location inside Phnom Penh.';
        return;
    }
    if (marker) marker.remove();
    marker = new mapboxgl.Marker().setLngLat([lng, lat]).addTo(map);
    document.getElementById('lat').value = lat.toFixed(7);
    document.getElementById('lng').value = lng.toFixed(7);
    document.getElementById('pin-label').textContent = `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
});

let newMap = null;
let newMarker = null;

document.getElementById('new-addr-details').addEventListener('toggle', function () {
    if (!this.open) return;
    if (newMap) { newMap.resize(); return; }
    newMap = new mapboxgl.Map({
        container: 'new-addr-map',
        style: 'mapbox://styles/mapbox/streets-v12',
        center: [104.9160, 11.5564],
        zoom: 13,
        maxBounds: [[104.654628, 11.324807], [105.055619, 11.737473]]
    });
    newMap.on('load', () => addCityMask(newMap));
    newMap.on('click', e => {
        const { lng, lat } = e.lngLat;
        if (!pointInPolygon(lat, lng, CITY_BOUNDARY)) {
            document.getElementById('new-pin-label').textContent = 'Please select a location inside Phnom Penh.';
            return;
        }
        if (newMarker) newMarker.remove();
        newMarker = new mapboxgl.Marker().setLngLat([lng, lat]).addTo(newMap);
        document.getElementById('new_lat').value = lat.toFixed(7);
        document.getElementById('new_lng').value = lng.toFixed(7);
        document.getElementById('new-pin-label').textContent = `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
    });
});
</script>
<?php endif; ?>

</body>
</html>
