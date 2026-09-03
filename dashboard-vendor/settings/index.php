<?php
session_start([
    'gc_maxlifetime'  => 28800,
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
$validTabs = ['account', 'business', 'password', 'danger'];
$tab       = $_GET['tab'] ?? 'account';
// Address and Bank QR were folded into the Business tab; keep old links working
if ($tab === 'address' || $tab === 'aba-qr') $tab = 'business';
if (!in_array($tab, $validTabs)) $tab = 'account';

$stmt = $pdo->prepare('SELECT name, email, phone, avatar, avatar_color, aba_qr, aba_account_name FROM vendors WHERE id = ?');
$stmt->execute([$userId]);
$vendor = $stmt->fetch();

$stmt = $pdo->prepare('SELECT id, name, name_km, category, description, description_km, house_number, address, address_notes, khan, sangkat, city, lat, lng, banner, approved, rejection_reason, suspended, suspension_reason FROM businesses WHERE user_id = ? AND deleted_at IS NULL LIMIT 1');
$stmt->execute([$userId]);
$business = $stmt->fetch();

// Category tree for the cascade picker. Stored values are English names
// (same comma-separated shape businesses.category has always held); `label`
// is what the picker displays in the current language.
$catTree = [];
if ($tab === 'business' && $business) {
    foreach ($pdo->query('SELECT id, parent_id, name, name_km FROM categories ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $catTree[] = ['id' => (int)$c['id'], 'parent_id' => $c['parent_id'] !== null ? (int)$c['parent_id'] : null, 'name' => $c['name'], 'label' => cat_name($c)];
    }
}

// Storefront layout (lives inside the Business tab): the shop's sellable
// products, plus the current featured pick and slot order so the form can
// pre-fill.
$sfProducts = [];
$sfFeatured = 0;
$sfSlots    = [];
if ($tab === 'business' && $business) {
    $stmt = $pdo->prepare('SELECT id, name, name_km, is_featured, storefront_order FROM products WHERE business_id = ? AND active = 1 AND archived = 0 ORDER BY name ASC');
    $stmt->execute([$business['id']]);
    $sfProducts = $stmt->fetchAll();
    foreach ($sfProducts as $p) {
        if ((int)$p['is_featured'] === 1) $sfFeatured = (int)$p['id'];
        if ($p['storefront_order'] !== null) $sfSlots[(int)$p['storefront_order']] = (int)$p['id'];
    }
    ksort($sfSlots);
    $sfSlots = array_values($sfSlots);
}

$locations = ($tab === 'business' && $business) ? require __DIR__ . '/../../config/phnom-penh-locations.php' : [];
$cities    = ($tab === 'business' && $business) ? require __DIR__ . '/../../config/cities.php' : [];

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
    <?php if ($tab === 'business' && $business): ?>
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
<?php require __DIR__ . '/../../vendor-subnav/vendor-subnav.php'; ?>

<main>
    <h1 style="margin-bottom:1.5rem"><?= $tab === 'business' ? $t['vendor_settings_tab_business'] : $t['settings_title'] ?></h1>

    <div class="settings-wrap">

        <?php if ($tab !== 'business'): // Business is a top-level subnav tab, not a settings tab ?>
        <nav class="settings-nav">
            <a href="?tab=account"  class="<?= $tab === 'account'  ? 'active' : '' ?>"><?= $t['settings_tab_account'] ?></a>
            <a href="?tab=password" class="<?= $tab === 'password' ? 'active' : '' ?>"><?= $t['settings_password_heading'] ?></a>
            <a href="?tab=danger"   class="danger-link <?= $tab === 'danger' ? 'active' : '' ?>"><?= $t['settings_delete_account'] ?></a>
        </nav>
        <?php endif; ?>

        <div class="settings-content<?= $tab === 'business' ? ' settings-content--business' : '' ?>">

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

            <?php elseif ($tab === 'business'): ?>
            <div class="settings-section settings-section--full">
                <h2><?= $t['vendor_settings_tab_business'] ?></h2>

                <?php if (!$business): ?>
                <p style="font-size:0.9rem;color:#6b7280;"><?= $t['vendor_no_business'] ?> <a href="/submit/"><?= $t['vendor_submit_one'] ?></a></p>
                <?php else: ?>

                <?php if ((int)$business['suspended'] === 1): ?>
                <!-- A suspended shop is off the marketplace but the vendor is
                     still here fixing it, so this is where they read why.
                     There is no resubmit button: suspension is lifted by an
                     admin, not by the vendor sending it back to the queue. -->
                <div class="biz-rejected">
                    <p class="biz-rejected-title"><?= $t['vendor_biz_suspended_heading'] ?></p>
                    <?php if (!empty($business['suspension_reason'])): ?>
                    <p class="biz-rejected-reason"><strong><?= $t['vendor_biz_suspended_reason'] ?></strong> <?= nl2br(htmlspecialchars($business['suspension_reason'])) ?></p>
                    <?php else: ?>
                    <p class="biz-rejected-reason"><?= $t['vendor_biz_suspended_no_reason'] ?></p>
                    <?php endif; ?>
                    <p class="biz-rejected-help"><?= $t['vendor_biz_suspended_help'] ?></p>
                </div>
                <?php elseif ((int)$business['approved'] === -1): ?>
                <!-- The only place a rejected vendor is told why, and the only way
                     back into the review queue. -->
                <div class="biz-rejected">
                    <p class="biz-rejected-title"><?= $t['vendor_biz_rejected_heading'] ?></p>
                    <?php if (!empty($business['rejection_reason'])): ?>
                    <p class="biz-rejected-reason"><strong><?= $t['vendor_biz_rejected_reason'] ?></strong> <?= nl2br(htmlspecialchars($business['rejection_reason'])) ?></p>
                    <?php else: ?>
                    <p class="biz-rejected-reason"><?= $t['vendor_biz_rejected_no_reason'] ?></p>
                    <?php endif; ?>
                    <p class="biz-rejected-help"><?= $t['vendor_biz_rejected_help'] ?></p>
                    <form method="POST" action="/dashboard-vendor/settings/business-resubmit-action.php"
                          onsubmit="return confirm('<?= htmlspecialchars($t['vendor_biz_resubmit_confirm'], ENT_QUOTES) ?>')">
                        <?= csrf_input() ?>
                        <button type="submit" class="btn-save" style="margin-top:0"><?= $t['vendor_biz_resubmit'] ?></button>
                    </form>
                </div>
                <?php elseif ((int)$business['approved'] === 0): ?>
                <p class="settings-msg settings-msg--pending"><?= $t['vendor_biz_pending_note'] ?></p>
                <?php endif; ?>

                <div class="biz-card-cols">
                <div>
                <form method="POST" action="/dashboard-vendor/settings/business-action.php" id="business-form">
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
                        <label for="description"><?= $t['vendor_settings_description'] ?> <span class="field-hint" style="font-weight:400;display:inline"><?= $t['vendor_biz_desc_hint'] ?></span></label>
                        <textarea id="description" name="description" rows="4" maxlength="160" placeholder="<?= htmlspecialchars($t['vendor_biz_desc_placeholder']) ?>" oninput="document.getElementById('desc-count').textContent = 160 - this.value.length"><?= htmlspecialchars($business['description'] ?? '') ?></textarea>
                        <p class="field-hint" style="margin:0.3rem 0 0"><span id="desc-count"><?= 160 - mb_strlen($business['description'] ?? '') ?></span> <?= $t['vendor_biz_desc_count'] ?></p>
                    </div>
                    <div class="settings-field">
                        <label for="description_km"><?= $t['vendor_settings_description'] ?> <span class="field-hint" style="font-weight:400;display:inline"><?= $t['form_km_field'] ?> <?= $t['vendor_biz_desc_hint'] ?></span></label>
                        <textarea id="description_km" name="description_km" rows="4" maxlength="160" placeholder="ការពិពណ៌នាហាងជាភាសាខ្មែរ" oninput="document.getElementById('desc-km-count').textContent = 160 - this.value.length"><?= htmlspecialchars($business['description_km'] ?? '') ?></textarea>
                        <p class="field-hint" style="margin:0.3rem 0 0"><span id="desc-km-count"><?= 160 - mb_strlen($business['description_km'] ?? '') ?></span> <?= $t['vendor_biz_desc_count'] ?></p>
                    </div>
                    <?php if (!empty($catTree)): ?>
                    <div class="settings-field">
                        <label><?= $t['vendor_settings_categories'] ?> <span class="field-hint" style="font-weight:400;display:inline"><?= $t['vendor_cat_hint'] ?></span></label>
                        <ul class="psp-cat-chosen" data-cat-chosen hidden></ul>
                        <div data-cat-cascade data-target="biz-category" data-ph-root="<?= htmlspecialchars($t['prod_select_category']) ?>" data-ph-sub="<?= htmlspecialchars($t['prod_select_subcategory']) ?>"></div>
                        <div class="biz-cat-actions">
                            <button type="button" class="biz-cat-btn" data-cat-add><?= $t['vendor_cat_add'] ?></button>
                            <button type="button" class="biz-cat-btn biz-cat-btn--ghost" data-cat-clear><?= $t['vendor_cat_clear'] ?></button>
                        </div>
                        <input type="hidden" id="biz-category" name="category" value="<?= htmlspecialchars($business['category'] ?? '') ?>">
                    </div>
                    <?php endif; ?>
                </form>
                </div>

                <div>
                <div class="settings-field">
                    <label><?= $t['vendor_settings_banner'] ?> <span class="field-hint" style="font-weight:400;display:inline"> <?= $t['vendor_settings_banner_hint'] ?></span></label>
                    <?php if ($business['banner']): ?>
                        <img src="/uploads/<?= htmlspecialchars($business['banner']) ?>" alt="" class="banner-preview">
                    <?php endif; ?>
                    <div class="banner-actions">
                        <form method="POST" action="/dashboard-vendor/settings/banner-action.php" enctype="multipart/form-data" style="display:inline">
                            <?= csrf_input() ?>
                            <label for="banner" class="btn-upload" style="margin-top:0.6rem;display:inline-block"><?= $business['banner'] ? $t['vendor_replace_banner'] : $t['vendor_upload_banner'] ?></label>
                            <input type="file" id="banner" name="banner" accept="image/jpeg,image/png" style="display:none" onchange="this.form.submit()">
                        </form>
                        <?php if ($business['banner']): ?>
                        <form method="POST" action="/dashboard-vendor/settings/banner-action.php" style="display:inline" onsubmit="return confirm('<?= htmlspecialchars($t['vendor_remove_banner_confirm'], ENT_QUOTES) ?>')">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="remove">
                            <button type="submit" class="btn-remove-avatar" style="margin-top:0.6rem"><?= $t['vendor_remove_banner'] ?></button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <p class="field-hint"><?= $t['vendor_banner_upload_hint'] ?></p>
                </div>

                <hr class="form-divider">

                <!-- Storefront layout — same card, under the banner. What shows
                     on the shop page and in what order. -->
                <h3 class="biz-subheading"><?= $t['storefront_heading'] ?></h3>
                <?php if (empty($sfProducts)): ?>
                <p class="field-hint"><?= $t['storefront_no_products'] ?></p>
                <?php else: ?>
                <p class="field-hint" style="margin-bottom:1.25rem"><?= $t['storefront_intro'] ?></p>

                <!-- These fields post with the business form above (form="business-form"),
                     so the single Save button at the bottom saves everything at once. -->
                <div class="settings-field">
                    <label for="featured"><?= $t['storefront_featured_label'] ?> <span class="field-hint" style="font-weight:400;display:inline"><?= $t['storefront_featured_hint'] ?></span></label>
                    <select id="featured" name="featured" form="business-form">
                        <option value="0"><?= $t['storefront_none'] ?></option>
                        <?php foreach ($sfProducts as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= $sfFeatured === (int)$p['id'] ? 'selected' : '' ?>><?= htmlspecialchars(lang_field($p, 'name')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="settings-field">
                    <label><?= $t['storefront_slots_label'] ?> <span class="field-hint" style="font-weight:400;display:inline"><?= $t['storefront_slots_hint'] ?></span></label>
                    <div id="slot-list">
                        <?php $rows = $sfSlots ?: [0]; foreach ($rows as $i => $slotPid): ?>
                        <div class="slot-row">
                            <span class="slot-num"><?= $t['storefront_slot'] ?> #<span class="slot-index"><?= $i + 1 ?></span></span>
                            <select name="slots[]" form="business-form" class="slot-select">
                                <option value="0"><?= $t['storefront_slot_empty'] ?></option>
                                <?php foreach ($sfProducts as $p): ?>
                                <option value="<?= (int)$p['id'] ?>" data-name="<?= htmlspecialchars(lang_field($p, 'name')) ?>" <?= (int)$slotPid === (int)$p['id'] ? 'selected' : '' ?>><?= htmlspecialchars(lang_field($p, 'name')) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="slot-remove" onclick="removeSlot(this)"><?= $t['storefront_remove'] ?></button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn-add-slot" onclick="addSlot()"><?= $t['storefront_add_slot'] ?></button>
                </div>

                <!-- Template cloned by addSlot() to add another empty slot row. -->
                <template id="slot-template">
                    <div class="slot-row">
                        <span class="slot-num"><?= $t['storefront_slot'] ?> #<span class="slot-index"></span></span>
                        <select name="slots[]" form="business-form" class="slot-select">
                            <option value="0"><?= $t['storefront_slot_empty'] ?></option>
                            <?php foreach ($sfProducts as $p): ?>
                            <option value="<?= (int)$p['id'] ?>" data-name="<?= htmlspecialchars(lang_field($p, 'name')) ?>"><?= htmlspecialchars(lang_field($p, 'name')) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="slot-remove" onclick="removeSlot(this)"><?= $t['storefront_remove'] ?></button>
                    </div>
                </template>
                <?php endif; ?>
                </div>
                </div>

                <?php endif; ?>
            </div>

            <?php if ($business): ?>
            <!-- One Save button for the whole Business card (details + storefront),
                 submitting the business form. -->
            <button type="submit" form="business-form" class="btn-save"><?= $t['settings_save'] ?></button>
            <?php endif; ?>

            <?php if ($business): ?>
            <!-- Address — folded into the Business tab (was its own tab) -->
            <div class="settings-section">
                <h2><?= $t['vendor_settings_tab_address'] ?></h2>

                <?php
                $addrParts = array_filter([
                    trim(($business['house_number'] ?? '') . ' ' . ($business['address'] ?? '')),
                    $business['sangkat'] ?? '',
                    $business['khan'] ?? '',
                    $business['city'] ?: 'Phnom Penh',
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
                        <label for="city"><?= $t['settings_address_city'] ?></label>
                        <select id="city" name="city">
                            <?php $selCity = $business['city'] ?: ($cities[0] ?? ''); ?>
                            <?php foreach ($cities as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>" <?= ($selCity === $c) ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                            <?php endforeach; ?>
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
            </div>
            <?php endif; ?>

            <!-- Bank QR — folded into the Business tab (was its own tab) -->
            <div class="settings-section">
                <h2><?= $t['vendor_settings_bank_qr'] ?></h2>
                <p class="field-hint" style="margin-bottom:1.25rem;"><?= $t['vendor_settings_bank_hint'] ?></p>
                <?php if ($vendor['aba_qr']): ?>
                <img src="/uploads/<?= htmlspecialchars($vendor['aba_qr']) ?>" alt="Your Bank QR" style="width:140px;height:140px;object-fit:contain;border:1px solid #e5e7eb;border-radius:6px;display:block;margin-bottom:1.25rem;">
                <?php endif; ?>
                <form method="POST" action="/dashboard-vendor/settings/aba-qr-action.php" enctype="multipart/form-data">
                    <?= csrf_input() ?>
                    <div class="settings-field">
                        <label for="aba_account_name"><?= $t['vendor_qr_account_name'] ?></label>
                        <input type="text" id="aba_account_name" name="aba_account_name" value="<?= htmlspecialchars($vendor['aba_account_name'] ?? '') ?>" maxlength="100" required>
                        <p class="field-hint"><?= $t['vendor_qr_account_name_hint'] ?></p>
                    </div>
                    <div class="settings-field">
                        <label for="aba_qr"><?= $vendor['aba_qr'] ? $t['vendor_replace_qr'] : $t['vendor_upload_qr'] ?> <span class="field-hint" style="font-weight:400;display:inline"><?= $t['vendor_qr_hint'] ?></span></label>
                        <input type="file" id="aba_qr" name="aba_qr" accept="image/jpeg,image/png" <?= $vendor['aba_qr'] ? '' : 'required' ?>>
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

<?php if (!empty($catTree)): ?>
<script type="application/json" id="cat-tree-data"><?= json_encode($catTree, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?></script>
<script src="/js/cat-cascade.js"></script>
<?php endif; ?>

<?php if ($tab === 'business' && $business && !empty($sfProducts)): ?>
<script>
const FEATURED_SUFFIX = <?= json_encode(' ' . $t['storefront_featured_suffix']) ?>;
const ADDED_SUFFIX    = <?= json_encode(' ' . $t['storefront_added_suffix']) ?>;

function renumberSlots() {
    document.querySelectorAll('#slot-list .slot-row').forEach((row, i) => {
        row.querySelector('.slot-index').textContent = i + 1;
    });
}
// A product can appear on the storefront only once. In every slot dropdown,
// disable + relabel any product that's already the featured hero or already
// chosen in another slot — so the vendor sees each product only once, before
// saving (the server dedupes too, as a backstop). Runs on load and on any
// change to the featured pick or a slot.
function syncSlots() {
    const fid   = document.getElementById('featured').value;
    const slots = Array.from(document.querySelectorAll('#slot-list .slot-select'));
    slots.forEach(sel => {
        Array.from(sel.options).forEach(opt => {
            if (opt.value === '0') return;
            const name         = opt.dataset.name || opt.textContent;
            const isFeatured   = fid !== '0' && opt.value === fid;
            const usedElsewhere = slots.some(other => other !== sel && other.value === opt.value);
            if (isFeatured) {
                if (sel.value === opt.value) sel.value = '0';
                opt.disabled = true;
                opt.textContent = name + FEATURED_SUFFIX;
            } else if (usedElsewhere) {
                opt.disabled = true;
                opt.textContent = name + ADDED_SUFFIX;
            } else {
                opt.disabled = false;
                opt.textContent = name;
            }
        });
    });
}
function addSlot() {
    const tpl = document.getElementById('slot-template');
    document.getElementById('slot-list').appendChild(tpl.content.cloneNode(true));
    renumberSlots();
    syncSlots();
}
function removeSlot(btn) {
    btn.closest('.slot-row').remove();
    renumberSlots();
    syncSlots();
}
document.getElementById('featured').addEventListener('change', syncSlots);
document.getElementById('slot-list').addEventListener('change', syncSlots);
syncSlots();
</script>
<?php endif; ?>

<?php if ($tab === 'business' && $business): ?>
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
