<?php
session_start([
    'gc_maxlifetime'  => 28800,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/notify.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['buyer', 'vendor'], true)) {
    echo json_encode(['count' => 0, 'items' => []]);
    exit;
}

$lang = current_lang();
$t    = require __DIR__ . '/../../lang/' . $lang . '.php';

$role   = $_SESSION['role'];
$userId = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare(
    'SELECT id, type, message, data, link, read_at, created_at
     FROM notifications
     WHERE role = ? AND user_id = ?
     ORDER BY created_at DESC
     LIMIT 15'
);
$stmt->execute([$role, $userId]);
$rows = $stmt->fetchAll();

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE role = ? AND user_id = ? AND read_at IS NULL');
$countStmt->execute([$role, $userId]);
$count = (int)$countStmt->fetchColumn();

$items = [];
foreach ($rows as $r) {
    $items[] = [
        'id'      => (int)$r['id'],
        'type'    => $r['type'],
        'message' => notification_text($r, $t),
        'link'    => $r['link'],
        'read'    => $r['read_at'] !== null,
        'time'    => notification_ago($r['created_at'], $t),
    ];
}

echo json_encode(['count' => $count, 'items' => $items]);
