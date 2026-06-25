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
$from      = $_POST['from'] ?? 'products';

$stmt = $pdo->prepare('SELECT id FROM businesses WHERE user_id = ? AND approved = 1');
$stmt->execute([$userId]);
$ownedIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (!empty($ownedIds)) {
    $ph   = implode(',', array_fill(0, count($ownedIds), '?'));
    $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND business_id IN ($ph)");
    $stmt->execute(array_merge([$productId], array_map('intval', $ownedIds)));

    if ($stmt->fetch()) {
        // Delete photo files
        $photos = $pdo->prepare('SELECT filename FROM product_photos WHERE product_id = ?');
        $photos->execute([$productId]);
        foreach ($photos->fetchAll(PDO::FETCH_COLUMN) as $filename) {
            $path = __DIR__ . '/../uploads/' . $filename;
            if (file_exists($path)) @unlink($path);
        }

        // Nullify order_items reference (preserve history)
        $pdo->prepare('UPDATE order_items SET product_id = NULL WHERE product_id = ?')->execute([$productId]);

        // Remove from carts
        $pdo->prepare('DELETE FROM cart_items WHERE product_id = ?')->execute([$productId]);

        // Delete product (product_photos cascade)
        $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$productId]);
    }
}

header('Location: /products/' . ($from === 'archive' ? '?tab=archive' : ''));
exit;
