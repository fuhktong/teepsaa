<?php
// The Messages tab — the vendor's support threads with teepsaa, matching
// messages-vendor/index.php.
//
// "Messages" here means support, not the buyer: buyers and vendors never
// message each other on teepsaa. Every thread is between this vendor and the
// teepsaa team, which is why 'admin' is the only other sender and why an unread
// message always means teepsaa has replied.
//
// A thread has three states. 'pending' is a request teepsaa has not picked up
// yet — read-only, no reply box. 'open' is a live conversation. 'closed' is
// finished, and the way back is a new request, not a reply to an old one.

require __DIR__ . '/../../config/api.php';

api_require_method('GET');

$vendor = api_require_vendor($pdo);
$userId = (int)$vendor['id'];

$limit  = max(1, min(50, (int)($_GET['limit'] ?? 20)));
$offset = max(0, (int)($_GET['offset'] ?? 0));

$countStmt = $pdo->prepare("
    SELECT COUNT(*) FROM support_threads
     WHERE sender_id = ? AND sender_role = 'vendor'
");
$countStmt->execute([$userId]);
$total = (int)$countStmt->fetchColumn();

// Unread across every thread, for the count on the tab. Only admin messages
// can be unread to a vendor — the vendor's own are read by definition.
$unreadStmt = $pdo->prepare("
    SELECT COUNT(*)
      FROM support_messages m
      JOIN support_threads t ON t.id = m.thread_id
     WHERE t.sender_id = ? AND t.sender_role = 'vendor'
       AND m.sender = 'admin' AND m.read_at IS NULL
");
$unreadStmt->execute([$userId]);
$unread = (int)$unreadStmt->fetchColumn();

// One pending request at a time, the rule contact-vendor/ enforces on both the
// form and the submit. The app needs the same answer before it offers a "new
// message" button, and the id so it can send the vendor to the request they
// already have instead of a dead end.
$pendingStmt = $pdo->prepare("
    SELECT id FROM support_threads
     WHERE sender_id = ? AND sender_role = 'vendor' AND status = 'pending'
     LIMIT 1
");
$pendingStmt->execute([$userId]);
$pendingId = (int)($pendingStmt->fetchColumn() ?: 0);

// LIMIT and OFFSET are inlined rather than bound because MySQL will not take
// them as strings from an emulated prepare. They are integers by the casts
// above, so there is nothing left in them to inject.
$stmt = $pdo->prepare("
    SELECT t.id, t.subject, t.status, t.issue_type, t.created_at, t.updated_at,
           (SELECT m.body   FROM support_messages m WHERE m.thread_id = t.id ORDER BY m.id DESC LIMIT 1) AS last_body,
           (SELECT m.sender FROM support_messages m WHERE m.thread_id = t.id ORDER BY m.id DESC LIMIT 1) AS last_sender,
           (SELECT COUNT(*) FROM support_messages m
             WHERE m.thread_id = t.id AND m.sender = 'admin' AND m.read_at IS NULL) AS unread
      FROM support_threads t
     WHERE t.sender_id = ? AND t.sender_role = 'vendor'
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
        // Cut to the same 80 characters the website's list shows, so a long
        // message is not shipped in full to be thrown away by the app.
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
