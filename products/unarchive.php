<?php
session_start();
require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header('Location: /login-vendor/'); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /products/'); exit;
}

csrf_verify();

$userId    = $_SESSION['user_id'];
$productId = (int)($_POST['product_id'] ?? 0);

$stmt = $pdo->prepare('SELECT id FROM businesses WHERE user_id = ? AND approved = 1');
$stmt->execute([$userId]);
$ownedIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (!empty($ownedIds)) {
    $ph = implode(',', array_fill(0, count($ownedIds), '?'));
    $pdo->prepare("UPDATE products SET archived = 0, active = 0 WHERE id = ? AND business_id IN ($ph)")
        ->execute(array_merge([$productId], array_map('intval', $ownedIds)));
}

header('Location: /products/?tab=archive');
exit;
