<?php
session_start();
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';

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
$photoId   = (int)($_POST['photo_id']   ?? 0);
$productId = (int)($_POST['product_id'] ?? 0);

$stmt = $pdo->prepare('
    SELECT pp.id
    FROM product_photos pp
    JOIN products p ON p.id = pp.product_id
    JOIN businesses b ON b.id = p.business_id
    WHERE pp.id = ? AND pp.product_id = ? AND b.user_id = ?
');
$stmt->execute([$photoId, $productId, $userId]);

if ($stmt->fetch()) {
    $pdo->prepare('UPDATE product_photos SET is_primary = 0 WHERE product_id = ?')->execute([$productId]);
    $pdo->prepare('UPDATE product_photos SET is_primary = 1 WHERE id = ?')->execute([$photoId]);
}

header('Location: /products/?action=edit&id=' . $productId);
exit;
