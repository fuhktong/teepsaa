<?php
session_start();
require __DIR__ . '/../../config/db.php';
require __DIR__ . '/../../config/csrf.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'buyer') {
    header('Location: /login-buyer/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dashboard-buyer/settings/?tab=address');
    exit;
}

csrf_verify();

$userId      = $_SESSION['user_id'];
$phone       = trim($_POST['phone'] ?? '');
$houseNumber = trim($_POST['house_number'] ?? '');
$address     = trim($_POST['address'] ?? '');
$notes       = trim($_POST['address_notes'] ?? '');
$khan        = trim($_POST['khan'] ?? '');
$sangkat     = trim($_POST['sangkat'] ?? '');
$lat         = $_POST['lat'] ?? '';
$lng         = $_POST['lng'] ?? '';

$latVal = $lat !== '' ? filter_var($lat, FILTER_VALIDATE_FLOAT) : null;
$lngVal = $lng !== '' ? filter_var($lng, FILTER_VALIDATE_FLOAT) : null;

$stmt = $pdo->prepare('UPDATE buyers SET phone = ?, house_number = ?, address = ?, address_notes = ?, khan = ?, sangkat = ?, lat = ?, lng = ? WHERE id = ?');
$stmt->execute([$phone ?: null, $houseNumber ?: null, $address ?: null, $notes ?: null, $khan ?: null, $sangkat ?: null, $latVal, $lngVal, $userId]);

$_SESSION['settings_success'] = 'Delivery address saved.';
header('Location: /dashboard-buyer/settings/?tab=address');
exit;
