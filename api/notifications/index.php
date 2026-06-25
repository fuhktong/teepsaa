<?php
session_start();
require __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['buyer', 'vendor'], true)) {
    echo json_encode(['count' => 0, 'items' => []]);
    exit;
}

$role   = $_SESSION['role'];
$userId = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare(
    'SELECT id, type, message, link, read_at, created_at
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
    $diff = time() - strtotime($r['created_at']);
    if ($diff < 60)    $ago = 'just now';
    elseif ($diff < 3600)  $ago = floor($diff / 60) . 'm ago';
    elseif ($diff < 86400) $ago = floor($diff / 3600) . 'h ago';
    else                   $ago = floor($diff / 86400) . 'd ago';

    $items[] = [
        'id'      => (int)$r['id'],
        'type'    => $r['type'],
        'message' => $r['message'],
        'link'    => $r['link'],
        'read'    => $r['read_at'] !== null,
        'time'    => $ago,
    ];
}

echo json_encode(['count' => $count, 'items' => $items]);
