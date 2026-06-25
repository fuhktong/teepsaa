<?php
session_start();
require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login-buyer/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /cart/');
    exit;
}

csrf_verify();

$userId     = $_SESSION['user_id'];
$cartItemId = (int)($_POST['cart_item_id'] ?? 0);
$action     = $_POST['action'] ?? '';
$quantity   = max(0, (int)($_POST['quantity'] ?? 0));

if ($action === 'remove' || $quantity === 0) {
    $stmt = $pdo->prepare('DELETE FROM cart_items WHERE id = ? AND buyer_user_id = ?');
    $stmt->execute([$cartItemId, $userId]);
} else {
    // Fetch item with effective stock to cap the quantity
    $stmt = $pdo->prepare('
        SELECT ci.id, COALESCE(pv.stock, p.stock) AS effective_stock
        FROM cart_items ci
        JOIN products p ON p.id = ci.product_id
        LEFT JOIN product_variants pv ON pv.id = ci.variant_id
        WHERE ci.id = ? AND ci.buyer_user_id = ?
    ');
    $stmt->execute([$cartItemId, $userId]);
    $item = $stmt->fetch();

    if ($item) {
        $quantity = min($quantity, (int)$item['effective_stock']);
        if ($quantity === 0) {
            $pdo->prepare('DELETE FROM cart_items WHERE id = ? AND buyer_user_id = ?')->execute([$cartItemId, $userId]);
        } else {
            $pdo->prepare('UPDATE cart_items SET quantity = ? WHERE id = ? AND buyer_user_id = ?')->execute([$quantity, $cartItemId, $userId]);
        }
    }
}

header('Location: /cart/');
exit;
