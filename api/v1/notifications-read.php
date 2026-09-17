<?php
/**
 * POST /api/v1/notifications-read.php   {"id": 12}  or  {}
 *
 * Marks one notification read, or every unread one when no id is sent — the
 * same two-in-one contract as /api/notifications/mark-read.php, so the bell on
 * the website and the list in the app clear each other.
 *
 * Reading something is not a destructive act, so an id that is already read, or
 * belongs to somebody else, is not an error: the reply is the unread count
 * either way, and the WHERE clause is what keeps one vendor out of another's
 * rows.
 */
require __DIR__ . '/../../config/api.php';

api_require_method('POST');
$vendor = api_require_vendor($pdo);
$userId = (int)$vendor['id'];

$body = api_body();
$id   = (int)($body['id'] ?? 0);

if ($id > 0) {
    $pdo->prepare('UPDATE notifications SET read_at = NOW()
                    WHERE id = ? AND role = ? AND user_id = ? AND read_at IS NULL')
        ->execute([$id, 'vendor', $userId]);
} else {
    $pdo->prepare('UPDATE notifications SET read_at = NOW()
                    WHERE role = ? AND user_id = ? AND read_at IS NULL')
        ->execute(['vendor', $userId]);
}

$unreadStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE role = ? AND user_id = ? AND read_at IS NULL');
$unreadStmt->execute(['vendor', $userId]);

api_json(['ok' => true, 'unread' => (int)$unreadStmt->fetchColumn()]);
