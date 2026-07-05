<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
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

$userId = $_SESSION['user_id'];
$lat    = $_POST['lat'] ?? '';
$lng    = $_POST['lng'] ?? '';

$latVal = filter_var($lat, FILTER_VALIDATE_FLOAT);
$lngVal = filter_var($lng, FILTER_VALIDATE_FLOAT);

if ($latVal === false || $lngVal === false) {
    $_SESSION['settings_error'] = 'Please set a pin on the map.';
    header('Location: /dashboard-vendor/settings/?tab=business');
    exit;
}

$stmt = $pdo->prepare('UPDATE businesses SET lat = ?, lng = ? WHERE user_id = ?');
$stmt->execute([$latVal, $lngVal, $userId]);

$_SESSION['settings_success'] = 'Business location updated.';
header('Location: /dashboard-vendor/settings/?tab=business');
exit;
