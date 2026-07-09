<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

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

if (empty($_FILES['banner']['name'])) {
    header('Location: /dashboard-vendor/settings/?tab=business');
    exit;
}

$tmp  = $_FILES['banner']['tmp_name'];
$size = $_FILES['banner']['size'];
$mime = image_type_from_magic($tmp);

if (!in_array($mime, $allowed, true) || $size > 4 * 1024 * 1024) {
    $_SESSION['settings_error'] = 'Invalid file. JPG or PNG only, max 4MB.';
    header('Location: /dashboard-vendor/settings/?tab=business');
    exit;
}

$ext      = $mime === 'image/png' ? 'png' : 'jpg';
$filename = 'banner_' . $userId . '_' . time() . '.' . $ext;

if (move_uploaded_file($tmp, $uploadDir . $filename)) {
    $stmt = $pdo->prepare('SELECT banner FROM businesses WHERE user_id = ?');
    $stmt->execute([$userId]);
    $old = $stmt->fetchColumn();
    if ($old && file_exists($uploadDir . $old)) {
        @unlink($uploadDir . $old);
    }

    $stmt = $pdo->prepare('UPDATE businesses SET banner = ? WHERE user_id = ?');
    $stmt->execute([$filename, $userId]);
    $_SESSION['settings_success'] = 'Banner updated.';
} else {
    $_SESSION['settings_error'] = 'Upload failed. Please try again.';
}

header('Location: /dashboard-vendor/settings/?tab=business');
exit;
