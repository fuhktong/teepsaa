<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'buyer') {
    header('Location: /login-buyer/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dashboard-buyer/');
    exit;
}

csrf_verify();

$userId  = $_SESSION['user_id'];
$itemId  = (int)($_POST['order_item_id'] ?? 0);
$rating  = (int)($_POST['rating'] ?? 0);
$comment = trim($_POST['comment'] ?? '');

if (!$itemId || $rating < 1 || $rating > 5) {
    header('Location: /dashboard-buyer/');
    exit;
}

if (mb_strlen($comment) > 1000) {
    $comment = mb_substr($comment, 0, 1000);
}

$stmt = $pdo->prepare('
    SELECT oi.id, oi.product_id,
           o.id AS order_id, o.public_id AS order_public_id, o.status, o.business_id
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    WHERE oi.id = ? AND o.buyer_user_id = ?
');
$stmt->execute([$itemId, $userId]);
$item = $stmt->fetch();

if (!$item) {
    header('Location: /dashboard-buyer/');
    exit;
}

if (!in_array($item['status'], ['delivered', 'completed'])) {
    header('Location: /dashboard-buyer/order.php?id=' . $item['order_public_id']);
    exit;
}

$check = $pdo->prepare('SELECT id FROM reviews WHERE order_item_id = ?');
$check->execute([$itemId]);
if ($check->fetch()) {
    header('Location: /dashboard-buyer/order.php?id=' . $item['order_public_id']);
    exit;
}

$insert = $pdo->prepare('
    INSERT INTO reviews (order_item_id, buyer_id, product_id, business_id, rating, comment)
    VALUES (?, ?, ?, ?, ?, ?)
');
$insert->execute([
    $itemId,
    $userId,
    $item['product_id'] ?: null,
    $item['business_id'],
    $rating,
    $comment ?: null,
]);

$_SESSION['flash_success'] = 'Review submitted. Thank you!';
header('Location: /dashboard-buyer/order.php?id=' . $item['order_public_id']);
exit;
