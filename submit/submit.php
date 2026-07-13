<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header('Location: /login-vendor/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /submit/');
    exit;
}

csrf_verify();

$stmt = $pdo->prepare('SELECT email_verified_at FROM vendors WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$vendor = $stmt->fetch();
if (!$vendor || !$vendor['email_verified_at']) {
    $_SESSION['submit_error'] = 'Please verify your email address before submitting a business.';
    header('Location: /resend-verification/');
    exit;
}

$stmt = $pdo->prepare('SELECT COUNT(*) FROM businesses WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
if ($stmt->fetchColumn() > 0) {
    $_SESSION['submit_error'] = 'Your account already has a business.';
    header('Location: /submit/');
    exit;
}

$name         = trim($_POST['name'] ?? '');
$description  = trim($_POST['description'] ?? '');
$category_id  = (int)($_POST['category_id'] ?? 0);
$house_number = trim($_POST['house_number'] ?? '');
$address      = trim($_POST['address'] ?? '');
$khan         = trim($_POST['khan'] ?? '');
$sangkat      = trim($_POST['sangkat'] ?? '');
$lat          = $_POST['lat'] ?? '';
$lng          = $_POST['lng'] ?? '';

if (!$name) {
    $_SESSION['submit_error'] = 'Business name is required.';
    header('Location: /submit/');
    exit;
}

if (!$category_id) {
    $_SESSION['submit_error'] = 'Please select a category.';
    header('Location: /submit/');
    exit;
}

if (!is_numeric($lat) || !is_numeric($lng)) {
    $_SESSION['submit_error'] = 'Please click the map to set a location.';
    header('Location: /submit/');
    exit;
}

$catStmt = $pdo->prepare('SELECT name FROM categories WHERE id = ?');
$catStmt->execute([$category_id]);
$categoryName = $catStmt->fetchColumn() ?: '';

// Link promo code to business if vendor registered with one
$vendorPromo = $pdo->prepare('SELECT promo_code FROM vendors WHERE id = ?');
$vendorPromo->execute([$_SESSION['user_id']]);
$vendorPromoCode = $vendorPromo->fetchColumn();

$promoCodeId = null;
if ($vendorPromoCode) {
    $pcStmt = $pdo->prepare('SELECT id FROM promo_codes WHERE code = ? AND active = 1 AND (uses_limit IS NULL OR uses_count < uses_limit)');
    $pcStmt->execute([$vendorPromoCode]);
    $promoCodeId = $pcStmt->fetchColumn() ?: null;
    if ($promoCodeId) {
        $pdo->prepare('UPDATE promo_codes SET uses_count = uses_count + 1 WHERE id = ?')->execute([$promoCodeId]);
    }
}

$stmt = $pdo->prepare('INSERT INTO businesses (user_id, name, category, description, house_number, address, khan, sangkat, lat, lng, promo_code_id, public_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$stmt->execute([$_SESSION['user_id'], $name, $categoryName, $description, $house_number, $address, $khan, $sangkat, $lat, $lng, $promoCodeId, uuid_v4()]);
$business_id = $pdo->lastInsertId();

$allowed_types = ['image/jpeg', 'image/png'];
$max_size      = 2 * 1024 * 1024;
$upload_dir    = __DIR__ . '/../uploads/';
$photo_count   = 0;

if (!empty($_FILES['photos']['name'][0])) {
    foreach ($_FILES['photos']['error'] as $i => $error) {
        if ($photo_count >= 5) break;
        if ($error !== UPLOAD_ERR_OK) continue;

        $tmp  = $_FILES['photos']['tmp_name'][$i];
        $size = $_FILES['photos']['size'][$i];
        $type = image_type_from_magic($tmp);

        if (!in_array($type, $allowed_types, true) || $size > $max_size) continue;

        $ext      = $type === 'image/png' ? 'png' : 'jpg';
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;

        if (move_uploaded_file($tmp, $upload_dir . $filename)) {
            $stmt = $pdo->prepare('INSERT INTO photos (business_id, filename) VALUES (?, ?)');
            $stmt->execute([$business_id, $filename]);
            $photo_count++;
        }
    }
}

if (!empty($_FILES['banner']['name']) && $_FILES['banner']['error'] === UPLOAD_ERR_OK) {
    $tmp  = $_FILES['banner']['tmp_name'];
    $size = $_FILES['banner']['size'];
    $type = image_type_from_magic($tmp);

    if (in_array($type, $allowed_types, true) && $size <= 4 * 1024 * 1024) {
        $ext      = $type === 'image/png' ? 'png' : 'jpg';
        $filename = 'banner_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
        if (move_uploaded_file($tmp, $upload_dir . $filename)) {
            $pdo->prepare('UPDATE businesses SET banner = ? WHERE id = ?')->execute([$filename, $business_id]);
        }
    }
}

$_SESSION['submit_success'] = 'Business submitted! It will appear on the map once approved.';
header('Location: /dashboard-vendor/');
exit;
