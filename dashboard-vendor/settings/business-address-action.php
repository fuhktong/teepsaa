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

$userId      = $_SESSION['user_id'];
$name        = trim($_POST['business_name'] ?? '');
$houseNumber = trim($_POST['house_number'] ?? '');
$address     = trim($_POST['address'] ?? '');
$notes       = trim($_POST['address_notes'] ?? '');
$khan        = trim($_POST['khan'] ?? '');
$sangkat     = trim($_POST['sangkat'] ?? '');
$lat         = $_POST['lat'] ?? '';
$lng         = $_POST['lng'] ?? '';

if (!$name) {
    $_SESSION['settings_error'] = 'Business name is required.';
    header('Location: /dashboard-vendor/settings/?tab=business');
    exit;
}

$latVal = $lat !== '' ? filter_var($lat, FILTER_VALIDATE_FLOAT) : null;
$lngVal = $lng !== '' ? filter_var($lng, FILTER_VALIDATE_FLOAT) : null;

$stmt = $pdo->prepare('
    UPDATE businesses
    SET name = ?, house_number = ?, address = ?, address_notes = ?, khan = ?, sangkat = ?, lat = ?, lng = ?
    WHERE user_id = ?
');
$stmt->execute([
    $name,
    $houseNumber ?: null,
    $address     ?: null,
    $notes       ?: null,
    $khan        ?: null,
    $sangkat     ?: null,
    $latVal,
    $lngVal,
    $userId,
]);

$_SESSION['settings_success'] = 'Business address updated.';
header('Location: /dashboard-vendor/settings/?tab=business');
exit;
