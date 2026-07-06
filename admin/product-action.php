<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/admin-auth.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: /login-admin/');
    exit;
}

admin_require('products');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/products.php');
    exit;
}

csrf_verify();

$action    = $_POST['action'] ?? '';
$productId = (int)($_POST['product_id'] ?? 0);

if (!$productId) {
    header('Location: /admin/products.php');
    exit;
}

if ($action === 'set_royalty_add_on') {
    $rate = round((float)($_POST['royalty_add_on'] ?? 0) / 100, 4);
    if ($rate < 0 || $rate > 1) {
        $_SESSION['admin_error'] = 'Rate must be between 0% and 100%.';
        header('Location: /admin/product.php?id=' . $productId);
        exit;
    }
    $pdo->prepare('UPDATE products SET royalty_add_on = ? WHERE id = ?')
        ->execute([$rate, $productId]);
    $_SESSION['admin_success'] = 'Product royalty add-on saved.';

} elseif ($action === 'toggle_active') {
    $pdo->prepare('UPDATE products SET active = 1 - active WHERE id = ?')
        ->execute([$productId]);
    $_SESSION['admin_success'] = 'Product status updated.';

} elseif ($action === 'delete_photo') {
    $photoId = (int)($_POST['photo_id'] ?? 0);
    if ($photoId) {
        $stmt = $pdo->prepare('SELECT filename, is_primary FROM product_photos WHERE id = ? AND product_id = ?');
        $stmt->execute([$photoId, $productId]);
        $photo = $stmt->fetch();
        if ($photo) {
            $pdo->prepare('DELETE FROM product_photos WHERE id = ?')->execute([$photoId]);
            $file = __DIR__ . '/../uploads/' . $photo['filename'];
            if (file_exists($file)) unlink($file);
            // If deleted photo was primary, promote the next one
            if ($photo['is_primary']) {
                $pdo->prepare('UPDATE product_photos SET is_primary = 1 WHERE product_id = ? ORDER BY sort_order ASC, id ASC LIMIT 1')
                    ->execute([$productId]);
            }
        }
    }
    $_SESSION['admin_success'] = 'Photo removed.';

} elseif ($action === 'delete_product') {
    // Delete all photo files first
    $stmt = $pdo->prepare('SELECT filename FROM product_photos WHERE product_id = ?');
    $stmt->execute([$productId]);
    foreach ($stmt->fetchAll() as $ph) {
        $file = __DIR__ . '/../uploads/' . $ph['filename'];
        if (file_exists($file)) unlink($file);
    }
    $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$productId]);
    $_SESSION['admin_success'] = 'Product deleted.';
    header('Location: /admin/products.php');
    exit;
}

header('Location: /admin/product.php?id=' . $productId);
exit;
