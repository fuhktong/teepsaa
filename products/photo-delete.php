<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';

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
$photoId   = (int)($_POST['photo_id']   ?? 0);
$productId = (int)($_POST['product_id'] ?? 0);

$stmt = $pdo->prepare('
    SELECT pp.filename, pp.is_primary
    FROM product_photos pp
    JOIN products p ON p.id = pp.product_id
    JOIN businesses b ON b.id = p.business_id
    WHERE pp.id = ? AND pp.product_id = ? AND b.user_id = ?
');
$stmt->execute([$photoId, $productId, $userId]);
$photo = $stmt->fetch();

if ($photo) {
    $filePath = __DIR__ . '/../uploads/' . $photo['filename'];
    if (file_exists($filePath)) @unlink($filePath);
    $pdo->prepare('DELETE FROM product_photos WHERE id = ?')->execute([$photoId]);

    if ($photo['is_primary']) {
        $next = $pdo->prepare('SELECT id FROM product_photos WHERE product_id = ? ORDER BY sort_order ASC, id ASC LIMIT 1');
        $next->execute([$productId]);
        $nextId = $next->fetchColumn();
        if ($nextId) {
            $pdo->prepare('UPDATE product_photos SET is_primary = 1 WHERE id = ?')->execute([$nextId]);
        }
    }
}

header('Location: /products/?action=edit&id=' . $productId);
exit;
