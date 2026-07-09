<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'buyer') {
    header('Location: /login-buyer/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /search/');
    exit;
}

csrf_verify();

$userId    = $_SESSION['user_id'];
$productId = (int)($_POST['product_id'] ?? 0);
$variantId = (int)($_POST['variant_id'] ?? 0) ?: null;
$redirect  = $_POST['redirect'] ?? '/search/';
if (!preg_match('#^/(?!/)#', $redirect)) {
    $redirect = '/search/';
}

$stmt = $pdo->prepare('SELECT id, stock, active FROM products WHERE id = ?');
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product || !$product['active']) {
    $_SESSION['cart_error'] = 'This product is unavailable.';
    header('Location: ' . $redirect);
    exit;
}

// If a variant was selected, validate it; otherwise ensure this product has no variants
if ($variantId !== null) {
    $stmt = $pdo->prepare('SELECT id, stock FROM product_variants WHERE id = ? AND product_id = ?');
    $stmt->execute([$variantId, $productId]);
    $variant = $stmt->fetch();

    if (!$variant || $variant['stock'] < 1) {
        $_SESSION['cart_error'] = 'This size is unavailable.';
        header('Location: ' . $redirect);
        exit;
    }
} else {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM product_variants WHERE product_id = ?');
    $stmt->execute([$productId]);
    if ($stmt->fetchColumn() > 0) {
        $_SESSION['cart_error'] = 'Please select a size.';
        header('Location: ' . $redirect);
        exit;
    }

    if ($product['stock'] < 1) {
        $_SESSION['cart_error'] = 'This product is unavailable.';
        header('Location: ' . $redirect);
        exit;
    }
}

$stockLimit = $variantId !== null ? $variant['stock'] : $product['stock'];

// Manual upsert — ON DUPLICATE KEY UPDATE can't handle NULL variant_id correctly
if ($variantId !== null) {
    $stmt = $pdo->prepare('SELECT id, quantity FROM cart_items WHERE buyer_user_id = ? AND product_id = ? AND variant_id = ?');
    $stmt->execute([$userId, $productId, $variantId]);
} else {
    $stmt = $pdo->prepare('SELECT id, quantity FROM cart_items WHERE buyer_user_id = ? AND product_id = ? AND variant_id IS NULL');
    $stmt->execute([$userId, $productId]);
}
$existing = $stmt->fetch();

if ($existing) {
    if ($existing['quantity'] >= $stockLimit) {
        $_SESSION['cart_error'] = 'You already have the maximum available quantity in your cart.';
        header('Location: ' . $redirect);
        exit;
    }
    $pdo->prepare('UPDATE cart_items SET quantity = quantity + 1 WHERE id = ?')->execute([$existing['id']]);
} else {
    $pdo->prepare('INSERT INTO cart_items (buyer_user_id, product_id, variant_id, quantity) VALUES (?, ?, ?, 1)')
        ->execute([$userId, $productId, $variantId]);
}

$_SESSION['cart_success'] = 'Added to cart.';
header('Location: ' . $redirect);
exit;
