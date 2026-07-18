<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

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

// Mirror the main address into the address book as the default entry —
// otherwise it only lives on the buyers table and never shows in the
// saved-addresses list (and gets silently overwritten on a default switch)
if ($address !== '' || $khan !== '' || $houseNumber !== '') {
    $stmt = $pdo->prepare('SELECT id FROM buyer_addresses WHERE buyer_user_id = ? AND is_default = 1');
    $stmt->execute([$userId]);
    $defaultId = $stmt->fetchColumn();
    if ($defaultId) {
        $pdo->prepare('UPDATE buyer_addresses SET house_number=?, address=?, address_notes=?, khan=?, sangkat=?, lat=?, lng=? WHERE id=?')
            ->execute([$houseNumber ?: null, $address ?: null, $notes ?: null, $khan ?: null, $sangkat ?: null, $latVal, $lngVal, $defaultId]);
    } else {
        $pdo->prepare('INSERT INTO buyer_addresses (buyer_user_id, label, house_number, address, address_notes, khan, sangkat, lat, lng, is_default) VALUES (?,?,?,?,?,?,?,?,?,1)')
            ->execute([$userId, $khan !== '' ? $khan : 'Address', $houseNumber ?: null, $address ?: null, $notes ?: null, $khan ?: null, $sangkat ?: null, $latVal, $lngVal]);
    }
}

$_SESSION['settings_success'] = 'Delivery address saved.';
header('Location: /dashboard-buyer/settings/?tab=address');
exit;
