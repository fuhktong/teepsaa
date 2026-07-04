<?php
session_start();
require __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/notify.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['buyer', 'vendor'], true)) {
    echo json_encode(['count' => 0, 'items' => []]);
    exit;
}

$lang = in_array($_SESSION['lang'] ?? 'km', ['en', 'km'], true) ? ($_SESSION['lang'] ?? 'km') : 'km';
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
    $diff = time() - strtotime($r['created_at']);
    if ($diff < 60)        $ago = $t['notif_just_now'];
    elseif ($diff < 3600)  $ago = sprintf($t['notif_min_ago'], floor($diff / 60));
    elseif ($diff < 86400) $ago = sprintf($t['notif_hour_ago'], floor($diff / 3600));
    else                   $ago = sprintf($t['notif_day_ago'], floor($diff / 86400));

    $items[] = [
        'id'      => (int)$r['id'],
        'type'    => $r['type'],
        'message' => notification_text($r, $t),
        'link'    => $r['link'],
        'read'    => $r['read_at'] !== null,
        'time'    => $ago,
    ];
}

echo json_encode(['count' => $count, 'items' => $items]);
