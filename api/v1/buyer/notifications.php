<?php
// GET /api/v1/buyer/notifications.php?limit=20&offset=0
//
// The buyer's notification list, newest first — /notifications/ for the app.
// The same table and the same (role, user_id) key as the website's bell, so
// reading one on the phone clears it on the website too. Written after
// api/v1/notifications.php, the vendor's.
//
// The words come in the language the app asks for (?lang=), not the one saved
// on the account: the app's language switch is what the buyer is looking at.

require __DIR__ . '/../../../config/api.php';
require_once __DIR__ . '/../../../config/notify.php';
require __DIR__ . '/../../../config/buyer-orders.php';

api_require_method('GET');

$buyer  = api_require_buyer($pdo);
$userId = (int)$buyer['id'];
$t      = buyer_order_words();

// Clamped, not trusted — as api/v1/notifications.php.
$limit  = max(1, min(50, (int)($_GET['limit'] ?? 20)));
$offset = max(0, (int)($_GET['offset'] ?? 0));

$totalStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE role = ? AND user_id = ?');
$totalStmt->execute(['buyer', $userId]);
$total = (int)$totalStmt->fetchColumn();

$unreadStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE role = ? AND user_id = ? AND read_at IS NULL');
$unreadStmt->execute(['buyer', $userId]);
$unread = (int)$unreadStmt->fetchColumn();

$stmt = $pdo->prepare(
    'SELECT id, type, message, data, link, read_at, created_at
       FROM notifications
      WHERE role = ? AND user_id = ?
      ORDER BY created_at DESC, id DESC
      LIMIT ' . $limit . ' OFFSET ' . $offset
);
$stmt->execute(['buyer', $userId]);

/**
 * Which app screen a notification opens. The stored link is a website path;
 * the app cannot follow it, so it is turned into a screen it already has.
 * Every buyer notification today points at an order or the cart. Anything
 * added later comes back with no target, and the row is simply not tappable.
 */
function buyer_app_target(array $row): ?array {
    $link = (string)($row['link'] ?? '');
    if (str_contains($link, '/orders-buyer/') && preg_match('~[?&]id=([A-Za-z0-9_-]+)~', $link, $m)) {
        return ['screen' => 'order', 'id' => $m[1]];
    }
    if (str_starts_with($link, '/cart')) return ['screen' => 'cart', 'id' => null];
    if (str_starts_with($link, '/messages-buyer/') && preg_match('~[?&]id=(\d+)~', $link, $m)) {
        return ['screen' => 'thread', 'id' => $m[1]];
    }
    return null;
}

$items = [];
foreach ($stmt->fetchAll() as $r) {
    $items[] = [
        'id'         => (int)$r['id'],
        'type'       => $r['type'],
        'text'       => notification_text($r, $t),
        'data'       => !empty($r['data']) ? (json_decode($r['data'], true) ?: null) : null,
        'read'       => $r['read_at'] !== null,
        'target'     => buyer_app_target($r),
        'created_at' => $r['created_at'],
    ];
}

api_json([
    'notifications' => $items,
    'total'         => $total,
    'unread'        => $unread,
    'has_more'      => ($offset + count($items)) < $total,
]);
