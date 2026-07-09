<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    http_response_code(403); echo json_encode(['ok' => false]); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['ok' => false]); exit;
}

csrf_verify();

$userId    = $_SESSION['user_id'];
$productId = (int)($_POST['product_id'] ?? 0);
$photoIds  = array_values(array_map('intval', (array)($_POST['photo_ids'] ?? [])));

if (!$productId || empty($photoIds)) {
    http_response_code(400); echo json_encode(['ok' => false]); exit;
}

// Verify product ownership
$stmt = $pdo->prepare('
    SELECT p.id FROM products p
    JOIN businesses b ON b.id = p.business_id
    WHERE p.id = ? AND b.user_id = ?
');
$stmt->execute([$productId, $userId]);
if (!$stmt->fetch()) {
    http_response_code(403); echo json_encode(['ok' => false]); exit;
}

// Verify all photo IDs belong to this product
$ph   = implode(',', array_fill(0, count($photoIds), '?'));
$stmt = $pdo->prepare("SELECT id FROM product_photos WHERE id IN ($ph) AND product_id = ?");
$stmt->execute(array_merge($photoIds, [$productId]));
$valid = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (count($valid) !== count($photoIds)) {
    http_response_code(400); echo json_encode(['ok' => false]); exit;
}

foreach ($photoIds as $order => $photoId) {
    $pdo->prepare('UPDATE product_photos SET sort_order = ?, is_primary = ? WHERE id = ?')
        ->execute([$order, $order === 0 ? 1 : 0, $photoId]);
}

echo json_encode(['ok' => true]);
