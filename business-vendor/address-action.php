<?php
session_start([
    'gc_maxlifetime'  => 28800,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header('Location: /login-vendor/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /business-vendor/');
    exit;
}

csrf_verify();

$userId      = $_SESSION['user_id'];
$houseNumber = trim($_POST['house_number'] ?? '');
$address     = trim($_POST['address'] ?? '');
$notes       = trim($_POST['address_notes'] ?? '');
$khan        = trim($_POST['khan'] ?? '');
$sangkat     = trim($_POST['sangkat'] ?? '');
$city        = trim($_POST['city'] ?? '');
$lat         = $_POST['lat'] ?? '';
$lng         = $_POST['lng'] ?? '';

// Only accept a city we actually deliver in (Phnom Penh for now).
$cities = require __DIR__ . '/../config/cities.php';
$city   = in_array($city, $cities, true) ? $city : ($cities[0] ?? null);

$latVal = $lat !== '' ? filter_var($lat, FILTER_VALIDATE_FLOAT) : null;
$lngVal = $lng !== '' ? filter_var($lng, FILTER_VALIDATE_FLOAT) : null;

$stmt = $pdo->prepare('
    UPDATE businesses
    SET house_number = ?, address = ?, address_notes = ?, khan = ?, sangkat = ?, city = ?, lat = ?, lng = ?
    WHERE user_id = ? AND deleted_at IS NULL
');
$stmt->execute([
    $houseNumber ?: null,
    $address     ?: null,
    $notes       ?: null,
    $khan        ?: null,
    $sangkat     ?: null,
    $city,
    $latVal,
    $lngVal,
    $userId,
]);

$_SESSION['settings_success'] = 'Address updated.';
header('Location: /business-vendor/');
exit;
