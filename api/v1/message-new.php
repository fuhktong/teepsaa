<?php
// Start a support request — the app's version of contact-vendor/submit.php.
//
// One pending request at a time. That rule is the whole queue discipline of
// teepsaa support: a vendor with a question they have already asked should be
// sent back to it, not allowed to ask it three more ways. The form and the
// submit on the website both check it, so this does too.
//
// The thread opens as 'pending', which is read-only on both platforms until
// teepsaa answers and the reply flips it to 'open'. So this endpoint writes the
// first message and nothing more can be said until support picks it up.

require __DIR__ . '/../../config/api.php';

api_require_method('POST');

$vendor = api_require_vendor($pdo);
$userId = (int)$vendor['id'];

$input     = api_body();
$issueType = trim((string)($input['issue_type'] ?? ''));
$subject   = trim((string)($input['subject'] ?? ''));
$body      = trim((string)($input['body'] ?? ''));
// The app never holds a database id, so the order is named by its public id —
// the same string the orders list and the order screen already use.
$orderKey  = trim((string)($input['order_id'] ?? ''));

// Kept identical to contact-vendor/submit.php. These strings are stored as
// written and shown back on the admin side, so they are not free text: a
// spelling that drifts would file the request under a category nobody reads.
$validIssues = ['Order dispute', 'Payout issue', 'Product/listing issue', 'Account issue', 'Other'];

if (!in_array($issueType, $validIssues, true)) {
    api_json(['error' => 'bad_issue_type', 'allowed' => $validIssues], 400);
}
if ($subject === '') api_json(['error' => 'missing_subject'], 400);
if ($body === '')    api_json(['error' => 'missing_body'], 400);

// The website truncates an over-long subject to fit the column. Refusing is the
// better answer for an endpoint: a vendor whose title was quietly cut in half
// has no way to tell it happened.
if (mb_strlen($subject) > 255) api_json(['error' => 'subject_too_long', 'max' => 255], 400);
if (mb_strlen($body) > 2000)   api_json(['error' => 'too_long', 'max' => 2000], 400);

$pendingStmt = $pdo->prepare("
    SELECT id FROM support_threads
     WHERE sender_id = ? AND sender_role = 'vendor' AND status = 'pending'
     LIMIT 1
");
$pendingStmt->execute([$userId]);
if ($pendingId = (int)($pendingStmt->fetchColumn() ?: 0)) {
    api_json(['error' => 'pending_exists', 'pending_thread_id' => $pendingId], 409);
}

// An order can be attached for context, and it has to be one of this vendor's
// own — otherwise the reference is a way to ask teepsaa about a stranger's
// order. The website drops an order it cannot verify and files the message
// without it; saying so is better than sending a request that has quietly lost
// the one detail it was about.
$orderRef = null;
if ($orderKey !== '') {
    $orderStmt = $pdo->prepare('
        SELECT o.id, o.created_at
          FROM orders o
          JOIN businesses b ON b.id = o.business_id
         WHERE o.public_id = ? AND b.user_id = ?
         LIMIT 1
    ');
    $orderStmt->execute([$orderKey, $userId]);
    $order = $orderStmt->fetch();
    if (!$order) api_json(['error' => 'order_not_found'], 404);

    $orderRef = date('ymd', strtotime($order['created_at']))
              . '-' . str_pad((string)$order['id'], 4, '0', STR_PAD_LEFT);
}

// The reference goes into the message text, exactly as the website writes it.
// support_threads has no order column, so this line is the only record of what
// the request is about, and admin/messages reads it as plain text.
$firstMessage = ($orderRef ? "Order: #$orderRef\n\n" : '') . $body;

// Both rows or neither. A thread with no message in it shows up as a blank
// request on the admin side and cannot be replied to.
$pdo->beginTransaction();
try {
    $pdo->prepare("
        INSERT INTO support_threads (sender_id, sender_role, subject, issue_type, status)
        VALUES (?, 'vendor', ?, ?, 'pending')
    ")->execute([$userId, $subject, $issueType]);

    $threadId = (int)$pdo->lastInsertId();

    $pdo->prepare("
        INSERT INTO support_messages (thread_id, sender, body) VALUES (?, 'vendor', ?)
    ")->execute([$threadId, $firstMessage]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

api_json([
    'ok'        => true,
    'thread_id' => $threadId,
    'status'    => 'pending',
], 201);
