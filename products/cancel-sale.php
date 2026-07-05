<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
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
$bizIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (!$productId || empty($bizIds)) {
    header('Location: /products/');
    exit;
}

$ph = implode(',', array_fill(0, count($bizIds), '?'));
$check = $pdo->prepare("SELECT id FROM products WHERE id = ? AND business_id IN ($ph)");
$check->execute(array_merge([$productId], array_map('intval', $bizIds)));
if (!$check->fetch()) {
    header('Location: /products/');
    exit;
}

$pdo->prepare('UPDATE products SET sale_price = NULL, sale_ends_at = NULL WHERE id = ?')
    ->execute([$productId]);

header('Location: /products/?action=edit&id=' . $productId);
exit;
