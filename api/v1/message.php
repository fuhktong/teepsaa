<?php
// One support thread and everything said in it — the app's version of
// messages-vendor/thread.php.
//
// Opening a thread is also how a vendor reads it: the website marks teepsaa's
// messages read on the way in, and this does the same. That means a GET here
// writes, which is unusual but deliberate — it is the only moment we know for
// certain the vendor has seen them.
//
// `after` is for polling. The app asks for what came in since the last id it
// holds, and gets the envelope back every time regardless, so a thread that
// teepsaa has since picked up (pending → open) or closed shows its new state
// without a second call.

require __DIR__ . '/../../config/api.php';

api_require_method('GET');

$vendor = api_require_vendor($pdo);
$userId = (int)$vendor['id'];

$threadId = (int)($_GET['id'] ?? 0);
$after    = max(0, (int)($_GET['after'] ?? 0));

if (!$threadId) api_json(['error' => 'missing_id'], 400);

// sender_role is half the ownership check, not decoration: buyers and vendors
// are separate tables with their own id sequences, so buyer 12 and vendor 12
// are different people and a thread of theirs must never cross over.
$stmt = $pdo->prepare("
    SELECT id, subject, status, issue_type, created_at, updated_at
      FROM support_threads
     WHERE id = ? AND sender_id = ? AND sender_role = 'vendor'
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
        // 'admin' is teepsaa; anything else on a vendor's thread is the vendor.
        // Said as a flag rather than a role name so the app never has to know
        // which enum values exist to draw a bubble on the right side.
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
    // Worked out here so the app's reply box and the endpoint can never
    // disagree about whether a reply is allowed. A pending request is waiting
    // on teepsaa to pick it up, and the website shows it with no reply box at
    // all; a closed one is finished and needs a new request instead.
    'can_reply'  => $thread['status'] === 'open',
    'created_at' => $thread['created_at'],
    'updated_at' => $thread['updated_at'],
    'messages'   => $messages,
]);
