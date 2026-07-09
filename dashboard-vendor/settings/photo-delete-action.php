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
$photoId   = (int)($_POST['photo_id'] ?? 0);
$uploadDir = __DIR__ . '/../../uploads/';

$stmt = $pdo->prepare('SELECT id FROM businesses WHERE user_id = ? LIMIT 1');
$stmt->execute([$userId]);
$businessId = $stmt->fetchColumn();

if (!$businessId || !$photoId) {
    header('Location: /dashboard-vendor/settings/?tab=business');
    exit;
}

$stmt = $pdo->prepare('SELECT filename FROM photos WHERE id = ? AND business_id = ?');
$stmt->execute([$photoId, $businessId]);
$filename = $stmt->fetchColumn();

if ($filename) {
    $stmt = $pdo->prepare('DELETE FROM photos WHERE id = ? AND business_id = ?');
    $stmt->execute([$photoId, $businessId]);
    if (file_exists($uploadDir . $filename)) {
        @unlink($uploadDir . $filename);
    }
    $_SESSION['settings_success'] = 'Photo deleted.';
}

header('Location: /dashboard-vendor/settings/?tab=business');
exit;
