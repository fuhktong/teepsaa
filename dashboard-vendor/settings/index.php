<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../../config/db.php';
require __DIR__ . '/../../config/csrf.php';
require __DIR__ . '/../../config/mapbox.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header('Location: /login-vendor/');
    exit;
}

$userId    = $_SESSION['user_id'];
$validTabs = ['account', 'address', 'business', 'aba-qr', 'password', 'danger'];
$tab       = in_array($_GET['tab'] ?? '', $validTabs) ? $_GET['tab'] : 'account';

$stmt = $pdo->prepare('SELECT name, email, phone, avatar, avatar_color, aba_qr FROM vendors WHERE id = ?');
$stmt->execute([$userId]);
$vendor = $stmt->fetch();

$stmt = $pdo->prepare('SELECT id, name, name_km, description, description_km, house_number, address, address_notes, khan, sangkat, lat, lng, banner FROM businesses WHERE user_id = ? AND deleted_at IS NULL LIMIT 1');
$stmt->execute([$userId]);
$business = $stmt->fetch();

$photos = [];
$parentCategories = [];
$selectedCategories = [];
if ($tab === 'business' && $business) {
    $stmt = $pdo->prepare('SELECT id, filename FROM photos WHERE business_id = ? ORDER BY id ASC');
    $stmt->execute([$business['id']]);
    $photos = $stmt->fetchAll();

    $parentCategories = $pdo->query('SELECT id, name, name_km FROM categories WHERE parent_id IS NULL ORDER BY name ASC')->fetchAll();
    $selectedCategories = array_filter(array_map('trim', explode(',', $business['category'] ?? '')));
}

$locations = ($tab === 'address') ? require __DIR__ . '/../../config/phnom-penh-locations.php' : [];

$bizProductCount = 0;
$bizOpenOrders   = 0;
if ($tab === 'danger' && $business) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE business_id = ?');
    $stmt->execute([$business['id']]);
    $bizProductCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE business_id = ? AND status NOT IN ('completed','cancelled','refunded','refund_rejected')");
    $stmt->execute([$business['id']]);
    $bizOpenOrders = (int)$stmt->fetchColumn();
}

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
    <?php if ($tab === 'address' && $business): ?>
    <link href="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css" rel="stylesheet">
    <?php endif; ?>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/dashboard-vendor/settings/settings.css">
</head>
<body>

<?php require __DIR__ . '/../../header/header.php'; ?>

