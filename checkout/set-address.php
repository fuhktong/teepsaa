<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'buyer') {
    header('Location: /login-buyer/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /checkout/');
    exit;
}

csrf_verify();

$userId = (int)$_SESSION['user_id'];
$addrId = (int)($_POST['address_id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM buyer_addresses WHERE id = ? AND buyer_user_id = ?');
$stmt->execute([$addrId, $userId]);
$addr = $stmt->fetch();

if ($addr) {
    $pdo->prepare('UPDATE buyers SET house_number=?, address=?, address_notes=?, khan=?, sangkat=?, lat=?, lng=? WHERE id=?')
        ->execute([$addr['house_number'], $addr['address'], $addr['address_notes'], $addr['khan'], $addr['sangkat'], $addr['lat'], $addr['lng'], $userId]);

    $pdo->prepare('UPDATE buyer_addresses SET is_default = 0 WHERE buyer_user_id = ?')->execute([$userId]);
    $pdo->prepare('UPDATE buyer_addresses SET is_default = 1 WHERE id = ?')->execute([$addrId]);
}

header('Location: /checkout/');
exit;
