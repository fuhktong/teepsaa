<?php
session_start([
    'gc_maxlifetime'  => 28800,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) && empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// One browser can hold an admin session and a buyer/vendor one at the same
// time, so the caller says which hat it is wearing: admin pages send
// as=admin, and it is honoured only if this session really is an admin.
$isAdmin = !empty($_SESSION['admin_id']) && ($_GET['as'] ?? '') === 'admin';
$role    = $isAdmin ? '' : ($_SESSION['role'] ?? '');
$userId  = $_SESSION['user_id'] ?? 0;

// The page behind this endpoint calls admin_require('messages'); without the same
// check here a custom admin who was never granted that section could reach
// the data through the API instead. admin-auth.php's device-restore block is
// inert on this path — it only runs when no admin session exists.
if ($isAdmin) {
    require_once __DIR__ . '/../../config/admin-auth.php';
    if (!admin_can('messages')) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
}

$threadId = (int)($_GET['thread_id'] ?? 0);
$afterId  = (int)($_GET['after'] ?? 0);

if (!$threadId) {
    echo json_encode(['messages' => []]);
    exit;
}

// Verify ownership
if ($role === 'buyer') {
    $stmt = $pdo->prepare("SELECT id FROM support_threads WHERE id = ? AND sender_id = ? AND sender_role = 'buyer'");
    $stmt->execute([$threadId, $userId]);
} elseif ($role === 'vendor') {
    $stmt = $pdo->prepare("SELECT id FROM support_threads WHERE id = ? AND sender_id = ? AND sender_role = 'vendor'");
    $stmt->execute([$threadId, $userId]);
} elseif ($isAdmin) {
    $stmt = $pdo->prepare("SELECT id FROM support_threads WHERE id = ?");
    $stmt->execute([$threadId]);
} else {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

if (!$stmt->fetch()) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// Mark incoming messages as read
if ($role === 'buyer' || $role === 'vendor') {
    $pdo->prepare("UPDATE support_messages SET read_at = NOW() WHERE thread_id = ? AND sender = 'admin' AND read_at IS NULL")
        ->execute([$threadId]);
} elseif ($isAdmin) {
    $pdo->prepare("UPDATE support_messages SET read_at = NOW() WHERE thread_id = ? AND sender IN ('buyer','vendor','guest') AND read_at IS NULL")
        ->execute([$threadId]);
}

$stmt = $pdo->prepare('SELECT id, sender, body, created_at FROM support_messages WHERE thread_id = ? AND id > ? ORDER BY id ASC');
$stmt->execute([$threadId, $afterId]);

echo json_encode(['messages' => $stmt->fetchAll()]);
