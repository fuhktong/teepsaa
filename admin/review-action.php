<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: /login-admin/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/products.php');
    exit;
}

csrf_verify();

$reviewId  = (int)($_POST['review_id'] ?? 0);
$productId = (int)($_POST['product_id'] ?? 0);

if (!$reviewId) {
    header('Location: /admin/products.php');
    exit;
}

$stmt = $pdo->prepare('DELETE FROM reviews WHERE id = ?');
$stmt->execute([$reviewId]);

$_SESSION['admin_success'] = 'Review deleted.';
if (($_POST['redirect_to'] ?? '') === 'reviews') {
    header('Location: /admin/reviews.php');
} else {
    header('Location: /admin/product.php?id=' . $productId);
}
exit;
