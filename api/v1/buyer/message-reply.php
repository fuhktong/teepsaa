<?php
// POST /api/v1/buyer/message-reply.php   {"id": 12, "body": "..."}
//
// A reply in a support thread — messages-buyer/reply.php for the app. Only an
// 'open' thread takes one: reply.php itself refuses only closed threads, but
// the page never offers a reply box on a pending request, and the app follows
// the page (the same call as api/v1/message-reply.php). No email and no
// notification, as on the website.

require __DIR__ . '/../../../config/api.php';

api_require_method('POST');

$buyer  = api_require_buyer($pdo);
$userId = (int)$buyer['id'];

$input    = api_body();
$threadId = (int)($input['id'] ?? 0);
$body     = trim((string)($input['body'] ?? ''));

if (!$threadId)   api_json(['error' => 'missing_id'], 400);
if ($body === '') api_json(['error' => 'missing_body'], 400);
if (mb_strlen($body) > 2000) api_json(['error' => 'too_long', 'max' => 2000], 400);

$stmt = $pdo->prepare("
    SELECT id, status FROM support_threads
     WHERE id = ? AND sender_id = ? AND sender_role = 'buyer'
");
$stmt->execute([$threadId, $userId]);
$thread = $stmt->fetch();

if (!$thread) api_json(['error' => 'not_found'], 404);
if ($thread['status'] !== 'open') api_json(['error' => 'not_open', 'status' => $thread['status']], 409);

$pdo->prepare("INSERT INTO support_messages (thread_id, sender, body) VALUES (?, 'buyer', ?)")
    ->execute([$threadId, $body]);
$messageId = (int)$pdo->lastInsertId();

$pdo->prepare('UPDATE support_threads SET updated_at = NOW() WHERE id = ?')->execute([$threadId]);

api_json([
    'ok'      => true,
    'message' => [
        'id'         => $messageId,
        'mine'       => true,
        'sender'     => 'buyer',
        'body'       => $body,
        'created_at' => date('Y-m-d H:i:s'),
    ],
]);
