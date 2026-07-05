<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

require __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$orderId = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
if (!$orderId) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found']);
    exit;
}

$userId  = $_SESSION['user_id'];
$role    = $_SESSION['role'] ?? '';
$isAdmin = !empty($_SESSION['is_admin']);

if ($isAdmin) {
    $stmt = $pdo->prepare('SELECT status FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
} elseif ($role === 'buyer') {
    $stmt = $pdo->prepare('SELECT status FROM orders WHERE id = ? AND buyer_user_id = ?');
    $stmt->execute([$orderId, $userId]);
} elseif ($role === 'vendor') {
    $stmt = $pdo->prepare('
        SELECT o.status FROM orders o
        JOIN businesses b ON b.id = o.business_id
        WHERE o.id = ? AND b.user_id = ?
    ');
    $stmt->execute([$orderId, $userId]);
} else {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$row = $stmt->fetch();
if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found']);
    exit;
}

echo json_encode(['status' => $row['status']]);
