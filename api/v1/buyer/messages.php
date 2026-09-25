<?php
// GET /api/v1/buyer/messages.php?limit=20&offset=0
//
// The buyer's support threads with teepsaa — messages-buyer/index.php for the
// app, and a copy of the vendor's api/v1/messages.php with the role swapped.
// Buyers and vendors never message each other; every thread is with teepsaa,
// so 'admin' is the only other sender.
//
// 'pending' is a request not yet picked up (read-only), 'open' a live
// conversation, 'closed' finished — a new request, not a reply, is the way back.

require __DIR__ . '/../../../config/api.php';

api_require_method('GET');

$buyer  = api_require_buyer($pdo);
$userId = (int)$buyer['id'];

$limit  = max(1, min(50, (int)($_GET['limit'] ?? 20)));
$offset = max(0, (int)($_GET['offset'] ?? 0));

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM support_threads WHERE sender_id = ? AND sender_role = 'buyer'");
$countStmt->execute([$userId]);
$total = (int)$countStmt->fetchColumn();

$unreadStmt = $pdo->prepare("
    SELECT COUNT(*)
      FROM support_messages m
      JOIN support_threads t ON t.id = m.thread_id
     WHERE t.sender_id = ? AND t.sender_role = 'buyer'
       AND m.sender = 'admin' AND m.read_at IS NULL
");
$unreadStmt->execute([$userId]);
$unread = (int)$unreadStmt->fetchColumn();

// One pending request at a time — contact-buyer/ checks it on the form and the
// submit, so the app is told before it offers a "new message" button.
$pendingStmt = $pdo->prepare("
    SELECT id FROM support_threads
     WHERE sender_id = ? AND sender_role = 'buyer' AND status = 'pending'
     LIMIT 1
");
$pendingStmt->execute([$userId]);
$pendingId = (int)($pendingStmt->fetchColumn() ?: 0);

$stmt = $pdo->prepare("
    SELECT t.id, t.subject, t.status, t.issue_type, t.created_at, t.updated_at,
           (SELECT m.body   FROM support_messages m WHERE m.thread_id = t.id ORDER BY m.id DESC LIMIT 1) AS last_body,
           (SELECT m.sender FROM support_messages m WHERE m.thread_id = t.id ORDER BY m.id DESC LIMIT 1) AS last_sender,
           (SELECT COUNT(*) FROM support_messages m
             WHERE m.thread_id = t.id AND m.sender = 'admin' AND m.read_at IS NULL) AS unread
      FROM support_threads t
     WHERE t.sender_id = ? AND t.sender_role = 'buyer'
     ORDER BY t.updated_at DESC, t.id DESC
     LIMIT $limit OFFSET $offset
");
$stmt->execute([$userId]);

$threads = [];
foreach ($stmt->fetchAll() as $t) {
    $threads[] = [
        'id'          => (int)$t['id'],
        'subject'     => $t['subject'],
        'status'      => $t['status'],
        'issue_type'  => $t['issue_type'] ?: null,
        'preview'     => $t['last_body'] !== null ? mb_substr($t['last_body'], 0, 80) : null,
        'last_sender' => $t['last_sender'],
        'unread'      => (int)$t['unread'],
        'created_at'  => $t['created_at'],
        'updated_at'  => $t['updated_at'],
    ];
}

api_json([
    'threads'           => $threads,
    'total'             => $total,
    'unread'            => $unread,
    'has_more'          => ($offset + count($threads)) < $total,
    'can_start'         => $pendingId === 0,
    'pending_thread_id' => $pendingId ?: null,
]);
