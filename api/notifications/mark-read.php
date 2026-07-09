<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['buyer', 'vendor'], true)) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

$role   = $_SESSION['role'];
$userId = (int)$_SESSION['user_id'];
$id     = (int)($_POST['id'] ?? 0);

if ($id > 0) {
    $pdo->prepare('UPDATE notifications SET read_at = NOW() WHERE id = ? AND role = ? AND user_id = ? AND read_at IS NULL')
        ->execute([$id, $role, $userId]);
} else {
    $pdo->prepare('UPDATE notifications SET read_at = NOW() WHERE role = ? AND user_id = ? AND read_at IS NULL')
        ->execute([$role, $userId]);
}

echo json_encode(['ok' => true]);
