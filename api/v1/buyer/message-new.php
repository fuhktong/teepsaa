<?php
// POST /api/v1/buyer/message-new.php
//   {"issue_type": "Order issue", "subject": "...", "body": "...", "order_id": "<public id>"}
//
// Start a support request — contact-buyer/submit.php for the app. The thread
// opens as 'pending' and stays read-only until teepsaa answers.
//
// Two places where this is stricter than the website, as the vendor's
// api/v1/message-new.php is: an over-long subject is refused rather than
// quietly cut, and an order that is not this buyer's is refused rather than
// quietly dropped from the message.

require __DIR__ . '/../../../config/api.php';
require __DIR__ . '/../../../config/buyer-orders.php';

api_require_method('POST');

$buyer  = api_require_buyer($pdo);
$userId = (int)$buyer['id'];

$input     = api_body();
$issueType = trim((string)($input['issue_type'] ?? ''));
$subject   = trim((string)($input['subject'] ?? ''));
$body      = trim((string)($input['body'] ?? ''));
$orderKey  = trim((string)($input['order_id'] ?? ''));

// Kept identical to contact-buyer/submit.php — these are stored as written.
$validIssues = ['Order issue', 'Payment issue', 'Account issue', 'Other'];

if (!in_array($issueType, $validIssues, true)) {
    api_json(['error' => 'bad_issue_type', 'allowed' => $validIssues], 400);
}
if ($subject === '') api_json(['error' => 'missing_subject'], 400);
if ($body === '')    api_json(['error' => 'missing_body'], 400);
if (mb_strlen($subject) > 255) api_json(['error' => 'subject_too_long', 'max' => 255], 400);
if (mb_strlen($body) > 2000)   api_json(['error' => 'too_long', 'max' => 2000], 400);

$pendingStmt = $pdo->prepare("
    SELECT id FROM support_threads
     WHERE sender_id = ? AND sender_role = 'buyer' AND status = 'pending'
     LIMIT 1
");
$pendingStmt->execute([$userId]);
if ($pendingId = (int)($pendingStmt->fetchColumn() ?: 0)) {
    api_json(['error' => 'pending_exists', 'pending_thread_id' => $pendingId], 409);
}

// The order must be this buyer's own.
$orderRef = null;
if ($orderKey !== '') {
    $os = $pdo->prepare('SELECT id, created_at FROM orders WHERE public_id = ? AND buyer_user_id = ? LIMIT 1');
    $os->execute([$orderKey, $userId]);
    $order = $os->fetch();
    if (!$order) api_json(['error' => 'order_not_found'], 404);
    $orderRef = buyer_order_ref((int)$order['id'], $order['created_at']);
}

// support_threads has no order column; this line is the only record of it,
// written exactly as the website writes it.
$firstMessage = ($orderRef ? "Order: #$orderRef\n\n" : '') . $body;

$pdo->beginTransaction();
try {
    $pdo->prepare("
        INSERT INTO support_threads (sender_id, sender_role, subject, issue_type, status)
        VALUES (?, 'buyer', ?, ?, 'pending')
    ")->execute([$userId, $subject, $issueType]);
    $threadId = (int)$pdo->lastInsertId();

    $pdo->prepare("INSERT INTO support_messages (thread_id, sender, body) VALUES (?, 'buyer', ?)")
        ->execute([$threadId, $firstMessage]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

api_json(['ok' => true, 'thread_id' => $threadId, 'status' => 'pending'], 201);