<main>
    <h1 style="margin-bottom:1.5rem"><?= $t['settings_title'] ?></h1>

    <div class="settings-wrap">

        <nav class="settings-nav">
            <a href="?tab=account"  class="<?= $tab === 'account'  ? 'active' : '' ?>"><?= $t['settings_tab_account'] ?></a>
            <a href="?tab=address"  class="<?= $tab === 'address'  ? 'active' : '' ?>"><?= $t['vendor_settings_tab_address'] ?></a>
            <a href="?tab=business" class="<?= $tab === 'business' ? 'active' : '' ?>"><?= $t['vendor_settings_tab_business'] ?></a>
            <a href="?tab=aba-qr"   class="<?= $tab === 'aba-qr'   ? 'active' : '' ?>"><?= $t['vendor_settings_tab_bank'] ?></a>
            <a href="?tab=password" class="<?= $tab === 'password' ? 'active' : '' ?>"><?= $t['settings_password_heading'] ?></a>
            <a href="?tab=danger"   class="danger-link <?= $tab === 'danger' ? 'active' : '' ?>"><?= $t['settings_delete_account'] ?></a>
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
                <h2><?= $t['settings_tab_account'] ?></h2>

                <?php $vColorIdx = isset($vendor['avatar_color']) ? (int)$vendor['avatar_color'] : (abs($userId) % 5); ?>
                <div class="avatar-preview-wrap">
                    <?php if ($vendor['avatar']): ?>
                        <img src="/uploads/<?= htmlspecialchars($vendor['avatar']) ?>" alt="" class="avatar-preview">
                    <?php else: ?>
                        <?= _avatar_svg($userId, $vColorIdx, 64) ?>
                    <?php endif; ?>
                    <div>
                        <form method="POST" action="/dashboard-vendor/settings/avatar-action.php" enctype="multipart/form-data" style="display:inline">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="photo">
                            <label for="avatar" class="btn-upload"><?= $t['settings_choose_photo'] ?></label>
                            <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png" style="display:none" onchange="this.form.submit()">
                        </form>
                        <?php if ($vendor['avatar']): ?>
                        <form method="POST" action="/dashboard-vendor/settings/avatar-action.php" style="display:inline;margin-left:0.5rem">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="btn-remove-avatar"><?= $t['settings_remove_photo'] ?></button>
                        </form>
                        <?php endif; ?>
                        <p class="field-hint" style="margin-top:0.35rem"><?= $t['settings_photo_hint'] ?></p>
                    </div>
                </div>

                <?php $avPalette = ['#4a86e8','#e06055','#f6b026','#57bb8a','#8e63ce']; ?>
                <div style="margin-top:1.1rem">
                    <label class="settings-field-label"><?= $t['settings_avatar_color'] ?> <span class="field-hint" style="font-weight:400"><?= $t['settings_avatar_hint'] ?></span></label>
                    <form method="POST" action="/dashboard-vendor/settings/avatar-color-action.php">
                        <?= csrf_input() ?>
                        <div class="avatar-color-picker">
                            <?php foreach ($avPalette as $i => $bg): ?>
                            <label class="avatar-color-swatch <?= $vColorIdx === $i ? 'selected' : '' ?>" style="--ac:<?= $bg ?>">
                                <input type="radio" name="color" value="<?= $i ?>" onchange="this.form.submit()"<?= $vColorIdx === $i ? ' checked' : '' ?>>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </form>
                </div>

                <hr class="form-divider">

                <form method="POST" action="/dashboard-vendor/settings/profile-action.php">
                    <?= csrf_input() ?>
                    <div class="settings-field">
                        <label for="name"><?= $t['vendor_contact_name'] ?></label>
                        <input type="text" id="name" name="name" value="<?= htmlspecialchars($vendor['name']) ?>" required>
                    </div>
                    <div class="settings-field">
                        <label for="email"><?= $t['vendor_contact_email'] ?></label>
                        <input type="email" id="email" value="<?= htmlspecialchars($vendor['email']) ?>" readonly>
                        <p class="field-hint"><?= $t['settings_email_hint'] ?></p>
                    </div>
                    <div class="settings-field">
                        <label for="phone"><?= $t['vendor_contact_phone'] ?></label>
                        <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($vendor['phone'] ?? '') ?>" placeholder="e.g. 012 345 678">
                    </div>
                    <button type="submit" class="btn-save"><?= $t['settings_save'] ?></button>
                </form>
            </div>

            <?php elseif ($tab === 'address'): ?>
            <div class="settings-section">
                <h2><?= $t['vendor_settings_tab_address'] ?></h2>

                <?php if (!$business): ?>
                <p style="font-size:0.9rem;color:#6b7280;"><?= $t['vendor_no_business'] ?> <a href="/submit/"><?= $t['vendor_submit_one'] ?></a></p>
                <?php else: ?>

                <?php
                $addrParts = array_filter([
                    trim(($business['house_number'] ?? '') . ' ' . ($business['address'] ?? '')),
                    $business['sangkat'] ?? '',
                    $business['khan'] ?? '',
                    'Phnom Penh',
                ]);
                $addrLine   = implode(', ', $addrParts);
                $hasAddress = !empty($business['address']) || !empty($business['khan']);
                ?>
                <?php if ($hasAddress): ?>
                <div class="addr-display">
                    <p class="addr-display-line"><?= htmlspecialchars($addrLine) ?></p>
                    <?php if ($business['address_notes']): ?>
                    <p class="addr-display-notes"><?= htmlspecialchars($business['address_notes']) ?></p>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <p class="addr-display-empty"><?= $t['settings_no_address'] ?></p>
                <?php endif; ?>
                <details class="addr-edit"<?= !$hasAddress ? ' open' : '' ?>>
                    <summary class="addr-edit-toggle"><?= $t['settings_address_edit'] ?></summary>
                    <div class="addr-edit-body">

                <form method="POST" action="/dashboard-vendor/settings/address-action.php">
                    <?= csrf_input() ?>

                    <div class="settings-field">
                        <label for="house_number"><?= $t['settings_address_house'] ?></label>
                        <input type="text" id="house_number" name="house_number" value="<?= htmlspecialchars($business['house_number'] ?? '') ?>" placeholder="e.g. 15">
                    </div>

                    <div class="settings-field">
                        <label for="address"><?= $t['settings_street'] ?></label>
                        <input type="text" id="address" name="address" value="<?= htmlspecialchars($business['address'] ?? '') ?>" placeholder="e.g. Street 240">
                    </div>

                    <div class="settings-field">
                        <label for="address_notes"><?= $t['settings_address_floor'] ?></label>
                        <input type="text" id="address_notes" name="address_notes" value="<?= htmlspecialchars($business['address_notes'] ?? '') ?>" placeholder="e.g. Ground floor, blue sign">
                    </div>

                    <div class="settings-field">
                        <label for="khan"><?= $t['settings_address_khan'] ?></label>
                        <select id="khan" name="khan" onchange="updateSangkats(this.value)">
                            <option value=""><?= $t['settings_select_khan'] ?></option>
                            <?php foreach (array_keys($locations) as $k): ?>
                            <option value="<?= htmlspecialchars($k) ?>" <?= ($business['khan'] === $k) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($k) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="settings-field">
                        <label for="sangkat"><?= $t['settings_address_sangkat'] ?></label>
                        <select id="sangkat" name="sangkat">
                            <option value=""><?= $t['settings_select_sangkat'] ?></option>
                            <?php if ($business['khan'] && isset($locations[$business['khan']])): ?>
                                <?php foreach ($locations[$business['khan']] as $s): ?>
                                <option value="<?= htmlspecialchars($s) ?>" <?= ($business['sangkat'] === $s) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s) ?>
                                </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="settings-field">
                        <label><?= $t['vendor_map_pin'] ?> <span class="field-hint" style="font-weight:400;display:inline"> <?= $t['vendor_map_pin_hint'] ?></span></label>
                        <div id="addr-map"></div>
                        <p id="pin-label" class="pin-label">
                            <?= ($business['lat'] && $business['lng'])
                                ? number_format((float)$business['lat'], 5) . ', ' . number_format((float)$business['lng'], 5)
                                : $t['vendor_no_pin_full'] ?>
                        </p>
                        <input type="hidden" id="lat" name="lat" value="<?= htmlspecialchars($business['lat'] ?? '') ?>">
                        <input type="hidden" id="lng" name="lng" value="<?= htmlspecialchars($business['lng'] ?? '') ?>">
                    </div>

                    <button type="submit" class="btn-save"><?= $t['settings_save_address'] ?></button>
                </form>

                    </div>
                </details>

                <?php endif; ?>
            </div>

            <?php elseif ($tab === 'business'): ?>
            <div class="settings-section">
                <h2><?= $t['vendor_settings_tab_business'] ?></h2>

                <?php if (!$business): ?>
                <p style="font-size:0.9rem;color:#6b7280;"><?= $t['vendor_no_business'] ?> <a href="/submit/"><?= $t['vendor_submit_one'] ?></a></p>
                <?php else: ?>

                <form method="POST" action="/dashboard-vendor/settings/business-action.php">
                    <?= csrf_input() ?>
                    <div class="settings-field">
                        <label for="business_name"><?= $t['vendor_settings_biz_name'] ?></label>
                        <input type="text" id="business_name" name="business_name" value="<?= htmlspecialchars($business['name']) ?>" required>
                    </div>
                    <div class="settings-field">
                        <label for="business_name_km"><?= $t['vendor_settings_biz_name'] ?> <span class="field-hint" style="font-weight:400;display:inline"><?= $t['form_km_field'] ?></span></label>
                        <input type="text" id="business_name_km" name="business_name_km" value="<?= htmlspecialchars($business['name_km'] ?? '') ?>" placeholder="ឈ្មោះហាងជាភាសាខ្មែរ">
                    </div>
                    <div class="settings-field">
                        <label for="description"><?= $t['vendor_settings_description'] ?></label>
                        <textarea id="description" name="description" rows="4" placeholder="<?= htmlspecialchars($t['vendor_biz_desc_placeholder']) ?>"><?= htmlspecialchars($business['description'] ?? '') ?></textarea>
                    </div>
                    <div class="settings-field">
                        <label for="description_km"><?= $t['vendor_settings_description'] ?> <span class="field-hint" style="font-weight:400;display:inline"><?= $t['form_km_field'] ?></span></label>
                        <textarea id="description_km" name="description_km" rows="4" placeholder="ការពិពណ៌នាហាងជាភាសាខ្មែរ"><?= htmlspecialchars($business['description_km'] ?? '') ?></textarea>
                    </div>
                    <?php if (!empty($parentCategories)): ?>
                    <div class="settings-field">
                        <label><?= $t['vendor_settings_categories'] ?> <span class="field-hint" style="font-weight:400;display:inline"><?= $t['vendor_cat_hint'] ?></span></label>
                        <div class="biz-cat-grid">
                            <?php foreach ($parentCategories as $cat): ?>
                            <?php $checked = in_array($cat['name'], $selectedCategories, true); ?>
                            <label class="biz-cat-option <?= $checked ? 'biz-cat-option--selected' : '' ?>">
                                <input type="checkbox" name="categories[]" value="<?= htmlspecialchars($cat['name']) ?>" <?= $checked ? 'checked' : '' ?> onchange="this.closest('label').classList.toggle('biz-cat-option--selected', this.checked)">
                                <?= htmlspecialchars(cat_name($cat)) ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <button type="submit" class="btn-save"><?= $t['settings_save'] ?></button>
                </form>

                <hr class="form-divider">

                <div class="settings-field">
                    <label><?= $t['vendor_settings_banner'] ?> <span class="field-hint" style="font-weight:400;display:inline"> <?= $t['vendor_settings_banner_hint'] ?></span></label>
                    <?php if ($business['banner']): ?>
                        <img src="/uploads/<?= htmlspecialchars($business['banner']) ?>" alt="" class="banner-preview">
                    <?php endif; ?>
                    <form method="POST" action="/dashboard-vendor/settings/banner-action.php" enctype="multipart/form-data">
                        <?= csrf_input() ?>
                        <label for="banner" class="btn-upload" style="margin-top:0.6rem;display:inline-block"><?= $business['banner'] ? $t['vendor_replace_banner'] : $t['vendor_upload_banner'] ?></label>
                        <input type="file" id="banner" name="banner" accept="image/jpeg,image/png" style="display:none" onchange="this.form.submit()">
                        <p class="field-hint"><?= $t['vendor_banner_upload_hint'] ?></p>
                    </form>
                </div>

                <hr class="form-divider">

                <div class="settings-field">
                    <label><?= $t['vendor_gallery'] ?> <span class="field-hint" style="font-weight:400;display:inline"> <?= $t['vendor_settings_photos_hint'] ?></span></label>
                    <?php if (!empty($photos)): ?>
                    <div class="gallery-grid">
                        <?php foreach ($photos as $ph): ?>
                        <div class="gallery-item">
                            <img src="/uploads/<?= htmlspecialchars($ph['filename']) ?>" alt="">
                            <form method="POST" action="/dashboard-vendor/settings/photo-delete-action.php">
                                <?= csrf_input() ?>
                                <input type="hidden" name="photo_id" value="<?= $ph['id'] ?>">
                                <button type="submit" class="gallery-delete" title="Delete">×</button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (count($photos) < 10): ?>
                    <form method="POST" action="/dashboard-vendor/settings/photo-upload-action.php" enctype="multipart/form-data" style="margin-top:0.75rem">
                        <?= csrf_input() ?>
                        <label for="gallery_photo" class="btn-upload" style="margin-top:0.6rem;display:inline-block"><?= $t['vendor_settings_photos'] ?></label>
                        <input type="file" id="gallery_photo" name="gallery_photo[]" accept="image/jpeg,image/png" multiple style="display:none" onchange="this.form.submit()">
                        <p class="field-hint"><?= sprintf($t['vendor_gallery_upload_hint'], count($photos)) ?></p>
                    </form>
                    <?php endif; ?>
                </div>

                <?php endif; ?>
            </div>

            <?php elseif ($tab === 'aba-qr'): ?>
            <div class="settings-section">
                <h2><?= $t['vendor_settings_bank_qr'] ?></h2>
                <p class="field-hint" style="margin-bottom:1.25rem;"><?= $t['vendor_settings_bank_hint'] ?></p>
                <?php if ($vendor['aba_qr']): ?>
                <img src="/uploads/<?= htmlspecialchars($vendor['aba_qr']) ?>" alt="Your Bank QR" style="width:140px;height:140px;object-fit:contain;border:1px solid #e5e7eb;border-radius:6px;display:block;margin-bottom:1.25rem;">
                <?php endif; ?>
                <form method="POST" action="/dashboard-vendor/settings/aba-qr-action.php" enctype="multipart/form-data">
                    <?= csrf_input() ?>
                    <div class="settings-field">
                        <label for="aba_qr"><?= $vendor['aba_qr'] ? $t['vendor_replace_qr'] : $t['vendor_upload_qr'] ?> <span class="field-hint" style="font-weight:400;display:inline"><?= $t['vendor_qr_hint'] ?></span></label>
                        <input type="file" id="aba_qr" name="aba_qr" accept="image/jpeg,image/png" required>
                    </div>
                    <button type="submit" class="btn-save"><?= $t['vendor_upload'] ?></button>
                </form>
            </div>

            <?php elseif ($tab === 'password'): ?>
            <div class="settings-section">
                <h2><?= $t['settings_password_heading'] ?></h2>
                <form method="POST" action="/dashboard-vendor/settings/password-action.php">
                    <?= csrf_input() ?>
                    <input type="text" name="username" value="<?= htmlspecialchars($vendor['email']) ?>" autocomplete="username" hidden readonly>
                    <div class="settings-field">
                        <label for="current_password"><?= $t['settings_current_pw'] ?></label>
                        <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                    </div>
                    <div class="settings-field">
                        <label for="new_password"><?= $t['settings_new_pw'] ?></label>
                        <input type="password" id="new_password" name="new_password" required autocomplete="new-password" minlength="8">
                        <p class="field-hint"><?= $t['settings_pw_hint'] ?></p>
                    </div>
                    <div class="settings-field">
                        <label for="confirm_password"><?= $t['settings_confirm_pw'] ?></label>
                        <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn-save"><?= $t['settings_update_pw'] ?></button>
                </form>
            </div>

            <?php elseif ($tab === 'danger'): ?>
            <div class="settings-section">
                <?php if ($business): ?>
                <h2><?= $t['settings_delete_business'] ?></h2>
                <div class="danger-zone" style="margin-bottom:2rem">
                    <p><?= $t['settings_delete_biz_explain'] ?></p>
                    <?php if ($bizProductCount > 0 || $bizOpenOrders > 0): ?>
                        <?php if ($bizProductCount > 0): ?>
                        <p><?= sprintf($t['settings_delete_biz_products'], $bizProductCount) ?> <a href="/products/"><?= $t['settings_delete_biz_goto_products'] ?></a></p>
                        <?php endif; ?>
                        <?php if ($bizOpenOrders > 0): ?>
                        <p><?= sprintf($t['settings_delete_biz_orders'], $bizOpenOrders) ?></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p><?= $t['settings_delete_biz_warning'] ?></p>
                        <form method="POST" action="/dashboard-vendor/settings/business-delete-action.php">
                            <?= csrf_input() ?>
                            <div class="settings-field">
                                <label for="delete_biz_password"><?= $t['settings_confirm_pw_label'] ?></label>
                                <input type="password" id="delete_biz_password" name="password" required autocomplete="current-password">
                            </div>
                            <button type="submit" class="btn-danger"><?= $t['settings_delete_biz_confirm'] ?></button>
                        </form>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <h2><?= $t['settings_delete_account'] ?></h2>
                <div class="danger-zone">
                    <p><?= $t['vendor_delete_warning'] ?></p>
                    <form method="POST" action="/dashboard-vendor/settings/delete-action.php">
                        <?= csrf_input() ?>
                        <div class="settings-field">
                            <label for="delete_password"><?= $t['settings_confirm_pw_label'] ?></label>
                            <input type="password" id="delete_password" name="password" required autocomplete="current-password">
                        </div>
                        <button type="submit" class="btn-danger"><?= $t['settings_delete_confirm'] ?></button>
                    </form>
                </div>
            </div>

            <?php endif; ?>

        </div>
    </div>
</main>

<?php require __DIR__ . '/../../footer/footer.php'; ?>

<?php if ($tab === 'address' && $business): ?>
<script src="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js"></script>
<script src="/js/boundary.js"></script>
<script>
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
const existingLat = <?= $business['lat'] ? (float)$business['lat'] : 'null' ?>;
const existingLng = <?= $business['lng'] ? (float)$business['lng'] : 'null' ?>;

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
</script>
<?php endif; ?>

</body>
</html>
