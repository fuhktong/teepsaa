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
    header('Location: /products/');
    exit;
}

csrf_verify();

$userId    = $_SESSION['user_id'];
$productId = (int)($_POST['product_id'] ?? 0);

$stmt = $pdo->prepare('SELECT id FROM businesses WHERE user_id = ? AND approved = 1');
$stmt->execute([$userId]);
$ownedIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (!empty($ownedIds)) {
    $placeholders = implode(',', array_fill(0, count($ownedIds), '?'));
    $stmt = $pdo->prepare("UPDATE products SET active = 1 - active WHERE id = ? AND business_id IN ($placeholders)");
    $stmt->execute(array_merge([$productId], array_map('intval', $ownedIds)));
}

header('Location: /products/?action=edit&id=' . $productId);
exit;
