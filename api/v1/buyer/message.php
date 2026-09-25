<?php
// GET /api/v1/buyer/message.php?id=12[&after=340]
//
// One support thread — messages-buyer/thread.php for the app, with
// api/messages/poll.php's `after` for fetching only what is new. Opening a
// thread marks teepsaa's messages in it read, as the website page does; this
// GET writes on purpose (see api/v1/message.php).

require __DIR__ . '/../../../config/api.php';

api_require_method('GET');

$buyer  = api_require_buyer($pdo);
$userId = (int)$buyer['id'];

$threadId = (int)($_GET['id'] ?? 0);
$after    = max(0, (int)($_GET['after'] ?? 0));

if (!$threadId) api_json(['error' => 'missing_id'], 400);

// sender_role is half the ownership check: buyer 12 and vendor 12 are
// different people with separate id sequences.
$stmt = $pdo->prepare("
    SELECT id, subject, status, issue_type, created_at, updated_at
      FROM support_threads
     WHERE id = ? AND sender_id = ? AND sender_role = 'buyer'
");
$stmt->execute([$threadId, $userId]);
$thread = $stmt->fetch();

if (!$thread) api_json(['error' => 'not_found'], 404);

$pdo->prepare("
    UPDATE support_messages SET read_at = NOW()
     WHERE thread_id = ? AND sender = 'admin' AND read_at IS NULL
")->execute([$threadId]);

$msgStmt = $pdo->prepare('
    SELECT id, sender, body, created_at
      FROM support_messages
     WHERE thread_id = ? AND id > ?
     ORDER BY id ASC
');
$msgStmt->execute([$threadId, $after]);

$messages = [];
foreach ($msgStmt->fetchAll() as $m) {
    $messages[] = [
        'id'         => (int)$m['id'],
        'mine'       => $m['sender'] !== 'admin',
        'sender'     => $m['sender'],
        'body'       => $m['body'],
        'created_at' => $m['created_at'],
    ];
}

api_json([
    'id'         => (int)$thread['id'],
    'subject'    => $thread['subject'],
    'status'     => $thread['status'],
    'issue_type' => $thread['issue_type'] ?: null,
    // The website shows no reply box on a pending or closed thread.
    'can_reply'  => $thread['status'] === 'open',
    'created_at' => $thread['created_at'],
    'updated_at' => $thread['updated_at'],
    'messages'   => $messages,
]);
