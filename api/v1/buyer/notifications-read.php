<?php
// POST /api/v1/buyer/notifications-read.php   {"id": 12}  or  {}
//
// Marks one notification read, or every unread one when no id is sent — the
// contract of /api/notifications/mark-read.php and of the vendor's
// api/v1/notifications-read.php. An id that is already read, or is somebody
// else's, is not an error; the WHERE clause is what keeps buyers apart.

require __DIR__ . '/../../../config/api.php';

api_require_method('POST');

$buyer  = api_require_buyer($pdo);
$userId = (int)$buyer['id'];

$id = (int)(api_body()['id'] ?? 0);

if ($id > 0) {
    $pdo->prepare('UPDATE notifications SET read_at = NOW()
                    WHERE id = ? AND role = ? AND user_id = ? AND read_at IS NULL')
        ->execute([$id, 'buyer', $userId]);
} else {
    $pdo->prepare('UPDATE notifications SET read_at = NOW()
                    WHERE role = ? AND user_id = ? AND read_at IS NULL')
        ->execute(['buyer', $userId]);
}

$unreadStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE role = ? AND user_id = ? AND read_at IS NULL');
$unreadStmt->execute(['buyer', $userId]);

api_json(['ok' => true, 'unread' => (int)$unreadStmt->fetchColumn()]);
