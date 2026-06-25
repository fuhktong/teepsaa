<?php
session_start();
require __DIR__ . '/../../config/db.php';
require __DIR__ . '/../../config/csrf.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header('Location: /login-vendor/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dashboard-vendor/settings/?tab=business');
    exit;
}

csrf_verify();

$userId    = $_SESSION['user_id'];
$uploadDir = __DIR__ . '/../../uploads/';
$allowed   = ['image/jpeg', 'image/png'];

$stmt = $pdo->prepare('SELECT id FROM businesses WHERE user_id = ? LIMIT 1');
$stmt->execute([$userId]);
$businessId = $stmt->fetchColumn();

if (!$businessId) {
    header('Location: /dashboard-vendor/settings/?tab=business');
    exit;
}

if (empty($_FILES['gallery_photo']['name'][0])) {
    header('Location: /dashboard-vendor/settings/?tab=business');
    exit;
}

$stmt = $pdo->prepare('SELECT COUNT(*) FROM photos WHERE business_id = ?');
$stmt->execute([$businessId]);
$existing = (int)$stmt->fetchColumn();

$uploaded = 0;
$errors   = 0;

foreach ($_FILES['gallery_photo']['error'] as $i => $error) {
    if ($existing + $uploaded >= 10) break;
    if ($error !== UPLOAD_ERR_OK) { $errors++; continue; }

    $tmp  = $_FILES['gallery_photo']['tmp_name'][$i];
    $size = $_FILES['gallery_photo']['size'][$i];
    $mime = image_type_from_magic($tmp);

    if (!in_array($mime, $allowed, true) || $size > 4 * 1024 * 1024) { $errors++; continue; }

    $ext      = $mime === 'image/png' ? 'png' : 'jpg';
    $filename = 'gallery_' . $businessId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

    if (move_uploaded_file($tmp, $uploadDir . $filename)) {
        $stmt = $pdo->prepare('INSERT INTO photos (business_id, filename) VALUES (?, ?)');
        $stmt->execute([$businessId, $filename]);
        $uploaded++;
    } else {
        $errors++;
    }
}

if ($uploaded > 0 && $errors === 0) {
    $_SESSION['settings_success'] = $uploaded === 1 ? 'Photo added.' : "$uploaded photos added.";
} elseif ($uploaded > 0 && $errors > 0) {
    $_SESSION['settings_success'] = "$uploaded photo(s) added. $errors could not be uploaded.";
} else {
    $_SESSION['settings_error'] = 'Upload failed. JPG or PNG only, max 4MB each.';
}

header('Location: /dashboard-vendor/settings/?tab=business');
exit;
