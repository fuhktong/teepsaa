<?php
session_start([
    'gc_maxlifetime'  => 28800,
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

$stmt = $pdo->prepare('SELECT id FROM businesses WHERE user_id = ? AND approved = 1 AND suspended = 0');
$stmt->execute([$userId]);
$ownedIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

$publicId = null;
if (!empty($ownedIds)) {
    $placeholders = implode(',', array_fill(0, count($ownedIds), '?'));

    // Confirm the product is owned, and grab its shop + current featured state.
    $stmt = $pdo->prepare("SELECT public_id, business_id, is_featured FROM products WHERE id = ? AND business_id IN ($placeholders)");
    $stmt->execute(array_merge([$productId], array_map('intval', $ownedIds)));
    $prod = $stmt->fetch();

    if ($prod) {
        $publicId = $prod['public_id'];
        if ((int)$prod['is_featured'] === 1) {
            // Already featured → un-feature it.
            $pdo->prepare('UPDATE products SET is_featured = 0 WHERE id = ?')->execute([$productId]);
        } else {
            // Only one featured product per shop: clear the shop's others first.
            $pdo->prepare('UPDATE products SET is_featured = 0 WHERE business_id = ?')->execute([(int)$prod['business_id']]);
            $pdo->prepare('UPDATE products SET is_featured = 1 WHERE id = ?')->execute([$productId]);
        }
    }
}

header('Location: ' . ($publicId ? '/products/?action=edit&id=' . $publicId : '/products/'));
exit;
