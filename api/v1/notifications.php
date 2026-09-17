<?php
/**
 * GET /api/v1/notifications.php?limit=20&offset=0
 *
 * The vendor's notification list, newest first — the app's version of
 * /notifications/. Same table, same (role, user_id) key, so the phone and the
 * website show one another's read marks.
 *
 * The text is rendered here rather than in the app because the wording lives in
 * lang/, in the vendor's own language. `type` and `data` come back alongside it
 * so a later app version can render its own text without a new endpoint.
 */
require __DIR__ . '/../../config/api.php';
require_once __DIR__ . '/../../config/notify.php';

api_require_method('GET');
$vendor = api_require_vendor($pdo);
$userId = (int)$vendor['id'];

$lang = in_array($vendor['lang'] ?? 'en', ['en', 'km'], true) ? $vendor['lang'] : 'en';
$t    = require __DIR__ . '/../../lang/' . $lang . '.php';

// Clamped, not trusted: a limit of 100000 is a way to ask the server to fall over.
$limit  = max(1, min(50, (int)($_GET['limit'] ?? 20)));
$offset = max(0, (int)($_GET['offset'] ?? 0));

$totalStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE role = ? AND user_id = ?');
$totalStmt->execute(['vendor', $userId]);
$total = (int)$totalStmt->fetchColumn();

$unreadStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE role = ? AND user_id = ? AND read_at IS NULL');
$unreadStmt->execute(['vendor', $userId]);
$unread = (int)$unreadStmt->fetchColumn();

// LIMIT/OFFSET are inlined because they are ints this file just clamped;
// binding them as strings is what breaks the query under emulated prepares.
$stmt = $pdo->prepare(
    'SELECT id, type, message, data, link, read_at, created_at
       FROM notifications
      WHERE role = ? AND user_id = ?
      ORDER BY created_at DESC, id DESC
      LIMIT ' . $limit . ' OFFSET ' . $offset
);
$stmt->execute(['vendor', $userId]);
$rows = $stmt->fetchAll();

/**
 * Which app screen a notification should open, if any.
 *
 * The stored link is a website path, which a bundled app cannot follow, so it
 * is translated into a screen name and an id the app already knows how to open.
 * Refund rows are sent to the refund screen rather than the order screen the
 * website links to: the confirm-received button only exists there, and
 * "mark it received" is the whole point of that notification.
 */
function app_target(array $row): ?array {
    $refundTypes = ['refund_requested', 'refund_approved', 'refund_rejected',
                    'refund_sent', 'return_dispatched', 'return_received'];

    $link = (string)($row['link'] ?? '');
    $id   = null;
    if (preg_match('~[?&]id=([A-Za-z0-9_-]+)~', $link, $m)) $id = $m[1];

    if ($id !== null && str_contains($link, '/orders-vendor/')) {
        return ['screen' => in_array($row['type'], $refundTypes, true) ? 'refund' : 'order', 'id' => $id];
    }
    if (str_contains($link, '/products')) return ['screen' => 'products', 'id' => null];

    // Bank details, and anything added to the website after this app shipped.
    // No target means the row simply isn't tappable, which is honest.
    return null;
}

$items = [];
foreach ($rows as $r) {
    $items[] = [
        'id'         => (int)$r['id'],
        'type'       => $r['type'],
        'text'       => notification_text($r, $t),
        'data'       => !empty($r['data']) ? (json_decode($r['data'], true) ?: null) : null,
        'read'       => $r['read_at'] !== null,
        'target'     => app_target($r),
        'created_at' => $r['created_at'],
    ];
}

api_json([
    'notifications' => $items,
    'total'         => $total,
    'unread'        => $unread,
    'has_more'      => ($offset + count($items)) < $total,
]);
