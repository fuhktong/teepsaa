<?php
session_start();
require __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'buyer') {
    echo json_encode(['error' => 'login_required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$buyerId   = (int)$_SESSION['user_id'];
$productId = (int)($_POST['product_id'] ?? 0);

if (!$productId) {
    http_response_code(400);
    echo json_encode(['error' => 'missing_product_id']);
    exit;
}

$check = $pdo->prepare('SELECT id FROM wishlists WHERE buyer_user_id = ? AND product_id = ?');
$check->execute([$buyerId, $productId]);

if ($check->fetch()) {
    $pdo->prepare('DELETE FROM wishlists WHERE buyer_user_id = ? AND product_id = ?')
        ->execute([$buyerId, $productId]);
    echo json_encode(['wishlisted' => false]);
} else {
    $pdo->prepare('INSERT INTO wishlists (buyer_user_id, product_id) VALUES (?, ?)')
        ->execute([$buyerId, $productId]);
    echo json_encode(['wishlisted' => true]);
}
