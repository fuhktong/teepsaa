<?php
// Reply in a support thread — the app's version of the chat box on
// messages-vendor/thread.php, which posts to api/messages/reply.php.
//
// Only 'open' threads take a reply. The website's own endpoint is looser — it
// refuses closed threads but would accept one that is still pending — while the
// page it serves shows no reply box on a pending request at all. The app
// follows the page, not the gap: a pending request is waiting on teepsaa, and
// letting a vendor pile messages onto it would leave them believing they had
// answered someone.
//
// No email and no notification: teepsaa reads these in admin/messages, and the
// website sends nothing on a vendor reply either.

require __DIR__ . '/../../config/api.php';

api_require_method('POST');

$vendor = api_require_vendor($pdo);
$userId = (int)$vendor['id'];

$input    = api_body();
$threadId = (int)($input['id'] ?? 0);
$body     = trim((string)($input['body'] ?? ''));

if (!$threadId)   api_json(['error' => 'missing_id'], 400);
if ($body === '') api_json(['error' => 'missing_body'], 400);

// 2000 is the website's maxlength on the textarea and the check behind it. A
// form has a browser in front of it to keep to that; an endpoint has nothing,
// so the limit has to be enforced here or the column decides for us.
if (mb_strlen($body) > 2000) {
    api_json(['error' => 'too_long', 'max' => 2000], 400);
}

$stmt = $pdo->prepare("
    SELECT id, status FROM support_threads
     WHERE id = ? AND sender_id = ? AND sender_role = 'vendor'
");
$stmt->execute([$threadId, $userId]);
$thread = $stmt->fetch();

if (!$thread) api_json(['error' => 'not_found'], 404);

if ($thread['status'] !== 'open') {
    api_json(['error' => 'not_open', 'status' => $thread['status']], 409);
}

$pdo->prepare('INSERT INTO support_messages (thread_id, sender, body) VALUES (?, ?, ?)')
    ->execute([$threadId, 'vendor', $body]);

$messageId = (int)$pdo->lastInsertId();

// Bumped so the thread climbs back to the top of the list, the same as the
// website does. Without it a reply would leave the thread sorted by whenever
// teepsaa last spoke.
$pdo->prepare('UPDATE support_threads SET updated_at = NOW() WHERE id = ?')
    ->execute([$threadId]);

// The whole message is sent back, not just an ok, so the app draws the bubble
// from the row that exists rather than from what it hoped it wrote.
api_json([
    'ok'      => true,
    'message' => [
        'id'         => $messageId,
        'mine'       => true,
        'sender'     => 'vendor',
        'body'       => $body,
        'created_at' => date('Y-m-d H:i:s'),
    ],
]);
